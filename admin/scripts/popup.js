/**
 * Строка из AGS_LANG (js_* без префикса) с английским fallback и подстановкой {1}, {2}…
 * popup.js подключается и на страницах без AGS_LANG — тогда всегда fallback.
 */
function popupT(key, fallback, ...args) {
    const dict = (typeof AGS_LANG !== 'undefined' && AGS_LANG) ? AGS_LANG : {};
    let str = (typeof dict[key] === 'string' && dict[key] !== '') ? dict[key] : fallback;
    args.forEach((a, i) => { str = str.split('{' + (i + 1) + '}').join(String(a)); });
    return str;
}

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
    if (e.target.id !== 'modal_form') return;
    e.preventDefault();
    const form = e.target;

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn?.innerHTML;
    if (submitBtn) {
        if (submitBtn.disabled) return; // повторный сабмит, пока идёт запрос
        submitBtn.disabled = true;
        const spinIcon = document.createElement('i');
        spinIcon.className = 'fas fa-spinner fa-spin me-2';
        submitBtn.replaceChildren(spinIcon, document.createTextNode(popupT('popup_saving', 'Saving...')));
    }
    const restoreBtn = () => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    };
    const hideModal = () => {
        const modalElement = document.getElementById('dynamicModal');
        if (modalElement) bootstrap.Modal.getInstance(modalElement)?.hide();
    };

    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(response => response.text().then(text => ({ status: response.status, text })))
        .then(({ status, text }) => {
            let data = null;
            try { data = JSON.parse(text); } catch (err) { /* не JSON — старый формат */ }

            // Новый формат: {ok, gid, html} — заменить строку таблицы целиком
            if (data && typeof data === 'object' && 'ok' in data) {
                if (!data.ok) {
                    alert(data.error || popupT('popup_save_failed_http', 'Failed to save (HTTP {1}).', status));
                    restoreBtn();
                    return;
                }
                replaceModalRow(data);
                hideModal();
                restoreBtn();
                return;
            }

            // Старый формат (строка с <script> или HTML) — для остальных страниц с popupWindow()
            handleLegacyModalResponse(typeof data === 'string' ? data : text);
            hideModal();
            if (typeof QuickPermEditor !== 'undefined' && typeof QuickPermEditor.initAll === 'function') {
                setTimeout(() => QuickPermEditor.initAll(), 200);
            }
            restoreBtn();
        })
        .catch(error => {
            console.error('Form submission error:', error);
            alert(popupT('popup_save_failed_error', 'Failed to save. Error: {1}', error.message));
            restoreBtn();
        });
}

/** Заменяет tr[data-group-id=gid] (или #row_{gid}) на data.html и переинициализирует QuickPermEditor */
function replaceModalRow(data) {
    const gid = parseInt(data.gid, 10);
    if (!gid || typeof data.html !== 'string' || data.html === '') return;

    const tpl = document.createElement('template');
    tpl.innerHTML = data.html.trim();
    const newRow = tpl.content.querySelector('tr');
    if (!newRow) return;

    const modal = document.getElementById('dynamicModal');
    const oldRow = Array.from(document.querySelectorAll('tr[data-group-id="' + gid + '"], #row_' + gid))
        .find(tr => !modal || !modal.contains(tr));
    if (!oldRow) return;

    oldRow.replaceWith(newRow);

    if (typeof QuickPermEditor !== 'undefined' && typeof QuickPermEditor.init === 'function') {
        QuickPermEditor.init(gid);
    }
}

/** Старый протокол: JSON-строка с <script> внутри или HTML со строкой [id^="row_"] */
function handleLegacyModalResponse(html) {
    if (!html) return;
    const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
    let match;
    let scriptsExecuted = false;

    while ((match = scriptRegex.exec(html)) !== null) {
        scriptsExecuted = true;
        try {
            const el = document.createElement('script');
            el.textContent = match[1];
            document.body.appendChild(el);
            el.remove();
        } catch (err) {
            console.error('Error executing modal response script:', err);
        }
    }

    if (!scriptsExecuted) {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newRow = temp.querySelector('[id^="row_"]');
        const existingRow = newRow && document.getElementById(newRow.id);
        if (existingRow) existingRow.outerHTML = newRow.outerHTML;
    }
}