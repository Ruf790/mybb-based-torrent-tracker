let deleteId = null;
let deleteImageSrc = null;

document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        
        deleteId = this.dataset.id;
        const filename = this.dataset.filename || 'this screenshot';
        
        // Получаем src изображения из карточки
        const card = this.closest('.screenshot-card');
        const img = card?.querySelector('img');
        deleteImageSrc = img?.src || '';
        
        // Обновляем модалку
        document.getElementById('singleDeleteModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion';
        document.getElementById('singleDeleteTitle').textContent = 'Delete Screenshot?';
        document.getElementById('singleDeleteFilename').innerHTML = '<strong>"' + filename + '"</strong>';
        document.getElementById('singleDeleteFileName').textContent = filename;
        
        // Получаем информацию о файле
        const fileInfoEl = document.getElementById('singleDeleteFileInfo');
        fileInfoEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
        
        // Имитация получения информации
        setTimeout(() => {
            const randomSize = Math.floor(Math.random() * 500 + 100);
            const today = new Date();
            const dateStr = today.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            fileInfoEl.innerHTML = '<i class="fas fa-database me-1"></i> ' + randomSize + ' KB • ' + 
                                   '<i class="fas fa-calendar me-1"></i> ' + dateStr;
        }, 500);
        
        
const previewContainer = document.getElementById('singleDeletePreviewContainer');
const previewImg = document.getElementById('singleDeleteImage');

if (deleteImageSrc && deleteImageSrc !== '') {
    previewImg.src = deleteImageSrc;
    previewImg.style.display = 'block';
} else {
    previewImg.style.display = 'none';
}
previewContainer.style.display = 'block';
		
		
    });
});

document.getElementById('confirmSingleDeleteBtn').addEventListener('click', function () {
    if (!deleteId) return;

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Deleting...';

    fetch('index.php?act=manage_screenshots&action=delete&id=' + deleteId, {
        method: 'POST',
        body: new URLSearchParams({ my_post_key: my_post_key }),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('singleDeleteModal'));
        if (modal) modal.hide();

        if (data.status === 'success') {
            const card = document.querySelector('.screenshot-checkbox[value="' + deleteId + '"]')?.closest('.screenshot-card');
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => card.remove(), 300);
            }
            showToast(data.message || 'Screenshot deleted successfully.', 'success');
        } else {
            showToast(data.message || 'Failed to delete screenshot.', 'error');;
        }

        deleteId = null;
        deleteImageSrc = null;
    })
    .catch(err => {
        console.error(err);
        showToast('An error occurred while deleting the screenshot.', 'error');
        deleteId = null;
        deleteImageSrc = null;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
});


// Очищаем превью при закрытии модалки
document.getElementById('singleDeleteModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('singleDeleteImage').src = '';
    document.getElementById('singleDeleteFileInfo').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
    document.getElementById('singleDeleteFilename').innerHTML = '';
});



document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.screenshot-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const form = document.getElementById('massDeleteForm');
    const alertContainer = document.getElementById('alertContainer');
    const previewContainer = document.getElementById('massDeletePreview');
    const deleteCountEl = document.getElementById('deleteCount');
    const modalEl = document.getElementById('massDeleteModal');
    const previewList = document.getElementById('previewList');

    // Создаем экземпляр модалки
    let massDeleteModal;
    if (modalEl) {
        massDeleteModal = new bootstrap.Modal(modalEl);
    }

    function getSelectedCheckboxes() {
        return Array.from(document.querySelectorAll('.screenshot-checkbox:checked'));
    }

    function renderDeletePreview() {
        if (!previewList || !deleteCountEl || !previewContainer) return;
        
        previewList.innerHTML = '';
        const selected = getSelectedCheckboxes();
        deleteCountEl.textContent = selected.length;

        if (selected.length === 0) {
            previewContainer.style.display = 'none';
            return;
        }

        selected.forEach(cb => {
            let src = cb.dataset.imgSrc;
            if (!src) return;

            const item = document.createElement('div');
            item.className = 'preview-item';
            
            // Создаем изображение с обработчиком ошибок
            const img = new Image();
            img.src = src;
            img.alt = 'Screenshot';
            img.style.cssText = 'width:100%; height:100%; object-fit:cover; display:block;';
            
            // Обработчик ошибки загрузки
            img.onerror = function() {
                this.src = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'56\' viewBox=\'0 0 100 56\'%3E%3Crect width=\'100\' height=\'56\' fill=\'%23e9ecef\'/%3E%3Ctext x=\'50\' y=\'28\' font-size=\'10\' text-anchor=\'middle\' fill=\'%236c757d\'%3ENo image%3C/text%3E%3C/svg%3E';
            };
            
            const imgDiv = document.createElement('div');
            imgDiv.className = 'preview-image';
            imgDiv.appendChild(img);
            
            item.appendChild(imgDiv);
            previewList.appendChild(item);
        });

        previewContainer.style.display = 'block';
    }

    // Открытие модалки через JS
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.hasAttribute('disabled')) {
                // Показываем предупреждение
                Swal.fire({
                    title: 'No Selection',
                    text: 'Please select at least one screenshot to delete.',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            
            renderDeletePreview();
            if (massDeleteModal) {
                massDeleteModal.show();
            }
        });
    }

    // Обновление состояния кнопки
    function updateDeleteBtnState() {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        
        if (anyChecked) {
            deleteBtn.removeAttribute('disabled');
            deleteBtn.classList.remove('btn-secondary');
            deleteBtn.classList.add('btn-danger');
        } else {
            deleteBtn.setAttribute('disabled', 'disabled');
            deleteBtn.classList.remove('btn-danger');
            deleteBtn.classList.add('btn-secondary');
        }
    }

    // Checkbox event listeners
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteBtnState);
    });

    // Select All/Deselect All functionality
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            this.innerHTML = allChecked 
                ? '<i class="fas fa-check-square me-2"></i>Select All'
                : '<i class="fas fa-times-circle me-2"></i>Deselect All';
            updateDeleteBtnState();
        });
    }

    // Confirm delete action
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            const selected = getSelectedCheckboxes();
            const selectedCount = selected.length;

            if (selectedCount === 0) return;

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (massDeleteModal) {
                    massDeleteModal.hide();
                }

                if (data.status === 'success' || data.status === 'partial') {
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred while deleting screenshots', 'error');
            });
        });
    }


    // Initial state
    updateDeleteBtnState();
});

