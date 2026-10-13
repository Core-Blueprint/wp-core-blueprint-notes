'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'src/Library/Page.php'), 'utf8');
const query = fs.readFileSync(path.join(root, 'src/Library/Query.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/library-admin.css'), 'utf8');
const display = fs.readFileSync(path.join(root, 'src/Library/Display.php'), 'utf8');
const bulk = fs.readFileSync(path.join(root, 'assets/library-bulk.js'), 'utf8');
const marker = fs.readFileSync(path.join(root, 'assets/library-admin.js'), 'utf8');

function check(condition, name) {
    if (!condition) throw new Error('Library Table View: ' + name);
}
check(page.includes('Display::init()') &&
    page.includes('Display::current($owner)') &&
    page.includes('Display::switch_url($mode, $state)'),
    'One per-user List/Table control on the existing Library page');
check(page.includes('render_table($query->posts, $owner, $state)') &&
    page.includes('self::render_note($post, $owner)') &&
    page.includes('new \\WP_Query(Query::args($owner, $state))'),
    'Both modes use exactly the same owner-bound WordPress query');
check((page.match(/new \\WP_Query/g) || []).length === 1,
    'Table does not introduce a second WP_Query or separate pagination');
check(page.includes('private static function render_table(') &&
    page.includes('private static function render_table_row(') &&
    page.includes('<table class="cb-notes-library__table">') &&
    page.includes('scope="row"') && page.includes('scope="col"'),
    'Semantic table and row headings');
check(page.includes("esc_html__('Title'") &&
    page.includes("esc_html__('Notebook'") &&
    page.includes("esc_html__('Category'") &&
    page.includes("esc_html__('Tags'") &&
    page.includes("esc_html__('Modified'") &&
    page.includes("esc_html__('Actions'"),
    'All six requested columns');
check(page.includes("['sort' => 'title', 'paged' => 1]") &&
    page.includes("['sort' => $modified_sort, 'paged' => 1]") &&
    page.includes('aria-sort="ascending"') &&
    page.includes("$modified_dir = $state['sort'] === 'oldest' ? 'ascending' : 'descending'"),
    'Title and Modified sorted with the existing supported query options');
const tableRow = page.slice(
    page.indexOf('private static function render_table_row('),
    page.indexOf('private static function render_empty_state(')
);
check(tableRow.includes('(int)$post->post_author !== $owner') &&
    tableRow.includes('Access::can_read($post)') &&
    tableRow.indexOf('Access::can_read($post)') < tableRow.indexOf('Catalog::labels_for_post('),
    'No table label or note title resolved before owner and object read authorization');
check(tableRow.includes('esc_html($title)') &&
    tableRow.includes("esc_html($labels['category'])") &&
    tableRow.includes('esc_attr($extra_title)') &&
    tableRow.includes('esc_html($tag)'),
    'Per-user labels, overflow titles and links are escaped');
check(page.includes('private static function render_actions(') &&
    (page.match(/self::render_actions\(\$post, \$title, \$link\);/g) || []).length === 2,
    'List and Table share precisely one source of Favorite/Pin/Edit/Trash controls');
check(tableRow.includes('data-cb-notes-select-id=') &&
    bulk.includes("document.querySelectorAll('[data-cb-notes-select-id]')") &&
    marker.includes("document.querySelectorAll('[data-cb-notes-marker]')"),
    'Existing selection/markers work in either mode without selector changes');
check(!query.includes('cbn_layout') &&
    !query.includes('library_display'),
    'Presentation never changes personal Library query/security contract');
check(display.includes("add_action('admin_post_' . self::ACTION") &&
    display.includes('check_admin_referer(') &&
    display.includes("current_user_can('cb_notes_edit')") &&
    display.includes('update_user_meta($owner, self::META, $layout)') &&
    display.includes('wp_safe_redirect(Page::url($state))'),
    'Preference stored with capability/CSRF gate and bounded canonical redirect');
check(css.includes('.cb-notes-library__table-scroll {') &&
    css.includes('overflow-x: auto;') &&
    css.includes('.cb-notes-library__table {') &&
    css.includes('min-width: 740px;') &&
    css.includes('var(--cb-border)'),
    'Responsive, contained, token-based Table View');
check(css.includes('.cb-notes-library__display-link.is-active') &&
    css.includes('.cb-notes-library__display-link:focus-visible'),
    'Keyboard-visible selected view toggle with accessible current-page state');
check(!/#[0-9a-fA-F]{3,8}\b/.test(css), 'No custom hardcoded color palette');
console.log('PASS JS: tests/library-table-contract.cjs');
