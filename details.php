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

// ── Helpers ─────────────────────────────────────────────────────────────────
/**
 * Состояние картинки по ссылке: свой файл в /torrents/images/ или
 * /torrents/screens/ проверяем на диске, внешнюю ссылку — нет.
 * Возвращает 'ok', 'missing' или 'external'.
 */
function dt_local_image_state(string $url): string
{
    global $BASEURL;

    $path = (string)parse_url($url, PHP_URL_PATH);
    $host = (string)parse_url($url, PHP_URL_HOST);

    if ($host !== '' && strcasecmp($host, (string)parse_url((string)$BASEURL, PHP_URL_HOST)) !== 0) {
        return 'external';
    }

    foreach (['/torrents/images/', '/torrents/screens/'] as $dir) {
        if (str_starts_with($path, $dir)) {
            return is_file(__DIR__ . $dir . basename($path)) ? 'ok' : 'missing';
        }
    }

    return 'external';
}

/** Заглушка вместо отсутствующего постера / скриншота. */
function dt_img_missing_html(string $kind, string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES);

    return '<div class="dt-img-missing dt-img-missing--' . $kind . '" role="img" aria-label="' . $text . '">'
         . '<i class="fa-light fa-image" aria-hidden="true"></i><span>' . $text . '</span></div>';
}

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
if (!empty($mybb->input['pid']) && !$mybb->input['id']) {
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

function getHealthColor(mixed $seeders, mixed $leechers): string
{
    return match (true) {
        getHealthPercentage($seeders, $leechers) >= 70 => 'success',
        getHealthPercentage($seeders, $leechers) >= 40 => 'warning',
        default                                        => 'danger',
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

function dtFileType(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'video'   => ['mp4','mkv','avi','mov','wmv','flv','webm','mpeg','mpg','3gp','m4v','vob','ts','m2ts','ogv','rm','rmvb'],
        'audio'   => ['mp3','flac','wav','ogg','m4a','aac'],
        'image'   => ['jpg','jpeg','png','gif','bmp','webp','svg','tiff'],
        'subs'    => ['srt','ass','ssa','sub','idx','vtt','sup'],
        'archive' => ['zip','rar','7z','tar','gz','bz2'],
        'doc'     => ['nfo','txt','md','log','pdf','doc','docx','xls','xlsx','ppt','pptx'],
        'code'    => ['php','html','css','js','json','xml','py','java','c','cpp','sh','bat'],
        'exec'    => ['exe','iso','apk','bin','dll','app','deb','rpm'],
    ];

    foreach ($icons as $key => $exts) {
        if (in_array($ext, $exts, true)) {
            return $key;
        }
    }
    return 'default';
}

/** Сколько файлов каждого типа в дереве: ['video' => 2, ...] */
function dtFileStats(array $tree, array $stats = []): array
{
    foreach ($tree as $name => $content) {
        if (is_array($content)) {
            $stats = dtFileStats($content, $stats);
        } else {
            $type = dtFileType((string)$name);
            $stats[$type] = ($stats[$type] ?? 0) + 1;
        }
    }
    return $stats;
}

function getFileIcon($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $colors = [
        'video'   => 'st-soft-danger',
        'audio'   => 'st-soft-primary',
        'image'   => 'st-soft-success',
        'subs'    => 'st-soft-info',
        'archive' => 'st-soft-warning',
        'doc'     => 'st-soft-muted',
        'code'    => 'st-soft-info',
        'exec'    => 'st-soft-muted',
        'default' => 'st-soft-muted',
    ];

    $iconClasses = [
        'video'   => 'fa-solid fa-file-video',
        'audio'   => 'fa-solid fa-file-audio',
        'image'   => 'fa-solid fa-file-image',
        'subs'    => 'fa-solid fa-closed-captioning',
        'archive' => 'fa-solid fa-file-zipper',
        'doc'     => 'fa-solid fa-file-lines',
        'code'    => 'fa-solid fa-file-code',
        'exec'    => 'fa-solid fa-microchip',
        'default' => 'fa-solid fa-file',
    ];

    $type = dtFileType((string)$filename);

    $colorClass = $colors[$type]     ?? $colors['default'];
    $iconClass  = $iconClasses[$type] ?? $iconClasses['default'];

    $label = htmlspecialchars(strtoupper($ext));

    return '<span class="st-badge dt-ext ' . $colorClass . '" aria-label="' . $label . '" title="' . $label . '">'
         . '<i class="' . $iconClass . '"></i>' . $label
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
                        <i class="fa-solid fa-folder text-warning me-2"></i>' . htmlspecialchars((string)$name) . '
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
            $html .= '<div class="dt-file">'
                   . $icon
                   . '<span class="dt-file__name" title="' . htmlspecialchars((string)$name, ENT_QUOTES) . '">' . htmlspecialchars((string)$name) . '</span>'
                   . '<span class="st-badge st-soft-muted ms-auto">' . mksize($content) . '</span>'
                   . '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}

// ── Load torrent ────────────────────────────────────────────────────────────
// Один запрос вместо get_torrent() + отдельного JOIN-а
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
", [(int)$mybb->input['id']]);

$Torrent = $query ? $db->fetch_array($query) : null;

if (!$Torrent) {
    stderr($lang->global['notorrentid'], $SITENAME . ' - ' . $lang->details['title_notfound'], 404, 'torrent');
}
if ($Torrent['banned'] === 'yes' && !$is_mod) {
    stderr($lang->global['torrentbanned']);
}

$id = (int)$Torrent['id'];

// Раньше в $torrent2['id'] был id владельца (u.id) — сохраняем это для get_user_icons()
$torrent2 = ['id' => (int)$Torrent['owner']] + $Torrent;

$lang->load('browse');
$lang->load('upload');

require_once(INC_PATH . '/functions_mkprettytime.php');

// ── Similar torrents ────────────────────────────────────────────────────────
$SimilarTorrents = '';
$Torrent_name    = $Torrent['name'];

// Убираем операторы BOOLEAN MODE: "Spider-Man" не должно значить «Spider без Man»
$ft = trim((string)preg_replace('/[+\-<>()~*"@]+/u', ' ', (string)$Torrent_name));

$query_result = false;
if ($ft !== '') {
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
        HAVING score > 1
        ORDER BY score DESC
        LIMIT 8
    ";

    $query_result = $db->sql_query_prepared($query, [$ft, $ft, $id]);
}

if ($query_result && $db->num_rows($query_result) > 0) {
    $FoundSMTQ   = '';
    $found_count = 0;

    // Ровные ряды по 4: лишние карточки неполного ряда не выводим
    $sim_total = (int)$db->num_rows($query_result);
    $sim_limit = $sim_total >= 4 ? $sim_total - $sim_total % 4 : $sim_total;

    while ($found_count < $sim_limit && ($SMTQ = $db->fetch_array($query_result))) {
        $SEOLink  = get_torrent_link($SMTQ['id']);
        $SEOLinkC = get_category_link($SMTQ['category']);

        $poster = !empty($SMTQ['t_image'])
            ? htmlspecialchars_uni($SMTQ['t_image'])
            : $BASEURL . '/include/templates/default/images/no_image.png';

        $uploaderHtml = (!$is_mod && $SMTQ['owner'] != $CURUSER['id'] && $SMTQ['anonymous'] == 'yes')
            ? '<span><i class="fa-solid fa-user-secret me-1"></i>' . $lang->global['anonymous'] . '</span>'
            : '<a href="' . get_profile_link($SMTQ['owner']) . '" class="text-decoration-none">'
              . format_name($SMTQ['username'], $SMTQ['usergroup']) . '</a>'
              . ($SMTQ['anonymous'] == 'yes' ? ' <span>(' . $lang->global['anonymous'] . ')</span>' : '');

        $FoundSMTQ .= '
        <article class="dt-sim">
            <a href="' . $SEOLink . '" class="dt-sim__poster" title="' . htmlspecialchars_uni($SMTQ['name']) . '">
                <img src="' . $poster . '" loading="lazy"
                     alt="' . htmlspecialchars_uni($SMTQ['name']) . '"
                     onerror="this.src=\'' . $BASEURL . '/include/templates/default/images/no_image.png\'">
                <span class="dt-sim__stats">
                    <span class="dt-sim__stat dt-sim__stat--up"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($SMTQ['seeders']) . '</span>
                    <span class="dt-sim__stat dt-sim__stat--down"><i class="fa-solid fa-arrow-down"></i>' . ts_nf($SMTQ['leechers']) . '</span>
                </span>
            </a>
            <a href="' . $SEOLinkC . '" class="dt-sim__cat" title="' . htmlspecialchars_uni((string)$SMTQ['catname']) . '">
                <i class="' . $SMTQ['catimage'] . '"></i>
            </a>
            <div class="dt-sim__body">
                <a href="' . $SEOLink . '" class="dt-sim__title" title="' . htmlspecialchars_uni($SMTQ['name']) . '">'
                    . htmlspecialchars_uni($SMTQ['name']) . '</a>
                <div class="dt-sim__meta">
                    <span class="min-w-0 text-truncate">' . $uploaderHtml . '</span>
                    <span class="text-nowrap">' . mksize($SMTQ['size']) . '</span>
                </div>
            </div>
        </article>';

        $found_count++;
    }

    if ($FoundSMTQ) {
        $SimilarTorrents = '
        <section class="st-card">
            <div class="st-card__head">
                <span class="st-ico st-soft-primary"><i class="fa-solid fa-layer-group"></i></span>
                ' . $lang->details['smililartorrents'] . '
                <span class="st-badge st-soft-muted ms-auto">' . $found_count . '</span>
            </div>
            <div class="st-card__body">
                <div class="dt-sim-grid">' . $FoundSMTQ . '</div>
            </div>
        </section>';
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
                   . 'title="' . $tagSafe . '" class="dt-tag"><i class="fa-solid fa-hashtag"></i>' . $tagSafe . '</a> ';
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
                                  . ' <i class="fa-solid fa-chevron-right small text-body-secondary"></i> '
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


echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/details.css?ver=38">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/claim.css?ver=121">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">';
// Strings for page scripts: js_* keys from the lang, without the prefix
$ags_js_lang = [];
foreach ($lang->details as $ags_key => $ags_val) {
    if (str_starts_with((string)$ags_key, 'js_')) {
        $ags_js_lang[substr((string)$ags_key, 3)] = $ags_val;
    }
}
echo '<script>const AGS_LANG = ' . json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/toast.js?ver=2"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/bookmark.js?ver=21"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/details_modal.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/popover.js"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/details.js?ver=9"></script>';
echo '<script type="text/javascript" src="' . $BASEURL . '/scripts/report.js?ver=2"></script>';

require_once INC_PATH . '/modals.php';

// Быстрый комментарий приходит по AJAX: стили и скрипты нужны сразу, даже без комментариев
echo commenttable_assets();



// ── Hit & Run warning ───────────────────────────────────────────────────────
$gigs = $CURUSER['downloaded'] / (1024 * 1024 * 1024);
$warning_message = '';

if ($hitrun == 'yes') {
    $ratio      = ($CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0);
    $percentage = $ratio * 100;

    if ($Torrent['free'] != 'yes' && $usergroups['isvipgroup'] != 'yes' && $ratio <= ($hitrun_ratio + 0.4)
        && $Torrent['owner'] != $CURUSER['id'] && !$is_mod && $CURUSER['downloaded'] <> 0) {

        $warning_message = '
    <div class="st-card st-danger-box dt-alert" role="alert">
        <span class="st-ico st-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div class="min-w-0">' . ags_fmt($lang->details['downloadwarning'], number_format($ratio, 2), number_format($percentage, 0) . '%', (string)$hitrun_ratio) . '</div>
    </div>';
    }
}

$sratio = $Torrent['leechers'] > 0 ? $Torrent['seeders'] / $Torrent['leechers'] : 1;
$lratio = $Torrent['seeders'] > 0 ? $Torrent['leechers'] / $Torrent['seeders'] : 1;

// ── Rating data ─────────────────────────────────────────────────────────────
// Один запрос: среднее, число голосов и оценка текущего пользователя
$showcommenttable = '';
$threadcount      = 0;

$q2 = $db->sql_query_prepared("
    SELECT ROUND(AVG(rating), 1)                      AS avg_r,
           COUNT(*)                                   AS cnt,
           MAX(CASE WHEN user_id = ? THEN rating END) AS mine
    FROM torrent_ratings
    WHERE torrent_id = ?
", [(int)$CURUSER['id'], $id]);
$rr = $q2 ? ($db->fetch_array($q2) ?: []) : [];

$rating_data = [
    'avg'         => (float)($rr['avg_r'] ?? 0),
    'count'       => (int)($rr['cnt'] ?? 0),
    'user_rating' => (int)($rr['mine'] ?? 0),
];

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
        <div class="user-stars" id="user-stars">' . $user_stars_html . '</div>
        <span class="st-badge st-soft-muted" id="rating-hint">'
            . ($rating_data['user_rating'] ? $rating_data['user_rating'] . '/10' : htmlspecialchars($lang->details['hint_rate'])) .
        '</span>';
} else {
    $user_section = '<a href="login.php" class="btn btn-sm btn-outline-primary st-pill"><i class="fa-solid fa-right-to-bracket me-1"></i>' . htmlspecialchars($lang->details['btn_login_to_rate']) . '</a>';
}

$ags_votes_label = ags_fmt(ags_plural($rating_data['count'], $lang->details['lbl_votes']), number_format($rating_data['count']));

$rating_html = '
<div class="dt-rate">
    <span class="st-ico st-soft-warning dt-rate__ico"><i class="fa-solid fa-star"></i></span>
    <div class="dt-rate__avg">
        <div class="dt-rate__score"><span class="rating-score">' . ($rating_data['count'] > 0 ? number_format($rating_data['avg'], 1) : '—') . '</span><small>/ 10</small></div>
        <div class="dt-rate__votes">' . htmlspecialchars($ags_votes_label) . '</div>
    </div>
    <div class="min-w-0">
        <div class="dt-rate__title">' . htmlspecialchars($lang->details['sec_user_rating']) . '</div>
        <div class="rating-stars dt-rate__stars" id="rating-display">' . $avg_stars_html . '</div>
    </div>
    <div class="dt-rate__mine">' . $user_section . '</div>
</div>
<script src="' . $BASEURL . '/scripts/rating.js?ver=121"></script>
<script>ratingInit(' . (int)$rating_data['user_rating'] . ', ' . (int)$id . ', "' . $BASEURL . '", "' . htmlspecialchars((string)$mybb->post_code, ENT_QUOTES) . '");</script>';

// ── Comments ────────────────────────────────────────────────────────────────
$query       = $db->sql_query_prepared("SELECT COUNT(id) AS commentss FROM comments c WHERE torrent = ?", [$id]);
$threadcount = $query ? (int)$db->fetch_field($query, "commentss") : 0;

// Ссылка на конкретный комментарий (…-comment-{pid}.html): проверяем, что он есть и относится к этому торренту
$target_comment = null;
if (!empty($mybb->input['pid'])) {
    $target_comment = get_comment($mybb->input['pid']);

    if (!$target_comment || (int)$target_comment['torrent'] !== (int)$id) {
        $target_comment = null;

        $cnf_pid   = (int)$mybb->input['pid'];
        $cnf_title = htmlspecialchars($lang->details['comment_not_found'] ?? 'Comment not found');
        $cnf_desc  = htmlspecialchars($lang->details['comment_not_found_desc'] ?? 'It may have been deleted or merged with another comment, or the link is incorrect.');
        $cnf_btn   = htmlspecialchars($lang->details['comment_not_found_last'] ?? 'Latest comments');
        $cnf_close = htmlspecialchars($lang->details['comment_not_found_close'] ?? 'Close');

        // Последний комментарий этого торрента — кнопка ведёт прямо к нему
        $cnf_last  = '';
        $cnf_q     = $db->sql_query_prepared("SELECT MAX(id) AS last_id FROM comments WHERE torrent = ?", [(int)$id]);
        $cnf_lid   = $cnf_q ? (int)$db->fetch_field($cnf_q, 'last_id') : 0;
        if ($cnf_lid > 0) {
            $cnf_last = $BASEURL . '/' . get_comment_link($cnf_lid, (int)$id) . '#pid' . $cnf_lid;
        }

        $showcommenttable .= '
    <div id="comment-not-found">
        <div class="cnf-card" role="alert">
            <span class="cnf-icon"><i class="fa-solid fa-comment-slash"></i></span>
            <div class="flex-grow-1 me-sm-3">
                <p class="cnf-title">' . $cnf_title . '<span class="cnf-pid">#' . $cnf_pid . '</span></p>
                <p class="cnf-desc">' . $cnf_desc . '</p>
            </div>

            ' . ($cnf_last !== '' ? '<a href="' . htmlspecialchars($cnf_last) . '" class="btn btn-sm btn-warning st-pill text-nowrap">
                <i class="fa-solid fa-arrow-down-wide-short me-1"></i>' . $cnf_btn . '
            </a>' : '') . '

            <button type="button" class="btn-close cnf-close" aria-label="' . $cnf_close . '"
                    onclick="this.closest(\'#comment-not-found\').remove()"></button>
        </div>
    </div>';
    }
}

if (!$threadcount) {
    $showcommenttable .= '
    <section class="st-card dt-comments-empty">
        <div class="st-empty">
            <span class="st-ico st-soft-muted"><i class="fa-solid fa-comments"></i></span>
            <div>' . $lang->details['nocommentsyet'] . '</div>
        </div>
    </section>
    <div id="ajax_comment_preview"></div>
    <div id="ajax_comment_preview2"></div>';
} else {
    $multipage = '';
    $page      = 1;
    $perpage   = $ts_perpage;

    if (isset($mybb->input['page']) && $mybb->input['page'] != "last") {
        $page = (int)$mybb->input['page'];
    }

    // Страница, на которой находится комментарий. Считаем по id — в том же порядке, что и вывод (ORDER BY c.id)
    if ($target_comment && (int)$perpage > 0) {
        $res = $db->sql_query_prepared(
            "SELECT COUNT(*) AS count FROM comments WHERE torrent = ? AND id <= ?",
            [(int)$id, (int)$target_comment['id']]
        );
        if ($res) {
            $page = (int)ceil((int)$db->fetch_field($res, 'count') / (int)$perpage);
        }
    }

    // Общее число уже посчитано выше — второй COUNT не нужен
    $postcount = $threadcount;
    $pages     = (int)ceil($postcount / $perpage);

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

    $comment_sql = "
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
    ";
    $query = $comment_sql . " WHERE c.torrent = ? ORDER BY c.id LIMIT ?, ?";

    $params = [(int)$id, (int)$start, (int)$perpage];
    $subres = $db->sql_query_prepared($query, $params);

    if ($subres) {
        while ($subrow = $db->fetch_array($subres)) {
            $allrows[] = $subrow;
        }
        $db->free_result($subres);
    }

    // Закреплённый комментарий — первым на каждой странице. На своём месте в списке
    // не показываем, но он остаётся в нумерации и постраничности.
    $dt_pinned_id = (int)($Torrent['pinned_comment'] ?? 0);
    if ($dt_pinned_id > 0) {
        $dt_pin_res = $db->sql_query_prepared($comment_sql . " WHERE c.id = ? AND c.torrent = ?", [$dt_pinned_id, (int)$id]);
        $dt_pin_row = $dt_pin_res ? $db->fetch_array($dt_pin_res) : null;

        if ($dt_pin_row) {
            foreach ($allrows as $dt_i => $dt_r) {
                if ((int)$dt_r['id'] === $dt_pinned_id) {
                    $allrows[$dt_i]['pin_skip'] = true;
                }
            }
            $dt_pin_row['is_pinned'] = true;
            array_unshift($allrows, $dt_pin_row);
        }
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

    $dt_pager = $multipage !== '' ? '<div class="st-pager dt-pager">' . $multipage . '</div>' : '';

    $showcommenttable .= $dt_pager
                       . commenttable($allrows, '', '', false, true, true)
                       . $dt_pager;
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
           u.id, u.avatar, u.avatardimensions, u.invisible, u.enabled, u.username, u.usergroup, u.warned, u.donor
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
<section class="st-card dt-quick">
    <div class="st-card__head">
        <span class="st-ico st-soft-primary"><i class="fa-solid fa-pen-to-square"></i></span>
        ' . htmlspecialchars($lang->details['sec_quick_comment']) . '
    </div>
    <div class="st-card__body">
    ' . (!empty($cerror) ? '<div class="alert alert-danger py-2">' . $cerror . '</div>' : '') . '
    ' . ($use_xmlhttprequest == '1' ? '<script src="' . $BASEURL . '/scripts/quick_comment.js?ver=72"></script>' : '') . '
    ' . $editor['toolbar'] . '
    <form name="comment" id="comment" method="post" action="comment.php?action=add&tid=' . $id . '" novalidate>
        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="page" value="' . intval($page ?? ($_GET['page'] ?? 1)) . '">
        <input type="hidden" name="posthash" value="' . htmlspecialchars($posthash) . '">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        <div id="fileIdsContainer"></div>
        <div class="mb-3">
            <label for="message" class="form-label"><i class="fa-solid fa-comment me-1"></i>' . htmlspecialchars($lang->details['lbl_your_comment']) . ' <small class="text-body-secondary">' . htmlspecialchars(ags_fmt($lang->details['hint_max_chars'], 500)) . '</small></label>
            <textarea class="form-control" id="message" name="message" rows="6"
                      placeholder="' . htmlspecialchars($lang->details['ph_comment'], ENT_QUOTES) . '" maxlength="500"
                      aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>
        <div id="message_preview" class="form-control mt-3 d-none"></div>
        ' . $uploader . '
        ' . ($use_xmlhttprequest == '1' ? '
        <div class="dt-quick__actions">
            <i id="loading-layer" class="fa-solid fa-circle-notch fa-spin text-primary" aria-label="' . htmlspecialchars($lang->details['aria_loading'], ENT_QUOTES) . '" style="display:none;"></i>
            <button type="button" class="btn btn-primary st-pill" id="quickcomment" onclick="TSajaxquickcomment(\'' . $id . '\');"><i class="fa-solid fa-paper-plane me-1"></i>' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid=' . $id . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-gear me-1"></i>' . $lang->global['advancedbutton'] . '</a>
        </div>' : '
        <div class="dt-quick__actions">
            <button type="submit" name="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-paper-plane me-1"></i>' . $lang->global['buttonsubmit'] . '</button>
            <a href="comment.php?action=add&tid=' . $id . '" class="btn btn-outline-secondary st-pill"><i class="fa-solid fa-gear me-1"></i>' . $lang->global['advancedbutton'] . '</a>
        </div>') . '
    </form>
    ' . $editor['modal'] . '
    </div>
</section>';

// ── Uploader name ───────────────────────────────────────────────────────────
if ($Torrent['anonymous'] == 'yes' AND $Torrent['owner'] != $CURUSER['id'] AND !$is_mod) {
    $username = '<span class="text-body-secondary"><i class="fa-solid fa-user-secret me-1"></i>' . $lang->global['anonymous'] . '</span>';
} else {
    $username = '<a href="' . get_profile_link($Torrent['owner']) . '" class="text-decoration-none">'
              . format_name($torrent2['username'], $torrent2['usergroup']) . '</a>'
              . get_user_icons($torrent2);
}

// ── IMDb ────────────────────────────────────────────────────────────────────
$ShowTLINK = '';

// IMDb: разбираем сохранённый HTML для карточки и чипов в шапке
$imdb_data = null;
$imdb_chips = '';
if (!empty($Torrent['t_link'])) {
    require_once INC_PATH . '/functions_imdb_card.php';
    $imdb_data = imdb_card_parse((string)$Torrent['t_link']);

    if ($imdb_data !== null) {
        if ($imdb_data['rating'] !== '') {
            $imdb_chips .= '<a class="dt-chip dt-chip--imdb" href="' . htmlspecialchars($imdb_data['url'], ENT_QUOTES) . '" target="_blank" rel="noopener"><i class="fa-solid fa-star"></i>IMDb ' . htmlspecialchars($imdb_data['rating']) . '</a>';
        }
        foreach (array_slice($imdb_data['genres'], 0, 3) as $imdb_genre) {
            $imdb_chips .= '<span class="dt-chip">' . htmlspecialchars($imdb_genre) . '</span>';
        }
        if ($imdb_data['year'] !== '') {
            $imdb_chips .= '<span class="dt-chip">' . htmlspecialchars($imdb_data['year']) . '</span>';
        }
    }
    $refresh = !empty($is_mod) ? ($lang->global['refresh'] ?? '') : '';

    $ShowTLINK = '
    <section class="st-card dt-imdb-card">
        <div class="st-card__head">
            <span class="st-ico st-soft-warning"><i class="fa-solid fa-film"></i></span>
            ' . $lang->details['t_link'] . '
            ' . ($refresh !== '' ? '<div id="imdbupdatebutton" class="ms-auto">
                <a href="#" class="btn btn-sm btn-outline-secondary st-pill" onclick="TS_IMDB(' . $id . '); return false;"><i class="fa-solid fa-rotate me-1"></i>' . $refresh . '</a>
            </div>' : '') . '
        </div>
        <div class="st-card__body"><div id="imdbdetails">' . ($imdb_data !== null ? imdb_card_render($imdb_data) : $Torrent['t_link']) . '</div></div>
    </section>';
}

// ── Manage dropdown ─────────────────────────────────────────────────────────
$show_manage = '';

if ((int)$CURUSER['id'] === (int)$torrent2['owner'] || $is_mod) {
    $show_manage .= '
    <div class="dropdown">
        <a href="#" class="st-icon-btn" role="button" id="manageCompactDropdown" data-bs-toggle="dropdown"
           aria-expanded="false" title="' . htmlspecialchars($lang->details['lbl_manage'], ENT_QUOTES) . '"
           aria-label="' . htmlspecialchars($lang->details['lbl_manage'], ENT_QUOTES) . '">
            <i class="fa-solid fa-ellipsis-vertical"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="manageCompactDropdown">
            <li>
                <a class="dropdown-item" href="upload.php?id=' . $id . '">
                    <i class="fa-solid fa-file-pen text-info me-2"></i>' . htmlspecialchars($lang->details['menu_full_edit']) . '
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger"
                   href="#" data-bs-toggle="modal" data-bs-target="#deleteTorrentModal"
                   data-torrent-id="' . $id . '"
                   data-torrent-name="' . htmlspecialchars_uni($Torrent['name']) . '">
                    <i class="fa-solid fa-trash-can me-2"></i>' . htmlspecialchars($lang->details['btn_delete']) . '
                </a>
            </li>
        </ul>
    </div>';
}

if ($is_mod) {
    $ags_t_hitrun     = htmlspecialchars($lang->details['tip_hitrun'], ENT_QUOTES);
    $ags_t_open       = htmlspecialchars($lang->details['open'], ENT_QUOTES);
    $ags_t_close      = htmlspecialchars($lang->details['close'], ENT_QUOTES);
    $ags_t_info       = htmlspecialchars($lang->details['torrentinfo'], ENT_QUOTES);

    $ags_t_nuke = htmlspecialchars(
        ($Torrent['isnuked'] ?? 'no') === 'yes'
            ? ($lang->details['tip_nuked'] ?? 'Nuked: change reason / unnuke')
            : ($lang->details['tip_nuke'] ?? 'Nuke torrent'),
        ENT_QUOTES
    );

    $ags_t_comments = $Torrent['allowcomments'] != 'yes' ? $ags_t_open : $ags_t_close;

    $show_manage .= '
    <a href="' . $BASEURL . '/admin/index.php?act=hit_and_run&torrentid=' . $id . '" class="st-icon-btn" title="' . $ags_t_hitrun . '" aria-label="' . $ags_t_hitrun . '">
        <i class="fa-solid fa-person-running"></i></a>

    <form method="post" action="' . $BASEURL . '/comment.php?tid=' . $id . '&action=' . ($Torrent['allowcomments'] != 'yes' ? 'open' : 'close') . '" class="d-inline-flex m-0">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        <button type="submit" class="st-icon-btn border-0" title="' . $ags_t_comments . '" aria-label="' . $ags_t_comments . '">
            <i class="fa-solid fa-comment-slash ' . ($Torrent['allowcomments'] != 'yes' ? 'text-danger' : 'text-success') . '"></i>
        </button>
    </form>

    <a href="' . $BASEURL . '/admin/index.php?act=torrent_info&amp;id=' . $id . '" class="st-icon-btn" title="' . $ags_t_info . '" aria-label="' . $ags_t_info . '">
        <i class="fa-solid fa-circle-info text-info"></i></a>

    <a href="' . $BASEURL . '/admin/nuketorrent.php?id=' . $id . '" class="st-icon-btn" title="' . $ags_t_nuke . '" aria-label="' . $ags_t_nuke . '">
        <i class="fa-solid fa-radiation ' . (($Torrent['isnuked'] ?? 'no') === 'yes' ? 'text-danger' : 'text-warning') . '"></i></a>';
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
        unset($current);
    } catch (\Throwable $e) {
        $TorrentObj = null;
        $tree       = null;
    }
}

// ── Description / Screens / Images ──────────────────────────────────────────
// MediaInfo в описании → отдельная карточка, из описания блок убираем
require_once INC_PATH . '/functions_mediainfo.php';
$dt_mi_card  = '';
$dt_mi_chips = [];
$dt_descr_raw = (string)$Torrent['descr'];

// Сначала поле MediaInfo из формы заливки (torrents_mediainfo), иначе ищем в описании
$dt_mi_found = null;
$dt_mi_text  = '';
$dt_mi_res   = $db->sql_query_prepared("SELECT mediainfo FROM torrents_mediainfo WHERE torrent_id = ?", [(int)$id]);
if ($dt_mi_res && ($dt_mi_row = $db->fetch_array($dt_mi_res))) {
    $dt_mi_text  = trim((string)$dt_mi_row['mediainfo']);
    $dt_mi_found = mi_find($dt_mi_text);
}
$dt_mi_in_descr = $dt_mi_found === null ? mi_find($dt_descr_raw) : null;
$dt_mi_found  ??= $dt_mi_in_descr;

if ($dt_mi_found !== null) {
    $dt_mi_data   = mi_data($dt_mi_found);
    $dt_mi_card   = mi_render($dt_mi_data, $dt_mi_found['raw']);
    $dt_mi_chips  = mi_chips($dt_mi_data);
    if ($dt_mi_in_descr !== null) {
        $dt_descr_raw = mi_strip($dt_descr_raw, $dt_mi_in_descr);
    }
}
// Поле заполнено, но формат незнакомый — блок с исходным текстом
if ($dt_mi_found === null && $dt_mi_text !== '') {
    $dt_mi_card = mi_render_raw($dt_mi_text);
}

$descr = trim($dt_descr_raw) !== '' ? $parser->parse_message($dt_descr_raw, $parser_options) : '';

// Карточка описания: если в описании был только MediaInfo, её не выводим
$dt_descr_card = $descr === '' ? '' : '
    <section class="st-card">
        <div class="st-card__head">
            <span class="st-ico st-soft-primary"><i class="fa-solid fa-align-left"></i></span>
            ' . htmlspecialchars($lang->details['description'], ENT_QUOTES) . '
        </div>
        <div class="st-card__body dt-descr">' . $descr . '</div>
    </section>';

$screenshots = [];
$res = $db->sql_query_prepared("SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY sort_order ASC, id ASC", [$id]);

if ($res) {
    while ($row = $db->fetch_array($res)) {
        $screenshots[] = $row;
    }
}

$ags_t_screenshot = htmlspecialchars($lang->details['lbl_screenshot'], ENT_QUOTES);
$ags_t_shot_miss  = (string)($lang->details['lbl_screenshot_missing'] ?? 'Screenshot file not found.');
$screensHtml      = '<div class="row g-3">';
$dt_shot_strip    = '';   // лента превью над вкладками
$dt_shot_n        = 0;
foreach ($screenshots as $shot) {
    $filename      = htmlspecialchars($shot['filename']);
    $screenshotUrl = '/torrents/screens/' . $filename;

    // Файла скриншота нет на диске — заглушка вместо битой картинки
    if (!is_file(__DIR__ . '/torrents/screens/' . basename((string)$shot['filename']))) {
        $screensHtml .= '
    <div class="col-6 col-md-4 col-lg-3">
        ' . dt_img_missing_html('shot', $ags_t_shot_miss) . '
    </div>';
        continue;
    }

    $dt_shot_strip .= '<button type="button" class="dt-strip__item" data-shot="' . $dt_shot_n++ . '" aria-label="' . $ags_t_screenshot . ' ' . $dt_shot_n . '">'
                    . '<img src="' . $screenshotUrl . '" alt="" loading="lazy" onerror="this.parentNode.remove()"></button>';

    $screensHtml .= '
    <div class="col-6 col-md-4 col-lg-3">
        <a href="#" class="screenshot-wrapper"
           data-img-src="' . $screenshotUrl . '" data-title="' . $ags_t_screenshot . '">
            <img src="' . $screenshotUrl . '" class="img-fluid" alt="' . $ags_t_screenshot . '" loading="lazy"
                 data-kind="shot" data-missing="' . htmlspecialchars($ags_t_shot_miss, ENT_QUOTES) . '"
                 onerror="window.dtImgMissing && dtImgMissing(this)">
        </a>
    </div>';
}
$screensHtml .= '</div>';

$modal_images = '';
$images       = [];
$ags_t_poster_miss = (string)($lang->details['lbl_poster_missing'] ?? 'No poster available.');

if (!empty($Torrent['t_image']))  $images[] = $Torrent['t_image'];
if (!empty($Torrent['t_image2'])) $images[] = $Torrent['t_image2'];

foreach ($images as $img) {
    // Файла постера нет на сервере — заглушка, без открытия в модалке
    if (dt_local_image_state((string)$img) === 'missing') {
        $modal_images .= dt_img_missing_html('poster', $ags_t_poster_miss);
        continue;
    }

    $modal_images .= '
    <a href="#" data-bs-toggle="modal" data-bs-target="#universalImageModal"
       data-img-src="' . htmlspecialchars_uni($img) . '"
       data-title="' . htmlspecialchars_uni($Torrent['name']) . '">
        <img src="' . htmlspecialchars_uni($img) . '" width="400"
             alt="' . htmlspecialchars_uni($Torrent['name']) . '"
             data-kind="poster" data-missing="' . htmlspecialchars($ags_t_poster_miss, ENT_QUOTES) . '"
             onerror="window.dtImgMissing && dtImgMissing(this)">
    </a>';
}

$screenTab     = '';
$screenContent = '';

if (!empty($screenshots)) {
    $screenTab = '
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="screen-tab" data-bs-toggle="tab"
                data-bs-target="#screen" type="button" role="tab"
                aria-controls="screen" aria-selected="false">
            <i class="fa-solid fa-images"></i>' . htmlspecialchars($lang->details['tab_screens']) . ' (' . count($screenshots) . ')
        </button>
    </li>';

    $screenContent = '
    <div class="tab-pane fade" id="screen" role="tabpanel" aria-labelledby="screen-tab">
        ' . $screensHtml . '
    </div>';
}

$nfoTab     = '';
$nfoContent = '';

if (!empty($torrent2['nfo'])) {
    $nfoTab = '
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="nfo-tab" data-bs-toggle="tab"
                data-bs-target="#nfo" type="button" role="tab">
            <i class="fa-solid fa-file-lines"></i>' . htmlspecialchars($lang->details['tab_nfo']) . '
        </button>
    </li>';

    $nfoContent = '
    <div class="tab-pane fade" id="nfo" role="tabpanel" aria-labelledby="nfo-tab">
        <div class="dt-pane-head">
            <h6 class="dt-pane-title"><i class="fa-solid fa-file-lines"></i>' . htmlspecialchars($lang->details['sec_nfo_file']) . '</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="copyNfo()">
                <i class="fa-solid fa-copy me-1"></i>' . htmlspecialchars($lang->details['btn_copy']) . '
            </button>
        </div>
        <div class="dt-nfo">
            <div class="dt-nfo__head"><i class="fa-solid fa-file-lines me-1"></i>' . htmlspecialchars($Torrent['name']) . '.nfo</div>
            <pre id="nfoText">' . htmlspecialchars($torrent2['nfo']) . '</pre>
        </div>
    </div>';
}

// ── Magnet button ───────────────────────────────────────────────────────────
$magnetButton = ($TorrentObj !== null && !$TorrentObj->isPrivate())
    ? '<li><a class="dropdown-item magnet-btn" href="#" data-magnet-id="' . $id . '"><i class="fa-solid fa-magnet me-2"></i>' . htmlspecialchars($lang->details['menu_magnet']) . '</a></li><li><hr class="dropdown-divider"></li>'
    : '';

// ── Claim box (port of NexusPHP "claim block" in details.php) ───────────────
require_once INC_PATH . '/functions_claim.php';
$claimBox = '';
$ags_json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (claim_enabled() && claim_torrent_old_enough($Torrent)) {
    $myClaim     = claim_get((int)$CURUSER['id'], (int)$id);
    $claimCount  = claim_count_torrent((int)$id);
    $claimersUrl = $BASEURL . '/claim.php?torrent_id=' . (int)$id;
    $claimForm   = static function (string $action, string $btnClass, string $icon, string $label, string $confirm) use ($id, $mybb): string {
        return '<form method="post" action="claim.php" data-claim-confirm="' . htmlspecialchars($confirm, ENT_QUOTES) . '">'
             . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">'
             . '<input type="hidden" name="action" value="' . $action . '">'
             . '<input type="hidden" name="torrent_id" value="' . (int)$id . '">'
             . '<input type="hidden" name="returnto" value="details.php?id=' . (int)$id . '">'
             . '<button class="btn ' . $btnClass . ' btn-sm st-pill"><i class="fa-solid ' . $icon . ' me-1"></i>' . htmlspecialchars($label) . '</button></form>';
    };

    if ($myClaim) {
        $text = ags_fmt($lang->details['claim_mine'], date('d.m.Y', (int)$myClaim['added']), claim_cfg('seed_hours'), claim_cfg('upload_times'));
        $btn  = '<a href="' . $BASEURL . '/claim.php" class="btn btn-outline-primary btn-sm st-pill"><i class="fa-solid fa-list-check me-1"></i>' . htmlspecialchars($lang->details['claim_btn_my']) . '</a>'
              . $claimForm('remove', 'btn-outline-danger', 'fa-xmark', $lang->details['claim_btn_give_up'],
                    ags_fmt($lang->details['claim_confirm_give_up'], number_format(claim_cfg('give_up_deduct'))));
    } else {
        try {
            claim_check_can((int)$CURUSER['id'], (int)$id);
            $text = ags_fmt($lang->details['claim_offer'], claim_cfg('seed_hours'));
            $btn  = $claimForm('add', 'btn-success', 'fa-thumbs-up', $lang->details['claim_btn_claim'],
                    ags_fmt($lang->details['claim_confirm_add'], claim_cfg('seed_hours'), number_format(claim_cfg('remove_deduct'))));
        } catch (ClaimException $ex) {
            $text = '<span class="text-body-secondary">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES) . '</span>';
            $btn  = '';
        }
    }

    // Подтверждение формы — в scripts/details.js (подписи кнопок берутся из data-атрибутов)
    $claimBox = '
        <div class="claim-box dt-claim"
             data-claim-yes="' . htmlspecialchars($lang->details['claim_yes'], ENT_QUOTES) . '"
             data-claim-cancel="' . htmlspecialchars($lang->details['claim_cancel'], ENT_QUOTES) . '">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-heart-pulse"></i></span>
            <div class="claim-box__text">' . $text . '
                <div class="st-meta mt-1"><a href="' . $claimersUrl . '">' . htmlspecialchars(ags_fmt($lang->details['claim_by'], (int)$claimCount, claim_cfg('max_per_torrent'))) . '</a></div>
            </div>
            <div class="dt-claim__actions">' . $btn . '</div>
        </div>';
}

$act = '<span id="bookmark' . $Torrent['id'] . '" class="dt-bm">'
     . get_torrent_bookmark_state($CURUSER['id'], (int)$Torrent['id'])
     . '</span>';

// ── Подходящие открытые запросы (видят владелец раздачи и стафф) ───────────
$RequestMatches = '';
if ((int)$Torrent['owner'] === (int)$CURUSER['id'] || !empty($is_mod)) {
    require_once INC_PATH . '/functions_requests.php';
    $rm_list = [];
    try {
        $rm_list = req_find_matches($Torrent);
    } catch (\Throwable $e) {
        error_log('req_find_matches: ' . $e->getMessage());
    }

    if ($rm_list) {
        $rm_conf = htmlspecialchars(json_encode($lang->details['conf_req_fill'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
        $rm_rows = '';
        foreach ($rm_list as $rm) {
            $rm_rid   = (int)$rm['id'];
            $rm_badge = $rm['match_by'] === 'imdb'
                ? '<span class="rm-badge rm-badge--imdb"><i class="fa-brands fa-imdb"></i>' . htmlspecialchars($lang->details['lbl_req_match_imdb']) . '</span>'
                : '<span class="rm-badge"><i class="fa-solid fa-font"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_req_match_title'], (int)$rm['match_score'])) . '</span>';
            $rm_meta  = [];
            if (!empty($rm['year'])) {
                $rm_meta[] = '<span><i class="fa-regular fa-calendar"></i>' . (int)$rm['year'] . '</span>';
            }
            if (!empty($rm['category_name'])) {
                $rm_meta[] = '<span><i class="fa-solid fa-tag"></i>' . htmlspecialchars_uni((string)$rm['category_name']) . '</span>';
            }
            $rm_meta[] = '<span><i class="fa-solid fa-users"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_req_votes'], (int)$rm['votes'])) . '</span>';
            if ((float)$rm['bounty'] > 0) {
                $rm_meta[] = '<span class="rm-bounty"><i class="fa-solid fa-coins"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_req_bounty'], number_format((float)$rm['bounty'], 1))) . '</span>';
            }

            $rm_rows .= '
            <div class="rm-item">
                <div class="rm-item__main">
                    <a class="rm-item__title" href="requests.php?action=view&amp;rid=' . $rm_rid . '">' . htmlspecialchars_uni((string)$rm['title']) . '</a>
                    <div class="rm-item__meta">' . $rm_badge . implode('', $rm_meta) . '</div>
                </div>
                <form method="post" action="requests.php?action=fill&amp;rid=' . $rm_rid . '" class="rm-item__form" onsubmit="return confirm(' . $rm_conf . ')">
                    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                    <input type="hidden" name="torrent_id" value="' . (int)$id . '">
                    <button type="submit" class="btn btn-sm btn-success st-pill"><i class="fa-solid fa-check me-1"></i>' . htmlspecialchars($lang->details['btn_req_fill']) . '</button>
                </form>
            </div>';
        }

        $RequestMatches = '
    <section class="st-card dt-rm">
        <div class="st-card__head">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-hand-holding-heart"></i></span>
            <div class="min-w-0">
                <div>' . htmlspecialchars($lang->details['sec_req_match']) . '</div>
                <div class="dt-rm__hint">' . htmlspecialchars($lang->details['hint_req_match']) . '</div>
            </div>
        </div>
        <div class="st-card__body rm-list">' . $rm_rows . '</div>
    </section>';
    }
}

// ── Main layout ─────────────────────────────────────────────────────────────
$ags_health    = getHealthPercentage($Torrent['seeders'], $Torrent['leechers']);
$ags_health_c  = getHealthColor($Torrent['seeders'], $Torrent['leechers']);

// Сколько бонусов даст сидирование этой раздачи (формула крона seedbonus)
require_once INC_PATH . '/functions_seedbonus_estimate.php';
$dt_bp_est = !empty($CURUSER['id']) ? sbe_estimate($Torrent, (int)$CURUSER['id']) : null;
$dt_bp     = $dt_bp_est !== null && $dt_bp_est['hourly'] > 0 ? sbe_render($dt_bp_est) : '';
$ags_numfiles  = (int)$Torrent['numfiles'];
$ags_files_lbl = htmlspecialchars(ags_fmt(ags_plural($ags_numfiles, $lang->details['lbl_files']), ts_nf($ags_numfiles)));

// Плитки: все цифры уже есть в строке раздачи — лишних запросов нет.
// [иконка, цвет, значение, подпись, атрибуты ссылки, доп. класс]
$dt_kpi_tiles = [
    ['fa-arrow-up',     'st-soft-success', ts_nf($Torrent['seeders']),         $lang->details['stat_seeders'],  'href="#peers" data-scroll-tab="peers"', ' scroll-to-tab'],
    ['fa-arrow-down',   'st-soft-danger',  ts_nf($Torrent['leechers']),        $lang->details['stat_leechers'], 'href="#peers" data-scroll-tab="peers"', ' scroll-to-tab'],
    ['fa-circle-check', 'st-soft-info',    ts_nf($Torrent['times_completed']), $lang->details['snatched'],      'href="viewsnatches.php?id=' . $id . '"', ''],
    ['fa-comments',     'st-soft-primary', ts_nf($Torrent['comments']),        $lang->details['comments'],      'href="#dt-comments"', ''],
];
$dt_kpis = '';
foreach ($dt_kpi_tiles as [$kpi_icon, $kpi_soft, $kpi_val, $kpi_lbl, $kpi_attrs, $kpi_class]) {
    $dt_kpis .= '
        <a ' . $kpi_attrs . ' class="st-kpi st-kpi--muted' . $kpi_class . '">
            <span class="st-ico ' . $kpi_soft . '"><i class="fa-solid ' . $kpi_icon . '"></i></span>
            <div class="min-w-0">
                <div class="st-kpi__val">' . $kpi_val . '</div>
                <div class="st-kpi__lbl">' . htmlspecialchars($kpi_lbl, ENT_QUOTES) . '</div>
            </div>
            <i class="fa-solid ' . $kpi_icon . ' st-kpi__bg" aria-hidden="true"></i>
        </a>';
}

// Для шапки и липкой полоски
$dt_torrent_tags = GetTorrentTags($Torrent);

// Технические чипы из MediaInfo (2160p, HDR, HEVC, Atmos) — перед чипами IMDb
$dt_tech_chips = '';
foreach ($dt_mi_chips as $dt_chip) {
    $dt_tech_chips .= '<span class="dt-chip dt-chip--tech">' . htmlspecialchars($dt_chip) . '</span>';
}
$imdb_chips = $dt_tech_chips . $imdb_chips;

// ── Сроки акций ──────────────────────────────────────────────────────────
// Единицы для счётчика (сервер и details.js): «2 д 5 ч»
$dt_units = [
    $lang->details['lbl_unit_d'] ?? 'd',
    $lang->details['lbl_unit_h'] ?? 'h',
    $lang->details['lbl_unit_m'] ?? 'm',
];
$dt_left = static function (int $secs) use ($dt_units): string {
    $secs = max(60, $secs);
    $d = intdiv($secs, 86400);
    $h = intdiv($secs % 86400, 3600);
    $m = intdiv($secs % 3600, 60);
    return $d > 0 ? $d . ' ' . $dt_units[0] . ($h ? ' ' . $h . ' ' . $dt_units[1] : '')
         : ($h > 0 ? $h . ' ' . $dt_units[1] . ($m ? ' ' . $m . ' ' . $dt_units[2] : '') : $m . ' ' . $dt_units[2]);
};
$dt_timer = static function (int $until, string $text, string $class) use ($dt_left, $dt_units, $dateformat, $timeformat): string {
    return '<span class="st-badge ' . $class . ' dt-timer" data-until="' . $until . '"'
         . ' data-units="' . htmlspecialchars(implode('|', $dt_units), ENT_QUOTES) . '"'
         . ' data-text="' . htmlspecialchars($text, ENT_QUOTES) . '"'
         . ' title="' . htmlspecialchars(my_datee($dateformat, $until) . ' ' . my_datee($timeformat, $until), ENT_QUOTES) . '">'
         . '<i class="fa-solid fa-hourglass-half"></i><span class="dt-timer__txt">'
         . htmlspecialchars(ags_fmt($text, $dt_left($until - TIMENOW))) . '</span></span>';
};

// Акция на раздаче: cron/torrentspromo.php снимает её через N дней после заливки
// (настройки expire* в admin/torrents_promo.php). promotion_time_type != 0 — бессрочная.
$dt_promo_badge = '';
if ((int)($Torrent['promotion_time_type'] ?? 0) === 0) {
    $dt_f = $Torrent['free'] === 'yes';
    $dt_s = $Torrent['silver'] === 'yes';
    $dt_2 = $Torrent['doubleupload'] === 'yes';
    $dt_3 = $Torrent['thirtypercent'] === 'yes';
    $dt_promo_days = match (true) {
        $dt_3 && !$dt_f && !$dt_s && !$dt_2 => $expirethirtypercentleech ?? 0,
        // «фрилич + ×2» подходит и под условие фрилича (free='yes'), снимается по меньшему сроку
        $dt_f && $dt_2 && !$dt_3            => min(array_filter([(float)($expirefree ?? 0), (float)($expiretwoupfree ?? 0)]) ?: [0]),
        $dt_f && !$dt_3                     => $expirefree ?? 0,
        $dt_s && $dt_2 && !$dt_f && !$dt_3  => $expiretwouphalfleech ?? 0,
        $dt_s && !$dt_f && !$dt_2 && !$dt_3 => $expirehalfleech ?? 0,
        $dt_2 && !$dt_f && !$dt_s && !$dt_3 => $expiretwoup ?? 0,
        default                             => 0,
    };
    $dt_promo_until = (int)$Torrent['added'] + (int)((float)$dt_promo_days * 86400);   // как в cron/torrentspromo.php
    if ((float)$dt_promo_days > 0 && $dt_promo_until > TIMENOW) {
        $dt_promo_badge = $dt_timer($dt_promo_until, $lang->details['lbl_promo_left'] ?? 'Promo ends in {1}', 'st-soft-warning');
    }
}

// Акция на весь сайт (cache/freeleech.php из admin/managesettings.php)
$dt_event = '';
$__FLSTYPE = $__F_START = $__F_END = '';
@include TSDIR . '/cache/freeleech.php';
$dt_ev_from  = is_numeric($__F_START) ? (int)$__F_START : (int)strtotime((string)$__F_START);
$dt_ev_until = is_numeric($__F_END)   ? (int)$__F_END   : (int)strtotime((string)$__F_END);
if ($__FLSTYPE !== '' && $dt_ev_from > 0 && TIMENOW >= $dt_ev_from && TIMENOW <= $dt_ev_until) {
    $dt_ev_names = [
        'freeleech'          => $lang->details['lbl_event_freeleech']   ?? 'Freeleech on all torrents',
        'silverleech'        => $lang->details['lbl_event_silverleech'] ?? '50% leech on all torrents',
        'thirtypercentleech' => $lang->details['lbl_event_thirty']      ?? '30% leech on all torrents',
        'doubleupload'       => $lang->details['lbl_event_double']      ?? '2x upload on all torrents',
    ];
    if (isset($dt_ev_names[$__FLSTYPE])) {
        $dt_event = '
    <div class="st-card dt-event">
        <span class="st-ico st-soft-success"><i class="fa-solid fa-gift"></i></span>
        <span class="dt-event__title">' . htmlspecialchars($dt_ev_names[$__FLSTYPE]) . '</span>
        ' . $dt_timer($dt_ev_until, $lang->details['lbl_event_left'] ?? 'ends in {1}', 'st-soft-success') . '
    </div>';
    }
}
$dt_sticky_poster = '<span class="dt-sticky__poster dt-sticky__poster--ico"><i class="' . ($torrent2['icon'] ?: 'fa-solid fa-layer-group') . '"></i></span>';
foreach ($images as $dt_img) {
    if (dt_local_image_state((string)$dt_img) !== 'missing') {
        $dt_sticky_poster = '<img class="dt-sticky__poster" src="' . htmlspecialchars_uni($dt_img) . '" alt="" loading="lazy">';
        break;
    }
}

// Файлы: поиск и сводка по типам
$dt_files_bar = '';
if (isset($tree) && is_array($tree) && $ags_numfiles > 1) {
    $dt_ft_stats = dtFileStats($tree);
    $dt_ft_other = ($dt_ft_stats['code'] ?? 0) + ($dt_ft_stats['exec'] ?? 0) + ($dt_ft_stats['default'] ?? 0);
    $dt_ft_list  = [
        ['video',   'fa-film',              'st-soft-danger',  $dt_ft_stats['video']   ?? 0, $lang->details['lbl_ft_video']   ?? 'Video'],
        ['audio',   'fa-music',             'st-soft-primary', $dt_ft_stats['audio']   ?? 0, $lang->details['lbl_ft_audio']   ?? 'Audio'],
        ['subs',    'fa-closed-captioning', 'st-soft-info',    $dt_ft_stats['subs']    ?? 0, $lang->details['lbl_ft_subs']    ?? 'Subtitles'],
        ['image',   'fa-image',             'st-soft-success', $dt_ft_stats['image']   ?? 0, $lang->details['lbl_ft_image']   ?? 'Images'],
        ['archive', 'fa-file-zipper',       'st-soft-warning', $dt_ft_stats['archive'] ?? 0, $lang->details['lbl_ft_archive'] ?? 'Archives'],
        ['doc',     'fa-file-lines',        'st-soft-muted',   $dt_ft_stats['doc']     ?? 0, $lang->details['lbl_ft_doc']     ?? 'Documents'],
        ['other',   'fa-file',              'st-soft-muted',   $dt_ft_other,                 $lang->details['lbl_ft_other']   ?? 'Other'],
    ];
    $dt_files_bar = '<div class="dt-fbar">'
                  . '<input type="search" class="form-control form-control-sm dt-fbar__search" id="dtFileSearch" oninput="dtFilterFiles(this)"'
                  . ' placeholder="' . htmlspecialchars($lang->details['ph_file_search'] ?? 'Search files…', ENT_QUOTES) . '">';
    foreach ($dt_ft_list as [, $dt_ft_icon, $dt_ft_soft, $dt_ft_n, $dt_ft_lbl]) {
        if ($dt_ft_n > 0) {
            $dt_files_bar .= '<span class="st-badge ' . $dt_ft_soft . '"><i class="fa-solid ' . $dt_ft_icon . '"></i>'
                           . htmlspecialchars($dt_ft_lbl) . ': ' . (int)$dt_ft_n . '</span>';
        }
    }
    $dt_files_bar .= '</div>';
}

$details = '
<div id="torrent_details" class="container-md st-page dt-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="dt-crumbs">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/"><i class="fa-solid fa-house me-1"></i>' . htmlspecialchars($lang->details['nav_home'], ENT_QUOTES) . '</a>
            </li>
            <li class="breadcrumb-item">
                <a href="browse.php"><i class="fa-solid fa-table-cells-large me-1"></i>' . htmlspecialchars($lang->details['nav_browse'], ENT_QUOTES) . '</a>
            </li>
            <li class="breadcrumb-item active text-truncate" aria-current="page" title="' . htmlspecialchars_uni($Torrent['name']) . '">'
                . htmlspecialchars_uni(mb_strlen($Torrent['name']) > 60
                    ? mb_substr($Torrent['name'], 0, 60) . '…'
                    : $Torrent['name']) . '
            </li>
        </ol>
    </nav>

    ' . $warning_message . '
    ' . $dt_event . '

    <!-- Header -->
    <header class="st-card st-head dt-head">
        <div class="st-head__icon st-soft-primary"><i class="' . ($torrent2['icon'] ?: 'fa-solid fa-layer-group') . '"></i></div>

        <div class="dt-head__body">
            <h1 class="st-title">
                <span class="dt-head__tags">' . $dt_torrent_tags . '</span>
                ' . htmlspecialchars_uni($Torrent['name']) . '
            </h1>

            <div class="st-head__meta">
                ' . $dt_promo_badge . '
                <span class="st-badge st-soft-muted" title="' . htmlspecialchars($lang->details['tip_torrent_id'], ENT_QUOTES) . '"><i class="fa-solid fa-hashtag"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_id'], (int)$id)) . '</span>
                <span class="st-badge dt-soft-purple" title="' . htmlspecialchars($lang->details['size'], ENT_QUOTES) . '"><i class="fa-solid fa-hard-drive"></i>' . mksize($Torrent['size']) . '</span>
                <span class="st-badge st-soft-warning" title="' . htmlspecialchars($lang->details['tip_numfiles'], ENT_QUOTES) . '"><i class="fa-solid fa-file"></i>' . $ags_files_lbl . '</span>
                <span class="st-badge st-soft-muted" title="' . htmlspecialchars($lang->details['lbl_uploaded'], ENT_QUOTES) . '"><i class="fa-solid fa-calendar-check"></i>' . my_datee('relative', $Torrent['added']) . '</span>
                <span class="st-badge text-bg-' . $ags_health_c . '" title="' . htmlspecialchars($lang->details['tip_health'], ENT_QUOTES) . '"><i class="fa-solid fa-heart-pulse"></i>' . htmlspecialchars(ags_fmt($lang->details['lbl_health'], $ags_health)) . '</span>
                ' . ($already_snatched ? '
                <span class="st-badge st-soft-info" title="' . htmlspecialchars($lang->details['tip_snatched_by_you'], ENT_QUOTES) . '"><i class="fa-solid fa-circle-check"></i>' . htmlspecialchars($lang->details['badge_already_downloaded'], ENT_QUOTES) . '</span>' : '') . '
            </div>
            ' . ($imdb_chips !== '' ? '<div class="dt-chips">' . $imdb_chips . '</div>' : '') . '
        </div>

        <div class="dt-head__actions">
            ' . $act . '
            <a href="' . get_download_link($id) . '" class="btn btn-primary st-pill"
               title="' . htmlspecialchars($lang->details['dltorrent'], ENT_QUOTES) . '">
                <i class="fa-solid fa-cloud-arrow-down me-2"></i>' . htmlspecialchars($lang->details['download'], ENT_QUOTES) . '
            </a>
            <button type="button" class="btn btn-outline-secondary st-pill dt-btn-ico report-btn"
                    data-bs-toggle="modal" data-bs-target="#reportModal"
                    data-report-type="torrent"
                    data-report-id="' . $id . '"
                    data-report-userid="' . $Torrent['owner'] . '"
                    data-report-name="' . htmlspecialchars($Torrent['name'] ?? $lang->details['lbl_torrent_fallback']) . '"
                    title="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '"
                    aria-label="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '">
                <i class="fa-solid fa-flag"></i>
            </button>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary st-pill dt-btn-ico"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="' . htmlspecialchars($lang->details['tip_more_options'], ENT_QUOTES) . '"
                        aria-label="' . htmlspecialchars($lang->details['tip_more_options'], ENT_QUOTES) . '">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    ' . $magnetButton . '
                    <li>
                        <a class="dropdown-item scroll-to-tab" href="#info" data-scroll-tab="info">
                            <i class="fa-solid fa-circle-info me-2"></i>' . htmlspecialchars($lang->details['tab_info'], ENT_QUOTES) . '
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item scroll-to-tab" href="#files" data-scroll-tab="files">
                            <i class="fa-solid fa-folder me-2"></i>' . htmlspecialchars($lang->details['menu_filelist'], ENT_QUOTES) . '
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item scroll-to-tab" href="#peers" data-scroll-tab="peers">
                            <i class="fa-solid fa-users me-2"></i>' . htmlspecialchars($lang->details['menu_peers'], ENT_QUOTES) . '
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    ' . $RequestMatches . '

    <!-- Claim / posters / health -->
    <section class="st-card dt-overview">
        ' . $claimBox . '
        ' . ($modal_images ? '
        <div class="dt-posters">' . $modal_images . '</div>' : '') . '
        <div class="dt-health">
            <span class="dt-health__lbl"><i class="fa-solid fa-heart-pulse"></i>' . htmlspecialchars($lang->details['lbl_torrent_health'], ENT_QUOTES) . '</span>
            <div class="progress dt-health__bar" role="progressbar" aria-valuenow="' . $ags_health . '" aria-valuemin="0" aria-valuemax="100"
                 aria-label="' . htmlspecialchars($lang->details['lbl_torrent_health'], ENT_QUOTES) . '">
                <div class="progress-bar bg-' . $ags_health_c . '" style="width: ' . $ags_health . '%"></div>
            </div>
            <span class="dt-health__val">' . $ags_health . '%</span>
        </div>
        ' . $dt_bp . '
    </section>

    <!-- KPI tiles -->
    <div class="dt-kpis">' . $dt_kpis . '
    </div>

    ' . ($dt_shot_strip !== '' ? '
    <!-- Лента скриншотов: клик открывает просмотр (details.js) -->
    <section class="st-card dt-strip">
        <span class="dt-strip__head"><i class="fa-solid fa-images"></i>' . htmlspecialchars($lang->details['tab_screens'], ENT_QUOTES) . ' <b>' . $dt_shot_n . '</b></span>
        <div class="dt-strip__list">' . $dt_shot_strip . '</div>
    </section>' : '') . '

    <!-- Tabs -->
    <section class="st-card">
        <div class="dt-tabs-wrap">
            <ul class="nav dt-tabs" id="torrentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab"
                            data-bs-target="#info" type="button" role="tab">
                        <i class="fa-solid fa-circle-info"></i>' . htmlspecialchars($lang->details['tab_info'], ENT_QUOTES) . '
                    </button>
                </li>
                ' . $screenTab . '
                ' . $nfoTab . '
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="files-tab" data-bs-toggle="tab"
                            data-bs-target="#files" type="button" role="tab">
                        <i class="fa-solid fa-folder"></i>' . htmlspecialchars(ags_fmt($lang->details['tab_files'], ts_nf($Torrent['numfiles']))) . '
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="peers-tab" data-bs-toggle="tab"
                            data-bs-target="#peers" type="button" role="tab">
                        <i class="fa-solid fa-users"></i>' . htmlspecialchars(ags_fmt($lang->details['tab_peers'], ts_nf($Torrent['seeders'] + $Torrent['leechers']))) . '
                    </button>
                </li>
                ' . ((int)$Torrent['seeders'] === 0 && $CURUSER ? '
                <li class="nav-item ms-auto">
                    <form method="post" action="' . $BASEURL . '/takereseed.php" class="m-0"
                          onsubmit="return confirm(' . htmlspecialchars(json_encode($lang->details['confirm_reseed'], $ags_json_flags), ENT_QUOTES) . ')">
                        <input type="hidden" name="my_post_key" value="' . htmlspecialchars((string)($mybb->post_code ?? ''), ENT_QUOTES) . '">
                        <input type="hidden" name="reseedid" value="' . (int)$Torrent['id'] . '">
                        <button type="submit" class="btn btn-sm btn-outline-warning st-pill text-nowrap">
                            <i class="fa-solid fa-bullhorn me-1"></i>' . htmlspecialchars($lang->details['btn_request_reseed'], ENT_QUOTES) . '
                        </button>
                    </form>
                </li>' : '') . '
            </ul>
        </div>

        <div class="st-card__body">
            <div class="tab-content" id="torrentTabsContent">

                <!-- Info -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="dt-info">
                        <div class="st-rows">
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-calendar"></i>' . htmlspecialchars($lang->details['lbl_uploaded'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . my_datee($dateformat, $Torrent['added']) . ' <small class="text-body-secondary ms-1">' . my_datee($timeformat, $Torrent['added']) . '</small></span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-tag"></i>' . htmlspecialchars($lang->details['lbl_category'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . $torrent2['categoryname'] . '</span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-hard-drive"></i>' . htmlspecialchars($lang->details['size'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . mksize($Torrent['size']) . '</span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-fingerprint"></i>' . htmlspecialchars($lang->details['infohash'], ENT_QUOTES) . '</span>
                                <span class="st-row__val dt-hash">' . htmlspecialchars($Torrent['info_hash'] ?? $lang->details['na']) . '</span>
                            </div>
                        </div>

                        <div class="st-rows">
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-circle-check"></i>' . htmlspecialchars($lang->details['snatched'], ENT_QUOTES) . '</span>
                                <span class="st-row__val"><a href="viewsnatches.php?id=' . $id . '">' . ts_nf($Torrent['times_completed']) . '</a></span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-eye"></i>' . htmlspecialchars($lang->details['views'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . ts_nf($Torrent['hits']) . '</span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-comments"></i>' . htmlspecialchars($lang->details['comments'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . ts_nf($Torrent['comments']) . '</span>
                            </div>
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-user"></i>' . htmlspecialchars($lang->details['uppedby'], ENT_QUOTES) . '</span>
                                <span class="st-row__val">' . $username . '</span>
                            </div>
                            ' . ($show_manage != '' ? '
                            <div class="st-row">
                                <span class="st-row__lbl"><i class="fa-solid fa-gear"></i>' . htmlspecialchars($lang->details['lbl_manage'], ENT_QUOTES) . '</span>
                                <span class="st-row__val"><div class="dt-manage">' . $show_manage . '</div></span>
                            </div>' : '') . '
                        </div>
                    </div>

                    ' . ($keywords ? '
                    <div class="dt-tags">
                        <h6 class="dt-pane-title"><i class="fa-solid fa-tags"></i>' . htmlspecialchars($lang->details['sec_tags'], ENT_QUOTES) . '</h6>
                        <div class="dt-tags__list">' . $keywords . '</div>
                    </div>' : '') . '

                    ' . $rating_html . '
                </div>

                <!-- Files -->
                <div class="tab-pane fade" id="files" role="tabpanel">
                    <div class="dt-pane-head">
                        <h6 class="dt-pane-title"><i class="fa-solid fa-folder-tree"></i>' . htmlspecialchars($lang->details['sec_file_structure'], ENT_QUOTES) . '</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-primary st-pill" onclick="expandAllFiles()">
                                <i class="fa-solid fa-plus me-1"></i>' . htmlspecialchars($lang->details['btn_expand_all'], ENT_QUOTES) . '
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="collapseAllFiles()">
                                <i class="fa-solid fa-minus me-1"></i>' . htmlspecialchars($lang->details['btn_collapse_all'], ENT_QUOTES) . '
                            </button>
                        </div>
                    </div>
                    ' . $dt_files_bar . '
                    ' . (isset($tree) && $tree !== null
                        ? '<div class="file-tree">' . renderAccordion($tree) . '</div>'
                        : '<div class="alert alert-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>' . htmlspecialchars($lang->details['err_torrent_file_missing'], ENT_QUOTES) . '</div>') . '
                </div>

                ' . $screenContent . '
                ' . $nfoContent . '

                <!-- Peers -->
                <div class="tab-pane fade" id="peers" role="tabpanel">
                    ' . $peerstable . '
                </div>
            </div>
        </div>
    </section>

    ' . $dt_mi_card . '

    ' . $dt_descr_card . '

    ' . $ShowTLINK . '
    ' . $SimilarTorrents . '

    <!-- Comments -->
    <div class="dt-comments" id="dt-comments">
        ' . $showcommenttable . '
    </div>

    <!-- Липкая полоска: появляется, когда шапка ушла за экран (details.js) -->
    <div class="dt-sticky" id="dtSticky" aria-hidden="true">
        <div class="container-md dt-sticky__in">
            ' . $dt_sticky_poster . '
            <div class="dt-sticky__main">
                <div class="dt-sticky__title">
                    <span class="dt-sticky__tags">' . $dt_torrent_tags . '</span>' . htmlspecialchars_uni($Torrent['name']) . '
                </div>
                ' . ($imdb_chips !== '' ? '<div class="dt-chips">' . $imdb_chips . '</div>' : '') . '
                <div class="dt-sticky__meta">
                    <span><i class="fa-solid fa-hard-drive"></i>' . mksize($Torrent['size']) . '</span>
                    <span>' . $torrent2['categoryname'] . '</span>
                    <span><i class="fa-solid fa-file"></i>' . $ags_files_lbl . '</span>
                </div>
            </div>
            <div class="dt-sticky__side">
                <div class="dt-sticky__by">
                    ' . htmlspecialchars($lang->details['uppedby'], ENT_QUOTES) . ': ' . $username . ' · ' . my_datee('relative', $Torrent['added']) . '
                </div>
                <div class="dt-sticky__actions">
                    <span class="dt-sticky__stat text-success"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($Torrent['seeders']) . '</span>
                    <span class="dt-sticky__stat text-danger"><i class="fa-solid fa-arrow-down"></i>' . ts_nf($Torrent['leechers']) . '</span>
                    <button type="button" class="dt-sticky__ico report-btn"
                            data-bs-toggle="modal" data-bs-target="#reportModal"
                            data-report-type="torrent"
                            data-report-id="' . $id . '"
                            data-report-userid="' . $Torrent['owner'] . '"
                            data-report-name="' . htmlspecialchars($Torrent['name'] ?? $lang->details['lbl_torrent_fallback']) . '"
                            title="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '"
                            aria-label="' . htmlspecialchars($lang->details['tip_report_torrent'], ENT_QUOTES) . '"><i class="fa-solid fa-flag"></i></button>
                    <button type="button" class="dt-sticky__ico" id="dtStickyBm" aria-label="' . htmlspecialchars($lang->details['bm_title_off'] ?? 'Bookmark', ENT_QUOTES) . '"></button>
                    <a href="' . get_download_link($id) . '" class="btn btn-sm btn-primary st-pill"><i class="fa-solid fa-cloud-arrow-down me-1"></i>' . htmlspecialchars($lang->details['download'], ENT_QUOTES) . '</a>
                </div>
            </div>
        </div>
    </div>
</div>

' . ($magnetModal ?? '') . '
' . ($screenTab !== '' ? '
<div class="modal fade dt-gallery" id="dtGallery" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen m-0">
        <div class="modal-content">
            <div class="dt-gallery__top">
                <span id="dtGalleryCount" aria-live="polite"></span>
                <button type="button" class="dt-gallery__btn" data-bs-dismiss="modal" aria-label="' . htmlspecialchars($lang->details['comment_not_found_close'] ?? 'Close', ENT_QUOTES) . '"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="dt-gallery__stage">
                <button type="button" class="dt-gallery__btn" id="dtGalleryPrev" aria-label="' . htmlspecialchars($lang->global['previous'] ?? 'Previous', ENT_QUOTES) . '"><i class="fa-solid fa-chevron-left"></i></button>
                <img id="dtGalleryImg" src="data:," alt="">
                <button type="button" class="dt-gallery__btn" id="dtGalleryNext" aria-label="' . htmlspecialchars($lang->global['next'] ?? 'Next', ENT_QUOTES) . '"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="dt-gallery__thumbs" id="dtGalleryThumbs"></div>
        </div>
    </div>
</div>' : '') . '';


echo '
' . ($is_mod ? '
<script type="text/javascript">
    l_updated = "' . $lang->global['imgupdated'] . '";
    l_refresh = "' . $lang->global['refresh'] . '";
</script>' : '');

echo $details;

stdfoot();
