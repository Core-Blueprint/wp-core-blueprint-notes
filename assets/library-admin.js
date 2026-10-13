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

            // Never display a destructive action if Base's confirmation is
            // unavailable. No window.confirm or insecure fallback.
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                return;
            }
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
