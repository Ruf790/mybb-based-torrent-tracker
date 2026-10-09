/* Mass Mail — admin/scripts/massmail.js */
(function () {
    'use strict';

    const page = document.querySelector('.mm-page');
    if (!page) return;

    /* ── i18n ────────────────────────────────────────────────────── */
    /* AGS_LANG is printed by massmail.php (js_* keys without the prefix).
       $lang->load() turns {1} into %1$s, so both forms are substituted. */
    const L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};

    function str(key, fallback) {
        return typeof L[key] === 'string' ? L[key] : fallback;
    }

    function t(key, fallback, ...args) {
        return str(key, fallback).replace(/\{(\d+)\}|%(\d+)\$s/g, (m, a, b) => {
            const i = parseInt(a || b, 10) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }

    /* Marks an argument of rich() to be wrapped in <strong> */
    const B = (v) => ({ bold: String(v) });

    /* Fills el with a translated template as DOM nodes (no innerHTML) */
    function rich(el, key, fallback, ...args) {
        const tpl = str(key, fallback);
        const re  = /\{(\d+)\}|%(\d+)\$s/g;
        const frag = document.createDocumentFragment();
        let last = 0;
        let m;
        while ((m = re.exec(tpl)) !== null) {
            if (m.index > last) frag.appendChild(document.createTextNode(tpl.slice(last, m.index)));
            const v = args[parseInt(m[1] || m[2], 10) - 1];
            if (v === undefined) {
                frag.appendChild(document.createTextNode(m[0]));
            } else if (v !== null && typeof v === 'object' && 'bold' in v) {
                const s = document.createElement('strong');
                s.textContent = v.bold;
                frag.appendChild(s);
            } else {
                frag.appendChild(document.createTextNode(String(v)));
            }
            last = re.lastIndex;
        }
        if (last < tpl.length) frag.appendChild(document.createTextNode(tpl.slice(last)));
        el.textContent = '';
        el.appendChild(frag);
        return el;
    }

    let pluralRules = null;
    try { pluralRules = new Intl.PluralRules(t('locale', 'en')); } catch (e) { /* no Intl: one/other */ }

    function plural(n, base, one, other) {
        const cat = pluralRules ? pluralRules.select(n) : (n === 1 ? 'one' : 'other');
        const key = base + '_' + cat;
        return typeof L[key] === 'string' ? L[key] : t(base + '_other', n === 1 ? one : other);
    }

    const batchWord = (n) => plural(n, 'batches', 'batch', 'batches');

    const fmt = (n) => Number(n).toLocaleString();

    function duration(sec) {
        if (sec <= 0) return t('dur_s', '{1} s', 0);
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        if (h) return t('dur_h_m', '{1} h {2} min', h, m);
        if (m) return s ? t('dur_m_s', '{1} min {2} s', m, s) : t('dur_m', '{1} min', m);
        return t('dur_s', '{1} s', s);
    }

    /* Escape for SweetAlert2 options that are rendered as HTML (button labels) */
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* SweetAlert2 with a plain confirm()/alert() fallback.
       opts: title, text (confirm() fallback), body (plain text) or node (DOM element), confirm, icon, danger */
    function ask(opts) {
        if (window.Swal) {
            const cfg = {
                titleText: opts.title,
                icon: opts.icon || 'question',
                showCancelButton: true,
                confirmButtonText: esc(opts.confirm),
                cancelButtonText: esc(t('cancel', 'Cancel')),
                reverseButtons: true,
                focusCancel: true,
                confirmButtonColor: opts.danger ? 'var(--bs-danger)' : 'var(--bs-primary)'
            };
            if (opts.node) cfg.html = opts.node;
            else cfg.text = opts.body || opts.text;
            return Swal.fire(cfg).then((r) => r.isConfirmed);
        }
        return Promise.resolve(window.confirm(opts.text));
    }

    function tell(title, text, focusEl) {
        const done = () => focusEl && focusEl.focus();
        if (window.Swal) {
            Swal.fire({ titleText: title, text: text, icon: 'warning', confirmButtonColor: 'var(--bs-primary)' }).then(done);
        } else {
            window.alert(text);
            done();
        }
    }

    if (page.dataset.mode === 'form') initForm();
    if (page.dataset.mode === 'send') initSend();

    /* ── Compose form ────────────────────────────────────────────── */
    function initForm() {
        const form = document.getElementById('mm-form');
        if (!form) return;

        const boxes   = Array.from(form.querySelectorAll('input[name="usergroup[]"]'));
        const all     = document.getElementById('ug_select_all');
        const batch   = document.getElementById('mm-batch');
        const wait    = document.getElementById('mm-wait');
        const subject = document.getElementById('mm-subject');
        const message = document.getElementById('message');
        const sendBtn = document.getElementById('mm-send');
        const zero    = parseInt(page.dataset.zeroCount, 10) || 0;

        const out = {
            recipients: document.getElementById('mm-kpi-recipients'),
            batches:    document.getElementById('mm-kpi-batches'),
            time:       document.getElementById('mm-kpi-time'),
            summary:    document.querySelector('#mm-bar-summary span'),
            subjCount:  document.getElementById('mm-subject-count')
        };

        let state = { recipients: 0, batches: 0, secs: 0, groups: 0 };

        function recompute() {
            const checked = boxes.filter((b) => b.checked);
            const per     = Math.max(1, parseInt(batch.value, 10) || 1);
            const pause   = Math.max(1, parseInt(wait.value, 10) || 1);
            const recipients = checked.length
                ? checked.reduce((s, b) => s + (parseInt(b.dataset.count, 10) || 0), 0) + zero
                : 0;
            const batches = recipients ? Math.ceil(recipients / per) : 0;
            const secs    = batches > 1 ? (batches - 1) * pause : 0;

            state = { recipients, batches, secs, groups: checked.length };

            out.recipients.textContent = fmt(recipients);
            out.batches.textContent    = fmt(batches);
            out.time.textContent       = duration(secs);

            if (all) {
                all.checked       = boxes.length > 0 && checked.length === boxes.length;
                all.indeterminate = checked.length > 0 && checked.length < boxes.length;
            }

            if (recipients) {
                rich(out.summary, 'summary', 'Emailing {1} members in {2} {3}, about {4}.',
                    B(fmt(recipients)), B(fmt(batches)), batchWord(batches), B(duration(secs)));
            } else {
                out.summary.textContent = checked.length
                    ? t('summary_no_members', 'The selected groups have no confirmed, enabled members.')
                    : t('summary_pick', 'Pick at least one group to see who will get this email.');
            }
        }

        if (all) {
            all.addEventListener('change', () => {
                boxes.forEach((b) => { b.checked = all.checked; });
                recompute();
            });
        }
        boxes.forEach((b) => b.addEventListener('change', recompute));
        [batch, wait].forEach((el) => el.addEventListener('input', recompute));

        subject.addEventListener('input', () => { out.subjCount.textContent = subject.value.length; });

        /* Write / Preview */
        const tabs   = Array.from(page.querySelectorAll('[data-mm-tab]'));
        const panes  = Array.from(page.querySelectorAll('[data-mm-pane]'));
        const frame  = document.getElementById('mm-preview-frame');
        let wrap = { header: '', footer: '' };
        try {
            wrap = JSON.parse(document.getElementById('mm-preview-data').textContent) || wrap;
        } catch (e) { /* keep empty header/footer */ }

        function renderPreview() {
            frame.srcdoc = '<!doctype html><meta charset="utf-8"><base target="_blank">' +
                '<style>body{font:14px/1.55 Arial,Helvetica,sans-serif;color:#222;margin:18px}img{max-width:100%}</style>' +
                wrap.header + '<br><hr><br>' + message.value + '<br><hr><br>' + wrap.footer;
        }

        tabs.forEach((tab) => tab.addEventListener('click', () => {
            const name = tab.dataset.mmTab;
            tabs.forEach((tb) => {
                const on = tb === tab;
                tb.classList.toggle('is-active', on);
                tb.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panes.forEach((p) => { p.hidden = p.dataset.mmPane !== name; });
            if (name === 'preview') renderPreview();
        }));

        /* Clear */
        form.addEventListener('reset', (e) => {
            if (form.dataset.resetOk === '1') {
                delete form.dataset.resetOk;
                setTimeout(() => { out.subjCount.textContent = subject.value.length; recompute(); }, 0);
                return;
            }
            if (!subject.value.trim() && !message.value.trim()) {
                setTimeout(recompute, 0);
                return;
            }
            e.preventDefault();
            ask({
                title: t('clear_title', 'Clear the form?'),
                text: t('clear_text', 'Clear the subject, message and selected groups?'),
                body: t('clear_body', 'The subject, message and selected groups will be cleared.'),
                icon: 'warning',
                confirm: t('clear_confirm', 'Clear'),
                danger: true
            }).then((ok) => {
                if (!ok) return;
                form.dataset.resetOk = '1';
                form.reset();
            });
        });

        /* Send */
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const noRcpt = t('err_recipients_title', 'No recipients');
            if (!subject.value.trim()) return tell(t('err_subject_title', 'Subject is empty'), t('err_subject', 'Add a subject before sending.'), subject);
            if (!message.value.trim()) return tell(t('err_message_title', 'Message is empty'), t('err_message', 'Write a message before sending.'), message);
            if (!state.groups)         return tell(noRcpt, t('err_no_groups', 'Pick at least one usergroup.'), all || boxes[0]);
            if (!state.recipients)     return tell(noRcpt, t('err_no_members', 'The selected groups have no confirmed, enabled members.'), boxes[0]);

            const subj = subject.value.trim();
            const node = document.createElement('div');
            const head = document.createElement('b');
            head.textContent = subj;
            node.appendChild(head);
            node.appendChild(document.createElement('br'));
            node.appendChild(rich(document.createElement('span'), 'send_body',
                'Goes to {1} members in {2} {3} (about {4}). Keep this tab open while it runs.',
                B(fmt(state.recipients)), fmt(state.batches), batchWord(state.batches), duration(state.secs)));

            ask({
                title: t('send_title', 'Send this email?'),
                text: t('send_text', 'Send "{1}" to {2} members?', subj, fmt(state.recipients)),
                node: node,
                icon: 'question',
                confirm: t('send_confirm', 'Send mail')
            }).then((ok) => {
                if (!ok) return;
                sendBtn.disabled = true;
                sendBtn.querySelector('i').className = 'fa-solid fa-spinner fa-spin';
                sendBtn.querySelector('span').textContent = t('starting', 'Starting…');
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        recompute();
    }

    /* ── Sending page ────────────────────────────────────────────── */
    function initSend() {
        const stopForm = document.getElementById('mm-stop-form');
        if (stopForm) {
            stopForm.addEventListener('submit', (e) => {
                if (stopForm.dataset.ok === '1') return;
                e.preventDefault();
                ask({
                    title: t('stop_title', 'Stop this mailing?'),
                    text: t('stop_text', 'Stop the mailing? Batches that were already sent stay sent.'),
                    body: t('stop_body', 'Remaining batches will not be sent. Batches that already went out stay sent.'),
                    icon: 'warning',
                    confirm: t('stop_confirm', 'Stop mailing'),
                    danger: true
                }).then((ok) => {
                    if (!ok) return;
                    stopForm.dataset.ok = '1';
                    HTMLFormElement.prototype.submit.call(stopForm);
                });
            });
        }

        const next = page.dataset.next;
        if (!next) return;

        let sec     = parseInt(page.dataset.seconds, 10) || 1;
        let paused  = false;
        const num   = document.getElementById('mm-countdown');
        const box   = page.querySelector('.mm-countdown');
        const icon  = document.getElementById('mm-countdown-icon');
        const pause = document.getElementById('mm-pause');

        const timer = setInterval(() => {
            if (paused) return;
            sec--;
            if (sec <= 0) {
                clearInterval(timer);
                num.textContent = '0';
                window.location.href = next;
                return;
            }
            num.textContent = sec;
        }, 1000);

        if (pause) {
            pause.addEventListener('click', () => {
                paused = !paused;
                box.classList.toggle('is-paused', paused);
                icon.className = paused ? 'fa-solid fa-circle-pause' : 'fa-solid fa-hourglass-half';
                pause.querySelector('i').className = paused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
                pause.querySelector('span').textContent = paused ? t('resume', 'Resume') : t('pause', 'Pause');
            });
        }
    }
})();
