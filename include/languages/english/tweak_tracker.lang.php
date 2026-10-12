<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['tweak_tracker'] = array (
	// ── Page header ──
	'page_title'    => 'Tracker cleanup',
	'page_subtitle' => 'Removes orphaned records and files, rotates backups and optimizes tables. Preview first with a dry run.',

	// ── KPI tiles ──
	'kpi_rows_dry'  => 'Records to delete',
	'kpi_rows_run'  => 'Records deleted',
	'kpi_bytes_dry' => 'Disk space to free',
	'kpi_bytes_run' => 'Disk space freed',
	'kpi_duration'  => 'Duration',
	'kpi_opt_dry'   => 'Tables to optimize',
	'kpi_opt_run'   => 'Tables optimized',

	// ── Units ──
	'unit_sec' => '{1} s',
	'unit_gb'  => '{1} GB',
	'unit_mb'  => '{1} MB',
	'unit_kb'  => '{1} KB',
	'unit_b'   => '{1} B',

	// ── Report ──
	'badge_dry'       => 'Dry run, nothing was deleted',
	'badge_run'       => 'Cleanup finished',
	'meta_run_at'     => '{1}, by {2}',
	'lbl_show_empty'  => 'Show steps with nothing to do',
	'hint_dry_counts' => 'Counts for later steps don\'t include rows that earlier steps would remove (for example, votes on requests that are about to be deleted). A real run may remove slightly more.',
	'th_action'       => 'Action',
	'th_records'      => 'Records',
	'th_size'         => 'Size',
	'th_status'       => 'Status',
	'status_ok'       => 'OK',
	'status_skip'     => 'Skipped',
	'status_error'    => 'Error',

	// ── Operations form ──
	'sec_operations' => 'Operations',
	'btn_select_all' => 'Select all',
	'hint_backup'    => 'Back up the database before a real run.',
	'btn_dry'        => 'Dry run',
	'btn_run'        => 'Run cleanup',

	// ── Operation groups ──
	'grp_db_orphans_title'   => 'Orphaned database records',
	'grp_db_orphans_desc'    => 'Rows that point to deleted users, torrents, threads, comments or posts.',
	'grp_file_records_title' => 'Orphaned attachments and screenshots',
	'grp_file_records_desc'  => 'Records whose owner is gone, plus their files. Includes unposted drafts older than 48 hours.',
	'grp_disk_scan_title'    => 'Files with no database record',
	'grp_disk_scan_desc'     => 'Files in uploads, avatars, screens, posters and torrents that nothing references. Service files and files younger than 1 hour are kept.',
	'grp_time_cleanup_title' => 'Expired records',
	'grp_time_cleanup_desc'  => 'Old sessions, search log, login attempts, 2FA tokens, captcha and mail errors.',
	'grp_backups_title'      => 'Old database backups',
	'grp_backups_desc'       => 'Backup files older than {1} days.',
	'grp_recount_title'      => 'Recount comment counters',
	'grp_recount_desc'       => 'Sets torrents.comments to the real number of comments.',
	'grp_optimize_title'     => 'Optimize tables',
	'grp_optimize_desc'      => 'Rebuilds tables with more than 10 MB of free space. Large tables are locked while this runs.',

	// ── Step labels ──
	'lbl_orphans_in'      => 'Orphans in {1}',
	'lbl_comment_files'   => 'Orphaned comment_files records',
	'lbl_draft_attach'    => 'Abandoned draft attachments (older than 48h)',
	'lbl_attach_deleted'  => 'Attachments of deleted posts and comments',
	'lbl_attach_orphaned' => 'Orphaned attachments',
	'lbl_screens_deleted' => 'Screenshots of deleted torrents',
	'lbl_scan_uploads'    => 'uploads/ files with no comment_files record',
	'lbl_scan_attach'     => 'Attachment files with no attachments record',
	'lbl_scan_avatars'    => 'Avatars of users that no longer exist',
	'lbl_scan_screens'    => 'Screenshot files with no screenshots record',
	'lbl_scan_posters'    => 'Poster images no torrent references',
	'lbl_scan_torrents'   => '.torrent files of deleted torrents',
	'lbl_time_expired'    => '{1}: expired',
	'lbl_time_older_1'    => '{1}: older than {2} day',
	'lbl_time_older_n'    => '{1}: older than {2} days',
	'lbl_backups_older'   => 'Backups older than {1} days',
	'lbl_recount'         => 'torrents.comments counter',
	'lbl_recount_fixed'   => 'torrents.comments counter (torrents fixed)',
	'lbl_optimize_table'  => 'Optimize {1}',
	'lbl_optimize_none'   => 'No table has more than {1} free',

	// ── Step notes ──
	'note_table_not_found'     => 'table not found',
	'note_column_not_found'    => 'column {1} not found',
	'note_ref_not_found'       => '{1}.{2} not found',
	'note_folder_not_found'    => 'folder not found: {1}',
	'note_backup_folder'       => 'backup folder not found',
	'note_column_missing'      => 'column not found',
	'note_reclaimable'         => 'reclaimable space (estimate)',
	'note_nothing_to_optimize' => 'nothing to optimize',
	'note_names_more'          => '{1} (+{2} more)',

	// ── Errors ──
	'err_title'     => 'Error',
	'err_token'     => 'Invalid security token. Please go back and try again.',
	'err_no_groups' => 'Select at least one operation.',
	'err_locked'    => 'Cleanup is already running. Wait for it to finish and reload the page.',

	// ── JS ──
	'js_select_all'     => 'Select all',
	'js_clear_all'      => 'Clear all',
	'js_working'        => 'Working…',
	'js_confirm_native' => 'Run cleanup now? Deleted records and files cannot be restored.',
	'js_swal_title'     => 'Run cleanup?',
	'js_swal_text'      => 'Deleted records and files cannot be restored. Make sure you have a fresh backup.',
	'js_swal_confirm'   => 'Run cleanup',
	'js_swal_cancel'    => 'Cancel',
);
?>
