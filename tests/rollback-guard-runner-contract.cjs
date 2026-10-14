'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const load = file => fs.readFileSync(path.join(root, file), 'utf8');
const runner = load('tools/check-rollback-guard');
const fixture = load('tests/rollback/fixture.php');
const config = load('tests/rollback/wordpress-config.php');
const router = load('tests/rollback/router.php');
const build = load('tools/build-release');
const fail = why => { throw new Error('Notes recovery guard runner contract: ' + why); };
const must = (ok, why) => { if (!ok) fail(why); };
const syntax = spawnSync('bash', ['-n', path.join(root, 'tools/check-rollback-guard')], {
    encoding: 'utf8', timeout: 10000,
});
must(!syntax.error && syntax.status === 0, 'Full runner Bash syntax fails: ' + (syntax.stderr || ''));
must(runner.includes('bash -n "$0"') && runner.indexOf('bash -n "$0"') < runner.indexOf('CREATE DATABASE core_blueprint_notes_rollback_source'),
    'Whole-script Bash preflight must precede any disposable DB creation');
must(runner.includes('ZIP="$ROOT/dist/core-blueprint-notes-0.1.0-alpha26.zip"') &&
    runner.includes('bash "$ROOT/tools/build-release"') &&
    build.includes("'0.1.0-alpha26'"),
    'Guard must rebuild the current Alpha26 artifact, not silently reuse Alpha24');
must(runner.includes('flock -x 9') && runner.includes('Disposable database already exists, refusing to touch:') &&
    runner.indexOf('OWN_DBS=1') > runner.indexOf('CREATE DATABASE core_blueprint_notes_rollback_source'),
    'Existing test DB refusal, lock and explicit cleanup ownership required');
must(runner.includes('CB_NOTES_ROLLBACK_DB="$TARGET_DB"') &&
    runner.includes('php -S 127.0.0.1:18778') &&
    router.includes("'/tmp/core-blueprint-tests/core-blueprint-notes-rollback/run.'") &&
    config.includes("($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'"),
    'Only isolated local target can serve HTTP');
must(config.includes("str_repeat(") &&
    config.includes("'a' : 'b'") &&
    runner.includes('source) path="$SRC"; db="$SOURCE_DB"; url="$ORIGIN"') &&
    runner.includes('target) path="$DST"; db="$TARGET_DB"; url="$ORIGIN"'),
    'Source and target must have same URL, differing only in independent epoch');
const steps = [
    'CB_NOTES_ROLLBACK_PHASE=seed',
    'mariadb-dump',
    'CB_NOTES_ROLLBACK_PHASE=revoke',
    '"$TARGET_DB" < "$SQL"',
    'CB_NOTES_ROLLBACK_PHASE=target_denied',
    'check_http 404 "$OLD_KEY"',
    'CB_NOTES_ROLLBACK_PHASE=target_reissue',
    'check_http 404 "$OLD_KEY" ',
    'check_http 200 "$NEW_KEY"',
];
let last = -1;
for (const phase of steps) {
    const at = runner.indexOf(phase, last + 1);
    must(at > last, 'Missing/out of order recovery acceptance phase: ' + phase);
    last = at;
}
must(fixture.includes('Old Public share cannot be delivered with a rotated epoch') &&
    fixture.includes('Old token is still in restored SQL') &&
    fixture.includes('Original synthetic private Gutenberg content survived SQL restore') &&
    fixture.includes('New E2 signed Public snapshot is valid'),
    'Restored old grant denial must not be confused with missing content or owner reissue');
must(!/^\s*declare\s*\(\s*strict_types\s*=/m.test(fixture),
    'WP-CLI eval-file fixture must not declare strict_types');
must(runner.includes('Notes historical share recovery guard: PASS (old HTTP 404; reissued HTTP 200)') &&
    runner.includes('CDN, full-host rollback and real Secure Share: NOT RUN'),
    'Never imply broad hosting or provider security acceptance');
console.log('Notes rollback recovery guard source contract: PASS (runtime NOT EXECUTED)');
