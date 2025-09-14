<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Optimized Cron]===============*/
/***********************************************/

if (!defined('IN_CRON')) {
    exit();
}

require INC_PATH . '/functions_pm.php';




// ======= Вспомогательные функции =======

// Безопасная генерация IN-условия для SQL
function build_safe_in_clause(array $ids, string $field = 'id'): string {
    if (empty($ids)) return '0=1'; // Защита от пустого массива
    $safe_ids = array_map('intval', $ids);
    return $field . ' IN (' . implode(',', $safe_ids) . ')';
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
$query = $db->sql_query("SELECT DISTINCT id FROM users WHERE leechwarn='yes' AND uploaded / downloaded >= $leechwarn_remove_ratio AND enabled='yes'");
$CQueryCount++;

$leechwarn_remove_ids = [];
while ($row = $db->fetch_array($query)) {
    $leechwarn_remove_ids[] = $row['id'];
}

if ($leechwarn_remove_ids) {
    $db->sql_query("
        UPDATE users
        SET leechwarn='no',
            leechwarnuntil=0,
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - Leech-Warning removed by System.\n', modcomment)
        WHERE " . build_safe_in_clause($leechwarn_remove_ids)
    );
    $CQueryCount++;
}

// ======= Apply LeechWarn =======
$downloaded_limit = $leechwarn_gig_limit * GB_IN_BYTES;
$query = $db->sql_query("SELECT DISTINCT id FROM users WHERE usergroup='" . UC_USER . "' AND leechwarn='no' AND enabled='yes' AND uploaded / downloaded < $leechwarn_min_ratio AND downloaded >= $downloaded_limit");
$CQueryCount++;

$leechwarn_ids = [];
$until = TIMENOW + $leechwarn_length * WEEK_IN_SECONDS;
while ($row = $db->fetch_array($query)) {
    $leechwarn_ids[] = $row['id'];
}

if ($leechwarn_ids) {
    $db->sql_query("
        UPDATE users
        SET leechwarn='yes',
            leechwarnuntil=$until,
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - Leech-Warned by System - Low Ratio.\n', modcomment)
        WHERE " . build_safe_in_clause($leechwarn_ids)
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
$query = $db->sql_query("SELECT DISTINCT id FROM users WHERE usergroup='" . UC_USER . "' AND enabled='yes' AND leechwarn='yes' AND leechwarnuntil < " . TIMENOW);
$CQueryCount++;

$leech_ban_ids = [];
$reason = 'Reason: Banned by System because of Leech-Warning expired!';
while ($row = $db->fetch_array($query)) {
    $leech_ban_ids[] = $row['id'];
}

if ($leech_ban_ids) {
    $db->sql_query("
        UPDATE users
        SET enabled='no',
            usergroup='" . UC_BANNED . "',
            notifs=" . $db->sqlesc($reason) . ",
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - $reason\n', modcomment)
        WHERE " . build_safe_in_clause($leech_ban_ids)
    );
    $CQueryCount++;
    savelog('Banned users (LeechWarn expired): ' . implode(', ', $leech_ban_ids));
    $CQueryCount++;
}

// ======= Ban by max warn =======
$query = $db->sql_query("SELECT DISTINCT id FROM users WHERE enabled='yes' AND timeswarned >= '$ban_user_limit'");
$CQueryCount++;

$ban_limit_ids = [];
$reason = 'Reason: Automatically banned system. Max Warn Limit reached!';
while ($row = $db->fetch_array($query)) {
    $ban_limit_ids[] = $row['id'];
}

if ($ban_limit_ids) {
    $db->sql_query("
        UPDATE users
        SET enabled='no',
            usergroup='" . UC_BANNED . "',
            notifs=" . $db->sqlesc($reason) . ",
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - $reason', modcomment)
        WHERE " . build_safe_in_clause($ban_limit_ids)
    );
    $CQueryCount++;
    savelog('Banned users (Max warn limit): ' . implode(', ', $ban_limit_ids));
    $CQueryCount++;
}

// ======= Remove expired warns =======
$query = $db->sql_query("SELECT DISTINCT id FROM users WHERE warned='yes' AND warneduntil < " . TIMENOW . " AND enabled='yes'");
$CQueryCount++;

$warn_remove_ids = [];
while ($row = $db->fetch_array($query)) {
    $warn_remove_ids[] = $row['id'];
}

if ($warn_remove_ids) {
    $db->sql_query("
        UPDATE users
        SET warned='no',
            timeswarned=IF(timeswarned>0,timeswarned-1,0),
            warneduntil=0,
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - Warning removed by System.\n', modcomment)
        WHERE " . build_safe_in_clause($warn_remove_ids)
    );
    $CQueryCount++;
}

// ======= Promote Power Users =======
if ($promote_gig_limit > 0) {
    $limit = $promote_gig_limit * GB_IN_BYTES;
    $maxdt = TIMENOW - DAY_IN_SECONDS * $promote_min_reg_days;

    $query = $db->sql_query("
        SELECT DISTINCT id FROM users
        WHERE usergroup='" . UC_USER . "' 
        AND enabled='yes' 
        AND uploaded >= $limit 
        AND uploaded / downloaded >= $promote_min_ratio
        AND added < $maxdt
    ");
    $CQueryCount++;

    $promote_ids = [];
    while ($row = $db->fetch_array($query)) {
        $promote_ids[] = $row['id'];
    }

    if ($promote_ids) {
        $db->sql_query("
            UPDATE users
            SET usergroup='" . UC_POWER_USER . "',
                modcomment=CONCAT('" . gmdate('Y-m-d') . " - Promoted to POWER USER by AutoSystem.\n', modcomment)
            WHERE " . build_safe_in_clause($promote_ids)
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
$query = $db->sql_query("
    SELECT DISTINCT id FROM users
    WHERE usergroup='" . UC_POWER_USER . "' 
    AND uploaded / downloaded < $demote_min_ratio
    AND enabled='yes'
");
$CQueryCount++;

$demote_ids = [];
while ($row = $db->fetch_array($query)) {
    $demote_ids[] = $row['id'];
}

if ($demote_ids) {
    $db->sql_query("
        UPDATE users
        SET usergroup='" . UC_USER . "',
            modcomment=CONCAT('" . gmdate('Y-m-d') . " - Demoted to USER by AutoSystem.\n', modcomment)
        WHERE " . build_safe_in_clause($demote_ids)
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

?>