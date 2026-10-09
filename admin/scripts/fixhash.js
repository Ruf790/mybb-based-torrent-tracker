/* Fix Torrent Hashes — apply (AJAX POST + CSRF), Auto Fix, copy hash */
(function () {
    'use strict';

    const root = document.getElementById('fhPage');
    if (!root) return;

    // ── Lang: AGS_LANG выводится PHP перед подключением скрипта ────────
    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

    // t(key, fallback, ...args) — подставляет {1}… и %1$s… ($lang->load() конвертирует {N} в %N$s)
    function t(key, fallback) {
        let s = (typeof L[key] === 'string') ? L[key] : fallback;
        for (let i = 2; i < arguments.length; i++) {
            const n = i - 1, v = String(arguments[i]);
            s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
        }
        return s;
    }

    const currentPage = parseInt(root.dataset.page, 10) || 1;
    const totalPages  = parseInt(root.dataset.totalPages, 10) || 1;
    const mismatch    = parseInt(root.dataset.mismatch, 10) || 0;
    const autoMode    = root.dataset.auto === '1';
    const stopUrl     = root.dataset.stopUrl || '';

    const applyForm = document.getElementById('applyForm');
    const applyBtn  = document.getElementById('applyBtn');
    const hasSwal   = typeof window.Swal !== 'undefined';

    // ── SweetAlert2 с fallback (title/text — только как текст) ─────────
    function confirmBox(title, text) {
        if (!hasSwal) return Promise.resolve(window.confirm(title + '\n\n' + text));
        return Swal.fire({
            icon: 'question',
            titleText: title,
            text: text,
            showCancelButton: true,
            confirmButtonText: t('btn_confirm', 'Apply fixes'),
            cancelButtonText: t('btn_cancel', 'Cancel'),
            reverseButtons: true,
            focusCancel: true
        }).then(r => r.isConfirmed);
    }

    function notify(icon, title, text) {
        if (!hasSwal) {
            window.alert(title + (text ? '\n\n' + text : ''));
            return Promise.resolve();
        }
        return Swal.fire({ icon: icon, titleText: title, text: text || '' });
    }

    function toast(icon, title) {
        if (!hasSwal) return;
        Swal.fire({
            toast: true, position: 'bottom-end', icon: icon, titleText: title,
            showConfirmButton: false, timer: 1400, timerProgressBar: true
        });
    }

    // ── Apply ───────────────────────────────────────────────────────────
    let originalBtnHtml = applyBtn ? applyBtn.innerHTML : '';

    function setBusy(busy) {
        if (!applyBtn) return;
        applyBtn.disabled = busy;
        if (busy) {
            const spin = document.createElement('span');
            spin.className = 'spinner-border spinner-border-sm me-2';
            spin.setAttribute('aria-hidden', 'true');
            applyBtn.replaceChildren(spin, document.createTextNode(t('applying', 'Applying…')));
        } else {
            applyBtn.innerHTML = originalBtnHtml; // исходная серверная разметка кнопки
        }
    }

    function postApply() {
        return fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: new FormData(applyForm),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(r => r.json());
    }

    function resultText(d) {
        let s = t('result_fixed', 'Fixed {1} torrent(s)', d.fixed);
        if (d.errors)  s += t('result_errors', ', {1} error(s)', d.errors);
        if (d.skipped) s += t('result_skipped', ', {1} skipped', d.skipped);
        return s + '.';
    }

    if (applyForm && applyBtn) {
        applyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (applyBtn.disabled) return;

            confirmBox(
                t('confirm_title', 'Apply fixes?'),
                t('confirm_text', 'The stored info_hash will be replaced for {1} torrent(s) on page {2}.', mismatch, currentPage)
            ).then(function (ok) {
                if (!ok) return;
                setBusy(true);
                postApply()
                    .then(function (data) {
                        if (data && data.success) {
                            notify(data.errors ? 'warning' : 'success', t('applied_title', 'Fixes applied'), resultText(data))
                                .then(() => window.location.reload());
                        } else {
                            notify('error', t('not_applied', 'Fixes not applied'), (data && data.error) || t('unknown_error', 'Unknown error'));
                            setBusy(false);
                        }
                    })
                    .catch(function () {
                        notify('error', t('req_failed', 'Request failed'), t('req_failed_text', 'The server did not return a valid response. Try again.'));
                        setBusy(false);
                    });
            });
        });
    }

    // ── Auto switch: применяем сразу при переключении ──────────────────
    const autoSwitch = document.getElementById('autoRefreshSwitch');
    const filterForm = document.getElementById('fhFilterForm');
    if (autoSwitch && filterForm) {
        autoSwitch.addEventListener('change', () => filterForm.submit());
    }

    // ── Copy hash ───────────────────────────────────────────────────────
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy') ? resolve() : reject(); }
            catch (err) { reject(err); }
            finally { ta.remove(); }
        });
    }

    root.addEventListener('click', function (e) {
        const btn = e.target.closest('.fh-copy');
        if (!btn) return;
        copyText(btn.dataset.copy || '')
            .then(function () {
                btn.classList.add('is-copied');
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i>';
                }, 1200);
                toast('success', t('copied', 'Hash copied'));
            })
            .catch(() => toast('error', t('copy_failed', 'Copy failed')));
    });

    // ── Auto Fix ────────────────────────────────────────────────────────
    if (!autoMode) return;

    const statusText = document.querySelector('#fhAutoStatus .fh-auto-text');
    const needsFix = applyBtn && !applyBtn.disabled;
    let left = 10;

    function render() {
        if (statusText) {
            statusText.textContent = needsFix
                ? t('auto_fix_in', 'Fixing in {1}s', left)
                : t('auto_next_in', 'Next page in {1}s', left);
        }
    }

    function goNext() {
        if (currentPage < totalPages) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', String(currentPage + 1));
            url.searchParams.set('auto', '1');
            window.location.href = url.toString();
            return;
        }
        if (statusText) statusText.textContent = t('auto_finished', 'Finished');
        notify('success', t('finished_title', 'Auto Fix finished'), t('finished_text', 'All {1} page(s) have been processed.', totalPages))
            .then(function () { if (stopUrl) window.location.href = stopUrl; });
    }

    function step() {
        if (statusText) {
            statusText.textContent = needsFix
                ? t('auto_applying', 'Applying fixes…')
                : t('auto_moving', 'Moving on…');
        }
        const job = needsFix
            ? (setBusy(true), postApply().catch(() => null))
            : Promise.resolve(null);
        job.then(() => setTimeout(goNext, 500));
    }

    render();
    const timer = setInterval(function () {
        left--;
        if (left <= 0) {
            clearInterval(timer);
            step();
        } else {
            render();
        }
    }, 1000);
})();
