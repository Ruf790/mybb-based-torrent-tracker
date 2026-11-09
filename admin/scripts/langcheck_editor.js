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
    const tableCard = table.parentElement, rawCard = document.getElementById('lceRaw'), rawText = document.getElementById('lceRawText');
    const rawLangEl = document.getElementById('lceRawLang'), rawSave = document.getElementById('lceRawSave'), rawError = document.getElementById('lceRawError');
    const viewTable = document.getElementById('lceViewTable'), viewRaw = document.getElementById('lceViewRaw'), problemsWrap = document.getElementById('lceProblemsWrap');
    const kpi = { keys: document.getElementById('lceKpiKeys'), empty: document.getElementById('lceKpiEmpty'), missing: document.getElementById('lceKpiMissing') };

    let current = null;                 // { page, state, rows }
    let openEditors = 0;
    let loadToken = 0, searchToken = 0, searchTimer;

    const startQs = new URLSearchParams(location.search);
    let view = startQs.get('view') === 'raw' ? 'raw' : 'table';
    let cm = null, cmReady = null, cmMark = null;
    let rawOriginal = '', rawHash = '', rawLoaded = false, rawToken = 0;

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
                    loadHistory();
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
        qs.delete('view');
        qs.delete('rawlang');
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

    /* ── Весь файл ── */
    const CM_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/';
    const isDark = () => (document.documentElement.getAttribute('data-bs-theme') || document.body.getAttribute('data-bs-theme')) === 'dark';

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src; s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    function loadCss(href) {
        const l = document.createElement('link');
        l.rel = 'stylesheet'; l.href = href;
        document.head.appendChild(l);
    }
    /** CodeMirror грузится только при первом открытии вкладки; не загрузился — работает обычное поле */
    function ensureEditor() {
        if (cmReady) return cmReady;
        cmReady = (async () => {
            try {
                ['codemirror.min.css', 'addon/dialog/dialog.min.css', 'theme/eclipse.min.css', 'theme/material-darker.min.css'].forEach(f => loadCss(CM_BASE + f));
                await loadScript(CM_BASE + 'codemirror.min.js');
                for (const f of ['mode/xml/xml', 'mode/javascript/javascript', 'mode/css/css', 'mode/htmlmixed/htmlmixed', 'mode/clike/clike', 'mode/php/php',
                                 'addon/dialog/dialog', 'addon/search/searchcursor', 'addon/search/search']) {
                    await loadScript(CM_BASE + f + '.min.js');
                }
                cm = CodeMirror.fromTextArea(rawText, {
                    mode: 'application/x-httpd-php', lineNumbers: true, lineWrapping: true, indentUnit: 4,
                    indentWithTabs: true, theme: isDark() ? 'material-darker' : 'eclipse',
                    extraKeys: { 'Ctrl-S': () => saveRaw(false), 'Cmd-S': () => saveRaw(false) },
                });
                cm.on('change', clearRawError);
            } catch (e) {
                cm = null;
            }
        })();
        return cmReady;
    }
    const getRaw = () => (cm ? cm.getValue() : rawText.value).replace(/\r\n?/g, '\n');
    function setRaw(text) {
        if (cm) { cm.setValue(text); cm.clearHistory(); cm.refresh(); } else { rawText.value = text; }
    }
    const rawDirty = () => view === 'raw' && rawLoaded && getRaw() !== rawOriginal;

    function clearRawError() {
        rawError.hidden = true;
        if (cm && cmMark !== null) { cm.removeLineClass(cmMark, 'background', 'lce-bad-line'); cmMark = null; }
    }
    function showRawError(text, line) {
        rawError.textContent = text;
        rawError.hidden = false;
        if (cm && line) {
            if (cmMark !== null) cm.removeLineClass(cmMark, 'background', 'lce-bad-line');
            cmMark = cm.addLineClass(line - 1, 'background', 'lce-bad-line');
            cm.setCursor({ line: line - 1, ch: 0 });
            cm.scrollIntoView({ line: line - 1, ch: 0 }, 120);
        }
    }

    async function ask(icon, title, text, confirmText, cancelText) {
        if (window.Swal) {
            const r = await Swal.fire({ icon, title, text, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: cancelText });
            return r.isConfirmed;
        }
        return window.confirm(title + '\n\n' + text);
    }

    async function loadRaw() {
        const token = ++rawToken;
        rawLoaded = false;
        clearRawError();
        rawSave.disabled = true;
        await ensureEditor();
        try {
            const data = await getJson({ ajax: 'raw', page: fileSel.value, lang: rawLangEl.value });
            if (token !== rawToken) return;
            if (!data.ok) { setRaw(''); showRawError(data.error || t('ed_load_failed', 'Could not load the file.')); return; }
            rawOriginal = data.src.replace(/\r\n?/g, '\n');
            rawHash = data.hash;
            setRaw(rawOriginal);
            rawLoaded = true;
            rawSave.disabled = false;
            clearRawError();
        } catch (e) {
            if (token === rawToken) showRawError(t('ed_load_failed', 'Could not load the file.'));
        }
    }

    async function saveRaw(confirmRemoved) {
        if (!rawLoaded || rawSave.disabled) return;
        rawSave.disabled = true;
        clearRawError();
        let reload = false, retry = false;
        const src = getRaw();
        try {
            const body = new URLSearchParams({ my_post_key: csrf, page: fileSel.value, lang: rawLangEl.value, src, hash: rawHash, confirm_removed: confirmRemoved ? '1' : '0' });
            const res = await fetch(url + '&ajax=saveraw', { method: 'POST', credentials: 'same-origin', body });
            const data = await res.json();
            if (data.ok) {
                rawHash = data.hash;
                rawOriginal = src;
                loadHistory();
                if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: t('ed_saved', 'Saved'), timer: 1800, showConfirmButton: false });
            } else if (data.code === 'conflict_file') {
                reload = await ask('warning', t('ed_file_changed_title', 'File changed'),
                    t('ed_file_changed', 'Someone changed this file while you were editing. Reload it? Your edits will be lost.'),
                    t('ed_reload', 'Reload'), t('ed_keep', 'Keep editing'));
            } else if (data.code === 'confirm_removed') {
                const names = data.removed.slice(0, 30).join(', ') + (data.removed.length > 30 ? ' …' : '');
                retry = await ask('warning', t('ed_removed_title', 'Keys will disappear'),
                    t('ed_removed', 'These keys are removed or renamed, pages that use them may break: {1}', names),
                    t('ed_removed_confirm', 'Save anyway'), t('ed_cancel', 'Cancel'));
            } else {
                showRawError(data.error || t('ed_save_failed', 'Could not save.'), data.line);
            }
        } catch (e) {
            showRawError(t('ed_save_failed', 'Could not save.'));
        } finally {
            rawSave.disabled = false;
        }
        if (reload) await loadRaw();
        if (retry) await saveRaw(true);
    }

    function syncUrl() {
        const qs = new URLSearchParams(location.search);
        qs.set('page', fileSel.value);
        if (view === 'raw') { qs.set('view', 'raw'); qs.set('rawlang', rawLangEl.value); qs.delete('key'); }
        else { qs.delete('view'); qs.delete('rawlang'); }
        history.replaceState(null, '', location.pathname + '?' + qs.toString());
    }
    function applyView() {
        const raw = view === 'raw';
        tableCard.hidden = raw;
        rawCard.hidden = !raw;
        rawLangEl.hidden = !raw;
        filterEl.hidden = raw;
        problemsWrap.hidden = raw;
        document.getElementById('lceTableHint').hidden = raw;
        // Счётчики относятся к таблице: во «Всём файле» они бы показывали прошлый файл
        if (raw) { kpi.keys.textContent = kpi.empty.textContent = kpi.missing.textContent = '—'; }
        viewTable.classList.toggle('active', !raw);
        viewRaw.classList.toggle('active', raw);
    }
    function reload(key) {
        if (view === 'raw') { syncUrl(); loadRaw(); } else { loadFile(fileSel.value, key || ''); }
    }

    function confirmDiscard() {
        if (!openEditors && !rawDirty()) return true;
        if (!window.confirm(t('ed_unsaved', 'You have unsaved edits. Discard them?'))) return false;
        openEditors = 0;
        rawLoaded = false;
        return true;
    }
    function setView(next) {
        if (next === view || !confirmDiscard()) return;
        view = next;
        applyView();
        reload('');
    }
    viewTable.addEventListener('click', () => setView('table'));
    viewRaw.addEventListener('click', () => setView('raw'));
    rawSave.addEventListener('click', () => saveRaw(false));
    rawText.addEventListener('input', clearRawError);
    rawText.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveRaw(false); }
    });

    let lastPage = fileSel.value, lastRawLang;
    fileSel.addEventListener('change', () => {
        if (!confirmDiscard()) { fileSel.value = lastPage; return; }
        lastPage = fileSel.value;
        reload('');
    });
    rawLangEl.addEventListener('change', () => {
        if (!confirmDiscard()) { rawLangEl.value = lastRawLang; return; }
        lastRawLang = rawLangEl.value;
        reload('');
    });
    filterEl.addEventListener('input', applyFilter);
    problemsEl.addEventListener('change', applyFilter);
    window.addEventListener('beforeunload', e => { if (openEditors || rawDirty()) { e.preventDefault(); e.returnValue = ''; } });

    /* ── История ── */
    const historyBtn = document.getElementById('lceHistoryBtn'), historyEl = document.getElementById('lceHistory');

    function historyText(w) {
        if (w.t === 'key') return t('ed_hist_key', 'Changed: {1}', w.k);
        if (w.t === 'add') return t('ed_hist_add', 'Added: {1}', w.k);
        if (w.t === 'restore') return t('ed_hist_restore_entry', 'Rollback');
        return t('ed_hist_file', 'Whole file: {1} changed, {2} added, {3} removed', w.c, w.a, w.r);
    }

    async function loadHistory() {
        if (historyEl.hidden) return;
        try {
            const data = await getJson({ ajax: 'history', page: fileSel.value });
            if (!data.ok) { historyEl.replaceChildren(el('div', 'lce-note is-bad', data.error || '')); return; }
            if (!data.entries.length) {
                historyEl.replaceChildren(el('div', 'lce-note', t('ed_hist_empty', 'No saved edits for this file yet.')));
                return;
            }
            const frag = document.createDocumentFragment();
            data.entries.forEach(h => {
                const row = el('div', 'lce-hrow');
                const info = el('div', 'lce-hinfo');
                const meta = el('div', 'lc-muted lce-hmeta');
                meta.append(el('span', '', new Date(h.t * 1000).toLocaleString()), el('span', '', h.user), el('code', '', h.lang));
                info.append(meta, el('div', 'lce-hwhat', historyText(h.what)));
                const b = el('button', 'btn btn-sm lc-pill', t('ed_hist_restore', 'Roll back'));
                b.type = 'button';
                b.addEventListener('click', () => restore(h));
                row.append(info, b);
                frag.appendChild(row);
            });
            historyEl.replaceChildren(frag);
        } catch (e) {
            historyEl.replaceChildren(el('div', 'lce-note is-bad', t('ed_load_failed', 'Could not load the file.')));
        }
    }

    function diffNode(data) {
        const box = el('div', 'lce-diff');
        data.changes.forEach(c => {
            const line = el('div', 'lce-diff-row');
            line.append(el('code', '', c.k));
            line.append(el('div', 'lce-diff-from', c.from === null ? t('ed_hist_none', '(no key)') : c.from));
            line.append(el('div', 'lce-diff-to', c.to === null ? t('ed_hist_none', '(no key)') : c.to));
            box.appendChild(line);
        });
        if (data.total > data.changes.length) box.appendChild(el('div', 'lc-muted', t('ed_hist_more', 'and {1} more', data.total - data.changes.length)));
        return box;
    }

    async function restore(h) {
        if (!confirmDiscard()) return;
        const page = fileSel.value;
        try {
            const diff = await getJson({ ajax: 'histdiff', page, lang: h.lang, id: h.id });
            if (!diff.ok) { showHistoryError(diff.error); return; }
            if (!diff.total) {
                if (window.Swal) Swal.fire({ icon: 'info', text: t('ed_hist_same', 'The file already matches this version.') });
                else window.alert(t('ed_hist_same', 'The file already matches this version.'));
                return;
            }
            const title = t('ed_hist_confirm', 'Return {1} to the state before this edit? Later edits of this file are undone too.', page + ' [' + h.lang + ']');
            let ok;
            if (window.Swal) {
                const box = el('div');
                box.append(el('p', '', title), diffNode(diff));
                const r = await Swal.fire({ icon: 'warning', html: box, width: 640, showCancelButton: true,
                    confirmButtonText: t('ed_hist_restore', 'Roll back'), cancelButtonText: t('ed_cancel', 'Cancel') });
                ok = r.isConfirmed;
            } else {
                ok = window.confirm(title);
            }
            if (!ok) return;
            const body = new URLSearchParams({ my_post_key: csrf, page, lang: h.lang, id: h.id });
            const res = await fetch(url + '&ajax=restore', { method: 'POST', credentials: 'same-origin', body });
            const data = await res.json();
            if (!data.ok) { showHistoryError(data.error); return; }
            rawLoaded = false;
            reload('');
            loadHistory();
            if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: t('ed_hist_done', 'Rolled back'), timer: 1800, showConfirmButton: false });
        } catch (e) {
            showHistoryError(t('ed_save_failed', 'Could not save.'));
        }
    }
    function showHistoryError(text) {
        if (window.Swal) Swal.fire({ icon: 'error', text: text || t('ed_save_failed', 'Could not save.') });
        else window.alert(text || t('ed_save_failed', 'Could not save.'));
    }

    historyBtn.addEventListener('click', () => {
        historyEl.hidden = !historyEl.hidden;
        historyBtn.classList.toggle('active', !historyEl.hidden);
        historyBtn.setAttribute('aria-expanded', String(!historyEl.hidden));
        if (!historyEl.hidden) historyEl.replaceChildren(el('div', 'lce-note', t('ed_loading', 'Loading…')));
        loadHistory();
    });
    fileSel.addEventListener('change', loadHistory);

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
                view = 'table';
                applyView();
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

    const wantedLang = startQs.get('rawlang') || app.dataset.uiLang;
    if (langs.includes(wantedLang)) rawLangEl.value = wantedLang;
    lastRawLang = rawLangEl.value;
    applyView();
    if (fileSel.value) reload(app.dataset.focusKey || '');
    else notice('is-bad', t('ed_load_failed', 'Could not load the file.'));
})();
