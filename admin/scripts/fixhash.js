/* Fix Torrent Hashes — apply (AJAX POST + CSRF), Auto Fix, copy hash */
(function () {
    'use strict';

    const root = document.getElementById('fhPage');
    if (!root) return;

    const currentPage = parseInt(root.dataset.page, 10) || 1;
    const totalPages  = parseInt(root.dataset.totalPages, 10) || 1;
    const mismatch    = parseInt(root.dataset.mismatch, 10) || 0;
    const autoMode    = root.dataset.auto === '1';
    const stopUrl     = root.dataset.stopUrl || '';

    const applyForm = document.getElementById('applyForm');
    const applyBtn  = document.getElementById('applyBtn');
    const hasSwal   = typeof window.Swal !== 'undefined';

    // ── SweetAlert2 с fallback ──────────────────────────────────────────
    function confirmBox(title, text) {
        if (!hasSwal) return Promise.resolve(window.confirm(title + '\n\n' + text));
        return Swal.fire({
            icon: 'question',
            title: title,
            text: text,
            showCancelButton: true,
            confirmButtonText: 'Apply fixes',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusCancel: true
        }).then(r => r.isConfirmed);
    }

    function notify(icon, title, text) {
        if (!hasSwal) {
            window.alert(title + (text ? '\n\n' + text : ''));
            return Promise.resolve();
        }
        return Swal.fire({ icon: icon, title: title, text: text || '' });
    }

    function toast(icon, title) {
        if (!hasSwal) return;
        Swal.fire({
            toast: true, position: 'bottom-end', icon: icon, title: title,
            showConfirmButton: false, timer: 1400, timerProgressBar: true
        });
    }

    // ── Apply ───────────────────────────────────────────────────────────
    let originalBtnHtml = applyBtn ? applyBtn.innerHTML : '';

    function setBusy(busy) {
        if (!applyBtn) return;
        applyBtn.disabled = busy;
        applyBtn.innerHTML = busy
            ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Applying…'
            : originalBtnHtml;
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
        let t = 'Fixed ' + d.fixed + ' torrent(s)';
        if (d.errors)  t += ', ' + d.errors + ' error(s)';
        if (d.skipped) t += ', ' + d.skipped + ' skipped';
        return t + '.';
    }

    if (applyForm && applyBtn) {
        applyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (applyBtn.disabled) return;

            confirmBox(
                'Apply fixes?',
                'The stored info_hash will be replaced for ' + mismatch + ' torrent(s) on page ' + currentPage + '.'
            ).then(function (ok) {
                if (!ok) return;
                setBusy(true);
                postApply()
                    .then(function (data) {
                        if (data && data.success) {
                            notify(data.errors ? 'warning' : 'success', 'Fixes applied', resultText(data))
                                .then(() => window.location.reload());
                        } else {
                            notify('error', 'Fixes not applied', (data && data.error) || 'Unknown error');
                            setBusy(false);
                        }
                    })
                    .catch(function () {
                        notify('error', 'Request failed', 'The server did not return a valid response. Try again.');
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
                toast('success', 'Hash copied');
            })
            .catch(() => toast('error', 'Copy failed'));
    });

    // ── Auto Fix ────────────────────────────────────────────────────────
    if (!autoMode) return;

    const statusText = document.querySelector('#fhAutoStatus .fh-auto-text');
    const needsFix = applyBtn && !applyBtn.disabled;
    let left = 10;

    function render() {
        if (statusText) {
            statusText.textContent = (needsFix ? 'Fixing' : 'Next page') + ' in ' + left + 's';
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
        if (statusText) statusText.textContent = 'Finished';
        notify('success', 'Auto Fix finished', 'All ' + totalPages + ' page(s) have been processed.')
            .then(function () { if (stopUrl) window.location.href = stopUrl; });
    }

    function step() {
        if (statusText) statusText.textContent = needsFix ? 'Applying fixes…' : 'Moving on…';
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
