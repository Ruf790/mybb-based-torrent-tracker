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
 * Build a post bit
 *
 * @param array $post The post data
 * @param int $post_type The type of post bit we're building (1 = preview, 2 = pm, 3 = announcement, else = post)
 * @return string The built post bit
 */
 
 
 
 
 
 
function build_postbit($post, $post_type=0)
{
	global $db, $altbg, $theme, $mybb, $postcounter, $profile_fields, $regdateformat, $wolcutoffmins, $usergroups, $CURUSER, $f_postsperpage, $moderator, $forummoderator, $thread, $permissions, $forum_threads;
	global $titlescache, $page, $templates, $forumpermissions, $attachcache;
	global $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
	global $plugins, $parser, $cache, $ignored_users, $hascustomtitle;

	$hascustomtitle = 0;

	// Set default values for any fields not provided here
	foreach(array('pid', 'aid', 'pmid', 'posturl', 'button_multiquote', 'subject_extra', 'attachments', 'button_rep', 'button_warn', 'button_purgespammer', 'button_pm', 'button_reply_pm', 'button_replyall_pm', 'button_forward_pm', 'button_delete_pm', 'replink', 'warninglevel') as $post_field)
	{
		if(empty($post[$post_field]))
		{
			$post[$post_field] = '';
		}
	}
	
	// Set up the message parser if it doesn't already exist.
	if(!$parser)
	{
		require_once INC_PATH."/class_parser.php";
		$parser = new postParser;
	}

	

	$unapproved_shade = '';
	if(isset($post['visible']) && $post['visible'] == 0 && $post_type == 0)
	{
		$altbg = $unapproved_shade = 'unapproved_post';
	}
	elseif(isset($post['visible']) && $post['visible'] == -1 && $post_type == 0)
	{
		$altbg = $unapproved_shade = 'unapproved_post deleted_post';
	}
	elseif($altbg == 'trow1')
	{
		$altbg = 'trow2';
	}
	else
	{
		$altbg = 'trow1';
	}
	$post['fid'] = $fid;
	switch($post_type)
	{
		case 1: // Message preview
			global $forum;
			$parser_options['allow_html'] = 1;
			$parser_options['allow_mycode'] = 1;
			$parser_options['allow_smilies'] = 1;
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = 0;
			break;
		case 2: // Private message
			global $message, $pmid;
			$idtype = 'pmid';
			$parser_options['allow_html'] = 1;
			$parser_options['allow_mycode'] = 1;
			$parser_options['allow_smilies'] = 1;
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = $pmid;
			break;
		case 3: // Announcement
			global $announcementarray, $message;
			//$parser_options['allow_html'] = $mybb->settings['announcementshtml'] && $announcementarray['allowhtml'];
			//$parser_options['allow_mycode'] = $announcementarray['allowmycode'];
			//$parser_options['allow_smilies'] = $announcementarray['allowsmilies'];
			//$parser_options['allow_imgcode'] = 1;
			//$parser_options['allow_videocode'] = 1;
			//$parser_options['me_username'] = $post['username'];
			//$parser_options['filter_badwords'] = 1;
			$id = $announcementarray['aid'];
			break;
		default: // Regular post
			global $forum, $thread, $tid;
			$oldforum = $forum;
			$id = (int)$post['pid'];
			$idtype = 'pid';
			$parser_options['allow_html'] = 1;
			$parser_options['allow_mycode'] = 1;
			$parser_options['allow_smilies'] = 1;
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['filter_badwords'] = 1;
			break;
	}

	if(!$post['username'])
	{
		$post['username'] = 'guest'; // htmlspecialchars_uni'd below
	}

	if($post['userusername'])
	{
		$parser_options['me_username'] = $post['userusername'];
	}
	else
	{
		$parser_options['me_username'] = $post['username'];
	}

	$post['username'] = htmlspecialchars_uni($post['username']);
	$post['userusername'] = htmlspecialchars_uni($post['userusername']);

	if(!$postcounter)
	{ // Used to show the # of the post
		if($page > 1)
		{
			if(!$f_postsperpage || (int)$f_postsperpage < 1)
			{
				$f_postsperpage = 20;
			}

			$postcounter = $f_postsperpage*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
		$post_extra_style = "border-top-width: 0;";
	}
	elseif($mybb->get_input('mode') == "threaded")
	{
		$post_extra_style = "border-top-width: 0;";
	}
	else
	{
		$post_extra_style = "margin-top: 5px;";
	}

	if(!$altbg)
	{ // Define the alternate background colour if this is the first post
		$altbg = "trow1";
	}
	$postcounter++;

	// Format the post date and time using my_date
	$post['postdate'] = my_datee('relative', $post['dateline']);

	// Dont want any little 'nasties' in the subject
	$post['subject'] = $parser->parse_badwords($post['subject']);

	// Pm's have been htmlspecialchars_uni()'ed already.
	if($post_type != 2)
	{
		$post['subject'] = htmlspecialchars_uni($post['subject']);
	}

	if(empty($post['subject']))
	{
		$post['subject'] = '&nbsp;';
	}

	$post['author'] = $post['uid'];
	$post['subject_title'] = $post['subject'];

	// Get the usergroup
	if($post['usergroup'])
	{
		$usergroup = usergroup_permissions($post['usergroup']);
	}
	else
	{
		$usergroup = usergroup_permissions(1);
	}

	// Fetch display group data.
	$displaygroupfields = array("title", "description", "namestyle", "title");
	

	if(empty($post['displaygroup']))
	{
		$post['displaygroup'] = $post['usergroup'];
	}

	// Set to hardcoded Guest usergroup ID (1) for guest author or deleted user.
	if(empty($post['usergroup']))
	{
		$post['usergroup'] = 1;
	}
	if(empty($post['displaygroup']))
	{
		$post['displaygroup'] = 1;
	}

	$displaygroup = usergroup_displaygroup($post['displaygroup']);
	if(is_array($displaygroup))
	{
		$usergroup = array_merge($usergroups, $displaygroup);
	}

	if(!is_array($titlescache))
	{
		$cached_titles = $cache->read("usertitles");
		if(!empty($cached_titles))
		{
			foreach($cached_titles as $title)
			{
				$titlescache[$title['posts']] = $title;
			}
		}

		if(is_array($titlescache))
		{
			krsort($titlescache);
		}
		unset($title, $cached_titles);
	}

	// Work out the usergroup/title stuff
	//$post['groupimage'] = '';
	//if(!empty($usergroup['image']))
	//{
	//	$language = $mybb->settings['bblanguage'];
	//	if(!empty($mybb->user['language']))
	//	{
	//		$language = $mybb->user['language'];
	//	}

	//	$usergroup['image'] = str_replace("{lang}", $language, $usergroup['image']);
	//	$usergroup['image'] = str_replace("{theme}", $theme['imgdir'], $usergroup['image']);
	//	eval("\$post['groupimage'] = \"".$templates->get("postbit_groupimage")."\";");

	//	if($mybb->settings['postlayout'] == "classic")
	//	{
	//		$post['groupimage'] .= "<br />";
	//	}
	//}

	if($post['userusername'])
	{
		// This post was made by a registered user
		$post['username'] = $post['userusername'];
		$post['profilelink_plain'] = get_profile_link($post['id']);
		$post['username_formatted'] = format_name($post['username'], $post['usergroup'], $post['displaygroup']);
		$post['profilelink'] = build_profile_link($post['username_formatted'], $post['id']);

		if(trim($post['usertitle']) != "")
		{
			$hascustomtitle = 1;
		}

		if($usergroups['usertitle'] != "" && !$hascustomtitle)
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		//elseif(is_array($titlescache) && !$usergroup['title'])
		//{
		//	reset($titlescache);
		//	foreach($titlescache as $key => $titleinfo)
		//	{
		//		if($post['postnum'] >= $key)
		//		{
		//			if(!$hascustomtitle)
		//			{
		//				$post['title'] = $titleinfo['title'];
		//			}
		//			$post['stars'] = $titleinfo['stars'];
		//			$post['starimage'] = $titleinfo['starimage'];
		//			break;
		//		}
		//	}
		//}

		$post['usertitle'] = htmlspecialchars_uni($post['usertitle']);

		//if($usergroup['stars'])
		//{
		//	$post['stars'] = $usergroup['stars'];
		//}

		//if(empty($post['starimage']))
		//{
		//	$post['starimage'] = $usergroup['starimage'];
		//}

		//$post['userstars'] = '';
		//if($post['starimage'] && isset($post['stars']))
		//{
			// Only display stars if we have an image to use...
		//	$post['starimage'] = str_replace("{theme}", $theme['imgdir'], $post['starimage']);

		//	for($i = 0; $i < $post['stars']; ++$i)
		//	{
		//		eval("\$post['userstars'] .= \"".$templates->get("postbit_userstar", 1, 0)."\";");
		//	}

		//	$post['userstars'] .= "<br />";
		//}

		$postnum = $post['postnum'];
		$post['postnum'] = ts_nf($post['postnum']);
		$post['threadnum'] = ts_nf($post['threadnum']);

		// Determine the status to show for the user (Online/Offline/Away)
        $moderator = is_mod($usergroups);

		$timecut = TIMENOW - $wolcutoffmins;
		if($post['lastactive'] > $timecut && ($post['invisible'] != 1 || $moderator) && $post['lastvisit'] != $post['lastactive'])
		{
			$post['onlinestatus'] = '<a href="online.php" title="'.$lang->global['postbit_status_online'].'"><i class="fa-solid fa-circle-dot smaller" style="vertical-align: 0.115em; padding-left: 4px; color: #68c000"></i></a>';
		}
		else
		{
			//$allowaway = "1";
			
			//if($post['away'] == 1 && $allowaway != 0)
			//{
				//$post['onlinestatus'] = '<a href="'.$post['profilelink_plain'].'" title="{$lang->postbit_status_away}">
				//<img src="{$theme[imgdir]}/buddy_away.png" border="0" alt="{$lang->postbit_status_away}" class="buddy_status" /></a>';
			//}
			//else
			//{
				$post['onlinestatus'] = '<i class="fa-solid fa-circle-dot smaller" title="'.$lang->global['postbit_status_offline'].'" style="vertical-align: 0.115em; padding-left: 4px; color: #ccc"></i>';
			//}
		}

		$post['useravatar'] = '';
		if(isset($CURUSER['showavatars']) && $CURUSER['showavatars'] != 0 || $CURUSER['id'] == 0)
		{
			$useravatar = format_avatar($post['avatar'], $post['avatardimensions']);
			eval("\$post['useravatar'] = \"".$templates->get("postbit_avatar")."\";");
			
		}

		//$post['button_find'] = '';
		//if($mybb->usergroup['cansearch'] == 1)
		//{
			//eval("\$post['button_find'] = \"".$templates->get("postbit_find")."\";");
		//}

		//if($mybb->settings['enablepms'] == 1 && $post['uid'] != $mybb->user['uid'] && (($post['receivepms'] != 0 && $usergroup['canusepms'] != 0 && $mybb->usergroup['cansendpms'] == 1 && my_strpos(",".$post['ignorelist'].",", ",".$mybb->user['uid'].",") === false) || $mybb->usergroup['canoverridepm'] == 1))
		//{
			$post['button_pm'] = '<a href="private.php?action=send&amp;uid='.$post['uid'].'" title="{$lang->postbit_pm}" class="btn btn-secondary btn-sm" style="font-size: 12px!important; font-weight: 500!important"><i class="fa-solid fa-envelope"></i> &nbsp;{$lang->postbit_button_pm}</a>';
		//}

		///$post['button_rep'] = '';
		//if($post_type != 3 && $mybb->settings['enablereputation'] == 1 && $mybb->settings['postrep'] == 1 && $mybb->usergroup['cangivereputations'] == 1 && $usergroup['usereputationsystem'] == 1 && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']) && $post['uid'] != $mybb->user['uid'] && (!isset($post['visible']) || $post['visible'] == 1) && (!isset($thread['visible']) || $thread['visible'] == 1))
		//{
		//	if(empty($post['pid']))
		//	{
		//		$post['pid'] = 0;
		//	}

			//eval("\$post['button_rep'] = \"".$templates->get("postbit_rep_button")."\";");
		//}

		//if($post['website'] != "" && !is_member($mybb->settings['hidewebsite']) && $usergroup['canchangewebsite'] == 1)
		//{
		//	$post['website'] = htmlspecialchars_uni($post['website']);
			//eval("\$post['button_www'] = \"".$templates->get("postbit_www")."\";");
		//}
		//else
		//{
		//	$post['button_www'] = "";
		//}

		//if($post['hideemail'] != 1 && $post['uid'] != $mybb->user['uid'] && $mybb->usergroup['cansendemail'] == 1)
		//{
			//eval("\$post['button_email'] = \"".$templates->get("postbit_email")."\";");
		//}
		//else
		//{
			//$post['button_email'] = "";
		//}

		$post['userregdate'] = my_datee($regdateformat, $post['added']);

		// Work out the reputation this user has (only show if not announcement)
		//if($post_type != 3 && $usergroup['usereputationsystem'] != 0 && $mybb->settings['enablereputation'] == 1)
		//{
		//	$post['userreputation'] = get_reputation($post['reputation'], $post['uid']);
		//	eval("\$post['replink'] = \"".$templates->get("postbit_reputation")."\";");
		//}

		// Showing the warning level? (only show if not announcement)
		//if($post_type != 3 && $mybb->settings['enablewarningsystem'] != 0 && $usergroup['canreceivewarnings'] != 0 && ($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $post['uid'] && $mybb->settings['canviewownwarning'] != 0)))
		//{
		//	if($mybb->settings['maxwarningpoints'] < 1)
		//	{
		//		$mybb->settings['maxwarningpoints'] = 10;
		//	}

		//	$warning_level = round($post['warningpoints']/$mybb->settings['maxwarningpoints']*100);
		//	if($warning_level > 100)
		//	{
		//		$warning_level = 100;
		//	}
		//	$warning_level = get_colored_warning_level($warning_level);

			// If we can warn them, it's not the same person, and we're in a PM or a post.
		//	if($mybb->usergroup['canwarnusers'] != 0 && $post['uid'] != $mybb->user['uid'] && ($post_type == 0 || $post_type == 2))
		//	{
		//		eval("\$post['button_warn'] = \"".$templates->get("postbit_warn")."\";");
		//		$warning_link = "warnings.php?uid={$post['uid']}";
		//	}
		//	else
		//	{
		//		$post['button_warn'] = '';
		//		$warning_link = "usercp.php";
		//	}
		//	eval("\$post['warninglevel'] = \"".$templates->get("postbit_warninglevel")."\";");
		//}

		//if($post_type != 3 && $post_type != 1 && purgespammer_show($post['postnum'], $post['usergroup'], $post['uid']))
		//{
			//eval("\$post['button_purgespammer'] = \"".$templates->get('postbit_purgespammer')."\";");
		//}

		if(!isset($profile_fields))
		{
			$profile_fields = array();

			// Fetch profile fields to display
			$pfcache = $cache->read('profilefields');
		
			if(is_array($pfcache))
			{
				foreach($pfcache as $profilefield)
				{
					if($profilefield['postbit'] != 1)
					{
						continue;
					}
		
					$profile_fields[$profilefield['fid']] = $profilefield;
				}
			}
		}

		// Display profile fields on posts - only if field is filled in
		$post['profilefield'] = '';
		if(!empty($profile_fields))
		{
			foreach($profile_fields as $field)
			{
				$fieldfid = "fid{$field['fid']}";
				if(!empty($post[$fieldfid]))
				{
					$post['fieldvalue'] = '';
					$post['fieldname'] = htmlspecialchars_uni($field['name']);

					$thing = explode("\n", $field['type'], "2");
					$type = trim($thing[0]);
					$useropts = explode("\n", $post[$fieldfid]);

					if(is_array($useropts) && ($type == "multiselect" || $type == "checkbox"))
					{
						$post['fieldvalue_option'] = '';

						foreach($useropts as $val)
						{
							if($val != '')
							{
								eval("\$post['fieldvalue_option'] .= \"".$templates->get("postbit_profilefield_multiselect_value")."\";");
							}
						}
						if($post['fieldvalue_option'] != '')
						{
							eval("\$post['fieldvalue'] .= \"".$templates->get("postbit_profilefield_multiselect")."\";");
						}
					}
					else
					{
						$field_parser_options = array(
							"allow_html" => $field['allowhtml'],
							"allow_mycode" => $field['allowmycode'],
							"allow_smilies" => $field['allowsmilies'],
							"allow_imgcode" => $field['allowimgcode'],
							"allow_videocode" => $field['allowvideocode'],
							#"nofollow_on" => 1,
							"filter_badwords" => 1
						);

						if($field['type'] == "textarea")
						{
							$field_parser_options['me_username'] = $post['username'];
						}
						else
						{
							$field_parser_options['nl2br'] = 0;
						}

						if($mybb->user['uid'] != 0 && $mybb->user['showimages'] != 1 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
						{
							$field_parser_options['allow_imgcode'] = 0;
						}

						$post['fieldvalue'] = $parser->parse_message($post[$fieldfid], $field_parser_options);
					}

					eval("\$post['profilefield'] .= \"".$templates->get("postbit_profilefield")."\";");
				}
			}
		}

		
		eval("\$post['user_details'] = \"".$templates->get("postbit_author_user")."\";");
	}
	else
	{ // Message was posted by a guest or an unknown user
		$post['profilelink'] = format_name($post['username'], 1);

		if($usergroup['title'])
		{
			$post['title'] = $usergroup['title'];
		}
		else
		{
			$post['title'] = 'guest';
		}

		$post['title'] = htmlspecialchars_uni($post['title']);
		$post['userstars'] = '';
		$post['useravatar'] = '';

		$usergroup['title'] = 'na';

		$post['userregdate'] = 'na';
		$post['postnum'] = 'na';
		$post['button_profile'] = '';
		$post['button_email'] = '';
		$post['button_www'] = '';
		$post['signature'] = '';
		$post['button_pm'] = '';
		$post['button_find'] = '';
		$post['onlinestatus'] = '';
		$post['replink'] = '';
		eval("\$post['user_details'] = \"".$templates->get("postbit_author_guest")."\";");
	}

	$post['input_editreason'] = '';
	$post['button_edit'] = '';
	$post['button_quickdelete'] = '';
	$post['button_quickrestore'] = '';
	$post['button_quote'] = '';
	$post['button_quickquote'] = '';
	$post['button_report'] = '';
	$post['button_reply_pm'] = '';
	$post['button_replyall_pm'] = '';
	$post['button_forward_pm']  = '';
	$post['button_delete_pm'] = '';

	// For private messages, fetch the reply/forward/delete icons
	if($post_type == 2 && $post['pmid'])
	{
		global $replyall;

		eval("\$post['button_reply_pm'] = \"".$templates->get("postbit_reply_pm")."\";");
		eval("\$post['button_forward_pm'] = \"".$templates->get("postbit_forward_pm")."\";");
		eval("\$post['button_delete_pm'] = \"".$templates->get("postbit_delete_pm")."\";");

		if($replyall == true)
		{
		   eval("\$post['button_replyall_pm'] = \"".$templates->get("postbit_replyall_pm")."\";");
		
		}
	}

	$post['editedmsg'] = '';
	if(!$post_type)
	{
		if(!isset($forumpermissions))
		{
			$forumpermissions = forum_permissions($fid);
		}

		// Figure out if we need to show an "edited by" message
		//if($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "" && ($mybb->settings['showeditedby'] != 0 && $usergroup['cancp'] == 0 && !is_moderator($post['fid'], "", $post['uid']) || ($mybb->settings['showeditedbyadmin'] != 0 && ($usergroup['cancp'] == 1 || is_moderator($post['fid'], "", $post['uid'])))))
		
	  if($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "")
	  {
			$post['editdate'] = my_datee('relative', $post['edittime']);
			$post['editnote'] = sprintf('This post was last modified: '.$post['editdate'].' by');
			$post['editusername'] = htmlspecialchars_uni($post['editusername']);
			$post['editedprofilelink'] = build_profile_link($post['editusername'], $post['edituid']);
			$editreason = "";
			if($post['editreason'] != "")
			{
				$post['editreason'] = $parser->parse_badwords($post['editreason']);
				$post['editreason'] = htmlspecialchars_uni($post['editreason']);

				eval("\$editreason = \"".$templates->get("postbit_editedby_editreason")."\";");
				
			}
			eval("\$post['editedmsg'] = \"".$templates->get("postbit_editedby")."\";");
		}

		$time = TIMENOW;
		
		$is_mod = is_mod($usergroups);
		
		if($is_mod || ($forumpermissions['caneditposts'] == 1 && $CURUSER['id'] == $post['uid'] && $thread['closed'] != 1 && $CURUSER['id'] != 0))
        {
		    eval("\$post['input_editreason'] = \"".$templates->get("postbit_editreason")."\";");
			eval("\$post['button_edit'] = \"".$templates->get("postbit_edit")."\";");
		}

		// Quick Delete button
		$can_delete_thread = $can_delete_post = 0;
		if($CURUSER['id'] == $post['uid'] && $thread['closed'] == 0)
		{
			if($forumpermissions['candeletethreads'] == 1 && $postcounter == 1)
			{
				$can_delete_thread = 1;
			}
			else if($forumpermissions['candeleteposts'] == 1 && $postcounter != 1)
			{
				$can_delete_post = 1;
			}
		}

		$postbit_qdelete = $postbit_qrestore = '';
		if($CURUSER['id'] != 0)
		{
			
			if(($is_mod  || $can_delete_post == 1) && $postcounter != 1)
			{
				$postbit_qdelete = 'postbit_qdelete_post';
				$display = '';
				if($post['visible'] == -1)
				{
					$display = "none";
				}
				
	
				eval("\$post['button_quickdelete'] = \"".$templates->get("postbit_quickdelete")."\";");
				
				
				
			}
			else if(($is_mod || $can_delete_thread == 1) && $postcounter == 1)
			{
				$postbit_qdelete = 'postbit_qdelete_thread';
				$display = '';
				if($post['visible'] == -1)
				{
					$display = "none";
				}
				eval("\$post['button_quickdelete'] = \"".$templates->get("postbit_quickdelete")."\";");
			}

			// Restore Post
			//if(is_moderator($fid, "canrestoreposts") && $postcounter != 1)
			//{
			//	$display = "none";
			//	if($post['visible'] == -1)
			//	{
			//		$display = '';
			//	}
			///	$postbit_qrestore = $lang->postbit_qrestore_post;
			//	eval("\$post['button_quickrestore'] = \"".$templates->get("postbit_quickrestore")."\";");
			//}

			// Restore Thread
			//else if(is_moderator($fid, "canrestorethreads") && $postcounter == 1)
			//{
			//	$display = "none";
			//	if($post['visible'] == -1)
			//	{
			//		$display = "";
			//	}
			//	$postbit_qrestore = $lang->postbit_qrestore_thread;
			//	eval("\$post['button_quickrestore'] = \"".$templates->get("postbit_quickrestore")."\";");
			//}
		}

		//if(!isset($ismod))
		//{
			//$ismod = is_moderator($fid);
		//}

		// Inline moderation stuff
		if ($is_mod)
		{
			if(isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|".$post['pid']."|") !== false)
			//if(strstr($_COOKIE[$inlinecookie], "|".$post['pid']."|"))
			{
				$inlinecheck = "checked=\"checked\"";
				$inlinecount++;
			}
			else
			{
				$inlinecheck = "";
			}

			eval("\$post['inlinecheck'] = \"".$templates->get("postbit_inlinecheck")."\";");
			
			
			if($post['visible'] == 0)
			{
				$invisiblepost = 1;
			}
		}
		else
		{
			$post['inlinecheck'] = "";
		}
		
		$post['postlink'] = get_post_link($post['pid'], $post['tid']);
		$post_number = ts_nf($postcounter);
		
		eval("\$post['posturl'] = \"".$templates->get("postbit_posturl")."\";");
		
		
			
		
		
		global $forum, $thread;

		
		$is_mod = is_mod($usergroups);
		
		
			
		if($forum['open'] != 0 && ($thread['closed'] != 1 || $is_mod) && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads'])))
	    {
			eval("\$post['button_quote'] = \"".$templates->get("postbit_quote")."\";");	
		}

		$multiquote = "1";
		
		
		if($forumpermissions['canpostreplys'] != 0 && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads'])) && ($thread['closed'] != 1 || $is_mod) && $multiquote != 0 && $forum['open'] != 0 && !$post_type)
		{
			
           eval("\$post['button_multiquote'] = \"".$templates->get("postbit_multiquote")."\";");
				
		}

		if(isset($post['reporters']))
		{
			$skip_report = my_unserialize($post['reporters']);
			if(is_array($skip_report))
			{
				$skip_report[] = 0;
			}
			else
			{
				$skip_report = array(0);
			}
		}
		else
		{
			$skip_report = array(0);
		}

		//$reportable = user_permissions($post['uid']);
		//if(!in_array($mybb->user['uid'], $skip_report) && !empty($reportable['canbereported']))
		//{
		//	eval("\$post['button_report'] = \"".$templates->get("postbit_report")."\";");
		//}
	}
	elseif($post_type == 3) // announcement
	{
		
		$is_mod = is_mod($usergroups);
		
		if($is_mod)
		{
			eval("\$post['button_edit'] = \"".$templates->get("announcement_edit")."\";");
			eval("\$post['button_quickdelete'] = \"".$templates->get("announcement_quickdelete")."\";");

		}
	}

	$post['iplogged'] = '';
	
	
	$logip = "hide";
	
	$show_ips = $logip;
	
	$showpmip = "hide";
	
	// Show post IP addresses... PMs now can have IP addresses too as of 1.8!
	if($post_type == 2)
	{
		$show_ips = $showpmip;
	}
	if(!$post_type || $post_type == 2)
	{
		if($show_ips != "no" && !empty($post['ipaddress']))
		{
			//$ipaddress = my_inet_ntop($db->unescape_binary($post['ipaddress']));

			if($show_ips == "show")
			{
				eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_show")."\";");
			}
			//else if($show_ips == "hide" && (is_moderator($fid, "canviewips") || $mybb->usergroup['issupermod']))
			//{
			//	$action = 'getip';
			//	$javascript = 'getIP';

			//	if($post_type == 2)
			//	{
			//		$action = 'getpmip';
			//		$javascript = 'getPMIP';
			//	}

			//	eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_hiden")."\";");
			//}
		}
	}

	$post['poststatus'] = '';
	if(!$post_type && $post['visible'] != 1)
	{
		$status_type = '';
		//if(is_moderator($fid, "canviewdeleted") && $postcounter != 1 && $post['visible'] == -1)
		//{
			//$status_type = $lang->postbit_post_deleted;
		//}
		//else if(is_moderator($fid, "canviewunapprove") && $postcounter != 1 && $post['visible'] == 0)
		
		if($postcounter != 1 && $post['visible'] == 0)
		{
			$status_type = $lang->global['postbit_post_unapproved'];
		}
		//else if(is_moderator($fid, "canviewdeleted") && $postcounter == 1 && $post['visible'] == -1)
		//{
			//$status_type = $lang->postbit_thread_deleted;
		//}
		//else if(is_moderator($fid, "canviewunapprove") && $postcounter == 1 && $post['visible'] == 0)
		if($postcounter == 1 && $post['visible'] == 0)
		{
			$status_type = $lang->global['postbit_thread_unapproved'];
		}

		eval("\$post['poststatus'] = \"".$templates->get("postbit_status")."\";");
	}

	

	// If we have incoming search terms to highlight - get it done.
	if(!empty($mybb->input['highlight']))
	{
		$parser_options['highlight'] = $mybb->input['highlight'];
		$post['subject'] = $parser->highlight_message($post['subject'], $parser_options['highlight']);
	}

	
	$parser_options = array(
	    "allow_html" => 1,
	    "allow_mycode" => 1,
	    "allow_smilies" => 1,
	    "allow_imgcode" => 1,
	    "allow_videocode" => 1,
	    "filter_badwords" => 1
    );
	
	$post['message'] = $parser->parse_message($post['message'], $parser_options);

	$post['attachments'] = '';
	
	$enableattachments = "1";
	if($enableattachments != 0)
	{
		get_post_attachments($id, $post);
	}

	//if(isset($post['includesig']) && $post['includesig'] != 0 && $post['username'] && $post['signature'] != "" && ($mybb->user['uid'] == 0 || $mybb->user['showsigs'] != 0)
	//&& ($post['suspendsignature'] == 0 || $post['suspendsignature'] == 1 && $post['suspendsigtime'] != 0 && $post['suspendsigtime'] < TIME_NOW) && $usergroup['canusesig'] == 1
	//&& ($usergroup['canusesigxposts'] == 0 || $usergroup['canusesigxposts'] > 0 && $postnum > $usergroup['canusesigxposts']) && !is_member($mybb->settings['hidesignatures']))
	
	
	//if(isset($post['includesig']) && $post['includesig'] != 0 && $post['username'] && $post['signature'] != "" && ($CURUSER['id'] == 0 || $CURUSER['showsigs'] != 0))
		
	if($post['username'] && $post['signature'] != "" && ($CURUSER['id'] == 0 || $CURUSER['showsigs'] != 0))
	{
		$sig_parser = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"me_username" => 1,
			"filter_badwords" => 1
		);

		//if($usergroup['signofollow'])
		//{
			//$sig_parser['nofollow_on'] = 1;
		//}

		//if($mybb->user['uid'] != 0 && $mybb->user['showimages'] != 1 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
		//{
		//	$sig_parser['allow_imgcode'] = 0;
		//}

		$post['signature'] = $parser->parse_message($post['signature'], $sig_parser);
		
		$post['signature'] = '
		
		
		<div class="signature scaleimages mt-4">
	<hr />
	'.$post['signature'].'
</div>
		
		
		
		';
	}
	else
	{
		$post['signature'] = "";
	}

	//$icon_cache = $cache->read("posticons");

	//if(isset($post['icon']) && $post['icon'] > 0 && $icon_cache[$post['icon']])
	//{
	//	$icon = $icon_cache[$post['icon']];

	//	$icon['path'] = htmlspecialchars_uni($icon['path']);
	//	$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
	//	$icon['name'] = htmlspecialchars_uni($icon['name']);
		
		
	//	$post['icon'] = '<img src="'.$icon['path'].'" alt="'.$icon['name'].'" title="'.$icon['name'].'" style="vertical-align: middle;" />&nbsp;';
	//}
	//else
	//{
	//	$post['icon'] = "";
	//}

	$post_visibility = $ignore_bit = $deleted_bit = '';
	switch($post_type)
	{
		case 1: // Message preview
			$post = $plugins->run_hooks("postbit_prev", $post);
			break;
		case 2: // Private message
			$post = $plugins->run_hooks("postbit_pm", $post);
			break;
		case 3: // Announcement
			$post = $plugins->run_hooks("postbit_announcement", $post);
			break;
		default: // Regular post
			$post = $plugins->run_hooks("postbit", $post);

			if(!isset($ignored_users))
			{
				$ignored_users = array();
				if($CURUSER['id'] > 0 && $CURUSER['ignorelist'] != "")
				{
					$ignore_list = explode(',', $CURUSER['ignorelist']);
					foreach($ignore_list as $uid)
					{
						$ignored_users[$uid] = 1;
					}
				}
			}

			// Has this post been deleted but can be viewed? Hide this post
			//if($post['visible'] == -1 && is_moderator($fid, "canviewdeleted"))
			//{
			//	$deleted_message = sprintf($lang->postbit_deleted_post_user, $post['username']);
			//	eval("\$deleted_bit = \"".$templates->get("postbit_deleted")."\";");
			//	$post_visibility = "display: none;";
			//}

			// Is the user (not moderator) logged in and have unapproved posts?
			//if($CURUSER['id'] && $post['visible'] == 0 && $post['uid'] == $CURUSER['id'] && !is_moderator($fid, "canviewunapprove"))
			if($CURUSER['id'] && $post['visible'] == 0 && $post['uid'] == $CURUSER['id'])
			{
				$ignored_message = sprintf('The post made by you is under moderation and currently not visible publicly. The post will be visible to everyone once a moderator approves it');
				
				eval("\$ignore_bit = \"".$templates->get("postbit_ignored")."\";");
				
				$post_visibility = "display: none;";
			}

			// Is this author on the ignore list of the current user? Hide this post
			if(is_array($ignored_users) && $post['uid'] != 0 && isset($ignored_users[$post['uid']]) && $ignored_users[$post['uid']] == 1 && empty($deleted_bit))
			{
				$ignored_message = sprintf('The contents of this message are hidden because '.$post['username'].' is on your <a href="usercp.php?action=editlists">ignore list</a>');
				
				eval("\$ignore_bit = \"".$templates->get("postbit_ignored")."\";");
				
				$post_visibility = "display: none;";
			}
			break;
	}

	//if($post_type == 0 && $forumpermissions['canviewdeletionnotice'] == 1 && $post['visible'] == -1 && !is_moderator($fid, "canviewdeleted"))
	if($post_type == 0 && $post['visible'] == -1)
	{
		$postbit = '
		
		<div class="row mb-4 ps-5 pe-5">
<div class="col align-self-center">
	<a id="pid'.$post['pid'].'" name="pid'.$post['pid'].'"></a>
	<div id="post_'.$post['pid'].'" class="post deleted_post_hidden"></div>
<i class="bi bi-info-circle"></i> This post has been deleted
</div>
</div>';
		
		
		
		
		
	}
	else
	{
		
		$postlayout = "horizontal";
		
		if($postlayout == "classic")
		{
			eval("\$postbit = \"".$templates->get("postbit_classic")."\";");
		}
		else
		{

			eval("\$postbit = \"".$templates->get("postbit")."\";");	
		
		}
	}

	$GLOBALS['post'] = "";

	return $postbit;
}

/**
 * Fetch the attachments for a specific post and parse inline [attachment=id] code.
 * Note: assumes you have $attachcache, an array of attachments set up.
 *
 * @param int $id The ID of the item.
 * @param array $post The post or item passed by reference.
 */
function get_post_attachments($id, &$post)
{
	global $attachcache, $mybb, $theme, $templates, $forumpermissions, $lang;

	$validationcount = 0;
	$tcount = 0;
	$post['attachmentlist'] = $post['thumblist'] = $post['imagelist'] = '';
	if(!isset($forumpermissions))
	{
		$forumpermissions = forum_permissions($post['fid']);
	}

	if(isset($attachcache[$id]) && is_array($attachcache[$id]))
	{ // This post has 1 or more attachments
		foreach($attachcache[$id] as $aid => $attachment)
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
				$attachment['filesize'] = mksize($attachment['filesize']);
				$ext = get_extension($attachment['filename']);
				if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
				{
					$isimage = true;
				}
				else
				{
					$isimage = false;
				}
				$attachment['icon'] = get_attachment_icon($ext);
				$attachment['downloads'] = ts_nf($attachment['downloads']);

				if(!$attachment['dateuploaded'])
				{
					$attachment['dateuploaded'] = $post['dateline'];
				}
				$attachdate = my_datee('normal', $attachment['dateuploaded']);
				// Support for [attachment=id] code
				if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
					// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
					// Show as download for all other cases
					
					$attachthumbnails = "yes";
					
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $attachthumbnails == "yes")
					{
						$attbit = '
						
						
						
						<div class="col-auto"><a href="attachment.php?aid='.$attachment['aid'].'" target="_blank">
						<img src="attachment.php?thumbnail='.$attachment['aid'].'" style="width:80px; height: 80px" class="img-thumbnail display-inline" alt="" title="'.$lang->global['postbit_attachment_filename'].' '.$attachment['filename'].'
'.$lang->global['postbit_attachment_size'].' '.$attachment['filesize'].'
	'.$attachdate.'" /></a></div>
						
						
						
						
						
						';
					}
					
					
					
					elseif((($attachment['thumbnail'] == "SMALL") || $attachthumbnails == "no") && $isimage)
					{
						$attbit = '
						
						
						
						<img src="attachment.php?aid='.$attachment['aid'].'" class="attachment" style="width:16px; height:16px;" alt="" title="'.$lang->global['postbit_attachment_filename'].' '.$attachment['filename'].'
'.$lang->global['postbit_attachment_size'].' '.$attachment['filesize'].'
'.$attachdate.'" />&nbsp;&nbsp;&nbsp;
						
						
						
						
						
						';
					}
					else
					{
						
						$attbit = '
						
						
						<div class="row mt-2 g-1 text-muted">
	<div class="col-auto align-self-center">

'.$attachment['icon'].'
		
	</div>
	<div class="col align-self-center">
		<a href="attachment.php?aid='.$attachment['aid'].'" target="_blank" title="'.$attachdate.'">'.$attachment['filename'].'</a> ('.$lang->global['postbit_attachment_size'].' <span class="text-dark">'.$attachment['filesize'].'</span> {$lang->postbit_attachment_downloads} <span class="text-dark">'.$attachment['downloads'].')</span>
	</div>
</div>
						
						
						';
						
						
					}
					$post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $post['message']);
				}
				else
				{
					// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
					// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
					// Show as download for all other cases
					
					$attachthumbnails = "yes";
					
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $attachthumbnails == "yes")
					{
						$post['thumblist'] .= '
						
						
						
						<div class="col-auto"><a href="attachment.php?aid='.$attachment['aid'].'" target="_blank">
						<img src="attachment.php?thumbnail='.$attachment['aid'].'" style="width:80px; height: 80px" class="img-thumbnail display-inline" alt="" title="'.$lang->global['postbit_attachment_filename'].' '.$attachment['filename'].'
'.$lang->global['postbit_attachment_size'].' '.$attachment['filesize'].'
	'.$attachdate.'" /></a></div>
						
						
						
						
						
						';
						
						
						if($tcount == 5)
						{
							$post['thumblist'] .= "<br />";
							$tcount = 0;
						}
						++$tcount;
					}
					elseif((($attachment['thumbnail'] == "SMALL") || $attachthumbnails == "no") && $isimage)
					{
						if ($forumpermissions['candlattachments'])
						{
						     $post['imagelist'] .= '
							 
							 
							 
							 <img src="attachment.php?aid='.$attachment['aid'].'" class="attachment" style="width:16px; height:16px;" alt="" title="'.$lang->global['postbit_attachment_filename'].' '.$attachment['filename'].'
'.$lang->global['postbit_attachment_size'].' '.$attachment['filesize'].'
'.$attachdate.'" />&nbsp;&nbsp;&nbsp;
							 
							 
							 
							 
							 
							 
							 
							 
							 ';
						} 
						else 
						{
							$post['thumblist'] .= '
							
							
							<div class="col-auto"><a href="attachment.php?aid='.$attachment['aid'].'" target="_blank">
							<img src="attachment.php?thumbnail='.$attachment['aid'].'" style="width:80px; height: 80px" class="img-thumbnail display-inline" alt="" title="'.$lang->global['postbit_attachment_filename'].' '.$attachment['filename'].'
'.$lang->global['postbit_attachment_size'].' '.$attachment['filesize'].'
	'.$attachdate.'" /></a></div>
							
							
							
							
							';
							
							
							
							if($tcount == 5)
							{
								$post['thumblist'] .= "<br />";
								$tcount = 0;
							}
							++$tcount;
						}
					}
					else
					{
						$post['attachmentlist'] .= '
						
						<div class="row mt-2 g-1 text-muted">
	<div class="col-auto align-self-center">

'.$attachment['icon'].'
		
	</div>
	<div class="col align-self-center">
		<a href="attachment.php?aid='.$attachment['aid'].'" target="_blank" title="'.$attachdate.'">'.$attachment['filename'].'</a> (Size: <span class="text-dark">'.$attachment['filesize'].'</span> Downloads: <span class="text-dark">'.$attachment['downloads'].')</span>
	</div>
</div>
						
						';
					}
				}
			}
			else
			{
				$validationcount++;
			}
		}
		if($validationcount > 0)
		{
			if($validationcount == 1)
			{
				$postbit_unapproved_attachments = $lang->global['postbit_unapproved_attachment'];
			}
			else
			{
				$postbit_unapproved_attachments = sprintf($lang->global['postbit_unapproved_attachments'], $validationcount);
			}
			eval("\$post['attachmentlist'] .= \"".$templates->get("postbit_attachments_attachment_unapproved")."\";");
		}
		if($post['thumblist'])
		{
			$post['attachedthumbs'] = '
			
			<div class="row mt-2">
	         '.$post['thumblist'].'
			 </div>';
			
			
			
		}
		else
		{
			$post['attachedthumbs'] = '';
		}
		if($post['imagelist'])
		{
			$post['attachedimages'] = '{$lang->postbit_attachments_images}<br />
          '.$post['imagelist'].'
           <br />';
		   
		   
		}
		else
		{
			$post['attachedimages'] = '';
		}
		if($post['attachmentlist'] || $post['thumblist'] || $post['imagelist'])
		{
			$post['attachments'] = '
			
			<div class="mb-0 mt-4">
	<i class="fa-solid fa-paperclip"></i> <strong>Attached Files</strong>
	<hr />
'.$post['attachmentlist'].'
'.$post['attachedthumbs'].'
'.$post['attachedimages'].'
</div>
			
			
			';
		}
	}
}

/**
 * Returns bytes count from human readable string
 * Used to parse ini_get human-readable values to int
 *
 * @param string $val Human-readable value
 */
function return_bytes($val) {
	$val = trim($val);
	if ($val == "")
	{
		return 0;
	}

	$last = strtolower($val[strlen($val)-1]);

	$val = intval($val);

	switch($last)
	{
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

/**
 * Detects whether an attachment removal/approval/unapproval
 * submit button was pressed (without triggering an AJAX request)
 * and sets inputs accordingly (as for an AJAX request).
 */
function detect_attachmentact()
{
	global $mybb;

	foreach($mybb->input as $key => $val)
	{
		if(strpos($key, 'rem_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 4);
			$mybb->input['attachmentact'] = 'remove';
			break;
		}
		elseif(strpos($key, 'approveattach_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 14);
			$mybb->input['attachmentact'] = 'approve';
			break;
		}
		elseif(strpos($key, 'unapproveattach_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 16);
			$mybb->input['attachmentact'] = 'unapprove';
			break;
		}
	}
}
