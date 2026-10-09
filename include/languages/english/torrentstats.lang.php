<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['torrentstats'] = array (

    // ── Page ──
    'page_title'        => 'Torrent Stats',
    'sec_dashboard'     => 'Torrent Statistics Dashboard',

    // ── Chart titles (by grouping) ──
    'sec_added_day'     => 'Torrents Added (Daily)',
    'sec_added_month'   => 'Torrents Added (Monthly)',
    'sec_added_year'    => 'Torrents Added (Yearly)',

    // ── Preset buttons ──
    'btn_last_7'        => 'Last 7 Days',
    'btn_last_30'       => 'Last 30 Days',
    'btn_this_year'     => 'This Year',
    'btn_all_time'      => 'All Time',

    // ── Filter form ──
    'lbl_from'          => 'From:',
    'lbl_to'            => 'To:',
    'lbl_group_by'      => 'Group By:',
    'opt_day'           => 'Day',
    'opt_month'         => 'Month',
    'opt_year'          => 'Year',
    'btn_filter'        => 'Filter',

    // ── Summary ──
    'lbl_total_in_range' => 'Total Torrents in Range: {1}',
    'lbl_seeders'       => 'Seeders',
    'lbl_leechers'      => 'Leechers',
    'lbl_completed'     => 'Times Completed',
    'lbl_total_size'    => 'Total Size',

    // ── Size units ──
    'unit_kb'           => 'KB',
    'unit_mb'           => 'MB',
    'unit_gb'           => 'GB',
    'unit_tb'           => 'TB',

    // ── JS (chart) ──
    'js_axis_day'       => 'Date',
    'js_axis_month'     => 'Month',
    'js_axis_year'      => 'Year',
    'js_axis_count'     => 'Count',
    'js_series_added'   => 'Torrents Added',
    'js_tooltip_added'  => 'Torrents Added: {1}',
);
?>
