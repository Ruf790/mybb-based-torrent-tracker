<?php
/**
 * TS Special Edition / MyBB Database Optimization Task
 * Fully compatible with PHP 8.4
 */

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

$startTime = microtime(true);
$currentTime = date('Y-m-d H:i:s');
$databaseName = $config['database']['database'];

$dbCheck = $db->sql_query("SHOW DATABASES LIKE '" . $db->escape_string($databaseName) . "'");
++$CQueryCount;

if ($dbCheck === false) {
    savelog("ERROR: [{$currentTime}] Database check query failed: " . $db->error);
    exit();
}

if ($db->num_rows($dbCheck) === 0) {
    savelog("ERROR: [{$currentTime}] Database '{$databaseName}' does not exist. Skipping optimization.");
    exit();
}

$query = $db->sql_query("SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "`");
++$CQueryCount;

if ($query === false) {
    savelog("ERROR: [{$currentTime}] SHOW TABLE STATUS failed: " . $db->error);
    exit();
}

$optimizedTables = 0;
$totalFreedMB = 0.0;

while ($table = $db->fetch_array($query)) {
    $tableName = $table['Name'] ?? '';
    $dataFree = (int)($table['Data_free'] ?? 0);
    $engine = $table['Engine'] ?? 'Unknown';
    $tableSizeBefore = round((($table['Data_length'] ?? 0) + ($table['Index_length'] ?? 0)) / 1024 / 1024, 2);
    $rowsCount = (int)($table['Rows'] ?? 0);

    if ($dataFree > 0 && $engine === 'MyISAM') {
        try {
            $db->optimize_table($tableName);
            ++$CQueryCount;

            $queryAfter = $db->sql_query("SHOW TABLE STATUS FROM `" . $db->escape_string($databaseName) . "` WHERE Name = '" . $db->escape_string($tableName) . "'");
            $tableAfter = $db->fetch_array($queryAfter);

            $tableSizeAfter = round((($tableAfter['Data_length'] ?? 0) + ($tableAfter['Index_length'] ?? 0)) / 1024 / 1024, 2);
            $sizeDifference = max(0, $tableSizeBefore - $tableSizeAfter);
            $totalFreedMB += $sizeDifference;

            $sizeDifferenceStr = $sizeDifference > 0
                ? " Freed space: {$sizeDifference} MB"
                : " No size change.";

            savelog("SUCCESS: [{$currentTime}] Table '{$tableName}' optimized successfully. "
                . "Before: {$tableSizeBefore} MB, After: {$tableSizeAfter} MB. "
                . "Data_free: {$dataFree} bytes, Engine: {$engine}, Rows: {$rowsCount}.{$sizeDifferenceStr}");
            ++$CQueryCount;

            ++$optimizedTables;
        } catch (Throwable $e) {
            savelog("ERROR: [{$currentTime}] Failed to optimize table '{$tableName}': " . $e->getMessage());
        }
    }
}

$elapsed = round(microtime(true) - $startTime, 3);

if ($optimizedTables > 0) {
    savelog("INFO: [{$currentTime}] Optimization completed in {$elapsed}s. "
        . "{$optimizedTables} tables optimized, total freed: {$totalFreedMB} MB.");
    ++$CQueryCount;
} else {
    savelog("INFO: [{$currentTime}] No tables required optimization in '{$databaseName}' ({$elapsed}s).");
    ++$CQueryCount;
}
