<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * The deal with this file is that it handles all of the XML HTTP Requests for MyBB.
 *
 * It contains a stripped down version of the MyBB core which does not load things
 * such as themes, who's online data, all of the language packs and more.
 *
 * This is done to make response times when using XML HTTP Requests faster and
 * less intense on the server.
 */
define("IN_MYBB", 1);

define ('TSF_FORUMS_TSSEv56', true);
define ('TSF_FORUMS_GLOBAL_TSSEv56', true);
define ('TSF_VERSION', 'v1.5 by xam');
define("SCRIPTNAME", "xmlhttp.php");
define ('IN_FORUMS', true );







require_once 'global.php';

  
if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
     exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

require_once INC_PATH.'/tsf_functions.php';


// Include our base data handler class
require_once INC_PATH . '/datahandler.php';








//$shutdown_queries = $shutdown_functions = array();

//// Load some of the stock caches we'll be using.
//$groupscache = $cache->read("usergroups");

//if(!is_array($groupscache))
//{
	//$cache->update_usergroups();
	//$groupscache = $cache->read("usergroups");
//}

// Send no cache headers
header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");



// Create the session
//require_once MYBB_ROOT."inc/class_session.php";
////$session = new session;
//$session->init();

// Load the language we'll be using
//if(!isset($mybb->settings['bblanguage']))
//{
	//$mybb->settings['bblanguage'] = "english";
//}
//if(isset($mybb->user['language']) && $lang->language_exists($mybb->user['language']))
//{
	//$mybb->settings['bblanguage'] = $mybb->user['language'];
//}
//$lang->set_language($mybb->settings['bblanguage']);

if(function_exists('mb_internal_encoding') && !empty($charset))
{
	@mb_internal_encoding($charset);
}




if($charset)
{
	$charset = $charset;
}
// If not, revert to UTF-8
else
{
	$charset = "UTF-8";
}

//$lang->load("global");
$lang->load("xmlhttp");

$closed_bypass = array("refresh_captcha", "validate_captcha");

$mybb->input['action'] = $mybb->get_input('action');

$plugins->run_hooks("xmlhttp");

// If the board is closed, the user is not an administrator and they're not trying to login, show the board closed message
//if($mybb->settings['boardclosed'] == 1 && $mybb->usergroup['canviewboardclosed'] != 1 && !in_array($mybb->input['action'], $closed_bypass))
//{
	// Show error
	//if(!$mybb->settings['boardclosed_reason'])
	//{
	//	$mybb->settings['boardclosed_reason'] = $lang->boardclosed_reason;
	//}

	//$lang->error_boardclosed .= "<br /><em>{$mybb->settings['boardclosed_reason']}</em>";

	//xmlhttp_error($lang->error_boardclosed);
//}


function show_msg ($message = '', $error = true, $color = 'red', $strong = true, $extra = '', $extra2 = '')
{
    global $shoutboxcharset;
    header ('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . 'GMT');
    header ('Cache-Control: no-cache, must-revalidate');
    header ('Pragma: no-cache');
    header ('' . 'Content-type: text/html; charset=' . $shoutboxcharset);
    if ($error)
    {
      exit ('<error>' . $message . '</error>');
    }

    exit ($extra . (!empty ($color) ? '<font color="' . $color . '">' : '') . ($strong ? '<strong>' : '') . $message . ($strong ? '</strong>' : '') . (!empty ($color) ? '</font>' : '') . $extra2);
}



function allowcomments($torrentid = 0)
{
    global $is_mod, $db;

    $sql = "SELECT allowcomments FROM torrents WHERE id = ?";
    $params = [(int)$torrentid];

    // Выполнение подготовленного запроса
    $query = $db->sql_query_prepared($sql, $params);

    // Используем $query->result для работы с результатом
    if (!$db->num_rows($query->result)) 
    {
        return false;
    }

    // Извлекаем данные из результата
    $Result = $db->fetch_array($query->result);
    $allowcomments = $Result['allowcomments'];

    if ($allowcomments != "yes" && !$is_mod) 
    {
        return false;
    }

    return true;
}




$is_mod = is_mod ($usergroups);





// Fetch a list of usernames beginning with a certain string (used for auto completion)
if($mybb->input['action'] == "get_users")
{
	$mybb->input['query'] = ltrim($mybb->get_input('query'));
	$search_type = $mybb->get_input('search_type', MyBB::INPUT_INT); // 0: starts with, 1: ends with, 2: contains

	// If the string is less than 2 characters, quit.
	if(my_strlen($mybb->input['query']) < 2)
	{
		exit;
	}

	if($mybb->get_input('getone', MyBB::INPUT_INT) == 1)
	{
		$limit = 1;
	}
	else
	{
		$limit = 15;
	}

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	// Query for any matching users.
	$query_options = array(
		"order_by" => "username",
		"order_dir" => "asc",
		"limit_start" => 0,
		"limit" => $limit
	);

	$plugins->run_hooks("xmlhttp_get_users_start");

	$likestring = $db->escape_string_like($mybb->input['query']);
	if($search_type == 1)
	{
		$likestring = '%'.$likestring;
	}
	elseif($search_type == 2)
	{
		$likestring = '%'.$likestring.'%';
	}
	else
	{
		$likestring .= '%';
	}

	$query = $db->simple_select("users", "id, username", "username LIKE '{$likestring}'", $query_options);
	if($limit == 1)
	{
		$user = $db->fetch_array($query);
		$data = array('uid' => $user['id'], 'id' => $user['username'], 'text' => $user['username']);
	}
	else
	{
		$data = array();
		while($user = $db->fetch_array($query))
		{
			$data[] = array('uid' => $user['id'], 'id' => $user['username'], 'text' => $user['username']);
		}
	}

	$plugins->run_hooks("xmlhttp_get_users_end");

	echo json_encode($data);
	exit;
}


// Fetch the list of multiquoted posts which are not in a specific thread
else if($mybb->input['action'] == "get_multiquoted")
{
	// If the cookie does not exist, exit
	if(!array_key_exists("multiquote", $mybb->cookies))
	{
		exit;
	}
	// Divide up the cookie using our delimeter
	$multiquoted = explode("|", $mybb->cookies['multiquote']);

	$plugins->run_hooks("xmlhttp_get_multiquoted_start");

	// No values - exit
	if(!is_array($multiquoted))
	{
		exit;
	}

	// Loop through each post ID and sanitize it before querying
	foreach($multiquoted as $post)
	{
		$quoted_posts[$post] = (int)$post;
	}

	// Join the post IDs back together
	$quoted_posts = implode(",", $quoted_posts);

	

	// Check group permissions if we can't view threads not started by us
	//$group_permissions = forum_permissions();
	$onlyusfids = array();
	foreach($group_permissions as $gpfid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $gpfid;
		}
	}

	$message = '';

	// Are we loading all quoted posts or only those not in the current thread?
	if(empty($mybb->input['load_all']))
	{
		$from_tid = "p.tid != '".$mybb->get_input('tid', MyBB::INPUT_INT)."' AND ";
	}
	else
	{
		$from_tid = '';
	}

	require_once INC_PATH."/class_parser.php";
	$parser = new postParser;

	require_once INC_PATH."/functions_posting.php";

	$plugins->run_hooks("xmlhttp_get_multiquoted_intermediate");

	// Query for any posts in the list which are not within the specified thread
	$query = $db->sql_query("
		SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, t.fid, t.uid AS thread_uid, p.visible, u.username AS userusername
		FROM tsf_posts p
		LEFT JOIN tsf_threads t ON (t.tid=p.tid)
		LEFT JOIN users u ON (u.id=p.uid)
		WHERE {$from_tid}p.pid IN ({$quoted_posts})
		ORDER BY p.dateline, p.pid
	");
	while($quoted_post = $db->fetch_array($query))
	{
		

		$message .= parse_quoted_message($quoted_post, false);
	}
	
	$maxquotedepth = "5";
	if($maxquotedepth != '0')
	{
		$message = remove_message_quotes($message);
	}

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_get_multiquoted_end");

	echo json_encode(array("message" => $message));
	exit;
}



else if($mybb->input['action'] == "edit_post")
{
	// Fetch the post from the database.
	$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

	// No result, die.
	if(!$post || $post['visible'] == -1)
	{
		xmlhttp_error('post_doesnt_exist');
	}

	// Fetch the thread associated with this post.
	$thread = get_thread($post['tid']);

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$thread || !$forum || $forum['type'] != "f")
	{
		xmlhttp_error('thread_doesnt_exist');
	}

	// Check if this forum is password protected and we have a valid password
	//if(check_forum_password($forum['fid'], 0, true))
	//{
	//	xmlhttp_error($lang->wrong_forum_password);
	//}

	// Fetch forum permissions.
	//$forumpermissions = forum_permissions($forum['fid']);

	$plugins->run_hooks("xmlhttp_edit_post_start");

	// If this user is not a moderator with "caneditposts" permissions.
	//if(!is_moderator($forum['fid'], "caneditposts"))
	//{
	//	// Thread is closed - no editing allowed.
	//	if($thread['closed'] == 1)
	///	{
	//		xmlhttp_error($lang->thread_closed_edit_message);
	//	}
	//	// Forum is not open, user doesn't have permission to edit, or author doesn't match this user - don't allow editing.
	//	else if($forum['open'] == 0 || $forumpermissions['caneditposts'] == 0 || $mybb->user['uid'] != $post['uid'] || $mybb->user['uid'] == 0 || $mybb->user['suspendposting'] == 1)
	//	{
	//		xmlhttp_error($lang->no_permission_edit_post);
	//	}
	//	// If we're past the edit time limit - don't allow editing.
	//	else if($mybb->usergroup['edittimelimit'] != 0 && $post['dateline'] < (TIME_NOW-($mybb->usergroup['edittimelimit']*60)))
	//	{
	//		$lang->edit_time_limit = $lang->sprintf($lang->edit_time_limit, $mybb->usergroup['edittimelimit']);
	//		xmlhttp_error($lang->edit_time_limit);
	//	}
	//	// User can't edit unapproved post unless permitted for own
	//	if($post['visible'] == 0 && !($mybb->settings['showownunapproved'] && $post['uid'] == $mybb->user['uid']))
	//	{
	//		xmlhttp_error($lang->post_moderation);
	//	}
	//}

	$plugins->run_hooks("xmlhttp_edit_post_end");

	if($mybb->get_input('do') == "get_post")
	{
		// Send our headers.
		header("Content-type: application/json; charset={$charset}");

		// Send the contents of the post.
		echo json_encode($post['message']);
		exit;
	}
	else if($mybb->get_input('do') == "update_post")
	{
		// Verify POST request
		//if(!verify_post_check($mybb->get_input('my_post_key'), true))
		//{
			//xmlhttp_error('invalid_post_code');
		//}

		$message = $mybb->get_input('value');
		$editreason = $mybb->get_input('editreason');
		if(my_strtolower($charset) != "utf-8")
		{
			if(function_exists("iconv"))
			{
				$message = iconv($charset, "UTF-8//IGNORE", $message);
				$editreason = iconv($charset, "UTF-8//IGNORE", $editreason);
			}
			else if(function_exists("mb_convert_encoding"))
			{
				$message = @mb_convert_encoding($message, $charset, "UTF-8");
				$editreason = @mb_convert_encoding($editreason, $charset, "UTF-8");
			}
			else if(my_strtolower($charset) == "iso-8859-1")
			{
				$message = utf8_decode($message);
				$editreason = utf8_decode($editreason);
			}
		}

		// Set up posthandler.
		require_once INC_PATH."/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$updatepost = array(
			"pid" => $post['pid'],
			"message" => $message,
			"editreason" => $editreason,
			"edit_uid" => $CURUSER['id']
		);

		// If this is the first post set the prefix. If a forum requires a prefix the quick edit would throw an error otherwise
		if($post['pid'] == $thread['firstpost'])
		{
			$updatepost['prefix'] = $thread['prefix'];
		}

		$posthandler->set_data($updatepost);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			xmlhttp_error($post_errors);
		}
		// No errors were found, we can call the update method.
		else
		{
			$postinfo = $posthandler->update_post();
			$visible = $postinfo['visible'];
			if($visible == 0 && !is_moderator($post['fid'], "canviewunapprove"))
			{
				// Is it the first post?
				if($thread['firstpost'] == $post['pid'])
				{
					echo json_encode(array("moderation_thread" => $lang->thread_moderation, 'url' => $mybb->settings['bburl'].'/'.get_forum_link($thread['fid']), "message" => $post['message']));
					exit;
				}
				else
				{
					echo json_encode(array("moderation_post" => $lang->post_moderation, 'url' => $mybb->settings['bburl'].'/'.get_thread_link($thread['tid']), "message" => $post['message']));
					exit;
				}
			}
		}

		require_once INC_PATH."/class_parser.php";
		$parser = new postParser;

		$parser_options = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"allow_videocode" => 1,
			"me_username" => $post['username'],
			"filter_badwords" => 1
		);

		$post['username'] = htmlspecialchars_uni($post['username']);

		

		$post['message'] = $parser->parse_message($message, $parser_options);

		// Now lets fetch all of the attachments for these posts.
		
		$enableattachments= "1";
		if($enableattachments != 0)
			
		{
			$query = $db->simple_select("attachments", "*", "pid='{$post['pid']}'");
			while($attachment = $db->fetch_array($query))
			{
				$attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
			}

			require_once INC_PATH."/functions_post.php";

			get_post_attachments($post['pid'], $post);
		}

		// Figure out if we need to show an "edited by" message
		// Only show if at least one of "showeditedby" or "showeditedbyadmin" is enabled
		
		$showeditedby = "1";
		
		if($showeditedby != 0)
		{
			$post['editdate'] = my_datee('relative', TIMENOW);
			$post['editnote'] = sprintf('This post was last modified: '.$post['editdate'].' by');
			$CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
			$post['editedprofilelink'] = build_profile_link($CURUSER['username'], $CURUSER['id']);
			$post['editreason'] = trim($editreason);
			$editreason = "";
			if($post['editreason'] != "")
			{
				$post['editreason'] = $parser->parse_badwords($post['editreason']);
				$post['editreason'] = htmlspecialchars_uni($post['editreason']);
				
				$editreason = ' Edit Reason: '.$post['editreason'].'';
			}
			
			
			$editedmsg = '
			
			<div class="mt-3"><span class="small">'.$post['editnote'].' '.$post['editedprofilelink'].''.$editreason.'</span></div>
			
			';
			
			
		}

		// Send our headers.
		header("Content-type: application/json; charset={$charset}");

		$editedmsg_response = null;
		if(!empty($editedmsg))
		{
			$editedmsg_response = str_replace(array("\r", "\n"), "", $editedmsg);
		}

		$plugins->run_hooks("xmlhttp_update_post");

		echo json_encode(array("message" => $post['message']."\n", "editedmsg" => $editedmsg_response));
		exit;
	}
}






// This action provides editing of thread/post subjects from within their respective list pages.
else if($mybb->input['action'] == "edit_subject" && $mybb->request_method == "post")
{
	// Verify POST request
	//if(!verify_post_check($mybb->get_input('my_post_key'), true))
	//{
	//	xmlhttp_error('invalid_post_code');
	//}

	// We're editing a thread subject.
	if($mybb->get_input('tid', MyBB::INPUT_INT))
	{
		// Fetch the thread.
		$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			xmlhttp_error('thread_doesnt_exist');
		}

		// Fetch some of the information from the first post of this thread.
		$query_options = array(
			"order_by" => "dateline, pid",
		);
		$query = $db->simple_select("tsf_posts", "pid,uid,dateline", "tid='".$thread['tid']."'", $query_options);
		$post = $db->fetch_array($query);
	}
	else
	{
		exit;
	}

	// Fetch the specific forum this thread/post is in.
	$forum = get_forum($thread['fid']);

	// Missing thread, invalid forum? Error.
	if(!$forum || $forum['type'] != "f")
	{
		xmlhttp_error('thread_doesnt_exist');
	}

	

	$plugins->run_hooks("xmlhttp_edit_subject_start");

	
	
	$subject = $mybb->get_input('value');
	if(my_strtolower($charset) != "utf-8")
	{
		if(function_exists("iconv"))
		{
			$subject = iconv($charset, "UTF-8//IGNORE", $subject);
		}
		else if(function_exists("mb_convert_encoding"))
		{
			$subject = @mb_convert_encoding($subject, $charset, "UTF-8");
		}
		else if(my_strtolower($charset) == "iso-8859-1")
		{
			$subject = utf8_decode($subject);
		}
	}

	// Only edit subject if subject has actually been changed
	if($thread['subject'] != $subject)
	{
		// Set up posthandler.
		require_once INC_PATH."/datahandlers/post.php";
		$posthandler = new PostDataHandler("update");
		$posthandler->action = "post";

		// Set the post data that came from the input to the $post array.
		$updatepost = array(
			"pid" => $post['pid'],
			"tid" => $thread['tid'],
			"fid" => $forum['fid'],
			"prefix" => $thread['prefix'],
			"subject" => $subject,
			"edit_uid" => $CURUSER['id']
		);
		$posthandler->set_data($updatepost);

		// Now let the post handler do all the hard work.
		if(!$posthandler->validate_post())
		{
			$post_errors = $posthandler->get_friendly_errors();
			xmlhttp_error($post_errors);
		}
		// No errors were found, we can call the update method.
		else
		{
			$posthandler->update_post();
			//if($ismod == true)
			//{
				$modlogdata = array(
					"tid" => $thread['tid'],
					"fid" => $forum['fid']
				);
				log_moderator_action($modlogdata, 'Edited Post');
			//}
		}
	}

	require_once INC_PATH."/class_parser.php";
	$parser = new postParser;

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_edit_subject_end");

	$mybb->input['value'] = $parser->parse_badwords($mybb->get_input('value'));

	// Spit the subject back to the browser.
	$subject = substr($mybb->input['value'], 0, 120); // 120 is the varchar length for the subject column
	echo json_encode(array("subject" => '<a href="'.get_thread_link($thread['tid']).'">'.htmlspecialchars_uni($subject).'</a>'));

	// Close the connection.
	exit;
}




else if($mybb->input['action'] == "get_buddyselect")
{
	// Send our headers.
	header("Content-type: text/plain; charset={$charset}");

	if($CURUSER['buddylist'] != "")
	{
		$query_options = array(
			"order_by" => "username",
			"order_dir" => "asc"
		);

		$plugins->run_hooks("xmlhttp_get_buddyselect_start");

		$timecut = TIMENOW - $wolcutoffmins;
		$query = $db->simple_select("users", "id, username, usergroup, displaygroup, lastactive, lastvisit, invisible", "id IN ({$CURUSER['buddylist']})", $query_options);
		$online = array();
		$offline = array();
		while($buddy = $db->fetch_array($query))
		{
			$buddy['username'] = htmlspecialchars_uni($buddy['username']);
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['id'], '_blank');
			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $CURUSER['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				eval("\$online[] = \"".$templates->get("xmlhttp_buddyselect_online")."\";");
			}
			else
			{
				eval("\$offline[] = \"".$templates->get("xmlhttp_buddyselect_offline")."\";");
			}
		}
		$online = implode("", $online);
		$offline = implode("", $offline);

		$plugins->run_hooks("xmlhttp_get_buddyselect_end");

		eval("\$buddy_select = \"".$templates->get("xmlhttp_buddyselect")."\";");
		echo $buddy_select;
	}
	else
	{
		xmlhttp_error('buddylist_error');
	}
}



else if($mybb->input['action'] == "complex_password")
{
	$password = trim($mybb->get_input('password'));
	$password = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $password);

	$minpasswordlength = '6';
	
	header("Content-type: application/json; charset={$charset}");

	$plugins->run_hooks("xmlhttp_complex_password");

	if(!preg_match("/^.*(?=.{".$minpasswordlength.",})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $password))
	{
		echo json_encode('complex_password_fails');
	}
	else
	{
		// Return nothing but an OK password if passes regex
		echo json_encode("true");
	}

	exit;
}


else if($mybb->input['action'] == "username_availability")
{
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		xmlhttp_error($lang->invalid_post_code);
	}

	require_once INC_PATH."/functions_user.php";
	$username = $mybb->get_input('username');

	// Fix bad characters
	$username = trim_blank_chrs($username);
	$username = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $username);

	// Remove multiple spaces from the username
	$username = preg_replace("#\s{2,}#", " ", $username);

	header("Content-type: application/json; charset={$charset}");

	if(empty($username))
	{
		echo json_encode('banned_characters_username');
		exit;
	}

	// Check if the username belongs to the list of banned usernames.
	$banned_username = is_banned_username($username, true);
	if($banned_username)
	{
		echo json_encode('banned_username');
		exit;
	}

	// Check for certain characters in username (<, >, &, and slashes)
	if(strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || strpos($username, ",") !== false || !validate_utf8_string($username, false, false))
	{
		echo json_encode('banned_characters_username');
		exit;
	}

	// Check if the username is actually already in use
	$user = get_user_by_username($username);

	$plugins->run_hooks("xmlhttp_username_availability");

	if($user)
	{
		$username_taken = sprintf($lang->xmlhttp['username_taken'], htmlspecialchars_uni($username));
		echo json_encode($username_taken);
		exit;
	}
	else
	{
	$username_available = sprintf($lang->xmlhttp['username_available'], htmlspecialchars_uni($username));
		echo json_encode("true");
		exit;
	}
}


else if($mybb->input['action'] == "email_availability")
{
	if(!verify_post_check($mybb->get_input('my_post_key'), true))
	{
		xmlhttp_error('invalid_post_code');
	}

	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler("insert");

	$email = $mybb->get_input('email');

	header("Content-type: application/json; charset={$charset}");

	$user = array(
		'email' => $email
	);

	$userhandler->set_data($user);

	$errors = array();

	if(!$userhandler->verify_email())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	$plugins->run_hooks("xmlhttp_email_availability");

	if(!empty($errors))
	{
		echo json_encode($errors[0]);
		exit;
	}
	else
	{
		echo json_encode("true");
		exit;
	}
}






else if($mybb->input['action'] == "search_torrents") 
{
    $input = isset($_GET['input']) ? trim($_GET['input']) : '';

    if (empty($input)) {
        header("Content-Type: application/json; charset={$charset}");
        echo json_encode([]);
        exit;
    }

    // Добавляем символы LIKE
    $like_input = "%{$input}%";

    $sql = "
        SELECT id, name, descr, t_image
        FROM torrents
        WHERE name LIKE ? OR descr LIKE ?
        ORDER BY name
        LIMIT 10
    ";

    $params = [$like_input, $like_input];

    $result = $db->sql_query_prepared($sql, $params);

    $torrents = [];
    while ($row = $db->fetch_array($result->result)) 
	{
        $image_url = !empty($row['t_image'])
            ? (strpos($row['t_image'], 'http') === 0 ? $row['t_image'] : $BASEURL . '/' . ltrim($row['t_image'], '/'))
            : $BASEURL . '/pic/nopreview.gif';

        $torrents[] = [
            'id' => $row['id'],
            'name' => mb_strimwidth($row['name'], 0, 100, '...'),
            'descr' => $row['descr'],
            'image_url' => $image_url
        ];
    }

    header("Content-Type: application/json; charset={$charset}");
    echo json_encode($torrents);
    exit;
}




	if ($mybb->input['action'] == "quick_comment" && isset($_POST['ajax_quick_comment']) && isset($_POST['id']) && isset($_POST['text']) && $CURUSER)	
	{
       
	  $query = $db->simple_select("ts_u_perm", "cancomment", "userid='".$CURUSER['id']."'");
	  
      if (0 < $db->num_rows ($query))
      {
        $commentperm = $db->fetch_array ($query);
        if ($commentperm['cancomment'] == '0')
        {
          show_msg ('nopermission');
        }
      }

      $torrentid = intval ($_POST['id']);
      $lang->load ('comment');
      if (allowcomments ($torrentid) == false)
      {
        show_msg ($lang->comment['closed']);
      }

      $text = urldecode ($_POST['text']);
      $text = strval ($text);
      if (strtolower ($shoutboxcharset) != 'utf-8')
      {
        if (function_exists ('iconv'))
        {
          $text = iconv ('UTF-8', $shoutboxcharset, $text);
        }
        else
        {
          if (function_exists ('mb_convert_encoding'))
          {
            $text = mb_convert_encoding ($text, $shoutboxcharset, 'UTF-8');
          }
          else
          {
            if (strtolower ($shoutboxcharset) == 'iso-8859-1')
            {
              $text = utf8_decode ($text);
            }
          }
        }
      }

      
	  $query = $db->simple_select("comments", "dateline", "user = '{$CURUSER['id']}'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
	  
      if (0 < $db->num_rows ($query))
      {
       
		 $Result = $db->fetch_array($query);
         $last_comment = $Result['dateline'];
      }

      $floodmsg = flood_check ($lang->comment['floodcomment'], $last_comment, true);
      
	  $res = $db->simple_select("torrents", "name, owner", "id='".$torrentid."'");
	  
      $arr = $db->fetch_array($res);
      if (!empty ($floodmsg))
      {
        show_msg (str_replace (array ('<font color="#9f040b" size="2">', '</font>', '<b>', '</b>'), '', $floodmsg));
      }
      else
      {
        if (!$arr)
        {
          show_msg ($lang->global['notorrentid']);
        }
        else
        {
          if (((empty ($text) OR empty ($torrentid)) OR !is_valid_id ($torrentid)))
          {
            show_msg ($lang->global['dontleavefieldsblank']);
          }
        }
      }

      $commentposted = false;
      if (!$is_mod)
      {
        
	    $query = $db->simple_select("comments", "id, user, text", "torrent='{$torrentid}'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => 1));
		
        if (0 < $db->num_rows ($query))
        {
         
		  $last_post55 = $db->fetch_array($query);
		  $lastcommentuserid = $last_post55['user'];
		  
          if ($lastcommentuserid == $CURUSER['id'])
          {
            $oldtext = $last_post55['text'];
            $newid = $last_post55['id'];
            

			$newtext = $oldtext .="\n[hr]\n".$_POST['text'];
			
			$update_comments = array(
			    "text" => $db->escape_string($newtext)
		    );
		    $update_comments['editedat'] = TIMENOW;
		    $update_comments['editedby'] = $db->escape_string($CURUSER['id']);
					
		    $db->update_query("comments", $update_comments, "id='{$newid}'");
			
			
            if ($db->affected_rows ())
            {
              $commentposted = true;
            }
          }
        }
      }

      if (!$commentposted)
      {
       
		// Insert the comment.
		$comment_insert_data = array(
			"user" => $db->escape_string($CURUSER['id']),
			"torrent" => $db->escape_string($torrentid),
			"dateline" => TIMENOW,
			"text" => $db->escape_string($text)
		
		);

		$db->insert_query("comments", $comment_insert_data);
		
        $cid = $db->insert_id();
		
		
		
		
		// Привязываем загруженные файлы к этому комментарию
                if (!empty($_POST['file_ids'])) 
				{
                   $file_ids = array_map('intval', $_POST['file_ids']); // защита
                   $id_list  = implode(',', $file_ids);

                   if (!empty($id_list)) 
				   {
                     $db->sql_query("
                         UPDATE comment_files 
                         SET comment_id = " . (int)$cid . "
                         WHERE id IN ($id_list)
                        ");
                   }
                }
		
		
		
		
		
      
		
		$update_array['comments'] = 'comments+1';
		$db->update_query("torrents", $update_array, "id='{$torrentid}'", 1, true);
		
		$update_comms['comms'] = 'comms+1';
		$db->update_query("users", $update_comms, "id='{$CURUSER['id']}'", 1, true);
		
		
		
		$sql = "SELECT commentpm FROM users WHERE id = ?";
        $params = [(int)$arr['owner']]; // кастим к int для безопасности

        $ras = $db->sql_query_prepared($sql, $params);

        $arg = $db->fetch_array($ras->result);

		
		
		  
		if (($arg['commentpm'] == 1 && $CURUSER['id'] != $arr['owner']))
        {
            require_once INC_PATH . '/functions_pm.php';
            $url2 = get_comment_link($cid, $torrentid)."#pid{$cid}";
					
			$pm = array(
				'subject' => sprintf ($lang->comment['newcommentsub']),
				'message' => sprintf ($lang->comment['newcommenttxt'], '[url=' . $BASEURL.'/'.$url2.']' . $arr['name'] . '[/url]'),
				'touid' => $arr['owner']
			);
			
			$pm['sender']['uid'] = -1;
			send_pm($pm, -1, true);
		  
		  
		}
		
      }


	  require_once INC_PATH . '/commenttable.php';
      
      $subres = $db->sql_query_prepared("
      SELECT c.id, c.torrent as torrentid, c.text, c.user, c.dateline, c.editedby, c.editedat,
           c.totalvotes, uu.username as editedbyuname, 
           gg.namestyle as editbynamestyle, u.postnum, u.threadnum, u.added, u.comms, u.enabled, u.warned, u.leechwarn, 
           u.username, u.usertitle, u.usergroup, u.donor, u.uploaded, 
           u.downloaded, u.avatar as useravatar, u.avatardimensions, u.signature, g.title as grouptitle, g.namestyle 
           FROM comments c 
           LEFT JOIN users uu ON (c.editedby = uu.id) 
           LEFT JOIN usergroups gg ON (uu.usergroup = gg.gid) 
           LEFT JOIN users u ON (c.user = u.id) 
           LEFT JOIN usergroups g ON (u.usergroup = g.gid) 
           WHERE c.id = ? 
           ORDER BY c.id", [(int)$cid]);

				
      $allrows = array();
      while ($subrow = $db->fetch_array($subres->result)) 
	  {
           $allrows[] = $subrow;
      }
      $lcid = 0;
      if (isset($_POST["lcid"])) 
	  {
          $lcid = intval($_POST["lcid"]);
      }
      define("LCID", $lcid);
      
	  $showcommenttable = commenttable($allrows, "", "", false, true, true);
      show_msg($showcommenttable, false, "", false);
					
      //return 1;
    }














/**
 * Spits an XML Http based error message back to the browser
 *
 * @param string $message The message to send back.
 */
function xmlhttp_error($message)
{
	global $charset;

	// Send our headers.
	header("Content-type: application/json; charset={$charset}");

	// Do we have an array of messages?
	if(is_array($message))
	{
		$response = array();
		foreach($message as $error)
		{
			$response[] = $error;
		}

		// Send the error messages.
		echo json_encode(array("errors" => array($response)));

		exit;
	}

	// Just a single error? Send it along.
	echo json_encode(array("errors" => array($message)));

	exit;
}
