// scripts/report.js
// Универсальная система репортов

class ReportSystem {
    constructor() {
        this.initializeModals();
        this.bindGlobalEvents();
    }
    
    initializeModals() {
        // Основная модалка репортов (для торрентов)
        this.initReportModal('reportModal', {
            formId: 'reportForm',
            submitBtnId: 'submitReport',
            reportedIdField: 'reportedId',
            typeField: 'reportType',
            useridField: 'reportUserid',
            infoTextId: 'reportingWhat',
            captchaDisplayId: 'captchaDisplay',
            captchaInputId: 'captchaInput',
            captchaRefreshId: 'refreshCaptcha',
            descriptionId: 'reportDescription',
            charCountId: 'charCount',
            reasonId: 'reportReason'
        });
        
        // Модалка репортов комментариев
        this.initReportModal('reportCommentModal', {
            formId: 'reportCommentForm',
            submitBtnId: 'submitCommentReport',
            reportedIdField: 'commentReportedId',
            typeField: 'commentReportType',
            useridField: 'commentReportedUserId',
            infoTextId: 'reportingComment',
            captchaDisplayId: 'commentCaptchaDisplay',
            captchaInputId: 'commentCaptchaInput',
            captchaRefreshId: 'commentRefreshCaptcha',
            descriptionId: 'commentReportDetails',
            charCountId: 'commentCharCount',
            reasonId: 'commentReportReason',
            isComment: true
        });
    }
    
    initReportModal(modalId, config) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Обработчик открытия модалки
        modal.addEventListener('show.bs.modal', (e) => {
            const button = e.relatedTarget;
            if (!button) return;
            
            this.handleModalOpen(modal, button, config);
        });
        
        // Настройка счетчика символов
        this.initCharCounter(config.descriptionId, config.charCountId);
        
        // Настройка CAPTCHA
        this.initCaptcha(config.captchaDisplayId, config.captchaRefreshId);
        
        // Обработчик отправки формы
        this.initFormSubmit(modal, config);
        
        // Сброс формы при закрытии
        modal.addEventListener('hidden.bs.modal', () => {
            this.resetModal(modal, config);
        });
    }
    
    handleModalOpen(modal, button, config) {
        const submitBtn = document.getElementById(config.submitBtnId);
        if (!submitBtn) return;
        
        // Получаем данные из data-атрибутов
        const data = this.getButtonData(button, config.isComment);
        
        console.log('Opening report modal with data:', data);
        
        // Заполняем скрытые поля
        this.fillHiddenFields(data, config);
        
        // Обновляем текст
        this.updateDisplayText(data, config);
        
        // Проверяем валидность
        const isValid = data.id && data.id !== '0';
        
        if (isValid) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-secondary');
            submitBtn.classList.add('btn-primary');
            
            // Показываем нормальный алерт
            const infoAlert = modal.querySelector('.alert-info');
            if (infoAlert) {
                infoAlert.className = 'alert alert-info mb-3';
                infoAlert.innerHTML = `<i class="bi bi-info-circle me-2"></i>${this.getInfoText(data, config.isComment)}`;
            }
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-secondary');
            
            // Показываем ошибку
            const infoAlert = modal.querySelector('.alert-info');
            if (infoAlert) {
                infoAlert.className = 'alert alert-danger mb-3';
                infoAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><strong>Error:</strong> Cannot identify item to report. Please use a valid report button.';
            }
        }
        
        // Генерируем CAPTCHA
        if (config.captchaDisplayId) {
            this.generateCaptcha(config.captchaDisplayId);
        }
    }
    
    getButtonData(button, isComment = false) {
        if (isComment) {
            return {
                type: 'comment',
                id: button.getAttribute('data-comment-id') || '',
                userId: button.getAttribute('data-comment-author-id') || '0',
                text: button.getAttribute('data-comment-text') || '',
                author: button.getAttribute('data-comment-author') || 'User',
                date: button.getAttribute('data-comment-date') || '',
                parentId: button.getAttribute('data-parent-id') || '',
                name: `Comment by ${button.getAttribute('data-comment-author') || 'User'}`
            };
        } else {
            return {
                type: button.getAttribute('data-report-type') || 'torrent',
                id: button.getAttribute('data-report-id') || '',
                userId: button.getAttribute('data-report-userid') || '0',
                name: button.getAttribute('data-report-name') || 'Item'
            };
        }
    }
    
    fillHiddenFields(data, config) {
        // ID репортируемого элемента
        const idField = document.getElementById(config.reportedIdField);
        if (idField) idField.value = data.id;
        
        // Тип
        const typeField = document.getElementById(config.typeField);
        if (typeField) typeField.value = data.type;
        
        // ID пользователя (автора)
        let userField = document.getElementById(config.useridField);
        if (!userField && config.useridField) {
            userField = document.createElement('input');
            userField.type = 'hidden';
            userField.name = 'reported_user_id';
            userField.id = config.useridField;
            const form = document.getElementById(config.formId);
            if (form) form.appendChild(userField);
        }
        if (userField) userField.value = data.userId;
        
        // Parent ID для комментариев
        if (config.isComment && data.parentId) {
            const parentField = document.getElementById('commentParentId');
            if (parentField) parentField.value = data.parentId;
        }
    }
    
    updateDisplayText(data, config) {
        const infoElement = document.getElementById(config.infoTextId);
        if (!infoElement) return;
        
        let text = `Reporting ${data.type.charAt(0).toUpperCase() + data.type.slice(1)}`;
        
        if (data.type === 'comment') {
            text = `Reporting Comment by ${data.author}`;
        } else if (data.name) {
            text += `: ${data.name}`;
        }
        
        if (data.id && data.id !== '0') {
            text += ` (ID: ${data.id})`;
        }
        
        infoElement.textContent = text;
        
        // Для комментариев обновляем предпросмотр
        if (config.isComment && data.type === 'comment') {
            this.updateCommentPreview(data);
        }
    }
    
    updateCommentPreview(data) {
        const previewText = document.getElementById('commentPreviewText');
        const authorPreview = document.getElementById('commentAuthorPreview');
        const datePreview = document.getElementById('commentDatePreview');
        
        if (previewText) {
            const displayText = data.text && data.text.length > 150 ? 
                data.text.substring(0, 147) + '...' : data.text || 'Comment text will appear here...';
            previewText.textContent = this.decodeHtmlEntities(displayText);
        }
        
        if (authorPreview) {
            authorPreview.textContent = data.author || 'User';
        }
        
        if (datePreview) {
            datePreview.textContent = data.date || '';
        }
    }
    
    getInfoText(data, isComment) {
        if (isComment) {
            return `Reporting comment by <strong>${this.escapeHtml(data.author)}</strong> (ID: ${this.escapeHtml(data.id)})`;
        }
        return `Reporting <strong>${this.escapeHtml(data.type)}</strong>: ${this.escapeHtml(data.name)} (ID: ${this.escapeHtml(data.id)})`;
    }
    
    initCharCounter(descriptionId, charCountId) {
        const descriptionField = document.getElementById(descriptionId);
        const charCountField = document.getElementById(charCountId);
        
        if (descriptionField && charCountField) {
            descriptionField.addEventListener('input', () => {
                charCountField.textContent = descriptionField.value.length + '/2000';
            });
        }
    }
    
    initCaptcha(displayId, refreshId) {
        const refreshBtn = document.getElementById(refreshId);
        const display = document.getElementById(displayId);
        if (refreshBtn && displayId) {
            refreshBtn.addEventListener('click', () => this.generateCaptcha(displayId));
        }
        if (display) {
            display.addEventListener('click', () => this.generateCaptcha(displayId));
        }
        if (displayId) {
            this.generateCaptcha(displayId);
        }
    }
    
    generateCaptcha(elementId) {
        // Капча теперь настоящая - код генерируется и хранится на сервере
        // (report_captcha.php), клиент только запрашивает новую картинку.
        const img = document.getElementById(elementId);
        if (img) {
            img.src = 'report_captcha.php?t=' + Date.now();
        }
    }
    
    initFormSubmit(modal, config) {
        const form = document.getElementById(config.formId);
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit(e, modal, config);
        });
    }
    
    handleFormSubmit(e, modal, config) {
        const form = document.getElementById(config.formId);
        if (!form) return;
        
        // Проверяем ID
        const idField = document.getElementById(config.reportedIdField);
        if (!idField || !idField.value || idField.value === '0') {
            this.showAlert(modal, 'Error: Cannot identify item to report.', 'danger');
            return false;
        }
        
        // Проверяем причину
        const reasonField = document.getElementById(config.reasonId);
        if (!reasonField || !reasonField.value) {
            this.showAlert(modal, 'Please select a reason for your report.', 'danger');
            reasonField?.focus();
            return false;
        }
        
        // Проверяем, что поле капчи заполнено (сам код проверяется на сервере -
        // клиент больше не знает "правильный" ответ, картинка и есть проверка).
        const captchaInput = document.getElementById(config.captchaInputId);
        
        if (captchaInput && !captchaInput.value.trim()) {
            this.showAlert(modal, 'Please enter the security code.', 'danger');
            captchaInput.focus();
            return false;
        }
        
        // Показываем индикатор загрузки
        const submitBtn = document.getElementById(config.submitBtnId);
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        submitBtn.disabled = true;
        
        // Таймаут
        const timeout = setTimeout(() => {
            if (submitBtn.disabled) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                this.showAlert(modal, 'Request timeout. Please try again.', 'warning');
            }
        }, 30000);
        
        // AJAX отправка
        const formData = new FormData(form);
        
        fetch('takereport.php', {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(response => response.text())
        .then(data => {
            clearTimeout(timeout);
            
            if (data.trim() === 'success' || data.includes('success') || data.includes('Location:')) {
                this.showAlert(modal, 'Report submitted successfully!', 'success');
                
                // Закрываем через 2 секунды
                setTimeout(() => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                }, 2000);
            } else {
                let serverMessage = data;
                try {
                    const parsed = JSON.parse(data);
                    serverMessage = parsed.error || (parsed.errors && parsed.errors[0]) || data;
                } catch (e) {
                    // ответ не JSON - используем как есть
                }
                throw new Error(serverMessage || 'Server error');
            }
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('Error:', error);
            this.showAlert(modal, error.message || 'Failed to submit report. Please try again.', 'danger');
            
            // Код капчи одноразовый и сервер уже аннулировал его при этой
            // попытке (вне зависимости от результата) - показываем новую картинку.
            if (config.captchaDisplayId) {
                this.generateCaptcha(config.captchaDisplayId);
            }
            const captchaInput = document.getElementById(config.captchaInputId);
            if (captchaInput) captchaInput.value = '';
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    showAlert(modal, message, type = 'info') {
        // Используем общие тосты (toast.js), как и остальные формы репортов на сайте.
        // toast.js понимает типы success/error/warning/info - 'danger' сюда не входит.
        const toastType = type === 'danger' ? 'error' : type;

        if (typeof showToast !== 'undefined') {
            showToast(this.escapeHtml(message), toastType);
            return;
        }

        // Fallback: если toast.js почему-то не подключен на странице - старое
        // поведение с алертом внутри самой модалки.
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show mb-3" role="alert">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle'} me-2"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            // Удаляем старые алерты
            modalBody.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)').forEach(alert => alert.remove());
            
            // Добавляем новый
            modalBody.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Автоудаление через 5 секунд
            if (type === 'success' || type === 'danger') {
                setTimeout(() => {
                    const alert = modalBody.querySelector(`.alert-${type}`);
                    if (alert) alert.remove();
                }, 5000);
            }
        }
    }
    
    resetModal(modal, config) {
        // Сбрасываем форму
        const form = document.getElementById(config.formId);
        if (form) form.reset();
        
        // Сбрасываем счетчик символов
        const charCount = document.getElementById(config.charCountId);
        if (charCount) charCount.textContent = '0/2000';
        
        // Сбрасываем информационный текст
        const infoText = document.getElementById(config.infoTextId);
        if (infoText) {
            infoText.textContent = config.isComment ? 'Comment' : 'Torrent';
        }
        
        // Сбрасываем предпросмотр комментария
        if (config.isComment) {
            const previewText = document.getElementById('commentPreviewText');
            const authorPreview = document.getElementById('commentAuthorPreview');
            const datePreview = document.getElementById('commentDatePreview');
            
            if (previewText) previewText.textContent = 'Comment text will appear here...';
            if (authorPreview) authorPreview.textContent = 'User';
            if (datePreview) datePreview.textContent = '';
        }
        
        // Сбрасываем кнопку
        const submitBtn = document.getElementById(config.submitBtnId);
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Report';
            submitBtn.disabled = false;
            submitBtn.classList.remove('btn-secondary');
            submitBtn.classList.add('btn-primary');
        }
        
        // Генерируем новую CAPTCHA
        if (config.captchaDisplayId) {
            this.generateCaptcha(config.captchaDisplayId);
        }
        
        // Очищаем алерты
        modal.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)').forEach(alert => alert.remove());
    }
    
    bindGlobalEvents() {
        // Глобальные обработчики
        document.addEventListener('click', (e) => {
            // Обработка кликов на кнопках репорта
            if (e.target.closest('.report-btn, .report-comment-btn')) {
                const btn = e.target.closest('.report-btn, .report-comment-btn');
                // Данные будут обработаны в show.bs.modal
            }
        });
    }
    
    // Вспомогательные функции
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    decodeHtmlEntities(text) {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    window.reportSystem = new ReportSystem();
    console.log('Report system initialized');
});