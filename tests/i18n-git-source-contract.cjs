'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': 'a46f286eea15d881bbf839f383b50f33feb5a930',
 'core-blueprint-notes-nl_NL.po': '1f651837a815d42bb2e925538a108a79212a4d72',
 'core-blueprint-notes-de_DE.po': '57c768689e6384721ce60dce1829b3c961078374',
 'core-blueprint-notes-fr_FR.po': 'afa8b1b9beaa1c24441badebc160a5e029686ad9',
 'core-blueprint-notes-es_ES.po': '0f9ef1c04996858eea1fa07f3f2908204601f6b2',
 'core-blueprint-notes-it_IT.po': '39b2f9419ed0da0fe88082a21273fb622684ef7d',
 'core-blueprint-notes-pt_PT.po': '9ca63f8343b06c6e49145b40568542195eaac1a6',
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
 assert.equal(ids,319,'all 319 source messages retained in '+rel);
}
console.log('Notes seven Git-tracked locale sources: PASS (319 each; editorial review still open)');
