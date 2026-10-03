(function () {
    'use strict';

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
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Deleting…';
                }
                form.submit();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var n = form.getAttribute('data-count') || '';
                var text = 'Permanently delete ' + n + ' orphaned torrent(s) with their covers, screenshots, comments and attachments? This cannot be undone.';

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        title: 'Delete orphaned files?',
                        text: text,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Delete ' + n,
                        cancelButtonText: 'Cancel',
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
