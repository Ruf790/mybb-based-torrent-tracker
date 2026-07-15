'use strict';

// ── Poster zoom ────────────────────────────────────────────
(function () {
    const overlay = document.getElementById('posterZoomOverlay');
    const img     = document.getElementById('posterZoomImg');
    if (!overlay || !img) return;

    let timer = null;

    document.addEventListener('mouseenter', e => {
        const link = e.target?.closest?.('.poster-link[data-zoom]');
        if (!link) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            img.src = link.dataset.zoom;
            img.classList.add('visible');
        }, 150);
    }, true);

    document.addEventListener('mouseleave', e => {
        const link = e.target?.closest?.('.poster-link[data-zoom]');
        if (!link) return;
        clearTimeout(timer);
        img.classList.remove('visible');
        setTimeout(() => { img.src = ''; }, 200);
    }, true);

    document.addEventListener('mousemove', e => {
        if (!img.classList.contains('visible')) return;
        const offX = e.clientX > window.innerWidth  / 2 ? -300 : 20;
        const offY = e.clientY > window.innerHeight / 2 ? -420 : 20;
        img.style.cssText = `position:fixed;left:${e.clientX + offX}px;top:${e.clientY + offY}px;transform:none`;
    });
})();

// ── Dead rows ──────────────────────────────────────────────
document.querySelectorAll('.torrent-row').forEach(row => {
    if (+row.dataset.seeders === 0 && +row.dataset.leechers === 0 && row.dataset.external !== 'yes') {
        row.classList.add('is-dead');
    }
});
