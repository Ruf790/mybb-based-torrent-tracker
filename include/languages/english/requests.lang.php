<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['requests'] = array
(
    // ── Page titles ──────────────────────────────────────────────────────────
    'title_list'            => 'Requests',
    'title_create'          => 'New Request',
    'title_view'            => 'Request: {1}',

    // ── Navigation / breadcrumbs ─────────────────────────────────────────────
    'nav_home'              => 'Home',
    'nav_requests'          => 'Requests',
    'nav_new'               => 'New Request',

    // ── Status badges ────────────────────────────────────────────────────────
    'status_open'           => 'Open',
    'status_filled'         => 'Filled',
    'status_cancelled'      => 'Cancelled',

    // ── Options (filters / selects) ──────────────────────────────────────────
    'opt_status_open'       => 'Open',
    'opt_status_filled'     => 'Filled',
    'opt_status_cancelled'  => 'Cancelled',
    'opt_status_all'        => 'All',
    'opt_cat_any'           => '— Any —',

    // ── List page ────────────────────────────────────────────────────────────
    'sec_list'              => 'Requests',
    'txt_total'             => '{1} total requests',
    'lbl_votes'             => 'votes',
    'txt_ago'               => '{1} ago',
    'txt_empty'             => 'No requests found',
    'btn_new'               => 'New Request',
    'btn_create_first'      => 'Create the first request',

    // ── Single request view ──────────────────────────────────────────────────
    'lbl_bounty_amount'     => '{1} BP bounty',
    'lbl_voters_count'      => '{1} voters',
    'txt_filled'            => 'This request was filled!',
    'lnk_download'          => 'Download here',
    'btn_vote'              => 'Vote',
    'ph_add_bp'             => 'Add BP',
    'lbl_balance'           => 'Balance: {1}',
    'txt_you_voted'         => 'You voted',
    'btn_fill'              => 'Fill',
    'btn_cancel_request'    => 'Cancel',
    'btn_delete'            => 'Delete',
    'conf_cancel'           => 'Cancel this request?',
    'conf_delete'           => 'Delete permanently? Bounty will be refunded.',

    // ── Sidebar ──────────────────────────────────────────────────────────────
    'lbl_voters'            => 'voters',
    'lbl_total_bounty'      => 'total bounty',
    'unit_bp'               => 'BP',
    'txt_created_ago'       => 'Created {1} ago',

    // ── Comments ─────────────────────────────────────────────────────────────
    'sec_comments'          => 'Comments',
    'txt_no_comments'       => 'No comments yet.',
    'ph_comment'            => 'Add a comment...',
    'aria_send_comment'     => 'Send comment',

    // ── Fill modal ───────────────────────────────────────────────────────────
    'pane_fill'             => 'Fill Request',
    'hint_fill'             => 'Enter the torrent ID that fulfills this request.',
    'lbl_torrent_id'        => 'Torrent ID',
    'ph_torrent_id'         => 'e.g. 12345',
    'btn_modal_cancel'      => 'Cancel',
    'btn_mark_filled'       => 'Mark as Filled',
    'aria_close'            => 'Close',

    // ── Create form ──────────────────────────────────────────────────────────
    'pane_create'           => 'Create a New Request',
    'lbl_title'             => 'Title',
    'ph_title'              => 'Movie / TV Show / Game title...',
    'lbl_category'          => 'Category',
    'lbl_year'              => 'Year',
    'lbl_bounty'            => 'Bounty (BP)',
    'hint_balance'          => 'Your balance: {1} BP',
    'lbl_description'       => 'Description',
    'ph_description'        => 'Add details — quality, language, source, etc.',
    'btn_form_cancel'       => 'Cancel',
    'btn_submit'            => 'Submit',

    // ── Validation errors (create form) ──────────────────────────────────────
    'err_title_short'       => 'Title must be at least 3 characters.',
    'err_title_long'        => 'Title is too long.',
    'err_no_bonus'          => 'Not enough bonus points.',

    // ── Errors (stderr / AJAX) ───────────────────────────────────────────────
    'err_post_key'          => 'Invalid post key. Please reload the page and try again.',
    'err_not_found'         => 'Request not found.',
    'err_closed'            => 'This request is closed.',
    'err_own_vote'          => 'You cannot vote on your own request.',
    'err_already_voted'     => 'You have already voted.',
    'err_empty_message'     => 'The comment cannot be empty.',
    'err_invalid_request'   => 'This request does not exist or is no longer open.',
    'err_no_permission'     => 'You do not have permission to do this.',

    // ── Flash messages ───────────────────────────────────────────────────────
    'flash_created'         => 'Request created successfully!',
    'flash_torrent_missing' => 'Torrent ID {1} does not exist.',
    'flash_filled'          => 'Request marked as filled!',
    'flash_cancelled'       => 'Request cancelled.',
    'flash_deleted'         => 'Request deleted.',

    // ── PM to requester ──────────────────────────────────────────────────────
    'pm_filled_subject'     => 'Your request has been filled!',
    'pm_filled_message'     => 'Your request [b]{1}[/b] has been filled. [url={2}]Click here to download[/url]',

    // ── JS strings ───────────────────────────────────────────────────────────
    'js_voting'             => 'Voting...',
    'js_vote_error'         => 'Error processing vote',
    'js_comment_empty'      => 'Please enter a comment.',
    'js_comment_error'      => 'Error adding comment',
    'js_server_error'       => 'Server error (HTTP {1}). Please try again.',
    'js_network_error'      => 'Network error. Please check your connection and try again.',
);
?>
