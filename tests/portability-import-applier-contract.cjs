'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = p => fs.readFileSync(path.join(__dirname, '..', p), 'utf8');
const body = read('src/Portability/ImportApplier.php');
const notes = read('src/Portability/NotesEntity.php');
const provider = read('src/Portability/Provider.php');
const session = read('src/Portability/ImportSession.php');
assert.ok(body.includes('ImportSession::authorized($context)') &&
    body.includes('ImportPlanner::plan_record($source, Foundation::MODE_CREATE_ONLY, 1, $context)') &&
    body.includes('$expected !== $plan'), 'No foreign or altered plans accepted');
assert.ok(body.includes('ImportJournal::reserve(') &&
    body.indexOf('ImportJournal::reserve(') < body.indexOf('wp_insert_post('),
    'Write-ahead receipt exists before a WordPress insert');
assert.ok(body.includes('ImportJournal::complete(') &&
    body.indexOf('ImportJournal::complete(') > body.indexOf('get_post_meta('),
    'Completion follows object/meta validation');
assert.ok(body.includes("count(get_user_meta($owner, Catalog::USER_META, false)) !== 1") &&
    body.includes('update_user_meta($owner, Catalog::USER_META, $target, $old)'),
    'Owner catalog uses previous-value compare and detects duplicates');
assert.ok(body.includes('post_author') && body.includes('get_current_user_id()') &&
    body.includes("['draft', 'private']"),
    'Destination actor/status restricted');
assert.ok(!body.includes('share_mode') && !body.includes('public_share')
    && !body.includes('provider_grant') && !body.includes('wp_delete_post'),
    'No restored sharing/grant or destructive cleanup');
assert.ok(session.includes('function target_plan()') && session.includes('function catalog('),
    'Only owner-attested mapping is allowed');
assert.ok(provider.includes("Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_JSON")
    && !provider.includes('Foundation::SUPPORT_IMPORT') &&
    notes.includes('public function can_import(array $context = []): bool {\n        return false;'),
    'Writable adapter is still dormant until real WordPress acceptance');
console.log('Notes inert P2-B write adapter source contract: PASS (runtime NOT RUN)');
