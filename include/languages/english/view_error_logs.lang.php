<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['view_error_logs'] = array (
	// ── Page ──
	'page_title'               => 'View Error Logs',
	'head_title'               => 'Error Log Viewer',
	'head_subtitle'            => 'PHP and tracker logs from <code>/error_logs</code>, newest file first',

	// ── Errors / alerts ──
	'err_invalid_log'          => 'That log file doesn\'t exist or isn\'t allowed. Pick one from the list below.',
	'err_not_found'            => 'Log file not found.',

	// ── Flash messages ──
	'flash_csrf'               => 'Security check failed. Please refresh the page and try again.',
	'flash_deleted'            => '{1} deleted successfully.',
	'flash_delete_failed'      => 'Failed to delete {1}.',
	'flash_deleted_all'        => 'Successfully deleted {1} log file(s).',
	'flash_none_deleted'       => 'No logs were deleted.',

	// ── KPI tiles ──
	'kpi_files'                => 'Log files',
	'kpi_total_size'           => 'Total size on disk',
	'kpi_last_write'           => 'Last write',
	'kpi_errors'               => 'Errors in this file',

	// ── Time / units ──
	'time_just_now'            => 'just now',
	'time_min_ago'             => '{1} min ago',
	'time_h_ago'               => '{1} h ago',
	'time_d_ago'               => '{1} d ago',
	'unit_b'                   => 'B',
	'unit_kb'                  => 'KB',
	'unit_mb'                  => 'MB',
	'unit_gb'                  => 'GB',

	// ── Toolbar ──
	'lbl_log_file'             => 'Log file',
	'opt_choose_log'           => '— choose a log file —',
	'hint_search'              => 'Search in log — regex supported',
	'lbl_search'               => 'Search in log',
	'tip_search_key'           => 'Press / to search',
	'tip_clear'                => 'Clear (Esc)',
	'lbl_clear'                => 'Clear search',

	// ── Log panel ──
	'meta_lines'               => '{1} lines',
	'lbl_filter_level'         => 'Filter by level',
	'chip_all'                 => 'All',
	'tip_copy_line'            => 'Copy line',
	'no_match'                 => 'No lines match',

	// ── Log levels ──
	'lvl_error'                => 'Error',
	'lvl_warning'              => 'Warning',
	'lvl_security'             => 'Security',
	'lvl_install'              => 'Install',
	'lvl_notice'               => 'Notice',
	'lvl_default'              => 'Other',

	// ── Empty states ──
	'empty_read_failed_title'  => 'Unable to read this file',
	'empty_read_failed_text'   => 'Check file permissions on the server.',
	'empty_log_title'          => 'This log is empty',
	'empty_log_text'           => 'Nothing has been written here yet.',
	'empty_none_title'         => 'No log files',
	'empty_none_text'          => 'The error log directory is clean.',
	'empty_select_title'       => 'No log file selected',
	'empty_select_text'        => 'Choose a file from the list above to view its contents.',

	// ── Action bar ──
	'bar_files_summary'        => '{1} file(s) · {2}',
	'tip_jump_top'             => 'Jump to first line',
	'tip_jump_bottom'          => 'Jump to last line',
	'btn_download'             => 'Download',
	'btn_delete'               => 'Delete',
	'btn_delete_all'           => 'Delete all',
	'lbl_close'                => 'Close',

	// ── JS strings ──
	'js_invalid_regex'         => 'Invalid regex — searching as plain text',
	'js_found'                 => '{1} of {2} lines',
	'js_cancel'                => 'Cancel',
	'js_del_one_title'         => 'Delete this log?',
	'js_del_one_note'          => 'This can\'t be undone.',
	'js_del_one_plain'         => 'Delete {1}? This can\'t be undone.',
	'js_del_one_confirm'       => 'Delete file',
	'js_del_all_title'         => 'Delete all {1} log files?',
	'js_del_all_note'          => '{1} of log history will be removed permanently.',
	'js_del_all_plain'         => 'Delete all {1} log files? This can\'t be undone.',
	'js_del_all_confirm'       => 'Delete all',
);
?>
