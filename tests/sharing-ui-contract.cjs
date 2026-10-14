'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const sharing = fs.readFileSync(path.join(root, 'assets/sharing.js'), 'utf8');
const notebook = fs.readFileSync(path.join(root, 'assets/editor.js'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'src/Sharing/Controller.php'), 'utf8');
const access = fs.readFileSync(path.join(root, 'src/Access.php'), 'utf8');

const checks = [
    ['Base danger confirmation', sharing.includes("confirmVariant: 'danger'")],
    ['Typed PUBLIC confirmation', sharing.includes("typedConfirm: 'PUBLIC'")],
    ['Explicit acknowledgement gate', sharing.includes('confirmCheck: {')],
    ['Fail-closed when Base modal unavailable', sharing.includes('if (!modal || typeof modal.show !==')],
    ['Share mode required in requests', sharing.includes('expected_mode: sharing.mode')],
    ['Content digest required in requests', sharing.includes('expected_content_version: sharing.content_version')],
    ['Emergency revocation not blocked by unsaved edits', sharing.includes("mode !== 'private'") && sharing.includes('disabled: busy')],
    ['Secure Share feature detected', sharing.includes('sharing.secure_available')],
    ['Optional Secure Share controls appear only when available', sharing.includes('if (sharing.secure_available) {')],
    ['Missing Secure Share has no placeholder or upsell', !sharing.includes('Secure Share is optional and currently unavailable.') && !sharing.includes("key: 'unavailable'")],
    ['Notebook primary, sharing independently registered', notebook.includes("label: __('Notebook', 'core-blueprint-notes')") && notebook.includes("title: __('Organize', 'core-blueprint-notes')") && sharing.includes("name: 'cb-notes-sharing'")],
    ['Save and share separation clearly explained', sharing.includes('Saving or publishing in Gutenberg keeps the note private')],
    ['Active shared copies are explained', sharing.includes('Saving changes will not update the frozen shared copy')],
    ['Public mode requires server confirmation', controller.includes("get_param('confirmation') !== 'PUBLIC'")],
    ['Public shares fail closed on trash', controller.includes("'trash', 'auto-draft', 'inherit'")],
    ['Missing recovery epoch blocks Public issuance', sharing.includes('disabled: blocked || !sharing.public_ready')],
    ['Unsigned legacy Public link shows unavailable status', sharing.includes('mode === \'public\' && !sharing.public_valid')],
    ['Revocation remains accessible even when an epoch is missing', sharing.includes("key: 'stop'") && sharing.includes('disabled: busy')],
    ['Notes publish status downgraded to private', access.includes("$data['post_status'] = 'private'")],
    ['Author gate for sharing', controller.includes("(int)$post->post_author === get_current_user_id()")],
];

checks.forEach(([name, result]) => {
    assert.equal(result, true, name);
    process.stdout.write('PASS: ' + name + '\n');
});
process.stdout.write('Notes alpha3 sharing UI contract: PASS\n');
