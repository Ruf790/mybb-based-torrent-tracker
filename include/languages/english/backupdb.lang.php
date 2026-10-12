<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['backupdb'] = array (

    // ── Page titles ──
    'title_main' => 'Database Backups',
    'title_new' => 'New database backup',
    'title_delete' => 'Delete backup',
    'hero_sub_list' => 'Create, download and remove SQL backups of the tracker database',
    'hero_sub_new' => 'Choose tables and options, then create a backup',

    // ── Buttons ──
    'btn_new_backup' => 'New backup',
    'btn_back_to_list' => 'Back to list',
    'btn_create' => 'Create backup',
    'btn_create_first' => 'Create the first backup',
    'btn_cancel' => 'Cancel',
    'btn_delete' => 'Delete',
    'btn_confirm_delete' => 'Yes, delete',
    'btn_download' => 'Download',
    'btn_all' => 'All',
    'btn_none' => 'None',
    'btn_go_back' => 'Go Back',

    // ── Errors ──
    'err_title' => 'Error',
    'err_unknown' => 'Unknown error',
    'err_security_token' => 'Invalid security token',

    // ── Flash messages ──
    'flash_no_file_dl' => 'You did not specify a database backup to download',
    'flash_link_expired' => 'Invalid or expired link. Please try downloading again from this page.',
    'flash_file_invalid' => 'The back up file you selected is either invalid or does not exist',
    'flash_not_exist' => 'The specified backup does not exist',
    'flash_deleted' => 'The backup has been deleted successfully',
    'flash_not_deleted' => 'The backup has not been deleted',
    'flash_no_tables' => 'You did not select any tables to backup',
    'flash_no_zlib' => 'The zlib library for PHP is not enabled - you cannot create GZIP compressed backups',
    'flash_created_title' => 'Backup created successfully',
    'flash_created_path' => 'The backup file has been saved to:',

    // ── Alerts (alert_*_html keys are printed as-is: HTML allowed) ──
    'alert_readonly_dl_html' => 'The <code>admin/backup</code> folder is not writable — backups can only be <strong>downloaded</strong>, not saved on the server.',
    'alert_readonly_list_html' => 'The <code>admin/backup</code> folder is not writable — new backups can only be downloaded.',
    'alert_no_zlib' => 'PHP zlib is not enabled — GZIP compression is unavailable.',

    // ── Delete confirmation / modal ──
    'confirm_title' => 'Delete this backup?',
    'modal_title' => 'Delete backup?',
    'hint_irreversible' => 'The file is removed from the server. This can\'t be undone.',
    'aria_close' => 'Close',

    // ── Tables pane ──
    'sec_tables' => 'Tables',
    'lbl_selected' => '{1} of {2} selected · {3}',
    'ph_filter' => 'Filter tables…',
    'lbl_rows' => '{1} rows',

    // ── Options ──
    'grp_filetype' => 'File type',
    'grp_save' => 'Save to',
    'grp_contents' => 'Contents',
    'grp_maint' => 'Maintenance',
    'opt_gzip_desc' => 'Compressed · much smaller',
    'opt_plain' => 'Plain SQL',
    'opt_plain_desc' => 'Readable text file',
    'opt_method_download' => 'Download',
    'opt_method_download_desc' => 'Straight to your computer',
    'opt_method_server' => 'Server',
    'opt_method_server_desc' => 'admin/backup folder',
    'opt_contents_full' => 'Full',
    'opt_contents_full_desc' => 'Structure + data',
    'opt_contents_structure' => 'Structure',
    'opt_contents_structure_desc' => 'Tables only',
    'opt_contents_data' => 'Data',
    'opt_contents_data_desc' => 'Rows only',
    'opt_optimize' => 'Analyze & optimize',
    'opt_optimize_desc' => 'Runs OPTIMIZE / ANALYZE on each selected table first',
    'hint_savebar' => 'Large databases can take a while — keep the page open',

    // ── KPI tiles ──
    'kpi_backups' => 'Backups',
    'kpi_space' => 'Space used',
    'kpi_latest' => 'Latest',
    'kpi_folder' => 'Folder',
    'kpi_never' => 'never',
    'kpi_writable' => 'Writable',
    'kpi_readonly' => 'Read-only',

    // ── Saved backups list ──
    'sec_saved' => 'Saved backups',
    'th_file' => 'File',
    'th_size' => 'Size',
    'th_created' => 'Created',
    'tag_latest' => 'latest',
    'empty_title' => 'No backups yet',
    'empty_hint' => 'Backups saved to the server show up here.',
    'tip_download' => 'Download',
    'tip_delete' => 'Delete',

    // ── JS strings (js_* → AGS_LANG, key without prefix) ──
    'js_working' => 'Working…',
    'js_create_backup' => 'Create backup',

);

?>
