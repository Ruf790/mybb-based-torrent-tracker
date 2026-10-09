<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['ratio'] = array (
	// ── Page ──
	'page_title'          => 'Ratio Manager',
	'page_sub'            => 'Set, add or subtract a member\'s uploaded / downloaded traffic',

	// ── Errors ──
	'err_csrf'            => 'Security check failed. Please refresh the page and try again.',
	'err_no_user'         => 'Please choose a user.',
	'err_no_value'        => 'Enter at least one value.',
	'err_bad_value'       => 'Use a number with an optional unit, e.g. 1073741824, 512 MB or 1.5 GB.',
	'err_user_not_found'  => 'User not found.',
	'err_db'              => 'Database update failed.',
	'err_unexpected'      => 'An unexpected error occurred.',

	// ── Result card ──
	'flash_done'          => 'Statistics of {1} updated',
	'btn_open_profile'    => 'Open profile',

	// ── Form ──
	'sec_change'          => 'Change traffic',
	'hint_units'          => 'Values accept units: 1073741824, 512 MB, 1.5 GB, 2 TB',
	'lbl_user'            => 'User',
	'ph_user'             => 'Start typing a username…',
	'lbl_operation'       => 'Operation',
	'opt_mode_set'        => 'Set to',
	'opt_mode_add'        => 'Add',
	'opt_mode_sub'        => 'Subtract',
	'lbl_uploaded'        => 'Uploaded',
	'lbl_downloaded'      => 'Downloaded',
	'ph_value'            => 'e.g. 10 GB',
	'hint_empty'          => 'Empty = leave unchanged',
	'btn_reset'           => 'Reset',
	'btn_save'            => 'Save',

	// ── Preview ──
	'sec_preview'         => 'Preview',
	'sec_preview_sub'     => 'Current values and the result',
	'preview_empty'       => 'Choose a user to see their stats',
	'lbl_up_short'        => 'Up',
	'lbl_down_short'      => 'Down',
	'lbl_ratio_now'       => 'Ratio now',
	'lbl_ratio_after'     => 'After',
	'note_logged'         => 'Every change is written to the site log with the old and new values.',

	// ── JS ──
	'js_hint_empty'       => 'Empty = leave unchanged',
	'js_hint_bad'         => 'Use e.g. 512 MB or 1.5 GB',
	'js_hint_bytes'       => '= {1} bytes',
	'js_user_meta'        => 'ID {1} · ratio {2}',
	'js_mode_set'         => 'Set to',
	'js_mode_add'         => 'Add',
	'js_mode_sub'         => 'Subtract',
	'js_lbl_uploaded'     => 'Uploaded',
	'js_lbl_downloaded'   => 'Downloaded',
	'js_lbl_ratio'        => 'Ratio',
	'js_log_note'         => 'Old and new values are written to the site log',
	'js_confirm_title'    => 'Save changes?',
	'js_confirm_simple'   => 'Update statistics for {1}?',
	'js_btn_save'         => 'Save',
	'js_btn_cancel'       => 'Cancel',
	'js_saving'           => 'Saving…',
);
?>
