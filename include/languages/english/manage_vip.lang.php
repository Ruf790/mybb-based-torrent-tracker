<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_vip'] = array (
	// ── Page ──
	'page_title'          => 'Manage VIP Accounts (Total {1} VIP Accounts found)',
	'sec_title'           => 'Manage VIP accounts',
	'sec_subtitle'        => 'Extend VIP time, give bonus points or invites, or remove VIP from selected members.',
	'tip_version'         => 'Module version',

	// ── KPI tiles ──
	'kpi_total'           => 'VIP accounts',
	'kpi_unlimited'       => 'Unlimited',
	'kpi_expiring'        => 'Expire within 7 days',
	'kpi_expired'         => 'Expired, waiting for cron',

	// ── Search toolbar ──
	'hint_search'         => 'Find a VIP by username',
	'lbl_username'        => 'Username',
	'btn_search'          => 'Search',
	'btn_clear'           => 'Clear',
	'lbl_range'           => '{1}–{2} of {3}',
	'lbl_none_found'      => '0 found',
	'lbl_pager_top'       => 'VIP users pagination',
	'lbl_pager_bottom'    => 'VIP users pagination bottom',

	// ── Table ──
	'lbl_select_all'      => 'Select all on this page',
	'lbl_select_user'     => 'Select {1}',
	'col_member'          => 'Member',
	'col_vip_until'       => 'VIP until',
	'col_seedbonus'       => 'Bonus points',
	'col_invites'         => 'Invites',
	'lbl_vip_member'      => 'VIP Member',

	// ── VIP until column ──
	'lbl_unlimited'       => 'Unlimited',
	'hint_time_left'      => '{1} left',
	'hint_expired'        => 'Expired, waiting for cron',

	// ── Empty state ──
	'sec_empty_search'    => 'No VIP account matches “{1}”',
	'hint_empty_search'   => 'Check the spelling or search for part of the name.',
	'btn_clear_search'    => 'Clear search',
	'sec_empty'           => 'There are no VIP accounts yet',
	'hint_empty'          => 'Members appear here once they buy VIP or staff move them into the VIP group.',

	// ── User popover ──
	'pop_online'          => 'Online',
	'pop_offline'         => 'Offline',
	'pop_joined'          => 'Joined',
	'pop_last_seen'       => 'Last seen',
	'pop_ratio'           => 'Ratio',
	'pop_seedbonus'       => 'Bonus points',
	'pop_uploaded'        => 'Uploaded',
	'pop_downloaded'      => 'Downloaded',
	'pop_invites'         => 'Invites',
	'pop_invites_value'   => '{1} available',

	// ── Action bar ──
	'tip_selected'        => 'Selected accounts',
	'lbl_selected'        => '{1} selected',
	'lbl_action'          => 'Action',
	'hint_amount'         => 'Amount',
	'btn_apply'           => 'Apply to selected',

	// ── Actions (labels + amount units) ──
	'opt_donoruntil'      => 'Extend VIP',
	'opt_seedbonus'       => 'Give bonus points',
	'opt_invites'         => 'Give invites',
	'opt_remove_vip'      => 'Remove VIP',
	'unit_donoruntil'     => 'weeks',
	'unit_seedbonus'      => 'points',
	'unit_invites'        => 'invites',
	'unit_remove_vip'     => '',

	// ── Flash messages ──
	'flash_dismiss'       => 'Dismiss',
	'flash_donoruntil'    => 'VIP extended by {1} week(s) for {2} account(s).',
	'flash_seedbonus'     => '{1} bonus points given to {2} account(s).',
	'flash_invites'       => '{1} invite(s) given to {2} account(s).',
	'flash_remove_vip'    => 'VIP removed from {1} account(s). Their previous group was restored.',
	'flash_none_selected' => 'Nothing was changed: select at least one VIP account in the table.',
	'flash_bad_amount'    => 'Nothing was changed: enter an amount from 1 to {1} {2}.',
	'flash_bad_amount_nu' => 'Nothing was changed: enter an amount from 1 to {1}.',
	'flash_bad_action'    => 'Nothing was changed: unknown action.',
	'flash_csrf'          => 'Nothing was changed: the form has expired. Reload the page and try again.',

	// ── JS strings ──
	'js_select_first'     => 'Select at least one VIP account first.',
	'js_remove_title_one' => 'Remove VIP from 1 account?',
	'js_remove_title_many'=> 'Remove VIP from {1} accounts?',
	'js_remove_text'      => 'They go back to their previous group right now. This cannot be undone.',
	'js_bad_amount'       => 'Enter an amount from 1 to {1} {2}.',
	'js_apply_title'      => '{1}?',
	'js_apply_text_one'   => '{1}: {2} {3} for 1 account.',
	'js_apply_text_many'  => '{1}: {2} {3} for {4} accounts.',
	'js_btn_remove'       => 'Remove VIP',
	'js_btn_apply'        => 'Apply',
	'js_btn_cancel'       => 'Cancel',
	'js_btn_ok'           => 'OK',
	'js_applying'         => 'Applying…',
);
?>
