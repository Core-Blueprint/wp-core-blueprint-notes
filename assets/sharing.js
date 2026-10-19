/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.plugins || !wp.editPost || !wp.components || !wp.element || !wp.data || !wp.apiFetch) {
        return;
    }
    var __ = wp.i18n && typeof wp.i18n.__ === 'function'
        ? wp.i18n.__ : function (message) { return message; };
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var Panel = wp.editPost.PluginDocumentSettingPanel;
    if (!Panel) return;

    function SharingPanel() {
        var editor = wp.data.useSelect(function (select) {
            var store = select('core/editor');
            return {
                id: store.getCurrentPostId(),
                dirty: store.isEditedPostDirty(),
                saving: store.isSavingPost()
            };
        }, []);
        var id = editor.id;
        var state = useState(null), sharing = state[0], setSharing = state[1];
        var busyState = useState(false), busy = busyState[0], setBusy = busyState[1];
        var errorState = useState(''), error = errorState[0], setError = errorState[1];
        var linkState = useState(''), secretLink = linkState[0], setSecretLink = linkState[1];
        var passwordState = useState(''), password = passwordState[0], setPassword = passwordState[1];
        var daysState = useState('7'), days = daysState[0], setDays = daysState[1];

        function path() {
            return '/core-blueprint-notes/v1/sharing/' + id;
        }
        useEffect(function () {
            if (!id) return;
            var active = true;
            wp.apiFetch({ path: '/core-blueprint-notes/v1/sharing/' + id })
                .then(function (result) { if (active) setSharing(result); })
                .catch(function () { if (active) setError(__('Sharing settings are unavailable.', 'core-blueprint-notes')); });
            return function () { active = false; };
        }, [id]);

        function requestMode(mode, confirmation) {
            if (!sharing || busy || (mode !== 'private' && (editor.dirty || editor.saving))) {
                setError(__('Save the note first, then review the sharing settings.', 'core-blueprint-notes'));
                return Promise.resolve();
            }
            setBusy(true);
            setError('');
            setSecretLink('');
            var data = {
                mode: mode,
                expected_modified_gmt: sharing.modified_gmt,
                expected_content_version: sharing.content_version,
                expected_mode: sharing.mode,
                confirmation: confirmation || ''
            };
            if (mode === 'secure') {
                data.password = password;
                data.expiry_days = Number(days);
            }
            return wp.apiFetch({ path: path(), method: 'POST', data: data })
                .then(function (response) {
                    if (response.invitation_url) setSecretLink(response.invitation_url);
                    setSharing(response);
                    setPassword('');
                })
                .catch(function (failure) {
                    setError(failure.message || __('Sharing could not be changed. Nothing new was published.', 'core-blueprint-notes'));
                })
                .finally(function () { setBusy(false); });
        }

        function confirmPublic() {
            if (editor.dirty || editor.saving) {
                setError(__('Save the note before making a public version.', 'core-blueprint-notes'));
                return;
            }
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError(__('The required Core Blueprint confirmation modal is unavailable. Nothing was published.', 'core-blueprint-notes'));
                return;
            }
            modal.show({
                title: __('Make this note public?', 'core-blueprint-notes'),
                body: __('A frozen copy of this note will be accessible to anyone with the public URL, without a login or password. Others can copy it even if you stop sharing later. Only supported text blocks are included. Later edits will not update this shared copy.', 'core-blueprint-notes'),
                confirmVariant: 'danger',
                confirmLabel: __('Make public', 'core-blueprint-notes'),
                cancelLabel: __('Keep private', 'core-blueprint-notes'),
                typedConfirm: 'PUBLIC',
                confirmCheck: {
                    label: __('I understand that anyone can access and copy the public version.', 'core-blueprint-notes')
                }
            }).then(function (accepted) {
                if (accepted === true) return requestMode('public', 'PUBLIC');
            }).catch(function () {
                setError(__('The required confirmation could not be completed. Nothing was published.', 'core-blueprint-notes'));
            });
        }

        function stopSharing() {
            if (!sharing || sharing.mode === 'private') return;
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError(__('The confirmation modal is unavailable.', 'core-blueprint-notes'));
                return;
            }
            modal.show({
                title: __('Stop sharing this note?', 'core-blueprint-notes'),
                body: __('Existing public or secure links will stop delivering this note. Copies already made by recipients cannot be recalled.', 'core-blueprint-notes'),
                confirmVariant: 'primary',
                confirmLabel: __('Stop sharing', 'core-blueprint-notes')
            }).then(function (accepted) {
                if (accepted === true) return requestMode('private');
            }).catch(function () { setError(__('Could not complete confirmation.', 'core-blueprint-notes')); });
        }

        var mode = sharing && sharing.mode ? sharing.mode : 'private';
        var status = !sharing ? __('Checking…', 'core-blueprint-notes') :
            mode === 'public' && !sharing.public_valid ? __('Public copy unavailable', 'core-blueprint-notes') :
            mode === 'public' ? __('Public copy active', 'core-blueprint-notes') :
            mode === 'secure' ? __('Secure copy active', 'core-blueprint-notes') : __('Private', 'core-blueprint-notes');
        var guidance = mode === 'private'
            ? __('Saving or publishing in Gutenberg keeps the note private. Sharing requires a separate action below.', 'core-blueprint-notes')
            : __('The original note stays private. Saving changes will not update the frozen shared copy. Use Stop sharing to revoke access.', 'core-blueprint-notes');
        var controls = [
            el('p', { key: 'status' }, el('strong', null, __('Sharing status: ', 'core-blueprint-notes')), status),
            el('p', { key: 'info', className: 'components-base-control__help' }, guidance)
        ];
        if (error) controls.push(el(wp.components.Notice, {
            key: 'error', status: 'error', isDismissible: true, onRemove: function () { setError(''); }
        }, error));
        if (!sharing) {
            controls.push(el('p', { key: 'loading' }, __('Loading sharing settings…', 'core-blueprint-notes')));
        } else {
            var blocked = busy || editor.dirty || editor.saving;
            if (!sharing.public_ready) {
                controls.push(el(wp.components.Notice, {
                    key: 'public-recovery', status: 'warning', isDismissible: false
                }, __('Public sharing is unavailable until your site operator configures the independent recovery key. Private notes and Secure Share remain available.', 'core-blueprint-notes')));
            } else if (mode === 'public' && !sharing.public_valid) {
                controls.push(el(wp.components.Notice, {
                    key: 'public-legacy', status: 'warning', isDismissible: false
                }, __('This previous public link is no longer valid. Stop sharing, then create a new link if needed.', 'core-blueprint-notes')));
            }
            if (editor.dirty && mode === 'private') controls.push(el('p', { key: 'save' }, __('Save your changes before creating a shared copy.', 'core-blueprint-notes')));
            if (editor.dirty && mode !== 'private') controls.push(el('p', { key: 'save' }, __('Unsaved edits are not visible to recipients. You can still stop sharing.', 'core-blueprint-notes')));
            if (mode === 'public' && sharing.public_url) {
                controls.push(el(wp.components.TextControl, {
                    key: 'public-link', label: __('Public URL', 'core-blueprint-notes'), value: sharing.public_url,
                    readOnly: true, onChange: function () {}
                }));
            }
            if (secretLink) {
                controls.push(el(wp.components.TextControl, {
                    key: 'secure-link', label: __('Secure invitation link (copy now)', 'core-blueprint-notes'), value: secretLink,
                    readOnly: true, onChange: function () {}
                }));
                controls.push(el('p', { key: 'secret-warning' },
                    __('This secure invitation cannot be retrieved later. Keep it confidential.', 'core-blueprint-notes')));
            }
            if (mode !== 'private') {
                controls.push(el(wp.components.Button, {
                    key: 'stop', variant: 'secondary', isBusy: busy, disabled: busy,
                    onClick: stopSharing
                }, __('Stop sharing', 'core-blueprint-notes')));
            } else {
                controls.push(el(wp.components.Button, {
                    key: 'public', variant: 'secondary', isDestructive: true,
                    disabled: blocked || !sharing.public_ready, onClick: confirmPublic
                }, __('Make public…', 'core-blueprint-notes')));
                if (sharing.secure_available) {
                    controls.push(el(wp.components.TextControl, {
                        key: 'password', label: __('Password or PIN (optional)', 'core-blueprint-notes'), type: 'password',
                        value: password, onChange: setPassword, autoComplete: 'new-password'
                    }));
                    controls.push(el(wp.components.SelectControl, {
                        key: 'expiry', label: __('Secure link expires after', 'core-blueprint-notes'),
                        value: days, onChange: setDays,
                        options: [
                            { label: __('1 day', 'core-blueprint-notes'), value: '1' },
                            { label: __('7 days', 'core-blueprint-notes'), value: '7' },
                            { label: __('30 days', 'core-blueprint-notes'), value: '30' }
                        ]
                    }));
                    controls.push(el(wp.components.Button, {
                        key: 'secure', variant: 'secondary', disabled: blocked,
                        onClick: function () { requestMode('secure'); }
                    }, __('Create secure link', 'core-blueprint-notes')));
                }
            }
        }
        return el(Panel, {
            name: 'cb-notes-sharing',
            title: __('Sharing', 'core-blueprint-notes'),
            className: 'cb-notes-sharing-panel'
        }, controls);
    }

    wp.plugins.registerPlugin('cb-notes-sharing', { render: SharingPanel, icon: 'share' });
}());
