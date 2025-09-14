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
define('THIS_SCRIPT', 'online.php');
define("SCRIPTNAME", "online.php");

$templatelist = "online,online_row,online_row_ip,online_today,online_today_row,online_row_ip_lookup,online_refresh,multipage,multipage_end,multipage_start";
$templatelist .= ",multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage";

define ('TSF_FORUMS_TSSEv56', true);

require_once 'global2.php';

  
if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
   exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}


require_once INC_PATH . '/functions_multipage.php';

require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/functions_online.php";






// Load global language phrases
$lang->load("online");

//if($usergroups['cansettingspanel'] == 'no')
//{
	//error_no_permission();
//}

// Make navigation
add_breadcrumb($lang->online['nav_online'], "online.php");

if($mybb->get_input('action') == "today")
{
	add_breadcrumb($lang->online['nav_onlinetoday']);

	$plugins->run_hooks("online_today_start");

	$threshold = TIMENOW-(60*60*24);
	$query = $db->simple_select("users", "COUNT(id) AS users", "lastactive > '{$threshold}'");
	$todaycount = $db->fetch_field($query, "users");

	$query = $db->simple_select("users", "COUNT(id) AS users", "lastactive > '{$threshold}' AND invisible = '1'");
	$invis_count = $db->fetch_field($query, "users");

	
	$wolusersperpage = "20";
	
	if(!$wolusersperpage || (int)$wolusersperpage < 1)
	{
		$wolusersperpage = 20;
	}

	// Add pagination
	$perpage = $wolusersperpage;

	if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($page-1) * $perpage;
		$pages = ceil($todaycount / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$query = $db->simple_select("users", "*", "lastactive > '{$threshold}'", array("order_by" => "lastactive", "order_dir" => "desc", "limit" => $perpage, "limit_start" => $start));

	$todayrows = '';
	while($online = $db->fetch_array($query))
	{
		$invisiblemark = '';
		//if($online['invisible'] == 1 && $mybb->usergroup['canbeinvisible'] == 1)
		if($online['invisible'] == 1)
		{
			$invisiblemark = "*";
		}

		//if($online['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $online['uid'] == $CURUSER['id'])
		if($online['invisible'] != 1 || $usergroups['canmassdelete'] == 'yes' || $online['id'] == $CURUSER['id'])
		{
			$username = format_name(htmlspecialchars_uni($online['username']), $online['usergroup'], $online['displaygroup']);
			$online['profilelink'] = build_profile_link($username, $online['id']);
			$onlinetime = my_datee('normal', $online['lastactive']);

			eval("\$todayrows .= \"".$templates->get("online_today_row")."\";");
				
		}
	}

	$multipage = multipage($todaycount, $perpage, $page, "online.php?action=today");

	$todaycount = ts_nf($todaycount);
	$invis_count = ts_nf($invis_count);

	if($todaycount == 1)
	{
		$onlinetoday = $lang->online['member_online_today'];
	}
	else
	{
		$onlinetoday = sprintf($lang->online['members_were_online_today'], $todaycount);
	}

	if($invis_count)
	{
		$string = $lang->online['members_online_hidden'];

		if($invis_count == 1)
		{
			$string = $lang->online['member_online_hidden'];
		}

		$onlinetoday .= sprintf($string, $invis_count);
	}

	$plugins->run_hooks("online_today_end");

	stdhead($lang->online['online_today']);
	
	build_breadcrumb ();
	
	eval("\$today = \"".$templates->get("online_today")."\";");
	
	echo $today;
	
	stdfoot();
	
	
}
else
{
	$plugins->run_hooks("online_start");

	// Custom sorting options
	if($mybb->get_input('sortby') == "username")
	{
		$sql = "u.username ASC, s.time DESC";
		$refresh_string = "?sortby=username";
	}
	elseif($mybb->get_input('sortby') == "location")
	{
		$sql = "s.location, s.time DESC";
		$refresh_string = "?sortby=location";
	}
	// Otherwise sort by last refresh
	else
	{
		switch($db->type)
		{
			case "sqlite":
			case "pgsql":
				$sql = "CASE WHEN s.uid > 0 THEN 1 ELSE 0 END DESC, s.time DESC";
				break;
			default:
				$sql = "IF( s.uid >0, 1, 0 ) DESC, s.time DESC";
				break;
		}
		$refresh_string = '';
	}

	$timesearch = TIMENOW - $wolcutoffmins*60;

	$query = $db->sql_query("
		SELECT COUNT(*) AS online FROM (
			SELECT 1
			FROM sessions
			WHERE time > $timesearch
			GROUP BY uid, ip
		) s
	");

	$online_count = $db->fetch_field($query, "online");

	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	// How many pages are there?
	$perpage = $f_threadsperpage;

	if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
		$start = ($page-1) * $perpage;
		$pages = ceil($online_count / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	// Assemble page URL
	$multipage = multipage($online_count, $perpage, $page, "online.php".$refresh_string);

	// Query for active sessions
	$dbversion = $db->get_version();
	if(
		(
			$db->type == 'mysqli' && (
				version_compare($dbversion, '10.2.0', '>=') || ( // MariaDB
					version_compare($dbversion, '10', '<') &&
					version_compare($dbversion, '8.0.2', '>=')
				)
			)
		) ||
		($db->type == 'pgsql' && version_compare($dbversion, '8.4.0', '>=')) ||
		($db->type == 'sqlite' && version_compare($dbversion, '3.25.0', '>='))
	)
	{
		$sql = str_replace('u.username', 's.username', $sql);

		$query = $db->sql_query("
			SELECT * FROM (
				SELECT
					s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup,
					row_number() OVER (PARTITION BY s.uid, s.ip ORDER BY time DESC) AS row_num
				FROM
					sessions s
					LEFT JOIN users u ON (s.uid = u.id)
				WHERE s.time > $timesearch
			) s
			WHERE row_num = 1
			ORDER BY $sql
			LIMIT {$start}, {$perpage}
		");
	}
	else
	{
		$query = $db->sql_query("
			SELECT
				s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup
			FROM
				sessions s
				INNER JOIN (
					SELECT
						MIN(s2.sid) AS sid
					FROM
						sessions s2
						LEFT JOIN sessions s3 ON (s2.sid = s3.sid AND s2.time < s3.time)
					WHERE s2.time > $timesearch AND s3.sid IS NULL
					GROUP BY s2.uid, s2.ip
				) s2 ON (s.sid = s2.sid)
				LEFT JOIN users u ON (s.uid = u.id)
			ORDER BY $sql
			LIMIT {$start}, {$perpage}
		");
	}

	// Fetch spiders
	$spiders = $cache->read("spiders");

	while($user = $db->fetch_array($query))
	{
		$plugins->run_hooks("online_user");

		// Fetch the WOL activity
		$user['activity'] = fetch_wol_activity($user['location'], $user['nopermission']);

		$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

		// Have a registered user
		if($user['uid'] > 0)
		{
			if(empty($users[$user['uid']]) || $users[$user['uid']]['time'] < $user['time'])
			{
				$users[$user['uid']] = $user;
			}
		}
		// Otherwise this session is a bot
		else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
		{
			$user['bot'] = $spiders[$botkey]['name'];
			$user['usergroup'] = $spiders[$botkey]['usergroup'];
			$guests[] = $user;
		}
		// Or a guest
		else
		{
			$guests[] = $user;
		}
	}

	// Now we build the actual online rows - we do this separately because we need to query all of the specific activity and location information
	$online_rows = '';
	if(isset($users) && is_array($users))
	{
		reset($users);
		foreach($users as $user)
		{
			$online_rows .= build_wol_row($user);
		}
	}
	if(isset($guests) && is_array($guests))
	{
		reset($guests);
		foreach($guests as $user)
		{
			$online_rows .= build_wol_row($user);
		}
	}

	// Fetch the most online information
	$most_online = $cache->read("mostonline");
	$record_count = $most_online['numusers'];
	$record_date = my_datee('relative', $most_online['time']);

	// Set automatic refreshing if enabled
	$refreshwol = "1";
	
	if($refreshwol > 0)
	{
		$refresh_time = $refreshwol * 60;
		
		eval("\$refresh = \"".$templates->get("online_refresh")."\";");
	}

	$plugins->run_hooks("online_end");

    stdhead($lang->online['users_online']);
	
	build_breadcrumb ();
	
	eval("\$online = \"".$templates->get("online")."\";");
	
	echo $online;
	
	stdfoot();
}
