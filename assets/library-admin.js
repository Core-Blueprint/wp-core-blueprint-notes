/* Small progressive enhancement only. Search/filtering/paging remain server-side. */
(function () {
    'use strict';

    function init() {
        var controls = document.querySelectorAll('[data-cb-notes-trash-url]');
        controls.forEach(function (button) {
            var url = button.getAttribute('data-cb-notes-trash-url');
            if (!url) return;
            button.hidden = false;
            button.addEventListener('click', function () {
                if (window.confirm('Move this private note to Trash? You can restore it from WordPress Trash.')) {
                    window.location.assign(url);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
