<?php
/**
 * Plugin Name:       Core Blueprint Notes
 * Plugin URI:        https://coreblueprint.io
 * Description:       Private Gutenberg notes with personal notebooks, categories, tags and controlled sharing.
 * Version:           0.1.0-alpha6
 * Author:            Core Blueprint
 * Author URI:        https://coreblueprint.io
 * License:           GPL-2.0-or-later
 * Text Domain:       core-blueprint-notes
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Requires Plugins: core-blueprint
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
if (defined('CB_NOTES_FILE')) {
    return;
}

define('CB_NOTES_FILE', __FILE__);
define('CB_NOTES_DIR', plugin_dir_path(__FILE__));
define('CB_NOTES_BASENAME', plugin_basename(__FILE__));
define('CB_NOTES_VERSION', '0.1.0-alpha6');
define('CB_NOTES_REQUIRED_API', '1.2');

spl_autoload_register(static function (string $class): void {
    $prefix = 'CB\\Notes\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = CB_NOTES_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/** @return string|null Operator-readable incompatibility, or null when ready. */
function cb_notes_requirement_error(): ?string {
    if (version_compare(PHP_VERSION, '8.4', '<')) {
        return 'PHP 8.4 or newer is required.';
    }
    if (!defined('CB_CORE_API_VERSION')) {
        return 'Core Blueprint Base must be installed and active.';
    }
    $available = (string) CB_CORE_API_VERSION;
    if (preg_match('/^(\\d+)\\.(\\d+)$/', $available, $version) !== 1 ||
        (int) $version[1] !== 1 || (int) $version[2] < 2) {
        return 'Core Blueprint Base public API 1.2 or newer compatible 1.x is required.';
    }
    if (!class_exists('CoreBlueprint\\Core\\ExtensionRegistry') ||
        !class_exists('CoreBlueprint\\Core\\Admin\\SettingsRegistry') ||
        !class_exists('CoreBlueprint\\Core\\Governance\\EventRegistry') ||
        !class_exists('CoreBlueprint\\Core\\Governance\\Audit')) {
        return 'Required Core Blueprint Base public services are unavailable.';
    }
    return null;
}

function cb_notes_activate(): void {
    $error = cb_notes_requirement_error();
    if (null !== $error) {
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins(CB_NOTES_BASENAME);
        wp_die(esc_html($error), esc_html('Core Blueprint Notes requirements not met'));
    }
    \CB\Notes\Plugin::grant_roles();
    \CB\Notes\Plugin::register_content();
    \CB\Notes\Sharing\Controller::register();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'cb_notes_activate');

add_action('plugins_loaded', static function (): void {
    $error = cb_notes_requirement_error();
    if (null !== $error) {
        add_action('admin_notices', static function () use ($error): void {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p><strong>Core Blueprint Notes:</strong> ' . esc_html($error) . '</p></div>';
            }
        });
        return;
    }
    \CB\Notes\Plugin::boot();
}, 30);
