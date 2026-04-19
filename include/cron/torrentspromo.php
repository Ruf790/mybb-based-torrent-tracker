<?php

if (!defined('IN_CRON')) {
    exit();
}

// ============================================================
//  Маппинг типов промо-акций
//  Ключ = тип (int), значение = условие WHERE для SELECT
// ============================================================

const PROMO_CONDITIONS = [
    1 => "free = 'no'  AND silver = 'no'  AND doubleupload = 'no'",
    2 => "free = 'yes'",
    3 => "doubleupload = 'yes' AND free = 'no'",
    4 => "free = 'yes' AND doubleupload = 'yes'",
    5 => "silver = 'yes' AND free = 'no'  AND doubleupload = 'no'",
    6 => "silver = 'yes' AND doubleupload = 'yes' AND free = 'no'",
    7 => "silver = 'yes' AND free = 'no'  AND doubleupload = 'no'",
];

// Что ставим в UPDATE и как называем тип для лога
const PROMO_TARGETS = [
    1 => ["free = 'no',  silver = 'no',  doubleupload = 'no'",  'normal'],
    2 => ["free = 'yes', silver = 'no',  doubleupload = 'no'",  'Free'],
    3 => ["free = 'no',  silver = 'no',  doubleupload = 'yes'", '2X'],
    4 => ["free = 'yes', silver = 'no',  doubleupload = 'yes'", '2X Free'],
    5 => ["free = 'no',  silver = 'yes', doubleupload = 'no'",  '50%'],
    6 => ["free = 'no',  silver = 'yes', doubleupload = 'yes'", '2X 50%'],
    7 => ["free = 'no',  silver = 'yes', doubleupload = 'no'",  '50%'],
];

// ============================================================

/**
 * Истекает промо-акцию для торрентов старше $days дней.
 *
 * Вместо UPDATE по одному — один SELECT + один UPDATE по IN(ids).
 *
 * @param float $days        Возраст в днях после которого промо истекает
 * @param int   $type        Тип текущей промо-акции (ключ PROMO_CONDITIONS)
 * @param int   $targettype  Тип в который переводим (ключ PROMO_TARGETS)
 * @return int Количество обновлённых торрентов
 */
function torrent_promotion_expire(float $days, int $type = 2, int $targettype = 1): int
{
    global $db, $CQueryCount;

    $condition  = PROMO_CONDITIONS[$type]       ?? PROMO_CONDITIONS[2];
    [$setFields, $targetLabel] = PROMO_TARGETS[$targettype] ?? PROMO_TARGETS[1];

    $dt = TIMENOW - (int)($days * 86400);

    // Один SELECT — берём только id и name
    $res = $db->sql_query("
        SELECT id, name
        FROM torrents
        WHERE added < {$dt}
          AND {$condition}
          AND ts_external = 'no'
          AND promotion_time_type = 0
    ");
    ++$CQueryCount;

    if (!$res || !$db->num_rows($res)) {
        return 0;
    }

    $ids   = [];
    $names = [];

    while ($row = $db->fetch_array($res)) {
        $ids[]   = (int)$row['id'];
        $names[] = $row['name'];
    }
    $db->free_result($res);

    // Один UPDATE вместо N
    $in = implode(',', $ids);
    $db->sql_query("UPDATE torrents SET {$setFields} WHERE id IN ({$in})");
    ++$CQueryCount;

    $updated = (int)$db->affected_rows();

    if ($updated > 0) {
        $label   = $targettype === 1 ? 'no longer on promotion' : "changed to {$targetLabel}";
        $nameLog = implode("\n", $names);
        savelog("Torrents {$label} (time expired):\n{$nameLog}", 'normal');
    }

    return $updated;
}

// ============================================================
//  Конфигурация запусков: [days, fromType, toType, label]
// ============================================================

$promotions = [
    [$expirehalfleech,         5, $halfleechbecome,         'Expired 50% Leech'],
    [$expirefree,              2, $freebecome,              'Expired Free Leech'],
    [$expiretwoup,             3, $twoupbecome,             'Expired 2X Upload'],
    [$expiretwoupfree,         4, $twoupfreebecome,         'Expired Free + 2X'],
    [$expiretwouphalfleech,    6, $twouphalfleechbecome,    'Expired 50% + 2X'],
    [$expirethirtypercentleech,7, $thirtypercentleechbecome,'Expired 30% Leech'],
    [$expirenormal,            1, $normalbecome,            'Expired Normal'],
];

// ============================================================
//  Запуск
// ============================================================

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