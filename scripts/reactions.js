/**
 * Smilie reactions on page items (torrent comments). Markup: react_bar() in include/functions_reactions.php,
 * config: window.AGS_REACT from react_assets(). One delegated handler for the whole page, so blocks
 * added later by AJAX (a new comment) work too. The chat has its own copy in chat.js.
 */
(() => {
    'use strict';
    if (window.__agsReactInit) return;
    window.__agsReactInit = true;

    const cfg = () => window.AGS_REACT || null;
    const t = (key, fallback, ...args) => {
        let s = String(cfg()?.lang?.[key] ?? fallback);
        args.forEach((v, i) => { s = s.split('{' + (i + 1) + '}').join(String(v)); });
        return s;
    };
    const el = (tag, cls, text) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    };

    const notify = (msg) => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: msg, showConfirmButton: false, timer: 3500 });
        } else {
            window.alert(msg);
        }
    };

    // ── Rendering (same markup as react_bar()) ──
    const pill = (r) => {
        const b = el('button', 'rx-pill' + (r.mine ? ' is-mine' : ''));
        b.type = 'button';
        b.dataset.key = r.k;
        let who = (r.who || []).join(', ');
        if (r.more > 0) who += ' ' + t('more', 'and {1} more', r.more);
        if (r.mine) who += '\n' + t('remove', 'Click to remove yours');
        b.title = who;
        const img = el('img');
        img.src = r.src;
        img.alt = r.k;
        img.loading = 'lazy';
        b.append(img, el('span', 'rx-n', String(r.n)));
        return b;
    };

    const paint = (bar, list) => {
        bar.querySelectorAll('.rx-pill').forEach((p) => p.remove());
        const add = bar.querySelector('.rx-add');
        list.forEach((r) => bar.insertBefore(pill(r), add));
    };

    // ── Toggle ──
    const busy = new WeakSet();
    const toggle = async (bar, key) => {
        const c = cfg();
        if (!c || busy.has(bar)) return;
        busy.add(bar);
        bar.classList.add('is-busy');
        try {
            const body = new FormData();
            body.append('my_post_key', c.key);
            body.append('type', bar.dataset.rxType);
            body.append('id', bar.dataset.rxId);
            body.append('key', key);
            const res = await fetch(c.endpoint + '?action=react', {
                method: 'POST', body, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (!data.ok) { notify(data.error || 'Error'); return; }
            paint(bar, data.reactions || []);
        } catch (e) {
            notify(t('err_token', 'Security token expired. Reload the page.'));
        } finally {
            busy.delete(bar);
            bar.classList.remove('is-busy');
        }
    };

    // ── Picker ──
    let picker = null;
    let anchor = null;

    const close = () => {
        if (!picker) return;
        picker.remove();
        picker = null;
        anchor = null;
    };

    const place = () => {
        if (!picker || !anchor) return;
        const r  = anchor.getBoundingClientRect();
        const pw = picker.offsetWidth;
        const ph = picker.offsetHeight;
        const left = Math.min(Math.max(8, r.left), window.innerWidth - pw - 8);
        let top = r.top - ph - 6;
        if (top < 8) top = r.bottom + 6;
        picker.style.left = left + 'px';
        picker.style.top  = Math.min(top, window.innerHeight - ph - 8) + 'px';
    };

    const open = (btn) => {
        const c = cfg();
        if (!c) return;
        const again = anchor === btn;
        close();
        if (again) return;

        const bar  = btn.closest('.rx');
        const mine = new Set([...bar.querySelectorAll('.rx-pill.is-mine')].map((p) => p.dataset.key));
        const item = (s) => {
            const b = el('button', 'rx-pick__item' + (mine.has(s.k) ? ' is-mine' : ''));
            b.type = 'button';
            b.title = s.k;
            b.dataset.key = s.k;
            const img = el('img');
            img.src = s.src;
            img.alt = s.k;
            img.loading = 'lazy';
            b.appendChild(img);
            return b;
        };

        picker = el('div', 'rx-pick');
        picker.setAttribute('role', 'dialog');
        const quick = el('div', 'rx-pick__quick');
        (c.quick || []).forEach((s) => quick.appendChild(item(s)));
        picker.appendChild(quick);

        if ((c.all || []).length > (c.quick || []).length) {
            const more = el('button', 'rx-pick__more');
            more.type = 'button';
            more.title = t('all', 'All smilies');
            more.setAttribute('aria-label', more.title);
            more.appendChild(el('i', 'fa-solid fa-plus'));
            quick.appendChild(more);
            more.addEventListener('click', () => {
                more.remove();
                const all = el('div', 'rx-pick__all');
                c.all.forEach((s) => all.appendChild(item(s)));
                picker.appendChild(all);
                place();
            });
        }

        picker.addEventListener('click', (e) => {
            const b = e.target.closest('.rx-pick__item');
            if (!b) return;
            close();
            toggle(bar, b.dataset.key);
        });

        anchor = btn;
        document.body.appendChild(picker);
        place();
    };

    // ── Events ──
    document.addEventListener('click', (e) => {
        const add = e.target.closest('.rx .rx-add');
        if (add) { e.preventDefault(); open(add); return; }
        const p = e.target.closest('.rx .rx-pill');
        if (p) { e.preventDefault(); toggle(p.closest('.rx'), p.dataset.key); }
    });
    document.addEventListener('pointerdown', (e) => {
        if (picker && !picker.contains(e.target) && !e.target.closest('.rx-add')) close();
    }, true);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', close);
    window.addEventListener('scroll', close, { passive: true });
})();
