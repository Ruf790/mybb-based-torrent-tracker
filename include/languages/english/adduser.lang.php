<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// admin/adduser.php — Create New Account
$language['adduser'] = array
(
	// ── Page ──────────────────────────────────────────────
	'title'                      => 'Create New Account',
	'subtitle'                   => 'Create an account by hand — it is confirmed immediately unless you ask for email activation',

	// ── Section headers ───────────────────────────────────
	'sec_account'                => 'Account',
	'sec_password'               => 'Password',
	'sec_traffic'                => 'Traffic & bonus',
	'sec_avatar'                 => 'Avatar',
	'sec_options'                => 'Options',

	// ── Field labels ──────────────────────────────────────
	'lbl_username'               => 'Username',
	'lbl_email'                  => 'Email',
	'lbl_usergroup'              => 'User group',
	'lbl_modcomment'             => 'Moderator note',
	'lbl_password'               => 'Password',
	'lbl_password2'              => 'Confirm',
	'lbl_uploaded'               => 'Uploaded',
	'lbl_downloaded'             => 'Downloaded',
	'lbl_seedbonus'              => 'Seed bonus',
	'lbl_invites'                => 'Invites',
	'lbl_optional'               => '(optional)',
	'lbl_generated'              => 'Generated:',

	// ── Placeholders ──────────────────────────────────────
	'ph_username'                => 'New username',
	'ph_modcomment'              => 'e.g. Invited by forum post #123',
	'ph_default_value'           => 'Default: {1}',
	'ph_default_gb'              => 'Default: {1} GB',

	// ── Hints ─────────────────────────────────────────────
	'hint_name_length'           => '{1}–{2} characters',
	'hint_email'                 => 'Used for login, notifications and activation',
	'hint_usergroup'             => 'Staff groups can\'t be assigned here',
	'hint_modcomment'            => 'Stored in the user\'s mod comment with today\'s date',
	'hint_pw_length'             => '{1}–{2} characters',
	'hint_pw_length_complex'     => '{1}–{2} characters, letters and numbers',
	'hint_signup_bonus'          => 'Empty = site signup bonus',
	'hint_signup_bonus_gb'       => 'Empty = site signup bonus ({1} GB)',
	'hint_avatar_url'            => 'Direct link to a JPG, PNG, GIF or WEBP image',
	'hint_avatar_drop'           => 'Drag & drop an image here or',
	'hint_avatar_limits'         => 'JPG, PNG, GIF, WEBP — size limit from the avatar settings',
	'hint_sendcredentials'       => 'The username and password are emailed to the user',
	'hint_confirm'               => 'Account stays “pending” until the user clicks the activation link',
	'hint_welcome_pm'            => 'A welcome PM is sent automatically',

	// ── Options (select / switches) ───────────────────────
	'opt_default_group'          => 'Default — {1}',
	'opt_default_group_fallback' => 'registration group',
	'opt_unit_b'                 => 'B',
	'opt_unit_mb'                => 'MB',
	'opt_unit_gb'                => 'GB',
	'opt_unit_tb'                => 'TB',
	'opt_sendcredentials'        => 'Email login credentials to this user',
	'opt_confirm'                => 'Request confirmation email',

	// ── Buttons / aria ────────────────────────────────────
	'btn_generate'               => 'Generate',
	'btn_copy'                   => 'Copy',
	'btn_tab_url'                => 'URL',
	'btn_tab_upload'             => 'Upload',
	'btn_choose_file'            => 'Choose file',
	'btn_remove_avatar'          => 'Remove avatar',
	'btn_add_another'            => 'Add another',
	'btn_go_profile'             => 'Go to profile',
	'aria_show_password'         => 'Show password',

	// ── Preview card ──────────────────────────────────────
	'pv_new_user'                => 'New user',
	'pv_no_email'                => 'no email yet',
	'pv_group'                   => 'Group',
	'pv_uploaded'                => 'Uploaded',
	'pv_downloaded'              => 'Downloaded',
	'pv_ratio'                   => 'Ratio',
	'pv_bonus'                   => 'Bonus',
	'pv_invites'                 => 'Invites',
	'pv_status'                  => 'Status',

	// ── Result boxes ──────────────────────────────────────
	'done_title'                 => 'Account created',
	'done_attention'             => '{1} can log in now, but something needs your attention:',
	'err_box_title'              => 'The account was not created:',

	// ── Validation errors ─────────────────────────────────
	'err_name_short'             => 'Username must be at least {1} characters long',
	'err_name_long'              => 'Username cannot be longer than {1} characters',
	'err_name_chars'             => 'Username contains characters that are not allowed',
	'err_name_taken'             => 'Username already exists',
	'err_name_banned'            => 'Username is not allowed',
	'err_email_invalid'          => 'That doesn\'t look like a valid email address.',
	'err_email_banned'           => 'The email address you have entered is currently disallowed from being used. Please enter a different email address.',
	'err_email_taken'            => 'The email address is already in use.',
	'err_pw_mismatch'            => 'The passwords didn\'t match! Must\'ve typoed. Try again.',
	'err_pw_short'               => 'Password must be at least {1} characters long',
	'err_pw_long'                => 'Password cannot be longer than {1} characters',
	'err_pw_same'                => 'Sorry, password cannot be the same as the username.',
	'err_pw_complex'             => 'Password must contain both letters and numbers',
	'err_usergroup'              => 'Invalid usergroup selected!',
	'err_avatar_invalid'         => 'Invalid avatar URL or image not accessible',
	'err_avatar_type'            => 'Unsupported avatar image type',
	'err_credentials_mail'       => 'Failed to send login credentials email - please deliver the password to the user manually. Password: {1}',

	// ── Flash messages ────────────────────────────────────
	'flash_created_but'          => 'Account created, but: {1}',

	// ── Welcome PM / emails ───────────────────────────────
	// {1} = site name
	'pm_welcome_subject'         => 'Welcome to {1}!',
	// {1} = username, {2} = site name, {3} = site URL
	'pm_welcome_body'            => 'Congratulations {1},

You are now a member of {2}, we would like to take this opportunity to say hello and welcome to {2}!

Please be sure to read the Rules: ({3}/rules.php) and the Faq: ({3}/faq.php#dl8) and be sure to stop by the Forums: ({3}/index2.php) and say Hello!

Enjoy your Stay.
The Staff of {2}',

	// {1} = site name
	'mail_credentials_subject'   => 'Your account details for {1}',
	// {1} = username, {2} = site name, {3} = password, {4} = site URL
	'mail_credentials_body'      => 'Hello {1},

An account has been created for you on {2} by staff.

Your login details:
Username: {1}
Password: {3}

You can log in here: {4}/member.php?action=login

For security, please consider changing your password after logging in.

The Staff of {2}',

	// {1} = site name
	'mail_activate_subject'      => 'Account Activation at {1}',
	// {1} = username, {2} = site name, {3} = site URL, {4} = user id, {5} = activation code
	'mail_activate_body'         => '{1},

To complete the registration process on {2}, you will need to go to the URL below in your web browser.

{3}/member.php?action=activate&id={4}&code={5}

If the above link does not work correctly, go to

{3}/member.php?action=activate

You will need to enter the following:
Username: {1}
Activation Code: {5}

Thank you,
{2} Staff',

	// ── JS: live checks ───────────────────────────────────
	'js_creating'                => 'Creating…',
	'js_name_len'                => '{1}–{2} characters',
	'js_name_chars'              => 'Characters < > & " \' \\ are not allowed',
	'js_name_taken'              => 'Already taken',
	'js_banned'                  => 'Disallowed by a ban filter',
	'js_available'               => 'Available',
	'js_email_hint'              => 'Used for login, notifications and activation',
	'js_email_invalid'           => 'Not a valid address',
	'js_email_taken'             => 'Used by another account',

	// ── JS: password ──────────────────────────────────────
	'js_pw_min'                  => 'At least {1} characters',
	'js_pw_max'                  => 'At most {1} characters',
	'js_pw_complex'              => 'Needs letters and numbers',
	'js_pw_same'                 => 'Must differ from the username',
	'js_pw_len'                  => '{1}–{2} characters',
	'js_pw_len_complex'          => '{1}–{2} characters, letters and numbers',
	'js_pw_match'                => 'Passwords match',
	'js_pw_mismatch'             => 'Passwords do not match',
	'js_strength_0'              => 'Very weak',
	'js_strength_1'              => 'Weak',
	'js_strength_2'              => 'Fair',
	'js_strength_3'              => 'Good',
	'js_strength_4'              => 'Strong',
	'js_strength_5'              => 'Very strong',
	'js_copied'                  => 'Password copied to clipboard',

	// ── JS: preview card ──────────────────────────────────
	'js_new_user'                => 'New user',
	'js_no_email'                => 'no email yet',
	'js_pending'                 => 'Pending activation',
	'js_confirmed'               => 'Confirmed',
	'js_unit_b'                  => 'B',
	'js_unit_kb'                 => 'KB',
	'js_unit_mb'                 => 'MB',
	'js_unit_gb'                 => 'GB',
	'js_unit_tb'                 => 'TB',
	'js_unit_pb'                 => 'PB',
);
?>
