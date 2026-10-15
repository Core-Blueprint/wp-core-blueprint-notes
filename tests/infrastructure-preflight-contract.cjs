'use strict';
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
function must(ok, label) { if (!ok) throw new Error('Notes preflight contract: ' + label); }
const scripts = ['check-test-infrastructure','check-integration','check-rc1-alpha26-batch',
    'build-release','build-alpha26-fixture','check-restore','check-http',
    'check-rollback-guard','check-rollback-repro','check-secure-runtime'];
for (const name of scripts) {
    const result = spawnSync('bash', ['-n', path.join(root, 'tools', name)], { encoding: 'utf8' });
    must(result.status === 0 && !result.error, 'Invalid Bash: ' + name + ' ' + result.stderr);
}
const guard = read('tools/check-test-infrastructure');
const integration = read('tools/check-integration');
const batch = read('tools/check-rc1-alpha26-batch');
must(guard.includes('HostConfig.PortBindings') && guard.includes('INFORMATION_SCHEMA.SCHEMATA') &&
    guard.includes('volume=${MOUNT#volume:}'), 'Bounded read-only DB/port/volume checks');
must(!/\bdocker (run|start|rm)\b|\bCREATE DATABASE\b|\bDROP DATABASE\b/.test(guard),
    'Preflight may not mutate container or SQL');
must(integration.includes('CB_NOTES_DB_MUTATION_APPROVED') &&
    integration.includes('CREATE DATABASE core_blueprint_notes_test') &&
    integration.includes('DROP DATABASE core_blueprint_notes_test;') &&
    !integration.includes('DROP DATABASE IF EXISTS core_blueprint_notes_test'),
    'New-only integration, explicitly owned cleanup');
must(batch.indexOf('tools/check-test-infrastructure') < batch.indexOf('bash "$ROOT/tools/check"') &&
    batch.indexOf('check-integration" --execute') < batch.indexOf('tools/verify-alpha26-build'),
    'Preflight and Level 2 before internal fixture');
for (const name of ['check-restore','check-http','check-rollback-guard','check-rollback-repro','check-secure-runtime']) {
    must(read('tools/' + name).includes('bash "$ROOT/tools/check-test-infrastructure"') &&
        read('tools/' + name).includes('CB_NOTES_DB_MUTATION_APPROVED'), name + ' must use shared gate');
}
must(read('tools/build-release').includes('Notes customer release BLOCKED') &&
    read('tools/build-alpha26-fixture').includes('NON-CUSTOMER Alpha26 fixture'), 'No customer release');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cb-notes-preflight-'));
try {
    const docker = path.join(dir, 'docker');
    fs.writeFileSync(docker, [
        '#!/usr/bin/env bash', 'case "$1" in',
        'inspect) case "$*" in',
        '*".Config.Image"*) echo mariadb:10.11.19 ;;',
        '*".State.Running"*) [[ "$MOCK_MODE" == stopped ]] && echo false || echo true ;;',
        '*".HostConfig.PortBindings"*) [[ "$MOCK_MODE" == exposed ]] && echo \'{"3306/tcp":[{"HostIp":"0.0.0.0","HostPort":"3307"}]}\' || echo \'{"3306/tcp":[{"HostIp":"127.0.0.1","HostPort":"3307"}]}\' ;;',
        '*".Mounts"*) echo volume:mocknotes123 ;;',
        '*) exit 2 ;; esac ;;',
        'port) echo 127.0.0.1:3307 ;;',
        'ps) echo cb-base-test-db; [[ "$MOCK_MODE" == shared ]] && echo second-instance || true ;;',
        'exec) [[ "$MOCK_MODE" == collision ]] && echo core_blueprint_notes_test || true ;;',
        '*) exit 3 ;; esac'
    ].join('\n') + '\n');
    fs.chmodSync(docker, 0o700);
    for (const [mode, success] of [['clean',true],['stopped',false],['exposed',false],
        ['shared',false],['collision',false]]) {
        const r = spawnSync('bash', [path.join(root, 'tools/check-test-infrastructure')], {
            env: { ...process.env, PATH: dir + ':' + process.env.PATH, MOCK_MODE: mode },
            encoding: 'utf8', timeout: 12000,
        });
        must(!r.error && (r.status === 0) === success, mode + ': ' + r.stdout + r.stderr);
    }
} finally { fs.rmSync(dir, { recursive: true, force: true }); }
console.log('Notes shared infrastructure preflight: PASS (mocked Docker; real runtime NOT RUN)');
