<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['news'] = array
(

// ── Page header ──
'page_title'            => "Manage Site News",
'pane_title'            => "Site News",
'pane_subtitle'         => "Publish announcements and keep the news archive up to date.",
'lbl_head_chip'         => "News Manager",

// ── KPI tiles ──
'lbl_kpi_total'         => "News articles",
'lbl_kpi_month'         => "Published this month",
'lbl_kpi_authors'       => "Authors",
'lbl_kpi_latest'        => "Latest article",

// ── Create form ──
'sec_create'            => "Create News Article",
'hint_create'           => "Share important updates with your community",
'lbl_title'             => "News Title",
'ph_title'              => "Enter an engaging title…",
'lbl_content'           => "News Content",
'ph_content'            => "Write your news content here…",
'lbl_char_count'        => "{1} / {2} characters",
'btn_publish'           => "Publish News",
'btn_clear'             => "Clear",

// ── Archive ──
'sec_archive'           => "News Archive",
'lbl_by'                => "by {1}",
'lbl_unknown_user'      => "Unknown User",
'lbl_news_id'           => "News ID: #{1}",
'btn_edit'              => "Edit",
'btn_delete'            => "Delete",
'tip_edit'              => "Edit news",
'tip_delete'            => "Delete news",
'sec_empty'             => "No News Available",
'hint_empty'            => "Be the first to create a news article!",
'lbl_load_error'        => "Error loading news:",

// ── Edit modal ──
'sec_edit'              => "Edit News Article",
'lbl_edit_title'        => "Title",
'ph_edit_title'         => "Enter news title",
'lbl_edit_content'      => "Content",
'lbl_preview'           => "Live Preview",
'hint_preview'          => "Preview will appear here as you type…",
'btn_cancel'            => "Cancel",
'btn_save'              => "Save Changes",
'aria_close'            => "Close",

// ── BBCode toolbar (edit modal) ──
'tip_bb_bold'           => "Bold",
'tip_bb_italic'         => "Italic",
'tip_bb_underline'      => "Underline",
'tip_bb_strike'         => "Strikethrough",
'tip_bb_left'           => "Align left",
'tip_bb_center'         => "Align center",
'tip_bb_right'          => "Align right",
'tip_bb_red'            => "Red text",
'tip_bb_blue'           => "Blue text",
'tip_bb_green'          => "Green text",
'tip_bb_url'            => "Link",
'tip_bb_img'            => "Image",
'tip_bb_video'          => "Video",
'tip_bb_youtube'        => "YouTube",
'tip_bb_quote'          => "Quote",
'tip_bb_code'           => "Code",
'tip_bb_spoiler'        => "Spoiler",
'tip_bb_ul'             => "Bulleted list",
'tip_bb_ol'             => "Numbered list",
'tip_bb_li'             => "List item",
'tip_bb_torrent'        => "Embed torrent card",
'lbl_bb_torrent'        => "Torrent",
'lbl_torrent_id'        => "Torrent ID or URL",
'ph_torrent_id'         => "e.g. 17 or paste the torrent link",
'btn_insert'            => "Insert",

// ── AJAX errors ──
'err_access_denied'     => "Access denied.",
'err_method'            => "Method not allowed.",
'err_csrf'              => "Security check failed. Please refresh the page and try again.",
'err_invalid_id'        => "Invalid news ID.",
'err_not_found'         => "News item not found.",
'err_title_empty'       => "Title cannot be empty.",
'err_content_empty'     => "Content cannot be empty.",
'err_title_long'        => "Title is too long (max {1} characters).",
'err_content_long'      => "Content is too long (max {1} characters).",
'err_invalid_user'      => "Invalid user ID.",
'err_update_failed'     => "Failed to update the news item.",
'err_create_failed'     => "Failed to create the news item.",
'err_invalid_action'    => "Invalid action.",
'err_internal'          => "Internal server error.",

// ── AJAX success ──
'flash_deleted'         => "News deleted successfully.",
'flash_updated'         => "News updated successfully.",
'flash_added'           => "News added successfully.",

// ── JS ──
'js_publishing'         => "Publishing…",
'js_saving'             => "Saving…",
'js_published'          => "News published!",
'js_updated'            => "News updated!",
'js_deleted'            => "News deleted!",
'js_add_failed'         => "Failed to publish the news.",
'js_edit_failed'        => "Edit failed.",
'js_delete_failed'      => "Failed to delete.",
'js_network'            => "Network error. Please try again.",
'js_invalid_item'       => "Invalid news item.",
'js_title_empty'        => "Title cannot be empty.",
'js_content_empty'      => "Content cannot be empty.",
'js_delete_title'       => "Delete this news article?",
'js_delete_text'        => "\"{1}\" will be removed permanently. This cannot be undone.",
'js_btn_delete'         => "Delete",
'js_btn_cancel'         => "Cancel",
'js_empty_title'        => "No News Available",
'js_empty_text'         => "Create your first news article!",
'js_preview_empty'      => "Preview will appear here…",
'js_spoiler'            => "Spoiler (click to show)",
'js_loading_torrent'    => "Loading torrent #{1}…",
'js_loading_preview'    => "Loading preview…",
'js_seeders'            => "{1} seeders",
'js_leechers'           => "{1} leechers",
'js_preview_failed'     => "Failed to load preview.",

);
