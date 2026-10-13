/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.element || !wp.components || !wp.apiFetch) return;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var target = document.getElementById('cb-notes-organization-root');
    if (!target) return;

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
            }).catch(function () { setError('Could not confirm removal. No changes were made.'); });
        }
        var kinds = [
            { label: 'Notebooks', value: 'notebook' },
            { label: 'Categories', value: 'category' },
            { label: 'Tags', value: 'tag' }
        ];
        var nodes = [
            el(wp.components.SelectControl, { key: 'kind', label: 'Manage',
                value: kind, options: kinds,
                onChange: function (value) { setKind(value); setEditing(''); setNewName(''); }
            })
        ];
        if (error) nodes.push(el(wp.components.Notice, {
            key: 'error', status: 'error', isDismissible: true, onRemove: function () { setError(''); }
        }, error));
        if (message) nodes.push(el(wp.components.Notice, {
            key: 'success', status: 'success', isDismissible: true, onRemove: function () { setMessage(''); }
        }, message));
        if (!catalog) {
            nodes.push(el('p', { key: 'loading' }, 'Loading private labels…'));
        } else {
            var items = catalog[kind] || [];
            nodes.push(el('h2', { key: 'title' }, kinds.find(function (item) { return item.value === kind; }).label));
            nodes.push(el('p', { key: 'counts' }, items.length + ' personal labels. Changes are private to your account.'));
            items.forEach(function (item) {
                var isEditing = editing === item.id;
                var row = [el('strong', { key: 'label' }, item.name)];
                if (isEditing) {
                    row.push(el(wp.components.TextControl, {
                        key: 'edit-name', label: 'Rename ' + item.name, value: editName,
                        maxLength: 80, onChange: setEditName
                    }));
                    row.push(el(wp.components.Button, {
                        key: 'save', variant: 'primary', disabled: busy || !editName.trim(),
                        onClick: function () { mutate('rename', item.id, editName.trim()); }
                    }, 'Save'));
                    row.push(el(wp.components.Button, {
                        key: 'cancel', variant: 'secondary', onClick: function () { setEditing(''); }
                    }, 'Cancel'));
                } else {
                    row.push(el(wp.components.Button, {
                        key: 'rename', variant: 'secondary', disabled: busy,
                        onClick: function () { setEditing(item.id); setEditName(item.name); }
                    }, 'Rename'));
                    row.push(el(wp.components.Button, {
                        key: 'remove', variant: 'secondary', isDestructive: true, disabled: busy,
                        onClick: function () { confirmDelete(item); }
                    }, 'Remove'));
                }
                nodes.push(el('div', {
                    key: item.id, style: {
                        display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '12px',
                        padding: '12px 0', borderBottom: '1px solid #7774'
                    }
                }, row));
            });
            nodes.push(el('h3', { key: 'create-h' }, 'Create ' + kind));
            nodes.push(el(wp.components.TextControl, {
                key: 'new-name', label: 'Name', value: newName, onChange: setNewName,
                maxLength: 80, placeholder: 'Name your new ' + kind
            }));
            nodes.push(el(wp.components.Button, {
                key: 'create', variant: 'primary', disabled: busy || !newName.trim(),
                onClick: function () { mutate('create', '', newName.trim()); }
            }, 'Create ' + kind));
            nodes.push(el('p', { key: 'help', className: 'description' },
                'Removing a label does not delete any note. Classification labels are not included in Public Share or Secure Share content.'));
        }
        return el('section', { className: 'cb-notes-organization-manager' }, nodes);
    }
    wp.element.render(el(App), target);
}());