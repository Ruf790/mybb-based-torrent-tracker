/* Change Username (staff panel) */
(function () {
    'use strict';

    // ── Ланг: AGS_LANG выводит PHP (ключи js_* без префикса) ──
    const L = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
    function t(key, fallback, ...args) {
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

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
    const DEFAULT_HINT = t('hint_default', '3–25 characters; the forum\'s name rules apply');
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
                    document.getElementById('cuCurrent').textContent = t('not_found', 'User not found');
                    document.getElementById('cuMeta').textContent = t('not_found_meta', 'Check the ID or name');
                    av.innerHTML = '<i class="fa-solid fa-user-slash"></i>';
                    setFlag('', '');
                    return;
                }
                uid = d.id;
                document.getElementById('cuCurrent').innerHTML = d.name_html; // экранировано на сервере
                document.getElementById('cuMeta').textContent = t('meta', 'ID {1} · joined {2}', d.id, d.joined);
                av.replaceChildren();
                if (d.avatar) {
                    const img = document.createElement('img');
                    img.src = d.avatar;
                    img.alt = '';
                    av.appendChild(img);
                } else {
                    av.textContent = (d.username || '?').charAt(0).toUpperCase();
                }
                if (d.protected) setFlag(t('flag_protected', 'Protected'), 'is-danger');
                else if (d.super) setFlag(t('flag_super', 'Super admin'), 'is-warn');
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
            hint.textContent = t('len', 'Must be 3–25 characters');
            hint.className = 'form-text text-danger';
            return;
        }
        clearTimeout(tCheck);
        tCheck = setTimeout(() => {
            fetch(self + sep + 'check=' + encodeURIComponent(v) + '&uid=' + uid, { credentials: 'same-origin' })
                .then(r => r.json()).then(d => {
                    if (nameIn.value.trim() !== v) return;
                    nameIn.classList.add(d.taken ? 'is-invalid' : 'is-valid');
                    // Иконка — элементом, перевод — текстовым узлом (не innerHTML)
                    const ic = document.createElement('i');
                    ic.className = 'fa-solid ' + (d.taken ? 'fa-circle-xmark' : 'fa-circle-check') + ' me-1';
                    hint.replaceChildren(ic, document.createTextNode(
                        d.taken ? t('taken', 'Already taken') : t('available', 'Available')
                    ));
                    hint.className = 'form-text ' + (d.taken ? 'text-danger' : 'text-success');
                }).catch(() => {});
        }, 300);
    }

    idIn.addEventListener('input', () => { clearTimeout(tLookup); tLookup = setTimeout(lookup, 300); });
    nameIn.addEventListener('input', check);
    form.addEventListener('reset', () => setTimeout(() => { box.hidden = true; uid = 0; check(); }, 0));
    if (idIn.value.trim()) lookup(); else check();
})();
