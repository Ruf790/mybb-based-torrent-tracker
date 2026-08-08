<?php

declare(strict_types=1);

// work out which items the user has collapsed
$collapse = $collapsed = $collapsedimg = $collapsedthead = [];
if (!empty($mybb->cookies['collapsed'])) {
    $colcookie = $mybb->cookies['collapsed'];
    // Preserve and don't unset $collapse, will be needed globally throughout many pages
    $collapse = explode("|", $colcookie);
    foreach ($collapse as $val) {
        $collapsed[$val."_e"] = "display: none;";
        $collapsedimg[$val] = "_collapsed";
        $collapsedthead[$val] = " thead_collapsed";
    }
}


function validate_password_from_uid(int|string $uid, string $password, array $user = []): array|false
{
    global $db, $mybb, $CURUSER;

    if (isset($CURUSER['id']) && $CURUSER['id'] == $uid) {
        $user = $mybb->user;
    }

    if (!$user['password']) {
        $user = get_user((int)$uid);
    }

    if (empty($user['loginkey'])) 
	{
        $user['loginkey'] = generate_loginkey();

        if (!empty($user['id'])) 
		{
            $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$user['loginkey'], $user['id']]);
        }
    }

    if (verify_user_password($user, $password)) {
        return $user;
    }

    return false;
}


function create_password(string $password, array|false $user = false): array
{
    global $plugins;

    $fields = null;

    $parameters = compact('password', 'user', 'fields');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('create_password', $parameters);
    }

    if ($parameters['fields'] !== null) {
        $fields = $parameters['fields'];
    } 
	
	else 
	{
       
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $fields = [
            'password' => $hash,
        ];
    }

    return $fields;
}

function verify_user_password(array $user, string $password): bool
{
    global $plugins;

    $result = null;

    $parameters = compact('user', 'password', 'result');

    if (!defined('IN_INSTALL') && !defined('IN_UPGRADE')) {
        $plugins->run_hooks('verify_user_password', $parameters);
    }

    if ($parameters['result'] !== null) {
        return $parameters['result'];
    }

 
    return password_verify($password, $user['password'] ?? '');
}


function generate_loginkey(): string
{
    return random_str(50);
}


function update_loginkey(int|string $uid): string
{
    global $db;

    $loginkey = generate_loginkey();
    $db->sql_query_prepared("UPDATE users SET loginkey = ? WHERE id = ?", [$loginkey, $uid]);

    return $loginkey;
}


function add_subscribed_thread(int|string $tid, int|string $notification = 1, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $query = $db->sql_query_prepared("SELECT * FROM threadsubscriptions WHERE tid = ? AND uid = ?", [(int)$tid, (int)$uid]);
    $subscription = $query ? $db->fetch_array($query) : null;

    if (!$subscription) {
        $db->sql_query_prepared(
            "INSERT INTO threadsubscriptions (`uid`,`tid`,`notification`,`dateline`) VALUES (?,?,?,?)",
            [(int)$uid, (int)$tid, (int)$notification, TIMENOW]
        );
    } else {
        // Subscription exists - simply update notification
        $db->sql_query_prepared(
            "UPDATE threadsubscriptions SET notification = ? WHERE uid = ? AND tid = ?",
            [(int)$notification, $uid, $tid]
        );
    }

    return true;
}


function remove_subscribed_thread(int|string $tid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid = ? AND uid = ?", [$tid, $uid]);

    return true;
}


function add_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $fid = (int)$fid;
    $uid = (int)$uid;

    $query = $db->sql_query_prepared("SELECT * FROM forumsubscriptions WHERE fid = ? AND uid = ? LIMIT 1", [$fid, $uid]);
    $fsubscription = $query ? $db->fetch_array($query) : null;

    if (!$fsubscription) {
        $db->sql_query_prepared("INSERT INTO forumsubscriptions (`fid`,`uid`) VALUES (?,?)", [$fid, $uid]);
    }

    return true;
}


function remove_subscribed_forum(int|string $fid, int|string $uid = 0): bool
{
    global $mybb, $db, $CURUSER;

    if (!$uid) {
        $uid = $CURUSER['id'];
    }

    if (!$uid) {
        return false;
    }

    $db->sql_query_prepared("DELETE FROM forumsubscriptions WHERE fid = ? AND uid = ?", [$fid, $uid]);

    return true;
}


function usercp_menu(): void
{
    global $mybb, $theme, $plugins, $lang, $usercpnav, $usercpmenu, $enablepms, $usergroups;

    $lang->load("usercpnav");

    // Add the default items as plugins with separated priorities of 10
    if ($enablepms != 0 && $usergroups['canusepms'] == 1) {
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


function usercp_menu_messenger(): void
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
    foreach ($foldersexploded as $key => $folders) {
        $folderinfo = explode("**", $folders, 2);
        $folderinfo[1] = get_pm_folder_name((int)$folderinfo[0], $folderinfo[1] ?? '');
        $folder_id = $folderinfo[0];
        $folder_name = $folderinfo[1];
        $folderlinks .= '<a href="private.php?fid='.$folder_id.'" class="btn btn-menu-coll"><i class="fa-regular fa-folder-open ms-2"></i> &nbsp;&nbsp; '.$folder_name.'</a>';
    }

    if (!isset($collapsedimg['usercppms'])) {
        $collapsedimg['usercppms'] = '';
    }
    if (!isset($collapsed['usercppms_e'])) {
        $collapsed['usercppms_e'] = '';
    }

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
            </ul>
        </div>
    </div>';

    $usercpmenu .= $usercp_nav_messenger;
}


function usercp_menu_profile(): void
{
    global $db, $mybb, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");

    $changenameop = '<a href="usercp.php?action=changename" class="btn btn-menu-coll"><i class="fa-solid fa-user ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_username'].'</a>';

    $changesigop = '<a href="usercp.php?action=editsig" class="btn btn-menu-coll"><i class="fa-solid fa-signature ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_sig'].'</a>';

    if (!isset($collapsedimg['usercpprofile'])) {
        $collapsedimg['usercpprofile'] = '';
    }

    if (!isset($collapsed['usercpprofile_e'])) {
        $collapsed['usercpprofile_e'] = '';
    }

    $expaltext = in_array("usercpprofile", $collapse ?? []) ? '[+]' : '[-]';

    $usercpmenu .= '
    <div>
<a class="btn btn-menu"data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-modu" role="button"><i class="fa-solid fa-gears me-2"></i>'.$lang->usercpnav['ucp_nav_profile'].'</a>

<div id="collapse-modu" class="collapse bg-transparent">
<ul class="list-group list-group-flush">
<a href="usercp.php?action=profile" class="btn btn-menu-coll"><i class="fa-solid fa-user-pen ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_profile'].'</a>
'.$changenameop.'
<a href="usercp.php?action=password" class="btn btn-menu-coll"><i class="fa-solid fa-key ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_pass'].'</a>
<a href="usercp.php?action=2fa" class="btn btn-menu-coll"><i class="fa-solid fa-shield-halved ms-2"></i> &nbsp;&nbsp; Two-Factor Authentication</a>
<a href="usercp.php?action=sessions" class="btn btn-menu-coll"><i class="fa-solid fa-display ms-2"></i> &nbsp;&nbsp; Active Session</a>
<a href="usercp.php?action=email" class="btn btn-menu-coll"><i class="fa-solid fa-at ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_email'].'</a>
<a href="usercp.php?action=avatar" class="btn btn-menu-coll"><i class="fa-solid fa-image ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_change_avatar'].'</a>
'.$changesigop.'
<a href="usercp.php?action=options" class="btn btn-menu-coll"><i class="fa-solid fa-gears ms-2"></i> &nbsp;&nbsp; '.$lang->usercpnav['ucp_nav_edit_options'].'</a>
</ul>
</div>
</div>';
}


function usercp_menu_misc(): void
{
    global $db, $mybb, $CURUSER, $theme, $usercpmenu, $lang, $collapse, $collapsed, $collapsedimg;

    $lang->load("usercpnav");

    $draftstart = $draftend = $attachmentop = '';
    $draftcount = 'Saved Drafts';

    $query = $db->sql_query_prepared("SELECT COUNT(pid) AS draftcount FROM posts WHERE visible = '-2' AND uid = ?", [$CURUSER['id']]);
    $count = $query ? $db->fetch_field($query, 'draftcount') : 0;

    if ($count > 0) {
        $draftcount = sprintf('<strong>Saved Drafts ('.ts_nf($count).')</strong>');
    }

    $attachmentop = '<a href="usercp.php?action=attachments" class="btn btn-menu"><i class="fa-solid fa-paperclip me-2"></i>Manage Attachments</a>';

    if (!isset($collapsedimg['usercpmisc'])) {
        $collapsedimg['usercpmisc'] = '';
    }

    if (!isset($collapsed['usercpmisc_e'])) {
        $collapsed['usercpmisc_e'] = '';
    }

    $profile_link = get_profile_link($CURUSER['id']);
    $expaltext = in_array("usercpmisc", $collapse) ? '[+]' : '[-]';

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




function update_pm_count(int|string $uid = 0, int $count_to_update = 7): array
{
    global $db, $mybb, $CURUSER;

    // If no user id, assume that we mean the current logged in user.
    if ((int)$uid == 0) {
        $uid = (int)(is_array($CURUSER) ? ($CURUSER['id'] ?? 0) : 0);
    }

    $uid = (int)$uid;
    $pmcount = [];

    if ($uid == 0) {
        return $pmcount;
    }

    // Update total number of messages.
    if ($count_to_update & 1) {
        $query = $db->sql_query_prepared("SELECT COUNT(pmid) AS pms_total FROM privatemessages WHERE uid = ?", [$uid]);
        $total = $query ? $db->fetch_array($query) : null;
        $pmcount['totalpms'] = $total['pms_total'];
    }

    // Update number of unread messages.
    if ($count_to_update & 2 && $db->field_exists("unreadpms", "users") == true) {
        $query = $db->sql_query_prepared(
            "SELECT COUNT(pmid) AS pms_unread FROM privatemessages WHERE uid = ? AND status = '0' AND folder = '1'",
            [$uid]
        );
        $unread = $query ? $db->fetch_array($query) : null;
        $pmcount['unreadpms'] = $unread['pms_unread'];
    }

    if (!empty($pmcount)) {
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($pmcount)));
        $params = array_values($pmcount);
        $params[] = $uid;

        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }

    return $pmcount;
}


function get_pm_folder_name(int|string $fid, string $name = ''): string
{
    global $lang;

    if ($name !== '') {
        return $name;
    }

    return match ((int)$fid) {
        0       => 'Inbox',
        1       => 'Unread',
        2       => 'Sent Items',
        3       => 'Drafts',
        4       => 'Trash Can',
        default => 'folder_untitled',
    };
}
