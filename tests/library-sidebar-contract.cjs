'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'src/Library/Page.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/library-admin.css'), 'utf8');

function check(ok, message) {
    if (!ok) throw new Error('Notes sidebar: ' + message);
}
const start = page.indexOf('public static function render(): void');
const body = page.slice(start);
const notebook = body.indexOf("esc_html__('Notebooks', 'core-blueprint-notes')");
const navEnd = body.indexOf("'</nav><div class=\"cb-notes-library__sidebar-footer\">'");
const footerLink = body.indexOf('class="cb-notes-library__organize-link"');
const main = body.indexOf("echo '<main class=\"cb-notes-library__main");
check(notebook >= 0 && navEnd > notebook && footerLink > navEnd && main > footerLink,
    'Organize action is a dedicated footer after notebooks, outside the navigation list');
check(body.includes("'edit.php?post_type=' . Plugin::TYPE . '&page=cb-notes-organize'") &&
    body.includes('esc_url($organize_url)'),
    'Organize action preserves canonical admin destination and escaped URL');
check(body.includes("esc_html__('Organize notes', 'core-blueprint-notes')") &&
    !body.includes('Manage notebooks and labels'),
    'Footer uses concise localized Organize notes label instead of legacy text link');
check(body.includes('class="cb-notes-library__organize-icon"') &&
    body.includes('stroke="currentColor"') &&
    body.includes('aria-hidden="true" focusable="false"'),
    'Sliders icon is decorative, uses currentColor and is hidden from assistive technologies');
check(css.includes('.cb-notes-library__sidebar-footer {') &&
    css.includes('border-block-start: 1px solid var(--cb-border);') &&
    css.includes('var(--cb-space-3, 12px)'),
    'Footer has a token-based 1px divider and compact separation');
check(css.includes('.cb-core-wrap .cb-notes-library__organize-link {') &&
    css.includes('display: flex;') && css.includes('align-items: center;') &&
    css.includes('color: var(--cb-text-muted);') &&
    css.includes('text-decoration: none;'),
    'Footer link looks like a compact muted navigation action without an underline');
check(css.includes('.cb-core-wrap .cb-notes-library__organize-link:hover,') &&
    css.includes('.cb-core-wrap .cb-notes-library__organize-link:focus-visible {') &&
    css.includes('background: var(--cb-surface-3);') &&
    css.includes('color: var(--cb-text-strong);'),
    'Hover/focus matches existing Base-token navigation affordances');
check(css.includes('.cb-notes-library a:focus-visible,') &&
    css.includes('outline: 2px solid var(--cb-interactive-focus, var(--cb-accent));'),
    'Existing keyboard focus remains visible with a theme token');
check(!css.includes('.cb-notes-library__organize {') &&
    !/#[0-9a-fA-F]{3,8}\b/.test(css),
    'Legacy unstyled link and independent hardcoded palette are absent');
check(body.includes('self::nav_link(__(\'Unfiled\'') &&
    body.includes('self::view_link(__(\'Favorites\'') &&
    body.includes('self::view_link(__(\'Pinned\''),
    'Existing quick access and Notebook links remain intact');
console.log('PASS JS: tests/library-sidebar-contract.cjs');
