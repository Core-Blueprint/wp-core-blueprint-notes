'use strict';
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const runner = read('tools/check-http');
const cfg = read('tests/http/wordpress-config.php');
const router = read('tests/http/router.php');
const fixture = read('tests/http/fixture.php');
const expected = '22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc';
function pass(condition, message) {
    if (!condition) throw new Error('Notes HTTP source gate: ' + message);
}
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
    runner.includes('foreign_write_status='),
    'HTTP test requires independent owner/B/anonymous REST and foreign mutation denial');
pass(runner.includes('request public 200 anon') &&
    runner.includes('request frozen 200 anon') &&
    runner.includes('request revoked 404 anon') &&
    runner.includes('request revoked_again 404 anon') &&
    runner.includes('NOTES_HTTP_NEW_PRIVATE_A'),
    'HTTP test must exercise a frozen snapshot and multiple post-revocation 404s');
pass(runner.includes('^cache-control:.*no-store') &&
    runner.includes('^x-robots-tag:.*noindex') &&
    runner.includes('^content-security-policy:.*default-src'),
    'HTTP origin cache, indexing, and CSP response headers must be verified');
pass(!/^\s*declare\s*\(\s*strict_types\s*=/m.test(fixture) &&
    !runner.includes('core_blueprint_notes_test"'),
    'WP-CLI eval fixture must remain valid and preserve PHPUnit database isolation');
console.log('Notes local HTTP origin safety contract: PASS (HTTP runner NOT EXECUTED)');
