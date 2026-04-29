<?php

$rootpath = './../';
require_once $rootpath . 'global.php';
require_once INC_PATH . '/functions_multipage.php';

// ── Single delete (AJAX GET) ─────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    header('Content-Type: application/json; charset=utf-8');
    $file_id = (int)$_GET['delete'];

    $res = $db->sql_query("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        LEFT JOIN news n ON n.id = cf.news_id
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        LEFT JOIN tsf_posts p ON p.pid = cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
        WHERE cf.id = $file_id
    ");

    if ($file = $db->fetch_array($res)) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\[]*\[\/img\]/i';

        $updates = [
            ['comment_id', 'comments',       'text',    'comment_text'],
            ['news_id',    'news',            'body',    'news_text'],
            ['torrent_id', 'torrents',        'descr',   'torrent_description'],
            ['post_id',    'tsf_posts',       'message', 'post_message'],
            ['messages_id','privatemessages', 'message', 'pm_message'],
        ];
        $pk = ['comment_id'=>'id','news_id'=>'id','torrent_id'=>'id','post_id'=>'pid','messages_id'=>'pmid'];

        foreach ($updates as [$fk, $table, $col, $textKey]) {
            if (!empty($file[$fk])) {
                $new = preg_replace($pattern, '[Image Deleted]', $file[$textKey]);
                if ($new !== $file[$textKey]) {
                    $db->sql_query("UPDATE $table SET $col='" . $db->escape_string($new) . "' WHERE {$pk[$fk]}=" . (int)$file[$fk]);
                    if ($table === 'news') $cache->update_news();
                }
            }
        }

        $db->sql_query("DELETE FROM comment_files WHERE id=$file_id");
        echo json_encode(['status'=>'success','id'=>$file_id,'message'=>'File deleted']);
    } else {
        echo json_encode(['status'=>'error','message'=>'File not found']);
    }
    exit;
}

// ── Bulk delete (AJAX POST) ──────────────────────────────
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');

    $selected = $_POST['selected_files'] ?? [];
    if (is_string($selected)) $selected = $selected !== '' ? [$selected] : [];
    $ids = array_filter(array_map('intval', (array)$selected));

    if (empty($ids)) {
        echo json_encode(['status'=>'error','message'=>'No files selected']);
        exit;
    }

    $ids_str = implode(',', $ids);
    $res = $db->sql_query("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id=cf.comment_id
        LEFT JOIN news n ON n.id=cf.news_id
        LEFT JOIN torrents t ON t.id=cf.torrent_id
        LEFT JOIN tsf_posts p ON p.pid=cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid=cf.messages_id
        WHERE cf.id IN ($ids_str)
    ");

    $affected = ['comments'=>[],'news'=>[],'torrents'=>[],'tsf_posts'=>[],'privatemessages'=>[]];
    $pk_map   = ['comments'=>'id','news'=>'id','torrents'=>'id','tsf_posts'=>'pid','privatemessages'=>'pmid'];
    $col_map  = ['comments'=>'text','news'=>'body','torrents'=>'descr','tsf_posts'=>'message','privatemessages'=>'message'];
    $fk_map   = ['comments'=>'comment_id','news'=>'news_id','torrents'=>'torrent_id','tsf_posts'=>'post_id','privatemessages'=>'messages_id'];
    $txt_map  = ['comments'=>'comment_text','news'=>'news_text','torrents'=>'torrent_description','tsf_posts'=>'post_message','privatemessages'=>'pm_message'];
    $deleted_ids = [];

    while ($file = $db->fetch_array($res)) {
        $deleted_ids[] = $file['id'];
        if (file_exists($file['file_path'])) unlink($file['file_path']);
        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\]]*\[\/img\]/i';

        foreach ($affected as $table => $_) {
            $fk = $fk_map[$table];
            if (!empty($file[$fk])) {
                $new = preg_replace($pattern, '[Image Deleted]', $file[$txt_map[$table]]);
                if ($new !== $file[$txt_map[$table]]) {
                    $affected[$table][(int)$file[$fk]] = $new;
                }
            }
        }
    }

    foreach ($affected as $table => $rows) {
        foreach ($rows as $id => $text) {
            $col = $col_map[$table];
            $pk  = $pk_map[$table];
            $db->sql_query("UPDATE $table SET $col='" . $db->escape_string($text) . "' WHERE $pk=$id");
            if ($table === 'news') $cache->update_news();
        }
    }

    $db->sql_query("DELETE FROM comment_files WHERE id IN ($ids_str)");
    echo json_encode(['status'=>'success','message'=>'Files deleted','deleted_ids'=>$deleted_ids]);
    exit;
}

// ── List page setup ──────────────────────────────────────
$is_ajax   = isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1';
$per_page  = 15;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;
$search    = isset($_GET['search'])     ? $db->escape_string($_GET['search'])     : '';
$typeFilter= isset($_GET['type'])       ? $db->escape_string($_GET['type'])       : '';

$where = [];
if ($search)     $where[] = "(file_name LIKE '%$search%' OR file_type LIKE '%$search%')";
if ($typeFilter) {
    $type_conditions = [
        'torrent' => "torrent_id IS NOT NULL AND torrent_id != 0",
        'news'    => "news_id IS NOT NULL AND news_id != 0",
        'comment' => "comment_id IS NOT NULL AND comment_id != 0",
        'post'    => "post_id IS NOT NULL AND post_id != 0",
        'message' => "messages_id IS NOT NULL AND messages_id != 0",
    ];
    if (isset($type_conditions[$typeFilter])) $where[] = $type_conditions[$typeFilter];
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_row   = $db->fetch_array($db->sql_query("SELECT COUNT(*) as total FROM comment_files $whereClause"));
$total_files = $total_row['total'];
$total_pages = ceil($total_files / $per_page);

$result = $db->sql_query("
    SELECT comment_files.*, users.username, users.usergroup, users.avatar, users.avatardimensions
    FROM comment_files
    LEFT JOIN users ON users.id = comment_files.user_id
    $whereClause
    ORDER BY comment_files.uploaded_at DESC
    LIMIT $offset, $per_page
");

$files = [];
while ($row = $db->fetch_array($result)) $files[] = $row;

$this_script2 = "index.php?act=manage_uploads"
    . ($search     ? "&search="  . urlencode($search)     : '')
    . ($typeFilter ? "&type="    . urlencode($typeFilter)  : '');

if ($is_ajax) {
    ob_start();
    include('manage_uploads_ajax.php');
    echo ob_get_clean();
    stdfoot();
    exit;
}

function getFileDimensions($file_path) {
    if (!file_exists($file_path)) return 'N/A';
    $info = getimagesize($file_path);
    return $info ? $info[0] . '×' . $info[1] : 'N/A';
}

// ── Output ───────────────────────────────────────────────
stdhead();
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bootstrap-icons.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/manage_uploads.css">

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div class="page-header">
            <div class="page-icon"><i class="bi bi-images"></i></div>
            <div>
                <h1 class="mb-1 fw-bold">Media Library</h1>
                <p class="text-muted mb-0">Manage all uploaded media files and attachments</p>
            </div>
        </div>
        <span class="badge bg-light text-dark fs-6">
            <i class="bi bi-database me-1"></i> <?= number_format($total_files) ?> files
        </span>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div class="fw-medium"><?= str_replace('+', ' ', htmlspecialchars($_GET['success'])) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search & actions -->
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                <form class="search-box w-100" id="searchForm">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control form-control-lg" name="search"
                           placeholder="Search files by name, type or user..."
                           value="<?= htmlspecialchars($search) ?>" id="searchInput">
                </form>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">File Type</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-image me-2"></i>Images</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-file-earmark-pdf me-2"></i>PDFs</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Sort By</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-up me-2"></i>Newest First</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-down me-2"></i>Oldest First</a></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary" id="bulkSelectBtn">
                        <i class="bi bi-check2-square me-1"></i> Select Files
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk actions -->
    <div class="bulk-actions card mb-4" id="bulkActions">
        <div class="card-body p-3">
            <form method="POST" action="<?= $_this_script_ ?>" class="d-flex flex-column flex-md-row align-items-md-center gap-3" id="bulkForm">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check2-square me-2 fs-5 text-primary"></i>
                    <span class="fw-medium" id="selectedCount">0</span>
                    <span class="text-muted ms-1">selected</span>
                </div>
                <select name="bulk_action" class="form-select flex-grow-1" required>
                    <option value="">Choose action...</option>
                    <option value="delete">Delete selected files</option>
                </select>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-danger" id="applyBulkAction" disabled>
                        <i class="bi bi-trash3 me-1"></i> Apply Action
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="cancelBulkAction">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
                </div>
                <input type="hidden" name="selected_files[]" id="selectedFilesInput">
            </form>
        </div>
    </div>

    <!-- Files table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <div id="filesTableContainer">
                    <?php include('manage_uploads_ajax.php'); ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> Edit File Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= $this_script_ ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="update" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">File Name</label>
                        <input type="text" class="form-control" name="file_name" id="editFileName">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium"><i class="bi bi-chat-square-text me-1"></i>Comment ID</label>
                            <input type="number" class="form-control" name="comment_id" id="editCommentId">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium"><i class="bi bi-newspaper me-1"></i>News ID</label>
                            <input type="number" class="form-control" name="news_id" id="editNewsId">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium"><i class="bi bi-download me-1"></i>Torrent ID</label>
                            <input type="number" class="form-control" name="torrent_id" id="editTorrentId">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium"><i class="bi bi-card-text me-1"></i>Post ID</label>
                            <input type="number" class="form-control" name="post_id" id="editPostId">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium"><i class="bi bi-person me-1"></i>User ID</label>
                            <input type="number" class="form-control" name="user_id" id="editUserId">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once INC_PATH . '/modals_images.php'; ?>

<script src="<?= $BASEURL ?>/scripts/details_modal.js"></script>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cloud-upload text-primary me-2"></i>Upload New Files
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Steps -->
                <div class="d-flex align-items-center justify-content-between mb-5 position-relative">
                    <?php foreach ([['bi-link-45deg','1. Link'],['bi-folder2-open','2. Select'],['bi-cloud-upload','3. Upload']] as [$icon,$label]): ?>
                    <div class="text-center step-item">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 mb-2 step-circle" style="width:60px;height:60px">
                            <i class="bi <?= $icon ?> text-primary fs-4"></i>
                        </div>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                    <?php if ($label !== '3. Upload'): ?>
                    <div class="text-primary step-arrow"><i class="bi bi-arrow-right fs-4"></i></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Content type & ID -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-tag me-1"></i>Content Type</label>
                        <select class="form-select form-select-lg border-0 bg-light" id="contentTypeSelect" required>
                            <option selected disabled>Choose type...</option>
                            <option value="comment">💬 Comment</option>
                            <option value="news">📰 News</option>
                            <option value="torrent">⬇️ Torrent</option>
                            <option value="post">📄 Post</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-hash me-1"></i>Content ID</label>
                        <input type="number" class="form-control form-control-lg border-0 bg-light" id="contentId" placeholder="Enter ID" required>
                    </div>
                </div>

                <!-- Drop area -->
                <div class="upload-area bg-light rounded-4 p-5 text-center position-relative"
                     id="dropArea" style="border:2px dashed var(--bs-primary);cursor:pointer">
                    <div class="upload-progress position-absolute top-0 start-0 end-0" style="display:none">
                        <div class="progress rounded-0 rounded-top-4" style="height:4px">
                            <div class="progress-bar bg-primary" id="uploadProgress" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="row justify-content-center mb-4">
                        <div class="col-auto file-type-icon"><i class="bi bi-file-earmark-image fs-1 text-primary opacity-50"></i></div>
                        <div class="col-auto file-type-icon"><i class="bi bi-file-earmark-pdf fs-1 text-danger opacity-50"></i></div>
                        <div class="col-auto file-type-icon"><i class="bi bi-file-earmark-word fs-1 text-primary opacity-50"></i></div>
                    </div>
                    <div class="image-preview-container mb-3" id="imagePreviewContainer" style="display:none">
                        <div class="d-flex flex-wrap gap-2 justify-content-center" id="imagePreviewList"></div>
                    </div>
                    <h5 class="fw-bold" id="dropAreaTitle">Drag & drop files here</h5>
                    <p class="text-muted mb-4" id="dropAreaSubtitle">or click to browse</p>
                    <div class="file-count-badge position-absolute top-0 end-0 m-3" id="fileCountBadge" style="display:none">
                        <span class="badge bg-primary rounded-pill p-2" id="fileCount"></span>
                    </div>
                    <input type="file" class="d-none" id="fileUploadInput" multiple accept="image/*,.pdf,.doc,.docx">
                    <button class="btn btn-outline-primary btn-lg px-5 rounded-pill" onclick="document.getElementById('fileUploadInput').click()" id="browseBtn">
                        <i class="bi bi-folder2-open me-2"></i>Browse
                    </button>
                    <button class="btn btn-link text-danger mt-3" id="clearFilesBtn" style="display:none" onclick="clearSelectedFiles()">
                        <i class="bi bi-x-circle me-1"></i>Clear all
                    </button>
                </div>

                <!-- Stats -->
                <div class="d-flex justify-content-between mt-3 text-secondary small flex-wrap">
                    <span><i class="bi bi-check-circle text-success me-1"></i>Images <span class="badge bg-light text-dark ms-1" id="imageCount">0</span></span>
                    <span><i class="bi bi-check-circle text-success me-1"></i>PDF <span class="badge bg-light text-dark ms-1" id="pdfCount">0</span></span>
                    <span><i class="bi bi-check-circle text-success me-1"></i>Docs <span class="badge bg-light text-dark ms-1" id="docCount">0</span></span>
                    <span id="sizeWarning" style="display:none"><i class="bi bi-exclamation-circle text-warning me-1"></i><span id="totalSize">0 MB</span></span>
                </div>

                <!-- Selected files list -->
                <div class="selected-files mt-4" id="selectedFilesList" style="display:none">
                    <div class="d-flex align-items-center justify-content-between bg-light p-3 rounded-3 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-files text-primary fs-5 me-2"></i>
                            <span class="fw-bold"><span id="selectedFilesCount">0</span> files ready</span>
                        </div>
                        <span class="text-muted small" id="totalSizeDisplay">0 KB</span>
                    </div>
                    <div class="list-group" id="selectedFilesListContainer" style="max-height:200px;overflow-y:auto"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary px-5 position-relative" id="startUploadBtn" disabled>
                    <span class="upload-text"><i class="bi bi-cloud-upload me-2"></i>Upload Now</span>
                    <span class="upload-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Uploading...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Bulk Delete Confirmation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-trash-alt text-danger fs-1"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1">Delete <span id="filesCount" class="text-danger">0</span> Selected Files?</h5>
                        <p class="text-muted mb-0">All related images in content will be replaced with "[Image Deleted]"</p>
                    </div>
                </div>
                <div class="bulk-preview-grid mb-3" id="bulkPreviewGrid"
                     style="max-height:300px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:10px;padding:10px;background:#f8f9fa;border-radius:8px">
                    <div class="text-center py-4 text-muted" id="bulkPreviewPlaceholder">
                        <i class="fas fa-images fa-2x mb-2"></i>
                        <div class="small">No images selected</div>
                    </div>
                </div>
                <div class="file-details bg-light p-3 rounded-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-3 fa-2x"></i>
                            <div>
                                <div class="fw-bold" id="selectedFilesSummary">0 files selected</div>
                                <div class="small text-muted" id="selectedFilesSize">Total size: 0 MB</div>
                            </div>
                        </div>
                        <span class="badge bg-danger" id="selectedFilesCount">0</span>
                    </div>
                </div>
                <div class="alert alert-warning mt-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i><strong>Warning:</strong> This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">
                    <i class="fas fa-trash-alt me-1"></i> Yes, Delete <span id="confirmCount">0</span> Files
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Single Delete Modal -->
<div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete File</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-trash-alt text-danger fs-1"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1" id="singleDeleteTitle">Delete File?</h5>
                        <p class="text-muted mb-0" id="singleDeleteFilename"></p>
                    </div>
                </div>
                <div class="single-preview-container mb-3 text-center" id="singlePreviewContainer">
                    <img id="singleDeleteImage" src="" alt="Preview"
                         style="max-width:100%;max-height:200px;border-radius:8px;display:none"
                         onerror="this.style.display='none'">
                </div>
                <div class="file-details bg-light p-3 rounded-3 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-file-alt text-primary me-3 fa-2x"></i>
                        <div>
                            <div class="fw-bold" id="singleDeleteFileName">filename.jpg</div>
                            <div class="small text-muted" id="singleDeleteFileInfo">
                                <i class="fas fa-spinner fa-spin me-1"></i> Loading...
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-2 mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i><strong>Warning:</strong> This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmSingleDeleteBtn">
                    <i class="fas fa-trash-alt me-1"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_uploads.js"></script>

<?php stdfoot(); ?>