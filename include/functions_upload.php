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
 * Get maximum upload filesize limit set in PHP
 * @since MyBB 1.8.27
 * @return int maximum allowed filesize
 */
 


function get_php_upload_limit()
{	
	$maxsize = array(return_bytes(ini_get('upload_max_filesize')), return_bytes(ini_get('post_max_size')));
	$maxsize = array_filter($maxsize); // Remove empty values

	if(empty($maxsize))
	{
		return 0;
	}
	else
	{
		return (int)min($maxsize);
	}
}
 
 
 
 
 $cdnpath = "";
 $usecdn = "0";
 
function copy_file_to_cdn($file_path = '', &$uploaded_path = null)
{
	global $mybb, $cdnpath, $usecdn, $plugins;

	$success = false;

	$file_path = (string)$file_path;

	$real_file_path = realpath($file_path);

	$file_dir_path = dirname($real_file_path);
	$file_dir_path = str_replace(TSDIR, '', $file_dir_path);
	$file_dir_path = ltrim($file_dir_path, './\\');

	$file_name = basename($real_file_path);

	if(file_exists($file_path))
	{

		if(is_object($plugins))
		{
			$hook_args = array(
				'file_path' => &$file_path,
				'real_file_path' => &$real_file_path,
				'file_name' => &$file_name,
				'file_dir_path'	=> &$file_dir_path
			);
			$plugins->run_hooks('copy_file_to_cdn_start', $hook_args);
		}

		if(!empty($usecdn) && !empty($cdnpath))
		{
			$cdn_path = rtrim($cdnpath, '/\\');

			if(substr($file_dir_path, 0, my_strlen(TSDIR)) == TSDIR)
			{
				$file_dir_path = str_replace(TSDIR, '', $file_dir_path);
			}

			$cdn_upload_path = $cdn_path . DIRECTORY_SEPARATOR . $file_dir_path;

			if(!($dir_exists = is_dir($cdn_upload_path)))
			{
				$dir_exists = @mkdir($cdn_upload_path, 0777, true);
			}

			if($dir_exists)
			{
				if(($cdn_upload_path = realpath($cdn_upload_path)) !== false)
				{
					$success = @copy($file_path, $cdn_upload_path.DIRECTORY_SEPARATOR.$file_name);

					if($success)
					{
						$uploaded_path = $cdn_upload_path;
					}
				}
			}
		}

		if(is_object($plugins))
		{
			$hook_args = array(
				'file_path' => &$file_path,
				'real_file_path' => &$real_file_path,
				'file_name' => &$file_name,
				'uploaded_path' => &$uploaded_path,
				'success' => &$success,
			);

			$plugins->run_hooks('copy_file_to_cdn_end', $hook_args);
		}
	}

	return $success;
} 
 


/**
 * Remove any matching avatars for a specific user ID
 *
 * @param int $uid The user ID
 * @param string $exclude A file name to be excluded from the removal
 */
function remove_avatars($uid, $exclude="")
{
	global $mybb, $avataruploadpath, $plugins;

	
	
	if(defined('IN_ADMINCP'))
	{
		$avatarpath = '../'.$avataruploadpath;
	}
	else
	{
		$avatarpath = $avataruploadpath;
	}

	$dir = opendir($avatarpath);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			$plugins->run_hooks("remove_avatars_do_delete", $file);

			if(preg_match("#avatar_".$uid."\.#", $file) && is_file($avatarpath."/".$file) && $file != $exclude)
			{
				delete_uploaded_file($avatarpath."/".$file);
			}
		}

		@closedir($dir);
	}
}


/**
 * Upload a new avatar in to the file system
 *
 * @param array $avatar Incoming FILE array, if we have one - otherwise takes $_FILES['avatarupload']
 * @param int $uid User ID this avatar is being uploaded for, if not the current user
 * @return array Array of errors if any, otherwise filename of successful.
 */
function upload_avatar($avatar=array(), $uid=0)
{
	global $db, $mybb, $CURUSER, $lang, $plugins, $cache, $avataruploadpath, $avatarsize;


	

	
	$ret = array();

	if(!$uid)
	{
		$uid = $CURUSER['id'];
	}

	if(empty($avatar['name']) || empty($avatar['tmp_name']))
	{
		$avatar = $_FILES['avatarupload'];
	}

	if(!is_uploaded_file($avatar['tmp_name']))
	{
		$ret['error'] = 'error_uploadfailedZZ';
		return $ret;
	}

	// Check we have a valid extension
    	$ext = get_extension(my_strtolower($avatar['name']));
    	if(!preg_match("#^(gif|jpg|jpeg|jpe|bmp|png)$#i", $ext))
    	{
        	$ret['error'] = 'Invalid file type. An uploaded avatar must be in GIF, JPEG, BMP or PNG format';
        	return $ret;
    	}

	
		$avatarpath = $avataruploadpath;
	

	$filename = "avatar_".$uid.".".$ext;
	$file = upload_file($avatar, $avatarpath, $filename);
	if(!empty($file['error']))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = 'error_uploadfailed1';
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($avatarpath."/".$filename))
	{
		$ret['error'] = 'error_uploadfailed2';
		delete_uploaded_file($avatarpath."/".$filename);
		return $ret;
	}

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($avatarpath."/".$filename);
	if(!is_array($img_dimensions))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = 'error_uploadfailed9';
		return $ret;
	}

	

	// Check a list of known MIME types to establish what kind of avatar we're uploading
	$attachtypes = (array)$cache->read('attachtypes');

	$allowed_mime_types = array();
	foreach($attachtypes as $attachtype)
	{
		if(defined('IN_ADMINCP') || is_member($attachtype['groups']) && $attachtype['avatarfile'])
		{
			$allowed_mime_types[$attachtype['mimetype']] = $attachtype['maxsize'];
		}
	}

	$avatar['type'] = my_strtolower($avatar['type']);

	switch($avatar['type'])
	{
		case "image/gif":
			$img_type =  1;
			break;
		case "image/jpeg":
		case "image/x-jpg":
		case "image/x-jpeg":
		case "image/pjpeg":
		case "image/jpg":
			$img_type = 2;
			break;
		case "image/png":
		case "image/x-png":
			$img_type = 3;
			break;
		case "image/bmp":
		case "image/x-bmp":
		case "image/x-windows-bmp":
			$img_type = 6;
			break;
		default:
			$img_type = 0;
	}

	// Check if the uploaded file type matches the correct image type (returned by getimagesize)
	if(empty($allowed_mime_types[$avatar['type']]) || $img_dimensions[2] != $img_type || $img_type == 0)
	{
		$ret['error'] = 'error_uploadfailed3';
		delete_uploaded_file($avatarpath."/".$filename);
		return $ret;
	}

	
	// Next check the file size
	if(($avatarsize > 0 && $avatar['size'] > ($avatarsize*1024)) || $avatar['size'] > ($allowed_mime_types[$avatar['type']]*1024))
	{
		delete_uploaded_file($avatarpath."/".$filename);
		$ret['error'] = 'error_uploadsize';
		return $ret;
	}

	// Everything is okay so lets delete old avatars for this user
	remove_avatars($uid, $filename);

	$ret = array(
		"avatar" => $avataruploadpath."/".$filename,
		"width" => (int)$img_dimensions[0],
		"height" => (int)$img_dimensions[1]
	);
	$ret = $plugins->run_hooks("upload_avatar_end", $ret);
	return $ret;
}







function check_parse_php_upload_err($FILE)
{
	global $lang;

	$err = '';

	if(isset($FILE['error']) && $FILE['error'] != 0 && ($FILE['error'] != UPLOAD_ERR_NO_FILE || $FILE['name']))
	{
		$err = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
		switch($FILE['error'])
		{
			case 1: // UPLOAD_ERR_INI_SIZE
				$err .= 'error_uploadfailed_php1';
				break;
			case 2: // UPLOAD_ERR_FORM_SIZE
				$err .= 'error_uploadfailed_php2';
				break;
			case 3: // UPLOAD_ERR_PARTIAL
				$err .= 'error_uploadfailed_php3';
				break;
			case 4: // UPLOAD_ERR_NO_FILE
				$err .= 'error_uploadfailed_php4';
				break;
			case 6: // UPLOAD_ERR_NO_TMP_DIR
				$err .= 'error_uploadfailed_php6';
				break;
			case 7: // UPLOAD_ERR_CANT_WRITE
				$err .= 'error_uploadfailed_php7';
				break;
			default:
				$err .= sprintf('error_uploadfailed_phpx', $FILE['error']);
				break;
		}
	}

	return $err;
}


function create_attachment_index($path)
{
	$index = @fopen(rtrim($path, '/').'/index.html', 'w');
	@fwrite($index, '<html>\n<head>\n<title></title>\n</head>\n<body>\n&nbsp;\n</body>\n</html>');
	@fclose($index);
}


function mk_path_abs2($path, $base = TSDIR)
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








function upload_attachment($attachment, $update_attachment=false)
{
	global $mybb, $db, $theme, $templates, $posthash, $pid, $tid, $forum, $mybb, $lang, $plugins, $cache, $usergroups, $uploadspath, $attachthumbh, $attachthumbw, $CURUSER;

	
	$posthash = $db->escape_string($mybb->get_input('posthash'));
	$pid = (int)$pid;

	if(!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_php4;
		return $ret;
	}

    $attachtypes = (array)$cache->read('attachtypes');
    $attachment = $plugins->run_hooks("upload_attachment_start", $attachment);

	$allowed_mime_types = array();
	foreach($attachtypes as $ext => $attachtype)
	{
		//if(!is_member($attachtype['groups']) || ($attachtype['forums'] != -1 && strpos(','.$attachtype['forums'].',', ','.$forum['fid'].',') === false))
		//{
			//unset($attachtypes[$ext]);
		//}
	}

    $ext = get_extension($attachment['name']);
    // Check if we have a valid extension
    if(!isset($attachtypes[$ext]))
    {
    	$ret['error'] = 'error_attachtype';
		return $ret;
	}
	else
	{
		$attachtype = $attachtypes[$ext];
	}

	// check the length of the filename
	$maxFileNameLength = 255;
	if(my_strlen($attachment['name']) > $maxFileNameLength)
	{
		$ret['error'] = sprintf('error_attach_filename_length', htmlspecialchars_uni($attachment['name']), $maxFileNameLength);
		return $ret;
	}

	// Check the size
	if($attachment['size'] > $attachtype['maxsize']*1024 && $attachtype['maxsize'] != "")
	{
		$ret['error'] = sprintf('error_attachsize', htmlspecialchars_uni($attachment['name']), $attachtype['maxsize']);
		return $ret;
	}

	// Double check attachment space usage
	if($usergroups['attachquota'] > 0)
	{
		$query = $db->simple_select("attachments", "SUM(filesize) AS ausage", "uid='".$CURUSER['id']."'");
		$usage = $db->fetch_array($query);
		$usage = $usage['ausage']+$attachment['size'];
		if($usage > ($usergroups['attachquota']*1024))
		{
			$friendlyquota = mksize($usergroups['attachquota']*1024);
			$ret['error'] = sprintf('error_reachedattachquota', $friendlyquota);
			return $ret;
		}
	}

	// Gather forum permissions
	//$forumpermissions = forum_permissions($forum['fid']);

	// Check if an attachment with this name is already in the post
	if($pid != 0)
	{
		$uploaded_query = "pid='{$pid}'";
	}
	else
	{
		$uploaded_query = "posthash='{$posthash}'";
	}
	$query = $db->simple_select("attachments", "*", "filename='".$db->escape_string($attachment['name'])."' AND ".$uploaded_query);
	$prevattach = $db->fetch_array($query);
	if($prevattach && $update_attachment == false)
	{
		if(!$mybb->usergroup['caneditattachments'] && !$forumpermissions['caneditattachments'])
		{
			$ret['error'] = $lang->error_alreadyuploaded_perm;
			return $ret;
		}

		$ret['error'] = sprintf('error_alreadyuploaded', htmlspecialchars_uni($attachment['name']));
		return $ret;
	}

	// Check to see how many attachments exist for this post already
	
	$maxattachments = "5";
	
	if($maxattachments > 0 && $update_attachment == false)
	{
		$query = $db->simple_select("attachments", "COUNT(aid) AS numattachs", $uploaded_query);
		$attachcount = $db->fetch_field($query, "numattachs");
		if($attachcount >= $maxattachments)
		{
			$ret['error'] = sprintf('error_maxattachpost', $maxattachments);
			return $ret;
		}
	}

	
	$uploadspath_abs = mk_path_abs($uploadspath);
	$month_dir = '';
	//if($mybb->safemode == false)
	//{
		// Check if the attachment directory (YYYYMM) exists, if not, create it
		$month_dir = gmdate("Ym");
		if(!@is_dir($uploadspath_abs."/".$month_dir))
		{
			@mkdir($uploadspath_abs."/".$month_dir);
			// Still doesn't exist - oh well, throw it in the main directory
			if(!@is_dir($uploadspath_abs."/".$month_dir))
			{
				$month_dir = '';
			}
			else
			{
				create_attachment_index($uploadspath_abs."/".$month_dir);
			}
		}
	//}

	// All seems to be good, lets move the attachment!
	$filename = "post_".$CURUSER['id']."_".TIMENOW."_".md5(random_str()).".attach";
	

	$file = upload_file($attachment, $uploadspath_abs."/".$month_dir, $filename);

	// Failed to create the attachment in the monthly directory, just throw it in the main directory
	if(!empty($file['error']) && $month_dir)
	{
		$file = upload_file($attachment, $uploadspath_abs.'/', $filename);
	}
	elseif($month_dir)
	{
		$filename = $month_dir."/".$filename;
	}

	if(!empty($file['error']))
	{
		$ret['error'] = $lang->error_uploadfailed.$lang->error_uploadfailed_detail;
		switch($file['error'])
		{
			case 1:
				$ret['error'] .= 'error_uploadfailed_nothingtomove';
				break;
			case 2:
				$ret['error'] .= 'error_uploadfailed_movefailed';
				break;
		}
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($uploadspath_abs."/".$filename))
	{
		$ret['error'] = 'error_uploadfailed'.'error_uploadfailed_detail'.'error_uploadfailed_lost';
		return $ret;
	}

	// Generate the array for the insert_query
	$attacharray = array(
		"pid" => $pid,
		"posthash" => $posthash,
		"uid" => $CURUSER['id'],
		"filename" => $db->escape_string($file['original_filename']),
		"filetype" => $db->escape_string($file['type']),
		"filesize" => (int)$file['size'],
		"attachname" => $filename,
		"downloads" => 0,
		"dateuploaded" => TIMENOW
	);

	// If we're uploading an image, check the MIME type compared to the image type and attempt to generate a thumbnail
	if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe" || $ext == "webp")
	{
		// Check a list of known MIME types to establish what kind of image we're uploading
		switch(my_strtolower($file['type']))
		{
			case "image/gif":
				$img_type =  1;
				break;
			case "image/jpeg":
			case "image/x-jpg":
			case "image/x-jpeg":
			case "image/pjpeg":
			case "image/jpg":
				$img_type = 2;
				break;
			case "image/png":
			case "image/x-png":
				$img_type = 3;
				break;
				
				case "image/webp":
			    $img_type = 18;
			    break;
				
			default:
				$img_type = 0;
		}

		$supported_mimes = array();
		foreach($attachtypes as $attachtype)
		{
			if(!empty($attachtype['mimetype']))
			{
				$supported_mimes[] = $attachtype['mimetype'];
			}
		}

		// Check if the uploaded file type matches the correct image type (returned by getimagesize)
		$img_dimensions = @getimagesize($uploadspath_abs."/".$filename);

		$mime = "";
		$file_path = $uploadspath_abs."/".$filename;
		if(function_exists("finfo_open"))
		{
			$file_info = finfo_open(FILEINFO_MIME);
			list($mime, ) = explode(';', finfo_file($file_info, $file_path), 1);
			finfo_close($file_info);
		}
		else if(function_exists("mime_content_type"))
		{
			$mime = mime_content_type($file_path);
		}

		if(!is_array($img_dimensions) || ($img_dimensions[2] != $img_type && !in_array($mime, $supported_mimes)))
		{
			delete_uploaded_file($uploadspath_abs."/".$filename);
			$ret['error'] = $lang->error_uploadfailed;
			return $ret;
		}
		require_once INC_PATH . "/functions_image.php";
		$thumbname = str_replace(".attach", "_thumb.$ext", $filename);

		$attacharray = $plugins->run_hooks("upload_attachment_thumb_start", $attacharray);

		
		

		
		$thumbnail = generate_thumbnail($uploadspath_abs."/".$filename, $uploadspath_abs, $thumbname, $attachthumbh, $attachthumbw);

		if(!empty($thumbnail['filename']))
		{
			$attacharray['thumbnail'] = $thumbnail['filename'];
		}
		elseif($thumbnail['code'] == 4)
		{
			$attacharray['thumbnail'] = "SMALL";
		}
	}
	//if($forumpermissions['modattachments'] == 1 && !is_moderator($forum['fid'], "canapproveunapproveattachs"))
	//{
	//	$attacharray['visible'] = 0;
	//}
	//else
	//{
		$attacharray['visible'] = 1;
	//}

	$attacharray = $plugins->run_hooks("upload_attachment_do_insert", $attacharray);

	if($prevattach && $update_attachment == true)
	{
		unset($attacharray['downloads']); // Keep our download count if we're updating an attachment
		$db->update_query("attachments", $attacharray, "aid='".$db->escape_string($prevattach['aid'])."'");

		// Remove old attachment file
		// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
		$query = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($prevattach['attachname'])."'");
		if($db->fetch_field($query, "numreferences") == 0)
		{
			delete_uploaded_file($uploadspath_abs."/".$prevattach['attachname']);
			if($prevattach['thumbnail'])
			{
				delete_uploaded_file($uploadspath_abs."/".$prevattach['thumbnail']);
			}

			$date_directory = explode('/', $prevattach['attachname']);
			$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
			if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
			{
				delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
			}
		}

		$aid = $prevattach['aid'];
	}
	else
	{
		$aid = $db->insert_query("attachments", $attacharray);
		if($pid)
		{
			update_thread_counters($tid, array("attachmentcount" => "+1"));
		}
	}
	$ret['aid'] = $aid;
	return $ret;
}




function remove_attachment($pid, $posthash, $aid)
{
	global $db, $mybb, $plugins, $uploadspath;
	$aid = (int)$aid;
	$posthash = $db->escape_string($posthash);
	if(!empty($posthash))
	{
		$query = $db->simple_select("attachments", "aid, attachname, thumbnail, visible", "aid='{$aid}' AND posthash='{$posthash}'");
		$attachment = $db->fetch_array($query);
	}
	else
	{
		$query = $db->simple_select("attachments", "aid, attachname, thumbnail, visible", "aid='{$aid}' AND pid='{$pid}'");
		$attachment = $db->fetch_array($query);
	}

	$plugins->run_hooks("remove_attachment_do_delete", $attachment);

	if($attachment === false)
	{
		// no attachment found with the given details
		return;
	}

	$db->delete_query("attachments", "aid='{$attachment['aid']}'");

	
	$uploadspath_abs = mk_path_abs($uploadspath);

	// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
	$query = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($attachment['attachname'])."'");
	if($db->fetch_field($query, "numreferences") == 0)
	{
		delete_uploaded_file($uploadspath_abs."/".$attachment['attachname']);
		if($attachment['thumbnail'] && $attachment['thumbnail'] !== 'SMALL')
		{
			delete_uploaded_file($uploadspath_abs."/".$attachment['thumbnail']);
		}

		$date_directory = explode('/', $attachment['attachname']);
		$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
		if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
		{
			delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
		}
	}

	if($attachment['visible'] == 1 && $pid)
	{
		$post = get_post($pid);
		update_thread_counters($post['tid'], array("attachmentcount" => "-1"));
	}
}



function remove_attachments($pid, $posthash="")
{
	global $db, $mybb, $plugins, $uploadspath;

	if($pid)
	{
		$post = get_post($pid);
	}
	$posthash = $db->escape_string($posthash);
	if($posthash != "" && !$pid)
	{
		$query = $db->simple_select("attachments", "*", "posthash='$posthash'");
	}
	else
	{
		$query = $db->simple_select("attachments", "*", "pid='$pid'");
	}

	
	$uploadspath_abs = mk_path_abs($uploadspath);

	$num_attachments = 0;
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['visible'] == 1)
		{
			$num_attachments++;
		}

		$plugins->run_hooks("remove_attachments_do_delete", $attachment);

		$db->delete_query("attachments", "aid='".$attachment['aid']."'");

		// Check if this attachment is referenced in any other posts. If it isn't, then we are safe to delete the actual file.
		$query2 = $db->simple_select("attachments", "COUNT(aid) as numreferences", "attachname='".$db->escape_string($attachment['attachname'])."'");
		if($db->fetch_field($query2, "numreferences") == 0)
		{
			delete_uploaded_file($uploadspath_abs."/".$attachment['attachname']);
			if($attachment['thumbnail'])
			{
				delete_uploaded_file($uploadspath_abs."/".$attachment['thumbnail']);
			}

			$date_directory = explode('/', $attachment['attachname']);
			$query_indir = $db->simple_select("attachments", "COUNT(aid) as indir", "attachname LIKE '".$db->escape_string_like($date_directory[0])."/%'");
			if($db->fetch_field($query_indir, 'indir') == 0 && @is_dir($uploadspath_abs."/".$date_directory[0]))
			{
				delete_upload_directory($uploadspath_abs."/".$date_directory[0]);
			}
		}
	}

	if($post['tid'])
	{
		update_thread_counters($post['tid'], array("attachmentcount" => "-{$num_attachments}"));
	}
}






function add_attachments($pid, $forumpermissions, $attachwhere, $action=false)
{
	global $db, $mybb, $editdraftpid, $lang;

	$ret = array();

	//if($forumpermissions['canpostattachments'])
	//{
		$attachments = array();
		$fields = array ('name', 'type', 'tmp_name', 'error', 'size');
		$aid = array();

		$total = isset($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 0;
		$filenames = "";
		$delim = "";
		for($i=0; $i<$total; ++$i)
		{
			foreach($fields as $field)
			{
				$attachments[$i][$field] = $_FILES['attachments'][$field][$i];
			}

			$FILE = $attachments[$i];
			if(!empty($FILE['name']) && !empty($FILE['type']) && $FILE['size'] > 0)
			{
				$filenames .= $delim . "'" . $db->escape_string($FILE['name']) . "'";
				$delim = ",";
			}
		}

		if ($filenames != '')
		{
			$query = $db->simple_select("attachments", "filename", "{$attachwhere} AND filename IN (".$filenames.")");

			while ($row = $db->fetch_array($query))
			{
				$aid[$row['filename']] = true;
			}
		}

		foreach($attachments as $FILE)
		{
			if($err = check_parse_php_upload_err($FILE))
			{
				$ret['errors'][] = $err;
				$mybb->input['action'] = $action;
			}
			else if(!empty($FILE['name']) && !empty($FILE['type']))
			{
				if($FILE['size'] > 0)
				{
					$filename = $db->escape_string($FILE['name']);
					$exists = !empty($aid[$filename]);

					$update_attachment = false;
					if($action == "editpost")
					{
						if($exists && $mybb->get_input('updateattachment'))
						{
							$update_attachment = true;
						}
					}
					else
					{
						if($exists && $mybb->get_input('updateattachment'))
						{
							$update_attachment = true;
						}
					}
					
					if(!$exists && $mybb->get_input('updateattachment') && $mybb->get_input('updateconfirmed', MyBB::INPUT_INT) != 1)
					{
						$ret['errors'][] = sprintf('error_updatefailed', $filename);
					}
					else
					{
						$attachedfile = upload_attachment($FILE, $update_attachment);

						if(!empty($attachedfile['error']))
						{
							$ret['errors'][] = $attachedfile['error'];
							$mybb->input['action'] = $action;
						}
						else if(isset($attachedfile['aid']) && $mybb->get_input('ajax', MyBB::INPUT_INT) == 1)
						{
							$ret['success'][] = array($attachedfile['aid'], get_attachment_icon(get_extension($filename)), htmlspecialchars_uni($filename), mksize($FILE['size']));
						}
					}
				}
				else
				{
					$ret['errors'][] = sprintf('error_uploadempty', htmlspecialchars_uni($FILE['name']));
					$mybb->input['action'] = $action;
				}
			}
		}
	//}

	return $ret;
}





/**
 * Delete an uploaded file both from the relative path and the CDN path if a CDN is in use.
 *
 * @param string $path The relative path to the uploaded file.
 *
 * @return bool Whether the file was deleted successfully.
 */
function delete_uploaded_file($path = '')
{
	global $mybb, $plugins;

	$cdnpath = "";
	$usecdn = "0";
	
	$deleted = false;

	$deleted = @unlink($path);

	$cdn_base_path = rtrim($cdnpath, '/');
	$path = ltrim($path, '/');
	$cdn_path = realpath($cdn_base_path . '/' . $path);

	if(!empty($usecdn) && !empty($cdn_base_path))
	{
		$deleted = $deleted && @unlink($cdn_path);
	}

	$hook_params = array(
		'path' => &$path,
		'deleted' => &$deleted,
	);

	$plugins->run_hooks('delete_uploaded_file', $hook_params);

	return $deleted;
}



/**
 * Actually move a file to the uploads directory
 *
 * @param array $file The PHP $_FILE array for the file
 * @param string $path The path to save the file in
 * @param string $filename The filename for the file (if blank, current is used)
 * @return array The uploaded file
 */
 
 
function my_chmod($file, $mode)
{
	// Passing $mode as an octal number causes strlen and substr to return incorrect values. Instead pass as a string
	if(substr($mode, 0, 1) != '0' || strlen($mode) !== 4)
	{
		return false;
	}
	$old_umask = umask(0);

	// We convert the octal string to a decimal number because passing a octal string doesn't work with chmod
	// and type casting subsequently removes the prepended 0 which is needed for octal numbers
	$result = chmod($file, octdec($mode));
	umask($old_umask);
	return $result;
}




function delete_upload_directory($path = '')
{
	global $mybb, $plugins;

	$deleted = false;

	$deleted_index = @unlink(rtrim($path, '/').'/index.html');

	$deleted = @rmdir($path);

	$cdnpath = "";
	$usecdn = "0";
	
	$cdn_base_path = rtrim($cdnpath, '/');
	$path = ltrim($path, '/');
	$cdn_path = rtrim(realpath($cdn_base_path . '/' . $path), '/');

	if(!empty($usecdn) && !empty($cdn_base_path))
	{
		$deleted = $deleted && @rmdir($cdn_path);
	}

	$hook_params = array(
		'path' => &$path,
		'deleted' => &$deleted,
	);

	$plugins->run_hooks('delete_upload_directory', $hook_params);

	// If not successfully deleted then reinstante the index file
	if(!$deleted && $deleted_index)
	{
		create_attachment_index($path);
	}

	return $deleted;
}
 
 
function upload_file($file, $path, $filename="")
{
	global $plugins, $mybb;

	$upload = array();

	if(empty($file['name']) || $file['name'] == "none" || $file['size'] < 1)
	{
		$upload['error'] = 1;
		return $upload;
	}

	if(!$filename)
	{
		$filename = $file['name'];
	}

	$upload['original_filename'] = preg_replace("#/$#", "", $file['name']); // Make the filename safe
	$filename = preg_replace("#/$#", "", $filename); // Make the filename safe
	$moved = @move_uploaded_file($file['tmp_name'], $path."/".$filename);

	$cdn_path = '';

	$moved_cdn = copy_file_to_cdn($path."/".$filename, $cdn_path);

	if(!$moved)
	{
		$upload['error'] = 2;
		return $upload;
	}
	@my_chmod($path."/".$filename, '0644');
	$upload['filename'] = $filename;
	$upload['path'] = $path;
	$upload['type'] = $file['type'];
	$upload['size'] = $file['size'];
	$upload = $plugins->run_hooks("upload_file_end", $upload);

	if($moved_cdn)
	{
		$upload['cdn_path'] = $cdn_path;
	}

	return $upload;
}
