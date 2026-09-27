/* Rules Manager (admin/modrules.php) — всё под .mr
 * Ждёт на обёртке .mr: data-confirm-*, data-open-edit, data-open-new */
(function () {
    'use strict';

    const root = document.querySelector('.mr');
    if (!root) return;

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    const newForm = $('#newRuleForm');

    function toggleNew(force) {
        if (!newForm) return;
        const show = typeof force === 'boolean' ? force : newForm.hidden;
        newForm.hidden = !show;
        if (show) {
            newForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const t = $('#title', newForm);
            if (t) setTimeout(() => t.focus(), 250);
        }
    }

    function openEdit(id) {
        const card = $('#rule-' + id);
        const edit = $('#editForm-' + id);
        if (!card || !edit) return;
        $('.mr-rule-body', card).hidden = true;
        edit.hidden = false;
        card.classList.add('is-editing');
        const t = $('input[name="title"]', edit);
        if (t) t.focus();
    }

    function closeEdit(id) {
        const card = $('#rule-' + id);
        const edit = $('#editForm-' + id);
        if (!card || !edit) return;
        edit.hidden = true;
        $('.mr-rule-body', card).hidden = false;
        card.classList.remove('is-editing');
    }

    function setGroups(btn, state) {
        const form = btn.closest('form');
        if (!form) return;
        $$('input[name="usergroups[]"]', form).forEach(cb => { cb.checked = state; });
    }

    function submitDelete(id) {
        const f = $('#mrDeleteForm');
        if (!f) return;
        f.elements.id.value = String(id);
        f.submit();
    }

    function askDelete(id) {
        const d = root.dataset;
        if (typeof window.Swal !== 'undefined') {
            window.Swal.fire({
                icon: 'warning',
                title: d.confirmTitle || '',
                text: d.confirmText || '',
                showCancelButton: true,
                confirmButtonText: d.confirmYes || 'OK',
                cancelButtonText: d.confirmNo || 'Cancel',
                confirmButtonColor: '#dc3545',
                reverseButtons: true,
                focusCancel: true
            }).then(r => { if (r.isConfirmed) submitDelete(id); });
        } else if (window.confirm((d.confirmTitle || '') + '\n' + (d.confirmText || ''))) {
            submitDelete(id);
        }
    }

    root.addEventListener('click', e => {
        const btn = e.target.closest('[data-mr]');
        if (!btn || !root.contains(btn)) return;
        const id = parseInt(btn.dataset.id || '0', 10);

        switch (btn.dataset.mr) {
            case 'toggle-new':  toggleNew(); break;
            case 'edit':        openEdit(id); break;
            case 'cancel-edit': closeEdit(id); break;
            case 'delete':      askDelete(id); break;
            case 'groups-all':  setGroups(btn, true); break;
            case 'groups-none': setGroups(btn, false); break;
            default: return;
        }
        e.preventDefault();
    });

    // Search by title/text
    const search = $('#mrSearch');
    const nothing = $('#mrNothing');
    if (search) {
        let timer = 0;
        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const q = search.value.trim().toLowerCase();
                let shown = 0;
                $$('.mr-rule').forEach(card => {
                    const hit = q === '' || (card.dataset.search || '').includes(q);
                    card.hidden = !hit;
                    if (hit) shown++;
                });
                if (nothing) nothing.hidden = shown > 0;
            }, 120);
        });
    }

    // Re-open the form that failed validation
    if (root.dataset.openNew === '1') toggleNew(true);
    const editId = parseInt(root.dataset.openEdit || '0', 10);
    if (editId > 0) {
        openEdit(editId);
        const card = $('#rule-' + editId);
        if (card) card.scrollIntoView({ block: 'start' });
    }
})();
