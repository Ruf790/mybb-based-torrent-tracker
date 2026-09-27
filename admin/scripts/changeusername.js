/* Change Username (staff panel) */
(function () {
    'use strict';

    // ── Экран подтверждения: кнопка активна только при отмеченном чекбоксе ──
    const confirmBox = document.getElementById('cuConfirm');
    const confirmBtn = document.getElementById('cuConfirmBtn');
    if (confirmBox && confirmBtn) {
        confirmBox.addEventListener('change', function () {
            confirmBtn.disabled = !this.checked;
        });
    }

    // ── Форма: живой предпросмотр пользователя и проверка ника ──
    const form = document.getElementById('username-change-form');
    if (!form) return;

    const self   = form.dataset.self || window.location.pathname;
    const sep    = self.includes('?') ? '&' : '?';
    const idIn   = document.getElementById('user-id');
    const nameIn = document.getElementById('username');
    const box    = document.getElementById('cuPreview');
    const flag   = document.getElementById('cuFlag');
    const hint   = document.getElementById('cuNameHint');
    const DEFAULT_HINT = '3–25 characters; the forum\'s name rules apply';
    let uid = 0, tLookup, tCheck, seq = 0;

    function setFlag(text, cls) {
        flag.hidden = !text;
        flag.textContent = text || '';
        flag.className = 'cu-flag ' + (cls || '');
    }

    function lookup() {
        const v = idIn.value.trim();
        if (!v) { box.hidden = true; uid = 0; return; }
        const my = ++seq;
        fetch(self + sep + 'lookup=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (my !== seq) return;
                box.hidden = false;
                const av = document.getElementById('cuAvatar');
                if (!d.found) {
                    uid = 0;
                    document.getElementById('cuCurrent').textContent = 'User not found';
                    document.getElementById('cuMeta').textContent = 'Check the ID or name';
                    av.innerHTML = '<i class="fa-solid fa-user-slash"></i>';
                    setFlag('', '');
                    return;
                }
                uid = d.id;
                document.getElementById('cuCurrent').innerHTML = d.name_html; // экранировано на сервере
                document.getElementById('cuMeta').textContent = 'ID ' + d.id + ' · joined ' + d.joined;
                av.replaceChildren();
                if (d.avatar) {
                    const img = document.createElement('img');
                    img.src = d.avatar;
                    img.alt = '';
                    av.appendChild(img);
                } else {
                    av.textContent = (d.username || '?').charAt(0).toUpperCase();
                }
                if (d.protected) setFlag('Protected', 'is-danger');
                else if (d.super) setFlag('Super admin', 'is-warn');
                else setFlag('', '');
                check();
            }).catch(() => {});
    }

    function check() {
        const v = nameIn.value.trim();
        document.getElementById('cuNew').textContent = v || '—';
        nameIn.classList.remove('is-invalid', 'is-valid');
        if (!v) { hint.textContent = DEFAULT_HINT; hint.className = 'form-text'; return; }
        if (v.length < 3 || v.length > 25) {
            nameIn.classList.add('is-invalid');
            hint.textContent = 'Must be 3–25 characters';
            hint.className = 'form-text text-danger';
            return;
        }
        clearTimeout(tCheck);
        tCheck = setTimeout(() => {
            fetch(self + sep + 'check=' + encodeURIComponent(v) + '&uid=' + uid, { credentials: 'same-origin' })
                .then(r => r.json()).then(d => {
                    if (nameIn.value.trim() !== v) return;
                    nameIn.classList.add(d.taken ? 'is-invalid' : 'is-valid');
                    hint.innerHTML = d.taken
                        ? '<i class="fa-solid fa-circle-xmark me-1"></i>Already taken'
                        : '<i class="fa-solid fa-circle-check me-1"></i>Available';
                    hint.className = 'form-text ' + (d.taken ? 'text-danger' : 'text-success');
                }).catch(() => {});
        }, 300);
    }

    idIn.addEventListener('input', () => { clearTimeout(tLookup); tLookup = setTimeout(lookup, 300); });
    nameIn.addEventListener('input', check);
    form.addEventListener('reset', () => setTimeout(() => { box.hidden = true; uid = 0; check(); }, 0));
    if (idIn.value.trim()) lookup(); else check();
})();
