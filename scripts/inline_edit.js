

var inlineEditor = {
    init: function() {
        document.addEventListener('DOMContentLoaded', () => {
            this.bindSubjects();
        });
    },

    bindSubjects: function() {
        const editableElements = document.querySelectorAll('.subject_editable');
        
        editableElements.forEach(element => {
            const id = element.id;
            const tid = id.replace(/[^\d.]/g, '');
            let editTimeout;
            let isEditing = false;

            // Добавляем подсказку
            element.title = '(Click and hold to edit)';
            element.style.cursor = 'pointer';

            // Обработчик зажатия мыши
            element.addEventListener('mousedown', (e) => {
                if (isEditing) return;
                
                // Сохраняем оригинальный контент
                if (!document.getElementById('tid_' + tid + '_temp')) {
                    const temp = element.cloneNode(true);
                    temp.id = 'tid_' + tid + '_temp';
                    temp.style.display = 'none';
                    document.body.appendChild(temp);
                }

                editTimeout = setTimeout(() => {
                    this.startEditing(element, tid);
                    isEditing = true;
                }, 700);
            });

            // Отмена таймера при отпускании мыши
            element.addEventListener('mouseup', () => {
                clearTimeout(editTimeout);
            });

            element.addEventListener('mouseleave', () => {
                clearTimeout(editTimeout);
            });
        });
    },

    startEditing: function(element, tid) {
        const originalContent = element.innerHTML;
        const originalId = element.id;
        
        // Добавляем класс редактирования
        element.classList.add('editing');
        
        // Создаем input для редактирования
        const input = document.createElement('input');
        input.type = 'text';
        input.value = element.textContent.trim();
        input.className = 'subject-input';
        input.style.width = '98%';
        input.style.padding = '4px 8px';
        input.style.border = '2px solid #007bff';
        input.style.borderRadius = '4px';
        input.style.fontSize = 'inherit';
        input.style.fontFamily = 'inherit';
        
        // Заменяем контент на input
        element.innerHTML = '';
        element.appendChild(input);
        element.id = 'editing_' + tid;
        
        // Фокус и выделение текста
        input.focus();
        input.select();

        // Флаг чтобы избежать двойного выполнения
        let isSaving = false;

        // Обработчики для input
        const handleSave = () => {
            if (isSaving) return;
            isSaving = true;

            const newValue = input.value.trim();
            if (newValue && newValue !== element.textContent) {
                this.saveSubject(tid, newValue, element, originalContent, originalId);
            } else {
                this.cancelEditing(element, originalId, originalContent, tid);
            }
        };

        const handleCancel = () => {
            if (isSaving) return;
            this.cancelEditing(element, originalId, originalContent, tid);
        };

        // Используем once для blur чтобы избежать конфликтов
        input.addEventListener('blur', () => {
            setTimeout(() => {
                if (!isSaving) {
                    handleSave();
                }
            }, 150);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSave();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                handleCancel();
            }
        });
    },

    saveSubject: function(tid, newSubject, element, originalContent, originalId) {
        // Показываем спиннер
        element.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';
        
        // Отправляем запрос
        fetch('xmlhttp.php?action=edit_subject&my_post_key=' + my_post_key + '&tid=' + tid, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'value=' + encodeURIComponent(newSubject)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('SUCCESS: Subject updated successfully');
            
            if (typeof data === 'object') {
                if (data.errors) {
                    // Показываем ошибки через Toast
                    data.errors.forEach(message => {
                        showToast('Error: ' + message, 'error');
                    });
                    // Восстанавливаем оригинальный контент
                    this.restoreContent(element, originalId, originalContent, tid);
                } else {
                    // Обновляем заголовок
                    this.restoreContent(element, originalId, data.subject || newSubject, tid);
                    showToast('Subject updated successfully', 'success');
                }
            }
            
            document.getElementById('tid_' + tid + '_temp')?.remove();
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error updating subject', 'error');
            this.restoreContent(element, originalId, originalContent, tid);
        });
    },

    restoreContent: function(element, originalId, content, tid) {
        // Безопасное восстановление контента
        if (element && element.parentNode) {
            element.innerHTML = content;
            element.id = originalId;
            this.restoreElement(element, tid);
        }
    },

    cancelEditing: function(element, originalId, originalContent, tid) {
        this.restoreContent(element, originalId, originalContent, tid);
        document.getElementById('tid_' + tid + '_temp')?.remove();
    },

    restoreElement: function(element, tid) {
        // Убираем класс редактирования
        element.classList.remove('editing');
        element.style.cursor = 'pointer';
        element.title = '(Click and hold to edit)';
    }
};

// Добавляем CSS стили для inline editor
const inlineEditorStyles = `
    .subject_editable {
        transition: all 0.2s ease;
        cursor: pointer;
        padding: 2px 4px;
        border-radius: 3px;
    }
    
    .subject_editable:hover {
        background-color: #f8f9fa;
    }
    
    .subject_editable.editing {
        background-color: #e3f2fd;
        border: 1px dashed #007bff;
    }
    
    .subject-input {
        width: 98% !important;
        padding: 4px 8px;
        border: 2px solid #007bff !important;
        border-radius: 4px;
        font-size: inherit;
        font-family: inherit;
        background: white;
    }
    
    .subject-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    }
`;

// Добавляем стили в документ
if (!document.getElementById('inline-editor-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'inline-editor-styles';
    styleElement.textContent = inlineEditorStyles;
    document.head.appendChild(styleElement);
}

// Инициализация
inlineEditor.init();