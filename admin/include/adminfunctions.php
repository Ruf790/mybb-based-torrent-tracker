<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


function calc_cron_time($stamp) 
{
    $ysecs = 365*24*60*60;
    $mosecs = 31*24*60*60;
    $wsecs = 7*24*60*60;
    $dsecs = 24*60*60;
    $hsecs = 60*60;
    $msecs = 60;

    $years = floor($stamp/$ysecs); $stamp %= $ysecs;
    $months = floor($stamp/$mosecs); $stamp %= $mosecs;
    $weeks = floor($stamp/$wsecs); $stamp %= $wsecs;
    $days = floor($stamp/$dsecs); $stamp %= $dsecs;
    $hours = floor($stamp/$hsecs); $stamp %= $hsecs;
    $minutes = floor($stamp/$msecs); $stamp %= $msecs;
    $seconds = $stamp;

    return ['years'=>$years,'months'=>$months,'weeks'=>$weeks,'days'=>$days,'hours'=>$hours,'minutes'=>$minutes];
}



function get_announcement_link($aid=0)
  {
	$link = str_replace("{aid}", $aid, ANNOUNCEMENT_URL);
	return htmlspecialchars_uni($link);
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






function log_admin_action()
{
	global $db, $mybb, $CURUSER;

	$data = func_get_args();

	if(count($data) == 1 && is_array($data[0]))
	{
		$data = $data[0];
	}

	if(!is_array($data))
	{
		$data = array($data);
	}

	$module = $db->escape_string($mybb->get_input('module', MyBB::INPUT_STRING));
	$action = $db->escape_string($mybb->get_input('action', MyBB::INPUT_STRING));
	$username = $db->escape_string($CURUSER['username']);
	
	// Определяем тип действия из данных
	$data_action = (!empty($data[0]) && is_string($data[0])) ? $data[0] : $action;
	
	$log_text = "";
	
	if($data_action == 'deletemod' && count($data) >= 4)
	{
		$log_text = "Deleted moderator #{$data[1]} ({$data[2]}) from forum #{$data[3]} ({$data[4]})";
	}
	elseif($data_action == 'editmod' && count($data) >= 4)
	{
		// editmod: [0:fid, 1:forum_name, 2:mid, 3:username]
		$log_text = "Edited moderator #{$data[2]} ({$data[3]}) in forum #{$data[0]} ({$data[1]})";
	}
	elseif($data_action == 'addmod' && count($data) >= 5)
	{
		// addmod: [0:addmod, 1:mid, 2:username, 3:fid, 4:forum_name]
		$log_text = "Added moderator #{$data[1]} ({$data[2]}) to forum #{$data[3]} ({$data[4]})";
	}
	elseif($action == 'add' && $module == 'moderators')
	{
		if(isset($data['username']))
		{
			$log_text = "Added user moderator: {$data['username']} to forum #{$data['fid']}";
		}
		elseif(isset($data['usergroup']))
		{
			$log_text = "Added group moderator: {$data['usergroup']} to forum #{$data['fid']}";
		}
	}
	elseif($action == 'permissions' && count($data) >= 2)
	{
		$log_text = "Edited group permissions for forum #{$data[0]} ({$data[1]})";
	}
	else
	{
		$log_text = "Admin action: {$username} - {$module}/{$action}";
		
		if(!empty($data))
		{
			$details = array();
			foreach($data as $key => $value)
			{
				if(is_scalar($value))
				{
					$details[] = "{$key}:{$value}";
				}
			}
			
			if(!empty($details))
			{
				$log_text .= " [" . implode(', ', $details) . "]";
			}
		}
	}

	if(mb_strlen($log_text) > 255)
	{
		$log_text = mb_substr($log_text, 0, 252) . '...';
	}

	$db->insert_query("sitelog", array(
		"added" => TIMENOW,
		"txt" => $db->escape_string($log_text)
	));
}







function join_usergroup($uid, $joingroup)
{
	global $db, $mybb;

	if($uid == $mybb->user['uid'])
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "additionalgroups, usergroup", "id='".(int)$uid."'");
		$user = $db->fetch_array($query);
	}

	// Build the new list of additional groups for this user and make sure they're in the right format
	$groups = array_map(
		'intval',
		explode(',', $user['additionalgroups'])
	);

	if(!in_array((int)$joingroup, $groups))
	{
		$groups[] = (int)$joingroup;
		$groups = array_diff($groups, array($user['usergroup']));
		$groups = array_unique($groups);

		$groupslist = implode(',', $groups);

		$db->update_query("users", array('additionalgroups' => $groupslist), "id='".(int)$uid."'");
		return true;
	}
	else
	{
		return false;
	}
}









function save_quick_perms($fid)
{
	global $db, $inherit, $canview, $canpostthreads, $canpostreplies, $canpostpolls, $canpostattachments, $cache;

	$permission_fields = array();

	$field_list = $db->show_fields_from("forumpermissions");
	foreach($field_list as $field)
	{
		if(strpos($field['Field'], 'can') !== false || strpos($field['Field'], 'mod') !== false)
		{
			$permission_fields[$field['Field']] = 1;
		}
	}

	// "Can Only View Own Threads" and "Can Only Reply Own Threads" permissions are forum permission only options
	$usergroup_permission_fields = $permission_fields;
	unset($usergroup_permission_fields['canonlyviewownthreads']);
	unset($usergroup_permission_fields['canonlyreplyownthreads']);

	$query = $db->simple_select("usergroups", "gid");
	while($usergroup = $db->fetch_array($query))
	{
		$query2 = $db->simple_select("forumpermissions", $db->escape_string(implode(',', array_keys($permission_fields))), "fid='{$fid}' AND gid='{$usergroup['gid']}'", array('limit' => 1));
		$existing_permissions = $db->fetch_array($query2);

		if(!$existing_permissions)
		{
			$query2 = $db->simple_select("usergroups", $db->escape_string(implode(',', array_keys($usergroup_permission_fields))), "gid='{$usergroup['gid']}'", array('limit' => 1));
			$existing_permissions = $db->fetch_array($query2);
		}

		// Delete existing permissions
		$db->delete_query("forumpermissions", "fid='{$fid}' AND gid='{$usergroup['gid']}'");

		// Only insert the new ones if we're using custom permissions
		if(empty($inherit[$usergroup['gid']]))
		{
			if(!empty($canview[$usergroup['gid']]))
			{
				$pview = 1;
			}
			else
			{
				$pview = 0;
			}

			if(!empty($canpostthreads[$usergroup['gid']]))
			{
				$pthreads = 1;
			}
			else
			{
				$pthreads = 0;
			}

			if(!empty($canpostreplies[$usergroup['gid']]))
			{
				$preplies = 1;
			}
			else
			{
				$preplies = 0;
			}

			if(!empty($canpostpolls[$usergroup['gid']]))
			{
				$ppolls = 1;
			}
			else
			{
				$ppolls = 0;
			}

			if(!$preplies && !$pthreads)
			{
				$ppost = 0;
			}
			else
			{
				$ppost = 1;
			}

			$insertquery = array(
				"fid" => (int)$fid,
				"gid" => (int)$usergroup['gid'],
				"canview" => (int)$pview,
				"canpostthreads" => (int)$pthreads,
				"canpostreplys" => (int)$preplies,
				"canpostpolls" => (int)$ppolls,
			);

			foreach($permission_fields as $field => $value)
			{
				if(array_key_exists($field, $insertquery))
				{
					continue;
				}

				$insertquery[$db->escape_string($field)] = isset($existing_permissions[$field]) ? (int)$existing_permissions[$field] : 0;
			}

			$db->insert_query("forumpermissions", $insertquery);
		}
	}
	$cache->update_forumpermissions();
}








function generate_forum_select($name, $selected, $options=array(), $is_first=1)
{
		global $fselectcache, $forum_cache, $selectoptions;

		if(!$selectoptions)
		{
			$selectoptions = '';
		}

		if(!isset($options['depth']))
		{
			$options['depth'] = 0;
		}

		$options['depth'] = (int)$options['depth'];

		if(!isset($options['pid']))
		{
			$options['pid'] = 0;
		}

		$pid = (int)$options['pid'];

		if(!is_array($fselectcache))
		{
			if(!is_array($forum_cache))
			{
				$forum_cache = cache_forums();
			}

			foreach($forum_cache as $fid => $forum)
			{
				$fselectcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
		}

		if(isset($options['main_option']) && $is_first)
		{
			$select_add = '';
			if($selected == -1)
			{
				$select_add = " selected=\"selected\"";
			}

			$selectoptions .= "<option value=\"-1\"{$select_add}>{$options['main_option']}</option>\n";
		}

		if(isset($fselectcache[$pid]))
		{
			foreach($fselectcache[$pid] as $main)
			{
				foreach($main as $forum)
				{
					if($forum['fid'] != "0" && $forum['linkto'] == '')
					{
						$select_add = '';

						if(!empty($selected) && ($forum['fid'] == $selected || (is_array($selected) && in_array($forum['fid'], $selected))))
						{
							$select_add = " selected=\"selected\"";
						}

						$sep = '';
						if(isset($options['depth']))
						{
							$sep = str_repeat("&nbsp;", $options['depth']);
						}

						$style = "";
						if($forum['active'] == 0)
						{
							$style = " style=\"font-style: italic;\"";
						}

						$selectoptions .= "<option value=\"{$forum['fid']}\"{$style}{$select_add}>".$sep.htmlspecialchars_uni(strip_tags($forum['name']))."</option>\n";

						if($forum_cache[$forum['fid']])
						{
							$options['depth'] += 5;
							$options['pid'] = $forum['fid'];
							generate_forum_select($forum['fid'], $selected, $options, 0);
							$options['depth'] -= 5;
						}
					}
				}
			}
		}

		if($is_first == 1)
		{
			if(!isset($options['multiple']))
			{
				$select = "<select name=\"{$name}\" class=\"form-select form-select-sm border pe-5 w-auto\"";
			}
			else
			{
				$select = "<select name=\"{$name}\" multiple=\"multiple\"";
			}
			if(isset($options['class']))
			{
				$select .= " class=\"{$options['class']}\"";
			}
			if(isset($options['id']))
			{
				$select .= " id=\"{$options['id']}\"";
			}
			if(isset($options['size']))
			{
				$select .= " size=\"{$options['size']}\"";
			}
			$select .= ">\n".$selectoptions."</select>\n";
			$selectoptions = '';
			return $select;
		}
}









function fetch_page_url2($url, $page)
{
	if($page <= 1)
	{
		$find = array(
			"-page-{page}",
			"&amp;page={page}",
			"{page}"
		);

		// Remove "Page 1" to the defacto URL
		$url = str_replace($find, array("", "", $page), $url);
		return $url;
	}
	else if(strpos($url, "{page}") === false)
	{
		// If no page identifier is specified we tack it on to the end of the URL
		if(strpos($url, "?") === false)
		{
			$url .= "?";
		}
		else
		{
			$url .= "&amp;";
		}

		$url .= "page=$page";
	}
	else
	{
		$url = str_replace("{page}", $page, $url);
	}

	return $url;
}



function draw_admin_pagination($page, $per_page, $total_items, $url)
{
	global $mybb, $lang, $maxmultipagelinks;

	if($total_items <= $per_page)
	{
		return '';
	}

	$pages = ceil($total_items / $per_page);

	$pagination = "<div class=\"pagination\"><span class=\"pages\">Pages: </span>\n";

	if($page > 1)
	{
		$prev = $page-1;
		$prev_page = fetch_page_url2($url, $prev);
		//$pagination .= "<a href=\"{$prev_page}\" class=\"pagination_previous\">&laquo; Previous</a> \n";
		
		
		$pagination .= "<a href=\"{$prev_page}\" class=\"pagination_previous\"><span class=\"btn btn-page text-uppercase\">&nbsp;<i class=\"fa-solid fa-angle-left\"></i>&nbsp;</span></a> \n";
		
		
	}

	// Maximum number of "page bits" to show
	if(!$maxmultipagelinks)
	{
		$maxmultipagelinks = 5;
	}

	$max_links = $maxmultipagelinks;

	$from = $page-floor($maxmultipagelinks/2);
	$to = $page+floor($maxmultipagelinks/2);

	if($from <= 0)
	{
		$from = 1;
		$to = $from+$max_links-1;
	}

	if($to > $pages)
	{
		$to = $pages;
		$from = $pages-$max_links+1;
		if($from <= 0)
		{
			$from = 1;
		}
	}

	if($to == 0)
	{
		$to = $pages;
	}

	if($from > 2)
	{
		$first = fetch_page_url2($url, 1);
		$pagination .= "<a href=\"{$first}\" title=\"Page 1\" class=\"pagination_first\">1</a> ... ";
	}

	for($i = $from; $i <= $to; ++$i)
	{
		$page_url = fetch_page_url2($url, $i);
		if($page == $i)
		{
			//$pagination .= "<span class=\"page-link\">{$i}</span> \n";
			
			
			$pagination .= '<li class="page-item active"><a class="page-link">'.$i.'</a></li>';
			
			
			
			
			
		}
		else
		{
			$pagination .= "<a href=\"{$page_url}\" title=\"Page {$i}\">{$i}</a> \n";
				
			
		}
	}

	if($to < $pages)
	{
		$last = fetch_page_url2($url, $pages);
		$pagination .= "... <a href=\"{$last}\" title=\"Page {$pages}\" class=\"pagination_last\">{$pages}</a>";
	}

	if($page < $pages)
	{
		$next = $page+1;
		$next_page = fetch_page_url2($url, $next);
		//$pagination .= " <a href=\"{$next_page}\" class=\"pagination_next\">Next &raquo;</a>\n";
		
		
		$pagination .= " <a href=\"{$next_page}\" class=\"pagination_next\"><span class=\"text-uppercase btn btn-page\">&nbsp;<i class=\"fa-solid fa-angle-right\"></i>&nbsp;</span></a>\n";
		
		
	}
	$pagination .= "</div>\n";
	return $pagination;
}







function mk_path_abs22($path, $base = TSDIR)
{
	$iswin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$char1 = my_substr($path, 0, 1);
	if($char1 != '/' && !($iswin && ($char1 == '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path))))
	{
		$path = $base.$path;
	}

	return $path;
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
				if(my_substr($attach_icons_schemes[$ext], 0, 1) != "")
				{
					$attach_icons_schemes[$ext] = "../".$attach_icons_schemes[$ext];
				}
			}
			elseif(defined("IN_PORTAL"))
			{
				global $change_dir;
				$attach_icons_schemes[$ext] = $change_dir."".str_replace("{theme}", $theme['imgdir'], $attachtypes[$ext]['icon']);
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
			$theme['imgdir'] = "../pic";
		}
		else if(defined("IN_PORTAL"))
		{
			global $change_dir;
			$theme['imgdir'] = "{$change_dir}/pic";
		}

		$icon = "{$theme['imgdir']}/attachtypes/unknown.png";

		$name = 'unknown';
	}

	$icon = htmlspecialchars_uni($icon);
	$attachment_icon = '<img src="'.$icon.'" title="'.$name.'" style="height: 16px; width: 16px" border="0" alt=".'.$ext.'" />';
	
	return $attachment_icon;
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















function fetch_ban_times()
{
	global $plugins, $lang;

	// Days-Months-Years
	$ban_times = array(
		"1-0-0" => "1 Day",
		"2-0-0" => "2 Days",
		"3-0-0" => "3 Days",
		"4-0-0" => "4 Days",
		"5-0-0" => "5 Days",
		"6-0-0" => "6 Days",
		"7-0-0" => "1 Week",
		"14-0-0" => "2 Weeks",
		"21-0-0" => "3 Weeks",
		"0-1-0" => "1 Month",
		"0-2-0" => "2 Months",
		"0-3-0" => "3 Months",
		"0-4-0" => "4 Months",
		"0-5-0" => "5 Months",
		"0-6-0" => "6 Months",
		"0-0-1" => "1 Year",
		"0-0-2" => "2 Years"
	);

	$ban_times = $plugins->run_hooks("functions_fetch_ban_times", $ban_times);

	$ban_times['---'] = 'Permanent';
	return $ban_times;
}


function ban_date2timestamp($date, $stamp=0)
{
	if($stamp == 0)
	{
		$stamp = TIMENOW;
	}
	$d = explode('-', $date);
	$nowdate = date("H-j-n-Y", $stamp);
	$n = explode('-', $nowdate);
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(date("G", $stamp), date("i", $stamp), 0, $n[2], $n[1], $n[3]);
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







function output_inline_error($errors)
{
		global $lang;

		if(!is_array($errors))
		{
			$errors = array($errors);
		}
		
		
		echo "<div class=\"container mt-3\">\n";
		echo "<div class=\"red_alert\">\n";
		
		echo "<p><em>The following errors were encountered:</em></p>\n";
		echo "<ul>\n";
		foreach($errors as $error)
		{
			echo "<li>{$error}</li>\n";
		}
		echo "</ul>\n";
		echo "</div></div>\n";
}




function output_nav_tabs($tabs=array(), $active='')
{
		global $plugins;
		$tabs = $plugins->run_hooks("admin_page_output_nav_tabs_start", $tabs);
		echo "<div class=\"container mt-3\"><div class=\"nav_tabs\">";
		echo "\t<ul>\n";
		foreach($tabs as $id => $tab)
		{
			$class = '';
			if($id == $active)
			{
				$class = ' active';
			}
			if(isset($tab['align']) == "right")
			{
				$class .= " right";
			}
			$target = '';
			if(isset($tab['link_target']))
			{
				$target = " target=\"{$tab['link_target']}\"";
			}
			$rel = '';
			if(isset($tab['link_rel']))
			{
				$rel = " rel=\"{$tab['link_rel']}\"";
			}
			if(!isset($tab['link']))
			{
				$tab['link'] = '';
			}
			echo "\t\t<li class=\"{$class}\"><a href=\"{$tab['link']}\"{$target}{$rel}>{$tab['title']}</a></li>\n";
			$target = '';
		}
		echo "\t</ul>\n";
		if(!empty($tabs[$active]['description']))
		{
			echo "\t<div class=\"tab_description\">{$tabs[$active]['description']}</div>\n";
		}
		echo "</div></div>";
		$arguments = array('tabs' => $tabs, 'active' => $active);
		$plugins->run_hooks("admin_page_output_nav_tabs_end", $arguments);
}




function generate_radio_button($name, $value="", $label="", $options=array())
{
		$input = "<label";
		if(isset($options['id']))
		{
			$input .= " for=\"{$options['id']}\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"label_{$options['class']}\"";
		}
		$input .= "><input type=\"radio\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"form-check-input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"form-check-input\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['checked']) && $options['checked'] != 0)
		{
			$input .= " checked=\"checked\"";
		}
		$input .= " />";
		if($label != "")
		{
			$input .= $label;
		}
		$input .= "</label>";
		return $input;
}


function generate_numeric_field($name, $value=0, $options=array())
{
		if(is_numeric($value))
		{
			$value = (float)$value;
		}
		else
		{
			$value = '';
		}

		$input = "<input type=\"number\" name=\"{$name}\" value=\"{$value}\"";
		if(isset($options['min']))
		{
			$input .= " min=\"".$options['min']."\"";
		}
		if(isset($options['max']))
		{
			$input .= " max=\"".$options['max']."\"";
		}
		if(isset($options['step']))
		{
			$input .= " step=\"".$options['step']."\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"form-control ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"form-control\"";
		}
		if(isset($options['style']))
		{
			$input .= " style=\"".$options['style']."\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
}



function generate_check_box($name, $value="", $label="", $options=array())
{
		$input = "<label";
		if(isset($options['id']))
		{
			$input .= " for=\"{$options['id']}\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"label_{$options['class']}\"";
		}
		$input .= "><input type=\"checkbox\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"form-check-input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"form-check-input\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['checked']) && ($options['checked'] === true || $options['checked'] == 1))
		{
			$input .= " checked=\"checked\"";
		}
		if(isset($options['onclick']))
		{
			$input .= " onclick=\"{$options['onclick']}\"";
		}
		$input .= " /> ";
		if($label != "")
		{
			$input .= $label;
		}
		$input .= "</label>";
		return $input;
}


function generate_text_box($name, $value="", $options=array())
{
		$input = "<input type=\"text\" name=\"".$name."\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"form-control ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"form-control\"";
		}
		if(isset($options['style']))
		{
			$input .= " style=\"".$options['style']."\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
}



function generate_select_box($name, $option_list=array(), $selected=array(), $options=array())
{
		if(!isset($options['multiple']))
		{
			$select = "<select class=\"form-select border form-select-sm w-auto pe-5\" name=\"{$name}\"";
		}
		else
		{
			$select = "<select name=\"{$name}\" multiple=\"multiple\"";
			if(!isset($options['size']))
			{
				$options['size'] = count($option_list);
			}
		}
		if(isset($options['class']))
		{
			$select .= " class=\"{$options['class']}\"";
		}
		if(isset($options['id']))
		{
			$select .= " id=\"{$options['id']}\"";
		}
		if(isset($options['size']))
		{
			$select .= " size=\"{$options['size']}\"";
		}
		$select .= ">\n";
		foreach($option_list as $value => $option)
		{
			$select_add = '';
			if((!is_array($selected) || !empty($selected)) && ((is_array($selected) && in_array((string)$value, $selected)) || (!is_array($selected) && (string)$value === (string)$selected)))
			{
				$select_add = " selected=\"selected\"";
			}
			$select .= "<option value=\"{$value}\"{$select_add}>{$option}</option>\n";
		}
		$select .= "</select>\n";
		return $select;
}


function generate_hidden_field($name, $value, $options=array())
{
	$input = "<input type=\"hidden\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
	if(isset($options['id']))
	{
		$input .= " id=\"".$options['id']."\"";
    }
	$input .= " />";
	return $input;
}




function subforums_count($array=array())
{
	$count = 0;
	foreach($array as $array2)
	{
		$count += count($array2);
	}

	return $count;
}





function flash_message($message = null, $type = 'info', $raw_html = false)
{
    // Добавляем новое сообщение в сессию
    if ($message !== null) {
        $_SESSION['flash'][] = [
            'message' => $message,
            'type'    => $type,
            'raw'     => $raw_html
        ];
        return;
    }

    // Если есть сообщения — выводим
    if (!empty($_SESSION['flash'])) {
        echo '
        <div aria-live="polite" aria-atomic="true" class="position-relative">
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">';

        foreach ($_SESSION['flash'] as $flash) {
            $typeClass = match ($flash['type']) {
                'success' => 'bg-success text-white',
                'error', 'danger' => 'bg-danger text-white',
                'warning' => 'bg-warning text-dark',
                default => 'bg-info text-white',
            };

            // если raw_html = true, не экранируем HTML
            $msg = $flash['raw'] ? $flash['message'] : htmlspecialchars($flash['message']);

            echo "
            <div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header {$typeClass}'>
                    <strong class='me-auto'>Message</strong>
                    <small>Now</small>
                    <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>
                </div>
                <div class='toast-body'>
                    {$msg}
                </div>
            </div>";
        }

        echo '
          </div>
        </div> 
        <script>
          document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".toast").forEach(function(toastEl) {
              let toast = new bootstrap.Toast(toastEl, { delay: 5000 });
              toast.show();
            });
          });
        </script>';

        unset($_SESSION['flash']); // очищаем после показа
    }
}





function admin_redirect($url)
{
	if(!headers_sent())
	{
		$url = str_replace("&amp;", "&", $url);
		header("Location: $url");
	}
	else
	{
		echo "<meta http-equiv=\"refresh\" content=\"0; url={$url}\">";
	}
	exit;
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


function make_parent_list($fid, $navsep=",")
{
	global $pforumcache, $db;

	if(!$pforumcache)
	{
		$query = $db->simple_select("tsf_forums", "name, fid, pid", "", array("order_by" => "disporder, pid"));
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum['fid']][$forum['pid']] = $forum;
		}
	}

	reset($pforumcache);
	reset($pforumcache[$fid]);

	$navigation = '';

	foreach($pforumcache[$fid] as $key => $forum)
	{
		if($fid == $forum['fid'])
		{
			if(!empty($pforumcache[$forum['pid']]))
			{
				$navigation = make_parent_list($forum['pid'], $navsep).$navigation;
			}

			if($navigation)
			{
				$navigation .= $navsep;
			}
			$navigation .= $forum['fid'];
		}
	}
	return $navigation;
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









/////////////////////////////////////////////////////////////////////////////////////////////MyBB Functions
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
		WHERE fid='{$fid}' AND visible='1'
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


function delete_attachments ($pid, $tid, $aid = '')
{
    global $f_upload_path, $db;
    $delete_files = array ();
    //$query = $db->sql_query ('SELECT a_name FROM ' . TSF_PREFIX . 'attachments WHERE a_pid = ' . $db->escape_string($pid) . ' AND a_tid = ' . $db->escape_string($tid));
	
	
	$query = $db->simple_select("tsf_attachments", "a_name", "a_pid='{$pid}' AND a_tid='{$tid}'");
	
    if (0 < $db->num_rows ($query))
    {
      while ($delete = mysqli_fetch_assoc ($query))
      {
        $delete_files[] = $delete['a_name'];
      }
    }

    if (0 < count ($delete_files))
    {
      foreach ($delete_files as $nowdelete)
      {
        if (file_exists ($f_upload_path . $nowdelete))
        {
          unlink ($f_upload_path . $nowdelete);
          continue;
        }
      }
    }

    $db->sql_query ('DELETE FROM ' . TSF_PREFIX . 'attachments WHERE a_pid = ' . $db->escape_string($pid) . ' AND a_tid = ' . $db->escape_string($tid) . ($aid ? ' AND a_id = ' . $db->escape_string($aid) : ''));
}


/////////////////////////////////////////////////////////////////////////////////////////////

  function admin_scripts ()
  {
    global $lang;
    echo '
	<script type="text/javascript">
		function ts_check_field(TSarrayLength)
		{			
			for (var TSloop=1; TSloop <= TSarrayLength; TSloop++)
			{
				var checkField = document.forms[0].elements[TSloop];
				if (checkField.value == "")
				{
					alert("Please don\'t leave required fields blank!\\n\\nEmpty Field: "+checkField.name);
					document.forms[0].elements[TSloop].focus();
					return false;
				}
			}
		};
	</script>
	';
  }

  
  
  
  
  


  
  
  
  
  
  
  
  
  



function get_list()
{
    global $thispath;
    global $_this_script_no_act;
    global $CURUSER;
    global $eol;
    global $db;
    
    $query = $db->sql_query('SELECT * FROM staffpanel WHERE usergroups LIKE \'%[' . intval($CURUSER['usergroup']) . ']%\' ORDER BY name');
    
    $str = '
    <style type="text/css">
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        padding: 16px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .tool-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 16px;
        transition: all 0.2s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,0.03);
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .tool-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--card-accent, linear-gradient(90deg, #3b82f6, #60a5fa));
        transform: scaleX(0);
        transition: transform 0.2s ease;
    }
    
    .tool-card:hover::before {
        transform: scaleX(1);
    }
    
    .tool-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 20px var(--card-shadow, rgba(59,130,246,0.1));
        border-color: var(--card-border, #e0f2fe);
        background: var(--card-hover-bg, #fafeff);
    }
    
    .tool-header {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .tool-icon-wrapper {
        position: relative;
        flex-shrink: 0;
    }
    
    .tool-icon {
        width: 40px;
        height: 40px;
        background: var(--icon-bg, linear-gradient(135deg, #f0f9ff, #e0f2fe));
        border: 1px solid var(--icon-border, #e0f2fe);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--icon-color, #3b82f6);
        font-size: 1.1em;
        transition: all 0.2s ease;
        position: relative;
        z-index: 2;
    }
    
    .tool-card:hover .tool-icon {
        background: var(--icon-gradient, linear-gradient(135deg, #3b82f6, #60a5fa));
        color: white;
        border-color: var(--icon-color, #3b82f6);
        transform: scale(1.05);
    }
    
    .tool-content {
        flex: 1;
        min-width: 0;
    }
    
    .tool-name {
        font-size: 0.9em;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px 0;
        line-height: 1.2;
        transition: color 0.2s ease;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .tool-card:hover .tool-name {
        color: var(--icon-color, #3b82f6);
    }
    
    .tool-description {
        color: #64748b;
        font-size: 0.75em;
        line-height: 1.3;
        margin: 0;
        transition: color 0.2s ease;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .tool-card:hover .tool-description {
        color: #475569;
    }
    
    .tool-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
    }
    
    .tool-badge {
        background: var(--badge-bg, #f0f9ff);
        color: var(--badge-color, #0369a1);
        padding: 3px 8px;
        border-radius: 8px;
        font-size: 0.7em;
        font-weight: 600;
        border: 1px solid var(--badge-border, #e0f2fe);
        transition: all 0.2s ease;
    }
    
    .tool-card:hover .tool-badge {
        background: var(--icon-color, #3b82f6);
        color: white;
        border-color: var(--icon-color, #3b82f6);
    }
    
    .tool-arrow {
        color: #cbd5e1;
        font-size: 0.8em;
        transition: all 0.2s ease;
    }
    
    .tool-card:hover .tool-arrow {
        color: var(--icon-color, #3b82f6);
        transform: translateX(2px);
    }
    
    /* Цветовые схемы для разных типов инструментов */
    .tool-card.blue {
        --icon-color: #3b82f6;
        --icon-bg: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        --icon-border: #e0f2fe;
        --icon-gradient: linear-gradient(135deg, #3b82f6, #60a5fa);
        --card-accent: linear-gradient(90deg, #3b82f6, #60a5fa);
        --card-shadow: rgba(59,130,246,0.1);
        --card-border: #e0f2fe;
        --card-hover-bg: #fafeff;
        --badge-bg: #f0f9ff;
        --badge-color: #0369a1;
        --badge-border: #e0f2fe;
    }
    
    .tool-card.green {
        --icon-color: #10b981;
        --icon-bg: linear-gradient(135deg, #ecfdf5, #d1fae5);
        --icon-border: #d1fae5;
        --icon-gradient: linear-gradient(135deg, #10b981, #34d399);
        --card-accent: linear-gradient(90deg, #10b981, #34d399);
        --card-shadow: rgba(16,185,129,0.1);
        --card-border: #d1fae5;
        --card-hover-bg: #f0fdf9;
        --badge-bg: #ecfdf5;
        --badge-color: #047857;
        --badge-border: #d1fae5;
    }
    
    .tool-card.purple {
        --icon-color: #8b5cf6;
        --icon-bg: linear-gradient(135deg, #faf5ff, #f3e8ff);
        --icon-border: #f3e8ff;
        --icon-gradient: linear-gradient(135deg, #8b5cf6, #a78bfa);
        --card-accent: linear-gradient(90deg, #8b5cf6, #a78bfa);
        --card-shadow: rgba(139,92,246,0.1);
        --card-border: #f3e8ff;
        --card-hover-bg: #faf7ff;
        --badge-bg: #faf5ff;
        --badge-color: #7c3aed;
        --badge-border: #f3e8ff;
    }
    
    .tool-card.orange {
        --icon-color: #f59e0b;
        --icon-bg: linear-gradient(135deg, #fffbeb, #fef3c7);
        --icon-border: #fef3c7;
        --icon-gradient: linear-gradient(135deg, #f59e0b, #fbbf24);
        --card-accent: linear-gradient(90deg, #f59e0b, #fbbf24);
        --card-shadow: rgba(245,158,11,0.1);
        --card-border: #fef3c7;
        --card-hover-bg: #fefce8;
        --badge-bg: #fffbeb;
        --badge-color: #d97706;
        --badge-border: #fef3c7;
    }
    
    .tool-card.red {
        --icon-color: #ef4444;
        --icon-bg: linear-gradient(135deg, #fef2f2, #fee2e2);
        --icon-border: #fee2e2;
        --icon-gradient: linear-gradient(135deg, #ef4444, #f87171);
        --card-accent: linear-gradient(90deg, #ef4444, #f87171);
        --card-shadow: rgba(239,68,68,0.1);
        --card-border: #fee2e2;
        --card-hover-bg: #fef2f2;
        --badge-bg: #fef2f2;
        --badge-color: #dc2626;
        --badge-border: #fee2e2;
    }
    
    .tool-card.pink {
        --icon-color: #ec4899;
        --icon-bg: linear-gradient(135deg, #fdf2f8, #fce7f3);
        --icon-border: #fce7f3;
        --icon-gradient: linear-gradient(135deg, #ec4899, #f472b6);
        --card-accent: linear-gradient(90deg, #ec4899, #f472b6);
        --card-shadow: rgba(236,72,153,0.1);
        --card-border: #fce7f3;
        --card-hover-bg: #fdf2f8;
        --badge-bg: #fdf2f8;
        --badge-color: #db2777;
        --badge-border: #fce7f3;
    }
    
    .tool-card.cyan {
        --icon-color: #06b6d4;
        --icon-bg: linear-gradient(135deg, #ecfeff, #cffafe);
        --icon-border: #cffafe;
        --icon-gradient: linear-gradient(135deg, #06b6d4, #22d3ee);
        --card-accent: linear-gradient(90deg, #06b6d4, #22d3ee);
        --card-shadow: rgba(6,182,212,0.1);
        --card-border: #cffafe;
        --card-hover-bg: #ecfeff;
        --badge-bg: #ecfeff;
        --badge-color: #0891b2;
        --badge-border: #cffafe;
    }
    
    .tools-stats {
        text-align: center;
        padding: 16px;
        margin: 16px auto;
        max-width: 1000px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        position: relative;
        overflow: hidden;
    }
    
    .stats-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 20% 80%, rgba(59,130,246,0.02) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(96,165,250,0.02) 0%, transparent 50%);
    }
    
    .stats-content {
        position: relative;
        z-index: 2;
    }
    
    .stats-icon {
        font-size: 1.8em;
        color: #3b82f6;
        margin-bottom: 8px;
        opacity: 0.8;
    }
    
    .stats-text {
        font-size: 0.9em;
        color: #475569;
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .stats-count {
        color: #3b82f6;
        font-weight: 800;
        font-size: 1.5em;
        text-shadow: 0 1px 4px rgba(59,130,246,0.2);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        max-width: 400px;
        margin: 0 auto;
    }
    
    .empty-icon {
        font-size: 3em;
        color: #e2e8f0;
        margin-bottom: 16px;
        opacity: 0.7;
    }
    
    .empty-text {
        font-size: 1.1em;
        color: #64748b;
        margin-bottom: 6px;
        font-weight: 600;
    }
    
    .empty-subtext {
        font-size: 0.85em;
        color: #94a3b8;
        line-height: 1.4;
    }
    
    /* Анимация появления карточек */
    @keyframes cardSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .tool-card {
        animation: cardSlideIn 0.4s ease-out;
    }
    
    /* Задержка для последовательного появления */
    .tool-card:nth-child(odd) { animation-delay: 0.1s; }
    .tool-card:nth-child(even) { animation-delay: 0.2s; }
    
    /* Адаптивность */
    @media (max-width: 1024px) {
        .tools-grid {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            padding: 12px;
        }
    }
    
    @media (max-width: 768px) {
        .tools-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            padding: 10px;
        }
        
        .tool-card {
            padding: 12px;
            min-height: 100px;
        }
        
        .tool-header {
            flex-direction: column;
            text-align: center;
            gap: 8px;
        }
        
        .tool-icon {
            width: 32px;
            height: 32px;
            font-size: 1em;
        }
        
        .tool-name {
            font-size: 0.8em;
        }
        
        .tool-description {
            font-size: 0.7em;
            -webkit-line-clamp: 1;
        }
        
        .tool-footer {
            flex-direction: column;
            gap: 6px;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .tools-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>' . $eol;
    
    $count = 0;
    $tools_html = '';
    
    while ($tools = $db->fetch_array($query))
    {
        $usergroups = explode(',', $tools['usergroups']);
        if (((@file_exists($thispath . $tools['filename']) AND strstr($tools['usergroups'], '[' . $CURUSER['usergroup'] . ']')) AND in_array('[' . $CURUSER['usergroup'] . ']', $usergroups, true)))
        {
            $icon_data = get_tool_icon_and_color($tools['name']);
            $icon = $icon_data['icon'];
            $color_class = $icon_data['color'];
            $display_name = htmlspecialchars(ucwords(str_replace('_', ' ', $tools['name'])));
            
            $tools_html .= '
            <div class="tool-card ' . $color_class . '" onclick="window.location.href=\'' . $_this_script_no_act . '?act=' . $tools['name'] . '\'">
                <div class="tool-header">
                    <div class="tool-icon-wrapper">
                        <div class="tool-icon">
                            <i class="' . $icon . '"></i>
                        </div>
                    </div>
                    <div class="tool-content">
                        <h3 class="tool-name" title="' . $display_name . '">' . $display_name . '</h3>
                        <p class="tool-description" title="' . htmlspecialchars($tools['description']) . '">' . htmlspecialchars($tools['description']) . '</p>
                    </div>
                </div>
                <div class="tool-footer">
                    <span class="tool-badge">Tool</span>
                    <span class="tool-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </div>
            </div>' . $eol;
            
            ++$count;
        }
    }
    
    
	
	
	if ($count > 0) 
	{
        $str .= '<div class="container mt-3"><div class="tools-grid">' . $tools_html . '</div>' . $eol;
        
        // Статистика
        $str .= '
                <div class="py-3 border-top">
                    <div class="row g-3 text-center">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-toolbox fa-2x text-primary mb-2"></i>
                                <h5 class="mb-1">' . $count . '</h5>
                                <small class="text-muted">Total Tools</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h5 class="mb-1">Active</h5>
                                <small class="text-muted">All Systems</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <i class="fas fa-shield-alt fa-2x text-info mb-2"></i>
                                <h5 class="mb-1">Secure</h5>
                                <small class="text-muted">Staff Access</small>
                            </div>
                        </div>
                    </div>
                </div>';
    } 
	else 
	{
        $str .= '
                <div class="text-center py-5">
                    <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Tools Available</h5>
                    <p class="text-muted small">You don\'t have access to any staff tools at the moment.</p>
                </div>';
    }
	
	
	
    
    echo $str;
}


function get_tool_icon_and_color($tool_name) 
{
    $tool_lower = strtolower($tool_name);
    
    $icon_color_map = [
        'stats' => ['fas fa-chart-bar', 'blue'],
        'analytics' => ['fas fa-chart-pie', 'blue'],
        'log' => ['fas fa-clipboard-list', 'blue'],
        'history' => ['fas fa-history', 'blue'],
        'system' => ['fas fa-server', 'blue'],
        'database' => ['fas fa-database', 'blue'],
        'user' => ['fas fa-users', 'green'],
        'member' => ['fas fa-user-friends', 'green'],
        'moderate' => ['fas fa-gavel', 'green'],
        'forum' => ['fas fa-comments', 'green'],
        'post' => ['fas fa-comment', 'green'],
        'settings' => ['fas fa-cogs', 'purple'],
        'config' => ['fas fa-sliders-h', 'purple'],
        'tools' => ['fas fa-toolbox', 'purple'],
        'search' => ['fas fa-search', 'orange'],
        'find' => ['fas fa-binoculars', 'orange'],
        'security' => ['fas fa-shield-alt', 'red'],
        'protect' => ['fas fa-lock', 'red'],
        'ban' => ['fas fa-ban', 'red'],
        'report' => ['fas fa-flag', 'red'],
        'mail' => ['fas fa-envelope', 'pink'],
        'email' => ['fas fa-mail-bulk', 'pink'],
        'news' => ['fas fa-newspaper', 'pink'],
        'announce' => ['fas fa-bullhorn', 'pink'],
        'torrent' => ['fas fa-download', 'cyan'],
        'upload' => ['fas fa-upload', 'cyan'],
        'backup' => ['fas fa-database', 'cyan'],
        'restore' => ['fas fa-download', 'cyan'],
        'debug' => ['fas fa-bug', 'orange'],
        'test' => ['fas fa-vial', 'orange'],
        'money' => ['fas fa-coins', 'green'],
        'donation' => ['fas fa-hand-holding-usd', 'green'],
    ];
    
    foreach ($icon_color_map as $key => $data) {
        if (strpos($tool_lower, $key) !== false) {
            return ['icon' => $data[0], 'color' => $data[1]];
        }
    }
    
    return ['icon' => 'fas fa-toolbox', 'color' => 'blue'];
}










function get_list2()
{
    global $thispath;
    global $_this_script_;
    global $_this_script_no_act;
    global $eol;
    global $db;
    
    $query = $db->sql_query('SELECT * FROM staffpanel ORDER BY name');
    
    $str = '
    <style type="text/css">
    .admin-tools-table {
        width: 100%;
        background: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        border: 1px solid #f1f5f9;
        margin: 16px 0;
    }
    
    .tools-header {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .tools-title {
        font-size: 1.3em;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .tools-title i {
        color: #3b82f6;
        font-size: 1.2em;
    }
    
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 0;
    }
    
    .tool-row {
        display: contents;
    }
    
    .tool-item {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
        background: #ffffff;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .tool-item:nth-child(odd) {
        border-right: 1px solid #f1f5f9;
    }
    
    .tool-item:hover {
        background: #fafeff;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(59,130,246,0.06);
    }
    
    .tool-header-info {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
    }
    
    .tool-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 1px solid #e0f2fe;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 1em;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    
    .tool-item:hover .tool-icon {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: white;
        transform: scale(1.05);
    }
    
    .tool-info {
        flex: 1;
        min-width: 0;
    }
    
    .tool-name {
        font-size: 1em;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px 0;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .tool-description {
        color: #64748b;
        font-size: 0.8em;
        line-height: 1.3;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .tool-groups {
        background: #f8fafc;
        padding: 8px 10px;
        border-radius: 8px;
        margin: 8px 0;
        border: 1px solid #e2e8f0;
    }
    
    .groups-label {
        font-size: 0.7em;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 4px;
    }
    
    .groups-list {
        font-size: 0.8em;
        color: #1e293b;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .tool-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 8px;
    }
    
    .tool-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.7em;
        font-weight: 500;
        color: #059669;
        background: #ecfdf5;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #d1fae5;
    }
    
    .status-dot {
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    .tool-actions {
        display: flex;
        gap: 6px;
    }
    
    .tool-btn {
        padding: 6px 10px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75em;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        text-decoration: none;
        min-width: 50px;
    }
    
    .tool-btn.edit {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: white;
        box-shadow: 0 1px 4px rgba(59,130,246,0.3);
    }
    
    .tool-btn.edit:hover {
        background: linear-gradient(135deg, #1d4ed8, #3b82f6);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(59,130,246,0.4);
    }
    
    .tool-btn.delete {
        background: linear-gradient(135deg, #ef4444, #f87171);
        color: white;
        box-shadow: 0 1px 4px rgba(239,68,68,0.3);
    }
    
    .tool-btn.delete:hover {
        background: linear-gradient(135deg, #dc2626, #ef4444);
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(239,68,68,0.4);
    }
    
    .tools-footer {
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        text-align: center;
    }
    
    .tools-count {
        font-size: 0.9em;
        font-weight: 600;
        color: #475569;
    }
    
    .count-number {
        color: #3b82f6;
        font-weight: 800;
        font-size: 1.1em;
    }
    
    /* Адаптивность */
    @media (max-width: 1024px) {
        .tools-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        .admin-tools-table {
            border-radius: 12px;
            margin: 12px 0;
        }
        
        .tools-header {
            padding: 16px;
        }
        
        .tools-title {
            font-size: 1.1em;
        }
        
        .tool-item {
            padding: 14px;
            min-height: 160px;
        }
        
        .tool-header-info {
            flex-direction: row;
            text-align: left;
            gap: 10px;
        }
        
        .tool-item:nth-child(odd) {
            border-right: none;
        }
        
        .tools-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .tool-header-info {
            flex-direction: column;
            text-align: center;
            gap: 8px;
        }
        
        .tool-icon {
            align-self: center;
        }
        
        .tool-meta {
            flex-direction: column;
            gap: 8px;
            align-items: stretch;
        }
        
        .tool-actions {
            justify-content: center;
        }
    }
    
    /* Анимация появления */
    .tool-item {
        animation: slideInUp 0.4s ease-out;
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Задержка для последовательной анимации */
    .tool-item:nth-child(2n) {
        animation-delay: 0.1s;
    }
    </style>' . $eol;
    
    $count = 0;
    $tools_html = '';
    
    while ($tools = $db->fetch_array($query))
    {
        if (@file_exists($thispath . $tools['filename']))
        {
            $usergroups = str_replace(array('[', ']'), '', $tools['usergroups']);
            $icon = get_tool_icon_admin($tools['name']);
            
            $tools_html .= '
            <div class="tool-row">
                <div class="tool-item">
                    <div>
                        <div class="tool-header-info">
                            <div class="tool-icon">
                                <i class="' . $icon . '"></i>
                            </div>
                            <div class="tool-info">
                                <h3 class="tool-name" title="' . htmlspecialchars($tools['name']) . '">' . htmlspecialchars(strtoupper($tools['name'])) . '</h3>
                                <p class="tool-description" title="' . htmlspecialchars($tools['description']) . '">' . htmlspecialchars($tools['description']) . '</p>
                            </div>
                        </div>
                        
                        <div class="tool-groups">
                            <div class="groups-label">Access</div>
                            <div class="groups-list" title="' . htmlspecialchars($usergroups) . '">' . htmlspecialchars($usergroups) . '</div>
                        </div>
                    </div>
                    
                    <div class="tool-meta">
                        <div class="tool-status">
                            <span class="status-dot"></span>
                            <span>Active</span>
                        </div>
                        <div class="tool-actions">
                            <a class="tool-btn edit" href="' . $_this_script_ . '&do=edit&id=' . $tools['id'] . '" title="Edit Tool">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a class="tool-btn delete" href="' . $_this_script_ . '&do=delete&id=' . $tools['id'] . '" onclick="return confirm(\'Delete this tool?\')" title="Delete Tool">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>' . $eol;
            
            ++$count;
        }
    }
    
    $str .= '
    <div class="admin-tools-table">
        <div class="tools-header">
            <h2 class="tools-title">
                <i class="fas fa-cogs"></i>
                Staff Tools
            </h2>
        </div>
        
        <div class="tools-grid">' . $tools_html . '</div>
        
        <div class="tools-footer">
            <div class="tools-count">
                <span class="count-number">' . $count . '</span> tools available
            </div>
        </div>
    </div>' . $eol;
    
    echo $str;
}

// Функция для получения иконок инструментов (остается без изменений)
function get_tool_icon_admin($tool_name) {
    $tool_lower = strtolower($tool_name);
    
    $icon_map = [
        'user' => 'fas fa-users',
        'search' => 'fas fa-search',
        'stats' => 'fas fa-chart-bar',
        'log' => 'fas fa-clipboard-list',
        'settings' => 'fas fa-cogs',
        'security' => 'fas fa-shield-alt',
        'backup' => 'fas fa-database',
        'mail' => 'fas fa-envelope',
        'report' => 'fas fa-flag',
        'moderate' => 'fas fa-gavel',
        'torrent' => 'fas fa-download',
        'forum' => 'fas fa-comments',
        'system' => 'fas fa-server',
        'debug' => 'fas fa-bug',
        'test' => 'fas fa-vial',
        'money' => 'fas fa-coins',
        'news' => 'fas fa-newspaper',
        'announce' => 'fas fa-bullhorn'
    ];
    
    foreach ($icon_map as $key => $icon) {
        if (strpos($tool_lower, $key) !== false) {
            return $icon;
        }
    }
    
    return 'fas fa-toolbox';
}













  
  

  function _end_ ($head = true)
  {
    if ($head)
    {
      stdhead ('Permission Denied!');
    }

    echo '<br /><font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> You have no permission!</font><br />';
    if ($head)
    {
      stdfoot ();
    }

    exit ();
  }

  function _access_check_ ()
  {
    global $usergroups;
    if ($usergroups['cansettingspanel'] != 1)
    {
      print_no_permission (true);
      exit ();
      return null;
    }

  }

  function _file_access_check_ ($name)
  {
    global $CURUSER, $db;
    //$query = $db->sql_query ('SELECT usergroups FROM staffpanel WHERE name = ' . $db->escape_string($name));
	
	$query = $db->simple_select("staffpanel", "usergroups", "name = '{$name}'");
	
    if ($db->num_rows ($query) == 0)
    {
      return null;
    }

    $result = $db->fetch_array($query);
    $usergroups = explode (',', $result['usergroups']);
    if ((!strstr ($result['usergroups'], '[' . $CURUSER['usergroup'] . ']') OR !in_array ('[' . $CURUSER['usergroup'] . ']', $usergroups, true)))
    {
      print_no_permission (true);
      exit ();
      return null;
    }

  }

  function _calculate_ ($value)
  {
    return mksize ($value);
  }

  function _form_open_ ($values = '', $hidden_values = '')
  {
    global $_this_script_;
    global $act;
    echo '<form method="post" action="' . $_this_script_ . '">
	<input type="hidden" class="btn btn-primary" name="act" value="' . $act . '">';
    if (is_array ($values))
    {
      foreach ($values as $val)
      {
        echo $val;
      }
    }
    else
    {
      if (!empty ($values))
      {
        echo $values;
      }
    }

    if (is_array ($hidden_values))
    {
      foreach ($hidden_values as $hidden)
      {
        echo $hidden;
      }

      return null;
    }

    if (!empty ($hidden_values))
    {
      echo $hidden_values;
    }

  }

  function _form_close_ ($button = 'save')
  {
    echo '<input type="submit" value="' . $button . '" class="btn btn-primary"></form>';
  }

  function _form_header_open_ ($text, $colspan = 4)
  {
    echo '
	
	<div class="container mt-3">
	<table align="center" border="0" class="tborder" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="thead" colspan="' . $colspan . '" align="center">' . $text . '</td></tr>';
  }
  
  

  function _form_header_close_ ()
  {
    echo '</table></tbody></td></tr></table></tbody></div>';
  }

  function _selectbox_ ($text = '', $name = '', $any = true, $anytext = 'any usergroup (all)', $selected = '')
  {
    global $db;
	$selectbox = (!empty ($text) ? $text . ':' : '') . ' <label><select name=' . $name . ' class="form-select form-select-sm border pe-5 w-auto">
	' . ($any ? '<option value="-" style="color: gray;">' . $anytext . '</option>' : '');
    $query_ug = $db->sql_query ('SELECT gid,title FROM usergroups');
    while ($tclass = $db->fetch_array ($query_ug))
    {
      $selectbox .= '<option value="' . $tclass['gid'] . '" ' . ($selected == $tclass['gid'] ? 'SELECTED' : '') . '>' . $tclass['title'] . '</option>';
    }

    $selectbox .= '</select></label>';
    return $selectbox;
  }

  function _get_file_type_ ($file)
  {
    $path_chunks = explode ('/', $file);
    $thefile = $path_chunks[count ($path_chunks) - 1];
    $dotpos = strrpos ($thefile, '.');
    return strtolower (substr ($thefile, $dotpos + 1));
  }








function menu($selected = '') {
    global $usergroups, $_this_script_, $_this_script_no_act;
    
    print '
    
	<div class="container mt-3">
	
	<div class="admin-menu-wrapper light-theme white-bg">
        <div class="menu-background"></div>
        <div class="menu-container">
            <div class="menu-header stat-card">
                <div class="menu-title">
                    <i class="fas fa-tachometer-alt blue-icon"></i>
                    <span>Admin Control Panel</span>
                </div>
                <div class="menu-status">
                    <span class="status-dot"></span>
                    <span>Online</span>
                </div>
            </div>
            
            <nav class="menu-nav">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'welcome' ? 'active' : '') . '" href="' . $_this_script_no_act . '">
                            <div class="menu-icon">
                                <i class="fas fa-home blue-icon"></i>
                            </div>
                            <span class="menu-text">Welcome</span>
                            <div class="menu-badge">🏠</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'stafftools' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=stafftools">
                            <div class="menu-icon">
                                <i class="fas fa-users-cog blue-icon"></i>
                            </div>
                            <span class="menu-text">Staff Tools</span>
                            <div class="menu-badge">👥</div>
                        </a>
                    </li>';

    if ($usergroups['cansettingspanel'] == 1) {
        print '
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'managestafftools' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=managestafftools">
                            <div class="menu-icon">
                                <i class="fas fa-wrench blue-icon"></i>
                            </div>
                            <span class="menu-text">Manage Staff Tools</span>
                            <div class="menu-badge">🔧</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($_GET['do'] == 'newtool' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=managestafftools&do=newtool">
                            <div class="menu-icon">
                                <i class="fas fa-plus-square blue-icon"></i>
                            </div>
                            <span class="menu-text">Add New Tool</span>
                            <div class="menu-badge">➕</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'securitycheck' ? 'active' : '') . '" href="' . $_this_script_no_act . '?act=securitycheck">
                            <div class="menu-icon">
                                <i class="fas fa-shield-alt blue-icon"></i>
                            </div>
                            <span class="menu-text">Security Console</span>
                            <div class="menu-badge security">🔒</div>
                        </a>
                    </li>
                    
                    <li class="menu-item">
                        <a class="menu-link stat-card ' . ($selected == 'settings' ? 'active' : '') . '" href="settings.php">
                            <div class="menu-icon">
                                <i class="fas fa-cogs blue-icon"></i>
                            </div>
                            <span class="menu-text">Tracker Settings</span>
                            <div class="menu-badge">⚙️</div>
                        </a>
                    </li>';
    }
    
    print '
                </ul>
            </nav>
            
            
        </div>
    </div>
	 </div>

    <style>
    /* Синие иконки */
    .blue-icon {
        color: #3b82f6 !important;
        transition: all 0.3s ease;
    }
    
    /* Базовые стили для белого фона */
    .admin-menu-wrapper.light-theme.white-bg {
        position: relative;
        margin: 30px 0;
        background: #ffffff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        min-height: 120px;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .admin-menu-wrapper.light-theme.white-bg .menu-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 20% 80%, rgba(59,130,246,0.03) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(37,99,235,0.03) 0%, transparent 50%);
        animation: backgroundFloat 8s ease-in-out infinite;
        opacity: 0.6;
    }
    
    @keyframes backgroundFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(0.5deg); }
    }
    
    /* Стили для stat-card */
    .admin-menu-wrapper .stat-card {
        padding: 1.25rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226,232,240,0.5);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        display: flex;
        align-items: center;
    }
    
    .admin-menu-wrapper .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(59,130,246,0.15);
        background: rgba(255,255,255,0.85);
        border-color: rgba(59,130,246,0.2);
    }
    
    /* Hover эффект для синих иконок */
    .admin-menu-wrapper .stat-card:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 8px rgba(59,130,246,0.4));
        transform: scale(1.1);
    }
    
    /* Активное состояние */
    .admin-menu-wrapper .stat-card.active {
        background: rgba(59,130,246,0.08);
        border-color: rgba(59,130,246,0.3);
        box-shadow: 0 5px 20px rgba(59,130,246,0.15);
        transform: translateY(-2px);
    }
    
    .admin-menu-wrapper .stat-card.active::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        border-radius: 0 0 12px 12px;
    }
    
    .admin-menu-wrapper .stat-card.active .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 10px rgba(59,130,246,0.6));
    }
    
    /* Health-stat для футера */
    .admin-menu-wrapper .health-stat {
        padding: 0.75rem 1rem;
        border-radius: 10px;
        background: rgba(0,0,0,0.03);
        border: 1px solid rgba(226,232,240,0.5);
        transition: all 0.3s ease;
        margin-top: auto;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .admin-menu-wrapper .health-stat:hover {
        background: rgba(59,130,246,0.05);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59,130,246,0.1);
        border-color: rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .health-stat:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 5px rgba(59,130,246,0.3));
    }
    
    /* Хедер меню */
    .admin-menu-wrapper .menu-header.stat-card {
        margin-bottom: 20px;
        justify-content: space-between;
        background: rgba(255,255,255,0.9);
        border-radius: 15px;
    }
    
    .admin-menu-wrapper .menu-header.stat-card:hover {
        transform: none;
        box-shadow: 0 5px 15px rgba(59,130,246,0.1);
    }
    
    .admin-menu-wrapper .menu-header.stat-card:hover .blue-icon {
        color: #1d4ed8 !important;
        filter: drop-shadow(0 0 6px rgba(59,130,246,0.3));
    }
    
    /* Список меню */
    .admin-menu-wrapper .menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .admin-menu-wrapper .menu-item {
        flex: 1;
        min-width: 200px;
    }
    
    /* Иконки в карточках с синим фоном */
    .admin-menu-wrapper .menu-icon {
        width: 40px;
        height: 40px;
        background: rgba(59,130,246,0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid rgba(59,130,246,0.2);
        transition: all 0.3s ease;
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-icon {
        background: rgba(59,130,246,0.15);
        transform: scale(1.05);
        border-color: rgba(59,130,246,0.3);
        box-shadow: 0 4px 12px rgba(59,130,246,0.15);
    }
    
    .admin-menu-wrapper .stat-card.active .menu-icon {
        background: rgba(59,130,246,0.2);
        border-color: rgba(59,130,246,0.4);
    }
    
    /* Текст в карточках */
    .admin-menu-wrapper .menu-text {
        flex: 1;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.95em;
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-text {
        color: #3b82f6;
    }
    
    .admin-menu-wrapper .stat-card.active .menu-text {
        color: #1d4ed8;
        font-weight: 700;
    }
    
    /* Бейджи */
    .admin-menu-wrapper .menu-badge {
        width: 28px;
        height: 28px;
        background: rgba(248,250,252,0.8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8em;
        color: #64748b;
        border: 1px solid rgba(148,163,184,0.3);
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .admin-menu-wrapper .menu-badge.security {
        background: rgba(59,130,246,0.1);
        color: #3b82f6;
        border-color: rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .stat-card:hover .menu-badge {
        transform: scale(1.2);
        background: #3b82f6;
        color: white;
        box-shadow: 0 3px 10px rgba(59,130,246,0.3);
        border-color: rgba(59,130,246,0.5);
    }
    
    /* Элементы хедера */
    .admin-menu-wrapper .menu-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.3em;
        font-weight: 700;
        color: #1e293b;
    }
    
    .admin-menu-wrapper .menu-title .blue-icon {
        font-size: 1.6em;
        background: rgba(59,130,246,0.1);
        padding: 10px;
        border-radius: 12px;
        border: 1px solid rgba(59,130,246,0.2);
    }
    
    .admin-menu-wrapper .menu-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9em;
        font-weight: 500;
        color: #059669;
    }
    
    .admin-menu-wrapper .status-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulseDot 2s infinite;
    }
    
    @keyframes pulseDot {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.2); }
        100% { opacity: 1; transform: scale(1); }
    }
    
    .admin-menu-wrapper .footer-info {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 0.85em;
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .admin-menu-wrapper.light-theme.white-bg {
            margin: 15px;
            border-radius: 15px;
        }
        
        .admin-menu-wrapper .menu-list {
            flex-direction: column;
            gap: 10px;
        }
        
        .admin-menu-wrapper .menu-item {
            min-width: auto;
        }
        
        .admin-menu-wrapper .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .admin-menu-wrapper .stat-card:hover .blue-icon {
            transform: scale(1.05);
        }
    }
    
    /* Анимация появления */
    .admin-menu-wrapper {
        animation: menuSlideIn 0.6s ease-out;
    }
    
    @keyframes menuSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
    ';
}




  function close_menu ()
  {
    echo '</td></tr></table>';
  }

  function stop_script ($msg = 'Your Script License has been Terminated!')
  {
    echo '<style type="text/css">
	<!--
	.warnbox
	{
		line-height: 1.4em; 
		float:center;
		background: lightyellow; 
		border:1px solid black;
		border-color:#6D90B0;
		font:normal 12px verdana;
		line-height:18px;
		z-index:100;
		border-right: 4px solid black;
		border-bottom: 4px solid black;
		padding: 0 0 3px 31px;
	}
	.red
	{
		color: #9f0808;
		font:bold 12px verdana;
	}
	a { color: #9f0808; background: inherit; text-decoration:none; }
	a:hover { background: inherit; text-decoration:underline; }
	-->
	</style>
	<div class="warnbox" align="center">
	<p align="center" class="red">	
	' . $msg . ' Please contact the TS Team regarding the issue by clicking following link: no thanks!	
	</font>
	</p>
	<p align="center">
	<strong>This could be because of one of the following reasons:</strong>
	<ul>
	<li>Your account has either been suspended or you have been banned from accessing this resource.</li>
	<li>Your account may still be awaiting activation or moderation.</li>
	<li>Feel free to contact us about this error message.</li>
	</ul></p>';
    exit ();
  }

  if ((!defined ('SETTING_PANEL_TSSEv56') AND !defined ('STAFF_PANEL_TSSEv56')))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

 
  define ('ADMIN_FUNCTIONS_TSSEv56', true);
  define ('AP_VERSION', 'v6.2 by xam');
  define ('S_VERSION', 'v7.9');
  define ('T_VERSION', '5.6');
  define ('O_VERSION', '');
  define ('TYPE', 99);
  if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
  {
    $eol = '
';
  }
  else
  {
    if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
    {
      $eol = '
';
    }
    else
    {
      $eol = '
';
    }
  }

  

  require_once $thispath . 'include/adminfunctions2.php';
  if (!defined ('_AF_2'))
  {
    exit ('The authentication has been blocked because of invalid file detected!');
  }

  include_once INC_PATH . '/functions_icons.php';
  if (!function_exists ('file_put_contents'))
  {
    function file_put_contents ($filename, $contents)
    {
      if (is_writable ($filename))
      {
        if ($handle = fopen ($filename, 'w'))
        {
          if (fwrite ($handle, $contents) === FALSE)
          {
            return false;
          }

          fclose ($filename);
          return true;
        }
      }

      return false;
    }
  }

?>
