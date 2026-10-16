'use strict';

/* Notification center: header bell + notifications.php page.
 * Strings come from window.AGS_NOTIF_LANG (js_* keys of notifications.lang.php). */
(function () {
    if (window.__agsNotifInit) return;
    window.__agsNotifInit = true;

    const LANG = window.AGS_NOTIF_LANG || {};
    const POLL_MS = 60000;

    function t(key, fallback, ...args) {
        let s = Object.prototype.hasOwnProperty.call(LANG, key) ? String(LANG[key]) : String(fallback ?? key);
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    // Swal button labels are HTML — escape lang text
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }

    // ── Header bell ─────────────────────────────────────────────────────────
    function initBell() {
        const root = document.getElementById('agsNotif');
        if (!root) return;

        const ajax    = root.dataset.ajax;
        const postKey = root.dataset.key || '';
        const badge   = document.getElementById('agsNotifBadge');
        const list    = document.getElementById('agsNotifList');
        const markAll = document.getElementById('agsNotifMarkAll');
        let loading   = false;

        function setBadge(n) {
            n = Number(n) || 0;
            badge.textContent = n > 99 ? '99+' : String(n);
            badge.hidden = n <= 0;
            markAll.disabled = n <= 0;
        }

        function showMessage(text) {
            list.replaceChildren(el('div', 'ags-notif-empty', text));
        }

        function renderItems(items) {
            if (!items.length) {
                const box = el('div', 'ags-notif-empty');
                const ic  = el('i', 'fa-regular fa-bell-slash');
                box.append(ic, el('div', '', t('empty', 'No notifications yet')));
                list.replaceChildren(box);
                return;
            }
            const frag = document.createDocumentFragment();
            items.forEach(n => {
                const a = el('a', 'ags-notif-item' + (n.read ? '' : ' is-unread'));
                a.href = n.url;

                const icon = el('span', 'ags-notif-icon text-' + n.color + '-emphasis bg-' + n.color + '-subtle');
                icon.append(el('i', 'fa-solid ' + n.icon));

                const body = el('span', 'ags-notif-body');
                body.append(el('span', 'ags-notif-text', n.text));
                const time = el('span', 'ags-notif-time', n.time);
                time.title = n.date;
                body.append(time);

                a.append(icon, body);
                if (!n.read) a.append(el('span', 'ags-notif-dot'));
                frag.append(a);
            });
            list.replaceChildren(frag);
        }

        async function load() {
            if (loading) return;
            loading = true;
            try {
                const r = await fetch(ajax + '?action=list', { credentials: 'same-origin', cache: 'no-store' });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.error || '');
                setBadge(d.unread);
                renderItems(d.items || []);
            } catch (e) {
                showMessage(e.message || t('load_failed', 'Could not load notifications.'));
            } finally {
                loading = false;
            }
        }

        async function poll() {
            if (document.visibilityState !== 'visible') return;
            try {
                const r = await fetch(ajax + '?action=count', { credentials: 'same-origin', cache: 'no-store' });
                if (!r.ok) return;
                const d = await r.json();
                if (typeof d.unread === 'number') setBadge(d.unread);
            } catch (e) { /* offline — try next time */ }
        }

        markAll.addEventListener('click', async () => {
            markAll.disabled = true;
            try {
                const body = new URLSearchParams({ action: 'read_all', my_post_key: postKey });
                const r = await fetch(ajax, { method: 'POST', body, credentials: 'same-origin' });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.error || '');
                setBadge(0);
                list.querySelectorAll('.ags-notif-item.is-unread').forEach(a => {
                    a.classList.remove('is-unread');
                    a.querySelector('.ags-notif-dot')?.remove();
                });
            } catch (e) {
                markAll.disabled = false;
                const msg = e.message || t('action_failed', 'Something went wrong. Please try again.');
                if (typeof window.showToast === 'function') window.showToast(msg, 'error');
            }
        });

        // Load the list every time the dropdown opens (Bootstrap event)
        root.addEventListener('show.bs.dropdown', load);

        setBadge(Number(badge.hidden ? 0 : parseInt(badge.textContent, 10)) || 0);
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', poll);
    }

    // ── notifications.php: confirm "Delete read" ────────────────────────────
    function initPage() {
        document.querySelectorAll('form[data-ntf-confirm]').forEach(form => {
            form.addEventListener('submit', e => {
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                const submitter = e.submitter;

                const go = () => {
                    form.dataset.confirmed = '1';
                    // keep the clicked button's name/value in the POST
                    if (submitter && submitter.name) {
                        const h = document.createElement('input');
                        h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value;
                        form.append(h);
                    }
                    form.submit();
                };

                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        titleText: t('delete_title', 'Delete read notifications?'),
                        text: t('delete_text', 'All read notifications will be removed. This cannot be undone.'),
                        showCancelButton: true,
                        confirmButtonText: esc(t('btn_delete', 'Delete')),
                        cancelButtonText: esc(t('btn_cancel', 'Cancel')),
                        confirmButtonColor: '#dc3545',
                        reverseButtons: true,
                        focusCancel: true,
                    }).then(r => { if (r.isConfirmed) go(); });
                } else if (confirm(t('delete_title', 'Delete read notifications?'))) {
                    go();
                }
            });
        });
    }

    function init() { initBell(); initPage(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
