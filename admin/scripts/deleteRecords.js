document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete_employee');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const empid = this.getAttribute('data-emp-id');
            const parentRow = this.closest('tr');
            showDeleteConfirmation(empid, parentRow);
        });
    });
});

async function showDeleteConfirmation(empid, parentRow) {
    const modalHTML = `
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa-solid fa-trash" style="color: #0d0d0d;"></i> Delete !
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="cancelDelete">No</button>
                        <button type="button" class="btn btn-danger" id="confirmDelete">Delete!</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modalElement = document.getElementById('deleteModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();

    document.getElementById('cancelDelete').addEventListener('click', function() {
        modal.hide();
    });

    document.getElementById('confirmDelete').addEventListener('click', async function() {
        await deleteEmployee(empid, parentRow, modal);
    });

    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function deleteEmployee(empid, parentRow, modal) {
    try {
        const formData = new FormData();
        formData.append('empid', empid);

        const response = await fetch('deleteRecords.php', {
            method: 'POST',
            body: formData
        });

		const result = await response.json();

        const message = result.message || (result.success ? 'Record deleted successfully' : 'Delete failed');
        showAlert(message);

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