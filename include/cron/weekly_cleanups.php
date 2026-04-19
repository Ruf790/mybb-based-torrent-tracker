<?php
/**
 * Hit & Run Cron
 *
 * Правила:
 * - каждый торрент-нарушитель = +1 к timeswarned + PM
 * - бан обрабатывается отдельным кроном по timeswarned >= ban_user_limit
 */

if (!defined('IN_CRON')) exit();

require_once INC_PATH . '/functions_pm.php';

// ── Настройки ─────────────────────────────────────────────
$MinSeedHours   = 24;
$MinFinishDate  = 1230772229;
$Enabled        = true;
$HRSkipGroups   = [4, 5, 6, 7, 8];
$ban_user_limit = 5;

if (!$Enabled || $MinSeedHours <= 0) return;

// ── Выборка нарушителей ───────────────────────────────────
$seed_seconds = $MinSeedHours * HOUR_IN_SECONDS;

$where = [
    "s.warned      = '0'",
    "s.finished    = 'yes'",
    "t.banned      = 'no'",
    "u.enabled     = 'yes'",
    "u.ustatus     = 'confirmed'",
    "s.completedat > " . (int)$MinFinishDate,
    "s.seedtime    < {$seed_seconds}",
    "NOT EXISTS (
        SELECT 1 FROM peers p
        WHERE p.torrent = s.torrentid
          AND p.userid  = s.userid
          AND p.seeder  = 'yes'
    )",
];

if (!empty($HRSkipGroups)) {
    $skip    = implode(',', array_map('intval', $HRSkipGroups));
    $where[] = "u.usergroup NOT IN (0,{$skip})";
}

$res = $db->sql_query("
    SELECT s.id, s.torrentid, s.userid, s.seedtime, s.completedat,
           t.name,
           u.username, u.timeswarned
    FROM snatched s
    INNER JOIN torrents t ON s.torrentid = t.id
    INNER JOIN users    u ON s.userid    = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.userid, s.completedat ASC
");
$CQueryCount++;

// ── Группируем по юзеру ───────────────────────────────────
$userTorrents  = [];
$userWarnCount = [];

while ($row = $db->fetch_array($res)) {
    $uid = (int)$row['userid'];
    $userTorrents[$uid][] = $row;
    $userWarnCount[$uid]  = (int)$row['timeswarned'];
}

// ── Распределяем по стадиям ───────────────────────────────
$warnPm     = []; // обычный PM
$finalPm    = []; // финальный PM (следующий — бан, но бан не наш)
$silentMark = []; // snatched.id — warned=1 без PM (уже за порогом)

foreach ($userTorrents as $uid => $torrents) {
    $warns = $userWarnCount[$uid];

    foreach ($torrents as $row) {
        if ($warns >= $ban_user_limit) {
            // Уже за порогом — тихая пометка, бан сделает другой крон
            $silentMark[] = (int)$row['id'];
            continue;
        }

        $warns++;

        if ($warns >= $ban_user_limit) {
            $finalPm[] = $row; // последний варн перед баном
        } else {
            $warnPm[] = $row;
        }
    }
}

// ── Отправка PM ───────────────────────────────────────────
if (!empty($warnPm)) {
    hr_send_pm($warnPm, $lang->cronjobs['hr_warn_subject'], $lang->cronjobs['hr_warn_message']);
}

if (!empty($finalPm)) {
    hr_send_pm($finalPm, $lang->cronjobs['hr_final_subject'], $lang->cronjobs['hr_final_message']);
}

if (!empty($silentMark)) {
    $in = implode(',', $silentMark);
    $db->sql_query("UPDATE snatched SET warned = '1' WHERE id IN ({$in})");
    $CQueryCount++;
}

// ── Инкремент timeswarned (+1 за каждый торрент) ─────────
$incrementMap = [];
foreach (array_merge($warnPm, $finalPm) as $row) {
    $uid = (int)$row['userid'];
    $incrementMap[$uid] = ($incrementMap[$uid] ?? 0) + 1;
}

if (!empty($incrementMap)) {
    if (max($incrementMap) === 1) {
        $in = implode(',', array_keys($incrementMap));
        $db->sql_query("UPDATE users SET timeswarned = timeswarned + 1 WHERE id IN ({$in})");
    } else {
        $caseWhen = array_map(
            fn($uid, $cnt) => "WHEN {$uid} THEN timeswarned + {$cnt}",
            array_keys($incrementMap),
            $incrementMap
        );
        $in = implode(',', array_keys($incrementMap));
        $db->sql_query(
            "UPDATE users SET timeswarned = CASE id\n"
            . implode("\n", $caseWhen)
            . "\nEND WHERE id IN ({$in})"
        );
    }
    $CQueryCount++;
}

// ── Лог ───────────────────────────────────────────────────
savelog(sprintf(
    'HR cron: warn=%d final=%d silent=%d',
    count($warnPm),
    count($finalPm),
    count($silentMark)
), 'cron');

// ── Функции ───────────────────────────────────────────────

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
        $in = implode(',', $snatchedIds);
        $db->sql_query("UPDATE snatched SET warned = '1' WHERE id IN ({$in})");
        $CQueryCount++;
    }
}