<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// Download Manager (admin/downloadadd.php)
$language['downloadadd'] = array (
	// ── Page ──
	'page_title'       => 'Download Manager',
	'page_sub'         => 'Add or remove download amount — for one member or a whole group',
	'unit_gb'          => 'GB',

	// ── Mode ──
	'aria_operation'   => 'Operation',
	'mode_add'         => 'Add download',
	'hint_mode_add'    => 'Increases downloaded, lowers ratio',
	'mode_remove'      => 'Remove download',
	'hint_mode_remove' => 'Never below zero',

	// ── KPI tiles ──
	'kpi_users'        => 'Active users',
	'kpi_downloaded'   => 'Total downloaded',
	'kpi_uploaded'     => 'Total uploaded',
	'kpi_ratio'        => 'Site ratio',

	// ── Single user ──
	'sec_single'        => 'Single user',
	'sec_single_sub'    => 'Up to {1} GB',
	'lbl_username'      => 'Username',
	'ph_username'       => 'Exact username',
	'lbl_downloaded'    => 'Downloaded',
	'lbl_ratio'         => 'Ratio',
	'lbl_uploaded'      => 'uploaded',
	'hint_inactive'     => 'Disabled or not confirmed — can\'t be changed',
	'hint_missing'      => 'No user with this name',
	'lbl_amount_add'    => 'Add',
	'lbl_amount_remove' => 'Remove',
	'lbl_note'          => 'Note',
	'lbl_optional'      => '(optional)',
	'ph_note'           => 'Reason, saved to the mod comment',
	'btn_single_add'    => 'Add download',
	'btn_single_remove' => 'Remove download',

	// ── Whole group ──
	'sec_group'        => 'Whole group',
	'sec_group_sub'    => 'Same amount for every active member',
	'lbl_group'        => 'Group',
	'opt_all_users'    => 'All users',
	'lbl_per_user'     => 'Per user',
	'opt_choose'       => 'Choose…',
	'hint_counting'    => 'Counting…',
	'btn_group_add'    => 'Add for group',
	'btn_group_remove' => 'Remove for group',
	'fallback_group'   => 'Group {1}',

	// ── Recent changes ──
	'sec_recent'       => 'Recent changes',
	'sec_recent_sub'   => 'From the site log',
	'hint_no_recent'   => 'No changes logged yet. Changes made here will show up in this list.',

	// ── Result of the last action ──
	'done_add_user'      => 'Added {1} GB to {2}',
	'done_remove_user'   => 'Removed {1} GB from {2}',
	'done_add_group'     => 'Added {1} GB to {2}',
	'done_remove_group'  => 'Removed {1} GB from {2}',
	'done_add_all'       => 'Added {1} GB to all users',
	'done_remove_all'    => 'Removed {1} GB from all users',
	'done_mass_detail'   => '{1} user(s), {2} in total. Noted in mod comments and the site log.',
	// Output as-is (arguments are escaped and wrapped in <strong> by PHP) — plain text only
	'done_single_detail' => 'Downloaded {1} → {2}, ratio {3} → {4}. Noted in mod comment.',
	'btn_profile'        => 'Profile',

	// ── Errors ──
	'err_csrf'                => 'Security check failed. Please refresh the page and try again.',
	'err_bulk_amount'         => 'Please choose an amount between 1 and {1} GB.',
	'err_group_nothing'       => 'No active users in this group have any download to remove — nothing was changed.',
	'err_group_empty'         => 'This group has no active users — nothing was changed.',
	'err_update_users'        => 'Failed to update users.',
	'err_no_username'         => 'Please enter a username.',
	'err_single_amount'       => 'Amount must be between 1 and {1} GB.',
	'err_user_not_found'      => 'User not found, disabled or not confirmed.',
	'err_user_nothing'        => 'This user has no download to remove — nothing was changed.',
	'err_update_user'         => 'Failed to update the user.',
	'err_unexpected'          => 'An unexpected error occurred. Please try again.',

	// ── JS: common ──
	'js_all_users'      => 'All users',
	'js_counting'       => 'Counting…',
	'js_stat_failed'    => 'Could not load statistics',
	'js_gstat_active'   => '{1} active user(s)',
	'js_gstat_total'    => ', {1} in total',
	'js_gstat_withdown' => ', {1} with download',
	'js_cancel'         => 'Cancel',
	'js_working'        => 'Working…',
	'js_applying'       => 'Applying…',
	'js_note_add'       => 'Noted in mod comment and site log',
	'js_note_remove'    => 'Never below zero, noted in mod comment and site log',

	// ── JS: single user ──
	'js_user_missing_title'     => 'User not found',
	'js_user_missing_text'      => 'Check the username — it must match exactly.',
	'js_user_missing_alert'     => 'User not found.',
	'js_inactive_title'         => 'Account is inactive',
	'js_inactive_text'          => 'Disabled or unconfirmed accounts can\'t be changed.',
	'js_inactive_alert'         => 'Account is inactive.',
	'js_nothing_user_title'     => 'Nothing to remove',
	'js_nothing_user_text'      => 'This user has no download.',
	'js_single_title_add'       => 'Add {1} GB download?',
	'js_single_title_remove'    => 'Remove {1} GB download?',
	'js_single_sub_down'        => 'Downloaded {1} → {2}',
	'js_single_sub_ratio'       => 'Ratio {1} → {2}',
	'js_single_unchecked'       => 'User not checked yet',
	'js_single_btn_add'         => 'Add {1} GB',
	'js_single_btn_remove'      => 'Remove {1} GB',
	'js_single_fallback_add'    => 'Add {1} GB download to {2}?',
	'js_single_fallback_remove' => 'Remove {1} GB download from {2}?',

	// ── JS: whole group ──
	'js_mass_choose_title'    => 'Choose an amount',
	'js_mass_choose_text'     => 'How many GB per user?',
	'js_mass_choose_alert'    => 'Choose an amount per user.',
	'js_mass_loading_title'   => 'Still counting…',
	'js_mass_loading_text'    => 'Group statistics are loading, try again in a moment.',
	'js_mass_loading_alert'   => 'Group statistics are loading, try again.',
	'js_mass_noactive_title'  => 'No active users',
	'js_mass_noactive_text'   => 'This group has no active members to change.',
	'js_mass_nothing_title'   => 'Nothing to remove',
	'js_mass_nothing_text'    => 'No active member of this group has any download.',
	'js_mass_nothing_alert'   => 'Nothing to change in this group.',
	'js_mass_title_add'       => 'Add {1} GB download to a whole group?',
	'js_mass_title_remove'    => 'Remove {1} GB download from a whole group?',
	'js_mass_sub'             => '{1} user(s), {2} in total',
	'js_mass_btn_add'         => 'Yes, add for {1} user(s)',
	'js_mass_btn_remove'      => 'Yes, remove for {1} user(s)',
	'js_mass_fallback_add'    => 'Add {1} GB download for every member of {2}?',
	'js_mass_fallback_remove' => 'Remove {1} GB download for every member of {2}?',
);
?>
