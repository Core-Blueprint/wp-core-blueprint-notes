'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname,'..');
const read = p=>fs.readFileSync(path.join(root,p),'utf8');
const runners=['check-rc1-alpha26-batch','verify-alpha26-build','check-alpha26-artifact',
  'check-restore','check-http','check-rollback-repro','check-rollback-guard','check-secure-runtime'];
for(const runner of runners){
 const s=read('tools/'+runner);
 assert.match(s,/NOTES_BRANCH/);
 assert.ok(s.includes('feature/notes-rc1-golden-hardening-v1'));
 assert.ok(s.includes('feature/notes-rc1-i18n-foundation-v1'));
 assert.ok(!/feature\/notes-rc1-\*/.test(s));
}
const importer=read('tools/i18n/import-candidate.py');
assert.ok(importer.includes('b87d3fb77e324a77af431c6d6f5be3a809b243b2cc25f01f6c8692aaf5c4b5bc'));
assert.ok(importer.includes('if len(keys) != 305:'));
assert.ok(importer.includes('if LANG.exists():'));
assert.ok(importer.includes('catalog.validate_po('));
assert.ok(importer.includes('git'));
console.log('Notes exact-branch and pinned candidate safeguards: PASS');
