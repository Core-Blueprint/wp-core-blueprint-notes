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

    var kinds = {
        notebook: { title: 'Notebooks', singular: 'notebook' },
        category: { title: 'Categories', singular: 'category' },
        tag: { title: 'Tags', singular: 'tag' }
    };
    var path = '/core-blueprint-notes/v1/organization';

    function validSection(value) {
        return Object.prototype.hasOwnProperty.call(kinds, value) ? value : 'notebook';
    }

    function sectionFromUrl() {
        return validSection(new URL(window.location.href).searchParams.get('section'));
    }

    function sectionUrl(section) {
        var url = new URL(window.location.href);
        url.searchParams.set('section', section);
        return url.toString();
    }

    function App() {
        var catalogState = useState(null), catalog = catalogState[0], setCatalog = catalogState[1];
        var kindState = useState(sectionFromUrl), kind = kindState[0], setKind = kindState[1];
        var addingState = useState(false), adding = addingState[0], setAdding = addingState[1];
        var nameState = useState(''), newName = nameState[0], setNewName = nameState[1];
        var editState = useState(''), editing = editState[0], setEditing = editState[1];
        var editNameState = useState(''), editName = editNameState[0], setEditName = editNameState[1];
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        var messageState = useState(''), message = messageState[0], setMessage = messageState[1];
        var errorState = useState(''), error = errorState[0], setError = errorState[1];
        var createInput = useRef(null);

        useEffect(function () {
            var active = true;
            wp.apiFetch({ path: path }).then(function (result) {
                if (active) setCatalog(result);
            }).catch(function (failure) {
                if (active) setError(failure.message || 'Could not load your personal labels.');
            });
            return function () { active = false; };
        }, []);

        useEffect(function () {
            if (adding && createInput.current) {
                var input = createInput.current.querySelector('input');
                if (input) input.focus();
            }
        }, [adding, kind]);

        useEffect(function () {
            function restoreSection() {
                setKind(sectionFromUrl());
                setAdding(false);
                setEditing('');
                setNewName('');
                setMessage('');
                setError('');
            }
            window.addEventListener('popstate', restoreSection);
            return function () { window.removeEventListener('popstate', restoreSection); };
        }, []);

        function selectSection(next, event) {
            // Respect Ctrl/Cmd/Shift-click and opening a section in a new tab.
            if (event && (event.ctrlKey || event.metaKey || event.shiftKey ||
                event.altKey || (typeof event.button === 'number' && event.button !== 0))) {
                return;
            }
            if (event) event.preventDefault();
            if (busy || next === kind) return;
            window.history.replaceState(window.history.state, '', sectionUrl(next));
            setKind(next);
            setAdding(false);
            setEditing('');
            setNewName('');
            setEditName('');
            setError('');
            setMessage('');
        }

        function mutate(action, itemId, name, selectedKind) {
            if (busy) return;
            setBusy(true);
            setError('');
            setMessage('');
            wp.apiFetch({
                path: path,
                method: 'POST',
                data: { action: action, kind: selectedKind, id: itemId || '', name: name || '' }
            }).then(function (result) {
                setCatalog(result);
                setNewName('');
                setEditing('');
                setEditName('');
                setAdding(false);
                var verb = action === 'create' ? 'Created' : action === 'rename' ? 'Renamed' : 'Removed';
                setMessage(verb + ' ' + kinds[selectedKind].singular + ' successfully.');
            }).catch(function (failure) {
                setError(failure.message || 'Changes could not be saved.');
            }).finally(function () { setBusy(false); });
        }

        function confirmDelete(item, selectedKind) {
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError('The Core Blueprint confirmation is unavailable. No item was removed.');
                return;
            }
            modal.show({
                title: 'Remove ' + item.name + '?',
                body: 'This removes the personal ' + kinds[selectedKind].singular +
                    ' from your catalog. Notes using it will become unfiled in that field. Note content and revisions remain intact.',
                confirmVariant: 'danger',
                confirmLabel: 'Remove label',
                confirmCheck: {
                    label: 'I understand that this label will no longer be assigned to notes.'
                }
            }).then(function (accepted) {
                if (accepted === true) mutate('delete', item.id, '', selectedKind);
            }).catch(function () {
                setError('Could not complete the confirmation. No changes were made.');
            });
        }

        var spec = kinds[kind];
        var tabs = Object.keys(kinds).map(function (key) {
            var active = key === kind;
            return el('a', {
                key: key,
                href: sectionUrl(key),
                className: 'nav-tab' + (active ? ' nav-tab-active' : ''),
                'aria-current': active ? 'page' : undefined,
                onClick: function (event) { selectSection(key, event); }
            }, kinds[key].title);
        });

        var content = [
            el('nav', {
                key: 'tabs', className: 'nav-tab-wrapper cb-core-tab-wrapper cb-notes-organize__tabs',
                'aria-label': 'Personal organization sections'
            }, tabs)
        ];

        if (error) {
            content.push(el('div', {
                key: 'error', className: 'cb-core-notice cb-core-notice--error',
                role: 'alert'
            }, el('span', {
                className: 'cb-core-notice__icon', 'aria-hidden': 'true'
            }, '!'), el('div', { className: 'cb-core-notice__content' },
                el('p', { className: 'cb-core-notice__message' }, error)
            )));
        }
        if (message) {
            content.push(el('p', {
                key: 'message', className: 'cb-notes-organize__feedback',
                role: 'status', 'aria-live': 'polite'
            }, message));
        }

        if (!catalog && !error) {
            content.push(el('p', {
                key: 'loading', className: 'cb-notes-organize__help',
                role: 'status'
            }, 'Loading personal labels…'));
        } else {
            var items = catalog[kind] || [];
            var list = [];

            if (items.length === 0) {
                list.push(el('p', {
                    key: 'empty', className: 'cb-notes-organize__empty'
                }, 'No ' + spec.title.toLowerCase() + ' yet. Use Add new to create your first ' + spec.singular + '.'));
            }
            items.forEach(function (item) {
                var isEditing = editing === item.id;
                var row;

                if (isEditing) {
                    row = el('div', { className: 'cb-notes-organize__rename' },
                        el(wp.components.TextControl, {
                            label: 'Rename ' + item.name,
                            value: editName,
                            maxLength: 80,
                            disabled: busy,
                            onChange: setEditName
                        }),
                        el('div', { className: 'cb-notes-organize__actions' },
                            el(wp.components.Button, {
                                className: 'cb-core-button cb-core-button--primary',
                                variant: 'primary',
                                disabled: busy || !editName.trim(),
                                onClick: function () { mutate('rename', item.id, editName.trim(), kind); }
                            }, 'Save'),
                            el(wp.components.Button, {
                                className: 'cb-core-button cb-core-button--secondary',
                                variant: 'secondary',
                                disabled: busy,
                                onClick: function () { setEditing(''); setEditName(''); }
                            }, 'Cancel')
                        )
                    );
                } else {
                    row = el('div', { className: 'cb-notes-organize__actions' },
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--secondary',
                            variant: 'secondary',
                            disabled: busy,
                            onClick: function () {
                                setAdding(false);
                                setEditing(item.id);
                                setEditName(item.name);
                                setError('');
                                setMessage('');
                            }
                        }, 'Rename'),
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--danger',
                            variant: 'secondary',
                            isDestructive: true,
                            disabled: busy,
                            onClick: function () { confirmDelete(item, kind); }
                        }, 'Remove')
                    );
                }

                list.push(el('div', {
                    key: item.id,
                    className: 'cb-notes-organize__item' + (isEditing ? ' cb-notes-organize__item--editing' : '')
                }, el('strong', { className: 'cb-notes-organize__item-name' }, item.name), row));
            });

            var header = el('div', { className: 'cb-notes-organize__list-header' },
                el('div', { className: 'cb-notes-organize__list-heading' },
                    el('h2', { className: 'cb-notes-organize__heading' }, spec.title),
                    el('span', { className: 'cb-notes-organize__count' },
                        items.length + (items.length === 1 ? ' personal label' : ' personal labels'))
                ),
                el(wp.components.Button, {
                    className: 'cb-core-button ' +
                        (adding ? 'cb-core-button--secondary' : 'cb-core-button--primary'),
                    variant: adding ? 'secondary' : 'primary',
                    disabled: busy,
                    'aria-expanded': adding,
                    'aria-controls': adding ? 'cb-notes-organize-create' : undefined,
                    onClick: function () {
                        setEditing('');
                        setEditName('');
                        setNewName('');
                        setAdding(!adding);
                        setError('');
                        setMessage('');
                    }
                }, adding ? 'Cancel' : 'Add new')
            );

            var panel = [header];
            if (adding) {
                panel.push(el('div', {
                    id: 'cb-notes-organize-create',
                    key: 'create',
                    ref: createInput,
                    className: 'cb-notes-organize__create'
                },
                    el(wp.components.TextControl, {
                        label: 'Name',
                        value: newName,
                        maxLength: 80,
                        disabled: busy,
                        onChange: setNewName,
                        placeholder: 'Name your new ' + spec.singular
                    }),
                    el('div', { className: 'cb-notes-organize__actions' },
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--primary',
                            variant: 'primary',
                            disabled: busy || !newName.trim(),
                            onClick: function () { mutate('create', '', newName.trim(), kind); }
                        }, 'Create ' + spec.singular),
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--secondary',
                            variant: 'secondary',
                            disabled: busy,
                            onClick: function () { setAdding(false); setNewName(''); }
                        }, 'Cancel')
                    )
                ));
            }
            panel.push(el('div', {
                key: 'items', className: 'cb-notes-organize__items'
            }, list));

            content.push(el('section', {
                key: 'manager', className: 'cb-core-card cb-notes-organize__card',
                'aria-label': spec.title + ' management'
            }, el('div', { className: 'cb-core-card__body' }, panel)));
        }

        return el('div', { className: 'cb-notes-organize__stack' }, content);
    }

    wp.element.render(el(App), target);
}());