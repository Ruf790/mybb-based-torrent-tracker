/* Hit & Run Detection Tool — admin/hit_and_run.php */
(function () {
    'use strict';

    // Строки из ланга (PHP выводит const AGS_LANG перед скриптом)
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    function t(key, fallback, ...args) {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    // Страница «Send Warnings»: клик по плейсхолдеру вставляет его в текст
    function initCompose() {
        const ta = document.getElementById('warnmessage');
        if (!ta) return;
        document.querySelectorAll('.hr .hr-token').forEach(function (t) {
            t.addEventListener('click', function () {
                const s = ta.selectionStart, e = ta.selectionEnd, v = t.dataset.token;
                ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
                ta.focus();
                ta.selectionStart = ta.selectionEnd = s + v.length;
            });
        });
    }

    const boxes    = () => Array.from(document.querySelectorAll('.hr .hr-cb'));
    const selected = () => boxes().filter(cb => cb.checked);

    function refresh() {
        const sel = selected();
        document.getElementById('hrSelCount').textContent = sel.length;
        document.querySelectorAll('.hr .hr-act').forEach(b => { b.disabled = sel.length === 0; });
        boxes().forEach(cb => cb.closest('tr').classList.toggle('is-selected', cb.checked));
        const all = boxes(), master = document.getElementById('hrCheckAll');
        master.checked = all.length > 0 && sel.length === all.length;
        master.indeterminate = sel.length > 0 && sel.length < all.length;
        master.disabled = all.length === 0;
    }

    // Основная страница: выбор строк, счётчик, бан через модалку
    function initList() {
        if (!document.getElementById('hrCheckAll')) return;
        document.getElementById('hrCheckAll').addEventListener('change', function () {
            boxes().forEach(cb => { cb.checked = this.checked; });
            refresh();
        });
        document.addEventListener('change', e => { if (e.target.classList && e.target.classList.contains('hr-cb')) refresh(); });

        // Клик по строке — переключить (кроме ссылок и уже предупреждённых)
        document.querySelector('.hr .hr-table tbody').addEventListener('click', e => {
            if (e.target.closest('a, input, label, button')) return;
            const cb = e.target.closest('tr')?.querySelector('.hr-cb');
            if (cb) { cb.checked = !cb.checked; refresh(); }
        });

        // Бан — только после подтверждения; уникальных пользователей считаем по userid
        document.getElementById('hrBanBtn').addEventListener('click', function () {
            const users = new Set(selected().map(cb => cb.value.split('|')[0]));
            document.getElementById('hrBanCount').textContent = users.size;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('hrBanModal')).show();
        });
        document.getElementById('hrBanConfirm').addEventListener('click', function () {
            const f = document.getElementById('hrBanField');
            f.disabled = false; f.value = '1';
            this.disabled = true;
            const spin = document.createElement('span');
            spin.className = 'spinner-border spinner-border-sm me-1';
            this.replaceChildren(spin, document.createTextNode(t('banning', 'Banning...')));
            document.getElementById('hrForm').submit();
        });

        refresh();
    }

    function init() { initCompose(); initList(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
