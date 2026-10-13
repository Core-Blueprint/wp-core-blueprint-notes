'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.join(__dirname, '../assets/library-admin.js'), 'utf8');

function boot(modal) {
    const callbacks = {};
    const moved = [];
    const feedback = { hidden: true, textContent: '' };
    const button = {
        hidden: true,
        disabled: false,
        getAttribute(key) {
            return key === 'data-cb-notes-trash-url' ? '/wp-admin/post.php?action=trash&_wpnonce=test' : null;
        },
        addEventListener(type, callback) {
            callbacks[type] = callback;
        },
    };
    const window = {
        cbCore: modal ? { modal } : undefined,
        location: { assign(url) { moved.push(url); } },
    };
    const document = {
        readyState: 'complete',
        querySelectorAll() { return [button]; },
        querySelector() { return feedback; },
    };
    vm.runInNewContext(code, { window, document }, { timeout: 1000 });
    return { window, button, feedback, moved, click: () => callbacks.click() };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    let calls = 0;
    let modalArgs = null;
    let widget = boot({
        show(options) {
            ++calls;
            modalArgs = options;
            return Promise.resolve(false);
        },
    });
    assert.equal(widget.button.hidden, false, 'JS-enhanced Trash control is displayed');
    widget.click();
    await flush();
    assert.equal(calls, 1, 'Base modal opened once');
    assert.equal(modalArgs.confirmVariant, 'danger');
    assert.equal(modalArgs.confirmLabel, 'Move to Trash');
    assert.equal(widget.moved.length, 0, 'Cancel never navigates');
    assert.equal(widget.button.disabled, false, 'Cancel restores the control');

    widget = boot({ show() { return Promise.resolve(true); } });
    widget.click();
    await flush();
    assert.equal(widget.moved.length, 1, 'Confirmed Trash navigates once');
    assert.ok(widget.moved[0].includes('action=trash'), 'WordPress trash route kept');

    widget = boot(null);
    widget.click();
    await flush();
    assert.equal(widget.button.hidden, true, 'Missing Base modal disables the action');
    assert.equal(widget.moved.length, 0, 'Missing Base modal cannot navigate');
    assert.match(widget.feedback.textContent, /unavailable/, 'Action failure announced');

    widget = boot(null);
    widget.window.cbCore = { modal: { show() { return Promise.resolve(true); } } };
    widget.click();
    await flush();
    assert.equal(widget.moved.length, 1, 'Lazy module availability works after DOM-ready');

    widget = boot({ show() { return Promise.reject(new Error('modal failed')); } });
    widget.click();
    await flush();
    assert.equal(widget.moved.length, 0, 'Modal exception cannot navigate');
    assert.match(widget.feedback.textContent, /Could not complete/, 'Modal exception announced');
    assert.equal(widget.button.disabled, false, 'Modal exception restores control');

    let resolveModal = null;
    calls = 0;
    widget = boot({ show() {
        ++calls;
        return new Promise(resolve => { resolveModal = resolve; });
    } });
    widget.click();
    widget.click();
    assert.equal(calls, 1, 'Double click does not open another modal');
    resolveModal(true);
    await flush();
    assert.equal(widget.moved.length, 1, 'Confirmation navigates only once');

    console.log('PASS JS: tests/library-interaction-smoke.cjs');
})().catch(error => {
    console.error('FAIL library interaction:', error);
    process.exitCode = 1;
});
