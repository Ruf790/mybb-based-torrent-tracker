function popupWindow(url, options, root) {
    if (!options) options = {};
    if (root !== true) url = rootpath + url;

    // Fetch the modal HTML
    fetch(url)
        .then(response => response.text())
        .then(html => {
            // Remove existing modal
            const existingModal = document.getElementById('dynamicModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Append new modal to body
            document.body.insertAdjacentHTML('beforeend', html);

            setTimeout(() => {
                const modalElement = document.getElementById('dynamicModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement, { 
                        backdrop: 'static', 
                        keyboard: true 
                    });
                    
                    modal.show();

                    // Remove modal when hidden
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        const modalToRemove = document.getElementById('dynamicModal');
                        if (modalToRemove) {
                            modalToRemove.remove();
                        }
                    });

                    // Initialize tabs if function exists
                    if (typeof window.initTabs === 'function') {
                        window.initTabs(modalElement);
                    }

                    // Initialize Bootstrap tabs
                    const tabButtons = modalElement.querySelectorAll('#permissionTabs button[data-bs-toggle="tab"]');
                    tabButtons.forEach(button => {
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            const tab = new bootstrap.Tab(this);
                            tab.show();
                        });
                    });
                }
            }, 50);
        })
        .catch(error => {
            console.error('Failed to load modal:', error);
        });

    // Remove old form submit handlers and add new one
    document.removeEventListener('submit', handleModalFormSubmit);
    document.addEventListener('submit', handleModalFormSubmit);
}

// Separate function for form submission handling
function handleModalFormSubmit(e) {
    if (e.target.id === 'modal_form') {
        e.preventDefault();
        const form = e.target;

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.innerHTML;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        }

        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(responseText => {
            console.log('Server response:', responseText);
            
            try {
                // Проверяем, является ли ответ JSON строкой
                let cleanHtml = responseText;
                if (responseText.startsWith('"') && responseText.endsWith('"')) {
                    cleanHtml = JSON.parse(responseText);
                }
                
                // Очищаем HTML, но сохраняем обратные слеши
                cleanHtml = cleanHtml
                    .replace(/\\"/g, '"')
                    .replace(/\\n/g, '\n')
                    .replace(/\\t/g, '\t');
                
                console.log('Cleaned HTML:', cleanHtml);

                // Извлекаем скрипты
                const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
                let match;
                let scriptsExecuted = false;
                
                while ((match = scriptRegex.exec(cleanHtml)) !== null) {
                    scriptsExecuted = true;
                    let scriptContent = match[1];
                    
                    // Восстанавливаем экранированные кавычки в скрипте
                    scriptContent = scriptContent
                        .replace(/\\'/g, "'")
                        .replace(/\\"/g, '"')
                        .replace(/\\\\/g, '\\');
                    
                    console.log('Executing script via Function');
                    
                    try {
                        // Используем eval вместо new Function для лучшей обработки
                        eval(scriptContent);
                        console.log('Script executed via eval');
                    } catch (evalError) {
                        console.error('Error executing script via eval:', evalError);
                        
                        // Запасной вариант - script элемент
                        try {
                            const scriptEl = document.createElement('script');
                            scriptEl.textContent = scriptContent;
                            document.body.appendChild(scriptEl);
                            document.body.removeChild(scriptEl);
                            console.log('Script executed via script element');
                        } catch (elementError) {
                            console.error('Error executing script via element:', elementError);
                        }
                    }
                }
                
                // Если скриптов нет, пробуем выполнить как есть
                if (!scriptsExecuted && cleanHtml) {
                    console.log('No scripts found, attempting to execute as HTML');
                    const temp = document.createElement('div');
                    temp.innerHTML = cleanHtml;
                    
                    // Ищем строку для обновления
                    const newRow = temp.querySelector('[id^="row_"]');
                    if (newRow && newRow.id) {
                        const existingRow = document.getElementById(newRow.id);
                        if (existingRow) {
                            existingRow.outerHTML = newRow.outerHTML;
                        }
                    }
                }

            } catch (parseError) {
                console.error('Error parsing server response:', parseError);
            }
            
            // Закрываем модальное окно
            const modalElement = document.getElementById('dynamicModal');
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
            
            // Переинициализируем QuickPermEditor
            if (typeof QuickPermEditor !== 'undefined') {
                setTimeout(() => {
                    QuickPermEditor.initAll();
                    console.log('QuickPermEditor reinitialized');
                }, 200);
            }
            
            // Восстанавливаем кнопку
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            alert('Failed to save. Error: ' + error.message);
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
}