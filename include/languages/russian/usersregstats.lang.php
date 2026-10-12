<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['usersregstats'] = array (
	// ── Page titles ──
	'sec_title_day'            => 'Регистрации пользователей — по дням',
	'sec_title_month'          => 'Регистрации пользователей — по месяцам',
	'sec_title_year'           => 'Регистрации пользователей — по годам',

	// ── Chart X-axis titles ──
	'lbl_axis_day'             => 'Дата',
	'lbl_axis_month'           => 'Месяц',
	'lbl_axis_year'            => 'Год',

	// ── Grouping units (used in "Avg per {1}" / "Max per {1}") ──
	'unit_day'                 => 'день',
	'unit_month'               => 'месяц',
	'unit_year'                => 'год',

	// ── Range presets ──
	'btn_last_7_days'          => 'За 7 дней',
	'btn_last_30_days'         => 'За 30 дней',
	'btn_this_year'            => 'В этом году',
	'btn_all_time'             => 'За всё время',

	// ── Summary ──
	'lbl_in_range'             => 'За период: {1}',
	'lbl_this_week'            => 'За неделю: {1}',
	'lbl_this_month'           => 'За месяц: {1}',
	'lbl_last_registered'      => 'Последний зарегистрированный:',

	// ── Mini cards ──
	'lbl_avg_per'              => 'В среднем за {1}',
	'lbl_max_per'              => 'Максимум за {1}',

	// ── Filter form ──
	'lbl_from'                 => 'С',
	'lbl_to'                   => 'По',
	'opt_day'                  => 'По дням',
	'opt_month'                => 'По месяцам',
	'opt_year'                 => 'По годам',
	'btn_filter'               => 'Показать',

	// ── Chart controls ──
	'lbl_chart_bar'            => 'Столбцы',
	'lbl_chart_line'           => 'Линия',
	'lbl_cumulative'           => 'Нарастающим итогом',
	'btn_csv'                  => 'CSV',
	'btn_png'                  => 'PNG',

	// ── JS strings (passed to AGS_LANG without the js_ prefix) ──
	'js_dataset_label'         => 'Регистрации',
	'js_tooltip_users'         => 'Пользователей: {1}',
	'js_axis_users'            => 'Пользователи',
	'js_csv_col_date'          => 'Дата',
	'js_csv_col_registrations' => 'Регистрации',
);
?>
