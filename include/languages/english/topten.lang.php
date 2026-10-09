<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['topten'] = array
(
	// ── Page ──────────────────────────────────────────────────────────────
	'html_lang'          => 'en',
	'page_title'         => 'Top 10 List',
	'page_subtitle'      => 'Community Statistics and Rankings',
	'page_eyebrow'       => 'Leaderboards',

	// ── Tabs ──────────────────────────────────────────────────────────────
	'tab_users'          => 'Users',
	'tab_torrents'       => 'Torrents',
	'tab_countries'      => 'Countries',
	'tab_peers'          => 'Peers',
	'tab_categories'     => 'Categories',
	'tab_seedbonus'      => 'SeedBonus',
	'tab_hot'            => 'Hot Right Now',
	'tab_contributors'   => 'Top Contributors',
	'tab_forum'          => 'Forum',

	// ── Limit links / badge ───────────────────────────────────────────────
	'lnk_top25'          => 'Top 25',
	'lnk_top50'          => 'Top 50',
	'lnk_top100'         => 'Top 100',
	'lnk_top250'         => 'Top 250',
	'badge_top'          => 'Top {1}',

	// ── Sections: users ───────────────────────────────────────────────────
	'sec_users_ul'       => 'Top {1} Uploaders',
	'sec_users_dl'       => 'Top {1} Downloaders',
	'sec_users_uls'      => 'Top {1} Fastest Uploaders <small>(average, includes inactive time)</small>',
	'sec_users_dls'      => 'Top {1} Fastest Downloaders <small>(average, includes inactive time)</small>',
	'sec_users_bsh'      => 'Top {1} Best Sharers <small>(with minimum 1 GB downloaded)</small>',
	'sec_users_wsh'      => 'Top {1} Worst Sharers <small>(with minimum 1 GB downloaded)</small>',

	// ── Sections: torrents ────────────────────────────────────────────────
	'sec_torrents_act'   => 'Top {1} Most Active Torrents',
	'sec_torrents_sna'   => 'Top {1} Most Snatched Torrents',
	'sec_torrents_mdt'   => 'Top {1} Most Data Transferred Torrents',
	'sec_torrents_bse'   => 'Top {1} Best Seeded Torrents <small>(with minimum 5 seeders)</small>',
	'sec_torrents_wse'   => 'Top {1} Worst Seeded Torrents <small>(with minimum 5 leechers, excluding unsnatched torrents)</small>',
	'sec_torrents_mcom'  => 'Top {1} Most Commented Torrents',

	// ── Sections: countries ───────────────────────────────────────────────
	'sec_countries_us'   => 'Top {1} Countries <small>(users)</small>',
	'sec_countries_ul'   => 'Top {1} Countries <small>(total uploaded)</small>',
	'sec_countries_avg'  => 'Top {1} Countries <small>(average uploaded per user, with minimum 1 TB uploaded and 100 users)</small>',
	'sec_countries_r'    => 'Top {1} Countries <small>(ratio, with minimum 1 TB uploaded, 1 TB downloaded and 100 users)</small>',

	// ── Sections: peers / other ───────────────────────────────────────────
	'sec_peers_ul'       => 'Top {1} Fastest Uploaders',
	'sec_peers_dl'       => 'Top {1} Fastest Downloaders',
	'sec_categories'     => 'Top {1} Categories',
	'sec_seedbonus'      => 'Top {1} SeedBonus Holders',
	'sec_hot'            => 'Hot Right Now — Top {1}',
	'sec_contributors'   => 'Top {1} Contributors',
	'sec_active_threads' => 'Most Active Threads',

	// ── Columns ───────────────────────────────────────────────────────────
	'col_user'           => 'User',
	'col_uploaded'       => 'Uploaded',
	'col_ulspeed'        => 'Upload Speed',
	'col_downloaded'     => 'Downloaded',
	'col_dlspeed'        => 'Download Speed',
	'col_ratio'          => 'Ratio',
	'col_sl_ratio'       => 'Ratio',
	'col_joined'         => 'Joined',
	'col_seedbonus'      => 'SeedBonus',
	'col_name'           => 'Name',
	'col_promo'          => 'Promo',
	'col_seeders'        => 'Seeders',
	'col_leechers'       => 'Leechers',
	'col_snatched'       => 'Snatched',
	'col_data'           => 'Data',
	'col_total'          => 'Total',
	'col_comments'       => 'Comments',
	'col_ratings'        => 'Ratings',
	'col_total_activity' => 'Total',
	'col_country'        => 'Country',
	'col_cnt_users'      => 'Users',
	'col_cnt_uploaded'   => 'Uploaded',
	'col_cnt_average'    => 'Average',
	'col_cnt_ratio'      => 'Ratio',
	'col_torrent'        => 'Torrent',
	'col_category'       => 'Category',
	'col_torrents'       => 'Torrents',
	'col_snatches'       => 'Snatches',
	'col_total_size'     => 'Total Size',
	'col_thread'         => 'Thread',
	'col_forum'          => 'Forum',
	'col_views'          => 'Views',
	'col_replies'        => 'Replies',
	'col_activity'       => 'Activity',

	// ── Labels ────────────────────────────────────────────────────────────
	'lbl_na'             => 'N/A',
	'lbl_per_sec'        => '/s',
	'lbl_by'             => 'by {1}',
	'alt_avatar'         => 'Avatar',

	// ── Promo badges ──────────────────────────────────────────────────────
	'badge_free'         => 'Free',
	'badge_thirty'       => '30%',
	'tip_silver'         => 'Silver download',

	// ── Messages ──────────────────────────────────────────────────────────
	'msg_not_available'  => 'This statistic is not available on this tracker.',
	
	'kpi_users'      => 'Members',
'kpi_torrents'   => 'Torrents',
'kpi_peers'      => 'Peers',
'kpi_downloaded' => '{1} downloaded',
'msg_no_data'    => 'No data yet',

	
);
?>
