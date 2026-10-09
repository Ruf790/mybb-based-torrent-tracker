<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// Manage Torrents (admin/manage_torrents.php + admin/manage_torrents_ajax.php)
// {1}, {2}… — placeholders for ags_fmt() / t()

$language['manage_torrents'] = array
(
    // ── Page ──
    'page_title'            => 'Manage Torrents',
    'sec_title'             => 'Manage Torrents',
    'sec_subtitle'          => 'Search, filter and apply bulk actions to torrents',
    'lbl_query_ms'          => '{1} ms',

    // ── KPI tiles ──
    'kpi_torrents'          => 'Torrents',
    'kpi_total_size'        => 'Total size',
    'kpi_freeleech'         => 'Freeleech',
    'kpi_dead'              => 'Dead / hidden',

    // ── Search ──
    'lbl_name'              => 'Name',
    'ph_search'             => 'Part of the torrent name…',
    'tip_clear_search'      => 'Clear',
    'lbl_category'          => 'Category',
    'btn_apply_filters'     => 'Apply',
    'tip_reset'             => 'Reset',
    'opt_all_categories'    => 'All categories',

    // ── Filter chips ──
    'flt_all'               => 'All',
    'flt_free'              => 'Free',
    'flt_silver'            => 'Silver',
    'flt_thirty'            => '30%',
    'flt_double'            => '2× Up',
    'flt_sticky'            => 'Sticky',
    'flt_dead'              => 'Dead',

    // ── Torrent list ──
    'lbl_select_page'       => 'Select page',
    'lbl_found'             => '{1} found · page {2}',
    'col_torrent'           => 'Torrent',
    'col_status'            => 'Status',
    'col_uploader'          => 'Uploader',
    'col_category'          => 'Category',
    'col_added'             => 'Added',
    'col_select'            => 'Select',
    'tip_manage'            => 'Manage',
    'tip_select'            => 'Select',
    'lbl_deleted_user'      => 'deleted user',
    'empty_title'           => 'No torrents found',
    'empty_hint'            => 'Try other filters',
    'btn_reset_filters'     => 'Reset filters',

    // ── Bulk actions bar ──
    'lbl_selected_count'    => '{1} selected',
    'opt_choose_action'     => '— Choose action —',
    'grp_organise'          => 'Organise',
    'grp_promotions'        => 'Promotions',
    'grp_danger'            => 'Danger',
    'opt_move'              => 'Move to category',
    'opt_sticky'            => 'Toggle sticky',
    'opt_visible'           => 'Toggle visible',
    'opt_anonymous'         => 'Toggle anonymous',
    'opt_free'              => 'Toggle freeleech',
    'opt_silver'            => 'Toggle silver (50%)',
    'opt_thirty'            => 'Toggle 30% leech',
    'opt_double'            => 'Toggle 2× upload',
    'opt_banned'            => 'Toggle banned',
    'opt_delete'            => 'Delete torrents',
    'opt_choose_category'   => 'Choose category…',
    'btn_apply'             => 'Apply',
    'btn_clear'             => 'Clear',
    'lbl_page_summary'      => '{1} on page · {2}',

    // ── Modals ──
    'mdl_manage_title'      => 'Manage torrent',
    'tip_close'             => 'Close',
    'lbl_loading'           => 'Loading…',
    'lbl_loading_info'      => 'Loading torrent information…',
    'mdl_confirm_title'     => 'Are you sure?',
    'mdl_confirm_msg'       => 'This action cannot be undone.',
    'btn_cancel'            => 'Cancel',
    'btn_confirm'           => 'Confirm',

    // ── Errors ──
    'err_direct_title'      => 'Error!',
    'err_direct_access'     => 'Direct initialization of this file is not allowed.',
    'err_security'          => 'Security check failed. Please refresh the page and try again.',
    'err_no_action'         => 'Please select action type!',
    'err_no_torrents'       => 'Please select at least one torrent!',
    'err_too_many'          => 'Too many torrents selected at once (max {1}). Narrow your selection and try again.',
    'err_sysop_required'    => 'This action ("{1}") requires a higher staff level (sysop).',
    'err_unknown_action'    => 'Unknown or not-yet-implemented action: {1}',
    'err_invalid_category'  => 'Invalid category selected!',

    // ── Flash messages (toasts) ──
    'flash_done'            => 'Action completed successfully!',
    'flash_bulk_done'       => 'Action completed successfully! ({1} torrent(s) affected)',
    'flash_quick_edit'      => 'Torrent #{1} updated successfully!',

    // ── Torrent modal (manage_torrents_ajax.php): errors ──
    'err_no_permission'     => 'Error! You do not have permission to access this page.',
    'err_invalid_id'        => 'Invalid torrent ID',
    'err_not_found'         => 'Torrent not found',

    // ── Torrent modal: info ──
    'info_unknown_user'     => 'Unknown',
    'info_uncategorized'    => 'Uncategorized',
    'info_poster'           => 'Poster',
    'info_no_image'         => 'No Image',
    'info_no_poster'        => 'No Poster',
    'info_view_full'        => 'View Full Size',
    'info_torrent'          => 'Torrent Info',
    'info_id'               => 'ID: #{1}',
    'info_added'            => 'Added: {1}',
    'info_seeders'          => 'Seeders',
    'info_leechers'         => 'Leechers',
    'info_completed'        => 'Completed',
    'info_uploader'         => 'Uploader',
    'info_owner_id'         => 'Owner ID: {1}',
    'info_category'         => 'Category',
    'info_category_id'      => 'Category ID: {1}',
    'info_file'             => 'File Info',
    'info_size'             => 'Size:',
    'info_hash_short'       => 'Info Hash:',
    'info_filename'         => 'File: {1}',
    'info_status'           => 'Status',
    'info_quick_edit'       => 'Quick Edit',
    'info_torrent_name'     => 'Torrent Name',
    'btn_save'              => 'Save',
    'info_total_size'       => 'Total Size',
    'info_seed_ratio'       => 'Seed Ratio',
    'info_technical'        => 'Technical Info',
    'info_hash'             => 'Info Hash',
    'info_magnet'           => 'Magnet Link',
    'tip_copy'              => 'Copy',
    'info_description'      => 'Description',

    // ── Torrent modal: status badges ──
    'badge_active'          => 'Active',
    'badge_dead'            => 'Dead',
    'badge_free'            => 'Free',
    'badge_silver'          => 'Silver',
    'badge_sticky'          => 'Sticky',
    'badge_double'          => '2x Upload',
    'badge_banned'          => 'Banned',
    'badge_anonymous'       => 'Anonymous',

    // ── Torrent modal: action buttons ──
    'act_sticky'            => 'Sticky',
    'act_free'              => 'Free',
    'act_silver'            => 'Silver',
    'act_double'            => '2x Upload',
    'act_visible'           => 'Toggle Visible',
    'act_delete'            => 'Delete',
    'act_view_page'         => 'View Page',

    // ── JS strings (AGS_LANG, prefix js_ is stripped) ──
    'js_selected_count'     => '{1} selected',
    'js_confirm_delete'     => 'Delete torrent #{1}?',
    'js_cannot_undo'        => 'This cannot be undone.',
    'js_confirm_toggle'     => 'Toggle "{1}" for torrent #{2}?',
    'js_field_sticky'       => 'Sticky',
    'js_field_free'         => 'Freeleech',
    'js_field_silver'       => 'Silver',
    'js_field_doubleupload' => '2× upload',
    'js_field_visible'      => 'Visible',
    'js_loading'            => 'Loading…',
    'js_loading_torrent'    => 'Loading torrent #{1}…',
    'js_load_failed'        => 'Could not load torrent #{1}. Please try again.',
    'js_bulk_delete_title'  => 'Delete selected torrents?',
    'js_bulk_delete_msg'    => 'The torrent files, images, screenshots, comments and all related data are removed permanently.',
    'js_bulk_ban_title'     => 'Toggle ban on selected torrents?',
    'js_bulk_ban_msg'       => 'Banned torrents are hidden from users and cannot be downloaded.',
    'js_bulk_selected_one'  => '1 torrent selected',
    'js_bulk_selected_many' => '{1} torrents selected',
);
?>
