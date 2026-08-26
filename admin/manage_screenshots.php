<?php

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_image_recode.php';

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function get_upload_path(string $filename): string
{
    return TSDIR . '/torrents/screens/' . $filename;
}

function get_image_path(string $filename): string
{
    global $BASEURL;
    return $BASEURL . '/torrents/screens/' . $filename;
}

function get_next_screenshot_number(int $torrent_id, object $db, int $step = 3): int
{
    $max = 0;
    $q = $db->sql_query_prepared("SELECT filename FROM screenshots WHERE torrent_id = ?", [$torrent_id]);
    while ($row = $db->fetch_array($q)) {
        if (preg_match('/^' . $torrent_id . '_(\d+)\./', $row['filename'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $max + $step;
}

function scr_verify_csrf(): void
{
    global $mybb, $_this_script_;
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
            exit;
        }
        header('Location: ' . $_this_script_ . '&error=csrf');
        exit;
    }
}

function scr_pagination(int $count, int $perpage, int $page, string $base_url): void
{
    if ($count > $perpage) {
        echo multipage($count, $perpage, $page, $base_url . '&page={page}');
    }
}

function scr_page_params(): array
{
    global $mybb, $CURUSER, $ts_perpage;
    $perpage = ($CURUSER['torrentsperpage'] ?? 0) ?: $ts_perpage ?: 20;
    $perpage = max(1, (int)$perpage);
    return [(int)($mybb->input['page'] ?? 1), $perpage];
}

// ═══════════════════════════════════════════════════════════
// ACTION: MASS DELETE
// ═══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'mass_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db; // ВАЖНО: добавить эту строку!
    
    header('Content-Type: application/json');
    scr_verify_csrf();

    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No screenshots selected']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $db->sql_query_prepared("SELECT id, filename FROM screenshots WHERE id IN ($placeholders)", $ids);
    $to_delete = [];
    while ($row = $db->fetch_array($q)) {
        $to_delete[$row['id']] = $row['filename'];
    }

    $deleted = [];
    $errors = [];
    
    foreach ($to_delete as $id => $filename) {
        // Удаляем файл
        $path = get_upload_path($filename);
        $file_ok = true;
        if (file_exists($path)) {
            $file_ok = unlink($path);
        }
        
        // Удаляем запись из БД
        $db->sql_query_prepared("DELETE FROM screenshots WHERE id = ?", [$id]);
        
        // Проверяем, что запись действительно удалена
        $check = $db->sql_query_prepared("SELECT id FROM screenshots WHERE id = ?", [$id]);
        $db_ok = $db->num_rows($check) == 0;

        if ($file_ok && $db_ok) {
            $deleted[] = $id;
        } else {
            $errors[$id] = !$file_ok ? 'File error' : 'DB error';
            if ($db_ok) $deleted[] = $id;
        }
    }

    write_log("Mass Delete Screens: deleted " . count($deleted) . ", errors " . count($errors) . ". IDs: " . implode(',', $ids));

    echo json_encode([
        'status'  => empty($errors) ? 'success' : (empty($deleted) ? 'error' : 'partial'),
        'deleted' => $deleted,
        'errors'  => $errors,
        'message' => empty($deleted)
            ? 'Failed to delete screenshots'
            : 'Deleted: ' . count($deleted) . (empty($errors) ? '' : ', errors: ' . count($errors)),
    ]);
    exit;
}



// ═══════════════════════════════════════════════════════════
// ACTION: AJAX UPLOAD (модалка на странице списка)
// ═══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'ajax_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db, $CURUSER;

    header('Content-Type: application/json');
    scr_verify_csrf();

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size    = 10 * 1024 * 1024; // 10 MB

    $torrent_id = (int)($_POST['torrent_id'] ?? 0);
    if ($torrent_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid Torrent ID is required']);
        exit;
    }

    $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
    if (!$torrent_check || $db->num_rows($torrent_check) === 0) {
        echo json_encode(['status' => 'error', 'message' => "Torrent ID {$torrent_id} does not exist"]);
        exit;
    }

    if (!isset($_FILES['screenshots']) || empty($_FILES['screenshots']['name'][0] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'No files uploaded']);
        exit;
    }

    $files = $_FILES['screenshots'];
    $fileCount = count($files['name']);
    $uploaded = 0;
    $errors = [];

    for ($i = 0; $i < $fileCount; $i++) {
        $name  = $files['name'][$i];
        $tmp   = $files['tmp_name'][$i];
        $size  = $files['size'][$i];
        $error = $files['error'][$i];

        if ($name === '') continue;

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "{$name}: upload error";
            continue;
        }
        if ($size > $max_size) {
            $errors[] = "{$name}: too large (max 10MB)";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = "{$name}: invalid format";
            continue;
        }

        if (!($info = @getimagesize($tmp))) {
            $errors[] = "{$name}: not a valid image";
            continue;
        }

        $num      = get_next_screenshot_number($torrent_id, $db, 3);
        $filename = $torrent_id . '_' . $num . '.' . $ext;
        $path     = get_upload_path($filename);

        if (!move_uploaded_file($tmp, $path)) {
            $errors[] = "{$name}: failed to save";
            continue;
        }

        // Перекодирование через GD — защита от "полиглот"-файлов
        // (getimagesize() проверяет только заголовок, не весь файл).
        if (recode_image_file($path, $info['mime']) === false) {
            @unlink($path);
            $errors[] = "{$name}: corrupted or invalid image data";
            continue;
        }

        $db->sql_query_prepared(
            'INSERT INTO screenshots (torrent_id, filename, uploaded_at) VALUES (?, ?, ?)',
            [$torrent_id, $filename, time()]
        );

        $fsize = round(filesize($path) / 1024, 2);
        $dims  = $info[0] . 'x' . $info[1];
        write_log("Screenshot uploaded: Torrent #{$torrent_id} | {$filename} | {$fsize}KB | {$dims} | {$CURUSER['username']}");
        $uploaded++;
    }

    if ($uploaded > 0 && empty($errors)) {
        echo json_encode(['status' => 'success', 'uploaded' => $uploaded, 'message' => "{$uploaded} screenshot(s) uploaded successfully"]);
    } elseif ($uploaded > 0) {
        echo json_encode(['status' => 'partial', 'uploaded' => $uploaded, 'message' => "{$uploaded} uploaded, errors: " . implode('; ', $errors)]);
    } else {
        write_log("[SCREENSHOT UPLOAD ERROR] Torrent #{$torrent_id} | {$CURUSER['username']} | " . implode('; ', $errors));
        echo json_encode(['status' => 'error', 'message' => implode('; ', $errors) ?: 'Upload failed']);
    }
    exit;
}



// ═══════════════════════════════════════════════════════════
// ACTION: AJAX EDIT (модалка на странице списка)
// ═══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'ajax_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db, $CURUSER;

    header('Content-Type: application/json');
    scr_verify_csrf();

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $id = (int)($_POST['id'] ?? 0);
    $res = $db->sql_query_prepared('SELECT * FROM screenshots WHERE id = ?', [$id]);
    $row = $res ? $db->fetch_array($res) : null;

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Screenshot not found']);
        exit;
    }

    $torrent_id = (int)($_POST['torrent_id'] ?? 0);
    $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
    if ($torrent_id <= 0 || !$torrent_check || $db->num_rows($torrent_check) === 0) {
        echo json_encode(['status' => 'error', 'message' => "Torrent ID {$torrent_id} does not exist"]);
        exit;
    }

    $filename = $row['filename'];
    $changes  = [];

    if ($row['torrent_id'] != $torrent_id) {
        $changes[] = "Torrent ID {$row['torrent_id']} → {$torrent_id}";
    }

    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid format. Allowed: ' . implode(', ', $allowed_ext)]);
            exit;
        }
        $screenshotInfo = @getimagesize($_FILES['screenshot']['tmp_name']);
        if (!$screenshotInfo) {
            echo json_encode(['status' => 'error', 'message' => 'File is not a valid image']);
            exit;
        }

        $old = get_upload_path($row['filename']);
        $num = get_next_screenshot_number($torrent_id, $db, 3);
        $newFilename = $torrent_id . '_' . $num . '.' . $ext;
        $newPath = get_upload_path($newFilename);

        if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $newPath)) {
            echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
            exit;
        }

        // Перекодирование через GD — защита от "полиглот"-файлов.
        if (recode_image_file($newPath, $screenshotInfo['mime']) === false) {
            @unlink($newPath);
            echo json_encode(['status' => 'error', 'message' => 'File is corrupted or is not a valid image']);
            exit;
        }

        if (file_exists($old)) unlink($old);
        $changes[]  = "File {$row['filename']} → {$newFilename}";
        $filename   = $newFilename;
    }

    $db->sql_query_prepared(
        'UPDATE screenshots SET torrent_id = ?, filename = ? WHERE id = ?',
        [$torrent_id, $filename, $id]
    );

    if ($changes) {
        write_log("Screenshot updated: ID:{$id} | " . implode(', ', $changes) . " | {$CURUSER['username']}");
    }

    echo json_encode(['status' => 'success', 'message' => 'Screenshot updated successfully']);
    exit;
}



// ═══════════════════════════════════════════════════════════
// ROUTER
// ═══════════════════════════════════════════════════════════
switch ($_GET['action'] ?? 'list') {
    case 'add':    handle_add();    break;
    case 'edit':   handle_edit();   break;
    case 'delete': handle_delete(); break;
    default:       show_list();
}

// ═══════════════════════════════════════════════════════════
// SHOW LIST
// ═══════════════════════════════════════════════════════════
function show_list(): void
{
    global $db, $_this_script_, $mybb, $BASEURL;

    $search     = trim($_GET['search']     ?? '');
    $torrent_id = trim($_GET['torrent_id'] ?? '');

    $params = [];
    $where  = [];
    if ($search) {
        $where[]  = "filename LIKE ?";
        $params[] = '%' . $search . '%';
    }
    if ($torrent_id) {
        $where[]  = "torrent_id = ?";
        $params[] = (int)$torrent_id;
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_res = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM screenshots $where_sql", $params);
    $count_row = $db->fetch_array($count_res);
    $count     = (int)($count_row['cnt'] ?? 0);

    [$page, $perpage] = scr_page_params();
    $pages = max(1, (int)ceil($count / $perpage));
    $page  = max(1, min($page, $pages));
    $start = ($page - 1) * $perpage;

    $page_url = $_this_script_
        . ($search     ? '&search='     . urlencode($search)     : '')
        . ($torrent_id ? '&torrent_id=' . (int)$torrent_id       : '');

    $list_params = array_merge($params, [$start, $perpage]);
    $result = $db->sql_query_prepared("SELECT * FROM screenshots $where_sql ORDER BY uploaded_at DESC LIMIT ?, ?", $list_params);

    stdhead('Screenshot Management');
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/manage_screenshots.css">';
    echo '<script>var my_post_key = "' . $mybb->post_code . '"; var scr_script = "' . $_this_script_ . '";</script>';

    echo '<div class="container mt-3">';

    // Header
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1 class="mt-4"><i class="fas fa-images me-2"></i>Screenshot Management</h1>';
    echo '<div class="d-flex gap-2">';
    echo '<button type="button" class="btn btn-outline-secondary" id="selectAllBtn"><i class="fas fa-check-square me-2"></i>Select All</button>';
    echo '<button type="button" class="btn btn-danger" id="deleteSelectedBtn" disabled><i class="fas fa-trash me-2"></i>Delete Selected</button>';
    echo '<a href="' . $_this_script_ . '&action=add" class="btn btn-primary" id="addNewBtn" data-bs-toggle="modal" data-bs-target="#uploadScreenshotModal"><i class="fas fa-plus me-2"></i>Add New</a>';
    echo '</div></div>';

    // Search
    echo '<div class="card mb-4 shadow-sm"><div class="card-body bg-light">';
    echo '<form method="get" action="index.php" class="row g-3">';
    echo '<input type="hidden" name="act" value="manage_screenshots">';
    echo '<div class="col-md-4"><div class="input-group">';
    echo '<span class="input-group-text"><i class="fas fa-search"></i></span>';
    echo '<input type="text" class="form-control" name="search" placeholder="Search..." value="' . htmlspecialchars($search) . '">';
    echo '</div></div>';
    echo '<div class="col-md-3"><div class="input-group">';
    echo '<span class="input-group-text"><i class="fas fa-hashtag"></i></span>';
    echo '<input type="number" class="form-control" name="torrent_id" placeholder="Torrent ID" value="' . htmlspecialchars($torrent_id) . '">';
    echo '</div></div>';
    echo '<div class="col-md-3 d-flex align-items-end">';
    echo '<button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter me-1"></i>Filter</button>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary"><i class="fas fa-sync me-1"></i>Reset</a>';
    echo '</div></form></div></div>';

    scr_pagination($count, $perpage, $page, $page_url);

    if ($db->num_rows($result) == 0) {
        echo '
<div class="text-center py-5">
    <i class="fas fa-images fa-4x text-muted mb-3"></i>
    <h5 class="text-muted">No screenshots found.</h5>
</div>';
    } else {

        echo '<form id="massDeleteForm" method="post" action="' . $_this_script_ . '&action=mass_delete">';
        echo '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">';
        echo '<div class="row g-4">';

        while ($row = $db->fetch_array($result)) {
            $img = get_image_path($row['filename']);
            echo '<div class="col-xl-3 col-lg-4 col-md-6 screenshot-card">';
            echo '<div class="card h-100 shadow-sm border-0 overflow-hidden">';
            echo '<div class="position-relative">';
            echo '<div class="form-check position-absolute top-0 start-0 m-2 z-1">';
            echo '<input class="form-check-input screenshot-checkbox" type="checkbox" name="ids[]" value="' . $row['id'] . '" data-img-src="' . htmlspecialchars($img) . '">';
            echo '</div>';
            echo '<a href="#" data-bs-toggle="modal" data-bs-target="#universalImageModal" data-img-src="' . htmlspecialchars($img) . '" data-title="Torrent #' . $row['torrent_id'] . '">';
            echo '<img src="' . htmlspecialchars($img) . '" class="card-img-top object-fit-cover" style="height:180px" alt="Screenshot">';
            echo '</a>';
            echo '<div class="position-absolute top-0 end-0 m-2"><span class="badge bg-dark opacity-75">#' . $row['id'] . '</span></div>';
            echo '</div>';
            echo '<div class="card-body p-3">';
            echo '<div class="d-flex justify-content-between align-items-start">';
            echo '<div><h6 class="mb-0 fw-bold">Torrent #' . $row['torrent_id'] . '</h6>';
            echo '<small class="text-muted">' . date('M d, Y H:i', $row['uploaded_at']) . '</small></div>';
            echo '<div class="dropdown">';
            echo '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>';
            echo '<ul class="dropdown-menu dropdown-menu-end">';
            echo '<li><a class="dropdown-item edit-screenshot-btn" href="' . $_this_script_ . '&action=edit&id=' . $row['id'] . '"'
                . ' data-id="' . $row['id'] . '"'
                . ' data-torrent-id="' . $row['torrent_id'] . '"'
                . ' data-filename="' . htmlspecialchars($row['filename']) . '"'
                . ' data-img-src="' . htmlspecialchars($img) . '"'
                . ' data-bs-toggle="modal" data-bs-target="#editScreenshotModal">'
                . '<i class="fas fa-edit me-2"></i>Edit</a></li>';
            echo '<li><a class="dropdown-item text-danger single-delete-btn" href="#"'
                . ' data-id="' . $row['id'] . '"'
                . ' data-filename="' . htmlspecialchars($row['filename']) . '"'
                . ' data-bs-toggle="modal" data-bs-target="#singleDeleteModal">'
                . '<i class="fas fa-trash me-2"></i>Delete</a></li>';
            echo '</ul></div></div></div></div></div>';
        }

        echo '</div></form>';
    }

    echo '<br>';
    scr_pagination($count, $perpage, $page, $page_url);

    require_once INC_PATH . '/modals_images.php';

    // ── Modals ──────────────────────────────────────────────
echo <<<HTML
<!-- Single Delete Modal -->
<div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-labelledby="singleDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="singleDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Основная иконка и текст -->
        <div class="d-flex align-items-center mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
            <i class="fas fa-trash-alt text-danger fs-1"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1" id="singleDeleteTitle">Delete Screenshot?</h5>
            <p class="text-muted mb-0" id="singleDeleteFilename"></p>
          </div>
        </div>
        <div id="singleDeletePreviewContainer" class="single-preview-container mb-3 text-center">
          <div class="preview-wrapper" style="display: inline-block; max-width: 100%;">
            <img id="singleDeleteImage" src="" alt="Preview" 
                 style="max-width: 100%; max-height: 200px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none;" 
                 onerror="this.style.display='none';">
          </div>
        </div>
        <div class="file-details bg-light p-3 rounded-3 mb-3">
          <div class="d-flex align-items-center">
            <i class="fas fa-file-image text-primary me-3 fa-2x"></i>
            <div class="overflow-hidden">
              <div class="fw-bold" id="singleDeleteFileName">filename.jpg</div>
              <div class="small text-muted" id="singleDeleteFileInfo">
                <i class="fas fa-spinner fa-spin me-1"></i> Loading...
              </div>
            </div>
          </div>
        </div>
        <div class="alert alert-warning mt-2 mb-0">
          <div class="d-flex">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
              <strong>Warning:</strong> This action cannot be undone!
            </div>
          </div>
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
HTML;




echo <<<HTML
<!-- Upload Screenshot Modal -->
<div class="modal fade" id="uploadScreenshotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-cloud-upload-alt text-primary me-2"></i>Upload Screenshots
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label text-secondary mb-2"><i class="fas fa-hashtag me-1"></i>Torrent ID</label>
                    <input type="number" class="form-control form-control-lg border-0 bg-light" id="scrTorrentId" placeholder="Enter Torrent ID" required>
                </div>

                <div class="upload-area bg-light rounded-4 p-5 text-center position-relative"
                     id="scrDropArea" style="border:2px dashed var(--bs-primary);cursor:pointer">
                    <div class="upload-progress position-absolute top-0 start-0 end-0" style="display:none">
                        <div class="progress rounded-0 rounded-top-4" style="height:4px">
                            <div class="progress-bar bg-primary" id="scrUploadProgress" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="image-preview-container mb-3" id="scrPreviewContainer" style="display:none">
                        <div class="d-flex flex-wrap gap-2 justify-content-center" id="scrPreviewGrid"></div>
                    </div>
                    <div id="scrPlaceholder">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3 opacity-50 text-primary"></i>
                        <h5 class="fw-bold">Drag & drop screenshots here</h5>
                        <p class="text-muted mb-4">or click to browse</p>
                    </div>
                    <div class="file-count-badge position-absolute top-0 end-0 m-3" id="scrFileCountBadge" style="display:none">
                        <span class="badge bg-primary rounded-pill p-2" id="scrFileCount"></span>
                    </div>
                    <input type="file" class="d-none" id="scrFileInput" multiple accept="image/*">
                    <button class="btn btn-outline-primary btn-lg px-5 rounded-pill" onclick="document.getElementById('scrFileInput').click()" id="scrBrowseBtn">
                        <i class="fas fa-folder-open me-2"></i>Browse
                    </button>
                    <button class="btn btn-link text-danger mt-3 d-none" id="scrClearBtn">
                        <i class="fas fa-times-circle me-1"></i>Clear all
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary px-5 position-relative" id="scrStartUploadBtn" disabled>
                    <span class="upload-text"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Now</span>
                    <span class="upload-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Uploading...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
HTML;



echo <<<HTML
<!-- Edit Screenshot Modal -->
<div class="modal fade" id="editScreenshotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit text-primary me-2"></i>Edit Screenshot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editScreenshotId" value="">
                <div class="row g-4 mb-3">
                    <div class="col-lg-6">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control border-0 bg-light" id="editTorrentId" required>
                            <label>Torrent ID</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary mb-2">New Screenshot File (optional)</label>
                            <input type="file" class="form-control border-0 bg-light" id="editScreenshotFile" accept="image/*">
                            <div class="form-text" id="editCurrentFilename"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="border rounded-4 p-3 text-center bg-light" style="min-height:220px">
                            <img id="editImagePreview" src="" class="img-fluid rounded" style="max-height:220px" alt="Preview">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary px-5 position-relative" id="editSaveBtn">
                    <span class="save-text"><i class="fas fa-save me-2"></i>Save Changes</span>
                    <span class="save-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Saving...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
HTML;



echo <<<HTML
<!-- Mass Delete Modal -->
<div class="modal fade" id="massDeleteModal" tabindex="-1" aria-labelledby="massDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="massDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> Delete Confirmation
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Основная иконка и текст -->
        <div class="d-flex align-items-center mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
            <i class="fas fa-trash-alt text-danger fs-1"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1">Delete <span id="deleteCount" class="text-danger">0</span> Screenshots?</h5>
            <p class="text-muted mb-0">All selected screenshots will be permanently removed.</p>
          </div>
        </div>

        <!-- Контейнер для превью -->
        <div id="massDeletePreview" class="selected-previews-container mb-3" style="display: none;">
          <div class="d-flex align-items-center mb-2">
            <i class="fas fa-images text-primary me-2"></i>
            <span class="fw-medium">Selected screenshots:</span>
          </div>
          <div id="previewList" class="previews-grid">
            <!-- Preview items will be inserted here -->
          </div>
        </div>

        <!-- Предупреждение -->
        <div class="alert alert-warning mt-3 mb-0">
          <div class="d-flex">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
              <strong>Warning:</strong> This action cannot be undone! All selected screenshots will be permanently deleted.
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="fas fa-trash-alt me-1"></i> Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>
HTML;

    echo '<script>
(function() {
    const modal        = document.getElementById("uploadScreenshotModal");
    const torrentInput = document.getElementById("scrTorrentId");
    const fileInput     = document.getElementById("scrFileInput");
    const dropArea      = document.getElementById("scrDropArea");
    const placeholder    = document.getElementById("scrPlaceholder");
    const previewContainer = document.getElementById("scrPreviewContainer");
    const previewGrid    = document.getElementById("scrPreviewGrid");
    const fileCountBadge = document.getElementById("scrFileCountBadge");
    const fileCountEl    = document.getElementById("scrFileCount");
    const clearBtn       = document.getElementById("scrClearBtn");
    const startBtn       = document.getElementById("scrStartUploadBtn");
    const progressWrap   = document.getElementById("scrUploadProgress")?.parentElement?.parentElement;
    const progressBar    = document.getElementById("scrUploadProgress");

    let selected = [];

    function updateStartBtn() {
        startBtn.disabled = !(selected.length > 0 && torrentInput.value.trim() !== "");
    }

    function renderPreview() {
        previewGrid.innerHTML = "";
        if (!selected.length) {
            placeholder.style.display = "";
            previewContainer.style.display = "none";
            fileCountBadge.style.display = "none";
            clearBtn.classList.add("d-none");
            updateStartBtn();
            return;
        }
        placeholder.style.display = "none";
        previewContainer.style.display = "block";
        fileCountBadge.style.display = "block";
        fileCountEl.textContent = selected.length + " file" + (selected.length > 1 ? "s" : "");
        clearBtn.classList.remove("d-none");

        selected.forEach(file => {
            if (!file.type.match("image.*")) return;
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement("img");
                img.src = e.target.result;
                img.className = "rounded border";
                img.style.cssText = "max-height:100px;max-width:140px;object-fit:cover";
                previewGrid.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
        updateStartBtn();
    }

    fileInput.addEventListener("change", function() {
        selected = [...this.files];
        renderPreview();
    });

    torrentInput.addEventListener("input", updateStartBtn);

    clearBtn.addEventListener("click", function() {
        selected = [];
        fileInput.value = "";
        renderPreview();
    });

    ["dragenter", "dragover", "dragleave", "drop"].forEach(ev =>
        dropArea.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); })
    );
    ["dragenter", "dragover"].forEach(ev =>
        dropArea.addEventListener(ev, () => dropArea.classList.add("border-primary"))
    );
    ["dragleave", "drop"].forEach(ev =>
        dropArea.addEventListener(ev, () => dropArea.classList.remove("border-primary"))
    );
    dropArea.addEventListener("drop", e => {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event("change"));
    });

    modal.addEventListener("hidden.bs.modal", function() {
        selected = [];
        fileInput.value = "";
        torrentInput.value = "";
        renderPreview();
        if (progressWrap) progressWrap.style.display = "none";
    });

    startBtn.addEventListener("click", function() {
        if (!selected.length || !torrentInput.value.trim()) return;

        const formData = new FormData();
        selected.forEach(f => formData.append("screenshots[]", f));
        formData.append("torrent_id", torrentInput.value.trim());
        formData.append("my_post_key", my_post_key);

        startBtn.disabled = true;
        startBtn.querySelector(".upload-text").classList.add("d-none");
        startBtn.querySelector(".upload-loading").classList.remove("d-none");

        if (progressWrap) {
            progressWrap.style.display = "block";
            progressBar.style.width = "0%";
        }
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            if (progressBar && progress <= 90) progressBar.style.width = progress + "%";
        }, 200);

        fetch(scr_script + "&action=ajax_upload", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            clearInterval(progressInterval);
            if (progressBar) progressBar.style.width = "100%";

            if (data.status === "success" || data.status === "partial") {
                if (typeof showToast === "function") {
                    showToast(data.message, data.status === "success" ? "success" : "warning");
                }
                setTimeout(() => {
                    const inst = bootstrap.Modal.getInstance(modal);
                    if (inst) inst.hide();
                    location.reload();
                }, 1200);
            } else {
                if (typeof showToast === "function") {
                    showToast(data.message || "Upload failed.", "error");
                } else {
                    alert(data.message || "Upload failed.");
                }
                if (progressWrap) progressWrap.style.display = "none";
            }
        })
        .catch(err => {
            clearInterval(progressInterval);
            console.error("Upload error:", err);
            if (typeof showToast === "function") {
                showToast("Server not responding.", "error");
            }
            if (progressWrap) progressWrap.style.display = "none";
        })
        .finally(() => {
            startBtn.disabled = false;
            startBtn.querySelector(".upload-text").classList.remove("d-none");
            startBtn.querySelector(".upload-loading").classList.add("d-none");
        });
    });
})();
</script>';

    echo '<script>
(function() {
    const editModal   = document.getElementById("editScreenshotModal");
    const idInput     = document.getElementById("editScreenshotId");
    const torrentInput = document.getElementById("editTorrentId");
    const fileInput    = document.getElementById("editScreenshotFile");
    const preview      = document.getElementById("editImagePreview");
    const currentInfo  = document.getElementById("editCurrentFilename");
    const saveBtn       = document.getElementById("editSaveBtn");

    editModal.addEventListener("show.bs.modal", function(event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        idInput.value = trigger.getAttribute("data-id") || "";
        torrentInput.value = trigger.getAttribute("data-torrent-id") || "";
        preview.src = trigger.getAttribute("data-img-src") || "";
        currentInfo.textContent = "Leave empty to keep: " + (trigger.getAttribute("data-filename") || "");
        fileInput.value = "";
    });

    fileInput.addEventListener("change", function() {
        const f = this.files[0];
        if (f && f.type.match("image.*")) {
            const r = new FileReader();
            r.onload = e => preview.src = e.target.result;
            r.readAsDataURL(f);
        }
    });

    saveBtn.addEventListener("click", function() {
        if (!torrentInput.value.trim()) {
            if (typeof showToast === "function") showToast("Torrent ID is required", "error");
            return;
        }

        const formData = new FormData();
        formData.append("id", idInput.value);
        formData.append("torrent_id", torrentInput.value.trim());
        formData.append("my_post_key", my_post_key);
        if (fileInput.files[0]) {
            formData.append("screenshot", fileInput.files[0]);
        }

        saveBtn.disabled = true;
        saveBtn.querySelector(".save-text").classList.add("d-none");
        saveBtn.querySelector(".save-loading").classList.remove("d-none");

        fetch(scr_script + "&action=ajax_edit", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                if (typeof showToast === "function") showToast(data.message, "success");
                setTimeout(() => {
                    const inst = bootstrap.Modal.getInstance(editModal);
                    if (inst) inst.hide();
                    location.reload();
                }, 1000);
            } else {
                if (typeof showToast === "function") {
                    showToast(data.message || "Update failed.", "error");
                } else {
                    alert(data.message || "Update failed.");
                }
            }
        })
        .catch(err => {
            console.error("Edit error:", err);
            if (typeof showToast === "function") showToast("Server not responding.", "error");
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.querySelector(".save-text").classList.remove("d-none");
            saveBtn.querySelector(".save-loading").classList.add("d-none");
        });
    });
})();
</script>';

    echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
    echo '<script src="' . $BASEURL . '/scripts/details_modal.js"></script>';
    echo '<script src="' . $BASEURL . '/admin/scripts/manage_screenshots.js"></script>';
    echo '</div>'; // container
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE ADD
// ═══════════════════════════════════════════════════════════
function handle_add(): void
{
    global $db, $_this_script_, $CURUSER, $mybb;

    $error   = null;
    $success_count = 0;
    $errors  = [];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        scr_verify_csrf();

        $torrent_id = (int)$_POST['torrent_id'];

        $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
        if ($torrent_id <= 0 || !$torrent_check || $db->num_rows($torrent_check) === 0) {
            $error = "Torrent ID {$torrent_id} does not exist";
        } else {

        // Нормализуем $_FILES['screenshot'] в плоский список файлов -
        // одинаково обрабатываем и одиночный (без multiple), и множественный
        // выбор, раз браузер отдаёт разную структуру массива для name="screenshot[]".
        $files = [];
        if (isset($_FILES['screenshot']) && is_array($_FILES['screenshot']['name'] ?? null)) {
            foreach ($_FILES['screenshot']['name'] as $i => $name) {
                if ($name === '') continue; // пустой слот (не выбран файл в этой позиции)
                $files[] = [
                    'name'     => $name,
                    'type'     => $_FILES['screenshot']['type'][$i],
                    'tmp_name' => $_FILES['screenshot']['tmp_name'][$i],
                    'error'    => $_FILES['screenshot']['error'][$i],
                    'size'     => $_FILES['screenshot']['size'][$i],
                ];
            }
        } elseif (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
            $files[] = $_FILES['screenshot'];
        }

        if (empty($files)) {
            $error = 'No file uploaded or upload error';
        } else {
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "{$file['name']}: upload error (code {$file['error']})";
                    continue;
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $errors[] = "{$file['name']}: invalid format. Allowed: " . implode(', ', $allowed_ext);
                    continue;
                }

                if (!($info = getimagesize($file['tmp_name']))) {
                    $errors[] = "{$file['name']}: not a valid image";
                    continue;
                }

                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                $path     = get_upload_path($filename);

                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    $errors[] = "{$file['name']}: file upload failed";
                    continue;
                }

                // Перекодирование через GD — защита от "полиглот"-файлов.
                if (recode_image_file($path, $info['mime']) === false) {
                    @unlink($path);
                    $errors[] = "{$file['name']}: corrupted or invalid image data";
                    continue;
                }

                $size = round(filesize($path) / 1024, 2);
                $dims = $info[0] . 'x' . $info[1];

                $db->sql_query_prepared(
                    'INSERT INTO screenshots (torrent_id, filename, uploaded_at) VALUES (?, ?, ?)',
                    [$torrent_id, $filename, time()]
                );

                write_log("Screenshot uploaded: Torrent #{$torrent_id} | {$filename} | {$size}KB | {$dims} | {$CURUSER['username']}");
                $success_count++;
            }

            if ($success_count > 0 && empty($errors)) {
                header('Location: ' . $_this_script_);
                exit;
            }

            if ($success_count > 0) {
                $error = "{$success_count} screenshot(s) uploaded successfully. Errors: " . implode('; ', $errors);
            } else {
                $error = implode('; ', $errors);
            }
        }
        } // конец else (torrent_id валиден)

        if ($error) {
            write_log("[SCREENSHOT UPLOAD ERROR] Torrent #{$_POST['torrent_id']} | {$CURUSER['username']} | {$error}");
        }
    }

    stdhead('Add Screenshot');
    echo '<div class="container mt-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1><i class="fas fa-plus-circle me-2"></i>Add Screenshot</h1>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
    echo '</div>';
    if ($error) echo '<div class="alert ' . ($success_count > 0 ? 'alert-warning' : 'alert-danger') . '">' . htmlspecialchars($error) . '</div>';

    echo '<div class="card border-0 shadow-sm"><div class="card-body">';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">';
    echo '<div class="row g-4 mb-4">';

    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" required>';
    echo '<label>Torrent ID</label></div>';
    echo '<div class="mb-3">';
    echo '<label class="form-label">Screenshot File(s)</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot[]" accept="image/*" multiple required>';
    echo '<div class="form-text">Allowed: ' . implode(', ', $allowed_ext) . ' · You can select multiple files at once</div>';
    echo '</div></div>';

    echo '<div class="col-lg-6">';
    echo '<div class="border rounded-3 p-4 text-center bg-light" style="min-height:250px" id="dropArea">';
    echo '<div id="previewGrid" class="d-flex flex-wrap gap-2 justify-content-center"></div>';
    echo '<div id="placeholderText" class="text-muted py-5">';
    echo '<i class="fas fa-cloud-upload-alt fa-3x mb-3 opacity-50"></i>';
    echo '<p>Image preview will appear here</p></div></div></div>';

    echo '</div>';
    echo '<div class="d-flex justify-content-end">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Screenshot(s)</button>';
    echo '</div></form></div></div></div>';

    echo '<script>
const fi=document.getElementById("screenshot"),grid=document.getElementById("previewGrid"),ph=document.getElementById("placeholderText");
fi.addEventListener("change",function(){
    grid.innerHTML="";
    const files=[...this.files];
    if(!files.length){ph.style.display="block";return;}
    ph.style.display="none";
    files.forEach(f=>{
        if(!f.type.match("image.*"))return;
        const r=new FileReader();
        r.onload=e=>{
            const img=document.createElement("img");
            img.src=e.target.result;
            img.className="rounded border";
            img.style.cssText="max-height:100px;max-width:140px;object-fit:cover";
            grid.appendChild(img);
        };
        r.readAsDataURL(f);
    });
});
const da=document.getElementById("dropArea");
["dragenter","dragover","dragleave","drop"].forEach(ev=>da.addEventListener(ev,e=>{e.preventDefault();e.stopPropagation();}));
["dragenter","dragover"].forEach(ev=>da.addEventListener(ev,()=>da.classList.add("border-primary")));
["dragleave","drop"].forEach(ev=>da.addEventListener(ev,()=>da.classList.remove("border-primary")));
da.addEventListener("drop",e=>{fi.files=e.dataTransfer.files;fi.dispatchEvent(new Event("change"));});
</script>';

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE EDIT
// ═══════════════════════════════════════════════════════════
function handle_edit(): void
{
    global $db, $_this_script_, $CURUSER, $mybb;

    $id  = (int)($_GET['id'] ?? 0);
    $res = $db->sql_query_prepared("SELECT * FROM screenshots WHERE id = ?", [$id]);
    $row = $db->fetch_array($res);

    if (!$row) {
        echo '<div class="container mt-3"><div class="alert alert-danger">Screenshot not found</div></div>';
        stdfoot();
        return;
    }

    $error    = null;
    $filename = $row['filename'];
    $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        scr_verify_csrf();

        $torrent_id = (int)$_POST['torrent_id'];
        $changes    = [];

        $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
        if ($torrent_id <= 0 || !$torrent_check || $db->num_rows($torrent_check) === 0) {
            $error = "Torrent ID {$torrent_id} does not exist";
        }

        if (!$error && $row['torrent_id'] != $torrent_id) {
            $changes[] = "Torrent ID {$row['torrent_id']} → {$torrent_id}";
        }

        if (!$error && isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            $screenshotInfo = @getimagesize($_FILES['screenshot']['tmp_name']);
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid format. Allowed: ' . implode(', ', $allowed);
            } elseif (!$screenshotInfo) {
                $error = 'File is not a valid image';
            } else {
                $old      = get_upload_path($row['filename']);
                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                $newPath  = get_upload_path($filename);

                if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $newPath)) {
                    $error    = 'File upload failed';
                    $filename = $row['filename'];
                } elseif (recode_image_file($newPath, $screenshotInfo['mime']) === false) {
                    // Перекодирование через GD — защита от "полиглот"-файлов.
                    @unlink($newPath);
                    $error    = 'File is corrupted or is not a valid image';
                    $filename = $row['filename'];
                } else {
                    if (file_exists($old)) unlink($old);
                    $changes[] = "File {$row['filename']} → {$filename}";
                }
            }
        }

        if (!$error) {
            $db->sql_query_prepared(
                "UPDATE screenshots SET torrent_id = ?, filename = ? WHERE id = ?",
                [$torrent_id, $filename, $id]
            );
            if ($changes) write_log("Screenshot updated: ID:{$id} | " . implode(', ', $changes) . " | {$CURUSER['username']}");
            header('Location: ' . $_this_script_);
            exit;
        }
    }

    stdhead('Edit Screenshot');
    echo '<div class="container mt-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h1><i class="fas fa-edit me-2"></i>Edit Screenshot</h1>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>';
    echo '</div>';
    if ($error) echo '<div class="alert alert-danger">' . $error . '</div>';

    echo '<div class="card border-0 shadow-sm"><div class="card-body">';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">';
    echo '<div class="row g-4 mb-4">';

    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" name="torrent_id" value="' . $row['torrent_id'] . '" required>';
    echo '<label>Torrent ID</label></div>';
    echo '<div class="mb-3">';
    echo '<label class="form-label">New Screenshot File</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*">';
    echo '<div class="form-text">Leave empty to keep: ' . htmlspecialchars($row['filename']) . '</div>';
    echo '</div></div>';

    echo '<div class="col-lg-6">';
    echo '<div class="border rounded-3 p-4 text-center bg-light" style="min-height:250px">';
    echo '<img id="imagePreview" src="' . get_image_path($row['filename']) . '" class="img-fluid" style="max-height:220px" alt="Preview">';
    echo '</div></div>';

    echo '</div>';
    echo '<div class="d-flex justify-content-end">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>';
    echo '</div></form></div></div></div>';

    echo '<script>
document.getElementById("screenshot").addEventListener("change",function(){
    const f=this.files[0];
    if(f&&f.type.match("image.*")){const r=new FileReader();r.onload=e=>document.getElementById("imagePreview").src=e.target.result;r.readAsDataURL(f);}
});
</script>';

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE DELETE (AJAX + regular)
// ═══════════════════════════════════════════════════════════
function handle_delete(): void
{
    global $db, $CURUSER, $_this_script_, $mybb;

    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'This action requires a POST request']);
            exit;
        }
        header('Location: ' . $_this_script_);
        exit;
    }

    scr_verify_csrf();

    $id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    $res        = $db->sql_query_prepared("SELECT filename, torrent_id FROM screenshots WHERE id = ?", [$id]);
    $screenshot = $db->fetch_array($res);

    if (!$screenshot) {
        write_log("[SCREENSHOT] Not found: ID={$id} by {$CURUSER['username']}");
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Screenshot not found']);
            exit;
        }
        header('Location: ' . $_this_script_);
        exit;
    }

    $path = get_upload_path($screenshot['filename']);
    $log  = file_exists($path) ? (unlink($path) ? 'file deleted' : 'file delete failed') : 'file not found';
    $db->sql_query_prepared("DELETE FROM screenshots WHERE id = ?", [$id]);

    write_log("Screenshot deleted: Torrent #{$screenshot['torrent_id']} | {$screenshot['filename']} | {$log} | {$CURUSER['username']} | {$_SERVER['REMOTE_ADDR']}");

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Screenshot deleted', 'deleted_id' => $id]);
        exit;
    }

    header('Location: ' . $_this_script_);
    exit;
}