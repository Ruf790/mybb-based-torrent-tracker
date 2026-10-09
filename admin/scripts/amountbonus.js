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

    // ── Ланг: AGS_LANG выводит PHP перед скриптом (ключи js_* без префикса) ──
    // $lang->load() превращает {1} в %1$s — поддерживаем оба формата
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const PH = /\{(\d+)\}|%(\d+)\$s/g;
    const raw = (key, fb) => (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fb;
    function t(key, fb, ...args) {
        return raw(key, fb).replace(PH, (m, a, b) => {
            const i = parseInt(a || b, 10) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }
    // То же, но в DOM: аргументы оборачиваются в <wrap>, всё вставляется как текст
    function tNode(key, fb, wrap, ...args) {
        const s = raw(key, fb), frag = document.createDocumentFragment();
        let last = 0, m;
        PH.lastIndex = 0;
        while ((m = PH.exec(s)) !== null) {
            frag.append(s.slice(last, m.index));
            const i = parseInt(m[1] || m[2], 10) - 1;
            if (i >= 0 && i < args.length) {
                const el = document.createElement(wrap);
                el.textContent = String(args[i]);
                frag.append(el);
            } else {
                frag.append(m[0]);
            }
            last = PH.lastIndex;
        }
        frag.append(s.slice(last));
        return frag;
    }

    function busy(f) {
        const b = f.querySelector('button[type="submit"]');
        const sp = document.createElement('span');
        sp.className = 'spinner-border spinner-border-sm me-1';
        b.disabled = true; b.replaceChildren(sp, document.createTextNode(t('sending', 'Sending…')));
    }

    // Подтверждение: SweetAlert2, если загружен, иначе confirm()
    function ask(opts) {
        if (typeof window.Swal === 'undefined') return Promise.resolve(window.confirm(opts.plain));
        return Swal.fire({
            icon: opts.icon || 'question',
            title: opts.title,
            html: opts.html,
            showCancelButton: true,
            confirmButtonText: opts.ok || esc(t('yes', 'Yes')),
            cancelButtonText: esc(t('cancel', 'Cancel')),
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
        const who = all ? t('all_who', 'ALL confirmed users') : opt.text.replace(/\s*\(.*$/, '').trim();
        const total = (n * per).toLocaleString();
        const perS = per.toLocaleString(), nS = n.toLocaleString();

        // Тело диалога собирается из DOM-узлов — переводы и ник группы вставляются как текст
        const body = document.createElement('div');
        const undo = document.createElement('div');
        undo.className = 'mt-2 small text-secondary';
        undo.textContent = t('confirm_undo', 'This can\'t be undone automatically.');
        body.append(
            tNode('confirm_body', 'Give {1} points to {2} user(s) in {3}.', 'b', perS, nS, who),
            document.createElement('br'),
            tNode('confirm_total', 'Total: {1} points.', 'b', total),
            undo
        );

        ask({
            icon: all ? 'warning' : 'question',
            title: all ? t('title_all', 'Send to every user?') : t('title_group', 'Distribute points?'),
            html: body,
            plain: t('confirm_body', 'Give {1} points to {2} user(s) in {3}.', perS, nS, who) + '\n' + t('confirm_total', 'Total: {1} points.', total),
            ok: '<i class="fa-solid fa-tower-broadcast me-1"></i>' + esc(t('confirm_ok', 'Distribute')),
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
    let timer, seq = 0;
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
                    $('abUserName').textContent = t('user_not_found', 'User not found'); $('abUserMeta').textContent = t('check_spelling', 'Check the exact spelling');
                    $('abUserBonus').textContent = ''; av.innerHTML = '<i class="fa-solid fa-user-slash"></i>'; return;
                }
                $('abUserName').innerHTML = d.name_html; // экранировано сервером
                $('abUserMeta').textContent = t('user_meta', 'ID {1} · {2}', d.id, d.group);
                $('abUserBonus').innerHTML = '<i class="fa-solid fa-coins me-1"></i>' + Number(d.bonus).toLocaleString();
                av.replaceChildren();
                if (d.avatar) { const i = document.createElement('img'); i.src = d.avatar; i.alt = ''; av.appendChild(i); }
                else av.textContent = (d.username || '?').charAt(0).toUpperCase();
            }).catch(() => {});
    }
    $('abUser').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 300); });
    $('abMe').addEventListener('click', () => { $('abUser').value = me; lookup(); });
    $('abSingle').addEventListener('reset', () => setTimeout(() => { $('abUserCard').hidden = true; }, 0));

    // Итог группового начисления
    function impact() {
        const g = $('abGroup'), n = parseInt(g.options[g.selectedIndex].dataset.n || '0', 10), per = parseInt($('abAmount2').value, 10) || 0;
        $('abN').textContent = n.toLocaleString(); $('abPer').textContent = per.toLocaleString();
        $('abTotal').textContent = per ? t('total', '{1} points in total', (n * per).toLocaleString()) : '—';
        const all = !g.value, w = $('abWarn');
        w.classList.toggle('is-danger', all);
        w.querySelector('i').className = 'fa-solid ' + (all ? 'fa-radiation' : 'fa-triangle-exclamation');
        $('abWarnText').replaceChildren(all
            ? tNode('warn_all', '{1} on the site gets the points.', 'strong', t('warn_all_who', 'Every confirmed user'))
            : t('warn_group', 'Every confirmed member of this group gets the points. It can\'t be undone automatically.'));
        $('abBulkBtn').disabled = n === 0;
    }
    $('abGroup').addEventListener('change', impact);
    $('abAmount2').addEventListener('input', impact);
    impact();
})();