/* batch_upload.js */
'use strict';

const VALID_IMAGE_TYPES = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];

let torrentCount = 1;

// ── Утилиты ──────────────────────────────────────────────

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text ?? '';
    return d.innerHTML;
}

function getCategoryHtml(name, idx) {
    const opts = BATCH_CONFIG.categories.map(c =>
        `<option value="${c.id}">${escapeHtml(c.name)}</option>`
    ).join('');
    return `<select class="form-control" name="${name}[${idx}]">${opts}</select>`;
}

function buildTorrentItemHtml(idx, fileName = '') {
    return `
    <div class="row">
      <div class="col-md-6">
        <label class="form-label fw-bold"><i class="fa-solid fa-file-archive me-1"></i>Torrent File *</label>
        <input class="form-control" type="file" name="torrentFiles[]" accept=".torrent">
        <div class="torrent-name mt-1 small text-muted">${escapeHtml(fileName)}</div>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-bold"><i class="fa-solid fa-image me-1"></i>Poster Image (Optional)</label>
        <input class="form-control" type="file" name="posters[]" accept="image/*">
        <div class="image-preview mt-2" style="max-width:150px;display:none">
          <img src="" class="img-thumbnail" style="max-height:100px">
        </div>
      </div>
    </div>
    <div class="row mt-2">
      <div class="col-md-6">
        <label class="form-label">Category</label>
        ${getCategoryHtml('batch_categories', idx)}
      </div>
      <div class="col-md-6">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description..."></textarea>
      </div>
    </div>`;
}

function setFileToInput(input, file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function showImagePreview(posterInput, file) {
    const preview = posterInput.closest('.col-md-6')?.querySelector('.image-preview');
    if (!preview) return;
    const img = preview.querySelector('img');
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(file);
}

function validateImage(file) {
    if (!VALID_IMAGE_TYPES.includes(file.type)) {
        alert(`${file.name}: invalid image type`);
        return false;
    }
    if (file.size > BATCH_CONFIG.maxImageBytes) {
        alert(`${file.name}: too large (max 5MB)`);
        return false;
    }
    return true;
}

// ── DOM готов ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const form             = document.getElementById('batchUploadForm');
    const progressModal    = new bootstrap.Modal(document.getElementById('batchProgressModal'));
    const overallBar       = document.getElementById('overallProgressBar');
    const overallPercent   = document.getElementById('overallProgressPercent');
    const fileProgressCont = document.getElementById('fileProgressContainer');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsList      = document.getElementById('resultsList');
    const closeModalBtn    = document.getElementById('closeModalBtn');
    const viewTorrentsBtn  = document.getElementById('viewTorrentsBtn');
    const dropZone         = document.querySelector('.drop-zone');
    const dragDropInput    = document.getElementById('dragDropFiles');

    updateTorrentCount();

    // ── Drag & Drop ───────────────────────────────────────

    dropZone?.addEventListener('click', () => dragDropInput.click());
    dropZone?.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.style.borderColor = '#0d6efd';
        dropZone.style.background  = '#e7f1ff';
    });
    dropZone?.addEventListener('dragleave', () => {
        dropZone.style.borderColor = '#6c757d';
        dropZone.style.background  = '#f8f9fa';
    });
    dropZone?.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor = '#6c757d';
        dropZone.style.background  = '#f8f9fa';
        handleDroppedFiles(e.dataTransfer.files);
    });
    dragDropInput?.addEventListener('change', function () {
        handleDroppedFiles(this.files);
    });

    // ── Кнопки ────────────────────────────────────────────

    document.getElementById('addMore')?.addEventListener('click', () => {
        if (torrentCount >= BATCH_CONFIG.maxTorrents) {
            alert(`Maximum ${BATCH_CONFIG.maxTorrents} torrents`);
            return;
        }
        addTorrentItem(null, torrentCount);
        updateTorrentCount();
    });

    // Предпросмотр постеров (делегирование)
    document.addEventListener('change', e => {
        if (!e.target.matches('input[name="posters[]"]')) return;
        const file = e.target.files[0];
        if (!file || !validateImage(file)) { e.target.value = ''; return; }
        showImagePreview(e.target, file);
    });

    // Отправка формы
    form?.addEventListener('submit', e => {
        e.preventDefault();

        const hasFiles = [...document.querySelectorAll('input[name="torrentFiles[]"]')]
            .some(i => i.files.length > 0);
        if (!hasFiles) { alert('Please select at least one torrent file'); return; }

        const allValid = [...document.querySelectorAll('input[name="posters[]"]')]
            .every(i => i.files.length === 0 || validateImage(i.files[0]));
        if (!allValid) return;

        resetUI();
        progressModal.show();
        createFileProgressItems();
        uploadFiles();
    });

    closeModalBtn?.addEventListener('click',  () => progressModal.hide());
    viewTorrentsBtn?.addEventListener('click', () => window.open('/browse.php', '_blank'));

    // ── Обработка файлов ──────────────────────────────────

    function handleDroppedFiles(files) {
        const container = document.getElementById('torrentContainer');
        const items     = container.querySelectorAll('.torrent-item');

        [...items].slice(1).forEach(i => i.remove());

        if (items[0]) {
            items[0].querySelector('input[name="torrentFiles[]"]').value = '';
            items[0].querySelector('.torrent-name').textContent          = '';
            items[0].querySelector('input[name="posters[]"]').value      = '';
            const p = items[0].querySelector('.image-preview');
            if (p) p.style.display = 'none';
            items[0].querySelector('textarea[name="descriptions[]"]').value = '';
        }

        torrentCount = 0;

        const torrentFiles = [...files]
            .filter(f => f.name.endsWith('.torrent'))
            .slice(0, BATCH_CONFIG.maxTorrents);

        torrentFiles.forEach((file, idx) => {
            if (idx === 0 && items[0]) {
                setFileToInput(items[0].querySelector('input[name="torrentFiles[]"]'), file);
                items[0].querySelector('.torrent-name').textContent = file.name;
            } else {
                addTorrentItem(file, idx);
            }
            torrentCount++;
        });

        updateTorrentCount();
    }

    function addTorrentItem(file, idx) {
        const container = document.getElementById('torrentContainer');
        const div = document.createElement('div');
        div.className = 'torrent-item mb-3 border p-3 rounded';
        div.innerHTML = buildTorrentItemHtml(idx, file?.name ?? '');
        container.appendChild(div);
        if (file) setFileToInput(div.querySelector('input[name="torrentFiles[]"]'), file);
    }

    function updateTorrentCount() {
        torrentCount = document.querySelectorAll('.torrent-item').length;
        const btn = document.getElementById('batchUploadBtn');
        if (btn) btn.innerHTML = `<i class="fa-solid fa-cloud-upload me-1"></i>Upload ${torrentCount} Torrent${torrentCount !== 1 ? 's' : ''}`;
    }

    function resetUI() {
        resultsContainer.style.display = 'none';
        resultsList.innerHTML          = '';
        fileProgressCont.innerHTML     = '';
        overallBar.style.width         = '0%';
        overallBar.className           = 'progress-bar progress-bar-striped progress-bar-animated';
        overallPercent.textContent     = '0%';
        closeModalBtn.style.display    = 'none';
        viewTorrentsBtn.style.display  = 'none';
    }

    // ── Прогресс ──────────────────────────────────────────

    function createFileProgressItems() {
        document.querySelectorAll('input[name="torrentFiles[]"]').forEach((input, idx) => {
            if (!input.files.length) return;
            const el = document.createElement('div');
            el.className = 'mb-2';
            el.id        = `fileProgress_${idx}`;
            el.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-truncate" style="max-width:70%">
                    <i class="fas fa-file me-1"></i>${escapeHtml(input.files[0].name)}
                  </span>
                  <span class="badge bg-secondary">Waiting</span>
                </div>
                <div class="progress" style="height:6px">
                  <div class="progress-bar" style="width:0%"></div>
                </div>`;
            fileProgressCont.appendChild(el);
        });
    }

    function updateFileProgress(idx, status, percent = 0) {
        const el = document.getElementById(`fileProgress_${idx}`);
        if (!el) return;
        const badge = el.querySelector('.badge');
        const bar   = el.querySelector('.progress-bar');
        bar.style.width = percent + '%';
        const map = {
            uploading:  ['bg-info',    'Uploading...',  'progress-bar bg-info progress-bar-striped progress-bar-animated'],
            processing: ['bg-warning', 'Processing...', 'progress-bar bg-warning progress-bar-striped progress-bar-animated'],
            success:    ['bg-success', 'Success',       'progress-bar bg-success'],
            error:      ['bg-danger',  'Error',         'progress-bar bg-danger'],
        };
        const [bc, text, barClass] = map[status] ?? ['bg-secondary', status, 'progress-bar'];
        badge.className   = `badge ${bc}`;
        badge.textContent = text;
        bar.className     = barClass;
    }

    // ── XHR загрузка ──────────────────────────────────────

    function uploadFiles() {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', e => {
            if (!e.lengthComputable) return;
            const pct = Math.round(e.loaded / e.total * 100);
            overallBar.style.width     = pct + '%';
            overallPercent.textContent = pct + '%';
            document.querySelectorAll('input[name="torrentFiles[]"]').forEach((inp, idx) => {
                if (inp.files.length) updateFileProgress(idx, 'uploading', pct);
            });
        });

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) return;
            try {
                if (!xhr.getResponseHeader('Content-Type')?.includes('application/json')) {
                    throw new Error('Non-JSON response from server');
                }
                const data = JSON.parse(xhr.responseText);
                data.success ? showResults(data) : showError(data.error ?? 'Server error');
            } catch (e) {
                showError('Response error: ' + e.message);
            }
        };

        xhr.onerror   = () => showError('Network error');
        xhr.timeout   = 300000;
        xhr.ontimeout = () => showError('Request timeout');

        xhr.open('POST', BATCH_CONFIG.scriptUrl);
        xhr.send(new FormData(form));
    }

    // ── Отображение результатов ───────────────────────────

    function showResults(data) {
        overallBar.className           = 'progress-bar bg-success';
        overallBar.style.width         = '100%';
        overallPercent.textContent     = '100%';
        resultsContainer.style.display = 'block';

        data.results?.forEach((_, i) => updateFileProgress(i, 'success', 100));

        const stats = document.createElement('div');
        stats.className = 'alert alert-success mb-3';
        stats.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            <strong>Done!</strong> Uploaded ${data.successful} of ${data.processed} torrents<br>
            <small class="text-muted">
                ${data.stats?.with_posters ?? 0} with posters |
                ${data.stats?.csv_imported ?? 0} CSV records
            </small>`;
        resultsList.appendChild(stats);

        if (data.results?.length) {
            const div = document.createElement('div');
            div.className = 'mb-3';
            data.results.forEach(r => {
                const item = document.createElement('div');
                item.className = 'alert alert-light border mb-2 d-flex align-items-center';
                item.innerHTML = `
                    <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                      <div>
                        <strong>${escapeHtml(r.name)}</strong><br>
                        <small class="text-muted">ID: ${r.id} | Files: ${r.files} | Size: ${r.size}</small>
                      </div>
                      <div>
                        ${r.has_poster ? '<span class="badge bg-info me-2"><i class="fas fa-image"></i></span>' : ''}
                        <a href="${escapeHtml(r.link)}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                      </div>
                    </div>`;
                div.appendChild(item);
            });
            resultsList.appendChild(div);
        }

        if (data.errors?.length) {
            const err = document.createElement('div');
            err.className = 'alert alert-danger mt-3';
            err.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Errors (${data.errors.length}):</strong>
                <ul class="mb-0">${data.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul>`;
            resultsList.appendChild(err);
        }

        closeModalBtn.style.display   = 'block';
        viewTorrentsBtn.style.display = 'block';
    }

    function showError(msg) {
        resultsContainer.style.display = 'block';
        overallBar.className           = 'progress-bar bg-danger';
        const err = document.createElement('div');
        err.className = 'alert alert-danger';
        err.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i><strong>Error:</strong> ${escapeHtml(msg)}`;
        resultsList.appendChild(err);
        closeModalBtn.style.display = 'block';
    }
});
