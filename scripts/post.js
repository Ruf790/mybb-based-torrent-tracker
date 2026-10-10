// Тексты берутся из AGS_LANG (ключи js_* из ланга страницы). Если страница AGS_LANG не
// выводит - работает английский fallback. Хелпер не перезаписывает уже объявленный t().
if (typeof window.t !== 'function') {
    window.t = function (key, fallback, ...args) {
        let str = (typeof AGS_LANG !== 'undefined' && AGS_LANG && typeof AGS_LANG[key] === 'string')
            ? AGS_LANG[key]
            : fallback;
        args.forEach(function (arg, i) {
            str = str.split('{' + (i + 1) + '}').join(String(arg));
        });
        return str;
    };
}

const removeattach_confirm = t('removeattach_confirm', 'Are you sure you want to delete this attachment?');

// ── Модалка подтверждения удаления вложения (Bootstrap modal, в стиле manage_screenshots.php) ──
const AttachRemoveModal = {
    IMAGE_EXT: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],

    esc: function(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    getExtension: function(filename) {
        const parts = (filename || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    },

    buildPreviewHtml: function(aid, filename, sourceEl) {
        const ext = this.getExtension(filename);

        if (this.IMAGE_EXT.includes(ext)) {
            return '<div class="preview-wrapper" style="display:inline-block;max-width:100%;">'
                + '<img src="attachment.php?aid=' + aid + '" alt="' + this.esc(t('attach_preview_alt', 'Preview')) + '" '
                + 'style="max-width:100%;max-height:200px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">'
                + '</div>';
        }

        // Берём уже отрендеренную сервером иконку (get_attachment_icon(), настраивается
        // в админке через attachtypes) - она всегда есть в разметке (сама функция
        // на PHP-стороне гарантированно возвращает <i class="fa..."> даже для
        // неизвестного расширения), поэтому отдельная JS-карта иконок не нужна.
        const existingIcon = sourceEl ? sourceEl.querySelector('i[class*="fa-"]') : null;
        const iconHtml = existingIcon
            ? (() => {
                const clone = existingIcon.cloneNode(true);
                clone.style.fontSize = '52px';
                clone.removeAttribute('title');
                return clone.outerHTML;
            })()
            : '<i class="fa-solid fa-file" style="font-size:52px;color:#94a3b8"></i>';

        return '<div>' + iconHtml
            + '<div class="small text-muted text-uppercase fw-bold mt-2">' + (ext || this.esc(t('attach_file_label', 'file'))) + '</div>'
            + '</div>';
    },

    show: function(aid, filename, sourceEl) {
        return new Promise((resolve) => {
            const existing = document.getElementById('attachRemoveModal');
            if (existing) existing.remove();

            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <div class="modal fade" id="attachRemoveModal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i><span class="attach-remove-title"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="d-flex align-items-center mb-3">
                          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-trash-alt text-danger fs-1"></i>
                          </div>
                          <div>
                            <h5 class="fw-bold mb-1 attach-remove-heading"></h5>
                            <p class="text-muted mb-0 attach-remove-filename"></p>
                          </div>
                        </div>
                        <div class="text-center mb-3 attach-remove-preview"></div>
                        <div class="alert alert-warning mt-2 mb-0">
                          <div class="d-flex">
                            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
                            <div><strong class="attach-remove-warning-label"></strong> <span class="attach-remove-warning"></span></div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                          <i class="fas fa-times me-1"></i> <span class="attach-remove-cancel"></span>
                        </button>
                        <button type="button" class="btn btn-danger" id="attachRemoveConfirmBtn">
                          <i class="fas fa-trash-alt me-1"></i> <span class="attach-remove-confirm"></span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
            `;
            const modalEl = wrapper.firstElementChild;
            // Переведённые тексты - только как textContent / атрибут
            modalEl.querySelector('.attach-remove-title').textContent = t('attach_modal_title', 'Confirm Deletion');
            modalEl.querySelector('.btn-close').setAttribute('aria-label', t('attach_modal_close', 'Close'));
            modalEl.querySelector('.attach-remove-heading').textContent = t('attach_modal_heading', 'Delete Attachment?');
            modalEl.querySelector('.attach-remove-warning-label').textContent = t('attach_modal_warning_label', 'Warning:');
            modalEl.querySelector('.attach-remove-warning').textContent = t('attach_modal_warning', 'This action cannot be undone!');
            modalEl.querySelector('.attach-remove-cancel').textContent = t('attach_modal_cancel', 'Cancel');
            modalEl.querySelector('.attach-remove-confirm').textContent = t('attach_modal_confirm', 'Yes, Delete');
            modalEl.querySelector('.attach-remove-filename').textContent = filename || '';
            modalEl.querySelector('.attach-remove-preview').innerHTML = this.buildPreviewHtml(aid, filename, sourceEl);

            document.body.appendChild(modalEl);

            const bsModal = new bootstrap.Modal(modalEl);
            let resolved = false;

            const finish = (result) => {
                if (resolved) return;
                resolved = true;
                resolve(result);
            };

            modalEl.querySelector('#attachRemoveConfirmBtn').addEventListener('click', () => {
                finish(true);
                bsModal.hide();
            });

            modalEl.addEventListener('hidden.bs.modal', () => {
                finish(false);
                modalEl.remove();
            });

            bsModal.show();
        });
    }
};

const Post = {
    fileInput: null,
    dropZone: null,
    form: null,

    init: function () {
        document.addEventListener('DOMContentLoaded', () => {
            this.fileInput = document.querySelector("input[name='attachments[]']");
            this.dropZone = document.getElementById('dropzone');
            
            // Убеждаемся, что получаем элемент формы, а не input
            this.form = this.fileInput ? this.fileInput.closest('form') : null;

     

            if (!this.fileInput || !this.dropZone || !this.form) {
                console.error('Required elements not found');
                return;
            }

            // Set initial text
            const dropZoneDiv = this.dropZone.querySelector('div');
            if (dropZoneDiv) dropZoneDiv.textContent = lang.drop_files;

            // Event listeners - ТОЛЬКО для кнопок управления файлами
            this.form.addEventListener('submit', (e) => this.checkAttachments(e));
            this.fileInput.addEventListener('change', () => this.addAttachments());

            // Drag and drop events
            this.setupDragAndDrop();

            // Hide file input, show dropzone
            this.fileInput.parentElement.parentElement.style.display = 'none';
            this.dropZone.parentElement.parentElement.style.display = 'block';
        });
    },

    setupDragAndDrop: function() {
        const events = ['drag', 'dragstart', 'dragover', 'dragenter', 'dragleave', 'dragend', 'drop', 'click'];
        
        events.forEach(event => {
            this.dropZone.addEventListener(event, (e) => {
                e.preventDefault();
                
                switch(event) {
                    case 'dragover':
                    case 'dragenter':
                        this.dropZone.classList.add('activated');
                        const div = this.dropZone.querySelector('div');
                        if (div) div.textContent = lang.upload_initiate;
                        break;
                        
                    case 'dragleave':
                    case 'dragend':
                        this.dropZone.classList.remove('activated');
                        const divLeave = this.dropZone.querySelector('div');
                        if (divLeave) divLeave.textContent = lang.drop_files;
                        break;
                        
                    case 'drop':
                        this.dropZone.classList.remove('activated');
                        const files = e.dataTransfer.files;
                        this.fileInput.files = files;
                        this.fileInput.dispatchEvent(new Event('change'));
                        break;
                        
                    case 'click':
                        this.fileInput.click();
                        break;
                }
            });
        });
    },

    loadMultiQuoted: function() {
        const tid = document.input.tid.value;
        this.ajaxRequest('xmlhttp.php?action=get_multiquoted&tid=' + tid, 'GET')
            .then(request => this.multiQuotedLoaded(request));
        return false;
    },

    loadMultiQuotedAll: function() {
        this.ajaxRequest('xmlhttp.php?action=get_multiquoted&load_all=1', 'GET')
            .then(request => this.multiQuotedLoaded(request));
        return false;
    },

    multiQuotedLoaded: function(request) {
        const json = JSON.parse(request.responseText);
        if (typeof json === 'object' && json.errors) {
            json.errors.forEach(message => {
                showToast(lang.post_fetch_error + ' ' + message, 'error');
            });
            return false;
        }

        const messageEl = document.getElementById('message');
        if (messageEl) {
            if (messageEl.value) {
                messageEl.value += "\n";
            }
            messageEl.value += json.message;
        }

        const multiquoteUnloaded = document.getElementById('multiquote_unloaded');
        if (multiquoteUnloaded) multiquoteUnloaded.style.display = 'none';
        
        document.input.quoted_ids.value = 'all';
    },

    clearMultiQuoted: function() {
        const multiquoteUnloaded = document.getElementById('multiquote_unloaded');
        if (multiquoteUnloaded) multiquoteUnloaded.style.display = 'none';
        this.deleteCookie('multiquote');
    },

    removeAttachment: function(aid) {
        const attachment = document.getElementById('attachment_' + aid);
        let filename = '';
        if (attachment) {
            if (attachment.dataset.filename) {
                filename = attachment.dataset.filename;
            } else {
                const nameEl = attachment.querySelector('.attachment-name, .attach-item-name, .attachment_filename, a');
                if (nameEl) filename = nameEl.textContent.trim();
            }
        }

        AttachRemoveModal.show(aid, filename, attachment).then((confirmed) => {
            if (!confirmed) return;

            this.attachmentAction(aid, 'remove');

            // Получаем URL действия формы через getAttribute
            const formAction = this.form.getAttribute('action');

            this.ajaxRequest(formAction + '&ajax=1', 'POST', new FormData(this.form))
                .then(data => {
                    if (data.errors) {
                        data.errors.forEach(message => {
                            showToast(message, 'error');
                        });
                        return false;
                    } else if (data.success) {
                        if (attachment) {
                            attachment.style.transition = 'opacity 0.5s';
                            attachment.style.opacity = '0';
                            setTimeout(() => {
                                const messageEl = document.getElementById('message');
                                if (messageEl) {
                                    messageEl.value = messageEl.value.split('[attachment=' + aid + ']').join('');
                                }

                                const usageEl = attachment.parentElement.querySelector('.tcat>strong');
                                if (usageEl) usageEl.textContent = data.usage;

                                attachment.remove();
                                this.regenAttachbuttons();
                            }, 500);
                        }
                        showToast(t('attach_removed', 'Attachment successfully removed'), 'success');
                    }
                    this.attachmentAction('', '');
                })
                .catch(() => {
                    showToast(t('attach_remove_error', 'Error removing attachment'), 'error');
                });
        });

        return false;
    },

    attachmentAction: function(aid, action) {
        document.input.attachmentaid.value = aid;
        document.input.attachmentact.value = action;
    },

    getAttachments: function() {
        const filenames = document.querySelectorAll('.attachment_filename');
        return Array.from(filenames).map(el => el.textContent);
    },

    getCommonFiles: function() {
        const files = this.fileInput.files;
        if (files.length) {
            const names = Array.from(files).map(file => file.name);
            return this.getAttachments().filter(name => names.includes(name));
        }
        return [];
    },

    addAttachments: function() {
        if (!this.checkAttachments()) return false;

        if (this.fileInput.files.length) {
            const common = this.getCommonFiles();
            if (common.length) {
                const list = document.createElement('ul');
                common.forEach(val => {
                    const li = document.createElement('li');
                    li.textContent = val;
                    list.appendChild(li);
                });

                if (confirm(lang.update_confirm.replace("{1}", list.outerHTML))) {
                    this.addHiddenInput('updateconfirmed', '1');
                    this.uploadAttachments('updateattachment');
                }
            } else {
                this.uploadAttachments('newattachment');
            }
        }
        return false;
    },

    uploadAttachments: function(type) {
        this.addHiddenInput(type, '1');
        const formData = new FormData(this.form);

        // Получаем URL действия формы через getAttribute
        const formAction = this.form.getAttribute('action');
        
        
        this.ajaxRequest(formAction + '&ajax=1', 'POST', formData, true)
            .then(data => {
                if (data.errors) {
                    data.errors.forEach(message => {
                        showToast(message, 'error');
                    });
                }
                
                if (data.success) {
                    data.success.forEach(message => {
                        const existing = document.getElementById('attachment_' + message[0]);
                        if (existing) existing.remove();
                        
                        const template = data.template
                            .replace(/\{1\}/g, message[0])
                            .replace('{2}', message[1])
                            .replace('{3}', message[2])
                            .replace('{4}', message[3]);
                        
                        const container = this.fileInput.parentElement.parentElement.parentElement;
                        container.insertAdjacentHTML('beforeend', template);
                        
                        const usageEl = container.querySelector('.tcat>strong');
                        if (usageEl) usageEl.textContent = data.usage;
                    });
                    showToast(t('files_uploaded', 'Files uploaded successfully'), 'success');
                }

                this.fileInput.value = '';
                this.regenAttachbuttons();
                this.removeTempInputs();
            })
            .catch(() => {
                showToast(t('files_upload_error', 'Error uploading files'), 'error');
                this.removeTempInputs();
            });
    },

    regenAttachbuttons: function() {
        const attachButtons = document.querySelectorAll("input[name=newattachment], input[name=updateattachment]");
        if (attachButtons.length === 0) return;

        const attachButton = attachButtons[0].cloneNode(true);
        const attachments = this.getAttachments();

        // Remove existing buttons
        document.querySelectorAll('input[name=updateattachment]').forEach(btn => btn.remove());
        document.querySelectorAll('input[name=newattachment]').forEach(btn => btn.remove());

        if (attachments.length) {
            const updateButton = attachButton.cloneNode(true);
            updateButton.name = 'updateattachment';
            updateButton.value = lang.update_attachment;
            updateButton.tabIndex = 12;
            
            const newAttachmentBtn = document.querySelector("input[name='newattachment']");
            if (newAttachmentBtn) {
                newAttachmentBtn.parentNode.insertBefore(document.createTextNode(' '), newAttachmentBtn);
                newAttachmentBtn.parentNode.insertBefore(updateButton, newAttachmentBtn);
            }
        }

        if (attachments.length < mybb_max_file_uploads) {
            const newButton = attachButton.cloneNode(true);
            newButton.name = 'newattachment';
            newButton.value = lang.add_attachment;
            newButton.tabIndex = 13;
            
            const updateAttachmentBtn = document.querySelector("input[name='updateattachment']");
            if (updateAttachmentBtn) {
                updateAttachmentBtn.parentNode.appendChild(document.createTextNode(' '));
                updateAttachmentBtn.parentNode.appendChild(newButton);
            }
        }
    },

    checkAttachments: function(e) {
        if (!e) return true;
        
        // Определяем какая кнопка вызвала submit
        const submitter = e.submitter?.name || '';
        
        // Только эти кнопки должны проверять файлы
        const fileButtons = ['newattachment', 'updateattachment'];
        
        // Если это не кнопка управления файлами - пропускаем проверку
        if (!fileButtons.includes(submitter)) {
            return true; // Позволяем форме отправиться
        }
        
        // Только для кнопок управления файлами блокируем отправку
        e.preventDefault();
        
        const file = this.fileInput;
        if (!file) {
            showToast(t('file_input_missing', 'File input not found'), 'error');
            return false;
        }

        // Проверяем только если это кнопки вложений
        if (!file.files.length) {
            showToast(lang.attachment_missing, 'error');
            return false;
        }

        if (mybb_max_file_uploads !== 0) {
            const common = this.getCommonFiles().length;
            const moreAllowed = (mybb_max_file_uploads - (this.getAttachments().length - common));
            
            if (moreAllowed < 0 || (!moreAllowed && file.files.length)) {
                showToast(lang.error_maxattachpost.replace('{1}', mybb_max_file_uploads), 'error');
                file.value = '';
                return false;
            } else if (file.files.length > moreAllowed) {
                showToast(lang.attachment_max_allowed_files.replace('{1}', (moreAllowed - common)), 'error');
                file.value = '';
                return false;
            }
        }

        if (file.files.length > php_max_file_uploads && php_max_file_uploads !== 0) {
            showToast(lang.attachment_too_many_files.replace('{1}', php_max_file_uploads), 'error');
            file.value = '';
            return false;
        }

        let totalSize = 0;
        Array.from(file.files).forEach(file => {
            totalSize += file.size;
        });

        if (totalSize > php_max_upload_size && php_max_upload_size > 0) {
            const php_max_upload_size_pretty = Math.round(php_max_upload_size / 1e4) / 1e2;
            showToast(lang.attachment_too_big_upload.replace('{1}', php_max_upload_size_pretty), 'error');
            file.value = '';
            return false;
        }

        // Если проверка прошла успешно
        return true;
    },

    // Helper methods
    ajaxRequest: function(url, method, data = null, isFormData = false) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url);
            
            if (!isFormData) {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error('Request failed'));
                }
            };
            
            xhr.onerror = () => reject(new Error('Request failed'));
            
            if (data && isFormData) {
                xhr.send(data);
            } else if (data) {
                xhr.send(new URLSearchParams(data));
            } else {
                xhr.send();
            }
        });
    },

    addHiddenInput: function(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        input.className = 'temp_input';
        this.form.appendChild(input);
    },

    removeTempInputs: function() {
        document.querySelectorAll('.temp_input').forEach(input => input.remove());
    },

    deleteCookie: function(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    }
};

// Initialize
Post.init();