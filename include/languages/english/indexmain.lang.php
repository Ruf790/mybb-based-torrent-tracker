<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['indexmain'] = array (
	// ── Page ──
	'page_title'          => 'Dashboard with Online Users and News',

	// ── News ──
	'sec_news'            => 'Latest News',
	'lbl_posted_by'       => 'Posted by {1} on {2}',
	'lbl_system'          => 'System',
	'msg_no_news'         => 'No news found.',

	// ── Seeders needed ──
	'sec_seeders_needed'  => 'Recently Uploaded Torrents Needing Seeders',
	'col_torrent'         => 'Torrent',
	'col_seeders'         => 'Seeders',
	'col_leechers'        => 'Leechers',
	'alt_poster'          => 'Poster',
	'pane_torrent_details'=> 'Torrent Details',
	'aria_close'          => 'Close',
	'msg_loading_details' => 'Loading torrent details...',
	'sec_all_seeded'      => 'All Torrents Have Seeders',
	'msg_all_seeded'      => 'Great job! All torrents are currently seeded.',

	// ── Latest torrents ──
	'sec_latest_torrents' => 'Latest Torrents',
	'lbl_free'            => 'Free',
	'tip_free'            => 'Free Torrent',
	'lbl_silver'          => 'Silver',
	'tip_silver'          => 'Silver Torrent',
	'lbl_double_upload'   => '2x Upload',
	'tip_double_upload'   => 'Double Upload',
	'lbl_added'           => 'Added: {1}',

	// ── Charts ──
	'sec_popular'         => 'Most Popular Torrents',
	'sec_active'          => 'Most Active Torrents',

	// ── Online users ──
	'sec_online'          => 'Online Users',
	'sec_last24'          => 'Last 24 Hours Active Users',
	'lbl_visible'         => 'Visible Members: {1}',
	'lbl_hidden'          => 'Hidden Members: {1}',
	'lbl_total_online'    => 'Total Online: {1}',
	'lbl_guests'          => 'Guests: {1}',
	'lbl_total_users'     => 'Total Users: {1}',

	// ── JS: torrent preview ──
	'js_preview_loading'  => 'Loading torrent details...',
	'js_preview_failed'   => 'Failed to load torrent preview.',

	// ── JS: charts ──
	'js_chart_popular'    => 'Most Popular Torrents',
	'js_chart_active'     => 'Most Active Torrents',
	'js_axis_torrent'     => 'Torrent',
	'js_axis_hits'        => 'Hits',
	'js_axis_completed'   => 'Completed Times',
	'js_series_hits'      => 'Hits',
	'js_series_completed' => 'Completed',
	'js_suffix_hits'      => 'hits',
	'js_suffix_completed' => 'completions',
);
?>
