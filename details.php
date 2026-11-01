<?php

declare(strict_types=1);

define("SCRIPTNAME", "details.php");

require_once('global.php');

$lang->load('details');
$lang->load('comment');

// ── Lang helpers ────────────────────────────────────────────────────────────
// $lang->load() turns {1} into %1$s, so both forms are substituted.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

// Plural form from a lang string: "one|other" (2 forms, English rules)
// or "one|few|many" (3 forms, Russian rules).
if (!function_exists('ags_plural')) {
    function ags_plural(int $n, string $forms): string
    {
        $f = explode('|', $forms);
        $n = abs($n);

        if (count($f) < 2) {
            return $f[0];
        }
        if (count($f) === 2) {
            return $n === 1 ? $f[0] : $f[1];
        }

        $m10  = $n % 10;
        $m100 = $n % 100;

        return match (true) {
            $m10 === 1 && $m100 !== 11                           => $f[0],
            $m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) => $f[1],
            default                                              => $f[2],
        };
    }
}

require_once 'cache/smilies.php';

require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;

if (empty($CURUSER['id'])) {
    print_no_permission();
}

require_once INC_PATH . '/functions_icons.php';
require_once(INC_PATH . '/commenttable.php');
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_getagent.php';
require_once INC_PATH . '/functions_comment_attachments.php';

require_once(INC_PATH . '/class_parser.php');
$parser = new postParser;

$parser_options = [
    "allow_html"      => 0,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1,
    "nofollow_on"     => 1,
];

gzip();
maxsysop();

// class_parser.php: [quote] links point to comments, not forum posts
define("IN_ARCHIVE", true);

const DT_CSS_VER = 1;
const DT_JS_VER  = 3;

// ── Helpers ─────────────────────────────────────────────────────────────────
function get_comment($pid)
{
    global $db;
    static $post_cache;

    $pid = (int)$pid;

    if (isset($post_cache[$pid])) {
        return $post_cache[$pid];
    }

    $query = $db->sql_query_prepared("SELECT * FROM comments WHERE id = ?", [$pid]);
    $post  = $query ? $db->fetch_array($query) : null;

    if ($post) {
        $post_cache[$pid] = $post;
        return $post;
    }

    $post_cache[$pid] = false;
    return false;
}

// ── pid → id trick ──────────────────────────────────────────────────────────
// get_comment() кэширует результат — ниже тот же комментарий берётся без запроса
if (!empty($mybb->input['pid']) && empty($mybb->input['id'])) {
    if (isset($style) && $style['pid'] == $mybb->input['pid'] && $style['id']) {
        $mybb->input['id'] = $style['id'];
        unset($style['id']);
    } else {
        $post = get_comment((int)$mybb->input['pid']);

        if (!$post) {
            stderr($lang->global['invalid_comm']);
        }

        $mybb->input['id'] = $post['torrent'];
    }
}

$is_mod = is_mod($usergroups);

function getHealthColor(mixed $seeders): string
{
    $seeders = (int)$seeders;

    return match (true) {
        $seeders >= 10 => 'success',
        $seeders >= 3  => 'warning',
        default        => 'danger',
    };
}

function getHealthPercentage(mixed $seeders, mixed $leechers): int
{
    $seeders  = (int)$seeders;
    $leechers = (int)$leechers;
    $total    = $seeders + $leechers;

    return $total === 0 ? 0 : (int)round(($seeders / $total) * 100);
}

/** Soft badge with the file extension */
function dt_file_badge(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $groups = [
        'video'   => ['mp4','mkv','avi','mov','wmv','flv','webm','mpeg','mpg','3gp','m4v','vob','ts','m2ts','ogv','rm','rmvb'],
        'audio'   => ['mp3','flac','wav','ogg','m4a','aac','ape','opus','dts','ac3'],
        'image'   => ['jpg','jpeg','png','gif','bmp','webp','svg','tiff'],
        'archive' => ['zip','rar','7z','tar','gz','bz2','xz'],
        'doc'     => ['nfo','txt','md','log','pdf','doc','docx','xls','xlsx','ppt','pptx','cue','srt','ass','sub'],
        'code'    => ['php','html','css','js','json','xml','py','java','c','cpp','sh','bat'],
        'exec'    => ['exe','iso','apk','bin','dll','app','deb','rpm','msi','img'],
    ];

    $look = [
        'video'   => ['st-soft-danger',  'fa-file-video'],
        'audio'   => ['st-soft-primary', 'fa-file-audio'],
        'image'   => ['st-soft-success', 'fa-file-image'],
        'archive' => ['st-soft-warning', 'fa-file-zipper'],
        'doc'     => ['st-soft-muted',   'fa-file-lines'],
        'code'    => ['st-soft-info',    'fa-file-code'],
        'exec'    => ['st-soft-muted',   'fa-microchip'],
        'default' => ['st-soft-muted',   'fa-file'],
    ];

    $type = 'default';
    foreach ($groups as $key => $exts) {
        if (in_array($ext, $exts, true)) {
            $type = $key;
            break;
        }
    }

    [$soft, $icon] = $look[$type];
    $label = $ext !== '' ? htmlspecialchars(strtoupper($ext)) : '';

    return '<span class="st-badge ' . $soft . ' dt-tree__ext"><i class="fa-solid ' . $icon . '"></i>' . $label . '</span>';
}

/** Total size of a folder in the file tree */
function dt_tree_size(array $node): int
{
    $sum = 0;
    foreach ($node as $item) {
        $sum += is_array($item) ? dt_tree_size($item) : (int)$item;
    }
    return $sum;
}

/** File tree: folders collapse (Bootstrap collapse, no data-bs-parent so "expand all" works) */
function dt_render_tree(array $tree, int $level = 0): string
{
    static $counter = 0;

    // Folders first, then files, both by name
    uksort($tree, static function ($a, $b) use ($tree): int {
        $da = is_array($tree[$a]);
        $db = is_array($tree[$b]);
        if ($da !== $db) {
            return $da ? -1 : 1;
        }
        return strnatcasecmp((string)$a, (string)$b);
    });

    // A lone top-level folder (the usual release layout) starts expanded
    $open_single = $level === 0 && count($tree) === 1 && is_array(reset($tree));

    $html = '';
    foreach ($tree as $name => $content) {
        $name = (string)$name;   // numeric file names become int array keys

        if (is_array($content)) {
            $cid  = 'dt-dir-' . (++$counter);
            $show = $open_single;

            $html .= '<div class="dt-tree__dir">'
                   . '<button class="dt-tree__row dt-tree__toggle' . ($show ? '' : ' collapsed') . '" type="button"'
                   . ' data-bs-toggle="collapse" data-bs-target="#' . $cid . '"'
                   . ' aria-expanded="' . ($show ? 'true' : 'false') . '" aria-controls="' . $cid . '">'
                   . '<i class="fa-solid fa-chevron-right dt-tree__chev" aria-hidden="true"></i>'
                   . '<i class="fa-solid fa-folder dt-tree__folder" aria-hidden="true"></i>'
                   . '<span class="dt-tree__name">' . htmlspecialchars($name) . '</span>'
                   . '<span class="dt-tree__size">' . mksize(dt_tree_size($content)) . '</span>'
                   . '</button>'
                   . '<div class="collapse dt-tree__kids' . ($show ? ' show' : '') . '" id="' . $cid . '">'
                   . dt_render_tree($content, $level + 1)
                   . '</div></div>';
        } else {
            $html .= '<div class="dt-tree__row dt-tree__file">'
                   . dt_file_badge($name)
                   . '<span class="dt-tree__name" title="' . htmlspecialchars($name, ENT_QUOTES) . '">' . htmlspecialchars($name) . '</span>'
                   . '<span class="dt-tree__size">' . mksize((int)$content) . '</span>'
                   . '</div>';
        }
    }

    return $html;
}

/** Profile link with a safely escaped, group-styled name */
function dt_user_link(int $uid, string $username, int|string $usergroup): string
{
    return '<a href="' . get_profile_link($uid) . '">'
         . format_name(htmlspecialchars_uni($username), (int)$usergroup) . '</a>';
}

// ── Load torrent ────────────────────────────────────────────────────────────
// Один запрос: торрент + NFO + категория + владелец
$query = $db->sql_query_prepared("
    SELECT t.*, n.nfo,
           c.name AS categoryname, c.pid, c.type, c.id AS categoryid, c.icon,
           u.username, u.usergroup, u.enabled, u.donor, u.warned, u.leechwarn,
           u.canupload, u.candownload, u.cancomment
    FROM torrents t
    LEFT JOIN torrents_nfo n ON n.torrent_id = t.id
    LEFT JOIN categories  c ON c.id = t.category
    LEFT JOIN users       u ON u.id = t.owner
    WHERE t.id = ?
    LIMIT 1
", [(int)($mybb->input['id'] ?? 0)]);

$Torrent = $query ? $db->fetch_array($query) : null;

if (!$Torrent) {
    stderr($lang->global['notorrentid'], $SITENAME . ' - ' . $lang->details['title_notfound'], 404, 'torrent');
}
if ($Torrent['banned'] === 'yes' && !$is_mod) {
    stderr($lang->global['torrentbanned']);
}

$id       = (int)$Torrent['id'];
$owner_id = (int)$Torrent['owner'];
$my_id    = (int)$CURUSER['id'];
$is_owner = $my_id === $owner_id;
$post_key = htmlspecialchars((string)($mybb->post_code ?? ''), ENT_QUOTES);

// get_user_icons() ждёт id владельца в ['id']
$torrent2 = ['id' => $owner_id] + $Torrent;

$lang->load('browse');
$lang->load('upload');

require_once(INC_PATH . '/functions_mkprettytime.php');

// ── Similar torrents ────────────────────────────────────────────────────────
$similar_html  = '';
$similar_count = 0;

// Убираем операторы BOOLEAN MODE: "Spider-Man" не должно значить «Spider без Man»
$ft = trim((string)preg_replace('/[+\-<>()~*"@]+/u', ' ', (string)$Torrent['name']));

if ($ft !== '') {
    $sim_res = $db->sql_query_prepared("
        SELECT MATCH(t.name) AGAINST(? IN BOOLEAN MODE) AS score,
               t.id, t.name, t.anonymous, t.owner, t.category, t.size, t.added, t.seeders,
               t.leechers, t.t_image, c.icon AS catimage, c.name AS catname, u.username, u.usergroup
        FROM torrents t
        LEFT JOIN categories c ON (c.id = t.category)
        LEFT JOIN users u ON (t.owner = u.id)
        WHERE MATCH(t.name) AGAINST(? IN BOOLEAN MODE)
          AND t.id != ?
          AND t.visible = 'yes'
          AND t.banned = 'no'
        HAVING score > 1
        ORDER BY score DESC
        LIMIT 12
    ", [$ft, $ft, $id]);

    $no_image = $BASEURL . '/include/templates/default/images/no_image.png';

    while ($sim_res && ($SMTQ = $db->fetch_array($sim_res))) {
        $s_link   = get_torrent_link($SMTQ['id']);
        $s_name   = htmlspecialchars_uni((string)$SMTQ['name']);
        $s_poster = !empty($SMTQ['t_image']) ? htmlspecialchars_uni((string)$SMTQ['t_image']) : $no_image;

        $s_hidden = !$is_mod && (int)$SMTQ['owner'] !== $my_id && $SMTQ['anonymous'] === 'yes';
        if ($s_hidden || empty($SMTQ['username'])) {
            $s_user = '<span class="text-body-secondary"><i class="fa-solid fa-user-secret me-1"></i>' . $lang->global['anonymous'] . '</span>';
        } else {
            $s_user = dt_user_link((int)$SMTQ['owner'], (string)$SMTQ['username'], (int)$SMTQ['usergroup']);
        }

        $similar_html .= '
            <div class="dt-sim">
                <a href="' . $s_link . '" class="dt-sim__poster" title="' . $s_name . '">
                    <img src="' . $s_poster . '" alt="" loading="lazy" data-fallback="' . $no_image . '">
                    <span class="dt-sim__peers">
                        <span class="text-success"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($SMTQ['seeders']) . '</span>
                        <span class="text-danger"><i class="fa-solid fa-arrow-down"></i>' . ts_nf($SMTQ['leechers']) . '</span>
                    </span>
                </a>
                <div class="dt-sim__body">
                    <a href="' . $s_link . '" class="dt-sim__name" title="' . $s_name . '">' . $s_name . '</a>
                    <div class="dt-sim__meta">
                        <a href="' . get_category_link((int)$SMTQ['category']) . '" title="' . htmlspecialchars_uni((string)$SMTQ['catname']) . '">'
                        . '<i class="' . htmlspecialchars((string)$SMTQ['catimage'], ENT_QUOTES) . '"></i></a>
                        <span class="min-w-0 text-truncate">' . $s_user . '</span>
                        <span class="ms-auto text-nowrap">' . mksize($SMTQ['size']) . '</span>
                    </div>
                </div>
            </div>';

        $similar_count++;
    }
}

// ── Tags ─────────────────────────────────────────────────────────────────────
$tags_html = '';
foreach (explode(',', (string)($Torrent['tags'] ?? '')) as $tag) {
    $tag = trim($tag);
    if ($tag === '') {
        continue;
    }
    $tags_html .= '<a href="' . $BASEURL . '/browse.php?do=search&amp;keywords=' . urlencode($tag) . '&amp;search_type=t_tags" class="dt-tag">'
                . '<i class="fa-solid fa-hashtag"></i>' . htmlspecialchars_uni($tag) . '</a>';
}

// ── Category chain ──────────────────────────────────────────────────────────
$cat_name = htmlspecialchars_uni((string)($Torrent['categoryname'] ?? ''));
$cat_icon = htmlspecialchars((string)($Torrent['icon'] ?? ''), ENT_QUOTES);
$cat_html = '<a href="' . get_category_link((int)($Torrent['categoryid'] ?? 0)) . '">' . $cat_name . '</a>';

if (($Torrent['type'] ?? '') === 's') {
    require(TSDIR . '/cache/categories.php');
    foreach ($_categoriesC as $catarray) {
        if ((int)$catarray['id'] === (int)$Torrent['pid']) {
            $cat_html = '<a href="' . get_category_link($catarray['id']) . '">' . htmlspecialchars_uni((string)$catarray['name']) . '</a>'
                      . '<i class="fa-solid fa-angle-right dt-crumb-sep" aria-hidden="true"></i>' . $cat_html;
            break;
        }
    }
}

// ── Rating data ─────────────────────────────────────────────────────────────
// Один запрос: среднее, число голосов, оценка текущего пользователя и «уже скачивал»
$q2 = $db->sql_query_prepared("
    SELECT ROUND(AVG(r.rating), 1)                      AS avg_r,
           COUNT(r.rating)                              AS cnt,
           MAX(CASE WHEN r.user_id = ? THEN r.rating END) AS mine,
           (SELECT 1 FROM snatched s
             WHERE s.userid = ? AND s.torrentid = ? AND s.finished = 'yes' LIMIT 1) AS snatched,
           (SELECT COUNT(*) FROM comments c WHERE c.torrent = ?)                     AS comms
    FROM torrent_ratings r
    WHERE r.torrent_id = ?
", [$my_id, $my_id, $id, $id, $id]);
$rr = $q2 ? ($db->fetch_array($q2) ?: []) : [];

$rating_avg   = (float)($rr['avg_r'] ?? 0);
$rating_count = (int)($rr['cnt'] ?? 0);
$rating_mine  = (int)($rr['mine'] ?? 0);
$already_snatched = !empty($rr['snatched']);
$threadcount  = (int)($rr['comms'] ?? 0);

// Stars: bi-* classes, the same that rating.js draws after a vote (shared with showthread)
$avg_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    if ($rating_avg >= $i) {
        $avg_stars_html .= '<i class="bi bi-star-fill rating-star-filled"></i>';
    } elseif ($rating_avg >= $i - 0.5) {
        $avg_stars_html .= '<i class="bi bi-star-half rating-star-filled"></i>';
    } else {
        $avg_stars_html .= '<i class="bi bi-star rating-star-empty"></i>';
    }
}

$user_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    $user_stars_html .= '<i class="bi bi-star-fill user-star' . ($rating_mine >= $i ? ' active' : '') . '" data-value="' . $i . '" onclick="rateTorrent(' . $i . ')"></i>';
}

$votes_label = htmlspecialchars(ags_fmt(ags_plural($rating_count, $lang->details['lbl_votes']), number_format($rating_count)));

$rating_html = '
    <div class="st-card dt-rating" id="dt-rating" data-rating="' . $rating_mine . '" data-torrent="' . $id . '">
        <div class="st-card__head">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-star"></i></span>
            ' . htmlspecialchars($lang->details['sec_user_rating']) . '
            <span class="ms-auto st-badge st-soft-muted">' . $votes_label . '</span>
        </div>
        <div class="st-card__body">
            <div class="dt-rating__top">
                <div class="dt-rating__score"><span class="rating-score">' . ($rating_count > 0 ? number_format($rating_avg, 1) : '—') . '</span><small>/10</small></div>
                <div class="rating-stars dt-rating__stars" id="rating-display">' . $avg_stars_html . '</div>
            </div>
            <div class="dt-rating__mine">
                <div class="user-stars" id="user-stars">' . $user_stars_html . '</div>
                <span class="small text-body-secondary" id="rating-hint">'
                    . ($rating_mine ? $rating_mine . '/10' : htmlspecialchars($lang->details['hint_rate'])) . '</span>
            </div>
        </div>
    </div>';

// ── Comments ────────────────────────────────────────────────────────────────
$comments_html = '';
$page          = 1;

// Ссылка на конкретный комментарий (…-comment-{pid}.html): проверяем, что он есть и относится к этому торренту
$target_comment = null;
$cnf_html       = '';
if (!empty($mybb->input['pid'])) {
    $target_comment = get_comment($mybb->input['pid']);

    if (!$target_comment || (int)$target_comment['torrent'] !== $id) {
        $target_comment = null;

        // Последний комментарий этого торрента — кнопка ведёт прямо к нему
        $cnf_last = '';
        $cnf_q    = $db->sql_query_prepared("SELECT MAX(id) AS last_id FROM comments WHERE torrent = ?", [$id]);
        $cnf_lid  = $cnf_q ? (int)$db->fetch_field($cnf_q, 'last_id') : 0;
        if ($cnf_lid > 0) {
            $cnf_last = $BASEURL . '/' . get_comment_link($cnf_lid, $id) . '#pid' . $cnf_lid;
        }

        $cnf_html = '
        <div class="st-card dt-alert dt-alert--warning" id="comment-not-found" role="alert">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-comment-slash"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">' . htmlspecialchars($lang->details['comment_not_found'] ?? 'Comment not found')
                    . ' <span class="st-badge st-soft-muted">#' . (int)$mybb->input['pid'] . '</span></div>
                <div class="small text-body-secondary">' . htmlspecialchars($lang->details['comment_not_found_desc'] ?? 'It may have been deleted or merged with another comment, or the link is incorrect.') . '</div>
            </div>
            ' . ($cnf_last !== '' ? '<a href="' . htmlspecialchars($cnf_last) . '" class="btn btn-sm btn-warning st-pill text-nowrap">
                <i class="fa-solid fa-arrow-down-wide-short me-1"></i>' . htmlspecialchars($lang->details['comment_not_found_last'] ?? 'Latest comments') . '</a>' : '') . '
            <button type="button" class="btn-close" data-dt-dismiss="#comment-not-found" aria-label="' . htmlspecialchars($lang->details['comment_not_found_close'] ?? 'Close', ENT_QUOTES) . '"></button>
        </div>';
    }
}

if ($threadcount > 0) {
    $perpage = max(1, (int)$ts_perpage);
    $pages   = (int)ceil($threadcount / $perpage);

    if (isset($mybb->input['page']) && $mybb->input['page'] === 'last') {
        $page = $pages;
    } elseif (isset($mybb->input['page'])) {
        $page = (int)$mybb->input['page'];
    }

    // Страница, на которой находится комментарий. Считаем по id — в том же порядке, что и вывод (ORDER BY c.id)
    if ($target_comment) {
        $res = $db->sql_query_prepared(
            "SELECT COUNT(*) AS count FROM comments WHERE torrent = ? AND id <= ?",
            [$id, (int)$target_comment['id']]
        );
        if ($res) {
            $page = (int)ceil((int)$db->fetch_field($res, 'count') / $perpage);
        }
    }

    if ($page > $pages || $page <= 0) {
        $page = 1;
    }

    $start     = ($page - 1) * $perpage;
    $multipage = multipage($threadcount, $perpage, $page, str_replace("{id}", (string)$id, TORRENT_URL_PAGED));

    $allrows = [];
    $subres  = $db->sql_query_prepared("
        SELECT
            c.id, c.torrent AS torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, c.editedat,
            uu.username AS editedbyuname, gg.namestyle AS editbynamestyle,
            u.added AS registered, u.enabled, u.lastactive, u.lastvisit, u.invisible, u.warned, u.leechwarn, u.username, u.usertitle,
            u.usergroup, u.postnum, u.threadnum, u.added, u.comms, u.donor, u.uploaded, u.downloaded,
            u.avatar AS useravatar, u.avatardimensions, u.signature,
            g.title AS grouptitle, g.namestyle
        FROM comments c
        LEFT JOIN users uu ON (c.editedby = uu.id)
        LEFT JOIN usergroups gg ON (uu.usergroup = gg.gid)
        LEFT JOIN users u ON (c.user = u.id)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        WHERE c.torrent = ?
        ORDER BY c.id
        LIMIT ?, ?
    ", [$id, $start, $perpage]);

    if ($subres) {
        while ($subrow = $db->fetch_array($subres)) {
            $allrows[] = $subrow;
        }
        $db->free_result($subres);
    }

    // Вложения всех комментариев страницы — одним запросом
    $all_attachments = [];
    if ($allrows) {
        $comment_ids  = array_map(static fn($r) => (int)$r['id'], $allrows);
        $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
        $att_res      = $db->sql_query_prepared(
            "SELECT * FROM attachments WHERE comment_id IN ($placeholders) AND visible = 1 ORDER BY comment_id, dateuploaded ASC",
            $comment_ids
        );
        while ($att_res && ($att = $db->fetch_array($att_res))) {
            $all_attachments[(int)$att['comment_id']][] = $att;
        }
    }
    $GLOBALS['all_attachments'] = $all_attachments;

    $pager = $multipage !== '' ? '<div class="st-pager dt-pager">' . $multipage . '</div>' : '';

    $comments_html = $pager . commenttable($allrows, '', '', false, true, true) . $pager;
} else {
    $comments_html = '
        <div class="st-card st-empty dt-comments-empty" id="dt-comments-empty">
            <span class="st-ico st-soft-muted"><i class="fa-solid fa-comments"></i></span>
            ' . htmlspecialchars(strip_tags($lang->details['nocommentsyet'])) . '
        </div>
        <div id="ajax_comment_preview"></div>';   // сюда quick_comment.js вставляет новый комментарий (при комментариях его выводит commenttable)
}

// ── Comment error (redirect back from comment.php) ──────────────────────────
$cerror = '';
if (isset($_GET['cerror'])) {
    $cerror = match ((int)$_GET['cerror']) {
        1       => $lang->global['notorrentid'],
        2       => $lang->global['dontleavefieldsblank'],
        3       => sprintf($lang->global['flooderror'], $usergroups['floodlimit'], $lang->comment['floodcomment'], '-'),
        default => $lang->global['error'],
    };
}

// ── Peers ───────────────────────────────────────────────────────────────────
$seeders     = [];
$downloaders = [];

$subres = $db->sql_query_prepared("
    SELECT p.seeder, p.finishedat, p.downloadoffset, p.uploadoffset, p.ip, p.port, p.uploaded, p.downloaded, p.to_go,
           p.started AS st, p.connectable, p.agent, p.peer_id, p.last_action AS la, p.userid,
           u.id, u.avatar, u.avatardimensions, u.invisible, u.enabled, u.username, u.usergroup, u.warned, u.donor
    FROM peers p
    LEFT JOIN users u ON (p.userid = u.id)
    WHERE p.torrent = ?
", [$id]);

while ($subres && ($subrow = $db->fetch_array($subres))) {
    if ($subrow['seeder'] === 'yes') {
        $seeders[] = $subrow;
    } else {
        $downloaders[] = $subrow;
    }
}

// Сиды — по отданному (больше выше), личи — по оставшемуся (меньше выше), ?usort — как сиды
usort($seeders, static fn($a, $b) => (float)$b['uploaded'] <=> (float)$a['uploaded']);
usort($downloaders, isset($_GET['usort'])
    ? static fn($a, $b) => (float)$b['uploaded'] <=> (float)$a['uploaded']
    : static fn($a, $b) => (float)$a['to_go'] <=> (float)$b['to_go']);

$peerstable  = dltable($lang->details['seeders2'], $seeders, $Torrent, true);
$peerstable .= dltable($lang->details['leechers2'], $downloaders, $Torrent, false);

// ── Quick comment ───────────────────────────────────────────────────────────
$can_comment = $Torrent['allowcomments'] === 'yes' || $is_mod;

if ($can_comment) {
    require_once INC_PATH . '/editor.php';
    // SweetAlert2 уже подключён страницей — редактор не грузит его второй раз
    if (!defined('AGS_SWAL_LOADED')) {
        define('AGS_SWAL_LOADED', true);
    }
    $editor   = insert_bbcode_editor($smilies, $BASEURL, 'message');
    $posthash = bin2hex(random_bytes(16));
    $uploader = render_attachment_uploader($posthash, $my_id);
    $ajax_on  = $use_xmlhttprequest == '1';

    $submit_btns = $ajax_on
        ? '<i id="loading-layer" class="fa-solid fa-circle-notch fa-spin dt-qc__spin" aria-label="' . htmlspecialchars($lang->details['aria_loading'], ENT_QUOTES) . '"></i>
           <button type="button" class="btn btn-primary st-pill" id="quickcomment" name="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');"><i class="fa-solid fa-paper-plane me-1"></i>' . $lang->global['buttonsubmit'] . '</button>'
        : '<button type="submit" name="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-paper-plane me-1"></i>' . $lang->global['buttonsubmit'] . '</button>';

    $quick_comment = '
    <div class="st-card dt-qc" id="dt-quick-comment">
        <div class="st-card__head">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-pen-to-square"></i></span>
            ' . htmlspecialchars($lang->details['sec_quick_comment']) . '
        </div>
        <div class="st-card__body">
            ' . ($cerror !== '' ? '<div class="alert alert-danger py-2">' . $cerror . '</div>' : '') . '
            ' . ($ajax_on ? '<script src="' . $BASEURL . '/scripts/quick_comment.js?ver=72"></script>' : '') . '
            ' . $editor['toolbar'] . '
            <form name="comment" id="comment" method="post" action="comment.php?action=add&amp;tid=' . $id . '" novalidate>
                <input type="hidden" name="ctype" value="quickcomment">
                <input type="hidden" name="page" value="' . (int)$page . '">
                <input type="hidden" name="posthash" value="' . htmlspecialchars($posthash) . '">
                <input type="hidden" name="my_post_key" value="' . $post_key . '">
                <div id="fileIdsContainer"></div>
                <label for="message" class="form-label small text-body-secondary">
                    ' . htmlspecialchars($lang->details['lbl_your_comment']) . ' · ' . htmlspecialchars(ags_fmt($lang->details['hint_max_chars'], 500)) . '
                </label>
                <textarea class="form-control dt-qc__text" id="message" name="message" rows="6"
                          placeholder="' . htmlspecialchars($lang->details['ph_comment'], ENT_QUOTES) . '" maxlength="500"
                          aria-describedby="charCount" required></textarea>
                <div id="charCount" class="form-text text-end">0 / 500</div>
                <div id="message_preview" class="form-control mt-2 d-none"></div>
                ' . $uploader . '
                <div class="dt-qc__bar">
                    <a href="comment.php?action=add&amp;tid=' . $id . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-sliders me-1"></i>' . $lang->global['advancedbutton'] . '</a>
                    ' . $submit_btns . '
                </div>
            </form>
            ' . $editor['modal'] . '
        </div>
    </div>';
} else {
    $quick_comment = '
    <div class="st-card st-empty">
        <span class="st-ico st-soft-danger"><i class="fa-solid fa-lock"></i></span>
        ' . htmlspecialchars(strip_tags((string)($lang->comment['closed'] ?? 'Comments are closed.'))) . '
    </div>';
}

// ── Uploader ────────────────────────────────────────────────────────────────
$uploader_hidden = $Torrent['anonymous'] === 'yes' && !$is_owner && !$is_mod;

if ($uploader_hidden || empty($Torrent['username'])) {
    $uploader_html = '<span class="text-body-secondary"><i class="fa-solid fa-user-secret me-1"></i>' . $lang->global['anonymous'] . '</span>';
} else {
    $uploader_html = dt_user_link($owner_id, (string)$Torrent['username'], (int)$Torrent['usergroup'])
                   . get_user_icons($torrent2)
                   . ($Torrent['anonymous'] === 'yes' ? ' <span class="text-body-secondary small">(' . $lang->global['anonymous'] . ')</span>' : '');
}

// ── IMDb ────────────────────────────────────────────────────────────────────
$imdb_html = '';
if (!empty($Torrent['t_link'])) {
    $imdb_html = '
    <div class="st-card dt-imdb">
        <div class="st-card__head">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-film"></i></span>
            ' . htmlspecialchars(strip_tags($lang->details['t_link'])) . '
            ' . ($is_mod ? '<button type="button" class="btn btn-sm btn-outline-secondary st-pill ms-auto" id="imdbupdatebutton" data-imdb-refresh="' . $id . '">
                <i class="fa-solid fa-rotate me-1"></i>' . htmlspecialchars(ucfirst((string)$lang->global['refresh'])) . '</button>' : '') . '
        </div>
        <div class="st-card__body" id="imdbdetails">' . $Torrent['t_link'] . '</div>
    </div>';
}

// ── Manage (owner / staff) ──────────────────────────────────────────────────
$manage_html = '';

if ($is_owner || $is_mod) {
    $del_warn = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$lang->details['mdl_deltorrent_warning'])));

    $manage_btns = '
        <a href="' . $BASEURL . '/upload.php?id=' . $id . '" class="dt-tool">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-pen-to-square"></i></span>' . htmlspecialchars($lang->details['menu_full_edit']) . '</a>';

    if ($is_mod) {
        $closed     = $Torrent['allowcomments'] !== 'yes';
        $nuked      = ($Torrent['isnuked'] ?? 'no') === 'yes';
        $toggle_lbl = $closed ? $lang->details['open'] : $lang->details['close'];

        $manage_btns .= '
        <form method="post" action="' . $BASEURL . '/comment.php?tid=' . $id . '&amp;action=' . ($closed ? 'open' : 'close') . '" class="m-0">
            <input type="hidden" name="my_post_key" value="' . $post_key . '">
            <button type="submit" class="dt-tool">
                <span class="st-ico ' . ($closed ? 'st-soft-danger' : 'st-soft-success') . '"><i class="fa-solid ' . ($closed ? 'fa-comment-slash' : 'fa-comment') . '"></i></span>'
                . htmlspecialchars($toggle_lbl) . '</button>
        </form>
        <a href="' . $BASEURL . '/admin/index.php?act=hit_and_run&amp;torrentid=' . $id . '" class="dt-tool">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-person-running"></i></span>' . htmlspecialchars($lang->details['tip_hitrun']) . '</a>
        <a href="' . $BASEURL . '/admin/index.php?act=torrent_info&amp;id=' . $id . '" class="dt-tool">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-circle-info"></i></span>' . htmlspecialchars($lang->details['torrentinfo']) . '</a>
        <a href="' . $BASEURL . '/admin/nuketorrent.php?id=' . $id . '" class="dt-tool">
            <span class="st-ico ' . ($nuked ? 'st-soft-danger' : 'st-soft-warning') . '"><i class="fa-solid fa-radiation"></i></span>'
            . htmlspecialchars($nuked ? ($lang->details['tip_nuked'] ?? 'Nuked: change reason / unnuke') : ($lang->details['tip_nuke'] ?? 'Nuke torrent')) . '</a>';
    }

    $manage_btns .= '
        <button type="button" class="dt-tool dt-tool--danger dt-delete-torrent"
                data-torrent-id="' . $id . '"
                data-name="' . htmlspecialchars_uni((string)$Torrent['name']) . '"
                data-title="' . htmlspecialchars($lang->details['mdl_deltorrent_question'], ENT_QUOTES) . '"
                data-warn="' . htmlspecialchars($del_warn, ENT_QUOTES) . '"
                data-yes="' . htmlspecialchars($lang->details['tip_delete_torrent'], ENT_QUOTES) . '"
                data-no="' . htmlspecialchars($lang->details['btn_cancel'], ENT_QUOTES) . '">
            <span class="st-ico st-soft-danger"><i class="fa-solid fa-trash-can"></i></span>' . htmlspecialchars($lang->details['btn_delete']) . '</button>';

    $manage_html = '
    <div class="st-card dt-manage">
        <div class="st-card__head">
            <span class="st-ico st-soft-muted"><i class="fa-solid fa-gear"></i></span>
            ' . htmlspecialchars($lang->details['lbl_manage']) . '
        </div>
        <div class="dt-tools">' . $manage_btns . '</div>
    </div>';
}

// ── Torrent file tree ───────────────────────────────────────────────────────
$TorrentObj   = null;
$tree         = null;
$torrent_path = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';

if (is_file($torrent_path)) {
    try {
        $TorrentObj = TorrentFile::load($torrent_path);

        $tree = [];
        foreach ($TorrentObj->v1()->getFiles() as $file) {
            $path    = str_replace('\\', '/', implode('/', array_values((array)$file->path)));
            $parts   = explode('/', $path);
            $last    = count($parts) - 1;
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === $last) {
                    $current[$part] = (int)$file->length;
                } else {
                    if (!isset($current[$part]) || !is_array($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
            unset($current);
        }
    } catch (\Throwable $e) {
        $tree = null;
    }
}

// ── Description / Screens / Posters / NFO ───────────────────────────────────
$descr = $parser->parse_message((string)$Torrent['descr'], $parser_options);

$screenshots = [];
$res = $db->sql_query_prepared("SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY sort_order ASC, id ASC", [$id]);
while ($res && ($row = $db->fetch_array($res))) {
    $screenshots[] = $row;
}

$t_screenshot = htmlspecialchars($lang->details['lbl_screenshot'], ENT_QUOTES);
$screens_html = '';
foreach ($screenshots as $shot) {
    $shot_url = $BASEURL . '/torrents/screens/' . rawurlencode((string)$shot['filename']);

    $screens_html .= '
        <a href="#" class="dt-shot" data-bs-toggle="modal" data-bs-target="#universalImageModal"
           data-img-src="' . htmlspecialchars($shot_url, ENT_QUOTES) . '" data-title="' . $t_screenshot . '">
            <img src="' . htmlspecialchars($shot_url, ENT_QUOTES) . '" alt="' . $t_screenshot . '" loading="lazy">
        </a>';
}

$posters_html = '';
foreach (['t_image', 't_image2'] as $img_key) {
    if (empty($Torrent[$img_key])) {
        continue;
    }
    $img = htmlspecialchars_uni((string)$Torrent[$img_key]);
    $posters_html .= '
        <a href="#" class="dt-poster" data-bs-toggle="modal" data-bs-target="#universalImageModal"
           data-img-src="' . $img . '" data-title="' . htmlspecialchars_uni((string)$Torrent['name']) . '">
            <img src="' . $img . '" alt="' . htmlspecialchars_uni((string)$Torrent['name']) . '">
        </a>';
}

$has_nfo = !empty($Torrent['nfo']);

// ── Claim box (port of NexusPHP "claim block") ──────────────────────────────
require_once INC_PATH . '/functions_claim.php';
$claim_html = '';

if (CLAIM_ENABLED && claim_torrent_old_enough($Torrent)) {
    $myClaim     = claim_get($my_id, $id);
    $claimCount  = claim_count_torrent($id);
    $claimersUrl = $BASEURL . '/claim.php?torrent_id=' . $id;

    $claim_form = static function (string $action, string $btnClass, string $icon, string $label, string $confirm) use ($id, $post_key, $lang): string {
        return '<form method="post" action="claim.php" class="m-0" data-dt-confirm="' . htmlspecialchars($confirm, ENT_QUOTES) . '"'
             . ' data-dt-yes="' . htmlspecialchars($lang->details['claim_yes'], ENT_QUOTES) . '"'
             . ' data-dt-no="' . htmlspecialchars($lang->details['claim_cancel'], ENT_QUOTES) . '">'
             . '<input type="hidden" name="my_post_key" value="' . $post_key . '">'
             . '<input type="hidden" name="action" value="' . $action . '">'
             . '<input type="hidden" name="torrent_id" value="' . $id . '">'
             . '<input type="hidden" name="returnto" value="details.php?id=' . $id . '">'
             . '<button class="btn ' . $btnClass . ' btn-sm st-pill"><i class="fa-solid ' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</button></form>';
    };

    if ($myClaim) {
        $claim_text = ags_fmt($lang->details['claim_mine'], date('d.m.Y', (int)$myClaim['added']), CLAIM_SEED_HOURS, CLAIM_UPLOAD_TIMES);
        $claim_btn  = '<a href="' . $BASEURL . '/claim.php" class="btn btn-outline-primary btn-sm st-pill"><i class="fa-solid fa-list-check me-1"></i>' . htmlspecialchars($lang->details['claim_btn_my']) . '</a>'
                    . $claim_form('remove', 'btn-outline-danger', 'fa-xmark', $lang->details['claim_btn_give_up'],
                          ags_fmt($lang->details['claim_confirm_give_up'], number_format(CLAIM_GIVE_UP_DEDUCT)));
    } else {
        try {
            claim_check_can($my_id, $id);
            $claim_text = ags_fmt($lang->details['claim_offer'], CLAIM_SEED_HOURS);
            $claim_btn  = $claim_form('add', 'btn-success', 'fa-hand-holding-heart', $lang->details['claim_btn_claim'],
                              ags_fmt($lang->details['claim_confirm_add'], CLAIM_SEED_HOURS, number_format(CLAIM_REMOVE_DEDUCT)));
        } catch (ClaimException $ex) {
            $claim_text = '<span class="text-body-secondary">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES) . '</span>';
            $claim_btn  = '';
        }
    }

    $claim_html = '
        <div class="dt-claim">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-heart-pulse"></i></span>
            <div class="dt-claim__text">' . $claim_text . '
                <div class="small mt-1"><a href="' . $claimersUrl . '">' . htmlspecialchars(ags_fmt($lang->details['claim_by'], $claimCount, CLAIM_MAX_PER_TORRENT)) . '</a></div>
            </div>
            <div class="dt-claim__btns">' . $claim_btn . '</div>
        </div>';
}

// ── Hit & Run warning ───────────────────────────────────────────────────────
$hitrun_html = '';
if ($hitrun == 'yes' && (float)$CURUSER['downloaded'] > 0) {
    $ratio = (float)$CURUSER['uploaded'] / (float)$CURUSER['downloaded'];

    if ($Torrent['free'] !== 'yes' && $usergroups['isvipgroup'] !== 'yes' && $ratio <= ((float)$hitrun_ratio + 0.4)
        && !$is_owner && !$is_mod) {
        $hitrun_html = '
        <div class="st-card dt-alert dt-alert--danger" role="alert">
            <span class="st-ico st-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div class="min-w-0">' . ags_fmt($lang->details['downloadwarning'], number_format($ratio, 2), number_format($ratio * 100, 0) . '%', (string)$hitrun_ratio) . '</div>
        </div>';
    }
}

// ── Header ──────────────────────────────────────────────────────────────────
$health      = getHealthPercentage($Torrent['seeders'], $Torrent['leechers']);
$health_c    = getHealthColor($Torrent['seeders']);
$numfiles    = (int)$Torrent['numfiles'];
$files_lbl   = htmlspecialchars(ags_fmt(ags_plural($numfiles, $lang->details['lbl_files']), ts_nf($numfiles)));
$name_html   = htmlspecialchars_uni((string)$Torrent['name']);
$is_public   = $TorrentObj !== null && !$TorrentObj->isPrivate();
$no_seeders  = (int)$Torrent['seeders'] === 0;

require_once INC_PATH . '/functions_bookmark.php';
$bookmark_html = '<span id="bookmark' . $id . '" class="dt-bookmark">' . get_torrent_bookmark_state($my_id, $id) . '</span>';

$head_badges = '';
if ($Torrent['banned'] === 'yes') {
    $head_badges .= '<span class="st-badge st-soft-danger"><i class="fa-solid fa-ban"></i>' . htmlspecialchars($lang->details['banned']) . '</span>';
}
if ($already_snatched) {
    $head_badges .= '<span class="st-badge st-soft-success" title="' . htmlspecialchars($lang->details['tip_snatched_by_you'], ENT_QUOTES) . '">'
                  . '<i class="fa-solid fa-circle-check"></i>' . htmlspecialchars($lang->details['badge_already_downloaded']) . '</span>';
}

$head_html = '
    <div class="st-card st-head dt-head">
        <span class="st-head__icon st-soft-primary"><i class="' . ($cat_icon !== '' ? $cat_icon : 'fa-solid fa-box-archive') . '"></i></span>
        <div class="min-w-0 flex-grow-1">
            <div class="dt-head__top">
                <h1 class="st-title">' . $name_html . '</h1>
                <span class="dt-head__tags">' . GetTorrentTags($Torrent) . $head_badges . '</span>
            </div>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-folder-open"></i>' . $cat_html . '</span>
                <span><i class="fa-solid fa-user"></i>' . $uploader_html . '</span>
                <span title="' . htmlspecialchars(my_datee($dateformat . ' ' . $timeformat, $Torrent['added']), ENT_QUOTES) . '"><i class="fa-solid fa-clock"></i>' . my_datee('relative', $Torrent['added']) . '</span>
                <span title="' . htmlspecialchars($lang->details['tip_torrent_id'], ENT_QUOTES) . '"><i class="fa-solid fa-hashtag"></i>' . $id . '</span>
            </div>
        </div>
        <div class="dt-head__side">' . $bookmark_html . '</div>
    </div>';

// ── KPI tiles ───────────────────────────────────────────────────────────────
$kpi = static function (string $icon, string $soft, string $value, string $label, string $href = ''): string {
    $tag  = $href !== '' ? 'a href="' . $href . '"' : 'div';
    $etag = $href !== '' ? 'a' : 'div';
    return '<' . $tag . ' class="st-kpi st-kpi--muted">'
         . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i>'
         . '<span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="min-w-0"><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . htmlspecialchars($label) . '</div></div>'
         . '</' . $etag . '>';
};

$kpis_html = '
    <div class="dt-kpis">'
    . $kpi('fa-arrow-up', 'st-soft-success', ts_nf($Torrent['seeders']), $lang->details['stat_seeders'], '#peers')
    . $kpi('fa-arrow-down', 'st-soft-danger', ts_nf($Torrent['leechers']), $lang->details['stat_leechers'], '#peers')
    . $kpi('fa-circle-check', 'st-soft-info', ts_nf($Torrent['times_completed']), $lang->details['snatched'], $BASEURL . '/viewsnatches.php?id=' . $id)
    . $kpi('fa-comments', 'st-soft-warning', ts_nf($threadcount), $lang->details['comments'], '#dt-comments') . '
    </div>';

// ── Download card ───────────────────────────────────────────────────────────
$reseed_form = '';
if ($no_seeders) {
    $reseed_form = '
        <form method="post" action="' . $BASEURL . '/takereseed.php" class="m-0"
              data-dt-confirm="' . htmlspecialchars($lang->details['confirm_reseed'], ENT_QUOTES) . '"
              data-dt-yes="' . htmlspecialchars($lang->details['btn_request_reseed'], ENT_QUOTES) . '"
              data-dt-no="' . htmlspecialchars($lang->details['btn_cancel'], ENT_QUOTES) . '">
            <input type="hidden" name="my_post_key" value="' . $post_key . '">
            <input type="hidden" name="reseedid" value="' . $id . '">
            <button type="submit" class="btn btn-outline-warning st-pill"><i class="fa-solid fa-bullhorn me-1"></i>' . htmlspecialchars($lang->details['btn_request_reseed']) . '</button>
        </form>';
}

$download_html = '
    <div class="st-card dt-dl">
        <div class="st-card__body">
            <div class="dt-dl__row">
                <span class="st-ico st-soft-primary dt-dl__ico"><i class="fa-solid fa-cloud-arrow-down"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold">' . htmlspecialchars($lang->details['dltorrent']) . '</div>
                    <div class="small text-body-secondary">
                        <i class="fa-solid fa-hard-drive me-1"></i>' . mksize($Torrent['size']) . '
                        <span class="mx-1">·</span><i class="fa-solid fa-file me-1"></i>' . $files_lbl . '
                    </div>
                </div>
                <div class="dt-dl__btns">
                    <a href="' . get_download_link($id) . '" class="btn btn-primary st-pill">
                        <i class="fa-solid fa-download me-1"></i>' . htmlspecialchars($lang->details['download']) . '</a>
                    ' . ($is_public ? '<button type="button" class="btn btn-outline-primary st-pill magnet-btn" data-magnet-id="' . $id . '">
                        <i class="fa-solid fa-magnet me-1"></i>' . htmlspecialchars($lang->details['menu_magnet']) . '</button>' : '') . '
                    ' . $reseed_form . '
                    <button type="button" class="btn btn-outline-secondary st-pill report-btn"
                            data-bs-toggle="modal" data-bs-target="#reportModal"
                            data-report-type="torrent" data-report-id="' . $id . '" data-report-userid="' . $owner_id . '"
                            data-report-name="' . $name_html . '"
                            title="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '"
                            aria-label="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '">
                        <i class="fa-solid fa-flag"></i></button>
                </div>
            </div>

            <div class="dt-health">
                <div class="dt-health__lbl">
                    <span><i class="fa-solid fa-heart-pulse me-1"></i>' . htmlspecialchars($lang->details['lbl_torrent_health']) . '</span>
                    <span class="fw-semibold">' . $health . '%</span>
                </div>
                <div class="progress dt-health__bar" role="progressbar" aria-valuenow="' . $health . '" aria-valuemin="0" aria-valuemax="100"
                     aria-label="' . htmlspecialchars($lang->details['lbl_torrent_health'], ENT_QUOTES) . '">
                    <div class="progress-bar bg-' . $health_c . '" style="width: ' . $health . '%"></div>
                </div>
            </div>
            ' . $claim_html . '
        </div>
    </div>';

// ── Tabs ────────────────────────────────────────────────────────────────────
$tab = static function (string $key, string $icon, string $label, bool $active = false): string {
    return '<li class="nav-item" role="presentation">'
         . '<button class="nav-link' . ($active ? ' active' : '') . '" id="' . $key . '-tab" data-bs-toggle="tab" data-bs-target="#' . $key . '"'
         . ' type="button" role="tab" aria-controls="' . $key . '" aria-selected="' . ($active ? 'true' : 'false') . '">'
         . '<i class="fa-solid ' . $icon . '"></i><span>' . $label . '</span></button></li>';
};

$tabs  = $tab('info', 'fa-circle-info', htmlspecialchars($lang->details['tab_info']), true);
$tabs .= $tab('files', 'fa-folder-tree', htmlspecialchars(ags_fmt($lang->details['tab_files'], ts_nf($numfiles))));
$tabs .= $tab('peers', 'fa-users', htmlspecialchars(ags_fmt($lang->details['tab_peers'], ts_nf((int)$Torrent['seeders'] + (int)$Torrent['leechers']))));
if ($screenshots) {
    $tabs .= $tab('screen', 'fa-images', htmlspecialchars($lang->details['tab_screens']) . ' <span class="dt-tabs__cnt">' . count($screenshots) . '</span>');
}
if ($has_nfo) {
    $tabs .= $tab('nfo', 'fa-file-lines', htmlspecialchars($lang->details['tab_nfo']));
}

$row = static fn(string $icon, string $label, string $value): string =>
    '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid ' . $icon . '"></i>' . htmlspecialchars($label) . '</span>'
    . '<span class="st-row__val">' . $value . '</span></div>';

$info_pane = '
    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
        <div class="dt-info">
            <div class="st-rows">'
            . $row('fa-calendar', $lang->details['lbl_uploaded'], my_datee($dateformat, $Torrent['added']) . ' <span class="text-body-secondary fw-normal">' . my_datee($timeformat, $Torrent['added']) . '</span>')
            . $row('fa-folder-open', $lang->details['lbl_category'], $cat_html)
            . $row('fa-hard-drive', $lang->details['size'], mksize($Torrent['size']) . ' <span class="text-body-secondary fw-normal">(' . $files_lbl . ')</span>')
            . $row('fa-user', $lang->details['uppedby'], $uploader_html) . '
            </div>
            <div class="st-rows">'
            . $row('fa-circle-check', $lang->details['snatched'], '<a href="' . $BASEURL . '/viewsnatches.php?id=' . $id . '">' . ts_nf($Torrent['times_completed']) . '</a>')
            . $row('fa-eye', $lang->details['views'], ts_nf($Torrent['hits']))
            . $row('fa-comments', $lang->details['comments'], ts_nf($threadcount))
            . $row('fa-fingerprint', $lang->details['infohash'], '<code class="dt-hash">' . htmlspecialchars((string)($Torrent['info_hash'] ?? $lang->details['na'])) . '</code>') . '
            </div>
        </div>
        ' . ($tags_html !== '' ? '
        <div class="dt-tags">
            <span class="dt-tags__lbl"><i class="fa-solid fa-tags me-1"></i>' . htmlspecialchars($lang->details['sec_tags']) . '</span>
            ' . $tags_html . '
        </div>' : '') . '
    </div>';

$files_pane = '
    <div class="tab-pane fade" id="files" role="tabpanel" aria-labelledby="files-tab">
        ' . ($tree !== null ? '
        <div class="st-toolbar dt-pane-bar">
            <span class="fw-semibold"><i class="fa-solid fa-folder-tree me-2"></i>' . htmlspecialchars($lang->details['sec_file_structure']) . '</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-dt-tree="expand"><i class="fa-solid fa-square-plus me-1"></i>' . htmlspecialchars($lang->details['btn_expand_all']) . '</button>
                <button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-dt-tree="collapse"><i class="fa-solid fa-square-minus me-1"></i>' . htmlspecialchars($lang->details['btn_collapse_all']) . '</button>
            </div>
        </div>
        <div class="file-tree dt-tree">' . dt_render_tree($tree) . '</div>'
        : '<div class="st-empty"><span class="st-ico st-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>' . htmlspecialchars($lang->details['err_torrent_file_missing']) . '</div>') . '
    </div>';

$peers_pane = '
    <div class="tab-pane fade" id="peers" role="tabpanel" aria-labelledby="peers-tab">' . $peerstable . '</div>';

$screen_pane = $screenshots ? '
    <div class="tab-pane fade" id="screen" role="tabpanel" aria-labelledby="screen-tab">
        <div class="dt-shots">' . $screens_html . '</div>
    </div>' : '';

$nfo_pane = $has_nfo ? '
    <div class="tab-pane fade" id="nfo" role="tabpanel" aria-labelledby="nfo-tab">
        <div class="st-toolbar dt-pane-bar">
            <span class="fw-semibold min-w-0 text-truncate"><i class="fa-solid fa-file-lines me-2"></i>' . $name_html . '.nfo</span>
            <button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-dt-copy="#nfoText"><i class="fa-solid fa-copy me-1"></i>' . htmlspecialchars($lang->details['btn_copy']) . '</button>
        </div>
        <pre id="nfoText" class="dt-nfo">' . htmlspecialchars((string)$Torrent['nfo']) . '</pre>
    </div>' : '';

$tabs_html = '
    <div class="st-card dt-tabs-card">
        <div class="dt-tabs-wrap">
            <ul class="nav dt-tabs" id="torrentTabs" role="tablist">' . $tabs . '</ul>
        </div>
        <div class="tab-content st-card__body" id="torrentTabsContent">'
        . $info_pane . $files_pane . $peers_pane . $screen_pane . $nfo_pane . '
        </div>
    </div>';

// ── Description, similar, comments ──────────────────────────────────────────
$descr_html = '
    <div class="st-card dt-descr">
        <div class="st-card__head">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-align-left"></i></span>
            ' . htmlspecialchars($lang->details['description']) . '
        </div>
        <div class="st-card__body dt-descr__body">' . $descr . '</div>
    </div>';

$similar_block = $similar_count > 0 ? '
    <div class="st-card dt-similar">
        <div class="st-card__head">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-layer-group"></i></span>
            ' . htmlspecialchars(strip_tags($lang->details['smililartorrents'])) . '
            <span class="ms-auto st-badge st-soft-muted">' . $similar_count . '</span>
        </div>
        <div class="st-card__body"><div class="dt-sims">' . $similar_html . '</div></div>
    </div>' : '';

$comments_block = '
    <section class="dt-comments" id="dt-comments">
        <div class="st-card st-card__head dt-comments__head">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-comments"></i></span>
            ' . htmlspecialchars($lang->details['comments']) . '
            <span class="ms-auto st-badge st-soft-muted">' . ts_nf($threadcount) . '</span>
        </div>
        ' . $comments_html . '
        ' . $quick_comment . '
    </section>';

$aside_html = ($posters_html !== '' ? '<div class="st-card dt-posters">' . $posters_html . '</div>' : '')
            . $rating_html
            . $manage_html;

// ── Output ──────────────────────────────────────────────────────────────────
stdhead(ags_fmt($lang->details['detailsfor'], (string)$Torrent['name']));

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=61">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/details.css?ver=' . DT_CSS_VER . '">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';

// Strings for page scripts: js_* keys from the lang, without the prefix
$ags_js_lang = [];
foreach ($lang->details as $ags_key => $ags_val) {
    if (str_starts_with((string)$ags_key, 'js_')) {
        $ags_js_lang[substr((string)$ags_key, 3)] = $ags_val;
    }
}
echo '<script>const AGS_LANG = ' . json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/toast.js?ver=2"></script>';
echo '<script src="' . $BASEURL . '/scripts/bookmark.js?ver=21"></script>';
echo '<script src="' . $BASEURL . '/scripts/details_modal.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/rating.js?ver=121"></script>';
echo '<script src="' . $BASEURL . '/scripts/details.js?ver=' . DT_JS_VER . '"></script>';
echo '<script src="' . $BASEURL . '/scripts/report.js?ver=2"></script>';

echo '
<div class="container-md st-page dt-page" id="torrent_details" data-post-key="' . $post_key . '">
    ' . $hitrun_html . '
    ' . $cnf_html . '
    ' . $head_html . '
    ' . $kpis_html . '

    <div class="dt-layout">
        <div class="dt-main min-w-0">
            ' . $download_html . '
            ' . $tabs_html . '
        </div>
        ' . ($aside_html !== '' ? '<aside class="dt-aside min-w-0">' . $aside_html . '</aside>' : '') . '
    </div>

    ' . $descr_html . '
    ' . $imdb_html . '
    ' . $similar_block . '
    ' . $comments_block . '
</div>';

// Modals (magnet, image viewer, report, comment edit/delete) — scoped wrapper for their styles
echo '<div class="dt-modals">';
require_once INC_PATH . '/modals.php';
echo $magnetModal ?? '';
echo '</div>';

stdfoot();
