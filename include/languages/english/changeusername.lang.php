<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['changeusername'] = array (
	// ── Page / header ──
	'page_title'          => 'Change Username',
	'head_title'          => 'Change Username',
	'head_sub'            => 'Rename an account — validated by the forum\'s own user rules',
	'foot_note'           => 'Super administrators can only be renamed by another super administrator · every change is logged',

	// ── Form: labels ──
	'lbl_user'            => 'User',
	'lbl_new'             => 'New username',
	'lbl_user_id'         => 'User ID {1}',

	// ── Form: placeholders ──
	'ph_user'             => 'ID or current username',
	'ph_new'              => 'New name',

	// ── Form: hints ──
	'hint_user'           => 'Numeric ID or the exact current name',
	'hint_new'            => '3–25 characters; the forum\'s name rules apply',

	// ── Buttons ──
	'btn_clear'           => 'Clear',
	'btn_continue'        => 'Continue',
	'btn_cancel'          => 'Cancel',
	'btn_confirm'         => 'Yes, rename',
	'btn_change_another'  => 'Change another',
	'btn_view_profile'    => 'View profile',

	// ── Confirmation screen ──
	'sec_confirm'         => 'Confirm the change',
	'conf_super_title'    => 'Super Administrator account.',
	'conf_super_text'     => 'Double-check before renaming.',
	'conf_pt_login'       => 'The user logs in with the new name from now on',
	'conf_pt_posts'       => 'Posts, comments and profile show the new name',
	'conf_pt_log'         => 'The change is written to the site log',
	'conf_pt_undo'        => 'To undo it, rename the account back manually',
	'conf_checkbox'       => 'I\'ve checked the new name and want to rename this account',

	// ── Result screen ──
	'sec_success'         => 'Username changed',
	'res_logged'          => 'written to the site log',

	// ── Flash / errors ──
	'flash_success'       => 'Username successfully updated.',
	'err_token'           => 'Invalid security token',
	'err_required'        => 'Please fill in all required fields.',
	'err_not_found'       => 'No user found with this ID or username.',
	'err_super'           => 'You do not have permission to change a super administrator\'s username.',
	'err_length'          => 'Username must be between 3 and 25 characters.',
	'err_same'            => 'The new username is the same as the current one.',
	'err_taken'           => 'This username is already taken.',
	'err_validation'      => 'Validation failed.',
	'err_update_failed'   => 'Failed to update the username.',
	'err_system'          => 'System error: {1}',

	// ── JS strings ──
	'js_hint_default'     => '3–25 characters; the forum\'s name rules apply',
	'js_not_found'        => 'User not found',
	'js_not_found_meta'   => 'Check the ID or name',
	'js_meta'             => 'ID {1} · joined {2}',
	'js_flag_protected'   => 'Protected',
	'js_flag_super'       => 'Super admin',
	'js_len'              => 'Must be 3–25 characters',
	'js_taken'            => 'Already taken',
	'js_available'        => 'Available',
);
?>
