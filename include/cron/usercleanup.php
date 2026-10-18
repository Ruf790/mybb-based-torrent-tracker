<?php

declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}

$startTime = microtime(true);

$wrapped = $db->sql_query_prepared(
    'SELECT uid, oldgroup FROM banned WHERE lifted != 0 AND lifted < ?',
    [(int)TIMENOW]
);
++$CQueryCount;

$bannedUsers = [];
if ($wrapped && $wrapped->result) {
    while ($ban = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
        $bannedUsers[] = $ban;
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

if ($bannedUsers) {
    $userIds = [];

    foreach ($bannedUsers as $ban) {
        $uid = (int)$ban['uid'];
        if ($uid <= 0) {
            continue;
        }

        $userIds[] = $uid;

        // Прежняя группа; 0 (битая запись) - в Registered, иначе юзер остался бы без группы
        $oldgroup = (int)$ban['oldgroup'];
        if ($oldgroup <= 0) {
            $oldgroup = 2;
        }

        $db->sql_query_prepared(
            "UPDATE users SET usergroup=?, notifs=? WHERE id=?",
            [$oldgroup, '', $uid]
        );
        ++$CQueryCount;
    }

    if ($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $db->sql_query_prepared("DELETE FROM banned WHERE uid IN ({$placeholders})", $userIds);
        ++$CQueryCount;
    }

    $elapsed = round(microtime(true) - $startTime, 3);
    savelog('Expired bans removed for users: ' . implode(', ', $userIds) . " ({$elapsed}s)");
    ++$CQueryCount;
}
// Nothing logged when there were no expired bans