<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['attachments'] = array (

    // ── Page titles (<title>) ──
    'ptitle_find'           => 'Attachments - Find Attachments',
    'ptitle_results'        => 'Attachments - Search Results',
    'ptitle_stats'          => 'Attachments - Attachment Statistics',
    'ptitle_comments'       => 'Attachments - Comment Attachments',
    'ptitle_orphans'        => 'Orphaned Attachments',

    // ── Nav tabs ──
    'tab_find'              => 'Find Attachments',
    'tab_find_desc'         => 'Using the attachments search system you can search for specific files users have attached to your forums.',
    'tab_orphans'           => 'Find Orphaned Attachments',
    'tab_orphans_desc'      => 'Orphaned attachments are attachments which are for some reason missing in the database or the file system.',
    'tab_stats'             => 'Attachment Statistics',
    'tab_stats_desc'        => 'Below are some general statistics for the attachments currently on your forum',
    'tab_comments'          => 'Comment Attachments',
    'tab_comments_desc'     => 'View and manage attachments uploaded to torrent comments.',

    // ── Page headers (hero) — output as-is, HTML allowed ──
    'hero_find_title'       => 'Find Attachments',
    'hero_find_sub'         => 'Search files that users have attached to forum posts and comments',
    'hero_results_title'    => 'Search Results',
    'hero_results_sub'      => '{1} attachment(s) · {2} total',
    'hero_stats_title'      => 'Attachment Statistics',
    'hero_stats_sub'        => 'Storage, traffic and the heaviest files across forum and comment attachments',
    'hero_comments_title'   => 'Comment Attachments',
    'hero_comments_sub'     => 'Files attached to torrent comments — both the <code>attachments</code> and <code>comment_files</code> storage',
    'hero_orphans_title'    => 'Orphaned Attachments',
    'hero_orphans_sub'      => 'Files and records that lost their counterpart — safe candidates for cleanup',

    // ── Section headers ──
    'sec_what'              => 'What to look for',
    'sec_results'           => 'Results',
    'sec_most_downloaded'   => 'Most downloaded',
    'sec_largest'           => 'Largest files',
    'sec_top_users'         => 'Users using the most disk space',
    'sec_db_rows'           => 'Attachment records',
    'sec_cf_rows'           => 'comment_files records',
    'sec_disk_files'        => 'Files on disk without a record',

    // ── KPI tiles ──
    'stat_attachments'      => 'Attachments',
    'stat_in_comments'      => '{1} in comments',
    'stat_disk'             => 'Disk space',
    'stat_on_server'        => 'on the server',
    'stat_bandwidth'        => 'Bandwidth',
    'stat_downloads_n'      => '{1} downloads',
    'stat_avg'              => 'Average size',
    'stat_per_file'         => 'per file',
    'stat_files'            => 'Files',
    'stat_space_used'       => 'Space used',
    'stat_downloads'        => 'Downloads',
    'stat_orph_files'       => 'Files w/o record',
    'stat_broken'           => 'Broken records',
    'stat_cf'               => 'comment_files',
    'stat_freeable'         => 'Can be freed',
    'stat_stale_n'          => '{1} stale draft(s)',

    // ── Form labels and inline text ──
    'lbl_file_name'         => 'File name',
    'lbl_username'          => 'Username',
    'lbl_mime'              => 'MIME type',
    'lbl_filename_contains' => 'File name contains',
    'lbl_filetype_contains' => 'File type contains',
    'lbl_poster'            => 'Poster username',
    'lbl_poster_is'         => 'Poster is',
    'lbl_in_forums'         => 'In forums',
    'lbl_sortby'            => 'Sort by',
    'lbl_order'             => 'Order',
    'lbl_perpage'           => 'Per page',
    'lbl_drafts_older'      => 'Drafts older than',
    'lbl_days'              => 'days',
    'lbl_found'             => 'found',
    'lbl_orphans_found'     => 'orphans found',
    'lbl_guest'             => 'Guest',
    'lbl_no_subject'        => 'No subject',
    'lbl_comment_n'         => 'Comment #{1}',
    'lbl_draft'             => 'Draft',
    'lbl_torrent_n'         => 'Torrent #{1}',
    'lbl_unknown'           => 'Unknown',
    'lbl_id'                => 'ID {1}',
    'lbl_files_n'           => '{1} file(s)',
    'lbl_storage_std'       => 'standard',
    'lbl_storage_cf'        => '.attach',

    // ── Selection toolbar — output as-is ({1} = counter markup, {2} = label, {3} = total) ──
    'tb_selected'           => 'Selected: {1} · {2}: {3}',

    // ── Table columns ──
    'col_file'              => 'File',
    'col_size'              => 'Size',
    'col_storage'           => 'Storage',
    'col_uploaded_by'       => 'Uploaded by',
    'col_torrent'           => 'Torrent',
    'col_date'              => 'Date',
    'col_attachment'        => 'Attachment',
    'col_posted_by'         => 'Posted by',
    'col_location'          => 'Location',
    'col_downloads'         => 'Downloads',
    'col_uploaded'          => 'Uploaded',
    'col_reason'            => 'Reason',

    // ── Placeholders, titles, aria-labels (plain text, escaped) ──
    'ph_contains'           => 'contains…',
    'ph_mime'               => 'e.g. image/',
    'ph_filename'           => 'e.g. screenshot',
    'ph_filetype'           => 'e.g. image/ or pdf',
    'tip_reset'             => 'Reset',
    'tip_storage_cf'        => 'comment_files table',
    'tip_storage_std'       => 'attachments table',
    'tip_user_atts'         => 'Show this user\'s attachments',
    'aria_select_all'       => 'Select all',
    'aria_select'           => 'Select',
    'aria_close'            => 'Close',
    'hint_forums'           => 'Leave empty for all forums · Ctrl-click to select several',

    // ── Buttons and quick links ──
    'btn_filter'            => 'Filter',
    'btn_new_search'        => 'New search',
    'btn_find'              => 'Find Attachments',
    'btn_delete_selected'   => 'Delete selected',
    'btn_rescan'            => 'Rescan',
    'btn_back'              => 'Back to attachments',
    'btn_cancel'            => 'Cancel',
    'btn_delete'            => 'Delete',
    'quick_comments'        => 'Comment attachments',
    'quick_orphans'         => 'Find orphans',
    'quick_stats'           => 'Statistics',

    // ── Select options ──
    'opt_sort_filename'     => 'File name',
    'opt_sort_filesize'     => 'File size',
    'opt_sort_downloads'    => 'Downloads',
    'opt_sort_date'         => 'Date uploaded',
    'opt_sort_username'     => 'Username',
    'opt_user_any'          => 'User or guest',
    'opt_user_users'        => 'Users only',
    'opt_user_guests'       => 'Guests only',
    'opt_asc'               => 'Ascending',
    'opt_desc'              => 'Descending',

    // ── Orphan reasons (plain text, escaped) ──
    'reason_post'           => 'Post deleted',
    'reason_comment'        => 'Comment deleted',
    'reason_torrent'        => 'Torrent deleted',
    'reason_file'           => 'File missing',
    'reason_stale'          => 'Stale draft',
    'reason_no_record'      => 'No DB record',

    // ── Empty states ──
    'empty_stats_title'     => 'No attachments yet',
    'empty_stats_text'      => 'Once something is uploaded, statistics will appear here.',
    'empty_comments_title'  => 'No comment attachments found',
    'empty_comments_text'   => 'Try adjusting the filters.',
    'empty_sync_title'      => 'Everything is in sync',
    'empty_sync_text'       => 'Files on disk, database records and drafts all match.',
    'empty_nothing'         => 'Nothing here yet.',
    'empty_no_broken'       => 'No broken attachment records.',
    'empty_no_broken_cf'    => 'No broken comment_files records.',
    'empty_no_orph_files'   => 'No orphaned files on disk.',

    // ── Delete confirmation modal — modal_question output as-is ({1} = counter markup) ──
    'modal_title'           => 'Delete attachments',
    'modal_question'        => 'Permanently delete {1} item(s)?',
    'modal_text'            => 'Files are removed from the disk together with their database records. This cannot be undone.',

    // ── Flash messages and errors ──
    'flash_csrf'            => 'Security check failed. Please try again.',
    'flash_none_selected'   => 'No attachments selected for deletion',
    'flash_deleted'         => 'Selected attachments have been deleted successfully',
    'flash_orphans_partial' => 'Unable to remove {1} attachment(s)<br />{2} attachment(s) removed successfully',
    'flash_orphans_failed'  => 'Unable to remove {1} attachment(s)',
    'flash_orphans_deleted' => 'The selected orphaned attachment(s) have been deleted successfully',
    'err_no_results'        => 'No attachments were found with the specified search criteria',

    // ── JS strings (passed to AGS_LANG without the js_ prefix) ──
    'js_deleting'           => 'Deleting...',
);
?>
