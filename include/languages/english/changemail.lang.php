<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['changemail'] = array (
	// ── Page ──
	'page_title'         => 'Change User Email Address',
	'pane_title'         => 'Change User Email',
	'pane_sub'           => 'Set a new email address for an account',
	'footer_note'        => 'Super administrators can only be changed by another super administrator · every change is logged',

	// ── Form: labels ──
	'lbl_user'           => 'User',
	'lbl_email'          => 'New email',

	// ── Form: placeholders ──
	'ph_user'            => 'ID or username',
	'ph_email'           => 'user@example.com',

	// ── Form: hints ──
	'hint_user'          => 'Numeric ID or the exact username',
	'hint_email'         => 'The user will receive mail at this address',

	// ── Preview ──
	'flag_protected'     => 'Protected',

	// ── Buttons ──
	'btn_clear'          => 'Clear',
	'btn_submit'         => 'Change email',
	'btn_another'        => 'Change another',
	'btn_profile'        => 'View profile',

	// ── Result ──
	'res_title'          => 'Email changed',
	'res_id'             => 'ID {1}',
	'res_logged'         => 'Written to the site log',

	// ── Messages ──
	'flash_success'      => 'Email successfully updated.',
	'err_security_title' => 'Security Error',
	'err_security_token' => 'Invalid security token. Please refresh the page and try again.',
	'err_required'       => 'Please fill in all required fields.',
	'err_user_not_found' => 'No user found with this ID or username.',
	'err_super_admin'    => 'You do not have permission to change a super administrator\'s email.',
	'err_invalid_email'  => 'Invalid email address format.',
	'err_same_email'     => 'This is already the current email of this user.',
	'err_email_taken'    => 'This email address is already used by another account.',
	'err_db'             => 'Database error: Unable to update email.',

	// ── JS: preview ──
	'js_not_found'       => 'User not found',
	'js_not_found_hint'  => 'Check the ID or username',
	'js_none'            => '(none)',
	'js_meta'            => 'ID {1} · joined {2}',

	// ── JS: address check ──
	'js_same'            => 'Same as the current email',
	'js_invalid'         => 'Not a valid address',
	'js_taken'           => 'Used by another account',
	'js_available'       => 'Available',

	// ── JS: confirmation ──
	'js_confirm_title'   => 'Change email?',
	'js_confirm_ok'      => 'Change email',
	'js_cancel'          => 'Cancel',
	'js_confirm_who'     => 'Change the email of {1}',
	'js_confirm_from'    => 'from {1}',
	'js_confirm_to'      => 'to {1}?',
	'js_saving'          => 'Saving…',
);
?>
