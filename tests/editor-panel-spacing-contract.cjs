'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const sharing = read('assets/sharing.js');
const editor = read('assets/editor.js');
const plugin = read('src/Plugin.php');
const css = read('assets/editor-panels.css');

assert.ok(sharing.includes("className: 'cb-notes-sharing-panel'"),
    'Sharing panel must have a stable, Notes-owned layout scope');
assert.ok(editor.includes("className: 'cb-notes-editor-organization'"),
    'Organize panel retains its existing scoped layout marker');
assert.ok(plugin.includes("wp_enqueue_style('cb-notes-editor-panels'") &&
    plugin.includes("plugins_url('assets/editor-panels.css',CB_NOTES_FILE)"),
    'Scoped Gutenberg layout stylesheet must be enqueued from the Notes editor assets hook');

for (const scope of [
    '.cb-notes-sharing-panel .components-notice',
    '.cb-notes-editor-organization .components-notice',
]) {
    assert.ok(css.includes(scope), scope + ' must share the Notes notice spacing rule');
}
assert.match(css, /margin-block-end:\s*var\(--cb-space-4\);/,
    'Notification/action separation must use canonical Base 16px spacing token');
assert.doesNotMatch(css, /(?:\d+(?:\.\d+)?(?:px|rem|em)|#[0-9a-fA-F]{3,8}|!important)/,
    'Notes Gutenberg polish must not define a hardcoded spacing, colour or override');
assert.doesNotMatch(css, /(?:\bselect\b|\boption\b|color-scheme|background-color|border-color)/,
    'Native Gutenberg select presentation and theme controls remain Base-owned');
assert.ok(sharing.includes('disabled: blocked || !sharing.public_ready') &&
    sharing.includes("key: 'stop'") &&
    sharing.includes('disabled: busy'),
    'CSS-only notification spacing must preserve Public refusal and emergency revocation');

console.log('Notes Gutenberg panel spacing: PASS (Base --cb-space-4, Sharing/Organize, no theme duplication)');
