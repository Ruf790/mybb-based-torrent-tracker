/**
 * staff.js — Staff Panel Shared Scripts
 * Place at: admin/scripts/staff.js
 * v2.0.0 — extracted from inline scripts
 */

(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════
       ADMIN FLOATING BAR
       ══════════════════════════════════════════════════════ */

    var floatingBar = {
        element:      null,
        isVisible:    true,
        persistClose: false,

        init: function () {
            this.element = document.getElementById('adminFloatingBar');
            if (!this.element) return; // нет на этой странице — выходим
            this.bindEvents();
            this.checkPreviousState();
            this.addGlowEffect();
        },

        bindEvents: function () {
            var self     = this;
            var closeBtn = this.element.querySelector('.floating-bar-close');
            if (!closeBtn) return;
            closeBtn.addEventListener('click', function () { self.close(); });
        },

        close: function () {
            this.isVisible = false;
            this.element.classList.add('hidden');
            if (this.persistClose) {
                try { localStorage.setItem('adminFloatingBarClosed', 'true'); } catch (e) {}
            }
            var el = this.element;
            setTimeout(function () { el.style.display = 'none'; }, 400);
        },

        show: function () {
            this.isVisible = true;
            this.element.style.display = 'flex';
            this.element.classList.remove('hidden');
            if (this.persistClose) {
                try { localStorage.removeItem('adminFloatingBarClosed'); } catch (e) {}
            }
        },

        checkPreviousState: function () {
            if (!this.persistClose) return;
            try {
                if (localStorage.getItem('adminFloatingBarClosed') === 'true') {
                    this.element.style.display = 'none';
                    this.isVisible = false;
                }
            } catch (e) {}
        },

        addGlowEffect: function () {
            var self = this;
            setInterval(function () {
                if (!self.isVisible) return;
                self.element.style.boxShadow =
                    '0 10px 40px rgba(59,130,246,.4), 0 0 0 1px rgba(255,255,255,.1)';
                setTimeout(function () {
                    if (self.isVisible) {
                        self.element.style.boxShadow =
                            '0 10px 40px rgba(59,130,246,.3), 0 0 0 1px rgba(255,255,255,.1)';
                    }
                }, 1000);
            }, 5000);
        }
    };

    /* ══════════════════════════════════════════════════════
       LIVE CLOCK  (#current-time on dashboard, #live-clock elsewhere)
       ══════════════════════════════════════════════════════ */

    function initLiveClock() {
        var el = document.getElementById('current-time') ||
                 document.getElementById('live-clock');
        if (!el) return;

        function tick() {
            var now = new Date();
            var h = String(now.getHours()).padStart(2, '0');
            var m = String(now.getMinutes()).padStart(2, '0');
            var s = String(now.getSeconds()).padStart(2, '0');
            el.textContent = h + ':' + m + ':' + s;
        }
        tick();
        setInterval(tick, 1000);
    }

    /* ══════════════════════════════════════════════════════
       BUTTON LOADING ANIMATION (dashboard)
       ══════════════════════════════════════════════════════ */

    function initButtonLoading() {
        document.querySelectorAll('.btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!this.getAttribute('href')) return;
                var originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                this.disabled = true;
                var self = this;
                setTimeout(function () {
                    self.innerHTML = originalHTML;
                    self.disabled = false;
                }, 2000);
            });
        });
    }

    /* ══════════════════════════════════════════════════════
       PERMISSIONS CHECKBOXES
       checkAllPermissions / uncheckAllPermissions — старые имена из оригинала
       setPerms — новое универсальное имя
       ══════════════════════════════════════════════════════ */

    window.checkAllPermissions = function () {
        document.querySelectorAll('.permission-checkbox, .perm-cb').forEach(function (cb) {
            cb.checked = true;
        });
    };
    window.uncheckAllPermissions = function () {
        document.querySelectorAll('.permission-checkbox, .perm-cb').forEach(function (cb) {
            cb.checked = false;
        });
    };
    window.setPerms = function (state) {
        document.querySelectorAll('.permission-checkbox, .perm-cb').forEach(function (cb) {
            cb.checked = state;
        });
    };

    /* ══════════════════════════════════════════════════════
       BOOTSTRAP FORM VALIDATION (.needs-validation)
       ══════════════════════════════════════════════════════ */

    function initFormValidation() {
        document.querySelectorAll('.needs-validation').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }

    /* ══════════════════════════════════════════════════════
       TOOL FILENAME AUTO-FILL (create tool form)
       ══════════════════════════════════════════════════════ */

    function initFilenameAutofill() {
        var nameInput = document.getElementById('toolName');
        var fileInput = document.getElementById('toolFilename');
        if (!nameInput || !fileInput) return;

        // blur — как в оригинале
        nameInput.addEventListener('blur', function () {
            if (nameInput.value && !fileInput.value) {
                fileInput.value = nameInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '') + '.php';
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       SECURITY SCORE ANIMATION
       ══════════════════════════════════════════════════════ */

    function animateSecurityScore() {
        var scoreEl = document.querySelector('.score-circle');
        if (!scoreEl) return;
        scoreEl.style.opacity   = '0';
        scoreEl.style.transform = 'scale(.85)';
        scoreEl.style.transition = 'opacity .5s ease, transform .5s cubic-bezier(.34,1.56,.64,1)';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                scoreEl.style.opacity   = '1';
                scoreEl.style.transform = 'scale(1)';
            });
        });
    }

    /* ══════════════════════════════════════════════════════
       INIT
       ══════════════════════════════════════════════════════ */

    document.addEventListener('DOMContentLoaded', function () {
        floatingBar.init();          // безопасно — внутри проверка на null
        window.adminFloatingBar = floatingBar;

        initLiveClock();
        initFormValidation();
        initFilenameAutofill();
        initButtonLoading();
        animateSecurityScore();
    });

})();
