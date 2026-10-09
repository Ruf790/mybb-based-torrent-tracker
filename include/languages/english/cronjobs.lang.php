<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cronjobs'] = array (
	// ── Page titles ──────────────────────────────────────────────
	'page_title'          => 'Cron Jobs',
	'page_title_running'  => 'Running Cron...',

	// ── Header / KPI ─────────────────────────────────────────────
	'pane_title'          => 'Cron Jobs',
	'pane_sub'            => 'Scheduled tasks that keep the tracker ticking — intervals, runs and timings',
	'btn_new_job'         => 'New job',
	'kpi_jobs'            => 'Jobs',
	'kpi_active'          => 'Active',
	'kpi_overdue'         => 'Overdue',
	'kpi_avg'             => 'Avg run time',
	'val_seconds'         => '{1} s',

	// ── Sections ─────────────────────────────────────────────────
	'sec_jobs'            => 'Scheduled jobs',
	'sec_jobs_sub'        => '{1} job(s) · {2} active',
	'sec_log'             => 'Execution log',
	'sec_log_sub'         => 'Last 50 runs',

	// ── Table headers ────────────────────────────────────────────
	'th_job'              => 'Job',
	'th_every'            => 'Every',
	'th_last_run'         => 'Last run',
	'th_next_run'         => 'Next run',
	'th_status'           => 'Status',
	'th_file'             => 'File',
	'th_queries'          => 'Queries',
	'th_duration'         => 'Duration',
	'th_when'             => 'When',

	// ── Job rows: tags & labels ──────────────────────────────────
	'tag_missing'         => 'missing',
	'tag_overdue'         => 'overdue',
	'tag_active'          => 'Active',
	'tag_disabled'        => 'Disabled',
	'lbl_queries'         => '{1} queries',
	'lbl_never'           => 'never',
	'lbl_unknown'         => 'unknown',

	// ── Tooltips (title) ─────────────────────────────────────────
	'tip_missing'         => 'File not found in /cron/',
	'tip_logged'          => 'Execution is logged',
	'tip_run'             => 'Run now',
	'tip_edit'            => 'Edit',
	'tip_enable'          => 'Enable',
	'tip_disable'         => 'Disable',
	'tip_delete'          => 'Delete',

	// ── Empty states / filter ────────────────────────────────────
	'empty_jobs'          => 'No cron jobs yet',
	'btn_create_first'    => 'Create the first job',
	'empty_log'           => 'No runs logged yet',
	'ph_log_filter'       => 'Filter by file…',

	// ── Modal: Create / Edit ─────────────────────────────────────
	'pane_modal_new'      => 'New cron job',
	'lbl_close'           => 'Close',
	'lbl_loading'         => 'Loading…',
	'lbl_file'            => 'File',
	'hint_file'           => 'PHP file in the <code>/cron/</code> folder — no paths', // raw HTML
	'lbl_description'     => 'Description',
	'ph_description'      => 'What does this job do?',
	'lbl_run_every'       => 'Run every',
	'lbl_active'          => 'Active',
	'hint_active'         => 'Runs on schedule',
	'lbl_loglevel'        => 'Log runs',
	'hint_loglevel'       => 'Duration and queries in the log',
	'btn_cancel'          => 'Cancel',
	'btn_create'          => 'Create job',

	// ── Modal: interval unit labels ──────────────────────────────
	'lbl_unit_months'     => 'months',
	'lbl_unit_weeks'      => 'weeks',
	'lbl_unit_days'       => 'days',
	'lbl_unit_hours'      => 'hours',
	'lbl_unit_minutes'    => 'minutes',

	// ── Modal: interval presets ──────────────────────────────────
	'opt_preset_5m'       => '5 min',
	'opt_preset_15m'      => '15 min',
	'opt_preset_30m'      => '30 min',
	'opt_preset_1h'       => '1 hour',
	'opt_preset_6h'       => '6 hours',
	'opt_preset_1d'       => '1 day',
	'opt_preset_1w'       => '1 week',

	// ── "Running cron" page ──────────────────────────────────────
	'run_heading'         => 'Running Cron Job',
	'run_wait'            => 'Please wait while the job executes...',
	'run_redirect'        => 'Redirecting in {1}s...', // raw HTML: {1} = countdown <span>

	// ── Status badges (render_status_badge) ──────────────────────
	'badge_active'        => 'ACTIVE',
	'badge_disabled'      => 'DISABLED',
	'badge_yes'           => 'YES',
	'badge_no'            => 'NO',

	// ── Errors ───────────────────────────────────────────────────
	'err_security_title'  => 'Security Error',
	'err_security_token'  => 'Invalid security token. Please refresh the page and try again.',
	'err_not_found'       => 'Not found',

	// ── Flash messages ───────────────────────────────────────────
	'flash_bad_filename'  => 'Invalid filename. Only letters, numbers, underscore, hyphen and a .php extension are allowed (no paths).',
	'flash_min_interval'  => 'Please choose a run interval of at least 1 minute.',
	'flash_created'       => 'New cron job created successfully!',
	'flash_updated'       => 'Cron job updated successfully!',
	'flash_enabled'       => 'Cron job enabled successfully!',
	'flash_disabled'      => 'Cron job disabled successfully!',
	'flash_deleted'       => 'Cron job deleted successfully!',

	// ── JS strings (AGS_LANG, prefix js_ is stripped) ────────────
	'js_locale'           => 'en', // Intl.PluralRules locale for unit words
	'js_sum_min_interval' => 'Choose an interval of at least 1 minute',
	'js_sum_runs_every'   => 'Runs every {1}',
	'js_modal_title_new'  => 'New cron job',
	'js_modal_title_edit' => 'Edit cron job',
	'js_btn_create'       => 'Create job',
	'js_btn_save'         => 'Save changes',
	'js_btn_saving'       => 'Saving…',
	'js_load_failed'      => 'Could not load the cron job',
	'js_confirm_delete'   => 'Delete cron job {1}?',

	// ── JS: unit words by plural form (one / few / many) ─────────
	'js_unit_months_one'  => 'month',
	'js_unit_months_few'  => 'months',
	'js_unit_months_many' => 'months',
	'js_unit_weeks_one'   => 'week',
	'js_unit_weeks_few'   => 'weeks',
	'js_unit_weeks_many'  => 'weeks',
	'js_unit_days_one'    => 'day',
	'js_unit_days_few'    => 'days',
	'js_unit_days_many'   => 'days',
	'js_unit_hours_one'   => 'hour',
	'js_unit_hours_few'   => 'hours',
	'js_unit_hours_many'  => 'hours',
	'js_unit_minutes_one' => 'minute',
	'js_unit_minutes_few' => 'minutes',
	'js_unit_minutes_many'=> 'minutes',
	

	
	
);
?>
