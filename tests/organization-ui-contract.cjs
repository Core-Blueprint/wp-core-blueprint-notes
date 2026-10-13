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
    ['Editor updates native WordPress post meta', editor.includes('dispatch.editPost({ meta:')],
    ['Organization supports create rename delete', admin.includes("mutate('create'") && admin.includes("mutate('rename'") && admin.includes("mutate('delete'")],
    ['Organization removal uses Base danger modal', admin.includes("confirmVariant: 'danger'") && admin.includes('confirmCheck: {')],
    ['Organize registers with Base AdminTheme', catalog.includes('AdminTheme::register_screen(self::$admin_hook')],
    ['Base nav tabs, notices and form components requested', catalog.includes("'nav-tabs', 'notices'") && catalog.includes("'form-controls'")],
    ['Base screen scope and theme-aware CSS loaded', catalog.includes('cb-core-wrap cb-core-page cb-core-form-scope cb-notes-organize-page') && catalog.includes('assets/organization-admin.css')],
    ['Three visible, accessible native links replace dropdown', admin.includes("className: 'nav-tab'") && admin.includes("'aria-current': active ? 'page'") && admin.includes('aria-label') && !admin.includes('KindPicker')],
    ['Tab URLs preserve WordPress admin query parameters', admin.includes('new URL(window.location.href)') && admin.includes("url.searchParams.set('section', section)") && admin.includes('sectionFromUrl')],
    ['Direct tab navigation updates content without page reload', admin.includes('setKind(next)') && admin.includes('window.history.replaceState')],
    ['Contextual Add new form is hidden by default', admin.includes('if (adding) {') && admin.includes("adding ? 'Cancel' : 'Add new'")],
    ['Rename remains inline and accessible', admin.includes("label: 'Rename ' + item.name") && admin.includes("setEditing(item.id)")],
    ['Canceling removal cannot mutate', admin.includes("if (accepted === true) mutate('delete'")],
    ['Full page replaced with one Base card', admin.includes('cb-core-card__body') && !admin.includes('cb-notes-organize__card--picker')],
    ['Success feedback is compact and screenreader-accessible', admin.includes('cb-notes-organize__feedback') && admin.includes("'aria-live': 'polite'") && !admin.includes("status: 'success'")],
    ['Error uses semantic Base notice', admin.includes('cb-core-notice cb-core-notice--error') && admin.includes("role: 'alert'")],
    ['Base notice renders icon and content slots', admin.includes('cb-core-notice__icon') && admin.includes('cb-core-notice__content')],
    ['Tab navigation respects browser modifier keys', admin.includes('event.ctrlKey || event.metaKey || event.shiftKey')],
    ['Loading indicator ends when REST fails', admin.includes('if (!catalog && !error) {')],
    ['Token layout is bounded, responsive and no picker overflow exception remains', layout.includes('max-width: 960px') && layout.includes('gap: var(--cb-space-4)') && layout.includes('@media (max-width: 600px)') && !layout.includes('overflow: visible;')],
    ['No custom colors or per-element style overrides', !admin.includes('style: {') && !layout.includes('#7774') && !layout.includes('!important')],
    ['Organize never falls back if Base modal absent', admin.includes('The Core Blueprint confirmation is unavailable.')],
    ['Per-owner classification storage remains unchanged', catalog.includes('get_user_meta($user_id, self::USER_META, true)')],
    ['Organization REST requires Notes edit capability', catalog.includes("current_user_can('cb_notes_edit')")],
    ['Library filters require owner catalog', catalog.includes('self::catalog(get_current_user_id())')],
    ['Gutenberg post meta checked against owner catalog', catalog.includes('Unknown personal organization item.')],
    ['No site-wide WP taxonomy registered', !catalog.includes('register_taxonomy(')],
    ['Shared snapshots never include Notes organization metadata', !snapshot.includes('cb_notes_organization') && !sharing.includes('organizationPath')]
];
checks.forEach(([label, pass]) => {
    assert.equal(pass, true, label);
    process.stdout.write('PASS: ' + label + '\n');
});
process.stdout.write('Notes alpha4 tabbed organization UI contract: PASS\n');