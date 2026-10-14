'use strict';

/**
 * R08.3: optional first-party HTTPS/browser security source review.
 *
 * This does not spin up a TLS listener or manufacture browser/session PASS.
 * Real browser and origin testing is a separate acceptance gate.
 */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const cp = require('node:child_process');

const notesRoot = path.resolve(__dirname, '../..');
const ssRoot = path.resolve(process.env.CB_NOTES_SECURE_SHARE_REPO ||
    path.join(os.homedir(), 'Downloads/wp-core-blueprint-secure-share-r08'));
const pinned = '88c15c45a4898dd1c43c1dfad1d7265f5b693350';

function die(message) {
    throw new Error('Notes R08.3 HTTPS source audit BLOCKED: ' + message);
}
function file(root, name) {
    const filename = path.join(root, name);
    if (!fs.existsSync(filename) || !fs.statSync(filename).isFile()) {
        die('Missing expected source: ' + name);
    }
    return fs.readFileSync(filename, 'utf8');
}
function git(root, ...args) {
    const result = cp.spawnSync('git', args, {
        cwd: root, encoding: 'utf8', timeout: 10000,
    });
    if (result.error || result.status !== 0) die('Cannot verify source checkout: ' + root);
    return result.stdout.trim();
}
function assertIn(source, phrase, label) {
    if (!source.includes(phrase)) die(label + ' (required source expression absent)');
}
function assertEvery(source, fragments, label) {
    for (const phrase of fragments) assertIn(source, phrase, label);
}
const notesHead = git(notesRoot, 'rev-parse', 'HEAD');
const ssHead = git(ssRoot, 'rev-parse', 'HEAD');
if (ssHead !== pinned) die('Secure Share HEAD not pinned to audited main');
if (git(ssRoot, 'status', '--porcelain', '--untracked-files=no') ||
    git(notesRoot, 'status', '--porcelain', '--untracked-files=no')) {
    die('Source modifications present; avoid ambiguous security evidence');
}

const controller = file(ssRoot, 'src/Runtime/PublicController.php');
const routes = file(ssRoot, 'src/Runtime/PublicRoutes.php');
const cookies = file(ssRoot, 'src/Runtime/CookieManager.php');
const shell = file(ssRoot, 'src/Runtime/ShellRenderer.php');
const headers = file(ssRoot, 'src/Runtime/ResponseHeaders.php');
const handles = file(ssRoot, 'src/Runtime/SessionHandle.php');
const throttle = file(ssRoot, 'src/Runtime/PasswordThrottle.php');
const note = file(notesRoot, 'src/Integration/SecureShareResource.php');
const sharing = file(notesRoot, 'src/Sharing/Controller.php');

assertEvery(controller, [
    'if ( ! is_ssl() )',
    'self::renderShellError( 404, $method );',
    'ExchangeIntent::valid( $intent, CookieManager::bootstrapIntent() )',
    'CookieManager::setSession( $handle, $bearer, $grant, $now )',
    'CookieManager::setPasswordProof( $handle, $proof, $grant, $now )',
    'PasswordHasher::verify( $password, $grant->passwordHash() )',
    'PasswordThrottle::reserveAttempt( $handle )',
    'PasswordThrottle::blocked( $handle )',
    'self::sessionGrant( $handle )',
    'Services::accessService()->context( $grant, $handle, $action, $passwordVerified )',
    'self::provider( $grant )->deliver( $context )',
], 'Real HTTPS exchange, password and resource authorization');

assertEvery(shell, [
    'window.location.hash.startsWith',
    'window.history.replaceState(',
    "cb_secure_share_action: 'exchange'",
    'cb_secure_share_token: token',
    'cb_secure_share_intent: intent',
    'form.method =',
    'form.submit()',
], 'Fragment removal, exchange and intent on browser bootstrap');
assertEvery(cookies, [
    "'secure' => true",
    "'httponly' => true",
    "'samesite' => 'Strict'",
    'SessionHandle::sessionCookieName( $handle )',
    'self::sessionPath( $handle )',
    "self::BOOTSTRAP_COOKIE = '__Secure-cb_ss_bootstrap'",
], 'Narrowly scoped Secure HttpOnly Strict session cookies');
assertEvery(headers, [
    'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
    "header( 'Vary: Cookie', false )",
    'noindex, nofollow, noarchive, nosnippet',
    'Referrer-Policy: no-referrer',
    'Content-Security-Policy:',
], 'No-store, privacy and CSP response headers');
assertEvery(routes, [
    'self::matchesCurrentRequest( $route, $handle )',
    'CacheIsolation::protectCurrentRequest( $route )',
    'flush_rewrite_rules( false )',
], 'Authenticated rewrite and cache isolation');
assertEvery(handles, [
    "'grant_revision'",
    "'token_digest'",
    "hash_hmac( 'sha256', $payload, $key )",
], 'Session handles bound to grant revision and bearer digest');
assertEvery(throttle, [
    'MAX_FAILURES = 5',
    'reserveAttempt( string $handle )',
    'get_transient(',
], 'Password attempt throttling');
assertEvery(note, [
    'function deliver(AccessContext $context): ResourceResponse',
    'get_post_meta($id, Controller::SECURE_META, true)',
    'get_post_meta($id, Controller::MODE_META, true)',
    '$context->grantId()',
    '$context->grantRevision()',
    'throw ResourceUnavailable::unavailable()',
], 'Live Notes grant/mode/provider authorization');
assertEvery(sharing, [
    'self::revoke_old_secure($post->ID)',
    'delete_post_meta($id, self::SECURE_META)',
], 'Notes lifecycle local grant shutdown');

console.log('Notes R08.3 HTTPS security source contract: PASS (browser/TLS runtime NOT RUN)');
console.log('Notes source HEAD: ' + notesHead);
console.log('Secure Share source HEAD: ' + ssHead);
console.log('Real HTTPS fragment exchange, cookies, password POST, cache and session revocation: NOT RUN');
