// forum report_post.js

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

// Обновляет картинку с кодом капчи (код проверяется на сервере, report_captcha.php)
function refreshForumPostCaptcha() {
    const img = document.getElementById('forumPostCaptchaDisplay');
    if (img) {
        img.src = 'report_captcha.php?t=' + Date.now();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Получаем элементы модалки
    const reportForumPostModal = document.getElementById('reportForumPostModal');
    if (!reportForumPostModal) return;
    
    // 2. Инициализируем Bootstrap модалку
    const modal = new bootstrap.Modal(reportForumPostModal);
    
    // 3. Обработчик открытия модалки
    reportForumPostModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Кнопка, по которой кликнули
        
        if (!button || !button.classList.contains('report-post-btn')) {
            return;
        }
        
        console.log('Opening report modal for forum post');
        
        // 4. Получаем данные из data-атрибутов
        const data = {
            postId: button.getAttribute('data-post-id') || '',
            userId: button.getAttribute('data-user-id') || '',
            postContent: button.getAttribute('data-post-content') || '',
            postAuthor: button.getAttribute('data-post-author') || '',
            threadTitle: button.getAttribute('data-thread-title') || '',
            threadId: button.getAttribute('data-thread-id') || '',
            forumId: button.getAttribute('data-forum-id') || '',
            forumName: button.getAttribute('data-forum-name') || '',
            postDate: button.getAttribute('data-post-date') || '',
            postSubject: button.getAttribute('data-post-subject') || ''
        };
        
        console.log('Post data:', data);
        
        // 5. Заполняем скрытые поля формы
        setFormValue('forumPostReportedId', data.postId);
        setFormValue('forumPostReportedUserId', data.userId);
        setFormValue('forumPostForumId', data.forumId);
        setFormValue('forumPostThreadId', data.threadId);
        
        // 6. Заполняем предпросмотр поста
        setPreviewText('forumPostAuthorPreview', data.postAuthor || 'User');
        setPreviewText('forumPostDatePreview', data.postDate ? `• ${data.postDate}` : '');
        setPreviewText('forumPostSubjectPreview', data.postSubject || data.threadTitle || 'Forum Post');
        setPreviewText('forumPostForumPreview', data.forumName || 'Forum');
        setPreviewText('forumPostThreadPreview', data.threadTitle || 'Thread');
        
        // 7. Обрабатываем текст поста (обрезаем если слишком длинный)
        let postContent = data.postContent || '';
        // Убираем лишние пробелы и переносы
        postContent = postContent.replace(/\s+/g, ' ').trim();
        // Обрезаем если слишком длинный
        if (postContent.length > 250) {
            postContent = postContent.substring(0, 250) + '...';
        }
        setPreviewText('forumPostPreviewText', postContent || 'Post content will appear here...');
        
        // 8. Сбрасываем форму к дефолтному состоянию
        resetReportForm();
        
        // 8b. Новый код капчи на каждое открытие модалки
        refreshForumPostCaptcha();
    });
    
    // 3b. Клик по картинке капчи тоже обновляет код
    const captchaImg = document.getElementById('forumPostCaptchaDisplay');
    if (captchaImg) {
        captchaImg.addEventListener('click', refreshForumPostCaptcha);
    }
    
    // 3c. Кнопка обновления капчи
    const captchaRefreshBtn = document.getElementById('forumPostRefreshCaptcha');
    if (captchaRefreshBtn) {
        captchaRefreshBtn.addEventListener('click', refreshForumPostCaptcha);
    }
    
    // 9. Счетчик символов для поля описания
    const detailsTextarea = document.getElementById('forumPostReportDetails');
    const charCountElement = document.getElementById('forumPostCharCount');
    
    if (detailsTextarea && charCountElement) {
        // Инициализируем счетчик
        updateCharCount(detailsTextarea, charCountElement);
        
        // Обновляем при вводе
        detailsTextarea.addEventListener('input', function() {
            updateCharCount(this, charCountElement);
        });
        
        // Обновляем при изменении через JS
        detailsTextarea.addEventListener('change', function() {
            updateCharCount(this, charCountElement);
        });
    }
    
    // 10. AJAX отправка формы
    const reportForm = document.getElementById('reportForumPostForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFormViaAjax(this, modal);
        });
    }
    
    // ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========
    
    // Установка значения в форму
    function setFormValue(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.value = value;
        } else {
            console.warn(`Element #${elementId} not found`);
        }
    }
    
    // Установка текста в предпросмотр
    function setPreviewText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
        }
    }
    
    // Обновление счетчика символов
    function updateCharCount(textarea, counter) {
        const length = textarea.value.length;
        counter.textContent = `${length}/2000`;
        
        // Меняем цвет в зависимости от длины
        counter.classList.remove('text-warning', 'text-danger');
        
        if (length > 1800 && length <= 1900) {
            counter.classList.add('text-warning');
        } else if (length > 1900) {
            counter.classList.add('text-danger');
        }
    }
    
    // Сброс формы к дефолтному состоянию
    function resetReportForm() {
        const form = document.getElementById('reportForumPostForm');
        if (!form) return;
        
        // Сбрасываем все поля кроме скрытых
        form.reset();
        
        // Сбрасываем счетчик символов
        if (charCountElement) {
            charCountElement.textContent = '0/2000';
            charCountElement.classList.remove('text-warning', 'text-danger');
        }
        
        // Сбрасываем выбранную причину на первый вариант
        const reasonSelect = document.getElementById('forumPostReportReason');
        if (reasonSelect) {
            reasonSelect.selectedIndex = 0;
        }
        
        // Сбрасываем правило нарушения
        const ruleSelect = document.getElementById('forumPostRuleViolation');
        if (ruleSelect) {
            ruleSelect.selectedIndex = 0;
        }
    }
    
    // AJAX отправка формы (опционально)
    function submitFormViaAjax(form, modalInstance) {
        const submitButton = form.querySelector('#submitForumPostReport');
        const originalButtonText = submitButton.innerHTML;
        
        const captchaInput = document.getElementById('forumPostCaptchaInput');
        if (captchaInput && !captchaInput.value.trim()) {
            if (typeof showToast !== 'undefined') {
                showToast('Please enter the security code.', 'warning');
            }
            captchaInput.focus();
            return;
        }
        
        // Показываем индикатор загрузки
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Submitting...';
        
        // Собираем данные формы
        const formData = new FormData(form);
        
        // Отправляем запрос
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            let data;
            try {
                data = await response.json();
            } catch (e) {
                throw new Error(`Server returned an unexpected response (status ${response.status})`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                // Показываем успешное сообщение
                if (typeof showToast !== 'undefined') {
                    showToast(escapeHtml(data.message || 'Report submitted successfully!'), 'success');
                } else {
                    alert('Report submitted successfully!');
                }
                
                // Закрываем модалку через 1.5 секунды
                setTimeout(() => {
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Если нужно перезагрузить страницу
                    if (data.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }, 1500);
            } else {
                // Показываем ошибку
                if (typeof showToast !== 'undefined') {
                    showToast(escapeHtml(data.error || 'Failed to submit report'), 'error');
                } else {
                    alert('Error: ' + (data.error || 'Failed to submit report'));
                }
                refreshForumPostCaptcha();
                if (captchaInput) captchaInput.value = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const shownMessage = error && error.message
                ? `Failed to submit report: ${error.message}`
                : 'Network error. Please try again.';
            if (typeof showToast !== 'undefined') {
                showToast(escapeHtml(shownMessage), 'error');
            } else {
                alert(shownMessage);
            }
            refreshForumPostCaptcha();
            if (captchaInput) captchaInput.value = '';
        })
        .finally(() => {
            // Восстанавливаем кнопку
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        });
    }
    
    // 11. Дополнительно: обработчик для кнопок вне dropdown (если есть)
    document.addEventListener('click', function(e) {
        const reportBtn = e.target.closest('.report-post-btn');
        if (reportBtn && !reportBtn.hasAttribute('data-bs-toggle')) {
            // Если кнопка без data-bs-toggle, открываем модалку вручную
            e.preventDefault();
            
            // Собираем данные
            const data = {
                postId: reportBtn.getAttribute('data-post-id') || '',
                userId: reportBtn.getAttribute('data-user-id') || '',
                postContent: reportBtn.getAttribute('data-post-content') || '',
                postAuthor: reportBtn.getAttribute('data-post-author') || '',
                threadTitle: reportBtn.getAttribute('data-thread-title') || '',
                threadId: reportBtn.getAttribute('data-thread-id') || '',
                forumId: reportBtn.getAttribute('data-forum-id') || '',
                forumName: reportBtn.getAttribute('data-forum-name') || '',
                postDate: reportBtn.getAttribute('data-post-date') || '',
                postSubject: reportBtn.getAttribute('data-post-subject') || ''
            };
            
            // Заполняем форму
            setFormValue('forumPostReportedId', data.postId);
            setFormValue('forumPostReportedUserId', data.userId);
            setFormValue('forumPostForumId', data.forumId);
            setFormValue('forumPostThreadId', data.threadId);
            
            setPreviewText('forumPostAuthorPreview', data.postAuthor || 'User');
            setPreviewText('forumPostDatePreview', data.postDate ? `• ${data.postDate}` : '');
            setPreviewText('forumPostSubjectPreview', data.postSubject || data.threadTitle || 'Forum Post');
            setPreviewText('forumPostForumPreview', data.forumName || 'Forum');
            setPreviewText('forumPostThreadPreview', data.threadTitle || 'Thread');
            
            let postContent = data.postContent || '';
            postContent = postContent.replace(/\s+/g, ' ').trim();
            if (postContent.length > 250) {
                postContent = postContent.substring(0, 250) + '...';
            }
            setPreviewText('forumPostPreviewText', postContent || 'Post content will appear here...');
            
            resetReportForm();
            refreshForumPostCaptcha();
            
            // Открываем модалку
            modal.show();
        }
    });
    
    console.log('Forum post report modal handler loaded');
});