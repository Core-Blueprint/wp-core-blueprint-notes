<?php
declare(strict_types=1);

/** One single-site local-only WordPress config, never a live-site config. */
$name = getenv('CB_NOTES_HTTP_DB_NAME');
if ($name !== 'core_blueprint_notes_http_test') {
    throw new RuntimeException('HTTP fixture refuses noncanonical database');
}
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    throw new RuntimeException('HTTP fixture disallows a network-facing web SAPI');
}
if (PHP_SAPI === 'cli-server') {
    if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
        || (string) ($_SERVER['SERVER_PORT'] ?? '') !== '18777'
        || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:18777') {
        http_response_code(403);
        exit('HTTP fixture only permits loopback');
    }
}
define('DB_NAME', $name);
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', '127.0.0.1:3307');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
          'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
    define($key, 'notes-http-local-test-only-' . $key);
}
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('DISABLE_WP_CRON', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
$table_prefix = 'cbhttp_';
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
