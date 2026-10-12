<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// Comments Admin (admin/latest_comments.php)
// Keys ending in _html are printed as-is (may contain markup); all others are plain text.
$language['latest_comments'] = array (

	// ── Page ──
	'page_title'           => 'Comments Admin',
	'page_subtitle'        => 'Moderate, edit, move, copy and merge comments across all torrents',

	// ── KPI tiles ──
	'kpi_total'            => 'Total comments',
	'kpi_today'            => 'Today',
	'kpi_week'             => 'Last 7 days',
	'kpi_authors'          => 'Active authors (30d)',

	// ── Filters ──
	'lbl_username'         => 'Username',
	'ph_username'          => 'Search by user…',
	'lbl_torrent'          => 'Torrent',
	'ph_torrent'           => 'Search by torrent…',
	'lbl_date_from'        => 'Date From',
	'lbl_date_to'          => 'Date To',
	'btn_filter'           => 'Filter',
	'tip_reset_filters'    => 'Reset filters',

	// ── Loading / empty state ──
	'lbl_loading'          => 'Loading…',
	'lbl_loading_comments' => 'Loading comments…',
	'sec_empty'            => 'No comments found',
	'hint_empty'           => 'Try changing or resetting the filters.',

	// ── Comments table ──
	'sec_comments'         => 'Comments',
	'hint_comments_page'   => 'Newest first · page {1} of {2}',
	'lbl_found'            => '{1} found',
	'col_id'               => 'ID',
	'col_user'             => 'User',
	'col_torrent'          => 'Torrent',
	'col_comment'          => 'Comment',
	'col_date'             => 'Date',
	'col_actions'          => 'Actions',
	'tip_select_all_page'  => 'Select all on page',
	'tip_open_comment'     => 'Open comment in new tab',
	'tip_edit_comment'     => 'Edit comment',
	'tip_delete_comment'   => 'Delete comment',
	'lbl_deleted_user'     => 'Deleted user',
	'lbl_deleted_torrent'  => 'Deleted torrent #{1}',
	'lbl_edited'           => 'edited {1}',
	'btn_select_all'       => 'Select All',
	'btn_move'             => 'Move',
	'btn_copy'             => 'Copy',
	'btn_merge'            => 'Merge',
	'btn_delete_selected'  => 'Delete Selected',
	'pager_showing_html'   => 'Showing <b>{1}</b> – <b>{2}</b> of <b>{3}</b> comments',

	// ── Modals: shared ──
	'btn_cancel'           => 'Cancel',
	'tip_close'            => 'Close',
	'lbl_target_tid'       => 'Target Torrent ID',
	'ph_target_tid'        => 'Enter target torrent ID',
	'note_selected_html'   => '<strong>Selected:</strong> {1} comments.',
	'note_irreversible'    => 'This action cannot be undone.',

	// ── Modal: move ──
	'sec_move'             => 'Move Selected Comments',
	'hint_move'            => 'Reassign comments to another torrent',
	'btn_move_confirm'     => 'Move Comments',

	// ── Modal: copy ──
	'sec_copy'             => 'Copy Selected Comments',
	'hint_copy'            => 'Duplicate comments with attachments',
	'note_copy'            => 'Originals remain intact.',
	'btn_copy_confirm'     => 'Copy Comments',

	// ── Modal: merge ──
	'sec_merge'            => 'Merge Into One Comment',
	'hint_merge'           => 'Join selected texts chronologically',
	'note_merge'           => 'Texts are joined in chronological order into a single new comment on the target torrent; the author of the earliest selected comment becomes the author of the merged comment. Originals are deleted.',
	'btn_merge_confirm'    => 'Merge Comments',

	// ── Modal: edit ──
	'sec_edit'             => 'Edit Comment',
	'hint_edit'            => 'BBCode supported · live preview below',
	'tip_insert_torrent'   => 'Insert torrent',
	'btn_torrent'          => 'Torrent',
	'lbl_torrent_id_url'   => 'Torrent ID or URL',
	'ph_torrent_id'        => 'e.g. 17 or paste the torrent link',
	'btn_insert'           => 'Insert',
	'ph_edit_comment'      => 'Edit your comment…',
	'lbl_live_preview'     => 'Live Preview',
	'btn_save'             => 'Save Changes',

	// ── Modal: bulk delete ──
	'sec_bulk_delete'      => 'Confirm Deletion',
	'hint_bulk_delete'     => 'Attachments will be removed too',
	'lbl_bulk_delete'      => 'Are you sure you want to delete the selected comments?',
	'btn_yes_delete'       => 'Yes, Delete',

	// ── BBCode toolbar ──
	'bb_bold'              => 'Bold',
	'bb_italic'            => 'Italic',
	'bb_underline'         => 'Underline',
	'bb_strike'            => 'Strikethrough',
	'bb_left'              => 'Align left',
	'bb_center'            => 'Align center',
	'bb_right'             => 'Align right',
	'bb_color'             => 'Red color',
	'bb_size'              => 'Font size',
	'bb_url'               => 'Link',
	'bb_email'             => 'E-mail',
	'bb_img'               => 'Image',
	'bb_video'             => 'Video',
	'bb_youtube'           => 'YouTube',
	'bb_quote'             => 'Quote',
	'bb_code'              => 'Code',
	'bb_php'               => 'PHP code',
	'bb_nfo'               => 'NFO',
	'bb_spoiler'           => 'Spoiler',
	'bb_list'              => 'Bulleted list',
	'bb_list_num'          => 'Numbered list',
	'bb_list_item'         => 'List item',

	// ── AJAX errors (JSON) ──
	'err_csrf'             => 'Invalid security token',
	'err_method'           => 'Method not allowed',
	'err_no_selection'     => 'No comments selected',
	'err_no_valid_ids'     => 'No valid comment IDs',
	'err_not_found'        => 'Comment not found',
	'err_comments_not_found' => 'Comments not found',
	'err_text_short'       => 'Comment must contain meaningful text (min 3 chars)',
	'err_invalid_target'   => 'Invalid target torrent ID',
	'err_target_not_found' => 'Target torrent not found',
	'err_merge_min'        => 'Select at least 2 comments to merge',
	'err_unknown_action'   => 'Unknown action',

	// ── JS: loading / list ──
	'js_loading'           => 'Loading…',
	'js_loading_comments'  => 'Loading comments…',
	'js_load_failed'       => 'Failed to load comments: {1}',
	'js_error_title'       => 'Error',
	'js_error_prefix'      => 'Error: {1}',
	'js_ajax_error'        => 'AJAX error: {1}',
	'js_cancel'            => 'Cancel',
	'js_invalid_target'    => 'Please enter a valid target torrent ID',

	// ── JS: bulk delete ──
	'js_select_one'        => 'Please select at least one comment',
	'js_bulk_confirm_one'  => 'Are you sure you want to delete {1} selected comment?',
	'js_bulk_confirm_many' => 'Are you sure you want to delete {1} selected comments?',
	'js_deleting'          => 'Deleting…',
	'js_bulk_deleted'      => '{1} comment(s) deleted successfully',
	'js_err_delete_many'   => 'Error deleting comments',

	// ── JS: copy ──
	'js_select_copy'       => 'Please select at least one comment to copy',
	'js_copy_title'        => 'Copy comments?',
	'js_copy_text'         => 'Copy {1} selected comment(s) to torrent ID {2}?',
	'js_copy_btn'          => 'Copy',
	'js_copying'           => 'Copying…',
	'js_copied'            => '{1} comment(s) copied successfully!',

	// ── JS: merge ──
	'js_select_merge'      => 'Please select at least 2 comments to merge',
	'js_merge_title'       => 'Merge comments?',
	'js_merge_text'        => 'Merge {1} selected comments into one, on torrent ID {2}? This cannot be undone.',
	'js_merge_btn'         => 'Merge',
	'js_merging'           => 'Merging…',
	'js_merged'            => '{1} comments merged into comment #{2}!',

	// ── JS: move ──
	'js_select_move'       => 'Please select at least one comment to move',
	'js_move_title'        => 'Move comments?',
	'js_move_text'         => 'Are you sure you want to move {1} selected comments to torrent ID {2}?',
	'js_move_btn'          => 'Move',
	'js_moving'            => 'Moving…',
	'js_moved'             => '{1} comment(s) moved successfully!',

	// ── JS: edit / save ──
	'js_err_load_comment'  => 'Error loading comment',
	'js_preview_failed'    => 'Preview generation failed',
	'js_text_min'          => 'Comment must be at least 3 characters long',
	'js_text_meaningful'   => 'Comment must contain meaningful text',
	'js_saving'            => 'Saving…',
	'js_saved'             => 'Comment updated successfully',
	'js_unknown_error'     => 'Unknown error occurred',
	'js_err_save'          => 'Error saving comment',
	'js_network_error'     => 'Network error - please check your connection',

	// ── JS: single delete ──
	'js_delete_title'      => 'Delete comment?',
	'js_delete_text'       => 'Are you sure you want to delete this comment?',
	'js_delete_btn'        => 'Delete',
	'js_deleted'           => 'Comment deleted successfully',
	'js_err_delete_one'    => 'Error deleting comment',

	// ── JS: torrent embed preview ──
	'js_preview_loading'   => 'Loading preview…',
	'js_preview_load_failed' => 'Failed to load preview',
	'js_preview_seeders'   => '{1} seeders',
	'js_preview_leechers'  => '{1} leechers',
);
?>
