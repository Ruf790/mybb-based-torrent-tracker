<?php
declare(strict_types=1);

/**
 * Автовыдача экзаменов с is_discovered = 1 (в NexusPHP: каждую минуту).
 * Рекомендуемый интервал: 5 минут.
 */

if (!defined('IN_CRON')) {
    die('Direct access denied.');
}

defined('TIMENOW') || define('TIMENOW', time());

require_once INC_PATH . '/functions_exam.php';

$n = exam_cron_assign();
// Only when something happened: every run with 0 would flood the site log
if ($n > 0 && function_exists('savelog')) {
    savelog("[exams] assigned: {$n}");
}
