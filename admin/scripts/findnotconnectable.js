/* admin/findnotconnectable.php */
(() => {
    'use strict';

    /** Translate with English fallback; fills {1} and %1$s. */
    const t = (key, fallback, ...args) => {
        const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    };

    /** SweetAlert2 confirm with native confirm() fallback. */
    const ask = (text, danger) => {
        if (typeof window.Swal === 'undefined') {
            return Promise.resolve(window.confirm(text));
        }
        return window.Swal.fire({
            title: t('confirm_title', 'Are you sure?'),
            text: text,                       // text, not html
            icon: danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: t('btn_yes', 'Yes'),
            cancelButtonText: t('btn_cancel', 'Cancel'),
            confirmButtonColor: danger ? '#dc3545' : undefined,
            reverseButtons: true,
            focusCancel: true,
        }).then(r => r.isConfirmed === true);
    };

    const init = () => {
        // ── Select all + selected counter ──
        const boxes   = () => document.querySelectorAll('.userCheckbox');
        const counter = document.getElementById('fncSelCount');
        const selAll  = document.getElementById('selectAll');

        const updateCount = () => {
            const list    = boxes();
            const checked = [...list].filter(cb => cb.checked).length;
            if (counter) {
                counter.textContent = t('selected', 'Selected: {1}', checked);
            }
            if (selAll) {
                selAll.checked       = list.length > 0 && checked === list.length;
                selAll.indeterminate = checked > 0 && checked < list.length;
            }
            return checked;
        };

        if (selAll) {
            selAll.addEventListener('change', () => {
                boxes().forEach(cb => { cb.checked = selAll.checked; });
                updateCount();
            });
        }
        boxes().forEach(cb => cb.addEventListener('change', updateCount));
        updateCount();

        // ── Confirmations ──
        document.querySelectorAll('form.fnc-confirm').forEach(form => {
            form.addEventListener('submit', ev => {
                if (form.dataset.confirmed === '1') {
                    return;
                }
                ev.preventDefault();

                const kind = form.dataset.confirm || '';
                let text, danger = false;
                if (kind === 'delete') {
                    text   = t('confirm_delete', 'Delete this log entry?');
                    danger = true;
                } else if (kind === 'selected') {
                    if (updateCount() === 0) {      // server answers with "select a user"
                        form.dataset.confirmed = '1';
                        form.submit();
                        return;
                    }
                    text = t('confirm_selected', 'Send the warning PM to {1} selected users?', updateCount());
                } else if (kind === 'mass') {
                    text = t('confirm_mass', 'Send this PM to all {1} unconnectable users?', form.dataset.count || '0');
                } else {
                    text = t('confirm_title', 'Are you sure?');
                }

                ask(text, danger).then(ok => {
                    if (ok) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                });
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
