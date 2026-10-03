<?php
declare(strict_types=1);

/**
 * Пересчёт прогресса всех идущих попыток (в NexusPHP: каждый час).
 * Рекомендуемый интервал: 60 минут.
 */

if (!defined('IN_CRON')) {
    die('Direct access denied.');
}

defined('TIMENOW') || define('TIMENOW', time());

require_once INC_PATH . '/functions_exam.php';

$r = exam_cron_update_progress();
// Only when there were attempts to update; a failure count is worth a line too
if ((int)$r['total'] > 0 && function_exists('savelog')) {
    savelog("[exams] progress updated: {$r['success']} / {$r['total']}");
}
