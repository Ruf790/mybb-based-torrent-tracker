(function () {
    'use strict';

    /* ============================================================
     * 1. Форма выбора таблиц (truncate)
     * ============================================================ */
    function initSelectionForm() {
        const list = document.getElementById('ctList');
        if (!list) return;

        const rows  = Array.from(list.querySelectorAll('.ct-row:not(.ct-locked)'));
        const srch  = document.getElementById('ctSearch');
        const cnt   = document.getElementById('ctCount');

        const checks = () => rows.map(r => r.querySelector('input[type=checkbox]'));

        const upd = () => {
            let n = 0;
            rows.forEach(r => {
                const chk = r.querySelector('input[type=checkbox]');
                const on  = chk && chk.checked;
                r.classList.toggle('ct-selected', !!on);
                if (on) n++;
            });
            if (cnt) cnt.textContent = n;
        };

        rows.forEach(r => {
            const chk = r.querySelector('input[type=checkbox]');
            if (chk) chk.addEventListener('change', upd);
        });

        if (srch) {
            srch.addEventListener('input', function () {
                const q = this.value.toLowerCase();
                rows.forEach(r => {
                    const name = (r.dataset.name || '').toLowerCase();
                    r.classList.toggle('hidden-by-search', !name.includes(q));
                });
            });
        }

        const ctAll = document.getElementById('ctAll');
        if (ctAll) ctAll.addEventListener('click', () => {
            rows.forEach(r => {
                if (r.classList.contains('hidden-by-search')) return;
                const chk = r.querySelector('input[type=checkbox]');
                if (chk) chk.checked = true;
            });
            upd();
        });

        const ctNone = document.getElementById('ctNone');
        if (ctNone) ctNone.addEventListener('click', () => {
            checks().forEach(chk => { if (chk) chk.checked = false; });
            upd();
        });

        const ctInvert = document.getElementById('ctInvert');
        if (ctInvert) ctInvert.addEventListener('click', () => {
            rows.forEach(r => {
                if (r.classList.contains('hidden-by-search')) return;
                const chk = r.querySelector('input[type=checkbox]');
                if (chk) chk.checked = !chk.checked;
            });
            upd();
        });

        const form = document.getElementById('truncateForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                const n = checks().filter(chk => chk && chk.checked).length;
                if (n === 0) {
                    e.preventDefault();
                    alert('Select at least one table.');
                    return;
                }
                if (!confirm('Truncate ' + n + ' table(s)?\n\nAll data will be permanently deleted.\nThis cannot be undone!')) {
                    e.preventDefault();
                }
            });
        }
    }

    /* ============================================================
     * 2. Страница результатов + AJAX optimize
     * ============================================================ */
    function initOptimize() {
        const panel = document.getElementById('ctSuccessPanel');
        if (!panel) return;

        // Данные передаются через data-атрибуты (безопаснее, чем инлайн-JS)
        let tables = [];
        let postKey = '';

        try {
            tables  = JSON.parse(panel.dataset.tables || '[]');
            postKey = panel.dataset.postKey || '';
        } catch (e) {
            console.error('cleartable: failed to parse data attributes', e);
            return;
        }

        const btn      = document.getElementById('btnOptimize');
        const bar      = document.getElementById('opt_bar');
        const label    = document.getElementById('opt_label');
        const progress = document.getElementById('opt_progress');
        const done     = document.getElementById('opt_done');

        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled  = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Optimizing…';
            progress.style.display = 'block';

            let idx = 0;

            function optimizeNext() {
                if (idx >= tables.length) {
                    progress.style.display = 'none';
                    done.style.display     = 'block';
                    btn.style.display      = 'none';
                    return;
                }

                const table = tables[idx];
                const pct   = Math.round((idx / tables.length) * 100);
                bar.style.width   = pct + '%';
                label.textContent = 'Optimizing: ' + table + ' (' + (idx + 1) + '/' + tables.length + ')';

                const statusEl = document.getElementById('opt_status_' + table);

                fetch(window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'do=ajax_optimize&table=' + encodeURIComponent(table) +
                          '&my_post_key=' + encodeURIComponent(postKey)
                })
                .then(r => r.json())
                .then(data => {
                    if (statusEl) {
                        statusEl.innerHTML = data.success
                            ? '<span style="color:#1e8e4f"><i class="bi bi-lightning-charge-fill"></i> optimized</span>'
                            : '<span style="color:var(--danger)" title="' +
                              (data.message || '').replace(/"/g, '&quot;') + '">failed - ' +
                              (data.message || 'unknown error') + '</span>';
                    }
                    idx++;
                    bar.style.width = Math.round((idx / tables.length) * 100) + '%';
                    optimizeNext();
                })
                .catch(err => {
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color:var(--danger)">error - ' + err + '</span>';
                    }
                    idx++;
                    optimizeNext();
                });
            }

            optimizeNext();
        });
    }

    /* ============================================================
     * Инициализация после загрузки DOM
     * ============================================================ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initSelectionForm();
            initOptimize();
        });
    } else {
        initSelectionForm();
        initOptimize();
    }
})();