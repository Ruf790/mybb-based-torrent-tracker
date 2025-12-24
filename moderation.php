<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */


define('IN_FORUM', true);
require_once 'global.php';

  
  
require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/functions_upload.php";

require_once INC_PATH . '/class_moderation.php';
$moderation = new Moderation;
  


// Load global language phrases
$lang->load("moderation");

$plugins->run_hooks("moderation_start");

$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
$modal = $mybb->get_input('modal', MyBB::INPUT_INT);

if($CURUSER['id'] == 0)
{
	
	print_no_permission();
}


if($pid)
{
	$post = get_post($pid);
	if(!$post)
	{
		error('error_invalidpost', $lang->error);
	}
	$tid = $post['tid'];
}

if($tid)
{
	$thread = get_thread($tid);
	if(!$thread)
	{
		error('error_invalidthread', $lang->error);
	}
	$fid = $thread['fid'];
}

if($fid)
{
	$modlogdata['fid'] = $fid;
	$forum = get_forum($fid);

	// Make navigation
	build_forum_breadcrumb($fid);

	// Get our permissions all nice and setup
	//$permissions = forum_permissions($fid);
}

if($pmid > 0)
{
	$query = $db->simple_select('privatemessages', 'uid, subject, ipaddress, fromid', "pmid = $pmid");

	$pm = $db->fetch_array($query);

	if(!$pm)
	{
		error($lang->error_invalidpm, $lang->error);
	}
}

// Get some navigation if we need it
$mybb->input['action'] = $mybb->get_input('action');
switch($mybb->input['action'])
{
	case "reports":
		add_breadcrumb($lang->reported_posts);
		break;
	case "allreports":
		add_breadcrumb($lang->all_reported_posts);
		break;

}

if(isset($thread))
{
	$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
	add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
	$modlogdata['tid'] = $thread['tid'];
}

if(isset($forum))
{
	// Check if this forum is password protected and we have a valid password
	check_forum_password($forum['fid']);
}

$log_multithreads_actions = array("do_multideletethreads", "multiclosethreads", "multiopenthreads", "multiapprovethreads", "multiunapprovethreads", "multirestorethreads", "multisoftdeletethreads","multistickthreads", "multiunstickthreads", "do_multimovethreads");
if(in_array($mybb->input['action'], $log_multithreads_actions))
{
	if(!empty($mybb->input['searchid']))
	{
		$tids = getids($mybb->get_input('searchid'), 'search');
	}
	else
	{
		$tids = getids($fid, 'forum');
	}

	$modlogdata['tids'] = (array)$tids;

	unset($tids);
}

$CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);




eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");

$allowable_moderation_actions = array("getip", "getpmip", "cancel_delayedmoderation", "delayedmoderation", "threadnotes", "purgespammer", "viewthreadnotes");

if($mybb->request_method != "post" && !in_array($mybb->input['action'], $allowable_moderation_actions))
{
	print_no_permission();
}

// Begin!
switch($mybb->input['action'])
{
	// Delayed Moderation
	case "cancel_delayedmoderation":
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		add_breadcrumb($lang->moderation['delayed_moderation']);
		//if(!is_moderator($fid, "canmanagethreads"))
		//{
			//print_no_permission();
		//}

		$plugins->run_hooks('moderation_cancel_delayedmoderation');

		$db->delete_query("delayedmoderation", "did='".$mybb->get_input('did', MyBB::INPUT_INT)."'");

		if($tid == 0)
		{
			moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_delayed_moderation_cancelled']);
		}
		else
		{
			moderation_redirect("moderation.php?action=delayedmoderation&amp;tid={$tid}&amp;my_post_key={$mybb->post_code}", $lang->moderation['redirect_delayed_moderation_cancelled']);
		}
		break;
	case "do_delayedmoderation":
	case "delayedmoderation":
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));
		
		$localized_time_offset = $CURUSER['timezone']*3600 + $CURUSER['dst']*3600;

		if(!$mybb->get_input('date_day', MyBB::INPUT_INT))
		{
			$mybb->input['date_day'] = gmdate('d', TIMENOW + $localized_time_offset);
		}
		if(!$mybb->get_input('date_month', MyBB::INPUT_INT))
		{
			$mybb->input['date_month'] = gmdate('m', TIMENOW + $localized_time_offset);
		}

		// Assume in-line moderation if TID is not set
		if(!empty($mybb->input['tid']))
		{
			$mybb->input['tids'] = $tid;
		}
		else
		{
			if($mybb->get_input('inlinetype') == 'search')
			{
				$tids = getids($mybb->get_input('searchid'), 'search');
			}
			else
			{
				$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
				$tids = getids($fid, "forum");
			}
			if(count($tids) < 1)
			{
				error($lang->error_inline_nothreadsselected, $lang->error);
			}

			$mybb->input['tids'] = $tids;
		}

		add_breadcrumb($lang->delayed_moderation);

		

		$errors = array();
		$customthreadtools = "";

		$allowed_types = array('move', 'merge', 'removeredirects', 'removesubscriptions');

		
		$allowed_types[] = "openclosethread";
        $allowed_types[] = "deletethread";
	    $allowed_types[] = "stick";
		$allowed_types[] = "approveunapprovethread";
		

		$mybb->input['type'] = $mybb->get_input('type');

		
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->simple_select("modtools", 'tid, name, `groups`', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND type = 't'");
					break;
				default:
					$query = $db->simple_select("modtools", 'tid, name, `groups`', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND type = 't'");
			}
			while($tool = $db->fetch_array($query))
			{
				if(is_member($tool['groups']))
				{
					$allowed_types[] = "modtool_".$tool['tid'];

					$tool['name'] = htmlspecialchars_uni($tool['name']);

					$checked = "";
					if($mybb->input['type'] == "modtool_".$tool['tid'])
					{
						$checked = "checked=\"checked\"";
					}

					eval("\$customthreadtools .= \"".$templates->get("moderation_delayedmoderation_custommodtool")."\";");
				}
			}
		

		$mybb->input['delayedmoderation'] = $mybb->get_input('delayedmoderation', MyBB::INPUT_ARRAY);

		if($mybb->input['action'] == "do_delayedmoderation" && $mybb->request_method == "post")
		{
			if(!in_array($mybb->input['type'], $allowed_types))
			{
				$mybb->input['type'] = '';
				$errors[] = $lang->error_delayedmoderation_unsupported_type;
			}

			if($mybb->input['type'] == 'move' && (!isset($mybb->input['delayedmoderation']['method']) || !in_array($mybb->input['delayedmoderation']['method'], array('move', 'redirect', 'copy'))))
			{
				$mybb->input['delayedmoderation']['method'] = '';
				$errors[] = $lang->error_delayedmoderation_unsupported_method;
			}

			if($mybb->input['type'] == 'move')
			{
				$newfid = (int)$mybb->input['delayedmoderation']['new_forum'];

				// Make sure moderator has permission to move to the new forum
				$newperms = forum_permissions($newfid);
				//if($newperms['canview'] == 0 || !is_moderator($newfid, 'canmovetononmodforum'))
				//{
				//	$errors[] = $lang->error_movetononmodforum;
				//}

				$newforum = get_forum($newfid);
				if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
				{
					$errors[] = 'Invalid forum';
				}

				$method = $mybb->input['delayedmoderation']['method'];
				if($method != "copy" && $fid == $newfid)
				{
					$errors[] = $lang->moderation['error_movetosameforum'];
				}
			}

			if($mybb->input['date_day'] > 31 || $mybb->input['date_day'] < 1)
			{
				$errors[] = $lang->moderation['error_delayedmoderation_invalid_date_day'];
			}

			if($mybb->input['date_month'] > 12 || $mybb->input['date_month'] < 1)
			{
				$errors[] = $lang->moderation['error_delayedmoderation_invalid_date_month'];
			}

			if($mybb->input['date_year'] < gmdate('Y', TIMENOW + $localized_time_offset))
			{
				$errors[] = $lang->moderation['error_delayedmoderation_invalid_date_year'];
			}

			$date_time = explode(' ', $mybb->get_input('date_time'));
			$date_time = explode(':', (string)$date_time[0]);

			if(stristr($mybb->input['date_time'], 'pm'))
			{
				$date_time[0] = 12+$date_time[0];
				if($date_time[0] >= 24)
				{
					$date_time[0] = '00';
				}
			}

			$rundate = gmmktime((int)$date_time[0], (int)$date_time[1], date('s', TIMENOW), $mybb->get_input('date_month', MyBB::INPUT_INT), $mybb->get_input('date_day', MyBB::INPUT_INT), $mybb->get_input('date_year', MyBB::INPUT_INT)) - $localized_time_offset;

			if(!$errors)
			{
				if(is_array($mybb->input['tids']))
				{
					$mybb->input['tids'] = implode(',', $mybb->input['tids']);
				}

				$did = $db->insert_query("delayedmoderation", array(
					'type' => $db->escape_string($mybb->input['type']),
					'delaydateline' => (int)$rundate,
					'uid' => $CURUSER['id'],
					'tids' => $db->escape_string($mybb->input['tids']),
					'fid' => $fid,
					'dateline' => TIMENOW,
					'inputs' => $db->escape_string(my_serialize($mybb->input['delayedmoderation']))
				));

				$plugins->run_hooks('moderation_do_delayedmoderation');

				$rundate_format = my_datee('relative', $rundate, '', 2);
				$redirect_delayed_moderation_thread = sprintf($lang->moderation['redirect_delayed_moderation_thread'], $rundate_format);

				if(!empty($mybb->input['tid']))
				{
					moderation_redirect(get_thread_link($thread['tid']), $redirect_delayed_moderation_thread);
				}
				else
				{
					if($mybb->get_input('inlinetype') == 'search')
					{
						moderation_redirect(get_forum_link($fid), sprintf($lang->moderation['redirect_delayed_moderation_search'], $rundate_format));
					}
					else
					{
						moderation_redirect(get_forum_link($fid), sprintf($lang->moderation['redirect_delayed_moderation_forum'], $rundate_format));
					}
				}
			}
			else
			{
				$type_selected = array();
				foreach($allowed_types as $type)
				{
					$type_selected[$type] = '';
				}
				$type_selected[$mybb->get_input('type')] = "checked=\"checked\"";
				$method_selected = array('move' => '', 'redirect' => '', 'copy' => '');
				if(isset($mybb->input['delayedmoderation']['method']))
				{
					$method_selected[$mybb->input['delayedmoderation']['method']] = "checked=\"checked\"";
				}

				foreach(array('redirect_expire', 'new_forum', 'subject', 'threadurl') as $value)
				{
					if(!isset($mybb->input['delayedmoderation'][$value]))
					{
						$mybb->input['delayedmoderation'][$value] = '';
					}
				}
				$mybb->input['delayedmoderation']['redirect_expire'] = (int)$mybb->input['delayedmoderation']['redirect_expire'];
				$mybb->input['delayedmoderation']['new_forum'] = (int)$mybb->input['delayedmoderation']['new_forum'];
				$mybb->input['delayedmoderation']['subject'] = htmlspecialchars_uni($mybb->input['delayedmoderation']['subject']);
				$mybb->input['delayedmoderation']['threadurl'] = htmlspecialchars_uni($mybb->input['delayedmoderation']['threadurl']);

				$forumselect = build_forum_jump("", $mybb->input['delayedmoderation']['new_forum'], 1, '', 0, true, '', "delayedmoderation[new_forum]");
			}
		}
		else
		{
			$type_selected = array();
			foreach($allowed_types as $type)
			{
				$type_selected[$type] = '';
			}
			$type_selected['openclosethread'] = "checked=\"checked\"";
			$method_selected = array('move' => 'checked="checked"', 'redirect' => '', 'copy' => '');

			$mybb->input['delayedmoderation']['redirect_expire'] = '';
			$mybb->input['delayedmoderation']['subject'] = isset($thread['subject']) ? $thread['subject'] : '';
			$mybb->input['delayedmoderation']['threadurl'] = '';

			$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "delayedmoderation[new_forum]");
		}

		if(isset($errors) && count($errors) > 0)
		{
			$display_errors = inline_error($errors);
		}
		else
		{
			$display_errors = '';
		}

		$forum_cache = $cache->read("forums");

		$actions = array(
			'openclosethread' => $lang->moderation['open_close_thread'],
			'deletethread' => $lang->moderation['delete_thread'],
			'move' => $lang->moderation['move_copy_thread'],
			'stick' =>$lang->moderation['stick_unstick_thread'],
			'merge' => $lang->moderation['merge_threads'],
			'removeredirects' => $lang->moderation['remove_redirects'],
			'removesubscriptions' => $lang->moderation['remove_subscriptions'],
			'approveunapprovethread' => $lang->moderation['approve_unapprove_thread']
		);

		switch($db->type)
		{
			case "pgsql":
			case "sqlite":
				$query = $db->simple_select("modtools", 'tid, name', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND type = 't'");
				break;
			default:
				$query = $db->simple_select("modtools", 'tid, name', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND type = 't'");
		}
		while($tool = $db->fetch_array($query))
		{
			$actions['modtool_'.$tool['tid']] = htmlspecialchars_uni($tool['name']);
		}

		$delayedmods = '';
		$trow = alt_trow(1);
		if($tid == 0)
		{
			// Inline thread moderation is used
			if($mybb->get_input('inlinetype') == 'search')
			{
				$tids = getids($mybb->get_input('searchid'), 'search');
			}
			else
			{
				$tids = getids($fid, "forum");
			}
			$where_array = array();
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					foreach($tids as $like)
					{
						$where_array[] = "','||d.tids||',' LIKE '%,".$db->escape_string($like).",%'";
					}
					$where_statement = implode(" OR ", $where_array);
					break;
				default:
					foreach($tids as $like)
					{
						$where_array[] = "CONCAT(',',d.tids,',') LIKE  '%,".$db->escape_string($like).",%'";
					}
					$where_statement = implode(" OR ", $where_array);
			}
			$query = $db->sql_query("
				SELECT d.*, u.username, f.name AS fname
				FROM delayedmoderation d
				LEFT JOIN users u ON (u.id=d.uid)
				LEFT JOIN tsf_forums f ON (f.fid=d.fid)
				WHERE ".$where_statement."
				ORDER BY d.dateline DESC
				LIMIT  0, 20
			");
		}
		else
		{
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$query = $db->sql_query("
						SELECT d.*, u.username, f.name AS fname
						FROM delayedmoderation d
						LEFT JOIN users u ON (u.id=d.uid)
						LEFT JOIN tsf_forums f ON (f.fid=d.fid)
						WHERE ','||d.tids||',' LIKE '%,{$tid},%'
						ORDER BY d.dateline DESC
						LIMIT  0, 20
					");
					break;
				default:
					$query = $db->sql_query("
						SELECT d.*, u.username, f.name AS fname
						FROM delayedmoderation d
						LEFT JOIN users u ON (u.id=d.uid)
						LEFT JOIN tsf_forums f ON (f.fid=d.fid)
						WHERE CONCAT(',',d.tids,',') LIKE '%,{$tid},%'
						ORDER BY d.dateline DESC
						LIMIT  0, 20
					");
			}
		}

		while($delayedmod = $db->fetch_array($query))
		{
			$delayedmod['dateline'] = my_datee('normal', $delayedmod['delaydateline'], "", 2);
			$delayedmod['username'] = htmlspecialchars_uni($delayedmod['username']);
			$delayedmod['profilelink'] = build_profile_link($delayedmod['username'], $delayedmod['uid']);
			$delayedmod['action'] = $actions[$delayedmod['type']];
			$info = '';
			if(strpos($delayedmod['tids'], ',') === false)
			{
				$delayed_thread = get_thread($delayedmod['tids']);
				$delayed_thread['link'] = get_thread_link($delayed_thread['tid']);
				$delayed_thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($delayed_thread['subject']));
				eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_thread_single")."\";");
			}
			else
			{
				eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_thread_multiple")."\";");
			}

			if($delayedmod['fname'])
			{
				$delayedmod['link'] = get_forum_link($delayedmod['fid']);
				$delayedmod['fname'] = htmlspecialchars_uni($delayedmod['fname']);
				eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_forum")."\";");
			}
			$delayedmod['inputs'] = my_unserialize($delayedmod['inputs']);

			if($delayedmod['type'] == 'move')
			{
				$delayedmod['link'] = get_forum_link($delayedmod['inputs']['new_forum']);
				$delayedmod['name'] = htmlspecialchars_uni($forum_cache[$delayedmod['inputs']['new_forum']]['name']);
				eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_new_forum")."\";");

				if($delayedmod['inputs']['method'] == "redirect")
				{
					if((int)$delayedmod['inputs']['redirect_expire'] == 0)
					{
						$redirect_expire_bit = $lang->redirect_forever;
					}
					else
					{
						$redirect_expire_bit = (int)$delayedmod['inputs']['redirect_expire']." {$lang->days}";
					}

					eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_redirect")."\";");
				}
			}
			elseif($delayedmod['type'] == 'merge')
			{
				$delayedmod['subject'] = htmlspecialchars_uni($delayedmod['inputs']['subject']);
				$delayedmod['threadurl'] = htmlspecialchars_uni($delayedmod['inputs']['threadurl']);
				eval("\$info .= \"".$templates->get("moderation_delayedmodaction_notes_merge")."\";");
			}

			eval("\$delayedmods .= \"".$templates->get("moderation_delayedmodaction_notes")."\";");
			$trow = alt_trow();
		}
		if(!$delayedmods)
		{
			$cols = 5;
			eval("\$delayedmods = \"".$templates->get("moderation_delayedmodaction_error")."\";");
		}

		$url = '';
		if($mybb->get_input('tid', MyBB::INPUT_INT))
		{
			$lang->threads = $lang->thread;
			$thread['link'] = get_thread_link($tid);
			$delayedmoderation_subject = $mybb->input['delayedmoderation']['subject'];
			$delayedmoderation_threadurl = $mybb->input['delayedmoderation']['threadurl'];
			eval("\$threads = \"".$templates->get("moderation_delayedmoderation_thread")."\";");
			eval("\$moderation_delayedmoderation_merge = \"".$templates->get("moderation_delayedmoderation_merge")."\";");
		}
		else
		{
			if($mybb->get_input('inlinetype') == 'search')
			{
				$tids = getids($mybb->get_input('searchid'), 'search');
				$url = htmlspecialchars_uni($mybb->get_input('url'));
			}
			else
			{
				$tids = getids($fid, "forum");
			}
			if(count($tids) < 1)
			{
				stderr($lang->moderation['error_inline_nothreadsselected']);
			}

			$threads = sprintf($lang->moderation['threads_selected'], count($tids));
			$moderation_delayedmoderation_merge = '';
		}
		$redirect_expire = $mybb->get_input('redirect_expire');
		eval("\$moderation_delayedmoderation_move = \"".$templates->get("moderation_delayedmoderation_move")."\";");

		// Generate form elements for date form
		$dateday = '';
		for($day = 1; $day <= 31; ++$day)
		{
			$selected = '';
			if($mybb->get_input('date_day', MyBB::INPUT_INT) == $day)
			{
				$selected = ' selected="selected"';
			}
			eval('$dateday .= "'.$templates->get('moderation_delayedmoderation_date_day').'";');
		}

		$datemonth = array();
		foreach(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12') as $month)
		{
			$datemonth[$month] = '';
			if($mybb->get_input('date_month', MyBB::INPUT_INT) == (int)$month)
			{
				$datemonth[$month] = ' selected="selected"';
			}
		}
		

		eval('$datemonth = "'.$templates->get('moderation_delayedmoderation_date_month').'";');

		$dateyear = gmdate('Y', TIMENOW  + $localized_time_offset);
		$datetime = gmdate($timeformat, TIMENOW + $localized_time_offset);

		$openclosethread = '';
		//if(is_moderator($fid, "canopenclosethreads"))
		//{
			eval('$openclosethread = "'.$templates->get('moderation_delayedmoderation_openclose').'";');
		//}

	

		$deletethread = '';
		//if(is_moderator($fid, "candeletethreads"))
		//{
			eval('$deletethread = "'.$templates->get('moderation_delayedmoderation_delete').'";');
		//}

		$stickunstickthread = '';
		//if(is_moderator($fid, "canstickunstickthreads"))
		//{
			eval('$stickunstickthread = "'.$templates->get('moderation_delayedmoderation_stick').'";');
		//}

		$approveunapprovethread = '';
		//if(is_moderator($fid, "canapproveunapprovethreads"))
		//{
			eval('$approveunapprovethread = "'.$templates->get('moderation_delayedmoderation_approve').'";');
		//} 

		$plugins->run_hooks("moderation_delayedmoderation");

		eval("\$delayedmoderation = \"".$templates->get("moderation_delayedmoderation")."\";");
		
		stdhead('aaaaaaaa');
		echo $delayedmoderation;
		
		stdfoot();
		
		
		
		break;
	// Open or close a thread
	case "openclosethread":
		// Verify incoming POST request
		//verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canopenclosethreads"))
		//{
		//	error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		if($thread['closed'] == 1)
		{
			$openclose = 'opened';
			$redirect = 'redirect_openthread';
			$moderation->open_threads($tid);
		}
		else
		{
			$openclose = 'closed';
			$redirect = 'redirect_closethread';
			$moderation->close_threads($tid);
		}

		$mod_process = sprintf($lang->moderation['mod_process'], $openclose);

		log_moderator_action($modlogdata, $mod_process);

		redirect(get_thread_link($thread['tid']), $redirect);
		
		break;

	// Stick or unstick that post to the top bab!
	case "stick":
		// Verify incoming POST request
		//verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canstickunstickthreads"))
		//{
		//	error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$plugins->run_hooks("moderation_stick");

		if($thread['sticky'] == 1)
		{
			$stuckunstuck = 'unstuck';
			$redirect = 'redirect_unstickthread';
			$moderation->unstick_threads($tid);
		}
		else
		{
			$stuckunstuck = 'stuck';
			$redirect = 'redirect_stickthread';
			$moderation->stick_threads($tid);
		}

		$mod_process = sprintf($lang->moderation['mod_process'], $stuckunstuck);

		log_moderator_action($modlogdata, $mod_process);

		redirect(get_thread_link($thread['tid']), $redirect);
		
		break;

	// Remove redirects to a specific thread
	case "removeredirects":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		if($thread['visible'] == -1)
		{
			error($lang->error_thread_deleted, $lang->error);
		}

		$plugins->run_hooks("moderation_removeredirects");

		$moderation->remove_redirects($tid);

		log_moderator_action($modlogdata, $lang->redirects_removed);
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_redirectsremoved);
		break;

	// Delete thread confirmation page
	case "deletethread":

		add_breadcrumb($lang->nav_deletethread);

		//if(!is_moderator($fid, "candeletethreads"))
		//{
		//	if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
		//	{
		//		error_no_permission();
		//	}
		//}

		$plugins->run_hooks("moderation_deletethread");

		$deletethread = '
		
		
		
		<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($mybb->settings[bbname]); ?> - <?php echo htmlspecialchars($lang->delete_thread); ?></title>
	
	 <style>
        .thread-subject {
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .warning-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.2);
        }
        .warning-icon i {
            font-size: 36px;
            color: white;
        }
        .delete-btn {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            border: none;
            padding: 0.75rem 2.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(229, 62, 62, 0.3);
        }
        .delete-btn:active {
            transform: translateY(0);
        }
        .btn-cancel {
            padding: 0.75rem 2rem;
            font-weight: 500;
        }
        .consequences-list {
            list-style: none;
            padding-left: 0;
        }
        .consequences-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .consequences-list li:last-child {
            border-bottom: none;
        }
        .consequences-list li i {
            width: 24px;
            color: #e53e3e;
        }
        .card-header {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            border: none;
        }
        .thread-preview {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 1px solid #e2e8f0;
            border-left: 4px solid #e53e3e;
            padding: 1rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .confirm-text {
            color: #e53e3e;
            font-weight: 600;
            font-size: 1.1rem;
        }
    </style>
	
	

</head>
<body class="bg-gray-50">

'.stdhead ('Delete Thread').'

<div class="container mt-3">
    <div class="row justify-content-center">
        <div>
            <div class="card">
                <div class="card-header border-0 py-4">
                    <h4 class="text-white mb-0 text-center">
                        <i class="fas fa-trash-alt me-2"></i>Delete Thread
                    </h4>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <!-- Warning Icon -->
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <!-- Thread Preview -->
                    <div class="thread-preview">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-file-alt text-danger fs-4 mt-1"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1 thread-subject">
                                    '.$thread['subject'].'
                                </h5>
                                <div class="text-muted small">
                                    <span class="me-3">
                                        <i class="fas fa-hashtag me-1"></i>ID: <?php echo (int)$tid; ?>
                                    </span>
                                    '.$thread['username'].'
                                    <span>
                                        <i class="fas fa-user me-1"></i>Author: '.$thread['username'].'
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Login Box (if needed) -->
                    
                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-lock me-2 fs-5"></i>
                            <div>'.$loginbox.'</div>
                        </div>
                    </div>
                    
                    
                    <!-- Warning Message -->
                    <div class="alert alert-danger border-danger">
                        <div class="d-flex">
                            <i class="fas fa-exclamation-circle text-danger fs-5 me-3 mt-1"></i>
                            <div>
                                <h5 class="alert-heading mb-2">Permanent Deletion Warning</h5>
                                <p class="mb-2">You are about to permanently delete this thread. This action cannot be undone.</p>
                                
                                <ul class="consequences-list mt-3 mb-2">
                                    <li class="d-flex align-items-center">
                                        <i class="fas fa-comments me-2"></i>
                                        <span>All posts in this thread will be permanently deleted</span>
                                    </li>
                                    <li class="d-flex align-items-center">
                                        <i class="fas fa-paperclip me-2"></i>
                                        <span>All attachments will be removed from the server</span>
                                    </li>
                                    <li class="d-flex align-items-center">
                                        <i class="fas fa-chart-bar me-2"></i>
                                        <span>Any poll associated with this thread will be deleted</span>
                                    </li>
                                    <li class="d-flex align-items-center">
                                        <i class="fas fa-history me-2"></i>
                                        <span>Thread statistics and activity will be lost</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirmation Form -->
                    <form action="moderation.php" method="post" id="threadDeleteForm">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
                        <input type="hidden" name="action" value="do_deletethread">
                        <input type="hidden" name="tid" value="'.$tid.'">
                        
                        <!-- Confirmation Check -->
                        <div class="mb-4 p-3 bg-light rounded">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                                <label class="form-check-label confirm-text" for="confirmDelete">
                                    <i class="fas fa-check-circle me-1"></i>
                                    I understand this action is permanent and cannot be undone
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="confirmBackup" required>
                                <label class="form-check-label text-muted" for="confirmBackup">
                                    I have ensured all important content is backed up elsewhere
                                </label>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center pt-3">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-cancel">
                                <i class="fas fa-arrow-left me-1"></i>Cancel
                            </a>
                            <button type="submit" 
                                    class="btn delete-btn" 
                                    name="submit"
                                    id="deleteThreadBtn"
                                    disabled>
                                <i class="fas fa-trash-alt me-2"></i>Delete Thread Permanently
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-transparent border-top-0 pt-0 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Admin Action • '.$SITENAME.'
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>



</body>
</html>
		
		
		
		
		
		';
		
		
		
		echo $deletethread;
		
		stdfoot();
		
		break;

	// Delete the actual thread here
	case "do_deletethread":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "candeletethreads"))
		///{
		//	if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
		//	{
		//		error_no_permission();
		//	}
		//}

		$plugins->run_hooks("moderation_do_deletethread");

		// Log the subject of the deleted thread
		$modlogdata['thread_subject'] = $thread['subject'];

		$thread['subject'] = $db->escape_string($thread['subject']);
		$thread_deleted = sprintf($lang->moderation['thread_deleted'], $thread['subject']);
		log_moderator_action($modlogdata, $thread_deleted);




		$moderation->delete_thread($tid);

		mark_reports($tid, "thread");
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_threaddeleted']);
		break;

	// Delete the poll from a thread confirmation page
	case "deletepoll":
		add_breadcrumb('nav_deletepoll');

		//if(!is_moderator($fid, "canmanagepolls"))
		//{
		//	if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
		//	{
		//		error_no_permission();
		//	}
		//}

		$plugins->run_hooks("moderation_deletepoll");

		$query = $db->simple_select("tsf_polls", "pid", "tid='$tid'");
		$poll = $db->fetch_array($query);
		if(!$poll)
		{
			stderr('error_invalidpoll');
		}





$deletepoll = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$mybb->settings['bbname']} - {$lang->moderation['delete_poll']}</title>
    {$headerinclude}
    
    <style>
   
        .poll-card {
            max-width: 500px;
            margin: 0 auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .poll-header {
            background: #dc3545;
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .poll-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .btn-delete {
            background: #dc3545;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .security-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div class="poll-card">
            <div class="poll-header">
                <div class="poll-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h4 class="mb-2">{$lang->moderation['delete_poll']}</h4>
                <p class="mb-0 opacity-75">This action cannot be undone</p>
            </div>
            
            <form action="moderation.php" method="post">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                <input type="hidden" name="action" value="do_deletepoll" />
                <input type="hidden" name="tid" value="{$tid}" />
                <input type="hidden" name="delete" value="1" />
                
                <div class="card-body p-4">
                    <div class="alert alert-danger mb-4">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Warning</h5>
                        <p class="mb-0">{$lang->moderation['delete_poll']}. Once deleted, poll cannot be restored.</p>
                    </div>
                    
                    <div class="mb-4">
                        <h6><i class="fas fa-chart-pie me-2"></i>What will be deleted:</h6>
                        <ul class="mt-3 mb-0">
                            <li>Poll questions and options</li>
                            <li>All voting data</li>
                            <li>Poll statistics</li>
                        </ul>
                    </div>
                    
                    <div class="security-box">
                        <h6><i class="fas fa-shield-alt me-2"></i>Security Verification</h6>
                        <div class="mt-2">{$loginbox}</div>
                    </div>
                </div>
                
                <div class="card-footer bg-white py-3 text-end">
                    <a href="showthread.php?tid={$tid}" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-delete text-white" name="submit" value="{$lang->moderation['delete_poll']}">
                        <i class="fas fa-trash-alt me-1"></i> {$lang->moderation['delete_poll']}
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
HTML;
		
		
		
		
		
		
		
		stdhead('delete poll');
		
		echo $deletepoll;
		
		stdfoot();
		
		break;

	// Delete the actual poll here!
	case "do_deletepoll":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		if(!isset($mybb->input['delete']))
		{
			stderr('redirect_pollnotdeleted');
		}
		//if(!is_moderator($fid, "canmanagepolls"))
		//{
		//	if($permissions['candeletethreads'] != 1 || $mybb->user['uid'] != $thread['uid'])
		//	{
		//		error_no_permission();
		//	}
		//}
		$query = $db->simple_select("tsf_polls", "pid", "tid = $tid");
		$poll = $db->fetch_array($query);
		if(!$poll)
		{
			stderr('error_invalidpoll');
		}

		$plugins->run_hooks("moderation_do_deletepoll");

		$poll_deleted = sprintf($lang->moderation['poll_deleted'], $thread['subject']);
		
		log_moderator_action($modlogdata, $poll_deleted);

		$moderation->delete_poll($poll['pid']);

		redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_polldeleted']);
		break;

	// Approve a thread
	case "approvethread":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canapproveunapprovethreads"))
		//{
			//error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			error($lang->error_thread_deleted, $lang->error);
		}

		$thread = get_thread($tid);

		$plugins->run_hooks("moderation_approvethread");

		$thread_approved = sprintf($lang->moderation['thread_approved'], $thread['subject']);
		log_moderator_action($modlogdata, $thread_approved);

		$moderation->approve_threads($tid, $fid);

		moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadapproved']);
		break;

	// Unapprove a thread
	case "unapprovethread":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canapproveunapprovethreads"))
		//{
			//error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			error($lang->error_thread_deleted, $lang->error);
		}

		$thread = get_thread($tid);

		$plugins->run_hooks("moderation_unapprovethread");

		$thread_unapproved = sprintf($lang->moderation['thread_unapproved'], $thread['subject']);
		log_moderator_action($modlogdata, $thread_unapproved);

		$moderation->unapprove_threads($tid);

		moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadunapproved']);
		break;





	// Move a thread
	case "move":
		add_breadcrumb($lang->nav_move);
		//if(!is_moderator($fid, "canmanagethreads"))
		//{
			//error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$plugins->run_hooks("moderation_move");

		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");
		
		
		
 $movethread = <<<HTML
<html>
<head>
    <title>{$mybb->settings['bbname']} - {$lang->move_copy_thread}</title>
    
    <style>
        .method-option { cursor: pointer; border: 1px solid #dee2e6; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; transition: all .2s; }
        .method-option:hover { background-color: #f8f9fa; }
        .method-option.selected { border-color: #0d6efd; background-color: #e7f1ff; }
        .radio-circle { width: 20px; height: 20px; border: 2px solid #0d6efd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 1rem; }
        .radio-circle-inner { width: 10px; height: 10px; border-radius: 50%; background-color: #0d6efd; display: none; }
        .method-option.selected .radio-circle-inner { display: block; }
    </style>
</head>
<body>
<div class="container mt-3">
    <div class="card">
        <div class="card-header text-center bg-primary bg-opacity-10">
            <i class="fas fa-exchange-alt fa-2x text-primary"></i>
            <h2 class="h4 mt-2">Move / Copy Thread</h2>
            <p class="mb-0 text-muted">Transfer thread to another forum</p>
        </div>

        <form action="moderation.php" method="post" id="moveCopyForm">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			<input type="hidden" name="action" value="do_move" />
            <input type="hidden" name="tid" value="{$tid}" />
			
           

            <div class="card-body">
                <!-- Security Verification -->
                <div class="mb-4">
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                            {$loginbox}
                        </div>
                    </div>
                </div>

                <!-- Thread Info -->
                <div class="mb-4 row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-comment fa-lg mb-1"></i>
                                <div class="fw-bold">Title</div>
                                <div>{$thread_info['subject']}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-hashtag fa-lg mb-1"></i>
                                <div class="fw-bold">Thread ID</div>
                                <div>#{$tid}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-user fa-lg mb-1"></i>
                                <div class="fw-bold">Author</div>
                                <div>{$thread_info['username']}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Destination Forum -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-folder me-2 text-primary"></i>Destination Forum</label>
                    {$forumselect}
                    <div class="form-text">Select the forum where you want to move or copy this thread.</div>
                </div>

                <!-- Method Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-cogs me-2 text-primary"></i>Transfer Method</label>

                    <!-- Move Option -->
                    <div class="method-option" onclick="selectMethod('move')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-arrow-right fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Move Thread</div>
                                <small class="text-muted">Transfer thread to new forum (original will be removed)</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="move" class="d-none">
                    </div>

                    <!-- Move with Redirect Option -->
                    <div class="method-option selected" onclick="selectMethod('redirect')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-link fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Move with Redirect</div>
                                <small class="text-muted">Move thread and leave redirect link in original forum</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <div class="mt-2">
                            <input type="number" name="redirect_expire" class="form-control" placeholder="Redirect days (leave blank for infinite)" min="1" max="365">
                        </div>
                        <input type="radio" name="method" value="redirect" checked class="d-none">
                    </div>

                    <!-- Copy Option -->
                    <div class="method-option" onclick="selectMethod('copy')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-copy fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Copy Thread</div>
                                <small class="text-muted">Create a copy in new forum (original remains)</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="copy" class="d-none">
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer bg-light text-end">
                <a href="showthread.php?tid={$tid}" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Cancel
                </a>
                <button type="submit" name="submit" value="Move / Copy Thread" class="btn btn-primary">
                    <i class="fas fa-exchange-alt me-1"></i> Process Thread
					
                </button>
				
				
				
				
				
            </div>
        </form>
    </div>
</div>

<script>
function selectMethod(method) {
    document.querySelectorAll('.method-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelectorAll('.method-option input[type=radio]').forEach(r => r.checked = false);
    const selected = Array.from(document.querySelectorAll('.method-option')).find(o => o.querySelector('input').value === method);
    if(selected){
        selected.classList.add('selected');
        selected.querySelector('input').checked = true;
    }
}
</script>

</body>
</html>
HTML;
        

  
		





	
		

		
		
		
		

		
		
		
		
		stdhead();
		echo $movethread;
		
		stdfoot();
		
		break;

	// Let's get this thing moving!
	case "do_move":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
		$method = $mybb->get_input('method');

		//if(!is_moderator($fid, "canmanagethreads"))
		//{
		//	error_no_permission();
		//}

		// Check if user has moderator permission to move to destination
		//if(!is_moderator($moveto, "canmanagethreads") && !is_moderator($fid, "canmovetononmodforum"))
		//{
		//	error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}
		
		//$newperms = forum_permissions($moveto);
		//if($newperms['canview'] == 0 && !is_moderator($fid, "canmovetononmodforum"))
		//{
			//error($lang->error_movetononmodforum, $lang->error);
		//}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			stderr('error_invalidforum');
		}
		if($method != "copy" && $thread['fid'] == $moveto)
		{
			stderr($lang->moderation['error_movetosameforum']);
		}

		$plugins->run_hooks('moderation_do_move');

		$expire = 0;
		if($mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0)
		{
			$expire = TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400);
		}

		$the_thread = $tid;

		$newtid = $moderation->move_thread($tid, $moveto, $method, $expire);

		switch($method)
		{
			case "copy":
				log_moderator_action($modlogdata, $lang->moderation['thread_copied']);
				break;
			default:
			case "move":
			case "redirect":
				log_moderator_action($modlogdata, $lang->moderation['thread_moved']);
				break;
		}

		redirect(get_thread_link($newtid), $lang->moderation['redirect_threadmoved']);
		break;





	// Merge threads
	case "merge":
		add_breadcrumb($lang->nav_merge);
		//if(!is_moderator($fid, "canmanagethreads"))
		//{
			//error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$plugins->run_hooks("moderation_merge");

		
		
		
		
		

		
		
		
		
		
		
		$merge = '
		
		
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - {$lang->merge_threads}</title>
	
	
	
	
	
	
    
</head>
<body>

'.stdhead('Merge Threads').'

<div class="container mt-3">
    <div class="row justify-content-center">
        <div>
            <div class="card shadow-sm border-0">
                
				
				<div class="card-header bg-primary border-0 py-3">
    <div class="d-flex align-items-center">
        
            <i class="fas fa-code-branch text-white"></i>
        
        <div>
            <h4 class="text-white mb-0">Merge Threads</h4>
            <small class="text-white text-opacity-75">Combine multiple threads into one</small>
        </div>
    </div>
</div>
                
                <div class="card-body p-4">
                   
                    <div class="alert alert-info mb-4">
                        '.$loginbox.'
                    </div>
                   
                    
                    <div class="thread-preview">
                        <small class="text-muted d-block mb-1">Current Thread</small>
                        <strong>'.$thread['subject'].'</strong>
                        <small class="text-muted d-block mt-1">Thread ID: '.$tid.'</small>
                    </div>
                    
                    <form action="moderation.php" method="post">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'">
                        <input type="hidden" name="action" value="do_merge">
                        <input type="hidden" name="tid" value="'.$tid.'">
                        
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-edit me-1"></i>New Subject
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-heading"></i>
                                </span>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       name="subject" 
                                       value="'.$thread['subject'].'" 
                                       placeholder="Enter new thread subject"
                                       required>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                This will be the new subject for the merged thread
                            </small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-link me-1"></i>Thread URL to Merge
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-external-link-alt"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="threadurl" 
                                       placeholder="https://example.com/thread-123"
                                       required>
                            </div>
                            
                            <div class="merge-instructions mt-3 p-3 bg-light rounded">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-info-circle me-1"></i>Instructions:
                                </h6>
                                <ul class="mb-0 ps-3">
                                    <li>Copy the full URL of the thread you want to merge into this one</li>
                                    <li>The thread you specify will be <strong>deleted</strong></li>
                                    <li>All posts from that thread will be merged into this one</li>
                                    <li>This action cannot be undone</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning border-warning">
                            <div class="d-flex">
                                <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                                <div>
                                    <strong>Warning:</strong> This action is permanent. Once threads are merged, 
                                    they cannot be separated. Please double-check the thread URL before proceeding.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" name="submit">
                                <i class="fas fa-code-branch me-1"></i>Merge Threads
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-transparent border-top-0 pt-0 text-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Moderation Action • '.$SITENAME.'
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>






</body>
</html>
		
		
		
		
		
		';
		
		
		
		echo $merge;
		
		stdfoot();
		
		
		break;

	// Let's get those threads together baby! (Merge threads)
	case "do_merge":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canmanagethreads"))
		//{
		//	error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$plugins->run_hooks("moderation_do_merge");

		// explode at # sign in a url (indicates a name reference) and reassign to the url
		$realurl = explode("#", $mybb->get_input('threadurl'));
		$mybb->input['threadurl'] = $realurl[0];

		// Are we using an SEO URL?
		if(substr($mybb->input['threadurl'], -4) == "html")
		{
			// Get thread to merge's tid the SEO way
			preg_match("#thread-([0-9]+)?#i", $mybb->input['threadurl'], $threadmatch);
			preg_match("#post-([0-9]+)?#i", $mybb->input['threadurl'], $postmatch);

			if($threadmatch[1])
			{
				$parameters['tid'] = $threadmatch[1];
			}

			if($postmatch[1])
			{
				$parameters['pid'] = $postmatch[1];
			}
		}
		else
		{
			// Get thread to merge's tid the normal way
			$splitloc = explode(".php", $mybb->input['threadurl']);
			$temp = explode("&", my_substr($splitloc[1], 1));

			if(!empty($temp))
			{
				for($i = 0; $i < count($temp); $i++)
				{
					$temp2 = explode("=", $temp[$i], 2);
					$parameters[$temp2[0]] = $temp2[1];
				}
			}
			else
			{
				$temp2 = explode("=", $splitloc[1], 2);
				$parameters[$temp2[0]] = $temp2[1];
			}
		}

		if(!empty($parameters['pid']) && empty($parameters['tid']))
		{
			$post = get_post($parameters['pid']);
			$mergetid = (int)$post['tid'];
		}
		elseif(!empty($parameters['tid']))
		{
			$mergetid = (int)$parameters['tid'];
		}
		else
		{
			$mergetid = 0;
		}
		$mergethread = get_thread($mergetid);
		if(!$mergethread)
		{
			stderr('error_badmergeurl');
		}
		if($mergetid == $tid)
		{ // sanity check
			stderr('error_mergewithself');
		}
		//if(!is_moderator($mergethread['fid'], "canmanagethreads"))
		//{
		//	error_no_permission();
		//}
		if(isset($mybb->input['subject']))
		{
			$subject = $mybb->get_input('subject');
		}
		else
		{
			$subject = $thread['subject'];
		}

		$moderation->merge_threads($mergetid, $tid, $subject);

		log_moderator_action($modlogdata, $lang->moderation['thread_merged']);

		redirect(get_thread_link($tid), $lang->moderation['redirect_threadsmerged']);
		break;

	// Divorce the posts in this thread (Split!)
	case "split":
		add_breadcrumb('nav_split');
		//if(!is_moderator($fid, "canmanagethreads"))
		//{
			//error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$query = $db->sql_query("
			SELECT p.*, u.*
			FROM tsf_posts p
			LEFT JOIN users u ON (p.uid=u.id)
			WHERE tid='$tid'
			ORDER BY dateline ASC, pid ASC
		");

		$numposts = $db->num_rows($query);
		if($numposts <= 1)
		{
			stderr('error_cantsplitonepost');
		}

		$altbg = "trow1";
		$posts = '';
		while($post = $db->fetch_array($query))
		{
			$postdate = my_datee('relative', $post['dateline']);
			$post['username'] = htmlspecialchars_uni($post['username']);

			$parser_options = array(
				"allow_html" => 1,
				"allow_mycode" => 1,
				"allow_smilies" => 1,
				"allow_imgcode" => 1,
				"allow_videocode" => 1,
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
		    
			
			$posts .= '
			
			<div class="mt-4 mb-4 border border-5 p-3 rounded">
Posted by '.$post['username'].' <span class="text-muted">'.$postdate.'</span> <input type="checkbox" class="form-check-input" name="splitpost['.$post['pid'].']" value="1" />
	<br /><br />

'.$message.'
</div>';
			
			
			$altbg = alt_trow();
		}

		clearinline($tid, 'thread');
		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");

		$plugins->run_hooks("moderation_split");

		$split = '
		
		
		
		<html>
<head>
<title>{$mybb->settings[bbname]} - {$lang->split_thread}</title>

</head>
<body>


'.stdhead ('title').'

<form action="moderation.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md">
<div class="card">
<div class="card-body">
	<div class="legend mb-4">Split Thread</div>

<div class="ps-3 pe-3">'.$loginbox.'</div>

<div class="ps-3 pe-3 mt-3">New Subject:
<input type="text" class="form-control border form-control-sm mb-3" name="newsubject" value="[split] '.$thread['subject'].'" size="50" />

New Forum:
'.$forumselect.'
	</div>
	<div class="legend mt-4 mb-4">Posts to Split</div>
<div class="ps-3 pe-3">
'.$posts.'

<div class="mt-3 text-end"><input type="submit" class="btn btn-primary" name="submit" value="Split Thread" /></div>
<input type="hidden" name="action" value="do_split" />
<input type="hidden" name="tid" value="'.$tid.'" />
	</div></div>
	</div></div>
</form>
</body>
</html>
		
		
		
		
		
		
		';
		
		
		echo $split;
		
		stdfoot();
		
		break;

	// Let's break them up buddy! (Do the split)
	case "do_split":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		//if(!is_moderator($fid, "canmanagethreads"))
		//{
		//	error_no_permission();
		//}

		if($thread['visible'] == -1)
		{
			stderr('error_thread_deleted');
		}

		$plugins->run_hooks("moderation_do_split");

		$mybb->input['splitpost'] = $mybb->get_input('splitpost', MyBB::INPUT_ARRAY);
		if(empty($mybb->input['splitpost']))
		{
			stderr('error_nosplitposts');
		}
		$query = $db->simple_select("tsf_posts", "COUNT(*) AS totalposts", "tid='{$tid}'");
		$count = $db->fetch_array($query);

		if($count['totalposts'] == 1)
		{
			stderr('error_cantsplitonepost');
		}

		if($count['totalposts'] == count($mybb->input['splitpost']))
		{
			stderr('error_cantsplitall');
		}

		if(!empty($mybb->input['moveto']))
		{
			$moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
		}
		else
		{
			$moveto = $fid;
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			stderr('error_invalidforum');
		}

		$pids = array();

		// move the selected posts over
		$query = $db->simple_select("tsf_posts", "pid", "tid='$tid'");
		while($post = $db->fetch_array($query))
		{
			if(isset($mybb->input['splitpost'][$post['pid']]) && $mybb->input['splitpost'][$post['pid']] == 1)
			{
				$pids[] = $post['pid'];
			}
			//mark_reports($post['pid'], "post");
		}

		$newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->get_input('newsubject'));

		//log_moderator_action($modlogdata, $lang->thread_split);

		moderation_redirect(get_thread_link($newtid), 'The thread has been split successfully.<br />You will now be taken to the new thread');
		break;

	// Delete Thread Subscriptions
	case "removesubscriptions":

        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

		if(!is_moderator($fid, "canmanagethreads"))
		{
			error_no_permission();
		}

		if($thread['visible'] == -1)
		{
			error($lang->error_thread_deleted, $lang->error);
		}

		$plugins->run_hooks("moderation_removesubscriptions");

		$moderation->remove_thread_subscriptions($tid, true);

		log_moderator_action($modlogdata, $lang->removed_subscriptions);

		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_removed_subscriptions);
		break;

	// Delete Threads - Inline moderation
	case "multideletethreads":
		add_breadcrumb('Inline Thread Deletion');

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'candeletethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'candeletethreads'))
			//{
				//error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			stderr('error_inline_nothreadsselected2');
		}

		$inlineids = implode("|", $threads);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$return_url = htmlspecialchars_uni($mybb->get_input('url'));
		




$multidelete = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - Delete Threads Permanently</title>
    
    
 
  <style>
     
        
        .delete-card {
           
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .delete-card:hover {
            transform: translateY(-5px);
        }
        
        .delete-header {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .delete-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }
        
        .delete-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            position: relative;
            z-index: 1;
        }
        
        .warning-icon {
            color: #ff6b6b;
            font-size: 48px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);
            border-left: 4px solid #ff6b6b;
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .warning-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .warning-list li {
            padding: 8px 0;
            border-bottom: 1px dashed #ffcccc;
            display: flex;
            align-items: center;
        }
        
        .warning-list li:last-child {
            border-bottom: none;
        }
        
        .warning-list i {
            color: #ff6b6b;
            margin-right: 10px;
            min-width: 20px;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 75, 43, 0.4);
        }
        
        .btn-delete:active {
            transform: translateY(0);
        }
        
        .btn-delete::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn-delete:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        .btn-cancel {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        .security-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid #dee2e6;
        }
        
        .stats-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 5px;
        }
        
        .thread-count {
            font-size: 3rem;
            font-weight: 700;
            color: #ff416c;
            text-align: center;
            margin: 20px 0;
        }
        
        .form-check-input:checked {
            background-color: #ff416c;
            border-color: #ff416c;
        }
        
        .confirmation-check {
            margin: 20px 0;
        }
    </style>
	
	
</head>
<body>
    <div class="container mt-3">
        <div class="delete-card">
            <!-- Header -->
            <div class="delete-header">
                <div class="delete-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h1 class="h3 mb-2">Delete Threads Permanently</h1>
                <p class="mb-0 opacity-75">Irreversible Action - Proceed with Caution</p>
            </div>
            
            <!-- Form -->
            <form action="moderation.php" method="post" id="deleteForm">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                <input type="hidden" name="action" value="do_multideletethreads" />
                <input type="hidden" name="fid" value="{$fid}" />
                <input type="hidden" name="threads" value="{$inlineids}" />
                <input type="hidden" name="url" value="{$return_url}" />
                
                <div class="card-body p-4">
                    <!-- Warning Icon -->
                    <div class="text-center warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <!-- Thread Count -->
                    <div class="text-center">
                        <div class="thread-count">
                            <i class="fas fa-hashtag"></i> 
                            <span id="threadCount">{$thread_count}</span>
                        </div>
                        <p class="text-muted">Threads Selected for Deletion</p>
                    </div>
                    
                    <!-- Warning Box -->
                    <div class="warning-box">
                        <h5 class="fw-bold mb-3"><i class="fas fa-radiation me-2"></i>Critical Warning</h5>
                        <p class="mb-3">You are about to permanently delete selected threads. This action cannot be undone!</p>
                        
                        <ul class="warning-list">
                            <li><i class="fas fa-times-circle"></i> All posts within these threads will be permanently deleted</li>
                            <li><i class="fas fa-paperclip"></i> All attachments will be removed from the server</li>
                            <li><i class="fas fa-chart-bar"></i> Polls and voting data will be erased</li>
                            <li><i class="fas fa-history"></i> Thread history and statistics will be lost</li>
                            <li><i class="fas fa-undo"></i> No recovery or restore option is available</li>
                        </ul>
                        
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-skull-crossbones me-2"></i>
                            <strong>Data Loss Warning:</strong> This operation will permanently remove content from the database.
                        </div>
                    </div>
                    
                    <!-- Security Verification -->
                    <div class="security-box">
                        <h6 class="fw-bold mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h6>
                        <div class="mb-0">{$loginbox}</div>
                    </div>
                    
                    <!-- Confirmation Check -->
                    <div class="confirmation-check">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                            <label class="form-check-label" for="confirmDelete">
                                <strong>I understand that this action is permanent and cannot be undone.</strong> 
                                I have verified that I want to delete these threads.
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="card-footer bg-light py-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                        <div class="mb-3 mb-md-0">
                            <a href="{$return_url}" class="btn btn-cancel text-white">
                                <i class="fas fa-arrow-left me-2"></i>
                                Cancel & Return
                            </a>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-delete text-white" name="submit" value="Delete Threads Permanently">
                                <i class="fas fa-trash-alt me-2"></i>
                                Delete Threads Permanently
                            </button>
                        </div>
                    </div>
                    
                    <!-- Progress bar (hidden by default) -->
                    <div class="progress mt-4" style="height: 6px; display: none;" id="progressBar">
                        <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('deleteForm');
        const confirmCheck = document.getElementById('confirmDelete');
        const progressBar = document.getElementById('progressBar');
        const deleteBtn = form.querySelector('.btn-delete');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                // Check if confirmation checkbox is checked
                if (!confirmCheck.checked) {
                    e.preventDefault();
                    alert('Please confirm that you understand this action is permanent by checking the box.');
                    confirmCheck.focus();
                    return false;
                }
                
                // Final confirmation
                if (!confirm('⚠️ FINAL WARNING: Are you absolutely sure you want to PERMANENTLY DELETE these threads?\n\nThis action is IRREVERSIBLE!')) {
                    e.preventDefault();
                    return false;
                }
                
                // Show progress bar
                progressBar.style.display = 'block';
                const progressBarInner = progressBar.querySelector('.progress-bar');
                
                // Animate progress bar
                let width = 0;
                const interval = setInterval(() => {
                    if (width >= 100) {
                        clearInterval(interval);
                    } else {
                        width += 10;
                        progressBarInner.style.width = width + '%';
                    }
                }, 50);
                
                // Disable button and show loading
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Deleting...';
                
                // Allow form to submit
                return true;
            });
        }
        
        // Add danger styling when checkbox is checked
        confirmCheck.addEventListener('change', function() {
            if (this.checked) {
                deleteBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
            } else {
                deleteBtn.style.background = 'linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%)';
            }
        });
        
        // Count threads from inlineids
        const threadIds = '{$inlineids}';
        if (threadIds) {
            const count = threadIds.split(',').length;
            document.getElementById('threadCount').textContent = count;
        }
    });
    </script>
</body>
</html>
HTML;











		
		stdhead();
		echo $multidelete;
		
		stdfoot();
		
		break;

	// Actually delete the threads - Inline moderation
	case "do_multideletethreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$threadlist = explode("|", $mybb->get_input('threads'));
		//if(!is_moderator_by_tids($threadlist, "candeletethreads"))
		//{
		//	error_no_permission();
		//}
		foreach($threadlist as $tid)
		{
			$tid = (int)$tid;
			$moderation->delete_thread($tid);
			$tlist[] = $tid;
		}
		log_moderator_action($modlogdata, $lang->moderation['multi_deleted_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		//mark_reports($tlist, "threads");
		redirect(get_forum_link($fid), 'The selected threads have been deleted permanently.<br />You will now be returned to your previous location');
		break;

	// Open threads - Inline moderation
	case "multiopenthreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canopenclosethreads'))
			//{
				//error_no_permission();
			//}
		}

		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected, $lang->error);
		}

		$moderation->open_threads($threads);

		log_moderator_action($modlogdata, $lang->moderation['multi_opened_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsopened']);
		break;

	// Close threads - Inline moderation
	case "multiclosethreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canopenclosethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canopenclosethreads'))
			//{
				//error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected, $lang->error);
		}

		$moderation->close_threads($threads);

		log_moderator_action($modlogdata, $lang->moderation['multi_closed_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsclosed']);
		break;

	// Approve threads - Inline moderation
	case "multiapprovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canapproveunapprovethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canapproveunapprovethreads'))
			//{
				//error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected, $lang->error);
		}

		$moderation->approve_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->moderation['multi_approved_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$cache->update_stats();
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsapproved']);
		break;

	// Unapprove threads - Inline moderation
	case "multiunapprovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canapproveunapprovethreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canapproveunapprovethreads'))
			//{
			//	error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			error('error_inline_nothreadsselected', $lang->error);
		}

		$moderation->unapprove_threads($threads, $fid);

		log_moderator_action($modlogdata, $lang->moderation['multi_unapproved_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$cache->update_stats();
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunapproved']);
		break;

	



	// Stick threads - Inline moderation
	case "multistickthreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canstickunstickthreads'))
			{
				print_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canstickunstickthreads'))
			//{
				//error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			error('error_inline_nothreadsselected', $lang->error);
		}

		$moderation->stick_threads($threads);

		log_moderator_action($modlogdata, $lang->moderation['multi_stuck_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsstuck']);
		break;

	// Unstick threads - Inline moderaton
	case "multiunstickthreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			if(!is_moderator_by_tids($threads, 'canstickunstickthreads'))
			{
				error_no_permission();
			}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canstickunstickthreads'))
			//{
			//	error_no_permission();
			//}
		}
		if(count($threads) < 1)
		{
			error($lang->error_inline_nothreadsselected, $lang->error);
		}

		$moderation->unstick_threads($threads);

		log_moderator_action($modlogdata, $lang->moderation['multi_unstuck_threads']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunstuck']);
		break;

	// Move threads - Inline moderation
	case "multimovethreads":
		add_breadcrumb('nav_multi_movethreads');

		if(!empty($mybb->input['searchid']))
		{
			// From search page
			$threads = getids($mybb->get_input('searchid'), 'search');
			//if(!is_moderator_by_tids($threads, 'canmanagethreads'))
			//{
			//	print_no_permission();
			//}
		}
		else
		{
			$threads = getids($fid, 'forum');
			//if(!is_moderator($fid, 'canmanagethreads'))
			//{
			//	error_no_permission();
			//}
		}

		if(count($threads) < 1)
		{
			stderr('error_inline_nothreadsselected777');
		}
		$inlineids = implode("|", $threads);
		
		$thread_count = count($threads);
		
		
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($fid, 'forum');
		}
		$forumselect = build_forum_jump("", '', 1, '', 0, true, '', "moveto");
		$return_url = htmlspecialchars_uni($mybb->get_input('url'));
		
		
		
		
		$movethreads = 
		
		
		
		<<<HTML
<html>
<head>
    <title>{$mybb->settings['bbname']} - {$lang->moderation['move_threads']}</title>
    {$headerinclude}
    
    <style>
        .method-option { 
            cursor: pointer; 
            border: 1px solid #dee2e6; 
            border-radius: .5rem; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            transition: all .2s; 
        }
        .method-option:hover { 
            background-color: #f8f9fa; 
        }
        .method-option.selected { 
            border-color: #0d6efd; 
            background-color: #e7f1ff; 
        }
        .radio-circle { 
            width: 20px; 
            height: 20px; 
            border: 2px solid #0d6efd; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-left: 1rem; 
        }
        .radio-circle-inner { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            background-color: #0d6efd; 
            display: none; 
        }
        .method-option.selected .radio-circle-inner { 
            display: block; 
        }
        .threads-badge {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .redirect-input {
            max-width: 120px;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="container mt-3">
    <div class="card">
        <div class="card-header text-center bg-primary bg-opacity-10">
            <i class="fas fa-exchange-alt fa-2x text-primary"></i>
            <h2 class="h4 mt-2">{$lang->moderation['move_threads']}</h2>
            <p class="mb-0 text-muted">Transfer multiple threads to another forum</p>
            
            <!-- Threads Count -->
            <div class="mt-3">
                <div class="threads-badge">
                    <i class="fas fa-layer-group"></i>
                    <span id="threadsCount">{$thread_count}</span>
                </div>
                <p class="text-muted small mb-0">Threads Selected</p>
            </div>
        </div>

        <form action="moderation.php" method="post" id="multiMoveForm">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="action" value="do_multimovethreads" />
            <input type="hidden" name="fid" value="{$fid}" />
            <input type="hidden" name="threads" value="{$inlineids}" />
            <input type="hidden" name="url" value="{$return_url}" />

            <div class="card-body">
                <!-- Security Verification -->
                <div class="mb-4">
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                            {$loginbox}
                        </div>
                    </div>
                </div>
                
                <!-- Information Alert -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are performing a bulk operation on multiple threads. This action will affect <strong id="threadsCount2">{$thread_count}</strong> thread(s).
                </div>

                <!-- Destination Forum -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-folder me-2 text-primary"></i>{$lang->moderation['new_forum']}</label>
                    {$forumselect}
                    <div class="form-text">Select the forum where you want to move or copy the threads.</div>
                </div>

                <!-- Method Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-cogs me-2 text-primary"></i>{$lang->moderation['method']}</label>

                    <!-- Move Option -->
                    <div class="method-option" onclick="selectMethod('move')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-arrow-right fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{$lang->moderation['method_move']}</div>
                                <small class="text-muted">Move threads to new forum (originals will be removed)</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="move" class="d-none">
                    </div>

                    <!-- Move with Redirect Option -->
                    <div class="method-option selected" onclick="selectMethod('redirect')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-link fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{$lang->moderation['method_move_redirect']}</div>
                                <small class="text-muted">Move threads and leave redirect links in original forum</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <div class="mt-2">
                            <div class="d-flex align-items-center">
                                <input type="number" 
                                       name="redirect_expire" 
                                       class="form-control redirect-input" 
                                       placeholder="Days"
                                       min="1"
                                       max="365">
                                <small class="text-muted ms-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    {$lang->moderation['redirect_expire_note']}
                                </small>
                            </div>
                        </div>
                        <input type="radio" name="method" value="redirect" checked class="d-none">
                    </div>

                    <!-- Copy Option -->
                    <div class="method-option" onclick="selectMethod('copy')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-copy fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{$lang->moderation['method_copy']}</div>
                                <small class="text-muted">Create copies in new forum (originals remain)</small>
                            </div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="copy" class="d-none">
                    </div>
                </div>
                
                <!-- Important Notes -->
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> This bulk operation will affect multiple threads. Ensure you have proper permissions for the destination forum.
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer bg-light text-end">
                <a href="{$return_url}" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i> Cancel
                </a>
                <button type="submit" name="submit" value="{$lang->moderation['move_threads']}" class="btn btn-primary">
                    <i class="fas fa-exchange-alt me-1"></i> {$lang->moderation['move_threads']}
                </button>
            </div>
        </form>
    </div>
</div>









<script>
function selectMethod(method) {
    document.querySelectorAll('.method-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelectorAll('.method-option input[type=radio]').forEach(r => r.checked = false);
    const selected = Array.from(document.querySelectorAll('.method-option')).find(o => o.querySelector('input').value === method);
    if(selected){
        selected.classList.add('selected');
        selected.querySelector('input').checked = true;
    }
}

// Инициализация
selectMethod('redirect');

// Валидация формы с SweetAlert2
document.getElementById('multiMoveForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const select = form.querySelector('select');
    const count = document.getElementById('threadsCount')?.textContent || '';
    const methodInput = form.querySelector('input[name="method"]:checked');
    const methodValue = methodInput ? methodInput.value : '';
    
    // Проверка выбора форума
    if (!select?.value) {
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'error',
                title: 'Forum Required',
                text: 'Please select a destination forum.',
                confirmButtonColor: '#0d6efd'
            });
        } else {
            alert('Please select a destination forum.');
        }
        select?.focus();
        return false;
    }
    
    // Проверка дней редиректа
    if (methodValue === 'redirect') {
        const redirectInput = form.querySelector('input[name="redirect_expire"]');
        if (redirectInput?.value.trim()) {
            const days = parseInt(redirectInput.value);
            if (days < 1 || days > 365) {
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Invalid Redirect Days',
                        text: 'Redirect days must be between 1 and 365, or leave blank for infinite.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert('Redirect days must be between 1 and 365, or leave blank for infinite.');
                }
                redirectInput.focus();
                return false;
            }
        }
    }
    
    const forumName = select.options[select.selectedIndex].text;
    const methodNames = {
        'move': 'move',
        'redirect': 'move with redirect',
        'copy': 'copy'
    };
    const methodText = methodNames[methodValue] || 'transfer';
    
    // Проверяем доступность SweetAlert
    if (typeof Swal !== 'undefined') {
        // Красивое подтверждение с SweetAlert2
        const result = await Swal.fire({
            title: '<strong>Confirm ' + (methodValue === 'copy' ? 'Copy' : 'Move') + ' Operation</strong>',
            html: `
                <div class="text-start">
                    <div class="alert alert-info border-0 mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        You are about to <strong>` + methodText + `</strong> <strong class="text-primary">` + count + `</strong> thread(s)
                    </div>
                    
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary bg-opacity-10 text-primary py-2">
                            <i class="fas fa-folder-open me-2"></i>
                            <strong>Destination Forum:</strong>
                        </div>
                        <div class="card-body py-3">
                            <h6 class="mb-1">` + forumName + `</h6>
                            <small class="text-muted">
                                <i class="fas fa-hashtag me-1"></i>Selected threads will be transferred here
                            </small>
                        </div>
                    </div>
                    
                    ` + (methodValue === 'redirect' ? `
                    <div class="alert alert-warning">
                        <i class="fas fa-link me-2"></i>
                        <strong>Redirect links</strong> will be created in the original forum.
                    </div>
                    ` : '') + `
                    
                    ` + (methodValue === 'copy' ? `
                    <div class="alert alert-info">
                        <i class="fas fa-copy me-2"></i>
                        <strong>Copies</strong> will be created - original threads will remain.
                    </div>
                    ` : '') + `
                    
                    ` + (methodValue === 'move' ? `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Threads will be <strong>permanently moved</strong> from their current location.
                    </div>
                    ` : '') + `
                    
                    <p class="text-muted small mt-3">
                        <i class="fas fa-clock me-1"></i>
                        This operation may take a few moments depending on the number of threads.
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `
                <i class="fas fa-exchange-alt me-2"></i>
                ` + (methodValue === 'copy' ? 'Copy Threads' : 'Move Threads') + `
            `,
            cancelButtonText: `
                <i class="fas fa-times me-2"></i>
                Cancel
            `,
            reverseButtons: true,
            width: 600,
            customClass: {
                popup: 'border-radius-15',
                confirmButton: 'shadow-sm',
                cancelButton: 'shadow-sm'
            }
        });
        
        if (!result.isConfirmed) {
            return false;
        }
    } else {
        // Fallback на стандартный confirm
        const confirmMessage = 'Are you sure you want to ' + methodText + ' ' + count + ' thread(s) to "' + forumName + '"?';
        if (!confirm(confirmMessage)) {
            return false;
        }
    }
    
    // Показываем индикатор загрузки
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
    }
    
    // Отправляем форму с помощью HTMLFormElement.prototype.submit
    setTimeout(() => {
        HTMLFormElement.prototype.submit.call(form);
    }, 100);
    
    return false;
});

// Опционально: добавить стили для SweetAlert
if (typeof Swal !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup.border-radius-15 {
            border-radius: 15px !important;
        }
        .swal2-confirm {
            padding: 12px 24px !important;
            font-weight: 600 !important;
        }
        .swal2-cancel {
            padding: 12px 24px !important;
            font-weight: 600 !important;
        }
    `;
    document.head.appendChild(style);
}
</script>



</body>
</html>
HTML;








		
		
		
		
		
		
		
		
		
		
		
		stdhead();
		
		echo $movethreads;
		
		stdfoot();
		
		break;

	// Actually move the threads in Inline moderation
	case "do_multimovethreads":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
		$method = $mybb->get_input('method');

		$threadlist = explode("|", $mybb->get_input('threads'));
		if(!is_moderator_by_tids($threadlist, 'canmanagethreads'))
		{
			error_no_permission();
		}
		foreach($threadlist as $tid)
		{
			$tids[] = (int)$tid;
		}
		// Make sure moderator has permission to move to the new forum
		$newperms = forum_permissions($moveto);
		//if(($newperms['canview'] == 0 || !is_moderator($moveto, 'canmanagethreads')) && !is_moderator_by_tids($tids, 'canmovetononmodforum'))
		//{
			//error($lang->error_movetononmodforum, $lang->error);
		//}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			stderr('error_invalidforum', 'error');
		}

		$plugins->run_hooks('moderation_do_multimovethreads');

		log_moderator_action($modlogdata, 'Threads Moved / Copied');
		$expire = 0;
		if($mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0)
		{
			$expire = TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400);
		}

		foreach($tids as $tid) {
			$moderation->move_thread($tid, $moveto, $method, $expire);
		}

		moderation_redirect(get_forum_link($moveto), 'The selected threads have been moved or copied.<br />You will now be taken to the new forum the threads are in.');
		break;

	// Delete posts - Inline moderation
	case "multideleteposts":
		add_breadcrumb($lang->nav_multi_deleteposts);

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			stderr('error_inline_nopostsselected', $lang->error);
		}
		if(!is_moderator_by_pids($posts, "candeleteposts"))
		{
			error_no_permission();
		}
		$inlineids = implode("|", $posts);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}

		$return_url = htmlspecialchars_uni($mybb->get_input('url'));

		$multidelete = '
		
		
		
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - Delete Posts Permanently</title>
    <style>
        .confirmation-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .warning-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 1.5rem;
        }
        .danger-zone {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }
        .btn-danger {
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-danger:hover {
            transform: translateY(-1px);
          
        }
        .btn-outline-secondary {
            margin-right: 1rem;
        }
        .post-count {
            font-size: 0.9rem;
            background-color: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            display: inline-block;
        }
        .thread-id-display {
            background: linear-gradient(135deg, #6c757d20, #6c757d10);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="confirmation-container py-5">
        <div class="card">
            <div class="card-header bg-danger text-white py-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h1 class="h4 mb-1">Delete Posts Permanently</h1>
                        <p class="mb-0 opacity-75">Irreversible Action Required</p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-5">
                <div class="text-center mb-5">
                    <div class="warning-icon text-danger mx-auto">
                        <i class="fas fa-trash-alt fa-4x"></i>
                    </div>
                    <h2 class="h5 text-muted mb-3">Final Confirmation Required</h2>
                </div>

                <form action="moderation.php" method="post" class="needs-validation" novalidate id="deleteForm">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                    <input type="hidden" name="action" value="do_multideleteposts" />
                    <input type="hidden" name="tid" value="'.$tid.'" id="threadId" />
                    <input type="hidden" name="posts" value="'.$inlineids.'" id="postIds" />
                    <input type="hidden" name="url" value="'.$return_url.'" />

                    <!-- Отображение Thread ID для пользователя -->
                    <div class="thread-id-display mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small">Thread ID:</span>
                                <strong class="h5 mb-0 ms-2">'.$tid.'</strong>
                            </div>
                            <span class="badge bg-secondary">
                                <i class="fas fa-link me-1"></i>
                                '.$tid.'
                            </span>
                        </div>
                    </div>

                    <div class="alert alert-danger danger-zone mb-4 p-4" role="alert">
                        <div class="d-flex">
                            <i class="fas fa-radiation-alt fa-lg me-3 mt-1"></i>
                            <div>
                                <h4 class="alert-heading h6 mb-2">⚠️ Permanent Deletion Warning</h4>
                                <p class="mb-3">You are about to <strong>permanently delete</strong> selected posts from this thread. This action <strong>cannot be undone</strong>.</p>
                                
                                <div class="mb-3">
                                    <span class="post-count">
                                        <i class="fas fa-hashtag me-1"></i>
                                        Thread ID: <strong>'.$tid.'</strong>
                                    </span>
                                    <span class="post-count ms-2">
                                        <i class="fas fa-comments me-1"></i>
                                        Posts to delete: <strong id="postCount">0</strong>
                                    </span>
                                </div>
                                
                                <hr class="my-3">
                                
                                <div class="text-danger mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Important:</strong> If all posts are removed from this thread, the entire thread will also be permanently deleted.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title h6 mb-3">
                                <i class="fas fa-shield-alt me-2"></i>
                                Security Verification
                            </h5>
                            <div class="mb-0">'.$loginbox.'</div>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-top">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                            <div class="mb-3 mb-md-0">
                                <a href="'.$return_url.'" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Cancel & Return
                                </a>
                            </div>
                            
                            <div class="d-flex flex-column flex-sm-row gap-3">
                                <button type="button" 
                                        class="btn btn-outline-danger" 
                                        onclick="showFinalWarning()">
                                    <i class="fas fa-eye me-2"></i>
                                    Review Selection
                                </button>
                                
                                <button type="submit" 
                                        name="submit" 
                                        value="Delete Posts Permanently"
                                        class="btn btn-danger"
                                        id="deleteButton">
                                    <i class="fas fa-trash-alt me-2"></i>
                                    Permanently Delete Posts
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="card-footer bg-light py-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Action logged for administrative purposes
                </small>
            </div>
        </div>
    </div>


</body>
</html>


';
		
		stdhead('fdfds');
		
		echo $multidelete;
		
		break;

	// Actually delete the posts in inline moderation
	case "do_multideleteposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$postlist = explode("|", $mybb->get_input('posts'));
		if(!is_moderator_by_pids($postlist, "candeleteposts"))
		{
			error_no_permission();
		}
		$postlist = array_map('intval', $postlist);
		$pids = implode(',', $postlist);

		$tids = array();
		if($pids)
		{
			$query = $db->simple_select("tsf_threads", "tid", "firstpost IN({$pids})");
			while($threadid = $db->fetch_field($query, "tid"))
			{
				$tids[] = $threadid;
			}
		}

		$deletecount = 0;
		foreach($postlist as $pid)
		{
			$pid = (int)$pid;
			$moderation->delete_post($pid);
			$plist[] = $pid;
			$deletecount++;
		}

		// If we have multiple threads, we must be coming from the search
		if(!empty($tids))
		{
			foreach($tids as $tid)
			{
				$moderation->delete_thread($tid);
				mark_reports($tid, "thread");
				$url = get_forum_link($fid);
			}
		}
		// Otherwise we're just deleting from showthread.php
		else
		{
			$query = $db->simple_select("tsf_posts", "pid", "tid = $tid");
			$numposts = $db->num_rows($query);
			if(!$numposts)
			{
				$moderation->delete_thread($tid);
				mark_reports($tid, "thread");
				$url = get_forum_link($fid);
			}
			else
			{
				mark_reports($plist, "posts");
				$url = get_thread_link($thread['tid']);
			}
		}

		$deleted_selective_posts = sprintf($lang->moderation['deleted_selective_posts'], $deletecount);
		log_moderator_action($modlogdata, $deleted_selective_posts);
		redirect($url, 'redirect_postsdeleted');
		break;

	// Merge posts - Inline moderation
	case "multimergeposts":
		add_breadcrumb($lang->nav_multi_mergeposts);

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		// Add the selected posts from other threads
		foreach($mybb->cookies as $key => $value)
		{
			if(strpos($key, "inlinemod_thread") !== false && $key != "inlinemod_thread$tid")
			{
				$inlinepostlist = explode("|", $mybb->cookies[$key]);
				foreach($inlinepostlist as $p)
				{
					$p = (int)$p;

					if(!empty($p))
					{
						$posts[] = (int)$p;
					}
				}
				// Remove the cookie once its data is retrieved
				my_unsetcookie($key);
			}
		}

		if(empty($posts))
		{
			stderr('Sorry, but you did not select any posts to perform inline moderation on, or your previous moderation session has expired (Automatically after 1 hour of inactivity). Please select some posts and try again');
		}

		//if(!is_moderator_by_pids($posts, "canmanagethreads"))
		//{
		//	error_no_permission();
		//}

		$postlist = "";
		$query = $db->sql_query("
			SELECT p.*, u.*
			FROM tsf_posts p
			LEFT JOIN users u ON (p.uid=u.id)
			WHERE pid IN (".implode(",", $posts).")
			ORDER BY dateline ASC, pid ASC
		");
		$altbg = "trow1";
		while($post = $db->fetch_array($query))
		{
			$postdate = my_datee('relative', $post['dateline']);

			$parser_options = array(
				"allow_html" => 1,
				"allow_mycode" => 1,
				"allow_smilies" => 1,
				"allow_imgcode" => 1,
				"allow_videocode" => 1,
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$message = $parser->parse_message($post['message'], $parser_options);
			
			$postlist .= '
			
			
			
			<div class="mt-4 mb-4 border border-5 p-3 rounded">
Posted by '.$post['username'].' <span class="text-muted">'.$postdate.'</span> <input type="checkbox" class="form-check-input" checked="checked" name="mergepost['.$post['pid'].']" value="1" />
	<br /><br />

'.$message.'
</div>
			
			
			';
			
			$altbg = alt_trow();
		}

		$inlineids = implode("|", $posts);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}

		$return_url = htmlspecialchars_uni($mybb->get_input('url'));

		$multimerge = '
		
		
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$mybb->settings[bbname]} - {$lang->merge_posts}</title>
     <style>
        .merge-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .merge-header {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #0d6efd 100%) !important;
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .merge-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .option-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .option-card:hover {
            border-color: var(--bs-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.1);
        }
        .option-card.selected {
            border-color: var(--bs-primary);
            background-color: rgba(13, 110, 253, 0.05);
        }
        .option-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .hr-option .option-icon {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #0d6efd 100%);
            color: white;
        }
        .newline-option .option-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .post-item {
            background: #f8f9fa;
            border-left: 4px solid var(--bs-primary);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }
        .post-item:hover {
            background: #f1f3ff;
            transform: translateX(5px);
        }
        .post-author {
            font-weight: 600;
            color: var(--bs-primary);
        }
        .post-date {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .post-content {
            margin-top: 8px;
            color: #495057;
            line-height: 1.5;
        }
        .btn-merge {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #0d6efd 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-merge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(13, 110, 253, 0.3);
        }
        .preview-area {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            min-height: 100px;
        }
        .preview-title {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .separator-preview {
            border-top: 2px solid var(--bs-primary);
            margin: 20px 0;
            opacity: 0.7;
        }
        .newline-preview {
            background: var(--bs-primary);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            display: inline-block;
            font-size: 0.8rem;
            margin: 10px 0;
        }
        /* Primary color overrides */
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
        }
        .text-primary {
            color: var(--bs-primary) !important;
        }
        .bg-primary {
            background-color: var(--bs-primary) !important;
        }
        .border-primary {
            border-color: var(--bs-primary) !important;
        }
        .btn-primary {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
        }
        .btn-primary:hover {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
        }
    </style>
</head>
<body>
  
    
    <div class="container mt-3">
        <div class="card">
            <!-- Header -->
            <div class="merge-header p-5">
                <div class="text-center">
                    <div class="merge-icon">
                        <i class="fas fa-shuffle fa-2x"></i>
                    </div>
                    <h1 class="h3 mb-2">Merge Posts</h1>
                    <p class="mb-0 opacity-75">Combine selected posts into a single message</p>
                </div>
            </div>
            
            <!-- Form -->
            <form action="moderation.php" method="post">
                <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                <input type="hidden" name="action" value="do_multimergeposts" />
                <input type="hidden" name="tid" value="'.$tid.'" />
                <input type="hidden" name="url" value="'.$return_url.'" />
                
                <div class="card-body p-5">
                    <!-- Security Verification -->
                    <div class="mb-5">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h5 class="card-title h6 mb-3">
                                    <i class="fas fa-shield-alt me-2 text-primary"></i>
                                    Security Verification
                                </h5>
                                <div class="mb-0">'.$loginbox.'</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Separator Selection -->
                    <div class="mb-5">
                        <h5 class="h6 mb-4">
                            <i class="fas fa-grip-lines me-2 text-primary"></i>
                            Select Post Separator
                        </h5>
                        
                        <div class="row g-3">
                            <!-- Horizontal Rule Option -->
                            <div class="col-md-6">
                              <div class="option-card hr-option p-4" onclick="selectOption(\'hr\')">
                                    <div class="d-flex align-items-center">
                                        <div class="option-icon">
                                            <i class="fas fa-minus fa-lg"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold">Horizontal Rule</h6>
                                            <p class="mb-0 text-muted small">Posts separated by a visible line</p>
                                        </div>
                                    </div>
                                    <input type="radio" name="sep" id="sep" value="hr" checked="checked" style="display: none;" />
                                </div>
                            </div>
                            
                            <!-- New Line Option -->
                            <div class="col-md-6">
                                <div class="option-card newline-option p-4" onclick="selectOption(\'new_line\')">

                                    <div class="d-flex align-items-center">
                                        <div class="option-icon">
                                            <i class="fas fa-arrow-down fa-lg"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold">New Line</h6>
                                            <p class="mb-0 text-muted small">Posts separated by line breaks</p>
                                        </div>
                                    </div>
                                    <input type="radio" name="sep" id="sep2" value="new_line" style="display: none;" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="preview-area mt-4">
                            <div class="preview-title">Preview</div>
                            <div id="previewContent">
                                <div class="post-preview">
                                    <div class="post-content">First post content will appear here...</div>
                                    <div class="separator-preview" id="hrPreview"></div>
                                    <div class="newline-preview d-none" id="newlinePreview">[New Post]</div>
                                    <div class="post-content">Second post content will appear here...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Posts List -->
                    <div class="mb-5">
                        <h5 class="h6 mb-4">
                            <i class="fas fa-list-check me-2 text-primary"></i>
                            Posts to Merge
                            <span class="badge bg-primary ms-2">'.$post_count.' posts selected</span>
                        </h5>
                        
                        <div class="posts-list">
                            '.$postlist.'
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Posts will be merged in chronological order. The original posts will be deleted.
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="card-footer bg-light py-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                        <div class="mb-3 mb-md-0">
                            <a href="'.$return_url.'" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Cancel & Return
                            </a>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-merge text-white" name="submit" value="Merge Posts">
                                <i class="fas fa-shuffle me-2"></i>
                                Merge Posts
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
 
</body>
</html>
		
		
		
		
		
		
		';
		
		
		stdhead('45353354334534');
		echo $multimerge;
		
		break;

	// Actually merge the posts - Inline moderation
	case "do_multimergeposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$mergepost = $mybb->get_input('mergepost', MyBB::INPUT_ARRAY);
		if(count($mergepost) <= 1)
		{
			stderr('error_nomergeposts');
		}

		foreach($mergepost as $pid => $yes)
		{
			$postlist[] = (int)$pid;
		}

		//if(!is_moderator_by_pids($postlist, "canmanagethreads"))
		//{
			//error_no_permission();
		//}

		$masterpid = $moderation->merge_posts($postlist, $tid, $mybb->input['sep']);

		//mark_reports($postlist, "posts");
		log_moderator_action($modlogdata, $lang->moderation['merged_selective_posts']);
		redirect(get_post_link($masterpid)."#pid$masterpid", 'redirect_inline_postsmerged');
		break;

	// Split posts - Inline moderation
	case "multisplitposts":
		add_breadcrumb($lang->nav_multi_splitposts);

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			stderr($lang->moderation['error_inline_nopostsselected']);
		}

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}
		$posts = array_map('intval', $posts);
		$pidin = implode(',', $posts);

		// Make sure that we are not splitting a thread with one post
		// Select number of posts in each thread that the splitted post is in
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$threads = $pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if((int)$tcheck['count'] <= 1)
			{
				stderr($lang->moderation['error_cantsplitonepost']);
			}
			$threads[] = $pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not splitting all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			stderr($lang->moderation['error_cantsplitall']);
		}

		$inlineids = implode("|", $posts);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");

		$return_url = htmlspecialchars_uni($mybb->get_input('url'));

		
		eval("\$splitposts = \"".$templates->get("moderation_inline_splitposts")."\";");
		
		
		stdhead('ddddd');
		
		echo $splitposts;
		
		stdfoot();
		
		
		break;

	// Actually split the posts - Inline moderation
	case "do_multisplitposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$plist = array();
		$postlist = explode("|", $mybb->get_input('posts'));
		foreach($postlist as $pid)
		{
			$pid = (int)$pid;
			$plist[] = $pid;
		}

		if(!is_moderator_by_pids($plist, "canmanagethreads"))
		{
			error_no_permission();
		}

		// Ensure all posts exist
		$posts = array();
		if(!empty($plist))
		{
			$query = $db->simple_select('tsf_posts', 'pid', 'pid IN ('.implode(',', $plist).')');
			while($pid = $db->fetch_field($query, 'pid'))
			{
				$posts[] = $pid;
			}
		}

		if(empty($posts))
		{
			error($lang->error_inline_nopostsselected, $lang->error);
		}

		$pidin = implode(',', $posts);

		// Make sure that we are not splitting a thread with one post
		// Select number of posts in each thread that the splitted post is in
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if((int)$tcheck['count'] <= 1)
			{
				error($lang->error_cantsplitonepost, $lang->error);
			}
			$pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not splitting all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			error($lang->error_cantsplitall, $lang->error);
		}

		if(isset($mybb->input['moveto']))
		{
			$moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
		}
		else
		{
			$moveto = $fid;
		}

		$newforum = get_forum($moveto);
		if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
		{
			error($lang->error_invalidforum, $lang->error);
		}

		$newsubject = $mybb->get_input('newsubject');
		$newtid = $moderation->split_posts($posts, $tid, $moveto, $newsubject);

		$pid_list = implode(', ', $posts);
		$lang->split_selective_posts = sprintf($lang->moderation['split_selective_posts'], $pid_list, $newtid);
		log_moderator_action($modlogdata, $lang->moderation['split_selective_posts']);

		moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_threadsplit']);
		break;

	// Move posts - Inline moderation
	case "multimoveposts":
		add_breadcrumb($lang->moderation['nav_multi_moveposts']);

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			stderr($lang->moderation['error_inline_nopostsselected']);
		}

		if(!is_moderator_by_pids($posts, "canmanagethreads"))
		{
			error_no_permission();
		}
		$posts = array_map('intval', $posts);
		$pidin = implode(',', $posts);

		// Make sure that we are not moving posts in a thread with one post
		// Select number of posts in each thread that the moved post is in
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$threads = $pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if((int)$tcheck['count'] <= 1)
			{
				error($lang->moderation['error_cantsplitonepost'], $lang->error);
			}
			$threads[] = $pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not moving all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			error($lang->moderation['error_cantmoveall'], $lang->error);
		}

		$inlineids = implode("|", $posts);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");

		$return_url = htmlspecialchars_uni($mybb->get_input('url'));

		$post_count = !empty($inlineids) ? count(explode('|', $inlineids)) : 0;
		
		$moveposts = '
		
		
		
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$SITENAME.' - <?php echo $lang->move_posts; ?></title>
    
	
	
	<style>
    .move-container {
        max-width: 700px;
        margin: 0 auto;
    }
    .move-header {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #0b5ed7 100%) !important;
        color: white;
        border-radius: 10px 10px 0 0;
    }
    .move-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .url-input-container {
        position: relative;
        margin-bottom: 25px;
    }
    .url-input {
        padding-left: 45px;
        padding-right: 45px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    .url-input:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    .url-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--bs-primary);
        z-index: 4;
    }
    .url-clear {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        cursor: pointer;
        opacity: 0.5;
        transition: opacity 0.2s;
        z-index: 4;
    }
    .url-clear:hover {
        opacity: 1;
        color: #dc3545;
    }
    .info-box {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid var(--bs-primary);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
    }
    .info-icon {
        width: 48px;
        height: 48px;
        background: rgba(13, 110, 253, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: var(--bs-primary);
    }
    .stats-box {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .stat-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .stat-item:last-child {
        margin-bottom: 0;
    }
    .stat-icon {
        width: 36px;
        height: 36px;
        background: #f8f9fa;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        color: var(--bs-primary);
    }
    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .stat-value {
        font-weight: 600;
        color: #495057;
    }
    .btn-move {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #0b5ed7 100%);
        border: none;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s ease;
    }
    .btn-move:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(13, 110, 253, 0.3);
    }
    .post-count-badge {
        background: linear-gradient(135deg, var(--bs-primary) 0%, #0b5ed7 100%);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
        margin-left: 10px;
    }
    .thread-preview {
        background: white;
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        display: none;
    }
    .thread-preview.show {
        display: block;
    }
    .preview-title {
        color: #495057;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    .preview-title i {
        margin-right: 10px;
        color: var(--bs-primary);
    }
    .preview-content {
        color: #6c757d;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .thread-example {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 5px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 3px solid var(--bs-primary);
    }
    .alert-warning {
        border-left: 4px solid #ffc107;
    }
    
    /* Primary color variables */
    :root {
        --bs-primary: #0d6efd;
        --bs-primary-rgb: 13, 110, 253;
    }
    
    /* Bootstrap primary color overrides */
    .bg-primary {
        background-color: var(--bs-primary) !important;
    }
    .text-primary {
        color: var(--bs-primary) !important;
    }
    .border-primary {
        border-color: var(--bs-primary) !important;
    }
    .btn-primary {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
    .btn-outline-primary {
        color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--bs-primary) !important;
        color: white !important;
    }
</style>
	
	
  
</head>
<body>
   
    
    <div class="container mt-3">
        <div class="card">
            <!-- Header -->
            <div class="move-header p-5">
                <div class="text-center">
                    <div class="move-icon">
                        <i class="fas fa-arrow-right fa-2x"></i>
                    </div>
                    <h1 class="h3 mb-2">Move Posts</h1>
                    <p class="mb-0 opacity-75">Transfer selected posts to another thread</p>
                </div>
            </div>
            
            <!-- Form -->
            <form action="moderation.php" method="post" id="moveForm">
                <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                <input type="hidden" name="action" value="do_multimoveposts" />
                <input type="hidden" name="tid" value="'.$tid.'" />
                <input type="hidden" name="posts" value="'.$inlineids.'" />
                <input type="hidden" name="url" value="'.$return_url.'" />
                
                <div class="card-body p-5">
                    <!-- Security Verification -->
                    <div class="mb-5">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h5 class="card-title h6 mb-3">
                                    <i class="fas fa-shield-alt me-2 text-primary"></i>
                                    Security Verification
                                </h5>
                                <div class="mb-0"><?php echo $loginbox; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Information Box -->
                    <div class="info-box mb-4">
                        <div class="d-flex align-items-start">
                            <div class="info-icon">
                                <i class="fas fa-info-circle fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="h6 mb-2">How to move posts</h5>
                                <p class="mb-0 text-muted small">
                                    Copy the full URL of the destination thread and paste it in the field below.
                                    The posts will be moved while preserving their content, authors, and timestamps.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                   
                    <div class="stats-box mb-4">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div>
                                <div class="stat-label">Posts to move</div>
                                <div class="stat-value">
                                    '.$post_count.' posts
                                </div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div>
                                <div class="stat-label">Current thread ID</div>
                                <div class="stat-value">#'.$tid.'</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- URL Input -->
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-3">
                            <i class="fas fa-link me-2 text-primary"></i>
                            Destination Thread URL
                            <span class="post-count-badge">'.$post_count.' posts selected</span>
                        </label>
                        
                        <div class="url-input-container">
                            <i class="fas fa-link url-icon"></i>
                            <input type="text" 
                                   class="form-control url-input" 
                                   name="threadurl" 
                                   id="threadUrl" 
                                   placeholder="https://yourforum.com/showthread.php?tid=123"
                                   autocomplete="off"
                                   required>
                            <i class="fas fa-times url-clear" id="clearUrl"></i>
                        </div>
                        
                        <div class="thread-example">
                            <i class="fas fa-lightbulb me-2 text-warning"></i>
                            <strong>Example:</strong> https://example.com/forum/showthread.php?tid=456
                        </div>
                        
                        <!-- Thread Preview -->
                        <div class="thread-preview" id="threadPreview">
                            <div class="preview-title">
                                <i class="fas fa-eye"></i>
                                Thread Preview
                            </div>
                            <div class="preview-content" id="previewContent">
                                Enter a valid thread URL to see preview...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Warning -->
                    <div class="alert alert-warning">
                        <div class="d-flex">
                            <i class="fas fa-exclamation-triangle fa-lg me-3 mt-1 text-warning"></i>
                            <div>
                                <h6 class="alert-heading mb-2">Important Notes</h6>
                                <ul class="mb-0 small">
                                    <li>Posts will be removed from the current thread and added to the destination thread</li>
                                    <li>The operation cannot be undone automatically</li>
                                    <li>Make sure you have permission to move posts to the destination thread</li>
                                    <li>All post metadata will be preserved</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="card-footer bg-light py-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                        <div class="mb-3 mb-md-0">
                            <a href="'.$return_url.'" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Cancel & Return
                            </a>
                        </div>
                        
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <button type="button" class="btn btn-outline-primary" id="validateBtn">
                                <i class="fas fa-check-circle me-2"></i>
                                Validate URL
                            </button>
                            <button type="submit" class="btn btn-move text-white" name="submit" value="Move Posts">
                                <i class="fas fa-arrow-right me-2"></i>
                                Move Posts
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


</body>
</html>
		
		
		
		
		
		';
		
		
		stdhead();
		
		build_breadcrumb();
		
		echo $moveposts;
		
		stdfoot();
		
		
		break;

	// Actually split the posts - Inline moderation
	case "do_multimoveposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$plugins->run_hooks("moderation_do_multimoveposts");

		// explode at # sign in a url (indicates a name reference) and reassign to the url
		$realurl = explode("#", $mybb->get_input('threadurl'));
		$mybb->input['threadurl'] = $realurl[0];

		// Are we using an SEO URL?
		if(substr($mybb->input['threadurl'], -4) == "html")
		{
			// Get thread to merge's tid the SEO way
			preg_match("#thread-([0-9]+)?#i", $mybb->input['threadurl'], $threadmatch);
			preg_match("#post-([0-9]+)?#i", $mybb->input['threadurl'], $postmatch);

			if(!empty($threadmatch[1]))
			{
				$parameters['tid'] = $threadmatch[1];
			}

			if(!empty($postmatch[1]))
			{
				$parameters['pid'] = $postmatch[1];
			}
		}
		else
		{
			// Get thread to merge's tid the normal way
			$splitloc = explode(".php", $mybb->input['threadurl']);
			$temp = explode("&", my_substr($splitloc[1], 1));

			if(!empty($temp))
			{
				for($i = 0; $i < count($temp); $i++)
				{
					$temp2 = explode("=", $temp[$i], 2);
					$parameters[$temp2[0]] = $temp2[1];
				}
			}
			else
			{
				$temp2 = explode("=", $splitloc[1], 2);
				$parameters[$temp2[0]] = $temp2[1];
			}
		}

		if(!empty($parameters['pid']) && empty($parameters['tid']))
		{
			$query = $db->simple_select("posts", "tid", "pid='".(int)$parameters['pid']."'");
			$post = $db->fetch_array($query);
			$newtid = $post['tid'];
		}
		elseif(!empty($parameters['tid']))
		{
			$newtid = $parameters['tid'];
		}
		else
		{
			$newtid = 0;
		}
		$newtid = (int)$newtid;
		$newthread = get_thread($newtid);
		if(!$newthread)
		{
			
			stderr($lang->moderation['error_badmovepostsurl']);
		}
		if($newtid == $tid)
		{ // sanity check
			stderr($lang->moderation['error_movetoself']);
		}

		$postlist = explode("|", $mybb->get_input('posts'));
		$plist = array();
		foreach($postlist as $pid)
		{
			$pid = (int)$pid;
			$plist[] = $pid;
		}

		if(!is_moderator_by_pids($plist, "canmanagethreads"))
		{
			error_no_permission();
		}

		// Ensure all posts exist
		$posts = array();
		if(!empty($plist))
		{
			$query = $db->simple_select('tsf_posts', 'pid', 'pid IN ('.implode(',', $plist).')');
			while($pid = $db->fetch_field($query, 'pid'))
			{
				$posts[] = $pid;
			}
		}

		if(empty($posts))
		{
			stderr($lang->moderation['error_inline_nopostsselected']);
		}

		$pidin = implode(',', $posts);

		// Make sure that we are not moving posts in a thread with one post
		// Select number of posts in each thread that the moved post is in
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$threads = $pcheck = array();
		while($tcheck = $db->fetch_array($query))
		{
			if((int)$tcheck['count'] <= 1)
			{
				stderr($lang->moderation['error_cantsplitonepost']);
			}
			$threads[] = $pcheck[] = $tcheck['tid']; // Save tids for below
		}

		// Make sure that we are not moving all posts in the thread
		// The query does not return a row when the count is 0, so find if some threads are missing (i.e. 0 posts after removal)
		$query = $db->sql_query("
			SELECT DISTINCT p.tid, COUNT(q.pid) as count
			FROM tsf_posts p
			LEFT JOIN tsf_posts q ON (p.tid=q.tid)
			WHERE p.pid IN ($pidin) AND q.pid NOT IN ($pidin)
			GROUP BY p.tid, p.pid
		");
		$pcheck2 = array();
		while($tcheck = $db->fetch_array($query))
		{
			if($tcheck['count'] > 0)
			{
				$pcheck2[] = $tcheck['tid'];
			}
		}
		if(count($pcheck2) != count($pcheck))
		{
			// One or more threads do not have posts after splitting
			stderr($lang->moderation['error_cantmoveall']);
		}

		$newtid = $moderation->split_posts($posts, $tid, $newthread['fid'], $db->escape_string($newthread['subject']), $newtid);

		$pid_list = implode(', ', $posts);
		$move_selective_posts = sprintf($lang->moderation['move_selective_posts'], $pid_list, $newtid);
		log_moderator_action($modlogdata, $move_selective_posts);

		moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_moveposts']);
		break;

	// Approve posts - Inline moderation
	case "multiapproveposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}
		if(count($posts) < 1)
		{
			stderr($lang->moderation['error_inline_nopostsselected'], $lang->error);
		}

		if(!is_moderator_by_pids($posts, "canapproveunapproveposts"))
		{
			error_no_permission();
		}

		$pids = array();
		foreach($posts as $pid)
		{
			$pids[] = (int)$pid;
		}

		$moderation->approve_posts($pids);

		log_moderator_action($modlogdata, $lang->moderation['multi_approve_posts']);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_inline_postsapproved']);
		break;

	// Unapprove posts - Inline moderation
	case "multiunapproveposts":

		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($mybb->get_input('inlinetype') == 'search')
		{
			$posts = getids($mybb->get_input('searchid'), 'search');
		}
		else
		{
			$posts = getids($tid, 'thread');
		}

		if(count($posts) < 1)
		{
			error($lang->error_inline_nopostsselected, $lang->error);
		}
		$pids = array();

		if(!is_moderator_by_pids($posts, "canapproveunapproveposts"))
		{
			error_no_permission();
		}
		foreach($posts as $pid)
		{
			$pids[] = (int)$pid;
		}

		$moderation->unapprove_posts($pids);

		log_moderator_action($modlogdata, $lang->multi_unapprove_posts);
		if($mybb->get_input('inlinetype') == 'search')
		{
			clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
		}
		else
		{
			clearinline($tid, 'thread');
		}
		moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_inline_postsunapproved);
		break;




		
	case "do_purgespammer":
	case "purgespammer":
		require_once MYBB_ROOT."inc/functions_user.php";

		$groups = explode(",", $mybb->settings['purgespammergroups']);
		if(!is_member($groups))
		{
			error_no_permission();
		}

		$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
		$user = get_user($uid);
		if(!$user || !purgespammer_show($user['postnum'], $user['usergroup'], $user['uid']))
		{
			error($lang->purgespammer_invalid_user);
		}

		if($mybb->input['action'] == "do_purgespammer")
		{
			verify_post_check($mybb->get_input('my_post_key'));

			$user_deleted = false;

			// Run the hooks first to avoid any issues when we delete the user
			$plugins->run_hooks("moderation_purgespammer_purge");

			require_once MYBB_ROOT.'inc/datahandlers/user.php';
			$userhandler = new UserDataHandler('delete');

			if($mybb->settings['purgespammerbandelete'] == "ban")
			{
				// First delete everything
				$userhandler->delete_content($uid);
				$userhandler->delete_posts($uid);
				
				// Next ban him (or update the banned reason, shouldn't happen)
				$query = $db->simple_select("banned", "uid", "uid = '{$uid}'");
				if($db->num_rows($query) > 0)
				{
					$banupdate = array(
						"reason" => $db->escape_string($mybb->settings['purgespammerbanreason'])
					);
					$db->update_query('banned', $banupdate, "uid = '{$uid}'");
				}
				else
				{
					$insert = array(
						"uid" => $uid,
						"gid" => (int)$mybb->settings['purgespammerbangroup'],
						"oldgroup" => 2,
						"oldadditionalgroups" => "",
						"olddisplaygroup" => 0,
						"admin" => (int)$mybb->user['uid'],
						"dateline" => TIMENOW,
						"bantime" => "---",
						"lifted" => 0,
						"reason" => $db->escape_string($mybb->settings['purgespammerbanreason'])
					);
					$db->insert_query('banned', $insert);
				}

				// Add the IP's to the banfilters
				if($mybb->settings['purgespammerbanip'] == 1)
				{
					foreach(array($user['regip'], $user['lastip']) as $ip)
					{
						$ip = my_inet_ntop($db->unescape_binary($ip));
						$query = $db->simple_select("banfilters", "type", "type = 1 AND filter = '".$db->escape_string($ip)."'");
						if($db->num_rows($query) == 0)
						{
							$insert = array(
								"filter" => $db->escape_string($ip),
								"type" => 1,
								"dateline" => TIMENOW
							);
							$db->insert_query("banfilters", $insert);
						}
					}
				}

				// Clear the profile
				$userhandler->clear_profile($uid, $mybb->settings['purgespammerbangroup']);

				$cache->update_bannedips();
				$cache->update_awaitingactivation();

				// Update reports cache
				$cache->update_reportedcontent();
			}
			elseif($mybb->settings['purgespammerbandelete'] == "delete")
			{
				$user_deleted = $userhandler->delete_user($uid, 1);
			}

			// Submit the user to stop forum spam
			if(!empty($mybb->settings['purgespammerapikey']))
			{
				$sfs = @fetch_remote_file("http://stopforumspam.com/add.php?username=" . urlencode($user['username']) . "&ip_addr=" . urlencode(my_inet_ntop($db->unescape_binary($user['lastip']))) . "&email=" . urlencode($user['email']) . "&api_key=" . urlencode($mybb->settings['purgespammerapikey']));
			}

			log_moderator_action(array('uid' => $uid, 'username' => $user['username']), $lang->purgespammer_modlog);

			if($user_deleted)
			{
				redirect($mybb->settings['bburl'], $lang->purgespammer_success);
			}
			else
			{
				redirect(get_profile_link($uid), $lang->purgespammer_success);
			}
		}
		elseif($mybb->input['action'] == "purgespammer")
		{
			$plugins->run_hooks("moderation_purgespammer_show");

			add_breadcrumb($lang->purgespammer);
			$lang->purgespammer_purge = $lang->sprintf($lang->purgespammer_purge, htmlspecialchars_uni($user['username']));
			if($mybb->settings['purgespammerbandelete'] == "ban")
			{
				$lang->purgespammer_purge_desc = $lang->sprintf($lang->purgespammer_purge_desc, $lang->purgespammer_ban);
			}
			else
			{
				$lang->purgespammer_purge_desc = $lang->sprintf($lang->purgespammer_purge_desc, $lang->purgespammer_delete);				
			}
			eval("\$purgespammer = \"".$templates->get('moderation_purgespammer')."\";");
			output_page($purgespammer);
		}
		break;
	default:
		require_once MYBB_ROOT."inc/class_custommoderation.php";
		$custommod = new CustomModeration;
		$tool = $custommod->tool_info($mybb->get_input('action', MyBB::INPUT_INT));
		if($tool !== false)
		{
			// Verify incoming POST request
			verify_post_check($mybb->get_input('my_post_key'));

			$options = my_unserialize($tool['threadoptions']);

			if(!is_member($tool['groups']))
			{
				error_no_permission();
			}
			
			if($thread['visible'] == -1)
			{
				error($lang->error_thread_deleted, $lang->error);
			}

			if(!empty($options['confirmation']) && empty($mybb->input['confirm']))
			{
				add_breadcrumb($lang->confirm_execute_tool);

				$lang->confirm_execute_tool_desc = $lang->sprintf($lang->confirm_execute_tool_desc, htmlspecialchars_uni($tool['name']));

				$action = $mybb->get_input('action', MyBB::INPUT_INT);
				$modtype = htmlspecialchars_uni($mybb->get_input('modtype'));
				$inlinetype = htmlspecialchars_uni($mybb->get_input('inlinetype'));
				$searchid = htmlspecialchars_uni($mybb->get_input('searchid'));
				$url = htmlspecialchars_uni($mybb->get_input('url'));
				$plugins->run_hooks('moderation_confirmation');

				eval('$page = "'.$templates->get('moderation_confirmation').'";');

				output_page($page);
				exit;
			}

			$tool['name'] = htmlspecialchars_uni($tool['name']);

			if($tool['type'] == 't' && $mybb->get_input('modtype') == 'inlinethread')
			{
				if($mybb->get_input('inlinetype') == 'search')
				{
					$tids = getids($mybb->get_input('searchid'), 'search');
				}
				else
				{
					$tids = getids($fid, "forum");
				}
				if(count($tids) < 1)
				{
					error($lang->error_inline_nopostsselected, $lang->error);
				}
				if(!is_moderator_by_tids($tids, "canusecustomtools"))
				{
					error_no_permission();
				}

				$thread_options = my_unserialize($tool['threadoptions']);
				if($thread_options['movethread'] && $forum_cache[$thread_options['movethread']]['type'] != "f")
				{
					error($lang->error_movetocategory, $lang->error);
				}

				$custommod->execute($mybb->get_input('action', MyBB::INPUT_INT), $tids);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($mybb->get_input('inlinetype') == 'search')
				{
					clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
					$lang->redirect_customtool_search = $lang->sprintf($lang->redirect_customtool_search, $tool['name']);
					$return_url = htmlspecialchars_uni($mybb->get_input('url'));
					moderation_redirect($return_url, $lang->redirect_customtool_search);
				}
				else
				{
					clearinline($fid, "forum");
					$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
					redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
				}
				break;
			}
			elseif($tool['type'] == 't' && $mybb->get_input('modtype') == 'thread')
			{
				if(!is_moderator_by_tids($tid, "canusecustomtools"))
				{
					error_no_permission();
				}

				$thread_options = my_unserialize($tool['threadoptions']);
				if($thread_options['movethread'] && $forum_cache[$thread_options['movethread']]['type'] != "f")
				{
					error($lang->error_movetocategory, $lang->error);
				}

				$ret = $custommod->execute($mybb->get_input('action', MyBB::INPUT_INT), $tid);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($ret == 'forum')
				{
					$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
					moderation_redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
				}
				else
				{
					$lang->redirect_customtool_thread = $lang->sprintf($lang->redirect_customtool_thread, $tool['name']);
					moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_customtool_thread);
				}
				break;
			}
			elseif($tool['type'] == 'p' && $mybb->get_input('modtype') == 'inlinepost')
			{
				if($mybb->get_input('inlinetype') == 'search')
				{
					$pids = getids($mybb->get_input('searchid'), 'search');
				}
				else
				{
					$pids = getids($tid, 'thread');
				}

				if(count($pids) < 1)
				{
					error($lang->error_inline_nopostsselected, $lang->error);
				}
				if(!is_moderator_by_pids($pids, "canusecustomtools"))
				{
					error_no_permission();
				}

				// Get threads which are associated with the posts
				$tids = array();
				$options = array(
					'order_by' => 'dateline, pid',
				);
				$query = $db->simple_select("posts", "DISTINCT tid, dateline", "pid IN (".implode(',',$pids).")", $options);
				while($row = $db->fetch_array($query))
				{
					$tids[] = $row['tid'];
				}

				$ret = $custommod->execute($mybb->get_input('action', MyBB::INPUT_INT), $tids, $pids);
 				$lang->custom_tool = $lang->sprintf($lang->custom_tool, $tool['name']);
				log_moderator_action($modlogdata, $lang->custom_tool);
				if($mybb->get_input('inlinetype') == 'search')
				{
					clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search');
					$lang->redirect_customtool_search = $lang->sprintf($lang->redirect_customtool_search, $tool['name']);
					$return_url = htmlspecialchars_uni($mybb->get_input('url'));
					moderation_redirect($return_url, $lang->redirect_customtool_search);
				}
				else
				{
					clearinline($tid, 'thread');
					if($ret == 'forum')
					{
						$lang->redirect_customtool_forum = $lang->sprintf($lang->redirect_customtool_forum, $tool['name']);
						moderation_redirect(get_forum_link($fid), $lang->redirect_customtool_forum);
					}
					else
					{
						$lang->redirect_customtool_thread = $lang->sprintf($lang->redirect_customtool_thread, $tool['name']);
						moderation_redirect(get_thread_link($tid), $lang->redirect_customtool_thread);
					}
				}

				break;
			}
		}
		error_no_permission();
		break;
}

/**
 * Some little handy functions for our inline moderation
 *
 * @param int $id
 * @param string $type
 *
 * @return array
 */
function getids($id, $type)
{
	global $mybb;

	$newids = array();
	$cookie = "inlinemod_".$type.$id;
	if(isset($mybb->cookies[$cookie]))
	{
		$cookie_ids = explode("|", $mybb->cookies[$cookie]);

		foreach($cookie_ids as $cookie_id)
		{
			if(empty($cookie_id))
			{
				continue;
			}

			if($cookie_id == 'ALL')
			{
				$newids += getallids($id, $type);
			}
			else
			{
				$newids[] = (int)$cookie_id;
			}
		}
	}

	return $newids;
}





/**
 * @param int $id
 * @param string $type
 *
 * @return array
 */
function getallids($id, $type)
{
	global $db, $mybb, $CURUSER;

	$ids = array();

	// Get any removed threads (after our user hit 'all')
	$removed_ids = array();
	$cookie = "inlinemod_".$type.$id."_removed";
	if(isset($mybb->cookies[$cookie]))
	{
		$removed_ids = explode("|", $mybb->cookies[$cookie]);

		if(!is_array($removed_ids))
		{
			$removed_ids = array();
		}
	}

	// "Select all Threads in this forum" only supported by forumdisplay and search
	if($type == 'forum')
	{
		$query = $db->simple_select("tsf_threads", "tid", "fid='".(int)$id."'");
		while($tid = $db->fetch_field($query, "tid"))
		{
			if(in_array($tid, $removed_ids))
			{
				continue;
			}

			$ids[] = $tid;
		}
	}
	elseif($type == 'search')
	{
		$query = $db->simple_select("searchlog", "resulttype, posts, threads", "sid='".$db->escape_string($id)."' AND uid='{$CURUSER['id']}'", 1);
		$searchlog = $db->fetch_array($query);
		if($searchlog['resulttype'] == 'posts')
		{
			$ids = explode(',', $searchlog['posts']);
		}
		else
		{
			$ids = explode(',', $searchlog['threads']);
		}

		if(is_array($ids))
		{
			foreach($ids as $key => $tid)
			{
				if(in_array($tid, $removed_ids))
				{
					unset($ids[$key]);
				}
			}
		}
	}

	return $ids;
}

/**
 * @param int $id
 * @param string $type
 */
function clearinline($id, $type)
{
	my_unsetcookie("inlinemod_".$type.$id);
	my_unsetcookie("inlinemod_{$type}{$id}_removed");
}

/**
 * @param int $id
 * @param string $type
 */
function extendinline($id, $type)
{
	my_setcookie("inlinemod_{$type}{$id}", '', TIMENOW+3600);
	my_setcookie("inlinemod_{$type}{$id}_removed", '', TIMENOW+3600);
}

/**
 * Checks if the current user is a moderator of all the posts specified
 *
 * Note: If no posts are specified, this function will return true.  It is the
 * responsibility of the calling script to error-check this case if necessary.
 *
 * @param array $posts Array of post IDs
 * @param string $permission Permission to check
 * @return bool True if moderator of all; false otherwise
 */
function is_moderator_by_pids($posts, $permission='')
{
	global $db, $mybb;

	// Speedy determination for supermods/admins and guests
	if($mybb->usergroup['issupermod'])
	{
		return true;
	}
	elseif(!$mybb->user['uid'])
	{
		return false;
	}
	// Make an array of threads if not an array
	if(!is_array($posts))
	{
		$posts = array($posts);
	}
	// Validate input
	$posts = array_map('intval', $posts);
	$posts[] = 0;
	// Get forums
	$posts_string = implode(',', $posts);
	$query = $db->simple_select("tsf_posts", "DISTINCT fid", "pid IN ($posts_string)");
	while($forum = $db->fetch_array($query))
	{
		if(!is_moderator($forum['fid'], $permission))
		{
			return false;
		}
	}
	return true;
}

/**
 * Checks if the current user is a moderator of all the threads specified
 *
 * Note: If no threads are specified, this function will return true.  It is the
 * responsibility of the calling script to error-check this case if necessary.
 *
 * @param array $threads Array of thread IDs
 * @param string $permission Permission to check
 * @return bool True if moderator of all; false otherwise
 */
function is_moderator_by_tids($threads, $permission='')
{
	global $db, $mybb;

	// Speedy determination for supermods/admins and guests
	if($mybb->usergroup['issupermod'])
	{
		return true;
	}
	elseif(!$mybb->user['uid'])
	{
		return false;
	}
	// Make an array of threads if not an array
	if(!is_array($threads))
	{
		$threads = array($threads);
	}
	// Validate input
	$threads = array_map('intval', $threads);
	$threads[] = 0;
	// Get forums
	$threads_string = implode(',', $threads);
	$query = $db->simple_select("threads", "DISTINCT fid", "tid IN ($threads_string)");
	while($forum = $db->fetch_array($query))
	{
		if(!is_moderator($forum['fid'], $permission))
		{
			return false;
		}
	}
	return true;
}

/**
 * Special redirect that takes a return URL into account
 * @param string $url URL
 * @param string $message Message
 * @param string $title Title
 */
 
function moderation_redirect($url, $message = "", $title = "")
{
    global $mybb, $BASEURL;
    
    if(!empty($mybb->input['url']))
    {
        $url = htmlentities($mybb->input['url']);
    }

    if(my_strpos($url, $BASEURL.'/') !== 0)
    {
        if(my_strpos($url, '/') === 0)
        {
            $url = my_substr($url, 1);
        }
        $url_segments = explode('/', $url);
        $url = $BASEURL.'/'.end($url_segments);
    }

    // Преобразуем NULL в пустую строку
    if ($message === null) {
        $message = "";
    }
    if ($title === null) {
        $title = "";
    }

    redirect($url, $message, $title);
}

?>













<!-- SweetAlert2 for beautiful dialogs (optional) -->
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/sweetalert2.min.css">
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/sweetalert2.min.js"></script>
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/toast.js"></script>
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/moderation.js"></script>






<?
