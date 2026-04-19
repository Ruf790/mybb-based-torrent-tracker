'use strict';

document.addEventListener('DOMContentLoaded', () => {

// ── Form ──────────────────────────────────────────────────────────────────────

const form = document.getElementById('registration_form');
if (!form) return;

// ── Error messages ────────────────────────────────────────────────────────────

const ERR = {
    required: lang.js_validator_not_empty,
    username: {
        required: lang.js_validator_no_username,
        length:   lang.js_validator_username_length,
        taken:    'This username is already taken',
    },
    email: {
        invalid:  'Please enter a valid email address',
        match:    'Email addresses do not match',
        taken:    'This email is already registered',
    },
    password: {
        length:   lang.js_validator_password_length,
        matches:  'Passwords do not match',
        security: 'Password is too simple. Please use a stronger password',
    },
};

// ── Styles ────────────────────────────────────────────────────────────────────

const style = document.createElement('style');
style.textContent = `
.rv-ok  { border-color: #059669 !important; background: #f0fdf4 !important; }
.rv-err { border-color: #dc2626 !important; background: #fef2f2 !important; }

.rv-msg {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    animation: rv-slide .25s ease-out;
}
.rv-msg-ok    { background: linear-gradient(135deg,#f0fdf4,#dcfce7); border-left: 4px solid #059669; color: #065f46; }
.rv-msg-error { background: linear-gradient(135deg,#fef2f2,#fee2e2); border-left: 4px solid #dc2626; color: #991b1b; }
.rv-msg-ok::before    { content: "✓"; font-weight: 700; }
.rv-msg-error::before { content: "⚠"; }

@keyframes rv-slide {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: none; }
}

@keyframes rv-shake {
    0%,100% { transform: translateX(0); }
    25%     { transform: translateX(-5px); }
    75%     { transform: translateX(5px); }
}

.rv-shake { animation: rv-shake .3s ease-in-out; }

/* Password strength */
.rv-strength {
    margin-top: 6px;
    height: 4px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}
.rv-strength-bar {
    height: 100%;
    width: 0%;
    border-radius: 4px;
    transition: width .3s ease, background .3s ease;
}
.rv-strength-label {
    margin-top: 4px;
    font-size: 11px;
    font-weight: 600;
    transition: color .3s ease;
}

/* Loading spinner */
.rv-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #e2e8f0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: rv-spin .6s linear infinite;
    vertical-align: middle;
    margin-left: 6px;
}
@keyframes rv-spin { to { transform: rotate(360deg); } }

/* Toast notification */
.rv-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: .9rem;
    color: #fff;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    animation: rv-slide .3s ease-out;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rv-toast-ok  { background: linear-gradient(135deg,#10b981,#059669); }
.rv-toast-err { background: linear-gradient(135deg,#ef4444,#dc2626); }
`;
document.head.appendChild(style);

// ── UI helpers ────────────────────────────────────────────────────────────────

function getWrap(input) {
    return input.closest('.field-wrap') || input.parentNode;
}

function clearStatus(input) {
    const wrap = getWrap(input);
    wrap.querySelectorAll('.rv-msg').forEach(el => el.remove());
    input.classList.remove('rv-ok', 'rv-err');
}

function setOk(input, message) {
    clearStatus(input);
    input.classList.add('rv-ok');
    const msg = document.createElement('div');
    msg.className = 'rv-msg rv-msg-ok';
    msg.textContent = message;
    getWrap(input).appendChild(msg);
}

function setError(input, message) {
    clearStatus(input);
    input.classList.add('rv-err');
    const msg = document.createElement('div');
    msg.className = 'rv-msg rv-msg-error';
    msg.textContent = message;
    getWrap(input).appendChild(msg);
    input.classList.add('rv-shake');
    setTimeout(() => input.classList.remove('rv-shake'), 300);
}

function showSpinner(input) {
    const s = document.createElement('span');
    s.className = 'rv-spinner rv-spinner-inst';
    getWrap(input).appendChild(s);
    return s;
}

function hideSpinner(input) {
    getWrap(input).querySelectorAll('.rv-spinner-inst').forEach(el => el.remove());
}

function toast(message, type = 'ok', duration = 2500) {
    const el = document.createElement('div');
    el.className = 'rv-toast rv-toast-' + type;
    el.innerHTML = (type === 'ok' ? '✓ ' : '⚠ ') + message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// ── Password strength ─────────────────────────────────────────────────────────

function initStrengthBar(input) {
    if (getWrap(input).querySelector('.rv-strength')) return;

    const bar  = document.createElement('div');
    bar.className = 'rv-strength';
    bar.innerHTML = '<div class="rv-strength-bar"></div>';

    const label = document.createElement('div');
    label.className = 'rv-strength-label';

    getWrap(input).appendChild(bar);
    getWrap(input).appendChild(label);
}

function updateStrength(input) {
    const val = input.value;
    let score = 0;
    if (val.length >= 8)          score++;
    if (/[a-z]/.test(val))        score++;
    if (/[A-Z]/.test(val))        score++;
    if (/[0-9]/.test(val))        score++;
    if (/[$@#&!%^*]/.test(val))   score++;

    const levels = [
        { label: 'Very weak', color: '#ef4444' },
        { label: 'Weak',      color: '#f97316' },
        { label: 'Fair',      color: '#eab308' },
        { label: 'Good',      color: '#22c55e' },
        { label: 'Strong',    color: '#059669' },
    ];

    const lvl   = levels[Math.min(score, 4)];
    const pct   = (score / 5) * 100;
    const wrap  = getWrap(input);
    const bar   = wrap.querySelector('.rv-strength-bar');
    const label = wrap.querySelector('.rv-strength-label');

    if (bar)   { bar.style.width = pct + '%'; bar.style.background = lvl.color; }
    if (label) { label.textContent = 'Password strength: ' + lvl.label; label.style.color = lvl.color; }
}

// ── Remote check ──────────────────────────────────────────────────────────────

async function checkRemote(url, data) {
    try {
        const fd = new FormData();
        fd.append('my_post_key', my_post_key);
        for (const [k, v] of Object.entries(data)) fd.append(k, v);

        const res  = await fetch(url, { method: 'POST', body: fd });
        const text = await res.text();

        let result;
        try { result = JSON.parse(text); } catch { result = text; }

        if (result === 'true' || result?.valid === true)  return { valid: true };
        if (typeof result === 'string')                   return { valid: false, message: result };
        if (result?.errors?.length)                       return { valid: false, message: result.errors[0] };
        if (result?.error)                                return { valid: false, message: result.error };
        return { valid: true };
    } catch {
        return { valid: false, message: 'Server error. Please try again.' };
    }
}

// ── Validators ────────────────────────────────────────────────────────────────

const validators = {

    username: async val => {
        if (!val)  return ERR.username.required;
        if (val.length < +regsettings.minnamelength || val.length > +regsettings.maxnamelength)
            return ERR.username.length;
        const r = await checkRemote('xmlhttp.php?action=username_availability', { username: val });
        return r.valid ? null : (r.message || ERR.username.taken);
    },

    email: async val => {
        if (!val)  return ERR.required;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return ERR.email.invalid;
        const r = await checkRemote('xmlhttp.php?action=email_availability', { email: val });
        return r.valid ? null : (r.message || ERR.email.taken);
    },

    email2: val => {
        const email = document.getElementById('email')?.value;
        if (!val)          return ERR.required;
        if (val !== email) return ERR.email.match;
        return null;
    },

    password: async val => {
        if (!val || val.length < +regsettings.minpasswordlength) return ERR.password.length;

        const u = document.getElementById('username')?.value;
        const e = document.getElementById('email')?.value;

        if (
            (e && (val === e || val.includes(e) || e.includes(val))) ||
            (u && (val === u || val.includes(u) || u.includes(val)))
        ) return ERR.password.security;

        if (regsettings.requirecomplexpasswords == '1') {
            const r = await checkRemote('xmlhttp.php?action=complex_password', { password: val });
            if (!r.valid) return r.message || 'Password does not meet complexity requirements';
        }
        return null;
    },

    password2: val => {
        const pass = document.getElementById('password')?.value;
        if (!val)          return ERR.password.length;
        if (val !== pass)  return ERR.password.matches;
        return null;
    },
};

// Success messages per field
const SUCCESS = {
    username:  'Username is available',
    email:     'Email address is valid',
    email2:    'Email addresses match',
    password:  'Password looks good',
    password2: 'Passwords match',
};

// ── Debounce ──────────────────────────────────────────────────────────────────

const debounceTimers = {};
function debounce(key, fn, delay = 650) {
    clearTimeout(debounceTimers[key]);
    debounceTimers[key] = setTimeout(fn, delay);
}

// ── Field validation ──────────────────────────────────────────────────────────

const REMOTE = new Set(['username', 'email', 'password']);

async function validateField(input) {
    const name      = input.name.replace('[]', '');
    const validator = validators[name];
    if (!validator) return true;

    clearStatus(input);

    let spinner = null;
    if (REMOTE.has(name) && input.value) {
        spinner = showSpinner(input);
    }

    let error;
    try {
        error = await validator(input.value);
    } finally {
        if (spinner) hideSpinner(input);
    }

    if (error) {
        setError(input, error);
        return false;
    }

    if (input.value && SUCCESS[name]) {
        setOk(input, SUCCESS[name]);
    }

    return true;
}

// ── Bind events ───────────────────────────────────────────────────────────────

['username', 'email', 'email2', 'password', 'password2'].forEach(name => {
    const el = document.getElementById(name);
    if (!el) return;

    // Password strength bar
    if (name === 'password') {
        initStrengthBar(el);
        el.addEventListener('input', () => { if (el.value) updateStrength(el); });
    }

    // Clear on focus
    el.addEventListener('focus', () => clearStatus(el));

    // Validate on blur
    el.addEventListener('blur', () => {
        if (el.value) validateField(el);
    });

    // Debounce on input
    el.addEventListener('input', () => {
        clearStatus(el);
        if (!el.value) return;

        const delay = REMOTE.has(name) ? 700 : 350;
        debounce(name, () => validateField(el), delay);
    });
});

// ── Form submit ───────────────────────────────────────────────────────────────

form.addEventListener('submit', async e => {
    e.preventDefault();

    const submitBtn  = form.querySelector('button[type="submit"], input[type="submit"]');
    const origLabel  = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="rv-spinner"></span> Checking...';

    const fields = ['username', 'email', 'email2'];
    if (regsettings.regtype !== 'randompass') fields.push('password', 'password2');

    // Validate all in parallel
    const results = await Promise.all(
        fields.map(name => {
            const el = document.getElementById(name);
            return el ? validateField(el) : Promise.resolve(true);
        })
    );

    submitBtn.disabled  = false;
    submitBtn.innerHTML = origLabel;

    const allValid = results.every(Boolean);

    if (allValid) {
        toast('All fields validated! Submitting...', 'ok', 1800);
        setTimeout(() => form.submit(), 400);
    } else {
        toast('Please fix the errors before submitting', 'err', 3000);
        // Scroll to first error
        const firstErr = form.querySelector('.rv-err');
        firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

}); // DOMContentLoaded