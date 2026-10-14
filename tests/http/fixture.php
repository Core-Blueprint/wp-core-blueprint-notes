<?php
// WP-CLI eval-file compatible (no declare strict_types).
use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;

if (DB_NAME !== 'core_blueprint_notes_http_test' || PHP_SAPI !== 'cli') {
    throw new RuntimeException('HTTP fixture refuses this WordPress runtime');
}
$phase = (string) getenv('CB_NOTES_HTTP_PHASE');
$manifest_path = (string) getenv('CB_NOTES_HTTP_MANIFEST');
if (!in_array($phase, ['seed', 'edit', 'revoke'], true)
    || $manifest_path === ''
    || !str_starts_with($manifest_path, '/tmp/core-blueprint-tests/core-blueprint-notes-http/run.')) {
    throw new RuntimeException('Invalid synthetic HTTP test phase/path');
}
$must = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('Notes HTTP fixture FAIL: ' . $label);
};
if ($phase === 'seed') {
    $users = [];
    foreach (['a', 'b'] as $slug) {
        $login = 'cbnotes_http_' . $slug;
        $uid = wp_create_user($login, 'local-disposable-http-' . $slug, $slug . '@cb-notes-http.invalid');
        $must(is_int($uid) && $uid > 0, 'Create editor ' . $slug);
        (new WP_User($uid))->set_role('editor');
        $pw = WP_Application_Passwords::create_new_application_password($uid, ['name' => 'Local disposable Notes HTTP']);
        $must(is_array($pw) && is_string($pw[0]) && $pw[0] !== '', 'Generate local application password ' . $slug);
        $users[$slug] = ['id' => $uid, 'login' => $login, 'password' => $pw[0]];
    }
    wp_set_current_user($users['a']['id']);
    $note_a = wp_insert_post([
        'post_type' => Plugin::TYPE, 'post_status' => 'private',
        'post_author' => $users['a']['id'], 'post_title' => 'Synthetic private A title',
        'post_content' => '<!-- wp:paragraph --><p>NOTES_HTTP_FROZEN_A</p><!-- /wp:paragraph -->'
    ], true);
    $must(is_int($note_a) && $note_a > 0, 'Create A private note');
    wp_set_current_user($users['b']['id']);
    $note_b = wp_insert_post([
        'post_type' => Plugin::TYPE, 'post_status' => 'private',
        'post_author' => $users['b']['id'], 'post_title' => 'Synthetic private B title',
        'post_content' => '<!-- wp:paragraph --><p>NOTES_HTTP_PRIVATE_B</p><!-- /wp:paragraph -->'
    ], true);
    $must(is_int($note_b) && $note_b > 0, 'Create B private note');

    wp_set_current_user($users['a']['id']);
    $read = rest_do_request(new WP_REST_Request('GET', '/core-blueprint-notes/v1/sharing/' . $note_a));
    $must($read->get_status() === 200, 'Owner sharing state');
    $d = $read->get_data();
    $request = new WP_REST_Request('POST', '/core-blueprint-notes/v1/sharing/' . $note_a);
    $request->set_body_params([
        'mode' => 'public', 'expected_mode' => 'private',
        'expected_content_version' => $d['content_version'],
        'expected_modified_gmt' => $d['modified_gmt'], 'confirmation' => 'PUBLIC',
    ]);
    $shared = rest_do_request($request);
    $must($shared->get_status() === 200, 'Explicit Public confirmation');
    $key = (string) get_post_meta($note_a, Controller::PUBLIC_KEY, true);
    $must(preg_match('/^[a-f0-9]{32}$/D', $key) === 1, 'Synthetic share token exists');
    $data = ['a' => $users['a'], 'b' => $users['b'],
        'note_a' => $note_a, 'note_b' => $note_b, 'key' => $key];
    $json = wp_json_encode($data);
    $must(is_string($json) && file_put_contents($manifest_path, $json, LOCK_EX) !== false,
        'Write private disposable manifest');
    chmod($manifest_path, 0600);
    echo "Notes synthetic HTTP fixture: PASS (seed)\n";
    return;
}

$raw = file_get_contents($manifest_path);
$info = is_string($raw) ? json_decode($raw, true) : null;
$must(is_array($info) && !empty($info['note_a']) && !empty($info['a']['id']), 'Read private fixture manifest');
wp_set_current_user((int) $info['a']['id']);
$note_id = (int) $info['note_a'];
if ($phase === 'edit') {
    $edited = wp_update_post([
        'ID' => $note_id,
        'post_content' => '<!-- wp:paragraph --><p>NOTES_HTTP_NEW_PRIVATE_A</p><!-- /wp:paragraph -->'
    ], true);
    $must(is_int($edited) && $edited === $note_id, 'Edit private source after public snapshot');
    $must((string) get_post_meta($note_id, Controller::PUBLIC_KEY, true) === (string) $info['key'],
        'Frozen link survives normal private content edit');
    echo "Notes synthetic HTTP fixture: PASS (private edit)\n";
    return;
}
$revoke = new WP_REST_Request('POST', '/core-blueprint-notes/v1/sharing/' . $note_id);
$revoke->set_param('mode', 'private');
$response = rest_do_request($revoke);
$must($response->get_status() === 200, 'Owner revocation REST');
$must((string) get_post_meta($note_id, Controller::PUBLIC_KEY, true) === '', 'Local token cleared');
echo "Notes synthetic HTTP fixture: PASS (revoked)\n";
