<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['csseditor'] = array (
	// ── Page ──
	'page_title'             => 'CSS Editor',
	'sec_editor'             => 'Advanced CSS Editor',
	'lbl_file_select'        => 'Stylesheet file',
	'lbl_preview_toggle'     => 'Preview',

	// ── Snippets ──
	'opt_snip_reset'         => 'Reset',
	'opt_snip_flex_center'   => 'Flex Center',
	'opt_snip_grid_layout'   => 'Grid Layout',
	'opt_snip_media_query'   => 'Media Query',
	'opt_snip_animation'     => 'Animation',
	'opt_snip_transition'    => 'Transition',
	'opt_snip_transform'     => 'Transform',
	'opt_snip_shadow'        => 'Shadow',

	// ── Toolbar ──
	'lbl_backup'             => 'Backup',
	'lbl_minify'             => 'Minify',
	'btn_search'             => 'Search',
	'btn_format'             => 'Format',
	'btn_save'               => 'Save',
	'btn_reload'             => 'Reload',

	// ── Preview ──
	'sec_preview'            => 'Preview Styles',
	'sec_preview_basic'      => 'Basic Elements',
	'sec_preview_typography' => 'Typography',
	'lbl_demo_primary'       => 'Primary',
	'lbl_demo_secondary'     => 'Secondary',
	'lbl_demo_success'       => 'Success',
	'lbl_demo_warning'       => 'Warning message example',
	'lbl_demo_card_title'    => 'Card Example',
	'lbl_demo_card_text'     => 'Sample text inside card.',
	'lbl_demo_card_btn'      => 'Card Button',
	'lbl_demo_h1'            => 'Heading h1',
	'lbl_demo_h2'            => 'Heading h2',
	// Output as-is (HTML allowed)
	'lbl_demo_paragraph'     => 'Regular paragraph with <strong>bold</strong> and <em>italic</em>.',
	'lbl_demo_list_1'        => 'List item 1',
	'lbl_demo_list_2'        => 'List item 2',
	'btn_refresh_preview'    => 'Refresh Preview',

	// ── Tabs ──
	'pane_backups'           => 'Backups',
	'pane_stats'             => 'Statistics',
	'pane_help'              => 'Help',

	// ── Backups ──
	'sec_backups'            => 'Backups:',
	'btn_refresh_backups'    => 'Reload',
	'th_date'                => 'Date',
	'th_size'                => 'Size',
	'th_action'              => 'Action',
	'tip_view'               => 'View',
	'tip_restore'            => 'Restore',
	'lbl_no_backups'         => 'No backups yet',

	// ── Statistics ──
	'sec_css_stats'          => 'CSS Statistics:',
	'lbl_selectors'          => 'Selectors',
	'lbl_properties'         => 'Properties',
	'lbl_media_queries'      => 'Media Queries',
	'sec_file_size'          => 'File Size:',
	'lbl_minified'           => 'Minified:',
	'lbl_original'           => 'Original:',

	// ── Help ──
	'sec_shortcuts'          => 'Keyboard Shortcuts:',
	'hint_kb_search'         => 'Search',
	'hint_kb_replace'        => 'Replace',
	'hint_kb_save'           => 'Save',
	'hint_kb_autocomplete'   => 'Autocomplete',
	'hint_kb_format'         => 'Format',
	'hint_kb_comment'        => 'Toggle comment',
	'sec_templates'          => 'Templates:',
	'hint_templates'         => 'Use the buttons above to insert common CSS patterns.',

	// ── Backup modal ──
	'sec_backup_modal'       => 'Backup preview',
	'btn_close'              => 'Close',
	'btn_load_into_editor'   => 'Load into editor',

	// ── Flash ──
	'flash_saved_backup'     => 'File saved with a backup!',
	'flash_saved'            => 'File saved!',

	// ── Errors ──
	'err_page'               => 'Error: {1}',
	'err_invalid_token'      => 'Invalid security token. Please reload the page.',
	'err_unknown_action'     => 'Unknown action: {1}',
	'err_file_not_found'     => 'File not found: {1}',
	'err_not_writable'       => 'No write permission: {1}',
	'err_empty_content'      => 'Content cannot be empty',
	'err_save_failed'        => 'Failed to save the file',
	'err_invalid_filename'   => 'Invalid file name',
	'err_access_denied'      => 'Access denied',
	'err_access_denied_file' => 'Access denied: {1}',
	'err_backup_not_found'   => 'Backup file not found',
	'err_backup_read_failed' => 'Failed to read the backup file',
	'err_backup_create_failed' => 'Failed to create a backup',

	// ── JS: toasts ──
	'js_formatted'           => 'Code formatted',
	'js_backup_loaded'       => 'Backup loaded into the editor',
	'js_content_loaded'      => 'Content loaded into the editor',
	'js_list_updated'        => 'List updated',
	'js_editor_empty'        => 'The editor is empty',
	'js_styles_applied'      => 'Styles applied',
	'js_snippet_added'       => 'Snippet added',
	'js_no_backups'          => 'No backups yet',
	'js_view'                => 'View',
	'js_restore'             => 'Restore',

	// ── JS: CodeMirror search dialog ──
	'js_cm_search'           => 'Search:',
	'js_cm_regex_hint'       => '(Use /re/ syntax for regexp search)',
	'js_cm_replace'          => 'Replace:',
	'js_cm_replace_all'      => 'Replace all:',
	'js_cm_with'             => 'With:',
	'js_cm_replace_confirm'  => 'Replace?',
	'js_cm_yes'              => 'Yes',
	'js_cm_no'               => 'No',
	'js_cm_all'              => 'All',
	'js_cm_stop'             => 'Stop',
);
?>
