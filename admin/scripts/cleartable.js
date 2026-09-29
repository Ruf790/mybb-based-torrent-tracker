'use strict';
/**
 * admin/scripts/cleartable.js — Truncate MySQL Tables
 *
 * Все сообщения через SweetAlert2 (/scripts/sweetalert2.min.js),
 * без него - откат на confirm()/alert(), страница работает и без JS
 * (серверная страница подтверждения «Step 2 of 2» осталась как fallback).
 */
(() => {
    const hasSwal = () => typeof window.Swal !== 'undefined';

    const esc = s => String(s).replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const tableListHtml = tables =>
        '<div style="max-height:220px;overflow:auto;text-align:left;margin-top:.75rem;' +
        'padding:.5rem .75rem;border-radius:10px;background:var(--bs-tertiary-bg,#f5f5f5);' +
        'font-family:var(--bs-font-monospace,monospace);font-size:.85rem;line-height:1.7">' +
        tables.map(t => '<i class="fa-solid fa-table me-2" style="opacity:.6"></i>' + esc(t)).join('<br>') +
        '</div>';

    const notify = (icon, title, html = '') => {
        if (hasSwal()) return Swal.fire({ icon, title, html });
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        window.alert(title + (tmp.textContent ? '\n\n' + tmp.textContent : ''));
        return Promise.resolve();
    };

    const toast = (icon, title) => hasSwal()
        ? Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 3500, timerProgressBar: true })
        : Promise.resolve();

    // ── Шаг 1: выбор таблиц ──────────────────────────────────────────────────
    function initSelection(form) {
        const list    = document.getElementById('ctList');
        const counter = document.getElementById('ctCount');
        const search  = document.getElementById('ctSearch');
        const checks  = [...form.querySelectorAll('.ct-check')];

        const isVisible = cb => cb.closest('.ct-row')?.style.display !== 'none';
        const selected  = () => checks.filter(cb => cb.checked).map(cb => cb.value);

        const update = () => {
            if (counter) counter.textContent = String(selected().length);
            checks.forEach(cb => cb.closest('.ct-row')?.classList.toggle('is-checked', cb.checked));
        };

        list?.addEventListener('change', update);

        search?.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            list?.querySelectorAll('.ct-row').forEach(row => {
                row.style.display = !q || (row.dataset.name ?? '').toLowerCase().includes(q) ? '' : 'none';
            });
        });

        document.getElementById('ctAll')?.addEventListener('click', () => {
            checks.forEach(cb => { if (isVisible(cb)) cb.checked = true; });
            update();
        });
        document.getElementById('ctNone')?.addEventListener('click', () => {
            checks.forEach(cb => { cb.checked = false; });
            update();
        });
        document.getElementById('ctInvert')?.addEventListener('click', () => {
            checks.forEach(cb => { if (isVisible(cb)) cb.checked = !cb.checked; });
            update();
        });

        form.addEventListener('submit', e => {
            e.preventDefault();
            const tables = selected();

            if (tables.length === 0) {
                notify('info', 'No tables selected', 'Tick at least one table to truncate.');
                return;
            }

            const title = `Truncate ${tables.length} table(s)?`;
            const warn  = 'Every row in these tables will be deleted. <strong>There is no undo</strong> — make sure you have a backup.';

            const ask = hasSwal()
                ? Swal.fire({
                      icon: 'warning',
                      title,
                      html: warn + tableListHtml(tables) +
                            '<div style="margin-top:1rem;font-size:.9rem">Type <code>TRUNCATE</code> to confirm</div>',
                      input: 'text',
                      inputAttributes: { autocapitalize: 'off', autocomplete: 'off', spellcheck: 'false' },
                      showCancelButton: true,
                      confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> Truncate',
                      cancelButtonText: 'Cancel',
                      confirmButtonColor: '#dc3545',
                      reverseButtons: true,
                      focusCancel: true,
                      preConfirm: v => {
                          if (String(v).trim() !== 'TRUNCATE') {
                              Swal.showValidationMessage('Type TRUNCATE exactly');
                              return false;
                          }
                          return true;
                      },
                  }).then(r => r.isConfirmed)
                : Promise.resolve(window.confirm(title + '\n\n' + tables.join(', ') + '\n\nThis cannot be undone.'));

            ask.then(ok => {
                if (!ok) return;

                // Сразу на выполнение: sure=true + CSRF-ключ (сервер принимает только POST с валидным ключом)
                const key = form.dataset.postKey ?? '';
                if (key) {
                    let hidden = form.querySelector('input[name="my_post_key"]');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'my_post_key';
                        form.appendChild(hidden);
                    }
                    hidden.value = key;
                    form.action = form.getAttribute('action') + '&sure=true';
                }
                // Без ключа - обычный путь через серверную страницу подтверждения

                if (hasSwal()) {
                    Swal.fire({ title: 'Truncating…', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
                }
                form.submit(); // нативный submit - не вызывает этот обработчик повторно
            });
        });

        update();
    }

    // ── Шаг 2: результаты + оптимизация ──────────────────────────────────────
    function initResults(panel) {
        const parse = raw => { try { return JSON.parse(raw || '[]'); } catch { return []; } };

        const tables  = parse(panel.dataset.tables);
        const postKey = panel.dataset.postKey ?? '';
        const url     = panel.dataset.url || location.href;

        // Если были провалы - модалка показывается в showFailures(), тост не нужен
        if (tables.length && !document.getElementById('ctFailed')) {
            toast('success', `${tables.length} table(s) truncated`);
        }

        const btn      = document.getElementById('btnOptimize');
        const progress = document.getElementById('opt_progress');
        const bar      = document.getElementById('opt_bar');
        const label    = document.getElementById('opt_label');
        const done     = document.getElementById('opt_done');

        const optimizeOne = async table => {
            const body = new FormData();
            body.append('do', 'ajax_optimize');
            body.append('table', table);
            body.append('my_post_key', postKey);

            const res  = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({ success: false, message: `HTTP ${res.status}` }));
            if (!res.ok && data.success !== false) data.success = false;
            return data;
        };

        btn?.addEventListener('click', async () => {
            if (!tables.length) return;

            btn.disabled = true;
            if (progress) progress.style.display = '';
            if (done) done.style.display = 'none';

            const errors = [];
            for (let i = 0; i < tables.length; i++) {
                const table  = tables[i];
                const status = document.getElementById('opt_status_' + table);
                if (label)  label.textContent = `Optimizing ${table} (${i + 1}/${tables.length})…`;
                if (status) status.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                let data;
                try {
                    data = await optimizeOne(table);
                } catch (err) {
                    data = { success: false, message: err.message };
                }

                if (status) {
                    status.innerHTML = data.success
                        ? '<i class="fa-solid fa-bolt" style="color:var(--bs-success)" title="Optimized"></i>'
                        : '<i class="fa-solid fa-circle-xmark" style="color:var(--bs-danger)" title="' + esc(data.message ?? 'Error') + '"></i>';
                }
                if (!data.success) errors.push(`${table}: ${data.message ?? 'unknown error'}`);
                if (bar) bar.style.width = Math.round((i + 1) / tables.length * 100) + '%';
            }

            if (progress) progress.style.display = 'none';

            if (errors.length) {
                btn.disabled = false;
                notify('error', 'Optimization finished with errors', tableListHtml(errors));
            } else {
                if (done) done.style.display = '';
                btn.style.display = 'none';
                toast('success', 'All tables optimized');
            }
        });
    }

    function showFailures(box) {
        let failed = [];
        try { failed = JSON.parse(box.dataset.tables || '[]'); } catch { /* ignore */ }
        if (!failed.length) return;
        const partial = document.getElementById('ctSuccessPanel')
            ? 'The tables that did succeed are listed on the page. '
            : '';
        notify('error', `Failed to truncate ${failed.length} table(s)`,
               partial + 'Check the error log.' + tableListHtml(failed));
    }

    // ── Ошибка с сервера ────────────────────────────────────────────────────
    function initError(box) {
        if (!hasSwal()) return; // остаётся обычная панель с кнопкой «Go back»
        box.style.visibility = 'hidden';
        Swal.fire({
            icon: box.dataset.type === 'danger' ? 'error' : 'warning',
            title: 'Truncate Database Tables',
            text: box.dataset.message ?? '',
            confirmButtonText: '<i class="fa-solid fa-arrow-left me-1"></i> Go back',
            allowOutsideClick: false,
        }).then(() => {
            location.href = box.dataset.back || location.pathname;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('truncateForm');
        if (form) initSelection(form);

        const panel = document.getElementById('ctSuccessPanel');
        if (panel) initResults(panel);

        const failed = document.getElementById('ctFailed');
        if (failed) showFailures(failed);

        const err = document.getElementById('ctError');
        if (err) initError(err);
    });
})();
