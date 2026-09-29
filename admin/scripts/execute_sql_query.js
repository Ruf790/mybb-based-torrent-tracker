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

    const hasSwal = () => !!(window.Swal && typeof window.Swal.fire === 'function');

    /**
     * Диалог подтверждения. Возвращает Promise<boolean>.
     */
    function askConfirm({ title, text, confirmText, danger = true, fallback }) {
        if (hasSwal()) {
            return window.Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Cancel',
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
            title: 'Clear query history?',
            text: 'All saved queries from this session will be removed.',
            confirmText: 'Clear history',
            fallback: 'Clear query history?',
        }).then(ok => { if (ok) nativeSubmit(clearHistForm); });
    });

    // ── Execute (с подтверждением деструктивных запросов) ───────────────────
    // Серверная сторона тоже проверяет confirm_destructive — JS можно отключить или обойти.
    let submitting = false;

    function setBusy(busy) {
        if (!runBtn) return;
        runBtn.disabled = busy;
        runBtn.innerHTML = busy
            ? '<i class="fa-solid fa-spinner fa-spin"></i> Running…'
            : '<i class="fa-solid fa-play"></i> Execute';
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
            title: `Run ${kw} query?`,
            text: 'This query changes or deletes data and runs immediately. There is no undo.',
            confirmText: `Run ${kw}`,
            fallback:
                `This looks like a destructive query (${kw}).\n\n` +
                'It will run immediately with no undo. Continue?',
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

    function flashButton(btn, html, ms = 1600) {
        const original = btn.innerHTML;
        btn.innerHTML = html;
        btn.disabled = true;
        setTimeout(() => {
            btn.innerHTML = original;
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
            .then(() => flashButton(copyBtn, '<i class="fa-solid fa-check"></i> Copied'))
            .catch(() => flashButton(copyBtn, '<i class="fa-solid fa-triangle-exclamation"></i> Copy failed'));
    });
})();