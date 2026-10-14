'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const load = file => fs.readFileSync(path.join(root, file), 'utf8');
const runner = load('tools/check-rollback-repro');
const config = load('tests/rollback/wordpress-config.php');
const fixture = load('tests/rollback/fixture.php');
function assert(ok, why) {
    if (!ok) throw new Error('Notes historical rollback safety gate: ' + why);
}
const syntax = spawnSync('bash', ['-n', path.join(root, 'tools/check-rollback-repro')], {
    encoding: 'utf8', timeout: 10000,
});
assert(!syntax.error && syntax.status === 0,
    'Entire runner Bash syntax must pass: ' + (syntax.stderr || 'unknown error').trim());
const source = 'core_blueprint_notes_rollback_source';
const target = 'core_blueprint_notes_rollback_target';
for (const name of [source, target]) {
    assert(runner.includes(name) && config.includes(name) && fixture.includes(name),
        'Fixed database name must be present in all components: ' + name);
}
assert(runner.includes('bash -n "$0"') &&
    runner.indexOf('bash -n "$0"') < runner.indexOf('CREATE DATABASE ' + source),
    'Runner must parse itself before any database creation');
assert(runner.includes('PINNED_SHA="22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc"') &&
    runner.includes('sha256sum "$ZIP"') && runner.includes('unzip -t "$ZIP"'),
    'Runner must refuse any unpinned release ZIP');
assert(runner.includes('flock -x 9') &&
    runner.includes('Disposable database already exists, refusing to touch:') &&
    runner.indexOf('OWN_DBS=1') > runner.indexOf('CREATE DATABASE ' + source) &&
    runner.includes('if [[ "$OWN_DBS" == 1 ]]'),
    'Lock, existing-DB refusal and database ownership must stay intact');
assert(config.includes("PHP_SAPI !== 'cli'") &&
    config.includes("define('DB_HOST', '127.0.0.1:3307')") &&
    config.includes("define('WP_HTTP_BLOCK_EXTERNAL', true)"),
    'Test must be CLI-only, pinned to local MariaDB, and block WordPress HTTP egress');
assert(fixture.includes("'confirmation' => 'PUBLIC'") &&
    fixture.includes("CB_NOTES_ROLLBACK_PHASE") &&
    fixture.includes("Controller::public_snapshot_allowed"),
    'Proof must create a confirmed test share and test real authorization');
const expectedOrder = [
    'CB_NOTES_ROLLBACK_PHASE=seed',
    'mariadb-dump',
    'CB_NOTES_ROLLBACK_PHASE=revoke',
    '"$TARGET_DB" < "$SQL"',
    'CB_NOTES_ROLLBACK_PHASE=target_exposure',
    'CB_NOTES_ROLLBACK_PHASE=source_still_revoked',
];
let lastAt = -1;
for (const step of expectedOrder) {
    const at = runner.indexOf(step);
    assert(at > lastAt, 'Proof phase absent or out of order: ' + step);
    lastAt = at;
}
assert(runner.includes('RISK REPRODUCED') && runner.includes('NOT been implemented') &&
    !runner.includes('Notes historical share rollback: SECURITY PASS'),
    'This proof must not falsely claim mitigation/security PASS');
assert(!/^\s*declare\s*\(\s*strict_types\s*=/m.test(fixture),
    'WP-CLI eval-file fixture must not declare strict_types');
console.log('Notes historical rollback reproduction source gate: PASS (runtime NOT RUN)');
