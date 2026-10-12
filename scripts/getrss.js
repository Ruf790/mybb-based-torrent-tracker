/* ArtCore Gangsta — getrss.php (RSS link generator)
 * Needs AGS_LANG (printed by getrss.php before this script). All translated
 * strings are inserted as text (textContent / createTextNode). */

'use strict';

function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string')
        ? AGS_LANG[key]
        : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

// ── Category multi-select ──
const selectedCats = new Set();

function setCatLabel(iconClass, text, strong) {
    ['catLabel', 'grssSummary'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const icon = document.createElement('i');
        icon.className = iconClass;
        let content;
        if (strong) {
            content = document.createElement('strong');
            content.textContent = text;
        } else {
            content = document.createTextNode(text);
        }
        el.replaceChildren(icon, content);
    });
}

function updateCatLabel() {
    const showall = document.getElementById('showall');
    if (showall && showall.checked) {
        setCatLabel('fa-solid fa-infinity text-success me-1', t('cats_all_future', 'All categories, including future ones'), true);
        return;
    }
    const total = document.querySelectorAll('.cat-pick-btn').length;
    const count = selectedCats.size;
    if (count > 0 && count === total) {
        setCatLabel('fa-solid fa-circle-check text-success me-1', t('cats_all', 'All categories selected'), true);
    } else if (count > 0) {
        const text = count === 1
            ? t('cats_selected_one', '{1} category selected', count)
            : t('cats_selected_other', '{1} categories selected', count);
        setCatLabel('fa-solid fa-circle-check text-success me-1', text, true);
    } else {
        setCatLabel('fa-solid fa-hand-pointer me-1', t('cat_hint', 'Click to select / deselect categories'), false);
    }
}

function syncSelected() {
    const input = document.getElementById('categoriesSelected');
    if (input) input.value = [...selectedCats].join(',');
    updateCatLabel();
}

function toggleCategory(btn) {
    const id = btn.dataset.id;
    if (selectedCats.has(id)) {
        selectedCats.delete(id);
        btn.classList.remove('active');
        btn.setAttribute('aria-pressed', 'false');
    } else {
        selectedCats.add(id);
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
    }
    syncSelected();
}

function selectAllCategories() {
    document.querySelectorAll('.cat-pick-btn').forEach(btn => {
        selectedCats.add(btn.dataset.id);
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
    });
    syncSelected();
}

function deselectAllCategories() {
    selectedCats.clear();
    document.querySelectorAll('.cat-pick-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-pressed', 'false');
    });
    syncSelected();
}

// "All categories incl. future" switch: the server ignores the picker when it is on
function handleSelectAll(cb) {
    const on = !!(cb && cb.checked);
    const picker = document.getElementById('catPicker');
    if (picker) picker.classList.toggle('grss-picker--off', on);
    document.querySelectorAll('.grss-quick').forEach(b => { b.disabled = on; });
    updateCatLabel();
}

// ── Copy RSS link ──
function copyRssLink(btnArg) {
    const copyText = document.getElementById('rssLink');
    if (!copyText) return;

    copyText.select();
    copyText.setSelectionRange(0, 99999);

    try {
        const successful = document.execCommand('copy');
        const btn = btnArg || (window.event && window.event.target ? window.event.target.closest('button') : null);
        if (btn) {
            const original = Array.from(btn.childNodes).map(n => n.cloneNode(true));
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-check me-1';
            btn.replaceChildren(icon, document.createTextNode(t('copied', 'Copied!')));
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');

            setTimeout(() => {
                btn.replaceChildren(...original);
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        }
    } catch (err) {
        console.error('Copy failed:', err);
        alert(t('copy_failed', 'Failed to copy link. Please copy it manually.'));
    }
}

// ── Form validation ──
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.needs-validation');

    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Restore state after browser back/forward (checkbox may stay checked)
    const showall = document.getElementById('showall');
    if (showall && showall.checked) handleSelectAll(showall);
});
