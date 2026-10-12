<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['logmails'] = array (
	// ── Page ──
	'page_title'          => 'Email Logs',

	// ── Flash messages ──
	'flash_cleared'       => 'Log table has been completely cleared!',
	'flash_deleted'       => 'Successfully deleted {1} {2}!',
	// plural forms separated by | (English: one|other)
	'plural_entries'      => 'entry|entries',

	// ── Search ──
	'sec_search'          => 'Search Email Logs',
	'ph_search'           => 'Enter email, subject or message text...',
	'aria_search'         => 'Search email logs',
	'btn_search'          => 'Search',
	'btn_clear_all'       => 'Clear All Logs',
	'btn_help'            => 'Help',
	// output as-is (HTML allowed)
	'hint_search'         => '<strong>Search works by:</strong> sender address, recipient address, and message content.',

	// ── Statistics ──
	'lbl_total'           => 'Total Entries',
	'lbl_last_update'     => 'Last Update',

	// ── Table ──
	'sec_history'         => 'Email History',
	'lbl_showing'         => 'Showing {1}-{2} of {3} entries',
	'btn_select_all'      => 'Select All',
	'th_date'             => 'Date',
	'th_message'          => 'Message',
	'th_actions'          => 'Actions',
	'lbl_id'              => 'ID: {1}',
	'lbl_from'            => 'From: {1}',
	'lbl_to'              => 'To: {1}',
	'title_view'          => 'View',
	'title_delete'        => 'Delete',
	'btn_delete_selected' => 'Delete Selected',
	'empty_title'         => 'Email logs are empty',
	'empty_text'          => 'All system emails will be displayed here.',

	// ── Accessibility ──
	'aria_select_all'     => 'Select all entries',
	'aria_select_row'     => 'Select entry #{1}',
	'aria_close'          => 'Close',

	// ── Modal ──
	'modal_title'         => 'Email Details',
	'lbl_loading'         => 'Loading...',

	// ── JS strings ──
	'js_selected'                => 'Selected: {1}',
	'js_show_more'               => 'Show More',
	'js_collapse'                => 'Collapse',
	'js_confirm_clear'           => 'Are you sure you want to completely clear all logs?',
	'js_confirm_delete_selected' => 'Delete selected entries?',
	'js_confirm_delete_one'      => 'Delete this entry?',
	'js_sender'                  => 'Sender',
	'js_recipient'               => 'Recipient',
	'js_send_date'               => 'Send Date',
	'js_message_content'         => 'Message Content',
);
?>
