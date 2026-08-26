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
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[email]', '[/email]', '<?php echo $textarea_id; ?>')">Email</button>

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
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[align=justify]', '[/align]', '<?php echo $textarea_id; ?>')">Justify</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[hr]', '', '<?php echo $textarea_id; ?>')" title="Horizontal Rule">HR</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', '<?php echo $textarea_id; ?>')">Quote</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', '<?php echo $textarea_id; ?>')">Code</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[php]', '[/php]', '<?php echo $textarea_id; ?>')">PHP</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[nfo]', '[/nfo]', '<?php echo $textarea_id; ?>')" title="NFO Block">NFO</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#torrentModal<?php echo $suffix; ?>" title="Embed Torrent Card"><i class="fa-solid fa-magnet"></i> Torrent</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tableModal<?php echo $suffix; ?>" title="Insert Table"><i class="fas fa-table"></i> Table</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list]\n[*]', '\n[/list]', '<?php echo $textarea_id; ?>')" title="Bulleted List"><i class="fas fa-list-ul"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list=1]\n[*]', '\n[/list]', '<?php echo $textarea_id; ?>')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[*]', '', '<?php echo $textarea_id; ?>')" title="List Item">[*]</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', '<?php echo $textarea_id; ?>')">Spoiler</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', '<?php echo $textarea_id; ?>')">YouTube</button>
		
		<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#videoModal">
    🎬 Video
</button>
		
        <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn<?php echo $suffix; ?>">Preview</button>
    </div>
	
	
	
<div class="modal fade" id="videoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Insert Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- URL -->
                <div class="mb-3">
                    <label class="form-label">Video URL</label>
                    <input type="text" class="form-control" id="videoUrl" placeholder="https://...">
                </div>

                <!-- TYPE -->
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="videoType">
                        <option value="auto">Auto Detect</option>
                        <option value="youtube">YouTube</option>
                        <option value="mp4">MP4 / WebM</option>
                    </select>
                </div>

                <!-- PREVIEW -->
                <div id="videoPreview" class="text-center"></div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="insertVideoBtn">Insert</button>
            </div>

        </div>
    </div>
</div>

<!-- Table Builder Modal -->
<div class="modal fade" id="tableModal<?php echo $suffix; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col">
                        <label class="form-label">Rows</label>
                        <input type="number" class="form-control" id="tableRows<?php echo $suffix; ?>" value="2" min="1" max="20">
                    </div>
                    <div class="col">
                        <label class="form-label">Columns</label>
                        <input type="number" class="form-control" id="tableCols<?php echo $suffix; ?>" value="2" min="1" max="10">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="insertTableBtn<?php echo $suffix; ?>">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Torrent Embed Modal -->
<div class="modal fade" id="torrentModal<?php echo $suffix; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-magnet me-2"></i>Embed Torrent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Torrent ID</label>
                <input type="text" inputmode="numeric" class="form-control" id="torrentIdInput<?php echo $suffix; ?>" placeholder="e.g. 17 or paste the torrent link/URL">
                <div class="form-text">You can also paste the full torrent URL or link — the ID will be extracted automatically.</div>
                <div id="torrentPreview<?php echo $suffix; ?>" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="insertTorrentBtn<?php echo $suffix; ?>">Insert</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ── Горячие клавиши Ctrl+B / Ctrl+I / Ctrl+U ─────────────────────────
    var ta = document.getElementById('<?php echo $textarea_id; ?>');
    if (ta) {
        ta.addEventListener('keydown', function(e) {
            if (!(e.ctrlKey || e.metaKey)) return;
            var tag = null;
            switch (e.key.toLowerCase()) {
                case 'b': tag = ['[b]', '[/b]']; break;
                case 'i': tag = ['[i]', '[/i]']; break;
                case 'u': tag = ['[u]', '[/u]']; break;
                default: return;
            }
            e.preventDefault();
            insertBBCode(tag[0], tag[1], '<?php echo $textarea_id; ?>');
        });
    }

    // ── Конструктор таблиц ────────────────────────────────────────────────
    var tblBtn = document.getElementById('insertTableBtn<?php echo $suffix; ?>');
    if (tblBtn) {
        tblBtn.addEventListener('click', function() {
            var rows = parseInt(document.getElementById('tableRows<?php echo $suffix; ?>').value, 10) || 1;
            var cols = parseInt(document.getElementById('tableCols<?php echo $suffix; ?>').value, 10) || 1;
            var out = '[table]\n';
            for (var r = 0; r < rows; r++) {
                out += '[tr]';
                for (var c = 0; c < cols; c++) {
                    out += '[td]cell[/td]';
                }
                out += '[/tr]\n';
            }
            out += '[/table]';
            insertBBCode(out, '', '<?php echo $textarea_id; ?>');

            var modalEl = document.getElementById('tableModal<?php echo $suffix; ?>');
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        });
    }

    // ── Вставка торрент-карточки ────────────────────────────────────────────
    var torrentBtn     = document.getElementById('insertTorrentBtn<?php echo $suffix; ?>');
    var torrentInput   = document.getElementById('torrentIdInput<?php echo $suffix; ?>');
    var torrentPreview = document.getElementById('torrentPreview<?php echo $suffix; ?>');
    var torrentTimer   = null;

    function escapeHtmlLocal(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // Достаём ID из чего угодно: голого числа, полного URL (torrent-17.html),
    // query-параметра (?id=17) или markdown-ссылки со всем этим внутри.
    function extractTorrentId(raw) {
        raw = raw || '';
        var m = raw.match(/torrent-(\d+)\.html/i)
             || raw.match(/[?&](?:id|tid)=(\d+)/i)
             || raw.match(/(\d+)/);
        return m ? m[1] : '';
    }

    if (torrentInput && torrentPreview) {
        torrentInput.addEventListener('input', function() {
            clearTimeout(torrentTimer);
            var id = extractTorrentId(torrentInput.value);
            if (!id) {
                torrentPreview.innerHTML = '';
                return;
            }
            torrentTimer = setTimeout(function() {
                torrentPreview.innerHTML = '<div class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading preview...</div>';
                fetch('<?php echo $BASEURL; ?>/ajax_torrent_preview.php?id=' + id)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.error) {
                            torrentPreview.innerHTML = '<div class="text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + escapeHtmlLocal(data.error) + '</div>';
                            return;
                        }
                        var img = data.image
                            ? '<img src="' + escapeHtmlLocal(data.image) + '" class="card-img-top" style="height:100px;object-fit:cover;">'
                            : '';
                        torrentPreview.innerHTML =
                            '<div class="card">' + img +
                            '<div class="card-body py-2 px-3">' +
                            '<div class="fw-bold text-truncate small"><i class="fa-solid fa-magnet me-1"></i>' + escapeHtmlLocal(data.name) + '</div>' +
                            '<div class="text-muted small">' + escapeHtmlLocal(data.catname) + ' &middot; ' + escapeHtmlLocal(data.size) +
                            ' &middot; <span class="text-success">' + data.seeders + ' seeders</span>' +
                            ' &middot; <span class="text-danger">' + data.leechers + ' leechers</span>' +
                            '</div></div></div>';
                    })
                    .catch(function() {
                        torrentPreview.innerHTML = '<div class="text-danger small">Failed to load preview</div>';
                    });
            }, 400);
        });
    }

    if (torrentBtn && torrentInput) {
        var doInsertTorrent = function() {
            var id = extractTorrentId(torrentInput.value);
            if (!id) {
                torrentInput.focus();
                return;
            }
            insertBBCode('[torrent=' + id + ']', '', '<?php echo $textarea_id; ?>');
            torrentInput.value = '';
            if (torrentPreview) torrentPreview.innerHTML = '';

            var modalEl = document.getElementById('torrentModal<?php echo $suffix; ?>');
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        };
        torrentBtn.addEventListener('click', doInsertTorrent);
        torrentInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doInsertTorrent();
            }
        });
    }
})();
</script>

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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                </div>
                <ul class="nav nav-tabs px-3">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-url" tabindex="-1">By URL</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-upload" tabindex="-1">Upload</a>
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
                    <button type="button" class="btn btn-primary" id="insertImageBtn">Insert</button>
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