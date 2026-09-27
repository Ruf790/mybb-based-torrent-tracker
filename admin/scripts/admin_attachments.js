// Admin → Attachments: выбор строк, «выбрать всё», модалка подтверждения удаления.
// Разметка модалки (#atmConfirm) выводится в render_header() в admin/attachments.php.
(function () {
    'use strict';
    let pendingForm = null;

    function refresh(form) {
        const boxes = form.querySelectorAll('input.atm-cb');
        const n = form.querySelectorAll('input.atm-cb:checked').length;
        form.querySelectorAll('.atm-selcount').forEach(el => { el.textContent = n; });
        form.querySelectorAll('.atm-del').forEach(b => { b.disabled = n === 0; });
        boxes.forEach(cb => cb.closest('tr')?.classList.toggle('is-selected', cb.checked));
        form.querySelectorAll('[data-check-all]').forEach(m => {
            const name = m.dataset.checkAll;
            const group = [...boxes].filter(cb => name === '*' || cb.name === name);
            const sel = group.filter(cb => cb.checked).length;
            m.checked = group.length > 0 && sel === group.length;
            m.indeterminate = sel > 0 && sel < group.length;
        });
    }

    document.addEventListener('change', function (e) {
        const t = e.target;
        const form = t.closest && t.closest('form.atm-selectable');
        if (!form) return;
        // Раньше checkAll() отмечал только aids[] — cf_ids[] на странице комментариев
        // оставались невыбранными. '*' — все чекбоксы формы.
        if (t.matches('[data-check-all]')) {
            const name = t.dataset.checkAll;
            form.querySelectorAll('input.atm-cb').forEach(cb => { if (name === '*' || cb.name === name) cb.checked = t.checked; });
        }
        refresh(form);
    });

    document.addEventListener('click', function (e) {
        const row = e.target.closest('form.atm-selectable .atm-table tbody tr');
        if (row && !e.target.closest('a, input, label, button')) {
            const cb = row.querySelector('input.atm-cb');
            if (cb) { cb.checked = !cb.checked; refresh(cb.form); }
            return;
        }
        const del = e.target.closest('.atm-del');
        if (del) {
            pendingForm = del.closest('form');
            document.getElementById('atmConfirmCount').textContent = pendingForm.querySelectorAll('input.atm-cb:checked').length;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('atmConfirm')).show();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.atm-selectable').forEach(refresh);
        document.getElementById('atmConfirmBtn')?.addEventListener('click', function () {
            if (!pendingForm) return;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
            pendingForm.submit();
        });
    });
})();
