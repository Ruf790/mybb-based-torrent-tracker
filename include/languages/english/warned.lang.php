<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['warned'] = array (
	// ── Meta ──
	// Plural rule used by flash_removed_* / js_confirm_text_*: 'en' (1 / other) or 'ru' (1, 2–4, 5+)
	'js_plural_rule'      => 'en',

	// ── Page ──
	'page_title'          => 'Warned Users',
	'sec_header'          => 'Warned Users',
	'hint_header'         => 'Active members with a normal or leech warning. Select rows to lift warnings in bulk.',

	// ── KPI tiles ──
	'lbl_kpi_all'         => 'All warned',
	'lbl_kpi_normal'      => 'Normal warnings',
	'lbl_kpi_leech'       => 'Leech warnings',
	'lbl_kpi_expiring'    => 'Expire within 7 days',
	'tip_kpi_show'        => 'Show: {1}',

	// ── Flash messages ──
	// {1} = number of users (string is output as-is, HTML allowed)
	'flash_removed_one'   => 'Warnings removed from <b>{1}</b> user.',
	'flash_removed_few'   => 'Warnings removed from <b>{1}</b> users.',
	'flash_removed_many'  => 'Warnings removed from <b>{1}</b> users.',
	'lbl_close'           => 'Close',

	// ── Errors ──
	'err_security'        => 'Security check failed. Please refresh the page and try again.',

	// ── Table headers ──
	'lbl_col_user'        => 'User',
	'lbl_col_registered'  => 'Registered',
	'lbl_col_lastseen'    => 'Last seen',
	'lbl_col_traffic'     => 'Traffic',
	'lbl_col_ratio'       => 'Ratio',
	'lbl_col_expires'     => 'Expires',
	'lbl_col_type'        => 'Type',
	'tip_select_all_page' => 'Select all on this page',
	'lbl_select_all'      => 'Select all',

	// ── Table rows ──
	'lbl_never'           => 'Never',
	'tip_no_download'     => 'Nothing downloaded',
	'tip_remove_warning'  => 'Remove warning',
	'lbl_select_user'     => 'Select {1}',

	// ── Warning types / expiry ──
	'opt_type_normal'     => 'Normal',
	'opt_type_leech'      => 'Leech',
	'lbl_no_expiry'       => 'No expiry',
	'lbl_expired_cron'    => 'Expired, awaiting cron',
	'lbl_time_left'       => '{1} left',

	// ── Empty state ──
	'sec_empty'           => 'No warned users',
	'hint_empty'          => 'Nobody in this view has an active warning.',

	// ── Action bar ──
	// {1} = selected counter element (HTML, output as-is), {2} = rows on page
	'lbl_selected'        => 'Selected: {1} of {2}',
	'btn_clear'           => 'Clear',
	'btn_remove'          => 'Remove warnings',

	// ── JS (confirmation dialog) ──
	'js_confirm_title'    => 'Remove warnings?',
	'js_confirm_text_one' => 'Warnings will be removed from {1} user.',
	'js_confirm_text_few' => 'Warnings will be removed from {1} users.',
	'js_confirm_text_many'=> 'Warnings will be removed from {1} users.',
	'js_confirm_btn'      => 'Remove warnings',
	'js_cancel_btn'       => 'Cancel',
	'js_continue'         => 'Continue?',
);
?>
