<?php
declare(strict_types=1);

define('IN_ARCHIVE', true);
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$parser         = new postParser();
$parser_options = [
    'allow_html'     => 0,
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
 * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
 */
function lc_escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

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
    $q = $db->sql_query_prepared('SELECT id, name FROM torrents WHERE id = ?', [$id]);
    return ($q ? $db->fetch_array($q) : null) ?: false;
}

/**
 * Пересчитывает счётчик комментариев торрента из реальных данных.
 * Единственное место где обновляется torrents.comments — устраняет дублирование.
 */
function sync_torrent_comment_count(int $torrent_id): void
{
    global $db;
    if ($torrent_id <= 0) return;
    $q = $db->sql_query_prepared('SELECT COUNT(*) AS cnt FROM comments WHERE torrent = ?', [$torrent_id]);
    $row = $q ? $db->fetch_array($q) : null;
    $db->sql_query_prepared('UPDATE torrents SET comments = ? WHERE id = ?', [(int)($row['cnt'] ?? 0), $torrent_id]);
}

/**
 * Пересчитывает счётчик комментариев пользователя из реальных данных.
 */
function sync_user_comment_count(int $user_id): void
{
    global $db;
    if ($user_id <= 0) return;
    $q = $db->sql_query_prepared('SELECT COUNT(*) AS cnt FROM comments WHERE user = ?', [$user_id]);
    $row = $q ? $db->fetch_array($q) : null;
    $db->sql_query_prepared('UPDATE users SET comms = ? WHERE id = ?', [(int)($row['cnt'] ?? 0), $user_id]);
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

/**
 * Стабильный цвет аватара-инициала по UID.
 */
function lc_avatar_color(int $uid): string
{
    $hue = ($uid * 47) % 360;
    return "hsl({$hue} 55% 48%)";
}

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
        $uid          = (int)($row['uid'] ?? 0);
        $seo_user     = $BASEURL . '/' . get_profile_link($uid);
        $seo_torrent  = $BASEURL . '/' . get_torrent_link($tid);
        $comment_link = $BASEURL . '/' . get_comment_link($pid, $tid);
        $date_str     = my_datee($dateformat, (int)$row['dateline']);
        $time_str     = my_datee($timeformat, (int)$row['dateline']);

        // Автор
        if ($row['username'] !== null) {
            $raw_name     = (string)$row['username'];
            $initial      = htmlspecialchars(mb_strtoupper(mb_substr($raw_name, 0, 1)));
            $av_color     = lc_avatar_color($uid);
            $username_fmt = format_name(htmlspecialchars($raw_name), $row['usergroup']);
            $user_html    = <<<HTML
            <div class="lc-user">
                <span class="lc-avatar" style="--lc-av:{$av_color}">{$initial}</span>
                <a href="{$seo_user}" class="lc-user-name">{$username_fmt}</a>
            </div>
            HTML;
        } else {
            $user_html = '<div class="lc-user"><span class="lc-avatar lc-avatar-ghost"><i class="fa-solid fa-user-slash"></i></span>'
                       . '<span class="text-body-secondary fst-italic">Deleted user</span></div>';
        }

        // Торрент
        if ($row['torrent_name'] !== null) {
            $torrent_name = htmlspecialchars($row['torrent_name']);
            $torrent_html = <<<HTML
            <a href="{$seo_torrent}" class="lc-torrent" title="{$torrent_name}">
                <i class="fa-solid fa-magnet"></i><span>{$torrent_name}</span>
            </a>
            HTML;
        } else {
            $torrent_html = "<span class=\"lc-badge lc-soft-danger\"><i class=\"fa-solid fa-triangle-exclamation\"></i> Deleted torrent #{$tid}</span>";
        }

        // Отметка о редактировании
        $edited_html = '';
        $edited_at   = (int)($row['editedat'] ?? 0);
        if ($edited_at > 0) {
            $edited_str  = my_datee($dateformat, $edited_at) . ' ' . my_datee($timeformat, $edited_at);
            $edited_html = "<div class=\"lc-edited\"><i class=\"fa-solid fa-pen\"></i> edited {$edited_str}</div>";
        }

        $rows .= <<<HTML
        <tr data-comment-id="{$pid}">
            <td class="lc-col-check">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input comment-checkbox" type="checkbox" value="{$pid}" id="comment{$pid}">
                    <label class="form-check-label" for="comment{$pid}"></label>
                </div>
            </td>
            <td>
                <a class="lc-id-pill" href="{$comment_link}#pid{$pid}" target="_blank" rel="noopener" title="Open comment in new tab">
                    #{$pid} <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </td>
            <td>{$user_html}</td>
            <td>{$torrent_html}</td>
            <td class="comment-text">
                <div class="lc-comment">{$parsed_text}</div>
                {$edited_html}
            </td>
            <td class="lc-date">
                <div><i class="fa-regular fa-calendar"></i>{$date_str}</div>
                <div><i class="fa-regular fa-clock"></i>{$time_str}</div>
            </td>
            <td class="text-end">
                <div class="lc-row-actions">
                    <button type="button" class="lc-icon-btn lc-edit" onclick="editComment({$pid})" title="Edit comment">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="lc-icon-btn lc-delete" onclick="deleteComment({$pid})" title="Delete comment">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </td>
        </tr>
        HTML;
    }

    $start      = number_format($offset + 1);
    $end        = number_format(min($offset + $limit, $total_comments));
    $total_fmt  = number_format($total_comments);
    $pagination = multipage($total_comments, $limit, $page, '#', false);

    return <<<HTML
    <div class="lc-card lc-table-card">
        <div class="lc-card-head">
            <div class="d-flex align-items-center gap-3">
                <span class="lc-icon-sq sm lc-soft-primary"><i class="fa-solid fa-list-ul"></i></span>
                <div>
                    <h5 class="lc-card-title">Comments</h5>
                    <div class="lc-card-sub">Newest first · page {$page} of {$total_pages}</div>
                </div>
            </div>
            <span class="lc-badge lc-soft-primary"><i class="fa-solid fa-filter"></i> {$total_fmt} found</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 lc-table">
                <thead>
                    <tr>
                        <th width="48">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="selectAll" title="Select all on page">
                            </div>
                        </th>
                        <th width="90"><i class="fa-solid fa-hashtag"></i> ID</th>
                        <th><i class="fa-solid fa-user"></i> User</th>
                        <th><i class="fa-solid fa-magnet"></i> Torrent</th>
                        <th><i class="fa-solid fa-comment"></i> Comment</th>
                        <th><i class="fa-regular fa-calendar"></i> Date</th>
                        <th width="110" class="text-end"><i class="fa-solid fa-gear"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>

        <div class="lc-actionbar">
            <button id="selectAllBtn" type="button" class="btn btn-sm lc-pill lc-btn-soft">
                <i class="fa-solid fa-check-double"></i> Select All
            </button>
            <div class="lc-actionbar-group">
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-warning" data-bs-toggle="modal" data-bs-target="#moveCommentsModal">
                    <i class="fa-solid fa-right-left"></i> Move
                </button>
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-info" data-bs-toggle="modal" data-bs-target="#copyCommentsModal">
                    <i class="fa-solid fa-copy"></i> Copy
                </button>
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-primary" data-bs-toggle="modal" data-bs-target="#mergeIntoOneModal">
                    <i class="fa-solid fa-object-group"></i> Merge
                </button>
                <button id="bulkDeleteBtn" type="button" class="btn btn-sm lc-pill btn-danger" disabled>
                    <i class="fa-solid fa-trash-can"></i> Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <div class="lc-pager">
        <div><i class="fa-solid fa-layer-group"></i> Showing <b>{$start}</b> – <b>{$end}</b> of <b>{$total_fmt}</b> comments</div>
        {$pagination}
    </div>
    HTML;
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

    $res = $db->sql_query_prepared(
        "SELECT id, name FROM torrents WHERE name LIKE ? ORDER BY name ASC LIMIT 20",
        ['%' . lc_escape_like($q) . '%']
    );
    $results = [];
    while ($res && ($row = $db->fetch_array($res))) {
        $results[] = ['id' => (int)$row['id'], 'name' => htmlspecialchars($row['name'])];
    }
    json_exit($results);
}

// ── list ─────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $limit  = 20;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Безопасная фильтрация — все строки через bind-параметры
    $where = [];
    $where_params = [];

    $username = trim((string)($_GET['username'] ?? ''));
    if ($username !== '') {
        $where[] = "u.username LIKE ?";
        $where_params[] = '%' . lc_escape_like($username) . '%';
    }

    $torrent_filter = trim((string)($_GET['torrent'] ?? ''));
    if ($torrent_filter !== '') {
        $where[] = "t.name LIKE ?";
        $where_params[] = '%' . lc_escape_like($torrent_filter) . '%';
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

    $total_q = $db->sql_query_prepared("
        SELECT COUNT(*) AS cnt
        FROM comments c
        LEFT JOIN users u ON c.user = u.id
        LEFT JOIN torrents t ON c.torrent = t.id
        {$where_sql}
    ", $where_params);
    $total_row = $total_q ? $db->fetch_array($total_q) : null;
    $total_comments = (int)($total_row['cnt'] ?? 0);
    $total_pages    = max(1, (int)ceil($total_comments / $limit));

    if ($total_comments === 0) {
        echo '<div class="lc-card lc-empty">
            <span class="lc-icon-sq xl lc-soft-secondary"><i class="fa-regular fa-comments"></i></span>
            <h4 class="lc-card-title">No comments found</h4>
            <p class="lc-card-sub mb-0">Try changing or resetting the filters.</p>
        </div>';
        exit;
    }

    $res = $db->sql_query_prepared("
        SELECT c.*, u.username, u.usergroup, u.id AS uid, t.name AS torrent_name
        FROM comments c
        LEFT JOIN users u ON c.user = u.id
        LEFT JOIN torrents t ON c.torrent = t.id
        {$where_sql}
        ORDER BY c.dateline DESC
        LIMIT ?, ?
    ", [...$where_params, $offset, $limit]);

    echo generateCommentsTable($res, $total_comments, $page, $limit, $offset, $total_pages);
    exit;
}

// ── preview ───────────────────────────────────────────────────────────────────
if ($action === 'preview') {
    require_post();
    require_csrf();
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
    $q = $db->sql_query_prepared('SELECT id, text FROM comments WHERE id = ?', [$id]);
    $comment = $q ? $db->fetch_array($q) : null;
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

    $q = $db->sql_query_prepared('SELECT id FROM comments WHERE id = ?', [$id]);
    $comment = $q ? $db->fetch_array($q) : null;
    if (!$comment) {
        json_exit(['success' => false, 'error' => 'Comment not found'], 404);
    }

    // Валидация — убираем пробелы и проверяем длину содержательного текста
    if (mb_strlen($text) < 3 || mb_strlen(preg_replace('/\s+/u', '', $text)) < 3) {
        json_exit(['success' => false, 'error' => 'Comment must contain meaningful text (min 3 chars)']);
    }

    $db->sql_query_prepared(
        'UPDATE comments SET text = ?, editedat = ?, editedby = ? WHERE id = ?',
        [$text, TIMENOW, (int)($CURUSER['id'] ?? 0), $id]
    );

    json_exit(['success' => true]);
}








// ── delete (одиночный) ───────────────────────────────────────────────────────
if ($action === 'delete') {
    require_post();
    require_csrf();
    $id      = max(0, (int)($_POST['id'] ?? 0));
    $q = $db->sql_query_prepared('SELECT user, torrent FROM comments WHERE id = ?', [$id]);
    $comment = $q ? $db->fetch_array($q) : null;
    if (!$comment) {
        json_exit(['success' => false, 'error' => 'Comment not found'], 404);
    }
    $user_id    = (int)$comment['user'];
    $torrent_id = (int)$comment['torrent'];
    // Удаляем прикреплённые файлы (comment_files)
    $files = $db->sql_query_prepared('SELECT * FROM comment_files WHERE comment_id = ?', [$id]);
    while ($files && ($file = $db->fetch_array($files))) {
        if (!empty($file['file_path']) && is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->sql_query_prepared('DELETE FROM comment_files WHERE comment_id = ?', [$id]);
    // Удаляем вложения (attachments)
    $uploadDir = TSDIR . '/uploads/attachments/';
    $atts = $db->sql_query_prepared('SELECT attachname, thumbnail FROM attachments WHERE comment_id = ?', [$id]);
    while ($atts && ($att = $db->fetch_array($atts))) {
        if (!empty($att['attachname'])) @unlink($uploadDir . $att['attachname']);
        if (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL') @unlink($uploadDir . $att['thumbnail']);
    }
    $db->sql_query_prepared('DELETE FROM attachments WHERE comment_id = ?', [$id]);
    $db->sql_query_prepared('DELETE FROM comments WHERE id = ?', [$id]);
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
    $query       = $db->sql_query_prepared("SELECT id, user, torrent FROM comments WHERE id IN ({$ids_str})");
    while ($query && ($row = $db->fetch_array($query))) {
        $user_ids[]    = (int)$row['user'];
        $torrent_ids[] = (int)$row['torrent'];
    }
    // Удаляем файлы (comment_files)
    $files = $db->sql_query_prepared("SELECT file_path FROM comment_files WHERE comment_id IN ({$ids_str})");
    while ($files && ($file = $db->fetch_array($files))) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->sql_query_prepared("DELETE FROM comment_files WHERE comment_id IN ({$ids_str})");
    // Удаляем вложения (attachments)
    $uploadDir = TSDIR . '/uploads/attachments/';
    $atts = $db->sql_query_prepared("SELECT attachname, thumbnail FROM attachments WHERE comment_id IN ({$ids_str})");
    while ($atts && ($att = $db->fetch_array($atts))) {
        if (!empty($att['attachname'])) @unlink($uploadDir . $att['attachname']);
        if (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL') @unlink($uploadDir . $att['thumbnail']);
    }
    $db->sql_query_prepared("DELETE FROM attachments WHERE comment_id IN ({$ids_str})");
    $db->sql_query_prepared("DELETE FROM comments WHERE id IN ({$ids_str})");
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

    $res = $db->sql_query_prepared("SELECT id, torrent FROM comments WHERE id IN ({$ids_str})");
    while ($res && ($row = $db->fetch_array($res))) {
        $source_ids[] = (int)$row['torrent'];
    }

    $db->sql_query_prepared("UPDATE comments SET torrent = ? WHERE id IN ({$ids_str})", [$target_tid]);
    $moved = (int)$db->affected_rows();

    // comment_files.torrent_id заполняется независимо от comment_id в
    // момент загрузки файла (см. upload_image.php) - это не производное
    // поле, значит при переносе комментария на другой торрент его тоже
    // нужно синхронизировать, иначе останется указывать на старый торрент.
    $db->sql_query_prepared(
        "UPDATE comment_files SET torrent_id = ? WHERE comment_id IN ({$ids_str})",
        [$target_tid]
    );

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

    $ids_str    = ids_to_sql($ids);
    $res        = $db->sql_query_prepared("SELECT * FROM comments WHERE id IN ({$ids_str})");
    $copied     = 0;
    $user_ids   = [];
    $uploadDir  = TSDIR . '/uploads/attachments/';

    while ($res && ($row = $db->fetch_array($res))) {
        $db->sql_query_prepared(
            "INSERT INTO comments (`user`,`torrent`,`text`,`dateline`,`editreason`,`editedby`,`editedat`) VALUES (?,?,?,?,?,?,?)",
            [
                (int)$row['user'],
                $target_tid,
                $row['text'],
                (int)$row['dateline'],
                $row['editreason'] ?? '',
                (int)$row['editedby'],
                (int)$row['editedat'],
            ]
        );
        $new_comment_id = (int)$db->insert_id();

        // ── Копируем вложения (attachments, comment_id-тип) ──────────────────
        // Физически дублируем файл на диске под новым именем - расшаривать
        // один и тот же физический файл между двумя строками нельзя: удаление
        // одной из копий комментария unlink()'ит файл и оставит вторую с
        // мёртвой ссылкой (см. delete_comment/delete_comments выше).
        $atts = $db->sql_query_prepared('SELECT * FROM attachments WHERE comment_id = ?', [(int)$row['id']]);
        while ($atts && ($attRow = $db->fetch_array($atts))) {
            $srcAttachname = $attRow['attachname'];
            $srcPath       = $uploadDir . $srcAttachname;

            if ($srcAttachname === '' || !is_file($srcPath)) {
                continue; // исходный файл потерян - не создаём битую ссылку у копии
            }

            $ext           = pathinfo($srcAttachname, PATHINFO_EXTENSION);
            $newAttachname = bin2hex(random_bytes(16)) . ($ext !== '' ? ".{$ext}" : '');

            if (!@copy($srcPath, $uploadDir . $newAttachname)) {
                continue; // не удалось скопировать файл - строку в БД без файла не создаём
            }

            $newThumbnail = '';
            $srcThumbnail = $attRow['thumbnail'] ?? '';
            if ($srcThumbnail === 'SMALL') {
                $newThumbnail = 'SMALL'; // спец-значение, не файл на диске
            } elseif ($srcThumbnail !== '' && is_file($uploadDir . $srcThumbnail)) {
                $thumbExt     = pathinfo($srcThumbnail, PATHINFO_EXTENSION);
                $tryThumbName = bin2hex(random_bytes(16)) . ($thumbExt !== '' ? ".{$thumbExt}" : '');
                $newThumbnail = @copy($uploadDir . $srcThumbnail, $uploadDir . $tryThumbName)
                    ? $tryThumbName
                    : ''; // превью не скопировалось - не критично, просто без неё
            }

            $db->sql_query_prepared(
                "INSERT INTO attachments (`pid`,`comment_id`,`posthash`,`uid`,`filename`,`filetype`,`filesize`,`attachname`,`downloads`,`dateuploaded`,`visible`,`thumbnail`)
                 VALUES (0,?,?,?,?,?,?,?,0,?,?,?)",
                [
                    $new_comment_id,
                    $attRow['posthash'],
                    (int)$attRow['uid'],
                    $attRow['filename'],
                    $attRow['filetype'],
                    (int)$attRow['filesize'],
                    $newAttachname,
                    TIMENOW,
                    (int)$attRow['visible'],
                    $newThumbnail,
                ]
            );
        }

        // ── Копируем вложения (comment_files) ─────────────────────────────────
        $cfiles = $db->sql_query_prepared('SELECT * FROM comment_files WHERE comment_id = ?', [(int)$row['id']]);
        while ($cfiles && ($cfRow = $db->fetch_array($cfiles))) {
            $srcPath = $cfRow['file_path'];

            if ($srcPath === '' || !is_file($srcPath)) {
                continue; // исходный файл потерян - не создаём битую ссылку у копии
            }

            $ext         = pathinfo($srcPath, PATHINFO_EXTENSION);
            $newFileName = bin2hex(random_bytes(16)) . ($ext !== '' ? ".{$ext}" : '');
            $newPath     = dirname($srcPath) . '/' . $newFileName;

            if (!@copy($srcPath, $newPath)) {
                continue; // не удалось скопировать файл - строку в БД без файла не создаём
            }

            // file_url строим по той же схеме, что и file_path - меняем
            // только имя файла, сохраняя структуру каталога исходного URL.
            $newFileUrl = rtrim(str_replace(basename($cfRow['file_url']), '', $cfRow['file_url']), '/') . '/' . $newFileName;

            $db->sql_query_prepared(
                "INSERT INTO comment_files (`comment_id`,`torrent_id`,`user_id`,`file_name`,`file_path`,`file_url`,`file_type`,`file_size`)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $new_comment_id,
                    $target_tid,
                    $cfRow['user_id'] !== null ? (int)$cfRow['user_id'] : null,
                    $cfRow['file_name'],
                    $newPath,
                    $newFileUrl,
                    $cfRow['file_type'],
                    (int)$cfRow['file_size'],
                ]
            );
            // uploaded_at сознательно не указываем - есть DEFAULT
            // CURRENT_TIMESTAMP, пусть MySQL сама проставит время копии.
        }

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

// ── merge_comments ────────────────────────────────────────────────────────────
if ($action === 'merge_comments') {
    require_post();
    require_csrf();

    $ids        = decode_comment_ids($_POST['comment_ids'] ?? '');
    $target_tid = max(0, (int)($_POST['target_tid'] ?? 0));

    if (count($ids) < 2) {
        json_exit(['error' => 'Select at least 2 comments to merge']);
    }
    if ($target_tid <= 0) {
        json_exit(['error' => 'Invalid target torrent ID']);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => 'Target torrent not found'], 404);
    }

    $ids_str = ids_to_sql($ids);

    // Читаем в хронологическом порядке (dateline ASC) - текст склеивается
    // в порядке написания, а автором итогового комментария становится
    // автор САМОГО РАННЕГО из выбранных (условность: комментарии могут
    // принадлежать разным пользователям, раз это лента со всех торрентов,
    // а не тред одного - в отличие от commenttable.php, где merge всегда
    // подразумевает одного и того же автора).
    $res = $db->sql_query_prepared("SELECT * FROM comments WHERE id IN ({$ids_str}) ORDER BY dateline ASC");

    $texts       = [];
    $source_tids = [];
    $user_ids    = [];
    $first_row   = null;

    while ($res && ($row = $db->fetch_array($res))) {
        if ($first_row === null) {
            $first_row = $row;
        }
        $texts[]       = $row['text'];
        $source_tids[] = (int)$row['torrent'];
        $user_ids[]    = (int)$row['user'];
    }

    if ($first_row === null) {
        json_exit(['error' => 'Comments not found'], 404);
    }

    $merged_text = implode("\n\n", $texts);

    $db->sql_query_prepared(
        "INSERT INTO comments (`user`,`torrent`,`text`,`dateline`,`editreason`,`editedby`,`editedat`) VALUES (?,?,?,?,?,?,?)",
        [
            (int)$first_row['user'],
            $target_tid,
            $merged_text,
            (int)$first_row['dateline'],
            'Merged from ' . count($ids) . ' comments',
            (int)$CURUSER['id'],
            TIMENOW,
        ]
    );
    $new_comment_id = (int)$db->insert_id();

    // Вложения не дублируем, как в copy_comments - исходные комментарии
    // всё равно удаляются ниже, поэтому просто переносим владение файлами
    // на новый объединённый комментарий.
    $db->sql_query_prepared(
        "UPDATE attachments SET comment_id = ? WHERE comment_id IN ({$ids_str})",
        [$new_comment_id]
    );
    $db->sql_query_prepared(
        "UPDATE comment_files SET comment_id = ?, torrent_id = ? WHERE comment_id IN ({$ids_str})",
        [$new_comment_id, $target_tid]
    );

    $db->sql_query_prepared("DELETE FROM comments WHERE id IN ({$ids_str})");

    // Пересчёт счётчиков для всех затронутых торрентов и пользователей
    foreach (array_unique([...$source_tids, $target_tid]) as $tid) {
        sync_torrent_comment_count($tid);
    }
    foreach (array_unique($user_ids) as $uid) {
        sync_user_comment_count($uid);
    }

    write_log(sprintf(
        'User %s (UID %d) merged %d comment(s) [%s] into new comment #%d on torrent #%d (%s)',
        htmlspecialchars($CURUSER['username']),
        (int)$CURUSER['id'],
        count($ids),
        $ids_str,
        $new_comment_id,
        $target_tid,
        htmlspecialchars($torrent['name'])
    ));

    json_exit(['success' => true, 'merged' => count($ids), 'new_comment_id' => $new_comment_id, 'target_torrent' => $torrent['name']]);
}

// Неизвестный action
json_exit(['error' => 'Unknown action'], 400);

// ---------------------------------------------------------------------------
// HTML страница
// ---------------------------------------------------------------------------
render_page:

// ── KPI-статистика (до stdhead — ничего не выводим) ─────────────────────────
$lc_q = $db->sql_query_prepared(
    'SELECT COUNT(*) AS total,
            SUM(dateline >= ?) AS today,
            SUM(dateline >= ?) AS week,
            COUNT(DISTINCT CASE WHEN dateline >= ? THEN user END) AS authors
     FROM comments',
    [(int)strtotime('today'), TIMENOW - 7 * 86400, TIMENOW - 30 * 86400]
);
$lc_stats = ($lc_q ? $db->fetch_array($lc_q) : null) ?: [];
$lc_kpis  = [
    ['Total comments',       (int)($lc_stats['total']   ?? 0), 'fa-comments',    'primary'],
    ['Today',                (int)($lc_stats['today']   ?? 0), 'fa-calendar-day', 'success'],
    ['Last 7 days',          (int)($lc_stats['week']    ?? 0), 'fa-chart-line',  'info'],
    ['Active authors (30d)', (int)($lc_stats['authors'] ?? 0), 'fa-user-pen',    'warning'],
];

// BBCode-панель: [open, close, icon-html, title]; null = разделитель
$bbcode_buttons = [
    ['[b]', '[/b]', '<i class="fa-solid fa-bold"></i>', 'Bold'],
    ['[i]', '[/i]', '<i class="fa-solid fa-italic"></i>', 'Italic'],
    ['[u]', '[/u]', '<i class="fa-solid fa-underline"></i>', 'Underline'],
    ['[s]', '[/s]', '<i class="fa-solid fa-strikethrough"></i>', 'Strikethrough'],
    null,
    ['[left]', '[/left]', '<i class="fa-solid fa-align-left"></i>', 'Align left'],
    ['[center]', '[/center]', '<i class="fa-solid fa-align-center"></i>', 'Align center'],
    ['[right]', '[/right]', '<i class="fa-solid fa-align-right"></i>', 'Align right'],
    null,
    ['[color=red]', '[/color]', '<i class="fa-solid fa-palette lc-bb-red"></i>', 'Red color'],
    ['[size=18]', '[/size]', '<i class="fa-solid fa-text-height"></i>', 'Font size'],
    null,
    ['[url]', '[/url]', '<i class="fa-solid fa-link"></i>', 'Link'],
    ['[email]', '[/email]', '<i class="fa-solid fa-envelope"></i>', 'E-mail'],
    ['[img]', '[/img]', '<i class="fa-solid fa-image"></i>', 'Image'],
    ['[video]', '[/video]', '<i class="fa-solid fa-film"></i>', 'Video'],
    ['[youtube]', '[/youtube]', '<i class="fa-brands fa-youtube lc-bb-red"></i>', 'YouTube'],
    null,
    ['[quote]', '[/quote]', '<i class="fa-solid fa-quote-right"></i>', 'Quote'],
    ['[code]', '[/code]', '<i class="fa-solid fa-code"></i>', 'Code'],
    ['[php]', '[/php]', '<i class="fa-brands fa-php"></i>', 'PHP code'],
    ['[nfo]', '[/nfo]', '<i class="fa-solid fa-file-lines"></i>', 'NFO'],
    ['[spoiler]', '[/spoiler]', '<i class="fa-solid fa-eye-slash"></i>', 'Spoiler'],
    null,
    ["[list]\n[*]", "\n[/list]", '<i class="fa-solid fa-list-ul"></i>', 'Bulleted list'],
    ["[list=1]\n[*]", "\n[/list]", '<i class="fa-solid fa-list-ol"></i>', 'Numbered list'],
    ['[*]', '', '<i class="fa-solid fa-asterisk"></i>', 'List item'],
];

stdhead('Comments Admin');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/admin/templates/latest-comments.css?v=1.0">

<div class="lc-page">
<div class="container mt-4 mb-5">

    <!-- Заголовок -->
    <div class="lc-card lc-header">
        <span class="lc-icon-sq lg lc-soft-primary"><i class="fa-solid fa-comments"></i></span>
        <div>
            <h1 class="lc-title">Comments Admin</h1>
            <p class="lc-subtitle">Moderate, edit, move, copy and merge comments across all torrents</p>
        </div>
    </div>

    <!-- KPI -->
    <div class="lc-kpis">
        <?php foreach ($lc_kpis as [$label, $value, $icon, $tone]): ?>
        <div class="lc-card lc-kpi">
            <span class="lc-icon-sq lc-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
            <div>
                <div class="lc-kpi-value"><?= number_format($value) ?></div>
                <div class="lc-kpi-label"><?= $label ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Фильтры -->
    <div class="lc-card lc-filters">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="username" class="form-label"><i class="fa-solid fa-user"></i> Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Search by user…">
                </div>
            </div>
            <div class="col-md-3">
                <label for="torrent" class="form-label"><i class="fa-solid fa-magnet"></i> Torrent</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="torrent" name="torrent" placeholder="Search by torrent…">
                </div>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label"><i class="fa-regular fa-calendar"></i> Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label"><i class="fa-regular fa-calendar-check"></i> Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary lc-pill flex-grow-1">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <button type="button" id="resetFilters" class="btn lc-pill lc-btn-soft lc-secondary" title="Reset filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица -->
    <div id="comments-table" class="fade-in">
        <div class="lc-card lc-empty">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading…</span>
            </div>
            <p class="lc-card-sub mt-3 mb-0">Loading comments…</p>
        </div>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade lc-modal" id="moveCommentsModal" tabindex="-1" aria-labelledby="moveCommentsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="lc-icon-sq lc-soft-warning"><i class="fa-solid fa-right-left"></i></span>
                    <div>
                        <h5 class="modal-title" id="moveCommentsTitle">Move Selected Comments</h5>
                        <div class="lc-card-sub">Reassign comments to another torrent</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="targetTorrent" class="form-label">Target Torrent ID</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="targetTorrent" placeholder="Enter target torrent ID">
                </div>
                <div class="lc-note lc-soft-warning">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><strong>Selected:</strong> <span id="moveSelectedCount">0</span> comments.<br>This action cannot be undone.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmMoveBtn" type="button" class="btn btn-warning lc-pill">
                    <i class="fa-solid fa-right-left"></i> Move Comments
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Copy Modal -->
<div class="modal fade lc-modal" id="copyCommentsModal" tabindex="-1" aria-labelledby="copyCommentsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="lc-icon-sq lc-soft-info"><i class="fa-solid fa-copy"></i></span>
                    <div>
                        <h5 class="modal-title" id="copyCommentsTitle">Copy Selected Comments</h5>
                        <div class="lc-card-sub">Duplicate comments with attachments</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="copyTargetTorrent" class="form-label">Target Torrent ID</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="copyTargetTorrent" placeholder="Enter target torrent ID">
                </div>
                <div class="lc-note lc-soft-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><strong>Selected:</strong> <span id="copySelectedCount">0</span> comments.<br>Originals remain intact.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmCopyBtn" type="button" class="btn btn-info lc-pill">
                    <i class="fa-solid fa-copy"></i> Copy Comments
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Merge Into One Modal -->
<div class="modal fade lc-modal" id="mergeIntoOneModal" tabindex="-1" aria-labelledby="mergeIntoOneTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="lc-icon-sq lc-soft-primary"><i class="fa-solid fa-object-group"></i></span>
                    <div>
                        <h5 class="modal-title" id="mergeIntoOneTitle">Merge Into One Comment</h5>
                        <div class="lc-card-sub">Join selected texts chronologically</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="mergeTargetTorrent" class="form-label">Target Torrent ID</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="mergeTargetTorrent" placeholder="Enter target torrent ID">
                </div>
                <div class="lc-note lc-soft-primary">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Selected:</strong> <span id="mergeIntoOneSelectedCount">0</span> comments.<br>
                        Texts are joined in chronological order into a single new comment on the target torrent;
                        the author of the earliest selected comment becomes the author of the merged comment.
                        Originals are deleted. This action cannot be undone.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmMergeIntoOneBtn" type="button" class="btn btn-primary lc-pill">
                    <i class="fa-solid fa-object-group"></i> Merge Comments
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade lc-modal" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="lc-icon-sq lc-soft-primary"><i class="fa-solid fa-pen-to-square"></i></span>
                    <div>
                        <h5 class="modal-title" id="editCommentTitle">Edit Comment</h5>
                        <div class="lc-card-sub">BBCode supported · live preview below</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="lc-bb-toolbar">
                    <?php
                    foreach ($bbcode_buttons as $btn) {
                        if ($btn === null) {
                            echo "<span class=\"lc-bb-sep\"></span>\n";
                            continue;
                        }
                        [$open, $close, $icon, $title] = $btn;
                        // Переносы строк ([list]/[*]) нужно экранировать как
                        // \n именно для JS-строки внутри onclick - буквальный
                        // перенос строки внутри '...' сломал бы синтаксис JS.
                        $open_esc  = htmlspecialchars(str_replace("\n", '\\n', $open),  ENT_QUOTES);
                        $close_esc = htmlspecialchars(str_replace("\n", '\\n', $close), ENT_QUOTES);
                        $title_esc = htmlspecialchars($title, ENT_QUOTES);
                        echo "<button type=\"button\" class=\"lc-bb\" title=\"{$title_esc}\" aria-label=\"{$title_esc}\" onclick=\"wrapBBCode('{$open_esc}','{$close_esc}')\">{$icon}</button>\n";
                    }
                    ?>
                    <span class="lc-bb-sep"></span>
                    <button type="button" class="lc-bb lc-bb-wide" id="torrentPanelToggle" title="Insert torrent">
                        <i class="fa-solid fa-magnet"></i> Torrent
                    </button>
                </div>

                <!-- Встроенная панель вставки торрента - скрыта, пока не нажата кнопка "Torrent".
                     initTorrentTagPanel() (comments-admin.js) уже слушает эти ID сама. -->
                <div id="torrentPanel" class="lc-torrent-panel d-none">
                    <label for="torrentIdInput" class="form-label"><i class="fa-solid fa-magnet"></i> Torrent ID or URL</label>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="numeric" class="form-control" id="torrentIdInput" placeholder="e.g. 17 or paste the torrent link">
                        <button type="button" class="btn btn-primary" id="insertTorrentBtn"><i class="fa-solid fa-plus"></i> Insert</button>
                    </div>
                    <div id="torrentPreview" class="mt-2"></div>
                </div>

                <textarea id="editCommentText" class="form-control lc-editor" rows="7" placeholder="Edit your comment…"></textarea>

                <div class="lc-preview-label"><i class="fa-solid fa-eye"></i> Live Preview</div>
                <div id="bbcodePreview" class="lc-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmEditComment" type="button" class="btn btn-primary lc-pill">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirm Modal -->
<div class="modal fade lc-modal" id="confirmBulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="lc-icon-sq lc-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div>
                        <h5 class="modal-title" id="bulkDeleteTitle">Confirm Deletion</h5>
                        <div class="lc-card-sub">Attachments will be removed too</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="bulkDeleteMessage" class="mb-0">Are you sure you want to delete the selected comments?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="confirmBulkDeleteBtn" type="button" class="btn btn-danger lc-pill">
                    <i class="fa-solid fa-trash-can"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index:1100;"></div>
</div><!-- /.lc-page -->


<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/sweetalert2.min.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/toast.js"></script>
<script>
    window.commentsBaseUrl = <?= json_encode($BASEURL) ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/comments-admin.js?v=1.8"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/latest-comments.js?v=1.0"></script>

<?php stdfoot(); ?>