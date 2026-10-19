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
    'One per-user List/Table/Cards control on the existing Library page');
check(page.includes("'icon' => 'dashicons-list-view'") &&
    page.includes("'icon' => 'dashicons-editor-table'") &&
    page.includes('cb-core-button--compact cb-core-button--icon-only cb-core-button--') &&
    page.includes("$variant = $active ? 'primary' : 'secondary'") &&
    page.includes("' aria-label=\"' . esc_attr($display['name'])") &&
    page.includes("' title=\"' . esc_attr($display['name'])") &&
    page.includes('aria-current="page"') &&
    page.includes('aria-hidden="true"'),
    'List/Table use Base icon-only toggle with localized tooltip, accessible name and current state');
check(css.includes('.cb-notes-library__display-toggle .cb-core-button--icon-only > .dashicons {') &&
    css.includes('align-items: center;') &&
    css.includes('justify-content: center;'),
    'View toggle icons are centered within the Base square hit targets');
check(page.includes('render_table($query->posts, $owner, $state)') &&
    page.includes('self::render_note($post, $owner)') &&
    page.includes('new \\WP_Query(Query::args($owner, $state))'),
    'All display modes use exactly the same owner-bound WordPress query');
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
check(page.includes("$title_next = $state['sort'] === 'title' ? 'title_desc' : 'title'") &&
    page.includes("$modified_next = $state['sort'] === 'newest' ? 'oldest' : 'newest'") &&
    page.includes("['sort' => $title_next, 'paged' => 1]") &&
    page.includes("['sort' => $modified_next, 'paged' => 1]"),
    'Title and Modified header clicks reverse ASC/DESC and reset to page one');
check(page.includes("$title_direction = $state['sort'] === 'title' ? 'ascending' : 'descending'") &&
    page.includes("$modified_direction = $state['sort'] === 'oldest' ? 'ascending' : 'descending'") &&
    page.includes(' aria-sort="' ) &&
    page.includes("$modified_active ?"),
    'aria-sort advertises only an actual column direction');
check(page.includes("$expanded = $state['category'] !== '' || $state['tag'] !== '';") &&
    !page.includes("$state['sort'] !== 'modified'"),
    'Header sorting does not automatically expand More filters');
check(query.includes("'title_desc' => ['title' => 'DESC'") &&
    query.includes("'newest' => ['modified' => 'DESC'") &&
    query.includes("'title' => ['title' => 'ASC'") &&
    query.includes("'oldest' => ['modified' => 'ASC'") &&
    query.includes("'cb_notes_pinned_first' => $state['sort'] === 'modified'"),
    'Query supports four deterministic explicit sort orders and the pinned default');
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
    css.includes('min-width: 940px;') &&
    css.includes('var(--cb-border)'),
    'Responsive, contained, token-based Table View');
check(page.includes('<span class="cb-notes-library__sort-label">') &&
    css.includes('.cb-notes-library__table thead th a .cb-notes-library__sort-label {') &&
    css.includes('text-decoration: underline;') &&
    css.includes('.cb-core-wrap .cb-notes-library__table thead th a {') &&
    css.includes('text-decoration: none;') &&
    css.includes('display: inline-block;'),
    'Only the sortable heading label receives underline, never its ASC/DESC indicator');
check(css.includes('th[aria-sort="ascending"] a::after') &&
    css.includes('th[aria-sort="descending"] a::after'),
    'Active Title/Modified sort direction is visible without new theme colors');
check(css.includes('.cb-notes-library__display-link.is-active') &&
    css.includes('.cb-notes-library__display-link:focus-visible'),
    'Keyboard-visible selected view toggle with accessible current-page state');
check(css.includes('.cb-notes-library__display-toggle .button.cb-core-button.cb-notes-library__display-link {') &&
    css.includes('.cb-notes-library__display-link.is-active {') &&
    css.includes('border-width: 1px;') &&
    css.includes('box-shadow: none !important;'),
    'List/Table has a strict 1px border without the Base primary glow or WP focus shadow');
const displayFocus = css.slice(
    css.indexOf('/* Mouse focus must not retain'),
    css.indexOf('/* Table is optional.')
);
check(displayFocus.includes('.cb-notes-library__display-link:focus {') &&
    displayFocus.includes('.cb-notes-library__display-link:focus-visible {') &&
    displayFocus.includes('outline: 1px solid var(--cb-interactive-focus, var(--cb-accent)) !important;') &&
    !displayFocus.includes('outline: 2px') &&
    displayFocus.includes('box-shadow: none !important;'),
    'Focus only retains a 1px accessible keyboard outline and no thick hover/focus halo');
check(!/#[0-9a-fA-F]{3,8}\b/.test(css), 'No custom hardcoded color palette');
console.log('PASS JS: tests/library-table-contract.cjs');
