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
], true) || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    throw new RuntimeException('Rollback test config permits only isolated CLI/loopback runtimes.');
}
if (PHP_SAPI === 'cli-server') {
    if ($db !== 'core_blueprint_notes_rollback_target'
        || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
        || (string)($_SERVER['SERVER_PORT'] ?? '') !== '18778'
        || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:18778') {
        http_response_code(403);
        exit('Only synthetic restored target on loopback is permitted');
    }
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
// Source E1 / restored target E2. Old Alpha24 ignores this new recovery
// key; the new guard must reject E1 signatures after SQL import into E2.
define('CB_NOTES_SHARE_EPOCH_KEY', str_repeat(
    $db === 'core_blueprint_notes_rollback_source' ? 'a' : 'b', 64
));
$table_prefix = 'cbrollback_';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
