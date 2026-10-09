(function () {
    'use strict';

    var form  = document.getElementById('ip-search-form');
    var input = document.getElementById('ip-address');
    var reV4  = /^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/;
    var reV6  = /^[0-9a-fA-F:.]+$/;

    // Ланг из PHP (const AGS_LANG), английский fallback, подстановка {1} и %1$s
    var L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    function t(key, fallback) {
        var str  = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        var args = Array.prototype.slice.call(arguments, 2);
        return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            var i = parseInt(a || b, 10) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }

    // Поиск: лёгкая проверка на клиенте (сервер всё равно валидирует) + спиннер
    if (form && input) {
        input.addEventListener('input', function () {
            input.setCustomValidity('');
            form.classList.remove('is-invalid');
        });
        form.addEventListener('submit', function (e) {
            var val = input.value.trim();
            input.value = val;
            var ok = reV4.test(val) || (val.indexOf(':') !== -1 && reV6.test(val));
            if (!ok) {
                e.preventDefault();
                form.classList.add('is-invalid');
                input.setCustomValidity(t('invalid_ip', 'Enter a valid IPv4 or IPv6 address'));
                input.reportValidity();
                return;
            }
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                var icon = btn.querySelector('i');
                if (icon) { icon.className = 'fa-solid fa-circle-notch fa-spin'; }
            }
        });
    }

    // Примеры IP
    document.querySelectorAll('.ips-chip[data-ip]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (!input) { return; }
            input.value = chip.getAttribute('data-ip') || '';
            input.focus();
        });
    });

    // Копирование (passkey / IP)
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (err) { /* ignore */ }
        document.body.removeChild(ta);
    }
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            var done = function () {
                var icon = btn.querySelector('i');
                if (!icon) { return; }
                var old = icon.className;
                icon.className = 'fa-solid fa-check';
                btn.classList.add('is-copied');
                setTimeout(function () {
                    icon.className = old;
                    btn.classList.remove('is-copied');
                }, 1200);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
            } else {
                fallbackCopy(text);
                done();
            }
        });
    });

    // Сброс passkey: SweetAlert2, иначе confirm()
    document.querySelectorAll('form.ips-reset-form').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (f.dataset.confirmed === '1') { return; }
            e.preventDefault();

            var name = f.getAttribute('data-username') || '';
            var msg  = t('reset_text', 'Reset passkey for {1}? The user will have to re-download all .torrent files.', name);
            var go   = function () {
                f.dataset.confirmed = '1';
                f.submit();
            };

            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    title: t('reset_title', 'Reset passkey?'),
                    text: msg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: t('reset_confirm', 'Yes, reset'),
                    cancelButtonText: t('reset_cancel', 'Cancel'),
                    confirmButtonColor: '#dc3545',
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (r) { if (r.isConfirmed) { go(); } });
            } else if (window.confirm(msg)) {
                go();
            }
        });
    });
})();
