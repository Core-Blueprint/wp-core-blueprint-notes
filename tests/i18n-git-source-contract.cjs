'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': 'd1fe9f788111c1f55faebb44d1d7b01b496fc28d',
 'core-blueprint-notes-nl_NL.po': '202e77e2d13b747b9ec7d78563344373d1c9676e',
 'core-blueprint-notes-de_DE.po': 'a18b1650c70545fe85fc5322b0d386b09e7202ff',
 'core-blueprint-notes-fr_FR.po': '76765fb875cf6b38402c285971bb7cd0a507a285',
 'core-blueprint-notes-es_ES.po': 'f6e3ae4a5b092d9ad3de6c8f6baaf6ccd84a9407',
 'core-blueprint-notes-it_IT.po': '763eb629d71011b15b0f8f51511eb2863463613b',
 'core-blueprint-notes-pt_PT.po': '0ae7588403c602b749b3843cd20665bd87988892',
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
 assert.equal(ids,306,'all 306 source messages retained in '+rel);
}
console.log('Notes seven Git-tracked locale sources: PASS (306 each; editorial review still open)');
