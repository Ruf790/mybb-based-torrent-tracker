<?php
declare(strict_types=1);

/**
 * Exam / task card under the site header, styled like the leech warning
 * (errorss.css: error-card222 / card-header22). NexusPHP shows it via msgalert().
 *
 * Used in include/templates/default/header.php:
 *     $warnmessages[] = exam_render_header_notice((int)$CURUSER['id']);
 */

if (!defined('IN_TRACKER') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

require_once __DIR__ . '/functions_exam.php';

function exam_render_header_notice(int $uid): string
{
    global $db, $BASEURL;

    if ($uid <= 0) return '';

    // One indexed query per page view; nothing else for users without an exam
    $eu = $db->fetch_array($db->sql_query_prepared(
        'SELECT * FROM exam_users WHERE uid = ? AND status = ? ORDER BY exam_id DESC LIMIT 1',
        [$uid, EXAM_USER_STATUS_NORMAL]
    ));
    if (!$eu) return '';
    $exam = exam_get((int)$eu['exam_id']);
    if (!$exam) return '';

    // Same as NexusPHP: refresh progress on view, but at most once per 5 minutes
    $formatted = null;
    if (TIMENOW - exam_ts((string)$eu['updated_at']) >= EXAM_PROGRESS_VIEW_THROTTLE) {
        $formatted = exam_update_progress($eu, $exam)['formatted'] ?? null;
    }
    $formatted ??= exam_progress_formatted($exam, json_decode((string)$eu['progress'], true) ?: []);

    $e      = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $base   = $e((string)$BASEURL);
    $isTask = (int)$exam['type'] === EXAM_TYPE_TASK;
    $begin  = exam_user_begin($eu, $exam);
    $end    = exam_user_end($eu, $exam);
    $left   = max(0, $end - TIMENOW);
    $soon   = $left < 3 * 86400;
    $done   = count(array_filter($formatted, static fn(array $f): bool => $f['passed']));
    $total  = count($formatted);
    $allMet = $total > 0 && $done === $total;

    // Last 3 days - red, like the leech warning; otherwise the exam's own color
    $tone = $soon && !$allMet ? 'danger' : (EXAM_BG[(string)$exam['background_color']] ?? 'primary');

    if ($left > 0) {
        if (!function_exists('mkprettytime')) {
            require_once INC_PATH . '/functions_mkprettytime.php';
        }
        $leftText = mkprettytime($left);
    } else {
        $leftText = '';
    }

    $title = ($isTask ? 'You have a task in progress: ' : 'You have an exam in progress: ') . $e((string)$exam['name']);
    $sub   = $done . ' of ' . $total . ' requirements met &middot; '
           . date('d.m.Y H:i', $begin) . ' - ' . date('d.m.Y H:i', $end);

    if ($allMet) {
        $alertTone = 'success';
        $alert     = '<strong>Well done!</strong> All requirements are met. The ' . ($isTask ? 'task' : 'exam')
                   . ' will be counted as passed when its time ends'
                   . ((int)$exam['success_reward_bonus'] > 0
                        ? ' and you will get <strong>' . exam_num((int)$exam['success_reward_bonus']) . '</strong> bonus points.'
                        : '.');
    } elseif ($left === 0) {
        $alertTone = 'secondary';
        $alert     = '<strong>Time is up.</strong> Results will be counted within a few minutes.';
    } elseif ($isTask) {
        $alertTone = $soon ? 'danger' : 'info';
        $alert     = '<strong>' . ($soon ? 'Hurry up!' : 'Task!') . '</strong> Complete all requirements within '
                   . $e($leftText) . ' to get <strong>' . exam_num((int)$exam['success_reward_bonus']) . '</strong> bonus points.'
                   . ((int)$exam['fail_deduct_bonus'] > 0
                        ? ' Otherwise <strong>' . exam_num((int)$exam['fail_deduct_bonus']) . '</strong> bonus points will be deducted.'
                        : '');
    } else {
        $alertTone = $soon ? 'danger' : 'primary';
        $reward    = (int)$exam['success_reward_bonus'];
        $alert     = '<strong>' . ($soon ? 'Danger!' : 'Exam!') . '</strong> You need to meet all requirements within '
                   . $e($leftText) . '.'
                   . ($reward > 0 ? ' Pass it to get <strong>' . exam_num($reward) . '</strong> bonus points.' : '')
                   . ' Otherwise your account will be disabled.';
    }

    $rows = '';
    foreach ($formatted as $f) {
        $bar = $f['passed'] ? 'bg-success' : 'bg-' . $tone;
        $rows .= '<div class="exam-req' . ($f['passed'] ? ' is-done' : '') . '">'
            . '<div class="exam-req__line">'
            . '<span><i class="bi ' . ($f['passed'] ? 'bi-check-circle-fill' : 'bi-circle') . '"></i>' . $e($f['name']) . '</span>'
            . '<span><strong>' . $e($f['current_text']) . '</strong> / ' . $e($f['require_text']) . '</span>'
            . '</div>'
            . '<div class="progress" role="progressbar" aria-label="' . $e($f['name']) . '" aria-valuenow="' . $f['pct'] . '" aria-valuemin="0" aria-valuemax="100">'
            . '<div class="progress-bar ' . $bar . '" style="width:' . $f['pct'] . '%"></div></div>'
            . '</div>';
    }

    $desc = trim((string)$exam['description']) !== ''
        ? '<p class="exam-desc">' . nl2br($e((string)$exam['description'])) . '</p>'
        : '';
    $link = $isTask ? '<p class="exam-desc"><a href="' . $base . '/task.php">View all tasks or abandon this one</a></p>' : '';

    return '
    <link href="' . $base . '/include/templates/default/style/errorss.css" rel="stylesheet">
    <link href="' . $base . '/include/templates/default/style/exam.css?ver=3" rel="stylesheet">
    <div class="card error-card222 exam-card exam-card--' . $tone . '">
        <div class="card-header22">
            <i class="bi ' . ($isTask ? 'bi-list-check' : 'bi-mortarboard-fill') . ' error-icon2"></i>
            <div>
                <h2 class="mb-0">' . $title . '</h2>
                <p class="mb-0 opacity-75">' . $sub . '</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-' . $alertTone . '" role="alert">' . $alert . '</div>
            ' . $rows . $desc . $link . '
        </div>
    </div>';
}