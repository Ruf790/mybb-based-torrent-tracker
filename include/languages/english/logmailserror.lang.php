<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['logmailserror'] = array (
	// ── Page ──
	'page_title'              => 'System Email Error Logs',

	// ── Flash messages ──
	'flash_cleared'           => 'Error log table has been completely cleared!',
	'flash_deleted'           => 'Successfully deleted {1} {2}!',
	'plural_entry_1'          => 'error entry',
	'plural_entry_2'          => 'error entries',
	'plural_entry_5'          => 'error entries',

	// ── Search ──
	'sec_search'              => 'Search Email Error Logs',
	'ph_search'               => 'Search by email, error message, or content...',
	'btn_search'              => 'Search Errors',
	'btn_clear_all'           => 'Clear All Error Logs',
	'confirm_clear_all'       => 'Are you sure you want to clear all error logs?',
	'btn_help'                => 'Help',
	'hint_search_html'        => '<strong>Search capabilities:</strong> Email addresses, error messages, and email content. Shows only failed email delivery attempts.',

	// ── Statistics ──
	'kpi_total_errors'        => 'Total Errors',
	'kpi_failed_emails'       => 'Failed Emails',
	'kpi_last_check'          => 'Last Check',
	'fmt_last_check'          => 'm/d/Y H:i:s',

	// ── Pagination ──
	'lbl_showing'             => 'Showing {1}-{2} of {3} errors',

	// ── Table ──
	'sec_failures'            => 'Email Delivery Failures',
	'btn_select_all'          => 'Select All',
	'th_datetime'             => 'Date & Time',
	'th_details'              => 'Error Details',
	'th_actions'              => 'Actions',
	'empty_title'             => 'No Email Errors Found',
	'empty_text'              => 'Great! All emails are being delivered successfully.',
	'lbl_from'                => 'From:',
	'lbl_to'                  => 'To:',
	'lbl_error_message'       => 'Error Message',
	'lbl_email_content'       => 'Email Content',
	'title_view'              => 'View Details',
	'title_delete'            => 'Delete Entry',
	'title_retry'             => 'Retry Sending',

	// ── Severity ──
	'sev_critical'            => 'CRITICAL',
	'sev_high'                => 'HIGH',
	'sev_medium'              => 'MEDIUM',
	'sev_low'                 => 'LOW',

	// ── Footer actions ──
	'btn_delete_selected'     => 'Delete Selected',
	'confirm_delete_selected' => 'Delete selected error entries?',

	// ── Modal ──
	'modal_title'             => 'Email Error Details',
	'modal_loading'           => 'Loading error details...',

	// ── JS strings ──
	'js_selected_count'       => 'Selected: {1}',
	'js_critical_selected'    => '⚠ {1} critical',
	'js_show_full'            => 'Show Full Email',
	'js_collapse'             => 'Collapse Email',
	'js_show_critical'        => 'Show Critical Only',
	'js_show_all'             => 'Show All',
	'js_confirm_delete_one'   => 'Delete this error entry?',
	'js_confirm_retry'        => 'Attempt to resend this email?',
	'js_resend_ok'            => 'Email resent successfully!',
	'js_resend_failed'        => 'Failed to resend: {1}',
	'js_error'                => 'Error: {1}',
	'js_modal_sender'         => 'Sender',
	'js_modal_recipient'      => 'Recipient',
	'js_modal_time'           => 'Error Time',
	'js_modal_occurred'       => 'Error occurred at:',
	'js_modal_error'          => 'Error Message',
	'js_modal_content'        => 'Original Email Content',
	'js_tips_title'           => 'Troubleshooting tips:',
	'js_tip_1'                => 'Check email server configuration',
	'js_tip_2'                => 'Verify recipient email address',
	'js_tip_3'                => 'Check SMTP authentication settings',
	'js_tip_4'                => 'Review email content for invalid characters',
);
?>
