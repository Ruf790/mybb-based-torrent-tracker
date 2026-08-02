<?php


declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'online.php');
define("SCRIPTNAME", "online.php");



define('IN_FORUM', true);
require_once 'global.php';



require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . "/functions_post.php";
require_once INC_PATH . "/functions_online.php";

// Load global language phrases
$lang->load("online");

// Make navigation
add_breadcrumb($lang->online['nav_online'], "online.php");

if($mybb->get_input('action') == "today")
{
    add_breadcrumb($lang->online['nav_onlinetoday']);

    $plugins->run_hooks("online_today_start");

    $threshold = TIMENOW - (60 * 60 * 24);
    $query = $db->sql_query_prepared(
        "SELECT COUNT(id) AS users FROM users WHERE lastactive > ?",
        [$threshold]
    );
    $todaycount = (int)$db->fetch_field($query, "users");

    $query = $db->sql_query_prepared(
        "SELECT COUNT(id) AS users FROM users WHERE lastactive > ? AND invisible = 1",
        [$threshold]
    );
    $invis_count = (int)$db->fetch_field($query, "users");

    
    
    if(!$ts_perpage || (int)$ts_perpage < 1)
    {
        $ts_perpage = 20;
    }

    // Add pagination
    $perpage = (int)$ts_perpage;

    if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
    {
        $page = $mybb->get_input('page', MyBB::INPUT_INT);
        $start = ($page - 1) * $perpage;
        $pages = $todaycount > 0 ? (int)ceil($todaycount / $perpage) : 1;
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

    $query = $db->sql_query_prepared(
        "SELECT * FROM users WHERE lastactive > ? ORDER BY lastactive DESC LIMIT ?, ?",
        [$threshold, $start, $perpage]
    );

    $todayrows = '';
    while($online = $db->fetch_array($query))
    {
        $invisiblemark = '';
        if(isset($online['invisible']) && $online['invisible'] == 1)
        {
            $invisiblemark = "*";
        }

        if(!isset($online['invisible']) || $online['invisible'] != 1 || 
           (isset($usergroups['canviewwolinvis']) && $usergroups['canviewwolinvis'] == 1) || 
           (isset($online['id']) && isset($CURUSER['id']) && $online['id'] == $CURUSER['id']))
        {
            $username = format_name(htmlspecialchars_uni($online['username'] ?? ''), 
                                  $online['usergroup'] ?? '', 
                                  $online['displaygroup'] ?? '');
            $online['profilelink'] = build_profile_link($username, $online['id'] ?? 0);
            $onlinetime = my_datee('normal', $online['lastactive'] ?? TIMENOW);

            $todayrows .= '
			<div class="col-lg-6 mb-3">
    <div class="card card-user-online h-100">
        <div class="card-body d-flex align-items-center p-3">
            <div class="user-avatar me-3">
                <img src="'.$online['avatar'].'" alt="'.$online['username'].'" class="rounded-circle" width="60" height="60">
            </div>
            <div class="user-info flex-grow-1">
                <h6 class="mb-1 fw-semibold">'.$online['profilelink'].''.$invisiblemark.'</h6>
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock me-1"></i>'.$onlinetime.'
                </p>
            </div>
        </div>
    </div>
</div>';
			
			
			
			
			
        }
    }

    $multipage = multipage($todaycount, $perpage, $page, "online.php?action=today");

    $todaycount_display = ts_nf($todaycount);
    $invis_count_display = ts_nf($invis_count);

    if($todaycount == 1)
    {
        $onlinetoday = $lang->online['member_online_today'];
    }
    else
    {
        $onlinetoday = sprintf($lang->online['members_were_online_today'], $todaycount_display);
    }

    if($invis_count > 0)
    {
        $string = $lang->online['members_online_hidden'];

        if($invis_count == 1)
        {
            $string = $lang->online['member_online_hidden'];
        }

        $onlinetoday .= sprintf($string, $invis_count_display);
    }

    $plugins->run_hooks("online_today_end");

    stdhead($lang->online['online_today']);
    
    build_breadcrumb();
    
    $today = '
	
	<html>
<head>
<title>'.$SITENAME.' - '.$lang->online['online_today'].'</title>

</head>
<body>
	<div class="container-md">
		<div class="card border-0 mb-4">
	
		<div class="row g-2">
		'.$todayrows.'
		</div>
			
		</div>
		
		'.$multipage.'
	</div>
</body>
</html>';
	
	
	
	
	
	
    
    echo $today;
    
    stdfoot();
}
else
{
    $plugins->run_hooks("online_start");

    // Custom sorting options
    $sql = "IF(s.uid > 0, 1, 0) DESC, s.time DESC";
    $refresh_string = '';

    if($mybb->get_input('sortby') == "username")
    {
        $sql = "u.username ASC, s.time DESC";
        $refresh_string = "?sortby=username";
    }
    elseif($mybb->get_input('sortby') == "location")
    {
        $sql = "s.location, s.time DESC";
        $refresh_string = "?sortby=location";
    }

    $timesearch = TIMENOW - ($wolcutoffmins ?? 15) * 60;

    // Get online count
    $query = $db->sql_query_prepared(
        "SELECT COUNT(DISTINCT CONCAT(COALESCE(uid, 0), '-', ip)) AS online 
         FROM sessions 
         WHERE time > ?",
        [$timesearch]
    );

    $online_count = (int)$db->fetch_field($query, "online");

    if(!isset($f_threadsperpage) || (int)($f_threadsperpage ?? 20) < 1)
    {
        $f_threadsperpage = 20;
    }

    // How many pages are there?
    $perpage = (int)$f_threadsperpage;

    if($mybb->get_input('page', MyBB::INPUT_INT) > 0)
    {
        $page = $mybb->get_input('page', MyBB::INPUT_INT);
        $start = ($page - 1) * $perpage;
        $pages = $online_count > 0 ? (int)ceil($online_count / $perpage) : 1;
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

    // Assemble page URL
    $multipage = multipage($online_count, $perpage, $page, "online.php" . $refresh_string);

    // Query for active sessions with PHP 8.4 compatibility
    $dbversion = $db->get_version();
    
    // Simplified query for better compatibility
    $query = $db->sql_query_prepared(
        "SELECT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, 
                u.invisible, u.usergroup, u.displaygroup
         FROM sessions s
         LEFT JOIN users u ON (s.uid = u.id)
         WHERE s.time > ?
         ORDER BY $sql
         LIMIT ?, ?",
        [$timesearch, $start, $perpage]
    );

    // Fetch spiders
    $spiders = $cache->read("spiders") ?? [];

    $users = [];
    $guests = [];

    while($user = $db->fetch_array($query))
    {
        $plugins->run_hooks("online_user");

        // Fetch the WOL activity
        //$user['activity'] = fetch_wol_activity($user['location'] ?? '', $user['nopermission'] ?? 0);
		
		
		$user['activity'] = fetch_wol_activity(
    $user['location'] ?? '',
    (bool)($user['nopermission'] ?? 0)
);
		
		
		
		
		

        $botkey = strtolower(str_replace("bot=", '', $user['sid'] ?? ''));

        // Have a registered user
        if(($user['uid'] ?? 0) > 0)
        {
            if(!isset($users[$user['uid']]) || ($users[$user['uid']]['time'] ?? 0) < ($user['time'] ?? 0))
            {
                $users[$user['uid']] = $user;
            }
        }
        // Otherwise this session is a bot
        elseif(isset($user['sid']) && str_contains($user['sid'], "bot=") && isset($spiders[$botkey]))
        {
            $user['bot'] = $spiders[$botkey]['name'] ?? '';
            $user['usergroup'] = $spiders[$botkey]['usergroup'] ?? '';
            $guests[] = $user;
        }
        // Or a guest
        else
        {
            $guests[] = $user;
        }
    }

    // Now we build the actual online rows
    $online_rows = '';
    
    // Process registered users
    foreach($users as $user)
    {
        $online_rows .= build_wol_row($user);
    }
    
    // Process guests and bots
    foreach($guests as $user)
    {
        $online_rows .= build_wol_row($user);
    }

    // Fetch the most online information
    $most_online = $cache->read("mostonline") ?? ['numusers' => 0, 'time' => TIMENOW];
    $record_count = (int)($most_online['numusers'] ?? 0);
    $record_date = my_datee('relative', $most_online['time'] ?? TIMENOW);

    // Set automatic refreshing if enabled
    $refreshwol = "1";
    
    $refresh = '';
    if($refreshwol > 0)
    {
        $refresh_time = (int)$refreshwol * 60;
        
        $refresh = '
		
		<meta http-equiv="refresh" content="'.$refresh_time.';URL=online.php'.$refresh_string.'" />
		
		';
		
		
    }

    $plugins->run_hooks("online_end");

    stdhead($lang->online['users_online']);
    
    build_breadcrumb();
    
     $online = '
	 
	 <html>
<head>
<title>'.$SITENAME.' - '.$lang->online['users_online'].'</title>

'.$refresh.'
</head>
<body>

	
	<div class="container-md">
		
<div class="card bg-nav border-0 mb-2">
<div class="card-body text-center">
	
	<div class="row">
		<div class="col-4 text-lg-start">
            <a href="online.php?sortby=username"><i class="fa-solid fa-user"></i> &nbsp;'.$lang->online['on_username'].'</a>
		</div>
		<div class="col-4 text-lg-center">
			<a href="online.php?sortby=time"><i class="fa-solid fa-clock"></i> &nbsp;'.$lang->online['time'].'</a>
		</div>
		<div class="col-4 text-lg-end">
			</a><a href="online.php?sortby=location"><i class="fa-solid fa-location-dot"></i> &nbsp;'.$lang->online['location'].'</a>	
		</div></div>
		</div></div>
	
	<div class="card border-0">
		
	
	
	
		<div class="row g-2">
		'.$online_rows.'
	
	
			
	
		</div>
		<div class="card-footer border-top-0 rounded text-center">
			<div class="row g-3">
				<div class="col-lg-6 text-lg-end">
		<a href="online.php?action=today" class="links"><i class="fa-solid fa-globe"></i> &nbsp;'.$lang->online['online_today'].'</a>
				</div>
				<div class="col-lg-6 text-lg-start">
					<a href="online.php" class="links"><i class="fa-solid fa-rotate-right"></i> &nbsp;'.$lang->online['refresh_page'].'</a>
				</div>
			</div>
		</div>
	</div>
		
	</div>
	'.$multipage.'
	
	
</body>
</html>';
	 
	
	 
	 
    
    echo $online;
    
    stdfoot();
}