<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['announcements_forum'] = array (
	// ── Page titles ──
	'pane_list'              => 'Forum Announcements',
	'pane_add'               => 'New Forum Announcement',
	'pane_edit'              => 'Edit Forum Announcement',
	'pane_view'              => 'View: {1}',

	// ── List ──
	'sec_announcements'      => 'Announcements',
	'lbl_total'              => 'Total: {1}',
	'col_id'                 => 'ID',
	'col_subject'            => 'Subject',
	'col_forum'              => 'Forum',
	'col_status'             => 'Status',
	'col_start'              => 'Start',
	'col_end'                => 'End',
	'col_actions'            => 'Actions',
	'lbl_by_author'          => 'by {1}',
	'lbl_unknown'            => 'Unknown',
	'lbl_forum_n'            => 'Forum #{1}',
	'lbl_empty'              => 'No forum announcements yet',

	// ── Forum / type badges ──
	'badge_global'           => '🌐 Global',
	'badge_tracker'          => 'Tracker',
	'opt_global_all'         => '🌐 Global (All Forums)',
	'opt_type_global'        => 'global',
	'opt_type_forum'         => 'forum',

	// ── Status ──
	'status_scheduled'       => 'Scheduled',
	'status_expired'         => 'Expired',
	'status_active'          => 'Active',

	// ── Buttons / titles ──
	'btn_new'                => 'New Announcement',
	'btn_view'               => 'View',
	'btn_edit'               => 'Edit',
	'btn_duplicate'          => 'Duplicate',
	'btn_delete'             => 'Delete',
	'btn_back'               => 'Back',
	'btn_cancel'             => 'Cancel',
	'btn_close'              => 'Close',
	'btn_publish'            => 'Publish Announcement',
	'btn_update'             => 'Update Announcement',

	// ── View ──
	'aria_breadcrumb'        => 'breadcrumb',
	'lbl_views_count'        => '{1} views',
	'lbl_active_period'      => 'Active:',
	'lbl_now'                => 'Now',
	'lbl_no_end'             => '∞ (No end)',
	'sec_actions'            => 'Actions',
	'sec_details'            => 'Details',
	'lbl_id'                 => 'ID',
	'lbl_type'               => 'Type',
	'lbl_views'              => 'Views',
	'lbl_words'              => 'Words',

	// ── Form ──
	'sec_basic'              => 'Basic Settings',
	'sec_schedule'           => 'Schedule',
	'sec_message'            => 'Message',
	'lbl_subject'            => 'Subject',
	'lbl_forum'              => 'Forum',
	'lbl_start_date'         => 'Start Date',
	'lbl_end_date'           => 'End Date',
	'opt_end_infinite'       => 'No end (Permanent)',
	'opt_end_finite'         => 'Set end date',
	'hint_time'              => 'HH:MM',
	'hint_message'           => 'Write your announcement using BBCode...',
	'hint_char_count'        => '{1} / 5000 characters',

	// ── Months ──
	'opt_month_01'           => 'January',
	'opt_month_02'           => 'February',
	'opt_month_03'           => 'March',
	'opt_month_04'           => 'April',
	'opt_month_05'           => 'May',
	'opt_month_06'           => 'June',
	'opt_month_07'           => 'July',
	'opt_month_08'           => 'August',
	'opt_month_09'           => 'September',
	'opt_month_10'           => 'October',
	'opt_month_11'           => 'November',
	'opt_month_12'           => 'December',

	// ── Delete modal ──
	'modal_delete_title'     => 'Delete Announcement',
	'modal_delete_text'      => 'You are about to delete:',
	'modal_delete_warn'      => 'This action cannot be undone.',

	// ── Validation errors ──
	'err_subject_required'   => 'Subject is required.',
	'err_message_required'   => 'Message is required.',
	'err_start_invalid'      => 'Invalid start date.',
	'err_end_invalid'        => 'Invalid end date.',
	'err_end_before_start'   => 'End date must be after start date.',

	// ── Flash messages ──
	'flash_not_found'        => 'Announcement not found.',
	'flash_invalid_id'       => 'Invalid ID.',
	'flash_added'            => 'Announcement added successfully.',
	'flash_updated'          => 'Announcement updated successfully.',
	'flash_deleted'          => 'Announcement deleted.',
	'flash_delete_cancelled' => 'Deletion cancelled.',

	// ── AJAX (duplicate) ──
	'json_invalid_request'   => 'Invalid request.',
	'json_not_found'         => 'Not found.',
	'json_duplicated'        => 'Duplicated successfully.',
	'val_copy_of'            => 'Copy of {1}',

	// ── JS ──
	'js_confirm_duplicate'   => 'Duplicate this announcement?',
);
?>
