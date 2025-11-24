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
            
            // Обрабатываем JSON строку со скриптом
            try {
                // Убираем экранирование и получаем чистый JavaScript код
                const cleanScript = responseText
                    .replace(/^"/, '') // Убираем начальную кавычку
                    .replace(/"$/, '') // Убираем конечную кавычку
                    .replace(/\\"/g, '"') // Убираем экранирование кавычек
                    .replace(/\\n/g, '\n') // Восстанавливаем переносы строк
                    .replace(/\\t/g, '\t') // Восстанавливаем табы
                    .replace(/\\\//g, '/'); // Восстанавливаем слеши

                console.log('Cleaned script:', cleanScript);

                // Извлекаем и выполняем JavaScript код
                if (cleanScript.includes('<script')) {
                    const scriptMatch = cleanScript.match(/<script[^>]*>([\s\S]*?)<\/script>/);
                    if (scriptMatch && scriptMatch[1]) {
                        const scriptContent = scriptMatch[1];
                        console.log('Executing script:', scriptContent);
                        
                        try {
                            new Function(scriptContent)();
                        } catch (scriptError) {
                            console.error('Error executing script:', scriptError);
                        }
                    }
                } else {
                    // Если это чистый JavaScript код (без тегов script)
                    console.log('Executing raw script');
                    try {
                        new Function(cleanScript)();
                    } catch (scriptError) {
                        console.error('Error executing raw script:', scriptError);
                    }
                }

            } catch (parseError) {
                console.error('Error parsing server response:', parseError);
            }
            
            // Закрываем модальное окно после выполнения скрипта
            const modal = bootstrap.Modal.getInstance(document.getElementById('dynamicModal'));
            if (modal) {
                modal.hide();
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