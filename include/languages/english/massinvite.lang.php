<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['massinvite'] = array (
	// ── Header ──
	'title'                => 'Mass Invites',
	'subtitle'             => 'Give or take invites from every active member of a group at once',

	// ── Result (after redirect) ──
	'flash_added'          => 'Added {1} invite(s) to {2}',
	'flash_removed'        => 'Removed {1} invite(s) from {2}',
	'flash_affected'       => '{1} user(s) affected · written to the site log',
	'lbl_all_groups'       => 'all groups',
	'lbl_group_n'          => 'Group {1}',

	// ── KPI tiles ──
	'kpi_total'            => 'Invites in hand',
	'kpi_holders'          => 'Members with invites',
	'kpi_average'          => 'Average',
	'kpi_top'              => 'Most invites',

	// ── Operation section ──
	'sec_operation'        => 'Operation',
	'sec_operation_sub'    => 'Choose what to do, how many and for whom',
	'opt_add'              => 'Add invites',
	'opt_add_hint'         => 'Everyone gets N more',
	'opt_remove'           => 'Remove invites',
	'opt_remove_hint'      => 'Up to N each, never below 0',
	'lbl_amount'           => 'Invites per user',
	'lbl_amount_unit'      => 'invites',
	'lbl_group'            => 'Target group',
	'hint_group'           => '“-” means every active, confirmed user',

	// ── Preview ──
	'lbl_impact'           => 'Impact',
	'lbl_affected'         => 'user(s) affected',
	'lbl_total_change'     => 'Total change',
	'lbl_change_initial'   => '+0 invites',

	// ── Action bar ──
	'hint_bulk'            => 'Bulk change — it can\'t be undone automatically',
	'btn_reset'            => 'Reset',
	'btn_preview'          => 'Preview',
	'btn_apply'            => 'Apply',

	// ── Errors ──
	'err_title'            => 'Error',
	'err_amount'           => 'Please enter an amount between 1 and {1}.',
	'err_csrf'             => 'Security check failed. Please refresh the page and try again.',

	// ── JS: preview ──
	'js_all_users'         => 'all users',
	'js_no_changes'        => 'No changes',
	'js_change'            => '{1}{2} invites',

	// ── JS: errors ──
	'js_error_title'       => 'Error',
	'js_error_generic'     => 'Something went wrong',
	'js_amount_range'      => 'Enter an amount between 1 and {1}.',
	'js_nobody'            => 'Nobody matches — no invites would change.',

	// ── JS: confirmation ──
	'js_confirm_add'       => 'Add {1} invite(s)?',
	'js_confirm_remove'    => 'Remove {1} invite(s)?',
	'js_confirm_summary'   => '{1} user(s) · {2}{3} invites in total',
	'js_confirm_plain'     => '{1}: {2} user(s), {3}{4} invites in total.',
	'js_never_below_zero'  => 'Never below zero',
	'js_logged'            => 'logged',
	'js_cant_undo'         => 'can\'t be undone',
	'js_btn_add'           => 'Add {1}',
	'js_btn_remove'        => 'Remove {1}',
	'js_cancel'            => 'Cancel',
	'js_applying'          => 'Applying…',
);
?>
