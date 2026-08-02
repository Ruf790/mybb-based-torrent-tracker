<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center">Error! Direct initialization of this file is not allowed.</div>');
}

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center">Error! You do not have permission to access this page.</div>');
}

require_once INC_PATH . '/functions_comment_attachments.php';

/**
 * Удаляет файлы одного торрента с диска (без единого SQL-запроса —
 * все данные уже собраны заранее батчем в delete_batch()).
 *
 * @param array<int,array{id:int,filename:string}> $own_screens скрины именно этого торрента
 * @return array<int> ID удалённых скриншотов (для отображения в UI)
 */
function deep_delete_files(int $id, string $screens_dir, array $own_screens, array $known_screenshots): array
{
    global $torrent_dir;

    if (!is_valid_id($id)) {
        return [];
    }

    $image_types = ['gif', 'jpg', 'jpeg', 'png', 'webp'];

    // Delete .torrent file
    @unlink(TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent');

    // Delete cover images
    foreach ($image_types as $image) {
        @unlink(TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image);
        @unlink(TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image);
    }

    // Delete this torrent's own screenshots (данные уже получены батчем)
    $deleted_screenshot_ids = [];
    foreach ($own_screens as $shot) {
        @unlink($screens_dir . $shot['filename']);
        $deleted_screenshot_ids[] = $shot['id'];
    }

    return $deleted_screenshot_ids;
}

/**
 * Пакетное удаление сразу нескольких "осиротевших" торрентов —
 * все SQL-запросы сделаны один раз на весь батч, а не по одному на торрент/комментарий.
 *
 * @param array<int> $batch ID торрентов для удаления
 * @return array{deleted:array<int>, screenshots:array<int,array<int>>}
 */
function delete_batch(array $batch): array
{
    global $db, $torrent_dir;

    if (empty($batch)) {
        return ['deleted' => [], 'screenshots' => []];
    }

    $ids_ph = implode(',', array_fill(0, count($batch), '?'));
    $screens_dir = TSDIR . '/' . $torrent_dir . '/screens/';

    // ── Скриншоты: одним запросом собираем все скрины сразу для всего батча ──
    $screens_by_torrent = [];
    $q = $db->sql_query_prepared("SELECT id, torrent_id, filename FROM screenshots WHERE torrent_id IN ({$ids_ph})", $batch);
    while ($q && ($row = $db->fetch_array($q))) {
        $tid = (int)$row['torrent_id'];
        $screens_by_torrent[$tid][] = ['id' => (int)$row['id'], 'filename' => $row['filename']];
    }

    // ── Комментарии: одним запросом собираем ID всех комментариев батча ──
    $all_comment_ids = [];
    $q = $db->sql_query_prepared("SELECT id FROM comments WHERE torrent IN ({$ids_ph})", $batch);
    while ($q && ($row = $db->fetch_array($q))) {
        $all_comment_ids[] = (int)$row['id'];
    }

    // ── Вложения комментариев: чистим файлы + записи одним проходом на весь батч ──
    if (!empty($all_comment_ids)) {
        $comment_ids_ph = implode(',', array_fill(0, count($all_comment_ids), '?'));
        $uploadDir = TSDIR . '/uploads/attachments/';

        $q = $db->sql_query_prepared("SELECT attachname, thumbnail FROM attachments WHERE comment_id IN ({$comment_ids_ph})", $all_comment_ids);
        while ($q && ($row = $db->fetch_array($q))) {
            if (!empty($row['attachname'])) {
                @unlink($uploadDir . $row['attachname']);
            }
            if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
                @unlink($uploadDir . $row['thumbnail']);
            }
        }
        $db->sql_query_prepared("DELETE FROM attachments WHERE comment_id IN ({$comment_ids_ph})", $all_comment_ids);

        $q = $db->sql_query_prepared("SELECT file_path FROM comment_files WHERE comment_id IN ({$comment_ids_ph})", $all_comment_ids);
        while ($q && ($row = $db->fetch_array($q))) {
            if (!empty($row['file_path']) && is_file($row['file_path'])) {
                @unlink($row['file_path']);
            }
        }
        $db->sql_query_prepared("DELETE FROM comment_files WHERE comment_id IN ({$comment_ids_ph})", $all_comment_ids);
    }

    // ── Кэш известных имён скриншотов для чистки настоящих "осиротевших" файлов
    //    (тех, что вообще ни к какому торренту не привязаны) ──
    $known_screenshots = [];
    $q = $db->sql_query_prepared("SELECT filename FROM screenshots");
    while ($q && ($row = $db->fetch_array($q))) {
        $known_screenshots[$row['filename']] = true;
    }

    // ── Файловые операции по каждому торренту (без SQL — всё уже получено выше) ──
    $deleted_ids = [];
    $deleted_screenshots = [];
    foreach ($batch as $id) {
        $shot_ids = deep_delete_files($id, $screens_dir, $screens_by_torrent[$id] ?? [], $known_screenshots);
        $deleted_ids[] = $id;
        if (!empty($shot_ids)) {
            $deleted_screenshots[$id] = $shot_ids;
        }
    }

    // Чистим настоящие "осиротевшие" файлы в screens/ (без записи в БД вообще)
    $screens = is_dir($screens_dir) ? scandir($screens_dir) : [];
    foreach ($screens as $screenshot) {
        if ($screenshot === '.' || $screenshot === '..') {
            continue;
        }
        if (!isset($known_screenshots[$screenshot])) {
            @unlink($screens_dir . $screenshot);
        }
    }

    // ── Все табличные удаления — одним запросом на таблицу для всего батча ──
    $db->sql_query_prepared("DELETE FROM screenshots WHERE torrent_id IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM peers WHERE torrent IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM comments WHERE torrent IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM bookmarks WHERE torrentid IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM snatched WHERE torrentid IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM torrents WHERE id IN ({$ids_ph})", $batch);
    $db->sql_query_prepared("DELETE FROM torrents_nfo WHERE id IN ({$ids_ph})", $batch);

    return ['deleted' => $deleted_ids, 'screenshots' => $deleted_screenshots];
}

// Get all torrent IDs from database
$torrent_ids = [];
$sql = $db->sql_query_prepared('SELECT id FROM torrents');
while ($sql && ($torrent = $db->fetch_array($sql))) {
    $torrent_ids[] = (int)$torrent['id'];
}

// Find orphaned .torrent files
$files = [];
$handle = opendir(TSDIR . '/' . $torrent_dir);
if ($handle) {
    while (($file = readdir($handle)) !== false) {
        if ($file !== '.' && $file !== '..' && str_ends_with($file, '.torrent')) {
            $file_id = (int)str_replace('.torrent', '', $file);
            $files[] = $file_id;
        }
    }
    closedir($handle);
}

$delete = [];
foreach ($files as $file) {
    if (!in_array($file, $torrent_ids, true)) {
        $delete[] = $file;
    }
}

$deleted_ids = [];
$deleted_screenshots = [];
$batch_limit = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sure']) && $_POST['sure'] === 'yes') {
    if (empty($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        echo '<div class="alert alert-danger">Security check failed. Please try again from the page.</div>';
    } else {
        $batch = array_slice($delete, 0, $batch_limit);
        $result = delete_batch($batch);
        $deleted_ids = $result['deleted'];
        $deleted_screenshots = $result['screenshots'];
        $delete = array_slice($delete, $batch_limit);

        if (!empty($deleted_ids)) {
            $total_shots = array_sum(array_map('count', $deleted_screenshots));
            $log_msg = 'Deleted ' . count($deleted_ids) . ' orphaned torrent file(s): ' . implode(', ', $deleted_ids);
            if ($total_shots > 0) {
                $log_msg .= '. Also deleted ' . $total_shots . ' screenshot(s).';
            }
            write_log($log_msg, 'torrent', 1);
        }
    }
}

stdhead('Delete Undeleted Torrent Files');

echo '
<div class="container-md">
    <div class="card border-0 mb-4">
        <div class="card-header rounded-bottom text-19 fw-bold">
            Delete Undeleted Torrent Files
        </div>
    </div>
</div>

<div class="container mt-3">
    <div class="card">
        <div class="card-body">';

if (!empty($deleted_ids)) {
    echo '<div class="alert alert-success" role="alert">
        ✅ <b>Successfully deleted ' . count($deleted_ids) . ' files.</b>
    </div>';

    echo '<p>Deleted torrent IDs:</p>';
    echo '<div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Torrent ID</th>
                    <th>Screenshots Deleted</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($deleted_ids as $index => $id) {
        $shots = $deleted_screenshots[$id] ?? [];
        $shots_cell = !empty($shots)
            ? count($shots) . ' &nbsp;<span class="text-muted small">(id: ' . htmlspecialchars(implode(', ', $shots)) . ')</span>'
            : '<span class="text-muted">—</span>';
        echo '<tr>
            <th scope="row">' . ($index + 1) . '</th>
            <td>' . htmlspecialchars((string)$id) . '</td>
            <td>' . $shots_cell . '</td>
        </tr>';
    }
    
    echo '</tbody>
        </table>
    </div>';
}

if (!empty($delete)) {
    echo '<p>Total <b>' . count($delete) . '</b> orphaned .torrent files found in <code>' . $torrent_dir . '</code> folder.</p>';
    if (count($delete) > $batch_limit) {
        echo '<p class="text-muted small">Processing in batches of ' . $batch_limit . ' — click "Delete All" repeatedly until the list is empty.</p>';
    }
    echo '<div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Torrent ID</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($delete as $index => $id) {
        echo '<tr>
            <th scope="row">' . ($index + 1) . '</th>
            <td>' . htmlspecialchars((string)$id) . '</td>
        </tr>';
    }
    
    echo '</tbody>
        </table>
    </div>';
    echo '<form method="post" action="' . $BASEURL . '/admin/index.php?act=delundeletedtorrents" class="mt-3" onsubmit="return confirm(\'Are you sure you want to permanently delete these files?\')">'
       . '<input type="hidden" name="sure" value="yes">'
       . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '') . '">'
       . '<button type="submit" class="btn btn-danger">Delete All</button>'
       . '</form>';
} elseif (empty($deleted_ids)) {
    
	echo '
<div class="text-center py-5">
    <i class="fas fa-check-circle fa-4x text-success mb-3 d-block"></i>
    <h5 class="text-success mb-1">All Clean!</h5>
    <p class="text-muted">There are no undeleted torrents found. Everything is clean!</p>
</div>';
	
	
}

echo '</div>
    </div>
</div>';

if (!empty($deleted_ids)) {
    echo '
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="deletedToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Successfully deleted ' . count($deleted_ids) . ' files!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.getElementById("deletedToast");
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>';
}

stdfoot();
?>