/**
 * username-validation.js — проверка нового ника (usercp.php?action=changename)
 * Тексты: window.AGS_LANG (из usercp.php), английский — fallback.
 */
(function () {
    'use strict';

    function t(key, fallback, ...args) {
        const L = window.AGS_LANG || {};
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            s = s.split('{' + (i + 1) + '}').join(a).split('%' + (i + 1) + '$s').join(a);
        });
        return s;
    }
    // SweetAlert через ags-dialog.js; без него — нативные alert()/confirm()
    const say = (msg, icon) => (window.agsAlert ? window.agsAlert(msg, icon) : (window.alert(msg), Promise.resolve()));
    const ask = (msg, opts) => (window.agsConfirm ? window.agsConfirm(msg, opts) : Promise.resolve(window.confirm(msg)));

    // ========= PHP VARIABLES FOR JS =========
    const MIN_USERNAME_LENGTH = parseInt(window.MIN_USERNAME_LENGTH, 10) || 3;
    const MAX_USERNAME_LENGTH = parseInt(window.MAX_USERNAME_LENGTH, 10) || 30;

    // ========= SHOW/HIDE PASSWORD =========
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const input = this.closest('.position-relative').querySelector('input');
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // ========= USERNAME VALIDATION =========
    const usernameInput = document.getElementById('username');
    const usernameFeedback = document.getElementById('usernameFeedback');
    const usernameLengthSpan = document.getElementById('usernameLength');
    const charCounter = document.getElementById('charCounter');

    function validateUsername(username) {
        if (username.length === 0) {
            return { valid: null, message: '' };
        }

        if (username.length < MIN_USERNAME_LENGTH) {
            return {
                valid: false,
                message: '⚠️ ' + t('username_too_short', 'Username must be at least {1} characters long (current: {2})',
                    MIN_USERNAME_LENGTH, username.length)
            };
        }

        if (username.length > MAX_USERNAME_LENGTH) {
            return {
                valid: false,
                message: '❌ ' + t('username_too_long', 'Username exceeds maximum length of {1} characters', MAX_USERNAME_LENGTH)
            };
        }

        const validPattern = /^[a-zA-Z0-9\s\_\-]+$/;
        if (!validPattern.test(username)) {
            return { valid: false, message: '❌ ' + t('username_bad_chars', 'Username can only contain letters, numbers, spaces, underscores, and hyphens') };
        }

        if (username.trim().length === 0) {
            return { valid: false, message: '❌ ' + t('username_empty', 'Username cannot be empty or only spaces') };
        }

        if (username !== username.trim()) {
            return { valid: false, message: '⚠️ ' + t('username_trim', 'Username cannot have leading or trailing spaces') };
        }

        if (/\s{2,}/.test(username)) {
            return { valid: false, message: '⚠️ ' + t('username_double_space', 'Username cannot have multiple consecutive spaces') };
        }

        return { valid: true, message: '✅ ' + t('username_ok', 'Username looks good!') };
    }

    // Иконка + текст через DOM (текст сообщения — не HTML)
    function setFeedback(iconClass, text, stateClass) {
        const icon = document.createElement('i');
        icon.className = 'fas ' + iconClass + ' me-1';
        usernameFeedback.replaceChildren(icon, document.createTextNode(' ' + text));
        usernameFeedback.className = 'username-feedback ' + stateClass;
    }

    function updateUsernameFeedback() {
        if (!usernameInput) return;

        const username = usernameInput.value;
        const result = validateUsername(username);

        usernameLengthSpan.textContent = username.length;

        if (username.length > MAX_USERNAME_LENGTH) {
            charCounter.classList.add('danger');
            charCounter.classList.remove('warning');
            usernameLengthSpan.style.color = 'var(--icon-danger)';
        } else if (username.length > MAX_USERNAME_LENGTH * 0.8) {
            charCounter.classList.add('warning');
            charCounter.classList.remove('danger');
            usernameLengthSpan.style.color = 'var(--icon-warning)';
        } else {
            charCounter.classList.remove('warning', 'danger');
            usernameLengthSpan.style.color = 'var(--text-muted)';
        }

        if (result.valid === null) {
            usernameFeedback.replaceChildren();
            usernameFeedback.className = 'username-feedback';
            return;
        }

        if (result.valid) {
            setFeedback('fa-check-circle', result.message, 'valid');
        } else {
            setFeedback('fa-times-circle', result.message, 'invalid');
        }
    }

    if (usernameInput) {
        usernameInput.addEventListener('input', updateUsernameFeedback);
        updateUsernameFeedback();
    }

    // ========= FORM SUBMIT VALIDATION =========
    // Форма с кнопкой #submitBtn, а не первая на странице (первая — поиск в шапке)
    const form = document.getElementById('submitBtn')?.closest('form') || document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    const passwordInput = document.getElementById('password');

    if (form) {
        form.addEventListener('submit', function (e) {
            if (usernameInput && usernameInput.value) {
                const username = usernameInput.value;
                const validation = validateUsername(username);

                if (validation.valid === false) {
                    e.preventDefault();
                    say(validation.message).then(() => usernameInput.focus());
                    return;
                }

                if (username.trim().length === 0) {
                    e.preventDefault();
                    say(t('enter_username', 'Please enter a username.')).then(() => usernameInput.focus());
                    return;
                }
            } else {
                e.preventDefault();
                say(t('enter_username', 'Please enter a username.')).then(() => usernameInput?.focus());
                return;
            }

            if (passwordInput && !passwordInput.value) {
                e.preventDefault();
                say(t('enter_password_confirm', 'Please enter your current password to confirm the change.')).then(() => passwordInput.focus());
                return;
            }

            if (submitBtn) {
                const spin = document.createElement('i');
                spin.className = 'fas fa-spinner fa-spin me-2';
                submitBtn.replaceChildren(spin, document.createTextNode(' ' + t('updating', 'Updating...')));
            }
        });
    }

    // ========= PREVENT EMPTY USERNAME =========
    if (usernameInput) {
        usernameInput.addEventListener('blur', function () {
            if (this.value.trim() === '') {
                this.value = '';
                updateUsernameFeedback();
            }
        });
    }

    // ========= REAL-TIME LENGTH CHECK =========
    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            const length = this.value.length;

            if (length < MIN_USERNAME_LENGTH && length > 0) {
                this.style.borderColor = 'var(--icon-warning)';
            } else if (length > MAX_USERNAME_LENGTH) {
                this.style.borderColor = 'var(--icon-danger)';
            } else if (length >= MIN_USERNAME_LENGTH && length <= MAX_USERNAME_LENGTH) {
                this.style.borderColor = 'var(--icon-success)';
            } else {
                this.style.borderColor = 'var(--input-border)';
            }
        });
    }

    // ========= TOOLTIP =========
    const usernameLabel = document.querySelector('label[for="username"]');
    if (usernameLabel) {
        usernameLabel.style.cursor = 'help';
        usernameLabel.title = t('username_rules',
            'Username must be {1}-{2} characters and contain only letters, numbers, spaces, underscores, and hyphens',
            MIN_USERNAME_LENGTH, MAX_USERNAME_LENGTH);
    }
})();
