<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['delundeletedtorrents'] = array (
    // ── Page ──
    'page_title'        => 'Delete Undeleted Torrent Files',
    'sec_title'         => 'Delete undeleted torrent files',
    'hint_subtitle'     => 'Finds .torrent files in {1} that no longer have a database record and removes them together with covers, screenshots, comments and attachments.',

    // ── Errors ──
    'err_no_permission' => 'Error! You do not have permission to access this page.',
    'err_db_title'      => 'Database error.',
    'err_db_text'       => 'Could not fetch the existing torrent IDs. Aborting for safety - proceeding would have treated every torrent on the site as "orphaned". Please check the database connection and try again.',
    'flash_csrf'        => 'Security check failed. The page may have expired - reload it and try again.',

    // ── KPI ──
    'lbl_kpi_db'        => 'Torrents in database',
    'lbl_kpi_disk'      => '.torrent files on disk',
    'lbl_kpi_orphans'   => 'Orphaned files',
    'lbl_kpi_last'      => 'Deleted in last batch',

    // ── Last batch result ──
    'flash_deleted'     => 'Deleted {1} orphaned torrent(s)',
    'hint_done_shots'   => 'Also removed {1} screenshot(s).',
    'hint_done_log'     => 'Full list is saved in the staff log.',
    'lbl_close'         => 'Close',

    // ── Orphan list ──
    'sec_orphans'       => '{1} orphaned .torrent file(s)',
    'hint_batches'      => 'Deleted in batches of {1}. Highlighted files go in the next batch.',
    'hint_one_batch'    => 'All of them will be deleted in one batch.',
    'ph_filter'         => 'Find ID',
    'lbl_filter'        => 'Find torrent ID',
    'lbl_shown'         => '{1} shown',

    // ── Action bar ──
    'lbl_next_batch'    => 'Next batch: {1} of {2}',
    'btn_delete_next'   => 'Delete next {1}',
    'btn_delete_all'    => 'Delete all {1}',

    // ── Empty state ──
    'sec_clean'         => 'All clean',
    'hint_clean'        => 'Every .torrent file on disk has a matching torrent in the database.',

    // ── JS ──
    'js_deleting'       => 'Deleting…',
    'js_confirm_title'  => 'Delete orphaned files?',
    'js_confirm_text'   => 'Permanently delete {1} orphaned torrent(s) with their covers, screenshots, comments and attachments? This cannot be undone.',
    'js_confirm_btn'    => 'Delete {1}',
    'js_cancel'         => 'Cancel',
);
?>
