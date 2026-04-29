// deleteForum.js - оптимизированная версия
document.addEventListener('DOMContentLoaded', function() {  
    // Используем делегирование событий для динамически добавленных элементов
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete_employee');
        if (deleteBtn) {
            e.preventDefault();   
            const empid = deleteBtn.getAttribute('data-emp-id');
            const forumName = deleteBtn.getAttribute('data-forum-name') || 'this forum';
            const parent = deleteBtn.closest(".tr") || deleteBtn.closest(".forum-row");
            
            // Создаем кастомное модальное окно вместо bootbox
            showDeleteConfirmation(empid, forumName, parent);
        }
    });
});





function showDeleteConfirmation(empid, forumName, parentRow) {
    // Определяем forumName с значением по умолчанию
    forumName = forumName || 'this forum';
    
    const modalHTML = `
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Заголовок с градиентом -->
            <div class="modal-header bg-danger bg-gradient text-white border-0 rounded-top">
                <div class="d-flex align-items-center">
                    <div class="modal-icon bg-white bg-opacity-25 rounded-circle p-2 me-3">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold">
                            <i class="fa-solid fa-trash-can me-2"></i>Delete Forum
                        </h5>
                        <p class="mb-0 small opacity-75">Critical Action Required</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Тело модального окна -->
            <div class="modal-body py-4">
                <div class="text-center mb-4">
                    <div class="delete-icon-wrapper mb-3">
                        <i class="fa-solid fa-trash-can text-danger fa-3x"></i>
                        <div class="pulse-ring"></div>
                    </div>
                    
                    <h4 class="fw-bold text-dark mb-3">
                        Confirm Deletion
                    </h4>
                    
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25" role="alert">
                        <div class="d-flex">
                            <i class="fa-solid fa-circle-exclamation text-danger mt-1 me-3"></i>
                            <div>
                                <p class="mb-1 fw-semibold">You are about to delete:</p>
                                <h6 class="mb-0 text-danger">
                                    <i class="fa-solid fa-folder-open me-2"></i>
                                    "${forumName}"
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Детали удаления -->
                <div class="border-top border-bottom py-3 my-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fa-solid fa-message text-muted fa-lg mb-2"></i>
                                <p class="mb-1 small">Threads</p>
                                <p class="mb-0 fw-bold">All</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fa-solid fa-comment text-muted fa-lg mb-2"></i>
                                <p class="mb-1 small">Posts</p>
                                <p class="mb-0 fw-bold">All</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Предупреждение -->
                <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25">
                    <div class="d-flex">
                        <i class="fa-solid fa-triangle-exclamation text-warning mt-1 me-3"></i>
                        <div>
                            <p class="mb-1 fw-semibold">This action cannot be undone!</p>
                            <p class="mb-0 small">All content in this forum will be permanently deleted.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Подтверждение -->
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="confirmDeleteCheckbox">
                    <label class="form-check-label small" for="confirmDeleteCheckbox">
                        I understand this action is permanent and cannot be reversed
                    </label>
                </div>
            </div>
            
            <!-- Футер модального окна -->
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4 fw-semibold" id="confirmDeleteBtn" disabled>
                    <i class="fa-solid fa-trash-can me-2"></i>
                    <span>Delete Forum</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" id="deleteSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>`;

    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modalElement = document.getElementById('deleteConfirmModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Показываем модальное окно
    modal.show();

    // Получаем элементы после добавления в DOM
    const confirmCheckbox = document.getElementById('confirmDeleteCheckbox');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const deleteSpinner = document.getElementById('deleteSpinner');

    // Включаем кнопку при согласии
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', function() {
            confirmBtn.disabled = !this.checked;
        });
    }

    // Обработчик подтверждения удаления
    confirmBtn.addEventListener('click', function() {
        // Показываем спиннер
        if (deleteSpinner) {
            deleteSpinner.classList.remove('d-none');
        }
        
        // Отключаем кнопку и чекбокс
        confirmBtn.disabled = true;
        if (confirmCheckbox) confirmCheckbox.disabled = true;
        
        // Меняем текст кнопки
        this.innerHTML = `
            <i class="fa-solid fa-trash-can me-2"></i>
            <span>Deleting...</span>
            <span class="spinner-border spinner-border-sm ms-2"></span>
        `;
        
        deleteEmployee(empid, parentRow, modal);
    });

    // Удаляем модальное окно после закрытия
    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}







async function deleteEmployee(empid, parentRow, modal) {
    try {
        // Получаем my_post_key из глобальной переменной или скрытого поля
        const myPostKey = window.my_post_key || document.querySelector('input[name="my_post_key"]')?.value;
        
        if (!myPostKey) {
            showAlert('Security token missing. Please refresh the page and try again.', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('fid', empid);
        formData.append('my_post_key', myPostKey);

        const response = await fetch(`index.php?act=management&action=delete&fid=${empid}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.text();

        // Проверяем успешность удаления
        if (response.ok) {
            // Плавно скрываем строку
            if (parentRow) {
                parentRow.classList.add('deleting');
                setTimeout(() => {
                    parentRow.remove();
                    // Обновляем нумерацию если нужно
                    updateRowNumbers();
                }, 300);
            }
            
            showAlert('Forum deleted successfully!', 'success');
        } else {
            showAlert('Error deleting forum: ' + result, 'danger');
        }

        modal.hide();
    } catch (error) {
        console.error('Delete error:', error);
        showAlert('Network error. Please check your connection and try again.', 'danger');
        modal.hide();
    }
}

function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'danger' ? 'alert-danger' : 'alert-info';
    
    const alertHTML = `
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', alertHTML);
    const toastElement = document.querySelector('.toast');
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();

    // Удаляем toast после скрытия
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.closest('.toast-container').remove();
    });
}

function updateRowNumbers() {
    // Обновляем порядковые номера если они используются
    document.querySelectorAll('.order-input').forEach((input, index) => {
        input.value = (index + 1) * 10;
    });
}