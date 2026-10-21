<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Library\Markers;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CoreBlueprint\Core\DataExchange\Foundation;

/**
 * Inert P2-B import write adapter, deliberately NOT advertised by Provider.
 * Fail-closed, create-only, validates each Base plan against its exact signed
 * whole-document ImportSession. A pending journal receipt prohibits retry.
 *
 * This is not RC1 accepted until real WordPress failure-injection tests pass.
 */
final class ImportApplier {
    public static function apply(array $plan, array $context): array|\WP_Error {
        if (!ImportSession::authorized($context)
            || ($plan['operation'] ?? null) !== Foundation::OP_CREATE
            || !is_string($plan['reference'] ?? null)
            || !is_array($plan['payload'] ?? null)
            || !is_array($plan['payload']['source'] ?? null)
                && ($plan['payload']['record_type'] ?? null) !== 'catalog') {
            return self::fail('cb_notes_import_apply_forbidden');
        }
        $source = ($plan['payload']['record_type'] ?? '') === 'catalog'
            ? ['record_type' => 'catalog', 'reference' => 'catalog:personal',
                'catalog' => $plan['payload']['catalog'] ?? null]
            : $plan['payload']['source'];
        // For the catalog source record, validate against the ORIGINAL
        // archive catalog, not the remapped target catalog.
        if (($plan['payload']['record_type'] ?? '') === 'catalog') {
            $bound = ImportSession::catalog($context);
            if ($bound instanceof \WP_Error) {
                return $bound;
            }
            $source = $bound['source'];
        }
        $expected = ImportPlanner::plan_record($source, Foundation::MODE_CREATE_ONLY, 1, $context);
        if ($expected instanceof \WP_Error || $expected !== $plan) {
            return self::fail('cb_notes_import_plan_changed');
        }
        $owner = get_current_user_id();
        if ($plan['payload']['owner'] !== $owner || !current_user_can('cb_notes_edit')) {
            return self::fail('cb_notes_import_apply_forbidden');
        }
        return $plan['payload']['record_type'] === 'catalog'
            ? self::write_catalog($plan['payload'])
            : self::write_note($plan['payload']);
    }

    /** Safely reconcile owner-private catalog without silently dropping labels. */
    private static function write_catalog(array $payload): array|\WP_Error {
        $owner = get_current_user_id();
        $target = $payload['catalog'];
        $old = get_user_meta($owner, Catalog::USER_META, true);
        $normalized = Catalog::catalog($owner);
        if ($normalized === $target) {
            return ['reference' => 'catalog:personal'];
        }
        $bound = ImportSession::target_plan();
        if ($bound instanceof \WP_Error || $normalized !== $bound['previous_catalog']
            || $target !== $bound['catalog']) {
            return self::fail('cb_notes_import_catalog_stale');
        }
        if ($old === '' || $old === false || $old === null) {
            // WordPress unique metadata alone does not guarantee SQL-level
            // uniqueness under concurrent writes. Verify exactly one row.
            $saved = add_user_meta($owner, Catalog::USER_META, $target, true);
        } elseif (is_array($old) && $old !== []
            && $old === $normalized
            && count(get_user_meta($owner, Catalog::USER_META, false)) === 1) {
            // WordPress uses the fourth arg as the SQL previous-value guard.
            $saved = update_user_meta($owner, Catalog::USER_META, $target, $old);
        } else {
            return self::fail('cb_notes_import_catalog_invalid');
        }
        if (!$saved
            || get_user_meta($owner, Catalog::USER_META, true) !== $target
            || count(get_user_meta($owner, Catalog::USER_META, false)) !== 1) {
            return self::fail('cb_notes_import_catalog_write_failed');
        }
        return ['reference' => 'catalog:personal'];
    }

    /**
     * A reservation is durable BEFORE wp_insert_post. Any ambiguous failure
     * leaves an owner-visible pending receipt rather than making a new Note
     * during an automatic retry. No remote sharing state is written.
     */
    private static function write_note(array $payload): array|\WP_Error {
        $owner = get_current_user_id();
        $record = $payload['source'];
        $mapped = $payload['mapped'];
        if (($mapped['operation'] ?? '') !== 'create'
            || !in_array($mapped['status'] ?? null, ['draft', 'private'], true)
            || Catalog::catalog($owner) !== ImportSession::target_catalog()) {
            return self::fail('cb_notes_import_catalog_stale');
        }
        $key = ImportJournal::reserve($owner, $payload['digest'], $record['reference']);
        if ($key instanceof \WP_Error) {
            return $key;
        }
        $meta = [
            ImportJournal::META => $key,
            Catalog::NOTEBOOK => $mapped['notebook_id'],
            Catalog::CATEGORY => $mapped['category_id'],
            Catalog::TAGS => $mapped['tag_ids'],
        ];
        if ($record['favorite']) {
            $meta[Markers::FAVORITE] = '1';
        }
        if ($record['pinned']) {
            $meta[Markers::PINNED] = '1';
        }
        $values = [
            'post_type' => Plugin::TYPE,
            'post_author' => $owner,
            'post_title' => $record['title'],
            'post_content' => $record['content'],
            'post_status' => $mapped['status'],
            'meta_input' => $meta,
        ];
        if ($record['created_gmt'] !== '0000-00-00 00:00:00') {
            $values['post_date_gmt'] = $record['created_gmt'];
            $values['post_date'] = get_date_from_gmt($record['created_gmt']);
        }
        $id = wp_insert_post($values, true);
        if ($id instanceof \WP_Error || !is_int($id) || $id < 1) {
            return self::fail('cb_notes_import_create_uncertain');
        }
        // WordPress assigns a new modification timestamp on insert. A
        // portability import must restore BOTH UTC timestamps before the
        // journal may become complete. A failed write leaves a pending intent
        // that is never automatically retried or silently marked successful.
        $source_local = static fn(string $gmt): string =>
            $gmt === '0000-00-00 00:00:00' ? $gmt : get_date_from_gmt($gmt);
        global $wpdb;
        $dates_written = $wpdb->update(
            $wpdb->posts,
            [
                'post_date' => $source_local($record['created_gmt']),
                'post_date_gmt' => $record['created_gmt'],
                'post_modified' => $source_local($record['modified_gmt']),
                'post_modified_gmt' => $record['modified_gmt'],
            ],
            ['ID' => $id, 'post_type' => Plugin::TYPE, 'post_author' => $owner],
            ['%s', '%s', '%s', '%s'],
            ['%d', '%s', '%d']
        );
        if ($dates_written === false) {
            return self::fail('cb_notes_import_timestamp_write_failed');
        }
        clean_post_cache($id);
        $post = get_post($id);
        if (!$post instanceof \WP_Post
            || (int)$post->post_author !== $owner
            || $post->post_type !== Plugin::TYPE
            || $post->post_status !== $mapped['status']
            || $post->post_title !== $record['title']
            || $post->post_content !== $record['content']
            || $post->post_date_gmt !== $record['created_gmt']
            || $post->post_modified_gmt !== $record['modified_gmt']
            || $post->post_date !== $source_local($record['created_gmt'])
            || $post->post_modified !== $source_local($record['modified_gmt'])) {
            return self::fail('cb_notes_import_create_uncertain');
        }
        foreach ($meta as $field => $value) {
            if (get_post_meta($id, $field, true) !== $value) {
                return self::fail('cb_notes_import_create_uncertain');
            }
        }
        $verified = ImportJournal::complete($owner, $payload['digest'], $record['reference'], $id);
        if ($verified instanceof \WP_Error) {
            return $verified;
        }
        return ['reference' => $record['reference']];
    }

    private static function fail(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import cannot safely commit this record. Check the import recovery journal.');
    }
}
