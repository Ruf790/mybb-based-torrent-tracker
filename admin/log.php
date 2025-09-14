<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/***********************************************/
// Проверяем INC_PATH
if (!defined('INC_PATH')) {
    define('INC_PATH', './inc'); // Укажи правильный путь, если отличается
}
// Проверяем зависимости
if (!file_exists(INC_PATH . '/class_parser.php')) {
    die('Error: class_parser.php not found in ' . INC_PATH);
}
require_once(INC_PATH . '/class_parser.php');
if (!file_exists(INC_PATH . '/functions_multipage.php')) {
    die('Error: functions_multipage.php not found in ' . INC_PATH);
}
require_once(INC_PATH . '/functions_multipage.php');
// Проверяем MyBB окружение
if (!isset($mybb) || !isset($db)) {
    die('Error: MyBB environment ($mybb or $db) is not initialized. Ensure this script runs within MyBB admin panel.');
}
// Проверяем STAFF_PANEL_TSSEv56
if (!defined('STAFF_PANEL_TSSEv56')) {
    define('STAFF_PANEL_TSSEv56', true);
    echo '<div class="alert alert-warning">Warning: STAFF_PANEL_TSSEv56 was not defined. Defined for testing.</div>';
}
// Инициализация парсера
$parser = new postParser;
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];
// Получаем параметры фильтрации
$searchstr = isset($_GET['query']) ? trim($db->escape_string($_GET['query'])) : '';
$event_filter = isset($_GET['event_filter']) ? trim($db->escape_string($_GET['event_filter'])) : 'all';
$date_filter = isset($_GET['date_filter']) ? trim($db->escape_string($_GET['date_filter'])) : '';
$log_type = isset($_GET['log_type']) ? trim($db->escape_string($_GET['log_type'])) : 'both';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
// Параметры для URL
$filter_params = [
    'query' => $searchstr,
    'event_filter' => $event_filter,
    'date_filter' => $date_filter,
    'log_type' => $log_type,
    'page' => $page
];
// Формируем условия WHERE
$where_conditions_sitelog = [];
$where_conditions_modlog = [];
if ($searchstr !== '') {
    $search_esc = "%" . $db->escape_string($searchstr) . "%";
    $where_conditions_sitelog[] = "s.txt LIKE '$search_esc'";
    $where_conditions_modlog[] = "(m.action LIKE '$search_esc' OR m.data LIKE '$search_esc')";
}
if ($event_filter !== 'all') {
    if ($event_filter === 'Screenshot') {
        $where_conditions_sitelog[] = "(s.txt LIKE '%Screenshot uploaded:%' OR s.txt LIKE '%Screenshot deleted:%' OR s.txt LIKE '%Screenshot updated:%' OR s.txt LIKE '%Screenshot error%')";
        $where_conditions_modlog[] = "(m.action LIKE '%Screenshot%' OR m.data LIKE '%Screenshot%')";
    } else {
        $event_esc = "%" . $db->escape_string($event_filter) . "%";
        $where_conditions_sitelog[] = "s.txt LIKE '$event_esc'";
        $where_conditions_modlog[] = "m.action LIKE '$event_esc'";
    }
}
if ($date_filter !== '') {
    $date_esc = $db->escape_string($date_filter);
    $where_conditions_sitelog[] = "DATE(FROM_UNIXTIME(s.added)) = '$date_esc'";
    $where_conditions_modlog[] = "DATE(FROM_UNIXTIME(m.dateline)) = '$date_esc'";
}
$where_sitelog = !empty($where_conditions_sitelog) ? "WHERE " . implode(" AND ", $where_conditions_sitelog) : "";
$where_modlog = !empty($where_conditions_modlog) ? "WHERE " . implode(" AND ", $where_conditions_modlog) : "";
// Считаем общее количество записей
$total_count = 0;
if ($log_type === 'both' || $log_type === 'site') {
    $count_query = "SELECT COUNT(*) as count FROM sitelog s $where_sitelog";
    $result = $db->sql_query($count_query);
    if (!$result) {
        die('Error: Site log count query failed: ' . $db->error());
    }
    $row = $db->fetch_array($result);
    $total_count += (int)$row['count'];
}
if ($log_type === 'both' || $log_type === 'moderator') {
    $count_query = "SELECT COUNT(*) as count FROM moderatorlog m $where_modlog";
    $result = $db->sql_query($count_query);
    if (!$result) {
        die('Error: Moderator log count query failed: ' . $db->error());
    }
    $row = $db->fetch_array($result);
    $total_count += (int)$row['count'];
}
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
    $union_queries[] = "SELECT s.id, s.added as timestamp, s.txt as content, 'site' as log_type, NULL as uid, NULL as username, NULL as usergroup, NULL as fid, NULL as tid, NULL as pid, NULL as ipaddress, NULL as data
                       FROM sitelog s $where_sitelog";
}
if ($log_type === 'both' || $log_type === 'moderator') {
    $union_queries[] = "SELECT NULL as id, m.dateline as timestamp, m.action as content, 'moderator' as log_type, m.uid, u.username, u.usergroup, m.fid, m.tid, m.pid, m.ipaddress, m.data
                       FROM moderatorlog m
                       LEFT JOIN users u ON (m.uid = u.id)
                       LEFT JOIN tsf_threads t ON (m.tid = t.tid)
                       LEFT JOIN tsf_forums f ON (m.fid = f.fid)
                       LEFT JOIN tsf_posts p ON (m.pid = p.pid)
                       $where_modlog";
}
if (empty($union_queries)) {
    $union_queries[] = "SELECT '0' as id, 0 as timestamp, 'No logs selected' as content, 'none' as log_type, NULL as uid, NULL as username, NULL as fid, NULL as tid, NULL as pid, NULL as ipaddress, NULL as data
                       WHERE 1=0";
}
$main_query = "(" . implode(") UNION ALL (", $union_queries) . ") ORDER BY timestamp DESC";
if ($total_count > 0) {
    $main_query .= " LIMIT $start, $perpage";
}
//error_log("Main query: $main_query"); // Логируем запрос для отладки
$res = $db->sql_query($main_query);
if (!$res) {
    die('Error: Main query failed: ' . $db->error());
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
    if ($arr['log_type'] === 'moderator') {
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
}

// ПРЕДЗАГРУЗКА ВСЕХ ДАННЫХ
$users_from_data = [];
$threads_data = [];
$forums_data = [];
$posts_data = [];
$announcements_data = [];

if (!empty($user_ids_from_data)) {
    $user_ids_str = implode(',', array_keys($user_ids_from_data));
    $result = $db->sql_query("SELECT id, username FROM users WHERE id IN ($user_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $users_from_data[$row['id']] = $row;
    }
}

if (!empty($thread_ids)) {
    $thread_ids_str = implode(',', array_keys($thread_ids));
    $result = $db->sql_query("SELECT tid, subject FROM tsf_threads WHERE tid IN ($thread_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $threads_data[$row['tid']] = $row;
    }
}

if (!empty($forum_ids)) {
    $forum_ids_str = implode(',', array_keys($forum_ids));
    $result = $db->sql_query("SELECT fid, name FROM tsf_forums WHERE fid IN ($forum_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $forums_data[$row['fid']] = $row;
    }
}

if (!empty($post_ids)) {
    $post_ids_str = implode(',', array_keys($post_ids));
    $result = $db->sql_query("SELECT pid, subject FROM tsf_posts WHERE pid IN ($post_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $posts_data[$row['pid']] = $row;
    }
}

if (!empty($announcement_ids)) {
    $announcement_ids_str = implode(',', array_keys($announcement_ids));
    $result = $db->sql_query("SELECT aid, subject FROM tsf_announcements WHERE aid IN ($announcement_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $announcements_data[$row['aid']] = $row;
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
    <title>Combined Log System - TS Special Edition v.5.6</title>
   
   
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
    stdhead('Combined Logs');
} else {
    echo '<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-clipboard-list fa-2x me-2"></i>
                <span class="fw-bold">TS Special Edition v.5.6 - Combined Log System</span>
            </a>
        </div>
    </nav>';
}
// Обработка очистки и удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear']) && $_POST['clear'] === 'yes' && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        $result = $db->query('TRUNCATE TABLE sitelog');
        if ($result) {
            echo '<div class="alert alert-danger text-center">Site log table cleared!</div>';
        } else {
            echo '<div class="alert alert-danger text-center">Failed to clear site log: ' . $db->error() . '</div>';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['logid']) && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        $site_log_ids = array_filter((array)$_POST['logid'], function($id) {
            return is_numeric($id) && $id > 0;
        });
        if (!empty($site_log_ids)) {
            $ids = implode(',', array_map('intval', $site_log_ids));
            $result = $db->query("DELETE FROM sitelog WHERE id IN ($ids)");
            if ($result) {
                echo '<div class="alert alert-warning text-center">Deleted ' . $db->affected_rows() . ' site log(s)!</div>';
            } else {
                echo '<div class="alert alert-danger text-center">Delete failed: ' . $db->error() . '</div>';
            }
        }
    }
}
// Форма фильтров
echo '
<div class="container">
    <div class="sticky-header">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="stats-badge">
                <i class="fas fa-database me-1"></i> Total Logs: ' . $total_count . '
            </div>
            <div class="action-buttons">
                <button class="btn btn-danger btn-clear" data-bs-toggle="modal" data-bs-target="#clearModal">
                    <i class="fas fa-trash me-1"></i> Clear Site Logs
                </button>
                <button class="btn btn-primary btn-clear" id="refresh-logs">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
            </div>
        </div>
        <form method="get" id="filter-form">
            <input type="hidden" name="act" value="log">
            <input type="hidden" name="action" value="combined_logs">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search-input"><i class="fas fa-search me-1"></i>Search</label>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="search-input" name="query"
                               placeholder="Search logs..." value="' . htmlspecialchars($searchstr) . '">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="log-type"><i class="fas fa-list me-1"></i>Log Type</label>
                    <select class="form-select" id="log-type" name="log_type">
                        <option value="both" ' . ($log_type == 'both' ? 'selected' : '') . '>Both Logs</option>
                        <option value="site" ' . ($log_type == 'site' ? 'selected' : '') . '>Site Log Only</option>
                        <option value="moderator" ' . ($log_type == 'moderator' ? 'selected' : '') . '>Moderator Log Only</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="event-filter"><i class="fas fa-filter me-1"></i>Event Type</label>
                    <select class="form-select" id="event-filter" name="event_filter">
                        <option value="all">All Events</option>
                        <option value="Banned User" ' . ($event_filter == 'Banned User' ? 'selected' : '') . '>Banned User</option>
                        <option value="Lifted User Ban" ' . ($event_filter == 'Lifted User Ban' ? 'selected' : '') . '>Lifted User Ban</option>
                        <option value="Merged Selective Posts" ' . ($event_filter == 'Merged Selective Posts' ? 'selected' : '') . '>Merged Selective Posts</option>
                        <option value="Deleted User" ' . ($event_filter == 'Deleted User' ? 'selected' : '') . '>Deleted User</option>
                        <option value="Edited Post" ' . ($event_filter == 'Edited Post' ? 'selected' : '') . '>Edited Post</option>
                        <option value="Deleted Post" ' . ($event_filter == 'Deleted Post' ? 'selected' : '') . '>Deleted Post</option>
                        <option value="Moved Thread" ' . ($event_filter == 'Moved Thread' ? 'selected' : '') . '>Moved Thread</option>
                        <option value="Closed Thread" ' . ($event_filter == 'Closed Thread' ? 'selected' : '') . '>Closed Thread</option>
                        <option value="Screenshot" ' . ($event_filter == 'Screenshot' ? 'selected' : '') . '>Screenshots</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date-filter"><i class="fas fa-calendar me-1"></i>Date</label>
                    <input type="date" class="form-control" id="date-filter" name="date_filter"
                           value="' . htmlspecialchars($date_filter) . '">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>';
// Активные фильтры
$active_filters = [];
if ($searchstr !== '') $active_filters[] = 'Search: "' . htmlspecialchars($searchstr) . '"';
if ($event_filter !== 'all') $active_filters[] = 'Event: ' . htmlspecialchars($event_filter);
if ($date_filter !== '') $active_filters[] = 'Date: ' . htmlspecialchars($date_filter);
$active_filters[] = 'Log Type: ' . ($log_type === 'both' ? 'Both' : ($log_type === 'site' ? 'Site Only' : 'Moderator Only'));
if (!empty($active_filters)) {
    echo '<div class="alert alert-info mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-filter me-2"></i>
                    <strong>Active filters:</strong> ' . implode(', ', $active_filters) . '
                    <span class="badge bg-primary ms-2">' . $total_count . ' found</span>
                </div>
                <a href="index.php?act=log&action=combined_logs" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times me-1"></i> Clear Filters
                </a>
            </div>
        </div>';
}
echo '<div id="log-container"><div class="container mt-3">';
// Пагинация
if ($total_count > $perpage) {
    echo '<div class="pagination">' . $multipage . '</div>';
}

















// ТЕПЕРЬ ВЫВОДИМ ТАБЛИЦУ
if (count($logs) == 0) 
{
    echo '<div class="alert alert-warning text-center">No logs found matching your criteria.</div>';
} 
else 
{
    echo '<form method="post" action="index.php?act=log&action=combined_logs" id="logs-form">
            <input type="hidden" name="action" value="delete">
            <div class="card card-custom">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="m-0"><i class="fas fa-history me-2"></i>Combined Event Log</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="select-all">
                        <label class="form-check-label small" for="select-all">Select Site Logs</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 100px">Type</th>
                                    <th style="width: 120px">Username</th>
                                    <th style="width: 120px">Date</th>
                                    <th style="width: 100px">Time</th>
                                    <th>Action</th>
                                    <th>Information</th>
                                    <th style="width: 120px">IP Address</th>
                                    <th style="width: 80px" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>';

    // ЦИКЛ ПО УЖЕ СОБРАННЫМ ДАННЫМ
    foreach ($logs as $arr) {
        $log_type = $arr['log_type'];
        $id = $arr['id'];
        $timestamp = $arr['timestamp'];
        $date = function_exists('my_date') ? my_date('Y-m-d', $timestamp) : date('Y-m-d', $timestamp);
        $time = function_exists('my_date') && isset($timeformat) ? my_date($timeformat, $timestamp) : date('H:i:s', $timestamp);
        $is_new = (time() - $timestamp <= 120);
        $row_class = $is_new ? 'log-entry-new' : '';
        $type_badge = $log_type === 'site' ?
            '<span class="badge bg-primary badge-site log-type-badge"><i class="fas fa-globe me-1"></i> Site</span>' :
            '<span class="badge bg-success badge-moderator log-type-badge"><i class="fas fa-user-shield me-1"></i> Mod</span>';
        $checkbox = (is_numeric($id) && $id > 0) ?
            '<input type="checkbox" class="form-check-input log-checkbox" name="logid[]" value="' . $id . '">' :
            '<span class="text-muted"><i class="fas fa-lock"></i></span>';

        // Обработка содержимого в зависимости от типа лога
        if ($log_type === 'site') {
            $content = $arr['content'];
            $username = 'N/A';
            $action = $content;
            $information = '';
            $ipaddress = 'N/A';
        } else {
            // Для moderatorlog - ИСПОЛЬЗУЕМ ПРЕДЗАГРУЖЕННЫЕ ДАННЫЕ
            $username22 = $arr['username'] ? format_name($arr['username'], $arr['usergroup']) : 'N/A (Deleted)';
            $username = '<a href="'.$BASEURL.'/'.get_profile_link($arr['uid']).'">'.$username22.'</a>';
            
            $ipaddress = $arr['ipaddress'] ? my_inet_ntop($db->unescape_binary($arr['ipaddress'])) : 'N/A';
            
            // Формируем action с информацией из data
            $action = htmlspecialchars($arr['content']);
            $data = my_unserialize($arr['data']);
            
            if (!empty($data['username'])) {
                $action .= ' User: ' . htmlspecialchars($data['username']);
            } elseif (!empty($data['uid']) && isset($users_from_data[$data['uid']])) {
                // Используем предзагруженные данные вместо запроса
                $user = $users_from_data[$data['uid']];
                $action .= ' User: ' . htmlspecialchars($user['username']);
            }
            
            $information = '';
            
            // Используем предзагруженные данные вместо отдельных запросов
            if ($arr['tid'] && isset($threads_data[$arr['tid']])) {
                $thread = $threads_data[$arr['tid']];
                $information .= "<strong>Thread:</strong> <a href=\"../".get_thread_link($arr['tid'])."\" target=\"_blank\">".htmlspecialchars($thread['subject'])."</a><br />";
            }
            
            if ($arr['fid'] && isset($forums_data[$arr['fid']])) {
                $forum = $forums_data[$arr['fid']];
                $information .= "<strong>Forum:</strong> <a href=\"../".get_forum_link($arr['fid'])."\" target=\"_blank\">".htmlspecialchars($forum['name'])."</a><br />";
            }
            
            if ($arr['pid'] && isset($posts_data[$arr['pid']])) {
                $post = $posts_data[$arr['pid']];
                $information .= "<strong>Post:</strong> <a href=\"../".get_post_link($arr['pid'])."#pid{$arr['pid']}\" target=\"_blank\">".htmlspecialchars($post['subject'])."</a>";
            }
            
            // Если в data есть информация об объявлении
            if (!$information && !empty($data['aid']) && isset($announcements_data[$data['aid']])) {
                $announcement = $announcements_data[$data['aid']];
                $information = "<strong>Announcement:</strong> <a href=\"../".get_announcement_link($data['aid'])."\" target=\"_blank\">".htmlspecialchars($announcement['subject'])."</a>";
            }
        }

        // Парсинг с fallback
        try {
            $parsed_action = $parser->parse_message($action, $parser_options);
        } catch (Exception $e) {
            $parsed_action = htmlspecialchars($action);
            echo '<div class="alert alert-warning">Parser error: ' . $e->getMessage() . '</div>';
        }

        // Маппинг бейджей
        $badge_map = [
            'Banned User' => ['danger', 'Ban', 'fa-ban'],
            'Lifted User Ban' => ['success', 'Unban', 'fa-unlock'],
            'Merged Selective Posts' => ['primary', 'Merge Posts', 'fa-compress'],
            'Deleted User' => ['danger', 'Delete User', 'fa-user-times'],
            'Edited Post' => ['primary', 'Edit Post', 'fa-edit'],
            'Deleted Post' => ['danger', 'Delete Post', 'fa-trash'],
            'Moved Thread' => ['warning', 'Move Thread', 'fa-exchange-alt'],
            'Closed Thread' => ['secondary', 'Close Thread', 'fa-lock'],
            'has been uploaded' => ['success', 'Upload', 'fa-upload'],
            'has been optimized..' => ['success', 'Optimization', 'fa-cogs'],
            'with no corrupted tables found' => ['success', 'DB Check', 'fa-database'],
            'task successfully ran' => ['success', 'Task', 'fa-tasks'],
            'was deleted by' => ['danger', 'Deletion', 'fa-trash'],
            'has been deleted by' => ['danger', 'Deletion', 'fa-trash'],
            'Attempt' => ['danger', 'Security', 'fa-shield-alt'],
            'unwanted' => ['danger', 'Spam', 'fa-ban'],
            'has downloaded' => ['danger', 'Download', 'fa-download'],
            'site settings updated by' => ['danger', 'Settings', 'fa-cog'],
            'has been edited by' => ['primary', 'Edit', 'fa-edit'],
            'has been saved' => ['primary', 'Saved', 'fa-save'],
            'settings updated' => ['primary', 'Settings', 'fa-cogs'],
            '[SQL ERROR]' => ['danger', 'SQL Error', 'fa-exclamation-triangle'],
            'Screenshot uploaded:' => ['success', 'Screen Upload', 'fa-image'],
            'Screenshot deleted:' => ['danger', 'Screen Delete', 'fa-trash'],
            'Screenshot updated:' => ['primary', 'Screen Edit', 'fa-edit'],
            'Screenshot error' => ['danger', 'Screen Error', 'fa-exclamation-circle'],
            'Mass Delete Screens:' => ['danger', 'Mass Screens Delete', 'fa-images'],
            'User' => ['danger', 'Comment Delete', 'fa-comment'],
            'for torrent #' => ['info', 'Torrent Screenshot', 'fa-film']
        ];
        $color = 'secondary';
        $badge = 'Log';
        $icon = 'fa-info-circle';
        foreach ($badge_map as $needle => [$clr, $lbl, $ico]) {
            if (stripos($action, $needle) !== false) {
                $color = $clr;
                $badge = $lbl;
                $icon = $ico;
                break;
            }
        }

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
                <i class="fas fa-trash me-1"></i> Delete Selected Site Logs
            </button>
        </div>
        </div></form>';
}








if ($total_count > $perpage) {
    echo '<div class="pagination">' . $multipage . '</div>';
}
echo '</div></div>';
echo '
<div class="modal fade" id="clearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear the site log? This cannot be undone.</p>
                <p class="text-warning"><i class="fas fa-exclamation-circle me-1"></i> Moderator logs will not be affected.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="index.php?act=log&action=combined_logs">
                    <input type="hidden" name="clear" value="yes">
                    <button type="submit" class="btn btn-danger">Clear Site Logs</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const filterForm = document.getElementById("filter-form");
    if (!filterForm) {
        console.error("Filter form not found");
        return;
    }
    const selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function() {
            document.querySelectorAll(".log-checkbox").forEach(cb => cb.checked = this.checked);
        });
    }
    const eventFilter = document.getElementById("event-filter");
    const dateFilter = document.getElementById("date-filter");
    const logTypeFilter = document.getElementById("log-type");
    [eventFilter, dateFilter, logTypeFilter].forEach(el => {
        if (el) {
            el.addEventListener("change", () => filterForm.submit());
        }
    });
    const searchInput = document.getElementById("search-input");
    let searchTimer;
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => filterForm.submit(), 800);
        });
    }
    const refreshBtn = document.getElementById("refresh-logs");
    if (refreshBtn) {
        refreshBtn.addEventListener("click", () => window.location.reload());
    }
});
</script>
</body>
</html>';
if (function_exists('stdfoot')) {
    stdfoot();
}
?>


