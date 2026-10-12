<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['phpeditor'] = array (
    // ── Page ──
    'page_title'            => 'PHP Editor',
    'hdr_title'             => 'PHP Editor',
    'aria_select_file'      => 'Select file',

    // ── File info bar ──
    'lbl_info_lines'        => '{1} lines',
    'lbl_read_only'         => 'Read only',

    // ── Editor controls ──
    'lbl_backup'            => 'Backup',
    'lbl_syntax_check'      => 'Syntax check',
    'btn_search'            => 'Search',
    'btn_format'            => 'Format',
    'btn_save'              => 'Save',
    'btn_reload'            => 'Reload',

    // ── Tabs ──
    'tab_backups'           => 'Backups',
    'tab_stats'             => 'Statistics',
    'tab_help'              => 'Help',

    // ── Backups pane ──
    'sec_backups_for'       => 'Backups for {1}',
    'btn_refresh_backups'   => 'Reload',
    'th_date'               => 'Date',
    'th_size'               => 'Size',
    'th_action'             => 'Action',
    'title_view'            => 'View',
    'title_restore'         => 'Restore',
    'lbl_no_backups'        => 'No backups yet',

    // ── Statistics pane ──
    'sec_file_stats'        => 'File Statistics:',
    'lbl_lines'             => 'Lines',
    'lbl_functions'         => 'Functions',
    'lbl_classes'           => 'Classes',
    'lbl_comments'          => 'Comments',
    'sec_file_size'         => 'File Size:',
    'lbl_current_size'      => 'Current:',
    'lbl_original_size'     => 'Original:',

    // ── Help pane ──
    'sec_shortcuts'         => 'Keyboard Shortcuts:',
    'hint_key_save'         => 'Save file',
    'hint_key_search'       => 'Search',
    'hint_key_replace'      => 'Find & Replace',
    'hint_key_goto'         => 'Go to line',
    'hint_key_autocomplete' => 'Autocomplete',
    'hint_key_format'       => 'Format code',
    'hint_key_comment'      => 'Toggle comment',
    'hint_key_undo'         => 'Undo',
    'hint_key_redo'         => 'Redo',
    'sec_file_types'        => 'Supported file types:',
    'sec_notes'             => 'Notes:',
    'hint_notes'            => 'PHP files are syntax-checked before save. Always backup before major edits.',

    // ── Backup preview modal ──
    'modal_backup_title'    => 'Backup preview',
    'btn_close'             => 'Close',
    'btn_load_into_editor'  => 'Load into editor',

    // ── Flash messages (AJAX) ──
    'flash_saved_backup'    => 'File saved with backup!',
    'flash_saved'           => 'File saved!',

    // ── Errors ──
    'err_invalid_token'     => 'Invalid security token. Please reload the page.',
    'err_unknown_action'    => 'Unknown action: {1}',
    'err_file_not_found'    => 'File not found: {1}',
    'err_not_writable'      => 'No write permission: {1}',
    'err_empty_content'     => 'Content cannot be empty',
    'err_php_syntax'        => 'PHP syntax error: {1}',
    'err_save_failed'       => 'Failed to save the file',
    'err_backup_failed'     => 'Failed to create a backup',
    'err_invalid_filename'  => 'Invalid file name',
    'err_backup_not_found'  => 'Backup file not found',
    'err_backup_read_failed'=> 'Failed to read the backup file',
    'err_access_denied'     => 'Access denied: {1}',
    'err_page'              => 'Error: {1}',

    // ── JS strings ──
    'js_formatted'          => 'Code formatted',
    'js_backup_loaded'      => 'Backup loaded into the editor',
    'js_content_loaded'     => 'Content loaded into the editor',
    'js_list_refreshed'     => 'List refreshed',
    'js_no_backups'         => 'No backups yet',
    'js_unbalanced_braces'  => 'Unbalanced {}',
    'js_unbalanced_parens'  => 'Unbalanced ()',
    'js_unbalanced_brackets'=> 'Unbalanced []',
    'js_syntax_ok'          => 'OK',
    'js_info_lines'         => '{1} lines',
);
?>
