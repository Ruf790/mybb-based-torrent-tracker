<?php

if (!defined('IN_CRON')) {
    exit();
}

const PROMO_CONDITIONS = [
    1 => "free = 'no'  AND silver = 'no'  AND doubleupload = 'no' AND thirtypercent = 'no'",
    2 => "free = 'yes' AND thirtypercent = 'no'",
    3 => "doubleupload = 'yes' AND free = 'no' AND thirtypercent = 'no'",
    4 => "free = 'yes' AND doubleupload = 'yes' AND thirtypercent = 'no'",
    5 => "silver = 'yes' AND free = 'no'  AND doubleupload = 'no' AND thirtypercent = 'no'",
    6 => "silver = 'yes' AND doubleupload = 'yes' AND free = 'no' AND thirtypercent = 'no'",
    7 => "thirtypercent = 'yes' AND free = 'no' AND silver = 'no' AND doubleupload = 'no'",
];

const PROMO_TARGETS = [
    1 => ["free = 'no',  silver = 'no',  doubleupload = 'no',  thirtypercent = 'no'",  'normal'],
    2 => ["free = 'yes', silver = 'no',  doubleupload = 'no',  thirtypercent = 'no'",  'Free'],
    3 => ["free = 'no',  silver = 'no',  doubleupload = 'yes', thirtypercent = 'no'",  '2X'],
    4 => ["free = 'yes', silver = 'no',  doubleupload = 'yes', thirtypercent = 'no'",  '2X Free'],
    5 => ["free = 'no',  silver = 'yes', doubleupload = 'no',  thirtypercent = 'no'",  '50%'],
    6 => ["free = 'no',  silver = 'yes', doubleupload = 'yes', thirtypercent = 'no'",  '2X 50%'],
    7 => ["free = 'no',  silver = 'no',  doubleupload = 'no',  thirtypercent = 'yes'", '30%'],
];

function torrent_promotion_expire(float $days, int $type = 2, int $targettype = 1): int
{
    global $db, $CQueryCount;

    $condition  = PROMO_CONDITIONS[$type]       ?? PROMO_CONDITIONS[2];
    [$setFields, $targetLabel] = PROMO_TARGETS[$targettype] ?? PROMO_TARGETS[1];

    $dt = TIMENOW - (int)($days * 86400);

    $wrapped = $db->sql_query_prepared(
        "SELECT id, name FROM torrents WHERE added < ? AND {$condition} AND promotion_time_type = 0",
        [$dt]
    );
    ++$CQueryCount;

    $numRows = 0;
    if ($wrapped && $wrapped->result) {
        $numRows = mysqli_num_rows($wrapped->result);
    }

    if (!$numRows) {
        if ($wrapped && $wrapped->stmt) {
            mysqli_stmt_close($wrapped->stmt);
        }
        return 0;
    }

    $ids   = [];
    $names = [];

    if ($wrapped && $wrapped->result) {
        while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
            $ids[]   = (int)$row['id'];
            $names[] = $row['name'];
        }
        mysqli_free_result($wrapped->result);
    }
    if ($wrapped && $wrapped->stmt) {
        mysqli_stmt_close($wrapped->stmt);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->sql_query_prepared(
        "UPDATE torrents SET {$setFields} WHERE id IN ({$placeholders})",
        $ids
    );
    ++$CQueryCount;

    $updated = (int)$db->affected_rows();

    if ($updated > 0) {
        $label   = $targettype === 1 ? 'no longer on promotion' : "changed to {$targetLabel}";
        $nameLog = implode("
", $names);
        savelog("Torrents {$label} (time expired):
{$nameLog}", 'normal');
    }

    return $updated;
}

$promotions = [
    [$expirehalfleech,         5, $halfleechbecome,         'Expired 50% Leech'],
    [$expirefree,              2, $freebecome,              'Expired Free Leech'],
    [$expiretwoup,             3, $twoupbecome,             'Expired 2X Upload'],
    [$expiretwoupfree,         4, $twoupfreebecome,         'Expired Free + 2X'],
    [$expiretwouphalfleech,    6, $twouphalfleechbecome,    'Expired 50% + 2X'],
    [$expirethirtypercentleech,7, $thirtypercentleechbecome,'Expired 30% Leech'],
    [$expirenormal,            1, $normalbecome,            'Expired Normal'],
];

savelog('Starting torrent promotion expiration cleanup', 'cron');

$totalProcessed = 0;

foreach ($promotions as [$days, $fromType, $toType, $label]) {
    if (empty($days) || $days <= 0) {
        continue;
    }

    $count = torrent_promotion_expire((float)$days, (int)$fromType, (int)$toType);
    $totalProcessed += $count;

    savelog("{$label} promotions: {$count} torrents", 'cron');
}

savelog("Finished promotion expiration. Total: {$totalProcessed} torrents", 'cron');
