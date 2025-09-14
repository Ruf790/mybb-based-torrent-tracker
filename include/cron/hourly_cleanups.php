<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Optimized Cron]===============*/
/***********************************************/

if (!defined('IN_CRON')) {
    exit();
}



// ======= Очистка устаревших данных =======
$db->sql_query("DELETE FROM loginattempts WHERE banned='no' AND added < '" . (TIMENOW - DAY_IN_SECONDS) . "'");
$CQueryCount++;

$db->sql_query("DELETE FROM invites WHERE time_invited < '" . (TIMENOW - 2 * DAY_IN_SECONDS) . "'");
$CQueryCount++;

// ======= Очистка пиров =======
$deadtime = deadtime();
$db->sql_query("DELETE FROM peers WHERE last_action < " . $deadtime);
$CQueryCount++;

$db->sql_query("UPDATE snatched SET seeder='no' WHERE seeder='yes' AND last_action < " . $deadtime);
$CQueryCount++;

// ======= Скрытие старых торрентов =======
$cut = TIMENOW - 2 * DAY_IN_SECONDS; // 2 дня
$db->sql_query("UPDATE torrents SET visible='no' WHERE visible='yes' AND mtime < {$cut} AND ts_external = 'no'");
$CQueryCount++;

// ======= Очистка сессий и поиска =======
$time_limits = [
    'sessionstime' => TIMENOW - DAY_IN_SECONDS,
    'threadreadcut' => TIMENOW - 7 * DAY_IN_SECONDS, // 7 дней
    'searchlog' => TIMENOW - DAY_IN_SECONDS
];

// Удаление старых поисков
$db->delete_query("searchlog", "dateline < '" . (int)$time_limits['searchlog'] . "'");
$CQueryCount++;

// Очистка сессий старше 24 часов
$db->delete_query("sessions", "time < '" . (int)$time_limits['sessionstime'] . "'");
$CQueryCount++;



// Удаление старых прочитанных тем
$db->delete_query("tsf_threadsread", "dateline < '" . (int)$time_limits['threadreadcut'] . "'");
$CQueryCount++;
$db->delete_query("tsf_forumsread", "dateline < '" . (int)$time_limits['threadreadcut'] . "'");
$CQueryCount++;

// ======= Очистка логов модераторов =======
if (!empty($config['log_pruning']['mod_logs']) && $config['log_pruning']['mod_logs'] > 0) {
    $cut = TIMENOW - $config['log_pruning']['mod_logs'] * DAY_IN_SECONDS;
    $db->delete_query("moderatorlog", "dateline < '{$cut}'");
    $CQueryCount++;
}

// ======= Обновление статистики торрентов =======
$torrents_data = [];
$fields = ['comments', 'leechers', 'seeders', 'times_completed'];

// Сбор данных о пирах
$query = $db->sql_query('SELECT torrent, seeder, COUNT(*) AS count FROM peers GROUP BY torrent, seeder');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $key = ($row['seeder'] == 'yes') ? 'seeders' : 'leechers';
    $torrents_data[$row['torrent']][$key] = (int)$row['count'];
}

// Сбор данных о завершенных скачиваниях
$query = $db->sql_query('SELECT torrentid, COUNT(*) as count FROM snatched WHERE finished=\'yes\' GROUP BY torrentid');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrentid']]['times_completed'] = (int)$row['count'];
}

// Сбор данных о комментариях
$query = $db->sql_query('SELECT torrent, COUNT(*) AS count FROM comments GROUP BY torrent');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrent']]['comments'] = (int)$row['count'];
}

// Массовое обновление торрентов
if (!empty($torrents_data)) {
    $query = $db->sql_query('SELECT id, seeders, leechers, comments, times_completed FROM torrents WHERE ts_external = \'no\'');
    $CQueryCount++;
    
    $updates = [];
    while ($row = $db->fetch_array($query)) {
        $id = (int)$row['id'];
        $current_data = $torrents_data[$id] ?? [];
        
        $update_fields = [];
        foreach ($fields as $field) {
            $new_value = (int)($current_data[$field] ?? 0);
            $old_value = (int)$row[$field];
            
            if ($new_value !== $old_value) {
                $update_fields[] = "`$field` = $new_value";
            }
        }
        
        if (!empty($update_fields)) {
            $updates[] = "UPDATE torrents SET " . implode(', ', $update_fields) . " WHERE id = $id";
        }
    }
    
    // Выполняем все updates одним запросом (если поддерживается)
    if (!empty($updates)) {
        foreach ($updates as $update_sql) {
            $db->sql_query($update_sql);
            $CQueryCount++;
        }
    }
}

// ======= Очистка памяти =======
unset($torrents_data, $fields, $time_limits, $updates, $update_fields);
?>