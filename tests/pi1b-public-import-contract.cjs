'use strict';
/** PI-1B contract. Structural/source guard only; not browser or MariaDB proof. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = p => fs.readFileSync(path.join(__dirname, '..', p), 'utf8');
const provider = read('src/Portability/Provider.php');
const page = read('src/Portability/Page.php');
const flow = read('src/Portability/ImportFlow.php');
const session = read('src/Portability/ImportSession.php');
const seed = read('src/Portability/MappingSeed.php');
const entry = read('core-blueprint-notes.php');
const runner = read('tools/check');
assert.ok(seed.includes("random_bytes(32)") && seed.includes("add_option(") &&
    seed.includes("get_option(") && !seed.includes("wp_salt(") &&
    seed.includes("'autoload'") === false,
    'Site-owned mapping seed must be generated once and independent of WP auth salts');
assert.ok(session.includes('MappingSeed::read()') && !session.includes("wp_salt('auth')"),
    'Every Notes import request binds stable mapping seed, not rotatable AUTH_KEY');
assert.ok(entry.includes('MappingSeed::provision()'),
    'Fresh installations provision mapping before import preview, never during Notes preview');
assert.ok(provider.includes('Foundation::SUPPORT_IMPORT') &&
    read('src/Portability/NotesEntity.php').includes('ImportSession::authorized($context)'),
    'Public registry import capability must retain request-local Notes ticket gate');
assert.ok(flow.includes('ImportSession::with_validated_json(') &&
    flow.includes('Engine::preview_json(') && flow.includes('Engine::apply_json(') &&
    flow.includes('hash_equals(') && flow.includes('Foundation::MODE_CREATE_ONLY'),
    'Base Engine owns canonical preview fingerprint and confirmed apply');
assert.ok(page.includes('check_admin_referer(self::PREVIEW_NONCE)') &&
    page.includes('check_admin_referer(self::CONFIRM_NONCE)') &&
    page.includes('is_uploaded_file(') &&
    page.includes('cb_notes_confirm_create_only') &&
    page.includes('add_action(\'admin_post_\' . self::CONFIRM_ACTION, [self::class, \'confirm_import\'])'),
    'Explicit owner-bound two-request confirmation, no raw archive storage');
assert.ok(!page.includes('ImportApplier::apply(') &&
    !page.includes('wp_insert_post(') &&
    !page.includes('update_user_meta(') &&
    !page.includes('wp_remote_get('),
    'UI cannot directly write a Note or bypass Base JSON Engine');
assert.ok(runner.includes('node "$ROOT/tests/pi1b-public-import-contract.cjs"'),
    'Public import source contract wired to canonical Level 1');
console.log('Notes PI-1B public import source contract: PASS (real WP/HTTP NOT RUN)');
