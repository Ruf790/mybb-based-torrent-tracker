<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['snatched_torrents'] = array (
	// ── Page / header ──
	'page_title'           => 'All Snatched Torrents',
	'sec_title'            => 'Snatched Torrents',
	'sec_subtitle'         => 'Who downloaded what, how much they transferred and whether they are still seeding.',
	'badge_filtered'       => 'Filtered view',

	// ── KPI tiles ──
	'kpi_total'            => 'Total snatches',
	'kpi_completed'        => 'Completed',
	'kpi_seeding'          => 'Seeding now',
	'kpi_downloaded'       => '{1} downloaded', // {1} = size

	// ── Search form ──
	'lbl_username'         => 'Username',
	'lbl_user_id'          => 'User ID',
	'lbl_torrent_name'     => 'Torrent name',
	'lbl_torrent_id'       => 'Torrent ID',
	'ph_username'          => 'Username...',
	'ph_user_id'           => 'User ID...',
	'ph_torrent_name'      => 'Torrent name...',
	'ph_torrent_id'        => 'Torrent ID...',
	'btn_search'           => 'Search',
	'btn_reset'            => 'Reset',

	// ── Filter chips ──
	'lbl_active_filters'   => 'Active filters:',
	'tip_remove_filter'    => 'Remove filter',
	'chip_user'            => 'User',
	'chip_user_id'         => 'User ID',
	'chip_torrent'         => 'Torrent',
	'chip_torrent_id'      => 'Torrent ID',

	// ── Table columns ──
	'col_user'             => 'User',
	'col_torrent'          => 'Torrent',
	'col_uploaded'         => 'Uploaded',
	'col_downloaded'       => 'Downloaded',
	'col_ratio'            => 'Ratio',
	'col_started'          => 'Started',
	'col_completed'        => 'Completed',
	'col_seeding'          => 'Seeding',
	'col_progress'         => 'Progress',

	// ── Table cells / statuses ──
	'txt_deleted_user'     => 'Deleted user',
	'txt_deleted_torrent'  => 'Deleted torrent',
	'txt_na'               => 'N/A',
	'st_in_progress'       => 'In progress',
	'st_not_started'       => 'Not started',
	'opt_yes'              => 'Yes',
	'opt_no'               => 'No',

	// ── Popovers (plain text, escaped) ──
	'pop_progress_title'   => 'Download progress',
	'pop_completed'        => 'Download completed',
	'pop_left'             => '{1}% — {2} left', // {1} = percent, {2} = size left

	// ── Pagination (HTML, output as is) ──
	'txt_showing'          => 'Showing <b>{1}–{2}</b> of <b>{3}</b>',
	'txt_page_of'          => 'Page <b>{1}</b> of <b>{2}</b>',

	// ── Empty state ──
	'empty_filtered_title' => 'No results found',
	'empty_filtered_text'  => 'No snatched torrents match your search criteria.',
	'btn_reset_filters'    => 'Reset filters',
	'empty_title'          => 'No snatched torrents yet',
	'empty_text'           => 'There are currently no snatched torrents in the database.',
);
?>
