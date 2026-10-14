'use strict';
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const fixture = path.join(__dirname, 'share-epoch-smoke.php');
const base = { ...process.env };
function run(mode, variant, site, snapshot) {
    const child = spawnSync('php', [fixture, mode], {
        encoding: 'utf8',
        env: {
            ...base,
            CB_EPOCH_TEST_VARIANT: variant || '',
            CB_EPOCH_TEST_SITE: site || 'https://notes-epoch.invalid/',
            CB_EPOCH_TEST_SNAPSHOT: snapshot ? JSON.stringify(snapshot) : '',
        },
        timeout: 10000,
    });
    if (child.error || child.status !== 0) {
        throw new Error('Notes epoch test subprocess failed: ' +
            ((child.error && child.error.message) || child.stderr || child.stdout).trim());
    }
    return child.stdout.trim();
}
function check(ok, name) {
    if (!ok) throw new Error('Notes epoch recovery FAIL: ' + name);
    console.log('PASS: ' + name);
}
check(run('missing', '', '') === 'MISSING_DENIED',
    'Missing independently provisioned epoch denies issuance and delivery');
const signed = JSON.parse(run('issue', 'a', 'https://notes-epoch.invalid/'));
check(signed.signature_version === 1 && /^[a-f0-9]{64}$/.test(signed.signature),
    'New public snapshot contains versioned HMAC authentication');
check(run('verify', 'a', 'https://notes-epoch.invalid/', signed) === 'ALLOWED',
    'Same install and epoch permits original frozen snapshot');
check(run('verify', 'b', 'https://notes-epoch.invalid/', signed) === 'DENIED',
    'A rotated independent epoch invalidates the old public snapshot');
check(run('verify', 'a', 'https://other-domain.invalid/', signed) === 'DENIED',
    'Cloned site at a different home URL cannot deliver previous public share');
const legacy = { ...signed };
delete legacy.signature;
delete legacy.signature_version;
check(run('verify', 'a', 'https://notes-epoch.invalid/', legacy) === 'DENIED',
    'Unsigned Alpha24 grant is denied, never silently grandfathered');
check(run('verify', 'a', 'https://notes-epoch.invalid/', { ...signed, html: '<p>TAMPERED</p>' }) === 'DENIED',
    'Edited frozen snapshot content invalidates signature');
check(run('verify', 'a', 'https://notes-epoch.invalid/', { ...signed, title: 'TAMPERED' }) === 'DENIED',
    'Edited snapshot title invalidates signature');
console.log('Notes independent Public share epoch contract: PASS');
