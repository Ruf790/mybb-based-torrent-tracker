<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// stats.php
$language['stats'] = array
(

// ── Page ──
'nav_stats'          => "Board Statistics",
'pane_title'         => "Board Statistics",
'pane_desc'          => "Forum activity at a glance",

// ── Errors ──
'err_not_enough_info' => "Sorry, but there is not enough information on this board to generate statistics. Before statistics can be generated this board needs to contain at least 1 member and 1 thread.",

// ── Header chips ──
'chip_days_online'   => "{1} days online",
'chip_newest'        => "Newest:",

// ── KPI tiles ──
'kpi_posts'          => "Posts",
'kpi_threads'        => "Threads",
'kpi_members'        => "Members",
'kpi_new_members'    => "New members per day",
'kpi_sub_per_day'    => "{1} per day",
'kpi_sub_posted'     => "{1} have posted",
'kpi_sub_over_days'  => "over {1} days",

// ── Section headings ──
'sec_most_replied'   => "Most Replied To Threads",
'sec_most_viewed'    => "Most Viewed Threads",
'sec_averages'       => "Averages",
'sec_records'        => "Records",

// ── Empty states ──
'empty_replied'      => "No threads with replies yet",
'empty_viewed'       => "No viewed threads yet",

// ── Thread rankings ──
'tip_count_replies'  => "{1} replies",
'tip_count_views'    => "{1} views",

// ── Averages ──
'avg_ppd'            => "Posts per day",
'avg_tpd'            => "Threads per day",
'avg_mpd'            => "Members per day",
'avg_ppm'            => "Posts per member",
'avg_tpm'            => "Threads per member",
'avg_rpt'            => "Replies per thread",

// ── Records ──
'rec_top_poster'     => "Top poster today",
'rec_popular_forum'  => "Most popular forum",
'rec_newest_member'  => "Newest member",
'rec_members_posted' => "Members who have posted",
'rec_top_referrer'   => "Top referrer",
'pill_posts'         => "{1} posts",
'pill_of'            => "{1} of {2}",
'pill_invited'       => "{1} invited",
'tip_posts_threads'  => "Posts / threads",

// ── Fallback names ──
'lbl_none'           => "None",
'lbl_nobody'         => "Nobody",
'lbl_guest'          => "Guest",

// ── Summary strings (output as-is, HTML allowed; available to stats_end plugins) ──
'txt_todays_top_poster' => "Today's top poster: <b>{1}</b> (<b>{2}</b> posts)",
'txt_popular_forum'     => "Most popular forum: <b>{1}</b> (<b>{2}</b> posts, <b>{3}</b> threads)",
'txt_top_referrer'      => "Top referrer: <strong>{1}</strong> (<strong>{2}</strong> referrals)",

);
?>
