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
require_once INC_PATH . '/functions_forum_jump.php';
require_once INC_PATH . '/functions_parent_list.php';

$lang->load('forumdisplay');

// ─── Хелперы вёрстки (всё под .fd-page, общий st-ui.css) ─────────────────────
// Lang-строка как есть (в ланге forumdisplay есть строки с HTML - *_html),
// с fallback для новых ключей, которых ещё может не быть в файлах ланга.
function fd_l(string $key, string $fallback = ''): string
{
    global $lang;
    return (string)($lang->forumdisplay[$key] ?? ($fallback !== '' ? $fallback : $key));
}

// Lang-строка с {1}/{2}… (lang->load() превращает их в %1$s - заменяем оба).
function fd_fmt(string $key, string $fallback, string|int|float ...$args): string
{
    $s = fd_l($key, $fallback);
    foreach ($args as $i => $a) {
        $n = $i + 1;
        $s = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$a, $s);
    }
    return $s;
}

// Число для вывода: ts_nf() только для чисел (у перемещённых тем там '-').
function fd_num(mixed $v): string
{
    return is_numeric($v) ? ts_nf((int)$v) : htmlspecialchars_uni((string)$v);
}

// Плашка-пилюля.
function fd_badge(string $soft, string $icon, string $text = '', string $title = ''): string
{
    return '<span class="st-badge ' . $soft . '"' . ($title !== '' ? ' title="' . htmlspecialchars_uni($title) . '"' : '') . '>'
         . ($icon !== '' ? '<i class="fa-solid ' . $icon . '"></i>' : '')
         . ($text !== '' ? '<span>' . $text . '</span>' : '')
         . '</span>';
}

// Аватар: картинка или буква. $username - СЫРОЕ имя.
function fd_avatar(array $u, int $size = 32): string
{
    $ua = format_avatar((string)($u['avatar'] ?? ''), (string)($u['avatardimensions'] ?? ''), '50|50');
    $img = (string)($ua['image'] ?? '');
    if (str_starts_with($img, '<')) return $img;
    if ($img !== '' && $img !== 'default' && empty($ua['is_placeholder'])) {
        return '<img src="' . htmlspecialchars_uni($img) . '" class="fd-ava" style="--fd-ava:' . $size . 'px" alt="" loading="lazy">';
    }
    $letter = htmlspecialchars_uni(mb_strtoupper(mb_substr((string)($u['username'] ?? ''), 0, 1)) ?: '?');
    return '<span class="fd-ava fd-ava--letter" style="--fd-ava:' . $size . 'px">' . $letter . '</span>';
}

// Заглушка пустого списка (.st-empty).
function fd_empty(string $icon, string $soft, string $title, string $hint): string
{
    return '<div class="st-empty">'
         .   '<div class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></div>'
         .   '<div class="fd-empty__title">' . $title . '</div>'
         .   ($hint !== '' ? '<div class="fd-empty__hint">' . $hint . '</div>' : '')
         . '</div>';
}

// Звёзды рейтинга (10-звёздочная шкала), FA6.
function fd_rating(float $avg, int $num): string
{
    $stars = '';
    for ($i = 1; $i <= 10; $i++) {
        $stars .= match (true) {
            $avg >= $i       => '<i class="fa-solid fa-star"></i>',
            $avg >= $i - 0.5 => '<i class="fa-solid fa-star-half-stroke"></i>',
            default          => '<i class="fa-regular fa-star"></i>',
        };
    }
    return '<span class="fd-rating" title="' . round($avg, 1) . ' / 10">'
         . '<span class="fd-rating__stars">' . $stars . '</span>'
         . '<span class="fd-rating__val">' . round($avg, 1) . ' <span class="fd-rating__num">(' . ts_nf($num) . ')</span></span>'
         . '</span>';
}

// <select> фильтра: [value => label], $sel - выбранное значение.
function fd_select(string $name, array $opts, string $sel, string $icon): string
{
    $html = '<label class="fd-filter__field"><i class="fa-solid ' . $icon . '"></i>'
          . '<select name="' . $name . '" class="form-select form-select-sm">';
    foreach ($opts as $v => $label) {
        $html .= '<option value="' . htmlspecialchars_uni((string)$v) . '"' . ((string)$v === $sel ? ' selected' : '') . '>' . $label . '</option>';
    }
    return $html . '</select></label>';
}

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
    stderr($lang->forumdisplay['err_invalid_forum'], sprintf($lang->forumdisplay['err_forum_not_found'], $SITENAME), 404, 'forum');
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

    $query = $db->sql_query_prepared("
        SELECT *
        FROM forums
        WHERE active != 0
        ORDER BY pid, disporder
    ");
} else {
    $query = $db->sql_query_prepared("
        SELECT f.*, fr.dateline AS lastread
        FROM forums f
        LEFT JOIN forumsread fr ON (fr.fid = f.fid AND fr.uid = ?)
        WHERE f.active != 0
        ORDER BY pid, disporder
    ", [(int)$CURUSER['id']]);
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
$child_forums  = build_forumbits($fid, 2, 'st');   // 'st' - общий рендер строк форумов (st-ui.css)
if (!empty($child_forums) && !empty($child_forums['forum_list'])) {
    $forums        = $child_forums['forum_list'];
    $sub_forums_in = sprintf($lang->forumdisplay['sec_subforums_in'], $foruminfo['name']); // name is already escaped

    $subforums = '<section class="st-card fd-subforums">'
        . '<div class="st-card__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-folder-tree"></i></span>'
        . '<span class="fd-subforums__title">' . $sub_forums_in . '</span></div>'
        . '<div class="fd-subforums__body">' . $forums . '</div>'
        . '</section>';
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
    $newthread = '<a href="newthread.php?fid=' . $fid . '" class="btn btn-primary st-pill fd-btn" role="button">'
               . '<i class="fa-solid fa-pen"></i><span>' . $lang->forumdisplay['btn_post_thread'] . '</span></a>';
}

// Поиск по форуму. postcode обязателен: search.php?action=do_search без него
// отвечает «неверный поиск» (verify_post_check), раньше форма не работала.
$searchforum = '';
if ($fpermissions['cansearch'] != 0 && $foruminfo['type'] === 'f') {
    $searchforum = '<form action="search.php" method="post" class="fd-search" role="search">'
        . '<input type="hidden" name="action" value="do_search">'
        . '<input type="hidden" name="postcode" value="' . generate_post_check() . '">'
        . '<input type="hidden" name="forums[]" value="' . $fid . '">'
        . '<input type="hidden" name="postthread" value="1">'
        . '<div class="input-group input-group-sm st-subject">'
        .   '<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>'
        .   '<input type="text" name="keywords" class="form-control" placeholder="' . $lang->forumdisplay['ph_search_keywords'] . '" aria-label="' . $lang->forumdisplay['ph_search_keywords'] . '">'
        .   '<button type="submit" class="btn btn-primary">' . $lang->global['search_button'] . '</button>'
        . '</div>'
        . '</form>';
}

// ─── Статистика форума ────────────────────────────────────────────────────────
$has_announcements = false;
$has_modtools      = false;
$forum_stats       = $cache->read('forumsdisplay');

if (is_array($forum_stats)) {
    $has_modtools      = !empty($forum_stats[-1]['modtools'])      || !empty($forum_stats[$fid]['modtools']);
    $has_announcements = !empty($forum_stats[-1]['announcements']) || !empty($forum_stats[$fid]['announcements']);
}

// ─── Объявления в родительских форумах ───────────────────────────────────────
foreach (explode(',', $parentlist) as $mfid) {
    if (!empty($forum_stats[(int)$mfid]['announcements'])) {
        $has_announcements = true;
    }
}

// ─── Модераторы ───────────────────────────────────────────────────────────────
// Модераторы сайта — пользователи группы UC_MODERATOR (таблицы moderators больше нет).
// Раньше здесь читался $moderatorcache, который в этом файле нигде не задавался.
$mod_links = [];
$q = $db->sql_query_prepared(
    "SELECT id, username, usergroup FROM users WHERE usergroup = ? ORDER BY username",
    [UC_MODERATOR]
);
while ($q && ($m = $db->fetch_array($q))) {
    $mod_links[] = '<a href="' . get_profile_link((int)$m['id']) . '">'
        . format_name(htmlspecialchars_uni($m['username']), (int)$m['usergroup']) . '</a>';
}
$moderators = implode($lang->global['comma'] . ' ', $mod_links);
$moderatedby = $moderators
    ? '<span class="fd-mods"><i class="fa-solid fa-shield-halved"></i><span>' . $lang->forumdisplay['lbl_moderated_by'] . ' ' . $moderators . '</span></span>'
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
    $query = $db->sql_query_prepared("
        SELECT s.ip, s.uid, u.username, s.time, u.invisible, u.usergroup
        FROM sessions s
        LEFT JOIN users u ON (s.uid = u.id)
        WHERE s.time > ? AND s.location1 = ? AND s.nopermission != 1
        ORDER BY u.username ASC, s.time DESC
    ", [(int)$timecut, (int)$fid]);
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
                $user['username']    = format_name(htmlspecialchars_uni($user['username']), (int)($user['usergroup'] ?? 1));
                $user['profilelink'] = build_profile_link($user['username'], $user['uid']);
                $onlinemembers      .= $comma . $user['profilelink'] . $invisiblemark;
                $comma               = ', ';
            }
        }
    }
    $guestcount = count($guest_ips);

    $guestsonline = $guestcount
        ? sprintf($lang->forumdisplay['lbl_guests_browsing'], $guestcount)
        : '';
    if ($CURUSER['invisible'] == 1) {
        --$inviscount;
    }
    $invisonline = ($inviscount > 0 && ($usergroups['canviewwolinvis'] ?? 0) != 1)
        ? sprintf($lang->forumdisplay['lbl_invis_browsing'], $inviscount)
        : '';
    $onlinesep  = ($invisonline !== '' && $onlinemembers) ? ', ' : '';
    $onlinesep2 = (($invisonline !== '' && $guestcount) || ($onlinemembers && $guestcount)) ? ', ' : '';
    $browsing_list = trim($onlinemembers . ' ' . $onlinesep . ' ' . $invisonline . ' ' . $onlinesep2 . ' ' . $guestsonline);
    $usersbrowsing = '<section class="st-card fd-browsing">'
        . '<div class="st-card__body">'
        .   '<span class="st-ico st-soft-success"><i class="fa-solid fa-users"></i></span>'
        .   '<div class="fd-browsing__text"><strong>' . $lang->forumdisplay['lbl_users_browsing'] . '</strong> ' . $browsing_list . '</div>'
        . '</div></section>';
}

// ─── Правила форума ───────────────────────────────────────────────────────────
$forumrules = '';
$bgcolor    = 'trow1';

// ─── Видимые состояния тредов ─────────────────────────────────────────────────
$visible_states = ['1'];
$is_mod         = is_mod($usergroups);

if ($is_mod) {
    $inlinemodcol = '<div class="form-check form-switch fd-allbox" title="' . htmlspecialchars_uni(fd_l('tip_select_all', 'Select all')) . '">'
        . '<input class="form-check-input" type="checkbox" name="allbox" id="allbox" onclick="inlineModeration.checkAll(this)">'
        . '</div>';

    $ismod       = true;
    $inlinecount = 0;
    $inlinemod   = '';
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
        $query        = $db->sql_query_prepared(
            "SELECT threads, unapprovedthreads FROM forums WHERE fid = ?",
            [(int)$fid]
        );
        $forum_threads = $db->fetch_array($query);

        if (in_array(1, $visible_states))  { $threadcount += (int)$forum_threads['threads']; }

        if (in_array(0, $visible_states)) {
            $threadcount += (int)$forum_threads['unapprovedthreads'];
        } elseif ($CURUSER['id'] && ($showownunapproved ?? false)) {
            $query        = $db->sql_query_prepared(
                "SELECT COUNT(tid) AS threads FROM threads t WHERE fid = ? AND t.visible=0 AND t.uid=?",
                [(int)$fid, (int)$CURUSER['id']]
            );
            $threadcount += (int)$db->fetch_field($query, 'threads');
        }
    } else {
        $query       = $db->sql_query_prepared(
            "SELECT COUNT(tid) AS threads FROM threads t WHERE fid = ? $tuseronly $tvisibleonly $datecutsql2 $prefixsql2",
            [(int)$fid]
        );
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

    $query = $db->sql_query_prepared("
        SELECT a.*, u.username
        FROM announcements a
        LEFT JOIN users u ON (u.id = a.uid)
        WHERE a.type IN ('forum', 'global')
          AND a.startdate <= ?
          AND (a.enddate >= ? OR a.enddate = '0')
          AND ($sql OR a.fid = '-1')
        ORDER BY a.startdate DESC $limit
    ", [(int)$time, (int)$time]);

    $cookie = [];
    if (isset($mybb->cookies['mybb']['announcements'])) {
        $cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']), false);
    }
    if (!is_array($cookie)) $cookie = [];

    $bgcolor = 'trow1';

    while ($query && ($announcement = $db->fetch_array($query))) {
        $ann_id = (int)$announcement['id'];
        $is_new = $announcement['startdate'] > $CURUSER['lastvisit'] && empty($cookie[$ann_id]);
        $new_class = $is_new ? 'subject_new' : 'subject_old';

        // раньше тут читался $cookie[$announcement['aid']] - такого поля нет
        if (isset($cookie[$ann_id]) && $cookie[$ann_id] < $CURUSER['lastvisit']) {
            unset($cookie[$ann_id]);
        }

        $announcement['announcementlink'] = get_announcement_link($ann_id);
        $announcement['subject']          = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));
        $postdate                         = my_datee('relative', $announcement['startdate']);
        $announcement['username']         = htmlspecialchars_uni($announcement['username']);
        $announcement['profilelink']      = build_profile_link($announcement['username'], $announcement['uid']);

        $rating       = '';
        $lpbackground = 'trow1';
        $modann       = '';

        $plugins->run_hooks('forumdisplay_announcement');

        $announcements .= '<div class="fd-item fd-item--ann">'
            . '<span class="st-ico st-soft-warning fd-item__ico"><i class="fa-solid fa-bullhorn"></i></span>'
            . '<div class="fd-item__body">'
            .   '<div class="fd-item__top">'
            .     fd_badge('st-soft-warning', 'fa-thumbtack', fd_l('lbl_announcement', 'Announcement'))
            .     '<a href="' . $announcement['announcementlink'] . '" class="fd-item__title ' . $new_class . '">' . $announcement['subject'] . '</a>'
            .   '</div>'
            .   '<div class="fd-item__meta"><span><i class="fa-solid fa-user"></i>' . $lang->forumdisplay['lbl_by'] . ' ' . $announcement['profilelink'] . '</span>'
            .   '<span><i class="fa-solid fa-clock"></i>' . $postdate . '</span></div>'
            . '</div>'
            . '</div>';
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

    $query = $db->sql_query_prepared("
        SELECT t.*, t.username AS threadusername, u.username
        FROM threads t
        LEFT JOIN users u ON (u.id = t.uid)
        WHERE t.fid = ? $tuseronly $tvisibleonly $datecutsql2 $prefixsql2
        ORDER BY t.sticky DESC, {$t}{$sortfield} $sortordernow $sortfield2
        LIMIT $start, $perpage
    ", [(int)$fid]);

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
// id selectAllrow / allSelectedrow и класс hiddenrow нужны inline_moderation.js.
// Раньше это были <tr> вне таблицы и они вообще не выводились на страницу.
$is_mod   = is_mod($usergroups);
$selectall = '';

if ($is_mod && $threadcount > $perpage) {
    $page_selected = sprintf($lang->forumdisplay['sel_page_html'], count($threadcache));
    $select_all    = sprintf($lang->forumdisplay['sel_select_all_html'], (int)$threadcount);
    $all_selected  = sprintf($lang->forumdisplay['sel_all_html'], (int)$threadcount);

    $selectall = '<div id="selectAllrow" class="hiddenrow fd-selectall">'
        . '<i class="fa-solid fa-circle-info"></i> ' . $page_selected
        . ' <a href="javascript:void(0)" onclick="inlineModeration.selectAll(); return false;">' . $select_all . '</a></div>'
        . '<div id="allSelectedrow" class="hiddenrow fd-selectall">'
        . '<i class="fa-solid fa-circle-check"></i> ' . $all_selected
        . ' <a href="javascript:void(0)" onclick="inlineModeration.clearChecked(); return false;">' . $lang->forumdisplay['btn_clear_selection'] . '</a></div>';
}

$tids_str = !empty($tids) ? implode(',', $tids) : '';

// ─── Иконки "точка" (участие пользователя) ───────────────────────────────────
$dotfolders = '1';

if ($dotfolders != 0 && $CURUSER['id'] && !empty($threadcache) && $tids_str !== '') {
    $tid_placeholders = implode(',', array_fill(0, count($tids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT DISTINCT tid,uid FROM posts WHERE uid=? AND tid IN ({$tid_placeholders}) {$visibleonly}",
        array_merge([(int)$CURUSER['id']], array_map('intval', array_values($tids)))
    );
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
    $tid_placeholders = implode(',', array_fill(0, count($tids), '?'));
    $query = $db->sql_query_prepared(
        "SELECT * FROM threadsread WHERE uid=? AND tid IN ({$tid_placeholders})",
        array_merge([(int)$CURUSER['id']], array_map('intval', array_values($tids)))
    );
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
    $query      = $db->sql_query_prepared(
        "SELECT dateline FROM forumsread WHERE fid = ? AND uid = ?",
        [(int)$fid, (int)$CURUSER['id']]
    );
    if ($db->num_rows($query) > 0) {
        $forum_read = (int)$db->fetch_field($query, 'dateline');
    }
    if ($forum_read == 0 || $forum_read < $read_cutoff) {
        $forum_read = $read_cutoff;
    }
} else {
    $forum_read = my_get_array_cookie('forumread', (string)$fid);
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

    // Аватары последних отписавшихся - одним запросом на страницу.
    // Раньше тут стоял плейсхолдер <avatarep_uid_[ID]> от плагина, которого нет,
    // и он выводился в HTML как есть (несуществующий тег).
    $lp_users = [];
    $lp_uids  = array_values(array_unique(array_filter(array_map(fn($t) => (int)$t['lastposteruid'], $threadcache))));
    if ($lp_uids) {
        $ph = implode(',', array_fill(0, count($lp_uids), '?'));
        $q  = $db->sql_query_prepared("SELECT id, username, avatar, avatardimensions FROM users WHERE id IN ({$ph})", $lp_uids);
        while ($q && ($u = $db->fetch_array($q))) $lp_users[(int)$u['id']] = $u;
    }

    $hottopic      = 20;
    $hottopicviews = 150;

    foreach ($threadcache as $thread) {
        $plugins->run_hooks('forumdisplay_thread');

        $moved    = explode('|', (string)$thread['closed']);
        $is_moved = $moved[0] === 'moved';
        $bgcolor  = $thread['visible'] == 0 ? 'trow_shaded' : 'trow1';

        $thread_type_class = ($thread['sticky'] == 1) ? ' forumdisplay_sticky' : ' forumdisplay_regular';
        $folder = $prefix = '';

        $thread['author'] = $thread['uid'];
        if (!$thread['username']) {
            $thread['username'] = $thread['profilelink'] = htmlspecialchars_uni($thread['threadusername'] ?: $lang->forumdisplay['lbl_guest']);
        } else {
            $thread['username']    = htmlspecialchars_uni($thread['username']);
            $thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
        }

        $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

        // ── Рейтинг ──────────────────────────────────────────────────────
        $rating = '';
        if (!empty($thread['numratings']) && $thread['numratings'] > 0) {
            $rating = fd_rating($thread['totalratings'] / $thread['numratings'], (int)$thread['numratings']);
        }

        // ─ Многостраничность треда ───────────────────────────────────────
        $thread['pages']     = 0;
        $thread['multipage'] = '';
        $thread['posts']     = $thread['replies'] + 1;

        if (is_mod($usergroups)) {
            $thread['posts'] += (int)($thread['unapprovedposts'] ?? 0);
        }

        if ($thread['posts'] > $f_postsperpage) {
            $thread['pages'] = (int)ceil($thread['posts'] / $f_postsperpage);
            $pagesstop       = $thread['pages'] > $maxmultipagelinks ? $maxmultipagelinks - 1 : $thread['pages'];

            $threadpages = '';
            for ($i = 1; $i <= $pagesstop; ++$i) {
                $threadpages .= '<a href="' . get_thread_link($thread['tid'], $i) . '">' . $i . '</a>';
            }
            if ($thread['pages'] > $maxmultipagelinks) {
                $threadpages .= '<span class="fd-pages__gap">…</span><a href="' . get_thread_link($thread['tid'], $thread['pages']) . '">' . $thread['pages'] . '</a>';
            }
            $thread['multipage'] = '<div class="fd-pages" aria-label="' . htmlspecialchars_uni(strip_tags($lang->forumdisplay['lbl_pages'])) . '">' . $threadpages . '</div>';
        }

        // ─ Инлайн-модерация ──────────────────────────────────────────────
        // Выбор хранится в localStorage (inline_moderation.js), исходно "не выбрано".
        $modbit = '';
        if (is_mod($usergroups)) {
            $multitid = (int)$thread['tid'];
            $modbit = '<div class="form-check form-switch fd-item__mod">'
                    . '<input class="form-check-input" type="checkbox" name="inlinemod_' . $multitid . '" id="inlinemod_' . $multitid . '" value="1">'
                    . '</div>';
        }

        if ($is_moved) {
            $prefix            = 'moved_prefix';
            $thread['tid']     = $moved[1];
            $thread['replies'] = '-';
            $thread['views']   = '-';
        }

        $thread['threadlink']   = get_thread_link($thread['tid']);
        $thread['lastpostlink'] = get_thread_link($thread['tid'], 0, 'lastpost');

        // ─ Состояние: точка / новое / горячее / закрыто ──────────────────
        $folder_label = '';
        $has_dot      = isset($thread['doticon']);
        if ($has_dot) {
            $folder       = 'dot_';
            $folder_label .= $lang->forumdisplay['tip_icon_dot'];
        }

        $gotounread = '';
        $new_class  = 'subject_old';
        $is_new     = false;

        if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
            $last_read = $thread['lastread'] ?? $read_cutoff;
        } else {
            $last_read = my_get_array_cookie('threadread', (string)$thread['tid']);
        }

        if ($forum_read > $last_read) {
            $last_read = $forum_read;
        }

        if ($thread['lastpost'] > $last_read && !$is_moved) {
            $is_new        = true;
            $folder       .= 'new';
            $folder_label .= $lang->forumdisplay['tip_icon_new'];
            $new_class     = 'subject_new';
            $thread['newpostlink'] = get_thread_link($thread['tid'], 0, 'newpost');
            $gotounread    = '<a href="' . $thread['newpostlink'] . '" class="fd-unread" title="' . htmlspecialchars_uni($lang->forumdisplay['tip_goto_first_unread']) . '" aria-label="' . htmlspecialchars_uni($lang->forumdisplay['tip_goto_first_unread']) . '"><i class="fa-solid fa-angles-right"></i></a>';
            $unreadpost    = 1;
        } else {
            $folder_label .= $lang->forumdisplay['tip_icon_no_new'];
        }

        $is_hot = is_numeric($thread['replies']) && is_numeric($thread['views'])
               && ($thread['replies'] >= $hottopic || $thread['views'] >= $hottopicviews);
        if ($is_hot) {
            $folder       .= 'hot';
            $folder_label .= $lang->forumdisplay['tip_icon_hot'];
        }

        $is_closed = $thread['closed'] == 1;
        if ($is_closed) {
            $folder       .= 'close';
            $folder_label .= $lang->forumdisplay['tip_icon_close'];
        }

        if ($is_moved) {
            $folder     = 'move';
            $gotounread = '';
        }

        $folder .= 'folder';
        $folder_label_attr = htmlspecialchars(strip_tags($folder_label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Иконка состояния (вместо спрайтов thread_status *folder)
        [$st_icon, $st_soft] = match (true) {
            $is_moved  => ['fa-arrow-right-arrow-left', 'st-soft-info'],
            $is_closed => ['fa-lock', $is_new ? 'st-soft-primary' : 'st-soft-muted'],
            $is_hot    => ['fa-fire', $is_new ? 'st-soft-danger' : 'st-soft-warning'],
            default    => ['fa-comments', $is_new ? 'st-soft-primary' : 'st-soft-muted'],
        };

        $inline_edit_tid   = $thread['tid'];
        $inline_edit_class = '';

        if (($thread['uid'] == $CURUSER['id'] && $thread['closed'] != 1 && $CURUSER['id'] != 0 && $can_edit_titles == 1)
            || ($is_mod ?? false)) {
            $inline_edit_class = 'subject_editable';
        }

        $lastposteruid  = (int)$thread['lastposteruid'];
        $lastposter_raw = (string)($thread['lastposter'] ?: $lang->forumdisplay['lbl_guest']);
        $lastposter     = htmlspecialchars_uni($lastposter_raw);
        $lastpostdate   = my_datee('relative', $thread['lastpost']);
        $lastposterlink = ($lastposteruid == 0) ? $lastposter : build_profile_link($lastposter, $lastposteruid);
        $lp_avatar      = fd_avatar($lp_users[$lastposteruid] ?? ['username' => $lastposter_raw], 34);

        // Раньше replies сначала форматировался ts_nf() ("1,234"), а потом
        // выводился через (int) - на 1000+ ответов показывалась «1».
        $replies_html = fd_num($thread['replies']);
        $views_html   = fd_num($thread['views']);
        $thread['replies'] = $replies_html;
        $thread['views']   = $views_html;

        // ─ Бейджи ────────────────────────────────────────────────────────
        $badges = '';
        if ($thread['sticky'] == 1) {
            $badges .= fd_badge('st-soft-warning', 'fa-thumbtack', fd_l('lbl_sticky', 'Sticky'));
        }
        if ($is_moved) {
            $badges .= fd_badge('st-soft-info', 'fa-arrow-right-arrow-left', $lang->forumdisplay['lbl_moved']);
        }
        if ($is_closed) {
            $badges .= fd_badge('st-soft-muted', 'fa-lock', '', strip_tags($lang->forumdisplay['tip_icon_close']));
        }
        if ($thread['visible'] == 0) {
            $badges .= fd_badge('st-soft-danger', 'fa-eye-slash', fd_l('lbl_unapproved', 'Unapproved'));
        }

        // ─ Неодобренные посты ────────────────────────────────────────────
        $unapproved_posts = '';
        if (($thread['unapprovedposts'] ?? 0) > 0) {
            $unapproved_posts_count = ($thread['unapprovedposts'] > 1)
                ? sprintf($lang->forumdisplay['tip_unapproved_many'], $thread['unapprovedposts'])
                : $lang->forumdisplay['tip_unapproved_one'];
            $thread['unapprovedposts'] = ts_nf((int)$thread['unapprovedposts']);
            $unapproved_posts = fd_badge('st-soft-danger', 'fa-hourglass-half', $thread['unapprovedposts'], strip_tags($unapproved_posts_count));
        }

        // ─ Вложения ──────────────────────────────────────────────────────
        $attachment_count = '';
        if (($enableattachments ?? 0) == 1 && ($thread['attachmentcount'] ?? 0) > 0) {
            $label = ($thread['attachmentcount'] > 1)
                ? sprintf($lang->forumdisplay['tip_attachment_many'], $thread['attachmentcount'])
                : $lang->forumdisplay['tip_attachment_one'];
            $attachment_count = '<span class="fd-attach" title="' . htmlspecialchars_uni(strip_tags($label)) . '"><i class="fa-solid fa-paperclip"></i>' . (int)$thread['attachmentcount'] . '</span>';
        }

        $plugins->run_hooks('forumdisplay_thread_end');

        $thread['start_datetime'] = my_datee('relative', $thread['dateline']);

        $row_class = 'fd-item inline_row' . ($thread['sticky'] == 1 ? ' fd-item--sticky' : '')
                   . ($thread['visible'] == 0 ? ' fd-item--unapproved' : '')
                   . ($is_new ? ' fd-item--new' : '');

        $threads .= '<div class="' . $row_class . '">'
            // иконка состояния
            . '<span class="st-ico ' . $st_soft . ' fd-item__ico" title="' . $folder_label_attr . '">'
            .   '<i class="fa-solid ' . $st_icon . '"></i>'
            .   ($has_dot ? '<span class="fd-dot" aria-hidden="true"></span>' : '')
            . '</span>'
            // тема
            . '<div class="fd-item__body">'
            .   '<div class="fd-item__top">'
            .     $badges
            .     '<a href="' . $thread['threadlink'] . '" class="fd-item__title"><span class="' . $inline_edit_class . ' ' . $new_class . '" id="tid_' . $inline_edit_tid . '">' . $thread['subject'] . '</span></a>'
            .     $unapproved_posts
            .   '</div>'
            .   '<div class="fd-item__meta">'
            .     '<span><i class="fa-solid fa-user"></i>' . $thread['profilelink'] . '</span>'
            .     '<span><i class="fa-solid fa-calendar"></i>' . $thread['start_datetime'] . '</span>'
            .     $attachment_count
            .     '<span class="fd-item__stats-inline"><i class="fa-solid fa-comments"></i>' . $replies_html
            .       ' <i class="fa-solid fa-eye ms-2"></i>' . $views_html . '</span>'
            .     $rating
            .   '</div>'
            .   $thread['multipage']
            . '</div>'
            // ответы / просмотры
            . '<div class="fd-item__stats">'
            .   '<a class="fd-stat" href="' . $BASEURL . '/misc.php?action=whoposted&amp;tid=' . (int)$thread['tid'] . '" onclick="whoPosted(' . (int)$thread['tid'] . '); return false;">'
            .     '<span class="fd-stat__val">' . $replies_html . '</span><span class="fd-stat__lbl">' . $lang->forumdisplay['lbl_replies'] . '</span></a>'
            .   '<div class="fd-stat"><span class="fd-stat__val">' . $views_html . '</span><span class="fd-stat__lbl">' . fd_l('lbl_views', 'Views') . '</span></div>'
            . '</div>'
            // последнее сообщение
            . '<div class="fd-item__last">'
            .   '<a href="' . $thread['lastpostlink'] . '" class="fd-item__last-ava" tabindex="-1" aria-hidden="true">' . $lp_avatar . '</a>'
            .   '<div class="fd-item__last-text">'
            .     '<div class="fd-item__last-who">' . $lastposterlink . $gotounread . '</div>'
            .     '<a href="' . $thread['lastpostlink'] . '" class="fd-item__last-date">' . $lastpostdate . '</a>'
            .   '</div>'
            . '</div>'
            . $modbit
            . '</div>';
    }

    // ─── Инлайн-модерация (меню) ─────────────────────────────────────────────
    $customthreadtools = $standardthreadtools = '';

    if ($ismod ?? false) {
        $gids = [(int)($CURUSER['usergroup'] ?? 1)];

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

        if ($customthreadtools) {
            //eval('$customthreadtools = "' . $templates->get('forumdisplay_inlinemoderation_custom') . '";');
        }

        $inlinemodopenclose = $inlinemodstickunstick = '';
        $inlinemoddelete    = $inlinemodmanage       = '';
        $inlinemodapproveunapprove = '';

        $inlinemodopenclose = '<option value="multiclosethreads">'.$lang->forumdisplay['opt_close_threads'].'</option>
        <option value="multiopenthreads">'.$lang->forumdisplay['opt_open_threads'].'</option>';

        $inlinemodstickunstick = '<option value="multistickthreads">'.$lang->forumdisplay['opt_stick_threads'].'</option>
        <option value="multiunstickthreads">'.$lang->forumdisplay['opt_unstick_threads'].'</option>';

        $inlinemodapproveunapprove = '<option value="multiapprovethreads">'.$lang->forumdisplay['opt_approve_threads'].'</option>
        <option value="multiunapprovethreads">'.$lang->forumdisplay['opt_unapprove_threads'].'</option>';

        $inlinemoddelete = '<option value="multideletethreads">' . $lang->forumdisplay['opt_delete_threads'] . '</option>';
        $inlinemodmanage = '<option value="multimovethreads">' . $lang->forumdisplay['opt_move_threads'] . '</option>';

        $standardthreadtools = '<optgroup label="' . $lang->forumdisplay['opt_group_standard'] . '">'
            . $inlinemodopenclose
            . $inlinemodstickunstick
            . $inlinemoddelete
            . $inlinemodmanage
            . $inlinemodapproveunapprove
            . '</optgroup>';

        // JS модалки удаления — inline, как раньше (scripts/forumdisplay.js не нужен)
$inline_modal_js = <<<'JS'
    document.addEventListener('DOMContentLoaded', function() {
        // t(key, englishFallback, ...args): AGS_LANG is emitted by PHP in <head>
        function t(key, fallback, ...args) {
            var s = (typeof AGS_LANG !== 'undefined' && AGS_LANG && AGS_LANG[key] != null) ? String(AGS_LANG[key]) : fallback;
            return s.replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
                var n = a || b;
                return args[n - 1] === undefined ? m : String(args[n - 1]);
            });
        }

        // Icon + text, text inserted as a text node (never via innerHTML)
        function setIconText(el, iconClass, text) {
            el.textContent = '';
            var icon = document.createElement('i');
            icon.className = iconClass;
            el.appendChild(icon);
            el.appendChild(document.createTextNode(text));
        }

        var selector     = document.getElementById("inlinemoderation_threads_selector");
        var form         = document.getElementById("inlinemoderation_threads");
        var confirmCheck = document.getElementById('confirmThreadDelete');
        var confirmBtn   = document.getElementById('confirmDeleteThreadsBtn');
        var deleteModal  = null;

        if (typeof bootstrap !== 'undefined') {
            var modalElement = document.getElementById('deleteThreadsModal');
            if (modalElement) {
                deleteModal = new bootstrap.Modal(modalElement);
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if (confirmCheck) confirmCheck.checked = false;
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.style.opacity = '0.6';
                        setIconText(confirmBtn, 'fas fa-trash-alt me-1', t('delete_threads', 'Delete Threads Permanently'));
                    }
                });
            }
        }

        if (confirmCheck) {
            confirmCheck.addEventListener('change', function() {
                if (confirmBtn) {
                    confirmBtn.disabled = !this.checked;
                    confirmBtn.style.opacity = this.checked ? '1' : '0.6';
                }
            });
        }

        if (selector && form) {

            form.addEventListener('submit', function(e) {
                if (selector.value === 'multideletethreads') {
                    e.preventDefault();

                    var selectedThreads = document.querySelectorAll('input[name^="inlinemod_"]:checked');
                    var selectedCount   = selectedThreads.length;

                    if (selectedCount === 0) {
                        alert(t('select_thread', 'Please select at least one thread.'));
                        return;
                    }

                    var countSpan = document.getElementById('modal_thread_count');
                    if (countSpan) countSpan.textContent = selectedCount;

                    var threadIds = Array.from(selectedThreads).map(function(el) {
                        return el.name.replace('inlinemod_', '');
                    });

                    // Превью
                    var previewContainer = document.getElementById('modal_threads_preview');
                    if (previewContainer) {
                        previewContainer.innerHTML = '';

                        threadIds.forEach(function(tid) {
                            var titleEl   = document.getElementById('tid_' + tid);
                            var titleText = titleEl ? titleEl.innerText.trim() : t('thread_id', 'Thread ID: {1}', tid);

                            var preview = document.createElement('div');
                            preview.className = 'card mb-2 border-danger border-opacity-25';

                            var body = document.createElement('div');
                            body.className = 'card-body py-2 px-3 d-flex justify-content-between align-items-center';

                            var title = document.createElement('span');
                            title.className = 'small fw-bold';
                            var fileIcon = document.createElement('i');
                            fileIcon.className = 'fas fa-file-alt text-danger me-2';
                            title.appendChild(fileIcon);
                            title.appendChild(document.createTextNode(titleText));

                            var badge = document.createElement('span');
                            badge.className = 'badge bg-secondary';
                            badge.textContent = t('tid_badge', 'TID: {1}', tid);

                            body.appendChild(title);
                            body.appendChild(badge);
                            preview.appendChild(body);
                            previewContainer.appendChild(preview);
                        });
                    }

                    // Скрытая форма
                    var oldForm = document.getElementById('deleteThreadsHiddenForm');
                    if (oldForm) { oldForm.remove(); }

                    var hiddenForm       = document.createElement('form');
                    hiddenForm.method    = 'post';
                    hiddenForm.action    = 'moderation.php';
                    hiddenForm.style.display = 'none';
                    hiddenForm.id        = 'deleteThreadsHiddenForm';

                    var fields = {
                        'my_post_key': document.querySelector('#inlinemoderation_threads input[name="my_post_key"]').value,
                        'fid':         String(inlineId),
                        'modtype':     'inlinethread',
                        'action':      'do_multideletethreads',
                        'threads':     threadIds.join(','),
                        'url':         window.location.href
                    };

                    for (var key in fields) {
                        var input   = document.createElement('input');
                        input.type  = 'hidden';
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
                            confirmBtn.disabled = true;
                            setIconText(confirmBtn, 'fas fa-spinner fa-spin me-2', t('deleting', 'Deleting...'));
                            hiddenForm.submit();
                            if (deleteModal) deleteModal.hide();
                        };
                    }

                    if (deleteModal) deleteModal.show();
                }
            });

            selector.addEventListener('change', function() {
                form.dispatchEvent(new Event('submit'));
            });
        }
    });


JS;

        $inlinemod = '<form action="moderation.php" method="post" id="inlinemoderation_threads" class="fd-inlinemod">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">'
            . '<input type="hidden" name="fid" value="' . $fid . '">'
            . '<input type="hidden" name="modtype" value="inlinethread">'
            . '<span class="fd-inlinemod__lbl"><i class="fa-solid fa-shield-halved"></i>' . fd_l('lbl_inline_mod', 'Moderation') . '</span>'
            . '<select name="action" id="inlinemoderation_threads_selector" class="form-select form-select-sm">'
            .   '<option value="delayedmoderation">' . $lang->forumdisplay['opt_delayed_moderation'] . '</option>'
            .   $standardthreadtools
            .   $customthreadtools
            . '</select>'
            . '<input type="submit" class="btn btn-sm btn-primary st-pill" name="go" value="' . htmlspecialchars_uni($lang->forumdisplay['btn_inline_go'] . ' (' . $inlinecount . ')') . '" id="inline_go">'
            . '<button type="button" onclick="inlineModeration.clearChecked();" class="btn btn-sm btn-outline-secondary st-pill" title="' . htmlspecialchars_uni(strip_tags($lang->forumdisplay['btn_clear'])) . '">'
            .   '<i class="fa-solid fa-xmark"></i><span class="fd-hide-xs">' . $lang->forumdisplay['btn_clear'] . '</span></button>'
            . '</form>'

            // ── Модалка удаления тем — прежний вид (id нужны JS ниже) ──
            . '<div class="modal fade" id="deleteThreadsModal" tabindex="-1" aria-labelledby="deleteThreadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">

            <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:42px;height:42px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteThreadsModalLabel">'.$lang->forumdisplay['modal_del_title'].'</h5>
                        <small class="opacity-75">'.$lang->forumdisplay['modal_del_subtitle'].'</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="'.$lang->forumdisplay['aria_close'].'"></button>
            </div>

            <div class="modal-body p-0">

                <!-- Счётчик -->
                <div class="text-center py-4" style="background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);">
                    <div style="font-size:3rem;font-weight:700;color:#ff416c;">
                        <i class="fas fa-hashtag"></i>
                        <span id="modal_thread_count">0</span>
                    </div>
                    <p class="text-muted mb-0">'.$lang->forumdisplay['modal_del_selected'].'</p>
                </div>

                <!-- Предупреждение -->
                <div class="px-4 pt-3 pb-2">
                    <div style="background:linear-gradient(135deg,#fff5f5,#ffeaea);border-left:4px solid #ff6b6b;border-radius:10px;padding:20px;">
                        <h6 class="fw-bold mb-3"><i class="fas fa-radiation me-2 text-danger"></i>'.$lang->forumdisplay['modal_del_warn_title'].'</h6>
                        <p class="small mb-3">'.$lang->forumdisplay['modal_del_warn_html'].'</p>
                        <ul class="list-unstyled mb-3">
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-times-circle text-danger me-2"></i>'.$lang->forumdisplay['modal_del_item_posts'].'
                            </li>
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-paperclip text-danger me-2"></i>'.$lang->forumdisplay['modal_del_item_attach'].'
                            </li>
                            <li class="py-1 border-bottom border-danger border-opacity-25 small">
                                <i class="fas fa-chart-bar text-danger me-2"></i>'.$lang->forumdisplay['modal_del_item_polls'].'
                            </li>
                             <li class="py-1 border-bottom border-danger border-opacity-25 small">
                               <i class="fas fa-history me-2"></i>'.$lang->forumdisplay['modal_del_item_history'].'
                             </li>
                            <li class="py-1 small">
                                <i class="fas fa-undo text-danger me-2"></i>'.$lang->forumdisplay['modal_del_item_norecover'].'
                            </li>
                        </ul>
                        <div class="alert alert-danger py-2 mb-0 small">
                            <i class="fas fa-skull-crossbones me-2"></i>
                            <strong>'.$lang->forumdisplay['modal_del_dataloss_title'].'</strong> '.$lang->forumdisplay['modal_del_dataloss_text'].'
                        </div>
                    </div>
                </div>

                <!-- Превью тредов -->
                <div class="px-4 py-3">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-eye me-1"></i>'.$lang->forumdisplay['modal_del_preview_title'].'
                    </h6>
                    <div id="modal_threads_preview" style="max-height: 200px; overflow-y: auto;"></div>
                </div>

                <!-- Чекбокс подтверждения -->
                <div class="px-4 pb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmThreadDelete" style="accent-color:#ff416c;">
                        <label class="form-check-label small" for="confirmThreadDelete">
                            <strong>'.$lang->forumdisplay['modal_del_confirm_strong'].'</strong>
                            '.$lang->forumdisplay['modal_del_confirm_text'].'
                        </label>
                    </div>
                </div>

            </div>

            <div class="modal-footer d-flex justify-content-between border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left me-1"></i>'.$lang->forumdisplay['btn_cancel_return'].'
                </button>
                <button type="button" class="btn btn-sm px-4 text-white" id="confirmDeleteThreadsBtn"
                    style="background:linear-gradient(135deg,#ff416c,#ff4b2b);border:none;opacity:0.6;" disabled>
                    <i class="fas fa-trash-alt me-1"></i>'.$lang->forumdisplay['btn_delete_threads'].'
                </button>
            </div>

        </div>
    </div>
</div>'

            // глобальные переменные, которые читает inline_moderation.js
            . '<script>'
            . 'var go_text = ' . json_encode($lang->forumdisplay['btn_inline_go'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
            . 'var all_text = "' . (int)$threadcount . '";'
            . 'var inlineType = "forum";'
            . 'var inlineId = ' . (int)$fid . ';'
            . '</script>'
            . '<script src="' . $BASEURL . '/scripts/inline_moderation.js?ver=1832"></script>'
            . '<script>' . $inline_modal_js . '</script>';
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
$add_remove_subscription_text = $lang->forumdisplay['btn_subscribe'];
$addremovesubscription        = '';

if ($CURUSER['id']) {
    $query = $db->sql_query_prepared(
        "SELECT fid FROM forumsubscriptions WHERE fid = ? AND uid = ? LIMIT 1",
        [(int)$fid, (int)$CURUSER['id']]
    );

    if ($db->num_rows($query) > 0) {
        $add_remove_subscription      = 'remove';
        $add_remove_subscription_text = $lang->forumdisplay['btn_unsubscribe'];
    }

    $addremovesubscription = '<a href="usercp.php?action=' . $add_remove_subscription . 'subscription&amp;type=forum&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-outline-secondary st-pill fd-btn">'
        . '<i class="fa-' . ($add_remove_subscription === 'add' ? 'regular' : 'solid') . ' fa-bell' . ($add_remove_subscription === 'add' ? '' : '-slash') . '"></i>'
        . '<span>' . $add_remove_subscription_text . '</span></a>';
}

// ─── Финальный рендер ─────────────────────────────────────────────────────────
$inline_edit_js = $clearstoredpass = '';
$threadslist    = '';
$head_actions   = '';
$rssdiscovery   = '';

if ($foruminfo['type'] !== 'c') {
    if ($fpermissions['canviewthreads'] != 1) {
        $threads = fd_empty('fa-lock', 'st-soft-danger', $lang->forumdisplay['err_no_permission'], $lang->forumdisplay['hint_no_permission']);
    }

    if (!$threadcount && $fpermissions['canviewthreads'] == 1) {
        $threads = fd_empty('fa-file-lines', 'st-soft-muted', $lang->forumdisplay['msg_no_threads_title'], $lang->forumdisplay['msg_no_threads_hint']);
    }

    if ($foruminfo['password'] !== '') {
        $clearstoredpass = '<a href="misc.php?action=clearpass&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '" class="fd-clearpass"><i class="fa-solid fa-key"></i>' . $lang->forumdisplay['lbl_clear_stored_password'] . '</a>';
    }

    // ── Сортировка: пилюли в шапке списка (сброс на 1-ю страницу) ─────────
    $sortbase = get_forum_link($fid) . $string . "datecut=$datecut&amp;prefix=$tprefix";
    $sort_opts = [
        'lastpost' => $lang->forumdisplay['opt_sort_lastpost'],
        'subject'  => $lang->forumdisplay['opt_sort_subject'],
        'starter'  => $lang->forumdisplay['opt_sort_starter'],
        'started'  => $lang->forumdisplay['opt_sort_started'],
        'replies'  => $lang->forumdisplay['opt_sort_replies'],
        'views'    => $lang->forumdisplay['opt_sort_views'],
    ];
    $sortpills = '';
    foreach ($sort_opts as $key => $label) {
        $active = $mybb->input['sortby'] === $key;
        $next   = $active ? $oppsort : (in_array($key, ['subject', 'starter'], true) ? 'asc' : 'desc');
        $arrow  = $active ? '<i class="fa-solid ' . ($sortordernow === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') . '"></i>' : '';
        $sortpills .= '<a href="' . $sortbase . '&amp;sortby=' . $key . '&amp;order=' . $next . '" class="btn btn-sm btn-outline-secondary st-pill fd-sort' . ($active ? ' active' : '') . '"'
                    . ($active ? ' aria-current="true"' : '') . '>' . $label . $arrow . '</a>';
    }

    // ── Фильтр (GET-форма, имена полей прежние) ──────────────────────────
    $forumsort = '<form action="forumdisplay.php" method="get" class="st-toolbar st-toolbar--bottom fd-filter">'
        . '<input type="hidden" name="fid" value="' . $fid . '">'
        . fd_select('sortby', [
              'subject'  => $lang->forumdisplay['opt_sort_subject'],
              'lastpost' => $lang->forumdisplay['opt_sort_lastpost'],
              'starter'  => $lang->forumdisplay['opt_sort_starter'],
              'started'  => $lang->forumdisplay['opt_sort_started'],
              'replies'  => $lang->forumdisplay['opt_sort_replies'],
              'views'    => $lang->forumdisplay['opt_sort_views'],
          ], (string)$mybb->input['sortby'], 'fa-arrow-down-wide-short')
        . fd_select('order', [
              'asc'  => $lang->forumdisplay['opt_order_asc'],
              'desc' => $lang->forumdisplay['opt_order_desc'],
          ], $sortordernow, 'fa-arrow-up-short-wide')
        . fd_select('datecut', [
              1    => $lang->forumdisplay['opt_date_1day'],
              5    => $lang->forumdisplay['opt_date_5days'],
              10   => $lang->forumdisplay['opt_date_10days'],
              20   => $lang->forumdisplay['opt_date_20days'],
              50   => $lang->forumdisplay['opt_date_50days'],
              75   => $lang->forumdisplay['opt_date_75days'],
              100  => $lang->forumdisplay['opt_date_100days'],
              365  => $lang->forumdisplay['opt_date_lastyear'],
              9999 => $lang->forumdisplay['opt_date_beginning'],
          ], (string)(int)$datecut, 'fa-calendar')
        . '<button type="submit" class="btn btn-sm btn-primary st-pill"><i class="fa-solid fa-filter"></i> ' . $lang->forumdisplay['btn_go'] . '</button>'
        . '</form>';

    $plugins->run_hooks('forumdisplay_threadlist');

    $rss_title = sprintf($lang->forumdisplay['tip_rss_latest'], htmlspecialchars(htmlspecialchars_decode($foruminfo['name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $rssdiscovery = '<link rel="alternate" type="application/rss+xml" title="' . $rss_title . ' (RSS 2.0)" href="' . $BASEURL . '/syndication.php?fid=' . $fid . '">'
                  . '<link rel="alternate" type="application/atom+xml" title="' . $rss_title . ' (Atom 1.0)" href="' . $BASEURL . '/syndication.php?type=atom1.0&amp;fid=' . $fid . '">';

    // Действия в шапке. Раньше в ссылке «прочитано» был неопределённый
    // $post_code_string (warning + ссылка без my_post_key).
    $head_actions = '<div class="fd-head__actions">'
        . '<a href="misc.php?action=markread&amp;fid=' . $fid . '&amp;my_post_key=' . $mybb->post_code . '" class="btn btn-outline-secondary st-pill fd-btn" title="' . htmlspecialchars_uni(strip_tags($lang->forumdisplay['tip_mark_read'])) . '">'
        .   '<i class="fa-solid fa-check-double"></i><span class="fd-hide-sm">' . strip_tags($lang->forumdisplay['tip_mark_read']) . '</span></a>'
        . $addremovesubscription
        . $newthread
        . '</div>';

    $pager = $multipage !== '' ? '<div class="st-pager fd-pager">' . $multipage . '</div>' : '';

    $threadslist = $pager
        . '<section class="st-card fd-list">'
        .   '<div class="st-toolbar fd-sortbar">'
        .     '<span class="fd-sortbar__lbl"><i class="fa-solid fa-arrow-down-wide-short"></i>' . fd_l('lbl_sort_by', 'Sort by') . '</span>'
        .     '<div class="fd-sortbar__pills">' . $sortpills . '</div>'
        .     $inlinemodcol
        .   '</div>'
        .   $selectall
        .   $announcementlist
        .   $threads
        .   $forumsort
        . '</section>'
        . $pager
        . (($searchforum !== '' || $inlinemod !== '')
            ? '<div class="st-card fd-bottom">' . $searchforum . $inlinemod . '</div>'
            : '')
        . $inline_edit_js;
} else {
    if (empty($forums)) {
        error($lang->forumdisplay['err_contains_no_forums']);
    }
}

$plugins->run_hooks('forumdisplay_end');

$foruminfo['name'] = strip_tags($foruminfo['name']);

// ─── JS strings (js_ keys -> AGS_LANG without the prefix) ────────────────────
$ags_lang_js = [];
foreach ($lang->forumdisplay as $lang_key => $lang_val) {
    if (str_starts_with((string)$lang_key, 'js_')) {
        $ags_lang_js[substr((string)$lang_key, 3)] = $lang_val;
    }
}
$json_flags    = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$ags_lang_json = json_encode($ags_lang_js, $json_flags);

// ─── Шапка форума ────────────────────────────────────────────────────────────
$head_meta = [];
if ($foruminfo['type'] !== 'c') {
    $head_meta[] = '<span><i class="fa-solid fa-comments"></i>' . fd_fmt('lbl_threads_count', '{1} threads', ts_nf((int)$threadcount)) . '</span>';
}
if ($moderatedby !== '') $head_meta[] = $moderatedby;
if ($rssdiscovery !== '') {
    $head_meta[] = '<a href="' . $BASEURL . '/syndication.php?fid=' . $fid . '"><i class="fa-solid fa-rss"></i>RSS</a>';
}
if ($clearstoredpass !== '') $head_meta[] = $clearstoredpass;

$forum_desc = trim((string)($foruminfo['description'] ?? ''));

$page_head = '<header class="st-card st-head fd-head">'
    . '<div class="st-head__icon st-soft-primary"><i class="fa-solid ' . ($foruminfo['type'] === 'c' ? 'fa-folder-tree' : 'fa-comments') . '"></i></div>'
    . '<div class="fd-head__text">'
    .   '<h1 class="st-title">' . htmlspecialchars_uni(htmlspecialchars_decode($foruminfo['name'])) . '</h1>'
    .   ($forum_desc !== '' ? '<div class="fd-head__desc">' . $forum_desc . '</div>' : '')
    .   ($head_meta ? '<div class="st-head__meta">' . implode('', $head_meta) . '</div>' : '')
    . '</div>'
    . $head_actions
    . '</header>';

// Раньше: stdhead('title') - заголовок вкладки был буквально «title»,
// а страница собиралась во вложенный <html><head><body> внутри body.
stdhead(htmlspecialchars_decode($foruminfo['name']));
build_breadcrumb();

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=5">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/forumdisplay.css?ver=1">';
echo $rssdiscovery;
echo '<script>'
   . 'if (typeof lang === "object" && lang) {'
   .   'lang.no_new_posts = ' . json_encode($lang->global['no_new_posts'], $json_flags) . ';'
   .   'lang.click_mark_read = ' . json_encode($lang->global['click_mark_read'], $json_flags) . ';'
   .   'lang.inline_edit_description = ' . json_encode($lang->forumdisplay['js_click_hold_edit'], $json_flags) . ';'
   .   'lang.post_fetch_error = ' . json_encode($lang->global['post_fetch_error'], $json_flags) . ';'
   . '}'
   . 'const AGS_LANG = ' . $ags_lang_json . ';'
   . '</script>';
echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/inline_edit.js?ver=1822"></script>';
echo '<script src="' . $BASEURL . '/scripts/whoposted_js.js?ver=1" defer></script>';

echo '<div class="container-md st-page fd-page">'
   . $rules
   . $page_head
   . $subforums
   . $threadslist
   . $usersbrowsing
   . '</div>';

stdfoot();