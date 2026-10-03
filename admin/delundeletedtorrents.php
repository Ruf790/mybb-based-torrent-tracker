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

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$page_url    = $BASEURL . '/admin/index.php?act=delundeletedtorrents';
$asset_ver   = 1;
$batch_limit = 200;

// ── Все ID торрентов из БД (как ключи — isset() вместо in_array()) ──
$torrent_id_set = [];
$sql = $db->sql_query_prepared('SELECT id FROM torrents');
// Критично: если запрос провалится, $sql будет false, и пустой набор ID означал бы,
// что ВСЕ реальные раздачи сайта "осиротевшие". Прерываемся сразу.
if (!$sql) {
    stdhead('Delete Undeleted Torrent Files');
    echo '<div class="container mt-3"><div class="alert alert-danger d-flex gap-2 align-items-start">
        <i class="fa-solid fa-database mt-1"></i>
        <div><b>Database error</b> while fetching existing torrent IDs. Aborting for safety -
        proceeding here would have treated every torrent on the site as "orphaned".
        Please check the database connection and try again.</div>
    </div></div>';
    stdfoot();
    exit;
}
while ($torrent = $db->fetch_array($sql)) {
    $torrent_id_set[(int)$torrent['id']] = true;
}

// ── Поиск .torrent файлов без записи в БД ──
$disk_count = 0;
$delete = [];
$handle = opendir(TSDIR . '/' . $torrent_dir);
if ($handle) {
    while (($file = readdir($handle)) !== false) {
        if (!str_ends_with($file, '.torrent')) {
            continue;
        }
        $stem = substr($file, 0, -8);
        // Только чисто числовые имена: "abc.torrent" раньше превращался в ID 0
        if ($stem === '' || !ctype_digit($stem) || (int)$stem <= 0) {
            continue;
        }
        $file_id = (int)$stem;
        $disk_count++;
        if (!isset($torrent_id_set[$file_id])) {
            $delete[] = $file_id;
        }
    }
    closedir($handle);
}
sort($delete, SORT_NUMERIC);

// ── POST: удаление батча → Post/Redirect/Get ──
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sure'] ?? '') === 'yes') {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $error = 'Security check failed. The page may have expired - reload it and try again.';
    } else {
        $batch = array_slice($delete, 0, $batch_limit);
        $result = delete_batch($batch);
        $deleted_ids = $result['deleted'];
        $total_shots = array_sum(array_map('count', $result['screenshots']));

        if (!empty($deleted_ids)) {
            $log_msg = 'Deleted ' . count($deleted_ids) . ' orphaned torrent file(s): ' . implode(', ', $deleted_ids);
            if ($total_shots > 0) {
                $log_msg .= '. Also deleted ' . $total_shots . ' screenshot(s).';
            }
            write_log($log_msg, 'torrent', 1);
        }

        header('Location: ' . $page_url . '&done=' . implode(',', $deleted_ids) . '&shots=' . $total_shots);
        exit;
    }
}

// ── Результат прошлого батча (из GET после редиректа) ──
$done_ids = [];
if (isset($_GET['done']) && is_string($_GET['done']) && $_GET['done'] !== '') {
    foreach (explode(',', $_GET['done']) as $part) {
        if (ctype_digit($part) && (int)$part > 0) {
            $done_ids[] = (int)$part;
        }
        if (count($done_ids) >= $batch_limit) {
            break;
        }
    }
}
$done_shots  = max(0, (int)($_GET['shots'] ?? 0));
$orphans     = count($delete);
$next_batch  = min($orphans, $batch_limit);

stdhead('Delete Undeleted Torrent Files');

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/delundeletedtorrents.css?ver=' . $asset_ver . '">
<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>
<script src="' . $BASEURL . '/admin/scripts/delundeletedtorrents.js?ver=' . $asset_ver . '"></script>';

// ── Шапка ──
echo '
<div class="dut-page container-md py-3">
    <div class="card dut-header mb-3">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="dut-icon-square dut-soft-danger"><i class="fa-solid fa-broom"></i></div>
            <div>
                <h1 class="dut-title">Delete undeleted torrent files</h1>
                <div class="dut-subtitle">Finds .torrent files in <code>' . $e((string)$torrent_dir) . '/</code> that no longer have a database record and removes them together with covers, screenshots, comments and attachments.</div>
            </div>
        </div>
    </div>';

if ($error !== '') {
    echo '
    <div class="alert alert-danger dut-alert" role="alert">
        <i class="fa-solid fa-shield-halved"></i><div>' . $e($error) . '</div>
    </div>';
}

// ── KPI ──
$orphan_tone = $orphans > 0 ? 'danger' : 'success';
$kpis = [
    ['fa-database',   'primary',    number_format(count($torrent_id_set)), 'Torrents in database'],
    ['fa-hard-drive', 'info',       number_format($disk_count),            '.torrent files on disk'],
    ['fa-link-slash', $orphan_tone, number_format($orphans),               'Orphaned files'],
    ['fa-trash-can',  'secondary',  number_format(count($done_ids)),       'Deleted in last batch'
        . ($done_shots > 0 ? ' <span class="dut-kpi-extra"><i class="fa-solid fa-images"></i> ' . number_format($done_shots) . '</span>' : '')],
];

echo '<div class="row g-3 mb-3">';
foreach ($kpis as [$icon, $tone, $value, $label]) {
    echo '
        <div class="col-6 col-lg-3">
            <div class="dut-kpi">
                <div class="dut-icon-square dut-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>
                <div>
                    <div class="dut-kpi-value">' . $value . '</div>
                    <div class="dut-kpi-label">' . $label . '</div>
                </div>
            </div>
        </div>';
}
echo '</div>';

// ── Результат последнего батча ──
if (!empty($done_ids)) {
    echo '
    <div class="dut-panel mb-3">
        <div class="dut-panel-head">
            <div class="dut-icon-square dut-icon-sm dut-soft-success"><i class="fa-solid fa-circle-check"></i></div>
            <div class="flex-grow-1">
                <div class="fw-semibold">Deleted ' . count($done_ids) . ' orphaned torrent(s)</div>
                <div class="dut-subtitle">' . ($done_shots > 0
                    ? 'Also removed ' . number_format($done_shots) . ' screenshot(s). '
                    : '') . 'Full list is saved in the staff log.</div>
            </div>
        </div>
        <div class="dut-chips dut-chips-short">';
    foreach ($done_ids as $id) {
        echo '<span class="dut-chip dut-chip-done"><i class="fa-solid fa-check"></i>#' . $id . '</span>';
    }
    echo '
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="deletedToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="fa-solid fa-circle-check me-2"></i>Deleted ' . count($done_ids) . ' orphaned torrent(s)</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>';
}

// ── Список осиротевших / пусто ──
if ($orphans > 0) {
    echo '
    <div class="dut-panel">
        <div class="dut-panel-head">
            <div class="dut-icon-square dut-icon-sm dut-soft-danger"><i class="fa-solid fa-file-circle-xmark"></i></div>
            <div class="flex-grow-1">
                <div class="fw-semibold">' . number_format($orphans) . ' orphaned .torrent file(s)</div>
                <div class="dut-subtitle">' . ($orphans > $batch_limit
                    ? 'Deleted in batches of ' . $batch_limit . '. Highlighted files go in the next batch.'
                    : 'All of them will be deleted in one batch.') . '</div>
            </div>
            <div class="dut-filter-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="dutFilter" class="form-control form-control-sm dut-filter" inputmode="numeric" placeholder="Find ID" aria-label="Find torrent ID">
                <span class="dut-filter-count"><span id="dutFilterCount">' . $orphans . '</span> shown</span>
            </div>
        </div>
        <div class="dut-chips" id="dutOrphanList">';
    foreach ($delete as $i => $id) {
        $cls = ($orphans > $batch_limit && $i < $batch_limit) ? ' dut-chip-next' : '';
        echo '<span class="dut-chip' . $cls . '" data-id="' . $id . '"><i class="fa-solid fa-file"></i>#' . $id . '</span>';
    }
    echo '
        </div>
    </div>

    <form method="post" action="' . $e($page_url) . '" id="dutDeleteForm" class="dut-actionbar" data-count="' . $next_batch . '">
        <input type="hidden" name="sure" value="yes">
        <input type="hidden" name="my_post_key" value="' . $e((string)($mybb->post_code ?? '')) . '">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-layer-group text-body-secondary"></i>
            <span>Next batch: <b>' . $next_batch . '</b> of ' . number_format($orphans) . '</span>
        </div>
        <button type="submit" class="btn btn-danger dut-btn-pill">
            <i class="fa-solid fa-trash-can me-2"></i>' . ($orphans > $batch_limit ? 'Delete next ' . $next_batch : 'Delete all ' . $next_batch) . '
        </button>
    </form>';
} else {
    echo '
    <div class="dut-panel dut-empty">
        <div class="dut-icon-square dut-soft-success"><i class="fa-solid fa-circle-check"></i></div>
        <div class="fw-semibold fs-5 mt-3">All clean</div>
        <div class="dut-subtitle">Every .torrent file on disk has a matching torrent in the database.</div>
    </div>';
}

echo '</div>';

stdfoot();