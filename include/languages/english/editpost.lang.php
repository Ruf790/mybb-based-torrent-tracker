<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// editpost.php
$language['editpost'] = array
(


'update_attachment' => "Update Attachment",
'remove_attachment' => "Remove",
'add_attachment' => "Add Attachment",
'new_attachment' => "New Attachment:",



// ── Page / navigation ──
'nav_edit_post' => 'Edit Post',
'pane_edit_post' => 'Edit This Post',
'title_post_not_found' => 'Post Not Found',
'title_access_denied' => 'Access Denied',

// ── Form: panes / links ──
'pane_post_options' => 'Post Options',
'pane_attachments' => 'Attachments',
'pane_mod_options' => 'Moderator Options',
'pane_poll' => 'Poll',

// ── Form: fields / buttons ──
'ph_edit_reason' => 'Edit reason (optional)',
'btn_preview_post' => 'Preview Post',
'btn_update_post' => 'Update Post',

// ── Moderator options ──
'lbl_close_thread' => 'Close Thread',
'lbl_stick_thread' => 'Stick Thread',

// ── Poll ──
'tip_poll_desc' => 'Optionally you may attach a poll to this thread.',
'lbl_poll_check' => 'I want to post a poll',
'lbl_poll_num_options' => 'Number of options:',
'hint_poll_max_options' => '(Maximum: {1})',

// ── Attachments ──
'lbl_new_attachment' => 'New Attachment:',
'btn_add_attachment' => 'Add Attachment',
'btn_update_attachment' => 'Update Attachment',
'btn_remove_attachment' => 'Remove',
'btn_approve_attachment' => 'Approve',
'btn_unapprove_attachment' => 'Unapprove',
'btn_insert_into_post' => 'Insert Into Post',
'tip_attach_quota' => 'Your allocated attachment usage quota is {1}.',
'tip_attach_usage' => 'You are currently using <strong>{1}</strong>.',
'lbl_view_attachments' => '[View My Attachments]',
'lbl_unlimited' => 'unlimited',

// ── Delete post (card + modal) ──
'tip_delete_note' => '<b>Note:</b> If this post is the first post in a thread deleting it will result in deletion of the whole thread.',
'btn_delete_now' => 'Delete Now',
'modal_delete_title' => 'Delete Post',
'modal_delete_confirm' => 'Are you sure you want to delete this post?',
'modal_delete_warning' => 'This action cannot be undone.',
'lbl_pid' => 'PID: {1}',
'modal_deleting_sr' => 'Deleting...',
'modal_deleting' => 'Deleting post...',
'btn_cancel' => 'Cancel',
'btn_delete_post' => 'Delete Post',

// ── Flash messages / errors ──
'flash_invalid_post' => 'Sorry, but you seem to have followed an invalid address. Please be sure the specified post exists and try again.',
'flash_thread_closed' => 'You cannot edit existing posts in this thread because it has been closed by a moderator.',
'flash_already_deleted' => 'The selected post has already been deleted.',
'flash_empty_post_input' => 'Your request was rejected because no data was received. This usually happens when the total size of the uploaded files exceeds the server limit.',
'flash_nodelete' => 'The post was not deleted because the deletion was not confirmed.',
'flash_post_edited' => 'Thank you, this post has been edited.<br />You will now be returned to the thread.',
'flash_post_edited_poll' => 'Thank you, this post has been edited. <br />Because you opted to post a poll, you\'ll now be taken to the poll creation page.',
'flash_thread_moderation' => 'The administrator has specified that all editing of threads require moderation. You will now be returned to the forum index.',
'flash_post_moderation' => 'The administrator has specified that all editing of posts require moderation. You will now be returned to the thread.',
'flash_thread_deleted' => 'Thank you, the thread has been deleted.<br />You will now be returned to the forum.',
'flash_post_deleted' => 'Thank you, the post has been deleted.<br />You will now be returned to the thread.',

// ── JS: attachments (consumed by post.js via lang.*) ──
'js_add_attachment' => 'Add Attachment',
'js_update_attachment' => 'Update Attachment',
'js_update_confirm' => 'The following file(s) are already attached and will be updated / replaced with the newly selected one(s). {1} Are you sure?',
'js_attachment_missing' => 'Please select one or more files before attempting to attach.',
'js_attachment_too_many_files' => 'You can upload a maximum of {1} files at once.',
'js_attachment_too_big_upload' => 'You can upload a maximum of {1} MB at once.',
'js_attachment_max_allowed_files' => 'You can attach {1} more file(s) to this post.',
'js_error_maxattachpost' => 'Sorry but you cannot attach this file because you have reached the maximum number of attachments allowed per post of {1}',
'js_drop_files' => 'Click or drop some files here to upload...',
'js_upload_initiate' => 'Release to initiate upload...',

// ── JS: delete post (delete_post_editpost.js) ──
'js_deleting' => 'Deleting...',
'js_thread_deleted' => 'Thread has been deleted successfully',
'js_post_deleted' => 'Post has been deleted successfully',
'js_delete_unexpected' => 'Unexpected response while deleting post',
'js_delete_error' => 'Error deleting post',




);

?>