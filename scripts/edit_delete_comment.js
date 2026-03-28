document.addEventListener('DOMContentLoaded', function () {
  let commentToEditId = null;
  let commentToDeleteId = null;
  let torrentId = null;

  const editBtn        = document.getElementById('confirmEditComment');
  const deleteBtn      = document.getElementById('confirmDeleteComment');
  const editModalEl    = document.getElementById('editCommentModal');
  const deleteModalEl  = document.getElementById('deleteCommentModal');
  const editTextarea   = document.getElementById('editCommentText');

  

  const editModal = new bootstrap.Modal(editModalEl,   { backdrop: 'static', keyboard: false });
  const deleteModal = new bootstrap.Modal(deleteModalEl,{ backdrop: 'static', keyboard: false });

  // === Делегирование кликов (чтобы работало после замены DOM) ===
  document.addEventListener('click', function (e) {
    // Открыть модалку редактирования
    const editBtnEl = e.target.closest('.edit-comment-btn');
    if (editBtnEl) {
      e.preventDefault();
      commentToEditId = editBtnEl.getAttribute('data-commentid');
      torrentId       = editBtnEl.getAttribute('data-torrentid');
      const existingText = editBtnEl.getAttribute('data-commenttext') || '';
      editTextarea.value = existingText;
      updatePreview();
      editModal.show();
      return;
    }

    // Триггер удаления (именно кнопка удаления)
   const delBtnEl = e.target.closest('.postbit_qdelete');
if (delBtnEl) {
    commentToDeleteId = delBtnEl.getAttribute('data-commentid');
    torrentId         = delBtnEl.getAttribute('data-torrentid');

    // Заполняем превью
    document.getElementById('commentPreviewAuthor').textContent = delBtnEl.getAttribute('data-author')  || 'Unknown';
    document.getElementById('commentPreviewDate').textContent   = delBtnEl.getAttribute('data-date')    || '';
    document.getElementById('commentPreviewId').textContent     = 'CID: ' + (commentToDeleteId || '');
    document.getElementById('commentPreviewText').innerHTML = parseBBCode(
    delBtnEl.getAttribute('data-preview') || 'No content'
);
}
   
   
   
  });

  // === Сохранить изменения (AJAX) ===
  editBtn.addEventListener('click', function () {
    if (!commentToEditId || !torrentId) return;

    const newCommentText = editTextarea.value.trim();
    if (!newCommentText) {
      showToast('Comment text cannot be empty.', 'warning');
      return;
    }

    editBtn.disabled = true;
    const originalText = editBtn.innerHTML;
    editBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Saving...`;

    fetch('comment.php?action=edit2', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ pid: commentToEditId, tid: torrentId, text: newCommentText })
    })
    .then(res => res.json())
    .then(data => {
      if (!data || !data.success) {
        showToast((data && data.error) || 'Failed to update comment.', 'danger');
        return;
      }

      // Полностью заменяем HTML узла комментария
      const container = document.getElementById('comment-' + data.pid);
      if (container && data.html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = data.html;

        const fresh = tmp.querySelector('#comment-' + data.pid) || tmp.firstElementChild;
        if (fresh) container.replaceWith(fresh);
      }

      editModal.hide();
      showToast('Comment updated successfully.', 'success');
    })
    .catch(() => {
      showToast('Request failed. Please try again.', 'danger');
    })
    .finally(() => {
      editBtn.disabled = false;
      editBtn.innerHTML = originalText;
    });
  });

  // === Подтвердить удаление (AJAX) ===
  deleteBtn.addEventListener('click', function () {
    if (!commentToDeleteId || !torrentId) return;

    deleteBtn.disabled = true;
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Deleting...`;

    fetch('comment.php?action=delete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ pid: commentToDeleteId, tid: torrentId })
    })
    .then(res => res.json())
    .then(data => {
      if (!data || !data.success) {
        showToast((data && data.error) || 'Failed to delete comment.', 'danger');
        return;
      }

      const container = document.getElementById('comment-' + commentToDeleteId);
      if (container) container.remove();

      deleteModal.hide();
      showToast('Comment deleted successfully.', 'success');
    })
    .catch(() => {
      showToast('Request failed. Please try again.', 'danger');
    })
    .finally(() => {
      deleteBtn.disabled = false;
      deleteBtn.innerHTML = originalText;
    });
  });


});


// ====== BBCode инструменты для превью в модалке ======
function wrapBBCode(openTag, closeTag) {
  const textarea = document.getElementById("editCommentText");
  if (!textarea) return;
  const start = textarea.selectionStart || 0;
  const end   = textarea.selectionEnd   || 0;
  const selectedText = textarea.value.substring(start, end);
  const before = textarea.value.substring(0, start);
  const after  = textarea.value.substring(end);
  textarea.value = before + openTag + selectedText + closeTag + after;
  textarea.focus();
  textarea.setSelectionRange(start + openTag.length, end + openTag.length);
  updatePreview();
}

function updatePreview() {
  const input = (document.getElementById("editCommentText") || {}).value || '';
  const parsed = parseBBCode(input);
  const preview = document.getElementById("bbcodePreview");
  if (preview) preview.innerHTML = parsed;
}

function parseBBCode(text) {
  return String(text)
    .replace(/\[b\](.*?)\[\/b\]/gi, '<strong>$1</strong>')
    .replace(/\[i\](.*?)\[\/i\]/gi, '<em>$1</em>')
    .replace(/\[u\](.*?)\[\/u\]/gi, '<u>$1</u>')
    .replace(/\[s\](.*?)\[\/s\]/gi, '<s>$1</s>')

    .replace(/\[left\](.*?)\[\/left\]/gis,   '<div style="text-align:left;">$1</div>')
    .replace(/\[center\](.*?)\[\/center\]/gis,'<div style="text-align:center;">$1</div>')
    .replace(/\[right\](.*?)\[\/right\]/gis, '<div style="text-align:right;">$1</div>')

    .replace(/\[color=(#[a-zA-Z0-9]+|[a-zA-Z]+)\](.*?)\[\/color\]/gi, '<span style="color:$1;">$2</span>')
    .replace(/\[size=(\d+)\](.*?)\[\/size\]/gi, '<span style="font-size:$1px;">$2</span>')

    .replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
    
	.replace(/\[img\](.*?)\[\/img\]/gi, '<img src="$1" alt="Image" class="rounded" style="max-width: 400px;">')
	
	
    .replace(/\[video\](.*?)\[\/video\]/gi, '<video controls style="max-width:100%;"><source src="$1" type="video/mp4"></video>')
    .replace(/\[youtube\](.*?)\[\/youtube\]/gi, '<iframe width="100%" height="315" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen referrerpolicy="no-referrer"></iframe>')

    .replace(/\[quote\](.*?)\[\/quote\]/gis, '<blockquote>$1</blockquote>')
    .replace(/\[code\](.*?)\[\/code\]/gis, '<pre><code>$1</code></pre>')

    .replace(/\[list\](.*?)\[\/list\]/gis, (_, c) => '<ul>' + c.replace(/\[\*\](.*)/g, '<li>$1</li>') + '</ul>')
    .replace(/\[list=1\](.*?)\[\/list\]/gis,(_, c) => '<ol>' + c.replace(/\[\*\](.*)/g, '<li>$1</li>') + '</ol>')

    .replace(/\n/g, "<br>");
}

// Живое превью
document.addEventListener("DOMContentLoaded", function () {
  const textarea = document.getElementById("editCommentText");
  if (textarea) textarea.addEventListener("input", updatePreview);
});







// ====== МАССОВОЕ УДАЛЕНИЕ КОММЕНТАРИЕВ ======
// Глобальные переменные для хранения выбранных комментариев
if (typeof window.selectedCommentIds === 'undefined') {
    window.selectedCommentIds = [];
}
if (typeof window.selectedTorrentIds === 'undefined') {
    window.selectedTorrentIds = [];
}




function massDeleteComments() {
    const selectedCheckboxes = document.querySelectorAll('.comment-checkbox:checked');

    if (selectedCheckboxes.length === 0) {
        showToast('Please select at least one comment to delete.', 'warning');
        return;
    }

    window.selectedCommentIds = [];
    window.selectedTorrentIds = [];

    var previewHTML = '';

    selectedCheckboxes.forEach(function(checkbox) {
        window.selectedCommentIds.push(checkbox.value);
        window.selectedTorrentIds.push(checkbox.dataset.tid);

        var cid       = checkbox.value;
        var deleteBtn = document.querySelector('.postbit_qdelete[data-commentid="' + cid + '"]');
        var author    = deleteBtn ? (deleteBtn.getAttribute('data-author')  || 'Unknown') : 'Unknown';
        var date      = deleteBtn ? (deleteBtn.getAttribute('data-date')    || '')        : '';
        var preview   = deleteBtn ? (deleteBtn.getAttribute('data-preview') || '')        : '';

        previewHTML +=
            '<div class="card mb-2 border-danger border-opacity-25">' +
                '<div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">' +
                    '<span class="small fw-bold text-danger">' +
                        '<i class="fas fa-user me-1"></i>' + author +
                    '</span>' +
                    '<div class="d-flex gap-2 align-items-center">' +
                        '<span class="text-muted small">' + date + '</span>' +
                        '<span class="badge bg-secondary">CID: ' + cid + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="card-body py-2 px-3 small">' +
                    (preview ? parseBBCode(preview) : '<span class="text-muted">No content</span>') +
                '</div>' +
            '</div>';
    });

    var selectedCountElement = document.getElementById('selectedCommentsCount');
    if (selectedCountElement) {
        selectedCountElement.textContent = window.selectedCommentIds.length;
    }

    var previewList = document.getElementById('massDeletePreviewList');
    if (previewList) {
        previewList.innerHTML = previewHTML;
    }

    const massDeleteModal = new bootstrap.Modal(document.getElementById('massDeleteConfirmModal'));
    massDeleteModal.show();
}

// Функция для выполнения удаления после подтверждения
function executeMassDelete() {
    // Показываем индикатор загрузки в модалке
    const confirmButton = document.getElementById('confirmMassDelete');
    const originalText = confirmButton.innerHTML;
    confirmButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    confirmButton.disabled = true;

    // Создаем FormData для отправки
    const formData = new FormData();
    formData.append('comment_ids', window.selectedCommentIds.join(','));
    formData.append('torrent_ids', window.selectedTorrentIds.join(','));

    // Отправляем AJAX запрос
    fetch('comment.php?action=massdelete', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response');
            });
        }
        return response.json();
    })
    .then(data => {
        // Закрываем модалку
        const massDeleteModal = bootstrap.Modal.getInstance(document.getElementById('massDeleteConfirmModal'));
        if (massDeleteModal) {
            massDeleteModal.hide();
        }

        if (data.success) {
            // Удаляем комментарии из DOM
            window.selectedCommentIds.forEach(commentId => {
                const commentElement = document.getElementById('comment-' + commentId);
                if (commentElement) {
                    commentElement.style.opacity = '0';
                    commentElement.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        commentElement.remove();
                    }, 300);
                }
            });
            
            // Обновляем счетчики
            updateCommentCounters(window.selectedCommentIds.length);
            
            // Показываем уведомление об успехе
            showToast(`Successfully deleted ${data.deleted || window.selectedCommentIds.length} comments!`, 'success');
        } else {
            // Показываем ошибку
            showToast('Error: ' + (data.error || 'Failed to delete comments'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error occurred. Please check console for details.', 'error');
    })
    .finally(() => {
        // Восстанавливаем кнопку подтверждения
        if (confirmButton) {
            confirmButton.innerHTML = originalText;
            confirmButton.disabled = false;
        }
        
        // Сбрасываем кнопку массового удаления
        const massDeleteButton = document.getElementById('massDeleteButton');
        if (massDeleteButton) {
            massDeleteButton.classList.add('d-none');
        }
        
        // Снимаем выделение со всех чекбоксов
        document.querySelectorAll('.comment-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Обновляем чекбокс "Выделить все"
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
        
        // Очищаем массивы
        window.selectedCommentIds = [];
        window.selectedTorrentIds = [];
    });
}

// Обработчик для кнопки подтверждения в модалке
if (document.getElementById('confirmMassDelete')) {
    document.getElementById('confirmMassDelete').addEventListener('click', executeMassDelete);
}

// Функция для показа/скрытия кнопки массового удаления
function toggleMassDeleteButton() {
    const checkboxes = document.querySelectorAll('.comment-checkbox:checked');
    const massDeleteButton = document.getElementById('massDeleteButton');
    
    if (massDeleteButton && checkboxes.length > 0) {
        massDeleteButton.classList.remove('d-none');
        massDeleteButton.innerHTML = `<i class="fa-solid fa-trash"></i> Delete Selected (${checkboxes.length})`;
    } else if (massDeleteButton) {
        massDeleteButton.classList.add('d-none');
    }
}

// Функция "Выделить все"/"Снять выделение"
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.comment-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    toggleMassDeleteButton();
}

// Простая функция обновления счетчиков
function updateCommentCounters(deletedCount) {
    // Ищем все элементы с счетчиками комментариев
    const counterElements = document.querySelectorAll('[class*="comment"], [id*="comment"]');
    
    counterElements.forEach(element => {
        const text = element.textContent || '';
        const match = text.match(/(\d+)\s*comment/i);
        
        if (match) {
            const currentCount = parseInt(match[1]);
            const newCount = Math.max(0, currentCount - deletedCount);
            element.textContent = text.replace(match[0], newCount + ' comment' + (newCount !== 1 ? 's' : ''));
        }
    });
    
    // Обновляем badge с количеством
    const badgeElements = document.querySelectorAll('.badge');
    badgeElements.forEach(badge => {
        const badgeText = badge.textContent;
        if (/^\d+$/.test(badgeText)) {
            const currentCount = parseInt(badgeText);
            const newCount = Math.max(0, currentCount - deletedCount);
            badge.textContent = newCount;
        }
    });
    
    // Если удалили все комментарии, показываем сообщение
    const remainingComments = document.querySelectorAll('.closest').length;
    if (remainingComments === 0) {
        const commentsContainer = document.querySelector('.container-md') || document.querySelector('body');
        const noCommentsMessage = document.createElement('div');
        noCommentsMessage.className = 'alert alert-info mt-3';
        noCommentsMessage.innerHTML = '<i class="fa-solid fa-info-circle"></i> No comments yet. Be the first to comment!';
        if (commentsContainer) {
            commentsContainer.appendChild(noCommentsMessage);
        }
    }
}

// ====== КОНЕЦ МАССОВОГО УДАЛЕНИЯ ======