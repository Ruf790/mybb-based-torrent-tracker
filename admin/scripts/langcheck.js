/* Lang Checker (langcheck.php) — кнопка «Скопировать отчёт» */
(function () {
    'use strict';
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (key, fallback, ...args) => (typeof L[key] === 'string' ? L[key] : fallback)
        .replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => { const v = args[(a || b) - 1]; return v === undefined ? m : String(v); });

    const btn = document.getElementById('lcCopy'), label = document.getElementById('lcCopyText'), area = document.getElementById('lcReport');
    if (!btn || !label || !area) return;
    const original = label.textContent;
    let timer;

    function flash(text, ok) {
        clearTimeout(timer);
        label.textContent = text;
        btn.classList.toggle('is-done', ok);
        timer = setTimeout(() => { label.textContent = original; btn.classList.remove('is-done'); }, 2500);
    }
    function fallback() {
        // Буфер недоступен (не HTTPS / запрет браузера): показать отчёт и выделить
        area.hidden = false;
        area.focus();
        area.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        if (ok) { area.hidden = true; flash(t('copied', 'Copied — paste it into the chat'), true); }
        else    { flash(t('copy_failed', 'Copy blocked — the report is shown below, press Ctrl+C'), false); area.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }

    btn.addEventListener('click', () => {
        const text = area.value;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => flash(t('copied', 'Copied — paste it into the chat'), true), fallback);
        } else {
            fallback();
        }
    });
})();
