/* Staff PM inbox — admin/scripts/staffbox.js */
(function () {
    'use strict';

    // Lang strings from PHP (js_* keys without the prefix)
    const LANG = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};

    // t('key', 'English fallback', arg1, arg2…) — substitutes {1} and %1$s
    function t(key, fallback) {
        let s = (typeof LANG[key] === 'string' && LANG[key] !== '') ? LANG[key] : fallback;
        for (let i = 2; i < arguments.length; i++) {
            const n = i - 1;
            const v = String(arguments[i]);
            s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
        }
        return s;
    }

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        const wrap = document.querySelector('.sb-wrap');
        if (!wrap) {
            return;
        }

        // Tooltips
        if (window.bootstrap && window.bootstrap.Tooltip) {
            wrap.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                window.bootstrap.Tooltip.getOrCreateInstance(el);
            });
        }

        // Remove ?msg=…&n=… from the address bar so a reload is clean
        try {
            const u = new URL(window.location.href);
            if (u.searchParams.has('msg')) {
                u.searchParams.delete('msg');
                u.searchParams.delete('n');
                window.history.replaceState(null, '', u.pathname + u.search + u.hash);
            }
        } catch (e) { /* old browser — ignore */ }

        // Flash close / auto-hide
        wrap.querySelectorAll('.sb-flash').forEach(function (flash) {
            const btn = flash.querySelector('[data-sb-dismiss]');
            if (btn) {
                btn.addEventListener('click', function () { flash.remove(); });
                setTimeout(function () { flash.remove(); }, 7000);
            }
        });

        // Selection
        const boxes = Array.from(wrap.querySelectorAll('.sb-select'));
        const allBoxes = Array.from(wrap.querySelectorAll('.sb-select-all'));
        const bulkBtns = Array.from(wrap.querySelectorAll('[data-sb-bulk]'));
        const countEl = document.getElementById('sbSelCount');
        const bar = wrap.querySelector('.sb-actionbar');

        function selectedCount() {
            return boxes.filter(function (b) { return b.checked; }).length;
        }

        function sync() {
            const n = selectedCount();
            boxes.forEach(function (b) {
                const row = b.closest('tr');
                if (row) {
                    row.classList.toggle('is-selected', b.checked);
                }
            });
            allBoxes.forEach(function (a) {
                a.checked = n > 0 && n === boxes.length;
                a.indeterminate = n > 0 && n < boxes.length;
            });
            bulkBtns.forEach(function (btn) { btn.disabled = n === 0; });
            if (countEl) {
                countEl.textContent = String(n);
            }
            if (bar) {
                bar.classList.toggle('has-selection', n > 0);
            }
        }

        allBoxes.forEach(function (a) {
            a.addEventListener('change', function () {
                boxes.forEach(function (b) { b.checked = a.checked; });
                sync();
            });
        });
        boxes.forEach(function (b) { b.addEventListener('change', sync); });

        // Click on an empty part of a row toggles its checkbox
        wrap.querySelectorAll('.sb-row').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, label')) {
                    return;
                }
                const box = row.querySelector('.sb-select');
                if (box) {
                    box.checked = !box.checked;
                    sync();
                }
            });
        });

        sync();

        // Confirmations (SweetAlert2 with confirm() fallback)
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-sb-confirm]');
            if (!btn || !wrap.contains(btn) || btn.disabled) {
                return;
            }
            const form = btn.form;
            if (!form) {
                return;
            }
            e.preventDefault();

            const title = btn.dataset.sbConfirm.replace('{n}', String(selectedCount()));
            const text = btn.dataset.sbConfirmText || '';
            const confirmText = btn.dataset.sbConfirmBtn || t('confirm', 'Confirm');
            const cancelText = t('cancel', 'Cancel');

            const go = function () {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = btn.name;
                hidden.value = btn.value;
                form.appendChild(hidden);
                form.submit();
            };

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const danger = getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim() || '#dc3545';
                window.Swal.fire({
                    titleText: title,           // titleText/text are rendered as plain text
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '',      // button labels are set as text in didOpen
                    cancelButtonText: '',
                    confirmButtonColor: danger,
                    reverseButtons: true,
                    focusCancel: true,
                    didOpen: function () {
                        const ok = window.Swal.getConfirmButton();
                        const no = window.Swal.getCancelButton();
                        if (ok) { ok.textContent = confirmText; }
                        if (no) { no.textContent = cancelText; }
                    }
                }).then(function (r) {
                    if (r.isConfirmed) {
                        go();
                    }
                });
            } else if (window.confirm(text ? title + '\n\n' + text : title)) {
                go();
            }
        });
    });
})();
