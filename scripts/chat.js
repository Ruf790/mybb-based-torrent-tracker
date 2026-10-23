/* AJAX chat: polling, history, replies, presence, pin, edit / delete / ban, private dialogs.
   Box markup: chat_render_box() in include/functions_chat.php

   Every conversation is a "view": the public chat (peer 0) and one per private dialog
   (peer = the other user's id). Each view has its own message list, scroll state and
   history paging; one poll brings the public chat, the open dialog and the unread
   counters of all the other dialogs. */
(() => {
    'use strict';

    const NEAR_BOTTOM_PX = 80;
    const NEAR_TOP_PX    = 60;
    const MAX_BACKOFF_MS = 60000;
    const GOTO_MAX_PAGES = 10;            // jumping to a quoted message loads at most this many history pages
    const SOUND_KEY      = 'agsChatSound';
    const TOOLS_KEY      = 'agsChatTools';     // formatting toolbar shown or hidden
    const DMS_KEY        = 'agsChatDms';       // open private tabs [{uid, name}]
    const TAB_KEY        = 'agsChatTab';       // the tab that was open (peer id, 0 = public)
    const DM_TABS_MAX    = 8;                  // more open dialogs than this: the oldest tab closes
    const JUMBO_MAX      = 8;                  // up to this many smilies / emoji alone = shown big

    // ── storage that may throw (private mode, blocked cookies) ──
    const store = {
        get(k) { try { return window.localStorage.getItem(k); } catch { return null; } },
        set(k, v) { try { window.localStorage.setItem(k, v); } catch { /* ignore */ } },
    };

    // ── a short two-tone "ping", no sound file needed ──
    let audio = null;
    const unlockAudio = () => {
        if (audio) return;
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (Ctx) { try { audio = new Ctx(); } catch { audio = null; } }
    };
    ['pointerdown', 'keydown'].forEach((ev) => document.addEventListener(ev, unlockAudio, { once: true, capture: true }));

    const ping = () => {
        if (!audio) return;                      // browsers allow sound only after a click / key press
        if (audio.state === 'suspended') audio.resume().catch(() => {});
        const now = audio.currentTime;
        [[880, 0], [1320, 0.11]].forEach(([freq, at]) => {
            const osc  = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, now + at);
            gain.gain.exponentialRampToValueAtTime(0.18, now + at + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + at + 0.16);
            osc.connect(gain).connect(audio.destination);
            osc.start(now + at);
            osc.stop(now + at + 0.18);
        });
    };

    const initBox = (box) => {
        let cfg;
        try { cfg = JSON.parse(box.getAttribute('data-chat') || '{}'); } catch { return; }

        const L = cfg.lang || {};
        const t = (key, fallback, ...args) =>
            String(L[key] ?? fallback).replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
                const v = args[Number(a || b) - 1];
                return v === undefined ? m : String(v);
            });

        const $ = (sel) => box.querySelector(sel);
        const form      = $('[data-ch-form]');
        const input     = $('[data-ch-input]');
        const send      = $('[data-ch-send]');
        const status    = $('[data-ch-status]');
        const newer     = $('[data-ch-newer]');
        const banned    = $('[data-ch-banned]');
        const onlineBtn = $('[data-ch-online]');
        const onlineNum = $('[data-ch-online-count]');
        const soundBtn  = $('[data-ch-sound]');
        const pinBox    = $('[data-ch-pin]');
        const pinBody   = $('[data-ch-pin-body]');
        const pinEdit   = $('[data-ch-pin-edit]');
        const pinRemove = $('[data-ch-pin-remove]');
        const bansBtn   = $('[data-ch-bans]');
        const replyBar  = $('[data-ch-replybar]');
        const replyText = $('[data-ch-replybar-text]');
        const replyX    = $('[data-ch-reply-cancel]');
        const toolsBtn  = $('[data-ch-tools]');
        const tabsDms   = $('[data-ch-tabs-dms]');
        const pubTab    = $('[data-ch-tab="0"]');
        const newDmBtn  = $('[data-ch-dm-new]');
        const prefsBtn  = $('[data-ch-dm-prefs]');
        const dmBar     = $('[data-ch-dmbar]');
        const dmBarText = $('[data-ch-dmbar-text]');
        const dmBlock   = $('[data-ch-dm-block]');
        const reportsEl = $('[data-ch-reports]');

        let timer  = null;
        let fails  = 0;
        let loading = false;
        let again   = false;        // a poll was asked for while one was running: run another right after
        let sending = false;
        let statusTimer = null;
        let pinTs = 0;
        let pinRaw = '';
        let replyTo = null;         // { id, name, snippet }
        let unread = 0;             // public messages that arrived while the browser tab was hidden
        let dmTotal = 0;            // unread private messages (all dialogs)
        let isBanned = false;
        let blocked = new Set();    // users I blocked
        let soundOn = store.get(SOUND_KEY) !== '0';
        const baseTitle = document.title;
        const placeholder = input.getAttribute('placeholder') || '';

        // ── local time (the viewer's time zone, not the server's) ──
        const pad = (n) => String(n).padStart(2, '0');
        const dayKey = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        const fmtTime = (d) => pad(d.getHours()) + ':' + pad(d.getMinutes());
        const fmtDate = (d) => pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
        const dayLabel = (d) => {
            const today = new Date();
            const yest  = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
            if (dayKey(d) === dayKey(today)) return t('today', 'Today');
            if (dayKey(d) === dayKey(yest))  return t('yesterday', 'Yesterday');
            return fmtDate(d);
        };

        // ── helpers ──────────────────────────────────────

        const el = (tag, cls, text) => {
            const e = document.createElement(tag);
            if (cls) e.className = cls;
            if (text !== undefined) e.textContent = text;
            return e;
        };

        const setStatus = (text, tone = 'info', ms = 4000) => {
            status.textContent = text || '';
            status.dataset.tone = tone;
            clearTimeout(statusTimer);
            if (ms > 0) statusTimer = setTimeout(() => { status.textContent = ''; }, ms);
        };

        const swal = () => window.Swal && typeof window.Swal.fire === 'function';

        const get = async (action, params = {}) => {
            const qs = new URLSearchParams({ action, ...params }).toString();
            const res = await fetch(cfg.endpoint + '?' + qs, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'error');
            return data;
        };

        const post = async (action, data) => {
            const body = new FormData();
            body.append('my_post_key', cfg.key);
            Object.entries(data).forEach(([k, v]) => body.append(k, String(v)));
            const res = await fetch(cfg.endpoint + '?action=' + encodeURIComponent(action), {
                method: 'POST', body, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        };

        const ask = (opts, fallback) => {
            if (swal()) {
                return window.Swal.fire({
                    showCancelButton: true, reverseButtons: true, focusCancel: !opts.input,
                    cancelButtonText: t('cancel', 'Cancel'), ...opts,
                });
            }
            return Promise.resolve(fallback());
        };

        const badge = (node, n) => {
            if (!node) return;
            node.hidden = !(n > 0);
            node.textContent = n > 99 ? '99+' : String(n || '');
        };

        // ── views: the public chat + one per private dialog ──

        const views = new Map();               // peer id → view
        const pubList = $('[data-ch-list]');

        const makeList = () => {
            const list = el('div', 'ch-list');
            list.setAttribute('role', 'log');
            list.setAttribute('aria-live', 'polite');
            const older = el('div', 'ch-older');
            older.hidden = true;
            older.appendChild(el('i', 'fa-solid fa-spinner fa-spin'));
            const empty = el('div', 'ch-empty');
            empty.append(el('i', 'fa-solid fa-spinner fa-spin'), t('loading', 'Loading…'));
            list.append(older, empty);
            list.hidden = true;
            // after the last list, so the form stays below all of them
            const lists = box.querySelectorAll('.ch-list');
            lists[lists.length - 1].after(list);
            return list;
        };

        const makeView = (peer, list, name = '') => {
            const v = {
                peer, list, name,
                older: list.querySelector('.ch-older'),
                empty: list.querySelector('.ch-empty'),
                lastId: 0, oldestId: 0, hasMore: false, since: 0,
                ready: false,          // first page shown: from now on new messages count as "unread" / ping
                atBottom: true,        // user is at the bottom: keep it there when pictures finish loading
                olderPromise: null,
                tab: null, tabBadge: null, unread: 0, gone: false,
            };
            views.set(peer, v);
            bindList(v);
            return v;
        };

        const pub = makeView(0, pubList);
        pub.tab = pubTab;
        pub.tabBadge = pubTab?.querySelector('[data-ch-tab-badge]') || null;
        let cur = pub;

        const nearBottom = (v) => v.list.scrollHeight - v.list.scrollTop - v.list.clientHeight < NEAR_BOTTOM_PX;
        const toBottom   = (v = cur) => { v.list.scrollTop = v.list.scrollHeight; if (v === cur) newer.hidden = true; v.atBottom = true; };
        const viewOf     = (node) => { for (const v of views.values()) if (v.list === node.closest('.ch-list')) return v; return null; };

        // ── tab title + sound ────────────────────────────

        const updateTitle = () => {
            const n = unread + dmTotal;
            document.title = n > 0 ? '(' + n + ') ' + baseTitle : baseTitle;
        };

        const paintSound = () => {
            if (!soundBtn) return;
            const i = soundBtn.querySelector('i');
            i.className = 'fa-solid ' + (soundOn ? 'fa-volume-high' : 'fa-volume-xmark');
            soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
            soundBtn.classList.toggle('is-off', !soundOn);
            const title = soundOn ? t('sound_on', 'Sound on mention: on') : t('sound_off', 'Sound on mention: off');
            soundBtn.title = title;
            soundBtn.setAttribute('aria-label', title);
        };

        soundBtn?.addEventListener('click', () => {
            soundOn = !soundOn;
            store.set(SOUND_KEY, soundOn ? '1' : '0');
            paintSound();
            if (soundOn) { unlockAudio(); ping(); }
        });
        paintSound();

        // ── formatting toolbar: hidden until asked for, choice remembered ──
        let toolsOn = store.get(TOOLS_KEY) === '1';
        const paintTools = () => {
            box.classList.toggle('is-tools', toolsOn);
            toolsBtn?.setAttribute('aria-pressed', toolsOn ? 'true' : 'false');
            toolsBtn?.classList.toggle('is-on', toolsOn);
        };
        toolsBtn?.addEventListener('click', () => {
            toolsOn = !toolsOn;
            store.set(TOOLS_KEY, toolsOn ? '1' : '0');
            paintTools();
            input.focus();
        });
        paintTools();

        // ── rendering ────────────────────────────────────

        const button = (icon, title, act) => {
            const b = el('button', 'ch-act');
            b.type = 'button';
            b.dataset.act = act;
            b.title = title;
            b.setAttribute('aria-label', title);
            b.appendChild(el('i', 'fa-solid ' + icon));
            return b;
        };

        const avatar = (m) => {
            const a = el('span', 'ch-avatar');
            a.setAttribute('aria-hidden', 'true');
            if (m.avatar) {
                const img = el('img');
                img.src = m.avatar;
                img.alt = '';
                img.loading = 'lazy';
                img.addEventListener('error', () => { img.remove(); a.textContent = (m.name || '?').charAt(0).toUpperCase(); });
                a.appendChild(img);
            } else {
                a.textContent = (m.name || '?').charAt(0).toUpperCase();
            }
            return a;
        };

        const quote = (r) => {
            const q = el('button', 'ch-quote' + (r.gone ? ' is-gone' : ''));
            q.type = 'button';
            q.dataset.goto = String(r.id);
            q.title = t('goto_reply', 'Go to the message');
            q.appendChild(el('i', 'fa-solid fa-reply'));
            if (r.name) q.appendChild(el('strong', '', r.name));
            q.appendChild(el('span', 'ch-quote__text', r.gone ? t('reply_gone', 'message deleted') : r.text));
            return q;
        };

        // Only smilies / emoji (no text, at most JUMBO_MAX): shown big, like in messengers
        const EMOJI_ONLY = /^(?:\p{Extended_Pictographic}|\p{Emoji_Modifier}|\p{Regional_Indicator}|️|‍)+$/u;
        const isJumbo = (body) => {
            const imgs = [...body.querySelectorAll('img')];
            if (imgs.some((i) => !i.classList.contains('smilie') && !/\/smilies\//.test(i.getAttribute('src') || ''))) return false;
            const text = body.textContent.replace(/\s+/g, '');
            if (!imgs.length && !text) return false;
            if (text && !EMOJI_ONLY.test(text)) return false;
            const emojis = text ? [...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(text)].length : 0;
            return imgs.length + emojis <= JUMBO_MAX;
        };

        const build = (m) => {
            const when  = new Date(m.ts * 1000);
            const tTime = fmtTime(when);
            const tFull = fmtDate(when) + ' ' + tTime;
            const li = el('div', 'ch-msg' + (m.mine ? ' is-mine' : '') + (m.mention ? ' is-mention' : ''));
            li.dataset.id = String(m.id);
            li.dataset.uid = String(m.uid);
            li.dataset.name = m.name || '';
            li.dataset.ts = String(m.ts);
            li.dataset.day = dayKey(when);
            li.dataset.snippet = m.snippet || '';
            if (m.raw !== undefined) li.dataset.raw = m.raw;

            const meta = el('div', 'ch-meta');
            const time = el('time', 'ch-time', tTime);
            time.title = tFull;
            time.dateTime = when.toISOString();
            meta.appendChild(time);

            const who = el('span', 'ch-who');
            who.innerHTML = m.user;              // built and escaped server-side (format_name)
            meta.appendChild(who);

            if (m.edited) meta.appendChild(el('span', 'ch-edited', t('edited', 'edited')));

            const acts = el('span', 'ch-acts');
            acts.appendChild(button('fa-reply', t('reply', 'Reply'), 'reply'));
            if (!m.dm && !m.mine && m.uid > 0 && m.uid !== cfg.meId) acts.appendChild(button('fa-envelope', t('dm_write', 'Write privately'), 'dm'));
            if (m.can_edit)        acts.appendChild(button('fa-pen', t('edit', 'Edit'), 'edit'));
            if (m.can_delete)      acts.appendChild(button('fa-trash', t('delete', 'Delete'), 'delete'));
            if (m.can_report)      acts.appendChild(button('fa-flag', t('report', 'Report to staff'), 'report'));
            if (cfg.mod && !m.dm)  acts.appendChild(button('fa-thumbtack', t('pin', 'Pin'), 'pin'));
            if (cfg.mod && !m.dm && !m.mine) acts.appendChild(button('fa-ban', t('ban', 'Ban from chat'), 'ban'));
            meta.appendChild(acts);

            const content = el('div', 'ch-content');
            content.appendChild(meta);
            if (m.reply) content.appendChild(quote(m.reply));

            const body = el('div', 'ch-body');
            body.innerHTML = m.html;             // output of postParser: HTML disabled, BBCode and smilies only
            if (isJumbo(body)) body.classList.add('is-jumbo');
            content.appendChild(body);

            // Short time on the left of continued messages (shown on hover)
            const side = el('time', 'ch-side-time', tTime);
            side.title = tFull;

            li.append(avatar(m), side, content);
            return li;
        };

        /**
         * Day separators ("Today", "Yesterday", date) and grouping: a message by the same
         * author within cfg.group seconds of the previous one hides avatar and name.
         * Rebuilt over the whole list, so edits, deletes and history stay right.
         */
        const regroup = (v) => {
            v.list.querySelectorAll('.ch-day').forEach((d) => d.remove());
            let prev = null;
            v.list.querySelectorAll('.ch-msg').forEach((m) => {
                if (!prev || prev.dataset.day !== m.dataset.day) {
                    const sep = el('div', 'ch-day');
                    sep.appendChild(el('span', '', dayLabel(new Date(Number(m.dataset.ts) * 1000))));
                    m.before(sep);
                    prev = null;
                }
                const cont = prev !== null
                    && (cfg.group ?? 0) > 0
                    && prev.dataset.uid === m.dataset.uid
                    && !m.querySelector('.ch-quote')        // a reply always shows who wrote it
                    && Number(m.dataset.ts) - Number(prev.dataset.ts) <= cfg.group;
                m.classList.toggle('is-cont', cont);
                prev = m;
            });
        };

        const refreshEmpty = (v) => {
            v.empty.hidden = v.list.querySelector('.ch-msg') !== null;
            if (!v.empty.hidden) {
                v.empty.textContent = v.peer === 0
                    ? t('empty', 'No messages yet. Say hello!')
                    : t('dm_empty', 'No messages yet. Only you two will see this dialog.');
            }
        };

        const apply = (v, messages, own = false) => {
            const stick = own || nearBottom(v) || v.list.hidden;
            const fresh = [];

            messages.forEach((m) => {
                if (!m) return;
                const old = v.list.querySelector('.ch-msg[data-id="' + m.id + '"]');
                if (m.deleted) {
                    old?.remove();
                } else if (old) {
                    old.replaceWith(build(m));
                } else if (m.id > v.lastId) {
                    v.list.appendChild(build(m));
                    fresh.push(m);
                }
                if (m.id > v.lastId) v.lastId = m.id;
                if (!m.deleted && (v.oldestId === 0 || m.id < v.oldestId)) v.oldestId = m.id;
            });

            regroup(v);
            refreshEmpty(v);

            if (fresh.length) {
                if (stick) toBottom(v);
                else if (v === cur) newer.hidden = false;

                if (v.ready && !own) {
                    const others = fresh.filter((m) => !m.mine);
                    if (v.peer === 0) {
                        // Unread counter in the browser tab title + ping on a mention / reply to me
                        if (others.length && document.hidden) { unread += others.length; updateTitle(); }
                        if (soundOn && others.some((m) => m.mention)) ping();
                        // Reading a private dialog: the public tab gets a badge instead
                        if (others.length && cur !== pub) { pub.unread += others.length; badge(pub.tabBadge, pub.unread); }
                    } else if (others.length && soundOn) {
                        ping();                    // a private message in the open dialog
                    }
                }
            }
        };

        const setBanned = (text) => {
            isBanned = !!text;
            banned.hidden = !text;
            banned.textContent = text || '';
            paintInput();
            if (text) clearReply();
        };

        // Input on / off: chat ban, or a dialog with an account that is gone
        const paintInput = () => {
            const off = isBanned || (cur.peer > 0 && cur.gone);
            input.disabled = off;
            send.disabled = off;
        };

        // ── history (scroll up) ──────────────────────────

        const loadOlder = (v) => {
            if (v.olderPromise) return v.olderPromise;
            if (!v.hasMore || v.oldestId === 0) return Promise.resolve(false);

            v.older.hidden = false;
            v.olderPromise = (async () => {
                try {
                    const params = { before: v.oldestId };
                    if (v.peer) params.dm = v.peer;
                    const data = await get('chat_history', params);
                    const msgs = data.messages || [];
                    v.hasMore = !!data.more;

                    // Keep what the user is looking at in place while content is added above it
                    const prevHeight = v.list.scrollHeight;
                    const prevTop    = v.list.scrollTop;
                    const anchor     = v.list.querySelector('.ch-msg');
                    const frag       = document.createDocumentFragment();
                    msgs.forEach((m) => {
                        if (m.deleted || v.list.querySelector('.ch-msg[data-id="' + m.id + '"]')) return;
                        frag.appendChild(build(m));
                        if (m.id < v.oldestId) v.oldestId = m.id;
                    });
                    if (anchor) anchor.before(frag); else v.list.appendChild(frag);
                    regroup(v);
                    refreshEmpty(v);
                    v.list.scrollTop = prevTop + (v.list.scrollHeight - prevHeight);
                    return msgs.length > 0;
                } catch {
                    setStatus(t('load_error', 'Could not load the chat.'), 'danger');
                    return false;
                } finally {
                    v.older.hidden = true;
                    v.olderPromise = null;
                }
            })();
            return v.olderPromise;
        };

        // Short history and no scrollbar yet: fill the box so scrolling up becomes possible
        const fillUp = async (v) => {
            for (let i = 0; i < 3 && v.hasMore && !v.list.hidden && v.list.scrollHeight <= v.list.clientHeight + 10; i++) {
                if (!(await loadOlder(v))) break;
                toBottom(v);
            }
        };

        const gotoMessage = async (v, id) => {
            let target = v.list.querySelector('.ch-msg[data-id="' + id + '"]');
            for (let i = 0; !target && v.hasMore && id < v.oldestId && i < GOTO_MAX_PAGES; i++) {
                if (!(await loadOlder(v))) break;
                target = v.list.querySelector('.ch-msg[data-id="' + id + '"]');
            }
            if (!target) { setStatus(t('reply_missing', 'That message is no longer available.'), 'danger'); return; }
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            target.classList.remove('is-flash');
            void target.offsetWidth;                // restart the animation
            target.classList.add('is-flash');
        };

        // ── pinned message (public chat only) ────────────

        const paintPin = () => { pinBox.hidden = pinTs === 0 || cur !== pub; };

        const setPin = (p) => {
            if (!p) return;
            pinTs = Number(p.ts) || 0;
            pinRaw = p.raw || '';
            if (pinTs === 0) pinBody.textContent = '';
            else pinBody.innerHTML = p.html;     // output of postParser
            paintPin();
        };

        const savePin = async (text) => {
            const data = await post('chat_pin', { text });
            if (!data.ok) { setStatus(data.error, 'danger'); return; }
            setPin(data.pin);
            setStatus(data.info, 'success');
        };

        pinEdit?.addEventListener('click', async () => {
            const r = await ask(
                { title: t('pin_title', 'Pinned message'), input: 'textarea', inputValue: pinRaw,
                  inputAttributes: { maxlength: String(cfg.max) }, confirmButtonText: t('save', 'Save') },
                () => { const v = window.prompt(t('pin_title', 'Pinned message'), pinRaw); return { isConfirmed: v !== null, value: v }; }
            );
            if (r.isConfirmed) savePin(String(r.value || '').trim()).catch(() => setStatus(t('send_error', 'Not sent, try again.'), 'danger'));
        });

        pinRemove?.addEventListener('click', async () => {
            const r = await ask(
                { title: t('unpin_title', 'Remove the pinned message?'), icon: 'warning', confirmButtonText: t('unpin', 'Unpin'), confirmButtonColor: '#dc3545' },
                () => ({ isConfirmed: window.confirm(t('unpin_title', 'Remove the pinned message?')) })
            );
            if (r.isConfirmed) savePin('').catch(() => setStatus(t('send_error', 'Not sent, try again.'), 'danger'));
        });

        // ── tabs ─────────────────────────────────────────

        const saveTabs = () => {
            const list = [...views.values()].filter((v) => v.peer > 0).map((v) => ({ uid: v.peer, name: v.name }));
            store.set(DMS_KEY, JSON.stringify(list));
            store.set(TAB_KEY, String(cur.peer));
        };

        const paintTab = (v) => {
            if (!v.tab) return;
            v.tab.querySelector('.ch-tab__name').textContent = v.name || '…';
            v.tab.title = t('dm_tab', 'Private dialog with {1}', v.name || '…');
        };

        const addTab = (v) => {
            const tab = el('button', 'ch-tab ch-tab--dm');
            tab.type = 'button';
            tab.dataset.chTab = String(v.peer);
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-selected', 'false');
            tab.append(el('i', 'fa-solid fa-lock'), el('span', 'ch-tab__name'));
            const b = el('span', 'ch-badge');
            b.hidden = true;
            tab.appendChild(b);
            const x = el('span', 'ch-tab__x');
            x.setAttribute('role', 'button');
            x.title = t('dm_close', 'Close the tab');
            x.setAttribute('aria-label', t('dm_close', 'Close the tab'));
            x.dataset.close = '1';
            x.appendChild(el('i', 'fa-solid fa-xmark'));
            tab.appendChild(x);
            tabsDms.appendChild(tab);
            v.tab = tab;
            v.tabBadge = b;
            paintTab(v);
        };

        const paintDmBar = () => {
            dmBar.hidden = cur === pub;
            if (cur === pub) return;
            dmBarText.textContent = cur.gone
                ? t('dm_gone', 'This account is not available.')
                : t('dm_bar', 'Private dialog with {1}: only you two see it.', cur.name || '…');
            const isBlocked = blocked.has(cur.peer);
            dmBlock.hidden = cur.gone;
            dmBlock.classList.toggle('is-on', isBlocked);
            dmBlock.querySelector('span').textContent = isBlocked ? t('unblock', 'Unblock') : t('block', 'Block');
            dmBlock.title = isBlocked
                ? t('unblock_tip', '{1} can write to you privately again', cur.name)
                : t('block_tip', '{1} will not be able to write to you privately', cur.name);
        };

        const switchTo = (v) => {
            if (v === cur) { input.focus(); return; }
            cur.list.hidden = true;
            cur.tab?.classList.remove('is-active');
            cur.tab?.setAttribute('aria-selected', 'false');
            cur = v;
            v.list.hidden = false;
            v.tab?.classList.add('is-active');
            v.tab?.setAttribute('aria-selected', 'true');
            v.unread = 0;
            badge(v.tabBadge, 0);
            newer.hidden = true;
            clearReply();
            paintPin();
            paintDmBar();
            paintInput();
            input.setAttribute('placeholder', v.peer ? t('ph_dm', 'Private message to {1}…', v.name || '…') : placeholder);
            box.classList.toggle('is-dm', v.peer > 0);
            if (v.atBottom) toBottom(v);
            saveTabs();
            kick();                          // load the dialog / mark it read right away
            input.focus();
        };

        const closeTab = (v) => {
            if (v === pub) return;
            if (cur === v) switchTo(pub);
            v.tab?.remove();
            v.list.remove();
            views.delete(v.peer);
            saveTabs();
        };

        /** Open (and by default show) the dialog with peer = { uid, name }. */
        const openDm = (peer, show = true) => {
            const id = Number(peer.uid);
            if (!id || id === cfg.meId) return null;
            let v = views.get(id);
            if (!v) {
                // Too many tabs: close the oldest one that is not open and has nothing unread
                const dms = [...views.values()].filter((x) => x.peer > 0);
                if (dms.length >= DM_TABS_MAX) {
                    const old = dms.find((x) => x !== cur && !x.unread);
                    if (old) closeTab(old);
                }
                v = makeView(id, makeList(), String(peer.name || ''));
                addTab(v);
                saveTabs();
            } else if (peer.name && !v.name) {
                v.name = String(peer.name);
                paintTab(v);
            }
            if (show) switchTo(v);
            return v;
        };

        box.querySelector('[data-ch-tabs]')?.addEventListener('click', (e) => {
            const tab = e.target.closest('.ch-tab[data-ch-tab]');
            if (!tab) return;
            const v = views.get(Number(tab.dataset.chTab));
            if (!v) return;
            if (e.target.closest('[data-close]')) { closeTab(v); return; }
            switchTo(v);
        });

        // Unread private dialogs from the poll: badges, auto-opened tabs, sound
        const applyDms = (dms) => {
            let total = 0;
            const seen = new Set();
            (dms || []).forEach((d) => {
                if (d.uid === cur.peer && !document.hidden) return;   // the open one is read already
                const v = views.get(d.uid) || openDm(d, false);
                if (!v) return;
                if (d.name && v.name !== d.name) { v.name = d.name; paintTab(v); }
                v.unread = d.unread;
                badge(v.tabBadge, d.unread);
                seen.add(d.uid);
                total += d.unread;
            });
            views.forEach((v) => { if (v.peer > 0 && !seen.has(v.peer)) { v.unread = 0; badge(v.tabBadge, 0); } });
            if (total > dmTotal && soundOn && pub.ready) ping();
            dmTotal = total;
            updateTitle();
        };

        // ── who is in the chat ───────────────────────────

        const setOnline = (n) => {
            if (typeof n !== 'number' || !onlineBtn) return;
            onlineNum.textContent = String(n);
            onlineBtn.hidden = false;
            const title = t('online_title', 'In the chat: {1}', n);
            onlineBtn.title = title;
            onlineBtn.setAttribute('aria-label', title);
        };

        onlineBtn?.addEventListener('click', async () => {
            try {
                const data = await get('chat_online');
                const users = data.users || [];
                setOnline(users.length);
                const wrap = el('div', 'ch-onlinelist');
                if (!users.length) {
                    wrap.appendChild(el('div', 'ch-onlinelist__empty', t('online_empty', 'Nobody else is here right now.')));
                } else {
                    users.forEach((u) => {
                        const item = el('span', 'ch-onlinelist__item');
                        const name = el('span');
                        name.innerHTML = typeof u === 'string' ? u : u.html;   // built and escaped server-side (format_name)
                        item.appendChild(name);
                        if (typeof u === 'object' && u.uid !== cfg.meId) {
                            const dm = el('button', 'ch-onlinelist__dm');
                            dm.type = 'button';
                            dm.title = t('dm_write', 'Write privately');
                            dm.setAttribute('aria-label', t('dm_write', 'Write privately') + ': ' + u.name);
                            dm.appendChild(el('i', 'fa-solid fa-envelope'));
                            dm.addEventListener('click', () => { window.Swal?.close(); openDm(u); });
                            item.appendChild(dm);
                        }
                        wrap.appendChild(item);
                    });
                }
                if (swal()) {
                    window.Swal.fire({ title: t('online_title', 'In the chat: {1}', users.length), html: wrap,
                        showConfirmButton: false, showCloseButton: true, width: 520 });
                } else {
                    window.alert(wrap.textContent);
                }
            } catch {
                setStatus(t('load_error', 'Could not load the chat.'), 'danger');
            }
        });

        // ── new private dialog: recent dialogs + search by name ──

        newDmBtn?.addEventListener('click', async () => {
            let dialogs = [];
            try { dialogs = (await get('chat_dm_list')).dialogs || []; } catch { /* the name field still works */ }

            const wrap = el('div', 'ch-dmnew');
            const field = el('div', 'ch-dmnew__field');
            const inp = el('input', 'form-control');
            inp.type = 'text';
            inp.maxLength = 64;
            inp.placeholder = t('dm_name_ph', 'Username');
            inp.setAttribute('aria-label', t('dm_name_ph', 'Username'));
            const go = el('button', 'btn btn-primary', t('dm_open', 'Write'));
            go.type = 'button';
            field.append(inp, go);
            const err = el('div', 'ch-dmnew__err');
            wrap.append(el('div', 'ch-dmnew__hint', t('dm_new_hint', 'Only you and the person you write to will see the dialog.')), field, err);

            if (dialogs.length) {
                wrap.appendChild(el('div', 'ch-dmnew__title', t('dm_recent', 'Recent dialogs')));
                const list = el('div', 'ch-dmnew__list');
                dialogs.forEach((d) => {
                    const row = el('button', 'ch-dmnew__row');
                    row.type = 'button';
                    row.appendChild(avatar({ avatar: d.avatar, name: d.name }));
                    const nm = el('span', 'ch-dmnew__name');
                    nm.innerHTML = d.user;           // built and escaped server-side (format_name)
                    nm.querySelectorAll('a').forEach((a) => a.replaceWith(...a.childNodes));   // a row, not a profile link
                    row.appendChild(nm);
                    const when = new Date(d.ts * 1000);
                    row.appendChild(el('span', 'ch-dmnew__when', dayLabel(when) + ' ' + fmtTime(when)));
                    if (d.unread > 0) row.appendChild(el('span', 'ch-badge', String(d.unread)));
                    row.addEventListener('click', () => { window.Swal?.close(); openDm(d); });
                    list.appendChild(row);
                });
                wrap.appendChild(list);
            }

            const find = async () => {
                const name = inp.value.trim();
                if (!name) { inp.focus(); return; }
                go.disabled = true;
                err.textContent = '';
                try {
                    const data = await get('chat_dm_user', { name });
                    window.Swal?.close();
                    openDm(data.user);
                } catch (ex) {
                    err.textContent = ex.message && ex.message !== 'error' && !/^HTTP/.test(ex.message) ? ex.message : t('dm_not_found', 'User not found.');
                } finally {
                    go.disabled = false;
                }
            };
            go.addEventListener('click', find);
            inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); find(); } });

            if (swal()) {
                window.Swal.fire({ title: t('dm_new_title', 'Private message'), html: wrap, showConfirmButton: false,
                    showCloseButton: true, width: 480, didOpen: () => inp.focus() });
            } else {
                const name = window.prompt(t('dm_name_ph', 'Username'));
                if (name) { inp.value = name; find(); }
            }
        });

        // ── who can write to me privately ────────────────

        const loadPrefs = async () => {
            try {
                const data = await get('chat_dm_prefs');
                blocked = new Set((data.blocked || []).map((b) => b.uid));
                paintDmBar();
                return data;
            } catch { return null; }
        };

        const setBlock = async (uid, on) => {
            const data = await post('chat_dm_block', { uid, on: on ? 1 : 0 });
            if (!data.ok) { setStatus(data.error, 'danger'); return null; }
            blocked = new Set((data.blocked || []).map((b) => b.uid));
            paintDmBar();
            setStatus(data.info, 'success', 6000);
            return data;
        };

        dmBlock?.addEventListener('click', async () => {
            if (cur === pub) return;
            const on = !blocked.has(cur.peer);
            if (on) {
                const r = await ask(
                    { title: t('block_title', 'Block {1}?', cur.name), text: t('block_text', '{1} will not be able to write to you privately. You can unblock any time.', cur.name),
                      icon: 'warning', confirmButtonText: t('block', 'Block'), confirmButtonColor: '#dc3545' },
                    () => ({ isConfirmed: window.confirm(t('block_title', 'Block {1}?', cur.name)) })
                );
                if (!r.isConfirmed) return;
            }
            setBlock(cur.peer, on).catch(() => setStatus(t('send_error', 'Not sent, try again.'), 'danger'));
        });

        const renderPrefs = (data) => {
            const wrap = el('div', 'ch-prefs');
            const row = el('label', 'ch-prefs__switch form-check form-switch');
            const cb = el('input', 'form-check-input');
            cb.type = 'checkbox';
            cb.role = 'switch';
            cb.checked = !!data.off;
            const lbl = el('span', 'form-check-label');
            lbl.append(el('strong', '', t('dm_off', 'Do not accept private messages')), el('small', 'd-block text-body-secondary', t('dm_off_hint', 'Staff can still write to you.')));
            row.append(cb, lbl);
            cb.addEventListener('change', async () => {
                cb.disabled = true;
                try { if (!(await setBlock(0, cb.checked))) cb.checked = !cb.checked; }
                catch { cb.checked = !cb.checked; setStatus(t('send_error', 'Not sent, try again.'), 'danger'); }
                finally { cb.disabled = false; }
            });
            wrap.appendChild(row);

            wrap.appendChild(el('div', 'ch-prefs__title', t('blocked_title', 'Blocked users')));
            const list = el('div', 'ch-prefs__list');
            const fill = (items) => {
                list.textContent = '';
                if (!items.length) { list.appendChild(el('div', 'ch-prefs__empty', t('blocked_empty', 'You have not blocked anybody.'))); return; }
                items.forEach((b) => {
                    const r = el('div', 'ch-prefs__row');
                    const nm = el('span', 'ch-prefs__name');
                    nm.innerHTML = b.user;           // built and escaped server-side (format_name)
                    const un = el('button', 'btn btn-sm btn-outline-success rounded-pill', t('unblock', 'Unblock'));
                    un.type = 'button';
                    un.addEventListener('click', async () => {
                        un.disabled = true;
                        try { const d = await setBlock(b.uid, false); if (d) fill(d.blocked || []); else un.disabled = false; }
                        catch { un.disabled = false; setStatus(t('send_error', 'Not sent, try again.'), 'danger'); }
                    });
                    r.append(nm, un);
                    list.appendChild(r);
                });
            };
            fill(data.blocked || []);
            wrap.appendChild(list);
            return wrap;
        };

        prefsBtn?.addEventListener('click', async () => {
            const data = await loadPrefs();
            if (!data) { setStatus(t('load_error', 'Could not load the chat.'), 'danger'); return; }
            if (swal()) {
                window.Swal.fire({ title: t('prefs_title', 'Private messages'), html: renderPrefs(data),
                    showConfirmButton: false, showCloseButton: true, width: 480 });
            }
        });

        // ── polling ──────────────────────────────────────

        const schedule = () => {
            clearTimeout(timer);
            const base = document.hidden ? cfg.pollBg : cfg.poll;
            const wait = fails > 0 ? Math.min(MAX_BACKOFF_MS, base * 2 ** Math.min(fails, 4)) : base;
            timer = setTimeout(load, wait);
        };

        // Poll now (tab switch, page back in view); if one is running, run another right after it
        const kick = () => {
            if (loading) { again = true; return; }
            clearTimeout(timer);
            load();
        };

        const load = async () => {
            if (loading) return;
            loading = true;
            const dmView = cur.peer > 0 ? cur : null;
            try {
                const params = { after: pub.lastId, since: pub.since, pin: pinTs };
                if (dmView) Object.assign(params, { dm: dmView.peer, dm_after: dmView.lastId, dm_since: dmView.since, seen: document.hidden ? 0 : 1 });

                const first = pub.lastId === 0;
                const data = await get('chat_fetch', params);
                pub.since = data.now;
                if (first && typeof data.more === 'boolean') pub.hasMore = data.more;
                apply(pub, data.messages || []);

                if (data.dm && views.get(data.dm.peer) === dmView) {
                    const v = dmView;
                    v.since = data.now;
                    if (typeof data.dm.more === 'boolean') v.hasMore = data.dm.more;
                    if (data.dm.user === null) v.gone = true;
                    else if (data.dm.user) { v.gone = false; if (data.dm.user.name !== v.name) { v.name = data.dm.user.name; paintTab(v); saveTabs(); } }
                    apply(v, data.dm.messages || []);
                    if (!v.ready) { v.ready = true; refreshEmpty(v); toBottom(v); fillUp(v); }
                    if (v === cur) { paintDmBar(); paintInput(); input.setAttribute('placeholder', t('ph_dm', 'Private message to {1}…', v.name || '…')); }
                }
                applyDms(data.dms);
                if (reportsEl && typeof data.reports === 'number') {
                    badge(reportsEl, data.reports);
                    reportsEl.title = t('reports_open', 'Open reports: {1}', data.reports);
                }

                setBanned(data.banned);
                setOnline(data.online);
                if (data.pin) setPin(data.pin);
                if (fails > 0) setStatus(t('reconnected', 'Connected'), 'success', 2000);
                fails = 0;
                if (!pub.ready) { pub.ready = true; fillUp(pub); }
            } catch (err) {
                fails++;
                if (fails === 1 || fails % 5 === 0) setStatus(t('offline', 'No connection, retrying…'), 'danger', 0);
                if (pub.lastId === 0) pub.empty.textContent = t('load_error', 'Could not load the chat.');
            } finally {
                loading = false;
                if (again) { again = false; clearTimeout(timer); setTimeout(load, 0); }
                else schedule();
            }
        };

        // ── replying ─────────────────────────────────────

        function clearReply() {
            replyTo = null;
            if (replyBar) replyBar.hidden = true;
            if (replyText) replyText.textContent = '';
        }

        const startReply = (li) => {
            if (input.disabled || !replyBar) return;
            replyTo = { id: Number(li.dataset.id), name: li.dataset.name || '', snippet: li.dataset.snippet || '' };
            replyText.textContent = '';
            replyText.append(t('replying_to', 'Reply to') + ' ', el('strong', '', replyTo.name));
            if (replyTo.snippet) replyText.append(': ', el('span', 'ch-replybar__snippet', replyTo.snippet));
            replyBar.hidden = false;
            input.focus();
        };

        replyX?.addEventListener('click', () => { clearReply(); input.focus(); });

        // ── sending ──────────────────────────────────────

        const resize = () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        };

        const submit = async () => {
            const text = input.value.trim();
            if (!text || sending) return;
            if (text.length > cfg.max) { setStatus(t('too_long', 'Too long: max {1} characters.', cfg.max), 'danger'); return; }

            const v = cur;                       // the tab may change while the request runs
            sending = true;
            send.disabled = true;
            try {
                // Pictures uploaded through the editor: it leaves file_ids[] inputs in #fileIdsContainer
                const fileBox = form.querySelector('[data-ch-file-ids]');
                const fileIds = fileBox ? [...fileBox.querySelectorAll('input[name="file_ids[]"]')].map((i) => i.value).filter(Boolean) : [];
                const data = await post('chat_send', { text, reply_to: replyTo ? replyTo.id : 0, to_uid: v.peer, file_ids: fileIds.join(',') });
                if (!data.ok) { setStatus(data.error || t('send_error', 'Not sent.'), 'danger', 6000); return; }
                input.value = '';
                if (fileBox) fileBox.textContent = '';      // these files belong to the sent message now
                resize();
                clearReply();
                apply(v, [data.message], true);
            } catch {
                setStatus(t('send_error', 'Not sent, try again.'), 'danger');
            } finally {
                sending = false;
                send.disabled = input.disabled;
                input.focus();
            }
        };

        form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
            if (e.key === 'Escape' && replyTo) { e.preventDefault(); clearReply(); }
        });
        input.addEventListener('input', resize);

        // ── message actions ──────────────────────────────

        const edit = async (li, v) => {
            const r = await ask(
                { title: t('edit_title', 'Edit message'), input: 'textarea', inputValue: li.dataset.raw || '',
                  inputAttributes: { maxlength: String(cfg.max) }, confirmButtonText: t('save', 'Save') },
                () => { const x = window.prompt(t('edit_title', 'Edit message'), li.dataset.raw || ''); return { isConfirmed: x !== null, value: x }; }
            );
            if (!r.isConfirmed || !String(r.value || '').trim()) return;
            const data = await post('chat_edit', { id: li.dataset.id, text: String(r.value) });
            data.ok ? apply(v, [data.message]) : setStatus(data.error, 'danger');
        };

        const remove = async (li, v) => {
            const r = await ask(
                { title: t('delete_title', 'Delete message?'), icon: 'warning', confirmButtonText: t('delete', 'Delete'), confirmButtonColor: '#dc3545' },
                () => ({ isConfirmed: window.confirm(t('delete_title', 'Delete message?')) })
            );
            if (!r.isConfirmed) return;
            const data = await post('chat_delete', { id: li.dataset.id });
            if (data.ok) {
                if (replyTo && String(replyTo.id) === li.dataset.id) clearReply();
                apply(v, [data.message]);
            } else {
                setStatus(data.error, 'danger');
            }
        };

        const report = async (li) => {
            const r = await ask(
                { title: t('report_title', 'Report this message to staff?'),
                  text: t('report_text', 'Staff will see this message and a few messages around it. Nothing else of your dialog.'),
                  input: 'text', inputPlaceholder: t('report_reason', 'Reason (optional)'),
                  inputAttributes: { maxlength: '255' }, icon: 'warning',
                  confirmButtonText: t('report', 'Report to staff'), confirmButtonColor: '#dc3545' },
                () => { const x = window.prompt(t('report_title', 'Report this message to staff?'), ''); return { isConfirmed: x !== null, value: x }; }
            );
            if (!r.isConfirmed) return;
            const data = await post('chat_report', { id: li.dataset.id, reason: String(r.value || '') });
            setStatus(data.ok ? data.info : data.error, data.ok ? 'success' : 'danger', 6000);
        };

        const pin = async (li) => {
            const r = await ask(
                { title: t('pin_confirm', 'Pin this message?'), icon: 'question', confirmButtonText: t('pin', 'Pin') },
                () => ({ isConfirmed: window.confirm(t('pin_confirm', 'Pin this message?')) })
            );
            if (r.isConfirmed) await savePin(li.dataset.raw || '');
        };

        const ban = async (li) => {
            const name = li.dataset.name;
            let minutes = null;
            let reason = '';
            if (swal()) {
                const r = await window.Swal.fire({
                    title: t('ban_title', 'Ban {1} from the chat?', name),
                    input: 'select',
                    inputOptions: { 60: t('ban_1h', '1 hour'), 1440: t('ban_1d', '1 day'), 10080: t('ban_7d', '7 days'), 0: t('ban_forever', 'Permanently') },
                    inputValue: '60',
                    showCancelButton: true, reverseButtons: true,
                    confirmButtonText: t('ban', 'Ban from chat'), confirmButtonColor: '#dc3545',
                    cancelButtonText: t('cancel', 'Cancel'),
                });
                if (!r.isConfirmed) return;
                minutes = r.value;
                const why = await window.Swal.fire({
                    title: t('ban_reason', 'Reason (optional)'), input: 'text',
                    inputAttributes: { maxlength: '255' },
                    showCancelButton: true, reverseButtons: true,
                    confirmButtonText: t('ban', 'Ban from chat'), cancelButtonText: t('cancel', 'Cancel'),
                });
                if (!why.isConfirmed) return;
                reason = String(why.value || '');
            } else {
                minutes = window.prompt(t('ban_minutes', 'Ban {1} for how many minutes? 0 = permanently', name), '60');
                if (minutes === null) return;
                reason = window.prompt(t('ban_reason', 'Reason (optional)'), '') || '';
            }
            const data = await post('chat_ban', { uid: li.dataset.uid, minutes, reason });
            setStatus(data.ok ? data.info : data.error, data.ok ? 'success' : 'danger', 6000);
        };

        // Click handling, scrolling and picture loading for one message list
        function bindList(v) {
            v.list.addEventListener('click', (e) => {
                const li = e.target.closest('.ch-msg');

                const btn = e.target.closest('.ch-act');
                if (btn && li) {
                    if (btn.dataset.act === 'reply') { startReply(li); return; }
                    if (btn.dataset.act === 'dm') { openDm({ uid: Number(li.dataset.uid), name: li.dataset.name }); return; }
                    const fn = { edit, delete: remove, report, pin, ban }[btn.dataset.act];
                    fn?.(li, v).catch(() => setStatus(t('send_error', 'Not sent, try again.'), 'danger'));
                    return;
                }

                const q = e.target.closest('.ch-quote');
                if (q) { gotoMessage(v, Number(q.dataset.goto)); return; }

                // Picture (not a smilie, not inside a link): open it big
                const pic = e.target.closest('.ch-body img');
                if (pic && !pic.closest('a') && !pic.classList.contains('smilie') && !/\/smilies\//.test(pic.getAttribute('src') || '')) {
                    e.preventDefault();
                    if (window.Swal) {
                        Swal.fire({
                            imageUrl: pic.currentSrc || pic.src,
                            imageAlt: pic.alt || '',
                            showConfirmButton: false,
                            showCloseButton: true,
                            width: 'auto',
                            padding: '.5rem',
                            customClass: { popup: 'ch-lightbox' },
                        });
                    } else {
                        window.open(pic.src, '_blank', 'noopener');
                    }
                    return;
                }

                // Public chat: click on a name = @mention it (Ctrl/Cmd+click still opens the profile)
                const who = e.target.closest('.ch-who a');
                if (who && li && v.peer === 0 && !e.ctrlKey && !e.metaKey && li.dataset.name && li.dataset.name !== cfg.me) {
                    e.preventDefault();
                    const tag = '@' + li.dataset.name + ' ';
                    if (!input.value.includes(tag)) input.value = (input.value + ' ' + tag).replace(/^\s+/, '');
                    input.focus();
                    resize();
                }
            });

            // Pictures load after the scroll to the bottom and push the list up: keep it pinned
            v.list.addEventListener('load', (e) => {
                if (e.target.tagName === 'IMG' && v.atBottom) v.list.scrollTop = v.list.scrollHeight;
            }, true);

            v.list.addEventListener('scroll', () => {
                if (v.list.hidden) return;
                v.atBottom = nearBottom(v);
                if (v.atBottom && v === cur) newer.hidden = true;
                if (v.list.scrollTop < NEAR_TOP_PX && v.hasMore && v.ready) loadOlder(v);
            });
        }

        newer.addEventListener('click', () => toBottom(cur));

        // ── staff: active bans ───────────────────────────

        const renderBans = (bans) => {
            const wrap = el('div', 'ch-bans');
            if (!bans.length) {
                wrap.appendChild(el('div', 'ch-bans__empty', t('bans_empty', 'Nobody is banned from the chat.')));
                return wrap;
            }
            bans.forEach((b) => {
                const row  = el('div', 'ch-bans__row');
                const info = el('div', 'ch-bans__info');
                const end  = new Date(b.until * 1000);
                const until = b.until === 0 ? t('ban_forever', 'Permanently') : t('until', 'until {1}', fmtDate(end) + ' ' + fmtTime(end));
                info.append(
                    el('div', 'ch-bans__name', b.name),
                    el('div', 'ch-bans__meta', until + (b.by ? ' · ' + t('by', 'by {1}', b.by) : '') + (b.reason ? ' · ' + b.reason : ''))
                );
                const btn = el('button', 'btn btn-sm btn-outline-success rounded-pill', t('unban', 'Unban'));
                btn.type = 'button';
                btn.addEventListener('click', async () => {
                    btn.disabled = true;
                    try {
                        const data = await post('chat_unban', { uid: b.uid });
                        if (data.ok) { row.remove(); setStatus(data.info, 'success', 6000); }
                        else { btn.disabled = false; setStatus(data.error, 'danger'); }
                    } catch { btn.disabled = false; setStatus(t('send_error', 'Not sent, try again.'), 'danger'); }
                });
                row.append(info, btn);
                wrap.appendChild(row);
            });
            return wrap;
        };

        bansBtn?.addEventListener('click', async () => {
            try {
                const data = await post('chat_bans', {});
                if (!data.ok) { setStatus(data.error, 'danger'); return; }
                if (swal()) {
                    window.Swal.fire({ title: t('bans_title', 'Chat bans'), html: renderBans(data.bans || []),
                        showConfirmButton: false, showCloseButton: true, width: 560 });
                } else {
                    window.alert((data.bans || []).map((b) => b.name).join('\n') || t('bans_empty', 'Nobody is banned from the chat.'));
                }
            } catch {
                setStatus(t('load_error', 'Could not load the chat.'), 'danger');
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                unread = 0;
                updateTitle();
                kick();
            }
        });

        // ── start: restore the open dialogs, then the first poll ──
        // (read the last tab first: restoring the tabs saves the tab list and would overwrite it)
        const want = cfg.dm > 0 ? cfg.dm : Number(store.get(TAB_KEY) || 0);
        let saved = [];
        try { saved = JSON.parse(store.get(DMS_KEY) || '[]'); } catch { saved = []; }
        if (Array.isArray(saved)) saved.slice(0, DM_TABS_MAX).forEach((d) => d && openDm(d, false));
        if (want > 0 && want !== cfg.meId) {
            openDm({ uid: want, name: views.get(want)?.name || '' });
        } else {
            load();
        }
        loadPrefs();
    };

    const start = () => document.querySelectorAll('[data-chat]').forEach(initBox);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
})();
