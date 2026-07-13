<?php


declare(strict_types=1);

define('SCRIPTNAME', 'forumdisplay.php');
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'forumdisplay.php');


define('IN_FORUM', true);

require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_forumlist.php';
require_once INC_PATH . '/functions_multipage.php';


if (empty($CURUSER['id'])) {
    print_no_permission();
}


// ─── Инициализация массивов сортировки ───────────────────────────────────────
$orderarrow = $sortsel = array_fill_keys(
    ['rating', 'subject', 'starter', 'started', 'replies', 'views', 'lastpost'],
    ''
);
$ordersel   = ['asc' => '', 'desc' => ''];
$datecutsel = array_fill_keys([1, 5, 10, 20, 50, 75, 100, 365, 9999], '');
$rules      = '';

$lang->load('forumdisplay');
$plugins->run_hooks('forumdisplay_start');

// ─── Обработка специальных fid (отрицательные) ───────────────────────────────
$fid = $mybb->get_input('fid', MyBB::INPUT_INT);

if ($fid < 0) {
    $location = match ($fid) {
        -1 => 'index.php',
        -2 => 'search.php',
        -3 => 'usercp.php',
        -4 => 'private.php',
        -5 => 'online.php',
        default => null,
    };

    if ($location !== null) {
        header('Location: ' . $location);
        exit;
    }
}

// ─── Информация о форуме ──────────────────────────────────────────────────────
$foruminfo = get_forum($fid);
if (!$foruminfo) {
    stderr($lang->forumdisplay['error_invalidforum'], $SITENAME . ' - Forum Not Found', 404, 'forum');
}

$currentitem = $fid;
build_forum_breadcrumb($fid);
$parentlist = $foruminfo['parentlist'];

$foruminfo['name'] = preg_replace('#&(?!\#[0-9]+;)#si', '&amp;', $foruminfo['name']);

$forumpermissions = forum_permissions();
$fpermissions     = $forumpermissions[$fid];

if ($fpermissions['canview'] != 1) {
    stdhead();
    error_no_permission();
}

// ─── Кэш форумов ─────────────────────────────────────────────────────────────
$fcache     = [];
$forumsread = [];

if ($CURUSER['id'] == 0) {
    if (isset($mybb->cookies['mybb']['forumread'])) {
        $forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
    }

    if (is_array($forumsread) && empty($forumsread) && isset($mybb->cookies['mybb']['readallforums'])) {
        $forumsread[$fid] = $mybb->cookies['mybb']['lastvisit'];
    }

    $query = $db->simple_select('forums', '*', 'active != 0', ['order_by' => 'pid, disporder']);
} else {
    $query = $db->sql_query("
        SELECT f.*, fr.dateline AS lastread
        FROM forums f
        LEFT JOIN forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$CURUSER['id']}')
        WHERE f.active != 0
        ORDER BY pid, disporder
    ");
}

while ($forum = $db->fetch_array($query)) {
    if ($CURUSER['id'] == 0 && isset($forumsread[$forum['fid']])) {
        $forum['lastread'] = $forumsread[$forum['fid']];
    }
    $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}

// ─── Подфорумы ───────────────────────────────────────────────────────────────
$bgcolor       = 'trow1';
$showdepth     = 3; // subforumsindex != 0
$subforums     = '';
$child_forums  = build_forumbits($fid, 2);

if (!empty($child_forums) && !empty($child_forums['forum_list'])) {
    $forums        = $child_forums['forum_list'];
    $sub_forums_in = 'Forums in ' . $foruminfo['name'];
    
	
	$subforums = '
	
	<div class="card mb-3 border-0">
<div class="card-header rounded">
	
<h5 class="mb-0 text-19">'.$sub_forums_in.'</h5>
</div>
<div class="card-body ps-1 pe-1 pt-0 border-bottom-rounded">
'.$forums.'
	
</div>
</div>';
	
	
	
	
	
}

$excols = 'forumdisplay';
check_forum_password($foruminfo['fid']);

if ($foruminfo['linkto']) {
    header('Location: ' . $foruminfo['linkto']);
    exit;
}

// ─── Forum jump & New thread ──────────────────────────────────────────────────
$forumjump = build_forum_jump('', $fid, 1);
$newthread  = '';

if ($foruminfo['type'] === 'f' && $foruminfo['open'] != 0 && $fpermissions['canpostthreads'] != 0) {
    
	
	$newthread = '<a href="newthread.php?fid='.$fid.'" class="btn btn-primary" role="button"><i class="fa-solid fa-pencil"></i> &nbsp;'.$lang->forumdisplay['post_thread'].'</a>';
	
	
}

$searchforum = '';
if ($fpermissions['cansearch'] != 0 && $foruminfo['type'] === 'f') {
    
	$searchforum = '<div class="row g-1">
<div class="col align-self-center">
<form action="search.php">
<input type="text" name="keywords" class="form-control border form-control-sm border" placeholder="Enter keywords..." />
	</div>
	<div class="col-auto align-self-center">
			<button type="submit" class="btn btn-primary btn-sm" value="Go!"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;'.$lang->global['search_button'].'</button>
	<input type="hidden" name="action" value="do_search" />
	<input type="hidden" name="forums[]" value="'.$fid.'" />
	<input type="hidden" name="postthread" value="1" />
	</form>
</div>
</div>';
	
	
}

// ─── Статистика форума ────────────────────────────────────────────────────────
$has_announcements = false;
$has_modtools      = false;
$forum_stats       = $cache->read('forumsdisplay');

if (is_array($forum_stats)) {
    $has_modtools      = !empty($forum_stats[-1]['modtools'])      || !empty($forum_stats[$fid]['modtools']);
    $has_announcements = !empty($forum_stats[-1]['announcements']) || !empty($forum_stats[$fid]['announcements']);
}

// ─── Модераторы ───────────────────────────────────────────────────────────────
$done_moderators = ['users' => [], 'groups' => []];
$moderators      = '';
$comma           = '';

foreach (explode(',', $parentlist) as $mfid) {
    if (!empty($forum_stats[(int)$mfid]['announcements'])) {
        $has_announcements = true;
    }

    if (!isset($moderatorcache[$mfid]) || !is_array($moderatorcache[$mfid])) {
        continue;
    }

    foreach ($moderatorcache[$mfid] as $modtype) {
        foreach ($modtype as $moderator) {
            if (!empty($moderator['isgroup'])) {
                if (in_array($moderator['id'], $done_moderators['groups'], true)) {
                    continue;
                }
                $moderator['title']    = htmlspecialchars_uni($moderator['title']);
                $moderators           .= $comma . $moderator['title'];
                $done_moderators['groups'][] = $moderator['id'];
            } else {
                if (in_array($moderator['id'], $done_moderators['users'], true)) {
                    continue;
                }
                $moderator['profilelink'] = get_profile_link($moderator['id']);
                $moderator['username']    = format_name(
                    htmlspecialchars_uni($moderator['username']),
                    $moderator['usergroup'],
                    $moderator['displaygroup']
                );
                $moderators .= $comma . '<a href="' . $moderator['profilelink'] . '">' . $moderator['username'] . '</a>';
                $done_moderators['users'][] = $moderator['id'];
            }
            $comma = 'comma';
        }
    }
}

$comma       = '';
$moderatedby = $moderators
    ? '<span class="small">{$lang->moderated_by} <strong>' . $moderators . '</strong></span><br />'
    : '';

// ─── Пользователи на форуме ───────────────────────────────────────────────────
$usersbrowsing  = '';
$browsingthisforum = '1';
if ($browsingthisforum != 0) {
    $timecut      = TIMENOW - $wolcutoffmins;
    $comma        = '';
    $guestcount   = 0;
    $membercount  = 0;
    $inviscount   = 0;
    $onlinemembers = '';
    $doneusers    = [];
    $guest_ips    = [];

    // Один запрос вместо двух
    $query = $db->sql_query("
        SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup, u.displaygroup
        FROM sessions s
        LEFT JOIN users u ON (s.uid = u.id)
        WHERE s.time > {$timecut} AND s.location1 = {$fid} AND s.nopermission != 1
        ORDER BY u.username ASC, s.time DESC
    ");
    while ($user = $db->fetch_array($query)) {
        if ($user['uid'] == 0) {
            $guest_ips[$user['ip']] = true;
            continue;
        }
        if (empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time']) {
            $doneusers[$user['uid']] = $user['time'];
            ++$membercount;
            $invisiblemark = '';
            if ($user['invisible'] == 1) {
                $invisiblemark = '*';
                ++$inviscount;
            }
            if ($user['invisible'] != 1 || ($usergroups['canviewwolinvis'] ?? 0) == 1 || $user['uid'] == $CURUSER['id']) {
                $user['username']    = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                $user['profilelink'] = build_profile_link($user['username'], $user['uid']);
                $onlinemembers      .= $comma . $user['profilelink'] . $invisiblemark;
                $comma               = ', ';
            }
        }
    }
    $guestcount = count($guest_ips);

    $guestsonline = $guestcount
        ? sprintf($lang->forumdisplay['users_browsing_forum_guests'], $guestcount)
        : '';
    if ($CURUSER['invisible'] == 1) {
        --$inviscount;
    }
    $invisonline = ($inviscount > 0 && ($usergroups['canviewwolinvis'] ?? 0) != 1)
        ? sprintf($lang->forumdisplay['users_browsing_forum_invis'], $inviscount)
        : '';
    $onlinesep  = ($invisonline !== '' && $onlinemembers) ? ', ' : '';
    $onlinesep2 = (($invisonline !== '' && $guestcount) || ($onlinemembers && $guestcount)) ? ', ' : '';
    $usersbrowsing = '<div class="card border-0 rounded mt-5">
    <div class="card-body bg-nav rounded">
        <strong>' . $lang->forumdisplay['users_browsing_forum'] . '</strong> ' . $onlinemembers . ' ' . $onlinesep . ' ' . $invisonline . ' ' . $onlinesep2 . ' ' . $guestsonline . '
    </div></div>';
}

// ─── Правила форума ───────────────────────────────────────────────────────────
$forumrules = '';
$bgcolor    = 'trow1';

// ─── Видимые состояния тредов ─────────────────────────────────────────────────
$visible_states = ['1'];
$is_mod         = is_mod($usergroups);

if ($is_mod) {
    
	$inlinemodcol = '<div class="form-check form-switch float-end">
    <input class="form-check-input form-check-input-sm" type="checkbox" name="allbox" id="allbox" onclick="inlineModeration.checkAll(this)">
</div>';
	
	
	
    $ismod       = true;
    $inlinecount = 0;
    $inlinemod   = '';
    $inlinecookie = 'inlinemod_forum' . $fid;
    $visible_states[] = '0';
} else {
    $inlinemod = $inlinemodcol = '';
    $is_mod    = false;
}

$visible_condition = 'visible IN (' . implode(',', array_unique($visible_states)) . ')';
$visibleonly       = 'AND ' . $visible_condition;

if ($CURUSER['id'] && ($showownunapproved ?? false)) {
    $visible_condition .= ' OR (t.visible=0 AND t.uid=' . (int)$CURUSER['id'] . ')';
}

$tvisibleonly  = 'AND (t.' . $visible_condition . ')';
$is_mod        = is_mod($usergroups);
$can_edit_titles = $is_mod ? 1 : 0;

// ─── Параметры сортировки ─────────────────────────────────────────────────────
$datecut = 9999;

if (empty($mybb->input['datecut'])) {
    if (!empty($CURUSER['daysprune'])) {
        $datecut = $CURUSER['daysprune'];
    } elseif (!empty($foruminfo['defaultdatecut'])) {
        $datecut = $foruminfo['defaultdatecut'];
    }
} else {
    $datecut = $mybb->get_input('datecut', MyBB::INPUT_INT);
}

$datecutsel[(int)$datecut] = ' selected="selected"';

if ($datecut > 0 && $datecut != 9999) {
    $checkdate   = TIMENOW - ($datecut * 86400);
    $datecutsql  = "AND (lastpost >= '$checkdate' OR sticky = '1')";
    $datecutsql2 = "AND (t.lastpost >= '$checkdate' OR t.sticky = '1')";
} else {
    $datecutsql = $datecutsql2 = '';
}

// ─── Фильтр по префиксу ──────────────────────────────────────────────────────
$tprefix = $mybb->get_input('prefix', MyBB::INPUT_INT);

[$prefixsql, $prefixsql2] = match (true) {
    $tprefix > 0  => ["AND prefix = {$tprefix}",  "AND t.prefix = {$tprefix}"],
    $tprefix == -1 => ['AND prefix = 0',           'AND t.prefix = 0'],
    $tprefix == -2 => ['AND prefix != 0',          'AND t.prefix != 0'],
    default        => ['', ''],
};

// ─── Порядок сортировки ───────────────────────────────────────────────────────
if (!isset($mybb->input['order']) && !empty($foruminfo['defaultsortorder'])) {
    $mybb->input['order'] = $foruminfo['defaultsortorder'];
} else {
    $mybb->input['order'] = $mybb->get_input('order');
}

$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));

[$sortordernow, $oppsort, $oppsortnext, $orderselKey] = match (my_strtolower($mybb->input['order'])) {
    'asc'   => ['asc',  'desc', 'desc', 'asc'],
    default => ['desc', 'asc',  'asc',  'desc'],
};
$ordersel[$orderselKey] = ' selected="selected"';

// ─── Поле сортировки ─────────────────────────────────────────────────────────
if (!isset($mybb->input['sortby']) && !empty($foruminfo['defaultsortby'])) {
    $mybb->input['sortby'] = $foruminfo['defaultsortby'];
} else {
    $mybb->input['sortby'] = $mybb->get_input('sortby');
}

$t          = 't.';
$sortfield2 = '';
$sortby     = htmlspecialchars_uni($mybb->input['sortby']);

switch ($mybb->input['sortby']) {
    case 'subject': $sortfield = 'subject';   break;
    case 'replies': $sortfield = 'replies';   break;
    case 'views':   $sortfield = 'views';     break;
    case 'starter': $sortfield = 'username';  break;
    case 'rating':
        $t          = '';
        $sortfield  = 'averagerating';
        $sortfield2 = ', t.totalratings DESC';
        break;
    case 'started': $sortfield = 'dateline';  break;
    default:
        $sortby               = 'lastpost';
        $sortfield            = 'lastpost';
        $mybb->input['sortby'] = 'lastpost';
        break;
}

$sortsel['rating']                    = '';
$sortsel[$mybb->input['sortby']] = ' selected="selected"';

$string  = ($mybb->seo_support ?? false) ? '?' : '&amp;';

$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
$sorturl = ($mybb->input['page'] > 1)
    ? get_forum_link($fid, $mybb->input['page']) . $string . "datecut=$datecut&amp;prefix=$tprefix"
    : get_forum_link($fid) . $string . "datecut=$datecut&amp;prefix=$tprefix";

$orderarrow['$sortby'] = '<span class="smalltext">[<a href="' . $sorturl . '&amp;sortby=' . $sortby . '&amp;order=' . $oppsortnext . '">' . $oppsort . '</a>]</span>';

// ─── Подсчёт тредов ───────────────────────────────────────────────────────────
$threadcount = 0;
$useronly    = '';
$tuseronly   = '';

if (isset($fpermissions['canonlyviewownthreads']) && $fpermissions['canonlyviewownthreads'] == 1) {
    $useronly  = "AND uid={$CURUSER['id']}";
    $tuseronly = "AND t.uid={$CURUSER['id']}";
}

if ($fpermissions['canviewthreads'] != 0) {
    if ($useronly === '' && $datecutsql === '' && $prefixsql === '') {
        $query        = $db->simple_select('forums', 'threads, unapprovedthreads', 'fid=' . (int)$fid);
        $forum_threads = $db->fetch_array($query);

        if (in_array(1, $visible_states))  { $threadcount += (int)$forum_threads['threads']; }
        if (in_array(-1, $visible_states)) { $threadcount += (int)($forum_threads['deletedthreads'] ?? 0); }

        if (in_array(0, $visible_states)) {
            $threadcount += (int)$forum_threads['unapprovedthreads'];
        } elseif ($CURUSER['id'] && ($showownunapproved ?? false)) {
            $query        = $db->simple_select('threads t', 'COUNT(tid) AS threads', "fid = '$fid' AND t.visible=0 AND t.uid=" . (int)$CURUSER['id']);
            $threadcount += (int)$db->fetch_field($query, 'threads');
        }
    } else {
        $query       = $db->simple_select('threads t', 'COUNT(tid) AS threads', "fid = '$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2");
        $threadcount = (int)$db->fetch_field($query, 'threads');
    }
}

// ─── Пагинация ────────────────────────────────────────────────────────────────
$perpage = (isset($f_threadsperpage) && (int)$f_threadsperpage > 0) ? (int)$f_threadsperpage : 20;
$page    = max(1, $mybb->input['page']);
$pages   = (int)ceil($threadcount / $perpage);
$start   = ($page > $pages) ? 0 : ($page - 1) * $perpage;

if ($page > $pages || $page <= 0) {
    $start = 0;
    $page  = 1;
}

$end   = $start + $perpage;
$lower = $start + 1;
$upper = min($end, $threadcount);

// ─── URL страницы ─────────────────────────────────────────────────────────────
$page_url = str_replace('{fid}', (string)$fid, FORUM_URL_PAGED);

if ($mybb->input['sortby'] || $mybb->input['order'] || ($mybb->input['datecut'] ?? null) || ($mybb->input['prefix'] ?? null)) {
    $q   = ($mybb->seo_support ?? false) ? '?' : '';
    $and = '';

    if ((!empty($foruminfo['defaultsortby']) && $sortby !== $foruminfo['defaultsortby'])
        || (empty($foruminfo['defaultsortby']) && $sortby !== 'lastpost')) {
        $page_url .= "{$q}{$and}sortby={$sortby}";
        $q = ''; $and = '&';
    }
    if ($sortordernow !== 'desc') {
        $page_url .= "{$q}{$and}order={$sortordernow}";
        $q = ''; $and = '&';
    }
    if ($datecut > 0 && $datecut != 9999) {
        $page_url .= "{$q}{$and}datecut={$datecut}";
        $q = ''; $and = '&';
    }
    if ($tprefix != 0) {
        $page_url .= "{$q}{$and}prefix={$tprefix}";
    }
}

$multipage = multipage($threadcount, $perpage, $page, $page_url);

 


    if ($sortfield === 'averagerating') 
	{
        $t = 't.'; $sortfield = 'lastpost';
    }
    $lpbackground = 'trow1';
    $colspan      = '6';


if ($ismod ?? false) {
    ++$colspan;
}

// ─── Объявления ──────────────────────────────────────────────────────────────
$announcementlist = '';

if ($has_announcements) {
    $announcements     = '';
    $announcementlimit = '2';
    $limit             = $announcementlimit ? 'LIMIT 0, ' . (int)$announcementlimit : '';
    $sql               = build_parent_list($fid, 'fid', 'OR', $parentlist);
    $time              = TIMENOW;

   $query = $db->sql_query("
    SELECT a.*, u.username
    FROM announcements a
    LEFT JOIN users u ON (u.id = a.uid)
    WHERE a.type IN ('forum', 'global')
      AND a.startdate <= '$time'
      AND (a.enddate >= '$time' OR a.enddate = '0')
      AND ($sql OR a.fid = '-1')
    ORDER BY a.startdate DESC $limit
");

    $cookie = [];
    if (isset($mybb->cookies['mybb']['announcements'])) {
        $cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']), false);
    }

    $bgcolor = alt_trow(true);

    while ($announcement = $db->fetch_array($query)) {
        [$new_class, $folder] = ($announcement['startdate'] > $CURUSER['lastvisit'] && empty($cookie[$announcement['id']]))
            ? [' class="subject_new"', 'newfolder']
            : [' class="subject_old"', 'folder'];

        if (isset($cookie[$announcement['id']]) && $cookie[$announcement['aid']] < $CURUSER['lastvisit']) {
            unset($cookie[$announcement['id']]);
        }

        $announcement['announcementlink'] = get_announcement_link((int)$announcement['id']);
        $announcement['subject']          = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));
        $postdate                         = my_datee('relative', $announcement['startdate']);
        $announcement['username']         = htmlspecialchars_uni($announcement['username']);
        $announcement['profilelink']      = build_profile_link($announcement['username'], $announcement['uid']);

        $rating       = '';
        $lpbackground = 'trow1';

        $modann = ($ismod ?? false)
            ? '<td align="center" class="' . $bgcolor . ' forumdisplay_announcement">-</td>'
            : '';

        $plugins->run_hooks('forumdisplay_announcement');
        
		
		$announcements .= '<div class="card border-bottom border-bottom-2 border-2 border-0 rounded-0" style="border-bottom: 2px solid;">
	<div class="card-body py-0 px-1 inline_row">
<div class="row py-3">
		<div class="col align-self-center ms-2">
			<h6 class="mb-0 text-forum"><a href="'.$announcement['announcementlink'].'">'.$announcement['subject'].'</a> <span class="text-muted small">'.$lang->forumdisplay['by'].'</span> <span class="links small">'.$announcement['profilelink'].'</h6></span>
	</div>
	<div class="col-lg-3 align-self-center small text-muted text-wrap text-uppercase">
				'.$postdate.'
	</div>
		</div>
	</div>
</div>';
		
		
        $bgcolor = alt_trow();
    }

    if ($announcements) {
        $announcementlist = $announcements;
        $shownormalsep    = true;
    }

    if (empty($cookie)) {
        my_setcookie('rt[announcements]', '0', TIMENOW - (60 * 60 * 24 * 365));
    } else {
        my_setcookie('rt[announcements]', addslashes(my_serialize($cookie)), -1);
    }
}

// ─── Треды ────────────────────────────────────────────────────────────────────
$tids        = [];
$threadcache = [];



if ($fpermissions['canviewthreads'] != 0) {
    $plugins->run_hooks('forumdisplay_get_threads');

    $query = $db->sql_query("
        SELECT t.*, t.username AS threadusername, u.username
        FROM threads t
        LEFT JOIN users u ON (u.id = t.uid)
        WHERE t.fid = '$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2
        ORDER BY t.sticky DESC, {$t}{$sortfield} $sortordernow $sortfield2
        LIMIT $start, $perpage
    ");

    $moved_threads = [];

    while ($thread = $db->fetch_array($query)) {
        $threadcache[$thread['tid']] = $thread;

        if (str_starts_with((string)$thread['closed'], 'moved')) {
            $tid = substr($thread['closed'], 6);
            if (!isset($tids[$tid])) {
                $moved_threads[$tid]        = $thread['tid'];
                $tids[$thread['tid']]       = $tid;
            }
        } else {
            $tids[$thread['tid']] = $thread['tid'];
            unset($moved_threads[$thread['tid']]);
        }
    }

    $args = ['threadcache' => &$threadcache, 'tids' => &$tids];
    $plugins->run_hooks('forumdisplay_before_thread', $args);
}

// ─── Select All (для модераторов) ─────────────────────────────────────────────
$is_mod   = is_mod($usergroups);
$selectall = '';

if ($is_mod && $threadcount > $perpage) {
    $page_selected = sprintf($lang->forumdisplay['page_selected'], count($threadcache));
    $select_all    = sprintf($lang->forumdisplay['select_all'], (int)$threadcount);
    $all_selected  = sprintf($lang->forumdisplay['all_selected'], (int)$threadcount);
   

$selectall = '
<tr id="selectAllrow" class="hiddenrow">
    <td colspan="8" class="selectall">' . $page_selected . ' <a href="javascript:void(0)" onclick="inlineModeration.selectAll(); return false;">' . $select_all . '</a></td>
</tr>
<tr id="allSelectedrow" class="hiddenrow">
    <td colspan="8" class="selectall">' . $all_selected . ' <a href="javascript:void(0)" onclick="inlineModeration.clearChecked(); return false;">' . $lang->forumdisplay['clear_selection'] . '</a></td>
</tr>';
   
   
}

$tids_str = !empty($tids) ? implode(',', $tids) : '';

// ─── Иконки "точка" (участие пользователя) ───────────────────────────────────
$dotfolders = '1';

if ($dotfolders != 0 && $CURUSER['id'] && !empty($threadcache) && $tids_str !== '') {
    $query = $db->simple_select('posts', 'DISTINCT tid,uid', "uid='{$CURUSER['id']}' AND tid IN ({$tids_str}) {$visibleonly}");
    while ($post = $db->fetch_array($query)) {
        $ptid = $moved_threads[$post['tid']] ?? $post['tid'];
        if (isset($threadcache[$ptid])) {
            $threadcache[$ptid]['doticon'] = 1;
        }
    }
}

// ─── Прочитанные треды ────────────────────────────────────────────────────────
$threadreadcut = 7;

if ($CURUSER['id'] && $threadreadcut > 0 && !empty($threadcache) && $tids_str !== '') {
    $query = $db->simple_select('threadsread', '*', "uid='{$CURUSER['id']}' AND tid IN ({$tids_str})");
    while ($readthread = $db->fetch_array($query)) {
        $rtid = $moved_threads[$readthread['tid']] ?? $readthread['tid'];
        if (isset($threadcache[$rtid])) {
            $threadcache[$rtid]['lastread'] = $readthread['dateline'];
        }
    }
}

$read_cutoff = TIMENOW - $threadreadcut * 60 * 60 * 24;

if ($threadreadcut > 0 && $CURUSER['id']) {
    $forum_read = 0;
    $query      = $db->simple_select('forumsread', 'dateline', "fid='{$fid}' AND uid='{$CURUSER['id']}'");
    if ($db->num_rows($query) > 0) {
        $forum_read = (int)$db->fetch_field($query, 'dateline');
    }
    if ($forum_read == 0 || $forum_read < $read_cutoff) {
        $forum_read = $read_cutoff;
    }
} else {
    $forum_read = my_get_array_cookie('forumread', $fid);
    if (isset($mybb->cookies['mybb']['readallforums']) && !$forum_read) {
        $forum_read = $mybb->cookies['mybb']['lastvisit'];
    }
}

// ─── Рендер тредов ───────────────────────────────────────────────────────────
$unreadpost = 0;
$threads    = '';

if (!empty($threadcache) && is_array($threadcache)) {
    $maxmultipagelinks = $maxmultipagelinks ?? 5;
    $f_postsperpage    = (isset($f_postsperpage) && (int)$f_postsperpage > 0) ? (int)$f_postsperpage : 20;

    foreach ($threadcache as $thread) {
        $plugins->run_hooks('forumdisplay_thread');

        $moved = explode('|', $thread['closed']);

        $bgcolor = match (true) {
            $thread['visible'] == 0  => 'trow_shaded',
            $thread['visible'] == -1 => 'trow_shaded trow_deleted',
            default                  => alt_trow(),
        };

        $thread_type_class = ($thread['sticky'] == 1) ? ' forumdisplay_sticky' : ' forumdisplay_regular';
        $folder = $prefix = '';

        $thread['author'] = $thread['uid'];
        if (!$thread['username']) {
            $thread['username'] = $thread['profilelink'] = htmlspecialchars_uni($thread['threadusername'] ?: 'guest');
        } else {
            $thread['username']    = htmlspecialchars_uni($thread['username']);
            $thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
        }

        $thread['threadprefix'] = $threadprefix = '';
        if ($thread['prefix'] != 0) {
            $threadprefix = build_prefixes($thread['prefix']);
            if (!empty($threadprefix)) {
                $thread['threadprefix'] = $threadprefix['displaystyle'] . '&nbsp;';
            }
        }

        $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

        if ($thread['sticky'] == 1 && !isset($donestickysep)) {
            $threads      .= '';
            $shownormalsep = true;
            $donestickysep = true;
        } elseif ($thread['sticky'] == 0 && !empty($shownormalsep)) {
            $threads      .= '';
            $shownormalsep = false;
        }

        // ── Thread rating display ──────────────────────────────────────────
        $rating = '';
        if (!empty($thread['numratings']) && $thread['numratings'] > 0) {
            $rating_avg = $thread['totalratings'] / $thread['numratings'];
            $avg_stars  = '';
            for ($ri = 1; $ri <= 10; $ri++) {
                if ($rating_avg >= $ri)           $avg_stars .= '<i class="bi bi-star-fill rating-star-filled"></i>';
                elseif ($rating_avg >= $ri - 0.5) $avg_stars .= '<i class="bi bi-star-half rating-star-filled"></i>';
                else                              $avg_stars .= '<i class="bi bi-star rating-star-empty"></i>';
            }
            
			
			
			$rating = '<div class="d-flex align-items-center gap-1 ms-2">
    <div class="d-flex gap-0" style="color:#f59e0b;font-size:1rem;">' . $avg_stars . '</div>
    <span class="text-muted ms-1" style="font-size:0.75rem;">'
        . round($rating_avg, 1) . ' <span class="opacity-50">(' . ts_nf($thread['numratings']) . ')</span>
    </span>
</div>';
        
		}


        // ─ Многостраничность треда ───────────────────────────────────────────
        $thread['pages']     = 0;
        $thread['multipage'] = '';
        $threadpages         = '';
        $morelink            = '';
        $thread['posts']     = $thread['replies'] + 1;

        if (is_mod($usergroups)) {
            $thread['posts'] += (int)($thread['unapprovedposts'] ?? 0);
        }

        if ($thread['posts'] > $f_postsperpage) {
            $thread['pages'] = (int)ceil($thread['posts'] / $f_postsperpage);

            if ($thread['pages'] > $maxmultipagelinks) {
                $pagesstop = $maxmultipagelinks - 1;
                $page_link = get_thread_link($thread['tid'], $thread['pages']);
                $morelink  = '... <a href="' . $page_link . '" class="btn-fd">' . $thread['pages'] . '</a>';
            } else {
                $pagesstop = $thread['pages'];
            }

            for ($i = 1; $i <= $pagesstop; ++$i) {
                $threadpages .= '<a href="' . get_thread_link($thread['tid'], $i) . '" class="btn-fd">' . $i . '</a>';
            }

            $thread['multipage'] = '<div class="d-block small"><span class="text-desc">Pages:</span> ' . $threadpages . $morelink . '</div>';
        }

        // ─ Инлайн-модерация ──────────────────────────────────────────────────
        $modbit = '';
        if (is_mod($usergroups)) {
            $inlinecheck = (isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], '|' . $thread['tid'] . '|') !== false)
                ? 'checked="checked"'
                : '';
            if ($inlinecheck) { ++$inlinecount; }
            $multitid = $thread['tid'];
            
			
			$modbit = '<div class="form-check form-switch float-end m-0">
                    <input class="form-check-input" type="checkbox" 
                      name="inlinemod_'.$multitid.'" 
                      id="inlinemod_'.$multitid.'" 
                      value="1" 
                      '.$inlinecheck.'>
             </div>';
			
			
			
			
			
        }

        if ($moved[0] === 'moved') {
            $prefix         = 'moved_prefix';
            $thread['tid']  = $moved[1];
            $thread['replies'] = '-';
            $thread['views']   = '-';
        }

        $thread['threadlink']   = get_thread_link($thread['tid']);
        $thread['lastpostlink'] = get_thread_link($thread['tid'], 0, 'lastpost');

        $folder_label = '';
        if (isset($thread['doticon'])) {
            $folder       = 'dot_';
            $folder_label .= $lang->forumdisplay['icon_dot'];
        }

        $gotounread = '';
        $new_class  = 'subject_old';

        if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
            $last_read = $thread['lastread'] ?? $read_cutoff;
        } else {
            $last_read = my_get_array_cookie('threadread', $thread['tid']);
        }

        if ($forum_read > $last_read) {
            $last_read = $forum_read;
        }

        if ($thread['lastpost'] > $last_read && $moved[0] !== 'moved') {
            $folder      .= 'new';
            $folder_label .= $lang->forumdisplay['icon_new'];
            $new_class    = 'subject_new';
            $thread['newpostlink'] = get_thread_link($thread['tid'], 0, 'newpost');
            $gotounread   = '<a href="' . $thread['newpostlink'] . '">
            <img src="pic/jump.png" alt="' . $lang->forumdisplay['goto_first_unread'] . '" title="' . $lang->forumdisplay['goto_first_unread'] . '" /></a> ';
            $unreadpost   = 1;
        } else {
            $folder_label .= $lang->forumdisplay['icon_no_new'];
        }

        $hottopic      = 20;
        $hottopicviews = 150;

        if ($thread['replies'] >= $hottopic || $thread['views'] >= $hottopicviews) {
            $folder      .= 'hot';
            $folder_label .= 'icon_hot';
        }

        if ($thread['closed'] == 1) {
            $folder      .= 'close';
            $folder_label .= $lang->forumdisplay['icon_close'];
        }

        if ($moved[0] === 'moved') {
            $folder     = 'move';
            $gotounread = '';
        }

        $folder .= 'folder';

        $inline_edit_tid   = $thread['tid'];
        $inline_edit_class = '';

        if (($thread['uid'] == $CURUSER['id'] && $thread['closed'] != 1 && $CURUSER['id'] != 0 && $can_edit_titles == 1)
            || ($is_mod ?? false)) {
            $inline_edit_class = 'subject_editable';
        }

        $lastposteruid  = $thread['lastposteruid'];
        $lastposter     = htmlspecialchars_uni($thread['lastposter'] ?: 'guest');
        $lastpostdate   = my_datee('relative', $thread['lastpost']);
        $lastposterlink = ($lastposteruid == 0) ? $lastposter : build_profile_link($lastposter, $lastposteruid);

        $thread['replies'] = ts_nf($thread['replies']);
        $thread['views']   = ts_nf($thread['views']);

        // ─ Неодобренные посты ────────────────────────────────────────────────
        $unapproved_posts = '';
        if (($thread['unapprovedposts'] ?? 0) > 0) {
            $unapproved_posts_count = ($thread['unapprovedposts'] > 1)
                ? sprintf($lang->forumdisplay['thread_unapproved_posts_count'], $thread['unapprovedposts'])
                : sprintf($lang->forumdisplay['thread_unapproved_post_count'], 1);
            $thread['unapprovedposts'] = ts_nf($thread['unapprovedposts']);
            
			
			$unapproved_posts = '<span title="'.$unapproved_posts_count.'">('.$thread['unapprovedposts'].')</span>';
			
			
			
        }

        // ─ Вложения ──────────────────────────────────────────────────────────
        $attachment_count = '';
        if (($enableattachments ?? 0) == 1 && ($thread['attachmentcount'] ?? 0) > 0) {
            $label            = ($thread['attachmentcount'] > 1)
                ? 'This thread contains ' . $thread['attachmentcount'] . ' attachments'
                : 'This thread contains 1 attachment';
            $attachment_count = '<i class="fa-solid fa-paperclip" style="color:#444;opacity:.4" title="' . $label . '"></i>';
        }

        $plugins->run_hooks('forumdisplay_thread_end');

        $thread['start_datetime'] = my_datee('relative', $thread['dateline']);
        
		$threads .= '<div class="card border-bottom border-0 rounded-0">
	<div class="card-body py-0 px-1 inline_row '.$bgcolor.'">
<div class="row py-2">
		<div class="col align-self-center ms-2">
			
			
        <h6 class="mb-0 text-forum"><a href="'.$thread['threadlink'].'">'.$thread['threadprefix'].'<span class="'.$inline_edit_class.' '.$new_class.'" id="tid_'.$inline_edit_tid.'">'.$thread['subject'].'</span></h6>
		<div class="links small">'.$lang->forumdisplay['by'].' '.$thread['profilelink'].''.$rating.'</div>


		
		<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none mt-1">
			<span class="'.$thread_type_class.'"></span>'.$attachment_count.'<span class="thread_status '.$folder.'" title="'.$folder_label.'"></span>
		</div>
		
		</div>
			<div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center text-end">
			<span class="'.$thread_type_class.'"></span>'.$attachment_count.'<span class="thread_status '.$folder.'" title="'.$folder_label.'"></span>
				'.$thread['multipage'].'
			</div>
		<div class="col-1 d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center text-center">
			
			
			<h6 class="mb-0"><a href="' . $BASEURL . '/misc.php?action=whoposted&amp;tid=' . (int)$thread['tid'] . '" onclick="whoPosted(' . (int)$thread['tid'] . '); return false;">' . (int)$thread['replies'] . '</a></h6>
<span class="text-muted small">' . $lang->forumdisplay['replies'] . '</span>
			
			
		</div>
			
		<div class="col-12 col-sm-12 col-md-12 col-lg-3 col-xl-3 col-xxl-3 mt-3 mt-sm-3 mt-md-3 mt-lg-0">
			<div class="row">
				<div class="col-auto align-self-center">
					<avatarep_uid_['.$thread['lastposteruid'].']>
				</div>
				<div class="col align-self-center me-2">'.$lastposterlink.'<br /><span class="small text-muted text-uppercase">'.$lastpostdate.'</span> '.$modbit.'
					</div>
				</div>
				
				
				
				
			</div>
			</div>
	</div>
			</div>';
		
		
    }

    // ─── Инлайн-модерация (меню) ─────────────────────────────────────────────
    $customthreadtools = $standardthreadtools = '';

    if ($ismod ?? false) {
        $gids = array_filter(array_unique(array_merge(
            explode(',', $CURUSER['additionalgroups']),
            [$CURUSER['usergroup']]
        )));

        $gidswhere = '';
        foreach ($gids as $gid) {
            $gid = (int)$gid;
            $gidswhere .= match ($db->type) {
                'pgsql', 'sqlite' => " OR ','||groups||',' LIKE '%,{$gid},%'",
                default           => " OR CONCAT(',',`groups`,',') LIKE '%,{$gid},%'",
            };
        }

        $whereClause = match ($db->type) {
            'pgsql', 'sqlite' => "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND (groups='' OR ','||groups||',' LIKE '%,-1,%'{$gidswhere}) AND type = 't'",
            default           => "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND (`groups`='' OR CONCAT(',',`groups`,',') LIKE '%,-1,%'{$gidswhere}) AND type = 't'",
        };

        //$query = $db->simple_select('modtools', 'tid, name', $whereClause);
        //while ($tool = $db->fetch_array($query)) {
            //$tool['name'] = htmlspecialchars_uni($tool['name']);
			//eval('$customthreadtools .= "' . $templates->get('forumdisplay_inlinemoderation_custom_tool') . '";');
        //}
		

        if ($customthreadtools) {
            //eval('$customthreadtools = "' . $templates->get('forumdisplay_inlinemoderation_custom') . '";');
        }

        $inlinemodopenclose = $inlinemodstickunstick = $inlinemodsoftdelete = '';
        $inlinemodrestore   = $inlinemoddelete       = $inlinemodmanage    = '';
        $inlinemodapproveunapprove = '';

        $inlinemodopenclose = '<option value="multiclosethreads">'.$lang->forumdisplay['close_threads'].'</option>
        <option value="multiopenthreads">'.$lang->forumdisplay['open_threads'].'</option>';
		
		
		
        $inlinemodstickunstick = '<option value="multistickthreads">'.$lang->forumdisplay['stick_threads'].'</option>
        <option value="multiunstickthreads">'.$lang->forumdisplay['unstick_threads'].'</option>';
		
		
        $inlinemodapproveunapprove = '<option value="multiapprovethreads">'.$lang->forumdisplay['approve_threads'].'</option>
        <option value="multiunapprovethreads">'.$lang->forumdisplay['unapprove_threads'].'</option>';
		
		

        $inlinemoddelete = '<option value="multideletethreads">Delete Threads Permanently</option>';
        $inlinemodmanage = '<option value="multimovethreads">Move / Copy Threads</option>';

        $standardthreadtools = '<optgroup label="Standard Tools">'
            . $inlinemodopenclose
            . $inlinemodstickunstick
            . $inlinemoddelete
            . $inlinemodmanage
            . $inlinemodapproveunapprove
            . '</optgroup>';

$inlinemod = '<div class="col-lg-6 align-self-center text-end py-3">
<script type="text/javascript" src="'.$BASEURL.'/scripts/inline_moderation.js?ver=1821"></script>
<form action="moderation.php" method="post" id="inlinemoderation_threads">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<input type="hidden" name="fid" value="'.$fid.'" />
<input type="hidden" name="modtype" value="inlinethread" />
<div class="row g-1 text-end">
    <div class="col d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block align-self-center">
        &nbsp;
    </div>
    <div class="col-lg-auto align-self-center">
        <select name="action" id="inlinemoderation_threads_selector" class="form-select form-select-sm w-auto pe-5">
            <option value="delayedmoderation">'.$lang->forumdisplay['delayed_moderation'].'</option>
           '.$standardthreadtools.'
            '.$customthreadtools.'
        </select>
    </div>
    <div class="col-auto align-self-center">
        <input type="submit" class="btn btn-primary btn-sm" name="go" value="'.$lang->forumdisplay['inline_go'].' ('.$inlinecount.')" id="inline_go" />
    </div>
    <div class="col-auto align-self-center">
        <button type="button" onclick="inlineModeration.clearChecked();" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-xmark"></i> &nbsp;'.$lang->forumdisplay['clear'].'
        </button>
    </div>
</div>
</form>
</div>

<!-- Модалка удаления тредов -->
<div class="modal fade" id="deleteThreadsModal" tabindex="-1" aria-labelledby="deleteThreadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">

            <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteThreadsModalLabel">Delete Threads Permanently</h5>
                        <small class="opacity-75">Irreversible Action — Proceed with Caution</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">

                <!-- Счётчик -->
                <div class="text-center py-4" style="background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);">
                    <div style="font-size:3rem;font-weight:700;color:#ff416c;">
                        <i class="fas fa-hashtag"></i>
                        <span id="modal_thread_count">0</span>
                    </div>
                    <p class="text-muted mb-0">Threads Selected for Deletion</p>
                </div>

                <!-- Предупреждение -->
                <div class="px-4 pt-3 pb-2">
                    <div style="background:linear-gradient(135deg,#fff5f5,#ffeaea);border-left:4px solid #ff6b6b;border-radius:10px;padding:20px;">
                        <h6 class="fw-bold mb-3"><i class="fas fa-radiation me-2 text-danger"></i>Critical Warning</h6>
                        <p class="small mb-3">You are about to <strong>permanently delete</strong> selected threads. This action <strong>cannot be undone!</strong></p>
                        <ul class="list-unstyled mb-3">
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-times-circle text-danger me-2"></i>All posts within these threads will be permanently deleted
                            </li>
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-paperclip text-danger me-2"></i>All attachments will be removed from the server
                            </li>
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-chart-bar text-danger me-2"></i>Polls and voting data will be erased
                            </li>
                             <li class="py-1 border-bottom border-danger border-opacity-25 small">
                               <i class="fas fa-history me-2"></i>Thread history and statistics will be lost 
                             </li>
                            <li class="py-1 small">
                                <i class="fas fa-undo text-danger me-2"></i>No recovery or restore option is available
                            </li>
                        </ul>
                        <div class="alert alert-danger py-2 mb-0 small">
                            <i class="fas fa-skull-crossbones me-2"></i>
                            <strong>Data Loss Warning:</strong> This will permanently remove content from the database.
                        </div>
                    </div>
                </div>

                <!-- Превью тредов -->
                <div class="px-4 py-3">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-eye me-1"></i>Threads to be deleted:
                    </h6>
                    <div id="modal_threads_preview" style="max-height: 200px; overflow-y: auto;"></div>
                </div>

                <!-- Чекбокс подтверждения -->
                <div class="px-4 pb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmThreadDelete" style="accent-color:#ff416c;">
                        <label class="form-check-label small" for="confirmThreadDelete">
                            <strong>I understand this action is permanent and cannot be undone.</strong>
                            I have verified that I want to delete these threads.
                        </label>
                    </div>
                </div>

            </div>

            <div class="modal-footer d-flex justify-content-between border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i>Cancel & Return
                </button>
                <button type="button" class="btn btn-sm px-4 text-white" id="confirmDeleteThreadsBtn"
                    style="background:linear-gradient(135deg,#ff416c,#ff4b2b);border:none;opacity:0.6;" disabled>
                    <i class="fas fa-trash-alt me-1"></i>Delete Threads Permanently
                </button>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
<!--
    var go_text = "' . addslashes($lang->forumdisplay['inline_go']) . '";
    var all_text = "' . (int)$threadcount . '";
    var inlineType = "forum";
    var inlineId = ' . (int)$fid . ';

    document.addEventListener(\'DOMContentLoaded\', function() {
        var selector     = document.getElementById("inlinemoderation_threads_selector");
        var form         = document.getElementById("inlinemoderation_threads");
        var confirmCheck = document.getElementById(\'confirmThreadDelete\');
        var confirmBtn   = document.getElementById(\'confirmDeleteThreadsBtn\');
        var deleteModal  = null;

        if (typeof bootstrap !== \'undefined\') {
            var modalElement = document.getElementById(\'deleteThreadsModal\');
            if (modalElement) {
                deleteModal = new bootstrap.Modal(modalElement);
                modalElement.addEventListener(\'hidden.bs.modal\', function() {
                    if (confirmCheck) confirmCheck.checked = false;
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.style.opacity = \'0.6\';
                        confirmBtn.innerHTML = \'<i class="fas fa-trash-alt me-1"></i>Delete Threads Permanently\';
                    }
                });
            }
        }

        if (confirmCheck) {
            confirmCheck.addEventListener(\'change\', function() {
                if (confirmBtn) {
                    confirmBtn.disabled = !this.checked;
                    confirmBtn.style.opacity = this.checked ? \'1\' : \'0.6\';
                }
            });
        }

        if (selector && form) {

            form.addEventListener(\'submit\', function(e) {
                if (selector.value === \'multideletethreads\') {
                    e.preventDefault();

                    var selectedThreads = document.querySelectorAll(\'input[name^="inlinemod_"]:checked\');
                    var selectedCount   = selectedThreads.length;

                    if (selectedCount === 0) {
                        alert(\'Please select at least one thread.\');
                        return;
                    }

                    var countSpan = document.getElementById(\'modal_thread_count\');
                    if (countSpan) countSpan.textContent = selectedCount;

                    var threadIds = Array.from(selectedThreads).map(function(el) {
                        return el.name.replace(\'inlinemod_\', \'\');
                    });

                    // Превью
                    var previewContainer = document.getElementById(\'modal_threads_preview\');
                    if (previewContainer) {
                        previewContainer.innerHTML = \'\';

                        threadIds.forEach(function(tid) {
                            var titleEl  = document.querySelector(\'a[id="tid_\' + tid + \'"]\');
                            var titleText = titleEl ? titleEl.innerText.trim() : \'Thread ID: \' + tid;

                            var preview  = document.createElement(\'div\');
                            preview.className = \'card mb-2 border-danger border-opacity-25\';
                            preview.innerHTML =
                                \'<div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">\' +
                                    \'<span class="small fw-bold">\' +
                                        \'<i class="fas fa-file-alt text-danger me-2"></i>\' + escapeHtml(titleText) +
                                    \'</span>\' +
                                    \'<span class="badge bg-secondary">TID: \' + tid + \'</span>\' +
                                \'</div>\';
                            previewContainer.appendChild(preview);
                        });
                    }

                    // Скрытая форма
                    var oldForm = document.getElementById(\'deleteThreadsHiddenForm\');
                    if (oldForm) { oldForm.remove(); }

                    var hiddenForm       = document.createElement(\'form\');
                    hiddenForm.method    = \'post\';
                    hiddenForm.action    = \'moderation.php\';
                    hiddenForm.style.display = \'none\';
                    hiddenForm.id        = \'deleteThreadsHiddenForm\';

                    var fields = {
                        \'my_post_key\': document.querySelector(\'#inlinemoderation_threads input[name="my_post_key"]\').value,
                        \'fid\':         \'' . (int)$fid . '\',
                        \'modtype\':     \'inlinethread\',
                        \'action\':      \'do_multideletethreads\',
                        \'threads\':     threadIds.join(\',\'),
                        \'url\':         window.location.href
                    };

                    for (var key in fields) {
                        var input   = document.createElement(\'input\');
                        input.type  = \'hidden\';
                        input.name  = key;
                        input.value = fields[key];
                        hiddenForm.appendChild(input);
                    }

                    document.body.appendChild(hiddenForm);

                    if (confirmBtn) {
                        confirmBtn.onclick = function() {
                            if (!confirmCheck || !confirmCheck.checked) {
                                if (confirmCheck) confirmCheck.focus();
                                return;
                            }
                            confirmBtn.disabled  = true;
                            confirmBtn.innerHTML = \'<i class="fas fa-spinner fa-spin me-2"></i>Deleting...\';
                            hiddenForm.submit();
                            if (deleteModal) deleteModal.hide();
                        };
                    }

                    if (deleteModal) deleteModal.show();
                }
            });

            selector.addEventListener(\'change\', function() {
                form.dispatchEvent(new Event(\'submit\'));
            });
        }
    });
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === "&") return "&amp;";
            if (m === "<") return "&lt;";
            if (m === ">") return "&gt;";
            return m;
        });
    }
// -->
</script>';

		
		
		
		
    }
}

// ─── Пометка форума прочитанным ───────────────────────────────────────────────
require_once INC_PATH . '/functions_indicators.php';

$unread_threads = fetch_unread_count($fid);
if ($unread_threads !== false && $unread_threads == 0 && empty($unread_forums)) {
    mark_forum_read($fid);
}

// ─── Подписка на форум ────────────────────────────────────────────────────────
$add_remove_subscription      = 'add';
$add_remove_subscription_text = 'Subscribe to this forum';
$addremovesubscription        = '';

if ($CURUSER['id']) {
    $query = $db->simple_select('forumsubscriptions', 'fid', "fid='{$fid}' AND uid='{$CURUSER['id']}'", ['limit' => 1]);

    if ($db->num_rows($query) > 0) {
        $add_remove_subscription      = 'remove';
        $add_remove_subscription_text = 'Unsubscribe from this forum';
    }

    $addremovesubscription = '<a href="usercp.php?action=' . $add_remove_subscription . 'subscription&amp;type=forum&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-secondary">&nbsp;' . $add_remove_subscription_text . '&nbsp;</a>';
}

// ─── Финальный рендер ─────────────────────────────────────────────────────────
$inline_edit_js = $clearstoredpass = '';

if ($foruminfo['type'] !== 'c') {
    if ($fpermissions['canviewthreads'] != 1) {
        
		
		$threads = '<div class="ps-3 pe-3">
    <div class="empty-state text-center p-5">
        <div class="mb-4">
            <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                <i class="fa-solid fa-lock fa-3x text-danger"></i>
            </div>
        </div>
        <h5 class="text-danger mb-3">' . $lang->forumdisplay['nopermission'] . '</h5>
        <p class="text-muted mb-0">
            <i class="fa-regular fa-circle-question me-1"></i>
            You don\'t have permission to view threads in this forum.
        </p>
    </div>
</div>';

		
    }

    if (!$threadcount && $fpermissions['canviewthreads'] == 1) {
        
		$threads = '<div class="text-center p-5">
    <div class="mb-4">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center p-3" style="width: 70px; height: 70px;">
            <i class="fa-regular fa-file-lines fa-3x text-secondary"></i>
        </div>
    </div>
    <h5 class="text-secondary mb-2">No threads available</h5>
    <p class="text-muted">No threads match your selected criteria. Try adjusting your filters.</p>
</div>';

    }

    if ($foruminfo['password'] !== '') {
        $clearstoredpass = ' | <a href="misc.php?action=clearpass&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '">'.$lang->forumdisplay['clear_stored_password'].'</a>';
    }

    $gobutton = '<button type="submit" class="btn btn-sm btn-primary rounded" value="Go"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>';

    $forumsort = '';
    
	$forumsort = '<form action="forumdisplay.php" method="get">
				<input type="hidden" name="fid" value="'.$fid.'" />

<div class="p-3 bg-nav border-0 rounded-bottom text-center">
<div class="row g-2">
	<div class="col d-none do-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
		&nbsp;
	</div>
<div class="col-lg-auto align-self-center">
				<select class="form-select border form-select-sm pe-5" name="sortby">
					<option value="subject"'.$sortsel['subject'].'>'.$lang->forumdisplay['sort_by_subject'].'</option>
					<option value="lastpost"'.$sortsel['lastpost'].'>'.$lang->forumdisplay['sort_by_lastpost'].'</option>
					<option value="starter"'.$sortsel['starter'].'>'.$lang->forumdisplay['sort_by_starter'].'</option>
					<option value="started"'.$sortsel['started'].'>'.$lang->forumdisplay['sort_by_started'].'</option>
					
					<option value="replies"'.$sortsel['replies'].'>'.$lang->forumdisplay['sort_by_replies'].'</option>
					<option value="views"'.$sortsel['views'].'>'.$lang->forumdisplay['sort_by_views'].'</option>
				</select>
	</div>
	<div class="col-lg-auto align-self-center">
				<select name="order" class="form-select border form-select-sm pe-5">
					<option value="asc"'.$ordersel['asc'].'>'.$lang->forumdisplay['sort_order_asc'].'</option>
					<option value="desc"'.$ordersel['desc'].'>'.$lang->forumdisplay['sort_order_desc'].'</option>
				</select>
	</div>
	<div class="col-lg-auto align-self-center">
				<select name="datecut" class="form-select border form-select-sm pe-5">
					<option value="1"'.$datecutsel['1'].'>'.$lang->forumdisplay['datelimit_1day'].'</option>
					<option value="5"'.$datecutsel['5'].'>'.$lang->forumdisplay['datelimit_5days'].'</option>
					<option value="10"'.$datecutsel['10'].'>'.$lang->forumdisplay['datelimit_10days'].'</option>
					<option value="20"'.$datecutsel['20'].'>'.$lang->forumdisplay['datelimit_20days'].'</option>
					<option value="50"'.$datecutsel['50'].'>'.$lang->forumdisplay['datelimit_50days'].'</option>
					<option value="75"'.$datecutsel['75'].'>'.$lang->forumdisplay['datelimit_75days'].'</option>
					<option value="100"'.$datecutsel['100'].'>'.$lang->forumdisplay['datelimit_100days'].'</option>
					<option value="365"'.$datecutsel['365'].'>'.$lang->forumdisplay['datelimit_lastyear'].'</option>
					<option value="9999"'.$datecutsel['9999'].'>'.$lang->forumdisplay['datelimit_beginning'].'</option>
				</select>
	</div>
	
				
	
	<div class="col-auto align-self-center text-end">
				'.$gobutton.'
				</form>
	</div>
		<div class="col d-none do-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
		&nbsp;
	</div>
	</div>
	</div>';

	
	
	
	

    $plugins->run_hooks('forumdisplay_threadlist');

    $rssdiscovery = '<link rel="alternate" type="application/rss+xml" title="'.$lang->forumdisplay['rss_discovery_forum'].' (RSS 2.0)" href="'.$BASEURL.'/syndication.php?fid='.$fid.'" />
<link rel="alternate" type="application/atom+xml" title="'.$lang->forumdisplay['rss_discovery_forum'].' (Atom 1.0)" href="'.$BASEURL.'/syndication.php?type=atom1.0&amp;fid='.$fid.'" />';
	
	
	
$threadslist  = '<div class="row m-0 me-0 pe-0 p-0 mb-3">
		<div class="col ms-0 ps-0 text-center text-sm-center text-md-center text-lg-start align-self-center">
			'.$multipage.'
		</div>
	<div class="col-12 col-sm-12 col-md-12 col-lg-auto col-xl-auto col-xxl-auto mt-3 mt-sm-3 mt-md-3 mt-lg-0 me-0 pe-0 text-end align-self-center">
		<a href="misc.php?action=markread&amp;fid='.$fid.''.$post_code_string.'" class="btn btn-secondary" title="'.$lang->forumdisplay['markforum_read'].'">&nbsp;<i class="fa-solid fa-check"></i>&nbsp;</a> &nbsp; '.$addremovesubscription.' &nbsp; '.$newthread.'
	</div>
</div>

<div class="card border-0">
<div class="card-header py-3 bg-nav rounded border-bottom-0">
	
	<div class="row">
		<div class="col">
	<p class="mb-0"><a href="'.$sorturl.'&amp;sortby=subject&amp;order=asc">'.$lang->forumdisplay['thread'].'</a> '.$orderarrow['subject'].' &mdash; <a href="'.$sorturl.'&amp;sortby=starter&amp;order=asc">'.$lang->forumdisplay['author'].'</a></p>
		</div>
		<div class="col-1 d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block text-center">
			<p class="mb-0">
				&nbsp;<a href="'.$sorturl.'&amp;sortby=replies&amp;order=desc">'.$lang->forumdisplay['replies'].'</a>
			</p>
		</div>
		<div class="col-3">
			<span class="float-end">'.$inlinemodcol.'</span>
			&nbsp;<a href="'.$sorturl.'&amp;sortby=lastpost&amp;order=desc" class="d-none d-sm-none d-md-none d-lg-inline-block d-xl-inline-block d-xxl-inline-block">'.$lang->forumdisplay['lastpost'].'</a>
			
		</div>
	</div>
</div>
</div>




'.$announcementlist.'
'.$threads.'

'.$forumsort.'


<div class="mt-3">'.$multipage.'</div>



<div class="row mt-5 m-0 p-0 border-top border-bottom">
	<div class="col-lg-4 align-self-center py-3">
		'.$searchforum.'
	</div>
	<div class="col d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
		&nbsp;
	</div>
'.$inlinemod.'
</div>

'.$inline_edit_js.'';


	
	
	
} else {
    $rssdiscovery = $threadslist = '';

    if (empty($forums)) {
        error($lang->forumdisplay['error_containsnoforums']);
    }
}

$plugins->run_hooks('forumdisplay_end');

$foruminfo['name'] = strip_tags($foruminfo['name']);

$forums = '<html>
<head>
<title>'.$SITENAME.' - '.$foruminfo['name'].' </title>


'.$rssdiscovery.'
<script type="text/javascript">
<!--
	lang.no_new_posts = "'.$lang->global['no_new_posts'].'";
	lang.click_mark_read = "'.$lang->global['click_mark_read'].'";
	lang.inline_edit_description = "'.$lang->forumdisplay['inline_edit_description'].'";
	lang.post_fetch_error = "'.$lang->global['post_fetch_error'].'";
// -->
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/inline_edit.js?ver=1821"></script>
</head>
<body>

	
<div class="container-md">
'.$rules.'
	'.$moderatedby.'
'.$subforums.'
'.$threadslist.'


	
'.$usersbrowsing.'

</div>
	

</body>
</html>';







stdhead('title');
build_breadcrumb();

echo '<script src="' . $BASEURL . '/scripts/whoposted_js.js" defer></script>';

echo $forums;
stdfoot();