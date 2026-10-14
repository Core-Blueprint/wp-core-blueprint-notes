/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.element || !wp.components || !wp.apiFetch) return;

    var __ = wp.i18n && typeof wp.i18n.__ === 'function'
        ? wp.i18n.__ : function (message) { return message; };
    var sprintf = wp.i18n && typeof wp.i18n.sprintf === 'function'
        ? wp.i18n.sprintf : function (format) {
            var args = Array.prototype.slice.call(arguments, 1);
            return format.replace(/%\d*\$?[sd]/g, function () { return String(args.shift()); });
        };
    var _n = wp.i18n && typeof wp.i18n._n === 'function'
        ? wp.i18n._n : function (one, many, count) { return count === 1 ? one : many; };
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var target = document.getElementById('cb-notes-organization-root');
    if (!target) return;

    var kinds = {
        notebook: { title: __('Notebooks', 'core-blueprint-notes'), singular: __('notebook', 'core-blueprint-notes') },
        category: { title: __('Categories', 'core-blueprint-notes'), singular: __('category', 'core-blueprint-notes') },
        tag: { title: __('Tags', 'core-blueprint-notes'), singular: __('tag', 'core-blueprint-notes') }
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
                if (active) setError(failure.message || __('Could not load your personal labels.', 'core-blueprint-notes'));
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
                var verb = action === 'create' ? __('Created', 'core-blueprint-notes') : action === 'rename' ? __('Renamed', 'core-blueprint-notes') : __('Removed', 'core-blueprint-notes');
                setMessage(sprintf(__('%1$s %2$s successfully.', 'core-blueprint-notes'), verb, kinds[selectedKind].singular));
            }).catch(function (failure) {
                setError(failure.message || __('Changes could not be saved.', 'core-blueprint-notes'));
            }).finally(function () { setBusy(false); });
        }

        function confirmDelete(item, selectedKind) {
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError(__('The Core Blueprint confirmation is unavailable. No item was removed.', 'core-blueprint-notes'));
                return;
            }
            modal.show({
                title: sprintf(__('Remove %s?', 'core-blueprint-notes'), item.name),
                body: sprintf(__('This removes the personal %s from your catalog. Notes using it will become unfiled in that field. Note content and revisions remain intact.', 'core-blueprint-notes'), kinds[selectedKind].singular),
                confirmVariant: 'danger',
                confirmLabel: __('Remove label', 'core-blueprint-notes'),
                confirmCheck: {
                    label: __('I understand that this label will no longer be assigned to notes.', 'core-blueprint-notes')
                }
            }).then(function (accepted) {
                if (accepted === true) mutate('delete', item.id, '', selectedKind);
            }).catch(function () {
                setError(__('Could not complete the confirmation. No changes were made.', 'core-blueprint-notes'));
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
                'aria-label': __('Personal organization sections', 'core-blueprint-notes')
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
            }, __('Loading personal labels…', 'core-blueprint-notes')));
        } else {
            var items = catalog[kind] || [];
            var list = [];

            if (items.length === 0) {
                list.push(el('p', {
                    key: 'empty', className: 'cb-notes-organize__empty'
                }, sprintf(__('No %1$s yet. Use Add new to create your first %2$s.', 'core-blueprint-notes'), spec.title.toLowerCase(), spec.singular)));
            }
            items.forEach(function (item) {
                var isEditing = editing === item.id;
                var row;

                if (isEditing) {
                    row = el('div', { className: 'cb-notes-organize__rename' },
                        el(wp.components.TextControl, {
                            label: sprintf(__('Rename %s', 'core-blueprint-notes'), item.name),
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
                            }, __('Save', 'core-blueprint-notes')),
                            el(wp.components.Button, {
                                className: 'cb-core-button cb-core-button--secondary',
                                variant: 'secondary',
                                disabled: busy,
                                onClick: function () { setEditing(''); setEditName(''); }
                            }, __('Cancel', 'core-blueprint-notes'))
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
                        }, __('Rename', 'core-blueprint-notes')),
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--danger',
                            variant: 'secondary',
                            isDestructive: true,
                            disabled: busy,
                            onClick: function () { confirmDelete(item, kind); }
                        }, __('Remove', 'core-blueprint-notes'))
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
                        sprintf(_n('%d personal label', '%d personal labels', items.length, 'core-blueprint-notes'), items.length))
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
                }, adding ? __('Cancel', 'core-blueprint-notes') : __('Add new', 'core-blueprint-notes'))
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
                        label: __('Name', 'core-blueprint-notes'),
                        value: newName,
                        maxLength: 80,
                        disabled: busy,
                        onChange: setNewName,
                        placeholder: sprintf(__('Name your new %s', 'core-blueprint-notes'), spec.singular)
                    }),
                    el('div', { className: 'cb-notes-organize__actions' },
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--primary',
                            variant: 'primary',
                            disabled: busy || !newName.trim(),
                            onClick: function () { mutate('create', '', newName.trim(), kind); }
                        }, sprintf(__('Create %s', 'core-blueprint-notes'), spec.singular)),
                        el(wp.components.Button, {
                            className: 'cb-core-button cb-core-button--secondary',
                            variant: 'secondary',
                            disabled: busy,
                            onClick: function () { setAdding(false); setNewName(''); }
                        }, __('Cancel', 'core-blueprint-notes'))
                    )
                ));
            }
            panel.push(el('div', {
                key: 'items', className: 'cb-notes-organize__items'
            }, list));

            content.push(el('section', {
                key: 'manager', className: 'cb-core-card cb-notes-organize__card',
                'aria-label': sprintf(__('%s management', 'core-blueprint-notes'), spec.title)
            }, el('div', { className: 'cb-core-card__body' }, panel)));
        }

        return el('div', { className: 'cb-notes-organize__stack' }, content);
    }

    wp.element.render(el(App), target);
}());