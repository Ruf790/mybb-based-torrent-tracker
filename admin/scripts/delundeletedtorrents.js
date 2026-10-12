(function () {
    'use strict';

    // Lang helper: AGS_LANG[key] (or English fallback), {1}/%1$s substitution
    var LANG = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    function t(key, fallback) {
        var str = (typeof LANG[key] === 'string' && LANG[key] !== '') ? LANG[key] : fallback;
        var args = Array.prototype.slice.call(arguments, 2);
        return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            var i = parseInt(a || b, 10) - 1;
            return (i >= 0 && i < args.length) ? String(args[i]) : m;
        });
    }

    // SweetAlert2 inserts button labels as HTML — escape them
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Success toast after redirect
        var toastEl = document.getElementById('deletedToast');
        if (toastEl && window.bootstrap && window.bootstrap.Toast) {
            new window.bootstrap.Toast(toastEl).show();
        }

        // Delete confirmation: SweetAlert2 with confirm() fallback
        var form = document.getElementById('dutDeleteForm');
        if (form) {
            var lockAndSubmit = function () {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    var icon = document.createElement('i');
                    icon.className = 'fa-solid fa-spinner fa-spin me-2';
                    btn.textContent = '';
                    btn.appendChild(icon);
                    btn.appendChild(document.createTextNode(t('deleting', 'Deleting…')));
                }
                form.submit();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var n = form.getAttribute('data-count') || '';
                var text = t('confirm_text', 'Permanently delete {1} orphaned torrent(s) with their covers, screenshots, comments and attachments? This cannot be undone.', n);

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        titleText: t('confirm_title', 'Delete orphaned files?'),
                        text: text,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: esc(t('confirm_btn', 'Delete {1}', n)),
                        cancelButtonText: esc(t('cancel', 'Cancel')),
                        confirmButtonColor: '#dc3545',
                        reverseButtons: true,
                        focusCancel: true
                    }).then(function (r) {
                        if (r.isConfirmed) {
                            lockAndSubmit();
                        }
                    });
                } else if (window.confirm(text)) {
                    lockAndSubmit();
                }
            });
        }

        // ID filter
        var input = document.getElementById('dutFilter');
        var list = document.getElementById('dutOrphanList');
        if (input && list) {
            var chips = list.querySelectorAll('.dut-chip');
            var counter = document.getElementById('dutFilterCount');
            input.addEventListener('input', function () {
                var q = input.value.replace(/\D/g, '');
                var shown = 0;
                chips.forEach(function (c) {
                    var match = q === '' || (c.getAttribute('data-id') || '').indexOf(q) !== -1;
                    c.hidden = !match;
                    if (match) { shown++; }
                });
                if (counter) { counter.textContent = String(shown); }
            });
        }
    });
})();
