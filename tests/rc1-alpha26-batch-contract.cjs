'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const file = p => fs.readFileSync(path.join(root, p), 'utf8');
const batch = file('tools/check-rc1-alpha26-batch');
const verifier = file('tools/verify-alpha26-build');
const preflight = file('tools/check-alpha26-artifact');
function must(ok, label) {
    if (!ok) throw new Error('Notes Alpha26 one-batch contract: ' + label);
}
for (const name of ['check-rc1-alpha26-batch', 'verify-alpha26-build', 'check-alpha26-artifact']) {
    const syntax = spawnSync('bash', ['-n', path.join(root, 'tools/' + name)], {
        encoding: 'utf8', timeout: 10000,
    });
    must(!syntax.error && syntax.status === 0, 'Invalid complete Bash source: ' + name);
}
must(batch.includes('set -euo pipefail') &&
    batch.includes('bash -n "$0"') &&
    batch.includes("feature/notes-rc1-golden-hardening-v1") &&
    batch.includes('git -C "$ROOT" diff --quiet'),
    'Batch refuses modified tracked source and unexpected branch');
const phases = [
    'bash "$ROOT/tools/check"',
    'bash "$ROOT/tools/check-integration" --execute',
    'CB_TEST_WP_VERSION=7.1.3 bash "$ROOT/tools/check-integration" --execute',
    'bash "$ROOT/tools/verify-alpha26-build"',
    'bash "$ROOT/tools/check-restore"',
    'bash "$ROOT/tools/check-http"',
    'bash "$ROOT/tools/check-rollback-guard"',
    'bash "$ROOT/tools/check-secure-runtime"',
    'node "$ROOT/tests/r08/https-security-source-contract.cjs"',
];
let at = -1;
for (const phase of phases) {
    const next = batch.indexOf(phase);
    must(next > at, 'Missing/misordered test phase: ' + phase);
    at = next;
}
must(verifier.includes('rm -f -- "$STAMP"') &&
    (verifier.match(/bash "\$ROOT\/tools\/build-alpha26-fixture"/g) || []).length === 2 &&
    verifier.includes('SHA1') && verifier.includes('SHA2') &&
    verifier.includes('[[ "$SHA1" == "$SHA2"') &&
    verifier.includes('git -C "$ROOT" rev-parse HEAD'),
    'Verifier must create two identical builds bound to the exact clean source HEAD');
must(preflight.includes('notes-alpha26-verified.txt') &&
    preflight.includes('git -C "$ROOT" rev-parse HEAD') &&
    preflight.includes('sha256sum "$ZIP"') &&
    preflight.includes('unzip -t "$ZIP"'),
    'Runtime preflight must reject wrong source revision and archive digest');
must(batch.includes('bash "$ROOT/tools/check-test-infrastructure"') &&
    batch.includes('CB_NOTES_DB_MUTATION_APPROVED=notes-alpha26-new-only-v1') &&
    batch.includes('if [[ "$MODE" == "--preflight" ]]') &&
    batch.indexOf('tools/check-test-infrastructure') < batch.indexOf('tools/check"'),
    'Preflight must precede source/build/runtime activity');
must(batch.includes('NOT RUN: manual R01-R13') &&
    batch.includes('NO GO for RC1 promotion') &&
    !batch.includes('git merge') && !batch.includes('git push'),
    'Local PASS must never promote, merge, or claim browser and hosting acceptance');
console.log('Notes Alpha26 integrated local batch source safety: PASS (runtime NOT EXECUTED)');
