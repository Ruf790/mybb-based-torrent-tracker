<?php
/**
 * Exams / tasks module: system PMs, requirement names, user-facing errors.
 * Used by include/functions_exam.php (loaded lazily via exam_t())
 * and by send_pm() with 'language_file' => 'exam'.
 */
if (!defined('IN_TRACKER') && !defined('IN_CRON') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

$language['exam_functions'] = array(

    // ── Types ──
    'type_exam' => 'Exam',
    'type_task' => 'Task',

    // ── Requirements ──
    'idx_uploaded'          => 'Uploaded',
    'idx_downloaded'        => 'Downloaded',
    'idx_seed_time_average' => 'Average seed time',
    'idx_seed_bonus'        => 'Bonus gained',
    'idx_seed_points'       => 'Seed points',
    'idx_upload_count'      => 'Torrents uploaded',
    'unit_hours'            => 'h',

    // ── Progress box (exam_render_current) ──
    'unit_d'        => 'd',
    'unit_h'        => 'h',
    'unit_m'        => 'm',
    'lbl_left'      => '{1} left',
    'lnk_all_tasks' => 'All tasks',

    // ── Errors (shown to users on task.php and to staff) ──
    'err_not_found'        => 'Exam not found.',
    'err_disabled'         => 'This exam is disabled.',
    'err_user_not_found'   => 'User not found.',
    'err_not_started'      => 'It has not started yet.',
    'err_ended'            => 'It has already ended.',
    'err_exam_staff_only'  => 'No permission: exams can only be assigned by staff, and not to staff.',
    'err_task_self_only'   => 'You can only claim a task for yourself.',
    'err_task_full'        => 'The maximum number of participants has been reached.',
    'err_not_target'       => 'This user does not match the target users of this exam or task.',
    'err_has_ongoing'      => 'Another exam or task is already in progress. Finish it first.',
    'err_end_before_begin' => 'End must be later than begin.',
    'err_recurring'        => 'Unknown recurring period: {1}',
    'err_time_condition'   => 'Invalid time condition: set exactly one of begin + end / duration / recurring.',
    'err_unknown_index'    => 'Unknown requirement: {1}',
    'err_no_such_task'     => 'You have no such task in progress.',
    'err_abandon_exam'     => 'Only tasks can be abandoned. An exam has to be finished.',
    'err_abandon_timeup'   => 'Time is up for this task, results are being counted.',
    'err_task_finished'    => 'This task is already finished.',

    // ── Pass-rate hints (staff) ──
    'hint_anomaly_low'      => 'Very few pass. Check the requirements (too hard for the time?), the target filters, and that stats are counted (announces).',
    'hint_anomaly_low_exam' => ' Every fail here disables an account.',
    'hint_anomaly_high'     => 'Almost everyone passes. The requirements may be too easy to check anything.',

    // ── PM: results ──
    'pm_task_done_subj'          => 'Task completed: {1}',
    'pm_task_done_msg'           => 'Congratulations! You completed the task [b]{1}[/b] in time ({2}) and received [b]{3}[/b] bonus points.',
    'pm_task_done_msg_noreward'  => 'Congratulations! You completed the task [b]{1}[/b] in time ({2}).',
    'pm_exam_passed_subj'        => 'Exam passed: {1}',
    'pm_exam_passed_msg'         => 'Congratulations! You passed the exam [b]{1}[/b] in time ({2}).',
    'pm_exam_passed_msg_bonus'   => 'Congratulations! You passed the exam [b]{1}[/b] in time ({2}). You received [b]{3}[/b] bonus points.',
    'pm_task_failed_subj'        => 'Task not completed: {1}',
    'pm_task_failed_msg'         => 'You did not complete the task [b]{1}[/b] in time ({2}). [b]{3}[/b] bonus points have been deducted.',
    'pm_task_failed_msg_free'    => 'You did not complete the task [b]{1}[/b] in time ({2}). No bonus points were deducted.',
    'pm_exam_failed_subj'        => 'Exam not passed: {1}',
    'pm_exam_failed_msg'         => 'You did not pass the exam [b]{1}[/b] in time ({2}), and your account has been disabled.',
    'pm_exam_failed_msg_staff'   => 'You did not pass the exam [b]{1}[/b] in time ({2}). Staff accounts are not disabled.',
    'pm_abandon_subj'            => 'Task abandoned: {1}',
    'pm_abandon_msg'             => 'You abandoned the task [b]{1}[/b]. [b]{2}[/b] bonus points have been deducted. You can claim another task now.',
    'pm_abandon_msg_free'        => 'You abandoned the task [b]{1}[/b]. No bonus points were deducted. You can claim another task now.',

    // ── PM: changes by staff ──
    'pm_end_changed_subj'        => 'End time changed: {1}',
    'pm_end_changed_msg'         => "The end time of [b]{1}[/b] was changed from {2} to {3}.\nChanged by: {4}",
    'pm_end_changed_msg_reason'  => "The end time of [b]{1}[/b] was changed from {2} to {3}.\nChanged by: {4}\nReason: {5}",
    'pm_time_changed_subj'       => 'Time changed: {1}',
    'pm_time_changed_msg'        => "The time of [b]{1}[/b] was changed from {2} to {3}.\nChanged by: {4}",
    'pm_req_changed_subj'        => 'Requirements changed: {1}',
    'pm_req_changed_msg'         => "The requirements of [b]{1}[/b] were changed:\n[list]\n{2}\n[/list]\nYour progress since the assignment is kept and is now checked against the new requirements.\nChanged by: {3}",

    // ── PM: pass-rate alert to admins ──
    // {1} name, {2} id, {3} rate with %, {4} passed, {5} finished, {6} disabled, {7} link
    'pm_anomaly_low_subj'  => 'Low pass rate: {1}',
    'pm_anomaly_high_subj' => 'High pass rate: {1}',
    'pm_anomaly_low_exam'  => "The exam [b]{1}[/b] (#{2}) has a very low pass rate: [b]{3}[/b] ({4} of {5} finished, {6} accounts disabled).\n\nVery few pass. Check the requirements (too hard for the time?), the target filters, and that stats are counted (announces). Every fail here disables an account.\n\n[url={7}]Open participants[/url]",
    'pm_anomaly_low_task'  => "The task [b]{1}[/b] (#{2}) has a very low pass rate: [b]{3}[/b] ({4} of {5} finished).\n\nVery few pass. Check the requirements (too hard for the time?), the target filters, and that stats are counted (announces).\n\n[url={7}]Open participants[/url]",
    'pm_anomaly_high_exam' => "The exam [b]{1}[/b] (#{2}) has a very high pass rate: [b]{3}[/b] ({4} of {5} finished).\n\nAlmost everyone passes. The requirements may be too easy to check anything.\n\n[url={7}]Open participants[/url]",
    'pm_anomaly_high_task' => "The task [b]{1}[/b] (#{2}) has a very high pass rate: [b]{3}[/b] ({4} of {5} finished).\n\nAlmost everyone passes. The requirements may be too easy to check anything.\n\n[url={7}]Open participants[/url]",
);
