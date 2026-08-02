<?php

declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}

require INC_PATH . '/functions_pm.php';




// ======= Вспомогательные функции =======

// Генерация плейсхолдеров для IN(...) под sql_query_prepared
function build_in_placeholders(array $ids): string {
    if (empty($ids)) return '0=1'; // Защита от пустого массива, используется как самостоятельное условие
    return implode(',', array_fill(0, count($ids), '?'));
}

// Отправка PM нескольким пользователям
function send_bulk_pm(array $user_ids, string $subject, string $message) {
    global $CQueryCount;
    foreach ($user_ids as $uid) {
        $result = send_pm([
            'subject' => $subject,
            'message' => $message,
            'touid' => (int)$uid,
            'sender' => ['uid' => -1]
        ], -1, true);
        if ($result) $CQueryCount++; // Учитываем запросы отправки PM
    }
}

// ======= LeechWarn remove =======
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id FROM users WHERE leechwarn='yes' AND uploaded / downloaded >= ? AND enabled='yes'",
    [(float)$leechwarn_remove_ratio]
);
$CQueryCount++;

$leechwarn_remove_ids = [];
while ($row = $db->fetch_array($query)) {
    $leechwarn_remove_ids[] = (int)$row['id'];
}

if ($leechwarn_remove_ids) {
    $modcomment  = gmdate('Y-m-d') . " - Leech-Warning removed by System.\n";
    $placeholders = build_in_placeholders($leechwarn_remove_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET leechwarn='no',
            leechwarnuntil=0,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([$modcomment], $leechwarn_remove_ids)
    );
    $CQueryCount++;
}

// ======= Apply LeechWarn =======
$downloaded_limit = $leechwarn_gig_limit * GB_IN_BYTES;
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id FROM users WHERE usergroup=? AND leechwarn='no' AND enabled='yes' AND uploaded / downloaded < ? AND downloaded >= ?",
    [(int)UC_USER, (float)$leechwarn_min_ratio, (int)$downloaded_limit]
);
$CQueryCount++;

$leechwarn_ids = [];
$until = TIMENOW + $leechwarn_length * WEEK_IN_SECONDS;
while ($row = $db->fetch_array($query)) {
    $leechwarn_ids[] = (int)$row['id'];
}

if ($leechwarn_ids) {
    $modcomment   = gmdate('Y-m-d') . " - Leech-Warned by System - Low Ratio.\n";
    $placeholders = build_in_placeholders($leechwarn_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET leechwarn='yes',
            leechwarnuntil=?,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([(int)$until, $modcomment], $leechwarn_ids)
    );
    $CQueryCount++;

    savelog('Leech-warned users: ' . implode(', ', $leechwarn_ids));
    $CQueryCount++;

    send_bulk_pm(
        $leechwarn_ids,
        $lang->cronjobs['lwarning_subject'],
        sprintf($lang->cronjobs['lwarning_message'], $leechwarn_remove_ratio, $leechwarn_length)
    );
}

// ======= Ban LeechWarn expired =======
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id FROM users WHERE usergroup=? AND enabled='yes' AND leechwarn='yes' AND leechwarnuntil < ?",
    [(int)UC_USER, TIMENOW]
);
$CQueryCount++;

$leech_ban_ids = [];
$reason = 'Reason: Banned by System because of Leech-Warning expired!';
while ($row = $db->fetch_array($query)) {
    $leech_ban_ids[] = (int)$row['id'];
}

if ($leech_ban_ids) {
    $modcomment   = gmdate('Y-m-d') . " - $reason\n";
    $placeholders = build_in_placeholders($leech_ban_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET enabled='no',
            usergroup=?,
            notifs=?,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([(int)UC_BANNED, $reason, $modcomment], $leech_ban_ids)
    );
    $CQueryCount++;
    savelog('Banned users (LeechWarn expired): ' . implode(', ', $leech_ban_ids));
    $CQueryCount++;
}




// ======= Ban by max warn =======
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id, usergroup, additionalgroups, displaygroup FROM users WHERE enabled='yes' AND timeswarned >= ?",
    [(int)$ban_user_limit]
);
$CQueryCount++;
$ban_limit_ids = [];
$reason = 'Reason: Automatically banned system. Max Warn Limit reached!';

while ($row = $db->fetch_array($query)) {
    $ban_limit_ids[] = (int)$row['id'];

    // Запись в таблицу banned
    $db->sql_query_prepared(
        "INSERT INTO banned (uid, gid, oldgroup, oldadditionalgroups, olddisplaygroup, admin, dateline, bantime, lifted, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            dateline = ?,
            reason   = ?",
        [
            (int)$row['id'],
            (int)UC_BANNED,
            (int)$row['usergroup'],
            $row['additionalgroups'] ?? '',
            (int)$row['displaygroup'],
            0,
            TIMENOW,
            'permanent',
            0,
            $reason,
            TIMENOW,
            $reason,
        ]
    );
    $CQueryCount++;
}

if ($ban_limit_ids) {
    $modcomment   = gmdate('Y-m-d') . " - $reason";
    $placeholders = build_in_placeholders($ban_limit_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET enabled='no',
            usergroup=?,
            notifs=?,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([(int)UC_BANNED, $reason, $modcomment], $ban_limit_ids)
    );
    $CQueryCount++;
    savelog('Banned users (Max warn limit): ' . implode(', ', $ban_limit_ids));
    $CQueryCount++;
}





// ======= Remove expired warns =======
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id FROM users WHERE warned='yes' AND warneduntil < ? AND enabled='yes'",
    [TIMENOW]
);
$CQueryCount++;

$warn_remove_ids = [];
while ($row = $db->fetch_array($query)) {
    $warn_remove_ids[] = (int)$row['id'];
}

if ($warn_remove_ids) {
    $modcomment   = gmdate('Y-m-d') . " - Warning removed by System.\n";
    $placeholders = build_in_placeholders($warn_remove_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET warned='no',
            timeswarned=IF(timeswarned>0,timeswarned-1,0),
            warneduntil=0,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([$modcomment], $warn_remove_ids)
    );
    $CQueryCount++;
}

// ======= Promote Power Users =======
if ($promote_gig_limit > 0) {
    $limit = $promote_gig_limit * GB_IN_BYTES;
    $maxdt = TIMENOW - DAY_IN_SECONDS * $promote_min_reg_days;

    $query = $db->sql_query_prepared(
        "SELECT DISTINCT id FROM users
        WHERE usergroup=?
        AND enabled='yes'
        AND uploaded >= ?
        AND uploaded / downloaded >= ?
        AND added < ?",
        [(int)UC_USER, (int)$limit, (float)$promote_min_ratio, (int)$maxdt]
    );
    $CQueryCount++;

    $promote_ids = [];
    while ($row = $db->fetch_array($query)) {
        $promote_ids[] = (int)$row['id'];
    }

    if ($promote_ids) {
        $modcomment   = gmdate('Y-m-d') . " - Promoted to POWER USER by AutoSystem.\n";
        $placeholders = build_in_placeholders($promote_ids);
        $db->sql_query_prepared(
            "UPDATE users
            SET usergroup=?,
                modcomment=CONCAT(?, modcomment)
            WHERE id IN ({$placeholders})",
            array_merge([(int)UC_POWER_USER, $modcomment], $promote_ids)
        );
        $CQueryCount++;
        savelog('Promoted users: ' . implode(', ', $promote_ids));
        $CQueryCount++;

        send_bulk_pm(
            $promote_ids,
            $lang->cronjobs['promote_subject'],
            $lang->cronjobs['promote_message']
        );
    }
}

// ======= Demote Power Users =======
$query = $db->sql_query_prepared(
    "SELECT DISTINCT id FROM users
    WHERE usergroup=?
    AND uploaded / downloaded < ?
    AND enabled='yes'",
    [(int)UC_POWER_USER, (float)$demote_min_ratio]
);
$CQueryCount++;

$demote_ids = [];
while ($row = $db->fetch_array($query)) {
    $demote_ids[] = (int)$row['id'];
}

if ($demote_ids) {
    $modcomment   = gmdate('Y-m-d') . " - Demoted to USER by AutoSystem.\n";
    $placeholders = build_in_placeholders($demote_ids);
    $db->sql_query_prepared(
        "UPDATE users
        SET usergroup=?,
            modcomment=CONCAT(?, modcomment)
        WHERE id IN ({$placeholders})",
        array_merge([(int)UC_USER, $modcomment], $demote_ids)
    );
    $CQueryCount++;
    savelog('Demoted users: ' . implode(', ', $demote_ids));
    $CQueryCount++;

    send_bulk_pm(
        $demote_ids,
        $lang->cronjobs['demote_subject'],
        sprintf($lang->cronjobs['demote_message'], $demote_min_ratio)
    );
}
