<?php
declare(strict_types=1);

namespace CB\Notes\Admin;

use CB\Notes\Plugin;
use CB\Notes\Sharing\SecureRevocationRecovery;
use CoreBlueprint\Core\Admin\SettingsRegistry;

defined('ABSPATH') || exit;

/**
 * Settings Hub provider. Base owns the page title and extension information;
 * Notes only renders its navigation and configuration body.
 */
final class SettingsPage {
    public const EXTENSION_ID = 'core-blueprint-notes';
    public const TAB_OVERVIEW = 'overview';
    public const TAB_AI = 'ai-access';

    public static function init(): void {
        add_action('core_blueprint_register_settings', [self::class, 'register']);
    }

    public static function register(): void {
        SettingsRegistry::register(self::EXTENSION_ID, [
            'label' => __('Notes', 'core-blueprint-notes'),
            'description' => __('Manage private notes, personal notebooks and optional AI access using Gutenberg.', 'core-blueprint-notes'),
            'group' => SettingsRegistry::GROUP_BUSINESS,
            'capability' => 'manage_options',
            'renderer' => [self::class, 'render'],
            'requirements' => [
                'components' => ['buttons', 'form-controls', 'metric-tiles', 'nav-tabs', 'panels'],
            ],
        ]);
    }

    /** @return array<string,string> */
    private static function tabs(): array {
        return [
            self::TAB_OVERVIEW => __('Overview', 'core-blueprint-notes'),
            self::TAB_AI => __('AI Access', 'core-blueprint-notes'),
        ];
    }

    private static function active_tab(): string {
        $requested = isset($_GET['tab']) && is_string($_GET['tab'])
            ? sanitize_key((string) wp_unslash($_GET['tab']))
            : self::TAB_OVERVIEW;
        return array_key_exists($requested, self::tabs()) ? $requested : self::TAB_OVERVIEW;
    }

    public static function url(string $tab = self::TAB_OVERVIEW): string {
        $tab = array_key_exists($tab, self::tabs()) ? $tab : self::TAB_OVERVIEW;
        return SettingsRegistry::url(self::EXTENSION_ID, ['tab' => $tab]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Notes settings.', 'core-blueprint-notes'));
        }

        $active = self::active_tab();
        echo '<nav class="nav-tab-wrapper cb-core-tab-wrapper" aria-label="' .
            esc_attr__('Notes settings sections', 'core-blueprint-notes') . '">';
        foreach (self::tabs() as $id => $label) {
            $selected = $id === $active;
            echo '<a class="nav-tab' . ($selected ? ' nav-tab-active' : '') .
                '" href="' . esc_url(self::url($id)) . '"' .
                ($selected ? ' aria-current="page"' : '') . '>' .
                esc_html($label) . '</a>';
        }
        echo '</nav>';

        if (self::TAB_AI === $active) {
            self::render_ai();
            return;
        }
        self::render_overview();
    }

    private static function personal_note_count(): int {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return 0;
        }
        $notes = new \WP_Query([
            'post_type' => Plugin::TYPE,
            'post_status' => ['draft', 'private', 'publish', 'pending', 'future'],
            'author' => $user_id,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => false,
            'suppress_filters' => false,
        ]);
        return max(0, (int) $notes->found_posts);
    }

    private static function render_overview(): void {
        $enabled = (bool) get_option(Plugin::AI_OPTION, false);
        echo '<div class="cb-core-tiles" aria-label="' .
            esc_attr__('Notes at a glance', 'core-blueprint-notes') . '">';
        self::metric(__('My notes', 'core-blueprint-notes'), (string) self::personal_note_count());
        self::metric(__('Editor', 'core-blueprint-notes'), 'Gutenberg');
        self::metric(__('AI access', 'core-blueprint-notes'),
            $enabled ? __('Enabled', 'core-blueprint-notes') : __('Disabled', 'core-blueprint-notes'));
        echo '</div>';

        echo '<section class="cb-core-panel">';
        echo '<h2>' . esc_html__('Notes workspace', 'core-blueprint-notes') . '</h2>';
        echo '<p>' . esc_html__('Create and edit your own notes in the native WordPress block editor. Personal notebook labels are stored with each note.', 'core-blueprint-notes') . '</p>';
        echo '<p>' . esc_html__('Notes are private by default. Team sharing and private file attachments are not available in this alpha.', 'core-blueprint-notes') . '</p>';
        echo '<p><a class="button cb-core-button cb-core-button--primary" href="' .
            esc_url(admin_url('edit.php?post_type=' . Plugin::TYPE . '&page=cb-notes-library')) . '">' .
            esc_html__('Open Notes Library', 'core-blueprint-notes') . '</a></p>';
        echo '</section>';

        // Secure Share can be optional/unavailable. Do not expose grant IDs,
        // bearer credentials or note content to the administrator interface.
        $pending = SecureRevocationRecovery::pending_count();
        if ($pending > 0) {
            echo '<section class="cb-core-panel">';
            echo '<h2>' . esc_html__('Secure Share recovery', 'core-blueprint-notes') . '</h2>';
            echo '<p>' . esc_html__('Some Secure Share grants still require provider-side revocation. Notes has already disabled local delivery. Resolve these before relying on the original links being fully revoked.', 'core-blueprint-notes') . '</p>';
            echo '<p><strong>' . esc_html__('Pending revocations:', 'core-blueprint-notes') . '</strong> ' . esc_html((string)$pending) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="cb_notes_retry_revocations">';
            wp_nonce_field('cb_notes_retry_revocations');
            echo '<button class="button cb-core-button" type="submit">' . esc_html__('Retry provider revocations', 'core-blueprint-notes') . '</button>';
            echo '</form></section>';
        }
    }

    private static function metric(string $label, string $value): void {
        echo '<div class="cb-core-tile cb-core-tile--metric cb-core-tile--neutral">';
        echo '<span class="cb-core-tile__label">' . esc_html($label) . '</span>';
        echo '<strong class="cb-core-tile__value">' . esc_html($value) . '</strong>';
        echo '</div>';
    }

    private static function render_ai(): void {
        $enabled = (bool) get_option(Plugin::AI_OPTION, false);
        echo '<section class="cb-core-panel">';
        echo '<h2>' . esc_html__('AI & MCP access', 'core-blueprint-notes') . '</h2>';
        echo '<p>' . esc_html__('Read-only Notes abilities are optional and disabled by default. A separately installed and authenticated WordPress MCP Adapter is required for an external AI client. Enabling this setting does not create a connection.', 'core-blueprint-notes') . '</p>';
        echo '<p><strong>' . esc_html__('Current setting:', 'core-blueprint-notes') . '</strong> ' .
            esc_html($enabled ? __('Enabled', 'core-blueprint-notes') : __('Disabled', 'core-blueprint-notes')) . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('cb_notes_settings');
        echo '<input type="hidden" name="' . esc_attr(Plugin::AI_OPTION) . '" value="0">';
        echo '<p><label><input type="checkbox" name="' . esc_attr(Plugin::AI_OPTION) .
            '" value="1" ' . checked($enabled, true, false) . '> ' .
            esc_html__('Allow read-only Notes abilities in MCP', 'core-blueprint-notes') . '</label></p>';
        echo '<p class="description">' . esc_html__('Only users with cb_notes_use_ai (administrators by default) may invoke abilities. They can read only notes they authored, even when using an administrator account.', 'core-blueprint-notes') . '</p>';
        submit_button(__('Save Changes', 'core-blueprint-notes'));
        echo '</form>';
        echo '</section>';

        echo '<section class="cb-core-panel">';
        echo '<h2>' . esc_html__('Available abilities', 'core-blueprint-notes') . '</h2>';
        echo '<p>' . esc_html__('List notes, search notes and read a note. Create, update and delete abilities are not registered.', 'core-blueprint-notes') . '</p>';
        echo '</section>';
    }

    /**
     * Legacy Notes -> AI Access item remains a shortcut; redirect before the
     * WordPress admin header is rendered (on the load-$hook action).
     */
    public static function redirect_legacy(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage Notes settings.', 'core-blueprint-notes'));
        }
        wp_safe_redirect(self::url(self::TAB_AI), 302);
        exit;
    }

    /** Fallback for an unusual admin execution path without load-$hook. */
    public static function legacy_link(): void {
        echo '<p><a href="' . esc_url(self::url(self::TAB_AI)) . '">' .
            esc_html__('Open AI Access settings', 'core-blueprint-notes') . '</a></p>';
    }
}
