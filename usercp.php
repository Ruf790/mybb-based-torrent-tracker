<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/



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



  define("IN_MYBB", 1);
  define('THIS_SCRIPT', 'usercp.php');
  define("ALLOWABLE_PAGE", "removesubscription,removesubscriptions");
  

$templatelist = "usercp,usercp_nav,usercp_profile,usercp_changename,usercp_password,usercp_subscriptions_thread,forumbit_depth2_forum_lastpost,usercp_forumsubscriptions_forum,postbit_reputation_formatted,usercp_subscriptions_thread_icon";
$templatelist .= ",usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups,usercp_nav_attachments,usercp_options_style,usercp_warnings_warning_post";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_usergroups_leader_usergroup,usercp_usergroups_leader,usercp_currentavatar,usercp_reputation,usercp_avatar_remove,usercp_resendactivation";
$templatelist .= ",usercp_attachments_attachment,usercp_attachments,usercp_profile_away,usercp_profile_customfield,usercp_profile_profilefields,usercp_profile_customtitle,usercp_forumsubscriptions_none,usercp_profile_customtitle_currentcustom";
$templatelist .= ",usercp_forumsubscriptions,usercp_subscriptions_none,usercp_subscriptions,usercp_options_pms_from_buddys,usercp_options_tppselect,usercp_options_pppselect,usercp_themeselector,usercp_profile_customtitle_reverttitle";
$templatelist .= ",usercp_nav_editsignature,usercp_referrals,usercp_notepad,usercp_latest_threads_threads,forumdisplay_thread_gotounread,usercp_latest_threads,usercp_subscriptions_remove,usercp_nav_messenger_folder,usercp_profile_profilefields_text";
$templatelist .= ",usercp_editsig_suspended,usercp_editsig,usercp_avatar_current,usercp_options_timezone_option,usercp_drafts,usercp_options_language,usercp_options_date_format,usercp_profile_website,usercp_latest_subscribed,usercp_warnings";
$templatelist .= ",usercp_avatar,usercp_editlists_userusercp_editlists,usercp_drafts_draft,usercp_usergroups_joingroup,usercp_attachments_none,usercp_avatar_upload,usercp_options_timezone,usercp_usergroups_joinable_usergroup_join";
$templatelist .= ",usercp_warnings_warning,usercp_nav_messenger_tracking,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";
$templatelist .= ",codebuttons,usercp_nav_messenger_compose,usercp_options_language_option,usercp_editlists,usercp_profile_contact_fields_field,usercp_latest_subscribed_threads,usercp_profile_contact_fields,usercp_profile_day,usercp_nav_home";
$templatelist .= ",usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,usercp_profile_profilefields_checkbox";
$templatelist .= ",usercp_options_tppselect_option,usercp_options_pppselect_option,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_lastpost_hidden,usercp_avatar_auto_resize_auto,usercp_avatar_auto_resize_user,usercp_options";
$templatelist .= ",usercp_editlists_no_buddies,usercp_editlists_no_ignored,usercp_editlists_no_requests,usercp_editlists_received_requests,usercp_editlists_sent_requests,usercp_drafts_draft_thread,usercp_drafts_draft_forum,usercp_editlists_user";
$templatelist .= ",usercp_usergroups_leader_usergroup_memberlist,usercp_usergroups_leader_usergroup_moderaterequests,usercp_usergroups_memberof_usergroup_leaveprimary,usercp_usergroups_memberof_usergroup_display,usercp_email,usercp_options_pms";
$templatelist .= ",usercp_usergroups_memberof_usergroup_leaveleader,usercp_usergroups_memberof_usergroup_leaveother,usercp_usergroups_memberof_usergroup_leave,usercp_usergroups_joinable_usergroup_description,usercp_options_time_format";
$templatelist .= ",usercp_editlists_sent_request,usercp_editlists_received_request,usercp_drafts_none,usercp_usergroups_memberof_usergroup_setdisplay,usercp_usergroups_memberof_usergroup_description,usercp_options_quick_reply";
$templatelist .= ",usercp_addsubscription_thread,forumdisplay_password,forumdisplay_password_wrongpass,delete_attachments_button,usercp_bookmarks_remove,usercp_bookmarks,";

  
  
  
  
  
  
  
  define ('TSF_FORUMS_TSSEv56', true);
  define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
  define ('TSF_VERSION', 'v1.5 by xam');
  define("SCRIPTNAME", "usercp.php");
   
   
  require_once 'global.php';
  
  if (!isset($CURUSER) || isset($CURUSER) && $CURUSER["id"] == 0) 
  {
     print_no_permission();
  }
  
  
  if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
  {
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  
  require_once INC_PATH.'/tsf_functions.php';
  require_once INC_PATH . '/editor.php';
  require_once INC_PATH . '/functions_multipage.php';
  require_once INC_PATH . '/functions_timezone.php';
  require_once INC_PATH.'/functions_user.php';
  require_once INC_PATH . '/functions_ts_remote_connect.php';
  require_once INC_PATH . '/readconfig.php';
 
  require_once(INC_PATH.'/class_parser.php');
  $parser = new postParser;
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
 
  
 
  // Load global language phrases
  $lang->load("usercp");
  $lang->load("member");
  

  gzip ();
  maxsysop ();
 
 
  $userid = intval ($CURUSER['id']);
  $IsStaff = is_mod ($usergroups);
  



$errors = '';

if(!isset($mybb->input['action']))
{
	$mybb->input['action'] = '';
}


usercp_menu();


$server_http_referer = '';
if(isset($_SERVER['HTTP_REFERER']))
{
	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	if(my_strpos($server_http_referer, $BASEURL.'/') !== 0)
	{
		if(my_strpos($server_http_referer, '/') === 0)
		{
			$server_http_referer = my_substr($server_http_referer, 1);
		}
		$url_segments = explode('/', $server_http_referer);
		$server_http_referer = $BASEURL.'/'.end($url_segments);
	}
}



 
 
 
$plugins->run_hooks("usercp_start");


if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler();

	$data = array(
		'uid' => $CURUSER['id'],
		'signature' => $mybb->get_input('signature'),
	);

	$userhandler->set_data($data);

	if(!$userhandler->verify_signature())
	{
		$error = inline_error($userhandler->get_friendly_errors());
	}

	if(isset($error) || !empty($mybb->input['preview']))
	{
		$mybb->input['action'] = "editsig";
	}
}



// Make navigation
add_breadcrumb($lang->usercp['nav_usercp'], "usercp.php");

switch($mybb->input['action'])
{
	case "profile":
	case "do_profile":
		add_breadcrumb($lang->usercp['ucp_nav_profile']);
		break;
	case "options":
	case "do_options":
		add_breadcrumb($lang->usercp['nav_options']);
		break;
	case "email":
	case "do_email":
		add_breadcrumb($lang->usercp['nav_email']);
		break;
	case "password":
	case "do_password":
		add_breadcrumb($lang->usercp['nav_password']);
		break;
	case "changename":
	case "do_changename":
		add_breadcrumb($lang->usercp['nav_changename']);
		break;
	case "subscriptions":
		add_breadcrumb($lang->usercpnav['ucp_nav_subscribed_threads']);
		break;
	case "forumsubscriptions":
		add_breadcrumb($lang->usercpnav['ucp_nav_forum_subscriptions']);
		break;
	case "editsig":
	case "do_editsig":
		add_breadcrumb($lang->usercp['nav_editsig']);
		break;
	case "avatar":
	case "do_avatar":
		add_breadcrumb($lang->usercp['nav_avatar']);
		break;
	case "notepad":
	case "do_notepad":
		add_breadcrumb($lang->usercpnav['ucp_nav_notepad']);
		break;
	case "editlists":
	case "do_editlists":
		add_breadcrumb($lang->usercpnav['ucp_nav_editlists']);
		break;
	case "drafts":
		add_breadcrumb($lang->usercpnav['ucp_nav_drafts']);
		break;
	case "usergroups":
		add_breadcrumb($lang->usercpnav['ucp_nav_usergroups']);
		break;
	case "attachments":
		add_breadcrumb($lang->usercpnav['ucp_nav_attachments']);
		break;
		
	case "bookmarks":
		add_breadcrumb($lang->usercpnav['ucp_nav_book']);
		break;
}









$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);


// Если аватар — это HTML-заглушка (начинается с '<'), выводим её как есть
if (strpos($useravatar['image'], '<') === 0) 
{
	 $avatarssss = $useravatar['image']; // <div class="avatar-ring2">No Avatar</div>
} 
// Иначе выводим как <img> (стандартный аватар)
else 
{
    $avatarssss = '<img class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
}



  
  
  
if($mybb->input['action'] == "do_changename" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	//if($mybb->usergroup['canchangename'] != 1)
	//{
	//	print_no_permission();
	//}

	$user = array();

	$plugins->run_hooks("usercp_do_changename_start");

	if(validate_password_from_uid($CURUSER['id'], $mybb->get_input('password')) == false)
	{
		$errors[] = $lang->usercp['error_invalidpassword'];
	}
	else
	{
		// Set up user handler.
		require_once INC_PATH."/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array_merge($user, array(
			"uid" => $CURUSER['id'],
			"username" => $mybb->get_input('username')
		));

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			$plugins->run_hooks("usercp_do_changename_end");
			redirect("usercp.php?action=changename", $lang->usercp['redirect_namechanged']);
		}
	}
	if(count($errors) > 0)
	{
		$errors = inline_error($errors);
		$mybb->input['action'] = "changename";
	}
}

  
  
if($mybb->input['action'] == "changename")
{
	$plugins->run_hooks("usercp_changename_start");
	//if($mybb->usergroup['canchangename'] != 1)
	//{
		//error_no_permission();
	//}

	// Coming back to this page after one or more errors were experienced, show field the user previously entered (with the exception of the password)
	if($errors)
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
	}
	else
	{
		$username = '';
	}

	$plugins->run_hooks("usercp_changename_end");

    stdhead($lang->usercp['change_username']);
	
	build_breadcrumb();
	
	eval("\$changename = \"".$templates->get("usercp_changename")."\";");
	
	echo $changename;
} 
 
 
 
if($mybb->input['action'] == "do_options" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();

	$plugins->run_hooks("usercp_do_options_start");

	// Set up user handler.
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array_merge($user, array(
		"uid" => $CURUSER['id'],
		"dateformat" => $mybb->get_input('dateformat', MyBB::INPUT_INT),
		"timeformat" => $mybb->get_input('timeformat', MyBB::INPUT_INT),
		"timezone" => $db->escape_string($mybb->get_input('timezoneoffset')),
		'usergroup'	=> $CURUSER['usergroup'],
		'additionalgroups'	=> $CURUSER['additionalgroups']
	));

	$user['options'] = array(
	
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
		"invisible" => $mybb->get_input('invisible', MyBB::INPUT_INT),
		"dstcorrection" => $mybb->get_input('dstcorrection', MyBB::INPUT_INT),
		"threadmode" => $mybb->get_input('threadmode'),
		"showsigs" => $mybb->get_input('showsigs', MyBB::INPUT_INT),
		"showavatars" => $mybb->get_input('showavatars', MyBB::INPUT_INT),
		"showredirect" => $mybb->get_input('showredirect', MyBB::INPUT_INT),
		"commentpm" => $mybb->get_input('commentpm', MyBB::INPUT_INT),
		"torrentsperpage" => $mybb->get_input('tp', MyBB::INPUT_INT),
		"daysprune" => $mybb->get_input('daysprune', MyBB::INPUT_INT),
		"buddyrequestsauto" => $mybb->get_input('buddyrequestsauto', MyBB::INPUT_INT),
		"buddyrequestspm" => $mybb->get_input('buddyrequestspm', MyBB::INPUT_INT),
		"pmnotice" => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
		"pmnotify" => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
		"receivepms" => $mybb->get_input('receivepms', MyBB::INPUT_INT)
		
	);

	$usertppoptions = "10,15,20,25,30,40,50";
	
	if($usertppoptions)
	{
		$user['options']['threadsperpages'] = $mybb->get_input('tpp', MyBB::INPUT_INT);
	}

	$userpppoptions = "5,10,15,20,25,30,40,50";
	
	if($userpppoptions)
	{
		$user['options']['postsperpage'] = $mybb->get_input('ppp', MyBB::INPUT_INT);
	}

	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$errors = inline_error($errors);
		$mybb->input['action'] = "options";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks("usercp_do_options_end");

		redirect("usercp.php?action=options", $lang->usercp['redirect_optionsupdated']);
	}
}

  

if($mybb->input['action'] == "options")
{
	if($errors != '')
	{
		$user = $mybb->input;
	}
	else
	{
		$user = $CURUSER;
	}

	$plugins->run_hooks("usercp_options_start");

	//$languages = $lang->get_languages();
	//$board_language = $langoptions = '';
	//if(count($languages) > 1)
	//{
		//foreach($languages as $name => $language)
		//{
			//$language = htmlspecialchars_uni($language);

			//$sel = '';
			//if(isset($user['language']) && $user['language'] == $name)
			//{
			//	$sel = " selected=\"selected\"";
			//}

			//eval('$langoptions .= "'.$templates->get('usercp_options_language_option').'";');
		//}

		//eval('$board_language = "'.$templates->get('usercp_options_language').'";');
	//}

	// Lets work out which options the user has selected and check the boxes
	if(isset($CURUSER['commentpm']) && $CURUSER['commentpm'] == 1)
	{
		$allowcommentpm = "checked=\"checked\"";
	}
	else
	{
		$allowcommentpm = "";
	}

	$canbeinvisible = '';

	// Check usergroup permission before showing invisible check box
	//if($mybb->usergroup['canbeinvisible'] == 1)
	//{
		if(isset($user['invisible']) && $user['invisible'] == 1)
		{
			$invisiblecheck = "checked=\"checked\"";
		}
		else
		{
			$invisiblecheck = "";
		}
		
		eval('$canbeinvisible = "'.$templates->get("usercp_options_invisible")."\";");
		
	//}

	if(isset($user['hideemail']) && $user['hideemail'] == 1)
	{
		$hideemailcheck = "checked=\"checked\"";
	}
	else
	{
		$hideemailcheck = "";
	}

	$no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
	if(isset($user['subscriptionmethod']) && $user['subscriptionmethod'] == 1)
	{
		$no_subscribe_selected = "selected=\"selected\"";
	}
	elseif(isset($user['subscriptionmethod']) && $user['subscriptionmethod'] == 2)
	{
		$instant_email_subscribe_selected = "selected=\"selected\"";
	}
	elseif(isset($user['subscriptionmethod']) && $user['subscriptionmethod'] == 3)
	{
		$instant_pm_subscribe_selected = "selected=\"selected\"";
	}
	else
	{
		$no_auto_subscribe_selected = "selected=\"selected\"";
	}


	if(isset($CURUSER['showsigs']) && $CURUSER['showsigs'] == 1)
	{
		$showsigscheck = "checked=\"checked\"";
	}
	else
	{
		$showsigscheck = "";
	}

	if(isset($CURUSER['showavatars']) && $CURUSER['showavatars'] == 1)
	{
		$showavatarscheck = "checked=\"checked\"";
	}
	else
	{
		$showavatarscheck = "";
	}

	

	if(isset($user['receivepms']) && $user['receivepms'] == 1)
	{
		$receivepmscheck = "checked=\"checked\"";
	}
	else
	{
		$receivepmscheck = "";
	}

	if(isset($user['receivefrombuddy']) && $user['receivefrombuddy'] == 1)
	{
		$receivefrombuddycheck = "checked=\"checked\"";
	}
	else
	{
		$receivefrombuddycheck = "";
	}

	if(isset($user['pmnotice']) && $user['pmnotice'] >= 1)
	{
		$pmnoticecheck = " checked=\"checked\"";
	}
	else
	{
		$pmnoticecheck = "";
	}

	$dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
	if(isset($user['dstcorrection']) && $user['dstcorrection'] == 2)
	{
		$dst_auto_selected = "selected=\"selected\"";
	}
	elseif(isset($user['dstcorrection']) && $user['dstcorrection'] == 1)
	{
		$dst_enabled_selected = "selected=\"selected\"";
	}
	else
	{
		$dst_disabled_selected = "selected=\"selected\"";
	}

	if(isset($user['showcodebuttons']) && $user['showcodebuttons'] == 1)
	{
		$showcodebuttonscheck = "checked=\"checked\"";
	}
	else
	{
		$showcodebuttonscheck = "";
	}

	if(isset($user['sourceeditor']) && $user['sourceeditor'] == 1)
	{
		$sourcemodecheck = "checked=\"checked\"";
	}
	else
	{
		$sourcemodecheck = "";
	}

	if(isset($user['showredirect']) && $user['showredirect'] != 0)
	{
		$showredirectcheck = "checked=\"checked\"";
	}
	else
	{
		$showredirectcheck = "";
	}

	if(isset($user['pmnotify']) && $user['pmnotify'] != 0)
	{
		$pmnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$pmnotifycheck = '';
	}

	if(isset($user['buddyrequestspm']) && $user['buddyrequestspm'] != 0)
	{
		$buddyrequestspmcheck = "checked=\"checked\"";
	}
	else
	{
		$buddyrequestspmcheck = '';
	}

	if(isset($user['buddyrequestsauto']) && $user['buddyrequestsauto'] != 0)
	{
		$buddyrequestsautocheck = "checked=\"checked\"";
	}
	else
	{
		$buddyrequestsautocheck = '';
	}

	if(!isset($user['threadmode']) || ($user['threadmode'] != "threaded" && $user['threadmode'] != "linear"))
	{
		$user['threadmode'] = ''; // Leave blank to show default
	}

	if(isset($user['classicpostbit']) && $user['classicpostbit'] != 0)
	{
		$classicpostbitcheck = "checked=\"checked\"";
	}
	else
	{
		$classicpostbitcheck = '';
	}

	$date_format_options = $dateformat = '';
	foreach($date_formats as $key => $format)
	{
		$selected = '';
		if(isset($CURUSER['dateformat']) && $CURUSER['dateformat'] == $key)
		{
			$selected = " selected=\"selected\"";
		}

		$dateformat = my_datee($format, TIMENOW, "", 0);
		$date_format_options .= '<option value="'.$key.'"'.$selected.'>'.$dateformat.'</option>';
	}

	$time_format_options = $timeformat = '';
	foreach($time_formats as $key => $format)
	{
		$selected = '';
		if(isset($CURUSER['timeformat']) && $CURUSER['timeformat'] == $key)
		{
			$selected = " selected=\"selected\"";
		}

		$timeformat = my_datee($format, TIMENOW, "", 0);
		$time_format_options .= '<option value="'.$key.'"'.$selected.'>'.$timeformat.'</option>';
	}

	$tzselect = build_timezone_select("timezoneoffset", $CURUSER['timezone'], true);
	
	
	$pms_from_buddys = '';
	
	$allowbuddyonly = "0";
	
	if($allowbuddyonly == 1)
	{
		eval("\$pms_from_buddys = \"".$templates->get("usercp_options_pms_from_buddys")."\";");
	}

	$pms = '';
	
	$enablepms = "1";
	
	if($enablepms != 0)
	{
		eval("\$pms = \"".$templates->get("usercp_options_pms")."\";");
	}

	//$quick_reply = '';
	//if($mybb->settings['quickreply'] == 1)
	//{
		//eval("\$quick_reply = \"".$templates->get("usercp_options_quick_reply")."\";");
	//}

	$threadview = array('linear' => '', 'threaded' => '');
	if(isset($user['threadmode']) && is_scalar($user['threadmode']))
	{
		$threadview[$user['threadmode']] = 'selected="selected"';
	}
	$daysprunesel = array(1 => '', 5 => '', 10 => '', 20 => '', 50 => '', 75 => '', 100 => '', 365 => '', 9999 => '');
	if(isset($user['daysprune']) && is_numeric($user['daysprune']))
	{
		$daysprunesel[$user['daysprune']] = 'selected="selected"';
	}
	if(!isset($user['style']))
	{
		$user['style'] = '';
	}

	$board_style = $stylelist = '';
	//$stylelist = build_theme_select("style", $user['style']);

	if(!empty($stylelist))
	{
		eval('$board_style = "'.$templates->get('usercp_options_style').'";');
	}

	
	$usertppoptions = "10,15,20,25,30,40,50";
	
	$tppselect = $pppselect = '';
	if($usertppoptions)
	{
		$explodedtpp = explode(",", $usertppoptions);
		$tppoptions = $tpp_option = '';
		if(is_array($explodedtpp))
		{
			foreach($explodedtpp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if(isset($user['threadsperpages']) && $user['threadsperpages'] == $val)
				{
					$selected = " selected=\"selected\"";
				}

				$tpp_option = sprintf($lang->usercp['tpp_option'], $val);
				eval("\$tppoptions .= \"".$templates->get("usercp_options_tppselect_option")."\";");
			}
		}
		
		eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
	}

	$userpppoptions = "5,10,15,20,25,30,40,50";
	
	if($userpppoptions)
	{
		$explodedppp = explode(",", $userpppoptions);
		$pppoptions = $ppp_option = '';
		if(is_array($explodedppp))
		{
			foreach($explodedppp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if(isset($user['postsperpage']) && $user['postsperpage'] == $val)
				{
					$selected = " selected=\"selected\"";
				}

				$ppp_option = sprintf($lang->usercp['ppp_option'], $val);
				//$pppoptions .= '<option value="'.$val.'"'.$selected.'>'.$ppp_option.'</option>';
				
				
				
				eval("\$pppoptions .= \"".$templates->get("usercp_options_pppselect_option")."\";");
			}
		}
	    eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
	}
	
	
	
	
	
	
	
	$userpppoptions22 = "5,10,15,20,25,30,40,50";
	
	if($userpppoptions22)
	{
		$explodedppp = explode(",", $userpppoptions22);
		$pppoptions2 = $ppp_option2 = '';
		if(is_array($explodedppp))
		{
			foreach($explodedppp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if(isset($CURUSER['torrentsperpage']) && $CURUSER['torrentsperpage'] == $val)
				{
					$selected = " selected=\"selected\"";
				}

				$ppp_option2 = sprintf($lang->usercp['tp_option'], $val);
				$pppoptions2 .= '
				
				
				<option value="'.$val.'"'.$selected.'>'.$ppp_option2.'</option>
				
				';
			}
		}
	   
		
		eval("\$pppselect2 = \"".$templates->get("usercp_options_tpppselect")."\";");
	}
	


$plugins->run_hooks("usercp_options_end");

stdhead($lang->usercp['edit_options']);

build_breadcrumb();


eval("\$editprofile = \"".$templates->get("usercp_options")."\";");


echo $editprofile;
	
	
	
}

  
  
  
  

 if($mybb->input['action'] == "do_email" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	$plugins->run_hooks("usercp_do_email_start");
	if(validate_password_from_uid($CURUSER['id'], $mybb->get_input('password')) == false)
	{
		$errors[] = 'error_invalidpassword';
	}
	else
	{
		// Set up user handler.
		require_once INC_PATH."/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $CURUSER['id'],
			"email" => $mybb->get_input('email'),
			"email2" => $mybb->get_input('email2')
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$activation = false;
			// Checking for pending activations for non-activated accounts
			if($CURUSER['usergroup'] == 5 && ($regtype == "verify" || $regtype == "both"))
			{
				$query = $db->simple_select("awaitingactivation", "*", "uid='".$CURUSER['id']."' AND (type='r' OR type='b')");
				$activation = $db->fetch_array($query);
			}
			if($activation)
			{
				$userhandler->update_user();

				$db->delete_query("awaitingactivation", "uid='".$CURUSER['id']."'");

				// Send new activation mail for non-activated accounts
				$activationcode = random_str();
				$activationarray = array(
					"uid" => $CURUSER['id'],
					"dateline" => TIMENOW,
					"code" => $activationcode,
					"type" => $activation['type']
				);
				$db->insert_query("awaitingactivation", $activationarray);
				$emailsubject = sprintf('Account Activation at '.$SITENAME.'');
				switch($username_method)
				{
					case 0:
						$emailmessage = sprintf($lang->member['email_activateaccount'], $CURUSER['username'], $SITENAME, $BASEURL, $CURUSER['id'], $activationcode);
						break;
					case 1:
						$emailmessage = sprintf($lang->member['email_activateaccount1'], $CURUSER['username'], $SITENAME, $BASEURL, $CURUSER['id'], $activationcode);
						break;
					case 2:
						$emailmessage = sprintf($lang->member['email_activateaccount2'], $CURUSER['username'], $SITENAME, $BASEURL, $CURUSER['id'], $activationcode);
						break;
					default:
						$emailmessage = sprintf($lang->member['email_activateaccount'], $CURUSER['username'], $SITENAME, $BASEURL, $CURUSER['id'], $activationcode);
						break;
				}
				my_mail($CURUSER['email'], $emailsubject, $emailmessage);

				$plugins->run_hooks("usercp_do_email_changed");
				redirect("usercp.php?action=email", $lang->member['redirect_emailupdated']);
			}
			elseif($mybb->usergroup['cancp'] != 1 && ($regtype == "verify" || $regtype == "both"))
			{
				$uid = $CURUSER['id'];
				$username = $CURUSER['username'];

				// Emails require verification
				$activationcode = random_str();
				$db->delete_query("awaitingactivation", "uid='".$CURUSER['id']."'");

				$newactivation = array(
					"uid" => $CURUSER['id'],
					"dateline" => TIMENOW,
					"code" => $activationcode,
					"type" => "e",
					"misc" => $db->escape_string($mybb->get_input('email'))
				);

				$db->insert_query("awaitingactivation", $newactivation);

				$mail_message = sprintf($lang->email_changeemail, $CURUSER['username'], $SITENAME, $CURUSER['email'], $mybb->get_input('email'), $BASEURL, $activationcode, $CURUSER['username'], $CURUSER['id']);

				$emailsubject_changeemail = sprintf('Change of Email at '.$SITENAME.'');
				my_mail($mybb->get_input('email'), $emailsubject_changeemail, $mail_message);

				$plugins->run_hooks("usercp_do_email_verify");
				error($lang->redirect_changeemail_activation);
			}
			else
			{
				$userhandler->update_user();
				// Email requires no activation
				$mail_message = sprintf($lang->member['email_changeemail_noactivation'], $CURUSER['username'], $SITENAME, $CURUSER['email'], $mybb->get_input('email'), $BASEURL);
				my_mail($mybb->get_input('email'), sprintf($lang->member['emailsubject_changeemail'], $SITENAME), $mail_message);
				$plugins->run_hooks("usercp_do_email_changed");
				redirect("usercp.php?action=email", $lang->member['redirect_emailupdated']);
			}
		}
	}
	if(count($errors) > 0)
	{
		$mybb->input['action'] = "email";
		$errors = inline_error($errors);
	}
}
  
  
  
if($mybb->input['action'] == "email")
{
	
	// Coming back to this page after one or more errors were experienced, show fields the user previously entered (with the exception of the password)
	if($errors)
	{
		$email = htmlspecialchars_uni($mybb->get_input('email'));
		$email2 = htmlspecialchars_uni($mybb->get_input('email2'));
	}
	else
	{
		$email = $email2 = '';
	}

	$plugins->run_hooks("usercp_email");


    eval("\$changemail = \"".$templates->get("usercp_email")."\";");
	

	stdhead($lang->usercp['change_email']);
	
	build_breadcrumb();
	

	echo $changemail;
}





if($mybb->input['action'] == "do_password" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();
	$errors = array();

	$plugins->run_hooks("usercp_do_password_start");
	if(validate_password_from_uid($CURUSER['id'], $mybb->get_input('oldpassword')) == false)
	{
		$errors[] = 'error_invalidpassword';
	}
	else
	{
		// Set up user handler.
		require_once INC_PATH."/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array_merge($user, array(
			"uid" => $CURUSER['id'],
			"password" => $mybb->get_input('password'),
			"password2" => $mybb->get_input('password2')
		));

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			
			my_setcookie("mybbuser", $CURUSER['id']."_".$userhandler->data['loginkey'], null, true, "lax");

			// Notify the user by email that their password has been changed
			//$mail_message = $lang->sprintf($lang->email_changepassword, $CURUSER['username'], $mybb->user['email'], $SITENAME, $BASEURL);
			//$lang->emailsubject_changepassword = $lang->sprintf($lang->emailsubject_changepassword, $SITENAME);
			//my_mail($mybb->user['email'], $lang->emailsubject_changepassword, $mail_message);

			$plugins->run_hooks("usercp_do_password_end");
			redirect("usercp.php?action=password", $lang->usercp['redirect_passwordupdated']);
		}
	}
	if(count($errors) > 0)
	{
			$mybb->input['action'] = "password";
			$errors = inline_error($errors);
	}
}





if($mybb->input['action'] == "password")
{
	$plugins->run_hooks("usercp_password");


	stdhead($lang->usercp['change_password']);
	
	build_breadcrumb();
	
	eval("\$editpassword = \"".$templates->get("usercp_password")."\";");
	
	echo $editpassword;
}







if ($mybb->input['action'] == "do_avatar" && $mybb->request_method == "post") 
{
    // Verify incoming POST request
    verify_post_check($mybb->get_input('my_post_key'));

    $allowremoteavatars = "1";
    $avatar_error = "";

    $plugins->run_hooks("usercp_do_avatar_start");

    require_once INC_PATH.'/functions_upload.php';

    if (!empty($mybb->input['remove'])) 
	{
        $updated_avatar = array(
            "avatar" => "",
            "avatardimensions" => "",
            "avatartype" => ""
        );
        $db->update_query("users", $updated_avatar, "id='" . $CURUSER['id'] . "'");
        remove_avatars($CURUSER['id']);
    }
    elseif ($_FILES['avatarupload']['name']) 
	{
        $avatar = upload_avatar();
        if (!empty($avatar['error'])) 
		{
            $avatar_error = $avatar['error'];
        } 
		else 
		{
            $avatar_dimensions = "";
            if ($avatar['width'] > 0 && $avatar['height'] > 0) {
                $avatar_dimensions = $avatar['width'] . "|" . $avatar['height'];
            }

            $updated_avatar = array(
                "avatar" => $avatar['avatar'],
                "avatardimensions" => $avatar_dimensions,
                "avatartype" => "upload"
            );
            $db->update_query("users", $updated_avatar, "id='" . $CURUSER['id'] . "'");
        }
    }
    
	elseif(!$allowremoteavatars && !$_FILES['avatarupload']['name']) // missing avatar image
	{
		$avatar_error = 'error_avatarimagemissing';
	}
	elseif($allowremoteavatars) // remote avatar
	{
		//$mybb->input['avatarurl'] = trim($mybb->input('avatarurl'));
		//$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->input['avatarurl']);
		
		//else
		//{
			//$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->get_input('avatarurl'));
			//$avataruploadpath = "./include/avatars";
			
			
			$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->input['avatarurl']);
			$ext = get_extension($mybb->input['avatarurl']);

			// Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
			$file = TS_Fetch_Data($mybb->input['avatarurl']);
			//$file = TS_Fetch_Data($_POST['avatarurl']);
			if(!$file)
			{
				$avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
			}
			else
			{
				//$tmp_name = $avataruploadpath."/remote_".md5;
				
				// Генерируем уникальное имя
                $tmp_name = $avataruploadpath."/remote_".md5(time().mt_rand());
				
				
				$fp = @fopen($tmp_name, "wb");
				if(!$fp)
				{
					$avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
				}
				else
				{
					fwrite($fp, $file);
					fclose($fp);
					list($width, $height, $type) = @getimagesize($tmp_name);
					@unlink($tmp_name);
					if(!$type)
					{
						$avatar_error = 'The URL you entered for your avatar does not appear to be valid. Please ensure you enter a valid URL';
					}
				}
			}

			$maxavatardims = "2000x2000";
			
			if(empty($avatar_error))
			{
				if($width && $height && $maxavatardims != "")
				{
					list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($maxavatardims));
					if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
					{
						$lang->error_avatartoobig = sprintf('error_avatartoobig22', $maxwidth, $maxheight);
						$avatar_error = 'error_avatartoobig2';
					}
				}
			}

			// Limiting URL string to stay within database limit
			if(strlen($mybb->input['avatarurl']) > 200)
			{
				$avatar_error = 'error_avatarurltoolong';
			}

			if(empty($avatar_error))
			{
				if($width > 0 && $height > 0)
				{
					$avatar_dimensions = (int)$width."|".(int)$height;
				}
				$updated_avatar = array(
					"avatar" => $db->escape_string($mybb->input['avatarurl']),
					"avatardimensions" => $avatar_dimensions,
					"avatartype" => "remote"
				);
				$db->update_query("users", $updated_avatar, "id='".$CURUSER['id']."'");
				remove_avatars($CURUSER['id']);
			}
		//}
	}
	else // remote avatar, but remote avatars are not allowed
	{
		$avatar_error = $lang->usercp['error_remote_avatar_not_allowed'];
	}
	
	

    if (empty($avatar_error)) 
	{
        $plugins->run_hooks("usercp_do_avatar_end");

        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true]);
            exit;
        }

        redirect("usercp.php?action=avatar", $lang->usercp['redirect_avatarupdated']);
    } 
	else 
	{
        if ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') 
		{
            echo json_encode(['success' => false, 'error' => $avatar_error]);
            exit;
        }

        $mybb->input['action'] = "avatar";
        $avatar_error = '
        <div class="container mt-3">
            <div class="red_alert mb-3" role="alert">
                ' . $avatar_error . '
            </div>
        </div>';
    }
}






if($mybb->input['action'] == "avatar")
{
	$plugins->run_hooks("usercp_avatar_start");

	$avatarmsg = $avatarurl = '';

	//$avataruploadpath = "./include/avatars";
	
	if($CURUSER['avatartype'] == "upload" || stristr($CURUSER['avatar'], $avataruploadpath))
	{
		$avatarmsg = "<br /><strong>".$lang->usercp['already_uploaded_avatar']."</strong>";
	}
	elseif($CURUSER['avatartype'] == "remote" || my_validate_url($CURUSER['avatar']))
	{
		$avatarmsg = "<br /><strong>".$lang->usercp['using_remote_avatar']."</strong>";
		$avatarurl = htmlspecialchars_uni($CURUSER['avatar']);
	}
	
	

    $useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);

// Определяем, нужно ли использовать <img> или выводить SVG напрямую
if (strpos($useravatar['image'], '<svg') === 0) 
{
    // Это SVG-заглушка - выводим как есть
    $currentavatar = '
    <div style="position: relative; display: inline-block;">
      <div id="avatarImage" class="rounded img-fluid" style="cursor: pointer;">
        '.$useravatar['image'].'
      </div>
      <input type="file" id="avatarInput" name="avatarupload" style="display: none;" accept="image/*">
    </div>';
} 
else 
{
    // Это обычный аватар - используем <img>
    $currentavatar = '
    <div style="position: relative; display: inline-block;">
      <img id="avatarImage" class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' style="cursor: pointer;">
      <input type="file" id="avatarInput" name="avatarupload" style="display: none;" accept="image/*">
    </div>';
}










echo '
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="avatarToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Avatar</strong>
      <small>Now</small>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toastMessage">
      Uploading...
    </div>
  </div>
</div>';


	


	if($maxavatardims != "")
	{
		list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($maxavatardims));
		$avatar_note .= "<br />".sprintf($lang->usercp['avatar_note_dimensions'], $maxwidth, $maxheight);
	}

	
	///$avatarsize = "25000";
	
	if($avatarsize)
	{
		$maxsize = mksize($avatarsize*1024);
		$avatar_note .= "<br />".sprintf($lang->usercp['avatar_note_size'], $maxsize);
	}

	$plugins->run_hooks("usercp_avatar_intermediate");

	$auto_resize = '';
	
	$avatarresizing = "auto";
	
	if($avatarresizing == "auto")
	{
	$auto_resize = '<div class="mt-1"><i class="bi bi-info-circle"></i> &nbsp;<span class="text-muted" style="font-size: 14px">'.$lang->usercp['avatar_auto_resize_note'].'</span></div>';
	}
	elseif($avatarresizing == "user")
	{
		$auto_resize = '<div class="form-check mt-1">
  <input class="form-check-input" type="checkbox" name="auto_resize" value="1" checked="checked" id="auto_resize" />
  <label class="form-check-label" for="flexCheckDefault">
    '.$lang->usercp['avatar_auto_resize_option'].'
  </label>
</div>';
	}

	//$avatarupload = '';
	
	//eval("\$avatarupload = \"".$templates->get("usercp_avatar_upload")."\";");
	

	$avatar_remote = '';
	
	eval("\$avatar_remote = \"".$templates->get("usercp_avatar_remote")."\";");
	

	$removeavatar = '';
	if(!empty($CURUSER['avatar']))
	{
		$removeavatar = '
		
		<button type="submit" class="btn btn-secondary" name="remove" value="Remove Avatar"><i class="fa-solid fa-xmark"></i> &nbsp;Remove Avatar</button>
		
		';
	}

	$plugins->run_hooks("usercp_avatar_end");

	if(!isset($avatar_error))
	{
		$avatar_error = '';
	}

    stdhead('title');
	
	build_breadcrumb();
	
	
	
	
	
echo <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    var avatarImage = document.getElementById("avatarImage");
    var avatarInput = document.getElementById("avatarInput");

    if (!avatarImage || !avatarInput) return;

    avatarImage.addEventListener("click", function() {
        avatarInput.click();
    });

    avatarInput.addEventListener("change", function() {
        var file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            avatarImage.src = e.target.result;
        };
        reader.readAsDataURL(file);

        const formData = new FormData();
        formData.append("avatarupload", file);
        formData.append("action", "do_avatar");
        formData.append("my_post_key", my_post_key);

        fetch("usercp.php?action=do_avatar", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast("Avatar successfully updated!", "success");
            } else {
                showToast("Error: " + data.error, "danger");
            }
        })
        .catch(error => {
            showToast("Upload error: " + error, "danger");
        });
    });

    function showToast(message, type = 'success') {
        const toastHeader = document.querySelector("#avatarToast .toast-header");
        const toastMessage = document.getElementById("toastMessage");

        toastHeader.classList.remove("bg-success", "bg-danger", "text-white");
        toastHeader.classList.add("text-white");

        if (type === 'success') {
            toastHeader.classList.add("bg-success");
        } else if (type === 'danger') {
            toastHeader.classList.add("bg-danger");
        }

        toastMessage.innerHTML = message;
        new bootstrap.Toast(document.getElementById("avatarToast")).show();
    }
});
</script>
JS;


	
	
	
	
	eval("\$avatar = \"".$templates->get("usercp_avatar")."\";");
	
	
    echo $avatar;
}







if($mybb->input['action'] == "do_addsubscription" && $mybb->get_input('type') != "forum")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$thread = get_thread($mybb->get_input('tid'));
	if(!$thread || $thread['visible'] == -1)
	{
		error('error_invalidthread');
	}

	// Is the currently logged in user a moderator of this forum?
	//$ismod = is_moderator($thread['fid']);

	// Make sure we are looking at a real thread here.
	//if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	//{
	//	error($lang->error_invalidthread);
	//}

	//$forumpermissions = forum_permissions($thread['fid']);
	//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	//{
		//error_no_permission();
	//}

	// check if the forum requires a password to view. If so, we need to show a form to the user
	//check_forum_password($thread['fid']);

	// Naming of the hook retained for backward compatibility while dropping usercp2.php
	$plugins->run_hooks("usercp2_do_addsubscription");

	add_subscribed_thread($thread['tid'], $mybb->get_input('notification', MyBB::INPUT_INT));

	if($mybb->get_input('referrer'))
	{
		$mybb->input['referrer'] = $mybb->get_input('referrer');

		if(my_strpos($mybb->input['referrer'], $BASEURL.'/') !== 0)
		{
			if(my_strpos($mybb->input['referrer'], '/') === 0)
			{
				$mybb->input['referrer'] = my_substr($mybb->input['url'], 1);
			}
			$url_segments = explode('/', $mybb->input['referrer']);
			$mybb->input['referrer'] = $BASEURL.'/'.end($url_segments);
		}

		$url = htmlspecialchars_uni($mybb->input['referrer']);
	}
	else
	{
		$url = get_thread_link($thread['tid']);
	}
	redirect($url, 'The selected thread has been added to your subscriptions list.<br />You will be now returned to the location you came from');
}








if($mybb->input['action'] == "addsubscription")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error('error_invalidforum');
		}
		//$forumpermissions = forum_permissions($forum['fid']);
		//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		//{
		//	error_no_permission();
		//}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		//check_forum_password($forum['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_addsubscription_forum");

		add_subscribed_forum($forum['fid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "index.php";
		}
		redirect($url, 'redirect_forumsubscriptionadded');
	}
	else
	{
		$thread  = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread || $thread['visible'] == -1)
		{
			error('error_invalidthread');
		}

		// Is the currently logged in user a moderator of this forum?
		//$ismod = is_moderator($thread['fid']);

		// Make sure we are looking at a real thread here.
		//if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		//{
			//error($lang->error_invalidthread);
		//}

		add_breadcrumb('nav_subthreads', "usercp.php?action=subscriptions");
		add_breadcrumb('nav_addsubscription');

		//$forumpermissions = forum_permissions($thread['fid']);
		//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
		//{
		//	error_no_permission();
		//}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		//check_forum_password($thread['fid']);

		$referrer = '';
		if($server_http_referer)
		{
			$referrer = $server_http_referer;
		}

		//require_once MYBB_ROOT."inc/class_parser.php";
		//$parser = new postParser;
		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$lang->subscribe_to_thread = sprintf('subscribe_to_thread', $thread['subject']);

		$notification_none_checked = $notification_email_checked = $notification_pm_checked = '';
		if($CURUSER['subscriptionmethod'] == 1 || $CURUSER['subscriptionmethod'] == 0)
		{
			$notification_none_checked = "checked=\"checked\"";
		}
		elseif($CURUSER['subscriptionmethod'] == 2)
		{
			$notification_email_checked = "checked=\"checked\"";
		}
		elseif($CURUSER['subscriptionmethod'] == 3)
		{
			$notification_pm_checked = "checked=\"checked\"";
		}

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_addsubscription_thread");

		eval("\$add_subscription = \"".$templates->get("usercp_addsubscription_thread")."\";");
		
        stdhead($lang->usercp['subscribe_to_thread']);
		
		build_breadcrumb();
		
		echo $add_subscription;
		
		exit;
	}
}




if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// User currently has a suspended signature
	//if($mybb->user['suspendsignature'] == 1 && $mybb->user['suspendsigtime'] > TIME_NOW)
	//{
		//error_no_permission();
	//}

	$plugins->run_hooks("usercp_do_editsig_start");

	//if($mybb->get_input('updateposts') == "enable")
	//{
		//$update_signature = array(
			//"includesig" => 1
		//);
		//$db->update_query("tsf_posts", $update_signature, "uid='".$CURUSER['id']."'");
	//}
	//elseif($mybb->get_input('updateposts') == "disable")
	//{
		//$update_signature = array(
			//"includesig" => 0
		//);
	    //$db->update_query("tsf_posts", $update_signature, "uid='".$CURUSER['id']."'");
	//}
	
	$new_signature = array(
		"signature" => $db->escape_string($mybb->input['signature'])
	);
	$plugins->run_hooks("usercp_do_editsig_process");
	$db->update_query("users", $new_signature, "id='".$CURUSER['id']."'");
	$plugins->run_hooks("usercp_do_editsig_end");
	redirect("usercp.php?action=editsig", 'Your signature has been successfully updated.<br />You will be now returned to the signature settings');
}



if($mybb->input['action'] == "editsig")
{
	$plugins->run_hooks("usercp_editsig_start");
	if(!empty($mybb->input['preview']) && empty($error))
	{
		$sig = $mybb->get_input('signature');
		$template = "usercp_editsig_preview";

	}
	elseif(empty($error))
	{
		$sig = $CURUSER['signature'];
		$template = "usercp_editsig_current";
	}
	else
	{
		$sig = $mybb->get_input('signature');
		$template = false;
	}

	if(!isset($error))
	{
		$error = '';
	}

	//if($mybb->user['suspendsignature'] && ($mybb->user['suspendsigtime'] == 0 || $mybb->user['suspendsigtime'] > 0 && $mybb->user['suspendsigtime'] > TIME_NOW))
	//{
		// User currently has no signature and they're suspended
		//error($lang->sig_suspended);
	//}

	//if($mybb->usergroup['canusesig'] != 1)
	//{
		// Usergroup has no permission to use this facility
		//error_no_permission();
	//}
	//elseif($mybb->usergroup['canusesig'] == 1 && $mybb->usergroup['canusesigxposts'] > 0 && $mybb->user['postnum'] < $mybb->usergroup['canusesigxposts'])
	//{
		// Usergroup can use this facility, but only after x posts
	//	error($lang->sprintf($lang->sig_suspended_posts, $mybb->usergroup['canusesigxposts']));
	//}

	$signature = '';
	if($sig && $template)
	{
		$sig_parser = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"me_username" => 1,
			"filter_badwords" => 1
		);

		

		$sigpreview = $parser->parse_message($sig, $sig_parser);
		eval("\$signature = \"".$templates->get($template)."\";");
	}

	// User has a current signature, so let's display it (but show an error message)
	if($mybb->user['suspendsignature'] && $mybb->user['suspendsigtime'] > TIMENOW)
	{
		$plugins->run_hooks("usercp_editsig_end");

		// User either doesn't have permission, or has their signature suspended
	    eval("\$editsig = \"".$templates->get("usercp_editsig_suspended")."\";");
	}
	else
	{
		// User is allowed to edit their signature
		$smilieinserter = '';
		
		
		$sigsmilies = "1";
		$sigmycode = "1";
		$sightml = "0";
		$sigimgcode = "1";
		$siglength = "255";
		
		if($sigsmilies == 1)
		{
			$sigsmilies = $lang->usercp['on'];
			//$smilieinserter = build_clickable_smilies();
		}
		else
		{
			$sigsmilies = $lang->usercp['off'];
		}
		if($sigmycode == 1)
		{
			$sigmycode = $lang->usercp['on'];
		}
		else
		{
			$sigmycode = $lang->usercp['off'];
		}
		if($sightml == 1)
		{
			$sightml = $lang->usercp['on'];
		}
		else
		{
			$sightml = $lang->usercp['off'];
		}
		if($sigimgcode == 1)
		{
			$sigimgcode = $lang->usercp['on'];
		}
		else
		{
			$sigimgcode = $lang->usercp['off'];
		}

		if($siglength == 0)
		{
			$siglength = $lang->usercp['unlimited'];
		}
		else
		{
			$siglength = $siglength;
		}

		$sig = htmlspecialchars_uni($sig);
		
		
	    require_once INC_PATH . '/editor.php';
	    require_once 'cache/smilies.php';
		
        $editor = insert_bbcode_editor($smilies, $BASEURL, 'signature');
			
			
		

		$plugins->run_hooks("usercp_editsig_end");

        eval("\$editsig = \"".$templates->get("usercp_editsig")."\";");
	}

	stdhead($lang->usercp['edit_sig']);
	
	
	
	build_breadcrumb();
	
	echo $editsig;
}





if($mybb->input['action'] == "do_profile" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();

	$plugins->run_hooks("usercp_do_profile_start");

	

	$bday = array(
		"day" => $mybb->get_input('bday1', MyBB::INPUT_INT),
		"month" => $mybb->get_input('bday2', MyBB::INPUT_INT),
		"year" => $mybb->get_input('bday3', MyBB::INPUT_INT)
	);

	// Set up user handler.
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array_merge($user, array(
		"uid" => $CURUSER['id'],
		"postnum" => $CURUSER['postnum'],
		"usergroup" => $CURUSER['usergroup'],
		"additionalgroups" => $CURUSER['additionalgroups'],
		"birthday" => $bday,
		"birthdayprivacy" => $mybb->get_input('birthdayprivacy')
	));
	

	
	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$raw_errors = $userhandler->get_errors();

		// Set to stored value if invalid
		if(array_key_exists("invalid_birthday_privacy", $raw_errors) || array_key_exists("conflicted_birthday_privacy", $raw_errors))
		{
			$mybb->input['birthdayprivacy'] = $CURUSER['birthdayprivacy'];
			$bday = explode("-", $CURUSER['birthday']);

			if(isset($bday[2]))
			{
				$mybb->input['bday3'] = $bday[2];
			}
		}

		$errors = inline_error($errors);
		$mybb->input['action'] = "profile";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks("usercp_do_profile_end");
		redirect("usercp.php?action=profile", 'redirect_profileupdated');
	}
}





if($mybb->input['action'] == "removesubscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscriptions_forum");

		$db->delete_query("tsf_forumsubscriptions", "uid='".$CURUSER['id']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionsremoved);
	}
	else
	{
		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscriptions_thread");

		$db->delete_query("tsf_threadsubscriptions", "uid='".$CURUSER['id']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->usercp['redirect_subscriptionsremoved']);
	}
}



if($mybb->input['action'] == "do_subscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if(!isset($mybb->input['check']) || !is_array($mybb->input['check']))
	{
		error($lang->usercp['no_subscriptions_selected']);
	}

	$plugins->run_hooks("usercp_do_subscriptions_start");

	// Clean input - only accept integers thanks!
	$mybb->input['check'] = array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY));
	$tids = implode(",", $mybb->input['check']);

	// Deleting these subscriptions?
	if($mybb->get_input('do') == "delete")
	{
		$db->delete_query("tsf_threadsubscriptions", "tid IN ($tids) AND uid='{$CURUSER['id']}'");
	}
	// Changing subscription type
	else
	{
		if($mybb->get_input('do') == "no_notification")
		{
			$new_notification = 0;
		}
		elseif($mybb->get_input('do') == "email_notification")
		{
			$new_notification = 1;
		}
		elseif($mybb->get_input('do') == "pm_notification")
		{
			$new_notification = 2;
		}

		// Update
		$update_array = array("notification" => $new_notification);
		$db->update_query("tsf_threadsubscriptions", $update_array, "tid IN ($tids) AND uid='{$CURUSER['id']}'");
	}

	// Done, redirect
	redirect("usercp.php?action=subscriptions", $lang->usercp['redirect_subscriptions_updated']);
}



if($mybb->input['action'] == "subscriptions")
{
	$plugins->run_hooks("usercp_subscriptions_start");

	// Thread visiblity
	$where = array(
		"s.uid={$CURUSER['id']}",
		//get_visible_where('t')
	);

	//if($unviewable_forums = get_unviewable_forums(true))
	//{
	//	$where[] = "t.fid NOT IN ({$unviewable_forums})";
	//}

	if($inactive_forums = get_inactive_forums())
	{
		$where[] = "t.fid NOT IN ({$inactive_forums})";
	}

	$where = implode(' AND ', $where);

	// Do Multi Pages
	$query = $db->sql_query("
		SELECT COUNT(s.tid) as threads
		FROM tsf_threadsubscriptions s
		LEFT JOIN tsf_threads t ON (t.tid = s.tid)
		WHERE {$where}
	");
	$threadcount = $db->fetch_field($query, "threads");

	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	$perpage = $f_threadsperpage;
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
		$pages = $threadcount / $perpage;
		$pages = ceil($pages);
		if($page > $pages || $page <= 0)
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
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=subscriptions");
	//$fpermissions = forum_permissions();
	$del_subscriptions = $subscriptions = array();

	// Fetch subscriptions
	$query = $db->sql_query("
		SELECT s.*, t.*, t.username AS threadusername, u.username
		FROM tsf_threadsubscriptions s
		LEFT JOIN tsf_threads t ON (s.tid=t.tid)
		LEFT JOIN users u ON (u.id = t.uid)
		WHERE {$where}
		ORDER BY t.lastpost DESC
		LIMIT $start, $perpage
	");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $subscription['uid'] != $mybb->user['id'])
		{
			// Hmm, you don't have permission to view this thread - unsubscribe!
			$del_subscriptions[] = $subscription['sid'];
		}
		elseif($subscription['tid'])
		{
			$subscriptions[$subscription['tid']] = $subscription;
		}
	}

	if(!empty($del_subscriptions))
	{
		$sids = implode(',', $del_subscriptions);

		if($sids)
		{
			$db->delete_query("tsf_threadsubscriptions", "sid IN ({$sids}) AND uid='{$CURUSER['id']}'");
		}

		$threadcount = $threadcount - count($del_subscriptions);

		if($threadcount < 0)
		{
			$threadcount = 0;
		}
	}

	if(!empty($subscriptions))
	{
		$tids = implode(",", array_keys($subscriptions));
		$readforums = array();

		// Build a forum cache.
		$query = $db->sql_query("
			SELECT f.fid, fr.dateline AS lastread
			FROM tsf_forums f
			LEFT JOIN tsf_forumsread fr ON (fr.fid=f.fid AND fr.uid='{$CURUSER['id']}')
			WHERE f.active != 0
			ORDER BY pid, disporder
		");

		while($forum = $db->fetch_array($query))
		{
			$readforums[$forum['fid']] = $forum['lastread'];
		}

		// Check participation by the current user in any of these threads - for 'dot' folder icons
		$dotfolders = "1";
		
		if($dotfolders != 0)
		{
			$query = $db->simple_select("tsf_posts", "tid,uid", "uid='{$CURUSER['id']}' AND tid IN ({$tids})");
			while($post = $db->fetch_array($query))
			{
				$subscriptions[$post['tid']]['doticon'] = 1;
			}
		}

		// Read threads
		$threadreadcut = "7";
		
		if($threadreadcut > 0)
		{
			$query = $db->simple_select("tsf_threadsread", "*", "uid='{$CURUSER['id']}' AND tid IN ({$tids})");
			while($readthread = $db->fetch_array($query))
			{
				$subscriptions[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}

		$icon_cache = $cache->read("posticons");
		$threadprefixes = build_prefixes();

		$threads = '';

		// Now we can build our subscription list
		foreach($subscriptions as $thread)
		{
			$bgcolor = alt_trow();

			$folder = '';
			$prefix = '';
			$thread['threadprefix'] = '';

			// If this thread has a prefix, insert a space between prefix and subject
			if($thread['prefix'] != 0 && !empty($threadprefixes[$thread['prefix']]))
			{
				$thread['threadprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
			}

			// Sanitize
			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			// Build our links
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

			// Fetch the thread icon if we have one
			if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
			{
				$icon = $icon_cache[$thread['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$icon['path'] = htmlspecialchars_uni($icon['path']);
				$icon['name'] = htmlspecialchars_uni($icon['name']);
				
				$icon = '<img src="'.$icon['path'].'" alt="'.$icon['name'].'" title="'.$icon['name'].'" />';
			}
			else
			{
				$icon = "&nbsp;";
			}

			// Determine the folder
			$folder = '';
			$folder_label = '';

			if(isset($thread['doticon']))
			{
				$folder = "dot_";
				$folder_label .= 'icon_dot';
			}

			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;

			if($threadreadcut > 0)
			{
				$read_cutoff = TIMENOW-$threadreadcut*60*60*24;
				if(empty($readforums[$thread['fid']]) || $readforums[$thread['fid']] < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
				else
				{
					$forum_read = $readforums[$thread['fid']];
				}
			}

			$cutoff = 0;
			if($threadreadcut > 0 && $thread['lastpost'] > $forum_read)
			{
				$cutoff = TIMENOW-$threadreadcut*60*60*24;
			}

			if($thread['lastpost'] > $cutoff)
			{
				if(!empty($thread['lastread']))
				{
					$lastread = $thread['lastread'];
				}
				else
				{
					$lastread = 1;
				}
			}

			if(!$lastread)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
				if($readcookie > $forum_read)
				{
					$lastread = $readcookie;
				}
				else
				{
					$lastread = $forum_read;
				}
			}

			if($lastread && $lastread < $thread['lastpost'])
			{
				$folder .= "new";
				$folder_label .= $lang->icon_new;
				$new_class = "subject_new";
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
				
			
				
				$gotounread = '
				
				
				<a href="'.$thread['newpostlink'].'"><img src="pic/jump.png" alt="Go to first unread post" title="Go to first unread post" /></a> 
				
				
				';
				
				
				
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= 'icon_no_new';
				$new_class = "subject_old";
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

			if($thread['visible'] == 0)
			{
				$bgcolor = "trow_shaded";
			}

			// Build last post info
			$lastpostdate = my_datee('relative', $thread['lastpost']);
			$lastposteruid = $thread['lastposteruid'];
			if(!$lastposteruid && !$thread['lastposter'])
			{
				$lastposter = htmlspecialchars_uni('guest');
			}
			else
			{
				$lastposter = htmlspecialchars_uni($thread['lastposter']);
			}

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

			// What kind of notification type do we have here?
			switch($thread['notification'])
			{
				case "2": // PM
					$notification_type = 'Instant PM Notification';
					break;
				case "1": // Email
					$notification_type = 'Change to email notification';
					break;
				default: // No notification
					$notification_type = 'No Notification';
			}

			eval("\$threads .= \"".$templates->get("usercp_subscriptions_thread")."\";");
		}

		$gobutton = '<button type="submit" class="btn btn-sm btn-primary rounded" value="Go"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>';
		
		
		// Provide remove options
		eval("\$remove_options = \"".$templates->get("usercp_subscriptions_remove")."\";");
		
		
		
	}
	else
	{
		$remove_options = '';
		
		
		$threads = 'You re currently not subscribed to any threads.<p>To subscribe to a thread:</p><ol><li>Navigate to the thread you wish to subscribe to.</li><li>Click the Subscribe to this thread link towards the bottom of the page.</li></ol>';
	}

	$plugins->run_hooks("usercp_subscriptions_end");

    eval("\$subscriptions = \"".$templates->get("usercp_subscriptions")."\";");
	
	
	stdhead('title');
	
	build_breadcrumb();
	
	echo $subscriptions;
	
	
}






if($mybb->input['action'] == "removesubscription" && ($mybb->request_method == "post" || verify_post_check($mybb->get_input('my_post_key'), true)))
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error('error_invalidforum');
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		//check_forum_password($forum['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_forum");

		remove_subscribed_forum($forum['fid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, 'The selected forum has been removed from your forum subscriptions list.<br />You will be now returned to where you came from.');
	}
	else
	{
		$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			error('error_invalidthread');
		}

		// Is the currently logged in user a moderator of this forum?
		//$ismod = is_moderator($thread['fid']);

		// Make sure we are looking at a real thread here.
		//if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		//{
		//	error($lang->error_invalidthread);
		//}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		//check_forum_password($thread['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_thread");

		remove_subscribed_thread($thread['tid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->usercp['redirect_subscriptionremoved']);
	}
}




if($mybb->input['action'] == "forumsubscriptions")
{
	$plugins->run_hooks("usercp_forumsubscriptions_start");

	// Build a forum cache.
	$query = $db->sql_query("
		SELECT f.fid, fr.dateline AS lastread
		FROM tsf_forums f
		LEFT JOIN tsf_forumsread fr ON (fr.fid=f.fid AND fr.uid='{$CURUSER['id']}')
		WHERE f.active != 0
		ORDER BY pid, disporder
	");
	$readforums = array();
	while($forum = $db->fetch_array($query))
	{
		$readforums[$forum['fid']] = $forum['lastread'];
	}

	//$fpermissions = forum_permissions();
	require_once INC_PATH."/functions_forumlist.php";

	$user_id = (int)$CURUSER['id'];

    $query = $db->sql_query_prepared("
    SELECT fs.*, f.*, t.subject AS lastpostsubject, fr.dateline AS lastread
    FROM tsf_forumsubscriptions fs
    LEFT JOIN tsf_forums f ON (f.fid = fs.fid)
    LEFT JOIN tsf_threads t ON (t.tid = f.lastposttid)
    LEFT JOIN tsf_forumsread fr ON (fr.fid = f.fid AND fr.uid = ?)
    WHERE f.type = 'f' AND fs.uid = ?
    ORDER BY f.name ASC", [$user_id, $user_id]);

	$forums = '';
	while($forum = $db->fetch_array($query->result))
	{
		$forum_url = get_forum_link($forum['fid']);
		$forumpermissions = $fpermissions[$forum['fid']];

		//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		//{
		//	continue;
		//}

		$lightbulb = get_forum_lightbulb(array('open' => $forum['open'], 'lastread' => $forum['lastread']), array('lastpost' => $forum['lastpost']));
		$folder = $lightbulb['folder'];

		//if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0)
		//{
		//	$posts = '-';
		//	$threads = '-';
		//}
		
		//else
		//{
			$posts = ts_nf($forum['posts']);
			$threads = ts_nf($forum['threads']);
		//}

		if($forum['lastpost'] == 0)
		{
			eval("\$lastpost = \"".$templates->get("forumbit_depth2_forum_lastpost_never")."\";");
			
			
		}
		// Hide last post
		//elseif(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $forum['lastposteruid'] != $mybb->user['uid'])
		//{
		//	eval("\$lastpost = \"".$templates->get("forumbit_depth2_forum_lastpost_hidden")."\";");
		//}
		//else
		//{
			$forum['lastpostsubject'] = $parser->parse_badwords($forum['lastpostsubject']);
			$lastpost_date = my_datee('relative', $forum['lastpost']);
			$lastposttid = $forum['lastposttid'];
			if(!$forum['lastposteruid'] && !$forum['lastposter'])
			{
				$lastposter = htmlspecialchars_uni($lang->guest);
			}
			else
			{
				$lastposter = htmlspecialchars_uni($forum['lastposter']);
			}
			if($forum['lastposteruid'] == 0)
			{
				$lastpost_profilelink = $lastposter;
			}
			else
			{
				$lastpost_profilelink = build_profile_link($lastposter, $forum['lastposteruid']);
			}
			$full_lastpost_subject = $lastpost_subject = htmlspecialchars_uni($forum['lastpostsubject']);
			if(my_strlen($lastpost_subject) > 25)
			{
				$lastpost_subject = my_substr($lastpost_subject, 0, 25) . "...";
			}
			$lastpost_link = get_thread_link($forum['lastposttid'], 0, "lastpost");
			
			eval("\$lastpost = \"".$templates->get("forumbit_depth2_forum_lastpost")."\";");
			
			
			
			
			
		//}

		$showdescriptions = "1";
		
		if($showdescriptions == 0)
		{
			$forum['description'] = "";
		}

		eval("\$forums .= \"".$templates->get("usercp_forumsubscriptions_forum")."\";");
	}

	if(!$forums)
	{
		eval("\$forums = \"".$templates->get("usercp_forumsubscriptions_none")."\";");
	}

	$plugins->run_hooks("usercp_forumsubscriptions_end");

    eval("\$forumsubscriptions = \"".$templates->get("usercp_forumsubscriptions")."\";");
	
	stdhead($lang->usercp['forum_subscriptions']);
	
	build_breadcrumb();
	
	echo $forumsubscriptions;
}














if($mybb->input['action'] == "do_removebookmarks")
{
    verify_post_check($mybb->get_input('my_post_key'));

    if(!isset($mybb->input['check']) || !is_array($mybb->input['check']))
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $lang->usercp['no_books_selected']
        ]);
        exit;
    }

    $plugins->run_hooks("usercp_do_subscriptions_start");

    $mybb->input['check'] = array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY));
    $tids = implode(",", $mybb->input['check']);

    if($mybb->get_input('do') == "delete")
    {
        $db->delete_query("bookmarks", "torrentid IN ($tids) AND userid='{$CURUSER['id']}'");
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $lang->usercp['redirect_bookmark_updated']
    ]);
    exit;
}

if($mybb->input['action'] == "removebookmarks")
{
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks("usercp2_removesubscriptions_thread");

    $db->delete_query("bookmarks", "userid='{$CURUSER['id']}'");

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $lang->usercp['redirect_bookmarkremoved']
    ]);
    exit;
}

// ======================= BOOKMARKS PAGE =======================
if($mybb->input['action'] == "bookmarks")
{
    $plugins->run_hooks("usercp_bookmarks_start");

// Первый запрос - подсчет общего количества
$count_query = $db->sql_query_prepared("
    SELECT COUNT(*) as total
    FROM bookmarks
    WHERE userid = ?
", [(int)$CURUSER['id']]);
$total = $db->fetch_field($count_query->result, 'total');

$perpage = 12;
$page = $mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
$start = ($page-1) * $perpage;
$multipage = multipage($total, $perpage, $page, "usercp.php?action=bookmarks");

// Второй запрос - получение данных с пагинацией
$query = $db->sql_query_prepared("
    SELECT b.*, t.name, t.added, t.seeders, t.leechers, t.t_image
    FROM bookmarks b
    LEFT JOIN torrents t ON t.id = b.torrentid
    WHERE b.userid = ?
    LIMIT ?, ?
", [(int)$CURUSER['id'], (int)$start, (int)$perpage]);
	
	
	echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css">';
	 // CSS для красивых карточек
    echo '<style>
    .bookmark-card { transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease; border-radius: 12px; opacity:0; transform: translateY(20px); animation: fadeInUp 0.5s forwards;}
    .bookmark-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.2);}
    .bookmark-img { width:100%; height:180px; object-fit:cover; transition: transform 0.3s ease;}
    .bookmark-card:hover .bookmark-img { transform: scale(1.05);}
    .bookmark-overlay { position:absolute; bottom:0; left:0; right:0; background: rgba(0,0,0,0.5); padding:0.3rem;}
    .bookmark-overlay h6 { color:#fff; margin:0; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
    @keyframes fadeInUp { to {opacity:1; transform: translateY(0);} }
    </style>';
	
	
	

    $bookmark_cards = '';
    $counter = 0;

    if($db->num_rows($query->result) > 0)
    {
        while($bm = $db->fetch_array($query->result))
        {
            $torrent_link = get_torrent_link($bm['torrentid']);
            //$added = my_datee('relative', $bm['added']);
            $image = !empty($bm['t_image']) ? htmlspecialchars_uni($bm['t_image']) : 'default_torrent.png';
            $name = cutename($bm['name'], 60);

            $bookmark_cards .= '
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm h-100 bookmark-card" style="animation-delay: '.($counter*0.05).'s;">
                    <a href="'.$torrent_link.'" class="position-relative d-block">
                        <img src="'.$image.'" class="card-img-top bookmark-img" alt="'.htmlspecialchars_uni($bm['name']).'">
                        <div class="bookmark-overlay"><h6>'.$name.'</h6></div>
                    </a>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2 small text-muted">
                            <span class="me-2"><i class="fa-solid fa-arrow-up me-1"></i>'.$bm['seeders'].'</span>
                            <span><i class="fa-solid fa-arrow-down me-1"></i>'.$bm['leechers'].'</span>
                        </div>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                               <i class="bi bi-calendar me-1"></i> ' . my_datee($dateformat, $bm['added']) . '
                               <i class="bi bi-clock me-1"></i> ' . my_datee($timeformat, $bm['added']) . '
                            </span>
                            <input type="checkbox" class="form-check-input" name="check['.$bm['torrentid'].']" value="'.$bm['torrentid'].'">
                        </div>
                    </div>
                </div>
            </div>';
            $counter++;
        }

        $remove_options = '
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
            <button type="button" class="btn btn-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#confirmDeleteSelectedModal">
                <i class="fa-solid fa-trash me-1"></i> Delete Selected
            </button>
            <button type="button" class="btn btn-danger btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal">
                <i class="fa-solid fa-xmark me-1"></i> Remove All
            </button>
        </div>';
    }
    else
    {
        $bookmark_cards = '
        <div class="col-12 text-center py-5 text-muted">
            <div class="mb-3 animate-bounce" style="font-size: 4rem; color: rgba(108,117,125,0.3);"><i class="fa-regular fa-bookmark"></i></div>
            <h5 class="mb-2 fw-semibold">Your bookmark list is empty</h5>
            <p class="mb-4">Start building your collection by bookmarking your favorite torrents.</p>
            <a href="browse.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Torrents
            </a>
        </div>
		<style>
/* Анимация «прыжка» для иконки */
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.animate-bounce {
    animation: bounce 1.5s infinite;
}

/* Плавное появление текста */
.fade-in {
    opacity: 0;
    transform: translateY(10px);
    animation: fadeInUp 0.8s forwards;
}
.fade-in.delay-1 { animation-delay: 0.3s; }
.fade-in.delay-2 { animation-delay: 0.6s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>';
		
		
		
		
		
		
		
		
		
		
        $remove_options = '';
    }

    $bookmarks_html = "
<div class=\"container\">
	<div class=\"row\">
        <div class=\"col-lg-3 mb-4\">{$usercpnav}</div>
        <div class=\"col-lg-9\">
		
		<div class=\"page-header mb-4\">
        <h2 class=\"h3 mb-2 text-dark\">
            <i class=\"fa-solid fa-bookmark me-2\"></i>My Bookmarks
        </h2>
        <p class=\"text-muted mb-0\">Manage your bookmarked torrents • {$total} total</p>
    </div>
		
		
		
            <form method=\"post\" action=\"usercp.php\" id=\"bookmarksForm\">
                <input type=\"hidden\" name=\"my_post_key\" value=\"{$mybb->post_code}\">
                <input type=\"hidden\" name=\"action\" value=\"do_removebookmarks\">
                <div class=\"row g-4\">{$bookmark_cards}</div>
                {$remove_options}
                ".($multipage ? "<div class=\"mt-3\">{$multipage}</div>" : "")."
            </form>
        </div>
    </div>
</div>

<!-- Модал Delete Selected -->
<div class=\"modal fade\" id=\"confirmDeleteSelectedModal\" tabindex=\"-1\" aria-labelledby=\"confirmDeleteSelectedLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog modal-dialog-centered\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"confirmDeleteSelectedLabel\">Confirm Deletion</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
      </div>
      <div class=\"modal-body\">
        Are you sure you want to delete <span id=\"selectedCount\">0</span> selected bookmark(s)? This cannot be undone.
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
        <button type=\"button\" class=\"btn btn-danger\" id=\"confirmDeleteSelectedBtn\">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Модал Remove All -->
<div class=\"modal fade\" id=\"confirmDeleteAllModal\" tabindex=\"-1\" aria-labelledby=\"confirmDeleteAllLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog modal-dialog-centered\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"confirmDeleteAllLabel\">Confirm Deletion</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>
      </div>
      <div class=\"modal-body\">
        Are you sure you want to remove all bookmarks? This cannot be undone.
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cancel</button>
        <button type=\"button\" class=\"btn btn-danger\" id=\"confirmDeleteAllBtn\">Yes, Remove All</button>
      </div>
    </div>
  </div>
</div>";



?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var bookmarksForm = document.getElementById("bookmarksForm");
    var selectedModal = new bootstrap.Modal(document.getElementById("confirmDeleteSelectedModal"));
    var confirmSelectedBtn = document.getElementById("confirmDeleteSelectedBtn");
    var selectedCountSpan = document.getElementById("selectedCount");
    var confirmAllBtn = document.getElementById("confirmDeleteAllBtn");

    var cardsContainer = bookmarksForm.querySelector('.row.g-4');

    // CSS для анимации исчезновения
    var style = document.createElement('style');
    style.innerHTML = `
        .fade-out {
            transition: all 0.5s ease;
            opacity: 0;
            transform: scale(0.95);
        }
    `;
    document.head.appendChild(style);

    function showToast(message, isSuccess = true) {
        const toastEl = document.getElementById('toastNotification');
        const toastMsg = document.getElementById('toastMessage');

        toastEl.className = `toast align-items-center text-bg-${isSuccess ? 'success' : 'danger'} border-0`;
        toastMsg.textContent = message;

        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    }

   
   
   
   function showEmptyState() {
    // Рендерим пустое состояние
    cardsContainer.innerHTML = `
    <div class="col-12 text-center py-5 text-muted fade-in">
        <div class="mb-3 animate-bounce" style="font-size: 4rem; color: rgba(108,117,125,0.3);">
            <i class="fa-regular fa-bookmark"></i>
        </div>
        <h5 class="mb-2 fw-semibold">Your bookmark list is empty</h5>
        <p class="mb-4">Start building your collection by bookmarking your favorite torrents.</p>
        <a href="browse.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Torrents
        </a>
    </div>`;

    // Добавляем класс, чтобы CSS мог скрывать пагинацию
    cardsContainer.classList.add('empty-state');

    // Скрываем пагинацию даже если она появится чуть позже
    const hidePagination = () => {
        const pagination = document.querySelector('nav.pagination, .mt-3');
        if(pagination) {
            pagination.style.display = 'none';
        } else {
            // Проверяем снова через 50ms, если пагинация ещё не появилась
            setTimeout(hidePagination, 50);
        }
    };
    hidePagination();
}

   
   
   
   
   
   
   

    // Delete Selected
    document.querySelector('[data-bs-target="#confirmDeleteSelectedModal"]').addEventListener('click', function() {
        var checked = bookmarksForm.querySelectorAll('input[type="checkbox"]:checked').length;
        if(checked === 0) { showToast('Please select at least one bookmark.', false); return; }
        selectedCountSpan.textContent = checked;
        selectedModal.show();
    });

    confirmSelectedBtn.addEventListener('click', function() {
        var formData = new FormData(bookmarksForm);
        formData.set('do','delete');

        fetch('usercp.php', { method:'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                bookmarksForm.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                    var card = cb.closest('.col-xl-3, .col-lg-4, .col-md-6');
                    card.classList.add('fade-out');
                    setTimeout(() => card.remove(), 500);
                });
                selectedModal.hide();
                showToast(data.message, true);

                // Если больше нет карточек, показываем пустое состояние
                setTimeout(() => {
                    if(cardsContainer.children.length === 0) showEmptyState();
                }, 600);
            } else {
                showToast(data.message, false);
            }
        });
    });

    // Remove All
    confirmAllBtn.addEventListener('click', function() {
        var formData = new FormData();
        formData.append('action','removebookmarks');
        formData.append('my_post_key','<?php echo $mybb->post_code; ?>');

        fetch('usercp.php', { method:'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                document.querySelectorAll('.col-xl-3, .col-lg-4, .col-md-6').forEach(card => {
                    card.classList.add('fade-out');
                    setTimeout(() => card.remove(), 500);
                });
                var allModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteAllModal'));
                if(allModal) allModal.hide();
                showToast(data.message, true);

                // Показ пустого состояния
                setTimeout(() => showEmptyState(), 600);
            } else {
                showToast(data.message, false);
            }
        });
    });
});
</script>
<?


stdhead('My Bookmarks - '.$SITENAME);
build_breadcrumb();
echo $bookmarks_html;

}





































if($mybb->input['action'] == "manage_files")
{
    $plugins->run_hooks("usercp_manage_files_start");

    // Do Multi Pages
    $query = $db->sql_query("
        SELECT COUNT(*) as files_count
        FROM comment_files 
        WHERE user_id = {$CURUSER['id']}
    ");
    $filecount = $db->fetch_field($query, "files_count");
    
    if(!$f_filesperpage || (int)$f_filesperpage < 1)
    {
        $f_filesperpage = 15; // Кратно 3 для 3 колонок
    }

    $perpage = $f_filesperpage;
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    if($page > 0)
    {
        $start = ($page-1) * $perpage;
        $pages = $filecount / $perpage;
        $pages = ceil($pages);
        if($page > $pages || $page <= 0)
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
    
    $multipage = multipage($filecount, $perpage, $page, "usercp.php?action=manage_files");
    
    // Fetch user files
    $query = $db->sql_query_prepared("
    SELECT cf.*, 
           c.torrent AS torrentid,   
           p.pid AS postid,         
           p.tid AS thread_id,
           p.subject AS post_subject
    FROM comment_files AS cf
    LEFT JOIN comments AS c 
        ON c.id = cf.comment_id
    LEFT JOIN tsf_posts AS p 
        ON p.pid = cf.post_id
    WHERE cf.user_id = ?
    ORDER BY cf.uploaded_at DESC
    LIMIT ?, ?", [(int)$CURUSER['id'], (int)$start, (int)$perpage]);
   
   
   
   
   
   
   
    
    if ($db->num_rows($query->result) > 0)
    {
        $filelist = '<div class="row g-4">';
        
        while ($file = $db->fetch_array($query->result))
        {
            $uploaded = $file['uploaded_at'];
            $file_size = mksize($file['file_size']);
            $file_type_icon = get_file_type_icon($file['file_type']);
			
			
			$postlink = get_post_link($file['postid'], $file['thread_id']);
			
			
            
            $filelist .= '
            <div class="col-xl-4 col-lg-4 col-md-6 col-sm-6">
                <div class="card file-card h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="file-checkbox">
                            <input type="checkbox" class="form-check-input file-checkbox-input" 
                                   name="file_ids[]" value="'.$file['id'].'" 
                                   id="file_'.$file['id'].'">
                            <label for="file_'.$file['id'].'" class="file-checkbox-label"></label>
                        </div>
                        
                        <div class="file-preview-wrapper text-center mb-3">
                            '.get_file_preview($file).'
                        </div>
                        
                        <div class="file-info text-center mb-3 position-relative">
                            <h6 class="file-name text-truncate mb-2 fw-semibold" 
                                title="'.htmlspecialchars_uni($file['file_name']).'">
                                '.htmlspecialchars_uni(cutename($file['file_name'], 40)).'
                            </h6>
                            
                            <div class="file-meta d-flex justify-content-center align-items-center gap-3 text-muted small mb-2">
                                <span class="file-size">
                                    <i class="fa-solid fa-weight-hanging me-1"></i>'.$file_size.'
                                </span>
                                <span class="file-type">
                                    '.$file_type_icon.'
                                </span>
                            </div>
                            
                            <div class="file-date small text-muted">
                                <i class="fa-regular fa-clock me-1"></i>'.$uploaded.'
                            </div>
							
							
							
							
							<!-- Бейджи для связи с комментариями, новостями, торрентами, постами, ЛС -->
    <div class="file-badges mt-2">
     
	 
	 '.($file['comment_id'] 
    ? '<a href="'.get_comment_link($file['comment_id'], $file['torrentid']).'#pid'.$file['comment_id'].'" 
         class="badge text-success bg-success bg-opacity-10 me-1" 
         data-bs-toggle="tooltip" 
         title="View Comment #'.$file['comment_id'].'">
         <i class="fa-solid fa-comment me-1"></i>
         Comment #'.$file['comment_id'].'
       </a>' 
    : 
    '').'
	 
	 
	 
        '.($file['news_id'] ? '<a href="news.php?id='.$file['news_id'].'" class="badge text-warning bg-warning bg-opacity-10 me-1" data-bs-toggle="tooltip" title="View News #'.$file['news_id'].'"><i class="fa-solid fa-newspaper me-1"></i>News #'.$file['news_id'].'</a>' : '').'
        
		
		
		'.($file['torrent_id'] 
    ? '<a href="'.get_torrent_link($file['torrent_id']).'" 
         class="badge text-info bg-info bg-opacity-10 me-1" 
         data-bs-toggle="tooltip" 
         title="View Torrent #'.$file['torrent_id'].'">
         <i class="fa-solid fa-download me-1"></i>
         Torrent #'.$file['torrent_id'].'
       </a>' 
    : ''
).'
		
		
		
	
		
		
		
		
		'.($file['postid'] 
    ? '<a href="'.$postlink.'#pid'.$file['postid'].'" 
         class="badge text-primary bg-primary bg-opacity-10 me-1" 
         data-bs-toggle="tooltip" 
         title="View Post #'.$file['postid'].'">
         <i class="fa-solid fa-file-lines me-1"></i>
         Post #'.$file['postid'].'
       </a>' 
    : ''
).'
		
       
		
		
		
        '.($file['messages_id'] ? '<a href="messages.php?id='.$file['messages_id'].'" class="badge text-secondary bg-secondary bg-opacity-10 me-1" data-bs-toggle="tooltip" title="View Message #'.$file['messages_id'].'"><i class="fa-solid fa-envelope-open-text me-1"></i>Message #'.$file['messages_id'].'</a>' : '').'
    </div>
							
							
							
							
							
							
                        </div>
						
						
						
						
	
						
						
						
						
						
						
                        
                        <div class="file-actions d-grid">
                            <a href="'.htmlspecialchars_uni($file['file_url']).'" 
                               target="_blank" 
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               title="View file">
                                <i class="fa-solid fa-eye me-2"></i>View File
                            </a>
                        </div>
                    </div>
                </div>
            </div>';
        }
        
        $filelist .= '</div>';
        
        // Provide remove options
        $remove_options = '
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
            <div class="d-flex flex-wrap gap-2">
                
				<button type="button" class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
    <i class="fa-solid fa-trash me-2"></i>Delete Selected
</button>
                
                
				
				
				<a href="#" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteAllModal">
                   <i class="fa-solid fa-broom me-2"></i>Delete All
                </a>
				
				
				
				
				
				
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">Selected: <span id="selected-count" class="fw-semibold">0</span></span>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="select-all">
                    <label class="form-check-label text-muted" for="select-all">Select all</label>
                </div>
            </div>
        </div>';
    }
    else
    {
        $filelist = '
        <div class="text-center py-5">
            <div class="empty-state">
                <i class="fa-regular fa-folder-open fa-4x text-muted mb-4"></i>
                <h4 class="text-muted mb-3">No files uploaded yet</h4>
                <p class="text-muted mb-4">You haven\'t uploaded any files yet.</p>
            </div>
        </div>';
        
        $remove_options = '';
    }

    $plugins->run_hooks("usercp_manage_files_end");
    
    $files_html = '
    <div class="container">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                '.$usercpnav.'
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="page-header mb-4">
                    <h2 class="h3 mb-2 text-dark">
                        <i class="fa-solid fa-images me-2"></i>My Files
                    </h2>
                    <p class="text-muted mb-0">Manage your uploaded files • '.$filecount.' total</p>
                </div>
                
                <form id="fileDeleteForm" action="usercp.php" method="post" name="input">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                    <input type="hidden" name="action" value="do_remove_files" />
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fa-solid fa-list me-2"></i>File Manager
                                </h5>
                                <span class="badge bg-primary">'.$filecount.' files</span>
                            </div>
                        </div>
                        
                        <div class="card-body p-4">
                            '.$filelist.'
                        </div>
                        
                        '.($remove_options ? '
                        <div class="card-footer bg-light py-3">
                            '.$remove_options.'
                        </div>' : '').'
                    </div>
                </form>
				
				
				
				<!-- Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete the selected files? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger" form="fileDeleteForm">Yes, Delete</button>
		
		
		
      </div>
    </div>
  </div>
</div>






<!-- Modal for delete ALL files -->
<div class="modal fade" id="confirmDeleteAllModal" tabindex="-1" aria-labelledby="confirmDeleteAllModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteAllModalLabel">
          <i class="fa-solid fa-triangle-exclamation me-2"></i> Confirm Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <strong>ALL your files</strong>? This action <strong>cannot</strong> be undone!
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="usercp.php?action=remove_all_files&amp;my_post_key='.$mybb->post_code.'" class="btn btn-danger">
          Yes, Delete All
        </a>
      </div>
    </div>
  </div>
</div>






				
                
                '.($multipage ? '
                <div class="mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            '.$multipage.'
                        </ul>
                    </nav>
                </div>' : '').'
            </div>
        </div>
    </div>';

    stdhead('My Files - '.$SITENAME);
    
    echo '
    <style>
    .file-card {
        transition: all 0.3s ease;
        border-radius: 12px;
        overflow: hidden;
    }
    .file-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .file-preview-wrapper {
        position: relative;
        height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    .file-preview {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
        border-radius: 8px;
    }
    .file-checkbox {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 2;
    }
    .file-checkbox-input {
        opacity: 0;
        position: absolute;
    }
    .file-checkbox-label {
        width: 22px;
        height: 22px;
        border: 2px solid #dee2e6;
        border-radius: 5px;
        background: white;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .file-checkbox-input:checked + .file-checkbox-label {
        background: #0d6efd;
        border-color: #0d6efd;
    }
    .file-checkbox-input:checked + .file-checkbox-label::after {
        content: "✓";
        color: white;
        font-size: 0.9rem;
        font-weight: bold;
        display: block;
        text-align: center;
        line-height: 18px;
    }
    .file-name {
        font-weight: 600;
        color: #2c3e50;
        line-height: 1.4;
        font-size: 1rem;
    }
    .file-info {
        margin-bottom: 1rem;
    }
    .file-meta {
        font-size: 0.9rem;
    }
    .empty-state {
        padding: 3rem 1rem;
    }
    .card-body {
        padding: 1.5rem;
    }
    .btn {
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
    </style>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Select all functionality
        const selectAll = document.getElementById("select-all");
        const fileCheckboxes = document.querySelectorAll(".file-checkbox-input");
        const selectedCount = document.getElementById("selected-count");
        
        selectAll?.addEventListener("change", function() {
            fileCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
        
        fileCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", updateSelectedCount);
        });
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll(".file-checkbox-input:checked").length;
            if (selectedCount) selectedCount.textContent = selected;
            if (selectAll) {
                selectAll.checked = selected === fileCheckboxes.length && fileCheckboxes.length > 0;
            }
        }
        
        // Initialize tooltips
        if (typeof $ !== "undefined") {
            $(\'[data-bs-toggle="tooltip"]\').tooltip();
        }
        
        // Smooth animations
        document.querySelectorAll(".file-card").forEach(card => {
            card.style.opacity = "0";
            card.style.transform = "translateY(20px)";
        });
        
        setTimeout(() => {
            document.querySelectorAll(".file-card").forEach((card, index) => {
                setTimeout(() => {
                    card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
                    card.style.opacity = "1";
                    card.style.transform = "translateY(0)";
                }, index * 100);
            });
        }, 100);
    });
    </script>';
    
    build_breadcrumb();
    echo $files_html;
    //stdfoot();
}

// Helper functions
function get_file_preview($file) {
    if (strpos($file['file_type'], 'image/') === 0) {
        return '<img src="'.htmlspecialchars_uni($file['file_url']).'" 
                  class="file-preview"
                  alt="'.htmlspecialchars_uni($file['file_name']).'"
                  style="max-width: 160px; max-height: 160px;"
                  onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">
                <div class="file-icon-fallback" style="display: none; height: 100%; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-image fa-3x text-muted"></i>
                </div>';
    } else {
        return '<div class="file-icon" style="height: 100%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-file fa-3x text-muted"></i>
                </div>';
    }
}

function get_file_type_icon($file_type) {
    $icons = [
        'image/' => 'fa-image',
        'video/' => 'fa-video',
        'audio/' => 'fa-music',
        'text/' => 'fa-file-lines',
        'application/pdf' => 'fa-file-pdf',
        'application/zip' => 'fa-file-zipper',
        'application/' => 'fa-file'
    ];
    
    foreach ($icons as $pattern => $icon) {
        if (strpos($file_type, $pattern) === 0) {
            return '<i class="fa-solid '.$icon.' me-1"></i>';
        }
    }
    
    return '<i class="fa-solid fa-file me-1"></i>';
}










// Обработка удаления файлов с заменой [img]...[/img]
if($mybb->input['action'] == "do_remove_files")
{
    //verify_post_check($mybb->get_input('my_post_key'));

    $deleted_count = 0;

    if(isset($mybb->input['file_ids']) && is_array($mybb->input['file_ids']))
    {
        foreach($mybb->input['file_ids'] as $file_id)
        {
            $file_id = (int)$file_id;

           

             $query = $db->sql_query_prepared("
             SELECT 
                cf.id, cf.file_path, cf.comment_id, cf.news_id, cf.torrent_id, cf.post_id, cf.messages_id,
                c.text AS comment_text,
                n.body AS news_text,
                t.descr AS torrent_description,
                p.message AS post_message,
                pm.message AS pm_message
                FROM comment_files cf
                LEFT JOIN comments c ON c.id = cf.comment_id
                LEFT JOIN news n ON n.id = cf.news_id
                LEFT JOIN torrents t ON t.id = cf.torrent_id
                LEFT JOIN tsf_posts p ON p.pid = cf.post_id
                LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
                WHERE cf.id = ? AND cf.user_id = ?", [(int)$file_id, (int)$CURUSER['id']]);






            if($db->num_rows($query) > 0)
            {
                $file = $db->fetch_array($query);

                if(!empty($file['file_path']) && file_exists($file['file_path'])) {
                    @unlink($file['file_path']);
                }

                $filename = basename($file['file_path']);
                $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\[]*\[\/img\]/i';

                // Обновляем все тексты
                $updates = [
                    ['field'=>'text','table'=>'comments','id_field'=>'id','id'=>$file['comment_id'],'content'=>$file['comment_text']],
                    ['field'=>'body','table'=>'news','id_field'=>'id','id'=>$file['news_id'],'content'=>$file['news_text']],
                    ['field'=>'descr','table'=>'torrents','id_field'=>'id','id'=>$file['torrent_id'],'content'=>$file['torrent_description']],
                    ['field'=>'message','table'=>'tsf_posts','id_field'=>'pid','id'=>$file['post_id'],'content'=>$file['post_message']],
                    ['field'=>'message','table'=>'privatemessages','id_field'=>'pmid','id'=>$file['messages_id'],'content'=>$file['pm_message']],
                ];

                foreach($updates as $u){
                    if(!empty($u['id'])){
                        $new_text = preg_replace($image_pattern,'[Image Deleted]',$u['content']);
                        if($new_text !== $u['content']){
                            $db->sql_query("
                                UPDATE {$u['table']}
                                SET {$u['field']} = '".$db->escape_string($new_text)."'
                                WHERE {$u['id_field']} = ".(int)$u['id']."
                            ");
                        }
                    }
                }

                $db->sql_query("DELETE FROM comment_files WHERE id = {$file_id} AND user_id = {$CURUSER['id']}");
                $deleted_count++;
            }
        }

        // Редирект с GET-параметром msg
        header("Location: usercp.php?action=manage_files&msg=".urlencode("Successfully deleted {$deleted_count} files"));
        exit;
    }
    else
    {
        header("Location: usercp.php?action=manage_files&msg=".urlencode("No files selected for deletion"));
        exit;
    }
}

// Удаление всех файлов
if($mybb->input['action'] == "remove_all_files")
{
    verify_post_check($mybb->get_input('my_post_key'));

    $query = $db->sql_query_prepared("
    SELECT 
        cf.id, cf.file_path, cf.comment_id, cf.news_id, cf.torrent_id, cf.post_id, cf.messages_id,
        c.text AS comment_text,
        n.body AS news_text,
        t.descr AS torrent_description,
        p.message AS post_message,
        pm.message AS pm_message
    FROM comment_files cf
    LEFT JOIN comments c ON c.id = cf.comment_id
    LEFT JOIN news n ON n.id = cf.news_id
    LEFT JOIN torrents t ON t.id = cf.torrent_id
    LEFT JOIN tsf_posts p ON p.pid = cf.post_id
    LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
    WHERE cf.user_id = ?", [(int)$CURUSER['id']]);

    $deleted_count = 0;

    while($file = $db->fetch_array($query))
    {
        if(!empty($file['file_path']) && file_exists($file['file_path'])) {
            @unlink($file['file_path']);
        }

        $filename = basename($file['file_path']);
        $image_pattern = '/\[img\][^\[]*' . preg_quote($filename, '/') . '[^\[]*\[\/img\]/i';

        $updates = [
            ['field'=>'text','table'=>'comments','id_field'=>'id','id'=>$file['comment_id'],'content'=>$file['comment_text']],
            ['field'=>'body','table'=>'news','id_field'=>'id','id'=>$file['news_id'],'content'=>$file['news_text']],
            ['field'=>'descr','table'=>'torrents','id_field'=>'id','id'=>$file['torrent_id'],'content'=>$file['torrent_description']],
            ['field'=>'message','table'=>'tsf_posts','id_field'=>'pid','id'=>$file['post_id'],'content'=>$file['post_message']],
            ['field'=>'message','table'=>'privatemessages','id_field'=>'pmid','id'=>$file['messages_id'],'content'=>$file['pm_message']],
        ];

        foreach($updates as $u){
            if(!empty($u['id'])){
                $new_text = preg_replace($image_pattern,'[Image Deleted]',$u['content']);
                if($new_text !== $u['content']){
                    $db->sql_query("
                        UPDATE {$u['table']}
                        SET {$u['field']} = '".$db->escape_string($new_text)."'
                        WHERE {$u['id_field']} = ".(int)$u['id']."
                    ");
                }
            }
        }

        $db->sql_query("DELETE FROM comment_files WHERE id = {$file['id']} AND user_id = {$CURUSER['id']}");
        $deleted_count++;
    }

    header("Location: usercp.php?action=manage_files&msg=".urlencode("Successfully deleted all {$deleted_count} files"));
    exit;
}




























if($mybb->input['action'] == "acceptrequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND touid='.(int)$CURUSER['id']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error('invalid_request');
	}

	$plugins->run_hooks("usercp_acceptrequest_start");

	$user = get_user($request['uid']);
	if(!empty($user))
	{
		// We want to add us to this user's buddy list
		if($user['buddylist'] != '')
		{
			$user['buddylist'] = explode(',', $user['buddylist']);
		}
		else
		{
			$user['buddylist'] = array();
		}

		$user['buddylist'][] = (int)$CURUSER['id'];

		// Now we have the new list, so throw it all back together
		$new_list = implode(",", $user['buddylist']);

		// And clean it up a little to ensure there is no possibility of bad values
		$new_list = preg_replace("#,{2,}#", ",", $new_list);
		$new_list = preg_replace("#[^0-9,]#", "", $new_list);

		if(my_substr($new_list, 0, 1) == ",")
		{
			$new_list = my_substr($new_list, 1);
		}
		if(my_substr($new_list, -1) == ",")
		{
			$new_list = my_substr($new_list, 0, my_strlen($new_list)-2);
		}

		$user['buddylist'] = $db->escape_string($new_list);

		$db->update_query("users", array('buddylist' => $user['buddylist']), "id='".(int)$user['id']."'");


		// We want to add the user to our buddy list
		if($CURUSER['buddylist'] != '')
		{
			$CURUSER['buddylist'] = explode(',', $CURUSER['buddylist']);
		}
		else
		{
			$CURUSER['buddylist'] = array();
		}

		$CURUSER['buddylist'][] = (int)$request['uid'];

		// Now we have the new list, so throw it all back together
		$new_list = implode(",", $CURUSER['buddylist']);

		// And clean it up a little to ensure there is no possibility of bad values
		$new_list = preg_replace("#,{2,}#", ",", $new_list);
		$new_list = preg_replace("#[^0-9,]#", "", $new_list);

		if(my_substr($new_list, 0, 1) == ",")
		{
			$new_list = my_substr($new_list, 1);
		}
		if(my_substr($new_list, -1) == ",")
		{
			$new_list = my_substr($new_list, 0, my_strlen($new_list)-2);
		}

		$CURUSER['buddylist'] = $db->escape_string($new_list);

		$db->update_query("users", array('buddylist' => $CURUSER['buddylist']), "id='".(int)$CURUSER['id']."'");

		
		require_once INC_PATH . '/functions_pm.php';
		
		$pm = array(
			'subject' => sprintf ($lang->usercp['buddyrequest_accepted_request_message']),
			'message' => sprintf ($lang->usercp['buddyrequest_accepted_request']),
			'touid' => $user['id']
		);

		send_pm($pm, $CURUSER['id'], true);
		

		$db->delete_query('buddyrequests', 'id='.(int)$request['id']);
	}
	else
	{
		error('user_doesnt_exist');
	}

	$plugins->run_hooks("usercp_acceptrequest_end");

	redirect("usercp.php?action=editlists", $lang->usercp['buddyrequest_accepted']);
}




elseif($mybb->input['action'] == "declinerequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND touid='.(int)$CURUSER['id']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error('invalid_request');
	}

	$plugins->run_hooks("usercp_declinerequest_start");

	$user = get_user($request['uid']);
	if(!empty($user))
	{
		$db->delete_query('buddyrequests', 'id='.(int)$request['id']);
	}
	else
	{
		error('user_doesnt_exist');
	}

	$plugins->run_hooks("usercp_declinerequest_end");

	redirect("usercp.php?action=editlists", $lang->usercp['buddyrequest_declined']);
}




elseif($mybb->input['action'] == "cancelrequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND uid='.(int)$CURUSER['id']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error('invalid_request');
	}

	$plugins->run_hooks("usercp_cancelrequest_start");

	$db->delete_query('buddyrequests', 'id='.(int)$request['id']);

	$plugins->run_hooks("usercp_cancelrequest_end");

	redirect("usercp.php?action=editlists", $lang->usercp['buddyrequest_cancelled']);
}




if($mybb->input['action'] == "do_editlists")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_editlists_start");

	$existing_users = array();
	$selected_list = array();
	if($mybb->get_input('manage') == "ignored")
	{
		if($CURUSER['ignorelist'])
		{
			$existing_users = explode(",", $CURUSER['ignorelist']);
		}

		if($CURUSER['buddylist'])
		{
			// Create a list of buddies...
			$selected_list = explode(",", $CURUSER['buddylist']);
		}
	}
	else
	{
		if($CURUSER['buddylist'])
		{
			$existing_users = explode(",", $CURUSER['buddylist']);
		}

		if($CURUSER['ignorelist'])
		{
			// Create a list of ignored users
			$selected_list = explode(",", $CURUSER['ignorelist']);
		}
	}

	$error_message = "";
	$message = "";

	// Adding one or more users to this list
	if($mybb->get_input('add_username'))
	{
		// Split up any usernames we have
		$found_users = 0;
		$adding_self = false;
		$users = explode(",", $mybb->get_input('add_username'));
		$users = array_map("trim", $users);
		$users = array_unique($users);
		foreach($users as $key => $username)
		{
			if(empty($username))
			{
				unset($users[$key]);
				continue;
			}

			if(my_strtoupper($CURUSER['username']) == my_strtoupper($username))
			{
				$adding_self = true;
				unset($users[$key]);
				continue;
			}
			$users[$key] = $db->escape_string($username);
		}

		// Get the requests we have sent that are still pending
		$query = $db->simple_select('buddyrequests', 'touid', 'uid='.(int)$CURUSER['id']);
		$requests = array();
		while($req = $db->fetch_array($query))
		{
			$requests[$req['touid']] = true;
		}

		// Get the requests we have received that are still pending
		$query = $db->simple_select('buddyrequests', 'uid', 'touid='.(int)$CURUSER['id']);
		$requests_rec = array();
		while($req = $db->fetch_array($query))
		{
			$requests_rec[$req['uid']] = true;
		}

		$sent = false;

		// Fetch out new users
		if(count($users) > 0)
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
			$query = $db->simple_select("users", "id,buddyrequestsauto,buddyrequestspm", "{$field} IN ('".my_strtolower(implode("','", $users))."')");
			while($user = $db->fetch_array($query))
			{
				++$found_users;

				// Make sure we're not adding a duplicate
				if(in_array($user['id'], $existing_users) || in_array($user['id'], $selected_list))
				{
					if($mybb->get_input('manage') == "ignored")
					{
						$error_message = "ignore";
					}
					else
					{
						$error_message = "buddy";
					}

					// On another list?
					$string = "users_already_on_".$error_message."_list";
					if(in_array($user['id'], $selected_list))
					{
						$string .= "_alt";
					}

					$error_message = $lang->$string;
					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				if(isset($requests[$user['id']]))
				{
					if($mybb->get_input('manage') != "ignored")
					{
						$error_message = 'users_already_sent_request';
					}
					elseif($mybb->get_input('manage') == "ignored")
					{
						$error_message = 'users_already_sent_request_alt';
					}

					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				if(isset($requests_rec[$user['id']]))
				{
					if($mybb->get_input('manage') != "ignored")
					{
						$error_message = 'users_already_rec_request';
					}
					elseif($mybb->get_input('manage') == "ignored")
					{
						$error_message = 'users_already_rec_request_alt';
					}

					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				// Do we have auto approval set to On?
				if($user['buddyrequestsauto'] == 1 && $mybb->get_input('manage') != "ignored")
				{
					$existing_users[] = $user['id'];

					$pm = array(
						'subject' => 'buddyrequest_new_buddy',
						'message' => 'buddyrequest_new_buddy_message',
						'touid' => $user['id'],
						'receivepms' => (int)$user['buddyrequestspm'],
						'language' => $user['language'],
						'language_file' => 'usercp'
					);

					//send_pm($pm);
					require_once INC_PATH . '/functions_pm.php';
					
					send_pm ($user['id'], sprintf ($lang->usercp['buddyrequest_new_buddy_message']), $lang->usercp['buddyrequest_new_buddy'], $CURUSER['id']);
					
					
					
				}
				elseif($user['buddyrequestsauto'] != 1 && $mybb->get_input('manage') != "ignored")
				{
					// Send request
					$id = $db->insert_query('buddyrequests', array('uid' => (int)$CURUSER['id'], 'touid' => (int)$user['id'], 'date' => TIMENOW));

					$pm = [
                        'subject'       => $lang->usercp['buddyrequest_received'],
                        'message'       => sprintf($lang->usercp['buddyrequest_received_message'], $CURUSER['username']),
                        'touid'         => (int)$user['id'],
                        'fromid'        => (int)$CURUSER['id'],
                        'receivepms'    => (int)$user['buddyrequestspm'],
                        'language'      => $user['language'],
                        'language_file' => 'usercp'
                    ];

                    require_once INC_PATH . '/functions_pm.php';
                    send_pm($pm);


					$sent = true;
				}
				elseif($mybb->get_input('manage') == "ignored")
				{
					$existing_users[] = $user['id'];
				}
			}
		}

		if($found_users < count($users))
		{
			if($error_message)
			{
				$error_message .= "<br />";
			}

			$error_message .= $lang->usercp['invalid_user_selected'];
		}

		if(($adding_self != true || ($adding_self == true && count($users) > 0)) && ($error_message == "" || count($users) > 1))
		{
			if($mybb->get_input('manage') == "ignored")
			{
				$message = $lang->usercp['users_added_to_ignore_list'];
			}
			else
			{
				$message = $lang->usercp['users_added_to_buddy_list'];
			}
		}

		if($adding_self == true)
		{
			if($mybb->get_input('manage') == "ignored")
			{
				$error_message = $lang->usercp['cant_add_self_to_ignore_list'];
			}
			else
			{
				$error_message = $lang->usercp['cant_add_self_to_buddy_list'];
			}
		}

		if(count($existing_users) == 0)
		{
			$message = "";

			if($sent === true)
			{
				$message = $lang->usercp['buddyrequests_sent_success'];
			}
		}
	}

	// Removing a user from this list
	elseif($mybb->get_input('delete', MyBB::INPUT_INT))
	{
		// Check if user exists on the list
		$key = array_search($mybb->get_input('delete', MyBB::INPUT_INT), $existing_users);
		if($key !== false)
		{
			unset($existing_users[$key]);
			$user = get_user($mybb->get_input('delete', MyBB::INPUT_INT));
			if(!empty($user))
			{
				// We want to remove us from this user's buddy list
				if($user['buddylist'] != '')
				{
					$user['buddylist'] = explode(',', $user['buddylist']);
				}
				else
				{
					$user['buddylist'] = array();
				}

				$key = array_search($mybb->get_input('delete', MyBB::INPUT_INT), $user['buddylist']);
				unset($user['buddylist'][$key]);

				// Now we have the new list, so throw it all back together
				$new_list = implode(",", $user['buddylist']);

				// And clean it up a little to ensure there is no possibility of bad values
				$new_list = preg_replace("#,{2,}#", ",", $new_list);
				$new_list = preg_replace("#[^0-9,]#", "", $new_list);

				if(my_substr($new_list, 0, 1) == ",")
				{
					$new_list = my_substr($new_list, 1);
				}
				if(my_substr($new_list, -1) == ",")
				{
					$new_list = my_substr($new_list, 0, my_strlen($new_list)-2);
				}

				$user['buddylist'] = $db->escape_string($new_list);

				$db->update_query("users", array('buddylist' => $user['buddylist']), "id='".(int)$user['uid']."'");
			}

			if($mybb->get_input('manage') == "ignored")
			{
				$message = $lang->usercp['removed_from_ignore_list'];
			}
			else
			{
				$message = $lang->usercp['removed_from_buddy_list'];
			}
			$user['username'] = htmlspecialchars_uni($user['username']);
			$message = sprintf($message, $user['username']);
		}
	}

	// Now we have the new list, so throw it all back together
	$new_list = implode(",", $existing_users);

	// And clean it up a little to ensure there is no possibility of bad values
	$new_list = preg_replace("#,{2,}#", ",", $new_list);
	$new_list = preg_replace("#[^0-9,]#", "", $new_list);

	if(my_substr($new_list, 0, 1) == ",")
	{
		$new_list = my_substr($new_list, 1);
	}
	if(my_substr($new_list, -1) == ",")
	{
		$new_list = my_substr($new_list, 0, my_strlen($new_list)-2);
	}

	// And update
	$user = array();
	if($mybb->get_input('manage') == "ignored")
	{
		$user['ignorelist'] = $db->escape_string($new_list);
		$CURUSER['ignorelist'] = $user['ignorelist'];
	}
	else
	{
		$user['buddylist'] = $db->escape_string($new_list);
		$CURUSER['buddylist'] = $user['buddylist'];
	}

	$db->update_query("users", $user, "id='".$CURUSER['id']."'");

	$plugins->run_hooks("usercp_do_editlists_end");

	// Ajax based request, throw new list to browser
	if(!empty($mybb->input['ajax']))
	{
		if($mybb->get_input('manage') == "ignored")
		{
			$list = "ignore";
		}
		else
		{
			$list = "buddy";
		}

		$message_js = '';
		if($message)
		{
			$message_js = "$.jGrowl('{$message}', {theme:'jgrowl_success'});";
		}

		if($error_message)
		{
			$message_js .= " $.jGrowl('{$error_message}', {theme:'jgrowl_error'});";
		}

		if($mybb->get_input('delete', MyBB::INPUT_INT))
		{
			header("Content-type: text/javascript");
			echo "$(\"#".$mybb->get_input('manage')."_".$mybb->get_input('delete', MyBB::INPUT_INT)."\").remove();\n";
			if($new_list == "")
			{
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"0\");\n";
				echo "\$(\"#buddylink\").remove();\n";

				if($mybb->get_input('manage') == "ignored")
				{
					echo "\$(\"#ignore_list\").html(\"<li>{$lang->usercp['ignore_list_empty']}</li>\");\n";
				}
				else
				{
					echo "\$(\"#buddy_list\").html(\"<li>{$lang->usercp['buddy_list_empty']}</li>\");\n";
				}
			}
			else
			{
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"".count(explode(",", $new_list))."\");\n";
			}
			echo $message_js;
			exit;
		}
		$mybb->input['action'] = "editlists";
	}
	else
	{
		if($error_message)
		{
			$message .= "<br />".$error_message;
		}
		redirect("usercp.php?action=editlists#".$mybb->get_input('manage'), $message);
	}
}










if($mybb->input['action'] == "editlists")
{
	$plugins->run_hooks("usercp_editlists_start");

	$timecut = TIMENOW - $wolcutoffmins;

	// Fetch out buddies
	$buddy_count = 0;
	$buddy_list = '';
	if($CURUSER['buddylist'])
	{
		$type = "buddy";
		$query = $db->simple_select("users", "*", "id IN ({$CURUSER['buddylist']})", array("order_by" => "username"));
		
		
		
		while($user = $db->fetch_array($query))
		{
			$user['username'] = htmlspecialchars_uni($user['username']);
			$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['id']);
			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
			{
				$status = "online";
			}
			else
			{
				$status = "offline";
			}
			eval("\$buddy_list .= \"".$templates->get("usercp_editlists_user")."\";");
			++$buddy_count;
		}
	}

	$current_buddies = sprintf($lang->usercp['current_buddies'], $buddy_count);
	if(!$buddy_list)
	{
		eval("\$buddy_list = \"".$templates->get("usercp_editlists_no_buddies")."\";");
	}

	// Fetch out ignore list users
	$ignore_count = 0;
	$ignore_list = '';
	if($CURUSER['ignorelist'])
	{
		$type = "ignored";
		$query = $db->simple_select("users", "*", "id IN ({$CURUSER['ignorelist']})", array("order_by" => "username"));
		while($user = $db->fetch_array($query))
		{
			$user['username'] = htmlspecialchars_uni($user['username']);
			$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['id']);
			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $usergroups['issupermod'] == 'yes') && $user['lastvisit'] != $user['lastactive'])
			{
				$status = "online";
			}
			else
			{
				$status = "offline";
			}
			eval("\$ignore_list .= \"".$templates->get("usercp_editlists_user")."\";");
			++$ignore_count;
		}
	}

	$current_ignored_users = sprintf($lang->usercp['current_ignored_users'], $ignore_count);
	if(!$ignore_list)
	{
		eval("\$ignore_list = \"".$templates->get("usercp_editlists_no_ignored")."\";");
	}

	// If an AJAX request from buddy management, echo out whatever the new list is.
	if($mybb->request_method == "post" && $mybb->input['ajax'] == 1)
	{
		if($mybb->input['manage'] == "ignored")
		{
			echo $ignore_list;
			echo "<script type=\"text/javascript\"> $(\"#ignored_count\").html(\"{$ignore_count}\"); {$message_js}</script>";
		}
		else
		{
			if(isset($sent) && $sent === true)
			{
				$sent_rows = '';
				$query = $db->sql_query("
					SELECT r.*, u.username
					FROM buddyrequests r
					LEFT JOIN users u ON (u.id=r.touid)
					WHERE r.uid=".(int)$CURUSER['id']);

				while($request = $db->fetch_array($query))
				{
					$bgcolor = alt_trow();
					$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int)$request['touid']);
					$request['date'] = my_datee('relative', $request['date']);
					eval("\$sent_rows .= \"".$templates->get("usercp_editlists_sent_request", 1, 0)."\";");
				}

				if($sent_rows == '')
				{
					eval("\$sent_rows = \"".$templates->get("usercp_editlists_no_requests", 1, 0)."\";");
				}

				eval("\$sent_requests = \"".$templates->get("usercp_editlists_sent_requests", 1, 0)."\";");

				echo $sent_requests."<script type=\"text/javascript\">{$message_js}</script>";
			}
			else
			{
				echo $buddy_list;
				echo "<script type=\"text/javascript\"> $(\"#buddy_count\").html(\"{$buddy_count}\"); {$message_js}</script>";
			}
		}
		exit;
	}

	$received_rows = $bgcolor = '';
	$query = $db->sql_query("
		SELECT r.*, u.username
		FROM buddyrequests r
		LEFT JOIN users u ON (u.id=r.uid)
		WHERE r.touid=".(int)$CURUSER['id']);

	while($request = $db->fetch_array($query))
	{
		$bgcolor = alt_trow();
		$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int)$request['id']);
		$request['date'] = my_datee('relative', $request['date']);
		eval("\$received_rows .= \"".$templates->get("usercp_editlists_received_request")."\";");
	}

	if($received_rows == '')
	{
		eval("\$received_rows = \"".$templates->get("usercp_editlists_no_requests")."\";");
	}

	eval("\$received_requests = \"".$templates->get("usercp_editlists_received_requests")."\";");

	$sent_rows = $bgcolor = '';
	$query = $db->sql_query("
		SELECT r.*, u.username
		FROM buddyrequests r
		LEFT JOIN users u ON (u.id=r.touid)
		WHERE r.uid=".(int)$CURUSER['id']);

	while($request = $db->fetch_array($query))
	{
		$bgcolor = alt_trow();
		$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int)$request['touid']);
		$request['date'] = my_datee('relative', $request['date']);
		eval("\$sent_rows .= \"".$templates->get("usercp_editlists_sent_request")."\";");
	}

	if($sent_rows == '')
	{
		eval("\$sent_rows = \"".$templates->get("usercp_editlists_no_requests")."\";");
	}

	eval("\$sent_requests = \"".$templates->get("usercp_editlists_sent_requests")."\";");

	$plugins->run_hooks("usercp_editlists_end");

	eval("\$listpage = \"".$templates->get("usercp_editlists")."\";");
	
	stdhead('title');
	
	build_breadcrumb();
	
	echo $listpage;
}





if($mybb->input['action'] == "do_drafts" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$mybb->input['deletedraft'] = $mybb->get_input('deletedraft', MyBB::INPUT_ARRAY);
	if(empty($mybb->input['deletedraft']))
	{
		error($lang->no_drafts_selected);
	}

	$plugins->run_hooks("usercp_do_drafts_start");

	$pidin = array();
	$tidin = array();

	foreach($mybb->input['deletedraft'] as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] = "'".(int)$id."'";
		}
		elseif($val == "thread")
		{
			$tidin[] = "'".(int)$id."'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->delete_query("tsf_threads", "tid IN ($tidin) AND visible='-2' AND uid='".$CURUSER['id']."'");
		$tidinp = "OR tid IN ($tidin)";
	}
	else
	{
		$tidinp = '';
	}
	if($pidin || $tidinp)
	{
		$pidinq = $tidin = '';
		if($pidin)
		{
			$pidin = implode(",", $pidin);
			$pidinq = "pid IN ($pidin)";
		}
		else
		{
			$pidinq = "1=0";
		}
		$db->delete_query("tsf_posts", "($pidinq $tidinp) AND visible='-2' AND uid='".$CURUSER['id']."'");
	}
	$plugins->run_hooks("usercp_do_drafts_end");
	redirect("usercp.php?action=drafts", $lang->usercp['selected_drafts_deleted']);
}



if($mybb->input['action'] == "drafts")
{
	$plugins->run_hooks("usercp_drafts_start");

	$query = $db->simple_select("tsf_posts", "COUNT(pid) AS draftcount", "visible='-2' AND uid='{$CURUSER['id']}'");
	$draftcount = $db->fetch_field($query, 'draftcount');

	$drafts = $disable_delete_drafts = '';
	$drafts_count = sprintf('Saved Drafts ('.ts_nf($draftcount).')');

	// Show a listing of all of the current 'draft' posts or threads the user has.
	if($draftcount)
	{
		
         $query = $db->sql_query_prepared("
          SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible
          FROM tsf_posts p
          LEFT JOIN tsf_threads t ON (t.tid=p.tid)
          LEFT JOIN tsf_forums f ON (f.fid=t.fid)
          WHERE p.uid = ? AND p.visible = '-2'
          ORDER BY p.dateline DESC, p.pid DESC", [(int)$CURUSER['id']]);



		while($draft = $db->fetch_array($query->result))
		{
			$detail = '';
			$trow = alt_trow();
			if($draft['threadvisible'] == 1) // We're looking at a draft post
			{
				$draft['threadlink'] = get_thread_link($draft['tid']);
				$draft['threadsubject'] = htmlspecialchars_uni($draft['threadsubject']);
				
				$detail = 'Tthread: <a href="'.$draft['threadlink'].'">'.$draft['threadsubject'].'</a>';
				
				
				$editurl = "newreply.php?action=editdraft&amp;pid={$draft['pid']}";
				$id = $draft['pid'];
				$type = "post";
			}
			elseif($draft['threadvisible'] == -2) // We're looking at a draft thread
			{
				$draft['forumlink'] = get_forum_link($draft['fid']);
				$draft['forumname'] = htmlspecialchars_uni($draft['forumname']);
				
				$detail = 'Forum: <a href="'.$draft['forumlink'].'">'.$draft['forumname'].'</a>';
				
				
				
				$editurl = "newthread.php?action=editdraft&amp;tid={$draft['tid']}";
				$id = $draft['tid'];
				$type = "thread";
			}

			$draft['subject'] = htmlspecialchars_uni($draft['subject']);
			$savedate = my_datee('relative', $draft['dateline']);
			
			eval("\$drafts .= \"".$templates->get("usercp_drafts_draft")."\";");
		}
	}
	else
	{
		$disable_delete_drafts = 'disabled="disabled"';
		
		eval("\$drafts = \"".$templates->get("usercp_drafts_none")."\";");
	
	}

	$plugins->run_hooks("usercp_drafts_end");

    eval("\$draftlist = \"".$templates->get("usercp_drafts")."\";");
	
	stdhead ('title');
	
	build_breadcrumb();
	
	echo $draftlist;
}





if($mybb->input['action'] == "profile")
{
	if($errors)
	{
		$user = $mybb->input;
		$bday = array();
		$bday[0] = $mybb->input['bday1'];
		$bday[1] = $mybb->input['bday2'];
		$bday[2] = intval($mybb->input['bday3']);
	}
	else
	{
		$user = $CURUSER['id'];
		$bday = explode("-", $CURUSER['birthday']);
		if(!isset($bday[1]))
		{
			$bday[1] = 0;
		}
	}
	if(!isset($bday[2]) || $bday[2] == 0)
	{
		$bday[2] = '';
	}

	$plugins->run_hooks("usercp_profile_start");

	$bdaydaysel = '';
	for($day = 1; $day <= 31; ++$day)
	{
		if($bday[0] == $day)
		{
			$selected = "selected=\"selected\"";
		}
		else
		{
			$selected = '';
		}

		$bdaydaysel .= '<option value="'.$day.'"'.$selected.'>'.$day.'</option>';
	}

	$bdaymonthsel = array();
	foreach(range(1, 12) as $month)
	{
		$bdaymonthsel[$month] = '';
	}
	$bdaymonthsel[$bday[1]] = 'selected="selected"';

	$allselected = $noneselected = $ageselected = '';
	if($CURUSER['birthdayprivacy'] == 'all' || !$CURUSER['birthdayprivacy'])
	{
		$allselected = " selected=\"selected\"";
	}
	elseif($CURUSER['birthdayprivacy'] == 'none')
	{
		$noneselected = " selected=\"selected\"";
	}
	elseif($CURUSER['birthdayprivacy'] == 'age')
	{
		$ageselected = " selected=\"selected\"";
	}


	$plugins->run_hooks("usercp_profile_end");

    eval("\$editprofile = \"".$templates->get("usercp_profile")."\";");
	
	stdhead('title');

	echo $editprofile;
}
 
  
 
  
  



if($mybb->input['action'] == "do_attachments" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	require_once INC_PATH."/functions_upload.php";
	if(!isset($mybb->input['attachments']) || !is_array($mybb->input['attachments']))
	{
		error('no_attachments_selected');
	}

	$plugins->run_hooks("usercp_do_attachments_start");

	// Get unviewable forums
	$f_perm_sql = '';
	//$unviewable_forums = get_unviewable_forums(true);
    $inactiveforums = get_inactive_forums();
	if($unviewable_forums)
	{
		$f_perm_sql = " AND p.fid NOT IN ($unviewable_forums)";
	}
	if($inactiveforums)
	{
		$f_perm_sql .= " AND p.fid NOT IN ($inactiveforums)";
	}

	$aids = implode(',', array_map('intval', $mybb->input['attachments']));

	$query = $db->sql_query("
		SELECT a.*, p.fid
		FROM attachments a
		LEFT JOIN tsf_posts p ON (a.pid=p.pid)
		WHERE aid IN ({$aids}) AND a.uid={$CURUSER['id']} {$f_perm_sql}
	");

	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], '', $attachment['aid']);
	}
	$plugins->run_hooks("usercp_do_attachments_end");
	redirect("usercp.php?action=attachments", $lang->usercp['attachments_deleted']);
}



if($mybb->input['action'] == "attachments")
{
	require_once INC_PATH."/functions_upload.php";

	$enableattachments = "1";
	
	if($enableattachments == 0)
	{
		error('attachments_disabled');
	}

	$plugins->run_hooks("usercp_attachments_start");

	// Get unviewable forums
	$f_perm_sql = '';
	//$unviewable_forums = get_unviewable_forums(true);
	$inactiveforums = get_inactive_forums();
	if($unviewable_forums)
	{
		$f_perm_sql = " AND t.fid NOT IN ($unviewable_forums)";
	}
	if($inactiveforums)
	{
		$f_perm_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$attachments = '';

	// Pagination
	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	$perpage = $f_threadsperpage;
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

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

	$query = $db->sql_query_prepared("
    SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject
    FROM attachments a
    LEFT JOIN tsf_posts p ON (a.pid=p.pid)
    LEFT JOIN tsf_threads t ON (t.tid=p.tid)
    WHERE a.uid = ? {$f_perm_sql}
    ORDER BY p.dateline DESC, p.pid DESC LIMIT ?, ?", [(int)$CURUSER['id'], (int)$start, (int)$perpage]);



	$bandwidth = $totaldownloads = $totalusage = $totalattachments = $processedattachments = 0;
	while($attachment = $db->fetch_array($query->result))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
			$attachment['subject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['subject']));
			$attachment['postlink'] = get_post_link($attachment['pid'], $attachment['tid']);
			$attachment['threadlink'] = get_thread_link($attachment['tid']);
			$attachment['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['threadsubject']));

			$size = mksize($attachment['filesize']);
			$icon = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			$sizedownloads = sprintf('('.$size.', '.$attachment['downloads'].' Downloads)');
			$attachdate = my_datee('relative', $attachment['dateline']);
			$altbg = alt_trow();

            eval("\$attachments .= \"".$templates->get("usercp_attachments_attachment")."\";");
			
			


			// Add to bandwidth total
			$bandwidth += ($attachment['filesize'] * $attachment['downloads']);
			$totaldownloads += $attachment['downloads'];
			$totalusage += $attachment['filesize'];
			++$totalattachments;
		}
		else
		{
			// This little thing delets attachments without a thread/post
			remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
		}
		++$processedattachments;
	}

	$multipage = '';
	if($processedattachments >= $perpage || $page > 1)
	{
		
        $query = $db->sql_query_prepared("
           SELECT SUM(a.filesize) AS ausage, COUNT(a.aid) AS acount
           FROM attachments a
           LEFT JOIN tsf_posts p ON (a.pid=p.pid)
           LEFT JOIN tsf_threads t ON (t.tid=p.tid) WHERE a.uid = ? {$f_perm_sql}", [(int)$CURUSER['id']]);



		$usage = $db->fetch_array($query->result);
		$totalusage = $usage['ausage'];
		$totalattachments = $usage['acount'];

		$multipage = multipage($totalattachments, $perpage, $page, "usercp.php?action=attachments");
	}

	$friendlyusage = mksize((int)$totalusage);
	if($usergroups['attachquota'])
	{
		$percent = round(($totalusage/($usergroups['attachquota']*1024))*100);
		$friendlyusage .= sprintf($lang->usercp['attachments_usage_percent'], $percent);
		$attachquota = mksize($usergroups['attachquota']*1024);
		$usagenote = sprintf('- Using '.$friendlyusage.' of '.$attachquota.' in '.$totalattachments.' Attachments');
	}
	else
	{
		$attachquota = $lang->usercp['unlimited'];
		$usagenote = sprintf($lang->usercp['attachments_usage'], $friendlyusage, $totalattachments);
	}

	$bandwidth = mksize($bandwidth);

	eval("\$delete_button = \"".$templates->get("delete_attachments_button")."\";");


	if(!$attachments)
	{
		$attachments = 'You currently do not have any files attached to your posts';
		
		
		$usagenote = '';
		$delete_button = '';
	}

	$plugins->run_hooks("usercp_attachments_end");

	
   eval("\$manageattachments = \"".$templates->get("usercp_attachments")."\";");
	
   stdhead('title');
   
   echo $manageattachments;
}







  

if(!$mybb->input['action'])
{
	// Get posts per day
	$daysreg = (TIMENOW - $CURUSER['added']) / (24*3600);

	if($daysreg < 1)
	{
		$daysreg = 1;
	}

	$perday = $CURUSER['postnum'] / $daysreg;
	$perday = round($perday, 2);
	if($perday > $CURUSER['postnum'])
	{
		$perday = $CURUSER['postnum'];
	}

	$stats = $cache->read("indexstats");
	$posts = $stats['totalposts'];
	if($posts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $CURUSER['postnum']*100/$posts;
		$percent = round($percent, 2);
	}

	$colspan = 2;
	$ss = sprintf($lang->usercp['posts_day'], ts_nf($perday), $percent);
	$regdate = my_datee('relative', $CURUSER['added']);
	
	
	$bonus = $CURUSER['seedbonus'];
	
	$com = $CURUSER['comms'];
	
	
	
	//$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
    //$avatar = '<img class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
	
	
	$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);


// Если аватар — это HTML-заглушка (начинается с '<'), выводим её как есть
if (strpos($useravatar['image'], '<') === 0) 
{
	 $avatar = $useravatar['image']; // <div class="avatar-ring2">No Avatar</div>
} 
// Иначе выводим как <img> (стандартный аватар)
else 
{
    $avatar = '<img class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
}
	
	
	
	
	
	
	
	
	
	
	

	$mybb->user['email'] = htmlspecialchars_uni($CURUSER['email']);

    $usergroup = htmlspecialchars_uni($groupscache[$CURUSER['usergroup']]['title']);
   
 
	//if($CURUSER['usergroup'] == 5 && $verification != "admin")
	//{
	 //  $usergroup .= '<div class="mt-3 mb-3"><a href="member.php?action=resendactivation" class="btn btn-outline-danger btn-sm"><i class="bi bi-info-circle"></i> {$lang->resend_activation}</a></div>';
	//}
	

	// Format username
	$username = format_name(htmlspecialchars_uni($CURUSER['username']), $CURUSER['usergroup'], $CURUSER['displaygroup']);
	$username = build_profile_link($username, $CURUSER['id']);

	// Format post numbers
	$mybb->user['posts'] = ts_nf($CURUSER['postnum']);



	$plugins->run_hooks("usercp_end");
	
    eval("\$usercp = \"".$templates->get("usercp")."\";");
	
	stdhead('title');
	
	echo $usercp;
}

  

  stdfoot ();
  
  
  
?>
