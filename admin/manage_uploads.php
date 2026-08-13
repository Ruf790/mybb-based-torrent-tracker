<?php

declare(strict_types=1);


$rootpath = './../';

if (!defined('IN_ADMINCP')) {
    define('IN_ADMINCP', true);
}

require_once $rootpath . 'global.php';

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Error! You do not have permission to access this page.</div>');
}



require_once INC_PATH . '/functions_multipage.php';

// ── Общая карта полиморфных типов контента ───────────────
function cf_content_map(): array
{
    return [
        'comment' => ['fk' => 'comment_id',  'table' => 'comments',        'idField' => 'id',   'textField' => 'text'],
        'news'    => ['fk' => 'news_id',     'table' => 'news',            'idField' => 'id',   'textField' => 'body'],
        'torrent' => ['fk' => 'torrent_id',  'table' => 'torrents',        'idField' => 'id',   'textField' => 'descr'],
        'post'    => ['fk' => 'post_id',     'table' => 'posts',           'idField' => 'pid',  'textField' => 'message'],
        'message' => ['fk' => 'messages_id', 'table' => 'privatemessages', 'idField' => 'pmid', 'textField' => 'message'],
    ];
}

function cf_target_exists(object $db, string $contentType, int $contentId): bool
{
    $map = cf_content_map();
    if (!isset($map[$contentType]) || $contentId <= 0) return false;
    $t = $map[$contentType];
    $res = $db->sql_query_prepared("SELECT {$t['idField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
    return $res && $db->num_rows($res) > 0;
}

// ── Edit (чинил - раньше форма слала update=1, но обработчика не было вообще) ──
if (isset($_POST['update']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file ID']);
        exit;
    }

    $map = cf_content_map();
    $provided = [];
    foreach ($map as $type => $t) {
        $val = $_POST[$t['fk']] ?? '';
        if ($val !== '') {
            $provided[$type] = (int)$val;
        }
    }

    if (count($provided) > 1) {
        echo json_encode(['status' => 'error', 'message' => 'Only one content link (Comment/News/Torrent/Post) can be set at a time']);
        exit;
    }

    foreach ($provided as $type => $cid) {
        if (!cf_target_exists($db, $type, $cid)) {
            echo json_encode(['status' => 'error', 'message' => ucfirst($type) . " with ID {$cid} does not exist"]);
            exit;
        }
    }

    $file_name = trim($_POST['file_name'] ?? '');
    $user_id   = (int)($_POST['user_id'] ?? 0);

    $set = ['file_name = ?', 'user_id = ?', 'comment_id = ?', 'news_id = ?', 'torrent_id = ?', 'post_id = ?'];
    $params = [
        $file_name,
        $user_id > 0 ? $user_id : null,
        $provided['comment'] ?? null,
        $provided['news'] ?? null,
        $provided['torrent'] ?? null,
        $provided['post'] ?? null,
        $id,
    ];

    $db->sql_query_prepared('UPDATE comment_files SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    write_log("comment_files updated: ID:{$id} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => 'File details updated']);
    exit;
}

// ── Move (заменяет текущую привязку на новую; старая полностью очищается) ──
if (isset($_POST['ajax_move']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $contentType = $_POST['content_type'] ?? '';
    $contentId   = (int)($_POST['content_id'] ?? 0);
    $insertTag   = !empty($_POST['insert_tag']) && $contentType !== 'message';

    $map = cf_content_map();
    if (!isset($map[$contentType])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid content type']);
        exit;
    }

    if (!cf_target_exists($db, $contentType, $contentId)) {
        echo json_encode(['status' => 'error', 'message' => ucfirst($contentType) . " with ID {$contentId} does not exist"]);
        exit;
    }

    $res = $db->sql_query_prepared('SELECT * FROM comment_files WHERE id = ?', [$id]);
    $file = $res ? $db->fetch_array($res) : null;
    if (!$file) {
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
        exit;
    }

    // ── Убираем [img]-тег из старой цели, если файл был куда-то привязан ──
    // Тот же паттерн, что уже используется в delete-обработчике этого файла.
    foreach ($map as $oldType => $t) {
        if ($oldType === 'message') continue; // PM-текст не трогаем автоматически
        if (empty($file[$t['fk']])) continue;

        $oldId = (int)$file[$t['fk']];
        $res2  = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$oldId]);
        $row2  = $res2 ? $db->fetch_array($res2) : null;
        if ($row2 === null) continue;

        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\]]*\[\/img\]/i';
        $current = $row2[$t['textField']] ?? '';
        $updated = preg_replace($pattern, '', $current);
        $updated = trim(preg_replace('/\n{3,}/', "\n\n", $updated ?? ''));

        if ($updated !== trim($current)) {
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $oldId]);
            if ($oldType === 'news') $cache->update_news();
        }
        break; // ровно один FK может быть задан за раз (полиморфная эксклюзивность)
    }

    $fk = $map[$contentType]['fk'];
    $db->sql_query_prepared(
        "UPDATE comment_files SET comment_id = NULL, news_id = NULL, torrent_id = NULL, post_id = NULL, messages_id = NULL, {$fk} = ? WHERE id = ?",
        [$contentId, $id]
    );

    // ── Опционально вставляем [img]-тег в текст новой цели ──────────────
    if ($insertTag) {
        $t   = $map[$contentType];
        $res3 = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
        $row3 = $res3 ? $db->fetch_array($res3) : null;
        if ($row3 !== null) {
            $current = $row3[$t['textField']] ?? '';
            $updated = $current !== '' ? $current . "\n\n" . '[img]' . $file['file_url'] . '[/img]' : '[img]' . $file['file_url'] . '[/img]';
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $contentId]);
            if ($contentType === 'news') $cache->update_news();
        }
    }

    write_log("comment_files moved: ID:{$id} -> {$contentType} #{$contentId} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => 'File moved successfully']);
    exit;
}

// ── Copy (физическое дублирование файла + новая строка на другой/тот же контент) ──
if (isset($_POST['ajax_copy']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $contentType = $_POST['content_type'] ?? '';
    $contentId   = (int)($_POST['content_id'] ?? 0);
    $insertTag   = !empty($_POST['insert_tag']) && $contentType !== 'message'; // PM-текст не трогаем автоматически

    $map = cf_content_map();
    if (!isset($map[$contentType])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid content type']);
        exit;
    }

    if (!cf_target_exists($db, $contentType, $contentId)) {
        echo json_encode(['status' => 'error', 'message' => ucfirst($contentType) . " with ID {$contentId} does not exist"]);
        exit;
    }

    $res = $db->sql_query_prepared('SELECT * FROM comment_files WHERE id = ?', [$id]);
    $file = $res ? $db->fetch_array($res) : null;
    if (!$file || !is_file($file['file_path'])) {
        echo json_encode(['status' => 'error', 'message' => 'Source file not found on disk']);
        exit;
    }

    $ext         = pathinfo($file['file_path'], PATHINFO_EXTENSION);
    $newFileName = bin2hex(random_bytes(16)) . ($ext !== '' ? ".{$ext}" : '');
    $newPath     = dirname($file['file_path']) . '/' . $newFileName;

    if (!@copy($file['file_path'], $newPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to copy file on disk']);
        exit;
    }

    $newUrl = rtrim(str_replace(basename($file['file_url']), '', $file['file_url']), '/') . '/' . $newFileName;
    $fk     = $map[$contentType]['fk'];

    $db->sql_query_prepared(
        "INSERT INTO comment_files (file_name, file_path, file_url, file_type, file_size, user_id, {$fk}, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$file['file_name'], $newPath, $newUrl, $file['file_type'], (int)$file['file_size'], (int)($CURUSER['id'] ?? 0), $contentId]
    );
    $newId = $db->insert_id();

    if ($insertTag) {
        $t = $map[$contentType];
        $res = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
        $row = $res ? $db->fetch_array($res) : null;
        if ($row !== null) {
            $current = $row[$t['textField']] ?? '';
            $updated = $current !== '' ? $current . "\n\n" . '[img]' . $newUrl . '[/img]' : '[img]' . $newUrl . '[/img]';
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $contentId]);
            if ($contentType === 'news') $cache->update_news();
        }
    }

    write_log("comment_files copied: ID:{$id} -> new ID:{$newId} -> {$contentType} #{$contentId} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => 'File copied successfully', 'new_id' => $newId]);
    exit;
}

// ── Single delete (AJAX GET) ─────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_GET['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $file_id = (int)$_GET['delete'];

    $res = $db->sql_query_prepared("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        LEFT JOIN news n ON n.id = cf.news_id
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        LEFT JOIN posts p ON p.pid = cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
        WHERE cf.id = ?
    ", [$file_id]);

    if ($res && ($file = $db->fetch_array($res))) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\[]*\[\/img\]/i';

        $updates = [
            ['comment_id', 'comments',       'text',    'comment_text'],
            ['news_id',    'news',            'body',    'news_text'],
            ['torrent_id', 'torrents',        'descr',   'torrent_description'],
            ['post_id',    'posts',       'message', 'post_message'],
            ['messages_id','privatemessages', 'message', 'pm_message'],
        ];
        $pk = ['comment_id'=>'id','news_id'=>'id','torrent_id'=>'id','post_id'=>'pid','messages_id'=>'pmid'];

        foreach ($updates as [$fk, $table, $col, $textKey]) {
            if (!empty($file[$fk])) {
                $new = preg_replace($pattern, '[Image Deleted]', $file[$textKey]);
                if ($new !== $file[$textKey]) {
                    $db->sql_query_prepared("UPDATE $table SET $col = ? WHERE {$pk[$fk]} = ?", [$new, (int)$file[$fk]]);
                    if ($table === 'news') $cache->update_news();
                }
            }
        }

        $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [$file_id]);
        echo json_encode(['status'=>'success','id'=>$file_id,'message'=>'File deleted']);
    } else {
        echo json_encode(['status'=>'error','message'=>'File not found']);
    }
    exit;
}

// ── Bulk delete (AJAX POST) ──────────────────────────────
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $selected = $_POST['selected_files'] ?? [];
    if (is_string($selected)) $selected = $selected !== '' ? [$selected] : [];
    $ids = array_filter(array_map('intval', (array)$selected));

    if (empty($ids)) {
        echo json_encode(['status'=>'error','message'=>'No files selected']);
        exit;
    }

    $ids_ph = implode(',', array_fill(0, count($ids), '?'));
    $res = $db->sql_query_prepared("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id=cf.comment_id
        LEFT JOIN news n ON n.id=cf.news_id
        LEFT JOIN torrents t ON t.id=cf.torrent_id
        LEFT JOIN posts p ON p.pid=cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid=cf.messages_id
        WHERE cf.id IN ({$ids_ph})
    ", $ids);

    $affected = ['comments'=>[],'news'=>[],'torrents'=>[],'posts'=>[],'privatemessages'=>[]];
    $pk_map   = ['comments'=>'id','news'=>'id','torrents'=>'id','posts'=>'pid','privatemessages'=>'pmid'];
    $col_map  = ['comments'=>'text','news'=>'body','torrents'=>'descr','posts'=>'message','privatemessages'=>'message'];
    $fk_map   = ['comments'=>'comment_id','news'=>'news_id','torrents'=>'torrent_id','posts'=>'post_id','privatemessages'=>'messages_id'];
    $txt_map  = ['comments'=>'comment_text','news'=>'news_text','torrents'=>'torrent_description','posts'=>'post_message','privatemessages'=>'pm_message'];
    $deleted_ids = [];

    while ($res && ($file = $db->fetch_array($res))) {
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
            $db->sql_query_prepared("UPDATE $table SET $col = ? WHERE $pk = ?", [$text, $id]);
            if ($table === 'news') $cache->update_news();
        }
    }

    $db->sql_query_prepared("DELETE FROM comment_files WHERE id IN ({$ids_ph})", $ids);
    echo json_encode(['status'=>'success','message'=>'Files deleted','deleted_ids'=>$deleted_ids]);
    exit;
}

// ── List page setup ──────────────────────────────────────
$is_ajax   = isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1';
$per_page  = $ts_perpage;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;
$search    = trim($_GET['search'] ?? '');
$typeFilter= trim($_GET['type']   ?? '');

$where = [];
$where_params = [];
if ($search) {
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $where[] = "(file_name LIKE ? OR file_type LIKE ?)";
    array_push($where_params, "%$like%", "%$like%");
}
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

$total_q = $db->sql_query_prepared("SELECT COUNT(*) as total FROM comment_files $whereClause", $where_params);
$total_row   = $total_q ? $db->fetch_array($total_q) : null;
$total_files = $total_row['total'] ?? 0;
$total_pages = ceil($total_files / $per_page);

$result = $db->sql_query_prepared("
    SELECT comment_files.*, users.username, users.usergroup, users.avatar, users.avatardimensions
    FROM comment_files
    LEFT JOIN users ON users.id = comment_files.user_id
    $whereClause
    ORDER BY comment_files.uploaded_at DESC
    LIMIT ?, ?
", [...$where_params, $offset, $per_page]);

$files = [];
while ($result && ($row = $db->fetch_array($result))) $files[] = $row;

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
            <i class="bi bi-database me-1"></i> <?= ts_nf($total_files) ?> files
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
                <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
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
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i> Edit File Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label fw-medium">File Name</label>
                    <input type="text" class="form-control border-0 bg-light" id="editFileName">
                </div>
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i> Only one of Comment/News/Torrent/Post can be set at a time. Leave the others empty.
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium"><i class="bi bi-chat-square-text me-1"></i>Comment ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="editCommentId">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium"><i class="bi bi-newspaper me-1"></i>News ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="editNewsId">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium"><i class="bi bi-download me-1"></i>Torrent ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="editTorrentId">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium"><i class="bi bi-card-text me-1"></i>Post ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="editPostId">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium"><i class="bi bi-person me-1"></i>User ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="editUserId">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary px-4 position-relative" id="editSaveBtn">
                    <span class="save-text"><i class="bi bi-check-lg me-1"></i> Save Changes</span>
                    <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-arrows-move text-primary"></i> Move File
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="moveId">
                <div class="text-center mb-4">
                    <img id="movePreviewImg" src="" class="rounded border" style="max-height:140px;max-width:100%" alt="Preview">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-tag me-1"></i>New Content Type</label>
                        <select class="form-select border-0 bg-light" id="moveContentType" required>
                            <option value="" selected disabled>Choose type...</option>
                            <option value="comment">💬 Comment</option>
                            <option value="news">📰 News</option>
                            <option value="torrent">⬇️ Torrent</option>
                            <option value="post">📄 Post</option>
                            <option value="message">✉️ Message</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-hash me-1"></i>New Content ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="moveContentId" placeholder="Enter ID" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary mb-2"><i class="bi bi-code-slash me-1"></i>BBCode Tag</label>
                    <div class="input-group">
                        <input type="text" class="form-control border-0 bg-light" id="moveBbcodeTag" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="moveCopyBbcodeBtn" title="Copy to clipboard">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="moveInsertTag" checked>
                    <label class="form-check-label" for="moveInsertTag">
                        Also insert this BBCode tag into the new location automatically
                    </label>
                    <div class="form-text" id="moveInsertTagNote">The tag is always removed from the old location.</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary px-4 position-relative" id="moveSaveBtn">
                    <span class="save-text"><i class="bi bi-arrows-move me-1"></i> Move</span>
                    <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Moving...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Copy Modal -->
<div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-files text-primary"></i> Copy File
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="copyId">
                <div class="text-center mb-4">
                    <img id="copyPreviewImg" src="" class="rounded border" style="max-height:140px;max-width:100%" alt="Preview">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-tag me-1"></i>Target Content Type</label>
                        <select class="form-select border-0 bg-light" id="copyContentType" required>
                            <option value="" selected disabled>Choose type...</option>
                            <option value="comment">💬 Comment</option>
                            <option value="news">📰 News</option>
                            <option value="torrent">⬇️ Torrent</option>
                            <option value="post">📄 Post</option>
                            <option value="message">✉️ Message</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary mb-2"><i class="bi bi-hash me-1"></i>Target Content ID</label>
                        <input type="number" class="form-control border-0 bg-light" id="copyContentId" placeholder="Enter ID" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary mb-2"><i class="bi bi-code-slash me-1"></i>BBCode Tag</label>
                    <div class="input-group">
                        <input type="text" class="form-control border-0 bg-light" id="copyBbcodeTag" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyCopyBbcodeBtn" title="Copy to clipboard">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="copyInsertTag" checked>
                    <label class="form-check-label" for="copyInsertTag">
                        Also insert this BBCode tag into the target's text automatically
                    </label>
                    <div class="form-text" id="copyInsertTagNote"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary px-4 position-relative" id="copySaveBtn">
                    <span class="save-text"><i class="bi bi-files me-1"></i> Copy</span>
                    <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Copying...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const myPostKey = <?= json_encode($mybb->post_code ?? '') ?>;
    const scriptUrl  = <?= json_encode($_this_script_) ?>;

    function toast(msg, type) {
        if (typeof showToast === 'function') { showToast(msg, type); }
        else { alert(msg); }
    }

    // ── Edit ──────────────────────────────────────────────
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('editId').value = t.getAttribute('data-id') || '';
        document.getElementById('editFileName').value = t.getAttribute('data-file-name') || '';
        document.getElementById('editCommentId').value = t.getAttribute('data-comment-id') || '';
        document.getElementById('editNewsId').value = t.getAttribute('data-news-id') || '';
        document.getElementById('editTorrentId').value = t.getAttribute('data-torrent-id') || '';
        document.getElementById('editPostId').value = t.getAttribute('data-post-id') || '';
        document.getElementById('editUserId').value = t.getAttribute('data-user-id') || '';
    });

    document.getElementById('editSaveBtn').addEventListener('click', function() {
        const btn = this;
        const formData = new FormData();
        formData.append('update', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('editId').value);
        formData.append('file_name', document.getElementById('editFileName').value);
        formData.append('comment_id', document.getElementById('editCommentId').value);
        formData.append('news_id', document.getElementById('editNewsId').value);
        formData.append('torrent_id', document.getElementById('editTorrentId').value);
        formData.append('post_id', document.getElementById('editPostId').value);
        formData.append('user_id', document.getElementById('editUserId').value);

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(editModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Update failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });

    // ── Move ──────────────────────────────────────────────
    const moveModal = document.getElementById('moveModal');
    function updateMoveBbcode() {
        const url = document.getElementById('movePreviewImg').src;
        document.getElementById('moveBbcodeTag').value = url ? '[img]' + url + '[/img]' : '';
    }
    function updateMoveInsertNote() {
        const type = document.getElementById('moveContentType').value;
        const checkbox = document.getElementById('moveInsertTag');
        const note = document.getElementById('moveInsertTagNote');
        if (type === 'message') {
            checkbox.checked = false;
            checkbox.disabled = true;
            note.textContent = 'The tag is always removed from the old location. Not inserted automatically for Messages - private message text is never modified automatically.';
        } else {
            checkbox.disabled = false;
            note.textContent = 'The tag is always removed from the old location.';
        }
    }
    moveModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('moveId').value = t.getAttribute('data-id') || '';
        document.getElementById('movePreviewImg').src = t.getAttribute('data-file-url') || '';
        document.getElementById('moveContentType').value = '';
        document.getElementById('moveContentId').value = '';
        document.getElementById('moveInsertTag').checked = true;
        document.getElementById('moveInsertTag').disabled = false;
        document.getElementById('moveInsertTagNote').textContent = 'The tag is always removed from the old location.';
        updateMoveBbcode();
    });
    document.getElementById('moveContentType').addEventListener('change', updateMoveInsertNote);
    document.getElementById('moveCopyBbcodeBtn').addEventListener('click', function() {
        const input = document.getElementById('moveBbcodeTag');
        input.select();
        navigator.clipboard?.writeText(input.value).then(() => toast('Copied to clipboard', 'success'));
    });
    document.getElementById('moveSaveBtn').addEventListener('click', function() {
        const btn = this;
        const contentType = document.getElementById('moveContentType').value;
        const contentId   = document.getElementById('moveContentId').value.trim();
        if (!contentType || !contentId) { toast('Content type and ID are required', 'error'); return; }

        const formData = new FormData();
        formData.append('ajax_move', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('moveId').value);
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('insert_tag', document.getElementById('moveInsertTag').checked ? '1' : '');

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(moveModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Move failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });

    // ── Copy ──────────────────────────────────────────────
    const copyModal = document.getElementById('copyModal');
    function updateCopyBbcode() {
        const url = document.getElementById('copyPreviewImg').src;
        document.getElementById('copyBbcodeTag').value = url ? '[img]' + url + '[/img]' : '';
    }
    function updateCopyInsertNote() {
        const type = document.getElementById('copyContentType').value;
        const checkbox = document.getElementById('copyInsertTag');
        const note = document.getElementById('copyInsertTagNote');
        if (type === 'message') {
            checkbox.checked = false;
            checkbox.disabled = true;
            note.textContent = 'Not available for Messages - private message text is never modified automatically.';
        } else {
            checkbox.disabled = false;
            note.textContent = '';
        }
    }
    copyModal.addEventListener('show.bs.modal', function(event) {
        const t = event.relatedTarget;
        if (!t) return;
        document.getElementById('copyId').value = t.getAttribute('data-id') || '';
        document.getElementById('copyPreviewImg').src = t.getAttribute('data-file-url') || '';
        document.getElementById('copyContentType').value = '';
        document.getElementById('copyContentId').value = '';
        document.getElementById('copyInsertTag').checked = true;
        document.getElementById('copyInsertTag').disabled = false;
        document.getElementById('copyInsertTagNote').textContent = '';
        updateCopyBbcode();
    });
    document.getElementById('copyContentType').addEventListener('change', updateCopyInsertNote);
    document.getElementById('copyCopyBbcodeBtn').addEventListener('click', function() {
        const input = document.getElementById('copyBbcodeTag');
        input.select();
        navigator.clipboard?.writeText(input.value).then(() => toast('Copied to clipboard', 'success'));
    });
    document.getElementById('copySaveBtn').addEventListener('click', function() {
        const btn = this;
        const contentType = document.getElementById('copyContentType').value;
        const contentId   = document.getElementById('copyContentId').value.trim();
        if (!contentType || !contentId) { toast('Content type and ID are required', 'error'); return; }

        const formData = new FormData();
        formData.append('ajax_copy', '1');
        formData.append('my_post_key', myPostKey);
        formData.append('id', document.getElementById('copyId').value);
        formData.append('content_type', contentType);
        formData.append('content_id', contentId);
        formData.append('insert_tag', document.getElementById('copyInsertTag').checked ? '1' : '');

        btn.disabled = true;
        btn.querySelector('.save-text').classList.add('d-none');
        btn.querySelector('.save-loading').classList.remove('d-none');

        fetch(scriptUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    toast(data.message, 'success');
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(copyModal)?.hide();
                        location.reload();
                    }, 900);
                } else {
                    toast(data.message || 'Copy failed.', 'error');
                }
            })
            .catch(() => toast('Server not responding.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.querySelector('.save-text').classList.remove('d-none');
                btn.querySelector('.save-loading').classList.add('d-none');
            });
    });
})();
</script>

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