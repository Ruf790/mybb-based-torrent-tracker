<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

// Days to keep each log (0 = never prune).
// Values from $config['log_pruning'] take precedence when set.
$log_pruning = array_merge([
    'admin_logs'     => 365,
    'mod_logs'       => 365,
    'mail_logs'      => 180,
    'user_mail_logs' => 180,
], array_map('intval', (array)($config['log_pruning'] ?? [])));

// Admin / moderator actions are in sitelog (category admin / moderator)
foreach (['admin_logs' => 'admin', 'mod_logs' => 'moderator'] as $key => $category) {
    $days = (int)($log_pruning[$key] ?? 0);
    if ($days > 0) {
        $db->sql_query_prepared(
            "DELETE FROM sitelog WHERE category = ? AND added < ?",
            [$category, TIMENOW - $days * DAY_IN_SECONDS]
        );
        ++$CQueryCount;
    }
}

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

unset($log_pruning, $log_tables, $key, $table, $days, $category);