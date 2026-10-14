<?php
declare(strict_types=1);

/**
 * Notes Level 2 bootstrap, called only from tools/check-integration.
 * Mirrors the tested Work Base-activation order without Work dependencies.
 */
$tests_dir = rtrim( (string) getenv( 'WP_TESTS_DIR' ), '/\\' );
$base_source = rtrim( (string) getenv( 'CB_TEST_BASE_SOURCE' ), '/\\' );
$base_plugin = (string) getenv( 'CB_TEST_BASE_PLUGIN_FILE' );
$notes_plugin = (string) getenv( 'CB_TEST_NOTES_PLUGIN_FILE' );
if ( ! is_file( $tests_dir . '/includes/functions.php' ) ) {
    throw new RuntimeException( 'Notes wp-phpunit library missing: run tools/check-integration.' );
}
if ( ! is_file( $base_source . '/vendor/autoload.php' )
    || ! is_dir( $base_source . '/vendor/yoast/phpunit-polyfills' )
    || ! is_file( $base_plugin ) || ! is_file( $notes_plugin ) ) {
    throw new RuntimeException( 'Notes integration requires staged Base/Notes and pinned PHPUnit dependencies.' );
}
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $base_source . '/vendor/yoast/phpunit-polyfills' );
}
require_once $base_source . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
putenv( 'WP_PHPUNIT__TABLE_PREFIX=cbnotestests_' );
require_once $tests_dir . '/includes/functions.php';

tests_add_filter( 'pre_wp_mail', static fn(): bool => true );
tests_add_filter( 'muplugins_loaded', static function () use ( $base_plugin, $notes_plugin ): void {
    require_once $base_plugin;
    require_once $notes_plugin;
    // Base schema and permissions must be ready before Notes' plugins_loaded boot.
    add_action( 'plugins_loaded', static function (): void {
        \CoreBlueprint\Core\Core::activate();
        global $wpdb;
        $table = \CoreBlueprint\Core\DB::audit_log_table();
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $found !== $table || '' !== (string) $wpdb->last_error ) {
            throw new RuntimeException( 'Base audit schema was not installed for Notes integration.' );
        }
    }, 2 );
} );
require_once $tests_dir . '/includes/bootstrap.php';
