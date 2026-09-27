/* Snatched Torrents (staff panel) — client-side column sorting */

document.addEventListener('DOMContentLoaded', function () {
    const headers = document.querySelectorAll('.stn-page .sortable');

    headers.forEach(function (th) {
        th.addEventListener('click', function () {
            const tbody = th.closest('table').querySelector('tbody');
            const rows  = Array.from(tbody.rows);
            const idx   = Array.from(th.parentElement.cells).indexOf(th);
            const isNum = th.dataset.type === 'num';
            const asc   = !th.classList.contains('asc');

            rows.sort(function (a, b) {
                const va = a.cells[idx].dataset.sort ?? a.cells[idx].textContent.trim();
                const vb = b.cells[idx].dataset.sort ?? b.cells[idx].textContent.trim();
                const r  = isNum ? (parseFloat(va) || 0) - (parseFloat(vb) || 0) : va.localeCompare(vb);
                return asc ? r : -r;
            });
            rows.forEach(function (row) { tbody.appendChild(row); });

            headers.forEach(function (h) {
                h.classList.remove('asc', 'desc');
                const ico = h.querySelector('.stn-sort-ico');
                if (ico) { ico.className = 'fa-solid fa-sort stn-sort-ico'; }
            });
            th.classList.add(asc ? 'asc' : 'desc');
            const ico = th.querySelector('.stn-sort-ico');
            if (ico) { ico.className = 'fa-solid ' + (asc ? 'fa-sort-up' : 'fa-sort-down') + ' stn-sort-ico'; }
        });
    });
});
