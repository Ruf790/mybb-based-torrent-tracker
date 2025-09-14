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
define('THIS_SCRIPT', 'attachment.php');
define("SCRIPTNAME", "attachment.php");

define ('TSF_FORUMS_TSSEv56', true);

require_once 'global2.php';

  
if ((!defined ('IN_SCRIPT_TSSEv56') OR !defined ('TSF_FORUMS_GLOBAL_TSSEv56')))
{
   exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}


function mk_path_abs222222222($path, $base = TSDIR)
{
	$iswin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$char1 = my_substr($path, 0, 1);
	if($char1 != '/' && !($iswin && ($char1 == '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path))))
	{
		$path = $base.$path;
	}

	return $path;
}



function mk_path_abs($path, $base = TSDIR)
{
	$iswin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$char1 = my_substr($path, 0, 1);

	if($char1 != '/' && !($iswin && ($char1 == '\\' || preg_match('(^[a-zA-Z]:\\\\)', $path))))
	{
		// Убираем точку и лишние слэши
		$base = rtrim($base, ".\\/").DIRECTORY_SEPARATOR;
		$path = $base . ltrim($path, "/\\");
	}

	return $path;
}


//if($mybb->settings['enableattachments'] != 1)
//{
	//error($lang->attachments_disabled);
//}

// Find the AID we're looking for
if(isset($mybb->input['thumbnail']))
{
	$aid = $mybb->get_input('thumbnail', MyBB::INPUT_INT);
}
else
{
	$aid = $mybb->get_input('aid', MyBB::INPUT_INT);
}

$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

// Select attachment data from database
if($aid)
{
	$query = $db->simple_select("attachments", "*", "aid='{$aid}'");
}
else
{
	$query = $db->simple_select("attachments", "*", "pid='{$pid}'");
}
$attachment = $db->fetch_array($query);

$plugins->run_hooks("attachment_start");

if(!$attachment)
{
	error($lang->error_invalidattachment);
}

if($attachment['thumbnail'] == '' && isset($mybb->input['thumbnail']))
{
	error($lang->error_invalidattachment);
}

$attachtypes = (array)$cache->read('attachtypes');
$ext = get_extension($attachment['filename']);

if(empty($attachtypes[$ext]))
{
	stderr('error_invalidattachment4');
}

$attachtype = $attachtypes[$ext];

$pid = $attachment['pid'];

// Don't check the permissions on preview
if($pid || $attachment['uid'] != $CURUSER['id'])
{
	$post = get_post($pid);

	if(!$post)
	{
		stderr('error_invalidthread');
	}

	// Check permissions if the post is not a draft
	if($post['visible'] != -2)
	{
		$thread = get_thread($post['tid']);

		if(!$thread && !isset($mybb->input['thumbnail']))
		{
			error($lang->error_invalidthread);
		}
		$fid = $thread['fid'];

		// Get forum info
		$forum = get_forum($fid);

		// Permissions
		//$forumpermissions = forum_permissions($fid);

		//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']) || ($forumpermissions['candlattachments'] == 0 && empty($mybb->input['thumbnail'])))
		//{
		//	error_no_permission();
		//}

		//// Error if attachment is invalid or not visible
		//if(!$attachment['attachname'] || (!is_moderator($fid, "canviewunapprove") && ($attachment['visible'] != 1 || $thread['visible'] != 1 || $post['visible'] != 1)))
		//{
		//	error($lang->error_invalidattachment);
		//}

		//if($attachtype['forums'] != -1 && strpos(','.$attachtype['forums'].',', ','.$fid.',') === false)
		//{
		//	error_no_permission();
		//}
	}
}

if(!isset($mybb->input['thumbnail'])) // Only increment the download count if this is not a thumbnail
{
	//if(!is_member($attachtype['groups']))
	//{
	//	error_no_permission();
	//}

	$attachupdate = array(
		"downloads" => $attachment['downloads']+1,
	);
	$db->update_query("attachments", $attachupdate, "aid='{$attachment['aid']}'");
}

// basename isn't UTF-8 safe. This is a workaround.
$attachment['filename'] = ltrim(basename(' '.$attachment['filename']));

$uploadspath = "./uploads";



$uploadspath_abs = mk_path_abs($uploadspath);





$plugins->run_hooks("attachment_end");

if(isset($mybb->input['thumbnail']))
{
	if(!file_exists($uploadspath_abs."/".$attachment['thumbnail']))
	{
		stderr('error_invalidattachment55');
	}

	$ext = get_extension($attachment['thumbnail']);
	switch($ext)
	{
		case "gif":
			$type = "image/gif";
			break;
		case "bmp":
			$type = "image/bmp";
			break;
		case "png":
			$type = "image/png";
			break;
		case "jpg":
		case "jpeg":
		case "jpe":
			$type = "image/jpeg";
			break;
			
			case "webp":
			$type = "image/webp";
			break;
			
		default:
			$type = "image/unknown";
			break;
	}

	//header("Content-disposition: filename=\"{$attachment['filename']}\"");
	header("Content-disposition: filename=\"{$attachment['filename']}\"");
	
	header("Content-type: ".$type);
	$thumb = $uploadspath_abs."/".$attachment['thumbnail'];
	header("Content-length: ".@filesize($thumb));
	$handle = fopen($thumb, 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
else
{
	if(!file_exists($uploadspath_abs."/".$attachment['attachname']))
	{
		stderr('error_invalidattachment777');
	}

	$ext = get_extension($attachment['filename']);

	switch($attachment['filetype'])
	{
		case "application/pdf":
		case "image/bmp":
		case "image/gif":
		case "image/jpeg":
		case "image/pjpeg":
		case "image/png":
		case "image/webp":
		case "text/plain":
			header("Content-type: {$attachment['filetype']}");
			if(!empty($attachtypes[$ext]['forcedownload']))
			{
				$disposition = "attachment";
			}
			else
			{
				$disposition = "inline";
			}
			break;

		default:
			$filetype = $attachment['filetype'];

			if(!$filetype)
			{
				$filetype = 'application/force-download';
			}

			header("Content-type: {$filetype}");
			$disposition = "attachment";
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie") !== false)
	{
		header("Content-disposition: attachment; filename=\"{$attachment['filename']}\"");
	}
	else
	{
		header("Content-disposition: {$disposition}; filename=\"{$attachment['filename']}\"");
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie 6.0") !== false)
	{
		header("Expires: -1");
	}

	header("Content-length: {$attachment['filesize']}");
	header("Content-range: bytes=0-".($attachment['filesize']-1)."/".$attachment['filesize']);
	$handle = fopen($uploadspath_abs."/".$attachment['attachname'], 'rb');
	while(!feof($handle))
	{
		echo fread($handle, 8192);
	}
	fclose($handle);
}
