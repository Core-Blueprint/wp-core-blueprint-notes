'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/library-admin.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function boot(kind, view, response, enableFetch = true) {
    const attrs = {
        'data-cb-notes-marker': kind,
        'data-cb-notes-id': '41',
        'data-cb-notes-nonce': 'nonce-for-41',
        'aria-pressed': 'false'
    };
    const classes = new Set(['cb-core-button--secondary']);
    const callbacks = {};
    const feedback = { hidden: true, textContent: '' };
    const sent = [];
    let reloaded = 0;
    const button = {
        hidden: true,
        disabled: false,
        title: '',
        classList: { toggle(value, active) {
            if (active) classes.add(value);
            else classes.delete(value);
        } },
        getAttribute(key) { return attrs[key] || null; },
        setAttribute(key, value) { attrs[key] = value; },
        addEventListener(event, listener) { callbacks[event] = listener; }
    };
    const root = { getAttribute(key) {
        return key === 'data-cb-notes-ajax-url' ? '/wp-admin/admin-ajax.php' :
            key === 'data-cb-notes-view' ? view : '';
    } };
    const document = {
        readyState: 'complete',
        querySelector(selector) {
            return selector === '[data-cb-notes-ajax-url]' ? root : feedback;
        },
        querySelectorAll(selector) {
            return selector === '[data-cb-notes-marker]' ? [button] : [];
        }
    };
    const window = {
        location: { reload() { reloaded++; } }
    };
    if (enableFetch) window.fetch = (url, options) => {
        sent.push({ url, options });
        return response instanceof Error ? Promise.reject(response)
            : Promise.resolve({ json: () => Promise.resolve(response) });
    };
    vm.runInNewContext(source, { window, document, URLSearchParams }, { timeout: 1000 });
    return {
        button, attrs, classes, feedback, sent,
        click() { callbacks.click(); },
        get reloads() { return reloaded; }
    };
}

(async () => {
    let widget = boot('favorite', '', { success: true, data: { favorite: true, pinned: false } });
    assert.equal(widget.button.hidden, false, 'Favorite button is progressively enabled');
    widget.click();
    await flush();
    assert.equal(widget.sent.length, 1, 'One AJAX toggle request');
    const params = new URLSearchParams(widget.sent[0].options.body);
    assert.equal(params.get('action'), 'cb_notes_mark', 'Server marker action used');
    assert.equal(params.get('note_id'), '41', 'Only selected note ID sent');
    assert.equal(params.get('_ajax_nonce'), 'nonce-for-41', 'CSRF nonce included');
    assert.equal(params.get('kind'), 'favorite');
    assert.equal(params.get('enabled'), '1');
    assert.equal(widget.attrs['aria-pressed'], 'true', 'Toggle accessible state updated');
    assert.equal(widget.attrs['aria-label'], 'Remove from favorites');
    assert.equal(widget.classes.has('cb-core-button--primary'), true, 'Active marker uses Base primary');
    assert.equal(widget.classes.has('cb-core-button--secondary'), false);
    assert.equal(widget.reloads, 0, 'Favorite toggles in All Notes without reload');

    widget = boot('pinned', '', { success: true, data: { favorite: false, pinned: true } });
    widget.click();
    await flush();
    assert.equal(widget.reloads, 1, 'Pin reloads server page to recalculate sort/pagination');

    widget = boot('favorite', 'favorites', { success: true, data: { favorite: true, pinned: false } });
    widget.click();
    await flush();
    assert.equal(widget.reloads, 1, 'Favorite view reloads to recalculate filtering');

    widget = boot('favorite', '', { success: false, data: { message: 'Access denied.' } });
    widget.click();
    await flush();
    assert.equal(widget.attrs['aria-pressed'], 'false', 'Server denial never changes marker');
    assert.match(widget.feedback.textContent, /Access denied/, 'Server denial announced');
    assert.equal(widget.button.disabled, false, 'Server denial restores control');

    widget = boot('favorite', '', new Error('Network unavailable'));
    widget.click();
    await flush();
    assert.equal(widget.attrs['aria-pressed'], 'false', 'Network error leaves state unchanged');
    assert.match(widget.feedback.textContent, /Network unavailable/, 'Network error announced');

    widget = boot('favorite', '', null, false);
    assert.equal(widget.button.hidden, true, 'Missing fetch keeps marker fail-closed');

    console.log('PASS JS: tests/library-markers-interaction.cjs');
})().catch(error => {
    console.error('FAIL P1 marker interaction:', error);
    process.exitCode = 1;
});
