<?php
declare(strict_types=1);

/**
 * TS Special Edition / MyBB Cron Helper Functions
 * Compatible with PHP 8.4
 */

if (!defined('IN_CRON')) {
    exit();
}

/**
 * Log system events to `sitelog` table
 */
function savelog(string $text): void
{
    global $db;

    $insert = [
        'added' => TIMENOW,
        'txt'   => $db->escape_string($text)
    ];

    $db->insert_query('sitelog', $insert);
}

/**
 * Log cron execution data to `ts_cron_log`
 */
function logcronaction(string $filename, int $queryCount, float $executeTime): void
{
    global $db;

    $sql = sprintf(
        "REPLACE INTO ts_cron_log (filename, querycount, executetime, runtime)
         VALUES ('%s', '%d', '%.4f', '%d')",
        $db->escape_string($filename),
        $queryCount,
        $executeTime,
        TIMENOW
    );

    $db->sql_query($sql);
}

/**
 * Calculate deadtime for peers (based on announce interval)
 */
function deadtime(): int
{
    $announceInterval = 900; // 15 minutes default
    return TIMENOW - (int)floor($announceInterval * 1.3);
}

/**
 * Send emails from mail queue
 */
function send_mail_queue(int $count = 10): void
{
    global $db, $cache, $plugins;

    if (isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('send_mail_queue_start');
    }

    $query = $db->simple_select(
        'mailqueue',
        '*',
        '',
        ['order_by' => 'mid', 'order_dir' => 'asc', 'limit_start' => 0, 'limit' => $count]
    );

    while ($email = $db->fetch_array($query)) {
        $db->delete_query('mailqueue', "mid='{$email['mid']}'");

        if ($db->affected_rows() === 1) {
            my_mail(
                $email['mailto'],
                $email['subject'],
                $email['message'],
                $email['mailfrom'],
                '',
                $email['headers'],
                true
            );
        }
    }

    if (isset($cache) && is_object($cache)) {
        $cache->update_mailqueue(TIMENOW, 0);
    }

    if (isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('send_mail_queue_end');
    }
}
