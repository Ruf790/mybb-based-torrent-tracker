<?php


declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';

if (!function_exists('escape_like_pattern')) {
    /**
     * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
     * Кавычки экранировать не нужно, это делает сам биндинг.
     */
    function escape_like_pattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}


// ── mk_path_abs22 ─────────────────────────────────────────────────────────────
function mk_path_abs22(string $path, string $base = TSDIR): string
{
    $iswin = str_starts_with(strtoupper(PHP_OS), 'WIN');
    $char1 = my_substr($path, 0, 1);

    if ($char1 !== '/' && !($iswin && ($char1 === '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path)))) {
        $path = $base . $path;
    }

    return $path;
}



// ── get_attachment_icon ───────────────────────────────────────────────────────
function get_attachment_icon(string $ext): string
{
    global $cache, $attachtypes;

    if (!$attachtypes) {
        $attachtypes = $cache->read('attachtypes');
    }

    $ext  = my_strtolower($ext);
    $name = htmlspecialchars_uni($attachtypes[$ext]['name'] ?? $ext);

    if (!empty($attachtypes[$ext]['icon'])) {
        $icon = trim($attachtypes[$ext]['icon']);

        if (str_starts_with($icon, '<')) {
            if (!str_contains($icon, 'title=')) {
                $pos  = strpos($icon, '>');
                $icon = $pos !== false
                    ? substr($icon, 0, $pos) . " title=\"{$name}\">" . substr($icon, $pos + 1)
                    : $icon;
            }
            if (!str_contains($icon, 'font-size:')) {
                if (str_contains($icon, 'style=')) {
                    $icon = str_replace('style="', 'style="font-size:16px; ', $icon);
                } else {
                    $pos  = strpos($icon, '>');
                    $icon = $pos !== false
                        ? substr($icon, 0, $pos) . ' style="font-size:16px;">' . substr($icon, $pos + 1)
                        : $icon;
                }
            }
            return $icon;
        }
    }

    return "<i class=\"fas fa-file\" title=\"{$name}\" style=\"font-size:16px;color:#ccc;\"></i>";
}



// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


// Initialize input parameters
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}

$plugins->run_hooks("admin_forum_attachments_begin");

$uploadspath = TSDIR . '/uploads/';
$uploadspath_abs = mk_path_abs22($uploadspath);
$default_perpage = 20;
// Ограничиваем: perpage=100000 в адресе иначе выгружал бы всю таблицу
$perpage = min(200, max(1, $mybb->get_input('perpage', MyBB::INPUT_INT) ?: $default_perpage));

// Navigation tabs
$sub_tabs = [
    'find_attachments' => [
        'title' => 'Find Attachments',
        'link' => "index.php?act=attachments",
        'description' => 'Using the attachments search system you can search for specific files users have attached to your forums.'
    ],
    'find_orphans' => [
        'title' => 'Find Orphaned Attachments',
        'link' => "index.php?act=attachments&action=orphans",
        'description' => 'Orphaned attachments are attachments which are for some reason missing in the database or the file system.'
    ],
    'stats' => [
        'title' => 'Attachment Statistics',
        'link' => "index.php?act=attachments&action=stats",
        'description' => 'Below are some general statistics for the attachments currently on your forum'
    ],
    'comment_attachments' => [
        'title' => 'Comment Attachments',
        'link' => "index.php?act=attachments&action=comment_attachments",
        'description' => 'View and manage attachments uploaded to torrent comments.'
    ]
];

/**
 * Handle attachment deletion (POST only, CSRF-protected)
 *
 * Раньше POST-ветка удаляла файлы БЕЗ проверки my_post_key (CSRF), а GET-ветка
 * печатала модалку без stdhead() — bootstrap не загружен, модалка не открывалась,
 * а ссылка «Delete» вела обратно на GET той же страницы.
 */
if ($mybb->input['action'] === "delete") {
    $plugins->run_hooks("admin_forum_attachments_delete");

    $return_to = in_array($mybb->get_input('return'), ['comment_attachments'], true)
        ? 'index.php?act=attachments&action=' . $mybb->get_input('return')
        : 'index.php?act=attachments';

    if ($mybb->request_method !== "post") {
        admin_redirect($return_to);
    }
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Security check failed. Please try again.', 'error');
        admin_redirect($return_to);
    }

    $aids = is_array($mybb->input['aids'] ?? null)
        ? array_values(array_filter(array_map('intval', $mybb->input['aids'])))
        : array_filter([$mybb->get_input('aid', MyBB::INPUT_INT)]);

    $cf_ids = is_array($mybb->input['cf_ids'] ?? null)
        ? array_values(array_filter(array_map('intval', $mybb->input['cf_ids'])))
        : [];

    if (empty($aids) && empty($cf_ids)) {
        flash_message('No attachments selected for deletion', 'error');
        admin_redirect($return_to);
    }

    {

        require_once INC_PATH . "/functions_upload.php";

        if (!empty($aids)) {
		$aids_ph = implode(',', array_fill(0, count($aids), '?'));
		$query = $db->sql_query_prepared(
    "SELECT aid, pid, posthash, filename, attachname, thumbnail, comment_id FROM attachments WHERE aid IN ({$aids_ph})",
    $aids
);
while ($query && ($attachment = $db->fetch_array($query))) {
    if ((int)($attachment['comment_id'] ?? 0) > 0) {
        // Комментарий — удаляем только этот файл
        $uploadDir = TSDIR . '/uploads/attachments/';
        delete_uploaded_file($uploadDir . $attachment['attachname']);
        if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
            delete_uploaded_file($uploadDir . $attachment['thumbnail']);
        }
        $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [(int)$attachment['aid']]);
        log_admin_action($attachment['aid'], $attachment['filename']);
    } elseif (!(int)$attachment['pid']) {
        // Форум — черновик
        remove_attachment(0, $attachment['posthash'], (int)$attachment['aid']);
        log_admin_action($attachment['aid'], $attachment['filename']);
    } else {
        // Форум — с постом
        remove_attachment((int)$attachment['pid'], '', (int)$attachment['aid']);
        log_admin_action($attachment['aid'], $attachment['filename'], $attachment['pid']);
    }
}
        }

        // comment_files — отдельное хранилище (.attach-файлы), своя таблица и свои колонки
        if (!empty($cf_ids)) {
            $cf_ph = implode(',', array_fill(0, count($cf_ids), '?'));
            $query = $db->sql_query_prepared("SELECT id, file_name, file_path FROM comment_files WHERE id IN ({$cf_ph})", $cf_ids);
            while ($query && ($file = $db->fetch_array($query))) {
                if (!empty($file['file_path']) && is_file($file['file_path'])) {
                    @unlink($file['file_path']);
                }
                $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [(int)$file['id']]);
                log_admin_action($file['id'], $file['file_name']);
            }
        }



        $plugins->run_hooks("admin_forum_attachments_delete_commit");
        flash_message('Selected attachments have been deleted successfully', 'success');
        admin_redirect($return_to);
    }
}


/**
 * Display attachment statistics
 */
if ($mybb->input['action'] === "stats") {
    $plugins->run_hooks("admin_forum_attachments_stats");

    $query = $db->sql_query_prepared("
        SELECT COUNT(*) AS total_attachments,
               COALESCE(SUM(filesize), 0) AS disk_usage,
               COALESCE(SUM(downloads * filesize), 0) AS bandwidthused,
               COALESCE(SUM(downloads), 0) AS total_downloads,
               COALESCE(SUM(comment_id > 0), 0) AS comment_count
        FROM attachments WHERE visible = '1'
    ");
    $attachment_stats = $query ? $db->fetch_array($query) : null;

    $total_attachments = (int)($attachment_stats['total_attachments'] ?? 0);
    $disk_usage        = (float)($attachment_stats['disk_usage'] ?? 0);
    $bandwidthused     = (float)($attachment_stats['bandwidthused'] ?? 0);
    $total_downloads   = (int)($attachment_stats['total_downloads'] ?? 0);
    $comment_count     = (int)($attachment_stats['comment_count'] ?? 0);
    $average_size      = $total_attachments > 0 ? $disk_usage / $total_attachments : 0;

    render_header('Attachments - Attachment Statistics');
    output_nav_tabs($sub_tabs, 'stats');

    echo '<div class="container mt-3 mb-4 atm">';
    echo atm_hero('fa-chart-pie', 'ic-purple', 'Attachment Statistics', 'Storage, traffic and the heaviest files across forum and comment attachments');

    if ($total_attachments === 0) {
        echo atm_empty('fa-chart-pie', 'No attachments yet', 'Once something is uploaded, statistics will appear here.');
        echo '</div>';
        stdfoot();
        exit;
    }

    echo atm_stats([
        ['fa-paperclip',       'ic-blue',   'Attachments',     ts_nf($total_attachments), ts_nf($comment_count) . ' in comments'],
        ['fa-hard-drive',      'ic-green',  'Disk space',      mksize($disk_usage),       'on the server'],
        ['fa-cloud-arrow-down','ic-teal',   'Bandwidth',       mksize($bandwidthused),    ts_nf($total_downloads) . ' downloads'],
        ['fa-scale-balanced',  'ic-amber',  'Average size',    mksize($average_size),     'per file'],
    ]);

    echo '<div class="row g-3">';
    echo '<div class="col-lg-6">';
    render_top_attachments_section('Most downloaded', 'downloads DESC', 'ic-green', 'fa-trophy', 'downloads');
    echo '</div><div class="col-lg-6">';
    render_top_attachments_section('Largest files', 'filesize DESC', 'ic-red', 'fa-weight-hanging', 'filesize');
    echo '</div><div class="col-12">';
    render_top_users_section();
    echo '</div></div></div>';

    stdfoot();
}


/**
 * Handle orphaned attachments deletion
 */
if ($mybb->input['action'] === "delete_orphans" && $mybb->request_method === "post") {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Security check failed. Please try again.', 'error');
        admin_redirect('index.php?act=attachments&action=orphans');
    }

    $plugins->run_hooks("admin_forum_attachments_delete_orphans");

    $success_count = $error_count = 0;

    // Delete orphaned files
    if (is_array($mybb->input['orphaned_files'] ?? null)) {
        foreach ($mybb->input['orphaned_files'] as $file) {
            $file = str_replace('..', '', $file);
            $path = $uploadspath_abs . "/" . $file;
            $real_path = realpath($path);

            if ($real_path === false || !str_starts_with(str_replace('\\', '/', $real_path), str_replace('\\', '/', realpath(TSDIR)) . '/') || $real_path === realpath(TSDIR . 'install/lock')) {
                $error_count++;
                continue;
            }

            if (!@unlink($uploadspath_abs . "/" . $file)) {
                $error_count++;
            } else {
                $success_count++;
            }
        }
    }

    // Delete orphaned database entries — ветвим по comment_id/pid, как в основном
    // action=delete выше: комментарийные вложения (в т.ч. просроченные черновики)
    // чистим напрямую (файл + thumbnail + строка), форумные — через remove_attachment()
    if (is_array($mybb->input['orphaned_attachments'] ?? null)) {
        $orphaned_aids = array_map('intval', $mybb->input['orphaned_attachments']);
        require_once INC_PATH . "/functions_upload.php";

        $orph_ph = implode(',', array_fill(0, count($orphaned_aids), '?'));
        $query = $db->sql_query_prepared("SELECT aid,pid,posthash,comment_id,attachname,thumbnail FROM attachments WHERE aid IN ({$orph_ph})", $orphaned_aids);
        while ($query && ($attachment = $db->fetch_array($query))) {
            $is_comment_style = (int)($attachment['comment_id'] ?? 0) > 0
                || (!str_contains((string)$attachment['attachname'], '/') && (int)$attachment['pid'] === 0);

            if ($is_comment_style) {
                // Комментарийное вложение ИЛИ его черновик без родителя —
                // плоское хранилище uploads/attachments/, без префикса месяца.
                // Отличаем черновик-форум от черновика-комментария по формату
                // attachname: форумные всегда содержат "/" (префикс месяца).
                $uploadDir = TSDIR . '/uploads/attachments/';
                if (!empty($attachment['attachname'])) {
                    @unlink($uploadDir . $attachment['attachname']);
                }
                if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
                    @unlink($uploadDir . $attachment['thumbnail']);
                }
                $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [(int)$attachment['aid']]);
            } else {
                // Форумное вложение (с постом или черновик с posthash) —
                // remove_attachment() сам корректно резолвит путь с префиксом месяца
                remove_attachment((int)$attachment['pid'], (string)$attachment['posthash'], (int)$attachment['aid']);
            }
            $success_count++;
        }
    }

    // Delete orphaned comment_files entries (включая просроченные черновики) —
    // раньше этот блок отсутствовал, cf_ids[] с формы сканирования тихо
    // игнорировались.
    if (is_array($mybb->input['cf_ids'] ?? null)) {
        $cf_ids = array_map('intval', $mybb->input['cf_ids']);
        $cf_ph  = implode(',', array_fill(0, count($cf_ids), '?'));
        $query  = $db->sql_query_prepared("SELECT id, file_name, file_path FROM comment_files WHERE id IN ({$cf_ph})", $cf_ids);
        while ($query && ($file = $db->fetch_array($query))) {
            if (!empty($file['file_path']) && is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
            $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [(int)$file['id']]);
            log_admin_action($file['id'], $file['file_name']);
            $success_count++;
        }
    }

    $plugins->run_hooks("admin_forum_attachments_delete_orphans_commit");

    // Prepare flash message
    if ($error_count > 0 && $success_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)<br />{$success_count} attachment(s) removed successfully";
        $status = 'error';
    } elseif ($error_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)";
        $status = 'error';
    } else {
        $message = "The selected orphaned attachment(s) have been deleted successfully";
        $status = 'success';
    }

    flash_message($message, $status);
    admin_redirect('index.php?act=attachments');
}

/**
 * Handle orphaned attachments
 */
if ($mybb->input['action'] === "orphans") {
    $plugins->run_hooks("admin_forum_attachments_orphans");
    handle_orphans_scan();
}

/**
 * Comment attachments page
 */
if ($mybb->input['action'] === 'comment_attachments') {
    handle_comment_attachments();
}

/**
 * Main attachments search page
 */
if (!$mybb->input['action']) {
    $plugins->run_hooks("admin_forum_attachments_start");

    if ($mybb->request_method === "post" || $mybb->get_input('results', MyBB::INPUT_INT) === 1) {
        handle_attachments_search();
    } else {
        render_search_form();
    }
}

// ═══════════════════════════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════════════════════════

function atm_hero(string $icon, string $cls, string $title, string $sub, string $right = ''): string
{
    return '<div class="atm-card mb-3"><div class="atm-head">'
         . '<span class="atm-head-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div><h1 class="atm-title">' . $title . '</h1><div class="atm-sub">' . $sub . '</div></div>'
         . ($right !== '' ? '<div class="ms-auto d-flex flex-wrap gap-2">' . $right . '</div>' : '')
         . '</div></div>';
}

/** @param array<array{0:string,1:string,2:string,3:string,4?:string}> $cards */
function atm_stats(array $cards): string
{
    $html = '<div class="row g-3 mb-3">';
    foreach ($cards as $c) {
        $html .= '<div class="col-6 col-md-3"><div class="atm-card atm-stat">'
               . '<span class="atm-stat-icon ' . $c[1] . '"><i class="fa-solid ' . $c[0] . '"></i></span>'
               . '<div style="min-width:0"><div class="atm-stat-label">' . $c[2] . '</div><div class="atm-stat-value">' . $c[3] . '</div>'
               . (!empty($c[4]) ? '<div class="atm-muted">' . $c[4] . '</div>' : '')
               . '</div></div></div>';
    }
    return $html . '</div>';
}

function atm_empty(string $icon, string $title, string $text, string $extra = ''): string
{
    return '<div class="atm-card"><div class="atm-empty"><i class="fa-solid ' . $icon . '"></i>'
         . '<div class="fw-semibold">' . $title . '</div><div class="small">' . $text . '</div>' . $extra . '</div></div>';
}

/** Иконка / превью файла. Раньше fallback делал outerHTML='…addslashes($icon)…' —
 *  двойные кавычки иконки ломали атрибут onerror, и подмена не срабатывала. */
function atm_file_visual(string $filename, string $filetype, ?string $thumb_url): string
{
    $icon = '<span class="atm-ficon">' . get_attachment_icon(get_extension($filename)) . '</span>';
    if ($thumb_url !== null && str_starts_with($filetype, 'image/')) {
        return '<span class="atm-thumb"><img src="' . $thumb_url . '" alt="" loading="lazy" onerror="this.parentNode.hidden=true;this.parentNode.nextElementSibling.hidden=false;"></span>'
             . str_replace('<span class="atm-ficon">', '<span class="atm-ficon" hidden>', $icon);
    }
    return $icon;
}

function atm_file_cell(string $visual, string $name_html, string $meta = ''): string
{
    return '<div class="atm-file">' . $visual . '<div style="min-width:0"><div class="atm-fname">' . $name_html . '</div>'
         . ($meta !== '' ? '<div class="atm-muted">' . $meta . '</div>' : '') . '</div></div>';
}

function atm_mime(string $mime): string
{
    return $mime !== '' ? '<span class="atm-mime">' . htmlspecialchars_uni($mime) . '</span>' : '';
}

/** Прилипающая панель выбора + кнопка удаления (открывает модалку подтверждения) */
function atm_toolbar(string $label, int $total): string
{
    return '<div class="atm-card atm-toolbar mb-3">'
         . '<div class="atm-selinfo"><i class="fa-solid fa-square-check me-1"></i>Selected: <b class="atm-selcount">0</b> · ' . $label . ': ' . ts_nf($total) . '</div>'
         . '<button type="button" class="btn btn-sm btn-danger rounded-pill px-3 atm-del" disabled><i class="fa-solid fa-trash me-1"></i>Delete selected</button>'
         . '</div>';
}

function atm_check_all(string $name): string
{
    return '<div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" data-check-all="' . $name . '" aria-label="Select all"></div>';
}

function atm_check(string $name, string $value): string
{
    return '<div class="form-check form-switch m-0"><input class="form-check-input atm-cb" type="checkbox" role="switch" name="' . $name . '" value="' . $value . '" aria-label="Select"></div>';
}

function atm_user_link(int $uid, string $username, $usergroup = 0): string
{
    global $BASEURL;
    if ($uid <= 0 || $username === '') {
        return '<span class="atm-muted"><i class="fa-solid fa-user-secret me-1"></i>Guest</span>';
    }
    // Ник экранируем до format_name() — раньше он вставлялся как есть
    return '<a href="' . $BASEURL . '/' . get_profile_link($uid) . '" target="_blank" class="text-decoration-none fw-semibold">'
         . format_name(htmlspecialchars_uni($username), (int)$usergroup) . '</a>';
}

/** Где «живёт» вложение: тема форума, комментарий к торренту или черновик */
function atm_location(array $a): string
{
    if (!empty($a['pid']) && !empty($a['tid'])) {
        return '<a href="../' . get_post_link((int)$a['pid']) . '" target="_blank" class="text-decoration-none"><i class="fa-solid fa-comments me-1 text-body-secondary"></i>'
             . htmlspecialchars_uni($a['subject'] ?? 'No subject') . '</a>';
    }
    if (!empty($a['comment_id'])) {
        return '<span class="atm-muted"><i class="fa-solid fa-comment-dots me-1"></i>Comment #' . (int)$a['comment_id'] . '</span>';
    }
    return '<span class="atm-tag t-draft"><i class="fa-solid fa-file-pen"></i>Draft</span>';
}

// ═══════════════════════════════════════════════════════════
// COMMENT ATTACHMENTS
// ═══════════════════════════════════════════════════════════

function handle_comment_attachments(): void {
    global $mybb, $db, $perpage, $BASEURL;

    // Фильтры собираются отдельно под каждую таблицу — колонки называются по-разному
    $filename_val = $mybb->get_input('filename') ? escape_like_pattern($mybb->input['filename']) : '';
    $mimetype_val = $mybb->get_input('mimetype') ? escape_like_pattern($mybb->input['mimetype']) : '';
    $user_id_filter = null;
    if (!empty($mybb->input['username'])) {
        $found_user = get_user_by_username($mybb->input['username']);
        if ($found_user) {
            $user_id_filter = (int)$found_user['id'];
        }
    }

    $att_filter = 'comment_id > 0';
    $cf_filter  = 'comment_id IS NOT NULL';
    $att_params = [];
    $cf_params  = [];

    if ($filename_val !== '') {
        $att_filter .= " AND filename LIKE ?";
        $cf_filter  .= " AND file_name LIKE ?";
        $att_params[] = "%{$filename_val}%";
        $cf_params[]  = "%{$filename_val}%";
    }
    if ($mimetype_val !== '') {
        $att_filter .= " AND filetype LIKE ?";
        $cf_filter  .= " AND file_type LIKE ?";
        $att_params[] = "%{$mimetype_val}%";
        $cf_params[]  = "%{$mimetype_val}%";
    }
    if ($user_id_filter !== null) {
        $att_filter .= " AND uid = ?";
        $cf_filter  .= " AND user_id = ?";
        $att_params[] = $user_id_filter;
        $cf_params[]  = $user_id_filter;
    }
    $union_params = [...$att_params, ...$cf_params];

    // UNION ALL нормализует колонки обеих таблиц под общие имена
    $union_sql = "
        SELECT aid AS id, filename, filesize, filetype, thumbnail, uid,
               comment_id, dateuploaded AS dateuploaded_ts, downloads,
               NULL AS file_url_override, attachname, 'attachments' AS source
        FROM attachments
        WHERE {$att_filter}

        UNION ALL

        SELECT id, file_name AS filename, file_size AS filesize, file_type AS filetype, NULL AS thumbnail, user_id AS uid,
               comment_id, UNIX_TIMESTAMP(uploaded_at) AS dateuploaded_ts, 0 AS downloads,
               file_url AS file_url_override, file_name AS attachname, 'comment_files' AS source
        FROM comment_files
        WHERE {$cf_filter}
    ";

    $query = $db->sql_query_prepared("SELECT COUNT(*) AS num_results FROM ({$union_sql}) x", $union_params);
    $num_results = $query ? (int)$db->fetch_field($query, 'num_results') : 0;

    $stats_query = $db->sql_query_prepared("
        SELECT COUNT(*) AS total_count, SUM(filesize) AS total_size, SUM(downloads) AS total_downloads, AVG(filesize) AS avg_size
        FROM (
            SELECT filesize, downloads FROM attachments WHERE comment_id > 0
            UNION ALL
            SELECT file_size AS filesize, 0 AS downloads FROM comment_files WHERE comment_id IS NOT NULL
        ) s
    ");
    $stats = $stats_query ? $db->fetch_array($stats_query) : null;

    render_header('Attachments - Comment Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'comment_attachments');

    echo '<div class="container mt-3 mb-4 atm">';
    echo atm_hero('fa-comment-dots', 'ic-blue', 'Comment Attachments', 'Files attached to torrent comments — both the <code>attachments</code> and <code>comment_files</code> storage');
    echo atm_stats([
        ['fa-file',        'ic-blue',  'Files',         ts_nf((int)($stats['total_count'] ?? 0))],
        ['fa-hard-drive',  'ic-green', 'Space used',    mksize((float)($stats['total_size'] ?? 0))],
        ['fa-download',    'ic-teal',  'Downloads',     ts_nf((int)($stats['total_downloads'] ?? 0))],
        ['fa-chart-line',  'ic-amber', 'Average size',  mksize((float)($stats['avg_size'] ?? 0))],
    ]);

    $fv = static fn(string $k): string => htmlspecialchars_uni($mybb->input[$k] ?? '');
    $has_filter = $filename_val !== '' || $mimetype_val !== '' || !empty($mybb->input['username']);
    echo '
    <form method="get" action="index.php" class="atm-card p-3 mb-3">
        <input type="hidden" name="act" value="attachments">
        <input type="hidden" name="action" value="comment_attachments">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label"><i class="fa-solid fa-file-signature"></i>File name</label><input type="text" class="form-control" name="filename" value="' . $fv('filename') . '" placeholder="contains…"></div>
            <div class="col-md-3"><label class="form-label"><i class="fa-solid fa-user"></i>Username</label><input type="text" class="form-control" name="username" value="' . $fv('username') . '"></div>
            <div class="col-md-3"><label class="form-label"><i class="fa-solid fa-code"></i>MIME type</label><input type="text" class="form-control" name="mimetype" value="' . $fv('mimetype') . '" placeholder="e.g. image/"></div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill flex-grow-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
                ' . ($has_filter ? '<a href="index.php?act=attachments&amp;action=comment_attachments" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="fa-solid fa-xmark"></i></a>' : '') . '
            </div>
        </div>
    </form>';

    if ($num_results === 0) {
        echo atm_empty('fa-magnifying-glass', 'No comment attachments found', 'Try adjusting the filters.');
        echo '</div>';
        stdfoot();
        exit;
    }

    $page  = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perpage;

    echo '<form action="index.php?act=attachments&amp;action=delete" method="post" class="atm-selectable">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <input type="hidden" name="return" value="comment_attachments">'
        . atm_toolbar('found', $num_results) . '
        <div class="atm-card overflow-hidden"><div class="table-responsive"><table class="table atm-table">
            <thead><tr>
                <th style="width:48px">' . atm_check_all('*') . '</th>
                <th><i class="fa-solid fa-file"></i>File</th>
                <th class="text-center"><i class="fa-solid fa-weight-hanging"></i>Size</th>
                <th class="text-center"><i class="fa-solid fa-database"></i>Storage</th>
                <th><i class="fa-solid fa-user"></i>Uploaded by</th>
                <th><i class="fa-solid fa-magnet"></i>Torrent</th>
                <th class="text-end"><i class="fa-solid fa-clock"></i>Date</th>
            </tr></thead><tbody>';

    $query = $db->sql_query_prepared("
        SELECT x.*, u.username AS user_username, u.id AS user_pk, u.enabled, u.donor, u.warned, u.leechwarn, u.usergroup, u.canupload, u.candownload, u.cancomment,
               c.torrent AS torrent_id, t.name AS torrent_name
        FROM ({$union_sql}) x
        LEFT JOIN users u ON (u.id = x.uid)
        LEFT JOIN comments c ON (c.id = x.comment_id)
        LEFT JOIN torrents t ON (t.id = c.torrent)
        ORDER BY x.dateuploaded_ts DESC
        LIMIT ?, ?
    ", [...$union_params, $start, $perpage]);

    while ($query && ($att = $db->fetch_array($query))) {
        $is_cf   = $att['source'] === 'comment_files';
        $att_url = $is_cf
            ? htmlspecialchars_uni((string)$att['file_url_override'])
            : '../uploads/attachments/' . rawurlencode((string)$att['attachname']);
        $thumb   = $is_cf ? $att_url
                 : ((!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL') ? '../uploads/attachments/' . rawurlencode($att['thumbnail']) : $att_url);

        $visual  = atm_file_visual((string)$att['filename'], (string)$att['filetype'], $thumb);
        $name    = '<a href="' . $att_url . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni((string)$att['filename']) . '</a>';
        $meta    = atm_mime((string)$att['filetype']) . ((int)$att['downloads'] > 0 ? ' <span class="ms-1"><i class="fa-solid fa-download me-1"></i>' . ts_nf((int)$att['downloads']) . '</span>' : '');

        $storage = $is_cf
            ? '<span class="atm-tag t-cf" title="comment_files table"><i class="fa-solid fa-box-archive"></i>.attach</span>'
            : '<span class="atm-tag t-std" title="attachments table"><i class="fa-solid fa-paperclip"></i>standard</span>';

        $user = atm_user_link((int)$att['user_pk'], (string)($att['user_username'] ?? ''), $att['usergroup'] ?? 0)
              . ((int)$att['user_pk'] > 0 ? get_user_icons($att) : '');

        $torrent = ($att['comment_id'] && $att['torrent_id'])
            ? '<a href="../details.php?id=' . (int)$att['torrent_id'] . '#pid' . (int)$att['comment_id'] . '" target="_blank" class="atm-link-trunc"><i class="fa-solid fa-magnet text-danger me-1"></i>'
              . htmlspecialchars_uni($att['torrent_name'] ?? 'Torrent #' . $att['torrent_id']) . '</a>'
            : '<span class="atm-muted">—</span>';

        $date = $att['dateuploaded_ts'] > 0 ? my_datee('relative', (int)$att['dateuploaded_ts']) : 'Unknown';

        echo '<tr>'
           . '<td>' . atm_check($is_cf ? 'cf_ids[]' : 'aids[]', (string)(int)$att['id']) . '</td>'
           . '<td>' . atm_file_cell($visual, $name, $meta) . '</td>'
           . '<td class="text-center"><span class="atm-size">' . mksize((float)$att['filesize']) . '</span></td>'
           . '<td class="text-center">' . $storage . '</td>'
           . '<td>' . $user . '</td>'
           . '<td>' . $torrent . '</td>'
           . '<td class="text-end atm-muted text-nowrap">' . $date . '</td>'
           . '</tr>';
    }

    echo '</tbody></table></div></div></form>';

    if ($num_results > $perpage) {
        $search_url = "index.php?act=attachments&amp;action=comment_attachments";
        foreach (['filename', 'username', 'mimetype'] as $p) {
            if ($mybb->get_input($p)) $search_url .= "&amp;{$p}=" . urlencode($mybb->input[$p]);
        }
        echo '<div class="d-flex justify-content-center mt-3">' . multipage($num_results, $perpage, $page, $search_url . "&amp;page={page}") . '</div>';
    }

    echo '</div>';
    stdfoot();
    exit;
}

// ═══════════════════════════════════════════════════════════
// SEARCH
// ═══════════════════════════════════════════════════════════

function handle_attachments_search(): void {
    global $mybb, $db, $perpage;

    $search_sql = '1=1';

    // Параметры поиска для ссылок пагинации/сортировки
    $url_params = [];
    foreach (['filename', 'mimetype', 'username', 'user_types', 'perpage'] as $param) {
        if ($mybb->get_input($param)) {
            $url_params[] = "{$param}=" . urlencode((string)$mybb->input[$param]);
        }
    }
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        foreach ($mybb->input['forum'] as $fid) {
            $url_params[] = 'forum[]=' . (int)$fid;
        }
    }
    $base_url = 'index.php?act=attachments&amp;results=1' . ($url_params ? '&amp;' . implode('&amp;', $url_params) : '');

    $search_params = [];
    if ($mybb->get_input('filename')) {
        $search_sql .= " AND a.filename LIKE ?";
        $search_params[] = '%' . escape_like_pattern($mybb->input['filename']) . '%';
    }
    if ($mybb->get_input('mimetype')) {
        $search_sql .= " AND a.filetype LIKE ?";
        $search_params[] = '%' . escape_like_pattern($mybb->input['mimetype']) . '%';
    }
    if (!empty($mybb->input['username'])) {
        $user = get_user_by_username($mybb->input['username']);
        if ($user) {
            $search_sql .= " AND a.uid=?";
            $search_params[] = $user['id'];
        } else {
            $search_sql .= " AND p.username LIKE ?";
            $search_params[] = '%' . escape_like_pattern($mybb->input['username']) . '%';
        }
    }
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        $forum_ids = array_values(array_filter(array_map('intval', $mybb->input['forum'])));
        if ($forum_ids) {
            $search_sql .= " AND p.fid IN (" . implode(",", $forum_ids) . ")";
        }
    }
    $user_types = $mybb->get_input('user_types', MyBB::INPUT_INT);
    if ($user_types === 1) {
        $search_sql .= " AND a.uid > 0";
    } elseif ($user_types === -1) {
        $search_sql .= " AND a.uid = 0";
    }

    $query = $db->sql_query_prepared("
        SELECT COUNT(a.aid) AS num_results, COALESCE(SUM(a.filesize), 0) AS total_size
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        WHERE {$search_sql}
    ", $search_params);
    $counts      = $query ? $db->fetch_array($query) : [];
    $num_results = (int)($counts['num_results'] ?? 0);

    if (!$num_results) {
        render_search_form(['No attachments were found with the specified search criteria']);
        return;
    }

    render_header('Attachments - Search Results');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');

    $page  = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perpage;

    $sortby = in_array($mybb->input['sortby'] ?? '', ['filename', 'filesize', 'downloads', 'dateuploaded', 'username'], true) ? $mybb->input['sortby'] : 'filename';
    $sort_field = match ($sortby) {
        'filesize'     => 'a.filesize',
        'downloads'    => 'a.downloads',
        'dateuploaded' => 'a.dateuploaded',
        'username'     => 'u.username',
        default        => 'a.filename',
    };
    $order = ($mybb->input['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    // Кликабельные заголовки колонок: повторный клик меняет направление
    $sort_th = static function (string $field, string $icon, string $label, string $align = '') use ($sortby, $order, $base_url): string {
        $active = $sortby === $field;
        $next   = ($active && $order === 'ASC') ? 'desc' : 'asc';
        $arrow  = $active ? ($order === 'ASC' ? ' <i class="fa-solid fa-arrow-up-short-wide atm-sort-on"></i>' : ' <i class="fa-solid fa-arrow-down-wide-short atm-sort-on"></i>') : '';
        return '<th class="' . $align . '"><a class="atm-sort' . ($active ? ' is-active' : '') . '" href="' . $base_url . '&amp;sortby=' . $field . '&amp;order=' . $next . '"><i class="fa-solid ' . $icon . '"></i>' . $label . $arrow . '</a></th>';
    };

    echo '<div class="container mt-3 mb-4 atm">';
    echo atm_hero('fa-magnifying-glass', 'ic-blue', 'Search Results',
        ts_nf($num_results) . ' attachment(s) · ' . mksize((float)($counts['total_size'] ?? 0)) . ' total',
        '<a href="index.php?act=attachments" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fa-solid fa-sliders me-1"></i>New search</a>');

    echo '<form action="index.php?act=attachments&amp;action=delete" method="post" class="atm-selectable">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
        . atm_toolbar('found', $num_results) . '
        <div class="atm-card overflow-hidden"><div class="table-responsive"><table class="table atm-table">
            <thead><tr>
                <th style="width:48px">' . atm_check_all('aids[]') . '</th>'
                . $sort_th('filename', 'fa-file', 'Attachment')
                . $sort_th('filesize', 'fa-weight-hanging', 'Size', 'text-center')
                . $sort_th('username', 'fa-user', 'Posted by')
                . '<th><i class="fa-solid fa-location-dot"></i>Location</th>'
                . $sort_th('downloads', 'fa-download', 'Downloads', 'text-center')
                . $sort_th('dateuploaded', 'fa-clock', 'Uploaded', 'text-end') . '
            </tr></thead><tbody>';

    $query = $db->sql_query_prepared("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username, u.usergroup
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        WHERE {$search_sql}
        ORDER BY {$sort_field} {$order}
        LIMIT ?, ?
    ", [...$search_params, $start, $perpage]);

    while ($query && ($a = $db->fetch_array($query))) {
        $username = (string)($a['user_username'] ?: $a['username']);
        $visual   = atm_file_visual((string)$a['filename'], (string)$a['filetype'], null);
        $name     = '<a href="../attachment.php?aid=' . (int)$a['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni((string)$a['filename']) . '</a>';

        echo '<tr>'
           . '<td>' . atm_check('aids[]', (string)(int)$a['aid']) . '</td>'
           . '<td>' . atm_file_cell($visual, $name, atm_mime((string)$a['filetype'])) . '</td>'
           . '<td class="text-center"><span class="atm-size">' . mksize((float)$a['filesize']) . '</span></td>'
           . '<td>' . atm_user_link((int)$a['uid'], $username, $a['usergroup'] ?? 0) . '</td>'
           . '<td>' . atm_location($a) . '</td>'
           . '<td class="text-center"><span class="atm-dl"><i class="fa-solid fa-download"></i>' . ts_nf((int)$a['downloads']) . '</span></td>'
           . '<td class="text-end atm-muted text-nowrap">' . ($a['dateuploaded'] > 0 ? my_datee('relative', (int)$a['dateuploaded']) : 'Unknown') . '</td>'
           . '</tr>';
    }

    echo '</tbody></table></div></div></form>';

    if ($num_results > $perpage) {
        $url = $base_url . '&amp;sortby=' . $sortby . '&amp;order=' . strtolower($order);
        echo '<div class="d-flex justify-content-center mt-3">' . multipage($num_results, $perpage, $page, $url . "&amp;page={page}") . '</div>';
    }

    echo '</div>';
    stdfoot();
}

function render_search_form(array $errors = []): void {
    global $mybb, $db, $perpage;

    render_header('Attachments - Find Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');

    echo '<div class="container mt-3 mb-4 atm">';
    echo atm_hero('fa-paperclip', 'ic-blue', 'Find Attachments', 'Search files that users have attached to forum posts and comments');

    if (!empty($errors)) {
        echo '<div class="alert alert-warning d-flex align-items-center gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation"></i>' . implode('<br>', array_map('htmlspecialchars_uni', $errors)) . '</div>';
    }

    $v = static fn(string $k): string => htmlspecialchars((string)($mybb->input[$k] ?? ''));

    $sort_options = ['filename' => 'File name', 'filesize' => 'File size', 'downloads' => 'Downloads', 'dateuploaded' => 'Date uploaded', 'username' => 'Username'];
    $user_types   = ['0' => 'User or guest', '1' => 'Users only', '-1' => 'Guests only'];

    echo '
    <form action="index.php?act=attachments" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="atm-card h-100">
                    <div class="atm-sec-head"><span class="atm-sec-icon ic-blue"><i class="fa-solid fa-filter"></i></span>What to look for</div>
                    <div class="p-3 pt-0">
                        <div class="row g-3">
                            <div class="col-md-6"><label for="filename" class="form-label"><i class="fa-solid fa-file-signature"></i>File name contains</label>
                                <input type="text" name="filename" value="' . $v('filename') . '" class="form-control" id="filename" placeholder="e.g. screenshot"></div>
                            <div class="col-md-6"><label for="mimetype" class="form-label"><i class="fa-solid fa-code"></i>File type contains</label>
                                <input type="text" name="mimetype" value="' . $v('mimetype') . '" class="form-control" id="mimetype" placeholder="e.g. image/ or pdf"></div>
                            <div class="col-md-6"><label for="username" class="form-label"><i class="fa-solid fa-user"></i>Poster username</label>
                                <input type="text" name="username" value="' . $v('username') . '" class="form-control" id="username"></div>
                            <div class="col-md-6"><label for="user_types" class="form-label"><i class="fa-solid fa-user-group"></i>Poster is</label>
                                ' . generate_select_box('user_types', $user_types, $mybb->input['user_types'] ?? '', ['id' => 'user_types', 'class' => 'form-select']) . '</div>
                            <div class="col-12"><label for="forum" class="form-label"><i class="fa-solid fa-comments"></i>In forums</label>
                                ' . generate_forum_select('forum[]', $mybb->input['forum'] ?? '', ['multiple' => true, 'size' => 6, 'id' => 'forum', 'class' => 'form-select']) . '
                                <span class="atm-help"><i class="fa-solid fa-keyboard me-1"></i>Leave empty for all forums · Ctrl-click to select several</span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="atm-card h-100 d-flex flex-column">
                    <div class="atm-sec-head"><span class="atm-sec-icon ic-purple"><i class="fa-solid fa-arrow-down-wide-short"></i></span>Results</div>
                    <div class="p-3 pt-0 flex-grow-1">
                        <div class="mb-3"><label for="sortby" class="form-label"><i class="fa-solid fa-sort"></i>Sort by</label>
                            ' . generate_select_box('sortby', $sort_options, $mybb->input['sortby'] ?? '', ['id' => 'sortby', 'class' => 'form-select']) . '</div>
                        <div class="mb-3"><label for="order" class="form-label"><i class="fa-solid fa-arrow-up-wide-short"></i>Order</label>
                            ' . generate_select_box('order', ['asc' => 'Ascending', 'desc' => 'Descending'], $mybb->input['order'] ?? '', ['id' => 'order', 'class' => 'form-select']) . '</div>
                        <div><label for="perpage" class="form-label"><i class="fa-solid fa-list-ol"></i>Per page</label>
                            <input type="number" name="perpage" value="' . (int)$perpage . '" class="form-control" id="perpage" min="1" max="200"></div>
                    </div>
                    <div class="p-3 pt-0">
                        <button type="submit" class="btn btn-primary rounded-pill w-100 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Find Attachments</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="index.php?act=attachments&amp;action=comment_attachments" class="atm-quick"><i class="fa-solid fa-comment-dots text-primary"></i>Comment attachments</a>
        <a href="index.php?act=attachments&amp;action=orphans" class="atm-quick"><i class="fa-solid fa-broom text-warning"></i>Find orphans</a>
        <a href="index.php?act=attachments&amp;action=stats" class="atm-quick"><i class="fa-solid fa-chart-pie text-success"></i>Statistics</a>
    </div>
    </div>';

    stdfoot();
}

// ═══════════════════════════════════════════════════════════
// STATS SECTIONS
// ═══════════════════════════════════════════════════════════

function render_top_attachments_section(string $title, string $order, string $cls, string $icon, string $metric): void {
    global $db;

    // $order передаётся только из кода (не из запроса) — whitelisting не нужен
    $query = $db->sql_query_prepared("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username, u.usergroup
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        ORDER BY {$order}
        LIMIT 5
    ");

    echo '<div class="atm-card h-100"><div class="atm-sec-head"><span class="atm-sec-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>' . $title . '</div><div class="px-3 pb-3">';

    $rank = 0;
    while ($query && ($a = $db->fetch_array($query))) {
        $rank++;
        $username = (string)($a['user_username'] ?: $a['username']);
        $value = $metric === 'downloads'
            ? '<span class="atm-dl"><i class="fa-solid fa-download"></i>' . ts_nf((int)$a['downloads']) . '</span>'
            : '<span class="atm-size">' . mksize((float)$a['filesize']) . '</span>';

        echo '<div class="atm-rank-row">'
           . '<span class="atm-rank r' . min($rank, 4) . '">' . $rank . '</span>'
           . atm_file_cell(
                atm_file_visual((string)$a['filename'], (string)$a['filetype'], null),
                '<a href="../attachment.php?aid=' . (int)$a['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni((string)$a['filename']) . '</a>',
                atm_user_link((int)$a['uid'], $username, $a['usergroup'] ?? 0) . ' · ' . atm_location($a)
             )
           . '<span class="ms-auto">' . $value . '</span>'
           . '</div>';
    }
    if ($rank === 0) {
        echo '<div class="atm-muted text-center py-3">Nothing here yet.</div>';
    }
    echo '</div></div>';
}

function render_top_users_section(): void {
    global $db;

    $query = $db->sql_query_prepared("
        SELECT a.uid, u.username, u.usergroup, SUM(a.filesize) AS totalsize, COUNT(*) AS files
        FROM attachments a
        LEFT JOIN users u ON (u.id=a.uid)
        GROUP BY a.uid, u.username, u.usergroup
        ORDER BY totalsize DESC
        LIMIT 5
    ");

    $rows = [];
    while ($query && ($r = $db->fetch_array($query))) {
        $rows[] = $r;
    }
    $max = $rows ? max(1.0, (float)$rows[0]['totalsize']) : 1.0;

    echo '<div class="atm-card"><div class="atm-sec-head"><span class="atm-sec-icon ic-amber"><i class="fa-solid fa-users"></i></span>Users using the most disk space</div><div class="px-3 pb-3">';
    foreach ($rows as $i => $u) {
        $pct  = (int)round((float)$u['totalsize'] / $max * 100);
        $name = (string)($u['username'] ?? '');
        echo '<div class="atm-rank-row">'
           . '<span class="atm-rank r' . min($i + 1, 4) . '">' . ($i + 1) . '</span>'
           . '<div class="flex-grow-1" style="min-width:0">'
           .   '<div class="d-flex justify-content-between gap-2">' . atm_user_link((int)$u['uid'], $name, $u['usergroup'] ?? 0)
           .   '<a href="index.php?act=attachments&amp;results=1&amp;username=' . urlencode($name) . '" class="atm-size text-decoration-none" title="Show this user\'s attachments">' . mksize((float)$u['totalsize']) . '</a></div>'
           .   '<div class="atm-bar mt-1"><span style="width:' . $pct . '%"></span></div>'
           .   '<div class="atm-muted mt-1">' . ts_nf((int)$u['files']) . ' file(s)</div>'
           . '</div></div>';
    }
    if (!$rows) {
        echo '<div class="atm-muted text-center py-3">Nothing here yet.</div>';
    }
    echo '</div></div>';
}

// ═══════════════════════════════════════════════════════════
// LAYOUT
// ═══════════════════════════════════════════════════════════

function render_header(string $title): void {
    stdhead($title);

    global $BASEURL;
	// CSS и JS вынесены в отдельные файлы; ?v=filemtime — сброс кеша после правок
    
	echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/admin_attachments.css?ver=2">';

    echo <<<'HTML'
<!-- Подтверждение удаления -->
<div class="modal fade" id="atmConfirm" tabindex="-1" aria-labelledby="atmConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="atmConfirmLabel"><i class="fa-solid fa-trash me-2"></i>Delete attachments</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-start">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0" style="width:44px;height:44px"><i class="fa-solid fa-file-circle-xmark"></i></span>
                    <div>
                        <div class="fw-semibold">Permanently delete <span id="atmConfirmCount">0</span> item(s)?</div>
                        <div class="small text-body-secondary">Files are removed from the disk together with their database records. This cannot be undone.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-3" id="atmConfirmBtn"><i class="fa-solid fa-trash me-1"></i>Delete</button>
            </div>
        </div>
    </div>
</div>
HTML;

    //echo atm_asset_js('/scripts/admin_attachments.js') . "\n";
	
	echo '<script src="' . $BASEURL . '/admin/scripts/admin_attachments.js"></script>';
}

/** URL статического файла с версией по времени изменения (кеш-бастинг) */
function atm_asset_url(string $rel): string
{
    global $BASEURL;
    $mtime = @filemtime(rtrim(TSDIR, '/\\') . $rel);
    return htmlspecialchars_uni($BASEURL . $rel . ($mtime ? '?v=' . $mtime : ''));
}

function atm_asset_css(string $rel): string
{
    return '<link rel="stylesheet" href="' . atm_asset_url($rel) . '">';
}

function atm_asset_js(string $rel): string
{
    return '<script src="' . atm_asset_url($rel) . '" defer></script>';
}


/**
 * Найти физические файлы без записи в БД (по attachname/thumbnail)
 */
function scan_orphaned_files(string $commentUploadDir, string $forumUploadsBase, $db): array
{
    $known = [];
    $q = $db->sql_query_prepared("SELECT attachname, thumbnail FROM attachments");
    while ($q && ($row = $db->fetch_array($q))) {
        if (!empty($row['attachname'])) {
            $known[$row['attachname']] = true;
        }
        if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
            $known[$row['thumbnail']] = true;
        }
    }

    // comment_files хранит ПОЛНЫЙ путь в file_path (не относительно uploads/) —
    // нормализуем слэши (в БД могут быть и / и \) для надёжного сравнения
    $knownCommentFilePaths = [];
    $qcf = $db->sql_query_prepared("SELECT file_path FROM comment_files WHERE file_path != ''");
    while ($qcf && ($row = $db->fetch_array($qcf))) {
        $knownCommentFilePaths[str_replace('\\', '/', $row['file_path'])] = true;
    }

    $orphans = [];

    // Комментарийные вложения — общая папка uploads/attachments/
    if (is_dir($commentUploadDir)) {
        foreach (scandir($commentUploadDir) as $file) {
            if ($file === '.' || $file === '..' || is_dir($commentUploadDir . $file)) {
                continue;
            }
            if (!isset($known[$file])) {
                // Префикс папки — тот же формат "папка/файл", что и у форумных ниже,
                // чтобы обработчик удаления резолвил путь от uploads/ одинаково для всех
                $orphans[] = ['name' => 'attachments/' . $file, 'size' => @filesize($commentUploadDir . $file) ?: 0];
            }
        }
    }

    // Форумные вложения — папки по месяцу загрузки: uploads/YYYYMM/
    if (is_dir($forumUploadsBase)) {
        foreach (scandir($forumUploadsBase) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $forumUploadsBase . $entry . '/';

            // Файлы, лежащие ПРЯМО в корне uploads/ (не в подпапке) — это владения
            // comment_files, у которой file_path хранит абсолютный путь целиком
            if (!is_dir($entryPath)) {
                $normalized = str_replace('\\', '/', $forumUploadsBase . $entry);
                if (!isset($knownCommentFilePaths[$normalized])) {
                    $orphans[] = ['name' => $entry, 'size' => @filesize($entryPath) ?: 0];
                }
                continue;
            }

            if (!preg_match('/^\d{6}$/', $entry)) {
                continue; // не YYYYMM-папка (например, "attachments" сюда тоже не попадёт)
            }

            foreach (scandir($entryPath) as $file) {
                if ($file === '.' || $file === '..' || is_dir($entryPath . $file)) {
                    continue;
                }
                // attachname хранит путь С префиксом месяца ("202607/файл") — сверяем
                // по такому же полному ключу, а не по голому имени файла
                $relative = $entry . '/' . $file;
                if (!isset($known[$relative])) {
                    $orphans[] = ['name' => $relative, 'size' => @filesize($entryPath . $file) ?: 0];
                }
            }
        }
    }

    return $orphans;
}

/**
 * Найти записи в attachments, у которых родитель (пост/комментарий) удалён,
 * либо физический файл на диске отсутствует. LEFT JOIN — один запрос на
 * категорию, без N+1.
 */
function scan_orphaned_db_rows($db, string $uploadDir): array
{
    $orphans = [];

    // Форумные вложения на несуществующий пост
    $q = $db->sql_query_prepared("
        SELECT a.aid, a.filename, a.filesize, a.attachname, a.thumbnail, a.pid, a.comment_id
        FROM attachments a
        LEFT JOIN posts p ON p.pid = a.pid
        WHERE a.pid != 0 AND a.comment_id = 0 AND p.pid IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_post';
        $orphans[] = $row;
    }

    // Комментарийные вложения на несуществующий комментарий
    $q = $db->sql_query_prepared("
        SELECT a.aid, a.filename, a.filesize, a.attachname, a.thumbnail, a.pid, a.comment_id
        FROM attachments a
        LEFT JOIN comments c ON c.id = a.comment_id
        WHERE a.comment_id != 0 AND c.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_comment';
        $orphans[] = $row;
    }

    // Записи, чей файл физически отсутствует на диске.
    // Форумные вложения (comment_id = 0): attachname УЖЕ хранит относительный путь
    // с префиксом месяца (например "202607/post_1_....attach" — так его формирует
    // upload_attachment() в functions_upload.php), поэтому просто uploads/ + attachname.
    // Комментарийные (comment_id != 0) — общая плоская uploads/attachments/, без префикса.
    $forumUploadsBase = TSDIR . '/uploads/';
    $q = $db->sql_query_prepared("SELECT aid, filename, filesize, attachname, thumbnail, pid, comment_id, dateuploaded FROM attachments WHERE attachname != ''");
    while ($q && ($row = $db->fetch_array($q))) {
        if ((int)$row['comment_id'] > 0) {
            $expectedPath = $uploadDir . $row['attachname'];
        } else {
            $expectedPath = $forumUploadsBase . $row['attachname'];
        }

        if (!is_file($expectedPath)) {
            $row['reason'] = 'missing_file';
            $orphans[] = $row;
        }
    }

    return $orphans;
}

/**
 * Найти "забытые" черновики — залиты, но так и не привязаны ни к посту,
 * ни к комментарию дольше $cutoffDays дней (пользователь начал загрузку
 * и передумал/закрыл вкладку).
 */
function scan_stale_draft_attachments($db, int $cutoffDays = 7): array
{
    $cutoff = TIMENOW - ($cutoffDays * 86400);
    $q = $db->sql_query_prepared("
        SELECT aid, filename, filesize, attachname, thumbnail, dateuploaded
        FROM attachments
        WHERE pid = 0 AND comment_id = 0 AND dateuploaded < ?
        ORDER BY dateuploaded ASC
    ", [$cutoff]);
    $rows = [];
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'stale_draft';
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Найти черновики comment_files - файлы, загруженные через редактор
 * (upload_image.php), но так и не привязанные ни к чему (юзер закрыл
 * вкладку/передумал до отправки формы). В отличие от attachments, тут все
 * FK-колонки nullable и "не привязано" значит NULL, а не 0.
 */
function scan_stale_draft_comment_files($db, int $cutoffDays = 7): array
{
    $cutoff = date('Y-m-d H:i:s', TIMENOW - ($cutoffDays * 86400));
    $q = $db->sql_query_prepared("
        SELECT id, file_name, file_size, file_path, uploaded_at
        FROM comment_files
        WHERE comment_id IS NULL AND news_id IS NULL AND torrent_id IS NULL
          AND post_id IS NULL AND messages_id IS NULL
          AND uploaded_at < ?
        ORDER BY uploaded_at ASC
    ", [$cutoff]);
    $rows = [];
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'stale_draft';
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Найти "сирот" в comment_files — отдельном хранилище (.attach-файлы) со
 * своими FK-колонками (comment_id/post_id/torrent_id/news_id/messages_id).
 * Проверяем родителя только для трёх известных таблиц (comments/posts/torrents) —
 * news_id и messages_id пропущены, не знаем точно, на какие таблицы они ссылаются.
 * Плюс отдельно — записи, чей файл физически отсутствует на диске (это не
 * требует знания родительской таблицы, просто проверяем file_path напрямую).
 */
function scan_orphaned_comment_files($db): array
{
    $orphans = [];

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        WHERE cf.comment_id IS NOT NULL AND c.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_comment';
        $orphans[] = $row;
    }

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN posts p ON p.pid = cf.post_id
        WHERE cf.post_id IS NOT NULL AND p.pid IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_post';
        $orphans[] = $row;
    }

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        WHERE cf.torrent_id IS NOT NULL AND t.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_torrent';
        $orphans[] = $row;
    }

    // Файл физически отсутствует на диске — не зависит от типа родителя
    $q = $db->sql_query_prepared("SELECT id, file_name, file_size, file_path, comment_id, post_id, torrent_id, news_id, messages_id FROM comment_files WHERE file_path != ''");
    while ($q && ($row = $db->fetch_array($q))) {
        if (!is_file($row['file_path'])) {
            $row['reason'] = 'missing_file';
            $orphans[] = $row;
        }
    }

    return $orphans;
}


/**
 * Единая страница результатов сканирования сирот
 */
function handle_orphans_scan(): void {
    global $mybb, $db;

    render_header('Orphaned Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_orphans');

    $uploadDir = TSDIR . '/uploads/attachments/';
    $staleDays = max(1, $mybb->get_input('stale_days', MyBB::INPUT_INT) ?: 7);

    $orphanedFiles = scan_orphaned_files($uploadDir, TSDIR . '/uploads/', $db);
    $orphanedRows  = scan_orphaned_db_rows($db, $uploadDir);
    $staleDrafts   = scan_stale_draft_attachments($db, $staleDays);
    $orphanedCommentFiles = [...scan_orphaned_comment_files($db), ...scan_stale_draft_comment_files($db, $staleDays)];

    $dbRows     = [...$orphanedRows, ...$staleDrafts];
    $totalFound = count($orphanedFiles) + count($dbRows) + count($orphanedCommentFiles);
    $sumSize    = static fn(array $rows, string $k): float => array_sum(array_map(fn($r) => (float)($r[$k] ?? 0), $rows));
    $reclaim    = $sumSize($orphanedFiles, 'size') + $sumSize($dbRows, 'filesize') + $sumSize($orphanedCommentFiles, 'file_size');

    $days_form = '<form method="get" action="index.php" class="d-flex align-items-center gap-2">
        <input type="hidden" name="act" value="attachments"><input type="hidden" name="action" value="orphans">
        <label class="atm-muted text-nowrap" for="stale_days"><i class="fa-solid fa-hourglass-half me-1"></i>Drafts older than</label>
        <div class="input-group input-group-sm" style="width:130px"><input type="number" min="1" max="365" class="form-control" id="stale_days" name="stale_days" value="' . $staleDays . '"><span class="input-group-text">days</span></div>
        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="fa-solid fa-rotate me-1"></i>Rescan</button>
    </form>';

    echo '<div class="container mt-3 mb-4 atm">';
    echo atm_hero('fa-broom', 'ic-amber', 'Orphaned Attachments', 'Files and records that lost their counterpart — safe candidates for cleanup', $days_form);

    echo atm_stats([
        ['fa-file-circle-question', 'ic-slate',  'Files w/o record',  ts_nf(count($orphanedFiles))],
        ['fa-database',             'ic-red',    'Broken records',    ts_nf(count($orphanedRows))],
        ['fa-box-archive',          'ic-purple', 'comment_files',     ts_nf(count($orphanedCommentFiles))],
        ['fa-hard-drive',           'ic-green',  'Can be freed',      mksize($reclaim), ts_nf(count($staleDrafts)) . ' stale draft(s)'],
    ]);

    if ($totalFound === 0) {
        echo atm_empty('fa-circle-check', 'Everything is in sync', 'Files on disk, database records and drafts all match.',
            '<a href="index.php?act=attachments" class="btn btn-sm btn-primary rounded-pill px-3 mt-3"><i class="fa-solid fa-arrow-left me-1"></i>Back to attachments</a>');
        echo '</div>';
        stdfoot();
        return;
    }

    $reasons = [
        'missing_post'    => ['fa-comments',     'Post deleted',    't-red'],
        'missing_comment' => ['fa-comment-slash','Comment deleted', 't-red'],
        'missing_torrent' => ['fa-magnet',       'Torrent deleted', 't-red'],
        'missing_file'    => ['fa-file-circle-exclamation', 'File missing', 't-amber'],
        'stale_draft'     => ['fa-file-pen',     'Stale draft',     't-draft'],
    ];
    $reason = static function (string $r) use ($reasons): string {
        [$ic, $lbl, $cls] = $reasons[$r] ?? ['fa-question', $r, 't-draft'];
        return '<span class="atm-tag ' . $cls . '"><i class="fa-solid ' . $ic . '"></i>' . htmlspecialchars_uni($lbl) . '</span>';
    };

    $section = static function (string $icon, string $cls, string $title, string $name, array $rows, callable $row_html, string $empty): string {
        $html = '<div class="atm-card overflow-hidden mb-3">'
              . '<div class="atm-sec-head"><span class="atm-sec-icon ' . $cls . '"><i class="fa-solid ' . $icon . '"></i></span>' . $title
              . '<span class="atm-count atm-tag t-draft">' . count($rows) . '</span></div>';
        if (!$rows) {
            return $html . '<div class="atm-section-empty border-top"><i class="fa-solid fa-circle-check text-success me-1"></i>' . $empty . '</div></div>';
        }
        $html .= '<div class="table-responsive"><table class="table atm-table"><thead><tr>'
               . '<th style="width:48px">' . atm_check_all($name) . '</th>'
               . '<th><i class="fa-solid fa-file"></i>File</th><th class="text-center"><i class="fa-solid fa-weight-hanging"></i>Size</th><th class="text-end"><i class="fa-solid fa-circle-info"></i>Reason</th>'
               . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= $row_html($r);
        }
        return $html . '</tbody></table></div></div>';
    };

    echo '<form action="index.php?act=attachments&amp;action=delete_orphans" method="post" class="atm-selectable">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">'
        . atm_toolbar('orphans found', $totalFound);

    echo $section('fa-database', 'ic-red', 'Attachment records', 'orphaned_attachments[]', $dbRows,
        fn(array $r): string => '<tr><td>' . atm_check('orphaned_attachments[]', (string)(int)$r['aid']) . '</td>'
            . '<td>' . atm_file_cell(atm_file_visual((string)$r['filename'], '', null), htmlspecialchars_uni((string)$r['filename']), 'ID ' . (int)$r['aid']) . '</td>'
            . '<td class="text-center"><span class="atm-size">' . mksize((float)$r['filesize']) . '</span></td>'
            . '<td class="text-end">' . $reason((string)$r['reason']) . '</td></tr>',
        'No broken attachment records.');

    echo $section('fa-box-archive', 'ic-purple', 'comment_files records', 'cf_ids[]', $orphanedCommentFiles,
        fn(array $r): string => '<tr><td>' . atm_check('cf_ids[]', (string)(int)$r['id']) . '</td>'
            . '<td>' . atm_file_cell(atm_file_visual((string)$r['file_name'], '', null), htmlspecialchars_uni((string)$r['file_name']), 'ID ' . (int)$r['id']) . '</td>'
            . '<td class="text-center"><span class="atm-size">' . mksize((float)$r['file_size']) . '</span></td>'
            . '<td class="text-end">' . $reason((string)$r['reason']) . '</td></tr>',
        'No broken comment_files records.');

    echo $section('fa-file-circle-question', 'ic-slate', 'Files on disk without a record', 'orphaned_files[]', $orphanedFiles,
        fn(array $f): string => '<tr><td>' . atm_check('orphaned_files[]', htmlspecialchars_uni((string)$f['name'])) . '</td>'
            . '<td>' . atm_file_cell(atm_file_visual((string)$f['name'], '', null), '<span class="font-monospace">' . htmlspecialchars_uni((string)$f['name']) . '</span>') . '</td>'
            . '<td class="text-center"><span class="atm-size">' . mksize((float)$f['size']) . '</span></td>'
            . '<td class="text-end"><span class="atm-tag t-amber"><i class="fa-solid fa-link-slash"></i>No DB record</span></td></tr>',
        'No orphaned files on disk.');

    echo '</form></div>';
    stdfoot();
}