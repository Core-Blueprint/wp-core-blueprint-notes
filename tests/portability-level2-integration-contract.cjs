'use strict';
/**
 * Source guard only. Real WP/MariaDB acceptance is owned by the existing
 * product Level 2 entrypoint, never by this Node contract.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const runner = read('tools/check-integration');
const infra = read('tools/check-test-infrastructure');
const config = read('tests/wp-tests-config.php');
const phpunit = read('tests/phpunit-notes.xml.dist');
const suite = read('tests/integration/NotesRuntimePortabilityImportIntegrationTest.php');
assert.ok(!suite.includes('self::assertWPError('),
    'WordPress PHPUnit assertWPError is an instance assertion, including in callbacks');
const provider = read('src/Portability/Provider.php');
const page = read('src/Portability/Page.php');

assert.ok(runner.includes('core_blueprint_notes_int_$(php -r') &&
    runner.includes('CREATE DATABASE') && runner.includes('OWN_DB=1') &&
    runner.includes('cb-base-test-db') &&
    runner.includes('CB_NOTES_DB_MUTATION_APPROVED') &&
    runner.includes('bash "$ROOT/tools/check-test-infrastructure"'),
    'Notes Level 2 uses new-only product schema after explicit infrastructure preflight');
assert.ok(config.includes("'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'") &&
    suite.includes("'/^core_blueprint_notes_int_[a-f0-9]{12}_test$/D'") &&
    suite.includes("'127.0.0.1:3307'"),
    'WordPress/PHPUnit cannot mutate Base, production or historical Notes schemas');
assert.ok(phpunit.includes('<directory suffix="Test.php">integration</directory>') &&
    runner.includes('tests/phpunit-notes.xml.dist') &&
    runner.includes('--fail-on-skipped') &&
    runner.includes('--fail-on-incomplete'),
    'New Notes import integration test is discovered by canonical Level 2 gate');
for (const name of [
    'test_real_owner_import_creates_remapped_private_note_once',
    'test_invalid_relations_and_other_owner_cannot_begin_import',
    'test_real_catalog_write_failure_stops_before_first_note_or_journal',
    'test_missing_receipt_meta_after_post_insert_blocks_automatic_retry',
    'test_real_pending_journal_blocks_retry_without_creating_duplicate',
    'test_owner_markdown_zip_keeps_gutenberg_source_and_excludes_foreign_notes',
    'test_base_engine_requires_attested_notes_session_for_public_import',
    'test_real_wordpress_insert_failure_leaves_reviewable_pending_intent'
]) {
    assert.ok(suite.includes('function ' + name + '('), 'Required real WP test missing: ' + name);
}
assert.ok(suite.includes('ImportSession::with_validated_json(') &&
    suite.includes('Engine::export_json(') &&
    suite.includes('Engine::preview_json(') &&
    suite.includes('ImportJournal::reserve(') &&
    suite.includes("add_filter('wp_insert_post_empty_content'"),
    'Suite must exercise real WordPress persistence and fail-closed runtime');
assert.ok(provider.includes('Foundation::SUPPORT_IMPORT') &&
    page.includes('ImportFlow::apply_json(') &&
    !page.includes('ImportApplier::apply(') &&
    read('src/Portability/ImportFlow.php').includes('Engine::apply_json('),
    'Import exposed only through Notes-authorized canonical Base Engine flow');
assert.ok(infra.includes('Expected existing mariadb:10.11.19 helper') &&
    infra.includes('Existing helper stopped; do not create or start a container automatically'),
    'Container must never be provisioned or started by a read-only preflight');

console.log('Notes P2-B isolated Level 2 coverage source contract: PASS (WP runtime NOT RUN)');
