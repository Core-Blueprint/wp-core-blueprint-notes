/* Small progressive enhancement only. Search/filtering/paging remain server-side. */
(function () {
    'use strict';

    function report(message) {
        var feedback = document.querySelector('.cb-notes-library__feedback');
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = !message;
    }

    function init() {
        var controls = document.querySelectorAll('[data-cb-notes-trash-url]');
        controls.forEach(function (button) {
            var url = button.getAttribute('data-cb-notes-trash-url');
            if (!url) return;

            // Expose only with JavaScript; resolve the Base modal lazily at
            // click time, because WP script modules can load after DOM-ready.
            // Never navigate without an explicit Base modal confirmation.
            button.hidden = false;
            button.addEventListener('click', function () {
                if (button.disabled) return;
                var confirmation = window.cbCore && window.cbCore.modal;
                if (!confirmation || typeof confirmation.show !== 'function') {
                    button.hidden = true;
                    report('The Core Blueprint confirmation is unavailable. Nothing was moved to Trash.');
                    return;
                }
                report('');
                button.disabled = true;
                confirmation.show({
                    title: 'Move note to Trash?',
                    body: 'This moves your private note to WordPress Trash. You can restore the note later, but any active share links will be revoked.',
                    confirmVariant: 'danger',
                    confirmLabel: 'Move to Trash',
                    cancelLabel: 'Cancel'
                }).then(function (accepted) {
                    if (accepted === true) {
                        window.location.assign(url);
                    }
                }).catch(function () {
                    report('Could not complete the confirmation. Nothing was moved to Trash.');
                }).finally(function () {
                    button.disabled = false;
                });
            });
        });
        // Progressive enhancement: authenticated per-note quick markers.
        var root = document.querySelector('[data-cb-notes-ajax-url]');
        var endpoint = root && typeof root.getAttribute === 'function'
            ? root.getAttribute('data-cb-notes-ajax-url') : '';
        var view = root && typeof root.getAttribute === 'function'
            ? root.getAttribute('data-cb-notes-view') : '';
        if (!endpoint || typeof window.fetch !== 'function' || typeof URLSearchParams === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-cb-notes-marker]').forEach(function (button) {
            var kind = button.getAttribute('data-cb-notes-marker');
            var id = button.getAttribute('data-cb-notes-id');
            var nonce = button.getAttribute('data-cb-notes-nonce');
            if ((kind !== 'favorite' && kind !== 'pinned') || !/^[1-9][0-9]*$/.test(id || '') || !nonce) {
                return;
            }
            button.hidden = false;
            button.addEventListener('click', function () {
                if (button.disabled) return;
                var enable = button.getAttribute('aria-pressed') !== 'true';
                var params = new URLSearchParams();
                params.set('action', 'cb_notes_mark');
                params.set('note_id', id);
                params.set('_ajax_nonce', nonce);
                params.set('kind', kind);
                params.set('enabled', enable ? '1' : '0');
                button.disabled = true;
                report('');

                window.fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (!result || result.success !== true || !result.data ||
                        typeof result.data.favorite !== 'boolean' ||
                        typeof result.data.pinned !== 'boolean') {
                        var message = result && result.data && result.data.message
                            ? result.data.message : 'Could not save this note preference.';
                        throw new Error(message);
                    }
                    // Pin ordering and saved views require server pagination
                    // to be recalculated, not an inaccurate local DOM reorder.
                    if (kind === 'pinned' || view === kind + 's') {
                        window.location.reload();
                        return;
                    }
                    var marked = result.data[kind];
                    button.setAttribute('aria-pressed', marked ? 'true' : 'false');
                    button.classList.toggle('cb-core-button--primary', marked);
                    button.classList.toggle('cb-core-button--secondary', !marked);
                    var label = kind === 'favorite'
                        ? (marked ? 'Remove from favorites' : 'Add to favorites')
                        : (marked ? 'Unpin note' : 'Pin note');
                    button.setAttribute('aria-label', label);
                    button.title = label;
                }).catch(function (failure) {
                    report(failure && failure.message
                        ? failure.message : 'Could not update this note. Please try again.');
                }).finally(function () {
                    button.disabled = false;
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
