// Admin → Attachments: выбор строк, «выбрать всё», модалка подтверждения удаления.
// Разметка модалки (#atmConfirm) выводится в render_header() в admin/attachments.php.
(function () {
    'use strict';
    let pendingForm = null;

    // Ланг-строки приходят из PHP как const AGS_LANG = {...} (ключи js_* без префикса).
    // $lang->load() превращает {1} в %1$s — подставляем оба формата.
    const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    function t(key, fallback, ...args) {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

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
            // Перевод вставляем текстом, не через innerHTML
            const spin = document.createElement('span');
            spin.className = 'spinner-border spinner-border-sm me-1';
            this.replaceChildren(spin, document.createTextNode(t('deleting', 'Deleting...')));
            pendingForm.submit();
        });
    });
})();
