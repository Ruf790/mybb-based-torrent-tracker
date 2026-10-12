<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['amountbonus'] = array (
	// ── Access ──
	'err_access_denied'   => 'Access Denied!',
	'err_no_permission'   => 'You do not have permission to access this page.',

	// ── Page header ──
	'page_title'          => 'Bonus Points Distribution',
	'page_subtitle'       => 'Give seed bonus points to one member or to a whole group',

	// ── KPI tiles ──
	'kpi_confirmed'       => 'Confirmed users',
	'kpi_circulation'     => 'Points in circulation',
	'kpi_avg'             => 'Average per user',
	'kpi_max'             => 'Richest balance',

	// ── Section headers ──
	'sec_single'          => 'Single user',
	'sec_single_sub'      => 'Exact username',
	'sec_bulk'            => 'Bulk distribution',
	'sec_bulk_sub'        => 'Every confirmed member of a group',
	'sec_recent'          => 'Recent distributions',
	'sec_recent_sub'      => 'From the site log',

	// ── Labels ──
	'lbl_username'        => 'Username',
	'lbl_points'          => 'Points',
	'lbl_points_per_user' => 'Points per user',
	'lbl_group'           => 'Target group',
	'lbl_unit'            => 'points',
	'lbl_close'           => 'Close',
	// {1} — users, {2} — points per user (HTML spans are substituted, output as is)
	'lbl_impact'          => '{1} users × {2} points',

	// ── Placeholders / tooltips ──
	'ph_username'         => 'Exact username',
	'ph_amount'           => 'Amount',
	'tip_myself'          => 'Myself',

	// ── Buttons ──
	'btn_clear'           => 'Clear',
	'btn_send'            => 'Send points',
	'btn_distribute'      => 'Distribute',

	// ── Target group select ──
	'opt_all_confirmed'   => 'All confirmed users ({1})',
	'opt_staff_protected' => ' — staff, protected',
	'opt_banned_group'    => ' — banned group',

	// ── Legacy group select (generateGroupSelect) ──
	'opt_grp_all'         => '👥 All User Groups',
	'opt_grp_2'           => '👤 User',
	'opt_grp_3'           => '⚡ Power User',
	'opt_grp_4'           => '⭐ VIP',
	'opt_grp_5'           => '📤 Uploader',
	'opt_grp_6'           => '🛡️ Moderator',
	'opt_grp_7'           => '👑 Administrator',
	'opt_grp_8'           => '🔧 Sysop',
	'opt_grp_protected'   => ' (staff — protected)',

	// ── Recent log ──
	'txt_nothing_yet'     => 'Nothing distributed yet.',

	// ── Flash messages (plain text — escaped on output) ──
	'flash_error_title'   => 'Error:',
	'flash_success_title' => 'Success!',
	'flash_bad_token'     => '⚠️ Invalid security token. Please refresh the page and try again.',
	'flash_bad_amount'    => '❌ Please enter a valid number between 1 and 1,000,000 for bonus points.',
	'flash_protected'     => '🚫 Bulk distribution to staff or administrative groups is not allowed.',
	'flash_no_target'     => 'Please specify either a username or select "All Users".',
	'flash_unexpected'    => '⚠️ An unexpected error occurred. Please try again.',
	'flash_critical'      => 'A critical error occurred. Please contact the administrator.',
	// {1} — points, {2} — target (flash_target_all / flash_target_group)
	'flash_bulk_done'     => '✅ {1} bonus points have been successfully sent to {2}.',
	'flash_target_all'    => 'all confirmed users',
	'flash_target_group'  => 'the {1} group',
	'flash_group_id'      => 'Group {1}',

	// ── Errors (shown in flash after "❌ ") ──
	'err_protected_group' => 'Bulk distribution to staff or administrative groups is not allowed.',
	'err_update_users'    => 'Failed to update user records.',
	'err_enter_username'  => 'Please enter a username.',
	'err_update_user'     => 'Failed to update user account.',
	'err_user_not_found'  => 'User \'{1}\' not found.',
	'err_user_fetch'      => 'Failed to retrieve user information.',

	// ── JS (admin/scripts/amountbonus.js, plain text) ──
	'js_sending'          => 'Sending…',
	'js_yes'              => 'Yes',
	'js_cancel'           => 'Cancel',
	'js_all_who'          => 'ALL confirmed users',
	'js_title_all'        => 'Send to every user?',
	'js_title_group'      => 'Distribute points?',
	// {1} — points per user, {2} — number of users, {3} — group
	'js_confirm_body'     => 'Give {1} points to {2} user(s) in {3}.',
	'js_confirm_total'    => 'Total: {1} points.',
	'js_confirm_undo'     => 'This can\'t be undone automatically.',
	'js_confirm_ok'       => 'Distribute',
	'js_user_not_found'   => 'User not found',
	'js_check_spelling'   => 'Check the exact spelling',
	// {1} — user ID, {2} — group
	'js_user_meta'        => 'ID {1} · {2}',
	'js_total'            => '{1} points in total',
	// {1} — js_warn_all_who (bold)
	'js_warn_all'         => '{1} on the site gets the points.',
	'js_warn_all_who'     => 'Every confirmed user',
	'js_warn_group'       => 'Every confirmed member of this group gets the points. It can\'t be undone automatically.',
);
?>
