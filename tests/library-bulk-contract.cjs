'use strict';

const fs = require('node:fs');
const path = require('node:path');
const base = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(base, 'src/Library/Page.php'), 'utf8');
const php = fs.readFileSync(path.join(base, 'src/Library/Bulk.php'), 'utf8');
const script = fs.readFileSync(path.join(base, 'assets/library-bulk.js'), 'utf8');
const css = fs.readFileSync(path.join(base, 'assets/library-admin.css'), 'utf8');
const plugin = fs.readFileSync(path.join(base, 'src/Plugin.php'), 'utf8');

function check(condition, description) {
    if (!condition) throw new Error('P2 contract: ' + description);
}

check(page.includes('data-cb-notes-select-id=') && page.includes('data-cb-notes-select-all'),
    'Selection limited to renderable personal note rows');
check(page.includes('data-cb-notes-bulk-toolbar hidden') &&
    page.includes('data-cb-notes-bulk-nonce'), 'JS-only bulk toolbar with scoped nonce');
check(page.includes("self::select_options($catalog['notebook']") &&
    page.includes("self::select_options($catalog['category']"), 'Owner catalog drives destination controls');
check(page.includes('value="__choose__" selected disabled') &&
    script.includes("field.value === '__choose__'") &&
    script.includes("target === '__choose__'"),
    'No implicit Unfiled/No-category bulk change without explicit destination');
check(page.includes('data-cb-notes-bulk-action="notebook"') &&
    page.includes('data-cb-notes-bulk-action="category"') &&
    page.includes('data-cb-notes-bulk-action="trash"'), 'Three explicit bulk actions');
check(page.includes('cb-core-button--danger cb-core-button--compact') &&
    page.includes('data-cb-notes-bulk-action="trash"'), 'Trash uses canonical Base danger button');
check(page.includes("'cb-notes-library-bulk'") && page.includes("'assets/library-bulk.js'"),
    'Library-only script registered');
check(plugin.includes('Bulk::init()'), 'Scoped AJAX registered from Notes boot');
check(php.includes("add_action('wp_ajax_cb_notes_bulk'") &&
    !php.includes("wp_ajax_nopriv"), 'AJAX requires authenticated WordPress user');
check(php.includes('public const MAX_IDS = 20;') &&
    php.includes('count($raw) > self::MAX_IDS'), 'Strict per-page bound enforced');
check(php.includes("current_user_can('cb_notes_edit')") &&
    php.includes('Markers::can_mark($id)') &&
    php.includes("current_user_can('delete_post', $id)"), 'Per-note owner and capability checks');
check(php.includes("Catalog::contains($owner, $operation, $target)") &&
    php.includes("Catalog::valid_id($target)"), 'No foreign or invented destination');
check(php.includes("wp_trash_post($id)") &&
    php.includes("get_post_status($id) === 'trash'"),
    'WordPress trash hooks preserve sharing revocation and persisted-status truth');
check(!php.includes('wp_delete_post('), 'No permanent deletion');
check(php.includes("'updated_ids' => $updated") &&
    php.includes("'failed_ids' => $failed"), 'Partial results are explicit');
check(script.includes("modal.show({") &&
    script.includes("confirmVariant: 'danger'") &&
    script.includes("confirmed === true"), 'Destructive batch requires Base confirmation');
check(!script.includes('window.confirm('), 'No native confirmation fallback');
check(script.includes('new Set(returnedIds)') &&
    script.includes('failedNames.join('), 'Response integrity and named partial failures');
check(script.includes("payload.append('ids[]', id)") &&
    script.includes("credentials: 'same-origin'"), 'Single credentialed request for selected IDs');
check(css.includes('.cb-notes-library__select[hidden]') &&
    css.includes('.cb-notes-library__bulk[hidden]'), 'No-JS controls stay hidden even with Base display rules');
check(css.includes('@media (max-width: 680px)') &&
    css.includes('var(--cb-surface-2)'), 'Responsive composition uses Base tokens');

console.log('PASS JS: tests/library-bulk-contract.cjs');
