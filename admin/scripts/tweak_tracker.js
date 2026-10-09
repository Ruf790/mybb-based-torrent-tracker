/* Tweak Tracker - страница очистки в панели персонала */
(() => {
    'use strict';

    const form = document.getElementById('ttForm');
    if (!form) return;

    // Строки из ланга (AGS_LANG выводит PHP), английский fallback, {1} и %1$s.
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (key, fallback, ...args) => {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    };

    const modeInput = form.querySelector('input[name="mode_js"]');
    const btnDry    = document.getElementById('ttDry');
    const btnRun    = document.getElementById('ttRun');
    const toggleAll = document.getElementById('ttToggleAll');
    const boxes     = [...form.querySelectorAll('input[name="groups[]"]')];

    const checked = () => boxes.filter(b => b.checked);

    function updateState() {
        const n = checked().length;
        btnDry.disabled = btnRun.disabled = n === 0;
        const label = toggleAll?.querySelector('span');
        if (label) label.textContent = n === boxes.length ? t('clear_all', 'Clear all') : t('select_all', 'Select all');
    }

    boxes.forEach(b => b.addEventListener('change', updateState));

    toggleAll?.addEventListener('click', () => {
        const all = checked().length === boxes.length;
        boxes.forEach(b => { b.checked = !all; });
        updateState();
    });

    function setBusy(btn) {
        btnDry.disabled = btnRun.disabled = true;
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-spinner fa-spin me-1';
        btn.replaceChildren(icon, document.createTextNode(t('working', 'Working…')));
    }

    function submitWith(mode) {
        modeInput.value = mode;
        setBusy(mode === 'run' ? btnRun : btnDry);
        form.submit();
    }

    btnDry.addEventListener('click', e => {
        e.preventDefault();
        submitWith('dry');
    });

    btnRun.addEventListener('click', e => {
        e.preventDefault();

        const titles = checked().map(b => b.dataset.title || b.value);

        if (!window.Swal) {
            if (confirm(t('confirm_native', 'Run cleanup now? Deleted records and files cannot be restored.') + '\n\n- ' + titles.join('\n- '))) {
                submitWith('run');
            }
            return;
        }

        // Список собираем через DOM, без innerHTML с данными
        const wrap = document.createElement('div');
        const p = document.createElement('p');
        p.textContent = t('swal_text', 'Deleted records and files cannot be restored. Make sure you have a fresh backup.');
        const ul = document.createElement('ul');
        ul.style.textAlign = 'left';
        ul.style.margin = '.75rem auto 0';
        ul.style.maxWidth = '22rem';
        titles.forEach(t => {
            const li = document.createElement('li');
            li.textContent = t;
            ul.appendChild(li);
        });
        wrap.append(p, ul);

        Swal.fire({
            title: t('swal_title', 'Run cleanup?'),
            html: wrap,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: t('swal_confirm', 'Run cleanup'),
            cancelButtonText: t('swal_cancel', 'Cancel'),
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545',
            reverseButtons: true,
            focusCancel: true
        }).then(r => {
            if (r.isConfirmed) submitWith('run');
        });
    });

    // Показ пустых/пропущенных шагов в отчёте
    const showAll = document.getElementById('ttShowAll');
    const report  = document.querySelector('.tt-page .tt-report');
    showAll?.addEventListener('change', () => {
        report?.classList.toggle('tt-show-empty', showAll.checked);
    });

    updateState();
})();
