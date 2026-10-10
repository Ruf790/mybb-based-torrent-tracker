<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// functions_upload.php (errors returned to pages / toasts)
$language['functions_upload'] = array
(

// ── Common ──
'err_upload_failed' => 'The file upload failed. Please choose a valid file and try again.',
'err_not_valid_image' => 'The uploaded file is not a valid image.',
'err_image_corrupted' => 'The uploaded file is corrupted or is not a valid image.',

// ── PHP upload error codes ──
'err_php_ini_size' => 'The file upload failed: the file exceeds the upload_max_filesize limit set in php.ini. Please contact the administrator.',
'err_php_form_size' => 'The file upload failed: the file exceeds the MAX_FILE_SIZE value specified in the form.',
'err_php_partial' => 'The file upload failed: the file was only partially uploaded. Please try again.',
'err_php_no_file' => 'The file upload failed: no file was uploaded.',
'err_php_no_tmp_dir' => 'The file upload failed: the server is missing a temporary folder. Please contact the administrator.',
'err_php_cant_write' => 'The file upload failed: the file could not be written to disk. Please contact the administrator.',
'err_php_unknown' => 'The file upload failed: unrecognized PHP upload error code {1}. Please contact the administrator.',

// ── Moving the file ──
'err_nothing_to_move' => 'The file upload failed: there was no file to move to the uploads directory.',
'err_move_failed' => 'The file upload failed: the file could not be moved to the uploads directory.',
'err_file_lost' => 'The file upload failed: the file was lost after uploading. Please try again.',

// ── Avatars ──
'err_avatar_type' => 'Invalid file type. An uploaded avatar must be in GIF, JPEG, BMP, PNG or WebP format.',
'err_avatar_too_large' => 'The size of the uploaded file is too large.',

// ── Attachments ──
'err_type_not_allowed' => 'The type of file that you attached is not allowed. Please remove the attachment or choose a different type.',
'err_real_type_mismatch' => 'The uploaded file was rejected: its real content does not match an allowed file type.',
'err_filename_too_long' => 'The file name {1} exceeds the maximum file name length of 255.',
'err_file_too_large' => 'The file {1} is too large. The maximum size for that type of file is {2} kilobytes.',
'err_quota' => 'Sorry, but you cannot attach this file because you have reached your attachment quota of {1}.',
'err_already_uploaded' => 'The file {1} has already been attached to this post.',
'err_max_attachments' => 'Sorry, but you cannot attach this file because you have reached the maximum number of attachments allowed per post of {1}.',
'err_upload_empty' => 'The file {1} is empty (0 bytes) and was not uploaded.',
'err_update_failed' => 'The attachment {1} could not be updated because no attachment with this name exists yet.',

);

?>