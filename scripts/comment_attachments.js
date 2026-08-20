/**
 * comment_attachments.js
 * Attachment uploader for comments
 */

function initAttachmentUploader(posthash, postKey, uploadUrl, commentId) {

    commentId = commentId || 0;

    const dropzone  = document.getElementById('attDropzone-' + posthash);
    const list      = document.getElementById('attPreviewList-' + posthash);
    const fileInput = dropzone?.querySelector('.att-file-input');

    if (!dropzone || !list || !fileInput) return;

    // ── Drag & Drop ──────────────────────────────────────────────
    ['dragenter', 'dragover'].forEach(ev =>
        dropzone.addEventListener(ev, e => {
            e.preventDefault();
            dropzone.classList.add('att-dropzone--active');
        })
    );
    ['dragleave', 'dragend', 'drop'].forEach(ev =>
        dropzone.addEventListener(ev, e => {
            e.preventDefault();
            dropzone.classList.remove('att-dropzone--active');
        })
    );
    dropzone.addEventListener('drop', e => {
        uploadFiles(Array.from(e.dataTransfer.files));
    });
    fileInput.addEventListener('change', () => {
        uploadFiles(Array.from(fileInput.files));
        fileInput.value = '';
    });

    // ── Upload ───────────────────────────────────────────────────
    function uploadFiles(files) {
        files.forEach(uploadFile);
    }

    function uploadFile(file) {
        const item = document.createElement('div');
        item.className = 'att-item att-item--uploading';
        item.innerHTML = `
            <div class="att-item-thumb att-item-thumb--loading">
                <div class="att-spinner"></div>
            </div>
            <div class="att-item-info">
                <div class="att-item-name">${escHtml(file.name)}</div>
                <div class="att-item-size">${formatSize(file.size)}</div>
                <div class="att-progress"><div class="att-progress-bar" style="width:0%"></div></div>
            </div>`;
        list.appendChild(item);

        const fd = new FormData();
        fd.append('attachment', file);
        fd.append('posthash', posthash);
        fd.append('my_post_key', postKey);
        fd.append('comment_id', commentId);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);

        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const pct = Math.round(e.loaded / e.total * 100);
                item.querySelector('.att-progress-bar').style.width = pct + '%';
            }
        });

        xhr.addEventListener('load', () => {
            let resp;
            try { resp = JSON.parse(xhr.responseText); } catch { resp = null; }

            if (resp?.success) {
                item.outerHTML = buildPreviewItem(resp);
                bindDeleteBtn(list.querySelector('[data-aid="' + resp.aid + '"]'));
            } else {
                item.classList.add('att-item--error');
                item.querySelector('.att-item-size').textContent = resp?.error || 'Upload failed';
                item.querySelector('.att-progress')?.remove();
                setTimeout(() => item.remove(), 4000);
            }
        });

        xhr.addEventListener('error', () => {
            item.classList.add('att-item--error');
            item.querySelector('.att-item-size').textContent = 'Network error';
            setTimeout(() => item.remove(), 4000);
        });

        xhr.send(fd);
    }

    // ── Build preview HTML ───────────────────────────────────────
    function buildPreviewItem(resp) {
        const isImage = resp.is_image;
        const isVideo = resp.is_video;
        const isAudio = resp.is_audio;

        let thumbHtml = '';
        if (isImage) {
            thumbHtml = `<a href="${escHtml(resp.url)}" target="_blank" class="att-thumb-link">
                <img src="${escHtml(resp.thumb || resp.url)}" class="att-thumb-img" alt="">
            </a>`;
        } else if (isVideo) {
            thumbHtml = `<a href="${escHtml(resp.url)}" target="_blank" class="att-thumb-link att-video-link">
                <i class="fas fa-play-circle att-thumb-icon" style="color:#0d6efd;font-size:2rem;"></i>
            </a>`;
        } else if (isAudio) {
            thumbHtml = `<i class="fas fa-music att-thumb-icon" style="color:#6f42c1;font-size:2rem;"></i>`;
        } else {
            thumbHtml = resp.icon || `<i class="fas fa-file att-thumb-icon"></i>`;
        }

        const extraHtml = isAudio
            ? `<audio controls class="att-audio-player mt-1" style="width:100%;height:28px;">
                   <source src="${escHtml(resp.url)}" type="${escHtml(resp.type)}">
               </audio>`
            : '';

        return `
            <div class="att-item${isAudio ? ' att-item--audio' : ''}" data-aid="${resp.aid}">
                <div class="att-item-thumb">${thumbHtml}</div>
                <div class="att-item-info">
                    <div class="att-item-name">
                        <a href="${escHtml(resp.url)}" target="_blank">${escHtml(resp.name)}</a>
                    </div>
                    <div class="att-item-size">${formatSize(resp.size)}</div>
                    ${extraHtml}
                </div>
                <button type="button" class="att-delete-btn" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
    }

    // ── Delete confirmation modal ───────────────────────────────────
    function showAttachDeleteModal(item) {
        return new Promise((resolve) => {
            const existing = document.getElementById('attDeleteModal');
            if (existing) existing.remove();

            const nameEl = item.querySelector('.att-item-name');
            const filename = nameEl ? nameEl.textContent.trim() : '';
            const thumbEl = item.querySelector('.att-item-thumb');
            const previewHtml = thumbEl ? thumbEl.innerHTML : '';

            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <div class="modal fade" id="attDeleteModal" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="d-flex align-items-center mb-3">
                          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-trash-alt text-danger fs-1"></i>
                          </div>
                          <div>
                            <h5 class="fw-bold mb-1">Delete Attachment?</h5>
                            <p class="text-muted mb-0 att-delete-filename"></p>
                          </div>
                        </div>
                        <div class="text-center mb-3 att-delete-preview" style="font-size:2.5rem;"></div>
                        <div class="alert alert-warning mt-2 mb-0">
                          <div class="d-flex">
                            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
                            <div><strong>Warning:</strong> This action cannot be undone!</div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                          <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="attDeleteConfirmBtn">
                          <i class="fas fa-trash-alt me-1"></i> Yes, Delete
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
            `;
            const modalEl = wrapper.firstElementChild;
            modalEl.querySelector('.att-delete-filename').textContent = filename;

            const previewBox = modalEl.querySelector('.att-delete-preview');
            previewBox.innerHTML = previewHtml;

            const previewImg = previewBox.querySelector('img');
            if (previewImg) {
                previewImg.classList.remove('att-thumb-img');
                previewImg.style.width = 'auto';
                previewImg.style.height = 'auto';
                previewImg.style.maxWidth = '100%';
                previewImg.style.maxHeight = '260px';
                previewImg.style.objectFit = 'contain';
                previewImg.style.borderRadius = '8px';
                previewImg.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                const link = previewBox.querySelector('a');
                if (link) {
                    link.removeAttribute('href');
                    link.removeAttribute('target');
                    link.style.cursor = 'default';
                    link.style.display = 'inline-block';
                }
            }

            document.body.appendChild(modalEl);

            const bsModal = new bootstrap.Modal(modalEl);
            let resolved = false;
            const finish = (result) => {
                if (resolved) return;
                resolved = true;
                resolve(result);
            };

            modalEl.querySelector('#attDeleteConfirmBtn').addEventListener('click', () => {
                console.log('[comment_attachments] Yes, Delete clicked');
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

    // ── Delete ───────────────────────────────────────────────────
    function bindDeleteBtn(item) {
        if (!item) return;
        const btn = item.querySelector('.att-delete-btn');
        if (!btn) {
            console.warn('[comment_attachments] .att-delete-btn not found in item', item);
            return;
        }
        btn.addEventListener('click', () => {
            showAttachDeleteModal(item).then((confirmed) => {
                if (!confirmed) {
                    console.log('[comment_attachments] delete cancelled');
                    return;
                }

                const aid = item.dataset.aid;
                if (!aid) {
                    console.warn('[comment_attachments] no data-aid on item, removing from DOM only', item);
                    item.remove();
                    return;
                }

                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('aid', aid);
                fd.append('my_post_key', postKey);

                console.log('[comment_attachments] sending delete request', { aid, uploadUrl });

                fetch(uploadUrl, { method: 'POST', body: fd })
                    .then(r => {
                        console.log('[comment_attachments] delete response status', r.status);
                        return r.json();
                    })
                    .then(resp => {
                        console.log('[comment_attachments] delete response body', resp);
                        if (resp.success) {
                            item.remove();
                        } else {
                            console.warn('[comment_attachments] server reported failure, not removing item', resp);
                        }
                    })
                    .catch(err => {
                        console.error('[comment_attachments] delete request failed', err);
                    });
            });
        });
    }

    // Bind delete on existing items
    list.querySelectorAll('.att-item[data-aid]').forEach(bindDeleteBtn);

    // ── Helpers ──────────────────────────────────────────────────
    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, c => (
            {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]
        ));
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
}