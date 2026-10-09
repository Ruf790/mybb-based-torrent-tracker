'use strict';
/**
 * admin/scripts/cleartable.js — Truncate MySQL Tables
 *
 * Все сообщения через SweetAlert2 (/scripts/sweetalert2.min.js),
 * без него - откат на confirm()/alert(), страница работает и без JS
 * (серверная страница подтверждения «Step 2 of 2» осталась как fallback).
 *
 * Тексты: PHP выводит const AGS_LANG (ключи js_* из cleartable.lang.php без префикса)
 * до подключения этого файла. t(key, fallback, ...args) берёт перевод оттуда,
 * а если его нет - английский fallback. Переводы вставляются как текст
 * (textContent / createTextNode / titleText), не через innerHTML.
 */
(() => {
    const hasSwal = () => typeof window.Swal !== 'undefined';

    // ── i18n ─────────────────────────────────────────────────────────────────
    // t('optimizing', 'Optimizing {1} ({2}/{3})…', table, i, total)
    const t = (key, fallback, ...args) => {
        const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
        const src  = typeof dict[key] === 'string' ? dict[key] : fallback;
        return src.replace(/\{(\d+)\}/g, (m, n) => (args[n - 1] !== undefined ? String(args[n - 1]) : m));
    };

    const esc = s => String(s).replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    // Мини-разметка в строках ланга: **жирный** и `код` -> <strong>/<code> через DOM (без innerHTML)
    const richText = str => {
        const frag = document.createDocumentFragment();
        String(str).split(/(\*\*[^*]+\*\*|`[^`]+`)/).forEach(part => {
            if (!part) return;
            let el = null, cut = 0;
            if (part.length > 4 && part.startsWith('**') && part.endsWith('**')) { el = document.createElement('strong'); cut = 2; }
            else if (part.length > 2 && part.startsWith('`') && part.endsWith('`')) { el = document.createElement('code'); cut = 1; }
            if (el) {
                el.textContent = part.slice(cut, -cut);
                frag.appendChild(el);
            } else {
                frag.appendChild(document.createTextNode(part));
            }
        });
        return frag;
    };

    const tableListEl = items => {
        const box = document.createElement('div');
        box.style.cssText = 'max-height:220px;overflow:auto;text-align:left;margin-top:.75rem;' +
            'padding:.5rem .75rem;border-radius:10px;background:var(--bs-tertiary-bg,#f5f5f5);' +
            'font-family:var(--bs-font-monospace,monospace);font-size:.85rem;line-height:1.7';
        items.forEach((name, i) => {
            if (i) box.appendChild(document.createElement('br'));
            const ic = document.createElement('i');
            ic.className = 'fa-solid fa-table me-2';
            ic.style.opacity = '.6';
            box.appendChild(ic);
            box.appendChild(document.createTextNode(String(name)));
        });
        return box;
    };

    // text - обычный текст; list - массив строк (имена таблиц / ошибки)
    const notify = (icon, title, text = '', list = null) => {
        if (hasSwal()) {
            const opts = { icon, titleText: title };
            if (list) {
                const box = document.createElement('div');
                if (text) {
                    const p = document.createElement('div');
                    p.textContent = text;
                    box.appendChild(p);
                }
                box.appendChild(tableListEl(list));
                opts.html = box;
            } else if (text) {
                opts.text = text;
            }
            return Swal.fire(opts);
        }
        window.alert(title + (text ? '\n\n' + text : '') + (list ? '\n\n' + list.join('\n') : ''));
        return Promise.resolve();
    };

    const toast = (icon, title) => hasSwal()
        ? Swal.fire({ toast: true, position: 'top-end', icon, titleText: title, showConfirmButton: false, timer: 3500, timerProgressBar: true })
        : Promise.resolve();

    const setStatus = (el, cls, color = '', title = '') => {
        const i = document.createElement('i');
        i.className = cls;
        if (color) i.style.color = color;
        if (title) i.title = title;
        el.replaceChildren(i);
    };

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
                notify('info',
                    t('none_title', 'No tables selected'),
                    t('none_text', 'Tick at least one table to truncate.'));
                return;
            }

            const title = t('confirm_title', 'Truncate {1} table(s)?', tables.length);

            let ask;
            if (hasSwal()) {
                const body = document.createElement('div');
                body.appendChild(richText(t('confirm_warn',
                    'Every row in these tables will be deleted. **There is no undo** — make sure you have a backup.')));
                body.appendChild(tableListEl(tables));

                const hint = document.createElement('div');
                hint.style.cssText = 'margin-top:1rem;font-size:.9rem';
                hint.appendChild(richText(t('confirm_type', 'Type `TRUNCATE` to confirm')));
                body.appendChild(hint);

                ask = Swal.fire({
                    icon: 'warning',
                    titleText: title,
                    html: body,
                    input: 'text',
                    inputAttributes: { autocapitalize: 'off', autocomplete: 'off', spellcheck: 'false' },
                    showCancelButton: true,
                    // Swal трактует текст кнопок как HTML - перевод экранируем
                    confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> ' + esc(t('confirm_btn', 'Truncate')),
                    cancelButtonText: esc(t('cancel', 'Cancel')),
                    confirmButtonColor: '#dc3545',
                    reverseButtons: true,
                    focusCancel: true,
                    preConfirm: v => {
                        // Слово-подтверждение TRUNCATE не переводится: оно же проверяется здесь
                        if (String(v).trim() !== 'TRUNCATE') {
                            Swal.showValidationMessage(esc(t('type_exact', 'Type TRUNCATE exactly')));
                            return false;
                        }
                        return true;
                    },
                }).then(r => r.isConfirmed);
            } else {
                ask = Promise.resolve(window.confirm(
                    title + '\n\n' + tables.join(', ') + '\n\n' + t('confirm_tail', 'This cannot be undone.')));
            }

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
                    Swal.fire({
                        titleText: t('truncating', 'Truncating…'),
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading(),
                    });
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
            toast('success', t('trunc_done', '{1} table(s) truncated', tables.length));
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
                if (label)  label.textContent = t('optimizing', 'Optimizing {1} ({2}/{3})…', table, i + 1, tables.length);
                if (status) setStatus(status, 'fa-solid fa-spinner fa-spin');

                let data;
                try {
                    data = await optimizeOne(table);
                } catch (err) {
                    data = { success: false, message: err.message };
                }

                if (status) {
                    if (data.success) {
                        setStatus(status, 'fa-solid fa-bolt', 'var(--bs-success)', t('opt_ok', 'Optimized'));
                    } else {
                        setStatus(status, 'fa-solid fa-circle-xmark', 'var(--bs-danger)', data.message ?? t('opt_err', 'Error'));
                    }
                }
                if (!data.success) errors.push(`${table}: ${data.message ?? t('unknown_error', 'unknown error')}`);
                if (bar) bar.style.width = Math.round((i + 1) / tables.length * 100) + '%';
            }

            if (progress) progress.style.display = 'none';

            if (errors.length) {
                btn.disabled = false;
                notify('error', t('opt_errors_title', 'Optimization finished with errors'), '', errors);
            } else {
                if (done) done.style.display = '';
                btn.style.display = 'none';
                toast('success', t('opt_all_done', 'All tables optimized'));
            }
        });
    }

    function showFailures(box) {
        let failed = [];
        try { failed = JSON.parse(box.dataset.tables || '[]'); } catch { /* ignore */ }
        if (!failed.length) return;
        const partial = document.getElementById('ctSuccessPanel')
            ? t('fail_partial', 'The tables that did succeed are listed on the page.')
            : '';
        notify('error',
            t('fail_title', 'Failed to truncate {1} table(s)', failed.length),
            [partial, t('fail_log', 'Check the error log.')].filter(Boolean).join(' '),
            failed);
    }

    // ── Ошибка с сервера ────────────────────────────────────────────────────
    function initError(box) {
        if (!hasSwal()) return; // остаётся обычная панель с кнопкой «Go back»
        box.style.visibility = 'hidden';
        Swal.fire({
            icon: box.dataset.type === 'danger' ? 'error' : 'warning',
            titleText: t('error_title', 'Truncate Database Tables'),
            text: box.dataset.message ?? '',
            confirmButtonText: '<i class="fa-solid fa-arrow-left me-1"></i> ' + esc(t('go_back', 'Go back')),
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
