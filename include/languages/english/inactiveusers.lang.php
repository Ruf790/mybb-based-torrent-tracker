<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['inactiveusers'] = array (
	// ── Page header ──
	'head_title'          => 'Inactive Users ({1}+ days)',
	'pane_title'          => 'Inactive Users',
	'pane_sub'            => 'No activity for more than <b>{1} days</b> · accounts are deleted <b>{2} days</b> after the warning email',

	// ── Stats (KPI tiles) ──
	'stat_inactive'       => 'Inactive',
	'stat_not_warned'     => 'Not warned',
	'stat_warned'         => 'Warned',
	'stat_overdue'        => 'Overdue',

	// ── Flash messages ──
	'flash_no_selected'   => 'No users selected.',
	'flash_sent'          => 'Sent {1} warning email(s).',
	'flash_sent_partial'  => 'Sent {1} warning email(s), {2} failed.',
	'flash_deleted'       => '{1} user account(s) deleted.',
	'flash_skipped'       => '({1} protected account(s) skipped)',

	// ── Toolbar ──
	'lbl_selected'        => 'Selected: {1} of {2}',
	'btn_select_overdue'  => 'Select overdue',
	'tip_select_overdue'  => 'Select users whose warning period has expired',
	'btn_send_warning'    => 'Send Warning',
	'btn_delete'          => 'Delete',

	// ── Table headers ──
	'aria_select_all'     => 'Select all',
	'th_user'             => 'User',
	'th_email'            => 'Email',
	'th_ratio'            => 'Ratio',
	'th_joined'           => 'Joined',
	'th_last_access'      => 'Last Access',
	'th_status'           => 'Status',

	// ── Table rows ──
	'aria_select_user'    => 'Select {1}',
	'lbl_user_id'         => 'ID {1}',
	'lbl_ago'             => '{1} ago',
	'lbl_never'           => 'Never',
	'badge_not_warned'    => 'Not warned',
	'badge_warned'        => 'Warned {1} ago',
	'badge_overdue'       => 'Overdue',
	'hint_deletion_in'    => 'Deletion in {1} · {2}',
	'hint_expired'        => 'Warning expired {1} ago',

	// ── Empty state ──
	'empty_title'         => 'No inactive users found.',
	'empty_text'          => 'Everyone has been active within the last {1} days.',

	// ── Delete confirmation modal ──
	'modal_title'         => 'Confirm Deletion',
	'modal_question'      => 'Delete {1} account(s)?',
	'modal_warning'       => 'This action cannot be undone. Staff and protected accounts are skipped automatically.',
	'aria_close'          => 'Close',
	'btn_cancel'          => 'Cancel',
	'btn_confirm_delete'  => 'Delete Users',

	// ── Emails ({1} = username, {2} = site name, {3} = inactivity days, {4} = days until deletion, {5} = site URL) ──
	'mail_subject_inactive' => '{1} - Account Inactive!',
	'mail_subject_deleted'  => '{1} - Account Deleted!',
	'mail_body_inactive'    => '<p>Dear {1},</p><p>It has come to our attention that you registered at <b>{2}</b> more than <b>{3} days ago</b>, but haven\'t logged in since.</p><p>Did you forget about us?</p><p>We would be happy to see you around again!</p><p>If you don\'t log in within <b>{4} days</b> from now, we will <b><font color=red>delete</font></b> your account.</p><p>&nbsp;</p><p>Sincerely,</p><p>{2} Team</p><p><a href="{5}">{5}</a></p><p>&nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',
	'mail_body_deleted'     => '<p>Dear {1},</p><p>You have not logged in at <b>{2}</b> for more than <b>{3} days</b>.</p><p>You also didn\'t respond to the email we sent you <b>{4} days ago</b>.</p><p>Therefore we have decided to <b><font color=red>delete</font></b> your account, as it seems you are no longer interested in our site.</p><p>We are sorry to see you leave — feel free to come back at any time.</p><p>&nbsp;</p><p>Sincerely,</p><p>{2} Team</p><p><a href="{5}">{5}</a></p><p>&nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',

	// ── JS strings ──
	'js_select_one'       => 'Please select at least one user.',
	'js_confirm_send'     => 'Send warning emails to {1} user(s)?',
	'js_deleting'         => 'Deleting...',
);
?>
