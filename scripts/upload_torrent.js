const BASEURL = "https://ruff-tracker.eu"; // change to your domain or root









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
                 class="preview-img" 
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
    
    // Инициализация удаления скриншотов
    let selectedContainer = null;
    let selectedScreenshotId = null;

    const modalEl = document.getElementById('deleteScreenshotModal');
    const confirmBtn = document.getElementById('confirmDeleteScreenshotBtn');
    if (modalEl && confirmBtn) {
        const modalInstance = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.delete-screenshot-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                selectedContainer = this.closest('.screenshot-item');
                selectedScreenshotId = selectedContainer.getAttribute('data-id');
                modalInstance.show();
            });
        });

        confirmBtn.addEventListener('click', function() {
            if (!selectedScreenshotId) return;

            fetch('upload.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_screenshot&screenshot_id=' + encodeURIComponent(selectedScreenshotId)
            })
            .then(response => response.json())
            .then(data => {
                const toastEl = document.getElementById('screenshotToast');
                const toastMsg = document.getElementById('toastMessage');
                const toast = new bootstrap.Toast(toastEl);

                if (data.success) {
                    selectedContainer.remove();
                    toastMsg.textContent = 'Screenshot deleted successfully!';
                    toastEl.classList.remove('text-bg-danger');
                    toastEl.classList.add('text-bg-success');
                    toast.show();
                } else {
                    toastMsg.textContent = 'Error: ' + data.error;
                    toastEl.classList.remove('text-bg-success');
                    toastEl.classList.add('text-bg-danger');
                    toast.show();
                }

                modalInstance.hide();
                selectedContainer = null;
                selectedScreenshotId = null;
            })
            .catch(err => {
                const toastEl = document.getElementById('screenshotToast');
                const toastMsg = document.getElementById('toastMessage');
                const toast = new bootstrap.Toast(toastEl);

                toastMsg.textContent = 'Request error!';
                toastEl.classList.remove('text-bg-success');
                toastEl.classList.add('text-bg-danger');
                toast.show();
                console.error(err);

                modalInstance.hide();
                selectedContainer = null;
                selectedScreenshotId = null;
            });
        });
    }
    
    // Обработчик скрытия модального окна загрузки
    uploadModalEl.addEventListener('hidden.bs.modal', function() {
        stopUploadTimer();
        updateUploadProgress(0, 'Ready to upload');
    });
});

// Preview images for Screenshots Upload
const screenshotsUpload = document.getElementById("screenshotsUpload");
const screenshotsPreview = document.getElementById("screenshotsPreview");

if (screenshotsUpload && screenshotsPreview) {
    screenshotsUpload.addEventListener("change", () => {
        screenshotsPreview.innerHTML = ""; // Clear preview

        Array.from(screenshotsUpload.files).forEach(file => {
            if (!file.type.startsWith("image/")) return;

            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement("img");
                img.src = e.target.result;
                img.classList.add("preview-img");
                screenshotsPreview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
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







// Единый обработчик для torrent file
document.getElementById('torrentFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fileInfo = document.getElementById('torrentFileInfo');
    const preview = document.getElementById('torrentFilePreview');
    
    // 1. Автозаполнение названия из имени файла
    const fileName = file?.name;
    if (fileName && !document.getElementById('formName').value) {
        const cleanName = fileName
            .replace(/\.torrent$/i, '')
            .replace(/[_-]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        
        const formattedName = cleanName.replace(/\b\w/g, c => c.toUpperCase());
        document.getElementById('formName').value = formattedName;
        
        const event = new Event('input');
        document.getElementById('formName').dispatchEvent(event);
    }
    
    // 2. Отображение информации о файле
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        fileInfo.textContent = `${file.name} (${fileSize} MB)`;
        fileInfo.classList.add('text-success');
        fileInfo.classList.remove('text-danger');
        
        preview.innerHTML = `
            <div class="alert alert-success d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div>
                    <strong>File selected:</strong> ${file.name}<br>
                    <small>Size: ${fileSize} MB • Type: Torrent file</small>
                </div>
            </div>
        `;
    } else {
        fileInfo.textContent = '';
        fileInfo.classList.remove('text-success', 'text-danger');
        preview.innerHTML = '';
    }
    
    // 3. Валидация файла
    this.classList.remove('is-invalid');
    
    if (file && !file.name.toLowerCase().endsWith('.torrent')) {
        this.classList.add('is-invalid');
        fileInfo.textContent = 'Please select a .torrent file';
        fileInfo.classList.remove('text-success');
        fileInfo.classList.add('text-danger');
        this.value = '';
        preview.innerHTML = '<div class="alert alert-danger">Please select a valid .torrent file</div>';
    }
});