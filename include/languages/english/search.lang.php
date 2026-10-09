<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// search.php (+ include/functions_search.php)
$language['search'] = array
(

// ── Navigation / page titles ──
'nav_search'            => "Search",
'nav_results'           => "Results",
'title_search'          => "Forum Search",
'title_results_threads' => "Search Results",
'title_results_posts'   => "Search Results — Posts",

// ── Search form: hero ──
'sec_hero_title' => "Forum Search",
'hint_hero'      => "Find threads, posts and discussions across the forum",

// ── Search form: quick links ──
'lnk_new_posts'  => "New posts",
'lnk_today'      => "Today",
'lnk_this_week'  => "This week",
'lnk_my_threads' => "My threads",
'lnk_my_posts'   => "My posts",

// ── Search form: main row ──
'ph_keywords'      => "Search keywords… (Ctrl+K)",
'btn_search'       => "Search",
'btn_clear'        => "Clear",
'lbl_show_as'      => "Show as:",
'opt_show_threads' => "Threads",
'opt_show_posts'   => "Posts",
'lbl_advanced'     => "Advanced options",

// ── Search form: advanced options ──
'lbl_author'          => "Author",
'ph_author'           => "Username…",
'lbl_exact_match'     => "Exact match only",
'lbl_search_in'       => "Search in",
'opt_subject_message' => "Subject & message",
'opt_subject_only'    => "Subject only",
'lbl_sort_by'         => "Sort by",
'opt_sortby_lastpost' => "Last post date",
'opt_sortby_dateline' => "Post date",
'opt_sortby_subject'  => "Subject",
'opt_sortby_replies'  => "Replies",
'opt_sortby_views'    => "Views",
'opt_sortby_starter'  => "Author",
'opt_sortby_forum'    => "Forum",
'lbl_order'           => "Order",
'opt_order_desc'      => "Newest first",
'opt_order_asc'       => "Oldest first",
'lbl_date_from'       => "Posted from",
'lbl_date_to'         => "Posted to",
'ph_date'             => "YYYY-MM-DD",
'lbl_min_replies'     => "Minimum replies",
'lbl_forums'          => "Forums",
'hint_forums_multi'   => "Hold Ctrl to select multiple",
'search_all_forums'   => "Search All Open Forums",

// ── Search form: moderator options (output as is) ──
'mod_options'              => "Moderator Options",
'display_all'              => "Display all",
'display_only_approved'    => "Display only approved",
'display_only_unapproved'  => "Display only unapproved",
'display_only_softdeleted' => "Display only soft deleted",
'results'                  => "results",

// ── Results: header (HTML, output as is) ──
'sec_thread_results'   => "Thread Results",
'sec_post_results'     => "Post Results",
'lbl_found_thread_one' => "Found <strong>{1}</strong> thread",
'lbl_found_threads'    => "Found <strong>{1}</strong> threads",
'lbl_found_post_one'   => "Found <strong>{1}</strong> post",
'lbl_found_posts'      => "Found <strong>{1}</strong> posts",

// ── Results: sort bar ──
'opt_sort_date'    => "Date",
'opt_sort_replies' => "Replies",
'opt_sort_views'   => "Views",
'opt_sort_rating'  => "Rating",
'opt_sort_subject' => "Subject",
'opt_sort_author'  => "Author",
'opt_sort_forum'   => "Forum",

// ── Results: cards ──
'badge_new'         => "New",
'badge_hot'         => "Hot",
'badge_post'        => "Post",
'lbl_replies_count' => "{1} replies",
'lbl_views_count'   => "{1} views",
'lbl_lastpost_by'   => "{1} by {2}",
'btn_view'          => "View",
'btn_view_post'     => "View post",
'btn_thread'        => "Thread",

// ── Live suggestions (AJAX) ──
'suggest_meta_thread' => "{1} · {2} replies",
'suggest_meta_user'   => "Member",

// ── JS strings (passed to AGS_LANG without the js_ prefix) ──
'js_type_thread' => "thread",
'js_type_post'   => "post",
'js_type_user'   => "user",
'js_type_forum'  => "forum",
'js_fp_locale'   => "default",

// ── Messages / errors ──
'redirect_searchresults'   => "Thank you, your search has been submitted and you will now be taken to the results list.",
'error_invalidforum'       => "Invalid forum",
'error_closedinvalidforum' => "You may not post in this forum because either the forum is closed, it is a redirect to another webpage, or it is a category.",
'error_no_search_support'  => "This database engine does not support searching.",
'error_nosearchterms'      => "You did not enter any search terms. At a minimum, you must enter either some search terms or a username to search by.",
'error_nosearchresults'    => "Sorry, but no results were returned using the query information you provided. Please redefine your search terms and try again.",
'error_minsearchlength'    => "One or more of your search terms were shorter than the minimum length. The minimum search term length is {1} characters.<br /><br />If you're trying to search for an entire phrase, enclose it within double quotes. For example \"The quick brown fox jumps over the lazy dog\".",
'error_searchflooding_1'   => "Sorry, but you can only perform one search every {1} seconds. Please wait another 1 second before attempting to search again.",
'error_searchflooding'     => "Sorry, but you can only perform one search every {1} seconds. Please wait another {2} seconds before attempting to search again.",
'error_invalidsearch'      => "An invalid search was specified.  Please go back and try again.",

);
?>
