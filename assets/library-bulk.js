/* Personal Notes P2: page-local selection and authenticated bulk actions.
 * No cross-page selection or destructive fallback without Base confirmation. */
(function () {
    'use strict';
    var __ = typeof wp !== 'undefined' && wp.i18n && typeof wp.i18n.__ === 'function'
        ? wp.i18n.__ : function (message) { return message; };
    var sprintf = typeof wp !== 'undefined' && wp.i18n && typeof wp.i18n.sprintf === 'function'
        ? wp.i18n.sprintf : function (format) {
            var args = Array.prototype.slice.call(arguments, 1);
            return format.replace(/%\d*\$?[sd]/g, function () { return String(args.shift()); });
        };
    var _n = typeof wp !== 'undefined' && wp.i18n && typeof wp.i18n._n === 'function'
        ? wp.i18n._n : function (one, many, count) { return count === 1 ? one : many; };


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
        var operationSelect = document.querySelector('[data-cb-notes-bulk-operation]');
        var notebook = document.querySelector('[data-cb-notes-bulk-notebook]');
        var category = document.querySelector('[data-cb-notes-bulk-category]');
        var notebookGroup = document.querySelector('[data-cb-notes-bulk-target="notebook"]');
        var categoryGroup = document.querySelector('[data-cb-notes-bulk-target="category"]');
        var apply = document.querySelector('[data-cb-notes-bulk-action="apply"]');
        var trash = document.querySelector('[data-cb-notes-bulk-action="trash"]');
        var endpoint = root && root.getAttribute('data-cb-notes-ajax-url');
        var nonce = root && root.getAttribute('data-cb-notes-bulk-nonce');
        if (!root || !toolbar || !selectAll || !resultBox || !resultMessage ||
            !clear || !countLabel || !refresh || !operationSelect || !notebook ||
            !category || !notebookGroup || !categoryGroup || !apply || !trash ||
            boxes.length < 1 || boxes.length > 20 ||
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
            countLabel.textContent = sprintf(_n('%d selected', '%d selected', count, 'core-blueprint-notes'), count);
            toolbar.hidden = count === 0;
            selectAll.checked = count === boxes.length;
            selectAll.indeterminate = count > 0 && count < boxes.length;
            boxes.forEach(function (box) { box.disabled = busy; });
            selectAll.disabled = busy;
            clear.disabled = busy;
            var operation = operationSelect.value;
            var validOperation = operation === 'notebook' || operation === 'category';
            notebookGroup.hidden = operation !== 'notebook';
            categoryGroup.hidden = operation !== 'category';
            operationSelect.disabled = busy;
            notebook.disabled = busy;
            category.disabled = busy;
            apply.disabled = busy || count === 0 || !validOperation ||
                (operation === 'notebook' ? notebook.value : category.value) === '__choose__';
            trash.disabled = busy || count === 0;
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
        operationSelect.addEventListener('change', function () {
            // Switching the operation must not reuse a stale previous target.
            notebook.value = '__choose__';
            category.value = '__choose__';
            draw();
        });
        notebook.addEventListener('change', draw);
        category.addEventListener('change', draw);
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
                        ? response.data.message : __('The selected notes could not be updated.', 'core-blueprint-notes'));
                }
                var outcome = response.data;
                if (outcome.requested !== ids.length ||
                    !Array.isArray(outcome.updated_ids) || !Array.isArray(outcome.failed_ids) ||
                    outcome.updated !== outcome.updated_ids.length ||
                    outcome.failed !== outcome.failed_ids.length ||
                    outcome.updated + outcome.failed !== ids.length) {
                    throw new Error(__('Unexpected bulk response. Refresh results before trying again.', 'core-blueprint-notes'));
                }
                var returnedIds = outcome.updated_ids.concat(outcome.failed_ids).map(String);
                if (new Set(returnedIds).size !== ids.length ||
                    !returnedIds.every(function (id) { return ids.indexOf(id) !== -1; })) {
                    throw new Error(__('Unexpected note IDs in bulk response. Refresh results before retrying.', 'core-blueprint-notes'));
                }
                // Use existing, already-visible note labels for per-note failure
                // feedback; never interpolate untrusted text as HTML.
                var failedNames = outcome.failed_ids.map(function (id) {
                    var box = boxes.find(function (item) {
                        return item.getAttribute('data-cb-notes-select-id') === String(id);
                    });
                    return box ? (box.getAttribute('aria-label') || (sprintf(__('Note #%s', 'core-blueprint-notes'), id)))
                        .replace(/^Select note: /, '') : (sprintf(__('Note #%s', 'core-blueprint-notes'), id));
                });
                // Server already applied authorized operations. Do not invent a
                // local sort, count, category label or pagination state.
                clearSelection();
                operationSelect.value = '__choose__';
                notebook.value = '__choose__';
                category.value = '__choose__';
                draw();
                showResult(sprintf(__('%1$d updated; %2$d failed.', 'core-blueprint-notes'), outcome.updated, outcome.failed) +
                    (failedNames.length ? ' ' + sprintf(__('Not updated: %s.', 'core-blueprint-notes'), failedNames.join(', ')) : '') +
                    ' ' + __('Refresh results to see the latest Library.', 'core-blueprint-notes'));
            }).catch(function (error) {
                showError(error && error.message
                    ? error.message : __('Bulk action status unknown. Refresh before retrying.', 'core-blueprint-notes'));
            }).finally(function () {
                busy = false;
                draw();
            });
        }

        apply.addEventListener('click', function () {
            if (busy || apply.disabled) return;
            var ids = selected().map(function (box) {
                return box.getAttribute('data-cb-notes-select-id');
            });
            var operation = operationSelect.value;
            if (!ids.length || ids.length > 20 ||
                (operation !== 'notebook' && operation !== 'category')) return;
            var target = operation === 'notebook' ? notebook.value : category.value;
            if (typeof target !== 'string' || target === '__choose__') return;
            execute(operation, ids, target);
        });

        trash.addEventListener('click', function () {
            if (busy || trash.disabled) return;
            var ids = selected().map(function (box) {
                return box.getAttribute('data-cb-notes-select-id');
            });
            if (!ids.length || ids.length > 20) return;

            // Fail closed: dangerous bulk mutation *always* requires Base.
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                showError(__('The Core Blueprint confirmation is unavailable. Nothing was moved to Trash.', 'core-blueprint-notes'));
                return;
            }
            busy = true;
            draw();
            showError('');
            modal.show({
                title: __('Move selected notes to Trash?', 'core-blueprint-notes'),
                body: sprintf(__('You selected %d private notes. You can restore them from WordPress Trash, but existing share links will be revoked.', 'core-blueprint-notes'), ids.length),
                confirmVariant: 'danger',
                confirmLabel: __('Move to Trash', 'core-blueprint-notes'),
                cancelLabel: __('Cancel', 'core-blueprint-notes')
            }).then(function (confirmed) {
                if (confirmed === true) {
                    busy = false;
                    return execute('trash', ids, '');
                }
                return null;
            }).catch(function () {
                showError(__('Confirmation failed. Nothing was requested from Trash.', 'core-blueprint-notes'));
            }).finally(function () {
                busy = false;
                draw();
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
