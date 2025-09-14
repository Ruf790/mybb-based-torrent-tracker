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
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'search.php');
define("SCRIPTNAME", "search.php");

define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');


$templatelist = "search,forumdisplay_thread_gotounread,search_results_threads_thread,search_results_threads,search_results_posts,search_results_posts_post,search_results_icon,search_forumlist_forum,search_forumlist";
$templatelist .= ",multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";
$templatelist .= ",search_results_posts_inlinecheck,search_results_posts_nocheck,search_results_threads_inlinecheck,search_results_threads_nocheck,search_results_inlinemodcol,search_results_inlinemodcol_empty,search_results_posts_inlinemoderation_custom_tool";
$templatelist .= ",search_results_posts_inlinemoderation_custom,search_results_posts_inlinemoderation,search_results_threads_inlinemoderation_custom_tool,search_results_threads_inlinemoderation_custom,search_results_threads_inlinemoderation";
$templatelist .= ",forumdisplay_thread_attachment_count,search_threads_inlinemoderation_selectall,search_posts_inlinemoderation_selectall,post_prefixselect_prefix,post_prefixselect_multiple,search_orderarrow";
$templatelist .= ",search_results_posts_forumlink,search_results_threads_forumlink,forumdisplay_thread_multipage_more,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage,search_moderator_options";



require_once 'global.php';

if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

require_once INC_PATH.'/tsf_functions.php';
require_once INC_PATH . '/functions_multipage.php';

require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/functions_search.php";


require_once INC_PATH."/class_parser.php";
$parser = new postParser;




// Load global language phrases
$lang->load("search");







function build_prefix_select($fid, $selected_pid=0, $multiple=0, $previous_pid=0)
{
	global $cache, $db, $lang, $mybb, $templates;

	if($fid != 'all')
	{
		$fid = (int)$fid;
	}

	$prefix_cache = build_prefixes(0);
	if(empty($prefix_cache))
	{
		// We've got no prefixes to show
		return '';
	}

	// Go through each of our prefixes and decide which ones we can use
	$prefixes = array();
	foreach($prefix_cache as $prefix)
	{
		if($fid != "all" && $prefix['forums'] != "-1")
		{
			// Decide whether this prefix can be used in our forum
			$forums = explode(",", $prefix['forums']);

			if(!in_array($fid, $forums) && $prefix['pid'] != $previous_pid)
			{
				// This prefix is not in our forum list
				continue;
			}
		}

		if(is_member($prefix['groups']) || $prefix['pid'] == $previous_pid)
		{
			// The current user can use this prefix
			$prefixes[$prefix['pid']] = $prefix;
		}
	}

	if(empty($prefixes))
	{
		return '';
	}

	$prefixselect = $prefixselect_prefix = '';

	if($multiple == 1)
	{
		$any_selected = "";
		if($selected_pid == 'any')
		{
			$any_selected = " selected=\"selected\"";
		}
	}

	$default_selected = "";
	if(((int)$selected_pid == 0) && $selected_pid != 'any')
	{
		$default_selected = " selected=\"selected\"";
	}

	foreach($prefixes as $prefix)
	{
		$selected = "";
		if($prefix['pid'] == $selected_pid)
		{
			$selected = " selected=\"selected\"";
		}

		$prefix['prefix'] = htmlspecialchars_uni($prefix['prefix']);
		eval("\$prefixselect_prefix .= \"".$templates->get("post_prefixselect_prefix")."\";");
	}

	if($multiple != 0)
	{
		eval("\$prefixselect = \"".$templates->get("post_prefixselect_multiple")."\";");
	}
	else
	{
		eval("\$prefixselect = \"".$templates->get("post_prefixselect_single")."\";");
	}

	return $prefixselect;
}


function build_prefixes($pid=0)
{
	global $cache;
	static $prefixes_cache;

	if(is_array($prefixes_cache))
	{
		if($pid > 0 && is_array($prefixes_cache[$pid]))
		{
			return $prefixes_cache[$pid];
		}

		return $prefixes_cache;
	}

	$prefix_cache = $cache->read("threadprefixes");

	if(!is_array($prefix_cache))
	{
		// No cache
		$prefix_cache = $cache->read("threadprefixes", true);

		if(!is_array($prefix_cache))
		{
			return array();
		}
	}

	$prefixes_cache = array();
	foreach($prefix_cache as $prefix)
	{
		$prefixes_cache[$prefix['pid']] = $prefix;
	}

	if($pid != 0 && is_array($prefixes_cache[$pid]))
	{
		return $prefixes_cache[$pid];
	}
	else if(!empty($prefixes_cache))
	{
		return $prefixes_cache;
	}

	return false;
}








$is_mod = is_mod($usergroups);



add_breadcrumb($lang->search['nav_search'], "search.php");

$mybb->input['action'] = $mybb->get_input('action');
switch($mybb->input['action'])
{
	case "results":
		add_breadcrumb($lang->search['nav_results']);
		break;
	default:
		break;
}

if($usergroups['cansearch'] == 0)
{
   print_no_permission();
}

$now = TIMENOW;
$mybb->input['keywords'] = trim($mybb->get_input('keywords'));

$limitsql = "";

$searchhardlimit = "0";

if((int)$searchhardlimit > 0)
{
	$limitsql = "LIMIT ".(int)$searchhardlimit;
}

if($mybb->input['action'] == "results")
{
	$sid = $db->escape_string($mybb->get_input('sid'));
	$query = $db->simple_select("searchlog", "*", "sid='$sid'");
	$search = $db->fetch_array($query);

	if(!$search)
	{
		stderr($lang->search['error_invalidsearch']);
	}

	$plugins->run_hooks("search_results_start");

	// Decide on our sorting fields and sorting order.
	$order = my_strtolower(htmlspecialchars_uni($mybb->get_input('order')));
	$sortby = my_strtolower(htmlspecialchars_uni($mybb->get_input('sortby')));

	switch($sortby)
	{
		case "replies":
			$sortfield = "t.replies";
			break;
		case "views":
			$sortfield = "t.views";
			break;
		case "subject":
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.subject";
			}
			else
			{
				$sortfield = "p.subject";
			}
			break;
		case "forum":
			$sortfield = "f.name";
			break;
		case "starter":
			if($search['resulttype'] == "threads")
			{
				$sortfield = "t.username";
			}
			else
			{
				$sortfield = "p.username";
			}
			break;
		case "lastpost":
		default:
			if(isset($search['resulttype']) && $search['resulttype'] == "threads")
			{
				$sortfield = "t.lastpost";
				$sortby = "lastpost";
			}
			else
			{
				$sortfield = "p.dateline";
				$sortby = "dateline";
			}
			break;
	}

	if($order != "asc")
	{
		$order = "desc";
		$oppsortnext = "asc";
		$oppsort = 'asc';
	}
	else
	{
		$oppsortnext = "desc";
		$oppsort = 'desc';
	}

	$f_threadsperpage = "20";
	
	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $f_threadsperpage;
	$page = $mybb->get_input('page');
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	// Work out if we have terms to highlight
	$highlight = "";
	if(!empty($search['keywords']))
	{
		if($mybb->seo_support == true)
		{
			$highlight = "?highlight=".urlencode($search['keywords']);
		}
		else
		{
			$highlight = "&amp;highlight=".urlencode($search['keywords']);
		}
	}

	$sorturl = "search.php?action=results&amp;sid={$sid}";
	$thread_url = "";
	$post_url = "";

	$orderarrow = array('replies' => '', 'views' => '', 'subject' => '', 'forum' => '', 'starter' => '', 'lastpost' => '', 'dateline' => '');

	eval("\$orderarrow['$sortby'] = \"".$templates->get("search_orderarrow")."\";");

	// Read some caches we will be using
	$forumcache = $cache->read("forums");
	$icon_cache = $cache->read("posticons");

	$threads = array();

	if($CURUSER['id'] == 0)
	{
		// Build a forum cache.
		$query = $db->sql_query("
			SELECT fid
			FROM tsf_forums
			WHERE active != 0
			ORDER BY pid, disporder
		");

		if(isset($mybb->cookies['mybb']['forumread']))
		{
			$forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
		}
		else
		{
			$forumsread = array();
		}
	}
	else
	{
		// Build a forum cache.
		$query = $db->sql_query("
			SELECT f.fid, fr.dateline AS lastread
			FROM tsf_forums f
			LEFT JOIN tsf_forumsread fr ON (fr.fid=f.fid AND fr.uid='{$CURUSER['id']}')
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

		if(isset($forum['lastread']))
		{
			$readforums[$forum['fid']] = $forum['lastread'];
		}
		else
		{
			$readforums[$forum['fid']] = '';
		}
	}
	$fpermissions = forum_permissions();

	// Inline Mod Column for moderators
	$inlinemodcol = $inlinecookie = $inline_edit_js = '';
	$is_mod = $is_supermod = $show_inline_moderation = false;
	if($usergroups['issupermod'])
	{
		$is_supermod = true;
	}
	if($is_supermod || $is_mod)
	{
		$inlinecookie = "inlinemod_search".$sid;
		$inlinecount = 0;
		$is_mod = true;
		$return_url = 'search.php?'.htmlspecialchars_uni($_SERVER['QUERY_STRING']);
	}

	// Show search results as 'threads'
	if(isset($search['resulttype']) && $search['resulttype'] == "threads")
	{
		$threadcount = 0;

		// Moderators can view unapproved threads and deleted threads from forums they moderate
		$unapproved_where_t = get_visible_where('t');

		// If we have saved WHERE conditions, execute them
		if($search['querycache'] != "")
		{
			$where_conditions = $search['querycache'];
			$query = $db->simple_select("tsf_threads t", "t.tid", $where_conditions. " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' ORDER BY t.lastpost DESC {$limitsql}");
			
			
			
			while($thread = $db->fetch_array($query))
			{
				$threads[$thread['tid']] = $thread['tid'];
				$threadcount++;
			}
			// Build our list of threads.
			if($threadcount > 0)
			{
				$search['threads'] = implode(",", $threads);
			}
			// No results.
			else
			{
				stderr($lang->search['error_nosearchresults']);
			}
			$where_conditions = "t.tid IN (".$search['threads'].")";
		}
		// This search doesn't use a query cache, results stored in search table.
		else
		{
			$where_conditions = "t.tid IN (".$search['threads'].")";
			$query = $db->simple_select("tsf_threads t", "COUNT(t.tid) AS resultcount", $where_conditions. " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$limitsql}");
			$count = $db->fetch_array($query);

			if(!$count['resultcount'])
			{
				stderr($lang->search['error_nosearchresults']);
			}
			$threadcount = $count['resultcount'];
		}

		$permsql = "";
		$onlyusfids = array();

		// Check group permissions if we can't view threads not started by us
		$group_permissions = forum_permissions();
		foreach($group_permissions as $fid => $forum_permissions)
		{
			if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
			{
				$onlyusfids[] = $fid;
			}
		}
		if(!empty($onlyusfids))
		{
			$permsql .= "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
		}

		$unsearchforums = get_unsearchable_forums();
		if($unsearchforums)
		{
			$permsql .= " AND t.fid NOT IN ($unsearchforums)";
		}
		$inactiveforums = get_inactive_forums();
		if($inactiveforums)
		{
			$permsql .= " AND t.fid NOT IN ($inactiveforums)";
		}

		$pages = ceil($threadcount / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}

		// Begin selecting matching threads, cache them.
		$sqlarray = array(
			'order_by' => $sortfield,
			'order_dir' => $order,
			'limit_start' => $start,
			'limit' => $perpage
		);
		$query = $db->sql_query("
			SELECT t.*, u.username AS userusername
			FROM tsf_threads t
			LEFT JOIN users u ON (u.id=t.uid)
			LEFT JOIN tsf_forums f ON (t.fid=f.fid)
			WHERE $where_conditions AND ({$unapproved_where_t}) {$permsql} AND t.closed NOT LIKE 'moved|%'
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");

		$threadprefixes = build_prefixes();
		$thread_cache = array();
		while($thread = $db->fetch_array($query))
		{
			$thread['threadprefix'] = '';
			if($thread['prefix'] && !empty($threadprefixes[$thread['prefix']]))
			{
				$thread['threadprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'];
			}
			$thread_cache[$thread['tid']] = $thread;
		}
		$thread_ids = implode(",", array_keys($thread_cache));

		if(empty($thread_ids))
		{
			stderr($lang->search['error_nosearchresults']);
		}

		// Fetch dot icons if enabled
		$dotfolders = "1";
		
		if($dotfolders != 0 && $CURUSER['id'] && $thread_cache)
		{
			$unapproved_where_p = str_replace('t.', '', $unapproved_where_t);
			$query = $db->simple_select("tsf_posts", "DISTINCT tid,uid", "uid='{$CURUSER['id']}' AND tid IN({$thread_ids}) AND ({$unapproved_where_p})");
			while($thread = $db->fetch_array($query))
			{
				$thread_cache[$thread['tid']]['dot_icon'] = 1;
			}
		}

		// Fetch the read threads.
		
		$threadreadcut = "7";
		
		if($CURUSER['id'] && $threadreadcut > 0)
		{
			$query = $db->simple_select("tsf_threadsread", "tid,dateline", "uid='".$CURUSER['id']."' AND tid IN(".$thread_ids.")");
			while($readthread = $db->fetch_array($query))
			{
				$thread_cache[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}

        $maxmultipagelinks = "5";
		
		if(!$maxmultipagelinks)
		{
			$maxmultipagelinks = 5;
		}

		$results = '';

		foreach($thread_cache as $thread)
		{
			$bgcolor = alt_trow();
			$folder = '';
			$prefix = '';

			// Unapproved colour
			if($thread['visible'] == 0)
			{
				$bgcolor = 'trow_shaded';
			}
			elseif($thread['visible'] == -1)
			{
				$bgcolor = 'trow_shaded trow_deleted';
			}

			if($thread['userusername'])
			{
				$thread['username'] = $thread['userusername'];
			}
			$thread['username'] = htmlspecialchars_uni($thread['username']);
			$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);

			// If this thread has a prefix, insert a space between prefix and subject
			if($thread['prefix'] != 0)
			{
				$thread['threadprefix'] .= '&nbsp;';
			}

			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			if(isset($icon_cache[$thread['icon']]))
			{
				$posticon = $icon_cache[$thread['icon']];
				$posticon['path'] = str_replace("{theme}", $theme['imgdir'], $posticon['path']);
				$posticon['path'] = htmlspecialchars_uni($posticon['path']);
				$posticon['name'] = htmlspecialchars_uni($posticon['name']);
				eval("\$icon = \"".$templates->get("search_results_icon")."\";");
			}
			else
			{
				$icon = "&nbsp;";
			}
			if($thread['poll'])
			{
				$prefix = 'poll_prefix';
			}

			// Determine the folder
			$folder = '';
			$folder_label = '';
			if(isset($thread['dot_icon']))
			{
				$folder = "dot_";
				$folder_label .= $lang->search['icon_dot'];
			}
			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$last_read = 0;

			$threadreadcut = "7";
			
			if($threadreadcut > 0 && $CURUSER['id'])
			{
				$forum_read = $readforums[$thread['fid']];

				$read_cutoff = TIMENOW-$threadreadcut*60*60*24;
				if($forum_read == 0 || $forum_read < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
			}
			else
			{
				if(isset($forumsread[$thread['fid']]))
				{
					$forum_read = $forumsread[$thread['fid']];
				}
				else
				{
					$forum_read = '';
				}
			}

			if($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read)
			{
				if(isset($thread['lastread']))
				{
					$last_read = $thread['lastread'];
				}
				else
				{
					$last_read = $read_cutoff;
				}
			}
			else
			{
				$last_read = my_get_array_cookie("threadread", $thread['tid']);
			}

			if($forum_read > $last_read)
			{
				$last_read = $forum_read;
			}

			if($thread['lastpost'] > $last_read && $last_read)
			{
				$folder .= "new";
				$new_class = "subject_new";
				$folder_label .= $lang->search['icon_new'];
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost").$highlight;
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$new_class = 'subject_old';
				$folder_label .= 'icon_no_new';
			}

			
			$hottopic = "20";
			$hottopicviews = "150";
			
			if($thread['replies'] >= $hottopic || $thread['views'] >= $hottopicviews)
			{
				$folder .= "hot";
				$folder_label .= 'icon_hot';
			}
			if($thread['closed'] == 1)
			{
				$folder .= "close";
				$folder_label .= 'icon_close';
			}
			$folder .= "folder";

			$f_postsperpage = "10";
			
			if(!$f_postsperpage || (int)$f_postsperpage < 1)
			{
				$f_postsperpage = 20;
			}

			$thread['pages'] = 0;
			$thread['multipage'] = '';
			$threadpages = '';
			$morelink = '';
			$thread['posts'] = $thread['replies'] + 1;
			if($is_mod)
			{
				//if(is_moderator($thread['fid'], "canviewdeleted") == true)
				//{
				//	$thread['posts'] += $thread['deletedposts'];
				//}
				//if(is_moderator($thread['fid'], "canviewunapprove") == true)
				//{
					$thread['posts'] += $thread['unapprovedposts'];
				//}
			}
			elseif($group_permissions[$thread['fid']]['canviewdeletionnotice'] != 0)
			{
				$thread['posts'] += $thread['deletedposts'];
			}

			if($thread['posts'] > $f_postsperpage)
			{
				$thread['pages'] = $thread['posts'] / $f_postsperpage;
				$thread['pages'] = ceil($thread['pages']);
				if($thread['pages'] > $maxmultipagelinks)
				{
					$pagesstop = $maxmultipagelinks - 1;
					$page_link = get_thread_link($thread['tid'], $thread['pages']).$highlight;
					eval("\$morelink = \"".$templates->get("forumdisplay_thread_multipage_more")."\";");
				}
				else
				{
					$pagesstop = $thread['pages'];
				}
				for($i = 1; $i <= $pagesstop; ++$i)
				{
					$page_link = get_thread_link($thread['tid'], $i).$highlight;
					eval("\$threadpages .= \"".$templates->get("forumdisplay_thread_multipage_page")."\";");
				}
				eval("\$thread['multipage'] = \"".$templates->get("forumdisplay_thread_multipage")."\";");
			}
			else
			{
				$threadpages = '';
				$morelink = '';
				$thread['multipage'] = '';
			}
			$lastpostdate = my_datee('relative', $thread['lastpost']);
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");
			$lastposteruid = $thread['lastposteruid'];
			if(!$lastposteruid && !$thread['lastposter'])
			{
				$lastposter = htmlspecialchars_uni($lang->guest);
			}
			else
			{
				$lastposter = htmlspecialchars_uni($thread['lastposter']);
			}
			$thread_link = get_thread_link($thread['tid']);

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$lastposterlink = $lastposter;
			}
			else
			{
				$lastposterlink = build_profile_link($lastposter, $lastposteruid);
			}

			$thread['replies'] = ts_nf($thread['replies']);
			$thread['views'] = ts_nf($thread['views']);

			$thread['forumlink'] = '';
			if($forumcache[$thread['fid']])
			{
				$thread['forumlink_link'] = get_forum_link($thread['fid']);
				$thread['forumlink_name'] = $forumcache[$thread['fid']]['name'];
				eval("\$thread['forumlink'] = \"".$templates->get("search_results_threads_forumlink")."\";");
			}

			// If this user is the author of the thread and it is not closed or they are a moderator, they can edit
			if(($thread['uid'] == $CURUSER['id'] && $thread['closed'] != 1 && $CURUSER['id'] != 0 && $fpermissions[$thread['fid']]['caneditposts'] == 1) || $is_mod)
			{
				$inline_edit_class = "subject_editable";
			}
			else
			{
				$inline_edit_class = "";
			}

			// If this thread has 1 or more attachments show the papperclip
			
			$enableattachments= "1";
			
			if($enableattachments == 1 && $thread['attachmentcount'] > 0)
			{
				if($thread['attachmentcount'] > 1)
				{
					$attachment_count = sprintf('attachment_count_multiple', $thread['attachmentcount']);
				}
				else
				{
					$attachment_count = 'attachment_count';
				}

				eval("\$attachment_count = \"".$templates->get("forumdisplay_thread_attachment_count")."\";");
			}
			else
			{
				$attachment_count = '';
			}

			$inline_edit_tid = $thread['tid'];

			// Inline thread moderation

			
			$inline_mod_checkbox = '';
			if($is_supermod || $is_mod)
			{
				if(isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$thread['tid']}|") !== false)
				{
					$inlinecheck = "checked=\"checked\"";
					++$inlinecount;
				}
				else
				{
					$inlinecheck = '';
				}

				// If this user is allowed to use the inline moderation tools for at least one thread, include the necessary scripts
				$show_inline_moderation = true;

				eval("\$inline_mod_checkbox = \"".$templates->get("search_results_threads_inlinecheck")."\";");
			}
			elseif($is_mod)
			{
				eval("\$inline_mod_checkbox = \"".$templates->get("search_results_threads_nocheck")."\";");
			}

			$plugins->run_hooks("search_results_thread");
			eval("\$results .= \"".$templates->get("search_results_threads_thread")."\";");
		}
		if(!$results)
		{
			stderr($lang->search['error_nosearchresults']);
		}
		$multipage = multipage($threadcount, $perpage, $page, "search.php?action=results&amp;sid=$sid&amp;sortby=$sortby&amp;order=$order&amp;uid=".$mybb->get_input('uid', MyBB::INPUT_INT));
		if($upper > $threadcount)
		{
			$upper = $threadcount;
		}

		$selectall = '';
		$inlinemod = '';

		// Inline Thread Moderation Options
		if($show_inline_moderation)
		{
			eval("\$inlinemodcol = \"".$templates->get("search_results_inlinemodcol")."\";");

			// If user has moderation tools available, prepare the Select All feature
			$page_selected = sprintf($lang->search['page_selected'], count($thread_cache));
			$all_selected = sprintf($lang->search['all_selected'], (int)$threadcount);
			$select_all = sprintf($lang->search['select_all'], (int)$threadcount);
			eval("\$selectall = \"".$templates->get("search_threads_inlinemoderation_selectall")."\";");

			$customthreadtools = '';
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->simple_select("modtools", "tid, name", "type='t' AND (','||forums||',' LIKE '%,-1,%' OR forums='')");
					break;
				default:
					$query = $db->simple_select("modtools", "tid, name", "type='t' AND (CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='')");
			}

			while($tool = $db->fetch_array($query))
			{
				$tool['name'] = htmlspecialchars_uni($tool['name']);
				eval("\$customthreadtools .= \"".$templates->get("search_results_threads_inlinemoderation_custom_tool")."\";");
			}
			// Build inline moderation dropdown
			if(!empty($customthreadtools))
			{
				eval("\$customthreadtools = \"".$templates->get("search_results_threads_inlinemoderation_custom")."\";");
			}
			eval("\$inlinemod = \"".$templates->get("search_results_threads_inlinemoderation")."\";");
		}
		elseif($is_mod)
		{
			eval("\$inlinemodcol = \"".$templates->get("search_results_inlinemodcol_empty")."\";");
		}

		$plugins->run_hooks("search_results_end");

		eval("\$searchresults = \"".$templates->get("search_results_threads")."\";");
		
		stdhead();
		
		build_breadcrumb();
		
		
		
		echo $searchresults;
		
		stdfoot();
	}
	else // Displaying results as posts
	{
		if(empty($search['posts']))
		{
			stderr($lang->search['error_nosearchresults']);
		}

		$postcount = 0;

		// Moderators can view unapproved threads and deleted threads from forums they moderate
		$unapproved_where = get_visible_where();

		$post_cache_options = array();
		
		$searchhardlimit = "0";
		
		if((int)$searchhardlimit > 0)
		{
			$post_cache_options['limit'] = (int)$searchhardlimit;
		}

		if(strpos($sortfield, 'p.') !== false)
		{
			$post_cache_options['order_by'] = str_replace('p.', '', $sortfield);
			$post_cache_options['order_dir'] = $order;
		}

		$tids = array();
		$pids = array();
		// Make sure the posts we're viewing we have permission to view.
		$query = $db->simple_select("tsf_posts", "pid, tid", "pid IN(".$db->escape_string($search['posts']).") AND ({$unapproved_where})", $post_cache_options);
		
		$query = $db->simple_select("tsf_posts", "pid, tid", "pid IN(".$db->escape_string($search['posts']).")", $post_cache_options);
		
		
		while($post = $db->fetch_array($query))
		{
			$pids[$post['pid']] = $post['tid'];
			$tids[$post['tid']][$post['pid']] = $post['pid'];
		}

		if(!empty($pids))
		{
			$temp_pids = array();

			$group_permissions = forum_permissions();
			$permsql = '';
			$onlyusfids = array();

			foreach($group_permissions as $fid => $forum_permissions)
			{
				if(!empty($forum_permissions['canonlyviewownthreads']))
				{
					$onlyusfids[] = $fid;
				}
			}

			if($onlyusfids)
			{
				$permsql .= " OR (fid IN(".implode(',', $onlyusfids).") AND uid!={$CURUSER['id']})";
			}
			$unsearchforums = get_unsearchable_forums();
			if($unsearchforums)
			{
				$permsql .= " OR fid IN ($unsearchforums)";
			}
			$inactiveforums = get_inactive_forums();
			if($inactiveforums)
			{
				$permsql .= " OR fid IN ($inactiveforums)";
			}

			// Find threads in our list that we don't have permission to view and remove them
			$query = $db->simple_select("tsf_threads", "tid", "tid IN(".$db->escape_string(implode(',', array_keys($tids))).") AND (NOT ({$unapproved_where}){$permsql} OR closed LIKE 'moved|%')");
			
			
			
			while($thread = $db->fetch_array($query))
			{
				if(array_key_exists($thread['tid'], $tids))
				{
					$temp_pids = $tids[$thread['tid']];
					foreach($temp_pids as $pid)
					{
						unset($pids[$pid]);
						unset($tids[$thread['tid']]);
					}
					unset($tids[$thread['tid']]);
				}
			}
			unset($temp_pids);
		}

		// Declare our post count
		$postcount = count($pids);

		if(!$postcount)
		{
			stderr($lang->search['error_nosearchresults']);
		}

		// And now we have our sanatized post list
		$search['posts'] = implode(',', array_keys($pids));

		$tids = implode(",", array_keys($tids));

		// Read threads
		$threadreadcut = "7";
		
		if($CURUSER['id'] && $threadreadcut > 0)
		{
			$query = $db->simple_select("tsf_threadsread", "tid, dateline", "uid='".$CURUSER['id']."' AND tid IN(".$db->escape_string($tids).")");
			while($readthread = $db->fetch_array($query))
			{
				$readthreads[$readthread['tid']] = $readthread['dateline'];
			}
		}

		$dot_icon = array();
		
		$dotfolders = "1";
		
		if($dotfolders != 0 && $CURUSER['id'] != 0)
		{
			$query = $db->simple_select("tsf_posts", "DISTINCT tid,uid", "uid='{$CURUSER['id']}' AND tid IN({$db->escape_string($tids)}) AND ({$unapproved_where})");
			while($post = $db->fetch_array($query))
			{
				$dot_icon[$post['tid']] = true;
			}
		}

		$results = '';

		$pages = ceil($postcount / $perpage);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}

		$query = $db->sql_query("
			SELECT p.*, u.username AS userusername, t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views, t.lastpost AS thread_lastpost, t.closed AS thread_closed, t.uid as thread_uid
			FROM tsf_posts p
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			LEFT JOIN users u ON (u.id=p.uid)
			LEFT JOIN tsf_forums f ON (t.fid=f.fid)
			WHERE p.pid IN (".$db->escape_string($search['posts']).")
			ORDER BY $sortfield $order
			LIMIT $start, $perpage
		");
		while($post = $db->fetch_array($query))
		{
			$bgcolor = alt_trow();
			if($post['visible'] == 0)
			{
				$bgcolor = 'trow_shaded';
			}
			elseif($post['visible'] == -1)
			{
				$bgcolor = 'trow_shaded trow_deleted';
			}
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}
			$post['username'] = htmlspecialchars_uni($post['username']);
			$post['profilelink'] = build_profile_link($post['username'], $post['uid']);
			$post['subject'] = $parser->parse_badwords($post['subject']);
			$post['thread_subject'] = $parser->parse_badwords($post['thread_subject']);
			$post['thread_subject'] = htmlspecialchars_uni($post['thread_subject']);

			if(isset($icon_cache[$post['icon']]))
			{
				$posticon = $icon_cache[$post['icon']];
				$posticon['path'] = str_replace("{theme}", $theme['imgdir'], $posticon['path']);
				$posticon['path'] = htmlspecialchars_uni($posticon['path']);
				$posticon['name'] = htmlspecialchars_uni($posticon['name']);
				eval("\$icon = \"".$templates->get("search_results_icon")."\";");
			}
			else
			{
				$icon = "&nbsp;";
			}

			$post['forumlink'] = '';
			if(!empty($forumcache[$post['fid']]))
			{
				$post['forumlink_link'] = get_forum_link($post['fid']);
				$post['forumlink_name'] = $forumcache[$post['fid']]['name'];
				eval("\$post['forumlink'] = \"".$templates->get("search_results_posts_forumlink")."\";");
			}

			// Determine the folder
			$folder = '';
			$folder_label = '';
			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$last_read = 0;

			if(isset($readthreads[$post['tid']]))
			{
				$post['thread_lastread'] = $readthreads[$post['tid']];
			}
			else
			{
				$post['thread_lastread'] = '';
			}

			$threadreadcut = "7";
			
			if($threadreadcut > 0 && $CURUSER['id'])
			{
				$forum_read = $readforums[$post['fid']];

				$read_cutoff = TIMENOW-$threadreadcut*60*60*24;
				if($forum_read == 0 || $forum_read < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
			}
			else
			{
				if(isset($forumsread[$post['fid']]))
				{
					$forum_read = $forumsread[$post['fid']];
				}
				else
				{
					$forum_read = '';
				}
			}

			if($threadreadcut > 0 && $CURUSER['id'] && $post['thread_lastpost'] > $forum_read)
			{
				$cutoff = TIMENOW-$threadreadcut*60*60*24;
				if($post['thread_lastpost'] > $cutoff)
				{
					if($post['thread_lastread'])
					{
						$last_read = $post['thread_lastread'];
					}
					else
					{
						$last_read = 1;
					}
				}
			}

			if(isset($dot_icon[$post['tid']]))
			{
				$folder = "dot_";
				$folder_label .= 'icon_dot';
			}

			if(!$last_read)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $post['tid']);
				if($readcookie > $forum_read)
				{
					$last_read = $readcookie;
				}
				elseif($forum_read > $CURUSER['lastvisit'])
				{
					$last_read = $forum_read;
				}
				else
				{
					$last_read = $CURUSER['lastvisit'];
				}
			}

			if($post['thread_lastpost'] > $last_read && $last_read)
			{
				$folder .= "new";
				$folder_label .= 'icon_new';
				$thread['newpostlink'] = get_thread_link($post['tid'], 0, "newpost");
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= 'icon_no_new';
			}

			
			$hottopicviews = "150";
			$hottopic = "20";
			
			
			if($post['thread_replies'] >= $hottopic || $post['thread_views'] >= $hottopicviews)
			{
				$folder .= "hot";
				$folder_label .= 'icon_hot';
			}
			if($post['thread_closed'] == 1)
			{
				$folder .= "close";
				$folder_label .='icon_close';
			}
			$folder .= "folder";

			$post['thread_replies'] = ts_nf($post['thread_replies']);
			$post['thread_views'] = ts_nf($post['thread_views']);

			$post['forumlink'] = '';
			if($forumcache[$post['fid']])
			{
				$post['forumlink_link'] = get_forum_link($post['fid']);
				$post['forumlink_name'] = $forumcache[$post['fid']]['name'];
				eval("\$post['forumlink'] = \"".$templates->get("search_results_posts_forumlink")."\";");
			}

			if(!$post['subject'])
			{
				$post['subject'] = $post['message'];
			}
			if(my_strlen($post['subject']) > 50)
			{
				$post['subject'] = htmlspecialchars_uni(my_substr($post['subject'], 0, 50)."...");
			}
			else
			{
				$post['subject'] = htmlspecialchars_uni($post['subject']);
			}
			// What we do here is parse the post using our post parser, then strip the tags from it
			$parser_options = array(
				'allow_html' => 0,
				'allow_mycode' => 1,
				'allow_smilies' => 0,
				'allow_imgcode' => 0,
				'me_username' => $post['username'],
				'filter_badwords' => 1
			);
			$post['message'] = strip_tags($parser->text_parse_message($post['message'], $parser_options));
			if(my_strlen($post['message']) > 200)
			{
				$prev = my_substr($post['message'], 0, 200)."...";
			}
			else
			{
				$prev = $post['message'];
			}
			$posted = my_datee('relative', $post['dateline']);

			$thread_url = get_thread_link($post['tid']);
			$post_url = get_post_link($post['pid'], $post['tid']);

			// Inline post moderation
			$inline_mod_checkbox = '';
			if($is_supermod || is_moderator($post['fid']))
			{
				if(isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|{$post['pid']}|") !== false)
				{
					$inlinecheck = "checked=\"checked\"";
					++$inlinecount;
				}
				else
				{
					$inlinecheck = '';
				}

				$show_inline_moderation = true;

				eval("\$inline_mod_checkbox = \"".$templates->get("search_results_posts_inlinecheck")."\";");
			}
			elseif($is_mod)
			{
				eval("\$inline_mod_checkbox = \"".$templates->get("search_results_posts_nocheck")."\";");
			}

			$plugins->run_hooks("search_results_post");
			eval("\$results .= \"".$templates->get("search_results_posts_post")."\";");
		}
		if(!$results)
		{
			stderr($lang->search['error_nosearchresults']);
		}
		$multipage = multipage($postcount, $perpage, $page, "search.php?action=results&amp;sid=".htmlspecialchars_uni($mybb->get_input('sid'))."&amp;sortby=$sortby&amp;order=$order&amp;uid=".$mybb->get_input('uid', MyBB::INPUT_INT));
		if($upper > $postcount)
		{
			$upper = $postcount;
		}

		$selectall = '';
		$inlinemod = '';

		// Inline Post Moderation Options
		if($show_inline_moderation)
		{
			eval("\$inlinemodcol = \"".$templates->get("search_results_inlinemodcol")."\";");

			// If user has moderation tools available, prepare the Select All feature
			$num_results = $db->num_rows($query);
			$page_selected = sprintf('page_selected', (int)$num_results);
			$select_all = sprintf('select_all', (int)$postcount);
			$all_selected = sprintf('all_selected', (int)$postcount);
			eval("\$selectall = \"".$templates->get("search_posts_inlinemoderation_selectall")."\";");

			$customthreadtools = $customposttools = '';
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->simple_select("modtools", "tid, name, type", "type='p' AND (','||forums||',' LIKE '%,-1,%' OR forums='')");
					break;
				default:
					$query = $db->simple_select("modtools", "tid, name, type", "type='p' AND (CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='')");
			}

			while($tool = $db->fetch_array($query))
			{
				eval("\$customposttools .= \"".$templates->get("search_results_posts_inlinemoderation_custom_tool")."\";");
			}
			// Build inline moderation dropdown
			if(!empty($customposttools))
			{
				eval("\$customposttools = \"".$templates->get("search_results_posts_inlinemoderation_custom")."\";");
			}
			eval("\$inlinemod = \"".$templates->get("search_results_posts_inlinemoderation")."\";");
		}
		elseif($is_mod)
		{
			eval("\$inlinemodcol = \"".$templates->get("search_results_inlinemodcol_empty")."\";");
		}

		$plugins->run_hooks("search_results_end");

		eval("\$searchresults = \"".$templates->get("search_results_posts")."\";");
		
		stdhead();
		
		build_breadcrumb();
		
		echo $searchresults;
		
		stdfoot();
	}
}
elseif($mybb->input['action'] == "findguest")
{
	$where_sql = "uid='0'";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND fid NOT IN ($inactiveforums)";
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where = get_visible_where();
	$where_sql .= " AND ({$unapproved_where})";

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$where_sql .= " AND fid NOT IN(".implode(',', $onlyusfids).")";
	}

	$options = array(
		'order_by' => 'dateline DESC, pid DESC',
	);

	// Do we have a hard search limit?
	if($searchhardlimit > 0)
	{
		$options['limit'] = (int)$searchhardlimit;
	}

	$pids = '';
	$comma = '';
	$query = $db->simple_select("tsf_posts", "pid", "{$where_sql}", $options);
	while($pid = $db->fetch_field($query, "pid"))
	{
		$pids .= $comma.$pid;
		$comma = ',';
	}

	$tids = '';
	$comma = '';
	$query = $db->simple_select("tsf_threads", "tid", $where_sql);
	while($tid = $db->fetch_field($query, "tid"))
	{
		$tids .= $comma.$tid;
		$comma = ',';
	}

	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $db->escape_string($tids),
		"posts" => $db->escape_string($pids),
		"resulttype" => "posts",
		"querycache" => '',
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
elseif($mybb->input['action'] == "finduser")
{
	$where_sql = "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND fid NOT IN ($inactiveforums)";
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	//$unapproved_where = get_visible_where();
	//$where_sql .= " AND ({$unapproved_where})";

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$where_sql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$CURUSER['id']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$options = array(
		'order_by' => 'dateline DESC, pid DESC',
	);

	// Do we have a hard search limit?
	$searchhardlimit = "0";
	
	if($searchhardlimit > 0)
	{
		$options['limit'] = (int)$searchhardlimit;
	}

	$pids = '';
	$comma = '';
	$query = $db->simple_select("tsf_posts", "pid", "{$where_sql}", $options);
	while($pid = $db->fetch_field($query, "pid"))
	{
		$pids .= $comma.$pid;
		$comma = ',';
	}

	$tids = '';
	$comma = '';
	$query = $db->simple_select("tsf_threads", "tid", $where_sql);
	while($tid = $db->fetch_field($query, "tid"))
	{
		$tids .= $comma.$tid;
		$comma = ',';
	}

	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $db->escape_string($tids),
		"posts" => $db->escape_string($pids),
		"resulttype" => "posts",
		"querycache" => '',
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
elseif($mybb->input['action'] == "finduserthreads")
{
	$where_sql = "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND fid NOT IN ($inactiveforums)";
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where = get_visible_where();
	$where_sql .= " AND ({$unapproved_where})";

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$where_sql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$CURUSER['id']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$tids = '';
	$comma = '';
	$query = $db->simple_select("tsf_threads", "tid", $where_sql);
	while($tid = $db->fetch_field($query, "tid"))
	{
		$tids .= $comma.$tid;
		$comma = ',';
	}

	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $db->escape_string($tids),
		"posts" => '',
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);
	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
elseif($mybb->input['action'] == "getnew")
{

	$where_sql = "lastpost >= '".(int)$CURUSER['lastvisit']."'";

	if($mybb->get_input('fid', MyBB::INPUT_INT))
	{
		$where_sql .= " AND fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'";
	}
	else if($mybb->get_input('fids'))
	{
		$fids = explode(',', $mybb->get_input('fids'));
		foreach($fids as $key => $fid)
		{
			$fids[$key] = (int)$fid;
		}

		if(!empty($fids))
		{
			$where_sql .= " AND fid IN (".implode(',', $fids).")";
		}
	}

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND fid NOT IN ($inactiveforums)";
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where = get_visible_where();
	$where_sql .= " AND ({$unapproved_where})";

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
    }
	if(!empty($onlyusfids))
	{
		$where_sql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$CURUSER['id']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$tids = '';
	$comma = '';
	$query = $db->simple_select("tsf_threads", "tid", $where_sql);
	while($tid = $db->fetch_field($query, "tid"))
	{
		$tids .= $comma.$tid;
		$comma = ',';
	}

	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $db->escape_string($tids),
		"posts" => '',
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
elseif($mybb->input['action'] == "getdaily")
{
	if($mybb->get_input('days', MyBB::INPUT_INT) < 1)
	{
		$days = 1;
	}
	else
	{
		$days = $mybb->get_input('days', MyBB::INPUT_INT);
	}
	$datecut = TIMENOW-(86400*$days);

	$where_sql = "lastpost >='".$datecut."'";

	if($mybb->get_input('fid', MyBB::INPUT_INT))
	{
		$where_sql .= " AND fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'";
	}
	else if($mybb->get_input('fids'))
	{
		$fids = explode(',', $mybb->get_input('fids'));
		foreach($fids as $key => $fid)
		{
			$fids[$key] = (int)$fid;
		}

		if(!empty($fids))
		{
			$where_sql .= " AND fid IN (".implode(',', $fids).")";
		}
	}

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$where_sql .= " AND fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$where_sql .= " AND fid NOT IN ($inactiveforums)";
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where = get_visible_where();
	$where_sql .= " AND ({$unapproved_where})";

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$where_sql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$CURUSER['id']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$tids = '';
	$comma = '';
	$query = $db->simple_select("tsf_threads", "tid", $where_sql);
	while($tid = $db->fetch_field($query, "tid"))
	{
		$tids .= $comma.$tid;
		$comma = ',';
	}

	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $db->escape_string($tids),
		"posts" => '',
		"resulttype" => "threads",
		"querycache" => $db->escape_string($where_sql),
		"keywords" => ''
	);

	$plugins->run_hooks("search_do_search_process");
	$db->insert_query("searchlog", $searcharray);
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
elseif($mybb->input['action'] == "do_search")
{
	$plugins->run_hooks("search_do_search_start");

	// Check if search flood checking is enabled and user is not admin
	$searchfloodtime = "30";
	
	if($searchfloodtime > 0 && $usergroups['cansearch'] != 1)
	{
		// Fetch the time this user last searched
		if($CURUSER['id'])
		{
			$conditions = "uid='{$CURUSER['id']}'";
		}
		else
		{
			$conditions = "uid='0' AND ipaddress=".$CURUSER['ip'];
		}
		$timecut = TIMENOW-$searchfloodtime;
		$query = $db->simple_select("searchlog", "*", "$conditions AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if(!empty($last_search['sid']))
		{
			$remaining_time = $searchfloodtime-(TIMENOW-$last_search['dateline']);
			if($remaining_time == 1)
			{
				$error_searchflooding = sprintf($lang->search['error_searchflooding_1'], $searchfloodtime);
			}
			else
			{
				$error_searchflooding = sprintf($lang->search['error_searchflooding'], $searchfloodtime, $remaining_time);
			}
			stderr($error_searchflooding);
		}
	}
	if($mybb->get_input('showresults') == "threads")
	{
		$resulttype = "threads";
	}
	else
	{
		$resulttype = "posts";
	}

	if(isset($mybb->input['forums']) && is_array($mybb->input['forums']))
	{
		$forums = $mybb->get_input('forums', MyBB::INPUT_ARRAY);
	}
	else
	{
		$forums = array($mybb->get_input('forums'));
	}

	$search_data = array(
		"keywords" => $mybb->input['keywords'],
		"author" => $mybb->get_input('author'),
		"postthread" => $mybb->get_input('postthread', MyBB::INPUT_INT),
		"matchusername" => $mybb->get_input('matchusername', MyBB::INPUT_INT),
		"postdate" => $mybb->get_input('postdate', MyBB::INPUT_INT),
		"pddir" => $mybb->get_input('pddir', MyBB::INPUT_INT),
		"forums" => $forums,
		"findthreadst" => $mybb->get_input('findthreadst', MyBB::INPUT_INT),
		"numreplies" => $mybb->get_input('numreplies', MyBB::INPUT_INT),
		"threadprefix" => $mybb->get_input('threadprefix', MyBB::INPUT_ARRAY)
	);

	if($is_mod && !empty($mybb->input['visible']))
	{
		$search_data['visible'] = $mybb->get_input('visible', MyBB::INPUT_INT);
	}

	$searchtype = "standard";
	
	if($db->can_search == true)
	{
		if($searchtype == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->is_fulltext("posts"))
		{
			$search_results = perform_search_mysql_ft($search_data);
		}
		else
		{
			$search_results = perform_search_mysql($search_data);
		}
	}
	else
	{
		stderr('error_no_search_support');
	}
	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => $now,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $search_results['threads'],
		"posts" => $search_results['posts'],
		"resulttype" => $resulttype,
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->input['keywords']),
	);
	$plugins->run_hooks("search_do_search_process");

	$db->insert_query("searchlog", $searcharray);

	if(my_strtolower($mybb->get_input('sortordr')) == "asc" || my_strtolower($mybb->get_input('sortordr') == "desc"))
	{
		$sortorder = $mybb->get_input('sortordr');
	}
	else
	{
		$sortorder = "desc";
	}
	$sortby = htmlspecialchars_uni($mybb->get_input('sortby'));
	$plugins->run_hooks("search_do_search_end");
	redirect("search.php?action=results&sid=".$sid."&sortby=".$sortby."&order=".$sortorder, $lang->search['redirect_searchresults']);
}
else if($mybb->input['action'] == "thread")
{
	// Fetch thread info
	$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
	//$ismod = is_moderator($thread['fid']);

	//if(!$thread || ($thread['visible'] != 1 && $ismod == false && ($thread['visible'] != -1 || $mybb->settings['soft_delete'] != 1 || !$mybb->user['uid'] || $mybb->user['uid'] != $thread['uid'])) || ($thread['visible'] > 1 && $ismod == true))
	//{
		//stderr('error_invalidthread');
	//}

	// Get forum info
	$forum = get_forum($thread['fid']);
	if(!$forum)
	{
		stderr($lang->search['error_invalidforum']);
	}

	$forum_permissions = forum_permissions($forum['fid']);

	if($forum['open'] == 0 || $forum['type'] != "f")
	{
		stderr($lang->search['error_closedinvalidforum']);
	}
	if($forum_permissions['canview'] == 0 || $forum_permissions['canviewthreads'] != 1 || (isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $CURUSER['id']))
	{
		//print_no_permission();
	}

	$plugins->run_hooks("search_thread_start");

	// Check if search flood checking is enabled and user is not admin
	if($searchfloodtime > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		if($CURUSER['id'])
		{
			$conditions = "uid='{$CURUSER['id']}'";
		}
		else
		{
			$conditions = "uid='0' AND ipaddress=".$db->escape_binary($session->packedip);
		}
		$timecut = TIMENOW-$searchfloodtime;
		$query = $db->simple_select("searchlog", "*", "$conditions AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);

		if($last_search)
		{
			// We shouldn't show remaining time if time is 0 or under.
			$remaining_time = $searchfloodtime-(TIMENOW-$last_search['dateline']);
			// Users last search was within the flood time, show the error.
			if($remaining_time > 0)
			{
				if($remaining_time == 1)
				{
					$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $searchfloodtime);
				}
				else
				{
					$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $searchfloodtime, $remaining_time);
				}
				stderr('error_searchflooding');
			}
		}
	}

	$search_data = array(
		"keywords" => $mybb->input['keywords'],
		"postthread" => 1,
		"tid" => $mybb->get_input('tid', MyBB::INPUT_INT)
	);

	
	$searchtype = "standard";
	
	if($db->can_search == true)
	{
		if($searchtype == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->is_fulltext("posts"))
		{
			$search_results = perform_search_mysql_ft($search_data);
		}
		else
		{
			$search_results = perform_search_mysql($search_data);
		}
	}
	else
	{
		stderr($lang->search['error_no_search_support']);
	}
	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => $now,
		"ipaddress" => $CURUSER['ip'],
		"threads" => $search_results['threads'],
		"posts" => $search_results['posts'],
		"resulttype" => 'posts',
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->input['keywords'])
	);
	$plugins->run_hooks("search_thread_process");

	$db->insert_query("searchlog", $searcharray);

	$plugins->run_hooks("search_do_search_end");
	redirect("search.php?action=results&sid=".$sid, $lang->search['redirect_searchresults']);
}
else
{
	$plugins->run_hooks("search_start");
	$srchlist = make_searchable_forums();
	$prefixselect = build_prefix_select('all', 'any', 1);
	
	
	$maxnamelength = "30";

	$rowspan = 5;

	$moderator_options = '';
	if($is_mod)
	{
		$rowspan += 2;
		eval("\$moderator_options = \"".$templates->get("search_moderator_options")."\";");
	}

	$plugins->run_hooks("search_end");

	eval("\$search = \"".$templates->get("search")."\";");
	
	stdhead();
	
	build_breadcrumb();
	
	echo $search;
	
	stdfoot();
}
