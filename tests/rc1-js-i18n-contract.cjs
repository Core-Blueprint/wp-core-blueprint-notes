'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

const groups = [
    { file: 'assets/editor.js', php: 'src/Plugin.php', handle: 'cb-notes-editor',
        tokens: ['Notebook', 'Category', 'Add existing tag', 'New %s name', 'Show categories and tags'] },
    { file: 'assets/sharing.js', php: 'src/Plugin.php', handle: 'cb-notes-sharing',
        tokens: ['Sharing', 'Make this note public?', 'Stop sharing', 'Save the note before making a public version.'] },
    { file: 'assets/organization-admin.js', php: 'src/Organization/Catalog.php', handle: 'cb-notes-organize',
        tokens: ['Notebooks', 'Rename %s', 'Create %s', 'Remove label', 'Loading personal labels…'] },
    { file: 'assets/library-admin.js', php: 'src/Library/Page.php', handle: 'cb-notes-library',
        tokens: ['Move note to Trash?', 'Could not update this note. Please try again.'] },
    { file: 'assets/library-bulk.js', php: 'src/Library/Page.php', handle: 'cb-notes-library-bulk',
        tokens: ['Move selected notes to Trash?', 'Not updated: %s.', 'Refresh results to see the latest Library.'] }
];
for (const group of groups) {
    const source = read(group.file);
    const registration = read(group.php);
    assert.match(source, /\bvar __ =/, group.file + ' must wire WordPress i18n');
    assert.match(source, /wp\.i18n\.__/, group.file + ' must use wp.i18n');
    assert.ok(registration.includes("'wp-i18n'"), group.handle + ' needs wp-i18n as script dependency');
    assert.ok(registration.includes("wp_set_script_translations('" + group.handle + "', 'core-blueprint-notes'"),
        group.handle + ' must register script text domain');
    assert.ok(source.includes("'core-blueprint-notes'"), group.file + ' must use suite domain');
    // Extracted msgids must be statically literal (WordPress translation tooling).
    for (const literal of group.tokens) {
        assert.ok(source.includes("__('" + literal + "', 'core-blueprint-notes')"),
            group.file + ' missing extractable literal: ' + literal);
    }
    assert.doesNotMatch(source, /\b__\s*\(\s*[a-zA-Z_$]/,
        group.file + ' cannot use dynamic msgids');
}
const editor = read('assets/editor.js');
const sharing = read('assets/sharing.js');
const bulk = read('assets/library-bulk.js');
assert.ok(sharing.includes("typedConfirm: 'PUBLIC'") && sharing.includes("requestMode('public', 'PUBLIC')"),
    'Typed PUBLIC acknowledgment must remain an exact, untranslated protocol sentinel');
assert.match(editor, /sprintf\(__\('New %s name', 'core-blueprint-notes'\)/,
    'Dynamic Gutenberg labels must use a positional translation placeholder');
assert.match(bulk, /_n\('%d selected', '%d selected', count, 'core-blueprint-notes'\)/,
    'Bulk selected count must use gettext plural API');
assert.ok(bulk.includes("'__choose__'"), 'Bulk destination sentinel must remain untranslated');
console.log('PASS JS: tests/rc1-js-i18n-contract.cjs (static extraction, no installed language catalogs asserted)');
