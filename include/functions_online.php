<?php
declare(strict_types=1);

/**
 * MyBB 1.8 — Who's Online (WOL) System
 * Modernized for PHP 8.4+ | Clean, Typed, Secure
 * © 2025 xAI Enhanced
 */

if (!defined('IN_MYBB')) {
    exit('Direct access not allowed.');
}

/**
 * Activity types enum
 */
enum WolActivity: string
{
    case INDEX = 'index';
	case INDEX2  = 'index2';  
	case MYBONUS  = 'mybonus';  
	case TORRENT = 'torrent';   
    case DOWNLOAD = 'download'; 
	case UPLOAD = 'upload';
	case BROWSE = 'browse';
    case FORUM = 'forumdisplay';
    case THREAD = 'showthread';
    case POST = 'showpost';
    case NEW_THREAD = 'newthread';
    case NEW_REPLY = 'newreply';
    case PROFILE = 'member_profile';
    case EDIT_POST = 'editpost';
    case ANNOUNCEMENT = 'announcements';
    case ATTACHMENT = 'attachment';
   
    
	case MODCP                  = 'modcp';
    case MODCP_MODLOGS          = 'modcp_modlogs';
    case MODCP_ANNOUNCEMENTS    = 'modcp_announcements';
    case MODCP_FINDUSER         = 'modcp_finduser';
    case MODCP_WARNINGLOGS      = 'modcp_warninglogs';
    case MODCP_IPSEARCH         = 'modcp_ipsearch';
    case MODCP_REPORT           = 'modcp_report';
    case MODCP_NEW_ANNOUNCEMENT = 'modcp_new_announcement';
    case MODCP_DELETE_ANNOUNCEMENT = 'modcp_delete_announcement';
    case MODCP_EDIT_ANNOUNCEMENT   = 'modcp_edit_announcement';
    case MODCP_MOD_QUEUE        = 'modcp_mod_queue';
    case MODCP_EDITPROFILE      = 'modcp_editprofile';
    case MODCP_BANNING          = 'modcp_banning';
	
	
	
	case WOL       = 'wol';
    case WOLTODAY  = 'woltoday';
	
	case PRIVATE          = 'private';
    case PRIVATE_SEND     = 'private_send';
    case PRIVATE_READ     = 'private_read';
    case PRIVATE_FOLDERS  = 'private_folders';
	
   
    case USERCP               = 'usercp';
    case USERCP_PROFILE       = 'usercp_profile';
    case USERCP_OPTIONS       = 'usercp_options';
    case USERCP_PASSWORD      = 'usercp_password';
    case USERCP_EDITSIG       = 'usercp_editsig';
    case USERCP_AVATAR        = 'usercp_avatar';
    case USERCP_EDITLISTS     = 'usercp_editlists';
    case USERCP_FAVORITES     = 'usercp_favorites';
    case USERCP_SUBSCRIPTIONS = 'usercp_subscriptions';
    case USERCP_MANAGEFAV     = 'usercp_managefavorites';
    case USERCP_MANAGESUB     = 'usercp_managesubscriptions';
    case USERCP_NOTEPAD       = 'usercp_notepad';
   
   
    case UNKNOWN = 'unknown';
    
	
	
	
}

/**
 * Readonly DTO for user activity
 */
readonly class UserActivity
{
    public function __construct(
        public WolActivity $activity,
        public int $uid = 0,
        public int $fid = 0,
        public int $tid = 0,
        public int $pid = 0,
        public int $aid = 0,
        public int $eid = 0,
        public int $id = 0,
        public int $page = 1,
        public string $location = '',
        public bool $nopermission = false,
    ) {}
}






function build_friendly_wol_location(UserActivity $activity): string
{
    global $lang, $db, $usernames, $SITENAME, $threads, $forums, $torrents2, $announcements, $attachments, $posts;

    preload_wol_data($activity);
	
	

    return match ($activity->activity) {
        WolActivity::TORRENT => sprintf(
            $lang->online['viewing_torrent2'] ?? 'Viewing Torrent: <a href="%1$s">%2$s</a>',
            get_torrent_link($activity->id),
            $torrents2[$activity->id] ?? 'Unknown Torrent'
        ),

        WolActivity::FORUM => sprintf(
            $lang->online['viewing_forum2'] ?? 'Viewing Forum: <a href="%1$s">%2$s</a>',
            get_forum_link($activity->fid),
            $forums[$activity->fid] ?? 'Forum #' . $activity->fid
        ),

        WolActivity::THREAD => sprintf(
            $lang->online['reading_thread2'] ?? 'Reading Thread: <a href="%1$s">%2$s</a> (Page %3$s)',
            get_thread_link($activity->tid, $activity->page),
            $threads[$activity->tid] ?? 'Unknown Thread',
            $activity->page
        ),

        WolActivity::DOWNLOAD => sprintf(
            $lang->online['downloading_torrent2'] ?? 'Downloading: <a href="%1$s">%2$s</a>',
            get_download_link($activity->id),
            $torrents2[$activity->id] ?? 'Torrent #' . $activity->id
        ),

        WolActivity::PROFILE => sprintf(
            $lang->online['viewing_profile2'] ?? 'Viewing Profile of <a href="%1$s">%2$s</a>',
            get_profile_link($activity->uid),
            $usernames[$activity->uid] ?? 'User'
        ),

        WolActivity::ANNOUNCEMENT => sprintf(
            $lang->online['viewing_announcements'] ?? 'Viewing Announcement: <a href="%1$s">%2$s</a>',
            get_announcement_link($activity->aid),
            $announcements[$activity->aid] ?? 'Announcement'
        ),

       


WolActivity::ATTACHMENT => sprintf(
    $lang->online['viewing_attachment2'] ?? 'Viewing Attachment in <a href="%3$s">%2$s</a> (ID: %1$s)',
    $activity->aid,
    
    $threads[$posts[$attachments[$activity->aid] ?? 0] ?? 0]['subject'] ?? 'Unknown Thread',
    get_thread_link($posts[$attachments[$activity->aid] ?? 0] ?? 0) ?? '#'
),


   

        WolActivity::NEW_THREAD => sprintf(
            $lang->online['posting_thread2'] ?? 'Creating Thread in <a href="%1$s">%2$s</a>',
            get_forum_link($activity->fid),
            $forums[$activity->fid] ?? 'Forum'
        ),

        WolActivity::NEW_REPLY => sprintf(
            $lang->online['replying_thread2'] ?? 'Replying in <a href="%1$s">%2$s</a>',
            get_thread_link($activity->tid),
            $threads[$activity->tid] ?? 'Thread'
        ),

        WolActivity::INDEX => sprintf('%s <a href="index.php">Main Index</a>', $SITENAME),
		
		WolActivity::INDEX2 => sprintf('%s <a href="index2.php">Main Forum</a>', $SITENAME),
		
		WolActivity::MYBONUS => sprintf('%s <a href="mybonus.php">View Bonus Page</a>', $SITENAME),
		
		
		
		WolActivity::UPLOAD => sprintf('%s <a href="upload.php">Uploading Torrent</a>', $SITENAME),
		
		WolActivity::BROWSE => sprintf('%s <a href="browse.php"><a href="browse.php">Viewing Browse Page</a></a>', $SITENAME),
		
		
		
		WolActivity::USERCP               => $lang->online['user_cp'] ?? 'User Control Panel',
WolActivity::USERCP_PROFILE       => $lang->online['updating_profile'] ?? 'Updating Profile',
WolActivity::USERCP_OPTIONS       => $lang->online['updating_options'] ?? 'Updating Options',
WolActivity::USERCP_PASSWORD      => $lang->online['editing_password'] ?? 'Changing Password',
WolActivity::USERCP_EDITSIG       => $lang->online['editing_signature'] ?? 'Editing Signature',
WolActivity::USERCP_AVATAR        => $lang->online['changing_avatar'] ?? 'Changing Avatar',
WolActivity::USERCP_EDITLISTS     => $lang->online['managing_buddyignorelist'] ?? 'Managing Buddy/Ignore List',
WolActivity::USERCP_FAVORITES     => $lang->online['viewing_favorites'] ?? 'Viewing Favorites',
WolActivity::USERCP_SUBSCRIPTIONS => $lang->online['viewing_subscriptions'] ?? 'Viewing Subscriptions',
WolActivity::USERCP_MANAGEFAV     => $lang->online['managing_favorites'] ?? 'Managing Favorites',
WolActivity::USERCP_MANAGESUB     => $lang->online['managing_subscriptions'] ?? 'Managing Subscriptions',
WolActivity::USERCP_NOTEPAD       => $lang->online['editing_pad'] ?? 'Editing Notepad',
		
	
WolActivity::WOL       => $lang->online['viewing_wol'] ?? 'Viewing <a href="online.php">Who\'s Online</a>',
WolActivity::WOLTODAY  => $lang->online['viewing_woltoday'] ?? 'Viewing <a href="online.php?action=today">Who Was Online Today</a>',



WolActivity::PRIVATE          => $lang->online['using_pmsystem'] ?? 'Using PM System',
WolActivity::PRIVATE_SEND     => $lang->online['sending_pm'] ?? 'Sending PM',
WolActivity::PRIVATE_READ     => $lang->online['reading_pm'] ?? 'Reading PM',
WolActivity::PRIVATE_FOLDERS  => $lang->online['editing_pmfolders'] ?? 'Editing PM Folders',




WolActivity::MODCP                  => $lang->online['viewing_modcp'] ?? 'Viewing ModCP',
WolActivity::MODCP_MODLOGS          => $lang->online['viewing_modlogs'] ?? 'Viewing Moderator Logs',
WolActivity::MODCP_ANNOUNCEMENTS    => $lang->online['managing_announcements'] ?? 'Managing Announcements',
WolActivity::MODCP_FINDUSER         => $lang->online['search_for_user'] ?? 'Searching for User',
WolActivity::MODCP_WARNINGLOGS      => $lang->online['managing_warninglogs'] ?? 'Managing Warning Logs',
WolActivity::MODCP_IPSEARCH         => $lang->online['searching_ips'] ?? 'Searching IPs',
WolActivity::MODCP_REPORT           => $lang->online['viewing_reports'] ?? 'Viewing Reports',
WolActivity::MODCP_NEW_ANNOUNCEMENT => $lang->online['adding_announcement'] ?? 'Adding Announcement',
WolActivity::MODCP_DELETE_ANNOUNCEMENT => $lang->online['deleting_announcement'] ?? 'Deleting Announcement',
WolActivity::MODCP_EDIT_ANNOUNCEMENT   => $lang->online['editing_announcement'] ?? 'Editing Announcement',
WolActivity::MODCP_MOD_QUEUE        => $lang->online['managing_modqueue'] ?? 'Managing Moderation Queue',
WolActivity::MODCP_EDITPROFILE      => $lang->online['editing_user_profiles'] ?? 'Editing User Profiles',
WolActivity::MODCP_BANNING          => $lang->online['managing_bans'] ?? 'Managing Bans',


		
		
		
		

        default => $activity->nopermission
            ? ($lang->online['viewing_noperms'] ?? 'No permission')
            : ($lang->online['unknown_location'] ?? 'Unknown location')
    };
}



function preload_wol_data(UserActivity $activity): void
{
    global $db, $usernames, $threads, $forums, $torrents2, $announcements, $attachments, $posts, $fid_list, $tid_list, $uid_list, $aid_list, $id_list, $ann_list;

    $usernames ??= [];
    $threads ??= [];
    $forums ??= [];
    $torrents2 ??= [];
    $announcements ??= [];
    $attachments ??= [];
    $posts ??= [];

    // Users
    if ($activity->uid && !isset($usernames[$activity->uid])) {
        $user = $db->fetch_array($db->sql_query_prepared('SELECT id, username FROM users WHERE id = ?', [(int)$activity->uid]));
        if ($user) $usernames[$user['id']] = htmlspecialchars_uni($user['username']);
    }

    // Threads
    if ($activity->tid && !isset($threads[$activity->tid])) {
        $thread = $db->fetch_array($db->sql_query_prepared('SELECT tid, subject FROM threads WHERE tid = ?', [(int)$activity->tid]));
        if ($thread) $threads[$thread['tid']] = htmlspecialchars_uni($thread['subject']);
    }

    // Forums
    if ($activity->fid && !isset($forums[$activity->fid])) {
        $forum = $db->fetch_array($db->sql_query_prepared('SELECT fid, name FROM forums WHERE fid = ?', [(int)$activity->fid]));
        if ($forum) $forums[$forum['fid']] = htmlspecialchars_uni($forum['name']);
    }

    // Torrents
    if ($activity->id && !isset($torrents2[$activity->id])) {
        $torrent = $db->fetch_array($db->sql_query_prepared('SELECT id, name FROM torrents WHERE id = ?', [(int)$activity->id]));
        if ($torrent) $torrents2[$torrent['id']] = htmlspecialchars_uni($torrent['name']);
    }

    // Announcements
    if ($activity->aid && !isset($announcements[$activity->aid])) {
        $ann = $db->fetch_array($db->sql_query_prepared('SELECT id, subject FROM announcements WHERE id = ?', [(int)$activity->aid]));
        if ($ann) $announcements[$ann['id']] = htmlspecialchars_uni($ann['subject']);
    }

    
	
	
	
		// === ATTACHMENT: aid → pid → tid → thread (на лету) ===
if ($activity->activity === WolActivity::ATTACHMENT && $activity->aid) {
    $aid = (int)$activity->aid;

    // 1. Получаем pid из attachments
    if (!isset($attachments[$aid])) {
        $att = $db->fetch_array($db->sql_query_prepared('SELECT pid FROM attachments WHERE aid = ?', [$aid]));
        if ($att) {
            $attachments[$aid] = (int)$att['pid'];
        }
    }

    // 2. Получаем tid из posts
    if (isset($attachments[$aid])) {
        $pid = (int)$attachments[$aid];
        if (!isset($posts[$pid])) {
            $post = $db->fetch_array($db->sql_query_prepared('SELECT tid FROM posts WHERE pid = ?', [$pid]));
            if ($post) {
                $posts[$pid] = (int)$post['tid'];
            }
        }
    }

    // 3. Получаем subject из threads
    if (isset($posts[$pid])) {
        $tid = (int)$posts[$pid];
        if (!isset($threads[$tid])) {
            $thread = $db->fetch_array($db->sql_query_prepared('SELECT subject FROM threads WHERE tid = ?', [$tid]));
            if ($thread) {
                $threads[$tid] = ['subject' => $thread['subject']];
            }
        }
    }
}
// === КОНЕЦ ATTACHMENT ===
	
	
	
	
	
	
	
	
	
	
	
	
	
	
}









function build_wol_row(array $user): string
{
    global $mybb, $db, $lang, $CURUSER, $usergroups;

    $is_visible = $user['invisible'] != 1 
        || $usergroups['canstaffpanel'] == '1' 
        || ($user['uid'] ?? 0) == $CURUSER['id'];
    
    if (!$is_visible) return '';

    $invisible = $user['invisible'] == 1 ? '*' : '';
    
    $uid = (int)($user['uid'] ?? 0);  // ← ЯВНОЕ ПРИВЕДЕНИЕ К int

    $username = $uid > 0
        ? format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup'])
        : ($user['bot'] ?? 'Guest');

    $profile_link = $uid > 0 
        ? build_profile_link($username, $uid) . $invisible 
        : $username;

    $time = my_datee('relative', $user['time']);
    $location = build_friendly_wol_location($user['activity']);

    $ip = '';
    if ($usergroups['canstaffpanel'] == '1' && !empty($user['ip']) && $db !== null) {
        $ip_binary = $db->unescape_binary($user['ip']);
        $ip = '<small class="text-muted">IP: ' . htmlspecialchars(my_inet_ntop($ip_binary)) . '</small>';
    }

    return <<<HTML
    <div class="col-lg-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>$profile_link</strong><br>
                        $ip
                    </div>
                    <small class="text-muted">$time</small>
                </div>
                <small class="text-primary mt-1 d-block">$location</small>
            </div>
        </div>
    </div>
    HTML;
}







/**
 * Parse URL and determine user activity
 */
function fetch_wol_activity(string $location, bool $nopermission = false): UserActivity
{
    global $user, $parameters;

    // Parse URL
    [$file, $query] = explode('?', $location . '?');
    $file = basename($file, '.php');
    $params = parse_query_string($query ?? '');

    if ($nopermission) {
        $file = 'nopermission';
    }

    // Default activity
    $activity = match ($file) {
        'announcements' => handle_announcement($params),
        'attachment'    => handle_attachment($params),
        'calendar'      => handle_calendar($params),
        'forumdisplay'  => handle_forum($params),
        'member'        => handle_member($params),
        'misc'          => handle_misc($params),
        'modcp'         => handle_modcp($params),
        'newreply'      => handle_newreply($params),
        'newthread'     => handle_newthread($params),
        'online'        => handle_online($params),
        'polls'         => handle_polls($params),
        'printthread'   => handle_printthread($params),
        'private'       => handle_private($params),
        'reputation'    => handle_reputation($params),
        'showthread'    => handle_showthread($params),
        'usercp'        => handle_usercp($params),
        'details'       => handle_details($params),
        'download'      => handle_download($params),
        'index'         => WolActivity::INDEX,
        'index2'        => WolActivity::INDEX2,
		'mybonus'        => WolActivity::MYBONUS,
        'upload'        => WolActivity::UPLOAD,
        'browse'        => WolActivity::BROWSE,
        'nopermission'  => WolActivity::UNKNOWN,
        default         => WolActivity::UNKNOWN,
    };
	
	

    return new UserActivity(
    activity: $activity,
    uid: $params['uid'] ?? $params['id'] ?? 0,  // ← ДОБАВЬ $params['id'] ?? 
    fid: $params['fid'] ?? 0,
    tid: $params['tid'] ?? 0,
    pid: $params['pid'] ?? 0,
    aid: $params['aid'] ?? 0,
    eid: $params['eid'] ?? 0,
    id:  $params['id'] ?? 0,
    page: $params['page'] ?? 1,
    location: $location,
    nopermission: $nopermission
	
	
	
);


error_log("PARSED PARAMS: " . print_r($params, true));

error_log("PARAMS DEBUG: " . print_r($params, true));
error_log("UID FINAL: " . ($params['uid'] ?? $params['id'] ?? 0));

}

// === Handlers ===
function handle_announcement(array $p): WolActivity { global $ann_list; $ann_list[$p['aid'] ?? 0] = (int)($p['aid'] ?? 0); return WolActivity::ANNOUNCEMENT; }
function handle_attachment(array $p): WolActivity { global $aid_list; $aid_list[] = (int)($p['aid'] ?? 0); return WolActivity::ATTACHMENT; }
function handle_forum(array $p): WolActivity { global $fid_list; $fid_list[(int)($p['fid'] ?? 0)] = (int)($p['fid'] ?? 0); return WolActivity::FORUM; }
function handle_newthread(array $p): WolActivity { global $fid_list; $fid_list[(int)($p['fid'] ?? 0)] = (int)($p['fid'] ?? 0); return WolActivity::NEW_THREAD; }
function handle_newreply(array $p): WolActivity { global $tid_list; $tid_list[(int)($p['tid'] ?? 0)] = (int)($p['tid'] ?? 0); return WolActivity::NEW_REPLY; }
function handle_showthread(array $p): WolActivity { global $tid_list, $pid_list; $tid_list[(int)($p['tid'] ?? 0)] = (int)($p['tid'] ?? 0); if (($p['action'] ?? '') === 'showpost') { $pid_list[(int)($p['pid'] ?? 0)] = (int)($p['pid'] ?? 0); return WolActivity::POST; } return WolActivity::THREAD; }
function handle_printthread(array $p): WolActivity { global $tid_list; $tid_list[(int)($p['tid'] ?? 0)] = (int)($p['tid'] ?? 0); return WolActivity::THREAD; }

function handle_online(array $p): WolActivity
{
    return match ($p['action'] ?? '') {
        'today' => WolActivity::WOLTODAY,
        default => WolActivity::WOL,
    };
}


function handle_reputation(array $p): WolActivity { global $uid_list; $uid_list[(int)($p['id'] ?? 0)] = (int)($p['id'] ?? 0); return WolActivity::PROFILE; }





function handle_details(array $p): WolActivity
{
    global $id_list;
    $id_list[(int)($p['id'] ?? 0)] = (int)($p['id'] ?? 0);
    return WolActivity::TORRENT;  // ← ВОЗВРАЩАЙ TORRENT!
}

function handle_download(array $p): WolActivity
{
    global $id_list;
    $id_list[(int)($p['id'] ?? 0)] = (int)($p['id'] ?? 0);
    return WolActivity::DOWNLOAD;  // ← если нужно
}





function handle_calendar(array $p): WolActivity
{
    global $eid_list;
    return match ($p['action'] ?? '') {
        'event' => ($eid_list[(int)($p['eid'] ?? 0)] = (int)($p['eid'] ?? 0)) ? WolActivity::INDEX : WolActivity::INDEX,
        'addevent', 'do_addevent' => WolActivity::INDEX,
        'editevent', 'do_editevent' => WolActivity::INDEX,
        default => WolActivity::INDEX,
    };
}

function handle_member(array $p): WolActivity
{
    global $uid_list;
    return match ($p['action'] ?? '') {
        'profile' => ($uid_list[(int)($p['id'] ?? 0)] = (int)($p['id'] ?? 0)) ? WolActivity::PROFILE : WolActivity::PROFILE,
        'register', 'do_register' => WolActivity::PROFILE,
        'login', 'do_login' => WolActivity::PROFILE,
        'logout' => WolActivity::PROFILE,
        default => WolActivity::PROFILE,
    };
}



function handle_modcp(array $p): WolActivity
{
    $action = $p['action'] ?? '';

    return match ($action) {
        'modlogs'           => WolActivity::MODCP_MODLOGS,
        'announcements'     => WolActivity::MODCP_ANNOUNCEMENTS,
        'finduser'          => WolActivity::MODCP_FINDUSER,
        'warninglogs'       => WolActivity::MODCP_WARNINGLOGS,
        'ipsearch'          => WolActivity::MODCP_IPSEARCH,
        'report'            => WolActivity::MODCP_REPORT,
        'new_announcement'  => WolActivity::MODCP_NEW_ANNOUNCEMENT,
        'delete_announcement' => WolActivity::MODCP_DELETE_ANNOUNCEMENT,
        'edit_announcement'   => WolActivity::MODCP_EDIT_ANNOUNCEMENT,
        'mod_queue'         => WolActivity::MODCP_MOD_QUEUE,
        'editprofile'       => WolActivity::MODCP_EDITPROFILE,
        'banning'           => WolActivity::MODCP_BANNING,
        default             => WolActivity::MODCP,
    };
}


function handle_usercp(array $p): WolActivity
{
    $action = $p['action'] ?? '';

    return match ($action) {
        'profile', 'do_profile'                 => WolActivity::USERCP_PROFILE,
        'options', 'do_options'                 => WolActivity::USERCP_OPTIONS,
        'password', 'do_password'               => WolActivity::USERCP_PASSWORD,
        'editsig', 'do_editsig'                 => WolActivity::USERCP_EDITSIG,
        'avatar', 'do_avatar'                   => WolActivity::USERCP_AVATAR,
        'editlists', 'do_editlists'             => WolActivity::USERCP_EDITLISTS,
        'favorites'                             => WolActivity::USERCP_FAVORITES,
        'subscriptions'                         => WolActivity::USERCP_SUBSCRIPTIONS,
        'addfavorite', 'removefavorite', 'removefavorites' => WolActivity::USERCP_MANAGEFAV,
        'addsubscription', 'do_addsubscription', 'removesubscription', 'removesubscriptions' => WolActivity::USERCP_MANAGESUB,
        'notepad', 'do_notepad'                 => WolActivity::USERCP_NOTEPAD,
        default                                 => WolActivity::USERCP,
    };
}

function handle_polls(array $p): WolActivity
{
    return match ($p['action'] ?? '') {
        'do_newpoll' => WolActivity::INDEX,
        'do_editpoll' => WolActivity::INDEX,
        default => in_array($p['action'] ?? '', ['newpoll', 'editpoll', 'showresults', 'vote'], true) ? WolActivity::INDEX : WolActivity::INDEX,
    };
}

function handle_private(array $p): WolActivity
{
    $action = $p['action'] ?? '';

    return match ($action) {
        'send', 'do_send'       => WolActivity::PRIVATE_SEND,
        'read'                  => WolActivity::PRIVATE_READ,
        'folders', 'do_folders' => WolActivity::PRIVATE_FOLDERS,
        default                 => WolActivity::PRIVATE,
    };
}

function handle_misc(array $p): WolActivity
{
    global $tid_list;
    if (($p['action'] ?? '') === 'whoposted') {
        $tid_list[(int)($p['tid'] ?? 0)] = (int)($p['tid'] ?? 0);
    }
    return WolActivity::INDEX;
}

// === Helpers ===
function parse_query_string(string $query): array
{
    // Убираем &amp; → &
    $query = str_replace('&amp;', '&', $query);

    $params = [];
    parse_str($query, $params);

    // Приводим числовые параметры к int
    $int_keys = ['uid', 'fid', 'tid', 'pid', 'aid', 'eid', 'id', 'page'];
    foreach ($int_keys as $key) {
        if (isset($params[$key])) {
            $params[$key] = (int)$params[$key];
        }
    }

    // Экранируем строки
    return array_map(fn($v) => is_string($v) ? htmlspecialchars($v) : $v, $params);
}