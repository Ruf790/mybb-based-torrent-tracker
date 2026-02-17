<?php

?>
<!-- Universal Image Preview Modal -->
<div class="modal fade" id="universalImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div id="universalImageModalTitle" class="text-dark">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-image-fill text-primary me-2" style="font-size: 1.5rem;"></i>
                        <div>
                            <h5 class="mb-0 fw-semibold">Image Viewer</h5>
                            <small class="text-muted">Press ESC to close</small>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-0 position-relative">
                <!-- Loading spinner -->
                <div id="imageLoadingSpinner" class="position-absolute top-50 start-50 translate-middle" style="display: none; z-index: 10;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                
                <!-- Error message -->
                <div id="imageErrorMessage" class="position-absolute top-50 start-50 translate-middle text-center" style="display: none; z-index: 10;">
                    <div class="bg-danger text-white p-3 rounded-3 shadow-lg">
                        <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                        <p class="mb-0 mt-2"><span></span></p>
                    </div>
                </div>
                
                <!-- Image container -->
                <div class="text-center overflow-auto" style="max-height: 80vh; background: #f4f4f4; padding: 10px;">
                    <img id="universalImagePreview" 
                         class="img-fluid rounded-3 shadow-sm" 
                         style="transition: transform 0.3s ease; cursor: zoom-in; background: white;"
                         alt="Preview">
                </div>
            </div>
            
            <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.05); background: rgba(255,255,255,0.8);">
                <div class="d-flex gap-2 align-items-center">
                    <!-- Image information -->
                    <span id="universalImageSize" class="text-muted small bg-light px-3 py-2 rounded-pill">
                        <i class="bi bi-database me-1"></i>
                        <span class="fw-semibold">—</span>
                    </span>
                    <span id="universalImageDimensions" class="text-muted small bg-light px-3 py-2 rounded-pill">
                        <i class="bi bi-arrows-angle-expand me-1"></i>
                        <span class="fw-semibold">—</span>
                    </span>
                </div>
                
                <div class="d-flex gap-2">
                    <!-- Zoom level -->
                    <span id="zoomLevel" class="badge bg-light text-dark rounded-pill px-3 py-2 align-self-center shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-percent me-1"></i>100%
                    </span>
                    
                    <!-- Control buttons -->
                    <button id="zoomOutBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Zoom out (Ctrl+-)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button id="zoomInBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Zoom in (Ctrl++)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button id="rotateBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Rotate (R)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button id="universalFullscreenBtn" class="btn btn-sm btn-light rounded-pill px-3" title="Fullscreen (F)" style="border: 1px solid #dee2e6;">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                    <a id="universalDownloadBtn" class="btn btn-sm btn-primary rounded-pill px-4" download title="Download" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="bi bi-download me-1"></i>
                        Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles for light theme */
#universalImageModal .modal-content {
    border-radius: 20px;
    overflow: hidden;
}

#universalImageModal .modal-header {
    padding: 1rem 1.5rem;
    background: white;
}

#universalImageModal .modal-footer {
    padding: 1rem 1.5rem;
    backdrop-filter: blur(10px);
}

#universalImageModal .btn-light {
    background: white;
    color: #495057;
    transition: all 0.2s ease;
}

#universalImageModal .btn-light:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    color: #667eea;
}

#universalImageModal .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
}

#universalImageModal .bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border: 1px solid rgba(255,255,255,0.5);
}

#universalImagePreview {
    max-width: 100%;
    max-height: calc(80vh - 20px);
    object-fit: contain;
}

/* Animation for buttons */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

#universalImageModal .btn-primary:active {
    animation: pulse 0.3s ease;
}

/* Scrollbar styles */
#universalImageModal .overflow-auto::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 4px;
}

#universalImageModal .overflow-auto::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}
</style>