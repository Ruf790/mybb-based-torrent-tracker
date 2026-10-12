<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['spam'] = array (

	// ── Header ──
	'pane_title' => 'Private Messages',
	'pane_subtitle' => 'Staff review of user conversations and spam reports',

	// ── KPI ──
	'kpi_total' => 'Total messages',
	'kpi_unread' => 'Unread',
	'kpi_last24' => 'Last 24 hours',
	'kpi_matching' => 'Matching filters',
	'kpi_shown_all' => 'Shown (no filters)',

	// ── Filters ──
	'lbl_search' => 'Search',
	'ph_search' => 'Subject or message text',
	'lbl_sender_uid' => 'Sender UID',
	'lbl_recipient_uid' => 'Recipient UID',
	'ph_any' => 'Any',
	'lbl_status' => 'Status',
	'opt_status_all' => 'All statuses',
	'opt_status_unread' => 'Unread',
	'opt_status_read' => 'Read / replied',
	'btn_filter' => 'Filter',
	'btn_reset' => 'Reset',
	'lbl_active' => 'Active:',
	'tip_remove' => 'Remove',
	'tag_search' => '“{1}”',
	'tag_from_uid' => 'From UID {1}',
	'tag_to_uid' => 'To UID {1}',

	// ── Message statuses ──
	'status_unread' => 'Unread',
	'status_read' => 'Read',
	'status_replied' => 'Replied',
	'status_forwarded' => 'Forwarded',
	'status_other' => 'Status {1}',

	// ── Table ──
	'th_sender' => 'Sender',
	'th_recipient' => 'Recipient',
	'th_subject' => 'Subject',
	'th_date' => 'Date',
	'th_status' => 'Status',
	'th_ip' => 'IP',
	'tip_filter_sender' => 'Show all from this sender',
	'tip_filter_recipient' => 'Show all to this recipient',
	'lbl_no_subject' => '(no subject)',
	'btn_view' => 'View',
	'name_system' => 'System',
	'name_deleted' => 'Deleted #{1}',
	'name_deleted_user' => 'Deleted user #{1}',

	// ── Empty state ──
	'msg_empty_title' => 'No messages found',
	'msg_empty_filters' => 'Try changing or resetting the filters.',
	'msg_empty_none' => 'There are no private messages yet.',

	// ── Modal and toast ──
	'lbl_modal_title' => 'Message',
	'aria_close' => 'Close',
	'btn_close' => 'Close',
	'lbl_toast_done' => 'Done',

	// ── Message view (spam_message.php) ──
	'err_access_denied' => 'Access denied. Staff only.',
	'err_invalid_id' => 'Invalid message ID',
	'err_not_found' => 'Message not found',
	'role_sender' => 'Sender',
	'role_recipient' => 'Recipient',
	'tip_sent_date' => 'Sent date',
	'tip_sender_ip' => 'Sender IP address',
	'tip_message_id' => 'Message ID',
	'tip_message_length' => 'Message length',
	'lbl_chars' => '{1} chars',
	'tab_rendered' => 'Rendered',
	'tab_raw' => 'Raw',
	'btn_copy' => 'Copy',
	'tip_copy' => 'Copy raw text to clipboard',
	'tip_download' => 'Download as .txt',

	// ── JavaScript strings (spam.js) ──
	'js_no_subject' => '(no subject)',
	'js_loading' => 'Loading message…',
	'js_load_failed' => 'Failed to load message. {1}',
	'js_copied' => 'Copied to clipboard',
	'js_saved' => 'Saved {1}',
);
?>
