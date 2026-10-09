/* admin/scripts/cronjobs.js — Cron Jobs page
 * Needs (inline, before this file): const thisScript, const AGS_LANG
 */
(function () {
'use strict';

// ── i18n ─────────────────────────────────────────────────────────────
const L_ = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

/** Translation with English fallback; {1}/%1$s → args */
function t(key, fallback, ...args) {
    let s = (typeof L_[key] === 'string') ? L_[key] : fallback;
    args.forEach((a, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
    });
    return s;
}

/** Append str to el as text, replacing {n}/%n$s with DOM nodes (no innerHTML) */
function appendFmt(el, str, nodes) {
    str.split(/(\{\d+\}|%\d+\$s)/).forEach(part => {
        const m = part.match(/^\{(\d+)\}$|^%(\d+)\$s$/);
        if (m) {
            const node = nodes[parseInt(m[1] || m[2], 10) - 1];
            if (node) el.appendChild(node);
        } else if (part) {
            el.appendChild(document.createTextNode(part));
        }
    });
}

function iconEl(cls) {
    const i = document.createElement('i');
    i.className = cls;
    return i;
}

let pluralRules = null;
try { pluralRules = new Intl.PluralRules(t('locale', 'en')); } catch (e) { pluralRules = new Intl.PluralRules('en'); }

const UNIT_WORDS = {
    months:  [['unit_months_one', 'month'],   ['unit_months_few', 'months'],   ['unit_months_many', 'months']],
    weeks:   [['unit_weeks_one', 'week'],     ['unit_weeks_few', 'weeks'],     ['unit_weeks_many', 'weeks']],
    days:    [['unit_days_one', 'day'],       ['unit_days_few', 'days'],       ['unit_days_many', 'days']],
    hours:   [['unit_hours_one', 'hour'],     ['unit_hours_few', 'hours'],     ['unit_hours_many', 'hours']],
    minutes: [['unit_minutes_one', 'minute'], ['unit_minutes_few', 'minutes'], ['unit_minutes_many', 'minutes']],
};

function unitWord(u, v) {
    const cat = pluralRules.select(v);              // one | few | many | other
    const [key, fb] = UNIT_WORDS[u][cat === 'one' ? 0 : (cat === 'few' ? 1 : 2)];
    return t(key, fb);
}

// ── Page ─────────────────────────────────────────────────────────────
const cronModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cronModal'));
const UNITS = ['months', 'weeks', 'days', 'hours', 'minutes'];
const SECS  = { months: 2678400, weeks: 604800, days: 86400, hours: 3600, minutes: 60 };

function intervalSeconds() {
    return UNITS.reduce((s, u) => s + (parseInt(document.getElementById('modal_' + u).value, 10) || 0) * SECS[u], 0);
}
function updateSum() {
    const s = intervalSeconds(), el = document.getElementById('cjSum');
    el.replaceChildren();
    if (s < 60) {
        const span = document.createElement('span');
        span.className = 'text-danger';
        span.appendChild(iconEl('fa-solid fa-circle-xmark me-1'));
        span.appendChild(document.createTextNode(t('sum_min_interval', 'Choose an interval of at least 1 minute')));
        el.appendChild(span);
        return;
    }
    const parts = UNITS.map(u => [u, parseInt(document.getElementById('modal_' + u).value, 10) || 0]).filter(([, v]) => v)
        .map(([u, v]) => v + ' ' + unitWord(u, v));
    const strong = document.createElement('strong');
    strong.textContent = parts.join(' ');
    el.appendChild(iconEl('fa-solid fa-rotate me-1 text-body-secondary'));
    appendFmt(el, t('sum_runs_every', 'Runs every {1}'), [strong]);
}
UNITS.forEach(u => document.getElementById('modal_' + u).addEventListener('change', updateSum));
document.querySelectorAll('.cj-preset').forEach(b => b.addEventListener('click', () => {
    b.dataset.v.split(',').forEach((v, i) => { document.getElementById('modal_' + UNITS[i]).value = v; });
    updateSum();
}));

function setModalMode(edit) {
    document.getElementById('cronModalLabel').textContent = edit ? t('modal_title_edit', 'Edit cron job') : t('modal_title_new', 'New cron job');
    document.getElementById('modalIcon').className = 'fa-solid ' + (edit ? 'fa-pen' : 'fa-plus');
    document.getElementById('modalSaveBtnText').textContent = edit ? t('btn_save', 'Save changes') : t('btn_create', 'Create job');
}

function openCreateModal() {
    const f = document.getElementById('cronForm');
    f.reset();
    // Раньше после неудачной загрузки задачи форма оставалась скрытой и при «Create»
    f.classList.remove('d-none');
    document.getElementById('modalLoader').classList.add('d-none');
    document.getElementById('formCronId').value = '999';
    f.action = thisScript + '&act2=save_new&cronid=999';
    document.getElementById('modalActive').checked = true;   // новая задача по умолчанию включена
    document.getElementById('modalLoglevel').checked = true;
    document.getElementById('modal_hours').value = '1';
    setModalMode(false);
    updateSum();
    cronModal.show();
}

function openEditModal(cronid) {
    setModalMode(true);
    document.getElementById('modalLoader').classList.remove('d-none');
    document.getElementById('cronForm').classList.add('d-none');
    cronModal.show();

    fetch(thisScript + '&act2=get_cron_data&cronid=' + cronid, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error('not found');
            document.getElementById('formCronId').value       = data.cronid;
            document.getElementById('modalFilename').value    = data.filename;
            document.getElementById('modalDescription').value = data.description;
            document.getElementById('modalActive').checked    = data.active === 1;
            document.getElementById('modalLoglevel').checked  = data.loglevel === 1;
            UNITS.forEach(u => { document.getElementById('modal_' + u).value = String(data.tarray[u] ?? 0); });
            document.getElementById('cronForm').action = thisScript + '&act2=save&cronid=' + data.cronid;
            document.getElementById('modalLoader').classList.add('d-none');
            document.getElementById('cronForm').classList.remove('d-none');
            updateSum();
        })
        .catch(() => {
            cronModal.hide();
            const msg = t('load_failed', 'Could not load the cron job');
            (typeof showToast === 'function') ? showToast(msg, 'error') : alert(msg);
        });
}

function submitCronForm() {
    const form = document.getElementById('cronForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (intervalSeconds() < 60) { updateSum(); return; }
    const b = document.getElementById('modalSaveBtn');
    b.disabled = true;
    const sp = document.createElement('span');
    sp.className = 'spinner-border spinner-border-sm me-1';
    b.replaceChildren(sp, document.createTextNode(t('btn_saving', 'Saving…')));
    form.submit();
}

// Удаление — подтверждение с именем файла (раньше имя подставлялось прямо в JS-строку onsubmit)
document.querySelectorAll('.cj-del').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(t('confirm_delete', 'Delete cron job {1}?', f.dataset.file))) e.preventDefault();
}));

// Фильтр журнала
document.getElementById('cjLogFilter')?.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('#cjLog tbody tr').forEach(tr => { tr.hidden = q && !tr.dataset.f.includes(q); });
});

// onclick="…" в разметке
window.openCreateModal = openCreateModal;
window.openEditModal   = openEditModal;
window.submitCronForm  = submitCronForm;
})();
