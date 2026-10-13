'use strict';

// The source gate is deliberately NOT a substitute for two-user WP runtime tests.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');

function check(value, detail) {
    if (!value) throw new Error('RC1 source gate: ' + detail);
}

const plugin = read('core-blueprint-notes.php');
const access = read('src/Access.php');
const sharing = read('src/Sharing/Controller.php');
const snapshot = read('src/Sharing/Snapshot.php');
const catalog = read('src/Organization/Catalog.php');
const abilities = read('src/Abilities.php');
const library = read('src/Library/Query.php');
const build = read('tools/build-release');
const acceptance = read('docs/RC1-GOLDEN-HARDENING-ACCEPTANCE.md');
const readme = read('README.md');

check(plugin.includes('Version:           0.1.0-alpha24') &&
    plugin.includes("define('CB_NOTES_VERSION', '0.1.0-alpha24')") &&
    readme.includes('0.1.0-alpha24') &&
    build.includes("'0.1.0-alpha24'"),
    'Header, runtime, documentation and release script use one exact alpha candidate');
check(plugin.includes('Requires at least: 7.0') &&
    plugin.includes('Requires PHP:      8.4') &&
    plugin.includes('Requires Plugins: core-blueprint') &&
    plugin.includes("define('CB_NOTES_REQUIRED_API', '1.2')") &&
    plugin.includes('cb_notes_requirement_error'),
    'Base / WordPress / PHP dependency constraints remain explicit');
check(plugin.includes("'public'=>false,'publicly_queryable'=>false") &&
    plugin.includes("'exclude_from_search'=>true") &&
    plugin.includes("'show_in_rest'=>true") &&
    plugin.includes("'rest_base'=>'cb-notes'") &&
    access.includes('before_rest') && access.includes('scope_rest') &&
    access.includes('scope_query') && access.includes('map_meta_cap'),
    'Non-public Gutenberg CPT is protected by read, list and object policy layers');
check(catalog.includes('get_current_user_id()') &&
    catalog.includes('Catalog::') === false &&
    library.includes("'author' => $owner > 0 ? $owner : -1") &&
    library.includes("'invalid' => $invalid") &&
    library.includes("'post__in'"),
    'Personal catalog and Library deny unknown/foreign selections and force owner scope');
check(sharing.includes("$request->get_param('confirmation') !== 'PUBLIC'") &&
    sharing.includes('hash_equals') &&
    sharing.includes('public_snapshot_allowed') &&
    sharing.includes('Controller') &&
    sharing.includes('revoke_for_lifecycle') &&
    snapshot.includes('private const ALLOWED') &&
    snapshot.includes('wp_kses(render_block('),
    'Sharing keeps typed acknowledgement, frozen allowlisted projection and lifecycle revocation');
check(abilities.includes("get_option(Plugin::AI_OPTION,false)") &&
    abilities.includes("'readonly'=>true") &&
    abilities.includes("'mcp'=>['public'=>$enabled") &&
    !abilities.includes("'create-draft'=>") &&
    !abilities.includes("'propose-update'=>"),
    'AI abilities are read-only and not externally exposed by default');
check(sharing.includes("'Note not found.' => __('Note not found.', 'core-blueprint-notes')") &&
    catalog.includes("'notebook' => __('All notebooks', 'core-blueprint-notes')") &&
    !/\b(?:__|esc_html__|esc_attr__)\s*\(\s*\$/.test(sharing) &&
    !/\b(?:__|esc_html__|esc_attr__)\s*\(\s*\$/.test(catalog),
    'Sharing errors and classification filter labels use static gettext keys');
check(build.includes('rm -f -- "$ZIP"') &&
    build.includes('zip -q -X "$ZIP" -@') &&
    build.includes('touch -t 202001010000.00') &&
    build.includes("Unexpected package file:") &&
    build.includes('Symlinks are not allowed'),
    'Release script rebuilds clean, stable metadata ZIP from allowlisted source only');
for (const gate of ['R01','R02','R03','R04','R05','R06','R07','R08','R09','R10','R11','R12','R13']) {
    check(acceptance.includes(gate), 'Acceptance matrix missing ' + gate);
}
check(acceptance.includes('NOT RUN') &&
    acceptance.includes('JavaScript') &&
    acceptance.includes('portability') &&
    acceptance.includes('uninstall'),
    'Unexecuted runtime/security/i18n/portability release blockers are explicitly recorded');

console.log('PASS JS: tests/rc1-source-contract.cjs (source-only; runtime gates remain open)');
