// Debounce function
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

// Handle search
function handleSearch() {
    const searchTerm = document.getElementById('searchInput').value.trim();
    const url = new URL(window.location.href);
    
    // Update URL parameters
    url.searchParams.set('search', searchTerm);
    url.searchParams.set('ajax_search', '1');
    url.searchParams.delete('page'); // Reset to first page on new search
    
    // Show loading indicator
    document.getElementById('filesTableContainer').innerHTML = `
        <div class="text-center py-5 my-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Searching files...</p>
        </div>
    `;
    
    // Fetch results
    fetch(url.toString())
        .then(response => response.text())
        .then(html => {
            document.getElementById('filesTableContainer').innerHTML = html;
            // Reinitialize event handlers
            initializeTableEvents();
            updatePaginationLinks(searchTerm);
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('filesTableContainer').innerHTML = `
                <div class="alert alert-danger mx-4 my-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Error loading results. Please try again.
                </div>
            `;
        });
}

// Update pagination links after search
function updatePaginationLinks(searchTerm) {
    document.querySelectorAll('.pagination a').forEach(link => {
        const href = new URL(link.href);
        if (searchTerm) {
            href.searchParams.set('search', searchTerm);
        } else {
            href.searchParams.delete('search');
        }
        link.href = href.toString();
    });
}

// Initialize table event handlers
function initializeTableEvents() {
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editId').value = this.dataset.id;
            document.getElementById('editFileName').value = this.dataset.fileName || '';
            document.getElementById('editCommentId').value = this.dataset.commentId || '';
            document.getElementById('editNewsId').value = this.dataset.newsId || '';
            document.getElementById('editTorrentId').value = this.dataset.torrentId || '';
            
			 document.getElementById('editPostId').value = this.dataset.postId || '';
			
			
			
			document.getElementById('editUserId').value = this.dataset.userId || '';
            document.getElementById('editDescription').value = this.dataset.description || '';
        });
    });
    
    // Bulk selection
const selectAll = document.getElementById('selectAll');
const fileCheckboxes = document.querySelectorAll('.file-checkbox');
const applyBtn = document.getElementById('applyBulkAction');
const selectedCountSpan = document.getElementById('selectedCount');
const selectedFilesInput = document.getElementById('selectedFilesInput');
const bulkActions = document.getElementById('bulkActions');

function updateSelection() {
    // Get all checked checkboxes
    const checked = document.querySelectorAll('.file-checkbox:checked');
    const selectedFiles = Array.from(checked).map(cb => cb.value);
    const count = selectedFiles.length;
    
    // Update counter
    if (selectedCountSpan) {
        selectedCountSpan.textContent = count;
    }
    
    // Update hidden input
    if (selectedFilesInput) {
        selectedFilesInput.value = selectedFiles.join(',');
    }
    
    // Update select all checkbox
    if (selectAll) {
        selectAll.checked = count === fileCheckboxes.length && count > 0;
    }
    
    // Show/hide bulk actions panel
    if (bulkActions) {
        if (count > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
    }
    
    // Enable/disable apply button
    if (applyBtn) {
        applyBtn.disabled = count === 0;
        console.log('Apply button disabled:', applyBtn.disabled, 'Count:', count); // Для отладки
    }
}

// Add event listeners to checkboxes
if (fileCheckboxes.length > 0) {
    fileCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });
}

// Select All functionality
if (selectAll) {
    selectAll.addEventListener('change', (e) => {
        fileCheckboxes.forEach(cb => {
            cb.checked = e.target.checked;
        });
        updateSelection();
    });
}

// Cancel button
const cancelBulkAction = document.getElementById('cancelBulkAction');
if (cancelBulkAction) {
    cancelBulkAction.addEventListener('click', () => {
        fileCheckboxes.forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        updateSelection();
    });
}

// Initial update
updateSelection();
    
    if (selectAll) {
        selectAll.addEventListener('change', (e) => {
            fileCheckboxes.forEach(cb => cb.checked = e.target.checked);
            updateSelection();
        });
    }
    
    fileCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });
    
   
}





// Функция обновления состояния кнопки Apply Action
function updateApplyButtonState() {
    const applyBtn = document.getElementById('applyBulkAction');
    const selectedCount = document.querySelectorAll('.file-checkbox:checked').length;
    if (applyBtn) {
        applyBtn.disabled = selectedCount === 0;
    }
}

// Добавь вызов этой функции в updateSelection
function updateSelection() {
    const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
        .map(cb => cb.value);
    document.getElementById('selectedCount').textContent = selectedFiles.length;
    document.getElementById('selectedFilesInput').value = selectedFiles.join(',');
    
    const selectAll = document.getElementById('selectAll');
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    if (selectAll) {
        selectAll.checked = selectedFiles.length === fileCheckboxes.length && selectedFiles.length > 0;
    }
    
    // Show/hide bulk actions
    const bulkActions = document.getElementById('bulkActions');
    if (bulkActions) {
        if (selectedFiles.length > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
    }
    
    // Update apply button state
    updateApplyButtonState();
}








// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    initializeTableEvents();
    
    // Delayed search
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    const debouncedSearch = debounce(handleSearch, 350);
    
    if (searchInput) {
        searchInput.addEventListener('input', debouncedSearch);
        searchInput.focus();
    }
    
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleSearch();
        });
    }
    
    // Bulk actions
    const bulkSelectBtn = document.getElementById('bulkSelectBtn');
    const cancelBulkAction = document.getElementById('cancelBulkAction');
    const bulkActions = document.getElementById('bulkActions');
    
    if (bulkSelectBtn) {
        bulkSelectBtn.addEventListener('click', () => {
            // Toggle all checkboxes
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            
            // Trigger change event to update selection
            checkboxes.forEach(cb => {
                const event = new Event('change');
                cb.dispatchEvent(event);
            });
        });
    }
    
    if (cancelBulkAction && bulkActions) {
        cancelBulkAction.addEventListener('click', () => {
            bulkActions.classList.remove('show');
            document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectedCount').textContent = '0';
            document.getElementById('selectedFilesInput').value = '';
            if (document.getElementById('selectAll')) {
                document.getElementById('selectAll').checked = false;
            }
        });
    }
    
    // File upload drag and drop
    const dropArea = document.querySelector('.border-dashed');
    if (dropArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('border-primary', 'bg-primary-soft');
        }
        
        function unhighlight() {
            dropArea.classList.remove('border-primary', 'bg-primary-soft');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            console.log('Files dropped:', files);
            // Handle file upload here
        }
    }
});






// ============ BULK DELETE MODAL FUNCTIONALITY ============
document.addEventListener('DOMContentLoaded', function() {
    const applyBtn = document.getElementById('applyBulkAction');
    const modalElement = document.getElementById('confirmBulkDeleteModal');
    const filesCountSpan = document.getElementById('filesCount');
    const confirmCountSpan = document.getElementById('confirmCount');
    const selectedFilesSummary = document.getElementById('selectedFilesSummary');
    const selectedFilesSize = document.getElementById('selectedFilesSize');
    const selectedFilesCount = document.getElementById('selectedFilesCount');
    const bulkPreviewGrid = document.getElementById('bulkPreviewGrid');
    const confirmDeleteBtn = document.getElementById('confirmBulkDelete');
    
    if (!applyBtn || !modalElement || !confirmDeleteBtn) {
        console.log('Bulk delete elements not found');
        return;
    }
    
    const bulkModal = new bootstrap.Modal(modalElement);
    
    // Open modal when Apply Action is clicked
    applyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        const selectedCount = checkboxes.length;
        
        if (selectedCount === 0) {
            showToast('Please select at least one file to delete.', 'warning');
            return;
        }
        
        // Update counters
        if (filesCountSpan) filesCountSpan.textContent = selectedCount;
        if (confirmCountSpan) confirmCountSpan.textContent = selectedCount;
        if (selectedFilesCount) selectedFilesCount.textContent = selectedCount;
        if (selectedFilesSummary) selectedFilesSummary.textContent = `${selectedCount} file${selectedCount !== 1 ? 's' : ''} selected`;
        
        // Calculate total size and collect previews
        let totalSize = 0;
        let previews = [];
        let fileNames = [];
        
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row) {
                // Get file name
                const fileNameEl = row.querySelector('.fw-semibold');
                if (fileNameEl) {
                    fileNames.push(fileNameEl.textContent.trim());
                }
                
                // Get file size
                const sizeEl = row.querySelector('.text-muted.small i.bi-hdd')?.parentElement;
                if (sizeEl) {
                    const sizeText = sizeEl.textContent;
                    const sizeMatch = sizeText.match(/[\d.]+/);
                    if (sizeMatch) {
                        const sizeValue = parseFloat(sizeMatch[0]);
                        if (sizeText.includes('KB')) totalSize += sizeValue * 1024;
                        else if (sizeText.includes('MB')) totalSize += sizeValue * 1024 * 1024;
                        else if (sizeText.includes('GB')) totalSize += sizeValue * 1024 * 1024 * 1024;
                        else totalSize += sizeValue;
                    }
                }
                
                // Get preview image
                const previewImg = row.querySelector('.img-preview');
                if (previewImg && previewImg.src) {
                    previews.push({
                        src: previewImg.src,
                        name: fileNameEl?.textContent?.trim() || 'Image'
                    });
                }
            }
        });
        
        // Update total size display
        if (selectedFilesSize) {
            if (totalSize > 0) {
                if (totalSize < 1024) selectedFilesSize.textContent = `Total size: ${totalSize} B`;
                else if (totalSize < 1024 * 1024) selectedFilesSize.textContent = `Total size: ${(totalSize / 1024).toFixed(2)} KB`;
                else if (totalSize < 1024 * 1024 * 1024) selectedFilesSize.textContent = `Total size: ${(totalSize / (1024 * 1024)).toFixed(2)} MB`;
                else selectedFilesSize.textContent = `Total size: ${(totalSize / (1024 * 1024 * 1024)).toFixed(2)} GB`;
            } else {
                selectedFilesSize.textContent = 'Total size: 0 MB';
            }
        }
        
        // Show previews or file icons
        if (bulkPreviewGrid) {
            if (previews.length > 0) {
                bulkPreviewGrid.innerHTML = '';
                previews.slice(0, 4).forEach((preview, index) => {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'preview-item';
                    previewDiv.innerHTML = `
                        <img src="${preview.src}" alt="${preview.name}" onerror="this.src='/assets/img/placeholder-image.jpg'">
                        <div class="preview-overlay">${preview.name.substring(0, 15)}${preview.name.length > 15 ? '...' : ''}</div>
                       `;
                    bulkPreviewGrid.appendChild(previewDiv);
                });
                
                if (previews.length > 4) {
                    const moreDiv = document.createElement('div');
                    moreDiv.className = 'preview-item';
                    moreDiv.innerHTML = `
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#e9ecef; border-radius:6px;">
                            <span class="fw-bold text-muted">+${previews.length - 4}</span>
                        </div>
                    `;
                    bulkPreviewGrid.appendChild(moreDiv);
                }
            } else if (fileNames.length > 0) {
                bulkPreviewGrid.innerHTML = '';
                fileNames.slice(0, 4).forEach((name, index) => {
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'preview-item';
                    fileDiv.innerHTML = `
                        <div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#e9ecef; border-radius:6px; padding:5px; text-align:center;">
                            <i class="fas fa-file fa-2x text-muted mb-1"></i>
                            <small class="text-truncate" style="max-width:70px;" title="${name}">${name.length > 15 ? name.substring(0, 12) + '...' : name}</small>
                        </div>
                        <div class="preview-index">${index + 1}</div>
                    `;
                    bulkPreviewGrid.appendChild(fileDiv);
                });
                
                if (fileNames.length > 4) {
                    const moreDiv = document.createElement('div');
                    moreDiv.className = 'preview-item';
                    moreDiv.innerHTML = `
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#e9ecef; border-radius:6px;">
                            <span class="fw-bold text-muted">+${fileNames.length - 4}</span>
                        </div>
                    `;
                    bulkPreviewGrid.appendChild(moreDiv);
                }
            } else {
                bulkPreviewGrid.innerHTML = '<div class="preview-placeholder text-center py-4 text-muted"><i class="fas fa-images fa-2x mb-2"></i><div class="small">No previews available</div></div>';
            }
        }
        
        bulkModal.show();
    });
    
    // Handle confirm delete
    confirmDeleteBtn.addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        const selectedIds = Array.from(checkboxes).map(cb => cb.value);
        
        if (selectedIds.length === 0) {
            bulkModal.hide();
            return;
        }
        
        // Disable button
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        
        // Prepare data
        const params = new URLSearchParams();
        params.append('bulk_action', 'delete');
        const csrfToken = document.querySelector('#bulkForm input[name="my_post_key"]')?.value || '';
        params.append('my_post_key', csrfToken);
        selectedIds.forEach(id => params.append('selected_files[]', id));
        
        // Send request
        fetch('manage_uploads.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Remove rows
                data.deleted_ids.forEach(id => {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.4s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 400);
                    }
                });
                
                // Reset selection
                document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
                if (document.getElementById('selectAll')) {
                    document.getElementById('selectAll').checked = false;
                }
                document.getElementById('selectedCount').textContent = '0';
                document.getElementById('selectedFilesInput').value = '';
                
                // Hide modal
                bulkModal.hide();
                
                // Hide bulk actions panel
                const bulkActions = document.getElementById('bulkActions');
                if (bulkActions) {
                    bulkActions.classList.remove('show');
                }
                
                // Show success toast
                showToast(`${selectedIds.length} file${selectedIds.length !== 1 ? 's' : ''} deleted successfully.`, 'success');
            } else {
                showToast(data.message || 'Failed to delete files.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Server not responding.', 'error');
        })
        .finally(() => {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Yes, Delete Files';
        });
    });
});

















// ============ SINGLE DELETE MODAL FUNCTIONALITY ============
document.addEventListener('DOMContentLoaded', function() {
    const singleDeleteLinks = document.querySelectorAll('.btn-delete');
    const singleModalElement = document.getElementById('singleDeleteModal');
    const confirmSingleBtn = document.getElementById('confirmSingleDeleteBtn');
    const singleDeleteImage = document.getElementById('singleDeleteImage');
    const singleDeleteFilename = document.getElementById('singleDeleteFilename');
    const singleDeleteFileName = document.getElementById('singleDeleteFileName');
    const singleDeleteFileInfo = document.getElementById('singleDeleteFileInfo');
    
    if (!singleModalElement || !confirmSingleBtn) return;
    
    let currentFileId = null;
    let currentRow = null;
    const singleModal = new bootstrap.Modal(singleModalElement);
    
    singleDeleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const row = this.closest('tr');
            if (!row) return;
            
            currentRow = row;
            currentFileId = this.dataset.id;
            
            // Get file details
            const fileNameEl = row.querySelector('.fw-semibold');
            const fileName = fileNameEl ? fileNameEl.textContent.trim() : 'Unknown file';
            
            // Get file size
            let fileSize = 'Unknown';
            const sizeEl = row.querySelector('.text-muted.small i.bi-hdd')?.parentElement;
            if (sizeEl) {
                fileSize = sizeEl.textContent.trim();
            }
            
            // Update modal text
            singleDeleteFilename.textContent = fileName;
            singleDeleteFileName.textContent = fileName;
            singleDeleteFileInfo.innerHTML = `<i class="fas fa-file me-1"></i> ${fileName} · ${fileSize}`;
            
            // Check for preview image
            const previewImg = row.querySelector('.img-preview');
            if (previewImg && previewImg.src) {
                singleDeleteImage.src = previewImg.src;
                singleDeleteImage.style.display = 'block';
            } else {
                singleDeleteImage.style.display = 'none';
            }
            
            singleModal.show();
        });
    });
    
    // Handle confirm delete
    confirmSingleBtn.addEventListener('click', function() {
        if (!currentFileId || !currentRow) return;
        
        confirmSingleBtn.disabled = true;
        confirmSingleBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        
        const csrfToken = document.querySelector('#bulkForm input[name="my_post_key"]')?.value || '';
        fetch('manage_uploads.php?delete=' + currentFileId + '&my_post_key=' + encodeURIComponent(csrfToken))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (currentRow && currentRow.parentNode) {
                        currentRow.style.transition = 'opacity 0.4s';
                        currentRow.style.opacity = '0';
                        setTimeout(() => {
                            if (currentRow && currentRow.parentNode) {
                                currentRow.remove();
                                
                                // Обновляем интерфейс
                                const checkbox = document.querySelector(`.file-checkbox[value="${currentFileId}"]`);
                                if (checkbox) checkbox.checked = false;
                                
                                const selectedCount = document.querySelectorAll('.file-checkbox:checked').length;
                                const selectedCountSpan = document.getElementById('selectedCount');
                                if (selectedCountSpan) {
                                    selectedCountSpan.textContent = selectedCount;
                                }
                                
                                const selectAll = document.getElementById('selectAll');
                                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                                if (selectAll) {
                                    selectAll.checked = selectedCount === fileCheckboxes.length && selectedCount > 0;
                                }
                                
                                const applyBtn = document.getElementById('applyBulkAction');
                                if (applyBtn) {
                                    applyBtn.disabled = selectedCount === 0;
                                }
                                
                                const bulkActions = document.getElementById('bulkActions');
                                if (bulkActions) {
                                    if (selectedCount > 0) {
                                        bulkActions.classList.add('show');
                                    } else {
                                        bulkActions.classList.remove('show');
                                    }
                                }
                                
                                const selectedFilesInput = document.getElementById('selectedFilesInput');
                                if (selectedFilesInput) {
                                    const selectedIds = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
                                    selectedFilesInput.value = selectedIds.join(',');
                                }
                            }
                        }, 400);
                    }
                    
                    singleModal.hide();
                    showToast('File deleted successfully.', 'success');
                } else {
                    showToast(data.message || 'Failed to delete file.', 'error');
                }
            })
            .catch(() => showToast('Server not responding.', 'error'))
            .finally(() => {
                confirmSingleBtn.disabled = false;
                confirmSingleBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Yes, Delete';
            });
    });
});




// Upload Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    const uploadModal = document.getElementById('uploadModal');
    const fileInput = document.getElementById('fileUploadInput');
    const startUploadBtn = document.getElementById('startUploadBtn');
    const dropArea = document.querySelector('.upload-area');
    const contentTypeSelect = document.getElementById('contentTypeSelect');
    const contentIdInput = document.getElementById('contentId');
    const selectedFilesDiv = document.getElementById('selectedFilesList');
    const selectedFilesCount = document.getElementById('selectedFilesCount');
    const selectedFilesContainer = document.getElementById('selectedFilesListContainer');
    const clearFilesBtn = document.getElementById('clearFilesBtn');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreviewList = document.getElementById('imagePreviewList');
    const fileCountBadge = document.getElementById('fileCountBadge');
    const fileCount = document.getElementById('fileCount');
    const imageCount = document.getElementById('imageCount');
    const pdfCount = document.getElementById('pdfCount');
    const docCount = document.getElementById('docCount');
    const totalSizeDisplay = document.getElementById('totalSizeDisplay');
    const dropAreaTitle = document.getElementById('dropAreaTitle');
    const dropAreaSubtitle = document.getElementById('dropAreaSubtitle');
    const uploadProgress = document.getElementById('uploadProgress');
    
    if (!uploadModal || !startUploadBtn) {
        console.log('Upload modal elements not found');
        return;
    }
    
    let selectedFiles = [];
    
    // Функция отображения выбранных файлов
    function displaySelectedFiles() {
        if (selectedFiles.length > 0) {
            selectedFilesDiv.style.display = 'block';
            selectedFilesCount.textContent = selectedFiles.length;
            startUploadBtn.disabled = false;
            if (clearFilesBtn) clearFilesBtn.style.display = 'inline-block';
            
            // Обновляем заголовок
            dropAreaTitle.textContent = `${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} selected`;
            dropAreaSubtitle.textContent = 'Click to add more files';
            
            // Обновляем счетчик файлов
            if (fileCountBadge) {
                fileCountBadge.style.display = 'block';
                fileCount.textContent = selectedFiles.length;
            }
            
            // Обновляем статистику
            updateFileStats();
            
            // Показываем превью для изображений
            showImagePreviews();
            
            // Список файлов
            let html = '';
            selectedFiles.forEach((file, index) => {
                const size = (file.size / 1024).toFixed(2);
                const fileType = file.type.includes('image') ? 'image' : 
                                file.type.includes('pdf') ? 'pdf' : 'text';
                const iconColor = fileType === 'image' ? 'text-primary' : 
                                  fileType === 'pdf' ? 'text-danger' : 'text-info';
                
                html += `
                    <div class="list-group-item file-item d-flex justify-content-between align-items-center py-2 px-3 border-0 bg-light mb-1 rounded-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-file-earmark-${fileType} ${iconColor} me-2"></i>
                            <span class="small fw-medium">${truncateFileName(file.name, 30)}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-dark px-3 py-2 rounded-pill">${size} KB</span>
                            <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(${index})">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            selectedFilesContainer.innerHTML = html;
        } else {
            selectedFilesDiv.style.display = 'none';
            startUploadBtn.disabled = true;
            if (clearFilesBtn) clearFilesBtn.style.display = 'none';
            if (fileCountBadge) fileCountBadge.style.display = 'none';
            if (imagePreviewContainer) imagePreviewContainer.style.display = 'none';
            
            // Сбрасываем заголовок
            dropAreaTitle.textContent = 'Drag & drop files here';
            dropAreaSubtitle.textContent = 'or click to browse';
            
            // Сбрасываем статистику
            resetFileStats();
        }
    }
    
    // Функция обновления статистики
    function updateFileStats() {
        let imgCount = 0, pdfCount = 0, docCount = 0;
        let totalSize = 0;
        
        selectedFiles.forEach(file => {
            totalSize += file.size;
            if (file.type.startsWith('image/')) imgCount++;
            else if (file.type === 'application/pdf') pdfCount++;
            else if (file.type.includes('document') || file.type.includes('msword')) docCount++;
        });
        
        if (imageCount) imageCount.textContent = imgCount;
        if (pdfCount) pdfCount.textContent = pdfCount;
        if (docCount) docCount.textContent = docCount;
        
        const totalKB = (totalSize / 1024).toFixed(2);
        const totalMB = (totalSize / (1024 * 1024)).toFixed(2);
        if (totalSizeDisplay) {
            totalSizeDisplay.textContent = totalMB > 1 ? `${totalMB} MB` : `${totalKB} KB`;
        }
    }
    
    // Функция сброса статистики
    function resetFileStats() {
        if (imageCount) imageCount.textContent = '0';
        if (pdfCount) pdfCount.textContent = '0';
        if (docCount) docCount.textContent = '0';
        if (totalSizeDisplay) totalSizeDisplay.textContent = '0 KB';
    }
    
    // Функция показа превью изображений


// Функция показа превью изображений
function showImagePreviews() {
    if (!imagePreviewContainer || !imagePreviewList) return;
    
    // Очищаем контейнер перед добавлением новых превью
    imagePreviewList.innerHTML = '';
    
    const imageFiles = selectedFiles.filter(file => file.type.startsWith('image/'));
    
    if (imageFiles.length > 0) {
        imagePreviewContainer.style.display = 'block';
        
        // Используем асинхронный подход с Promise для правильной загрузки
        const loadPreviews = async () => {
            for (let i = 0; i < Math.min(imageFiles.length, 5); i++) {
                const file = imageFiles[i];
                try {
                    const dataUrl = await readFileAsDataURL(file);
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    previewItem.dataset.index = i;
                    previewItem.innerHTML = `
                        <img src="${dataUrl}" alt="Preview">
                        <div class="remove-preview" onclick="removeFileByIndex(${i})">
                            <i class="bi bi-x"></i>
                        </div>
                    `;
                    imagePreviewList.appendChild(previewItem);
                } catch (error) {
                    console.error('Error loading preview:', error);
                }
            }
            
            if (imageFiles.length > 5) {
                const moreDiv = document.createElement('div');
                moreDiv.className = 'preview-item bg-light d-flex align-items-center justify-content-center';
                moreDiv.innerHTML = `<span class="fw-bold text-muted">+${imageFiles.length - 5}</span>`;
                imagePreviewList.appendChild(moreDiv);
            }
        };
        
        loadPreviews();
    } else {
        imagePreviewContainer.style.display = 'none';
    }
}

// Вспомогательная функция для чтения файла
function readFileAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

// Новая функция для удаления по индексу
window.removeFileByIndex = function(index) {
    // Находим реальный индекс в общем массиве
    const imageFiles = selectedFiles.filter(file => file.type.startsWith('image/'));
    if (index < 0 || index >= imageFiles.length) return;
    
    // Находим этот файл в общем массиве по уникальным свойствам
    const fileToRemove = imageFiles[index];
    const realIndex = selectedFiles.findIndex(f => 
        f.name === fileToRemove.name && 
        f.size === fileToRemove.size && 
        f.lastModified === fileToRemove.lastModified
    );
    
    if (realIndex !== -1) {
        removeFile(realIndex);
    }
};



    
    // Функция удаления файла из списка
   window.removeFile = function(index) {
    if (index < 0 || index >= selectedFiles.length) return;
    
    selectedFiles.splice(index, 1);
    
    // Обновляем input
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
    
    // Просто вызываем displaySelectedFiles, он сам очистит и пересоздаст всё
    displaySelectedFiles();
};
    
    // Функция очистки всех файлов
    window.clearSelectedFiles = function() {
        selectedFiles = [];
        fileInput.value = '';
        displaySelectedFiles();
    };
    
    // Функция обрезки имени файла
    function truncateFileName(name, maxLength) {
        if (name.length <= maxLength) return name;
        const ext = name.split('.').pop();
        const nameWithoutExt = name.slice(0, -(ext.length + 1));
        return nameWithoutExt.slice(0, maxLength - 3 - ext.length) + '...' + ext;
    }
    
    // Handle file selection via button
    fileInput.addEventListener('change', function(e) {
        selectedFiles = Array.from(e.target.files);
        console.log('Selected files:', selectedFiles.length);
        displaySelectedFiles();
    });
    
    // Drag and drop functionality
    if (dropArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('dragover');
        }
        
        function unhighlight() {
            dropArea.classList.remove('dragover');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            selectedFiles = Array.from(dt.files);
            fileInput.files = dt.files;
            console.log('Dropped files:', selectedFiles.length);
            displaySelectedFiles();
        }
    }
    
    // Handle upload button click
    startUploadBtn.addEventListener('click', function() {
        console.log('Upload button clicked');
        
        // Check if files are selected
        if (selectedFiles.length === 0) {
            showToast('Please select files to upload.', 'warning');
            return;
        }
        
        // Check content type and ID
        const contentType = contentTypeSelect ? contentTypeSelect.value : '';
        const contentId = contentIdInput ? contentIdInput.value.trim() : '';
        
        console.log('Content type:', contentType);
        console.log('Content ID:', contentId);
        
        if (!contentType) {
            showToast('Please select content type.', 'warning');
            return;
        }
        
        if (!contentId) {
            showToast('Please enter content ID.', 'warning');
            return;
        }
        
        // Validate file types and sizes
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 10 * 1024 * 1024; // 10 MB
        
        let invalidFiles = [];
        let oversizedFiles = [];
        let totalSize = 0;
        
        selectedFiles.forEach(file => {
            totalSize += file.size;
            if (!allowedTypes.includes(file.type) && !file.type.startsWith('image/')) {
                invalidFiles.push(file.name);
            }
            if (file.size > maxSize) {
                oversizedFiles.push(file.name);
            }
        });
        
        if (totalSize > 10 * 1024 * 1024) {
            showToast('Total file size exceeds 10 MB limit.', 'error');
            return;
        }
        
        if (invalidFiles.length > 0) {
            showToast(`Invalid file types: ${invalidFiles.join(', ')}`, 'error');
            return;
        }
        
        if (oversizedFiles.length > 0) {
            showToast(`Files too large (max 10MB each): ${oversizedFiles.join(', ')}`, 'error');
            return;
        }
        
        // Create FormData
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('my_post_key', document.querySelector('#bulkForm input[name="my_post_key"]')?.value || '');
        
        // Disable button and show loading
        startUploadBtn.disabled = true;
        startUploadBtn.classList.add('btn-loading');
        startUploadBtn.querySelector('.upload-text').classList.add('d-none');
        startUploadBtn.querySelector('.upload-loading').classList.remove('d-none');
        
        // Show progress bar
        if (uploadProgress) {
            uploadProgress.parentElement.parentElement.style.display = 'block';
            uploadProgress.style.width = '0%';
        }
        
        console.log('Sending upload request...');
        
        // Simulate progress (you can remove this in production)
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            if (uploadProgress && progress <= 90) {
                uploadProgress.style.width = progress + '%';
            }
        }, 300);
        
        // Send upload request
        fetch('upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            console.log('Upload response:', data);
            
            if (uploadProgress) {
                uploadProgress.style.width = '100%';
            }
            
            if (data.success) {
                showToast(data.message, 'success');
                
                // Reset form
                selectedFiles = [];
                fileInput.value = '';
                if (contentIdInput) contentIdInput.value = '';
                if (contentTypeSelect) contentTypeSelect.value = '';
                displaySelectedFiles();
                
                // Close modal after delay
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(uploadModal);
                    if (modal) modal.hide();
                    
                    // Refresh file list
                    if (typeof handleSearch === 'function') {
                        handleSearch();
                    } else {
                        location.reload();
                    }
                }, 1500);
            } else {
                showToast(data.message || 'Upload failed.', 'error');
                if (uploadProgress) {
                    uploadProgress.parentElement.parentElement.style.display = 'none';
                }
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            console.error('Upload error:', error);
            showToast('Server not responding.', 'error');
            if (uploadProgress) {
                uploadProgress.parentElement.parentElement.style.display = 'none';
            }
        })
        .finally(() => {
            startUploadBtn.disabled = false;
            startUploadBtn.classList.remove('btn-loading');
            startUploadBtn.querySelector('.upload-text').classList.remove('d-none');
            startUploadBtn.querySelector('.upload-loading').classList.add('d-none');
        });
    });
    
    // Reset on modal close
    uploadModal.addEventListener('hidden.bs.modal', function() {
        selectedFiles = [];
        fileInput.value = '';
        if (contentIdInput) contentIdInput.value = '';
        if (contentTypeSelect) contentTypeSelect.value = '';
        displaySelectedFiles();
        startUploadBtn.disabled = true;
        startUploadBtn.innerHTML = '<span class="upload-text"><i class="bi bi-cloud-upload me-2"></i>Upload Now</span><span class="upload-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Uploading...</span>';
        
        if (uploadProgress) {
            uploadProgress.parentElement.parentElement.style.display = 'none';
            uploadProgress.style.width = '0%';
        }
    });
});