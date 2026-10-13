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
    private const ACTION = 'cb_notes_library_display';

    public static function init(): void {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
    }

    public static function current(int $user_id): string {
        if ($user_id < 1) {
            return 'list';
        }
        return get_user_meta($user_id, self::META, true) === 'table' ? 'table' : 'list';
    }

    public static function switch_url(string $layout, array $state): string {
        if (!in_array($layout, ['list', 'table'], true)) {
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
        $layout = isset($_GET['layout']) && is_string($_GET['layout'])
            ? sanitize_key(wp_unslash($_GET['layout'])) : '';
        if (!in_array($layout, ['list', 'table'], true)) {
            wp_die(esc_html__('Invalid display.', 'core-blueprint-notes'));
        }
        check_admin_referer(self::ACTION . '_' . $layout);
        $owner = get_current_user_id();
        $state = Query::state($_GET, Catalog::catalog($owner));
        update_user_meta($owner, self::META, $layout);
        // Reconstruct the known admin URL; never redirect to user input.
        wp_safe_redirect(Page::url($state));
        exit;
    }
}
