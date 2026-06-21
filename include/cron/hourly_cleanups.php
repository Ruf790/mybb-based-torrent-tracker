<?php

declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}



// ======= Cleanup of stale data =======
$db->sql_query("DELETE FROM loginattempts WHERE banned='no' AND added < '" . (TIMENOW - DAY_IN_SECONDS) . "'");
$CQueryCount++;


// ======= Peer cleanup =======
$deadtime = deadtime();
$db->sql_query("DELETE FROM peers WHERE last_action < " . $deadtime);
$CQueryCount++;

$db->sql_query("UPDATE snatched SET seeder='no' WHERE seeder='yes' AND last_action < " . $deadtime);
$CQueryCount++;

// ======= Hide stale torrents =======
$cut = TIMENOW - 2 * DAY_IN_SECONDS; // 2 days
$db->sql_query("UPDATE torrents SET visible='no' WHERE visible='yes' AND mtime < {$cut}");
$CQueryCount++;

// ======= Session and search cleanup =======
$time_limits = [
    'sessionstime' => TIMENOW - DAY_IN_SECONDS,
    'threadreadcut' => TIMENOW - 7 * DAY_IN_SECONDS, // 7 days
    'searchlog' => TIMENOW - DAY_IN_SECONDS
];

// Remove old search log entries
$db->delete_query("searchlog", "dateline < '" . (int)$time_limits['searchlog'] . "'");
$CQueryCount++;

// Clean up sessions older than 24 hours
$db->delete_query("sessions", "time < '" . (int)$time_limits['sessionstime'] . "'");
$CQueryCount++;



// Remove old read-thread/forum markers
$db->delete_query("threadsread", "dateline < '" . (int)$time_limits['threadreadcut'] . "'");
$CQueryCount++;
$db->delete_query("forumsread", "dateline < '" . (int)$time_limits['threadreadcut'] . "'");
$CQueryCount++;

// ======= Moderator log pruning =======
if (!empty($config['log_pruning']['mod_logs']) && $config['log_pruning']['mod_logs'] > 0) {
    $cut = TIMENOW - $config['log_pruning']['mod_logs'] * DAY_IN_SECONDS;
    $db->delete_query("moderatorlog", "dateline < '{$cut}'");
    $CQueryCount++;
}

// ======= Torrent statistics update =======
$torrents_data = [];
$fields = ['comments', 'leechers', 'seeders', 'times_completed'];

// Gather peer counts
$query = $db->sql_query('SELECT torrent, seeder, COUNT(*) AS count FROM peers GROUP BY torrent, seeder');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $key = ($row['seeder'] == 'yes') ? 'seeders' : 'leechers';
    $torrents_data[$row['torrent']][$key] = (int)$row['count'];
}

// Gather completed download counts
$query = $db->sql_query('SELECT torrentid, COUNT(*) as count FROM snatched WHERE finished=\'yes\' GROUP BY torrentid');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrentid']]['times_completed'] = (int)$row['count'];
}

// Gather comment counts
$query = $db->sql_query('SELECT torrent, COUNT(*) AS count FROM comments GROUP BY torrent');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrent']]['comments'] = (int)$row['count'];
}

// Bulk-update torrents
if (!empty($torrents_data)) {
    $torrent_ids = array_map('intval', array_keys($torrents_data));
    $ids_list    = implode(',', $torrent_ids);

    $query = $db->sql_query("SELECT id, seeders, leechers, comments, times_completed FROM torrents WHERE id IN ({$ids_list})");
    $CQueryCount++;

    $changed_ids   = [];
    $field_changes = []; // field => [id => new_value]

    while ($row = $db->fetch_array($query)) {
        $id = (int)$row['id'];
        $current_data = $torrents_data[$id] ?? [];

        $row_changed = false;
        foreach ($fields as $field) {
            $new_value = (int)($current_data[$field] ?? 0);
            $old_value = (int)$row[$field];

            if ($new_value !== $old_value) {
                $field_changes[$field][$id] = $new_value;
                $row_changed = true;
            }
        }

        if ($row_changed) {
            $changed_ids[] = $id;
        }
    }

    // One UPDATE per field via CASE WHEN, instead of one UPDATE per changed
    // row — significantly fewer queries at larger scale.
    if (!empty($changed_ids)) {
        $changed_ids_list = implode(',', $changed_ids);

        foreach ($field_changes as $field => $id_to_value) {
            if (empty($id_to_value)) {
                continue;
            }

            $case_parts = [];
            foreach ($id_to_value as $id => $value) {
                $case_parts[] = "WHEN {$id} THEN {$value}";
            }
            $case_sql = implode(' ', $case_parts);

            $affected_ids = implode(',', array_keys($id_to_value));

            $db->sql_query(
                "UPDATE torrents SET `{$field}` = CASE id {$case_sql} ELSE `{$field}` END WHERE id IN ({$affected_ids})"
            );
            $CQueryCount++;
        }
    }
}

// ======= Memory cleanup =======
unset($torrents_data, $fields, $time_limits, $torrent_ids, $changed_ids, $field_changes);