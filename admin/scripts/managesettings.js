'use strict';
/**
 * admin/scripts/settings.js — Settings Panel (settings.php)
 * Заменяет settings-page.js и admin-settings.js (оба можно удалить).
 *
 *  - flatpickr для дат freeleech
 *  - вкладки: Bootstrap Tab + hash (#kps-settings после PRG-редиректа) + localStorage
 *  - режим обслуживания: показ блока длительности + подтверждение SweetAlert2
 *  - автозаполнение ID в таблице персонала
 *  - кнопка «Save tab» / Ctrl+S — сохраняет открытую вкладку
 *  - индикатор несохранённых изменений + предупреждение при уходе со страницы
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.ag-settings');
    if (!root) return;

    const TAB_KEY = 'settings_active_tab';

    // ── Подтверждение: SweetAlert2, иначе confirm() ─────────────────────────
    const confirmDialog = (title, text, confirmText) => {
        if (window.Swal) {
            return Swal.fire({
                title, text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true,
            }).then(r => r.isConfirmed);
        }
        return Promise.resolve(window.confirm(title + '\n\n' + text));
    };

    const submitForm = form => {
        const btn = form.querySelector('button[type="submit"]');
        btn ? form.requestSubmit(btn) : form.requestSubmit();
    };

    // ── Flatpickr ───────────────────────────────────────────────────────────
    if (typeof flatpickr !== 'undefined') {
        const base = { enableTime: true, enableSeconds: true, dateFormat: 'Y-m-d H:i:S', time_24hr: true, allowInput: true };
        if (document.getElementById('startPicker')) {
            flatpickr('#startPicker', { ...base, defaultHour: 0, defaultMinute: 0, defaultSeconds: 0 });
        }
        if (document.getElementById('endPicker')) {
            flatpickr('#endPicker', { ...base, defaultHour: 23, defaultMinute: 59, defaultSeconds: 59 });
        }
    }

    // ── Вкладки ─────────────────────────────────────────────────────────────
    // Своё переключение, без bootstrap.Tab: Tab ищет общий родитель .nav/[role=tablist],
    // а ссылки разбиты по четырём <ul> в группах - Bootstrap молча ничего не делал.
    const tabLinks = [...root.querySelectorAll('.ag-nav .nav-link[data-ag-pane]')];
    const panes    = [...root.querySelectorAll('.tab-content > .tab-pane')];
    const linkFor  = id => tabLinks.find(a => a.getAttribute('href') === '#' + id) ?? null;

    const showTab = id => {
        const link = linkFor(id);
        const pane = id ? document.getElementById(id) : null;
        if (!link || !pane) return false;

        tabLinks.forEach(a => {
            const on = a === link;
            a.classList.toggle('active', on);
            a.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        if (!pane.classList.contains('active')) {
            panes.forEach(p => { if (p !== pane) p.classList.remove('active', 'show'); });
            pane.classList.add('active');
            void pane.offsetWidth;          // reflow, чтобы .fade анимировался
            pane.classList.add('show');
        }

        // pathname без ?saved=… - чтобы F5 не показывал флаг старого сохранения
        history.replaceState(null, '', location.pathname + '#' + id);
        try { localStorage.setItem(TAB_KEY, id); } catch { /* private mode */ }
        return true;
    };

    tabLinks.forEach(link => link.addEventListener('click', e => {
        e.preventDefault();
        showTab(link.getAttribute('href').slice(1));
    }));

    // Приоритет: hash из редиректа → последняя открытая вкладка → Main
    let startId = location.hash.slice(1);
    if (!linkFor(startId)) {
        try { startId = (localStorage.getItem(TAB_KEY) ?? '').replace(/^#/, ''); } catch { startId = ''; }
    }
    if (linkFor(startId)) showTab(startId);

    window.addEventListener('hashchange', () => showTab(location.hash.slice(1)));

    // Ссылки из карточек-предупреждений: переключить вкладку без прыжка страницы
    root.querySelectorAll('[data-ag-tab]').forEach(a => a.addEventListener('click', e => {
        if (!showTab(a.dataset.agTab)) return;
        e.preventDefault();
        root.querySelector('.ag-layout')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));

    // ── Режим обслуживания ──────────────────────────────────────────────────
    const siteSw  = document.getElementById('sw_SITEONLINE');
    const offGrp  = document.getElementById('offlineDurationGroup');
    const limMode = document.getElementById('limitedMode');
    const unlMode = document.getElementById('unlimitedMode');
    const timeGrp = document.getElementById('timeLimitGroup');

    const syncOffline = () => {
        if (offGrp && siteSw)   offGrp.style.display  = siteSw.checked ? 'none' : 'block';
        if (timeGrp && unlMode) timeGrp.style.display = unlMode.checked ? 'none' : 'flex';
    };
    [siteSw, limMode, unlMode].forEach(el => el?.addEventListener('change', syncOffline));
    syncOffline();

    // Выключение сайта - только после подтверждения
    const mainForm   = siteSw?.form ?? null;
    const wasOnline  = siteSw?.checked ?? false;
    mainForm?.addEventListener('submit', e => {
        if (!wasOnline || siteSw.checked || mainForm.dataset.confirmed === '1') return;
        e.preventDefault();
        const unlimited = unlMode?.checked;
        const mins = mainForm.querySelector('[name="offline_minutes_input"]')?.value || '30';
        confirmDialog(
            'Take the site offline?',
            unlimited
                ? 'Regular users will see the maintenance message until you switch it back on.'
                : `Regular users will see the maintenance message for ${mins} min.`,
            'Go offline'
        ).then(ok => {
            if (!ok) return;
            mainForm.dataset.confirmed = '1';
            submitForm(mainForm);
        });
    });

    // ── Несохранённые изменения ─────────────────────────────────────────────
    const forms = [...root.querySelectorAll('form.ag-pane')];
    let submitting = false;

    const markDirty = form => {
        if (form.classList.contains('is-dirty')) return;
        form.classList.add('is-dirty');
        const note = form.querySelector('.ag-savebar-note');
        if (note) note.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i>Unsaved changes';
        const paneId = form.closest('.tab-pane')?.id;
        if (paneId) linkFor(paneId)?.classList.add('is-dirty');
    };

    forms.forEach(form => {
        form.addEventListener('input',  () => markDirty(form));
        form.addEventListener('change', () => markDirty(form));
        // Зарегистрирован после обработчика подтверждения - видит его preventDefault()
        form.addEventListener('submit', e => { if (!e.defaultPrevented) submitting = true; });
    });

    window.addEventListener('beforeunload', e => {
        if (submitting || !root.querySelector('form.ag-pane.is-dirty')) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // ── Сохранение открытой вкладки: кнопка в шапке и Ctrl+S ───────────────
    const saveActive = () => {
        const form = root.querySelector('.tab-pane.active form.ag-pane');
        if (form) submitForm(form);
    };
    document.getElementById('globalSaveBtn')?.addEventListener('click', saveActive);
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            saveActive();
        }
    });

    // ── Персонал: ID по имени ───────────────────────────────────────────────
    if (typeof staffData !== 'undefined' && Array.isArray(staffData)) {
        const idByName = new Map(staffData.map(s => [String(s.username).toLowerCase(), String(s.id)]));

        root.addEventListener('input', e => {
            const inp = e.target;
            if (!(inp instanceof HTMLInputElement)) return;

            // Ручная правка ID - больше не трогаем его автоматически
            if (inp.name === 'staffids[]') { delete inp.dataset.auto; return; }
            if (inp.name !== 'staffnames[]') return;

            const idInp = inp.closest('tr')?.querySelector('input[name="staffids[]"]');
            if (!idInp) return;

            const id = idByName.get(inp.value.trim().toLowerCase());
            if (id && (idInp.value === '' || idInp.dataset.auto === '1')) {
                if (idInp.value !== id) {
                    idInp.value = id;
                    idInp.classList.remove('ag-autofilled');
                    void idInp.offsetWidth; // перезапуск анимации
                    idInp.classList.add('ag-autofilled');
                }
                idInp.dataset.auto = '1';
            } else if (!id && idInp.dataset.auto === '1') {
                idInp.value = '';
                delete idInp.dataset.auto;
            }
        });
    }

    // Кнопки очистки строк меняют value без события input - помечаем форму вручную
    root.addEventListener('click', e => {
        const btn = e.target.closest('.ag-icon-btn, [data-clear-rows]');
        if (btn?.form) markDirty(btn.form);
    });
});
