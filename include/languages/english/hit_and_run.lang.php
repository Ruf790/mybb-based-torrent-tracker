<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['hit_and_run'] = array (
	// ── Page titles ──
	'title_main'            => 'Hit & Run Detection Tool',
	'title_compose'         => 'Hit & Run — Send Warnings',

	// ── Header ──
	'pane_main'             => 'Hit &amp; Run Detection',
	'pane_main_sub'         => 'Finished downloads that are no longer seeded and break the share rules',
	'pane_compose'          => 'Send Hit &amp; Run Warnings',
	'pane_compose_sub'      => '<b>{1}</b> selected snatch(es) will receive a PM',

	// ── KPI tiles ──
	'stat_violations'       => 'Violations',
	'stat_users'            => 'Users',
	'stat_warned_7d'        => 'Warned · 7 days',
	'stat_ban_threshold'    => 'Ban threshold',
	'stat_warns_value'      => '{1} warns',

	// ── Filters & search ──
	'tab_low_ratio'         => 'Low ratio',
	'tab_seedtime'          => 'Seed time',
	'ph_search'             => 'Search...',
	'opt_username'          => 'Username',
	'opt_userid'            => 'User ID',
	'opt_torrentid'         => 'Torrent ID',
	'btn_search'            => 'Search',
	'lbl_filters'           => 'Filters:',
	'chip_torrent'          => 'Torrent #{1}',
	'chip_user'             => 'User #{1}',
	'chip_search'           => 'Search: {1}',
	'tip_remove_filter'     => 'Remove filter',

	// ── Selection mode ──
	'type_seedtime'         => 'Seed time below leech time',
	'type_ratio'            => 'Ratio below {1}',

	// ── Toolbar ──
	'lbl_selected'          => 'Selected:',
	'btn_warn'              => 'Warn',
	'btn_ban'               => 'Ban',

	// ── Table ──
	'aria_select_all'       => 'Select all',
	'aria_select'           => 'Select',
	'col_user'              => 'User',
	'col_torrent'           => 'Torrent',
	'col_uploaded'          => 'Uploaded / Seed',
	'col_downloaded'        => 'Downloaded / Leech',
	'col_ratio'             => 'Ratio',
	'col_warns'             => 'Warns',
	'tip_already_warned'    => 'Already warned in the last 7 days',
	'tip_only_user'         => 'Show only this user',
	'tip_only_torrent'      => '{1} — show only this torrent',
	'tip_seeders'           => 'Seeders',
	'tip_leechers'          => 'Leechers',
	'tip_open_torrent'      => 'Open torrent',
	'lbl_never_seeded'      => 'never seeded',
	'tip_seed_bar'          => 'Seed {1}% vs leech {2}%',
	'lbl_warned_ago'        => 'Warned {1}',
	'empty_title'           => 'Nothing found',
	'empty_text'            => 'No violations match the current filter.',
	'hint_table'            => 'Users warned in the last 7 days can\'t be selected again. Click a user or torrent to filter by it.',

	// ── Ban modal ──
	'modal_ban_title'       => 'Ban users',
	'modal_ban_question'    => 'Ban {1} user(s)?',
	'modal_ban_text'        => 'Accounts will be disabled and moved to the banned group. The whole account is banned, not just this torrent.',
	'btn_cancel'            => 'Cancel',
	'aria_close'            => 'Close',

	// ── Compose page ──
	'lbl_message'           => 'Message',
	'lbl_placeholders'      => 'Placeholders (click to insert):',
	'hint_compose'          => 'The ratio is calculated on the server; users already warned in the last 7 days are skipped.',
	'btn_back'              => 'Back',
	'btn_reset'             => 'Reset',
	'btn_send'              => 'Send {1} Warning(s)',
	// {1} = min ratio (setting hr_min_ratio), {2} = warning limit (ban_user_limit); {torrentinfo} etc. are filled per user
	'msg_default_warn'      => "Hi,\n\nWe have noticed a Hit & Run on the following torrent:\n{torrentinfo}\nYour current ratio on this torrent: {showratio}\n\nYou have 1 (one) week to raise your ratio on this torrent to {1}, otherwise you will get another warning.\n\nIf you no longer have this torrent on your computer, you can download it again here:\n{torrentdownloadinfo}\n\nPlease note: once you reach {2} warnings, your account will be banned.\n\nHave a great day.",
	'pm_subject'            => '⚠️ Warning!',

	// ── Flash messages ──
	'flash_error'           => 'Error!',
	'flash_success'         => 'Success!',
	'flash_warning'         => 'Warning!',
	'flash_csrf'            => 'Security check failed. Please refresh the page and try again.',
	'flash_banned'          => 'Users have been banned successfully!',
	'flash_no_valid'        => 'No valid hit-and-run users were found in the selection.',
	'flash_warned'          => '{1} user(s) have been warned successfully!',

	// ── JS ──
	'js_banning'            => 'Banning...',
);
?>
