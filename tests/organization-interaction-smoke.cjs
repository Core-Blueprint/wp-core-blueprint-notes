'use strict';

/**
 * Minimal wp.element runtime probe, exercising organizer state/actions with a
 * mocked REST backend. Real browser/Base accessibility remains a staging gate.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.join(__dirname, '..', 'assets/organization-admin.js'), 'utf8');
const store = { notebook: [], category: [], tag: [] };
const sent = [];
const modalCalls = [];
let acceptRemoval = false;
let component = null;
let tree = null;
let cursor = 0;
const slots = [];
const effects = [];
let pending = [];
const listeners = {};
const location = { href: 'https://example.test/wp-admin/edit.php?post_type=cb_note&page=cb-notes-organize' };

function createElement(type, props, ...children) {
    return { type, props: props || {}, children: children.flat(Infinity) };
}
function useState(initial) {
    const i = cursor++;
    if (!(i in slots)) slots[i] = typeof initial === 'function' ? initial() : initial;
    return [slots[i], value => {
        slots[i] = typeof value === 'function' ? value(slots[i]) : value;
    }];
}
function useRef(initial) {
    const i = cursor++;
    if (!(i in slots)) slots[i] = { current: initial };
    return slots[i];
}
function useEffect(fn, deps) {
    const i = cursor++;
    const serialized = JSON.stringify(deps);
    if (effects[i] !== serialized) {
        effects[i] = serialized;
        pending.push(fn);
    }
}
function repaint() {
    cursor = 0;
    pending = [];
    tree = component();
    pending.forEach(fn => fn());
    return tree;
}
function walk(node, predicate, result = []) {
    if (!node || typeof node !== 'object') return result;
    if (predicate(node)) result.push(node);
    (node.children || []).forEach(child => walk(child, predicate, result));
    return result;
}
function flattenText(node) {
    if (typeof node === 'string' || typeof node === 'number') return String(node);
    if (!node || typeof node !== 'object') return '';
    return (node.children || []).map(flattenText).join('');
}
function control(type, text) {
    const found = walk(tree, n => n.type === type && flattenText(n) === text);
    assert.ok(found.length, 'Missing ' + type + ': ' + text);
    return found[0];
}
function field(label) {
    const found = walk(tree, n => n.type === 'TextControl' && n.props.label === label);
    assert.ok(found.length, 'Missing field ' + label);
    return found[0];
}
async function settle() {
    await new Promise(resolve => setImmediate(resolve));
    repaint();
}
const windowStub = {
    location,
    history: {
        state: null,
        replaceState(state, title, href) { location.href = href; }
    },
    addEventListener(name, callback) { listeners[name] = callback; },
    removeEventListener(name) { delete listeners[name]; },
    cbCore: { modal: { show(payload) {
        modalCalls.push(payload);
        return Promise.resolve(acceptRemoval);
    } } }
};
const wp = {
    element: {
        createElement, useState, useEffect, useRef,
        render(node) { component = node.type; repaint(); }
    },
    components: { Button: 'Button', TextControl: 'TextControl' },
    apiFetch: async function (args) {
        if (!args.method) return structuredClone(store);
        sent.push(structuredClone(args.data));
        const { action, kind, name, id } = args.data;
        if (action === 'create') store[kind].push({ id: 'opaque' + sent.length, name });
        if (action === 'rename') store[kind].find(item => item.id === id).name = name;
        if (action === 'delete') store[kind] = store[kind].filter(item => item.id !== id);
        return structuredClone(store);
    }
};
vm.runInNewContext(code, {
    wp, window: windowStub, document: { getElementById: () => ({}) },
    URL
}, { filename: 'organization-admin.js' });

(async () => {
    await settle();
    assert.equal(walk(tree, n => n.type === 'a').length, 3, 'Three navigation links');
    assert.equal(walk(tree, n => n.type === 'a' && n.props['aria-current'] === 'page').length, 1);
    assert.equal(control('Button', 'Add new').props.disabled, false);
    assert.equal(walk(tree, n => n.type === 'TextControl').length, 0, 'Creation is hidden by default');
    console.log('PASS: accessible Base tabs and hidden contextual creation');

    const category = walk(tree, n => n.type === 'a' && flattenText(n) === 'Categories')[0];
    assert.ok(category.props.href.includes('section=category'));
    category.props.onClick({ preventDefault() {} });
    repaint();
    assert.ok(location.href.includes('section=category'));
    assert.ok(flattenText(tree).includes('No categories yet'));
    console.log('PASS: category tab switches in-place and retains deep link');

    control('Button', 'Add new').props.onClick();
    repaint();
    assert.ok(field('Name'));
    field('Name').props.onChange('Meetings');
    repaint();
    assert.equal(control('Button', 'Create category').props.disabled, false);
    control('Button', 'Create category').props.onClick();
    await settle();
    assert.deepEqual(sent.at(-1), { action: 'create', kind: 'category', id: '', name: 'Meetings' });
    assert.ok(flattenText(tree).includes('Meetings'));
    assert.equal(walk(tree, n => n.type === 'TextControl').length, 0, 'Creation form closes after save');
    console.log('PASS: Add new opens contextually, persists category and closes');

    control('Button', 'Rename').props.onClick();
    repaint();
    field('Rename Meetings').props.onChange('Meetings 2026');
    repaint();
    control('Button', 'Save').props.onClick();
    await settle();
    assert.equal(store.category[0].name, 'Meetings 2026');
    assert.ok(flattenText(tree).includes('Renamed category successfully.'));
    console.log('PASS: inline rename and compact feedback');

    control('Button', 'Remove').props.onClick();
    await settle();
    assert.equal(modalCalls.at(-1).confirmVariant, 'danger');
    assert.ok(modalCalls.at(-1).confirmCheck);
    assert.equal(store.category.length, 1, 'Canceled confirmation must not delete');
    acceptRemoval = true;
    control('Button', 'Remove').props.onClick();
    await settle();
    assert.equal(store.category.length, 0);
    console.log('PASS: cancellation is safe; confirmed removal uses Base danger modal');

    const tags = walk(tree, n => n.type === 'a' && flattenText(n) === 'Tags')[0];
    tags.props.onClick({ preventDefault() {} });
    repaint();
    assert.ok(location.href.includes('section=tag'));
    assert.ok(flattenText(tree).includes('No tags yet'));
    console.log('PASS: Tags navigation works after CRUD actions');
    console.log('Notes Organize interaction smoke: PASS');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
