'use strict';

// Source-only checks. Do not confuse these with an executed SQL backup/restore.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const runner = read('tools/check-restore');
const config = read('tests/restore/wordpress-config.php');
const fixture = read('tests/restore/fixture.php');
// WP-CLI eval-file prefixes source with a PHP close tag and executes it via eval().
// A strict_types declaration then raises a fatal; this fixture is eval-compatible.
if (/^\s*declare\s*\(\s*strict_types\s*=/m.test(fixture)) {
    throw new Error('Notes restore gate: wp eval-file fixture must not declare strict_types');
}
const expectedSha = '77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908';

function check(ok, description) {
    if (!ok) throw new Error('Notes restore gate: ' + description);
}
for (const name of [
    'core_blueprint_notes_restore_source',
    'core_blueprint_notes_restore_target',
]) {
    check(runner.includes(name) && config.includes(name) && fixture.includes(name),
        'Both isolated names must be hardcoded and present in all components: ' + name);
}
check(runner.includes('EXPECTED_ZIP_SHA="' + expectedSha + '"') &&
    runner.includes('actual_sha') && runner.includes('sha256sum "$ZIP"'),
    'Restore must pin exact operator-confirmed artifact');
check(runner.includes("grep -Fxq -- \"$name\"") &&
    runner.includes('Database already exists, refusing to modify it'),
    'Existing database names must cause a hard stop');
check(runner.includes('flock -x 9') && runner.includes('OWN_DBS=0') &&
    runner.includes('if [[ "$OWN_DBS" == "1" ]]'),
    'Runner uses exclusive lock and cleanup ownership');
const createDbAt = runner.indexOf('CREATE DATABASE core_blueprint_notes_restore_source');
const cleanupOwnershipAt = runner.indexOf('OWN_DBS=1');
check(createDbAt >= 0 && cleanupOwnershipAt > createDbAt &&
    runner.includes('NO automatic DROP; inspect dedicated names before retrying.'),
    'Runner must acquire cleanup ownership only after both CREATE statements succeed');
check(runner.includes('mariadb-dump') &&
    runner.includes('"$SOURCE_DB" > "$SQL"') &&
    runner.includes('"$TARGET_DB" < "$SQL"'),
    'A full database SQL dump and separate destination import are required');
check(runner.includes('wp_site source core install') &&
    runner.includes('wp_site source plugin activate core-blueprint') &&
    runner.includes('wp_site source plugin activate core-blueprint-notes') &&
    runner.includes('wp_site target eval-file'),
    'Both source and target bootstrap through real WP-CLI');
check(config.includes("PHP_SAPI !== 'cli'") &&
    config.includes("define('DB_HOST', '127.0.0.1:3307')") &&
    config.includes("define('WP_HTTP_BLOCK_EXTERNAL', true)"),
    'Site config restricts runtime, database host, and HTTP egress');
check(fixture.includes("hash('sha256', $note_a->post_content)") &&
    fixture.includes('Catalog::labels_for_post($mine)') &&
    fixture.includes('wp_get_post_revisions($mine)') &&
    fixture.includes('PrivilegedAccessRegistry::is_approved($admin)'),
    'Post content, catalogs, revisions and Base approval are checked');
check(fixture.includes("'revision_hashes_a' => $revision_hashes") &&
    fixture.includes('count($revisions_after) === count($expected_revisions)') &&
    fixture.includes('$revision_post_id = (int) $revision_id;') &&
    fixture.includes('wp_get_post_revision($revision_post_id)') &&
    !fixture.includes('wp_get_post_revision((int) $revision_id)') &&
    fixture.includes("hash('sha256', $revision->post_content) === $hash"),
    'SQL roundtrip verifies exact native revision IDs, counts and content');
check(fixture.includes('Controller::PUBLIC_KEY') &&
    fixture.includes('rest_do_request(') &&
    fixture.includes('wp_set_current_user(0)'),
    'Sharing and cross-user/anonymous gates must be tested');
console.log('Notes disposable full SQL restore source safety gate: PASS (runner NOT EXECUTED)');
