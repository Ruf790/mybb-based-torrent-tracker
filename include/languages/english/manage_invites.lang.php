<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_invites'] = array (
	// ── Page ──────────────────────────────────────────────────────────────
	'page_title'          => 'Invite Manager',
	'pane_title'          => 'Invite Management',
	'pane_subtitle'       => 'Manage and monitor all invitation codes',

	// ── Errors ────────────────────────────────────────────────────────────
	'err_invalid_token'   => 'Invalid security token',

	// ── Stat tiles ────────────────────────────────────────────────────────
	'stat_total'          => 'Total',
	'stat_pending'        => 'Pending',
	'stat_used'           => 'Used',
	'stat_expired'        => 'Expired',
	'stat_revoked'        => 'Revoked',
	'hint_stat_total'     => 'Total invitations generated',
	'hint_stat_pending'   => 'Awaiting activation',
	'hint_stat_used'      => 'Successfully used',
	'hint_stat_expired'   => 'Past expiration date',
	'hint_stat_revoked'   => 'Manually revoked',

	// ── Invite statuses (badges, filter options) ──────────────────────────
	'status_pending'      => 'Pending',
	'status_used'         => 'Used',
	'status_expired'      => 'Expired',
	'status_revoked'      => 'Revoked',

	// ── Filters ───────────────────────────────────────────────────────────
	'lbl_search'          => 'Search',
	'ph_search'           => 'Username, email or invite code...',
	'lbl_status'          => 'Status',
	'opt_all_statuses'    => 'All Statuses',
	'btn_apply_filters'   => 'Apply Filters',
	'btn_reset'           => 'Reset',

	// ── Add invites ───────────────────────────────────────────────────────
	'lbl_add_invites'     => 'Add Invites to User',
	'lbl_user_id'         => 'User ID',
	'ph_user_id'          => 'Enter user ID',
	'ph_amount'           => 'Amount',
	'btn_add_invites'     => 'Add Invites',

	// ── Bulk actions bar ──────────────────────────────────────────────────
	'lbl_selected_count'  => '{1} invite(s) selected',
	'btn_revoke_selected' => 'Revoke Selected',
	'btn_delete_selected' => 'Delete Selected',
	'btn_cancel'          => 'Cancel',

	// ── Invites table ─────────────────────────────────────────────────────
	'sec_list'            => 'Invitations List',
	'lbl_total_badge'     => '{1} total',
	'lbl_page_of'         => 'Page {1} of {2}',
	'col_id'              => 'ID',
	'col_code'            => 'Invite Code',
	'col_inviter'         => 'Inviter',
	'col_invitee'         => 'Invitee',
	'col_email'           => 'Email',
	'col_status'          => 'Status',
	'col_created'         => 'Created',
	'col_expires'         => 'Expires',
	'col_ip'              => 'IP Addresses',
	'col_actions'         => 'Actions',
	'tip_copy_code'       => 'Copy code',
	'tip_revoke'          => 'Revoke',
	'tip_delete'          => 'Delete',

	// ── Empty state ───────────────────────────────────────────────────────
	'empty_title'         => 'No invites found',
	'empty_hint'          => 'Try adjusting your filters or create new invites',

	// ── Invite e-mail ({1} inviter, {2} site name, {3} link, {4} days) ────
	'mail_subject'        => 'You\'ve been invited to {1}',
	'mail_body'           => "Hello!\n\n{1} has invited you to join {2}.\n\nRegister here:\n{3}\n\nThis invite expires in {4} days.\n\n— {2} Team",

	// ── JS strings ────────────────────────────────────────────────────────
	'js_cancel'               => 'Cancel',
	'js_delete'               => 'Delete',
	'js_revoke'               => 'Revoke',
	'js_deleting'             => 'Deleting…',
	'js_revoking'             => 'Revoking…',
	'js_no_selected'          => 'No invites selected',
	'js_bulk_delete_title'    => 'Delete {1} invite(s)?',
	'js_bulk_revoke_title'    => 'Revoke {1} invite(s)?',
	'js_bulk_delete_text'     => 'The selected invites will be removed permanently. This cannot be undone.',
	'js_bulk_revoke_text'     => 'Pending invite codes will stop working. Used and expired invites are not affected.',
	'js_single_delete_title'  => 'Delete invite #{1}?',
	'js_single_revoke_title'  => 'Revoke invite #{1}?',
	'js_single_delete_text'   => 'The invite will be removed permanently.',
	'js_single_revoke_text'   => 'The invite code will stop working.',
	'js_copied'               => 'Invite code copied to clipboard!',
	'js_copy_failed'          => 'Failed to copy code',
);
?>
