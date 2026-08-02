<?php

declare(strict_types=1);

/**
 * Database Table Check & Repair (MyBB / TS SE)
 * Compatible with PHP 8.4
 */



if (!isset($db) || !in_array($db->type, ['mysql', 'mysqli'], true)) {
    return;
}

@set_time_limit(0);

$okMessages = [
    "The storage engine for the table doesn't support check",
    "Table is already up to date",
    "OK"
];

$tablesList = '';
$comma = '';
$repairedTables = [];
$settingDone = false;

$configDatabase = $config['database']['database'] ?? '';

if ($configDatabase === '') {
    savelog('Error: Database name not set in config.');
    return;
}


$tables = $db->list_tables($configDatabase);
++$CQueryCount;

if (!empty($tables)) {
    foreach ($tables as $table) {
        $tablesList .= "{$comma}{$table} ";
        $comma = ',';
    }

    
    if ($tablesList !== '') {
        $checkQuery = $db->sql_query("CHECK TABLE {$tablesList} CHANGED");
        ++$CQueryCount;

        while ($table = $db->fetch_array($checkQuery)) {
            if (!in_array($table['Msg_text'], $okMessages, true)) {
                // При необходимости можно включить закрытие форума на время ремонта
                /*
                if ($table['Table'] !== "{$configDatabase}.{$configPrefix}settings" && $settingDone === false) {
                    $db->update_query("settings", ['value' => 1], "name='boardclosed'", 1);
                    $db->update_query("settings", [
                        'value' => $db->escape_string($lang->error_database_repair)
                    ], "name='boardclosed_reason'", 1);
                    rebuild_settings();
                    $settingDone = true;
                }
                */

                $db->sql_query("REPAIR TABLE {$table['Table']}");
                $repairedTables[] = $table['Table'];
                ++$CQueryCount;
            }
        }
    }
}


if (!empty($repairedTables)) {
    savelog(sprintf(
        'Notice: Database check successfully repaired the following table(s): %s',
        implode(', ', $repairedTables)
    ));
} else {
    savelog('Notice: Database check completed — no corrupted tables found.');
}