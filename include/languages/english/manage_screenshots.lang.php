<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_screenshots'] = array (

    // ── Page titles (stdhead) ──
    'title_list'                => 'Screenshot Management',
    'title_add'                 => 'Add Screenshot',
    'title_edit'                => 'Edit Screenshot',

    // ── Page headers ──
    'sec_list_title'            => 'Screenshot Management',
    'sec_list_sub'              => 'Upload, replace and remove torrent screenshots',
    'sec_add_title'             => 'Add screenshots',
    'sec_add_sub'               => 'Attach one or more images to a torrent',
    'sec_edit_title'            => 'Edit screenshot #{1}',
    'sec_edit_sub'              => 'Change the torrent or replace the image file',

    // ── KPI tiles ──
    'kpi_total'                 => 'Total screenshots',
    'kpi_torrents'              => 'Torrents with screenshots',
    'kpi_day'                   => 'Uploaded in 24 hours',
    'kpi_week'                  => 'Uploaded in 7 days',

    // ── Filter ──
    'ph_filename'               => 'File name',
    'aria_search'               => 'Search by file name',
    'ph_torrent_id'             => 'Torrent ID',
    'btn_filter'                => 'Filter',
    'btn_reset'                 => 'Reset',
    'lbl_found'                 => 'Found: {1}',

    // ── Empty states ──
    'empty_filter_title'        => 'Nothing matches this filter',
    'empty_filter_text'         => 'Try another file name or torrent ID.',
    'btn_reset_filter'          => 'Reset filter',
    'empty_title'               => 'No screenshots yet',
    'empty_text'                => 'Upload the first screenshots for a torrent.',
    'notfound_title'            => 'Screenshot not found',
    'notfound_text'             => 'It may have been deleted already.',

    // ── Screenshot cards ──
    'lbl_torrent'               => 'Torrent #{1}',
    'aria_select'               => 'Select screenshot #{1}',
    'aria_view'                 => 'View screenshot #{1}',
    'alt_screenshot'            => 'Screenshot #{1}',
    'alt_preview'               => 'Preview',
    'tip_open_original'         => 'Open original',

    // ── Buttons ──
    'btn_upload_screenshots'    => 'Upload screenshots',
    'btn_edit'                  => 'Edit',
    'btn_delete'                => 'Delete',
    'btn_cancel'                => 'Cancel',
    'btn_close'                 => 'Close',
    'btn_back'                  => 'Back to list',
    'btn_select_all'            => 'Select all',
    'btn_delete_selected'       => 'Delete selected',
    'btn_choose_files'          => 'Choose files',
    'btn_clear_all'             => 'Clear all',
    'btn_upload'                => 'Upload',
    'btn_save_changes'          => 'Save changes',
    'btn_save_screenshots'      => 'Save screenshots',

    // ── Bottom bars ──
    'lbl_selected'              => 'Selected: {1}',
    'lbl_selected_files'        => 'Selected files: {1}',

    // ── Delete modals ──
    'pane_confirm_delete'       => 'Confirm deletion',
    'lbl_delete_single_q'       => 'Delete Screenshot?',
    'lbl_loading'               => 'Loading...',
    'warn_undo'                 => 'This can\'t be undone.',
    'warn_single_text'          => 'The file and its record will be removed.',
    'lbl_delete_mass_q'         => 'Delete {1} screenshots?',
    'lbl_delete_mass_text'      => 'Files and records of all selected screenshots will be removed.',
    'lbl_selected_screens'      => 'Selected screenshots:',
    'warn_mass_text'            => 'Deleted screenshots can\'t be restored.',

    // ── Upload modal ──
    'lbl_torrent_id'            => 'Torrent ID',
    'ph_torrent_example'        => 'For example, 1542',
    'lbl_drop_title'            => 'Drop screenshots here',
    'hint_drop_formats'         => 'JPG, PNG, GIF or WEBP, up to 10 MB each',
    'lbl_uploading'             => 'Uploading...',

    // ── Edit modal ──
    'pane_edit'                 => 'Edit screenshot',
    'lbl_replace_optional'      => 'Replace file (optional)',
    'lbl_saving'                => 'Saving...',

    // ── Add page ──
    'lbl_files'                 => 'Screenshot files',
    'hint_allowed_multi'        => 'Allowed: {1}. You can select several files at once.',
    'hint_preview_here'         => 'Selected images will be previewed here',

    // ── Edit page ──
    'lbl_replace_file'          => 'Replace file',
    'hint_keep_file'            => 'Leave empty to keep {1}',

    // ── Formats ──
    'fmt_date'                  => 'M d, Y H:i',

    // ── Messages: success ──
    'flash_mass_deleted'        => 'Deleted: {1}',
    'flash_mass_deleted_errors' => 'Deleted: {1}, errors: {2}',
    'flash_uploaded'            => '{1} screenshot(s) uploaded successfully',
    'flash_uploaded_partial'    => '{1} uploaded, errors: {2}',
    'flash_uploaded_with_errors'=> '{1} screenshot(s) uploaded successfully. Errors: {2}',
    'flash_updated'             => 'Screenshot updated successfully',
    'flash_deleted'             => 'Screenshot deleted',

    // ── Messages: errors ──
    'err_csrf'                  => 'Invalid security token',
    'err_none_selected'         => 'No screenshots selected',
    'err_mass_failed'           => 'Failed to delete screenshots',
    'err_torrent_required'      => 'Valid Torrent ID is required',
    'err_torrent_missing'       => 'Torrent ID {1} does not exist',
    'err_no_files'              => 'No files uploaded',
    'err_no_file'               => 'No file uploaded or upload error',
    'err_upload_failed'         => 'Upload failed',
    'err_not_found'             => 'Screenshot not found',
    'err_format_allowed'        => 'Invalid format. Allowed: {1}',
    'err_not_image'             => 'File is not a valid image',
    'err_file_upload_failed'    => 'File upload failed',
    'err_corrupt'               => 'File is corrupted or is not a valid image',
    'err_post_required'         => 'This action requires a POST request',

    // ── Messages: per-file errors ({1} = file name) ──
    'err_f_upload'              => '{1}: upload error',
    'err_f_upload_code'         => '{1}: upload error (code {2})',
    'err_f_too_large'           => '{1}: too large (max 10 MB)',
    'err_f_format'              => '{1}: invalid format',
    'err_f_format_allowed'      => '{1}: invalid format. Allowed: {2}',
    'err_f_not_image'           => '{1}: not a valid image',
    'err_f_save'                => '{1}: failed to save',
    'err_f_corrupt'             => '{1}: corrupted or invalid image data',

    // ── JS: general ──
    'js_locale'                 => 'en-US',
    'js_server_down'            => 'Server not responding.',
    'js_no_image'               => 'No image',
    'js_screenshot'             => 'Screenshot',

    // ── JS: single delete ──
    'js_confirm_deletion'       => 'Confirm Deletion',
    'js_delete_q'               => 'Delete Screenshot?',
    'js_this_screenshot'        => 'this screenshot',
    'js_quoted'                 => '"{1}"',
    'js_loading'                => 'Loading...',
    'js_kb'                     => '{1} KB',
    'js_deleting'               => 'Deleting...',
    'js_deleted_ok'             => 'Screenshot deleted successfully.',
    'js_delete_failed'          => 'Failed to delete screenshot.',
    'js_delete_error'           => 'An error occurred while deleting the screenshot.',

    // ── JS: mass delete ──
    'js_no_selection_title'     => 'No Selection',
    'js_no_selection_text'      => 'Please select at least one screenshot to delete.',
    'js_select_all'             => 'Select All',
    'js_deselect_all'           => 'Deselect All',
    'js_mass_error'             => 'An error occurred while deleting screenshots',

    // ── JS: upload modal (plural forms per Intl.PluralRules) ──
    'js_files_one'              => '{1} file',
    'js_files_few'              => '{1} files',
    'js_files_many'             => '{1} files',
    'js_files_other'            => '{1} files',
    'js_upload_failed'          => 'Upload failed.',

    // ── JS: edit modal ──
    'js_keep_file'              => 'Leave empty to keep: {1}',
    'js_torrent_required'       => 'Torrent ID is required',
    'js_update_failed'          => 'Update failed.',
);
?>
