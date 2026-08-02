<?php
/**
 * Seedbonus cron (Avistaz-style)
 * Requires: IN_CRON defined, $db, $CQueryCount
 */

if (!defined('IN_CRON')) {
    exit();
}

// ============================================================
//  1. Загрузка настроек
// ============================================================

$cfg = loadSeedbonusSettings($db, $CQueryCount);

if (empty($cfg['enabled'])) {
    savelog('Seedbonus cron: disabled in settings');
    return;
}

// ============================================================
//  2. Константы из настроек
// ============================================================

$ANNOUNCE_INTERVAL  = 900;
$CRON_SEC           = max(60, (int)$cfg['cron_interval'] * 60);
$CRON_HOURS         = $CRON_SEC / 3600;

$BASE_BONUS         = max(0.0, (float)$cfg['base_bonus']);
$HOUR_CAP           = max(0.0, (float)$cfg['hour_cap']);
$MAX_DB_VALUE       = max(0.0, (float)$cfg['max_db_value']);
$BATCH_SIZE         = max(1,   (int)$cfg['batch_size']);

$MULTIPLIER_TYPE    = (string)$cfg['torrent_multiplier_type'];
$FLAT_MULTIPLIER    = max(0.0, (float)$cfg['flat_multiplier']);

$LEECH_NONE         = max(0.0, (float)$cfg['leech_none']);
$LEECH_FEW          = max(0.0, (float)$cfg['leech_few']);
$LEECH_MANY         = max(0.0, (float)$cfg['leech_many']);

$SIZE_SMALL         = max(0.0, (float)$cfg['size_small']);
$SIZE_MEDIUM        = max(0.0, (float)$cfg['size_medium']);
$SIZE_LARGE         = max(0.0, (float)$cfg['size_large']);
$SIZE_XLARGE        = max(0.0, (float)$cfg['size_xlarge']);
$SIZE_HUGE          = max(0.0, (float)$cfg['size_huge']);

$SEEDERS_MANY       = max(0.0, (float)$cfg['seeders_many']);
$SEEDERS_MEDIUM     = max(0.0, (float)$cfg['seeders_medium']);

$AGE_OLD            = max(0.0, (float)$cfg['age_old']);
$AGE_MEDIUM         = max(0.0, (float)$cfg['age_medium']);

$PROMO_FREE         = max(0.0, (float)$cfg['promo_free']);
$PROMO_SILVER       = max(0.0, (float)$cfg['promo_silver']);
$PROMO_DOUBLE       = max(0.0, (float)$cfg['promo_double']);

$HISTORY_SECONDS    = max(86400, (int)$cfg['history_days'] * 86400);
$ENABLE_HEURISTIC   = (bool)$cfg['enable_heuristic'];

$HEURISTIC = [
    50 => max(0, (int)$cfg['heuristic_50']),
    40 => max(0, (int)$cfg['heuristic_40']),
    30 => max(0, (int)$cfg['heuristic_30']),
    20 => max(0, (int)$cfg['heuristic_20']),
    10 => max(0, (int)$cfg['heuristic_10']),
     5 => max(0, (int)$cfg['heuristic_5']),
     1 => max(0, (int)$cfg['heuristic_1']),
];

$MAX_POSSIBLE_BONUS = $HOUR_CAP * $CRON_HOURS * 2;

$activeWindow = $ANNOUNCE_INTERVAL * 3;

savelog("Seedbonus cron: start | interval={$CRON_SEC}s | base={$BASE_BONUS} | cap={$HOUR_CAP}");

// ============================================================
//  3. Основной запрос
// ============================================================

$sql = buildMainQuery([
    'active_window'  => $activeWindow,
    'cron_hours'     => $CRON_HOURS,
    'leech_none'     => $LEECH_NONE,
    'leech_few'      => $LEECH_FEW,
    'leech_many'     => $LEECH_MANY,
    'size_small'     => $SIZE_SMALL,
    'size_medium'    => $SIZE_MEDIUM,
    'size_large'     => $SIZE_LARGE,
    'size_xlarge'    => $SIZE_XLARGE,
    'size_huge'      => $SIZE_HUGE,
    'seeders_many'   => $SEEDERS_MANY,
    'seeders_medium' => $SEEDERS_MEDIUM,
    'age_old'        => $AGE_OLD,
    'age_medium'     => $AGE_MEDIUM,
    'promo_free'     => $PROMO_FREE,
    'promo_silver'   => $PROMO_SILVER,
    'promo_double'   => $PROMO_DOUBLE,
]);

$wrapped = $db->sql_query_prepared($sql, []);
++$CQueryCount;

$numRows = 0;
if ($wrapped && $wrapped->result) {
    $numRows = mysqli_num_rows($wrapped->result);
}

if (!$numRows) {
    if ($wrapped && $wrapped->stmt) {
        mysqli_stmt_close($wrapped->stmt);
    }
    savelog('Seedbonus cron: done | no active seeders');
    return;
}

// ============================================================
//  4. Сбор данных из результата
// ============================================================

$allRows    = [];
$allUserIds = [];

if ($wrapped && $wrapped->result) {
    while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
        $uid = (int)$row['userid'];
        $allUserIds[] = $uid;
        $allRows[$uid] = [
            'torrents' => (int)$row['torrents_count'],
            'hours'    => max(0.0, (float)$row['avg_hours_seeded']),
            'raw'      => max(0.0, (float)$row['raw_bonus_sum']),
        ];
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

// ============================================================
//  5. Загрузка текущих бонусов
// ============================================================

$currentBonuses = loadCurrentBonuses($allUserIds, $db, $CQueryCount);

// ============================================================
//  6. Расчёт и накопление обновлений
// ============================================================

$updates  = [];
$batchNum = 0;
$stats    = ['processed' => 0, 'updated' => 0, 'maxed' => 0, 'total' => 0.0];

foreach ($allRows as $uid => $data) {
    $torrents = $data['torrents'];
    $hours    = $data['hours'];
    $raw      = $data['raw'];

    if ($torrents > 10000 || $hours > 1000 || $raw > 100000) {
        savelog("WARNING: uid={$uid} anomal: torrents={$torrents} hours={$hours} raw={$raw}");
        continue;
    }

    if ($ENABLE_HEURISTIC) {
        $heuristicPerInterval = getHeuristicHours($torrents, $HEURISTIC) * ($CRON_HOURS / 24);
        $hours = max($hours, $heuristicPerInterval);
    }

    $capMul      = torrentMultiplier($torrents, $MULTIPLIER_TYPE, $FLAT_MULTIPLIER);
    $hourlyBonus = min($raw * $BASE_BONUS * $capMul, $HOUR_CAP);
    $finalBonus  = round($hourlyBonus * $hours, 1);

    if ($finalBonus < 0.1) {
        continue;
    }

    if ($finalBonus > $MAX_POSSIBLE_BONUS) {
        savelog("WARNING: uid={$uid} bonus capped {$finalBonus} → {$MAX_POSSIBLE_BONUS}");
        $finalBonus = $MAX_POSSIBLE_BONUS;
    }

    $current = $currentBonuses[$uid] ?? 0.0;

    if ($current >= $MAX_DB_VALUE) {
        $stats['maxed']++;
        continue;
    }

    if ($current + $finalBonus > $MAX_DB_VALUE) {
        $finalBonus = round($MAX_DB_VALUE - $current, 1);
        if ($finalBonus < 0.1) {
            $stats['maxed']++;
            continue;
        }
    }

    $updates[] = ['userid' => $uid, 'bonus' => $finalBonus];
    $stats['processed']++;
    $stats['total'] += $finalBonus;

    if (count($updates) >= $BATCH_SIZE) {
        $stats['updated'] += processBatch($updates, $db, $CQueryCount);
        $updates = [];
        $batchNum++;
        usleep(50000);
    }
}

if (!empty($updates)) {
    $stats['updated'] += processBatch($updates, $db, $CQueryCount);
}

if ($stats['updated'] > 0) {
    $db->sql_query_prepared('FLUSH TABLES', []);
    ++$CQueryCount;
}

// ============================================================
//  7. Лог
// ============================================================

savelog(sprintf(
    'Seedbonus cron: done | users=%d | updated=%d | maxed=%d | total=%.1f | queries=%d',
    $stats['processed'],
    $stats['updated'],
    $stats['maxed'],
    $stats['total'],
    $CQueryCount
));

// ============================================================
//  Функции
// ============================================================

function loadSeedbonusSettings($db, int &$queryCount): array
{
    $defaults = [
        'enabled'               => false,
        'cron_interval'         => 15,
        'base_bonus'            => 10.0,
        'hour_cap'              => 500.0,
        'max_db_value'          => 9999999.9,
        'batch_size'            => 100,
        'torrent_multiplier_type' => 'penalty',
        'flat_multiplier'       => 1.0,
        'leech_none'            => 1.2,
        'leech_few'             => 1.5,
        'leech_many'            => 1.8,
        'size_small'            => 1.0,
        'size_medium'           => 1.2,
        'size_large'            => 1.5,
        'size_xlarge'           => 1.8,
        'size_huge'             => 2.0,
        'seeders_many'          => 0.9,
        'seeders_medium'        => 0.95,
        'age_old'               => 1.5,
        'age_medium'            => 1.3,
        'promo_free'            => 0.7,
        'promo_silver'          => 0.5,
        'promo_double'          => 0.5,
        'history_days'          => 1,
        'enable_heuristic'      => false,
        'heuristic_50'          => 24,
        'heuristic_40'          => 20,
        'heuristic_30'          => 16,
        'heuristic_20'          => 12,
        'heuristic_10'          => 8,
        'heuristic_5'           => 4,
        'heuristic_1'           => 2,
    ];

    $wrapped = $db->sql_query_prepared(
        'SELECT setting_key, setting_value, setting_type FROM seedbonus_settings',
        []
    );
    ++$queryCount;

    $cfg = $defaults;

    if ($wrapped && $wrapped->result) {
        while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
            $key = $row['setting_key'];
            $val = $row['setting_value'];

            $cfg[$key] = match ($row['setting_type']) {
                'boolean' => in_array($val, ['yes', 'true', '1', 'on'], true),
                'integer' => (int)$val,
                'float'   => (float)$val,
                'array'   => json_decode($val, true) ?? [],
                default   => (string)$val,
            };
        }
        mysqli_free_result($wrapped->result);
    }
    if ($wrapped && $wrapped->stmt) {
        mysqli_stmt_close($wrapped->stmt);
    }

    return $cfg;
}

function loadCurrentBonuses(array $userIds, $db, int &$queryCount): array
{
    $result = [];

    if (empty($userIds)) {
        return $result;
    }

    $safeIds = array_map('intval', $userIds);

    foreach (array_chunk($safeIds, 5000) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $wrapped = $db->sql_query_prepared(
            "SELECT id, seedbonus FROM users WHERE id IN ({$placeholders})",
            $chunk
        );
        ++$queryCount;

        if ($wrapped && $wrapped->result) {
            while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
                $result[(int)$row['id']] = max(0.0, (float)$row['seedbonus']);
            }
            mysqli_free_result($wrapped->result);
        }
        if ($wrapped && $wrapped->stmt) {
            mysqli_stmt_close($wrapped->stmt);
        }
    }

    return $result;
}

function buildMainQuery(array $p): string
{
    $f = array_map('floatval', array_diff_key($p, ['active_window' => 1]));
    $activeWindow = (int)$p['active_window'];
    $cronHours    = $f['cron_hours'];

    return "
        SELECT
            p.userid,
            COUNT(DISTINCT p.torrent) AS torrents_count,
            AVG(
                GREATEST(0.25, LEAST(
                    (UNIX_TIMESTAMP() - GREATEST(p.last_action, UNIX_TIMESTAMP() - {$activeWindow})) / 3600,
                    {$cronHours}
                ))
            ) AS avg_hours_seeded,
            SUM(
                CASE
                    WHEN t.leechers = 0      THEN {$f['leech_none']}
                    WHEN t.leechers <= 2     THEN {$f['leech_few']}
                    ELSE                          {$f['leech_many']}
                END *
                CASE
                    WHEN t.size < 536870912   THEN {$f['size_small']}
                    WHEN t.size < 2147483648  THEN {$f['size_medium']}
                    WHEN t.size < 8589934592  THEN {$f['size_large']}
                    WHEN t.size < 21474836480 THEN {$f['size_xlarge']}
                    ELSE                           {$f['size_huge']}
                END *
                CASE
                    WHEN t.seeders > 100 THEN {$f['seeders_many']}
                    WHEN t.seeders > 50  THEN {$f['seeders_medium']}
                    ELSE 1.0
                END *
                CASE
                    WHEN (UNIX_TIMESTAMP() - t.added) > 15552000 THEN {$f['age_old']}
                    WHEN (UNIX_TIMESTAMP() - t.added) > 5184000  THEN {$f['age_medium']}
                    ELSE 1.0
                END *
                (1.0
                    + (t.free         = 'yes') * {$f['promo_free']}
                    + (t.silver       = 'yes') * {$f['promo_silver']}
                    + (t.doubleupload = 'yes') * {$f['promo_double']}
                )
            ) AS raw_bonus_sum
        FROM peers p
        INNER JOIN torrents t ON t.id = p.torrent
        WHERE p.seeder     = 'yes'
          AND p.userid     > 0
          AND t.visible    = 'yes'
          AND t.banned     = 'no'
          AND t.isnuked    = 'no'
          AND p.last_action >= UNIX_TIMESTAMP() - {$activeWindow}
        GROUP BY p.userid
        HAVING raw_bonus_sum > 0
        ORDER BY NULL
    ";
}

function torrentMultiplier(int $count, string $type, float $flat): float
{
    return match ($type) {
        'penalty' => match (true) {
            $count <= 20  => 1.0,
            $count <= 50  => 0.9,
            $count <= 100 => 0.8,
            default       => 0.7,
        },
        'neutral' => $count <= 100 ? 1.0 : 0.9,
        'reward'  => match (true) {
            $count >= 100 => 1.2,
            $count >= 50  => 1.1,
            $count >= 20  => 1.0,
            default       => 0.9,
        },
        'flat'    => max(0.0, $flat),
        default   => 1.0,
    };
}

function getHeuristicHours(int $count, array $h): float
{
    return (float)match (true) {
        $count >= 50 => $h[50],
        $count >= 40 => $h[40],
        $count >= 30 => $h[30],
        $count >= 20 => $h[20],
        $count >= 10 => $h[10],
        $count >= 5  => $h[5],
        default      => $h[1],
    };
}

function processBatch(array $updates, $db, int &$queryCount): int
{
    if (empty($updates)) {
        return 0;
    }

    $caseWhen = [];
    $params   = [];
    $userIds  = [];

    foreach ($updates as $u) {
        $uid   = (int)$u['userid'];
        $bonus = (float)$u['bonus'];

        if ($uid <= 0 || $bonus <= 0) {
            continue;
        }

        $caseWhen[] = "WHEN id = ? THEN seedbonus + ?";
        $params[]   = $uid;
        $params[]   = $bonus;
        $userIds[]  = $uid;
    }

    if (empty($userIds)) {
        return 0;
    }

    $in        = implode(',', array_fill(0, count($userIds), '?'));
    $sql       = "UPDATE users SET seedbonus = CASE
"
               . implode("
", $caseWhen)
               . "
ELSE seedbonus END WHERE id IN ({$in})";
    $allParams = array_merge($params, $userIds);

    $db->sql_query_prepared($sql, $allParams);
    ++$queryCount;

    return (int)$db->affected_rows();
}
