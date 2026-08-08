'use strict';

/* ══════════════════════════════════════════════════════════
   BBCode helpers (доступны глобально до DOMContentLoaded)
   ══════════════════════════════════════════════════════════ */

// Escapes raw HTML so it can never be interpreted as real markup.
// MUST run before any BBCode-to-HTML substitution below.
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        switch (ch) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
        }
    });
}

function parseBBCode(text) {
    return escapeHtml(text)
        .replace(/\[b\](.*?)\[\/b\]/gi,   '<strong>$1</strong>')
        .replace(/\[i\](.*?)\[\/i\]/gi,   '<em>$1</em>')
        .replace(/\[u\](.*?)\[\/u\]/gi,   '<u>$1</u>')
        .replace(/\[s\](.*?)\[\/s\]/gi,   '<s>$1</s>')
        .replace(/\[left\](.*?)\[\/left\]/gis,    '<div style="text-align:left">$1</div>')
        .replace(/\[center\](.*?)\[\/center\]/gis,'<div style="text-align:center">$1</div>')
        .replace(/\[right\](.*?)\[\/right\]/gis,  '<div style="text-align:right">$1</div>')
        .replace(/\[color=(#[\da-fA-F]+|[a-zA-Z]+)\](.*?)\[\/color\]/gi,'<span style="color:$1">$2</span>')
        .replace(/\[size=(\d+)\](.*?)\[\/size\]/gi,'<span style="font-size:$1px">$2</span>')
        .replace(/\[url\](.*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
        .replace(/\[img\](.*?)\[\/img\]/gi,'<img src="$1" alt="" class="rounded" style="max-width:400px">')
        .replace(/\[video\](.*?)\[\/video\]/gi,'<video controls style="max-width:100%"><source src="$1" type="video/mp4"></video>')
        .replace(/\[youtube\](.*?)\[\/youtube\]/gi,'<iframe width="100%" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen referrerpolicy="no-referrer"></iframe>')
        .replace(/\[quote\](.*?)\[\/quote\]/gis,'<blockquote>$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/gis,  '<pre><code>$1</code></pre>')
        .replace(/\[list\](.*?)\[\/list\]/gis,   (_, c) => '<ul>'  + c.replace(/\[\*\](.*)/g,'<li>$1</li>') + '</ul>')
        .replace(/\[list=1\](.*?)\[\/list\]/gis, (_, c) => '<ol>'  + c.replace(/\[\*\](.*)/g,'<li>$1</li>') + '</ol>')
        .replace(/\n/g, '<br>');
}

function wrapBBCode(openTag, closeTag) {
    const ta = document.getElementById('editCommentText');
    if (!ta) return;
    const s    = ta.selectionStart;
    const e    = ta.selectionEnd;
    const sel  = ta.value.substring(s, e);
    ta.value   = ta.value.substring(0, s) + openTag + sel + closeTag + ta.value.substring(e);
    ta.focus();
    ta.setSelectionRange(s + openTag.length, e + openTag.length);
    updatePreview();
}

function wrapBBCodeNear(btn, openTag, closeTag) {
    const ta = btn.closest('.modal-body, .card-body, .bb-editor')?.querySelector('textarea');
    if (!ta) return;
    const s   = ta.selectionStart;
    const e   = ta.selectionEnd;
    const sel = ta.value.substring(s, e);
    ta.value  = ta.value.substring(0, s) + openTag + sel + closeTag + ta.value.substring(e);
    ta.focus();
    ta.setSelectionRange(s + openTag.length, e + openTag.length);
}

function updatePreview() {
    const ta      = document.getElementById('editCommentText');
    const preview = document.getElementById('bbcodePreview');
    if (ta && preview) preview.innerHTML = parseBBCode(ta.value);
}

/* ══════════════════════════════════════════════════════════
   Mass delete — глобальные переменные
   ══════════════════════════════════════════════════════════ */

window.selectedCommentIds = [];
window.selectedTorrentIds = [];

function toggleSelectAll(source) {
    document.querySelectorAll('.comment-checkbox').forEach(cb => { cb.checked = source.checked; });
    toggleMassDeleteButton();
}

function toggleMassDeleteButton() {
    const checked = document.querySelectorAll('.comment-checkbox:checked');
    const btn     = document.getElementById('massDeleteButton');
    if (!btn) return;
    if (checked.length > 0) {
        btn.classList.remove('d-none');
        btn.innerHTML = `<i class="fa-solid fa-trash"></i> Delete Selected (${checked.length})`;
    } else {
        btn.classList.add('d-none');
    }
}

function massDeleteComments() {
    const checked = document.querySelectorAll('.comment-checkbox:checked');
    if (!checked.length) { showToast('Please select at least one comment.', 'warning'); return; }

    window.selectedCommentIds = [];
    window.selectedTorrentIds = [];
    let previewHTML = '';

    checked.forEach(cb => {
        const cid     = cb.value;
        const delBtn  = document.querySelector(`.postbit_qdelete[data-commentid="${cid}"]`);
        window.selectedCommentIds.push(cid);
        window.selectedTorrentIds.push(cb.dataset.tid);
        const author  = delBtn?.getAttribute('data-author')  || 'Unknown';
        const date    = delBtn?.getAttribute('data-date')    || '';
        const preview = delBtn?.getAttribute('data-preview') || '';
        previewHTML += `
        <div class="card mb-2 border-danger border-opacity-25">
            <div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-danger"><i class="fas fa-user me-1"></i>${author}</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="text-muted small">${date}</span>
                    <span class="badge bg-secondary">CID: ${cid}</span>
                </div>
            </div>
            <div class="card-body py-2 px-3 small">
                ${preview ? parseBBCode(preview) : '<span class="text-muted">No content</span>'}
            </div>
        </div>`;
    });

    const countEl = document.getElementById('selectedCommentsCount');
    if (countEl) countEl.textContent = window.selectedCommentIds.length;

    const listEl = document.getElementById('massDeletePreviewList');
    if (listEl) listEl.innerHTML = previewHTML;

    // Используем getInstance чтобы не создавать дубли
    const modalEl = document.getElementById('massDeleteConfirmModal');
    if (!modalEl) return;
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
}

function executeMassDelete() {
    const confirmBtn = document.getElementById('confirmMassDelete');
    if (!confirmBtn) return;
    const originalHTML = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
    confirmBtn.disabled  = true;

    const formData = new FormData();
    formData.append('comment_ids', window.selectedCommentIds.join(','));
    formData.append('torrent_ids', window.selectedTorrentIds.join(','));
    formData.append('my_post_key', window.my_post_key || '');

    fetch('comment.php?action=massdelete', { method: 'POST', body: formData })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            const modalEl = document.getElementById('massDeleteConfirmModal');
            bootstrap.Modal.getInstance(modalEl)?.hide();

            if (!data?.success) {
                showToast('Error: ' + (data?.error || 'Failed to delete comments'), 'danger');
                return;
            }

            const deleted      = data.deleted || window.selectedCommentIds.length;
            const totalOnPage  = document.querySelectorAll('[id^="comment-"]').length;
            const willBeEmpty  = totalOnPage <= window.selectedCommentIds.length;

            showToast(`Successfully deleted ${deleted} comment(s)!`, 'success');

            if (willBeEmpty) {
                const params = new URLSearchParams(window.location.search);
                const page   = parseInt(params.get('page') || '1');
                setTimeout(() => {
                    if (page > 1) { params.set('page', page - 1); window.location.search = params.toString(); }
                    else window.location.reload();
                }, 800);
            } else {
                window.selectedCommentIds.forEach(id => {
                    const el = document.getElementById('comment-' + id);
                    if (!el) return;
                    el.style.transition = 'opacity 0.3s';
                    el.style.opacity    = '0';
                    setTimeout(() => el.remove(), 300);
                });
                updateCommentCounters(deleted);
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error. Please try again.', 'danger');
        })
        .finally(() => {
            confirmBtn.innerHTML = originalHTML;
            confirmBtn.disabled  = false;
            document.querySelectorAll('.comment-checkbox').forEach(cb => { cb.checked = false; });
            const selectAll = document.getElementById('selectAllCheckbox');
            if (selectAll) selectAll.checked = false;
            toggleMassDeleteButton();
            window.selectedCommentIds = [];
            window.selectedTorrentIds = [];
        });
}

function updateCommentCounters(deletedCount) {
    document.querySelectorAll('[class*="comment-count"], [id*="comment-count"]').forEach(el => {
        const n = parseInt(el.textContent);
        if (!isNaN(n)) el.textContent = Math.max(0, n - deletedCount);
    });
}

/* ══════════════════════════════════════════════════════════
   DOMContentLoaded — инициализация модалок
   ══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {
    /* ── Элементы ──────────────────────────────────────────── */
    const editModalEl   = document.getElementById('editCommentModal');
    const deleteModalEl = document.getElementById('deleteCommentModal');
    const editBtn       = document.getElementById('confirmEditComment');
    const deleteBtn     = document.getElementById('confirmDeleteComment');
    const editTextarea  = document.getElementById('editCommentText');

    // Защита: если модалок нет на странице — выходим
    if (!editModalEl || !deleteModalEl) return;

    const MODAL_OPTS  = { backdrop: 'static', keyboard: false };
    const editModal   = new bootstrap.Modal(editModalEl,   MODAL_OPTS);
    const deleteModal = new bootstrap.Modal(deleteModalEl, MODAL_OPTS);

    let commentToEditId   = null;
    let commentToDeleteId = null;
    let torrentId         = null;

    /* ── Live preview textarea ─────────────────────────────── */
    if (editTextarea) editTextarea.addEventListener('input', updatePreview);

    /* ── Делегирование кликов ──────────────────────────────── */
    document.addEventListener('click', function (e) {
        // Редактировать
        const editTrigger = e.target.closest('.edit-comment-btn');
        if (editTrigger) {
            e.preventDefault();
            commentToEditId = editTrigger.dataset.commentid;
            torrentId       = editTrigger.dataset.torrentid;
            if (editTextarea) editTextarea.value = editTrigger.getAttribute('data-commenttext') || '';
            updatePreview();
            editModal.show();
            return;
        }

        // Удалить
        const delTrigger = e.target.closest('.postbit_qdelete');
        if (delTrigger) {
            commentToDeleteId = delTrigger.dataset.commentid;
            torrentId         = delTrigger.dataset.torrentid;
            const safe = id => document.getElementById(id);
            if (safe('commentPreviewAuthor')) safe('commentPreviewAuthor').textContent = delTrigger.getAttribute('data-author')  || 'Unknown';
            if (safe('commentPreviewDate'))   safe('commentPreviewDate').textContent   = delTrigger.getAttribute('data-date')    || '';
            if (safe('commentPreviewId'))     safe('commentPreviewId').textContent     = 'CID: ' + (commentToDeleteId || '');
            if (safe('commentPreviewText'))   safe('commentPreviewText').innerHTML     = parseBBCode(delTrigger.getAttribute('data-preview') || '');
            deleteModal.show();
        }
    });

    /* ── Сохранить редактирование ──────────────────────────── */
    editBtn?.addEventListener('click', function () {
        if (!commentToEditId || !torrentId) return;
        const text = editTextarea?.value.trim();
        if (!text) { showToast('Comment text cannot be empty.', 'warning'); return; }

        const origHTML  = editBtn.innerHTML;
        editBtn.disabled = true;
        editBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        fetch('comment.php?action=edit2', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
     
			body: JSON.stringify({ pid: commentToEditId, tid: torrentId, text, my_post_key: window.my_post_key || '' }),
        })
        .then(res => res.json())
        .then(data => {
            if (!data?.success) { showToast(data?.error || 'Failed to update comment.', 'danger'); return; }
            const container = document.getElementById('comment-' + data.pid);
            if (container && data.html) {
                const tmp   = document.createElement('div');
                tmp.innerHTML = data.html;
                const fresh = tmp.querySelector('#comment-' + data.pid) || tmp.firstElementChild;
                if (fresh) container.replaceWith(fresh);
            }
            editModal.hide();
            showToast('Comment updated successfully.', 'success');
        })
        .catch(() => showToast('Request failed. Please try again.', 'danger'))
        .finally(() => { editBtn.disabled = false; editBtn.innerHTML = origHTML; });
    });

    /* ── Подтвердить удаление ──────────────────────────────── */
    deleteBtn?.addEventListener('click', function () {
        if (!commentToDeleteId || !torrentId) return;

        const origHTML    = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

        fetch('comment.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ pid: commentToDeleteId, tid: torrentId, my_post_key: window.my_post_key || '' }),
        })
        .then(res => res.json())
        .then(data => {
            if (!data?.success) { showToast(data?.error || 'Failed to delete comment.', 'danger'); return; }
            deleteModal.hide();
            showToast('Comment deleted successfully.', 'success');

            const totalOnPage = document.querySelectorAll('[id^="comment-"]').length;
            if (totalOnPage <= 1) {
                const params = new URLSearchParams(window.location.search);
                const page   = parseInt(params.get('page') || '1');
                setTimeout(() => {
                    if (page > 1) { params.set('page', page - 1); window.location.search = params.toString(); }
                    else window.location.reload();
                }, 800);
            } else {
                const el = document.getElementById('comment-' + commentToDeleteId);
                if (el) {
                    el.style.transition = 'opacity 0.3s';
                    el.style.opacity    = '0';
                    setTimeout(() => el.remove(), 300);
                }
            }
        })
        .catch(() => showToast('Request failed. Please try again.', 'danger'))
        .finally(() => { deleteBtn.disabled = false; deleteBtn.innerHTML = origHTML; });
    });

    /* ── Mass delete confirm btn ───────────────────────────── */
    document.getElementById('confirmMassDelete')?.addEventListener('click', executeMassDelete);
});