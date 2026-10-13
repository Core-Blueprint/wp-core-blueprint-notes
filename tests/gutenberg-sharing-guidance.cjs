'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../assets/sharing.js'), 'utf8');

function renderSharing(sharing, dirty) {
    const configured = [];
    let slot = 0;
    const slots = [sharing, false, '', '', '', '7'];
    const element = (type, props, ...children) => ({
        type, props: props || {}, children: children.flat(Infinity)
    });
    const wp = {
        plugins: { registerPlugin(name, definition) { configured.push({ name, definition }); } },
        editPost: { PluginDocumentSettingPanel: 'Panel' },
        element: {
            createElement: element,
            useState(initial) { const i = slot++; return [slots[i] ?? initial, value => { slots[i] = value; }]; },
            useEffect() {}
        },
        data: {
            useSelect() { return { id: 9, dirty, saving: false }; }
        },
        components: {
            Button: 'Button', TextControl: 'TextControl',
            SelectControl: 'SelectControl', Notice: 'Notice'
        },
        apiFetch() { return Promise.resolve(sharing); }
    };

    vm.runInNewContext(script, { wp }, { timeout: 1000 });
    assert.equal(configured.length, 1, 'Sharing remains a separate Gutenberg plugin');
    assert.equal(configured[0].name, 'cb-notes-sharing');
    const tree = configured[0].definition.render();
    const nodes = [];
    function visit(node) {
        if (Array.isArray(node)) return node.forEach(visit);
        if (node === null || typeof node !== 'object') return;
        nodes.push(node);
        (node.children || []).forEach(visit);
    }
    visit(tree);
    function readText(node) {
        if (Array.isArray(node)) return node.map(readText).join(' ');
        if (typeof node === 'string' || typeof node === 'number') return String(node);
        if (!node || !node.children) return '';
        return node.children.map(readText).join(' ');
    }
    const text = readText(tree);
    const buttons = nodes.filter(node => node.type === 'Button');
    const hasButton = name => buttons.some(button => button.children.includes(name));
    const getButton = name => buttons.find(button => button.children.includes(name));
    return { text, buttons, hasButton, getButton, nodes };
}

let view = renderSharing({ mode: 'private', secure_available: false }, false);
assert.match(view.text, /Sharing status:\s+Private/, 'Private state explained');
assert.match(view.text, /Saving or publishing in Gutenberg keeps the note private/, 'Save does not share');
assert.ok(view.hasButton('Make public…'), 'Explicit public share action retained');
assert.equal(view.hasButton('Create secure link'), false, 'No Secure Share upsell when missing');

view = renderSharing({ mode: 'private', secure_available: true }, true);
assert.match(view.text, /Save your changes before creating a shared copy/, 'Save first guidance');
assert.equal(view.getButton('Make public…').props.disabled, true, 'Unsaved post cannot publish copy');
assert.equal(view.getButton('Create secure link').props.disabled, true, 'Unsaved post cannot issue secure link');

view = renderSharing({ mode: 'public', public_url: 'https://example.test/share' }, true);
assert.match(view.text, /Public copy active/, 'Public share state explained');
assert.match(view.text, /Saving changes will not update the frozen shared copy/, 'Frozen snapshot explained');
assert.match(view.text, /You can still stop sharing/, 'Revocation explained during unsaved edits');
assert.ok(view.hasButton('Stop sharing'), 'Revocation remains available');
assert.equal(view.getButton('Stop sharing').props.disabled, false, 'Revocation not blocked by dirty changes');
assert.equal(view.hasButton('Make public…'), false, 'No duplicate public action while active');

view = renderSharing({ mode: 'secure' }, false);
assert.match(view.text, /Secure copy active/, 'Secure copy state explained');
assert.ok(view.hasButton('Stop sharing'), 'Secure share revocation stays available');

console.log('PASS JS: tests/gutenberg-sharing-guidance.cjs');
