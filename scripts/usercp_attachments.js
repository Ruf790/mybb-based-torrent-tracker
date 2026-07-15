/**
 * attachments.js — управление вложениями (выбор, подсветка, доп. инфо)
 */
document.addEventListener('DOMContentLoaded', function () {

    // Подсветка при выборе чекбокса
    document.querySelectorAll('.attachment-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var item = this.closest('.attachment-item');
            if (this.checked) {
                item.classList.add('border-primary');
                item.style.backgroundColor = '#f8f9ff';
            } else {
                item.classList.remove('border-primary');
                item.style.backgroundColor = '';
            }
        });
    });

    // Клик по строке: показать/скрыть доп. инфо
    // Курсор pointer вынесен в CSS — не нужно ставить через JS
    document.querySelectorAll('.attachment-item').forEach(function (item) {
        item.addEventListener('click', function (e) {
            if (e.target.closest('.btn') || e.target.closest('.form-check-input')) return;
            var info = this.querySelector('.additional-info');
            if (info) info.classList.toggle('d-none');
        });
    });

});
