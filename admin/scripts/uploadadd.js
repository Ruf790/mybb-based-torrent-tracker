/* Upload Manager (uploadadd.php) — URL страницы берётся из data-self на #um */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const root = $('um');
    const self = root.dataset.self || location.pathname + location.search, sep = self.includes('?') ? '&' : '?';
    const GB = 1073741824;
    const fmt = b => { const u = ['B','KB','MB','GB','TB','PB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(2) : b) + ' ' + u[i]; };
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const op = () => root.dataset.op;
    const isAdd = () => op() === 'add';
    let user = null, group = null, t1, t2;

    // ── Ланг: AGS_LANG из PHP; {1} и %1$s ($lang->load() превращает {1} в %1$s) ──
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const PH = /\{(\d+)\}|%(\d+)\$s/g;
    const t = (key, fallback, ...args) => {
        const s = typeof L[key] === 'string' ? L[key] : fallback;
        return s.replace(PH, (m, a, b) => { const v = args[(a || b) - 1]; return v === undefined ? m : String(v); });
    };
    /** Строка перевода как DOM: плейсхолдеры → узлы (Element) или текст; сам перевод — только текстом */
    const tf = (key, fallback, ...parts) => {
        const s = typeof L[key] === 'string' ? L[key] : fallback, f = document.createDocumentFragment();
        let last = 0;
        s.replace(PH, (m, a, b, off) => {
            f.append(s.slice(last, off));
            const p = parts[(a || b) - 1];
            f.append(p === undefined ? m : (p instanceof Node ? p : String(p)));
            last = off + m.length;
            return m;
        });
        f.append(s.slice(last));
        return f;
    };
    const el = (tag, cls, ...kids) => { const n = document.createElement(tag); if (cls) n.className = cls; n.append(...kids); return n; };
    const html = s => { const n = document.createElement('span'); n.innerHTML = s; return n; };   // только серверный HTML (name_html)

    document.querySelector('.um .um-group select')?.classList.add('form-select');

    // ── Режим ──
    function setOp(v) {
        root.dataset.op = v;
        document.querySelectorAll('.um-op-field').forEach(f => { f.value = v; });
        document.querySelectorAll('.um-verb').forEach(s => { s.textContent = v === 'add' ? t('verb_add', 'Add') : t('verb_remove', 'Remove'); });
        $('umHeadIcon').className = 'fa-solid ' + (v === 'add' ? 'fa-cloud-arrow-up' : 'fa-cloud-arrow-down');
        userPreview(); groupRender();
        try { const u = new URL(location.href); u.searchParams.set('op', v); history.replaceState(null, '', u); } catch (e) {}
    }
    document.querySelectorAll('[name="um_op"]').forEach(r => r.addEventListener('change', () => setOp(r.value)));

    // ── Один пользователь ──
    const after = gb => user ? (isAdd() ? user.uploaded + gb * GB : Math.max(0, user.uploaded - gb * GB)) : 0;
    function userPreview() {
        const box = $('umUser');
        if (!user) { box.hidden = true; return; }
        const gb = parseInt($('uploaded').value, 10) || 0;
        box.hidden = false;
        $('umUName').innerHTML = user.name_html;         // экранировано сервером
        $('umUNow').textContent = user.up_h;
        $('umUAfter').textContent = fmt(after(gb));
        $('umUInactive').hidden = user.active;
    }
    $('username').addEventListener('input', () => {
        clearTimeout(t1); user = null; userPreview();
        const v = $('username').value.trim(); if (v.length < 1) return;
        t1 = setTimeout(() => fetch(self + sep + 'lookup=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => { if ($('username').value.trim() === v) { user = d.found ? d : null; userPreview(); } }).catch(() => {}), 300);
    });
    $('uploaded').addEventListener('input', userPreview);
    document.querySelectorAll('.um .um-chips').forEach(b => b.addEventListener('click', e => {
        const c = e.target.closest('.um-chip'); if (!c) return; $(b.dataset.for).value = c.dataset.v; userPreview();
    }));

    // ── Группа ──
    const gSel = () => document.querySelector('#umMass [name="usergroup"]');
    function groupRender() {
        if (!group) return;
        const gb = parseInt($('classamount').value, 10) || 0;
        const box = $('umGStat');
        box.replaceChildren(t('group_active', '{1} active user(s)', group.n.toLocaleString()));
        if (gb) box.append(' · ', tf('group_total', '{1} in total', el('b', 'um-accent', isAdd() ? '+' + group.add_h : '−' + group.remove_h)));
        if (!isAdd()) box.append(' · ', t('group_withup', '{1} with upload', group.withup.toLocaleString()));
    }
    function groupPreview() {
        const s = gSel(), val = s ? s.value : '';
        const real = /^\d+$/.test(val) && val !== '0';
        $('umGWho').textContent = real ? s.options[s.selectedIndex].text : t('all_users', 'All users');
        clearTimeout(t2);
        t2 = setTimeout(() => fetch(self + sep + 'groupstat=' + (real ? val : '0') + '&gb=' + (parseInt($('classamount').value, 10) || 0), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => { group = d; groupRender(); }).catch(() => {}), 250);
    }
    gSel()?.addEventListener('change', groupPreview);
    $('classamount').addEventListener('change', groupPreview);

    // ── Подтверждения (SweetAlert2, запасной вариант — confirm) ──
    async function ask(o) {
        if (!window.Swal) return confirm(o.fallback);
        const r = await Swal.fire({
            titleText: o.title, html: o.html, icon: isAdd() ? 'question' : 'warning',
            showCancelButton: true, reverseButtons: true, focusCancel: !isAdd(),
            confirmButtonText: esc(o.button), cancelButtonText: esc(t('cancel', 'Cancel')),
            confirmButtonColor: isAdd() ? '#16a34a' : '#dc2626', cancelButtonColor: '#6c757d',
        });
        return r.isConfirmed;
    }
    function busy(form) {
        const b = form.querySelector('button[type="submit"]');
        if (b) { b.disabled = true; b.replaceChildren(el('span', 'spinner-border spinner-border-sm me-1'), t('working', 'Working…')); }
        if (window.Swal) Swal.fire({ titleText: t('applying', 'Applying…'), allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
    }
    /** Карточка в диалоге: title / sub — узлы DOM (Node) */
    const card = (icon, title, sub) => {
        const row = el('div', 'd-flex align-items-center gap-3 p-3 rounded-4 mb-2',
            el('i', 'fa-solid ' + icon + ' fa-lg ' + (isAdd() ? 'text-success' : 'text-danger')),
            el('div', '', el('div', 'fw-bold', title), el('small', 'text-body-secondary', sub)));
        row.style.background = isAdd() ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.07)';
        const note = el('div', 'small text-body-secondary',
            el('i', 'fa-solid ' + (isAdd() ? 'fa-clipboard-list' : 'fa-shield-halved') + ' me-1'),
            isAdd() ? t('note_add', 'Noted in mod comment and site log') : t('note_remove', 'Never below zero · noted in mod comment and site log'));
        return el('div', 'text-start', row, note);
    };

    $('umSingle').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        if (!f.checkValidity()) { f.reportValidity(); return; }
        const gb = parseInt($('uploaded').value, 10), who = $('username').value.trim();
        let sub;
        if (user) {
            const res = el('b', '', fmt(after(gb)));
            res.style.color = isAdd() ? '#16a34a' : '#dc2626';
            sub = tf('single_sub', 'Uploaded {1} → {2}', el('b', '', user.up_h), res);
        } else {
            sub = document.createTextNode(t('not_checked', 'User not checked yet'));
        }
        const ok = await ask({
            title: isAdd() ? t('single_title_add', 'Add {1} GB?', gb) : t('single_title_remove', 'Remove {1} GB?', gb),
            html: card(isAdd() ? 'fa-user-plus' : 'fa-user-minus', user ? html(user.name_html) : document.createTextNode(who), sub),
            button: isAdd() ? t('single_btn_add', 'Add {1} GB', gb) : t('single_btn_remove', 'Remove {1} GB', gb),
            fallback: isAdd() ? t('single_fb_add', 'Add {1} GB to {2}?', gb, who) : t('single_fb_remove', 'Remove {1} GB from {2}?', gb, who),
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    $('umMass').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        const gb = $('classamount').value;
        if (gb === '0') {
            window.Swal
                ? Swal.fire({ titleText: t('amount_title', 'Choose an amount'), text: t('amount_text', 'How many GB per user?'), icon: 'info' })
                : alert(t('amount_alert', 'Choose an amount per user.'));
            return;
        }
        const who = $('umGWho').textContent, n = group ? group.n.toLocaleString() : '?';
        const total = group ? (isAdd() ? '+' + group.add_h : '−' + group.remove_h) : '?';
        const ok = await ask({
            title: isAdd() ? t('mass_title_add', 'Add {1} GB to a whole group?', gb) : t('mass_title_remove', 'Remove {1} GB from a whole group?', gb),
            html: card('fa-users', document.createTextNode(who), tf('mass_sub', '{1} user(s) · {2} in total', n, el('b', '', total))),
            button: isAdd() ? t('mass_btn_add', 'Yes, add for {1} user(s)', n) : t('mass_btn_remove', 'Yes, remove for {1} user(s)', n),
            fallback: (isAdd() ? t('mass_fb_add', 'Add {1} GB for every member of {2}?', gb, who) : t('mass_fb_remove', 'Remove {1} GB from every member of {2}?', gb, who))
                + '\n\n' + t('mass_sub', '{1} user(s) · {2} in total', n, total),
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    setOp(op());
    groupPreview();
    if ($('username').value.trim()) $('username').dispatchEvent(new Event('input'));
})();
