// ── i18n ────────────────────────────────────────────────────────────────────
// AGS_LANG выводит latest_comments.php из js_* ключей ланга (без префикса).
// $lang->load() превращает {N} в %N$s — подставляем оба формата.
function t(key, fallback, ...args) {
    const dict = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    const str  = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
    return str.replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a !== undefined ? a : b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Элемент с классом и текстом (текст — только через textContent)
function lcEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = text;
    return el;
}

// Кнопка в состоянии загрузки: спиннер + текст
function lcSetBusy(btn, text) {
    btn.replaceChildren(
        lcEl('span', 'spinner-border spinner-border-sm'),
        document.createTextNode(' ' + text)
    );
}

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
        const wrap    = lcEl('div', 'text-center py-5');
        const spinner = lcEl('div', 'spinner-border text-primary');
        spinner.setAttribute('role', 'status');
        spinner.appendChild(lcEl('span', 'visually-hidden', t('loading', 'Loading...')));
        wrap.append(spinner, lcEl('p', 'mt-2 text-muted', t('loading_comments', 'Loading comments...')));
        commentsTable.replaceChildren(wrap);
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
                const alert = lcEl('div', 'alert alert-danger');
                alert.append(
                    lcEl('i', 'bi bi-exclamation-triangle'),
                    document.createTextNode(' ' + t('load_failed', 'Failed to load comments: {1}', error.message))
                );
                commentsTable.replaceChildren(alert);
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
    const moveSelectedCount = document.getElementById('moveSelectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAll = document.getElementById('selectAll');

    if (selectedCount) selectedCount.textContent = commentManager.selectedComments.length;
    if (moveSelectedCount) moveSelectedCount.textContent = commentManager.selectedComments.length;
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
    if (e.target.closest('#selectAllBtn')) {
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
            showToast(t('select_one', 'Please select at least one comment'), 'warning');
            return;
        }

        const modalElement = document.getElementById('confirmBulkDeleteModal');
        if (!modalElement) return;

        const modal = new bootstrap.Modal(modalElement);
        const count = commentManager.selectedComments.length;
        const messageElement = document.getElementById('bulkDeleteMessage');
        
        if (messageElement) {
            messageElement.textContent = count === 1
                ? t('bulk_confirm_one', 'Are you sure you want to delete {1} selected comment?', count)
                : t('bulk_confirm_many', 'Are you sure you want to delete {1} selected comments?', count);
        }

        // Обработчик подтверждения удаления
        const confirmBtn = document.getElementById('confirmBulkDeleteBtn');
        if (confirmBtn) {
            const handleConfirm = function() {
                const btn = this;
                const btnHtml = btn.innerHTML; // серверная разметка кнопки (иконка + переведённый текст)
                btn.disabled = true;
                lcSetBusy(btn, t('deleting', 'Deleting...'));

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
                        showToast(t('bulk_deleted', '{1} comment(s) deleted successfully', response.deleted), 'success');
                        loadComments(commentManager.currentPage);
                        modal.hide();
                    } else {
                        showToast(response.error || t('err_delete_many', 'Error deleting comments'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Bulk delete error:', error);
                    showToast(t('error_prefix', 'Error: {1}', error.message), 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = btnHtml;
                    confirmBtn.removeEventListener('click', handleConfirm);
                });
            };

            confirmBtn.addEventListener('click', handleConfirm);
        }

        modal.show();
    });
}

// Copy Comments
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('#confirmCopyBtn');
    if (btn) {
        const targetInput = document.getElementById('copyTargetTorrent');
        if (!targetInput) return;

        const target = parseInt(targetInput.value);
        if (!target || target <= 0) {
            Swal.fire({ icon: 'warning', titleText: t('invalid_target', 'Please enter a valid target torrent ID') });
            return;
        }

        const selectedComments = commentManager.selectedComments;
        if (selectedComments.length === 0) {
            Swal.fire({ icon: 'warning', titleText: t('select_copy', 'Please select at least one comment to copy') });
            return;
        }

        const confirmResult = await Swal.fire({
            icon: 'question',
            titleText: t('copy_title', 'Copy comments?'),
            text: t('copy_text', 'Copy {1} selected comment(s) to torrent ID {2}?', selectedComments.length, target),
            showCancelButton: true,
            confirmButtonText: escapeHtml(t('copy_btn', 'Copy')),
            cancelButtonText: escapeHtml(t('cancel', 'Cancel'))
        });
        if (!confirmResult.isConfirmed) return;

        const btnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = t('copying', 'Copying...');

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
                showToast(t('copied', '{1} comment(s) copied successfully!', res.copied), 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('copyCommentsModal'));
                if (modal) modal.hide();
                if (targetInput) targetInput.value = '';
            } else {
                showToast(t('error_prefix', 'Error: {1}', res.error), 'error');
            }
        })
        .catch(error => {
            console.error('Copy error:', error);
            showToast(t('ajax_error', 'AJAX error: {1}', error.message), 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = btnHtml;
        });
    }
});

// Merge Selected Comments Into One (join texts, delete originals)
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('#confirmMergeIntoOneBtn');
    if (btn) {
        const targetInput = document.getElementById('mergeTargetTorrent');
        if (!targetInput) return;

        const target = parseInt(targetInput.value);
        if (!target || target <= 0) {
            Swal.fire({ icon: 'warning', titleText: t('invalid_target', 'Please enter a valid target torrent ID') });
            return;
        }

        const selectedComments = commentManager.selectedComments;
        if (selectedComments.length < 2) {
            Swal.fire({ icon: 'warning', titleText: t('select_merge', 'Please select at least 2 comments to merge') });
            return;
        }

        const confirmResult = await Swal.fire({
            icon: 'warning',
            titleText: t('merge_title', 'Merge comments?'),
            text: t('merge_text', 'Merge {1} selected comments into one, on torrent ID {2}? This cannot be undone.', selectedComments.length, target),
            showCancelButton: true,
            confirmButtonText: escapeHtml(t('merge_btn', 'Merge')),
            cancelButtonText: escapeHtml(t('cancel', 'Cancel'))
        });
        if (!confirmResult.isConfirmed) return;

        const btnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = t('merging', 'Merging...');

        const formData = new FormData();
        formData.append('comment_ids', JSON.stringify(selectedComments));
        formData.append('target_tid', target);
        if (typeof my_post_key !== 'undefined') {
            formData.append('my_post_key', my_post_key);
        }

        fetch('index.php?act=latest_comments&action=merge_comments', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                showToast(t('merged', '{1} comments merged into comment #{2}!', res.merged, res.new_comment_id), 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('mergeIntoOneModal'));
                if (modal) modal.hide();
                loadComments(commentManager.currentPage);
                if (targetInput) targetInput.value = '';
            } else {
                showToast(t('error_prefix', 'Error: {1}', res.error), 'error');
            }
        })
        .catch(error => {
            console.error('Merge error:', error);
            showToast(t('ajax_error', 'AJAX error: {1}', error.message), 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = btnHtml;
        });
    }
});

// Move Comments
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('#confirmMoveBtn');
    if (btn) {
        const targetInput = document.getElementById('targetTorrent');
        if (!targetInput) return;

        const target = parseInt(targetInput.value);
        if (!target || target <= 0) {
            Swal.fire({ icon: 'warning', titleText: t('invalid_target', 'Please enter a valid target torrent ID') });
            return;
        }

        const selectedComments = commentManager.selectedComments;
        if (selectedComments.length === 0) {
            Swal.fire({ icon: 'warning', titleText: t('select_move', 'Please select at least one comment to move') });
            return;
        }

        const confirmResult = await Swal.fire({
            icon: 'question',
            titleText: t('move_title', 'Move comments?'),
            text: t('move_text', 'Are you sure you want to move {1} selected comments to torrent ID {2}?', selectedComments.length, target),
            showCancelButton: true,
            confirmButtonText: escapeHtml(t('move_btn', 'Move')),
            cancelButtonText: escapeHtml(t('cancel', 'Cancel'))
        });
        if (!confirmResult.isConfirmed) return;

        const btnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = t('moving', 'Moving...');

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
                showToast(t('moved', '{1} comment(s) moved successfully!', res.moved), 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('moveCommentsModal'));
                if (modal) modal.hide();
                loadComments(commentManager.currentPage);
                if (targetInput) targetInput.value = '';
            } else {
                showToast(t('error_prefix', 'Error: {1}', res.error), 'error');
            }
        })
        .catch(error => {
            console.error('Move error:', error);
            showToast(t('ajax_error', 'AJAX error: {1}', error.message), 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = btnHtml;
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
                Swal.fire({ icon: 'error', titleText: t('error_title', 'Error'), text: data.error });
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
            Swal.fire({ icon: 'error', titleText: t('err_load_comment', 'Error loading comment'), text: error.message });
        });
}

function updatePreview() {
    const editCommentText = document.getElementById('editCommentText');
    if (!editCommentText) return;

    const formData = new FormData();
    formData.append('text', editCommentText.value);
    if (typeof my_post_key !== 'undefined') {
        formData.append('my_post_key', my_post_key);
    }

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
        if (preview) preview.replaceChildren(lcEl('div', 'text-danger', t('preview_failed', 'Preview generation failed')));
    });
}

function saveComment() {
    const editCommentText = document.getElementById('editCommentText');
    if (!editCommentText) return;

    const commentText = editCommentText.value.trim();
    
    // Клиентская валидация
    if (commentText.length < 3) {
        showToast(t('text_min', 'Comment must be at least 3 characters long'), 'warning');
        return;
    }
    
    const cleanText = commentText.replace(/\s+/g, '');
    if (cleanText.length < 3) {
        showToast(t('text_meaningful', 'Comment must contain meaningful text'), 'warning');
        return;
    }
    
    const saveBtn = document.getElementById('confirmEditComment');
    if (!saveBtn) return;

    const saveBtnHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    lcSetBusy(saveBtn, t('saving', 'Saving...'));

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
            showToast(t('saved', 'Comment updated successfully'), 'success');
        } else {
            const errorMsg = response && response.error ? response.error : t('unknown_error', 'Unknown error occurred');
            showToast(errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('Save comment error:', error);
        let errorMsg = t('err_save', 'Error saving comment');
        if (error.message.includes('Network')) {
            errorMsg = t('network_error', 'Network error - please check your connection');
        }
        showToast(errorMsg, 'error');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = saveBtnHtml;
    });
}

async function deleteComment(id) {
    const confirmResult = await Swal.fire({
        icon: 'warning',
        titleText: t('delete_title', 'Delete comment?'),
        text: t('delete_text', 'Are you sure you want to delete this comment?'),
        showCancelButton: true,
        confirmButtonText: escapeHtml(t('delete_btn', 'Delete')),
        cancelButtonText: escapeHtml(t('cancel', 'Cancel')),
        confirmButtonColor: '#d33'
    });
    if (!confirmResult.isConfirmed) return;

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
            showToast(t('deleted', 'Comment deleted successfully'), 'success');
            loadComments(commentManager.currentPage);
        } else {
            throw new Error('Delete failed');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showToast(t('err_delete_one', 'Error deleting comment'), 'error');
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
    if (e.target.id === 'mergeIntoOneModal') {
        const mergeIntoOneSelectedCount = document.getElementById('mergeIntoOneSelectedCount');
        if (mergeIntoOneSelectedCount) {
            mergeIntoOneSelectedCount.textContent = commentManager.selectedComments.length;
        }
    }
});


document.addEventListener('click', function (e) {
    const link = e.target.closest('#comments-table .pagination-wrapper a[href]');
    if (!link) return;

    e.preventDefault();

    // Активная (текущая) страница рендерится как href="#" без номера -
    // клик по ней не должен никуда переходить.
    if (link.classList.contains('active')) return;

    const href = link.getAttribute('href') || '';
    const match = href.match(/[?&]page=(\d+)/);
    const pageNum = match ? parseInt(match[1], 10) : 1;

    loadComments(pageNum);
});

document.addEventListener('submit', function (e) {
    const form = e.target.closest('#comments-table .dropdown-menu form');
    if (!form) return;

    const input = form.querySelector('input[name="page"]');
    const pageNum = input ? (parseInt(input.value, 10) || 1) : 1;

    e.preventDefault();
    loadComments(pageNum);
});

// ── Torrent embed panel (модалка Edit Comment) ──────────────────────────────
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, function (ch) {
        switch (ch) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
        }
    });
}

// Извлекает ID из голого числа, полного URL (torrent-17.html), query-параметра
// или произвольного вставленного текста со ссылкой внутри.
function extractTorrentTagId(raw) {
    raw = raw || '';
    const m = raw.match(/torrent-(\d+)\.html/i)
           || raw.match(/[?&](?:id|tid)=(\d+)/i)
           || raw.match(/(\d+)/);
    return m ? m[1] : '';
}

function insertTorrentTag(id) {
    const ta = document.getElementById('editCommentText');
    if (!ta) return;
    const s = ta.selectionStart;
    const e = ta.selectionEnd;
    const tag = '[torrent=' + id + ']';
    ta.value = ta.value.substring(0, s) + tag + ta.value.substring(e);
    ta.focus();
    ta.setSelectionRange(s + tag.length, s + tag.length);
    if (typeof updatePreview === 'function') updatePreview();
}

function initTorrentTagPanel() {
    const input   = document.getElementById('torrentIdInput');
    const btn     = document.getElementById('insertTorrentBtn');
    const preview = document.getElementById('torrentPreview');
    if (!input || !btn) return;

    let debounceTimer = null;

    if (preview) {
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const id = extractTorrentTagId(input.value);
            if (!id) {
                preview.innerHTML = '';
                return;
            }
            debounceTimer = setTimeout(function () {
                const loading = lcEl('div', 'text-muted small');
                loading.append(
                    lcEl('i', 'fa-solid fa-spinner fa-spin me-1'),
                    document.createTextNode(t('preview_loading', 'Loading preview...'))
                );
                preview.replaceChildren(loading);
                // Мы в /admin/ - относительный путь резолвился бы в
                // /admin/ajax_torrent_preview.php (404), файл лежит в
                // корне сайта, нужен абсолютный путь.
                fetch((window.commentsBaseUrl || '') + '/ajax_torrent_preview.php?id=' + encodeURIComponent(id))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.error) {
                            preview.replaceChildren(lcEl('div', 'text-danger small', String(data.error)));
                            return;
                        }
                        const card = lcEl('div', 'card');
                        if (data.image) {
                            const img = lcEl('img', 'card-img-top');
                            img.src = String(data.image);
                            img.style.height = '100px';
                            img.style.objectFit = 'cover';
                            card.appendChild(img);
                        }
                        const body  = lcEl('div', 'card-body py-2 px-3');
                        const title = lcEl('div', 'fw-bold text-truncate small');
                        title.append(lcEl('i', 'fa-solid fa-magnet me-1'), document.createTextNode(String(data.name ?? '')));
                        const meta = lcEl('div', 'text-muted small');
                        meta.append(
                            document.createTextNode(String(data.catname ?? '') + ' \u00B7 ' + String(data.size ?? '') + ' \u00B7 '),
                            lcEl('span', 'text-success', t('preview_seeders', '{1} seeders', data.seeders)),
                            document.createTextNode(' \u00B7 '),
                            lcEl('span', 'text-danger', t('preview_leechers', '{1} leechers', data.leechers))
                        );
                        body.append(title, meta);
                        card.appendChild(body);
                        preview.replaceChildren(card);
                    })
                    .catch(function () {
                        preview.replaceChildren(lcEl('div', 'text-danger small', t('preview_load_failed', 'Failed to load preview')));
                    });
            }, 400);
        });
    }

    const doInsert = function () {
        const id = extractTorrentTagId(input.value);
        if (!id) {
            input.focus();
            return;
        }
        insertTorrentTag(id);
        input.value = '';
        if (preview) preview.innerHTML = '';
    };

    btn.addEventListener('click', doInsert);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doInsert();
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initTorrentTagPanel();
});