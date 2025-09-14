<?php
if (!defined('IN_CRON')) {
    exit();
}

$databaseName = $config['database']['database'];
$currentTime = date("Y-m-d H:i:s");

$dbCheck = $db->sql_query("SHOW DATABASES LIKE '" . $databaseName . "'");
++$CQueryCount;

if ($dbCheck === false) {
    savelog("ERROR: [{$currentTime}] Database check query failed: " . $db->error);
    exit();
}

if ($db->num_rows($dbCheck) === 0) {
    savelog("ERROR: [{$currentTime}] Database '{$databaseName}' does not exist. Skipping optimization.");
    exit();
}

$query = $db->sql_query("SHOW TABLE STATUS FROM `{$databaseName}`");
++$CQueryCount;

if ($query === false) {
    savelog("ERROR: [{$currentTime}] SHOW TABLE STATUS failed: " . $db->error);
    exit();
}

$optimizedTables = 0;

while ($table = $db->fetch_array($query)) {
    $tableName = $table['Name'];
    $dataFree = $table['Data_free'];
    $engine = $table['Engine'];
    $tableSizeBefore = round(($table['Data_length'] + $table['Index_length']) / 1024 / 1024, 2);
    $rowsCount = $table['Rows'];

    

    if ($dataFree > 0 && $engine == 'MyISAM') 
	{
        try {
            $db->optimize_table($tableName);
            ++$CQueryCount;

            $queryAfter = $db->sql_query("SHOW TABLE STATUS FROM `{$databaseName}` WHERE Name = '{$tableName}'");
            $tableAfter = $db->fetch_array($queryAfter);
            $tableSizeAfter = round(($tableAfter['Data_length'] + $tableAfter['Index_length']) / 1024 / 1024, 2);
            
            $sizeDifference = $tableSizeBefore - $tableSizeAfter;
            $sizeDifferenceStr = $sizeDifference != 0 ? " Freed space: {$sizeDifference} MB" : " No size change.";

            savelog("SUCCESS: [{$currentTime}] Table '{$tableName}' optimized successfully. Before: {$tableSizeBefore} MB, After: {$tableSizeAfter} MB. Data_free: {$dataFree} bytes, Engine: {$engine}, Rows: {$rowsCount}.{$sizeDifferenceStr}");
			++$CQueryCount;
			
            $optimizedTables++;
        } catch (Exception $e) {
            savelog("ERROR: [{$currentTime}] Failed to optimize table '{$tableName}': " . $e->getMessage());
        }
    }
}

if ($optimizedTables > 0) 
{
    savelog("INFO: [{$currentTime}] Optimization completed: {$optimizedTables} tables optimized.");
	++$CQueryCount;
} 
else 
{
    savelog("INFO: [{$currentTime}] No tables required optimization in database '{$databaseName}'.");
	++$CQueryCount;
}
?>
