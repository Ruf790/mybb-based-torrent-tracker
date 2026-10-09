/* Cache Manager (admin/cache.php) — список кэшей и просмотр содержимого */
(function () {
    'use strict';

    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

    /** Перевод с английским fallback; {1} и %1$s → аргументы */
    function t(key, fallback, ...args) {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((v, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(v)).split('%' + n + '$s').join(String(v));
        });
        return s;
    }

    /** Иконка + текст кнопки без innerHTML */
    function setLabel(btn, iconClass, text) {
        const i = document.createElement('i');
        i.className = iconClass;
        btn.replaceChildren(i, document.createTextNode(text));
    }

    // ── Просмотр кэша ──
    function initView() {
        const pre = document.getElementById('ccPre');
        if (!pre) return;
        const original = pre.textContent;
        const find = document.getElementById('ccFind'), hits = document.getElementById('ccHits');
        const esc = s => s.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
        let timer;
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const q = find.value;
                if (!q) { pre.textContent = original; hits.textContent = ''; return; }
                const parts = original.split(q);
                pre.innerHTML = parts.map(esc).join('<mark>' + esc(q) + '</mark>');
                hits.textContent = t('hits', '{1} match(es)', parts.length - 1);
                pre.querySelector('mark')?.scrollIntoView({ block: 'center' });
            }, 200);
        });
        document.getElementById('ccWrap').addEventListener('click', function () {
            pre.classList.toggle('is-wrap'); this.classList.toggle('active');
        });
        document.getElementById('ccCopy').addEventListener('click', function () {
            navigator.clipboard?.writeText(original).then(() => {
                setLabel(this, 'fa-solid fa-check me-1', t('copied', 'Copied'));
                setTimeout(() => { setLabel(this, 'fa-regular fa-copy me-1', t('copy', 'Copy')); }, 1500);
            });
        });
    }

    // ── Фильтр списка ──
    function initList() {
        const f = document.getElementById('ccFilter');
        if (!f) return;
        f.addEventListener('input', function () {
            const q = f.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('.cc .cc-table tbody tr[data-search]').forEach(tr => {
                const ok = !q || tr.dataset.search.includes(q);
                tr.hidden = !ok; if (ok) shown++;
            });
            document.getElementById('ccNoMatch').hidden = shown !== 0;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initView();
        initList();
    });
})();
