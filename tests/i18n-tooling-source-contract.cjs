'use strict';
// Static tooling integrity only; never asserts a reviewed locale catalog PASS.
const fs = require('node:fs');
const crypto = require('node:crypto');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const source = Buffer.from(read('tools/i18n/catalog.py'));
const reference = JSON.parse(read('tools/i18n/reference.json'));
const cfg = JSON.parse(read('tools/i18n/config.json'));
const gitBlob = crypto.createHash('sha1')
  .update(Buffer.concat([Buffer.from('blob ' + source.length + '\0'), source])).digest('hex');
assert.equal(reference.implementation, 'core-blueprint-first-party-i18n');
assert.equal(gitBlob, reference.catalog_git_blob);
assert.equal(crypto.createHash('sha256').update(source).digest('hex'), reference.catalog_sha256);
assert.equal(cfg.product, 'Core Blueprint Notes');
assert.equal(cfg.domain, 'core-blueprint-notes');
assert.equal(cfg.main_file, 'core-blueprint-notes.php');
assert.equal(cfg.version_constant, 'CB_NOTES_VERSION');
assert.equal(cfg.skip_js, false);
assert.deepEqual(cfg.source_paths, ['core-blueprint-notes.php', 'src', 'assets']);
assert.match(source.toString('utf8'), /nl_NL.*de_DE.*fr_FR.*es_ES.*it_IT.*pt_PT/);
assert.match(source.toString('utf8'), /make-pot/);
assert.match(source.toString('utf8'), /untranslated entry/);
assert.match(source.toString('utf8'), /fuzzy translation/);
for (const name of ['check','update']) {
  assert.match(read('tools/i18n/' + name), /tools\/i18n\/catalog.py/);
}
assert.match(read('tools/i18n/check'), /tools\/i18n\/check-reference/);
assert.match(read('tools/build-release'), /Notes customer release BLOCKED/);
console.log('Notes i18n tooling source: PASS (six-locale CONTENT HOLD; runtime NOT RUN)');
