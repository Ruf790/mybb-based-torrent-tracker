/* Update Check (admin/check_update.php) */
(function () {
    'use strict';

    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

    /** Строка ланга с подстановкой {1}, {2}… и %1$s… */
    function t(key, fallback, ...args) {
        let s = (L[key] !== undefined) ? String(L[key]) : String(fallback);
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    /** Подтверждение: SweetAlert2, если загружен, иначе confirm() */
    function ask(title, text) {
        if (typeof window.Swal === 'undefined') return Promise.resolve(window.confirm(title + '\n\n' + text));
        return Swal.fire({
            icon: 'question',
            title: title,
            text: text,
            showCancelButton: true,
            confirmButtonText: t('yes', 'Yes'),
            cancelButtonText: t('cancel', 'Cancel'),
            confirmButtonColor: '#198754',
            reverseButtons: true,
            focusCancel: true
        }).then(r => r.isConfirmed);
    }

    /** Кнопка в состоянии «работаю»: запрос к GitHub или проверка файлов может занять время */
    function busy(form) {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-spinner fa-spin me-1';
        const label = document.createElement('span');
        label.textContent = t('working', 'Working…');
        Array.from(btn.childNodes).forEach(n => { if (n !== icon) n.remove(); });
        btn.appendChild(label);
    }

    document.querySelectorAll('.cu form[data-busy]').forEach(form => {
        form.addEventListener('submit', () => busy(form));
    });

    document.querySelectorAll('.cu form[data-confirm]').forEach(form => {
        let confirmed = false;
        form.addEventListener('submit', e => {
            if (confirmed) return;
            e.preventDefault();
            const kind = form.dataset.confirm;
            let title, text;
            if (kind === 'mark_latest') {
                title = t('confirm_mark_title', 'Mark as up to date?');
                text  = t('confirm_mark_text', 'The installed version will be set to commit {1}.', form.dataset.sha || '');
            } else {
                const input = form.querySelector('input[name="sha"]');
                title = t('confirm_sha_title', 'Save installed commit?');
                text  = t('confirm_sha_text', 'The installed version will be set to commit {1}.', input ? input.value.trim() : '');
            }
            ask(title, text).then(ok => {
                if (!ok) return;
                confirmed = true;
                busy(form);
                form.submit();
            });
        });
    });
})();
