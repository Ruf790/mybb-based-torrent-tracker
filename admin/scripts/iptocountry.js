/* IP to Country — admin page */
(() => {
    'use strict';

    const root = document.querySelector('.itc-page');
    if (!root) return;

    const form   = root.querySelector('#itc-form');
    const input  = root.querySelector('#itc-ip');
    const submit = root.querySelector('#itc-submit');
    const myIp   = root.querySelector('#itc-myip');
    const recent = root.querySelector('#itc-recent');

    /* ---------- loading state ---------- */
    form?.addEventListener('submit', () => {
        input.value = input.value.trim();
        if (!submit) return;
        submit.disabled = true;
        submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Searching…</span>';
    });

    /* ---------- "My IP" ---------- */
    myIp?.addEventListener('click', () => {
        input.value = myIp.dataset.ip || '';
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });

    /* ---------- copy ---------- */
    const copyText = async (text) => {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            ta.remove();
            return ok;
        }
    };

    root.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-copy]');
        if (!btn) return;
        if (!(await copyText(btn.dataset.copy))) return;
        const icon = btn.querySelector('i');
        btn.classList.add('is-done');
        icon.className = 'fa-solid fa-check';
        setTimeout(() => {
            btn.classList.remove('is-done');
            icon.className = 'fa-regular fa-copy';
        }, 1500);
    });

    /* ---------- recent lookups (this browser only) ---------- */
    const KEY = 'itc_recent';
    const MAX = 8;

    const load = () => {
        try {
            const list = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(list)
                ? list.filter(x => x && typeof x.ip === 'string').slice(0, MAX)
                : [];
        } catch {
            return [];
        }
    };
    const save = (list) => {
        try { localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX))); } catch { /* storage off */ }
    };

    const flagEmoji = (cc) => /^[A-Z]{2}$/.test(cc || '')
        ? String.fromCodePoint(...[...cc].map(c => 0x1F1E6 + c.charCodeAt(0) - 65))
        : '🌐';

    const found = root.dataset.lookedUp;
    if (found) {
        save([
            { ip: found, country: root.dataset.country || '', cc: root.dataset.cc || '' },
            ...load().filter(x => x.ip !== found),
        ]);
    }

    const render = () => {
        if (!recent) return;
        const list = load().filter(x => x.ip !== found);
        const box  = recent.querySelector('.itc-recent-list');
        box.replaceChildren();
        recent.hidden = list.length === 0;

        for (const item of list) {
            const url = new URL(form.action, location.href);
            url.searchParams.set('act', 'iptocountry');
            url.searchParams.set('do', '2');
            url.searchParams.set('ip_address', item.ip);

            const a = document.createElement('a');
            a.className = 'itc-chip';
            a.href = url.toString();
            a.title = item.country || item.ip;
            a.textContent = `${flagEmoji(item.cc)} ${item.ip}`;
            box.appendChild(a);
        }
    };

    recent?.querySelector('.itc-recent-clear')?.addEventListener('click', () => {
        save(found ? load().filter(x => x.ip === found) : []);
        render();
    });

    render();
})();
