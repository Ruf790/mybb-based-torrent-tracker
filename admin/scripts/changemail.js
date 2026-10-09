/* changemail.php — Change User Email: живой предпросмотр и проверка адреса */
(function () {
    'use strict';

    // Строки из languages/<lang>/changemail.lang.php (js_* без префикса)
    const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    /** Перевод с английским fallback; подстановка {1} и %1$s (так их отдаёт $lang->load()) */
    function t(key, fallback, ...args) {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1, v = String(a);
            s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v).split('%' + n + '$d').join(v);
        });
        return s;
    }

    /** Иконка FA + перевод как текст (не innerHTML) */
    function iconText(el, icon, text) {
        const i = document.createElement('i');
        i.className = 'fa-solid ' + icon + ' me-1';
        el.replaceChildren(i, document.createTextNode(text));
    }

    const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

    function init() {
        const form   = document.getElementById('email-change-form');
        if (!form) return; // страница результата — формы нет
        const self   = form.dataset.self || form.getAttribute('action') || location.pathname;
        const sep    = self.includes('?') ? '&' : '?';
        const who    = document.getElementById('username');
        const mail   = document.getElementById('email');
        const box    = document.getElementById('cePreview');
        const hint   = document.getElementById('ceMailHint');
        const hintDefault = hint.textContent; // уже переведена сервером
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
                        document.getElementById('ceUser').textContent = t('not_found', 'User not found');
                        document.getElementById('ceOld').textContent = '—';
                        document.getElementById('ceMeta').textContent = t('not_found_hint', 'Check the ID or username');
                        av.innerHTML = '<i class="fa-solid fa-user-slash"></i>';
                        document.getElementById('ceFlag').hidden = true;
                        return;
                    }
                    uid = d.id; current = d.email || ''; uname = d.username;
                    document.getElementById('ceUser').innerHTML = d.name_html; // экранировано сервером
                    document.getElementById('ceOld').textContent = current || t('none', '(none)');
                    document.getElementById('ceMeta').textContent = t('meta', 'ID {1} · joined {2}', d.id, d.joined);
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
            if (!v) { hint.textContent = hintDefault; return; }
            if (current && v.toLowerCase() === current.toLowerCase()) {
                mail.classList.add('is-invalid'); hint.className = 'form-text text-danger';
                iconText(hint, 'fa-circle-xmark', t('same', 'Same as the current email')); return;
            }
            clearTimeout(tMail);
            tMail = setTimeout(() => {
                fetch(self + sep + 'check=' + encodeURIComponent(v) + '&uid=' + uid, { credentials: 'same-origin' })
                    .then(r => r.json()).then(d => {
                        if (mail.value.trim() !== v) return;
                        const bad = !d.valid || d.taken;
                        mail.classList.add(bad ? 'is-invalid' : 'is-valid');
                        hint.className = 'form-text ' + (bad ? 'text-danger' : 'text-success');
                        if (!d.valid)     iconText(hint, 'fa-circle-xmark', t('invalid', 'Not a valid address'));
                        else if (d.taken) iconText(hint, 'fa-circle-xmark', t('taken', 'Used by another account'));
                        else              iconText(hint, 'fa-circle-check', t('available', 'Available'));
                    }).catch(() => {});
            }, 300);
        }

        who.addEventListener('input', () => { clearTimeout(tWho); tWho = setTimeout(lookup, 300); });
        mail.addEventListener('input', check);
        form.addEventListener('reset', () => setTimeout(() => { box.hidden = true; uid = 0; current = ''; check(); }, 0));
        let confirmed = false;

        function send() {
            confirmed = true;
            const b = document.getElementById('ceSubmit');
            b.disabled = true;
            const sp = document.createElement('span');
            sp.className = 'spinner-border spinner-border-sm me-1';
            b.replaceChildren(sp, document.createTextNode(t('saving', 'Saving…')));
            form.submit(); // submit() не вызывает событие submit — повторного подтверждения не будет
        }

        form.addEventListener('submit', function (e) {
            if (confirmed) return;
            e.preventDefault();
            if (!who.value.trim() || !mail.value.trim()) { form.reportValidity(); return; }

            const name = uname || who.value.trim(), to = mail.value.trim();

            if (typeof Swal === 'undefined') { // fallback, если SweetAlert2 не загрузился
                const msg = t('confirm_who', 'Change the email of {1}', name)
                          + (current ? '\n' + t('confirm_from', 'from {1}', current) : '')
                          + '\n' + t('confirm_to', 'to {1}?', to);
                if (confirm(msg)) send();
                return;
            }

            Swal.fire({
                titleText: t('confirm_title', 'Change email?'), // titleText — вставляется как текст
                html: '<div class="mb-2"><b>' + esc(name) + '</b></div>'
                    + (current ? '<div class="text-body-secondary text-decoration-line-through">' + esc(current) + '</div>'
                               + '<div class="my-1"><i class="fa-solid fa-arrow-down"></i></div>' : '')
                    + '<div class="fw-semibold text-success">' + esc(to) + '</div>',
                icon: 'question',
                showCancelButton: true,
                // кнопки SweetAlert2 принимают HTML — перевод экранируется
                confirmButtonText: '<i class="fa-solid fa-paper-plane me-1"></i>' + esc(t('confirm_ok', 'Change email')),
                cancelButtonText: esc(t('cancel', 'Cancel')),
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
