// ========= PHP VARIABLES FOR JS =========
const MIN_USERNAME_LENGTH = parseInt(window.MIN_USERNAME_LENGTH) || 3;
const MAX_USERNAME_LENGTH = parseInt(window.MAX_USERNAME_LENGTH) || 30;

// ========= SHOW/HIDE PASSWORD =========
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
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
            message: '⚠️ Username must be at least ' + MIN_USERNAME_LENGTH + ' characters long (current: ' + username.length + ')' 
        };
    }

    if (username.length > MAX_USERNAME_LENGTH) {
        return { 
            valid: false, 
            message: '❌ Username exceeds maximum length of ' + MAX_USERNAME_LENGTH + ' characters' 
        };
    }

    const validPattern = /^[a-zA-Z0-9\s\_\-]+$/;
    if (!validPattern.test(username)) {
        return { valid: false, message: '❌ Username can only contain letters, numbers, spaces, underscores, and hyphens' };
    }

    if (username.trim().length === 0) {
        return { valid: false, message: '❌ Username cannot be empty or only spaces' };
    }

    if (username !== username.trim()) {
        return { valid: false, message: '⚠️ Username cannot have leading or trailing spaces' };
    }

    if (/\s{2,}/.test(username)) {
        return { valid: false, message: '⚠️ Username cannot have multiple consecutive spaces' };
    }

    return { valid: true, message: '✅ Username looks good!' };
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
        usernameFeedback.innerHTML = '';
        usernameFeedback.className = 'username-feedback';
        return;
    }

    if (result.valid) {
        usernameFeedback.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + result.message;
        usernameFeedback.className = 'username-feedback valid';
    } else {
        usernameFeedback.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + result.message;
        usernameFeedback.className = 'username-feedback invalid';
    }
}

if (usernameInput) {
    usernameInput.addEventListener('input', updateUsernameFeedback);
    updateUsernameFeedback();
}

// ========= FORM SUBMIT VALIDATION =========
const form = document.querySelector('form');
const submitBtn = document.getElementById('submitBtn');
const passwordInput = document.getElementById('password');

if (form) {
    form.addEventListener('submit', function(e) {
        if (usernameInput && usernameInput.value) {
            const username = usernameInput.value;
            const validation = validateUsername(username);

            if (validation.valid === false) {
                e.preventDefault();
                alert(validation.message);
                usernameInput.focus();
                return;
            }

            if (username.trim().length === 0) {
                e.preventDefault();
                alert('Please enter a username.');
                usernameInput.focus();
                return;
            }
        } else {
            e.preventDefault();
            alert('Please enter a username.');
            usernameInput.focus();
            return;
        }

        if (passwordInput && !passwordInput.value) {
            e.preventDefault();
            alert('Please enter your current password to confirm the change.');
            passwordInput.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
        }
    });
}

// ========= PREVENT EMPTY USERNAME =========
if (usernameInput) {
    usernameInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            this.value = '';
            updateUsernameFeedback();
        }
    });
}

// ========= REAL-TIME LENGTH CHECK =========
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
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
    usernameLabel.title = 'Username must be ' + MIN_USERNAME_LENGTH + '-' + MAX_USERNAME_LENGTH + 
                          ' characters and contain only letters, numbers, spaces, underscores, and hyphens';
}

console.log('Username validation rules:', MIN_USERNAME_LENGTH, MAX_USERNAME_LENGTH);