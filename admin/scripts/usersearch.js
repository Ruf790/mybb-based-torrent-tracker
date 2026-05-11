/**
 * usersearch.js — passkey toggle/copy helpers
 */
'use strict';

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
