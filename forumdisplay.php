<?php


declare(strict_types=1);

define('SCRIPTNAME', 'forumdisplay.php');
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'forumdisplay.php');

$templatelist  = 'forumdisplay,forumdisplay_thread,forumbit_depth1_cat,forumbit_depth2_cat,forumbit_depth2_forum,forumdisplay_subforums,forumdisplay_threadlist,forumdisplay_moderatedby,forumdisplay_searchforum,forumdisplay_forumsort,forumdisplay_thread_rating,forumdisplay_threadlist_rating';
$templatelist .= ',forumbit_depth1_forum_lastpost,forumdisplay_thread_multipage_page,forumdisplay_thread_multipage,forumdisplay_thread_multipage_more,forumdisplay_thread_gotounread,forumbit_depth2_forum_lastpost,forumdisplay_rules_link,forumdisplay_orderarrow,forumdisplay_newthread';
$templatelist .= ',multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,forumdisplay_thread_unapproved_posts,forumdisplay_nothreads';
$templatelist .= ',forumjump_advanced,forumjump_special,forumjump_bit,forumdisplay_password_wrongpass,forumdisplay_password,forumdisplay_inlinemoderation_custom_tool,forumbit_subforums,forumbit_moderators,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_lastpost_hidden';
$templatelist .= ',forumdisplay_usersbrowsing_user,forumdisplay_usersbrowsing,forumdisplay_inlinemoderation,forumdisplay_thread_modbit,forumdisplay_inlinemoderation_col,forumdisplay_inlinemoderation_selectall,forumdisplay_threadlist_clearpass,forumdisplay_thread_rating_moved';
$templatelist .= ',forumdisplay_announcements_announcement,forumdisplay_announcements,forumdisplay_threads_sep,forumbit_depth3_statusicon,forumbit_depth3,forumdisplay_sticky_sep,forumdisplay_thread_attachment_count,forumdisplay_rssdiscovery,forumbit_moderators_group';
$templatelist .= ',forumdisplay_inlinemoderation_openclose,forumdisplay_inlinemoderation_stickunstick,forumdisplay_inlinemoderation_softdelete,forumdisplay_inlinemoderation_restore,forumdisplay_inlinemoderation_delete,forumdisplay_inlinemoderation_manage,forumdisplay_nopermission';
$templatelist .= ',forumbit_depth2_forum_unapproved_posts,forumbit_depth2_forum_unapproved_threads,forumbit_moderators_user,forumdisplay_inlinemoderation_standard,forumdisplay_threadlist_prefixes_prefix,forumdisplay_threadlist_prefixes,forumdisplay_thread_icon,forumdisplay_rules';
$templatelist .= ',forumdisplay_thread_deleted,forumdisplay_announcements_announcement_modbit,forumbit_depth2_forum_viewers,forumdisplay_threadlist_sortrating,forumdisplay_inlinemoderation_custom,forumdisplay_announcement_rating,forumdisplay_inlinemoderation_approveunapprove,forumdisplay_threadlist_subscription';

define('IN_FORUM', true);

require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_forumlist.php';
require_once INC_PATH . '/functions_multipage.php';

if (!isset($CURUSER)) {
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
    stderr($lang->forumdisplay['error_invalidforum']);
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

    $query = $db->simple_select('tsf_forums', '*', 'active != 0', ['order_by' => 'pid, disporder']);
} else {
    $query = $db->sql_query("
        SELECT f.*, fr.dateline AS lastread
        FROM tsf_forums f
        LEFT JOIN tsf_forumsread fr ON (fr.fid = f.fid AND fr.uid = '{$CURUSER['id']}')
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
    eval('$subforums = "' . $templates->get('forumdisplay_subforums') . '";');
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
    eval('$newthread = "' . $templates->get('forumdisplay_newthread') . '";');
}

$searchforum = '';
if ($fpermissions['cansearch'] != 0 && $foruminfo['type'] === 'f') {
    eval('$searchforum = "' . $templates->get('forumdisplay_searchforum') . '";');
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

    $query      = $db->simple_select('sessions', 'COUNT(DISTINCT ip) AS guestcount', "uid = 0 AND time > $timecut AND location1 = $fid AND nopermission != 1");
    $guestcount = (int)$db->fetch_field($query, 'guestcount');

    $query = $db->sql_query("
        SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup, u.displaygroup
        FROM sessions s
        LEFT JOIN users u ON (s.uid = u.id)
        WHERE s.uid != 0 AND s.time > $timecut AND location1 = $fid AND nopermission != 1
        ORDER BY u.username ASC, s.time DESC
    ");

    while ($user = $db->fetch_array($query)) {
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

    eval('$usersbrowsing = "' . $templates->get('forumdisplay_usersbrowsing') . '";');
}

// ─── Правила форума ───────────────────────────────────────────────────────────
$forumrules = '';
$bgcolor    = 'trow1';

// ─── Видимые состояния тредов ─────────────────────────────────────────────────
$visible_states = ['1'];
$is_mod         = is_mod($usergroups);

if ($is_mod) {
    eval('$inlinemodcol = "' . $templates->get('forumdisplay_inlinemoderation_col') . '";');
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
        $query        = $db->simple_select('tsf_forums', 'threads, unapprovedthreads', 'fid=' . (int)$fid);
        $forum_threads = $db->fetch_array($query);

        if (in_array(1, $visible_states))  { $threadcount += (int)$forum_threads['threads']; }
        if (in_array(-1, $visible_states)) { $threadcount += (int)($forum_threads['deletedthreads'] ?? 0); }

        if (in_array(0, $visible_states)) {
            $threadcount += (int)$forum_threads['unapprovedthreads'];
        } elseif ($CURUSER['id'] && ($showownunapproved ?? false)) {
            $query        = $db->simple_select('tsf_threads t', 'COUNT(tid) AS threads', "fid = '$fid' AND t.visible=0 AND t.uid=" . (int)$CURUSER['id']);
            $threadcount += (int)$db->fetch_field($query, 'threads');
        }
    } else {
        $query       = $db->simple_select('tsf_threads t', 'COUNT(tid) AS threads', "fid = '$fid' $tuseronly $tvisibleonly $datecutsql2 $prefixsql2");
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

// ─── Рейтинг тредов ──────────────────────────────────────────────────────────
$ratingcol    = '';
$ratingsort   = '';
$allowthreadratings = '0';
$ratingadd    = '';

if ($allowthreadratings != 0 && $foruminfo['allowtratings'] != 0 && $fpermissions['canviewthreads'] != 0) {
    $lang->load('ratethread');
    $ratingadd = ($db->type === 'pgsql')
        ? 'CASE WHEN t.numratings=0 THEN 0 ELSE t.totalratings/t.numratings::numeric END AS averagerating, '
        : '(t.totalratings/t.numratings) AS averagerating, ';

    $lpbackground = 'trow2';
    eval('$ratingcol  = "' . $templates->get('forumdisplay_threadlist_rating') . '";');
    eval('$ratingsort = "' . $templates->get('forumdisplay_threadlist_sortrating') . '";');
    $colspan = '7';
} else {
    if ($sortfield === 'averagerating') {
        $t = 't.'; $sortfield = 'lastpost';
    }
    $lpbackground = 'trow1';
    $colspan      = '6';
}

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
        FROM tsf_announcements a
        LEFT JOIN users u ON (u.id = a.uid)
        WHERE a.startdate <= '$time' AND (a.enddate >= '$time' OR a.enddate = '0') AND ($sql OR fid = '-1')
        ORDER BY a.startdate DESC $limit
    ");

    $cookie = [];
    if (isset($mybb->cookies['mybb']['announcements'])) {
        $cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']), false);
    }

    $bgcolor = alt_trow(true);

    while ($announcement = $db->fetch_array($query)) {
        [$new_class, $folder] = ($announcement['startdate'] > $CURUSER['lastvisit'] && empty($cookie[$announcement['aid']]))
            ? [' class="subject_new"', 'newfolder']
            : [' class="subject_old"', 'folder'];

        if (isset($cookie[$announcement['aid']]) && $cookie[$announcement['aid']] < $CURUSER['lastvisit']) {
            unset($cookie[$announcement['aid']]);
        }

        $announcement['announcementlink'] = get_announcement_link((int)$announcement['aid']);
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
        eval('$announcements .= "' . $templates->get('forumdisplay_announcements_announcement') . '";');
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
        SELECT t.*, {$ratingadd}t.username AS threadusername, u.username
        FROM tsf_threads t
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
    eval('$selectall = "' . $templates->get('forumdisplay_inlinemoderation_selectall') . '";');
}

$tids_str = !empty($tids) ? implode(',', $tids) : '';

// ─── Иконки "точка" (участие пользователя) ───────────────────────────────────
$dotfolders = '1';

if ($dotfolders != 0 && $CURUSER['id'] && !empty($threadcache) && $tids_str !== '') {
    $query = $db->simple_select('tsf_posts', 'DISTINCT tid,uid', "uid='{$CURUSER['id']}' AND tid IN ({$tids_str}) {$visibleonly}");
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
    $query = $db->simple_select('tsf_threadsread', '*', "uid='{$CURUSER['id']}' AND tid IN ({$tids_str})");
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
    $query      = $db->simple_select('tsf_forumsread', 'dateline', "fid='{$fid}' AND uid='{$CURUSER['id']}'");
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

        $rating = '';

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
            eval('$modbit = "' . $templates->get('forumdisplay_thread_modbit') . '";');
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
            eval('$unapproved_posts = "' . $templates->get('forumdisplay_thread_unapproved_posts') . '";');
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
        eval('$threads .= "' . $templates->get('forumdisplay_thread') . '";');
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

        $query = $db->simple_select('modtools', 'tid, name', $whereClause);
        while ($tool = $db->fetch_array($query)) {
            $tool['name'] = htmlspecialchars_uni($tool['name']);
            eval('$customthreadtools .= "' . $templates->get('forumdisplay_inlinemoderation_custom_tool') . '";');
        }

        if ($customthreadtools) {
            eval('$customthreadtools = "' . $templates->get('forumdisplay_inlinemoderation_custom') . '";');
        }

        $inlinemodopenclose = $inlinemodstickunstick = $inlinemodsoftdelete = '';
        $inlinemodrestore   = $inlinemoddelete       = $inlinemodmanage    = '';
        $inlinemodapproveunapprove = '';

        eval('$inlinemodopenclose      = "' . $templates->get('forumdisplay_inlinemoderation_openclose') . '";');
        eval('$inlinemodstickunstick   = "' . $templates->get('forumdisplay_inlinemoderation_stickunstick') . '";');
        eval('$inlinemodapproveunapprove = "' . $templates->get('forumdisplay_inlinemoderation_approveunapprove') . '";');

        $inlinemoddelete = '<option value="multideletethreads">Delete Threads Permanently</option>';
        $inlinemodmanage = '<option value="multimovethreads">Move / Copy Threads</option>';

        $standardthreadtools = '<optgroup label="Standard Tools">'
            . $inlinemodopenclose
            . $inlinemodstickunstick
            . $inlinemoddelete
            . $inlinemodmanage
            . $inlinemodapproveunapprove
            . '</optgroup>';

        eval('$inlinemod = "' . $templates->get('forumdisplay_inlinemoderation') . '";');
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
    $query = $db->simple_select('tsf_forumsubscriptions', 'fid', "fid='{$fid}' AND uid='{$CURUSER['id']}'", ['limit' => 1]);

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
        $threads = '<div class="ps-3 pe-3"><div class="alert alert-danger">{$lang->nopermission}</div></div>';
    }

    if (!$threadcount && $fpermissions['canviewthreads'] == 1) {
        $threads = '<div class="p-3">Sorry, but there are currently no threads in this forum with the specified date and time limiting options</div>';
    }

    if ($foruminfo['password'] !== '') {
        $clearstoredpass = ' | <a href="misc.php?action=clearpass&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '">{$lang->clear_stored_password}</a>';
    }

    eval('$gobutton = "' . $templates->get('gobutton') . '";');

    $forumsort = '';
    eval('$forumsort = "' . $templates->get('forumdisplay_forumsort') . '";');

    $plugins->run_hooks('forumdisplay_threadlist');

    eval('$rssdiscovery = "' . $templates->get('forumdisplay_rssdiscovery') . '";');
    eval('$threadslist  = "' . $templates->get('forumdisplay_threadlist') . '";');
} else {
    $rssdiscovery = $threadslist = '';

    if (empty($forums)) {
        error($lang->forumdisplay['error_containsnoforums']);
    }
}

$plugins->run_hooks('forumdisplay_end');

$foruminfo['name'] = strip_tags($foruminfo['name']);

eval('$forums = "' . $templates->get('forumdisplay') . '";');

stdhead('title');
build_breadcrumb();
echo $forums;
stdfoot();