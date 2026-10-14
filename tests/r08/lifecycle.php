<?php
// WP-CLI eval-file: intentionally no strict_types declaration.
use CB\Notes\Plugin;
use CB\Notes\Sharing\Controller;
use CB\Notes\Sharing\SecureRevocationRecovery;

if (PHP_SAPI !== 'cli' || DB_NAME !== 'core_blueprint_notes_r08_test'
    || (string)get_option('home') !== 'https://cb-notes-r08.invalid'
    || (string)getenv('CB_NOTES_R08_PHASE') !== 'lifecycle') {
    throw new RuntimeException('Notes lifecycle fixture refuses non-disposable site.');
}
$id = (int)get_option('cb_notes_r08_lifecycle_note_id', 0);
$post = $id > 0 ? get_post($id) : null;
if (!$post instanceof WP_Post || $post->post_type !== Plugin::TYPE) {
    throw new RuntimeException('R08 lifecycle: note did not survive plugin deactivation/reactivation.');
}
if (!str_contains($post->post_content, 'R08_NEW_UNSHARED_CONTENT')
    || get_post_meta($id, Controller::MODE_META, true) !== 'private'
    || get_post_meta($id, Controller::SECURE_META, true) !== ''
    || SecureRevocationRecovery::pending_count() !== 0) {
    throw new RuntimeException('R08 lifecycle: content, revocation or recovery state changed.');
}
echo "Notes R10 disposable plugin deactivation/reactivation: PASS (note retained; Secure Share remains revoked)\n";
