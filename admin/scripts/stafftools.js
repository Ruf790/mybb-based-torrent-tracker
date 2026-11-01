/* admin/scripts/stafftools.js — поиск и фильтр по категориям на странице Staff Tools */
(function () {
    'use strict';

    var search = document.getElementById('spToolSearch');
    var cats   = document.getElementById('spToolCats');
    var grid   = document.getElementById('spTools');
    var none   = document.getElementById('spNoResult');
    if (!grid) return;

    var tools  = Array.prototype.slice.call(grid.querySelectorAll('.sp-tool'));
    var cat    = 'all';

    function apply() {
        var q = search ? search.value.trim().toLowerCase() : '';
        var shown = 0;
        tools.forEach(function (el) {
            var ok = (cat === 'all' || el.getAttribute('data-category') === cat)
                  && (q === '' || (el.getAttribute('data-search') || '').indexOf(q) !== -1);
            el.hidden = !ok;
            if (ok) shown++;
        });
        if (none) none.hidden = shown !== 0;
    }

    if (search) {
        search.addEventListener('input', apply);
        // Esc — очистить поиск
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && search.value !== '') { search.value = ''; apply(); }
        });
    }

    if (cats) {
        cats.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-sp-cat]');
            if (!btn) return;
            cat = btn.getAttribute('data-sp-cat') || 'all';
            cats.querySelectorAll('[data-sp-cat]').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('btn-outline-secondary', !on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            apply();
        });
    }
})();
