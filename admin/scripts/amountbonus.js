/* Bonus Points Distribution (admin/amountbonus.php)
 * Ждёт на обёртке .ab: data-self (URL страницы), data-me (свой ник) */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const root = document.querySelector('.ab');
    if (!root) return;
    const self = root.dataset.self || location.pathname + location.search;
    const me   = root.dataset.me || '';
    const sep = self.includes('?') ? '&' : '?';

    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function busy(f) {
        const b = f.querySelector('button[type="submit"]');
        b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
    }

    // Подтверждение: SweetAlert2, если загружен, иначе confirm()
    function ask(opts) {
        if (typeof window.Swal === 'undefined') return Promise.resolve(window.confirm(opts.plain));
        return Swal.fire({
            icon: opts.icon || 'question',
            title: opts.title,
            html: opts.html,
            showCancelButton: true,
            confirmButtonText: opts.ok || 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: opts.color || '#0d6efd',
            reverseButtons: true,
            focusCancel: true
        }).then(r => r.isConfirmed);
    }

    // Валидация + подтверждение массовой раздачи + спиннер
    document.querySelectorAll('.ab .needs-validation').forEach(f => f.addEventListener('submit', e => {
        if (!f.checkValidity()) { e.preventDefault(); e.stopPropagation(); f.classList.add('was-validated'); return; }
        if (f.id !== 'abBulk') { busy(f); return; }

        e.preventDefault();
        const g = $('abGroup'), opt = g.options[g.selectedIndex];
        const n = parseInt(opt.dataset.n || '0', 10), per = parseInt($('abAmount2').value, 10) || 0;
        const all = !g.value;
        const who = all ? 'ALL confirmed users' : opt.text.replace(/\s*\(.*$/, '').trim();
        const total = (n * per).toLocaleString();

        ask({
            icon: all ? 'warning' : 'question',
            title: all ? 'Send to every user?' : 'Distribute points?',
            html: 'Give <b>' + per.toLocaleString() + '</b> points to <b>' + n.toLocaleString() + '</b> user(s) in <b>' + esc(who) + '</b>.'
                + '<br>Total: <b>' + total + '</b> points.'
                + '<div class="mt-2 small text-secondary">This can\'t be undone automatically.</div>',
            plain: 'Give ' + per.toLocaleString() + ' points to ' + n.toLocaleString() + ' user(s) in: ' + who + '?\nTotal: ' + total + ' points.',
            ok: '<i class="fa-solid fa-tower-broadcast me-1"></i>Distribute',
            color: all ? '#dc3545' : '#f59e0b'
        }).then(yes => {
            if (!yes) return;
            busy(f);
            f.submit(); // submit() не вызывает событие submit повторно
        });
    }));

    // Быстрые суммы
    document.querySelectorAll('.ab .ab-chips').forEach(box => box.addEventListener('click', e => {
        const c = e.target.closest('.ab-chip'); if (!c) return;
        const i = $(box.dataset.target); i.value = c.dataset.v; i.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    // Карточка пользователя: текущий баланс
    let t, seq = 0;
    function lookup() {
        const v = $('abUser').value.trim(), card = $('abUserCard');
        if (!v) { card.hidden = true; return; }
        const my = ++seq;
        fetch(self + sep + 'lookup=' + encodeURIComponent(v), { credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (my !== seq) return;
                card.hidden = false;
                const av = $('abUserAv');
                if (!d.found) {
                    $('abUserName').textContent = 'User not found'; $('abUserMeta').textContent = 'Check the exact spelling';
                    $('abUserBonus').textContent = ''; av.innerHTML = '<i class="fa-solid fa-user-slash"></i>'; return;
                }
                $('abUserName').innerHTML = d.name_html; // экранировано сервером
                $('abUserMeta').textContent = 'ID ' + d.id + ' · ' + d.group;
                $('abUserBonus').innerHTML = '<i class="fa-solid fa-coins me-1"></i>' + Number(d.bonus).toLocaleString();
                av.replaceChildren();
                if (d.avatar) { const i = document.createElement('img'); i.src = d.avatar; i.alt = ''; av.appendChild(i); }
                else av.textContent = (d.username || '?').charAt(0).toUpperCase();
            }).catch(() => {});
    }
    $('abUser').addEventListener('input', () => { clearTimeout(t); t = setTimeout(lookup, 300); });
    $('abMe').addEventListener('click', () => { $('abUser').value = me; lookup(); });
    $('abSingle').addEventListener('reset', () => setTimeout(() => { $('abUserCard').hidden = true; }, 0));

    // Итог группового начисления
    function impact() {
        const g = $('abGroup'), n = parseInt(g.options[g.selectedIndex].dataset.n || '0', 10), per = parseInt($('abAmount2').value, 10) || 0;
        $('abN').textContent = n.toLocaleString(); $('abPer').textContent = per.toLocaleString();
        $('abTotal').textContent = per ? (n * per).toLocaleString() + ' points in total' : '—';
        const all = !g.value, w = $('abWarn');
        w.classList.toggle('is-danger', all);
        w.querySelector('i').className = 'fa-solid ' + (all ? 'fa-radiation' : 'fa-triangle-exclamation');
        $('abWarnText').innerHTML = all ? '<strong>Every confirmed user</strong> on the site gets the points.' : 'Every confirmed member of this group gets the points. It can\'t be undone automatically.';
        $('abBulkBtn').disabled = n === 0;
    }
    $('abGroup').addEventListener('change', impact);
    $('abAmount2').addEventListener('input', impact);
    impact();
})();