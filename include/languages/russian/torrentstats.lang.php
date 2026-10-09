<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['torrentstats'] = array (

    // ── Page ──
    'page_title'        => 'Статистика раздач',
    'sec_dashboard'     => 'Статистика раздач',

    // ── Chart titles (by grouping) ──
    'sec_added_day'     => 'Новые раздачи (по дням)',
    'sec_added_month'   => 'Новые раздачи (по месяцам)',
    'sec_added_year'    => 'Новые раздачи (по годам)',

    // ── Preset buttons ──
    'btn_last_7'        => 'За 7 дней',
    'btn_last_30'       => 'За 30 дней',
    'btn_this_year'     => 'В этом году',
    'btn_all_time'      => 'За всё время',

    // ── Filter form ──
    'lbl_from'          => 'С:',
    'lbl_to'            => 'По:',
    'lbl_group_by'      => 'Группировка:',
    'opt_day'           => 'По дням',
    'opt_month'         => 'По месяцам',
    'opt_year'          => 'По годам',
    'btn_filter'        => 'Показать',

    // ── Summary ──
    'lbl_total_in_range' => 'Раздач за период: {1}',
    'lbl_seeders'       => 'Сиды',
    'lbl_leechers'      => 'Личи',
    'lbl_completed'     => 'Скачиваний',
    'lbl_total_size'    => 'Общий объём',

    // ── Size units ──
    'unit_kb'           => 'КБ',
    'unit_mb'           => 'МБ',
    'unit_gb'           => 'ГБ',
    'unit_tb'           => 'ТБ',

    // ── JS (chart) ──
    'js_axis_day'       => 'Дата',
    'js_axis_month'     => 'Месяц',
    'js_axis_year'      => 'Год',
    'js_axis_count'     => 'Количество',
    'js_series_added'   => 'Новые раздачи',
    'js_tooltip_added'  => 'Добавлено раздач: {1}',
);
?>
