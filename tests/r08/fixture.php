<?php
// WP-CLI eval-file: no top-level strict_types declaration.
use CB\Notes\Plugin as Notes;
use CB\Notes\Sharing\Controller;
use CB\Notes\Integration\SecureProvider;
use CB\SecureShare\Plugin as SecurePlugin;
use CB\SecureShare\Database\Installer as SecureInstaller;
use CB\SecureShare\Domain\GrantId;
use CB\SecureShare\Domain\ShareAction;
use CB\SecureShare\PublicApi\ProviderRegistry;
use CB\SecureShare\PublicApi\ResourceUnavailable;
use CB\SecureShare\Runtime\Services;
use CB\SecureShare\Runtime\SessionHandle;
use CB\SecureShare\Security\PasswordHasher;

if (PHP_SAPI !== 'cli' || DB_NAME !== 'core_blueprint_notes_r08_test'
    || (string)getenv('CB_NOTES_R08_PHASE') !== 'integrated'
    || (string)get_option('home') !== 'https://cb-notes-r08.invalid') {
    throw new RuntimeException('R08 fixture refuses any unrecognized database, phase or home URL.');
}
$checks = 0;
$must = static function(bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException('R08 first-party provider FAIL: ' . $label);
};
$refused = static function (callable $fn, string $message) use ($must): void {
    try {
        $fn();
        $must(false, $message);
    } catch (ResourceUnavailable | \CB\SecureShare\Runtime\RuntimeFailure $e) {
        $must(true, $message);
    }
};
$must(CB_NOTES_VERSION === '0.1.0-alpha26', 'Alpha26 code actually installed');
$must(defined('CB_SECURE_SHARE_VERSION') && CB_SECURE_SHARE_VERSION === '0.1.0-dev',
    'Real Secure Share development plugin actually installed');
$must(SecurePlugin::is_booted() && SecureProvider::available(),
    'Secure Share real runtime booted and recognized by Notes');
$must(ProviderRegistry::get('core-blueprint-notes') === \CB\Notes\Integration\SecureShareResource::instance(),
    'Real Secure Share provider registry contains exact Notes adapter');
$must(SecureInstaller::ready(), 'Real Secure Share tables exist in isolated database');

$editor = wp_create_user('cbnotes_r08_editor', 'r08-isolated-demo-only', 'r08@invalid.example');
$must(is_int($editor) && $editor > 0, 'Create synthetic owner');
(new WP_User($editor))->set_role('editor');
$foreign = wp_create_user('cbnotes_r08_foreign', 'r08-other-demo-only', 'other-r08@invalid.example');
$must(is_int($foreign) && $foreign > 0, 'Create distinct foreign user');
(new WP_User($foreign))->set_role('editor');
wp_set_current_user($editor);
$note = wp_insert_post([
    'post_type' => Notes::TYPE,
    'post_author' => $editor,
    'post_status' => 'private',
    'post_title' => 'R08 Synthetic Shared Note',
    'post_content' => '<!-- wp:paragraph --><p>R08_SYNTHETIC_PRIVATE</p><!-- /wp:paragraph -->',
], true);
$must(is_int($note) && $note > 0, 'Create synthetic private Gutenberg Note');
$route = '/core-blueprint-notes/v1/sharing/' . $note;
$state = rest_do_request(new WP_REST_Request('GET', $route));
$must($state->get_status() === 200 && $state->get_data()['secure_available'] === true,
    'Notes reports real Secure Share provider available');
$stateData = $state->get_data();
wp_set_current_user($foreign);
$deny = new WP_REST_Request('POST', $route);
$deny->set_param('mode', 'secure');
$deny->set_param('id', $note);
// WordPress REST returns 403 for an authenticated user denied by permission_callback.
$must(rest_do_request($deny)->get_status() === 403,
    'Foreign owner is forbidden from issuing a Secure Share grant for another note');
wp_set_current_user($editor);
$create = new WP_REST_Request('POST', $route);
$create->set_param('id', $note);
$create->set_body_params([
    'mode' => 'secure',
    'expected_mode' => 'private',
    'expected_content_version' => $stateData['content_version'],
    'expected_modified_gmt' => $stateData['modified_gmt'],
    'password' => 'r08-local-test-password-2026',
    'expiry_days' => 1,
]);
$issued = rest_do_request($create);
$must($issued->get_status() === 200, 'Owner issues real password-protected Secure Share grant');
$data = $issued->get_data();
$must(is_array($data) && ($data['mode'] ?? '') === 'secure'
    && isset($data['invitation_url']) && is_string($data['invitation_url']),
    'Notes returns one-time invite only on issuance');
$invite = (string)$data['invitation_url']; // NEVER echo/log this value.
$must(parse_url($invite, PHP_URL_SCHEME) === 'https'
    && preg_match('/^[A-Za-z0-9_-]{43}$/D', (string)parse_url($invite, PHP_URL_FRAGMENT)) === 1
    && !str_contains((string)parse_url($invite, PHP_URL_QUERY), 'r08-local-test'),
    'Invitation uses HTTPS and a secret fragment, not the request query');
$snapshot = get_post_meta($note, Controller::SECURE_META, true);
$must(is_array($snapshot) && ($snapshot['grant_id'] ?? '') !== ''
    && ($snapshot['revision'] ?? null) === 1
    && get_post_meta($note, Controller::MODE_META, true) === 'secure',
    'Notes finalized exact grant/revision and secure snapshot');
$grant = Services::repository()->findById(new GrantId($snapshot['grant_id']));
$must($grant !== null && $grant->revision() === 1
    && $grant->resource()->provider() === 'core-blueprint-notes'
    && $grant->resource()->type() === 'note'
    && $grant->resource()->key() === $note . ':' . $snapshot['key']
    && $grant->createdBy() === $editor,
    'Real Secure Share database persists exact grant and owner');
$must($grant->requiresPassword()
    && !PasswordHasher::verify('wrong-synthetic-password', $grant->passwordHash())
    && PasswordHasher::verify('r08-local-test-password-2026', $grant->passwordHash()),
    'Real provider hashes password and rejects incorrect proof');
$expires = $grant->expiresAt();
$must($expires instanceof DateTimeImmutable
    && $expires->getTimestamp() > time()
    && $expires->getTimestamp() <= time() + 90000,
    'Real provider stores one-day time-bounded expiry');

$bearer = (string)parse_url($invite, PHP_URL_FRAGMENT);
$inspected = Services::accessService()->inspectBearer($bearer);
$must($inspected->id()->value() === $snapshot['grant_id'],
    'Real Secure Share bearer lookup validates persisted token digest');
$handle = SessionHandle::forGrant($inspected);
$refused(static function() use ($inspected, $handle): void {
    Services::accessService()->context($inspected, $handle, ShareAction::View, false);
}, 'Password-protected grant denies context before password proof');
$context = Services::accessService()->context($inspected, $handle, ShareAction::View, true);
$provider = ProviderRegistry::get('core-blueprint-notes');
$resource = $provider->deliver($context);
$must($resource->contentType() === 'text/html'
    && str_contains($resource->body(), 'R08_SYNTHETIC_PRIVATE'),
    'Actual registered Notes provider delivers frozen note from real authorized context');
// A post edit after issuance must never mutate its frozen secure snapshot.
wp_update_post([
    'ID' => $note,
    'post_content' => '<!-- wp:paragraph --><p>R08_NEW_UNSHARED_CONTENT</p><!-- /wp:paragraph -->',
]);
$stillFrozen = $provider->deliver($context);
$must(str_contains($stillFrozen->body(), 'R08_SYNTHETIC_PRIVATE')
    && !str_contains($stillFrozen->body(), 'R08_NEW_UNSHARED_CONTENT'),
    'Private edits cannot mutate issued secure snapshot');
$stop = new WP_REST_Request('POST', $route);
$stop->set_param('id', $note);
$stop->set_param('mode', 'private');
$revoked = rest_do_request($stop);
$must($revoked->get_status() === 200
    && get_post_meta($note, Controller::SECURE_META, true) === ''
    && get_post_meta($note, Controller::MODE_META, true) === 'private',
    'Notes revocation immediately removes local provider authority');
$revokedGrant = Services::repository()->findById(new GrantId($snapshot['grant_id']));
$must($revokedGrant !== null && $revokedGrant->revokedAt() !== null
    && $revokedGrant->revision() === 2,
    'Real Secure Share provider grant is revoked and revision advanced');
$refused(static function() use ($bearer): void {
    Services::accessService()->inspectBearer($bearer);
}, 'Old bearer cannot authorize after provider revocation');
$refused(static function() use ($provider, $context): void {
    $provider->deliver($context);
}, 'Cached old AccessContext cannot retrieve Notes after local revocation');
echo "Notes R08 first-party Secure Share CLI runtime: PASS ({$checks} checks)\n";
echo "R08 HTTPS browser flow, cookies, CDN and provider rollback: NOT RUN\n";
