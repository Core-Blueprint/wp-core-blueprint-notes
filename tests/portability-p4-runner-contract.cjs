'use strict';
// Source/safety guard ONLY. Never provisions WordPress or Docker.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = name => fs.readFileSync(path.resolve(__dirname, '..', name), 'utf8');
const canonical = read('tools/check-integration');
const helper = read('tools/check-portability-p4');
const site = read('tests/portability/p4-site.php');
const provider = read('src/Portability/Provider.php');
const cliProbe = read('tests/portability/p4-cli-args-probe.php');
assert.ok(!site.includes('global $args;') &&
    site.includes("isset($args) || !is_array($args)") &&
    site.includes("$mode = (string)($args[0] ?? '');"),
    'P4 must read WP-CLI eval-file local $args, not shadow them with global $args');
assert.ok(cliProbe.includes("$args !== ['p4-argv-probe']") &&
    helper.includes('wp eval-file "$ROOT/tests/portability/p4-cli-args-probe.php" p4-argv-probe --skip-wordpress') &&
    helper.indexOf('p4-cli-args-probe.php') < helper.indexOf('CREATE DATABASE'),
    'Read-only preflight must prove actual WP-CLI argument passing before SQL creation');

assert.ok(!/\bdeclare\s*\(\s*strict_types\s*=/.test(site),
    'WP-CLI eval-file test fixtures cannot contain a strict_types declaration');
assert.ok(helper.includes('cb-notes-p4-no-mail.php') &&
    helper.includes("add_filter('pre_wp_mail', static fn(): bool => true") &&
    helper.indexOf('cb-notes-p4-no-mail.php') < helper.indexOf('wp --path="$site" core install'),
    'Mail must be intercepted before any P4 WordPress install or user notification');

assert.ok(canonical.includes('if [[ "$MODE" == "--p4-preflight" || "$MODE" == "--p4-execute" ]]') &&
    canonical.includes('bash "$ROOT/tools/check-portability-p4" "--${MODE#--p4-}"'),
    'P4 must be selected through the existing canonical Notes Level 2 runner');
assert.ok(canonical.includes('MODE="${1:---preflight}"') &&
    canonical.includes('core_blueprint_notes_int_$(php -r'),
    'P4 cannot weaken or replace existing PHPUnit Level 2');
assert.ok(helper.includes('P4_EXISTING="$(docker exec') &&
    helper.includes("SCHEMA_NAME REGEXP '^core_blueprint_notes_p4_[0-9a-f]{12}_[ab]_test$'") &&
    helper.includes('Existing P4 schemas require operator review (never auto-drop)') &&
    helper.indexOf('P4_EXISTING=') < helper.indexOf('if [[ "$MODE" == --preflight ]]'),
    'Read-only P4 inventory must fail closed before returning a preflight PASS');
const create = helper.indexOf('CREATE DATABASE');
assert.ok(create > helper.indexOf('if [[ "$MODE" == --preflight ]]') &&
    create > helper.indexOf('CB_NOTES_P4_DB_MUTATION_APPROVED') &&
    helper.includes('bash "$ROOT/tools/check-test-infrastructure"') &&
    helper.includes('notes-p4-two-new-schemas-v1'),
    'Read-only infrastructure guard and separate P4 approval precede mutation');
assert.ok(helper.includes('core_blueprint_notes_p4_${TOKEN}_a_test') &&
    helper.includes('core_blueprint_notes_p4_${TOKEN}_b_test') &&
    helper.includes('^core_blueprint_notes_p4_[a-f0-9]{12}_a_test$') &&
    helper.includes('^core_blueprint_notes_p4_[a-f0-9]{12}_b_test$') &&
    !helper.includes('DROP DATABASE IF EXISTS'),
    'Two distinct new-only Notes schema names; no existing schema reset');
assert.ok(helper.includes('OWN_A=0') && helper.includes('OWN_B=0') &&
    helper.includes('if [[ "$OWN_A" == 1 ]]') &&
    helper.includes('if [[ "$OWN_B" == 1 ]]') &&
    helper.includes('trap cleanup EXIT'),
    'Cleanup is bounded to test resources created by this execution');
assert.ok(helper.includes('stage_site a "$A" "$DB_A"') &&
    helper.includes('stage_site b "$B" "$DB_B"') &&
    helper.includes('CASE="$ROOT/tests/portability/p4-site.php"') &&
    helper.includes('wp --path="$A" eval-file') &&
    helper.includes('wp --path="$B" eval-file'),
    'Site A and Site B have independent installed WordPress');
for (const phase of ['source','target-init','import','replay','export-target','verify']) {
    assert.ok(site.includes("case '" + phase + "':"), 'Missing Site A/B phase: ' + phase);
}
assert.ok(site.includes('semantic round-trip mismatch (field names only:') &&
    site.includes('array_keys($changed_fields)') &&
    !site.includes('print_r($source_notes') &&
    !site.includes('var_dump($target_notes'),
    'Portability diagnostics must reveal field names, not private note content');
assert.ok(site.includes('OwnerExport::json()') &&
    site.includes('OwnerExport::markdown_zip()') &&
    site.includes('ImportSession::with_validated_json(') &&
    site.includes('ImportJournal::receipt(') &&
    site.includes('cbp4_normalized(') &&
    site.includes("'modified_gmt' => $note['modified_gmt']") &&
    site.includes('FOREIGN-P4-SECRET') &&
    site.includes('FOREIGN-TARGET-SECRET') &&
    site.includes('ZipArchive'),
    'P4 must compare owner isolation, raw blocks, journal, timestamps and Markdown');
assert.ok(!provider.includes('Foundation::SUPPORT_IMPORT') &&
    !helper.includes('wp db reset') &&
    !helper.includes('wp core install --force') &&
    !helper.includes('docker start ') &&
    !helper.includes('docker run '),
    'Provider stays export-only; no container bootstrap or destructive reset');
console.log('Notes P4 installed-Site-A/B source contract: PASS (WP runtime NOT RUN)');
