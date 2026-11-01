/* admin/scripts/stafftools-manage.js — «Управление инструментами» и форма инструмента:
   подтверждение удаления (SweetAlert2, запасной вариант — confirm()),
   «отметить / снять все» группы, имя файла из названия инструмента. */
(function () {
    'use strict';

    /* ── Удаление: форма с data-sp-confirm ─────────────────────────────── */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-sp-confirm]') || form.dataset.spConfirmed === '1') return;
        e.preventDefault();

        var question = form.getAttribute('data-sp-confirm') || 'Delete?';
        var detail   = form.getAttribute('data-sp-confirm-detail') || '';
        var go = function () { form.dataset.spConfirmed = '1'; form.submit(); };

        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'warning',
                title: question,
                text: detail,
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText: form.getAttribute('data-sp-yes') || 'OK',
                cancelButtonText: form.getAttribute('data-sp-no') || 'Cancel',
                confirmButtonColor: 'var(--bs-danger)'
            }).then(function (r) { if (r.isConfirmed) go(); });
        } else if (window.confirm(question + (detail ? '\n\n' + detail : ''))) {
            go();
        }
    });

    /* ── Отметить / снять все группы ───────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-sp-perms]') : null;
        if (!btn) return;
        var state = btn.getAttribute('data-sp-perms') === '1';
        document.querySelectorAll('.perm-cb').forEach(function (cb) { cb.checked = state; });
    });

    /* ── Имя файла = admin/<название>.php ─────────────────────────────── */
    var nameInput = document.getElementById('toolName');
    var fileInput = document.getElementById('toolFilename');
    if (nameInput && fileInput) {
        var sync = function () {
            var v = nameInput.value.trim().toLowerCase();
            fileInput.value = v ? v + '.php' : '';
        };
        nameInput.addEventListener('input', sync);
        nameInput.form && nameInput.form.addEventListener('reset', function () { setTimeout(sync, 0); });
    }
})();
