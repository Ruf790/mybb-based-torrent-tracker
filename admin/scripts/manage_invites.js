'use strict';
/**
 * admin/scripts/manage_invites.js — Invite Manager
 * Функции глобальные: на них ссылаются onclick/onchange в разметке manage_invites.php.
 * SweetAlert2 (/scripts/sweetalert2.min.js) подключается после stdhead(), без него - confirm().
 */

// SweetAlert2-подтверждение, без него - обычный confirm()
function confirmAction({ title, text, confirmText, danger = true }) {
    if (typeof Swal === 'undefined') {
        return Promise.resolve(window.confirm(title + (text ? '\n\n' + text : '')));
    }
    return Swal.fire({
        icon: danger ? 'warning' : 'question',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: danger ? '#dc3545' : '#f0ad4e',
        reverseButtons: true,
        focusCancel: true,
    }).then(r => r.isConfirmed);
}

function showBusy(title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ title, allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
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
        showNotification('No invites selected', 'warning');
        return;
    }
    
    const n = checkboxes.length;
    const isDelete = actionType === 'delete';
    confirmAction({
        title: isDelete ? `Delete ${n} invite(s)?` : `Revoke ${n} invite(s)?`,
        text: isDelete
            ? 'The selected invites will be removed permanently. This cannot be undone.'
            : 'Pending invite codes will stop working. Used and expired invites are not affected.',
        confirmText: isDelete ? 'Delete' : 'Revoke',
        danger: isDelete,
    }).then(ok => {
        if (!ok) return;
        document.getElementById('bulkActionType').value = actionType;
        showBusy(isDelete ? 'Deleting…' : 'Revoking…');
        document.getElementById('bulkForm').submit();
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Invite code copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy code', 'error');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info',
            title: message,
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
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
    `;
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
        title: isDelete ? `Delete invite #${id}?` : `Revoke invite #${id}?`,
        text: isDelete ? 'The invite will be removed permanently.' : 'The invite code will stop working.',
        confirmText: isDelete ? 'Delete' : 'Revoke',
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

    showBusy(type === 'delete' ? 'Deleting…' : 'Revoking…');
    form.submit();
}
