/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.element || !wp.components || !wp.apiFetch) return;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var target = document.getElementById('cb-notes-organization-root');
    if (!target) return;

    /*
     * Base Select Picker enhances and relocates the native <select>.
     * React owns only the host slot; it never reconciles the enhanced DOM.
     * The native select remains the accessible and functional fallback.
     */
    function KindPicker(props) {
        var host = useRef(null);
        var select = useRef(null);
        var currentChange = useRef(props.onChange);
        currentChange.current = props.onChange;

        useEffect(function () {
            var container = host.current;
            if (!container) return;
            var nativeSelect = document.createElement('select');
            nativeSelect.id = 'cb-notes-organize-kind';
            nativeSelect.dataset.cbCoreSelectPicker = '1';
            nativeSelect.dataset.cbCoreSelectPickerSearch = 'false';
            [
                ['notebook', 'Notebooks'],
                ['category', 'Categories'],
                ['tag', 'Tags']
            ].forEach(function (entry) {
                var option = document.createElement('option');
                option.value = entry[0];
                option.textContent = entry[1];
                nativeSelect.appendChild(option);
            });
            nativeSelect.value = props.value;
            container.appendChild(nativeSelect);
            select.current = nativeSelect;
            function changed() { currentChange.current(nativeSelect.value); }
            nativeSelect.addEventListener('change', changed);

            // If Base loaded earlier, enhance now; otherwise its module
            // scans the marked native select at DOMContentLoaded.
            if (window.cbCore && window.cbCore.selectPicker &&
                typeof window.cbCore.selectPicker.enhance === 'function') {
                window.cbCore.selectPicker.enhance(nativeSelect);
            }
            return function () {
                nativeSelect.removeEventListener('change', changed);
                select.current = null;
                container.textContent = '';
            };
        }, []);

        useEffect(function () {
            if (!select.current) return;
            select.current.value = props.value;
            select.current.dispatchEvent(new Event('cb:select-picker-sync'));
        }, [props.value]);

        return el('div', { className: 'cb-core-field cb-notes-organize__selector' },
            el('label', {
                htmlFor: 'cb-notes-organize-kind', className: 'cb-core-field__label'
            }, 'Manage'),
            el('div', { ref: host, className: 'cb-notes-organize__picker-host' })
        );
    }

    function Card(props) {
        return el('section', {
            className: 'cb-core-card cb-notes-organize__card', key: props.id
        },
            el('header', { className: 'cb-core-card__header' },
                el('h2', { className: 'cb-core-card__title' }, props.title)),
            el('div', { className: 'cb-core-card__body' }, props.children)
        );
    }

    function App() {
        var catalogState = useState(null), catalog = catalogState[0], setCatalog = catalogState[1];
        var kindState = useState('notebook'), kind = kindState[0], setKind = kindState[1];
        var nameState = useState(''), newName = nameState[0], setNewName = nameState[1];
        var editState = useState(''), editing = editState[0], setEditing = editState[1];
        var editNameState = useState(''), editName = editNameState[0], setEditName = editNameState[1];
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        var messageState = useState(''), message = messageState[0], setMessage = messageState[1];
        var errorState = useState(''), error = errorState[0], setError = errorState[1];
        var path = '/core-blueprint-notes/v1/organization';

        useEffect(function () {
            var alive = true;
            wp.apiFetch({ path: path }).then(function (data) {
                if (alive) setCatalog(data);
            }).catch(function (failure) {
                if (alive) setError(failure.message || 'Could not load your personal labels.');
            });
            return function () { alive = false; };
        }, []);

        function mutate(action, id, name) {
            if (busy) return;
            setBusy(true);
            setError('');
            setMessage('');
            wp.apiFetch({ path: path, method: 'POST',
                data: { action: action, kind: kind, id: id || '', name: name || '' }
            }).then(function (result) {
                setCatalog(result);
                setNewName('');
                setEditing('');
                setEditName('');
                setMessage('Your personal ' + kind + ' list has been updated.');
            }).catch(function (failure) {
                setError(failure.message || 'Changes could not be saved.');
            }).finally(function () { setBusy(false); });
        }

        function confirmDelete(item) {
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError('The Core Blueprint confirmation is unavailable. No item was removed.');
                return;
            }
            modal.show({
                title: 'Remove ' + item.name + '?',
                body: 'This removes the personal ' + kind + ' from your catalog. Notes linked to it will become unfiled in that field. Note contents and revisions will not be deleted.',
                confirmVariant: 'danger',
                confirmLabel: 'Remove label',
                confirmCheck: { label: 'I understand that this label will no longer be assigned to notes.' }
            }).then(function (yes) {
                if (yes === true) mutate('delete', item.id, '');
            }).catch(function () {
                setError('Could not confirm removal. No changes were made.');
            });
        }

        var kinds = {
            notebook: { title: 'Notebooks', singular: 'notebook' },
            category: { title: 'Categories', singular: 'category' },
            tag: { title: 'Tags', singular: 'tag' }
        };
        var spec = kinds[kind];
        var children = [
            el(Card, { id: 'select', key: 'select', title: 'Personal organization' },
                el(KindPicker, {
                    value: kind,
                    onChange: function (value) {
                        setKind(value);
                        setEditing('');
                        setNewName('');
                        setMessage('');
                        setError('');
                    }
                })
            )
        ];

        var notices = [];
        if (error) notices.push(el(wp.components.Notice, {
            key: 'error', status: 'error', isDismissible: true,
            onRemove: function () { setError(''); }
        }, error));
        if (message) notices.push(el(wp.components.Notice, {
            key: 'success', status: 'success', isDismissible: true,
            onRemove: function () { setMessage(''); }
        }, message));
        if (notices.length) {
            children.push(el('div', {
                key: 'notices', className: 'cb-notes-organize__notices', role: 'status'
            }, notices));
        }

        if (!catalog) {
            children.push(el(Card, { id: 'loading', key: 'loading', title: spec.title },
                el('p', { className: 'cb-notes-organize__help' }, 'Loading private labels…')
            ));
        } else {
            var items = catalog[kind] || [];
            var existing = [
                el('p', { key: 'count', className: 'cb-notes-organize__help' },
                    items.length + ' personal labels. Changes are private to your account.')
            ];

            if (!items.length) {
                existing.push(el('p', {
                    key: 'empty', className: 'cb-notes-organize__empty'
                }, 'No ' + spec.title.toLowerCase() + ' yet. Create one below.'));
            }

            items.forEach(function (item) {
                var isEditing = editing === item.id;
                var actions;
                if (isEditing) {
                    actions = el('div', { className: 'cb-notes-organize__rename' },
                        el(wp.components.TextControl, {
                            label: 'Rename ' + item.name, value: editName,
                            maxLength: 80, onChange: setEditName
                        }),
                        el('div', { className: 'cb-notes-organize__actions' },
                            el(wp.components.Button, {
                                className: 'cb-core-button cb-core-button--primary',
                                variant: 'primary', disabled: busy || !editName.trim(),
                                onClick: function () { mutate('rename', item.id, editName.trim()); }
                            }, 'Save'),
                            el(wp.components.Button, {
                                className: 'cb-core-button cb-core-button--secondary',
                                variant: 'secondary',
                                onClick: function () { setEditing(''); }
                            }, 'Cancel')
                        )
                    );
                } else {
                    actions = el('div', { className: 'cb-notes-organize__actions' },
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--secondary',
                            variant: 'secondary', disabled: busy,
                            onClick: function () { setEditing(item.id); setEditName(item.name); }
                        }, 'Rename'),
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--danger',
                            variant: 'secondary', isDestructive: true, disabled: busy,
                            onClick: function () { confirmDelete(item); }
                        }, 'Remove')
                    );
                }
                existing.push(el('div', { key: item.id, className: 'cb-notes-organize__item' },
                    el('strong', { className: 'cb-notes-organize__item-name' }, item.name),
                    actions
                ));
            });

            children.push(el(Card, {
                id: 'items', key: 'items', title: spec.title
            }, el('div', { className: 'cb-notes-organize__items' }, existing)));

            children.push(el(Card, {
                id: 'create', key: 'create', title: 'Create ' + spec.singular
            },
                el('div', { className: 'cb-notes-organize__create' },
                    el(wp.components.TextControl, {
                        key: 'new-name', label: 'Name', value: newName,
                        onChange: setNewName, maxLength: 80,
                        placeholder: 'Name your new ' + spec.singular
                    }),
                    el('div', { className: 'cb-notes-organize__actions' },
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--primary',
                            variant: 'primary', disabled: busy || !newName.trim(),
                            onClick: function () { mutate('create', '', newName.trim()); }
                        }, 'Create ' + spec.singular)
                    ),
                    el('p', { className: 'cb-notes-organize__help' },
                        'Removing a label does not delete any note. Labels are not included in shared content.')
                )
            ));
        }
        return el('div', { className: 'cb-notes-organize__stack' }, children);
    }

    wp.element.render(el(App), target);
}());