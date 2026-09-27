'use strict';

const presets = window.seedbonusPresets || {};

// ── SweetAlert2 с fallback на нативные alert/confirm ────────
const hasSwal = () => typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';

/** Цвета попапа из Bootstrap-переменных — чтобы тёмная тема не давала белое окно */
function swalTheme() {
    const cs = getComputedStyle(document.documentElement);
    const v  = name => cs.getPropertyValue(name).trim();
    return {
        background: v('--bs-body-bg') || undefined,
        color:      v('--bs-body-color') || undefined,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-primary rounded-pill px-4 mx-1',
            cancelButton:  'btn btn-outline-secondary rounded-pill px-4 mx-1',
        },
    };
}

/**
 * Подтверждение. Всегда возвращает Promise<boolean>.
 * opts: { title, text, html, icon, confirmText, confirmIcon, danger }
 */
function sbConfirm(opts) {
    const o = Object.assign({ icon: 'question', confirmText: 'Yes', confirmIcon: 'fa-check' }, opts);
    if (!hasSwal()) {
        return Promise.resolve(window.confirm([o.title, o.text].filter(Boolean).join('\n\n')));
    }
    const theme = swalTheme();
    if (o.danger) theme.customClass.confirmButton = 'btn btn-danger rounded-pill px-4 mx-1';

    return window.Swal.fire(Object.assign(theme, {
        title: o.title,
        text: o.html ? undefined : o.text,
        html: o.html,
        icon: o.icon,
        showCancelButton: true,
        focusCancel: true,
        reverseButtons: true,
        confirmButtonText: '<i class="fa-solid ' + o.confirmIcon + ' me-1"></i>' + o.confirmText,
        cancelButtonText: '<i class="fa-solid fa-xmark me-1"></i>Cancel',
    })).then(r => r.isConfirmed === true);
}

/** Модальное сообщение (вместо alert) */
function sbAlert(msg, icon = 'info', title = '') {
    if (!hasSwal()) { window.alert(msg); return Promise.resolve(); }
    return window.Swal.fire(Object.assign(swalTheme(), {
        title: title || undefined,
        text: String(msg),
        icon: icon,
        confirmButtonText: 'OK',
    }));
}

/** Экранирование для html-опции Swal */
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

// toast.js может не загрузиться — тогда модальный SweetAlert (или alert, если нет и его)
const notify = (msg, type = 'info') =>
    (typeof window.showToast === 'function')
        ? window.showToast(String(msg), type)
        : sbAlert(msg, ({ success: 'success', error: 'error', warning: 'warning' })[type] || 'info');

let dirty = false;

document.addEventListener('DOMContentLoaded', function () {
    initSliders();
    initEventListeners();
    updateSliderValues();
    markActivePreset();
    updateInflation();
});

// ── Ползунки ────────────────────────────────────────────────
function initSliders() {
    document.querySelectorAll('input[type="range"]').forEach(slider => {
        const valueSpan = document.getElementById(slider.id + 'Value');
        if (valueSpan) {
            slider.addEventListener('input', function () { valueSpan.textContent = this.value; });
        }
    });
}

function updateSliderValues() {
    document.querySelectorAll('input[type="range"]').forEach(slider => {
        const valueSpan = document.getElementById(slider.id + 'Value');
        if (valueSpan) valueSpan.textContent = slider.value;
    });
}

// ── События ─────────────────────────────────────────────────
function initEventListeners() {
    document.querySelectorAll('.config-badge').forEach(badge => {
        badge.addEventListener('click', function (e) {
            e.preventDefault();
            loadPreset(this.dataset.preset);
        });
    });

    document.getElementById('saveBtn')?.addEventListener('click', e => { e.preventDefault(); saveSettings(); });
    document.getElementById('resetBtn')?.addEventListener('click', e => { e.preventDefault(); resetSettings(); });
    document.getElementById('copyCodeBtn')?.addEventListener('click', copyCode);
    document.getElementById('downloadCodeBtn')?.addEventListener('click', downloadCode);
    document.getElementById('inflationUsers')?.addEventListener('input', updateInflation);

    // Несохранённые изменения: индикатор + предупреждение при уходе со страницы
    ['basicForm', 'multipliersForm', 'timeForm'].forEach(id => {
        const f = document.getElementById(id);
        if (!f) return;
        f.addEventListener('input', setDirty);
        f.addEventListener('change', setDirty);
        // Enter в числовом поле раньше отправлял форму обычным GET-запросом и перезагружал страницу
        f.addEventListener('submit', e => { e.preventDefault(); saveSettings(); });
    });
    window.addEventListener('beforeunload', e => {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
}

function setDirty() {
    dirty = true;
    document.getElementById('sbDirty')?.removeAttribute('hidden');
    markActivePreset();
}

function setBusy(btn, busy, html) {
    if (!btn) return;
    if (busy) {
        btn.dataset.html = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (html || 'Saving…');
    } else {
        btn.disabled = false;
        if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }
}

// ── Пресеты ─────────────────────────────────────────────────
async function loadPreset(presetName, skipConfirm = false) {
    if (!presets[presetName]) {
        notify(`Preset "${presetName}" not found`, 'error');
        return;
    }
    if (!skipConfirm) {
        const ok = await sbConfirm({
            title: 'Load preset?',
            html: `The <b>${esc(presetName)}</b> preset will overwrite current rates and multipliers.`
                + (dirty ? '<br><span class="text-warning-emphasis">Your unsaved changes will be lost.</span>' : ''),
            text: `Load the "${presetName}" preset? Current rates and multipliers will be overwritten.`,
            icon: 'question',
            confirmText: 'Load preset',
            confirmIcon: 'fa-wand-magic-sparkles',
        });
        if (!ok) return;
    }

    const formData = new FormData();
    formData.append('action', 'load_preset');
    formData.append('preset', presetName);
    formData.append('my_post_key', typeof myPostKey !== 'undefined' ? myPostKey : '');

    post(formData)
        .then(data => {
            notify(data.message, data.success ? 'success' : 'error');
            if (data.success) { dirty = false; setTimeout(() => location.reload(), 900); }
        })
        .catch(err => notify('Error: ' + err.message, 'error'));
}

/** Подсветить пресет, если текущие значения формы с ним совпадают */
function markActivePreset() {
    const val = key => {
        const els = document.querySelectorAll(`[name="seedbonus_${key}"]`);
        if (!els.length) return undefined;
        const radio = [...els].find(e => e.type === 'radio');
        if (radio) return document.querySelector(`[name="seedbonus_${key}"]:checked`)?.value;
        return els[els.length - 1].value;
    };
    document.querySelectorAll('.config-badge').forEach(b => {
        const p = presets[b.dataset.preset];
        const match = p && Object.entries(p).every(([k, v]) => {
            const cur = val(k);
            if (cur === undefined) return true;
            return typeof v === 'number' ? Math.abs(parseFloat(cur) - v) < 1e-9 : String(cur) === String(v);
        });
        b.classList.toggle('is-active', !!match);
        b.setAttribute('aria-pressed', match ? 'true' : 'false');
    });
}

// ── Сохранение ──────────────────────────────────────────────
function saveSettings() {
    const formData = new FormData();
    ['basicForm', 'multipliersForm', 'timeForm'].forEach(id => {
        const form = document.getElementById(id);
        if (form) new FormData(form).forEach((v, k) => formData.append(k, v));
    });
    formData.append('action', 'save');
    formData.append('my_post_key', typeof myPostKey !== 'undefined' ? myPostKey : '');

    const btns = [document.getElementById('saveBtn'), document.getElementById('sbSaveBar')];
    btns.forEach(b => setBusy(b, true));

    post(formData)
        .then(data => {
            notify(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                dirty = false;
                document.getElementById('sbDirty')?.setAttribute('hidden', '');
                // Превью и прогноз считаются на сервере от сохранённых значений — обновим страницу
                setTimeout(() => location.reload(), 900);
            } else {
                btns.forEach(b => setBusy(b, false));
            }
        })
        .catch(err => { notify('Error: ' + err.message, 'error'); btns.forEach(b => setBusy(b, false)); });
}

async function resetSettings() {
    // Подтверждение спрашивается один раз — здесь; loadPreset() вызывается с skipConfirm
    const ok = await sbConfirm({
        title: 'Reset to defaults?',
        html: 'All settings will be replaced with the <b>balanced</b> preset.',
        text: 'Reset all settings to the "balanced" defaults?',
        icon: 'warning',
        confirmText: 'Reset settings',
        confirmIcon: 'fa-rotate-left',
        danger: true,
    });
    if (ok) loadPreset('balanced', true);
}

/** POST с разбором JSON и при ошибочном HTTP-статусе (сервер отвечает 403 с JSON) */
function post(formData) {
    return fetch(window.location.href, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(r => r.json().catch(() => { throw new Error('Unexpected server response (' + r.status + ')'); }));
}

// ── Код конфигурации ────────────────────────────────────────
function copyCode() {
    const el = document.getElementById('generatedCode');
    if (!el) return;
    const btn = document.getElementById('copyCodeBtn');
    navigator.clipboard.writeText(el.textContent)
        .then(() => {
            notify('Code copied to clipboard', 'success');
            if (btn) { const h = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied'; setTimeout(() => { btn.innerHTML = h; }, 1500); }
        })
        .catch(() => notify('Failed to copy code', 'error'));
}

function downloadCode() {
    const el = document.getElementById('generatedCode');
    if (!el) return;
    const url = URL.createObjectURL(new Blob(['<?php\n' + el.textContent + '\n'], { type: 'text/plain' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: 'seedbonus_config.php' });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    notify('File downloaded', 'success');
}

// ── Прогноз инфляции ────────────────────────────────────────
function updateInflation() {
    const users = Math.max(1, parseInt(document.getElementById('inflationUsers')?.value, 10) || 1);
    // Раньше дневной бонус читался из #previewDaily, которого на странице нет —
    // при любом изменении числа пользователей все цифры становились 0.
    // Теперь значение передаётся через data-daily.
    const src   = document.getElementById('inflationAvgBonus');
    const daily = parseFloat(src?.dataset.daily ?? '0') || 0;
    const total   = users * daily;
    const monthly = total * 30;
    const perUser = daily * 30; // раньше делилось на зашитые «500 пользователей»

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = Math.round(v).toLocaleString('en-US'); };
    set('inflationDaily',      total);
    set('inflationMonthly',    monthly);
    set('inflationAvgBalance', perUser);
}