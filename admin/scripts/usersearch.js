/**
 * usersearch.js — passkey toggle/copy + bulk actions + avatar upload (users search/management)
 */
'use strict';

/* ────────────────────────────────────────────────────────────────────────
   i18n: AGS_LANG выводит usersearch.php (ключи js_* без префикса).
   t(key, fallback, ...args) — английский fallback, подстановка {1} / %1$s.
   applyT(root) — переводит [data-t] (textContent) и [data-t-ph] (placeholder);
   английский текст в разметке служит fallback, аргументы — data-t-args через |.
   ──────────────────────────────────────────────────────────────────────── */

function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string')
        ? AGS_LANG[key]
        : String(fallback ?? key);
    args.forEach((arg, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(arg)).split('%' + n + '$s').join(String(arg));
    });
    return s;
}

function applyT(root) {
    root.querySelectorAll('[data-t]').forEach(el => {
        const args = el.dataset.tArgs !== undefined ? el.dataset.tArgs.split('|') : [];
        el.textContent = t(el.dataset.t, el.textContent.trim(), ...args);
    });
    root.querySelectorAll('[data-t-ph]').forEach(el => {
        el.placeholder = t(el.dataset.tPh, el.getAttribute('placeholder') || '');
    });
}

/* Кнопка/контейнер: статичная иконка-спиннер + переведённый текст текстовым узлом */
function setBusy(el, iconHtml, text) {
    el.innerHTML = iconHtml;
    el.appendChild(document.createTextNode(text));
}

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
        showToast(t('passkey_copied', 'Passkey copied!'), 'success');
        var icon = btn.querySelector('i');
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
    }).catch(function () {
        showToast(t('copy_failed', 'Failed to copy'), 'error');
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

    if (action === 'pm') {
        showBulkPmModal(ids);
        return;
    }

    let groupId = null;
    if (action === 'changegroup') {
        const groupSelect = document.getElementById('bulkGroupSelect');
        if (!groupSelect || !groupSelect.value) {
            showToast(t('select_group', 'Please select a group first'), 'warning');
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

    // [value, lang key, English fallback]
    const banTimes = [
        ['1-0-0',  'bt_1d',   '1 Day'],
        ['2-0-0',  'bt_2d',   '2 Days'],
        ['3-0-0',  'bt_3d',   '3 Days'],
        ['4-0-0',  'bt_4d',   '4 Days'],
        ['5-0-0',  'bt_5d',   '5 Days'],
        ['6-0-0',  'bt_6d',   '6 Days'],
        ['7-0-0',  'bt_1w',   '1 Week'],
        ['14-0-0', 'bt_2w',   '2 Weeks'],
        ['21-0-0', 'bt_3w',   '3 Weeks'],
        ['0-1-0',  'bt_1m',   '1 Month'],
        ['0-2-0',  'bt_2m',   '2 Months'],
        ['0-3-0',  'bt_3m',   '3 Months'],
        ['0-4-0',  'bt_4m',   '4 Months'],
        ['0-5-0',  'bt_5m',   '5 Months'],
        ['0-6-0',  'bt_6m',   '6 Months'],
        ['0-0-1',  'bt_1y',   '1 Year'],
        ['0-0-2',  'bt_2y',   '2 Years'],
        ['---',    'bt_perm', 'Permanent']
    ];

    const idsPreview = ids.slice(0,5).join(', ') + (ids.length > 5 ? '...' : '');

    const modalHTML = `
        <div class="modal fade" id="bulkBanModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 glass-card">
                    <div class="modal-header bg-warning text-dark text-center py-4 border-0">
                        <div class="w-100">
                            <i class="fas fa-ban fa-3x mb-3"></i>
                            <h3 class="mb-0" data-t="ban_title">Ban Users</h3>
                        </div>
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="user-info bg-light rounded-4 p-3 mb-4 text-center">
                            <h5 class="fw-bold mb-1" data-t="users_selected" data-t-args="${ids.length}">{1} users selected</h5>
                            <p class="text-muted mb-0 small" data-t="ids" data-t-args="${idsPreview}">IDs: {1}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-clock me-1"></i><span data-t="ban_duration">Ban Duration</span>
                            </label>
                            <select class="form-select" id="banDuration"></select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-chat-text me-1"></i><span data-t="ban_reason">Ban Reason</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="banReason"
                                   placeholder="Enter reason for ban..." data-t-ph="ban_reason_ph"
                                   maxlength="255">
                            <div class="form-text" data-t="ban_reason_hint">Optional. Max 255 characters.</div>
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-warning btn-lg px-4" id="confirmBulkBan">
                                <i class="fas fa-ban me-2"></i><span data-t="ban_confirm" data-t-args="${ids.length}">Ban {1} Users</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i><span data-t="cancel">Cancel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalEl = document.getElementById('bulkBanModal');
    const modal   = new bootstrap.Modal(modalEl);

    applyT(modalEl);
    const durationSelect = modalEl.querySelector('#banDuration');
    banTimes.forEach(([val, key, fallback]) => {
        const opt = document.createElement('option');
        opt.value       = val;
        opt.textContent = t(key, fallback);
        if (val === '---') opt.selected = true;
        durationSelect.appendChild(opt);
    });

    document.getElementById('confirmBulkBan').addEventListener('click', function() {
        const reason  = document.getElementById('banReason').value.trim();
        const bantime = document.getElementById('banDuration').value;

        this.disabled = true;
        setBusy(this, '<i class="fas fa-spinner fa-spin me-2"></i>', t('banning', 'Banning...'));
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

    const idsPreview = ids.slice(0,5).join(', ') + (ids.length > 5 ? '...' : '');

    const modalHTML = `
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 glass-card">
                    <div class="modal-header bg-danger text-white text-center py-4 border-0">
                        <div class="w-100">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h3 class="mb-0" data-t="del_title">Confirm Bulk Deletion</h3>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-5">
                        <h5 class="text-danger mb-3" data-t="del_question">Are you sure you want to delete these accounts?</h5>
                        <div class="user-info bg-light rounded-4 p-4 mb-4 mx-auto" style="max-width:300px;">
                            <h4 class="text-danger fw-bold mb-2" data-t="users_selected" data-t-args="${ids.length}">{1} users selected</h4>
                            <p class="text-muted mb-0" data-t="ids" data-t-args="${idsPreview}">IDs: {1}</p>
                        </div>
                        <p class="text-muted mb-4">
                            <i class="fas fa-info-circle text-info me-1"></i>
                            <span data-t="del_warning">This action cannot be undone. All user data will be permanently removed.</span>
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-danger btn-lg px-4" id="confirmBulkDelete">
                                <i class="fas fa-trash me-2"></i><span data-t="del_confirm" data-t-args="${ids.length}">Yes, Delete {1} Accounts</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i><span data-t="cancel">Cancel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalEl = document.getElementById('bulkDeleteModal');
    const modal   = new bootstrap.Modal(modalEl);
    applyT(modalEl);

    document.getElementById('confirmBulkDelete').addEventListener('click', function() {
        this.disabled = true;
        setBusy(this, '<i class="fas fa-spinner fa-spin me-2"></i>', t('deleting', 'Deleting...'));
        modal.hide();
        executeBulkAction('delete', ids);
    });

    modalEl.addEventListener('hidden.bs.modal', function() { this.remove(); });
    modal.show();
}

function showBulkPmModal(ids) {
    if (document.getElementById('bulkPmModal')) {
        document.getElementById('bulkPmModal').remove();
    }

    const idsPreview = ids.slice(0,5).join(', ') + (ids.length > 5 ? '...' : '');

    const modalHTML = `
        <div class="modal fade" id="bulkPmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 glass-card">
                    <div class="modal-header bg-primary text-white text-center py-4 border-0">
                        <div class="w-100">
                            <i class="bi bi-envelope fa-3x mb-3" style="font-size:2.5rem;"></i>
                            <h3 class="mb-0" data-t="pm_title">Send PM</h3>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="user-info bg-light rounded-4 p-3 mb-4 text-center">
                            <h5 class="fw-bold mb-1" data-t="users_selected" data-t-args="${ids.length}">{1} users selected</h5>
                            <p class="text-muted mb-0 small" data-t="ids" data-t-args="${idsPreview}">IDs: {1}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-card-text me-1"></i><span data-t="pm_subject">Subject</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="bulkPmSubject"
                                   placeholder="Subject..." data-t-ph="pm_subject_ph"
                                   maxlength="200">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-chat-text me-1"></i><span data-t="pm_message">Message</span>
                            </label>
                            <textarea class="form-control"
                                      id="bulkPmMessage"
                                      rows="6"
                                      placeholder="Write your message..." data-t-ph="pm_message_ph"></textarea>
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-primary btn-lg px-4" id="confirmBulkPm">
                                <i class="bi bi-send me-2"></i><span data-t="pm_confirm" data-t-args="${ids.length}">Send to {1} Users</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i><span data-t="cancel">Cancel</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalEl = document.getElementById('bulkPmModal');
    const modal   = new bootstrap.Modal(modalEl);
    applyT(modalEl);

    document.getElementById('confirmBulkPm').addEventListener('click', function() {
        const subject = document.getElementById('bulkPmSubject').value.trim();
        const message = document.getElementById('bulkPmMessage').value.trim();

        if (!subject || !message) {
            showToast(t('pm_fill', 'Please fill in both subject and message'), 'warning');
            return;
        }

        this.disabled = true;
        setBusy(this, '<i class="fas fa-spinner fa-spin me-2"></i>', t('sending', 'Sending...'));
        modal.hide();
        executeBulkAction('pm', ids, null, { pmSubject: subject, pmMessage: message });
    });

    modalEl.addEventListener('hidden.bs.modal', function() { this.remove(); });
    modal.show();
}

function executeBulkAction(action, ids, groupId, extra = {}) {
    const bar = document.getElementById('bulkActionBar');
    const originalHtml = bar.innerHTML;
    bar.innerHTML = '<div class="d-flex align-items-center gap-2">'
        + '<div class="spinner-border spinner-border-sm text-primary"></div></div>';
    bar.firstElementChild.appendChild(document.createTextNode(' ' + t('processing', 'Processing {1} users...', ids.length)));

    const formData = new FormData();
    formData.append('bulk_action', action);
    formData.append('my_post_key', window.myPostKey || '');
    ids.forEach(id => formData.append('user_ids[]', id));
    if (groupId)       formData.append('group_id',   groupId);
    if (extra.reason)  formData.append('ban_reason',  extra.reason);
    if (extra.bantime) formData.append('ban_time',    extra.bantime);
    if (extra.pmSubject) formData.append('pm_subject', extra.pmSubject);
    if (extra.pmMessage) formData.append('pm_message', extra.pmMessage);

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
            showToast(data.error || t('error', 'Error occurred'), 'error');
        }
    })
    .catch(() => {
        bar.innerHTML = originalHtml;
        showToast(t('request_failed', 'Request failed'), 'error');
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

        const file = fileInput.files[0];

        if (!/\.(jpg|jpeg|png|gif|webp)$/i.test(file.name)) {
            alert(t('av_bad_type', 'Allowed JPG/JPEG/PNG/GIF/WebP'));
            fileInput.value = '';
            return;
        }

        // Лимит берётся из data-max-mb на самом инпуте (реальная настройка
        // avatarsize с сервера) — 22 запасное значение, если атрибут вдруг
        // отсутствует/некорректен.
        const maxMb = parseFloat(fileInput.dataset.maxMb) || 22;
        if (file.size > maxMb * 1024 * 1024) {
            alert(t('av_too_big', 'File is too big (max. {1} MB)', maxMb));
            fileInput.value = '';
            return;
        }

        const fd = new FormData();
        fd.append('avatar', file);
        fd.append('id', targetUid);
        fd.append('my_post_key', window.myPostKey || '');

        const box  = targetCell;
        const prev = box.innerHTML;
        box.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:50px;width:50px;font-size:12px;color:#666;"></div>';
        box.firstElementChild.textContent = t('uploading', 'Uploading…');

        fetch(UPLOAD_URL, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(j => {
                if (!j.ok) throw new Error(j.error || t('av_failed', 'Upload failed'));
                const url = (j.href || j.url) + '?v=' + Date.now();
                const img = document.createElement('img');
                img.src       = url;
                img.alt       = t('alt_avatar', 'avatar');
                img.className = 'rounded';
                img.width     = 50;
                box.replaceChildren(img);
            })
            .catch(err => { alert(err.message || t('av_error', 'Upload error')); box.innerHTML = prev; })
            .finally(() => { targetCell = null; targetUid = null; });
    });
})();