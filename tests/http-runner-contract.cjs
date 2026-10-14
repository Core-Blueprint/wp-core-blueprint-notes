'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const runner = read('tools/check-http');
const cfg = read('tests/http/wordpress-config.php');
const router = read('tests/http/router.php');
const fixture = read('tests/http/fixture.php');
const expected = '77035d6ba1c5e352d97f917eb8a4ddcaca09af3b36db155bbb2ab4303610e908';
const syntax = spawnSync('bash', ['-n', path.join(root, 'tools/check-http')], {
    encoding: 'utf8',
    timeout: 10000,
});
if (syntax.error || syntax.status !== 0) {
    throw new Error('Notes HTTP source gate: complete shell syntax validation failed: ' +
        (syntax.stderr || (syntax.error && syntax.error.message) || 'unknown error').trim());
}
function pass(condition, message) {
    if (!condition) throw new Error('Notes HTTP source gate: ' + message);
}
pass(runner.includes('bash -n "$0"') &&
    runner.indexOf('bash -n "$0"') < runner.indexOf('CREATE DATABASE core_blueprint_notes_http_test'),
    'HTTP runner must self-parse before any temporary database is created');
pass(runner.includes('core_blueprint_notes_http_test') &&
    cfg.includes("core_blueprint_notes_http_test") &&
    fixture.includes("core_blueprint_notes_http_test"),
    'HTTP test must use one fixed isolated database');
pass(runner.includes('127.0.0.1:18777') &&
    cfg.includes("127.0.0.1:18777") &&
    router.includes('CB_NOTES_HTTP_SITE') &&
    router.includes('core-blueprint-notes-http/run.'),
    'PHP built-in origin must be loopback-only and bound to the temporary test root');
pass(cfg.includes("PHP_SAPI, ['cli', 'cli-server']") &&
    cfg.includes("($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'") &&
    cfg.includes("define('WP_HTTP_BLOCK_EXTERNAL', true)"),
    'HTTP WordPress config rejects external clients and external WordPress HTTP requests');
pass(runner.includes('SHA="' + expected + '"') &&
    runner.includes('sha256sum "$ZIP"') && runner.includes('unzip -t "$ZIP"'),
    'Pinned original ZIP must be validated before any test DB creation');
pass(runner.includes("grep -Fxq -- \"$DB\"") &&
    runner.includes('Existing HTTP test database: never overwrite.') &&
    runner.indexOf('OWN_DB=1') > runner.indexOf('CREATE DATABASE core_blueprint_notes_http_test'),
    'Runner must refuse existing database and own cleanup only after CREATE succeeds');
pass(runner.includes('flock -x 9') && runner.includes('kill "$SERVER_PID"') &&
    runner.includes('DROP DATABASE IF EXISTS core_blueprint_notes_http_test'),
    'Runner must lock and clean up only its own server and fixed disposable DB');
pass(fixture.includes("['seed', 'edit', 'revoke']") &&
    fixture.includes('WP_Application_Passwords::create_new_application_password') &&
    fixture.includes("get_post_meta($note_a, Controller::PUBLIC_KEY") &&
    fixture.includes("'confirmation' => 'PUBLIC'"),
    'Fixture must create real A/B auth and an explicitly confirmed public snapshot');
pass(runner.includes('request owner 200 a') &&
    runner.includes('request b_foreign denied b') &&
    runner.includes('request anon_exact denied anon') &&
    runner.includes('request anon_list 200 anon') &&
    runner.includes("'^x-wp-total:[[:space:]]*0[[:space:]]*$'") &&
    runner.includes("'^x-wp-total:[[:space:]]*1[[:space:]]*$'") &&
    runner.includes('Anonymous collection count must reveal zero Notes.') &&
    runner.includes("B default publish-status REST collection must have total zero.") &&
    runner.includes("B's explicit private REST collection must count exactly B's own Note.") &&
    runner.includes("request b_list_private 200 b '/wp-json/wp/v2/cb-notes?context=edit&status=private'") &&
    runner.includes("request b_list_default 200 b '/wp-json/wp/v2/cb-notes'") &&
    runner.includes('"$WORK/b_list_private.body" "$NOTE_B"') &&
    runner.includes('foreign_write_status='),
    'HTTP test requires independent owner/B/anonymous REST and foreign mutation denial, with explicit private filter');
pass(runner.includes('request public 200 anon') &&
    runner.includes('request frozen 200 anon') &&
    runner.includes('request revoked 404 anon') &&
    runner.includes('request revoked_again 404 anon') &&
    runner.includes('NOTES_HTTP_NEW_PRIVATE_A'),
    'HTTP test must exercise a frozen snapshot and multiple post-revocation 404s');
pass((runner.match(/request public 200 anon/g) || []).length === 1 &&
    (runner.match(/request frozen 200 anon/g) || []).length === 1 &&
    (runner.match(/Notes real loopback HTTP origin: PASS/g) || []).length === 1,
    'HTTP runner must contain exactly one complete test sequence and PASS claim');
pass(runner.includes('^cache-control:.*no-store') &&
    runner.includes('^x-robots-tag:.*noindex') &&
    runner.includes('^content-security-policy:.*default-src'),
    'HTTP origin cache, indexing, and CSP response headers must be verified');
pass(!/^\s*declare\s*\(\s*strict_types\s*=/m.test(fixture) &&
    !runner.includes('core_blueprint_notes_test"'),
    'WP-CLI eval fixture must remain valid and preserve PHPUnit database isolation');
console.log('Notes local HTTP origin safety contract: PASS (HTTP runner NOT EXECUTED)');
