/* Poll manager (admin) — confirmations, selection, bulk actions, answer editor. */
(function () {
    'use strict';

    const hasSwal = () => typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';

    const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    /** Promise<boolean>. SweetAlert2, если есть; иначе нативный confirm(). */
    function confirmDialog(opts) {
        const o = Object.assign({ title: 'Are you sure?', text: '', icon: 'warning', confirm: 'Confirm', tone: 'danger' }, opts);
        if (!hasSwal()) {
            return Promise.resolve(window.confirm(o.text ? o.title + '\n\n' + o.text : o.title));
        }
        return window.Swal.fire({
            title: o.title,
            text: o.text || undefined,
            icon: o.icon,
            showCancelButton: true,
            confirmButtonText: o.confirm,
            cancelButtonText: 'Cancel',
            confirmButtonColor: cssVar('--bs-' + o.tone) || undefined,
            reverseButtons: true,
            focusCancel: o.tone === 'danger'
        }).then((r) => !!r.isConfirmed);
    }

    function notify(text, icon) {
        if (!hasSwal()) { window.alert(text); return; }
        window.Swal.fire({ toast: true, position: 'top-end', timer: 2600, timerProgressBar: true, showConfirmButton: false, icon: icon || 'info', title: text });
    }

    /** submit() в обход возможного <input name="submit"> и без повторного события submit. */
    function realSubmit(form) {
        form.classList.add('is-busy');
        HTMLFormElement.prototype.submit.call(form);
    }

    // ── Confirm on forms with data-confirm ───────────────────────────────────
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.closest('.mp-page') || !form.dataset.confirm) return;
        e.preventDefault();
        confirmDialog({
            title: form.dataset.confirm,
            text: form.dataset.confirmText || '',
            confirm: form.dataset.confirmBtn || 'Confirm',
            tone: form.dataset.confirmTone || 'danger',
            icon: form.dataset.confirmIcon || 'warning'
        }).then((ok) => { if (ok) realSubmit(form); });
    });

    // ── Selection (select all, counters, row highlight) ──────────────────────
    const items = (cls) => Array.from(document.querySelectorAll('.mp-page input.' + cls));

    function refreshSelection(cls) {
        const all = items(cls);
        const checked = all.filter((cb) => cb.checked);
        all.forEach((cb) => { const tr = cb.closest('tr'); if (tr) tr.classList.toggle('is-selected', cb.checked); });

        document.querySelectorAll('[data-count-for="' + cls + '"]').forEach((el) => {
            el.textContent = String(checked.length);
            const bar = el.closest('.mp-actionbar');
            if (bar) bar.classList.toggle('has-selection', checked.length > 0);
        });
        document.querySelectorAll('[data-select-all="' + cls + '"]').forEach((m) => {
            m.checked = all.length > 0 && checked.length === all.length;
            m.indeterminate = checked.length > 0 && checked.length < all.length;
        });
    }

    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement) || !t.closest('.mp-page')) return;
        if (t.dataset.selectAll) {
            items(t.dataset.selectAll).forEach((cb) => { cb.checked = t.checked; });
            refreshSelection(t.dataset.selectAll);
            return;
        }
        ['pollCheckbox', 'voteCheckbox'].forEach((cls) => { if (t.classList.contains(cls)) refreshSelection(cls); });
    });

    // ── Bulk buttons ─────────────────────────────────────────────────────────
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.mp-page [data-bulk]');
        if (!btn) return;
        const cls = btn.dataset.bulk;
        const form = document.getElementById(btn.dataset.bulkForm);
        const count = items(cls).filter((cb) => cb.checked).length;
        const noun = btn.dataset.bulkNoun || 'item';
        if (!form) return;
        if (count === 0) { notify('Select at least one ' + noun + ' first.', 'info'); return; }

        const verb = btn.dataset.bulkVerb || 'Apply to';
        confirmDialog({
            title: verb + ' ' + count + ' ' + noun + (count === 1 ? '' : 's') + '?',
            text: btn.dataset.confirmText || '',
            confirm: verb + ' ' + count,
            tone: btn.dataset.confirmTone || 'danger'
        }).then((ok) => {
            if (!ok) return;
            if (btn.dataset.bulkAction) {
                const act = form.querySelector('input[name="action"]');
                if (act) act.value = btn.dataset.bulkAction;
            }
            realSubmit(form);
        });
    });

    // ── Character counter ────────────────────────────────────────────────────
    function refreshCounter(input) {
        const out = document.querySelector('[data-counter-for="' + input.id + '"]');
        if (!out) return;
        const max = parseInt(input.dataset.maxcount, 10) || 0;
        const len = input.value.length;
        out.textContent = len + ' / ' + max;
        out.classList.toggle('is-near', len >= max * 0.85 && len < max);
        out.classList.toggle('is-full', len >= max);
    }
    document.querySelectorAll('.mp-page input[data-maxcount]').forEach((inp) => {
        refreshCounter(inp);
        inp.addEventListener('input', () => refreshCounter(inp));
    });

    // ── Thread title autofill (stops once the title is edited by hand) ───────
    (function () {
        const q = document.getElementById('pollQuestion');
        const t = document.getElementById('threadTitle');
        if (!q || !t) return;
        let touched = t.value !== '';
        t.addEventListener('input', () => { touched = true; });
        q.addEventListener('input', () => { if (!touched) t.value = q.value.slice(0, 120); });
    })();

    // ── Answer editor ────────────────────────────────────────────────────────
    const box = document.getElementById('optionsContainer');
    if (box) {
        const withVotes = box.dataset.withVotes === '1';
        const max = parseInt(box.dataset.max, 10) || 20;
        const min = parseInt(box.dataset.min, 10) || 2;
        const PALETTE = 8;
        const addBtn = document.querySelector('.mp-page [data-option-add]');

        const rows = () => Array.from(box.querySelectorAll('.option-row'));

        function reindex() {
            const list = rows();
            list.forEach((row, i) => {
                for (let c = 0; c < PALETTE; c++) row.classList.remove('mp-c' + c);
                row.classList.add('mp-c' + (i % PALETTE));
                const num = row.querySelector('.mp-option-num');
                if (num) num.textContent = String(i + 1);
                const text = row.querySelector('input[name="options[]"]');
                if (text) { text.placeholder = 'Answer ' + (i + 1); text.setAttribute('aria-label', 'Answer ' + (i + 1)); }
                const votes = row.querySelector('input[name="votes[]"]');
                if (votes) votes.setAttribute('aria-label', 'Votes for answer ' + (i + 1));
            });
            document.querySelectorAll('.mp-page [data-option-count]').forEach((el) => { el.textContent = String(list.length); });
            if (addBtn) addBtn.disabled = list.length >= max;
        }

        function makeRow() {
            const row = document.createElement('div');
            row.className = 'mp-option option-row';

            const num = document.createElement('span');
            num.className = 'mp-option-num';
            row.appendChild(num);

            const text = document.createElement('input');
            text.type = 'text';
            text.name = 'options[]';
            text.className = 'form-control';
            text.required = true;
            row.appendChild(text);

            if (withVotes) {
                const votes = document.createElement('input');
                votes.type = 'number';
                votes.name = 'votes[]';
                votes.className = 'form-control mp-option-votes';
                votes.min = '0';
                votes.value = '0';
                row.appendChild(votes);
            }

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'mp-icon-btn mp-tone-danger';
            rm.title = 'Remove answer';
            rm.setAttribute('data-option-remove', '');
            rm.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Remove</span>';
            row.appendChild(rm);
            return row;
        }

        function addOption(focus) {
            if (rows().length >= max) { notify('A poll can have at most ' + max + ' answers.', 'info'); return null; }
            const row = makeRow();
            box.appendChild(row);
            reindex();
            if (focus !== false) row.querySelector('input[name="options[]"]').focus();
            return row;
        }

        function removeOption(row) {
            if (rows().length <= min) { notify('A poll needs at least ' + min + ' answers.', 'info'); return; }
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            reindex();
            const input = next && next.querySelector('input[name="options[]"]');
            if (input) input.focus();
        }

        if (addBtn) addBtn.addEventListener('click', () => addOption());

        box.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-option-remove]');
            if (btn) removeOption(btn.closest('.option-row'));
        });

        // Enter в последнем поле ответа добавляет новый ответ, а не отправляет форму
        box.addEventListener('keydown', (e) => {
            const t = e.target;
            if (e.key !== 'Enter' || !(t instanceof HTMLInputElement) || t.name !== 'options[]') return;
            e.preventDefault();
            const list = rows();
            const row = t.closest('.option-row');
            if (row === list[list.length - 1]) {
                if (t.value.trim() !== '') addOption();
            } else {
                const nxt = row.nextElementSibling && row.nextElementSibling.querySelector('input[name="options[]"]');
                if (nxt) nxt.focus();
            }
        });

        reindex();

        // Совместимость со старыми inline-вызовами
        window.addOption = () => addOption();
        window.removeOption = (btn) => removeOption(btn.closest('.option-row'));
        window.reindexOptions = reindex;
    }
})();
