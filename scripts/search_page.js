(function () {

// ── i18n: строки приходят из PHP (js_* ключи ланга) как AGS_LANG ────────
const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
function t(key, fallback, ...args) {
    let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}
const TYPE_FALLBACK = { thread: 'thread', post: 'post', user: 'user', forum: 'forum' };

// Ctrl+K
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('srKeywords')?.focus();
    }
});
// Clear button
document.getElementById('srClear')?.addEventListener('click', () => {
    document.getElementById('srKeywords').value = '';
    document.getElementById('srKeywords').focus();
    if (window.hideSuggestions) window.hideSuggestions();
});
// Chevron rotate on collapse
document.getElementById('srAdv')?.addEventListener('show.bs.collapse', () => {
    document.querySelector('.sr-adv-toggle .fa-chevron-down').style.transform = 'rotate(180deg)';
});
document.getElementById('srAdv')?.addEventListener('hide.bs.collapse', () => {
    document.querySelector('.sr-adv-toggle .fa-chevron-down').style.transform = 'rotate(0deg)';
});

// ── Live autocomplete suggestions ───────────────────────────────────────
(function () {
    const input  = document.getElementById('srKeywords');
    const box    = document.getElementById('srSuggestBox');
    if (!input || !box) return;

    let debounceTimer = null;
    let activeIndex   = -1;
    let currentItems  = [];
    let abortCtrl     = null;

    function hideSuggestions() {
        box.style.display = 'none';
        box.innerHTML = '';
        activeIndex  = -1;
        currentItems = [];
    }
    window.hideSuggestions = hideSuggestions;

    function renderSuggestions(items) {
        currentItems = items;
        activeIndex  = -1;

        if (!items.length) {
            hideSuggestions();
            return;
        }

        box.innerHTML = '';
        items.forEach((item, i) => {
            const a = document.createElement('a');
            a.href = item.url;
            a.className = 'sr-suggest-item';
            a.dataset.index = String(i);

            const icon = document.createElement('i');
            icon.className = 'fas ' + item.icon + ' sr-suggest-icon';

            const text = document.createElement('span');
            text.className = 'sr-suggest-text';

            // title/meta - HTML с сервера (уже экранирован, с <mark> подсветкой)
            const subj = document.createElement('span');
            subj.className = 'sr-suggest-subject';
            subj.innerHTML = item.title;

            const meta = document.createElement('span');
            meta.className = 'sr-suggest-meta';
            meta.innerHTML = item.meta;

            text.append(subj, meta);

            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary ms-auto';
            badge.textContent = t('type_' + item.type, TYPE_FALLBACK[item.type] || String(item.type));

            a.append(icon, text, badge);
            box.appendChild(a);
        });

        box.style.display = 'block';
    }

    function fetchSuggestions(q) {
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();

        fetch('search.php?action=suggest&q=' + encodeURIComponent(q), { signal: abortCtrl.signal })
            .then(r => r.json())
            .then(data => renderSuggestions(data.results || []))
            .catch(() => {});
    }

    input.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            hideSuggestions();
            return;
        }

        debounceTimer = setTimeout(() => fetchSuggestions(q), 220);
    });

    input.addEventListener('keydown', function (e) {
        if (box.style.display === 'none') return;
        const links = box.querySelectorAll('.sr-suggest-item');
        if (!links.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, links.length - 1);
            updateActive(links);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            updateActive(links);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            links[activeIndex].click();
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });

    function updateActive(links) {
        links.forEach((l, i) => l.classList.toggle('sr-suggest-active', i === activeIndex));
        if (activeIndex >= 0) links[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== input) hideSuggestions();
    });
})();

// ── Date range pickers (Posted from / Posted to) ────────────────────────
(function () {
    if (!window.flatpickr) return;

    const baseOpts = {
        dateFormat:    'Y-m-d',
        altInput:      true,
        altFormat:     'd F Y',
        allowInput:    true,
        disableMobile: true,
        static:        true
    };
    // Локаль календаря - по языку сайта ('default' = английский)
    const fpLocale = t('fp_locale', 'default');
    if (fpLocale !== 'default' && flatpickr.l10ns && flatpickr.l10ns[fpLocale]) {
        baseOpts.locale = flatpickr.l10ns[fpLocale];
    }

    const fpFrom = flatpickr('#srDateFrom', baseOpts);
    const fpTo   = flatpickr('#srDateTo',   baseOpts);

    if (fpFrom && fpTo) {
        fpFrom.config.onChange.push(sel => fpTo.set('minDate', sel && sel[0] ? sel[0] : null));
        fpTo.config.onChange.push(sel => fpFrom.set('maxDate', sel && sel[0] ? sel[0] : null));
        if (fpFrom.input.value) fpTo.set('minDate', fpFrom.selectedDates[0] || fpFrom.input.value);
        if (fpTo.input.value)   fpFrom.set('maxDate', fpTo.selectedDates[0] || fpTo.input.value);
    }
})();

})();
