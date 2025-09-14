<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

@ini_set('memory_limit', '20000M');
define('TT_VERSION', '2.1 by xam');





if (!isset($_GET['begin_optimization'])) {
    
	stdhead();
	echo '
   
    <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
    <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">

    <div class="container mt-3">
	<div class="card error-card">
        <div class="card-header22">
            <i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:2rem;"></i>
            <div>
                <h2 class="mb-0">Sanity Check!</h2>
                <p class="mb-0 opacity-75">Are you sure you want to optimize your tracker tables?</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" role="alert">
                <strong>Warning!</strong> Please backup your database first!
            </div>
            <a href="'.$_this_script_.'&begin_optimization=true" class="btn btn-danger">
                <i class="bi bi-play-fill me-1"></i> Click to Begin
            </a>
        </div>
    </div>
	</div>';
	stdfoot();
    exit; // останавливаем выполнение дальше
}









$torrents = array();
$users = array();

// Получаем ID торрентов
$Query = $db->simple_select("torrents", "id");
while ($torrent = $db->fetch_array($Query)) {
    $torrents[] = $torrent['id'];
}

// Получаем ID активных пользователей
$Query = $db->simple_select("users", "id", "enabled ='yes' AND ustatus ='confirmed'");
while ($user = $db->fetch_array($Query)) {
    $users[] = $user['id'];
}

if ((!$ValidTorrents = implode(',', $torrents) OR !$ValidUsers = implode(',', $users))) {
    stderr($lang->global['error'], 'There is no torrent/user found. You must have at least one torrent/user to use this tool.');
}

unset($torrents);
unset($users);

// Массив для логирования
$log = array();
$start_time = microtime(true);

// Функция для массового удаления
function delete_invalid_records($table, $condition, $id_field = 'id') {
    global $db, $log;
    
    $deleted_count = 0;
    $Query = $db->simple_select($table, $id_field, $condition);
    
    if ($db->num_rows($Query) > 0) {
        $ids_to_delete = array();
        while ($Delete = $db->fetch_array($Query)) {
            $ids_to_delete[] = $Delete[$id_field];
            $deleted_count++;
            
            // Удаляем пачками по 1000 записей для избежания слишком больших запросов
            if (count($ids_to_delete) >= 1000) {
                $ids_string = implode(',', $ids_to_delete);
                $db->delete_query($table, "$id_field IN ($ids_string)");
                $ids_to_delete = array();
            }
        }
        
        // Удаляем оставшиеся записи
        if (!empty($ids_to_delete)) {
            $ids_string = implode(',', $ids_to_delete);
            $db->delete_query($table, "$id_field IN ($ids_string)");
        }
        
        $log[] = "Deleted {$deleted_count} invalid records from {$table}";
    }
    
    return $deleted_count;
}

// Очистка таблиц
delete_invalid_records('announce_actions', "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})");
delete_invalid_records('bookmarks', "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})");
delete_invalid_records('cheat_attempts', "uid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})");
delete_invalid_records('comments', "user NOT IN ({$ValidUsers}) OR torrent NOT IN ({$ValidTorrents})");
delete_invalid_records('invites', "inviter NOT IN ({$ValidUsers})");
delete_invalid_records('notconnectablepmlog', "user NOT IN ({$ValidUsers})");
delete_invalid_records('peers', "userid NOT IN ({$ValidUsers}) OR torrent NOT IN ({$ValidTorrents})");
delete_invalid_records('reports', "addedby NOT IN ({$ValidUsers})");
delete_invalid_records('snatched', "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})");
delete_invalid_records('staffmessages', "sender NOT IN ({$ValidUsers})");
delete_invalid_records('ts_hit_and_run', "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})");
delete_invalid_records('ts_inactivity', "userid NOT IN ({$ValidUsers})", 'userid');
delete_invalid_records('ts_u_perm', "userid NOT IN ({$ValidUsers})", 'userid');

// Дополнительные таблицы из вашей базы
delete_invalid_records('ratings', "userid NOT IN ({$ValidUsers}) OR rating_id NOT IN ({$ValidTorrents})");
delete_invalid_records('comment_files', "user_id NOT IN ({$ValidUsers}) OR torrent_id NOT IN ({$ValidTorrents})");
delete_invalid_records('screenshots', "torrent_id NOT IN ({$ValidTorrents})");

// Очистка устаревших данных
$thirty_days_ago = TIMENOW - (30 * 86400);
$seven_days_ago = TIMENOW - (7 * 86400);

// Очистка старых сессий
$db->delete_query('sessions', "time < {$thirty_days_ago}");
$deleted = $db->affected_rows();
if ($deleted > 0) {
    $log[] = "Deleted {$deleted} old session records";
}

// Очистка старых поисковых запросов
$db->delete_query('searchlog', "dateline < {$seven_days_ago}");
$deleted = $db->affected_rows();
if ($deleted > 0) {
    $log[] = "Deleted {$deleted} old search log records";
}

// Очистка старых попыток входа
$db->delete_query('loginattempts', "added < {$thirty_days_ago}");
$deleted = $db->affected_rows();
if ($deleted > 0) {
    $log[] = "Deleted {$deleted} old login attempt records";
}

// Оптимизация таблиц
$tables_to_optimize = array(
    'announce_actions', 'bookmarks', 'cheat_attempts', 'comments', 'invites',
    'notconnectablepmlog', 'peers', 'reports', 'snatched', 'staffmessages',
    'ts_hit_and_run', 'ts_inactivity', 'ts_u_perm', 'ratings', 'comment_files',
    'screenshots', 'sessions', 'searchlog', 'loginattempts', 'iplog'
);

foreach ($tables_to_optimize as $table) {
    if ($db->table_exists($table)) {
        $db->sql_query("OPTIMIZE TABLE {$table}");
        $log[] = "Optimized table: {$table}";
    }
}









// Формирование отчета
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

stdhead();

echo '
<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">

<div class="container mt-3">
    <div class="card error-card">
        <div class="card-header22 success">
            <i class="bi bi-check-circle-fill me-2" style="font-size:2rem;"></i>
            <div>
                <h2 class="mb-0">Database Optimization Complete</h2>
                <p class="mb-0 opacity-75">Tracker tables successfully optimized!</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-success" role="alert">
                <strong>Success!</strong> Optimization finished in '.$execution_time.' seconds.
            </div>
            <p><strong>Actions Performed:</strong></p>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #28a745; padding: 10px;">
                <ul>';
foreach ($log as $log_entry) {
    echo '<li>'.$log_entry.'</li>';
}
echo '
                </ul>
            </div>
            <p><strong>Next Steps:</strong> Please run a full database optimization through your database management tool.</p>
        </div>
    </div>
</div>';

stdfoot();
exit;











?>