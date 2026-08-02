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
$wrapped = $db->sql_query_prepared(
    "SHOW TABLES FROM `" . $db->escape_string($databaseName) . "`",
    []
);
++$CQueryCount;

$tableNames = [];
if ($wrapped && $wrapped->result) {
    while ($row = mysqli_fetch_row($wrapped->result)) {
        $tableNames[] = $row[0];
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

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
    $wrapped = $db->sql_query_prepared(
        "SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "`"
        . " WHERE Name = '" . $db->escape_string($tableName) . "'",
        []
    );
    ++$CQueryCount;

    $table = null;
    if ($wrapped && $wrapped->result) {
        $table = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH);
        mysqli_free_result($wrapped->result);
    }
    if ($wrapped && $wrapped->stmt) {
        mysqli_stmt_close($wrapped->stmt);
    }

    if (!$table) {
        continue;
    }

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
        // Шаг 1 — оптимизация
        $db->sql_query_prepared(
            "OPTIMIZE TABLE `" . $db->escape_string($tableName) . "`",
            []
        );
        ++$CQueryCount;

        // Шаг 2 — анализ
        $db->sql_query_prepared(
            "ANALYZE TABLE `" . $db->escape_string($tableName) . "`",
            []
        );
        ++$CQueryCount;

        // Статистика после оптимизации
        $wrappedAfter = $db->sql_query_prepared(
            "SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "`"
            . " WHERE Name = '" . $db->escape_string($tableName) . "'",
            []
        );
        ++$CQueryCount;

        $tableAfter     = null;
        $tableSizeAfter = 0.0;
        if ($wrappedAfter && $wrappedAfter->result) {
            $tableAfter = mysqli_fetch_array($wrappedAfter->result, MYSQLI_BOTH);
            mysqli_free_result($wrappedAfter->result);
        }
        if ($wrappedAfter && $wrappedAfter->stmt) {
            mysqli_stmt_close($wrappedAfter->stmt);
        }

        if ($tableAfter) {
            $tableSizeAfter = round(
                (($tableAfter['Data_length'] ?? 0) + ($tableAfter['Index_length'] ?? 0)) / 1024 / 1024, 2
            );
        }

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
    $tableList = implode(', ', $optimizedTables);
    savelog(
        "DB Optimized SUCCESS: [{$currentTime}] freed: {$totalFreedMB} MB in {$elapsed}s, skipped: {$skippedCount}. "
        . "Tables: [{$tableList}]"
    );
	++$CQueryCount;
} elseif ($optimizedCount > 0) {
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

if ($errorCount > 0) {
    savelog(
        "DB Optimized ERROR: [{$currentTime}] Failed tables ({$errorCount}): "
        . implode(' | ', $errorTables)
    );
	++$CQueryCount;
}
