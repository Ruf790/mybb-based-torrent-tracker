<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['staffbox'] = array (
	// ── Page titles ──
	'page_title_inbox'       => 'Staff messages',
	'page_title_reply'       => 'Reply to staff message',

	// ── Section headers ──
	'sec_inbox_title'        => 'Staff messages',
	'sec_inbox_sub'          => 'Questions and reports members sent to the staff team',
	'sec_reply_title'        => 'Reply to staff message',
	'sec_view_sub'           => 'Message #{1}',
	'sec_original'           => 'Original message',
	'sec_preview'            => 'Preview',
	'sec_your_reply'         => 'Your reply',
	'sec_message'            => 'Message',
	'sec_answer'             => 'Answer',

	// ── Labels ──
	'lbl_subject'            => 'Subject',
	'lbl_message'            => 'Message',
	'lbl_from'               => 'From',
	'lbl_sent'               => 'Sent',
	'lbl_status'             => 'Status',
	'lbl_answered_by'        => 'Answered by',
	'lbl_reply_to'           => 'To {1}',
	'lbl_by_list'            => 'by {1}',
	'lbl_answer_author'      => 'by {1}',
	'lbl_selected'           => '{1} selected',
	'lbl_no_subject'         => 'No subject',
	'lbl_system'             => 'System',
	'lbl_deleted_user'       => '[Deleted]',
	'lbl_status_answered'    => 'Answered',
	'lbl_status_waiting'     => 'Waiting',
	'lbl_note_no_reply'      => 'Marked as answered without a reply sent from this page.',

	// ── KPI tiles ──
	'kpi_total'              => 'Total messages',
	'kpi_open'               => 'Waiting for answer',
	'kpi_done'               => 'Answered',
	'kpi_day'                => 'Last 24 hours',

	// ── Filter tabs ──
	'tab_all'                => 'All',
	'tab_open'               => 'Waiting',
	'tab_done'               => 'Answered',

	// ── Table headers ──
	'th_message'             => 'Message',
	'th_from'                => 'From',
	'th_sent'                => 'Sent',
	'th_status'              => 'Status',

	// ── Accessibility (aria-label) ──
	'aria_tabs'              => 'Filter messages',
	'aria_select_all'        => 'Select all',
	'aria_select_msg'        => 'Select message #{1}',
	'aria_close'             => 'Close',

	// ── Buttons ──
	'btn_inbox'              => 'Inbox',
	'btn_reply'              => 'Reply',
	'btn_mark_answered'      => 'Mark answered',
	'btn_delete'             => 'Delete',
	'btn_back'               => 'Back',
	'btn_preview'            => 'Preview',
	'btn_send'               => 'Send reply',

	// ── Tooltips ──
	'tip_view'               => 'View',
	'tip_reply'              => 'Reply',
	'tip_delete'             => 'Delete',

	// ── Confirmations (data-sb-confirm*, {n} is replaced by JS) ──
	'confirm_delete_one'     => 'Delete this message?',
	'confirm_delete_view'    => 'The message and its saved answer will be removed.',
	'confirm_irreversible'   => 'This cannot be undone.',
	'confirm_delete_bulk'    => 'Delete {n} selected message(s)?',
	'confirm_btn_delete'     => 'Delete',

	// ── Empty states ──
	'empty_open_title'       => 'All caught up',
	'empty_open_text'        => 'No message is waiting for an answer.',
	'empty_done_title'       => 'Nothing answered yet',
	'empty_done_text'        => 'Answered messages will appear here.',
	'empty_all_title'        => 'Inbox is empty',
	'empty_all_text'         => 'Messages members send to the staff will appear here.',

	// ── Flash messages ──
	'flash_deleted_one'      => 'Message deleted.',
	'flash_deleted_many'     => '{1} messages deleted.',
	'flash_answered_none'    => 'Already marked as answered.',
	'flash_answered_one'     => 'Marked as answered.',
	'flash_answered_many'    => '{1} messages marked as answered.',
	'flash_sent'             => 'Reply sent and message marked as answered.',
	'flash_none'             => 'Select at least one message first.',
	'flash_csrf'             => 'Security token expired. Reload the page and try again.',

	// ── Errors ──
	'err_title'              => 'Error',
	'err_not_found'          => 'Message not found.',
	'err_no_user'            => 'No user with that ID.',
	'err_csrf_reply'         => 'Security token expired. Copy your text, reload the page and try again.',
	'err_empty_reply'        => 'Write a reply before sending.',

	// ── PM defaults ──
	'pm_default_subject'     => 'Re: Staff message',

	// ── JS strings ──
	'js_confirm'             => 'Confirm',
	'js_cancel'              => 'Cancel',
);
?>
