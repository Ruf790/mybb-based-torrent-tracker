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

    document.querySelector('.um .um-group select')?.classList.add('form-select');

    // ── Режим ──
    function setOp(v) {
        root.dataset.op = v;
        document.querySelectorAll('.um-op-field').forEach(f => { f.value = v; });
        document.querySelectorAll('.um-verb').forEach(s => { s.textContent = v === 'add' ? 'Add' : 'Remove'; });
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
        $('umGStat').innerHTML = group.n.toLocaleString() + ' active user(s)'
            + (gb ? ' · <b class="um-accent">' + (isAdd() ? '+' + esc(group.add_h) : '−' + esc(group.remove_h)) + '</b> in total' : '')
            + (!isAdd() ? ' · ' + group.withup.toLocaleString() + ' with upload' : '');
    }
    function groupPreview() {
        const s = gSel(), val = s ? s.value : '';
        const real = /^\d+$/.test(val) && val !== '0';
        $('umGWho').textContent = real ? s.options[s.selectedIndex].text : 'All users';
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
            title: o.title, html: o.html, icon: isAdd() ? 'question' : 'warning',
            showCancelButton: true, reverseButtons: true, focusCancel: !isAdd(),
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
        + '<div class="small text-body-secondary">' + (isAdd() ? '<i class="fa-solid fa-clipboard-list me-1"></i>Noted in mod comment and site log' : '<i class="fa-solid fa-shield-halved me-1"></i>Never below zero · noted in mod comment and site log') + '</div></div>';

    $('umSingle').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        if (!f.checkValidity()) { f.reportValidity(); return; }
        const gb = parseInt($('uploaded').value, 10), who = $('username').value.trim(), verb = isAdd() ? 'Add' : 'Remove';
        const sub = user ? 'Uploaded <b>' + esc(user.up_h) + '</b> → <b style="color:' + (isAdd() ? '#16a34a' : '#dc2626') + '">' + esc(fmt(after(gb))) + '</b>' : 'User not checked yet';
        const ok = await ask({
            title: verb + ' ' + gb + ' GB?',
            html: card(isAdd() ? 'fa-user-plus' : 'fa-user-minus', user ? user.name_html : esc(who), sub),
            button: verb + ' ' + gb + ' GB',
            fallback: verb + ' ' + gb + ' GB ' + (isAdd() ? 'to ' : 'from ') + who + '?',
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    $('umMass').addEventListener('submit', async e => {
        const f = e.currentTarget; e.preventDefault();
        const gb = $('classamount').value;
        if (gb === '0') { window.Swal ? Swal.fire({ title: 'Choose an amount', text: 'How many GB per user?', icon: 'info' }) : alert('Choose an amount per user.'); return; }
        const who = $('umGWho').textContent, n = group ? group.n.toLocaleString() : '?';
        const total = group ? (isAdd() ? '+' + group.add_h : '−' + group.remove_h) : '?';
        const verb = isAdd() ? 'Add' : 'Remove';
        const ok = await ask({
            title: verb + ' ' + gb + ' GB ' + (isAdd() ? 'to' : 'from') + ' a whole group?',
            html: card('fa-users', esc(who), esc(n) + ' user(s) · <b>' + esc(total) + '</b> in total'),
            button: 'Yes, ' + verb.toLowerCase() + ' for ' + n + ' user(s)',
            fallback: verb + ' ' + gb + ' GB for every member of ' + who + '?\n\n' + n + ' user(s) · ' + total + ' in total',
        });
        if (ok) { busy(f); HTMLFormElement.prototype.submit.call(f); }
    });

    setOp(op());
    groupPreview();
    if ($('username').value.trim()) $('username').dispatchEvent(new Event('input'));
})();
