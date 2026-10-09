/**
 * Forum Management (admin/management.php) — весь JS страниц.
 * Подключается один раз в fm_head_assets(). Каждый блок сам проверяет,
 * есть ли на странице нужные элементы, поэтому файл общий для всех action.
 */
(function () {
    'use strict';

    // Замена document.write('<style>.popup_button{display:inline}.popup_menu{display:none}</style>'):
    // скрипт синхронный и стоит там же, где стоял document.write, поэтому эффект тот же.
    document.documentElement.classList.add('fm2-js');

    /** Строка из AGS_LANG (js_* без префикса) с английским fallback и подстановкой {1}, {2}… */
    const t = (key, fallback, ...args) => {
        const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
        let str = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
        args.forEach((a, i) => { str = str.split('{' + (i + 1) + '}').join(String(a)); });
        return str;
    };

    /** Иконка + текст (текст — только через textNode) */
    const iconText = (iconClass, text) => {
        const i = document.createElement('i');
        i.className = iconClass;
        return [i, document.createTextNode(text)];
    };

    const onReady = fn => {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    };

    /** POST через временную форму (модалки удаления/очистки) */
    function postForm(action, fields) {
        const f = document.createElement('form');
        f.method = 'post';
        f.action = action;
        Object.entries(fields).forEach(([n, v]) => {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = n;
            i.value = v ?? '';
            f.appendChild(i);
        });
        document.body.appendChild(f);
        f.submit();
    }

    // ── Глобальные функции для onclick="" в разметке ──────────
    window.toggleAdditionalOptions = function () {
        const l = document.getElementById('additional_options_link');
        const o = document.getElementById('additional_options');
        if (!l || !o) return false;
        if (o.style.display === 'block' || o.style.display === '') {
            l.style.display = 'block'; o.style.display = 'none';
        } else {
            l.style.display = 'none'; o.style.display = 'block';
        }
        return false;
    };

    window.selectType = function (type) {
        document.querySelectorAll('.type-card').forEach(c => {
            c.classList.remove('active');
            const ic = c.querySelector('.type-check i');
            if (ic) ic.className = 'far fa-circle';
            const r = c.querySelector('input[type="radio"]');
            if (r) r.checked = false;
        });
        const card = document.querySelector('.type-card[data-type="' + type + '"]');
        if (card) {
            card.classList.add('active');
            const ic = card.querySelector('.type-check i');
            if (ic) ic.className = 'fas fa-check-circle';
            const r = card.querySelector('input[type="radio"]');
            if (r) r.checked = true;
        }
    };

    // ── Ошибки формы (fm_errors) → showToast() ────────────────
    function initErrors() {
        document.querySelectorAll('.fm2-errors').forEach(el => {
            let messages;
            try { messages = JSON.parse(el.dataset.messages || '[]'); } catch (e) { return; }
            if (!messages.length) return;
            const show = () => {
                if (typeof window.showToast === 'function') window.showToast(messages, 'error');
                else console.warn('toast.js not available:', messages);
            };
            if (typeof window.showToast === 'function' || !el.dataset.toastSrc) { show(); return; }
            const s = document.createElement('script');
            s.src = el.dataset.toastSrc;
            s.onload = show;
            s.onerror = show;
            document.head.appendChild(s);
        });
    }

    // ── Тип форума (Forum / Category) + счётчик описания ──────
    function initTypeCards() {
        const checked = document.querySelector('input[name="type"]:checked');
        const card = checked && checked.closest('.type-card');
        if (card) {
            card.classList.add('active');
            const ic = card.querySelector('.type-check i');
            if (ic) ic.className = 'fas fa-check-circle';
        }
        const d = document.getElementById('description');
        const c = document.getElementById('charCount');
        if (d && c) d.addEventListener('input', function () { c.textContent = Math.min(this.value.length, 500); });
    }

    // QuickPermEditor сам инициализирует все tr[data-group-id] (scripts/quick_perm_editor.js)

    // ── Модалки «Clear permissions» и «Remove moderator» ─────
    // Делегирование на document: строки прав заменяются после сохранения в AJAX-модалке,
    // и у новых кнопок .clear-permission-btn не было бы своих обработчиков.
    function initConfirmModals() {
        const clearModal = document.getElementById('clearPermissionModal');
        const clearBtn   = document.getElementById('confirmClearBtn');
        const modModal   = document.getElementById('deleteModeratorModal');
        const modBtn     = document.getElementById('confirmDeleteModeratorBtn');
        let cur = {};
        let d = {};

        document.addEventListener('click', e => {
            const cb = clearModal && e.target.closest('.clear-permission-btn');
            if (cb) {
                e.preventDefault();
                cur = { pid: cb.dataset.pid, fid: cb.dataset.fid, gid: cb.dataset.gid,
                        groupName: cb.dataset.groupName, postKey: cb.dataset.postKey };
                const name = document.getElementById('modalGroupName');
                if (name) name.textContent = cur.groupName;
                bootstrap.Modal.getOrCreateInstance(clearModal).show();
                return;
            }
            const mb = modModal && e.target.closest('.delete-moderator-btn');
            if (mb) {
                e.preventDefault();
                d = { mid: mb.dataset.mid, fid: mb.dataset.fid, isgroup: mb.dataset.isgroup, postKey: mb.dataset.postKey };
                bootstrap.Modal.getOrCreateInstance(modModal).show();
            }
        });

        clearBtn?.addEventListener('click', () => postForm(
            'index.php?act=management&action=clear_permission',
            { pid: cur.pid, fid: cur.fid, gid: cur.gid, my_post_key: cur.postKey }
        ));
        modBtn?.addEventListener('click', () => postForm(
            'index.php?act=management&action=deletemod',
            { id: d.mid, fid: d.fid, isgroup: d.isgroup, my_post_key: d.postKey }
        ));
    }

    // ── Copy Forum ────────────────────────────────────────────
    function initCopyForum() {
        const form = document.getElementById('copyForumForm');
        const toSel = document.getElementById('to');
        const cg = document.getElementById('copygroups');
        if (!form || !toSel || !cg) return;
        const newF = document.getElementById('newForumSettings');
        const copyS = document.getElementById('copySettings');
        const sgList = document.getElementById('selectedGroupsList');

        const syncTo = () => {
            const v = toSel.value;
            if (newF) newF.style.display = v === '-1' ? 'block' : 'none';
            if (copyS) copyS.style.display = v !== '-1' && v !== '' ? 'block' : 'none';
        };
        const syncGroups = () => {
            if (!sgList) return;
            const sel = Array.from(cg.selectedOptions);
            if (sel.length) {
                sgList.replaceChildren(...sel.map(o => {
                    const b = document.createElement('span');
                    b.className = 'badge bg-primary me-1 mb-1';
                    b.textContent = o.text;
                    return b;
                }));
            } else {
                const p = document.createElement('p');
                p.className = 'text-muted small mb-0';
                p.textContent = t('no_groups_selected', 'No groups selected');
                sgList.replaceChildren(p);
            }
        };
        const setAll = on => { Array.from(cg.options).forEach(o => { o.selected = on; }); syncGroups(); };

        toSel.addEventListener('change', syncTo);
        cg.addEventListener('change', syncGroups);
        document.getElementById('selectAllGroups')?.addEventListener('click', () => setAll(true));
        document.getElementById('deselectAllGroups')?.addEventListener('click', () => setAll(false));
        syncTo(); syncGroups();

        form.addEventListener('submit', function () {
            const btn = this.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.disabled = true;
            btn.replaceChildren(...iconText('fas fa-spinner fa-spin me-2', t('copying', 'Copying...')));
        });
    }

    // ── Edit Moderator: счётчик включённых прав, «всё вкл/выкл» ─
    function initEditModerator() {
        const form = document.getElementById('editModForm');
        if (!form) return;
        const count = () => {
            const n = form.querySelectorAll('.fm2-sw input:checked').length;
            const el = document.getElementById('fm2SwCount');
            if (el) el.replaceChildren(...iconText('fa-solid fa-toggle-on', t('sw_count', '{1} enabled', n)));
        };
        form.addEventListener('change', count);
        form.addEventListener('reset', () => setTimeout(count, 0));
        form.querySelectorAll('[data-sw-all]').forEach(b => b.addEventListener('click', () => {
            const on = b.dataset.swAll === '1';
            b.closest('.fm2-msec').querySelectorAll('.fm2-sw input').forEach(cb => { cb.checked = on; });
            count();
        }));
        count();
    }

    // ── Главная страница: поповеры, вкладка по #hash, фильтр ──
    function initMainPage() {
        if (window.bootstrap && bootstrap.Popover) {
            document.querySelectorAll('.fm2 [data-bs-toggle="popover"]').forEach(el =>
                bootstrap.Popover.getOrCreateInstance(el, { container: 'body', trigger: 'hover focus' }));
        }
        const h = location.hash.replace('#tab_', '').replace('#', '');
        const btn = h && document.querySelector('#forumTabs [data-bs-target="#' + CSS.escape(h) + '"]');
        if (btn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(btn).show();

        const f = document.getElementById('fm2Filter');
        const noMatch = document.getElementById('fm2NoMatch');
        f && f.addEventListener('input', function () {
            const q = f.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('.fm2 .fm2-table tbody tr[data-search]').forEach(tr => {
                const ok = !q || tr.dataset.search.includes(q);
                tr.hidden = !ok; if (ok) shown++;
            });
            if (noMatch) noMatch.hidden = shown !== 0;
        });
    }

    // AJAX-модалка прав (action=permissions&ajax=1): загрузку, вкладки и сохранение
    // #modal_form обрабатывает scripts/popup.js (popupWindow + handleModalFormSubmit).

    onReady(() => {
        initErrors();
        initTypeCards();
        initConfirmModals();
        initCopyForum();
        initEditModerator();
        initMainPage();
    });
})();