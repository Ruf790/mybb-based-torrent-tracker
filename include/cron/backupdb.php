<?php


declare(strict_types=1);

/**
 * MyBB / TS Special Edition Backup Script
 * Fully compatible with PHP 8.4
 * Optimized for large databases
 */



if (!defined('IN_CRON')) {
    exit();
}

$BackupDirectory = THIS_PATH . '/admin/backup';




$startTime = microtime(true);


if (!is_dir($BackupDirectory) && !mkdir($BackupDirectory, 0775, true)) {
    savelog('Error: Unable to create backup directory.');
    return;
}

$filenameBase = $BackupDirectory . '/backup_' . date('_Ymd_His_') . random_str(16);
$useGzip = function_exists('gzopen');

$fp = $useGzip
    ? gzopen($filenameBase . '.incomplete.sql.gz', 'w9')
    : fopen($filenameBase . '.incomplete.sql', 'w');

if (!$fp) {
    savelog('Error: Unable to open backup file for writing.');
    return;
}

$tables = $db->list_tables($config['database']['database']);
++$CQueryCount;

$time = date('dS F Y \a\t H:i', TIMENOW);
$contents = "-- {$SITENAME} Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";


if (isset($plugins) && is_object($plugins)) {
    $args = [
        'task'   => &$task,
        'tables' => &$tables,
    ];
    $plugins->run_hooks('task_backupdb', $args);
}


foreach ($tables as $table) {
    
    $structure = $db->show_create_table($table) . ";\n";
    ++$CQueryCount;
    $contents .= $structure;
    clear_overflow($fp, $contents, $useGzip);

    
    $fieldsArray = $db->show_fields_from($table);
    ++$CQueryCount;
    $fieldList = array_map(static fn(array $f): string => $f['Field'], $fieldsArray);
    $fieldsSql = '`' . implode('`,`', $fieldList) . '`';

   
    if ($db->engine === 'mysqli') {
        $query = mysqli_query($db->read_link, "SELECT * FROM {$table}", MYSQLI_USE_RESULT);
    } else {
        $query = $db->sql_query_prepared("SELECT * FROM {$table}");
    }
    ++$CQueryCount;

    if (!$query) {
        savelog("Warning: Unable to read table {$table}");
        continue;
    }

    while ($row = $db->fetch_array($query)) {
        $insert = "INSERT INTO {$table} ({$fieldsSql}) VALUES (";
        $values = [];

        foreach ($fieldList as $field) {
            $value = $row[$field] ?? null;
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escaped = ($db->engine === 'mysqli')
                    ? mysqli_real_escape_string($db->read_link, (string)$value)
                    : $db->escape_string((string)$value);
                $values[] = "'" . $escaped . "'";
            }
        }

        $contents .= $insert . implode(',', $values) . ");\n";

       
        if (strlen($contents) > 1024 * 1024) {
            clear_overflow($fp, $contents, $useGzip);
        }
    }

    $db->free_result($query);
}


if ($contents !== '') {
    clear_overflow($fp, $contents, $useGzip);
}




// Завершение и статистика
$endTime = microtime(true);
$elapsed = round($endTime - $startTime, 3);

if ($useGzip) {
    gzclose($fp);
    rename($filenameBase . '.incomplete.sql.gz', $filenameBase . '.sql.gz');
    $finalFile = $filenameBase . '.sql.gz';
} else {
    fclose($fp);
    rename($filenameBase . '.incomplete.sql', $filenameBase . '.sql');
    $finalFile = $filenameBase . '.sql';
}

$fileSize = file_exists($finalFile) ? round(filesize($finalFile) / 1024 / 1024, 2) : 0;
savelog("Database backup completed successfully ({$elapsed}s, {$fileSize} MB, gzip: " . ($useGzip ? 'yes' : 'no') . ")");
++$CQueryCount;


function clear_overflow($fp, string &$contents, bool $gzip = false): void
{
    if ($gzip) {
        gzwrite($fp, $contents);
    } else {
        fwrite($fp, $contents);
    }
    $contents = '';
}
