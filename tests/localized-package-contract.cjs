'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const build = read('tools/build-localized-candidate');
for (const required of ['check-rc1-alpha26-batch" --execute','tools/i18n/check','--no-purge','msgfmt --check-header --check-format','assets/editor.js','assets/sharing.js','assets/organization-admin.js','assets/library-admin.js','assets/library-bulk.js','FIRST=','SECOND=','EDITORIAL/FIELD/RELEASE HOLD']) {
  assert.ok(build.includes(required), 'Missing localizable-runtime requirement: ' + required);
}
assert.match(read('tools/build-release'), /Notes customer release BLOCKED/);
console.log('Notes localized candidate builder source: PASS (runtime NOT RUN)');
