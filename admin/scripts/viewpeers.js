/* viewpeers.php — Peer List: popovers, client-side filter, copy IP */
(function () {
    'use strict';

    var L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

    /* t(key, fallback, ...args) — English fallback, substitutes {1} and %1$s */
    function t(key, fallback) {
        var str  = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        var args = Array.prototype.slice.call(arguments, 2);
        return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            var i = parseInt(a || b, 10) - 1;
            return i >= 0 && i < args.length ? String(args[i]) : m;
        });
    }

    function init() {
        var root = document.querySelector('.vp-page');
        if (!root) {
            return;
        }

        /* ---------- Popovers ---------- */
        var popovers = [];
        if (window.bootstrap && bootstrap.Popover) {
            root.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
                popovers.push(new bootstrap.Popover(el, {
                    trigger: 'hover focus',
                    placement: 'top',
                    html: true,
                    container: 'body',
                    customClass: 'vp-popover',
                    delay: { show: 120, hide: 80 }
                }));
            });
        }

        function hideAllPopovers() {
            popovers.forEach(function (p) { p.hide(); });
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-bs-toggle="popover"]')) {
                hideAllPopovers();
            }
        });
        window.addEventListener('scroll', hideAllPopovers, { passive: true });

        /* ---------- Filter (current page only) ---------- */
        var input   = root.querySelector('#vp-search');
        var buttons = root.querySelectorAll('[data-vp-filter]');
        var rows    = root.querySelectorAll('tbody tr[data-status]');
        var noMatch = root.querySelector('#vp-no-match');
        var counter = root.querySelector('#vp-visible');
        var mode    = 'all';
        var timer   = null;

        function apply() {
            var q = input ? input.value.trim().toLowerCase() : '';
            var shown = 0;

            rows.forEach(function (row) {
                var okStatus = mode === 'all' || row.dataset.status === mode;
                var okText   = q === '' || (row.dataset.search || '').indexOf(q) !== -1;
                var visible  = okStatus && okText;
                row.hidden = !visible;
                if (visible) {
                    shown++;
                }
            });

            if (noMatch) {
                noMatch.hidden = shown !== 0 || rows.length === 0;
            }
            if (counter) {
                counter.textContent = String(shown);
            }
        }

        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(apply, 120);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    input.value = '';
                    apply();
                }
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.dataset.vpFilter || 'all';
                buttons.forEach(function (b) {
                    var on = b === btn;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                apply();
            });
        });

        apply();

        /* ---------- Copy IP ---------- */
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
                } catch (err) {
                    reject(err);
                } finally {
                    document.body.removeChild(ta);
                }
            });
        }

        root.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-vp-copy]');
            if (!btn) {
                return;
            }
            var icon      = btn.querySelector('i');
            var origTitle = btn.getAttribute('title') || '';
            var setTip    = function (text) {
                btn.setAttribute('title', text);
                btn.setAttribute('aria-label', text);
            };
            var restoreTip = function () { setTip(origTitle); };

            copyText(btn.dataset.vpCopy).then(function () {
                setTip(t('copied', 'Copied: {1}', btn.dataset.vpCopy));
                btn.classList.add('is-done');
                if (icon) {
                    icon.className = 'fa-solid fa-check';
                }
                setTimeout(function () {
                    btn.classList.remove('is-done');
                    if (icon) {
                        icon.className = 'fa-solid fa-copy';
                    }
                    restoreTip();
                }, 1200);
            }).catch(function () {
                setTip(t('copy_failed', 'Could not copy to clipboard'));
                setTimeout(restoreTip, 2000);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
