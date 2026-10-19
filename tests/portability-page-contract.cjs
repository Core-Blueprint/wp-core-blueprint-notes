'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const read = file => fs.readFileSync(path.join(__dirname, '..', file), 'utf8');
const page = read('src/Portability/Page.php');
const plugin = read('src/Plugin.php');
const library = read('src/Library/Page.php');
const provider = read('src/Portability/Provider.php');
const entity = read('src/Portability/NotesEntity.php');
assert.ok(plugin.includes('PortabilityPage::init()'), 'Owner page registered by plugin');
assert.ok(library.includes('Portability\\Page::url()'), 'Library links to secondary page');
assert.ok(page.includes("'edit.php?post_type=' . Plugin::TYPE") &&
    page.includes('remove_submenu_page(') &&
    page.includes("add_action('admin_menu', [self::class, 'hide_menu_row'], 50)"),
    'Registered WordPress Notes screen hides only the submenu row');
assert.ok(page.includes("add_action('load-' . self::$hook, [self::class, 'private_screen_headers'])") &&
    page.includes('function private_screen_headers(): void'),
    'Preview screen no-store headers are set before admin HTML renders');
assert.ok(page.includes('admin_post_') && page.includes('check_admin_referer(self::ACTION)'),
    'Download action authenticated and nonce-bound');
assert.ok(page.includes("['json', 'markdown']") &&
    page.includes('OwnerExport::json()') && page.includes('OwnerExport::markdown_zip()'),
    'Both export formats use canonical owner services');
for (const header of [
    'nocache_headers()', 'X-Robots-Tag: noindex', 'X-Content-Type-Options: nosniff',
    'Content-Disposition: attachment', 'Content-Length:',
]) assert.ok(page.includes(header), 'Private download response: ' + header);
assert.ok(page.includes("current_user_can('cb_notes_edit')") &&
    page.includes("get_current_user_id() > 0"),
    'Owner capability gate present for download and preview');
assert.ok(page.includes('is_uploaded_file(') &&
    page.includes('UPLOAD_ERR_OK') && page.includes('10 * 1024 * 1024'),
    'Untrusted upload validated and bounded');
assert.ok(page.includes('ImportSession::with_validated_json(') &&
    page.includes('ImportSession::record('),
    'Complete owner-scoped document validation precedes preview');
assert.ok(!page.includes('apply_json(') && !page.includes('wp_insert_post(') &&
    !page.includes('update_user_meta('),
    'No writable import endpoint or hidden mutation');
assert.ok(provider.includes("Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_JSON") &&
    !provider.includes('Foundation::SUPPORT_IMPORT') &&
    entity.includes('public function can_import(array $context = []): bool {\n        return ImportSession::authorized($context);'),
    'Base import remains undiscoverable until owner write/recovery accepted');
console.log('Notes owner portability page read-only source contract: PASS (browser pending)');
