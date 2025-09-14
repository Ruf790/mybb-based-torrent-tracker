<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'index2.php');
define("SCRIPTNAME", "index2.php");


$templatelist = "index_logoutlink,footer_contactus,footer_showteamlink,index,index_whosonline,index_whosonline_memberbit,footer,global_dst_detection,index_stats,forumbit_depth1_cat,forumbit_depth2_cat,forumbit_depth2_forum,forumbit_depth1_forum_lastpost,forumbit_depth2_forum_lastpost,forumbit_moderators";
$templatelist .= ",forumbit_depth3,forumbit_depth3_statusicon,index_boardstats,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_viewers";
$templatelist .= ",forumbit_moderators_group,forumbit_moderators_user,forumbit_depth2_forum_lastpost_hidden,forumbit_subforums,forumbit_depth2_forum_unapproved_posts,forumbit_depth2_forum_unapproved_threads";



define ('TSF_FORUMS_TSSEv56', true);
require_once 'global2.php';
if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}




require_once INC_PATH.'/functions_forumlist.php';


$lang->load('index');

$lang->load('usercp');



$plugins->run_hooks('index_start');

$logoutlink = '';
if($CURUSER['id'] != 0)
{
	eval('$logoutlink = "'.$templates->get('index_logoutlink').'";');
}

//$statspage = '';
//if($mybb->settings['statsenabled'] != 0)
//{
//	$stats_page_separator = '';
	//if(!empty($logoutlink))
//	{
//		$stats_page_separator = $lang->board_stats_link_separator;
//	}
//	eval('$statspage = "'.$templates->get('index_statspage').'";');
//}

$onlinecount = null;
$whosonline = '';

	$wolorder = "username";
	
	// Get the online users.
	if($wolorder == 'username')
	{
		$order_by = 'u.username ASC';
		$order_by2 = 's.time DESC';
	}
	else
	{
		$order_by = 's.time DESC';
		$order_by2 = 'u.username ASC';
	}

	$timesearch = TIMENOW - (int)$wolcutoffmins;

	$membercount = $guestcount = $anoncount = $botcount = 0;
	$forum_viewers = $doneusers = $onlinemembers = $onlinebots = array();

	//if($mybb->settings['showforumviewing'] != 0)
	//{
		$query = $db->sql_query("
			SELECT
				location1, COUNT(DISTINCT ip) AS guestcount
			FROM
				sessions
			WHERE uid = 0 AND location1 != 0 AND SUBSTR(sid,4,1) != '=' AND time > $timesearch
			GROUP BY location1
		");

		while($location = $db->fetch_array($query))
		{
			if(isset($forum_viewers[$location['location1']]))
			{
				$forum_viewers[$location['location1']] += $location['guestcount'];
			}
			else
			{
				$forum_viewers[$location['location1']] = $location['guestcount'];
			}
		}
	//}

	$query = $db->simple_select("sessions", "COUNT(DISTINCT ip) AS guestcount", "uid = 0 AND SUBSTR(sid,4,1) != '=' AND time > $timesearch");
	$guestcount = $db->fetch_field($query, "guestcount");

	$query = $db->query("
		SELECT
			s.sid, s.ip, s.uid, s.time, s.location, s.location1, u.username, u.invisible, u.usergroup, u.displaygroup
		FROM
			sessions s
			LEFT JOIN users u ON (s.uid=u.id)
		WHERE (s.uid != 0 OR SUBSTR(s.sid,4,1) = '=') AND s.time > $timesearch
		ORDER BY {$order_by}, {$order_by2}
	");

	// Fetch spiders
	$spiders = $cache->read('spiders');

	// Loop through all users and spiders.
	while($user = $db->fetch_array($query))
	{
		// Create a key to test if this user is a search bot.
		$botkey = my_strtolower(str_replace('bot=', '', $user['sid']));

		// Decide what type of user we are dealing with.
		if($user['uid'] > 0)
		{
			// The user is registered.
			if(empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time'])
			{
				// If the user is logged in anonymously, update the count for that.
				if($user['invisible'] == 1)
				{
					++$anoncount;
				}
				++$membercount;
				//if($user['invisible'] != 1 || $user['uid'] == $CURUSER['id'])
				if($user['invisible'] != 1 || $usergroups['issupermod'] == 'yes' || $user['uid'] == $CURUSER['id'])
				{
					// If this usergroup can see anonymously logged-in users, mark them.
					if($user['invisible'] == 1)
					{
						$invisiblemark = '*';
					}
					else
					{
						$invisiblemark = '';
					}

					// Properly format the username and assign the template.
					$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
					
					//$onlinemembers[] = ''.$user['profilelink'].''.$invisiblemark.'';
					
					eval('$onlinemembers[] = "'.$templates->get('index_whosonline_memberbit', 1, 0).'";');
					
				}
				// This user has been handled.
				$doneusers[$user['uid']] = $user['time'];
			}
		}
		elseif(my_strpos($user['sid'], 'bot=') !== false && $spiders[$botkey] && $mybb->settings['woldisplayspiders'] == 1)
		{
			if($wolorder == 'username')
			{
				$key = $spiders[$botkey]['name'];
			}
			else
			{
				$key = $user['time'];
			}

			// The user is a search bot.
			$onlinebots[$key] = format_name($spiders[$botkey]['name'], $spiders[$botkey]['usergroup']);
			++$botcount;
		}

		if($user['location1'])
		{
			if(isset($forum_viewers[$user['location1']]))
			{
				++$forum_viewers[$user['location1']];
			}
			else
			{
				$forum_viewers[$user['location1']] = 1;
			}
		}
	}

	if($wolorder == 'activity')
	{
		// activity ordering is DESC, username is ASC
		krsort($onlinebots);
	}
	else
	{
		ksort($onlinebots);
	}

	$onlinemembers = array_merge($onlinebots, $onlinemembers);
	if(!empty($onlinemembers))
	{
		$comma = $lang->comma." ";
		$onlinemembers = implode($comma, $onlinemembers);
	}
	else
	{
		$onlinemembers = "";
	}

	// Build the who's online bit on the index page.
	$onlinecount = $membercount + $guestcount + $botcount;

	if($onlinecount != 1)
	{
		$onlinebit = 'users';
	}
	else
	{
		$onlinebit = 'user';
	}
	if($membercount != 1)
	{
		$memberbit = 'members';
	}
	else
	{
		$memberbit = 'member';
	}
	if($anoncount != 1)
	{
		$anonbit = 'are';
	}
	else
	{
		$anonbit = 'is';
	}
	if($guestcount != 1)
	{
		$guestbit = 'guests';
	}
	else
	{
		$guestbit = 'guest';
	}
	$online_note = sprintf(''.ts_nf($onlinecount).' '.$onlinebit.' active in the past '.$wolcutoffmins.' minutes ('.ts_nf($membercount).' '.$memberbit.', '.ts_nf($anoncount).' of whom '.$anonbit.' invisible, and '.ts_nf($guestcount).' '.$guestbit.').');
	
	//$whosonline = ''.$online_note.'<div class="mt-2">'.$onlinemembers.'</div>';
	
	eval('$whosonline = "'.$templates->get('index_whosonline').'";');


// Build the birthdays for to show on the index page.
$bdays = $birthdays = '';

	// First, see what day this is.
	$bdaycount = $bdayhidden = 0;
	$bdaydate = my_datee('j-n', TIMENOW, '', 0);
	$year = my_datee('Y', TIMENOW, '', 0);

	$bdaycache = $cache->read('birthdays');

	if(!is_array($bdaycache))
	{
		$cache->update_birthdays();
		$bdaycache = $cache->read('birthdays');
	}

	$hiddencount = 0;
	$today_bdays = array();
	if(isset($bdaycache[$bdaydate]))
	{
		if(isset($bdaycache[$bdaydate]['hiddencount']))
		{
			$hiddencount = $bdaycache[$bdaydate]['hiddencount'];
		}
		if(isset($bdaycache[$bdaydate]['users']))
		{
			$today_bdays = $bdaycache[$bdaydate]['users'];
		}
	}

	$comma = '';
	if(!empty($today_bdays))
	{
		$showbirthdayspostlimit = "0";
		
		if((int)$showbirthdayspostlimit > 0)
		{
			$bdayusers = array();
			foreach($today_bdays as $key => $bdayuser_pc)
			{
				$bdayusers[$bdayuser_pc['id']] = $key;
			}

			if(!empty($bdayusers))
			{
				// Find out if our users have enough posts to be seen on our birthday list
				$bday_sql = implode(',', array_keys($bdayusers));
				$query = $db->simple_select('users', 'id, postnum', "id IN ({$bday_sql})");

				
				
				while($bdayuser = $db->fetch_array($query))
				{
					$showbirthdayspostlimit = "0";
					
					if($bdayuser['postnum'] < $showbirthdayspostlimit)
					{
						unset($today_bdays[$bdayusers[$bdayuser['id']]]);
					}
				}
			}
		}

		// We still have birthdays - display them in our list!
		if(!empty($today_bdays))
		{
			foreach($today_bdays as $bdayuser)
			{
				if($bdayuser['displaygroup'] == 0)
				{
					$bdayuser['displaygroup'] = $bdayuser['usergroup'];
				}

				// If this user's display group can't be seen in the birthday list, skip it
				if(isset($groupscache[$bdayuser['displaygroup']]))
				{
					continue;
				}

				$age = '';
				$bday = explode('-', $bdayuser['birthday']);
				if($year > $bday['2'] && $bday['2'] != '')
				{
					$age = ' ('.($year - $bday['2']).')';
				}

				$bdayuser['username'] = format_name(htmlspecialchars_uni($bdayuser['username']), $bdayuser['usergroup'], $bdayuser['displaygroup']);
				$bdayuser['profilelink'] = build_profile_link($bdayuser['username'], $bdayuser['id']);
				
				$bdays .= ''.$comma.''.$bdayuser['profilelink'].''.$age.'';
				
				
				++$bdaycount;
				$comma = 'comma';
			}
		}
	}

	if($hiddencount > 0)
	{
		if($bdaycount > 0)
		{
			$bdays .= ' - ';
		}

		$bdays .= "{$hiddencount} {$lang->birthdayhidden}";
	}

	// If there are one or more birthdays, show them.
	if($bdaycount > 0 || $hiddencount > 0)
	{
		$birthdays = '<div class="row mt-3">
<div class="col align-self-center text-desc">
	<i class="fa-solid fa-cake-candles"></i> {$lang->todays_birthdays} - {$bdays}
</div>
</div>';
	
	}


// Build the forum statistics to show on the index page.
$forumstats = '';


// Show the board statistics table only if one or more index statistics are enabled.
$boardstats = '';

	
	// First, load the stats cache.
	$stats = $cache->read('stats');

	// Check who's the newest member.
	if(!$stats['lastusername'])
	{
		$newestmember = 'nobody';
	}
	else
	{
		$newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
	}

	// Format the stats language.
	$stats_posts_threads = sprintf($lang->tsf_forums['stats_posts_threads'], ts_nf($stats['numposts']), ts_nf($stats['numthreads']));
	$stats_numusers = sprintf($lang->tsf_forums['stats_numusers'], ts_nf($stats['numusers']));
	$stats_newestuser = sprintf($lang->tsf_forums['stats_newestuser'], $newestmember);
	

	
	if (file_exists(TSDIR.'/cache/onlinestats.php'))
    {
	    include_once(TSDIR.'/cache/onlinestats.php');
    }

    if(!$onlinestats['most_ever'])
    {
	    $onlinestats['most_ever'] = 0;
    }
	
	
	$stats_mostonline = sprintf($lang->index['online'], ts_nf($onlinestats['most_ever']), my_datee($dateformat, $onlinestats['most_ever_time']), my_datee($timeformat, $onlinestats['most_ever_time']));

	
	

    
	eval('$forumstats = "'.$templates->get('index_stats').'";');
	
	
	eval('$boardstats = "'.$templates->get('index_boardstats').'";');
	
	
	


if($CURUSER['id'] == 0)
{
	// Build a forum cache.
	$query = $db->simple_select('tsf_forums', '*', 'active!=0', array('order_by' => 'pid, disporder'));

	$forumsread = array();
	if(isset($mybb->cookies['mybb']['forumread']))
	{
		$forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
	}
}
else
{
	// Build a forum cache.
	$query = $db->sql_query("
		SELECT f.*, fr.dateline AS lastread
		FROM tsf_forums f
		LEFT JOIN tsf_forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$CURUSER['id']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");
}

while($forum = $db->fetch_array($query))
{
	if($CURUSER['id'] == 0)
	{
		if(!empty($forumsread[$forum['fid']]))
		{
			$forum['lastread'] = $forumsread[$forum['fid']];
		}
	}
	$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}
$forumpermissions = forum_permissions();

// Get the forum moderators if the setting is enabled.
$moderatorcache = array();
//if($mybb->settings['modlist'] != 0 && $mybb->settings['modlist'] != 'off')
//{
	$moderatorcache = $cache->read('moderators');
//}

$excols = 'index';
$permissioncache = null;
$bgcolor = 'trow1';

// Decide if we're showing first-level subforums on the index page.
$showdepth = 2;

$subforumsindex = "2";

if($subforumsindex != 0)
{
	$showdepth = 3;
}

$forum_list = build_forumbits();
$forums = $forum_list['forum_list'];

$plugins->run_hooks('index_end');


// DST Auto detection enabled?
$auto_dst_detection = '';
if($CURUSER['id'] > 0 && $CURUSER['dstcorrection'] == 2)
{
	$timezone = (float)$CURUSER['timezone'] + $CURUSER['dst'];
	eval('$auto_dst_detection = "'.$templates->get('global_dst_detection').'";');
}


$showteamlink = '';

eval('$showteamlink = "'.$templates->get('footer_showteamlink').'";');


// If we use the contact form, show 'Contact Us' link when appropriate
$contact_us = '';

$contactlink = "contact.php";


if(!my_validate_url($contactlink, true) && my_substr($contactlink, 0, 7) != 'mailto:')
{
	$contactlink = $BASEURL.'/'.$contactlink;
}

eval('$contact_us = "'.$templates->get('footer_contactus').'";');



eval('$footer = "'.$templates->get('footer').'";');


eval('$index = "'.$templates->get('index').'";');



stdhead ('' . $SITENAME . ' FORUMS');

echo $index;



stdfoot();