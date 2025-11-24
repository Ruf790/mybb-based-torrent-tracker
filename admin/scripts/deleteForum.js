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
    // Создаем модальное окно
    const modalHTML = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-trash" style="color: #0d0d0d;"></i> Delete !
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to Delete?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete!</button>
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
        deleteEmployee(empid, parentRow, modal);
    });

    // Удаляем модальное окно после закрытия
    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function deleteEmployee(empid, parentRow, modal) {
    try {
        const formData = new FormData();
        formData.append('fid', empid);

        const response = await fetch(`index.php?act=management&action=delete&fid=${empid}&my_post_key=${my_post_key}`, {
            method: 'POST',
            body: formData
        });

        const result = await response.text();

        // Показываем результат
        showAlert(result);

        // Плавно скрываем строку
        if (parentRow) {
            parentRow.style.transition = 'opacity 0.5s ease';
            parentRow.style.opacity = '0';
            setTimeout(() => {
                parentRow.remove();
            }, 500);
        }

        modal.hide();
    } catch (error) {
        showAlert('Error....');
        console.error('Delete error:', error);
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