/* Lang Checker — вкладка «Редактор» (admin/include/functions_langcheck_editor.php) */
(function () {
    'use strict';
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (key, fallback, ...args) => (typeof L[key] === 'string' ? L[key] : fallback)
        .replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => { const v = args[(a || b) - 1]; return v === undefined ? m : String(v); });

    const app = document.getElementById('lceApp');
    if (!app) return;

    const url = app.dataset.url, csrf = app.dataset.key, langs = app.dataset.langs.split(',').filter(Boolean);
    const table = document.getElementById('lceTable'), fileSel = document.getElementById('lceFile');
    const filterEl = document.getElementById('lceFilter'), problemsEl = document.getElementById('lceProblems');
    const searchEl = document.getElementById('lceSearch'), resultsEl = document.getElementById('lceResults');
    const kpi = { keys: document.getElementById('lceKpiKeys'), empty: document.getElementById('lceKpiEmpty'), missing: document.getElementById('lceKpiMissing') };

    let current = null;                 // { page, state, rows }
    let openEditors = 0;
    let loadToken = 0, searchToken = 0, searchTimer;

    function el(tag, cls, text) {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }
    function icon(cls) { const i = el('i', cls); i.setAttribute('aria-hidden', 'true'); return i; }

    function notice(cls, text) {
        table.replaceChildren(el('div', 'lce-note ' + cls, text));
    }

    async function getJson(params) {
        const q = new URLSearchParams(params);
        const res = await fetch(url + '&' + q.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        return res.json();
    }

    /* ── Состояние строк ── */
    function cellKind(row, lang) {
        const c = row.c[lang];
        if (!c) return 'off';
        if (c.ro) return 'ro';
        if (c.v === null) return 'missing';
        if (c.v === '') return 'empty';
        return 'ok';
    }
    /** Пустая строка — проблема, только если в другом языке текст есть (иначе пусто намеренно) */
    function rowProblems(row) {
        const kinds = langs.map(l => cellKind(row, l));
        const hasText = kinds.includes('ok') || kinds.includes('ro');
        return { missing: kinds.filter(k => k === 'missing').length, empty: hasText ? kinds.filter(k => k === 'empty').length : 0 };
    }
    function updateKpi() {
        if (!current) return;
        let empty = 0, missing = 0;
        current.rows.forEach(r => { const p = rowProblems(r); empty += p.empty; missing += p.missing; });
        kpi.keys.textContent = current.rows.length.toLocaleString();
        kpi.empty.textContent = empty.toLocaleString();
        kpi.missing.textContent = missing.toLocaleString();
    }

    function rowVisible(row) {
        if (problemsEl.checked) {
            const p = rowProblems(row);
            if (!p.empty && !p.missing) return false;
        }
        const q = filterEl.value.trim().toLowerCase();
        if (!q) return true;
        if (row.k.toLowerCase().includes(q)) return true;
        return langs.some(l => row.c[l] && typeof row.c[l].v === 'string' && row.c[l].v.toLowerCase().includes(q));
    }
    function applyFilter() {
        if (!current) return;
        let anyVisible = false;
        table.querySelectorAll('.lce-row[data-i]').forEach(node => {
            const show = rowVisible(current.rows[+node.dataset.i]);
            node.hidden = !show;
            if (show) anyVisible = true;
        });
        // Заголовок раздела виден, только если виден хотя бы один его ключ
        table.querySelectorAll('.lce-sec').forEach(sec => {
            let n = sec.nextElementSibling, show = false;
            while (n && !n.classList.contains('lce-sec')) {
                if (!n.hidden && n.dataset.i !== undefined) { show = true; break; }
                n = n.nextElementSibling;
            }
            sec.hidden = !show;
        });
        let empty = table.querySelector('.lce-none');
        if (!anyVisible && !empty) {
            table.appendChild(el('div', 'lce-note lce-none', t('ed_no_rows', 'Nothing matches the filter.')));
        } else if (anyVisible && empty) {
            empty.remove();
        }
    }

    /* ── Отрисовка ── */
    function fillCell(cell, row, lang) {
        const kind = cellKind(row, lang);
        cell.className = 'lce-cell is-' + kind;
        cell.replaceChildren();
        cell.removeAttribute('tabindex');
        cell.removeAttribute('role');
        cell.removeAttribute('title');
        if (kind === 'off') {
            cell.appendChild(el('span', 'lce-tag', t('ed_no_file', 'File not available')));
            return;
        }
        if (kind === 'ro') {
            cell.title = t('ed_readonly', 'This value is built in code and can only be changed in the file');
            cell.appendChild(icon('fa-solid fa-lock me-1'));
            cell.appendChild(document.createTextNode(row.c[lang].v === null ? t('ed_expression', 'PHP expression') : row.c[lang].v));
            return;
        }
        cell.tabIndex = 0;
        cell.setAttribute('role', 'button');
        if (kind === 'missing') cell.appendChild(el('span', 'lce-tag is-bad', t('ed_missing', 'Missing — click to add')));
        else if (kind === 'empty') cell.appendChild(el('span', 'lce-tag', t('ed_empty', 'Empty')));
        else cell.textContent = row.c[lang].v;
    }

    function renderRows() {
        const frag = document.createDocumentFragment();
        const head = el('div', 'lce-row lce-head');
        head.appendChild(el('div', 'lce-key', t('ed_col_key', 'Key')));
        langs.forEach(l => head.appendChild(el('div', 'lce-cell', l)));
        frag.appendChild(head);

        current.rows.forEach((row, i) => {
            if (row.sec) frag.appendChild(el('div', 'lce-sec', row.sec));
            const node = el('div', 'lce-row');
            node.dataset.i = String(i);
            node.dataset.key = row.k;
            const key = el('div', 'lce-key');
            key.appendChild(el('code', '', row.k));
            node.appendChild(key);
            langs.forEach(lang => {
                const cell = el('div');
                cell.dataset.lang = lang;
                fillCell(cell, row, lang);
                node.appendChild(cell);
            });
            frag.appendChild(node);
        });
        table.replaceChildren(frag);
        applyFilter();
    }

    /* ── Редактирование ── */
    function openEditor(cell) {
        if (cell.classList.contains('is-editing')) return;
        const node = cell.closest('.lce-row');
        const row = current.rows[+node.dataset.i], lang = cell.dataset.lang;
        const state = { rev: row.c[lang].r, original: row.c[lang].v ?? '' };

        cell.classList.add('is-editing');
        cell.removeAttribute('role');
        cell.removeAttribute('tabindex');
        openEditors++;

        const area = el('textarea', 'form-control lce-area');
        area.value = state.original;
        area.rows = Math.min(12, Math.max(2, state.original.split('\n').length + Math.floor(state.original.length / 70)));
        area.setAttribute('aria-label', row.k + ' — ' + lang);
        const error = el('div', 'lce-error');
        error.hidden = true;
        const save = el('button', 'btn btn-sm lc-pill lc-go', t('ed_save', 'Save'));
        save.type = 'button';
        const cancel = el('button', 'btn btn-sm lc-pill', t('ed_cancel', 'Cancel'));
        cancel.type = 'button';
        const actions = el('div', 'lce-actions');
        actions.append(save, cancel);
        cell.replaceChildren(area, error, actions);
        area.focus();

        function close() {
            openEditors--;
            fillCell(cell, row, lang);
            cell.focus();
            applyFilter();
        }
        function showError(text) { error.textContent = text; error.hidden = false; }

        async function submit(rev) {
            save.disabled = true;
            error.hidden = true;
            try {
                const body = new URLSearchParams({ my_post_key: csrf, page: current.page, lang, key: row.k, value: area.value, rev });
                const res = await fetch(url + '&ajax=save', { method: 'POST', credentials: 'same-origin', body });
                const data = await res.json();
                if (data.ok) {
                    row.c[lang].v = data.value;
                    row.c[lang].r = data.rev;
                    updateKpi();
                    close();
                    return;
                }
                if (data.code === 'conflict') { await resolveConflict(data); return; }
                showError(data.error || t('ed_save_failed', 'Could not save.'));
            } catch (e) {
                showError(t('ed_save_failed', 'Could not save.'));
            } finally {
                save.disabled = false;
            }
        }

        async function resolveConflict(data) {
            const text = data.current === null || data.current === undefined
                ? t('ed_conflict_removed', 'Someone has removed this key in the meantime.')
                : t('ed_conflict', 'Someone changed this value while you were editing. Current value: {1}', data.current);
            let overwrite;
            if (window.Swal) {
                const r = await Swal.fire({
                    icon: 'warning', title: t('ed_conflict_title', 'Value changed'), text,
                    showCancelButton: true,
                    confirmButtonText: t('ed_overwrite', 'Overwrite'),
                    cancelButtonText: t('ed_use_current', 'Use current value'),
                });
                overwrite = r.isConfirmed;
                if (r.isDismissed && r.dismiss !== Swal.DismissReason.cancel) return;   // закрыли окно — правка остаётся открытой
            } else {
                overwrite = window.confirm(text + '\n\n' + t('ed_overwrite', 'Overwrite') + '?');
            }
            if (overwrite) { await submit(data.rev || ''); return; }
            if (data.current === null || data.current === undefined) {
                row.c[lang] = { v: null, r: '', ro: false };
            } else {
                row.c[lang].v = data.current;
                row.c[lang].r = data.rev;
            }
            updateKpi();
            close();
        }

        save.addEventListener('click', () => submit(state.rev));
        cancel.addEventListener('click', close);
        area.addEventListener('keydown', e => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); if (!save.disabled) submit(state.rev); }
            if (e.key === 'Escape') { e.preventDefault(); close(); }
        });
    }

    table.addEventListener('click', e => {
        const cell = e.target.closest('.lce-cell[role="button"]');
        if (cell && !window.getSelection().toString()) openEditor(cell);
    });
    table.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.lce-cell[role="button"]')) {
            e.preventDefault();
            openEditor(e.target);
        }
    });

    /* ── Загрузка файла ── */
    function focusKey(key) {
        if (!key) return;
        const node = [...table.querySelectorAll('.lce-row[data-i]')].find(n => n.dataset.key === key);
        if (!node) return;
        node.classList.add('is-focus');
        node.scrollIntoView({ block: 'center' });
    }

    async function loadFile(page, key) {
        const token = ++loadToken;
        current = null;
        openEditors = 0;
        notice('', t('ed_loading', 'Loading…'));
        const qs = new URLSearchParams(location.search);
        qs.set('page', page);
        if (key) qs.set('key', key); else qs.delete('key');
        history.replaceState(null, '', location.pathname + '?' + qs.toString());
        try {
            const data = await getJson({ ajax: 'file', page });
            if (token !== loadToken) return;
            if (!data.ok) { notice('is-bad', data.error || t('ed_load_failed', 'Could not load the file.')); return; }
            current = data;
            updateKpi();
            if (!data.rows.length) {
                notice('is-bad', t('ed_unsupported', 'This file has a different format and can only be edited by hand.'));
                return;
            }
            renderRows();
            focusKey(key);
        } catch (e) {
            if (token === loadToken) notice('is-bad', t('ed_load_failed', 'Could not load the file.'));
        }
    }

    function confirmDiscard() {
        return !openEditors || window.confirm(t('ed_unsaved', 'You have unsaved edits. Discard them?'));
    }

    let lastPage = fileSel.value;
    fileSel.addEventListener('change', () => {
        if (!confirmDiscard()) { fileSel.value = lastPage; return; }
        lastPage = fileSel.value;
        loadFile(lastPage, '');
    });
    filterEl.addEventListener('input', applyFilter);
    problemsEl.addEventListener('change', applyFilter);
    window.addEventListener('beforeunload', e => { if (openEditors) { e.preventDefault(); e.returnValue = ''; } });

    /* ── Поиск по всем файлам ── */
    function renderResults(data) {
        resultsEl.hidden = false;
        if (!data.hits.length) {
            resultsEl.replaceChildren(el('div', 'lce-note', t('ed_no_results', 'Nothing found.')));
            return;
        }
        const frag = document.createDocumentFragment();
        frag.appendChild(el('div', 'lc-muted lce-res-count', data.more
            ? t('ed_results_more', 'Showing the first {1} matches — refine the search', data.hits.length)
            : t('ed_results', 'Matches: {1}', data.hits.length)));
        data.hits.forEach(h => {
            const b = el('button', 'lce-hit');
            b.type = 'button';
            const where = el('span', 'lce-hit-where');
            where.append(el('code', '', h.page), el('code', '', h.key));
            b.append(where, el('span', 'lce-hit-text', h.text));
            b.addEventListener('click', () => {
                if (!confirmDiscard()) return;
                filterEl.value = '';
                problemsEl.checked = false;
                fileSel.value = h.page;
                lastPage = h.page;
                loadFile(h.page, h.key);
            });
            frag.appendChild(b);
        });
        resultsEl.replaceChildren(frag);
    }

    searchEl.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = searchEl.value.trim();
        const token = ++searchToken;
        if (q.length < 2) { resultsEl.hidden = true; resultsEl.replaceChildren(); return; }
        searchTimer = setTimeout(async () => {
            try {
                const data = await getJson({ ajax: 'search', q });
                if (token !== searchToken) return;
                if (data.ok) renderResults(data);
            } catch (e) {
                if (token === searchToken) {
                    resultsEl.hidden = false;
                    resultsEl.replaceChildren(el('div', 'lce-note is-bad', t('ed_load_failed', 'Could not load the file.')));
                }
            }
        }, 300);
    });

    if (fileSel.value) loadFile(fileSel.value, app.dataset.focusKey || '');
    else notice('is-bad', t('ed_load_failed', 'Could not load the file.'));
})();
