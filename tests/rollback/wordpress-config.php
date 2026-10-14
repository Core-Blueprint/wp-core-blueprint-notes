<?php
declare(strict_types=1);

/**
 * Isolated WordPress configuration for the historical-share rollback rehearsal.
 * This config and its test-only credentials must never be deployed to a real site.
 */
$db = getenv('CB_NOTES_ROLLBACK_DB');
if (!in_array($db, [
    'core_blueprint_notes_rollback_source',
    'core_blueprint_notes_rollback_target',
], true) || PHP_SAPI !== 'cli') {
    throw new RuntimeException('Rollback test config permits only the two fixed disposable CLI databases.');
}
define('DB_NAME', $db);
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1:3307');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
foreach ([
    'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
    'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
] as $key) {
    define($key, $db . '-' . $key . '-test-only');
}
define('WP_ENVIRONMENT_TYPE', 'local');
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
$table_prefix = 'cbrollback_';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
