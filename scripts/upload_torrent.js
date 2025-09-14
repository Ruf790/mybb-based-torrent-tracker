const BASEURL = "https://ruff-tracker.eu"; // change to your domain or root








function showErrorModal(message) {
  document.getElementById('errorModalBody').textContent = message;
  const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
  errorModal.show();
}




  
  
 // Toggle upload input groups
  function toggleUploadInputs() {
    const urlRadio = document.getElementById('uploadByUrl');
    const fileRadio = document.getElementById('uploadByFile');
    const urlGroup = document.getElementById('uploadUrlGroup');
    const fileGroup = document.getElementById('uploadFileGroup');

    if (urlRadio.checked) {
      urlGroup.classList.remove('d-none');
      fileGroup.classList.add('d-none');
    } else if (fileRadio.checked) {
      urlGroup.classList.add('d-none');
      fileGroup.classList.remove('d-none');
    }
  }

  // Initial toggle
  toggleUploadInputs();

  // Listen for radio changes
  document.getElementsByName('uploadType').forEach(radio => {
    radio.addEventListener('change', toggleUploadInputs);
  });

  // Live preview for URL input
  document.getElementById('imageUrl').addEventListener('input', function() {
    const url = this.value.trim();
    const preview = document.getElementById('image22Preview');
    preview.innerHTML = ''; // Clear previous previews

    if (url) {
      const img = document.createElement('img');
      img.src = url;
      img.style.maxWidth = '150px';
      img.style.maxHeight = '150px';
      img.style.objectFit = 'contain';
      img.alt = "Preview Image (URL)";
      img.onerror = () => {
        preview.innerHTML = '<p class="text-danger">Invalid image URL or cannot load image.</p>';
      };
      preview.appendChild(img);
    }
  });

  // Live preview for file upload
  document.getElementById('imagesUpload').addEventListener('change', function() {
    const files = this.files;
    const preview = document.getElementById('imagesPreview');
    preview.innerHTML = ''; // Clear previous previews

    if (files.length === 0) return;

    Array.from(files).forEach(file => {
      if (!file.type.startsWith('image/')) return; // skip non-images

      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '150px';
        img.style.maxHeight = '150px';
        img.style.objectFit = 'contain';
        img.alt = file.name;
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });
  
  
  
  
  
  
// Toggle upload input groups for Image 2
  function toggleUploadInputs2() {
    const urlRadio = document.getElementById('uploadByUrl2');
    const fileRadio = document.getElementById('uploadByFile2');
    const urlGroup = document.getElementById('uploadUrlGroup2');
    const fileGroup = document.getElementById('uploadFileGroup2');

    if (urlRadio.checked) {
      urlGroup.classList.remove('d-none');
      fileGroup.classList.add('d-none');
    } else if (fileRadio.checked) {
      urlGroup.classList.add('d-none');
      fileGroup.classList.remove('d-none');
    }
  }

  // Initial toggle for Image 2
  toggleUploadInputs2();

  // Listen for radio changes Image 2
  document.getElementsByName('uploadType2').forEach(radio => {
    radio.addEventListener('change', toggleUploadInputs2);
  });

  // Live preview for URL input Image 2
  document.getElementById('imageUrl2').addEventListener('input', function() {
    const url = this.value.trim();
    const preview = document.getElementById('image22Preview2');
    preview.innerHTML = ''; // Clear previous previews

    if (url) {
      const img = document.createElement('img');
      img.src = url;
      img.style.maxWidth = '150px';
      img.style.maxHeight = '150px';
      img.style.objectFit = 'contain';
      img.alt = "Preview Image 2 (URL)";
      img.onerror = () => {
        preview.innerHTML = '<p class="text-danger">Invalid image URL or cannot load image.</p>';
      };
      preview.appendChild(img);
    }
  });

  // Live preview for file upload Image 2
  document.getElementById('imagesUpload2').addEventListener('change', function() {
    const files = this.files;
    const preview = document.getElementById('imagesPreview2');
    preview.innerHTML = ''; // Clear previous previews

    if (files.length === 0) return;

    Array.from(files).forEach(file => {
      if (!file.type.startsWith('image/')) return; // skip non-images

      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '150px';
        img.style.maxHeight = '150px';
        img.style.objectFit = 'contain';
        img.alt = file.name;
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });
  
  
  
  
  
  
  
  
  
  
  
  

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

// Функция для показа модального окна ошибки (должна быть определена)
function showErrorModal22222(message) {
    console.error("Error modal:", message);
    // Реализация показа модального окна ошибки
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