<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['useractivity'] = array (
	// ── Page ──
	'page_title'           => 'Active Users — {1} – {2}',

	// ── KPI tiles ──
	'kpi_total_users'      => 'Total unique users',
	'kpi_avg_time_day'     => 'Avg time online / day (hrs)',
	'kpi_period'           => 'Period',
	'kpi_days'             => '{1} days',

	// ── Filter form ──
	'lbl_from'             => 'From',
	'lbl_to'               => 'To',
	'aria_date_preset'     => 'Date range preset',

	// ── Preset options ──
	'opt_preset'           => '— Preset —',
	'opt_last_7'           => 'Last 7 days',
	'opt_last_30'          => 'Last 30 days',
	'opt_this_month'       => 'This month',
	'opt_last_month'       => 'Last month',

	// ── Buttons ──
	'btn_show'             => 'Show',
	'btn_reset'            => 'Reset',
	'btn_toggle_users'     => 'Toggle Users',
	'btn_toggle_time'      => 'Toggle Time',
	'btn_toggle_avg'       => 'Toggle Avg',
	'btn_chart_type'       => 'Bar / Line',
	'btn_refresh'          => 'Refresh',
	'btn_csv'              => 'CSV',
	'btn_png'              => 'PNG',
	'btn_json'             => 'JSON',

	// ── Sections ──
	'sec_chart'            => 'Active users: {1} – {2}',
	'sec_users_on_date'    => 'Users active on {1}',

	// ── Messages ──
	'msg_loading'          => 'Loading…',
	'msg_no_users'         => 'No users active on this date.',

	// ── JS: chart ──
	'js_axis_time'         => 'Time (hrs)',
	'js_axis_users'        => 'Users Count',
	'js_series_users'      => 'Users Count',
	'js_series_total_time' => 'Total Time Online (hrs)',
	'js_series_avg_time'   => 'Avg Time per User (hrs)',
	'js_suffix_users'      => 'users',
	'js_suffix_hrs'        => 'hrs',

	// ── JS: CSV export columns ──
	'js_csv_date'          => 'Date',
	'js_csv_users'         => 'Users',
	'js_csv_total_time'    => 'Total Time (hrs)',
	'js_csv_avg_time'      => 'Avg Time (hrs)',
);
?>
