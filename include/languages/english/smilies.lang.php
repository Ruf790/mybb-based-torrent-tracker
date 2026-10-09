<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['smilies'] = array (
	// ── Page titles ──
	'title_list'            => 'Smilies',
	'title_add'             => 'Add smilie',
	'title_edit'            => 'Edit smilie',
	'title_import'          => 'Import smilies',

	// ── Security ──
	'err_security_title'    => 'Security Error',
	'err_security'          => 'Invalid security token. Please refresh the page and try again.',

	// ── Units ──
	'unit_b'                => '{1} B',
	'unit_kb'               => '{1} KB',
	'unit_mb'               => '{1} MB',
	'unit_px'               => '{1} × {2} px',

	// ── List: header & KPIs ──
	'sub_list'              => 'Drag a card by its {1} handle to reorder, click a code to copy it.',
	'btn_import'            => 'Import',
	'btn_export'            => 'Export',
	'btn_add'               => 'Add smilie',
	'kpi_smilies'           => 'Smilies',
	'kpi_files'             => 'Image files · {1}',
	'kpi_unused'            => 'Unused files',
	'kpi_missing'           => 'Missing files',

	// ── List: toolbar & cards ──
	'ph_filter'             => 'Filter by title, code or file',
	'aria_filter'           => 'Filter smilies',
	'lbl_shown'             => '{1} shown',
	'empty_title'           => 'No smilies yet',
	'empty_text'            => 'Add one by hand or import a JSON file.',
	'tip_drag'              => 'Drag to reorder',
	'tip_file_not_found'    => 'File not found: {1}',
	'tip_copy'              => 'Copy code',
	'badge_missing'         => 'File missing',
	'tip_order'             => 'Display order',
	'aria_order'            => 'Order',
	'tip_edit'              => 'Edit',
	'tip_delete'            => 'Delete',
	'no_match'              => 'Nothing matches this filter.',

	// ── List: save bar ──
	'hint_dirty'            => 'Order changed, not saved yet',
	'hint_clean'            => 'Drag cards or edit numbers, then save',
	'btn_save_order'        => 'Save order',

	// ── Form: header ──
	'tip_back'              => 'Back to smilies',
	'sub_edit'              => 'Code {1} · ID {2}',
	'sub_add'               => 'Pick an image from the smilies folder and give it a code.',

	// ── Form: details ──
	'sec_details'           => 'Details',
	'lbl_title'             => 'Title',
	'ph_title'              => 'Smile',
	'hint_title'            => 'Shown as the tooltip in the smilie picker.',
	'lbl_code'              => 'Code',
	'hint_code'             => 'What users type in a post or comment. Must be unique.',
	'lbl_file'              => 'Image file',
	'btn_browse'            => 'Browse',
	'hint_files'            => '{1} images in {2}',
	'lbl_order'             => 'Display order',
	'btn_last'              => 'Last',
	'hint_order'            => 'Lower numbers come first.',

	// ── Form: preview ──
	'sec_preview'           => 'Preview',
	'preview_no_title'      => 'No title',
	'preview_in_post'       => 'In a post:',
	'preview_sample'        => 'Nice upload, thanks',
	'meta_size'             => 'Size',
	'meta_dims'             => 'Dimensions',
	'warn_big'              => 'This image is large for a smilie and will slow down pages with many of them.',

	// ── Form: actions ──
	'btn_save_changes'      => 'Save changes',
	'btn_cancel'            => 'Cancel',
	'btn_delete'            => 'Delete',

	// ── File picker ──
	'picker_title'          => 'Choose an image',
	'aria_close'            => 'Close',
	'ph_picker_filter'      => 'Filter files',
	'picker_empty'          => 'The smilies folder has no images.',
	'tip_used_by'           => '{1} · used by {2}',
	'tip_in_use'            => 'In use',
	'picker_legend'         => 'already used by another smilie',

	// ── Validation ──
	'err_title_empty'       => 'Enter a title.',
	'err_title_long'        => 'The title can be at most {1} characters.',
	'err_code_empty'        => 'Enter the code users will type.',
	'err_code_long'         => 'The code can be at most {1} characters.',
	'err_code_taken'        => 'Another smilie already uses this code.',
	'err_file_empty'        => 'Choose an image file.',
	'err_file_bad'          => 'Use a file name only (gif, png, jpg, jpeg or webp), without folders.',
	'err_file_missing'      => 'This file is not in the smilies folder.',
	'err_order_range'       => 'Order must be between {1} and {2}.',

	// ── Flash messages ──
	'flash_not_found'       => 'Smilie not found.',
	'flash_updated'         => 'Smilie updated.',
	'flash_added'           => 'Smilie added.',
	'flash_deleted'         => 'Smilie "{1}" deleted.',
	'flash_nothing'         => 'Nothing to save.',
	'flash_order_saved'     => 'Order saved, changed: {1}.',
	'flash_order_same'      => 'Order was already up to date.',
	'flash_imported'        => 'Smilies imported: {1}.',
	'flash_imported_skip'   => 'Smilies imported: {1}. Skipped: {2} (duplicate code, missing file or invalid data).',

	// ── Import page ──
	'sub_import'            => 'Adds smilies from a JSON export. Existing smilies are not changed.',
	'import_err_nofile'     => 'Choose a JSON file to import.',
	'import_err_size'       => 'The file is larger than {1}.',
	'import_err_format'     => 'This is not a smilies export: expected a JSON list.',
	'drop_title'            => 'Choose a JSON file',
	'drop_hint'             => 'or drop it here · up to {1}',
	'rule_folder'           => 'Image files must already be in {1}',
	'rule_skip'             => 'Codes that already exist are skipped',
	'rule_format'           => 'Format: {1}',

	// ── JS ──
	'js_this_smilie'        => 'this smilie',
	'js_confirm_delete'     => 'Delete "{1}"? This cannot be undone.',
	'js_delete_title'       => 'Delete smilie?',
	'js_delete_text'        => 'Delete "{1}"? Posts that use its code will show the plain text instead.',
	'js_delete'             => 'Delete',
	'js_cancel'             => 'Cancel',
	'js_shown'              => '{1} shown',
	'js_no_title'           => 'No title',
	'js_choose_json'        => 'Choose a JSON file',
);
?>
