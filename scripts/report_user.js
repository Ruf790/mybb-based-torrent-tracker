document.addEventListener('DOMContentLoaded', function() {
    const reportForm = document.getElementById('reportUserForm');
    const submitBtn = reportForm.querySelector('button[type="submit"]');
    
    reportForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Валидация перед отправкой
        if (!validateReportForm()) {
            return;
        }
        
        // Показать индикатор загрузки
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Submitting...';
        submitBtn.disabled = true;
        
        // AJAX отправка
        const formData = new FormData(reportForm);
        
        fetch(reportForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Успешно - закрыть модальное окно и показать сообщение
                const modal = bootstrap.Modal.getInstance(document.getElementById('reportUserModal'));
                modal.hide();
                
                showToast(
                    '<i class="fa-solid fa-check-circle me-2"></i>' + (data.message || 'Report submitted successfully'),
                    'success'
                );
                
                // Очистить форму
                reportForm.reset();
                resetCharCounter();
                
                // Обновить счетчик символов
                updateCharCounter();
                
                // Перенаправить если указано в ответе
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                }
            } else {
                // Ошибка от сервера
                let errorMessage = 'Failed to submit report';
                
                if (data.error) {
                    errorMessage = data.error;
                } else if (data.errors && data.errors.length > 0) {
                    errorMessage = data.errors.join('<br>');
                }
                
                showToast(
                    '<i class="fa-solid fa-exclamation-circle me-2"></i>' + errorMessage,
                    'danger'
                );
                
                // Выделить поля с ошибками
                highlightErrors(data.errors);
            }
        })
        .catch(error => {
            console.error('Report submission error:', error);
            
            let errorMessage = 'Network error, please try again';
            
            // Проверяем если это JSON ошибка
            if (error.message.includes('JSON')) {
                errorMessage = 'Invalid server response. Please try again.';
            }
            
            showToast(
                '<i class="fa-solid fa-times-circle me-2"></i>' + errorMessage,
                'danger'
            );
        })
        .finally(() => {
            // Восстановить кнопку
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
    });
    
    // Валидация формы
    function validateReportForm() {
        const reasonSelected = reportForm.querySelector('input[name="reason"]:checked');
        const description = reportForm.querySelector('#reportDescription').value.trim();
      
        
        // Проверка причины
        if (!reasonSelected) {
            showToast('Please select a report reason', 'warning');
            highlightField('reason');
            return false;
        }
        
        // Проверка описания
        if (description.length < 10) {
            showToast('Please provide a detailed description (minimum 10 characters)', 'warning');
            highlightField('description');
            return false;
        }
        
        if (description.length > 2000) {
            showToast('Description is too long (maximum 2000 characters)', 'warning');
            highlightField('description');
            return false;
        }
        
    
        
        return true;
    }
    
    // Выделение поля с ошибкой
    function highlightField(fieldName) {
        const field = document.querySelector(`[name="${fieldName}"]`) || 
                      document.querySelector(`#${fieldName}`);
        
        if (field) {
            field.classList.add('is-invalid');
            field.focus();
            
            // Убрать класс через 3 секунды
            setTimeout(() => {
                field.classList.remove('is-invalid');
            }, 3000);
        }
    }
    
    // Подсветка ошибок из массива
    function highlightErrors(errors) {
        if (!Array.isArray(errors)) return;
        
        errors.forEach(error => {
            if (error.includes('reason')) highlightField('reason');
            if (error.includes('description')) highlightField('description');
            if (error.includes('captcha')) highlightField('captcha');
        });
    }
    
    // Счетчик символов для описания
    const descriptionField = document.getElementById('reportDescription');
    const charCount = document.createElement('div');
    charCount.className = 'form-text text-end';
    charCount.id = 'charCount';
    charCount.innerHTML = '0/2000 characters';
    descriptionField.parentNode.appendChild(charCount);
    
    function updateCharCounter() {
        const length = descriptionField.value.length;
        charCount.textContent = `${length}/2000 characters`;
        
        if (length < 10) {
            charCount.className = 'form-text text-end text-danger';
        } else if (length < 100) {
            charCount.className = 'form-text text-end text-warning';
        } else if (length > 1900) {
            charCount.className = 'form-text text-end text-danger';
        } else {
            charCount.className = 'form-text text-end text-success';
        }
    }
    
    function resetCharCounter() {
        charCount.textContent = '0/2000 characters';
        charCount.className = 'form-text text-end text-danger';
    }
    
    descriptionField.addEventListener('input', updateCharCounter);
    updateCharCounter(); // Инициализация
    
    // Автозаполнение описания при выборе причины
    const reasonRadios = document.querySelectorAll('input[name="reason"]');
    reasonRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const descriptionField = document.getElementById('reportDescription');
            if (descriptionField.value.trim() === '') {
                const reasonText = this.parentNode.querySelector('.fw-bold').textContent;
                const reasonDesc = this.parentNode.querySelector('.text-muted').textContent;
                
                descriptionField.value = 
                    `I am reporting this user for: ${reasonText}\n\n` +
                    `Reason: ${reasonDesc}\n\n` +
                    `Additional details:\n` +
                    `- Date/Time: ${new Date().toLocaleString()}\n` +
                    `- Reported User: ${document.getElementById('reportUserModalLabel').textContent.replace('Report User: ', '')}\n` +
                    `- Evidence:\n`;
                
                descriptionField.dispatchEvent(new Event('input'));
                descriptionField.focus();
            }
        });
    });
    

    
    // Очистка формы при закрытии модального окна
    const reportModal = document.getElementById('reportUserModal');
    if (reportModal) {
        reportModal.addEventListener('hidden.bs.modal', function () {
            reportForm.reset();
            resetCharCounter();
            
            // Сбросить выбор причины
            const selectedReason = reportForm.querySelector('input[name="reason"]:checked');
            if (selectedReason) {
                selectedReason.checked = false;
            }
            
            // Убрать классы ошибок
            document.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
        });
    }
});

// Функция для открытия модального окна
function openReportUserModal(userId, username) {
    const modal = new bootstrap.Modal(document.getElementById('reportUserModal'));
    
    // Обновить данные в форме
    document.querySelector('input[name="reported_id"]').value = userId;
    document.querySelector('input[name="reported_user_id"]').value = userId;
    
    // Обновить заголовок
    const modalTitle = document.getElementById('reportUserModalLabel');
    if (modalTitle) {
        modalTitle.innerHTML = 
            '<i class="fa-solid fa-flag me-2"></i>Report User: ' + 
            username.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    // Показать модальное окно
    modal.show();
    
    // Сфокусироваться на первом поле после открытия
    setTimeout(() => {
        const firstReason = document.querySelector('input[name="reason"]');
        if (firstReason) {
            firstReason.focus();
        }
    }, 500);
}