<?php

 
// work out which items the user has collapsed
$collapse = $collapsed = $collapsedimg = $collapsedthead = array();
if(!empty($mybb->cookies['collapsed']))
{
	$colcookie = $mybb->cookies['collapsed'];
	// Preserve and don't unset $collapse, will be needed globally throughout many pages
	$collapse = explode("|", $colcookie);
	foreach($collapse as $val)
	{
		$collapsed[$val."_e"] = "display: none;";
		$collapsedimg[$val] = "_collapsed";
		$collapsedthead[$val] = " thead_collapsed";
	}
}
 


function user_exists($uid)
{
	global $db;

	$query = $db->simple_select("users", "COUNT(*) as user", "id='".(int)$uid."'", array('limit' => 1));
	if($db->fetch_field($query, 'user') == 1)
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Checks if $username already exists in the database.
 *
 * @param string $username The username for check for.
 * @return boolean True when exists, false when not.
 */
function username_exists($username)
{
	$options = array(
		'username_method' => 2
	);

	return (bool)get_user_by_username($username, $options);
}

/**
 * Checks a password with a supplied username.
 *
 * @param string $username The username of the user.
 * @param string $password The plain-text password.
 * @return boolean|array False when no match, array with user info when match.
 */
function validate_password_from_username($username, $password)
{
	global $mybb, $username_method;

	$options = array(
		'fields' => '*',
		'username_method' => $username_method,
	);

	$user = get_user_by_username($username, $options);

	if(!$user)
	{
		return false;
	}

	return validate_password_from_uid($user['uid'], $password, $user);
}

/**
 * Checks a password with a supplied uid.
 *
 * @param int $uid The user id.
 * @param string $password The plain-text password.
 * @param array $user An optional user data array.
 * @return boolean|array False when not valid, user data array when valid.
 */
function validate_password_from_uid($uid, $password, $user = array())
{
	global $db, $mybb, $CURUSER;
	if(isset($CURUSER['id']) && $CURUSER['id'] == $uid)
	{
		$user = $mybb->user;
	}
	if(!$user['password'])
	{
		$user = get_user($uid);
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$sql_array = array(
			"loginkey" => $user['loginkey']
		);
		$db->update_query("users", $sql_array, "id = ".$CURUSER['id']);
	}
	if(verify_user_password($user, $password))
	{
		return $user;
	}
	else
	{
		return false;
	}
}

/**
 * Updates a user's password.
 *
 * @param int $uid The user's id.
 * @param string $password The md5()'ed password.
 * @param string $salt (Optional) The salt of the user.
 * @return array The new password.
 * @deprecated deprecated since version 1.8.6 Please use other alternatives.
 */
function update_password($uid, $password, $salt="")
{
	global $db, $plugins;

	$newpassword = array();

	// If no salt was specified, check in database first, if still doesn't exist, create one
	if(!$salt)
	{
		$query = $db->simple_select("users", "salt", "id='$uid'");
		$user = $db->fetch_array($query);
		if($user['salt'])
		{
			$salt = $user['salt'];
		}
		else
		{
			$salt = generate_salt();
		}
		$newpassword['salt'] = $salt;
	}

	// Create new password based on salt
	$saltedpw = salt_password($password, $salt);

	// Generate new login key
	$loginkey = generate_loginkey();

	// Update password and login key in database
	$newpassword['password'] = $saltedpw;
	$newpassword['loginkey'] = $loginkey;
	$db->update_query("users", $newpassword, "id='$uid'");

	$plugins->run_hooks("password_changed");

	return $newpassword;
}

/**
 * Salts a password based on a supplied salt.
 *
 * @param string $password The md5()'ed password.
 * @param string $salt The salt.
 * @return string The password hash.
 * @deprecated deprecated since version 1.8.9 Please use other alternatives.
 */
function salt_password($password, $salt)
{
	return md5(md5($salt).$password);
}

/**
 * Salts a password based on a supplied salt.
 *
 * @param string $password The input password.
 * @param string $salt (Optional) The salt used by the MyBB algorithm.
 * @param string $user (Optional) An array containing password-related data.
 * @return array Password-related fields.
 */
function create_password($password, $salt = false, $user = false)
{
	global $plugins;

	$fields = null;

	$parameters = compact('password', 'salt', 'user', 'fields');

	if(!defined('IN_INSTALL') && !defined('IN_UPGRADE'))
	{
		$plugins->run_hooks('create_password', $parameters);
	}

	if(!is_null($parameters['fields']))
	{
		$fields = $parameters['fields'];
	}
	else
	{
		if(!$salt)
		{
			$salt = generate_salt();
		}

		$hash = md5(md5($salt).md5($password));

		$fields = array(
			'salt' => $salt,
			'password' => $hash,
		);
	}

	return $fields;
}

/**
 * Compares user's password data against provided input.
 *
 * @param array $user An array containing password-related data.
 * @param string $password The plain-text input password.
 * @return bool Result of the comparison.
 */
function verify_user_password($user, $password)
{
	global $plugins;

	$result = null;

	$parameters = compact('user', 'password', 'result');

	if(!defined('IN_INSTALL') && !defined('IN_UPGRADE'))
	{
		$plugins->run_hooks('verify_user_password', $parameters);
	}

	if(!is_null($parameters['result']))
	{
		return $parameters['result'];
	}
	else
	{
		$password_fields = create_password($password, $user['salt'], $user);

		return my_hash_equals($user['password'], $password_fields['password']);
	}
}

/**
 * Generates a random salt
 *
 * @return string The salt.
 */
function generate_salt()
{
	return random_str(8);
}

/**
 * Generates a 50 character random login key.
 *
 * @return string The login key.
 */
function generate_loginkey()
{
	return random_str(50);
}

/**
 * Updates a user's salt in the database (does not update a password).
 *
 * @param int $uid The uid of the user to update.
 * @return string The new salt.
 */
function update_salt($uid)
{
	global $db;

	$salt = generate_salt();
	$sql_array = array(
		"salt" => $salt
	);
	$db->update_query("users", $sql_array, "id='{$uid}'");

	return $salt;
}

/**
 * Generates a new login key for a user.
 *
 * @param int $uid The uid of the user to update.
 * @return string The new login key.
 */
function update_loginkey($uid)
{
	global $db;

	$loginkey = generate_loginkey();
	$sql_array = array(
		"loginkey" => $loginkey
	);
	$db->update_query("users", $sql_array, "id='{$uid}'");

	return $loginkey;

}

/**
 * Adds a thread to a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $tid The tid of the thread to add to the list.
 * @param int $notification (Optional) The type of notification to receive for replies (0=none, 1=email, 2=pm)
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_thread($tid, $notification=1, $uid=0)
{
	global $mybb, $db, $CURUSER;

	if(!$uid)
	{
		$uid = $CURUSER['id'];
	}

	if(!$uid)
	{
		return false;
	}

	$query = $db->simple_select("threadsubscriptions", "*", "tid='".(int)$tid."' AND uid='".(int)$uid."'");
	$subscription = $db->fetch_array($query);
	if(!$subscription)
	{
		$insert_array = array(
			'uid' => (int)$uid,
			'tid' => (int)$tid,
			'notification' => (int)$notification,
			'dateline' => TIMENOW
		);
		$db->insert_query("threadsubscriptions", $insert_array);
	}
	else
	{
		// Subscription exists - simply update notification
		$update_array = array(
			"notification" => (int)$notification
		);
		$db->update_query("threadsubscriptions", $update_array, "uid='{$uid}' AND tid='{$tid}'");
	}
	return true;
}

/**
 * Remove a thread from a user's thread subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $tid The tid of the thread to remove from the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_thread($tid, $uid=0)
{
	global $mybb, $db, $CURUSER;

	if(!$uid)
	{
		$uid = $CURUSER['id'];
	}

	if(!$uid)
	{
		return false;
	}
	$db->delete_query("threadsubscriptions", "tid='".$tid."' AND uid='{$uid}'");

	return true;
}

/**
 * Adds a forum to a user's forum subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $fid The fid of the forum to add to the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function add_subscribed_forum($fid, $uid=0)
{
	global $mybb, $db, $CURUSER;

	if(!$uid)
	{
		$uid = $CURUSER['id'];
	}

	if(!$uid)
	{
		return false;
	}

	$fid = (int)$fid;
	$uid = (int)$uid;

	$query = $db->simple_select("forumsubscriptions", "*", "fid='".$fid."' AND uid='{$uid}'", array('limit' => 1));
	$fsubscription = $db->fetch_array($query);
	if(!$fsubscription)
	{
		$insert_array = array(
			'fid' => $fid,
			'uid' => $uid
		);
		$db->insert_query("forumsubscriptions", $insert_array);
	}

	return true;
}

/**
 * Removes a forum from a user's forum subscription list.
 * If no uid is supplied, the currently logged in user's id will be used.
 *
 * @param int $fid The fid of the forum to remove from the list.
 * @param int $uid (Optional) The uid of the user who's list to update.
 * @return boolean True when success, false when otherwise.
 */
function remove_subscribed_forum($fid, $uid=0)
{
	global $mybb, $db, $CURUSER;

	if(!$uid)
	{
		$uid = $CURUSER['id'];
	}

	if(!$uid)
	{
		return false;
	}
	$db->delete_query("forumsubscriptions", "fid='".$fid."' AND uid='{$uid}'");

	return true;
}

/**
 * Constructs the usercp navigation menu.
 *
 */
function usercp_menu()
{
	global $mybb, $theme, $plugins, $lang, $usercpnav, $usercpmenu, $enablepms, $usergroups;

	$lang->load("usercpnav");

	// Add the default items as plugins with separated priorities of 10
	if($enablepms != 0 && $usergroups['canusepms'] == 1)
	{
		$plugins->add_hook("usercp_menu", "usercp_menu_messenger", 10);
	}

	
	$plugins->add_hook("usercp_menu", "usercp_menu_profile", 20);
	$plugins->add_hook("usercp_menu", "usercp_menu_misc", 30);
	

	// Run the plugin hooks
	$plugins->run_hooks("usercp_menu");
	global $usercpmenu;

	$ucp_nav_home = '
	
	<a href="usercp.php" class="btn btn-menu"><i class="fa-solid fa-house me-2"></i>'.$lang->usercpnav['ucp_nav_home'].'</a>';
	


    $usercpnav = '
	
	<div class="card mb-4 border-0">
	<div class="card-body m-0 p-0">
		'.$ucp_nav_home.'
'.$usercpmenu.'
	</div>
</div>';
	
	
	
	

	$plugins->run_hooks("usercp_menu_built");
}

/**
 * Constructs the usercp messenger menu.
 *
 */
function usercp_menu_messenger()
{
    global $db, $mybb, $CURUSER, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");

    // 1. Сначала собираем tracking
    $ucp_nav_tracking = '<a href="private.php?action=tracking" class="btn btn-menu-coll"><i class="fa-solid fa-flag ms-2"></i> &nbsp;&nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_tracking'].'</a>';

    // 2. Потом compose
    $ucp_nav_compose = '<a href="private.php?action=send" class="btn btn-menu-coll"><i class="fa-solid fa-marker ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_compose'].'</a>';

    // 3. Потом папки
    $folderlinks = $folder_id = $folder_name = '';
    $foldersexploded = explode("$%%$", $CURUSER['pmfolders']);
    foreach($foldersexploded as $key => $folders)
    {
        $folderinfo = explode("**", $folders, 2);
        $folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
        $folder_id = $folderinfo[0];
        $folder_name = $folderinfo[1];
        $folderlinks .= '<a href="private.php?fid='.$folder_id.'" class="btn btn-menu-coll"><i class="fa-regular fa-folder-open ms-2"></i> &nbsp;&nbsp; '.$folder_name.'</a>';
    }

    if(!isset($collapsedimg['usercppms'])) { $collapsedimg['usercppms'] = ''; }
    if(!isset($collapsed['usercppms_e'])) { $collapsed['usercppms_e'] = ''; }

    // 4. Только теперь собираем итоговый HTML — все переменные уже готовы
    $usercp_nav_messenger = '
    <div>
        <a class="btn btn-menu" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-modu2" role="button">
            <i class="fa-solid fa-envelope-open-text me-2"></i>'.$lang->usercpnav['ucp_nav_messenger'].'
        </a>
        <div id="collapse-modu2" class="collapse bg-transparent">
            <ul class="list-group list-group-flush ms-0">
                '.$ucp_nav_compose.'
                '.$folderlinks.'
                '.$ucp_nav_tracking.'
                <a href="private.php?action=folders" class="btn btn-menu-coll"><i class="fa-solid fa-gear ms-2"></i> &nbsp;&nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_folders'].'</a>
            </ul>
        </div>
    </div>';

    $usercpmenu .= $usercp_nav_messenger;
}


/**
 * Constructs the usercp profile menu.
 *
 */
function usercp_menu_profile()
{
	global $db, $mybb, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

	$lang->load("usercpnav");
	
	
	$changenameop = '';
	
	$changenameop = '<a href="usercp.php?action=changename" class="btn btn-menu-coll"><i class="fa-solid fa-user ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_username'].'</a>';
	
	$changesigop ='<a href="usercp.php?action=editsig" class="btn btn-menu-coll"><i class="fa-solid fa-signature ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_sig'].'</a>';
	

	if(!isset($collapsedimg['usercpprofile']))
	{
		$collapsedimg['usercpprofile'] = '';
	}

	if(!isset($collapsed['usercpprofile_e']))
	{
		$collapsed['usercpprofile_e'] = '';
	}

	$expaltext = (in_array("usercpprofile", $collapse)) ? '[+]' : '[-]';
    
	
	$usercpmenu .= '
	
	
	<div>
<a class="btn btn-menu"data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-modu" role="button"><i class="fa-solid fa-gears me-2"></i>'.$lang->usercpnav['ucp_nav_profile'].'</a>
								
<div id="collapse-modu" class="collapse bg-transparent">
<ul class="list-group list-group-flush">
<a href="usercp.php?action=profile" class="btn btn-menu-coll"><i class="fa-solid fa-user-pen ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_profile'].'</a>
'.$changenameop.'
<a href="usercp.php?action=password" class="btn btn-menu-coll"><i class="fa-solid fa-key ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_pass'].'</a>
<a href="usercp.php?action=email" class="btn btn-menu-coll"><i class="fa-solid fa-at ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_email'].'</a>     
<a href="usercp.php?action=avatar" class="btn btn-menu-coll"><i class="fa-solid fa-image ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_avatar'].'</a>	
'.$changesigop.'
<a href="usercp.php?action=options" class="btn btn-menu-coll"><i class="fa-solid fa-gears ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_options'].'</a>
</ul>
	
</div>
</div>';
	
	
	


}

/**
 * Constructs the usercp misc menu.
 *
 */
function usercp_menu_misc()
{
	global $db, $mybb, $CURUSER, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

	$lang->load("usercpnav");
	
	$draftstart = $draftend = $attachmentop = '';
	$draftcount = 'Saved Drafts';

	
	
	$query = $db->simple_select("posts", "COUNT(pid) AS draftcount", "visible = '-2' AND uid = '{$CURUSER['id']}'");
	$count = $db->fetch_field($query, 'draftcount');

	if($count > 0)
	{
		$draftcount = sprintf('<strong>Saved Drafts ('.ts_nf($count).')</strong>');
	}

	
	$attachmentop = '<a href="usercp.php?action=attachments" class="btn btn-menu"><i class="fa-solid fa-paperclip me-2"></i>Manage Attachments</a>';


	if(!isset($collapsedimg['usercpmisc']))
	{
		$collapsedimg['usercpmisc'] = '';
	}

	if(!isset($collapsed['usercpmisc_e']))
	{
		$collapsed['usercpmisc_e'] = '';
	}

	$profile_link = get_profile_link($CURUSER['id']);
	$expaltext = (in_array("usercpmisc", $collapse)) ? '[+]' : '[-]';
	
	
	$usercpmenu .= '
	
	
	
	<div class="user-cp-nav d-flex flex-column gap-2">
  
    <div class="nav-section">
       
        <a href="usercp.php?action=editlists" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-user-group fa-fw me-2"></i>
            <span>'.$lang->usercpnav['ucp_nav_editlists'].'</span>
        </a>
    </div>

    
   
    <div class="nav-section">
        '.$attachmentop.'
    </div>
    

    <!-- Контент и подписки -->
    <div class="nav-section">
        <a href="usercp.php?action=drafts" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-pen-ruler fa-fw me-2"></i>
            <span class="badge bg-secondary ms-auto">'.$draftcount.'</span>
        </a>
        <a href="usercp.php?action=subscriptions" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-comment fa-fw me-2"></i>
            <span>'.$lang->usercpnav['ucp_nav_subscribed_threads'].'</span>
        </a>
        <a href="usercp.php?action=forumsubscriptions" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-eye fa-fw me-2"></i>
            <span>'.$lang->usercpnav['ucp_nav_forum_subscriptions'].'</span>
        </a>
    </div>

    <!-- Закладки и файлы -->
    <div class="nav-section">
        <a href="usercp.php?action=bookmarks" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-bookmark fa-fw me-2"></i>
            <span>'.$lang->usercpnav['ucp_nav_book'].'</span>
        </a>
        <a href="usercp.php?action=manage_files" class="btn btn-menu d-flex align-items-center">
            <i class="fa-solid fa-folder-open fa-fw me-2"></i>
            <span>My Files</span>
        </a>
    </div>

    <!-- Профиль -->
    <div class="nav-section mt-3">
        <a href="'.$profile_link.'" class="btn btn-menu btn-profile d-flex align-items-center">
            <i class="fa-solid fa-id-card-clip fa-fw me-2"></i>
            <span>'.$lang->usercpnav['ucp_nav_view_profile'].'</span>
            <i class="fa-solid fa-arrow-up-right-from-square ms-auto text-muted small"></i>
        </a>
    </div>
</div>

<style>
.user-cp-nav {
    padding: 1rem;
}

.nav-section {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.nav-section:last-child {
    border-bottom: none;
}

.btn-menu {
    text-align: left;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.2s ease;
    color: #495057;
    background: #f8f9fa;
    border: 1px solid transparent;
    margin-bottom: 0.25rem;
}

.btn-menu:hover {
    background: #e9ecef;
    border-color: #dee2e6;
    color: #0d6efd;
    transform: translateX(3px);
}

.btn-menu .badge {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
}

.btn-profile {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}

.btn-profile:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dde1e6 100%);
    border-color: #0d6efd;
}
</style>';
	
	
	
	

}

/**
 * Gets the usertitle for a specific uid.
 *
 * @param int $uid The uid of the user to get the usertitle of.
 * @return string The usertitle of the user.
 */
function get_usertitle($uid=0)
{
	global $db, $mybb, $CURUSER;

	if($CURUSER['id'] == $uid)
	{
		$user = $mybb->user;
	}
	else
	{
		$query = $db->simple_select("users", "usertitle,postnum", "id='$uid'", array('limit' => 1));
		$user = $db->fetch_array($query);
	}

	if($user['usertitle'])
	{
		return $user['usertitle'];
	}
	else
	{
		$usertitles = $mybb->cache->read('usertitles');
		foreach($usertitles as $title)
		{
			if($title['posts'] <= $user['postnum'])
			{
				$usertitle = $title;
				break;
			}
		}

		return $usertitle['title'];
	}
}

/**
 * Updates a users private message count in the users table with the number of pms they have.
 *
 * @param int $uid The user id to update the count for. If none, assumes currently logged in user.
 * @param int $count_to_update Bitwise value for what to update. 1 = total, 2 = new, 4 = unread. Combinations accepted.
 * @return array The updated counters
 */
function update_pm_count($uid=0, $count_to_update=7)
{
	global $db, $mybb, $CURUSER;

	// If no user id, assume that we mean the current logged in user.
	if((int)$uid == 0)
	{
		$uid = (int)(is_array($CURUSER) ? ($CURUSER['id'] ?? 0) : 0);
	}

	$uid = (int)$uid;
	$pmcount = array();
	if($uid == 0)
	{
		return $pmcount;
	}

	// Update total number of messages.
	if($count_to_update & 1)
	{
		$query = $db->simple_select("privatemessages", "COUNT(pmid) AS pms_total", "uid='".$uid."'");
		$total = $db->fetch_array($query);
		$pmcount['totalpms'] = $total['pms_total'];
	}

	// Update number of unread messages.
	if($count_to_update & 2 && $db->field_exists("unreadpms", "users") == true)
	{
		$query = $db->simple_select("privatemessages", "COUNT(pmid) AS pms_unread", "uid='".$uid."' AND status='0' AND folder='1'");
		$unread = $db->fetch_array($query);
		$pmcount['unreadpms'] = $unread['pms_unread'];
	}

	if(!empty($pmcount))
	{
		$db->update_query("users", $pmcount, "id='".$uid."'");
	}
	return $pmcount;
}

/**
 * Return the language specific name for a PM folder.
 *
 * @param int $fid The ID of the folder.
 * @param string $name The folder name - can be blank, will use language default.
 * @return string The name of the folder.
 */
function get_pm_folder_name($fid, $name="")
{
	global $lang;

	if($name != '')
	{
		return $name;
	}

	switch($fid)
	{
		case 0:
			return 'Inbox';
			break;
		case 1:
			return 'Unread';
			break;
		case 2:
			return 'Sent Items';
			break;
		case 3:
			return 'Drafts';
			break;
		case 4:
			return 'Trash Can';
			break;
		default:
			return 'folder_untitled';
	}
}
