<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['fixhash'] = array (
	// ── Page ──
	'title'              => 'Fix Torrent Hashes',
	'subtitle'           => 'Compares the info_hash stored in the database with the real v1 hash of each .torrent file.',

	// ── KPI ──
	'kpi_total'          => 'Torrents in total',
	'kpi_mismatch'       => 'Need fixing on this page',
	'kpi_ok'             => 'Match on this page',
	'kpi_missing'        => 'Missing or unreadable',

	// ── Toolbar ── (hint_preview is output as-is, HTML allowed)
	'hint_preview'       => 'This page only <strong>previews</strong> differences. Nothing is written to the database until you apply fixes.',
	'lbl_auto'           => 'Auto Fix every 10s',
	'btn_apply_auto'     => 'Apply',

	// ── Table ──
	'th_torrent'         => 'Torrent',
	'th_stored'          => 'Stored hash',
	'th_real'            => 'Real hash',
	'th_status'          => 'Status',
	'empty'              => 'No torrents on this page.',
	'tip_copy'           => 'Copy hash',
	'note_file_missing'  => 'File missing',

	// ── Status badges ──
	'badge_ok'           => 'Matches',
	'badge_mismatch'     => 'Needs fix',
	'badge_missing'      => 'No file',
	'badge_nov1'         => 'v2 only',
	'badge_error'        => 'Unreadable',

	// ── Pagination ──
	'aria_pagination'    => 'Page navigation',
	'tip_first'          => 'First page',
	'tip_last'           => 'Last page',
	'btn_prev'           => 'Previous',
	'btn_next'           => 'Next',

	// ── Action bar ── (progress_page is output as-is, HTML allowed)
	'progress_page'      => 'Page <strong>{1}</strong> of <strong>{2}</strong>',
	'progress_to_fix'    => '{1} to fix here',
	'auto_running'       => 'Auto Fix running',
	'btn_stop'           => 'Stop',
	'btn_apply'          => 'Apply fixes for this page',

	// ── Errors (JSON) ──
	'err_token'          => 'Invalid security token',

	// ── JS: confirm / results ──
	'js_confirm_title'   => 'Apply fixes?',
	'js_confirm_text'    => 'The stored info_hash will be replaced for {1} torrent(s) on page {2}.',
	'js_btn_confirm'     => 'Apply fixes',
	'js_btn_cancel'      => 'Cancel',
	'js_applying'        => 'Applying…',
	'js_result_fixed'    => 'Fixed {1} torrent(s)',
	'js_result_errors'   => ', {1} error(s)',
	'js_result_skipped'  => ', {1} skipped',
	'js_applied_title'   => 'Fixes applied',
	'js_not_applied'     => 'Fixes not applied',
	'js_unknown_error'   => 'Unknown error',
	'js_req_failed'      => 'Request failed',
	'js_req_failed_text' => 'The server did not return a valid response. Try again.',

	// ── JS: copy ──
	'js_copied'          => 'Hash copied',
	'js_copy_failed'     => 'Copy failed',

	// ── JS: Auto Fix ──
	'js_auto_fix_in'     => 'Fixing in {1}s',
	'js_auto_next_in'    => 'Next page in {1}s',
	'js_auto_applying'   => 'Applying fixes…',
	'js_auto_moving'     => 'Moving on…',
	'js_auto_finished'   => 'Finished',
	'js_finished_title'  => 'Auto Fix finished',
	'js_finished_text'   => 'All {1} page(s) have been processed.',
);
?>
