document.addEventListener('DOMContentLoaded', function() {  
    document.querySelectorAll('.delete_employee').forEach(button => {
        button.addEventListener('click', function(e) {   
            e.preventDefault();   
            const empid = this.getAttribute('data-emp-id');
            const parent = this.closest("tr");   
            
            // Создаем кастомное модальное окно вместо bootbox
            showDeleteConfirmation(empid, parent);
        });  
    });
});

function showDeleteConfirmation(empid, parentRow) {
    // Получаем ключ безопасности
    const myPostKey = document.querySelector('input[name="my_post_key"]').value;
    
    // Создаем модальное окно
    const modalHTML = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-trash" style="color: #0d0d0d;"></i> Delete Group
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this group? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fa-solid fa-trash me-1"></i>Delete!
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modalElement = document.getElementById('deleteConfirmModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Показываем модальное окно
    modal.show();

    // Обработчик подтверждения удаления
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        deleteGroup(empid, parentRow, modal, myPostKey);
    });

    // Удаляем модальное окно после закрытия
    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function deleteGroup(empid, parentRow, modal, myPostKey) {
    try {
        // Показываем индикатор загрузки
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';
        deleteBtn.disabled = true;

        const response = await fetch(`index.php?act=groups&action=delete&gid=${empid}&my_post_key=${myPostKey}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `gid=${empid}`
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.text();

        // Скрываем модальное окно подтверждения
        modal.hide();

        // Показываем результат
        showAlert(result);

        // Плавно скрываем строку
        if (parentRow) {
            parentRow.style.transition = 'all 0.5s ease';
            parentRow.style.opacity = '0';
            parentRow.style.transform = 'translateX(-100%)';
            setTimeout(() => {
                if (parentRow.parentNode) {
                    parentRow.parentNode.removeChild(parentRow);
                }
            }, 500);
        }

    } catch (error) {
        console.error('Delete error:', error);
        showAlert('Error deleting group. Please try again.');
        
        // Восстанавливаем кнопку в случае ошибки
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        if (deleteBtn) {
            deleteBtn.innerHTML = '<i class="fa-solid fa-trash me-1"></i>Delete!';
            deleteBtn.disabled = false;
        }
    }
}

function showAlert(message) {
    const alertHTML = `
        <div class="modal fade" id="alertModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', alertHTML);
    const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
    alertModal.show();

    document.getElementById('alertModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Добавляем CSS для плавных анимаций
const style = document.createElement('style');
style.textContent = `
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
    }
    tr {
        transition: all 0.5s ease;
    }
    .btn-success {
        background-color: #198754;
        border-color: #198754;
    }
    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }
`;
document.head.appendChild(style);