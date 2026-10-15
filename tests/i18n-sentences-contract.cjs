'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const src = fs.readFileSync(path.join(__dirname, '..', 'assets/organization-admin.js'), 'utf8');
for (const kind of ['Notebook', 'Category', 'Tag']) {
  for (const action of ['created', 'renamed', 'removed']) {
    assert.ok(src.includes("__('" + kind + " " + action + ".'"+ ", 'core-blueprint-notes')"),
      kind + ' ' + action + ' must be a complete translatable sentence');
  }
}
for (const kind of ['notebook', 'category', 'tag']) {
  const plural = kind === 'category' ? 'categories' : kind + 's';
  assert.ok(src.includes("__('No " + plural + " yet. Use Add new to create your first " + kind + ".'"+ ", 'core-blueprint-notes')"),
    kind + ' empty state must be independently translatable');
}
assert.ok(!src.includes("'%1$s %2$s successfully.'"));
assert.ok(!src.includes("'No %1$s yet. Use Add new to create your first %2$s.'"));
console.log('Notes full-sentence i18n contract: PASS');
