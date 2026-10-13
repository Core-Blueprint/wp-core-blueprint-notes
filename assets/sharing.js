/* global wp */
(function () {
    'use strict';
    if (!wp || !wp.plugins || !wp.editPost || !wp.components || !wp.element || !wp.data || !wp.apiFetch) {
        return;
    }
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
                .catch(function () { if (active) setError('Sharing settings are unavailable.'); });
            return function () { active = false; };
        }, [id]);

        function requestMode(mode, confirmation) {
            if (!sharing || busy || (mode !== 'private' && (editor.dirty || editor.saving))) {
                setError('Save the note first, then review the sharing settings.');
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
                    setError(failure.message || 'Sharing could not be changed. Nothing new was published.');
                })
                .finally(function () { setBusy(false); });
        }

        function confirmPublic() {
            if (editor.dirty || editor.saving) {
                setError('Save the note before making a public version.');
                return;
            }
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError('The required Core Blueprint confirmation modal is unavailable. Nothing was published.');
                return;
            }
            modal.show({
                title: 'Make this note public?',
                body: 'A frozen copy of this note will be accessible to anyone with the public URL, without a login or password. Others can copy it even if you stop sharing later. Only supported text blocks are included. Later edits will not update this shared copy.',
                confirmVariant: 'danger',
                confirmLabel: 'Make public',
                cancelLabel: 'Keep private',
                typedConfirm: 'PUBLIC',
                confirmCheck: {
                    label: 'I understand that anyone can access and copy the public version.'
                }
            }).then(function (accepted) {
                if (accepted === true) return requestMode('public', 'PUBLIC');
            }).catch(function () {
                setError('The required confirmation could not be completed. Nothing was published.');
            });
        }

        function stopSharing() {
            if (!sharing || sharing.mode === 'private') return;
            var modal = window.cbCore && window.cbCore.modal;
            if (!modal || typeof modal.show !== 'function') {
                setError('The confirmation modal is unavailable.');
                return;
            }
            modal.show({
                title: 'Stop sharing this note?',
                body: 'Existing public or secure links will stop delivering this note. Copies already made by recipients cannot be recalled.',
                confirmVariant: 'primary',
                confirmLabel: 'Stop sharing'
            }).then(function (accepted) {
                if (accepted === true) return requestMode('private');
            }).catch(function () { setError('Could not complete confirmation.'); });
        }

        var mode = sharing && sharing.mode ? sharing.mode : 'private';
        var controls = [
            el('p', { key: 'info', className: 'components-base-control__help' },
                'Notes stay private when you use the WordPress Publish button. Sharing requires a separate explicit action here.'),
            el('p', { key: 'status' }, el('strong', null, 'Access: '),
                mode === 'public' ? 'Public link' : mode === 'secure' ? 'Secure link' : 'Private')
        ];
        if (error) controls.push(el(wp.components.Notice, {
            key: 'error', status: 'error', isDismissible: true, onRemove: function () { setError(''); }
        }, error));
        if (!sharing) {
            controls.push(el('p', { key: 'loading' }, 'Loading sharing settings…'));
        } else {
            var blocked = busy || editor.dirty || editor.saving;
            if (editor.dirty) controls.push(el('p', { key: 'save' }, 'Save your changes before sharing.'));
            if (mode === 'public' && sharing.public_url) {
                controls.push(el(wp.components.TextControl, {
                    key: 'public-link', label: 'Public URL', value: sharing.public_url,
                    readOnly: true, onChange: function () {}
                }));
            }
            if (secretLink) {
                controls.push(el(wp.components.TextControl, {
                    key: 'secure-link', label: 'Secure invitation link (copy now)', value: secretLink,
                    readOnly: true, onChange: function () {}
                }));
                controls.push(el('p', { key: 'secret-warning' },
                    'This secure invitation cannot be retrieved later. Keep it confidential.'));
            }
            if (mode !== 'private') {
                controls.push(el(wp.components.Button, {
                    key: 'stop', variant: 'secondary', isBusy: busy, disabled: busy,
                    onClick: stopSharing
                }, 'Stop sharing'));
            } else {
                controls.push(el(wp.components.Button, {
                    key: 'public', variant: 'secondary', isDestructive: true,
                    disabled: blocked, onClick: confirmPublic
                }, 'Make public…'));
                if (sharing.secure_available) {
                    controls.push(el(wp.components.TextControl, {
                        key: 'password', label: 'Password or PIN (optional)', type: 'password',
                        value: password, onChange: setPassword, autoComplete: 'new-password'
                    }));
                    controls.push(el(wp.components.SelectControl, {
                        key: 'expiry', label: 'Secure link expires after',
                        value: days, onChange: setDays,
                        options: [
                            { label: '1 day', value: '1' },
                            { label: '7 days', value: '7' },
                            { label: '30 days', value: '30' }
                        ]
                    }));
                    controls.push(el(wp.components.Button, {
                        key: 'secure', variant: 'secondary', disabled: blocked,
                        onClick: function () { requestMode('secure'); }
                    }, 'Create secure link'));
                } else {
                    controls.push(el('p', { key: 'unavailable', className: 'components-base-control__help' },
                        'Secure Share is optional and currently unavailable.'));
                }
            }
        }
        return el(Panel, { name: 'cb-notes-sharing', title: 'Sharing' }, controls);
    }

    wp.plugins.registerPlugin('cb-notes-sharing', { render: SharingPanel, icon: 'share' });
}());
