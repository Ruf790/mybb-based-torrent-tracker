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
    "allow_html" => 0,
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

$where_conditions_sitelog = [];
$where_conditions_modlog = [];
$where_params_sitelog = [];
$where_params_modlog = [];


$where_conditions_adminlog = [];
$where_params_adminlog = [];
if ($searchstr !== '') {
    $search_like = "%{$searchstr}%";
    $where_conditions_adminlog[] = "(a.action LIKE ? OR a.module LIKE ? OR a.data LIKE ?)";
    array_push($where_params_adminlog, $search_like, $search_like, $search_like);
}
if ($event_filter !== 'all') {
    $event_like = "%{$event_filter}%";
    $where_conditions_adminlog[] = "(a.action LIKE ? OR a.module LIKE ?)";
    array_push($where_params_adminlog, $event_like, $event_like);
}
if ($date_filter !== '') {
    $where_conditions_adminlog[] = "DATE(FROM_UNIXTIME(a.dateline)) = ?";
    $where_params_adminlog[] = $date_filter;
}
$where_adminlog = !empty($where_conditions_adminlog) 
    ? "WHERE " . implode(" AND ", $where_conditions_adminlog) 
    : "";





if ($searchstr !== '') 
{
    $search_like = "%{$searchstr}%";
    $where_conditions_sitelog[] = "s.txt LIKE ?";
    $where_params_sitelog[] = $search_like;
    $where_conditions_modlog[] = "(m.action LIKE ? OR m.data LIKE ?)";
    array_push($where_params_modlog, $search_like, $search_like);
}








if ($event_filter !== 'all') 
{
    if ($event_filter === 'Screenshot') 
	{
        $where_conditions_sitelog[] = "(s.txt LIKE '%Screenshot uploaded:%' OR s.txt LIKE '%Screenshot deleted:%' OR s.txt LIKE '%Screenshot updated:%' OR s.txt LIKE '%Screenshot error%')";
        $where_conditions_modlog[] = "(m.action LIKE '%Screenshot%' OR m.data LIKE '%Screenshot%')";
    } 
	
	elseif ($event_filter === 'Delete Comment') 
    {
        $where_conditions_sitelog[] = "(
        s.txt LIKE '%Comment Delete%' 
        OR s.txt LIKE '%Mass Comment Delete%' 
        OR s.txt LIKE '%deleted a comment%' 
        OR s.txt LIKE '%deleted comments%' 
        OR s.txt LIKE '%User % deleted a comment (CID%' 
        )";
    
        $where_conditions_modlog[] = "(
        m.action LIKE '%Comment Delete%' 
        OR m.action LIKE '%Mass Comment Delete%' 
        OR m.action LIKE '%deleted a comment%' 
        OR m.action LIKE '%deleted comments%' 
        OR m.action LIKE '%User % deleted a comment (CID%' 
        )";
    }
	
	elseif ($event_filter === 'Torrent Upload') 
	{
        $where_conditions_sitelog[] = "(s.txt LIKE '%has been uploaded%' OR s.txt LIKE '%torrent uploaded%')";
        $where_conditions_modlog[] = "(m.action LIKE '%has been uploaded%' OR m.action LIKE '%torrent uploaded%')";
    } 
	else 
	{
        $event_like = "%{$event_filter}%";
        $where_conditions_sitelog[] = "s.txt LIKE ?";
        $where_params_sitelog[] = $event_like;
        $where_conditions_modlog[] = "m.action LIKE ?";
        $where_params_modlog[] = $event_like;
    }
}











if ($date_filter !== '') {
    $where_conditions_sitelog[] = "DATE(FROM_UNIXTIME(s.added)) = ?";
    $where_params_sitelog[] = $date_filter;
    $where_conditions_modlog[] = "DATE(FROM_UNIXTIME(m.dateline)) = ?";
    $where_params_modlog[] = $date_filter;
}
$where_sitelog = !empty($where_conditions_sitelog) ? "WHERE " . implode(" AND ", $where_conditions_sitelog) : "";
$where_modlog = !empty($where_conditions_modlog) ? "WHERE " . implode(" AND ", $where_conditions_modlog) : "";

// Считаем общее количество записей
$count_union = [];
$union_params = [];
if ($log_type === 'both' || $log_type === 'site') {
    $count_union[] = "SELECT COUNT(*) as count FROM sitelog s $where_sitelog";
    array_push($union_params, ...$where_params_sitelog);
}
if ($log_type === 'both' || $log_type === 'moderator') {
    $count_union[] = "SELECT COUNT(*) as count FROM moderatorlog m $where_modlog";
    array_push($union_params, ...$where_params_modlog);
}
if ($log_type === 'both' || $log_type === 'admin') {
    $count_union[] = "SELECT COUNT(*) as count FROM adminlog a $where_adminlog";
    array_push($union_params, ...$where_params_adminlog);
}
$count_sql = "SELECT SUM(count) as total FROM (" . implode(" UNION ALL ", $count_union) . ") as counts";
$result = $db->sql_query_prepared($count_sql, $union_params);
if (!$result) {
    die(ags_fmt($lang->log['err_count_query'], (string)$db->error()));
}
$row = $db->fetch_array($result);
$total_count = (int)$row['total'];


// Пагинация
$perpage = 50;
$pages = $total_count > 0 ? ceil($total_count / $perpage) : 1;
if ($page > $pages && $pages > 0) {
    $page = $pages;
}
$start = max(0, ($page - 1) * $perpage);




// Формируем UNION запрос
$union_queries = [];
if ($log_type === 'both' || $log_type === 'site') {
    $union_queries[] = "SELECT s.id, s.added as timestamp, s.txt as content,
                        'site' as log_type, s.uid, u.username, u.usergroup,
                        NULL as fid, NULL as tid, NULL as pid,
                        s.ipaddress, NULL as data, NULL as module
                        FROM sitelog s
                        LEFT JOIN users u ON (s.uid = u.id)
                        $where_sitelog";
}
if ($log_type === 'both' || $log_type === 'moderator') {
    $union_queries[] = "SELECT NULL as id, m.dateline as timestamp, m.action as content,
                        'moderator' as log_type, m.uid, u.username, u.usergroup,
                        m.fid, m.tid, m.pid, m.ipaddress, m.data, NULL as module
                        FROM moderatorlog m
                        LEFT JOIN users u ON (m.uid = u.id)
                        $where_modlog";
}
if ($log_type === 'both' || $log_type === 'admin') {
    $union_queries[] = "SELECT NULL as id, a.dateline as timestamp, a.action as content,
                        'admin' as log_type, a.uid, u.username, u.usergroup,
                        NULL as fid, NULL as tid, NULL as pid,
                        a.ipaddress, a.data, a.module
                        FROM adminlog a
                        LEFT JOIN users u ON (a.uid = u.id)
                        $where_adminlog";
}
if (empty($union_queries)) {
    $union_queries[] = "SELECT '0' as id, 0 as timestamp, 'No logs selected' as content,
                        'none' as log_type, NULL as uid, NULL as username, NULL as usergroup,
                        NULL as fid, NULL as tid, NULL as pid, NULL as ipaddress,
                        NULL as data, NULL as module
                        WHERE 1=0";
}
$main_query = "(" . implode(") UNION ALL (", $union_queries) . ") ORDER BY timestamp DESC";
$main_params = $union_params;
if ($total_count > 0) {
    $main_query .= " LIMIT ?, ?";
    $main_params[] = $start;
    $main_params[] = $perpage;
}


//error_log("Main query: $main_query"); // Логируем запрос для отладки
$res = $db->sql_query_prepared($main_query, $main_params);
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

        $data = my_unserialize($arr['data']);
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
// HTML-вывод
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $L['title_system'] . '</title>
   
   
        <style>
            :root {
                --primary: #4e73df;
                --success: #1cc88a;
                --info: #36b9cc;
                --warning: #f6c23e;
                --danger: #e74a3b;
                --dark: #5a5c69;
                --light: #f8f9fc;
            }
            
            .card-custom {
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                border: none;
                margin-bottom: 20px;
            }
            
            .card-header-custom {
                background: linear-gradient(135deg, #fff 0%, #f8f9fc 100%);
                border-bottom: 1px solid #e3e6f0;
                border-radius: 10px 10px 0 0 !important;
                padding: 15px 20px;
                font-weight: 700;
                color: var(--dark);
            }
            
            .log-entry {
                transition: all 0.3s ease;
                border-left: 4px solid transparent;
            }
            
            .log-entry:hover {
                background-color: #f8f9fc;
                transform: translateX(5px);
            }
            
            .log-entry-new {
                border-left-color: var(--success);
                background-color: rgba(28, 200, 138, 0.05);
            }
            
            .badge-log {
                font-size: 0.75em;
                padding: 5px 10px;
                border-radius: 20px;
            }
            
            .log-date {
                min-width: 110px;
                font-weight: 600;
                color: var(--dark);
            }
            
            .log-time {
                min-width: 100px;
                color: #858796;
            }
            
            .search-box {
                position: relative;
            }
            
            .search-box .form-control {
                padding-left: 40px;
                border-radius: 20px;
            }
            
            .search-box i {
                position: absolute;
                left: 15px;
                top: 12px;
                color: #b7b9cc;
            }
            
            .pagination-custom .page-item.active .page-link {
                background-color: var(--primary);
                border-color: var(--primary);
            }
            
            .pagination-custom .page-link {
                color: var(--primary);
                border-radius: 5px;
                margin: 0 3px;
                border: none;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }
            
            .btn-clear {
                border-radius: 20px;
                padding: 8px 20px;
                font-weight: 600;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .fade-in {
                animation: fadeIn 0.5s ease forwards;
            }
            
            .sticky-header {
                position: sticky;
                top: 0;
                background: white;
                z-index: 100;
                box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .log-content {
                line-height: 1.6;
            }
            
            .action-buttons {
                display: flex;
                gap: 10px;
            }
            
            .filter-row {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                align-items: end;
            }
            
            .filter-group {
                flex: 1;
                min-width: 200px;
            }
            
            .filter-group label {
                font-weight: 600;
                margin-bottom: 5px;
                color: var(--dark);
            }
            
            .stats-badge {
                background: linear-gradient(135deg, var(--primary) 0%, #2a4cb3 100%);
                color: white;
                padding: 8px 15px;
                border-radius: 20px;
                font-weight: 600;
            }
            
            @media (max-width: 768px) {
                .filter-row {
                    flex-direction: column;
                }
                
                .filter-group {
                    min-width: 100%;
                }
                
                .action-buttons {
                    flex-wrap: wrap;
                }
            }
        </style>
   
   
   
   
   
   
</head>
<body>';
if (function_exists('stdhead')) {
    stdhead($lang->log['title_page']);
} else {
    echo '<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-clipboard-list fa-2x me-2"></i>
                <span class="fw-bold">TS Special Edition v.5.6 - ' . $L['title_system'] . '</span>
            </a>
        </div>
    </nav>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear']) && $_POST['clear'] === 'yes' && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            exit(htmlspecialchars($lang->log['err_invalid_token']));
        }

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

echo '
<div class="container">
    <div class="sticky-header">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="stats-badge">
                <i class="fas fa-database me-1"></i> ' . ags_fmt($L['lbl_total_logs'], $total_count) . '
            </div>
            <div class="action-buttons">
                <button class="btn btn-danger btn-clear" data-bs-toggle="modal" data-bs-target="#clearModal">
                    <i class="fas fa-trash me-1"></i> ' . $L['btn_clear_site_logs'] . '
                </button>
                <button class="btn btn-primary btn-clear" id="refresh-logs">
                    <i class="fas fa-sync-alt me-1"></i> ' . $L['btn_refresh'] . '
                </button>
				
				<button class="btn btn-secondary btn-clear" id="export-csv">
                    <i class="fas fa-download me-1"></i> ' . $L['btn_export_csv'] . '
                </button>
				
				
            </div>
        </div>
        <form method="get" id="filter-form">
            <input type="hidden" name="act" value="log">
            <input type="hidden" name="action" value="combined_logs">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search-input"><i class="fas fa-search me-1"></i>' . $L['lbl_search'] . '</label>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="search-input" name="query"
                               placeholder="' . $L['ph_search'] . '" value="' . htmlspecialchars($searchstr) . '">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="log-type"><i class="fas fa-list me-1"></i>' . $L['lbl_log_type'] . '</label>
                    <select class="form-select" id="log-type" name="log_type">
                        <option value="both" ' . ($log_type == 'both' ? 'selected' : '') . '>' . $L['opt_log_both'] . '</option>
                        <option value="site" ' . ($log_type == 'site' ? 'selected' : '') . '>' . $L['opt_log_site'] . '</option>
                        <option value="moderator" ' . ($log_type == 'moderator' ? 'selected' : '') . '>' . $L['opt_log_moderator'] . '</option>
						<option value="admin"     ' . ($log_type == 'admin'     ? 'selected' : '') . '>' . $L['opt_log_admin'] . '</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="event-filter"><i class="fas fa-filter me-1"></i>' . $L['lbl_event_type'] . '</label>
                    <select class="form-select" id="event-filter" name="event_filter">
                        ' . $event_options_html . '
                    </select>
                </div>
                
				<div class="filter-group">
    <label for="date-filter"><i class="fas fa-calendar me-1"></i>' . $L['lbl_date'] . '</label>
    <input type="text" class="form-control" id="date-filter" name="date_filter"
           value="'.htmlspecialchars($date_filter).'" placeholder="' . $L['ph_date'] . '">
</div>


<link rel="stylesheet" href="'.$BASEURL.'/admin/templates/flatpickr.min.css">
<script src="'.$BASEURL.'/admin/scripts/flatpickr.js"></script>
				
				
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-filter me-1"></i> ' . $L['btn_apply_filters'] . '
                    </button>
                </div>
            </div>
        </form>
    </div>';

$active_filters = [];
if ($searchstr !== '') $active_filters[] = htmlspecialchars(ags_fmt($lang->log['af_search'], $searchstr));
if ($event_filter !== 'all') {
    $event_label = isset($event_options[$event_filter]) ? $lang->log[$event_options[$event_filter]] : $event_filter;
    $active_filters[] = htmlspecialchars(ags_fmt($lang->log['af_event'], $event_label));
}
if ($date_filter !== '') $active_filters[] = htmlspecialchars(ags_fmt($lang->log['af_date'], $date_filter));
$active_filters[] = htmlspecialchars(ags_fmt($lang->log['af_log_type'],
    match ($log_type) {
        'both'  => $lang->log['af_type_both'],
        'site'  => $lang->log['af_type_site'],
        'admin' => $lang->log['af_type_admin'],
        default => $lang->log['af_type_moderator'],
    }));
if (!empty($active_filters)) {
    echo '<div class="alert alert-info mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-filter me-2"></i>
                    <strong>' . $L['lbl_active_filters'] . '</strong> ' . implode(', ', $active_filters) . '
                    <span class="badge bg-primary ms-2">' . ags_fmt($L['lbl_found'], $total_count) . '</span>
                </div>
                <a href="index.php?act=log&action=combined_logs" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times me-1"></i> ' . $L['btn_clear_filters'] . '
                </a>
            </div>
        </div>';
}
echo '<div id="log-container"><div class="container mt-3">';

if ($total_count > $perpage) 
{
    echo '<div class="pagination">' . $multipage . '</div>';
}









if (count($logs) == 0) {
    echo '
    <div class="text-center py-5">
        <i class="fas fa-list fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">' . $L['msg_no_logs'] . '</h5>
    </div>';
} 
else 
{
    echo '<form method="post" action="index.php?act=log&action=combined_logs" id="logs-form">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="m-0"><i class="fas fa-history me-2"></i>' . $L['sec_event_log'] . '</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="select-all">
                        <label class="form-check-label small" for="select-all">' . $L['lbl_select_site_logs'] . '</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 100px">' . $L['th_type'] . '</th>
                                    <th style="width: 120px">' . $L['th_username'] . '</th>
                                    <th style="width: 120px">' . $L['th_date'] . '</th>
                                    <th style="width: 100px">' . $L['th_time'] . '</th>
                                    <th>' . $L['th_action'] . '</th>
                                    <th>' . $L['th_information'] . '</th>
                                    <th style="width: 120px">' . $L['th_ip'] . '</th>
                                    <th style="width: 80px" class="text-center">' . $L['th_select'] . '</th>
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
    'site'      => '<span class="badge bg-primary badge-site log-type-badge"><i class="fas fa-globe me-1"></i> ' . $L['lbl_type_site'] . '</span>',
    'moderator' => '<span class="badge bg-success badge-moderator log-type-badge"><i class="fas fa-user-shield me-1"></i> ' . $L['lbl_type_mod'] . '</span>',
    'admin'     => '<span class="badge bg-danger badge-log log-type-badge"><i class="fas fa-crown"></i> ' . $L['lbl_type_admin'] . '</span>',
    default     => '<span class="log-type-pill badge-site">?</span>',
};
		
		
           
        $checkbox = (is_numeric($id) && $id > 0) ?
            '<input type="checkbox" class="form-check-input log-checkbox" name="logid[]" value="' . $id . '">' :
            '<span class="text-muted"><i class="fas fa-lock"></i></span>';

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
        $username = '<span class="badge bg-info badge-log log-type-badge">'
              . '<i class="fas fa-sync-alt me-1"></i> ' . $L['lbl_cron']
              . '</span>';
    }

    // IP
    $ipaddress = '<span style="color:#8a8ea8;font-size:12px">—</span>';
    if (!empty($arr['ipaddress'])) {
        $decoded = my_inet_ntop($db->unescape_binary($arr['ipaddress']));
        if ($decoded && $decoded !== '0.0.0.0') {
            $ipaddress = $decoded;
        } else {
            $ipaddress = '<span style="background:#f4f0fd;color:#6a3fc1;padding:2px 8px;'
                       . 'border-radius:6px;font-size:11px;font-weight:700">'
                       . '<i class="fas fa-robot me-1"></i>' . $L['lbl_cron']
                       . '</span>';
        }
    }

    // Category badge
    $cat_badges = [
        'general'    => ['#f0f2fa', '#6c7293', 'fa-circle-info',        'cat_general'],
        'news'       => ['#eaf6ff', '#0a6ebd', 'fa-newspaper',          'cat_news'],
        'screenshot' => ['#e8f4fd', '#1a7fc1', 'fa-image',              'cat_screenshot'],
        'torrent'    => ['#e8f9f0', '#1a8a4a', 'fa-magnet',             'cat_torrent'],
        'cron'       => ['#f4f0fd', '#6a3fc1', 'fa-robot',              'cat_cron'],
        'error'      => ['#fdecea', '#c0392b', 'fa-triangle-exclamation', 'cat_error'],
        'security'   => ['#fdecea', '#8e1a1a', 'fa-shield-halved',      'cat_security'],
        'settings'   => ['#fff8e6', '#c07800', 'fa-gear',               'cat_settings'],
        'ban'        => ['#fdecea', '#c0392b', 'fa-ban',                'cat_ban'],
        'deletion'   => ['#fdecea', '#c0392b', 'fa-trash',              'cat_deletion'],
        'mail'       => ['#e8f4fd', '#1a7fc1', 'fa-envelope',           'cat_mail'],
        'warning'    => ['#fff8e6', '#c07800', 'fa-triangle-exclamation', 'cat_warning'],
    ];

    $cat = !empty($arr['category']) ? $arr['category'] : 'general';
    [$bg, $cl, $icon, $label_key] = $cat_badges[$cat] ?? $cat_badges['general'];
    $label = $lang->log[$label_key];

    $information = '<span style="background:' . $bg . ';color:' . $cl . ';padding:3px 9px;'
                 . 'border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;'
                 . 'display:inline-flex;align-items:center;gap:4px">'
                 . '<i class="fas ' . $icon . '" style="font-size:10px"></i>'
                 . htmlspecialchars($label)
                 . '</span>';

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
        'data'      => my_unserialize($arr['data']),
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
        '<span style="background:#fff0e6;color:#c0392b;padding:2px 8px;'
        . 'border-radius:6px;font-size:11px;font-weight:700;margin-right:6px">'
        . htmlspecialchars($label)
        . '</span> ';

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
            $data = my_unserialize($arr['data']);
            
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
                $information .= "<strong>".$L['lbl_thread']."</strong> <a href=\"../".get_thread_link($arr['tid'])."\" target=\"_blank\">".htmlspecialchars($thread['subject'])."</a><br />";
            }
            
            if ($arr['fid'] && isset($forums_data[$arr['fid']])) {
                $forum = $forums_data[$arr['fid']];
                $information .= "<strong>".$L['lbl_forum']."</strong> <a href=\"../".get_forum_link($arr['fid'])."\" target=\"_blank\">".htmlspecialchars($forum['name'])."</a><br />";
            }
            
            if ($arr['pid'] && isset($posts_data[$arr['pid']])) {
                $post = $posts_data[$arr['pid']];
                $information .= "<strong>".$L['lbl_post']."</strong> <a href=\"../".get_post_link($arr['pid'])."#pid{$arr['pid']}\" target=\"_blank\">".htmlspecialchars($post['subject'])."</a>";
            }
            
            // Если в data есть информация об объявлении
            if (!$information && !empty($data['aid']) && isset($announcements_data[$data['aid']])) {
                $announcement = $announcements_data[$data['aid']];
                $information = "<strong>".$L['lbl_announcement']."</strong> <a href=\"../".get_announcement_link($data['aid'])."\" target=\"_blank\">".htmlspecialchars($announcement['subject'])."</a>";
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
        $badge = $L[$badge_key];
		
		
		

		
		
		
		
		
		
		
		

        echo "<tr class='log-entry $row_class fade-in'>
                <td>$type_badge</td>
                <td>$username</td>
                <td class='log-date'><i class='fas fa-calendar-alt me-1 text-muted'></i> $date</td>
                <td class='log-time'><i class='fas fa-clock me-1 text-muted'></i> $time</td>
                <td>
                    <span class='badge bg-$color badge-log'>
                        <i class='fas $icon me-1'></i> $badge
                    </span>
                    <span class='ms-2 text-$color'><b>$parsed_action</b></span>
                </td>
                <td>$information</td>
                <td>$ipaddress</td>
                <td class='text-center'>
                    $checkbox
                </td>
              </tr>";
    }

    echo '</tbody></table></div></div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="fas fa-trash me-1"></i> ' . $L['btn_delete_selected_site'] . '
            </button>
        </div>
        </div></form>';
}








if ($total_count > $perpage) {
    echo '<div class="pagination">' . $multipage . '</div>';
}
echo '</div></div>';



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
<script src="<?= $BASEURL ?>/admin/scripts/log.js?ver=1"></script>
</body>
</html>
<?php


stdfoot();

?>