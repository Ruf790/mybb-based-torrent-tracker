<?php
/**
 * MyBB / TS Special Edition Backup Script
 * Optimized for large databases
 */

if (!defined('IN_CRON')) {
    exit();
}

$BackupDirectory = THIS_PATH . '/admin/backup';
$db->set_table_prefix('');

$file = $BackupDirectory . '/backup_' . date("_Ymd_His_") . random_str(16);

if (function_exists('gzopen')) {
    $fp = gzopen($file . '.incomplete.sql.gz', 'w9');
} else {
    $fp = fopen($file . '.incomplete.sql', 'w');
}

$tables = $db->list_tables($config['database']['database'], $config['database']['table_prefix']);
++$CQueryCount;

$time = date('dS F Y \a\t H:i', TIMENOW);
$contents = "-- Ruff Tracker Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";

if (is_object($plugins)) {
    $args = [
        'task'   => &$task,
        'tables' => &$tables,
    ];
    $plugins->run_hooks('task_backupdb', $args);
}

// Проходим по всем таблицам
foreach ($tables as $table) {
    // Получаем структуру таблицы
    $structure = $db->show_create_table($table) . ";\n";
    ++$CQueryCount;
    $contents .= $structure;
    clear_overflow($fp, $contents);

    // Получаем список полей таблицы
    $fields_array = $db->show_fields_from($table);
    ++$CQueryCount;
    $field_list = array_map(fn($f) => $f['Field'], $fields_array);
    $fields = "`" . implode("`,`", $field_list) . "`";

    // Сбор данных таблицы
    if ($db->engine === 'mysqli') {
        $query = mysqli_query($db->read_link, "SELECT * FROM {$db->table_prefix}{$table}", MYSQLI_USE_RESULT);
        ++$CQueryCount;
    } else {
        $query = $db->simple_select($table);
        ++$CQueryCount;
    }

    while ($row = $db->fetch_array($query)) {
        $insert = "INSERT INTO {$table} ($fields) VALUES (";
        $comma = '';
        foreach ($field_list as $field) {
            if (!isset($row[$field]) || is_null($row[$field])) {
                $insert .= $comma . "NULL";
            } else if ($db->engine === 'mysqli') {
                $insert .= $comma . "'" . mysqli_real_escape_string($db->read_link, $row[$field]) . "'";
            } else {
                $insert .= $comma . "'" . $db->escape_string($row[$field]) . "'";
            }
            $comma = ',';
        }
        $insert .= ");\n";
        $contents .= $insert;

        // Сбрасываем в файл каждые ~1 МБ
        if (strlen($contents) > 1024 * 1024) {
            clear_overflow($fp, $contents);
        }
    }

    $db->free_result($query);
}

// Сбрасываем остаток после всех таблиц
if (!empty($contents)) {
    clear_overflow($fp, $contents);
}

$db->set_table_prefix(TABLE_PREFIX);

// Завершение и переименование файла
if (function_exists('gzopen')) {
    gzclose($fp);
    rename($file . '.incomplete.sql.gz', $file . '.sql.gz');
} else {
    fclose($fp);
    rename($file . '.incomplete.sql', $file . '.sql');
}

savelog('The database backup task successfully ran.');
++$CQueryCount;

// ======= Функция для безопасной записи и сброса содержимого =======
function clear_overflow($fp, &$contents)
{
    if (function_exists('gzopen')) {
        gzwrite($fp, $contents);
    } else {
        fwrite($fp, $contents);
    }
    $contents = '';
}
?>
