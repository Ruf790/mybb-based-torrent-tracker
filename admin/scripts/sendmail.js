/* Send Mail — staff panel */
(() => {
    'use strict';

    // Kept for backwards compatibility with other scripts
    window.ts_show = (id) => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'block';
    };

    const init = () => {
        const form = document.getElementById('mailForm');
        if (!form) return;

        const email     = document.getElementById('emailInput');
        const subject   = document.getElementById('subjectInput');
        const message   = document.getElementById('messageInput');
        const counter   = document.getElementById('smCounter');
        const count     = document.getElementById('smCount');
        const pvTo      = document.getElementById('smPvTo');
        const pvSubject = document.getElementById('smPvSubject');
        const frame     = document.getElementById('smPvFrame');
        const sendBtn   = document.getElementById('smSend');
        const resetBtn  = document.getElementById('smReset');
        const MIN_LEN   = 6;

        /* ---- Confirm dialog: SweetAlert2 with confirm() fallback ---- */
        const ask = (opts) => {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    showCancelButton: true,
                    reverseButtons: true,
                    ...opts,
                }).then((r) => r.isConfirmed);
            }
            return Promise.resolve(window.confirm(opts.text || opts.title));
        };

        /* ---- Toolbar ---- */
        const TAGS = {
            bold:      ['<b>', '</b>'],
            italic:    ['<i>', '</i>'],
            underline: ['<u>', '</u>'],
            heading:   ['<h3>', '</h3>'],
            paragraph: ['<p>', '</p>'],
            list:      ['<ul>\n  <li>', '</li>\n</ul>'],
            link:      ['<a href="https://">', '</a>'],
            image:     ['<img src="https://', '" alt="">'],
            hr:        ['<hr>\n', ''],
            br:        ['<br>\n', ''],
        };

        const wrap = (name) => {
            const t = TAGS[name];
            if (!t) return;
            const [open, close] = t;
            const s = message.selectionStart;
            const e = message.selectionEnd;
            const selected = message.value.slice(s, e);

            message.focus();
            message.setRangeText(open + selected + close, s, e, 'end');
            if (s === e) {
                const pos = s + open.length;
                message.setSelectionRange(pos, pos);
            }
            message.dispatchEvent(new Event('input', { bubbles: true }));
        };

        document.querySelectorAll('.sm-page [data-sm-tag]').forEach((btn) => {
            btn.addEventListener('click', () => wrap(btn.dataset.smTag));
        });

        /* ---- Counter ---- */
        const updateCounter = () => {
            const n = message.value.trim().length;
            count.textContent = String(n);
            counter.classList.toggle('is-short', n > 0 && n < MIN_LEN);
        };

        /* ---- Live preview ---- */
        const setMeta = (el, value, fallback) => {
            el.textContent = value || fallback;
            el.classList.toggle('is-empty', !value);
        };

        const render = () => {
            setMeta(pvTo, email.value.trim(), 'No recipient yet');
            setMeta(pvSubject, subject.value.trim(), 'No subject yet');

            const body = message.value.trim()
                || '<p style="color:#adb5bd;font-style:italic">Your message will appear here.</p>';

            // sandbox="" on the iframe blocks scripts, forms and navigation
            frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
                + '<style>body{margin:18px;font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
                + 'color:#212529;overflow-wrap:anywhere}img{max-width:100%;height:auto}a{color:#0d6efd}'
                + 'h1,h2,h3{line-height:1.25}</style></head><body>' + body + '</body></html>';
        };

        let timer = 0;
        const schedule = () => {
            clearTimeout(timer);
            timer = setTimeout(render, 180);
        };

        [email, subject, message].forEach((el) => el.addEventListener('input', schedule));
        message.addEventListener('input', updateCounter);

        /* ---- Hotkeys ---- */
        message.addEventListener('keydown', (ev) => {
            if (!(ev.ctrlKey || ev.metaKey)) return;
            const map = { b: 'bold', i: 'italic', u: 'underline', k: 'link' };
            const key = ev.key.toLowerCase();

            if (map[key]) {
                ev.preventDefault();
                wrap(map[key]);
            } else if (key === 'enter') {
                ev.preventDefault();
                form.requestSubmit(sendBtn);
            }
        });

        /* ---- Submit with confirmation ---- */
        let confirmed = false;

        form.addEventListener('submit', (ev) => {
            if (confirmed) return;
            ev.preventDefault();
            form.classList.add('was-validated');

            if (!form.checkValidity() || message.value.trim().length < MIN_LEN) {
                const firstBad = form.querySelector(':invalid');
                if (firstBad) firstBad.focus();
                return;
            }

            ask({
                icon: 'question',
                title: 'Send this email?',
                text: 'It will be delivered to ' + email.value.trim() + '.',
                confirmButtonText: 'Send email',
                cancelButtonText: 'Keep editing',
            }).then((ok) => {
                if (!ok) return;
                confirmed = true;
                sendBtn.disabled = true;
                resetBtn.disabled = true;
                window.ts_show('loading-layer');
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        /* ---- Reset with confirmation ---- */
        resetBtn.addEventListener('click', () => {
            const dirty = [email, subject, message].some((el) => el.value !== el.defaultValue);
            if (!dirty) return;

            ask({
                icon: 'warning',
                title: 'Discard your changes?',
                text: 'The form will go back to how it was when the page loaded.',
                confirmButtonText: 'Discard',
                cancelButtonText: 'Keep editing',
            }).then((ok) => {
                if (!ok) return;
                form.reset();
                form.classList.remove('was-validated');
                updateCounter();
                render();
            });
        });

        updateCounter();
        render();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();