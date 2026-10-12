<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['viewsnatches'] = array(

    
	'headmessage'		=>'Snatch Details',
	'snatchdetails'		=>'Snatch Details for {1}</b></a>', // Changed v3.6
	'message'			=>'<p align=center class=success>The users at the top finished the download most recently</p>',
	'username'			=>'Username',
	'uploaded'			=>'Upl',
	'downloaded'		=>'Down',
	'ratio'					=>'Ratio',
	'finished'				=>'Finished',
	'lastaction'			=>'Last-Action',
	'seeding'				=>'Seeding',
	'connectable'		=>'Conn',
	'port'					=>'Port',
	'waiting'				=>'waiting..',
	'seedtime'			=>'Seed time', // Added v3.6
	'leechtime'			=>'Leech time', // Added v3.6
	'external'				=>'This is an external torrent!',
	
	
	
	
	// ── Page / errors ─────────────────────────────────────────────────────────
    'page_title'                => 'Snatch List',
    'headmessage'               => 'Snatch List',
    'external'                  => 'This torrent is external and has no snatch data.',
    'not_found'                 => 'Torrent not found.',

    // ── Torrent info ──────────────────────────────────────────────────────────
    'torrent_size'              => 'Size',
    'torrent_category'          => 'Category',
    'torrent_unknown'           => 'Unknown',
    'snatches_count'            => '{1} snatches',

    // ── Moderator tools ───────────────────────────────────────────────────────
    'mod_tools'                 => 'Moderator Tools',
    'search_placeholder'        => 'Search username...',
    'btn_filters'               => 'Filters',
    'btn_reseed'                => 'Reseed',
    'btn_hnr'                   => 'H&R',
    'btn_export'                => 'Export',

    // ── Filters ───────────────────────────────────────────────────────────────
    'filter_all_status'         => 'All Status',
    'filter_seeders_only'       => 'Seeders Only',
    'filter_non_seeders'        => 'Non-Seeders',
    'filter_all_connectivity'   => 'All Connectivity',
    'filter_connectable'        => 'Connectable',
    'filter_not_connectable'    => 'Not Connectable',
    'filter_all_ratios'         => 'All Ratios',
    'filter_ratio_poor'         => 'Ratio < 0.5',
    'filter_ratio_good'         => 'Ratio ≥ 1.0',
    'filter_all_hnr'            => 'All H&R',
    'filter_clean'              => 'Clean',
    'filter_warned'             => 'Warned',
    'filter_banned'             => 'Banned',
    'btn_clear'                 => 'Clear',
    'btn_apply'                 => 'Apply',

    // ── Bulk actions ──────────────────────────────────────────────────────────
    'select_all'                => 'Select All',
    'selected_count'            => 'Selected:',
    'btn_message'               => 'Message',
    'btn_request_reseed'        => 'Request Reseed',

    // ── Charts ────────────────────────────────────────────────────────────────
    'ratio_distribution'        => 'Ratio Distribution',
    'quick_stats'               => 'Quick Stats',
    'stat_unique_users'         => 'Unique Users',
    'stat_avg_ratio'            => 'Avg Ratio',
    'stat_total_speed'          => 'Total Speed',
    'stat_completion'           => 'Completion',

    // ── H&R status ────────────────────────────────────────────────────────────
    'hnr_status'                => 'H&R Status',
    'hnr_clean'                 => 'Clean',
    'hnr_warned'                => 'Warned',
    'hnr_banned'                => 'Banned',
    'hnr_pct_clean'             => '% clean',
    'hnr_pct_banned'            => '% banned',
    //'hnr_title_clean'           => 'Clean: {1}%',
    //'hnr_title_warned'          => 'Warned: {1}%',
    //'hnr_title_banned'          => 'Banned: {1}%',
	'hnr_title_clean'           => 'Clean: {1}%%',
'hnr_title_warned'          => 'Warned: {1}%%',
'hnr_title_banned'          => 'Banned: {1}%%',

    // ── Stat cards ────────────────────────────────────────────────────────────
    'stat_current_seeders'      => 'Current Seeders',
    'stat_total_uploaded'       => 'Total Uploaded',
    'stat_total_downloaded'     => 'Total Downloaded',
    'stat_total_seedtime'       => 'Total Seed Time',
    'stat_pct_snatchers'        => '% of snatchers',
    'stat_users_over_24h'       => '{1} users > 24h',

    // ── Seed time distribution ────────────────────────────────────────────────
    'seedtime_dist'             => 'Seed Time Distribution',
    'seedtime_poor'             => '< 1 hour',
    'seedtime_fair'             => '1-6 hours',
    'seedtime_good'             => '6-24 hours',
    'seedtime_excellent'        => '> 24 hours',

    // ── Pagination ────────────────────────────────────────────────────────────
    'page_of'                   => 'Page {1} of {2}',
    'showing'                   => 'Showing {1}–{2} of {3}',

    // ── Table headers ─────────────────────────────────────────────────────────
    'snatch_details'            => 'Snatch Details',
    'col_user'                  => 'User',
    'col_uploaded'              => 'Uploaded',
    'col_downloaded'            => 'Downloaded',
    'col_ratio'                 => 'Ratio',
    'col_finished'              => 'Finished',
    'col_last_action'           => 'Last Action',
    'col_status'                => 'Status',
    'col_seedtime'              => 'Seed Time',
    'col_leechtime'             => 'Leech Time',
    'col_hnr'                   => 'H&R',
    'col_actions'               => 'Actions',

    // ── Row badges ────────────────────────────────────────────────────────────
    'badge_seeding'             => 'Seeding',
    'badge_inactive'            => 'Inactive',
    'badge_connectable'         => 'Connectable',
    'badge_not_connectable'     => 'Not Connectable',
    'badge_clean'               => 'Clean',
    'badge_warned_x'            => 'Warned {1}x',
    'badge_banned'              => 'Banned',

    // ── Actions ───────────────────────────────────────────────────────────────
    'btn_view_details'          => 'View Details',
    'btn_delete'                => 'Delete',
    'confirm_delete'            => 'Delete this snatch record?',

    // ── Empty state ───────────────────────────────────────────────────────────
    'no_snatches'               => 'No snatches found',

    // ── User details modal ────────────────────────────────────────────────────
    'modal_user_details'        => 'User Snatch Details',
    'modal_loading'             => 'Loading...',

    // ── Messages ─────────────────────────────────────────────────────────────
    'msg_subject_default'       => 'Regarding torrent: {1}',
    'msg_reseed_subject'        => 'Reseed request (TID: {1})',
    'msg_reseed_body'           => "Hello {username},\n\nWe noticed that you have downloaded {torrenturl} but are no longer seeding it.\nCould you please help the community by reseeding it? Thank you!\n\nBest regards, Staff",

);
