/* ═══════════════════════════════════════════════════════════════════════════
   SQL Query Editor — admin/scripts/execute_sql_query.js
   Подтверждения через SweetAlert2 с fallback на confirm().
   ═══════════════════════════════════════════════════════════════════════════ */
(() => {
    'use strict';

    const form      = document.getElementById('qeForm');
    const ta        = document.getElementById('query');
    const confirmFd = document.getElementById('confirmDestructive');
    const runBtn    = form?.querySelector('.qe-btn-run');
    if (!form || !ta || !confirmFd) return;

    // Тот же паттерн, что DESTRUCTIVE_QUERY_PATTERN на сервере: пропускает
    // пробелы и комментарии (/* */, --, #) перед ключевым словом.
    // Раньше тут был /^\s*(DROP|...)/ — запрос вида "/* x */ DROP ..." сервер
    // считал деструктивным, а клиент нет, и такой запрос нельзя было выполнить вообще.
    const DESTRUCTIVE = /^(?:\s+|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\/\*[\s\S]*?\*\/)*(DROP|DELETE|TRUNCATE|UPDATE|ALTER)\b/i;

    // ── Lang ────────────────────────────────────────────────────────────────
    // AGS_LANG выводится PHP перед подключением скрипта (ключи js_* без префикса).
    // {1} и %1$s — оба формата, т.к. $lang->load() превращает {1} в %1$s.
    const LANG = (typeof AGS_LANG !== 'undefined' && AGS_LANG && typeof AGS_LANG === 'object') ? AGS_LANG : {};

    function t(key, fallback, ...args) {
        let s = (typeof LANG[key] === 'string' && LANG[key] !== '') ? LANG[key] : fallback;
        args.forEach((arg, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(arg)).split('%' + n + '$s').join(String(arg));
        });
        return s;
    }

    // SweetAlert2 вставляет confirmButtonText/cancelButtonText как HTML — экранируем
    const esc = str => String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);

    // Иконка + текст через DOM (без innerHTML для переводов)
    function setIconLabel(el, iconClass, text) {
        const i = document.createElement('i');
        i.className = iconClass;
        el.replaceChildren(i, document.createTextNode(' ' + text));
    }

    const hasSwal = () => !!(window.Swal && typeof window.Swal.fire === 'function');

    /**
     * Диалог подтверждения. Возвращает Promise<boolean>.
     */
    function askConfirm({ title, text, confirmText, danger = true, fallback }) {
        if (hasSwal()) {
            return window.Swal.fire({
                titleText: title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: esc(confirmText),
                cancelButtonText: esc(t('cancel', 'Cancel')),
                confirmButtonColor: danger ? '#dc3545' : undefined,
                focusCancel: true,
                reverseButtons: true,
            }).then(r => r.isConfirmed === true);
        }
        return Promise.resolve(window.confirm(fallback ?? (title + '\n\n' + text)));
    }

    // Нативный submit — не вызывает событие submit повторно
    const nativeSubmit = f => HTMLFormElement.prototype.submit.call(f);

    /**
     * Замена текста с сохранением истории Ctrl+Z, где браузер это умеет.
     */
    function setQuery(value, { focus = true } = {}) {
        ta.focus();
        ta.select();
        let ok = false;
        try { ok = document.execCommand('insertText', false, value); } catch (_) { ok = false; }
        if (!ok || ta.value !== value) ta.value = value;
        ta.setSelectionRange(ta.value.length, ta.value.length);
        if (!focus) ta.blur();
    }

    // ── Format ──────────────────────────────────────────────────────────────
    // Верхний регистр ключевых слов + перенос строки перед основными клаузами.
    // Строки, `идентификаторы` и комментарии не трогаются — раньше Format мог
    // превратить WHERE name = 'in' в WHERE name = 'IN'.
    const KEYWORDS = [
        'SELECT', 'DISTINCT', 'FROM', 'WHERE', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN',
        'CROSS JOIN', 'JOIN', 'ON', 'USING', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT',
        'OFFSET', 'UNION ALL', 'UNION', 'INSERT INTO', 'INTO', 'VALUES', 'UPDATE', 'SET',
        'DELETE', 'CREATE', 'DROP', 'ALTER', 'TRUNCATE', 'TABLE', 'SHOW', 'TABLES',
        'STATUS', 'PROCESSLIST', 'DESCRIBE', 'EXPLAIN', 'AND', 'OR', 'NOT', 'IN',
        'EXISTS', 'BETWEEN', 'LIKE', 'IS NOT NULL', 'IS NULL', 'NULL', 'AS', 'ASC',
        'DESC', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
    ];
    const KW_RE = new RegExp(
        '\\b(' + KEYWORDS.map(k => k.replace(/ /g, '\\s+')).join('|') + ')\\b', 'gi'
    );
    const CLAUSE_RE = /\s+(FROM|WHERE|GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT|UNION(?:\s+ALL)?|(?:LEFT\s+|RIGHT\s+|INNER\s+|CROSS\s+)?JOIN)\b/gi;
    // Строки, `backticks` и комментарии — захватывающая группа, чтобы split их сохранил
    const PROTECTED_RE = /('(?:[^'\\]|\\.|'')*'|"(?:[^"\\]|\\.|"")*"|`[^`]*`|--[^\n]*|#[^\n]*|\/\*[\s\S]*?\*\/)/;

    function formatSql(sql) {
        return sql
            .split(PROTECTED_RE)
            .map((part, i) => {
                if (i % 2 === 1) return part; // защищённый фрагмент
                return part
                    .replace(KW_RE, m => m.toUpperCase().replace(/\s+/g, ' '))
                    .replace(CLAUSE_RE, (m, kw) => '\n' + kw);
            })
            .join('')
            .trim();
    }

    document.getElementById('qeFormat')?.addEventListener('click', () => {
        if (ta.value.trim() === '') return;
        setQuery(formatSql(ta.value));
    });

    // ── Clear editor ────────────────────────────────────────────────────────
    document.getElementById('qeClear')?.addEventListener('click', () => {
        setQuery('');
    });

    // ── Examples ────────────────────────────────────────────────────────────
    document.querySelectorAll('.qe-ex-btn').forEach(btn => {
        btn.addEventListener('click', () => setQuery(btn.dataset.q ?? ''));
    });

    // ── History items (клик и Enter/Space с клавиатуры) ─────────────────────
    const histModalEl = document.getElementById('histModal');
    document.querySelectorAll('.qe-history-item').forEach(item => {
        item.setAttribute('tabindex', '0');
        item.setAttribute('role', 'button');

        const load = () => {
            setQuery(item.dataset.q ?? '');
            if (window.bootstrap && histModalEl) {
                window.bootstrap.Modal.getInstance(histModalEl)?.hide();
            }
            // Фокус после закрытия модалки, иначе Bootstrap вернёт его на кнопку History
            histModalEl?.addEventListener('hidden.bs.modal', () => ta.focus(), { once: true });
        };

        item.addEventListener('click', load);
        item.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                load();
            }
        });
    });

    // ── Clear history ───────────────────────────────────────────────────────
    const clearHistForm = document.getElementById('qeClearHistoryForm');
    clearHistForm?.addEventListener('submit', e => {
        e.preventDefault();
        askConfirm({
            title: t('clear_hist_title', 'Clear query history?'),
            text: t('clear_hist_text', 'All saved queries from this session will be removed.'),
            confirmText: t('clear_hist_confirm', 'Clear history'),
            fallback: t('clear_hist_title', 'Clear query history?'),
        }).then(ok => { if (ok) nativeSubmit(clearHistForm); });
    });

    // ── Execute (с подтверждением деструктивных запросов) ───────────────────
    // Серверная сторона тоже проверяет confirm_destructive — JS можно отключить или обойти.
    let submitting = false;
    // Исходная разметка кнопки (уже переведена сервером) — для восстановления
    const runBtnNodes = runBtn ? [...runBtn.childNodes].map(n => n.cloneNode(true)) : [];

    function setBusy(busy) {
        if (!runBtn) return;
        runBtn.disabled = busy;
        if (busy) {
            setIconLabel(runBtn, 'fa-solid fa-spinner fa-spin', t('running', 'Running…'));
        } else {
            runBtn.replaceChildren(...runBtnNodes.map(n => n.cloneNode(true)));
        }
    }

    function doSubmit() {
        submitting = true;
        setBusy(true);
        nativeSubmit(form);
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (submitting) return;

        const sql = ta.value.trim();
        const m   = sql.match(DESTRUCTIVE);

        if (!m) {
            confirmFd.value = '0';
            doSubmit();
            return;
        }

        const kw = m[1].toUpperCase();
        askConfirm({
            title: t('run_title', 'Run {1} query?', kw),
            text: t('run_text', 'This query changes or deletes data and runs immediately. There is no undo.'),
            confirmText: t('run_confirm', 'Run {1}', kw),
            fallback:
                t('run_fallback_head', 'This looks like a destructive query ({1}).', kw) + '\n\n' +
                t('run_fallback_body', 'It will run immediately with no undo. Continue?'),
        }).then(ok => {
            if (!ok) {
                ta.focus();
                return;
            }
            confirmFd.value = '1';
            doSubmit();
        });
    });

    // Кнопка «Назад» из bfcache не должна оставлять кнопку в состоянии «Running…»
    window.addEventListener('pageshow', ev => {
        if (ev.persisted) {
            submitting = false;
            setBusy(false);
        }
    });

    // ── Ctrl/Cmd+Enter ──────────────────────────────────────────────────────
    // requestSubmit(), а не submit(): submit() не вызывает событие submit и
    // пропускал подтверждение деструктивных запросов.
    if (runBtn) runBtn.title = 'Ctrl+Enter';
    ta.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(runBtn ?? undefined);
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        }
    });

    // ── Scroll to results ───────────────────────────────────────────────────
    const result = document.querySelector('.qe-result');
    if (result) {
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        result.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
    }

    // ── Copy result as CSV ──────────────────────────────────────────────────
    const copyBtn = document.getElementById('qeCopyCsv');

    function copyText(text) {
        if (navigator.clipboard?.writeText && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback для не-HTTPS / старых браузеров
        return new Promise((resolve, reject) => {
            const tmp = document.createElement('textarea');
            tmp.value = text;
            tmp.setAttribute('readonly', '');
            tmp.style.cssText = 'position:fixed;top:-1000px;opacity:0';
            document.body.appendChild(tmp);
            tmp.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (err) {
                reject(err);
            } finally {
                tmp.remove();
            }
        });
    }

    function flashButton(btn, iconClass, text, ms = 1600) {
        const original = [...btn.childNodes].map(n => n.cloneNode(true));
        setIconLabel(btn, iconClass, text);
        btn.disabled = true;
        setTimeout(() => {
            btn.replaceChildren(...original);
            btn.disabled = false;
        }, ms);
    }

    copyBtn?.addEventListener('click', () => {
        const table = document.getElementById('qeResultTable');
        if (!table) return;

        const csvCell = cell => {
            // NULL в CSV — пустое поле без кавычек, а не строка "NULL"
            if (cell.querySelector('.qe-null')) return '';
            return '"' + cell.textContent.replace(/"/g, '""') + '"';
        };

        const csv = [...table.querySelectorAll('tr')]
            .map(tr => [...tr.children].slice(1).map(csvCell).join(','))
            .join('\r\n');

        copyText(csv)
            .then(() => flashButton(copyBtn, 'fa-solid fa-check', t('copied', 'Copied')))
            .catch(() => flashButton(copyBtn, 'fa-solid fa-triangle-exclamation', t('copy_failed', 'Copy failed')));
    });
})();