<?php
declare(strict_types=1);

define('IN_ARCHIVE', true);
require_once INC_PATH . '/class_parser.php';

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$parser         = new postParser();
$parser_options = [
    'allow_html'     => 1,
    'allow_mycode'   => 1,
    'allow_smilies'  => 1,
    'allow_imgcode'  => 1,
    'allow_videocode'=> 1,
    'filter_badwords'=> 1,
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Отправляет JSON-ответ и завершает выполнение.
 */
function json_exit(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Проверяет CSRF-токен из POST. Завершает с ошибкой если невалиден.
 */
function require_csrf(): void
{
    global $mybb;
    if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        json_exit(['error' => 'Invalid security token'], 403);
    }
}

/**
 * Проверяет что запрос является POST. Завершает с ошибкой если нет.
 */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_exit(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Декодирует JSON-строку в массив int. Завершает с ошибкой если невалидно.
 *
 * @return int[]
 */
function decode_comment_ids(mixed $raw): array
{
    if (empty($raw)) {
        json_exit(['error' => 'No comments selected']);
    }

    $ids = is_array($raw) ? $raw : json_decode((string)$raw, true);

    if (!is_array($ids) || empty($ids)) {
        json_exit(['error' => 'No comments selected']);
    }

    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) {
        json_exit(['error' => 'No valid comment IDs']);
    }

    return array_values($ids);
}

// ---------------------------------------------------------------------------
// DB helpers — изолируем все запросы
// ---------------------------------------------------------------------------

function validate_torrent_exists(int $id): array|false
{
    global $db;
    if ($id <= 0) return false;
    return $db->fetch_array(
        $db->simple_select('torrents', 'id, name', 'id = ' . $id)
    ) ?: false;
}

/**
 * Пересчитывает счётчик комментариев торрента из реальных данных.
 * Единственное место где обновляется torrents.comments — устраняет дублирование.
 */
function sync_torrent_comment_count(int $torrent_id): void
{
    global $db;
    if ($torrent_id <= 0) return;
    $row = $db->fetch_array(
        $db->sql_query('SELECT COUNT(*) AS cnt FROM comments WHERE torrent = ' . $torrent_id)
    );
    $db->sql_query('UPDATE torrents SET comments = ' . (int)$row['cnt'] . ' WHERE id = ' . $torrent_id);
}

/**
 * Пересчитывает счётчик комментариев пользователя из реальных данных.
 */
function sync_user_comment_count(int $user_id): void
{
    global $db;
    if ($user_id <= 0) return;
    $row = $db->fetch_array(
        $db->sql_query('SELECT COUNT(*) AS cnt FROM comments WHERE user = ' . $user_id)
    );
    $db->sql_query('UPDATE users SET comms = ' . (int)$row['cnt'] . ' WHERE id = ' . $user_id);
}

/**
 * Строит безопасный WHERE для списка id.
 *
 * @param int[] $ids
 */
function ids_to_sql(array $ids): string
{
    return implode(',', array_map('intval', $ids));
}

// ---------------------------------------------------------------------------
// KPS helper — удаление очков за комментарии
// ---------------------------------------------------------------------------

function deduct_kps_for_comments(array $user_ids): void
{
    if (!function_exists('kps')) return;

    global $cache;
    $kpscache = $cache->read('KPS');
    $points   = (int)($kpscache['kpscomment'] ?? 1);

    foreach (array_unique($user_ids) as $uid) {
        if ($uid > 0) {
            kps('-', $points, $uid);
        }
    }
}

// ---------------------------------------------------------------------------
// HTML generators
// ---------------------------------------------------------------------------

function generateCommentsTable(
    mixed  $res,
    int    $total_comments,
    int    $page,
    int    $limit,
    int    $offset,
    int    $total_pages
): string {
    global $parser, $parser_options, $BASEURL, $db, $dateformat, $timeformat;

    $rows = '';
    while ($row = $db->fetch_array($res)) {
        // Логируем orphaned комментарии через write_log вместо error_log
        if ($row['torrent_name'] === null) {
            write_log(sprintf(
                'Orphaned comment ID %d: torrent=%d user=%d (%s)',
                (int)$row['id'],
                (int)$row['torrent'],
                (int)$row['uid'],
                htmlspecialchars($row['username'] ?? '')
            ));
        }

        $parsed_text = $parser->parse_message($row['text'], $parser_options);
        $parsed_text = preg_replace_callback(
            '#<img([^>]*)>#i',
            fn($m) => "<img{$m[1]} style='max-width:100px;height:auto;border-radius:4px;' />",
            $parsed_text
        );

        $pid          = (int)$row['id'];
        $tid          = (int)$row['torrent'];
        $seo_user     = $BASEURL . '/' . get_profile_link((int)$row['uid']);
        $seo_torrent  = $BASEURL . '/' . get_torrent_link($tid);
        $comment_link = $BASEURL . '/' . get_comment_link($pid, $tid);
        $torrent_name = htmlspecialchars($row['torrent_name'] ?? '');
        $username_fmt = format_name(htmlspecialchars($row['username'] ?? ''), $row['usergroup']);
        $date_str     = my_datee($dateformat, (int)$row['dateline']);
        $time_str     = my_datee($timeformat, (int)$row['dateline']);

        $rows .= <<<HTML
        <tr data-comment-id="{$pid}">
            <td>
                <div class="form-check form-switch d-inline-block">
                    <input class="form-check-input comment-checkbox" type="checkbox" value="{$pid}" id="comment{$pid}">
                    <label class="form-check-label" for="comment{$pid}"></label>
                </div>
            </td>
            <td class="fw-bold">
                <a href="{$comment_link}#pid{$pid}" target="_blank">{$pid} <i class="bi bi-link-45deg"></i></a>
            </td>
            <td><a href="{$seo_user}">{$username_fmt}</a></td>
            <td><a href="{$seo_torrent}">{$torrent_name}</a></td>
            <td class="comment-text">{$parsed_text}</td>
            <td>
                <span class="small text-muted"><i class="bi bi-calendar me-1"></i>{$date_str}</span><br>
                <span class="small text-muted"><i class="bi bi-clock me-1"></i>{$time_str}</span>
            </td>
            <td class="text-center">
                <button class="btn btn-sm p-1 me-2" style="background:none;border:none;"
                        onclick="editComment({$pid})" title="Edit">
                    <i class="fa-solid fa-pen-to-square fa-xl" style="color:#0658e5;"></i>
                </button>
                <button class="btn btn-sm p-1" style="background:none;border:none;"
                        onclick="deleteComment({$pid})" title="Delete">
                    <i class="fa-solid fa-trash-can fa-xl" style="color:#eb0f0f;"></i>
                </button>
            </td>
        </tr>
        HTML;
    }

    $start   = $offset + 1;
    $end     = min($offset + $limit, $total_comments);
    $pagination = generatePagination($page, $total_pages);

    return <<<HTML
    <div class="card shadow-sm border-0 bg-white">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Comments Management</h5>
            <div>
                <button class="btn btn-sm btn-warning me-2" data-bs-toggle="modal" data-bs-target="#mergeCommentsModal">
                    <i class="bi bi-arrow-left-right"></i> Move Comments
                </button>
                <button class="btn btn-sm btn-info me-2" data-bs-toggle="modal" data-bs-target="#copyCommentsModal">
                    <i class="bi bi-copy"></i> Copy Comments
                </button>
                <button id="bulkDeleteBtn" class="btn btn-sm btn-danger me-2" disabled>
                    <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                </button>
                <button id="selectAllBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check-all"></i> Select All
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </div>
                        </th>
                        <th width="50">#</th>
                        <th>User</th>
                        <th>Torrent</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th width="120" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <div class="text-muted small">Showing <b>{$start}</b> – <b>{$end}</b> of <b>{$total_comments}</b> comments</div>
        {$pagination}
    </div>
    HTML;
}

function generatePagination(int $current, int $total): string
{
    if ($total <= 1) return '';

    $range = 2;
    $out   = '<nav><ul class="pagination pagination-sm mb-0">';

    // Prev
    $prev_disabled = $current <= 1 ? 'disabled' : '';
    $prev          = max(1, $current - 1);
    $out .= "<li class=\"page-item {$prev_disabled}\"><a class=\"page-link\" href=\"javascript:void(0);\" onclick=\"loadComments({$prev})\">&laquo; Prev</a></li>";

    // First + ellipsis
    if ($current - $range > 1) {
        $out .= '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadComments(1)">1</a></li>';
        if ($current - $range > 2) {
            $out .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    // Pages
    for ($i = max(1, $current - $range); $i <= min($total, $current + $range); $i++) {
        $active = $i === $current ? 'active' : '';
        $out   .= "<li class=\"page-item {$active}\"><a class=\"page-link\" href=\"javascript:void(0);\" onclick=\"loadComments({$i})\">{$i}</a></li>";
    }

    // Last + ellipsis
    if ($current + $range < $total) {
        if ($current + $range < $total - 1) {
            $out .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $out .= "<li class=\"page-item\"><a class=\"page-link\" href=\"javascript:void(0);\" onclick=\"loadComments({$total})\">{$total}</a></li>";
    }

    // Next
    $next_disabled = $current >= $total ? 'disabled' : '';
    $next          = min($total, $current + 1);
    $out .= "<li class=\"page-item {$next_disabled}\"><a class=\"page-link\" href=\"javascript:void(0);\" onclick=\"loadComments({$next})\">Next &raquo;</a></li>";

    return $out . '</ul></nav>';
}

// ---------------------------------------------------------------------------
// AJAX router
// ---------------------------------------------------------------------------

if (!isset($_GET['action'])) {
    // Покажем HTML-страницу ниже
    goto render_page;
}

$action = (string)($_GET['action'] ?? '');

// ── search_torrents ──────────────────────────────────────────────────────────
if ($action === 'search_torrents' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        json_exit([]);
    }

    $q_escaped = $db->escape_string($q);
    $res       = $db->sql_query(
        "SELECT id, name FROM torrents WHERE name LIKE '%" . $q_escaped . "%' ORDER BY name ASC LIMIT 20"
    );
    $results = [];
    while ($row = $db->fetch_array($res)) {
        $results[] = ['id' => (int)$row['id'], 'name' => htmlspecialchars($row['name'])];
    }
    json_exit($results);
}

// ── list ─────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $limit  = 20;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Безопасная фильтрация — все строки через escape_string
    $where = [];

    $username = trim((string)($_GET['username'] ?? ''));
    if ($username !== '') {
        $where[] = "u.username LIKE '%" . $db->escape_string_like($username) . "%'";
    }

    $torrent_filter = trim((string)($_GET['torrent'] ?? ''));
    if ($torrent_filter !== '') {
        $where[] = "t.name LIKE '%" . $db->escape_string_like($torrent_filter) . "%'";
    }

    // Дата — принимаем только формат YYYY-MM-DD, парсим через strtotime
    $date_from_raw = preg_match('#^\d{4}-\d{2}-\d{2}$#', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
    $date_to_raw   = preg_match('#^\d{4}-\d{2}-\d{2}$#', $_GET['date_to']   ?? '') ? $_GET['date_to']   : '';

    if ($date_from_raw !== '') {
        $where[] = 'c.dateline >= ' . (int)strtotime($date_from_raw . ' 00:00:00');
    }
    if ($date_to_raw !== '') {
        $where[] = 'c.dateline <= ' . (int)strtotime($date_to_raw . ' 23:59:59');
    }

    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $total_row     = $db->fetch_array($db->sql_query("
        SELECT COUNT(*) AS cnt
        FROM comments c
        LEFT JOIN users u ON c.user = u.id
        LEFT JOIN torrents t ON c.torrent = t.id
        {$where_sql}
    "));
    $total_comments = (int)$total_row['cnt'];
    $total_pages    = max(1, (int)ceil($total_comments / $limit));

    if ($total_comments === 0) {
        echo '<div class="text-center py-5">
            <i class="fa-regular fa-comments fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">No comments found</h4>
        </div>';
        exit;
    }

    $res = $db->sql_query("
        SELECT c.*, u.username, u.usergroup, u.id AS uid, t.name AS torrent_name
        FROM comments c
        LEFT JOIN users u ON c.user = u.id
        LEFT JOIN torrents t ON c.torrent = t.id
        {$where_sql}
        ORDER BY c.dateline DESC
        LIMIT {$offset}, {$limit}
    ");

    echo generateCommentsTable($res, $total_comments, $page, $limit, $offset, $total_pages);
    exit;
}

// ── preview ───────────────────────────────────────────────────────────────────
if ($action === 'preview') {
    require_post();
    $text        = (string)($_POST['text'] ?? '');
    $parsed_text = $parser->parse_message($text, $parser_options);
    $parsed_text = preg_replace_callback(
        '#<img([^>]*)>#i',
        fn($m) => "<img{$m[1]} style='max-width:200px;height:auto;border-radius:4px;' />",
        $parsed_text
    );
    echo $parsed_text;
    exit;
}

// ── edit (GET — получить текст) ───────────────────────────────────────────────
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id      = max(0, (int)($_GET['id'] ?? 0));
    $comment = $db->fetch_array($db->simple_select('comments', 'id, text', 'id = ' . $id));
    if (!$comment) {
        json_exit(['error' => 'Comment not found'], 404);
    }
    json_exit(['text' => $comment['text']]);
}

// ── save ─────────────────────────────────────────────────────────────────────
if ($action === 'save') {
    require_post();
    require_csrf();

    $id   = max(0, (int)($_POST['id'] ?? 0));
    $text = trim((string)($_POST['text'] ?? ''));

    $comment = $db->fetch_array($db->simple_select('comments', 'id', 'id = ' . $id));
    if (!$comment) {
        json_exit(['success' => false, 'error' => 'Comment not found'], 404);
    }

    // Валидация — убираем пробелы и проверяем длину содержательного текста
    if (mb_strlen($text) < 3 || mb_strlen(preg_replace('/\s+/u', '', $text)) < 3) {
        json_exit(['success' => false, 'error' => 'Comment must contain meaningful text (min 3 chars)']);
    }

    $db->update_query('comments', [
        'text'     => $db->escape_string($text),
        'editedat' => TIMENOW,
        'editedby' => (int)($CURUSER['id'] ?? 0),
    ], 'id = ' . $id);

    json_exit(['success' => true]);
}








// ── delete (одиночный) ───────────────────────────────────────────────────────
if ($action === 'delete') {
    require_post();
    require_csrf();
    $id      = max(0, (int)($_POST['id'] ?? 0));
    $comment = $db->fetch_array($db->sql_query(
        'SELECT user, torrent FROM comments WHERE id = ' . $id
    ));
    if (!$comment) {
        json_exit(['success' => false, 'error' => 'Comment not found'], 404);
    }
    $user_id    = (int)$comment['user'];
    $torrent_id = (int)$comment['torrent'];
    // Удаляем прикреплённые файлы (comment_files)
    $files = $db->simple_select('comment_files', '*', 'comment_id = ' . $id);
    while ($file = $db->fetch_array($files)) {
        if (!empty($file['file_path']) && is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->delete_query('comment_files', 'comment_id = ' . $id);
    // Удаляем вложения (attachments)
    $uploadDir = TSDIR . '/uploads/attachments/';
    $atts = $db->simple_select('attachments', 'attachname, thumbnail', 'comment_id = ' . $id);
    while ($att = $db->fetch_array($atts)) {
        if (!empty($att['attachname'])) @unlink($uploadDir . $att['attachname']);
        if (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL') @unlink($uploadDir . $att['thumbnail']);
    }
    $db->delete_query('attachments', 'comment_id = ' . $id);
    $db->delete_query('comments', 'id = ' . $id);
    // Пересчёт счётчиков
    sync_torrent_comment_count($torrent_id);
    sync_user_comment_count($user_id);
    deduct_kps_for_comments([$user_id]);
    write_log(sprintf(
        'User %s (UID %d) deleted comment #%d from torrent #%d',
        htmlspecialchars($CURUSER['username']),
        (int)$CURUSER['id'],
        $id,
        $torrent_id
    ));
    json_exit(['success' => true]);
}
// ── bulk_delete ───────────────────────────────────────────────────────────────
if ($action === 'bulk_delete') {
    require_post();
    require_csrf();
    $ids     = decode_comment_ids($_POST['ids'] ?? []);
    $ids_str = ids_to_sql($ids);
    // Получаем данные перед удалением
    $user_ids    = [];
    $torrent_ids = [];
    $query       = $db->sql_query("SELECT id, user, torrent FROM comments WHERE id IN ({$ids_str})");
    while ($row = $db->fetch_array($query)) {
        $user_ids[]    = (int)$row['user'];
        $torrent_ids[] = (int)$row['torrent'];
    }
    // Удаляем файлы (comment_files)
    $files = $db->sql_query("SELECT file_path FROM comment_files WHERE comment_id IN ({$ids_str})");
    while ($file = $db->fetch_array($files)) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->sql_query("DELETE FROM comment_files WHERE comment_id IN ({$ids_str})");
    // Удаляем вложения (attachments)
    $uploadDir = TSDIR . '/uploads/attachments/';
    $atts = $db->sql_query("SELECT attachname, thumbnail FROM attachments WHERE comment_id IN ({$ids_str})");
    while ($att = $db->fetch_array($atts)) {
        if (!empty($att['attachname'])) @unlink($uploadDir . $att['attachname']);
        if (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL') @unlink($uploadDir . $att['thumbnail']);
    }
    $db->sql_query("DELETE FROM attachments WHERE comment_id IN ({$ids_str})");
    $db->sql_query("DELETE FROM comments WHERE id IN ({$ids_str})");
    $deleted = (int)$db->affected_rows();
    // Пересчёт через sync_
    foreach (array_unique($torrent_ids) as $tid) {
        sync_torrent_comment_count($tid);
    }
    foreach (array_unique($user_ids) as $uid) {
        sync_user_comment_count($uid);
    }
    deduct_kps_for_comments($user_ids);
    write_log(sprintf(
        'User %s (UID %d) bulk-deleted %d comment(s): [%s]',
        htmlspecialchars($CURUSER['username']),
        (int)$CURUSER['id'],
        $deleted,
        $ids_str
    ));
    json_exit(['success' => true, 'deleted' => $deleted]);
}









// ── move_comments ─────────────────────────────────────────────────────────────
if ($action === 'move_comments') {
    require_post();
    require_csrf();

    $ids        = decode_comment_ids($_POST['comment_ids'] ?? '');
    $target_tid = max(0, (int)($_POST['target_tid'] ?? 0));

    if ($target_tid <= 0) {
        json_exit(['error' => 'Invalid target torrent ID']);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => 'Target torrent not found'], 404);
    }

    $ids_str    = ids_to_sql($ids);
    $source_ids = [];

    $res = $db->sql_query("SELECT id, torrent FROM comments WHERE id IN ({$ids_str})");
    while ($row = $db->fetch_array($res)) {
        $source_ids[] = (int)$row['torrent'];
    }

    $db->sql_query("UPDATE comments SET torrent = {$target_tid} WHERE id IN ({$ids_str})");
    $moved = (int)$db->affected_rows();

    // Пересчёт счётчиков для всех затронутых торрентов
    foreach (array_unique([...$source_ids, $target_tid]) as $tid) {
        sync_torrent_comment_count($tid);
    }

    write_log(sprintf(
        'User %s (UID %d) moved %d comment(s) [%s] to torrent #%d (%s)',
        htmlspecialchars($CURUSER['username']),
        (int)$CURUSER['id'],
        $moved,
        $ids_str,
        $target_tid,
        htmlspecialchars($torrent['name'])
    ));

    json_exit(['success' => true, 'moved' => $moved, 'target_torrent' => $torrent['name']]);
}

// ── copy_comments ─────────────────────────────────────────────────────────────
if ($action === 'copy_comments') {
    require_post();
    require_csrf(); // Включён — в оригинале был закомментирован

    $ids        = decode_comment_ids($_POST['comment_ids'] ?? '');
    $target_tid = max(0, (int)($_POST['target_tid'] ?? 0));

    if ($target_tid <= 0) {
        json_exit(['error' => 'Invalid target torrent ID']);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => 'Target torrent not found'], 404);
    }

    $ids_str   = ids_to_sql($ids);
    $res       = $db->sql_query("SELECT * FROM comments WHERE id IN ({$ids_str})");
    $copied    = 0;
    $user_ids  = [];

    while ($row = $db->fetch_array($res)) {
        $db->insert_query('comments', [
            'user'      => (int)$row['user'],
            'torrent'   => $target_tid,
            'text'      => $db->escape_string($row['text']),
            'dateline'  => (int)$row['dateline'],
            'editreason'=> $db->escape_string($row['editreason'] ?? ''),
            'editedby'  => (int)$row['editedby'],
            'editedat'  => (int)$row['editedat'],
        ]);
        $user_ids[] = (int)$row['user'];
        $copied++;
    }

    // Пересчёт счётчиков
    sync_torrent_comment_count($target_tid);
    foreach (array_unique($user_ids) as $uid) {
        sync_user_comment_count($uid);
    }

    write_log(sprintf(
        'User %s (UID %d) copied %d comment(s) [%s] to torrent #%d (%s)',
        htmlspecialchars($CURUSER['username']),
        (int)$CURUSER['id'],
        $copied,
        $ids_str,
        $target_tid,
        htmlspecialchars($torrent['name'])
    ));

    json_exit(['success' => true, 'copied' => $copied, 'target_torrent' => $torrent['name']]);
}

// Неизвестный action
json_exit(['error' => 'Unknown action'], 400);

// ---------------------------------------------------------------------------
// HTML страница
// ---------------------------------------------------------------------------
render_page:

stdhead('Comments Admin');
?>
<script>const my_post_key = '<?= htmlspecialchars($mybb->post_code, ENT_QUOTES) ?>';</script>

<div class="container mt-4">
    <h1 class="mb-4 text-dark"><i class="bi bi-chat-text"></i> Comments Admin</h1>

    <!-- Фильтры -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Search by user…">
                </div>
                <div class="col-md-3">
                    <label for="torrent" class="form-label">Torrent</label>
                    <input type="text" class="form-control" id="torrent" name="torrent" placeholder="Search by torrent…">
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <button type="button" id="resetFilters" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица -->
    <div id="comments-table" class="fade-in">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
            <p class="mt-2 text-muted">Loading comments…</p>
        </div>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade" id="mergeCommentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow-sm">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Move Selected Comments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label for="targetTorrent" class="form-label">Target Torrent ID</label>
                <input type="number" class="form-control" id="targetTorrent" placeholder="Enter target torrent ID">
                <div class="alert alert-info small mt-3">
                    <strong>Selected:</strong> <span id="selectedCommentsCount">0</span> comments.<br>
                    This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmMergeBtn" type="button" class="btn btn-warning">Move Comments</button>
            </div>
        </div>
    </div>
</div>

<!-- Copy Modal -->
<div class="modal fade" id="copyCommentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow-sm">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title">Copy Selected Comments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label for="copyTargetTorrent" class="form-label">Target Torrent ID</label>
                <input type="number" class="form-control" id="copyTargetTorrent" placeholder="Enter target torrent ID">
                <div class="alert alert-info small mt-3">
                    <strong>Selected:</strong> <span id="copySelectedCount">0</span> comments.<br>
                    Originals remain intact.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmCopyBtn" type="button" class="btn btn-info">Copy Comments</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow-sm rounded-3 border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Comment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 d-flex flex-wrap gap-1">
                    <?php
                    $bbcode_buttons = [
                        ['[b]','[/b]','<b>B</b>'], ['[i]','[/i]','<i>I</i>'],
                        ['[u]','[/u]','<u>U</u>'], ['[s]','[/s]','<s>S</s>'],
                        ['[left]','[/left]','Left'], ['[center]','[/center]','Center'],
                        ['[right]','[/right]','Right'], ['[color=red]','[/color]','Red'],
                        ['[size=18]','[/size]','Size'], ['[url]','[/url]','URL'],
                        ['[img]','[/img]','IMG'], ['[video]','[/video]','Video'],
                        ['[youtube]','[/youtube]','YouTube'], ['[quote]','[/quote]','Quote'],
                        ['[code]','[/code]','Code'],
                    ];
                    foreach ($bbcode_buttons as [$open, $close, $label]) {
                        $open_esc  = htmlspecialchars($open,  ENT_QUOTES);
                        $close_esc = htmlspecialchars($close, ENT_QUOTES);
                        echo "<button class=\"btn btn-sm btn-light\" onclick=\"wrapBBCode('{$open_esc}','{$close_esc}')\">{$label}</button>\n";
                    }
                    ?>
                </div>
                <textarea id="editCommentText" class="form-control mb-3" rows="6" placeholder="Edit your comment…"></textarea>
                <h6>Live Preview</h6>
                <div id="bbcodePreview" class="border p-2 bg-light rounded" style="min-height:100px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmEditComment" type="button" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirm Modal -->
<div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="bulkDeleteMessage">Are you sure you want to delete the selected comments?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmBulkDeleteBtn" type="button" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index:1100;"></div>

<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/bootstrap-icons.css">

<style>
#selectAll, .comment-checkbox { transform: scale(1.2); cursor: pointer; }
.table-active { background-color: rgba(0,123,255,.1) !important; }
#bulkDeleteBtn:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,.1); }
</style>

<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/toast.js"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/comments-admin.js?v=1.1"></script>

<?php stdfoot(); ?>