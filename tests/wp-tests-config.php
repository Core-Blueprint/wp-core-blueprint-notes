<?php
declare(strict_types=1);

/** Notes-only WordPress PHPUnit database; never share Base or Work test schemas. */
$core_dir = rtrim( (string) getenv( 'WP_CORE_DIR' ), '/\\' );
if ( '' === $core_dir || ! is_file( $core_dir . '/wp-settings.php' ) ) {
    throw new RuntimeException( 'Notes integration requires a provisioned WordPress runtime.' );
}
$db_name = (string) getenv( 'WP_DB_NAME' );
$db_host = (string) getenv( 'WP_DB_HOST' );
$db_user = (string) getenv( 'WP_DB_USER' );
$db_password = (string) getenv( 'WP_DB_PASSWORD' );
if ( 1 !== preg_match( '/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D', $db_name ) ||
     '127.0.0.1:3307' !== $db_host ||
     'root' !== $db_user || 'root' !== $db_password ) {
    throw new RuntimeException( 'Notes integration refuses noncanonical DB credentials or host.' );
}
define( 'ABSPATH', $core_dir . '/' );
define( 'DB_NAME', $db_name );
define( 'DB_USER', $db_user );
define( 'DB_PASSWORD', $db_password );
define( 'DB_HOST', $db_host );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', 'cb-notes-tests-auth' );
define( 'SECURE_AUTH_KEY', 'cb-notes-tests-secure-auth' );
define( 'LOGGED_IN_KEY', 'cb-notes-tests-login' );
define( 'NONCE_KEY', 'cb-notes-tests-nonce' );
define( 'AUTH_SALT', 'cb-notes-tests-auth-salt' );
define( 'SECURE_AUTH_SALT', 'cb-notes-tests-secure-salt' );
define( 'LOGGED_IN_SALT', 'cb-notes-tests-login-salt' );
define( 'NONCE_SALT', 'cb-notes-tests-nonce-salt' );
define( 'CB_NOTES_SHARE_EPOCH_KEY', str_repeat( 'a', 64 ) );
define( 'WP_TESTS_DOMAIN', 'notes.test' );
define( 'WP_TESTS_EMAIL', 'noreply@notes.test' );
define( 'WP_TESTS_TITLE', 'Core Blueprint Notes Integration Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
