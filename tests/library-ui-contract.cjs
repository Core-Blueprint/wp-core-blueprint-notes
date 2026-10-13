'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const page = fs.readFileSync(path.join(root, 'src/Library/Page.php'), 'utf8');
const query = fs.readFileSync(path.join(root, 'src/Library/Query.php'), 'utf8');
const plugin = fs.readFileSync(path.join(root, 'src/Plugin.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/library-admin.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/library-admin.css'), 'utf8');
const markers = fs.readFileSync(path.join(root, 'src/Library/Markers.php'), 'utf8');

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
verify(page.includes('class="button cb-core-button cb-core-button--secondary cb-core-button--compact cb-core-button--icon-only cb-notes-library__edit"'), 'Edit uses canonical Base compact icon-only secondary button');
verify(page.includes('class="button cb-core-button cb-core-button--danger cb-core-button--compact cb-core-button--icon-only cb-notes-library__trash" hidden'), 'Trash uses canonical Base compact icon-only danger button');
verify(!page.includes('button-link cb-notes-library__trash'), 'no old text-link trash action');
verify(page.includes('dashicons dashicons-edit') && page.includes('dashicons dashicons-trash'), 'Edit and Trash render WordPress pencil/trash icons');
verify(page.includes("__('Edit note: %s'") && page.includes("__('Move note to Trash: %s'"),
    'Icon-only controls have localized per-note accessible names');
verify(page.includes('aria-label="' + "' . esc_attr($edit_label)" )
    && page.includes('aria-label="' + "' . esc_attr($trash_label)" ),
    'Both icon-only actions expose accessible labels');
verify(page.includes('title="' + "' . esc_attr($edit_label)" )
    && page.includes('title="' + "' . esc_attr($trash_label)" ),
    'Both icon-only actions show accessible native tooltips');
verify(!page.includes("esc_html__('Move to Trash', 'core-blueprint-notes') . '</button>'"),
    'Trash action no longer renders a text label');
verify(css.includes('.cb-core-button.cb-notes-library__trash[hidden]') && css.includes('display: none;'), 'hidden trash remains hidden against Base inline-flex display');
verify(css.includes('button.cb-core-button.cb-core-button--danger.cb-notes-library__trash {')
    && css.includes('border: 1px solid var(--cb-danger);')
    && css.includes('color: var(--cb-danger);'),
    'Trash is visibly outlined red using the canonical Base danger token');
verify(css.includes('button.cb-core-button.cb-core-button--danger.cb-notes-library__trash:hover,')
    && css.includes('background: var(--cb-danger);')
    && css.includes('color: var(--cb-surface-1);'),
    'Trash hover/focus uses the Base danger interaction contract');
verify(css.includes('button.cb-core-button.cb-core-button--danger.cb-notes-library__trash:focus-visible {')
    && css.includes('outline: 2px solid var(--cb-danger);'),
    'Trash focus ring is danger red rather than generic blue');
verify(!/#[0-9a-fA-F]{3,8}\b/.test(css),
    'Notes Library introduces no independent hardcoded danger palette');

verify(page.includes('data-cb-notes-trash-url') && page.includes(' hidden'), 'trash disabled without explicit JS confirmation');
verify(query.includes("'author' => $owner > 0 ? $owner : -1"), 'SQL owner bound');
verify(query.includes("'post__in'] = [0]"), 'fail-closed invalid filters');
verify(plugin.includes('LibraryPage::init()'), 'library registered in plugin');
verify(js.includes('confirmation.show({') && js.includes("confirmVariant: 'danger'") && js.includes('window.location.assign(url)'), 'Base danger modal before destructive navigation');
verify(!js.includes('window.confirm('), 'no browser confirm fallback');
verify(page.includes('Assets::enqueue_modals('), 'Base Modal Foundation enqueued for Library');
verify(page.includes('render_empty_state($state'), 'contextual empty-state renderer');
verify(page.includes('This notebook is empty') && page.includes('Your Library is empty') && page.includes('No unfiled notes'), 'distinct empty states');
verify(!/\bjQuery\b|\$\(/.test(js), 'no jQuery');
verify(css.includes('var(--cb-surface-') && css.includes('var(--cb-border)'), 'semantic design tokens');
verify(css.includes('@media (max-width: 680px)') && css.includes(':focus-visible'), 'responsive and keyboard focus');
verify(page.includes('data-cb-notes-marker') && page.includes('aria-pressed='), 'P1 markers have accessible state and intent');
verify(page.includes('Quick access') && page.includes('Favorites') && page.includes('Pinned'), 'P1 saved views are discoverable');
verify(page.includes('wp_create_nonce(') && markers.includes("wp_verify_nonce($nonce"), 'P1 markers require per-note CSRF validation');
verify(markers.includes("(int)$post->post_author === get_current_user_id()"), 'P1 owner cannot mark another owner note');
verify(markers.includes("current_user_can('edit_post', $id)"), 'P1 marker also requires object capability');
verify(markers.includes('posts_orderby') && markers.includes('cb_notes_pinned_first'), 'P1 pinned sorting is query scoped');
verify(!markers.includes('register_post_meta(') && !markers.includes('show_in_rest'), 'P1 flags are not Gutenberg or public REST schemas');
verify(query.includes("Markers::FAVORITE") && query.includes("Markers::PINNED"), 'P1 saved views filter by exact private metadata');
verify(js.includes("window.fetch(endpoint") && js.includes("credentials: 'same-origin'"), 'P1 actions are authenticated async requests');
verify(css.includes('cb-notes-library__marker[hidden]'), 'P1 actions remain hidden without JavaScript');
verify(css.includes('.cb-notes-library__actions .cb-core-button--icon-only > .dashicons {')
    && css.includes('align-items: center;')
    && css.includes('justify-content: center;')
    && css.includes('.cb-notes-library__actions .cb-core-button--icon-only > .dashicons::before {'),
    'All Library action glyphs are centered inside Base icon-only buttons');
verify(!css.includes('.cb-notes-library__marker .dashicons { font-size: 18px;'),
    'P1 icon sizing stays owned by Base');
console.log('PASS JS: tests/library-ui-contract.cjs');
