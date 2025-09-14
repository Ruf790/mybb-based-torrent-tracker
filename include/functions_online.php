<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */
 
function get_inactive_forums2()
{
	global $forum_cache, $cache;

	if(!$forum_cache)
	{
		cache_forums();
	}

	$inactive = array();

	foreach($forum_cache as $fid => $forum)
	{
		if($forum['active'] == 0)
		{
			$inactive[] = $fid;
			foreach($forum_cache as $fid1 => $forum1)
			{
				if(my_strpos(",".$forum1['parentlist'].",", ",".$fid.",") !== false && !in_array($fid1, $inactive))
				{
					$inactive[] = $fid1;
				}
			}
		}
	}

	$inactiveforums = implode(",", $inactive);

	return $inactiveforums;
}

 

 
function get_announcement_link2($aid=0)
{
	$link = str_replace("{aid}", $aid, ANNOUNCEMENT_URL);
	return htmlspecialchars_uni($link);
}
  
function get_forum_link2($fid, $page=0)
{
	if($page > 0)
	{
		$link = str_replace("{fid}", $fid, FORUM_URL_PAGED);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{fid}", $fid, FORUM_URL);
		return htmlspecialchars_uni($link);
	}
}
 
function get_thread_link2($tid, $page=0, $action='')
{
	if($page > 1)
	{
		if($action)
		{
			$link = THREAD_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = THREAD_URL_PAGED;
		}
		$link = str_replace("{tid}", $tid, $link);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		if($action)
		{
			$link = THREAD_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = THREAD_URL;
		}
		$link = str_replace("{tid}", $tid, $link);
		return htmlspecialchars_uni($link);
	}
}
 
function fetch_wol_activity($location, $nopermission=false)
{
	global $uid_list, $aid_list, $pid_list, $tid_list, $id_list, $fid_list, $ann_list, $eid_list, $plugins, $user, $parameters;

	$user_activity = array();

	$split_loc = explode(".php", $location);
	if(isset($user['location']) && $split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}
	$parameters = array();
	if(isset($split_loc[1]))
	{
		$temp = explode("&amp;", my_substr($split_loc[1], 1));
		foreach($temp as $param)
		{
			$temp2 = explode("=", $param, 2);
			if(isset($temp2[1]))
			{
				$parameters[$temp2[0]] = $temp2[1];
			}
		}
	}

	if($nopermission)
	{
		$filename = "nopermission";
	}

	switch($filename)
	{
		case "announcements":
			if(!isset($parameters['aid']))
			{
				$parameters['aid'] = 0;
			}
			$parameters['aid'] = (int)$parameters['aid'];
			if($parameters['aid'] > 0)
			{
				$ann_list[$parameters['aid']] = $parameters['aid'];
			}
			$user_activity['activity'] = "announcements";
			$user_activity['ann'] = $parameters['aid'];
			break;
		case "attachment":
			if(!isset($parameters['aid']))
			{
				$parameters['aid'] = 0;
			}
			$parameters['aid'] = (int)$parameters['aid'];
			if($parameters['aid'] > 0)
			{
				$aid_list[] = $parameters['aid'];
			}
			$user_activity['activity'] = "attachment";
			$user_activity['aid'] = $parameters['aid'];
			break;
		case "calendar":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "event")
			{
				if(!isset($parameters['eid']))
				{
					$parameters['eid'] = 0;
				}
				$parameters['eid'] = (int)$parameters['eid'];
				if($parameters['eid'] > 0)
				{
					$eid_list[$parameters['eid']] = $parameters['eid'];
				}
				$user_activity['activity'] = "calendar_event";
				$user_activity['eid'] = $parameters['eid'];
			}
			elseif($parameters['action'] == "addevent" || $parameters['action'] == "do_addevent")
			{
				$user_activity['activity'] = "calendar_addevent";
			}
			elseif($parameters['action'] == "editevent" || $parameters['action'] == "do_editevent")
			{
				$user_activity['activity'] = "calendar_editevent";
			}
			else
			{
				$user_activity['activity'] = "calendar";
			}
			break;
		case "contact":
			$user_activity['activity'] = "contact";
			break;
		case "editpost":
			$user_activity['activity'] = "editpost";
			break;
		case "forumdisplay":
			if(!isset($parameters['fid']))
			{
				$parameters['fid'] = 0;
			}
			$parameters['fid'] = (int)$parameters['fid'];
			if($parameters['fid'] > 0)
			{
				$fid_list[$parameters['fid']] = $parameters['fid'];
			}
			$user_activity['activity'] = "forumdisplay";
			$user_activity['fid'] = $parameters['fid'];
			break;
		
	
		
		case "index":
		case '':
			$user_activity['activity'] = "index";
			break;
			
			
		case "index2":
		case '':
			$user_activity['activity'] = "index2";
			break;
			
			
			
		case "upload":
		case '':
			$user_activity['activity'] = "upload";
			break;
			
		//case "download":
		//case '':
			//$user_activity['activity'] = "download";
			//break;
			
		case "browse":
		case '':
			$user_activity['activity'] = "browse";
			break;
			
		case "managegroup":
			$user_activity['activity'] = "managegroup";
			break;
		case "userdetails":
				$user_activity['activity'] = "userdetails";

				if(!isset($parameters['id']))
				{
					$parameters['id'] = 0;
				}
				$parameters['id'] = (int)$parameters['id'];

				if($parameters['id'] == 0)
				{
					global $userid;

					// $user is available in Who's Online but not in Member Profile, use $memprofile instead
					if(!empty($user['id']))
					{
						$parameters['id'] = $user['id'];
					}
					elseif(!empty($userid['id']))
					{
						$parameters['id'] = $userid['id'];
					}
				}

				if($parameters['id'] > 0)
				{
					$uid_list[$parameters['id']] = $parameters['id'];
				}
				$user_activity['id'] = $parameters['id'];
			
			
				//$user_activity['activity'] = "userdetails";
			
			break;
		case "users":
			$user_activity['activity'] = "users";
			break;
			
		
        case "member":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "activate")
			{
				$user_activity['activity'] = "member_activate";
			}
			elseif($parameters['action'] == "register" || $parameters['action'] == "do_register")
			{
				$user_activity['activity'] = "member_register";
			}
			elseif($parameters['action'] == "login" || $parameters['action'] == "do_login")
			{
				$user_activity['activity'] = "member_login";
			}
			elseif($parameters['action'] == "logout")
			{
				$user_activity['activity'] = "member_logout";
			}
			elseif($parameters['action'] == "profile")
			{
				$user_activity['activity'] = "member_profile";

				if(!isset($parameters['id']))
				{
					$parameters['id'] = 0;
				}
				$parameters['id'] = (int)$parameters['id'];

				if($parameters['id'] == 0)
				{
					global $memprofile;

					// $user is available in Who's Online but not in Member Profile, use $memprofile instead
					if(!empty($user['id']))
					{
						$parameters['id'] = $user['id'];
					}
					elseif(!empty($memprofile['id']))
					{
						$parameters['id'] = $memprofile['id'];
					}
				}

				if($parameters['id'] > 0)
				{
					$uid_list[$parameters['id']] = $parameters['id'];
				}
				$user_activity['id'] = $parameters['id'];
			}
			elseif($parameters['action'] == "emailuser" || $parameters['action'] == "do_emailuser")
			{
				$user_activity['activity'] = "member_emailuser";
			}
			elseif($parameters['action'] == "rate" || $parameters['action'] == "do_rate")
			{
				$user_activity['activity'] = "member_rate";
			}
			elseif($parameters['action'] == "resendactivation" || $parameters['action'] == "do_resendactivation")
			{
				$user_activity['activity'] = "member_resendactivation";
			}
			elseif($parameters['action'] == "lostpw" || $parameters['action'] == "do_lostpw" || $parameters['action'] == "resetpassword")
			{
				$user_activity['activity'] = "member_lostpw";
			}
			else
			{
				$user_activity['activity'] = "member";
			}
			break;		
			
			
			
			
			
			
			
			
		case "misc":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			$accepted_parameters = array("markread", "help", "buddypopup", "smilies", "syndication", "dstswitch");
			if($parameters['action'] == "whoposted")
			{
				if(!isset($parameters['tid']))
				{
					$parameters['tid'] = 0;
				}
				$parameters['tid'] = (int)$parameters['tid'];
				if($parameters['tid'] > 0)
				{
					$tid_list[$parameters['tid']] = $parameters['tid'];
				}
				$user_activity['activity'] = "misc_whoposted";
				$user_activity['tid'] = $parameters['tid'];
			}
			elseif(in_array($parameters['action'], $accepted_parameters))
			{
				$user_activity['activity'] = "misc_".$parameters['action'];
			}
			else
			{
				$user_activity['activity'] = "misc";
			}
			break;
		case "modcp":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}

			$accepted_parameters = array("modlogs", "announcements", "finduser", "warninglogs", "ipsearch");

			foreach($accepted_parameters as $action)
			{
				if($parameters['action'] == $action)
				{
					$user_activity['activity'] = "modcp_".$action;
					break;
				}
			}

			$accepted_parameters = array();
			$accepted_parameters['report'] = array("do_reports", "reports", "allreports");
			$accepted_parameters['new_announcement'] = array("do_new_announcement", "new_announcement");
			$accepted_parameters['delete_announcement'] = array("do_delete_announcement", "delete_announcement");
			$accepted_parameters['edit_announcement'] = array("do_edit_announcement", "edit_announcement");
			$accepted_parameters['mod_queue'] = array("do_modqueue", "modqueue");
			$accepted_parameters['editprofile'] = array("do_editprofile", "editprofile");
			$accepted_parameters['banning'] = array("do_banuser", "banning", "liftban", "banuser");

			foreach($accepted_parameters as $name => $actions)
			{
				if(in_array($parameters['action'], $actions))
				{
					$user_activity['activity'] = "modcp_".$name;
					break;
				}
			}

			if(empty($user_activity['activity']))
			{
				$user_activity['activity'] = "modcp";
			}
			break;
		case "moderation":
			$user_activity['activity'] = "moderation";
			break;
		case "newreply":
			if(!isset($parameters['tid']))
			{
				$parameters['tid'] = 0;
			}
			$parameters['tid'] = (int)$parameters['tid'];
			if($parameters['tid'] > 0)
			{
				$tid_list[$parameters['tid']] = $parameters['tid'];
			}
			$user_activity['activity'] = "newreply";
			$user_activity['tid'] = $parameters['tid'];
			break;
		case "newthread":
			if(!isset($parameters['fid']))
			{
				$parameters['fid'] = 0;
			}
			$parameters['fid'] = (int)$parameters['fid'];
			if($parameters['fid'] > 0)
			{
				$fid_list[$parameters['fid']] = $parameters['fid'];
			}
			$user_activity['activity'] = "newthread";
			$user_activity['fid'] = $parameters['fid'];
			break;
		case "online":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "today")
			{
				$user_activity['activity'] = "woltoday";
			}
			else
			{
				$user_activity['activity'] = "wol";
			}
			break;
		case "polls":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			// Make the "do" parts the same as the other one.
			if($parameters['action'] == "do_newpoll")
			{
				$user_activity['activity'] = "newpoll";
			}
			elseif($parameters['action'] == "do_editpoll")
			{
				$user_activity['activity'] = "editpoll";
			}
			else
			{
				$accepted_parameters = array("do_editpoll", "editpoll", "newpoll", "do_newpoll", "showresults", "vote");

				foreach($accepted_parameters as $action)
				{
					if($parameters['action'] == $action)
					{
						$user_activity['activity'] = $action;
						break;
					}
				}

				if(empty($user_activity['activity']))
				{
					$user_activity['activity'] = "showresults";
				}
			}
			break;
		case "printthread":
			if(!isset($parameters['tid']))
			{
				$parameters['tid'] = 0;
			}
			$parameters['tid'] = (int)$parameters['tid'];
			if($parameters['tid'] > 0)
			{
				$tid_list[$parameters['tid']] = $parameters['tid'];
			}
			$user_activity['activity'] = "printthread";
			$user_activity['tid'] = $parameters['tid'];
			break;
		case "private":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "send" || $parameters['action'] == "do_send")
			{
				$user_activity['activity'] = "private_send";
			}
			elseif($parameters['action'] == "read")
			{
				$user_activity['activity'] = "private_read";
			}
			elseif($parameters['action'] == "folders" || $parameters['action'] == "do_folders")
			{
				$user_activity['activity'] = "private_folders";
			}
			else
			{
				$user_activity['activity'] = "private";
			}
			break;
		case "ratethread":
			$user_activity['activity'] = "ratethread";
			break;
		case "report":
			$user_activity['activity'] = "report";
			break;
		case "reputation":
            if(!isset($parameters['action']))
            {
                $parameters['action'] = '';
            }
			if(!isset($parameters['uid']))
			{
				$parameters['uid'] = 0;
			}
			$parameters['uid'] = (int)$parameters['uid'];
			if($parameters['uid'] > 0)
			{
				$uid_list[$parameters['uid']] = $parameters['uid'];
			}
			$user_activity['uid'] = $parameters['uid'];

			if($parameters['action'] == "add")
			{
				$user_activity['activity'] = "reputation";
			}
			else
			{
				$user_activity['activity'] = "reputation_report";
			}
			break;
		case "tsf_search":
			$user_activity['activity'] = "tsf_search";
			break;
		case "sendthread":
			if(!isset($parameters['tid']))
			{
				$parameters['tid'] = 0;
			}
			$parameters['tid'] = (int)$parameters['tid'];
			if($parameters['tid'] > 0)
			{
				$tid_list[$parameters['tid']] = $parameters['tid'];
			}
			$user_activity['activity'] = "sendthread";
			$user_activity['tid'] = $parameters['tid'];
		break;
		case "showteam":
			$user_activity['activity'] = "showteam";
			break;
		case "showthread":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if(!isset($parameters['pid']))
			{
				$parameters['pid'] = 0;
			}
			$parameters['pid'] = (int)$parameters['pid'];
			if($parameters['pid'] > 0 && $parameters['action'] == "showpost")
			{
				$pid_list[$parameters['pid']] = $parameters['pid'];
				$user_activity['activity'] = "showpost";
				$user_activity['pid'] = $parameters['pid'];
			}
			else
			{
				if(!isset($parameters['page']))
				{
					$parameters['page'] = 0;
				}
				$parameters['page'] = (int)$parameters['page'];
				$user_activity['page'] = $parameters['page'];
				if(!isset($parameters['tid']))
				{
					$parameters['tid'] = 0;
				}
				$parameters['tid'] = (int)$parameters['tid'];
				if($parameters['tid'] > 0)
				{
					$tid_list[$parameters['tid']] = $parameters['tid'];
				}
				$user_activity['activity'] = "showthread";
				$user_activity['tid'] = $parameters['tid'];
			}
			break;
			
		case "details":
			if(!isset($parameters['id']))
			{
				$parameters['id'] = 0;
			}
			$parameters['id'] = (int)$parameters['id'];
			if($parameters['id'] > 0)
			{
				$id_list[$parameters['id']] = $parameters['id'];
			}
			$user_activity['activity'] = "details";
			$user_activity['id'] = $parameters['id'];
			break;
			
		case "download":
			if(!isset($parameters['id']))
			{
				$parameters['id'] = 0;
			}
			$parameters['id'] = (int)$parameters['id'];
			if($parameters['id'] > 0)
			{
				$id_list[$parameters['id']] = $parameters['id'];
			}
			$user_activity['activity'] = "download";
			$user_activity['id'] = $parameters['id'];
			break;
			

		case "stats":
			$user_activity['activity'] = "stats";
			break;
		case "usercp":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "profile" || $parameters['action'] == "do_profile")
			{
				$user_activity['activity'] = "usercp_profile";
			}
			elseif($parameters['action'] == "options" || $parameters['action'] == "do_options")
			{
				$user_activity['activity'] = "usercp_options";
			}
			elseif($parameters['action'] == "password" || $parameters['action'] == "do_password")
			{
				$user_activity['activity'] = "usercp_password";
			}
			elseif($parameters['action'] == "editsig" || $parameters['action'] == "do_editsig")
			{
				$user_activity['activity'] = "usercp_editsig";
			}
			elseif($parameters['action'] == "avatar" || $parameters['action'] == "do_avatar")
			{
				$user_activity['activity'] = "usercp_avatar";
			}
			elseif($parameters['action'] == "editlists" || $parameters['action'] == "do_editlists")
			{
				$user_activity['activity'] = "usercp_editlists";
			}
			elseif($parameters['action'] == "favorites")
			{
				$user_activity['activity'] = "usercp_favorites";
			}
			elseif($parameters['action'] == "subscriptions")
			{
				$user_activity['activity'] = "usercp_subscriptions";
			}
			elseif($parameters['action'] == "addfavorite" || $parameters['action'] == "removefavorite" || $parameters['action'] == "removefavorites")
			{
				$user_activity['activity'] = "usercp_managefavorites";
			}
			else if($parameters['action'] == "addsubscription" || $parameters['action'] == "do_addsubscription" || $parameters['action'] == "removesubscription" || $parameters['action'] == "removesubscriptions")
			{
				$user_activity['activity'] = "usercp_managesubscriptions";
			}
			elseif($parameters['action'] == "notepad" || $parameters['action'] == "do_notepad")
			{
				$user_activity['activity'] = "usercp_notepad";
			}
			else
			{
				$user_activity['activity'] = "usercp";
			}
			break;
		case "portal":
			$user_activity['activity'] = "portal";
			break;
		case "warnings":
			if(!isset($parameters['action']))
			{
				$parameters['action'] = '';
			}
			if($parameters['action'] == "warn" || $parameters['action'] == "do_warn")
			{
				$user_activity['activity'] = "warnings_warn";
			}
			elseif($parameters['action'] == "do_revoke")
			{
				$user_activity['activity'] = "warnings_revoke";
			}
			elseif($parameters['action'] == "view")
			{
				$user_activity['activity'] = "warnings_view";
			}
			else
			{
				$user_activity['activity'] = "warnings";
			}
			break;
		case "nopermission":
			$user_activity['activity'] = "nopermission";
			$user_activity['nopermission'] = 1;
			break;
		default:
			$user_activity['activity'] = "unknown";
			break;
	}

	// Expects $location to be passed through already sanitized
	$user_activity['location'] = $location;

	$user_activity = $plugins->run_hooks("fetch_wol_activity_end", $user_activity);

	return $user_activity;
}

/**
 * Builds a friendly named Who's Online location from an "activity" and array of user data. Assumes fetch_wol_activity has already been called.
 *
 * @param array $user_activity Array containing activity and essential IDs.
 * @return string Location name for the activity being performed.
 */
function build_friendly_wol_location($user_activity)
{
	global $db, $lang, $uid_list, $aid_list, $pid_list, $tid_list, $id_list, $fid_list, $ann_list, $eid_list, $plugins, $parser, $mybb;
	global $cache, $SITENAME, $threads, $torrents2, $forums, $forums_linkto, $userid, $CURUSER, $forum_cache, $posts, $announcements, $events, $usernames, $attachments;

	// Fetch forum permissions for this user
	$unviewableforums = get_unviewable_forums();
	$inactiveforums = get_inactive_forums();
	$fidnot = '';
	$unviewablefids = $inactivefids = array();
	if($unviewableforums)
	{
		$fidnot = " AND fid NOT IN ($unviewableforums)";
		$unviewablefids = explode(',', $unviewableforums);
	}
	if($inactiveforums)
	{
		$fidnot .= " AND fid NOT IN ($inactiveforums)";
		$inactivefids = explode(',', $inactiveforums);
	}

	
	// Fetch any users
$usernames = $usernames ?? [];
$uid_list = $uid_list ?? [];
$CURUSER['id'] = $CURUSER['id'] ?? 0;
$CURUSER['username'] = $CURUSER['username'] ?? '';
$activity_id = (int)($user_activity['id'] ?? 0);

// Добавляем текущего пользователя в uid_list, если его там нет
if (!in_array($CURUSER['id'], $uid_list, true)) {
    $uid_list[] = $CURUSER['id'];
}

// Приведение всех uid к integer
$uid_list = array_map('intval', $uid_list);

// Загружаем юзеров, которых ещё нет в $usernames
$missing_uids = array_filter($uid_list, fn($uid) => !isset($usernames[$uid]));
if (!empty($missing_uids)) {
    $uid_sql = implode(',', $missing_uids);
    $query = $db->simple_select("users", "id, username", "id IN ($uid_sql)");
    while ($user = $db->fetch_array($query)) {
        $usernames[(int)$user['id']] = htmlspecialchars_uni((string)$user['username']);
    }
}

// Определяем $location_name для профиля
if (!empty($usernames[$activity_id])) {
    $location_name = sprintf(
        $lang->online['viewing_profile2'],
        get_profile_link($activity_id),
        $usernames[$activity_id]
    );
} else {
    $location_name = $lang->online['viewing_profile'];
}




	
	

	// Fetch any attachments
$attachments = $attachments ?? [];
$aid_list = $aid_list ?? [];
$pid_list = $pid_list ?? [];

// Проверяем, есть ли что обрабатывать
if (empty($attachments) && !empty($aid_list)) {

    // Приведение всех aid к integer для безопасности
    $aid_list = array_map('intval', $aid_list);

    // Составляем SQL IN список
    $aid_sql = implode(',', $aid_list);

    // Запрос к базе
    $query = $db->simple_select("attachments", "aid, pid", "aid IN ({$aid_sql})");

    while ($attachment = $db->fetch_array($query)) {
        $aid = (int)$attachment['aid'];
        $pid = (int)$attachment['pid'];

        $attachments[$aid] = $pid;
        $pid_list[] = $pid;
    }

    // Можно обновить $aid_list, если нужно
    $aid_list = array_keys($attachments);
}

	
	
	

	// Fetch any announcements
$announcements = $announcements ?? [];
$ann_list = $ann_list ?? [];
$fidnot = $fidnot ?? '';

// Проверяем, есть ли что обрабатывать
if (empty($announcements) && !empty($ann_list)) {

    // Приведение всех aid к integer для безопасности
    $ann_list = array_map('intval', $ann_list);

    // Составляем SQL IN список
    $aid_sql = implode(',', $ann_list);

    // Запрос к базе
    $query = $db->simple_select("tsf_announcements", "aid, subject", "aid IN ({$aid_sql}) {$fidnot}");

    while ($announcement = $db->fetch_array($query)) {
        $aid = (int)$announcement['aid'];
        $title = htmlspecialchars_uni($parser->parse_badwords((string)$announcement['subject']));

        $announcements[$aid] = $title;
    }

    // Если нужно, можно обновить $ann_list
    $ann_list = array_keys($announcements);
}
	
	
	
	
	
	
	
	

	// Fetch any posts
$posts = $posts ?? [];
$pid_list = $pid_list ?? [];

// Проверяем, есть ли что обрабатывать
if (empty($posts) && !empty($pid_list)) {

    // Приведение всех pid к integer для безопасности
    $pid_list = array_map('intval', $pid_list);

    // Составляем SQL IN список
    $pid_sql = implode(',', $pid_list);

    // Запрос к базе
    $query = $db->simple_select("tsf_posts", "pid, tid", "pid IN ({$pid_sql})");

    $tid_list = $tid_list ?? [];
    while ($post = $db->fetch_array($query)) {
        $pid = (int)$post['pid'];
        $tid = (int)$post['tid'];

        $posts[$pid] = $tid;
        $tid_list[] = $tid;
    }
}


	
	
	

	// Fetch any threads
// Убедимся, что массивы существуют
$threads = $threads ?? [];
$tid_list = $tid_list ?? [];
$fidnot = $fidnot ?? '';

// Проверяем, есть ли что обрабатывать
if (empty($threads) && !empty($tid_list)) {
    $threads = [];
    $thread_fid_list = [];

    // Приведение всех tid к integer для безопасности
    $tid_list = array_map('intval', $tid_list);

    // Составляем SQL IN список
    $tid_sql = implode(',', $tid_list);

    // Запрос к базе
    $query = $db->simple_select('tsf_threads', 'uid, fid, tid, subject, visible, prefix', "tid IN({$tid_sql}) {$fidnot}");

    while ($thread = $db->fetch_array($query)) {
        $t_tid = (int)$thread['tid'];
        $t_fid = (int)$thread['fid'];
        $t_subject = htmlspecialchars_uni((string)$thread['subject']);

        $threads[$t_tid] = $t_subject;
        $thread_fid_list[] = $t_fid;
    }

    // Обновляем fid_list, если нужно
    $fid_list = $thread_fid_list;
}






	
	
	
	// Fetch any TORRENTS
	// Убедимся, что массивы существуют
$torrents2 = $torrents2 ?? [];
$id_list = $id_list ?? [];

if (empty($torrents2) && !empty($id_list)) {
    $torrents2 = [];
    $torrent_id_list = [];

    $id_list = array_map('intval', $id_list);
    $id_sql = implode(',', $id_list);

    $query = $db->simple_select('torrents', 'id, name', "id IN({$id_sql})");

    while ($torrent = $db->fetch_array($query)) {
        $t_id = (int)$torrent['id'];
        $t_name = htmlspecialchars_uni((string)$torrent['name']);

        $torrents2[$t_id] = $t_name;
        $torrent_id_list[] = $t_id;
    }

    $id_list = $torrent_id_list;
}


	

	
	
	
	
	// Fetch any forums
	if(!is_array($forums) && !empty($fid_list)) 
	{
      $fidnot = array_merge($unviewablefids ?? [], $inactivefids ?? []);

      $forums = [];
      $forums_linkto = [];

        foreach ($forum_cache as $fid => $forum) 
	    {
           if (in_array($fid, $fid_list, true) && !in_array($fid, $fidnot, true)) 
		   {
              $forums[$fid] = $forum['name'];
              $forums_linkto[$fid] = $forum['linkto'];
           }
        }
    }

	
	
	
	
	

	// And finaly any events
	//if(!is_array($events) && count($eid_list) > 0)
	//{
		//$eid_sql = implode(",", $eid_list);
		//$query = $db->simple_select("events", "eid,name", "eid IN ($eid_sql)");
		//while($event = $db->fetch_array($query))
		//{
		//	$events[$event['eid']] = htmlspecialchars_uni($parser->parse_badwords($event['name']));
		//}
	//}

	// Now we've got everything we need we can put a name to the location
	switch($user_activity['activity'])
	{
		// announcement.php functions
		case "announcements":
			if(!empty($announcements[$user_activity['ann']]))
			{
				//$location_name =  sprintf('Viewing Announcement <a href="'.get_announcement_link2($user_activity['ann']).'">'.$announcements[$user_activity['ann']].'</a>');
				
				$location_name =  sprintf($lang->online['viewing_announcements'], get_announcement_link2($user_activity['ann']), $announcements[$user_activity['ann']]);
			}
			else
			{
				$location_name = $lang->online['viewing_announcements2'];
			}
			break;
		// attachment.php actions
		case "attachment":
			$pid = $attachments[$user_activity['aid']];
			$tid = $posts[$pid];
			if(!empty($threads[$tid]))
			{
				//$location_name = sprintf('Viewing <a href="attachment.php?aid='.$user_activity['aid'].'" target=\"_blank\">Attachment</a> in Thread <a href="'.get_thread_link2($tid).'">'.$threads[$tid].'</a>');
				
				$location_name = sprintf($lang->online['viewing_attachment2'], $user_activity['aid'], $threads[$tid], get_thread_link2($tid));
			}
			else
			{
				$location_name = $lang->online['viewing_attachment'];
			}
			break;;

		// editpost.php functions
		case "editpost":
			$location_name = $lang->online['editing_post'];
			break;
		// forumdisplay.php functions
		case "forumdisplay":
			if(!empty($forums[$user_activity['fid']]))
			{
				if($forums_linkto[$user_activity['fid']])
				{
					//$location_name = sprintf('Being Redirected To <a href=\"{1}\">{2}</a>', get_forum_link2($user_activity['fid']), $forums[$user_activity['fid']]);
					//$location_name = sprintf($lang->online['forum_redirect_to'], get_forum_link2($user_activity['fid']), $forums[$user_activity['fid']]);
					
					$location_name = sprintf($lang->online['forum_redirect_to'], get_forum_link2($user_activity['fid']), $forums[$user_activity['fid']]);
				}
				else
				{
					
					
					$location_name = sprintf($lang->online['viewing_forum2'], get_forum_link2($user_activity['fid']), $forums[$user_activity['fid']]);
				}
			}
			else
			{
				$location_name = $lang->online['viewing_forum'];
			}
			break;
		// index.php functions
		case "index":
			$location_name = sprintf(''.$SITENAME.' <a href="index.php">Main Index</a>');
			break;
			
		case "index2":
			$location_name = sprintf(''.$SITENAME.' <a href="index2.php">Main Forum</a>');
			break;
			
		case "upload":
			$location_name = sprintf(''.$SITENAME.' <a href="upload.php">Uploading Torrent</a>');
			break;
		case "browse":
			$location_name = sprintf(''.$SITENAME.' <a href="browse.php">Viewing Browse Page</a>');
			break;
		// managegroup.php functions
		case "managegroup":
			$location_name = $lang->managing_group;
			break;
		// member.php functions
		case "member_profile":
			if(!empty($usernames[$user_activity['id']]))
			{
				$location_name = sprintf($lang->online['viewing_profile2'], get_profile_link($user_activity['id']), $usernames[$user_activity['id']]);
			}
			else
			{
				$location_name = $lang->online['viewing_profile'];
			}
			break;
			
			
		case "member_register":
			$location_name = $lang->registering;
			break;
		case "member":
		case "member_login":
			// Guest or member?
			if($CURUSER['id'] == 0)
			{
				$location_name = $lang->online['logging_in'];
			}
			else
			{
				$location_name = $lang->online['logging_in_plain'];
			}
			break;
		case "member_logout":
			$location_name = $lang->online['logging_out'];
			break;
		case "member_emailuser":
			$location_name = $lang->online['emailing_user'];
			break;
		case "member_rate":
			$location_name = $lang->online['rating_user'];
			break;
		case "member_resendactivation":
			$location_name = $lang->online['member_resendactivation'];
			break;
		case "member_lostpw":
			$location_name = $lang->online['member_lostpw'];
			break;	
			
			
		// users.php functions
		case "users":
			$location_name ='Viewing <a href="users.php">Users List</a>';
			break;
		// misc.php functions
		case "misc_dstswitch":
			$location_name = $lang->online['changing_dst'];
			break;
		case "misc_whoposted":
			if(!empty($threads[$user_activity['tid']]))
			{
				$location_name = sprintf($lang->online['viewing_whoposted2'], get_thread_link2($user_activity['tid']), $threads[$user_activity['tid']]);
			}
			else
			{
				$location_name = $lang->online['viewing_whoposted'];
			}
			break;
		case "misc_markread":
			//$location_name = sprintf('marking_read', $mybb->post_code);
			$location_name = sprintf($lang->online['marking_read'], $mybb->post_code);
			break;
		case "misc_help":
			$location_name = 'viewing_helpdocs';
			break;
		case "misc_buddypopup":
			$location_name = 'viewing_buddylist';
			break;
		case "misc_smilies":
			$location_name = 'viewing_smilies';
			break;
		case "misc_syndication":
			$location_name = $lang->online['viewing_syndication'];
			break;
			
		// modcp.php functions
		case "modcp_modlogs":
			$location_name = $lang->online['viewing_modlogs'];
			break;
		case "modcp_announcements":
			$location_name = $lang->online['managing_announcements'];
			break;
		case "modcp_finduser":
			$location_name = $lang->online['search_for_user'];
			break;
		case "modcp_warninglogs":
			$location_name = $lang->online['managing_warninglogs'];
			break;
		case "modcp_ipsearch":
			$location_name = $lang->online['searching_ips'];
			break;
		case "modcp_report":
			$location_name = $lang->online['viewing_reports'];
			break;
		case "modcp_new_announcement":
			$location_name = $lang->online['adding_announcement'];
			break;
		case "modcp_delete_announcement":
			$location_name = $lang->online['deleting_announcement'];
			break;
		case "modcp_edit_announcement":
			$location_name = $lang->online['editing_announcement'];
			break;
		case "modcp_mod_queue":
			$location_name = $lang->online['managing_modqueue'];
			break;
		case "modcp_editprofile":
			$location_name = $lang->online['editing_user_profiles'];
			break;
		case "modcp_banning":
			$location_name = $lang->online['managing_bans'];
			break;
		case "modcp":
			$location_name = $lang->online['viewing_modcp'];
			break;
		// moderation.php functions
		case "moderation":
			$location_name = $lang->online['using_modtools'];
			break;	
			
		// newreply.php functions
		case "newreply":
			if(!empty($threads[$user_activity['tid']]))
			{
				//$location_name = sprintf('Replying to Thread <a href="'.get_thread_link2($user_activity['tid']).'">'.$threads[$user_activity['tid']].'</a>');
				$location_name = sprintf($lang->online['replying_thread2'], get_thread_link2($user_activity['tid']), $threads[$user_activity['tid']]);
			}
			else
			{
				$location_name = $lang->online['replying_thread'];
			}
			break;
		// newthread.php functions
		case "newthread":
			if(!empty($forums[$user_activity['fid']]))
			{
				//$location_name = sprintf('Posting New Thread in <a href="'.get_forum_link2($user_activity['fid']).'">'.$forums[$user_activity['fid']].'</a>');
				$location_name = sprintf($lang->online['posting_thread2'], get_forum_link2($user_activity['fid']), $forums[$user_activity['fid']]);
			}
			else
			{
				$location_name = $lang->online['posting_thread'];
			}
			break;
		// online.php functions
		case "wol":
			$location_name = $lang->online['viewing_wol'];
			break;
		case "woltoday":
			$location_name = $lang->online['viewing_woltoday'];
			break;
		// polls.php functions
		case "newpoll":
			$location_name = $lang->online['creating_poll'];
			break;
		case "editpoll":
			$location_name = $lang->online['editing_poll'];
			break;
		case "showresults":
			$location_name = $lang->online['viewing_pollresults'];
			break;
		case "vote":
			$location_name = $lang->online['voting_poll'];
			break;
		// printthread.php functions
		case "printthread":
			if(!empty($threads[$user_activity['tid']]))
			{
				$location_name = sprintf($lang->online['printing_thread2'], get_thread_link2($user_activity['tid']), $threads[$user_activity['tid']]);
			}
			else
			{
				$location_name = $lang->online['printing_thread'];
			}
			break;
		// private.php functions
		case "private_send":
			$location_name = $lang->online['sending_pm'];
			break;
		case "private_read":
			$location_name = $lang->online['reading_pm'];
			break;
		case "private_folders":
			$location_name = $lang->online['editing_pmfolders'];
			break;
		case "private":
			$location_name = $lang->online['using_pmsystem'];
			break;
		/* Ratethread functions */
		case "ratethread":
			$location_name = $lang->rating_thread;
			break;
		// report.php functions
		case "report":
			$location_name = $lang->reporting_post;
			break;
		// reputation.php functions
		case "reputation":
			$location_name = $lang->sprintf($lang->giving_reputation, get_profile_link($user_activity['uid']), $usernames[$user_activity['uid']]);
			break;
		case "reputation_report":
			if(!empty($usernames[$user_activity['uid']]))
			{
				$location_name = $lang->sprintf($lang->viewing_reputation_report, "reputation.php?uid={$user_activity['uid']}", $usernames[$user_activity['uid']]);
			}
			else
			{
				$location_name = $lang->sprintf($lang->viewing_reputation_report2);
			}
			break;
		// search.php functions
		case "tsf_search":
			$location_name = sprintf('<a href="tsf_search.php">Searching</a> '.$SITENAME.'');
			break;
			
			
		case "details":
			if(!empty($torrents2[$user_activity['id']]))
			{
				$pagenote = '';
				//$location_name = sprintf('Viewing Torrent Details <a href="'.get_torrent_link($user_activity['id']).'">'.$torrents2[$user_activity['id']].'</a> '.$pagenote.'');
				
				
				$location_name = sprintf($lang->online['viewing_torrent2'], get_torrent_link($user_activity['id']), $torrents2[$user_activity['id']], $pagenote);
			} 
			else
			{
				$location_name = $lang->online['viewing_torrent'];
			}
			break;	
			
		case "download":
			if(!empty($torrents2[$user_activity['id']]))
			{
				$pagenote = '';
				//$location_name = sprintf('Download Torrent <a href="'.get_download_link($user_activity['id']).'">'.$torrents2[$user_activity['id']].'</a> '.$pagenote.'');
				
				$location_name = sprintf($lang->online['download_torrent2'], get_download_link($user_activity['id']), $torrents2[$user_activity['id']], $pagenote);
			}
			else
			{
				$location_name = $lang->online['download_torrent'];
			}
			break;
			
		
		// showthread.php functions
		case "showthread":
			if(!empty($threads[$user_activity['tid']]))
			{
				$pagenote = '';
				//$location_name = sprintf('Reading Thread <a href="'.get_thread_link2($user_activity['tid']).'">'.$threads[$user_activity['tid']].'</a> '.$pagenote.'');
				
				$location_name = sprintf($lang->online['reading_thread2'], get_thread_link2($user_activity['tid']), $threads[$user_activity['tid']], $pagenote);
			}
			else
			{
				$location_name = $lang->online['reading_thread'];
			}
			break;
		case "showpost":
			if(!empty($posts[$user_activity['pid']]) && !empty($threads[$posts[$user_activity['pid']]]))
			{
				$pagenote = '';
				$location_name = sprintf($lang->reading_thread2, get_thread_link2($posts[$user_activity['pid']]), $threads[$posts[$user_activity['pid']]], $pagenote);
			}
			else
			{
				$location_name = $lang->online['reading_thread'];
			}
			break;
		// showteam.php functions
		case "showteam":
			$location_name = $lang->viewing_team;
			break;
		// stats.php functions
		case "stats":
			$location_name = $lang->viewing_stats;
			break;
		// usercp.php functions
		case "usercp_profile":
			$location_name = $lang->online['updating_profile'];
			break;
		case "usercp_editlists":
			$location_name = $lang->online['managing_buddyignorelist'];
			break;
		case "usercp_options":
			$location_name = $lang->online['updating_options'];
			break;
		case "usercp_editsig":
			$location_name = $lang->online['editing_signature'];
			break;
		case "usercp_avatar":
			$location_name = $lang->online['changing_avatar'];
			break;
		case "usercp_subscriptions":
			$location_name = $lang->online['viewing_subscriptions'];
			break;
		case "usercp_favorites":
			$location_name = $lang->online['viewing_favorites'];
			break;
		case "usercp_notepad":
			$location_name = $lang->online['editing_pad'];
			break;
		case "usercp_password":
			$location_name = $lang->online['editing_password'];
			break;
		case "usercp":
			$location_name = $lang->online['user_cp'];
			break;
		case "usercp_managefavorites":
			$location_name = $lang->online['managing_favorites'];
			break;
		case "usercp_managesubscriptions":
			$location_name = $lang->online['managing_subscriptions'];
			break;
		
	}

	$plugin_array = array('user_activity' => &$user_activity, 'location_name' => &$location_name);
	$plugins->run_hooks("build_friendly_wol_location_end", $plugin_array);

	if(isset($user_activity['nopermission']) && $user_activity['nopermission'] == 1)
	{
		//$location_name = 'viewing_noperms';
		$location_name = $lang->online['viewing_noperms'];
	}

	if(!$location_name)
	{
		//$location_name = sprintf('<a href="'.$user_activity['location'].'">Unknown Location</a>');
		
		$location_name = sprintf($lang->online['unknown_location'], $user_activity['location']);
	}

	return $location_name;
}

/**
 * Build a Who's Online row for a specific user
 *
 * @param array $user Array of user information including activity information
 * @return string Formatted online row
 */
function build_wol_row($user)
{
	global $mybb, $lang, $templates, $theme, $session, $db, $CURUSER, $usergroups;

	// We have a registered user
	if($user['uid'] > 0)
	{
		// Only those with "canviewwolinvis" permissions can view invisible users
		//if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $CURUSER['id'])
		if($user['invisible'] != 1 || $usergroups['canstaffpanel'] == '1' || $user['uid'] == $CURUSER['id'])
		{
			// Append an invisible mark if the user is invisible
			//if($user['invisible'] == 1 && $mybb->usergroup['canbeinvisible'] == 1)
			if($user['invisible'] == 1)
			{
				$invisible_mark = "*";
			}
			else
			{
				$invisible_mark = '';
			}

			$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
			$online_name = build_profile_link($user['username'], $user['uid']).$invisible_mark;
		}
	}
	// We have a bot
	elseif(!empty($user['bot']))
	{
		$online_name = format_name($user['bot'], $user['usergroup']);
	}
	// Otherwise we've got a plain old guest
	else
	{
		$online_name = format_name('guest', 1);
	}

	$online_time = my_datee('relative', $user['time']);

	// Fetch the location name for this users activity
	$location = build_friendly_wol_location($user['activity']);

	// Can view IPs, then fetch the IP template
	//if($mybb->usergroup['canviewonlineips'] == 1)
	//{
		//$user['ip'] = htmlspecialchars_uni ($user['ip']);
		
		$user['ip'] = my_inet_ntop($db->unescape_binary($user['ip']));

		//if($mybb->usergroup['canmodcp'] == 1 && $mybb->usergroup['canuseipsearch'] == 1)
		//{
			$lookup = '&nbsp;<a href="modcp.php?action=ipsearch&amp;ipaddress='.$user['ip'].'&amp;search_users=1" class="links"><i class="fa-solid fa-circle-info"></i></a>';
		//}

		$user_ip = '<span class="text-muted">IP: '.$user['ip'].' '.$lookup.'</span>';
	//}
	//else
	//{
	//	$user_ip = $lookup = $user['ip'] = '';
	//}

	$online_row = '';
	// And finally if we have permission to view this user, return the completed online row
	//if($user['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $mybb->user['uid'])
	//if($user['id'] == $CURUSER['id'])
	//{
		$online_row = '
		
		<div class="col-lg-6 mb-2">
	<div class="card">
		<div class="card-body">
			<h6 class="mb-0">'.$online_name.'</h6>
			'.$user_ip.'
			
			<p class="mt-2 mb-0 small">'.$online_time.'</p>
			<span class="small">'.$location.'</span>
		</div>
	</div>
</div>';
		
		
	//}
	return $online_row;
}
