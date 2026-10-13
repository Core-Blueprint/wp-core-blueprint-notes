'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const editor = fs.readFileSync(path.join(root, 'assets/editor.js'), 'utf8');
const admin = fs.readFileSync(path.join(root, 'assets/organization-admin.js'), 'utf8');
const catalog = fs.readFileSync(path.join(root, 'src/Organization/Catalog.php'), 'utf8');
const layout = fs.readFileSync(path.join(root, 'assets/organization-admin.css'), 'utf8');
const sharing = fs.readFileSync(path.join(root, 'assets/sharing.js'), 'utf8');
const snapshot = fs.readFileSync(path.join(root, 'src/Sharing/Snapshot.php'), 'utf8');

const checks = [
    ['Gutenberg exposes Notebook, Category and Tags', editor.includes("['notebook', 'category', 'tag'].forEach(")],
    ['Notebook input uses Name not duplicated pane label', editor.includes("kind === 'notebook' ? 'Name' : 'Type'")],
    ['Gutenberg uses owner-only organization endpoint', editor.includes("'/core-blueprint-notes/v1/organization'")],
    ['Tags selector enforces max 12', editor.includes('selected.length < 12')],
    ['Editor updates native WordPress post meta', editor.includes("dispatch.editPost({ meta:")],
    ['Organization management supports create rename delete', admin.includes("mutate('create'") && admin.includes("mutate('rename'") && admin.includes("mutate('delete'")],
    ['Organization removal uses Base danger modal', admin.includes("confirmVariant: 'danger'") && admin.includes('confirmCheck: {')],
    ['Organize screen declares Base AdminTheme compatibility', catalog.includes('AdminTheme::register_screen(self::$admin_hook')],
    ['Semantic Base picker and components requested', catalog.includes("'foundations' => ['select-picker']") && catalog.includes("'components' => ['buttons', 'cards', 'fields', 'form-controls', 'panels']")],
    ['Base layout scope and theme-aware CSS enqueued', catalog.includes('cb-core-wrap cb-core-page cb-core-form-scope cb-notes-organize-page') && catalog.includes("assets/organization-admin.css")],
    ['Native select is isolated from React reconciliation', admin.includes('function KindPicker(props)') && admin.includes('useRef(null)') && admin.includes('container.appendChild(nativeSelect)')],
    ['Base Select Picker enhancement enabled', admin.includes('nativeSelect.dataset.cbCoreSelectPicker') && admin.includes('window.cbCore.selectPicker.enhance(nativeSelect)')],
    ['Select picker retains a native accessible fallback', admin.includes("nativeSelect.id = 'cb-notes-organize-kind'") && admin.includes("htmlFor: 'cb-notes-organize-kind'")],
    ['Semantic Base cards and button variants used', admin.includes('cb-core-card__body') && admin.includes('cb-core-button--primary') && admin.includes('cb-core-button--danger')],
    ['Create action has token spacing', layout.includes('.cb-notes-organize__create') && layout.includes('gap: var(--cb-space-4)')],
    ['Organize layout is bounded and responsive', layout.includes('max-width: 960px') && layout.includes('max-width: 520px') && layout.includes('@media (max-width: 600px)')],
    ['No hardcoded colors or inline geometry remain', !admin.includes("style: {") && !layout.includes('#7774') && !layout.includes('!important')],
    ['Organization never falls back if Base modal absent', admin.includes('The Core Blueprint confirmation is unavailable.')],
    ['Persistent catalog uses per-owner user meta', catalog.includes("get_user_meta($user_id, self::USER_META, true)")],
    ['Organization REST requires Notes edit capability', catalog.includes("current_user_can('cb_notes_edit')")],
    ['Library filters require owner catalog', catalog.includes('self::catalog(get_current_user_id())')],
    ['Post meta validated against owner catalog', catalog.includes('Unknown personal organization item.')],
    ['No site-wide WP taxonomy is registered', !catalog.includes('register_taxonomy(')],
    ['Public sharing does not include organization metadata', !snapshot.includes('cb_notes_organization') && !sharing.includes('organizationPath')],
];
checks.forEach(([label, pass]) => {
    assert.equal(pass, true, label);
    process.stdout.write('PASS: ' + label + '\n');
});
process.stdout.write('Notes alpha4 organization UI contract: PASS\n');