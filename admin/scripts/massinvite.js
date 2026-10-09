'use strict';

(function () {
    const form            = document.getElementById('massInviteForm');
    const previewBox      = document.getElementById('previewBox');
    const previewCount    = document.getElementById('previewCount');
    const estimatedChange = document.getElementById('estimatedChange');
    const previewBtn      = document.getElementById('previewBtn');
    if (!form || !previewBox) return;

    let timer = null, seq = 0, last = null;

    /** Перевод из AGS_LANG (ключи без js_) с английским fallback; {1} и %1$s → аргументы */
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};
    const t = (key, fallback, ...args) => {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        args.forEach((a, i) => { s = s.split('{' + (i + 1) + '}').join(String(a)).split('%' + (i + 1) + '$s').join(String(a)); });
        return s;
    };
    const MAX = parseInt(window.massInviteMax, 10) || 10000;

    const params = () => {
        const amount = parseInt(document.getElementById('amount').value, 10) || 0;
        const type   = form.querySelector('input[name="type"]:checked')?.value || '+';
        const sel    = form.querySelector('[name="usergroup"]');
        const group  = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text.trim() : '';
        return { amount, type, group: (!sel || sel.value === '-' || sel.value === '') ? t('all_users', 'all users') : group };
    };

    /** Запрос числа затронутых пользователей и точного изменения */
    function fetchPreview() {
        const { amount } = params();
        if (amount < 1) return Promise.resolve(null);
        const data = new FormData(form);
        data.set('preview', 'yes');
        data.delete('doit');
        const my = ++seq;
        return fetch(window.massInviteScript, { method: 'POST', body: data, credentials: 'same-origin' })
            // при ошибке сервер отвечает 403 с JSON {error: …}
            .then(r => r.json())
            .then(json => {
                if (json.error) throw new Error(json.error);
                if (my !== seq) return null;          // устаревший ответ
                last = { count: parseInt(json.count, 10) || 0, change: parseInt(json.change ?? 0, 10) || 0 };
                render();
                return last;
            });
    }

    function render() {
        if (!last) return;
        const { type } = params();
        previewBox.classList.remove('d-none');
        previewCount.textContent = last.count.toLocaleString();
        // Раньше здесь выводилось «+5 invites» — то есть значение НА ОДНОГО, а не итог
        if (last.count === 0) {
            estimatedChange.textContent = t('no_changes', 'No changes');
            estimatedChange.className = 'text-body-secondary';
        } else {
            estimatedChange.textContent = t('change', '{1}{2} invites', type === '+' ? '+' : '−', last.change.toLocaleString());
            estimatedChange.className = type === '+' ? 'text-success' : 'text-danger';
        }
        previewBox.style.borderColor = last.count === 0 ? 'rgba(245,158,11,.6)' : '';
    }

    // Раньше запрос уходил на КАЖДОЕ нажатие клавиши (input + change) — теперь с паузой
    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(() => fetchPreview().catch(err => console.error('Preview:', err)), 300);
    }

    form.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('input', schedule);
        el.addEventListener('change', schedule);
    });
    previewBtn?.addEventListener('click', () => fetchPreview().catch(showError));
    form.addEventListener('reset', () => setTimeout(schedule, 0));

    function showError(err) {
        const msg = err?.message || t('error_generic', 'Something went wrong');
        if (window.Swal) Swal.fire({ titleText: t('error_title', 'Error'), text: msg, icon: 'error' });
        else alert(msg);
    }

    /** Элемент с классами и текстом (только textContent, без innerHTML) */
    const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    };

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const { amount, type, group } = params();
        if (amount < 1) { showError(new Error(t('amount_range', 'Enter an amount between 1 and {1}.', MAX))); return; }

        // Свежие цифры прямо перед подтверждением (раньше брался текст из превью — мог устареть)
        let p;
        try { p = await fetchPreview(); } catch (err) { showError(err); return; }
        p = p || last || { count: 0, change: 0 };
        if (p.count === 0) { showError(new Error(t('nobody', 'Nobody matches — no invites would change.'))); return; }

        const add   = type === '+';
        const sign  = add ? '+' : '−';
        const title = add ? t('confirm_add', 'Add {1} invite(s)?', amount) : t('confirm_remove', 'Remove {1} invite(s)?', amount);

        // Та же разметка, что раньше, но тексты — через textContent
        const html = el('div', 'text-start');
        const box  = el('div', 'd-flex align-items-center gap-3 p-3 rounded-4 mb-3');
        box.style.background = add ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.07)';
        box.append(el('i', `fa-solid ${add ? 'fa-plus' : 'fa-minus'} fa-lg ${add ? 'text-success' : 'text-danger'}`));
        const info = el('div');
        info.append(el('div', 'fw-bold', group),
            el('small', 'text-body-secondary', t('confirm_summary', '{1} user(s) · {2}{3} invites in total', p.count.toLocaleString(), sign, p.change.toLocaleString())));
        box.append(info);
        const foot = el('div', 'small text-body-secondary');
        foot.append(el('i', 'fa-solid fa-shield-halved me-1'), document.createTextNode(t('never_below_zero', 'Never below zero') + ' · '),
            el('i', 'fa-solid fa-clipboard-list ms-1 me-1'), document.createTextNode(t('logged', 'logged') + ' · '),
            el('i', 'fa-solid fa-triangle-exclamation ms-1 me-1 text-warning'), document.createTextNode(t('cant_undo', "can't be undone")));
        html.append(box, foot);

        const submitNow = () => {
            const b = document.getElementById('miGo') || form.querySelector('button[type="submit"]');
            if (b) { b.disabled = true; b.replaceChildren(el('span', 'spinner-border spinner-border-sm me-1'), document.createTextNode(t('applying', 'Applying…'))); }
            // Раньше отправка ждала 1,5 секунды «для красоты»
            HTMLFormElement.prototype.submit.call(form);
        };

        // Раньше без SweetAlert2 форма просто не отправлялась (preventDefault без запасного пути)
        if (!window.Swal) {
            if (confirm(title + '\n\n' + t('confirm_plain', '{1}: {2} user(s), {3}{4} invites in total.', group, p.count, sign, p.change))) submitNow();
            return;
        }

        const res = await Swal.fire({
            titleText: title, html,
            icon: add ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: add ? t('btn_add', 'Add {1}', amount) : t('btn_remove', 'Remove {1}', amount),
            cancelButtonText: t('cancel', 'Cancel'),
            reverseButtons: true,
            focusCancel: !add,
            confirmButtonColor: add ? '#16a34a' : '#dc2626',
            cancelButtonColor: '#6c757d',
        });
        if (res.isConfirmed) {
            Swal.fire({ titleText: t('applying', 'Applying…'), allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            submitNow();
        }
    });

    // Раньше: вставка глобальных стилей (.btn-group .btn с !important, .progress-bar,
    // .form-control:focus для всей страницы) и «звук при наведении» с битым аудио —
    // всё убрано, оформление теперь в самом massinvite.php
    schedule();
})();