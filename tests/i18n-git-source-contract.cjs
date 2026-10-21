'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': '3a7fb2943645ac447788b6aa9bdead28f6c59329',
 'core-blueprint-notes-nl_NL.po': 'bcc6ee544c8770fbb170663151e0deb6adc86d83',
 'core-blueprint-notes-de_DE.po': '586c4d68b3ef622c710dbdce1447c05db30067e8',
 'core-blueprint-notes-fr_FR.po': '44a1e417152b621274a6890f497a3bb8c2822b15',
 'core-blueprint-notes-es_ES.po': '817e41f18767a7ef90f80e9c9f3d113d8bc583a0',
 'core-blueprint-notes-it_IT.po': '397bc0b410f700d43668b42859de7eaeb9c3f21f',
 'core-blueprint-notes-pt_PT.po': '9019fa1dd4c1bf83a43669b05b401bf43d9f33a8',
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
 assert.equal(ids,329,'all 329 source messages retained in '+rel);
}
console.log('Notes seven Git-tracked locale sources: PASS (329 each; editorial review still open)');
