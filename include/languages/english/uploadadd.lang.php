<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['uploadadd'] = array (
	// ── Page ──
	'page_title'          => 'Upload Manager',
	'page_subtitle'       => 'Add or remove upload credit — for one member or a whole group',

	// ── Mode ──
	'aria_operation'      => 'Operation',
	'opt_mode_add'        => 'Add upload',
	'hint_mode_add'       => 'Credit extra upload',
	'opt_mode_remove'     => 'Remove upload',
	'hint_mode_remove'    => 'Never below zero',
	'lbl_verb_add'        => 'Add',
	'lbl_verb_remove'     => 'Remove',

	// ── KPI tiles ──
	'kpi_users'           => 'Active users',
	'kpi_uploaded'        => 'Total uploaded',
	'kpi_downloaded'      => 'Total downloaded',
	'kpi_ratio'           => 'Site ratio',

	// ── Single user ──
	'sec_single'          => 'Single user',
	'hint_single_max'     => 'Up to {1} GB',
	'lbl_username'        => 'Username',
	'ph_username'         => 'Exact username',
	'lbl_uploaded'        => 'Uploaded',
	'hint_inactive'       => 'Disabled or not confirmed — can\'t be changed',
	'btn_single_suffix'   => 'upload',

	// ── Whole group ──
	'sec_group'           => 'Whole group',
	'hint_group'          => 'Same amount for every active member',
	'lbl_group'           => 'Group',
	'lbl_per_user'        => 'Per user',
	'lbl_group_n'         => 'Group {1}',
	'opt_all_users'       => 'All users',
	'opt_choose'          => 'Choose…',
	'btn_group_suffix'    => 'for group',

	// ── Recent changes ──
	'sec_recent'          => 'Recent changes',
	'hint_recent'         => 'From the site log',
	'empty_recent'        => 'No changes logged yet.',

	// ── Flash messages ──
	'flash_single_added'     => 'Added {1} GB to {2}',
	'flash_single_removed'   => 'Removed {1} GB from {2}',
	'flash_group_added'      => 'Added {1} GB to every member of {2}',
	'flash_group_removed'    => 'Removed {1} GB from every member of {2}',
	'flash_all_added'        => 'Added {1} GB to all users',
	'flash_all_removed'      => 'Removed {1} GB from all users',
	'flash_mass_detail'      => '{1} user(s) · {2} in total · noted in mod comments and the site log',
	'flash_single_detail'    => 'Uploaded {1} → {2} · noted in mod comment',
	'btn_profile'            => 'Profile',

	// ── Errors ──
	'err_csrf'            => 'Security check failed. Please refresh the page and try again.',
	'err_bulk_amount'     => 'Please choose an amount between 1 and {1} GB.',
	'err_username'        => 'Please enter a username.',
	'err_single_amount'   => 'Amount must be between 1 and {1} GB.',
	'err_user_not_found'  => 'User not found, disabled or not confirmed.',
	'err_update_users'    => 'Failed to update users.',
	'err_update_user'     => 'Failed to update the user.',
	'err_unexpected'      => 'An unexpected error occurred. Please try again.',

	// ── JS: common ──
	'js_verb_add'         => 'Add',
	'js_verb_remove'      => 'Remove',
	'js_all_users'        => 'All users',
	'js_cancel'           => 'Cancel',
	'js_working'          => 'Working…',
	'js_applying'         => 'Applying…',
	'js_note_add'         => 'Noted in mod comment and site log',
	'js_note_remove'      => 'Never below zero · noted in mod comment and site log',

	// ── JS: group preview ──
	'js_group_active'     => '{1} active user(s)',
	'js_group_total'      => '{1} in total',
	'js_group_withup'     => '{1} with upload',

	// ── JS: single user confirm ──
	'js_single_sub'       => 'Uploaded {1} → {2}',
	'js_not_checked'      => 'User not checked yet',
	'js_single_title_add'    => 'Add {1} GB?',
	'js_single_title_remove' => 'Remove {1} GB?',
	'js_single_btn_add'      => 'Add {1} GB',
	'js_single_btn_remove'   => 'Remove {1} GB',
	'js_single_fb_add'       => 'Add {1} GB to {2}?',
	'js_single_fb_remove'    => 'Remove {1} GB from {2}?',

	// ── JS: group confirm ──
	'js_amount_title'     => 'Choose an amount',
	'js_amount_text'      => 'How many GB per user?',
	'js_amount_alert'     => 'Choose an amount per user.',
	'js_mass_title_add'      => 'Add {1} GB to a whole group?',
	'js_mass_title_remove'   => 'Remove {1} GB from a whole group?',
	'js_mass_sub'            => '{1} user(s) · {2} in total',
	'js_mass_btn_add'        => 'Yes, add for {1} user(s)',
	'js_mass_btn_remove'     => 'Yes, remove for {1} user(s)',
	'js_mass_fb_add'         => 'Add {1} GB for every member of {2}?',
	'js_mass_fb_remove'      => 'Remove {1} GB from every member of {2}?',
);
?>
