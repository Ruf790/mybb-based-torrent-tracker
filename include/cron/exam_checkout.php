<?php
declare(strict_types=1);

/**
 * Подведение итогов закончившихся экзаменов и заданий (в NexusPHP: каждые 5 минут).
 * Рекомендуемый интервал: 5 минут.
 */

if (!defined('IN_CRON')) {
    die('Direct access denied.');
}

defined('TIMENOW') || define('TIMENOW', time());

require_once INC_PATH . '/functions_exam.php';

$n = exam_cron_checkout();
// Only when something happened (each checked-out exam is also logged in detail by
// exam_cron_checkout(): who passed, failed, was disabled)
if ($n > 0 && function_exists('savelog')) {
    savelog("[exams] checked out: {$n}");
}
