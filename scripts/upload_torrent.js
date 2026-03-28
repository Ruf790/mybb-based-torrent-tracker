const BASEURL = "https://ruff-tracker.eu"; // change to your domain or root

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
    titleElement.textContent = 'Delete Screenshot?';
  } else {
    console.warn('Element deleteScreenshotTitle not found');
  }
  
  if (filenameElement) {
    filenameElement.innerHTML = '<strong>"' + filename + '"</strong>';
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
        showToast('Error showing modal', 'error');
      }
    }
  } else {
    console.error('Modal element not found');
    if (typeof showToast === 'function') {
      showToast('Error: Modal not found', 'error');
    }
  }
}

// Обработчик для кнопки подтверждения удаления
const confirmBtn = document.getElementById('confirmDeleteScreenshotBtn');
if (confirmBtn) {
  confirmBtn.addEventListener('click', function() {
    if (!deleteId) {
      if (typeof showToast === 'function') {
        showToast('No screenshot selected for deletion', 'error');
      }
      return;
    }

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Deleting...';

    // Создаем FormData для отправки
    const formData = new FormData();
    formData.append('action', 'delete_screenshot');
    formData.append('screenshot_id', deleteId);

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
        
        // Показываем toast с успехом
        if (typeof showToast === 'function') {
          showToast('Screenshot deleted successfully!', 'success');
        } else {
          console.log('Screenshot deleted successfully!');
        }
        
        // Если после удаления не осталось скриншотов, показываем сообщение
        const remainingScreenshots = document.querySelectorAll('.screenshot-item').length;
        if (remainingScreenshots === 0) {
          const container = document.getElementById('existingScreenshots');
          if (container) {
            container.innerHTML = '<p class="text-muted text-center py-3">No screenshots yet</p>';
          }
        }
      } else {
        // Показываем toast с ошибкой
        if (typeof showToast === 'function') {
          showToast(data.error || 'Failed to delete screenshot.', 'error');
        } else {
          console.error('Failed to delete screenshot:', data.error);
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      
      if (typeof showToast === 'function') {
        showToast('An error occurred while deleting the screenshot.', 'error');
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

// Очищаем превью при закрытии модалки
const modalElement = document.getElementById('deleteScreenshotModal');
if (modalElement) {
  modalElement.addEventListener('hidden.bs.modal', function() {
    const previewImg = document.getElementById('deleteScreenshotImage');
    const filenameElement = document.getElementById('deleteScreenshotFilename');
    const noImageDiv = document.getElementById('noImagePreview');
    
    if (previewImg) previewImg.src = '';
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
      showToast('Error: Modal elements missing', 'error');
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
        showToast('Could not find screenshot item', 'error');
      }
      return;
    }
    
    // Получаем ID из data-атрибута
    const id = screenshotItem.dataset.id;
    if (!id) {
      console.error('No ID found for screenshot');
      if (typeof showToast === 'function') {
        showToast('Cannot delete: Missing screenshot ID', 'error');
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
            alert('Please select a valid image file (JPG, PNG, GIF, WebP)');
            input.value = '';
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) { // 10MB
            alert('Image size should be less than 10MB');
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
                 alt="Image preview">
            <button type="button" 
                    class="delete-btn" 
                    onclick="removeImage('${previewContainerId}', '${previewContainerId === 'imagePreview' ? 'imageUrl' : 'imageUrl2'}', '${previewContainerId === 'imagePreview' ? 'imagesUpload' : 'imagesUpload2'}')"
                    title="Remove image">
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
    previewContainer.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    const img = new Image();
    img.onload = function() {
        updateImagePreview(url, previewContainerId);
    };
    
    img.onerror = function() {
        previewContainer.innerHTML = `
            <div class="alert alert-warning p-2">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Cannot load image from URL
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
    document.getElementById('errorModalBody').textContent = message;
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    errorModal.show();
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
        updateUploadProgress(0, 'Ready to upload');
    });
});

// Preview images for Screenshots Upload
document.addEventListener('DOMContentLoaded', function() {
    const screenshotsUpload  = document.getElementById('screenshotsUpload');
    const screenshotsPreview = document.getElementById('screenshotsPreview');

    if (!screenshotsUpload || !screenshotsPreview) return;

    screenshotsUpload.addEventListener('change', function() {
        screenshotsPreview.innerHTML = '';

        Array.from(this.files).forEach(function(file, index) {
            if (!file.type.startsWith('image/')) return;

            var reader = new FileReader();
            reader.onload = function(e) {
                var item = document.createElement('div');
                item.className = 'screenshot-item';

                var img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-screenshot';
                img.alt = 'Screenshot ' + (index + 1);

                var badge = document.createElement('div');
                badge.className = 'screenshot-order-badge position-absolute bottom-0 start-0 badge bg-dark bg-opacity-75 m-1';
                badge.style.fontSize = '10px';
                badge.style.pointerEvents = 'none';
                badge.textContent = '#' + (index + 1);

                item.style.position = 'relative';
                item.appendChild(img);
                item.appendChild(badge);
                screenshotsPreview.appendChild(item);
            };
            reader.readAsDataURL(file);
        });
    });
});

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
    const minLength = 3;
    const maxLength = 255;

    if (!formNameValue || formNameValue.length < minLength || formNameValue.length > maxLength) {
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
    if (progress < 25) return 'Uploading torrent file...';
    if (progress < 50) return 'Processing metadata...';
    if (progress < 75) return 'Uploading screenshots...';
    if (progress < 90) return 'Finalizing...';
    return 'Almost done!';
}

// Обработчик отправки формы
document.getElementById("torrent-upload-form").addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validateForm()) {
        const firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
    }

    // Проверяем, не открыто ли уже модальное окно
    if (!uploadModalEl.classList.contains('show')) {
        uploadModal.show();
        startUploadTimer();
    }

    const form = document.getElementById("torrent-upload-form");
    const formData = new FormData(form);

    try {
        const response = await fetch("upload.php", { method: "POST", body: formData });
        const data = await response.json();

        // Ждём, пока модал скроется
        uploadModalEl.addEventListener("hidden.bs.modal", () => {
            if (data.success && data.id) {
                if (data.hash_changed) {
                    // Приватный торрент
                    const oldModal = document.getElementById("uploadSuccessModal");
                    if (oldModal) oldModal.remove();

                    const modalHtml = `
                        <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-labelledby="uploadSuccessModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="uploadSuccessModalLabel">Upload Completed</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <p>Your torrent has been successfully uploaded and updated with a private flag.</p>
                                        <p id="countdown-text" class="fw-bold">Redirecting in <span id="countdown">10</span> seconds...</p>
                                        <div class="progress" style="height: 20px;">
                                            <div id="progress-bar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="${data.download}" class="btn btn-primary"><i class="fa fa-download me-1"></i> Download Torrent</a>
                                        <a href="${data.link}" class="btn btn-outline-primary"><i class="fa fa-info-circle me-1"></i> View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    document.body.insertAdjacentHTML('beforeend', modalHtml);

                    const uploadSuccessModalEl = document.getElementById("uploadSuccessModal");
                    const uploadSuccessModal = new bootstrap.Modal(uploadSuccessModalEl);

                    uploadSuccessModalEl.addEventListener("shown.bs.modal", () => {
                        const progressBar = document.getElementById("progress-bar");
                        progressBar.style.transition = "width 10s linear";
                        requestAnimationFrame(() => {
                            progressBar.style.width = "100%";
                        });
                    });

                    uploadSuccessModal.show();

                    let secondsLeft = 10;
                    const countdownEl = document.getElementById("countdown");
                    const countdownInterval = setInterval(() => {
                        secondsLeft--;
                        countdownEl.textContent = secondsLeft;
                        if (secondsLeft <= 0) clearInterval(countdownInterval);
                    }, 1000);

                    setTimeout(() => {
                        window.location.href = data.link;
                    }, 10000);

                } else {
                    // Обычный торрент
                    const completeModalEl = new bootstrap.Modal(document.getElementById('uploadCompleteModal'));
                    completeModalEl.show();
                    setTimeout(() => {
                        window.location.href = data.link;
                    }, 3000);
                }
            } else {
                showErrorModal("Upload failed: " + (data.errors ? data.errors.join(", ") : "Missing ID."));
            }
        }, { once: true });

        // Сразу скрываем модал
        uploadModal.hide();
        stopUploadTimer();

    } catch (error) {
        uploadModal.hide();
        stopUploadTimer();
        showErrorModal("An error occurred: " + error.message);
    }
});

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

// Функция для показа модального окна ошибки
function showErrorModal22222(message) {
    console.error("Error modal:", message);
    alert("Error: " + message);
}

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
            showTorrentDropError('Only .torrent files are accepted!');
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
        showTorrentDropError('Only .torrent files are accepted!');
        return;
    }

    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        showTorrentDropError('File is too large (max 50MB).');
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
            showToast('Could not read torrent file info_hash.', 'warning');
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
                badge.innerHTML = '<i class="fas fa-check me-1"></i>Ready to upload — No duplicates found';
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
        badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Duplicate detected!';
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
            '<strong>Duplicate torrent detected!</strong><br>' +
            'A torrent with the same info_hash already exists: ' +
            '<a href="' + data.link + '" target="_blank" class="fw-bold">' + data.name + '</a>' +
            '<span class="text-muted small ms-2">uploaded ' + data.added + '</span>' +
            '<div class="mt-2">' +
                '<button type="button" class="btn btn-sm btn-outline-warning me-2" onclick="ignoreDuplicateWarning()">' +
                    '<i class="fas fa-upload me-1"></i>Upload Anyway' +
                '</button>' +
                '<a href="' + data.link + '" target="_blank" class="btn btn-sm btn-outline-secondary">' +
                    '<i class="fas fa-eye me-1"></i>View Existing' +
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
        badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Duplicate — uploading anyway';
    }

    showToast('Duplicate warning ignored. You can now upload.', 'warning');
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
        showToast('Please enter at least one URL.', 'warning');
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
        showToast('Skipped ' + invalidUrls.length + ' invalid URL(s). Only jpg, png, gif, webp supported.', 'warning');
    }

    if (validUrls.length === 0) {
        showToast('No valid image URLs found.', 'danger');
        return;
    }

    // Очищаем старые превью и инпуты
    previewEl.innerHTML  = '';
    inputsEl.innerHTML   = '';

    var loaded   = 0;
    var failed   = 0;
    var total    = validUrls.length;

    // Показываем счётчик загрузки
    previewEl.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading ' + total + ' image(s)...</div>';

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
                '<button type="button" class="delete-btn position-absolute top-0 end-0" onclick="removeScreenshotUrl(this, \'' + url.replace(/'/g, "\\'") + '\')" title="Remove">' +
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

            var msg = 'Loaded ' + loaded + ' screenshot(s)';
            if (failed > 0) msg += ', ' + failed + ' failed to load';
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
            listEl.innerHTML = '<small class="text-muted">No file list available</small>';
        } else {
            var html = '<table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">' +
                '<thead><tr>' +
                    '<th class="text-muted fw-normal">#</th>' +
                    '<th class="text-muted fw-normal">File</th>' +
                    '<th class="text-muted fw-normal text-end">Size</th>' +
                '</tr></thead><tbody>';

            files.forEach(function(file, index) {
                var parts    = file.path.split('/');
                var filename = parts[parts.length - 1];
                var folder   = parts.length > 1 ? parts.slice(0, -1).join('/') + '/' : '';

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
            showToast('Screenshot order saved!', 'success');
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
        showToast('Please enter an IMDb URL first.', 'warning');
        return;
    }

    if (!url.match(/https?:\/\/www\.imdb\.com\/title\/tt\d+/i)) {
        showToast('Invalid IMDb URL. Example: https://www.imdb.com/title/tt1234567/', 'warning');
        return;
    }

    // UI — загрузка
    btn.disabled     = true;
    btn.innerHTML    = '<i class="fas fa-spinner fa-spin me-1"></i>Fetching...';
    loading.style.display = 'flex';
    preview.style.display = 'none';
    errorEl.style.display = 'none';

    fetch('upload.php?action=get_imdb_data&imdb_url=' + encodeURIComponent(url))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loading.style.display = 'none';
            btn.disabled          = false;
            btn.innerHTML         = '<i class="fab fa-imdb me-1"></i>Fetch';

            if (!data.success) {
                errorEl.style.display = 'block';
                document.getElementById('imdbErrorText').textContent = data.error || 'Failed to fetch IMDb data';
                return;
            }

            imdbData = data;
            renderImdbPreview(data);
            showToast('IMDb data loaded successfully!', 'success');
        })
        .catch(function(e) {
            loading.style.display = 'none';
            btn.disabled          = false;
            btn.innerHTML         = '<i class="fab fa-imdb me-1"></i>Fetch';
            errorEl.style.display = 'block';
            document.getElementById('imdbErrorText').textContent = 'Network error: ' + e.message;
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
        showToast('No poster available.', 'warning');
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
        showToast('Poster set as Main Image!', 'success');
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
        showToast('Poster set as Secondary Image!', 'success');
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

    showToast('IMDb info added to description!', 'success');
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
            ? '<i class="fas fa-eye-slash me-1"></i>Hide'
            : '<i class="fas fa-eye me-1"></i>Preview';
    }
}

function hideNfoPreview() {
    var container = document.getElementById('nfoPreviewContainer');
    if (container) container.style.display = 'none';

    var btn = document.getElementById('nfoPreviewBtn');
    if (btn) {
        btn.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-eye me-1"></i>Preview';
    }
}