/* global wp */
(function () {
    'use strict';
    if (typeof wp === 'undefined' || !wp.plugins || !wp.editPost || !wp.data || !wp.element || !wp.components || !wp.apiFetch) {
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
    var titles = {
        notebook: 'notebook',
        category: 'category',
        tag: 'tag'
    };
    var organizationPath = '/core-blueprint-notes/v1/organization';

    function OrganizationPanel() {
        var meta = wp.data.useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);
        var dispatch = wp.data.useDispatch('core/editor');

        var catalogState = useState(null), catalog = catalogState[0], setCatalog = catalogState[1];
        var advancedState = useState(false), advanced = advancedState[0], setAdvanced = advancedState[1];
        var createState = useState(''), creating = createState[0], setCreating = createState[1];
        var nameState = useState(''), name = nameState[0], setName = nameState[1];
        var tagState = useState(''), tagToAdd = tagState[0], setTagToAdd = tagState[1];
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        var errorState = useState(''), error = errorState[0], setError = errorState[1];

        var chosenCategory = meta[fields.category] || '';
        var chosenTags = Array.isArray(meta[fields.tag]) ? meta[fields.tag] : [];
        var chosenTagIds = chosenTags.join(',');

        useEffect(function () {
            var active = true;
            wp.apiFetch({ path: organizationPath }).then(function (response) {
                if (active) setCatalog(response);
            }).catch(function () {
                if (active) setError('Personal organization could not be loaded. Reload the editor before changing labels.');
            });
            return function () { active = false; };
        }, []);

        // Do not hide existing classifications when opening an edited note.
        useEffect(function () {
            if (chosenCategory || chosenTagIds) setAdvanced(true);
        }, [chosenCategory, chosenTagIds]);

        function save(kind, value) {
            dispatch.editPost({ meta: Object.assign({}, meta, { [fields[kind]]: value }) });
        }

        function beginCreation(kind) {
            if (busy) return;
            setCreating(kind);
            setName('');
            setError('');
            if (kind !== 'notebook') setAdvanced(true);
        }

        function createItem() {
            if (!creating || !name.trim() || !catalog || busy) return;
            var kind = creating;
            var existing = Array.isArray(catalog[kind]) ? catalog[kind] : [];
            var inputName = name.trim();
            setBusy(true);
            setError('');

            wp.apiFetch({
                path: organizationPath,
                method: 'POST',
                data: { action: 'create', kind: kind, name: inputName }
            }).then(function (next) {
                if (!next || !Array.isArray(next[kind])) {
                    throw new Error('The new label could not be confirmed. Reload the editor.');
                }
                var created = next[kind].find(function (item) {
                    return !existing.some(function (previous) { return previous.id === item.id; });
                });
                setCatalog(next);
                if (created) {
                    if (kind === 'tag') {
                        var selected = Array.isArray(meta[fields.tag]) ? meta[fields.tag] : [];
                        if (selected.length < 12 && !selected.includes(created.id)) {
                            save('tag', selected.concat([created.id]));
                        }
                    } else {
                        save(kind, created.id);
                    }
                }
                setCreating('');
                setName('');
            }).catch(function (failure) {
                setError(failure.message || 'Could not create the personal label.');
            }).finally(function () { setBusy(false); });
        }

        function createControls(kind) {
            if (creating !== kind) {
                return el(wp.components.Button, {
                    key: 'new-' + kind,
                    variant: 'tertiary',
                    disabled: busy || (kind === 'tag' && chosenTags.length >= 12),
                    onClick: function () { beginCreation(kind); }
                }, 'New ' + titles[kind]);
            }
            return el('div', { key: 'create-' + kind, className: 'cb-notes-editor__new-label' },
                el(wp.components.TextControl, {
                    label: 'New ' + titles[kind] + ' name',
                    value: name,
                    onChange: setName,
                    maxLength: 80,
                    placeholder: 'Enter a name…',
                    disabled: busy
                }),
                el(wp.components.Button, {
                    variant: 'secondary',
                    disabled: busy || !name.trim(),
                    isBusy: busy,
                    onClick: createItem
                }, 'Create ' + titles[kind]),
                el(wp.components.Button, {
                    variant: 'tertiary',
                    disabled: busy,
                    onClick: function () { setCreating(''); setName(''); }
                }, 'Cancel')
            );
        }

        var controls = [];
        controls.push(el('p', { key: 'privacy', className: 'components-base-control__help' },
            'Your notes stay private when saved. Sharing is a separate action.'));

        if (error) {
            controls.push(el(wp.components.Notice, {
                key: 'error', status: 'error', isDismissible: true,
                onRemove: function () { setError(''); }
            }, error));
        }

        if (!catalog) {
            controls.push(el('p', { key: 'loading' }, error
                ? 'Labels are unavailable. Existing note classifications remain unchanged.'
                : 'Loading your personal organization…'));
        } else {
            var notebooks = Array.isArray(catalog.notebook) ? catalog.notebook : [];
            var categories = Array.isArray(catalog.category) ? catalog.category : [];
            var tags = Array.isArray(catalog.tag) ? catalog.tag : [];
            var tagOptions = tags.filter(function (item) { return !chosenTags.includes(item.id); });
            var selectedTags = tags.filter(function (item) { return chosenTags.includes(item.id); });

            // Notebook is the one primary everyday organization decision.
            controls.push(el(wp.components.SelectControl, {
                key: 'notebook',
                label: 'Notebook',
                value: meta[fields.notebook] || '',
                options: [{ label: 'No notebook', value: '' }].concat(notebooks.map(function (item) {
                    return { label: item.name, value: item.id };
                })),
                onChange: function (value) { save('notebook', value); },
                help: 'Choose where you want to find this note.'
            }));
            controls.push(createControls('notebook'));

            var applied = (chosenCategory ? 1 : 0) + selectedTags.length;
            controls.push(el(wp.components.Button, {
                key: 'advanced-toggle',
                variant: 'tertiary',
                'aria-expanded': advanced,
                'aria-controls': 'cb-notes-editor-advanced',
                onClick: function () { setAdvanced(!advanced); }
            }, (advanced ? 'Hide' : 'Show') + ' categories and tags' +
                (applied ? ' (' + applied + ' applied)' : '')));

            if (advanced) {
                controls.push(el('div', { key: 'advanced', id: 'cb-notes-editor-advanced' },
                    el(wp.components.SelectControl, {
                        label: 'Category',
                        value: chosenCategory,
                        options: [{ label: 'No category', value: '' }].concat(categories.map(function (item) {
                            return { label: item.name, value: item.id };
                        })),
                        onChange: function (value) { save('category', value); }
                    }),
                    createControls('category'),
                    el('p', { className: 'components-base-control__help' },
                        'Tags (' + chosenTags.length + '/12)'),
                    selectedTags.length ? selectedTags.map(function (item) {
                        return el(wp.components.Button, {
                            key: 'selected-tag-' + item.id,
                            variant: 'secondary',
                            size: 'small',
                            onClick: function () {
                                save('tag', chosenTags.filter(function (id) { return id !== item.id; }));
                            },
                            'aria-label': 'Remove tag ' + item.name
                        }, item.name + ' ×');
                    }) : el('p', { className: 'components-base-control__help' }, 'No tags selected.'),
                    chosenTags.length < 12 && tagOptions.length ? el(wp.components.SelectControl, {
                        label: 'Add existing tag',
                        value: tagToAdd,
                        options: [{ label: 'Select a tag…', value: '' }].concat(tagOptions.map(function (item) {
                            return { label: item.name, value: item.id };
                        })),
                        onChange: setTagToAdd
                    }) : null,
                    chosenTags.length < 12 && tagOptions.length ? el(wp.components.Button, {
                        variant: 'secondary',
                        disabled: !tagToAdd || chosenTags.includes(tagToAdd),
                        onClick: function () {
                            if (tagToAdd && !chosenTags.includes(tagToAdd) && chosenTags.length < 12) {
                                save('tag', chosenTags.concat([tagToAdd]));
                                setTagToAdd('');
                            }
                        }
                    }, 'Add tag') : null,
                    chosenTags.length < 12 ? createControls('tag') : el('p', {
                        className: 'components-base-control__help'
                    }, 'Maximum of 12 tags reached.')
                ));
            }
            controls.push(el('p', { key: 'manage', className: 'components-base-control__help' },
                'Rename or remove personal labels in Notes → Organize.'));
        }

        return el(Panel, {
            name: 'cb-notes-organize',
            title: 'Organize',
            className: 'cb-notes-editor-organization'
        }, controls);
    }

    wp.plugins.registerPlugin('cb-notes-organize', {
        render: OrganizationPanel,
        icon: 'category'
    });
}());
