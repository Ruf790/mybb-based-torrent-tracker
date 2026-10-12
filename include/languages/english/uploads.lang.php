<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['uploads'] = array
(

// ── Common ──
'err_login_required'    => "You must be logged in to upload files.",
'err_csrf'              => "Security check failed. Please refresh the page and try again.",
'err_no_file'           => "No file was uploaded.",
'err_not_image'         => "The file is corrupted or is not a valid image.",
'err_save_failed'       => "Failed to save the file.",
'err_save_failed_code'  => "Failed to save the file (code {1}).",
'err_file_too_large'    => "The file is too large (max {1}).",
'lbl_size_b'            => "{1} B",
'lbl_size_kb'           => "{1} KB",
'lbl_size_mb'           => "{1} MB",
'lbl_size_gb'           => "{1} GB",

// ── Attachment uploader widget ──
'lbl_dropzone'          => "Drag & drop files here",
'lbl_dropzone_or'       => "or",
'lbl_browse'            => "browse",
'hint_dropzone'         => "Images, video, audio, documents, archives, subtitles · video up to {1}, audio up to {2}, images up to {3}, other files up to {4}",
'tip_remove'            => "Remove",

// ── JS: attachment uploader (comment_attachments.js) ──
'js_upload_failed'      => "Upload failed",
'js_network'            => "Network error",
'js_remove'             => "Remove",
'js_delete_title'       => "Delete attachment?",
'js_delete_confirm_title' => "Confirm Deletion",
'js_warning_label'      => "Warning:",
'js_delete_warning'     => "This action cannot be undone!",
'js_btn_delete'         => "Yes, Delete",
'js_btn_cancel'         => "Cancel",
'js_aria_close'         => "Close",
'js_delete_failed'      => "Failed to delete the attachment.",
'js_size_b'             => "{1} B",
'js_size_kb'            => "{1} KB",
'js_size_mb'            => "{1} MB",

// ── Comment attachments ──
'err_invalid_id'        => "Invalid attachment ID.",
'err_not_found'         => "Attachment not found.",
'err_no_permission'     => "You don't have permission to delete this attachment.",
'err_limit'             => "Attachment limit reached ({1} files max).",
'err_upload_code'       => "Upload error (code {1}).",
'err_file_too_large_cat'=> "The file is too large: the limit for {2} is {1}.",
'err_quota'             => "You can't attach this file: you have reached your attachment quota of {1}.",
'err_mime'              => "File type not allowed: {1}",
'err_ext'               => "Extension not allowed: {1}",
'opt_cat_video'         => "videos",
'opt_cat_audio'         => "audio",
'opt_cat_image'         => "images",
'opt_cat_other'         => "other files",

// ── Editor images ──
'err_unknown_type'      => "Unknown upload type.",
'err_img_type'          => "Invalid file type: {1}",
'err_img_mismatch'      => "Image type mismatch — the file header is inconsistent.",
'err_img_ext'           => "Invalid file extension.",
'err_multi_target'      => "More than one attachment target was specified.",
'err_comment_not_found' => "Comment not found.",
'err_comment_perm'      => "You don't have permission for this comment.",
'err_torrent_not_found' => "Torrent not found.",
'err_news_not_found'    => "News post not found.",
'err_mkdir'             => "Failed to create the upload folder.",
'err_db'                => "Database write error.",

// ── PHP upload errors ──
'err_php_size'          => "The file exceeds the allowed size.",
'err_php_partial'       => "The file was only partially uploaded.",
'err_php_no_file'       => "No file was selected.",
'err_php_no_tmp'        => "Missing temporary folder.",
'err_php_cant_write'    => "Failed to write the file to disk.",
'err_php_extension'     => "The upload was stopped by a PHP extension.",
'err_php_unknown'       => "Unknown upload error.",

);
