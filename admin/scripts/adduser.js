/* admin/scripts/add_user.js — Create New Account (admin/adduser.php)
 * Ждёт перед собой: const AGS_LANG (строки js_* без префикса) и const AU_CFG.
 * Переводы вставляются только как текст (textContent / createTextNode). */
(function () {
    'use strict';
    const L   = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
    const cfg = (typeof AU_CFG !== 'undefined' && AU_CFG) ? AU_CFG : {};

    // t(key, fallback, ...args): строка из ланга (или английский fallback) с подстановкой {1} / %1$s
    function t(key, fallback, ...args) {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1, v = String(a);
            s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
        });
        return s;
    }

    const $ = id => document.getElementById(id);
    const sep = cfg.self.includes('?') ? '&' : '?';
    const units = { B: 1, MB: 1024 ** 2, GB: 1024 ** 3, TB: 1024 ** 4 };
    const unitLabels = [t('unit_b', 'B'), t('unit_kb', 'KB'), t('unit_mb', 'MB'), t('unit_gb', 'GB'), t('unit_tb', 'TB'), t('unit_pb', 'PB')];
    const fmt = b => { let i = 0; while (b >= 1024 && i < unitLabels.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(2) : b) + ' ' + unitLabels[i]; };
    const icon = (cls) => { const i = document.createElement('i'); i.className = 'fa-solid ' + cls + ' me-1'; return i; };
    // hint(id, text, cls, iconClass) — иконка элементом, текст — текстовым узлом
    const hint = (id, text, cls, ic) => {
        const h = $(id); if (!h) return;
        h.className = 'au-hint' + (cls ? ' ' + cls : '');
        h.replaceChildren();
        if (ic) h.appendChild(icon(ic));
        h.appendChild(document.createTextNode(text));
    };
    const nameLen = () => t('name_len', '{1}–{2} characters', cfg.minName, cfg.maxName);
    const pwLen   = () => cfg.complex
        ? t('pw_len_complex', '{1}–{2} characters, letters and numbers', cfg.minPw, cfg.maxPw)
        : t('pw_len', '{1}–{2} characters', cfg.minPw, cfg.maxPw);

    // ── Bootstrap-валидация ─────────────────────────────────
    const form = $('auForm');
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); form.classList.add('was-validated'); return; }
        const b = $('auSubmit'); b.disabled = true;
        const sp = document.createElement('span'); sp.className = 'spinner-border spinner-border-sm me-1';
        b.replaceChildren(sp, document.createTextNode(t('creating', 'Creating…')));
    });

    // ── Ник: длина + живая проверка занятости ───────────────
    // Раньше разрешались только a-z и цифры (и в браузере, и на сервере)
    let tName, tMail;
    $('input_username').addEventListener('input', function () {
        const v = this.value.trim();
        const len = [...v].length;
        if (!v) { this.setCustomValidity(''); hint('hint_username', nameLen()); update(); return; }
        if (len < cfg.minName || len > cfg.maxName) {
            this.setCustomValidity('length'); hint('hint_username', nameLen(), 'bad', 'fa-circle-xmark'); update(); return;
        }
        if (/[<>&"'\\\x00-\x1f]/.test(v)) {
            this.setCustomValidity('chars'); hint('hint_username', t('name_chars', 'Characters < > & " \' \\ are not allowed'), 'bad', 'fa-circle-xmark'); update(); return;
        }
        this.setCustomValidity('');
        clearTimeout(tName);
        tName = setTimeout(() => fetch(cfg.self + sep + 'check_name=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if ($('input_username').value.trim() !== v) return;
                if (d.taken)       { $('input_username').setCustomValidity('taken');  hint('hint_username', t('name_taken', 'Already taken'), 'bad', 'fa-circle-xmark'); }
                else if (d.banned) { $('input_username').setCustomValidity('banned'); hint('hint_username', t('banned', 'Disallowed by a ban filter'), 'bad', 'fa-ban'); }
                else hint('hint_username', t('available', 'Available'), 'ok', 'fa-circle-check');
            }).catch(() => {}), 300);
        update();
    });

    // ── Email: формат + занятость + бан ─────────────────────
    $('input_email').addEventListener('input', function () {
        const v = this.value.trim();
        update();
        if (!v) { hint('hint_email', t('email_hint', 'Used for login, notifications and activation')); return; }
        clearTimeout(tMail);
        tMail = setTimeout(() => fetch(cfg.self + sep + 'check_email=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if ($('input_email').value.trim() !== v) return;
                const bad = !d.valid || d.taken || d.banned;
                $('input_email').setCustomValidity(bad ? 'bad' : '');
                if (!d.valid)       hint('hint_email', t('email_invalid', 'Not a valid address'), 'bad', 'fa-circle-xmark');
                else if (d.taken)   hint('hint_email', t('email_taken', 'Used by another account'), 'bad', 'fa-circle-xmark');
                else if (d.banned)  hint('hint_email', t('banned', 'Disallowed by a ban filter'), 'bad', 'fa-ban');
                else                hint('hint_email', t('available', 'Available'), 'ok', 'fa-circle-check');
            }).catch(() => {}), 350);
    });

    // ── Пароль: правила + индикатор надёжности ──────────────
    const strengthLabels = [
        t('strength_0', 'Very weak'), t('strength_1', 'Weak'), t('strength_2', 'Fair'),
        t('strength_3', 'Good'), t('strength_4', 'Strong'), t('strength_5', 'Very strong'),
    ];
    function checkPw() {
        const p = $('input_password'), p2 = $('input_password2'), v = p.value;
        let err = '';
        if (v.length < cfg.minPw) err = t('pw_min', 'At least {1} characters', cfg.minPw);
        else if (v.length > cfg.maxPw) err = t('pw_max', 'At most {1} characters', cfg.maxPw);
        else if (cfg.complex && (!/[a-zA-Z]/.test(v) || !/[0-9]/.test(v))) err = t('pw_complex', 'Needs letters and numbers');
        else if (v && v === $('input_username').value.trim()) err = t('pw_same', 'Must differ from the username');
        p.setCustomValidity(err);

        let score = 0;
        if (v.length >= 8) score++; if (v.length >= 12) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++; if (/\d/.test(v)) score++; if (/[^A-Za-z0-9]/.test(v)) score++;
        const bar = $('au_strength'), colors = ['#ef4444', '#ef4444', '#f59e0b', '#eab308', '#22c55e', '#16a34a'];
        bar.style.width = v ? (Math.max(1, score) / 5 * 100) + '%' : '0';
        bar.style.backgroundColor = colors[score];
        if (!v)       hint('hint_password', pwLen(), '');
        else if (err) hint('hint_password', err, 'bad', 'fa-circle-xmark');
        else          hint('hint_password', strengthLabels[score], score >= 3 ? 'ok' : '');

        const match = p2.value === v;
        p2.setCustomValidity(match ? '' : 'mismatch');
        if (!p2.value)  hint('hint_password2', '', '');
        else if (match) hint('hint_password2', t('pw_match', 'Passwords match'), 'ok', 'fa-circle-check');
        else            hint('hint_password2', t('pw_mismatch', 'Passwords do not match'), 'bad', 'fa-circle-xmark');
    }
    $('input_password').addEventListener('input', checkPw);
    $('input_password2').addEventListener('input', checkPw);
    document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
        const f = $(b.dataset.togglePw), show = f.type === 'password';
        f.type = show ? 'text' : 'password'; $('input_password2').type = f.type;
        b.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
    }));

    // Генерация пароля — криптостойкий генератор вместо Math.random()
    window.generatePassword = function () {
        const letters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ', digits = '23456789', symbols = '!@#$%^&*';
        const all = letters + digits + symbols;
        const len = Math.min(cfg.maxPw, Math.max(cfg.minPw, 14));
        const rnd = n => { const a = new Uint32Array(1); crypto.getRandomValues(a); return a[0] % n; };
        const chars = [letters[rnd(letters.length)], digits[rnd(digits.length)], symbols[rnd(symbols.length)]];
        while (chars.length < len) chars.push(all[rnd(all.length)]);
        for (let i = chars.length - 1; i > 0; i--) { const j = rnd(i + 1); [chars[i], chars[j]] = [chars[j], chars[i]]; }
        const pw = chars.join('');
        $('input_password').value = pw; $('input_password2').value = pw; checkPw();
        $('generated_password_text').textContent = pw;
        $('generated_password_box').style.display = '';
    };
    window.copyGeneratedPassword = function () {
        const txt = $('generated_password_text').textContent;
        if (txt) navigator.clipboard?.writeText(txt).then(() => { if (typeof showToast === 'function') showToast(t('copied', 'Password copied to clipboard'), 'success'); });
    };

    // ── Аватар ──────────────────────────────────────────────
    window.switchAvatarTab = function (tab) {
        $('avatar-panel-url').style.display  = tab === 'url'  ? '' : 'none';
        $('avatar-panel-file').style.display = tab === 'file' ? '' : 'none';
        $('tab-url').classList.toggle('active',  tab === 'url');
        $('tab-file').classList.toggle('active', tab === 'file');
    };
    function showAvatar(src) {
        const box = $('pvAvatar');
        box.replaceChildren();
        if (src) {
            const img = document.createElement('img'); img.alt = ''; img.src = src;
            img.onerror = () => { box.textContent = initial(); };
            box.appendChild(img);
            $('avatar_preview').hidden = false;
        } else {
            box.textContent = initial();
            $('avatar_preview').hidden = true;
        }
    }
    const initial = () => ([...$('input_username').value.trim()][0] || '?').toUpperCase();
    window.clearAvatar = function () {
        $('input_avatar').value = ''; $('input_avatar_file').value = '';
        $('avatar-filename').style.display = 'none';
        showAvatar('');
    };
    window.handleAvatarDrop = function (e) {
        e.preventDefault();
        $('avatar-dropzone').classList.remove('border-primary');
        if (e.dataTransfer.files[0]) setAvatarFile(e.dataTransfer.files[0]);
    };
    function setAvatarFile(file) {
        const dt = new DataTransfer(); dt.items.add(file);
        $('input_avatar_file').files = dt.files;
        $('avatar-filename').textContent = file.name; $('avatar-filename').style.display = 'block';
        const r = new FileReader(); r.onload = e => showAvatar(e.target.result); r.readAsDataURL(file);
    }
    $('input_avatar_file').addEventListener('change', function () { if (this.files[0]) setAvatarFile(this.files[0]); });
    $('input_avatar').addEventListener('input', function () { showAvatar(/^https?:\/\//i.test(this.value) ? this.value : ''); });

    // ── Карточка-превью ─────────────────────────────────────
    function bytes(field, unitName, def) {
        const raw = $(field).value.trim();
        const u = document.querySelector('[name="' + unitName + '"]').value;
        return raw === '' ? def : Math.max(0, parseFloat(raw) || 0) * units[u];
    }
    function update() {
        const name = $('input_username').value.trim();
        $('pvName').textContent = name || t('new_user', 'New user');
        $('pvEmail').textContent = $('input_email').value.trim() || t('no_email', 'no email yet');
        if (!$('pvAvatar').querySelector('img')) $('pvAvatar').textContent = initial();
        const g = $('input_usergroup'), opt = g.options[g.selectedIndex];
        // Пункт «По умолчанию — …» хранит чистое имя группы в data-name
        $('pvGroup').textContent = opt.dataset.name ?? opt.text;
        const up = bytes('input_uploaded', 'uploaded_unit', cfg.defUpGb * units.GB);
        const down = bytes('input_downloaded', 'downloaded_unit', 0);
        $('pvUp').textContent = fmt(up); $('pvDown').textContent = fmt(down);
        $('pvRatio').textContent = down > 0 ? (up / down).toFixed(2) : '∞';
        const sb = $('input_seedbonus').value.trim();
        $('pvBonus').textContent = (sb === '' ? cfg.defBonus : parseInt(sb, 10) || 0).toLocaleString();
        $('pvInv').textContent = (parseInt($('input_invites').value, 10) || 0).toLocaleString();
        const pending = $('confirm').checked;
        const st = document.createElement('span');
        st.className = pending ? 'text-warning' : 'text-success';
        st.append(icon(pending ? 'fa-hourglass-half' : 'fa-circle-check'),
                  document.createTextNode(pending ? t('pending', 'Pending activation') : t('confirmed', 'Confirmed')));
        $('pvStatus').replaceChildren(st);
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);

    // Начальное состояние (после ошибки поля уже заполнены)
    if ($('input_avatar').value) showAvatar($('input_avatar').value);
    if ($('input_username').value) $('input_username').dispatchEvent(new Event('input'));
    if ($('input_email').value) $('input_email').dispatchEvent(new Event('input'));
    update();
})();
