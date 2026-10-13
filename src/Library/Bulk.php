<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;

defined('ABSPATH') || exit;

/**
 * P2: bounded, owner-only Library bulk mutation. No direct SQL writes or
 * REST-exposed post meta. WordPress owns Trash and its share-revocation hooks.
 */
final class Bulk {
    public const MAX_IDS = 20;
    public const NONCE_ACTION = 'cb_notes_bulk_v1';

    public static function init(): void {
        add_action('wp_ajax_cb_notes_bulk', [self::class, 'ajax']);
    }

    /** @return list<int>|null Exact, distinct, positive IDs; reject malformed input as a whole. */
    public static function ids(mixed $raw): ?array {
        if (!is_array($raw) || count($raw) < 1 || count($raw) > self::MAX_IDS) {
            return null;
        }
        $result = [];
        foreach ($raw as $entry) {
            if (!is_string($entry) || !preg_match('/^[1-9][0-9]*$/D', $entry)
                || (string)(int)$entry !== $entry) {
                return null;
            }
            $id = (int)$entry;
            if ($id < 1 || in_array($id, $result, true)) {
                return null;
            }
            $result[] = $id;
        }
        return $result;
    }

    public static function can_change(int $id, string $operation): bool {
        if (!Markers::can_mark($id)) {
            return false;
        }
        return $operation !== 'trash' || current_user_can('delete_post', $id);
    }

    /** Validate an opaque target against the requesting user's catalog. */
    public static function target_valid(string $operation, mixed $target, int $owner): bool {
        if ($operation === 'trash') {
            return $target === '';
        }
        if (!in_array($operation, ['notebook', 'category'], true) || !is_string($target)) {
            return false;
        }
        return $target === '' || (
            Catalog::valid_id($target) && Catalog::contains($owner, $operation, $target)
        );
    }

    /** Perform exactly one authorized operation; no direct sharing mutations. */
    private static function apply(int $id, string $operation, string $target): bool {
        if ($operation === 'trash') {
            // Fires trashed_post, including Notes public/secure revocation.
            $result = wp_trash_post($id);
            // wp_trash_post() returns the pre-trash WP_Post on WordPress core.
            // Verify the persisted status instead of relying on that object.
            return $result instanceof \WP_Post && get_post_status($id) === 'trash';
        }

        $key = $operation === 'notebook' ? Catalog::NOTEBOOK : Catalog::CATEGORY;
        if ((string)get_post_meta($id, $key, true) === $target) {
            return true;
        }
        update_post_meta($id, $key, $target);
        return (string)get_post_meta($id, $key, true) === $target;
    }

    /**
     * Process independently authorized notes. Generic failures avoid revealing
     * foreign authors, post statuses, titles, or taxonomy/catalog names.
     * @param list<int> $ids
     * @return array{requested:int,updated:int,failed:int,updated_ids:list<int>,failed_ids:list<int>}
     */
    public static function process(array $ids, string $operation, string $target): array {
        $updated = [];
        $failed = [];
        foreach ($ids as $id) {
            if (!self::can_change($id, $operation)) {
                $failed[] = $id;
                continue;
            }
            if (self::apply($id, $operation, $target)) {
                $updated[] = $id;
            } else {
                $failed[] = $id;
            }
        }
        return [
            'requested' => count($ids),
            'updated' => count($updated),
            'failed' => count($failed),
            'updated_ids' => $updated,
            'failed_ids' => $failed,
        ];
    }

    public static function ajax(): void {
        if (!current_user_can('cb_notes_edit') || get_current_user_id() < 1) {
            wp_send_json_error(['message' => __('Access denied.', 'core-blueprint-notes')], 403);
            return;
        }
        $nonce = $_POST['_ajax_nonce'] ?? null;
        if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Your session has expired. Reload the Library.', 'core-blueprint-notes')], 403);
            return;
        }
        $ids = self::ids($_POST['ids'] ?? null);
        $operation = $_POST['operation'] ?? null;
        $target = $_POST['target'] ?? '';
        if ($ids === null || !is_string($operation)
            || !in_array($operation, ['notebook', 'category', 'trash'], true)
            || !self::target_valid($operation, $target, get_current_user_id())) {
            wp_send_json_error(['message' => __('Invalid bulk selection or destination.', 'core-blueprint-notes')], 422);
            return;
        }
        wp_send_json_success(self::process($ids, $operation, $target));
    }
}
