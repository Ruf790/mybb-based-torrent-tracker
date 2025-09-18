<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/




 define("IN_MYBB", 1);
 define('THIS_SCRIPT', 'modcp.php');
 define("SCRIPTNAME", "modcp.php");

$templatelist = "modcp_reports,modcp_reports_report,modcp_reports_selectall,modcp_reports_multipage,modcp_reports_allreport,modcp_reports_allreports,modcp_modlogs_multipage,modcp_announcements_delete,modcp_announcements_edit,modcp_awaitingmoderation";
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,modcp_banning,modcp_banning_ban,modcp_announcements_announcement_global,modcp_no_announcements_forum,modcp_modqueue_threads_thread,modcp_awaitingthreads,preview";
$templatelist .= ",modcp_banning_nobanned,modcp_modqueue_threads_empty,modcp_modqueue_masscontrols,modcp_modqueue_threads,modcp_modqueue_posts_post,modcp_modqueue_posts_empty,modcp_awaitingposts,modcp_nav_editprofile,modcp_nav_banning";
$templatelist .= ",modcp_nav,modcp_modlogs_noresults,modcp_modlogs_nologs,modcp,modcp_modqueue_posts,modcp_modqueue_attachments_attachment,modcp_modqueue_attachments_empty,modcp_modqueue_attachments,modcp_editprofile_suspensions_info";
$templatelist .= ",modcp_no_announcements_global,modcp_announcements_global,modcp_announcements_forum,modcp_announcements,modcp_editprofile_select_option,modcp_editprofile_select,modcp_finduser_noresults, modcp_nav_forums_posts";
$templatelist .= ",codebuttons,modcp_announcements_new,modcp_modqueue_empty,forumjump_bit,forumjump_special,modcp_warninglogs_warning_revoked,modcp_warninglogs_warning,modcp_ipsearch_result,modcp_nav_modqueue,modcp_banuser_liftlist";
$templatelist .= ",modcp_modlogs,modcp_finduser_user,modcp_finduser,usercp_profile_customfield,usercp_profile_profilefields,modcp_ipsearch_noresults,modcp_ipsearch_results,modcp_ipsearch_misc_info,modcp_nav_announcements,modcp_modqueue_post_link";
$templatelist .= ",modcp_editprofile,modcp_ipsearch,modcp_banuser_addusername,modcp_banuser,modcp_warninglogs_nologs,modcp_banuser_editusername,modcp_lastattachment,modcp_lastpost,modcp_lastthread,modcp_nobanned,modcp_modqueue_thread_link";
$templatelist .= ",modcp_warninglogs,modcp_modlogs_result,modcp_editprofile_signature,forumjump_advanced,modcp_announcements_forum_nomod,modcp_announcements_announcement,usercp_profile_away,modcp_modlogs_user,modcp_editprofile_away";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,modcp_awaitingattachments,modcp_modqueue_attachment_link";
$templatelist .= ",postbit_groupimage,postbit_userstar,postbit_online,postbit_offline,postbit_away,postbit_avatar,postbit_find,postbit_pm,postbit_email,postbit_www,postbit_author_user,announcement_edit,announcement_quickdelete";
$templatelist .= ",modcp_awaitingmoderation_none,modcp_banning_edit,modcp_banuser_bangroups_group,modcp_banuser_lift,modcp_modlogs_result_announcement,modcp_modlogs_result_forum,modcp_modlogs_result_post,modcp_modlogs_result_thread";
$templatelist .= ",modcp_nav_warninglogs,modcp_nav_ipsearch,modcp_nav_users,modcp_announcements_day,modcp_announcements_month_start,modcp_announcements_month_end,modcp_announcements_announcement_expired,modcp_announcements_announcement_active";
$templatelist .= ",modcp_modqueue_link_forum,modcp_modqueue_link_thread,usercp_profile_day,modcp_ipsearch_result_regip,modcp_ipsearch_result_lastip,modcp_ipsearch_result_post,modcp_ipsearch_results_information,usercp_profile_profilefields_text";
$templatelist .= ",usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,postbit";
$templatelist .= ",modcp_banning_remaining,postmodcp_nav_announcements,modcp_nav_reportcenter,modcp_nav_modlogs,modcp_latestfivemodactions,modcp_banuser_bangroups_hidden,modcp_banuser_bangroups,usercp_profile_profilefields_checkbox";

  
  
  
  
  define ('TSF_FORUMS_TSSEv56', true);
  define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
  define ('TSF_VERSION', 'v1.5 by xam');
  //define ('IN_FORUMS', true );

  require_once 'global.php';
  
  
  if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
  {
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  
  require_once INC_PATH.'/tsf_functions.php';
  
  
  require_once INC_PATH."/functions_modcp.php";
  
  
  require_once INC_PATH . '/editor.php';
  
  require_once INC_PATH.'/functions_user.php';
  
  require_once INC_PATH . '/functions_multipage.php';
  
  require_once INC_PATH.'/functions_mkprettytime.php';
  
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  

  require_once(INC_PATH.'/class_parser.php');
  $parser = new postParser;
  
 
  
  
  // Load global language phrases
  $lang->load("modcp");
  
  
  
  if($CURUSER['id'] == 0 || $usergroups['canstaffpanel'] != 1)
  {
	print_no_permission();
  }
  
  
  
  if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
  {
	$f_threadsperpage = 20;
  }
  
  
  $tflist = $flist = $tflist_queue_threads = $flist_queue_threads = $tflist_queue_posts = $flist_queue_posts = $tflist_queue_attach =
  $flist_queue_attach = $wflist_reports = $tflist_reports = $flist_reports = $tflist_modlog = $flist_modlog = $errors = '';
 

 // SQL for fetching items only related to forums this user moderates
$moderated_forums = array();
$numannouncements = $nummodqueuethreads = $nummodqueueposts = $nummodqueueattach = $numreportedposts = $nummodlogs = 0;







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
  

  
  
  function inline_error22222222222($errors, $title="", $json_data=array())
  {
	global $theme, $mybb, $db, $lang, $templates, $charset;

	if(!$title)
	{
		$title = 'please_correct_errors';
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
  
  // Set up the array of ban times.
$bantimes = fetch_ban_times();
  

  
 
$plugins->run_hooks("modcp_nav");



    eval("\$nav_announcements = \"".$templates->get("modcp_nav_announcements")."\";");
	
	
	eval("\$nav_modqueue = \"".$templates->get("modcp_nav_modqueue")."\";");
	

    eval("\$nav_editprofile = \"".$templates->get("modcp_nav_editprofile")."\";");

    eval("\$nav_modlogs = \"".$templates->get("modcp_nav_modlogs")."\";");


	eval("\$nav_banning = \"".$templates->get("modcp_nav_banning")."\";");
	
	
	$expaltext = (in_array("modcpforums", $collapse)) ? '[+]' : '[-]';
	
	eval("\$modcp_nav_forums_posts = \"".$templates->get("modcp_nav_forums_posts")."\";");
	
	
	
	$expaltext = (in_array("modcpusers", $collapse)) ? '[+]' : '[-]';
	
	eval("\$modcp_nav_users = \"".$templates->get("modcp_nav_users")."\";");
	
	


eval("\$modcp_nav = \"".$templates->get("modcp_nav")."\";");


$plugins->run_hooks("modcp_start");


add_breadcrumb($lang->modcp['nav_modcp'], "modcp.php");



$mybb->input['action'] = $mybb->get_input('action');



if($mybb->input['action'] == "liftban")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canbanusers'] == 0)
	//{
		//error_no_permission();
	//}


	
	$query = $db->simple_select("banned", "*", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
	
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		stderr($lang->modcp['error_invalidban']);
	}

	// Permission to edit this ban?
	if($CURUSER['id'] != $ban['admin'] && $usergroups['issupermod'] != '1' && $usergroups['canuserdetails'] != '1')
	{
		print_no_permission();
	}

	$plugins->run_hooks("modcp_liftban_start");

	$query = $db->simple_select("users", "username", "id = '{$ban['uid']}'");
	$username = $db->fetch_field($query, "username");

	$updated_group = array(
		'usergroup' => $ban['oldgroup'],
		'additionalgroups' => $db->escape_string($ban['oldadditionalgroups']),
		'displaygroup' => $ban['olddisplaygroup'],
		"notifs" => ''
	);
	$db->update_query("users", $updated_group, "id='{$ban['uid']}'");
	$db->delete_query("banned", "uid='{$ban['uid']}'");

	$cache->update_moderators();
	log_moderator_action(array("uid" => $ban['uid'], "username" => $username), 'Lifted User Ban');

	$plugins->run_hooks("modcp_liftban_end");

	redirect("modcp.php?action=banning", 'The ban has successfully been lifted');
}




if($mybb->input['action'] == "do_banuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canbanusers'] == 0)
	//{
		//error_no_permission();
	//}

	// Editing an existing ban
	$existing_ban = false;
	if($mybb->input['uid'])
	{
		// Get the users info from their uid
		$uid = (int)$mybb->input['uid']; 

        $query = $db->sql_query_prepared("
           SELECT b.*, u.uid, u.username, u.usergroup, u.additionalgroups, u.displaygroup
           FROM banned b
           LEFT JOIN users u ON (b.uid = u.id)
           WHERE b.uid = ?", [$uid]);

		
		$user = $db->fetch_array($query);

		if($user)
		{
			$existing_ban = true;
		}

		// Permission to edit this ban?
		if($existing_ban && $CURUSER['id'] != $user['admin'] && $usergroups['issupermod'] != '1' && $usergroups['canuserdetails'] != '1')
		{
			print_no_permission();
		}
	}

	$errors = array();

	// Creating a new ban
	if(!$existing_ban)
	{
		// Get the users info from their Username
		$options = array(
			'fields' => array('username', 'usergroup', 'additionalgroups', 'displaygroup')
		);

		$user = get_user_by_username($mybb->input['username'], $options);

		if(!$user)
		{
			$errors[] = 'The username you entered was invalid. Please ensure you enter a valid username';
		}
	}

	if($user['id'] == $CURUSER['id'])
	{
		$errors[] = 'You cannot ban yourself. Please enter another username';
	}

	// Have permissions to ban this user?
	if(!modcp_can_manage_user($user['uid']))
	{
		$errors[] = 'error_cannotbanuser';
	}

	// Check for an incoming reason
	if(empty($mybb->input['banreason']))
	{
		$errors[] = 'You did not enter a reason for this ban. Please enter a valid reason below';
	}

	
	
	
	
	
	// Check banned group
	$usergroups_cache = $cache->read('usergroups');
	if(isset($usergroups_cache[$mybb->get_input('usergroup', MyBB::INPUT_INT)]))
	{
		$usergroup = $usergroups_cache[$mybb->get_input('usergroup', MyBB::INPUT_INT)];
	}

	if(!isset($usergroup) || empty($usergroup['isbannedgroup']))
	{
		$errors[] = 'You did not select a valid group to move this user to';
	}
	
	
	
	
	

	// If this is a new ban, we check the user isn't already part of a banned group
	if(!$existing_ban && $user['id'])
	{
		$query = $db->simple_select("banned", "uid", "uid='{$user['id']}'", array('limit' => 1));
		if($db->num_rows($query) > 0)
		{
			$errors[] = 'This user is already banned. You cannot ban a user more than once';
		}
	}

	$plugins->run_hooks("modcp_do_banuser_start");

	// Still no errors? Ban the user
	if(!$errors)
	{
		// Ban the user
		if($mybb->input['liftafter'] == '---')
		{
			$lifted = 0;
		}
		else
		{
			if(!isset($user['dateline']))
			{
				$user['dateline'] = 0;
			}
			
			$lifted = ban_date2timestamp($mybb->get_input('liftafter'), $user['dateline']);
		}

		$banreason = my_substr($mybb->get_input('banreason'), 0, 255);

		if($existing_ban)
		{
			$update_array = array(
				'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'dateline' => TIMENOW,
				'bantime' => $db->escape_string($mybb->get_input('liftafter')),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($banreason)
			);

			$db->update_query('banned', $update_array, "uid='{$user['uid']}'");
			
			
			$notifs = array(
				"notifs" => $db->escape_string('Ban Reason: ' . $banreason)
			);
					
			$db->update_query('users', $notifs, "id='{$user['uid']}'");
			
			
		}
		else
		{
			$insert_array = array(
				'uid' => $user['id'],
				'gid' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
				'oldgroup' => (int)$user['usergroup'],
				'oldadditionalgroups' => $db->escape_string($user['additionalgroups']),
				'olddisplaygroup' => (int)$user['displaygroup'],
				'admin' => (int)$CURUSER['id'],
				'dateline' => TIMENOW,
				'bantime' => $db->escape_string($mybb->get_input('liftafter')),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($banreason)
			);

			$db->insert_query('banned', $insert_array);
			
			
			$update_notifs = array(
				"notifs" => $db->escape_string('Ban Reason: ' . $banreason)
			);
					
			$db->update_query('users', $update_notifs, "id='{$user['id']}'");
				
			
			
			
		}

		// Move the user to the banned group
		$update_array = array(
			'usergroup' => $mybb->get_input('usergroup', MyBB::INPUT_INT),
			'displaygroup' => 0,
			'additionalgroups' => ''
		);
		$db->update_query('users', $update_array, "id = {$user['id']}");

		// Log edit or add ban
		if($existing_ban)
		{
			log_moderator_action(array("uid" => $user['id'], "username" => $user['username']), 'Edited User Ban');
		}
		else
		{
			log_moderator_action(array("uid" => $user['id'], "username" => $user['username']), 'Banned User');
		}

		$plugins->run_hooks("modcp_do_banuser_end");

		if($existing_ban)
		{
			redirect("modcp.php?action=banning", 'The users ban has successfully been updated');
		}
		else
		{
			redirect("modcp.php?action=banning",'The user has successfully been banned');
		}
	}
	// Otherwise has errors, throw back to ban page
	else
	{
		$mybb->input['action'] = "banuser";
	}
}


if($mybb->input['action'] == "banuser")
{
	add_breadcrumb($lang->modcp['mcp_nav_banning'], "modcp.php?action=banning");
	
	

	//if($mybb->usergroup['canbanusers'] == 0)
	//{
		//error_no_permission();
	//}

	
	if($mybb->input['uid'])
	{
		add_breadcrumb($lang->modcp['mcp_nav_editing_ban']);
	}
	else
	{
		add_breadcrumb($lang->modcp['mcp_nav_ban_user']);
	}

	$plugins->run_hooks("modcp_banuser_start");

	$banuser_username = '';
	$banreason = '';

	// If incoming user ID, we are editing a ban
	if($mybb->input['uid'])
	{
		
		
		$uid = (int)$mybb->input['uid']; // приведение к integer для безопасности

        $query = $db->sql_query_prepared("
            SELECT b.*, u.username, u.id
            FROM banned b
            LEFT JOIN users u ON (b.uid = u.id)
            WHERE b.uid = ?", [$uid]);


		
		$banned = $db->fetch_array($query);
		if(!empty($banned['username']))
		{
			$username = $banned['username'] = htmlspecialchars_uni($banned['username']);
			$banreason = htmlspecialchars_uni($banned['reason']);
			$uid = $mybb->input['id'];
			$user = get_user($banned['uid']);
			$lang->modcp['ban_user'] = $lang->modcp['edit_ban']; // Swap over lang variables
			
			
			
			eval("\$banuser_username = \"".$templates->get("modcp_banuser_editusername")."\";");
  
		}
	}

	// Permission to edit this ban?
	//if(!empty($banned) && $banned['uid'] && $mybb->user['uid'] != $banned['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	//{
		//error_no_permission();
	//}

	// New ban!
	if(!$banuser_username)
	{
		if($mybb->input['uid'])
		{
			$user = get_user($mybb->input['uid']);
			$user['username'] = htmlspecialchars_uni($user['username']);
			$username = $user['username'];
		}
		else
		{
			$username = htmlspecialchars_uni($mybb->input['username']);
		}
		eval("\$banuser_username = \"".$templates->get("modcp_banuser_addusername")."\";");
	   
	   
	}

	// Coming back to this page from an error?
	if($errors)
	{
		$errors = inline_error($errors);
		$banned = array(
			"bantime" => $mybb->input['liftafter'],
			"reason" => $mybb->input['reason'],
			"gid" => $mybb->input['gid']
		);
		$banreason = htmlspecialchars_uni($mybb->input['banreason']);
	}

	// Generate the banned times dropdown
	$liftlist = '';
	foreach($bantimes as $time => $title)
	{
		$selected = '';
		if(isset($banned['bantime']) && $banned['bantime'] == $time)
		{
			$selected = " selected=\"selected\"";
		}

		$thattime = '';
		if($time != '---')
		{
			$dateline = TIMENOW;
			if(isset($banned['dateline']))
			{
				$dateline = $banned['dateline'];
			}

			$thatime = my_datee("D, jS M Y @ {$timeformat}", ban_date2timestamp($time, $dateline));
			
			$thattime = " ({$thatime})";
		}

		$liftlist .= '<option value="'.$time.'"'.$selected.'>'.$title.''.$thattime.'</option>';
	}

	$bangroup_option = $bangroups = '';
	$numgroups = $banned_group = 0;
	$groupscache = $cache->read("usergroups");

	foreach($groupscache as $key => $group)
	{
		if($group['isbannedgroup'])
		{
			$selected = "";
			if(isset($banned['gid']) && $banned['gid'] == $group['gid'])
			{
				$selected = " selected=\"selected\"";
			}

			$group['title'] = htmlspecialchars_uni($group['title']);
			
			$bangroup_option .= '<option value="'.$group['gid'].'"'.$selected.'>'.$group['title'].'</option>';
			
			$banned_group = $group['gid'];
			++$numgroups;
		}
	}

	if($numgroups == 0)
	{
		stderr('no_banned_group');
	}
	elseif($numgroups > 1)
	{
		eval("\$bangroups = \"".$templates->get("modcp_banuser_bangroups")."\";");
	}
	else
	{
		eval("\$bangroups = \"".$templates->get("modcp_banuser_bangroups_hidden")."\";");
	}

	if(!empty($banned['uid']))
	{
		eval("\$lift_link = \"".$templates->get("modcp_banuser_lift")."\";");
		$uid = $banned['uid'];
	}
	else
	{
		$lift_link = '';
		$uid = 0;
	}

	$plugins->run_hooks("modcp_banuser_end");

	
	stdhead($lang->modcp['ban_user']);
	
	build_breadcrumb();
	
	
	eval("\$banuser = \"".$templates->get("modcp_banuser")."\";");
	

	echo $banuser;
}


if($mybb->input['action'] == "banning")
{
	//if($mybb->usergroup['canbanusers'] == 0)
	//{
		//error_no_permission();
	//}

	add_breadcrumb($lang->modcp['mcp_nav_banning'], "modcp.php?action=banning");

	if(!$f_threadsperpage)
	{
		$f_threadsperpage = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $f_threadsperpage;
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$query = $db->simple_select("banned", "COUNT(uid) AS count");
	$banned_count = $db->fetch_field($query, "count");

	$postcount = (int)$banned_count;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$upper = $start+$perpage;

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=banning");

	$plugins->run_hooks("modcp_banning_start");

	
	$start = (int)$start;
    $perpage = (int)$perpage;

    $query = $db->sql_query_prepared("
       SELECT b.*, a.username AS adminuser, u.username
       FROM banned b
       LEFT JOIN users u ON (b.uid = u.id)
       LEFT JOIN users a ON (b.admin = a.id)
       ORDER BY dateline DESC
       LIMIT ?, ?", [$start, $perpage]);



	// Get the banned users
	$bannedusers = '';
	while($banned = $db->fetch_array($query))
	{
		$banned['username'] = htmlspecialchars_uni($banned['username']);
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($CURUSER['id'] == $banned['admin'] || !$banned['adminuser'] || $usergroups['issupermod'] == 1)
		{
			$edit_link = '
			
			<a href="modcp.php?action=banuser&amp;uid='.$banned['uid'].'">Edit Ban</a> &mdash; <a href="modcp.php?action=liftban&amp;uid='.$banned['uid'].'&amp;my_post_key='.$mybb->post_code.'">Lift Ban</a>
			
			';
		}

		$admin_profile = build_profile_link(htmlspecialchars_uni($banned['adminuser']), $banned['admin']);

		$trow = alt_trow();

		if($banned['reason'])
		{
			$banned['reason'] = htmlspecialchars_uni($parser->parse_badwords($banned['reason']));
		}
		else
		{
			$banned['reason'] = 'na';
		}

		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banlength = 'Permanent';
			$timeremaining = 'na';
		}
		else
		{
			$banlength = $bantimes[$banned['bantime']];
			$remaining = $banned['lifted']-TIMENOW;

			
			
			$timeremaining = mkprettytime($remaining);

			$banned_class = '';
			$ban_remaining = "{$timeremaining} remaining";

			if($remaining <= 0)
			{
				$banned_class = "imminent_banned";
				$ban_remaining = 'Ban Ending Imminently';
			}
			if($remaining < 3600)
			{
				$banned_class = "high_banned";
			}
			else if($remaining < 86400)
			{
				$banned_class = "moderate_banned";
			}
			else if($remaining < 604800)
			{
				$banned_class = "low_banned";
			}
			else
			{
				$banned_class = "normal_banned";
			}

			$timeremaining = '
			
			
			<span class="text-danger">'.$ban_remaining.'</span>
			
			
			';
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		$bannedusers = '<div class="py-2 border-top">There are currently no banned users</div>';
	}

	$plugins->run_hooks("modcp_banning");

	
	stdhead($lang->modcp['ban_banned']);
	
	build_breadcrumb();
	
	eval("\$bannedpage = \"".$templates->get("modcp_banning")."\";");
	
	echo $bannedpage;

}









if($mybb->input['action'] == "do_edit_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
	//	error_no_permission();
	//}

	// Get the announcement
	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("tsf_announcements", "*", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	// Check that it exists
	if(!$announcement)
	{
		stderr('The specified announcement is invalid');
	}

	// Mod has permissions to edit this announcement
	//if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	//{
		//error_no_permission();
	//}

	$errors = array();

	// Basic error checking
	
	if(!trim($mybb->input['title']))
	{
		$errors[] = 'error_missing_title';
	}

	
	if(!trim($mybb->input['message']))
	{
		$errors[] = 'error_missing_message';
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$localized_time_offset = (float)$CURUSER['timezone']*3600 + $CURUSER['dst']*3600;

	$startdate = gmmktime((int)$startdate[0], (int)$startdate[1], 0, $mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) - $localized_time_offset;
	if(!checkdate($mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) || $startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->get_input('endtime_type', MyBB::INPUT_INT) == "2")
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime((int)$enddate[0], (int)$enddate[1], 0, $mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) - $localized_time_offset;
		if(!checkdate($mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) || $enddate < 0 || $enddate == false)
		{
			$errors[] = 'error_invalid_end_date';
		}
		elseif($enddate <= $startdate)
		{
			$errors[] = 'error_end_before_start';
		}
	}
	

	

	$plugins->run_hooks("modcp_do_edit_announcement_start");

	// Proceed to update if no errors
	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'edit_announcement';
		}
		else
		{
			$update_announcement = array(
				'uid' => $CURUSER['id'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate
			);
			$db->update_query("tsf_announcements", $update_announcement, "aid='{$aid}'");

			log_moderator_action(array("aid" => $announcement['aid'], "subject" => $mybb->input['title']), 'Announcement Edited');

			$plugins->run_hooks("modcp_do_edit_announcement_end");

			//$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", 'redirect_edit_announcement');
		}
	}
	else
	{
		$mybb->input['action'] = 'edit_announcement';
	}
}




if($mybb->input['action'] == "edit_announcement")
{
	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
		//error_no_permission();
	//}

	$aid = intval($mybb->input['aid']);

	add_breadcrumb($lang->modcp['mcp_nav_announcements'], "modcp.php?action=announcements");
	add_breadcrumb($lang->modcp['edit_announcement'], "modcp.php?action=edit_announcements&amp;aid={$aid}");

	// Get announcement
	if(!isset($announcement) || $mybb->request_method != 'post')
	{
		$query = $db->simple_select("tsf_announcements", "*", "aid='{$aid}'");
		$announcement = $db->fetch_array($query);
	}

	if(!$announcement)
	{
		stderr('error_invalid_announcement');
	}
	//if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	//{
		//error_no_permission();
	//}

	if(!$announcement['startdate'])
	{
		// No start date? Make it now.
		$announcement['startdate'] = TIMENOW;
	}

	$makeshift_end = false;
	if(!$announcement['enddate'])
	{
		$makeshift_end = true;
		$makeshift_time = TIMENOW;
		if($announcement['startdate'])
		{
			$makeshift_time = $announcement['startdate'];
		}

		// No end date? Make it a year from now.
		$announcement['enddate'] = $makeshift_time + (60 * 60 * 24 * 366);
	}

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		

		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = $mybb->get_input('starttime_day', MyBB::INPUT_INT);
		$starttime_time = htmlspecialchars_uni($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = $mybb->get_input('endtime_day', MyBB::INPUT_INT);
		$endtime_time = htmlspecialchars_uni($mybb->input['endtime_time']);

		$errored = true;
	}
	else
	{
		$localized_time_startdate = $announcement['startdate'] + (float)$CURUSER['timezone']*3600 + $CURUSER['dst']*3600;
		$localized_time_enddate = $announcement['enddate'] + (float)$CURUSER['timezone']*3600 + $CURUSER['dst']*3600;

		$starttime_time = gmdate($timeformat, $localized_time_startdate);
		$endtime_time = gmdate($timeformat, $localized_time_enddate);

		$startday = gmdate('j', $localized_time_startdate);
		$endday = gmdate('j', $localized_time_enddate);

		$startmonth = gmdate('m', $localized_time_startdate);
		$endmonth = gmdate('m', $localized_time_enddate);

		$startdateyear = gmdate('Y', $localized_time_startdate);
		$enddateyear = gmdate('Y', $localized_time_enddate);

		$errored = false;
	}

	// Generate form elements
	$startdateday = $enddateday = '';
	for($day = 1; $day <= 31; ++$day)
	{
		if($startday == $day)
		{
			$selected = " selected=\"selected\"";
			$startdateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
		else
		{
			$selected = '';
			$startdateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}

		if($endday == $day)
		{
			$selected = " selected=\"selected\"";
			$enddateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
		else
		{
			$selected = '';
			$enddateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
	}

	$startmonthsel = $endmonthsel = array();
	foreach(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12') as $month)
	{
		$startmonthsel[$month] = '';
		$endmonthsel[$month] = '';
	}
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth = $enddatemonth = '';


    eval("\$startdatemonth .= \"".$templates->get("modcp_announcements_month_start")."\";");
	eval("\$enddatemonth .= \"".$templates->get("modcp_announcements_month_end")."\";");
	
	
	

	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array('yes' => '', 'no' => '');

	


	$end_type_sel = array('infinite' => '', 'finite' => '');
	
	if(($errored && $mybb->get_input('endtime_type', MyBB::INPUT_INT) == 2) || (!$errored && (int)$announcement['enddate'] == 0) || $makeshift_end == true)
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	
	
	
	// Подключите функцию insert_bbcode_editor
    require_once INC_PATH . '/editor.php';
	require_once 'cache/smilies.php';


    // Вызов функции
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'message');
	
	
	
		$codebuttons = '
<br />

  
    ' . $editor['toolbar'] . '

    <div class="mb-3">
      <label for="message" class="form-label">Your Comment <small class="text-muted">(max 500 characters)</small></label>
      <textarea class="form-control" id="message" name="message" rows="11" placeholder="Write your comment using BBCode..." maxlength="500" aria-describedby="charCount" required>'.$message.'</textarea>
      <div id="charCount" class="form-text text-end">0 / 500</div>
    </div>
	' . $editor['modal'] . '
	';
	
	
	
	
	
	
	
	
	
	
	
	

	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => $announcement['aid'],
			'fid' => $announcement['fid'],
			'uid' => $CURUSER['id'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'dateline' => TIMENOW,
			'userusername' => $CURUSER['username'],
		);

		$array = $CURUSER;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		
		
		require_once INC_PATH.'/functions_post.php';
		
		$postbit = build_postbit($announcementarray, 3);
		$preview = $postbit;
	}
	else
	{
		$preview = '';
	}

	$plugins->run_hooks("modcp_edit_announcement");

    stdhead($lang->modcp['edit_announcement']);
	
	build_breadcrumb();
    
	eval("\$announcements = \"".$templates->get("modcp_announcements_edit")."\";");
	
	
	
	
	echo $announcements;
}




if($mybb->input['action'] == "do_delete_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
		//error_no_permission();
	//}

	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("tsf_announcements", "aid, subject, fid", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	if(!$announcement)
	{
		stderr('The specified announcement is invalid');
	}
	//if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	//{
		//error_no_permission();
	//}

	$plugins->run_hooks("modcp_do_delete_announcement");

	$db->delete_query("tsf_announcements", "aid='{$aid}'");
	log_moderator_action(array("aid" => $announcement['aid'], "subject" => $announcement['subject']), 'Announcement Deleted');
	//$cache->update_forumsdisplay();

	redirect("modcp.php?action=announcements",'The announcement has been deleted');
}



if($mybb->input['action'] == "delete_announcement")
{
	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
		//error_no_permission();
	//}

	$aid = intval($mybb->input['aid']);
	$query = $db->simple_select("tsf_announcements", "aid, subject, fid", "aid='{$aid}'");

	$announcement = $db->fetch_array($query);
	$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

	if(!$announcement)
	{
		stderr('The specified announcement is invalid');
	}

	//if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'], "canmanageannouncements")) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	//{
		//error_no_permission();
	//}

	$plugins->run_hooks("modcp_delete_announcement");

    stdhead($lang->modcp['delete_announcement']);
	
	
	eval("\$announcements = \"".$templates->get("modcp_announcements_delete")."\";");
	
	
	
	echo $announcements;
}


if($mybb->input['action'] == "do_new_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
	//	error_no_permission();
	//}

	$announcement_fid = intval($mybb->input['fid']);
	//if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid, "canmanageannouncements")) || ($unviewableforums && in_array($announcement_fid, $unviewableforums)))
	//{
		//error_no_permission();
	//}

	$errors = array();

	//$mybb->input['title'] = $mybb->get_input('title');
	if(!trim($mybb->input['title']))
	{
		$errors[] = 'error_missing_title';
	}

	//$mybb->input['message'] = $mybb->get_input('message');
	if(!trim($mybb->input['message']))
	{
		$errors[] = 'error_missing_message';
	}

	if(!$announcement_fid)
	{
		$errors[] = 'error_missing_forum';
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$localized_time_offset = (float)$CURUSER['timezone']*3600 + $CURUSER['dst']*3600;

	$startdate = gmmktime((int)$startdate[0], (int)$startdate[1], 0, $mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) - $localized_time_offset;
	if(!checkdate($mybb->get_input('starttime_month', MyBB::INPUT_INT), $mybb->get_input('starttime_day', MyBB::INPUT_INT), $mybb->get_input('starttime_year', MyBB::INPUT_INT)) || $startdate < 0 || $startdate == false)
	{
		$errors[] = 'error_invalid_start_date';
	}

	if($mybb->get_input('endtime_type', MyBB::INPUT_INT) == 2)
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime((int)$enddate[0], (int)$enddate[1], 0, $mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) - $localized_time_offset;
		if(!checkdate($mybb->get_input('endtime_month', MyBB::INPUT_INT), $mybb->get_input('endtime_day', MyBB::INPUT_INT), $mybb->get_input('endtime_year', MyBB::INPUT_INT)) || $enddate < 0 || $enddate == false)
		{
			$errors[] = 'error_invalid_end_date';
		}

		if($enddate <= $startdate)
		{
			$errors[] = 'error_end_before_start';
		}
	}

	

	$plugins->run_hooks("modcp_do_new_announcement_start");

	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'new_announcement';
		}
		else
		{
			$insert_announcement = array(
				'fid' => $announcement_fid,
				'uid' => $CURUSER['id'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate
			);
			$aid = $db->insert_query("tsf_announcements", $insert_announcement);

			log_moderator_action(array("aid" => $aid, "subject" => $mybb->input['title']), 'Announcement Added');

			$plugins->run_hooks("modcp_do_new_announcement_end");

			$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", 'redirect_add_announcement');
		}
	}
	else
	{
		$mybb->input['action'] = 'new_announcement';
	}
}


 
if($mybb->input['action'] == "new_announcement")
{
	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
		//error_no_permission();
	//}

	add_breadcrumb($lang->modcp['mcp_nav_announcements'], "modcp.php?action=announcements");
	add_breadcrumb($lang->modcp['add_announcement'], "modcp.php?action=new_announcements");

	$announcement_fid = $mybb->get_input('fid', MyBB::INPUT_INT);

	//if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid, "canmanageannouncements")) || ($unviewableforums && in_array($announcement_fid, $unviewableforums)))
	//{
		//error_no_permission();
	//}

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		

		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = $mybb->get_input('starttime_day', MyBB::INPUT_INT);
		$starttime_time = htmlspecialchars_uni($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = $mybb->get_input('endtime_day', MyBB::INPUT_INT);
		$endtime_time = htmlspecialchars_uni($mybb->input['endtime_time']);
	}
	else
	{
		$localized_time = TIMENOW + (float)$CURUSER['timezone']*3600 + $CURUSER['dst']*3600;

		$starttime_time = gmdate($timeformat, $localized_time);
		$endtime_time = gmdate($timeformat, $localized_time);
		$startday = $endday = gmdate("j", $localized_time);
		$startmonth = $endmonth = gmdate("m", $localized_time);
		$startdateyear = gmdate("Y", $localized_time);

		$announcement = array(
			'subject' => '',
			'message' => ''
			);

		$enddateyear = $startdateyear+1;
	}

	// Generate form elements
	$startdateday = $enddateday = '';
	for($day = 1; $day <= 31; ++$day)
	{
		if($startday == $day)
		{
			$selected = " selected=\"selected\"";
			$startdateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
		else
		{
			$selected = '';
			$startdateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}

		if($endday == $day)
		{
			$selected = " selected=\"selected\"";
			$enddateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
		else
		{
			$selected = '';
			$enddateday .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
		}
	}

	$startmonthsel = $endmonthsel = array();
	foreach(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12') as $month)
	{
		$startmonthsel[$month] = '';
		$endmonthsel[$month] = '';
	}
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth = $enddatemonth = '';

    eval("\$startdatemonth .= \"".$templates->get("modcp_announcements_month_start")."\";");
	
    eval("\$enddatemonth .= \"".$templates->get("modcp_announcements_month_end")."\";");


	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array('yes' => '', 'no' => '');

	


	$end_type_sel = array('infinite' => '', 'finite' => '');
	if(!isset($mybb->input['endtime_type']) || $mybb->input['endtime_type'] == 2)
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	
	
	
	
	
    require_once INC_PATH . '/editor.php';
	require_once 'cache/smilies.php';


   
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'message');
	
	
	
	$codebuttons = '
<br />

' . $editor['toolbar'] . '

    <div class="mb-3">
      <label for="message" class="form-label">Your Comment <small class="text-muted">(max 500 characters)</small></label>
      <textarea class="form-control" id="message" name="message" rows="11" placeholder="Write your comment using BBCode..." maxlength="500" aria-describedby="charCount" required>'.$message.'</textarea>
      <div id="charCount" class="form-text text-end">0 / 500</div>
    </div>
	
	 ' . $editor['modal'] . '
	';
	
	

	
	

	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => 0,
			'fid' => $announcement_fid,
			'uid' => $CURUSER['id'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'dateline' => TIMENOW,
			'userusername' => $mybb->user['username'],
		);

		$array = $CURUSER;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		//require_once MYBB_ROOT."inc/functions_post.php";
		require_once INC_PATH . '/functions_post.php';
		$postbit = build_postbit($announcementarray, 3);
		$preview = $postbit;
	}
	else
	{
		$preview = '';
	}

	$plugins->run_hooks("modcp_new_announcement");

    eval("\$announcements = \"".$templates->get("modcp_announcements_new")."\";");
	
    stdhead($lang->modcp['add_announcement']);
	
	build_breadcrumb();
	
	
	echo $announcements;
}




if($mybb->input['action'] == "announcements")
{
	//if($mybb->usergroup['canmanageannounce'] == 0)
	//{
		//error_no_permission();
	//}

	//if($numannouncements == 0 && $mybb->usergroup['issupermod'] != 1)
	//{
		//error($lang->you_cannot_manage_announcements);
	//}

	add_breadcrumb($lang->modcp['mcp_nav_announcements'], "modcp.php?action=announcements");

	// Fetch announcements into their proper arrays
	$query = $db->simple_select("tsf_announcements", "aid, fid, subject, enddate");
	$announcements = $global_announcements = array();
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['fid'] == -1)
		{
			$global_announcements[$announcement['aid']] = $announcement;
			continue;
		}
		$announcements[$announcement['fid']][$announcement['aid']] = $announcement;
	}

	$announcements_global = '';
	//if($mybb->usergroup['issupermod'] == 1)
	//{
		if($global_announcements)
		{
			// Get the global announcements
			foreach($global_announcements as $aid => $announcement)
			{
				//$trow = alt_trow();
				if((isset($announcement['startdate']) && $announcement['startdate'] > TIMENOW) || (isset($announcement['enddate']) && $announcement['enddate'] < TIMENOW && $announcement['enddate'] != 0))
				{
					$icon = '<div class="subforumicon subforum_minioff" title="{$lang->expired_announcement}"></div>';
				}
				else
				{
				    $icon = '<div class="subforumicon subforum_minion" title="{$lang->active_announcement}"></div>';
				}

				$subject = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

				eval("\$announcements_global .= \"".$templates->get("modcp_announcements_announcement_global")."\";");
				
				
				
			}
		}
		else
		{
			// No global announcements
			eval("\$announcements_global = \"".$templates->get("modcp_no_announcements_global")."\";");
		}
		eval("\$announcements_global = \"".$templates->get("modcp_announcements_global")."\";");	
				
				
				
				
				
				
	//}

	$announcements_forum = '';
	fetch_forum_announcements();

	if(!$announcements_forum)
	{
		$announcements_forum = '<div class="py-2 border-top">There are currently no forum announcements on your board</div>';
	}

	$plugins->run_hooks("modcp_announcements");

	
	stdhead($lang->modcp['manage_announcement']);
	
	build_breadcrumb();
	
	eval("\$announcements = \"".$templates->get("modcp_announcements")."\";");
	
	
	
	
	echo $announcements;
}






if($mybb->input['action'] == "do_modqueue")
{
	require_once INC_PATH."/class_moderation.php";
	$moderation = new Moderation;

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	//if($mybb->usergroup['canmanagemodqueue'] == 0)
	//{
		//error_no_permission();
	//}

	$plugins->run_hooks("modcp_do_modqueue_start");

	$mybb->input['threads'] = $mybb->get_input('threads', MyBB::INPUT_ARRAY);
	$mybb->input['posts'] = $mybb->get_input('posts', MyBB::INPUT_ARRAY);
	$mybb->input['attachments'] = $mybb->get_input('attachments', MyBB::INPUT_ARRAY);
	if(!empty($mybb->input['threads']))
	{
		$threads = array_map("intval", array_keys($mybb->input['threads']));
		$threads_to_approve = $threads_to_delete = array();
		// Fetch threads
		$query = $db->simple_select("tsf_threads", "tid", "tid IN (".implode(",", $threads)."){$flist_queue_threads}");
		while($thread = $db->fetch_array($query))
		{
			if(!isset($mybb->input['threads'][$thread['tid']]))
			{
				continue;
			}
			$action = $mybb->input['threads'][$thread['tid']];
			if($action == "approve")
			{
				$threads_to_approve[] = $thread['tid'];
			}
			else if($action == "delete")
			{
				$threads_to_delete[] = $thread['tid'];
			}
		}
		if(!empty($threads_to_approve))
		{
			$moderation->approve_threads($threads_to_approve);
			log_moderator_action(array('tids' => $threads_to_approve), $lang->modcp['multi_approve_threads']);
		}
		if(!empty($threads_to_delete))
		{
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->soft_delete_threads($threads_to_delete);
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_soft_delete_threads);
			}
			else
			{
				foreach($threads_to_delete as $tid)
				{
					$moderation->delete_thread($tid);
				}
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_delete_threads);
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue", $lang->modcp['redirect_threadsmoderated']);
	}
	else if(!empty($mybb->input['posts']))
	{
		$posts = array_map("intval", array_keys($mybb->input['posts']));
		// Fetch posts
		$posts_to_approve = $posts_to_delete = array();
		$query = $db->simple_select("tsf_posts", "pid", "pid IN (".implode(",", $posts)."){$flist_queue_posts}");
		while($post = $db->fetch_array($query))
		{
			if(!isset($mybb->input['posts'][$post['pid']]))
			{
				continue;
			}
			$action = $mybb->input['posts'][$post['pid']];
			if($action == "approve")
			{
				$posts_to_approve[] = $post['pid'];
			}
			else if($action == "delete" && $mybb->settings['soft_delete'] != 1)
			{
				$moderation->delete_post($post['pid']);
			}
			else if($action == "delete")
			{
				$posts_to_delete[] = $post['pid'];
			}
		}
		if(!empty($posts_to_approve))
		{
			$moderation->approve_posts($posts_to_approve);
			log_moderator_action(array('pids' => $posts_to_approve), $lang->modcp['multi_approve_posts']);
		}
		if(!empty($posts_to_delete))
		{
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->soft_delete_posts($posts_to_delete);
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_soft_delete_posts);
			}
			else
			{
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_delete_posts);
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue&type=posts", $lang->modcp['redirect_postsmoderated']);
	}
	else if(!empty($mybb->input['attachments']))
	{
		$attachments = array_map("intval", array_keys($mybb->input['attachments']));
		$query = $db->sql_query("
			SELECT a.pid, a.aid, t.tid
			FROM  attachments a
			LEFT JOIN tsf_posts p ON (a.pid=p.pid)
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			WHERE aid IN (".implode(",", $attachments)."){$tflist_queue_attach}
		");
		while($attachment = $db->fetch_array($query))
		{
			if(!isset($mybb->input['attachments'][$attachment['aid']]))
			{
				continue;
			}
			$action = $mybb->input['attachments'][$attachment['aid']];
			if($action == "approve")
			{
				$db->update_query("attachments", array("visible" => 1), "aid='{$attachment['aid']}'");
				if(isset($attachment['tid']))
            	{
					update_thread_counters((int)$attachment['tid'], array("attachmentcount" => "+1"));
				}
			}
			else if($action == "delete")
			{
				remove_attachment($attachment['pid'], '', $attachment['aid']);
				if(isset($attachment['tid']))
            	{
					update_thread_counters((int)$attachment['tid'], array("attachmentcount" => "-1"));
				}
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue&type=attachments", $lang->modcp['redirect_attachmentsmoderated']);
	}
}





if($mybb->input['action'] == "modqueue")
{
	$navsep = '';

	//if($mybb->usergroup['canmanagemodqueue'] == 0)
	//{
		//error_no_permission();
	//}

	//if($nummodqueuethreads == 0 && $nummodqueueposts == 0 && $nummodqueueattach == 0 && $mybb->usergroup['issupermod'] != 1)
	//{
		//error($lang->you_cannot_use_mod_queue);
	//}

	$mybb->input['type'] = $mybb->get_input('type');
	$threadqueue = $postqueue = $attachmentqueue = '';
	if($mybb->input['type'] == "threads" || !$mybb->input['type'] && ($nummodqueuethreads > 0 || $usergroups['issupermod'] == "1"))
	{
		//if($nummodqueuethreads == 0 && $usergroups['issupermod'] == "yes")
		//{
			//error($lang->you_cannot_moderate_threads);
		//}

		$forum_cache = $cache->read("forums");

		$query = $db->simple_select("tsf_threads", "COUNT(tid) AS unapprovedthreads", "visible='0' {$flist_queue_threads}");
		$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $f_threadsperpage;
		$pages = $unapproved_threads / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_threads, $perpage, $page, "modcp.php?action=modqueue&type=threads");

		$query = $db->sql_query("
			SELECT t.tid, t.dateline, t.fid, t.subject, t.username AS threadusername, p.message AS postmessage, u.username AS username, t.uid
			FROM tsf_threads t
			LEFT JOIN tsf_posts p ON (p.pid=t.firstpost)
			LEFT JOIN users u ON (u.id=t.uid)
			WHERE t.visible='0' {$tflist_queue_threads}
			ORDER BY t.lastpost DESC
			LIMIT {$start}, {$perpage}
		");
		$threads = '';
		while($thread = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$forum_link = get_forum_link($thread['fid']);
			$forum_name = $forum_cache[$thread['fid']]['name'];
			$threaddate = my_datee('relative', $thread['dateline']);

			if($thread['username'] == "")
			{
				if($thread['threadusername'] != "")
				{
					$thread['threadusername'] = htmlspecialchars_uni($thread['threadusername']);
					$profile_link = $thread['threadusername'];
				}
				else
				{
					$profile_link = 'guest';
				}
			}
			else
			{
				$thread['username'] = htmlspecialchars_uni($thread['username']);
				$profile_link = build_profile_link($thread['username'], $thread['uid']);
			}

			$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
			eval("\$forum = \"".$templates->get("modcp_modqueue_link_forum")."\";");
			eval("\$threads .= \"".$templates->get("modcp_modqueue_threads_thread")."\";");
		}

		if(!$threads && $mybb->input['type'] == "threads")
		{
			eval("\$threads = \"".$templates->get("modcp_modqueue_threads_empty")."\";");
		}

		if($threads)
		{
			
			
			add_breadcrumb($lang->modcp['mcp_nav_modqueue_threads'], "modcp.php?action=modqueue&amp;type=threads");

			$plugins->run_hooks("modcp_modqueue_threads_end");

			//if($nummodqueueposts > 0 || $usergroups['issupermod'] == "yes")
			//{
				$navsep = " | ";
				eval("\$post_link = \"".$templates->get("modcp_modqueue_post_link")."\";");
			//}

			//if($enableattachments == 1 && ($nummodqueueattach > 0 || $usergroups['issupermod'] == "yes"))
			//{
				$navsep = " | ";
				eval("\$attachment_link = \"".$templates->get("modcp_modqueue_attachment_link")."\";");
			//}

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$threadqueue = \"".$templates->get("modcp_modqueue_threads")."\";");
			
			stdhead();
			
			build_breadcrumb();
			
			echo $threadqueue;
			
		}
		$type = 'threads';
	}

	if($mybb->input['type'] == "posts" || (!$mybb->input['type'] && !$threadqueue && ($nummodqueueposts > 0 || $usergroups['issupermod'] == "1")))	
	{
		if($nummodqueueposts == 0 && $usergroups['issupermod'] != "1")
		{
			stderr('you_cannot_moderate_posts');
		}

		$forum_cache = $cache->read("forums");

		$query = $db->sql_query("
			SELECT COUNT(pid) AS unapprovedposts
			FROM  tsf_posts p
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
		");
		$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $f_postsperpage;
		$pages = $unapproved_posts / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_posts, $perpage, $page, "modcp.php?action=modqueue&amp;type=posts");

		$query = $db->sql_query("
			SELECT p.pid, p.subject, p.message, p.username AS postusername, t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
			FROM  tsf_posts p
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			LEFT JOIN users u ON (u.id=p.uid)
			WHERE p.visible='0' {$tflist_queue_posts} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC, p.pid DESC
			LIMIT {$start}, {$perpage}
		");
		$posts = '';
		while($post = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$post['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($post['threadsubject']));
			$post['subject'] = htmlspecialchars_uni($parser->parse_badwords($post['subject']));
			$post['threadlink'] = get_thread_link($post['tid']);
			$post['postlink'] = get_post_link($post['pid'], $post['tid']);
			$forum_link = get_forum_link($post['fid']);
			$forum_name = $forum_cache[$post['fid']]['name'];
			$postdate = my_datee('relative', $post['dateline']);

			if($post['username'] == "")
			{
				if($post['postusername'] != "")
				{
					$post['postusername'] = htmlspecialchars_uni($post['postusername']);
					$profile_link = $post['postusername'];
				}
				else
				{
					$profile_link = $lang->guest;
				}
			}
			else
			{
				$post['username'] = htmlspecialchars_uni($post['username']);
				$profile_link = build_profile_link($post['username'], $post['uid']);
			}

			eval("\$thread = \"".$templates->get("modcp_modqueue_link_thread")."\";");
			eval("\$forum = \"".$templates->get("modcp_modqueue_link_forum")."\";");
			$post['message'] = nl2br(htmlspecialchars_uni($post['message']));
			eval("\$posts .= \"".$templates->get("modcp_modqueue_posts_post")."\";");
		}

		if(!$posts && $mybb->input['type'] == "posts")
		{
			eval("\$posts = \"".$templates->get("modcp_modqueue_posts_empty")."\";");
		}

		if($posts)
		{
			
			
			add_breadcrumb($lang->modcp['mcp_nav_modqueue_posts'], "modcp.php?action=modqueue&amp;type=posts");

			$plugins->run_hooks("modcp_modqueue_posts_end");

			//if($nummodqueuethreads > 0 || $usergroups['issupermod'] == "yes")
			//{
				$navsep = " | ";
				eval("\$thread_link = \"".$templates->get("modcp_modqueue_thread_link")."\";");
			//}

			//if($enableattachments == 1 && ($nummodqueueattach > 0 || $usergroups['issupermod'] == "yes"))
			//{
				$navsep = " | ";
				eval("\$attachment_link = \"".$templates->get("modcp_modqueue_attachment_link")."\";");
			//}

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$postqueue = \"".$templates->get("modcp_modqueue_posts")."\";");
			
			stdhead();
			
			echo $postqueue;
			
			
		}
	}

    
	if($mybb->input['type'] == "attachments" || (!$mybb->input['type'] && !$postqueue && !$threadqueue && $enableattachments == 1 && ($nummodqueueattach > 0 || $usergroups['issupermod'] == "1")))
	{
		if($enableattachments == 0)
		{
			stderr('attachments_disabled');
		}

		if($nummodqueueattach == 0 && $usergroups['issupermod'] != "1")
		{
			stderr('you_cannot_moderate_attachments');
		}

		$query = $db->sql_query("
			SELECT COUNT(aid) AS unapprovedattachments
			FROM attachments a
			LEFT JOIN tsf_posts p ON (p.pid=a.pid)
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			WHERE a.visible='0'{$tflist_queue_attach}
		");
		$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$perpage = $f_postsperpage;
		$pages = $unapproved_attachments / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_attachments, $perpage, $page, "modcp.php?action=modqueue&amp;type=attachments");

		$query = $db->sql_query("
			SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
			FROM attachments a
			LEFT JOIN tsf_posts p ON (p.pid=a.pid)
			LEFT JOIN tsf_threads t ON (t.tid=p.tid)
			LEFT JOIN users u ON (u.id=p.uid)
			WHERE a.visible='0'{$tflist_queue_attach}
			ORDER BY a.dateuploaded DESC
			LIMIT {$start}, {$perpage}
		");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$altbg = alt_trow();

			if(!$attachment['dateuploaded'])
			{
				$attachment['dateuploaded'] = $attachment['dateline'];
			}

			$attachdate = my_datee('relative', $attachment['dateuploaded']);

			$attachment['postsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['postsubject']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			$attachment['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['threadsubject']));
			$attachment['filesize'] = mksize($attachment['filesize']);

			$link = get_post_link($attachment['pid'], $attachment['tid']) . "#pid{$attachment['pid']}";
			$thread_link = get_thread_link($attachment['tid']);
			$attachment['username'] = htmlspecialchars_uni($attachment['username']);
			$profile_link = build_profile_link($attachment['username'], $attachment['uid']);

			eval("\$attachments .= \"".$templates->get("modcp_modqueue_attachments_attachment")."\";");
		}

		if(!$attachments && $mybb->input['type'] == "attachments")
		{
			eval("\$attachments = \"".$templates->get("modcp_modqueue_attachments_empty")."\";");
		}

		if($attachments)
		{
			add_breadcrumb($lang->modcp['mcp_nav_modqueue_attachments'], "modcp.php?action=modqueue&amp;type=attachments");

			$plugins->run_hooks("modcp_modqueue_attachments_end");

			//if($nummodqueuethreads > 0 || $mybb->usergroup['issupermod'] == 1)
			//{
				eval("\$thread_link = \"".$templates->get("modcp_modqueue_thread_link")."\";");
				$navsep = " | ";
			//}

			//if($nummodqueueposts > 0 || $usergroups['issupermod'] == "yes")
			//{
				eval("\$post_link = \"".$templates->get("modcp_modqueue_post_link")."\";");
				$navsep = " | ";
			//}

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$attachmentqueue = \"".$templates->get("modcp_modqueue_attachments")."\";");
			
			stdhead();
			
			echo $attachmentqueue;
		}
	}

	// Still nothing? All queues are empty! :-D
	if(!$threadqueue && !$postqueue && !$attachmentqueue)
	{
		add_breadcrumb($lang->modcp['mcp_nav_modqueue'], "modcp.php?action=modqueue");

		$plugins->run_hooks("modcp_modqueue_end");

		eval("\$queue = \"".$templates->get("modcp_modqueue_empty")."\";");
		
		
		stdhead();
		
		echo $queue;
	}
}





if($mybb->input['action'] == "finduser")
{
	

	add_breadcrumb('mcp_nav_users', "modcp.php?action=finduser");
	
	if ($usergroups['canuserdetails'] != '1')
    {
      print_no_permission (true);
    }
	
	

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $f_threadsperpage;
	}
	$where = '';

	if(isset($mybb->input['username']))
	{
		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$field = 'username';
				break;
			default:
				$field = 'LOWER(username)';
				break;
		}
		$where = " AND {$field} LIKE '%".my_strtolower($db->escape_string_like($mybb->get_input('username')))."%'";
	}

	// Sort order & direction
	switch($mybb->get_input('sortby'))
	{
		case "last_access":
			$sortby = "last_access";
			break;
		case "postnum":
			$sortby = "postnum";
			break;
		case "username":
			$sortby = "username";
			break;
		default:
			$sortby = "added";
	}
	$sortbysel = array('last_access' => '', 'postnum' => '', 'username' => '', 'added' => '');
	//$sortbysel[$mybb->get_input('sortby')] = " selected=\"selected\"";
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	
	$order = $mybb->input['order'];
	if($order != "asc")
	{
		$order = "desc";
	}
	$ordersel = array('asc' => '', 'desc' => '');
	$ordersel[$order] = " selected=\"selected\"";

	$query = $db->simple_select("users", "COUNT(id) AS count", "1=1 {$where}");
	$user_count = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->input['page'] != "last")
	{
		$page = intval($mybb->input['page']);
	}

	$pages = $user_count / $perpage;
	$pages = ceil($pages);

	if($mybb->input['page'] == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$page_url = 'modcp.php?action=finduser';
	foreach(array('username', 'sortby', 'order') as $field)
	{
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	$multipage = multipage($user_count, $perpage, $page, $page_url);

	$usergroups_cache = $cache->read("usergroups");

	$plugins->run_hooks("modcp_finduser_start");

	// Fetch out results
	$query = $db->simple_select("users", "*", "1=1 {$where}", array("order_by" => $sortby, "order_dir" => $order, "limit" => $perpage, "limit_start" => $start));
	$users = '';
	while($user = $db->fetch_array($query))
	{
		$alt_row = alt_trow();
		$user['username'] = htmlspecialchars_uni($user['username']);
		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['postnum'] = ts_nf($user['postnum']);
		$regdate = my_datee('relative', $user['added']);

		//if($user['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1 && $user['uid'] != $mybb->user['uid'])
		
	    
		//$is_mod = is_mod($usergroups);
		
		if($user['invisible'] == 1 && $user['id'] != $CURUSER['id'])
			
		{
			//$lastdate = 'last_access_never';
			$lastdate = $lang->modcp['lastvisit_never'];

			if($user['lastvisit'])
			{
				// We have had at least some active time, hide it instead
				//$lastdate = 'last_access_hidden';
				$lastdate = $lang->modcp['lastvisit_hidden'];
				
			}
		}
		else
		{
			$lastdate = my_datee('relative', $user['lastvisit']);
		}

		$usergroup = htmlspecialchars_uni($usergroups_cache[$user['usergroup']]['title']);
		
		
		eval("\$users .= \"".$templates->get("modcp_finduser_user")."\";");
	}

	// No results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("modcp_finduser_noresults")."\";");
	}

	$plugins->run_hooks("modcp_finduser_end");

	$username = htmlspecialchars_uni($mybb->get_input('username'));
	
	
	eval("\$finduser = \"".$templates->get("modcp_finduser")."\";");

	stdhead($lang->modcp['find_users']);
	
	
	echo $finduser;
}






if($mybb->input['action'] == "modlogs")
{
	//if($mybb->usergroup['canviewmodlogs'] == 0)
	//{
		//error_no_permission();
	//}

	//if($nummodlogs == 0 && $mybb->usergroup['issupermod'] != 1)
	//{
		//error($lang->you_cannot_view_mod_logs);
	//}

	add_breadcrumb($lang->modcp['mcp_nav_modlogs'], "modcp.php?action=modlogs");

	//$perpage = intval($mybb->input['perpage']);
	
	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $f_threadsperpage;
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->get_input('uid', MyBB::INPUT_INT))
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries in a specific forum
	if($mybb->get_input('fid', MyBB::INPUT_INT))
	{
		$where .= " AND t.fid='".$mybb->get_input('fid', MyBB::INPUT_INT)."'";
	}

	$mybb->input['sortby'] = $mybb->get_input('sortby');

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "forum":
			$sortby = "f.name";
			break;
		case "thread":
			$sortby = "t.subject";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$plugins->run_hooks("modcp_modlogs_start");

	$query = $db->sql_query("
		SELECT COUNT(l.dateline) AS count
		FROM moderatorlog l
		LEFT JOIN users u ON (u.id=l.uid)
		LEFT JOIN tsf_threads t ON (t.tid=l.tid)
		WHERE 1=1 {$where}{$tflist_modlog}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$page_url = 'modcp.php?action=modlogs&amp;perpage='.$perpage;
	foreach(array('uid', 'fid') as $field)
	{
		$mybb->input[$field] = $mybb->get_input($field, MyBB::INPUT_INT);
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}
	foreach(array('sortby', 'order') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	$multipage = multipage($postcount, $perpage, $page, $page_url);
	$resultspages = '';
	if($postcount > $perpage)
	{
		$resultspages = '<div class="mb-4">'.$multipage.'</div>';
	}
	$query = $db->sql_query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM moderatorlog l
		LEFT JOIN users u ON (u.id=l.uid)
		LEFT JOIN tsf_threads t ON (t.tid=l.tid)
		LEFT JOIN tsf_forums f ON (f.fid=l.fid)
		LEFT JOIN tsf_posts p ON (p.pid=l.pid)
		WHERE 1=1 {$where}{$tflist_modlog}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	$results = '';
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$log_date = my_datee('relative', $logitem['dateline']);
		$trow = alt_trow();
		if($logitem['username'])
		{
			$logitem['username'] = htmlspecialchars_uni($logitem['username']);
			$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
			$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		}
		else
		{
			$username = $logitem['profilelink'] = $logitem['username'] = htmlspecialchars_uni($lang->na_deleted);
		}
		
		
		
		$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));
	

		if($logitem['tsubject'])
		{
			$logitem['tsubject'] = htmlspecialchars_uni($parser->parse_badwords($logitem['tsubject']));
			$logitem['thread'] = get_thread_link($logitem['tid']);
		    
			
			eval("\$information .= \"".$templates->get("modcp_modlogs_result_thread")."\";");
			
		}
		if($logitem['fname'])
		{
			$logitem['forum'] = get_forum_link($logitem['fid']);
			
			eval("\$information .= \"".$templates->get("modcp_modlogs_result_forum")."\";");
			
			
		}
		if($logitem['psubject'])
		{
			$logitem['psubject'] = htmlspecialchars_uni($parser->parse_badwords($logitem['psubject']));
			$logitem['post'] = get_post_link($logitem['pid']);
			
			
			eval("\$information .= \"".$templates->get("modcp_modlogs_result_post")."\";");
		}

		// Edited a user or managed announcement?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$data = my_unserialize($logitem['data']);
			if(!empty($data['uid']))
			{
				$data['username'] = htmlspecialchars_uni($data['username']);
				//$information = sprintf('edited_user_info', htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
				//$information = sprintf('<strong>User:</strong> <a href="'.get_profile_link($data['uid']).'">'.htmlspecialchars_uni($data['username']).'</a>');
				$information = sprintf($lang->modcp['edited_user_info'], htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
				
				
			}
			if(!empty($data['aid']))
			{
				$data['subject'] = htmlspecialchars_uni($parser->parse_badwords($data['subject']));
				$data['announcement'] = get_announcement_link($data['aid']);
				
				eval("\$information .= \"".$templates->get("modcp_modlogs_result_announcement")."\";");
			}
		}

		$plugins->run_hooks("modcp_modlogs_result");

		eval("\$results .= \"".$templates->get("modcp_modlogs_result")."\";");
	}

	if(!$results)
	{
		eval("\$results = \"".$templates->get("modcp_modlogs_noresults")."\";");
	}

	$plugins->run_hooks("modcp_modlogs_filter");

	// Fetch filter options
	$sortbysel = array('username' => '', 'forum' => '', 'thread' => '', 'dateline' => '');
	
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	
	
	$ordersel = array('asc' => '', 'desc' => '');
	$ordersel[$order] = "selected=\"selected\"";
	$user_options = '';
	$query = $db->sql_query("
		SELECT DISTINCT l.uid, u.username
		FROM moderatorlog l
		LEFT JOIN users u ON (l.uid=u.id)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = 'na_deleted';
		}

		$selected = '';
		if($mybb->get_input('uid', MyBB::INPUT_INT) == $user['uid'])
		{
			$selected = " selected=\"selected\"";
		}

		$user['username'] = htmlspecialchars_uni($user['username']);
		
		$user_options .= '<option value="'.$user['uid'].'"'.$selected.'>'.$user['username'].'</option>';
	}

	$forum_select = build_forum_jump("", $mybb->input['fid'], 1, '', 0, true, '', "fid");

	
	eval("\$modlogs = \"".$templates->get("modcp_modlogs")."\";");
	
	stdhead($lang->modcp['modlogs']);
	
	build_breadcrumb();
	
	echo $modlogs;
}








if($mybb->input['action'] == "do_modnotes")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("modcp_do_modnotes_start");

	// Update Moderator Notes cache
	$update_cache = array(
		"modmessage" => $mybb->get_input('modnotes')
	);
	$cache->update("modnotes", $update_cache);

	$plugins->run_hooks("modcp_do_modnotes_end");

	redirect("modcp.php", $lang->modcp['redirect_modnotes']);
}





if(!$mybb->input['action'])
{
	
	
	
	
	
$latestfivemodactions = '';
	//if(($nummodlogs > 0 || $mybb->usergroup['issupermod'] == 1) && $mybb->usergroup['canviewmodlogs'] == 1)
	//{
		$where = '';
		if($tflist_modlog)
		{
			$where = "WHERE (t.fid <> 0 {$tflist_modlog}) OR (l.fid <> 0)";
		}

		$query = $db->sql_query("
			SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
			FROM moderatorlog l
			LEFT JOIN users u ON (u.id=l.uid)
			LEFT JOIN tsf_threads t ON (t.tid=l.tid)
			LEFT JOIN tsf_forums f ON (f.fid=l.fid)
			LEFT JOIN tsf_posts p ON (p.pid=l.pid)
			{$where}
			ORDER BY l.dateline DESC
			LIMIT 5
		");

		$modlogresults = '';
		while($logitem = $db->fetch_array($query))
		{
			$information = '';
			$logitem['action'] = htmlspecialchars_uni($logitem['action']);
			$log_date = my_datee('relative', $logitem['dateline']);
			$trow = alt_trow();
			$logitem['username'] = htmlspecialchars_uni($logitem['username']);
			$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
			$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
			$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));
			
			

			if($logitem['tsubject'])
			{
				$logitem['tsubject'] = htmlspecialchars_uni($parser->parse_badwords($logitem['tsubject']));
				$logitem['thread'] = get_thread_link($logitem['tid']);
				
				//$information .= '<strong>'.$lang->modcp['thread'].':</strong> <a href="'.$logitem['thread'].'" target="_blank">'.$logitem['tsubject'].'</a><br />';
				eval("\$information .= \"".$templates->get("modcp_modlogs_result_thread")."\";");
			}
			if($logitem['fname'])
			{
				$logitem['forum'] = get_forum_link($logitem['fid']);
				
				//$information .= '<strong>'.$lang->modcp['forum'].':</strong> <a href="'.$logitem['forum'].'" target="_blank">'.$logitem['fname'].'</a><br />';
				eval("\$information .= \"".$templates->get("modcp_modlogs_result_forum")."\";");
			}
			if($logitem['psubject'])
			{
				$logitem['psubject'] = htmlspecialchars_uni($parser->parse_badwords($logitem['psubject']));
				$logitem['post'] = get_post_link($logitem['pid']);
				
				$information .= '<strong>{$lang->post}:</strong> <a href="'.$logitem['post'].'#pid'.$logitem['pid'].'">'.$logitem['psubject'].'</a>';
			}

			// Edited a user or managed announcement?
			if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
			{
				$data = my_unserialize($logitem['data']);
				if(isset($data['uid']))
				{
					//$information = sprintf('<strong>User:</strong> <a href=\"{2}\">{1}</a>', htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
					$information = sprintf('<strong>User:</strong> <a href="'.get_profile_link($data['uid']).'">'.htmlspecialchars_uni($data['username']).'</a>');
				}
				if(isset($data['aid']))
				{
					$data['subject'] = htmlspecialchars_uni($parser->parse_badwords($data['subject']));
					$data['announcement'] = get_announcement_link($data['aid']);
					
					$information .= '<strong>Announcement:</strong> <a href="'.$data['announcement'].'" target="_blank">'.$data['subject'].'</a>';
				}
			}

			$plugins->run_hooks("modcp_modlogs_result");

			eval("\$modlogresults .= \"".$templates->get("modcp_modlogs_result")."\";");
		}

		if(!$modlogresults)
		{
			$modlogresults = '<div class="py-2 border-top">{$lang->no_logs}</div>';
		}

		eval("\$latestfivemodactions = \"".$templates->get("modcp_latestfivemodactions")."\";");
	//}
	
$query = $db->sql_query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM banned b
		LEFT JOIN users u ON (b.uid=u.id)
		LEFT JOIN users a ON (b.admin=a.id)
		WHERE b.bantime != '---' AND b.bantime != 'perm'
		ORDER BY lifted ASC
		LIMIT 5
	");

	$banned_cache = array();
	while($banned = $db->fetch_array($query))
	{
		$banned['remaining'] = $banned['lifted']-TIMENOW;
		$banned_cache[$banned['remaining'].$banned['uid']] = $banned;

		unset($banned);
	}

	// Get the banned users
	$bannedusers = '';
	foreach($banned_cache as $banned)
	{
		$banned['username'] = htmlspecialchars_uni($banned['username']);
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($CURUSER['id'] == $banned['admin'] || !$banned['adminuser'] || $usergroups['issupermod'] == 1)
		{
			$edit_link = '<a href="modcp.php?action=banuser&amp;uid='.$banned['uid'].'">Edit Ban</a> &mdash; <a href="modcp.php?action=liftban&amp;uid='.$banned['uid'].'&amp;my_post_key='.$mybb->post_code.'">Lift Ban</a>';
		}

		$admin_profile = build_profile_link(htmlspecialchars_uni($banned['adminuser']), $banned['admin']);

		$trow = alt_trow();

		if($banned['reason'])
		{
			$banned['reason'] = htmlspecialchars_uni($parser->parse_badwords($banned['reason']));
		}
		else
		{
			$banned['reason'] = 'na';
		}

		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banlength = 'permanent';
			$timeremaining = 'na';
		}
		else
		{
			$banlength = $bantimes[$banned['bantime']];
			$remaining = $banned['remaining'];

			
			
			$timeremaining = mkprettytime($remaining);

			$banned_class = '';
			$ban_remaining = "".$timeremaining." remaining";

			if($remaining <= 0)
			{
				$banned_class = "imminent_banned";
				$ban_remaining = $lang->modcp['ban_ending_imminently'];
			}
			else if($remaining < 3600)
			{
				$banned_class = "high_banned";
			}
			else if($remaining < 86400)
			{
				$banned_class = "moderate_banned";
			}
			else if($remaining < 604800)
			{
				$banned_class = "low_banned";
			}
			else
			{
				$banned_class = "normal_banned";
			}

			eval('$timeremaining = "'.$templates->get('modcp_banning_remaining').'";');
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		eval("\$bannedusers = \"".$templates->get("modcp_nobanned")."\";");
	}


	$modnotes = '';
	$modnotes_cache = $cache->read("modnotes");
	if($modnotes_cache !== false)
	{
		$modnotes = htmlspecialchars_uni($modnotes_cache['modmessage']);
	}
	
	
	
$plugins->run_hooks("modcp_end");


	

eval("\$modcp = \"".$templates->get("modcp")."\";");

stdhead($lang->modcp['modcp']);


build_breadcrumb();


echo $modcp;

}


  stdfoot ();
?>
