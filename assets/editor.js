/* global wp */
(function () {
    'use strict';
    if (typeof wp === 'undefined' || !wp.plugins || !wp.editPost || !wp.data || !wp.element || !wp.components || !wp.apiFetch) {
        return;
    }

    // WordPress wp-i18n is an enqueued dependency. Keep English fallback for
    // isolated browser test harnesses; extraction always sees literal msgids.
    var __ = wp.i18n && typeof wp.i18n.__ === 'function'
        ? wp.i18n.__ : function (message) { return message; };
    var sprintf = wp.i18n && typeof wp.i18n.sprintf === 'function'
        ? wp.i18n.sprintf : function (format) {
            var args = Array.prototype.slice.call(arguments, 1);
            return format.replace(/%s|%d/g, function () { return String(args.shift()); });
        };
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
        notebook: __('notebook', 'core-blueprint-notes'),
        category: __('category', 'core-blueprint-notes'),
        tag: __('tag', 'core-blueprint-notes')
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
                if (active) setError(__('Personal organization could not be loaded. Reload the editor before changing labels.', 'core-blueprint-notes'));
            });
            return function () { active = false; };
        }, []);

        // Do not hide existing classifications when opening an edited note.
        useEffect(function () {
            if (chosenCategory || chosenTagIds) setAdvanced(true);
        }, [chosenCategory, chosenTagIds]);

        function save(kind, value) {
            // An asynchronous catalog creation can resolve after other sidebar
            // edits. Read the latest Gutenberg meta at commit time so it does
            // not accidentally revert unrelated classifications.
            var latest = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
            dispatch.editPost({ meta: Object.assign({}, latest, { [fields[kind]]: value }) });
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
                    throw new Error(__('The new label could not be confirmed. Reload the editor.', 'core-blueprint-notes'));
                }
                var created = next[kind].find(function (item) {
                    return !existing.some(function (previous) { return previous.id === item.id; });
                });
                setCatalog(next);
                if (!created) {
                    throw new Error(__('The new label could not be found. Reload the editor before trying again.', 'core-blueprint-notes'));
                }
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
                setError(failure.message || __('Could not create the personal label.', 'core-blueprint-notes'));
            }).finally(function () { setBusy(false); });
        }

        function createControls(kind) {
            if (creating !== kind) {
                return el(wp.components.Button, {
                    key: 'new-' + kind,
                    variant: 'tertiary',
                    disabled: busy || (kind === 'tag' && chosenTags.length >= 12),
                    onClick: function () { beginCreation(kind); }
                }, sprintf(__('New %s', 'core-blueprint-notes'), titles[kind]));
            }
            return el('div', { key: 'create-' + kind, className: 'cb-notes-editor__new-label' },
                el(wp.components.TextControl, {
                    label: sprintf(__('New %s name', 'core-blueprint-notes'), titles[kind]),
                    value: name,
                    onChange: setName,
                    maxLength: 80,
                    placeholder: __('Enter a name…', 'core-blueprint-notes'),
                    disabled: busy
                }),
                el(wp.components.Button, {
                    variant: 'secondary',
                    disabled: busy || !name.trim(),
                    isBusy: busy,
                    onClick: createItem
                }, sprintf(__('Create %s', 'core-blueprint-notes'), titles[kind])),
                el(wp.components.Button, {
                    variant: 'tertiary',
                    disabled: busy,
                    onClick: function () { setCreating(''); setName(''); }
                }, __('Cancel', 'core-blueprint-notes'))
            );
        }

        var controls = [];
        controls.push(el('p', { key: 'privacy', className: 'components-base-control__help' },
            __('Saving this note does not publish it or update any shared copy. Sharing is a separate action.', 'core-blueprint-notes')));

        if (error) {
            controls.push(el(wp.components.Notice, {
                key: 'error', status: 'error', isDismissible: true,
                onRemove: function () { setError(''); }
            }, error));
        }

        if (!catalog) {
            controls.push(el('p', { key: 'loading' }, error
                ? __('Labels are unavailable. Existing note classifications remain unchanged.', 'core-blueprint-notes')
                : __('Loading your personal organization…', 'core-blueprint-notes')));
        } else {
            var notebooks = Array.isArray(catalog.notebook) ? catalog.notebook : [];
            var categories = Array.isArray(catalog.category) ? catalog.category : [];
            var tags = Array.isArray(catalog.tag) ? catalog.tag : [];
            var tagOptions = tags.filter(function (item) { return !chosenTags.includes(item.id); });
            var selectedTags = tags.filter(function (item) { return chosenTags.includes(item.id); });

            // Notebook is the one primary everyday organization decision.
            controls.push(el(wp.components.SelectControl, {
                key: 'notebook',
                label: __('Notebook', 'core-blueprint-notes'),
                value: meta[fields.notebook] || '',
                options: [{ label: __('No notebook', 'core-blueprint-notes'), value: '' }].concat(notebooks.map(function (item) {
                    return { label: item.name, value: item.id };
                })),
                onChange: function (value) { save('notebook', value); },
                help: __('Choose where you want to find this note.', 'core-blueprint-notes')
            }));
            controls.push(createControls('notebook'));

            var applied = (chosenCategory ? 1 : 0) + selectedTags.length;
            controls.push(el(wp.components.Button, {
                key: 'advanced-toggle',
                variant: 'tertiary',
                'aria-expanded': advanced,
                'aria-controls': 'cb-notes-editor-advanced',
                onClick: function () { setAdvanced(!advanced); }
            }, applied
                ? sprintf(advanced ? __('Hide categories and tags (%d applied)', 'core-blueprint-notes') : __('Show categories and tags (%d applied)', 'core-blueprint-notes'), applied)
                : (advanced ? __('Hide categories and tags', 'core-blueprint-notes') : __('Show categories and tags', 'core-blueprint-notes'))));

            if (advanced) {
                controls.push(el('div', { key: 'advanced', id: 'cb-notes-editor-advanced' },
                    el(wp.components.SelectControl, {
                        label: __('Category', 'core-blueprint-notes'),
                        value: chosenCategory,
                        options: [{ label: __('No category', 'core-blueprint-notes'), value: '' }].concat(categories.map(function (item) {
                            return { label: item.name, value: item.id };
                        })),
                        onChange: function (value) { save('category', value); }
                    }),
                    createControls('category'),
                    el('p', { className: 'components-base-control__help' },
                        sprintf(__('Tags (%d/12)', 'core-blueprint-notes'), chosenTags.length)),
                    selectedTags.length ? selectedTags.map(function (item) {
                        return el(wp.components.Button, {
                            key: 'selected-tag-' + item.id,
                            variant: 'secondary',
                            size: 'small',
                            onClick: function () {
                                save('tag', chosenTags.filter(function (id) { return id !== item.id; }));
                            },
                            'aria-label': sprintf(__('Remove tag %s', 'core-blueprint-notes'), item.name)
                        }, item.name + ' ×');
                    }) : el('p', { className: 'components-base-control__help' }, __('No tags selected.', 'core-blueprint-notes')),
                    chosenTags.length < 12 && tagOptions.length ? el(wp.components.SelectControl, {
                        label: __('Add existing tag', 'core-blueprint-notes'),
                        value: tagToAdd,
                        options: [{ label: __('Select a tag…', 'core-blueprint-notes'), value: '' }].concat(tagOptions.map(function (item) {
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
                    }, __('Add tag', 'core-blueprint-notes')) : null,
                    chosenTags.length < 12 ? createControls('tag') : el('p', {
                        className: 'components-base-control__help'
                    }, __('Maximum of 12 tags reached.', 'core-blueprint-notes'))
                ));
            } else {
                // Keep the aria-controls target present while collapsed.
                controls.push(el('div', { key: 'advanced', id: 'cb-notes-editor-advanced', hidden: true }));
            }
            controls.push(el('p', { key: 'manage', className: 'components-base-control__help' },
                __('Rename or remove personal labels in Notes → Organize.', 'core-blueprint-notes')));
        }

        return el(Panel, {
            name: 'cb-notes-organize',
            title: __('Organize', 'core-blueprint-notes'),
            className: 'cb-notes-editor-organization'
        }, controls);
    }

    wp.plugins.registerPlugin('cb-notes-organize', {
        render: OrganizationPanel,
        icon: 'category'
    });
}());
