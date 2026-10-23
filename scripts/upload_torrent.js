/* ── i18n ─────────────────────────────────────────────────────────────────
   Strings come from AGS_LANG (js_ut_* keys in upload.lang.php, printed by upload.php
   before this script). Missing key -> English fallback. {1} and %1$s are both substituted.
   Result is plain text: escape it before putting it into innerHTML. */
function ut(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : null;
    const str  = (dict && typeof dict['ut_' + key] === 'string') ? dict['ut_' + key] : fallback;
    return String(str).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
        const i = parseInt(a || b, 10) - 1;
        return (i >= 0 && i < args.length) ? String(args[i]) : m;
    });
}

// Translated text escaped for innerHTML / template literals
function utH(key, fallback, ...args) {
    return escapeHtml(ut(key, fallback, ...args));
}

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

let deleteId = null;
let deleteImageSrc = null;
let deleteFilename = null;

// Функция для обработки ошибки загрузки изображения
function handleImageError(img) {
  if (img) {
    img.style.display = 'none';
  }
  const noImageDiv = document.getElementById('noImagePreview');
  if (noImageDiv) {
    noImageDiv.style.display = 'flex';
  }
}

// Функция для открытия модалки удаления
function openDeleteModal(id, filename, imageSrc) {
  console.log('Opening modal with:', { id, filename, imageSrc }); // Для отладки
  
  deleteId = id;
  deleteImageSrc = imageSrc;
  deleteFilename = filename;
  
  // Проверяем существование элементов перед обновлением
  const titleElement = document.getElementById('deleteScreenshotTitle');
  const filenameElement = document.getElementById('deleteScreenshotFilename');
  const fileNameElement = document.getElementById('deleteScreenshotFileName');
  const previewContainer = document.getElementById('deleteScreenshotPreviewContainer');
  const previewImg = document.getElementById('deleteScreenshotImage');
  const noImageDiv = document.getElementById('noImagePreview');
  
  // Обновляем только если элементы существуют
  if (titleElement) {
    titleElement.textContent = ut('delete_title', 'Delete screenshot?');
  } else {
    console.warn('Element deleteScreenshotTitle not found');
  }
  
  if (filenameElement) {
    filenameElement.innerHTML = '<strong>"' + escapeHtml(filename) + '"</strong>';
  } else {
    console.warn('Element deleteScreenshotFilename not found');
  }
  
  if (fileNameElement) {
    fileNameElement.textContent = filename;
  } else {
    console.warn('Element deleteScreenshotFileName not found');
  }
  
  // Показываем превью если есть изображение
  if (previewContainer && previewImg && noImageDiv) {
    if (deleteImageSrc && deleteImageSrc !== '') {
      previewImg.onerror = function () { handleImageError(this); };
      previewImg.src = deleteImageSrc;
      previewImg.style.display = 'block';
      noImageDiv.style.display = 'none';
      previewContainer.style.display = 'block';
    } else {
      previewImg.style.display = 'none';
      noImageDiv.style.display = 'flex';
      previewContainer.style.display = 'block';
    }
  } else {
    console.warn('Preview elements not found:', {
      previewContainer: !!previewContainer,
      previewImg: !!previewImg,
      noImageDiv: !!noImageDiv
    });
  }
  
  // Показываем модалку
  const modalElement = document.getElementById('deleteScreenshotModal');
  if (modalElement) {
    try {
      const modal = new bootstrap.Modal(modalElement);
      modal.show();
    } catch (error) {
      console.error('Error showing modal:', error);
      if (typeof showToast === 'function') {
        showToast(ut('modal_error', 'Could not open the dialog'), 'error');
      }
    }
  } else {
    console.error('Modal element not found');
    if (typeof showToast === 'function') {
      showToast(ut('modal_error', 'Could not open the dialog'), 'error');
    }
  }
}

// Обработчик для кнопки подтверждения удаления
const confirmBtn = document.getElementById('confirmDeleteScreenshotBtn');
if (confirmBtn) {
  confirmBtn.addEventListener('click', function() {
    if (!deleteId) {
      if (typeof showToast === 'function') {
        showToast(ut('no_selected', 'No screenshot selected for deletion'), 'error');
      }
      return;
    }

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> ' + escapeHtml(ut('deleting', 'Deleting...'));

    // Создаем FormData для отправки
    const formData = new FormData();
    formData.append('action', 'delete_screenshot');
    formData.append('screenshot_id', deleteId);
    formData.append('my_post_key', typeof myPostKey !== 'undefined' ? myPostKey : '');

    // AJAX запрос на удаление
    fetch(window.location.href, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(data => {
      // Скрываем модалку
      const modalElement = document.getElementById('deleteScreenshotModal');
      if (modalElement) {
        try {
          const modal = bootstrap.Modal.getInstance(modalElement);
          if (modal) modal.hide();
        } catch (error) {
          console.error('Error hiding modal:', error);
        }
      }

      if (data.success) {
         // Удаляем элемент скриншота из DOM
        const screenshotItem = document.querySelector(`.screenshot-item[data-id="${deleteId}"]`);
        if (screenshotItem) {
          screenshotItem.style.transition = 'all 0.3s ease';
          screenshotItem.style.opacity = '0';
          screenshotItem.style.transform = 'scale(0.8)';
          setTimeout(() => screenshotItem.remove(), 300);
        }

        // ✅ Обновляем счётчик — иначе JS будет думать, что скринов всё ещё столько же,
        // и может заблокировать загрузку новых, хотя место уже освободилось.
        if (typeof existingScreenshotsCount !== 'undefined') {
          existingScreenshotsCount = Math.max(0, existingScreenshotsCount - 1);
        }
        
        // Показываем toast с успехом
        if (typeof showToast === 'function') {
          showToast(ut('shot_deleted', 'Screenshot deleted!'), 'success');
        } else {
          console.log('Screenshot deleted successfully!');
        }
        
        // Если после удаления не осталось скриншотов, показываем сообщение
        const remainingScreenshots = document.querySelectorAll('.screenshot-item').length;
        if (remainingScreenshots === 0) {
          const container = document.getElementById('existingScreenshots');
          if (container) {
            container.innerHTML = '<p class="text-muted text-center py-3">' + escapeHtml(ut('no_shots', 'No screenshots yet')) + '</p>';
          }
        }
      } else {
        // Показываем toast с ошибкой
        if (typeof showToast === 'function') {
          showToast(data.error || ut('delete_failed', 'Could not delete the screenshot.'), 'error');
        } else {
          console.error('Failed to delete screenshot:', data.error);
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      
      if (typeof showToast === 'function') {
        showToast(ut('delete_error', 'An error occurred while deleting the screenshot.'), 'error');
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
      deleteId = null;
      deleteImageSrc = null;
      deleteFilename = null;
    });
  });
} else {
  console.error('Confirm delete button not found');
}

// ── Массовый выбор существующих скриншотов ──────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCb   = document.getElementById('selectAllScreenshotsCheckbox');
    const deleteBtn     = document.getElementById('deleteSelectedScreenshotsBtn');
    const countEl       = document.getElementById('selectedScreenshotsCount');
    const container      = document.getElementById('existingScreenshots');
    if (!selectAllCb || !deleteBtn || !container) return;

    function getCheckboxes() {
        return Array.from(container.querySelectorAll('.screenshot-select-checkbox'));
    }

    function updateSelectedUi() {
        const checked = getCheckboxes().filter(cb => cb.checked);
        countEl.textContent = checked.length;
        deleteBtn.classList.toggle('d-none', checked.length === 0);

        const all = getCheckboxes();
        selectAllCb.checked = all.length > 0 && checked.length === all.length;
        selectAllCb.indeterminate = checked.length > 0 && checked.length < all.length;
    }

    container.addEventListener('change', function (e) {
        if (e.target.classList.contains('screenshot-select-checkbox')) {
            updateSelectedUi();
        }
    });

    selectAllCb.addEventListener('change', function () {
        getCheckboxes().forEach(cb => { cb.checked = selectAllCb.checked; });
        updateSelectedUi();
    });

    let pendingBulkDeleteIds = [];

    deleteBtn.addEventListener('click', function () {
        const checked = getCheckboxes().filter(cb => cb.checked);
        if (!checked.length) return;

        pendingBulkDeleteIds = checked.map(cb => cb.dataset.id);

        const countEl2 = document.getElementById('bulkDeleteScreenshotsCount');
        if (countEl2) countEl2.textContent = pendingBulkDeleteIds.length;

        // Миниатюры выбранных скриншотов - берём src у уже отрисованной
        // картинки внутри того же .screenshot-item, отдельный запрос не нужен.
        // Показываем максимум 10 превью, остальное - просто "+N ещё".
        const MAX_PREVIEW = 10;
        const previewEl = document.getElementById('bulkDeleteScreenshotsPreview');
        const previewLabelEl = document.getElementById('bulkDeleteScreenshotsPreviewLabel');

        if (previewLabelEl) {
            previewLabelEl.textContent = ut('selected_shots', 'Selected screenshots ({1})', checked.length);
            previewLabelEl.classList.remove('d-none');
        }

        if (previewEl) {
            const shown = checked.slice(0, MAX_PREVIEW);
            const extra = checked.length - shown.length;

            previewEl.innerHTML = shown.map(cb => {
                const item = cb.closest('.screenshot-item');
                const img = item ? item.querySelector('img') : null;
                const src = img ? img.src : '';
                return src
                    ? '<img src="' + src + '" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">'
                    : '';
            }).join('');

            if (extra > 0) {
                previewEl.innerHTML += '<div class="d-flex align-items-center justify-content-center text-muted small fw-semibold" ' +
                    'style="width:64px;height:64px;border-radius:6px;border:1px dashed #adb5bd;">+' + extra + '</div>';
            }
        }

        const modalEl = document.getElementById('bulkDeleteScreenshotsModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    });

    const confirmBulkBtn = document.getElementById('confirmBulkDeleteScreenshotsBtn');
    if (confirmBulkBtn) {
        confirmBulkBtn.addEventListener('click', function () {
            if (!pendingBulkDeleteIds.length) return;

            const modalEl = document.getElementById('bulkDeleteScreenshotsModal');
            const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
            if (modalInstance) modalInstance.hide();

            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + escapeHtml(ut('deleting', 'Deleting...'));

            // Тот же эндпоинт, что и для одиночного удаления - вызываем по
            // очереди для каждого выбранного ID, без изменений на бэкенде.
           // ✅ Один запрос на всё — сервер сам проверит владельца и удалит файлы.
            const formData = new FormData();
            formData.append('action', 'delete_screenshots');
            formData.append('my_post_key', typeof myPostKey !== 'undefined' ? myPostKey : '');
            pendingBulkDeleteIds.forEach(id => formData.append('screenshot_ids[]', id));

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    if (typeof showToast === 'function') {
                        showToast(data.error || ut('delete_many_failed', 'Could not delete the screenshots.'), 'error');
                    }
                    return;
                }

                // Убираем из DOM те элементы, чьи ID есть в pendingBulkDeleteIds
                // (сервер удалил их все разом — отдельно поштучно проверять не надо).
                let removed = 0;
                pendingBulkDeleteIds.forEach(id => {
                    const item = container.querySelector(`.screenshot-item[data-id="${id}"]`);
                    if (item) {
                        item.remove();
                        removed++;
                    }
                });

                // Обновляем счётчик по факту ответа сервера
                const actualDeleted = (typeof data.deleted === 'number') ? data.deleted : removed;
                if (typeof existingScreenshotsCount !== 'undefined') {
                    existingScreenshotsCount = Math.max(0, existingScreenshotsCount - actualDeleted);
                }

                if (typeof showToast === 'function') {
                    let msg = ut('deleted_many', '{1} screenshot(s) deleted.', actualDeleted);
                    if (data.denied) {
                        msg += ' ' + ut('skipped_denied', '{1} skipped (no permission).', data.denied);
                    }
                    showToast(msg, data.denied ? 'warning' : 'success');
                }

                if (container.querySelectorAll('.screenshot-item').length === 0) {
                    container.innerHTML = '<p class="text-muted text-center py-3">' + escapeHtml(ut('no_shots', 'No screenshots yet')) + '</p>';
                }

                pendingBulkDeleteIds = [];
                updateSelectedUi();
            })
            .catch(() => {
                if (typeof showToast === 'function') {
                    showToast(ut('network_delete', 'Network error while deleting screenshots.'), 'error');
                }
            })
            .finally(() => {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            });
			
			
        });
    }

});

// Очищаем превью при закрытии модалки
const modalElement = document.getElementById('deleteScreenshotModal');
if (modalElement) {
  modalElement.addEventListener('hidden.bs.modal', function() {
    const previewImg = document.getElementById('deleteScreenshotImage');
    const filenameElement = document.getElementById('deleteScreenshotFilename');
    const noImageDiv = document.getElementById('noImagePreview');
    
    if (previewImg) {
      previewImg.onerror = null;
      previewImg.src = '';
    }
    if (filenameElement) filenameElement.innerHTML = '';
    if (noImageDiv) noImageDiv.style.display = 'none';
  });
} else {
  console.error('Modal element not found for hidden event');
}

// Инициализация кнопок удаления
document.addEventListener('DOMContentLoaded', function() {
  // Убеждаемся что toast система инициализирована
  if (typeof initToastSystem === 'function') {
    initToastSystem();
  }
  
  // Проверяем наличие всех необходимых элементов модалки
  const requiredElements = [
    'deleteScreenshotModal',
    'deleteScreenshotTitle',
    'deleteScreenshotFilename',
    'deleteScreenshotFileName',
    'deleteScreenshotPreviewContainer',
    'deleteScreenshotImage',
    'noImagePreview',
    'confirmDeleteScreenshotBtn'
  ];
  
  let allElementsFound = true;
  requiredElements.forEach(id => {
    if (!document.getElementById(id)) {
      console.error(`Required element "${id}" not found in DOM`);
      allElementsFound = false;
    }
  });
  
  if (!allElementsFound) {
    console.error('Some required modal elements are missing!');
    if (typeof showToast === 'function') {
      showToast(ut('modal_error', 'Could not open the dialog'), 'error');
    }
  }
  
  // Используем делегирование событий для динамических элементов
  document.addEventListener('click', function(e) {
    const deleteBtn = e.target.closest('.delete-screenshot-btn, .delete-btn');
    if (!deleteBtn) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    // Находим родительский элемент screenshot-item
    const screenshotItem = deleteBtn.closest('.screenshot-item');
    if (!screenshotItem) {
      console.error('Could not find screenshot item');
      if (typeof showToast === 'function') {
        showToast(ut('item_missing', 'Screenshot not found on the page'), 'error');
      }
      return;
    }
    
    // Получаем ID из data-атрибута
    const id = screenshotItem.dataset.id;
    if (!id) {
      console.error('No ID found for screenshot');
      if (typeof showToast === 'function') {
        showToast(ut('missing_id', 'Cannot delete: screenshot ID is missing'), 'error');
      }
      return;
    }
    
    // Находим изображение внутри screenshot-item
    const img = screenshotItem.querySelector('.preview-screenshot');
    const imageSrc = img ? img.src : '';
    
    // Получаем имя файла из src
    let filename = 'screenshot.jpg';
    if (imageSrc) {
      filename = imageSrc.split('/').pop();
    }
    
    console.log('Delete button clicked:', { id, filename, imageSrc });
    
    openDeleteModal(id, filename, imageSrc);
  });
});

// Проверяем загрузку toast системы
document.addEventListener('DOMContentLoaded', function() {
  if (typeof showToast !== 'function') {
    console.warn('showToast function not found. Make sure toast.js is loaded before this script.');
  }
});

// Инициализация переключения между URL и файлом
document.addEventListener('DOMContentLoaded', function() {
    // Для первого изображения
    document.querySelectorAll('input[name="uploadType1"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isUrl = this.value === 'url';
            document.getElementById('uploadUrlGroup1').classList.toggle('d-none', !isUrl);
            document.getElementById('uploadFileGroup1').classList.toggle('d-none', isUrl);
            
            // Очищаем неактивное поле
            if (isUrl) {
                document.getElementById('imagesUpload').value = '';
            } else {
                document.getElementById('imageUrl').value = '';
            }
        });
    });

    // Для второго изображения
    document.querySelectorAll('input[name="uploadType2"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isUrl = this.value === 'url';
            document.getElementById('uploadUrlGroup2').classList.toggle('d-none', !isUrl);
            document.getElementById('uploadFileGroup2').classList.toggle('d-none', isUrl);
            
            // Очищаем неактивное поле
            if (isUrl) {
                document.getElementById('imagesUpload2').value = '';
            } else {
                document.getElementById('imageUrl2').value = '';
            }
        });
    });
    
    // Инициализируем начальное состояние
    document.querySelector('#uploadByUrl1').dispatchEvent(new Event('change'));
    document.querySelector('#uploadByUrl2').dispatchEvent(new Event('change'));
});

// Обработка загрузки изображения из файла
function handleImageUpload(input, previewContainerId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Валидация файла
        if (!file.type.match('image.*')) {
            alert(ut('image_type', 'Please choose an image file (JPG, PNG, GIF, WebP)'));
            input.value = '';
            return;
        }
        
        if (file.size > 200 * 1024 * 1024) { // 10MB
            alert(ut('image_size', 'The image must be smaller than 10 MB'));
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            updateImagePreview(e.target.result, previewContainerId);
        };
        reader.readAsDataURL(file);
    }
}

// Обновление предпросмотра изображения
function updateImagePreview(imageSrc, previewContainerId) {
    const previewContainer = document.getElementById(previewContainerId);
    
    previewContainer.innerHTML = `
        <div class="preview-item">
            <img src="${imageSrc}" 
                 class="preview-poster" 
                 alt="${utH('image_preview', 'Image preview')}">
            <button type="button" 
                    class="delete-btn" 
                    onclick="removeImage('${previewContainerId}', '${previewContainerId === 'imagePreview' ? 'imageUrl' : 'imageUrl2'}', '${previewContainerId === 'imagePreview' ? 'imagesUpload' : 'imagesUpload2'}')"
                    title="${escapeHtml(ut('image_remove', 'Remove image'))}">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
}

// Обновление предпросмотра из URL
window.updateImagePreviewFromUrl = function(url, previewContainerId) {
    if (!url.trim()) {
        removeImage(previewContainerId);
        return;
    }
    
    const previewContainer = document.getElementById(previewContainerId);
    previewContainer.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> ' + escapeHtml(ut('loading', 'Loading...')) + '</div>';
    
    const img = new Image();
    img.onload = function() {
        updateImagePreview(url, previewContainerId);
    };
    
    img.onerror = function() {
        previewContainer.innerHTML = `
            <div class="alert alert-warning p-2">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${escapeHtml(ut('image_url_fail', 'Cannot load the image from this link'))}
            </div>
        `;
    };
    
    img.src = url;
}

// Удаление изображения
window.removeImage = function(previewContainerId, urlInputId = null, fileInputId = null) {
    const previewContainer = document.getElementById(previewContainerId);
    previewContainer.innerHTML = '';
    
    // Очищаем соответствующие поля ввода
    if (urlInputId) {
        const urlInput = document.getElementById(urlInputId);
        if (urlInput) urlInput.value = '';
    }
    
    if (fileInputId) {
        const fileInput = document.getElementById(fileInputId);
        if (fileInput) fileInput.value = '';
    }
}

// Drag & Drop для зон загрузки
document.querySelectorAll('.upload-zone-sm').forEach(zone => {
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '#4f46e5';
        this.style.backgroundColor = '#e0e7ff';
    });
    
    zone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '';
        this.style.backgroundColor = '';
    });
    
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.style.borderColor = '';
        this.style.backgroundColor = '';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            const card = this.closest('.card');
            const isFirstImage = card.querySelector('h6').textContent.includes('Main');
            const inputId = isFirstImage ? 'imagesUpload' : 'imagesUpload2';
            const previewId = isFirstImage ? 'imagePreview' : 'imagePreview2';
            
            // Выбираем загрузку файлом
            const radioId = isFirstImage ? 'uploadByFile1' : 'uploadByFile2';
            document.getElementById(radioId).checked = true;
            document.getElementById(radioId).dispatchEvent(new Event('change'));
            
            // Устанавливаем файл в input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById(inputId).files = dataTransfer.files;
            
            // Обрабатываем файл
            setTimeout(() => {
                handleImageUpload(document.getElementById(inputId), previewId);
            }, 100);
        }
    });
});

// ========== Остальной существующий код ==========

function showErrorModal(message) {
    // Принудительно закрываем uploadModal
    const uModal = document.getElementById('uploadModal');
    if (uModal) {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        uModal.classList.remove('show');
        uModal.style.display = 'none';
        uModal.setAttribute('aria-hidden', 'true');
    }

    const body = document.getElementById('errorModalBody');
    if (body) {
        body.innerHTML = String(message)
            .split('\n')
            .map(line => {
                let s = line.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                s = s.replace(/(SQL Error|Unknown column|Failed Query|Error)/gi, '<strong style="color:#b91c1c">$1</strong>');
                return `<div class="error-line">${s}</div>`;
            })
            .join('');
        body.scrollTop = 0;
    }

    // Кнопка Copy
    const copyBtn = document.getElementById('copyErrorBtn');
    if (copyBtn) {
        copyBtn.onclick = () => {
            const text = body ? (body.innerText || body.textContent) : '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text.trim()).then(() => {
                    const orig = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="fas fa-check me-1"></i>' + escapeHtml(ut('copied', 'Copied!'));
                    copyBtn.classList.replace('btn-danger', 'btn-success');
                    setTimeout(() => {
                        copyBtn.innerHTML = orig;
                        copyBtn.classList.replace('btn-success', 'btn-danger');
                    }, 2000);
                }).catch(() => {});
            }
        };
    }

    setTimeout(() => {
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }, 100);
}

// Создаем глобальные переменные для модальных окон
let uploadModal = null;
let uploadModalEl = null;
let uploadTimer = null;
let uploadSeconds = 0;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация модального окна загрузки
    uploadModalEl = document.getElementById('uploadModal');
    uploadModal = new bootstrap.Modal(uploadModalEl);
    
    // Инициализация счетчика символов
    const nameField = document.getElementById('formName');
    if (nameField) {
        const charCount = nameField.value.length;
        const counter = document.getElementById('formNameCharCount');
        if (counter) {
            counter.textContent = charCount;
        }
    }
    
    // Обработчик скрытия модального окна загрузки
    uploadModalEl.addEventListener('hidden.bs.modal', function() {
        stopUploadTimer();
        updateUploadProgress(0, ut('ready', 'Ready to upload'));
    });
});




document.addEventListener('DOMContentLoaded', function() {
    const uploadEl = document.getElementById('screenshotsUpload');
    if (!uploadEl) return;

    const maxScreenshots = parseInt(uploadEl.dataset.max || '3');

    uploadEl.addEventListener('change', function() {
        if (this.files.length === 0) return;

        const selectedCount = this.files.length;
        const grid       = document.getElementById('modalPreviewGrid');
        const count      = document.getElementById('modalPreviewCount');
        const confirmBtn = document.querySelector('#screenshotPreviewModal .btn-primary');

        grid.innerHTML = '';

        // Превью всех файлов
        Array.from(this.files).forEach((file, i) => {
            const reader = new FileReader();
            reader.onload = e => {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-lg-3';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-fluid rounded" style="aspect-ratio:16/9;object-fit:cover;width:100%">
                        <span class="position-absolute top-0 start-0 badge bg-dark m-1">${i + 1}</span>
                        <span class="position-absolute bottom-0 end-0 badge bg-secondary m-1" style="font-size:.65rem">
                            ${(file.size / 1024).toFixed(0)} KB
                        </span>
                    </div>
                    <p class="text-muted small text-truncate mt-1 mb-0">${escapeHtml(file.name)}</p>`;
                grid.appendChild(col);
            };
            reader.readAsDataURL(file);
        });

        // Проверка лимита на одну загрузку
        if (selectedCount > maxScreenshots) {
            count.innerHTML = `<span class="text-danger">
                <i class="fas fa-exclamation-circle me-1"></i>
                ${ut('too_many', 'Too many! Max {1}, selected {2}.', '<strong>' + Number(maxScreenshots) + '</strong>', '<strong>' + Number(selectedCount) + '</strong>')}
            </span>`;
            confirmBtn.disabled = true;
        } else {
    // Проверка общего лимита (существующие + новые) — по настройке группы
    const existingCount = (typeof existingScreenshotsCount !== 'undefined') ? existingScreenshotsCount : 0;
    const totalAfter = existingCount + selectedCount;
    if (totalAfter > maxScreenshots) {
        count.innerHTML = `<span class="text-danger">
            <i class="fas fa-exclamation-circle me-1"></i>
            ${ut('limit_total', 'You already have {1} screenshot(s). Max {2} in total — you can add up to {3} more.', '<strong>' + Number(existingCount) + '</strong>', Number(maxScreenshots), '<strong>' + Math.max(0, maxScreenshots - existingCount) + '</strong>')}
        </span>`;
        confirmBtn.disabled = true;
    } else {
        count.textContent = ut('count', '{1} / {2} screenshots', totalAfter, maxScreenshots);
        confirmBtn.disabled = false;
    }
}

        new bootstrap.Modal(document.getElementById('screenshotPreviewModal')).show();
    });
});

function clearScreenshots() {
    const uploadEl = document.getElementById('screenshotsUpload');
    if (uploadEl) uploadEl.value = '';

    const preview = document.getElementById('screenshotsPreview');
    if (preview) preview.innerHTML = '';

    const grid = document.getElementById('modalPreviewGrid');
    if (grid) grid.innerHTML = '';

    const confirmBtn = document.querySelector('#screenshotPreviewModal .btn-primary');
    if (confirmBtn) confirmBtn.disabled = false;

    const modal = bootstrap.Modal.getInstance(document.getElementById('screenshotPreviewModal'));
    if (modal) modal.hide();
}


function showSelectedPreviews() {
    const uploadEl  = document.getElementById('screenshotsUpload');
    const preview   = document.getElementById('screenshotsPreview');
    if (!uploadEl || !preview) return;

    preview.innerHTML = '';

    Array.from(uploadEl.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const item = document.createElement('div');
            item.className = 'screenshot-item';
            item.style.position = 'relative';
            item.innerHTML = `
                <img src="${e.target.result}" class="preview-screenshot" alt="Screenshot ${i + 1}">
                <div class="screenshot-order-badge position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75 m-1" style="font-size:10px;pointer-events:none">#${i + 1}</div>`;
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}






// Form Validation helper function
function validateForm() {
    let valid = true;

    // Torrent File validation
    const torrentFileInput = document.getElementById("torrentFile");
    const isEdit = document.querySelector('input[name="EditTorrent"]') !== null;
    if (!isEdit && !torrentFileInput.files.length) {
        torrentFileInput.classList.add("is-invalid");
        valid = false;
    } else {
        torrentFileInput.classList.remove("is-invalid");
    }

    // Form Name validation
    const formNameInput = document.getElementById("formName");
    const formNameValue = formNameInput.value.trim();
    if (!formNameValue || formNameValue.length < 3 || formNameValue.length > 255) {
        formNameInput.classList.add("is-invalid");
        valid = false;
    } else {
        formNameInput.classList.remove("is-invalid");
    }

    // Description validation
    const descriptionInput = document.getElementById("description");
    if (!descriptionInput.value.trim()) {
        descriptionInput.classList.add("is-invalid");
        valid = false;
    } else {
        descriptionInput.classList.remove("is-invalid");
    }

    // ── Category validation ──────────────────────────────
    const categoryInput = document.getElementById("categorySelected");
    const categoryError = document.getElementById("categoryError");
    const categoryLabel = document.getElementById("categoryLabel");

    if (!categoryInput.value || categoryInput.value === '0') {
        categoryError.style.display = 'block';
        categoryLabel.innerHTML =
            '<span class="text-danger">' +
            '<i class="fas fa-exclamation-circle me-1"></i>' + escapeHtml(ut('select_category', 'Please select a category')) +
            '</span>';
        // Подсвечиваем все кнопки красной рамкой
        document.querySelectorAll('.cat-pick-btn').forEach(btn => {
            btn.style.borderColor = '#dc3545';
        });
        valid = false;
    } else {
        categoryError.style.display = 'none';
        // Убираем красную рамку
        document.querySelectorAll('.cat-pick-btn').forEach(btn => {
            btn.style.borderColor = '';
        });
    }

    return valid;
}

// Функция для запуска таймера
function startUploadTimer() {
    uploadSeconds = 0;
    const timerElement = document.getElementById('uploadTimer');
    if (timerElement) {
        timerElement.textContent = '0';
    }
    
    if (uploadTimer) clearInterval(uploadTimer);
    
    uploadTimer = setInterval(() => {
        uploadSeconds++;
        if (timerElement) {
            timerElement.textContent = uploadSeconds;
        }
        
        // Обновляем прогресс бар (симуляция)
        const progress = Math.min(uploadSeconds * 2, 90); // Максимум 90% до ответа сервера
        updateUploadProgress(progress, getStatusText(progress));
        
    }, 1000);
}

// Функция для остановки таймера
function stopUploadTimer() {
    if (uploadTimer) {
        clearInterval(uploadTimer);
        uploadTimer = null;
    }
}

// Функция для обновления прогресса
function updateUploadProgress(percentage, statusText = '') {
    const progressBar = document.getElementById('uploadProgressBar');
    const statusElement = document.getElementById('uploadStatusText');
    const percentageElement = document.getElementById('progressPercentage');
    
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
    }
    
    if (percentageElement) {
        percentageElement.textContent = percentage + '%';
    }
    
    if (statusElement && statusText) {
        statusElement.textContent = statusText;
    }
}

function getStatusText(progress) {
    if (progress < 25) return ut('st_torrent', 'Uploading torrent file...');
    if (progress < 50) return ut('st_meta', 'Processing metadata...');
    if (progress < 75) return ut('st_screens', 'Uploading screenshots...');
    if (progress < 90) return ut('st_final', 'Finalizing...');
    return ut('st_almost', 'Almost done!');
}

// Обработчик отправки формы
document.getElementById("torrent-upload-form").addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validateForm()) {
        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
    }

    // При редактировании — не показываем модалку загрузки
    const isEditMode = document.querySelector('input[name="EditTorrent"]') !== null;
    if (!isEditMode && !uploadModalEl.classList.contains('show')) {
        uploadModal.show();
        startUploadTimer();
    }

    const form = document.getElementById("torrent-upload-form");
    const formData = new FormData(form);

    try {
        const response = await fetch("upload.php", { method: "POST", body: formData });
        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (e) {
            // Ответ не JSON — парсим HTML ошибку
            // Only the error itself: the alert text (+ the failed query for staff),
            // not the whole card with "Go Back / Home Page" buttons
            const tmp = document.createElement('div');
            tmp.innerHTML = rawText;
            tmp.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
            const alertEl = tmp.querySelector('.alert-danger');
            const codeEl  = tmp.querySelector('code');
            let errorText = alertEl
                ? alertEl.textContent + (codeEl ? '\n' + codeEl.textContent : '')
                : (tmp.querySelector('.card-body') || tmp).textContent;
            errorText = errorText
                .split('\n').map(l => l.replace(/\s+/g, ' ').trim()).filter(Boolean)
                .join('\n').slice(0, 1000);
            stopUploadTimer();
            uploadModal.hide();
            showErrorModal(ut('server_error', 'Server error:') + '\n' + errorText);
            return;
        }

        // ── Result: one place for upload and edit ─────────────────────────
        if (isEditMode) {
            // Edit: the upload modal is not shown, so the result is shown right away
            stopUploadTimer();
            showUploadResult(data, true);
            return;
        }

        // New upload: close the progress modal first, then show the result.
        // Fallback after 700 ms in case 'hidden.bs.modal' never fires; the flag
        // guarantees the result is shown only once.
        stopUploadTimer();
        let resultShown = false;
        const finish = () => {
            if (resultShown) return;
            resultShown = true;
            showUploadResult(data, false);
        };
        uploadModalEl.addEventListener('hidden.bs.modal', finish, { once: true });
        uploadModal.hide();
        setTimeout(() => {
            if (resultShown) return;
            forceCloseModal(uploadModalEl);
            finish();
        }, 700);

    } catch (error) {
        uploadModal.hide();
        stopUploadTimer();
        showErrorModal(ut('error_occurred', 'An error occurred: {1}', error.message));
    }
});

// ========== Upload result ==========

/** Error text from the server answer (error or errors[]). */
function uploadErrorText(data) {
    if (data && data.error) return data.error;
    if (data && Array.isArray(data.errors) && data.errors.length) return data.errors.join('\n');
    return ut('upload_failed', 'Upload failed (no details)');
}

/** Close a Bootstrap modal by hand (when its own events did not fire). */
function forceCloseModal(el) {
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    if (el) {
        el.classList.remove('show');
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

/**
 * What to show after a successful upload / edit:
 *  - hash_changed (the private flag was added, the .torrent changed) -> "download the torrent" modal, 10 s;
 *    the user must re-download it, otherwise the client has the old file
 *  - edit without a new file -> "changes saved", 3 s
 *  - new upload without changes -> the static #uploadCompleteModal from upload.php, 3 s
 */
function showUploadResult(data, isEdit) {
    if (!data || !data.success || !data.id) {
        showErrorModal(uploadErrorText(data));
        return;
    }

    if (data.hash_changed) {
        showRedirectModal({
            tone:    'primary',
            icon:    'fa-cloud-upload-alt',
            title:   ut('done_title', 'Upload completed'),
            text:    ut('done_private', 'Your torrent has been uploaded and updated with the private flag.'),
            seconds: 10,
            link:    data.link,
            buttons: `
                <a href="${escapeHtml(data.download)}" class="btn btn-primary me-2">
                    <i class="fa fa-download me-2"></i>${utH('download', 'Download torrent')}
                </a>
                <a href="${escapeHtml(data.link)}" class="btn btn-outline-primary">
                    <i class="fa fa-info-circle me-2"></i>${utH('view_details', 'View details')}
                </a>`
        });
        return;
    }

    if (isEdit) {
        showRedirectModal({
            tone:    'success',
            icon:    'fa-check-circle',
            title:   ut('saved_title', 'Changes saved'),
            heading: ut('success', 'Success!'),
            text:    ut('saved_text', 'Your torrent has been updated.'),
            seconds: 3,
            link:    data.link,
            buttons: `
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>${utH('stay', 'Stay here')}
                </button>
                <a href="${escapeHtml(data.link)}" class="btn btn-success">
                    <i class="fas fa-eye me-2"></i>${utH('view_torrent', 'View torrent')}
                </a>`
        });
        return;
    }

    // Plain new upload: static modal from upload.php
    const completeEl = document.getElementById('uploadCompleteModal');
    if (completeEl) new bootstrap.Modal(completeEl).show();
    setTimeout(() => { window.location.href = data.link; }, 3000);
}

/**
 * Modal with a countdown and redirect to opts.link after opts.seconds.
 * Closing the modal cancels the redirect ("stay here").
 * opts: tone, icon, title, heading (optional), text, seconds, link, buttons (HTML).
 */
function showRedirectModal(opts) {
    const old = document.getElementById('uploadSuccessModal');
    if (old) old.remove();

    const tone = opts.tone;
    document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-labelledby="uploadSuccessModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-${tone} text-white border-0">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas ${opts.icon} fa-2x me-3"></i>
                            <h5 class="modal-title mb-0" id="uploadSuccessModalLabel">${escapeHtml(opts.title)}</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="success-animation mb-3">
                            <i class="fas fa-check-circle text-${tone}" style="font-size: 3.5rem;"></i>
                        </div>
                        ${opts.heading ? `<h4 class="text-${tone} mb-2">${escapeHtml(opts.heading)}</h4>` : ''}
                        <p class="mb-2">${escapeHtml(opts.text)}</p>
                        <div class="redirect-timer mt-3">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <span class="text-muted">${utH('redirect_in', 'Redirecting in')}</span>
                                <span id="redirectCountdown" class="badge bg-${tone} fs-5 px-3 py-2 rounded-pill">${opts.seconds}</span>
                                <span class="text-muted">${utH('seconds', 'seconds')}</span>
                            </div>
                            <div class="progress mt-3 mx-auto" style="height: 4px; max-width: 200px;">
                                <div id="redirectProgress" class="progress-bar bg-${tone}" style="width: 0%; transition: width 1s linear;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-4">${opts.buttons}</div>
                </div>
            </div>
        </div>`);

    const el    = document.getElementById('uploadSuccessModal');
    const modal = new bootstrap.Modal(el);

    // The timer starts once the modal is fully shown
    el.addEventListener('shown.bs.modal', () => {
        const total     = opts.seconds;
        let   left      = total;
        const countdown = document.getElementById('redirectCountdown');
        const progress  = document.getElementById('redirectProgress');

        const iv = setInterval(() => {
            left--;
            if (countdown) {
                countdown.textContent = Math.max(0, left);
                countdown.style.transform = 'scale(1.1)';
                setTimeout(() => { countdown.style.transform = 'scale(1)'; }, 200);
            }
            if (progress) progress.style.width = ((total - left) / total * 100) + '%';
            if (left <= 0) clearInterval(iv);
        }, 1000);

        const redirect = setTimeout(() => { window.location.href = opts.link; }, total * 1000);

        // Closing the modal = stay on the page
        el.addEventListener('hidden.bs.modal', () => {
            clearInterval(iv);
            clearTimeout(redirect);
        }, { once: true });
    }, { once: true });

    modal.show();
}

// Счетчик символов для названия торрента
document.getElementById('formName').addEventListener('input', function() {
    const charCount = this.value.length;
    const counter = document.getElementById('formNameCharCount');
    
    if (counter) {
        counter.textContent = charCount;
        
        // Изменение цвета в зависимости от длины
        if (charCount > 200) {
            counter.classList.add('danger');
            counter.classList.remove('warning');
        } else if (charCount > 150) {
            counter.classList.add('warning');
            counter.classList.remove('danger');
        } else {
            counter.classList.remove('warning', 'danger');
        }
    }
});

// Валидация в реальном времени
document.getElementById('formName').addEventListener('blur', function() {
    this.classList.remove('is-invalid');
    
    if (this.value.length < 3) {
        this.classList.add('is-invalid');
        this.focus();
    }
});



function ShowHideField(fieldId) {
    var checkbox = document.querySelector('input[name="isnuked"]');
    var reasonField = document.getElementById(fieldId);
    
    // Toggle the visibility based on checkbox state
    if (checkbox.checked) {
        reasonField.style.display = '';
    } else {
        reasonField.style.display = 'none';
    }
}

// Call ShowHideField on page load to set initial state
window.onload = function() {
    ShowHideField('nukereason');
};

// Функция для удаления превью изображения
window.removeImagePreview = function(previewContainerId, urlInputId = null, fileInputId = null) {
    const previewContainer = document.getElementById(previewContainerId);
    if (previewContainer) {
        previewContainer.innerHTML = '';
    }
    
    // Очищаем соответствующие поля ввода
    if (urlInputId) {
        const urlInput = document.getElementById(urlInputId);
        if (urlInput) urlInput.value = '';
    }
    
    if (fileInputId) {
        const fileInput = document.getElementById(fileInputId);
        if (fileInput) fileInput.value = '';
    }
};

// Для совместимости со старым кодом
if (!window.removeImage) {
    window.removeImage = window.removeImagePreview;
}



// ====== TORRENT DRAG & DROP ======
document.addEventListener('DOMContentLoaded', function() {
    const dropZone   = document.getElementById('torrentDropZone');
    const fileInput  = document.getElementById('torrentFile');

    if (!dropZone || !fileInput) return;

   
   // Клик по зоне открывает диалог
dropZone.addEventListener('click', function(e) {
    if (e.target.closest('#torrentRemoveBtn')) return;
    if (e.target === fileInput) return; // предотвращаем двойной вызов
    e.stopPropagation();
    fileInput.click();
});

    // Выбор через диалог
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleTorrentFile(this.files[0]);
        }
    });

    // Drag over
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const file = e.dataTransfer.items[0];
        if (file && (file.type === 'application/x-bittorrent' || file.type === '')) {
            dropZone.classList.add('drag-over');
            dropZone.classList.remove('drag-invalid');
        } else {
            dropZone.classList.add('drag-invalid');
            dropZone.classList.remove('drag-over');
        }
    });

    // Drag leave
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('drag-over', 'drag-invalid');
    });

    // Drop
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('drag-over', 'drag-invalid');

        const files = e.dataTransfer.files;
        if (!files || files.length === 0) return;

        const file = files[0];

        // Проверяем расширение
        if (!file.name.toLowerCase().endsWith('.torrent')) {
            showTorrentDropError(ut('only_torrent', 'Only .torrent files are accepted!'));
            return;
        }

        // Присваиваем файл к input
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;

        handleTorrentFile(file);
    });

    // Drag enter — предотвращаем дефолтное открытие файла браузером
    document.addEventListener('dragover',  function(e) { e.preventDefault(); });
    document.addEventListener('drop',      function(e) { e.preventDefault(); });
});

function handleTorrentFile(file) {
    if (!file.name.toLowerCase().endsWith('.torrent')) {
        showTorrentDropError(ut('only_torrent', 'Only .torrent files are accepted!'));
        return;
    }

    // Same limit as the server (UPLOAD_MAX_TORRENT_MB in upload.php, printed as maxTorrentMb)
    const maxMb   = (typeof maxTorrentMb === 'number' && maxTorrentMb > 0) ? maxTorrentMb : 5;
    const maxSize = maxMb * 1024 * 1024;
    if (file.size > maxSize) {
        showTorrentDropError(ut('torrent_too_big', 'File is too large (max {1} MB).', maxMb));
        return;
    }

    // Убираем старое предупреждение о дубликате если есть
    var oldWarning = document.getElementById('torrentDuplicateWarning');
    if (oldWarning) oldWarning.remove();

    // Разблокируем кнопку
    var submitBtn = document.querySelector('#torrent-upload-form button[type="submit"]');
    if (submitBtn && submitBtn.dataset.blockedByDuplicate) {
        submitBtn.disabled = false;
        delete submitBtn.dataset.blockedByDuplicate;
    }

    // Показываем инфо о файле
    document.getElementById('torrentDropContent').style.display  = 'none';
    document.getElementById('torrentFileSelected').style.display = 'flex';
    document.getElementById('torrentSelectedName').textContent   = file.name;
    document.getElementById('torrentSelectedSize').textContent   = formatFileSize(file.size);

    document.getElementById('torrentDropZone').style.background  = '#f0fff4';
    document.getElementById('torrentDropZone').style.borderColor = '#198754';

    // Запускаем проверку дубликата + автозаполнение названия
    checkTorrentDuplicate(file);

    // Запускаем раннюю проверку passkey
    checkTorrentPasskey(file);
}

function removeTorrentFile() {
    const fileInput = document.getElementById('torrentFile');
    const dropZone  = document.getElementById('torrentDropZone');

    // Сбрасываем input
    fileInput.value = '';

    // Возвращаем исходное состояние
    document.getElementById('torrentDropContent').style.display  = 'block';
    document.getElementById('torrentFileSelected').style.display = 'none';
    dropZone.style.background  = '#f8f9ff';
    dropZone.style.borderColor = '#0d6efd';
}

function showTorrentDropError(message) {
    const dropZone = document.getElementById('torrentDropZone');
    dropZone.classList.add('drag-invalid');
    setTimeout(function() { dropZone.classList.remove('drag-invalid'); }, 2000);

    if (typeof showToast !== 'undefined') {
        showToast(message, 'danger');
    } else {
        alert(message);
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k     = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i     = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}





// ====== BENCODE PARSER (минимальный для info_hash) ======

function bencodeParseValue(data, offset) {
    const byte = data[offset];

    // Integer: i<number>e
    if (byte === 0x69) { // 'i'
        let end = offset + 1;
        while (data[end] !== 0x65) end++; // 'e'
        const num = parseInt(new TextDecoder().decode(data.slice(offset + 1, end)));
        return { value: num, offset: end + 1 };
    }

    // List: l...e
    if (byte === 0x6C) { // 'l'
        const list = [];
        offset++;
        while (data[offset] !== 0x65) { // 'e'
            const result = bencodeParseValue(data, offset);
            list.push(result.value);
            offset = result.offset;
        }
        return { value: list, offset: offset + 1 };
    }

    // Dict: d...e
    if (byte === 0x64) { // 'd'
        const dict = {};
        offset++;
        while (data[offset] !== 0x65) { // 'e'
            const keyResult = bencodeParseValue(data, offset);
            offset = keyResult.offset;
            const valResult = bencodeParseValue(data, offset);
            offset = valResult.offset;
            dict[keyResult.value] = valResult.value;
            // Сохраняем позиции для вычисления info_hash
            if (keyResult.value === 'info') {
                dict['__info_start'] = keyResult.offset;
                dict['__info_end']   = offset;
            }
        }
        return { value: dict, offset: offset + 1 };
    }

    // String: <length>:<data>
    const colonPos = data.indexOf(0x3A, offset); // ':'
    const length   = parseInt(new TextDecoder().decode(data.slice(offset, colonPos)));
    const strData  = data.slice(colonPos + 1, colonPos + 1 + length);
    return {
        value:  new TextDecoder('utf-8', { fatal: false }).decode(strData),
        raw:    strData,
        offset: colonPos + 1 + length
    };
}

// Вычисляем SHA1 info_hash
async function computeInfoHash(buffer) {
    try {
        const data = new Uint8Array(buffer);

        // Находим позицию "4:info"
        const infoKey = new TextEncoder().encode('4:info');
        let infoStart = -1;

        for (let i = 0; i < data.length - infoKey.length; i++) {
            let match = true;
            for (let j = 0; j < infoKey.length; j++) {
                if (data[i + j] !== infoKey[j]) { match = false; break; }
            }
            if (match) { infoStart = i + infoKey.length; break; }
        }

        if (infoStart === -1) return null;

        // Находим конец info dict
        const infoEnd = findBencodeEnd(data, infoStart);
        if (infoEnd === -1) return null;

        // Вычисляем SHA1
        const infoData  = data.slice(infoStart, infoEnd);
        const hashBuffer = await crypto.subtle.digest('SHA-1', infoData);
        const hashArray  = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

    } catch (e) {
        console.error('Error computing info_hash:', e);
        return null;
    }
}

function findBencodeEnd(data, start) {
    let depth = 0;
    let i = start;

    while (i < data.length) {
        const byte = data[i];

        if (byte === 0x64 || byte === 0x6C) { // 'd' or 'l'
            depth++;
            i++;
        } else if (byte === 0x65) { // 'e'
            depth--;
            i++;
            if (depth === 0) return i;
        } else if (byte === 0x69) { // 'i'
            while (data[i] !== 0x65) i++;
            i++; // skip 'e'
        } else if (byte >= 0x30 && byte <= 0x39) { // digit
            let colonPos = i;
            while (data[colonPos] !== 0x3A) colonPos++;
            const len = parseInt(new TextDecoder().decode(data.slice(i, colonPos)));
            i = colonPos + 1 + len;
        } else {
            i++;
        }
    }
    return -1;
}

// Получаем название торрента из .torrent файла
function getTorrentName(data) {
    try {
        const decoder = new TextDecoder('utf-8', { fatal: false });

        // Ищем "4:name" в байтах
        const nameKey = new TextEncoder().encode('4:name');
        for (let i = 0; i < data.length - nameKey.length; i++) {
            let match = true;
            for (let j = 0; j < nameKey.length; j++) {
                if (data[i + j] !== nameKey[j]) { match = false; break; }
            }
            if (match) {
                // Читаем строку после "4:name"
                let pos = i + nameKey.length;
                let colonPos = pos;
                while (data[colonPos] !== 0x3A) colonPos++;
                const len  = parseInt(decoder.decode(data.slice(pos, colonPos)));
                const name = decoder.decode(data.slice(colonPos + 1, colonPos + 1 + len));
                return name;
            }
        }
        return null;
    } catch (e) {
        return null;
    }
}

// ====== ОСНОВНАЯ ФУНКЦИЯ ПРОВЕРКИ ======
async function checkTorrentPasskey(file) {
    const submitBtn = document.querySelector('#torrent-upload-form button[type="submit"]');

    try {
        const formData = new FormData();
        formData.append('torrentFile', file);

        const response = await fetch('upload.php?action=check_passkey', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (!result.valid) {
            showTorrentDropError(result.error || ut('not_yours', 'This torrent does not belong to your account.'));

            // Блокируем отправку, как и при обнаружении дубликата
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.blockedByDuplicate = '1';
            }

            // Помечаем зону как невалидную
            var badge = document.querySelector('#torrentFileSelected .badge');
            if (badge) {
                badge.className = 'badge bg-danger mt-1';
                badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>' + escapeHtml(result.error || ut('bad_passkey', 'Invalid passkey'));
            }
            var dropZone = document.getElementById('torrentDropZone');
            if (dropZone) {
                dropZone.style.background  = '#fff5f5';
                dropZone.style.borderColor = '#dc3545';
            }
        }
    } catch (e) {
        console.error('Passkey check error:', e);
        // Сетевая ошибка — не блокируем, финальная отправка формы перепроверит на сервере
    }
}

async function checkTorrentDuplicate(file) {
    const dropZone   = document.getElementById('torrentDropZone');
    const progressEl = document.getElementById('torrentUploadProgress');

    if (progressEl) progressEl.style.display = 'block';

    try {
        const buffer   = await file.arrayBuffer();
        const data     = new Uint8Array(buffer);
        const infoHash = await computeInfoHash(buffer);

        if (progressEl) progressEl.style.display = 'none';

        if (!infoHash) {
            showToast(ut('no_hash', 'Could not read the torrent info_hash.'), 'warning');
            return;
        }

        // Автозаполняем название
        const nameInput = document.getElementById('formName');
        if (nameInput && !nameInput.value.trim()) {
            const torrentName = getTorrentName(data);
            if (torrentName) {
                nameInput.value = torrentName;
                var nameCountEl = document.getElementById('formNameCharCount');
                if (nameCountEl) nameCountEl.textContent = torrentName.length;
            }
        }

        // ← НОВОЕ: рендерим метаданные и список файлов
        var files = getTorrentFiles(data);
        renderTorrentMeta(files, infoHash);

        // Проверяем дубликат
        const response  = await fetch('upload.php?action=check_torrent_hash&info_hash=' + encodeURIComponent(infoHash));
        const data_json = await response.json();

        if (data_json.exists) {
            showTorrentDuplicateWarning(data_json);
        } else {
            var badge = document.querySelector('#torrentFileSelected .badge');
            if (badge) {
                badge.className = 'badge bg-success mt-1';
                badge.innerHTML = '<i class="fas fa-check me-1"></i>' + escapeHtml(ut('no_dupes', 'Ready to upload — no duplicates found'));
            }
        }

    } catch (e) {
        if (progressEl) progressEl.style.display = 'none';
        console.error('Duplicate check error:', e);
    }
}

function showTorrentDuplicateWarning(data) {
    // Меняем badge на предупреждение
    var badge = document.querySelector('#torrentFileSelected .badge');
    if (badge) {
        badge.className = 'badge bg-warning text-dark mt-1';
        badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + escapeHtml(ut('dupe', 'Duplicate detected!'));
    }

    // Меняем фон зоны
    var dropZone = document.getElementById('torrentDropZone');
    if (dropZone) {
        dropZone.style.background  = '#fffbf0';
        dropZone.style.borderColor = '#ffc107';
    }

    // Показываем предупреждение под зоной
    var existingWarning = document.getElementById('torrentDuplicateWarning');
    if (existingWarning) existingWarning.remove();

    var warning = document.createElement('div');
    warning.id  = 'torrentDuplicateWarning';
    warning.className = 'alert alert-warning d-flex align-items-start gap-3 mt-2';
    warning.innerHTML =
        '<i class="fas fa-exclamation-triangle fa-lg mt-1 text-warning"></i>' +
        '<div>' +
            '<strong>' + utH('dupe_title', 'Duplicate torrent detected!') + '</strong><br>' +
            utH('dupe_exists', 'A torrent with the same info_hash already exists:') + ' ' +
            '<a href="' + escapeHtml(data.link) + '" target="_blank" class="fw-bold">' + escapeHtml(data.name) + '</a>' +
            '<span class="text-muted small ms-2">' + utH('dupe_uploaded', 'uploaded {1}', data.added) + '</span>' +
            '<div class="mt-2">' +
                '<button type="button" class="btn btn-sm btn-outline-warning me-2" onclick="ignoreDuplicateWarning()">' +
                    '<i class="fas fa-upload me-1"></i>' + utH('dupe_upload_anyway', 'Upload anyway') +
                '</button>' +
                '<a href="' + escapeHtml(data.link) + '" target="_blank" class="btn btn-sm btn-outline-secondary">' +
                    '<i class="fas fa-eye me-1"></i>' + utH('dupe_view', 'View existing') +
                '</a>' +
            '</div>' +
        '</div>';

    var dropZoneParent = document.getElementById('torrentDropZone').parentNode;
    dropZoneParent.insertBefore(warning, document.getElementById('torrentUploadProgress').nextSibling);

    // Блокируем кнопку submit
    var submitBtn = document.querySelector('#torrent-upload-form button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.dataset.blockedByDuplicate = '1';
    }
}

function ignoreDuplicateWarning() {
    // Убираем предупреждение и разблокируем кнопку
    var warning = document.getElementById('torrentDuplicateWarning');
    if (warning) warning.remove();

    var submitBtn = document.querySelector('#torrent-upload-form button[type="submit"]');
    if (submitBtn && submitBtn.dataset.blockedByDuplicate) {
        submitBtn.disabled = false;
        delete submitBtn.dataset.blockedByDuplicate;
    }

    var badge = document.querySelector('#torrentFileSelected .badge');
    if (badge) {
        badge.className = 'badge bg-warning text-dark mt-1';
        badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + escapeHtml(ut('dupe_anyway', 'Duplicate — uploading anyway'));
    }

    showToast(ut('dupe_ignored', 'Duplicate warning ignored. You can now upload.'), 'warning');
}













// ====== BULK URL SCREENSHOTS ======
document.addEventListener('DOMContentLoaded', function() {
    var fileRadio = document.getElementById('screenshotByFile');
    var urlRadio  = document.getElementById('screenshotByUrl');

    if (fileRadio && urlRadio) {
        fileRadio.addEventListener('change', function() {
            document.getElementById('screenshotFileGroup').classList.remove('d-none');
            document.getElementById('screenshotUrlGroup').classList.add('d-none');
        });

        urlRadio.addEventListener('change', function() {
            document.getElementById('screenshotFileGroup').classList.add('d-none');
            document.getElementById('screenshotUrlGroup').classList.remove('d-none');
        });
    }
});

function loadScreenshotUrls() {
    var textarea    = document.getElementById('screenshotUrlsInput');
    var previewEl   = document.getElementById('screenshotUrlPreview');
    var inputsEl    = document.getElementById('screenshotUrlInputs');

    if (!textarea || !previewEl || !inputsEl) return;

    var lines = textarea.value.split('\n')
        .map(function(l) { return l.trim(); })
        .filter(function(l) { return l.length > 0; });

    if (lines.length === 0) {
        showToast(ut('enter_url', 'Please enter at least one link.'), 'warning');
        return;
    }

    // Валидация URL
    var validExtensions = /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i;
    var validUrls = [];
    var invalidUrls = [];

    lines.forEach(function(url) {
        try {
            new URL(url); // проверяем что это валидный URL
            if (validExtensions.test(url)) {
                validUrls.push(url);
            } else {
                invalidUrls.push(url);
            }
        } catch (e) {
            invalidUrls.push(url);
        }
    });

    if (invalidUrls.length > 0) {
        showToast(ut('skipped_urls', 'Skipped invalid links: {1}. Only jpg, png, gif, webp are supported.', invalidUrls.length), 'warning');
    }

    if (validUrls.length === 0) {
        showToast(ut('no_valid_urls', 'No valid image links found.'), 'danger');
        return;
    }

    // Очищаем старые превью и инпуты
    previewEl.innerHTML  = '';
    inputsEl.innerHTML   = '';

    var loaded   = 0;
    var failed   = 0;
    var total    = validUrls.length;

    // Показываем счётчик загрузки
    previewEl.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>' + escapeHtml(ut('loading_images', 'Loading {1} image(s)...', total)) + '</div>';

    var tempContainer = document.createElement('div');
    tempContainer.className = 'preview-container';

    validUrls.forEach(function(url, index) {
        var img = new Image();

        img.onload = function() {
            loaded++;

            // Создаём превью
            var item = document.createElement('div');
            item.className = 'screenshot-url-item position-relative d-inline-block me-2 mb-2';
            item.dataset.url = url;
            item.innerHTML =
                '<img src="' + url + '" class="preview-screenshot" alt="Screenshot ' + (index + 1) + '" style="width:120px;height:90px;object-fit:cover;border-radius:6px;">' +
                '<button type="button" class="delete-btn position-absolute top-0 end-0" onclick="removeScreenshotUrl(this, \'' + url.replace(/'/g, "\\'") + '\')" title="' + escapeHtml(ut('remove', 'Remove')) + '">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
                '<div class="text-center mt-1">' +
                    '<small class="text-muted" style="font-size:10px;">#' + (index + 1) + '</small>' +
                '</div>';

            tempContainer.appendChild(item);

            // Скрытый input для отправки на сервер
            var input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'screenshot_urls[]';
            input.value = url;
            input.dataset.url = url;
            inputsEl.appendChild(input);

            checkAllLoaded();
        };

        img.onerror = function() {
            failed++;
            checkAllLoaded();
        };

        img.src = url;
    });

    function checkAllLoaded() {
        if (loaded + failed === total) {
            previewEl.innerHTML = '';
            previewEl.appendChild(tempContainer);

           var msg = ut('loaded_shots', 'Loaded {1} screenshot(s)', loaded);
           if (failed > 0) msg += ', ' + ut('failed_shots', '{1} failed to load', failed);
           showToast(msg, failed > 0 ? 'warning' : 'success');

        }
    }
}

function removeScreenshotUrl(btn, url) {
    // Удаляем превью
    var item = btn.closest('.screenshot-url-item');
    if (item) item.remove();

    // Удаляем скрытый input
    var inputsEl = document.getElementById('screenshotUrlInputs');
    if (inputsEl) {
        var inputs = inputsEl.querySelectorAll('input[data-url]');
        inputs.forEach(function(input) {
            if (input.dataset.url === url) input.remove();
        });
    }
}




















// ====== ПАРСИНГ ФАЙЛОВ ИЗ TORRENT ======

function getTorrentFiles(data) {
    try {
        const decoder = new TextDecoder('utf-8', { fatal: false });

        // Ищем "5:files" — список файлов (multi-file torrent)
        const filesKey = new TextEncoder().encode('5:files');
        for (let i = 0; i < data.length - filesKey.length; i++) {
            let match = true;
            for (let j = 0; j < filesKey.length; j++) {
                if (data[i + j] !== filesKey[j]) { match = false; break; }
            }
            if (match) {
                // Нашли список файлов — парсим bencode list
                return parseBencodeFileList(data, i + filesKey.length, decoder);
            }
        }

        // Single-file torrent — берём только имя
        const name = getTorrentName(data);
        const length = getTorrentSingleLength(data);
        if (name) {
            return [{ path: name, size: length }];
        }

        return [];
    } catch (e) {
        console.error('Error parsing files:', e);
        return [];
    }
}

function getTorrentSingleLength(data) {
    try {
        const decoder = new TextDecoder('utf-8', { fatal: false });
        // Ищем "6:length" внутри info dict
        const key = new TextEncoder().encode('6:length');
        for (let i = 0; i < data.length - key.length; i++) {
            let match = true;
            for (let j = 0; j < key.length; j++) {
                if (data[i + j] !== key[j]) { match = false; break; }
            }
            if (match) {
                // После "6:length" идёт integer "i<num>e"
                let pos = i + key.length;
                if (data[pos] === 0x69) { // 'i'
                    pos++;
                    let numStart = pos;
                    while (data[pos] !== 0x65) pos++; // 'e'
                    return parseInt(decoder.decode(data.slice(numStart, pos)));
                }
            }
        }
        return 0;
    } catch (e) {
        return 0;
    }
}

function parseBencodeFileList(data, offset, decoder) {
    const files = [];

    // offset должен указывать на 'l' (list start)
    if (data[offset] !== 0x6C) return files; // 'l'
    offset++;

    while (data[offset] !== 0x65 && offset < data.length) { // 'e'
        // Каждый элемент — dict
        if (data[offset] !== 0x64) break; // 'd'
        offset++;

        let fileSize = 0;
        let filePath = '';

        while (data[offset] !== 0x65 && offset < data.length) { // 'e' конец dict
            // Читаем ключ
            let colonPos = offset;
            while (data[colonPos] !== 0x3A && colonPos < data.length) colonPos++;
            const keyLen  = parseInt(decoder.decode(data.slice(offset, colonPos)));
            offset        = colonPos + 1;
            const keyName = decoder.decode(data.slice(offset, offset + keyLen));
            offset       += keyLen;

            if (keyName === 'length') {
                // integer
                if (data[offset] === 0x69) {
                    offset++;
                    let numStart = offset;
                    while (data[offset] !== 0x65) offset++;
                    fileSize = parseInt(decoder.decode(data.slice(numStart, offset)));
                    offset++; // skip 'e'
                }
            } else if (keyName === 'path' || keyName === 'path.utf-8') {
                // list of strings
                if (data[offset] === 0x6C) {
                    offset++;
                    const parts = [];
                    while (data[offset] !== 0x65 && offset < data.length) {
                        let cPos = offset;
                        while (data[cPos] !== 0x3A && cPos < data.length) cPos++;
                        const pLen = parseInt(decoder.decode(data.slice(offset, cPos)));
                        offset = cPos + 1;
                        parts.push(decoder.decode(data.slice(offset, offset + pLen)));
                        offset += pLen;
                    }
                    offset++; // skip 'e'
                    filePath = parts.join('/');
                }
            } else {
                // Пропускаем значение
                offset = skipBencodeValue(data, offset);
            }
        }
        offset++; // skip 'e' конец dict

        if (filePath) {
            files.push({ path: filePath, size: fileSize });
        }
    }

    return files;
}

function skipBencodeValue(data, offset) {
    const decoder = new TextDecoder('utf-8', { fatal: false });
    const byte = data[offset];

    if (byte === 0x69) { // integer
        while (data[offset] !== 0x65) offset++;
        return offset + 1;
    }
    if (byte === 0x6C || byte === 0x64) { // list or dict
        offset++;
        while (data[offset] !== 0x65 && offset < data.length) {
            offset = skipBencodeValue(data, offset);
        }
        return offset + 1;
    }
    if (byte >= 0x30 && byte <= 0x39) { // string
        let colonPos = offset;
        while (data[colonPos] !== 0x3A) colonPos++;
        const len = parseInt(decoder.decode(data.slice(offset, colonPos)));
        return colonPos + 1 + len;
    }
    return offset + 1;
}

function getTorrentTotalSize(files) {
    return files.reduce(function(sum, f) { return sum + (f.size || 0); }, 0);
}

function toggleTorrentFileList() {
    var list     = document.getElementById('torrentFileList');
    var icon     = document.getElementById('torrentFileListToggleIcon');
    var text     = document.getElementById('torrentFileListToggleText');
    var isHidden = list.style.display === 'none';

    list.style.display = isHidden ? 'block' : 'none';
    icon.className     = isHidden ? 'fas fa-eye-slash me-1' : 'fas fa-eye me-1';
    text.textContent   = isHidden ? 'Hide' : 'Show';
}

function renderTorrentMeta(files, infoHash) {
    var metaEl = document.getElementById('torrentMetaInfo');
    if (!metaEl) return;

    var totalSize  = getTorrentTotalSize(files);
    var totalFiles = files.length;

    document.getElementById('torrentMetaSize').textContent  = formatFileSize(totalSize);
    document.getElementById('torrentMetaFiles').textContent = totalFiles;
    document.getElementById('torrentMetaHash').textContent  = infoHash ? infoHash.substring(0, 16) + '...' : '—';

    // Список файлов
    var listEl = document.getElementById('torrentFileList');
    if (listEl) {
        if (files.length === 0) {
            listEl.innerHTML = '<small class="text-muted">' + escapeHtml(ut('no_file_list', 'No file list available')) + '</small>';
        } else {
            var html = '<table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">' +
                '<thead><tr>' +
                    '<th class="text-muted fw-normal">#</th>' +
                    '<th class="text-muted fw-normal">' + utH('th_file', 'File') + '</th>' +
                    '<th class="text-muted fw-normal text-end">' + utH('th_size', 'Size') + '</th>' +
                '</tr></thead><tbody>';

            files.forEach(function(file, index) {
                var parts    = file.path.split('/');
                var filename = escapeHtml(parts[parts.length - 1]);
                var folder   = parts.length > 1 ? escapeHtml(parts.slice(0, -1).join('/') + '/') : '';

                html +=
                    '<tr>' +
                        '<td class="text-muted">' + (index + 1) + '</td>' +
                        '<td style="word-break:break-all;">' +
                            (folder ? '<span class="text-muted">' + folder + '</span>' : '') +
                            '<span class="fw-bold">' + filename + '</span>' +
                        '</td>' +
                        '<td class="text-end text-muted">' + formatFileSize(file.size) + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            listEl.innerHTML = html;
        }
    }

    metaEl.style.display = 'block';
}











// ====== СОРТИРОВКА СКРИНШОТОВ ======
document.addEventListener('DOMContentLoaded', function() {

    // Сортировка новых скриншотов (загруженных файлами)
    var screenshotsPreview = document.getElementById('screenshotsPreview');
    if (screenshotsPreview && typeof Sortable !== 'undefined') {
        Sortable.create(screenshotsPreview, {
            animation:    150,
            ghostClass:   'screenshot-drag-ghost',
            chosenClass:  'screenshot-drag-chosen',
            dragClass:    'screenshot-drag-active',
            handle:       'img',
            onEnd: function() {
                updateScreenshotOrder('screenshotsPreview');
            }
        });
    }

    // Сортировка существующих скриншотов (при редактировании)
    var existingScreenshots = document.getElementById('existingScreenshots');
    if (existingScreenshots && typeof Sortable !== 'undefined') {
        Sortable.create(existingScreenshots, {
            animation:    150,
            ghostClass:   'screenshot-drag-ghost',
            chosenClass:  'screenshot-drag-chosen',
            dragClass:    'screenshot-drag-active',
            handle:       'img',
            onEnd: function() {
                updateScreenshotOrder('existingScreenshots');
                saveExistingScreenshotOrder();
            }
        });
    }

    // Сортировка URL скриншотов
    var screenshotUrlPreview = document.getElementById('screenshotUrlPreview');
    if (screenshotUrlPreview && typeof Sortable !== 'undefined') {
        Sortable.create(screenshotUrlPreview, {
            animation:   150,
            ghostClass:  'screenshot-drag-ghost',
            chosenClass: 'screenshot-drag-chosen',
            handle:      'img',
            onEnd: function() {
                reorderScreenshotUrlInputs();
            }
        });
    }
});

// Обновляем порядковые номера после сортировки
function updateScreenshotOrder(containerId) {
    var container = document.getElementById(containerId);
    if (!container) return;

    var items = container.querySelectorAll('.screenshot-item, .screenshot-url-item, .preview-item');
    items.forEach(function(item, index) {
        var badge = item.querySelector('.screenshot-order-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'screenshot-order-badge position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75 m-1';
            badge.style.fontSize = '10px';
            item.style.position = 'relative';
            item.appendChild(badge);
        }
        badge.textContent = '#' + (index + 1);
    });
}

// Переупорядочиваем скрытые инпуты URL скриншотов после сортировки
function reorderScreenshotUrlInputs() {
    var previewEl = document.getElementById('screenshotUrlPreview');
    var inputsEl  = document.getElementById('screenshotUrlInputs');
    if (!previewEl || !inputsEl) return;

    // Собираем текущий порядок URL из превью
    var items = previewEl.querySelectorAll('.screenshot-url-item');
    var orderedUrls = [];
    items.forEach(function(item) {
        if (item.dataset.url) {
            orderedUrls.push(item.dataset.url);
        }
    });

    // Пересоздаём инпуты в новом порядке
    inputsEl.innerHTML = '';
    orderedUrls.forEach(function(url) {
        var input    = document.createElement('input');
        input.type   = 'hidden';
        input.name   = 'screenshot_urls[]';
        input.value  = url;
        input.dataset.url = url;
        inputsEl.appendChild(input);
    });

    // Обновляем номера
    updateScreenshotOrder('screenshotUrlPreview');
}

// Сохраняем порядок существующих скриншотов через AJAX
function saveExistingScreenshotOrder() {
    var container = document.getElementById('existingScreenshots');
    if (!container) return;

    var items = container.querySelectorAll('.screenshot-item');
    var order = [];
    items.forEach(function(item) {
        if (item.dataset.id) {
            order.push(item.dataset.id);
        }
    });

    if (order.length === 0) return;

    // Отправляем новый порядок на сервер
    var formData = new FormData();
    formData.append('action', 'reorder_screenshots');
    formData.append('my_post_key', typeof myPostKey !== 'undefined' ? myPostKey : '');
    order.forEach(function(id) {
        formData.append('order[]', id);
    });

    fetch(window.location.href, {
        method: 'POST',
        body:   formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(ut('order_saved', 'Screenshot order saved!'), 'success');
        }
    })
    .catch(function() {
        // Тихо игнорируем — порядок сохранится при следующем сохранении торрента
    });
}












// ====== IMDB AUTOFILL ======
var imdbData = null; // хранит последние данные IMDb

function fetchImdbData() {
    var url     = document.getElementById('imdbUrl').value.trim();
    var btn     = document.getElementById('imdbFetchBtn');
    var loading = document.getElementById('imdbLoading');
    var preview = document.getElementById('imdbPreview');
    var errorEl = document.getElementById('imdbError');

    if (!url) {
        showToast(ut('imdb_empty', 'Please enter an IMDb link first.'), 'warning');
        return;
    }

    if (!url.match(/https?:\/\/www\.imdb\.com\/title\/tt\d+/i)) {
        showToast(ut('imdb_invalid', 'Invalid IMDb link. Example: https://www.imdb.com/title/tt1234567/'), 'warning');
        return;
    }

    // UI — загрузка
    btn.disabled     = true;
    btn.innerHTML    = '<i class="fas fa-spinner fa-spin me-1"></i>' + escapeHtml(ut('fetching', 'Fetching...'));
    loading.style.display = 'flex';
    preview.style.display = 'none';
    errorEl.style.display = 'none';

    fetch('upload.php?action=get_imdb_data&imdb_url=' + encodeURIComponent(url))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loading.style.display = 'none';
            btn.disabled          = false;
            btn.innerHTML         = '<i class="fab fa-imdb me-1"></i>' + escapeHtml(ut('fetch', 'Fetch'));

            if (!data.success) {
                errorEl.style.display = 'block';
                document.getElementById('imdbErrorText').textContent = data.error || ut('imdb_failed', 'Could not get IMDb data');
                return;
            }

            imdbData = data;
            renderImdbPreview(data);
            showToast(ut('imdb_loaded', 'IMDb data loaded!'), 'success');
        })
        .catch(function(e) {
            loading.style.display = 'none';
            btn.disabled          = false;
            btn.innerHTML         = '<i class="fab fa-imdb me-1"></i>' + escapeHtml(ut('fetch', 'Fetch'));
            errorEl.style.display = 'block';
            document.getElementById('imdbErrorText').textContent = ut('network_error', 'Network error: {1}', e.message);
        });
}

function renderImdbPreview(data) {
    var preview = document.getElementById('imdbPreview');

    // Заголовок
    document.getElementById('imdbPreviewTitle').textContent = data.title || '—';

    // Год
    var yearEl = document.getElementById('imdbPreviewYear');
    if (data.year) {
        yearEl.textContent    = data.year;
        yearEl.style.display  = 'inline-block';
    }

    // Жанр
    var genreEl = document.getElementById('imdbPreviewGenre');
    if (data.genre) {
        genreEl.textContent   = data.genre;
        genreEl.style.display = 'inline-block';
    }

    // Рейтинг
    var ratingEl = document.getElementById('imdbPreviewRating');
    if (data.rating) {
        ratingEl.querySelector('span').textContent = data.rating + '/10';
        ratingEl.style.display = 'inline-block';
    }

    // Описание
    document.getElementById('imdbPreviewPlot').textContent = data.plot || '';

    // Постер
    if (data.poster) {
        var posterPreview = document.getElementById('imdbPosterPreview');
        var posterImg     = document.getElementById('imdbPosterImg');
        posterImg.src     = data.poster;
        posterImg.onload  = function() {
            posterPreview.style.display = 'block';
        };
        posterImg.onerror = function() {
            posterPreview.style.display = 'none';
        };
    }

    preview.style.display = 'block';
}

// Применяем постер к полю изображения
function applyImdbPoster(target) {
    if (!imdbData || !imdbData.poster) {
        showToast(ut('no_poster', 'No poster available.'), 'warning');
        return;
    }

    var poster = imdbData.poster;

    if (target === 'main') {
        var urlInput = document.getElementById('imageUrl');
        if (urlInput) {
            urlInput.value = poster;
            updateImagePreviewFromUrl(poster, 'imagePreview');
        }
        // Переключаем на URL режим
        var radioUrl = document.getElementById('uploadByUrl1');
        if (radioUrl) {
            radioUrl.checked = true;
            radioUrl.dispatchEvent(new Event('change'));
        }
        showToast(ut('poster_main', 'Poster set as the main image!'), 'success');
    } else {
        var urlInput2 = document.getElementById('imageUrl2');
        if (urlInput2) {
            urlInput2.value = poster;
            updateImagePreviewFromUrl(poster, 'imagePreview2');
        }
        var radioUrl2 = document.getElementById('uploadByUrl2');
        if (radioUrl2) {
            radioUrl2.checked = true;
            radioUrl2.dispatchEvent(new Event('change'));
        }
        showToast(ut('poster_2nd', 'Poster set as the secondary image!'), 'success');
    }
}

// Добавляем базовую инфу в описание
function applyImdbToDescription() {
    if (!imdbData) return;

    var textarea = document.getElementById('description');
    if (!textarea) return;

    var info = '';
    if (imdbData.title) info += '[b]' + imdbData.title + '[/b]';
    if (imdbData.year)  info += ' (' + imdbData.year + ')';
    info += '\n';
    if (imdbData.genre)  info += '[b]Genre:[/b] ' + imdbData.genre + '\n';
    if (imdbData.rating) info += '[b]IMDb Rating:[/b] ' + imdbData.rating + '/10\n';
    if (imdbData.plot)   info += '\n' + imdbData.plot + '\n';

    // Добавляем в начало если пустое, иначе в конец
    if (!textarea.value.trim()) {
        textarea.value = info;
    } else {
        textarea.value = textarea.value + '\n\n' + info;
    }

    showToast(ut('imdb_added', 'IMDb info added to the description!'), 'success');
}

// Автофетч при потере фокуса если URL валидный
document.addEventListener('DOMContentLoaded', function() {
    var imdbInput = document.getElementById('imdbUrl');
    if (imdbInput) {
        imdbInput.addEventListener('blur', function() {
            var url = this.value.trim();
            if (url && url.match(/https?:\/\/www\.imdb\.com\/title\/tt\d+/i)) {
                fetchImdbData();
            }
        });
    }
});




//////////////////////////////////////////////////////////////////////////////
// ====== NFO PREVIEW ======
document.addEventListener('DOMContentLoaded', function() {
    var nfoInput = document.getElementById('nfoFile');
    if (!nfoInput) return;

    nfoInput.addEventListener('change', function() {
        var file = this.files[0];
        var previewBtn = document.getElementById('nfoPreviewBtn');

        if (!file) {
            if (previewBtn) previewBtn.style.display = 'none';
            hideNfoPreview();
            return;
        }

        // Показываем кнопку Preview
        if (previewBtn) previewBtn.style.display = 'inline-block';

        // Читаем файл
        var reader = new FileReader();
        reader.onload = function(e) {
            var content = e.target.result;
            var lines   = content.split('\n');

            // Заполняем превью
            var previewText = document.getElementById('nfoPreviewText');
            var previewName = document.getElementById('nfoPreviewFilename');
            var previewSize = document.getElementById('nfoPreviewSize');
            var previewLines = document.getElementById('nfoPreviewLines');

            if (previewText)  previewText.textContent  = content;
            if (previewName)  previewName.textContent  = file.name;
            if (previewSize)  previewSize.textContent  = formatFileSize(file.size);
            if (previewLines) previewLines.textContent = lines.length + ' lines';
        };
        reader.readAsText(file, 'UTF-8');
    });
});

function toggleNfoPreview() {
    var container = document.getElementById('nfoPreviewContainer');
    if (!container) return;
    var isHidden = container.style.display === 'none';
    container.style.display = isHidden ? 'block' : 'none';

    var btn = document.getElementById('nfoPreviewBtn');
    if (btn) {
        btn.innerHTML = isHidden
            ? '<i class="fas fa-eye-slash me-1"></i>' + escapeHtml(ut('hide', 'Hide'))
            : '<i class="fas fa-eye me-1"></i>' + escapeHtml(ut('preview', 'Preview'));
    }
}

function hideNfoPreview() {
    var container = document.getElementById('nfoPreviewContainer');
    if (container) container.style.display = 'none';

    var btn = document.getElementById('nfoPreviewBtn');
    if (btn) {
        btn.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-eye me-1"></i>' + escapeHtml(ut('preview', 'Preview'));
    }
}