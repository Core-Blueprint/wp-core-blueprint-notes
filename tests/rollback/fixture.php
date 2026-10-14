<?php
// WP-CLI eval-file compatible: no strict_types at eval() scope.
use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;

$phase = (string) getenv('CB_NOTES_ROLLBACK_PHASE');
$expected = $phase === 'target_exposure'
    ? 'core_blueprint_notes_rollback_target'
    : 'core_blueprint_notes_rollback_source';
if (!in_array($phase, ['seed', 'revoke', 'source_still_revoked', 'target_exposure'], true)
    || DB_NAME !== $expected || PHP_SAPI !== 'cli') {
    throw new RuntimeException('Refusing unexpected rollback test phase or database.');
}
$checks = 0;
$must = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) {
        throw new RuntimeException('Notes rollback test FAILED: ' . $message);
    }
};
if ($phase === 'seed') {
    $uid = wp_create_user(
        'cbnotes_rollback_editor', 'disposable-rollback-only',
        'notes-rollback@invalid.example'
    );
    $must(is_int($uid) && $uid > 0, 'Create synthetic owner');
    (new WP_User($uid))->set_role('editor');
    wp_set_current_user($uid);
    $note = wp_insert_post([
        'post_type' => Plugin::TYPE,
        'post_author' => $uid,
        'post_title' => 'Synthetic historical public share',
        'post_status' => 'private',
        'post_content' => '<!-- wp:paragraph --><p>ROLLBACK_SYNTHETIC_ONLY</p><!-- /wp:paragraph -->',
    ], true);
    $must(is_int($note) && $note > 0, 'Create private synthetic note');
    $read = rest_do_request(new WP_REST_Request(
        'GET', '/core-blueprint-notes/v1/sharing/' . $note
    ));
    $must($read->get_status() === 200, 'Owner can inspect share settings');
    $state = $read->get_data();
    $req = new WP_REST_Request('POST', '/core-blueprint-notes/v1/sharing/' . $note);
    $req->set_body_params([
        'mode' => 'public',
        'expected_mode' => 'private',
        'expected_content_version' => $state['content_version'],
        'expected_modified_gmt' => $state['modified_gmt'],
        'confirmation' => 'PUBLIC',
    ]);
    $share = rest_do_request($req);
    $must($share->get_status() === 200, 'Explicit synthetic share creation succeeds');
    $key = (string) get_post_meta($note, Controller::PUBLIC_KEY, true);
    $snapshot = get_post_meta($note, Controller::PUBLIC_META, true);
    $must(preg_match('/^[a-f0-9]{32}$/D', $key) === 1, 'Opaque test token format');
    $must(Controller::public_snapshot_allowed(get_post($note), $key, $snapshot),
        'Public synthetic token is authorized before backup');
    update_option('cb_notes_rollback_manifest_v1', [
        'owner_id' => $uid, 'note_id' => $note, 'synthetic_key' => $key,
    ], false);
    $must(is_array(get_option('cb_notes_rollback_manifest_v1')),
        'SQL snapshot contains the synthetic test manifest');
    echo "Notes rollback rehearsal: source active-share seed PASS ({$checks} checks)\n";
    return;
}
$m = get_option('cb_notes_rollback_manifest_v1');
$must(is_array($m) && isset($m['owner_id'], $m['note_id'], $m['synthetic_key']),
    'Read restored test-only manifest');
$post = get_post((int) $m['note_id']);
$must($post instanceof WP_Post && $post->post_type === Plugin::TYPE,
    'Synthetic private note record retained');
$must((int) $post->post_author === (int) $m['owner_id'],
    'Synthetic owner mapping retained');
$key = (string) $m['synthetic_key'];
$must(preg_match('/^[a-f0-9]{32}$/D', $key) === 1, 'Synthetic key is well formed');
if ($phase === 'revoke') {
    wp_set_current_user((int) $m['owner_id']);
    $req = new WP_REST_Request('POST', '/core-blueprint-notes/v1/sharing/' . $post->ID);
    $req->set_param('mode', 'private');
    $response = rest_do_request($req);
    $must($response->get_status() === 200, 'Owner revocation succeeds');
    $must(get_post_meta($post->ID, Controller::PUBLIC_KEY, true) === '',
        'Revoked source no longer stores its public key');
    $must(!Controller::public_snapshot_allowed(
        $post, $key, get_post_meta($post->ID, Controller::PUBLIC_META, true)
    ), 'Previously active token is denied in current source');
    echo "Notes rollback rehearsal: source revocation PASS ({$checks} checks)\n";
    return;
}
$allowed = Controller::public_snapshot_allowed(
    $post, $key, get_post_meta($post->ID, Controller::PUBLIC_META, true)
);
if ($phase === 'source_still_revoked') {
    $must(!$allowed, 'Original source remains revoked after separate target restore');
    echo "Notes rollback rehearsal: original source remains revoked PASS ({$checks} checks)\n";
    return;
}
// This is an intentionally EXPECTED VULNERABILITY REPRODUCTION on Alpha24.
// Never run on a publicly addressable site; never print/reveal the bearer key.
$must($allowed, 'Historical active-key SQL restore reauthorizes revoked share');
echo "Notes rollback rehearsal: RISK REPRODUCED after old SQL restore ({$checks} checks)\n";
