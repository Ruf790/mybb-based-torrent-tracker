<?php


define("IN_MYBB", 1);
define("IGNORE_CLEAN_VARS", "sid");
define('SCRIPTNAME', 'private.php');



 
 define('IN_FORUM', true);

 require_once "global.php";
 



require_once 'cache/smilies.php';

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/functions_user.php";
require_once INC_PATH."/class_parser.php";
$parser = new postParser;


// Include our base data handler class
require_once INC_PATH . '/datahandler.php';


  


// Load global language phrases
$lang->load("private");

if($enablepms == 0)
{
	error($lang->private['pms_disabled']);
}



if($CURUSER['id'] == '/' || $CURUSER['id'] == 0 || $usergroups['canusepms'] == 0)
{
	print_no_permission();
}



$mybb->input['fid'] = $mybb->get_input('fid', MyBB::INPUT_INT);

$folder_id = $folder_name = $folderjump_folder = $folderoplist_folder = $foldersearch_folder ='';

$foldernames = array();
$foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
foreach($foldersexploded as $key => $folders)
{
	$folderinfo = explode("**", $folders, 2);
	if($mybb->input['fid'] == $folderinfo[0])
	{
		$sel = ' selected="selected"';
	}
	else
	{
		$sel = '';
	}
	$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
	$foldernames[$folderinfo[0]] = $folderinfo[1];

	$folder_id = $folderinfo[0];
	$folder_name = $folderinfo[1];

	$folderjump_folder .= '<option value="'.$folder_id.'"'.$sel.'>'.$folder_name.'</option>';

	// Manipulate search folder selection & move selector to omit "Unread"
	if($folder_id != 1)
	{
		if($folder_id == 0)
		{
			$folder_id = 1;
		}
		$folderoplist_folder .= '<option value="'.$folder_id.'"'.$sel.'>'.$folder_name.'</option>';
		$foldersearch_folder .= '<option value="'.$folder_id.'"'.$sel.'>'.$folder_name.'</option>';
	}
}

$from_fid = $mybb->input['fid'];

$folderjump = '<select name="jumpto" class="form-select form-select-sm border">
'.$folderjump_folder.'
</select>';
$folderoplist = '<input type="hidden" value="'.$from_fid.'" name="fromfid" />
<select name="fid" class="form-select form-select-sm border w-auto pe-5">
'.$folderoplist_folder.'
</select>';
$foldersearch = '<select name="folder[]" id="folder" class="form-select form-select-sm border w-auto pe-5">
<option selected="selected">'.$lang->private['all_folders'].'</option>
'.$foldersearch_folder.'
</select>';

usercp_menu();

$plugins->run_hooks("private_start");

// Make navigation
add_breadcrumb($lang->private['nav_pms'], "private.php");

$mybb->input['action'] = $mybb->get_input('action');
switch($mybb->input['action'])
{
	case "send":
		add_breadcrumb('nav_send');
		break;
	case "tracking":
		add_breadcrumb('nav_tracking');
		break;
	case "folders":
		add_breadcrumb('nav_folders');
		break;
	case "empty":
		add_breadcrumb('nav_empty');
		break;
	case "export":
		add_breadcrumb('nav_export');
		break;
	case "advanced_search":
		add_breadcrumb('nav_search');
		break;
	case "results":
		add_breadcrumb('nav_results');
		break;
}

if(!empty($mybb->input['preview']))
{
	$mybb->input['action'] = "send";
}

if(($mybb->input['action'] == "do_search" || $mybb->input['action'] == "do_stuff" && ($mybb->get_input('quick_search') || !$mybb->get_input('hop') && !$mybb->get_input('moveto') && !$mybb->get_input('delete'))) && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_search_start");

	// Simulate coming from our advanced search form with some preset options
	if($mybb->get_input('quick_search'))
	{
		$mybb->input['action'] = "do_search";
		$mybb->input['subject'] = 1;
		$mybb->input['message'] = 1;
		$mybb->input['folder'] = $mybb->input['fid'];
		unset($mybb->input['jumpto']);
		unset($mybb->input['fromfid']);
	}

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		$timecut = TIMENOW-$mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "uid='{$CURUSER['id']}' AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$remaining_time = $mybb->settings['searchfloodtime']-(TIMENOW-$last_search['dateline']);
			if($remaining_time == 1)
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			}
			else
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}

	if($mybb->get_input('subject', MyBB::INPUT_INT) != 1 && $mybb->get_input('message', MyBB::INPUT_INT) != 1)
	{
		stderr('error_nosearchresults');
	}

	if($mybb->get_input('message', MyBB::INPUT_INT) == 1)
	{
		$resulttype = "pmmessages";
	}
	else
	{
		$resulttype = "pmsubjects";
	}

	$search_data = array(
		"keywords" => $mybb->get_input('keywords'),
		"subject" => $mybb->get_input('subject', MyBB::INPUT_INT),
		"message" => $mybb->get_input('message', MyBB::INPUT_INT),
		"sender" => $mybb->get_input('sender'),
		"status" => $mybb->get_input('status', MyBB::INPUT_ARRAY),
		"folder" => $mybb->get_input('folder', MyBB::INPUT_ARRAY)
	);

	if($db->can_search == true)
	{
		require_once INC_PATH."/functions_search.php";

		$search_results = privatemessage_perform_search_mysql($search_data);
	}
	else
	{
		error($lang->error_no_search_support);
	}
	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $CURUSER['id'],
		"dateline" => TIMENOW,
		"ipaddress" => $db->escape_binary($session->packedip),
		"threads" => '',
		"posts" => '',
		"resulttype" => $resulttype,
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->get_input('keywords')),
	);
	$plugins->run_hooks("private_do_search_process");

	$db->insert_query("searchlog", $searcharray);

	// Sender sort won't work yet
	$sortby = array('subject', 'sender', 'dateline');

	if(in_array($mybb->get_input('sort'), $sortby))
	{
		$sortby = $mybb->get_input('sort');
	}
	else
	{
		$sortby = "dateline";
	}

	if(my_strtolower($mybb->get_input('sortordr')) == "asc")
	{
		$sortorder = "asc";
	}
	else
	{
		$sortorder = "desc";
	}

	$plugins->run_hooks("private_do_search_end");
	redirect("private.php?action=results&sid=".$sid."&sortby=".$sortby."&order=".$sortorder, $lang->private['redirect_searchresults']);
}

if($mybb->input['action'] == "results")
{
	$sid = $mybb->get_input('sid');
	$query = $db->simple_select("searchlog", "*", "sid='".$db->escape_string($sid)."' AND uid='{$CURUSER['id']}'");
	$search = $db->fetch_array($query);

	if(!$search)
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("private_results_start");

	// Decide on our sorting fields and sorting order.
	$order = my_strtolower($mybb->get_input('order'));
	$sortby = my_strtolower($mybb->get_input('sortby'));

	$sortby_accepted = array('subject', 'username', 'dateline');

	if(in_array($sortby, $sortby_accepted))
	{
		$query_sortby = $sortby;

		if($query_sortby == "username")
		{
			$query_sortby = "fromusername";
		}
	}
	else
	{
		$sortby = $query_sortby = "dateline";
	}

	if($order != "asc")
	{
		$order = "desc";
	}

	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "pmid IN(".$db->escape_string($search['querycache']).")");
	$pmscount = $db->fetch_field($query, "total");

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $f_threadsperpage;
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
		$pages = ceil($pmscount / $perpage);
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
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	// Work out if we have terms to highlight
	$highlight = "";
	if($search['keywords'])
	{
		$highlight = "&amp;highlight=".urlencode($search['keywords']);
	}

	// Do Multi Pages
	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}
	$multipage = multipage($pmscount, $perpage, $page, "private.php?action=results&amp;sid=".htmlspecialchars_uni($mybb->get_input('sid'))."&amp;sortby={$sortby}&amp;order={$order}");
	$messagelist = '';

	

	// Cache users in multiple recipients for sent & drafts folder
	// Get all recipients into an array
	$cached_users = $get_users = array();
	$users_query = $db->simple_select("privatemessages", "recipients", "pmid IN(".$db->escape_string($search['querycache']).")", array('limit_start' => $start, 'limit' => $perpage, 'order_by' => $query_sortby, 'order_dir' => $order));
	while($row = $db->fetch_array($users_query))
	{
		$recipients = my_unserialize($row['recipients']);
		if(isset($recipients['to']) && is_array($recipients['to']) && count($recipients['to']))
		{
			$get_users = array_merge($get_users, $recipients['to']);
		}

		if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
		{
			$get_users = array_merge($get_users, $recipients['bcc']);
		}
	}

	$get_users = implode(',', array_unique($get_users));

	// Grab info
	if($get_users)
	{
		$users_query = $db->simple_select("users", "id, username, usergroup, displaygroup", "id IN ({$get_users})");
		while($user = $db->fetch_array($users_query))
		{
			$cached_users[$user['id']] = $user;
		}
	}

	$query = $db->sql_query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername
		FROM privatemessages pm
		LEFT JOIN users fu ON (fu.id=pm.fromid)
		LEFT JOIN users tu ON (tu.id=pm.toid)
		WHERE pm.pmid IN(".$db->escape_string($search['querycache']).") AND pm.uid='{$CURUSER['id']}'
		ORDER BY pm.{$query_sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($message = $db->fetch_array($query))
	{
		$msgalt = $msgstatus = '';

		
// Determine Folder Icon

// В том же блоке где определяете статус
if ($message['status'] == 0) {
    $msgstatus   = 'new_pm';
    $msgalt      = 'Новое сообщение';
    $fa_icon_html = '<i class="fa-solid fa-envelope"></i>';
    $icon_color  = '#e74c3c';
    $icon_bg     = 'rgba(231, 76, 60, 0.1)';
    $badge_class = 'status-new';
}
else if ($message['status'] == 1) {
    $msgstatus   = 'old_pm';
    $msgalt      = 'Прочитанное сообщение';
    $fa_icon_html = '<i class="fa-solid fa-envelope-open"></i>';
    $icon_color  = '#95a5a6';
    $icon_bg     = 'rgba(149, 165, 166, 0.1)';
    $badge_class = 'status-read';
}
else if ($message['status'] == 3) {
    $msgstatus   = 're_pm';
    $msgalt      = 'Ответ на сообщение';
    $fa_icon_html = '<i class="fa-solid fa-reply"></i>';
    $icon_color  = '#3498db';
    $icon_bg     = 'rgba(52, 152, 219, 0.1)';
    $badge_class = 'status-reply';
}
else if ($message['status'] == 4) {
    $msgstatus   = 'fw_pm';
    $msgalt      = 'Пересланное сообщение';
    $fa_icon_html = '<i class="fa-solid fa-share"></i>';
    $icon_color  = '#27ae60';
    $icon_bg     = 'rgba(39, 174, 96, 0.1)';
    $badge_class = 'status-forward';
}















		$folder = $message['folder'];

		$tofromuid = 0;
		if($folder == 2 || $folder == 3)
		{
			// Sent Items or Drafts Folder Check
			$recipients = my_unserialize($message['recipients']);
			$to_users = $bcc_users = '';
			if(
				isset($recipients['to']) &&
				(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
			)
			{
				foreach($recipients['to'] as $uid)
				{
					$profilelink = get_profile_link($uid);
					$user = $cached_users[$uid];
					$user['username'] = htmlspecialchars_uni($user['username']);
					$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$to_users .= '<div class="popup_item_container"><a href="'.$profilelink.'" class="popup_item">'.$username.'</a></div>';
				}
				if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
				{
					$bcc_users = '<div class="tcat"><strong>'.$lang->private['bcc'].'</strong></div>';
					foreach($recipients['bcc'] as $uid)
					{
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$user['username'] = htmlspecialchars_uni($user['username']);
						$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						$bcc_users .= '<div class="popup_item_container"><a href="'.$profilelink.'" class="popup_item">'.$username.'</a></div>';
					}
				}

				$tofromusername = '<a href="private.php?action=read&amp;pmid='.$message['pmid'].'" id="private_message_'.$message['pmid'].'">'.$lang->private['multiple_recipients'].'</a>
		<div id="private_message_'.$message['pmid'].'_popup" class="popup_menu" style="display: none;"><div class="tcat"><strong>'.$lang->private['to'].'</strong></div>'.$to_users.''.$bcc_users.'</div>
<script type="text/javascript">
<!--
	$("#private_message_'.$message['pmid'].'").popupMenu();
// -->
</script>';
			}
			else if($message['toid'])
			{
				$tofromusername = htmlspecialchars_uni($message['tousername']);
				$tofromuid = $message['toid'];
			}
			else
			{
				$tofromusername = $lang->not_sent;
			}
		}
		else
		{
			$tofromusername = htmlspecialchars_uni($message['fromusername']);
			$tofromuid = $message['fromid'];
			if($tofromuid == 0)
			{
				$tofromusername = 'Ruff Tracker Engine';
			}
		}

		$tofromusername = build_profile_link($tofromusername, $tofromuid);

		$denyreceipt = '';

		

		if(!trim($message['subject']))
		{
			$message['subject'] = $lang->pm_no_subject;
		}

		$message['subject'] = $parser->parse_badwords($message['subject']);

		if(my_strlen($message['subject']) > 50)
		{
			$message['subject'] = htmlspecialchars_uni(my_substr($message['subject'], 0, 50)."...");
		}
		else
		{
			$message['subject'] = htmlspecialchars_uni($message['subject']);
		}

		if($message['folder'] != "3")
		{
			$senddate = my_datee('relative', $message['dateline']);
		}
		else
		{
			$senddate = $lang->not_sent;
		}

		$fid = "0";
		if((int)$message['folder'] > 1)
		{
			$fid = $message['folder'];
		}
		$foldername = $foldernames[$fid];

		// What we do here is parse the post using our post parser, then strip the tags from it
		$parser_options = array(
			'allow_html' => 0,
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'filter_badwords' => 1
		);
		$message['message'] = strip_tags($parser->parse_message($message['message'], $parser_options));
		if(my_strlen($message['message']) > 200)
		{
			$message['message'] = my_substr($message['message'], 0, 200)."...";
		}

		$messagelist .= '<div class="card mb-0 border-0">
	<div class="card-body pt-0 inline_row">
	<div class="row g-2 pb-3 border-bottom mb-0">
		<div class="col-auto col-sm-auto col-md-auto col-lg-1 col-xl-1 col-xxl-1 align-self-center">
			<avatarep_uid_['.$tofromuid.']>
				</div>
			<div class="col align-self-center">
				<h6 class="mb-0 text-forum"><a class="'.$msgstatus.'" href="private.php?action=read&amp;pmid='.$message['pmid'].'">'.$message['subject'].'</a>'.$denyreceipt.'</h6>
				<i class="fa-regular fa-folder-open small text-desc"></i> &nbsp;<a href="private.php?fid='.$message['folder'].'" class="links small">'.$foldername.'</a> &mdash; <span class="links small">'.$tofromusername.'</span>
			</div>
			<div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center">
				'.$icon.' <img src="'.$theme['imgdir'].'/'.$msgstatus.'.png" alt="'.$msgalt.'" title="'.$msgalt.'" /> 
			</div>
			<div class="col-lg-3 text-muted align-self-center">
				'.$senddate.'
				<span class="float-end">
					<input type="checkbox" class="form-check-input" name="check['.$message['pmid'].']" value="1" />
				</span>
			</div>
		</div>
	</div>
	</div>';
	}

	if($db->num_rows($query) == 0)
	{
		$messagelist = '<tr>
<td colspan="7" class="trow1">'.$lang->private['nomessages'].'</td>
</tr>';





	}

	$plugins->run_hooks("private_results_end");

	$results = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['private_messaging'].'</title>

</head>
<body>

	<form action="private.php" method="post" name="pmForm">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
	
<div class="card border-0 mb-3">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-2 text-forum">
			<div class="col-1">
				&nbsp;
			</div>
			<div class="col align-self-center">
				'.$lang->private['message'].'</a> &mdash; '.$lang->private['sender'].'
			</div>
			<div class="col-3 align-self-center">
				<span class="d-none d-sm-none d-md-none d-lg-inline-block d-xl-inline-block d-xxl-inline-block">'.$lang->private['date_sent'].'</span>
				<span class="float-end"><input name="allbox" title="'.$lang->private['check_all'].'" type="checkbox" class="form-check-input checkall" value="'.$lang->private['check_all'].'" /></span>
			</div>
	</div>
	
	</div>
	</div>	
	
'.$messagelist.'
	
	
	
	
	
	
	
	<div class="card border-0 mt-3">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-1">
			<div class="col">
				&nbsp;
			</div>
						<div class="col-auto">
				<input type="submit" class="btn btn-primary btn-sm" name="moveto" value="'.$lang->private['move_to'].'" />&nbsp; <i class="fa-solid fa-arrow-right"></i>&nbsp;  
			</div>
			<div class="col-auto">
	'.$folderoplist.' 
			</div>
			<div class="col-auto">
				&nbsp;'.$lang->private['or'].'&nbsp; <input type="submit" class="btn btn-primary btn-sm" name="delete" value="'.$lang->private['delete'].'" />
			</div>
			<div class="col">
				&nbsp;
			</div>
	</div>
		</div>
	</div>	

<input type="hidden" name="action" value="do_stuff" />
</form>

'.$multipage.'
</div></div>
</div>
</div>
</div>

</body>
</html>';
	
	echo $results;
}

if($mybb->input['action'] == "advanced_search")
{
	$plugins->run_hooks("private_advanced_search");

	$advanced_search = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['advanced_private_message_search'].'</title>

</head>
<body>

<form action="private.php" method="post" name="pmForm">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<input type="hidden" name="action" value="do_search" />
<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
				
<div class="card">
<div class="card-body">
	
	
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['keywords'].'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['keywords'].'
</div>
<div class="col">
<label for="keywords">'.$lang->private['keywords'].'</label>
<input type="text" class="form-control form-control-sm border" name="keywords" id="keywords" maxlength="250" />
	<div class="form-check form-check-inline mt-1">
  <input type="checkbox" class="form-check-input" name="subject" checked="checked" value="1" />
  <label class="form-check-label" for="inlineCheckbox1">'.$lang->private['search_in_subject'].'</label>
</div>
	<div class="form-check form-check-inline mt-1">
  <input type="checkbox" class="form-check-input" name="message" checked="checked" value="1" />
  <label class="form-check-label" for="inlineCheckbox1">'.$lang->private['search_in_message'].'</label>
</div>	
</div>
</div>
	
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->sender.'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['sender'].'
</div>
<div class="col">
	<label for="keywords">'.$lang->private['sender'].'</label>
			<input type="text" class="form-control form-control-sm border border" name="sender" id="sender" maxlength="250" />	
</div>
</div>	
	
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['message_status'].'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['message_status'].'
</div>
<div class="col">
 <input type="checkbox" name="status[new]" class="form-check-input" checked="checked" value="1" /> '.$lang->private['message_status_new'].'
	<br />
<input type="checkbox" class="form-check-input" name="status[replied]" checked="checked" value="1" /> '.$lang->private['message_status_replied_to'].'	
	<br />
<input type="checkbox" class="form-check-input" name="status[forwarded]" checked="checked" value="1" /> '.$lang->private['message_status_forwarded'].'
	<br />
<input type="checkbox" class="form-check-input" name="status[read]" checked="checked" value="1" /> '.$lang->private['message_status_read'].'
	
</div>
</div>	
	
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['folder'].'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['folder'].'
</div>
<div class="col">
'.$foldersearch.'
</div>
</div>		
	
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['search_options'].'</div>
<div class="row g-3 m-auto pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['search_options'].'
</div>
<div class="col">
	<div class="pb-3 mb-3 border-bottom">
<label>'.$lang->private['sort_by'].'</label>
		<select name="sort" id="sort" class="form-select form-select-sm border w-auto pe-5">
				<option value="subject">'.$lang->private['sort_by_subject'].'</option>
				<option value="sender">'.$lang->private['sort_by_sender'].'</option>
				<option value="dateline" selected="selected">'.$lang->private['sort_by_date'].'</option>
			</select>
	</div>
		<div class="radio-toolbar mt-2">
  <input type="radio" name="sortordr" id="order_asc" value="asc" />
  <label for="order_asc">'.$lang->private['ascending_order'].'</label>

  <input type="radio" name="sortordr" checked="checked" id="order_desc" value="desc" />
  <label for="order_desc">'.$lang->private['descending_order'].'</label>
</div>
	
</div>
</div>		

	</div>
				
<div class="card-footer text-center">
<button type="submit" value="'.$lang->private['search_private_messages'].'" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;'.$lang->private['search_private_messages'].'</button></div>
</div>
</form>
</div></div>
</div>
</div>
</div>

<link rel="stylesheet" href="'.$BASEURL.'/scripts/select2/select2-2.css?ver=1807" />
<script type="text/javascript" src="'.$BASEURL.'/scripts/select2/select2.min.js?ver=1806"></script>

<script type="text/javascript">
<!--

	MyBB.select2();
	$("#sender").select2({
		placeholder: "{Search for a user}",
		minimumInputLength: 2,
		multiple: false,
		allowClear: true,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		}
	});

// -->
</script>
</body>
</html>';

	stdhead($lang->private['advanced_private_message_search']);
	
	echo $advanced_search;
	
	stdfoot();
}

// Dismissing a new/unread PM notice
if($mybb->input['action'] == "dismiss_notice")
{
	if($CURUSER['pmnotice'] != 2)
	{
		exit;
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$updated_user = array(
		"pmnotice" => 1
	);
	$db->update_query("users", $updated_user, "id='{$CURUSER['id']}'");

	if(!empty($mybb->input['ajax']))
	{
		echo 1;
		exit;
	}
	else
	{
		header("Location: index.php");
		exit;
	}
}

$send_errors = '';

if($mybb->input['action'] == "do_send" && $mybb->request_method == "post")
{
	
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_send_do_send");

	// Attempt to see if this PM is a duplicate or not
	$to = array_map("trim", explode(",", $mybb->get_input('to')));
	$to = array_unique($to); // Filter out any duplicates
	$to_escaped = implode("','", array_map(array($db, 'escape_string'), array_map('my_strtolower', $to)));
	$time_cutoff = TIMENOW - (5 * 60 * 60);
	$query = $db->sql_query("
		SELECT pm.pmid
		FROM privatemessages pm
		LEFT JOIN users u ON(u.id=pm.toid)
		WHERE LOWER(u.username) IN ('{$to_escaped}') AND pm.dateline > {$time_cutoff} AND pm.fromid='{$CURUSER['id']}' AND pm.subject='".$db->escape_string($mybb->get_input('subject'))."' AND pm.message='".$db->escape_string($mybb->get_input('message'))."' AND pm.folder!='3'
		LIMIT 0, 1
	");
	if($db->num_rows($query) > 0)
	{
		stderr($lang->private['error_pm_already_submitted']);
	}

	require_once INC_PATH."/datahandlers/pm.php";
	$pmhandler = new PMDataHandler();

	$pm = array(
		"subject" => $mybb->get_input('subject'),
		"message" => $mybb->get_input('message'),
		"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
		"fromid" => $CURUSER['id'],
		"do" => $mybb->get_input('do'),
		"pmid" => $mybb->get_input('pmid', MyBB::INPUT_INT),
		"ipaddress" => $session->packedip
	);

	// Split up any recipients we have
	$pm['to'] = $to;
	if(!empty($mybb->input['bcc']))
	{
		$pm['bcc'] = explode(",", $mybb->get_input('bcc'));
		$pm['bcc'] = array_map("trim", $pm['bcc']);
	}

	$mybb->input['options'] = $mybb->get_input('options', MyBB::INPUT_ARRAY);

	if(!$usergroups['cantrackpms'])
	{
		$mybb->input['options']['readreceipt'] = false;
	}

	$pm['options'] = array();
	
	
	if(isset($mybb->input['options']['savecopy']) && $mybb->input['options']['savecopy'] == 1)
	{
		$pm['options']['savecopy'] = 1;
	}
	else
	{
		$pm['options']['savecopy'] = 0;
	}
	if(isset($mybb->input['options']['readreceipt']))
	{
		$pm['options']['readreceipt'] = $mybb->input['options']['readreceipt'];
	}

	if(!empty($mybb->input['saveasdraft']))
	{
		$pm['saveasdraft'] = 1;
	}
	$pmhandler->set_data($pm);

	// Now let the pm handler do all the hard work.
	if(!$pmhandler->validate_pm())
	{
		$pm_errors = $pmhandler->get_friendly_errors();
		$send_errors = inline_error($pm_errors);
		$mybb->input['action'] = "send";
	}
	else
	{
		$pminfo = $pmhandler->insert_pm();
		$plugins->run_hooks("private_do_send_end");

		if(isset($pminfo['draftsaved']))
		{
			redirect("private.php", $lang->private['redirect_pmsaved']);
		}
		else
		{
			redirect("private.php", $lang->private['redirect_pmsent']);
		}
	}
}

if($mybb->input['action'] == "send")
{
	

	$plugins->run_hooks("private_send_start");

	$smilieinserter = $codebuttons = '';

	
	
// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';


// Вызов функции
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message');


$codebuttons ='


' . $editor['toolbar'] . '

 ' . $editor['modal'] . '

';
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

	$post_icon = $lang->private['message_icon'];

	//$posticons = get_post_icons();
	$message = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('message')));
	$subject = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('subject')));

	//$optionschecked = array('signature' => '', 'disablesmilies' => '', 'savecopy' => '', 'readreceipt' => '');
	$optionschecked = array('savecopy' => '', 'readreceipt' => '');
	$to = $bcc = '';

	if(!empty($mybb->input['preview']) || $send_errors)
	{
		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		//if(isset($options['signature']) && $options['signature'] == 1)
		//{
			//$optionschecked['signature'] = 'checked="checked"';
		//}
		//if(isset($options['disablesmilies']) && $options['disablesmilies'] == 1)
		//{
			//$optionschecked['disablesmilies'] = 'checked="checked"';
		//}
		if(isset($options['savecopy']) && $options['savecopy'] != 0)
		{
			$optionschecked['savecopy'] = 'checked="checked"';
		}
		if(isset($options['readreceipt']) && $options['readreceipt'] != 0)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}
		$to = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to'))))));
		$bcc = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc'))))));
	}

	$preview = '';
	// Preview
	if(!empty($mybb->input['preview']))
	{
		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		
		$query = $db->sql_query_prepared("
          SELECT u.username AS userusername, u.*
          FROM users u
          WHERE u.id = ?", [(int)$CURUSER['id']]);


		$post = $db->fetch_array($query);

		$post['userusername'] = $CURUSER['username'];
		$post['postusername'] = $CURUSER['username'];
		$post['message'] = $mybb->get_input('message');
		$post['subject'] = htmlspecialchars_uni($mybb->get_input('subject'));
		$post['icon'] = $mybb->get_input('icon', MyBB::INPUT_INT);
		
		
		$post['dateline'] = TIMENOW;

		
		// Merge usergroup data from the cache
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
			$post[$key] = $groupscache[$post['usergroup']][$field];
		}

		$postbit = build_postbit($post, 2);
		
		$preview = ''.$postbit.'';
	}
	else if(!$send_errors)
	{
		// New PM, so load default settings
		
		$optionschecked['readreceipt'] = 'checked="checked"';
		
		$optionschecked['savecopy'] = 'checked="checked"';
	}

	// Draft, reply, forward
	if($mybb->get_input('pmid') && empty($mybb->input['preview']) && !$send_errors)
	{
		$query = $db->sql_query("
			SELECT pm.*, u.username AS quotename
			FROM privatemessages pm
			LEFT JOIN users u ON (u.id=pm.fromid)
			WHERE pm.pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND pm.uid='{$CURUSER['id']}'
		");

		$pm = $db->fetch_array($query);
		$message = htmlspecialchars_uni($parser->parse_badwords($pm['message']));
		$subject = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

		if($pm['folder'] == "3")
		{
			// message saved in drafts
			$mybb->input['uid'] = $pm['toid'];

			
			if($pm['receipt'])
			{
				$optionschecked['readreceipt'] = 'checked="checked"';
			}

			// Get list of recipients
			$recipients = my_unserialize($pm['recipients']);
			$comma = $recipientids = '';
			if(isset($recipients['to']) && is_array($recipients['to']))
			{
				foreach($recipients['to'] as $recipient)
				{
					$recipient_list['to'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}
			}

			if(isset($recipients['bcc']) && is_array($recipients['bcc']))
			{
				foreach($recipients['bcc'] as $recipient)
				{
					$recipient_list['bcc'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}
			}

			if(!empty($recipientids))
			{
				$query = $db->simple_select("users", "id, username", "id IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && in_array($user['id'], $recipient_list['bcc']))
					{
						$bcc .= htmlspecialchars_uni($user['username']).', ';
					}
					else
					{
						$to .= htmlspecialchars_uni($user['username']).', ';
					}
				}
			}
		}
		else
		{
			// forward/reply
			$subject = preg_replace("#(FW|RE):( *)#is", '', $subject);
			$message = "[quote='{$pm['quotename']}']\n$message\n[/quote]";
			$message = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $message);

			require_once INC_PATH."/functions_posting.php";

			
			$maxpmquotedepth = "5";
			
			if($maxpmquotedepth != '0')
			{
				$message = remove_message_quotes($message, $maxpmquotedepth);
			}

			if($mybb->input['do'] == 'forward')
			{
				$subject = "Fw: $subject";
			}
			elseif($mybb->input['do'] == 'reply')
			{
				$subject = "Re: $subject";
				$uid = $pm['fromid'];
				if($CURUSER['id'] == $uid)
				{
					$to = $CURUSER['username'];
				}
				else
				{
					$query = $db->simple_select('users', 'username', "id='{$uid}'");
					$to = $db->fetch_field($query, 'username');
				}
				$to = htmlspecialchars_uni($to);
			}
			else if($mybb->input['do'] == 'replyall')
			{
				$subject = "Re: $subject";

				// Get list of recipients
				$recipients = my_unserialize($pm['recipients']);
				$recipientids = $pm['fromid'];
				if(isset($recipients['to']) && is_array($recipients['to']))
				{
					foreach($recipients['to'] as $recipient)
					{
						if($recipient == $CURUSER['id'])
						{
							continue;
						}
						$recipientids .= ','.$recipient;
					}
				}
				$comma = '';
				$query = $db->simple_select('users', 'id, username', "id IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					$to .= $comma.htmlspecialchars_uni($user['username']);
					$comma = $lang->private['comma'];
				}
			}
		}
	}

	// New PM with recipient preset
	if($mybb->get_input('uid', MyBB::INPUT_INT) && empty($mybb->input['preview']))
	{
		$query = $db->simple_select('users', 'username', "id='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		$to = htmlspecialchars_uni($db->fetch_field($query, 'username')).', ';
	}

	$max_recipients = '';
	if($usergroups['maxpmrecipients'] > 0)
	{
		$max_recipients = sprintf($lang->private['max_recipients'], $usergroups['maxpmrecipients']);
	}

	if($send_errors)
	{
		$to = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to'))))));
		$bcc = htmlspecialchars_uni(implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc'))))));
	}

	// Load the auto complete javascript if it is enabled.
	$autocompletejs = '<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var container = document.getElementById(\'to-container\');
    var toInput = document.getElementById(\'to\');
    
    if (!container || !toInput) return;
    
    var maxRecipients = parseInt(toInput.getAttribute(\'data-max-recipients\')) || 5;
    var input = container.querySelector(\'.select2-tags-input\');
    if (!input) return;
    
    var recipients = [];
    var debounceTimer = null;
    
    // Инициализация существующих значений
    if (toInput.value) {
        var values = toInput.value.split(\',\')
            .map(function(v) { return v.trim(); })
            .filter(function(v) { return v !== \'\'; });
        
        values.forEach(function(value) {
            addRecipient(value, true);
        });
    }
    
    // Dropdown для автодополнения
    var dropdown = document.createElement(\'div\');
    dropdown.className = \'select2-dropdown\';
    dropdown.style.cssText = \'position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: none;\';
    document.body.appendChild(dropdown);

    // Создаем элемент для отображения ошибки
    var errorDisplay = document.createElement(\'div\');
    errorDisplay.className = \'recipient-limit-error\';
    errorDisplay.style.cssText = \'color: #dc3545; font-size: 12px; margin-top: 5px; display: none;\';
    container.parentNode.insertBefore(errorDisplay, container.nextSibling);
    
    // Функция добавления получателя
    function addRecipient(username, skipDuplicateCheck) {
        console.log(\'Adding recipient:\', username, \'Current:\', recipients.length, \'Max:\', maxRecipients);
        
        // Проверка лимита
        if (recipients.length >= maxRecipients) {
            showLimitMessage();
            return;
        }
        
        if (!skipDuplicateCheck && recipients.includes(username)) {
            return;
        }
        
        recipients.push(username);
        
        // Создаем тег
        var tag = document.createElement(\'div\');
        tag.style.cssText = \'display: inline-flex; align-items: center; background: #e9ecef; border-radius: 16px; padding: 4px 8px; font-size: 14px; color: #495057; margin: 2px;\';
        
        var textSpan = document.createElement(\'span\');
        textSpan.textContent = username;
        textSpan.style.cssText = \'margin-right: 6px;\';
        tag.appendChild(textSpan);
        
        var removeBtn = document.createElement(\'button\');
        removeBtn.textContent = \'×\';
        removeBtn.style.cssText = \'background: none; border: none; color: #6c757d; font-size: 16px; cursor: pointer; padding: 0; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;\';
        removeBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            removeRecipient(username);
        };
        tag.appendChild(removeBtn);
        
        container.insertBefore(tag, input);
        
        // Обновляем оригинальное поле
        toInput.value = recipients.join(\', \');
        
        // Очищаем поле ввода
        input.value = \'\';
        
        // Включаем поле если оно было отключено
        enableInput();
        
        // Скрываем dropdown
        dropdown.style.display = \'none\';
        
        // Проверяем лимит после добавления
        if (recipients.length >= maxRecipients) {
            showLimitMessage();
        }
    }
    
    // Функция удаления получателя
    function removeRecipient(username) {
        var index = recipients.indexOf(username);
        if (index > -1) {
            recipients.splice(index, 1);
            toInput.value = recipients.join(\', \');
            redrawTags();
            enableInput(); // Включаем поле при удалении
        }
    }
    
    // Функция перерисовки тегов
    function redrawTags() {
        // Удаляем все существующие теги
        var tags = container.querySelectorAll(\'div\');
        for (var i = 0; i < tags.length; i++) {
            if (tags[i] !== input && !tags[i].classList.contains(\'select2-tags-input\')) {
                container.removeChild(tags[i]);
            }
        }
        
        // Создаем теги заново
        recipients.forEach(function(username) {
            var tag = document.createElement(\'div\');
            tag.style.cssText = \'display: inline-flex; align-items: center; background: #e9ecef; border-radius: 16px; padding: 4px 8px; font-size: 14px; color: #495057; margin: 2px;\';
            
            var textSpan = document.createElement(\'span\');
            textSpan.textContent = username;
            textSpan.style.cssText = \'margin-right: 6px;\';
            tag.appendChild(textSpan);
            
            var removeBtn = document.createElement(\'button\');
            removeBtn.textContent = \'×\';
            removeBtn.style.cssText = \'background: none; border: none; color: #6c757d; font-size: 16px; cursor: pointer; padding: 0; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center;\';
            removeBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                removeRecipient(username);
            };
            tag.appendChild(removeBtn);
            
            container.insertBefore(tag, input);
        });
    }
    
    // Показать сообщение о лимите
    function showLimitMessage() {
        var message = \'You are only allowed to send messages to \' + maxRecipients + \' users at a time\';
        showMessage(message);
        disableInput();
        
        // Показываем ошибку под полем
        errorDisplay.textContent = message;
        errorDisplay.style.display = \'block\';
    }
    
    // Включить поле ввода
    function enableInput() {
        input.disabled = false;
        input.placeholder = \'Search for users\';
        container.style.opacity = \'1\';
        container.style.borderColor = \'#ddd\';
        errorDisplay.style.display = \'none\';
    }
    
    // Отключить поле ввода
    function disableInput() {
        input.disabled = true;
        input.placeholder = \'Maximum recipients reached (\' + maxRecipients + \')\';
        container.style.opacity = \'0.7\';
        container.style.borderColor = \'#dc3545\';
    }
    
    // Поиск пользователей
    function searchUsers(query) {
        if (query.length < 2) {
            dropdown.style.display = \'none\';
            return;
        }
        
        fetch("xmlhttp.php?action=get_users&query=" + encodeURIComponent(query))
            .then(function(response) {
                return response.json();
            })
            .then(function(users) {
                displayResults(users);
            })
            .catch(function(error) {
                console.error(\'Error:\', error);
                dropdown.style.display = \'none\';
            });
    }
    
    // Отображение результатов
    function displayResults(users) {
        dropdown.innerHTML = \'\';
        
        if (!users || users.length === 0) {
            showMessage(\'No matches found\');
            return;
        }
        
        users.forEach(function(user) {
            var username = user.username || user.text || user.name || user.label || "";
            var item = document.createElement(\'div\');
            item.textContent = username;
            item.style.cssText = \'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: 14px; background: white; color: black;\';
            item.onmouseover = function() { 
                this.style.background = \'#007bff\'; 
                this.style.color = \'white\';
            };
            item.onmouseout = function() { 
                this.style.background = \'white\'; 
                this.style.color = \'black\';
            };
            item.onclick = function() { 
                addRecipient(username); 
            };
            dropdown.appendChild(item);
        });
        
        dropdown.style.display = \'block\';
        updateDropdownPosition();
    }
    
    // Показать сообщение
    function showMessage(message) {
        dropdown.innerHTML = \'<div style="padding: 8px 12px; color: #666; font-style: italic; font-size: 14px;">\' + message + \'</div>\';
        dropdown.style.display = \'block\';
        updateDropdownPosition();
    }
    
    // Обновление позиции dropdown
    function updateDropdownPosition() {
        var rect = container.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + window.scrollY) + \'px\';
        dropdown.style.left = (rect.left + window.scrollX) + \'px\';
        dropdown.style.width = rect.width + \'px\';
    }
    
    // Обработчики событий
    input.addEventListener(\'focus\', function() {
        container.style.borderColor = recipients.length >= maxRecipients ? \'#dc3545\' : \'#007bff\';
        container.style.boxShadow = \'0 0 0 2px rgba(0, 123, 255, 0.25)\';
        if (input.value.trim().length >= 2) {
            searchUsers(input.value.trim());
        }
    });
    
    input.addEventListener(\'blur\', function() {
        setTimeout(function() {
            container.style.borderColor = recipients.length >= maxRecipients ? \'#dc3545\' : \'#ddd\';
            container.style.boxShadow = \'none\';
            dropdown.style.display = \'none\';
        }, 200);
    });
    
    input.addEventListener(\'input\', function(e) {
        var query = e.target.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            searchUsers(query);
        }, 300);
    });
    
    input.addEventListener(\'keydown\', function(e) {
        if (e.key === \'Enter\' && input.value.trim().length >= 2) {
            e.preventDefault();
            addRecipient(input.value.trim());
        } else if (e.key === \'Backspace\' && input.value === \'\' && recipients.length > 0) {
            e.preventDefault();
            removeRecipient(recipients[recipients.length - 1]);
        }
    });
    
    window.addEventListener(\'resize\', updateDropdownPosition);
    
    document.addEventListener(\'click\', function(e) {
        if (!container.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = \'none\';
        }
    });
    
    // Показываем счетчик получателей
    var counterDisplay = document.createElement(\'div\');
    counterDisplay.className = \'recipient-counter\';
    counterDisplay.style.cssText = \'font-size: 12px; color: #6c757d; margin-top: 5px;\';
    container.parentNode.insertBefore(counterDisplay, container.nextSibling);
    
    function updateCounter() {
        counterDisplay.textContent = \'Recipients: \' + recipients.length + \'/\' + maxRecipients;
        if (recipients.length >= maxRecipients) {
            counterDisplay.style.color = \'#dc3545\';
            counterDisplay.style.fontWeight = \'bold\';
        } else {
            counterDisplay.style.color = \'#6c757d\';
            counterDisplay.style.fontWeight = \'normal\';
        }
    }
    
    // Обновляем счетчик при изменениях
    var originalAddRecipient = addRecipient;
    addRecipient = function(username, skipDuplicateCheck) {
        originalAddRecipient(username, skipDuplicateCheck);
        updateCounter();
    };
    
    var originalRemoveRecipient = removeRecipient;
    removeRecipient = function(username) {
        originalRemoveRecipient(username);
        updateCounter();
    };
    
    // Инициализируем счетчик
    updateCounter();
    
    console.log(\'Select2 tags initialized with limit:\', maxRecipients);
});
</script>';

	$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
	$do = $mybb->get_input('do');
	if($do != "forward" && $do != "reply" && $do != "replyall")
	{
		$do = '';
	}

	$buddy_select_to = $buddy_select_bcc = '';
	// See if it's actually worth showing the buddylist icon.
	if($CURUSER['buddylist'] != '' && $use_xmlhttprequest == 1)
	{
		$buddy_select = 'to';
		$buddy_select_to = '<script type="text/javascript"><!--
document.write(\'<br /><span class="smalltext"><a href="javascript:void(0)" onclick="UserCP.openBuddySelect(\\\''.$buddy_select.'\\\'); return false;"><img src="pic/buddies.png" alt="" style="vertical-align: middle;" alt="" title="'.$lang->private['select_from_buddies'].'" /> '.$lang->private['select_from_buddies'].'</a></span>\');
// --></script>';
		$buddy_select = 'bcc';
		$buddy_select_bcc = '<script type="text/javascript"><!--
document.write(\'<br /><span class="smalltext"><a href="javascript:void(0)" onclick="UserCP.openBuddySelect(\\\''.$buddy_select.'\\\'); return false;"><img src="pic/buddies.png" alt="" style="vertical-align: middle;" alt="" title="'.$lang->private['select_from_buddies'].'" /> '.$lang->private['select_from_buddies'].'</a></span>\');
// --></script>';
	}

	
    // Hide tracking option if no permission
	$private_send_tracking = '';
	if($usergroups['cantrackpms'])
	{
		$private_send_tracking = '<input type="checkbox" class="form-check-input" name="options[readreceipt]" value="1" tabindex="8" '.$optionschecked['readreceipt'].' /> '.$options_read_receipt.'';
	}








	

	$plugins->run_hooks("private_send_end");

	$send = '<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.$lang->private['compose_pm'].'</title>
 
    <link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/private.css">

    
    <script type="text/javascript" src="'.$BASEURL.'/scripts/usercp.js?ver=1827"></script>
</head>
<body>


<form action="private.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div id="fileIdsContainer"></div>

<div class="container-md py-4">
<div class="row g-4">
    <div class="col-lg-3">
        '.$usercpnav.'
    </div>
    <div class="col-lg-9">
        
        '.$preview.'
        '.$send_errors.'
        
        <div class="card">
            <div class="card-body">
                
                <!-- Recipients Section -->
                <div class="mb-4 pb-3 border-bottom">
                    <label class="fw-semibold mb-2">
                        <i class="fas fa-users text-primary me-2"></i> Recipients
                    </label>
                    <input name="to" id="to" value="'.$to.'" tabindex="1" class="form-control border" placeholder="Search for users" style="display: none;">
                    <div id="to-container"></div>
                    <div class="select2-counter" id="recipientCounter">
                        <i class="fas fa-user-plus me-1"></i> Recipients: 0/5
                    </div>
                    <div class="select2-error" id="recipientError">
                        <i class="fas fa-exclamation-triangle"></i> You are only allowed to send messages to 5 users at a time
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i> Click to search and select users (minimum 2 characters)
                    </small>
                </div>
                
                <!-- Subject Section -->
                <div class="mb-4 pb-3 border-bottom">
                    <label class="fw-semibold mb-2">
                        <i class="fas fa-heading text-primary me-2"></i> Subject
                    </label>
                    <input type="text" class="form-control" name="subject" maxlength="85" value="'.$subject.'" tabindex="3" placeholder="Enter message subject..." />
                </div>
                
                <!-- Message Editor -->
                <div class="mb-3">
                    <label class="fw-semibold mb-2">
                        <i class="fas fa-pen-fancy text-primary me-2"></i> Message
                    </label>
                    '.$codebuttons.'
                    <textarea name="message" id="message" class="form-control" style="width: 100%; height: 350px" tabindex="4" placeholder="Write your message here...">'.$message.'</textarea>
                </div>
                
                <!-- Options Toggle -->
                <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
                    <a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-pmop" href="#collapse-pmop" role="button">
                        <i class="fas fa-cog"></i> Options
                    </a>
                    <button type="submit" class="btn-thread" name="preview" value="Preview" tabindex="11">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
                
                <!-- Collapsible Options -->
                <div id="collapse-pmop" class="collapse">
                    <div class="mt-3 pt-2">
                        <div class="bg-nav p-2 rounded text-16 d-block d-lg-none mb-3">
                            <i class="fas fa-sliders-h me-2"></i> Options
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-3 d-none d-lg-block">
                                <div class="section-title-left text-center p-3">
                                    <i class="fas fa-sliders-h section-icon-large"></i>
                                    <div class="fw-bold mt-2">Options</div>
                                    <small class="text-muted">Message settings</small>
                                </div>
                            </div>
                            <div class="col-lg-9">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="options[savecopy]" value="1" tabindex="7" '.$optionschecked['savecopy'].' id="savecopy" />
                                    <label class="form-check-label" for="savecopy">
                                        <i class="fas fa-save text-success me-1"></i> Save a copy in my Sent Items folder
                                    </label>
                                </div>
                                '.$private_send_tracking.'
                            </div>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="action" value="do_send" />
                <input type="hidden" name="pmid" value="'.$pmid.'" />
                <input type="hidden" name="do" value="'.$do.'" />
                
            </div> <!-- card-body -->
            
            <div class="card-footer text-center">
                <button type="submit" class="btn btn-primary" name="submit" value="Send Message" tabindex="9" accesskey="s">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div> <!-- card -->
        
    </div> <!-- col -->
</div> <!-- row -->
</div> <!-- container -->
</form>


<script>
    window.maxRecipients = '.$usergroups['maxpmrecipients'].';
</script>
<script src="'.$BASEURL.'/scripts/select2-field.js"></script>


</body>
</html>';
	
	stdhead ('title');
	
	echo $send;
	
	stdfoot();
}

if($mybb->input['action'] == "read")
{
	$plugins->run_hooks("private_read");

	$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);

	$query = $db->sql_query("
		SELECT pm.*, u.*
		FROM privatemessages pm
		LEFT JOIN users u ON (u.id=pm.fromid)
		WHERE pm.pmid='{$pmid}' AND pm.uid='".$CURUSER['id']."'
	");
	$pm = $db->fetch_array($query);

	if(!$pm)
	{
		stderr($lang->private['error_invalidpm']);
	}

	if($pm['folder'] == 3)
	{
		header("Location: private.php?action=send&pmid={$pm['pmid']}");
		exit;
	}

	// If we've gotten a PM, attach the group info
	$data_key = array(
		'title' => 'grouptitle',
		'usertitle' => 'groupusertitle',
		'image' => 'groupimage',
		'namestyle' => 'namestyle'
	);

	if(isset($groupscache[$pm['usergroup']]))
	{
		foreach($data_key as $field => $key)
		{
			$pm[$key] = $groupscache[$pm['usergroup']][$field];
		}
	}

	if($pm['receipt'] == 1)
	{
		if($usergroups['candenypmreceipts'] == 1 && $mybb->get_input('denyreceipt', MyBB::INPUT_INT) == 1)
		{
			$receiptadd = 0;
		}
		else
		{
			$receiptadd = 2;
		}
	}

	$action_time = '';
	if($pm['status'] == 0)
	{
		$time = TIMENOW;
		$updatearray = array(
			'status' => 1,
			'readtime' => $time
		);

		if(isset($receiptadd))
		{
			$updatearray['receipt'] = $receiptadd;
		}

		$db->update_query('privatemessages', $updatearray, "pmid='{$pmid}'");

		// Update the unread count - it has now changed.
		update_pm_count($CURUSER['id'], 6);

		// Update PM notice value if this is our last unread PM
		if($CURUSER['unreadpms']-1 <= 0 && $CURUSER['pmnotice'] == 2)
		{
			$updated_user = array(
				"pmnotice" => 1
			);
			$db->update_query("users", $updated_user, "id='{$CURUSER['id']}'");
		}
	}
	// Replied PM?
	else if($pm['status'] == 3 && $pm['statustime'])
	{
		$reply_string = $lang->private['you_replied_on'];
		$reply_date = my_datee('relative', $pm['statustime']);

		if((TIMENOW - $pm['statustime']) < 3600)
		{
			// Relative string for the first hour
			$reply_string = $lang->you_replied;
		}

		$actioned_on = sprintf($reply_string, $reply_date);
		$action_time = '<div class="mb-4 alert bg-success text-white border-0 little">
	<i class="fa-solid fa-check"></i> &nbsp;'.$actioned_on.'
</div>';
	}
	else if($pm['status'] == 4 && $pm['statustime'])
	{
		$forward_string = $lang->private['you_forwarded_on'];
		$forward_date = my_datee('relative', $pm['statustime']);

		if((TIMENOW - $pm['statustime']) < 3600)
		{
			$forward_string = $lang->private['you_forwarded'];
		}

		$actioned_on = sprintf($forward_string, $forward_date);
		$action_time = '<div class="mb-4 alert bg-success text-white border-0 little">
	<i class="fa-solid fa-check"></i> &nbsp;'.$actioned_on.'
</div>';
	}

	$pm['userusername'] = $pm['username'];
	$pm['subject'] = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

	if($pm['fromid'] == 0)
	{
		$pm['username'] = 'Ruff Tracker Engine';
	}

	if(!$pm['username'])
	{
		$pm['username'] = 'na';
	}

	// Fetch the recipients for this message
	$pm['recipients'] = my_unserialize($pm['recipients']);

	if(isset($pm['recipients']['to']) && is_array($pm['recipients']['to']))
	{
		$uid_sql = implode(',', $pm['recipients']['to']);
	}
	else
	{
		$uid_sql = $pm['toid'];
		$pm['recipients']['to'] = array($pm['toid']);
	}

	$show_bcc = 0;

	// If we have any BCC recipients and this user is an Administrator, add them on to the query
	//if(isset($pm['recipients']['bcc']) && count($pm['recipients']['bcc']) > 0 && $mybb->usergroup['cancp'] == 1)
	if(isset($pm['recipients']['bcc']) && count($pm['recipients']['bcc']) > 0)
	{
		$show_bcc = 1;
		$uid_sql .= ','.implode(',', $pm['recipients']['bcc']);
	}

	// Fetch recipient names from the database
	$bcc_recipients = $to_recipients = $bcc_form_val = array();
	$query = $db->simple_select('users', 'id, username', "id IN ({$uid_sql})");
	while($recipient = $db->fetch_array($query))
	{
		// User is a BCC recipient
		$recipient['username'] = htmlspecialchars_uni($recipient['username']);
		if($show_bcc && in_array($recipient['id'], $pm['recipients']['bcc']))
		{
			$bcc_recipients[] = build_profile_link($recipient['username'], $recipient['id']);
			$bcc_form_val[] = $recipient['username'];
		}
		// User is a normal recipient
		else if(in_array($recipient['id'], $pm['recipients']['to']))
		{
			$to_recipients[] = build_profile_link($recipient['username'], $recipient['id']);
		}
	}

	$bcc = '';
	if(count($bcc_recipients) > 0)
	{
		$bcc_recipients = implode(', ', $bcc_recipients);
		$bcc_form_val = implode(',', $bcc_form_val);
		$bcc = '<br />'.$lang->private['bcc'].' '.$bcc_recipients.'';
	}
	else
	{
		$bcc_form_val = '';
	}

	$replyall = false;
	if(count($to_recipients) > 1)
	{
		$replyall = true;
	}

	if(count($to_recipients) > 0)
	{
		$to_recipients = implode($lang->private['comma'], $to_recipients);
	}
	else
	{
		$to_recipients = $lang->private['nobody'];
	}

	$pm['subject_extra'] = '<br />
'.$lang->private['to'].' '.$to_recipients.'
'.$bcc.'';

	add_breadcrumb($pm['subject']);
	$message = build_postbit($pm, 2);

	// Decide whether or not to show quick reply.
	$quickreply = '';
	if($usergroups['cansendpms'] != 0 && $pm['fromid'] != 0 && $pm['folder'] != 3)
	//if($pm['fromid'] != 0 && $pm['folder'] != 3)
	{
		$trow = alt_trow();

		$optionschecked = array('savecopy' => 'checked="checked"');
		
		
		$optionschecked['readreceipt'] = 'checked="checked"';
		

		require_once INC_PATH.'/functions_posting.php';

		$quoted_message = array(
			'message' => htmlspecialchars_uni($parser->parse_badwords($pm['message'])),
			'username' => $pm['username'],
			'quote_is_pm' => true
		);
		$quoted_message = parse_quoted_message($quoted_message);

		
		$maxpmquotedepth = "5";
		
		if($maxpmquotedepth != '0')
		{
			$quoted_message = remove_message_quotes($quoted_message, $maxpmquotedepth);
		}

		$subject = preg_replace("#(FW|RE):( *)#is", '', $pm['subject']);

		if($CURUSER['id'] == $pm['fromid'])
		{
			$to = htmlspecialchars_uni($CURUSER['username']);
		}
		else
		{
			$query = $db->simple_select('users', 'username', "id='{$pm['fromid']}'");
			$to = htmlspecialchars_uni($db->fetch_field($query, 'username'));
		}

		$private_send_tracking = '';
		
		$options_read_receipt = $lang->private['quickreply_read_receipt'];

		$private_send_tracking = '<input type="checkbox" class="form-check-input" name="options[readreceipt]" value="1" tabindex="8" '.$optionschecked['readreceipt'].' /> '.$options_read_receipt.'';
		

		$postoptionschecked = $optionschecked; // Backwards compatability instead of correcting variable used in template

		if(!isset($collapsedthead['quickreply']))
		{
			$collapsedthead['quickreply'] = '';
		}
		if(!isset($collapsedimg['quickreply']))
		{
			$collapsedimg['quickreply'] = '';
		}
		if(!isset($collapsed['quickreply_e']))
		{
			$collapsed['quickreply_e'] = '';
		}

		$expaltext = (in_array("quickreply", $collapse)) ? '+' : '-';
		$quickreply = '<form action="private.php" method="post" name="input">
	<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
	<input type="hidden" name="to" value="'.$to.'" />
	<input type="hidden" name="bcc" value="'.$bcc_form_val.'" />
	<input type="hidden" name="subject" value="Re: '.$subject.'" />
	<input type="hidden" name="action" value="do_send" />
	<input type="hidden" name="pmid" value="'.$pmid.'" />
	<input type="hidden" name="do" value="reply" />
	
			
			<div class="row d-flex g-2 mb-4 mt-5">
    <div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block"><img src="'.$CURUSER['avatar'].'" class="rounded d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block img-fluid" style="width: 100px;"></div>
    <div class="col">
		
        <div class="card">
            <div class="card-body">
				<h6 class="mb-2">'.$CURUSER['username'].'</h6>
		<textarea class="form-control form-control-sm border-0 p-0" style="resize: none; height: 150px;"  name="message" id="message" tabindex="1">'.$quoted_message.'</textarea>
			
	<div id="collapse-reply" class="collapse bg-nav mt-3 p-2">
       
  <input type="checkbox" class="form-check-input" name="options[savecopy]" value="1"'.$optionschecked['savecopy'].' /> '.$lang->private['quickreply_save_copy'].' <br />
'.$private_send_tracking.'

    </div>
				</div>
				<div class="card-footer border-top-0">
					<input type="submit" class="btn btn-primary" value="'.$lang->private['send_message'].'" tabindex="2" accesskey="s" /> <a class="btn btn-thread ms-3 me-3" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-reply" role="button"><i class="fa-solid fa-gear"></i></a> <input type="submit" class="btn btn-thread" name="preview" value="'.$lang->private['preview'].'" tabindex="3" />
				</div>
			</div>
	</div>
	</div>
</form>';
	}

	$plugins->run_hooks("private_read_end");

	
	stdhead('title');
	
	$read = '<html>
<head>
<title>'.$lang->private['viewing_pm'].' '.$pm['subject'].'</title>

</head>
<body>

<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
'.$action_time.'	
<div id="posts">
'.$message.'
</div>	
'.$quickreply.'
</div>
</div>
</div>

</body>
</html>';
	
	
	echo $read;
	
	stdfoot();
}

if($mybb->input['action'] == "tracking")
{
	

	$plugins->run_hooks("private_tracking_start");
	$readmessages = '';
	$unreadmessages = '';

	if(!$f_postsperpage || (int)$f_postsperpage < 1)
	{
		$f_postsperpage = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $f_postsperpage;

	$query = $db->simple_select("privatemessages", "COUNT(pmid) as readpms", "receipt='2' AND folder!='3' AND status!='0' AND fromid='".$CURUSER['id']."'");
	$postcount = $db->fetch_field($query, "readpms");

	$page = $mybb->get_input('read_page', MyBB::INPUT_INT);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('read_page') == "last")
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

	$read_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;read_page={page}");

	$query = $db->sql_query("
		SELECT pm.pmid, pm.subject, pm.toid, pm.readtime, u.username as tousername
		FROM privatemessages pm
		LEFT JOIN users u ON (u.id=pm.toid)
		WHERE pm.receipt='2' AND pm.folder!='3'  AND pm.status!='0' AND pm.fromid='".$CURUSER['id']."'
		ORDER BY pm.readtime DESC
		LIMIT {$start}, {$perpage}
	");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($readmessage['subject']));
		$readmessage['tousername'] = htmlspecialchars_uni($readmessage['tousername']);
		$readmessage['profilelink'] = build_profile_link($readmessage['tousername'], $readmessage['toid']);
		$readdate = my_datee('relative', $readmessage['readtime']);
		$readmessages .= '<div class="card mb-0 border-0">
	<div class="card-body pt-0 inline_row">
	<div class="row g-2 pb-3 border-bottom mb-0">
		<div class="col-auto col-sm-auto col-md-auto col-lg-1 col-xl-1 col-xxl-1 align-self-center">
			<avatarep_uid_['.$readmessage['toid'].']>
				</div>
			<div class="col align-self-center">
				<h6 class="mb-0 text-forum">'.$readmessage['subject'].'</h6>
				<span class="links small">'.$readmessage['profilelink'].'</span>
			</div>
			<div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center">
				'.$icon.' <img src="'.$theme['imgdir'].'/old_pm.png" alt="" />
			</div>
			<div class="col-lg-3 text-muted align-self-center">
				'.$readdate.'
				<span class="float-end">
					<input type="checkbox" class="form-check-input" name="readcheck['.$readmessage['pmid'].']" value="1" />
				</span>
			</div>
		</div>
	</div>
	</div>';
	}

	$stoptrackingread = '';
	if(!empty($readmessages))
	{
		$stoptrackingread = '<div class="text-center"><button type="submit" class="btn btn-primary btn-sm" name="stoptracking" value="'.$lang->private['stop_tracking'].'"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->private['stop_tracking'].'</button> &nbsp; <a href="private.php?action=stopalltracking&amp;my_post_key='.$mybb->post_code.'" class="btn btn-primary btn-sm" style="color: #ffffff!important;"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->private['stop_tracking_all'].'</a></div>';
	}

	if(!$readmessages)
	{
		$readmessages = '<div class="ps-3 pe-3 pb-3">'.$no_readmessages.'</div>';
	}

	$query = $db->simple_select("privatemessages", "COUNT(pmid) as unreadpms", "receipt='1' AND folder!='3' AND status='0' AND fromid='".$CURUSER['id']."'");
	$postcount = $db->fetch_field($query, "unreadpms");

	$page = $mybb->get_input('unread_page', MyBB::INPUT_INT);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('unread_page') == "last")
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

	$unread_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;unread_page={page}");

	

	$sql = "
    SELECT pm.pmid, pm.subject, pm.toid, pm.dateline, u.username AS tousername
    FROM privatemessages pm
    LEFT JOIN users u ON u.id = pm.toid
    WHERE pm.receipt = '1'
      AND pm.folder != '3'
      AND pm.status = '0'
      AND pm.fromid = ?
    ORDER BY pm.dateline DESC
    LIMIT ?, ?
    ";

// Приводим к int
$start = (int)$start;
$perpage = (int)$perpage;
$fromid = (int)$CURUSER['id'];

$query = $db->sql_query_prepared($sql, [$fromid, $start, $perpage]);















	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($unreadmessage['subject']));
		$unreadmessage['tousername'] = htmlspecialchars_uni($unreadmessage['tousername']);
		$unreadmessage['profilelink'] = build_profile_link($unreadmessage['tousername'], $unreadmessage['toid']);
		$senddate = my_datee('relative', $unreadmessage['dateline']);
		$unreadmessages .= '<div class="card mb-0 border-0">
	<div class="card-body pt-0 inline_row">
	<div class="row g-2 pb-3 border-bottom mb-0">
		<div class="col-auto col-sm-auto col-md-auto col-lg-1 col-xl-1 col-xxl-1 align-self-center">
			<avatarep_uid_['.$unreadmessage['toid'].']>
				</div>
			<div class="col align-self-center">
				<h6 class="mb-0 text-forum">'.$unreadmessage['subject'].'</h6>
				<span class="links small">'.$unreadmessage['profilelink'].'</span>
			</div>
			<div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center">
				'.$icon.' <img src="'.$theme['imgdir'].'/new_pm.png" alt="" />
			</div>
			<div class="col-lg-3 text-muted align-self-center">
				'.$senddate.'
				<span class="float-end">
					<input type="checkbox" class="form-check-input" name="unreadcheck['.$unreadmessage['pmid'].']" value="1" />
				</span>
			</div>
		</div>
	</div>
	</div>';
	}

	$stoptrackingunread = '';
	if(!empty($unreadmessages))
	{
		$stoptrackingunread = '<div class="text-center">
	<button type="submit" class="btn btn-primary btn-sm" name="stoptrackingunread" value="'.$lang->private['stop_tracking'].'"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->private['stop_tracking'].'</button> &nbsp; <button type="submit" class="btn btn-primary btn-sm" name="cancel" value="'.$lang->private['delete'].'"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->private['delete'].'</button>
</div>';
	}

	if(!$unreadmessages)
	{
		$no_readmessages = $lang->private['no_unreadmessages'];
		$unreadmessages = '<div class="ps-3 pe-3 pb-3">'.$no_readmessages.'</div>';
	}

	$plugins->run_hooks("private_tracking_end");

	$tracking = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['pm_tracking'].'</title>

</head>
<body>

<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">

	
	<form action="private.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<input type="hidden" name="action" value="do_tracking" />
		
		<div class="card border-0 mb-4">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-2 text-forum">
			<div class="col-1">
				&nbsp;
			</div>
			<div class="col align-self-center">
				'.$lang->private['message_title'].' &mdash; '.$lang->private['sentto'].'
			</div>
			<div class="col-3 align-self-center">
				<span class="d-none d-sm-none d-md-none d-lg-inline-block d-xl-inline-block d-xxl-inline-block">'.$lang->private['dateread'].'</span>
				<span class="float-end"><input type="checkbox" class="form-check-input checkall" name="allbox" /></span>
			</div>
	</div>
		
			</div>
		</div>

'.$readmessages.'

	<div class="card border-0 mb-4">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
'.$stoptrackingread.'
</form>
		</div>
		</div>


	'.$unread_multipage.'
		
<form action="private.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<input type="hidden" name="action" value="do_tracking" />
	
	<div class="card border-0 mb-4">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-2 text-forum">
			<div class="col-1">
				&nbsp;
			</div>
			<div class="col align-self-center">
				'.$lang->private['message_title'].' &mdash; '.$lang->private['sentto'].'
			</div>
			<div class="col-3 align-self-center">
				<span class="d-none d-sm-none d-md-none d-lg-inline-block d-xl-inline-block d-xxl-inline-block">'.$lang->private['datesent'].'</span>
				<span class="float-end"><input type="checkbox" class="form-check-input checkall" name="allbox" /></span>
			</div>
	</div>
		
			</div>
		</div>
	
		
'.$unreadmessages.'

	<div class="card border-0 mb-4">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
'.$stoptrackingunread.'
	</div>
	</div>
</form>
</div></div>
	</div>


</body>
</html>';
	
	stdhead($lang->private['pm_tracking']);
	
	echo $tracking;
	
	stdfoot();
}

if($mybb->input['action'] == "do_tracking" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_tracking_start");

	if(!empty($mybb->input['stoptracking']))
	{
		$mybb->input['readcheck'] = $mybb->get_input('readcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['readcheck']))
		{
			foreach($mybb->input['readcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".(int)$key." AND fromid=".$CURUSER['id']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->private['redirect_pmstrackingstopped']);
	}
	elseif(!empty($mybb->input['stoptrackingunread']))
	{
		$mybb->input['unreadcheck'] = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".(int)$key." AND fromid=".$CURUSER['id']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->private['redirect_pmstrackingstopped']);
	}
	elseif(!empty($mybb->input['cancel']))
	{
		$mybb->input['unreadcheck'] = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $pmid => $val)
			{
				$pmids[$pmid] = (int)$pmid;
			}

			$pmids = implode(",", $pmids);
			$query = $db->simple_select("privatemessages", "uid", "pmid IN ($pmids) AND fromid='".$CURUSER['id']."'");
			while($pm = $db->fetch_array($query))
			{
				$pmuids[$pm['uid']] = $pm['uid'];
			}

			$db->delete_query("privatemessages", "pmid IN ($pmids) AND receipt='1' AND status='0' AND fromid='".$CURUSER['id']."'");
			foreach($pmuids as $uid)
			{
				// Message is canceled, update PM count for this user
				update_pm_count($uid);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->private['redirect_pmstrackingcanceled']);
	}
}

if($mybb->input['action'] == "stopalltracking")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_stopalltracking_start");

	$sql_array = array(
		"receipt" => 0
	);
	$db->update_query("privatemessages", $sql_array, "receipt='2' AND folder!='3' AND status!='0' AND fromid=".$CURUSER['id']);

	$plugins->run_hooks("private_stopalltracking_end");
	redirect("private.php?action=tracking", $lang->private['redirect_allpmstrackingstopped']);
}

if($mybb->input['action'] == "folders")
{
	$plugins->run_hooks("private_folders_start");

	$folderlist = '';
	$foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $foldername);

		if((int)$folderinfo[0] < 5)
		{
			$foldername2 = get_pm_folder_name($fid);
			$folderlist .= '<div class="mb-3 pb-3 border-bottom">
		<input type="text" class="form-control form-control-sm border" name="folder['.$fid.']" value="'.$foldername.'" maxlength="30" />
	<span class="small text-desc">('.$foldername2.' - '.$lang->private['cannot_be_removed'].')</span>
</div>';
			unset($name);
		}
		else
		{
			$folderlist .= '<div class="mb-3 pb-3 border-bottom">
<input type="text" class="form-control form-control-sm border mb-3" name="folder['.$fid.']" value="'.$foldername.'" maxlength="30" />
</div>';
		}
	}

	$newfolders = '';
	for($i = 1; $i <= 5; ++$i)
	{
		$fid = "new$i";
		$foldername = '';
		$newfolders .= '<div class="mb-3 pb-3 border-bottom">
<input type="text" class="form-control form-control-sm border mb-3" name="folder['.$fid.']" value="'.$foldername.'" maxlength="30" />
</div>';
	}

	$plugins->run_hooks("private_folders_end");

	$folders = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['pm_folders'].'</title>

</head>
<body>

<form action="private.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
				
<div class="card">
<div class="card-body">
	
	<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['existing_folders'].'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['existing_folders'].'
</div>
<div class="col">
	<div class="mb-3 border-bottom pb-3">
		'.$lang->private['edit_folder_note'].'
	</div>
'.$folderlist.'
</div>
</div>
	
		<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->private['new_folders'].'</div>
<div class="row g-3 m-auto pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->private['new_folders'].'
</div>
<div class="col">
	<div class="mb-3 border-bottom pb-3">
		'.$lang->private['add_folders_note'].'
	</div>
'.$newfolders.'
</div>
</div>
	
	</div>
	<div class="card-footer text-center">
		<input type="hidden" name="action" value="do_folders" />
<button type="submit" class="btn btn-primary" name="submit" value="'.$lang->private['update_folders'].'"><i class="fa-solid fa-folder-open"></i> &nbsp;'.$lang->private['update_folders'].'</button>
	</div>
	</div>
	</div>
	</div>
	</div>

</form>


</body>
</html>';
	
	stdhead('title');
	
	echo $folders;
	
	stdfoot();
}

if($mybb->input['action'] == "do_folders" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_folders_start");

	$highestid = 2;
	$folders = '';
	$donefolders = array();
	$mybb->input['folder'] = $mybb->get_input('folder', MyBB::INPUT_ARRAY);
	foreach($mybb->input['folder'] as $key => $val)
	{
		if(empty($donefolders[$val]) )// Probably was a check for duplicate folder names, but doesn't seem to be used now
		{
			if(my_substr($key, 0, 3) == "new") // Create a new folder
			{
				++$highestid;
				$fid = (int)$highestid;
			}
			else // Editing an existing folder
			{
				if($key > $highestid)
				{
					$highestid = $key;
				}

				$fid = (int)$key;
				// Use default language strings if empty or value is language string
				if($val == get_pm_folder_name($fid) || trim($val) == '')
				{
					$val = '';
				}
			}

			if($val != '' && trim($val) == '' && !(is_numeric($key) && $key <= 4))
			{
				// If the name only contains whitespace and it's not a default folder, print an error
				error($lang->error_emptypmfoldername);
			}

			if($val != '' || (is_numeric($key) && $key <= 4))
			{
				// If there is a name or if this is a default folder, save it
				$foldername = $db->escape_string(htmlspecialchars_uni($val));

				if(my_strpos($foldername, "$%%$") === false)
				{
					if($folders != '')
					{
						$folders .= "$%%$";
					}
					$folders .= "$fid**$foldername";
				}
				else
				{
					error($lang->error_invalidpmfoldername);
				}
			}
			else
			{
				// Delete PMs from the folder
				$db->delete_query("privatemessages", "folder='$fid' AND uid='".$CURUSER['id']."'");
			}
		}
	}

	$sql_array = array(
		"pmfolders" => $folders
	);
	$db->update_query("users", $sql_array, "id='".$CURUSER['id']."'");

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_folders_end");

	redirect("private.php", $lang->private['redirect_pmfoldersupdated']);
}

if($mybb->input['action'] == "empty")
{
	if($CURUSER['totalpms'] == 0)
	{
		error($lang->private['error_nopms']);
	}

	$plugins->run_hooks("private_empty_start");

	$foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
	$folderlist = '';
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$unread = ''; 
		$fid = $folderinfo[0];
		if($folderinfo[0] == "1")
		{
			$fid = "1";
			$unread = " AND status='0'";
		}
		if($folderinfo[0] == "0")
		{
			$fid = "1";
		}
		$foldername = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$query = $db->simple_select("privatemessages", "COUNT(*) AS pmsinfolder", " folder='$fid'$unread AND uid='".$CURUSER['id']."'");
		$thing = $db->fetch_array($query);
		$foldercount = ts_nf($thing['pmsinfolder']);
		$folderlist .= '<div class="py-2">
<input type="checkbox" class="form-check-input" name="empty['.$fid.']" value="1" /> &nbsp;&nbsp;'.$foldername.' &nbsp;<span class="fw-bold">'.$foldercount.'</span>
</div>';
	}

	$plugins->run_hooks("private_empty_end");

	$folders = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['empty_folders'].'</title>

</head>
<body>

<form action="private.php" method="post">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
<div class="col">
				
<div class="card">
<div class="card-body">
	
	<div class="border-bottom pb-3 mb-3">'.$lang->private['empty_note'].'</div>

	<strong>'.$lang->private['empty_q'].'</strong>
	<div class="py-2">
'.$folderlist.'
	</div>

	<div class="border-top py-3"><input type="checkbox" class="form-check-input" name="keepunread" value="1" checked="checked" /> '.$lang->private['keep_unread'].'</div>

	</div>
	<div class="card-footer text-center">
<input type="hidden" name="action" value="do_empty" />
<button type="submit" class="btn btn-primary" name="submit" value="'.$lang->private['delete'].'"><i class="fa-solid fa-trash"></i> &nbsp;'.$lang->private['delete'].'</button>
	</div></div>
	</div>

</form>
	</div></div>


</body>
</html>';
	
	stdhead($lang->private['empty_folders']);
	
	echo $folders;
	
	stdfoot();
}

if($mybb->input['action'] == "do_empty" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_empty_start");

	$emptyq = '';
	$mybb->input['empty'] = $mybb->get_input('empty', MyBB::INPUT_ARRAY);
	$keepunreadq = '';
	if($mybb->get_input('keepunread', MyBB::INPUT_INT) == 1)
	{
		$keepunreadq = " AND status!='0'";
	}
	if(!empty($mybb->input['empty']))
	{
		foreach($mybb->input['empty'] as $key => $val)
		{
			if($val == 1)
			{
				$key = (int)$key;
				if($emptyq)
				{
					$emptyq .= " OR ";
				}
				$emptyq .= "folder='$key'";
			}
		}

		if($emptyq != '')
		{
			$db->delete_query("privatemessages", "($emptyq) AND uid='".$CURUSER['id']."'{$keepunreadq}");
		}
	}

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_empty_end");
	redirect("private.php", $lang->private['redirect_pmfoldersemptied']);
}

if($mybb->input['action'] == "do_stuff" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_stuff");

	if(!empty($mybb->input['hop']))
	{
		header("Location: private.php?fid=".$mybb->get_input('jumpto'));
	}
	elseif(!empty($mybb->input['moveto']))
	{
		$pms = array_map('intval', array_keys($mybb->get_input('check', MyBB::INPUT_ARRAY)));
		if(!empty($pms))
		{
			if(!$mybb->input['fid'])
			{
				$mybb->input['fid'] = 1;
			}

			if(array_key_exists($mybb->input['fid'], $foldernames))
			{
				$db->update_query("privatemessages", array("folder" => $mybb->input['fid']), "pmid IN (".implode(",", $pms).") AND uid='".$CURUSER['id']."'");
				update_pm_count();
			}
			else
			{
				error($lang->error_invalidmovefid);
			}
		}

		if(!empty($mybb->input['fromfid']))
		{
			redirect("private.php?fid=".$mybb->get_input('fromfid', MyBB::INPUT_INT), $lang->private['redirect_pmsmoved']);
		}
		else
		{
			redirect("private.php", $lang->private['redirect_pmsmoved']);
		}
	}
	elseif(!empty($mybb->input['delete']))
	{
		$mybb->input['check'] = $mybb->get_input('check', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['check']))
		{
			$pmssql = '';
			foreach($mybb->input['check'] as $key => $val)
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'".(int)$key."'";
			}

			$deletepms = array();
			$query = $db->simple_select("privatemessages", "pmid, folder", "pmid IN ($pmssql) AND uid='".$CURUSER['id']."' AND folder='4'", array('order_by' => 'pmid'));
			while($delpm = $db->fetch_array($query))
			{
				$deletepms[$delpm['pmid']] = 1;
			}

			foreach($mybb->input['check'] as $key => $val)
			{
				$key = (int)$key;
				if(!empty($deletepms[$key]))
				{
					$db->delete_query("privatemessages", "pmid='$key' AND uid='".$CURUSER['id']."'");
				}
				else
				{
					$sql_array = array(
						"folder" => 4,
						"deletetime" => TIMENOW
					);
					$db->update_query("privatemessages", $sql_array, "pmid='".$key."' AND uid='".$CURUSER['id']."'");
				}
			}
		}
		// Update PM count
		update_pm_count();

		if(!empty($mybb->input['fromfid']))
		{
			redirect("private.php?fid=".$mybb->get_input('fromfid', MyBB::INPUT_INT), $lang->private['redirect_pmsdeleted']);
		}
		else
		{
			redirect("private.php", $lang->private['redirect_pmsdeleted']);
		}
	}
}

if($mybb->input['action'] == "delete")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_delete_start");

	$query = $db->simple_select("privatemessages", "*", "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND uid='".$CURUSER['id']."' AND folder='4'", array('order_by' => 'pmid'));
	if($db->num_rows($query) == 1)
	{
		$db->delete_query("privatemessages", "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."'");
	}
	else
	{
		$sql_array = array(
			"folder" => 4,
			"deletetime" => TIMENOW
		);
		$db->update_query("privatemessages", $sql_array, "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND uid='".$CURUSER['id']."'");
	}

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_delete_end");
	redirect("private.php", $lang->private['redirect_pmsdeleted']);
}














// ========================
// СТРАНИЦА ВЫБОРА ПАРАМЕТРОВ
// ========================
if ($mybb->input['action'] == "export")
{
    if ($CURUSER['totalpms'] == 0) {
        error($lang->error_nopms);
    }

    $plugins->run_hooks("private_export_start");

    // Строим список папок
    $foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
    $folderlist_folder = '';
    foreach ($foldersexploded as $folders)
    {
        $folderinfo = explode("**", $folders, 2);
        $folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
        $folder_id   = (int)$folderinfo[0];
        $folder_name = htmlspecialchars_uni($folderinfo[1]);
        $folderlist_folder .= '<option value="'.$folder_id.'">'.$folder_name.'</option>';
    }

    $folderlist = '
<select name="exportfolders[]" multiple="multiple" class="form-select form-select-sm border">
  <option value="all" selected="selected">'.$lang->private['all_folders'].'</option>
  '.$folderlist_folder.'
</select>';

    $plugins->run_hooks("private_export_end");

    stdhead($lang->private['archive_messages']);



echo '
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.htmlspecialchars_uni($SITENAME).' - '.htmlspecialchars_uni($lang->private['archive_messages']).'</title>
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/pm-export.css"> 
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-lg-3">
            <div class="user-cp-nav">
                '.$usercpnav.'
            </div>
        </div>
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-envelope-open-text"></i>
                        '.htmlspecialchars_uni($lang->private['archive_messages']).'
                    </h2>
                </div>
                
                <form action="private.php" method="post" id="exportForm">
                    <input type="hidden" name="my_post_key" value="'.htmlspecialchars_uni($mybb->post_code).'" />
                    
                    <div class="card-body">
                        <div class="alert">
                            <i class="fas fa-info-circle"></i> 
                            '.htmlspecialchars_uni($lang->private['archive_note']).'
                        </div>
                        
                        <!-- Select Folders -->
                        <div class="form-section">
                            <div class="form-label">
                                <i class="fas fa-folder"></i>
                                '.htmlspecialchars_uni($lang->folders).'
                            </div>
                            '.$folderlist.'
                            <small class="badge" style="margin-top: 0.5rem; display: inline-block;">
                                <i class="fas fa-info-circle"></i> Hold Ctrl (Cmd) key to select multiple folders
                            </small>
                        </div>
                        
                        <!-- Date Filter -->
                        <div class="form-section">
                            <div class="form-label">
                                <i class="fas fa-calendar-alt"></i>
                                '.htmlspecialchars_uni($lang->private['date_limit']).'
                            </div>
                            <div class="date-group">
                                <div>
                                    <select name="dayway" class="form-select">
                                        <option value="older">📅 '.htmlspecialchars_uni($lang->private['date_limit_older']).'</option>
                                        <option value="newer">📅 '.htmlspecialchars_uni($lang->private['date_limit_newer']).'</option>
                                        <option value="disregard" selected>♾️ '.htmlspecialchars_uni($lang->private['date_limit_disregard']).'</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="number" class="form-control" name="daycut" value="30" size="3" maxlength="4" placeholder="Number of days" min="1" max="3650" />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Unread Only -->
                        <div class="form-section">
                            <label class="checkbox-group">
                                <input type="checkbox" class="form-check-input" name="exportunread" value="1" />
                                <div>
                                    <strong><i class="fas fa-envelope"></i> Unread messages only</strong>
                                    <small>Export only messages that you havent read yet</small>
                                </div>
                            </label>
                        </div>
                        
                        <!-- Delete After Export -->
                        <div class="form-section">
                            <label class="checkbox-group">
                                <input type="checkbox" class="form-check-input" name="deletepms" value="1" />
                                <div>
                                    <strong><i class="fas fa-trash-alt"></i> '.htmlspecialchars_uni($lang->private['delete_archived']).'</strong>
                                    <small>⚠️ '.htmlspecialchars_uni($lang->private['delete_archived_note']).'</small>
                                </div>
                            </label>
                        </div>
                        
                        <!-- Export Format -->
                        <div class="form-section">
                            <div class="form-label">
                                <i class="fas fa-file-alt"></i>
                                '.htmlspecialchars_uni($lang->private['export_format']).'
                            </div>
                            <div class="format-options">
                                <label class="format-card" data-format="html">
                                    <input type="radio" name="exporttype" value="html" checked="checked" />
                                    <div class="format-icon">🌐</div>
                                    <div><strong>HTML</strong></div>
                                    <small>Web page with formatting preserved</small>
                                </label>
                                <label class="format-card" data-format="txt">
                                    <input type="radio" name="exporttype" value="txt" />
                                    <div class="format-icon">📄</div>
                                    <div><strong>TXT</strong></div>
                                    <small>Plain text file</small>
                                </label>
                                <label class="format-card" data-format="csv">
                                    <input type="radio" name="exporttype" value="csv" />
                                    <div class="format-icon">📊</div>
                                    <div><strong>CSV</strong></div>
                                    <small>For Excel, Google Sheets and other spreadsheets</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <input type="hidden" name="action" value="do_export" />
                        <button type="submit" class="btn btn-primary" name="submit">
                            <i class="fas fa-download"></i>
                            Export Messages
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="' . $BASEURL . '/scripts/pm-export.js"></script>

</body>
</html>';

stdfoot();


            




	
}


// ========================
// ОБРАБОТКА ЭКСПОРТА
// ========================
if ($mybb->input['action'] == "do_export" && $mybb->request_method == "post")
{
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks("private_do_export_start");

    $exporttype = $mybb->get_input('exporttype');
    if (!in_array($exporttype, ['html', 'txt', 'csv'])) {
        $exporttype = 'html';
    }

    // Папки пользователя
    $foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
    $folderMap = []; // folder_id => folder_name
    foreach ($foldersexploded as $folders)
    {
        $folderinfo = explode("**", $folders, 2);
        $folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
        $folderMap[(int)$folderinfo[0]] = $folderinfo[1];
    }

    // ---- Формируем WHERE ----
    $where_parts = [];
    $params       = [(int)$CURUSER['id']];

    if ($mybb->get_input('pmid', MyBB::INPUT_INT)) {
        $where_parts[] = "pm.pmid = ?";
        $params[] = $mybb->get_input('pmid', MyBB::INPUT_INT);
    } else {
        $daycut = $mybb->get_input('daycut', MyBB::INPUT_INT);
        $dayway = $mybb->get_input('dayway');
        if ($daycut && $dayway !== 'disregard') {
            $datecut = TIMENOW - ($daycut * 86400);
            if ($dayway === 'older') {
                $where_parts[] = "pm.dateline <= ?";
            } else {
                $where_parts[] = "pm.dateline >= ?";
            }
            $params[] = $datecut;
        }

        // Папки
        $exportfolders = $mybb->get_input('exportfolders', MyBB::INPUT_ARRAY);
        if (empty($exportfolders)) {
            error($lang->error_pmnoarchivefolders);
        }

        $hasAll = false;
        $folderIds = [];
        foreach ($exportfolders as $val) {
            if ($val === 'all') { $hasAll = true; break; }
            $folderIds[] = (int)$val;
        }

        if (!$hasAll && !empty($folderIds)) {
            $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
            $where_parts[] = "pm.folder IN ($placeholders)";
            foreach ($folderIds as $fid) {
                $params[] = $fid;
            }
        }

        // Только прочитанные
        if ($mybb->get_input('exportunread', MyBB::INPUT_INT) != 1) {
            $where_parts[] = "pm.status != '0'";
        }
    }

    $where_parts[] = "pm.uid = ?"; // уже первый $params[0], но дублируем через AND
    // Примечание: uid уже передаётся как первый параметр до where_parts,
    // поэтому собираем итоговый WHERE без него и добавляем uid отдельно:

    // Пересобираем: uid идёт последним параметром
    array_shift($params); // убираем uid из начала, добавим в конец
    $params[] = (int)$CURUSER['id'];

    $where_sql = empty($where_parts) ? "1=1" : implode(" AND ", $where_parts);

    $sql = "
        SELECT pm.*, fu.username AS fromusername, tu.username AS tousername
        FROM privatemessages pm
        LEFT JOIN users fu ON fu.id = pm.fromid
        LEFT JOIN users tu ON tu.id = pm.toid
        WHERE $where_sql
        ORDER BY pm.folder ASC, pm.dateline DESC
    ";

    $query = $db->sql_query_prepared($sql, $params);

    if (!$db->num_rows($query)) {
        error($lang->private['error_nopmsarchive']);
    }

    // ---- Рендеринг сообщений ----
    $exdate = my_datee($dateformat, TIMENOW, 0, 0);
    $extime = my_datee($timeformat, TIMENOW, 0, 0);
    $exported_date       = sprintf($lang->private['exported_date'], $exdate, $extime);
    $private_messages_for = sprintf($lang->private['private_messages_for'], htmlspecialchars_uni($CURUSER['username']));

    // Для HTML — получаем глобальный CSS
    $css = '';

    $allMessages   = [];  // сгруппированные по папкам
    $pmIdsToDelete = [];

    while ($message = $db->fetch_array($query))
    {
        $pmIdsToDelete[] = (int)$message['pmid'];
        $folder_id = (int)$message['folder'];

        // Кому/от кого
        if ($folder_id == 2 || $folder_id == 3) {
            // Исходящие / Черновики
            $tofrom    = $lang->to;
            $tofromuid = (int)$message['toid'];
            if ($tofromuid) {
                $tofromusername = ($exporttype === 'txt')
                    ? $message['tousername']
                    : build_profile_link($message['tousername'], $tofromuid);
            } else {
                $tofromusername = $lang->not_sent;
            }
        } else {
            // Входящие
            $tofrom    = $lang->from;
            $tofromuid = (int)$message['fromid'];
            $tofromusername = ($exporttype === 'txt')
                ? $message['fromusername']
                : build_profile_link($message['fromusername'], $tofromuid);

            if ($tofromuid === 0) {
                $tofromusername = 'Ruff Tracker Engine';
            }
        }

        if ($tofromuid === 0) {
            $message['fromusername'] = 'Ruff Tracker Engine';
        }
        if (!$message['toid'] && $folder_id === 3) {
            $message['tousername'] = $lang->not_sent;
        }

        // Дата отправки
        if ($folder_id !== 3) {
            $senddate  = my_datee($dateformat, $message['dateline'], "", false);
            $sendtime  = my_datee($timeformat, $message['dateline'], "", false);
            $senddate .= " ".$lang->at." ".$sendtime;
        } else {
            $senddate = $lang->not_sent;
        }

        $subject = $parser->parse_badwords($message['subject']);

        // Форматируем тело сообщения
        if ($exporttype === 'html') {
            $parser_options = [
                "allow_html"      => 1,
                "allow_mycode"    => 1,
                "allow_smilies"   => 0,
                "allow_imgcode"   => 1,
                "allow_videocode" => 1,
                "me_username"     => $CURUSER['username'],
                "filter_badwords" => 1,
            ];
            $body    = $parser->parse_message($message['message'], $parser_options);
            $subject = htmlspecialchars_uni($subject);
        } else {
            $body = str_replace(["\r\n", "\n"], ["\n", "\r\n"], $message['message']);
        }

        if ($exporttype === 'csv') {
            $body                    = my_escape_csv($body);
            $subject                 = my_escape_csv($subject);
            $message['tousername']   = my_escape_csv($message['tousername']);
            $message['fromusername'] = my_escape_csv($message['fromusername']);
            $tofromusername          = my_escape_csv($tofromusername);
        }

        $allMessages[$folder_id][] = [
            'subject'        => $subject,
            'body'           => $body,
            'senddate'       => $senddate,
            'tofrom'         => $tofrom,
            'tofromusername' => $tofromusername,
            'fromusername'   => $message['fromusername'],
            'tousername'     => $message['tousername'],
            'folder_id'      => $folder_id,
        ];
    }

    // ---- Сборка итогового файла ----
    $output = '';

    if ($exporttype === 'html') {
        $output = pm_export_build_html($allMessages, $folderMap, $private_messages_for, $exported_date, $css);
    } elseif ($exporttype === 'txt') {
        $output = pm_export_build_txt($allMessages, $folderMap, $private_messages_for, $exported_date);
    } elseif ($exporttype === 'csv') {
        $output = pm_export_build_csv($allMessages, $folderMap);
    }

    $plugins->run_hooks("private_do_export_end");

    // Удаление если нужно
    if ($mybb->get_input('deletepms', MyBB::INPUT_INT) == 1 && !empty($pmIdsToDelete)) {
        $idList = implode(',', $pmIdsToDelete);
        $db->delete_query("privatemessages", "pmid IN ($idList)");
        update_pm_count();
    }

    // Отдаём файл
    switch ($exporttype) {
        case 'html':
            $filename    = 'pm-archive.html';
            $contenttype = 'text/html; charset=utf-8';
            break;
        case 'csv':
            $filename    = 'pm-archive.csv';
            $contenttype = 'application/octet-stream';
            break;
        default:
            $filename    = 'pm-archive.txt';
            $contenttype = 'text/plain; charset=utf-8';
    }

    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Content-Type: $contenttype");

    if ($exporttype !== 'html') {
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
    }
    echo $output;
    exit;
}


// ========================
// ФУНКЦИИ РЕНДЕРИНГА
// ========================

/**
 * HTML-экспорт
 */
function pm_export_build_html(array $allMessages, array $folderMap, string $title, string $exported_date, string $css): string
{
    $body = '';
    foreach ($allMessages as $folder_id => $messages)
    {
        $foldername = htmlspecialchars_uni($folderMap[$folder_id] ?? 'Folder #'.$folder_id);
        $body .= '<h2 class="pm-folder">'.$foldername.'</h2>'."\n";

        foreach ($messages as $m)
        {
            $body .= '
<div class="pm-message">
  <div class="pm-meta">
    <span class="pm-subject"><strong>'.htmlspecialchars_uni($m['subject']).'</strong></span>
    &nbsp;|&nbsp;
    <span class="pm-tofrom">'.htmlspecialchars_uni($m['tofrom']).': '.$m['tofromusername'].'</span>
    &nbsp;|&nbsp;
    <span class="pm-date">'.htmlspecialchars_uni($m['senddate']).'</span>
  </div>
  <div class="pm-body">'.$m['body'].'</div>
</div>
';
        }
    }

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>'.htmlspecialchars_uni($title).'</title>
  <style>
    '.$css.'
    body { font-family: sans-serif; padding: 20px; }
    .pm-folder { border-bottom: 2px solid #aaa; padding-bottom: 4px; margin-top: 30px; }
    .pm-message { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 14px; padding: 10px 14px; }
    .pm-meta { font-size: 0.85em; color: #555; margin-bottom: 8px; }
    .pm-body { font-size: 0.95em; }
  </style>
</head>
<body>
  <h1>'.htmlspecialchars_uni($title).'</h1>
  <p>'.htmlspecialchars_uni($exported_date).'</p>
  '.$body.'
</body>
</html>';
}

/**
 * TXT-экспорт
 */
function pm_export_build_txt(array $allMessages, array $folderMap, string $title, string $exported_date): string
{
    $lines = [];
    $lines[] = $title;
    $lines[] = $exported_date;
    $lines[] = str_repeat('=', 60);

    foreach ($allMessages as $folder_id => $messages)
    {
        $foldername = $folderMap[$folder_id] ?? 'Folder #'.$folder_id;
        $lines[] = '';
        $lines[] = '*** '.$foldername.' ***';
        $lines[] = str_repeat('-', 60);

        foreach ($messages as $m)
        {
            $lines[] = 'Subject : '.$m['subject'];
            $lines[] = $m['tofrom'].'     : '.$m['tofromusername'];
            $lines[] = 'Date    : '.$m['senddate'];
            $lines[] = '';
            $lines[] = $m['body'];
            $lines[] = str_repeat('-', 60);
        }
    }

    return implode("\r\n", $lines);
}

/**
 * CSV-экспорт
 */
function pm_export_build_csv(array $allMessages, array $folderMap): string
{
    $rows = [];
    // Заголовок
    $rows[] = '"Folder","Subject","From","To","Date","Message"';

    foreach ($allMessages as $folder_id => $messages)
    {
        $foldername = my_escape_csv($folderMap[$folder_id] ?? 'Folder #'.$folder_id);
        foreach ($messages as $m)
        {
            $rows[] = implode(',', [
                '"'.$foldername.'"',
                '"'.$m['subject'].'"',
                '"'.$m['fromusername'].'"',
                '"'.$m['tousername'].'"',
                '"'.$m['senddate'].'"',
                '"'.$m['body'].'"',
            ]);
        }
    }

    return implode("\r\n", $rows);
}










if(!$mybb->input['action'])
{
	$plugins->run_hooks("private_inbox");

	if(!$mybb->input['fid'] || !array_key_exists($mybb->input['fid'], $foldernames))
	{
		$mybb->input['fid'] = 0;
	}

	$fid = (int)$mybb->input['fid'];
	$folder = !$fid ? 1 : $fid;
	$foldername = $foldernames[$fid];

	if($folder == 2 || $folder == 3)
	{ // Sent Items Folder
		$sender = $lang->private['sentto'];
	}
	else
	{
		$sender = $lang->private['sender'];
	}

	$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));
	$ordersel = array('asc' => '', 'desc');
	switch(my_strtolower($mybb->input['order']))
	{
		case "asc":
			$sortordernow = "asc";
			$ordersel['asc'] = "selected=\"selected\"";
			$oppsort = $lang->private['desc'];
			$oppsortnext = "desc";
			break;
		default:
			$sortordernow = "desc";
			$ordersel['desc'] = "selected=\"selected\"";
			$oppsort = $lang->private['asc'];
			$oppsortnext = "asc";
			break;
	}

	// Sort by which field?
	$sortby = htmlspecialchars_uni($mybb->get_input('sortby'));
	switch($mybb->get_input('sortby'))
	{
		case "subject":
			$sortfield = "subject";
			break;
		case "username":
			$sortfield = "username";
			break;
		default:
			$sortby = "dateline";
			$sortfield = "dateline";
			$mybb->input['sortby'] = "dateline";
			break;
	}
	$orderarrow = $sortsel = array('subject' => '', 'username' => '', 'dateline' => '');
	$sortsel[$sortby] = "selected=\"selected\"";

	$orderarrow['$sortby'] = '<span class="smalltext">[<a href="private.php?fid='.$fid.'&amp;sortby='.$sortby.'&amp;order='.$oppsortnext.'">'.$oppsort.'</a>]</span>';

	// Do Multi Pages
	$selective = "";
	if($fid == 1)
	{
		$selective = " AND status='0'";
	}

	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$CURUSER['id']."' AND folder='$folder'$selective");
	$pmscount = $db->fetch_field($query, "total");

	if(!$f_threadsperpage || (int)$f_threadsperpage < 1)
	{
		$f_threadsperpage = 20;
	}

	$perpage = $f_threadsperpage;
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	if($page > 0)
	{
		$start = ($page-1) *$perpage;
		$pages = ceil($pmscount / $perpage);
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

	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}

	if($mybb->input['order'] || ($sortby && $sortby != "dateline"))
	{
		$page_url = "private.php?fid={$fid}&sortby={$sortby}&order={$sortordernow}";
	}
	else
	{
		$page_url = "private.php?fid={$fid}";
	}

	$multipage = multipage($pmscount, $perpage, $page, $page_url);
	$selective = $messagelist = '';

	

	// Cache users in multiple recipients for sent & drafts folder
	if($folder == 2 || $folder == 3)
	{
		if($sortfield == "username")
		{
			$u = "u.";
		}
		else
		{
			$u = "pm.";
		}

		// Get all recipients into an array
		$cached_users = $get_users = array();
		$users_query = $db->sql_query("
			SELECT pm.recipients
			FROM privatemessages pm
			LEFT JOIN users u ON (u.id=pm.toid)
			WHERE pm.folder='{$folder}' AND pm.uid='{$CURUSER['id']}'
			ORDER BY {$u}{$sortfield} {$sortordernow}
			LIMIT {$start}, {$perpage}
		");
		while($row = $db->fetch_array($users_query))
		{
			$recipients = my_unserialize($row['recipients']);
			if(isset($recipients['to']) && is_array($recipients['to']) && count($recipients['to']))
			{
				$get_users = array_merge($get_users, $recipients['to']);
			}

			if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
			{
				$get_users = array_merge($get_users, $recipients['bcc']);
			}
		}

		$get_users = implode(',', array_unique($get_users));

		// Grab info
		if($get_users)
		{
			$users_query = $db->simple_select("users", "id, username, usergroup, displaygroup", "id IN ({$get_users})");
			while($user = $db->fetch_array($users_query))
			{
				$cached_users[$user['id']] = $user;
			}
		}
	}

	if($folder == 2 || $folder == 3)
	{
		if($sortfield == "username")
		{
			$pm = "tu.";
		}
		else
		{
			$pm = "pm.";
		}
	}
	else
	{
		if($fid == 1)
		{
			$selective = " AND pm.status='0'";
		}

		if($sortfield == "username")
		{
			$pm = "fu.";
		}
		else
		{
			$pm = "pm.";
		}
	}

	$query = $db->sql_query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername,fu.avatar, fu.avatardimensions
		FROM privatemessages pm
		LEFT JOIN users fu ON (fu.id=pm.fromid)
		LEFT JOIN users tu ON (tu.id=pm.toid)
		WHERE pm.folder='$folder' AND pm.uid='".$CURUSER['id']."'{$selective}
		ORDER BY {$pm}{$sortfield} {$sortordernow}
		LIMIT $start, $perpage
	");

	if($db->num_rows($query) > 0)
	{
		$bgcolor = alt_trow(true);
		while($message = $db->fetch_array($query))
		{
			$msgalt = $msgstatus = '';

			
			
			
			
// Determine Folder Icon

if($message['status'] == 0)
{
    $msgstatus = 'new_pm';
    $msgalt = $lang->private['new_pm'];
    $fa_icon_html = '<i class="fa-solid fa-envelope fa-fw"></i>';
    $badge_class = 'status-new';
    $popover_title = 'New Message';
    $popover_content = 'This message has not been read yet';
}
else if($message['status'] == 1)
{
    $msgstatus = 'old_pm';
    $msgalt = $lang->private['old_pm'];
    $fa_icon_html = '<i class="fa-regular fa-envelope-open fa-fw"></i>';
    $badge_class = 'status-read';
    $popover_title = 'Read Message';
    $popover_content = 'This message has been read';
}
else if($message['status'] == 3)
{
    $msgstatus = 're_pm';
    $msgalt = $lang->private['reply_pm'];
    $fa_icon_html = '<i class="fa-solid fa-reply fa-fw"></i>';
    $badge_class = 'status-reply';
    $popover_title = 'Reply to Message';
    $popover_content = 'This is a reply to a previous message';
}
else if($message['status'] == 4)
{
    $msgstatus = 'fw_pm';
    $msgalt = $lang->private['fwd_pm'];
    $fa_icon_html = '<i class="fa-solid fa-share fa-fw"></i>';
    $badge_class = 'status-forward';
    $popover_title = 'Forwarded Message';
    $popover_content = 'This message has been forwarded';
}





			
			
			
			
			
			
			
			
			
			$avatar = $message['avatar'];
            $dimensions = $message['avatardimensions'];
			

			$tofromuid = 0;
			if($folder == 2 || $folder == 3)
			{ // Sent Items or Drafts Folder Check
				$recipients = my_unserialize($message['recipients']);
				$to_users = $bcc_users = '';
				if(isset($recipients['to']) && count($recipients['to']) > 1 || (isset($recipients['to']) && count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
				{
					foreach($recipients['to'] as $uid)
					{
						if(!isset($cached_users[$uid]))
						{
							continue;
						}
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$user['username'] = htmlspecialchars_uni($user['username']);
						$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						if(!$user['username'])
						{
							$username = $lang->na;
						}
						$to_users .= '<div class="popup_item_container"><a href="'.$profilelink.'" class="popup_item">'.$username.'</a></div>';
					}
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
					{
						$bcc_users = '<div class="tcat"><strong>'.$lang->private['bcc'].'</strong></div>';
						foreach($recipients['bcc'] as $uid)
						{
							if(!isset($cached_users[$uid]))
							{
								continue;
							}
							$profilelink = get_profile_link($uid);
							$user = $cached_users[$uid];
							$user['username'] = htmlspecialchars_uni($user['username']);
							$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							if(!$user['username'])
							{
								$username = $lang->na;
							}
							$bcc_users .= '<div class="popup_item_container"><a href="'.$profilelink.'" class="popup_item">'.$username.'</a></div>';
						}
					}

					$tofromusername = '<a href="private.php?action=read&amp;pmid='.$message['pmid'].'" id="private_message_'.$message['pmid'].'">'.$lang->private['multiple_recipients'].'</a>
		<div id="private_message_'.$message['pmid'].'_popup" class="popup_menu" style="display: none;"><div class="tcat"><strong>'.$lang->private['to'].'</strong></div>'.$to_users.''.$bcc_users.'</div>
<script type="text/javascript">
<!--
	$("#private_message_'.$message['pmid'].'").popupMenu();
// -->
</script>';
				}
				else if($message['toid'])
				{
					$tofromusername = htmlspecialchars_uni($message['tousername']);
					$tofromuid = $message['toid'];
				}
				else
				{
					$tofromusername = 'not_sent';
				}
			}
			else
			{
				$tofromusername = htmlspecialchars_uni($message['fromusername']);
				$tofromuid = $message['fromid'];
				if($tofromuid == 0)
				{
					$tofromusername = ''.$SITENAME.' Engine';
				}

				if(!$tofromusername)
				{
					$tofromuid = 0;
					$tofromusername = $lang->na;
				}
			}

			$tofromusername = build_profile_link($tofromusername, $tofromuid);
			
			
			

			
			
			
			
			
		   $useravatar = format_avatar($avatar, $dimensions);

           if (strpos($useravatar['image'], '<') === 0) 
           {
      
	               $ava_img = '
                        <svg class="nav-avatar rounded border avatar-ring2" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                           <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
                           <text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">No Avatar</text>
                        </svg>';
            } 
            else 
            {
                   $ava_img = '<img class="user-avatar" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';
            }


			
			
			

			if($usergroups['candenypmreceipts'] == 1 && $message['receipt'] == '1' && $message['folder'] != '3' && $message['folder'] != 2)
			
			{
				$denyreceipt = '<span class="smalltext"><a href="private.php?action=read&amp;pmid='.$message['pmid'].'&amp;denyreceipt=1">'.$lang->private['deny_receipt'].'</a></span>';
			}
			else
			{
				$denyreceipt = '';
			}

			

			if(!trim($message['subject']))
			{
				$message['subject'] = $lang->private['pm_no_subject'];
			}

			$message['subject'] = htmlspecialchars_uni($parser->parse_badwords($message['subject']));
			if($message['folder'] != "3")
			{
				$senddate = my_datee('relative', $message['dateline']);
			}
			else
			{
				$senddate = $lang->not_sent;
			}

			$plugins->run_hooks("private_message");

			$messagelist .= '<div class="card message-card shadow-sm border-0 mb-3">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <!-- Аватар -->
           
		   <div class="col-auto">
    
        '.$ava_img.'
    
</div>
		   
		   
		   
            
            <!-- Основной контент -->
            <div class="col">
                <div class="d-flex flex-column">
                    <h6 class="mb-1">
                        <a href="private.php?action=read&amp;pmid='.$message['pmid'].'" 
                           class="message-title '.$msgstatus.' text-decoration-none fw-semibold">
                            '.$message['subject'].'
                        </a>
                        '.$denyreceipt.'
                    </h6>
                    <span class="text-muted small">
                        <i class="fas fa-user me-1"></i>'.$tofromusername.'
                    </span>
                </div>
            </div>
            
			
			
          
		<!-- Статус сообщения с Bootstrap Popover -->
<div class="col-auto d-none d-lg-block">
    <div class="message-status">
        <span class="status-badge '.$badge_class.'"
              data-bs-toggle="popover"
              data-bs-trigger="hover focus"
              data-bs-placement="top"
              data-bs-title="'.$popover_title.'"
              data-bs-content="'.$popover_content.'"
              data-bs-html="true"
              data-bs-container="body"
              title="'.$msgalt.'">
            '.$fa_icon_html.'
        </span>
    </div>
</div>
			
			
            
           <!-- Дата и выбор сообщения -->
<div class="col-lg-3">
    <div class="d-flex align-items-center justify-content-end gap-3">
        <!-- Дата -->
        <span class="text-muted small text-nowrap">
            <i class="far fa-clock me-1"></i>
            '.$senddate.'
        </span>
        
        <!-- Свитч выбора сообщения -->
        <div class="form-check form-switch message-select-switch">
            <input type="checkbox" 
                   class="form-check-input message-select-toggle" 
                   name="check['.$message['pmid'].']" 
                   value="1"
                   id="select-'.$message['pmid'].'"
                   data-pmid="'.$message['pmid'].'">
            <label class="form-check-label" for="select-'.$message['pmid'].'">
                <span class="select-text"></span>
            </label>
        </div>
    </div>
</div>
        </div>
    </div>
</div>


<style>

/* Базовые стили для бейджа статуса */
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    cursor: default;
    border: 1px solid transparent;
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

.status-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.12);
}

/* Новое сообщение */
.status-badge.status-new {
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(231, 76, 60, 0.08) 100%);
    color: #e74c3c;
    border-color: rgba(231, 76, 60, 0.2);
}

/* Прочитанное */
.status-badge.status-read {
    background: linear-gradient(135deg, rgba(149, 165, 166, 0.15) 0%, rgba(149, 165, 166, 0.08) 100%);
    color: #95a5a6;
    border-color: rgba(149, 165, 166, 0.2);
}

/* Ответ */
.status-badge.status-reply {
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.15) 0%, rgba(52, 152, 219, 0.08) 100%);
    color: #3498db;
    border-color: rgba(52, 152, 219, 0.2);
}

/* Пересланное */
.status-badge.status-forward {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.15) 0%, rgba(39, 174, 96, 0.08) 100%);
    color: #27ae60;
    border-color: rgba(39, 174, 96, 0.2);
}

/* По умолчанию */
.status-badge.status-default {
    background: linear-gradient(135deg, rgba(127, 140, 141, 0.15) 0%, rgba(127, 140, 141, 0.08) 100%);
    color: #7f8c8d;
    border-color: rgba(127, 140, 141, 0.2);
}

/* Анимация пульсации для новых сообщений */
@keyframes pulse-glow {
    0% {
        box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(231, 76, 60, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
    }
}

.status-badge.status-new {
    animation: pulse-glow 2s infinite;
}

/* Мобильная версия */
@media (max-width: 992px) {
    .status-badge {
        width: 36px;
        height: 36px;
        font-size: 14px;
        border-radius: 8px;
    }
}



.message-card {
    border-radius: 12px;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.message-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}


.nav-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 6px;
            border: 1px solid #ccc;
        }

.user-avatar { 
    width: 44px; 
    height: 44px; 
    border-radius: 50%; 
    object-fit: cover; 
    margin-right: 0.75rem; 
    border: 2px solid #e9ecef; 
    transition: border-color 0.3s ease;
}
.user-avatar:hover {
    border-color: #007bff;
}

.avatar-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.message-title {
    color: #2c3e50;
    transition: color 0.2s ease;
}

.message-title:hover {
    color: #3498db;
}

.message-title.unread {
    color: #e74c3c;
    font-weight: 700;
}

.message-title.replied {
    color: #27ae60;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #f8f9fa;
    padding: 6px;
}

.status-icon {
    width: 20px;
    height: 20px;
}

.message-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    border: 2px solid #dee2e6;
}

.message-checkbox:checked {
    background-color: #3498db;
    border-color: #3498db;
}

@media (max-width: 992px) {
    .card-body {
        padding: 1.5rem !important;
    }
}
</style>
<script>
// Добавьте этот код в конец страницы
document.addEventListener(\'DOMContentLoaded\', function() {
    // Обработчики для свитчей выбора сообщений
    const messageToggles = document.querySelectorAll(\'.message-select-toggle\');
    
    messageToggles.forEach(toggle => {
        toggle.addEventListener(\'change\', function() {
            const pmid = this.getAttribute(\'data-pmid\');
            const card = this.closest(\'.message-card\');
            const labelText = this.parentElement.querySelector(\'.select-text\');
            
            if(this.checked) {
                // Добавляем класс выделения
                if(card) card.classList.add(\'selected\');
                if(labelText) labelText.textContent = \'Selected\';
            } else {
                // Убираем выделение
                if(card) card.classList.remove(\'selected\');
                if(labelText) labelText.textContent = \'Select\';
            }
            
            // Обновляем состояние главного свитча "Выбрать все"
            updateSelectAllSwitch();
        });
        
        // Инициализируем начальное состояние
        if(toggle.checked) {
            const card = toggle.closest(\'.message-card\');
            const labelText = toggle.parentElement.querySelector(\'.select-text\');
            if(card) card.classList.add(\'selected\');
            if(labelText) labelText.textContent = \'Selected\';
        }
    });
    
    // Функция обновления главного свитча "Выбрать все"
    function updateSelectAllSwitch() {
        const selectAllSwitch = document.getElementById(\'selectAllSwitch\');
        if(!selectAllSwitch) return;
        
        const checkedToggles = document.querySelectorAll(\'.message-select-toggle:checked\');
        const totalToggles = messageToggles.length;
        
        if(checkedToggles.length === totalToggles) {
            selectAllSwitch.checked = true;
            selectAllSwitch.indeterminate = false;
        } else if(checkedToggles.length > 0) {
            selectAllSwitch.checked = false;
            selectAllSwitch.indeterminate = true;
        } else {
            selectAllSwitch.checked = false;
            selectAllSwitch.indeterminate = false;
        }
    }
    
    // Обработчик для главного свитча "Выбрать все"
    const selectAllSwitch = document.getElementById(\'selectAllSwitch\');
    if(selectAllSwitch) {
        selectAllSwitch.addEventListener(\'change\', function() {
            const isChecked = this.checked;
            
            messageToggles.forEach(toggle => {
                toggle.checked = isChecked;
                
                // Триггерим событие change для каждого свитча
                toggle.dispatchEvent(new Event(\'change\'));
            });
        });
    }
    
    // Клик по карточке также переключает свитч
    const messageCards = document.querySelectorAll(\'.message-card\');
    messageCards.forEach(card => {
        card.addEventListener(\'click\', function(e) {
            // Не переключаем если кликнули на ссылку или сам свитч
            if(e.target.tagName === \'A\' || e.target.closest(\'a\') || 
               e.target.classList.contains(\'message-select-toggle\') ||
               e.target.closest(\'.message-select-toggle\')) {
                return;
            }
            
            const toggle = this.querySelector(\'.message-select-toggle\');
            if(toggle) {
                toggle.checked = !toggle.checked;
                toggle.dispatchEvent(new Event(\'change\'));
            }
        });
        
        // Курсор указателя для всей карточки
        card.style.cursor = \'pointer\';
    });
});
</script>';
			$bgcolor = alt_trow();
		}
	}
	else
	{
		
		
		
		$messagelist .= '
<tr>
    <td colspan="7" class="trow1">
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;">
                <i class="fas fa-inbox"></i>
            </div>
            <div style="font-size: 18px; color: #4a5568; margin-bottom: 10px;">
                <strong>' . $lang->private['nomessages'] . '</strong>
            </div>
            <div style="font-size: 14px; color: #718096;">
                <i class="fas fa-envelope-open-text"></i> Your mailbox is empty
            </div>
        </div>
    </td>
</tr>';
		
		
		
		
	}

	$pmspacebar = '';
	if($usergroups['pmquota'] != 0)
	{
		$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$CURUSER['id']."'");
		$pmscount = $db->fetch_array($query);
		if($pmscount['total'] == 0)
		{
			$spaceused = 0;
		}
		else
		{
			$spaceused = $pmscount['total'] / $usergroups['pmquota'] * 100;
		}
		$spaceused2 = 100 - $spaceused;
		$belowhalf = $overhalf = '';
		if($spaceused <= "50")
		{
			$spaceused_severity = "low";
			$belowhalf = round($spaceused, 0)."%";
			if((int)$belowhalf > 100)
			{
				$belowhalf = "100%";
			}
		}
		else
		{
			if($spaceused <= "75")
			{
				$spaceused_severity = "medium";
			}

			else
			{
				$spaceused_severity = "high";
			}

			$overhalf = round($spaceused, 0)."%";
			if((int)$overhalf > 100)
			{
				$overhalf = "100%";
			}
		}

		if($spaceused > 100)
		{
			$spaceused = 100;
			$spaceused2 = 0;
		}

		$pmspacebar = '<div class="progress mt-3" style="height: 40px">
  <div class="progress-bar" role="progressbar" aria-label="Basic example" aria-valuenow="0" style="width:'.$spaceused.'%;" aria-valuemin="0" aria-valuemax="100"></div>
</div>
<div class="mt-1">'.$spaceused.'% '.$lang->private['pmspaceused'].'</div>';
	}

	$composelink = '';
	
	$composelink = '| <a href="private.php?action=send">'.$lang->private['compose_message2'].'</a>';
	

	$emptyexportlink = '';
	if($CURUSER['totalpms'] > 0)
	{
		$emptyexportlink = '<div class="col-lg-auto">
<a href="private.php?action=empty" class="links"><i class="fa-solid fa-trash"></i> &nbsp;'.$lang->private['empty_folders2'].'</a>
</div>
<div class="col-lg-auto">
<a href="private.php?action=export" class="links"><i class="fa-solid fa-download"></i> &nbsp;'.$lang->private['export_messages2'].'</a>
</div>';
	}

	$limitwarning = '';
	if($usergroups['pmquota'] != 0 && $pmscount['total'] >= $usergroups['pmquota'])
	{
		$limitwarning = '<div class="progress mt-3">
  <div class="progress-bar" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">'.$lang->private['reached_warning'].'</div>
</div>';
	}

	$plugins->run_hooks("private_end");

	$folder = '<html>
<head>
<title>'.$SITENAME.' - '.$lang->private['private_messaging'].'</title>
</head>
<body>

<div class="container-md">
<div class="row">
<div class="col-lg-3">

'.$usercpnav.'
				
</div>
				
<div class="col">

	'.$limitwarning.' 
	<form action="private.php" method="post" name="pmForm">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div class="row g-2 mb-2">
	<div class="col-lg-5 align-self-center">

			<div class="input-group">
  <input type="text" name="keywords" value="'.$lang->private['enter_keywords'].'" onfocus="if(this.value == \''.$lang->private['enter_keywords'].'\') { this.value = \'\'; }" onblur="if(this.value==\'\') { this.value=\''.$lang->private['enter_keywords'].'\'; }" class="form-control form-control-sm border rounded" size="25" />&nbsp;&nbsp;
				<button class="btn btn-primary btn-sm rounded" type="submit" name="quick_search"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;'.$lang->private['search_pms'].'</button>
			</div>
		</div>
		<div class="col-lg text-lg-end align-self-center">
			<a href="private.php?action=advanced_search" class="links"><i class="fa-solid fa-gear"></i> &nbsp;'.$lang->private['advanced_search'].'</a>
		</div>
	</div>
	
<div class="card border-0 mb-3">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-2 text-forum">
			<div class="col-1">
				&nbsp;
			</div>
			<div class="col align-self-center">
				<a href="private.php?fid='.$fid.'&amp;sortby=subject&amp;order=asc">'.$lang->private['message_title'].'</a> &mdash; <a href="private.php?fid='.$fid.'&amp;sortby=username&amp;order=asc">'.$sender.'</a>
			</div>
			
			<div class="col-3 align-self-center">
    <div class="d-flex align-items-center justify-content-end gap-2">
        <!-- Дата -->
        <a href="private.php?fid='.$fid.'&amp;sortby=dateline&amp;order=desc" 
           class="d-none d-sm-none d-md-none d-lg-inline-block d-xl-inline-block d-xxl-inline-block text-decoration-none">
            '.$lang->private['date_sent'].'
        </a>
        
        <!-- Свитч для выбора всех -->
        <div class="form-check form-switch ms-auto">
            <input class="form-check-input select-all-switch" 
                   type="checkbox" 
                   role="switch"
                   id="selectAllSwitch"
                   title="'.$lang->private['check_all'].'">
            <label class="form-check-label small ms-2" for="selectAllSwitch">
                '.$lang->private['check_all'].'
            </label>
        </div>
    </div>
</div>
	</div>
	
	</div>
	</div>
	

'.$messagelist.'
'.$multipage.' 
	<div class="card border-0 mt-3">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
		
		<div class="row g-1">
			<div class="col">
				&nbsp;
			</div>
						<div class="col-auto">
				<button type="submit" class="btn btn-primary btn-sm" name="moveto" value="'.$lang->private['move_to'].'">'.$lang->private['move_to'].' &nbsp;<i class="fa-solid fa-arrow-right"></i></button>  
			</div>
			<div class="col-auto">
	'.$folderoplist.' 
			</div>
			<div class="col-auto">
				&nbsp;'.$lang->private['or'].'&nbsp; <button type="submit" class="btn btn-primary btn-sm" name="delete" value="'.$lang->private['delete'].'"><i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->private['delete'].'</button>
			</div>
			<div class="col">
				&nbsp;
			</div>
	</div>
		</div>
	</div>
	
<div class="row g-3 mt-2 text-start">
	<div class="col d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
		&nbsp;
	</div>
	<div class="col-lg-auto">
<a  href="private.php?action=folders" class="links"><i class="fa-solid fa-folder-open"></i> &nbsp;'.$lang->private['manage_folders'].'</a>
	</div>
   '.$emptyexportlink.'
	</div>
	
	
	'.$pmspacebar.'

			
<input type="hidden" name="action" value="do_stuff" />
</form>
	</div>
	</div>
	
	</div>
	
</div>
</div>



</body>
</html>';
	
	stdhead('title');
	
	echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/popover.js"></script>';
	
	echo $folder;
	
	stdfoot();
}