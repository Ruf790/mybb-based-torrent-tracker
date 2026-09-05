'use strict';

document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('formatSearch');
    const clearButton = document.getElementById('clearSearch');
    const formatCards = document.querySelectorAll('[data-format]');

    // ── Поиск ─────────────────────────────────────────────
    function applySearch(term) {
        const q = term.toLowerCase().trim();
        formatCards.forEach(card => {
            const match = !q
                || card.dataset.format.includes(q)
                || card.textContent.toLowerCase().includes(q);
            card.style.display = match ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', function () {
        applySearch(this.value);
    });

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        applySearch('');
        searchInput.focus();
    });

    // ── Теги-ярлыки ───────────────────────────────────────
    document.querySelectorAll('.tag-link').forEach(function (tag) {
        tag.addEventListener('click', function () {
            const term   = this.dataset.tag;
            const tabId  = this.dataset.tab + '-tab';

            searchInput.value = term;
            applySearch(term);

            const tabEl = document.getElementById(tabId);
            if (tabEl) {
                new bootstrap.Tab(tabEl).show();
            }
        });
    });

    // ── Клавиатура ────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            applySearch('');
        }
    });

    // ── Bootstrap tooltips ────────────────────────────────
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el);
    });

});
