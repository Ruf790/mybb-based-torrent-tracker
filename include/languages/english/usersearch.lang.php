<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

/**
 * Language file: admin/usersearch.php (User Search) + admin/scripts/usersearch.js
 * {1}, {2}… — placeholders, filled via ags_fmt() in PHP and t() in JS.
 * All strings are plain text (no HTML).
 */

$language['usersearch'] = array (

	// ── Page ──
	'page_title'          => 'User Search',
	'btn_latest'          => 'Latest Users',
	'sec_latest'          => 'Latest Users',
	'sec_found'           => 'Users found: {1}',
	'msg_no_users'        => 'No users found.',

	// ── Stats (KPI tiles) ──
	'stat_total'          => 'Total Users',
	'stat_active'         => 'Active',
	'stat_banned'         => 'Banned',
	'stat_new7'           => 'New (7d)',
	'stat_today'          => 'New Today',
	'stat_online'         => 'Online (15m)',

	// ── Quick filters ──
	'qf_title'            => 'Quick filters:',
	'qf_banned'           => 'Banned',
	'qf_today'            => 'New Today',
	'qf_new7'             => 'New 7 Days',
	'qf_latest10'         => 'Latest 10',
	'qf_active'           => 'Active',

	// ── Search form: labels ──
	'lbl_username'        => 'Username',
	'lbl_email'           => 'Email',
	'lbl_group'           => 'Group',
	'lbl_status'          => 'Status',
	'lbl_regip'           => 'Reg IP',
	'lbl_lastip'          => 'Last IP',
	'lbl_country'         => 'Country',
	'lbl_exactmatch'      => 'Exact match username',
	'lbl_reg_from'        => 'Reg Date From',
	'lbl_reg_to'          => 'Reg Date To',
	'lbl_active_from'     => 'Last Active From',
	'lbl_active_to'       => 'Last Active To',
	'lbl_min_up'          => 'Min Uploaded (MB)',
	'lbl_max_up'          => 'Max Uploaded (MB)',
	'lbl_min_ratio'       => 'Min Ratio',
	'lbl_max_ratio'       => 'Max Ratio',
	'lbl_warnings'        => 'Warnings',
	'lbl_orderby'         => 'Order by',
	'lbl_direction'       => 'Direction',

	// ── Search form: placeholders ──
	'ph_username'         => 'Username',
	'ph_email'            => 'Email',
	'ph_regip'            => 'Reg IP',
	'ph_lastip'           => 'Last IP',
	'ph_country'          => 'Country',
	'ph_date'             => 'YYYY-MM-DD',

	// ── Select options ──
	'opt_all_groups'      => 'All groups',
	'opt_all'             => 'All',
	'opt_active'          => 'Active',
	'opt_banned'          => 'Banned',
	'opt_username'        => 'Username',
	'opt_email'           => 'Email',
	'opt_id'              => 'ID',
	'opt_asc'             => 'Ascending',
	'opt_desc'            => 'Descending',
	'opt_change_group'    => 'Change group...',

	// ── Buttons ──
	'btn_search'          => 'Search',
	'btn_clear'           => 'Clear',
	'btn_ban'             => 'Ban',
	'btn_unban'           => 'Unban',
	'btn_pm'              => 'Send PM',
	'btn_apply'           => 'Apply',
	'btn_delete'          => 'Delete',
	'btn_clear_sel'       => 'Clear',

	// ── Bulk action bar ──
	// {1} = counter element (output as-is)
	'bulk_selected'       => 'Selected: {1} users',

	// ── Tooltips / title / aria-label / alt ──
	'tip_clear'           => 'Clear',
	'tip_online'          => 'Online now',
	'tip_last_seen'       => 'Last seen {1}',
	'tip_delete'          => 'Delete',
	'tip_change_avatar'   => 'Click to change avatar',
	'tip_toggle_passkey'  => 'Show/Hide',
	'tip_copy_passkey'    => 'Copy',
	'tip_select_all'      => 'Select all',
	'alt_avatar'          => 'avatar',

	// ── Table headers ──
	'th_id'               => 'ID',
	'th_avatar'           => 'Avatar',
	'th_username'         => 'Username',
	'th_email'            => 'Email',
	'th_group'            => 'Group',
	'th_ips'              => 'Reg IP/Last IP',
	'th_updown'           => 'Upl/Down',
	'th_ratio'            => 'Ratio',
	'th_info'             => 'Info',
	'th_actions'          => 'Actions',

	// ── Badges ──
	'badge_warned'        => 'Warned ×{1}',
	'badge_donor'         => 'Donor',

	// ── Bulk actions: results (JSON → toast) ──
	'flash_banned'        => '{1} user(s) banned',
	'flash_unbanned'      => '{1} user(s) unbanned',
	'flash_pm_sent'       => '{1} PM(s) sent',
	'flash_group_changed' => '{1} user(s) moved to group {2}',
	'flash_group_skipped' => '(some were skipped: super admin or your own account)',
	'flash_deleted'       => '{1} user(s) deleted',
	'flash_del_super'     => '(you do not have permission to delete a super administrator account)',
	'flash_del_self'      => '(some were skipped: your own account)',

	// ── Errors ──
	'err_prefix'          => 'Error: {1}',
	'err_security'        => 'Security check failed',
	'err_security_refresh'=> 'Security check failed. Please refresh the page and try again.',
	'err_no_users'        => 'No users selected',
	'err_pm_required'     => 'Subject and message are required',
	'err_invalid_group'   => 'Invalid group',
	'err_no_eligible'     => 'No eligible users (super admins and your own account are protected)',
	'err_unknown_action'  => 'Unknown action',
	'err_count_query'     => 'Count query failed: {1}',
	'err_data_query'      => 'Data query failed: {1}',

	// ── Avatar upload (server) ──
	'err_av_not_logged'   => 'You are not logged in',
	'err_av_no_uid'       => 'Profile uid is not specified',
	'err_av_no_perm'      => 'You are not allowed to change this avatar',
	'err_av_no_file'      => 'File is not uploaded',
	'msg_av_updated'      => 'Avatar updated',

	// ── JS: passkey / common ──
	'js_passkey_copied'   => 'Passkey copied!',
	'js_copy_failed'      => 'Failed to copy',
	'js_select_group'     => 'Please select a group first',
	'js_users_selected'   => '{1} users selected',
	'js_ids'              => 'IDs: {1}',
	'js_cancel'           => 'Cancel',
	'js_processing'       => 'Processing {1} users...',
	'js_error'            => 'Error occurred',
	'js_request_failed'   => 'Request failed',

	// ── JS: ban modal ──
	'js_ban_title'        => 'Ban Users',
	'js_ban_duration'     => 'Ban Duration',
	'js_ban_reason'       => 'Ban Reason',
	'js_ban_reason_ph'    => 'Enter reason for ban...',
	'js_ban_reason_hint'  => 'Optional. Max 255 characters.',
	'js_ban_confirm'      => 'Ban {1} Users',
	'js_banning'          => 'Banning...',

	// ── JS: ban durations ──
	'js_bt_1d'            => '1 Day',
	'js_bt_2d'            => '2 Days',
	'js_bt_3d'            => '3 Days',
	'js_bt_4d'            => '4 Days',
	'js_bt_5d'            => '5 Days',
	'js_bt_6d'            => '6 Days',
	'js_bt_1w'            => '1 Week',
	'js_bt_2w'            => '2 Weeks',
	'js_bt_3w'            => '3 Weeks',
	'js_bt_1m'            => '1 Month',
	'js_bt_2m'            => '2 Months',
	'js_bt_3m'            => '3 Months',
	'js_bt_4m'            => '4 Months',
	'js_bt_5m'            => '5 Months',
	'js_bt_6m'            => '6 Months',
	'js_bt_1y'            => '1 Year',
	'js_bt_2y'            => '2 Years',
	'js_bt_perm'          => 'Permanent',

	// ── JS: delete modal ──
	'js_del_title'        => 'Confirm Bulk Deletion',
	'js_del_question'     => 'Are you sure you want to delete these accounts?',
	'js_del_warning'      => 'This action cannot be undone. All user data will be permanently removed.',
	'js_del_confirm'      => 'Yes, Delete {1} Accounts',
	'js_deleting'         => 'Deleting...',

	// ── JS: PM modal ──
	'js_pm_title'         => 'Send PM',
	'js_pm_subject'       => 'Subject',
	'js_pm_subject_ph'    => 'Subject...',
	'js_pm_message'       => 'Message',
	'js_pm_message_ph'    => 'Write your message...',
	'js_pm_confirm'       => 'Send to {1} Users',
	'js_pm_fill'          => 'Please fill in both subject and message',
	'js_sending'          => 'Sending...',

	// ── JS: avatar upload ──
	'js_av_bad_type'      => 'Allowed JPG/JPEG/PNG/GIF/WebP',
	'js_av_too_big'       => 'File is too big (max. {1} MB)',
	'js_uploading'        => 'Uploading…',
	'js_av_failed'        => 'Upload failed',
	'js_av_error'         => 'Upload error',
	'js_alt_avatar'       => 'avatar',

);
?>
