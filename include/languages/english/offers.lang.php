<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['offers'] = array
(
    // ── Page titles ──────────────────────────────────────────────────────────
    'title_list'            => 'Offers',
    'title_create'          => 'New Offer',
    'title_view'            => 'Offer: {1}',

    // ── Navigation / breadcrumbs ─────────────────────────────────────────────
    'nav_home'              => 'Home',
    'nav_offers'            => 'Offers',
    'nav_new'               => 'New Offer',

    // ── Status badges ────────────────────────────────────────────────────────
    'status_open'           => 'Open',
    'status_uploaded'       => 'Uploaded',
    'status_cancelled'      => 'Cancelled',

    // ── Options (filters / selects) ──────────────────────────────────────────
    'opt_status_open'       => 'Open',
    'opt_status_uploaded'   => 'Uploaded',
    'opt_status_cancelled'  => 'Cancelled',
    'opt_status_all'        => 'All',
    'opt_cat_any'           => '— Any —',

    // ── List page ────────────────────────────────────────────────────────────
    'sec_list'              => 'Offers',
    'txt_total'             => '{1} total offers',
    'lbl_wants'             => 'wants',
    'txt_ago'               => '{1} ago',
    'txt_empty'             => 'No offers found',
    'btn_new'               => 'Post an Offer',
    'btn_create_first'      => 'Post the first offer',

    // ── Single offer view ────────────────────────────────────────────────────
    'lbl_want_count'        => '{1} want this',
    'txt_uploaded'          => 'This offer has been uploaded!',
    'lnk_download'          => 'Download here',
    'btn_want'              => 'I want this!',
    'txt_your_offer'        => 'Your offer',
    'txt_you_requested'     => 'You requested this',
    'btn_uploaded'          => 'Uploaded',
    'btn_cancel_offer'      => 'Cancel',
    'btn_delete'            => 'Delete',
    'conf_cancel'           => 'Cancel this offer?',
    'conf_delete'           => 'Delete this offer permanently?',

    // ── Sidebar ──────────────────────────────────────────────────────────────
    'lbl_people_want'       => 'people want this',
    'lbl_offered_by'        => 'Offered by',
    'lbl_year_value'        => 'Year: {1}',

    // ── Comments ─────────────────────────────────────────────────────────────
    'sec_comments'          => 'Comments',
    'txt_no_comments'       => 'No comments yet.',
    'ph_comment'            => 'Add a comment...',
    'aria_send_comment'     => 'Send comment',

    // ── Upload modal ─────────────────────────────────────────────────────────
    'pane_upload'           => 'Mark as Uploaded',
    'hint_upload'           => 'Enter the torrent ID that you uploaded for this offer.',
    'lbl_torrent_id'        => 'Torrent ID',
    'ph_torrent_id'         => 'e.g. 12345',
    'btn_modal_cancel'      => 'Cancel',
    'btn_confirm_upload'    => 'Confirm Upload',
    'aria_close'            => 'Close',

    // ── Create form ──────────────────────────────────────────────────────────
    'pane_create'           => 'Post a New Offer',
    'hint_create'           => 'I have this and can upload it if people want it',
    'lbl_title'             => 'Title',
    'ph_title'              => 'Movie / TV Show / Game title...',
    'lbl_category'          => 'Category',
    'lbl_year'              => 'Year',
    'lbl_description'       => 'Description',
    'ph_description'        => 'Describe what you have — quality, format, source, size, etc.',
    'btn_form_cancel'       => 'Cancel',
    'btn_submit'            => 'Post Offer',

    // ── Validation errors (create form) ──────────────────────────────────────
    'err_title_short'       => 'Title must be at least 3 characters.',
    'err_title_long'        => 'Title is too long.',

    // ── Errors (stderr / AJAX) ───────────────────────────────────────────────
    'err_post_key'          => 'Invalid post key. Please reload the page and try again.',
    'err_not_found'         => 'Offer not found.',
    'err_closed'            => 'This offer is closed.',
    'err_own_offer'         => 'You cannot request your own offer.',
    'err_already_requested' => 'You have already requested this offer.',
    'err_empty_message'     => 'The comment cannot be empty.',
    'err_no_permission'     => 'You do not have permission to do this.',

    // ── Flash messages ───────────────────────────────────────────────────────
    'flash_created'         => 'Offer created successfully!',
    'flash_torrent_missing' => 'Torrent ID {1} does not exist.',
    'flash_uploaded'        => 'Offer marked as uploaded!',
    'flash_cancelled'       => 'Offer cancelled.',
    'flash_deleted'         => 'Offer deleted.',

    // ── PM to users who wanted the offer ─────────────────────────────────────
    'pm_uploaded_subject'   => 'Offer uploaded: {1}',
    'pm_uploaded_message'   => 'The offer [b]{1}[/b] has been uploaded! [url={2}]Download here[/url]',

    // ── JS strings ───────────────────────────────────────────────────────────
    'js_loading'            => 'Loading...',
    'js_want_error'         => 'Error processing request',
    'js_comment_empty'      => 'Please enter a comment.',
    'js_comment_error'      => 'Error adding comment',
    'js_server_error'       => 'Server error (HTTP {1}). Please try again.',
    'js_network_error'      => 'Network error. Please check your connection and try again.',
);
?>
