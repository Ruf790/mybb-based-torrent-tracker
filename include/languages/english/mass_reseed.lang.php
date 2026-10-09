<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['mass_reseed'] = array (
	// ── Page titles & headers ──
	'title_list'        => 'Weak Torrents — Reseed',
	'title_form'        => 'Request Reseed',
	'pane_list'         => 'Mass Reseed',
	'pane_list_sub'     => 'Torrents without seeders — ask uploaders or snatchers to seed again',
	'pane_form'         => 'Request reseed',
	'pane_form_sub'     => 'Send a private message asking people to seed again',
	'btn_back'          => 'Back to list',

	// ── KPI tiles ──
	'kpi_dead'          => 'Dead torrents',
	'kpi_waiting'       => 'Leechers waiting',
	'kpi_snatched'      => 'Past snatches',
	'kpi_per_request'   => 'Per request',
	'kpi_up_to'         => 'up to {1}',

	// ── Toolbar ──
	'lbl_sort'          => 'Sort:',
	'opt_sort_snatched' => 'Most snatched',
	'opt_sort_leechers' => 'Leechers waiting',
	'opt_sort_added'    => 'Newest',
	'opt_sort_name'     => 'Name',
	'ph_filter'         => 'Filter by name…',

	// ── Table ──
	'col_torrent'       => 'Torrent',
	'col_uploader'      => 'Uploader',
	'col_leech'         => 'Leech',
	'col_snatched'      => 'Snatched',
	'aria_select_all'   => 'Select all',
	'aria_select'       => 'Select',
	'lbl_deleted'       => 'deleted',
	'tip_snatches'      => 'View snatches',
	'tip_edit'          => 'Edit',
	'tip_delete'        => 'Delete',
	'empty_title'       => 'No dead torrents',
	'empty_text'        => 'Every visible torrent has at least one seeder.',
	'hint_list_limit'   => 'Showing the first {1} — change the sort order to see others.',
	'lbl_selected'      => '{1} selected',
	'lbl_max'           => '(max {1})',
	'btn_request'       => 'Request reseed',

	// ── Reseed form ──
	'hint_truncated'    => 'Only the first {1} selected torrents are included in one request.',
	'sec_message'       => 'Message',
	'sec_message_sub'   => 'One PM per recipient and torrent',
	'lbl_subject'       => 'Subject',
	'lbl_text'          => 'Text',
	'lbl_insert'        => 'Insert:',
	'sec_recipients'    => 'Recipients',
	'opt_owner'         => 'Uploaders only',
	'opt_all'           => 'Everyone who snatched',
	'lbl_msg_count'     => '{1} message(s)',
	'sec_options'       => 'Options',
	'lbl_send_as'       => 'Send as',
	'opt_system'        => 'System',
	'opt_me'            => 'Me',
	'lbl_double'        => 'Double upload',
	'hint_double'       => 'Reward reseeders with 2× upload',
	'sec_torrents_one'  => '{1} torrent',
	'sec_torrents_many' => '{1} torrents',
	'lbl_will_send'     => '{1} message(s) will be sent',
	'btn_send'          => 'Send requests',

	// ── Default PM ({username} and {torrentname} are replaced per recipient) ──
	'msg_subject'       => 'Reseed request',
	'msg_body'          => "Hello {username},\n\nthe torrent {torrentname} has no seeders right now. If you still have the files, please consider seeding it again.\n\nThank you!",

	// ── Errors & flash messages ──
	'err_security_title'  => 'Security Error',
	'err_security'        => 'Invalid security token. Please refresh the page and try again.',
	'flash_fill'          => 'Please fill in the subject and the message.',
	'flash_none_selected' => 'No torrents selected.',
	'flash_sent'          => 'Reseed requests sent: {1} message(s) for {2} torrent(s).',
	'flash_sent_double'   => 'Double upload enabled.',
	'flash_nobody'        => 'Nobody to notify — the selected torrents have no uploader or snatchers left.',

	// ── JS strings ──
	'js_double_note'    => 'Bonus: you get double upload credit for seeding this torrent again!',
	'js_nobody_title'   => 'Nobody to notify',
	'js_nobody_text'    => 'Try “Everyone who snatched”.',
	'js_confirm_send'   => 'Send {1} message(s)?',
	'js_confirm_torrents' => '{1} torrent(s)',
	'js_confirm_double' => 'Double upload will be enabled',
	'js_btn_send'       => 'Send',
	'js_btn_cancel'     => 'Cancel',
	'js_sending'        => 'Sending…',
	'js_delete_title'   => 'Delete torrent?',
	'js_delete_btn'     => 'Delete',
	'js_delete_confirm' => 'Delete torrent {1}?',
	'js_too_many_title' => 'Too many selected',
	'js_too_many_text'  => 'Only the first {1} will be included. Continue?',
	'js_continue'       => 'Continue',
);
?>
