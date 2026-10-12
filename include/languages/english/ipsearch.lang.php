<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['ipsearch'] = array (
	// ── Page / header ──
	'page_title'         => 'IP Search',
	'pane_title'         => 'IP Address Search',
	'pane_sub'           => 'Find accounts by registration IP and login history · IPv4 & IPv6',

	// ── Search form ──
	'lbl_placeholder'    => 'Enter IPv4 or IPv6 address',
	'btn_search'         => 'Search',
	'hint_searched'      => 'Registration IP (users) and login history (login_log) are searched. Try:',

	// ── Alerts / flash ──
	'err_invalid_title'  => 'Invalid IP address',
	'err_invalid_text'   => 'Please enter a valid IPv4 or IPv6 address.',
	'flash_reset_ok'     => 'Passkey reset',
	'flash_reset_ok_txt' => 'A new passkey was generated. The user has to re-download their .torrent files.',
	'flash_reset_fail'   => 'Passkey was not reset',
	'flash_reset_fail_txt' => 'Security token expired or the user no longer exists. Please try again.',
	'err_title'          => 'Error',
	'err_invalid_date'   => 'Invalid date',

	// ── KPI tiles ──
	'kpi_registered'     => 'Registered from IP',
	'kpi_logins'         => 'Logged in from IP',
	'kpi_unique'         => 'Unique accounts',
	'opt_scope_private'  => 'Private / reserved',
	'opt_scope_public'   => 'Public',

	// ── Empty state ──
	'sec_empty_title'    => 'No accounts found',
	// {1} = IP (already escaped), output as-is
	'sec_empty_text'     => 'Nobody registered or logged in from <code>{1}</code>.',

	// ── Result sections ──
	'sec_registered'       => 'Registered from this IP',
	'sec_registered_empty' => 'Nobody registered from this IP.',
	'sec_logins'           => 'Logged in from this IP',
	'sec_logins_empty'     => 'No logins from this IP in the log.',

	// ── Table headers ──
	'lbl_username'       => 'Username',
	'lbl_email'          => 'Email',
	'lbl_last_ip'        => 'Last IP',
	'lbl_passkey'        => 'Passkey',
	'lbl_last_seen'      => 'Last seen',
	'lbl_registered'     => 'Registered',
	'lbl_uploaded'       => 'Uploaded',
	'lbl_downloaded'     => 'Downloaded',
	'lbl_ratio'          => 'Ratio',

	// ── Tooltips ──
	'tip_same_ip'        => 'Same as searched IP',
	'tip_search_ip'      => 'Search this IP',
	'tip_copy_passkey'   => 'Copy passkey',
	'tip_reset_passkey'  => 'Reset passkey',

	// ── Action bar ──
	'btn_copy_ip'        => 'Copy IP',
	'btn_new_search'     => 'New search',

	// ── JS ──
	'js_invalid_ip'      => 'Enter a valid IPv4 or IPv6 address',
	'js_reset_title'     => 'Reset passkey?',
	// {1} = username
	'js_reset_text'      => 'Reset passkey for {1}? The user will have to re-download all .torrent files.',
	'js_reset_confirm'   => 'Yes, reset',
	'js_reset_cancel'    => 'Cancel',
);
?>
