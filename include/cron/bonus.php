<?php
/**
 * Seedbonus cron (Avistaz-style) - с использованием настроек из БД
 * TS Special Edition 5.6 / MyISAM
 */

if (!defined('IN_CRON')) {
    exit();
}

// ===== ЗАГРУЗКА НАСТРОЕК ИЗ БД (ИЗ ТАБЛИЦЫ seedbonus_settings) =====
$seedbonus_settings = [];

// Загружаем все настройки seedbonus одним запросом из правильной таблицы
$res = $db->sql_query("SELECT setting_key, setting_value, setting_type FROM seedbonus_settings");
while ($row = $db->fetch_array($res)) {
    // Приводим значение к правильному типу
    $value = $row['setting_value'];
    switch ($row['setting_type']) {
        case 'boolean':
            $value = ($value === 'yes' || $value === 'true' || $value === '1' || $value === 'on');
            break;
        case 'integer':
            $value = intval($value);
            break;
        case 'float':
            $value = floatval($value);
            break;
        case 'array':
            $value = json_decode($value, true) ?? [];
            break;
        default:
            $value = (string)$value;
    }
    $seedbonus_settings[$row['setting_key']] = $value;
}
++$CQueryCount;

// Проверяем включена ли система
if (empty($seedbonus_settings['enabled']) || $seedbonus_settings['enabled'] === false) {
    savelog("Seedbonus cron: Система отключена в настройках");
    exit(0);
}



// ===== НАСТРОЙКИ SEEDBONUS CRON ИЗ БД =====
$ANNOUNCE_INTERVAL = 900; // 15 минут (фиксированное из настроек трекера)
$CRON_INTERVAL_SEC = max(60, intval($seedbonus_settings['cron_interval'] ?? 15) * 60);
$CRON_INTERVAL_HOURS = $CRON_INTERVAL_SEC / 3600;

// Основные параметры (используем значения из таблицы)
$BASE_BONUS = max(0, floatval($seedbonus_settings['base_bonus'] ?? 10.0));
$HOUR_CAP = max(0, floatval($seedbonus_settings['hour_cap'] ?? 500.0));
$MAX_DB_VALUE = max(0, floatval($seedbonus_settings['max_db_value'] ?? 9999999.9));
$BATCH_SIZE = max(1, intval($seedbonus_settings['batch_size'] ?? 100));

// Множитель торрентов
$TORRENT_MULTIPLIER_TYPE = $seedbonus_settings['torrent_multiplier_type'] ?? 'penalty';
$FLAT_MULTIPLIER = max(0, floatval($seedbonus_settings['flat_multiplier'] ?? 1.0));

// Множители личеров
$LEECH_NONE = max(0, floatval($seedbonus_settings['leech_none'] ?? 1.2));
$LEECH_FEW = max(0, floatval($seedbonus_settings['leech_few'] ?? 1.5));
$LEECH_MANY = max(0, floatval($seedbonus_settings['leech_many'] ?? 1.8));

// Множители размера
$SIZE_SMALL = max(0, floatval($seedbonus_settings['size_small'] ?? 1.0));
$SIZE_MEDIUM = max(0, floatval($seedbonus_settings['size_medium'] ?? 1.2));
$SIZE_LARGE = max(0, floatval($seedbonus_settings['size_large'] ?? 1.5));
$SIZE_XLARGE = max(0, floatval($seedbonus_settings['size_xlarge'] ?? 1.8));
$SIZE_HUGE = max(0, floatval($seedbonus_settings['size_huge'] ?? 2.0));

// Другие множители
$SEEDERS_MANY = max(0, floatval($seedbonus_settings['seeders_many'] ?? 0.9));
$SEEDERS_MEDIUM = max(0, floatval($seedbonus_settings['seeders_medium'] ?? 0.95));
$AGE_OLD = max(0, floatval($seedbonus_settings['age_old'] ?? 1.5));
$AGE_MEDIUM = max(0, floatval($seedbonus_settings['age_medium'] ?? 1.3));
$PROMO_FREE = max(0, floatval($seedbonus_settings['promo_free'] ?? 0.7));
$PROMO_SILVER = max(0, floatval($seedbonus_settings['promo_silver'] ?? 0.5));
$PROMO_DOUBLE = max(0, floatval($seedbonus_settings['promo_double'] ?? 0.5));

// Временные настройки
$HISTORY_DAYS = max(1, intval($seedbonus_settings['history_days'] ?? 1));
$HISTORY_SECONDS = $HISTORY_DAYS * 86400;

// Эвристика времени - исправляем проверку для boolean типа
$ENABLE_HEURISTIC = false;
if (isset($seedbonus_settings['enable_heuristic'])) {
    if (is_bool($seedbonus_settings['enable_heuristic'])) {
        $ENABLE_HEURISTIC = $seedbonus_settings['enable_heuristic'];
    } else {
        $ENABLE_HEURISTIC = ($seedbonus_settings['enable_heuristic'] === 'yes' || 
                            $seedbonus_settings['enable_heuristic'] === 'true' || 
                            $seedbonus_settings['enable_heuristic'] === '1' ||
                            $seedbonus_settings['enable_heuristic'] === 'on');
    }
}

// Настройки эвристики
$HEURISTIC_50 = max(0, intval($seedbonus_settings['heuristic_50'] ?? 24));
$HEURISTIC_40 = max(0, intval($seedbonus_settings['heuristic_40'] ?? 20));
$HEURISTIC_30 = max(0, intval($seedbonus_settings['heuristic_30'] ?? 16));
$HEURISTIC_20 = max(0, intval($seedbonus_settings['heuristic_20'] ?? 12));
$HEURISTIC_10 = max(0, intval($seedbonus_settings['heuristic_10'] ?? 8));
$HEURISTIC_5 = max(0, intval($seedbonus_settings['heuristic_5'] ?? 4));
$HEURISTIC_1 = max(0, intval($seedbonus_settings['heuristic_1'] ?? 2));


savelog("Seedbonus cron: start | interval={$CRON_INTERVAL_SEC}s | base={$BASE_BONUS} | cap={$HOUR_CAP}");
++$CQueryCount;




// ===== 1. ОДИН ЗАПРОС ДЛЯ ВСЕХ ДАННЫХ (С НАСТРОЙКАМИ ИЗ БД) =====
// Безопасное вставка значений в SQL запрос
$query = "
    SELECT 
        p.userid,
        COUNT(DISTINCT p.torrent) as torrents_count,
        -- Среднее время сидирования
        AVG(
            GREATEST(0.25, LEAST(
                (UNIX_TIMESTAMP() - GREATEST(
                    p.last_action,
                    UNIX_TIMESTAMP() - " . ($ANNOUNCE_INTERVAL * 3) . "
                )) / 3600,
                " . $CRON_INTERVAL_HOURS . "
            ))
        ) as avg_hours_seeded,
        -- Сумма всех множителей (используем настройки из БД)
        SUM(
            /* Leecher множители */
            CASE 
                WHEN t.leechers = 0 THEN " . floatval($LEECH_NONE) . "
                WHEN t.leechers <= 2 THEN " . floatval($LEECH_FEW) . "
                ELSE " . floatval($LEECH_MANY) . "
            END *
            
            /* Size множители */
            CASE 
                WHEN t.size < 536870912 THEN " . floatval($SIZE_SMALL) . "      -- < 0.5 GB
                WHEN t.size < 2147483648 THEN " . floatval($SIZE_MEDIUM) . "   -- < 2 GB
                WHEN t.size < 8589934592 THEN " . floatval($SIZE_LARGE) . "    -- < 8 GB
                WHEN t.size < 21474836480 THEN " . floatval($SIZE_XLARGE) . "   -- < 20 GB
                ELSE " . floatval($SIZE_HUGE) . "                               -- >= 20 GB
            END *
            
            /* Seeders штрафы */
            CASE 
                WHEN t.seeders > 100 THEN " . floatval($SEEDERS_MANY) . "
                WHEN t.seeders > 50 THEN " . floatval($SEEDERS_MEDIUM) . "
                ELSE 1.0
            END *
            
            /* Age factor бонусы */
            CASE 
                WHEN (UNIX_TIMESTAMP() - t.added) > 15552000 THEN " . floatval($AGE_OLD) . "  -- > 180 дней
                WHEN (UNIX_TIMESTAMP() - t.added) > 5184000 THEN " . floatval($AGE_MEDIUM) . " -- > 60 дней
                ELSE 1.0
            END *
            
            /* Promotion multipliers */
            (1.0 + 
                (t.free = 'yes') * " . floatval($PROMO_FREE) . " +
                (t.silver = 'yes') * " . floatval($PROMO_SILVER) . " +
                (t.doubleupload = 'yes') * " . floatval($PROMO_DOUBLE) . "
            )
        ) as raw_bonus_sum
        
    FROM peers p
    INNER JOIN torrents t ON t.id = p.torrent
    WHERE p.seeder = 'yes'
      AND p.userid > 0
      AND t.visible = 'yes'
      AND t.banned = 'no'
      AND t.isnuked = 'no'
      AND p.last_action >= UNIX_TIMESTAMP() - " . ($ANNOUNCE_INTERVAL * 3) . "
    GROUP BY p.userid
    HAVING raw_bonus_sum > 0
    ORDER BY NULL
";

$res = $db->sql_query($query);
++$CQueryCount;

if (!$db->num_rows($res)) {
    savelog("Seedbonus cron: done | no active seeders");
    exit(0);
}

// ===== 2. ОБРАБОТКА С КАПАМИ ИЗ НАСТРОЕК =====
$updates = [];
$batchNum = 0;
$maxedUsers = 0; // ← НОВЫЙ СЧЁТЧИК
$stats = [
    'processed' => 0, 
    'updated' => 0, 
    'total_bonus' => 0.0, 
    'queries' => 1,
    'maxed_users' => 0 // ← Добавляем в статистику
];

$allUserIds = [];
$allRows = [];

// Собираем все данные в массивы
while ($row = $db->fetch_array($res)) {
    $userid = (int)$row['userid'];
    $allUserIds[] = $userid;
    
    $allRows[$userid] = [
        'torrents_count' => (int)$row['torrents_count'],
        'avg_hours_seeded' => max(0, (float)$row['avg_hours_seeded']), // Защита от отрицательных
        'raw_bonus' => max(0, (float)$row['raw_bonus_sum']), // Защита от отрицательных
    ];
}


// Получаем текущие бонусы одной пачкой
$currentBonuses = [];
if (!empty($allUserIds)) {
    // Разбиваем на части если ID слишком много
    $idChunks = array_chunk($allUserIds, 5000);
    foreach ($idChunks as $chunk) {
        $idsList = implode(',', $chunk);
        $bonusRes = $db->sql_query("SELECT id, seedbonus FROM users WHERE id IN ($idsList)");
        ++$CQueryCount;
        $stats['queries']++;

        while ($bonusRow = $db->fetch_array($bonusRes)) {
            $currentBonuses[$bonusRow['id']] = max(0, (float)$bonusRow['seedbonus']); // Защита от отрицательных
        }
    }
}

// ===== Основной цикл расчёта с настройками из БД =====
foreach ($allRows as $userid => $data) {
    $torrentsCount = max(0, $data['torrents_count']); // Защита от отрицательных
    $avgHoursSeeded = max(0, $data['avg_hours_seeded']); // Защита от отрицательных
    $rawBonus = max(0, $data['raw_bonus']); // Защита от отрицательных
    
    // Проверка на аномальные значения
    if ($torrentsCount > 10000 || $avgHoursSeeded > 1000 || $rawBonus > 100000) {
        savelog("WARNING: User $userid has abnormal values: torrents=$torrentsCount, hours=$avgHoursSeeded, raw=$rawBonus");
        continue;
    }
    
    // Множитель по количеству торрентов (из настроек)
    $capMul = calculateTorrentMultiplier($torrentsCount, $TORRENT_MULTIPLIER_TYPE, $FLAT_MULTIPLIER);
    
    // Использовать эвристику времени если включено (исправленный расчет)
    if ($ENABLE_HEURISTIC) {
        $heuristicHoursPerDay = getHeuristicHours($torrentsCount, [
            'heuristic_50' => $HEURISTIC_50,
            'heuristic_40' => $HEURISTIC_40,
            'heuristic_30' => $HEURISTIC_30,
            'heuristic_20' => $HEURISTIC_20,
            'heuristic_10' => $HEURISTIC_10,
            'heuristic_5' => $HEURISTIC_5,
            'heuristic_1' => $HEURISTIC_1,
        ]);
        
        // Пересчитываем эвристику (часы в день) в часы за интервал крона
        $heuristicForInterval = $heuristicHoursPerDay * ($CRON_INTERVAL_HOURS / 24);
        
        // Используем максимальное значение из реального времени и эвристики
        $avgHoursSeeded = max($avgHoursSeeded, $heuristicForInterval);
    }
    
    // Расчёт часового бонуса
    $hourlyBonus = $rawBonus * $BASE_BONUS * $capMul;
    
    // ВАЖНО: Применяем часовой кап
    if ($hourlyBonus > $HOUR_CAP) {
        $hourlyBonus = $HOUR_CAP;
    }
    
    // Учитываем реальное время сидирования
    $finalBonus = $hourlyBonus * $avgHoursSeeded;
    $finalBonus = round($finalBonus, 1); // Для decimal(9,1)
    
    // Проверка на отрицательные и минимальные значения
    if ($finalBonus < 0.1 || $finalBonus < 0) {
        continue;
    }
    
    // Проверка на аномально большие значения
    $maxPossibleBonus = $HOUR_CAP * $CRON_INTERVAL_HOURS * 2; // Запас в 2 раза
    if ($finalBonus > $maxPossibleBonus) {
        savelog("WARNING: User $userid bonus too high: $finalBonus, capping to $maxPossibleBonus");
        $finalBonus = min($finalBonus, $maxPossibleBonus);
    }
    
    // Проверка лимита в БД
    $current = $currentBonuses[$userid] ?? 0.0;
    
    // Пользователь уже на максимуме
    if ($current >= $MAX_DB_VALUE) {
        $maxedUsers++;
        continue;
    }
    
    // Проверяем, чтобы бонус не превышал максимум
    if ($current + $finalBonus > $MAX_DB_VALUE) {
        $finalBonus = $MAX_DB_VALUE - $current;
        $finalBonus = round($finalBonus, 1);
        if ($finalBonus < 0.1) {
            $maxedUsers++;  // бонус слишком мал — считается как maxed
            continue;
        }
    }
    
    $updates[] = [
        'userid' => $userid,
        'bonus' => $finalBonus,
        'torrents' => $torrentsCount
    ];
    
    $stats['processed']++;
    $stats['total_bonus'] += $finalBonus;
    
    // Пачечная обработка
    if (count($updates) >= $BATCH_SIZE) {
        $batchUpdated = processBatch($updates, $batchNum, $db, $CQueryCount);
        $stats['updated'] += $batchUpdated;
        $stats['queries'] += ($batchUpdated > 0 ? 2 : 1);
        
        $updates = [];
        $batchNum++;
        usleep(50000); // 0.05 сек для MyISAM
    }
}

// Последняя пачка
if (!empty($updates)) {
    $batchUpdated = processBatch($updates, $batchNum, $db, $CQueryCount);
    $stats['updated'] += $batchUpdated;
    $stats['queries'] += ($batchUpdated > 0 ? 2 : 1);
}

// Добавляем maxed users в статистику
$stats['maxed_users'] = $maxedUsers;

// ===== 3. ФИНАЛЬНАЯ СТАТИСТИКА =====
savelog(sprintf(
    "Seedbonus cron: done | users=%d | updated=%d | maxed=%d | total=%.1f | queries=%d",
    $stats['processed'],
    $stats['updated'],
    $stats['maxed_users'],
    $stats['total_bonus'],
    $CQueryCount
));



// ===== ФУНКЦИИ =====

/**
 * Расчёт множителя по количеству торрентов
 */
function calculateTorrentMultiplier($torrentsCount, $type, $flatValue = 1.0) {
    $torrentsCount = max(0, intval($torrentsCount)); // Защита от отрицательных
    $flatValue = max(0, floatval($flatValue)); // Защита от отрицательных
    
    switch ($type) {
        case 'penalty':
            if ($torrentsCount <= 20) return 1.0;
            if ($torrentsCount <= 50) return 0.9;
            if ($torrentsCount <= 100) return 0.8;
            return 0.7;
            
        case 'neutral':
            return ($torrentsCount <= 100) ? 1.0 : 0.9;
            
        case 'reward':
            if ($torrentsCount >= 100) return 1.2;
            if ($torrentsCount >= 50) return 1.1;
            if ($torrentsCount >= 20) return 1.0;
            return 0.9;
            
        case 'flat':
            return $flatValue;
            
        default:
            return 1.0;
    }
}

/**
 * Получение эвристического времени сидирования (часов в день)
 */
function getHeuristicHours($torrentsCount, $settings) {
    $torrentsCount = max(0, intval($torrentsCount)); // Защита от отрицательных
    
    if ($torrentsCount >= 50) return floatval($settings['heuristic_50']);
    if ($torrentsCount >= 40) return floatval($settings['heuristic_40']);
    if ($torrentsCount >= 30) return floatval($settings['heuristic_30']);
    if ($torrentsCount >= 20) return floatval($settings['heuristic_20']);
    if ($torrentsCount >= 10) return floatval($settings['heuristic_10']);
    if ($torrentsCount >= 5) return floatval($settings['heuristic_5']);
    return floatval($settings['heuristic_1']);
}

/**
 * Обработка пачки обновлений
 */
function processBatch($updates, $batchNum, $db, &$queryCount) {
    if (empty($updates)) return 0;
    
    // Валидация данных
    $validUpdates = [];
    foreach ($updates as $update) {
        $userid = intval($update['userid']);
        $bonus = floatval($update['bonus']);
        if ($userid > 0 && $bonus > 0) {
            $validUpdates[] = ['userid' => $userid, 'bonus' => $bonus];
        }
    }
    
    if (empty($validUpdates)) return 0;
    
    $caseWhen = [];
    $userIds = [];
    $batchBonus = 0;
    
    foreach ($validUpdates as $update) {
        $caseWhen[] = "WHEN {$update['userid']} THEN {$update['bonus']}";
        $userIds[] = $update['userid'];
        $batchBonus += $update['bonus'];
    }
    
    $sql = "UPDATE users SET seedbonus = seedbonus + CASE id\n" .
           implode("\n", $caseWhen) . 
           "\nEND WHERE id IN (" . implode(',', $userIds) . ")";
    
    $db->sql_query($sql);
    ++$queryCount;
    
    $affected = $db->affected_rows();
    
    // Для MyISAM
    if ($affected > 0) {
        $db->sql_query("FLUSH TABLES");
        ++$queryCount;
    }
    
    
    return $affected;
}