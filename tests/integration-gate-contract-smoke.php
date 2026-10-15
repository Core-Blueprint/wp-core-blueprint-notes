<?php
declare(strict_types=1);
/** Source-level safety contract for isolated WordPress Level 2. */
$root = dirname( __DIR__ );
$runner = (string) file_get_contents( $root . '/tools/check-integration' );
$preflight = (string) file_get_contents( $root . '/tools/check-test-infrastructure' );
$config = (string) file_get_contents( __DIR__ . '/wp-tests-config.php' );
$bootstrap = (string) file_get_contents( __DIR__ . '/phpunit-notes-bootstrap.php' );
$suite = (string) file_get_contents( __DIR__ . '/phpunit-notes.xml.dist' );
$integration = (string) file_get_contents( __DIR__ . '/integration/NotesRuntimePrivacyIntegrationTest.php' );
$recovery = (string) file_get_contents( __DIR__ . '/integration/NotesRuntimeRecoveryIntegrationTest.php' );
$sharing = (string) file_get_contents( __DIR__ . '/integration/NotesRuntimeMutationSharingIntegrationTest.php' );
$checks = [
    'Unique Notes schema is generated inside approved execution; no existing database reset' =>
        str_contains( $runner, 'DB_NAME="core_blueprint_notes_int_$(php -r' )
        && str_contains( $runner, 'random_bytes(6)' )
        && str_contains( $runner, '^core_blueprint_notes_int_[a-f0-9]{12}_test$' )
        && str_contains( $runner, 'CREATE DATABASE ' )
        && str_contains( $runner, 'DROP DATABASE ' )
        && str_contains( $runner, 'OWN_DB=1' )
        && ! str_contains( $runner, 'DROP DATABASE IF EXISTS core_blueprint_notes_test' )
        && ! str_contains( $runner, 'core_blueprint_work_test' )
        && ! str_contains( $runner, 'wordpress_test' ),
    'Canonical MariaDB helper and safety preflight before database creation' =>
        str_contains( $runner, 'cb-base-test-db' )
        && str_contains( $preflight, 'mariadb:10.11.19' )
        && str_contains( $runner, 'DB_HOST="127.0.0.1:3307"' )
        && str_contains( $runner, 'bash "$ROOT/tools/check-test-infrastructure"' )
        && str_contains( $runner, 'CB_NOTES_DB_MUTATION_APPROVED' )
        && strpos( $runner, 'check-test-infrastructure' ) < strpos( $runner, 'CREATE DATABASE ' ),
    'Isolated runtime root, staging and serialized runs' =>
        str_contains( $runner, '/tmp/core-blueprint-tests/core-blueprint-notes' )
        && str_contains( $runner, 'flock -x 9' )
        && str_contains( $runner, 'CB_TEST_NOTES_PLUGIN_FILE' )
        && str_contains( $runner, '--exclude=' )
        && str_contains( $runner, 'trap cleanup EXIT' ),
    'WordPress compatibility and real PHPUnit failures remain blocking' =>
        str_contains( $runner, '7.0) WP_TEST_TAG=7.0.0' )
        && str_contains( $runner, '7.1.3) WP_TEST_TAG=7.1.0' )
        && str_contains( $runner, 'vendor/bin/phpunit' )
        && str_contains( $runner, '--fail-on-skipped' )
        && str_contains( $runner, 'WordPress database error' ),
    'PHPUnit config and actual tests enforce isolated random Notes identifier' =>
        str_contains( $config, "'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'" )
        && str_contains( $config, "'127.0.0.1:3307' !== \$db_host" )
        && str_contains( $integration, "'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'" )
        && str_contains( $recovery, "'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'" )
        && str_contains( $sharing, "'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'" ),
    'Base schema activation precedes Notes boot' =>
        str_contains( $bootstrap, "tests_add_filter( 'muplugins_loaded'" )
        && str_contains( $bootstrap, 'CB_TEST_BASE_PLUGIN_FILE' )
        && str_contains( $bootstrap, 'CB_TEST_NOTES_PLUGIN_FILE' )
        && str_contains( $bootstrap, 'Core::activate();' )
        && str_contains( $bootstrap, '}, 2 );' ),
    'Runner references owner-privacy and AI integration tests' =>
        str_contains( $suite, 'phpunit-notes-bootstrap.php' )
        && str_contains( $suite, 'integration</directory>' )
        && str_contains( $integration, 'Abilities::read_note' )
        && str_contains( $integration, 'rest_do_request' ),
];
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Notes Level 2 safety contract failed: $label\n" );
        exit( 1 );
    }
}
echo "Notes Level 2 source safety contract: PASS\n";
