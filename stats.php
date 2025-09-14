<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'stats.php');
define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');

$templatelist = "stats,stats_thread,stats_topforum";



require_once 'global.php';

if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

require_once INC_PATH.'/tsf_functions.php';






require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("stats");

add_breadcrumb($lang->stats['nav_stats']);

$stats = $cache->read("stats");

if($stats['numthreads'] < 1 || $stats['numusers'] < 1)
{
	stderr($lang->stats['not_enough_info_stats']);
}



$plugins->run_hooks("stats_start");

$repliesperthread = ts_nf(round((($stats['numposts'] - $stats['numthreads']) / $stats['numthreads']), 2));
$postspermember = ts_nf(round(($stats['numposts'] / $stats['numusers']), 2));
$threadspermember = ts_nf(round(($stats['numthreads'] / $stats['numusers']), 2));

// Get number of days since board start (might need improvement)
$query = $db->simple_select("users", "added", "", array('order_by' => 'added', 'limit' => 1));
$result = $db->fetch_array($query);
$days = (TIMENOW - $result['added']) / 86400;
if($days < 1)
{
	$days = 1;
}
// Get "per day" things
$postsperday = ts_nf(round(($stats['numposts'] / $days), 2));
$threadsperday = ts_nf(round(($stats['numthreads'] / $days), 2));
$membersperday = ts_nf(round(($stats['numusers'] / $days), 2));

// Get forum permissions
//$unviewableforums = get_unviewable_forums(true);
$inactiveforums = get_inactive_forums();
$unviewablefids = $inactivefids = array();
$fidnot = '';

if($unviewableforums)
{
	$fidnot .= "AND fid NOT IN ($unviewableforums)";
	$unviewablefids = explode(',', $unviewableforums);
}
if($inactiveforums)
{
	$fidnot .= "AND fid NOT IN ($inactiveforums)";
	$inactivefids = explode(',', $inactiveforums);
}

$unviewableforumsarray = array_merge($unviewablefids, $inactivefids);

// Check group permissions if we can't view threads not started by us
//$group_permissions = forum_permissions();
$onlyusfids = array();
foreach($group_permissions as $gpfid => $forum_permissions)
{
	if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
	{
		$onlyusfids[] = $gpfid;
	}
}

// Most replied-to threads
$most_replied = $cache->read("most_replied_threads");

if(!$most_replied)
{
	$cache->update_most_replied_threads();
	$most_replied = $cache->read("most_replied_threads", true);
}

$mostreplies = '';
if(!empty($most_replied))
{
	foreach($most_replied as $key => $thread)
	{
		//if(
		//	!in_array($thread['fid'], $unviewableforumsarray) &&
		//	(!in_array($thread['fid'], $onlyusfids) || ($CURUSER['id'] && $thread['uid'] == $CURUSER['id']))
		//)
		//{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$numberbit = ts_nf($thread['replies']);
			$numbertype = 'replies';
			$thread['threadlink'] = get_thread_link($thread['tid']);
			eval("\$mostreplies .= \"".$templates->get("stats_thread")."\";");
		//}
	}
}

// Most viewed threads
$most_viewed = $cache->read("most_viewed_threads");

if(!$most_viewed)
{
	$cache->update_most_viewed_threads();
	$most_viewed = $cache->read("most_viewed_threads", true);
}

$mostviews = '';
if(!empty($most_viewed))
{
	foreach($most_viewed as $key => $thread)
	{
		//if(
		//	!in_array($thread['fid'], $unviewableforumsarray) &&
		//	(!in_array($thread['fid'], $onlyusfids) || ($CURUSER['id'] && $thread['uid'] == $CURUSER['id']))
		//)
		//{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$numberbit = ts_nf($thread['views']);
			$numbertype = 'views';
			$thread['threadlink'] = get_thread_link($thread['tid']);
			eval("\$mostviews .= \"".$templates->get("stats_thread")."\";");
		//}
	}
}

$statistics = $cache->read('statistics');

$statscachetime = "0";

$statscachetime = (int)$statscachetime;

if($statscachetime < 1)
{
	$statscachetime = 0;
}

$interval = $statscachetime*3600;

if(!$statistics || $interval == 0 || TIMENOW - $interval > $statistics['time'])
{
	$cache->update_statistics();
	$statistics = $cache->read('statistics');
}

// Top forum
$query = $db->simple_select('tsf_forums', 'fid, name, threads, posts', "type='f'$fidnot", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
$forum = $db->fetch_array($query);

if(!$forum)
{
	$topforum = 'none';
	$topforumposts = 'no';
	$topforumthreads = 'no';
}
else
{
	$forum['name'] = htmlspecialchars_uni(strip_tags($forum['name']));
	$forum['link'] = get_forum_link($forum['fid']);
	eval("\$topforum = \"".$templates->get("stats_topforum")."\";");
	$topforumposts = $forum['posts'];
	$topforumthreads = $forum['threads'];
}

// Top referrer defined for the templates even if we don't use it
$top_referrer = '';
if($mybb->settings['statstopreferrer'] == 1 && isset($statistics['top_referrer']['uid']))
{
	// Only show this if we have anything more the 0 referrals
	if($statistics['top_referrer']['referrals'] > 0)
	{
		$toprefuser = build_profile_link(htmlspecialchars_uni($statistics['top_referrer']['username']), $statistics['top_referrer']['uid']);
		$top_referrer = sprintf($lang->stats['top_referrer'], $toprefuser, ts_nf($statistics['top_referrer']['referrals']));
	}
}

// Today's top poster
if(!isset($statistics['top_poster']['id']))
{
	$topposter = 'nobody';
	$topposterposts = 'no_posts';
}
else
{
	if(!$statistics['top_poster']['id'])
	{
		$topposter = 'guest';
	}
	else
	{
		$topposter = build_profile_link(htmlspecialchars_uni($statistics['top_poster']['username']), $statistics['top_poster']['uid']);
	}

	$topposterposts = $statistics['top_poster']['poststoday'];
}

// What percent of members have posted?
$posters = $statistics['posters'];
$havepostedpercent = ts_nf(round((($posters / $stats['numusers']) * 100), 2)) . "%";

$todays_top_poster = sprintf($lang->stats['todays_top_poster'], $topposter, ts_nf($topposterposts));
$popular_forum = sprintf($lang->stats['popular_forum'], $topforum, ts_nf($topforumposts), ts_nf($topforumthreads));

$stats['numposts'] = ts_nf($stats['numposts']);
$stats['numthreads'] = ts_nf($stats['numthreads']);
$stats['numusers'] = ts_nf($stats['numusers']);
$stats['newest_user'] = build_profile_link($stats['lastusername'], $stats['lastuid']);

$plugins->run_hooks("stats_end");

eval("\$stats = \"".$templates->get("stats")."\";");

stdhead();

build_breadcrumb();

echo $stats;

stdfoot();
