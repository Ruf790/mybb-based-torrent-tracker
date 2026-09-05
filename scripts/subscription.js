/**
 * subscription.js — управление подписками на темы
 */
document.addEventListener('DOMContentLoaded', function () {

    // ── Переключатель подписки ────────────────────────────────────────────
    document.querySelectorAll('.subscription-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var card = this.closest('.subscription-card');
            card.classList.toggle('selected', this.checked);

            // Кратковременная подсветка для визуального фидбека
            var cls = this.checked ? 'border-success' : 'border-warning';
            card.classList.add(cls);
            setTimeout(function () { card.classList.remove(cls); }, 800);
        });
    });

    // ── Раскрытие доп. информации по клику на карточку ───────────────────
    document.querySelectorAll('.subscription-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('.btn') || e.target.closest('.form-check-input')) return;
            var info = this.querySelector('.additional-info');
            if (info) info.classList.toggle('d-none');
        });
    });

});
