/* reports.php — Report Management (staff panel) */
(function () {
    'use strict';

    // Сообщения приходят из PHP (REPORT_MESSAGES) через <script type="application/json" id="rp-messages">
    var MSG = {};
    try {
        var cfg = document.getElementById('rp-messages');
        if (cfg) { MSG = JSON.parse(cfg.textContent) || {}; }
    } catch (err) { MSG = {}; }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') { window.showToast(msg, type); }
    }

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function ask(o) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: o.title,
                text: o.text || '',
                icon: o.icon || 'warning',
                showCancelButton: true,
                reverseButtons: true,
                focusCancel: o.variant === 'danger',
                confirmButtonText: o.btn || 'Confirm',
                cancelButtonText: 'Cancel',
                confirmButtonColor: cssVar('--bs-' + (o.variant || 'primary'), '#0d6efd'),
                cancelButtonColor: cssVar('--bs-secondary', '#6c757d')
            }).then(function (r) { return !!r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(o.title + (o.text ? '\n\n' + o.text : '')));
    }

    function readConfirm(el) {
        var d = el.dataset;
        return {
            title: d.confirmTitle || 'Are you sure?',
            text: d.confirmText || '',
            icon: d.confirmIcon || 'warning',
            btn: d.confirmBtn || 'Confirm',
            variant: d.confirmVariant || 'primary'
        };
    }

    function setBusy(form, busy) {
        form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = busy; });
    }

    function ajaxSubmit(form) {
        setBusy(form, true);
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    setBusy(form, false);
                    toast(data.message || 'Action failed', 'danger');
                    return;
                }
                toast(data.message || 'Done', 'success');
                var row = form.closest('tr');
                if (!row) { window.location.reload(); return; }
                var body = row.parentNode;
                row.classList.add('rp-row-out');
                setTimeout(function () {
                    row.remove();
                    if (!body.querySelector('tr')) { window.location.reload(); }
                }, 300);
            })
            .catch(function () {
                // Сеть/JSON сломались — обычная отправка формы (PRG)
                form.dataset.confirmed = '1';
                form.submit();
            });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.rp-page form[data-confirm]')) { return; }
        if (form.dataset.confirmed === '1') { return; }
        e.preventDefault();

        var source = form;
        if (form.dataset.confirm === 'choice') {
            source = form.querySelector('input[name="do"]:checked');
            if (!source) { toast('Choose an action first', 'warning'); return; }
        }

        ask(readConfirm(source)).then(function (ok) {
            if (!ok) { return; }
            if (form.classList.contains('rp-ajax')) { ajaxSubmit(form); return; }
            form.dataset.confirmed = '1';
            setBusy(form, true);
            form.submit();
        });
    });

    document.addEventListener('click', function (e) {
        var a = e.target.closest('.rp-page a[data-confirm]');
        if (!a) { return; }
        e.preventDefault();
        ask(readConfirm(a)).then(function (ok) {
            if (!ok) { return; }
            if (a.target === '_blank') { window.open(a.href, '_blank', 'noopener'); }
            else { window.location.href = a.href; }
        });
    });

    function flash() {
        var url = new URL(window.location.href);
        var changed = false;
        ['success', 'error'].forEach(function (k) {
            var code = url.searchParams.get(k);
            if (code === null) { return; }
            var text = (MSG[k] && MSG[k][code]) || (k === 'success' ? 'Done' : 'Something went wrong');
            toast(text, k === 'success' ? 'success' : 'danger');
            url.searchParams.delete(k);
            changed = true;
        });
        // Убираем флаг из адреса, чтобы F5 не показывал тост повторно
        if (changed && window.history && history.replaceState) {
            history.replaceState(null, '', url.toString());
        }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', flash); }
    else { flash(); }
})();
