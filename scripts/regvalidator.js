'use strict';

/* Registration form (member.php?action=register), st-ui / auth.css look.
   Styles live in auth.css (.au-page .rv-*), strings come from lang.js_* (member.php),
   with English fallbacks. Server checks: xmlhttp.php username / email / complex password. */

document.addEventListener('DOMContentLoaded', () => {

const form = document.getElementById('registration_form');
if (!form) return;

const L   = window.lang || {};
const RS  = window.regsettings || {};
const t   = (key, fallback) => (L[key] !== undefined && L[key] !== '' ? String(L[key]) : fallback);

const MSG = {
    required:      t('js_validator_not_empty', 'This field is required'),
    userRequired:  t('js_validator_no_username', 'Please enter a username'),
    userLength:    t('js_validator_username_length', 'Username length is not allowed'),
    userTaken:     t('js_rv_username_taken', 'This username is already taken'),
    userOk:        t('js_rv_username_ok', 'Username is available'),
    emailInvalid:  t('js_validator_invalid_email', 'Please enter a valid email address'),
    emailTaken:    t('js_rv_email_taken', 'This email is already registered'),
    emailOk:       t('js_rv_email_ok', 'Email address is valid'),
    emailMatch:    t('js_validator_email_match', 'Email addresses do not match'),
    email2Ok:      t('js_rv_email2_ok', 'Email addresses match'),
    passLength:    t('js_validator_password_length', 'Password is too short'),
    passSecurity:  t('js_validator_bad_password_security', 'Password must not contain your username or email'),
    passOk:        t('js_rv_password_ok', 'Password looks good'),
    passMatch:     t('js_validator_password_matches', 'Passwords do not match'),
    pass2Ok:       t('js_rv_password2_ok', 'Passwords match'),
    serverError:   t('js_rv_server_error', 'Server error. Please try again.'),
    checking:      t('js_rv_checking', 'Checking…'),
    fixErrors:     t('js_rv_fix_errors', 'Please fix the highlighted fields'),
    caps:          t('js_rv_caps', 'Caps Lock is on'),
    show:          t('js_rv_show', 'Show password'),
    hide:          t('js_rv_hide', 'Hide password'),
    strength:      t('js_rv_strength', 'Strength'),
    levels: [
        t('js_rv_s1', 'weak'),
        t('js_rv_s2', 'fair'),
        t('js_rv_s3', 'good'),
        t('js_rv_s4', 'strong'),
    ],
};

// Server strings may hold entities (&quot; …) — show them as text
const plain = s => {
    const d = document.createElement('div');
    d.innerHTML = String(s);
    return d.textContent || '';
};

// ── UI ────────────────────────────────────────────────────────────────────────

const wrapOf  = input => input.closest('.field-wrap') || input.parentNode;
const boxOf   = input => input.closest('.au-input') || input.parentNode;

function feedback(input) {
    const wrap = wrapOf(input);
    let fb = wrap.querySelector(':scope > .rv-fb');
    if (!fb) {
        fb = document.createElement('div');
        fb.className = 'rv-fb';
        fb.setAttribute('aria-live', 'polite');
        // under the strength meter if there is one
        const meter = wrap.querySelector(':scope > .rv-meter');
        meter ? meter.after(fb) : boxOf(input).after(fb);
    }
    return fb;
}

function setState(input, state, text = '') {
    input.classList.toggle('is-valid', state === 'ok');
    input.classList.toggle('is-invalid', state === 'err');
    input.setAttribute('aria-invalid', state === 'err' ? 'true' : 'false');
    boxOf(input).classList.toggle('rv-busy', state === 'busy');

    const fb = feedback(input);
    if (state === 'ok' || state === 'err') {
        fb.className = 'rv-fb is-' + state;
        fb.innerHTML = '<i class="fa-solid ' + (state === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i><span></span>';
        fb.lastChild.textContent = text;
    } else {
        fb.className = 'rv-fb';
        fb.textContent = '';
    }
}

function shake(input) {
    const box = boxOf(input);
    box.classList.remove('rv-shake');
    void box.offsetWidth;   // restart the animation
    box.classList.add('rv-shake');
}

// ── Password: show/hide, Caps Lock, strength ─────────────────────────────────

function addEye(input) {
    const box = boxOf(input);
    if (box.querySelector('.rv-eye')) return;
    box.classList.add('rv-has-eye');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'rv-eye';
    btn.title = MSG.show;
    btn.setAttribute('aria-label', MSG.show);
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = '<i class="fa-solid ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        btn.title = show ? MSG.hide : MSG.show;
        btn.setAttribute('aria-label', btn.title);
        input.focus();
    });
    box.appendChild(btn);
}

function addCaps(input) {
    const note = document.createElement('div');
    note.className = 'rv-caps';
    note.innerHTML = '<i class="fa-solid fa-arrow-up-from-bracket"></i><span></span>';
    note.lastChild.textContent = MSG.caps;
    note.hidden = true;
    boxOf(input).after(note);
    const check = e => { note.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); };
    input.addEventListener('keydown', check);
    input.addEventListener('keyup', check);
    input.addEventListener('blur', () => { note.hidden = true; });
}

function addMeter(input) {
    const meter = document.createElement('div');
    meter.className = 'rv-meter';
    meter.hidden = true;
    meter.innerHTML = '<div class="rv-meter__bars"><i></i><i></i><i></i><i></i></div><span class="rv-meter__lbl"></span>';
    boxOf(input).after(meter);
    return meter;
}

function scorePassword(v) {
    if (!v) return 0;
    let s = 0;
    if (v.length >= Math.max(8, +RS.minpasswordlength || 0)) s++;
    if (v.length >= 12) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    if (/^(.)\1+$/.test(v) || /^(?:1234\d*|qwerty\w*|password\d*|пароль\d*|abc123\w*|111\d*)$/i.test(v)) s = Math.min(s, 1);
    return Math.max(1, Math.min(4, s));     // 1…4
}

function updateMeter(meter, value) {
    meter.hidden = !value;
    if (!value) return;
    const s = scorePassword(value);
    meter.dataset.level = String(s);
    meter.querySelector('.rv-meter__lbl').textContent = MSG.strength + ': ' + MSG.levels[s - 1];
}

// ── Server checks ─────────────────────────────────────────────────────────────

const lastRemote = {};     // field -> {value, error}: no repeated requests for the same value

async function remote(action, data) {
    const fd = new FormData();
    fd.append('my_post_key', window.my_post_key || '');
    for (const [k, v] of Object.entries(data)) fd.append(k, v);
    try {
        const res  = await fetch('xmlhttp.php?action=' + action, { method: 'POST', body: fd, credentials: 'same-origin' });
        const text = await res.text();
        let r;
        try { r = JSON.parse(text); } catch { r = text; }
        if (r === 'true' || r === true || r?.valid === true) return null;
        if (typeof r === 'string' && r.trim() !== '') return plain(r);
        if (r?.errors?.length) return plain(r.errors[0]);
        if (r?.error) return plain(r.error);
        return null;
    } catch {
        return MSG.serverError;
    }
}

async function remoteCached(field, action, data, value) {
    const c = lastRemote[field];
    if (c && c.value === value) return c.error;
    const error = await remote(action, data);
    if (error !== MSG.serverError) lastRemote[field] = { value, error };
    return error;
}

// ── Validators: return error text or null ─────────────────────────────────────

const val = id => document.getElementById(id)?.value ?? '';

const validators = {
    username: {
        remote: true,
        ok: MSG.userOk,
        run: async v => {
            v = v.trim();
            if (!v) return MSG.userRequired;
            if (v.length < +RS.minnamelength || v.length > +RS.maxnamelength) return MSG.userLength;
            return remoteCached('username', 'username_availability', { username: v }, v);
        },
    },
    email: {
        remote: true,
        ok: MSG.emailOk,
        run: async v => {
            v = v.trim();
            if (!v) return MSG.required;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return MSG.emailInvalid;
            return remoteCached('email', 'email_availability', { email: v }, v);
        },
    },
    email2: {
        ok: MSG.email2Ok,
        run: v => {
            if (!v.trim()) return MSG.required;
            return v.trim().toLowerCase() === val('email').trim().toLowerCase() ? null : MSG.emailMatch;
        },
    },
    password: {
        remote: RS.requirecomplexpasswords == '1',
        ok: MSG.passOk,
        run: async v => {
            if (!v || v.length < +RS.minpasswordlength) return MSG.passLength;
            if (RS.maxpasswordlength && v.length > +RS.maxpasswordlength) return MSG.passLength;
            const lv = v.toLowerCase();
            const u  = val('username').trim().toLowerCase();
            const e  = val('email').trim().toLowerCase();
            if ((u.length >= 3 && lv.includes(u)) || (e.length >= 3 && (lv.includes(e) || lv.includes(e.split('@')[0])))) {
                return MSG.passSecurity;
            }
            if (RS.requirecomplexpasswords == '1') {
                const err = await remoteCached('password', 'complex_password', { password: v }, v);
                return err || null;
            }
            return null;
        },
    },
    password2: {
        ok: MSG.pass2Ok,
        run: v => {
            if (!v) return MSG.required;
            return v === val('password') ? null : MSG.passMatch;
        },
    },
};

// Each check gets a ticket: an old answer arriving after a newer one is ignored
const ticket = {};

async function validateField(input, { silentEmpty = false } = {}) {
    const name = input.id;
    const v    = validators[name];
    if (!v) return true;

    if (silentEmpty && !input.value) {
        setState(input, 'none');
        return true;
    }

    const my = (ticket[name] = (ticket[name] || 0) + 1);
    if (v.remote && input.value) setState(input, 'busy');

    const error = await v.run(input.value);
    if (ticket[name] !== my) return !error;   // superseded

    if (error) {
        setState(input, 'err', error);
        return false;
    }
    setState(input, input.value ? 'ok' : 'none', v.ok);
    return true;
}

// ── Wiring ────────────────────────────────────────────────────────────────────

const timers = {};
const later  = (key, fn, ms) => { clearTimeout(timers[key]); timers[key] = setTimeout(fn, ms); };

// Fields that depend on others: re-check them when the "parent" changes
const dependents = { email: ['email2'], password: ['password2'], username: ['password'] };

const fields = ['username', 'email', 'email2', 'password', 'password2']
    .map(id => document.getElementById(id))
    .filter(Boolean);

let meter = null;

fields.forEach(el => {
    const name = el.id;

    if (name === 'password' || name === 'password2') {
        addEye(el);
        addCaps(el);
    }
    if (name === 'password') {
        meter = addMeter(el);
    }

    el.addEventListener('input', () => {
        if (name === 'password' && meter) updateMeter(meter, el.value);
        setState(el, 'none');
        if (!el.value) return;
        later(name, () => validateField(el), validators[name].remote ? 600 : 300);

        (dependents[name] || []).forEach(id => {
            const d = document.getElementById(id);
            if (d && d.value) later(id, () => validateField(d), 350);
        });
    });

    el.addEventListener('blur', () => {
        if (!el.value) return;
        clearTimeout(timers[name]);
        validateField(el);
    });
});

// ── Submit ────────────────────────────────────────────────────────────────────

let passed = false;
const alertBox = document.createElement('div');
alertBox.className = 'rv-alert';
alertBox.hidden = true;
alertBox.setAttribute('role', 'alert');
alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i><span></span>';
alertBox.lastChild.textContent = MSG.fixErrors;

const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
submitBtn?.before(alertBox);

form.addEventListener('submit', async e => {
    if (passed) return;
    e.preventDefault();

    const label = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + MSG.checking;
    }

    const check = fields.filter(el => !(RS.regtype === 'randompass' && el.id.startsWith('password')));
    const ok    = (await Promise.all(check.map(el => validateField(el)))).every(Boolean);

    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = label;
    }

    alertBox.hidden = ok;
    if (ok) {
        passed = true;
        // keep the name/value of the submit button (member.php reads regsubmit)
        if (submitBtn?.name) {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = submitBtn.name; h.value = submitBtn.value;
            form.appendChild(h);
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + label;
        }
        form.submit();
        return;
    }

    const first = form.querySelector('.is-invalid');
    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        shake(first);
        setTimeout(() => first.focus({ preventScroll: true }), 250);
    }
});

}); // DOMContentLoaded
