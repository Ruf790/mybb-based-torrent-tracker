/**
 * edituser.js — admin/edituser.php
 *   вкладки (запоминание, #хэш, кнопки-переходы), панель сохранения,
 *   кнопки-действия (POST через #euActionForm + SweetAlert2/confirm),
 *   аватар (флаг изменения, проверка URL), пасскей, BBCode-превью,
 *   фильтр аудита, дерево приглашений, графики (Chart.js).
 * Тексты: window.AGS_LANG (ключи js_* из edituser.lang.php без префикса).
 */
'use strict';

// ── i18n: {1}, {2}… и %1$s ($lang->load() переводит {N} в %N$s) ─────────────
function t(key, fallback, ...args) {
    const dict = (typeof window.AGS_LANG === 'object' && window.AGS_LANG !== null) ? window.AGS_LANG : {};
    let s = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : (fallback ?? key);
    args.forEach((arg, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(arg)).split('%' + n + '$s').join(String(arg));
    });
    return s;
}

function euConfirm(text, danger) {
    if (typeof window.agsConfirm === 'function') {
        return window.agsConfirm(text, { danger: !!danger });
    }
    return Promise.resolve(window.confirm(text));
}

function euToast(msg, type) {
    if (typeof window.showToast === 'function') {
        window.showToast(msg, type);
    } else if (typeof window.agsAlert === 'function') {
        window.agsAlert(msg, type === 'error' ? 'error' : 'info');
    } else {
        window.alert(msg);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.eu-page');
    if (!page) return;

    // ── Flash-тосты ──────────────────────────────────────────────────────────
    if (window.bootstrap && bootstrap.Toast) {
        document.querySelectorAll('.eu-toast').forEach(el => bootstrap.Toast.getOrCreateInstance(el).show());
    }

    // ── Вкладки ──────────────────────────────────────────────────────────────
    const saveBar = document.getElementById('euSaveBar');
    const tabBtns = document.querySelectorAll('#userTabs [data-bs-toggle="tab"]');
    const STORE   = 'activeUserTab';

    function syncSaveBar(btn) {
        if (saveBar && btn) saveBar.classList.toggle('d-none', btn.dataset.euEditable !== '1');
    }
    function showTab(target) {
        const btn = document.querySelector('#userTabs [data-bs-target="' + target + '"]');
        if (!btn || !window.bootstrap) return false;
        bootstrap.Tab.getOrCreateInstance(btn).show();
        btn.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        return true;
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => {
            const target = e.target.getAttribute('data-bs-target');
            try { sessionStorage.setItem(STORE, target); } catch (err) { /* приватный режим */ }
            syncSaveBar(e.target);
        });
    });

    let initial = (location.hash && /^#[a-z]+$/.test(location.hash)) ? location.hash : null;
    if (!initial) {
        try { initial = sessionStorage.getItem(STORE); } catch (err) { initial = null; }
    }
    if (!(initial && showTab(initial))) {
        syncSaveBar(document.querySelector('#userTabs .eu-tab.active'));
    }

    // Кнопки «перейти к вкладке» (H&R → Предупреждения / Права)
    page.addEventListener('click', e => {
        const go = e.target.closest('[data-eu-goto]');
        if (!go) return;
        e.preventDefault();
        if (showTab(go.dataset.euGoto)) window.scrollTo({ top: page.offsetTop, behavior: 'smooth' });
    });

    // ── Кнопки-действия: отдельная POST-форма, не основная ───────────────────
    const actForm  = document.getElementById('euActionForm');
    const actField = document.getElementById('euActionField');
    page.addEventListener('click', e => {
        const btn = e.target.closest('[data-eu-action]');
        if (!btn || !actForm || !actField) return;
        e.preventDefault();
        euConfirm(btn.dataset.euConfirm || t('confirm', 'Are you sure?'), btn.dataset.euDanger === '1').then(ok => {
            if (!ok) return;
            actField.name = btn.dataset.euAction;
            btn.disabled = true;
            actForm.submit();
        });
    });

    // ── Основная форма ───────────────────────────────────────────────────────
    const form        = document.getElementById('userEditForm');
    const avatarInput = document.getElementById('euAvatarUrl');
    const avatarFlag  = document.getElementById('avatar_changed');
    if (form) {
        // Флаг смены аватара считаем по значению при отправке (автозаполнение браузера
        // может стрельнуть change без намерения сотрудника).
        const originalAvatar = avatarInput ? avatarInput.value : '';
        form.addEventListener('submit', () => {
            if (avatarInput && avatarFlag) {
                avatarFlag.value = (avatarInput.value.trim() !== originalAvatar.trim()) ? '1' : '0';
            }
            const submit = form.querySelector('button[type="submit"]');
            if (submit) setTimeout(() => { submit.disabled = true; }, 0);
        });
    }

    // ── Проверка URL аватара ─────────────────────────────────────────────────
    const testBtn = document.getElementById('euAvatarTest');
    if (testBtn && avatarInput) {
        testBtn.addEventListener('click', () => {
            let url = avatarInput.value.trim();
            if (!url) { euToast(t('avatar_enter_url', 'Please enter an avatar URL first.'), 'warning'); return; }
            // относительный путь — от корня сайта, а не от /admin/
            if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) url = '/' + url;

            const html = testBtn.innerHTML;
            testBtn.disabled = true;
            testBtn.textContent = '';
            const spin = document.createElement('i');
            spin.className = 'fa-solid fa-spinner fa-spin me-1';
            testBtn.append(spin, document.createTextNode(t('avatar_testing', 'Testing...')));

            const img = new Image();
            const done = () => { clearTimeout(timer); testBtn.disabled = false; testBtn.innerHTML = html; };
            const timer = setTimeout(() => {
                img.onload = img.onerror = null;
                done();
                euToast(t('avatar_timeout', 'Avatar test timed out.'), 'error');
            }, 10000);
            img.onload  = () => { done(); euToast(t('avatar_valid', '✓ Avatar valid! Dimensions: {1}×{2}px', img.naturalWidth, img.naturalHeight), 'success'); };
            img.onerror = () => { done(); euToast(t('avatar_invalid', '✗ Avatar URL invalid or image cannot be loaded.'), 'error'); };
            img.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
        });
    }

    // Аватар с клавиатуры (avatar_upload.js слушает только клик)
    const ava = document.getElementById('avatar-container');
    if (ava && ava.dataset.canChange === '1') {
        ava.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ava.click(); }
        });
    }

    // ── Пасскей ──────────────────────────────────────────────────────────────
    const pk       = document.getElementById('passkeyText');
    const pkToggle = document.getElementById('passkeyToggle');
    const pkCopy   = document.getElementById('passkeyCopy');
    if (pk && pkToggle) {
        pkToggle.addEventListener('click', () => {
            const hidden = pk.classList.toggle('is-hidden');
            pkToggle.querySelector('i').className = hidden ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
        });
    }
    if (pkCopy) {
        pkCopy.addEventListener('click', () => {
            const val = pkCopy.dataset.copy || '';
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(val).then(() => {
                const i = pkCopy.querySelector('i');
                i.className = 'fa-solid fa-check';
                setTimeout(() => { i.className = 'fa-solid fa-copy'; }, 1500);
            });
        });
    }

    // ── BBCode: кнопки и живое превью (parseBBCode / wrapBBCodeNear — edit_delete_comment.js) ──
    page.addEventListener('click', e => {
        const b = e.target.closest('[data-bb-open]');
        if (!b || typeof window.wrapBBCodeNear !== 'function') return;
        e.preventDefault();
        window.wrapBBCodeNear(b, b.dataset.bbOpen, b.dataset.bbClose || '');
        const ta = b.closest('.bb-editor')?.querySelector('textarea');
        if (ta) ta.dispatchEvent(new Event('input', { bubbles: true }));
    });
    page.addEventListener('input', e => {
        if (e.target.tagName !== 'TEXTAREA' || typeof window.parseBBCode !== 'function') return;
        let preview = e.target.closest('.bb-editor')?.querySelector('[data-bb-preview]');
        if (!preview && e.target.name === 'signature') preview = page.querySelector('.eu-sig');
        if (preview) preview.innerHTML = window.parseBBCode(e.target.value);
    });

    // ── Аудит: фильтр по категории ───────────────────────────────────────────
    const auditFilter = document.getElementById('auditFilter');
    if (auditFilter) {
        auditFilter.addEventListener('change', () => {
            const v = auditFilter.value;
            document.querySelectorAll('.eu-timeline__item').forEach(li => {
                li.hidden = !(v === 'all' || li.dataset.cat === v);
            });
        });
    }

    // ── Дерево приглашений ───────────────────────────────────────────────────
    page.addEventListener('click', e => {
        const tg = e.target.closest('[data-eu-tree]');
        if (!tg) return;
        const node = tg.closest('.eu-tree__node');
        const collapsed = node.classList.toggle('is-collapsed');
        tg.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });

    // ── Графики: рисуем при первом открытии вкладки «Статистика» ─────────────
    let chartsDone = false;
    function drawCharts() {
        if (chartsDone || typeof window.Chart !== 'function' || !window.EU_CHART) return;
        chartsDone = true;

        const css  = getComputedStyle(document.documentElement);
        const v    = (name, fb) => (css.getPropertyValue(name) || '').trim() || fb;
        const text = v('--bs-secondary-color', '#6c757d');
        const grid = v('--bs-border-color-translucent', 'rgba(0,0,0,.1)');
        const ok   = v('--bs-success', '#198754');
        const bad  = v('--bs-danger', '#dc3545');
        const pri  = v('--bs-primary', '#0d6efd');
        const d    = window.EU_CHART;

        Chart.defaults.color = text;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

        const base = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: grid }, border: { display: false } },
            },
        };

        const tr = document.getElementById('trafficChart');
        if (tr) {
            const gb = t('chart_gb', 'GB');
            new Chart(tr, {
                type: 'bar',
                data: {
                    labels: d.months,
                    datasets: [
                        { label: t('chart_downloaded', 'Downloaded'), data: d.dl, backgroundColor: bad, borderRadius: 6, maxBarThickness: 28 },
                        { label: t('chart_uploaded', 'Uploaded'),     data: d.ul, backgroundColor: ok,  borderRadius: 6, maxBarThickness: 28 },
                    ],
                },
                options: Object.assign({}, base, {
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle' } },
                        tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + c.parsed.y + ' ' + gb } },
                    },
                    scales: Object.assign({}, base.scales, {
                        y: Object.assign({}, base.scales.y, { ticks: { callback: val => val + ' ' + gb } }),
                    }),
                }),
            });
        }

        const ac = document.getElementById('activityChart');
        if (ac) {
            new Chart(ac, {
                type: 'bar',
                data: {
                    labels: d.days.map(s => s.slice(5)),
                    datasets: [{ label: t('chart_snatches', 'Snatches'), data: d.day, backgroundColor: pri, borderRadius: 5, maxBarThickness: 18 }],
                },
                options: Object.assign({}, base, {
                    plugins: { legend: { display: false } },
                    scales: Object.assign({}, base.scales, {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                        y: Object.assign({}, base.scales.y, { ticks: { precision: 0 } }),
                    }),
                }),
            });
        }
    }

    const statsBtn = document.querySelector('#userTabs [data-bs-target="#stats"]');
    if (statsBtn) {
        if (statsBtn.classList.contains('active')) drawCharts();
        statsBtn.addEventListener('shown.bs.tab', drawCharts);
    }
});
