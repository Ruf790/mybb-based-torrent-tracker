/*******************************************************************************
 * Login Security Manager v3.2 — small UI helpers (independent of maxlogin.js)
 *
 *  1. Config bootstrap: reads <script type="application/json"> blocks printed
 *     by maxlogin.php into window.maxloginConfig / window.maxloginLogConfig.
 *     Must be loaded AFTER the config block and BEFORE maxlogin.js.
 *  2. Delegated "copy IP" handler for .ml-copy[data-copy].
 *  3. Delegated [data-ml-trigger="#selector"] — clicks another control
 *     (replaces the inline <script> / onclick in the empty states, which
 *     also works for HTML inserted via AJAX).
 ******************************************************************************/
(function () {
    'use strict';

    function readConfig(elementId, globalName) {
        var el = document.getElementById(elementId);
        if (!el) return;
        try {
            window[globalName] = JSON.parse(el.textContent || '{}');
        } catch (err) {
            window[globalName] = {};
        }
    }

    readConfig('maxlogin-config', 'maxloginConfig');
    readConfig('maxlogin-log-config', 'maxloginLogConfig');

    // Bind delegated handlers only once even if the file is included twice
    if (window.__maxloginUiBound) return;
    window.__maxloginUiBound = true;

    function copyText(text, done) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () {});
            return;
        }
        var t = document.createElement('textarea');
        t.value = text;
        t.setAttribute('readonly', '');
        t.style.position = 'fixed';
        t.style.opacity = '0';
        document.body.appendChild(t);
        t.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        t.remove();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ml-copy');
        if (!btn) return;
        e.preventDefault();

        copyText(btn.getAttribute('data-copy') || '', function () {
            var i = btn.querySelector('i');
            if (!i) return;
            var old = i.className;
            i.className = 'fa-solid fa-check';
            setTimeout(function () { i.className = old; }, 1200);
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ml-trigger]');
        if (!btn) return;
        e.preventDefault();

        var target = document.querySelector(btn.getAttribute('data-ml-trigger'));
        if (target) target.click();
    });
})();
