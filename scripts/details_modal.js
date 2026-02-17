document.addEventListener('DOMContentLoaded', function () {
    // Modal elements
    const modalElements = {
        modal: document.getElementById('universalImageModal'),
        img: document.getElementById('universalImagePreview'),
        title: document.getElementById('universalImageModalTitle'),
        size: document.getElementById('universalImageSize'),
        dimensions: document.getElementById('universalImageDimensions'),
        downloadBtn: document.getElementById('universalDownloadBtn'),
        fullscreenBtn: document.getElementById('universalFullscreenBtn'),
        loadingSpinner: document.getElementById('imageLoadingSpinner'),
        errorMessage: document.getElementById('imageErrorMessage'),
        zoomLevel: document.getElementById('zoomLevel'),
        zoomInBtn: document.getElementById('zoomInBtn'),
        zoomOutBtn: document.getElementById('zoomOutBtn'),
        rotateBtn: document.getElementById('rotateBtn'),
        imageInfo: document.getElementById('imageInfo')
    };

    let currentZoom = 1;
    let currentRotation = 0;
    let currentImageSrc = '';

    // File size formatting function
    function formatFileSize(bytes) {
        if (!bytes) return '—';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        return size.toFixed(1) + ' ' + units[unitIndex];
    }

    // Reset transformations
    function resetTransformations() {
        currentZoom = 1;
        currentRotation = 0;
        updateImageTransform();
        if (modalElements.zoomLevel) {
            modalElements.zoomLevel.textContent = '100%';
        }
    }

    // Update image transformation
    function updateImageTransform() {
        modalElements.img.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
    }

    // Show loading spinner
    function showLoading(show = true) {
        if (modalElements.loadingSpinner) {
            modalElements.loadingSpinner.style.display = show ? 'flex' : 'none';
        }
        if (modalElements.errorMessage) {
            modalElements.errorMessage.style.display = 'none';
        }
        modalElements.img.style.opacity = show ? '0.3' : '1';
    }

    // Show error message
    function showError(message) {
        if (modalElements.loadingSpinner) {
            modalElements.loadingSpinner.style.display = 'none';
        }
        if (modalElements.errorMessage) {
            modalElements.errorMessage.style.display = 'block';
            modalElements.errorMessage.querySelector('span').textContent = message;
        }
        modalElements.img.style.opacity = '0.3';
    }

    // Modal open handler
    modalElements.modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const imgSrc = trigger.getAttribute('data-img-src');
        const imgTitle = trigger.getAttribute('data-title') || 'Image Preview';
        
        currentImageSrc = imgSrc;
        
        // Reset transformations
        resetTransformations();
        
        // Show loading spinner
        showLoading(true);
        
        // Set title with icon
        modalElements.title.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-image-circle text-primary me-2" style="font-size: 1.5rem;"></i>
                <div>
                    <h5 class="mb-0">${imgTitle}</h5>
                    <small class="text-muted">Image Preview</small>
                </div>
            </div>
        `;
        
        // Load image
        modalElements.img.onload = function() {
            showLoading(false);
            
            // Get natural dimensions
            const width = this.naturalWidth;
            const height = this.naturalHeight;
            
            if (modalElements.dimensions) {
                modalElements.dimensions.innerHTML = `
                    <i class="bi bi-arrows-angle-expand me-1"></i>
                    ${width} × ${height} px
                `;
            }
            
            // Update image information
            if (modalElements.imageInfo) {
                modalElements.imageInfo.innerHTML = `
                    <div class="d-flex gap-3 flex-wrap">
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-arrows-angle-expand me-1"></i>${width} × ${height}
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-file-image me-1"></i>${formatFileSize(this.fileSize)}
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-aspect-ratio me-1"></i>${(width/height).toFixed(2)}:1
                        </span>
                    </div>
                `;
            }
        };
        
        modalElements.img.onerror = function() {
            showError('Failed to load image');
        };
        
        modalElements.img.src = imgSrc;
        modalElements.downloadBtn.href = imgSrc;
        
        // Get file size via HEAD request
        fetch(imgSrc, { method: 'HEAD', cache: 'force-cache' })
            .then(response => {
                const size = response.headers.get('Content-Length');
                if (size && modalElements.size) {
                    const fileSize = formatFileSize(parseInt(size));
                    modalElements.size.innerHTML = `
                        <i class="bi bi-database me-1"></i>
                        ${fileSize}
                    `;
                    
                    // Add size to image for onload
                    if (modalElements.img) {
                        modalElements.img.fileSize = parseInt(size);
                    }
                }
            })
            .catch(() => {
                if (modalElements.size) {
                    modalElements.size.innerHTML = `
                        <i class="bi bi-database me-1"></i>
                        —
                    `;
                }
            });
    });

    // Modal close handler
    modalElements.modal.addEventListener('hidden.bs.modal', function () {
        modalElements.img.src = '';
        showLoading(false);
    });

    // Image zoom
    if (modalElements.zoomInBtn) {
        modalElements.zoomInBtn.addEventListener('click', function () {
            currentZoom = Math.min(currentZoom + 0.25, 3);
            updateImageTransform();
            if (modalElements.zoomLevel) {
                modalElements.zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
            }
        });
    }

    if (modalElements.zoomOutBtn) {
        modalElements.zoomOutBtn.addEventListener('click', function () {
            currentZoom = Math.max(currentZoom - 0.25, 0.5);
            updateImageTransform();
            if (modalElements.zoomLevel) {
                modalElements.zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
            }
        });
    }

    // Image rotation
    if (modalElements.rotateBtn) {
        modalElements.rotateBtn.addEventListener('click', function () {
            currentRotation = (currentRotation + 90) % 360;
            updateImageTransform();
        });
    }

    // Fullscreen mode with animation
    if (modalElements.fullscreenBtn) {
        modalElements.fullscreenBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                modalElements.img.requestFullscreen().catch(err => {
                    console.log('Fullscreen error:', err);
                });
            } else {
                document.exitFullscreen();
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (!modalElements.modal.classList.contains('show')) return;
        
        switch(e.key) {
            case 'Escape':
                bootstrap.Modal.getInstance(modalElements.modal).hide();
                break;
            case '+':
            case '=':
                e.preventDefault();
                if (modalElements.zoomInBtn) modalElements.zoomInBtn.click();
                break;
            case '-':
                e.preventDefault();
                if (modalElements.zoomOutBtn) modalElements.zoomOutBtn.click();
                break;
            case 'r':
                e.preventDefault();
                if (modalElements.rotateBtn) modalElements.rotateBtn.click();
                break;
            case 'f':
                e.preventDefault();
                if (modalElements.fullscreenBtn) modalElements.fullscreenBtn.click();
                break;
        }
    });
});
