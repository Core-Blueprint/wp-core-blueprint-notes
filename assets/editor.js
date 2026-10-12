/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.plugins || !wp.editPost || !wp.data || !wp.element || !wp.components) {
        return;
    }
    var el = wp.element.createElement;
    var Panel = wp.editPost.PluginDocumentSettingPanel;
    if (!Panel) {
        return;
    }
    function NotebookPanel() {
        var meta = wp.data.useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);
        var dispatch = wp.data.useDispatch('core/editor');
        return el(Panel, { name: 'cb-notes-notebook', title: 'Notebook', className: 'cb-notes-notebook' },
            el(wp.components.TextControl, {
                label: 'Notebook',
                help: 'Personal notebook label. Notes stay private to their owner.',
                value: meta.cb_notes_notebook || '',
                onChange: function (value) {
                    dispatch.editPost({ meta: Object.assign({}, meta, { cb_notes_notebook: value }) });
                }
            })
        );
    }
    wp.plugins.registerPlugin('cb-notes-notebook', { render: NotebookPanel, icon: 'edit-page' });
}());
