<?php
declare(strict_types=1);

/**
 * Disposable Notes SQL restore fixture only.
 *
 * Copied to wp-config.php under two temporary WordPress directories.
 * Refuses all database names except the two dedicated hardcoded test databases.
 * Credentials and salts are intentionally synthetic and may never be used live.
 */
$name = getenv('CB_RESTORE_DB_NAME');
if (!in_array($name, [
    'core_blueprint_notes_restore_source',
    'core_blueprint_notes_restore_target',
], true)) {
    throw new RuntimeException('Notes restore config refuses an unrecognized database.');
}
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Notes restore sites may only run from the CLI.');
}
define('DB_NAME', $name);
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1:3307');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
// Independent synthetic epoch for Alpha25's Public Share Guard.
define('CB_NOTES_SHARE_EPOCH_KEY', str_repeat($name === 'core_blueprint_notes_restore_source' ? 'a' : 'b', 64));
define('AUTH_KEY', $name . '-auth-key-test-only');
define('SECURE_AUTH_KEY', $name . '-secure-auth-key-test-only');
define('LOGGED_IN_KEY', $name . '-login-key-test-only');
define('NONCE_KEY', $name . '-nonce-key-test-only');
define('AUTH_SALT', $name . '-auth-salt-test-only');
define('SECURE_AUTH_SALT', $name . '-secure-auth-salt-test-only');
define('LOGGED_IN_SALT', $name . '-login-salt-test-only');
define('NONCE_SALT', $name . '-nonce-salt-test-only');
define('WP_DEBUG', true);
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
$table_prefix = 'cbnotesr_';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
