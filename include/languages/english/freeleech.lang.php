<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['freeleech'] = array (
	// ── Page ──
	'page_title'        => 'FreeLeech Manager',
	'sec_title'         => 'FreeLeech Manager',
	'sec_subtitle'      => 'Switch every torrent on the tracker to free download, or bring them all back to normal.',

	// ── Mode badge ──
	'mode_none'         => 'No torrents',
	'mode_global'       => 'Global FreeLeech active',
	'mode_normal'       => 'Normal mode',
	'mode_mixed'        => 'Mixed mode',

	// ── KPI ──
	'lbl_total'         => 'Total torrents',
	'lbl_free'          => 'FreeLeech',
	'lbl_normal'        => 'Normal',
	'lbl_free_share'    => 'Free share',

	// ── Distribution ──
	'lbl_dist_free'     => 'Free',
	'lbl_dist_normal'   => 'Normal',
	'aria_dist'         => '{1}% of torrents are free',

	// ── Actions ──
	'pane_free'         => 'Enable FreeLeech',
	'hint_free'         => 'Downloads stop counting against ratio for every torrent. Upload is still credited.',
	'pane_normal'       => 'Restore normal',
	'hint_normal'       => 'Every torrent goes back to regular download accounting.',
	'lbl_now'           => 'Now',
	'lbl_after'         => 'After',
	'note_whole'        => 'Both actions change the whole tracker at once and are written to the staff log.',
	'btn_free'          => 'Enable FreeLeech',
	'btn_normal'        => 'Restore normal',

	// ── Flash ──
	'flash_free'        => 'FreeLeech enabled — {1} torrent(s) are now free.',
	'flash_normal'      => 'Normal mode restored — {1} torrent(s) switched back.',
	'flash_failed'      => 'The action failed. Refresh the page and try again.',

	// ── Errors ──
	'lbl_error'         => 'Error!',
	'err_direct'        => 'Direct initialization of this file is not allowed.',
	'err_no_permission' => 'You do not have permission to access this page.',
	'err_csrf'          => 'Security check failed. Please refresh the page and try again.',
	'err_db_enable'     => 'Database error while enabling FreeLeech.',
	'err_db_restore'    => 'Database error while restoring normal mode.',
	'err_db_stats'      => 'Database error while loading FreeLeech statistics.',
	'err_required_vars' => 'Required variables are not set.',

	// ── JS ──
	'js_confirm_free_title'   => 'Enable FreeLeech?',
	'js_confirm_free_btn'     => 'Enable FreeLeech',
	'js_confirm_free_plain'   => 'Enable FreeLeech for all {1} torrents?',
	'js_confirm_normal_title' => 'Restore normal mode?',
	'js_confirm_normal_btn'   => 'Restore normal',
	'js_confirm_normal_plain' => 'Restore normal mode for all {1} torrents?',
	'js_now'                  => 'Now',
	'js_after'                => 'After',
	'js_whole_tracker'        => 'This changes the whole tracker at once.',
	'js_cancel'               => 'Cancel',
	'js_working'              => 'Working…',
	'js_done_title'           => 'Done',
	'js_error_title'          => 'Action failed',
	'js_server_status'        => 'Server responded with {1}.',
	'js_network_error'        => 'Network error. Check your connection and try again.',
);
?>
