<?php
function insert_bbcode_editor($smilies = [], $BASEURL = '', $textarea_id = 'commentText') 
{
    // Initialize output buffers for toolbar and modal
    ob_start();
    $toolbar_output = '';
    $modal_output = '';

    // Generate ID suffixes based on textarea_id
    $suffix = '';
    switch($textarea_id) {
        case 'description': $suffix = '2'; break;
        case 'message': $suffix = '3'; break;
        case 'newsMessage': $suffix = '4'; break;
        case 'staffMessage': $suffix = '5'; break;
        default: $suffix = ''; // for commentText
    }

    // Toolbar content (inside or before <form>)
    ob_start();
?>
    
    <script>
        const smilies = <?php echo json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <link rel="stylesheet" href="<?php echo $BASEURL; ?>/include/templates/default/style/bbcode.css" type="text/css">
    <script src="<?php echo $BASEURL; ?>/scripts/bbcode_tools.js"></script>

    <!-- BBCode Toolbar -->
    <div class="mb-2 d-flex flex-wrap gap-1">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]', '[/b]', '<?php echo $textarea_id; ?>')"><strong>B</strong></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]', '[/i]', '<?php echo $textarea_id; ?>')"><em>I</em></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]', '[/u]', '<?php echo $textarea_id; ?>')"><u>U</u></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]', '[/s]', '<?php echo $textarea_id; ?>')">S</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]', '[/url]', '<?php echo $textarea_id; ?>')">URL</button>

        <!-- Image upload button -->
        <button type="button" class="btn btn-sm btn-outline-secondary" id="imageUploadBtn" data-bs-toggle="modal" data-bs-target="#imageUploadModal">
            <i class="fas fa-image"></i> Upload Image
        </button>

        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn" data-textarea="<?php echo $textarea_id; ?>">🎨 Color</button>
            <div class="color-palette d-none"></div>
        </div>
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="smileyBtn<?php echo $suffix; ?>" data-textarea="<?php echo $textarea_id; ?>">😊</button>
            <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute" id="smileyPanel<?php echo $suffix; ?>" style="z-index:1000;"></div>
        </div>

        <!-- Size selection -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary size-picker-btn" id="sizeBtn-<?php echo $textarea_id; ?>" data-textarea="<?php echo $textarea_id; ?>">Size</button>
            <div class="size-menu dropdown-menu p-2" id="sizeMenu-<?php echo $textarea_id; ?>"></div>
        </div>

        <!-- Font selection -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary font-picker-btn" id="fontBtn-<?php echo $textarea_id; ?>" data-textarea="<?php echo $textarea_id; ?>">Font</button>
            <div class="font-menu dropdown-menu p-2 shadow" id="fontMenu-<?php echo $textarea_id; ?>"></div>
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[center]', '[/center]', '<?php echo $textarea_id; ?>')">Center</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[left]', '[/left]', '<?php echo $textarea_id; ?>')">Left</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[right]', '[/right]', '<?php echo $textarea_id; ?>')">Right</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', '<?php echo $textarea_id; ?>')">Quote</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', '<?php echo $textarea_id; ?>')">Code</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', '<?php echo $textarea_id; ?>')">Spoiler</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', '<?php echo $textarea_id; ?>')">YouTube</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn<?php echo $suffix; ?>">Preview</button>
    </div>
<?php
    $toolbar_output = ob_get_clean();

    // Modal content (outside <form>)
    ob_start();
?>
    <!-- Image Upload Modal -->
    <div class="modal fade" id="imageUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Insert Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <ul class="nav nav-tabs px-3">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-url">By URL</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-upload">Upload</a>
                    </li>
                </ul>
                <div class="modal-body tab-content">
                    <div class="tab-pane fade show active" id="tab-url">
                        <div class="mb-3">
                            <label class="form-label">Image URL</label>
                            <input type="text" class="form-control" id="imageUrl5" placeholder="https://">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Width (optional)</label>
                                <input type="number" class="form-control" id="imageWidth">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Height (optional)</label>
                                <input type="number" class="form-control" id="imageHeight">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-upload">
                        <div class="mb-3">
                            <label class="form-label">Select Image</label>
                            <input type="file" class="form-control" id="imageUpload" accept="image/*">
                        </div>
                        <div class="progress d-none" id="uploadProgress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="uploadPreview" class="mt-2 text-center"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="insertImageBtn" onclick="insertImage('<?php echo $textarea_id; ?>')">Insert</button>
                </div>
            </div>
        </div>
    </div>
<?php
    $modal_output = ob_get_clean();

    return [
        'toolbar' => $toolbar_output,
        'modal' => $modal_output
    ];
}
?>