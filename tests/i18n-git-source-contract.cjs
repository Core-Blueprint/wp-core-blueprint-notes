'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': 'eaa0844135410ae834830b2f0410570fda38f136',
 'core-blueprint-notes-nl_NL.po': '519175ebbf9222f4afe40d9cffef3a1ebafc4338',
 'core-blueprint-notes-de_DE.po': '8c66c81712f32130a4525640d37f8fc9d8ceb190',
 'core-blueprint-notes-fr_FR.po': '936daaeadc91134758f658dbf418f2599cc0a9da',
 'core-blueprint-notes-es_ES.po': 'd96f673574f0d9d7d920a99a40e62d0baf0326e3',
 'core-blueprint-notes-it_IT.po': '57c94e38545042a2813c97fa296a8b96fc9a107d',
 'core-blueprint-notes-pt_PT.po': '4ea9f45ea83d64ab732421e9e410cb59ff75c238',
};
for (const [name, gitBlob] of Object.entries(expected)) {
 const rel='languages/'+name, source=fs.readFileSync(path.join(root,rel),'utf8');
 const result=cp.spawnSync('git',['hash-object',rel],{cwd:root,encoding:'utf8',timeout:10000});
 assert.equal(result.status,0,'git hash-object must pass: '+rel);
 assert.equal(result.stdout.trim(),gitBlob,'catalog content must match audited source: '+rel);
 const tracked=cp.spawnSync('git',['ls-files','--error-unmatch','--',rel],
     {cwd:root,encoding:'utf8',timeout:10000});
 assert.equal(tracked.status,0,'Git must track catalog: '+rel);
 const ids=(source.match(/^msgid "/gm)||[]).length-1;
 assert.equal(ids,305,'all 305 source messages retained in '+rel);
}
console.log('Notes seven Git-tracked locale sources: PASS (305 each; editorial review still open)');
