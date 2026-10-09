/* view_error_logs.php — Error Log Viewer (staff panel) */
(() => {
    'use strict';

    // Данные страницы приходят из PHP через <script type="application/json" id="lelData">
    const LEL = (() => {
        const fallback = { selected: '', files: [], totalSize: '0 B' };
        const node = document.getElementById('lelData');
        if (!node) return fallback;
        try { return Object.assign(fallback, JSON.parse(node.textContent || '{}')); } catch (e) { return fallback; }
    })();

    // ── i18n: AGS_LANG выводит PHP (js_* ключи ланга без префикса) ──
    const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    /** Перевод с английским fallback; {1}… и %1$s (после $lang->load()) → args */
    function t(key, fallback, ...args) {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** Небольшой DOM-хелпер: тексты только через textContent */
    function el(tag, attrs = {}, ...children) {
        const node = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'style') node.style.cssText = v; else node.setAttribute(k, v);
        }
        for (const c of children) {
            node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        }
        return node;
    }

    // ── Log lines ───────────────────────────────────────────
    const searchInput = document.getElementById('searchInput');
    const searchField = document.getElementById('searchField');
    const logOutput   = document.getElementById('logOutput');
    const noResults   = document.getElementById('noResults');
    const counter     = document.getElementById('foundCounter');
    const lines = logOutput ? Array.from(logOutput.querySelectorAll('.log-line')) : [];

    // Исходный текст храним отдельно: в innerHTML он попадает только экранированным
    lines.forEach((el) => {
        el._content = el.querySelector('.log-content');
        el._text = el._content.textContent;
        el._hl = false;
    });

    let activeLevel = 'all';

    function buildRegex(q, flags) {
        try {
            return { re: new RegExp(q, flags), ok: true };
        } catch (e) {
            return { re: new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), flags), ok: false };
        }
    }

    function highlight(text, re) {
        let out = '', last = 0;
        for (const m of text.matchAll(re)) {
            if (m[0] === '') continue;
            out += esc(text.slice(last, m.index)) + '<mark>' + esc(m[0]) + '</mark>';
            last = m.index + m[0].length;
        }
        return out + esc(text.slice(last));
    }

    function apply() {
        if (!lines.length) return;
        const q = searchInput ? searchInput.value.trim() : '';
        const test = q ? buildRegex(q, 'i') : null;
        const glob = q ? buildRegex(q, 'gi').re : null;

        searchField?.classList.toggle('has-value', q !== '');
        searchField?.classList.toggle('is-invalid', !!test && !test.ok);
        if (searchField) {
            searchField.title = test && !test.ok
                ? t('invalid_regex', 'Invalid regex — searching as plain text')
                : '';
        }

        let shown = 0;
        for (const el of lines) {
            const show = (activeLevel === 'all' || el.dataset.level === activeLevel)
                && (!test || test.re.test(el._text));
            el.hidden = !show;
            if (show) shown++;

            if (show && glob) {
                el._content.innerHTML = highlight(el._text, glob);
                el._hl = true;
            } else if (el._hl) {
                el._content.textContent = el._text;
                el._hl = false;
            }
        }

        noResults?.classList.toggle('show', shown === 0);
        if (counter) {
            const filtered = q !== '' || activeLevel !== 'all';
            counter.textContent = filtered
                ? t('found', '{1} of {2} lines', shown.toLocaleString(), lines.length.toLocaleString())
                : '';
        }
    }

    let tmr = null;
    const applyDebounced = () => { clearTimeout(tmr); tmr = setTimeout(apply, lines.length > 5000 ? 220 : 90); };

    function clearSearch() {
        if (!searchInput) return;
        searchInput.value = '';
        apply();
        searchInput.focus();
    }
    window.clearSearch = clearSearch;

    document.querySelector('#searchField .lel-x')?.addEventListener('click', clearSearch);

    // Выбор файла — сразу открываем
    document.getElementById('log')?.addEventListener('change', (e) => e.target.form?.submit());

    searchInput?.addEventListener('input', applyDebounced);
    searchInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(tmr); apply(); }
        if (e.key === 'Escape') { e.preventDefault(); clearSearch(); }
    });

    // "/" — фокус на поиск (если не печатаем в другом поле)
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || !searchInput) return;
        const tag = (document.activeElement?.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement?.isContentEditable) return;
        e.preventDefault();
        searchInput.focus();
    });

    // Level chips
    document.querySelectorAll('.lel-chip[data-level]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const lvl = chip.dataset.level;
            activeLevel = (activeLevel === lvl && lvl !== 'all') ? 'all' : lvl;
            document.querySelectorAll('.lel-chip[data-level]').forEach((c) => {
                c.classList.toggle('active', c.dataset.level === activeLevel);
                c.setAttribute('aria-pressed', c.dataset.level === activeLevel ? 'true' : 'false');
            });
            apply();
        });
    });

    // Copy line (event delegation)
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy') ? resolve() : reject(); } catch (err) { reject(err); }
            document.body.removeChild(ta);
        });
    }

    function copyLogLine(btn) {
        const line = btn.closest('.log-line');
        if (!line) return;
        copyText(line._text ?? '').then(() => {
            const icon = btn.querySelector('i');
            icon.className = 'fa-solid fa-check';
            btn.classList.add('copied');
            setTimeout(() => { icon.className = 'fa-regular fa-copy'; btn.classList.remove('copied'); }, 1200);
        }).catch(() => {});
    }
    window.copyLogLine = copyLogLine;

    logOutput?.addEventListener('click', (e) => {
        const btn = e.target.closest('.copy-line-btn');
        if (btn) copyLogLine(btn);
    });

    // Jump top / bottom
    document.getElementById('jumpTop')?.addEventListener('click', () => { if (logOutput) logOutput.scrollTop = 0; });
    document.getElementById('jumpBottom')?.addEventListener('click', () => { if (logOutput) logOutput.scrollTop = logOutput.scrollHeight; });

    // Свежие записи в логе — внизу, поэтому сразу показываем конец файла
    if (logOutput) logOutput.scrollTop = logOutput.scrollHeight;

    // ── Delete confirmations: SweetAlert2 → confirm() fallback ──
    // opts.html — DOM-узел (Swal принимает HTMLElement), переводы внутри только текстом
    function confirmAction(opts, onOk) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            const danger = getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545';
            window.Swal.fire({
                title: opts.title,
                html: opts.html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: opts.confirm,
                cancelButtonText: t('cancel', 'Cancel'),
                confirmButtonColor: danger,
                reverseButtons: true,
                focusCancel: true
            }).then((r) => { if (r.isConfirmed) onOk(); });
        } else if (window.confirm(opts.plain)) {
            onOk();
        }
    }

    document.querySelectorAll('[data-confirm]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.dataset.confirm === 'one') {
                const form = document.getElementById('lelDeleteOne');
                if (!form) return;
                const html = el('div', {},
                    el('code', {}, LEL.selected),
                    el('br'),
                    el('small', {}, t('del_one_note', "This can't be undone."))
                );
                confirmAction({
                    title: t('del_one_title', 'Delete this log?'),
                    html,
                    plain: t('del_one_plain', "Delete {1}? This can't be undone.", LEL.selected),
                    confirm: t('del_one_confirm', 'Delete file')
                }, () => form.submit());
            } else {
                const form = document.getElementById('lelDeleteAll');
                if (!form) return;
                const n = LEL.files.length;
                const list = el('ul', { style: 'margin:0' }, ...LEL.files.map((f) => el('li', {}, el('code', {}, f))));
                const html = el('div', {},
                    el('div', { style: 'max-height:200px;overflow:auto;text-align:left' }, list),
                    el('small', {}, t('del_all_note', '{1} of log history will be removed permanently.', LEL.totalSize))
                );
                confirmAction({
                    title: t('del_all_title', 'Delete all {1} log files?', n),
                    html,
                    plain: t('del_all_plain', "Delete all {1} log files? This can't be undone.", n),
                    confirm: t('del_all_confirm', 'Delete all')
                }, () => form.submit());
            }
        });
    });

    // ── Toast ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const toastEl = document.querySelector('.toast');
        if (toastEl && window.bootstrap?.Toast) {
            new window.bootstrap.Toast(toastEl, { delay: 5000 }).show();
        }
        // Убираем status/msg из адреса, чтобы тост не всплывал при F5
        const url = new URL(window.location.href);
        if (url.searchParams.has('status') || url.searchParams.has('msg')) {
            url.searchParams.delete('status');
            url.searchParams.delete('msg');
            history.replaceState(null, '', url.toString());
        }
    });
})();
