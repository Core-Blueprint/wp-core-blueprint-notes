'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../assets/library-bulk.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function boot(options = {}) {
    const checkboxes = [11, 12].map(id => {
        const listeners = {};
        const wrapper = { hidden: true };
        return {
            checked: false, disabled: false, listeners, wrapper,
            getAttribute(name) { return name === 'data-cb-notes-select-id' ? String(id) : null; },
            closest() { return wrapper; },
            addEventListener(type, listener) { listeners[type] = listener; },
            toggle(checked) { this.checked = checked; listeners.change(); }
        };
    });
    function control(attributes = {}) {
        const listeners = {};
        return {
            hidden: true, checked: false, indeterminate: false,
            disabled: false, textContent: '', value: '', listeners,
            wrapper: { hidden: true },
            closest() { return this.wrapper; },
            addEventListener(type, fn) { listeners[type] = fn; },
            getAttribute(key) { return attributes[key] ?? null; },
            click() { listeners.click(); },
            change(checked) { this.checked = checked; listeners.change(); }
        };
    }
    const root = control({
        'data-cb-notes-ajax-url': '/wp-admin/admin-ajax.php',
        'data-cb-notes-bulk-nonce': 'bulk-nonce'
    });
    const toolbar = control(), all = control(), result = control(), message = control();
    const clear = control(), label = control(), refresh = control(), error = control();
    const notebook = control(), category = control();
    notebook.value = '__choose__';
    category.value = '__choose__';
    const actions = ['notebook', 'category', 'trash'].map(name =>
        control({ 'data-cb-notes-bulk-action': name })
    );
    const map = new Map([
        ['[data-cb-notes-bulk-nonce]', root],
        ['[data-cb-notes-bulk-toolbar]', toolbar],
        ['[data-cb-notes-select-all]', all],
        ['[data-cb-notes-bulk-result]', result],
        ['[data-cb-notes-bulk-message]', message],
        ['[data-cb-notes-bulk-clear]', clear],
        ['[data-cb-notes-selection-count]', label],
        ['[data-cb-notes-bulk-refresh]', refresh],
        ['[data-cb-notes-bulk-notebook]', notebook],
        ['[data-cb-notes-bulk-category]', category],
        ['.cb-notes-library__feedback', error]
    ]);
    const document = {
        readyState: 'complete',
        querySelector(selector) { return map.get(selector) || null; },
        querySelectorAll(selector) {
            return selector === '[data-cb-notes-select-id]' ? checkboxes
                : selector === '[data-cb-notes-bulk-action]' ? actions : [];
        }
    };
    const requests = [];
    let reloads = 0, confirmations = 0, lastModal = null;
    const window = {
        location: { reload() { reloads++; } }
    };
    if (options.modal !== 'missing') {
        window.cbCore = { modal: { show(settings) {
            confirmations++;
            lastModal = settings;
            return options.confirm === 'error' ? Promise.reject(new Error('Modal failure'))
                : Promise.resolve(options.confirm === true);
        } } };
    }
    if (options.fetch !== 'missing') {
        window.fetch = (url, optionsUsed) => {
            requests.push({ url, ...optionsUsed });
            if (options.fetch === 'reject') return Promise.reject(new Error('Network failure'));
            return Promise.resolve({ json() { return Promise.resolve(
                options.response || {
                    success: true,
                    data: {
                        requested: optionsUsed.body.includes('ids%5B%5D=12') ? 2 : 1,
                        updated: optionsUsed.body.includes('ids%5B%5D=12') ? 2 : 1,
                        failed: 0,
                        updated_ids: optionsUsed.body.includes('ids%5B%5D=12') ? [11,12] : [11],
                        failed_ids: []
                    }
                }
            ); } });
        };
    }
    vm.runInNewContext(script, { document, window, URLSearchParams }, { timeout: 1000 });

    return {
        checkboxes, toolbar, all, result, message, label, error,
        clear, refresh, notebook, category, actions, requests,
        get reloads() { return reloads; },
        get confirmations() { return confirmations; },
        get lastModal() { return lastModal; }
    };
}
function selectFirst(widget) { widget.checkboxes[0].toggle(true); }
(async () => {
    let w = boot();
    assert.equal(w.toolbar.hidden, true, 'Toolbar starts hidden');
    assert.equal(w.checkboxes[0].wrapper.hidden, false, 'Selection progressively revealed');
    assert.equal(w.all.wrapper.hidden, false, 'Select-all progressively revealed');
    selectFirst(w);
    assert.equal(w.toolbar.hidden, false, 'Toolbar appears after first selection');
    assert.equal(w.label.textContent, '1 selected', 'Accessible count');
    assert.equal(w.all.indeterminate, true, 'Select-all indicates partial selection');
    assert.equal(w.actions[0].disabled, true, 'Bulk notebook is disabled until a destination is chosen');
    w.notebook.value = 'a'.repeat(32);
    w.notebook.listeners.change();
    assert.equal(w.actions[0].disabled, false, 'Explicit notebook choice enables action');
    w.actions[0].click();
    await flush();
    assert.equal(w.requests.length, 1, 'Notebook operation uses one batched request');
    let params = new URLSearchParams(w.requests[0].body);
    assert.equal(params.get('action'), 'cb_notes_bulk');
    assert.equal(params.get('_ajax_nonce'), 'bulk-nonce');
    assert.equal(params.get('operation'), 'notebook');
    assert.equal(params.get('target'), 'a'.repeat(32));
    assert.deepEqual(params.getAll('ids[]'), ['11'], 'Only checked notes are sent');
    assert.equal(w.message.textContent, '1 updated; 0 failed. Refresh results to see the latest Library.');
    assert.equal(w.toolbar.hidden, true, 'Selection clears after server-confirmed action');
    assert.equal(w.reloads, 0, 'No forced reload after content changes');

    w = boot({response: {
        success: true, data: {
            requested: 2, updated: 1, failed: 1,
            updated_ids: [11], failed_ids: [12]
        }
    }});
    w.all.change(true);
    assert.equal(w.all.checked, true, 'Select-all checks all visible rows');
    assert.equal(w.label.textContent, '2 selected', 'Two-note selection count');
    assert.equal(w.actions[1].disabled, true, 'Category action requires intentional category choice');
    w.category.value = 'b'.repeat(32);
    w.category.listeners.change();
    w.actions[1].click();
    await flush();
    params = new URLSearchParams(w.requests[0].body);
    assert.equal(params.get('operation'), 'category');
    assert.equal(params.get('target'), 'b'.repeat(32));
    assert.deepEqual(params.getAll('ids[]'), ['11','12']);
    assert.match(w.message.textContent, /1 updated; 1 failed/, 'Partial-failure feedback is honest');
    assert.equal(w.reloads, 0, 'Partial failure is not concealed by automatic reload');
    w.refresh.click();
    assert.equal(w.reloads, 1, 'Explicit Refresh reloads server results');

    w = boot({ confirm: false });
    selectFirst(w);
    w.actions[2].click();
    await flush();
    assert.equal(w.confirmations, 1, 'Base confirmation is required');
    assert.equal(w.lastModal.confirmVariant, 'danger');
    assert.equal(w.requests.length, 0, 'Cancel cannot send destructive request');
    assert.equal(w.checkboxes[0].checked, true, 'Cancel keeps selection');

    w = boot({ confirm: true });
    selectFirst(w);
    w.actions[2].click();
    await flush();
    assert.equal(w.requests.length, 1, 'Confirm submits exactly one Trash batch');
    params = new URLSearchParams(w.requests[0].body);
    assert.equal(params.get('operation'), 'trash');
    assert.equal(params.get('target'), '');
    assert.equal(w.toolbar.hidden, true, 'Successful Trash clears selection');

    w = boot({modal: 'missing'});
    selectFirst(w);
    w.actions[2].click();
    await flush();
    assert.equal(w.requests.length, 0, 'Missing Base modal fails closed');
    assert.match(w.error.textContent, /unavailable/, 'Missing modal error announced');

    w = boot({fetch: 'reject'});
    selectFirst(w);
    w.notebook.value = 'a'.repeat(32);
    w.notebook.listeners.change();
    w.actions[0].click();
    await flush();
    assert.equal(w.checkboxes[0].checked, true, 'Network failure preserves selection');
    assert.match(w.error.textContent, /Network failure/, 'Network error is announced');
    assert.equal(w.actions[0].disabled, false, 'Controls recover after request failure');

    w = boot({fetch: 'missing'});
    assert.equal(w.checkboxes[0].wrapper.hidden, true, 'No fetch leaves all selections hidden');
    assert.equal(w.toolbar.hidden, true, 'No fetch leaves bulk actions hidden');

    console.log('PASS JS: tests/library-bulk-interaction.cjs');
})().catch(error => {
    console.error('FAIL Notes P2 bulk interaction:', error);
    process.exitCode = 1;
});
