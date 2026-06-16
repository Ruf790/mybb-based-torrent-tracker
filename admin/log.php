<?php

declare(strict_types=1);


require_once(INC_PATH . '/class_parser.php');
require_once(INC_PATH . '/functions_multipage.php');



if (!defined('STAFF_PANEL')) 
{
    define('STAFF_PANEL_TSSEv56', true);
    echo '<div class="alert alert-warning">Warning: STAFF_PANEL_TSSEv56 was not defined. Defined for testing.</div>';
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





// Admin log language strings
$admin_log_lang = [
    // Forum Management
    'admin_log_forum_management_add'              => 'Added forum #{1} ({2})',
    'admin_log_forum_management_edit'             => 'Edited forum #{1} ({2})',
    'admin_log_forum_management_delete'           => 'Deleted forum #{1} ({2})',
    'admin_log_forum_management_permissions'      => 'Edited group permissions for forum #{1} ({2})',
    'admin_log_forum_management_quickpermissions' => 'Updated quick permissions for forum #{2} ({3})',
    'admin_log_forum_management_addmod'           => 'Added moderator #{2} ({3}) to forum #{4} ({5})',
    'admin_log_forum_management_editmod'          => 'Edited moderator #{3} ({4}) on forum #{1} ({2})',
    'admin_log_forum_management_deletemod'        => 'Deleted moderator #{1} ({2}) from forum #{3} ({4})',
    'admin_log_forum_management_copy'             => 'Copied settings from forum #{1} ({2}) to forum #{3} ({4})',
    'admin_log_forum_management_orders'           => 'Updated root forum orders',
    'admin_log_forum_management_orders_sub'       => 'Updated forum orders within forum #{2} ({3})',

    // Users
    'admin_log_user_users_add'                    => 'Created user #{1} ({2})',
    'admin_log_user_users_edit'                   => 'Edited user #{1} ({2})',
    'admin_log_user_users_delete'                 => 'Deleted user #{1} ({2})',
    'admin_log_user_users_merge'                  => 'Merged user #{1} ({2}) into user #{3} ({4})',
    'admin_log_user_users_activate_user'          => 'Activated user #{1} ({2})',
    'admin_log_user_users_inline_delete'          => 'Deleted {1} user(s)',
    'admin_log_user_users_inline_activated'       => 'Activated {1} user(s)',
    'admin_log_user_users_inline_banned_perm'     => 'Banned {1} user(s) permanently',
    'admin_log_user_users_inline_banned_temp'     => 'Banned {1} user(s) until {2}',
    'admin_log_user_users_inline_lift'            => 'Lifted {1} user(s) bans',

    // Banning
    'admin_log_user_banning_add_permanent'        => 'Banned user #{1} ({2}) permanently',
    'admin_log_user_banning_add_temporary'        => 'Banned user #{1} ({2}) until {3}',
    'admin_log_user_banning_lift'                 => 'Lifted ban for user #{1} ({2})',
    'admin_log_user_banning_edit'                 => 'Edited ban for user #{1} ({2})',

    // Groups
    'admin_log_user_groups_add'                   => 'Added usergroup #{1} ({2})',
    'admin_log_user_groups_edit'                  => 'Edited usergroup #{1} ({2})',
    'admin_log_user_groups_delete'                => 'Deleted usergroup #{1} ({2})',

    // Settings
    'admin_log_config_settings_change'            => 'Changed board settings',
    'admin_log_config_settings_add'               => 'Added setting #{1} ({2})',
    'admin_log_config_settings_edit'              => 'Edited setting #{1} ({2})',
    'admin_log_config_settings_delete'            => 'Deleted setting #{1} ({2})',

    // Plugins
    'admin_log_config_plugins_activate'           => 'Activated plugin: {1}',
    'admin_log_config_plugins_activate_install'   => 'Activated and installed plugin: {1}',
    'admin_log_config_plugins_deactivate'         => 'Deactivated plugin: {1}',
    'admin_log_config_plugins_deactivate_uninstall' => 'Deactivated and uninstalled plugin: {1}',

    // Templates
    'admin_log_style_templates_add_set'           => 'Added template set #{1} ({2})',
    'admin_log_style_templates_edit_set'          => 'Edited template set #{1} ({2})',
    'admin_log_style_templates_delete_set'        => 'Deleted template set #{1} ({2})',
    'admin_log_style_templates_edit_template'     => 'Edited template #{1} ({2}) from set #{3} ({4})',
    'admin_log_style_templates_edit_template_global' => 'Edited template #{1} ({2}) from global set',

    // Tools
    'admin_log_tools_adminlog_prune'              => 'Pruned {4} admin logs older than {1} days',
    'admin_log_tools_modlog_prune'                => 'Pruned {4} mod logs older than {1} days',
    'admin_log_tools_backupdb_backup'             => 'Created a backup: {2}',
    'admin_log_tools_backupdb_backup_download'    => 'Downloaded a backup of the database',
    'admin_log_tools_backupdb_delete'             => 'Deleted a backup: {1}',
    'admin_log_tools_cache_rebuild'               => 'Rebuilt cache ({1})',
    'admin_log_tools_cache_rebuild_all'           => 'Rebuilt & reloaded all caches',
    'admin_log_tools_optimizedb_'                 => 'Optimized database tables: {1}',
    'admin_log_tools_recount_rebuild_stats'       => 'Recounted and rebuilt statistics',
    'admin_log_tools_recount_rebuild_forum'       => 'Recounted and rebuilt forum counters',
    'admin_log_tools_recount_rebuild_userposts'   => 'Recounted and rebuilt user post counts',
    'admin_log_admin_locked_out'                  => 'Admin login attempt for user #{1} ({2}) locked out',
	
	
	
	'admin_log_config_banning_add_ip' => "Added IP ban #{1} ({2})",
    'admin_log_config_banning_add_username' => "Added disallowed username #{1} ({2})",
    'admin_log_config_banning_add_email' => "Added disallowed email #{1} ({2})",
    'admin_log_config_banning_delete_ip' => "Removed IP ban #{1} ({2})",
    'admin_log_config_banning_delete_username' => "Removed disallowed username #{1} ({2})",
    'admin_log_config_banning_delete_email' => "Removed disallowed email #{1} ({2})",
	
	
	'admin_log_forum_attachments_delete_post' => "Deleted attachment #{1} ({2}) from post #{3}",
'admin_log_forum_attachments_delete' => "Deleted attachment #{1} ({2})",
'admin_log_forum_attachments_delete_orphans' => "Deleted orphaned attachments",

'admin_log_user_awaiting_activation_activate_activated' => "Activated {2} user account(s)",
'admin_log_user_awaiting_activation_activate_deleted' => "Deleted {2} user account(s)",

'awaiting_activation' => "Awaiting Activation",
'awaiting_activation_desc' => "Here you can manage users who are awaiting activation. Please note any user who is awaiting email activation will not need to confirm their email if they are activated here.",

	
	
	
	
	
	
];



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
            $string = str_replace('#{' . ($k + 1) . '}', '#' . htmlspecialchars((string)$v), $string);
        }
        // Убираем незаполненные плейсхолдеры
        $string = preg_replace('/\#?\{\d+\}/', '', $string);
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

























$searchstr = isset($_GET['query']) ? trim($db->escape_string($_GET['query'])) : '';
$event_filter = isset($_GET['event_filter']) ? trim($db->escape_string($_GET['event_filter'])) : 'all';
$date_filter = isset($_GET['date_filter']) ? trim($db->escape_string($_GET['date_filter'])) : '';
$log_type = isset($_GET['log_type']) ? trim($db->escape_string($_GET['log_type'])) : 'both';
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;

$filter_params = [
    'query' => $searchstr,
    'event_filter' => $event_filter,
    'date_filter' => $date_filter,
    'log_type' => $log_type
];

$where_conditions_sitelog = [];
$where_conditions_modlog = [];


$where_conditions_adminlog = [];
if ($searchstr !== '') {
    $search_esc = "%" . $db->escape_string($searchstr) . "%";
    $where_conditions_adminlog[] = "(a.action LIKE '$search_esc' OR a.module LIKE '$search_esc' OR a.data LIKE '$search_esc')";
}
if ($event_filter !== 'all') {
    $event_esc = "%" . $db->escape_string($event_filter) . "%";
    $where_conditions_adminlog[] = "(a.action LIKE '$event_esc' OR a.module LIKE '$event_esc')";
}
if ($date_filter !== '') {
    $date_esc = $db->escape_string($date_filter);
    $where_conditions_adminlog[] = "DATE(FROM_UNIXTIME(a.dateline)) = '$date_esc'";
}
$where_adminlog = !empty($where_conditions_adminlog) 
    ? "WHERE " . implode(" AND ", $where_conditions_adminlog) 
    : "";





if ($searchstr !== '') 
{
    $search_esc = "%" . $db->escape_string($searchstr) . "%";
    $where_conditions_sitelog[] = "s.txt LIKE '$search_esc'";
    $where_conditions_modlog[] = "(m.action LIKE '$search_esc' OR m.data LIKE '$search_esc')";
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
$count_union = [];
if ($log_type === 'both' || $log_type === 'site') {
    $count_union[] = "SELECT COUNT(*) as count FROM sitelog s $where_sitelog";
}
if ($log_type === 'both' || $log_type === 'moderator') {
    $count_union[] = "SELECT COUNT(*) as count FROM moderatorlog m $where_modlog";
}
if ($log_type === 'both' || $log_type === 'admin') {
    $count_union[] = "SELECT COUNT(*) as count FROM adminlog a $where_adminlog";
}
$count_sql = "SELECT SUM(count) as total FROM (" . implode(" UNION ALL ", $count_union) . ") as counts";
$result = $db->sql_query($count_sql);
if (!$result) {
    die('Error: Count query failed: ' . $db->error());
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
    $user_ids_str = implode(',', array_keys($user_ids_from_data));
    $result = $db->sql_query("SELECT id, username, usergroup FROM users WHERE id IN ($user_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $users_from_data[$row['id']] = $row;
    }
}

if (!empty($thread_ids)) {
    $thread_ids_str = implode(',', array_keys($thread_ids));
    $result = $db->sql_query("SELECT tid, subject FROM threads WHERE tid IN ($thread_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $threads_data[$row['tid']] = $row;
    }
}

if (!empty($forum_ids)) {
    $forum_ids_str = implode(',', array_keys($forum_ids));
    $result = $db->sql_query("SELECT fid, name FROM forums WHERE fid IN ($forum_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $forums_data[$row['fid']] = $row;
    }
}

if (!empty($post_ids)) {
    $post_ids_str = implode(',', array_keys($post_ids));
    $result = $db->sql_query("SELECT pid, subject FROM posts WHERE pid IN ($post_ids_str)");
    while ($row = $db->fetch_array($result)) {
        $posts_data[$row['pid']] = $row;
    }
}

if (!empty($announcement_ids)) {
    $announcement_ids_str = implode(',', array_keys($announcement_ids));
    $result = $db->sql_query("SELECT id, subject FROM announcements WHERE id IN ($announcement_ids_str)");
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
    <title>Combined Log System</title>
   
   
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear']) && $_POST['clear'] === 'yes' && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        $result = $db->sql_query('TRUNCATE TABLE sitelog');
        if ($result) {
            flash_message('Site log table cleared!', 'success');
        } else {
            flash_message('Failed to clear site log: ' . $db->error(), 'danger');
        }
        admin_redirect('index.php?act=log&action=combined_logs');

    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['logid']) && isset($usergroups['cansettingspanel']) && $usergroups['cansettingspanel'] == '1') {
        $site_log_ids = array_filter((array)$_POST['logid'], function($id) {
            return is_numeric($id) && $id > 0;
        });
        if (!empty($site_log_ids)) {
            $ids = implode(',', array_map('intval', $site_log_ids));
            $result = $db->sql_query("DELETE FROM sitelog WHERE id IN ($ids)");
            if ($result) {
                flash_message('Deleted ' . $db->affected_rows() . ' site log(s)!', 'warning');
            } else {
                flash_message('Delete failed: ' . $db->error(), 'danger');
            }
        } else {
            flash_message('No valid log IDs selected.', 'warning');
        }
        admin_redirect('index.php?act=log&action=combined_logs');
    }
}

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
				
				<button class="btn btn-secondary btn-clear" id="export-csv">
                    <i class="fas fa-download me-1"></i> Export CSV
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
						<option value="admin"     ' . ($log_type == 'admin'     ? 'selected' : '') . '>Admin Only</option>
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
						
						<option value="Delete Comment" ' . ($event_filter == 'Delete Comment' ? 'selected' : '') . '>Delete Comment</option>
						<option value="Torrent Upload" ' . ($event_filter == 'Torrent Upload' ? 'selected' : '') . '>Torrent Upload</option>
						
                    </select>
                </div>
                
				<div class="filter-group">
    <label for="date-filter"><i class="fas fa-calendar me-1"></i>Date</label>
    <input type="text" class="form-control" id="date-filter" name="date_filter"
           value="'.htmlspecialchars($date_filter).'" placeholder="Select a date">
</div>


<link rel="stylesheet" href="'.$BASEURL.'/admin/templates/flatpickr.min.css">
<script src="'.$BASEURL.'/admin/scripts/flatpickr.js"></script>
<script>
    flatpickr("#date-filter", {
		dateFormat: "Y-m-d",
        allowInput: true,
        defaultDate: "'.htmlspecialchars($date_filter).'"
    });
</script>
				
				
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>';

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

if ($total_count > $perpage) 
{
    echo '<div class="pagination">' . $multipage . '</div>';
}









if (count($logs) == 0) {
    echo '
    <div class="text-center py-5">
        <i class="fas fa-list fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">No logs found matching your criteria.</h5>
    </div>';
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
        $entry_type = $arr['log_type'];
        $id = $arr['id'];
        $timestamp = isset($arr['timestamp']) ? (int)$arr['timestamp'] : time();
        $date = function_exists('my_date') ? my_date('Y-m-d', $timestamp) : date('Y-m-d', $timestamp);
        $time = function_exists('my_date') && isset($timeformat) ? my_date($timeformat, $timestamp) : date('H:i:s', $timestamp);
        $is_new = (time() - $timestamp <= 120);
        $row_class = $is_new ? 'log-entry-new' : '';
        
		$type_badge = match($entry_type) {
    'site'      => '<span class="badge bg-primary badge-site log-type-badge"><i class="fas fa-globe me-1"></i> Site</span>',
    'moderator' => '<span class="badge bg-success badge-moderator log-type-badge"><i class="fas fa-user-shield me-1"></i> Mod</span>',
    'admin'     => '<span class="badge bg-danger badge-log log-type-badge"><i class="fas fa-crown"></i> Admin</span>',
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
              . '<i class="fas fa-sync-alt me-1"></i> Cron'
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
                       . '<i class="fas fa-robot me-1"></i>Cron'
                       . '</span>';
        }
    }

    // Category badge
    if (!empty($arr['category'])) {
        $cat_colors = [
            'screenshot' => ['#e8f4fd', '#1a7fc1'],
            'torrent'    => ['#e8f9f0', '#1a8a4a'],
            'cron'       => ['#f4f0fd', '#6a3fc1'],
            'error'      => ['#fdecea', '#c0392b'],
            'security'   => ['#fdecea', '#c0392b'],
            'settings'   => ['#fff8e6', '#c07800'],
            'ban'        => ['#fdecea', '#c0392b'],
            'deletion'   => ['#fdecea', '#c0392b'],
            'mail'       => ['#e8f4fd', '#1a7fc1'],
            'warning'    => ['#fff8e6', '#c07800'],
            'general'    => ['#f0f2fa', '#8a8ea8'],
        ];
        $cat = $arr['category'];
        [$bg, $cl] = $cat_colors[$cat] ?? ['#f0f2fa', '#8a8ea8'];
        $information = '<span style="background:' . $bg . ';color:' . $cl . ';padding:2px 8px;'
                     . 'border-radius:6px;font-size:11px;font-weight:700">'
                     . htmlspecialchars(ucfirst($cat))
                     . '</span>';
    }
}

  
  
  
  
  
  
  
  
  
  
  
  
		





elseif ($entry_type === 'admin') {
    $username22  = $arr['username'] ? format_name($arr['username'], $arr['usergroup']) : 'N/A (Deleted)';
    $username    = '<a href="' . $BASEURL . '/' . get_profile_link($arr['uid']) . '">' . $username22 . '</a>';
    $ipaddress   = $arr['ipaddress'] ? my_inet_ntop($db->unescape_binary($arr['ipaddress'])) : 'N/A';
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
    $action = format_admin_log($logitem, $admin_log_lang);

    // Module badge
    $module_raw = str_replace('/', '-', $arr['module'] ?? '');
    $module_labels = [
        'forum-management' => 'Forums',
        'user-management'  => 'Users',
        'config-settings'  => 'Settings',
        'config-plugins'   => 'Plugins',
        'style-templates'  => 'Templates',
        'style-themes'     => 'Themes',
        'tools-adminlog'   => 'Admin Log',
        'tools-modlog'     => 'Mod Log',
        'tools-backupdb'   => 'Backup',
        'tools-tasks'      => 'Tasks',
        'tools-cache'      => 'Cache',
        'user-groups'      => 'Groups',
        'user-banning'     => 'Banning',
    ];
    $module_label = $module_labels[$module_raw] 
        ?? str_replace('-', ' ', ucwords($module_raw));

    if ($module_label) {
        $action = '<span style="background:#fff0e6;color:#c0392b;padding:2px 8px;'
                . 'border-radius:6px;font-size:11px;font-weight:700;margin-right:6px">'
                . htmlspecialchars($module_label)
                . '</span> ' . $action;
    }
}





		
		
		
		else {
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
          
            'task successfully ran' => ['success', 'Task', 'fa-tasks'],
            'was deleted by' => ['danger', 'Deletion', 'fa-trash'],
            'has been deleted by' => ['danger', 'Deletion', 'fa-trash'],
            'Attempt' => ['danger', 'Security', 'fa-shield-alt'],
            'unwanted' => ['danger', 'Spam', 'fa-ban'],
            'has downloaded' => ['danger', 'Download', 'fa-download'],
            'site settings updated by' => ['danger', 'Settings', 'fa-cog'],
            'has been edited by' => ['primary', 'Edit', 'fa-edit'],
            'has been saved' => ['primary', 'Saved', 'fa-save'],
		    
			'copied' => ['primary', 'Comment Copy', 'fa-copy'],
			
			'copied' => ['primary', 'Copied settings', 'fa-copy'],
			
			
			'Threads Deleted' => ['danger', 'Threads Del', 'fa-trash'], 
			
			
			'Database check completed' => ['success', 'DB Check', 'fa-database'],
			
			
			'Deleted Selective Posts' => ['danger', 'Del Posts', 'fa-object-group'],
			
			
			'Rebuilt cache' => ['success', 'Cache Rebuild', 'fa-arrows-rotate'],
			
			'Deleted attachment' => ['danger', 'Attachment Deleted', 'fa-trash-alt'],
			
			
			'moved' => ['warning', 'Comment Move', 'fa-arrows-alt'],
			
			'Added usergroup' => ['success', 'User Group Added', 'fa-users'],
			
			'Edited usergroup' => ['warning', 'User Group Edited', 'fa-pencil'],
			
			
			'Database backup completed successfully' => ['success', 'Database Backup Completed', 'fa-database'],
			
			'Created a backup' => ['success', 'Database Backup Created', 'fa-database'],
			
			'done | no active seeders' => ['success', 'Seed Bonus Awarded', 'fa-check-circle'],
	
			
			
           	'send mail queue' => ['info', 'Mail Queue', 'fa-paper-plane'],
            'Mass Comment Delete' => ['danger', 'Mass Comment Delete', 'fa-comment'],
			
			
			
			
			'Added disallowed'  => ['danger',  'Blacklist Add', 'fa-ban'],
            'Removed disallowed'=> ['success', 'Blacklist Del', 'fa-check-circle'],
            'Added IP ban'      => ['danger',  'IP Ban',        'fa-shield-alt'],
            'Removed IP ban'    => ['success', 'IP Unban',      'fa-shield-alt'],
			
			
			'DB Optimized SUCCESS' => ['success', 'DB Optimized', 'fa-database'],
			
			
			
			
			
			
			
			
			
			'Added forum'       => ['success', 'Forum Add',   'fa-plus'],
'Edited forum'      => ['primary', 'Forum Edit',  'fa-edit'],
'Deleted forum'     => ['danger',  'Forum Del',   'fa-trash'],
'Updated quick'     => ['warning', 'Permissions', 'fa-key'],
'Edited group perm' => ['warning', 'Permissions', 'fa-key'],
'Added moderator'   => ['success', 'Add Mod',     'fa-user-plus'],

			
			
'Mass Invite' => ['success', 'Mass Invite', 'fa-envelope-open-text'],

			
			
			
			'Seedbonus cron: Система отключена' => ['danger', 'Seedbonus', 'fa-ban'],
'Seedbonus cron: start' => ['info', 'Seedbonus Start', 'fa-play'],
'Seedbonus cron: done | no active seeders' => ['secondary', 'Seedbonus Done', 'fa-stop'],
'WARNING: User' => ['warning', 'Seedbonus Warning', 'fa-exclamation-triangle'],
'Seedbonus cron: done | users=' => ['success', 'Seedbonus Done', 'fa-check'],

'GB upload added to' => ['success', 'Upload Added', 'fa-upload'],


'GB upload added to user' => ['success', 'Upload Added', 'fa-upload'],


	'optimized successfully' => ['success', 'DB Optimized', 'fa-database'],
	
	'Promoted users' => ['success', 'Promotion', 'fa-level-up-alt'],

'Demoted users' => ['warning', 'Demotion', 'fa-level-down-alt'],


'Leech-warned users' => ['warning', 'Leech Warning', 'fa-exclamation-triangle'],


'Finished torrent promotion expiration cleanup' => ['success', 'Promotion Cleanup', 'fa-broom'],


'Expired Free Leech promotions' => ['success', 'FreeLeech Expired', 'fa-hourglass-end'],
	

	'Expired 50% + 2X promotions' => ['success', '50%+2X Expired', 'fa-hourglass-end'],
'Expired 30% Leech promotions' => ['success', '30% Leech Expired', 'fa-hourglass-end'],


'Starting torrent promotion expiration cleanup' => ['info', 'Promo Cleanup Start', 'fa-play-circle'],

'Expired 2X Upload promotions' => ['secondary', '2X Upload Expired', 'fa-stop-circle'],
'Expired Free + 2X promotions' => ['secondary', 'Free+2X Expired', 'fa-stop-circle'],

'Expired 50% Leech promotions' => ['secondary', '50% Leech Expired', 'fa-stop-circle'],


'Torrents no longer on promotion' => ['secondary', 'Promotion Expired', 'fa-minus-circle'],
    'Torrents promotion changed' => ['success', 'Promotion Changed', 'fa-star'],
			
			
			
			
            'settings updated' => ['primary', 'Settings', 'fa-cogs'],
            '[SQL ERROR]' => ['danger', 'SQL Error', 'fa-exclamation-triangle'],
            'Screenshot uploaded:' => ['success', 'Screen Upload', 'fa-image'],
            'Screenshot deleted:' => ['danger', 'Screen Delete', 'fa-trash'],
            'Screenshot updated:' => ['primary', 'Screen Edit', 'fa-edit'],
            'Screenshot error' => ['danger', 'Screen Error', 'fa-exclamation-circle'],
            'Mass Delete Screens:' => ['danger', 'Mass Screens Delete', 'fa-images'],
            //'User' => ['danger', 'Comment Delete', 'fa-comment'],
			
			'Lifted ban for user' => ['success', 'Unban', 'fa-user-check'],
			
'deleted a comment (CID'   => ['danger', 'Comment Delete', 'fa-comment'],
'deleted comments'         => ['danger', 'Comment Delete', 'fa-comment'],
'Mass Comment Delete'      => ['danger', 'Mass Del Comments', 'fa-comment-slash'],
			
			
			
			
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
                <i class="fas fa-trash me-1"></i> Delete2 Selected Site Logs
            </button>
        </div>
        </div></form>';
}








if ($total_count > $perpage) {
    echo '<div class="pagination">' . $multipage . '</div>';
}
echo '</div></div>';



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
                <h4 style="color:#fff;margin:0;font-weight:700;font-size:20px">Clear Site Logs</h4>
                <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:14px">This action is permanent</p>
            </div>

            <!-- Тело -->
            <div style="padding:28px;text-align:center;background:#fff">
                <p style="color:#5a5c69;font-size:15px;margin:0 0 12px;line-height:1.6">
                    Are you sure you want to <strong style="color:#e74a3b">clear all site logs</strong>?<br>This cannot be undone.
                </p>
                <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;text-align:left">
                    <i class="fas fa-exclamation-circle" style="color:#f6c23e;font-size:18px;flex-shrink:0"></i>
                    <span style="color:#856404;font-size:13px">Moderator logs will <strong>not</strong> be affected by this action.</span>
                </div>
            </div>

            <!-- Футер -->
            <div style="padding:0 28px 28px;background:#fff;display:flex;gap:12px">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    style="flex:1;border-radius:10px;padding:11px;font-weight:600;border:2px solid #e3e6f0;background:#fff;color:#5a5c69">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <form method="post" action="index.php?act=log&action=combined_logs" style="flex:1;margin:0">
                    <input type="hidden" name="clear" value="yes">
                    <button type="submit"
                        style="width:100%;border-radius:10px;padding:11px;font-weight:600;border:none;background:linear-gradient(135deg,#e74a3b,#c0392b);color:#fff;cursor:pointer">
                        <i class="fas fa-trash-alt me-1"></i> Clear Logs
                    </button>
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
HTML;
?>
<script>
document.getElementById("export-csv")?.addEventListener("click", function() {
    const rows = [["Type","Username","Date","Time","Action","IP"]];
    document.querySelectorAll("tbody tr").forEach(tr => {
        const cells = tr.querySelectorAll("td");
        if (cells.length >= 7) {
            rows.push([
                cells[0].innerText.trim(),
                cells[1].innerText.trim(),
                cells[2].innerText.trim(),
                cells[3].innerText.trim(),
                cells[4].innerText.trim(),
                cells[6].innerText.trim(),
            ]);
        }
    });
    const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join("\n");
    const a = document.createElement("a");
    a.href = "data:text/csv;charset=utf-8," + encodeURIComponent(csv);
    a.download = "logs_" + new Date().toISOString().slice(0,10) + ".csv";
    a.click();
});



function updateDeleteBtn() {
    const count = document.querySelectorAll('.log-checkbox:checked').length;
    const btn = document.querySelector('#logs-form [type="submit"].btn-danger');
    if (btn) {
        btn.innerHTML = count > 0
            ? '<i class="fas fa-trash me-1"></i> Delete Selected (' + count + ')'
            : '<i class="fas fa-trash me-1"></i> Delete Selected';
        btn.disabled = count === 0;
    }
}

function showDeleteModal(count, onConfirm) {
    const existing = document.getElementById('deleteConfirmModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'deleteConfirmModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;animation:fadeInBg 0.2s ease';

    modal.innerHTML =
        '<style>' +
        '@keyframes fadeInBg{from{opacity:0}to{opacity:1}}' +
        '@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}' +
        '</style>' +
        '<div style="background:#fff;border-radius:16px;padding:0;width:420px;max-width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp 0.25s ease;overflow:hidden">' +
            '<div style="background:linear-gradient(135deg,#e74a3b,#c0392b);padding:24px 28px 20px;text-align:center">' +
                '<div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">' +
                    '<i class="fas fa-trash" style="color:#fff;font-size:22px"></i>' +
                '</div>' +
                '<h5 style="color:#fff;margin:0;font-size:18px;font-weight:700">Confirm Deletion</h5>' +
            '</div>' +
            '<div style="padding:24px 28px;text-align:center">' +
                '<p style="color:#5a5c69;margin:0 0 6px;font-size:15px">You are about to delete</p>' +
                '<p style="color:#e74a3b;font-size:28px;font-weight:700;margin:0 0 6px">' + count + '</p>' +
                '<p style="color:#5a5c69;margin:0 0 20px;font-size:15px">log entr' + (count === 1 ? 'y' : 'ies') + '. This cannot be undone.</p>' +
                '<div style="display:flex;gap:12px;justify-content:center">' +
                    '<button id="modalCancel" style="flex:1;padding:11px;border:2px solid #e3e6f0;background:#fff;border-radius:10px;color:#5a5c69;font-size:14px;font-weight:600;cursor:pointer">Cancel</button>' +
                    '<button id="modalConfirm" style="flex:1;padding:11px;border:none;background:linear-gradient(135deg,#e74a3b,#c0392b);border-radius:10px;color:#fff;font-size:14px;font-weight:600;cursor:pointer">' +
                        '<i class="fas fa-trash me-1"></i> Delete ' + count + ' log' + (count === 1 ? '' : 's') +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    document.body.appendChild(modal);

    document.getElementById('modalCancel').onclick = function() { modal.remove(); };
    document.getElementById('modalConfirm').onclick = function() { modal.remove(); onConfirm(); };
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
}

document.addEventListener('DOMContentLoaded', function() {
    updateDeleteBtn();

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('log-checkbox')) {
            updateDeleteBtn();
        }
    });

    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.log-checkbox').forEach(cb => cb.checked = this.checked);
            updateDeleteBtn();
        });
    }

    const logsForm = document.getElementById('logs-form');
    if (logsForm) {
        logsForm.addEventListener('submit', function(e) {
            const count = document.querySelectorAll('.log-checkbox:checked').length;
            if (count === 0) { e.preventDefault(); return; }
            e.preventDefault();
            showDeleteModal(count, function() {
                logsForm.submit();
            });
        });
    }
});
</script>
</body>
</html>
<?php


stdfoot();

?>


