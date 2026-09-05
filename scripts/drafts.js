/**
 * drafts.js — управление черновиками (выбор, удаление)
 */
document.addEventListener('DOMContentLoaded', () => {
    const checkAll        = document.getElementById('checkAll');
    const selectedCountEl = document.getElementById('selectedCount');
    const selectedBox     = document.querySelector('.selected-count');
    const deleteBtn       = document.getElementById('deleteButton');

    function getCheckboxes() {
        return document.querySelectorAll('.draft-checkbox');
    }

    function updateUI() {
        const boxes = getCheckboxes();
        let checked = 0;

        boxes.forEach(cb => {
            const card = cb.closest('.draft-card');
            if (cb.checked) {
                checked++;
                card?.classList.add('selected');
            } else {
                card?.classList.remove('selected');
            }
        });

        selectedCountEl.textContent = checked;
        selectedBox.classList.toggle('d-none', checked === 0);
        deleteBtn.disabled = checked === 0;

        checkAll.checked       = boxes.length > 0 && checked === boxes.length;
        checkAll.indeterminate = checked > 0 && checked < boxes.length;
    }

    // Выбрать все
    checkAll.addEventListener('change', () => {
        getCheckboxes().forEach(cb => { cb.checked = checkAll.checked; });
        updateUI();
    });

    // Одиночный выбор
    document.addEventListener('change', e => {
        if (e.target.classList.contains('draft-checkbox')) {
            updateUI();
        }
    });

    // Подтверждение удаления
    document.getElementById('draftsForm')?.addEventListener('submit', e => {
        if (!document.querySelectorAll('.draft-checkbox:checked').length) {
            e.preventDefault();
            alert('Выберите хотя бы один черновик');
        }
    });
});
