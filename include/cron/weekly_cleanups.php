<?php
/**
 * Hit & Run Cron
 */

if (!defined('IN_CRON')) exit();

require_once INC_PATH . '/functions_pm.php';

$MinSeedHours   = 24;
$MinFinishDate  = 1230772229;
$Enabled        = true;
$HRSkipGroups   = [4, 5, 6, 7, 8];
$ban_user_limit = 5;

if (!$Enabled || $MinSeedHours <= 0) return;

$seed_seconds = $MinSeedHours * HOUR_IN_SECONDS;

$where = [
    "s.warned      = '0'",
    "s.finished    = 'yes'",
    "t.banned      = 'no'",
    "u.enabled     = 'yes'",
    "u.ustatus     = 'confirmed'",
    "s.completedat > ?",
    "s.seedtime    < ?",
    "NOT EXISTS (
        SELECT 1 FROM peers p
        WHERE p.torrent = s.torrentid
          AND p.userid  = s.userid
          AND p.seeder  = 'yes'
    )",
];

$params = [(int)$MinFinishDate, (int)$seed_seconds];

if (!empty($HRSkipGroups)) {
    $skip    = implode(',', array_map('intval', $HRSkipGroups));
    $where[] = "u.usergroup NOT IN (0,{$skip})";
}

$wrapped = $db->sql_query_prepared(
    "SELECT s.id, s.torrentid, s.userid, s.seedtime, s.completedat,
           t.name,
           u.username, u.timeswarned
    FROM snatched s
    INNER JOIN torrents t ON s.torrentid = t.id
    INNER JOIN users    u ON s.userid    = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.userid, s.completedat ASC",
    $params
);
$CQueryCount++;

$userTorrents  = [];
$userWarnCount = [];

if ($wrapped && $wrapped->result) {
    while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
        $uid = (int)$row['userid'];
        $userTorrents[$uid][] = $row;
        $userWarnCount[$uid]  = (int)$row['timeswarned'];
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

$warnPm     = [];
$finalPm    = [];
$silentMark = [];

foreach ($userTorrents as $uid => $torrents) {
    $warns = $userWarnCount[$uid];

    foreach ($torrents as $row) {
        if ($warns >= $ban_user_limit) {
            $silentMark[] = (int)$row['id'];
            continue;
        }

        $warns++;

        if ($warns >= $ban_user_limit) {
            $finalPm[] = $row;
        } else {
            $warnPm[] = $row;
        }
    }
}

if (!empty($warnPm)) {
    hr_send_pm($warnPm, $lang->cronjobs['hr_warn_subject'], $lang->cronjobs['hr_warn_message']);
}

if (!empty($finalPm)) {
    hr_send_pm($finalPm, $lang->cronjobs['hr_final_subject'], $lang->cronjobs['hr_final_message']);
}

if (!empty($silentMark)) {
    $placeholders = implode(',', array_fill(0, count($silentMark), '?'));
    $db->sql_query_prepared("UPDATE snatched SET warned = '1' WHERE id IN ({$placeholders})", $silentMark);
    $CQueryCount++;
}

$incrementMap = [];
foreach (array_merge($warnPm, $finalPm) as $row) {
    $uid = (int)$row['userid'];
    $incrementMap[$uid] = ($incrementMap[$uid] ?? 0) + 1;
}

if (!empty($incrementMap)) {
    if (max($incrementMap) === 1) {
        $placeholders = implode(',', array_fill(0, count($incrementMap), '?'));
        $db->sql_query_prepared(
            "UPDATE users SET timeswarned = timeswarned + 1 WHERE id IN ({$placeholders})",
            array_keys($incrementMap)
        );
    } else {
        $caseWhen = [];
        $params   = [];
        $userIds  = [];
        foreach ($incrementMap as $uid => $cnt) {
            $caseWhen[] = "WHEN id = ? THEN timeswarned + ?";
            $params[]   = $uid;
            $params[]   = $cnt;
            $userIds[]  = $uid;
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $db->sql_query_prepared(
            "UPDATE users SET timeswarned = CASE
"
            . implode("
", $caseWhen)
            . "
ELSE timeswarned END WHERE id IN ({$in})",
            array_merge($params, $userIds)
        );
    }
    $CQueryCount++;
}

savelog(sprintf(
    'HR cron: warn=%d final=%d silent=%d',
    count($warnPm),
    count($finalPm),
    count($silentMark)
), 'cron');

function hr_send_pm(array $rows, string $subject, string $tpl): void
{
    global $CQueryCount, $MinSeedHours, $BASEURL, $db;

    $snatchedIds = [];

    foreach ($rows as $r) {
        $seeded_h = (int)floor($r['seedtime'] / HOUR_IN_SECONDS);

        $message = sprintf($tpl,
            $r['username'],
            '[URL=' . $BASEURL . '/details.php?id='  . $r['torrentid'] . ']' . htmlspecialchars($r['name']) . '[/URL]',
            $seeded_h,
            $MinSeedHours,
            '[URL=' . $BASEURL . '/download.php?id=' . $r['torrentid'] . ']' . htmlspecialchars($r['name']) . '[/URL]',
            $MinSeedHours
        );

        send_pm([
            'subject' => $subject,
            'message' => $message,
            'touid'   => (int)$r['userid'],
            'sender'  => ['uid' => -1],
        ], -1, true);

        $CQueryCount++;
        $snatchedIds[] = (int)$r['id'];
    }

    if (!empty($snatchedIds)) {
        $placeholders = implode(',', array_fill(0, count($snatchedIds), '?'));
        $db->sql_query_prepared("UPDATE snatched SET warned = '1' WHERE id IN ({$placeholders})", $snatchedIds);
        $CQueryCount++;
    }
}
