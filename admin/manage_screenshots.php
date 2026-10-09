<?php

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_image_recode.php';

global $lang;
$lang->load('manage_screenshots');


// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
const DAY_IN_SECONDS  = 86400;

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в языковую строку (strtr — без повторной замены).
     * $lang->load() превращает {N} в %N$s, поэтому подставляем оба формата.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

/**
 * Языковой массив страницы, заранее экранированный для вывода в HTML/heredoc.
 */
function scr_lang_html(): array
{
    global $lang;
    return array_map(
        static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES),
        $lang->manage_screenshots
    );
}

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
    global $mybb, $_this_script_, $lang;
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_csrf']]);
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
    global $db, $lang; // ВАЖНО: добавить эту строку!
    
    header('Content-Type: application/json');
    scr_verify_csrf();

    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_none_selected']]);
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
            ? $lang->manage_screenshots['err_mass_failed']
            : (empty($errors)
                ? ags_fmt($lang->manage_screenshots['flash_mass_deleted'], count($deleted))
                : ags_fmt($lang->manage_screenshots['flash_mass_deleted_errors'], count($deleted), count($errors))),
    ]);
    exit;
}



// ═══════════════════════════════════════════════════════════
// ACTION: AJAX UPLOAD (модалка на странице списка)
// ═══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'ajax_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db, $CURUSER, $lang;

    header('Content-Type: application/json');
    scr_verify_csrf();

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size    = 10 * 1024 * 1024; // 10 MB

    $torrent_id = (int)($_POST['torrent_id'] ?? 0);
    if ($torrent_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_torrent_required']]);
        exit;
    }

    $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
    if (!$torrent_check || $db->num_rows($torrent_check) === 0) {
        echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_screenshots['err_torrent_missing'], $torrent_id)]);
        exit;
    }

    if (!isset($_FILES['screenshots']) || empty($_FILES['screenshots']['name'][0] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_no_files']]);
        exit;
    }

    $files = $_FILES['screenshots'];
    $fileCount = count($files['name']);
    $uploaded = 0;
    $errors = [];     // для пользователя (переведено)
    $log_errors = []; // для write_log (английский)

    for ($i = 0; $i < $fileCount; $i++) {
        $name  = $files['name'][$i];
        $tmp   = $files['tmp_name'][$i];
        $size  = $files['size'][$i];
        $error = $files['error'][$i];

        if ($name === '') continue;

        if ($error !== UPLOAD_ERR_OK) {
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_upload'], $name);
            $log_errors[] = "{$name}: upload error";
            continue;
        }
        if ($size > $max_size) {
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_too_large'], $name);
            $log_errors[] = "{$name}: too large (max 10MB)";
            continue;
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_format'], $name);
            $log_errors[] = "{$name}: invalid format";
            continue;
        }

        if (!($info = @getimagesize($tmp))) {
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_not_image'], $name);
            $log_errors[] = "{$name}: not a valid image";
            continue;
        }

        $num      = get_next_screenshot_number($torrent_id, $db, 3);
        $filename = $torrent_id . '_' . $num . '.' . $ext;
        $path     = get_upload_path($filename);

        if (!move_uploaded_file($tmp, $path)) {
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_save'], $name);
            $log_errors[] = "{$name}: failed to save";
            continue;
        }

        // Перекодирование — защита от "полиглот"-файлов (getimagesize()
        // проверяет только заголовок, не весь файл). Анимированные GIF
        // recode_image_file() обрабатывает отдельно через Imagick, сохраняя
        // анимацию.
        if (recode_image_file($path, $info['mime']) === false) {
            @unlink($path);
            $errors[]     = ags_fmt($lang->manage_screenshots['err_f_corrupt'], $name);
            $log_errors[] = "{$name}: corrupted or invalid image data";
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
        echo json_encode(['status' => 'success', 'uploaded' => $uploaded, 'message' => ags_fmt($lang->manage_screenshots['flash_uploaded'], $uploaded)]);
    } elseif ($uploaded > 0) {
        echo json_encode(['status' => 'partial', 'uploaded' => $uploaded, 'message' => ags_fmt($lang->manage_screenshots['flash_uploaded_partial'], $uploaded, implode('; ', $errors))]);
    } else {
        write_log("[SCREENSHOT UPLOAD ERROR] Torrent #{$torrent_id} | {$CURUSER['username']} | " . implode('; ', $log_errors));
        echo json_encode(['status' => 'error', 'message' => implode('; ', $errors) ?: $lang->manage_screenshots['err_upload_failed']]);
    }
    exit;
}



// ═══════════════════════════════════════════════════════════
// ACTION: AJAX EDIT (модалка на странице списка)
// ═══════════════════════════════════════════════════════════
if (($_GET['action'] ?? '') === 'ajax_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db, $CURUSER, $lang;

    header('Content-Type: application/json');
    scr_verify_csrf();

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $id = (int)($_POST['id'] ?? 0);
    $res = $db->sql_query_prepared('SELECT * FROM screenshots WHERE id = ?', [$id]);
    $row = $res ? $db->fetch_array($res) : null;

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_not_found']]);
        exit;
    }

    $torrent_id = (int)($_POST['torrent_id'] ?? 0);
    $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
    if ($torrent_id <= 0 || !$torrent_check || $db->num_rows($torrent_check) === 0) {
        echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_screenshots['err_torrent_missing'], $torrent_id)]);
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
            echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_screenshots['err_format_allowed'], implode(', ', $allowed_ext))]);
            exit;
        }
        $screenshotInfo = @getimagesize($_FILES['screenshot']['tmp_name']);
        if (!$screenshotInfo) {
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_not_image']]);
            exit;
        }

        $old = get_upload_path($row['filename']);
        $num = get_next_screenshot_number($torrent_id, $db, 3);
        $newFilename = $torrent_id . '_' . $num . '.' . $ext;
        $newPath = get_upload_path($newFilename);

        if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $newPath)) {
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_file_upload_failed']]);
            exit;
        }

        // Перекодирование — защита от "полиглот"-файлов.
        if (recode_image_file($newPath, $screenshotInfo['mime']) === false) {
            @unlink($newPath);
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_corrupt']]);
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

    echo json_encode(['status' => 'success', 'message' => $lang->manage_screenshots['flash_updated']]);
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
// UI HELPERS (общие стили и шапка для всех страниц модуля)
// ═══════════════════════════════════════════════════════════
function scr_styles(): void
{
    global $BASEURL;
    echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/manage_screenshots.css?ver=2">';
}



function scr_header(string $icon, string $title, string $subtitle, string $actions = ''): void
{
    echo '<div class="scr-panel scr-head d-flex flex-wrap align-items-center gap-3 mb-4">';
    echo '<div class="scr-icon scr-tone-primary"><i class="fa-solid ' . $icon . '"></i></div>';
    echo '<div class="me-auto"><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($subtitle) . '</p></div>';
    if ($actions !== '') {
        echo '<div class="d-flex flex-wrap gap-2">' . $actions . '</div>';
    }
    echo '</div>';
}

function scr_kpi(string $icon, string $tone, string $value, string $label): string
{
    return '<div class="col-6 col-lg-3"><div class="scr-panel scr-kpi">'
        . '<div class="scr-icon scr-icon-sm scr-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>'
        . '<div><div class="scr-kpi-value">' . $value . '</div><div class="scr-kpi-label">' . $label . '</div></div>'
        . '</div></div>';
}

// ═══════════════════════════════════════════════════════════
// SHOW LIST
// ═══════════════════════════════════════════════════════════
function show_list(): void
{
    global $db, $_this_script_, $mybb, $BASEURL, $lang;

    $h = scr_lang_html();

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

    // KPI — одним запросом по всей таблице (без учёта фильтра)
    $stats_res = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                COUNT(DISTINCT torrent_id) AS torrents,
                COALESCE(SUM(uploaded_at >= ?), 0) AS day,
                COALESCE(SUM(uploaded_at >= ?), 0) AS week
           FROM screenshots",
        [TIMENOW - DAY_IN_SECONDS, TIMENOW - 7 * DAY_IN_SECONDS]
    );
    $stats = $db->fetch_array($stats_res) ?: [];

    [$page, $perpage] = scr_page_params();
    $pages = max(1, (int)ceil($count / $perpage));
    $page  = max(1, min($page, $pages));
    $start = ($page - 1) * $perpage;

    $page_url = $_this_script_
        . ($search     ? '&search='     . urlencode($search)     : '')
        . ($torrent_id ? '&torrent_id=' . (int)$torrent_id       : '');

    $list_params = array_merge($params, [$start, $perpage]);
    $result = $db->sql_query_prepared("SELECT * FROM screenshots $where_sql ORDER BY uploaded_at DESC LIMIT ?, ?", $list_params);
    $has_rows = $db->num_rows($result) > 0;
    $is_filtered = ($search !== '' || $torrent_id !== '');

    stdhead($lang->manage_screenshots['title_list']);

  
    scr_styles();
    echo '<script>var my_post_key = "' . $mybb->post_code . '"; var scr_script = "' . $_this_script_ . '";</script>';

    echo '<div class="container mt-3 scr-page">';

    // Header
    scr_header(
        'fa-images',
        $lang->manage_screenshots['sec_list_title'],
        $lang->manage_screenshots['sec_list_sub'],
        '<a href="' . $_this_script_ . '&action=add" class="btn btn-primary rounded-pill px-4" id="addNewBtn" data-bs-toggle="modal" data-bs-target="#uploadScreenshotModal">'
        . '<i class="fa-solid fa-cloud-arrow-up me-2"></i>' . $h['btn_upload_screenshots'] . '</a>'
    );

    // KPI tiles
    echo '<div class="row g-3 mb-4">';
    echo scr_kpi('fa-images',        'primary', number_format((int)($stats['total'] ?? 0)),    $h['kpi_total']);
    echo scr_kpi('fa-magnet',        'info',    number_format((int)($stats['torrents'] ?? 0)), $h['kpi_torrents']);
    echo scr_kpi('fa-clock',         'success', number_format((int)($stats['day'] ?? 0)),      $h['kpi_day']);
    echo scr_kpi('fa-calendar-week', 'warning', number_format((int)($stats['week'] ?? 0)),     $h['kpi_week']);
    echo '</div>';

    // Search
    echo '<div class="scr-panel scr-filter mb-4">';
    echo '<form method="get" action="index.php" class="row g-2 align-items-center">';
    echo '<input type="hidden" name="act" value="manage_screenshots">';
    echo '<div class="col-md-5"><div class="input-group">';
    echo '<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>';
    echo '<input type="text" class="form-control" name="search" placeholder="' . $h['ph_filename'] . '" aria-label="' . $h['aria_search'] . '" value="' . htmlspecialchars($search) . '">';
    echo '</div></div>';
    echo '<div class="col-md-3"><div class="input-group">';
    echo '<span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>';
    echo '<input type="number" class="form-control" name="torrent_id" placeholder="' . $h['ph_torrent_id'] . '" aria-label="' . $h['ph_torrent_id'] . '" value="' . htmlspecialchars($torrent_id) . '">';
    echo '</div></div>';
    echo '<div class="col-md-4 d-flex gap-2">';
    echo '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-filter me-2"></i>' . $h['btn_filter'] . '</button>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-rotate-left me-2"></i>' . $h['btn_reset'] . '</a>';
    echo '</div></form>';
    if ($is_filtered) {
        echo '<div class="scr-meta mt-2"><i class="fa-solid fa-list-check"></i><span>'
            . ags_fmt($h['lbl_found'], '<strong>' . number_format($count) . '</strong>')
            . '</span></div>';
    }
    echo '</div>';

    scr_pagination($count, $perpage, $page, $page_url);

    if (!$has_rows) {
        echo '<div class="scr-panel scr-empty">';
        if ($is_filtered) {
            echo '<div class="scr-icon scr-tone-warning"><i class="fa-solid fa-magnifying-glass"></i></div>';
            echo '<h5 class="fw-semibold">' . $h['empty_filter_title'] . '</h5>';
            echo '<p class="text-body-secondary mb-3">' . $h['empty_filter_text'] . '</p>';
            echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-rotate-left me-2"></i>' . $h['btn_reset_filter'] . '</a>';
        } else {
            echo '<div class="scr-icon scr-tone-primary"><i class="fa-solid fa-images"></i></div>';
            echo '<h5 class="fw-semibold">' . $h['empty_title'] . '</h5>';
            echo '<p class="text-body-secondary mb-3">' . $h['empty_text'] . '</p>';
            echo '<button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#uploadScreenshotModal"><i class="fa-solid fa-cloud-arrow-up me-2"></i>' . $h['btn_upload_screenshots'] . '</button>';
        }
        echo '</div>';
    } else {

        echo '<form id="massDeleteForm" method="post" action="' . $_this_script_ . '&action=mass_delete">';
        echo '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">';
        echo '<div class="row g-3" id="scrGrid">';

        while ($row = $db->fetch_array($result)) {
            $id       = (int)$row['id'];
            $tid      = (int)$row['torrent_id'];
            $img      = htmlspecialchars(get_image_path($row['filename']));
            $fname    = htmlspecialchars($row['filename']);
            $ext      = strtoupper(pathinfo($row['filename'], PATHINFO_EXTENSION));
            $date     = date($lang->manage_screenshots['fmt_date'], (int)$row['uploaded_at']);
            $torrent_label = ags_fmt($h['lbl_torrent'], $tid);

            echo '<div class="col-xl-3 col-lg-4 col-sm-6 screenshot-card">';
            echo '<div class="card h-100 scr-card">';

            // Thumbnail
            echo '<div class="position-relative">';
            echo '<div class="form-check scr-check">';
            echo '<input class="form-check-input screenshot-checkbox" type="checkbox" name="ids[]" value="' . $id . '" data-img-src="' . $img . '" aria-label="' . ags_fmt($h['aria_select'], $id) . '">';
            echo '</div>';
            echo '<span class="scr-chip scr-chip-id">#' . $id . '</span>';
            if ($ext !== '') {
                echo '<span class="scr-chip scr-chip-ext"><i class="fa-solid fa-file-image me-1"></i>' . htmlspecialchars($ext) . '</span>';
            }
            echo '<a href="#" class="scr-thumb" data-bs-toggle="modal" data-bs-target="#universalImageModal" data-img-src="' . $img . '" data-title="' . $torrent_label . '" aria-label="' . ags_fmt($h['aria_view'], $id) . '">';
            echo '<img src="' . $img . '" alt="' . ags_fmt($h['alt_screenshot'], $id) . '" loading="lazy">';
            echo '<span class="scr-thumb-zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></span>';
            echo '</a>';
            echo '</div>';

            // Body
            echo '<div class="scr-body">';
            echo '<a class="scr-torrent d-inline-flex align-items-center gap-2 mb-1" href="' . $BASEURL . '/details.php?id=' . $tid . '" target="_blank" rel="noopener">'
                . '<i class="fa-solid fa-magnet text-primary"></i>' . $torrent_label . '</a>';
            echo '<div class="scr-meta" title="' . $fname . '"><i class="fa-solid fa-file-lines"></i><span>' . $fname . '</span></div>';
            echo '<div class="scr-meta"><i class="fa-solid fa-clock"></i><span>' . $date . '</span></div>';

            echo '<div class="scr-actions">';
            echo '<a class="btn btn-sm btn-outline-primary rounded-pill edit-screenshot-btn" href="' . $_this_script_ . '&action=edit&id=' . $id . '"'
                . ' data-id="' . $id . '"'
                . ' data-torrent-id="' . $tid . '"'
                . ' data-filename="' . $fname . '"'
                . ' data-img-src="' . $img . '"'
                . ' data-bs-toggle="modal" data-bs-target="#editScreenshotModal">'
                . '<i class="fa-solid fa-pen-to-square me-1"></i>' . $h['btn_edit'] . '</a>';
            echo '<a class="btn btn-sm btn-outline-danger rounded-pill single-delete-btn" href="#"'
                . ' data-id="' . $id . '"'
                . ' data-filename="' . $fname . '"'
                . ' data-bs-toggle="modal" data-bs-target="#singleDeleteModal">'
                . '<i class="fa-solid fa-trash-can me-1"></i>' . $h['btn_delete'] . '</a>';
            echo '<a class="btn btn-sm btn-outline-secondary rounded-pill scr-open" href="' . $img . '" target="_blank" rel="noopener" title="' . $h['tip_open_original'] . '" aria-label="' . $h['tip_open_original'] . '">'
                . '<i class="fa-solid fa-up-right-from-square"></i></a>';
            echo '</div>';

            echo '</div></div></div>';
        }

        echo '</div></form>';

        // Sticky bulk-action bar
        echo '<div class="scr-bar">';
        echo '<div class="scr-bar-info"><i class="fa-solid fa-square-check"></i><span>'
            . ags_fmt($h['lbl_selected'], '<strong id="scrSelectedCount">0</strong>')
            . '</span></div>';
        echo '<button type="button" class="btn btn-outline-secondary rounded-pill px-3" id="selectAllBtn"><i class="fa-solid fa-check-double me-2"></i>' . $h['btn_select_all'] . '</button>';
        echo '<button type="button" class="btn btn-danger rounded-pill px-3" id="deleteSelectedBtn" disabled><i class="fa-solid fa-trash-can me-2"></i>' . $h['btn_delete_selected'] . '</button>';
        echo '</div>';
    }

    echo '<div class="mt-3">';
    scr_pagination($count, $perpage, $page, $page_url);
    echo '</div>';

    require_once INC_PATH . '/modals_images.php';

    // Заголовок массового удаления: счётчик — <span id="deleteCount">, на него опирается JS
    $mass_delete_q = ags_fmt($h['lbl_delete_mass_q'], '<span id="deleteCount" class="text-danger">0</span>');

    // ── Modals ──────────────────────────────────────────────
echo <<<HTML
<!-- Single Delete Modal -->
<div class="modal fade" id="singleDeleteModal" tabindex="-1" aria-labelledby="singleDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title fw-semibold" id="singleDeleteModalLabel">
          <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>{$h['pane_confirm_delete']}
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{$h['btn_close']}"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
          <div class="scr-icon scr-tone-danger me-3"><i class="fa-solid fa-trash-can"></i></div>
          <div>
            <h5 class="fw-bold mb-1" id="singleDeleteTitle">{$h['lbl_delete_single_q']}</h5>
            <p class="text-muted mb-0" id="singleDeleteFilename"></p>
          </div>
        </div>
        <div id="singleDeletePreviewContainer" class="single-preview-container mb-3 text-center">
          <div class="preview-wrapper" style="display: inline-block; max-width: 100%;">
            <img id="singleDeleteImage" src="" alt="{$h['alt_preview']}"
                 style="max-width: 100%; max-height: 200px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none;"
                 onerror="this.style.display='none';">
          </div>
        </div>
        <div class="file-details bg-body-tertiary p-3 rounded-3 mb-3">
          <div class="d-flex align-items-center">
            <div class="scr-icon scr-icon-sm scr-tone-primary me-3"><i class="fa-solid fa-file-image"></i></div>
            <div class="overflow-hidden">
              <div class="fw-bold text-truncate" id="singleDeleteFileName">filename.jpg</div>
              <div class="small text-muted" id="singleDeleteFileInfo">
                <i class="fa-solid fa-spinner fa-spin me-1"></i> {$h['lbl_loading']}
              </div>
            </div>
          </div>
        </div>
        <div class="alert alert-warning d-flex mb-0">
          <i class="fa-solid fa-circle-exclamation me-2 mt-1"></i>
          <div><strong>{$h['warn_undo']}</strong> {$h['warn_single_text']}</div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i> {$h['btn_cancel']}
        </button>
        <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmSingleDeleteBtn">
          <i class="fa-solid fa-trash-can me-1"></i> {$h['btn_delete']}
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
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="scr-icon scr-icon-sm scr-tone-primary"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <h5 class="modal-title fw-semibold mb-0">{$h['btn_upload_screenshots']}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$h['btn_close']}"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label text-body-secondary mb-2" for="scrTorrentId"><i class="fa-solid fa-hashtag me-1"></i>{$h['lbl_torrent_id']}</label>
                    <input type="number" class="form-control form-control-lg bg-body-tertiary" id="scrTorrentId" placeholder="{$h['ph_torrent_example']}" required>
                </div>

                <div class="upload-area scr-drop p-5 text-center position-relative" id="scrDropArea" style="cursor:pointer">
                    <div class="upload-progress position-absolute top-0 start-0 end-0" style="display:none">
                        <div class="progress rounded-0 rounded-top-4" style="height:4px">
                            <div class="progress-bar bg-primary" id="scrUploadProgress" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="image-preview-container mb-3" id="scrPreviewContainer" style="display:none">
                        <div class="d-flex flex-wrap gap-2 justify-content-center" id="scrPreviewGrid"></div>
                    </div>
                    <div id="scrPlaceholder">
                        <div class="scr-icon scr-tone-primary mx-auto mb-3" style="width:4.5rem;height:4.5rem;font-size:1.8rem;border-radius:50%"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <h5 class="fw-semibold">{$h['lbl_drop_title']}</h5>
                        <p class="text-body-secondary mb-4"><i class="fa-solid fa-file-image me-1"></i>{$h['hint_drop_formats']}</p>
                    </div>
                    <div class="file-count-badge position-absolute top-0 end-0 m-3" id="scrFileCountBadge" style="display:none">
                        <span class="badge bg-primary rounded-pill p-2" id="scrFileCount"></span>
                    </div>
                    <input type="file" class="d-none" id="scrFileInput" multiple accept="image/*">
                    <button class="btn btn-outline-primary btn-lg px-5 rounded-pill" onclick="document.getElementById('scrFileInput').click()" id="scrBrowseBtn">
                        <i class="fa-solid fa-folder-open me-2"></i>{$h['btn_choose_files']}
                    </button>
                    <button class="btn btn-link text-danger mt-3 d-none" id="scrClearBtn">
                        <i class="fa-solid fa-circle-xmark me-1"></i>{$h['btn_clear_all']}
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>{$h['btn_cancel']}
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-5 position-relative" id="scrStartUploadBtn" disabled>
                    <span class="upload-text"><i class="fa-solid fa-cloud-arrow-up me-2"></i>{$h['btn_upload']}</span>
                    <span class="upload-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>{$h['lbl_uploading']}
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
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="scr-icon scr-icon-sm scr-tone-primary"><i class="fa-solid fa-pen-to-square"></i></div>
                    <h5 class="modal-title fw-semibold mb-0">{$h['pane_edit']}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$h['btn_close']}"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="editScreenshotId" value="">
                <div class="row g-4 mb-3">
                    <div class="col-lg-6">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control bg-body-tertiary" id="editTorrentId" placeholder="{$h['lbl_torrent_id']}" required>
                            <label for="editTorrentId"><i class="fa-solid fa-hashtag me-1"></i>{$h['lbl_torrent_id']}</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-body-secondary mb-2" for="editScreenshotFile"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>{$h['lbl_replace_optional']}</label>
                            <input type="file" class="form-control bg-body-tertiary" id="editScreenshotFile" accept="image/*">
                            <div class="form-text" id="editCurrentFilename"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="scr-drop p-3 text-center d-grid" style="min-height:220px;place-items:center">
                            <img id="editImagePreview" src="" class="img-fluid rounded" style="max-height:220px" alt="{$h['alt_preview']}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>{$h['btn_cancel']}
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-5 position-relative" id="editSaveBtn">
                    <span class="save-text"><i class="fa-solid fa-floppy-disk me-2"></i>{$h['btn_save_changes']}</span>
                    <span class="save-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>{$h['lbl_saving']}
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
        <h5 class="modal-title fw-semibold" id="massDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>{$h['pane_confirm_delete']}
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{$h['btn_close']}"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex align-items-center mb-3">
          <div class="scr-icon scr-tone-danger me-3"><i class="fa-solid fa-trash-can"></i></div>
          <div>
            <h5 class="fw-bold mb-1">{$mass_delete_q}</h5>
            <p class="text-muted mb-0">{$h['lbl_delete_mass_text']}</p>
          </div>
        </div>

        <div id="massDeletePreview" class="selected-previews-container mb-3" style="display: none;">
          <div class="d-flex align-items-center mb-2">
            <i class="fa-solid fa-images text-primary me-2"></i>
            <span class="fw-medium">{$h['lbl_selected_screens']}</span>
          </div>
          <div id="previewList" class="previews-grid"></div>
        </div>

        <div class="alert alert-warning d-flex mt-3 mb-0">
          <i class="fa-solid fa-circle-exclamation me-2 mt-1"></i>
          <div><strong>{$h['warn_undo']}</strong> {$h['warn_mass_text']}</div>
        </div>
      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i> {$h['btn_cancel']}
        </button>
        <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn">
          <i class="fa-solid fa-trash-can me-1"></i> {$h['btn_delete']}
        </button>
      </div>
    </div>
  </div>
</div>
HTML;

    // JS-строки: ключи js_* из ланга → AGS_LANG без префикса
    $js_lang = [];
    foreach ($lang->manage_screenshots as $key => $value) {
        if (str_starts_with($key, 'js_')) {
            $js_lang[substr($key, 3)] = $value;
        }
    }
    echo '<script>const AGS_LANG = '
        . json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ';</script>';

    echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
    echo '<script src="' . $BASEURL . '/scripts/details_modal.js"></script>';
    echo '<script src="' . $BASEURL . '/admin/scripts/manage_screenshots.js?ver=31"></script>';

    // Счётчик выбранных в нижней панели (не мешает manage_screenshots.js)
    echo <<<'JS'
<script>
(function () {
    var root = document.querySelector('.scr-page');
    if (!root) return;
    var out = document.getElementById('scrSelectedCount');
    function update() {
        var n = root.querySelectorAll('.screenshot-checkbox:checked').length;
        if (out) out.textContent = n;
        root.classList.toggle('scr-has-selection', n > 0);
    }
    root.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('screenshot-checkbox')) update();
    });
    var selectAll = document.getElementById('selectAllBtn');
    if (selectAll) selectAll.addEventListener('click', function () { setTimeout(update, 0); });
    var grid = document.getElementById('scrGrid');
    if (grid && 'MutationObserver' in window) new MutationObserver(update).observe(grid, { childList: true, subtree: true });
    update();
})();
</script>
JS;

    echo '</div>'; // container
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE ADD
// ═══════════════════════════════════════════════════════════
function handle_add(): void
{
    global $db, $_this_script_, $CURUSER, $mybb, $lang;

    $h = scr_lang_html();

    $error   = null;
    $log_error = ''; // английский вариант $error для write_log
    $success_count = 0;
    $errors  = [];
    $log_errors = [];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        scr_verify_csrf();

        $torrent_id = (int)$_POST['torrent_id'];

        $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
        if ($torrent_id <= 0 || !$torrent_check || $db->num_rows($torrent_check) === 0) {
            $error     = ags_fmt($lang->manage_screenshots['err_torrent_missing'], $torrent_id);
            $log_error = "Torrent ID {$torrent_id} does not exist";
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
            $error     = $lang->manage_screenshots['err_no_file'];
            $log_error = 'No file uploaded or upload error';
        } else {
            foreach ($files as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[]     = ags_fmt($lang->manage_screenshots['err_f_upload_code'], $file['name'], $file['error']);
                    $log_errors[] = "{$file['name']}: upload error (code {$file['error']})";
                    continue;
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $errors[]     = ags_fmt($lang->manage_screenshots['err_f_format_allowed'], $file['name'], implode(', ', $allowed_ext));
                    $log_errors[] = "{$file['name']}: invalid format. Allowed: " . implode(', ', $allowed_ext);
                    continue;
                }

                if (!($info = getimagesize($file['tmp_name']))) {
                    $errors[]     = ags_fmt($lang->manage_screenshots['err_f_not_image'], $file['name']);
                    $log_errors[] = "{$file['name']}: not a valid image";
                    continue;
                }

                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                $path     = get_upload_path($filename);

                if (!move_uploaded_file($file['tmp_name'], $path)) {
                    $errors[]     = ags_fmt($lang->manage_screenshots['err_f_save'], $file['name']);
                    $log_errors[] = "{$file['name']}: file upload failed";
                    continue;
                }

                // Перекодирование — защита от "полиглот"-файлов.
                if (recode_image_file($path, $info['mime']) === false) {
                    @unlink($path);
                    $errors[]     = ags_fmt($lang->manage_screenshots['err_f_corrupt'], $file['name']);
                    $log_errors[] = "{$file['name']}: corrupted or invalid image data";
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
                $error     = ags_fmt($lang->manage_screenshots['flash_uploaded_with_errors'], $success_count, implode('; ', $errors));
                $log_error = "{$success_count} screenshot(s) uploaded successfully. Errors: " . implode('; ', $log_errors);
            } else {
                $error     = implode('; ', $errors);
                $log_error = implode('; ', $log_errors);
            }
        }
        } // конец else (torrent_id валиден)

        if ($error) {
            write_log("[SCREENSHOT UPLOAD ERROR] Torrent #{$_POST['torrent_id']} | {$CURUSER['username']} | {$log_error}");
        }
    }

    stdhead($lang->manage_screenshots['title_add']);
    scr_styles();
    echo '<div class="container mt-3 scr-page">';
    scr_header(
        'fa-circle-plus',
        $lang->manage_screenshots['sec_add_title'],
        $lang->manage_screenshots['sec_add_sub'],
        '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i>' . $h['btn_back'] . '</a>'
    );
    if ($error) {
        echo '<div class="alert ' . ($success_count > 0 ? 'alert-warning' : 'alert-danger') . ' d-flex align-items-start">'
            . '<i class="fa-solid ' . ($success_count > 0 ? 'fa-circle-exclamation' : 'fa-circle-xmark') . ' me-2 mt-1"></i>'
            . '<div>' . htmlspecialchars($error) . '</div></div>';
    }

    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">';
    echo '<div class="scr-panel p-4"><div class="row g-4">';

    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" placeholder="' . $h['lbl_torrent_id'] . '" required>';
    echo '<label for="torrent_id"><i class="fa-solid fa-hashtag me-1"></i>' . $h['lbl_torrent_id'] . '</label></div>';
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="screenshot"><i class="fa-solid fa-file-image me-1 text-primary"></i>' . $h['lbl_files'] . '</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot[]" accept="image/*" multiple required>';
    echo '<div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>' . ags_fmt($h['hint_allowed_multi'], implode(', ', $allowed_ext)) . '</div>';
    echo '</div></div>';

    echo '<div class="col-lg-6">';
    echo '<div class="scr-drop p-4 text-center d-grid" style="min-height:250px;place-items:center" id="dropArea">';
    echo '<div id="previewGrid" class="d-flex flex-wrap gap-2 justify-content-center"></div>';
    echo '<div id="placeholderText" class="text-body-secondary">';
    echo '<div class="scr-icon scr-tone-primary mx-auto mb-3" style="border-radius:50%"><i class="fa-solid fa-image"></i></div>';
    echo '<p class="mb-0">' . $h['hint_preview_here'] . '</p></div></div></div>';

    echo '</div></div>';

    echo '<div class="scr-bar">';
    echo '<div class="scr-bar-info"><i class="fa-solid fa-images"></i><span>'
        . ags_fmt($h['lbl_selected_files'], '<strong id="scrFileTotal">0</strong>')
        . '</span></div>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-xmark me-2"></i>' . $h['btn_cancel'] . '</a>';
    echo '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-floppy-disk me-2"></i>' . $h['btn_save_screenshots'] . '</button>';
    echo '</div>';
    echo '</form>';

    echo <<<'JS'
<script>
(function () {
    var input = document.getElementById('screenshot');
    var grid  = document.getElementById('previewGrid');
    var ph    = document.getElementById('placeholderText');
    var total = document.getElementById('scrFileTotal');
    if (!input || !grid) return;
    input.addEventListener('change', function () {
        grid.innerHTML = '';
        var files = Array.prototype.filter.call(input.files, function (f) { return f.type.indexOf('image/') === 0; });
        files.forEach(function (f) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.alt = f.name;
            img.title = f.name;
            img.className = 'scr-mini';
            img.onload = function () { URL.revokeObjectURL(img.src); };
            grid.appendChild(img);
        });
        if (ph) ph.style.display = files.length ? 'none' : '';
        if (total) total.textContent = files.length;
    });
})();
</script>
JS;

    echo '</div>';
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE EDIT
// ═══════════════════════════════════════════════════════════
function handle_edit(): void
{
    global $db, $_this_script_, $CURUSER, $mybb, $lang;

    $h = scr_lang_html();

    $id  = (int)($_GET['id'] ?? 0);
    $res = $db->sql_query_prepared("SELECT * FROM screenshots WHERE id = ?", [$id]);
    $row = $db->fetch_array($res);

    if (!$row) {
        stdhead($lang->manage_screenshots['title_edit']);
        scr_styles();
        echo '<div class="container mt-3 scr-page"><div class="scr-panel scr-empty">';
        echo '<div class="scr-icon scr-tone-danger"><i class="fa-solid fa-image"></i></div>';
        echo '<h5 class="fw-semibold">' . $h['notfound_title'] . '</h5>';
        echo '<p class="text-body-secondary mb-3">' . $h['notfound_text'] . '</p>';
        echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i>' . $h['btn_back'] . '</a>';
        echo '</div></div>';
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
            $error = ags_fmt($lang->manage_screenshots['err_torrent_missing'], $torrent_id);
        }

        if (!$error && $row['torrent_id'] != $torrent_id) {
            $changes[] = "Torrent ID {$row['torrent_id']} → {$torrent_id}";
        }

        if (!$error && isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            $screenshotInfo = @getimagesize($_FILES['screenshot']['tmp_name']);
            if (!in_array($ext, $allowed)) {
                $error = ags_fmt($lang->manage_screenshots['err_format_allowed'], implode(', ', $allowed));
            } elseif (!$screenshotInfo) {
                $error = $lang->manage_screenshots['err_not_image'];
            } else {
                $old      = get_upload_path($row['filename']);
                $num      = get_next_screenshot_number($torrent_id, $db, 3);
                $filename = $torrent_id . '_' . $num . '.' . $ext;
                $newPath  = get_upload_path($filename);

                if (!move_uploaded_file($_FILES['screenshot']['tmp_name'], $newPath)) {
                    $error    = $lang->manage_screenshots['err_file_upload_failed'];
                    $filename = $row['filename'];
                } elseif (recode_image_file($newPath, $screenshotInfo['mime']) === false) {
                    // Перекодирование — защита от "полиглот"-файлов.
                    @unlink($newPath);
                    $error    = $lang->manage_screenshots['err_corrupt'];
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

    stdhead($lang->manage_screenshots['title_edit']);
    scr_styles();
    echo '<div class="container mt-3 scr-page">';
    scr_header(
        'fa-pen-to-square',
        ags_fmt($lang->manage_screenshots['sec_edit_title'], $id),
        $lang->manage_screenshots['sec_edit_sub'],
        '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i>' . $h['btn_back'] . '</a>'
    );
    if ($error) {
        echo '<div class="alert alert-danger d-flex align-items-start"><i class="fa-solid fa-circle-xmark me-2 mt-1"></i><div>' . htmlspecialchars($error) . '</div></div>';
    }

    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">';
    echo '<div class="scr-panel p-4"><div class="row g-4">';

    echo '<div class="col-lg-6">';
    echo '<div class="form-floating mb-3">';
    echo '<input type="number" class="form-control" id="torrent_id" name="torrent_id" placeholder="' . $h['lbl_torrent_id'] . '" value="' . (int)$row['torrent_id'] . '" required>';
    echo '<label for="torrent_id"><i class="fa-solid fa-hashtag me-1"></i>' . $h['lbl_torrent_id'] . '</label></div>';
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="screenshot"><i class="fa-solid fa-arrow-right-arrow-left me-1 text-primary"></i>' . $h['lbl_replace_file'] . '</label>';
    echo '<input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*">';
    echo '<div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>' . ags_fmt($h['hint_keep_file'], htmlspecialchars($row['filename'])) . '</div>';
    echo '</div></div>';

    echo '<div class="col-lg-6">';
    echo '<div class="scr-drop p-4 text-center d-grid" style="min-height:250px;place-items:center">';
    echo '<img id="imagePreview" src="' . htmlspecialchars(get_image_path($row['filename'])) . '" class="img-fluid rounded" style="max-height:220px" alt="' . $h['alt_preview'] . '">';
    echo '</div></div>';

    echo '</div></div>';

    echo '<div class="scr-bar">';
    echo '<div class="scr-bar-info"><i class="fa-solid fa-file-image"></i><span class="text-truncate">' . htmlspecialchars($row['filename']) . '</span></div>';
    echo '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-xmark me-2"></i>' . $h['btn_cancel'] . '</a>';
    echo '<button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-floppy-disk me-2"></i>' . $h['btn_save_changes'] . '</button>';
    echo '</div>';
    echo '</form>';

    echo <<<'JS'
<script>
(function () {
    var input = document.getElementById('screenshot');
    var img   = document.getElementById('imagePreview');
    if (!input || !img) return;
    var original = img.src;
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        img.src = (f && f.type.indexOf('image/') === 0) ? URL.createObjectURL(f) : original;
    });
})();
</script>
JS;

    echo '</div>';
    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// HANDLE DELETE (AJAX + regular)
// ═══════════════════════════════════════════════════════════
function handle_delete(): void
{
    global $db, $CURUSER, $_this_script_, $mybb, $lang;

    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_post_required']]);
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
            echo json_encode(['status' => 'error', 'message' => $lang->manage_screenshots['err_not_found']]);
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
        echo json_encode(['status' => 'success', 'message' => $lang->manage_screenshots['flash_deleted'], 'deleted_id' => $id]);
        exit;
    }

    header('Location: ' . $_this_script_);
    exit;
}