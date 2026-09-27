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

    document.querySelector('.dm .dm-group select')?.classList.add('form-select');

    // ── Режим ──
    function setOp(v) {
        root.dataset.op = v;
        document.querySelectorAll('.dm-op-field').forEach(f => { f.value = v; });
        document.querySelectorAll('.dm-verb').forEach(s => { s.textContent = v === 'add' ? 'Add' : 'Remove'; });
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
        $('dmGStat').innerHTML = group.n.toLocaleString() + ' active user(s)'
            + (gb ? ', <b style="color:var(--dm-c)">' + (isAdd() ? '+' + esc(group.add_h) : '−' + esc(group.remove_h)) + '</b> in total' : '')
            + (!isAdd() ? ', ' + group.withdown.toLocaleString() + ' with download' : '');
    }
    function groupPreview() {
        const s = gSel(), val = s ? s.value : '';
        const real = /^\d+$/.test(val) && val !== '0';
        $('dmGWho').textContent = real ? s.options[s.selectedIndex].text : 'All users';
        clearTimeout(t2);
        group = null; $('dmGStat').textContent = 'Counting…'; // старые цифры не должны попасть в подтверждение
        t2 = setTimeout(() => fetch(self + sep + 'groupstat=' + (real ? val : '0') + '&gb=' + (parseInt($('classamount').value, 10) || 0), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (gSel()?.value !== val) return; // пока шёл запрос, выбрали другую группу
                group = d; groupRender();
            }).catch(() => { $('dmGStat').textContent = 'Could not load statistics'; }), 250);
    }
    gSel()?.addEventListener('change', groupPreview);
    $('classamount').addEventListener('change', groupPreview);

    // ── Подтверждения (SweetAlert2, запасной вариант — confirm) ──
    async function ask(o) {
        if (!window.Swal) return confirm(o.fallback);
        const r = await Swal.fire({
            title: o.title, html: o.html, icon: isAdd() ? 'question' : 'warning',
            showCancelButton: true, reverseButtons: true, focusCancel: true,
            confirmButtonText: o.button, cancelButtonText: 'Cancel',
            confirmButtonColor: isAdd() ? '#16a34a' : '#dc2626', cancelButtonColor: '#6c757d',
        });
        return r.isConfirmed;
    }
    function busy(form) {
        const b = form.querySelector('button[type="submit"]');
        if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working…'; }
        if (window.Swal) Swal.fire({ title: 'Applying…', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
    }
    const card = (icon, title, sub) => '<div class="text-start"><div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-2" style="background:' + (isAdd() ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.07)') + '">'
        + '<i class="fa-solid ' + icon + ' fa-lg ' + (isAdd() ? 'text-success' : 'text-danger') + '"></i><div><div class="fw-bold">' + title + '</div><small class="text-body-secondary">' + sub + '</small></div></div>'
        + '<div class="small text-body-secondary">' + (isAdd() ? '<i class="fa-solid fa-clipboard-list me-1"></i>Noted in mod comment and site log' : '<i class="fa-solid fa-shield-halved me-1"></i>Never below zero, noted in mod comment and site log') + '</div></div>';

    $('dmSingle').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        if (!f.checkValidity()) { f.reportValidity(); return; }
        if (!$('dmUMissing').hidden) { window.Swal ? Swal.fire({ title: 'User not found', text: 'Check the username — it must match exactly.', icon: 'error' }) : alert('User not found.'); return; }
        if (user && !user.active) { window.Swal ? Swal.fire({ title: 'Account is inactive', text: 'Disabled or unconfirmed accounts can\'t be changed.', icon: 'error' }) : alert('Account is inactive.'); return; }
        if (user && !isAdd() && user.downloaded <= 0) { window.Swal ? Swal.fire({ title: 'Nothing to remove', text: 'This user has no download.', icon: 'info' }) : alert('This user has no download.'); return; }
        const gb = parseInt($('downloaded').value, 10), who = $('username').value.trim(), verb = isAdd() ? 'Add' : 'Remove';
        const col = isAdd() ? '#16a34a' : '#dc2626';
        const sub = user
            ? 'Downloaded <b>' + esc(user.down_h) + '</b> → <b style="color:' + col + '">' + esc(fmt(after(gb))) + '</b><br>Ratio <b>' + esc(ratio(user.uploaded, user.downloaded)) + '</b> → <b>' + esc(ratio(user.uploaded, after(gb))) + '</b>'
            : 'User not checked yet';
        const ok = await ask({
            title: verb + ' ' + gb + ' GB download?',
            html: card(isAdd() ? 'fa-user-plus' : 'fa-user-minus', user ? user.name_html : esc(who), sub),
            button: verb + ' ' + gb + ' GB',
            fallback: verb + ' ' + gb + ' GB download ' + (isAdd() ? 'to ' : 'from ') + who + '?',
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    $('dmMass').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        const gb = $('classamount').value;
        if (gb === '0') { window.Swal ? Swal.fire({ title: 'Choose an amount', text: 'How many GB per user?', icon: 'info' }) : alert('Choose an amount per user.'); return; }
        if (!group) { window.Swal ? Swal.fire({ title: 'Still counting…', text: 'Group statistics are loading, try again in a moment.', icon: 'info' }) : alert('Group statistics are loading, try again.'); return; }
        const affected = isAdd() ? group.n : group.withdown;
        if (affected === 0) { window.Swal ? Swal.fire({ title: isAdd() ? 'No active users' : 'Nothing to remove', text: isAdd() ? 'This group has no active members to change.' : 'No active member of this group has any download.', icon: 'info' }) : alert('Nothing to change in this group.'); return; }
        const who = $('dmGWho').textContent, n = affected.toLocaleString();
        const total = isAdd() ? '+' + group.add_h : '−' + group.remove_h;
        const verb = isAdd() ? 'Add' : 'Remove';
        const ok = await ask({
            title: verb + ' ' + gb + ' GB download ' + (isAdd() ? 'to' : 'from') + ' a whole group?',
            html: card('fa-users', esc(who), esc(n) + ' user(s), <b>' + esc(total) + '</b> in total'),
            button: 'Yes, ' + verb.toLowerCase() + ' for ' + n + ' user(s)',
            fallback: verb + ' ' + gb + ' GB download for every member of ' + who + '?\n\n' + n + ' user(s), ' + total + ' in total',
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    // «Назад» после отправки: страница из bfcache с заблокированной кнопкой и открытым Swal
    addEventListener('pageshow', e => { if (e.persisted) location.reload(); });

    setOp(op());
    groupPreview();
    if ($('username').value.trim()) $('username').dispatchEvent(new Event('input'));
})();