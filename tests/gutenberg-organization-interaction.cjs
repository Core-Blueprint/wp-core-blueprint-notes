'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/editor.js'), 'utf8');
const id = n => String(n).repeat(32);
const initialCatalog = {
    notebook: [{ id: id('a'), name: 'Projects' }],
    category: [{ id: id('b'), name: 'Meetings' }],
    tag: [{ id: id('c'), name: 'Planning' }]
};
function harness(existing = {}) {
    const catalog = structuredClone(initialCatalog);
    const meta = Object.assign({
        cb_notes_notebook_id: '',
        cb_notes_category_id: '',
        cb_notes_tag_ids: []
    }, existing);
    const plugins = [];
    const writes = [];
    const slots = [];
    const depsSeen = [];
    let hook = 0;
    let pending = [];
    let tree;

    function el(type, props, ...children) {
        return { type, props: props || {}, children: children.flat(Infinity) };
    }
    function useState(initial) {
        const current = hook++;
        if (!(current in slots)) slots[current] = initial;
        return [slots[current], value => {
            slots[current] = typeof value === 'function' ? value(slots[current]) : value;
        }];
    }
    function useEffect(fn, deps) {
        const current = hook++;
        const serialized = JSON.stringify(deps);
        if (depsSeen[current] !== serialized) {
            depsSeen[current] = serialized;
            pending.push(fn);
        }
    }
    const wp = {
        plugins: { registerPlugin(name, config) { plugins.push({ name, config }); } },
        editPost: { PluginDocumentSettingPanel: 'PluginDocumentSettingPanel' },
        element: { createElement: el, useState, useEffect },
        data: {
            select() {
                return { getEditedPostAttribute: () => meta };
            },
            useSelect(callback) {
                return callback(() => ({ getEditedPostAttribute: () => meta }));
            },
            useDispatch() {
                return { editPost(change) {
                    Object.assign(meta, change.meta);
                    writes.push(change.meta);
                } };
            }
        },
        components: {
            SelectControl: 'SelectControl', Button: 'Button',
            TextControl: 'TextControl', Notice: 'Notice'
        },
        apiFetch({ method, data }) {
            if (method === 'POST') {
                if (!data || data.action !== 'create') return Promise.reject(new Error('Unknown action'));
                if (!catalog[data.kind]) return Promise.reject(new Error('Unknown kind'));
                const next = catalog[data.kind].length + 1;
                catalog[data.kind].push({ id: String(next).padStart(32, '0'), name: data.name });
            }
            return Promise.resolve(structuredClone(catalog));
        }
    };
    vm.runInNewContext(source, { wp, window: {} }, { timeout: 1000 });
    assert.equal(plugins.length, 1, 'Only one Gutenberg organization plugin');
    assert.equal(plugins[0].name, 'cb-notes-organize');

    function paint() {
        hook = 0;
        pending = [];
        tree = plugins[0].config.render();
        pending.forEach(fn => fn());
        return tree;
    }
    function walk(node, predicate, found = []) {
        if (Array.isArray(node)) {
            node.forEach(item => walk(item, predicate, found));
            return found;
        }
        if (!node || typeof node !== 'object') return found;
        if (predicate(node)) found.push(node);
        (node.children || []).forEach(item => walk(item, predicate, found));
        return found;
    }
    function label(node) {
        if (Array.isArray(node)) return node.map(label).join('');
        if (typeof node === 'string' || typeof node === 'number') return String(node);
        if (!node || !node.children) return '';
        return node.children.map(label).join('');
    }
    function field(type, identifier) {
        const found = walk(tree, node =>
            node.type === type && (node.props.label === identifier || label(node) === identifier)
        );
        assert.equal(found.length, 1, 'Missing/duplicated ' + type + ': ' + identifier);
        return found[0];
    }
    return { meta, writes, paint, field, walk: predicate => walk(tree, predicate), plugins };
}
const settle = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    let run = harness();
    run.paint();
    await settle(); // Loads current user's catalog.
    run.paint();
    assert.equal(run.plugins[0].config.render instanceof Function, true);
    assert.equal(run.walk(n => n.type === 'SelectControl' && n.props.label === 'Notebook').length, 1);
    assert.equal(run.walk(n => n.type === 'SelectControl' && n.props.label === 'Category').length, 0,
        'Beginner sees only Notebook by default');

    run.field('SelectControl', 'Notebook').props.onChange(id('a'));
    assert.equal(run.meta.cb_notes_notebook_id, id('a'), 'Notebook saves to native post meta');

    run.paint();
    run.field('Button', 'Show categories and tags').props.onClick();
    run.paint();
    assert.equal(run.field('SelectControl', 'Category').props.label, 'Category');
    run.field('SelectControl', 'Category').props.onChange(id('b'));
    assert.equal(run.meta.cb_notes_category_id, id('b'), 'Category saves to native post meta');

    run.paint();
    run.field('SelectControl', 'Add existing tag').props.onChange(id('c'));
    run.paint();
    run.field('Button', 'Add tag').props.onClick();
    assert.deepEqual(Array.from(run.meta.cb_notes_tag_ids), [id('c')], 'Tag persists to native meta');

    run.paint();
    assert.equal(run.field('Button', 'Planning ×').props['aria-label'], 'Remove tag Planning');
    run.field('Button', 'Planning ×').props.onClick();
    assert.equal(run.meta.cb_notes_tag_ids.length, 0, 'Tag removal persists');

    run.paint();
    run.field('Button', 'New notebook').props.onClick();
    run.paint();
    assert.equal(run.walk(n => n.type === 'TextControl' && n.props.label === 'New notebook name').length, 1);
    run.field('TextControl', 'New notebook name').props.onChange('Ideas');
    run.paint();
    run.field('Button', 'Create notebook').props.onClick();
    // Simulate another Gutenberg sidebar control changing meta during REST.
    run.meta.cb_notes_unrelated = 'preserved';
    await settle();
    run.paint();
    assert.equal(run.meta.cb_notes_notebook_id, '00000000000000000000000000000002', 'New notebook selected');
    assert.equal(run.writes.at(-1).cb_notes_unrelated, 'preserved', 'Latest unrelated meta preserved across async creation');
    assert.equal(run.walk(n => n.type === 'TextControl' && n.props.label === 'New notebook name').length, 0,
        'Creation form closes after success');

    run = harness({ cb_notes_category_id: id('b'), cb_notes_tag_ids: [id('c')] });
    run.paint();
    await settle();
    run.paint();
    run.paint();
    assert.equal(run.walk(n => n.type === 'SelectControl' && n.props.label === 'Category').length, 1,
        'Existing classification opens advanced controls');
    assert.equal(run.walk(n => n.type === 'Button' && labelFrom(n) === 'Planning ×').length, 1,
        'Existing tags remain visible');

    run = harness({ cb_notes_tag_ids: Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(32, '0')) });
    run.paint();
    await settle();
    run.paint();
    run.paint();
    assert.equal(run.walk(n => n.type === 'Button' && labelFrom(n) === 'Add tag').length, 0,
        'Tag add unavailable at twelve');
    assert.equal(run.walk(n => n.type === 'SelectControl' && n.props.label === 'Add existing tag').length, 0,
        'Tag selector unavailable at twelve');

    function labelFrom(node) {
        return (node.children || []).filter(s => typeof s === 'string').join('');
    }

    console.log('PASS JS: tests/gutenberg-organization-interaction.cjs');
})().catch(error => { console.error('FAIL Gutenberg UX:', error); process.exitCode = 1; });
