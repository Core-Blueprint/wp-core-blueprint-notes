'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const root = path.resolve(__dirname,'..');
const expected = {
 'core-blueprint-notes.pot': '59ec3650c90d36a843b3bffdc9e96d61357c4562',
 'core-blueprint-notes-nl_NL.po': '572b4dbf7ae993b09dada658bb2dd02c2209c2d1',
 'core-blueprint-notes-de_DE.po': '6786dcc5e452e3a98825148b1c14e52ee1dbb27e',
 'core-blueprint-notes-fr_FR.po': '9e9ea20834e3b9d3f3a8ccfa9f370b6c184f7e76',
 'core-blueprint-notes-es_ES.po': 'd059ce8376e874033095a5ae466bc43070d33a41',
 'core-blueprint-notes-it_IT.po': '5a4927f4280278d9cbc4c0b81a6945c7963dfdd5',
 'core-blueprint-notes-pt_PT.po': 'e5d7b4c2b5bf851f1ad513ef0f360f29561322be',
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
