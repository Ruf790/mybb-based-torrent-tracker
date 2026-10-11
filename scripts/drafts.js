/**
 * drafts.js — управление черновиками (выбор, удаление)
 * Тексты: window.AGS_LANG (из usercp.php), английский — fallback.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    function t(key, fallback, ...args) {
        const L = window.AGS_LANG || {};
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            s = s.split('{' + (i + 1) + '}').join(a).split('%' + (i + 1) + '$s').join(a);
        });
        return s;
    }
    // SweetAlert через ags-dialog.js; без него — нативные alert()/confirm()
    const say = (msg, icon) => (window.agsAlert ? window.agsAlert(msg, icon) : (window.alert(msg), Promise.resolve()));
    const ask = (msg, opts) => (window.agsConfirm ? window.agsConfirm(msg, opts) : Promise.resolve(window.confirm(msg)));

    const form            = document.getElementById('draftsForm');
    const checkAll        = document.getElementById('checkAll');
    const selectedCountEl = document.getElementById('selectedCount');
    const selectedBox     = document.querySelector('.selected-count');
    const deleteBtn       = document.getElementById('deleteButton');

    function getCheckboxes() {
        return document.querySelectorAll('.draft-checkbox');
    }

    function updateUI() {
        const boxes = getCheckboxes();
        let checked = 0;

        boxes.forEach(cb => {
            const card = cb.closest('.draft-card');
            if (cb.checked) {
                checked++;
                card?.classList.add('selected');
            } else {
                card?.classList.remove('selected');
            }
        });

        if (selectedCountEl) selectedCountEl.textContent = checked;
        selectedBox?.classList.toggle('d-none', checked === 0);
        if (deleteBtn) deleteBtn.disabled = checked === 0;

        if (checkAll) {
            checkAll.checked       = boxes.length > 0 && checked === boxes.length;
            checkAll.indeterminate = checked > 0 && checked < boxes.length;
        }
    }

    // Выбрать все
    checkAll?.addEventListener('change', () => {
        getCheckboxes().forEach(cb => { cb.checked = checkAll.checked; });
        updateUI();
    });

    // Одиночный выбор
    document.addEventListener('change', e => {
        if (e.target.classList.contains('draft-checkbox')) {
            updateUI();
        }
    });

    // Отправка без повторного события submit (подтверждение уже получено)
    function submitForm() {
        HTMLFormElement.prototype.submit.call(form);
    }

    // Кнопка-корзина у отдельного черновика (раньше обработчика не было — кнопка ничего не делала)
    document.addEventListener('click', e => {
        const btn = e.target.closest('.delete-single-draft');
        if (!btn || !form) return;

        const cb = document.getElementById('draft_' + btn.dataset.draftId);
        if (!cb) return;

        ask(t('confirm_delete_draft', 'Delete this draft? This cannot be undone.'), { danger: true }).then(ok => {
            if (!ok) return;
            getCheckboxes().forEach(box => { box.checked = (box === cb); });
            updateUI();
            submitForm();
        });
    });

    // Подтверждение удаления выбранных
    form?.addEventListener('submit', e => {
        e.preventDefault();
        const count = document.querySelectorAll('.draft-checkbox:checked').length;

        if (!count) {
            say(t('select_draft', 'Please select at least one draft.'));
            return;
        }

        ask(t('confirm_delete_drafts', 'Delete {1} selected draft(s)? This cannot be undone.', count), { danger: true })
            .then(ok => { if (ok) submitForm(); });
    });

    updateUI();
});
