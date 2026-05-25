'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Копирование статистики ────────────────────────────
    window.copyStats = function () {
        const el = document.getElementById('bonusStatsData');
        if (!el) return;

        const stats = {
            'Hourly Bonus':     el.dataset.hourly   + ' pts/h',
            'Active Torrents':  el.dataset.torrents,
            'Seeding Time':     el.dataset.seedtime + ' min',
            'Daily Projection': el.dataset.daily    + ' pts/day',
        };

        const text = Object.entries(stats)
            .map(([k, v]) => `${k}: ${v}`)
            .join('\n');

        navigator.clipboard.writeText(text)
            .then(() => alert('Bonus stats copied to clipboard!'));
    };

});
