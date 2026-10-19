'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const load = path => fs.readFileSync(require('node:path').join(root, path), 'utf8');
const source = load('src/Portability/OwnerExport.php');
const provider = load('src/Portability/Provider.php');
const entity = load('src/Portability/NotesEntity.php');
const archive = load('src/Portability/MarkdownArchive.php');
assert.ok(source.includes("Engine::export_json('core-blueprint-notes', 'personal-notes')"),
    'JSON export uses Base canonical envelope');
assert.ok(source.includes('MarkdownArchive::zip($records)') &&
    source.includes('$entity->can_export()') &&
    source.includes('$entity->export_records()'),
    'Markdown ZIP uses existing authorized current-owner provider');
assert.ok(!source.includes('add_action(') && !source.includes('register_rest_route(')
    && !source.includes('header(') && !source.includes('readfile('),
    'no exposed endpoint or HTTP side effect in exporter');
assert.ok(provider.includes("Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_JSON") &&
    !provider.includes('Foundation::SUPPORT_IMPORT'),
    'import remains unadvertised until complete P2-B safety');
assert.ok(entity.includes('public function can_import(array $context = []): bool {\n        return false;'),
    'all importer writes still fail closed');
assert.ok(archive.includes('@unlink($temp)') && archive.includes('finally {'),
    'ZIP staging file removed even on errors');
console.log('Notes P3 read-only export service source contract: PASS (runtime not run)');
