<?php

declare(strict_types=1);


require_once(INC_PATH . '/class_parser.php');
require_once(INC_PATH . '/functions_multipage.php');



if (!defined('STAFF_PANEL')) 
{
    exit('<div class="alert alert-danger">Error! Direct initialization of this file is not allowed.</div>');
}

// admin/index.php already required global.php — load the page lang right away
$lang->load('log');

if (empty($CURUSER['id']) || !is_mod($usergroups)) 
{
    http_response_code(403);
    exit('<div class="alert alert-danger">' . htmlspecialchars($lang->log['err_no_permission']) . '</div>');
}

$parser = new postParser;
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];





// ── Lang helpers ──
if (!function_exists('ags_fmt'))
{
    /**
     * {1}, {2}... placeholders (also %1$s — $lang->load() converts {n} into %n$s).
     */
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
 * English copy of this page's lang strings.
 * Badge detection matches English needles, so admin-log text and module labels
 * are also built in English (for matching only) to keep badges identical in any language.
 */
function ags_log_en(): array
{
    global $lang;
    static $en = null;

    if ($en !== null) {
        return $en;
    }

    $en   = [];
    $file = INC_PATH . '/languages/english/log.lang.php';
    if (is_file($file)) {
        $language = [];
        include $file;
        $en = (isset($language['log']) && is_array($language['log'])) ? $language['log'] : [];
    }
    if (!$en) {
        $en = (array)$lang->log; // fallback: current language
    }
    return $en;
}

// Admin log descriptions: admin_log_* keys in languages/<lang>/log.lang.php
$admin_log_lang    = $lang->log;  // display
$admin_log_lang_en = ags_log_en(); // badge matching

// HTML-escaped copy of the page strings for markup output
$L = array_map(static fn($v) => is_string($v) ? htmlspecialchars($v) : $v, $lang->log);



function format_admin_log($logitem, $admin_log_lang)
{
    $module_raw = str_replace('/', '-', $logitem['module']);
    $module_path = explode('-', $module_raw);
    $module = $module_path[0];
    $action = isset($module_path[1]) ? $module_path[1] : null;

    $lang_string = 'admin_log_' . $module . '_' . $action . '_' . $logitem['action'];

    // Специальные случаи — как в оригинале
    switch ($lang_string) {
        case 'admin_log_forum_management_':
            $lang_string .= $logitem['data'][0];
            if ($logitem['data'][0] == 'orders' && !empty($logitem['data'][1])) {
                $lang_string .= '_sub';
            }
            break;
        
		case 'admin_log_user_banning_':
		case 'admin_log_user_banning_add':
    if (empty($logitem['data'][2]) || $logitem['data'][2] == 0) {
        $lang_string = 'admin_log_user_banning_add_permanent';
    } else {
        // Конвертируем timestamp в дату как в оригинале MyBB
        $logitem['data'][2] = function_exists('my_date') 
            ? my_date('d-m-Y', (int)$logitem['data'][2])
            : date('d-m-Y', (int)$logitem['data'][2]);
        $lang_string = 'admin_log_user_banning_add_temporary';
    }
    break;
	
	
	// == CONFIG ==
		case 'admin_log_config_banning_add': // Banning IP/Username/Email
		case 'admin_log_config_banning_delete': // Removing banned IP/username/emails
			switch($logitem['data'][2])
			{
				case 1:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_ip';
					break;
				case 2:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_username';
					break;
				case 3:
					$lang_string = 'admin_log_config_banning_'.$logitem['action'].'_email';
					break;
			}
			break;
	
			
			
        case 'admin_log_config_plugins_activate':
            if (!empty($logitem['data'][1])) $lang_string .= '_install';
            break;
        case 'admin_log_config_plugins_deactivate':
            if (!empty($logitem['data'][1])) $lang_string .= '_uninstall';
            break;
        case 'admin_log_style_templates_edit_template':
        case 'admin_log_style_templates_delete_template':
            if (isset($logitem['data'][2]) && $logitem['data'][2] == -1) {
                $lang_string .= '_global';
            }
            break;
        case 'admin_log_user_users_inline_banned':
            if (empty($logitem['data'][1])) {
                $lang_string = 'admin_log_user_users_inline_banned_perm';
            } else {
                $lang_string = 'admin_log_user_users_inline_banned_temp';
            }
            break;
    }

    if (isset($admin_log_lang[$lang_string])) {
        // Подставляем {1}, {2}, {3}... из data
        $string = $admin_log_lang[$lang_string];
        foreach ($logitem['data'] as $k => $v) {
            $string = str_replace('{' . ($k + 1) . '}', htmlspecialchars((string)$v), $string);
            $string = str_replace('%' . ($k + 1) . '$s', htmlspecialchars((string)$v), $string); // $lang->load() format
        }
        // Убираем незаполненные плейсхолдеры ({n} и %n$s)
        $string = preg_replace('/\#?(?:\{\d+\}|%\d+\$s)/', '', $string);
    } else {
        // Fallback — показываем module/action + данные
        $string = htmlspecialchars($module_raw . ' → ' . $logitem['action']);
        if (!empty($logitem['data'])) {
            $parts = [];
            foreach ($logitem['data'] as $k => $v) {
                if (is_scalar($v) && $v !== '') {
                    $parts[] = htmlspecialchars((string)$v);
                }
            }
            if ($parts) {
                $string .= ' (' . implode(', ', $parts) . ')';
            }
        }
    }

    return $string;
}

























$searchstr = isset($_GET['query']) ? trim((string)$_GET['query']) : '';
$event_filter = isset($_GET['event_filter']) ? trim((string)$_GET['event_filter']) : 'all';
$date_filter = isset($_GET['date_filter']) ? trim((string)$_GET['date_filter']) : '';
$log_type = isset($_GET['log_type']) ? trim((string)$_GET['log_type']) : 'both';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;

$filter_params = [
    'query' => $searchstr,
    'event_filter' => $event_filter,
    'date_filter' => $date_filter,
    'log_type' => $log_type
];

// Everything is in sitelog now: category 'admin' / 'moderator' = admin / moderator actions

$where  = [];
$params = [];

$where[] = match ($log_type) {
    // write_log(..., 'admin') rows (cleartable …) have no module: they are site events
    'site'      => "NOT (s.category = 'moderator' OR (s.category = 'admin' AND s.module <> ''))",
    'moderator' => "s.category = 'moderator'",
    'admin'     => "s.category = 'admin'",
    default     => '1=1',
};

if ($searchstr !== '') {
    $search_like = "%{$searchstr}%";
    $where[] = "(s.txt LIKE ? OR s.module LIKE ? OR s.data LIKE ?)";
    array_push($params, $search_like, $search_like, $search_like);
}

if ($event_filter !== 'all') {
    if ($event_filter === 'Screenshot') {
        $where[] = "(s.txt LIKE '%Screenshot uploaded:%' OR s.txt LIKE '%Screenshot deleted:%' OR s.txt LIKE '%Screenshot updated:%' OR s.txt LIKE '%Screenshot error%' OR s.data LIKE '%Screenshot%')";
    } elseif ($event_filter === 'Delete Comment') {
        $where[] = "(s.txt LIKE '%Comment Delete%' OR s.txt LIKE '%deleted a comment%' OR s.txt LIKE '%deleted comments%')";
    } elseif ($event_filter === 'Torrent Upload') {
        $where[] = "(s.txt LIKE '%has been uploaded%' OR s.txt LIKE '%torrent uploaded%')";
    } else {
        $where[] = "(s.txt LIKE ? OR s.module LIKE ?)";
        array_push($params, "%{$event_filter}%", "%{$event_filter}%");
    }
}

if ($date_filter !== '') {
    // index-friendly range instead of DATE(FROM_UNIXTIME(...))
    $day0 = strtotime($date_filter . ' 00:00:00');
    if ($day0 !== false) {
        $where[] = "s.added >= ? AND s.added < ?";
        array_push($params, $day0, $day0 + 86400);
    }
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$result = $db->sql_query_prepared("SELECT COUNT(*) AS total FROM sitelog s $where_sql", $params);
if (!$result) {
    die(ags_fmt($lang->log['err_count_query'], (string)$db->error()));
}
$row = $db->fetch_array($result);
$total_count = (int)$row['total'];

// Пагинация
$perpage = 50;
$pages = $total_count > 0 ? (int)ceil($total_count / $perpage) : 1;
if ($page > $pages && $pages > 0) {
    $page = $pages;
}
$start = max(0, ($page - 1) * $perpage);

$res = $db->sql_query_prepared(
    "SELECT s.id, s.added AS timestamp, s.txt AS content,
            CASE WHEN s.category = 'moderator' THEN 'moderator'
                 WHEN s.category = 'admin' AND s.module <> '' THEN 'admin'
                 ELSE 'site' END AS log_type,
            s.category, s.uid, u.username, u.usergroup,
            s.fid, s.tid, s.pid, s.ipaddress, s.data, s.module
       FROM sitelog s
       LEFT JOIN users u ON (s.uid = u.id)
     $where_sql
     ORDER BY s.added DESC, s.id DESC
     LIMIT ?, ?",
    array_merge($params, [$start, $perpage])
);
if (!$res) {
    die(ags_fmt($lang->log['err_main_query'], (string)$db->error()));
}






// СОБИРАЕМ ВСЕ ДАННЫЕ ДЛЯ ПРЕДЗАГРУЗКИ
$logs = [];
while ($arr = $db->fetch_array($res)) 
{
    $logs[] = $arr;
}

$user_ids_from_data = [];
$thread_ids = [];
$forum_ids = [];
$post_ids = [];
$announcement_ids = [];

foreach ($logs as $arr) {
    if ($arr['log_type'] === 'moderator' || $arr['log_type'] === 'admin') {
        if ($arr['tid']) $thread_ids[$arr['tid']] = true;
        if ($arr['fid']) $forum_ids[$arr['fid']] = true;
        if ($arr['pid']) $post_ids[$arr['pid']] = true;

        $data = my_unserialize((string)($arr['data'] ?? ''));
        if (!empty($data['uid']) && empty($data['username'])) {
            $user_ids_from_data[$data['uid']] = true;
        }
        if (!empty($data['aid'])) {
            $announcement_ids[$data['aid']] = true;
        }
    }

    // Site логи — грузим юзера по uid
    if ($arr['log_type'] === 'site' && !empty($arr['uid'])) {
        $user_ids_from_data[$arr['uid']] = true;
    }
}

// ПРЕДЗАГРУЗКА ВСЕХ ДАННЫХ
$users_from_data = [];
$threads_data = [];
$forums_data = [];
$posts_data = [];
$announcements_data = [];

if (!empty($user_ids_from_data)) {
    $user_ids_str = implode(',', array_map('intval', array_keys($user_ids_from_data)));
    $result = $db->sql_query_prepared("SELECT id, username, usergroup FROM users WHERE id IN ($user_ids_str)");
    while ($result && ($row = $db->fetch_array($result))) {
        $users_from_data[$row['id']] = $row;
    }
}

if (!empty($thread_ids)) {
    $thread_ids_str = implode(',', array_map('intval', array_keys($thread_ids)));
    $result = $db->sql_query_prepared("SELECT tid, subject FROM threads WHERE tid IN ($thread_ids_str)");
    while ($result && ($row = $db->fetch_array($result))) {
        $threads_data[$row['tid']] = $row;
    }
}

if (!empty($forum_ids)) {
    $forum_ids_str = implode(',', array_map('intval', array_keys($forum_ids)));
    $result = $db->sql_query_prepared("SELECT fid, name FROM forums WHERE fid IN ($forum_ids_str)");
    while ($result && ($row = $db->fetch_array($result))) {
        $forums_data[$row['fid']] = $row;
    }
}

if (!empty($post_ids)) {
    $post_ids_str = implode(',', array_map('intval', array_keys($post_ids)));
    $result = $db->sql_query_prepared("SELECT pid, subject FROM posts WHERE pid IN ($post_ids_str)");
    while ($result && ($row = $db->fetch_array($result))) {
        $posts_data[$row['pid']] = $row;
    }
}

if (!empty($announcement_ids)) {
    $announcement_ids_str = implode(',', array_map('intval', array_keys($announcement_ids)));
    $result = $db->sql_query_prepared("SELECT id, subject FROM announcements WHERE id IN ($announcement_ids_str)");
    while ($result && ($row = $db->fetch_array($result))) {
        $announcements_data[$row['id']] = $row;
    }
}

















// Пагинация
$base_url = "index.php?act=log&action=combined_logs&" . http_build_query($filter_params);
$multipage = $total_count > $perpage ? multipage($total_count, $perpage, $page, $base_url) : '';
// POST (clear / delete) — before any output, so admin_redirect() can send a header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear']) && $_POST['clear'] === 'yes' && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            exit(htmlspecialchars($lang->log['err_invalid_token']));
        }

        // Everything: site events, admin and moderator actions
        $result = $db->sql_query_prepared('TRUNCATE TABLE sitelog');
        if ($result) {
            flash_message($lang->log['flash_cleared'], 'success');
        } else {
            flash_message(ags_fmt($lang->log['flash_clear_failed'], (string)$db->error()), 'danger');
        }
        admin_redirect('index.php?act=log&action=combined_logs');

    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['logid']) && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            exit(htmlspecialchars($lang->log['err_invalid_token']));
        }

        $site_log_ids = array_filter((array)$_POST['logid'], function($id) {
            return is_numeric($id) && $id > 0;
        });
        if (!empty($site_log_ids)) {
            $ids = implode(',', array_map('intval', $site_log_ids));
            $result = $db->sql_query_prepared("DELETE FROM sitelog WHERE id IN ($ids)");
            if ($result) {
                flash_message(ags_fmt($lang->log['flash_deleted'], (int)$db->affected_rows()), 'warning');
            } else {
                flash_message(ags_fmt($lang->log['flash_delete_failed'], (string)$db->error()), 'danger');
            }
        } else {
            flash_message($lang->log['flash_no_ids'], 'warning');
        }
        admin_redirect('index.php?act=log&action=combined_logs');
    }
}

// KPI tiles (indexed: added, category+added)
$kpi = static function (string $sql, array $p = []) use ($db): int {
    $r = $db->sql_query_prepared($sql, $p);
    return $r && ($row = $db->fetch_array($r)) ? (int)array_values($row)[0] : 0;
};
$day0       = (int)strtotime('today');
$kpi_total  = $kpi("SELECT COUNT(*) FROM sitelog");
$kpi_today  = $kpi("SELECT COUNT(*) FROM sitelog WHERE added >= ?", [$day0]);
$kpi_errors = $kpi("SELECT COUNT(*) FROM sitelog WHERE category IN ('error', 'security') AND added >= ?", [TIMENOW - 86400]);
$kpi_staff  = $kpi("SELECT COUNT(*) FROM sitelog WHERE (category = 'moderator' OR (category = 'admin' AND module <> '')) AND added >= ?", [TIMENOW - 7 * 86400]);

stdhead($lang->log['title_page']);
echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/log.css?ver=1">'
   . '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/flatpickr.min.css">'
   . '<script src="' . $BASEURL . '/admin/scripts/flatpickr.js"></script>';


// Event filter: value (English, used in SQL) => lang key
$event_options = [
    'Banned User'            => 'opt_event_banned_user',
    'Lifted User Ban'        => 'opt_event_lifted_ban',
    'Merged Selective Posts' => 'opt_event_merged_posts',
    'Deleted User'           => 'opt_event_deleted_user',
    'Edited Post'            => 'opt_event_edited_post',
    'Deleted Post'           => 'opt_event_deleted_post',
    'Moved Thread'           => 'opt_event_moved_thread',
    'Closed Thread'          => 'opt_event_closed_thread',
    'Screenshot'             => 'opt_event_screenshot',
    'Delete Comment'         => 'opt_event_delete_comment',
    'Torrent Upload'         => 'opt_event_torrent_upload',
];
$event_options_html = '<option value="all">' . $L['opt_event_all'] . '</option>';
foreach ($event_options as $ev_value => $ev_key) {
    $event_options_html .= "\n                        <option value=\"" . htmlspecialchars($ev_value) . '" '
        . ($event_filter == $ev_value ? 'selected' : '') . '>' . $L[$ev_key] . '</option>';
}

$lgL = static fn(string $k, string $d): string => (string)($L[$k] ?? htmlspecialchars($d));

$type_opts = [
    'both'      => ['fa-layer-group', $L['opt_log_both']],
    'site'      => ['fa-globe',       $L['lbl_type_site']],
    'moderator' => ['fa-user-shield', $L['lbl_type_mod']],
    'admin'     => ['fa-crown',       $L['lbl_type_admin']],
];
$type_seg = '';
foreach ($type_opts as $val => [$ic, $lbl]) {
    $type_seg .= '<label class="lg-seg__i"><input type="radio" name="log_type" value="' . $val . '"' . ($log_type === $val ? ' checked' : '') . '>'
               . '<span><i class="fa-solid ' . $ic . '"></i>' . $lbl . '</span></label>';
}

$can_clear = !empty($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1';

echo '<div class="container mt-3 mb-4 lg">';

// Hero
echo '<div class="lg-card mb-3"><div class="lg-head">'
   . '<span class="lg-head__ico"><i class="fa-solid fa-clock-rotate-left"></i></span>'
   . '<div class="min-w-0"><h1 class="lg-title">' . $L['title_system'] . '</h1>'
   . '<div class="lg-sub">' . $lgL('hero_sub', 'Site events, moderator and admin actions in one log') . '</div></div>'
   . '<div class="ms-auto d-flex flex-wrap gap-2">'
   .   '<button type="button" class="btn btn-sm btn-outline-secondary" id="refresh-logs"><i class="fa-solid fa-rotate me-1"></i>' . $L['btn_refresh'] . '</button>'
   .   '<button type="button" class="btn btn-sm btn-outline-secondary" id="export-csv"><i class="fa-solid fa-file-csv me-1"></i>' . $L['btn_export_csv'] . '</button>'
   .   ($can_clear ? '<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearModal"><i class="fa-solid fa-trash me-1"></i>' . $L['btn_clear_site_logs'] . '</button>' : '')
   . '</div></div></div>';

// KPI
echo '<div class="row g-3 mb-3">';
foreach ([
    ['fa-database',             'ic-blue',   $lgL('kpi_total', 'Total entries'),   ts_nf($kpi_total)],
    ['fa-calendar-day',         'ic-green',  $lgL('kpi_today', 'Today'),           ts_nf($kpi_today)],
    ['fa-triangle-exclamation', $kpi_errors > 0 ? 'ic-red' : 'ic-slate', $lgL('kpi_errors', 'Errors, 24 h'), ts_nf($kpi_errors)],
    ['fa-user-shield',          'ic-purple', $lgL('kpi_staff', 'Staff actions, 7 days'), ts_nf($kpi_staff)],
] as [$ic, $cls, $lbl, $val]) {
    echo '<div class="col-6 col-lg-3"><div class="lg-card lg-kpi"><span class="lg-kpi__ico ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
       . '<div class="min-w-0"><div class="lg-kpi__lbl">' . $lbl . '</div><div class="lg-kpi__val">' . $val . '</div></div></div></div>';
}
echo '</div>';

// Filters
echo '<form method="get" id="filter-form" class="lg-card lg-filters mb-3">
    <input type="hidden" name="act" value="log">
    <input type="hidden" name="action" value="combined_logs">
    <div class="lg-seg" role="radiogroup" aria-label="' . $L['lbl_log_type'] . '">' . $type_seg . '</div>
    <div class="lg-frow">
        <div class="lg-field lg-field--grow">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="search-input" name="query" placeholder="' . $L['ph_search'] . '" value="' . htmlspecialchars($searchstr) . '" aria-label="' . $L['lbl_search'] . '">
        </div>
        <div class="lg-field">
            <i class="fa-solid fa-filter"></i>
            <select class="form-select" id="event-filter" name="event_filter" aria-label="' . $L['lbl_event_type'] . '">' . $event_options_html . '</select>
        </div>
        <div class="lg-field lg-field--date">
            <i class="fa-solid fa-calendar"></i>
            <input type="text" class="form-control" id="date-filter" name="date_filter" value="' . htmlspecialchars($date_filter) . '" placeholder="' . $L['ph_date'] . '" aria-label="' . $L['lbl_date'] . '">
        </div>
        <button type="submit" class="btn btn-primary px-3"><i class="fa-solid fa-filter me-1"></i>' . $L['btn_apply_filters'] . '</button>
    </div>
</form>';

// Active filters (type is shown by the segmented control)
$chips = [];
if ($searchstr !== '') $chips[] = htmlspecialchars(ags_fmt($lang->log['af_search'], $searchstr));
if ($event_filter !== 'all') {
    $event_label = isset($event_options[$event_filter]) ? $lang->log[$event_options[$event_filter]] : $event_filter;
    $chips[] = htmlspecialchars(ags_fmt($lang->log['af_event'], $event_label));
}
if ($date_filter !== '') $chips[] = htmlspecialchars(ags_fmt($lang->log['af_date'], $date_filter));
if ($chips || $log_type !== 'both') {
    echo '<div class="lg-active mb-3"><i class="fa-solid fa-filter"></i>'
       . implode('', array_map(static fn(string $c): string => '<span class="lg-chip">' . $c . '</span>', $chips))
       . '<span class="lg-chip lg-chip--n">' . ags_fmt($L['lbl_found'], ts_nf($total_count)) . '</span>'
       . '<a href="index.php?act=log&action=combined_logs" class="ms-auto lg-reset"><i class="fa-solid fa-xmark me-1"></i>' . $L['btn_clear_filters'] . '</a></div>';
}
echo '<div id="log-container">';

if (count($logs) == 0) {
    echo '<div class="lg-card"><div class="lg-empty"><i class="fa-regular fa-folder-open"></i><div>' . $L['msg_no_logs'] . '</div></div></div>';
}
else
{
    echo '<form method="post" action="index.php?act=log&action=combined_logs" id="logs-form">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
            <div class="lg-card overflow-hidden">
                <div class="lg-sec">
                    <span class="lg-sec__ico"><i class="fa-solid fa-list"></i></span>
                    <h2 class="lg-sec__title">' . $L['sec_event_log'] . '</h2>
                    <span class="lg-chip lg-chip--n">' . ts_nf($total_count) . '</span>
                    ' . ($can_clear ? '<label class="ms-auto lg-selall"><input class="form-check-input" type="checkbox" id="select-all"><span>' . $L['lbl_select_site_logs'] . '</span></label>' : '') . '
                </div>
                <div class="table-responsive">
                    <table class="table lg-table mb-0">
                        <thead>
                            <tr>
                                <th>' . $L['th_type'] . '</th>
                                <th>' . $L['th_username'] . '</th>
                                <th>' . $L['th_date'] . '</th>
                                <th>' . $L['th_action'] . '</th>
                                <th>' . $L['th_information'] . '</th>
                                <th>' . $L['th_ip'] . '</th>
                                ' . ($can_clear ? '<th class="text-center"><i class="fa-solid fa-check"></i></th>' : '') . '
                            </tr>
                        </thead>
                        <tbody>';

    // ЦИКЛ ПО УЖЕ СОБРАННЫМ ДАННЫМ
    foreach ($logs as $arr) {
        $entry_type = $arr['log_type'];
        $id = $arr['id'];
        $timestamp = isset($arr['timestamp']) ? (int)$arr['timestamp'] : time();
        $date = function_exists('my_date') ? my_date('Y-m-d', $timestamp) : date('Y-m-d', $timestamp);
        $time = function_exists('my_date') && isset($timeformat) ? my_date($timeformat, $timestamp) : date('H:i:s', $timestamp);
        $is_new = (time() - $timestamp <= 120);
        $row_class = $is_new ? 'log-entry-new' : '';
        
        $type_badge = match($entry_type) {
            'site'      => '<span class="lg-type t-site"><i class="fa-solid fa-globe"></i>' . $L['lbl_type_site'] . '</span>',
            'moderator' => '<span class="lg-type t-mod"><i class="fa-solid fa-user-shield"></i>' . $L['lbl_type_mod'] . '</span>',
            'admin'     => '<span class="lg-type t-admin"><i class="fa-solid fa-crown"></i>' . $L['lbl_type_admin'] . '</span>',
            default     => '<span class="lg-type">?</span>',
        };
		
		
           
        $checkbox = (is_numeric($id) && $id > 0)
            ? '<input type="checkbox" class="form-check-input log-checkbox" name="logid[]" value="' . (int)$id . '" aria-label="#' . (int)$id . '">'
            : '<i class="fa-solid fa-lock text-body-tertiary"></i>';

        // Обработка содержимого в зависимости от типа лога
  
  
if ($entry_type === 'site') {
    $content     = $arr['content'];
    $action      = $content;
    $information = '';

    // Username
    if (!empty($arr['uid'])) {
        if (isset($users_from_data[$arr['uid']])) {
            $user22   = $users_from_data[$arr['uid']];
            $uname    = $user22['username'] ?? '';
            $ugroup   = $user22['usergroup'] ?? 2;
            $username = '<a href="' . $BASEURL . '/' . get_profile_link($arr['uid']) . '">'
                      . format_name($uname, $ugroup)
                      . '</a>';
        } else {
            $username = '<a href="' . $BASEURL . '/' . get_profile_link($arr['uid']) . '">'
                      . '#' . (int)$arr['uid']
                      . '</a>';
        }
    } else {
        // uid = 0 — автоматическая задача
        $username = '<span class="lg-pill tone-purple"><i class="fa-solid fa-robot"></i>' . $L['lbl_cron'] . '</span>';
    }

    // IP
    $ipaddress = '<span class="text-body-tertiary">—</span>';
    if (!empty($arr['ipaddress'])) {
        $decoded = my_inet_ntop($db->unescape_binary($arr['ipaddress']));
        if ($decoded && $decoded !== '0.0.0.0') {
            $ipaddress = $decoded;
        } else {
            $ipaddress = '<span class="lg-pill tone-purple"><i class="fa-solid fa-robot"></i>' . $L['lbl_cron'] . '</span>';
        }
    }

    // Category badge
    // category => [tone, icon, lang key]
    $cat_badges = [
        'general'    => ['secondary', 'fa-circle-info',           'cat_general'],
        'news'       => ['info',      'fa-newspaper',             'cat_news'],
        'screenshot' => ['info',      'fa-image',                 'cat_screenshot'],
        'torrent'    => ['success',   'fa-magnet',                'cat_torrent'],
        'cron'       => ['purple',    'fa-robot',                 'cat_cron'],
        'error'      => ['danger',    'fa-triangle-exclamation',  'cat_error'],
        'security'   => ['danger',    'fa-shield-halved',         'cat_security'],
        'settings'   => ['warning',   'fa-gear',                  'cat_settings'],
        'ban'        => ['danger',    'fa-ban',                   'cat_ban'],
        'deletion'   => ['danger',    'fa-trash',                 'cat_deletion'],
        'mail'       => ['info',      'fa-envelope',              'cat_mail'],
        'warning'    => ['warning',   'fa-triangle-exclamation',  'cat_warning'],
        'admin'      => ['danger',    'fa-crown',                 'cat_admin'],
    ];

    $cat = !empty($arr['category']) ? $arr['category'] : 'general';
    [$tone, $icon, $label_key] = $cat_badges[$cat] ?? $cat_badges['general'];
    $label = (string)($lang->log[$label_key] ?? ucfirst($cat));

    $information = '<span class="lg-pill tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i>' . htmlspecialchars($label) . '</span>';

    $match_action = $action; // badge matching on raw (English) log text
}

  
  
  
  
  
  
  
  
  
  
  
  
		





elseif ($entry_type === 'admin') {
    $username22  = $arr['username'] ? format_name($arr['username'], $arr['usergroup']) : $L['lbl_na_deleted'];
    $username    = '<a href="' . $BASEURL . '/' . get_profile_link($arr['uid']) . '">' . $username22 . '</a>';
    $ipaddress   = $arr['ipaddress'] ? my_inet_ntop($db->unescape_binary($arr['ipaddress'])) : $L['lbl_na'];
    $information = '';

    $logitem = [
        'uid'       => $arr['uid'],
        'username'  => $arr['username'] ?? '',
        'usergroup' => $arr['usergroup'] ?? '',
        'module'    => $arr['module'] ?? '',
        'action'    => $arr['content'] ?? '',
        'data'      => my_unserialize((string)($arr['data'] ?? '')),
        'dateline'  => $arr['timestamp'],
        'ipaddress' => $arr['ipaddress'],
    ];

    if (!is_array($logitem['data'])) {
        $logitem['data'] = [];
    }

    // Вызываем нашу функцию format_admin_log
    $action    = format_admin_log($logitem, $admin_log_lang);
    $action_en = format_admin_log($logitem, $admin_log_lang_en); // badge matching only

    // Module badge
    $module_raw = str_replace('/', '-', $arr['module'] ?? '');
    $module_keys = [
        'forum-management' => 'mod_forums',
        'user-management'  => 'mod_users',
        'config-settings'  => 'mod_settings',
        'config-plugins'   => 'mod_plugins',
        'style-templates'  => 'mod_templates',
        'style-themes'     => 'mod_themes',
        'tools-adminlog'   => 'mod_admin_log',
        'tools-modlog'     => 'mod_mod_log',
        'tools-backupdb'   => 'mod_backup',
        'tools-tasks'      => 'mod_tasks',
        'tools-cache'      => 'mod_cache',
        'user-groups'      => 'mod_groups',
        'user-banning'     => 'mod_banning',
    ];
    $module_key      = $module_keys[$module_raw] ?? null;
    $module_fallback = str_replace('-', ' ', ucwords($module_raw));
    $module_label    = $module_key !== null ? $lang->log[$module_key] : $module_fallback;
    $module_label_en = $module_key !== null ? (string)($admin_log_lang_en[$module_key] ?? $module_label) : $module_fallback;

    $module_span = static fn(string $label): string =>
        '<span class="lg-pill tone-danger me-1">' . htmlspecialchars($label) . '</span> ';

    if ($module_label) {
        $action = $module_span($module_label) . $action;
    }
    $match_action = $module_label_en ? $module_span($module_label_en) . $action_en : $action_en;
}





		
		
		
		else {
            // Для moderatorlog - ИСПОЛЬЗУЕМ ПРЕДЗАГРУЖЕННЫЕ ДАННЫЕ
            $username22 = $arr['username'] ? format_name($arr['username'], $arr['usergroup']) : $L['lbl_na_deleted'];
            $username = '<a href="'.$BASEURL.'/'.get_profile_link($arr['uid']).'">'.$username22.'</a>';
            
            $ipaddress = $arr['ipaddress'] ? my_inet_ntop($db->unescape_binary($arr['ipaddress'])) : $L['lbl_na'];
            
            // Формируем action с информацией из data
            $action = htmlspecialchars($arr['content']);
            $match_action = $action;
            $data = my_unserialize((string)($arr['data'] ?? ''));
            
            $mod_user = null;
            if (!empty($data['username'])) {
                $mod_user = (string)$data['username'];
            } elseif (!empty($data['uid']) && isset($users_from_data[$data['uid']])) {
                // Используем предзагруженные данные вместо запроса
                $user = $users_from_data[$data['uid']];
                $mod_user = (string)$user['username'];
            }
            if ($mod_user !== null) {
                $action       .= ' ' . $L['lbl_user_inline'] . ' ' . htmlspecialchars($mod_user);
                $match_action .= ' User: ' . htmlspecialchars($mod_user); // English, badge matching only
            }
            
            $information = '';
            
            // Используем предзагруженные данные вместо отдельных запросов
            if ($arr['tid'] && isset($threads_data[$arr['tid']])) {
                $thread = $threads_data[$arr['tid']];
                $information .= '<a class="lg-ref" href="../' . get_thread_link($arr['tid']) . '" target="_blank" title="' . $L['lbl_thread'] . '"><i class="fa-solid fa-comments"></i>' . htmlspecialchars($thread['subject']) . '</a>';
            }
            
            if ($arr['fid'] && isset($forums_data[$arr['fid']])) {
                $forum = $forums_data[$arr['fid']];
                $information .= '<a class="lg-ref" href="../' . get_forum_link($arr['fid']) . '" target="_blank" title="' . $L['lbl_forum'] . '"><i class="fa-solid fa-folder"></i>' . htmlspecialchars($forum['name']) . '</a>';
            }
            
            if ($arr['pid'] && isset($posts_data[$arr['pid']])) {
                $post = $posts_data[$arr['pid']];
                $information .= '<a class="lg-ref" href="../' . get_post_link($arr['pid']) . '#pid' . (int)$arr['pid'] . '" target="_blank" title="' . $L['lbl_post'] . '"><i class="fa-solid fa-message"></i>' . htmlspecialchars($post['subject']) . '</a>';
            }
            
            // Если в data есть информация об объявлении
            if (!$information && !empty($data['aid']) && isset($announcements_data[$data['aid']])) {
                $announcement = $announcements_data[$data['aid']];
                $information = '<a class="lg-ref" href="../' . get_announcement_link($data['aid']) . '" target="_blank" title="' . $L['lbl_announcement'] . '"><i class="fa-solid fa-bullhorn"></i>' . htmlspecialchars($announcement['subject']) . '</a>';
            }
        }

        // Парсинг с fallback
        try {
            $parsed_action = $parser->parse_message($action, $parser_options);
        } catch (Exception $e) {
            $parsed_action = htmlspecialchars($action);
            echo '<div class="alert alert-warning">' . htmlspecialchars(ags_fmt($lang->log['err_parser'], $e->getMessage())) . '</div>';
        }

        // Маппинг бейджей
        $badge_map = [
        // ── Модерация форума (moderation.php, modcp, usersearch) ──
        'Thread Deleted Permanently'    => ['danger',    'badge_thread_deleted',    'fa-trash'],
        'Threads Deleted Permanently'   => ['danger',    'badge_threads_del',       'fa-trash'],
        'Deleted threads'               => ['danger',    'badge_threads_del',       'fa-trash'],
        'Deleted posts'                 => ['danger',    'badge_del_posts',         'fa-object-group'],
        'Thread Unapproved'             => ['warning',   'badge_unapproved',        'fa-eye-slash'],
        'Threads Unapproved'            => ['warning',   'badge_unapproved',        'fa-eye-slash'],
        'Posts Unapproved'              => ['warning',   'badge_unapproved',        'fa-eye-slash'],
        'Thread Approved'               => ['success',   'badge_approved',          'fa-check'],
        'Threads Approved'              => ['success',   'badge_approved',          'fa-check'],
        'Posts Approved'                => ['success',   'badge_approved',          'fa-check'],
        'Thread Opened'                 => ['success',   'badge_thread_open',       'fa-lock-open'],
        'Threads Opened'                => ['success',   'badge_thread_open',       'fa-lock-open'],
        'Thread Closed'                 => ['secondary', 'badge_close_thread',      'fa-lock'],
        'Threads Closed'                => ['secondary', 'badge_close_thread',      'fa-lock'],
        'Thread Unstuck'                => ['secondary', 'badge_thread_unstuck',    'fa-thumbtack'],
        'Threads Unstuck'               => ['secondary', 'badge_thread_unstuck',    'fa-thumbtack'],
        'Thread Stuck'                  => ['info',      'badge_thread_stuck',      'fa-thumbtack'],
        'Threads Stuck'                 => ['info',      'badge_thread_stuck',      'fa-thumbtack'],
        'Thread Copied'                 => ['primary',   'badge_thread_copied',     'fa-copy'],
        'Thread Moved'                  => ['warning',   'badge_move_thread',       'fa-exchange-alt'],
        'Threads Moved / Copied'        => ['warning',   'badge_move_thread',       'fa-exchange-alt'],
        'Threads Merged'                => ['primary',   'badge_threads_merged',    'fa-code-merge'],
        'Split posts'                   => ['primary',   'badge_split_posts',       'fa-scissors'],
        'Moved posts'                   => ['warning',   'badge_posts_moved',       'fa-right-left'],
        'Poll Deleted'                  => ['danger',    'badge_poll_deleted',      'fa-square-poll-vertical'],
        'Thread Redirects Removed'      => ['secondary', 'badge_redirects_removed', 'fa-link-slash'],
        'Removed All Subscriptions'     => ['secondary', 'badge_subs_removed',      'fa-bell-slash'],
        'Bulk PM sent to'               => ['info',      'badge_bulk_pm',           'fa-envelope'],
        'Bulk changed group to'         => ['warning',   'badge_group_change',      'fa-users-gear'],
        'Bulk deleted'                  => ['danger',    'badge_bulk_delete',       'fa-user-xmark'],
        'Lifted User Ban (bulk)'        => ['success',   'badge_unban',             'fa-unlock'],

        // ── write_log() из админки: точные фразы выше общих ('moved', 'copied' …) ──
        'Lifted ban of'                 => ['success',   'badge_unban',             'fa-unlock'],
        'Edited ban of'                 => ['warning',   'badge_ban_edit',          'fa-user-pen'],
        'Added ban filter'              => ['danger',    'badge_ban_filter_add',    'fa-filter'],
        'Removed ban filter'            => ['success',   'badge_ban_filter_del',    'fa-filter-circle-xmark'],
        'Pruned all content of'         => ['danger',    'badge_prune_user',        'fa-broom'],
        'Awaiting activation: deleted'  => ['danger',    'badge_pending_deleted',   'fa-user-xmark'],
        'Awaiting activation: activated'=> ['success',   'badge_pending_activated', 'fa-user-check'],
        'Deleted usergroup'             => ['danger',    'badge_group_deleted',     'fa-users-slash'],
        'Edited permissions of group'   => ['warning',   'badge_permissions',       'fa-key'],
        'Copied forum'                  => ['primary',   'badge_forum_copy',        'fa-copy'],
        'display order'                 => ['secondary', 'badge_order',             'fa-arrow-down-1-9'],
        'Reloaded settings cache'       => ['success',   'badge_cache_rebuild',     'fa-arrows-rotate'],
        'Reloaded cache'                => ['success',   'badge_cache_rebuild',     'fa-arrows-rotate'],
        'Rebuilt all caches'            => ['success',   'badge_cache_rebuild',     'fa-arrows-rotate'],
        'Created database backup'       => ['success',   'badge_backup_created',    'fa-database'],
        'Downloaded database backup'    => ['info',      'badge_backup_downloaded', 'fa-download'],
        'Deleted database backup'       => ['danger',    'badge_backup_deleted',    'fa-trash'],
        'Deleted draft attachment'      => ['danger',    'badge_attachment_deleted','fa-trash-alt'],
        'Deleted comment attachment'    => ['danger',    'badge_attachment_deleted','fa-trash-alt'],
        'comment file'                  => ['danger',    'badge_attachment_deleted','fa-trash-alt'],
        'Banned '                       => ['danger',    'badge_ban',               'fa-ban'],

        // ── Пользователи / баны ──────────────────────────────
        'Banned User'          => ['danger',  'badge_ban',        'fa-ban'],
        'Lifted User Ban'      => ['success', 'badge_unban',      'fa-unlock'],
        'Lifted ban for user'  => ['success', 'badge_unban',      'fa-user-check'],
        'Deleted User'         => ['danger',  'badge_delete_user','fa-user-times'],
        'Added disallowed'     => ['danger',  'badge_blacklist_add', 'fa-ban'],
        'Removed disallowed'   => ['success', 'badge_blacklist_del', 'fa-check-circle'],
        'Added IP ban'         => ['danger',  'badge_ip_ban',        'fa-shield-alt'],
        'Removed IP ban'       => ['success', 'badge_ip_unban',      'fa-shield-alt'],
        'Added usergroup'      => ['success', 'badge_group_added', 'fa-users'],
        'Edited usergroup'     => ['warning', 'badge_group_edited', 'fa-pencil'],
        'Promoted users'       => ['success', 'badge_promotion',  'fa-level-up-alt'],
        'Demoted users'        => ['warning', 'badge_demotion',   'fa-level-down-alt'],
        'Leech-warned users'   => ['warning', 'badge_leech_warning', 'fa-exclamation-triangle'],
        'Added moderator'      => ['success', 'badge_add_mod',    'fa-user-plus'],
        'Mass Invite'          => ['success', 'badge_mass_invite','fa-envelope-open-text'],

        // ── Посты / темы ──────────────────────────────────────
        'Merged Selective Posts'  => ['primary', 'badge_merge_posts', 'fa-compress'],
        'Edited Post'              => ['primary', 'badge_edit_post',   'fa-edit'],
        'Deleted Post'             => ['danger',  'badge_delete_post', 'fa-trash'],
        'Moved Thread'             => ['warning', 'badge_move_thread', 'fa-exchange-alt'],
        'Closed Thread'            => ['secondary', 'badge_close_thread', 'fa-lock'],
        'Threads Deleted'          => ['danger',  'badge_threads_del', 'fa-trash'],
        'Deleted Selective Posts'  => ['danger',  'badge_del_posts',   'fa-object-group'],
        'was deleted by'           => ['danger',  'badge_deletion',    'fa-trash'],
        'has been deleted by'      => ['danger',  'badge_deletion',    'fa-trash'],
        'has been edited by'       => ['primary', 'badge_edit',        'fa-edit'],
        'has been saved'           => ['primary', 'badge_saved',       'fa-save'],

        // ── Комментарии ───────────────────────────────────────
        'copied'                    => ['primary', 'badge_copied_settings',   'fa-copy'],
        'moved'                     => ['warning', 'badge_comment_move',      'fa-arrows-alt'],
        'deleted a comment (CID'    => ['danger',  'badge_comment_delete',    'fa-comment'],
        'deleted comments'          => ['danger',  'badge_comment_delete',    'fa-comment'],
        'Mass Comment Delete'       => ['danger',  'badge_mass_del_comments', 'fa-comment-slash'],

        // ── Форумы / права ────────────────────────────────────
        'Added forum'       => ['success', 'badge_forum_add',   'fa-plus'],
        'Edited forum'      => ['primary', 'badge_forum_edit',  'fa-edit'],
        'Deleted forum'     => ['danger',  'badge_forum_del',   'fa-trash'],
        'Updated quick'     => ['warning', 'badge_permissions', 'fa-key'],
        'Edited group perm' => ['warning', 'badge_permissions', 'fa-key'],

        // ── Скриншоты ─────────────────────────────────────────
        'Screenshot uploaded:' => ['success', 'badge_screen_upload', 'fa-image'],
        'Screenshot deleted:'  => ['danger',  'badge_screen_delete', 'fa-trash'],
        'Screenshot updated:'  => ['primary', 'badge_screen_edit',   'fa-edit'],
        'Screenshot error'     => ['danger',  'badge_screen_error',  'fa-exclamation-circle'],
        'Mass Delete Screens:' => ['danger',  'badge_mass_screens_delete', 'fa-images'],
        'for torrent #'        => ['info',    'badge_torrent_screenshot',  'fa-film'],

        // ── Аплоад / скачивание ────────────────────────────────
        'has been uploaded'       => ['success', 'badge_upload',       'fa-upload'],
        'has downloaded'          => ['danger',  'badge_download',     'fa-download'],
        'GB upload added to'      => ['success', 'badge_upload_added', 'fa-upload'],
        'GB upload added to user' => ['success', 'badge_upload_added', 'fa-upload'],
        'Deleted attachment'      => ['danger',  'badge_attachment_deleted', 'fa-trash-alt'],

        // ── Продвижения / промо торрентов ────────────────────
        'Starting torrent promotion expiration cleanup' => ['info',      'badge_promo_cleanup_start', 'fa-play-circle'],
        'Finished torrent promotion expiration cleanup' => ['success',   'badge_promo_cleanup',   'fa-broom'],
        'Expired Free Leech promotions'                  => ['success',   'badge_freeleech_expired',   'fa-hourglass-end'],
        'Expired 50% + 2X promotions'                     => ['success',   'badge_50_2x_expired',      'fa-hourglass-end'],
        'Expired 30% Leech promotions'                    => ['success',   'badge_30_leech_expired',   'fa-hourglass-end'],
        'Expired 2X Upload promotions'                    => ['secondary', 'badge_2x_upload_expired',   'fa-stop-circle'],
        'Expired Free + 2X promotions'                    => ['secondary', 'badge_free_2x_expired',     'fa-stop-circle'],
        'Expired 50% Leech promotions'                    => ['secondary', 'badge_50_leech_expired',   'fa-stop-circle'],
        'Torrents no longer on promotion'                 => ['secondary', 'badge_promo_expired',   'fa-minus-circle'],
        'Torrents promotion changed'                      => ['success',   'badge_promo_changed',   'fa-star'],

        // ── SeedBonus ─────────────────────────────────────────
        'Seedbonus cron: Система отключена'         => ['danger',    'badge_seedbonus',         'fa-ban'],
        'Seedbonus cron: start'                      => ['info',      'badge_seedbonus_start',   'fa-play'],
        'Seedbonus cron: done | no active seeders'   => ['secondary', 'badge_seedbonus_done',    'fa-stop'],
        'Seedbonus cron: done | users='              => ['success',   'badge_seedbonus_done',    'fa-check'],
        'done | no active seeders'                    => ['success',   'badge_seedbonus_awarded','fa-check-circle'],
        'WARNING: User'                               => ['warning',   'badge_seedbonus_warning', 'fa-exclamation-triangle'],

        // ── База данных / кэш / бэкапы ───────────────────────
        'has been optimized..'                        => ['success', 'badge_optimization', 'fa-cogs'],
        'Database check completed'                     => ['success', 'badge_db_check',     'fa-database'],
        'Rebuilt cache'                                 => ['success', 'badge_cache_rebuild','fa-arrows-rotate'],
        'Database backup completed successfully'        => ['success', 'badge_backup_completed', 'fa-database'],
        'Created a backup'                              => ['success', 'badge_backup_created',   'fa-database'],
        'DB Optimized SUCCESS'                          => ['success', 'badge_db_optimized', 'fa-database'],
        'optimized successfully'                        => ['success', 'badge_db_optimized', 'fa-database'],
        'TRUNCATED table(s):'                           => ['danger',  'badge_table_truncated', 'fa-eraser'],
        'FAILED to truncate table(s):'                  => ['danger',  'badge_truncate_failed',  'fa-times-circle'],
        '[SQL ERROR]'                                    => ['danger',  'badge_sql_error', 'fa-exclamation-triangle'],

        // ── Настройки / система ───────────────────────────────
        'site settings updated by' => ['danger',  'badge_settings', 'fa-cog'],
        'settings updated'         => ['primary', 'badge_settings', 'fa-cogs'],
        'task successfully ran'    => ['success', 'badge_task',     'fa-tasks'],
        'send mail queue'          => ['info',    'badge_mail_queue', 'fa-paper-plane'],

        // ── Безопасность / спам ───────────────────────────────
        'Attempt'  => ['danger', 'badge_security', 'fa-shield-alt'],
        'unwanted' => ['danger', 'badge_spam',      'fa-ban'],
        ];
		
		
		
        $color = 'secondary';
        $badge_key = 'badge_log';
        $icon = 'fa-info-circle';
        // Needles are English: match against the English text, show the translated label
        foreach ($badge_map as $needle => [$clr, $lbl_key, $ico]) {
            if (stripos($match_action, $needle) !== false) {
                $color = $clr;
                $badge_key = $lbl_key;
                $icon = $ico;
                break;
            }
        }
        $badge = $L[$badge_key] ?? $L['badge_log'];
		
		
		

		
		
		
		
		
		
		
		

        $ip_plain = trim(strip_tags((string)$ipaddress));
        echo '<tr class="lg-row is-' . $entry_type . ($is_new ? ' is-new' : '') . '"'
           . ' data-type="' . htmlspecialchars(strip_tags($type_badge)) . '" data-user="' . htmlspecialchars(trim(strip_tags((string)$username))) . '"'
           . ' data-date="' . $date . '" data-time="' . htmlspecialchars((string)$time) . '"'
           . ' data-action="' . htmlspecialchars(trim(preg_replace('/\s+/', ' ', strip_tags($badge . ' ' . $parsed_action)))) . '" data-ip="' . htmlspecialchars($ip_plain) . '">'
           . '<td>' . $type_badge . '</td>'
           . '<td class="lg-user">' . $username . '</td>'
           . '<td class="lg-when"><span class="lg-when__d">' . $date . '</span><span class="lg-when__t">' . $time . '</span></td>'
           . '<td class="lg-act"><span class="lg-pill tone-' . $color . '"><i class="fa-solid ' . $icon . '"></i>' . $badge . '</span>'
           .   '<span class="lg-act__txt' . ($color === 'danger' ? ' is-danger' : '') . '">' . $parsed_action . '</span></td>'
           . '<td class="lg-info">' . $information . '</td>'
           . '<td class="lg-ip">' . $ipaddress . '</td>'
           . ($can_clear ? '<td class="text-center">' . $checkbox . '</td>' : '')
           . '</tr>';
    }

    echo '</tbody></table></div>'
       . ($can_clear ? '<div class="lg-foot"><span class="lg-muted"><i class="fa-solid fa-circle-info me-1"></i>' . ags_fmt($L['lbl_total_logs'], ts_nf($total_count)) . '</span>'
           . '<button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash me-1"></i>' . $L['btn_delete_selected_site'] . '</button></div>' : '')
       . '</div></form>';
}








if ($total_count > $perpage) {
    echo '<div class="lg-pages">' . $multipage . '</div>';
}
echo '</div></div>';



if ($can_clear) {
$modal_confirm_html = ags_fmt($L['modal_clear_confirm'],
    '<strong style="color:#e74a3b">' . $L['modal_clear_confirm_strong'] . '</strong>');
$modal_note_html = $lang->log['modal_clear_note']; // HTML allowed, output as-is

echo <<<HTML
<div class="modal fade" id="clearModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:20px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.2)">

            <!-- Шапка -->
            <div style="background:linear-gradient(135deg,#e74a3b 0%,#c0392b 100%);padding:32px 28px 24px;text-align:center;position:relative">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    style="position:absolute;top:16px;right:16px;opacity:0.8"></button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:2px solid rgba(255,255,255,0.3)">
                    <i class="fas fa-trash-alt" style="color:#fff;font-size:28px"></i>
                </div>
                <h4 style="color:#fff;margin:0;font-weight:700;font-size:20px">{$L['modal_clear_title']}</h4>
                <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:14px">{$L['modal_clear_sub']}</p>
            </div>

            <!-- Тело -->
            <div style="padding:28px;text-align:center;background:#fff">
                <p style="color:#5a5c69;font-size:15px;margin:0 0 12px;line-height:1.6">
                    {$modal_confirm_html}<br>{$L['modal_cannot_undo']}
                </p>
                <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;text-align:left">
                    <i class="fas fa-exclamation-circle" style="color:#f6c23e;font-size:18px;flex-shrink:0"></i>
                    <span style="color:#856404;font-size:13px">{$modal_note_html}</span>
                </div>
            </div>

            <!-- Футер -->
            <div style="padding:0 28px 28px;background:#fff;display:flex;gap:12px">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    style="flex:1;border-radius:10px;padding:11px;font-weight:600;border:2px solid #e3e6f0;background:#fff;color:#5a5c69">
                    <i class="fas fa-times me-1"></i> {$L['btn_cancel']}
                </button>
                <form method="post" action="index.php?act=log&action=combined_logs" style="flex:1;margin:0">
                    <input type="hidden" name="clear" value="yes">
                    <input type="hidden" name="my_post_key" value="{$mybb->post_code}">
                    <button type="submit"
                        style="width:100%;border-radius:10px;padding:11px;font-weight:600;border:none;background:linear-gradient(135deg,#e74a3b,#c0392b);color:#fff;cursor:pointer">
                        <i class="fas fa-trash-alt me-1"></i> {$L['btn_clear_logs']}
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
HTML;
}

// JS strings: js_* keys -> AGS_LANG without the prefix
$ags_js_lang = [];
foreach ($lang->log as $k => $v) {
    if (is_string($v) && str_starts_with((string)$k, 'js_')) {
        $ags_js_lang[substr((string)$k, 3)] = $v;
    }
}
?>
<script>
const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/admin/scripts/log.js?ver=3"></script>
<?php


stdfoot();

?>