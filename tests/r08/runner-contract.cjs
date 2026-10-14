'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const runner = read('tools/check-secure-runtime');
const fixture = read('tests/r08/fixture.php');
const config = read('tests/r08/wordpress-config.php');
function check(ok, label) {
    if (!ok) throw new Error('R08 real-provider source contract BLOCKED: ' + label);
}
const syntax = spawnSync('bash', ['-n', path.join(root, 'tools/check-secure-runtime')], {
    encoding: 'utf8', timeout: 10000,
});
check(!syntax.error && syntax.status === 0,
    'Entire Bash script syntax must pass: ' + (syntax.stderr || 'unknown error').trim());
check(runner.includes('bash -n "$0"') &&
    runner.indexOf('bash -n "$0"') < runner.indexOf('CREATE DATABASE core_blueprint_notes_r08_test'),
    'Whole-script Bash parse must precede disposable DB allocation');
check(runner.includes('DB="core_blueprint_notes_r08_test"') &&
    config.includes("core_blueprint_notes_r08_test") &&
    fixture.includes("core_blueprint_notes_r08_test"),
    'All components must pin the dedicated test-only DB');
check(config.includes("PHP_SAPI !== 'cli'") &&
    config.includes("define('WP_HTTP_BLOCK_EXTERNAL', true)") &&
    config.includes("define('DISABLE_WP_CRON', true)"),
    'Synthetic WordPress must be CLI-only and disallow HTTP egress');
check(runner.includes('SS_HEAD="88c15c45a4898dd1c43c1dfad1d7265f5b693350"') &&
    runner.includes('SECURE="$HOME/Downloads/wp-core-blueprint-secure-share-r08"') &&
    runner.includes('git -C "$SECURE" rev-parse HEAD') &&
    runner.includes('CB_NOTES_SECURE_SHARE_REPO="$SECURE" node'),
    'Actual reviewed Secure Share clean worktree must be checked and used');
check(runner.includes('core-blueprint-notes-0.1.0-alpha26.zip') &&
    runner.includes('PIN="$(bash "$ROOT/tools/check-alpha26-artifact")"') &&
    runner.includes('sha256sum "$ZIP"') &&
    runner.includes('unzip -t "$ZIP"') &&
    read('tools/check-alpha26-artifact').includes('notes-alpha26-verified.txt'),
    'Runner must use exact-head double-build-attested Alpha26 ZIP');
check(runner.includes('flock -x 9') &&
    runner.includes('Dedicated R08 database already exists; refusing to change it.') &&
    runner.includes('OWN_DB=0') && runner.includes('OWN_DB=1') &&
    runner.indexOf('OWN_DB=1') > runner.indexOf('CREATE DATABASE core_blueprint_notes_r08_test') &&
    runner.includes('if [[ "$OWN_DB" == 1 ]]'),
    'Runner must refuse existing DB and clean only its own newly created DB');
check(runner.includes('wp_test plugin activate core-blueprint-secure-share --quiet') &&
    runner.includes('CB_NOTES_R08_PHASE=integrated wp_test eval-file'),
    'Real separate Secure Share plugin must be activated with a WP-CLI test fixture');
check(fixture.includes('SecureInstaller::ready()') &&
    fixture.includes('ProviderRegistry::get(') &&
    fixture.includes('Services::repository()->findById') &&
    fixture.includes('Services::accessService()->inspectBearer') &&
    fixture.includes('$provider->deliver($context)'),
    'Fixture must inspect real plugin schema, grant store, verified bearer and registered provider');
check(fixture.includes('PasswordHasher::verify(') &&
    fixture.includes('ShareAction::View, false') &&
    fixture.includes('ShareAction::View, true') &&
    fixture.includes('Services::accessService()->context(') &&
    fixture.includes('$grant->expiresAt()') &&
    fixture.includes('revokedAt() !== null') &&
    fixture.includes('revision() === 2') &&
    fixture.includes('Services::accessService()->inspectBearer($bearer)') &&
    fixture.includes('$provider->deliver($context)'),
    'Fixture must execute real password, expiry, context and provider revoke checks');
check(runner.includes('HTTPS browser, cookies, CDN and provider-side old-backup rollback: NOT RUN') &&
    fixture.includes('R08 HTTPS browser flow, cookies, CDN and provider rollback: NOT RUN'),
    'No source test or CLI runtime may claim separate HTTPS browser acceptance');
console.log('Notes R08 first-party provider runtime source contract: PASS (runtime NOT EXECUTED)');
