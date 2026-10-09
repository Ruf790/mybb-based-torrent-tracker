<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['usersregstats'] = array (
	// ── Page titles ──
	'sec_title_day'            => 'User Registrations — by Day',
	'sec_title_month'          => 'User Registrations — by Month',
	'sec_title_year'           => 'User Registrations — by Year',

	// ── Chart X-axis titles ──
	'lbl_axis_day'             => 'Date',
	'lbl_axis_month'           => 'Month',
	'lbl_axis_year'            => 'Year',

	// ── Grouping units (used in "Avg per {1}" / "Max per {1}") ──
	'unit_day'                 => 'day',
	'unit_month'               => 'month',
	'unit_year'                => 'year',

	// ── Range presets ──
	'btn_last_7_days'          => 'Last 7 Days',
	'btn_last_30_days'         => 'Last 30 Days',
	'btn_this_year'            => 'This Year',
	'btn_all_time'             => 'All Time',

	// ── Summary ──
	'lbl_in_range'             => 'In range: {1}',
	'lbl_this_week'            => 'This week: {1}',
	'lbl_this_month'           => 'This month: {1}',
	'lbl_last_registered'      => 'Last registered:',

	// ── Mini cards ──
	'lbl_avg_per'              => 'Avg per {1}',
	'lbl_max_per'              => 'Max per {1}',

	// ── Filter form ──
	'lbl_from'                 => 'From',
	'lbl_to'                   => 'To',
	'opt_day'                  => 'Day',
	'opt_month'                => 'Month',
	'opt_year'                 => 'Year',
	'btn_filter'               => 'Filter',

	// ── Chart controls ──
	'lbl_chart_bar'            => 'Bar',
	'lbl_chart_line'           => 'Line',
	'lbl_cumulative'           => 'Cumulative',
	'btn_csv'                  => 'CSV',
	'btn_png'                  => 'PNG',

	// ── JS strings (passed to AGS_LANG without the js_ prefix) ──
	'js_dataset_label'         => 'Registrations',
	'js_tooltip_users'         => '{1} users',
	'js_axis_users'            => 'Users',
	'js_csv_col_date'          => 'Date',
	'js_csv_col_registrations' => 'Registrations',
);
?>
