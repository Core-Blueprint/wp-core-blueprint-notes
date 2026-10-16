'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname,'..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const runners=['check-rc1-alpha26-batch','verify-alpha26-build','check-alpha26-artifact',
  'check-restore','check-http','check-rollback-repro','check-rollback-guard','check-secure-runtime'];
for(const runner of runners){
 const s=read('tools/'+runner);
 assert.match(s,/NOTES_BRANCH/);
 assert.ok(s.includes('feature/notes-rc1-golden-hardening-v1'));
 assert.ok(s.includes('feature/notes-rc1-i18n-foundation-v1'));
 assert.ok(s.includes('feature/notes-rc1-portability-data-exchange-v1'));
 assert.ok(!/feature\/notes-rc1-\*/.test(s));
}
assert.ok(!fs.existsSync(path.join(root,'tools/i18n/import-candidate')));
assert.ok(!fs.existsSync(path.join(root,'tools/i18n/import-candidate.py')));
console.log('Notes exact-branch and no external ZIP import: PASS');
