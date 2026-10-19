'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': '69054ef5423dbe7979c703a6b0f0f357452f3698',
 'core-blueprint-notes-nl_NL.po': '9376ca08d08208d4de147953410b5e73f03248cc',
 'core-blueprint-notes-de_DE.po': 'd574dc05eccfadc2133bdd1acf6bd42a2604d6e5',
 'core-blueprint-notes-fr_FR.po': 'baec6c56d2bba6e956c0317b548042b4ea9c0f84',
 'core-blueprint-notes-es_ES.po': '44a090525dc3e6df79473dedd8b04892c0b7f567',
 'core-blueprint-notes-it_IT.po': '9680f931a33997cdd09408184ec7b3ee45fb821b',
 'core-blueprint-notes-pt_PT.po': 'ad9cb66e5906d7e14ff87f82c666be902928d415',
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
 assert.equal(ids,308,'all 308 source messages retained in '+rel);
}
console.log('Notes seven Git-tracked locale sources: PASS (308 each; editorial review still open)');
