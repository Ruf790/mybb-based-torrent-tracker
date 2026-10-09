// deleteForum.js - оптимизированная версия

/**
 * Строка из AGS_LANG (ключи js_* из ланга без префикса) с английским fallback и подстановкой {1}, {2}…
 * Имя не t(): файл не обёрнут в IIFE, а глобальный t() есть и в других скриптах.
 */
function fmDelT(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
    let str = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
    // {1} — формат лангов; %1$s — если загрузчик лангов переделал плейсхолдеры под sprintf
    args.forEach((a, i) => {
        str = str.split('{' + (i + 1) + '}').join(String(a)).split('%' + (i + 1) + '$s').join(String(a));
    });
    return str;
}

/** Имя форума из data-атрибута: там HTML (MyBB хранит имена с разметкой/сущностями) → чистый текст */
function fmDelPlainName(html) {
    if (!html) return '';
    return new DOMParser().parseFromString(html, 'text/html').documentElement.textContent.trim();
}
document.addEventListener('DOMContentLoaded', function() {  
    // Используем делегирование событий для динамически добавленных элементов
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete_employee');
        if (deleteBtn) {
            e.preventDefault();   
            const empid = deleteBtn.getAttribute('data-emp-id');
            const forumName = fmDelPlainName(deleteBtn.getAttribute('data-forum-name'));
            const parent = deleteBtn.closest(".tr") || deleteBtn.closest(".forum-row");
            
            // Создаем кастомное модальное окно вместо bootbox
            showDeleteConfirmation(empid, forumName, parent);
        }
    });
});





function showDeleteConfirmation(empid, forumName, parentRow) {
    // Определяем forumName с значением по умолчанию
    forumName = forumName || fmDelT('del_this_forum', 'this forum');

    // Тексты в разметке — английский fallback; перевод ставится ниже через textContent (data-fm-t="ключ")
    
    const modalHTML = `
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Заголовок с градиентом -->
            <div class="modal-header bg-danger bg-gradient text-white border-0 rounded-top">
                <div class="d-flex align-items-center">
                    <div class="modal-icon bg-white bg-opacity-25 rounded-circle p-2 me-3">
                        <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold">
                            <i class="fa-solid fa-trash-can me-2"></i><span data-fm-t="del_title">Delete Forum</span>
                        </h5>
                        <p class="mb-0 small opacity-75" data-fm-t="del_subtitle">Critical Action Required</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" data-fm-aria="close" aria-label="Close"></button>
            </div>
            
            <!-- Тело модального окна -->
            <div class="modal-body py-4">
                <div class="text-center mb-4">
                    <div class="delete-icon-wrapper mb-3">
                        <i class="fa-solid fa-trash-can text-danger fa-3x"></i>
                        <div class="pulse-ring"></div>
                    </div>
                    
                    <h4 class="fw-bold text-dark mb-3" data-fm-t="del_confirm">Confirm Deletion</h4>
                    
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25" role="alert">
                        <div class="d-flex">
                            <i class="fa-solid fa-circle-exclamation text-danger mt-1 me-3"></i>
                            <div>
                                <p class="mb-1 fw-semibold" data-fm-t="del_about">You are about to delete:</p>
                                <h6 class="mb-0 text-danger">
                                    <i class="fa-solid fa-folder-open me-2"></i><span id="deleteForumName"></span>
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Детали удаления -->
                <div class="border-top border-bottom py-3 my-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fa-solid fa-message text-muted fa-lg mb-2"></i>
                                <p class="mb-1 small" data-fm-t="del_threads">Threads</p>
                                <p class="mb-0 fw-bold" data-fm-t="del_all">All</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fa-solid fa-comment text-muted fa-lg mb-2"></i>
                                <p class="mb-1 small" data-fm-t="del_posts">Posts</p>
                                <p class="mb-0 fw-bold" data-fm-t="del_all">All</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Предупреждение -->
                <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25">
                    <div class="d-flex">
                        <i class="fa-solid fa-triangle-exclamation text-warning mt-1 me-3"></i>
                        <div>
                            <p class="mb-1 fw-semibold" data-fm-t="del_warn_title">This action cannot be undone!</p>
                            <p class="mb-0 small" data-fm-t="del_warn_text">All content in this forum will be permanently deleted.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Подтверждение -->
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="confirmDeleteCheckbox">
                    <label class="form-check-label small" for="confirmDeleteCheckbox" data-fm-t="del_checkbox">I understand this action is permanent and cannot be reversed</label>
                </div>
            </div>
            
            <!-- Футер модального окна -->
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-2"></i><span data-fm-t="cancel">Cancel</span>
                </button>
                <button type="button" class="btn btn-danger px-4 fw-semibold" id="confirmDeleteBtn" disabled>
                    <i class="fa-solid fa-trash-can me-2"></i>
                    <span data-fm-t="del_button">Delete Forum</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" id="deleteSpinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>`;

    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modalElement = document.getElementById('deleteConfirmModal');

    // Переводы и имя форума — только как текст
    modalElement.querySelectorAll('[data-fm-t]').forEach(el => {
        el.textContent = fmDelT(el.dataset.fmT, el.textContent.trim());
    });
    modalElement.querySelectorAll('[data-fm-aria]').forEach(el => {
        el.setAttribute('aria-label', fmDelT(el.dataset.fmAria, el.getAttribute('aria-label') || ''));
    });
    const nameEl = modalElement.querySelector('#deleteForumName');
    if (nameEl) nameEl.textContent = fmDelT('del_quoted', '"{1}"', forumName);

    const modal = new bootstrap.Modal(modalElement);
    
    // Показываем модальное окно
    modal.show();

    // Получаем элементы после добавления в DOM
    const confirmCheckbox = document.getElementById('confirmDeleteCheckbox');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const deleteSpinner = document.getElementById('deleteSpinner');

    // Включаем кнопку при согласии
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', function() {
            confirmBtn.disabled = !this.checked;
        });
    }

    // Обработчик подтверждения удаления
    confirmBtn.addEventListener('click', function() {
        // Показываем спиннер
        if (deleteSpinner) {
            deleteSpinner.classList.remove('d-none');
        }
        
        // Отключаем кнопку и чекбокс
        confirmBtn.disabled = true;
        if (confirmCheckbox) confirmCheckbox.disabled = true;
        
        // Меняем текст кнопки
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-trash-can me-2';
        const label = document.createElement('span');
        label.textContent = fmDelT('del_deleting', 'Deleting...');
        const spin = document.createElement('span');
        spin.className = 'spinner-border spinner-border-sm ms-2';
        this.replaceChildren(icon, label, spin);
        
        deleteEmployee(empid, parentRow, modal);
    });

    // Удаляем модальное окно после закрытия
    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}







async function deleteEmployee(empid, parentRow, modal) {
    try {
        // Получаем my_post_key из глобальной переменной или скрытого поля
        const myPostKey = window.my_post_key || document.querySelector('input[name="my_post_key"]')?.value;
        
        if (!myPostKey) {
            showAlert(fmDelT('del_no_token', 'Security token missing. Please refresh the page and try again.'), 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('fid', empid);
        formData.append('my_post_key', myPostKey);

        const response = await fetch(`index.php?act=management&action=delete&fid=${empid}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.text();

        // Проверяем успешность удаления
        if (response.ok) {
            // Плавно скрываем строку
            if (parentRow) {
                parentRow.classList.add('deleting');
                setTimeout(() => {
                    parentRow.remove();
                    // Обновляем нумерацию если нужно
                    updateRowNumbers();
                }, 300);
            }
            
            showAlert(fmDelT('del_success', 'Forum deleted successfully!'), 'success');
        } else {
            showAlert(fmDelT('del_error', 'Error deleting forum: {1}', result.trim()), 'danger');
        }

        modal.hide();
    } catch (error) {
        console.error('Delete error:', error);
        showAlert(fmDelT('del_network', 'Network error. Please check your connection and try again.'), 'danger');
        modal.hide();
    }
}

function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'danger' ? 'alert-danger' : 'alert-info';
    
    const alertHTML = `
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', alertHTML);
    // Последний добавленный контейнер — сообщение (в т.ч. ответ сервера) ставится как текст
    const toastElement = document.body.lastElementChild.querySelector('.toast');
    toastElement.querySelector('.toast-body').textContent = message;
    toastElement.querySelector('.btn-close').setAttribute('aria-label', fmDelT('close', 'Close'));
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();

    // Удаляем toast после скрытия
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.closest('.toast-container').remove();
    });
}

function updateRowNumbers() {
    // Обновляем порядковые номера если они используются
    document.querySelectorAll('.order-input').forEach((input, index) => {
        input.value = (index + 1) * 10;
    });
}