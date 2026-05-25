document.addEventListener('DOMContentLoaded', function () {

    const config = window.passwordConfig || {};
    const MIN_PASSWORD_LENGTH = config.minLength || 6;
    const MAX_PASSWORD_LENGTH = config.maxLength || 30;
    const REQUIRE_COMPLEX_PASSWORDS = config.requireComplex || 0;

    const passwordInput = document.getElementById('password');
    const password2Input = document.getElementById('password2');
    const oldPasswordInput = document.getElementById('oldpassword');
    const submitBtn = document.getElementById('submitBtn');

    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const passwordLengthSpan = document.getElementById('passwordLength');
    const passwordCharCounter = document.getElementById('passwordCharCounter');
    const passwordMatch = document.getElementById('passwordMatch');

    const complexitySection = document.getElementById('complexitySection');

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

    function validatePasswordLength(password) {
        const length = password.length;

        if (length < MIN_PASSWORD_LENGTH) {
            return { valid: false, message: `Min ${MIN_PASSWORD_LENGTH} chars` };
        }
        if (length > MAX_PASSWORD_LENGTH) {
            return { valid: false, message: `Max ${MAX_PASSWORD_LENGTH} chars` };
        }
        return { valid: true };
    }

    function updatePasswordCounter() {
        if (!passwordInput) return;

        const length = passwordInput.value.length;
        if (passwordLengthSpan) passwordLengthSpan.textContent = length;

        if (!passwordCharCounter) return;

        passwordCharCounter.classList.remove('warning', 'danger');

        if (length > MAX_PASSWORD_LENGTH) {
            passwordCharCounter.classList.add('danger');
        } else if (length > MAX_PASSWORD_LENGTH * 0.8) {
            passwordCharCounter.classList.add('warning');
        }
    }

    // ===== MATCH CHECK =====
    function checkPasswordMatch() {
        if (!passwordInput || !password2Input || !passwordMatch) return;

        if (password2Input.value.length === 0) {
            passwordMatch.innerHTML = '';
            return;
        }

        if (passwordInput.value === password2Input.value) {
            passwordMatch.innerHTML = '✔ Passwords match';
            passwordMatch.className = 'text-success';
        } else {
            passwordMatch.innerHTML = '✖ Passwords do not match';
            passwordMatch.className = 'text-danger';
        }
    }

    // ===== EVENTS =====
    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updatePasswordCounter();
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
                alert('Enter current password');
                return;
            }

            if (!passwordInput || !passwordInput.value) {
                e.preventDefault();
                alert('Enter new password');
                return;
            }

            const password = passwordInput.value;
            const lengthValidation = validatePasswordLength(password);

            if (!lengthValidation.valid) {
                e.preventDefault();
                alert(lengthValidation.message);
                return;
            }

            if (REQUIRE_COMPLEX_PASSWORDS === 1) {
                const c = checkComplexity(password);
                if (!(c.uppercase && c.lowercase && c.number && c.special)) {
                    e.preventDefault();
                    alert('Password too weak');
                    return;
                }
            }

            if (password2Input && password !== password2Input.value) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }

            if (submitBtn) {
                submitBtn.innerHTML = 'Updating...';
            }
        });
    }

    console.log(`Password rules: ${MIN_PASSWORD_LENGTH}-${MAX_PASSWORD_LENGTH}, complex=${REQUIRE_COMPLEX_PASSWORDS}`);
});