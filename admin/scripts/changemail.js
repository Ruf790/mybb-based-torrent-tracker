/* changemail.php — Change User Email: живой предпросмотр и проверка адреса */
(function () {
    'use strict';

    function init() {
        const form   = document.getElementById('email-change-form');
        if (!form) return; // страница результата — формы нет
        const self   = form.dataset.self || form.getAttribute('action') || location.pathname;
        const sep    = self.includes('?') ? '&' : '?';
        const who    = document.getElementById('username');
        const mail   = document.getElementById('email');
        const box    = document.getElementById('cePreview');
        const hint   = document.getElementById('ceMailHint');
        let uid = 0, current = '', uname = '', tWho, tMail, seq = 0;

        function lookup() {
            const v = who.value.trim();
            if (!v) { box.hidden = true; uid = 0; return; }
            const my = ++seq;
            fetch(self + sep + 'lookup=' + encodeURIComponent(v), { credentials: 'same-origin' })
                .then(r => r.json()).then(d => {
                    if (my !== seq) return;
                    box.hidden = false;
                    const av = document.getElementById('ceAvatar');
                    if (!d.found) {
                        uid = 0; current = ''; uname = '';
                        document.getElementById('ceUser').textContent = 'User not found';
                        document.getElementById('ceOld').textContent = '—';
                        document.getElementById('ceMeta').textContent = 'Check the ID or username';
                        av.innerHTML = '<i class="fa-solid fa-user-slash"></i>';
                        document.getElementById('ceFlag').hidden = true;
                        return;
                    }
                    uid = d.id; current = d.email || ''; uname = d.username;
                    document.getElementById('ceUser').innerHTML = d.name_html; // экранировано сервером
                    document.getElementById('ceOld').textContent = current || '(none)';
                    document.getElementById('ceMeta').textContent = 'ID ' + d.id + ' · joined ' + d.joined;
                    av.replaceChildren();
                    if (d.avatar) { const img = document.createElement('img'); img.src = d.avatar; img.alt = ''; av.appendChild(img); }
                    else av.textContent = (d.username || '?').charAt(0).toUpperCase();
                    document.getElementById('ceFlag').hidden = !d.protected;
                    check();
                }).catch(() => {});
        }

        function check() {
            const v = mail.value.trim();
            document.getElementById('ceNew').textContent = v || '—';
            mail.classList.remove('is-invalid', 'is-valid');
            hint.className = 'form-text';
            if (!v) { hint.textContent = 'The user will receive mail at this address'; return; }
            if (current && v.toLowerCase() === current.toLowerCase()) {
                mail.classList.add('is-invalid'); hint.className = 'form-text text-danger';
                hint.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i>Same as the current email'; return;
            }
            clearTimeout(tMail);
            tMail = setTimeout(() => {
                fetch(self + sep + 'check=' + encodeURIComponent(v) + '&uid=' + uid, { credentials: 'same-origin' })
                    .then(r => r.json()).then(d => {
                        if (mail.value.trim() !== v) return;
                        const bad = !d.valid || d.taken;
                        mail.classList.add(bad ? 'is-invalid' : 'is-valid');
                        hint.className = 'form-text ' + (bad ? 'text-danger' : 'text-success');
                        hint.innerHTML = !d.valid ? '<i class="fa-solid fa-circle-xmark me-1"></i>Not a valid address'
                                       : d.taken ? '<i class="fa-solid fa-circle-xmark me-1"></i>Used by another account'
                                       : '<i class="fa-solid fa-circle-check me-1"></i>Available';
                    }).catch(() => {});
            }, 300);
        }

        who.addEventListener('input', () => { clearTimeout(tWho); tWho = setTimeout(lookup, 300); });
        mail.addEventListener('input', check);
        form.addEventListener('reset', () => setTimeout(() => { box.hidden = true; uid = 0; current = ''; check(); }, 0));
        const esc = t => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
        let confirmed = false;

        function send() {
            confirmed = true;
            const b = document.getElementById('ceSubmit');
            b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
            form.submit(); // submit() не вызывает событие submit — повторного подтверждения не будет
        }

        form.addEventListener('submit', function (e) {
            if (confirmed) return;
            e.preventDefault();
            if (!who.value.trim() || !mail.value.trim()) { form.reportValidity(); return; }

            const name = uname || who.value.trim(), to = mail.value.trim();

            if (typeof Swal === 'undefined') { // fallback, если SweetAlert2 не загрузился
                const msg = 'Change the email of ' + name + (current ? '\nfrom ' + current : '') + '\nto ' + to + '?';
                if (confirm(msg)) send();
                return;
            }

            Swal.fire({
                title: 'Change email?',
                html: '<div class="mb-2"><b>' + esc(name) + '</b></div>'
                    + (current ? '<div class="text-body-secondary text-decoration-line-through">' + esc(current) + '</div>'
                               + '<div class="my-1"><i class="fa-solid fa-arrow-down"></i></div>' : '')
                    + '<div class="fw-semibold text-success">' + esc(to) + '</div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-paper-plane me-1"></i>Change email',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
                reverseButtons: true,
                focusCancel: true
            }).then(r => { if (r.isConfirmed) send(); });
        });
        if (who.value.trim()) lookup(); else check();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();