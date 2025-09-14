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
define('THIS_SCRIPT', 'memberlist.php');
define("SCRIPTNAME", "memberlist.php");

$templatelist = "memberlist,memberlist_search,memberlist_user,memberlist_user_groupimage,memberlist_user_avatar,memberlist_user_userstar,memberlist_search_contact_field,memberlist_referrals,memberlist_referrals_bit";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,memberlist_error,memberlist_orderarrow";

define ('TSF_FORUMS_TSSEv56', true);

require_once 'global2.php';

  
if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
   exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}


require_once INC_PATH . '/functions_multipage.php';






// Load global language phrases
$lang->load("memberlist");

//if($mybb->settings['enablememberlist'] == 0)
//{
	//error($lang->memberlist_disabled);
//}



$plugins->run_hooks("memberlist_start");

add_breadcrumb($lang->memberlist['nav_memberlist'], "memberlist.php");

//if($mybb->usergroup['canviewmemberlist'] == 0)
//{
	//error_no_permission();
//}

$orderarrow = $sort_selected = array(
	'regdate' => '',
	'lastvisit' => '',
	'reputation' => '',
	'postnum' => '',
	'threadnum' => '',
	'referrals' => '',
	'username' => ''
);

// Showing advanced search page?
if($mybb->get_input('action') == "search")
{
	$plugins->run_hooks("memberlist_search");
	add_breadcrumb($lang->memberlist['nav_memberlist_search']);

	if(isset($usergroups['usergroup']))
	{
		$usergroup = $usergroups['usergroup'];
	}
	else
	{
		$usergroup = '';
	}
	if(isset($usergroups['additionalgroups']))
	{
		$additionalgroups = $usergroups['additionalgroups'];
	}
	else
	{
		$additionalgroups = '';
	}

	$contact_fields = array();
	foreach(array('skype', 'google', 'icq') as $field)
	{
		$contact_fields[$field] = '';
		$settingkey = 'allow'.$field.'field';

		if($mybb->settings[$settingkey] != '' && is_member($mybb->settings[$settingkey], array('usergroup' => $usergroup, 'additionalgroups' => $additionalgroups)))
		{
			$tmpl = 'memberlist_search_'.$field;

			$lang_string = 'search_'.$field;
			$lang_string = $lang->{$lang_string};

			$bgcolors[$field] = alt_trow();
			eval('$contact_fields[\''.$field.'\'] = "'.$templates->get('memberlist_search_contact_field').'";');
		}
	}

	$referrals_option = '';
	
	$usereferrals = "0";
	
	if($usereferrals == 1)
	{
		eval("\$referrals_option = \"".$templates->get("memberlist_referrals_option")."\";");
	}

	stdhead('title');
	
	eval("\$search_page = \"".$templates->get("memberlist_search")."\";");
	
	echo $search_page;
	
	stdfoot();
	
}
else
{
	$colspan = 6;
	$search_url = '';

	// Incoming sort field?
	if(isset($mybb->input['sort']))
	{
		$mybb->input['sort'] = strtolower($mybb->get_input('sort'));
	}
	else
	{
		$default_memberlist_sortby = "regdate";
		$mybb->input['sort'] = $default_memberlist_sortby;
	}

	switch($mybb->input['sort'])
	{
		case "added":
			$sort_field = "u.added";
			break;
		case "lastvisit":
			$sort_field = "u.lastactive";
			break;
		case "reputation":
			$sort_field = "u.reputation";
			break;
		case "postnum":
			$sort_field = "u.postnum";
			break;
		case "threadnum":
			$sort_field = "u.threadnum";
			break;
		case "referrals":
			if($usereferrals == 1)
			{
				$sort_field = "u.referrals";
			}
			else
			{
				$sort_field = "u.username";
			}
			break;
		default:
			$sort_field = "u.username";
			$mybb->input['sort'] = 'username';
			break;
	}
	$sort_selected[$mybb->input['sort']] = " selected=\"selected\"";

	// Incoming sort order?
	if(isset($mybb->input['order']))
	{
		$mybb->input['order'] = strtolower($mybb->input['order']);
	}
	else
	{
		$default_memberlist_order = "ascending";
		$mybb->input['order'] = strtolower($default_memberlist_order);
	}

	$order_check = array('ascending' => '', 'descending' => '');
	if($mybb->input['order'] == "ascending" || (!$mybb->input['order'] && $mybb->input['sort'] == 'username'))
	{
		$sort_order = "ASC";
		$sortordernow = "ascending";
		$oppsort = $lang->memberlist['desc'];
		$oppsortnext = "descending";
		$mybb->input['order'] = "ascending";
	}
	else
	{
		$sort_order = "DESC";
		$sortordernow = "descending";
		$oppsort = $lang->memberlist['asc'];
		$oppsortnext = "ascending";
		$mybb->input['order'] = "descending";
	}
	$order_check[$mybb->input['order']] = " checked=\"checked\"";

	if($sort_field == 'u.lastactive' && $mybb->usergroup['canviewwolinvis'] == 0)
	{
		$sort_field = "u.invisible ASC, CASE WHEN u.invisible = 1 THEN u.added ELSE u.lastactive END";
	}

	// Incoming results per page?
	$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$per_page = $mybb->input['perpage'];
	}
	else if($ts_perpage)
	{
		$per_page = $mybb->input['perpage'] = (int)$ts_perpage;
	}
	else
	{
		$per_page = $mybb->input['perpage'] = 20;
	}

	$search_query = '1=1';
	$search_url = "";

	switch($db->type)
	{
		// PostgreSQL's LIKE is case sensitive
		case "pgsql":
			$like = "ILIKE";
			break;
		default:
			$like = "LIKE";
	}

	// Limiting results to a certain letter
	if(isset($mybb->input['letter']))
	{
		$letter = chr(ord($mybb->get_input('letter')));
		if($mybb->input['letter'] == -1)
		{
			$search_query .= " AND u.username NOT REGEXP('[a-zA-Z]')";
		}
		else if(strlen($letter) == 1)
		{
			$search_query .= " AND u.username {$like} '".$db->escape_string_like($letter)."%'";
		}
		$search_url .= "&letter={$letter}";
	}

	// Searching for a matching username
	$search_username = htmlspecialchars_uni(trim($mybb->get_input('username')));
	if($search_username != '')
	{
		$username_like_query = $db->escape_string_like($search_username);

		// Name begins with
		if($mybb->get_input('username_match') == "begins")
		{
			$search_query .= " AND u.username {$like} '".$username_like_query."%'";
			$search_url .= "&username_match=begins";
		}
		// Just contains
		else if($mybb->get_input('username_match') == "contains")
		{
			$search_query .= " AND u.username {$like} '%".$username_like_query."%'";
			$search_url .= "&username_match=contains";
		}
		// Exact
		else
		{
			$username_esc = $db->escape_string(my_strtolower($search_username));
			$search_query .= " AND LOWER(u.username)='{$username_esc}'";
		}

		$search_url .= "&username=".urlencode($search_username);
	}

	// Website contains
	$mybb->input['website'] = trim($mybb->get_input('website'));
	$search_website = htmlspecialchars_uni($mybb->input['website']);
	if(trim($mybb->input['website']))
	{
		$search_query .= " AND u.website {$like} '%".$db->escape_string_like($mybb->input['website'])."%'";
		$search_url .= "&website=".urlencode($mybb->input['website']);
	}

	// Search by contact field input
	foreach(array('icq', 'google', 'skype') as $cfield)
	{
		$csetting = 'allow'.$cfield.'field';
		$mybb->input[$cfield] = trim($mybb->get_input($cfield));
		if($mybb->input[$cfield] && $mybb->settings[$csetting] != '')
		{
			if($mybb->settings[$csetting] != -1)
			{
				$gids = explode(',', (string)$mybb->settings[$csetting]);

				$search_query .= " AND (";
				$or = '';
				foreach($gids as $gid)
				{
					$gid = (int)$gid;
					$search_query .= $or.'u.usergroup=\''.$gid.'\'';
					switch($db->type)
					{
						case 'pgsql':
						case 'sqlite':
							$search_query .= " OR ','||u.additionalgroups||',' LIKE '%,{$gid},%'";
							break;
						default:
							$search_query .= " OR CONCAT(',',u.additionalgroups,',') LIKE '%,{$gid},%'";
							break;
					}
					$or = ' OR ';
				}
				$search_query .= ")";
			}
			if($cfield == 'icq')
			{
				$search_query .= " AND u.{$cfield} LIKE '%".(int)$mybb->input[$cfield]."%'";
			}
			else
			{
				$search_query .= " AND u.{$cfield} {$like} '%".$db->escape_string_like($mybb->input[$cfield])."%'";
			}
			$search_url .= "&{$cfield}=".urlencode($mybb->input[$cfield]);
		}
	}

	$usergroups_cache = $cache->read('usergroups');

	$group = array();
	foreach($usergroups_cache as $gid => $groupcache)
	{
		//if($groupcache['showmemberlist'] == 0)
		//{
		//	$group[] = (int)$gid;
		//}
	}

	if(is_array($group) && !empty($group))
	{
		$hiddengroup = implode(',', $group);

		$search_query .= " AND u.usergroup NOT IN ({$hiddengroup})";

		foreach($group as $hidegid)
		{
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$search_query .= " AND ','||u.additionalgroups||',' NOT LIKE '%,{$hidegid},%'";
					break;
				default:
					$search_query .= " AND CONCAT(',',u.additionalgroups,',') NOT LIKE '%,{$hidegid},%'";
					break;
			}
		}
	}
  
	$sorturl = htmlspecialchars_uni("memberlist.php?perpage={$mybb->input['perpage']}{$search_url}");
	$search_url = htmlspecialchars_uni("memberlist.php?sort={$mybb->input['sort']}&order={$mybb->input['order']}&perpage={$mybb->input['perpage']}{$search_url}");

	$plugins->run_hooks('memberlist_intermediate');

	$query = $db->simple_select("users u", "COUNT(*) AS users", "{$search_query}");
	$num_users = $db->fetch_field($query, "users");

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page && $page > 0)
	{
		$start = ($page - 1) * $per_page;
		$pages = ceil($num_users / $per_page);
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

	$sort = htmlspecialchars_uni($mybb->input['sort']);
	eval("\$orderarrow['{$sort}'] = \"".$templates->get("memberlist_orderarrow")."\";");

	$referral_header = '';

	
	$usereferrals = "0";
	
	// Referral?
	if($usereferrals == 1)
	{
		$colspan = 7;
		eval("\$referral_header = \"".$templates->get("memberlist_referrals")."\";");
	}

	$multipage = multipage($num_users, $per_page, $page, $search_url);

	// Cache a few things
	$usertitles = $cache->read('usertitles');
	$usertitles_cache = array();
	foreach($usertitles as $usertitle)
	{
		$usertitles_cache[$usertitle['posts']] = $usertitle;
	}
	$users = '';
	$query = $db->sql_query("
		SELECT u.*, f.*
		FROM users u
		LEFT JOIN userfields f ON (f.ufid=u.id)
		WHERE {$search_query}
		ORDER BY {$sort_field} {$sort_order}
		LIMIT {$start}, {$per_page}
	");
	while($user = $db->fetch_array($query))
	{
		$user = $plugins->run_hooks("memberlist_user", $user);

		$alt_bg = alt_trow();

		$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);

		$user['profilelink'] = build_profile_link($user['username'], $user['id']);

		// Get the display usergroup
		if($user['usergroup'])
		{
			$usergroup = usergroup_permissions($user['usergroup']);
		}
		else
		{
			$usergroup = usergroup_permissions(1);
		}

		//$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
		$displaygroupfields = array("title", "description", "namestyle", "usertitle", "image");

		if(!$user['displaygroup'])
		{
			$user['displaygroup'] = $user['usergroup'];
		}

		$display_group = usergroup_displaygroup($user['displaygroup']);
		if(is_array($display_group))
		{
			$usergroup = array_merge($usergroup, $display_group);
		}

		$referral_bit = '';

		// Build referral?
		$usereferrals = "0";
		
		if($usereferrals == 1)
		{
			$referral_count = (int) $user['referrals'];
			if($referral_count > 0)
			{
				$uid = (int) $user['uid'];
				eval("\$user['referrals'] = \"".$templates->get('member_referrals_link')."\";");
			}

			eval("\$referral_bit = \"".$templates->get("memberlist_referrals_bit")."\";");
		}

		//$usergroup['groupimage'] = '';
		// Work out the usergroup/title stuff
		//if(!empty($usergroup['image']))
		//{
		//	if(!empty($mybb->user['language']))
		//	{
		//		$language = $mybb->user['language'];
		//	}
		//	else
		//	{
		//		$language = $mybb->settings['bblanguage'];
		//	}
		//	$usergroup['image'] = str_replace("{lang}", $language, $usergroup['image']);
		//	$usergroup['image'] = str_replace("{theme}", $theme['imgdir'], $usergroup['image']);
		//	eval("\$usergroup['groupimage'] = \"".$templates->get("memberlist_user_groupimage")."\";");
		//}

		
		// User has group title
        $user['usertitle'] = '';
		$usertitle = '';
		
		$usertitle = $usergroup['title'];
		$usertitle = htmlspecialchars_uni($usertitle);
		$user['usertitle'] = $usertitle;
		
		

		//if(!empty($usergroup['stars']))
		//{
		//	$user['stars'] = $usergroup['stars'];
		//}

		//if(empty($user['starimage']))
		//{
		//	$user['starimage'] = $usergroup['starimage'];
		//}

		//$user['userstars'] = '';
		//if(!empty($user['starimage']) && isset($user['stars']))
		//{
			// Only display stars if we have an image to use...
		//	$starimage = str_replace("{theme}", $theme['imgdir'], $user['starimage']);

		//	for($i = 0; $i < $user['stars']; ++$i)
		//	{
		//		eval("\$user['userstars'] .= \"".$templates->get("memberlist_user_userstar", 1, 0)."\";");
		//	}
		//}

		//if($user['userstars'] && $usergroup['groupimage'])
		//{
		//	$user['userstars'] = "<br />".$user['userstars'];
		//}

		// Show avatar
		
		$memberlistmaxavatarsize = "70x70";
		
		//$useravatar = format_avatar($user['avatar'], $user['avatardimensions'], my_strtolower($memberlistmaxavatarsize));
		//eval("\$user['avatar'] = \"".$templates->get("memberlist_user_avatar")."\";");
		
		
		$useravatar = format_avatar($user['avatar'], $user['avatardimensions'], my_strtolower($memberlistmaxavatarsize));

        // Определяем, нужно ли использовать <img> или выводить SVG напрямую
        if (strpos($useravatar['image'], '<svg') === 0) 
		{
             // Это SVG-заглушка - выводим как есть с дополнительными классами
            $user['avatar'] = '';
        } 
		else 
		{
            // Это обычный аватар - используем <img> с заданными параметрами
            $user['avatar'] = '<img src="'.$useravatar['image'].'" alt="" class="rounded" style="width: 70px;" />';
        }
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		

		$last_seen = max(array($user['lastactive'], $user['lastvisit']));
		if(empty($last_seen))
		{
			$user['lastvisit'] = $lang->memberlist['lastvisit_never'];
		}
		else
		{
			// We have some stamp here
			if($user['invisible'] == 1 && $usergroups['issupermod'] != '1' && $user['uid'] != $CURUSER['id'])
			{
				$user['lastvisit'] = $lang->memberlist['lastvisit_hidden'];
			}
			else
			{
				$user['lastvisit'] = my_datee('relative', $last_seen);
			}
		}

		$user['added'] = my_datee('relative', $user['added']);
		$user['postnum'] = ts_nf($user['postnum']);
		$user['threadnum'] = ts_nf($user['threadnum']);
		eval("\$users .= \"".$templates->get("memberlist_user")."\";");
	}

	// Do we have no results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("memberlist_error")."\";");
	}

	$referrals_option = '';
	if($usereferrals == 1)
	{
		eval("\$referrals_option = \"".$templates->get("memberlist_referrals_option")."\";");
	}

	$plugins->run_hooks("memberlist_end");

	stdhead($lang->memberlist['member_list']);
	
	build_breadcrumb();
	
	eval("\$memberlist = \"".$templates->get("memberlist")."\";");
	
	echo $memberlist;
	
	stdfoot();
}