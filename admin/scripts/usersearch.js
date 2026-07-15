/**
 * usersearch.js — passkey toggle/copy + bulk actions + avatar upload (users search/management)
 */
'use strict';

/* ────────────────────────────────────────────────────────────────────────
   Passkey toggle/copy
   ──────────────────────────────────────────────────────────────────────── */

function togglePasskey(btn) {
    var span    = btn.closest('div').querySelector('.passkey-text');
    var icon    = btn.querySelector('i');
    var passkey = span.dataset.passkey;

    if (span.style.filter === 'none') {
        span.textContent  = passkey.substring(0, 8) + '...';
        span.style.filter = 'blur(4px)';
        span.style.userSelect = 'none';
        icon.className    = 'bi bi-eye';
    } else {
        span.textContent  = passkey;
        span.style.filter = 'none';
        span.style.userSelect = 'text';
        icon.className    = 'bi bi-eye-slash';
    }
}

function copyPasskey(btn) {
    var span    = btn.closest('div').querySelector('.passkey-text');
    var passkey = span.dataset.passkey;

    navigator.clipboard.writeText(passkey).then(function () {
        showToast('Passkey copied!', 'success');
        var icon = btn.querySelector('i');
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
    }).catch(function () {
        showToast('Failed to copy', 'error');
    });
}

/* ────────────────────────────────────────────────────────────────────────
   Bulk actions (checkboxes, ban/unban/changegroup/delete)
   ──────────────────────────────────────────────────────────────────────── */

document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});

document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('user-checkbox')) return;
    updateBulkBar();
    const all     = document.querySelectorAll('.user-checkbox');
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const master  = document.getElementById('checkAll');
    if (master) {
        master.checked       = all.length === checked.length && all.length > 0;
        master.indeterminate = checked.length > 0 && checked.length < all.length;
    }
});

function updateBulkBar() {
    const checked = document.querySelectorAll('.user-checkbox:checked');
    const bar     = document.getElementById('bulkActionBar');
    const counter = document.getElementById('selectedCount');
    if (!bar) return;
    bar.classList.toggle('d-none', checked.length === 0);
    if (counter) counter.textContent = checked.length;
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
}

function clearSelection() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('checkAll');
    if (master) { master.checked = false; master.indeterminate = false; }
    updateBulkBar();
}

function bulkAction(action) {
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    if (action === 'delete') {
        showBulkDeleteConfirmation(ids);
        return;
    }

    if (action === 'ban') {
        showBulkBanConfirmation(ids);
        return;
    }

    let groupId = null;
    if (action === 'changegroup') {
        const groupSelect = document.getElementById('bulkGroupSelect');
        if (!groupSelect || !groupSelect.value) {
            showToast('Please select a group first', 'warning');
            return;
        }
        groupId = groupSelect.value;
    }

    executeBulkAction(action, ids, groupId);
}

function showBulkBanConfirmation(ids) {
    if (document.getElementById('bulkBanModal')) {
        document.getElementById('bulkBanModal').remove();
    }

    const banTimes = {
        '1-0-0':  '1 Day',
        '2-0-0':  '2 Days',
        '3-0-0':  '3 Days',
        '4-0-0':  '4 Days',
        '5-0-0':  '5 Days',
        '6-0-0':  '6 Days',
        '7-0-0':  '1 Week',
        '14-0-0': '2 Weeks',
        '21-0-0': '3 Weeks',
        '0-1-0':  '1 Month',
        '0-2-0':  '2 Months',
        '0-3-0':  '3 Months',
        '0-4-0':  '4 Months',
        '0-5-0':  '5 Months',
        '0-6-0':  '6 Months',
        '0-0-1':  '1 Year',
        '0-0-2':  '2 Years',
        '---':    'Permanent'
    };

    let options = '';
    for (const [val, label] of Object.entries(banTimes)) {
        const selected = val === '---' ? ' selected' : '';
        options += `<option value="${val}"${selected}>${label}</option>`;
    }

    const modalHTML = `
        <div class="modal fade" id="bulkBanModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 glass-card">
                    <div class="modal-header bg-warning text-dark text-center py-4 border-0">
                        <div class="w-100">
                            <i class="fas fa-ban fa-3x mb-3"></i>
                            <h3 class="mb-0">Ban Users</h3>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="user-info bg-light rounded-4 p-3 mb-4 text-center">
                            <h5 class="fw-bold mb-1">${ids.length} users selected</h5>
                            <p class="text-muted mb-0 small">IDs: ${ids.slice(0,5).join(', ')}${ids.length > 5 ? '...' : ''}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock me-1"></i>Ban Duration
                            </label>
                            <select class="form-select" id="banDuration">
                                ${options}
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-chat-text me-1"></i>Ban Reason
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="banReason"
                                   placeholder="Enter reason for ban..."
                                   maxlength="255">
                            <div class="form-text">Optional. Max 255 characters.</div>
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-warning btn-lg px-4" id="confirmBulkBan">
                                <i class="fas fa-ban me-2"></i>Ban ${ids.length} Users
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalEl = document.getElementById('bulkBanModal');
    const modal   = new bootstrap.Modal(modalEl);

    document.getElementById('confirmBulkBan').addEventListener('click', function() {
        const reason  = document.getElementById('banReason').value.trim();
        const bantime = document.getElementById('banDuration').value;

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Banning...';
        modal.hide();
        executeBulkAction('ban', ids, null, { reason, bantime });
    });

    modalEl.addEventListener('hidden.bs.modal', function() { this.remove(); });
    modal.show();
}

function showBulkDeleteConfirmation(ids) {
    if (document.getElementById('bulkDeleteModal')) {
        document.getElementById('bulkDeleteModal').remove();
    }

    const modalHTML = `
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 glass-card">
                    <div class="modal-header bg-danger text-white text-center py-4 border-0">
                        <div class="w-100">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h3 class="mb-0">Confirm Bulk Deletion</h3>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-5">
                        <h5 class="text-danger mb-3">Are you sure you want to delete these accounts?</h5>
                        <div class="user-info bg-light rounded-4 p-4 mb-4 mx-auto" style="max-width:300px;">
                            <h4 class="text-danger fw-bold mb-2">${ids.length} users selected</h4>
                            <p class="text-muted mb-0">IDs: ${ids.slice(0,5).join(', ')}${ids.length > 5 ? '...' : ''}</p>
                        </div>
                        <p class="text-muted mb-4">
                            <i class="fas fa-info-circle text-info me-1"></i>
                            This action cannot be undone. All user data will be permanently removed.
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-danger btn-lg px-4" id="confirmBulkDelete">
                                <i class="fas fa-trash me-2"></i>Yes, Delete ${ids.length} Accounts
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalEl = document.getElementById('bulkDeleteModal');
    const modal   = new bootstrap.Modal(modalEl);

    document.getElementById('confirmBulkDelete').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
        modal.hide();
        executeBulkAction('delete', ids);
    });

    modalEl.addEventListener('hidden.bs.modal', function() { this.remove(); });
    modal.show();
}

function executeBulkAction(action, ids, groupId, extra = {}) {
    const bar = document.getElementById('bulkActionBar');
    const originalHtml = bar.innerHTML;
    bar.innerHTML = '<div class="d-flex align-items-center gap-2">'
        + '<div class="spinner-border spinner-border-sm text-primary"></div>'
        + ' Processing ' + ids.length + ' users...</div>';

    const formData = new FormData();
    formData.append('bulk_action', action);
    formData.append('my_post_key', window.myPostKey || '');
    ids.forEach(id => formData.append('user_ids[]', id));
    if (groupId)       formData.append('group_id',   groupId);
    if (extra.reason)  formData.append('ban_reason',  extra.reason);
    if (extra.bantime) formData.append('ban_time',    extra.bantime);

    fetch(window.location.href, {
        method:  'POST',
        body:    formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            ids.forEach(id => {
                const cb  = document.querySelector('.user-checkbox[value="' + id + '"]');
                const row = cb?.closest('tr');
                if (!row) return;
                if (action === 'ban') {
                    row.classList.add('table-warning');
                    row.classList.remove('table-success');
                } else if (action === 'unban') {
                    row.classList.add('table-success');
                    row.classList.remove('table-warning');
                } else if (action === 'changegroup') {
                    row.classList.add('table-info');
                } else if (action === 'delete') {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity    = '0';
                    setTimeout(() => row.remove(), 300);
                }
            });
            clearSelection();
            showToast(data.message, 'success');
        } else {
            bar.innerHTML = originalHtml;
            showToast(data.error || 'Error occurred', 'error');
        }
    })
    .catch(() => {
        bar.innerHTML = originalHtml;
        showToast('Request failed', 'error');
    });
}

/* ────────────────────────────────────────────────────────────────────────
   Avatar upload (клик по ячейке аватара в таблице)
   ──────────────────────────────────────────────────────────────────────── */

(function () {
    const fileInput = document.getElementById('avatarUploadInput');
    if (!fileInput) return;

    const UPLOAD_URL = 'index.php?act=usersearch&action=upload_avatar';
    let targetCell = null, targetUid = null;

    document.addEventListener('click', (e) => {
        const cell = e.target.closest('td[data-avatar-cell]');
        if (!cell) return;
        targetCell = cell;
        targetUid  = cell.dataset.uid;
        fileInput.value = '';
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        if (!fileInput.files || !fileInput.files[0] || !targetUid) return;

        const fd = new FormData();
        fd.append('avatar', fileInput.files[0]);
        fd.append('id', targetUid);
        fd.append('my_post_key', window.myPostKey || '');

        const box  = targetCell;
        const prev = box.innerHTML;
        box.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:50px;width:50px;font-size:12px;color:#666;">Uploading…</div>';

        fetch(UPLOAD_URL, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(j => {
                if (!j.ok) throw new Error(j.error || 'Upload failed');
                const url = (j.href || j.url) + '?v=' + Date.now();
                box.innerHTML = '<img src="' + url + '" alt="avatar" class="rounded" width="50">';
            })
            .catch(err => { alert(err.message || 'Upload error'); box.innerHTML = prev; })
            .finally(() => { targetCell = null; targetUid = null; });
    });
})();