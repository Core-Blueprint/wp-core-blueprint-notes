<?php
declare(strict_types=1);

namespace CB\Notes\Library;

use CB\Notes\Organization\Catalog;

defined('ABSPATH') || exit;

/**
 * Owner-only Library presentation setting.
 * This changes no note, catalog, sharing, or query state.
 */
final class Display {
    private const META = 'cb_notes_library_display';
    private const SIDEBAR_META = 'cb_notes_library_sidebar';
    private const ACTION = 'cb_notes_library_display';

    public static function init(): void {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
    }

    public static function current(int $user_id): string {
        if ($user_id < 1) {
            return 'list';
        }
        $layout = get_user_meta($user_id, self::META, true);
        return in_array($layout, ['list', 'table', 'cards'], true) ? $layout : 'list';
    }

    /** auto defaults to expanded on wide screens and collapsed on narrow screens. */
    public static function sidebar(int $user_id): string {
        if ($user_id < 1) {
            return 'auto';
        }
        $value = get_user_meta($user_id, self::SIDEBAR_META, true);
        return in_array($value, ['expanded', 'collapsed'], true) ? $value : 'auto';
    }

    /** Reuse the canonical owner-only, CSRF-gated display action. */
    public static function sidebar_url(string $position, array $state): string {
        if (!in_array($position, ['expanded', 'collapsed'], true)) {
            $position = 'collapsed';
        }
        $query = array_merge(
            Page::parameters($state),
            ['action' => self::ACTION, 'sidebar' => $position]
        );
        return wp_nonce_url(
            add_query_arg($query, admin_url('admin-post.php')),
            self::ACTION . '_sidebar_' . $position
        );
    }

    public static function switch_url(string $layout, array $state): string {
        if (!in_array($layout, ['list', 'table', 'cards'], true)) {
            $layout = 'list';
        }
        // Carry only the canonical, already-validated Library query values.
        $query = array_merge(
            Page::parameters($state),
            ['action' => self::ACTION, 'layout' => $layout]
        );
        return wp_nonce_url(
            add_query_arg($query, admin_url('admin-post.php')),
            self::ACTION . '_' . $layout
        );
    }

    public static function save(): void {
        if (!current_user_can('cb_notes_edit') || get_current_user_id() < 1) {
            wp_die(esc_html__('Access denied.', 'core-blueprint-notes'));
        }
        $sidebar_requested = array_key_exists('sidebar', $_GET);
        $layout_requested = array_key_exists('layout', $_GET);
        if ($sidebar_requested === $layout_requested) {
            wp_die(esc_html__('Invalid display.', 'core-blueprint-notes'));
        }
        $owner = get_current_user_id();
        if ($sidebar_requested) {
            $position = is_string($_GET['sidebar'])
                ? sanitize_key(wp_unslash($_GET['sidebar'])) : '';
            if (!in_array($position, ['expanded', 'collapsed'], true)) {
                wp_die(esc_html__('Invalid display.', 'core-blueprint-notes'));
            }
            check_admin_referer(self::ACTION . '_sidebar_' . $position);
            $state = Query::state($_GET, Catalog::catalog($owner));
            update_user_meta($owner, self::SIDEBAR_META, $position);
        } else {
            $layout = is_string($_GET['layout'])
                ? sanitize_key(wp_unslash($_GET['layout'])) : '';
            if (!in_array($layout, ['list', 'table', 'cards'], true)) {
                wp_die(esc_html__('Invalid display.', 'core-blueprint-notes'));
            }
            check_admin_referer(self::ACTION . '_' . $layout);
            $state = Query::state($_GET, Catalog::catalog($owner));
            update_user_meta($owner, self::META, $layout);
        }
        // Reconstruct the validated Library URL, never a user-supplied redirect.
        wp_safe_redirect(Page::url($state));
        exit;
    }
}
