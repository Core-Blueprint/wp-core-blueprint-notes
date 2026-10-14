<?php
declare(strict_types=1);

use CB\Notes\Access;
use CB\Notes\Organization\Catalog;
use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;
use CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy;
use CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry;

/**
 * Runs only inside tools/check-restore, on two synthetic SQL databases.
 * The full SQL dump transfers this manifest as an option, including stable IDs.
 */
$phase = getenv('CB_RESTORE_PHASE');
$expected_db = $phase === 'seed'
    ? 'core_blueprint_notes_restore_source'
    : 'core_blueprint_notes_restore_target';
if (!in_array($phase, ['seed', 'verify'], true) || DB_NAME !== $expected_db) {
    throw new RuntimeException('Notes restore fixture requires the correct disposable database and phase.');
}
if (!defined('CB_NOTES_VERSION') || CB_NOTES_VERSION !== '0.1.0-alpha24') {
    throw new RuntimeException('Restore requires installed Alpha24 Notes.');
}
if (!defined('CB_CORE_API_VERSION') || (string) CB_CORE_API_VERSION !== '1.2') {
    throw new RuntimeException('Restore requires Base API 1.2.');
}
$checks = 0;
$must = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Notes restore FAIL: ' . $message);
    }
};

if ($phase === 'seed') {
    $admin = get_user_by('login', 'cbnotes_restore_admin');
    $must($admin instanceof WP_User, 'Source admin exists');
    foreach (['a', 'b'] as $slug) {
        $login = 'cbnotes_restore_editor_' . $slug;
        $id = wp_create_user($login, 'disposable-notes-test-password-' . $slug, $slug . '@cb-notes-restore.invalid');
        $must(is_int($id) && $id > 0, 'Create synthetic editor ' . $slug);
        (new WP_User($id))->set_role('editor');
    }
    $a = get_user_by('login', 'cbnotes_restore_editor_a');
    $b = get_user_by('login', 'cbnotes_restore_editor_b');
    $must($a instanceof WP_User && $b instanceof WP_User && $a->ID !== $b->ID, 'Distinct editor identities');

    $create_label = static function (int $owner, string $kind, string $name) use ($must): string {
        wp_set_current_user($owner);
        $req = new WP_REST_Request('POST', '/core-blueprint-notes/v1/organization');
        $req->set_body_params(['kind' => $kind, 'action' => 'create', 'name' => $name]);
        $r = rest_do_request($req);
        $must($r->get_status() === 200, 'Create source ' . $kind);
        $data = $r->get_data();
        $must(is_array($data) && count($data[$kind] ?? []) >= 1, 'Catalog item persisted');
        $id = (string) end($data[$kind])['id'];
        $must(Catalog::valid_id($id), 'Opaque catalog identifier');
        return $id;
    };

    $an = $create_label($a->ID, 'notebook', 'Company knowledge');
    $ac = $create_label($a->ID, 'category', 'Governance');
    $at = $create_label($a->ID, 'tag', 'Recovery');
    $bn = $create_label($b->ID, 'notebook', 'Company knowledge');
    $must($an !== $bn, 'Same display name has distinct owner IDs');
    wp_set_current_user($a->ID);
    $content = '<!-- wp:paragraph --><p>DISPOSABLE A private knowledge fixture</p><!-- /wp:paragraph -->';
    $mine = wp_insert_post([
        'post_type' => Plugin::TYPE,
        'post_author' => $a->ID,
        'post_status' => 'private',
        'post_title' => 'Synthetic continuity memo',
        'post_content' => $content,
    ], true);
    $must(is_int($mine) && $mine > 0, 'Create source A note');
    $assign = new WP_REST_Request('POST', '/wp/v2/cb-notes/' . $mine);
    $assign->set_param('meta', [
        Catalog::NOTEBOOK => $an,
        Catalog::CATEGORY => $ac,
        Catalog::TAGS => [$at],
    ]);
    $must(rest_do_request($assign)->get_status() === 200, 'Owner Gutenberg-style REST assignment');
    $must(get_post_meta($mine, Catalog::NOTEBOOK, true) === $an, 'Notebook assignment persists');
    $must(get_post_meta($mine, Catalog::CATEGORY, true) === $ac, 'Category assignment persists');
    $must(get_post_meta($mine, Catalog::TAGS, true) === [$at], 'Tag assignment persists');
    update_post_meta($mine, '_cb_notes_favorite', '1');
    update_post_meta($mine, '_cb_notes_pinned', '1');
    update_user_meta($a->ID, 'cb_notes_library_display', 'table');
    $revision = wp_save_post_revision($mine);
    $must(is_int($revision) && $revision > 0, 'Create real native WordPress revision');

    $sharing_path = '/core-blueprint-notes/v1/sharing/' . $mine;
    $read = rest_do_request(new WP_REST_Request('GET', $sharing_path));
    $must($read->get_status() === 200, 'Owner can read sharing settings');
    $snapshot = $read->get_data();
    $share_req = new WP_REST_Request('POST', $sharing_path);
    $share_req->set_body_params([
        'mode' => 'public',
        'expected_mode' => 'private',
        'expected_content_version' => $snapshot['content_version'],
        'expected_modified_gmt' => $snapshot['modified_gmt'],
        'confirmation' => 'PUBLIC',
    ]);
    $must(rest_do_request($share_req)->get_status() === 200, 'Create synthetic public frozen snapshot');
    $must((string) get_post_meta($mine, Controller::PUBLIC_KEY, true) !== '', 'Public test key exists before revocation');
    $revoke_req = new WP_REST_Request('POST', $sharing_path);
    $revoke_req->set_param('mode', 'private');
    $must(rest_do_request($revoke_req)->get_status() === 200, 'Revoke synthetic public grant');
    $must((string) get_post_meta($mine, Controller::PUBLIC_KEY, true) === '', 'No live public key in the backup');

    $trashed = wp_insert_post([
        'post_type' => Plugin::TYPE,
        'post_author' => $a->ID,
        'post_status' => 'private',
        'post_title' => 'Synthetic trashed note',
    ], true);
    $must(is_int($trashed) && $trashed > 0, 'Create synthetic trashed note');
    $must(wp_trash_post($trashed) instanceof WP_Post, 'Trash source note');

    wp_set_current_user($b->ID);
    $other = wp_insert_post([
        'post_type' => Plugin::TYPE,
        'post_author' => $b->ID,
        'post_status' => 'private',
        'post_title' => 'Synthetic B private note',
        'post_content' => '<!-- wp:paragraph --><p>DISPOSABLE B secret</p><!-- /wp:paragraph -->',
    ], true);
    $must(is_int($other) && $other > 0, 'Create source B note');
    update_post_meta($other, Catalog::NOTEBOOK, $bn);

    // Server-side synthetic approval on the source only. A different site's
    // salts must invalidate this signed privilege fingerprint after restore.
    $must(PrivilegedAccessPolicy::is_privileged($admin), 'Administrator has privileged status');
    $must(PrivilegedAccessRegistry::approve($admin, 0, 'notes_restore_fixture'), 'Sign source test administrator');
    $must(PrivilegedAccessRegistry::is_approved($admin), 'Source test admin is approved');

    update_option('cb_notes_restore_fixture', [
        'a' => $a->ID, 'b' => $b->ID, 'admin' => $admin->ID,
        'note_a' => $mine, 'note_b' => $other, 'trashed' => $trashed,
        'notebook_a' => $an, 'category_a' => $ac, 'tag_a' => $at,
        'notebook_b' => $bn,
        'content_hash_a' => hash('sha256', $content),
    ], false);
    $must(is_array(get_option('cb_notes_restore_fixture')), 'Manifest stored in SQL backup');
    echo "Notes restore source fixture: PASS ({$checks} checks)\n";
    return;
}

$manifest = get_option('cb_notes_restore_fixture', null);
$must(is_array($manifest), 'Full SQL backup transferred manifest');
$a = get_user_by('login', 'cbnotes_restore_editor_a');
$b = get_user_by('login', 'cbnotes_restore_editor_b');
$admin = get_user_by('login', 'cbnotes_restore_admin');
$must($a instanceof WP_User && $b instanceof WP_User && $admin instanceof WP_User, 'All original users restored');
$must((int) $manifest['a'] === $a->ID && (int) $manifest['b'] === $b->ID, 'User identities retained');
$must((int) $manifest['admin'] === $admin->ID, 'Admin identity retained');
$mine = (int) $manifest['note_a'];
$other = (int) $manifest['note_b'];
$trashed = (int) $manifest['trashed'];
$note_a = get_post($mine);
$note_b = get_post($other);
$must($note_a instanceof WP_Post && $note_b instanceof WP_Post, 'Original note IDs and records restored');
$must($note_a->post_type === Plugin::TYPE && $note_b->post_type === Plugin::TYPE, 'Notes CPT survives import');
$must($note_a->post_status === 'private' && $note_b->post_status === 'private', 'Private status retained');
$must((int) $note_a->post_author === $a->ID && (int) $note_b->post_author === $b->ID, 'Owner mapping remains correct');
$must(hash('sha256', $note_a->post_content) === $manifest['content_hash_a'], 'Exact Gutenberg blocks preserved');
$must(count(wp_get_post_revisions($mine)) >= 1, 'At least one revision restored');

wp_set_current_user($a->ID);
$expected = ['notebook' => 'Company knowledge', 'category' => 'Governance', 'tags' => ['Recovery']];
$must(Catalog::labels_for_post($mine) === $expected, 'Per-user catalog names and references restored');
$must(get_post_meta($mine, Catalog::NOTEBOOK, true) === $manifest['notebook_a'], 'Opaque notebook ID unchanged');
$must(get_post_meta($mine, Catalog::CATEGORY, true) === $manifest['category_a'], 'Opaque category ID unchanged');
$must(get_post_meta($mine, Catalog::TAGS, true) === [$manifest['tag_a']], 'Opaque tag ID unchanged');
$must(get_post_meta($mine, '_cb_notes_favorite', true) === '1', 'Favorite marker restored');
$must(get_post_meta($mine, '_cb_notes_pinned', true) === '1', 'Pinned marker restored');
$must(get_user_meta($a->ID, 'cb_notes_library_display', true) === 'table', 'Table preference restored');
$must(Access::can_read($note_a), 'Owner A can still read own note');
$must(!Access::can_read($note_b), 'Owner A cannot read B note');
$must(rest_do_request(new WP_REST_Request('GET', '/wp/v2/cb-notes/' . $mine))->get_status() === 200,
    'Owner REST read succeeds after restore');

wp_set_current_user($b->ID);
$must(!Access::can_read($note_a), 'Owner B cannot read A note');
$must(Catalog::labels_for_post($mine) === ['notebook' => '', 'category' => '', 'tags' => []],
    'Owner B cannot resolve A private labels');
$must(rest_do_request(new WP_REST_Request('GET', '/wp/v2/cb-notes/' . $mine))->get_status() >= 400,
    'B REST must reject A private note');
$must(get_post_meta($other, Catalog::NOTEBOOK, true) === $manifest['notebook_b'], 'B independent opaque ID restored');
$must($manifest['notebook_a'] !== $manifest['notebook_b'], 'Same-name notebooks remain isolated');
wp_set_current_user(0);
$must(rest_do_request(new WP_REST_Request('GET', '/wp/v2/cb-notes/' . $mine))->get_status() >= 400,
    'Anonymous REST cannot read A note');

$must((string) get_post_meta($mine, Controller::PUBLIC_KEY, true) === '', 'Previously revoked public key remains revoked');
$must(!Controller::public_snapshot_allowed($note_a, '00000000000000000000000000000000',
    get_post_meta($mine, Controller::PUBLIC_META, true)), 'No public delivery after restore');
$must(get_post_status($trashed) === 'trash', 'Trashed note state preserved in restore');
$must(!is_post_publicly_viewable($note_a), 'Restored Notes remain non-public');

$must(!PrivilegedAccessRegistry::is_approved($admin),
    'Source-signed admin approval is invalid in target trust domain');
wp_set_current_user($admin->ID);
$must(!current_user_can('cb_notes_manage_all'),
    'Unapproved imported administrator cannot bypass owner boundary');
echo "Notes full SQL target verification: PASS ({$checks} checks)\n";
