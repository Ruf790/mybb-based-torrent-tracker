<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['attachment_types'] = array (

	// ── Page titles (stdhead) ──
	'page_title' => 'Attachment Types',
	'page_title_add' => 'Attachment Types - Add New',
	'page_title_edit' => 'Attachment Types - Edit',

	// ── Headings / subtitles ──
	'head_add' => 'Add Attachment Type',
	'head_edit' => 'Edit Attachment Type',
	'sub_form' => 'File type that users are allowed to attach',
	'sub_list' => 'File extensions users may attach to posts and comments',

	// ── Buttons ──
	'btn_back' => 'Back to list',
	'btn_add' => 'Add Type',
	'btn_create' => 'Create Type',
	'btn_save' => 'Save Changes',
	'btn_cancel' => 'Cancel',
	'btn_delete' => 'Delete',

	// ── Section headings ──
	'sec_file_type' => 'File type',
	'sec_icon' => 'Icon',
	'sec_behaviour' => 'Behaviour',
	'sec_groups' => 'Available to groups',
	'sec_forums' => 'Available in forums',
	'sec_all_types' => 'All types',

	// ── Field labels ──
	'lbl_name' => 'Name',
	'lbl_extension' => 'Extension',
	'lbl_mime' => 'MIME type',
	'lbl_maxsize' => 'Maximum size',
	'lbl_icon_html' => 'Font Awesome HTML',
	'lbl_enabled' => 'Enabled',
	'lbl_force_download' => 'Force download',
	'lbl_avatar_file' => 'Avatar file',
	'lbl_upload_max' => 'Upload max',
	'lbl_post_max' => 'Post max',

	// ── Hints (hint_mime and hint_icon contain HTML — printed as is) ──
	'hint_name' => 'Shown to users, e.g. “PDF Document”',
	'hint_extension' => 'Without the leading dot',
	'hint_mime' => 'What the server sends, e.g. <code>application/pdf</code>',
	'hint_maxsize' => '0 = no limit of its own (the PHP limits still apply)',
	'hint_icon' => 'For example <code>&lt;i class="fas fa-file-pdf" style="color:#e74c3c;"&gt;&lt;/i&gt;</code>',
	'hint_enabled' => 'Users can upload this type',
	'hint_force_download' => 'Always download instead of opening in the browser',
	'hint_avatar_file' => 'Allow this type for avatars',
	'hint_multi_select' => 'Hold Ctrl (⌘ on Mac) to select several',

	// ── Placeholders ──
	'ph_name' => 'e.g. PDF Document',
	'ph_extension' => 'pdf',
	'ph_mime' => 'application/pdf',
	'ph_maxsize' => '0 = unlimited',
	'ph_filter' => 'Filter by extension, name or MIME…',

	// ── Sizes / units ──
	'unit_kb' => 'KB',
	'unit_mb' => 'MB',
	'size_unlimited' => 'Unlimited',
	'pill_php_limit' => 'PHP {1}: {2}',

	// ── Options (all / selected / none) ──
	'opt_all_groups' => 'All groups',
	'opt_all_forums' => 'All forums',
	'opt_selected' => 'Selected',
	'opt_none' => 'None',

	// ── Icon presets ──
	'preset_pdf' => 'PDF',
	'preset_image' => 'Image',
	'preset_archive' => 'Archive',
	'preset_word' => 'Word',
	'preset_excel' => 'Excel',
	'preset_ppt' => 'PowerPoint',
	'preset_video' => 'Video',
	'preset_audio' => 'Audio',
	'preset_text' => 'Text',
	'preset_code' => 'Code',

	// ── Stat cards ──
	'stat_total' => 'Total types',
	'stat_enabled' => 'Enabled',
	'stat_disabled' => 'Disabled',
	'stat_avg_limit' => 'Avg. limit',

	// ── Table ──
	'th_type' => 'Type',
	'th_mime' => 'MIME',
	'th_status' => 'Status',
	'th_max_size' => 'Max size',
	'th_access' => 'Access',
	'th_flags' => 'Flags',
	'th_actions' => 'Actions',
	'status_enabled' => 'Enabled',
	'status_disabled' => 'Disabled',
	'scope_all_groups' => 'All groups',
	'scope_none_groups' => 'No groups',
	'scope_n_groups' => '{1} groups',
	'scope_all_forums' => 'All forums',
	'scope_none_forums' => 'No forums',
	'scope_n_forums' => '{1} forums',
	'empty_title' => 'No attachment types yet',
	'empty_text' => 'Add the first file type users are allowed to attach.',
	'no_match' => 'No matches on this page',

	// ── Tooltips (title) ──
	'tip_force_download' => 'Force download',
	'tip_opens_browser' => 'Opens in browser',
	'tip_avatar_yes' => 'Allowed for avatars',
	'tip_avatar_no' => 'Not for avatars',
	'tip_edit' => 'Edit',
	'tip_enable' => 'Enable',
	'tip_disable' => 'Disable',
	'tip_delete' => 'Delete',

	// ── Delete modal ──
	'modal_delete_title' => 'Delete attachment type',
	'modal_delete_q' => 'Delete {1}?',
	'modal_delete_text' => 'Users will no longer be able to upload this file type. Files already attached stay in place.',
	'aria_close' => 'Close',

	// ── Errors ──
	'err_no_permission' => 'Error! You do not have permission to access this page.',
	'err_token' => 'Invalid security token',
	'err_no_extension' => 'You did not enter a file extension for this attachment type',
	'err_no_mime' => 'You did not enter a MIME type for this attachment type',

	// ── Flash messages ──
	'flash_created' => 'The attachment type has been created.',
	'flash_updated' => 'The attachment type has been updated.',
	'flash_deleted' => 'The attachment type has been deleted.',
	'flash_invalid' => 'Invalid attachment type.',
	'flash_enabled' => 'The attachment type has been enabled.',
	'flash_disabled' => 'The attachment type has been disabled.',

);
?>
