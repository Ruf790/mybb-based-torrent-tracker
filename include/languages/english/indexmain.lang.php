<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['indexmain'] = array(

    // ── Page title ────────────────────────────────────────────────────────────
    'page_title'                => 'Dashboard',

    // ── News section ─────────────────────────────────────────────────────────
    'news_title'                => 'Latest News',
    'news_posted_by'            => 'Posted by',
    'news_on'                   => 'on',
    'news_none'                 => 'No news found.',

    // ── Seeders needed section ────────────────────────────────────────────────
    'seeders_needed_title'      => 'Recently Uploaded Torrents Needing Seeders',
    'seeders_col_torrent'       => 'Torrent',
    'seeders_col_seeders'       => 'Seeders',
    'seeders_col_leechers'      => 'Leechers',
    'seeders_all_seeded_title'  => 'All Torrents Have Seeders',
    'seeders_all_seeded_msg'    => 'Great job! All torrents are currently seeded.',

    // ── Torrent modal ─────────────────────────────────────────────────────────
    'torrent_details'           => 'Torrent Details',
    'torrent_loading'           => 'Loading torrent details...',
    'torrent_load_error'        => 'Failed to load torrent preview.',

    // ── Latest torrents section ───────────────────────────────────────────────
    'latest_torrents_title'     => 'Latest Torrents',
    'torrent_added'             => 'Added:',
    'torrent_badge_free'        => 'Free',
    'torrent_badge_free_title'  => 'Free Torrent',
    'torrent_badge_silver'      => 'Silver',
    'torrent_badge_silver_title'=> 'Silver Torrent',
    'torrent_badge_double'      => '2x Upload',
    'torrent_badge_double_title'=> 'Double Upload',

    // ── Charts section ────────────────────────────────────────────────────────
    'chart_popular_title'       => 'Most Popular Torrents',
    'chart_active_title'        => 'Most Active Torrents',
    'chart_axis_torrent'        => 'Torrent',
    'chart_axis_hits'           => 'Hits',
    'chart_axis_completed'      => 'Completed Times',
    'chart_series_hits'         => 'Hits',
    'chart_series_completed'    => 'Completed',
    'chart_tooltip_hits'        => ' hits',
    'chart_tooltip_completions' => ' completions',

    // ── Online users section ──────────────────────────────────────────────────
    'online_title'              => 'Online Users',
    'online_visible'            => 'Visible Members',
    'online_hidden'             => 'Hidden Members',
    'online_total'              => 'Total Online',

    // ── Last 24h section ──────────────────────────────────────────────────────
    'last24h_title'             => 'Last 24 Hours Active Users',
    'last24h_visible'           => 'Visible Members',
    'last24h_hidden'            => 'Hidden Members',
    'last24h_guests'            => 'Guests',
    'last24h_total'             => 'Total Users',

);
