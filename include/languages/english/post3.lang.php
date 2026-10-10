<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// shared: scripts/post.js + newthread.php / newreply.php / editpost.php
$language['post3'] = array
(

// ── Attachments: shared labels / buttons (newthread, newreply, editpost) ──
'lbl_new_attachment' => 'New Attachment:',
'btn_add_attachment' => 'Add Attachment',
'btn_update_attachment' => 'Update Attachment',
'btn_remove_attachment' => 'Remove',

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

// ── JS: attachment modal and toasts (post.js) ──
'js_removeattach_confirm' => 'Are you sure you want to delete this attachment?',
'js_attach_modal_title' => 'Confirm Deletion',
'js_attach_modal_close' => 'Close',
'js_attach_modal_heading' => 'Delete Attachment?',
'js_attach_modal_warning_label' => 'Warning:',
'js_attach_modal_warning' => 'This action cannot be undone!',
'js_attach_modal_cancel' => 'Cancel',
'js_attach_modal_confirm' => 'Yes, Delete',
'js_attach_preview_alt' => 'Preview',
'js_attach_file_label' => 'file',
'js_attach_removed' => 'Attachment successfully removed',
'js_attach_remove_error' => 'Error removing attachment',
'js_files_uploaded' => 'Files uploaded successfully',
'js_files_upload_error' => 'Error uploading files',
'js_file_input_missing' => 'File input not found',

);

?>