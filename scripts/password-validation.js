/**
 * password-validation.js — смена пароля (usercp.php?action=password)
 * Тексты: window.AGS_LANG (из usercp.php), английский — fallback.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function t(key, fallback, ...args) {
        const L = window.AGS_LANG || {};
        let s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach((a, i) => {
            s = s.split('{' + (i + 1) + '}').join(a).split('%' + (i + 1) + '$s').join(a);
        });
        return s;
    }
    const say = (msg, icon) => (window.agsAlert ? window.agsAlert(msg, icon) : (window.alert(msg), Promise.resolve()));
    const ask = (msg, opts) => (window.agsConfirm ? window.agsConfirm(msg, opts) : Promise.resolve(window.confirm(msg)));

    const config = window.passwordConfig || {};
    const MIN_PASSWORD_LENGTH = config.minLength || 6;
    const MAX_PASSWORD_LENGTH = config.maxLength || 30;
    const REQUIRE_COMPLEX_PASSWORDS = config.requireComplex || 0;

    const passwordInput = document.getElementById('password');
    const password2Input = document.getElementById('password2');
    const oldPasswordInput = document.getElementById('oldpassword');
    const submitBtn = document.getElementById('submitBtn');

    const passwordLengthSpan = document.getElementById('passwordLength');
    const passwordCharCounter = document.getElementById('passwordCharCounter');
    const passwordMatch = document.getElementById('passwordMatch');

    const complexitySection = document.getElementById('complexitySection');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');

    // Элементы требований
    const reqUppercase = document.getElementById('complexityUppercase');
    const reqLowercase = document.getElementById('complexityLowercase');
    const reqNumber    = document.getElementById('complexityNumber');
    const reqSpecial   = document.getElementById('complexitySpecial');

    // ===== SHOW COMPLEXITY BLOCK =====
    if (complexitySection) {
        complexitySection.style.display = REQUIRE_COMPLEX_PASSWORDS === 1 ? 'block' : 'none';
    }

    // ===== SHOW/HIDE PASSWORD =====
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ===== COMPLEXITY =====
    function checkComplexity(password) {
        return {
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
    }

    function renderComplexity(password) {
        if (!reqUppercase) return;
        const c = checkComplexity(password);
        const set = (el, ok) => {
            el.classList.toggle('valid', ok);
            el.classList.toggle('invalid', !ok);
        };
        set(reqUppercase, c.uppercase);
        set(reqLowercase, c.lowercase);
        set(reqNumber, c.number);
        set(reqSpecial, c.special);
    }

    function validatePasswordLength(password) {
        const length = password.length;

        if (length < MIN_PASSWORD_LENGTH) {
            return { valid: false, message: t('pwd_min', 'Password must be at least {1} characters', MIN_PASSWORD_LENGTH) };
        }
        if (length > MAX_PASSWORD_LENGTH) {
            return { valid: false, message: t('pwd_max', 'Password must be no longer than {1} characters', MAX_PASSWORD_LENGTH) };
        }
        return { valid: true };
    }

    // ===== STRENGTH =====
    function calcStrength(password) {
        let score = 0;
        if (password.length >= MIN_PASSWORD_LENGTH) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        return Math.min(4, Math.max(0, score - 1)); // 0..4
    }

    function renderStrength(password) {
        if (!strengthFill) return;
        const level = password.length === 0 ? 0 : calcStrength(password);
        const map = [
            { cls: '',                          txt: '' },
            { cls: 'st-strength__fill--weak',   txt: t('pwd_weak', 'Weak') },
            { cls: 'st-strength__fill--medium', txt: t('pwd_medium', 'Medium') },
            { cls: 'st-strength__fill--good',   txt: t('pwd_good', 'Good') },
            { cls: 'st-strength__fill--strong', txt: t('pwd_strong', 'Strong') },
        ];
        strengthFill.className = 'st-strength__fill' + (map[level].cls ? ' ' + map[level].cls : '');
        if (strengthText) strengthText.textContent = map[level].txt;
    }

    function updatePasswordCounter() {
        if (!passwordInput) return;

        const length = passwordInput.value.length;
        if (passwordLengthSpan) passwordLengthSpan.textContent = length;

        if (passwordCharCounter) {
            passwordCharCounter.classList.remove('st-charcount--warning', 'st-charcount--danger');
            if (length > MAX_PASSWORD_LENGTH) {
                passwordCharCounter.classList.add('st-charcount--danger');
            } else if (length > MAX_PASSWORD_LENGTH * 0.8) {
                passwordCharCounter.classList.add('st-charcount--warning');
            }
        }
    }

    // ===== MATCH CHECK =====
    function checkPasswordMatch() {
        if (!passwordInput || !password2Input || !passwordMatch) return;

        if (password2Input.value.length === 0) {
            passwordMatch.textContent = '';
            passwordMatch.className = 'small mt-1';
            return;
        }

        if (passwordInput.value === password2Input.value) {
            passwordMatch.textContent = '✔ ' + t('pwd_match', 'Passwords match');
            passwordMatch.className = 'small mt-1 text-success';
        } else {
            passwordMatch.textContent = '✖ ' + t('pwd_mismatch', 'Passwords do not match');
            passwordMatch.className = 'small mt-1 text-danger';
        }
    }

    // ===== EVENTS =====
    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updatePasswordCounter();
            renderComplexity(passwordInput.value);
            renderStrength(passwordInput.value);
            checkPasswordMatch();
        });
    }

    if (password2Input) {
        password2Input.addEventListener('input', checkPasswordMatch);
    }

    // ===== SUBMIT =====
    const form = document.querySelector('form');

    if (form) {
        form.addEventListener('submit', function (e) {

            if (oldPasswordInput && !oldPasswordInput.value) {
                e.preventDefault();
                say(t('enter_current_password', 'Enter your current password')).then(() => oldPasswordInput.focus());
                return;
            }

            if (!passwordInput || !passwordInput.value) {
                e.preventDefault();
                say(t('enter_new_password', 'Enter a new password')).then(() => passwordInput?.focus());
                return;
            }

            const password = passwordInput.value;
            const lengthValidation = validatePasswordLength(password);

            if (!lengthValidation.valid) {
                e.preventDefault();
                say(lengthValidation.message).then(() => passwordInput.focus());
                return;
            }

            if (REQUIRE_COMPLEX_PASSWORDS === 1) {
                const c = checkComplexity(password);
                if (!(c.uppercase && c.lowercase && c.number && c.special)) {
                    e.preventDefault();
                    say(t('pwd_weak', 'Password is too weak')).then(() => passwordInput.focus());
                    return;
                }
            }

            if (password2Input && password !== password2Input.value) {
                e.preventDefault();
                say(t('pwd_mismatch', 'Passwords do not match')).then(() => password2Input.focus());
                return;
            }

            if (submitBtn) {
                const spin = document.createElement('i');
                spin.className = 'fas fa-spinner fa-spin me-2';
                submitBtn.replaceChildren(spin, document.createTextNode(' ' + t('updating', 'Updating...')));
            }
        });
    }
});