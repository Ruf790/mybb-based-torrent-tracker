<?php

declare(strict_types=1);

define("SCRIPTNAME", "details.php");

require_once('global.php');

$lang->load('details');

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
            $m10 === 1 && $m100 !== 11                         => $f[0],
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
    "allow_html"     => 0,
    "allow_mycode"   => 1,
    "allow_smilies"  => 1,
    "allow_imgcode"  => 1,
    "allow_videocode"=> 1,
    "filter_badwords"=> 1,
    "nofollow_on"    => 1,
];

gzip();
maxsysop();

define('D_VERSION', '3.5.5');
define("IN_ARCHIVE", true);

// ── pid → id trick ──────────────────────────────────────────────────────────
if (!empty($mybb->input['pid']) && !$mybb->input['id']) {
    if (isset($style) && $style['pid'] == $mybb->input['pid'] && $style['id']) {
        $mybb->input['id'] = $style['id'];
        unset($style['id']);
    } else {
        $query = $db->sql_query_prepared("SELECT torrent FROM comments WHERE id = ? LIMIT 1", [(int)$mybb->input['pid']]);
        $post  = $query ? $db->fetch_array($query) : null;

        if (empty($post)) {
            stderr($lang->global['invalid_comm']);
        }

        $mybb->input['id'] = $post['torrent'];
    }
}

$is_mod = is_mod($usergroups);

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

function get_torrent(int $tid, bool $recache = false): array|false
{
    global $db;
    static $thread_cache = [];

    if (isset($thread_cache[$tid]) && !$recache) {
        return $thread_cache[$tid];
    }

    $query  = $db->sql_query_prepared("SELECT * FROM torrents WHERE id = ?", [$tid]);
    $thread = $query ? $db->fetch_array($query) : null;

    if ($thread) {
        $thread_cache[$tid] = $thread;
        return $thread;
    }

    $thread_cache[$tid] = false;
    return false;
}

function getHealthColor(mixed $seeders, mixed $leechers): string
{
    $seeders  = (int)$seeders;
    $leechers = (int)$leechers;

    return match (true) {
        $seeders === 0 => 'danger',
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

function getLeecherPercentage(mixed $seeders, mixed $leechers): int
{
    $seeders  = (int)$seeders;
    $leechers = (int)$leechers;
    $total    = $seeders + $leechers;

    return $total === 0 ? 0 : (int)round(($leechers / $total) * 100);
}

function getFileIcon($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'video'   => ['mp4','mkv','avi','mov','wmv','flv','webm','mpeg','mpg','3gp','m4v','vob','ts','m2ts','ogv','rm','rmvb'],
        'audio'   => ['mp3','flac','wav','ogg','m4a','aac'],
        'image'   => ['jpg','jpeg','png','gif','bmp','webp','svg','tiff'],
        'archive' => ['zip','rar','7z','tar','gz','bz2'],
        'doc'     => ['nfo','txt','md','log','pdf','doc','docx','xls','xlsx','ppt','pptx'],
        'code'    => ['php','html','css','js','json','xml','py','java','c','cpp','sh','bat'],
        'exec'    => ['exe','iso','apk','bin','dll','app','deb','rpm'],
    ];

    $colors = [
        'video'   => 'bg-danger',
        'audio'   => 'bg-primary',
        'image'   => 'bg-success',
        'archive' => 'bg-warning',
        'doc'     => 'bg-secondary',
        'code'    => 'bg-info',
        'exec'    => 'bg-dark',
        'default' => 'bg-secondary',
    ];

    $iconClasses = [
        'video'   => 'fa-solid fa-file-video',
        'audio'   => 'fa-solid fa-file-audio',
        'image'   => 'fa-solid fa-file-image',
        'archive' => 'fa-solid fa-file-zipper',
        'doc'     => 'fa-solid fa-file-lines',
        'code'    => 'fa-solid fa-file-code',
        'exec'    => 'fa-solid fa-microchip',
        'default' => 'fa-solid fa-file',
    ];

    $type = 'default';
    foreach ($icons as $key => $exts) {
        if (in_array($ext, $exts, true)) {
            $type = $key;
            break;
        }
    }

    $colorClass = $colors[$type]     ?? $colors['default'];
    $iconClass  = $iconClasses[$type] ?? $iconClasses['default'];

    return '<span class="badge rounded-pill d-inline-flex align-items-center px-2 py-1 ' . $colorClass . ' text-white" '
         . 'aria-label="' . htmlspecialchars(strtoupper($ext)) . '" title="' . htmlspecialchars(strtoupper($ext)) . '">'
         . '<i class="' . $iconClass . ' me-1" style="font-size: 1em;"></i>'
         . '<small style="line-height:1;">' . htmlspecialchars(strtoupper($ext)) . '</small>'
         . '</span>';
}

function renderAccordion($tree, $parentId = 'root', $level = 0)
{
    static $counter = 0;
    $html = '<div class="accordion" id="accordion-' . $parentId . '">';

    foreach ($tree as $name => $content) {
        if (is_array($content)) {
            $accordionId     = 'item-' . (++$counter);
            $showClass       = '';
            $buttonCollapsed = 'collapsed';
            $ariaExpanded    = 'false';

            $html .= '
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-' . $accordionId . '">
                    <button class="accordion-button ' . $buttonCollapsed . '" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapse-' . $accordionId . '"
                            aria-expanded="' . $ariaExpanded . '" aria-controls="collapse-' . $accordionId . '">
                        <i class="bi bi-folder-fill text-warning me-2"></i>' . htmlspecialchars((string)$name) . '
                    </button>
                </h2>
                <div id="collapse-' . $accordionId . '" class="accordion-collapse collapse ' . $showClass . '"
                     aria-labelledby="heading-' . $accordionId . '" data-bs-parent="#accordion-' . $parentId . '">
                    <div class="accordion-body">'
                        . renderAccordion($content, $accordionId, $level + 1) .
                    '</div>
                </div>
            </div>';
        } else {
            $icon = getFileIcon($name);
            $html .= '<div class="ms-3 py-1 d-flex align-items-center gap-2">'
                   . $icon
                   . '<span class="text-truncate">' . htmlspecialchars($name) . '</span>'
                   . '<span class="badge bg-secondary ms-auto">' . mksize($content) . '</span>'
                   . '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}

// ── Load torrent ────────────────────────────────────────────────────────────
$Torrent = get_torrent((int)$mybb->input['id']);

if (!$Torrent) {
    stderr($lang->global['notorrentid'], $SITENAME . ' - ' . $lang->details['title_notfound'], 404, 'torrent');
}

$id = $Torrent['id'];

$query = $db->sql_query_prepared("
    SELECT t.name, t.banned, t.owner, n.nfo, c.name AS categoryname,
           c.pid, c.type, c.id AS categoryid, c.icon,
           u.id, u.username, u.usergroup, u.enabled, u.donor, u.warned, u.leechwarn,
           u.canupload, u.candownload, u.cancomment,
           (SELECT ROUND(AVG(r.rating), 1) FROM torrent_ratings r WHERE r.torrent_id = t.id) AS rating_avg,
           (SELECT COUNT(r.id) FROM torrent_ratings r WHERE r.torrent_id = t.id) AS rating_count
    FROM torrents t
    LEFT JOIN torrents_nfo n ON (t.id = n.torrent_id)
    LEFT JOIN categories c ON (t.category = c.id)
    LEFT JOIN users u ON (t.owner = u.id)
    WHERE t.id = ?
", [$id]);

if (!$query || $db->num_rows($query) == 0 || !($torrent2 = $db->fetch_array($query))) {
    stderr($lang->global['notorrentid']);
} elseif ($torrent2["banned"] == "yes" && !$is_mod) {
    stderr($lang->global['torrentbanned']);
}

$lang->load('browse');
$lang->load('upload');

require_once(INC_PATH . '/functions_mkprettytime.php');

$SimilarTorrents = '';
$Torrent_name    = $Torrent['name'];

$query = "
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
    ORDER BY score DESC
    LIMIT 12
";

$params = [$Torrent_name, $Torrent_name, $id];
$query_result = $db->sql_query_prepared($query, $params);

if ($query_result && $db->num_rows($query_result) > 0) {
    $FoundSMTQ   = '';
    $found_count = 0;

    while ($SMTQ = $db->fetch_array($query_result)) {
        if ($SMTQ['score'] > 1) {
            $SEOLink  = get_torrent_link($SMTQ['id']);
            $SEOLinkC = get_category_link($SMTQ['category']);

            $poster = !empty($SMTQ['t_image'])
                ? htmlspecialchars_uni($SMTQ['t_image'])
                : $BASEURL . '/include/templates/default/images/no_image.png';

            $uploaderHtml = (!$is_mod && $SMTQ['owner'] != $CURUSER['id'] && $SMTQ['anonymous'] == 'yes')
                ? '<span class="text-muted small"><i class="bi bi-eye-slash me-1"></i>' . $lang->global['anonymous'] . '</span>'
                : '<a href="' . get_profile_link($SMTQ['owner']) . '" class="text-decoration-none small">'
                  . format_name($SMTQ['username'], $SMTQ['usergroup']) . '</a>'
                  . ($SMTQ['anonymous'] == 'yes' ? ' <span class="text-muted small">(' . $lang->global['anonymous'] . ')</span>' : '');

            $FoundSMTQ .= '
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100 border-0 shadow-sm similar-torrent-card">
                    <a href="' . $SEOLink . '" class="text-decoration-none">
                        <div class="position-relative overflow-hidden" style="height:160px;">
                            <img src="' . $poster . '"
                                 class="card-img-top w-100 h-100"
                                 style="object-fit:cover; transition: transform 0.3s ease;"
                                 alt="' . htmlspecialchars_uni($SMTQ['name']) . '"
                                 onerror="this.src=\'' . $BASEURL . '/include/templates/default/images/no_image.png\'">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center similar-overlay">
                                <i class="bi bi-play-circle-fill text-white" style="font-size:2.5rem; opacity:0.9;"></i>
                            </div>
                            <a href="' . $SEOLinkC . '" class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-dark bg-opacity-75">
                                    <i class="' . $SMTQ['catimage'] . '"></i>
                                </span>
                            </a>
                            <div class="position-absolute bottom-0 start-0 m-2 d-flex gap-1">
                                <span class="badge bg-success bg-opacity-90">
                                    <i class="bi bi-arrow-up-circle me-1"></i>' . ts_nf($SMTQ['seeders']) . '
                                </span>
                                <span class="badge bg-danger bg-opacity-90">
                                    <i class="bi bi-arrow-down-circle me-1"></i>' . ts_nf($SMTQ['leechers']) . '
                                </span>
                            </div>
                        </div>
                    </a>
                    <div class="card-body p-2">
                        <a href="' . $SEOLink . '" class="text-decoration-none text-dark">
                            <h6 class="card-title mb-1 text-truncate small fw-semibold"
                                title="' . htmlspecialchars_uni($SMTQ['name']) . '">
                                ' . htmlspecialchars_uni($SMTQ['name']) . '
                            </h6>
                        </a>
                        <div class="d-flex justify-content-between align-items-center">
                            ' . $uploaderHtml . '
                            <span class="text-muted small">' . mksize($SMTQ['size']) . '</span>
                        </div>
                    </div>
                </div>
            </div>';

            $found_count++;
        }
    }

    if ($FoundSMTQ) {
        $SimilarTorrents = '
        <div class="container mt-4">
            <div class="card border-0 shadow-sm similar-wrapper">
                <div class="card-header similar-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-collection-fill me-2"></i>' . $lang->details['smililartorrents'] . '
                    </h5>
                    <span class="badge bg-primary rounded-pill">' . $found_count . '</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        ' . $FoundSMTQ . '
                    </div>
                </div>
            </div>
        </div>';
    }
}

// ── Tags ─────────────────────────────────────────────────────────────────────
if (empty($Torrent["tags"])) {
    $keywords = '';
} else {
    $tags     = explode(",", $Torrent['tags']);
    $keywords = "";
    foreach ($tags as $tag) {
        $tagSafe   = htmlspecialchars_uni(trim($tag));
        $keywords .= '<a href="' . $BASEURL . '/browse.php?do=search&keywords=' . urlencode($tag) . '&search_type=t_tags" '
                   . 'title="' . $tagSafe . '" class="badge tag-badge"><i class="bi bi-hash me-1"></i>' . $tagSafe . '</a> ';
    }
    $keywords = rtrim($keywords);
}

// ── Category chain ──────────────────────────────────────────────────────────
$seolink2 = '';
if ($torrent2['type'] == 's') {
    require(TSDIR . '/cache/categories.php');
    foreach ($_categoriesC as $catarray) {
        if ($catarray['id'] == $torrent2['pid']) {
            $parentcategory = $catarray['name'];
            $parentcatid    = $catarray['id'];
            break;
        }
    }
    if (!empty($parentcategory) && !empty($parentcatid)) {
        $seolink  = get_category_link($parentcatid);
        $seolink2 = get_category_link($torrent2['categoryid']);
        $torrent2["categoryname"] = '<a href="' . $seolink . '" target="_self" alt="' . htmlspecialchars_uni($parentcategory) . '" title="' . htmlspecialchars_uni($parentcategory) . '">' . htmlspecialchars_uni($parentcategory) . '</a>'
                                  . ' <i class="bi bi-chevron-right small text-muted"></i> '
                                  . '<a href="' . $seolink2 . '" target="_self" alt="' . htmlspecialchars_uni($torrent2['categoryname']) . '" title="' . htmlspecialchars_uni($torrent2['categoryname']) . '">' . htmlspecialchars_uni($torrent2['categoryname']) . '</a>';
    }
} else {
    $seolink2 = get_category_link($torrent2['categoryid']);

    $torrent2["categoryname"] = '
    <a href="' . $seolink2 . '" target="_self" alt="' . htmlspecialchars_uni($torrent2['categoryname']) . '" title="' . htmlspecialchars_uni($torrent2['categoryname']) . '">
        <i class="' . $torrent2['icon'] . ' me-1" title="' . htmlspecialchars_uni($torrent2['categoryname']) . '"></i>' . htmlspecialchars_uni($torrent2['categoryname']) . '
    </a>';
}

// ── stdhead + assets ────────────────────────────────────────────────────────
$HEAD = ags_fmt($lang->details['detailsfor'], (string)$Torrent['name']);
stdhead($HEAD);

require_once INC_PATH . '/functions_bookmark.php';

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/details.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/claim.css?ver=121">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/animate.min.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">';
// Strings for page scripts: js_* keys from the lang, without the prefix
$ags_js_lang = [];
foreach ($lang->details as $ags_key => $ags_val) {
    if (str_starts_with((string)$ags_key, 'js_')) {
        $ags_js_lang[substr((string)$ags_key, 3)] = $ags_val;
    }
}
echo '<script>const AGS_LANG = ' . json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/toast.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/bookmark.js?ver=2"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/details_modal.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/details.js?ver=2"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/report.js?ver=2"></script>';

require_once INC_PATH . '/modals.php';

if ($CURUSER['id'] === $torrent2['owner'] || $is_mod) {
    require_once 'details_edit.php';
}

// ── Hit & Run warning ───────────────────────────────────────────────────────
$gigs = $CURUSER['downloaded'] / (1024 * 1024 * 1024);

if ($hitrun == 'yes') {
    $ratio      = ($CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
    $percentage = $ratio * 100;

    if ($Torrent['free'] != 'yes' && $usergroups['isvipgroup'] != 'yes' && $ratio <= ($hitrun_ratio + 0.4)
        && $Torrent['owner'] != $CURUSER['id'] && !$is_mod && $CURUSER['downloaded'] <> 0) {

        $warning_message = '<div class="container mt-3">
           <div class="hitrun-alert mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ' . ags_fmt($lang->details['downloadwarning'], number_format($ratio, 2), mksize($percentage), (string)$hitrun_ratio) . '
           </div>
        </div>';
    }
}

if (isset($warning_message)) {
    echo $warning_message;
}

$sratio = $Torrent['leechers'] > 0 ? $Torrent['seeders'] / $Torrent['leechers'] : 1;
$lratio = $Torrent['seeders'] > 0 ? $Torrent['leechers'] / $Torrent['seeders'] : 1;

// ── Rating data ─────────────────────────────────────────────────────────────
$showcommenttable = '';
$threadcount      = 0;

$user_rating  = 0;
$rating_data  = [
    'avg'         => (float)($torrent2['rating_avg'] ?? 0),
    'count'       => (int)($torrent2['rating_count'] ?? 0),
    'user_rating' => $user_rating,
];

if ($CURUSER['id']) {
    $q2 = $db->sql_query_prepared("SELECT rating FROM torrent_ratings WHERE torrent_id = ? AND user_id = ? LIMIT 1", [$id, $CURUSER['id']]);
    if ($q2 && ($ur = $db->fetch_array($q2))) {
        $rating_data['user_rating'] = (int)$ur['rating'];
    }
}

// ── "Уже скачивали" ──────────────────────────────────────────────────────────
$already_snatched = false;
if ($CURUSER['id']) {
    $q3 = $db->sql_query_prepared(
        "SELECT id FROM snatched WHERE userid = ? AND torrentid = ? AND finished = 'yes' LIMIT 1",
        [$CURUSER['id'], $id]
    );
    $already_snatched = $q3 && $db->num_rows($q3) > 0;
}

$avg_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    $filled = $rating_data['avg'] >= $i;
    $half   = !$filled && $rating_data['avg'] >= $i - 0.5;
    if ($filled)      $avg_stars_html .= '<i class="bi bi-star-fill rating-star-filled"></i>';
    elseif ($half)    $avg_stars_html .= '<i class="bi bi-star-half rating-star-filled"></i>';
    else              $avg_stars_html .= '<i class="bi bi-star rating-star-empty"></i>';
}

$user_stars_html = '';
if ($CURUSER['id']) {
    for ($i = 1; $i <= 10; $i++) {
        $active          = $rating_data['user_rating'] >= $i ? 'active' : '';
        $user_stars_html .= '<i class="bi bi-star-fill user-star ' . $active . '" data-value="' . $i . '" onclick="rateTorrent(' . $i . ')"></i>';
    }
    $user_section = '
        <div class="d-flex align-items-center gap-2">
            <div class="user-stars d-flex gap-1" id="user-stars">' . $user_stars_html . '</div>
            <span class="small text-muted" id="rating-hint">'
                . ($rating_data['user_rating'] ? $rating_data['user_rating'] . '/10' : htmlspecialchars($lang->details['hint_rate'])) .
            '</span>
        </div>';
} else {
    $user_section = '<a href="login.php" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-box-arrow-in-right me-1"></i>' . htmlspecialchars($lang->details['btn_login_to_rate']) . '</a>';
}

$ags_votes_label = ags_fmt(ags_plural($rating_data['count'], $lang->details['lbl_votes']), number_format($rating_data['count']));

$rating_html = '
<div class="rating-panel mt-4">
    <div class="rating-panel-header">
        <i class="bi bi-star-fill text-warning"></i>
        <span>' . htmlspecialchars($lang->details['sec_user_rating']) . '</span>
        <span class="badge bg-warning text-dark ms-auto">' . htmlspecialchars($ags_votes_label) . '</span>
    </div>
    <div class="row g-4 align-items-center mt-2">
        <div class="col-auto">
            <div class="rating-score-wrap">
                <div class="rating-score">' . ($rating_data['count'] > 0 ? number_format($rating_data['avg'], 1) : '—') . '</div>
                <div class="rating-score-max">/ 10</div>
                <div class="rating-votes text-muted small mt-1">
                    <i class="bi bi-people-fill me-1"></i>
                    ' . htmlspecialchars($ags_votes_label) . '
                </div>
            </div>
        </div>
        <div class="col">
            <div class="rating-stars-wrap">
                <div class="rating-stars mb-2" id="rating-display">' . $avg_stars_html . '</div>
                ' . $user_section . '
            </div>
        </div>
    </div>
</div>
<script src="' . $BASEURL . '/scripts/rating.js?ver=2"></script>
<script>ratingInit(' . $rating_data['user_rating'] . ', ' . $id . ', "' . $BASEURL . '");</script>';

// ── Comments ────────────────────────────────────────────────────────────────
$query       = $db->sql_query_prepared("SELECT COUNT(id) AS commentss FROM comments c WHERE torrent = ?", [$id]);
$threadcount = $query ? $db->fetch_field($query, "commentss") : 0;

if (!$threadcount) {
    $showcommenttable .= '
    <div class="container mt-3">
       <div class="card border-0 mb-4 comments-empty-card">
          <div class="card-header rounded-bottom text-19 fw-bold">
              <div style="display: block;" id="ajax_comment_preview">
                  <i class="bi bi-chat-dots me-2"></i>' . $lang->details['nocommentsyet'] . '
              </div>
              <div style="display: block;" id="ajax_comment_preview2"></div>
          </div>
       </div>
    </div>';
} else {
    $multipage = '';
    $page      = 1;
    $perpage   = $ts_perpage;

    if (isset($mybb->input['page']) && $mybb->input['page'] != "last") {
        $page = (int)$mybb->input['page'];
    }

    if (!empty($mybb->input['pid'])) {
        $post = get_comment($mybb->input['pid']);
        if ($post) {
            $query  = "SELECT COUNT(c.dateline) AS count FROM comments c WHERE c.torrent = ? AND c.dateline <= ?";
            $params = [$id, $post['dateline']];
            $res    = $db->sql_query_prepared($query, $params);

            if ($res) {
                $result = $db->fetch_field($res, "count");
                if (($result % $perpage) == 0) {
                    $page = $result / $perpage;
                } else {
                    $page = intval($result / $perpage) + 1;
                }
            }
        }
    }

    $query           = $db->sql_query_prepared("SELECT COUNT(*) AS replies FROM comments c WHERE c.torrent = ?", [$id]);
    $thread['replies'] = $query ? (int)$db->fetch_field($query, 'replies') - 1 : -1;

    $postcount = $thread['replies'] + 1;
    $pages     = ceil($postcount / $perpage);

    if (isset($mybb->input['page']) && $mybb->input['page'] == "last") {
        $page = $pages;
    }

    $page = (int)$page;
    if ($page > $pages || $page <= 0) {
        $page = 1;
    }

    if ($page) {
        $start = ($page - 1) * $perpage;
    } else {
        $start = 0;
        $page  = 1;
    }

    $upper       = $start + $perpage;
    $postcounter = "";

    if (!$postcounter) {
        if ($page > 1) {
            if (!$ts_perpage || (int)$ts_perpage < 1) {
                $ts_perpage = 20;
            }
            $postcounter = $ts_perpage * ($page - 1);
        } else {
            $postcounter = 0;
        }
    }

    $multipage = multipage((int)$postcount, (int)$perpage, (int)$page, str_replace("{id}", (string)$id, TORRENT_URL_PAGED));

    $allrows = [];

    $query = "
        SELECT
            c.id, c.torrent AS torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, c.editedat,
            uu.username AS editedbyuname, gg.namestyle AS editbynamestyle,
            u.added AS registered, u.enabled, u.lastactive, u.lastvisit, u.invisible, u.warned, u.leechwarn, u.username, u.usertitle,
            u.usergroup, u.displaygroup, u.postnum, u.threadnum, u.added, u.comms, u.donor, u.uploaded, u.downloaded,
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
    ";

    $params = [(int)$id, (int)$start, (int)$perpage];
    $subres = $db->sql_query_prepared($query, $params);

    if ($subres) {
        while ($subrow = $db->fetch_array($subres)) {
            $allrows[] = $subrow;
        }
        $db->free_result($subres);
    }

    $all_attachments = [];
    if (!empty($allrows)) {
        $comment_ids  = array_map(fn($r) => (int)$r['id'], $allrows);
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

    $showcommenttable .= '<div class="container mt-3">' . $multipage . '</div>'
                       . commenttable($allrows, '', '', false, true, true)
                       . '<div class="container mt-3">' . $multipage . '</div>';
}

$rowspan = 9;
$reseed  = '';

if ($Torrent['seeders'] == 0) {
    $reseed = '
    <tr>
        <td style="padding-left: 5px;" class="trow2" valign="top" width="147">' . $lang->details['askreseed'] . '</td>
        <td valign="top" style="padding-left: 5px;">' . ags_fmt($lang->details['askreseed2'], (int)$id) . '</td>
    </tr>';
    $rowspan++;
}

if (isset($_GET['cerror'])) {
    switch ($_GET['cerror']) {
        case 1:  $cerror = $lang->global['notorrentid']; break;
        case 2:  $cerror = $lang->global['dontleavefieldsblank']; break;
        case 3:  $cerror = sprintf($lang->global['flooderror'], $usergroups['floodlimit'], $lang->comment['floodcomment'], "-"); break;
        default: $cerror = $lang->global['error']; break;
    }
}

// ── Peers ───────────────────────────────────────────────────────────────────
$seeders     = [];
$downloaders = [];

$query = "
    SELECT p.seeder, p.finishedat, p.downloadoffset, p.uploadoffset, p.ip, p.port, p.uploaded, p.downloaded, p.to_go,
           p.started AS st, p.connectable, p.agent, p.peer_id, p.last_action AS la, p.userid,
           u.id, u.avatar, u.avatardimensions, u.invisible, u.enabled, u.username, u.usergroup, u.displaygroup, u.warned, u.donor
    FROM peers p
    LEFT JOIN users u ON (p.userid=u.id)
    WHERE p.torrent = ?
";

$subres = $db->sql_query_prepared($query, [$id]);

if ($subres && $db->num_rows($subres) > 0) {
    while ($subrow = $db->fetch_array($subres)) {
        if ($subrow['seeder'] === 'yes') {
            $seeders[] = $subrow;
        } else {
            $downloaders[] = $subrow;
        }
    }
}

function leech_sort($a, $b)
{
    if (isset($_GET["usort"])) return seed_sort($a, $b);
    $x = $a["to_go"];
    $y = $b["to_go"];
    if ($x == $y) return 0;
    if ($x < $y)  return -1;
    return 1;
}

function seed_sort($a, $b)
{
    $x = $a["uploaded"];
    $y = $b["uploaded"];
    if ($x == $y) return 0;
    if ($x < $y)  return 1;
    return -1;
}

usort($seeders, "seed_sort");
usort($downloaders, "leech_sort");

$peerstable  = dltable($lang->details['seeders2'], $seeders, $Torrent, true);
$peerstable .= dltable($lang->details['leechers2'], $downloaders, $Torrent, false);

// ── Comment editor ──────────────────────────────────────────────────────────
require_once INC_PATH . '/editor.php';
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message');

$posthash = bin2hex(random_bytes(16));
$uploader = render_attachment_uploader($posthash, (int)$CURUSER['id']);

$showcommenttable .= '
<br />
<div class="container mt-4">
    <h2 class="mb-3"><i class="bi bi-pencil-square me-2 text-primary"></i>' . htmlspecialchars($lang->details['sec_quick_comment']) . '</h2>
    ' . (!empty($cerror) ? '<div class="error">' . $cerror . '</div>' : '') . '
    ' . ($use_xmlhttprequest == '1' ? '<script src="' . $BASEURL . '/scripts/quick_comment.js?ver=2"></script>' : '') . '
    ' . $editor['toolbar'] . '
    <form name="comment" id="comment" method="post" action="comment.php?action=add&tid=' . $id . '" novalidate>
        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="page" value="' . intval($page ?? ($_GET['page'] ?? 1)) . '">
        <input type="hidden" name="posthash" value="' . htmlspecialchars($posthash) . '">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        <div id="fileIdsContainer"></div>
        <div class="mb-3">
            <label for="message" class="form-label"><i class="bi bi-chat-left-text me-1"></i>' . htmlspecialchars($lang->details['lbl_your_comment']) . ' <small class="text-muted">' . htmlspecialchars(ags_fmt($lang->details['hint_max_chars'], 500)) . '</small></label>
            <textarea class="form-control" id="message" name="message" rows="6"
                      placeholder="' . htmlspecialchars($lang->details['ph_comment'], ENT_QUOTES) . '" maxlength="500"
                      aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>
        <div id="message_preview" class="form-control mt-3 d-none"></div>
        ' . $uploader . '
        ' . ($use_xmlhttprequest == '1' ? '
        <div class="d-flex align-items-center justify-content-center mb-3">
            <i id="loading-layer" class="fa-solid fa-circle-notch fa-spin" aria-label="' . htmlspecialchars($lang->details['aria_loading'], ENT_QUOTES) . '" style="display:none; color: #0b59e0; width:24px; height:24px; margin-right: 10px;"></i>
            <button type="button" class="btn btn-primary me-2" id="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');"><i class="bi bi-send me-1"></i>' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid=' . $id . '" class="btn btn-secondary"><i class="bi bi-gear me-1"></i>' . $lang->global['advancedbutton'] . '</a>
        </div>' : '
        <div class="d-flex gap-2 justify-content-center mb-3">
            <button type="submit" name="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid=' . $id . '" class="btn btn-secondary"><i class="bi bi-gear me-1"></i>' . $lang->global['advancedbutton'] . '</a>
        </div>') . '
    </form>
    ' . $editor['modal'] . '
</div>';

// ── Uploader name ───────────────────────────────────────────────────────────
if ($Torrent['anonymous'] == 'yes' AND $Torrent['owner'] != $CURUSER['id'] AND !$is_mod) {
    $username = '<i class="bi bi-eye-slash display-6 opacity-50 mb-2 d-block"></i>';
} else {
    $username = '<a href="' . get_profile_link($Torrent['owner']) . '" class="text-decoration-none">'
              . format_name($torrent2['username'], $torrent2['usergroup']) . '</a>'
              . get_user_icons($torrent2);
}

// ── IMDb ────────────────────────────────────────────────────────────────────
$ShowTLINK = '';

if (!empty($Torrent['t_link'])) {
    $html    = $Torrent['t_link'];
    $refresh = !empty($is_mod) ? ($lang->global['refresh'] ?? '') : '';

   $ShowTLINK = '
	
	<div class="container mt-3">
  <div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">
	<span style="float: right;">
		<div id="imdbupdatebutton" name="imdbupdatebutton">
		<a href="#" onclick="TS_IMDB('.$id.'); return false;"><b><u><i>'.$refresh.'</i></u></b></a></div></span>'.$lang->details['t_link'].'

     </div>
	 
    <div class="card-body"><div id="imdbdetails" name="imdbdetails">'.$Torrent['t_link'].'</div></div> 
   
  </div>
</div>
	
	<br />
	
	';
}

// ── Manage dropdown ─────────────────────────────────────────────────────────
$show_manage = '';

if ($CURUSER['id'] === $torrent2['owner'] OR $is_mod) {
    $show_manage .= '
    <div class="dropdown d-inline-block">
        <a href="#" class="btn btn-light btn-icon rounded-circle p-2 shadow-sm manage-btn"
           role="button" id="manageCompactDropdown" data-bs-toggle="dropdown"
           aria-expanded="false" data-bs-toggle="tooltip" title="' . htmlspecialchars($lang->details['lbl_manage'], ENT_QUOTES) . '">
            <i class="bi bi-three-dots-vertical text-primary"></i>
        </a>
        <ul class="dropdown-menu shadow border-0 rounded-2 p-1" aria-labelledby="manageCompactDropdown">
            <li>
                <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1"
                   href="#" data-bs-toggle="modal" data-bs-target="#add_data_Modal">
                    <i class="bi bi-pencil-square text-success me-2"></i>
                    <span>' . htmlspecialchars($lang->details['menu_quick_edit']) . '</span>
                </a>
            </li>
            <li>
                <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1"
                   href="upload.php?id=' . $id . '">
                    <i class="bi bi-file-earmark-text text-info me-2"></i>
                    <span>' . htmlspecialchars($lang->details['menu_full_edit']) . '</span>
                </a>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
                <a class="dropdown-item d-flex align-items-center py-2 px-3 rounded-1 text-danger"
                   href="#" data-bs-toggle="modal" data-bs-target="#deleteTorrentModal"
                   data-torrent-id="' . $id . '"
                   data-torrent-name="' . htmlspecialchars_uni($Torrent['name']) . '">
                    <i class="bi bi-trash3 me-2"></i>
                    <span>' . htmlspecialchars($lang->details['btn_delete']) . '</span>
                </a>
            </li>
        </ul>
    </div>';
}

if ($is_mod) {
    $ags_t_hitrun     = htmlspecialchars($lang->details['tip_hitrun'], ENT_QUOTES);
    $ags_t_open       = htmlspecialchars($lang->details['open'], ENT_QUOTES);
    $ags_t_close      = htmlspecialchars($lang->details['close'], ENT_QUOTES);
    $ags_t_status     = htmlspecialchars(addslashes($Torrent['allowcomments'] == 'no' ? $lang->details['open'] : $lang->details['close']), ENT_QUOTES);
    $ags_t_info       = htmlspecialchars($lang->details['torrentinfo'], ENT_QUOTES);
    $ags_t_delete     = htmlspecialchars($lang->details['tip_delete_torrent'], ENT_QUOTES);

    $show_manage .= '
    <a href="' . $BASEURL . '/admin/index.php?act=hit_and_run&torrentid=' . $id . '" class="manage-icon-btn">
        <i class="fa-solid fa-person-running" alt="' . $ags_t_hitrun . '" title="' . $ags_t_hitrun . '"></i></a>

    <form method="post" action="' . $BASEURL . '/comment.php?tid=' . $id . '&action=' . ($Torrent['allowcomments'] != 'yes' ? 'open' : 'close') . '" style="display:inline;">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        <button type="submit" class="manage-icon-btn"
                onmouseout="window.status=\'\'; return true;"
                onMouseOver="window.status=\'' . $ags_t_status . '\'; return true;">'
        . ($Torrent['allowcomments'] != 'yes'
            ? '<i class="fa-solid fa-comment-slash" style="color: #e91b0c;" alt="' . $ags_t_open . '" title="' . $ags_t_open . '"></i>'
            : '<i class="fa-solid fa-comment-slash" style="color: #08e74b;" alt="' . $ags_t_close . '" title="' . $ags_t_close . '"></i>')
        . '</button>
    </form>

    <a href="' . $BASEURL . '/admin/index.php?act=torrent_info&amp;id=' . $id . '" class="manage-icon-btn">
        <i class="fa-sharp fa-solid fa-info" style="color: #94b4eb;" alt="' . $ags_t_info . '" title="' . $ags_t_info . '"></i></a>

    <a href="' . $BASEURL . '/admin/index.php?act=fastdelete&amp;id=' . $id . '" class="manage-icon-btn">
        <i class="fa-solid fa-trash-can" style="color: #eb0f0f;" alt="' . $ags_t_delete . '" title="' . $ags_t_delete . '"></i></a>';
}

// ── Torrent file tree ───────────────────────────────────────────────────────
$TorrentObj = null;
$tree       = null;

if (is_file(TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent")) {
    $TorrentPath = TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent";

    try {
        $TorrentObj = TorrentFile::load($TorrentPath);
        $files      = $TorrentObj->v1()->getFiles();

        $tree = [];
        foreach ($files as $file) {
            $path    = str_replace('\\', '/', implode('/', array_values((array)$file->path)));
            $size    = $file->length;
            $parts   = explode('/', $path);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current[$part] = $size;
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }
    } catch (\Throwable $e) {
        $TorrentObj = null;
        $tree       = null;
    }
}

// ── Description / Screens / Images ──────────────────────────────────────────
$descr = $parser->parse_message($Torrent['descr'], $parser_options);

$screenshots = [];
$res = $db->sql_query_prepared("SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY sort_order ASC, id ASC", [$id]);

if ($res) {
    while ($row = $db->fetch_array($res)) {
        $screenshots[] = $row;
    }
}

$ags_t_screenshot = htmlspecialchars($lang->details['lbl_screenshot'], ENT_QUOTES);
$screensHtml      = '<div class="row g-3">';
foreach ($screenshots as $shot) {
    $filename      = htmlspecialchars($shot['filename']);
    $screenshotUrl = '/torrents/screens/' . $filename;

    $screensHtml .= '
    <div class="col-6 col-md-4 col-lg-3">
        <a href="#" class="screenshot-wrapper d-block position-relative overflow-hidden rounded-4"
           data-bs-toggle="modal" data-bs-target="#universalImageModal"
           data-img-src="' . $screenshotUrl . '" data-title="' . $ags_t_screenshot . '">
            <img src="' . $screenshotUrl . '" class="img-fluid rounded-4 transition-scale" alt="' . $ags_t_screenshot . '">
        </a>
    </div>';
}
$screensHtml .= '</div>';

$modal_images = '';
$images       = [];

if (!empty($Torrent['t_image']))  $images[] = $Torrent['t_image'];
if (!empty($Torrent['t_image2'])) $images[] = $Torrent['t_image2'];

foreach ($images as $img) {
    $modal_images .= '
    <a href="#" data-bs-toggle="modal" data-bs-target="#universalImageModal"
       data-img-src="' . htmlspecialchars_uni($img) . '"
       data-title="' . htmlspecialchars_uni($Torrent['name']) . '">
        <img src="' . htmlspecialchars_uni($img) . '" class="rounded" width="400"
             alt="' . htmlspecialchars_uni($Torrent['name']) . '">
    </a>';
}

$screenTab     = '';
$screenContent = '';

if (!empty($screenshots)) {
    $screenTab = '
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="screen-tab" data-bs-toggle="tab"
                data-bs-target="#screen" type="button" role="tab"
                aria-controls="screen" aria-selected="false">
            <i class="bi bi-images me-2"></i>' . htmlspecialchars($lang->details['tab_screens']) . '
        </button>
    </li>';

    $screenContent = '
    <div class="tab-pane fade" id="screen" role="tabpanel" aria-labelledby="screen-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
            ' . $screensHtml . '
        </div>
    </div>';
}

$nfoTab     = '';
$nfoContent = '';

if (!empty($torrent2['nfo'])) {
    $nfoTab = '
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="nfo-tab" data-bs-toggle="tab"
                data-bs-target="#nfo" type="button" role="tab">
            <i class="bi bi-file-earmark-text-fill me-2"></i>' . htmlspecialchars($lang->details['tab_nfo']) . '
        </button>
    </li>';

    $nfoContent = '
    <div class="tab-pane fade" id="nfo" role="tabpanel" aria-labelledby="nfo-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>' . htmlspecialchars($lang->details['sec_nfo_file']) . '</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyNfo()">
                <i class="bi bi-clipboard me-1"></i>' . htmlspecialchars($lang->details['btn_copy']) . '
            </button>
        </div>
        <div class="card border-0" style="border: 1px solid #dee2e6 !important;">
            <div class="card-header d-flex justify-content-between align-items-center py-2"
                 style="background:#f1f3f5; border-bottom: 1px solid #dee2e6;">
                <span class="text-dark small">
                    <i class="fas fa-file-alt me-1 text-primary"></i>
                    ' . htmlspecialchars($Torrent['name']) . '.nfo
                </span>
            </div>
            <div class="card-body p-0">
                <pre id="nfoText"
                     style="background:#f8f9fa; color:#212529; font-family:\'Courier New\',monospace;
                            font-size:0.75rem; padding:1rem; margin:0; max-height:500px;
                            overflow-y:auto; white-space:pre; border-radius:0 0 8px 8px;">'
                . htmlspecialchars($torrent2['nfo']) .
                '</pre>
            </div>
        </div>
    </div>';
}

// ── Magnet button ───────────────────────────────────────────────────────────
$magnetButton = ($TorrentObj !== null && !$TorrentObj->isPrivate())
    ? '<li><a class="dropdown-item magnet-btn" href="#" data-magnet-id="' . $id . '"><i class="bi bi-magnet me-2"></i>' . htmlspecialchars($lang->details['menu_magnet']) . '</a></li>'
    : '';

// ── Claim box (port of NexusPHP "claim block" in details.php) ───────────────
require_once INC_PATH . '/functions_claim.php';
$claimBox = '';
$ags_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (CLAIM_ENABLED && claim_torrent_old_enough($Torrent)) {
    $myClaim     = claim_get((int)$CURUSER['id'], (int)$id);
    $claimCount  = claim_count_torrent((int)$id);
    $claimersUrl = $BASEURL . '/claim.php?torrent_id=' . (int)$id;
    $claimForm   = static function (string $action, string $btnClass, string $icon, string $label, string $confirm) use ($id, $mybb): string {
        return '<form method="post" action="claim.php" data-claim-confirm="' . htmlspecialchars($confirm, ENT_QUOTES) . '">'
             . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">'
             . '<input type="hidden" name="action" value="' . $action . '">'
             . '<input type="hidden" name="torrent_id" value="' . (int)$id . '">'
             . '<input type="hidden" name="returnto" value="details.php?id=' . (int)$id . '">'
             . '<button class="btn ' . $btnClass . ' btn-sm"><i class="bi ' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</button></form>';
    };

    if ($myClaim) {
        $text = ags_fmt($lang->details['claim_mine'], date('d.m.Y', (int)$myClaim['added']), CLAIM_SEED_HOURS, CLAIM_UPLOAD_TIMES);
        $btn  = '<a href="' . $BASEURL . '/claim.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check me-1"></i>' . htmlspecialchars($lang->details['claim_btn_my']) . '</a>'
              . $claimForm('remove', 'btn-outline-danger', 'bi-x-lg', $lang->details['claim_btn_give_up'],
                    ags_fmt($lang->details['claim_confirm_give_up'], number_format(CLAIM_GIVE_UP_DEDUCT)));
    } else {
        try {
            claim_check_can((int)$CURUSER['id'], (int)$id);
            $text = ags_fmt($lang->details['claim_offer'], CLAIM_SEED_HOURS);
            $btn  = $claimForm('add', 'btn-success', 'bi-hand-thumbs-up', $lang->details['claim_btn_claim'],
                    ags_fmt($lang->details['claim_confirm_add'], CLAIM_SEED_HOURS, number_format(CLAIM_REMOVE_DEDUCT)));
        } catch (ClaimException $ex) {
            $text = '<span class="text-muted">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES) . '</span>';
            $btn  = '';
        }
    }

    $claimBox = '
            <div class="claim-box mt-4">
                <i class="bi bi-heart-pulse fs-4 text-success"></i>
                <div class="claim-box__text">' . $text . '
                    <div class="small text-muted mt-1"><a href="' . $claimersUrl . '">' . htmlspecialchars(ags_fmt($lang->details['claim_by'], (int)$claimCount, CLAIM_MAX_PER_TORRENT)) . '</a></div>
                </div>
                <div class="d-flex gap-2 flex-wrap">' . $btn . '</div>
            </div>
            <script>
            document.addEventListener("submit", function (e) {
                var f = e.target;
                if (!f.matches("form[data-claim-confirm]") || f.dataset.ok === "1") return;
                e.preventDefault();
                var go = function () { f.dataset.ok = "1"; f.querySelector("button").disabled = true; f.submit(); };
                if (window.Swal) {
                    window.Swal.fire({ icon: "question", text: f.dataset.claimConfirm, showCancelButton: true, confirmButtonText: ' . json_encode($lang->details['claim_yes'], $ags_json_flags) . ',
                        cancelButtonText: ' . json_encode($lang->details['claim_cancel'], $ags_json_flags) . ', reverseButtons: true }).then(function (r) { if (r.isConfirmed) go(); });
                } else if (window.confirm(f.dataset.claimConfirm)) { go(); }
            });
            </script>';
}

$act = '<span id="bookmark' . $Torrent['id'] . '">'
     . get_torrent_bookmark_state($CURUSER['id'], (int)$Torrent['id'])
     . '</span>';

// ── Main layout ─────────────────────────────────────────────────────────────
$ags_health    = getHealthPercentage($Torrent['seeders'], $Torrent['leechers']);
$ags_numfiles  = (int)$Torrent['numfiles'];
$ags_files_lbl = htmlspecialchars(ags_fmt(ags_plural($ags_numfiles, $lang->details['lbl_files']), ts_nf($ags_numfiles)));

$details = '
<div id="torrent_details" class="container mt-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4 animate__animated animate__fadeIn">
        <ol class="breadcrumb torrent-breadcrumb p-3 rounded-3 shadow-sm">
            <li class="breadcrumb-item">
                <a href="/" class="text-decoration-none">
                    <i class="bi bi-house-door-fill me-1"></i> ' . htmlspecialchars($lang->details['nav_home'], ENT_QUOTES) . '
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="browse.php" class="text-decoration-none">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> ' . htmlspecialchars($lang->details['nav_browse'], ENT_QUOTES) . '
                </a>
            </li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 400px;"
                aria-current="page" title="' . htmlspecialchars_uni($Torrent['name']) . '">
                <i class="bi bi-file-earmark-zip-fill me-1"></i>
                ' . htmlspecialchars_uni(mb_strlen($Torrent['name']) > 60
                    ? mb_substr($Torrent['name'], 0, 60) . '…'
                    : $Torrent['name']) . '
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="torrent-header mb-5">
        <div class="d-flex align-items-start gap-4 flex-wrap">
            <div class="torrent-hero-icon">
                <i class="' . ($torrent2['icon'] ?? 'bi bi-collection-fill') . '"></i>
            </div>

            <div class="flex-grow-1">
                <h1 class="h3 mb-3 fw-bold text-dark animate__animated animate__fadeInDown">
                    <span class="status-badges me-2">' . GetTorrentTags($Torrent) . '</span>
                    ' . htmlspecialchars_uni($Torrent['name']) . '
                </h1>

                <div class="d-flex flex-wrap gap-2 align-items-center torrent-meta-badges">
                    <span class="badge meta-badge meta-badge-id" title="' . htmlspecialchars($lang->details['tip_torrent_id'], ENT_QUOTES) . '">
                        <i class="bi bi-hash me-1"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_id'], (int)$id)) . '
                    </span>
                    <span class="badge meta-badge meta-badge-size" title="' . htmlspecialchars($lang->details['size'], ENT_QUOTES) . '">
                        <i class="bi bi-hdd-fill me-1"></i>' . mksize($Torrent['size']) . '
                    </span>
                    <span class="badge meta-badge meta-badge-health bg-' . getHealthColor($Torrent['seeders'], $Torrent['leechers']) . '" title="' . htmlspecialchars($lang->details['tip_health'], ENT_QUOTES) . '">
                        <i class="bi bi-activity me-1"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_health'], $ags_health)) . '
                    </span>
                    <span class="badge meta-badge meta-badge-files" title="' . htmlspecialchars($lang->details['tip_numfiles'], ENT_QUOTES) . '">
                        <i class="bi bi-file-earmark-fill me-1"></i>' . $ags_files_lbl . '
                    </span>
                    <span class="badge meta-badge meta-badge-date" title="' . htmlspecialchars($lang->details['lbl_uploaded'], ENT_QUOTES) . '">
                        <i class="bi bi-calendar-check-fill me-1"></i>' . my_datee('relative', $Torrent['added']) . '
                    </span>
                    ' . ($already_snatched ? '
                    <span class="badge meta-badge meta-badge-snatched" title="' . htmlspecialchars($lang->details['tip_snatched_by_you'], ENT_QUOTES) . '">
                        <i class="bi bi-check2-circle me-1"></i>' . htmlspecialchars($lang->details['badge_already_downloaded'], ENT_QUOTES) . '
                    </span>' : '') . '
                    ' . $act . '
                </div>
            </div>
        </div>
    </div>

    <!-- Action-card -->
    <div class="card shadow-sm border-0 mb-5 action-card animate__animated animate__zoomIn">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-3">
                        <div class="action-icon">
                            <i class="bi bi-cloud-download"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold">' . htmlspecialchars($lang->details['dltorrent'], ENT_QUOTES) . '</h5>
                            <div class="text-muted small">
                                <i class="bi bi-hdd me-1"></i>' . mksize($Torrent['size']) . '
                                <span class="mx-2">•</span>
                                <i class="bi bi-file-earmark me-1"></i>' . $ags_files_lbl . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <div class="d-flex gap-2 justify-content-md-end flex-wrap align-items-center">
                        <a href="' . get_download_link($id) . '"
                           class="btn btn-primary btn-lg btn-download-pulse"
                           title="' . htmlspecialchars($lang->details['dltorrent'], ENT_QUOTES) . '">
                            <i class="bi bi-cloud-arrow-down-fill me-2"></i>' . htmlspecialchars($lang->details['download'], ENT_QUOTES) . '
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-lg report-btn"
                                data-bs-toggle="modal" data-bs-target="#reportModal"
                                data-report-type="torrent"
                                data-report-id="' . $id . '"
                                data-report-userid="' . $Torrent['owner'] . '"
                                data-report-name="' . htmlspecialchars($Torrent['name'] ?? $lang->details['lbl_torrent_fallback']) . '"
                                title="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '">
                            <i class="bi bi-flag-fill"></i>
                        </button>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-lg dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false" title="' . htmlspecialchars($lang->details['tip_more_options'], ENT_QUOTES) . '">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                ' . $magnetButton . '
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item scroll-to-tab" href="#info" data-scroll-tab="info">
                                        <i class="bi bi-info-circle me-2"></i>' . htmlspecialchars($lang->details['tab_info'], ENT_QUOTES) . '
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item scroll-to-tab" href="#files" data-scroll-tab="files">
                                        <i class="bi bi-folder me-2"></i>' . htmlspecialchars($lang->details['menu_filelist'], ENT_QUOTES) . '
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item scroll-to-tab" href="#peers" data-scroll-tab="peers">
                                        <i class="bi bi-people me-2"></i>' . htmlspecialchars($lang->details['menu_peers'], ENT_QUOTES) . '
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            ' . $claimBox . '

            ' . ($modal_images ? '
            <!-- Posters -->
            <div class="poster-strip mt-4">
                ' . $modal_images . '
            </div>
            ' : '') . '

            <!-- Health progress -->
            <div class="health-progress-wrap mt-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-muted">
                        <i class="bi bi-heart-pulse-fill me-1 text-danger"></i>' . htmlspecialchars($lang->details['lbl_torrent_health'], ENT_QUOTES) . '
                    </span>
                    <span class="small fw-bold">' . getHealthPercentage($Torrent['seeders'], $Torrent['leechers']) . '%</span>
                </div>
                <div class="progress health-progress" role="progressbar"
                     aria-valuenow="' . getHealthPercentage($Torrent['seeders'], $Torrent['leechers']) . '"
                     aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-' . getHealthColor($Torrent['seeders'], $Torrent['leechers']) . '"
                         style="width: ' . getHealthPercentage($Torrent['seeders'], $Torrent['leechers']) . '%"></div>
                </div>
            </div>

            <!-- Stat tiles -->
            <div class="row g-3 mt-4">
                <div class="col-6 col-md-3">
                    <div class="stat-tile stat-tile-success">
                        <div class="stat-tile-icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
                        <div class="stat-tile-body">
                            <div class="stat-tile-value">' . ts_nf($Torrent['seeders']) . '</div>
                            <div class="stat-tile-label">
                                <i class="bi bi-broadcast me-1"></i>' . htmlspecialchars($lang->details['stat_seeders'], ENT_QUOTES) . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile stat-tile-warning">
                        <div class="stat-tile-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                        <div class="stat-tile-body">
                            <div class="stat-tile-value">' . ts_nf($Torrent['leechers']) . '</div>
                            <div class="stat-tile-label">
                                <i class="bi bi-download me-1"></i>' . htmlspecialchars($lang->details['stat_leechers'], ENT_QUOTES) . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile stat-tile-info">
                        <div class="stat-tile-icon"><i class="bi bi-cloud-download-fill"></i></div>
                        <div class="stat-tile-body">
                            <div class="stat-tile-value">' . ts_nf($Torrent['times_completed']) . '</div>
                            <div class="stat-tile-label">
                                <i class="bi bi-check2-all me-1"></i>' . htmlspecialchars($lang->details['snatched'], ENT_QUOTES) . '
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-tile stat-tile-secondary">
                        <div class="stat-tile-icon"><i class="bi bi-chat-square-text-fill"></i></div>
                        <div class="stat-tile-body">
                            <div class="stat-tile-value">' . ts_nf($Torrent['comments']) . '</div>
                            <div class="stat-tile-label">
                                <i class="bi bi-chat-dots me-1"></i>' . htmlspecialchars($lang->details['comments'], ENT_QUOTES) . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="card shadow-sm border-0 mb-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light p-0">
            <ul class="nav nav-tabs nav-tabs-scrollable" id="torrentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab"
                            data-bs-target="#info" type="button" role="tab">
                        <i class="bi bi-info-square-fill me-2"></i>' . htmlspecialchars($lang->details['tab_info'], ENT_QUOTES) . '
                    </button>
                </li>
                ' . $screenTab . '
                ' . $nfoTab . '
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="files-tab" data-bs-toggle="tab"
                            data-bs-target="#files" type="button" role="tab">
                        <i class="bi bi-folder-fill me-2"></i>' . htmlspecialchars(ags_fmt($lang->details['tab_files'], ts_nf($Torrent['numfiles']))) . '
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="peers-tab" data-bs-toggle="tab"
                            data-bs-target="#peers" type="button" role="tab">
                        <i class="bi bi-people-fill me-2"></i>' . htmlspecialchars(ags_fmt($lang->details['tab_peers'], ts_nf($Torrent['seeders'] + $Torrent['leechers']))) . '
                    </button>
                </li>
                ' . ((int)$Torrent['seeders'] === 0 && $CURUSER ? '
                <li class="nav-item align-self-center ms-2">
                    <a href="' . $BASEURL . '/takereseed.php?reseedid=' . (int)$Torrent['id'] . '"
                       class="btn btn-sm btn-outline-warning"
                       onclick="return confirm(' . htmlspecialchars(json_encode($lang->details['confirm_reseed'], $ags_json_flags), ENT_QUOTES) . ')">
                        <i class="bi bi-megaphone-fill me-1"></i> ' . htmlspecialchars($lang->details['btn_request_reseed'], ENT_QUOTES) . '
                    </a>
                </li>' : '') . '
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="torrentTabsContent">

                <!-- Вкладка информации -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="row g-4">
                        <!-- Левая колонка -->
                        <div class="col-md-6">
                            <div class="info-grid">
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-calendar me-1"></i>' . htmlspecialchars($lang->details['lbl_uploaded'], ENT_QUOTES) . '</span>
                                    <span class="fw-bold text-end">
                                        ' . my_datee($dateformat, $Torrent['added']) . '
                                        <small class="text-muted ms-2">' . my_datee($timeformat, $Torrent['added']) . '</small>
                                    </span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-tag me-1"></i>' . htmlspecialchars($lang->details['lbl_category'], ENT_QUOTES) . '</span>
                                    <span class="fw-bold text-end">' . $torrent2['categoryname'] . '</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hdd me-1"></i>' . htmlspecialchars($lang->details['size'], ENT_QUOTES) . '</span>
                                    <span class="fw-bold text-end">' . mksize($Torrent['size']) . '</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-hash me-1"></i>' . htmlspecialchars($lang->details['infohash'], ENT_QUOTES) . '</span>
                                    <span class="font-monospace small text-end text-break">' . htmlspecialchars($Torrent['info_hash'] ?? $lang->details['na']) . '</span>
                                </div>
                            </div>
                        </div>

                        <!-- Правая колонка -->
                        <div class="col-md-6">
                            <div class="info-grid">
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-download me-1"></i>' . htmlspecialchars($lang->details['snatched'], ENT_QUOTES) . '</span>
                                    <span class="badge bg-light text-dark">
                                        <a href="viewsnatches.php?id=' . $id . '" class="text-decoration-none text-dark">
                                            ' . ts_nf($Torrent['times_completed']) . '
                                        </a>
                                    </span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-eye me-1"></i>' . htmlspecialchars($lang->details['views'], ENT_QUOTES) . '</span>
                                    <span class="badge bg-light text-dark">' . ts_nf($Torrent['hits']) . '</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-chat me-1"></i>' . htmlspecialchars($lang->details['comments'], ENT_QUOTES) . '</span>
                                    <span class="badge bg-light text-dark">' . ts_nf($Torrent['comments']) . '</span>
                                </div>
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-person me-1"></i>' . htmlspecialchars($lang->details['uppedby'], ENT_QUOTES) . '</span>
                                    <span class="fw-bold text-end">' . $username . '</span>
                                </div>
                                ' . ($show_manage != '' ? '
                                <div class="info-item d-flex justify-content-between border-bottom py-3">
                                    <span class="text-muted"><i class="bi bi-gear me-1"></i>' . htmlspecialchars($lang->details['lbl_manage'], ENT_QUOTES) . '</span>
                                    <span class="fw-bold text-end">' . $show_manage . '</span>
                                </div>' : '') . '
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    ' . ($keywords ? '
                    <div class="mt-4 tag-section">
                        <h6 class="fw-semibold mb-3">
                            <i class="bi bi-tags me-2"></i>' . htmlspecialchars($lang->details['sec_tags'], ENT_QUOTES) . '
                        </h6>
                        <div class="d-flex flex-wrap gap-2">' . $keywords . '</div>
                    </div>' : '') . '

                    <!-- Rating -->
                    ' . $rating_html . '
                </div>

                <!-- Files tab -->
                <div class="tab-pane fade" id="files" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>' . htmlspecialchars($lang->details['sec_file_structure'], ENT_QUOTES) . '</h6>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-primary rounded-2 d-flex align-items-center gap-2" onclick="expandAllFiles()">
                                <i class="bi bi-plus-circle"></i><span>' . htmlspecialchars($lang->details['btn_expand_all'], ENT_QUOTES) . '</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary rounded-2 d-flex align-items-center gap-2" onclick="collapseAllFiles()">
                                <i class="bi bi-dash-circle"></i><span>' . htmlspecialchars($lang->details['btn_collapse_all'], ENT_QUOTES) . '</span>
                            </button>
                        </div>
                    </div>
                    ' . (isset($tree) && $tree !== null
                        ? '<div class="file-tree">' . renderAccordion($tree) . '</div>'
                        : '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>' . htmlspecialchars($lang->details['err_torrent_file_missing'], ENT_QUOTES) . '</div>') . '
                </div>

                ' . $screenContent . '
                ' . $nfoContent . '

                <!-- Peers tab -->
                <div class="tab-pane fade" id="peers" role="tabpanel">
                    ' . $peerstable . '
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="card shadow-sm border-0 mt-5 animate__animated animate__fadeInUp">
        <div class="card-header bg-light">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-card-text me-2 text-primary"></i>' . htmlspecialchars($lang->details['description'], ENT_QUOTES) . '</h5>
        </div>
        <div class="card-body p-4">' . $descr . '</div>
    </div>

    <!-- Extra sections -->
    ' . $ShowTLINK . '
    ' . $SimilarTorrents . '
    ' . $showcommenttable . '
</div>

' . $magnetModal . '';


echo '
' . ($is_mod ? '
<script type="text/javascript">
    l_updated = "' . $lang->global['imgupdated'] . '";
    l_refresh = "' . $lang->global['refresh'] . '";
</script>' : '');

echo $details;

stdfoot();