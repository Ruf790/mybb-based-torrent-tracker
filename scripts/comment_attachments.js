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

    // ── Delete ───────────────────────────────────────────────────
    function bindDeleteBtn(item) {
        if (!item) return;
        item.querySelector('.att-delete-btn')?.addEventListener('click', () => {
            const aid = item.dataset.aid;
            if (!aid) { item.remove(); return; }

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('aid', aid);
            fd.append('my_post_key', postKey);

            fetch(uploadUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(resp => { if (resp.success) item.remove(); })
                .catch(() => item.remove());
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