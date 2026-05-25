<?php
declare(strict_types=1);


define('IN_MYBB',    1);
define('THIS_SCRIPT', 'index2.php');
define('SCRIPTNAME',  'index2.php');
define('IN_FORUM',    true);



require_once 'global.php';
require_once INC_PATH . '/functions_forumlist.php';

$lang->load('index');
$lang->load('usercp');

$plugins->run_hooks('index_start');

/* ── Logout link ─────────────────────────────────────────────────────── */
$logoutlink = '';
if (($CURUSER['id'] ?? 0) != 0) {
    
     $logoutlink = '<a class="float-left" href="member.php?action=logout&amp;logoutkey='.$CURUSER['logoutkey'].'"><i class="bi bi-box-arrow-right"></i> '.$lang->global['index_logout'].'</a>';
}

/* ── Who's Online ────────────────────────────────────────────────────── */
$timesearch  = TIMENOW - (int)$wolcutoffmins;
$membercount = $guestcount = $anoncount = $botcount = 0;
$forum_viewers = $doneusers = $onlinemembers = $onlinebots = [];

// Forum viewer counts (guests per forum)
$q = $db->sql_query("
    SELECT location1, COUNT(DISTINCT ip) AS guestcount
    FROM sessions
    WHERE uid = 0 AND location1 != 0 AND SUBSTR(sid,4,1) != '=' AND time > {$timesearch}
    GROUP BY location1
");
while ($loc = $db->fetch_array($q)) {
    $forum_viewers[$loc['location1']] = ($forum_viewers[$loc['location1']] ?? 0) + $loc['guestcount'];
}

// Total guest count
$q = $db->simple_select('sessions', 'COUNT(DISTINCT ip) AS guestcount',
    "uid = 0 AND SUBSTR(sid,4,1) != '=' AND time > {$timesearch}");
$guestcount = (int)$db->fetch_field($q, 'guestcount');

// Members & bots
$q = $db->sql_query("
    SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1,
           u.username, u.invisible, u.usergroup, u.displaygroup
    FROM sessions s
    LEFT JOIN users u ON s.uid = u.id
    WHERE (s.uid != 0 OR SUBSTR(s.sid,4,1) = '=') AND s.time > {$timesearch}
    ORDER BY u.username ASC, s.time DESC
");

$spiders         = $cache->read('spiders');
$woldisplayspiders = '1';

while ($user = $db->fetch_array($q)) {
    $botkey = my_strtolower(str_replace('bot=', '', $user['sid']));

    if ($user['uid'] > 0) {
        if (empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time']) {
            if ($user['invisible'] == 1) ++$anoncount;
            ++$membercount;

            $can_see = $user['invisible'] != 1
                || $usergroups['canviewwolinvis'] == 1
                || $user['uid'] == $CURUSER['id'];

            if ($can_see) {
                $invisiblemark = $user['invisible'] == 1 ? '*' : '';
                $user['username']    = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                $user['profilelink'] = build_profile_link($user['username'], $user['uid']);
                
				$onlinemembers[] = ''.$user['profilelink'].' '.$invisiblemark.'';
				
				
            }
            $doneusers[$user['uid']] = $user['time'];
        }
    } elseif (str_contains($user['sid'], 'bot=') && !empty($spiders[$botkey]) && $woldisplayspiders == 1) {
        $onlinebots[$spiders[$botkey]['name']] = format_name($spiders[$botkey]['name'], $spiders[$botkey]['usergroup']);
        ++$botcount;
    }

    if ($user['location1']) {
        $forum_viewers[$user['location1']] = ($forum_viewers[$user['location1']] ?? 0) + 1;
    }
}

ksort($onlinebots);
$onlinemembers = array_merge($onlinebots, $onlinemembers);
$onlinemembers = !empty($onlinemembers)
    ? implode($lang->global['comma'] . ' ', $onlinemembers)
    : '';

$onlinecount = $membercount + $guestcount + $botcount;

// Pluralisation helpers
$onlinebit = $onlinecount != 1  ? 'users'   : 'user';
$memberbit = $membercount != 1  ? 'members' : 'member';
$anonbit   = $anoncount   != 1  ? 'are'     : 'is';
$guestbit  = $guestcount  != 1  ? 'guests'  : 'guest';

$online_note = sprintf(
    '%s %s active in the past %s minutes (%s %s, %s of whom %s invisible, and %s %s).',
    ts_nf($onlinecount), $onlinebit, $wolcutoffmins,
    ts_nf($membercount), $memberbit,
    ts_nf($anoncount),   $anonbit,
    ts_nf($guestcount),  $guestbit
);

$whosonline = '';

$whosonline = '<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-gradient-primary bg-opacity-10 border-0 py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="bg-info rounded-circle p-2 me-3">
                <i class="fa-solid fa-users text-white fs-6"></i>
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-0 fw-bold text-info"> Online Now</h6>
            </div>
            <span class="badge bg-info rounded-pill px-3">
                '.$online_note.'
            </span>
        </div>
    </div>
    
    <div class="card-body p-4">
        <div class="online-status mb-3">
            <div class="d-flex align-items-center mb-2">
                <i class="fa-solid fa-circle text-success fa-xs me-2"></i>
                <small class="text-muted">Active in last 5 minutes</small>
            </div>
        </div>
        
        <div class="online-list">
            <div class="mt-2">'.$onlinemembers.'</div>
        </div>
        
        <!-- Дополнительная информация -->
        <div class="mt-3 pt-3 border-top">
            <div class="row text-center">
                <div class="col-4">
                    <div class="text-primary fw-bold">-</div>
                    <small class="text-muted">Members</small>
                </div>
                <div class="col-4">
                    <div class="text-warning fw-bold">-</div>
                    <small class="text-muted">Guests</small>
                </div>
                <div class="col-4">
                    <div class="text-success fw-bold">-</div>
                    <small class="text-muted">Bots</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Минималистичный стиль для списка онлайн пользователей -->
<style>
.online-list a {
    display: inline-block;
    background: #f8f9fa;
    padding: 4px 12px;
    margin: 2px;
    border-radius: 20px;
    text-decoration: none;
    color: #495057;
    font-size: 0.875rem;
    transition: all 0.2s;
    border: 1px solid #e9ecef;
}

.online-list a:hover {
    background: #e9ecef;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
</style>';



/* ── Birthdays ───────────────────────────────────────────────────────── */
$bdays = $birthdays = '';
$bdaycount = $bdayhidden = $hiddencount = 0;
$bdaydate   = my_datee('j-n', TIMENOW, '', 0);
$year       = (int)my_datee('Y', TIMENOW, '', 0);

$bdaycache = $cache->read('birthdays');
if (!is_array($bdaycache)) {
    $cache->update_birthdays();
    $bdaycache = $cache->read('birthdays');
}

$today_bdays = $bdaycache[$bdaydate]['users']      ?? [];
$hiddencount = $bdaycache[$bdaydate]['hiddencount'] ?? 0;

if (!empty($today_bdays)) {
    $showbirthdayspostlimit = 0;

    if ($showbirthdayspostlimit > 0) {
        $bday_sql = implode(',', array_column($today_bdays, 'id'));
        if ($bday_sql) {
            $q = $db->simple_select('users', 'id, postnum', "id IN ({$bday_sql})");
            while ($bu = $db->fetch_array($q)) {
                if ($bu['postnum'] < $showbirthdayspostlimit) {
                    $today_bdays = array_filter($today_bdays, fn($u) => $u['id'] != $bu['id']);
                }
            }
        }
    }

    $comma = '';
    foreach ($today_bdays as $bdayuser) {
        $bdayuser['displaygroup'] = $bdayuser['displaygroup'] ?: $bdayuser['usergroup'];
        if (isset($groupscache[$bdayuser['displaygroup']])) continue;

        $bday = explode('-', $bdayuser['birthday']);
        $age  = ($year > (int)($bday[2] ?? 0) && !empty($bday[2]))
            ? ' (' . ($year - (int)$bday[2]) . ')'
            : '';

        $bdayuser['username']    = format_name(htmlspecialchars_uni($bdayuser['username']), $bdayuser['usergroup'], $bdayuser['displaygroup']);
        $bdayuser['profilelink'] = build_profile_link($bdayuser['username'], $bdayuser['id']);
        $bdays .= $comma . $bdayuser['profilelink'] . $age;
        ++$bdaycount;
        $comma = ', ';
    }
}

if ($hiddencount > 0) {
    $bdays .= ($bdaycount > 0 ? ' - ' : '') . "{$hiddencount} {$lang->birthdayhidden}";
}

if ($bdaycount > 0 || $hiddencount > 0) {
    $birthdays = '<div class="row mt-3">
        <div class="col align-self-center text-desc">
            <i class="fa-solid fa-cake-candles me-1"></i> ' . $lang->todays_birthdays . ' — ' . $bdays . '
        </div>
    </div>';
}

/* ── Board Statistics ────────────────────────────────────────────────── */
$forumstats = $boardstats = '';

$stats = $cache->read('stats');

$newestmember = !empty($stats['lastusername'])
    ? build_profile_link($stats['lastusername'], $stats['lastuid'])
    : 'nobody';

$stats_posts_threads = sprintf($lang->tsf_forums['stats_posts_threads'], ts_nf($stats['numposts']), ts_nf($stats['numthreads']));
$stats_numusers      = sprintf($lang->tsf_forums['stats_numusers'],      ts_nf($stats['numusers']));
$stats_newestuser    = sprintf($lang->tsf_forums['stats_newestuser'],    $newestmember);

$onlinestats = [];
if (file_exists(TSDIR . '/cache/onlinestats.php')) {
    include_once TSDIR . '/cache/onlinestats.php';
}

$stats_mostonline = sprintf(
    $lang->index['online'],
    ts_nf($onlinestats['most_ever']      ?? 0),
    my_datee($dateformat, $onlinestats['most_ever_time'] ?? 0),
    my_datee($timeformat, $onlinestats['most_ever_time'] ?? 0)
);


$forumstats = '<div class="d-flex align-items-left justify-content-between px-3">
            <h5 class="mb-0 text-white">
                <i class="fa-solid fa-chart-pie me-2"></i>Forum Stats
            </h5>
            <a href="'.$BASEURL.'/stats.php" class="text-white text-decoration-none small">
                <i class="fa-solid fa-expand me-1"></i> Details
            </a>
        </div>
    </div>
    
    <div class="card-body p-3">
        <div class="row g-3">
            <!-- Posts & Threads -->
            <div class="col-12">
                <div class="d-flex align-items-left p-2 rounded-3 bg-light">
                    <div class="bg-primary bg-opacity-10 rounded-2 p-2 me-3">
                        <i class="fa-solid fa-message text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small text-muted">Posts/Threads</div>
                        <div class="fw-bold">'.$stats_posts_threads.'</div>
                    </div>
                </div>
            </div>
            
            <!-- Members -->
            <div class="col-12">
                <div class="d-flex align-items-left p-2 rounded-3 bg-light">
                    <div class="bg-success bg-opacity-10 rounded-2 p-2 me-3">
                        <i class="fa-solid fa-user-group text-success"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small text-muted">Total Members</div>
                        <div class="fw-bold">'.$stats_numusers.'</div>
                    </div>
                </div>
            </div>
            
            <!-- Latest Member -->
            <div class="col-12">
                <div class="d-flex align-items-left p-2 rounded-3 bg-light">
                    <div class="bg-info bg-opacity-10 rounded-2 p-2 me-3">
                        <i class="fa-solid fa-user-plus text-info"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small text-muted">Latest Member</div>
                        <div class="fw-bold text-truncate">'.$stats_newestuser.'</div>
                    </div>
                </div>
            </div>
            
            <!-- Peak Online -->
            <div class="col-12">
                <div class="d-flex align-items-left p-2 rounded-3 bg-light">
                    <div class="bg-warning bg-opacity-10 rounded-2 p-2 me-3">
                        <i class="fa-solid fa-users-viewfinder text-warning"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small text-muted">Peak Online</div>
                        <div class="fw-bold">'.$stats_mostonline.'</div>
                    </div>
                </div>
            </div>
        </div>';
    



$boardstats = '<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-gradient-primary bg-opacity-10 border-0 py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-3">
                <i class="fa-solid fa-chart-line text-primary fs-5"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-0">
                    <a href="'.$BASEURL.'/stats.php" class="text-decoration-none text-dark fw-semibold">
                        Board Statistics
                    </a>
                </h5>
                <small class="text-muted">Real-time forum insights</small>
            </div>
            <div class="ms-auto">
                <i class="fa-solid fa-chevron-right text-muted"></i>
            </div>
        </div>
    </div>
    
    <div class="card-body p-4">
        <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
            <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                <i class="fa-solid fa-users text-info fs-6"></i>
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-1 fw-semibold">Community Activity</h6>
                <div class="text-muted small">
                    '.$forumstats.'
                </div>
            </div>
        </div>
        
        <div class="row g-3 mt-2">
            <div class="col-6">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-2 p-2 me-2">
                        <i class="fa-solid fa-message text-success fs-6"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Total Posts</small>
                        <span class="fw-semibold">-</span>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 rounded-2 p-2 me-2">
                        <i class="fa-solid fa-user-plus text-warning fs-6"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">New Members</small>
                        <span class="fw-semibold">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-footer bg-transparent border-0 py-3 px-4 text-center">
        <a href="'.$BASEURL.'/stats.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">
            <i class="fa-solid fa-chart-simple me-2"></i>View Full Statistics
        </a>
    </div>
</div>';






/* ── Forum list ──────────────────────────────────────────────────────── */
if (($CURUSER['id'] ?? 0) == 0) {
    $q = $db->simple_select('forums', '*', 'active!=0', ['order_by' => 'pid, disporder']);
    $forumsread = [];
    if (isset($mybb->cookies['mybb']['forumread'])) {
        $forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
    }
} else {
    $q = $db->sql_query("
        SELECT f.*, fr.dateline AS lastread
        FROM forums f
        LEFT JOIN forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$CURUSER['id']}')
        WHERE f.active != 0
        ORDER BY pid, disporder
    ");
}

$fcache = [];

while ($forum = $db->fetch_array($q)) {
    if (($CURUSER['id'] ?? 0) == 0 && !empty($forumsread[$forum['fid']])) {
        $forum['lastread'] = $forumsread[$forum['fid']];
    }
    $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}


$forumpermissions = forum_permissions();
$moderatorcache   = $cache->read('moderators');
$excols           = 'index';
$permissioncache  = null;
$bgcolor          = 'trow1';
$showdepth        = '2' != '0' ? 3 : 2;   // subforumsindex = "2"

$forum_list = build_forumbits();
$forums     = $forum_list['forum_list'];


$plugins->run_hooks('index_end');

/* ── DST auto-detection ──────────────────────────────────────────────── */
$auto_dst_detection = '';
if ($CURUSER['id'] > 0 && $CURUSER['dstcorrection'] == 2) {
    $timezone = (float)$CURUSER['timezone'] + $CURUSER['dst'];
    
	$auto_dst_detection = '<script>
    var USER_TIMEZONE = "'.$timezone.'";
</script>

<script src="'.$BASEURL.'/scripts/dst.js"></script>';
	
	
	
}

/* ── Footer links ────────────────────────────────────────────────────── */
$showteamlink = '';

$showteamlink = '<div class="col-lg d-flex flex-column justify-content-between">
<a href="showteam.php" class="links"><i class="fa-solid fa-address-book"></i> &nbsp;'.$lang->index['bottomlinks_forumteam'].'</a>
</div>';



$contact_us  = '';
$contactlink = 'contact.php';
if (!my_validate_url($contactlink, true) && !str_starts_with($contactlink, 'mailto:')) {
    $contactlink = $BASEURL . '/' . $contactlink;
}

$contact_us = '<div class="col-lg d-flex flex-column justify-content-between">
	<a href="{$contactlink}" class="links"><i class="fa-solid fa-envelope"></i> &nbsp;'.$lang->index['bottomlinks_contactus'].'</a>
</div>';





$footer = '<div class="container mt-3">

	<div class="container-fluid mt-5 py-3 bg-nav">
<div class="container-md">
	
	
<div class="row flex m-auto text-14">
	'.$contact_us.'
	'.$showteamlink.'
	<div class="col-lg d-flex flex-column justify-content-between">
	<a href="'.$BASEURL.'" class="links"><i class="fa-solid fa-house"></i> &nbsp;'.$SITENAME.'</a>
	</div>
	<div class="col-lg d-flex flex-column justify-content-between">
	<a href="#top" class="links"><i class="fa-solid fa-circle-arrow-up"></i> &nbsp;'.$lang->global['bottomlinks_returntop'].'</a>
	</div>
	<div class="col-lg d-flex flex-column justify-content-between">
	<a href="'.$BASEURL.'/misc.php?action=markread'.$post_code_string.'" class="links"><i class="fa-solid fa-circle-dot"></i> &nbsp;'.$lang->global['bottomlinks_markread'].'</a>
	</div>
	<div class="col-lg d-flex flex-column justify-content-between">
	<a href="'.$BASEURL.'/misc.php?action=syndication" class="links"><i class="fa-solid fa-square-rss"></i> &nbsp;'.$lang->global['bottomlinks_syndication'].'</a>
	</div>
	</div>
	
		</div>
	</div>
	
	<div class="container-md">
	
	'.$auto_dst_detection.'
	<div class="row g-1 mt-3 mb-3 text-14">
		<div class="col-lg-auto align-self-center text-center text-sm-center text-md-center text-lg-start text-xl-start text-xxl-start">
				  
		</div>
		<div class="col-lg-auto align-self-center">
		
		</div>
		
	</div>
	</div>
		
	</div>

<!-- new -->
	</div>
	</div>';




$index  = '<html>
<head>
<title>'.$SITENAME.'</title>

<script type="text/javascript">
<!--
	lang.no_new_posts = "'.$lang->index['no_new_posts'].'";
	lang.click_mark_read = "'.$lang->index['click_mark_read'].'";
// -->
</script>
	<style type="text/css">
		
		.navigation {
			display: none;
		}
		
	</style>
</head>
<body>


	
	<div class="container-md">
		
<div class="mt-4">
'.$forums.'
		</div>

		
		
		<!-- online -->
				
<div class="row ps-0 m-0 py-3">
		
		
	</div>
	
	<div class="row ps-0 m-0 py-3 pt-0">
		<div class="col-auto  align-self-center text-center ps-3 pe-0">
			<i class="fa-solid fa-chart-column fs-icon text-white"></i>
		</div>
		<div class="col align-self-center text-start ms-3 pt-0 ps-0">
			
'.$birthdays.'
			
				</div>
	</div>
			
		<!-- boardstats -->
		'.$whosonline.'
		</br>
		'.$boardstats.'
	</div>
	'.$footer.'
</body>
</html>';


/* ── Output ──────────────────────────────────────────────────────────── */
stdhead($SITENAME . ' FORUMS');


echo $index;
stdfoot();