/**
 * Manage Uploads - Edit / Move / Copy modals
 * Config comes from data-* attributes on .mu-page (data-post-key, data-script-url).
 */
'use strict';

(function() {
    const cfg = document.querySelector('.mu-page')?.dataset || {};
    const myPostKey = cfg.postKey || '';
    const scriptUrl = cfg.scriptUrl || location.pathname + location.search;

    function toast(msg, type) {
        if (typeof showToast === 'function') { showToast(msg, type); }
        else if (typeof Swal !== 'undefined') {
            const icon = ({ success: 'success', error: 'error', warning: 'warning' })[type] || 'info';
            Swal.fire({ icon, title: msg });
        }
        else { alert(msg); }
    }

    // ── Edit ──────────────────────────────────────────────
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('editId').value = t.getAttribute('data-id') || '';
        document.getElementById('editFileName').value = t.getAttribute('data-file-name') || '';
        document.getElementById('editCommentId').value = t.getAttribute('data-comment-id') || '';
        document.getElementById('editNewsId').value = t.getAttribute('data-news-id') || '';
        document.getElementById('editTorrentId').value = t.getAttribute('data-torrent-id') || '';
        document.getElementById('editPostId').value = t.getAttribute('data-post-id') || '';
        document.getElementById('editUserId').value = t.getAttribute('data-user-id') || '';
    });

    document.getElementById('editSaveBtn').addEventListener('click', function() {
        const btn = this;
        const formData = new FormData();
        formData.append('update', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('editId').value);
        formData.append('file_name', document.getElementById('editFileName').value);
        formData.append('comment_id', document.getElementById('editCommentId').value);
        formData.append('news_id', document.getElementById('editNewsId').value);
        formData.append('torrent_id', document.getElementById('editTorrentId').value);
        formData.append('post_id', document.getElementById('editPostId').value);
        formData.append('user_id', document.getElementById('editUserId').value);

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(editModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Update failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });

    // ── Move ──────────────────────────────────────────────
    const moveModal = document.getElementById('moveModal');
    function updateMoveBbcode() {
        const url = document.getElementById('movePreviewImg').src;
        document.getElementById('moveBbcodeTag').value = url ? '[img]' + url + '[/img]' : '';
    }
    function updateMoveInsertNote() {
        const type = document.getElementById('moveContentType').value;
        const checkbox = document.getElementById('moveInsertTag');
        const note = document.getElementById('moveInsertTagNote');
        if (type === 'message') {
            checkbox.checked = false;
            checkbox.disabled = true;
            note.textContent = 'The tag is always removed from the old location. Not inserted automatically for Messages - private message text is never modified automatically.';
        } else {
            checkbox.disabled = false;
            note.textContent = 'The tag is always removed from the old location.';
        }
    }
    moveModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('moveId').value = t.getAttribute('data-id') || '';
        document.getElementById('movePreviewImg').src = t.getAttribute('data-file-url') || '';
        document.getElementById('moveContentType').value = '';
        document.getElementById('moveContentId').value = '';
        document.getElementById('moveInsertTag').checked = true;
        document.getElementById('moveInsertTag').disabled = false;
        document.getElementById('moveInsertTagNote').textContent = 'The tag is always removed from the old location.';
        updateMoveBbcode();
    });
    document.getElementById('moveContentType').addEventListener('change', updateMoveInsertNote);
    document.getElementById('moveCopyBbcodeBtn').addEventListener('click', function() {
        const input = document.getElementById('moveBbcodeTag');
        input.select();
        navigator.clipboard?.writeText(input.value).then(() => toast('Copied to clipboard', 'success'));
    });
    document.getElementById('moveSaveBtn').addEventListener('click', function() {
        const btn = this;
        const contentType = document.getElementById('moveContentType').value;
        const contentId   = document.getElementById('moveContentId').value.trim();
        if (!contentType || !contentId) { toast('Content type and ID are required', 'error'); return; }

        const formData = new FormData();
        formData.append('ajax_move', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('moveId').value);
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('insert_tag', document.getElementById('moveInsertTag').checked ? '1' : '');

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(moveModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Move failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });

    // ── Copy ──────────────────────────────────────────────
    const copyModal = document.getElementById('copyModal');
    function updateCopyBbcode() {
        const url = document.getElementById('copyPreviewImg').src;
        document.getElementById('copyBbcodeTag').value = url ? '[img]' + url + '[/img]' : '';
    }
    function updateCopyInsertNote() {
        const type = document.getElementById('copyContentType').value;
        const checkbox = document.getElementById('copyInsertTag');
        const note = document.getElementById('copyInsertTagNote');
        if (type === 'message') {
            checkbox.checked = false;
            checkbox.disabled = true;
            note.textContent = 'Not available for Messages - private message text is never modified automatically.';
        } else {
            checkbox.disabled = false;
            note.textContent = '';
        }
    }
    copyModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('copyId').value = t.getAttribute('data-id') || '';
        document.getElementById('copyPreviewImg').src = t.getAttribute('data-file-url') || '';
        document.getElementById('copyContentType').value = '';
        document.getElementById('copyContentId').value = '';
        document.getElementById('copyInsertTag').checked = true;
        document.getElementById('copyInsertTag').disabled = false;
        document.getElementById('copyInsertTagNote').textContent = '';
        updateCopyBbcode();
    });
    document.getElementById('copyContentType').addEventListener('change', updateCopyInsertNote);
    document.getElementById('copyCopyBbcodeBtn').addEventListener('click', function() {
        const input = document.getElementById('copyBbcodeTag');
        input.select();
        navigator.clipboard?.writeText(input.value).then(() => toast('Copied to clipboard', 'success'));
    });
    document.getElementById('copySaveBtn').addEventListener('click', function() {
        const btn = this;
        const contentType = document.getElementById('copyContentType').value;
        const contentId   = document.getElementById('copyContentId').value.trim();
        if (!contentType || !contentId) { toast('Content type and ID are required', 'error'); return; }

        const formData = new FormData();
        formData.append('ajax_copy', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('copyId').value);
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('insert_tag', document.getElementById('copyInsertTag').checked ? '1' : '');

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(copyModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Copy failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });
})();
