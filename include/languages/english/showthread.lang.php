<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// showthread.php
$language['showthread'] = array 
(

'mergeposts'=>'Merge Posts',
'invalid_post'	=>'The specified post does not exist.',

'moderation_user_posts' => "Please note that new posts you make must be approved by a moderator before becoming visible.",

'delete_poll' => "Delete Poll",
'close_thread' => "Close Thread",
'stick_thread' => "Stick Thread",

'standard_mod_tools' => "Standard Tools",

'error_invalidthread22' => "The specified thread does not exist.",

'author' => "Author",
'message' => "Message",
'threaded' => "Threaded Mode",
'linear' => "Linear Mode",
'next_oldest' => "Next Oldest",
'next_newest' => "Next Newest",
'view_printable' => "View a Printable Version",
'send_thread' => "Send this Thread to a Friend",
'subscribe_thread' => "Subscribe to this thread",
'unsubscribe_thread' => "Unsubscribe from this thread",
'add_poll_to_thread' => "Add Poll to this thread",
'moderation_options' => "Moderation Options:",
'delayed_moderation' => "Delayed Moderation",
'thread_notes' => "Edit / View Thread Notes",
'open_close_thread' => "Open / Close Thread",
'approve_thread' => "Approve Thread",
'unapprove_thread' => "Unapprove Thread",
'soft_delete_thread' => "Soft Delete Thread",
'restore_thread' => "Restore Thread",
'delete_thread' => "Delete Thread Permanently",
'delete_posts' => "Delete Selective Posts",
'move_thread' => "Move / Copy Thread",
'stick_unstick_thread' => "Stick / Unstick Thread",
'split_thread' => "Split Thread",
'merge_threads' => "Merge Threads",
'remove_redirects' => "Remove Redirects",
'remove_subscriptions' => "Remove All Subscriptions",
'poll' => "Poll:",
'show_results' => "Show Results",
'edit_poll' => "Edit poll",
'public_note' => "<b>Note:</b> This is a public poll, other users will be able to see what you voted for.",
'total' => "Total",
'vote' => "Vote!",
'total_votes' => "{1} vote(s)",
'you_voted' => "* You voted for this item.",
'poll_closed' => "This poll is closed.",
'poll_closes' => "This poll will close on: {1}",
'already_voted' => "You have already voted in this poll.",
'no_voting_permission' => "You do not have permission to vote in this poll.",
'undo_vote' => "Undo vote",
'quick_reply' => "Quick Reply",
'message_note' => "Type your reply to this message here.",
'signature' => "Signature",
'email_notify' => "Email Notification",
'disable_smilies' => "Disable Smilies",
'post_reply' => "Post Reply",
'post_reply_img' => "Post Reply",
'new_reply' => "New Reply",
'search_button' => 'Search',
'post_thread' => "Post Thread",
'preview_post' => "Preview Post",
'rating_average' => "{1} Vote(s) - {2} Average",
'rate_thread' => "Rate This Thread:",
'thread_rating' => "Thread Rating:",
'similar_threads' => "Possibly Related Threads&hellip,",
'thread' => "Thread",
'replies' => "Replies",
'views' => "Views",
'lastpost' => "Last Post",
'messages_in_thread' => "Messages In This Thread",
'users_browsing_thread' => "Users browsing this thread:",
'users_browsing_thread_guests' => "{1} Guest(s)",
'users_browsing_thread_invis' => "{1} Invisible User(s)",
'users_browsing_thread_reading' => "Reading&hellip,",
'inline_soft_delete_posts' => "Soft Delete Posts",
'inline_restore_posts' => "Restore Posts",
'inline_delete_posts' => "Delete Posts Permanently",
'inline_merge_posts' => "Merge Posts",
'inline_split_posts' => "Split Posts",
'inline_move_posts' => "Move Posts",
'inline_approve_posts' => "Approve Posts",
'inline_unapprove_posts' => "Unapprove Posts",
'inline_post_moderation' => "Inline Post Moderation:",
'inline_go' => "Go",
'go' => "Go",
'clear' => "Clear",
'thread_closed' => "Thread Closed",
'no_subject' => "No subject",
'error_nonextnewest' => "There are no threads that are newer than the one you were previously viewing.",
'error_nonextoldest' => "There are no threads that are older than the one you were previously viewing.",
'quickreply_multiquote_selected' => "You have selected one or more posts to quote.",
'quickreply_multiquote_now' => "Quote these posts now",
'or' => "or",
'quickreply_multiquote_deselect' => "deselect them",
'search_thread' => "Search Thread",
'enter_keywords' => "Enter Keywords",
'view_thread_notes' => "Thread Notes",
'view_all_notes' => "View All Notes",

'save_changes' => 'Save Changes',
'cancel_edit' => 'Cancel Edit',
'quick_edit_update_error' => 'There was an error editing your reply:',
'quick_reply_post_error' => 'There was an error posting your reply:',
'quick_delete_error' => 'There was an error deleting your reply:',
'quick_delete_success' => 'The post was deleted successfully.',
'quick_delete_thread_success' => 'The thread was deleted successfully.',
'quick_restore_error' => 'There was an error restoring your reply:',
'quick_restore_success' => 'The post was restored successfully.',
'post_deleted_error' => 'You can not perform this action to a deleted post.',





// ── Report modal ──
'report_title'             => 'Report Forum Post',
'report_close'             => 'Close',
'report_reporting'         => 'Reporting:',
'report_forum_post'        => 'Forum Post',

// ── Post preview ──
'report_post_preview'      => 'Post Preview',
'report_user'              => 'User',
'report_post_subject'      => 'Post Subject',
'report_forum'             => 'Forum:',
'report_forum_default'     => 'General',
'report_thread'            => 'Thread:',
'report_thread_default'    => 'Discussion',
'report_content_default'   => 'Post content will appear here...',

// ── Reason ──
'report_reason'            => 'Reason for Report',
'report_select_reason'     => 'Select a reason...',

'report_group_content'     => 'Content Violations',
'report_r_spam'            => 'Spam / Advertising',
'report_r_offensive'       => 'Offensive / Abusive Language',
'report_r_harassment'      => 'Harassment / Bullying',
'report_r_hate_speech'     => 'Hate Speech / Discrimination',
'report_r_explicit'        => 'Explicit / Adult Content',
'report_r_illegal'         => 'Illegal Content / Warez',

'report_group_rules'       => 'Forum Rules',
'report_r_off_topic'       => 'Off Topic / Wrong Forum',
'report_r_double_post'     => 'Double Post / Cross-Posting',
'report_r_flame'           => 'Flaming / Trolling',
'report_r_personal_attack' => 'Personal Attack',
'report_r_spoiler'         => 'Unmarked Spoilers',

'report_group_other'       => 'Other Issues',
'report_r_copyright'       => 'Copyright Infringement',
'report_r_personal_info'   => 'Personal Information',
'report_r_malware'         => 'Malware Link',
'report_r_scam'            => 'Scam / Fraud',
'report_r_other'           => 'Other Reason',

// ── Details ──
'report_details'              => 'Additional Details',
'report_details_placeholder'  => 'Please provide more details...',
'report_details_hint'         => 'Optional but very helpful for moderators',

// ── Rule violation ──
'report_rule_violation'    => 'Specific Rule Violation (Optional)',
'report_rule_none'         => 'Not specified',
'report_rule_1'            => 'Rule 1: No spamming or advertising',
'report_rule_2'            => 'Rule 2: No offensive language',
'report_rule_3'            => 'Rule 3: No harassment or bullying',
'report_rule_4'            => 'Rule 4: Stay on topic',
'report_rule_5'            => 'Rule 5: No warez or illegal content',
'report_rule_6'            => 'Rule 6: Respect other members',
'report_rule_7'            => 'Rule 7: No double posting',
'report_rule_8'            => 'Rule 8: Use appropriate language',

// ── Email ──
'report_email'             => 'Contact Email (Optional)',
'report_email_placeholder' => 'your@email.com',

// ── Captcha ──
'report_security'            => 'Security Check',
'report_captcha_alt'         => 'Security code',
'report_captcha_title'       => 'Click to refresh',
'report_captcha_placeholder' => 'Enter code',

// ── Warning ──
'report_important'         => 'Important:',
'report_rules_pre'         => 'Please only report posts that violate our',
'report_rules_link'        => 'forum rules',
'report_rules_post'        => 'False reports may result in penalties.',

// ── Buttons ──
'report_cancel'            => 'Cancel',
'report_submit'            => 'Submit Report',








// ── Moderation: General ──
'mod_go' => 'Go',
'mod_cancel' => 'Cancel',
'mod_delete_permanently' => 'Delete Permanently',
'mod_thread_id' => 'Thread ID:',
'mod_tid' => 'TID:',

// ── Moderation: Delete posts modal ──
'mod_dp_title' => 'Delete Posts Permanently',
'mod_dp_subtitle' => 'Irreversible Action',
'mod_dp_warning' => 'You are about to <strong>permanently delete</strong> selected posts. This <strong>cannot be undone</strong>.',
'mod_dp_posts' => 'Posts:',
'mod_dp_preview' => 'Posts to be deleted:',

// ── Moderation: Delete thread modal ──
'mod_dt_title' => 'Delete Thread',
'mod_dt_warning_title' => 'Permanent Deletion Warning',
'mod_dt_warning_text' => 'All posts, attachments, and poll data will be permanently deleted. This cannot be undone.',
'mod_dt_confirm1' => 'I understand this is permanent and cannot be undone',
'mod_dt_confirm2' => 'I have ensured all important content is backed up',

// ── Moderation: Merge thread modal ──
'mod_mg_title' => 'Merge Threads',
'mod_mg_subtitle' => 'Combine multiple threads into one',
'mod_mg_current' => 'Current Thread',
'mod_mg_new_subject' => 'New Subject',
'mod_mg_url' => 'Thread URL to Merge',
'mod_mg_note' => 'The specified thread will be <strong>deleted</strong> and its posts merged into this one.',
'mod_mg_button' => 'Merge Threads',

// ── Moderation: Move / copy thread modal ──
'mod_mv_title' => 'Move / Copy Thread',
'mod_mv_subtitle' => 'Transfer to another forum',
'mod_mv_destination' => 'Destination Forum',
'mod_mv_method' => 'Transfer Method',
'mod_mv_redirect' => 'Move with Redirect',
'mod_mv_redirect_desc' => 'Leave redirect in original forum',
'mod_mv_redirect_days' => 'Redirect days (blank = infinite)',
'mod_mv_move' => 'Move Thread',
'mod_mv_move_desc' => 'Remove from original forum',
'mod_mv_copy' => 'Copy Thread',
'mod_mv_copy_desc' => 'Keep original, create copy',
'mod_mv_process' => 'Process Thread',











// ── Postbit: edit / delete modals and buttons ──
'pb_bb_left' => 'Left',
'pb_bb_center' => 'Center',
'pb_bb_right' => 'Right',
'pb_bb_red' => 'Red',
'pb_bb_size' => 'Size',
'pb_bb_video' => 'Video',
'pb_bb_quote' => 'Quote',
'pb_bb_code' => 'Code',
'pb_bb_list' => 'List',
'pb_bb_list_num' => '#List',
'pb_bb_spoiler' => 'Spoiler',
'pb_torrent' => 'Torrent',
'pb_torrent_id_label' => 'Torrent ID or URL',
'pb_torrent_placeholder' => 'e.g. 17 or paste the torrent link',
'pb_insert' => 'Insert',
'pb_edit_title' => 'Edit Post',
'pb_edit_reason' => 'Edit Reason (optional)',
'pb_live_preview' => 'Live Preview',
'pb_cancel' => 'Cancel',
'pb_save_changes' => 'Save Changes',
'pb_delete_title' => 'Delete Post',
'pb_delete_confirm' => 'Are you sure you want to delete this post?',
'pb_delete_irreversible' => 'This action cannot be undone.',
'pb_pid' => 'PID:',
'pb_deleting' => 'Deleting...',
'pb_deleting_post' => 'Deleting post...',
'pb_delete' => 'Delete',
'pb_report_post' => 'Report Post',
'pb_edit_note' => 'This post was last modified: %s by',
'pb_unapproved_own' => 'The post made by you is under moderation and currently not visible publicly. It will be visible once a moderator approves it.',
'pb_ignored' => 'The contents of this message are hidden because %s is on your <a href="usercp.php?action=editlists">ignore list</a>.',
'pb_post_deleted' => 'This post has been deleted',







);