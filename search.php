<?php
declare(strict_types=1);

define('IN_MYBB',                  1);
define('IGNORE_CLEAN_VARS',        'sid');
define('THIS_SCRIPT',              'search.php');
define('SCRIPTNAME',               'search.php');

define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_search.php';

require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;

$parser_options = array(
	"allow_html" => 0,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);

$lang->load('search');

function highlight_search_term($text, $keyword)
{
    if(empty($keyword))
    {
        return htmlspecialchars_uni($text);
    }

    $text = htmlspecialchars_uni($text);

    return preg_replace(
        '#(' . preg_quote($keyword, '#') . ')#iu',
        '<mark>$1</mark>',
        $text
    );
}

// Converts a "YYYY-MM-DD" string into a unix timestamp.
// $end=true anchors to 23:59:59 of that day (range "to"), otherwise 00:00:00 (range "from").
function to_search_ts(?string $d, bool $end = false): int
{
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return 0;
    }
    return (int)strtotime($d . ($end ? ' 23:59:59' : ' 00:00:00'));
}

// ─────────────────────────────────────────────────────────────────────────────
// CSS / HTML HELPERS
// ─────────────────────────────────────────────────────────────────────────────
// Общий st-ui.css + свой search.css (все правила под .sr-page).
function search_css(): string
{
    global $BASEURL;
    return '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=43">'
         . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/search.css?ver=43">';
}

// Escaped lang string for HTML text / attributes (plain-text keys only).
function sr_e(string $key): string
{
    global $lang;
    return htmlspecialchars_uni((string)($lang->search[$key] ?? $key));
}

// Escaped lang string with {1}/{2}… placeholders (args are inserted as-is, i.e. pre-escaped/HTML).
function sr_fmt(string $key, string|int|float ...$args): string
{
    global $lang;
    return ags_fmt(htmlspecialchars_uni((string)($lang->search[$key] ?? $key)), ...$args);
}

// Аватар 44px. $username - СЫРОЕ имя (экранируется здесь).
function sr_avatar(string $avatar, string $dims, string $username): string
{
    $ua = format_avatar($avatar, $dims, '50|50');
    if (str_starts_with((string)$ua['image'], '<')) return $ua['image'];
    if (!empty($ua['image']) && $ua['image'] !== 'default') {
        return '<img src="' . htmlspecialchars_uni((string)$ua['image']) . '" class="sr-ava" alt="" loading="lazy">';
    }
    $letter = htmlspecialchars_uni(mb_strtoupper(mb_substr($username, 0, 1)) ?: '?');
    return '<span class="sr-ava sr-ava--letter">' . $letter . '</span>';
}

// Шапка страницы: иконка в мягком квадрате + заголовок + подзаголовок.
function sr_head(string $icon, string $soft, string $title, string $meta): string
{
    return '<div class="st-card st-head sr-head">'
         .   '<div class="st-head__icon st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></div>'
         .   '<div class="sr-head__text">'
         .     '<h1 class="st-title">' . $title . '</h1>'
         .     ($meta !== '' ? '<div class="st-head__meta">' . $meta . '</div>' : '')
         .   '</div>'
         . '</div>';
}

// Плашка-пилюля (.st-badge + мягкий цвет).
function sr_badge(string $soft, string $icon, string $text = '', string $extra = ''): string
{
    return '<span class="st-badge ' . $soft . ($extra !== '' ? ' ' . $extra : '') . '">'
         . ($icon !== '' ? '<i class="fa-solid ' . $icon . '"></i>' : '')
         . ($text !== '' ? '<span>' . $text . '</span>' : '')
         . '</span>';
}

// Панель сортировки (пилюли). $sorturl уже экранирован (&amp;).
function sr_sort_bar(array $opts, string $sortby, string $order, string $sorturl): string
{
    global $lang;
    $html = '<div class="st-toolbar sr-sortbar"><span class="sr-sortbar__lbl"><i class="fa-solid fa-arrow-down-wide-short"></i>'
          . sr_e('lbl_sort_by') . '</span><div class="sr-sortbar__pills">';
    foreach ($opts as $key => $label) {
        $is_active = $sortby === $key;
        $next      = ($is_active && $order === 'asc') ? 'desc' : 'asc';
        $arrow     = $is_active ? '<i class="fa-solid ' . ($order === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') . '"></i>' : '';
        $html .= '<a href="' . $sorturl . '&amp;sortby=' . $key . '&amp;order=' . $next . '" class="btn btn-sm btn-outline-secondary st-pill sr-sort-btn' . ($is_active ? ' active' : '') . '"'
               . ($is_active ? ' aria-current="true"' : '') . '>' . htmlspecialchars_uni($label) . $arrow . '</a>';
    }
    return $html . '</div></div>';
}

// Поле расширенной формы: подпись с иконкой + контрол.
function sr_field(string $cols, string $icon, string $label, string $control, string $for = ''): string
{
    return '<div class="' . $cols . ' sr-field">'
         . '<label class="sr-field__lbl"' . ($for !== '' ? ' for="' . $for . '"' : '') . '><i class="fa-solid ' . $icon . '"></i>' . $label . '</label>'
         . $control
         . '</div>';
}

// Подсвечивает все слова запроса (>=3 символа) в уже экранированном тексте.
function sr_highlight_words(string $escaped, string $keywords): string
{
    $words = preg_split('/[\s"]+/u', $keywords, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $words = array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array(mb_strtolower($w), ['and', 'or', 'not'], true));
    if (!$words) return $escaped;
    $words = array_map(fn($w) => preg_quote(htmlspecialchars_uni(trim($w, '+-*~<>()')), '#'), $words);
    $words = array_filter($words, fn($w) => $w !== '');
    if (!$words) return $escaped;
    return preg_replace('#(' . implode('|', $words) . ')#iu', '<mark>$1</mark>', $escaped) ?? $escaped;
}

// Превью поста: короткий текстовый сниппет + миниатюры картинок.
// Цитаты/спойлеры/nfo/code не берём ни в текст, ни в картинки.
// Текст строится БЕЗ тегов картинок/вложений/видео - иначе в strip_tags()
// попадал их запасной текст («Image unavailable … Open original»).
function sr_post_preview(string $message, string $keywords, array $attach_imgs = [], int $max_imgs = 4): array
{
    global $parser, $parser_options;

    $message = preg_replace('#\[(quote|spoiler|nfo|code|php)(=[^\]]*)?\].*?\[/\1\]#is', ' ', $message) ?? $message;

    // 1) Картинки - из полного HTML парсера (так ловятся и [img], и вложения)
    $html   = $parser->parse_message($message, $parser_options);
    $images = [];
    if (preg_match_all('#<img\b[^>]*>#i', $html, $tags)) {
        foreach ($tags[0] as $tag) {
            if (!preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag, $m)) continue;
            $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#class\s*=\s*["\'][^"\']*smil#i', $tag) || stripos($src, '/smilies/') !== false) continue;
            if (!preg_match('#^(https?:)?//|^/|^[\w./-]+$#i', $src)) continue;   // без data:, javascript: и т.п.
            $images[$src] = true;
        }
    }
    $images = array_keys($images);
    // + картинки-вложения поста (таблица attachments, см. sr_load_attach_images())
    $images = array_values(array_unique([...$images, ...$attach_imgs]));

    // 2) Текст - из сообщения без медиа-тегов
    $text_src = preg_replace('#\[(img|attachment|attach|video|youtube|media)(=[^\]]*)?\].*?\[/\1\]#is', ' ', $message) ?? $message;
    $text_src = preg_replace('#\[(attachment|attach)=\d+\]#i', ' ', $text_src) ?? $text_src;
    $text     = $parser->parse_message($text_src, $parser_options);
    $text     = html_entity_decode(strip_tags(str_replace(['<br />', '<br>', '</p>', '</div>'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text     = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (mb_strlen($text) > 280) $text = rtrim(mb_substr($text, 0, 280)) . '…';

    return [
        'text'  => $text === '' ? '' : sr_highlight_words(htmlspecialchars_uni($text), $keywords),
        'imgs'  => array_slice($images, 0, $max_imgs),
        'more'  => max(0, count($images) - $max_imgs),
    ];
}

// Картинки-вложения для постов страницы - одним запросом, как в showthread.php.
// Вложения - не BBCode: парсер их не рисует, они в таблице attachments.
// Только видимые картинки и только в форумах, где можно качать вложения
// (иначе attachment.php отдаст отказ и будет битая миниатюра).
// Возвращает [pid => [src, ...]].
function sr_load_attach_images(array $post_rows, array $fperms): array
{
    global $db;

    if (($GLOBALS['enableattachments'] ?? 1) == 0) return [];

    $pid_fid = [];
    foreach ($post_rows as $r) {
        $fid = (int)$r['fid'];
        if (($fperms[$fid]['candlattachments'] ?? 1) == 0) continue;
        $pid_fid[(int)$r['pid']] = $fid;
    }
    if (!$pid_fid) return [];

    $pids  = array_keys($pid_fid);
    $ph    = implode(',', array_fill(0, count($pids), '?'));
    $q     = $db->sql_query_prepared(
        "SELECT aid, pid, filename, thumbnail FROM attachments WHERE pid IN ({$ph}) AND visible = 1 ORDER BY aid",
        $pids
    );
    $thumbs_on = ($GLOBALS['attachthumbnails'] ?? 'yes') === 'yes';
    $out = [];
    while ($q && ($a = $db->fetch_array($q))) {
        $ext = strtolower(pathinfo((string)$a['filename'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpeg', 'jpg', 'gif', 'bmp', 'png', 'webp'], true)) continue;
        $has_thumb = $thumbs_on && $a['thumbnail'] !== '' && $a['thumbnail'] !== 'SMALL';
        $out[(int)$a['pid']][] = $has_thumb
            ? 'attachment.php?thumbnail=' . (int)$a['aid']
            : 'attachment.php?aid=' . (int)$a['aid'];
    }
    return $out;
}

// Полоска миниатюр поста; каждая ведёт на сам пост.
function sr_thumbs(array $preview, string $post_url): string
{
    if (!$preview['imgs']) return '';
    $html = '<div class="sr-thumbs">';
    foreach ($preview['imgs'] as $i => $src) {
        $last = $i === count($preview['imgs']) - 1 && $preview['more'] > 0;
        $html .= '<a href="' . $post_url . '" class="sr-thumb sr-above">'
               . '<img src="' . htmlspecialchars_uni($src) . '" alt="" loading="lazy" decoding="async">'
               . ($last ? '<span class="sr-thumb__more">+' . (int)$preview['more'] . '</span>' : '')
               . '</a>';
    }
    return $html . '</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────────────────────
$is_mod = is_mod($mybb->usergroup);
add_breadcrumb($lang->search['nav_search'], 'search.php');

$mybb->input['action'] = $mybb->get_input('action');
if ($mybb->input['action'] === 'results') add_breadcrumb($lang->search['nav_results']);
if (empty($usergroups) || $usergroups['cansearch'] == 0) print_no_permission();

$now             = TIMENOW;
$mybb->input['keywords'] = trim($mybb->get_input('keywords'));
$searchhardlimit = 0;
$limitsql        = $searchhardlimit > 0 ? "LIMIT {$searchhardlimit}" : '';

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: suggest — lightweight AJAX autocomplete (live dropdown while typing)
// ─────────────────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'suggest')
{
    header('Content-Type: application/json; charset=utf-8');

    // Rate limit - без него это публичный, не требующий авторизации AJAX-
    // эндпоинт с LIKE '%...%' (не использует индекс) - легко закидать
    // запросами и нагрузить БД. Простое окно на сессию: не больше 10
    // запросов за 10 секунд.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $rl_now    = time();
    $rl_window = 10;   // секунд
    $rl_max    = 10;   // запросов за окно

    $_SESSION['search_suggest_hits'] = array_filter(
        $_SESSION['search_suggest_hits'] ?? [],
        fn($t) => $t > $rl_now - $rl_window
    );

    if (count($_SESSION['search_suggest_hits']) >= $rl_max) {
        http_response_code(429);
        echo json_encode(['results' => [], 'error' => 'rate_limited']);
        exit;
    }

    $_SESSION['search_suggest_hits'][] = $rl_now;

    $kw = trim($mybb->get_input('q'));

    if(my_strlen($kw) < 2)
    {
        echo json_encode(['results' => []]);
        exit;
    }

    $results = [];

    /*
    =====================================================
    THREADS
    =====================================================
    */

    $like = '%' . escape_like_pattern($kw) . '%';

    $threads = $db->sql_query_prepared("
        SELECT
            t.tid,
            t.subject,
            t.replies,
            f.name AS forumname
        FROM threads t
        LEFT JOIN forums f ON(f.fid=t.fid)
        WHERE t.subject LIKE ?
        ORDER BY t.lastpost DESC
        LIMIT 5
    ", [$like]);

    while($threads && ($row = $db->fetch_array($threads)))
    {
        $results[] = [
            'type'    => 'thread',
            'icon'    => 'fa-comments',
            'title' => highlight_search_term($row['subject'], $kw),
            'meta'    => ags_fmt($lang->search['suggest_meta_thread'], htmlspecialchars_uni((string)$row['forumname']), (int)$row['replies']),
            'url'     => get_thread_link($row['tid'])
        ];
    }

    /*
    =====================================================
    POSTS
    =====================================================
    */

    $posts = $db->sql_query_prepared("
        SELECT
            p.pid,
            p.subject,
            LEFT(p.message,120) AS snippet,
            t.tid
        FROM posts p
        LEFT JOIN threads t ON(t.tid=p.tid)
        WHERE p.message LIKE ?
        ORDER BY p.dateline DESC
        LIMIT 5
    ", [$like]);

    while($posts && ($row = $db->fetch_array($posts)))
    {
        $results[] = [
            'type'    => 'post',
            'icon'    => 'fa-comment',
            'title' => highlight_search_term($row['subject'], $kw),
            'meta'  => highlight_search_term($row['snippet'], $kw),
            'url'     => get_post_link($row['pid'], $row['tid'])
        ];
    }

    /*
    =====================================================
    USERS
    =====================================================
    */

    $users = $db->sql_query_prepared("
        SELECT id, username
        FROM users
        WHERE username LIKE ?
        ORDER BY username ASC
        LIMIT 5
    ", [$like]);

    while($users && ($row = $db->fetch_array($users)))
    {
        $results[] = [
            'type'    => 'user',
            'icon'    => 'fa-user',
            'title' => highlight_search_term($row['username'], $kw),
            'meta'    => htmlspecialchars_uni($lang->search['suggest_meta_user']),
            'url'     => 'userdetails.php?id='.(int)$row['id']
        ];
    }

    /*
    =====================================================
    FORUMS
    =====================================================
    */

    $forums = $db->sql_query_prepared("
        SELECT fid,name,description
        FROM forums
        WHERE name LIKE ?
        ORDER BY disporder ASC
        LIMIT 5
    ", [$like]);

    while($forums && ($row = $db->fetch_array($forums)))
    {
        $results[] = [
            'type'    => 'forum',
            'icon'    => 'fa-folder',
           'title' => highlight_search_term($row['name'], $kw),
'meta'  => highlight_search_term(
    my_substr($row['description'], 0, 80),
    $kw
),
            'url'     => get_forum_link($row['fid'])
        ];
    }

    echo json_encode([
        'results' => array_slice($results,0,15)
    ]);

    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: results
// ─────────────────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'results') {

    $sid    = $mybb->get_input('sid');
    $query  = $db->sql_query_prepared("SELECT * FROM searchlog WHERE sid = ?", [$sid]);
    $search = $query ? $db->fetch_array($query) : null;

    if (!$search) stderr($lang->search['error_invalidsearch']);

    // Владелец выдачи - залогиненным сверяем uid, гостям (uid=0 в
    // searchlog) сверяем IP. Раньше по чужому sid можно было посмотреть
    // чужую выдачу целиком (sid практически неугадываем, но проверка
    // владельца - правильная защита в глубину, не полагаться на одну
    // лишь длину идентификатора).
    $search_owner_uid = (int)($search['uid'] ?? 0);
    if ($search_owner_uid > 0) {
        if ((int)($CURUSER['id'] ?? 0) !== $search_owner_uid) {
            stderr($lang->search['error_invalidsearch']);
        }
    } elseif (($search['ipaddress'] ?? '') !== get_ip()) {
        stderr($lang->search['error_invalidsearch']);
    }

    $plugins->run_hooks('search_results_start');

    $order  = my_strtolower(htmlspecialchars_uni($mybb->get_input('order')));
    $sortby = my_strtolower(htmlspecialchars_uni($mybb->get_input('sortby')));

    $is_threads = isset($search['resulttype']) && $search['resulttype'] === 'threads';

    $sortfield = match ($sortby) {
        'replies' => 't.replies',
        'views'   => 't.views',
        'subject' => $is_threads ? 't.subject' : 'p.subject',
        'forum'   => 'f.name',
        'starter' => $is_threads ? 't.username' : 'p.username',
        'rating'  => $is_threads ? 'avg_rating' : 't.lastpost',
        default   => (function() use (&$sortby, $is_threads): string {
            if ($is_threads) { $sortby = 'lastpost'; return 't.lastpost'; }
            $sortby = 'dateline'; return 'p.dateline';
        })(),
    };

    if ($order !== 'asc') { $order = 'desc'; $oppsort = 'asc'; }
    else                  { $oppsort = 'desc'; }

    $perpage = 20;
    $page    = max(1, (int)$mybb->get_input('page'));
    $start   = ($page - 1) * $perpage;
    $upper   = $start + $perpage;

    $highlight = '';
    if (!empty($search['keywords'])) {
        $highlight = $mybb->seo_support
            ? '?highlight=' . urlencode($search['keywords'])
            : '&amp;highlight=' . urlencode($search['keywords']);
    }

    $sorturl    = 'search.php?action=results&amp;sid=' . htmlspecialchars_uni($sid);
    $forumcache = $cache->read('forums');
    $forumsread = [];

    if ($CURUSER['id'] == 0) {
        $q = $db->sql_query_prepared("SELECT fid FROM forums WHERE active != 0 ORDER BY pid, disporder");
        $forumsread = isset($mybb->cookies['mybb']['forumread'])
            ? my_unserialize($mybb->cookies['mybb']['forumread'], false) : [];
    } else {
        $q = $db->sql_query_prepared(
            "SELECT f.fid, fr.dateline AS lastread FROM forums f LEFT JOIN forumsread fr ON (fr.fid=f.fid AND fr.uid=?) WHERE f.active!=0 ORDER BY pid, disporder",
            [(int)$CURUSER['id']]
        );
    }
    $readforums = [];
    while ($q && ($f = $db->fetch_array($q))) {
        if ($CURUSER['id'] == 0 && !empty($forumsread[$f['fid']])) $f['lastread'] = $forumsread[$f['fid']];
        $readforums[$f['fid']] = $f['lastread'] ?? '';
    }
    $fpermissions = forum_permissions();

    // ── Threads ────────────────────────────────────────────────────────────
    if ($is_threads) {

        $unapproved_where_t = get_visible_where('t');
        $threadcount        = 0;
        $threads            = [];

        if ($search['querycache'] !== '') {
            $q = $db->sql_query_prepared("SELECT t.tid FROM threads t WHERE {$search['querycache']} AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' ORDER BY t.lastpost DESC {$limitsql}");
            while ($q && ($t = $db->fetch_array($q))) { $threads[$t['tid']] = $t['tid']; $threadcount++; }
            if (!$threadcount) stderr($lang->search['error_nosearchresults']);
            $search['threads'] = implode(',', $threads);
            $where_conditions  = 't.tid IN (' . $search['threads'] . ')';
        } else {
            $where_conditions = 't.tid IN (' . $search['threads'] . ')';
            $q     = $db->sql_query_prepared("SELECT COUNT(t.tid) AS resultcount FROM threads t WHERE {$where_conditions} AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$limitsql}");
            $cnt   = $q ? $db->fetch_array($q) : null;
            if (!$cnt['resultcount']) stderr($lang->search['error_nosearchresults']);
            $threadcount = $cnt['resultcount'];
        }

        $permsql = ''; $onlyusfids = []; $permsql_params = [];
        $gp = forum_permissions();
        foreach ($gp as $fid => $fp) {
            if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
        }
        if (!empty($onlyusfids)) {
            $permsql .= "AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid=?) OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))";
            $permsql_params[] = (int)$CURUSER['id'];
        }
        $uf = get_unsearchable_forums(); if ($uf) $permsql .= " AND t.fid NOT IN ($uf)";
        $ia = get_inactive_forums();     if ($ia) $permsql .= " AND t.fid NOT IN ($ia)";

        $pages = max(1, (int)ceil($threadcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query_prepared("
            SELECT t.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup,
                   COALESCE(tr.avg_rating, 0) AS avg_rating
            FROM threads t
            LEFT JOIN users u ON (u.id = t.uid)
            LEFT JOIN forums f ON (t.fid = f.fid)
            LEFT JOIN (
                SELECT tid, AVG(rating) AS avg_rating
                FROM threadratings
                GROUP BY tid
            ) tr ON (tr.tid = t.tid)
            WHERE $where_conditions AND ({$unapproved_where_t}) {$permsql} AND t.closed NOT LIKE 'moved|%'
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ", $permsql_params);

        $thread_cache = [];
        while ($q && ($t = $db->fetch_array($q))) {
            $thread_cache[$t['tid']] = $t;
        }
        $thread_ids = implode(',', array_keys($thread_cache));
        if (empty($thread_ids)) stderr($lang->search['error_nosearchresults']);

        // dot icons
        if ($CURUSER['id'] && $thread_cache) {
            $uwp = str_replace('t.', '', $unapproved_where_t);
            $q2  = $db->sql_query_prepared("SELECT DISTINCT tid,uid FROM posts WHERE uid=? AND tid IN({$thread_ids}) AND ({$uwp})", [(int)$CURUSER['id']]);
            while ($q2 && ($t = $db->fetch_array($q2))) $thread_cache[$t['tid']]['dot_icon'] = 1;
        }
        // read threads
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q2 = $db->sql_query_prepared("SELECT tid,dateline FROM threadsread WHERE uid=? AND tid IN({$thread_ids})", [(int)$CURUSER['id']]);
            while ($q2 && ($rt = $db->fetch_array($q2))) $thread_cache[$rt['tid']]['lastread'] = $rt['dateline'];
        }

        // ── OUTPUT ────────────────────────────────────────────────────────
        stdhead($lang->search['title_results_threads']);
        build_breadcrumb();
        echo search_css();
        echo '<div class="container-md st-page sr-page">';

        echo sr_head(
            'fa-magnifying-glass', 'st-soft-primary',
            sr_e('sec_thread_results'),
            sr_fmt((int)$threadcount === 1 ? 'lbl_found_thread_one' : 'lbl_found_threads', ts_nf($threadcount))
        );

        echo '<div class="st-card sr-list">' . sr_sort_bar([
            'lastpost' => $lang->search['opt_sort_date'],
            'replies'  => $lang->search['opt_sort_replies'],
            'views'    => $lang->search['opt_sort_views'],
            'rating'   => $lang->search['opt_sort_rating'],
            'subject'  => $lang->search['opt_sort_subject'],
            'starter'  => $lang->search['opt_sort_author'],
            'forum'    => $lang->search['opt_sort_forum'],
        ], $sortby, $order, $sorturl);

        $f_postsperpage  = 10;
        $maxmultilinks   = 5;

        foreach ($thread_cache as $thread) {
            if ($thread['userusername']) $thread['username'] = $thread['userusername'];
            $raw_name              = (string)$thread['username'];
            $thread['username']    = htmlspecialchars_uni($raw_name);
            $thread['subject']     = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

            $thread_link  = get_thread_link($thread['tid']) . $highlight;
            $lastpostdate = my_datee('relative', $thread['lastpost']);
            $lp_uid       = $thread['lastposteruid'];
            $lp_name      = $lp_uid ? htmlspecialchars_uni($thread['lastposter']) : htmlspecialchars_uni($lang->guest);
            $lp_link      = $lp_uid ? build_profile_link($lp_name, $lp_uid) : $lp_name;

            // new/hot/closed badges (логика прежняя)
            $badges = '';
            $read_cutoff = TIMENOW - $threadreadcut * 86400;
            $forum_read  = $readforums[$thread['fid']] ?? 0;
            if ($forum_read == 0 || $forum_read < $read_cutoff) $forum_read = $read_cutoff;
            if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
                $last_read = $thread['lastread'] ?? $read_cutoff;
            } else {
                $last_read = my_get_array_cookie('threadread', (string)$thread['tid']);
            }
            if ($forum_read > $last_read) $last_read = $forum_read;
            if ($thread['lastpost'] > $last_read && $last_read) {
                $badges .= sr_badge('st-soft-primary', 'fa-circle sr-dot', sr_e('badge_new'));
            }
            if ($thread['replies'] >= 20 || $thread['views'] >= 150) {
                $badges .= sr_badge('st-soft-danger', 'fa-fire', sr_e('badge_hot'));
            }
            if ($thread['closed'] == 1) {
                $badges .= sr_badge('st-soft-muted', 'fa-lock');
            }
            if (!empty($thread['dot_icon'])) {
                $badges .= sr_badge('st-soft-info', 'fa-reply', '', 'sr-own');
            }

            // multipage
            $multipages_html = '';
            $thread['posts'] = $thread['replies'] + 1;
            if ($is_mod) $thread['posts'] += $thread['unapprovedposts'];
            if ($thread['posts'] > $f_postsperpage) {
                $total_pages = (int)ceil($thread['posts'] / $f_postsperpage);
                $stop        = min($total_pages, $maxmultilinks);
                $multipages_html = '<div class="sr-multipage">';
                for ($i = 1; $i <= $stop; $i++) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $i) . $highlight . '">' . $i . '</a>';
                }
                if ($total_pages > $maxmultilinks) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $total_pages) . $highlight . '">…' . $total_pages . '</a>';
                }
                $multipages_html .= '</div>';
            }

            $forum_name   = htmlspecialchars_uni((string)($forumcache[$thread['fid']]['name'] ?? ''));
            $forum_link   = get_forum_link($thread['fid']);
            $profile_link = get_profile_link((int)$thread['uid']);
            $avatar_html  = sr_avatar((string)($thread['avatar'] ?? ''), (string)($thread['avatardimensions'] ?? ''), $raw_name);

            echo '<div class="sr-item">';
            echo   '<a href="' . $profile_link . '" class="sr-item__ava sr-above">' . $avatar_html . '</a>';
            echo   '<div class="sr-item__body">';
            echo     '<div class="sr-item__top">';
            echo       '<a href="' . $thread_link . '" class="sr-item__title sr-stretch">' . $thread['subject'] . '</a>';
            echo       $badges;
            echo     '</div>';
            echo     '<div class="sr-item__meta">';
            echo       '<a href="' . $forum_link . '" class="st-badge st-soft-info sr-forum sr-above"><i class="fa-solid fa-folder-open"></i><span>' . $forum_name . '</span></a>';
            echo       '<span><i class="fa-solid fa-user"></i><a href="' . $profile_link . '" class="sr-above">' . $thread['username'] . '</a></span>';
            echo       '<span><i class="fa-solid fa-comments"></i>' . sr_fmt('lbl_replies_count', ts_nf($thread['replies'])) . '</span>';
            echo       '<span><i class="fa-solid fa-eye"></i>' . sr_fmt('lbl_views_count', ts_nf($thread['views'])) . '</span>';
            if ((float)($thread['avg_rating'] ?? 0) > 0) {
                echo   '<span class="sr-rating"><i class="fa-solid fa-star"></i>' . number_format((float)$thread['avg_rating'], 1) . '</span>';
            }
            echo       '<span class="sr-lastpost"><i class="fa-solid fa-clock"></i><span class="sr-above">' . sr_fmt('lbl_lastpost_by', $lastpostdate, $lp_link) . '</span></span>';
            echo     '</div>';
            echo     $multipages_html;
            echo   '</div>';
            echo   '<div class="sr-item__act">';
            echo     '<a href="' . $thread_link . '" class="btn btn-sm btn-outline-primary st-pill sr-go sr-above"><span>' . sr_e('btn_view') . '</span><i class="fa-solid fa-arrow-right"></i></a>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>'; // .sr-list

        // Pagination
        $mp_url = 'search.php?action=results&amp;sid=' . htmlspecialchars_uni($sid) . "&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="st-pager sr-pager">' . multipage((int)$threadcount, $perpage, $page, $mp_url) . '</div>';

        echo '</div>'; // .sr-page
        $plugins->run_hooks('search_results_end');
        stdfoot();

    } else {
        // ── Posts ──────────────────────────────────────────────────────────

        if (empty($search['posts'])) stderr($lang->search['error_nosearchresults']);

        $unapproved_where   = get_visible_where();
        $post_cache_options = [];
        if ($searchhardlimit > 0) $post_cache_options['limit'] = $searchhardlimit;
        if (str_contains($sortfield, 'p.')) {
            $post_cache_options['order_by']  = str_replace('p.', '', $sortfield);
            $post_cache_options['order_dir'] = $order;
        }

        $tids = []; $pids = [];
        $order_clause = !empty($post_cache_options['order_by']) ? " ORDER BY {$post_cache_options['order_by']} {$post_cache_options['order_dir']}" : '';
        $limit_clause = !empty($post_cache_options['limit']) ? " LIMIT {$post_cache_options['limit']}" : '';
        $q = $db->sql_query_prepared("SELECT pid, tid FROM posts WHERE pid IN({$search['posts']}) AND ({$unapproved_where}){$order_clause}{$limit_clause}");
        while ($q && ($p = $db->fetch_array($q))) {
            $pids[$p['pid']] = $p['tid'];
            $tids[$p['tid']][$p['pid']] = $p['pid'];
        }

        if (!empty($pids)) {
            $gp = forum_permissions(); $permsql = ''; $onlyusfids = []; $permsql_params = [];
            foreach ($gp as $fid => $fp) { if (!empty($fp['canonlyviewownthreads'])) $onlyusfids[] = $fid; }
            if ($onlyusfids) { $permsql .= ' OR (fid IN(' . implode(',', $onlyusfids) . ") AND uid!=?)"; $permsql_params[] = (int)$CURUSER['id']; }
            $uf = get_unsearchable_forums(); if ($uf) $permsql .= " OR fid IN ($uf)";
            $ia = get_inactive_forums();     if ($ia) $permsql .= " OR fid IN ($ia)";
            $tid_list = implode(',', array_keys($tids));
            $q = $db->sql_query_prepared("SELECT tid FROM threads WHERE tid IN({$tid_list}) AND (NOT ({$unapproved_where}){$permsql} OR closed LIKE 'moved|%')", $permsql_params);
            while ($q && ($t = $db->fetch_array($q))) {
                foreach ($tids[$t['tid']] as $pid) unset($pids[$pid]);
                unset($tids[$t['tid']]);
            }
        }

        $postcount = count($pids);

        if (!$postcount) stderr($lang->search['error_nosearchresults']);
        $search['posts'] = implode(',', array_keys($pids));
        $tids_str        = implode(',', array_keys($tids));

        $readthreads = [];
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q = $db->sql_query_prepared("SELECT tid, dateline FROM threadsread WHERE uid=? AND tid IN({$tids_str})", [(int)$CURUSER['id']]);
            while ($q && ($rt = $db->fetch_array($q))) $readthreads[$rt['tid']] = $rt['dateline'];
        }

        $pages = max(1, (int)ceil($postcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query_prepared("
            SELECT p.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup,
                   t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views,
                   t.lastpost AS thread_lastpost, t.closed AS thread_closed, t.uid AS thread_uid
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            LEFT JOIN forums f ON (t.fid = f.fid)
            WHERE p.pid IN ({$search['posts']})
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ");

        stdhead($lang->search['title_results_posts']);
        build_breadcrumb();
        echo search_css();
        echo '<div class="container-md st-page sr-page">';

        echo sr_head(
            'fa-comment-dots', 'st-soft-success',
            sr_e('sec_post_results'),
            sr_fmt($postcount === 1 ? 'lbl_found_post_one' : 'lbl_found_posts', ts_nf($postcount))
        );

        echo '<div class="st-card sr-list">' . sr_sort_bar([
            'dateline' => $lang->search['opt_sort_date'],
            'subject'  => $lang->search['opt_sort_subject'],
            'starter'  => $lang->search['opt_sort_author'],
            'forum'    => $lang->search['opt_sort_forum'],
        ], $sortby, $order, $sorturl);

        $kw_raw = (string)($search['keywords'] ?? '');

        $post_rows = [];
        while ($q && ($row = $db->fetch_array($q))) $post_rows[] = $row;
        $attach_imgs = sr_load_attach_images($post_rows, $fpermissions);

        foreach ($post_rows as $post) {
            if ($post['userusername']) $post['username'] = $post['userusername'];
            $raw_name               = (string)$post['username'];
            $post['username']       = htmlspecialchars_uni($raw_name);
            $post['thread_subject'] = htmlspecialchars_uni($parser->parse_badwords((string)$post['thread_subject']));

            $preview     = sr_post_preview((string)$post['message'], $kw_raw, $attach_imgs[(int)$post['pid']] ?? []);
            $posted      = my_datee('relative', $post['dateline']);
            $thread_url  = get_thread_link($post['tid']) . $highlight;
            $post_url    = get_post_link($post['pid'], $post['tid']) . $highlight . '#pid' . (int)$post['pid'];
            $forum_name  = htmlspecialchars_uni((string)($forumcache[$post['fid']]['name'] ?? ''));
            $forum_link  = get_forum_link($post['fid']);
            $profile_url = get_profile_link((int)$post['uid']);
            $avatar_html = sr_avatar((string)($post['avatar'] ?? ''), (string)($post['avatardimensions'] ?? ''), $raw_name);

            echo '<div class="sr-item sr-item--post">';
            echo   '<a href="' . $profile_url . '" class="sr-item__ava sr-above">' . $avatar_html . '</a>';
            echo   '<div class="sr-item__body">';
            echo     '<div class="sr-byline">';
            echo       '<a href="' . $profile_url . '" class="sr-byline__author sr-above">' . $post['username'] . '</a>';
            echo       '<span class="sr-byline__time"><i class="fa-solid fa-clock"></i>' . $posted . '</span>';
            echo       sr_badge('st-soft-success', 'fa-comment', sr_e('badge_post'));
            echo     '</div>';
            echo     '<a href="' . $post_url . '" class="sr-item__title sr-stretch">' . $post['thread_subject'] . '</a>';
            if ($preview['text'] !== '') {
                echo '<div class="sr-snippet">' . $preview['text'] . '</div>';
            }
            echo sr_thumbs($preview, $post_url);
            echo     '<div class="sr-item__meta">';
            echo       '<a href="' . $forum_link . '" class="st-badge st-soft-info sr-forum sr-above"><i class="fa-solid fa-folder-open"></i><span>' . $forum_name . '</span></a>';
            echo     '</div>';
            echo   '</div>';
            echo   '<div class="sr-item__act">';
            echo     '<a href="' . $post_url . '" class="btn btn-sm btn-outline-primary st-pill sr-go sr-above"><span>' . sr_e('btn_view_post') . '</span><i class="fa-solid fa-arrow-right"></i></a>';
            echo     '<a href="' . $thread_url . '" class="sr-thread-link sr-above"><i class="fa-solid fa-list"></i>' . sr_e('btn_thread') . '</a>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>'; // .sr-list

        // Pagination
        $mp_url2 = "search.php?action=results&amp;sid=" . htmlspecialchars_uni($mybb->get_input('sid')) . "&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="st-pager sr-pager">' . multipage($postcount, $perpage, $page, $mp_url2) . '</div>';

        echo '</div>'; // .sr-page
        $plugins->run_hooks('search_results_end');
        stdfoot();
    }
// ─────────────────────────────────────────────────────────────────────────────
// findguest / finduser / finduserthreads / getnew / getdaily
// ─────────────────────────────────────────────────────────────────────────────
} elseif (in_array($mybb->input['action'], ['findguest','finduser','finduserthreads','getnew','getdaily'], true)) {

    $action = $mybb->input['action'];

    // ВАЖНО: $where_sql сохраняется как есть в searchlog.querycache и позже
    // повторно склеивается в новый запрос на странице результатов (action=results).
    // Поэтому здесь используются литеральные значения, а не '?' — все они
    // гарантированно int благодаря MyBB::INPUT_INT/(int)-касту, плейсхолдеры
    // в сохранённом querycache были бы бесполезны при повторном использовании.
    $where_sql = match ($action) {
        'findguest'       => "uid='0'",
        'finduser',
        'finduserthreads' => "uid='" . $mybb->get_input('uid', MyBB::INPUT_INT) . "'",
        'getnew'          => "lastpost >= '" . (int)$CURUSER['lastvisit'] . "'",
        'getdaily'        => (function() use ($mybb): string {
            $days = max(1, $mybb->get_input('days', MyBB::INPUT_INT));
            return "lastpost >= '" . (TIMENOW - 86400 * $days) . "'";
        })(),
    };

    // optional fid filter for getnew/getdaily
    if (in_array($action, ['getnew','getdaily'], true)) {
        if ($mybb->get_input('fid', MyBB::INPUT_INT)) {
            $where_sql .= " AND fid='" . $mybb->get_input('fid', MyBB::INPUT_INT) . "'";
        } elseif ($mybb->get_input('fids')) {
            $fids = array_map('intval', explode(',', $mybb->get_input('fids')));
            if ($fids) $where_sql .= ' AND fid IN (' . implode(',', $fids) . ')';
        }
    }

    $uf = get_unsearchable_forums(); if ($uf) $where_sql .= " AND fid NOT IN ($uf)";
    $ia = get_inactive_forums();     if ($ia) $where_sql .= " AND fid NOT IN ($ia)";
    $where_sql .= ' AND (' . get_visible_where() . ')';

    $gp = forum_permissions(); $onlyusfids = [];
    foreach ($gp as $fid => $fp) {
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
    }
    if (!empty($onlyusfids)) {
        if ($action === 'findguest') {
            $where_sql .= ' AND fid NOT IN(' . implode(',', $onlyusfids) . ')';
        } else {
            $where_sql .= " AND ((fid IN(" . implode(',', $onlyusfids) . ") AND uid='{$CURUSER['id']}') OR fid NOT IN(" . implode(',', $onlyusfids) . "))";
        }
    }

    $resulttype = 'threads'; $querycache = ''; $pids = ''; $tids = ''; $comma = '';

    if (in_array($action, ['finduserthreads','getnew','getdaily'], true)) {
        $q = $db->sql_query_prepared("SELECT tid FROM threads WHERE {$where_sql}");
        while ($q && ($tid = $db->fetch_field($q, 'tid'))) { $tids .= $comma . $tid; $comma = ','; }
        $querycache = $where_sql;
    } else {
        $resulttype = 'posts';
        $order_limit = ' ORDER BY dateline DESC, pid DESC';
        if ($searchhardlimit > 0) $order_limit .= " LIMIT {$searchhardlimit}";
        $q = $db->sql_query_prepared("SELECT pid FROM posts WHERE {$where_sql}{$order_limit}");
        while ($q && ($pid = $db->fetch_field($q, 'pid'))) { $pids .= $comma . $pid; $comma = ','; }
        $comma = '';
        $q = $db->sql_query_prepared("SELECT tid FROM threads WHERE {$where_sql}");
        while ($q && ($tid = $db->fetch_field($q, 'tid'))) { $tids .= $comma . $tid; $comma = ','; }
    }

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');
    $db->sql_query_prepared(
        "INSERT INTO searchlog (`sid`,`uid`,`dateline`,`ipaddress`,`threads`,`posts`,`resulttype`,`querycache`,`keywords`) VALUES (?,?,?,?,?,?,?,?,?)",
        [$sid, $CURUSER['id'], TIMENOW, $session->packedip, $tids, $pids, $resulttype, $querycache, '']
    );
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// do_search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'do_search') {

    if (!verify_post_check($mybb->get_input('postcode'))) {
        stderr($lang->search['error_invalidsearch']);
    }

    $plugins->run_hooks('search_do_search_start');

    $searchfloodtime = 30;
    if ($searchfloodtime > 0 && $usergroups['cansearch'] != 1) {
        $timecut = TIMENOW - $searchfloodtime;
        if ($CURUSER['id']) {
            $cond_sql    = "uid = ?";
            $cond_params = [(int)$CURUSER['id']];
        } else {
            $cond_sql    = "uid = '0' AND ipaddress = ?";
            $cond_params = [$session->packedip];
        }
        $q  = $db->sql_query_prepared(
            "SELECT * FROM searchlog WHERE {$cond_sql} AND dateline > ? ORDER BY dateline DESC",
            [...$cond_params, $timecut]
        );
        $ls = $q ? $db->fetch_array($q) : null;
        if (!empty($ls['sid'])) {
            $rt  = $searchfloodtime - (TIMENOW - $ls['dateline']);
            stderr($rt === 1
                ? ags_fmt($lang->search['error_searchflooding_1'], $searchfloodtime)
                : ags_fmt($lang->search['error_searchflooding'], $searchfloodtime, $rt)
            );
        }
    }

    $resulttype = $mybb->get_input('showresults') === 'threads' ? 'threads' : 'posts';
    $forums     = isset($mybb->input['forums']) && is_array($mybb->input['forums'])
        ? $mybb->get_input('forums', MyBB::INPUT_ARRAY)
        : [$mybb->get_input('forums')];

    $search_data = [
        'keywords'      => $mybb->input['keywords'],
        'author'        => $mybb->get_input('author'),
        'postthread'    => $mybb->get_input('postthread',    MyBB::INPUT_INT),
        'matchusername' => $mybb->get_input('matchusername', MyBB::INPUT_INT),
        'postdate_from' => to_search_ts($mybb->get_input('postdate_from'), false),
        'postdate_to'   => to_search_ts($mybb->get_input('postdate_to'),   true),
        'forums'        => $forums,
        'findthreadst'  => $mybb->get_input('findthreadst',  MyBB::INPUT_INT),
        'numreplies'    => $mybb->get_input('numreplies',    MyBB::INPUT_INT),
    ];

    // "0" (только неодобренные) — тоже значение фильтра, поэтому не empty(), а проверка на пустую строку
    $visible_filter = $mybb->get_input('visible');
    if ($is_mod && in_array($visible_filter, ['0', '1'], true)) {
        $search_data['visible'] = (int)$visible_filter;
    }

    if (!$db->can_search) stderr($lang->search['error_no_search_support']);

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft($search_data)
        : perform_search_mysql($search_data);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');

	$db->sql_query_prepared(
        "INSERT INTO searchlog (`sid`,`uid`,`dateline`,`ipaddress`,`threads`,`posts`,`resulttype`,`querycache`,`keywords`) VALUES (?,?,?,?,?,?,?,?,?)",
        [$sid, $CURUSER['id'], $now, $session->packedip, $search_results['threads'], $search_results['posts'], $resulttype, $search_results['querycache'], $mybb->input['keywords']]
    );

    $so = my_strtolower($mybb->get_input('sortordr'));
    $sortorder = in_array($so, ['asc','desc']) ? $so : 'desc';
    $sortby    = htmlspecialchars_uni($mybb->get_input('sortby'));
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid={$sid}&sortby={$sortby}&order={$sortorder}", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// thread search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'thread') {

    $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
    $forum  = get_forum($thread['fid']);
    if (!$forum) stderr($lang->search['error_invalidforum']);
    if ($forum['open'] == 0 || $forum['type'] !== 'f') stderr($lang->search['error_closedinvalidforum']);
    if (!$db->can_search) stderr($lang->search['error_no_search_support']);

    $plugins->run_hooks('search_thread_start');

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)])
        : perform_search_mysql(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)]);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_thread_process');
    $db->sql_query_prepared(
        "INSERT INTO searchlog (`sid`,`uid`,`dateline`,`ipaddress`,`threads`,`posts`,`resulttype`,`querycache`,`keywords`) VALUES (?,?,?,?,?,?,?,?,?)",
        [$sid, $CURUSER['id'], $now, $session->packedip, $search_results['threads'], $search_results['posts'], 'posts', $search_results['querycache'], $mybb->input['keywords']]
    );
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// Search form (default)
// ─────────────────────────────────────────────────────────────────────────────
} else {

    $plugins->run_hooks('search_start');
    $srchlist      = make_searchable_forums();
    $moderator_options = '';
    if ($is_mod) {
        $moderator_options = sr_field('col-12', 'fa-shield-halved', sr_e('mod_options'),
            '<div class="sr-mod">'
          .   '<select name="visible" id="srVisible" class="form-select sr-field-ctrl sr-mod__select">'
          .     '<option value="">' . sr_e('display_all') . '</option>'
          .     '<option value="1">' . sr_e('display_only_approved') . '</option>'
          .     '<option value="0">' . sr_e('display_only_unapproved') . '</option>'
          .   '</select>'
          .   '<span class="sr-hint">' . sr_e('results') . '</span>'
          . '</div>',
            'srVisible'
        );
    }
    $plugins->run_hooks('search_end');

    $kw_val     = htmlspecialchars_uni($mybb->get_input('keywords'));
    $author_val = htmlspecialchars_uni($mybb->get_input('author'));

    $quick_links = [
        ['search.php?action=getnew',            'fa-bolt',          'lnk_new_posts'],
        ['search.php?action=getdaily&amp;days=1', 'fa-calendar-day',  'lnk_today'],
        ['search.php?action=getdaily&amp;days=7', 'fa-calendar-week', 'lnk_this_week'],
    ];
    if ($CURUSER['id']) {
        $quick_links[] = ['search.php?action=finduserthreads&amp;uid=' . (int)$CURUSER['id'], 'fa-user',    'lnk_my_threads'];
        $quick_links[] = ['search.php?action=finduser&amp;uid=' . (int)$CURUSER['id'],        'fa-comment', 'lnk_my_posts'];
    }

    stdhead($lang->search['title_search']);
    build_breadcrumb();
    echo search_css();
    ?>
    <div class="container-md st-page sr-page">

        <?= sr_head('fa-magnifying-glass', 'st-soft-primary', sr_e('sec_hero_title'), sr_e('hint_hero')) ?>

        <nav class="sr-quick" aria-label="<?= sr_e('sec_hero_title') ?>">
            <?php foreach ($quick_links as [$href, $icon, $key]): ?>
            <a href="<?= $href ?>" class="btn btn-sm btn-outline-secondary st-pill sr-quick-link"><i class="fa-solid <?= $icon ?>"></i><?= sr_e($key) ?></a>
            <?php endforeach; ?>
        </nav>

        <form action="search.php" method="post" id="srForm" class="st-card sr-form">
            <input type="hidden" name="action" value="do_search">
            <input type="hidden" name="postcode" value="<?= generate_post_check() ?>">
            <input type="hidden" name="findthreadst" value="1">
            <div class="st-card__body">

                <!-- Main input -->
                <div class="sr-main-row">
                    <div class="input-group st-subject sr-kw">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="keywords" id="srKeywords" class="form-control"
                               placeholder="<?= sr_e('ph_keywords') ?>"
                               value="<?= $kw_val ?>" autocomplete="off" maxlength="200"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="srSuggestBox"
                               aria-label="<?= sr_e('ph_keywords') ?>">
                        <button type="button" class="btn sr-btn sr-btn-ghost" id="srClear" title="<?= sr_e('btn_clear') ?>" aria-label="<?= sr_e('btn_clear') ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <button type="submit" class="btn sr-btn sr-btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i><span class="sr-btn__txt"><?= sr_e('btn_search') ?></span>
                        </button>
                    </div>
                    <div id="srSuggestBox" class="sr-suggest-box" role="listbox" style="display:none;"></div>
                </div>

                <!-- Show results as + advanced toggle -->
                <div class="sr-optrow">
                    <div class="sr-showas" role="radiogroup">
                        <span class="sr-showas__lbl"><?= sr_e('lbl_show_as') ?></span>
                        <div class="sr-seg">
                            <input class="btn-check" type="radio" name="showresults" id="srT" value="threads" checked>
                            <label class="sr-seg__btn" for="srT"><i class="fa-solid fa-list"></i><?= sr_e('opt_show_threads') ?></label>
                            <input class="btn-check" type="radio" name="showresults" id="srP" value="posts">
                            <label class="sr-seg__btn" for="srP"><i class="fa-solid fa-comment"></i><?= sr_e('opt_show_posts') ?></label>
                        </div>
                    </div>

                    <button type="button" class="sr-adv-toggle" data-bs-toggle="collapse" data-bs-target="#srAdv"
                            aria-expanded="false" aria-controls="srAdv">
                        <i class="fa-solid fa-sliders"></i><?= sr_e('lbl_advanced') ?>
                        <i class="fa-solid fa-chevron-down sr-chev"></i>
                    </button>
                </div>

                <div class="collapse" id="srAdv">
                    <div class="sr-adv-panel">
                        <div class="row g-3">
                            <?= sr_field('col-md-6', 'fa-user', sr_e('lbl_author'),
                                '<input type="text" name="author" id="srAuthor" class="form-control sr-field-ctrl" value="' . $author_val . '" placeholder="' . sr_e('ph_author') . '">'
                              . '<div class="form-check sr-exact">'
                              .   '<input class="form-check-input" type="checkbox" name="matchusername" value="1" id="srExact">'
                              .   '<label class="form-check-label" for="srExact">' . sr_e('lbl_exact_match') . '</label>'
                              . '</div>', 'srAuthor') ?>

                            <?= sr_field('col-md-6', 'fa-magnifying-glass-plus', sr_e('lbl_search_in'),
                                '<select name="postthread" id="srIn" class="form-select sr-field-ctrl">'
                              .   '<option value="1">' . sr_e('opt_subject_message') . '</option>'
                              .   '<option value="0">' . sr_e('opt_subject_only') . '</option>'
                              . '</select>', 'srIn') ?>

                            <?= sr_field('col-sm-6 col-lg-3', 'fa-sort', sr_e('lbl_sort_by'),
                                '<select name="sortby" id="srSortby" class="form-select sr-field-ctrl">'
                              .   '<option value="lastpost">' . sr_e('opt_sortby_lastpost') . '</option>'
                              .   '<option value="dateline">' . sr_e('opt_sortby_dateline') . '</option>'
                              .   '<option value="subject">'  . sr_e('opt_sortby_subject')  . '</option>'
                              .   '<option value="replies">'  . sr_e('opt_sortby_replies')  . '</option>'
                              .   '<option value="views">'    . sr_e('opt_sortby_views')    . '</option>'
                              .   '<option value="starter">'  . sr_e('opt_sortby_starter')  . '</option>'
                              .   '<option value="forum">'    . sr_e('opt_sortby_forum')    . '</option>'
                              . '</select>', 'srSortby') ?>

                            <?= sr_field('col-sm-6 col-lg-3', 'fa-arrow-down-wide-short', sr_e('lbl_order'),
                                '<select name="sortordr" id="srOrder" class="form-select sr-field-ctrl">'
                              .   '<option value="desc">' . sr_e('opt_order_desc') . '</option>'
                              .   '<option value="asc">'  . sr_e('opt_order_asc')  . '</option>'
                              . '</select>', 'srOrder') ?>

                            <?= sr_field('col-sm-6 col-lg-3', 'fa-calendar', sr_e('lbl_date_from'),
                                '<input type="text" name="postdate_from" id="srDateFrom" class="form-control sr-field-ctrl" placeholder="' . sr_e('ph_date') . '" autocomplete="off" value="' . htmlspecialchars_uni($mybb->get_input('postdate_from')) . '">', 'srDateFrom') ?>

                            <?= sr_field('col-sm-6 col-lg-3', 'fa-calendar', sr_e('lbl_date_to'),
                                '<input type="text" name="postdate_to" id="srDateTo" class="form-control sr-field-ctrl" placeholder="' . sr_e('ph_date') . '" autocomplete="off" value="' . htmlspecialchars_uni($mybb->get_input('postdate_to')) . '">', 'srDateTo') ?>

                            <?= sr_field('col-md-4', 'fa-reply', sr_e('lbl_min_replies'),
                                '<input type="number" name="numreplies" id="srReplies" class="form-control sr-field-ctrl" value="0" min="0">', 'srReplies') ?>

                            <?= sr_field('col-md-8', 'fa-comments', sr_e('lbl_forums'),
                                $srchlist . '<div class="sr-hint">' . sr_e('hint_forums_multi') . '</div>', 'select') ?>

                            <?= $moderator_options ?>
                        </div>
                    </div>
                </div>

            </div><!-- /.st-card__body -->
        </form>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <script src="<?= $BASEURL ?>/admin/scripts/flatpickr.js"></script>
    <?php
    // JS strings: js_* keys → AGS_LANG without the prefix
    $ags_js_lang = [];
    foreach ($lang->search as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) $ags_js_lang[substr((string)$k, 3)] = $v;
    }
    ?>
    <script>const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= $BASEURL ?>/scripts/search_page.js?ver=3"></script>
    <?php
    stdfoot();
}
