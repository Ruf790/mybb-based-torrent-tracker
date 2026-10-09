/* Requests & Offers — staff panel */
(function () {
    'use strict';

    const page = document.querySelector('.ro-page');
    if (!page) return;

    // ── Ланг: JSON-блок #ro-lang от PHP; запасной вариант — глобальный AGS_LANG
    function loadLang() {
        const el = document.getElementById('ro-lang');
        if (el) {
            try {
                const data = JSON.parse(el.textContent || '{}');
                if (data && typeof data === 'object') return data;
            } catch (err) {
                console.warn('requests_offers: bad #ro-lang JSON', err);
            }
        }
        if (typeof AGS_LANG === 'object' && AGS_LANG) return AGS_LANG;
        console.warn('requests_offers: lang strings not found, using English');
        return {};
    }
    const L = loadLang();

    /** Строка из ланга с английским fallback; подставляет {1} и %1$s. */
    function t(key, fallback, ...args) {
        const s = typeof L[key] === 'string' ? L[key] : fallback;
        return s.replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
            const i = Number(a || b) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }

    // Строки, разные для вкладок: js_req_* / js_off_*
    const pfx = page.dataset.tab === 'offers' ? 'off_' : 'req_';
    const tt  = (key, fallback, ...args) => t(pfx + key, fallback, ...args);

    /** Существительное по числу: 1 запрос / 2 запроса / 5 запросов. */
    let pluralRules = null;
    try { pluralRules = new Intl.PluralRules(t('locale', 'en')); } catch (_) { pluralRules = null; }

    function noun(n) {
        const fb  = pfx === 'off_' ? ['offer', 'offers'] : ['request', 'requests'];
        const cat = pluralRules ? pluralRules.select(n) : (n === 1 ? 'one' : 'other');
        if (cat === 'one') return tt('noun_one', fb[0]);
        if (cat === 'few') return tt('noun_few', fb[1]);
        return tt('noun_many', fb[1]);
    }

    // ── Подтверждения: SweetAlert2, fallback на confirm()/alert() ──────────
    const hasSwal = () => typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';

    function confirmAction(opts) {
        if (hasSwal()) {
            const cfg = {
                title: opts.title,
                text: opts.text || '',
                icon: opts.danger ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: opts.confirmText || t('confirm', 'Confirm'),
                cancelButtonText: t('cancel', 'Cancel'),
                reverseButtons: true,
                focusCancel: !!opts.danger
            };
            if (opts.danger) cfg.confirmButtonColor = '#dc3545';
            return window.Swal.fire(cfg).then(r => r.isConfirmed === true);
        }
        return Promise.resolve(window.confirm(opts.title + (opts.text ? '\n\n' + opts.text : '')));
    }

    function notify(text) {
        if (hasSwal()) window.Swal.fire({ icon: 'info', text: text });
        else window.alert(text);
    }

    // ── Выделение строк ────────────────────────────────────────────────────
    const bulkForm  = document.getElementById('ro-bulk-form');
    const selectAll = document.getElementById('ro-select-all');
    const countEl   = document.getElementById('ro-selected-count');
    const applyBtn  = document.getElementById('ro-apply-btn');
    const bar       = document.getElementById('ro-bulkbar');
    const clearBtn  = document.getElementById('ro-clear-selection');

    const rowChecks = () => Array.from(page.querySelectorAll('input.ro-check[name="ids[]"]'));

    function sync() {
        const all = rowChecks();
        const n   = all.filter(c => c.checked).length;

        if (countEl) countEl.querySelector('b').textContent = String(n);
        if (applyBtn) applyBtn.disabled = n === 0;
        if (bar) bar.classList.toggle('is-active', n > 0);

        all.forEach(c => {
            const tr = c.closest('tr');
            if (tr) tr.classList.toggle('is-selected', c.checked);
        });

        if (selectAll) {
            selectAll.checked       = all.length > 0 && n === all.length;
            selectAll.indeterminate = n > 0 && n < all.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            rowChecks().forEach(c => { c.checked = selectAll.checked; });
            sync();
        });
    }

    page.addEventListener('change', e => {
        if (e.target.matches('input.ro-check[name="ids[]"]')) sync();
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            rowChecks().forEach(c => { c.checked = false; });
            sync();
        });
    }

    sync();

    // ── Bulk submit ────────────────────────────────────────────────────────
    if (bulkForm) {
        bulkForm.addEventListener('submit', e => {
            e.preventDefault();

            const sel    = bulkForm.elements.namedItem('bulk_action');
            const action = sel ? sel.value : '';
            const n      = rowChecks().filter(c => c.checked).length;

            if (n === 0) return;
            if (!action) {
                notify(t('choose_action', 'Choose a bulk action first.'));
                return;
            }

            const label = sel.options[sel.selectedIndex].text.trim();
            const opts = action === 'delete'
                ? {
                    title: t('bulk_del_title', 'Delete {1} {2}?', n, noun(n)),
                    text: t('bulk_del_text', 'Their votes and comments will be removed too. This cannot be undone.'),
                    confirmText: t('bulk_del_btn', 'Delete {1}', n),
                    danger: true
                }
                : {
                    title: t('bulk_status_title', '{1} — {2} selected?', label, n),
                    text: tt('bulk_status_text', 'The status of every selected item will change.'),
                    confirmText: label
                };

            confirmAction(opts).then(ok => {
                if (ok) HTMLFormElement.prototype.submit.call(bulkForm);
            });
        });
    }

    // ── Меню действий (своё позиционирование) ──────────────────────────────
    // Меню переносится в .ro-page (стили остаются scoped) и ставится position:fixed —
    // Bootstrap-дропдаун внутри table-responsive обрезается краем экрана.
    let openMenu = null;

    function closeOpenMenu() {
        if (!openMenu) return;
        const { menu, placeholder, btn } = openMenu;
        menu.classList.remove('show');
        placeholder.replaceWith(menu);
        btn.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }

    function positionMenu(btn, menu) {
        const rect = btn.getBoundingClientRect();
        menu.style.visibility = 'hidden';
        menu.classList.add('show');
        const w = menu.offsetWidth;
        const h = menu.offsetHeight;

        let left = rect.right - w;
        let top  = rect.bottom + 6;

        if (left < 8) left = 8;
        if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
        if (top + h > window.innerHeight - 8) top = Math.max(8, rect.top - h - 6);

        menu.style.left = left + 'px';
        menu.style.top  = top + 'px';
        menu.style.visibility = '';
    }

    // ── Одиночные действия через скрытую POST-форму ────────────────────────
    const actionForm = document.getElementById('ro-action-form');

    function runAction(d) {
        const isDelete = d.roDo === 'delete';
        const opts = isDelete
            ? {
                title: tt('del_title', 'Delete #{1}?', d.id),
                text: (d.title ? '«' + d.title + '»\n' : '') + t('del_text', 'Its votes and comments will be removed too. This cannot be undone.'),
                confirmText: t('delete', 'Delete'),
                danger: true
            }
            : {
                title: t('status_title', 'Mark #{1} as {2}?', d.id, d.statusLabel),
                text: d.title || '',
                confirmText: t('status_btn', 'Mark as {1}', d.statusLabel)
            };

        confirmAction(opts).then(ok => {
            if (!ok || !actionForm) return;
            actionForm.elements.namedItem('ro_do').value  = d.roDo;
            actionForm.elements.namedItem('id').value     = d.id;
            actionForm.elements.namedItem('status').value = d.status || '';
            HTMLFormElement.prototype.submit.call(actionForm);
        });
    }

    document.addEventListener('click', e => {
        // Mark as Filled/Uploaded → модалка (Bootstrap открывает её сам через data-bs-*)
        const markBtn = e.target.closest('.ro-mark-complete');
        if (markBtn) {
            const idInput = document.getElementById('roCompleteId');
            const titleEl = document.getElementById('roCompleteTitle');
            if (idInput) idInput.value = markBtn.dataset.id;
            if (titleEl) titleEl.textContent = '#' + markBtn.dataset.id + ' · ' + (markBtn.dataset.title || '');
            closeOpenMenu();
            return;
        }

        const actionItem = e.target.closest('[data-ro-do]');
        if (actionItem) {
            e.preventDefault();
            const data = Object.assign({}, actionItem.dataset);
            closeOpenMenu();
            runAction(data);
            return;
        }

        const btn = e.target.closest('.ro-actions-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const menu = btn.closest('.ro-actions').querySelector('.ro-actions-menu');
            const alreadyOpen = openMenu && openMenu.btn === btn;

            closeOpenMenu();
            if (alreadyOpen || !menu) return;

            const placeholder = document.createComment('ro-menu-placeholder');
            menu.replaceWith(placeholder);
            page.appendChild(menu);
            positionMenu(btn, menu);
            btn.setAttribute('aria-expanded', 'true');
            openMenu = { menu, placeholder, btn };
            return;
        }

        if (openMenu && !e.target.closest('.ro-actions-menu')) closeOpenMenu();
    });

    window.addEventListener('scroll', closeOpenMenu, true);
    window.addEventListener('resize', closeOpenMenu);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeOpenMenu();
    });

    // Фокус на поле torrent ID при открытии модалки
    const modal = document.getElementById('roCompleteModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', () => {
            const input = document.getElementById('roTorrentId');
            if (input) { input.value = ''; input.focus(); }
        });
    }
})();
