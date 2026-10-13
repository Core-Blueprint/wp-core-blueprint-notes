'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const editor = fs.readFileSync(path.join(root, 'assets/editor.js'), 'utf8');
const admin = fs.readFileSync(path.join(root, 'assets/organization-admin.js'), 'utf8');
const catalog = fs.readFileSync(path.join(root, 'src/Organization/Catalog.php'), 'utf8');
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