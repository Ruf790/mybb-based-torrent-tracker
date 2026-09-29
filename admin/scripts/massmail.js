/* Mass Mail — admin/scripts/massmail.js */
(function () {
    'use strict';

    const page = document.querySelector('.mm-page');
    if (!page) return;

    const fmt = (n) => Number(n).toLocaleString();

    function duration(sec) {
        if (sec <= 0) return '0 s';
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        if (h) return h + ' h ' + m + ' min';
        if (m) return m + ' min' + (s ? ' ' + s + ' s' : '');
        return s + ' s';
    }

    /* SweetAlert2 with a plain confirm()/alert() fallback */
    function ask(opts) {
        if (window.Swal) {
            return Swal.fire({
                title: opts.title,
                html: opts.html,
                icon: opts.icon || 'question',
                showCancelButton: true,
                confirmButtonText: opts.confirm,
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true,
                confirmButtonColor: opts.danger ? 'var(--bs-danger)' : 'var(--bs-primary)'
            }).then((r) => r.isConfirmed);
        }
        return Promise.resolve(window.confirm(opts.text));
    }

    function tell(title, text, focusEl) {
        const done = () => focusEl && focusEl.focus();
        if (window.Swal) {
            Swal.fire({ title: title, text: text, icon: 'warning', confirmButtonColor: 'var(--bs-primary)' }).then(done);
        } else {
            window.alert(text);
            done();
        }
    }

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

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

            out.summary.innerHTML = recipients
                ? 'Emailing <strong>' + fmt(recipients) + '</strong> members in <strong>' + fmt(batches) +
                  '</strong> ' + (batches === 1 ? 'batch' : 'batches') + ', about <strong>' + duration(secs) + '</strong>.'
                : (checked.length ? 'The selected groups have no confirmed, enabled members.'
                                  : 'Pick at least one group to see who will get this email.');
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
            tabs.forEach((t) => {
                const on = t === tab;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
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
                title: 'Clear the form?',
                text: 'Clear the subject, message and selected groups?',
                html: 'The subject, message and selected groups will be cleared.',
                icon: 'warning',
                confirm: 'Clear',
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
            if (!subject.value.trim()) return tell('Subject is empty', 'Add a subject before sending.', subject);
            if (!message.value.trim()) return tell('Message is empty', 'Write a message before sending.', message);
            if (!state.groups)         return tell('No recipients', 'Pick at least one usergroup.', all || boxes[0]);
            if (!state.recipients)     return tell('No recipients', 'The selected groups have no confirmed, enabled members.', boxes[0]);

            ask({
                title: 'Send this email?',
                text: 'Send "' + subject.value.trim() + '" to ' + fmt(state.recipients) + ' members?',
                html: '<b>' + esc(subject.value.trim()) + '</b><br>goes to <b>' + fmt(state.recipients) + '</b> members in ' +
                      fmt(state.batches) + ' ' + (state.batches === 1 ? 'batch' : 'batches') +
                      ' (about ' + duration(state.secs) + '). Keep this tab open while it runs.',
                icon: 'question',
                confirm: 'Send mail'
            }).then((ok) => {
                if (!ok) return;
                sendBtn.disabled = true;
                sendBtn.querySelector('i').className = 'fa-solid fa-spinner fa-spin';
                sendBtn.querySelector('span').textContent = 'Starting…';
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
                    title: 'Stop this mailing?',
                    text: 'Stop the mailing? Batches that were already sent stay sent.',
                    html: 'Remaining batches will not be sent. Batches that already went out stay sent.',
                    icon: 'warning',
                    confirm: 'Stop mailing',
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
                pause.querySelector('span').textContent = paused ? 'Resume' : 'Pause';
            });
        }
    }
})();
