'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'src/Library/Page.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/library-admin.css'), 'utf8');
const catalog = fs.readFileSync(path.join(root, 'src/Organization/Catalog.php'), 'utf8');
const query = fs.readFileSync(path.join(root, 'src/Library/Query.php'), 'utf8');

function check(ok, label) {
    if (!ok) throw new Error('Notes Library overview: ' + label);
}
const start = page.indexOf('private static function render_note(');
const end = page.indexOf('private static function render_empty_state(', start);
check(start >= 0 && end > start, 'Personal note renderer retained');
const note = page.slice(start, end);
check(note.includes('(int)$post->post_author !== $owner') &&
    note.includes('Access::can_read($post)') &&
    note.includes('Catalog::labels_for_post((int)$post->ID)'),
    'Classifications only resolved after per-note owner and read checks');
check(note.includes('cb-notes-library__modified') &&
    note.includes('cb-notes-library__classification') &&
    note.includes('cb-notes-library__meta-kind'),
    'Timestamp separated from readable classification rail');
check(note.includes("esc_html__('Notebook:'") &&
    note.includes("esc_html__('Category:'") &&
    note.includes("esc_html__('Tags:'") &&
    note.includes("__('Unfiled',"),
    'Labels and unfiled fallback are localized');
check(note.includes("array_slice($labels['tags'], 0, 3)") &&
    note.includes("array_slice($labels['tags'], 3)") &&
    note.includes('esc_html((string)count($extra))'),
    'Three tags maximum plus accurate overflow count');
check(note.includes("__('Additional tags: %s'") &&
    note.includes('esc_attr($extra_title)'),
    'Overflow tag names are escaped for the accessible tooltip');
check(note.includes('esc_html($labels[\'notebook\']') &&
    note.includes("esc_html($labels['category'])") &&
    note.includes('esc_html($tag)'),
    'Personal catalog names rendered as safe text');
check(catalog.includes('public static function labels_for_post(int $post_id)') &&
    catalog.includes('Access::can_read($post)') &&
    catalog.includes('self::catalog((int)$post->post_author)'),
    'Metadata comes from authorized post-owner catalog, not global taxonomy');
check(query.includes("'author' => $owner > 0 ? $owner : -1"),
    'Owner-only Library query unchanged');
check(css.includes('.cb-notes-library__classification {') &&
    css.includes('flex-wrap: wrap;') &&
    css.includes('var(--cb-space-2, 8px)') &&
    css.includes('.cb-notes-library__tag-more {'),
    'Compact, wrapping Base-token metadata rail');
check(css.includes('.cb-notes-library__results-heading .cb-notes-library__results-title h2 {') &&
    /\.cb-notes-library__results-heading\s+\.cb-notes-library__results-title h2\s*\{\s*margin:\s*0;\s*\}/.test(css) &&
    css.includes('align-items: center;') &&
    !css.includes('.cb-notes-library__results-title h2 { margin-block-end:'),
    'Your notes heading has no bottom margin and aligns centrally with its checkbox');
check(!css.match(/#[0-9a-fA-F]{3,8}\b/), 'No hard-coded custom color palette');
console.log('PASS JS: tests/library-overview-contract.cjs');
