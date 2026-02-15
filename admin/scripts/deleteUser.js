document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete_employee');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const empid = this.getAttribute('data-emp-id');
            const username = this.getAttribute('data-username') || 'User';
            const parentRow = this.closest('tr');
            showDeleteConfirmation(empid, username, parentRow);
        });
    });
});

function showDeleteConfirmation(empid, username, parentRow) {
    if (document.getElementById('deleteModal')) {
        document.getElementById('deleteModal').remove();
    }
    
    const modalHTML = `
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 glass-card">
                    <!-- Header with danger gradient -->
                    <div class="modal-header bg-danger text-white text-center py-4 border-0">
                        <div class="w-100">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h3 class="mb-0">Confirm Account Deletion</h3>
                        </div>
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <!-- Body -->
                    <div class="modal-body text-center py-5">
                        <h5 class="text-danger mb-3">Are you sure you want to delete this account?</h5>
                        
                        <div class="user-info bg-light rounded-4 p-4 mb-4 mx-auto" style="max-width: 300px;">
                            <h4 class="text-danger fw-bold mb-2">${username}</h4>
                            <p class="text-muted mb-0">UID: #${empid}</p>
                        </div>
                        
                        <p class="text-muted mb-4">
                            <i class="fas fa-info-circle text-info me-1"></i>
                            This action cannot be undone. All user data will be permanently removed.
                        </p>
                        
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-danger btn-lg px-4" id="confirmDelete">
                                <i class="fas fa-trash me-2"></i>Yes, Delete Account
                            </button>
                            <button type="button" class="btn btn-secondary btn-lg px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    const modalElement = document.getElementById('deleteModal');
    const modal = new bootstrap.Modal(modalElement);
    
    const confirmBtn = document.getElementById('confirmDelete');
    
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
            deleteEmployee(empid, parentRow, modal);
        });
    }

    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
    
    modal.show();
}

async function deleteEmployee(empid, parentRow, modal) {
    try {
        const formData = new FormData();
        formData.append('empid', empid);

        const response = await fetch('deleteRecords.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        const result = await response.json();
        modal.hide();

        if (result.success) {
            showNotification('User deleted successfully', 'success');
            
            if (parentRow) {
                parentRow.style.transition = 'all 0.3s ease';
                parentRow.style.opacity = '0';
                parentRow.style.transform = 'translateX(-10px)';
                
                setTimeout(() => {
                    if (parentRow.parentNode) {
                        parentRow.remove();
                        
                        // Проверяем, остались ли строки
                        const tbody = parentRow.closest('tbody');
                        if (tbody && tbody.children.length === 0) {
                            showEmptyState(tbody);
                        }
                    }
                }, 300);
            }
        } else {
            showNotification(result.message || 'Failed to delete user', 'error');
        }

    } catch (error) {
        console.error('Delete error:', error);
        modal.hide();
        showNotification(error.message, 'error');
    }
}

function showNotification(message, type = 'success') {
    if (document.getElementById('notificationModal')) {
        document.getElementById('notificationModal').remove();
    }
    
    const icons = {
        success: 'fa-circle-check text-success',
        error: 'fa-circle-exclamation text-danger',
        warning: 'fa-triangle-exclamation text-warning'
    };
    
    const bgColors = {
        success: 'bg-success bg-opacity-10',
        error: 'bg-danger bg-opacity-10',
        warning: 'bg-warning bg-opacity-10'
    };
    
    const icon = icons[type] || icons.success;
    const bgColor = bgColors[type] || 'bg-light';
    
    const modalHTML = `
        <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow glass-card">
                    <div class="modal-body text-center py-4">
                        <div class="${bgColor} rounded-circle p-3 d-inline-block mb-3">
                            <i class="fa-solid ${icon} fa-2x"></i>
                        </div>
                        <p class="mb-3 small">${message}</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    modal.show();

    document.getElementById('notificationModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function showEmptyState(tbody) {
    const colCount = tbody.closest('table').querySelector('thead tr').children.length;
    const emptyHTML = `
        <tr>
            <td colspan="${colCount}" class="text-center py-5">
                <div class="text-muted glass-card p-4" style="background: rgba(255,255,255,0.5);">
                    <i class="fa-solid fa-users-slash fa-2x mb-2"></i>
                    <p class="small mb-0">No users found</p>
                </div>
            </td>
        </tr>
    `;
    tbody.innerHTML = emptyHTML;
}

// CSS стили с glassmorphism
const style = document.createElement('style');
style.textContent = `
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .modal-content {
        border-radius: 20px;
        overflow: hidden;
    }
    
    .modal-header {
        position: relative;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        padding: 2rem 1rem;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-header .btn-close:hover {
        opacity: 1;
    }
    
    .modal-body {
        background: rgba(255, 255, 255, 0.95);
    }
    
    .user-info {
        background: rgba(248, 249, 250, 0.8) !important;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .delete_employee {
        cursor: pointer;
        transition: all 0.2s ease;
        padding: 8px 12px;
        border-radius: 8px;
        background: transparent;
        border: none;
    }
    
    .delete_employee:hover {
        background-color: rgba(220, 53, 69, 0.1);
        transform: translateY(-2px);
    }
    
    .delete_employee:hover i {
        transform: scale(1.1);
        color: #dc3545;
    }
    
    .delete_employee i {
        transition: all 0.2s ease;
        font-size: 1.2rem;
        color: #6c757d;
    }
    
    tr {
        transition: all 0.3s ease;
    }
    
    .warning-icon {
        animation: fadeIn 0.3s ease;
    }
    
    .btn {
        transition: all 0.2s ease;
        border-radius: 10px;
        font-weight: 500;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border: none;
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(220, 53, 69, 0.4);
    }
    
    .btn-danger:disabled {
        opacity: 0.7;
        transform: none;
    }
    
    .btn-secondary {
        background: rgba(108, 117, 125, 0.1);
        border: 1px solid rgba(108, 117, 125, 0.2);
        color: #6c757d;
        backdrop-filter: blur(5px);
    }
    
    .btn-secondary:hover {
        background: rgba(108, 117, 125, 0.2);
        border-color: rgba(108, 117, 125, 0.3);
        color: #495057;
        transform: translateY(-2px);
    }
    
    .btn-outline-secondary {
        border: 1px solid rgba(108, 117, 125, 0.3);
        color: #6c757d;
    }
    
    .btn-outline-secondary:hover {
        background-color: rgba(108, 117, 125, 0.1);
        border-color: #6c757d;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .fa-spinner {
        animation: spin 1s linear infinite;
    }
    
    /* Glass effect for notification */
    #notificationModal .modal-content {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        
        .user-info {
            padding: 1rem !important;
        }
    }
`;
document.head.appendChild(style);

// Добавляем data-username атрибут к кнопкам удаления
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete_employee').forEach(btn => {
        const row = btn.closest('tr');
        if (row) {
            const usernameCell = row.querySelector('td:nth-child(3) a');
            if (usernameCell) {
                const username = usernameCell.textContent.trim();
                btn.setAttribute('data-username', username);
            }
        }
    });
});