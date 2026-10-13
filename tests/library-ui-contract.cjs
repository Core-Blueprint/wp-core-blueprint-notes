'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'src/Library/Page.php'), 'utf8');
const query = fs.readFileSync(path.join(root, 'src/Library/Query.php'), 'utf8');
const plugin = fs.readFileSync(path.join(root, 'src/Plugin.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/library-admin.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/library-admin.css'), 'utf8');

function verify(ok, description) {
    if (!ok) {
        console.error('FAIL library contract: ' + description);
        process.exit(1);
    }
}

verify(page.includes("current_user_can('cb_notes_edit')"), 'capability check');
verify(page.includes('Catalog::catalog($owner)'), 'owner catalog');
verify(page.includes('Query::state($_GET, $catalog)'), 'validated query state');
verify(page.includes('Access::can_read($post)'), 'per-row authorization');
verify(page.includes('(int)$post->post_author !== $owner'), 'personal-only view for admins');
verify(page.includes('aria-current="page"'), 'accessible notebook state');
verify(page.includes('type="search"') && page.includes('type="hidden" name="cbn_notebook"'), 'search and notebook filters');
verify(page.includes('<details class="cb-notes-library__advanced"'), 'advanced filter disclosure');
verify(page.includes('get_edit_post_link') && page.includes('get_delete_post_link'), 'native editing and trash actions');
verify(page.includes('data-cb-notes-trash-url') && page.includes(' hidden'), 'trash disabled without explicit JS confirmation');
verify(query.includes("'author' => $owner > 0 ? $owner : -1"), 'SQL owner bound');
verify(query.includes("'post__in'] = [0]"), 'fail-closed invalid filters');
verify(plugin.includes('LibraryPage::init()'), 'library registered in plugin');
verify(js.includes('window.confirm(') && js.includes('window.location.assign(url)'), 'explicit destructive confirmation');
verify(!/\bjQuery\b|\$\(/.test(js), 'no jQuery');
verify(css.includes('var(--cb-surface-') && css.includes('var(--cb-border)'), 'semantic design tokens');
verify(css.includes('@media (max-width: 680px)') && css.includes(':focus-visible'), 'responsive and keyboard focus');
console.log('PASS JS: tests/library-ui-contract.cjs');
