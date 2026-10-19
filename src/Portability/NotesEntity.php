<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Access;
use CB\Notes\Library\Markers;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CoreBlueprint\Core\DataExchange\EntityInterface;
use CoreBlueprint\Core\DataExchange\Foundation;

/** Owner-only, read-only first slice of Notes Data Exchange schema v1. */
final class NotesEntity implements EntityInterface {
    private const STATUSES = ['draft', 'private', 'pending', 'future', 'publish'];
    private const PAGE_SIZE = 100;

    public function is_available(): bool {
        return true;
    }

    public function schema_version(): int {
        return 1;
    }

    public function supports_schema_version(int $schema_version): bool {
        // The entity understands schema v1, but Base must still require the
        // independently accepted/advertised import capability.
        return $schema_version === 1;
    }

    public function can_export(array $context = []): bool {
        // Actor is always derived from the authenticated WordPress session.
        // Do not interpret owner/user/author overrides supplied by consumers.
        return get_current_user_id() > 0 && current_user_can('cb_notes_edit')
            && !array_key_exists('owner_id', $context)
            && !array_key_exists('user_id', $context)
            && !array_key_exists('author', $context);
    }

    public function can_import(array $context = []): bool {
        return ImportSession::authorized($context);
    }

    public function export_records(array $context = []): iterable|\WP_Error {
        if (!$this->can_export($context)) {
            return self::error('cb_notes_export_forbidden', 'Notes export is not authorized.');
        }
        $owner = get_current_user_id();
        $catalog = Catalog::catalog($owner);
        // The catalog loader filters malformed legacy entries. Export must not
        // silently drop those entries: refuse the archive until repaired.
        $raw = get_user_meta($owner, Catalog::USER_META, true);
        if ($raw !== '' && $raw !== false && $raw !== null) {
            if (!is_array($raw)) {
                return self::error('cb_notes_export_invalid_catalog', 'Notes catalog requires review before export.');
            }
            foreach (['notebook', 'category', 'tag'] as $kind) {
                if (array_key_exists($kind, $raw)
                    && (!is_array($raw[$kind]) || count($raw[$kind]) !== count($catalog[$kind]))) {
                    return self::error('cb_notes_export_invalid_catalog', 'Notes catalog requires review before export.');
                }
            }
        }

        // Reserve one record for the catalog. Base enforces 5,000 records and
        // a 10 MiB JSON envelope; never truncate a larger owner collection.
        $limit = Foundation::MAX_RECORDS - 1;
        $records = [[
            'record_type' => 'catalog',
            'reference' => 'catalog:personal',
            'catalog' => $catalog,
        ]];
        $count = null;
        $exported = 0;
        $page = 1;
        do {
            $query = new \WP_Query([
                'post_type' => Plugin::TYPE,
                'post_status' => self::STATUSES,
                'author' => $owner,
                'fields' => 'ids',
                'posts_per_page' => self::PAGE_SIZE,
                'paged' => $page,
                'orderby' => 'ID',
                'order' => 'ASC',
                'ignore_sticky_posts' => true,
                'suppress_filters' => false,
                'no_found_rows' => false,
            ]);
            $total = (int)$query->found_posts;
            if ($total < 0 || $total > $limit || ($count !== null && $count !== $total)
                || !is_array($query->posts)) {
                return self::error('cb_notes_export_changed', 'Notes export cannot safely include all notes.');
            }
            $count ??= $total;
            foreach ($query->posts as $id) {
                if (!is_int($id) && !ctype_digit((string)$id)) {
                    return self::error('cb_notes_export_changed', 'Notes export cannot safely include all notes.');
                }
                $post = get_post((int)$id);
                if (!$post instanceof \WP_Post || $post->post_type !== Plugin::TYPE
                    || (int)$post->post_author !== $owner || !Access::can_read($post)
                    || !in_array($post->post_status, self::STATUSES, true)) {
                    return self::error('cb_notes_export_changed', 'Notes export cannot safely include all notes.');
                }
                $item = $this->note_record($post, $catalog);
                if ($item instanceof \WP_Error) {
                    return $item;
                }
                $records[] = $item;
                ++$exported;
            }
            if ($exported > $limit || ($exported < $count && count($query->posts) !== self::PAGE_SIZE)) {
                return self::error('cb_notes_export_changed', 'Notes export cannot safely include all notes.');
            }
            ++$page;
        } while ($exported < $count);
        if ($exported !== $count) {
            return self::error('cb_notes_export_changed', 'Notes export cannot safely include all notes.');
        }
        return $records;
    }

    private function note_record(\WP_Post $post, array $catalog): array|\WP_Error {
        $id = (int)$post->ID;
        $notebook = get_post_meta($id, Catalog::NOTEBOOK, true);
        $category = get_post_meta($id, Catalog::CATEGORY, true);
        $tags = get_post_meta($id, Catalog::TAGS, true);
        $tags = $tags === '' ? [] : $tags;
        if (!is_string($notebook) || !is_string($category) || !is_array($tags)
            || count($tags) > 12 || !self::references_valid($catalog, 'notebook', $notebook)
            || !self::references_valid($catalog, 'category', $category)) {
            return self::error('cb_notes_export_invalid_relation', 'Notes classifications require review before export.');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || !self::references_valid($catalog, 'tag', $tag, false)) {
                return self::error('cb_notes_export_invalid_relation', 'Notes classifications require review before export.');
            }
        }
        if (count(array_unique($tags)) !== count($tags)
            || ($notebook === '' && (string)get_post_meta($id, Plugin::META_NOTEBOOK, true) !== '')) {
            return self::error('cb_notes_export_invalid_relation', 'Notes classifications require review before export.');
        }
        $markers = Markers::for_post($id);
        return [
            'record_type' => 'note',
            // Source-local reference only; importer must remap IDs to target.
            'reference' => 'note:' . $id,
            'title' => (string)$post->post_title,
            'content' => (string)$post->post_content,
            'status' => (string)$post->post_status,
            'created_gmt' => (string)$post->post_date_gmt,
            'modified_gmt' => (string)$post->post_modified_gmt,
            'notebook_id' => $notebook,
            'category_id' => $category,
            'tag_ids' => array_values($tags),
            'favorite' => $markers['favorite'],
            'pinned' => $markers['pinned'],
        ];
    }

    private static function references_valid(array $catalog, string $kind, string $value, bool $allow_empty = true): bool {
        if ($value === '') {
            return $allow_empty;
        }
        if (!Catalog::valid_id($value)) {
            return false;
        }
        foreach ($catalog[$kind] ?? [] as $item) {
            if ($item['id'] === $value) {
                return true;
            }
        }
        return false;
    }

    public function plan_import(array $record, string $mode, int $source_schema_version, array $context = []): array|\WP_Error {
        // Only the private, validated whole-archive request session may enter.
        if (!$this->can_import($context)) {
            return self::error('cb_notes_import_not_ready', 'Notes import is not available in this candidate.');
        }
        return ImportPlanner::plan_record($record, $mode, $source_schema_version, $context);
    }

    public function apply_import(array $plan, array $context = []): array|\WP_Error {
        if (!$this->can_import($context)) {
            return self::error('cb_notes_import_not_ready', 'Notes import is not available in this candidate.');
        }
        return ImportApplier::apply($plan, $context);
    }

    private static function error(string $code, string $message): \WP_Error {
        return new \WP_Error($code, $message);
    }
}
