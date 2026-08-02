<?php
declare(strict_types=1);



if (!defined('IN_CRON')) {
    exit();
}

/**
 * Log system events to `sitelog` table
 */

function savelog(string $Text, string $category = '', int $level = 0): void
{
    global $db, $CURUSER;

    // Определяем category автоматически если не передана
    if (empty($category)) {
        $text_lower = strtolower($Text);

        if (strpos($text_lower, 'screenshot') !== false) {
            $category = 'screenshot';
        } elseif (strpos($text_lower, 'torrent') !== false || strpos($text_lower, 'uploaded') !== false) {
            $category = 'torrent';
        } elseif (strpos($text_lower, 'seedbonus') !== false) {
            $category = 'cron';
        } elseif (strpos($text_lower, 'sql error') !== false || strpos($text_lower, '[sql error]') !== false) {
            $category = 'error';
            $level = 2; // автоматически danger
        } elseif (strpos($text_lower, 'attempt') !== false || strpos($text_lower, 'unwanted') !== false) {
            $category = 'security';
            $level = 2;
        } elseif (strpos($text_lower, 'settings updated') !== false) {
            $category = 'settings';
            $level = 1;
        } elseif (strpos($text_lower, 'banned') !== false) {
            $category = 'ban';
            $level = 1;
        } elseif (strpos($text_lower, 'deleted') !== false) {
            $category = 'deletion';
            $level = 1;
        } elseif (strpos($text_lower, 'mail') !== false) {
            $category = 'mail';
        } elseif (strpos($text_lower, 'cron') !== false || strpos($text_lower, 'task') !== false) {
            $category = 'cron';
        } elseif (strpos($text_lower, 'warning') !== false) {
            $category = 'warning';
            $level = 1;
        } else {
            $category = 'general';
        }
    }

    // Определяем uid
    $uid = !empty($CURUSER['id']) ? (int)$CURUSER['id'] : 0;


    $db->sql_query_prepared(
        "INSERT INTO sitelog (added, uid, ipaddress, txt, category, level) VALUES (?, ?, ?, ?, ?, ?)",
        [TIMENOW, $uid, my_inet_pton(get_ip()), $Text, $category, $level]
    );
}


/**
 * Log cron execution data to `ts_cron_log`
 */
function logcronaction(string $filename, int $queryCount, float $executeTime): void
{
    global $db;

    $db->sql_query_prepared(
        "REPLACE INTO cron_log (filename, querycount, executetime, runtime) VALUES (?, ?, ?, ?)",
        [$filename, $queryCount, $executeTime, TIMENOW]
    );
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

    $query = $db->sql_query_prepared(
        "SELECT * FROM mailqueue ORDER BY mid ASC LIMIT 0, ?",
        [$count]
    );

    while ($email = $db->fetch_array($query)) {
        $db->sql_query_prepared("DELETE FROM mailqueue WHERE mid = ?", [$email['mid']]);

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