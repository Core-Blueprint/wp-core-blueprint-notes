/* Personal Notes P2: page-local selection and authenticated bulk actions.
 * No cross-page selection or destructive fallback without Base confirmation. */
(function () {
    'use strict';

    function init() {
        var root = document.querySelector('[data-cb-notes-bulk-nonce]');
        var toolbar = document.querySelector('[data-cb-notes-bulk-toolbar]');
        var selectAll = document.querySelector('[data-cb-notes-select-all]');
        var boxes = Array.from(document.querySelectorAll('[data-cb-notes-select-id]'));
        var resultBox = document.querySelector('[data-cb-notes-bulk-result]');
        var resultMessage = document.querySelector('[data-cb-notes-bulk-message]');
        var clear = document.querySelector('[data-cb-notes-bulk-clear]');
        var countLabel = document.querySelector('[data-cb-notes-selection-count]');
        var refresh = document.querySelector('[data-cb-notes-bulk-refresh]');
        var actions = Array.from(document.querySelectorAll('[data-cb-notes-bulk-action]'));
        var endpoint = root && root.getAttribute('data-cb-notes-ajax-url');
        var nonce = root && root.getAttribute('data-cb-notes-bulk-nonce');
        if (!root || !toolbar || !selectAll || !resultBox || !resultMessage ||
            !clear || !countLabel || !refresh || boxes.length < 1 || boxes.length > 20 ||
            !endpoint || !nonce || typeof window.fetch !== 'function' ||
            typeof URLSearchParams === 'undefined') {
            return;
        }

        var busy = false;

        function selected() {
            return boxes.filter(function (box) { return box.checked; });
        }

        function draw() {
            var count = selected().length;
            countLabel.textContent = count + (count === 1 ? ' selected' : ' selected');
            toolbar.hidden = count === 0;
            selectAll.checked = count === boxes.length;
            selectAll.indeterminate = count > 0 && count < boxes.length;
            boxes.forEach(function (box) { box.disabled = busy; });
            selectAll.disabled = busy;
            clear.disabled = busy;
            actions.forEach(function (button) {
                var operation = button.getAttribute('data-cb-notes-bulk-action');
                var field = operation === 'notebook'
                    ? document.querySelector('[data-cb-notes-bulk-notebook]')
                    : operation === 'category'
                    ? document.querySelector('[data-cb-notes-bulk-category]') : null;
                button.disabled = busy || count === 0 ||
                    (field !== null && (!field || field.value === '__choose__'));
            });
        }

        function showResult(message) {
            resultMessage.textContent = message;
            resultBox.hidden = !message;
        }

        function showError(message) {
            // Existing Library alert region announces errors to assistive tech.
            var error = document.querySelector('.cb-notes-library__feedback');
            if (!error) return;
            error.textContent = message;
            error.hidden = !message;
        }

        function clearSelection() {
            boxes.forEach(function (box) { box.checked = false; });
            draw();
        }

        boxes.forEach(function (box) {
            var wrapper = box.closest('.cb-notes-library__select');
            if (wrapper) wrapper.hidden = false;
            box.addEventListener('change', draw);
        });
        var allWrapper = selectAll.closest('.cb-notes-library__select-all');
        if (allWrapper) allWrapper.hidden = false;
        selectAll.addEventListener('change', function () {
            if (busy) return;
            boxes.forEach(function (box) { box.checked = selectAll.checked; });
            draw();
        });
        clear.addEventListener('click', clearSelection);
        ['[data-cb-notes-bulk-notebook]', '[data-cb-notes-bulk-category]'].forEach(function (selector) {
            var field = document.querySelector(selector);
            if (field) field.addEventListener('change', draw);
        });
        refresh.addEventListener('click', function () { window.location.reload(); });

        function execute(operation, ids, target) {
            var payload = new URLSearchParams();
            payload.set('action', 'cb_notes_bulk');
            payload.set('_ajax_nonce', nonce);
            payload.set('operation', operation);
            payload.set('target', target);
            ids.forEach(function (id) { payload.append('ids[]', id); });

            busy = true;
            draw();
            showResult('');
            showError('');

            return window.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload.toString()
            }).then(function (response) { return response.json(); }).then(function (response) {
                if (!response || response.success !== true || !response.data) {
                    throw new Error(response && response.data && response.data.message
                        ? response.data.message : 'The selected notes could not be updated.');
                }
                var outcome = response.data;
                if (outcome.requested !== ids.length ||
                    !Array.isArray(outcome.updated_ids) || !Array.isArray(outcome.failed_ids) ||
                    outcome.updated !== outcome.updated_ids.length ||
                    outcome.failed !== outcome.failed_ids.length ||
                    outcome.updated + outcome.failed !== ids.length) {
                    throw new Error('Unexpected bulk response. Refresh results before trying again.');
                }
                var returnedIds = outcome.updated_ids.concat(outcome.failed_ids).map(String);
                if (new Set(returnedIds).size !== ids.length ||
                    !returnedIds.every(function (id) { return ids.indexOf(id) !== -1; })) {
                    throw new Error('Unexpected note IDs in bulk response. Refresh results before retrying.');
                }
                // Use existing, already-visible note labels for per-note failure
                // feedback; never interpolate untrusted text as HTML.
                var failedNames = outcome.failed_ids.map(function (id) {
                    var box = boxes.find(function (item) {
                        return item.getAttribute('data-cb-notes-select-id') === String(id);
                    });
                    return box ? (box.getAttribute('aria-label') || ('Note #' + id))
                        .replace(/^Select note: /, '') : ('Note #' + id);
                });
                // Server already applied authorized operations. Do not invent a
                // local sort, count, category label or pagination state.
                clearSelection();
                showResult(outcome.updated + ' updated; ' + outcome.failed + ' failed.' +
                    (failedNames.length ? ' Not updated: ' + failedNames.join(', ') + '.' : '') +
                    ' Refresh results to see the latest Library.');
            }).catch(function (error) {
                showError(error && error.message
                    ? error.message : 'Bulk action status unknown. Refresh before retrying.');
            }).finally(function () {
                busy = false;
                draw();
            });
        }

        actions.forEach(function (button) {
            button.addEventListener('click', function () {
                if (busy) return;
                var ids = selected().map(function (box) { return box.getAttribute('data-cb-notes-select-id'); });
                var operation = button.getAttribute('data-cb-notes-bulk-action');
                if (!ids.length || ids.length > 20 ||
                    ['notebook', 'category', 'trash'].indexOf(operation) < 0) return;

                var field = operation === 'notebook'
                    ? document.querySelector('[data-cb-notes-bulk-notebook]')
                    : document.querySelector('[data-cb-notes-bulk-category]');
                var target = operation === 'trash' ? '' : (field && field.value);
                if (typeof target !== 'string' || target === '__choose__') return;

                if (operation !== 'trash') {
                    execute(operation, ids, target);
                    return;
                }

                // Fail closed: dangerous bulk mutation *always* requires Base.
                var modal = window.cbCore && window.cbCore.modal;
                if (!modal || typeof modal.show !== 'function') {
                    showError('The Core Blueprint confirmation is unavailable. Nothing was moved to Trash.');
                    return;
                }
                busy = true;
                draw();
                showError('');
                modal.show({
                    title: 'Move selected notes to Trash?',
                    body: 'You selected ' + ids.length +
                        ' private notes. You can restore them from WordPress Trash, but existing share links will be revoked.',
                    confirmVariant: 'danger',
                    confirmLabel: 'Move to Trash',
                    cancelLabel: 'Cancel'
                }).then(function (confirmed) {
                    if (confirmed === true) {
                        // Release modal lock; execute immediately reapplies the lock.
                        busy = false;
                        return execute('trash', ids, '');
                    }
                    return null;
                }).catch(function () {
                    showError('Confirmation failed. Nothing was requested from Trash.');
                }).finally(function () {
                    busy = false;
                    draw();
                });
            });
        });
        draw();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
