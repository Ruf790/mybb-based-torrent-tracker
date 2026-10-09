<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['passkeysearch'] = array (
	// ── Page ──
	'title'               => 'Passkey Search',
	'subtitle'            => 'Find the account behind a passkey — paste the key, an announce URL or a .torrent link',

	// ── Search form ──
	'ph_passkey'          => '32-character passkey or announce URL…',
	'btn_clear'           => 'Clear',
	'btn_search'          => 'Search',
	'hint_format'         => '32 hexadecimal characters (0-9, a-f)',

	// ── Flash messages (output as HTML) ──
	'flash_bad_method'    => 'Invalid request method.',
	'flash_csrf'          => 'Security check failed. Please refresh the page and try again.',
	'flash_reset_invalid' => 'Invalid passkey — it must be 32 hexadecimal characters.',
	'flash_reset_ok'      => 'The passkey of <strong>{1}</strong> has been reset. They need a new passkey and must re-download their .torrent files.',
	'flash_reset_none'    => 'No user has this passkey (maybe it was already reset).',
	'flash_empty'         => 'Please enter a passkey.',
	'flash_invalid'       => 'Invalid passkey — it must be exactly 32 hexadecimal characters (0-9, a-f).',
	'flash_not_found'     => 'No registered user has this passkey.',
	'txt_unknown_user'    => 'the user',

	// ── Profile card ──
	'lbl_online'          => 'Online',
	'lbl_offline'         => 'Offline',
	'lbl_online_now'      => 'Online now',
	'lbl_last_seen'       => 'Last seen {1}',
	'lbl_never'           => 'never',
	'lbl_member'          => 'Member',
	'lbl_id'              => 'ID',
	'btn_open_profile'    => 'Open profile',
	'lbl_email'           => 'Email',
	'lbl_ip'              => 'IP',
	'lbl_joined'          => 'Joined',
	'lbl_last_active'     => 'Last active',

	// ── KPI tiles ──
	'kpi_uploaded'        => 'Uploaded',
	'kpi_downloaded'      => 'Downloaded',
	'kpi_ratio'           => 'Ratio',
	'kpi_active'          => 'Active now',
	'kpi_seed'            => 'seed',
	'kpi_leech'           => 'leech',

	// ── Passkey section ──
	'sec_passkey'         => 'Passkey',
	'sec_passkey_sub'     => 'Linked to this account',
	'btn_copy'            => 'Copy',

	// ── Reset warning ──
	'reset_title'         => 'Resetting the passkey',
	'reset_li_sessions'   => 'stops every .torrent downloaded with it — <strong>{1}</strong> active session(s) right now;',
	'reset_li_redownload' => 'the user needs to re-download their .torrent files;',
	'reset_li_logged'     => 'is logged in the site log.',
	'btn_reset'           => 'Reset passkey',

	// ── Intro cards ──
	'intro_who_title'     => 'Who is it?',
	'intro_who_text'      => 'Every account has its own 32-character passkey inside each .torrent it downloads.',
	'intro_url_title'     => 'Paste a URL',
	'intro_url_text'      => 'An announce URL like …/announce.php?passkey=… works too — the key is extracted.',
	'intro_leak_title'    => 'Leaked key?',
	'intro_leak_text'     => 'Reset it from the result: old .torrent files stop working right away.',

	// ── JS strings ──
	'js_confirm_reset'    => 'Reset the passkey of {1}?',
	'js_confirm_files'    => '• All their .torrent files stop working',
	'js_confirm_sessions' => '• {1} active session(s) will be dropped',
	'js_copy'             => 'Copy',
	'js_copied'           => 'Copied',
	'js_hint_valid'       => 'Looks like a valid passkey',
	'js_hint_format'      => '32 hexadecimal characters (0-9, a-f)',
);
?>
