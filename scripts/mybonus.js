'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Локализация ───────────────────────────────────────
    // AGS_LANG выводится из PHP перед подключением скрипта (ключи js_* из ланга,
    // без префикса). Если ключа нет - используется английский fallback.
    // Подстановка {1}, {2}… из аргументов.
    function t(key, fallback, ...args) {
        const src = (typeof AGS_LANG !== 'undefined' && AGS_LANG && typeof AGS_LANG[key] === 'string')
            ? AGS_LANG[key]
            : fallback;
        return src.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] !== undefined ? String(args[n - 1]) : m));
    }

    // ── Вкладки: запоминаем в hash (#bn-earn и т.п.) ───────
    const tabs = document.querySelectorAll('.bn-tabs [data-bs-target]');
    if (tabs.length && window.bootstrap && bootstrap.Tab) {
        const fromHash = location.hash && document.querySelector('.bn-tabs [data-bs-target="' + CSS.escape(location.hash) + '"]');
        if (fromHash) bootstrap.Tab.getOrCreateInstance(fromHash).show();

        tabs.forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () {
                history.replaceState(null, '', btn.getAttribute('data-bs-target'));
                btn.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
            });
        });
    }

    // ── Фильтр таблицы торрентов ──────────────────────────
    const filter = document.getElementById('mbTorFilter');
    const body   = document.getElementById('mbTorBody');
    if (filter && body) {
        const rows = Array.from(body.rows);
        filter.addEventListener('input', function () {
            const q = filter.value.trim().toLowerCase();
            rows.forEach(function (tr) {
                tr.hidden = q !== '' && !(tr.dataset.name || '').includes(q);
            });
        });
    }

    // ── Копирование статистики ────────────────────────────
    window.copyStats = function () {
        const el = document.getElementById('bonusStatsData');
        if (!el) return;

        const stats = {
            [t('stat_hourly',   'Hourly Bonus')]:     t('unit_pts_h',   '{1} pts/h',   el.dataset.hourly),
            [t('stat_torrents', 'Active Torrents')]:  el.dataset.torrents,
            [t('stat_seedtime', 'Seeding Time')]:     t('unit_min',     '{1} min',     el.dataset.seedtime),
            [t('stat_daily',    'Daily Projection')]: t('unit_pts_day', '{1} pts/day', el.dataset.daily),
        };

        const text = Object.entries(stats)
            .map(([k, v]) => `${k}: ${v}`)
            .join('\n');

        navigator.clipboard.writeText(text).then(function () {
            const msg = t('copied', 'Bonus stats copied to clipboard!');
            if (typeof showToast === 'function') showToast(msg, 'success');
        });
    };

});
