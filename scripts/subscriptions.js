/**
 * subscriptions.js — управление страницей подписок
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

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

    // ── Подтверждение удаления ────────────────────────────────────────────
    var form = document.getElementById('subscriptionsForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            var selected = document.querySelectorAll('.subscription-checkbox:checked').length;

            if (selected === 0) {
                e.preventDefault();
                showToast('Please select at least one subscription to remove.', 'warning');
                return;
            }

            if (!confirm('Are you sure you want to unsubscribe from ' + selected + ' thread(s)?')) {
                e.preventDefault();
            }
        });
    }

    // ── Toast-уведомление ─────────────────────────────────────────────────
    function showToast(message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top:20px;right:20px;z-index:1055;min-width:300px;';
        toast.innerHTML = message
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3000);
    }

    // ── Инициализация ─────────────────────────────────────────────────────
    updateSelectedCount();
});
