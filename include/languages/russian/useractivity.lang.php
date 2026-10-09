<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['useractivity'] = array (
	// ── Page ──
	'page_title'           => 'Активность пользователей — {1} – {2}',

	// ── KPI tiles ──
	'kpi_total_users'      => 'Уникальных пользователей',
	'kpi_avg_time_day'     => 'Среднее время онлайн в день (ч)',
	'kpi_period'           => 'Период',
	'kpi_days'             => '{1} дн.',

	// ── Filter form ──
	'lbl_from'             => 'С',
	'lbl_to'               => 'По',
	'aria_date_preset'     => 'Быстрый выбор периода',

	// ── Preset options ──
	'opt_preset'           => '— Быстрый выбор —',
	'opt_last_7'           => 'Последние 7 дней',
	'opt_last_30'          => 'Последние 30 дней',
	'opt_this_month'       => 'Текущий месяц',
	'opt_last_month'       => 'Прошлый месяц',

	// ── Buttons ──
	'btn_show'             => 'Показать',
	'btn_reset'            => 'Сбросить',
	'btn_toggle_users'     => 'Пользователи',
	'btn_toggle_time'      => 'Общее время',
	'btn_toggle_avg'       => 'Среднее время',
	'btn_chart_type'       => 'Столбцы / Линия',
	'btn_refresh'          => 'Обновить',
	'btn_csv'              => 'CSV',
	'btn_png'              => 'PNG',
	'btn_json'             => 'JSON',

	// ── Sections ──
	'sec_chart'            => 'Активные пользователи: {1} – {2}',
	'sec_users_on_date'    => 'Были на сайте {1}',

	// ── Messages ──
	'msg_loading'          => 'Загрузка…',
	'msg_no_users'         => 'В этот день никто не заходил.',

	// ── JS: chart ──
	'js_axis_time'         => 'Время (ч)',
	'js_axis_users'        => 'Пользователей',
	'js_series_users'      => 'Пользователей',
	'js_series_total_time' => 'Общее время онлайн (ч)',
	'js_series_avg_time'   => 'Среднее на пользователя (ч)',
	'js_suffix_users'      => 'чел.',
	'js_suffix_hrs'        => 'ч',

	// ── JS: CSV export columns ──
	'js_csv_date'          => 'Дата',
	'js_csv_users'         => 'Пользователей',
	'js_csv_total_time'    => 'Общее время (ч)',
	'js_csv_avg_time'      => 'Среднее время (ч)',
);
?>
