/**
 * ags-dialog.js — SweetAlert2 вместо alert()/confirm(), с откатом на нативные,
 * если Swal на странице нет.
 *
 *   agsAlert(text, icon = 'warning')         → Promise<void>
 *   agsConfirm(text, { icon, danger, confirmText, cancelText }) → Promise<boolean>
 *
 * Разметка без JS: data-ags-confirm="Текст" на <button type="submit"> или <a href>.
 * Клик перехватывается, после «Да» форма отправляется (с этой кнопкой как submitter)
 * или выполняется переход по ссылке. data-ags-danger="1" (или класс btn-danger) —
 * красная кнопка подтверждения.
 *
 * Тексты кнопок: window.AGS_LANG.dlg_ok / dlg_yes / dlg_cancel (выводит
 * ags_dialog_assets() из include/functions_additions.php).
 * Текст диалога передаётся как text, а не html — разметка в нём не исполняется.
 */
(function () {
    'use strict';

    if (window.agsAlert && window.agsConfirm) return; // уже подключён

    function t(key, fallback) {
        const L = window.AGS_LANG || {};
        return (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
    }

    function hasSwal() {
        return !!(window.Swal && typeof window.Swal.fire === 'function');
    }

    function agsAlert(text, icon) {
        if (!hasSwal()) {
            window.alert(text);
            return Promise.resolve();
        }
        return window.Swal.fire({
            icon: icon || 'warning',
            text: String(text),
            confirmButtonText: t('dlg_ok', 'OK'),
        }).then(() => undefined);
    }

    function agsConfirm(text, opts) {
        opts = opts || {};
        if (!hasSwal()) {
            return Promise.resolve(window.confirm(text));
        }
        const cfg = {
            icon: opts.icon || (opts.danger ? 'warning' : 'question'),
            text: String(text),
            showCancelButton: true,
            confirmButtonText: opts.confirmText || t('dlg_yes', 'Yes'),
            cancelButtonText: opts.cancelText || t('dlg_cancel', 'Cancel'),
            reverseButtons: true,
            focusCancel: !!opts.danger,
        };
        if (opts.danger) cfg.confirmButtonColor = '#dc3545';
        return window.Swal.fire(cfg).then(r => !!(r && r.isConfirmed));
    }

    window.agsAlert = agsAlert;
    window.agsConfirm = agsConfirm;

    // ── data-ags-confirm ─────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-ags-confirm]');
        if (!el || el.disabled) return;

        e.preventDefault();
        e.stopPropagation();

        const danger = el.dataset.agsDanger === '1' || el.classList.contains('btn-danger');
        agsConfirm(el.dataset.agsConfirm, { danger }).then(ok => {
            if (!ok) return;

            if (el.tagName === 'A' && el.getAttribute('href')) {
                window.location.href = el.href;
                return;
            }

            const form = el.form || el.closest('form');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(el.type === 'submit' ? el : undefined);
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }, true);
})();
