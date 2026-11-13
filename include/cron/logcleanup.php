<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

// Days to keep each log (0 = never prune).
// Values from $config['log_pruning'] take precedence when set.
$log_pruning = array_merge([
    'mail_logs'      => 180,
    'user_mail_logs' => 180,
], array_map('intval', (array)($config['log_pruning'] ?? [])));

// log key => table
$log_tables = [
    'mail_logs'      => 'mailerrors',
    'user_mail_logs' => 'maillogs',
];

foreach ($log_tables as $key => $table) {
    $days = (int)($log_pruning[$key] ?? 0);
    if ($days <= 0) {
        continue;
    }

    $db->sql_query_prepared(
        "DELETE FROM `{$table}` WHERE dateline < ?",
        [TIMENOW - $days * DAY_IN_SECONDS]
    );
    ++$CQueryCount;
}

unset($log_pruning, $log_tables, $key, $table, $days);