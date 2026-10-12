<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['doubleupload'] = array (
	// ── Page ──
	'page_title'          => 'Torrent Upload Mode Manager',
	'pane_title'          => 'Upload Mode Manager',
	'pane_subtitle'       => 'Turn double upload on or off for the whole tracker in one click',

	// ── Current mode badge ──
	'mode_double'         => 'Double upload is ON for every torrent',
	'mode_normal'         => 'All torrents use normal upload',
	'mode_mixed'          => 'Mixed — some torrents have double upload',
	'mode_empty'          => 'No torrents yet',

	// ── Stats ──
	'lbl_total'           => 'Total torrents',
	'lbl_normal'          => 'Normal upload',
	'lbl_double'          => 'Double upload ×2',
	'lbl_legend_normal'   => 'Normal',
	'lbl_legend_double'   => 'Double',
	'aria_split'          => '{1}% normal, {2}% double',

	// ── Access (output as HTML) ──
	'hint_no_access'      => 'Only <strong>Administrators</strong> and <strong>Sysops</strong> can change the upload mode — it affects the whole tracker.',

	// ── Action cards ({1} = <strong>count</strong>, output as HTML) ──
	'sec_enable'          => 'Enable double upload',
	'hint_enable'         => 'Every torrent counts upload ×2. Affects {1} torrent(s) that are still normal.',
	'btn_activate'        => 'Activate',
	'btn_already_on'      => 'Already on everywhere',
	'sec_revert'          => 'Revert to normal',
	'hint_revert'         => 'Restore the standard upload credit. Affects {1} torrent(s) with double upload.',
	'btn_revert'          => 'Revert',
	'btn_nothing'         => 'Nothing to revert',

	// ── How it works ──
	'sec_how'             => 'How it works',
	'guide_double'        => 'Double upload: users get 2× upload credit',
	'guide_normal'        => 'Normal: standard upload credit',
	'guide_all'           => 'Applies to every torrent at once',
	'guide_log'           => 'Every switch is written to the site log',
	'guide_keep'          => 'Credit already earned is not taken back',
	'guide_access'        => 'Admins & Sysops only',

	// ── Recent switches ──
	'sec_recent'          => 'Recent switches',
	'hint_no_log'         => 'No switches logged yet.',

	// ── Confirmation modals ──
	'modal_enable_title'  => 'Enable double upload?',
	'modal_revert_title'  => 'Revert to normal upload?',
	'lbl_double_now'      => 'Double now',
	'lbl_double_after'    => 'Double after',
	'note_enable'         => '{1} torrent(s) switch to ×2 upload right away, for every user.',
	'note_revert'         => 'Upload credit returns to normal. Credit already earned stays with users.',
	'btn_cancel'          => 'Cancel',
	'btn_enable_x2'       => 'Enable ×2',
	'aria_close'          => 'Close',
	'lbl_refreshing'      => 'Refreshing…',

	// ── AJAX responses ──
	'msg_double_title'    => 'Double Upload Enabled',
	'msg_normal_title'    => 'Normal Upload Restored',
	'msg_updated'         => 'Successfully updated {1} torrents',

	// ── Errors ──
	'err_db'              => 'Database error',
	'err_no_access'       => 'Insufficient privileges for this action',
	'err_csrf'            => 'Security check failed. Please refresh the page and try again.',
	'err_unexpected_ajax' => 'Unexpected error. The issue has been logged.',
	'err_system_title'    => 'System Error',
	'err_unexpected'      => 'An unexpected error occurred. The issue has been logged — please try again.',
	'err_fatal_title'     => 'Fatal Error',
	'err_fatal_contact'   => 'Please contact system administrator.',

	// ── JS ──
	'js_working'          => 'Working…',
	'js_done'             => 'Done',
	'js_unknown_error'    => 'Unknown error',
	'js_bad_response'     => 'Unexpected server response ({1})',
);
?>
