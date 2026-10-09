<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_avatars'] = array (

    // ── Page ──
    'page_title'          => 'Manage Avatars - {1}',
    'sec_title'           => 'Manage Avatars',
    'sec_subtitle'        => 'Review uploaded avatar files, find orphaned or suspicious ones and delete them',
    'lbl_range'           => '{1}–{2} of {3}',

    // ── KPI tiles ──
    'kpi_files'           => 'Avatar files',
    'kpi_disk'            => 'Disk usage',
    'kpi_orphans'         => 'Orphaned files',
    'kpi_flagged'         => 'Flagged on this page',

    // ── File size units ──
    'unit_b'              => 'B',
    'unit_kb'             => 'KB',
    'unit_mb'             => 'MB',
    'unit_gb'             => 'GB',

    // ── Deletion result (flash) ──
    'sec_result'          => 'Deletion result',
    'flash_profiles_cleared' => '{1} profile(s) cleared',
    'flash_deleted'       => 'Deleted',
    'flash_skipped_shared'=> 'Skipped, used by several accounts',
    'flash_not_found'     => 'Not found',
    'flash_unlink_failed' => 'Could not delete, check folder permissions',

    // ── Toolbar / filters ──
    'lbl_select_all'      => 'Select all shown',
    'aria_filters'        => 'Filter avatars',
    'opt_all'             => 'All',
    'opt_owned'           => 'In use',
    'opt_orphan'          => 'Orphaned',
    'opt_flagged'         => 'Flagged',

    // ── Avatar card ──
    'aria_select'         => 'Select {1}',
    'chip_not_image'      => 'Not an image',
    'chip_suspicious'     => 'Suspicious',
    'chip_shared'         => 'Shared ×{1}',
    'chip_orphaned'       => 'Orphaned',
    'tip_zoom'            => 'Open full size',
    'tip_size'            => 'File size',
    'tip_dims'            => 'Dimensions',
    'tip_type'            => 'Type',
    'tip_scan_ok'         => 'Content scan passed',
    'lbl_clean'           => 'Clean',
    'lbl_na'              => 'N/A',
    'lbl_more'            => '+{1} more',
    'lbl_no_owner'        => 'No owner',

    // ── Empty states ──
    'empty_filter_title'  => 'Nothing matches this filter on this page',
    'empty_filter_hint'   => 'Switch to <strong>All</strong> or go to another page.', // output as-is (HTML)
    'empty_title'         => 'No avatar files in {1}',
    'empty_hint'          => 'Avatars appear here as soon as members upload them.',

    // ── Action bar ──
    'lbl_selected'        => '{1} selected',
    'btn_select_orphans'  => 'Select orphaned',
    'btn_clear'           => 'Clear',
    'btn_delete'          => 'Delete selected',

    // ── Errors ──
    'err_invalid_token'   => 'Invalid security token',

    // ── JS ──
    'js_confirm_title'    => 'Delete selected avatars?',
    'js_confirm_files'    => '{1} file(s) will be permanently deleted.',
    'js_confirm_owned'    => '{1} member(s) will lose their avatar.',
    'js_confirm_orphans'  => '{1} orphaned file(s) have no owner.',
    'js_btn_delete'       => 'Delete',
    'js_btn_cancel'       => 'Cancel',
);
?>
