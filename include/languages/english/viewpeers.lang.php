<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['viewpeers'] = array (
	// ── Page ──
	'page_title'        => 'Peer List',
	'page_sub'          => 'Live peer connections on the tracker, newest first',
	'lbl_live'          => 'Live',

	// ── KPI tiles ──
	'kpi_active'        => 'Active peers',
	'kpi_seeders'       => 'Seeders',
	'kpi_leechers'      => 'Leechers',
	'kpi_users'         => 'Unique users',
	'kpi_pages'         => 'Pages: {1}',
	'kpi_pct_of'        => '{1}% of peers',
	'kpi_connectable'   => '{1} connectable peers',

	// ── Toolbar ──
	'ph_search'         => 'Filter this page by user, torrent, IP or client',
	'aria_filter'       => 'Filter by status',
	'opt_all'           => 'All',
	'opt_seed'          => 'Seeding',
	'opt_leech'         => 'Leeching',
	'lbl_shown'         => '{1} shown on this page',

	// ── Table headers ──
	'col_user'          => 'User',
	'col_torrent'       => 'Torrent',
	'col_address'       => 'Address',
	'col_traffic'       => 'Traffic',
	'col_client'        => 'Client',
	'col_connectable'   => 'Connectable',
	'col_status'        => 'Status',
	'col_started'       => 'Started',
	'col_activity'      => 'Activity',
	'col_offsets'       => 'Offsets',
	'col_to_go'         => 'To go',

	// ── Row cells ──
	'lbl_deleted_user'  => 'deleted user',
	'lbl_unknown_torrent' => 'Unknown torrent',
	'lbl_unknown_client'  => 'Unknown',
	'lbl_na'            => 'N/A',
	'lbl_port'          => 'port {1}',
	'tip_complete_pct'  => '{1}% complete',
	'tip_complete'      => 'Complete',
	'tip_copy_ip'       => 'Copy IP',

	// ── Badges ──
	'badge_yes'         => 'yes',
	'badge_no'          => 'no',
	'badge_seed'        => 'seed',
	'badge_leech'       => 'leech',

	// ── Relative time ──
	'ago_sec'           => '{1} sec ago',
	'ago_min'           => '{1} min ago',
	'ago_hour'          => '{1} h ago',
	'ago_day'           => '{1} d ago',

	// ── Empty states ──
	'msg_no_match'      => 'No peers on this page match the filter.',
	'msg_empty_title'   => 'No active peers',
	'msg_empty_text'    => 'Peers appear here as soon as a client announces to the tracker.',

	// ── Legend ──
	'legend_fresh'      => 'announced ≤ 30 min',
	'legend_late'       => '≤ 60 min',
	'legend_stale'      => 'stale',

	// ── JS ──
	'js_copied'         => 'Copied: {1}',
	'js_copy_failed'    => 'Could not copy to clipboard',
);
?>
