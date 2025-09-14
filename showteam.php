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
define('THIS_SCRIPT', 'showteam.php');

define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');


$templatelist = 'showteam,postbit_email,postbit_pm,showteam_usergroup_user,showteam_usergroup,showteam_moderators_mod,showteam_moderators,showteam_moderators_forum';



require_once 'global.php';

if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

require_once INC_PATH.'/tsf_functions.php';



// Load global language phrases
$lang->load('showteam');



add_breadcrumb($lang->showteam['nav_showteam']);

$plugins->run_hooks('showteam_start');

$timecut = TIMENOW - $wolcutoffmins;



$is_mod = is_mod($usergroups);


$usergroups = $moderators = $users = array();

// Fetch the list of groups which are to be shown on the page
$query = $db->simple_select("usergroups", "gid, title", "showforumteam=1", array('order_by' => 'disporder'));
while($usergroup = $db->fetch_array($query))
{
	$usergroups[$usergroup['gid']] = $usergroup;
}

if(empty($usergroups))
{
	stderr('error_noteamstoshow');
}

// Fetch specific forum moderator details
if($usergroups[5]['gid'])
{
	$query = $db->sql_query("
		SELECT m.*, f.name
		FROM moderators m
		LEFT JOIN users u ON (u.id=m.id)
		LEFT JOIN tsf_forums f ON (f.fid=m.fid)
		WHERE f.active = 1 AND m.isgroup = 0
		ORDER BY u.username
	");
	while($moderator = $db->fetch_array($query))
	{
		$moderators[$moderator['id']][] = $moderator;
	}
}

// Now query the users of those specific groups
$visible_groups = array_keys($usergroups);

$groups_in = implode(",", $visible_groups);
$users_in = implode(",", array_keys($moderators));

if(!$groups_in)
{
	$groups_in = 0;
}

if(!$users_in)
{
	$users_in = 0;
}

$forum_permissions = forum_permissions();
$query_part = '';

// Include additional groups if set to

	foreach($visible_groups as $visible_group)
	{
		if($db->type == "pgsql")
		{
			$query_part .= "'$visible_group' = ANY (string_to_array(additionalgroups, ',')) OR ";
		}
		else if($db->type == "sqlite")
		{
			$query_part .= "'$visible_group' IN (additionalgroups) OR ";
		}
		else
		{
			$query_part .= "FIND_IN_SET('$visible_group', additionalgroups) OR ";
		}
	}


// Include group leaders if set to
if($mybb->settings['showgroupleaders'])
{
	$leaders = $leadlist = $leaderlist = array();

	$query = $db->simple_select("groupleaders", "gid, uid");
	while($leader = $db->fetch_array($query))
	{
		$leaders[$leader['uid']][] = $leader['gid'];
	}

	if(!empty($leaders))
	{
		foreach($leaders as $uid => $gid)
		{
			$leaderlist[$uid] = $gid;
			$leadlist[] = implode(",", $gid);
		}
		$leadlist = implode(",", $leadlist);

		$query = $db->simple_select("usergroups", "gid, title, namestyle", "gid IN ($leadlist)");
		$leadlist = array();

		while($leaded_group = $db->fetch_array($query))
		{
			$leaded_groups[$leaded_group['gid']] = str_replace("{username}",$leaded_group['title'], $leaded_group['namestyle']);
		}

		// Create virtual usergroup container for leaders
		$usergroups[0] = array('gid' => 0, 'title' =>  $lang->group_leaders, 'usertitle' => $lang->group_leaders);
		foreach($leaderlist as $uid => $leaded)
		{
			foreach($leaded as $gid){
				$leadlist[] = $leaded_groups[$gid];
			}
			$usergroups[0]['user_list'][$uid]['leaded'] = implode(", ",$leadlist);
			unset($leadlist);
		}

		$users_in = implode(",", array_keys(array_flip(explode(",", implode(",", array_keys($leaderlist)).",".$users_in))));
	}
}

$query = $db->simple_select("users", "id, username, displaygroup, usergroup, 
additionalgroups, ignorelist, hideemail, receivepms, lastactive, lastvisit, invisible ", $query_part."displaygroup IN ($groups_in) OR (displaygroup='0' AND usergroup IN ($groups_in)) OR id IN ($users_in)", array('order_by' => 'username'));

while($user = $db->fetch_array($query))
{
	// If this user is a moderator
	if(isset($moderators[$user['id']]))
	{
		$forumlist = '';
		foreach($moderators[$user['id']] as $forum)
		{
			if($forum_permissions[$forum['fid']]['canview'] == 1)
			{
				$forum_url = get_forum_link($forum['fid']);
				eval("\$forumlist .= \"".$templates->get("showteam_moderators_forum")."\";");
			}
		}
		$user['forumlist'] = $forumlist;
		$forumlist = '';
		$usergroups[6]['user_list'][$user['id']] = $user;
	}

	if(isset($usergroups[0]['user_list']))
	{
		foreach($usergroups[0]['user_list'] as $uid => $userdetails)
		{
			if($user['uid'] == $uid)
			{
				$user['leaded'] = $usergroups[0]['user_list'][$uid]['leaded'];
				$usergroups[0]['user_list'][$uid] = $user;
			}
		}
	}

	if($user['displaygroup'] == '6' || $user['usergroup'] == '6')
	{
		$usergroups[6]['user_list'][$user['id']] = $user;
	}

	// Are they also in another group which is being shown on the list?
	if($user['displaygroup'] != 0)
	{
		$group = $user['displaygroup'];
	}
	else
	{
		$group = $user['usergroup'];
	}

	if(isset($usergroups[$group]) && $group != 6)
	{
		$usergroups[$group]['user_list'][$user['id']] = $user;
	}

	if($user['additionalgroups'] != '')
	{
		$adgroups = explode(',', $user['additionalgroups']);
		foreach($adgroups as $adgroup)
		{
			if(in_array($adgroup, $visible_groups))
			{
				$usergroups[$adgroup]['user_list'][$user['id']] = $user;
			}
		}
	}
}

// Now we have all of our user details we can display them.
$grouplist = '';
foreach($usergroups as $usergroup)
{
	$usergrouprows = $modrows = '';

	// If we have no users - don't show this group
	if(!isset($usergroup['user_list']))
	{
		continue;
	}

	$bgcolor = '';
	foreach($usergroup['user_list'] as $user)
	{
		$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
		$user['profilelink'] = get_profile_link($user['id']);

		// For the postbit templates
		$post['uid'] = $user['id'];
		$emailcode = $pmcode = '';
		if($user['hideemail'] != 1)
		{
			eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
		}

		if($user['receivepms'] != 0 && my_strpos(",".$user['ignorelist'].",", ",".$CURUSER['id'].",") === false)
		{
			eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
		}

		// For the online image
		if($user['lastactive'] > $timecut && ($user['invisible'] == 0) && $user['lastvisit'] != $user['lastactive'])
		{
			$status = "online";
		}
		

		if($user['invisible'] == 1 && !$is_mod && $user['id'] != $CURUSER['id'])
		{
			if($user['lastactive'])
			{
				$user['lastvisit'] = $lang->global['lastvisit_hidden'];
			}
			else
			{
				$user['lastvisit'] = $lang->global['lastvisit_never'];
			}
		}
		else
		{
			$user['lastvisit'] = my_datee('relative', $user['lastactive']);
		}

		$bgcolor = alt_trow();

		$plugins->run_hooks('showteam_user');

		// If the current group is a moderator group
		if(($usergroup['gid'] == 0 && !empty($user['leaded'])) || ($usergroup['gid'] == 6 && !empty($user['forumlist'])))
		{
			$scopelist = $usergroup['gid'] == 0 ? $user['leaded'] : $user['forumlist'];
			eval("\$modrows .= \"".$templates->get("showteam_moderators_mod")."\";");
		}
		else
		{
			eval("\$usergrouprows .= \"".$templates->get("showteam_usergroup_user")."\";");
		}
	}

	if($modrows && in_array($usergroup['gid'], array(0,6)))
	{
		$modscope = $usergroup['gid'] == 6 ? $lang->mod_forums : $lang->mod_groups;
		eval("\$grouplist .= \"".$templates->get("showteam_moderators")."\";");
	}

	if($usergrouprows)
	{
		eval("\$grouplist .= \"".$templates->get("showteam_usergroup")."\";");
	}
}

if(empty($grouplist))
{
	stderr('error_noteamstoshow');
}

$plugins->run_hooks("showteam_end");



eval("\$showteam = \"".$templates->get("showteam")."\";");



stdhead($lang->showteam['nav_showteam']);


build_breadcrumb();


echo $showteam;

stdfoot();









