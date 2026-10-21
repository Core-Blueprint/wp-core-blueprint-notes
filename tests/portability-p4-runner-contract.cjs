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

assert.ok(canonical.includes('if [[ "$MODE" == "--p4-preflight" || "$MODE" == "--p4-execute" ]]') &&
    canonical.includes('bash "$ROOT/tools/check-portability-p4" "--${MODE#--p4-}"'),
    'P4 must be selected through the existing canonical Notes Level 2 runner');
assert.ok(canonical.includes('MODE="${1:---preflight}"') &&
    canonical.includes('core_blueprint_notes_int_$(php -r'),
    'P4 cannot weaken or replace existing PHPUnit Level 2');
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
