<?php

declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}


require_once INC_PATH . '/class_moderation.php';
$moderation = new Moderation();



// ======= Cleanup of stale data =======
$db->sql_query_prepared("DELETE FROM loginattempts WHERE banned='no' AND added < ?", [TIMENOW - DAY_IN_SECONDS]);
$CQueryCount++;


// ======= Peer cleanup =======
$deadtime = deadtime();
$db->sql_query_prepared("DELETE FROM peers WHERE last_action < ?", [$deadtime]);
$CQueryCount++;

$db->sql_query_prepared("UPDATE snatched SET seeder='no' WHERE seeder='yes' AND last_action < ?", [$deadtime]);
$CQueryCount++;

// ======= Hide stale torrents =======
$cut = TIMENOW - 2 * DAY_IN_SECONDS; // 2 days
$db->sql_query_prepared("UPDATE torrents SET visible='no' WHERE visible='yes' AND mtime < ?", [$cut]);
$CQueryCount++;

// ======= Session and search cleanup =======
$time_limits = [
    'sessionstime' => TIMENOW - DAY_IN_SECONDS,
    'threadreadcut' => TIMENOW - 7 * DAY_IN_SECONDS, // 7 days
    'searchlog' => TIMENOW - DAY_IN_SECONDS,
	'threads'       => TIMENOW
];



// Delete moved threads with expired redirects
$query = $db->sql_query_prepared("SELECT tid FROM threads WHERE deletetime != 0 AND deletetime < ?", [(int)$time_limits['threads']]);
$CQueryCount++;

while ($tid = $db->fetch_field($query, 'tid')) {
    $moderation->delete_thread((int)$tid);
}




// Remove old search log entries
$db->sql_query_prepared("DELETE FROM searchlog WHERE dateline < ?", [(int)$time_limits['searchlog']]);
$CQueryCount++;

// Clean up sessions older than 24 hours
$db->sql_query_prepared("DELETE FROM sessions WHERE time < ?", [(int)$time_limits['sessionstime']]);
$CQueryCount++;



// Remove old read-thread/forum markers
$db->sql_query_prepared("DELETE FROM threadsread WHERE dateline < ?", [(int)$time_limits['threadreadcut']]);
$CQueryCount++;
$db->sql_query_prepared("DELETE FROM forumsread WHERE dateline < ?", [(int)$time_limits['threadreadcut']]);
$CQueryCount++;

// ======= Moderator log pruning =======
if (!empty($config['log_pruning']['mod_logs']) && $config['log_pruning']['mod_logs'] > 0) {
    $cut = TIMENOW - $config['log_pruning']['mod_logs'] * DAY_IN_SECONDS;
    $db->sql_query_prepared("DELETE FROM moderatorlog WHERE dateline < ?", [$cut]);
    $CQueryCount++;
}

// ======= Torrent statistics update =======
$torrents_data = [];
$fields = ['comments', 'leechers', 'seeders', 'times_completed'];

// Gather peer counts
$query = $db->sql_query_prepared('SELECT torrent, seeder, COUNT(*) AS count FROM peers GROUP BY torrent, seeder');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $key = ($row['seeder'] == 'yes') ? 'seeders' : 'leechers';
    $torrents_data[$row['torrent']][$key] = (int)$row['count'];
}

// Gather completed download counts
$query = $db->sql_query_prepared('SELECT torrentid, COUNT(*) as count FROM snatched WHERE finished=? GROUP BY torrentid', ['yes']);
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrentid']]['times_completed'] = (int)$row['count'];
}

// Gather comment counts
$query = $db->sql_query_prepared('SELECT torrent, COUNT(*) AS count FROM comments GROUP BY torrent');
$CQueryCount++;
while ($row = $db->fetch_array($query)) {
    $torrents_data[$row['torrent']]['comments'] = (int)$row['count'];
}

// Bulk-update torrents
if (!empty($torrents_data)) {
    $torrent_ids = array_map('intval', array_keys($torrents_data));

    $placeholders = implode(',', array_fill(0, count($torrent_ids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT id, seeders, leechers, comments, times_completed FROM torrents WHERE id IN ({$placeholders})",
        $torrent_ids
    );
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
        foreach ($field_changes as $field => $id_to_value) {
            if (empty($id_to_value)) {
                continue;
            }

            $case_parts  = [];
            $case_params = [];
            foreach ($id_to_value as $id => $value) {
                $case_parts[]  = "WHEN ? THEN ?";
                $case_params[] = $id;
                $case_params[] = $value;
            }
            $case_sql = implode(' ', $case_parts);

            $affected_ids    = array_keys($id_to_value);
            $in_placeholders = implode(',', array_fill(0, count($affected_ids), '?'));

            $db->sql_query_prepared(
                "UPDATE torrents SET `{$field}` = CASE id {$case_sql} ELSE `{$field}` END WHERE id IN ({$in_placeholders})",
                [...$case_params, ...$affected_ids]
            );
            $CQueryCount++;
        }
    }
}

// ======= Memory cleanup =======
unset($torrents_data, $fields, $time_limits, $torrent_ids, $changed_ids, $field_changes);