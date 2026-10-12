/* contactstaff.js — ArtCore Gangsta, Contact Staff page */
(function () {
    'use strict';

    // ── i18n ──────────────────────────────────────────────────────────────
    var L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    /** t(key, fallback, ...args) — {1} and %1$s placeholders. */
    function t(key, fallback) {
        var str  = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        var args = Array.prototype.slice.call(arguments, 2);
        return String(str).replace(/\{(\d+)\}|%(\d+)\$[sd]/g, function (m, a, b) {
            var i = parseInt(a || b, 10) - 1;
            return (i >= 0 && i < args.length) ? String(args[i]) : m;
        });
    }

    var form      = document.getElementById('staffForm');
    var textarea  = document.getElementById('staffMessage');
    var charCount = document.getElementById('charCount');
    if (!form || !textarea || !charCount) { return; }

    // ── Char counter ──────────────────────────────────────────────────────
    var maxLen = parseInt(textarea.getAttribute('maxlength'), 10) || 1000;

    function updateCount() {
        var len = textarea.value.length;
        charCount.textContent = t('char_count', '{1} / {2}', len, maxLen);
        charCount.classList.toggle('text-danger', len >= maxLen);
        charCount.classList.toggle('text-warning', len >= maxLen * 0.9 && len < maxLen);
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    // ── Submit button state ───────────────────────────────────────────────
    var btn       = document.getElementById('staffSubmit');
    var btnIcon   = document.getElementById('staffSubmitIcon');
    var btnLabel  = document.getElementById('staffSubmitLabel');
    var origLabel = btnLabel ? btnLabel.textContent : '';
    var origIcon  = btnIcon ? btnIcon.className : '';

    function setBusy(busy) {
        if (!btn) { return; }
        btn.disabled = busy;
        if (btnIcon)  { btnIcon.className = busy ? 'fa-solid fa-spinner fa-spin me-2' : origIcon; }
        if (btnLabel) { btnLabel.textContent = busy ? t('sending', 'Sending…') : origLabel; }
    }

    // ── AJAX submit ───────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(form);
        formData.append('ajax', '1');
        setBusy(true);

        fetch(form.action, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ok  = !!(data && data.success);
                var msg = (data && typeof data.message === 'string' && data.message !== '')
                    ? data.message
                    : t('submit_failed', 'Submission failed. Please try again.');
                showToast(msg, ok ? 'success' : 'danger');
                if (ok) { form.reset(); updateCount(); }
            })
            .catch(function () {
                showToast(t('submit_failed', 'Submission failed. Please try again.'), 'danger');
            })
            .then(function () { setBusy(false); });
    });

    // ── Toast ─────────────────────────────────────────────────────────────
    function showToast(message, type) {
        var el   = document.getElementById('formToast');
        var body = document.getElementById('formToastBody');
        var icon = document.getElementById('formToastIcon');
        type = type || 'success';
        el.className = 'toast align-items-center text-white bg-' + type + ' border-0';
        if (icon) {
            icon.className = 'fa-solid ms-3 ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation');
        }
        body.textContent = message;
        if (window.bootstrap && window.bootstrap.Toast) {
            new window.bootstrap.Toast(el).show();
        }
    }
}());
