<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/



function inline_error($errors, $title="", $json_data=array())
{
	global $theme, $mybb, $db, $lang, $templates, $charset;

	if(!$title)
	{
		$title = $lang->global['please_correct_errors'];
	}

	if(!is_array($errors))
	{
		$errors = array($errors);
	}

	// AJAX error message?
	if($mybb->input['ajax'])
	{
		// Send our headers.
		@header("Content-type: application/json; charset={$charset}");

		if(empty($json_data))
		{
			echo json_encode(array("errors" => $errors));
		}
		else
		{
			echo json_encode(array_merge(array("errors" => $errors), $json_data));
		}
		exit;
	}

	$errorlist = '';

	foreach($errors as $error)
	{
		$errorlist .= $error;
	}

	$errors = '<div class="red_alert">
           <i class="fa-solid fa-circle-exclamation"></i> &nbsp;'.$errorlist.'
            </div>';

	return $errors;
}




function check_forum_password($fid, $pid=0, $return=false)
{
	global $mybb, $header, $footer, $headerinclude, $theme, $templates, $lang, $forum_cache, $CURUSER, $BASEURL;

	$showform = true;

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
		if(!$forum_cache)
		{
			return false;
		}
	}

	// Loop through each of parent forums to ensure we have a password for them too
	if(isset($forum_cache[$fid]['parentlist']))
	{
		$parents = explode(',', $forum_cache[$fid]['parentlist']);
		rsort($parents);
	}
	if(!empty($parents))
	{
		foreach($parents as $parent_id)
		{
			if($parent_id == $fid || $parent_id == $pid)
			{
				continue;
			}

			if($forum_cache[$parent_id]['password'] !== "")
			{
				check_forum_password($parent_id, $fid);
			}
		}
	}

	if($forum_cache[$fid]['password'] !== '')
	{
		if(isset($mybb->input['pwverify']) && $pid == 0)
		{
			if(my_hash_equals($forum_cache[$fid]['password'], $mybb->get_input('pwverify')))
			{
				my_setcookie("forumpass[$fid]", md5($CURUSER['id'].$mybb->get_input('pwverify')), null, true);
				$showform = false;
			}
			else
			{
				eval("\$pwnote = \"".$templates->get("forumdisplay_password_wrongpass")."\";");
				$showform = true;
			}
		}
		else
		{
			if(!forum_password_validated($forum_cache[$fid]))
			{
				$showform = true;
			}
			else
			{
				$showform = false;
			}
		}
	}
	else
	{
		$showform = false;
	}

	if($return)
	{
		return $showform;
	}

	if($showform)
	{
		if($pid)
		{
			header("Location: ".$BASEURL."/".get_forum_link($fid));
		}
		else
		{
			$_SERVER['REQUEST_URI'] = htmlspecialchars_uni($_SERVER['REQUEST_URI']);
			eval("\$pwform = \"".$templates->get("forumdisplay_password")."\";");
			
			stdhead();
			
			build_breadcrumb();
			
			echo $pwform;
			
			stdfoot();
		}
		exit;
	}
}



function forum_password_validated($forum, $ignore_empty=false, $check_parents=false)
{
	global $mybb, $forum_cache, $CURUSER;

	if($check_parents && isset($forum['parentlist']))
	{
		if(!is_array($forum_cache))
		{
			$forum_cache = cache_forums();
			if(!$forum_cache)
			{
				return false;
			}
		}

		$parents = explode(',', $forum['parentlist']);
		rsort($parents);

		foreach($parents as $parent_id)
		{
			if($parent_id != $forum['fid'] && !forum_password_validated($forum_cache[$parent_id], true))
			{
				return false;
			}
		}
	}

	return ($ignore_empty && $forum['password'] === '') || (
		isset($mybb->cookies['forumpass'][$forum['fid']]) &&
		my_hash_equals(
			md5($CURUSER['id'].$forum['password']),
			$mybb->cookies['forumpass'][$forum['fid']]
		)
	);
}



function forum_permissions($fid=0, $uid=0, $gid=0)
{
	global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb, $cached_forum_permissions_permissions, $cached_forum_permissions, $CURUSER, $usergroups;

	if($uid == 0)
	{
		$uid = $CURUSER['id'];
	}

	if(!$gid || $gid == 0) // If no group, we need to fetch it
	{
		if($uid != 0 && $uid != $CURUSER['id'])
		{
			$user = get_user($uid);

			$gid = $user['usergroup'].",".$user['additionalgroups'];
			$groupperms = usergroup_permissions($gid);
		}
		else
		{
			$gid = $CURUSER['usergroup'];

			if(isset($CURUSER['additionalgroups']))
			{
				$gid .= ",".$CURUSER['additionalgroups'];
			}

			$groupperms = $usergroups;
		}
	}

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();

		if(!$forum_cache)
		{
			return false;
		}
	}

	if(!is_array($fpermcache))
	{
		$fpermcache = $cache->read("forumpermissions");
	}

	if($fid) // Fetch the permissions for a single forum
	{
		if(empty($cached_forum_permissions_permissions[$gid][$fid]))
		{
			$cached_forum_permissions_permissions[$gid][$fid] = fetch_forum_permissions($fid, $gid, $groupperms);
		}
		return $cached_forum_permissions_permissions[$gid][$fid];
	}
	else
	{
		if(empty($cached_forum_permissions[$gid]))
		{
			foreach($forum_cache as $forum)
			{
				$cached_forum_permissions[$gid][$forum['fid']] = fetch_forum_permissions($forum['fid'], $gid, $groupperms);
			}
		}
		return $cached_forum_permissions[$gid];
	}
}

/**
 * Fetches the permissions for a specific forum/group applying the inheritance scheme.
 * Called by forum_permissions()
 *
 * @param int $fid The forum ID
 * @param string $gid A comma separated list of usergroups
 * @param array $groupperms Group permissions
 * @return array Permissions for this forum
*/
function fetch_forum_permissions($fid, $gid, $groupperms)
{
	global $groupscache, $forum_cache, $fpermcache, $mybb, $fpermfields;

    if(isset($gid))
    {
        $groups = explode(",", $gid);
    }
    else
    {
        $groups = array();
    }

	$current_permissions = array();
	$only_view_own_threads = 1;
	$only_reply_own_threads = 1;

	if(empty($fpermcache[$fid])) // This forum has no custom or inherited permissions so lets just return the group permissions
	{
		$current_permissions = $groupperms;
	}
	else
	{
		foreach($groups as $gid)
		{
			// If this forum has custom or inherited permissions for the currently looped group.
			if(!empty($fpermcache[$fid][$gid]))
			{
				$level_permissions = $fpermcache[$fid][$gid];
			}
			// Or, use the group permission instead, if available. Some forum permissions not existing here will be added back later.
			else if(!empty($groupscache[$gid]))
			{
				$level_permissions = $groupscache[$gid];
			}
			// No permission is available for the currently looped group, probably we have bad data here.
			else
			{
				continue;
			}

			foreach($level_permissions as $permission => $access)
			{
				if(empty($current_permissions[$permission]) || $access >= $current_permissions[$permission] || ($access == "yes" && $current_permissions[$permission] == "no"))
				{
					$current_permissions[$permission] = $access;
				}
			}

			if($level_permissions["canview"] && empty($level_permissions["canonlyviewownthreads"]))
			{
				$only_view_own_threads = 0;
			}

			if($level_permissions["canpostreplys"] && empty($level_permissions["canonlyreplyownthreads"]))
			{
				$only_reply_own_threads = 0;
			}
		}

		if(count($current_permissions) == 0)
		{
			$current_permissions = $groupperms;
		}
	}

	// Figure out if we can view more than our own threads
	if($only_view_own_threads == 0 || !isset($current_permissions["canonlyviewownthreads"]))
	{
		$current_permissions["canonlyviewownthreads"] = 0;
	}

	// Figure out if we can reply more than our own threads
	if($only_reply_own_threads == 0 || !isset($current_permissions["canonlyreplyownthreads"]))
	{
		$current_permissions["canonlyreplyownthreads"] = 0;
	}

	return $current_permissions;
}




function build_highlight_array($terms)
{
	global $mybb;

	
	$minsearchword = "0";
	
	if($minsearchword < 1)
	{
		$minsearchword = 3;
	}

	if(is_array($terms))
	{
		$terms = implode(' ', $terms);
	}

	// Strip out any characters that shouldn't be included
	$bad_characters = array(
		"(",
		")",
		"+",
		"-",
		"~"
	);
	$terms = str_replace($bad_characters, '', $terms);
	$words = array();

	// Check if this is a "series of words" - should be treated as an EXACT match
	if(my_strpos($terms, "\"") !== false)
	{
		$inquote = false;
		$terms = explode("\"", $terms);
		foreach($terms as $phrase)
		{
			$phrase = htmlspecialchars_uni($phrase);
			if($phrase != "")
			{
				if($inquote)
				{
					$words[] = trim($phrase);
				}
				else
				{
					$split_words = preg_split("#\s{1,}#", $phrase, -1);
					if(!is_array($split_words))
					{
						continue;
					}
					foreach($split_words as $word)
					{
						if(!$word || strlen($word) < $mybb->settings['minsearchword'])
						{
							continue;
						}
						$words[] = trim($word);
					}
				}
			}
			$inquote = !$inquote;
		}
	}
	// Otherwise just a simple search query with no phrases
	else
	{
		$terms = htmlspecialchars_uni($terms);
		$split_words = preg_split("#\s{1,}#", $terms, -1);
		if(is_array($split_words))
		{
			foreach($split_words as $word)
			{
				if(!$word || strlen($word) < $mybb->settings['minsearchword'])
				{
					continue;
				}
				$words[] = trim($word);
			}
		}
	}

	// Sort the word array by length. Largest terms go first and work their way down to the smallest term.
	// This resolves problems like "test tes" where "tes" will be highlighted first, then "test" can't be highlighted because of the changed html
	usort($words, 'build_highlight_array_sort');

	$highlight_cache = array();

	// Loop through our words to build the PREG compatible strings
	foreach($words as $word)
	{
		$word = trim($word);

		$word = my_strtolower($word);

		// Special boolean operators should be stripped
		if($word == "" || $word == "or" || $word == "not" || $word == "and")
		{
			continue;
		}

		// Now make PREG compatible
		$find = "/(?<!&|&#)\b([[:alnum:]]*)(".preg_quote($word, "/").")(?![^<>]*?>)/ui";
		$replacement = "$1<span class=\"highlight\" style=\"padding-left: 0px; padding-right: 0px;\">$2</span>";
		$highlight_cache[$find] = $replacement;
	}

	return $highlight_cache;
}




function get_child_list($fid)
{
	static $forums_by_parent;

	$forums = array();
	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();
		foreach($forum_cache as $forum)
		{
			if($forum['active'] != 0)
			{
				$forums_by_parent[$forum['pid']][$forum['fid']] = $forum;
			}
		}
	}
	if(isset($forums_by_parent[$fid]))
	{
		if(!is_array($forums_by_parent[$fid]))
		{
			return $forums;
		}

		foreach($forums_by_parent[$fid] as $forum)
		{
			$forums[] = (int)$forum['fid'];
			$children = get_child_list($forum['fid']);
			if(is_array($children))
			{
				$forums = array_merge($forums, $children);
			}
		}
	}
	return $forums;
}



function get_user_by_username($username, $options=array())
{
	global $mybb, $db;

	$username = $db->escape_string(my_strtolower($username));

	if(!isset($options['username_method']))
	{
		$options['username_method'] = 0;
	}

	switch($db->type)
	{
		case 'mysql':
		case 'mysqli':
			$field = 'username';
			$efield = 'email';
			break;
		default:
			$field = 'LOWER(username)';
			$efield = 'LOWER(email)';
			break;
	}

	switch($options['username_method'])
	{
		case 1:
			$sqlwhere = "{$efield}='{$username}'";
			break;
		case 2:
			$sqlwhere = "{$field}='{$username}' OR {$efield}='{$username}'";
			break;
		default:
			$sqlwhere = "{$field}='{$username}'";
			break;
	}

	$fields = array('id');
	if(isset($options['fields']))
	{
		$fields = array_merge((array)$options['fields'], $fields);
	}

	$query = $db->simple_select('users', implode(',', array_unique($fields)), $sqlwhere, array('limit' => 1));

	if(isset($options['exists']))
	{
		return (bool)$db->num_rows($query);
	}

	return $db->fetch_array($query);
}


  
function is_member($groups, $user = false)
{
	global $mybb, $CURUSER;

	if(empty($groups))
	{
		return array();
	}

	if($user == false)
	{
		$user = $CURUSER;
	}
	else if(!is_array($user))
	{
		// Assume it's a UID
		$user = get_user($user);
	}

	$memberships = array_map('intval', explode(',', $user['additionalgroups']));
	$memberships[] = $user['usergroup'];

	if(!is_array($groups))
	{
		if((int)$groups == -1)
		{
			return $memberships;
		}
		else
		{
			if(is_string($groups))
			{
				$groups = explode(',', $groups);
			}
			else
			{
				$groups = (array)$groups;
			}
		}
	}

	$groups = array_filter(array_map('intval', $groups));

	return array_intersect($groups, $memberships);
}




function mark_reports($id, $type="post")
{
	global $db, $cache, $plugins;

	switch($type)
	{
		case "posts":
			if(is_array($id))
			{
				$rids = implode("','", $id);
				$rids = "'0','$rids'";
				$db->update_query("reportedcontent", array('reportstatus' => 1), "id IN($rids) AND reportstatus='0' AND (type = 'post' OR type = '')");
			}
			break;
		case "post":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "threads":
			if(is_array($id))
			{
				$rids = implode("','", $id);
				$rids = "'0','$rids'";
				$db->update_query("reportedcontent", array('reportstatus' => 1), "id2 IN($rids) AND reportstatus='0' AND (type = 'post' OR type = '')");
			}
			break;
		case "thread":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id2='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "forum":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "id3='$id' AND reportstatus='0' AND (type = 'post' OR type = '')");
			break;
		case "all":
			$db->update_query("reportedcontent", array('reportstatus' => 1), "reportstatus='0' AND (type = 'post' OR type = '')");
			break;
	}

	$arguments = array('id' => $id, 'type' => $type);
	$plugins->run_hooks("mark_reports", $arguments);
	$cache->update_reportedcontent();
}




function log_moderator_action($data, $action="")
{
	global $mybb, $db, $CURUSER, $session;

	$fid = 0;
	if(isset($data['fid']))
	{
		$fid = (int)$data['fid'];
		unset($data['fid']);
	}

	$tid = 0;
	if(isset($data['tid']))
	{
		$tid = (int)$data['tid'];
		unset($data['tid']);
	}

	$pid = 0;
	if(isset($data['pid']))
	{
		$pid = (int)$data['pid'];
		unset($data['pid']);
	}

	$tids = array();
	if(isset($data['tids']))
	{
		$tids = (array)$data['tids'];
		unset($data['tids']);
	}

	// Any remaining extra data - we my_serialize and insert in to its own column
	if(is_array($data))
	{
		$data = my_serialize($data);
	}


	
	$sql_array = array(
		"uid" => (int)$CURUSER['id'],
		"dateline" => TIMENOW,
		"fid" => (int)$fid,
		"tid" => $tid,
		"pid" => $pid,
		"action" => $db->escape_string($action),
		"data" => $db->escape_string($data),
		"ipaddress" => $db->escape_binary($session->packedip)
	);

	if($tids)
	{
		$multiple_sql_array = array();

		foreach($tids as $tid)
		{
			$sql_array['tid'] = (int)$tid;
			$multiple_sql_array[] = $sql_array;
		}

		$db->insert_query_multiple("moderatorlog", $multiple_sql_array);
	}
	else
	{
		$db->insert_query("moderatorlog", $sql_array);
	}
}






  
  

  function get_subscription_method($tid = 0, $postoptions = array())
  {
	global $mybb, $CURUSER;

	$subscription_methods = array('', 'none', 'email', 'pm'); // Define methods
	$subscription_method = (int)$CURUSER['subscriptionmethod']; // Set user default

	// If no user default method available then reset method
	if(!$subscription_method)
	{
		$subscription_method = 0;
	}

	// Return user default if no thread id available, in case
	if(!(int)$tid || (int)$tid <= 0)
	{
		return $subscription_methods[$subscription_method];
	}

	// If method not predefined set using data from database
	if(isset($postoptions['subscriptionmethod']))
	{
		$method = trim($postoptions['subscriptionmethod']);
		return (in_array($method, $subscription_methods)) ? $method : $subscription_methods[0];
	}
	else
	{
		global $db;

		$query = $db->simple_select("tsf_threadsubscriptions", "tid, notification", "tid='".(int)$tid."' AND uid='".$CURUSER['id']."'", array('limit' => 1));
		$subscription = $db->fetch_array($query);

		if($subscription)
		{
			$subscription_method = (int)$subscription['notification'] + 1;
		}
	}

	return $subscription_methods[$subscription_method];
  }
  
  
  
  function get_inactive_forums()
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
  
  
  
  
  function get_unviewable_forums($only_readable_threads=false)
  {
	global $forum_cache, $permissioncache, $mybb;

	if(!is_array($forum_cache))
	{
		cache_forums();
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	$unviewable = array();
	foreach($forum_cache as $fid => $forum)
	{
		if($permissioncache[$forum['fid']])
		{
			$perms = $permissioncache[$forum['fid']];
		}
		else
		{
			$perms = $mybb->usergroup;
		}

		$pwverified = 1;


		if(!forum_password_validated($forum, true))
		{
			$pwverified = 0;
		}
		else
		{
			// Check parents for passwords
			$parents = explode(",", $forum['parentlist']);
			foreach($parents as $parent)
			{
				if(!forum_password_validated($forum_cache[$parent], true))
				{
					$pwverified = 0;
					break;
				}
			}
		}

		if($perms['canview'] == 0 || $pwverified == 0 || ($only_readable_threads == true && $perms['canviewthreads'] == 0))
		{
			$unviewable[] = $forum['fid'];
		}
	}

	$unviewableforums = implode(',', $unviewable);

	return $unviewableforums;
  }
  
  
  
  
  
  
  
  
  
  
  
  function get_attachment_icon($ext)
  {
	global $cache, $attachtypes, $theme, $templates, $lang, $mybb;

	if(!$attachtypes)
	{
		$attachtypes = $cache->read("attachtypes");
	}

	$ext = my_strtolower($ext);

	if($attachtypes[$ext]['icon'])
	{
		static $attach_icons_schemes = array();
		if(!isset($attach_icons_schemes[$ext]))
		{
			$attach_icons_schemes[$ext] = parse_url($attachtypes[$ext]['icon']);
			if(!empty($attach_icons_schemes[$ext]['scheme']))
			{
				$attach_icons_schemes[$ext] = $attachtypes[$ext]['icon'];
			}
			elseif(defined("IN_ADMINCP"))
			{
				$attach_icons_schemes[$ext] = str_replace("{theme}", "", $attachtypes[$ext]['icon']);
				if(my_substr($attach_icons_schemes[$ext], 0, 1) != "/")
				{
					$attach_icons_schemes[$ext] = "../".$attach_icons_schemes[$ext];
				}
			}
			elseif(defined("IN_PORTAL"))
			{
				global $change_dir;
				$attach_icons_schemes[$ext] = $change_dir."/".str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
				$attach_icons_schemes[$ext] = $mybb->get_asset_url($attach_icons_schemes[$ext]);
			}
			else
			{
				

				
				$attach_icons_schemes[$ext] = str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
				$attach_icons_schemes[$ext] = $mybb->get_asset_url($attach_icons_schemes[$ext]);
			}
		}

		$icon = $attach_icons_schemes[$ext];

		$name = htmlspecialchars_uni($attachtypes[$ext]['name']);
	}
	else
	{
		if(defined("IN_ADMINCP"))
		{
			$theme['imgdir'] = "../images";
		}
		else if(defined("IN_PORTAL"))
		{
			global $change_dir;
			$theme['imgdir'] = "{$change_dir}/images";
		}

		$icon = "{$theme['imgdir']}/attachtypes/unknown.png";

		$name = 'unknown';
	}

	$icon = htmlspecialchars_uni($icon);
	$attachment_icon = '<img src="'.$icon.'" title="'.$name.'" style="height: 16px; width: 16px" border="0" alt=".'.$ext.'" />';
	
	return $attachment_icon;
  }
  
  
  


  
  
  
  
  
  function signed($int)
  {
	if($int < 0)
	{
		return "$int";
	}
	else
	{
		return "+$int";
	}
  }
  
  
  
  function get_parent_list($fid)
  {
	global $forum_cache;
	static $forumarraycache;

	if(!empty($forumarraycache[$fid]))
	{
		return $forumarraycache[$fid]['parentlist'];
	}
	elseif(!empty($forum_cache[$fid]))
	{
		return $forum_cache[$fid]['parentlist'];
	}
	else
	{
		cache_forums();
		return $forum_cache[$fid]['parentlist'];
	}
  }
  
  
  function get_announcement_link($aid=0)
  {
	$link = str_replace("{aid}", $aid, ANNOUNCEMENT_URL);
	return htmlspecialchars_uni($link);
  }


  function build_parent_list($fid, $column="fid", $joiner="OR", $parentlist="")
  {
	if(!$parentlist)
	{
		$parentlist = get_parent_list($fid);
	}

	$parentsexploded = explode(",", $parentlist);
	$builtlist = "(";
	$sep = '';

	foreach($parentsexploded as $key => $val)
	{
		$builtlist .= "$sep$column='$val'";
		$sep = " $joiner ";
	}

	$builtlist .= ")";

	return $builtlist;
  }


  function get_forum($fid, $active_override=0)
  {
	global $cache;
	static $forum_cache;

	if(!isset($forum_cache) || !is_array($forum_cache))
	{
		$forum_cache = $cache->read("forums");
	}

	if(empty($forum_cache[$fid]))
	{
		return false;
	}

	if($active_override != 1)
	{
		$parents = explode(",", $forum_cache[$fid]['parentlist']);
		if(is_array($parents))
		{
			foreach($parents as $parent)
			{
				if($forum_cache[$parent]['active'] == 0)
				{
					return false;
				}
			}
		}
	}

	return $forum_cache[$fid];
  }
  
  
  function update_thread_data($tid)
  {
	global $db;

	$thread = get_thread($tid);

	// If this is a moved thread marker, don't update it - we need it to stay as it is
	if(strpos($thread['closed'], 'moved|') !== false)
	{
		return;
	}

	$query = $db->sql_query("
		SELECT u.id, u.username, p.username AS postusername, p.dateline
		FROM tsf_posts p
		LEFT JOIN users u ON (u.id=p.uid)
		WHERE p.tid='$tid' AND p.visible='1'
		ORDER BY p.dateline DESC, p.pid DESC
		LIMIT 1"
	);
	$lastpost = $db->fetch_array($query);

	$db->free_result($query);

	$query = $db->sql_query("
		SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
		FROM tsf_posts p
		LEFT JOIN users u ON (u.id=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC, p.pid ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);

	$db->free_result($query);

	if(empty($firstpost['username']))
	{
		$firstpost['username'] = $firstpost['postusername'];
	}

	if(empty($lastpost['username']))
	{
		$lastpost['username'] = $lastpost['postusername'];
	}

	if(empty($lastpost['dateline']))
	{
		$lastpost['username'] = $firstpost['username'];
		$lastpost['uid'] = $firstpost['uid'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}

	$lastpost['username'] = $db->escape_string($lastpost['username']);
	$firstpost['username'] = $db->escape_string($firstpost['username']);

	$update_array = array(
		'firstpost' => (int)$firstpost['pid'],
		'username' => $firstpost['username'],
		'uid' => (int)$firstpost['id'],
		'dateline' => (int)$firstpost['dateline'],
		'lastpost' => (int)$lastpost['dateline'],
		'lastposter' => $lastpost['username'],
		'lastposteruid' => (int)$lastpost['id'],
	);
	$db->update_query("tsf_threads", $update_array, "tid='{$tid}'");
  }
  
  
  
  function update_first_post($tid)
  {
	global $db;

	$query = $db->sql_query("
		SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
		FROM tsf_posts p
		LEFT JOIN users u ON (u.id=p.uid)
		WHERE p.tid='$tid'
		ORDER BY p.dateline ASC, p.pid ASC
		LIMIT 1
	");
	$firstpost = $db->fetch_array($query);

	if(empty($firstpost['username']))
	{
		$firstpost['username'] = $firstpost['postusername'];
	}
	$firstpost['username'] = $db->escape_string($firstpost['username']);

	$update_array = array(
		'firstpost' => (int)$firstpost['pid'],
		'username' => $firstpost['username'],
		'uid' => (int)$firstpost['id'],
		'dateline' => (int)$firstpost['dateline']
	);
	$db->update_query("tsf_threads", $update_array, "tid='{$tid}'");
  }


  function update_user_counters($uid, $changes=array())
  {
	global $db;

	$update_query = array();

	$counters = array('postnum', 'threadnum');
	$uid = (int)$uid;

	// Fetch above counters for this user
	$query = $db->simple_select("users", implode(",", $counters), "id='{$uid}'");
	$user = $db->fetch_array($query);
	
	if($user)
	{
		foreach($counters as $counter)
		{
			if(array_key_exists($counter, $changes))
			{
				if(substr($changes[$counter], 0, 2) == "+-")
				{
					$changes[$counter] = substr($changes[$counter], 1);
				}
				// Adding or subtracting from previous value?
				if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
				{
					if((int)$changes[$counter] != 0)
					{
						$update_query[$counter] = $user[$counter] + $changes[$counter];
					}
				}
				else
				{
					$update_query[$counter] = $changes[$counter];
				}

				// Less than 0? That's bad
				if(isset($update_query[$counter]) && $update_query[$counter] < 0)
				{
					$update_query[$counter] = 0;
				}
			}
		}
	}

	$db->free_result($query);

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("users", $update_query, "id='{$uid}'");
	}
  }
  
  
  function update_last_post($tid)
  {
	global $db;

	$query = $db->sql_query("
		SELECT u.id, u.username, p.username AS postusername, p.dateline
		FROM tsf_posts p
		LEFT JOIN users u ON (u.id=p.uid)
		WHERE p.tid='$tid' AND p.visible='1'
		ORDER BY p.dateline DESC, p.pid DESC
		LIMIT 1"
	);
	$lastpost = $db->fetch_array($query);

	if(!$lastpost)
	{
		return false;
	}

	if(empty($lastpost['username']))
	{
		$lastpost['username'] = $lastpost['postusername'];
	}

	if(empty($lastpost['dateline']))
	{
		$query = $db->sql_query("
			SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
			FROM tsf_posts p
			LEFT JOIN users u ON (u.id=p.uid)
			WHERE p.tid='$tid'
			ORDER BY p.dateline ASC, p.pid ASC
			LIMIT 1
		");
		$firstpost = $db->fetch_array($query);

		$lastpost['username'] = $firstpost['username'];
		$lastpost['id'] = $firstpost['id'];
		$lastpost['dateline'] = $firstpost['dateline'];
	}

	$lastpost['username'] = $db->escape_string($lastpost['username']);

	$update_array = array(
		'lastpost' => (int)$lastpost['dateline'],
		'lastposter' => $lastpost['username'],
		'lastposteruid' => (int)$lastpost['id']
	);
	$db->update_query("tsf_threads", $update_array, "tid='{$tid}'");
 }


 function update_thread_counters($tid, $changes=array())
 {
	global $db;

	$update_query = array();
	$tid = (int)$tid;

	//$counters = array('replies', 'attachmentcount');
	
	$counters = array('replies', 'unapprovedposts', 'attachmentcount');
	
	
	// Fetch above counters for this thread
	$query = $db->simple_select("tsf_threads", implode(",", $counters), "tid='{$tid}'");
	$thread = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$update_query[$counter] = $thread[$counter] + $changes[$counter];
				}
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}

			// Less than 0? That's bad
			if(isset($update_query[$counter]) && $update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}

	$db->free_result($query);

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("tsf_threads", $update_query, "tid='{$tid}'");
	}
 }







function update_forum_counters($fid, $changes=array())
{
	global $db;

	$update_query = array();

	$counters = array('threads', 'unapprovedthreads', 'posts', 'unapprovedposts');

	// Fetch above counters for this forum
	$query = $db->simple_select("tsf_forums", implode(",", $counters), "fid='{$fid}'");
	$forum = $db->fetch_array($query);

	foreach($counters as $counter)
	{
		if(array_key_exists($counter, $changes))
		{
			if(substr($changes[$counter], 0, 2) == "+-")
			{
				$changes[$counter] = substr($changes[$counter], 1);
			}
			// Adding or subtracting from previous value?
			if(substr($changes[$counter], 0, 1) == "+" || substr($changes[$counter], 0, 1) == "-")
			{
				if((int)$changes[$counter] != 0)
				{
					$update_query[$counter] = $forum[$counter] + $changes[$counter];
				}
			}
			else
			{
				$update_query[$counter] = $changes[$counter];
			}

			// Less than 0? That's bad
			if(isset($update_query[$counter]) && $update_query[$counter] < 0)
			{
				$update_query[$counter] = 0;
			}
		}
	}

	// Only update if we're actually doing something
	if(count($update_query) > 0)
	{
		$db->update_query("tsf_forums", $update_query, "fid='".(int)$fid."'");
	}

	// Guess we should update the statistics too?
	$new_stats = array();
	if(array_key_exists('threads', $update_query))
	{
		$threads_diff = $update_query['threads'] - $forum['threads'];
		if($threads_diff > -1)
		{
			$new_stats['numthreads'] = "+{$threads_diff}";
		}
		else
		{
			$new_stats['numthreads'] = "{$threads_diff}";
		}
	}

	if(array_key_exists('unapprovedthreads', $update_query))
	{
		$unapprovedthreads_diff = $update_query['unapprovedthreads'] - $forum['unapprovedthreads'];
		if($unapprovedthreads_diff > -1)
		{
			$new_stats['numunapprovedthreads'] = "+{$unapprovedthreads_diff}";
		}
		else
		{
			$new_stats['numunapprovedthreads'] = "{$unapprovedthreads_diff}";
		}
	}

	if(array_key_exists('posts', $update_query))
	{
		$posts_diff = $update_query['posts'] - $forum['posts'];
		if($posts_diff > -1)
		{
			$new_stats['numposts'] = "+{$posts_diff}";
		}
		else
		{
			$new_stats['numposts'] = "{$posts_diff}";
		}
	}

	if(array_key_exists('unapprovedposts', $update_query))
	{
		$unapprovedposts_diff = $update_query['unapprovedposts'] - $forum['unapprovedposts'];
		if($unapprovedposts_diff > -1)
		{
			$new_stats['numunapprovedposts'] = "+{$unapprovedposts_diff}";
		}
		else
		{
			$new_stats['numunapprovedposts'] = "{$unapprovedposts_diff}";
		}
	}



	if(!empty($new_stats))
	{
		update_stats($new_stats);
	}
}


 
 
 
 function update_forum_lastpost($fid)
 {
	global $db;

	// Fetch the last post for this forum
	$query = $db->sql_query("
		SELECT tid, lastpost, lastposter, lastposteruid, subject
		FROM tsf_threads
		WHERE fid='{$fid}' AND visible='1' AND closed NOT LIKE 'moved|%'
		ORDER BY lastpost DESC
		LIMIT 0, 1
	");

	if($db->num_rows($query) > 0)
	{
		$lastpost = $db->fetch_array($query);

		$updated_forum = array(
			"lastpost" => (int)$lastpost['lastpost'],
			"lastposter" => $db->escape_string($lastpost['lastposter']),
			"lastposteruid" => (int)$lastpost['lastposteruid'],
			"lastposttid" => (int)$lastpost['tid'],
			"lastpostsubject" => $db->escape_string($lastpost['subject']),
		);
	}
	else {
		$updated_forum = array(
			"lastpost" => 0,
			"lastposter" => '',
			"lastposteruid" => 0,
			"lastposttid" => 0,
			"lastpostsubject" => '',
		);
	}

	$db->update_query("tsf_forums", $updated_forum, "fid='{$fid}'");
 }


 function get_post_link($pid, $tid=0)
 {
	if($tid > 0)
	{
		$link = str_replace("{tid}", $tid, THREAD_URL_POST);
		$link = str_replace("{pid}", $pid, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{pid}", $pid, POST_URL);
		return htmlspecialchars_uni($link);
	}
 }
  
 function get_forum_link($fid, $page=0)
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
 
 function build_breadcrumb()
 {
	global $nav, $navbits, $templates, $theme, $lang, $mybb, $f_threadsperpage;

	$navsep = '&nbsp; <i class="fa-solid fa-angle-right little"></i> &nbsp;';

	$i = 0;
	$activesep = '';

	if(is_array($navbits))
	{
		reset($navbits);
		foreach($navbits as $key => $navbit)
		{
			if(isset($navbits[$key+1]))
			{
				if(isset($navbits[$key+2]))
				{
					$sep = $navsep;
				}
				else
				{
					$sep = "";
				}

				$multipage = null;
				$multipage_dropdown = null;
				if(!empty($navbit['multipage']))
				{
					if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
					{
						$f_threadsperpage = 20;
					}

					$multipage = multipage($navbit['multipage']['num_threads'], $f_threadsperpage, $navbit['multipage']['current_page'], $navbit['multipage']['url'], true);
					if($multipage)
					{
						++$i;
						//$multipage_dropdown = '<img src="pic/arrow_down.png" alt="v" title="" class="pagination_breadcrumb_link" id="breadcrumb_multipage" />'.$multipage.'';
						$multipage_dropdown = '';
						$sep = $multipage_dropdown.$sep;
					}
				}

				// Replace page 1 URLs
				$navbit['url'] = str_replace("-page-1.html", ".html", $navbit['url']);
				$navbit['url'] = preg_replace("/&amp;page=1$/", "", $navbit['url']);

				$nav .= '<a href="'.$navbit['url'].'">'.$navbit['name'].'</a>'.$sep.'';
			}
		}
		$navsize = count($navbits);
		$navbit = $navbits[$navsize-1];
	}

	if($nav)
	{
		$activesep = '&nbsp; <i class="fa-solid fa-angle-right little"></i> &nbsp;';
	}

	$activebit = '<br />
<div class="border-bottom border-2 mb-0 mt-3 rounded-0">
	<h3>'.$navbit['name'].'</h3>
	</div>
	</br>
	';
	
	$donenav = '
	
	<div class="container mt-3">
	<div class="navigation">
'.$nav.''.$activesep.''.$activebit.'
</div>
</div>

';

	echo $donenav;
 }
 
 function build_forum_breadcrumb($fid, $multipage=array())
 {
	global $pforumcache, $currentitem, $forum_cache, $cache, $navbits, $lang, $BASEURL, $archiveurl;

	if(!$pforumcache)
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}

	    
	
		foreach($forum_cache as $key => $val)
		{
			$pforumcache[$val['fid']][$val['pid']] = $val;
		}
	}

	if(is_array($pforumcache[$fid]))
	{
		foreach($pforumcache[$fid] as $key => $forumnav)
		{
			if($fid == $forumnav['fid'])
			{
				if(!empty($pforumcache[$forumnav['pid']]))
				{
					build_forum_breadcrumb($forumnav['pid']);
				}

				$navsize = count($navbits);
				// Convert & to &amp;
				$navbits[$navsize]['name'] = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forumnav['name']);

				if(defined("IN_ARCHIVE"))
				{
					// Set up link to forum in breadcrumb.
					if($pforumcache[$fid][$forumnav['pid']]['type'] == 'f' || $pforumcache[$fid][$forumnav['pid']]['type'] == 'c')
					{
						$navbits[$navsize]['url'] = "{$BASEURL}forum-".$forumnav['fid'].".html";
					}
					else
					{
						$navbits[$navsize]['url'] = $archiveurl."/index.php";
					}
				}
				elseif(!empty($multipage))
				{
					$navbits[$navsize]['url'] = get_forum_link($forumnav['fid'], $multipage['current_page']);

					$navbits[$navsize]['multipage'] = $multipage;
					$navbits[$navsize]['multipage']['url'] = str_replace('{fid}', $forumnav['fid'], FORUM_URL_PAGED);
				}
				else
				{
					$navbits[$navsize]['url'] = get_forum_link($forumnav['fid']);
				}
			}
		}
	}

	return 1;
	
 }
  
 
  
  function get_thread_link($tid, $page=0, $action='')
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
  
  function get_thread($tid, $recache = false)
  {
	global $db;
	static $thread_cache;

	$tid = (int)$tid;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("tsf_threads", "*", "tid = '{$tid}'");
		$thread = $db->fetch_array($query);

		if($thread)
		{
			$thread_cache[$tid] = $thread;
			return $thread;
		}
		else
		{
			$thread_cache[$tid] = false;
			return false;
		}
	}
  }
  

  function get_post($pid)
  {
	global $db;
	static $post_cache;

	if(isset($post_cache[$pid]))
	{
		return $post_cache[$pid];
	}
	else
	{
		$query = $db->simple_select("tsf_posts", "*", "pid='".intval($pid)."'");
		$post = $db->fetch_array($query);

		if($post)
		{
			$post_cache[$pid] = $post;
			return $post;
		}
		else
		{
			$post_cache[$pid] = false;
			return false;
		}
	}
  }





  


  function is_forum_mod ($forumid = 0, $userid = 0)
  {
    global $db;
	if ((!$forumid OR !$userid))
    {
      return false;
    }

    $query = $db->sql_query ('SELECT userid FROM ' . TSF_PREFIX . ('' . 'moderators WHERE forumid=' . $forumid . ' AND userid=' . $userid));
    return (0 < $db->num_rows ($query) ? true : false);
  }

  




 
  

  function add_breadcrumb($name, $url = "")
{
    global $navbits;

    if (!is_array($navbits)) {
        $navbits = [];
    }

    $navsize = count($navbits);
    $navbits[$navsize] = [
        'name' => $name,
        'url' => $url
    ];
}
  

  function reset_breadcrumb()
  {
	global $navbits;

	$newnav[0]['name'] = $navbits[0]['name'];
	$newnav[0]['url'] = $navbits[0]['url'];
	if(!empty($navbits[0]['options']))
	{
		$newnav[0]['options'] = $navbits[0]['options'];
	}

	unset($GLOBALS['navbits']);
	$GLOBALS['navbits'] = $newnav;
  }
  

  function show_forum_images ($type)
  {
    global $lang;
    $images = array ('offlock' => '<img src="pic/offlock.gif" title="' . $lang->tsf_forums['forum_locked'] . '" alt="' . $lang->tsf_forums['forum_locked'] . '" class="inlineimg" />', 'off' => '<img src="pic/off.gif" title="' . $lang->tsf_forums['no_new_posts'] . '" alt="' . $lang->tsf_forums['no_new_posts'] . '" class="inlineimg" />', 'on' => '<img src="pic/on.gif" title="' . $lang->tsf_forums['new_posts'] . '" alt="' . $lang->tsf_forums['new_posts'] . '" class="inlineimg" />');
    return $images[$type];
  }

  


  
  
  
  function build_forum_jump($pid=0, $selitem=0, $addselect=1, $depth="", $showextras=1, $showall=false, $permissions="", $name="fid")
  {
	global $forum_cache, $jumpfcache, $permissioncache, $mybb, $forumjump, $forumjumpbits, $gobutton, $theme, $templates, $lang;

	$pid = (int)$pid;

	if(!is_array($jumpfcache))
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}

		foreach($forum_cache as $fid => $forum)
		{
			if($forum['active'] != 0)
			{
				$jumpfcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
		}
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	if(isset($jumpfcache[$pid]) && is_array($jumpfcache[$pid]))
	{
		foreach($jumpfcache[$pid] as $main)
		{
			foreach($main as $forum)
			{
				$perms = $permissioncache[$forum['fid']];

				$hideprivateforums = "1";
				
				//if($forum['fid'] != "0" && ($perms['canview'] != 0 || $hideprivateforums == 0) && $forum['linkto'] == '' && ($forum['showinjump'] != 0 || $showall == true))
				///{
					$optionselected = "";

					if($selitem == $forum['fid'])
					{
						$optionselected = 'selected="selected"';
					}

					$forum['name'] = htmlspecialchars_uni(strip_tags($forum['name']));

					$forumjumpbits .= '<option value="'.$forum['fid'].'" '.$optionselected.'>'.$depth.' '.$forum['name'].'</option>';

					if($forum_cache[$forum['fid']])
					{
						$newdepth = $depth."--";
						$forumjumpbits .= build_forum_jump($forum['fid'], $selitem, 0, $newdepth, $showextras, $showall);
					}
				//}
			}
		}
	}

	if($addselect)
	{
		if($showextras == 0)
		{
			$template = "special";
		}
		else
		{
			$template = "advanced";

			if(strpos(FORUM_URL, '.html') !== false)
			{
				$forum_link = "'".str_replace('{fid}', "'+option+'", FORUM_URL)."'";
			}
			else
			{
				$forum_link = "'".str_replace('{fid}', "'+option", FORUM_URL);
			}
		}

		$gobutton = '<button type="submit" class="btn btn-sm btn-primary rounded" value="Go"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>';

		$forumjump = '
		
		
		<form action="forumdisplay.php" method="get">

<select name="'.$name.'" class="form-select form-select-sm border pe-5 w-auto">
<option value="-4">Private Messages</option>
<option value="-3">User Control Panel</option>
<option value="-5">Whos Online</option>
<option value="-2">Search</option>
<option value="-1">Forum Home</option>
'.$forumjumpbits.'
</select>
'.$gobutton.'
</form>
<script type="text/javascript">
$(".forumjump").on("change", function() {
	var option = $(this).val();

	if(option < 0)
	{
		window.location = "forumdisplay.php?fid="+option;
	}
	else
	{
		window.location = '.$forum_link.';
	}
});
</script>';
		
		
		
		
	
	
	
	}

	return $forumjump;
  }
  
  
  



  if (((!defined ('TSF_FORUMS_TSSEv56') OR !defined ('IN_SCRIPT_TSSEv56')) OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
