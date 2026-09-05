<?php

declare(strict_types=1);

/**
 * Database Table Check & Repair (MyBB / TS SE)
 * Compatible with PHP 8.4
 *
 * NOTE: CHECK TABLE / REPAIR TABLE are administrative statements that
 * MySQL's prepared-statement protocol doesn't support at all (server
 * limitation, not our wrapper). Uses the isolated db_admin_raw_query()
 * helper (include/db_admin_raw.php) instead of a general-purpose method on
 * $db, so this stays a deliberate, explicit exception rather than a stray
 * unparameterized call that looks like every other query in the codebase.
 *
 * There is also no injection risk here: the table names come from
 * $db->list_tables(), i.e. MySQL's own information_schema, never from user
 * input. Identifiers can't be bound as `?` placeholders anyway (those only
 * work for values) — is_valid_table_name() below is the correct pattern for
 * dynamic identifiers: format validation + backtick-quoted interpolation.
 */

require_once INC_PATH . '/db_sql_query.php';

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

// Валидный MySQL identifier в бэктиках: буквы/цифры/подчёркивание/$.
// Первый символ МОЖЕТ быть цифрой - ограничение "не начинается с цифры"
// актуально только для НЕэкранированных идентификаторов в MySQL; здесь
// имя всегда подставляется в обратных кавычках (`{$table}`), так что
// имена вроде "2fa_pending" совершенно безопасны и не должны отсеиваться.
$is_valid_table_name = static fn(string $name): bool =>
    (bool) preg_match('/^[A-Za-z0-9_$]+$/', $name);

$tables = $db->list_tables($configDatabase);
++$CQueryCount;

if (!empty($tables)) {
    foreach ($tables as $table) {
        if (!$is_valid_table_name($table)) {
            savelog("Warning: skipped table with unexpected name format: {$table}");
            continue;
        }
        $tablesList .= "{$comma}`{$table}` ";
        $comma = ',';
    }


    if ($tablesList !== '') {
        $checkQuery = db_admin_raw_query($db, "CHECK TABLE {$tablesList} CHANGED");
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

                // $table['Table'] приходит от MySQL в формате "db.table" —
                // берём только часть после точки и снова валидируем формат
                // перед подстановкой (тот же protocol-limit, что и CHECK TABLE выше).
                $repairTableName = $table['Table'];
                if (str_contains($repairTableName, '.')) {
                    $repairTableName = substr($repairTableName, strrpos($repairTableName, '.') + 1);
                }

                if ($is_valid_table_name($repairTableName)) {
                    db_admin_raw_query($db, "REPAIR TABLE `{$repairTableName}`", write: true);
                    $repairedTables[] = $table['Table'];
                    ++$CQueryCount;
                } else {
                    savelog("Warning: skipped repair for table with unexpected name format: {$table['Table']}");
                }
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