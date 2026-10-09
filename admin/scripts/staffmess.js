/* Mass Message (staffmess.php) */
(function () {
    'use strict';

    const LANG = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    // t(key, fallback, ...args) — {1} и %1$s ($lang->load() превращает {N} в %N$s)
    function t(key, fallback, ...args) {
        let s = typeof LANG[key] === 'string' ? LANG[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    const fmt = (n) => Number(n).toLocaleString();

    function groupBoxes(form) {
        return form ? Array.from(form.querySelectorAll('input[type="checkbox"][name="gid[]"]')) : [];
    }

    function recipientsTotal(form) {
        return groupBoxes(form)
            .filter(cb => cb.checked)
            .reduce((sum, cb) => sum + (parseInt(cb.dataset.count, 10) || 0), 0);
    }

    function updateRecipients(form) {
        const total = fmt(recipientsTotal(form));
        ['smmRecipients', 'smmRecipientsBar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = total;
        });
    }

    // Глобальная: вызывается из onclick="checkAll(document.compose)"
    window.checkAll = function (form) {
        const boxes = groupBoxes(form);
        const allChecked = boxes.length > 0 && boxes.every(cb => cb.checked);
        boxes.forEach(cb => { cb.checked = !allChecked; });
        updateRecipients(form);
    };

    function ask(opts) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: opts.icon,
                title: opts.title,
                text: opts.text,
                showCancelButton: !!opts.cancel,
                confirmButtonText: opts.confirm,
                cancelButtonText: opts.cancel || undefined,
                reverseButtons: true,
                focusCancel: !!opts.cancel
            }).then(r => r.isConfirmed);
        }
        if (opts.cancel) {
            return Promise.resolve(window.confirm(opts.title + '\n\n' + opts.text));
        }
        window.alert(opts.title + '\n\n' + opts.text);
        return Promise.resolve(false);
    }

    function init() {
        const form = document.getElementById('massMessageForm');
        const messageEl = document.getElementById('message');
        const charCountEl = document.getElementById('charCount');

        if (messageEl && charCountEl) {
            const updateCharCount = () => {
                charCountEl.textContent = t('char_count', '{1} characters', fmt(messageEl.value.length));
            };
            messageEl.addEventListener('input', updateCharCount);
            updateCharCount();
        }

        if (!form) return;

        form.addEventListener('change', (e) => {
            if (e.target && e.target.name === 'gid[]') updateRecipients(form);
        });
        updateRecipients(form);

        let confirmed = false;
        form.addEventListener('submit', (e) => {
            if (confirmed) return;
            e.preventDefault();

            const checkedCount = groupBoxes(form).filter(cb => cb.checked).length;
            if (checkedCount === 0) {
                ask({
                    icon: 'warning',
                    title: t('no_groups_title', 'No groups selected'),
                    text: t('no_groups_text', 'Select at least one usergroup to send the message to.'),
                    confirm: t('ok', 'OK')
                });
                return;
            }

            ask({
                icon: 'question',
                title: t('confirm_title', 'Send mass message?'),
                text: t('confirm_text', 'The message will be sent as a PM to {1} user(s).', fmt(recipientsTotal(form))),
                confirm: t('confirm_yes', 'Send'),
                cancel: t('confirm_no', 'Cancel')
            }).then(ok => {
                if (!ok) return;
                confirmed = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
