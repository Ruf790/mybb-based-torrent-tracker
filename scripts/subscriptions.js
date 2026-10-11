/**
 * subscriptions.js — управление страницей подписок
 * Тексты: window.AGS_LANG (из usercp.php), английский — fallback.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function t(key, fallback, ...args) {
        var L = window.AGS_LANG || {};
        var s = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        args.forEach(function (a, i) {
            s = s.split('{' + (i + 1) + '}').join(a).split('%' + (i + 1) + '$s').join(a);
        });
        return s;
    }
    // SweetAlert через ags-dialog.js; без него — нативный confirm()
    function ask(msg, opts) {
        return window.agsConfirm ? window.agsConfirm(msg, opts) : Promise.resolve(window.confirm(msg));
    }

    var checkAllSwitch          = document.querySelector('.checkall');
    var selectedCountEl         = document.getElementById('selectedCount');
    var selectedCountContainer  = document.querySelector('.selected-count');
    var noSubscriptionsEl       = document.getElementById('noSubscriptions');
    var subscriptionsList       = document.getElementById('subscriptionsList');

    // ── Пустой список ─────────────────────────────────────────────────────
    if (subscriptionsList && !subscriptionsList.querySelector('.subscription-card')) {
        subscriptionsList.style.display = 'none';
        if (noSubscriptionsEl) noSubscriptionsEl.classList.remove('d-none');
    }

    // ── Обновление счётчика и состояния карточек ──────────────────────────
    function updateSelectedCount() {
        var checked  = document.querySelectorAll('.subscription-checkbox:checked').length;
        var total    = document.querySelectorAll('.subscription-checkbox').length;

        if (selectedCountEl)        selectedCountEl.textContent = checked;
        if (selectedCountContainer) selectedCountContainer.classList.toggle('d-none', checked === 0);

        document.querySelectorAll('.subscription-card').forEach(function (card) {
            var cb = card.querySelector('.subscription-checkbox');
            card.classList.toggle('selected', !!(cb && cb.checked));
        });

        if (checkAllSwitch) {
            checkAllSwitch.checked       = total > 0 && checked === total;
            checkAllSwitch.indeterminate = checked > 0 && checked < total;
        }
    }

    // ── Select All ────────────────────────────────────────────────────────
    if (checkAllSwitch) {
        checkAllSwitch.addEventListener('change', function () {
            document.querySelectorAll('.subscription-checkbox').forEach(function (cb) {
                cb.checked = checkAllSwitch.checked;
            });
            updateSelectedCount();
        });
    }

    // ── Одиночный чекбокс ─────────────────────────────────────────────────
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('subscription-checkbox')) {
            updateSelectedCount();
        }
    });

    // ── Отправка формы ────────────────────────────────────────────────────
    var form = document.getElementById('subscriptionsForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            var selected = document.querySelectorAll('.subscription-checkbox:checked').length;

            if (selected === 0) {
                e.preventDefault();
                showToast(t('select_subscription', 'Please select at least one subscription.'), 'warning');
                return;
            }

            // Подтверждение только для удаления — смена способа уведомлений ничего не удаляет
            var action = form.querySelector('select[name="do"]');
            if (action && action.value === 'delete') {
                e.preventDefault();
                ask(t('confirm_unsubscribe', 'Are you sure you want to unsubscribe from {1} thread(s)?', selected), { danger: true })
                    .then(function (ok) {
                        // .submit() не вызывает событие submit повторно
                        if (ok) HTMLFormElement.prototype.submit.call(form);
                    });
            }
        });
    }

    // ── Toast-уведомление ─────────────────────────────────────────────────
    function showToast(message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top:20px;right:20px;z-index:1055;min-width:300px;';
        toast.textContent = message;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', t('close', 'Close'));
        toast.appendChild(close);

        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3000);
    }

    // ── Инициализация ─────────────────────────────────────────────────────
    updateSelectedCount();
});
