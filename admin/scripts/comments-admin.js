// Глобальные переменные состояния
const commentManager = {
    currentPage: 1,
    deleteId: 0,
    editId: 0,
    selectedComments: [],
    filters: {
        username: '',
        torrent: '',
        date_from: '',
        date_to: ''
    },
    isLoading: false
};

// Основная функция загрузки комментариев
function loadComments(page = 1) {
    if (commentManager.isLoading) return;
    
    commentManager.currentPage = page;
    commentManager.isLoading = true;
    
    const commentsTable = document.getElementById('comments-table');
    if (commentsTable) {
        commentsTable.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading comments...</p>
            </div>
        `;
    }
    
    const queryParams = new URLSearchParams();
    queryParams.append('act', 'latest_comments');
    queryParams.append('action', 'list');
    queryParams.append('page', page);
    
    Object.entries(commentManager.filters).forEach(([key, value]) => {
        if (value) queryParams.append(key, value);
    });
    
    fetch(`index.php?${queryParams.toString()}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.text();
        })
        .then(data => {
            if (commentsTable) {
                commentsTable.innerHTML = data;
            }
            updateUrlWithFilters();
            updateSelection();
            bindBulkDeleteHandler();
        })
        .catch(error => {
            console.error('Error loading comments:', error);
            if (commentsTable) {
                commentsTable.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Failed to load comments: ${error.message}
                    </div>
                `;
            }
        })
        .finally(() => {
            commentManager.isLoading = false;
        });
}

// Функции для управления выбором
function updateSelection() {
    const checkboxes = document.querySelectorAll('.comment-checkbox:checked');
    commentManager.selectedComments = Array.from(checkboxes).map(checkbox => checkbox.value);

    const selectedCount = document.getElementById('selectedCount');
    const selectedCommentsCount = document.getElementById('selectedCommentsCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAll = document.getElementById('selectAll');

    if (selectedCount) selectedCount.textContent = commentManager.selectedComments.length;
    if (selectedCommentsCount) selectedCommentsCount.textContent = commentManager.selectedComments.length;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = commentManager.selectedComments.length === 0;

    // Сбрасываем выделение строк
    document.querySelectorAll('tr[data-comment-id]').forEach(row => {
        row.classList.remove('table-active');
    });

    // Выделяем выбранные строки
    commentManager.selectedComments.forEach(id => {
        const row = document.querySelector(`tr[data-comment-id="${id}"]`);
        if (row) row.classList.add('table-active');
    });

    // Обновляем состояние "Выбрать все"
    if (selectAll) {
        const allCheckboxes = document.querySelectorAll('.comment-checkbox');
        selectAll.checked = allCheckboxes.length > 0 && allCheckboxes.length === checkboxes.length;
    }
}

// Обработчики для чекбоксов
document.addEventListener('change', function(e) {
    if (e.target.id === 'selectAll') {
        const isChecked = e.target.checked;
        document.querySelectorAll('.comment-checkbox').forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateSelection();
    }
    
    if (e.target.classList.contains('comment-checkbox')) {
        updateSelection();
    }
});

document.addEventListener('click', function(e) {
    if (e.target.id === 'selectAllBtn') {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = true;
            selectAll.dispatchEvent(new Event('change'));
        }
    }
});


// Функция массового удаления
function bindBulkDeleteHandler() {
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (!bulkDeleteBtn) return;

    // Удаляем старые обработчики
    const newBulkDeleteBtn = bulkDeleteBtn.cloneNode(true);
    bulkDeleteBtn.parentNode.replaceChild(newBulkDeleteBtn, bulkDeleteBtn);

    newBulkDeleteBtn.addEventListener('click', function() {
        if (commentManager.selectedComments.length === 0) {
            showToast('Please select at least one comment', 'warning');
            return;
        }

        const modalElement = document.getElementById('confirmBulkDeleteModal');
        if (!modalElement) return;

        const modal = new bootstrap.Modal(modalElement);
        const count = commentManager.selectedComments.length;
        const word = count === 1 ? 'comment' : 'comments';
        const messageElement = document.getElementById('bulkDeleteMessage');
        
        if (messageElement) {
            messageElement.textContent = `Are you sure you want to delete ${count} selected ${word}?`;
        }

        // Обработчик подтверждения удаления
        const confirmBtn = document.getElementById('confirmBulkDeleteBtn');
        if (confirmBtn) {
            const handleConfirm = function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting...';

                // ИСПРАВЛЕНИЕ: Отправляем ids как массив, а не JSON строку
                const formData = new FormData();
                
                // Ключевое исправление: добавляем каждый ID отдельно
                commentManager.selectedComments.forEach(id => {
                    formData.append('ids[]', id); // Добавляем как массив
                });
                
                // Альтернативный вариант: как строку через запятую
                // formData.append('ids', commentManager.selectedComments.join(','));
                
                if (typeof my_post_key !== 'undefined') {
                    formData.append('my_post_key', my_post_key);
                }

                // Для отладки - выводим данные в консоль
                console.log('Отправляемые IDs:', commentManager.selectedComments);
                console.log('Количество:', commentManager.selectedComments.length);

                fetch('index.php?act=latest_comments&action=bulk_delete', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(response => {
                    console.log('Ответ от сервера:', response);
                    if (response.success) {
                        showToast(`${response.deleted} comments deleted successfully`, 'success');
                        loadComments(commentManager.currentPage);
                        modal.hide();
                    } else {
                        showToast(response.error || 'Error deleting comments', 'error');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete error:', error);
                    showToast('Error: ' + error.message, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash"></i> Delete Selected';
                    confirmBtn.removeEventListener('click', handleConfirm);
                });
            };

            confirmBtn.addEventListener('click', handleConfirm);
        }

        modal.show();
    });
}

// Copy Comments
document.addEventListener('click', function(e) {
    if (e.target.id === 'confirmCopyBtn') {
        const targetInput = document.getElementById('copyTargetTorrent');
        if (!targetInput) return;

        const target = parseInt(targetInput.value);
        if (!target || target <= 0) {
            alert("Please enter a valid target torrent ID");
            return;
        }

        const selectedComments = commentManager.selectedComments;
        if (selectedComments.length === 0) {
            alert("Please select at least one comment to copy");
            return;
        }

        if (!confirm(`Copy ${selectedComments.length} selected comment(s) to torrent ID ${target}?`)) return;

        const btn = e.target;
        btn.disabled = true;
        btn.textContent = 'Copying...';

        const formData = new FormData();
        formData.append('comment_ids', JSON.stringify(selectedComments));
        formData.append('target_tid', target);
        if (typeof my_post_key !== 'undefined') {
            formData.append('my_post_key', my_post_key);
        }

        fetch('index.php?act=latest_comments&action=copy_comments', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                showToast(res.copied + " comment(s) copied successfully!", 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('copyCommentsModal'));
                if (modal) modal.hide();
                if (targetInput) targetInput.value = '';
            } else {
                showToast("Error: " + res.error, 'error');
            }
        })
        .catch(error => {
            console.error('Copy error:', error);
            showToast("AJAX error: " + error.message, 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Copy Comments';
        });
    }
});

// Merge Comments
document.addEventListener('click', function(e) {
    if (e.target.id === 'confirmMergeBtn') {
        const targetInput = document.getElementById('targetTorrent');
        if (!targetInput) return;

        const target = parseInt(targetInput.value);
        if (!target || target <= 0) {
            alert("Please enter a valid target torrent ID");
            return;
        }

        const selectedComments = commentManager.selectedComments;
        if (selectedComments.length === 0) {
            alert("Please select at least one comment to merge");
            return;
        }

        if (!confirm(`Are you sure you want to move ${selectedComments.length} selected comments to torrent ID ${target}?`)) return;

        const btn = e.target;
        btn.disabled = true;
        btn.textContent = 'Merging...';

        const formData = new FormData();
        formData.append('comment_ids', JSON.stringify(selectedComments));
        formData.append('target_tid', target);
        if (typeof my_post_key !== 'undefined') {
            formData.append('my_post_key', my_post_key);
        }

        fetch('index.php?act=latest_comments&action=move_comments', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                showToast(res.moved + " comments moved successfully!", 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('mergeCommentsModal'));
                if (modal) modal.hide();
                loadComments(commentManager.currentPage);
                if (targetInput) targetInput.value = '';
            } else {
                showToast("Error: " + res.error, 'error');
            }
        })
        .catch(error => {
            console.error('Merge error:', error);
            showToast("AJAX error: " + error.message, 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Merge Comments';
        });
    }
});

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('act')) {
        urlParams.set('act', 'latest_comments');
        window.history.replaceState({}, '', `?${urlParams.toString()}`);
    }
    
    initFiltersFromUrl();
    setupFilterHandlers();
    loadComments(commentManager.currentPage);
    
    const confirmEditBtn = document.getElementById('confirmEditComment');
    const editCommentText = document.getElementById('editCommentText');
    
    if (confirmEditBtn) {
        confirmEditBtn.addEventListener('click', saveComment);
    }
    if (editCommentText) {
        editCommentText.addEventListener('input', updatePreview);
    }
});

// Остальные вспомогательные функции
function initFiltersFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.forEach((value, key) => {
        if (key in commentManager.filters) {
            commentManager.filters[key] = value;
            const input = document.getElementById(key);
            if (input) input.value = value;
        }
    });
    
    const page = urlParams.get('page');
    if (page) commentManager.currentPage = parseInt(page);
}

function setupFilterHandlers() {
    const filterForm = document.getElementById('filterForm');
    const resetFiltersBtn = document.getElementById('resetFilters');
    const usernameInput = document.getElementById('username');
    const torrentInput = document.getElementById('torrent');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');

    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', resetFilters);
    }

    let searchTimer;
    [usernameInput, torrentInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    applyFilters();
                }, 500);
            });
        }
    });

    [dateFromInput, dateToInput].forEach(input => {
        if (input) {
            input.addEventListener('change', applyFilters);
        }
    });
}

function applyFilters() {
    commentManager.filters = {
        username: document.getElementById('username')?.value.trim() || '',
        torrent: document.getElementById('torrent')?.value.trim() || '',
        date_from: document.getElementById('date_from')?.value || '',
        date_to: document.getElementById('date_to')?.value || ''
    };
    loadComments(1);
}

function resetFilters() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) filterForm.reset();
    
    commentManager.filters = {
        username: '',
        torrent: '',
        date_from: '',
        date_to: ''
    };
    loadComments(1);
}

function updateUrlWithFilters() {
    const params = new URLSearchParams();
    params.append('act', 'latest_comments');
    params.append('page', commentManager.currentPage);
    
    Object.entries(commentManager.filters).forEach(([key, value]) => {
        if (value) params.append(key, value);
    });
    
    window.history.replaceState({}, '', `?${params.toString()}`);
}

// Функции редактирования комментариев
function editComment(id) {
    commentManager.editId = id;
    
    fetch(`index.php?act=latest_comments&action=edit&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            const editCommentText = document.getElementById('editCommentText');
            if (editCommentText) {
                editCommentText.value = data.text;
            }
            updatePreview();
            new bootstrap.Modal(document.getElementById('editCommentModal')).show();
        })
        .catch(error => {
            console.error('Error loading comment:', error);
            alert('Error loading comment: ' + error.message);
        });
}

function updatePreview() {
    const editCommentText = document.getElementById('editCommentText');
    if (!editCommentText) return;

    const formData = new FormData();
    formData.append('text', editCommentText.value);

    fetch('index.php?act=latest_comments&action=preview', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const preview = document.getElementById('bbcodePreview');
        if (preview) preview.innerHTML = html;
    })
    .catch(error => {
        console.error('Preview error:', error);
        const preview = document.getElementById('bbcodePreview');
        if (preview) preview.innerHTML = '<div class="text-danger">Preview generation failed</div>';
    });
}

function saveComment() {
    const editCommentText = document.getElementById('editCommentText');
    if (!editCommentText) return;

    const commentText = editCommentText.value.trim();
    
    // Клиентская валидация
    if (commentText.length < 3) {
        showToast('Comment must be at least 3 characters long', 'warning');
        return;
    }
    
    const cleanText = commentText.replace(/\s+/g, '');
    if (cleanText.length < 3) {
        showToast('Comment must contain meaningful text', 'warning');
        return;
    }
    
    const saveBtn = document.getElementById('confirmEditComment');
    if (!saveBtn) return;

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    const formData = new FormData();
    formData.append('id', commentManager.editId);
    formData.append('text', commentText);
	
	
	if (typeof my_post_key !== 'undefined') {
    formData.append('my_post_key', my_post_key);
}
	
	

    fetch('index.php?act=latest_comments&action=save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(response => {
        if (response && response.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editCommentModal'));
            if (modal) modal.hide();
            loadComments(commentManager.currentPage);
            showToast('Comment updated successfully', 'success');
        } else {
            const errorMsg = response && response.error ? response.error : 'Unknown error occurred';
            showToast(errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Save comment error:', error);
        let errorMsg = 'Error saving comment';
        if (error.message.includes('Network')) {
            errorMsg = 'Network error - please check your connection';
        }
        showToast(errorMsg, 'error');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Changes';
    });
}

function deleteComment(id) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
	
	if (typeof my_post_key !== 'undefined') {
    formData.append('my_post_key', my_post_key);
}

    fetch('index.php?act=latest_comments&action=delete', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            showToast('Comment deleted successfully', 'success');
            loadComments(commentManager.currentPage);
        } else {
            throw new Error('Delete failed');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showToast('Error deleting comment', 'error');
    });
}

// BBCode редактор
function wrapBBCode(startTag, endTag) {
    const textarea = document.getElementById("editCommentText");
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + startTag + text.substring(start, end) + endTag + text.substring(end);
    textarea.focus();
    updatePreview();
}

// Обновление количества выбранных комментов в модалке копирования
document.addEventListener('show.bs.modal', function(e) {
    if (e.target.id === 'copyCommentsModal') {
        const copySelectedCount = document.getElementById('copySelectedCount');
        if (copySelectedCount) {
            copySelectedCount.textContent = commentManager.selectedComments.length;
        }
    }
});