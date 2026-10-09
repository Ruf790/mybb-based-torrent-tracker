'use strict';
/**
 * admin/scripts/manage_invites.js — Invite Manager
 * Функции глобальные: на них ссылаются onclick/onchange в разметке manage_invites.php.
 * SweetAlert2 (/scripts/sweetalert2.min.js) подключается после stdhead(), без него - confirm().
 * Строки берутся из AGS_LANG (js_* ключи ланга manage_invites, выводятся PHP перед скриптом).
 */

// Перевод: t(key, englishFallback, ...args) — {1} и %1$s ($lang->load() переводит {N} в %N$s)
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    let s = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
    args.forEach((arg, i) => {
        const n = i + 1;
        const v = String(arg);
        s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
    });
    return s;
}

// SweetAlert2 вставляет тексты кнопок как HTML — экранируем
function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

// SweetAlert2-подтверждение, без него - обычный confirm()
function confirmAction({ title, text, confirmText, danger = true }) {
    if (typeof Swal === 'undefined') {
        return Promise.resolve(window.confirm(title + (text ? '\n\n' + text : '')));
    }
    return Swal.fire({
        icon: danger ? 'warning' : 'question',
        titleText: title,
        text,
        showCancelButton: true,
        confirmButtonText: escHtml(confirmText),
        cancelButtonText: escHtml(t('cancel', 'Cancel')),
        confirmButtonColor: danger ? '#dc3545' : '#f0ad4e',
        reverseButtons: true,
        focusCancel: true,
    }).then(r => r.isConfirmed);
}

function showBusy(title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ titleText: title, allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
    }
}

// Select/Deselect all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBar();
}

// Update bulk actions bar visibility
function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    const count = checkboxes.length;
    const bar = document.getElementById('bulkActionsBar');

    if (count > 0) {
        bar.style.display = 'block';
        document.getElementById('selectedCount').innerText = count;
    } else {
        bar.style.display = 'none';
    }
}

// Clear all selections
function clearSelection() {
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    if (document.getElementById('selectAll')) {
        document.getElementById('selectAll').checked = false;
    }
    updateBulkBar();
}

// Bulk action
function bulkAction(actionType) {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    if (checkboxes.length === 0) {
        showNotification(t('no_selected', 'No invites selected'), 'warning');
        return;
    }

    const n = checkboxes.length;
    const isDelete = actionType === 'delete';
    confirmAction({
        title: isDelete
            ? t('bulk_delete_title', 'Delete {1} invite(s)?', n)
            : t('bulk_revoke_title', 'Revoke {1} invite(s)?', n),
        text: isDelete
            ? t('bulk_delete_text', 'The selected invites will be removed permanently. This cannot be undone.')
            : t('bulk_revoke_text', 'Pending invite codes will stop working. Used and expired invites are not affected.'),
        confirmText: isDelete ? t('delete', 'Delete') : t('revoke', 'Revoke'),
        danger: isDelete,
    }).then(ok => {
        if (!ok) return;
        document.getElementById('bulkActionType').value = actionType;
        showBusy(isDelete ? t('deleting', 'Deleting…') : t('revoking', 'Revoking…'));
        document.getElementById('bulkForm').submit();
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification(t('copied', 'Invite code copied to clipboard!'), 'success');
    }).catch(() => {
        showNotification(t('copy_failed', 'Failed to copy code'), 'error');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info',
            titleText: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.style.borderRadius = '12px';
    notification.style.fontSize = '0.95rem';
    notification.style.fontWeight = '500';
    notification.style.animation = 'fadeInUp 0.3s ease-out';

    const icon = document.createElement('i');
    icon.className = `fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2`;
    notification.appendChild(icon);
    notification.appendChild(document.createTextNode(message));

    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBulkBar();
});

function singleAction(type, id) {
    const isDelete = type === 'delete';
    confirmAction({
        title: isDelete
            ? t('single_delete_title', 'Delete invite #{1}?', id)
            : t('single_revoke_title', 'Revoke invite #{1}?', id),
        text: isDelete
            ? t('single_delete_text', 'The invite will be removed permanently.')
            : t('single_revoke_text', 'The invite code will stop working.'),
        confirmText: isDelete ? t('delete', 'Delete') : t('revoke', 'Revoke'),
        danger: isDelete,
    }).then(ok => { if (ok) submitSingleAction(type, id); });
}

function submitSingleAction(type, id) {
    const form = document.getElementById('bulkForm');

    // Убираем старые hidden inputs если есть
    ['singleFlag', 'singleId'].forEach(eid => {
        const el = document.getElementById(eid);
        if (el) el.remove();
    });

    // Снимаем все switches
    document.querySelectorAll('.invite-checkbox').forEach(cb => cb.checked = false);

    // Добавляем нужные поля
    const flag = document.createElement('input');
    flag.type = 'hidden';
    flag.id   = 'singleFlag';
    flag.name = type === 'revoke' ? 'admin_revoke' : 'admin_delete';
    flag.value = '1';
    form.appendChild(flag);

    const invId = document.createElement('input');
    invId.type  = 'hidden';
    invId.id    = 'singleId';
    invId.name  = 'invite_id';
    invId.value = id;
    form.appendChild(invId);

    showBusy(type === 'delete' ? t('deleting', 'Deleting…') : t('revoking', 'Revoking…'));
    form.submit();
}
