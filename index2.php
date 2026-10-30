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

// ─── Хелперы вёрстки (всё под .fx-page) ──────────────────────────────────────
function fx_l(string $key, string $fallback): string
{
    global $lang;
    return (string)($lang->index[$key] ?? $fallback);
}

function fx_fmt(string $key, string $fallback, string|int|float ...$args): string
{
    $s = fx_l($key, $fallback);
    foreach ($args as $i => $a) {
        $n = $i + 1;
        $s = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$a, $s);
    }
    return $s;
}

function fx_kpi(string $icon, string $soft, string $value, string $label, string $href = ''): string
{
    $tag = $href !== '' ? 'a href="' . $href . '"' : 'div';
    $end = $href !== '' ? 'a' : 'div';
    return '<' . $tag . ' class="st-kpi">'
         . '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . $label . '</div></div>'
         . '</' . $end . '>';
}

function fx_hstat(string $icon, string $value, string $label, string $mod = ''): string
{
    return '<div class="fx-hstat' . ($mod !== '' ? ' fx-hstat--' . $mod : '') . '">'
         . '<i class="fa-solid ' . $icon . '"></i>'
         . '<div><div class="fx-hstat__val">' . $value . '</div><div class="fx-hstat__lbl">' . $label . '</div></div>'
         . '</div>';
}

function fx_pill(string $href, string $icon, string $text, string $variant = 'btn-outline-secondary'): string
{
    return '<a href="' . $href . '" class="btn btn-sm ' . $variant . ' st-pill fx-pill"><i class="fa-solid ' . $icon . '"></i><span>' . $text . '</span></a>';
}

/* ── Logout link ─────────────────────────────────────────────────────── */
$logoutlink = '';
if (($CURUSER['id'] ?? 0) != 0) {
    $logoutlink = '<a href="member.php?action=logout&amp;logoutkey=' . $CURUSER['logoutkey'] . '"><i class="fa-solid fa-right-from-bracket"></i> ' . $lang->global['index_logout'] . '</a>';
}

/* ── Who's Online ────────────────────────────────────────────────────── */
$timesearch  = TIMENOW - (int)$wolcutoffmins;
$membercount = $guestcount = $anoncount = 0;
$forum_viewers = $doneusers = $onlinemembers = [];

// Forum viewer counts (guests per forum) — гостей в sessions больше нет,
// но запрос оставлен на случай, если появятся.
$q = $db->sql_query_prepared("
    SELECT location1, COUNT(DISTINCT ip) AS guestcount
    FROM sessions
    WHERE uid = 0 AND location1 != 0 AND time > ?
    GROUP BY location1
", [$timesearch]);
while ($q && ($loc = $db->fetch_array($q))) {
    $forum_viewers[$loc['location1']] = ($forum_viewers[$loc['location1']] ?? 0) + $loc['guestcount'];
}

// Total guest count
$q = $db->sql_query_prepared(
    "SELECT COUNT(DISTINCT ip) AS guestcount FROM sessions WHERE uid = 0 AND time > ?",
    [$timesearch]
);
$guestcount = $q ? (int)$db->fetch_field($q, 'guestcount') : 0;

// Members (гостей и ботов в sessions нет)
$q = $db->sql_query_prepared("
    SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1,
           u.username, u.invisible, u.usergroup
    FROM sessions s
    LEFT JOIN users u ON s.uid = u.id
    WHERE s.uid != 0 AND s.time > ?
    ORDER BY u.username ASC, s.time DESC
", [$timesearch]);

while ($q && ($user = $db->fetch_array($q))) {
    if ($user['uid'] > 0) {
        if (empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time']) {
            if ($user['invisible'] == 1) ++$anoncount;
            ++$membercount;

            $can_see = $user['invisible'] != 1
                || $usergroups['canviewwolinvis'] == 1
                || $user['uid'] == $CURUSER['id'];

            if ($can_see) {
                $invisiblemark = $user['invisible'] == 1 ? '<span class="fx-invis" title="' . htmlspecialchars_uni(fx_l('lbl_invisible', 'Invisible')) . '">*</span>' : '';
                $user['username']    = format_name(htmlspecialchars_uni($user['username']), (int)($user['usergroup'] ?? 1));
                $user['profilelink'] = build_profile_link($user['username'], $user['uid']);
                $onlinemembers[]     = '<span class="fx-chip">' . $user['profilelink'] . $invisiblemark . '</span>';
            }
            $doneusers[$user['uid']] = $user['time'];
        }
    }

    if ($user['location1']) {
        $forum_viewers[$user['location1']] = ($forum_viewers[$user['location1']] ?? 0) + 1;
    }
}

$onlinelist  = $onlinemembers;
$onlinecount = $membercount + $guestcount;

/* ── Birthdays ───────────────────────────────────────────────────────── */
$bdays = $birthdays = '';
$bdaycount = $hiddencount = 0;
$bdaydate   = my_datee('j-n', TIMENOW, '', 0);
$year       = (int)my_datee('Y', TIMENOW, '', 0);

$bdaycache = $cache->read('birthdays');
if (!is_array($bdaycache)) {
    $cache->update_birthdays();
    $bdaycache = $cache->read('birthdays');
}

$today_bdays = $bdaycache[$bdaydate]['users']       ?? [];
$hiddencount = (int)($bdaycache[$bdaydate]['hiddencount'] ?? 0);

if (!empty($today_bdays)) {
    $showbirthdayspostlimit = 0;

    if ($showbirthdayspostlimit > 0) {
        $bday_ids = array_map('intval', array_column($today_bdays, 'id'));
        if ($bday_ids) {
            $ph = implode(',', array_fill(0, count($bday_ids), '?'));
            $q  = $db->sql_query_prepared("SELECT id, postnum FROM users WHERE id IN ({$ph})", $bday_ids);
            while ($q && ($bu = $db->fetch_array($q))) {
                if ($bu['postnum'] < $showbirthdayspostlimit) {
                    $today_bdays = array_filter($today_bdays, fn($u) => $u['id'] != $bu['id']);
                }
            }
        }
    }

    $bday_ugs = $cache->read('usergroups') ?: [];
    foreach ($today_bdays as $bdayuser) {
        $bday_gid = (int)($bdayuser['usergroup'] ?? 1);
        if (isset($bday_ugs[$bday_gid]) && (int)($bday_ugs[$bday_gid]['showinbirthdaylist'] ?? 1) !== 1) continue;

        $bday = explode('-', (string)$bdayuser['birthday']);
        $age  = ($year > (int)($bday[2] ?? 0) && !empty($bday[2]))
            ? '<span class="fx-age">' . ($year - (int)$bday[2]) . '</span>'
            : '';

        $bdayuser['username']    = format_name(htmlspecialchars_uni($bdayuser['username']), $bday_gid);
        $bdayuser['profilelink'] = build_profile_link($bdayuser['username'], $bdayuser['id']);
        $bdays .= '<span class="fx-chip fx-chip--bday"><i class="fa-solid fa-cake-candles"></i>' . $bdayuser['profilelink'] . $age . '</span>';
        ++$bdaycount;
    }
}

if ($hiddencount > 0) {
    $bdays .= '<span class="fx-chip fx-chip--muted">' . $hiddencount . ' ' . fx_l('birthdayhidden', 'hidden') . '</span>';
}

if ($bdaycount > 0 || $hiddencount > 0) {
    $birthdays = '<div class="fx-bdays">'
        . '<div class="fx-sub"><i class="fa-solid fa-cake-candles"></i>' . fx_l('todays_birthdays', "Today's birthdays") . '</div>'
        . '<div class="fx-chips">' . $bdays . '</div>'
        . '</div>';
}

/* ── Board Statistics ────────────────────────────────────────────────── */
$stats = $cache->read('stats');
if (!is_array($stats)) $stats = [];

$today_start = strtotime('today');
$q = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM users WHERE added >= ?", [$today_start]);
$new_members_today = $q ? (int)$db->fetch_field($q, 'cnt') : 0;

$newestmember = !empty($stats['lastusername'])
    ? build_profile_link(htmlspecialchars_uni($stats['lastusername']), (int)$stats['lastuid'])
    : '—';

$onlinestats = $cache->read('onlinestats');
if (!is_array($onlinestats)) {
    $onlinestats = [];
}
$most_ever      = (int)($onlinestats['most_ever'] ?? 0);
$most_ever_time = (int)($onlinestats['most_ever_time'] ?? 0);

/* ── Forum list ──────────────────────────────────────────────────────── */
if (($CURUSER['id'] ?? 0) == 0) {
    $q = $db->sql_query_prepared("SELECT * FROM forums WHERE active!=0 ORDER BY pid, disporder");
    $forumsread = [];
    if (isset($mybb->cookies['mybb']['forumread'])) {
        $forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
    }
} else {
    $q = $db->sql_query_prepared("
        SELECT f.*, fr.dateline AS lastread
        FROM forums f
        LEFT JOIN forumsread fr ON (fr.fid = f.fid AND fr.uid = ?)
        WHERE f.active != 0
        ORDER BY pid, disporder
    ", [$CURUSER['id']]);
}

$fcache = [];

while ($q && ($forum = $db->fetch_array($q))) {
    if (($CURUSER['id'] ?? 0) == 0 && !empty($forumsread[$forum['fid']])) {
        $forum['lastread'] = $forumsread[$forum['fid']];
    }
    $fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
}

$forumpermissions = forum_permissions();
$site_moderators = [];
$q = $db->sql_query_prepared(
    "SELECT id, username, usergroup FROM users WHERE usergroup = ? ORDER BY username",
    [UC_MODERATOR]
);
while ($q && ($m = $db->fetch_array($q))) {
    $site_moderators[] = build_profile_link(
        format_name(htmlspecialchars_uni($m['username']), (int)$m['usergroup']),
        (int)$m['id']
    );
}
$moderators_list = implode($lang->global['comma'] . ' ', $site_moderators);
$excols           = 'index';
$permissioncache  = null;
$bgcolor          = 'trow1';
$showdepth        = 3;

$forum_list = build_forumbits(0, 1, 'st');
$forums = (!empty($forum_list) && !empty($forum_list['forum_list']))
    ? $forum_list['forum_list']
    : '<section class="st-card"><div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-regular fa-comments"></i></div>'
      . fx_l('lbl_no_forums', 'No forums found') . '</div></section>';

$plugins->run_hooks('index_end');

/* ── DST auto-detection ──────────────────────────────────────────────── */
$auto_dst_detection = '';
if ($CURUSER['id'] > 0 && $CURUSER['dstcorrection'] == 2) {
    $timezone = (float)$CURUSER['timezone'] + $CURUSER['dst'];
    $auto_dst_detection = '<script>var USER_TIMEZONE = ' . json_encode((string)$timezone) . ';</script>'
                        . '<script src="' . $BASEURL . '/scripts/dst.js"></script>';
}

/* ── Нижние ссылки ───────────────────────────────────────────────────── */
$contactlink = 'contact.php';
if (!my_validate_url($contactlink, true) && !str_starts_with($contactlink, 'mailto:')) {
    $contactlink = $BASEURL . '/' . $contactlink;
}

$links = fx_pill(htmlspecialchars_uni($contactlink), 'fa-envelope', $lang->index['bottomlinks_contactus'])
       . fx_pill('showteam.php', 'fa-address-book', $lang->index['bottomlinks_forumteam'])
       . fx_pill($BASEURL . '/misc.php?action=markread&amp;my_post_key=' . $mybb->post_code, 'fa-check-double', $lang->global['bottomlinks_markread'])
       . fx_pill($BASEURL . '/misc.php?action=syndication', 'fa-square-rss', $lang->global['bottomlinks_syndication'])
       . fx_pill('#top', 'fa-circle-arrow-up', $lang->global['bottomlinks_returntop']);

/* ── Блоки страницы ──────────────────────────────────────────────────── */
$page_head = '<header class="st-card fx-head">'
    . '<div class="fx-head__top">'
    .   '<div class="st-head__icon fx-head__icon"><i class="fa-solid fa-comments"></i></div>'
    .   '<div class="fx-head__text">'
    .     '<h1 class="st-title">' . htmlspecialchars_uni(fx_l('sec_forums', 'Forums')) . '</h1>'
    .     '<div class="fx-head__sub">' . htmlspecialchars_uni(strip_tags((string)$SITENAME)) . '</div>'
    .   '</div>'
    .   '<div class="fx-head__actions">'
    .     fx_pill('search.php?action=getnew', 'fa-bolt', fx_l('lnk_new_posts', 'New posts'), 'btn-primary')
    .     fx_pill('search.php', 'fa-magnifying-glass', fx_l('lnk_search', 'Search'))
    .   '</div>'
    . '</div>'
    . '<div class="fx-hstats">'
    .   fx_hstat('fa-message', ts_nf((int)($stats['numposts'] ?? 0)), fx_l('lbl_total_posts', 'Posts'))
    .   fx_hstat('fa-comments', ts_nf((int)($stats['numthreads'] ?? 0)), fx_l('lbl_total_threads', 'Threads'))
    .   fx_hstat('fa-user-group', ts_nf((int)($stats['numusers'] ?? 0)), fx_l('lbl_total_members', 'Members'))
    .   fx_hstat('fa-circle', ts_nf($onlinecount), fx_l('lbl_online', 'Online'), 'online')
    . '</div>'
    . '</header>';

$whosonline = '<section class="st-card fx-online">'
    . '<div class="st-card__head">'
    .   '<span class="st-ico st-soft-success"><i class="fa-solid fa-users"></i></span>'
    .   '<span>' . fx_l('sec_whos_online', 'Who\'s online') . '</span>'
    .   '<span class="ms-auto fx-muted">' . fx_fmt('lbl_active_minutes', 'active in the last {1} minutes', (int)$wolcutoffmins) . '</span>'
    . '</div>'
    . '<div class="st-card__body">'
    .   '<div class="fx-kpis">'
    .     fx_kpi('fa-user', 'st-soft-primary', ts_nf($membercount), fx_l('lbl_members', 'Members'))
    .     fx_kpi('fa-user-secret', 'st-soft-muted', ts_nf($guestcount), fx_l('lbl_guests', 'Guests'))
    .     fx_kpi('fa-eye-slash', 'st-soft-info', ts_nf($anoncount), fx_l('lbl_invisible', 'Invisible'))
    .   '</div>'
    .   ($onlinelist
            ? '<div class="fx-chips">' . implode('', $onlinelist) . '</div>'
            : '<div class="fx-muted fx-nobody">' . fx_l('lbl_nobody_online', 'No members online right now') . '</div>')
    .   $birthdays
    . '</div>'
    . '</section>';

$boardstats = '<section class="st-card fx-stats">'
    . '<div class="st-card__head">'
    .   '<span class="st-ico st-soft-info"><i class="fa-solid fa-chart-line"></i></span>'
    .   '<span>' . fx_l('sec_board_stats', 'Board statistics') . '</span>'
    .   '<a href="' . $BASEURL . '/stats.php" class="ms-auto fx-head-link fx-muted">' . fx_l('btn_full_stats', 'Full statistics') . ' <i class="fa-solid fa-arrow-right"></i></a>'
    . '</div>'
    . '<div class="st-card__body">'
    .   '<div class="fx-kpis fx-kpis--2">'
    .     fx_kpi('fa-user-plus', 'st-soft-success', ts_nf($new_members_today), fx_l('lbl_new_today', 'New today'))
    .     fx_kpi('fa-trophy', 'st-soft-warning', ts_nf($most_ever), fx_l('lbl_peak_online', 'Peak online'))
    .   '</div>'
    .   ($most_ever > 0 ? '<div class="fx-peak"><i class="fa-regular fa-calendar"></i>' . my_datee($dateformat, $most_ever_time) . ', ' . my_datee($timeformat, $most_ever_time) . '</div>' : '')
    .   '<div class="fx-newest"><span class="fx-newest__lbl"><i class="fa-solid fa-star"></i>' . fx_l('lbl_newest_member', 'Newest member') . '</span>'
    .   '<span class="fx-newest__val">' . $newestmember . '</span></div>'
    . '</div>'
    . '</section>';

/* ── Output ──────────────────────────────────────────────────────────── */
stdhead($SITENAME . ' FORUMS');

$json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=61">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/index2.css?ver=12">';
echo '<script>'
   . 'if (typeof lang === "object" && lang) {'
   .   'lang.no_new_posts = ' . json_encode($lang->index['no_new_posts'] ?? '', $json_flags) . ';'
   .   'lang.click_mark_read = ' . json_encode($lang->index['click_mark_read'] ?? '', $json_flags) . ';'
   . '}'
   . '</script>';
echo '<script src="' . $BASEURL . '/scripts/forum.js"></script>';

echo '<div class="container-md st-page fx-page">'
   . $page_head
   . '<div class="fx-forums">' . $forums . '</div>'
   . '<div class="fx-bottom">' . $whosonline . $boardstats . '</div>'
   . '<nav class="st-card st-toolbar fx-links">' . $links . '</nav>'
   . $auto_dst_detection
   . '</div>';

stdfoot();