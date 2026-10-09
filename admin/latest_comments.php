<?php
declare(strict_types=1);

define('IN_ARCHIVE', true);
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('latest_comments');

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
 * Подстановка {1}, {2}… в строку из ланга.
 * $lang->load() превращает {N} в %N$s — поддерживаем оба формата.
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/**
 * htmlspecialchars для строк ланга в HTML/атрибутах.
 */
function lc_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES);
}

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
    global $mybb, $lang;
    if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        json_exit(['error' => $lang->latest_comments['err_csrf']], 403);
    }
}

/**
 * Проверяет что запрос является POST. Завершает с ошибкой если нет.
 */
function require_post(): void
{
    global $lang;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_exit(['error' => $lang->latest_comments['err_method']], 405);
    }
}

/**
 * Декодирует JSON-строку в массив int. Завершает с ошибкой если невалидно.
 *
 * @return int[]
 */
function decode_comment_ids(mixed $raw): array
{
    global $lang;
    if (empty($raw)) {
        json_exit(['error' => $lang->latest_comments['err_no_selection']]);
    }

    $ids = is_array($raw) ? $raw : json_decode((string)$raw, true);

    if (!is_array($ids) || empty($ids)) {
        json_exit(['error' => $lang->latest_comments['err_no_selection']]);
    }

    $ids = array_filter(array_map('intval', $ids));
    if (empty($ids)) {
        json_exit(['error' => $lang->latest_comments['err_no_valid_ids']]);
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
    global $parser, $parser_options, $BASEURL, $db, $dateformat, $timeformat, $lang;

    // Строки ланга, экранированные один раз для heredoc
    $t_open_comment   = lc_h($lang->latest_comments['tip_open_comment']);
    $t_edit_comment   = lc_h($lang->latest_comments['tip_edit_comment']);
    $t_delete_comment = lc_h($lang->latest_comments['tip_delete_comment']);
    $t_deleted_user   = lc_h($lang->latest_comments['lbl_deleted_user']);

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
                       . '<span class="text-body-secondary fst-italic">' . $t_deleted_user . '</span></div>';
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
            $deleted_torrent = lc_h(ags_fmt($lang->latest_comments['lbl_deleted_torrent'], $tid));
            $torrent_html    = "<span class=\"lc-badge lc-soft-danger\"><i class=\"fa-solid fa-triangle-exclamation\"></i> {$deleted_torrent}</span>";
        }

        // Отметка о редактировании
        $edited_html = '';
        $edited_at   = (int)($row['editedat'] ?? 0);
        if ($edited_at > 0) {
            $edited_str  = my_datee($dateformat, $edited_at) . ' ' . my_datee($timeformat, $edited_at);
            $edited_lbl  = lc_h(ags_fmt($lang->latest_comments['lbl_edited'], $edited_str));
            $edited_html = "<div class=\"lc-edited\"><i class=\"fa-solid fa-pen\"></i> {$edited_lbl}</div>";
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
                <a class="lc-id-pill" href="{$comment_link}#pid{$pid}" target="_blank" rel="noopener" title="{$t_open_comment}">
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
                    <button type="button" class="lc-icon-btn lc-edit" onclick="editComment({$pid})" title="{$t_edit_comment}">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="lc-icon-btn lc-delete" onclick="deleteComment({$pid})" title="{$t_delete_comment}">
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

    $t_sec_comments  = lc_h($lang->latest_comments['sec_comments']);
    $t_comments_page = lc_h(ags_fmt($lang->latest_comments['hint_comments_page'], $page, $total_pages));
    $t_found         = lc_h(ags_fmt($lang->latest_comments['lbl_found'], $total_fmt));
    $t_select_page   = lc_h($lang->latest_comments['tip_select_all_page']);
    $t_col_id        = lc_h($lang->latest_comments['col_id']);
    $t_col_user      = lc_h($lang->latest_comments['col_user']);
    $t_col_torrent   = lc_h($lang->latest_comments['col_torrent']);
    $t_col_comment   = lc_h($lang->latest_comments['col_comment']);
    $t_col_date      = lc_h($lang->latest_comments['col_date']);
    $t_col_actions   = lc_h($lang->latest_comments['col_actions']);
    $t_select_all    = lc_h($lang->latest_comments['btn_select_all']);
    $t_move          = lc_h($lang->latest_comments['btn_move']);
    $t_copy          = lc_h($lang->latest_comments['btn_copy']);
    $t_merge         = lc_h($lang->latest_comments['btn_merge']);
    $t_delete_sel    = lc_h($lang->latest_comments['btn_delete_selected']);
    // pager_showing_html — HTML-строка ланга, выводится как есть
    $t_showing       = ags_fmt($lang->latest_comments['pager_showing_html'], $start, $end, $total_fmt);

    return <<<HTML
    <div class="lc-card lc-table-card">
        <div class="lc-card-head">
            <div class="d-flex align-items-center gap-3">
                <span class="lc-icon-sq sm lc-soft-primary"><i class="fa-solid fa-list-ul"></i></span>
                <div>
                    <h5 class="lc-card-title">{$t_sec_comments}</h5>
                    <div class="lc-card-sub">{$t_comments_page}</div>
                </div>
            </div>
            <span class="lc-badge lc-soft-primary"><i class="fa-solid fa-filter"></i> {$t_found}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 lc-table">
                <thead>
                    <tr>
                        <th width="48">
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="selectAll" title="{$t_select_page}">
                            </div>
                        </th>
                        <th width="90"><i class="fa-solid fa-hashtag"></i> {$t_col_id}</th>
                        <th><i class="fa-solid fa-user"></i> {$t_col_user}</th>
                        <th><i class="fa-solid fa-magnet"></i> {$t_col_torrent}</th>
                        <th><i class="fa-solid fa-comment"></i> {$t_col_comment}</th>
                        <th><i class="fa-regular fa-calendar"></i> {$t_col_date}</th>
                        <th width="110" class="text-end"><i class="fa-solid fa-gear"></i> {$t_col_actions}</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>

        <div class="lc-actionbar">
            <button id="selectAllBtn" type="button" class="btn btn-sm lc-pill lc-btn-soft">
                <i class="fa-solid fa-check-double"></i> {$t_select_all}
            </button>
            <div class="lc-actionbar-group">
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-warning" data-bs-toggle="modal" data-bs-target="#moveCommentsModal">
                    <i class="fa-solid fa-right-left"></i> {$t_move}
                </button>
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-info" data-bs-toggle="modal" data-bs-target="#copyCommentsModal">
                    <i class="fa-solid fa-copy"></i> {$t_copy}
                </button>
                <button type="button" class="btn btn-sm lc-pill lc-btn-soft lc-primary" data-bs-toggle="modal" data-bs-target="#mergeIntoOneModal">
                    <i class="fa-solid fa-object-group"></i> {$t_merge}
                </button>
                <button id="bulkDeleteBtn" type="button" class="btn btn-sm lc-pill btn-danger" disabled>
                    <i class="fa-solid fa-trash-can"></i> {$t_delete_sel} (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    <div class="lc-pager">
        <div><i class="fa-solid fa-layer-group"></i> {$t_showing}</div>
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
            <h4 class="lc-card-title">' . lc_h($lang->latest_comments['sec_empty']) . '</h4>
            <p class="lc-card-sub mb-0">' . lc_h($lang->latest_comments['hint_empty']) . '</p>
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
        json_exit(['error' => $lang->latest_comments['err_not_found']], 404);
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
        json_exit(['success' => false, 'error' => $lang->latest_comments['err_not_found']], 404);
    }

    // Валидация — убираем пробелы и проверяем длину содержательного текста
    if (mb_strlen($text) < 3 || mb_strlen(preg_replace('/\s+/u', '', $text)) < 3) {
        json_exit(['success' => false, 'error' => $lang->latest_comments['err_text_short']]);
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
        json_exit(['success' => false, 'error' => $lang->latest_comments['err_not_found']], 404);
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
        json_exit(['error' => $lang->latest_comments['err_invalid_target']]);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => $lang->latest_comments['err_target_not_found']], 404);
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
        json_exit(['error' => $lang->latest_comments['err_invalid_target']]);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => $lang->latest_comments['err_target_not_found']], 404);
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
        json_exit(['error' => $lang->latest_comments['err_merge_min']]);
    }
    if ($target_tid <= 0) {
        json_exit(['error' => $lang->latest_comments['err_invalid_target']]);
    }

    $torrent = validate_torrent_exists($target_tid);
    if (!$torrent) {
        json_exit(['error' => $lang->latest_comments['err_target_not_found']], 404);
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
        json_exit(['error' => $lang->latest_comments['err_comments_not_found']], 404);
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
json_exit(['error' => $lang->latest_comments['err_unknown_action']], 400);

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
    [$lang->latest_comments['kpi_total'],   (int)($lc_stats['total']   ?? 0), 'fa-comments',     'primary'],
    [$lang->latest_comments['kpi_today'],   (int)($lc_stats['today']   ?? 0), 'fa-calendar-day', 'success'],
    [$lang->latest_comments['kpi_week'],    (int)($lc_stats['week']    ?? 0), 'fa-chart-line',   'info'],
    [$lang->latest_comments['kpi_authors'], (int)($lc_stats['authors'] ?? 0), 'fa-user-pen',     'warning'],
];

// BBCode-панель: [open, close, icon-html, title]; null = разделитель
$bbcode_buttons = [
    ['[b]', '[/b]', '<i class="fa-solid fa-bold"></i>', $lang->latest_comments['bb_bold']],
    ['[i]', '[/i]', '<i class="fa-solid fa-italic"></i>', $lang->latest_comments['bb_italic']],
    ['[u]', '[/u]', '<i class="fa-solid fa-underline"></i>', $lang->latest_comments['bb_underline']],
    ['[s]', '[/s]', '<i class="fa-solid fa-strikethrough"></i>', $lang->latest_comments['bb_strike']],
    null,
    ['[left]', '[/left]', '<i class="fa-solid fa-align-left"></i>', $lang->latest_comments['bb_left']],
    ['[center]', '[/center]', '<i class="fa-solid fa-align-center"></i>', $lang->latest_comments['bb_center']],
    ['[right]', '[/right]', '<i class="fa-solid fa-align-right"></i>', $lang->latest_comments['bb_right']],
    null,
    ['[color=red]', '[/color]', '<i class="fa-solid fa-palette lc-bb-red"></i>', $lang->latest_comments['bb_color']],
    ['[size=18]', '[/size]', '<i class="fa-solid fa-text-height"></i>', $lang->latest_comments['bb_size']],
    null,
    ['[url]', '[/url]', '<i class="fa-solid fa-link"></i>', $lang->latest_comments['bb_url']],
    ['[email]', '[/email]', '<i class="fa-solid fa-envelope"></i>', $lang->latest_comments['bb_email']],
    ['[img]', '[/img]', '<i class="fa-solid fa-image"></i>', $lang->latest_comments['bb_img']],
    ['[video]', '[/video]', '<i class="fa-solid fa-film"></i>', $lang->latest_comments['bb_video']],
    ['[youtube]', '[/youtube]', '<i class="fa-brands fa-youtube lc-bb-red"></i>', $lang->latest_comments['bb_youtube']],
    null,
    ['[quote]', '[/quote]', '<i class="fa-solid fa-quote-right"></i>', $lang->latest_comments['bb_quote']],
    ['[code]', '[/code]', '<i class="fa-solid fa-code"></i>', $lang->latest_comments['bb_code']],
    ['[php]', '[/php]', '<i class="fa-brands fa-php"></i>', $lang->latest_comments['bb_php']],
    ['[nfo]', '[/nfo]', '<i class="fa-solid fa-file-lines"></i>', $lang->latest_comments['bb_nfo']],
    ['[spoiler]', '[/spoiler]', '<i class="fa-solid fa-eye-slash"></i>', $lang->latest_comments['bb_spoiler']],
    null,
    ["[list]\n[*]", "\n[/list]", '<i class="fa-solid fa-list-ul"></i>', $lang->latest_comments['bb_list']],
    ["[list=1]\n[*]", "\n[/list]", '<i class="fa-solid fa-list-ol"></i>', $lang->latest_comments['bb_list_num']],
    ['[*]', '', '<i class="fa-solid fa-asterisk"></i>', $lang->latest_comments['bb_list_item']],
];

stdhead($lang->latest_comments['page_title']);

// JS-строки: js_<key> из ланга → AGS_LANG.<key>
$lc_js_lang = [];
foreach ($lang->latest_comments as $lc_key => $lc_val) {
    if (str_starts_with((string)$lc_key, 'js_')) {
        $lc_js_lang[substr((string)$lc_key, 3)] = (string)$lc_val;
    }
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/admin/templates/latest-comments.css?v=1.0">

<div class="lc-page">
<div class="container mt-4 mb-5">

    <!-- Заголовок -->
    <div class="lc-card lc-header">
        <span class="lc-icon-sq lg lc-soft-primary"><i class="fa-solid fa-comments"></i></span>
        <div>
            <h1 class="lc-title"><?= lc_h($lang->latest_comments['page_title']) ?></h1>
            <p class="lc-subtitle"><?= lc_h($lang->latest_comments['page_subtitle']) ?></p>
        </div>
    </div>

    <!-- KPI -->
    <div class="lc-kpis">
        <?php foreach ($lc_kpis as [$label, $value, $icon, $tone]): ?>
        <div class="lc-card lc-kpi">
            <span class="lc-icon-sq lc-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
            <div>
                <div class="lc-kpi-value"><?= number_format($value) ?></div>
                <div class="lc-kpi-label"><?= lc_h($label) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Фильтры -->
    <div class="lc-card lc-filters">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="username" class="form-label"><i class="fa-solid fa-user"></i> <?= lc_h($lang->latest_comments['lbl_username']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="<?= lc_h($lang->latest_comments['ph_username']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label for="torrent" class="form-label"><i class="fa-solid fa-magnet"></i> <?= lc_h($lang->latest_comments['lbl_torrent']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="torrent" name="torrent" placeholder="<?= lc_h($lang->latest_comments['ph_torrent']) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label"><i class="fa-regular fa-calendar"></i> <?= lc_h($lang->latest_comments['lbl_date_from']) ?></label>
                <input type="date" class="form-control" id="date_from" name="date_from">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label"><i class="fa-regular fa-calendar-check"></i> <?= lc_h($lang->latest_comments['lbl_date_to']) ?></label>
                <input type="date" class="form-control" id="date_to" name="date_to">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary lc-pill flex-grow-1">
                    <i class="fa-solid fa-filter"></i> <?= lc_h($lang->latest_comments['btn_filter']) ?>
                </button>
                <button type="button" id="resetFilters" class="btn lc-pill lc-btn-soft lc-secondary" title="<?= lc_h($lang->latest_comments['tip_reset_filters']) ?>">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Таблица -->
    <div id="comments-table" class="fade-in">
        <div class="lc-card lc-empty">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"><?= lc_h($lang->latest_comments['lbl_loading']) ?></span>
            </div>
            <p class="lc-card-sub mt-3 mb-0"><?= lc_h($lang->latest_comments['lbl_loading_comments']) ?></p>
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
                        <h5 class="modal-title" id="moveCommentsTitle"><?= lc_h($lang->latest_comments['sec_move']) ?></h5>
                        <div class="lc-card-sub"><?= lc_h($lang->latest_comments['hint_move']) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lc_h($lang->latest_comments['tip_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <label for="targetTorrent" class="form-label"><?= lc_h($lang->latest_comments['lbl_target_tid']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="targetTorrent" placeholder="<?= lc_h($lang->latest_comments['ph_target_tid']) ?>">
                </div>
                <div class="lc-note lc-soft-warning">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= ags_fmt($lang->latest_comments['note_selected_html'], '<span id="moveSelectedCount">0</span>') ?><br><?= lc_h($lang->latest_comments['note_irreversible']) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal"><?= lc_h($lang->latest_comments['btn_cancel']) ?></button>
                <button id="confirmMoveBtn" type="button" class="btn btn-warning lc-pill">
                    <i class="fa-solid fa-right-left"></i> <?= lc_h($lang->latest_comments['btn_move_confirm']) ?>
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
                        <h5 class="modal-title" id="copyCommentsTitle"><?= lc_h($lang->latest_comments['sec_copy']) ?></h5>
                        <div class="lc-card-sub"><?= lc_h($lang->latest_comments['hint_copy']) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lc_h($lang->latest_comments['tip_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <label for="copyTargetTorrent" class="form-label"><?= lc_h($lang->latest_comments['lbl_target_tid']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="copyTargetTorrent" placeholder="<?= lc_h($lang->latest_comments['ph_target_tid']) ?>">
                </div>
                <div class="lc-note lc-soft-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= ags_fmt($lang->latest_comments['note_selected_html'], '<span id="copySelectedCount">0</span>') ?><br><?= lc_h($lang->latest_comments['note_copy']) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal"><?= lc_h($lang->latest_comments['btn_cancel']) ?></button>
                <button id="confirmCopyBtn" type="button" class="btn btn-info lc-pill">
                    <i class="fa-solid fa-copy"></i> <?= lc_h($lang->latest_comments['btn_copy_confirm']) ?>
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
                        <h5 class="modal-title" id="mergeIntoOneTitle"><?= lc_h($lang->latest_comments['sec_merge']) ?></h5>
                        <div class="lc-card-sub"><?= lc_h($lang->latest_comments['hint_merge']) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lc_h($lang->latest_comments['tip_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <label for="mergeTargetTorrent" class="form-label"><?= lc_h($lang->latest_comments['lbl_target_tid']) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                    <input type="number" class="form-control" id="mergeTargetTorrent" placeholder="<?= lc_h($lang->latest_comments['ph_target_tid']) ?>">
                </div>
                <div class="lc-note lc-soft-primary">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <?= ags_fmt($lang->latest_comments['note_selected_html'], '<span id="mergeIntoOneSelectedCount">0</span>') ?><br>
                        <?= lc_h($lang->latest_comments['note_merge']) ?> <?= lc_h($lang->latest_comments['note_irreversible']) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal"><?= lc_h($lang->latest_comments['btn_cancel']) ?></button>
                <button id="confirmMergeIntoOneBtn" type="button" class="btn btn-primary lc-pill">
                    <i class="fa-solid fa-object-group"></i> <?= lc_h($lang->latest_comments['btn_merge_confirm']) ?>
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
                        <h5 class="modal-title" id="editCommentTitle"><?= lc_h($lang->latest_comments['sec_edit']) ?></h5>
                        <div class="lc-card-sub"><?= lc_h($lang->latest_comments['hint_edit']) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lc_h($lang->latest_comments['tip_close']) ?>"></button>
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
                    <button type="button" class="lc-bb lc-bb-wide" id="torrentPanelToggle" title="<?= lc_h($lang->latest_comments['tip_insert_torrent']) ?>">
                        <i class="fa-solid fa-magnet"></i> <?= lc_h($lang->latest_comments['btn_torrent']) ?>
                    </button>
                </div>

                <!-- Встроенная панель вставки торрента - скрыта, пока не нажата кнопка "Torrent".
                     initTorrentTagPanel() (comments-admin.js) уже слушает эти ID сама. -->
                <div id="torrentPanel" class="lc-torrent-panel d-none">
                    <label for="torrentIdInput" class="form-label"><i class="fa-solid fa-magnet"></i> <?= lc_h($lang->latest_comments['lbl_torrent_id_url']) ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" inputmode="numeric" class="form-control" id="torrentIdInput" placeholder="<?= lc_h($lang->latest_comments['ph_torrent_id']) ?>">
                        <button type="button" class="btn btn-primary" id="insertTorrentBtn"><i class="fa-solid fa-plus"></i> <?= lc_h($lang->latest_comments['btn_insert']) ?></button>
                    </div>
                    <div id="torrentPreview" class="mt-2"></div>
                </div>

                <textarea id="editCommentText" class="form-control lc-editor" rows="7" placeholder="<?= lc_h($lang->latest_comments['ph_edit_comment']) ?>"></textarea>

                <div class="lc-preview-label"><i class="fa-solid fa-eye"></i> <?= lc_h($lang->latest_comments['lbl_live_preview']) ?></div>
                <div id="bbcodePreview" class="lc-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal"><?= lc_h($lang->latest_comments['btn_cancel']) ?></button>
                <button id="confirmEditComment" type="button" class="btn btn-primary lc-pill">
                    <i class="fa-solid fa-floppy-disk"></i> <?= lc_h($lang->latest_comments['btn_save']) ?>
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
                        <h5 class="modal-title" id="bulkDeleteTitle"><?= lc_h($lang->latest_comments['sec_bulk_delete']) ?></h5>
                        <div class="lc-card-sub"><?= lc_h($lang->latest_comments['hint_bulk_delete']) ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lc_h($lang->latest_comments['tip_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <p id="bulkDeleteMessage" class="mb-0"><?= lc_h($lang->latest_comments['lbl_bulk_delete']) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn lc-pill lc-btn-soft lc-secondary" data-bs-dismiss="modal"><?= lc_h($lang->latest_comments['btn_cancel']) ?></button>
                <button id="confirmBulkDeleteBtn" type="button" class="btn btn-danger lc-pill">
                    <i class="fa-solid fa-trash-can"></i> <?= lc_h($lang->latest_comments['btn_yes_delete']) ?>
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
    const AGS_LANG = <?= json_encode($lc_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/comments-admin.js?v=1.10"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/latest-comments.js?v=1.0"></script>

<?php stdfoot(); ?>