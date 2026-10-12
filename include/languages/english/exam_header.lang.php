<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['exam_header'] = array (

	// ── Card title / subtitle ──
	'title_task'             => 'You have a task in progress: {1}',
	'title_exam'             => 'You have an exam in progress: {1}',
	'sub_progress'           => '{1} of {2} requirements met',

	// ── Alert headings ──
	'head_hurry'             => 'Hurry up!',
	'head_task'              => 'Task!',
	'head_danger'            => 'Danger!',
	'head_exam'              => 'Exam!',

	// ── Alerts: all requirements met ──
	'alert_done_task_bonus'  => '<strong>Well done!</strong> All requirements are met. The task will be counted as passed when its time ends and you will get <strong>{1}</strong> bonus points.',
	'alert_done_task'        => '<strong>Well done!</strong> All requirements are met. The task will be counted as passed when its time ends.',
	'alert_done_exam_bonus'  => '<strong>Well done!</strong> All requirements are met. The exam will be counted as passed when its time ends and you will get <strong>{1}</strong> bonus points.',
	'alert_done_exam'        => '<strong>Well done!</strong> All requirements are met. The exam will be counted as passed when its time ends.',

	// ── Alerts: time is up ──
	'alert_timeup'           => '<strong>Time is up.</strong> Results will be counted within a few minutes.',

	// ── Alerts: task in progress ──
	'alert_task'             => '<strong>{1}</strong> Complete all requirements within {2} to get <strong>{3}</strong> bonus points.',
	'alert_task_penalty'     => ' Otherwise <strong>{1}</strong> bonus points will be deducted.',

	// ── Alerts: exam in progress ──
	'alert_exam'             => '<strong>{1}</strong> You need to meet all requirements within {2}.',
	'alert_exam_reward'      => ' Pass it to get <strong>{1}</strong> bonus points.',
	'alert_exam_disabled'    => ' Otherwise your account will be disabled.',

	// ── Links ──
	'lnk_tasks'              => 'View all tasks or abandon this one',
);
?>
