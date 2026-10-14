'use strict';

/**
 * R08 source interoperability gate against the actual checked-out Secure Share
 * repository. This is deliberately NOT an end-to-end HTTPS/browser test.
 *
 * No cloning, plugin activation, database changes or external access.
 */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawnSync } = require('node:child_process');

const notes = path.resolve(__dirname, '..');
const secure = path.resolve(process.env.CB_NOTES_SECURE_SHARE_REPO ||
    path.join(os.homedir(), 'Downloads/wp-core-blueprint-secure-share'));

function fail(label) { throw new Error('R08 Secure Share source gate BLOCKED: ' + label); }
function read(root, file) {
    const full = path.join(root, file);
    if (!fs.existsSync(full) || !fs.statSync(full).isFile()) {
        fail('Missing expected file ' + file + ' in ' + root);
    }
    return fs.readFileSync(full, 'utf8');
}
function exact(source, search, reason) {
    if (!source.includes(search)) fail(reason);
}
function git(dir, args) {
    const done = spawnSync('git', args, { cwd: dir, encoding: 'utf8', timeout: 10000 });
    if (done.status !== 0 || done.error) fail('Unable to validate clean repository: ' + dir);
    return done.stdout.trim();
}

if (!fs.existsSync(path.join(secure, 'core-blueprint-secure-share.php'))) {
    fail('Expected local checkout ~/Downloads/wp-core-blueprint-secure-share; ' +
        'clone the separate first-party repository into Downloads or explicitly set CB_NOTES_SECURE_SHARE_REPO.');
}
const notesHead = git(notes, ['rev-parse', 'HEAD']);
const secureHead = git(secure, ['rev-parse', 'HEAD']);
if (git(notes, ['status', '--porcelain', '--untracked-files=no']) ||
    git(secure, ['status', '--porcelain', '--untracked-files=no'])) {
    fail('Uncommitted tracked source changes. Refuse ambiguous runtime contract.');
}

const adapter = read(notes, 'src/Integration/SecureProvider.php');
const resource = read(notes, 'src/Integration/SecureShareResource.php');
const controller = read(notes, 'src/Sharing/Controller.php');
const ss = read(secure, 'src/PublicApi/SecureShare.php');
const shareRequest = read(secure, 'src/PublicApi/ShareRequest.php');
const issued = read(secure, 'src/PublicApi/IssuedShare.php');
const ctx = read(secure, 'src/PublicApi/AccessContext.php');
const interfaceCode = read(secure, 'src/PublicApi/ResourceProvider.php');
const plugin = read(secure, 'core-blueprint-secure-share.php');

exact(adapter, "interface_exists('CB\\\\SecureShare\\\\PublicApi\\\\ResourceProvider')",
    'Optional dependency must verify ResourceProvider interface.');
exact(adapter, 'SecureShare::isAvailable()', 'Secure Share readiness guard absent.');
exact(adapter, 'SecureShare::registerProvider(SecureShareResource::instance())',
    'Notes must register through consumer API.');
exact(interfaceCode, 'function providerId(): string', 'Provider ID method mismatch.');
exact(interfaceCode, 'function deliver( AccessContext $context ): ResourceResponse',
    'Resource delivery signature mismatch.');
exact(resource, 'implements ResourceProvider', 'Notes missing provider implementation.');
exact(resource, "return 'core-blueprint-notes'", 'Notes provider stable name mismatch.');
exact(resource, 'function deliver(AccessContext $context): ResourceResponse',
    'Notes provider method signature mismatch.');
for (const method of ['action()', 'resourceType()', 'resourceKey()', 'grantId()', 'grantRevision()']) {
    exact(resource, '$context->' + method, 'Notes missing validated context method ' + method);
    exact(ctx, 'function ' + method, 'Provider context API missing method ' + method);
}
exact(resource, 'throw ResourceUnavailable::unavailable()',
    'Provider must fail closed for stale/grant mismatch.');
exact(resource, "Controller::SECURE_META", 'Provider must check Notes current secure snapshot.');
exact(resource, "Controller::MODE_META", 'Provider must recheck current Notes share mode.');
for (const api of ['registerProvider(', 'issue(', 'revoke(']) {
    exact(ss, 'function ' + api, 'Secure Share facade missing ' + api);
    exact(controller, '::' + api, 'Notes controller missing facade ' + api);
}
for (const field of ['provider:', 'resourceType:', 'resourceKey:', 'actions:',
                     'label:', 'password:', 'expiresAt:', 'createdBy:']) {
    exact(controller, field, 'Notes consumer issue request missing ' + field);
}
exact(controller, '$issued->grantId()', 'Notes must persist the actual issued grant.');
exact(controller, '$issued->revision()', 'Notes must persist the actual grant revision.');
exact(controller, '$issued->invitationUrl()', 'Bearer must come only from initial provider issuance.');
for (const name of ['grantId()', 'revision()', 'invitationUrl()']) {
    exact(issued, 'function ' + name, 'IssuedShare public method mismatch ' + name);
}
exact(controller, 'self::revoke_old_secure($post->ID)', 'Notes must revoke old grants on replacement.');
exact(controller, 'delete_post_meta($id, self::SECURE_META)',
    'Revocation must locally disarm Notes delivery before provider cleanup.');
exact(plugin, 'CB_SECURE_SHARE_VERSION', 'Provider plugin is not the expected first-party extension.');
exact(shareRequest, 'ShareSecretPolicy::assertValid( $password )',
    'Password validation contract changed.');
exact(ss, 'ShareLink::invitation( $issued->credential() )',
    'HTTPS fragment invitation creation contract changed.');
exact(issued, "'https' !== $scheme", 'Invite URL must remain HTTPS-only.');
exact(resource, "get_post_meta($id, Controller::SECURE_META, true)",
    'Notes provider must re-check live snapshot at delivery.');

console.log('Notes R08 Secure Share API compatibility source: PASS');
console.log('Notes source HEAD: ' + notesHead);
console.log('Secure Share source HEAD: ' + secureHead);
console.log('R08 real HTTPS, provider persistence, password, expiry, revoke and rollback: NOT RUN');
