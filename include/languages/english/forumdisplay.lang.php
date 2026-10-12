<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// forumdisplay.php (english)
$language['forumdisplay'] = array
(

// ── Errors / access ──
    'err_invalid_forum' => 'Invalid forum',
    'err_forum_not_found' => '{1} - Forum Not Found',
    'err_contains_no_forums' => 'Sorry, but the forum you are currently viewing does not contain any child forums.',
    'err_no_permission' => 'Sorry, but you do not have permission to view threads in this forum.',
    'hint_no_permission' => 'You don\'t have permission to view threads in this forum.',

// ── Section headings / empty states ──
    'sec_subforums_in' => 'Forums in {1}',
    'msg_no_threads_title' => 'No threads available',
    'msg_no_threads_hint' => 'No threads match your selected criteria. Try adjusting your filters.',

// ── Labels ──
    'lbl_by' => 'by',
    'lbl_moderated_by' => 'Moderated By:',
    'lbl_thread' => 'Thread',
    'lbl_author' => 'Author',
    'lbl_replies' => 'Replies',
    'lbl_last_post' => 'Last Post',
    'lbl_pages' => 'Pages:',
    'lbl_moved' => 'Moved',
    'lbl_guest' => 'Guest',
    'lbl_sort_asc' => 'asc',
    'lbl_sort_desc' => 'desc',
    'lbl_users_browsing' => 'Users browsing this forum:',
    'lbl_guests_browsing' => '{1} Guest(s)',
    'lbl_invis_browsing' => '{1} Invisible User(s)',
    'lbl_clear_stored_password' => 'Clear stored forum password',

// ── Buttons / links ──
    'btn_post_thread' => 'Post Thread',
    'btn_subscribe' => 'Subscribe to this forum',
    'btn_unsubscribe' => 'Unsubscribe from this forum',
    'btn_go' => 'Go',
    'btn_inline_go' => 'Go',
    'btn_clear' => 'Clear',
    'btn_clear_selection' => 'Clear Selection.',
    'btn_cancel_return' => 'Cancel & Return',
    'btn_delete_threads' => 'Delete Threads Permanently',

// ── Placeholders / aria ──
    'ph_search_keywords' => 'Enter keywords...',
    'aria_close' => 'Close',

// ── Tooltips / hints ──
    'tip_mark_read' => 'Mark this forum read',
    'tip_goto_first_unread' => 'Go to first unread post',
    'tip_attachment_one' => 'This thread contains 1 attachment',
    'tip_attachment_many' => 'This thread contains {1} attachments',
    'tip_unapproved_one' => 'There is currently 1 unapproved post in this thread.',
    'tip_unapproved_many' => 'There are currently {1} unapproved posts in this thread.',
    'tip_rss_latest' => 'Latest Threads in {1}',

// ── Thread status icons (spaces are intentional: labels are concatenated) ──
    'tip_icon_dot' => 'Contains posts by you. ',
    'tip_icon_no_new' => 'No new posts.',
    'tip_icon_new' => 'New posts.',
    'tip_icon_hot' => ' Hot thread.',
    'tip_icon_close' => ' Closed thread.',

// ── Sort / filter options ──
    'opt_sort_subject' => 'Sort by: Subject',
    'opt_sort_lastpost' => 'Sort by: Last Post',
    'opt_sort_starter' => 'Sort by: Author',
    'opt_sort_started' => 'Sort by: Creation Time',
    'opt_sort_replies' => 'Sort by: Replies',
    'opt_sort_views' => 'Sort by: Views',
    'opt_order_asc' => 'Order: Ascending',
    'opt_order_desc' => 'Order: Descending',
    'opt_date_1day' => 'From: Today',
    'opt_date_5days' => 'From: 5 Days Ago',
    'opt_date_10days' => 'From: 10 Days Ago',
    'opt_date_20days' => 'From: 20 Days Ago',
    'opt_date_50days' => 'From: 50 Days Ago',
    'opt_date_75days' => 'From: 75 Days Ago',
    'opt_date_100days' => 'From: 100 Days Ago',
    'opt_date_lastyear' => 'From: The Last Year',
    'opt_date_beginning' => 'From: The Beginning',

// ── Inline moderation options ──
    'opt_delayed_moderation' => 'Delayed Moderation',
    'opt_group_standard' => 'Standard Tools',
    'opt_close_threads' => 'Close Threads',
    'opt_open_threads' => 'Open Threads',
    'opt_stick_threads' => 'Stick Threads',
    'opt_unstick_threads' => 'Unstick Threads',
    'opt_approve_threads' => 'Approve Threads',
    'opt_unapprove_threads' => 'Unapprove Threads',
    'opt_delete_threads' => 'Delete Threads Permanently',
    'opt_move_threads' => 'Move / Copy Threads',

// ── Select-all rows (HTML, output as is) ──
    'sel_page_html' => 'All <strong>{1}</strong> threads on this page are selected.',
    'sel_select_all_html' => 'Select all <strong>{1}</strong> threads in this forum.',
    'sel_all_html' => 'All <strong>{1}</strong> threads in this forum are selected.',

// ── Delete threads modal ──
    'modal_del_title' => 'Delete Threads Permanently',
    'modal_del_subtitle' => 'Irreversible Action — Proceed with Caution',
    'modal_del_selected' => 'Threads Selected for Deletion',
    'modal_del_warn_title' => 'Critical Warning',
    'modal_del_warn_html' => 'You are about to <strong>permanently delete</strong> selected threads. This action <strong>cannot be undone!</strong>',
    'modal_del_item_posts' => 'All posts within these threads will be permanently deleted',
    'modal_del_item_attach' => 'All attachments will be removed from the server',
    'modal_del_item_polls' => 'Polls and voting data will be erased',
    'modal_del_item_history' => 'Thread history and statistics will be lost',
    'modal_del_item_norecover' => 'No recovery or restore option is available',
    'modal_del_dataloss_title' => 'Data Loss Warning:',
    'modal_del_dataloss_text' => 'This will permanently remove content from the database.',
    'modal_del_preview_title' => 'Threads to be deleted:',
    'modal_del_confirm_strong' => 'I understand this action is permanent and cannot be undone.',
    'modal_del_confirm_text' => 'I have verified that I want to delete these threads.',

// ── JS strings (exported to AGS_LANG without the js_ prefix) ──
    'js_click_hold_edit' => '(Click and hold to edit)',
    'js_saving' => 'Saving...',
    'js_error_msg' => 'Error: {1}',
    'js_subject_updated' => 'Subject updated successfully',
    'js_error_updating' => 'Error updating subject',
    'js_whoposted_failed' => 'Failed to load. Please try again.',
    'js_select_thread' => 'Please select at least one thread.',
    'js_deleting' => 'Deleting...',
    'js_delete_threads' => 'Delete Threads Permanently',
    'js_thread_id' => 'Thread ID: {1}',
    'js_tid_badge' => 'TID: {1}',

);
?>
