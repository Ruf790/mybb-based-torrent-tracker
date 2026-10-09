<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['settings_history'] = array (
	// ── Page ──────────────────────────────────────────────
	'page_title'         => 'Settings History',
	'badge_total'        => 'Total: {1}',
	'btn_back'           => 'Back to Settings',

	// ── Stats ─────────────────────────────────────────────
	'stat_info'          => 'Info',
	'stat_warnings'      => 'Warnings',
	'stat_errors'        => 'Errors',
	'stat_today'         => 'Today',
	'stat_week'          => 'This Week',

	// ── Filters ───────────────────────────────────────────
	'ph_search'          => 'Search in logs...',
	'ph_user'            => 'Search by user...',
	'btn_filter'         => 'Filter',
	'btn_reset'          => 'Reset',

	// ── Table ─────────────────────────────────────────────
	'th_datetime'        => 'Date/Time',
	'th_user'            => 'User',
	'th_event'           => 'Event',
	'th_level'           => 'Level',
	'th_ip'              => 'IP',
	'th_actions'         => 'Actions',
	'badge_new'          => 'NEW',
	'lbl_unknown'        => 'Unknown',
	'lbl_system'         => 'System',
	'btn_show_more'      => 'Show more',
	'tip_profile'        => 'View user profile',
	'tip_copy'           => 'Copy log',

	// ── Levels ────────────────────────────────────────────
	'lvl_info'           => 'Info',
	'lvl_warning'        => 'Warning',
	'lvl_error'          => 'Error',

	// ── Empty state ───────────────────────────────────────
	'empty_title'        => 'No history found',
	'empty_text'         => 'Settings changes will appear here as they happen',
	'btn_go_settings'    => 'Go to Settings',

	// ── Actions ───────────────────────────────────────────
	'btn_autorefresh'    => 'Auto-refresh',
	'lbl_on'             => 'ON',
	'btn_cleanup'        => 'Cleanup Old Logs ({1}+ days)',
	'btn_export_csv'     => 'Export CSV',
	'btn_export_json'    => 'Export JSON',
	'btn_refresh'        => 'Refresh',

	// ── Flash messages ────────────────────────────────────
	'flash_csrf'         => 'Security check failed. Please try again.',
	'flash_deleted'      => 'Old logs deleted: {1}',

	// ── CSV export columns ────────────────────────────────
	'csv_datetime'       => 'Date/Time',
	'csv_uid'            => 'User ID',
	'csv_user'           => 'User',
	'csv_event'          => 'Event',
	'csv_level'          => 'Level',
	'csv_ip'             => 'IP Address',

	// ── JS strings ────────────────────────────────────────
	'js_show_more'       => 'Show more',
	'js_show_less'       => 'Show less',
	'js_on'              => 'ON',
	'js_off'             => 'OFF',
	'js_confirm_cleanup' => "⚠️ Are you sure you want to delete settings logs older than {1} days?\n\nThis action cannot be undone!",
);
?>
