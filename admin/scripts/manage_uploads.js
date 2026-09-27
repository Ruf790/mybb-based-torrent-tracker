/* ═══════════════════════════════════════════════════════════
   Media Library (admin/manage_uploads.php)
   Все обработчики таблицы - через делегирование на document,
   поэтому после AJAX-поиска ничего не нужно перепривязывать.
   ═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    // ── Helpers ─────────────────────────────────────────────
    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes < 1024) return bytes + ' B';
        const units = ['KB', 'MB', 'GB', 'TB'];
        let i = -1;
        do { bytes /= 1024; i++; } while (bytes >= 1024 && i < units.length - 1);
        return bytes.toFixed(bytes < 10 ? 2 : 1) + ' ' + units[i];
    }

    function shorten(name, max) {
        name = String(name ?? '');
        if (name.length <= max) return name;
        const dot = name.lastIndexOf('.');
        if (dot > 0 && name.length - dot <= 6) {
            const ext = name.slice(dot);
            return name.slice(0, Math.max(1, max - ext.length - 1)) + '…' + ext;
        }
        return name.slice(0, max - 1) + '…';
    }

    function notify(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        } else if (typeof window.Swal !== 'undefined') {
            const icon = ({ success: 'success', error: 'error', warning: 'warning' })[type] || 'info';
            window.Swal.fire({ icon, title: msg });
        } else {
            alert(msg);
        }
    }

    function postKey() {
        return $('#bulkForm input[name="my_post_key"]')?.value || '';
    }

    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    /** Данные строки: из data-атрибутов, с fallback на старую разметку */
    function rowInfo(row) {
        const name = row.dataset.fileName
            || row.querySelector('.mu-fname, .fw-semibold')?.textContent.trim()
            || 'Unknown file';
        const size = Number(row.dataset.fileSize || 0);
        const img  = row.querySelector('.img-preview');
        return { id: row.dataset.id, name, size, preview: img && img.src ? img.src : '' };
    }

    function fileRows()     { return $$('#filesTableContainer tbody tr[data-id]'); }
    function visibleBoxes() { return fileRows().filter(r => !r.classList.contains('d-none')).map(r => r.querySelector('.file-checkbox')).filter(Boolean); }

    // ── Selection & bulk bar ────────────────────────────────
    function updateSelection() {
        const checked  = $$('.file-checkbox:checked');
        const ids      = checked.map(cb => cb.value);
        const visible  = visibleBoxes();
        const selectAll = $('#selectAll');

        const countEl = $('#selectedCount');
        if (countEl) countEl.textContent = ids.length;

        const input = $('#selectedFilesInput');
        if (input) input.value = ids.join(',');

        if (selectAll) {
            const visChecked = visible.filter(cb => cb.checked).length;
            selectAll.checked       = visible.length > 0 && visChecked === visible.length;
            selectAll.indeterminate = visChecked > 0 && visChecked < visible.length;
        }

        $('#bulkActions')?.classList.toggle('show', ids.length > 0);

        const applyBtn = $('#applyBulkAction');
        if (applyBtn) applyBtn.disabled = ids.length === 0;
    }

    function clearSelection() {
        $$('.file-checkbox').forEach(cb => { cb.checked = false; });
        updateSelection();
    }

    document.addEventListener('change', e => {
        if (e.target.matches('.file-checkbox')) {
            updateSelection();
        } else if (e.target.id === 'selectAll') {
            visibleBoxes().forEach(cb => { cb.checked = e.target.checked; });
            updateSelection();
        }
    });

    /** Плавно убирает строки; если таблица опустела - перезапрашивает её (пустое состояние / пейджер) */
    function removeRows(ids) {
        ids.forEach(id => {
            const row = $(`tr[data-id="${CSS.escape(String(id))}"]`);
            if (!row) return;
            row.style.transition = 'opacity .35s';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                updateSelection();
                if (fileRows().length === 0) handleSearch();
            }, 350);
        });
    }

    // ── Client-side фильтры в шапке таблицы (имя / дата) ────
    function applyClientFilters() {
        const q    = ($('#nameFilter')?.value || '').trim().toLowerCase();
        const when = $('#dateFilter')?.value || '';
        const now  = new Date();
        let shown  = 0;

        fileRows().forEach(row => {
            let ok = true;
            if (q) ok = rowInfo(row).name.toLowerCase().includes(q);

            if (ok && when) {
                const d = new Date(String(row.dataset.uploadDate || '').replace(' ', 'T'));
                if (isNaN(d)) ok = false;
                else if (when === 'today') ok = d.toDateString() === now.toDateString();
                else if (when === 'week')  ok = (now - d) <= 7 * 86400000;
                else if (when === 'month') ok = d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
            }

            row.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });

        const tbody = $('#filesTableContainer tbody');
        let empty = $('#muFilterEmpty');
        if (tbody && fileRows().length > 0 && shown === 0) {
            if (!empty) {
                empty = document.createElement('tr');
                empty.id = 'muFilterEmpty';
                empty.innerHTML = `<td colspan="8" class="text-center text-body-secondary py-4">
                    <i class="fa-solid fa-filter-circle-xmark me-2"></i>No files on this page match the filter.</td>`;
                tbody.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
        updateSelection();
    }

    document.addEventListener('input', e => {
        if (e.target.id === 'nameFilter') applyClientFilters();
    });

    document.addEventListener('change', e => {
        if (e.target.id === 'dateFilter') {
            applyClientFilters();
        } else if (e.target.id === 'typeFilter') {
            // Серверный фильтр по привязке - полная перезагрузка с сохранением поиска
            const url = new URL(window.location.href);
            const search = ($('#searchInput')?.value || '').trim();
            e.target.value ? url.searchParams.set('type', e.target.value) : url.searchParams.delete('type');
            search ? url.searchParams.set('search', search) : url.searchParams.delete('search');
            url.searchParams.delete('page');
            url.searchParams.delete('ajax_search');
            window.location.href = url.toString();
        }
    });

    // ── AJAX search ─────────────────────────────────────────
    function handleSearch() {
        const container = $('#filesTableContainer');
        if (!container) return;

        const searchTerm = ($('#searchInput')?.value || '').trim();
        const url = new URL(window.location.href);
        searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
        url.searchParams.set('ajax_search', '1');
        url.searchParams.delete('page');

        container.innerHTML = `
            <div class="text-center py-5 my-4">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
                <p class="mt-3 text-body-secondary mb-0">Searching files…</p>
            </div>`;

        fetch(url.toString(), { credentials: 'same-origin' })
            .then(r => r.text())
            .then(html => {
                container.innerHTML = html;
                afterTableRender(searchTerm);
            })
            .catch(err => {
                console.error('Search error:', err);
                container.innerHTML = `
                    <div class="alert alert-danger m-4 d-flex align-items-center rounded-4">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Could not load results. Try again.
                    </div>`;
            });
    }
    window.handleSearch = handleSearch; // используется после загрузки файлов

    function afterTableRender(searchTerm) {
        // Ссылки пагинации должны сохранять текущий поиск, но без ajax_search
        $$('#filesTableContainer .pagination a').forEach(link => {
            try {
                const href = new URL(link.href);
                href.searchParams.delete('ajax_search');
                searchTerm ? href.searchParams.set('search', searchTerm) : href.searchParams.delete('search');
                link.href = href.toString();
            } catch (_) { /* не URL - пропускаем */ }
        });
        initTooltips($('#filesTableContainer'));
        applyClientFilters();
    }

    // Совместимость со старым кодом, который мог вызывать эту функцию
    window.initializeTableEvents = () => afterTableRender(($('#searchInput')?.value || '').trim());

    function initTooltips(root = document) {
        if (!window.bootstrap?.Tooltip) return;
        $$('[data-bs-toggle="tooltip"]', root).forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
    }

    // ── Page init ───────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initTooltips();
        updateSelection();

        const searchInput = $('#searchInput');
        if (searchInput) searchInput.addEventListener('input', debounce(handleSearch, 350));
        $('#searchForm')?.addEventListener('submit', e => { e.preventDefault(); handleSearch(); });

        // "Select files": выделить все видимые / снять выделение
        $('#bulkSelectBtn')?.addEventListener('click', () => {
            const boxes = visibleBoxes();
            const all = boxes.length > 0 && boxes.every(cb => cb.checked);
            boxes.forEach(cb => { cb.checked = !all; });
            updateSelection();
        });

        $('#cancelBulkAction')?.addEventListener('click', clearSelection);

        initBulkDelete();
        initSingleDelete();
        initUpload();
    });

    // ════════════════════ BULK DELETE ════════════════════
    function initBulkDelete() {
        const applyBtn   = $('#applyBulkAction');
        const modalEl    = $('#confirmBulkDeleteModal');
        const confirmBtn = $('#confirmBulkDelete');
        if (!applyBtn || !modalEl || !confirmBtn || !window.bootstrap) return;

        const modal       = bootstrap.Modal.getOrCreateInstance(modalEl);
        const grid        = $('#bulkPreviewGrid');
        const confirmHtml = confirmBtn.innerHTML; // содержит <span id="confirmCount">

        applyBtn.addEventListener('click', e => {
            e.preventDefault();

            const action = $('#bulkForm select[name="bulk_action"]')?.value || '';
            if (action !== 'delete') {
                notify('Choose an action first.', 'warning');
                return;
            }

            const rows = $$('.file-checkbox:checked').map(cb => cb.closest('tr')).filter(Boolean);
            if (rows.length === 0) {
                notify('Select at least one file.', 'warning');
                return;
            }

            const items = rows.map(rowInfo);
            const total = items.reduce((s, it) => s + it.size, 0);
            const n     = items.length;

            confirmBtn.innerHTML = confirmHtml;
            ['#filesCount', '#confirmCount', '#bulkSelectedCount'].forEach(sel => { const el = $(sel); if (el) el.textContent = n; });
            const summary = $('#selectedFilesSummary');
            if (summary) summary.textContent = `${n} file${n !== 1 ? 's' : ''} selected`;
            const sizeEl = $('#selectedFilesSize');
            if (sizeEl) sizeEl.textContent = 'Total size: ' + formatBytes(total);

            if (grid) {
                const LIMIT = 8;
                grid.innerHTML = items.slice(0, LIMIT).map(it => it.preview
                    ? `<div class="preview-item" title="${escapeHtml(it.name)}">
                           <img src="${escapeHtml(it.preview)}" alt="">
                           <div class="preview-overlay">${escapeHtml(shorten(it.name, 16))}</div>
                       </div>`
                    : `<div class="preview-item" title="${escapeHtml(it.name)}">
                           <div class="mu-tile"><i class="fa-solid fa-file fa-lg mb-1"></i><small>${escapeHtml(shorten(it.name, 12))}</small></div>
                       </div>`
                ).join('') + (n > LIMIT
                    ? `<div class="preview-item"><div class="mu-tile fw-bold">+${n - LIMIT}</div></div>`
                    : '');
            }

            modal.show();
        });

        confirmBtn.addEventListener('click', () => {
            const ids = $$('.file-checkbox:checked').map(cb => cb.value);
            if (ids.length === 0) { modal.hide(); return; }

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';

            const params = new URLSearchParams();
            params.append('bulk_action', 'delete');
            params.append('my_post_key', postKey());
            ids.forEach(id => params.append('selected_files[]', id));

            fetch('manage_uploads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params,
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        const deleted = data.deleted_ids || ids;
                        modal.hide();
                        clearSelection();
                        removeRows(deleted);
                        notify(`${deleted.length} file${deleted.length !== 1 ? 's' : ''} deleted.`, 'success');
                    } else {
                        notify(data.message || 'Could not delete the files.', 'error');
                    }
                })
                .catch(err => { console.error(err); notify('Server not responding.', 'error'); })
                .finally(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = confirmHtml;
                });
        });
    }

    // ════════════════════ SINGLE DELETE ════════════════════
    function initSingleDelete() {
        const modalEl    = $('#singleDeleteModal');
        const confirmBtn = $('#confirmSingleDeleteBtn');
        if (!modalEl || !confirmBtn || !window.bootstrap) return;

        const modal       = bootstrap.Modal.getOrCreateInstance(modalEl);
        const confirmHtml = confirmBtn.innerHTML;
        const img         = $('#singleDeleteImage');
        let current       = null;

        // Делегирование: работает и для строк, пришедших через AJAX-поиск
        document.addEventListener('click', e => {
            const link = e.target.closest('.btn-delete');
            if (!link) return;
            e.preventDefault();

            const row = link.closest('tr');
            if (!row) return;
            current = rowInfo(row);
            current.id = link.dataset.id || current.id;

            $('#singleDeleteFilename').textContent = current.name;
            $('#singleDeleteFileName').textContent = current.name;
            $('#singleDeleteFileInfo').innerHTML =
                `<i class="fa-solid fa-weight-hanging me-1"></i>${escapeHtml(formatBytes(current.size))}`;

            if (img) {
                img.style.display = current.preview ? 'block' : 'none';
                img.src = current.preview || '';
            }
            $('#singlePreviewContainer')?.classList.toggle('d-none', !current.preview);

            modal.show();
        });

        confirmBtn.addEventListener('click', () => {
            if (!current) return;

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting…';

            const id = current.id;
            const params = new URLSearchParams();
            params.append('delete', id);
            params.append('my_post_key', postKey());

            fetch('manage_uploads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params,
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        modal.hide();
                        const cb = $(`.file-checkbox[value="${CSS.escape(String(id))}"]`);
                        if (cb) cb.checked = false;
                        removeRows([id]);
                        notify('File deleted.', 'success');
                        current = null;
                    } else {
                        notify(data.message || 'Could not delete the file.', 'error');
                    }
                })
                .catch(() => notify('Server not responding.', 'error'))
                .finally(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = confirmHtml;
                });
        });
    }

    // ════════════════════ UPLOAD ════════════════════
    function initUpload() {
        const uploadModal = $('#uploadModal');
        const fileInput   = $('#fileUploadInput');
        const startBtn    = $('#startUploadBtn');
        if (!uploadModal || !fileInput || !startBtn) return;

        const dropArea       = $('#dropArea');
        const typeSelect     = $('#contentTypeSelect');
        const idInput        = $('#contentId');
        const listWrap       = $('#selectedFilesList');
        const listCount      = $('#selectedFilesCount');
        const listContainer  = $('#selectedFilesListContainer');
        const clearBtn       = $('#clearFilesBtn');
        const previewWrap    = $('#imagePreviewContainer');
        const previewList    = $('#imagePreviewList');
        const countBadge     = $('#fileCountBadge');
        const countBadgeText = $('#fileCount');
        const imageCountEl   = $('#imageCount');
        const pdfCountEl     = $('#pdfCount');
        const docCountEl     = $('#docCount');
        const sizeWarning    = $('#sizeWarning');
        const totalSizeEl    = $('#totalSize');
        const totalSizeDisp  = $('#totalSizeDisplay');
        const dropTitle      = $('#dropAreaTitle');
        const dropSubtitle   = $('#dropAreaSubtitle');
        const progressBar    = $('#uploadProgress');
        const progressWrap   = progressBar?.closest('.upload-progress');
        const startHtml      = startBtn.innerHTML;

        const MAX_TOTAL = 10 * 1024 * 1024; // 10 MB на всю пачку
        const ALLOWED   = ['application/pdf', 'application/msword',
                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

        let files = [];
        const key = f => `${f.name}|${f.size}|${f.lastModified}`;

        function kindOf(f) {
            if (f.type.startsWith('image/')) return 'image';
            if (f.type === 'application/pdf') return 'pdf';
            if (f.type.includes('msword') || f.type.includes('wordprocessingml')) return 'doc';
            return 'other';
        }
        const KIND_ICON = {
            image: 'fa-file-image text-primary',
            pdf:   'fa-file-pdf text-danger',
            doc:   'fa-file-word text-info',
            other: 'fa-file text-secondary'
        };

        function syncInput() {
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        function addFiles(list) {
            const known = new Set(files.map(key));
            Array.from(list).forEach(f => { if (!known.has(key(f))) { files.push(f); known.add(key(f)); } });
            syncInput();
            render();
        }

        function render() {
            const n = files.length;
            startBtn.disabled = n === 0;
            if (listWrap)   listWrap.style.display   = n ? 'block' : 'none';
            if (clearBtn)   clearBtn.style.display   = n ? 'inline-block' : 'none';
            if (countBadge) countBadge.style.display = n ? 'block' : 'none';
            if (countBadgeText) countBadgeText.textContent = n;
            if (listCount)  listCount.textContent = n;
            if (dropTitle)    dropTitle.textContent    = n ? `${n} file${n > 1 ? 's' : ''} selected` : 'Drag & drop files here';
            if (dropSubtitle) dropSubtitle.textContent = n ? 'Drop or click to add more' : 'or click to browse';

            // статистика
            const counts = { image: 0, pdf: 0, doc: 0, other: 0 };
            let total = 0;
            files.forEach(f => { counts[kindOf(f)]++; total += f.size; });
            if (imageCountEl) imageCountEl.textContent = counts.image;
            if (pdfCountEl)   pdfCountEl.textContent   = counts.pdf;
            if (docCountEl)   docCountEl.textContent   = counts.doc;
            if (totalSizeDisp) totalSizeDisp.textContent = formatBytes(total);
            if (sizeWarning) {
                sizeWarning.style.display = total > MAX_TOTAL * 0.8 ? 'inline-flex' : 'none';
                sizeWarning.classList.toggle('text-danger', total > MAX_TOTAL);
            }
            if (totalSizeEl) totalSizeEl.textContent = `${formatBytes(total)} of ${formatBytes(MAX_TOTAL)}`;

            // список
            if (listContainer) {
                listContainer.innerHTML = files.map((f, i) => `
                    <div class="list-group-item mu-upload-item">
                        <div class="d-flex align-items-center min-w-0">
                            <i class="fa-solid ${KIND_ICON[kindOf(f)]} me-2"></i>
                            <span class="small fw-medium text-truncate" title="${escapeHtml(f.name)}">${escapeHtml(shorten(f.name, 38))}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="mu-size-pill">${escapeHtml(formatBytes(f.size))}</span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-index="${i}" title="Remove">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </button>
                        </div>
                    </div>`).join('');
            }

            renderPreviews();
        }

        let previewToken = 0;
        function renderPreviews() {
            if (!previewWrap || !previewList) return;
            const token  = ++previewToken; // отменяет устаревший рендер при быстрых изменениях
            const images = files.map((f, i) => ({ f, i })).filter(x => x.f.type.startsWith('image/'));
            previewList.innerHTML = '';
            previewWrap.style.display = images.length ? 'block' : 'none';

            for (const { f, i } of images.slice(0, 6)) {
                const url = URL.createObjectURL(f);
                if (token !== previewToken) { URL.revokeObjectURL(url); return; }
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `
                    <img src="${url}" alt="">
                    <button type="button" class="remove-preview" data-remove-index="${i}" title="Remove">
                        <i class="fa-solid fa-xmark"></i>
                    </button>`;
                item.querySelector('img').addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
                previewList.appendChild(item);
            }
            if (images.length > 6) {
                const more = document.createElement('div');
                more.className = 'preview-item';
                more.innerHTML = `<div class="mu-tile fw-bold">+${images.length - 6}</div>`;
                previewList.appendChild(more);
            }
        }

        function removeAt(index) {
            if (index < 0 || index >= files.length) return;
            files.splice(index, 1);
            syncInput();
            render();
        }

        function reset() {
            files = [];
            fileInput.value = '';
            if (idInput) idInput.value = '';
            if (typeSelect) typeSelect.selectedIndex = 0;
            render();
            startBtn.innerHTML = startHtml;
            startBtn.disabled = true;
            if (progressWrap) progressWrap.style.display = 'none';
            if (progressBar)  progressBar.style.width = '0%';
        }

        // Глобальные функции - на них ссылаются onclick в разметке
        window.removeFile        = removeAt;
        window.removeFileByIndex = removeAt;
        window.clearSelectedFiles = () => { files = []; fileInput.value = ''; render(); };

        // Кнопки удаления в списке и превью
        uploadModal.addEventListener('click', e => {
            const btn = e.target.closest('[data-remove-index]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                removeAt(Number(btn.dataset.removeIndex));
            }
        });

        // Браузер кладёт в input только новый выбор - добавляем его к уже выбранным
        fileInput.addEventListener('change', e => addFiles(e.target.files));

        if (dropArea) {
            const stop = e => { e.preventDefault(); e.stopPropagation(); };
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => dropArea.addEventListener(ev, stop));
            ['dragenter', 'dragover'].forEach(ev => dropArea.addEventListener(ev, () => dropArea.classList.add('dragover')));
            ['dragleave', 'drop'].forEach(ev => dropArea.addEventListener(ev, () => dropArea.classList.remove('dragover')));
            dropArea.addEventListener('drop', e => addFiles(e.dataTransfer.files));

            // Клик по пустому месту зоны = Browse
            dropArea.addEventListener('click', e => {
                if (e.target.closest('button, .preview-item, input')) return;
                fileInput.click();
            });
        }

        startBtn.addEventListener('click', () => {
            if (files.length === 0) { notify('Select files to upload.', 'warning'); return; }

            const contentType = typeSelect?.value || '';
            const contentId   = (idInput?.value || '').trim();
            if (!contentType || typeSelect.selectedIndex === 0) { notify('Choose a content type.', 'warning'); return; }
            if (!contentId) { notify('Enter a content ID.', 'warning'); return; }

            const invalid = files.filter(f => !f.type.startsWith('image/') && !ALLOWED.includes(f.type)).map(f => f.name);
            const total   = files.reduce((s, f) => s + f.size, 0);
            if (invalid.length) { notify('Unsupported file type: ' + invalid.join(', '), 'error'); return; }
            if (total > MAX_TOTAL) { notify(`Total size is over ${formatBytes(MAX_TOTAL)}.`, 'error'); return; }

            const fd = new FormData();
            files.forEach(f => fd.append('files[]', f));
            fd.append('content_type', contentType);
            fd.append('content_id', contentId);
            fd.append('my_post_key', postKey());

            startBtn.disabled = true;
            startBtn.querySelector('.upload-text')?.classList.add('d-none');
            startBtn.querySelector('.upload-loading')?.classList.remove('d-none');

            // Реальный прогресс через XHR
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_handler.php');
            if (progressWrap) progressWrap.style.display = 'block';
            if (progressBar)  progressBar.style.width = '0%';

            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable && progressBar) progressBar.style.width = Math.round(e.loaded / e.total * 100) + '%';
            });

            const done = () => {
                startBtn.disabled = files.length === 0;
                startBtn.querySelector('.upload-text')?.classList.remove('d-none');
                startBtn.querySelector('.upload-loading')?.classList.add('d-none');
            };

            xhr.onload = () => {
                let data = null;
                try { data = JSON.parse(xhr.responseText); } catch (_) { /* не JSON */ }
                done();
                if (data && data.success) {
                    if (progressBar) progressBar.style.width = '100%';
                    notify(data.message || 'Files uploaded.', 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(uploadModal)?.hide();
                        handleSearch();
                    }, 900);
                } else {
                    if (progressWrap) progressWrap.style.display = 'none';
                    notify(data?.message || 'Upload failed.', 'error');
                }
            };
            xhr.onerror = () => {
                done();
                if (progressWrap) progressWrap.style.display = 'none';
                notify('Server not responding.', 'error');
            };
            xhr.send(fd);
        });

        uploadModal.addEventListener('hidden.bs.modal', reset);
        render();
    }
})();