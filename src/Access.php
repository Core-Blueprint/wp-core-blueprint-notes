<?php
declare(strict_types=1);
namespace CB\Notes;
defined('ABSPATH') || exit;
final class Access {
    public static function can_manage_all(): bool { return current_user_can('cb_notes_manage_all'); }
    public static function can_read(\WP_Post $post): bool { return self::allowed($post, 'read'); }
    public static function allowed(\WP_Post $post, string $operation, ?int $user_id = null): bool {
        if ($post->post_type !== 'cb_note' || $post->post_status === 'trash') return false;
        $user_id ??= get_current_user_id();
        if ($user_id < 1) return false;
        if (user_can($user_id, 'cb_notes_manage_all')) return true;
        $primitive = match ($operation) {'delete' => 'cb_notes_delete', 'edit', 'read' => 'cb_notes_edit', default => 'do_not_allow'};
        return (int)$post->post_author === $user_id && $primitive !== 'do_not_allow' && user_can($user_id, $primitive);
    }
    public static function map_meta_cap(array $caps, string $cap, int $user_id, array $args): array {
        if (!in_array($cap, ['edit_post','read_post','delete_post'], true)) return $caps;
        $post = isset($args[0]) ? get_post((int)$args[0]) : null;
        if (!$post instanceof \WP_Post || $post->post_type !== 'cb_note') return $caps;
        if ($post->post_status === 'trash' && $cap === 'read_post') return ['do_not_allow'];
        if ($user_id > 0 && user_can($user_id, 'cb_notes_manage_all')) return ['cb_notes_manage_all'];
        if ((int)$post->post_author !== $user_id || $user_id < 1) return ['do_not_allow'];
        return [$cap === 'delete_post' ? 'cb_notes_delete' : 'cb_notes_edit'];
    }
    public static function scope_query(\WP_Query $query): void {
        if ($query->get('post_type') !== 'cb_note' || self::can_manage_all()) return;
        $user = get_current_user_id();
        $query->set('author', $user > 0 ? $user : -1);
        if ($user < 1) $query->set('post__in', [0]);
    }
    public static function scope_rest(array $args): array {
        if (!self::can_manage_all()) {
            $user = get_current_user_id();
            $args['author'] = $user > 0 ? $user : -1;
            if ($user < 1) $args['post__in'] = [0];
        }
        return $args;
    }
    public static function protect_rest(\WP_REST_Response $response, \WP_Post $post): \WP_REST_Response|\WP_Error {
        if (!self::can_read($post)) return new \WP_Error('cb_notes_not_found', __('Note not found.', 'core-blueprint-notes'), ['status' => 404]);
        return $response;
    }
}
