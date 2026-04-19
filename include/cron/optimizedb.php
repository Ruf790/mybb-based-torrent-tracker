<?php
/**
 * Database Optimization cron
 * Fully compatible with PHP 8.4
 */
declare(strict_types=1);
if (!defined('IN_CRON')) {
    exit();
}

$startTime    = microtime(true);
$currentTime  = date('Y-m-d H:i:s');
$databaseName = $config['database']['database'];

// Получаем список таблиц
$tableNames = $db->list_tables($databaseName);
++$CQueryCount;

if (empty($tableNames)) {
    savelog("DB Optimized INFO: [{$currentTime}] No tables found in '{$databaseName}'.");
    exit();
}

$optimizedTables = [];
$errorTables     = [];
$totalFreedMB    = 0.0;
$skippedCount    = 0;

foreach ($tableNames as $tableName) {

    // Статистика до оптимизации
    $query = $db->sql_query(
        "SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "`"
        . " WHERE Name = '" . $db->escape_string($tableName) . "'"
    );
    ++$CQueryCount;

    $table           = $db->fetch_array($query);
    $dataFree        = (int)($table['Data_free']    ?? 0);
    $engine          = $table['Engine']             ?? 'Unknown';
    $rowsCount       = (int)($table['Rows']         ?? 0);
    $tableSizeBefore = round(
        (($table['Data_length'] ?? 0) + ($table['Index_length'] ?? 0)) / 1024 / 1024, 2
    );

    // Пропускаем таблицы без фрагментации или не MyISAM
    if ($dataFree === 0 || $engine !== 'MyISAM') {
        ++$skippedCount;
        continue;
    }

    try {
        // Шаг 1 — оптимизация (дефрагментация + освобождение места)
        $db->optimize_table($tableName);
        ++$CQueryCount;

        // Шаг 2 — анализ (пересчёт статистики индексов для query optimizer)
        $db->analyze_table($tableName);
        ++$CQueryCount;

        // Статистика после оптимизации
        $queryAfter = $db->sql_query(
            "SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "`"
            . " WHERE Name = '" . $db->escape_string($tableName) . "'"
        );
        ++$CQueryCount;

        $tableAfter     = $db->fetch_array($queryAfter);
        $tableSizeAfter = round(
            (($tableAfter['Data_length'] ?? 0) + ($tableAfter['Index_length'] ?? 0)) / 1024 / 1024, 2
        );

        $freed        = max(0.0, $tableSizeBefore - $tableSizeAfter);
        $totalFreedMB += $freed;

        $optimizedTables[] = "{$tableName}("
            . ($freed > 0 ? "-{$freed}MB" : "no change")
            . ", rows:{$rowsCount})";

    } catch (Throwable $e) {
        $errorTables[] = "{$tableName}: " . $e->getMessage();
    }
}

// Итоговый лог
$elapsed        = round(microtime(true) - $startTime, 3);
$optimizedCount = count($optimizedTables);
$errorCount     = count($errorTables);

if ($optimizedCount > 0 && $totalFreedMB > 0) {
    // Полный лог со списком таблиц
    $tableList = implode(', ', $optimizedTables);
    savelog(
        "DB Optimized SUCCESS: [{$currentTime}] freed: {$totalFreedMB} MB in {$elapsed}s, skipped: {$skippedCount}. "
        . "Tables: [{$tableList}]"
    );
	++$CQueryCount;
} elseif ($optimizedCount > 0) {
    // Оптимизировали но место не освободилось
    savelog(
        "DB Optimized INFO: [{$currentTime}] {$optimizedCount} tables processed, no space freed ({$elapsed}s)."
    );
	++$CQueryCount;
} else {
    savelog(
        "DB Optimized INFO: [{$currentTime}] No tables required optimization ({$elapsed}s), skipped: {$skippedCount}."
    );
	++$CQueryCount;
}

// Ошибки отдельной строкой если были
if ($errorCount > 0) {
    savelog(
        "DB Optimized ERROR: [{$currentTime}] Failed tables ({$errorCount}): "
        . implode(' | ', $errorTables)
    );
	++$CQueryCount;
}
