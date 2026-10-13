<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Plugin;

defined('ABSPATH') || exit;

/**
 * Personal Note markers, not sharing grants or Gutenberg content.
 * Protected post meta is deliberately excluded from REST schemas and snapshots.
 */
final class Markers {
    public const FAVORITE = '_cb_notes_favorite';
    public const PINNED = '_cb_notes_pinned';

    public static function init(): void {
        add_action('wp_ajax_cb_notes_mark', [self::class, 'ajax']);
        add_filter('posts_orderby', [self::class, 'pinned_first'], 20, 2);
    }

    /** @return array{favorite:bool,pinned:bool} */
    public static function for_post(int $id): array {
        return [
            'favorite' => get_post_meta($id, self::FAVORITE, true) === '1',
            'pinned' => get_post_meta($id, self::PINNED, true) === '1',
        ];
    }

    public static function can_mark(int $id): bool {
        if ($id < 1 || get_current_user_id() < 1 || !current_user_can('cb_notes_edit')) {
            return false;
        }
        $post = get_post($id);
        return $post instanceof \WP_Post
            && $post->post_type === Plugin::TYPE
            && !in_array($post->post_status, ['trash', 'auto-draft'], true)
            && (int)$post->post_author === get_current_user_id()
            && current_user_can('edit_post', $id);
    }

    public static function ajax(): void {
        $id = absint($_POST['note_id'] ?? 0);
        $nonce = $_POST['_ajax_nonce'] ?? '';
        if ($id < 1 || !is_string($nonce) || !wp_verify_nonce($nonce, 'cb_notes_mark_' . $id)) {
            wp_send_json_error(['message' => __('This action has expired. Reload and try again.', 'core-blueprint-notes')], 403);
            return;
        }
        if (!self::can_mark($id)) {
            wp_send_json_error(['message' => __('This note is not available to you.', 'core-blueprint-notes')], 403);
            return;
        }
        $kind = $_POST['kind'] ?? null;
        $enabled = $_POST['enabled'] ?? null;
        if (!is_string($kind) || !in_array($kind, ['favorite', 'pinned'], true)
            || !is_string($enabled) || !in_array($enabled, ['0', '1'], true)) {
            wp_send_json_error(['message' => __('Invalid note action.', 'core-blueprint-notes')], 422);
            return;
        }
        $key = $kind === 'favorite' ? self::FAVORITE : self::PINNED;
        if ($enabled === '1') {
            update_post_meta($id, $key, '1');
        } else {
            delete_post_meta($id, $key);
        }
        $flags = self::for_post($id);
        if ($flags[$kind] !== ($enabled === '1')) {
            wp_send_json_error(['message' => __('Could not save this preference.', 'core-blueprint-notes')], 500);
            return;
        }
        wp_send_json_success([
            'note_id' => $id,
            'favorite' => $flags['favorite'],
            'pinned' => $flags['pinned'],
        ]);
    }

    /**
     * Apply pinned-first sorting ONLY to the owner-bound Library query.
     * Explicit title/oldest sorts remain untouched. Correlated EXISTS keeps
     * normal pagination and result counts (no N+1 or duplicated JOIN rows).
     */
    public static function pinned_first(string $orderby, \WP_Query $query): string {
        if ($query->get('cb_notes_pinned_first') !== true
            || $query->get('post_type') !== Plugin::TYPE
            || (int)$query->get('author') < 1) {
            return $orderby;
        }
        global $wpdb;
        $posts = $wpdb->posts;
        $postmeta = $wpdb->postmeta;
        $pinned = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$postmeta} cbnp WHERE cbnp.post_id = {$posts}.ID AND cbnp.meta_key = %s AND cbnp.meta_value = %s)",
            self::PINNED, '1'
        );
        return 'CASE WHEN ' . $pinned . ' THEN 0 ELSE 1 END ASC, ' . $orderby;
    }
}
