'use strict';

(function () {
    const form            = document.getElementById('massInviteForm');
    const previewBox      = document.getElementById('previewBox');
    const previewCount    = document.getElementById('previewCount');
    const estimatedChange = document.getElementById('estimatedChange');
    const previewBtn      = document.getElementById('previewBtn');
    if (!form || !previewBox) return;

    let timer = null, seq = 0, last = null;

    const params = () => {
        const amount = parseInt(document.getElementById('amount').value, 10) || 0;
        const type   = form.querySelector('input[name="type"]:checked')?.value || '+';
        const sel    = form.querySelector('[name="usergroup"]');
        const group  = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text.trim() : '';
        return { amount, type, group: (!sel || sel.value === '-' || sel.value === '') ? 'all users' : group };
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
            estimatedChange.textContent = 'No changes';
            estimatedChange.className = 'text-body-secondary';
        } else {
            estimatedChange.textContent = (type === '+' ? '+' : '−') + last.change.toLocaleString() + ' invites';
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
        const msg = err?.message || 'Something went wrong';
        if (window.Swal) Swal.fire({ title: 'Error', text: msg, icon: 'error' });
        else alert(msg);
    }

    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const { amount, type, group } = params();
        if (amount < 1) { showError(new Error('Enter an amount between 1 and 10000.')); return; }

        // Свежие цифры прямо перед подтверждением (раньше брался текст из превью — мог устареть)
        let p;
        try { p = await fetchPreview(); } catch (err) { showError(err); return; }
        p = p || last || { count: 0, change: 0 };
        if (p.count === 0) { showError(new Error('Nobody matches — no invites would change.')); return; }

        const add   = type === '+';
        const title = (add ? 'Add ' : 'Remove ') + amount + ' invite(s)?';
        const html  = `
            <div class="text-start">
                <div class="d-flex align-items-center gap-3 p-3 rounded-4 mb-3" style="background:${add ? 'rgba(34,197,94,.08)' : 'rgba(239,68,68,.07)'}">
                    <i class="fa-solid ${add ? 'fa-plus' : 'fa-minus'} fa-lg ${add ? 'text-success' : 'text-danger'}"></i>
                    <div><div class="fw-bold">${esc(group)}</div>
                    <small class="text-body-secondary">${p.count.toLocaleString()} user(s) · ${add ? '+' : '−'}${p.change.toLocaleString()} invites in total</small></div>
                </div>
                <div class="small text-body-secondary"><i class="fa-solid fa-shield-halved me-1"></i>Never below zero · <i class="fa-solid fa-clipboard-list ms-1 me-1"></i>logged · <i class="fa-solid fa-triangle-exclamation ms-1 me-1 text-warning"></i>can't be undone</div>
            </div>`;

        const submitNow = () => {
            const b = document.getElementById('miGo') || form.querySelector('button[type="submit"]');
            if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying…'; }
            // Раньше отправка ждала 1,5 секунды «для красоты»
            HTMLFormElement.prototype.submit.call(form);
        };

        // Раньше без SweetAlert2 форма просто не отправлялась (preventDefault без запасного пути)
        if (!window.Swal) {
            if (confirm(title + '\n\n' + group + ': ' + p.count + ' user(s), ' + (add ? '+' : '−') + p.change + ' invites in total.')) submitNow();
            return;
        }

        const res = await Swal.fire({
            title, html,
            icon: add ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: add ? `Add ${amount}` : `Remove ${amount}`,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusCancel: !add,
            confirmButtonColor: add ? '#16a34a' : '#dc2626',
            cancelButtonColor: '#6c757d',
        });
        if (res.isConfirmed) {
            Swal.fire({ title: 'Applying…', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            submitNow();
        }
    });

    // Раньше: вставка глобальных стилей (.btn-group .btn с !important, .progress-bar,
    // .form-control:focus для всей страницы) и «звук при наведении» с битым аудио —
    // всё убрано, оформление теперь в самом massinvite.php
    schedule();
})();