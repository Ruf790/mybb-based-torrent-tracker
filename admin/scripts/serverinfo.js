/* Server information - вкладки, фильтры, навигация по phpinfo() */
(() => {
    'use strict';

    const page = document.querySelector('.si-page');
    if (!page) return;

    function t(key, fallback, ...args) {
        let str = Object.prototype.hasOwnProperty.call(window.AGS_LANG || {}, key) ? window.AGS_LANG[key] : fallback;
        args.forEach((arg, i) => { str = str.replaceAll('{' + (i + 1) + '}', String(arg)); });
        return str;
    }

    // ── Вкладки: активная хранится в адресе (#si-php и т.п.) ──
    const tabs = [...page.querySelectorAll('.si-tab[data-bs-target]')];

    function showTab(btn) {
        if (window.bootstrap?.Tab) {
            bootstrap.Tab.getOrCreateInstance(btn).show();
            return;
        }
        // Запасной вариант без bootstrap.js
        tabs.forEach(t => {
            const on = t === btn;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            const pane = page.querySelector(t.dataset.bsTarget);
            pane?.classList.toggle('show', on);
            pane?.classList.toggle('active', on);
        });
    }

    tabs.forEach(t => t.addEventListener('click', e => {
        e.preventDefault();
        showTab(t);
        history.replaceState(null, '', location.pathname + location.search + t.dataset.bsTarget);
        // phpinfo очень длинный - при переключении возвращаемся к вкладкам
        page.querySelector('.si-tabs').scrollIntoView({ block: 'nearest' });
    }));

    const fromHash = tabs.find(t => t.dataset.bsTarget === location.hash);
    if (fromHash) showTab(fromHash);

    // ── Фильтр переменных MySQL ─────────────────────────
    const varFilter = document.getElementById('siVarFilter');
    const varCount  = document.getElementById('siVarCount');
    if (varFilter) {
        const rows = [...document.querySelectorAll('#siVarTable tbody tr')];
        varFilter.addEventListener('input', () => {
            const q = varFilter.value.trim().toLowerCase();
            let n = 0;
            rows.forEach(r => {
                const hit = !q || r.dataset.search.includes(q);
                r.hidden = !hit;
                if (hit) n++;
            });
            if (varCount) varCount.textContent = q ? t('filter_count', '{1} of {2}', n, rows.length) : String(rows.length);
        });
    }

    // ── phpinfo(): кнопки модулей и фильтр ──────────────
    const info    = document.getElementById('siPhpinfo');
    const modules = document.getElementById('siModules');
    if (info) {
        const heads = [...info.querySelectorAll('h2')];

        heads.forEach((h, i) => {
            h.id = h.id || 'si-mod-' + i;
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = h.textContent.trim();
            b.addEventListener('click', () => h.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            modules?.appendChild(b);
        });

        // Строки таблиц и заголовки модулей; заголовок и таблицы модуля
        // скрываются, если в модуле не осталось подходящих строк.
        const sections = heads.map(h => {
            const tables = [];
            let el = h.nextElementSibling;
            while (el && el.tagName !== 'H2') {
                if (el.tagName === 'TABLE') tables.push(el);
                el = el.nextElementSibling;
            }
            return { h, tables };
        });

        const filter = document.getElementById('siInfoFilter');
        let t;
        filter?.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(() => {
                const q = filter.value.trim().toLowerCase();
                sections.forEach(({ h, tables }) => {
                    const modHit = !q || h.textContent.toLowerCase().includes(q);
                    let any = modHit;
                    tables.forEach(tb => {
                        let tableAny = false;
                        tb.querySelectorAll('tr').forEach(tr => {
                            if (tr.classList.contains('h')) return;
                            const hit = modHit || tr.textContent.toLowerCase().includes(q);
                            tr.classList.toggle('si-hidden', !hit);
                            if (hit) tableAny = true;
                        });
                        tb.classList.toggle('si-hidden', !tableAny);
                        if (tableAny) any = true;
                    });
                    h.classList.toggle('si-hidden', !any);
                });
                modules?.querySelectorAll('button').forEach((b, i) => {
                    b.hidden = sections[i].h.classList.contains('si-hidden');
                });
            }, 150);
        });
    }
})();
