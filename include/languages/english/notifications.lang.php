<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// Notification center: header bell, notifications.php, notifications_ajax.php
$language['notifications'] = array
(


// ── Page ──
'page_title'            => "Notifications",
'page_subtitle'         => "Replies, mentions, ratings, requests and other events on the tracker.",
'tab_all'               => "All",
'tab_unread'            => "Unread",
'btn_mark_all'          => "Mark all as read",
'btn_delete_read'       => "Delete read",
'btn_view_all'          => "View all notifications",
'sec_notifications'     => "Notifications",
'tip_bell'              => "Notifications",
'tip_unread'            => "Unread",
'lbl_loading'           => "Loading…",
'lbl_empty'             => "You have no notifications yet.",
'lbl_empty_unread'      => "No unread notifications — you're all caught up!",
'lbl_system'            => "System",
'lbl_deleted_user'      => "Deleted user",

// ── Flash / errors ──
'flash_marked'          => "All notifications are marked as read.",
'flash_deleted'         => "Read notifications have been deleted.",
'err_login'             => "Please log in to see your notifications.",
'err_csrf'              => "Security check failed. Please refresh the page and try again.",
'err_action'            => "Unknown action.",

// ── Time ──
'ago_now'               => "just now",
'ago_min'               => "{1} min ago",
'ago_hour'              => "{1} h ago",
'ago_day'               => "{1} d ago",
'fmt_date'              => "M j, Y H:i",

// ── Notification texts ──
// {1} = who did it, {2} = number of events (the _many variants), {3}… = details
'n_unknown'             => "New notification",
'n_torrent_comment'     => "New comment on your torrent “{3}” from {1}",
'n_torrent_comment_many'=> "New comments on your torrent “{3}”: {2}, latest from {1}",
'n_comment_reply'       => "Reply to your comment on “{3}” from {1}",
'n_comment_reply_many'  => "New replies to your comment on “{3}”: {2}, latest from {1}",
'n_mention'             => "{1} mentioned you in “{3}”",
'n_chat_mention'        => "{1} mentioned you in the chat: “{3}”",
'n_chat_mention_many'   => "{1} mentioned you in the chat {2} times, latest: “{3}”",
'n_chat_reply'          => "{1} replied to you in the chat: “{3}”",
'n_chat_reply_many'     => "{1} replied to you in the chat {2} times, latest: “{3}”",

'n_chat_dm'             => "{1} wrote to you privately in the chat: “{3}”",
'n_chat_dm_many'        => "{1} wrote to you privately in the chat {2} times, latest: “{3}”",


'n_rating'              => "{1} rated your torrent “{3}”: {4}",
'n_rating_many'         => "New ratings for your torrent “{3}”: {2}, latest from {1} — {4}",
'n_reaction'            => "{1} reacted {4} to your comment on “{3}”",
'n_reaction_many'       => "Reactions to your comment on “{3}”: {2}, latest {4} from {1}",
'n_claim'               => "{1} claimed your torrent “{3}” and will keep seeding it",
'n_claim_many'          => "Your torrent “{3}” was claimed for seeding: {2}, latest by {1}",
'n_claim_settled'       => "Claim results for {3}: {4} reached the norm, {5} removed, bonus {6}",
'n_request_filled'      => "Your request “{3}” has been filled by {1}",
'n_request_vote_filled' => "A request you voted for, “{3}”, has been filled by {1}",
'n_request_comment'     => "New comment on your request “{3}” from {1}",
'n_request_comment_many' => "New comments on your request “{3}”: {2}, latest from {1}",
'n_offer_uploaded'      => "“{3}” — an offer you wanted — has been uploaded by {1}",
'n_offer_comment'       => "New comment on your offer “{3}” from {1}",
'n_offer_comment_many'  => "New comments on your offer “{3}”: {2}, latest from {1}",
'n_reseed'              => "{1} asks you to seed “{3}” again",
'n_bonus'               => "You received {3} bonus points from {1}",
'n_thread_reply'        => "{1} replied to your thread “{3}”",
'n_thread_reply_many'   => "New replies in your thread “{3}”: {2}, latest from {1}",
'n_thread_subscription'      => "{1} replied in the thread “{3}” you follow",
'n_thread_subscription_many' => "New replies in the thread “{3}” you follow: {2}, latest from {1}",
'n_new_torrent'         => "New torrent in {3}: {4}",
'n_system'              => "{3}",

// ── JS ──
'js_empty'              => "No notifications yet",
'js_load_failed'        => "Could not load notifications.",
'js_action_failed'      => "Something went wrong. Please try again.",
'js_delete_title'       => "Delete read notifications?",
'js_delete_text'        => "All read notifications will be removed. This cannot be undone.",
'js_btn_delete'         => "Delete",
'js_btn_cancel'         => "Cancel",

);
?>
