/* Allowed clients (allagents.php) */
(function () {
    'use strict';

    function confirmAction(opts) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: opts.title,
                text: opts.text,
                icon: opts.icon || 'question',
                showCancelButton: true,
                confirmButtonText: opts.confirm,
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then(function (r) { return r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(opts.text));
    }

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('.aa-page');
        const form = document.getElementById('agentsForm');
        if (!root || !form) {
            return;
        }

        const boxes     = Array.from(form.querySelectorAll("input[name='client[]']"));
        const rows      = Array.from(form.querySelectorAll('.aa-row'));
        const search    = root.querySelector('#aaSearch');
        const filters   = Array.from(root.querySelectorAll('[data-aa-filter]'));
        const bulk      = Array.from(root.querySelectorAll('[data-aa-bulk]'));
        const noResults = root.querySelector('#aaNoResults');
        const saveBar   = root.querySelector('.aa-savebar');
        const status    = root.querySelector('#aaDirty');
        const kpiAllow  = root.querySelector('[data-kpi="allowed"]');
        const kpiBlock  = root.querySelector('[data-kpi="blocked"]');
        const resetBtn  = form.querySelector("button[type='reset']");

        let currentFilter = 'all';
        let submitting = false;

        function paint(box) {
            const row = box.closest('.aa-row');
            if (!row) {
                return;
            }
            row.dataset.state = box.checked ? 'allowed' : 'blocked';
            row.classList.toggle('is-changed', box.checked !== box.defaultChecked);

            const label = row.querySelector('.aa-state');
            if (label) {
                label.innerHTML = box.checked
                    ? '<i class="fa-solid fa-circle-check"></i><span>Allowed</span>'
                    : '<i class="fa-solid fa-ban"></i><span>Blocked</span>';
            }
        }

        function dirtyCount() {
            return boxes.filter(function (b) { return b.checked !== b.defaultChecked; }).length;
        }

        function refresh() {
            const allowed = boxes.filter(function (b) { return b.checked; }).length;
            if (kpiAllow) { kpiAllow.textContent = String(allowed); }
            if (kpiBlock) { kpiBlock.textContent = String(boxes.length - allowed); }

            if (!status || !saveBar) {
                return;
            }
            const n = dirtyCount();
            saveBar.classList.toggle('is-dirty', n > 0);
            status.innerHTML = n > 0
                ? '<i class="fa-solid fa-pen"></i><span>' + n + (n === 1 ? ' unsaved change' : ' unsaved changes') + '</span>'
                : '<i class="fa-solid fa-circle-check"></i><span>No unsaved changes</span>';
        }

        function repaintAll() {
            boxes.forEach(paint);
            refresh();
        }

        function applyFilter() {
            const q = search ? search.value.trim().toLowerCase() : '';
            let shown = 0;
            rows.forEach(function (row) {
                const matchText  = q === '' || (row.dataset.search || '').indexOf(q) !== -1;
                const matchState = currentFilter === 'all' || row.dataset.state === currentFilter;
                row.hidden = !(matchText && matchState);
                if (!row.hidden) { shown++; }
            });
            if (noResults) {
                noResults.hidden = shown > 0 || rows.length === 0;
            }
        }

        boxes.forEach(function (box) {
            box.addEventListener('change', function () {
                paint(box);
                refresh();
            });
        });

        if (search) {
            search.addEventListener('input', applyFilter);
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    search.value = '';
                    applyFilter();
                }
            });
            // "/" jumps to the search field
            document.addEventListener('keydown', function (e) {
                const t = e.target;
                const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
                if (e.key === '/' && !typing) {
                    e.preventDefault();
                    search.focus();
                }
            });
        }

        filters.forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentFilter = btn.dataset.aaFilter || 'all';
                filters.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                applyFilter();
            });
        });

        bulk.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const allow = btn.dataset.aaBulk === 'allow';
                rows.forEach(function (row) {
                    if (row.hidden) { return; }
                    const box = row.querySelector("input[name='client[]']");
                    if (box && box.checked !== allow) {
                        box.checked = allow;
                        paint(box);
                    }
                });
                refresh();
            });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (dirtyCount() === 0) {
                    return;
                }
                confirmAction({
                    title: 'Discard changes?',
                    text: 'Every switch goes back to its saved position.',
                    icon: 'warning',
                    confirm: 'Discard changes'
                }).then(function (ok) {
                    if (ok) {
                        form.reset();
                        repaintAll();
                    }
                });
            });
        }

        form.addEventListener('submit', function (e) {
            if (submitting) {
                return;
            }
            const anyAllowed = boxes.some(function (b) { return b.checked; });
            if (anyAllowed) {
                submitting = true;
                return;
            }
            e.preventDefault();
            confirmAction({
                title: 'Block every client?',
                text: 'No client will be on the allowed list after saving.',
                icon: 'warning',
                confirm: 'Save anyway'
            }).then(function (ok) {
                if (ok) {
                    submitting = true;
                    form.submit();
                }
            });
        });

        window.addEventListener('beforeunload', function (e) {
            if (!submitting && dirtyCount() > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        repaintAll();
        applyFilter();
    });
})();
