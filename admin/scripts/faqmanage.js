/* FAQ manager (admin/faqmanage.php) */
(function () {
    'use strict';

    const root = document.querySelector('.faqm');
    if (!root) {
        return;
    }

    /* ---------- i18n ---------- */

    const L = (typeof AGS_LANG === 'object' && AGS_LANG) ? AGS_LANG : {};

    /** t(key, fallback, ...args): lang string with {1}, {2}… (and %1$s) substituted. */
    function t(key, fallback) {
        let s = typeof L[key] === 'string' ? L[key] : fallback;
        for (let i = 2; i < arguments.length; i++) {
            const n = i - 1;
            const a = String(arguments[i]);
            s = s.split('{' + n + '}').join(a).split('%' + n + '$s').join(a);
        }
        return s;
    }

    /** Plural form of <base>_one / _few / _many for n (ru: 1 / 2–4 / 5+, en: 1 / other). */
    function pluralForm(n) {
        if (L.lang_code === 'ru') {
            const m10 = n % 10;
            const m100 = n % 100;
            if (m10 === 1 && m100 !== 11) { return 'one'; }
            if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) { return 'few'; }
            return 'many';
        }
        return n === 1 ? 'one' : 'many';
    }

    /** tp(n, base, fallbackOne, fallbackMany, ...args): {1} = n by default, extra args follow. */
    function tp(n, base, fbOne, fbMany) {
        const form = pluralForm(n);
        const extra = Array.prototype.slice.call(arguments, 4);
        return t.apply(null, [base + '_' + form, form === 'one' ? fbOne : fbMany].concat(extra.length ? extra : [n]));
    }

    /**
     * Подтверждение через SweetAlert2, если он загрузился, иначе обычный confirm().
     * @returns {Promise<boolean>}
     */
    function confirmAction(opts) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                icon: 'warning',
                titleText: opts.title,
                html: opts.html,
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-trash-can me-1"></i> ' + escapeHtml(opts.confirmText),
                cancelButtonText: escapeHtml(t('btn_cancel', 'Cancel')),
                confirmButtonColor: '#dc3545',
                reverseButtons: true,
                focusCancel: true
            }).then(function (r) { return !!r.isConfirmed; });
        }
        return Promise.resolve(window.confirm(opts.title + '\n\n' + opts.text));
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    const dirtyForms = new Set();

    /* ---------- Delete ---------- */

    const deleteForm = document.getElementById('faqmDeleteForm');
    const deleteId   = document.getElementById('faqmDeleteId');

    root.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-faqm-delete]');
        if (!btn || !deleteForm || !deleteId) {
            return;
        }
        e.preventDefault();

        const name     = btn.dataset.faqmName || '';
        const isCat    = btn.dataset.faqmKind === 'category';
        const children = parseInt(btn.dataset.faqmChildren || '0', 10);

        let text = t('del_text', '“{1}” will be deleted permanently.', name);
        if (isCat && children > 0) {
            text = tp(children, 'del_cat_text',
                '“{1}” and its {2} question will be deleted permanently.',
                '“{1}” and its {2} questions will be deleted permanently.',
                name, children.toLocaleString());
        }

        confirmAction({
            title: isCat ? t('del_cat_title', 'Delete this category?') : t('del_q_title', 'Delete this question?'),
            text: text,
            html: escapeHtml(text),
            confirmText: t('btn_delete', 'Delete')
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            dirtyForms.clear(); // уход со страницы здесь намеренный
            deleteId.value = btn.dataset.faqmDelete;
            deleteForm.submit();
        });
    });

    /* ---------- Unsaved changes: order forms + edit forms ---------- */

    function isFieldChanged(el) {
        if (el.type === 'hidden' || el.disabled || !el.name) {
            return false;
        }
        if (el.tagName === 'SELECT') {
            return Array.from(el.options).some(function (o) { return o.selected !== o.defaultSelected; });
        }
        if (el.type === 'checkbox' || el.type === 'radio') {
            return el.checked !== el.defaultChecked;
        }
        return el.value !== el.defaultValue;
    }

    function refresh(form) {
        const bar    = form.querySelector('[data-faqm-savebar]');
        const status = bar ? bar.querySelector('[data-faqm-status]') : null;

        let changed = 0;
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            const c = isFieldChanged(el);
            if (el.matches('[data-faqm-order]')) {
                el.classList.toggle('is-changed', c);
            }
            if (c) {
                changed++;
            }
        });

        if (changed > 0) {
            dirtyForms.add(form);
        } else {
            dirtyForms.delete(form);
        }

        if (bar) {
            bar.classList.toggle('is-dirty', changed > 0);
        }
        if (status) {
            status.textContent = changed > 0
                ? tp(changed, 'unsaved', '{1} unsaved change', '{1} unsaved changes', changed.toLocaleString())
                : status.dataset.idle;
        }
    }

    root.querySelectorAll('[data-faqm-order-form], [data-faqm-guard]').forEach(function (form) {
        form.addEventListener('input', function () { refresh(form); });
        form.addEventListener('change', function () { refresh(form); });
        form.addEventListener('reset', function () {
            // значения сбрасываются после события - пересчитываем в следующем тике
            setTimeout(function () { refresh(form); updateCounters(); }, 0);
        });
        form.addEventListener('submit', function () {
            dirtyForms.delete(form);
            const save = form.querySelector('.faqm-btn--save');
            if (save) {
                save.disabled = true;
                const icon = save.querySelector('i');
                if (icon) {
                    icon.className = 'fa-solid fa-spinner fa-spin';
                }
            }
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirtyForms.size > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /* ---------- Ctrl+S ---------- */

    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 's') {
            return;
        }
        const form = root.querySelector('[data-faqm-guard]') || root.querySelector('[data-faqm-order-form]');
        if (!form) {
            return;
        }
        e.preventDefault();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else if (form.reportValidity()) {
            form.submit();
        }
    });

    /* ---------- Character counter ---------- */

    const counters = root.querySelectorAll('[data-faqm-counter]');

    function updateCounters() {
        counters.forEach(function (c) {
            const field = document.getElementById(c.dataset.faqmCounter);
            if (!field) {
                return;
            }
            const n = field.value.length;
            c.textContent = tp(n, 'chars', '{1} character', '{1} characters', n.toLocaleString());
        });
    }

    counters.forEach(function (c) {
        const field = document.getElementById(c.dataset.faqmCounter);
        if (field) {
            field.addEventListener('input', updateCounters);
        }
    });
    updateCounters();

    /* ---------- Filter questions (view) ---------- */

    const filter  = root.querySelector('[data-faqm-filter]');
    const items   = Array.from(root.querySelectorAll('[data-faqm-q]'));
    const nomatch = root.querySelector('[data-faqm-nomatch]');

    if (filter) {
        filter.addEventListener('input', function () {
            const q = filter.value.trim().toLowerCase();
            let visible = 0;
            items.forEach(function (item) {
                const hit = q === '' || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.hidden = !hit;
                if (hit) {
                    visible++;
                }
            });
            if (nomatch) {
                nomatch.hidden = visible > 0;
            }
        });
        // Esc очищает фильтр
        filter.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && filter.value !== '') {
                filter.value = '';
                filter.dispatchEvent(new Event('input'));
            }
        });
    }

    /* ---------- Expand / collapse all ---------- */

    root.querySelectorAll('[data-faqm-expand]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const open = btn.dataset.faqmExpand === '1';
            items.forEach(function (item) {
                if (item.hidden) {
                    return;
                }
                const panel  = item.querySelector('.collapse');
                const toggle = item.querySelector('.faqm-q__toggle');
                if (!panel) {
                    return;
                }
                if (window.bootstrap && window.bootstrap.Collapse) {
                    const inst = window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });
                    if (open) { inst.show(); } else { inst.hide(); }
                } else {
                    panel.classList.toggle('show', open);
                    if (toggle) {
                        toggle.classList.toggle('collapsed', !open);
                        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    }
                }
            });
        });
    });

    /* ---------- Flash message ---------- */

    const flash = root.querySelector('[data-faqm-flash]');
    if (flash) {
        const hide = function () {
            flash.classList.add('is-hiding');
            setTimeout(function () { flash.remove(); }, 300);
        };
        const closeBtn = flash.querySelector('[data-faqm-flash-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', hide);
        }
        setTimeout(hide, 5000);

        // убираем ?msg= из адреса, чтобы F5 не показывал сообщение повторно
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('msg');
            window.history.replaceState(null, '', url.toString());
        }
    }
})();
