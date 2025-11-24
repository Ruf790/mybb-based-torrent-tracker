<?php

declare(strict_types=1);

/**
 * MyBB 1.8 - Statistics Page
 * Optimized for PHP 8.4
 * Copyright 2014 MyBB Group, All Rights Reserved
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'stats.php');


$templatelist = "stats,stats_thread,stats_topforum";


define('IN_FORUM', true);

require_once 'global.php';


require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/class_parser.php";

$parser = new postParser;

// Load global language phrases
$lang->load("stats");

add_breadcrumb($lang->stats['nav_stats']);

$stats = $cache->read("stats") ?? [];

// Validate statistics data
if(($stats['numthreads'] ?? 0) < 1 || ($stats['numusers'] ?? 0) < 1) {
    stderr($lang->stats['not_enough_info_stats']);
}

$plugins->run_hooks("stats_start");

// Calculate statistics
$repliesperthread = ts_nf(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2));
$postspermember = ts_nf(round(($stats['numposts'] / $stats['numusers']), 2));
$threadspermember = ts_nf(round(($stats['numthreads'] / $stats['numusers']), 2));

// Get number of days since board start
$query = $db->simple_select("users", "added", "", ['order_by' => 'added', 'limit' => 1]);
$result = $db->fetch_array($query);
$days = max((TIMENOW - ($result['added'] ?? TIMENOW)) / 86_400, 1);

// Get "per day" statistics
$postsperday = ts_nf(round(($stats['numposts'] / $days), 2));
$threadsperday = ts_nf(round(($stats['numthreads'] / $days), 2));
$membersperday = ts_nf(round(($stats['numusers'] / $days), 2));

// Forum permissions
$unviewableforums = get_unviewable_forums(true);
$inactiveforums = get_inactive_forums();

$unviewablefids = $unviewableforums ? explode(',', $unviewableforums) : [];
$inactivefids = $inactiveforums ? explode(',', $inactiveforums) : [];
$unviewableforumsarray = [...$unviewablefids, ...$inactivefids];

// Build forum exclusion clause
$fidnot = '';
if($unviewableforums) {
    $fidnot .= "AND fid NOT IN ($unviewableforums)";
}
if($inactiveforums) {
    $fidnot .= " AND fid NOT IN ($inactiveforums)";
}

// Check group permissions for thread visibility
$group_permissions = forum_permissions();
$onlyusfids = [];

foreach($group_permissions as $gpfid => $forum_permissions) {
    if(($forum_permissions['canonlyviewownthreads'] ?? 0) == 1) {
        $onlyusfids[] = $gpfid;
    }
}

/**
 * Process thread statistics with template
 */
function process_thread_stats(array $threads, string $number_type, string $template_name): string 
{
    global $templates, $parser, $unviewableforumsarray, $onlyusfids, $CURUSER;
    
    $output = '';
    
    foreach($threads as $thread) {
        // Skip if user cannot view this thread
        // if(in_array($thread['fid'], $unviewableforumsarray) || 
        //    (in_array($thread['fid'], $onlyusfids) && (!$CURUSER['id'] || $thread['uid'] != $CURUSER['id']))) {
        //     continue;
        // }
        
        $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
        $numberbit = ts_nf($thread[$number_type === 'replies' ? 'replies' : 'views']);
        $thread['threadlink'] = get_thread_link($thread['tid']);
        
        eval("\$output .= \"".$templates->get($template_name)."\";");
    }
    
    return $output;
}

// Most replied-to threads
$most_replied = $cache->read("most_replied_threads") ?? [];

if(empty($most_replied)) {
    $cache->update_most_replied_threads();
    $most_replied = $cache->read("most_replied_threads") ?? [];
}

$mostreplies = process_thread_stats($most_replied, 'replies', "stats_thread");

// Most viewed threads
$most_viewed = $cache->read("most_viewed_threads") ?? [];

if(empty($most_viewed)) {
    $cache->update_most_viewed_threads();
    $most_viewed = $cache->read("most_viewed_threads") ?? [];
}

$mostviews = process_thread_stats($most_viewed, 'views', "stats_thread");

// Update statistics cache if needed
$statistics = $cache->read('statistics') ?? [];
$statscachetime = (int)($statscachetime ?? 0);
$interval = max($statscachetime, 0) * 3_600;

if(empty($statistics) || $interval === 0 || TIMENOW - $interval > ($statistics['time'] ?? 0)) {
    $cache->update_statistics();
    $statistics = $cache->read('statistics') ?? [];
}

// Top forum
$query = $db->simple_select(
    'tsf_forums', 
    'fid, name, threads, posts', 
    "type='f'{$fidnot}", 
    ['order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1]
);

$forum = $db->fetch_array($query);

if(!$forum) {
    $topforum = 'none';
    $topforumposts = 'no';
    $topforumthreads = 'no';
} else {
    $forum['name'] = htmlspecialchars_uni(strip_tags($forum['name']));
    $forum['link'] = get_forum_link($forum['fid']);
    eval("\$topforum = \"".$templates->get("stats_topforum")."\";");
    $topforumposts = $forum['posts'];
    $topforumthreads = $forum['threads'];
}

// Top referrer
$top_referrer = '';
if(($mybb->settings['statstopreferrer'] ?? 0) == 1 && isset($statistics['top_referrer']['uid'])) {
    if(($statistics['top_referrer']['referrals'] ?? 0) > 0) {
        $toprefuser = build_profile_link(
            htmlspecialchars_uni($statistics['top_referrer']['username'] ?? ''), 
            $statistics['top_referrer']['uid'] ?? 0
        );
        $top_referrer = sprintf(
            $lang->stats['top_referrer'] ?? 'Top referrer: %s (%s referrals)', 
            $toprefuser, 
            ts_nf($statistics['top_referrer']['referrals'] ?? 0)
        );
    }
}

// Today's top poster with safe variable handling
$topposter = 'nobody';
$topposterposts = 'no_posts';

if(isset($statistics['top_poster']['id'])) {
    $topPosterId = $statistics['top_poster']['id'] ?? 0;
    
    if($topPosterId === 0) {
        $topposter = 'guest';
    } else {
        $top_poster_uid = $statistics['top_poster']['uid'] ?? 0;
        $topposter = build_profile_link(
            htmlspecialchars_uni($statistics['top_poster']['username'] ?? ''), 
            $top_poster_uid
        );
    }
    
    $topposterposts = $statistics['top_poster']['poststoday'] ?? 0;
}

// Calculate posting percentage
$posters = $statistics['posters'] ?? 0;
$havepostedpercent = ts_nf(round((($posters / $stats['numusers']) * 100), 2)) . "%";

$todays_top_poster = sprintf(
    $lang->stats['todays_top_poster'] ?? "Today's top poster: %s (%s posts)", 
    $topposter, 
    ts_nf($topposterposts)
);

$popular_forum = sprintf(
    $lang->stats['popular_forum'] ?? "Most popular forum: %s (%s posts, %s threads)", 
    $topforum, 
    ts_nf($topforumposts), 
    ts_nf($topforumthreads)
);

// Format statistics for display
$stats['numposts'] = ts_nf($stats['numposts'] ?? 0);
$stats['numthreads'] = ts_nf($stats['numthreads'] ?? 0);
$stats['numusers'] = ts_nf($stats['numusers'] ?? 0);
$stats['newest_user'] = build_profile_link(
    $stats['lastusername'] ?? '', 
    $stats['lastuid'] ?? 0
);

$plugins->run_hooks("stats_end");

eval("\$stats_output = \"".$templates->get("stats")."\";");

stdhead();
build_breadcrumb();
echo $stats_output;
stdfoot();