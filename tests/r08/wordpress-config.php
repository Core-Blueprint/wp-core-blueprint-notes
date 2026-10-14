<?php
declare(strict_types=1);

/**
 * R08 first-party provider runtime: disposable database, CLI only.
 * This config never represents a production HTTPS/browser environment.
 */
$db = (string)getenv('CB_NOTES_R08_DB');
if ($db !== 'core_blueprint_notes_r08_test' || PHP_SAPI !== 'cli') {
    throw new RuntimeException('R08 requires its dedicated disposable CLI-only database.');
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
] as $part) {
    define($part, hash('sha256', 'core-blueprint-r08-test-only:' . $part));
}
define('WP_ENVIRONMENT_TYPE', 'local');
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('CB_NOTES_SHARE_EPOCH_KEY', str_repeat('a', 64));
$table_prefix = 'cbr08_';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
