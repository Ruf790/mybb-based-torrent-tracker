<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['viewunbaniprequest'] = array (
	// ── Page header ──
	'page_title'      => 'Unban Requests Manager',
	'page_subtitle'   => 'Manage user requests for IP unbanning',
	'crumb_staff'     => 'Staff Panel',
	'crumb_current'   => 'Unban Requests',
	'aria_breadcrumb' => 'breadcrumb',
	'badge_total'     => '{1} Requests',

	// ── Sections ──
	'sec_list'   => 'Unban Requests',
	'sec_legend' => 'Action Legend',

	// ── Empty state ──
	'empty_title' => 'No Unban Requests',
	'empty_text'  => 'There are currently no pending unban requests.',

	// ── Buttons ──
	'btn_print'          => 'Print',
	'btn_export'         => 'Export',
	'btn_cancel'         => 'Cancel',
	'btn_delete_request' => 'Delete Request',

	// ── Table columns ──
	'col_id'        => 'ID',
	'col_ip'        => 'IP Address',
	'col_realip'    => 'Real IP',
	'col_email'     => 'Email',
	'col_comment'   => 'Comment',
	'col_submitted' => 'Submitted',
	'col_actions'   => 'Actions',

	// ── Cell values ──
	'val_na'         => 'N/A',
	'val_no_email'   => 'Not provided',
	'val_no_comment' => 'No comment',

	// ── Tooltips / legend ──
	'tip_edit_login'     => 'Edit Failed Login Attempt',
	'tip_delete_login'   => 'Delete Failed Login Attempt',
	'tip_delete_request' => 'Delete Unban Request',

	// ── Hints (HTML, output as is) ──
	'hint_legend_note'     => '<strong>Note:</strong> If no edit button is shown, the IP address could not be found in the failed login attempts database.',
	'hint_modal_no_notify' => 'The user will <strong>not</strong> be notified about this deletion.',

	// ── Delete modal ──
	'modal_title'    => 'Delete Unban Request',
	'modal_subtitle' => 'This action cannot be undone',
	'modal_warning'  => 'You are about to permanently delete this unban request.',
	'lbl_request_id' => 'Request ID',

	// ── Flash messages (HTML, output as is) ──
	'flash_deleted' => '<strong>Done!</strong> Unban request #{1} has been deleted.',
	'redirect_in'   => 'Redirecting in {1} seconds…',

	// ── Errors (plain text, escaped) ──
	'err_title'               => 'Error',
	'err_invalid_id'          => 'Invalid request ID',
	'err_delete_failed_title' => 'Delete Failed',
	'err_delete_failed'       => 'Unable to delete unban request #{1}',
	'err_db_title'            => 'Database Error',
	'err_db'                  => 'Failed to delete request: {1}',

	// ── JS strings ──
	'js_confirm_delete_login' => 'Delete this failed login attempt?',
);
?>
