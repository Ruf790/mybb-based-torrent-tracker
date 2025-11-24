<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Screenshot Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .screenshot-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: contain;
        }
        .screenshot-item {
            position: relative;
            margin: 10px;
            display: inline-block;
        }
        .remove-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background: red;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h5>Upload Screenshots</h5>
            </div>
            <div class="card-body">
                <!-- URL Upload -->
                <div class="mb-3">
                    <label class="form-label">Add Screenshot by URL</label>
                    <div class="input-group">
                        <input type="url" class="form-control" id="screenshotUrl" placeholder="https://example.com/screenshot.jpg">
                        <button class="btn btn-primary" type="button" id="addUrlBtn">
                            <i class="fas fa-plus"></i> Add URL
                        </button>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="mb-3">
                    <label class="form-label">Upload Screenshot Files</label>
                    <input type="file" class="form-control" id="screenshotFiles" multiple accept="image/*">
                </div>

                <!-- Preview Area -->
                <div class="mb-3">
                    <label class="form-label">Screenshots Preview</label>
                    <div id="screenshotsPreview" class="border p-3 rounded" style="min-height: 100px;">
                        <p class="text-muted text-center">No screenshots added yet</p>
                    </div>
                </div>

                <!-- Hidden field to store URLs (for form submission) -->
                <div id="screenshotUrlsContainer" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const screenshotUrlInput = document.getElementById('screenshotUrl');
        const addUrlBtn = document.getElementById('addUrlBtn');
        const screenshotFilesInput = document.getElementById('screenshotFiles');
        const screenshotsPreview = document.getElementById('screenshotsPreview');
        const screenshotUrlsContainer = document.getElementById('screenshotUrlsContainer');
        
        let screenshotUrls = [];
        let screenshotFiles = [];

        // Add screenshot by URL
        addUrlBtn.addEventListener('click', function() {
            const url = screenshotUrlInput.value.trim();
            if (!url) {
                alert('Please enter a valid URL');
                return;
            }

            if (!isValidImageUrl(url)) {
                alert('Please enter a valid image URL (jpg, jpeg, png, gif, webp)');
                return;
            }

            if (screenshotUrls.includes(url)) {
                alert('This URL has already been added');
                return;
            }

            screenshotUrls.push(url);
            addScreenshotPreview(url, 'url');
            screenshotUrlInput.value = '';
            updateHiddenFields();
        });

        // Handle file upload
        screenshotFilesInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            files.forEach(file => {
                if (!file.type.startsWith('image/')) {
                    alert(`File ${file.name} is not an image`);
                    return;
                }

                if (screenshotFiles.some(f => f.name === file.name && f.size === file.size)) {
                    alert(`File ${file.name} has already been added`);
                    return;
                }

                screenshotFiles.push(file);
                const objectUrl = URL.createObjectURL(file);
                addScreenshotPreview(objectUrl, 'file', file.name);
            });

            screenshotFilesInput.value = '';
            updateHiddenFields();
        });

        // Validate image URL
        function isValidImageUrl(url) {
            const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp'];
            return imageExtensions.some(ext => url.toLowerCase().includes(ext));
        }

        // Add screenshot preview
        function addScreenshotPreview(src, type, filename = null) {
            // Remove placeholder text if it exists
            const placeholder = screenshotsPreview.querySelector('.text-muted');
            if (placeholder) {
                placeholder.remove();
            }

            const screenshotItem = document.createElement('div');
            screenshotItem.className = 'screenshot-item';
            screenshotItem.dataset.type = type;
            screenshotItem.dataset.src = type === 'file' ? filename : src;

            const img = document.createElement('img');
            img.src = src;
            img.className = 'screenshot-preview img-thumbnail';
            img.onerror = function() {
                if (type === 'url') {
                    alert('Failed to load image from URL: ' + src);
                    screenshotItem.remove();
                    screenshotUrls = screenshotUrls.filter(url => url !== src);
                    updateHiddenFields();
                }
            };

            const removeBtn = document.createElement('div');
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '×';
            removeBtn.onclick = function() {
                if (type === 'url') {
                    screenshotUrls = screenshotUrls.filter(url => url !== src);
                } else {
                    screenshotFiles = screenshotFiles.filter(file => file.name !== filename);
                    URL.revokeObjectURL(src); // Free memory
                }
                screenshotItem.remove();
                
                // Show placeholder if no screenshots left
                if (screenshotsPreview.children.length === 0) {
                    const placeholder = document.createElement('p');
                    placeholder.className = 'text-muted text-center';
                    placeholder.textContent = 'No screenshots added yet';
                    screenshotsPreview.appendChild(placeholder);
                }
                
                updateHiddenFields();
            };

            screenshotItem.appendChild(img);
            screenshotItem.appendChild(removeBtn);
            screenshotsPreview.appendChild(screenshotItem);
        }

        // Update hidden fields for form submission
        function updateHiddenFields() {
            // Clear existing hidden fields
            screenshotUrlsContainer.innerHTML = '';

            // Add hidden fields for URLs
            screenshotUrls.forEach((url, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `screenshotUrls[${index}]`;
                input.value = url;
                screenshotUrlsContainer.appendChild(input);
            });

            // Note: Files will be handled by FormData during form submission
        }

        // Optional: Drag and drop functionality
        screenshotsPreview.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = '#f8f9fa';
        });

        screenshotsPreview.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.background = '';
        });

        screenshotsPreview.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '';
            
            const files = Array.from(e.dataTransfer.files);
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    screenshotFiles.push(file);
                    const objectUrl = URL.createObjectURL(file);
                    addScreenshotPreview(objectUrl, 'file', file.name);
                }
            });
            
            updateHiddenFields();
        });
    });
    </script>
</body>
</html>