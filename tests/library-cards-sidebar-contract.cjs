'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const page = read('src/Library/Page.php');
const display = read('src/Library/Display.php');
const css = read('assets/library-admin.css');
const query = read('src/Library/Query.php');
const bulk = read('assets/library-bulk.js');
const listStart = page.indexOf('private static function render_note(');
const tableStart = page.indexOf('private static function render_table(');
const renderStart = page.indexOf('public static function render(): void');
const render = page.slice(renderStart);
assert.ok(listStart > 0 && tableStart > listStart, 'Existing list and table renderers retained');
assert.ok(render.includes("'cards' => [") &&
    render.includes("__('Cards view', 'core-blueprint-notes')") &&
    render.includes("'icon' => 'dashicons-grid-view'"),
    'Cards appears as localized third Base icon-only view');
assert.ok(display.includes("['list', 'table', 'cards']") &&
    display.includes('update_user_meta($owner, self::META, $layout)'),
    'Three valid modes use one nonce-protected personal preference');
assert.ok(render.includes("$layout === 'cards' ? ' cb-notes-library__notes--cards' : ''") &&
    render.includes('self::render_note($post, $owner)') &&
    (page.match(/new \\WP_Query/g) || []).length === 1,
    'Cards reuse exact one owner query and authorized list renderer');
assert.ok(page.slice(listStart, tableStart).includes('Access::can_read($post)') &&
    page.slice(listStart, tableStart).includes('Catalog::labels_for_post((int)$post->ID)') &&
    page.slice(listStart, tableStart).includes('self::render_actions($post, $title, $link)'),
    'Card metadata, links, favorites/pin and trash use existing owner gate');
assert.ok(css.includes('.cb-notes-library__notes--cards {') &&
    css.includes('grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));') &&
    css.includes('var(--cb-space-4)'),
    'Cards use responsive token-based grid');
assert.ok(!render.includes('post_content') && !render.includes('the_content('),
    'Cards do not introduce note-body previews');
assert.ok(display.includes("private const SIDEBAR_META = 'cb_notes_library_sidebar'") &&
    display.includes('sidebar_url(string $position, array $state)') &&
    display.includes('check_admin_referer(self::ACTION . \'_sidebar_\' . $position)') &&
    display.includes('update_user_meta($owner, self::SIDEBAR_META, $position)') &&
    display.includes('$sidebar_requested === $layout_requested'),
    'Sidebar preference uses owner-only, CSRF-safe existing action without ambiguous dual writes');
assert.ok(render.includes("Display::sidebar($owner)") &&
    render.includes('data-cb-notes-sidebar=') &&
    render.includes('cb-notes-library__sidebar-open') &&
    render.includes('cb-notes-library__sidebar-close') &&
    render.includes('cb-notes-library__compact-nav') &&
    render.includes('Display::sidebar_url($target, $state)') &&
    render.includes('dashicons-arrow-down-alt2') &&
    css.includes('.cb-notes-library__compact-context {'),
    'Sidebar show/hide stays server-rendered and linked to current filter state');
assert.ok(css.includes('.cb-notes-library[data-cb-notes-sidebar="collapsed"] .cb-notes-library__sidebar {') &&
    css.includes('display: none;') &&
    css.includes('[data-cb-notes-sidebar="auto"]') &&
    css.includes('@media (max-width: 1000px)'),
    'Sidebar really disappears and auto mode defaults closed on narrow screens');
assert.ok(render.includes("esc_html($context_label)") &&
    render.includes("self::view_link(__('Favorites'") &&
    render.includes("self::view_link(__('Pinned'") &&
    render.includes("self::nav_link(__('Unfiled'") &&
    render.includes("esc_url($organize_url)"),
    'Hidden sidebar retains safe compact owner-only navigation');
assert.ok(!query.includes('cbn_layout') && !query.includes('library_display') &&
    bulk.includes("document.querySelectorAll('[data-cb-notes-select-id]')"),
    'No query/data/REST changes or bulk selection forks');
assert.doesNotMatch(css, /#[0-9a-fA-F]{3,8}\b/, 'No local color palette');
console.log('Notes Library Cards and collapsible sidebar: SOURCE PASS (browser acceptance pending)');
