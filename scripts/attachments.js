document.addEventListener('DOMContentLoaded', function () {

    // Переключатель "Выбрать все"
    const checkAllSwitch = document.querySelector('.checkall');
    const allCheckboxes = document.querySelectorAll('.attachment-checkbox');

    if (checkAllSwitch) {

        // Клик по "выбрать все"
        checkAllSwitch.addEventListener('change', function () {
            allCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Обновление состояния главного чекбокса
        allCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);

                checkAllSwitch.checked = allChecked;
                checkAllSwitch.indeterminate = !allChecked && someChecked;
            });
        });
    }

    // Анимация карточек
    const attachmentItems = document.querySelectorAll('.attachment-item');

    attachmentItems.forEach(item => {
        item.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            this.style.transition = 'all 0.3s ease';
        });

        item.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });

});