<?php

declare(strict_types=1);

/**
 * TS Special Edition / MyBB Ban Expiration Task
 * Fully compatible with PHP 8.4
 */



if (!defined('IN_CRON')) {
    exit();
}

$startTime = microtime(true);

// === Получаем истёкшие баны ===
$query = $db->simple_select(
    'banned',
    'uid, oldgroup, oldadditionalgroups, olddisplaygroup',
    'lifted != 0 AND lifted < ' . (int)TIMENOW
);
++$CQueryCount;

$bannedUsers = [];
while ($ban = $db->fetch_array($query)) {
    $bannedUsers[] = $ban;
}

if ($bannedUsers) {
    $userIds = [];

    foreach ($bannedUsers as $ban) {
        $uid = (int)$ban['uid'];
        if ($uid <= 0) {
            continue;
        }

        $userIds[] = $uid;

        $updatedUser = [
            'usergroup'         => (int)$ban['oldgroup'],
            'additionalgroups'  => $db->escape_string((string)$ban['oldadditionalgroups']),
            'displaygroup'      => (int)$ban['olddisplaygroup'],
            'notifs'            => '', 
        ];

        $db->update_query('users', $updatedUser, "id='{$uid}'");
        ++$CQueryCount;
    }

    // === Удаляем записи о банах ===
    if ($userIds) {
        $db->delete_query('banned', 'uid IN (' . implode(',', $userIds) . ')');
        ++$CQueryCount;
    }

    $elapsed = round(microtime(true) - $startTime, 3);
    savelog('Expired bans removed for users: ' . implode(', ', $userIds) . " ({$elapsed}s)");
    ++$CQueryCount;
} else {
    $elapsed = round(microtime(true) - $startTime, 3);
    savelog("No expired bans to remove ({$elapsed}s)");
    ++$CQueryCount;
}

