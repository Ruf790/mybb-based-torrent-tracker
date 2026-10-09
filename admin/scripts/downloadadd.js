(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const root = $('dm');
    const self = root.dataset.self || '', sep = self.includes('?') ? '&' : '?';
    const GB = 1073741824;
    const fmt = b => { const u = ['B','KB','MB','GB','TB','PB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(2) : b) + ' ' + u[i]; };
    const ratio = (up, down) => down > 0 ? (up / down).toFixed(2) : (up > 0 ? '∞' : '—');
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const op = () => root.dataset.op;
    const isAdd = () => op() === 'add';
    let user = null, group = null, t1, t2;

    // ── Ланг: AGS_LANG выводит PHP перед скриптом; {1} и %1$s ($lang->load() делает из {1} → %1$s) ──
    const L = typeof AGS_LANG === 'object' && AGS_LANG ? AGS_LANG : {};
    const PH = /\{(\d+)\}|%(\d+)\$s/g;
    const str = (k, fb) => typeof L[k] === 'string' ? L[k] : fb;
    /** Строка с подстановкой — результат вставлять только как текст */
    const t = (k, fb, ...args) => str(k, fb).replace(PH, (m, a, b) => { const i = +(a || b) - 1; return i in args ? String(args[i]) : m; });
    /** То же, но во фрагмент: аргументы — строки (станут текстом) или DOM-узлы */
    const tf = (k, fb, ...parts) => {
        const s = str(k, fb), f = document.createDocumentFragment();
        let last = 0, m;
        PH.lastIndex = 0;
        while ((m = PH.exec(s))) {
            f.append(s.slice(last, m.index));
            const p = parts[+(m[1] || m[2]) - 1];
            f.append(p === undefined ? m[0] : p);
            last = PH.lastIndex;
        }
        f.append(s.slice(last));
        return f;
    };
    const el = (tag, cls, ...kids) => { const n = document.createElement(tag); if (cls) n.className = cls; n.append(...kids); return n; };
    const bold = (txt, color) => { const n = el('b', '', String(txt)); if (color) n.style.color = color; return n; };
    /** HTML от сервера (name_html — экранировано сервером), не перевод */
    const serverHtml = html => { const n = document.createElement('span'); n.innerHTML = html; return n; };

    document.querySelector('.dm .dm-group select')?.classList.add('form-select');

    // ── Режим ──
    function setOp(v) {
        root.dataset.op = v;
        document.querySelectorAll('.dm-op-field').forEach(f => { f.value = v; });
        document.querySelectorAll('.dm-verb').forEach(s => {
            const txt = v === 'add' ? s.dataset.add : s.dataset.remove;
            if (txt) s.textContent = txt;
        });
        $('dmHeadIcon').className = 'fa-solid ' + (v === 'add' ? 'fa-cloud-arrow-down' : 'fa-eraser');
        userPreview(); groupRender();
        try { const u = new URL(location.href); u.searchParams.set('op', v); history.replaceState(null, '', u); } catch (e) {}
    }
    document.querySelectorAll('[name="dm_op"]').forEach(r => r.addEventListener('change', () => setOp(r.value)));

    // ── Один пользователь: было → станет, ратио ──
    const after = gb => user ? (isAdd() ? user.downloaded + gb * GB : Math.max(0, user.downloaded - gb * GB)) : 0;
    function userPreview() {
        const box = $('dmUser');
        if (!user) { box.hidden = true; return; }
        const gb = parseInt($('downloaded').value, 10) || 0;
        const nd = after(gb), r0 = ratio(user.uploaded, user.downloaded), r1 = ratio(user.uploaded, nd);
        box.hidden = false;
        $('dmUName').innerHTML = user.name_html;         // экранировано сервером
        $('dmUNow').textContent = user.down_h;
        $('dmUAfter').textContent = fmt(nd);
        $('dmRNow').textContent = r0;
        $('dmRAfter').textContent = r1;
        $('dmRAfter').className = 'v ' + (nd > user.downloaded ? 'r-down' : nd < user.downloaded ? 'r-up' : '');
        $('dmUUp').textContent = user.up_h;
        $('dmUInactive').hidden = user.active;
    }
    $('username').addEventListener('input', () => {
        clearTimeout(t1); user = null; userPreview(); $('dmUMissing').hidden = true;
        const v = $('username').value.trim(); if (v.length < 1) return;
        t1 = setTimeout(() => fetch(self + sep + 'lookup=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if ($('username').value.trim() !== v) return;
                user = d.found ? d : null; $('dmUMissing').hidden = d.found; userPreview();
            }).catch(() => {}), 300);
    });
    $('downloaded').addEventListener('input', userPreview);
    document.querySelectorAll('.dm .dm-chips').forEach(b => b.addEventListener('click', e => {
        const c = e.target.closest('.dm-chip'); if (!c) return; $(b.dataset.for).value = c.dataset.v; userPreview();
    }));

    // ── Группа ──
    const gSel = () => document.querySelector('#dmMass [name="usergroup"]');
    function groupRender() {
        if (!group) return;
        const gb = parseInt($('classamount').value, 10) || 0;
        const st = $('dmGStat');
        st.replaceChildren(t('gstat_active', '{1} active user(s)', group.n.toLocaleString()));
        if (gb) st.append(tf('gstat_total', ', {1} in total', bold(isAdd() ? '+' + group.add_h : '−' + group.remove_h, 'var(--dm-c)')));
        if (!isAdd()) st.append(t('gstat_withdown', ', {1} with download', group.withdown.toLocaleString()));
    }
    function groupPreview() {
        const s = gSel(), val = s ? s.value : '';
        const real = /^\d+$/.test(val) && val !== '0';
        $('dmGWho').textContent = real ? s.options[s.selectedIndex].text : t('all_users', 'All users');
        clearTimeout(t2);
        group = null; $('dmGStat').textContent = t('counting', 'Counting…'); // старые цифры не должны попасть в подтверждение
        t2 = setTimeout(() => fetch(self + sep + 'groupstat=' + (real ? val : '0') + '&gb=' + (parseInt($('classamount').value, 10) || 0), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (gSel()?.value !== val) return; // пока шёл запрос, выбрали другую группу
                group = d; groupRender();
            }).catch(() => { $('dmGStat').textContent = t('stat_failed', 'Could not load statistics'); }), 250);
    }
    gSel()?.addEventListener('change', groupPreview);
    $('classamount').addEventListener('change', groupPreview);

    // ── Подтверждения (SweetAlert2, запасной вариант — confirm) ──
    // titleText/text — как текст; html — DOM-узел; тексты кнопок Swal вставляет как HTML, поэтому esc()
    function say(title, text, icon, alertText) {
        window.Swal ? Swal.fire({ titleText: title, text, icon }) : alert(alertText);
    }
    async function ask(o) {
        if (!window.Swal) return confirm(o.fallback);
        const r = await Swal.fire({
            titleText: o.title, html: o.html, icon: isAdd() ? 'question' : 'warning',
            showCancelButton: true, reverseButtons: true, focusCancel: true,
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
    /** title, sub — строка (текст) или DOM-узел */
    const card = (icon, title, sub) => {
        const box = el('div', 'd-flex align-items-center gap-3 p-3 rounded-4 mb-2',
            el('i', 'fa-solid ' + icon + ' fa-lg ' + (isAdd() ? 'text-success' : 'text-danger')),
            el('div', '', el('div', 'fw-bold', title), el('small', 'text-body-secondary', sub)));
        box.style.background = isAdd() ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.07)';
        const note = isAdd()
            ? el('div', 'small text-body-secondary', el('i', 'fa-solid fa-clipboard-list me-1'), t('note_add', 'Noted in mod comment and site log'))
            : el('div', 'small text-body-secondary', el('i', 'fa-solid fa-shield-halved me-1'), t('note_remove', 'Never below zero, noted in mod comment and site log'));
        return el('div', 'text-start', box, note);
    };

    $('dmSingle').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        if (!f.checkValidity()) { f.reportValidity(); return; }
        if (!$('dmUMissing').hidden) { say(t('user_missing_title', 'User not found'), t('user_missing_text', 'Check the username — it must match exactly.'), 'error', t('user_missing_alert', 'User not found.')); return; }
        if (user && !user.active) { say(t('inactive_title', 'Account is inactive'), t('inactive_text', 'Disabled or unconfirmed accounts can\'t be changed.'), 'error', t('inactive_alert', 'Account is inactive.')); return; }
        if (user && !isAdd() && user.downloaded <= 0) { say(t('nothing_user_title', 'Nothing to remove'), t('nothing_user_text', 'This user has no download.'), 'info', t('nothing_user_text', 'This user has no download.')); return; }
        const gb = parseInt($('downloaded').value, 10), who = $('username').value.trim();
        const col = isAdd() ? '#16a34a' : '#dc2626';
        let sub;
        if (user) {
            sub = document.createDocumentFragment();
            sub.append(
                tf('single_sub_down', 'Downloaded {1} → {2}', bold(user.down_h), bold(fmt(after(gb)), col)),
                document.createElement('br'),
                tf('single_sub_ratio', 'Ratio {1} → {2}', bold(ratio(user.uploaded, user.downloaded)), bold(ratio(user.uploaded, after(gb)))));
        } else {
            sub = t('single_unchecked', 'User not checked yet');
        }
        const ok = await ask({
            title: isAdd() ? t('single_title_add', 'Add {1} GB download?', gb) : t('single_title_remove', 'Remove {1} GB download?', gb),
            html: card(isAdd() ? 'fa-user-plus' : 'fa-user-minus', user ? serverHtml(user.name_html) : who, sub),
            button: isAdd() ? t('single_btn_add', 'Add {1} GB', gb) : t('single_btn_remove', 'Remove {1} GB', gb),
            fallback: isAdd() ? t('single_fallback_add', 'Add {1} GB download to {2}?', gb, who) : t('single_fallback_remove', 'Remove {1} GB download from {2}?', gb, who),
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    $('dmMass').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        const gb = $('classamount').value;
        if (gb === '0') { say(t('mass_choose_title', 'Choose an amount'), t('mass_choose_text', 'How many GB per user?'), 'info', t('mass_choose_alert', 'Choose an amount per user.')); return; }
        if (!group) { say(t('mass_loading_title', 'Still counting…'), t('mass_loading_text', 'Group statistics are loading, try again in a moment.'), 'info', t('mass_loading_alert', 'Group statistics are loading, try again.')); return; }
        const affected = isAdd() ? group.n : group.withdown;
        if (affected === 0) {
            if (isAdd()) say(t('mass_noactive_title', 'No active users'), t('mass_noactive_text', 'This group has no active members to change.'), 'info', t('mass_nothing_alert', 'Nothing to change in this group.'));
            else say(t('mass_nothing_title', 'Nothing to remove'), t('mass_nothing_text', 'No active member of this group has any download.'), 'info', t('mass_nothing_alert', 'Nothing to change in this group.'));
            return;
        }
        const who = $('dmGWho').textContent, n = affected.toLocaleString();
        const total = isAdd() ? '+' + group.add_h : '−' + group.remove_h;
        const ok = await ask({
            title: isAdd() ? t('mass_title_add', 'Add {1} GB download to a whole group?', gb) : t('mass_title_remove', 'Remove {1} GB download from a whole group?', gb),
            html: card('fa-users', who, tf('mass_sub', '{1} user(s), {2} in total', n, bold(total))),
            button: isAdd() ? t('mass_btn_add', 'Yes, add for {1} user(s)', n) : t('mass_btn_remove', 'Yes, remove for {1} user(s)', n),
            fallback: (isAdd() ? t('mass_fallback_add', 'Add {1} GB download for every member of {2}?', gb, who) : t('mass_fallback_remove', 'Remove {1} GB download for every member of {2}?', gb, who))
                + '\n\n' + t('mass_sub', '{1} user(s), {2} in total', n, total),
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    // «Назад» после отправки: страница из bfcache с заблокированной кнопкой и открытым Swal
    addEventListener('pageshow', e => { if (e.persisted) location.reload(); });

    setOp(op());
    groupPreview();
    if ($('username').value.trim()) $('username').dispatchEvent(new Event('input'));
})();
