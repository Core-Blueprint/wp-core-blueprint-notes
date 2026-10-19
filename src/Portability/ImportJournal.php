<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CB\Notes\Plugin;

/**
 * Append-intent first, owner-scoped import receipt. An ambiguous "pending"
 * result must NEVER be retried automatically: doing so could duplicate a
 * WordPress post created before a subsequent metadata/journal write failed.
 *
 * This class has no WordPress hooks and cannot be invoked by a web request
 * until the whole-document import service is independently accepted.
 */
final class ImportJournal {
    private const PREFIX = 'cb_notes_import_receipt_v1_';
    public const META = '_cb_notes_import_receipt_v1';

    public static function key(int $owner, string $digest, string $reference): string|\WP_Error {
        if ($owner < 1 || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
            || preg_match('/^note:[1-9][0-9]*$/D', $reference) !== 1
            || $owner !== get_current_user_id() || !current_user_can('cb_notes_edit')) {
            return self::fail('cb_notes_import_journal_forbidden');
        }
        $salt = wp_salt('auth');
        if (!is_string($salt) || strlen($salt) < 32) {
            return self::fail('cb_notes_import_journal_unavailable');
        }
        return self::PREFIX . hash_hmac('sha256', $owner . "\0" . $digest . "\0" . $reference, $salt);
    }

    /** Unique option insertion reserves exactly one attempt per owner/file/ref. */
    public static function reserve(int $owner, string $digest, string $reference): string|\WP_Error {
        $key = self::key($owner, $digest, $reference);
        if ($key instanceof \WP_Error) {
            return $key;
        }
        $record = [
            'state' => 'pending',
            'owner' => $owner,
            'digest' => $digest,
            'reference' => $reference,
        ];
        if (!add_option($key, $record, '', false)) {
            // An existing receipt may be complete, but an ambiguous attempt
            // requires explicit verification; never attempt a second create.
            return self::fail('cb_notes_import_journal_exists');
        }
        if (get_option($key, false) !== $record) {
            return self::fail('cb_notes_import_journal_write_failed');
        }
        return $key;
    }

    /**
     * Only a verifiably owner-owned Note bearing this exact receipt may
     * advance from pending to complete. Never delete the pending intent.
     */
    public static function complete(
        int $owner,
        string $digest,
        string $reference,
        int $post_id
    ): true|\WP_Error {
        $key = self::key($owner, $digest, $reference);
        if ($key instanceof \WP_Error || $post_id < 1) {
            return self::fail('cb_notes_import_journal_forbidden');
        }
        $entry = get_option($key, false);
        if (!is_array($entry) || $entry !== [
            'state' => 'pending', 'owner' => $owner,
            'digest' => $digest, 'reference' => $reference,
        ]) {
            return self::fail('cb_notes_import_journal_not_pending');
        }
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post
            || (int)$post->post_author !== $owner
            || $post->post_type !== Plugin::TYPE
            || !in_array($post->post_status, ['private', 'draft'], true)
            || get_post_meta($post_id, self::META, true) !== $key
            || !current_user_can('edit_post', $post_id)) {
            return self::fail('cb_notes_import_journal_post_mismatch');
        }
        $finished = $entry;
        $finished['state'] = 'complete';
        $finished['post_id'] = $post_id;
        if (!update_option($key, $finished, false) || get_option($key, false) !== $finished) {
            return self::fail('cb_notes_import_journal_completion_failed');
        }
        return true;
    }

    /**
     * Returns no foreign owner data. A complete receipt is only usable when
     * the mapped WordPress note STILL belongs to this owner and bears its
     * expected receipt marker. Otherwise stop rather than overwrite/recreate.
     */
    public static function receipt(int $owner, string $digest, string $reference): array|\WP_Error {
        $key = self::key($owner, $digest, $reference);
        if ($key instanceof \WP_Error) {
            return $key;
        }
        $entry = get_option($key, false);
        if ($entry === false) {
            return ['state' => 'new'];
        }
        if (!is_array($entry) || ($entry['owner'] ?? null) !== $owner
            || ($entry['digest'] ?? null) !== $digest
            || ($entry['reference'] ?? null) !== $reference) {
            return self::fail('cb_notes_import_journal_corrupt');
        }
        if (($entry['state'] ?? null) === 'pending') {
            return self::fail('cb_notes_import_journal_needs_review');
        }
        if (($entry['state'] ?? null) !== 'complete'
            || !is_int($entry['post_id'] ?? null) || $entry['post_id'] < 1) {
            return self::fail('cb_notes_import_journal_corrupt');
        }
        $id = $entry['post_id'];
        $post = get_post($id);
        if (!$post instanceof \WP_Post || (int)$post->post_author !== $owner
            || $post->post_type !== Plugin::TYPE
            || !in_array($post->post_status, ['private', 'draft'], true)
            || get_post_meta($id, self::META, true) !== $key) {
            return self::fail('cb_notes_import_journal_needs_review');
        }
        return ['state' => 'complete', 'post_id' => $id];
    }

    private static function fail(string $code): \WP_Error {
        return new \WP_Error($code, 'Notes import recovery requires owner or operator review.');
    }
}
