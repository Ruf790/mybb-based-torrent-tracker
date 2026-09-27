(function () {
    'use strict';

    // Drop the one-shot flash params so F5 shows a clean page
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('vipmsg')) {
            ['vipmsg', 'n', 'amt', 'act'].forEach(function (k) { url.searchParams.delete(k); });
            window.history.replaceState(null, '', url.toString());
        }
    } catch (e) { /* old browser: ignore */ }

    const form = document.getElementById('vmUpdateForm');
    const bar  = document.getElementById('vmActionBar');
    if (!form || !bar) {
        return;
    }

    const boxes      = Array.from(form.querySelectorAll('input[name="userids[]"]'));
    const master     = document.getElementById('vmCheckAll');
    const countEl    = document.getElementById('selectedCount');
    const submitBtn  = document.getElementById('vmSubmit');
    const limit      = document.getElementById('limit');
    const limitGroup = document.getElementById('limitFormGroup');
    const unitEl     = document.getElementById('vmUnit');
    const radios     = Array.from(form.querySelectorAll('input[name="add"]'));

    const nf = new Intl.NumberFormat();

    function selectedCount() {
        return boxes.filter(function (b) { return b.checked; }).length;
    }

    function currentAction() {
        return radios.find(function (r) { return r.checked; }) || radios[0];
    }

    function sync() {
        const n = selectedCount();
        countEl.textContent = n;
        bar.classList.toggle('is-active', n > 0);
        submitBtn.disabled = n === 0;
        boxes.forEach(function (b) {
            const tr = b.closest('tr');
            if (tr) { tr.classList.toggle('is-selected', b.checked); }
        });
        if (master) {
            master.checked = n > 0 && n === boxes.length;
            master.indeterminate = n > 0 && n < boxes.length;
        }
    }

    function syncAction() {
        const r = currentAction();
        const isRemove = r.value === 'remove_vip';
        limitGroup.hidden = isRemove;
        limit.required = !isRemove;
        limit.max = r.dataset.max;
        unitEl.textContent = r.dataset.unit;
        submitBtn.classList.toggle('btn-danger', isRemove);
        submitBtn.classList.toggle('btn-primary', !isRemove);
    }

    boxes.forEach(function (b) { b.addEventListener('change', sync); });

    if (master) {
        // Runs after the legacy inline select_deselectAll(); also works without it
        master.addEventListener('click', function () {
            boxes.forEach(function (b) { b.checked = master.checked; });
            sync();
        });
    }

    // Click anywhere on a row (except links/inputs) toggles it
    form.querySelectorAll('tr.vm-row').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('a, input, label, button')) { return; }
            const cb = tr.querySelector('input[name="userids[]"]');
            if (cb) { cb.checked = !cb.checked; sync(); }
        });
    });

    radios.forEach(function (r) { r.addEventListener('change', syncAction); });

    function notify(text) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'info', text: text, confirmButtonText: 'OK' });
        } else {
            window.alert(text);
        }
    }

    function ask(title, text, danger) {
        if (window.Swal) {
            return window.Swal.fire({
                title: title,
                text: text,
                icon: danger ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: danger ? 'Remove VIP' : 'Apply',
                cancelButtonText: 'Cancel',
                confirmButtonColor: danger ? '#dc3545' : undefined,
                reverseButtons: true,
                focusCancel: danger
            }).then(function (res) { return !!res.isConfirmed; });
        }
        return Promise.resolve(window.confirm(title + '\n\n' + text));
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const n = selectedCount();
        if (n === 0) {
            notify('Select at least one VIP account first.');
            return;
        }

        const r = currentAction();
        const isRemove = r.value === 'remove_vip';
        const who = n === 1 ? '1 account' : nf.format(n) + ' accounts';
        let title;
        let text;

        if (isRemove) {
            title = 'Remove VIP from ' + who + '?';
            text  = 'They go back to their previous group right now. This cannot be undone.';
        } else {
            const v   = parseInt(limit.value, 10);
            const max = parseInt(r.dataset.max, 10);
            if (!(v >= 1 && v <= max)) {
                notify('Enter an amount from 1 to ' + nf.format(max) + ' ' + r.dataset.unit + '.');
                limit.focus();
                return;
            }
            title = r.dataset.label + '?';
            text  = r.dataset.label + ': ' + nf.format(v) + ' ' + r.dataset.unit + ' for ' + who + '.';
        }

        ask(title, text, isRemove).then(function (ok) {
            if (!ok) { return; }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Applying…';
            form.submit();
        });
    });

    syncAction();
    sync();
})();