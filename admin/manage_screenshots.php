<?php
require_once INC_PATH . '/functions_multipage.php';

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
    $q = $db->sql_query("SELECT filename FROM screenshots WHERE torrent_id='{$torrent_id}'");
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
    header('Content-Type: application/json');
    scr_verify_csrf();

    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No screenshots selected']);
        exit;
    }

    $q = $db->sql_query("SELECT id, filename FROM screenshots WHERE id IN (" . implode(',', $ids) . ")");
    $to_delete = [];
    while ($row = $db->fetch_array($q)) {
        $to_delete[$row['id']] = $row['filename'];
    }

    $deleted = [];
    $errors  = [];
    foreach ($to_delete as $id => $filename) {
        $path = get_upload_path($filename);
        $file_ok = !file_exists($path) || unlink($path);
        $db->sql_query("DELETE FROM screenshots WHERE id=" . (int)$id);
        $db_ok = $db->affected_rows() > 0;

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

    $where = [];
    if ($search)     $where[] = "filename LIKE '%" . $db->escape_string($search) . "%'";
    if ($torrent_id) $where[] = "torrent_id=" . (int)$torrent_id;
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $row   = mysqli_fetch_row($db->sql_query("SELECT COUNT(*) FROM screenshots $where_sql"));
    $count = (int)$row[0];

    [$page, $perpage] = scr_page_params();
    $pages = max(1, (int)ceil($count / $perpage));
    $page  = max(1, min($page, $pages));
    $start = ($page - 1) * $perpage;

    $page_url = $_this_script_
        . ($search     ? '&search='     . urlencode($search)     : '')
        . ($torrent_id ? '&torrent_id=' . (int)$torrent_id       : '');

    $result = $db->sql_query("SELECT * FROM screenshots $where_sql ORDER BY uploaded_at DESC LIMIT $start,$perpage");

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
    echo '<a href="' . $_this_script_ . '&action=add" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New</a>';
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
        echo '<div class="alert alert-info">No screenshots found</div>';
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
            echo '<li><a class="dropdown-item" href="' . $_this_script_ . '&action=edit&id=' . $row['id'] . '"><i class="fas fa-edit me-2"></i>Edit</a></li>';
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
    global $db, $_this_script_, $CURUSER;

    $error = null;
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $torrent_id = (int)$_POST['torrent_id'];

        if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
            $error = 'No file uploaded or upload error';
        } else {
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $error = 'Invalid format. Allowed: ' . implode(', ', $allowed_ext);
            } else {
                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                $path     = get_upload_path($filename);

                if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $path)) {
                    $error = 'File upload failed';
                } else {
                    $size   = round(filesize($path) / 1024, 2);
                    $info   = getimagesize($path);
                    $dims   = $info ? $info[0] . 'x' . $info[1] : 'unknown';
                    $db->insert_query('screenshots', [
                        'torrent_id'  => $torrent_id,
                        'filename'    => $filename,
                        'uploaded_at' => time(),
                    ]);
                    write_log("Screenshot uploaded: Torrent #{$torrent_id} | {$filename} | {$size}KB | {$dims} | {$CURUSER['username']}");
                    header('Location: ' . $_this_script_);
                    exit;
                }
            }
        }
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
    if ($error) echo '<div class="alert alert-danger">' . $error . '</div>';

    echo '<div class="card border-0 shadow-sm"><div class="card-body">';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<div class="row g-4 mb-4">';

    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" required>';
    echo '<label>Torrent ID</label></div>';
    echo '<div class="mb-3">';
    echo '<label class="form-label">Screenshot File</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*" required>';
    echo '<div class="form-text">Allowed: ' . implode(', ', $allowed_ext) . '</div>';
    echo '</div></div>';

    echo '<div class="col-lg-6">';
    echo '<div class="border rounded-3 p-4 text-center bg-light" style="min-height:250px" id="dropArea">';
    echo '<img id="imagePreview" src="" class="img-fluid" style="max-height:220px;display:none" alt="Preview">';
    echo '<div id="placeholderText" class="text-muted py-5">';
    echo '<i class="fas fa-cloud-upload-alt fa-3x mb-3 opacity-50"></i>';
    echo '<p>Image preview will appear here</p></div></div></div>';

    echo '</div>';
    echo '<div class="d-flex justify-content-end">';
    echo '<button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Screenshot</button>';
    echo '</div></form></div></div></div>';

    echo '<script>
const fi=document.getElementById("screenshot"),pr=document.getElementById("imagePreview"),ph=document.getElementById("placeholderText");
fi.addEventListener("change",function(){
    const f=this.files[0];
    if(f&&f.type.match("image.*")){const r=new FileReader();r.onload=e=>{pr.src=e.target.result;pr.style.display="block";ph.style.display="none";};r.readAsDataURL(f);}
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
    global $db, $_this_script_, $CURUSER;

    $id  = (int)($_GET['id'] ?? 0);
    $res = $db->sql_query("SELECT * FROM screenshots WHERE id={$id}");
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
        $torrent_id = (int)$_POST['torrent_id'];
        $changes    = [];

        if ($row['torrent_id'] != $torrent_id) {
            $changes[] = "Torrent ID {$row['torrent_id']} → {$torrent_id}";
        }

        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid format. Allowed: ' . implode(', ', $allowed);
            } else {
                $old = get_upload_path($row['filename']);
                if (file_exists($old)) unlink($old);
                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], get_upload_path($filename))) {
                    $error    = 'File upload failed';
                    $filename = $row['filename'];
                } else {
                    $changes[] = "File {$row['filename']} → {$filename}";
                }
            }
        }

        if (!$error) {
            $db->sql_query("UPDATE screenshots SET torrent_id={$torrent_id}, filename='" . $db->escape_string($filename) . "' WHERE id={$id}");
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        scr_verify_csrf();
    }

    $id      = (int)($_GET['id'] ?? 0);
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

    $res        = $db->sql_query("SELECT filename, torrent_id FROM screenshots WHERE id={$id}");
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
    $db->sql_query("DELETE FROM screenshots WHERE id={$id}");

    write_log("Screenshot deleted: Torrent #{$screenshot['torrent_id']} | {$screenshot['filename']} | {$log} | {$CURUSER['username']} | {$_SERVER['REMOTE_ADDR']}");

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Screenshot deleted', 'deleted_id' => $id]);
        exit;
    }

    header('Location: ' . $_this_script_);
    exit;
}