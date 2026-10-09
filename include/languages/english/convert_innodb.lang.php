<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['convert_innodb'] = array (
	// ── Page / header ──
	'page_title'        => 'Convert Tables to InnoDB + utf8mb4',
	'sec_subtitle'      => 'Switch legacy tables to the InnoDB engine and the utf8mb4 character set.',
	'badge_to_convert'  => '{1} to convert',
	'badge_all_done'    => 'All up to date',

	// ── KPI tiles ──
	'kpi_pending'       => 'Tables to convert',
	'kpi_engine'        => 'Need engine change',
	'kpi_charset'       => 'Need utf8mb4',
	'kpi_rows'          => '≈ {1} rows',

	// ── Warning (warn_text is output as-is, HTML allowed) ──
	'warn_title'        => 'Before you start',
	'warn_text'         => 'Each table is locked for the duration of its own conversion. Large tables can take a while &mdash; make a backup first and consider running this during low-traffic hours.',

	// ── Empty state ──
	'empty_title'       => 'Nothing to convert',
	'empty_text'        => 'Every table already uses InnoDB and utf8mb4.',

	// ── Toolbar ──
	'toolbar_pending'   => '{1} tables awaiting conversion',

	// ── Table columns ──
	'col_table'         => 'Table',
	'col_engine'        => 'Engine',
	'col_collation'     => 'Collation',
	'col_rows'          => 'Rows',
	'col_size'          => 'Size',
	'col_status'        => 'Status',

	// ── Labels ──
	'lbl_status_pending' => 'Pending',
	'aria_select_all'    => 'Select all tables',
	'aria_select_table'  => 'Select table {1}',

	// ── Buttons ──
	'btn_start'         => 'Start Conversion',

	// ── AJAX responses ──
	'err_invalid_token' => 'Invalid security token',
	'err_not_needed'    => "Table '{1}' does not need conversion",
	'err_alter_failed'  => 'ALTER TABLE failed',
	'msg_converted_in'  => 'Converted in {1}s',

	// ── JS ──
	'js_btn_converting'    => 'Converting...',
	'js_status_converting' => 'Converting...',
	'js_network_error'     => 'Network error',
	'js_progress'          => '{1} / {2} processed',
	'js_progress_failed'   => ' ({1} failed)',
	'js_done'              => 'Done',
	'js_done_errors'       => 'Done with errors',
);
?>
