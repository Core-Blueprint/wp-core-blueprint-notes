/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.plugins || !wp.editPost || !wp.data || !wp.element || !wp.components || !wp.apiFetch) {
        return;
    }
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var Panel = wp.editPost.PluginDocumentSettingPanel;
    if (!Panel) return;

    var fields = {
        notebook: 'cb_notes_notebook_id',
        category: 'cb_notes_category_id',
        tag: 'cb_notes_tag_ids'
    };
    var labels = {
        notebook: { title: 'Notebook', singular: 'notebook' },
        category: { title: 'Category', singular: 'category' },
        tag: { title: 'Tags', singular: 'tag' }
    };
    var organizationPath = '/core-blueprint-notes/v1/organization';

    function OrganizationPanel(props) {
        var kind = props.kind;
        var spec = labels[kind];
        var state = wp.data.useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);
        var dispatch = wp.data.useDispatch('core/editor');
        var entries = useState(null), catalog = entries[0], setCatalog = entries[1];
        var newName = useState(''), name = newName[0], setName = newName[1];
        var newTag = useState(''), tagToAdd = newTag[0], setTagToAdd = newTag[1];
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        var err = useState(''), error = err[0], setError = err[1];

        useEffect(function () {
            var active = true;
            wp.apiFetch({ path: organizationPath }).then(function (response) {
                if (active) setCatalog(response);
            }).catch(function () {
                if (active) setError('Personal organization could not be loaded.');
            });
            return function () { active = false; };
        }, []);

        function save(value) {
            dispatch.editPost({ meta: Object.assign({}, state, { [fields[kind]]: value }) });
        }
        function addItem() {
            if (!name.trim() || busy) return;
            setBusy(true);
            setError('');
            wp.apiFetch({
                path: organizationPath, method: 'POST',
                data: { action: 'create', kind: kind, name: name.trim() }
            }).then(function (next) {
                var before = catalog && catalog[kind] ? catalog[kind] : [];
                var created = next[kind].find(function (item) {
                    return !before.some(function (previous) { return previous.id === item.id; });
                });
                setCatalog(next);
                if (created) {
                    if (kind === 'tag') {
                        var current = Array.isArray(state[fields.tag]) ? state[fields.tag] : [];
                        if (current.length < 12) save(current.concat([created.id]));
                    } else {
                        save(created.id);
                    }
                }
                setName('');
            }).catch(function (failure) {
                setError(failure.message || 'Could not create the personal label.');
            }).finally(function () { setBusy(false); });
        }
        var controls = [];
        if (error) controls.push(el(wp.components.Notice, {
            key: 'error', status: 'error', isDismissible: true,
            onRemove: function () { setError(''); }
        }, error));
        if (!catalog) {
            controls.push(el('p', { key: 'loading' }, 'Loading your personal labels…'));
        } else {
            var options = catalog[kind] || [];
            if (kind !== 'tag') {
                controls.push(el(wp.components.SelectControl, {
                    key: 'choice',
                    label: kind === 'notebook' ? 'Notebook' : 'Category',
                    value: state[fields[kind]] || '',
                    options: [{ label: 'Unfiled', value: '' }].concat(options.map(function (item) {
                        return { label: item.name, value: item.id };
                    })),
                    onChange: save,
                    help: 'Only you can see your personal organization labels.'
                }));
            } else {
                var selected = Array.isArray(state[fields.tag]) ? state[fields.tag] : [];
                var available = options.filter(function (item) { return !selected.includes(item.id); });
                var chosen = options.filter(function (item) { return selected.includes(item.id); });
                controls.push(el('p', { key: 'selected-title' }, 'Selected tags (' + chosen.length + '/12)'));
                if (!chosen.length) controls.push(el('p', {
                    key: 'unfiled', className: 'components-base-control__help'
                }, 'No tags selected.'));
                chosen.forEach(function (item) {
                    controls.push(el('div', { key: 'tag-' + item.id, style: { marginBottom: '8px' } },
                        el(wp.components.Button, {
                            variant: 'secondary', size: 'small',
                            onClick: function () { save(selected.filter(function (id) { return id !== item.id; })); },
                            'aria-label': 'Remove tag ' + item.name
                        }, item.name + ' ×')
                    ));
                });
                if (selected.length < 12 && available.length) {
                    controls.push(el(wp.components.SelectControl, {
                        key: 'tag-choice', label: 'Add existing tag', value: tagToAdd,
                        options: [{ label: 'Select a tag…', value: '' }].concat(available.map(function (item) {
                            return { label: item.name, value: item.id };
                        })),
                        onChange: setTagToAdd
                    }));
                    controls.push(el(wp.components.Button, {
                        key: 'tag-add', variant: 'secondary', disabled: !tagToAdd,
                        onClick: function () { save(selected.concat([tagToAdd])); setTagToAdd(''); }
                    }, 'Add tag'));
                }
            }
            if (kind !== 'tag' || (Array.isArray(state[fields.tag]) ? state[fields.tag].length : 0) < 12) {
                controls.push(el(wp.components.TextControl, {
                    key: 'new-name', label: 'New ' + spec.singular,
                    value: name, onChange: setName, maxLength: 80,
                    placeholder: 'Enter a name…'
                }));
                controls.push(el(wp.components.Button, {
                    key: 'create', variant: 'secondary', disabled: busy || !name.trim(),
                    isBusy: busy, onClick: addItem
                }, 'Create ' + spec.singular));
            }
            controls.push(el('p', { key: 'manage', className: 'components-base-control__help' },
                'Rename or remove personal labels from Notes → Organize.'));
        }
        return el(Panel, {
            name: 'cb-notes-' + kind, title: spec.title,
            className: 'cb-notes-organization-' + kind
        }, controls);
    }

    ['notebook', 'category', 'tag'].forEach(function (kind) {
        wp.plugins.registerPlugin('cb-notes-' + kind, {
            render: function () { return el(OrganizationPanel, { kind: kind }); },
            icon: kind === 'tag' ? 'tag' : 'category'
        });
    });
}());