// toast.js - полностью без jQuery
// Основная функция Toast
function showToast(message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const icon = getToastIcon(type);
    const title = getToastTitle(type);
    const bgClass = getToastBgClass(type);
    
    // Создаем элемент тоста
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = 'toast fade';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="toast-header ${bgClass} text-white border-0">
            <div class="d-flex align-items-center w-100">
                <div class="toast-icon me-2">
                    ${icon}
                </div>
                <strong class="me-auto">${title}</strong>
                <small class="text-white-50">${getCurrentTime()}</small>
                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div class="toast-body bg-light">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    ${message}
                </div>
            </div>
        </div>
    `;

    // Add progress bar
    const progressBar = document.createElement('div');
    progressBar.className = 'toast-progress';
    progressBar.style.cssText = 'height: 3px; background: rgba(255,255,255,0.3);';
    
    const progressBarInner = document.createElement('div');
    progressBarInner.className = 'toast-progress-bar';
    progressBarInner.style.cssText = 'width: 100%; height: 100%; background: rgba(255,255,255,0.7); transition: width 5s linear;';
    
    progressBar.appendChild(progressBarInner);
    
    // Вставляем progress bar после header
    const toastHeader = toast.querySelector('.toast-header');
    toast.insertBefore(progressBar, toastHeader.nextSibling);

    // Append to container
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        createToastContainer();
        toastContainer = document.getElementById('toastContainer');
    }
    
    if (toastContainer) {
        toastContainer.appendChild(toast);
    } else {
        console.error('Toast container not found');
        return;
    }

    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });

    // Start progress bar animation
    setTimeout(() => {
        progressBarInner.style.width = '0%';
    }, 100);

    // Remove from DOM after hide
    toast.addEventListener('hidden.bs.toast', function () {
        if (this.parentNode) {
            this.parentNode.removeChild(this);
        }
    });

    bsToast.show();

    return bsToast;
}

// Helper functions
function getToastIcon(type) {
    const icons = {
        success: '<i class="bi bi-check-circle-fill"></i>',
        error: '<i class="bi bi-exclamation-triangle-fill"></i>',
        warning: '<i class="bi bi-exclamation-circle-fill"></i>',
        info: '<i class="bi bi-info-circle-fill"></i>'
    };
    return icons[type] || icons.info;
}

function getToastTitle(type) {
    const titles = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Information'
    };
    return titles[type] || titles.info;
}

function getToastBgClass(type) {
    const classes = {
        success: 'bg-success',
        error: 'bg-danger',
        warning: 'bg-danger',
        info: 'bg-info'
    };
    return classes[type] || classes.info;
}

function getCurrentTime() {
    const now = new Date();
    return now.toLocaleTimeString('en-US', { 
        hour12: false, 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// Add CSS styles - ИСПРАВЛЕНО БЕЗ JQUERY
function addToastStyles() {
    if (!document.getElementById('toast-styles')) {
        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast {
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 10px;
            }
            .toast-icon {
                font-size: 1.1em;
            }
            .toast-header {
                padding: 12px 16px;
            }
            .toast-body {
                padding: 16px;
                font-size: 0.95em;
            }
            .toast-progress {
                position: relative;
            }
            .toast-progress-bar {
                border-radius: 0 0 0 4px;
            }
            .toast.fade {
                transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            }
            .toast.show {
                transform: translateX(0);
            }
            .toast-container {
                max-width: 350px;
            }
        `;
        document.head.appendChild(style);
    }
}

// Создаем контейнер для тостов - ИСПРАВЛЕНО БЕЗ JQUERY
function createToastContainer() {
    if (!document.getElementById('toastContainer')) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.cssText = 'z-index: 9999; margin-top: 70px;';
        
        // Проверяем что document.body существует
        if (document.body) {
            document.body.appendChild(container);
        } else {
            console.error('document.body not found');
        }
    }
}

// Универсальная инициализация
function initToastSystem() {
    addToastStyles();
    createToastContainer();
}

// Запускаем инициализацию когда DOM готов
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToastSystem);
} else {
    // DOM уже загружен
    initToastSystem();
}

// Экспорт для использования в других файлах
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { 
        showToast, 
        initToastSystem,
        getToastIcon,
        getToastTitle,
        getToastBgClass,
        getCurrentTime
    };
}
