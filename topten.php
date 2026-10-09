<?php

declare(strict_types=1);

// display_errors НЕ включаем: подробности ошибок показывает только
// error_handler.php и только staff.
define("THIS_SCRIPT", "topten.php");
require "./global.php";
$lang->load("topten");
define("T_VERSION", "2.2");

include_once INC_PATH . "/functions_ratio.php";
require_once INC_PATH . "/functions_icons.php";

$is_mod     = is_mod($usergroups);
$xbt_active = "no";
$notin      = "7,6,5";

/**
 * Подстановка {1}, {2}… в строку ланга. $lang->load() превращает {1} в %1$s,
 * поэтому подставляются оба формата.
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

// ── Входные параметры ───────────────────────────────────────────────────────
$type = isset($_GET["type"]) ? (int)$_GET["type"] : 1;
if (!in_array($type, [1, 2, 3, 4, 5, 6, 7, 8, 9], true)) {
    $type = 1;
}
$limit   = isset($_GET["lim"]) ? (int)$_GET["lim"] : false;
$subtype = (isset($_GET["subtype"]) && is_string($_GET["subtype"])) ? $_GET["subtype"] : false;

$pu = (bool)$is_mod;
if (!$pu) {
    $limit = 10;
}

// ── Вкладки: [type, иконка FA, ключ ланга] ──────────────────────────────────
$tt_tabs = [
    [1, 'fa-users',               'tab_users'],
    [2, 'fa-photo-film',          'tab_torrents'],
    [3, 'fa-earth-europe',        'tab_countries'],
    [4, 'fa-server',              'tab_peers'],
    [5, 'fa-tags',                'tab_categories'],
    [6, 'fa-coins',               'tab_seedbonus'],
    [8, 'fa-fire',                'tab_hot'],
    [9, 'fa-hand-holding-heart',  'tab_contributors'],
    [7, 'fa-comments',            'tab_forum'],
];

// ── KPI (один запрос) ───────────────────────────────────────────────────────
$kpi = $db->fetch_array($db->sql_query_prepared(
    "SELECT (SELECT COUNT(*) FROM users WHERE enabled = 'yes')       AS users_cnt,
            (SELECT COUNT(*) FROM torrents)                          AS torrents_cnt,
            (SELECT COUNT(*) FROM peers)                             AS peers_cnt,
            (SELECT COALESCE(SUM(seeder = 'yes'), 0) FROM peers)     AS seeders_cnt,
            (SELECT COALESCE(SUM(uploaded), 0) FROM users)           AS up_total,
            (SELECT COALESCE(SUM(downloaded), 0) FROM users)         AS down_total",
    []
));
$kpi_users    = (int)($kpi['users_cnt'] ?? 0);
$kpi_torrents = (int)($kpi['torrents_cnt'] ?? 0);
$kpi_peers    = (int)($kpi['peers_cnt'] ?? 0);
$kpi_seeders  = (int)($kpi['seeders_cnt'] ?? 0);
$kpi_leechers = max(0, $kpi_peers - $kpi_seeders);
$kpi_up       = (float)($kpi['up_total'] ?? 0);
$kpi_down     = (float)($kpi['down_total'] ?? 0);


function get_thread_link(int|string $tid, int|string $page = 0, string $action = ''): string
{
    // strict_types: str_replace() принимает только строки, а tid/page
    // приходят из БД как native int (prepared statements).
    $tid  = (string)(int)$tid;
    $page = (int)$page;

    if ($action) {
        $link = str_replace("{action}", $action, THREAD_URL_ACTION);
    } else {
        $link = $page > 1 ? THREAD_URL_PAGED : THREAD_URL;
    }
    $link = str_replace("{tid}", $tid, $link);
    if ($page > 1) {
        $link = str_replace("{page}", (string)$page, $link);
    }
    return htmlspecialchars_uni($link);
}


// ═════════════════════════════════════════════════════════════════════════════
//  UI-хелперы
// ═════════════════════════════════════════════════════════════════════════════

/** Строка ланга с фолбэком (для новых ключей). */
function tt_l(string $key, string $fallback = ''): string
{
    global $lang;
    return (string)($lang->topten[$key] ?? $fallback);
}

function tt_rank(int $n): string
{
    return match ($n) {
        1       => '<span class="tt-rank tt-rank--gold"><i class="fa-solid fa-trophy"></i></span>',
        2       => '<span class="tt-rank tt-rank--silver"><i class="fa-solid fa-trophy"></i></span>',
        3       => '<span class="tt-rank tt-rank--bronze"><i class="fa-solid fa-trophy"></i></span>',
        default => '<span class="tt-rank">' . $n . '</span>',
    };
}

function tt_row(int $n): string
{
    return $n <= 3 ? '<tr class="tt-row--' . $n . '">' : '<tr>';
}

function tt_th(string $label, string $icon = '', string $class = '', string $width = ''): string
{
    $style = $width !== '' ? ' style="width:' . $width . '"' : '';
    $ico   = $icon !== '' ? '<i class="fa-solid ' . $icon . '"></i>' : '';
    return '<th class="' . $class . '"' . $style . '>' . $ico . $label . '</th>';
}

/** Ссылки «Топ-N» для staff. */
function tt_more(int $type, string $sub, array $lims): string
{
    global $pu, $limit, $lang;
    if (!$pu || $limit != 10) {
        return '';
    }
    $html = '';
    foreach ($lims as $l) {
        $href  = 'topten.php?type=' . $type . '&amp;lim=' . (int)$l . ($sub !== '' ? '&amp;subtype=' . $sub : '');
        $html .= '<a class="tt-chip" href="' . $href . '"><i class="fa-solid fa-list-ol"></i>' . $lang->topten['lnk_top' . (int)$l] . '</a>';
    }
    return $html;
}

function tt_card_open(string $icon, string $tone, string $title, string $more, string $thead): void
{
    global $lang;
    echo '
    <div class="tt-card tt-section">
        <div class="tt-toolbar">
            <div class="tt-toolbar__title">
                <span class="tt-toolbar__icon tt-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>
                <span>' . $title . '</span>
            </div>
            <div class="tt-toolbar__side">
                ' . $more . '
                <span class="tt-badge tt-soft-' . $tone . '"><i class="fa-solid fa-ranking-star"></i>' . ags_fmt($lang->topten["badge_top"], $GLOBALS['limit'] ?? 10) . '</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table tt-table">
                <thead><tr>' . $thead . '</tr></thead>
                <tbody>';
}

function tt_card_close(int $rows, int $cols): void
{
    if ($rows === 0) {
        echo '<tr><td colspan="' . $cols . '">
                <div class="tt-empty">
                    <div class="tt-empty__icon tt-soft-muted"><i class="fa-solid fa-inbox"></i></div>
                    <div class="text-body-secondary">' . tt_l('msg_no_data', 'No data yet') . '</div>
                </div>
              </td></tr>';
    }
    echo '</tbody></table></div></div>';
}

/** Тонкая полоска «доля от лидера». */
function tt_bar(float $value, float $max, string $tone): string
{
    $pct = $max > 0 ? max(2, min(100, (int)round($value / $max * 100))) : 0;
    return '<div class="progress tt-progress"><div class="progress-bar tt-bg-' . $tone . '" style="width:' . $pct . '%"></div></div>';
}

/** Пользователь: аватар + ник + значки + ID. */
function tt_user_cell(array $a): string
{
    $av    = format_avatar((string)($a['avatar'] ?? ''), (string)($a['avatardimensions'] ?? ''));
    $icons = array_key_exists('warned', $a) ? get_user_icons($a) : '';
    return '<div class="d-flex align-items-center gap-2 tt-user">
                <img class="tt-avatar" src="' . $av['image'] . '" alt="" loading="lazy">
                <div class="lh-sm">
                    <a href="' . get_profile_link((int)$a['userid']) . '">'
                        . format_name(htmlspecialchars_uni((string)$a['username']), (int)$a['usergroup']) .
                    '</a>' . $icons . '
                    <div class="tt-meta"><i class="fa-solid fa-id-badge"></i>' . (int)$a['userid'] . '</div>
                </div>
            </div>';
}

/** Торрент: постер (или иконка магнита) + название + мета. */
function tt_torrent_cell(array $a, string $meta = ''): string
{
    $link   = get_torrent_link($a['id']);
    $poster = trim((string)($a['t_image'] ?? ''));
    $thumb  = $poster !== ''
        ? '<a href="' . $link . '" tabindex="-1"><img src="' . htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') . '" alt="" class="tt-poster" loading="lazy"'
          . ' onerror="this.parentNode.outerHTML=\'<span class=&quot;tt-torrent-ico tt-soft-danger&quot;><i class=&quot;fa-solid fa-magnet&quot;></i></span>\'"></a>'
        : '<span class="tt-torrent-ico tt-soft-danger"><i class="fa-solid fa-magnet"></i></span>';

    return '<div class="d-flex align-items-center gap-2">' . $thumb . '
                <div class="lh-sm">
                    <a href="' . $link . '" class="tt-link">' . cutename($a['name'], 55) . '</a>
                    <div class="tt-meta"><i class="fa-solid fa-hashtag"></i>' . (int)$a['id']
                        . '<span class="ms-2"><i class="fa-solid fa-hard-drive"></i>' . mksize((float)($a['size'] ?? 0)) . '</span>'
                        . $meta . '</div>
                </div>
            </div>';
}

/** Бейдж рейтинга в soft-цветах (как в snatched_torrents). */
function tt_ratio(int|float $up, int|float $down): string
{
    if ($down > 0) {
        $r    = $up / $down;
        $soft = $r >= 1 ? 'tt-soft-success' : ($r >= 0.5 ? 'tt-soft-warning' : 'tt-soft-danger');
        return '<span class="tt-badge ' . $soft . '">' . number_format((float)$r, 2) . '</span>';
    }
    return $up > 0
        ? '<span class="tt-badge tt-soft-success"><i class="fa-solid fa-infinity"></i></span>'
        : '<span class="tt-badge tt-soft-muted">—</span>';
}


// ═════════════════════════════════════════════════════════════════════════════
//  Вывод
// ═════════════════════════════════════════════════════════════════════════════
stdhead($lang->topten["page_title"]);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/topten.css?v=' . T_VERSION . '">';
?>

<div class="container mt-3 py-4 tt-page">

    <!-- Header -->
    <div class="tt-card tt-head mb-3">
        <div class="tt-head__icon"><i class="fa-solid fa-trophy"></i></div>
        <div class="flex-grow-1">
            <h1 class="tt-title"><?php echo $lang->topten["page_title"]; ?></h1>
            <p><?php echo $lang->topten["page_subtitle"]; ?></p>
        </div>
        <span class="tt-badge tt-soft-warning d-none d-md-inline-flex"><i class="fa-solid fa-star"></i><?php echo $lang->topten["page_eyebrow"]; ?></span>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="tt-card tt-kpi">
                <div class="tt-kpi__icon tt-soft-primary"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="tt-kpi__value"><?php echo number_format($kpi_users); ?></div>
                    <div class="tt-kpi__label"><?php echo tt_l('kpi_users', 'Members'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tt-card tt-kpi">
                <div class="tt-kpi__icon tt-soft-danger"><i class="fa-solid fa-magnet"></i></div>
                <div>
                    <div class="tt-kpi__value"><?php echo number_format($kpi_torrents); ?></div>
                    <div class="tt-kpi__label"><?php echo tt_l('kpi_torrents', 'Torrents'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tt-card tt-kpi">
                <div class="tt-kpi__icon tt-soft-success"><i class="fa-solid fa-seedling"></i></div>
                <div>
                    <div class="tt-kpi__value"><?php echo number_format($kpi_peers); ?></div>
                    <div class="tt-kpi__label"><?php echo tt_l('kpi_peers', 'Peers'); ?></div>
                    <div class="tt-kpi__sub">
                        <i class="fa-solid fa-arrow-up text-success"></i> <?php echo number_format($kpi_seeders); ?>
                        <i class="fa-solid fa-arrow-down text-danger ms-2"></i> <?php echo number_format($kpi_leechers); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="tt-card tt-kpi">
                <div class="tt-kpi__icon tt-soft-warning"><i class="fa-solid fa-right-left"></i></div>
                <div>
                    <div class="tt-kpi__value tt-kpi__value--sm">
                        <i class="fa-solid fa-arrow-up text-success tt-ico-sm"></i> <?php echo mksize($kpi_up); ?>
                    </div>
                    <div class="tt-kpi__sub">
                        <i class="fa-solid fa-arrow-down text-danger"></i> <?php echo ags_fmt(tt_l('kpi_downloaded', '{1} downloaded'), mksize($kpi_down)); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="tt-card tt-tabs-card mb-3">
        <ul class="tt-tabs">
        <?php foreach ($tt_tabs as [$tid, $ticon, $tkey]): $active = $type === $tid; ?>
            <li>
                <a class="tt-tab<?php echo $active ? ' active' : ''; ?>" href="topten.php?type=<?php echo $tid; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-solid <?php echo $ticon; ?>"></i><span class="tt-tab__label"><?php echo $lang->topten[$tkey]; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </nav>

<?php

// ═════════════════════════════════════════════════════════════════════════════
//  Разделы
// ═════════════════════════════════════════════════════════════════════════════

$user_fields = "u.donor, u.enabled, u.warned, u.leechwarn";

if ($type === 1) {
    if (!$limit || $limit > 250) {
        $limit = 10;
    }

    $mainquery = "SELECT u.id as userid, u.username, u.usergroup, u.added, u.uploaded, u.downloaded,
              u.uploaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(u.added)) AS upspeed,
              u.downloaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(u.added)) AS downspeed,
              u.avatar, u.avatardimensions, {$user_fields},
              g.namestyle, g.canstaffpanel, g.issupermod, g.cansettingspanel
              FROM users u
              LEFT JOIN usergroups g ON (u.usergroup=g.gid)
              WHERE u.enabled = 'yes' AND u.usergroup NOT IN (" . $notin . ")";

    // subtype => [иконка, тон, ключ ланга, ORDER BY, доп. WHERE]
    $sections = [
        'ul'  => ['fa-cloud-arrow-up',   'success', 'sec_users_ul',  'uploaded DESC', ''],
        'dl'  => ['fa-cloud-arrow-down', 'danger',  'sec_users_dl',  'downloaded DESC', ''],
        'uls' => ['fa-gauge-high',       'info',    'sec_users_uls', 'upspeed DESC', ''],
        'dls' => ['fa-gauge',            'warning', 'sec_users_dls', 'downspeed DESC', ''],
        'bsh' => ['fa-thumbs-up',        'success', 'sec_users_bsh', 'uploaded / downloaded DESC', ' AND downloaded > 1073741824'],
        'wsh' => ['fa-thumbs-down',      'danger',  'sec_users_wsh', 'uploaded / downloaded ASC, downloaded DESC', ' AND downloaded > 1073741824'],
    ];
    foreach ($sections as $sub => [$icon, $tone, $key, $order, $extra]) {
        if ($limit == 10 || $subtype === $sub) {
            $r = $db->sql_query_prepared($mainquery . $extra . " ORDER BY " . $order . " LIMIT ?", [(int)$limit]);
            usertable($r, ags_fmt($lang->topten[$key], $limit), tt_more(1, $sub, [100, 250]), $icon, $tone);
        }
    }
}

elseif ($type === 2) {
    if (!$limit || $limit > 50) {
        $limit = 10;
    }

    $join  = $xbt_active === "yes" ? "xbt_files_users AS p ON t.id = p.fid" : "peers AS p ON t.id = p.torrent";
    $leech = $xbt_active === "yes" ? "p.`left` > 0" : "p.seeder = 'no'";

    // subtype => [иконка, тон, ключ ланга, WHERE, ORDER BY]
    $sections = [
        'act' => ['fa-bolt',                 'primary', 'sec_torrents_act', $leech, 'seeders + leechers DESC, seeders DESC, added ASC'],
        'sna' => ['fa-download',             'info',    'sec_torrents_sna', 't.times_completed > 0', 'times_completed DESC, added ASC'],
        'mdt' => ['fa-database',             'purple',  'sec_torrents_mdt', 'times_completed > 0', 'data DESC, added ASC'],
        'bse' => ['fa-seedling',             'success', 'sec_torrents_bse', 'seeders >= 5', 'seeders DESC, seeders+leechers DESC, added ASC'],
        'wse' => ['fa-triangle-exclamation', 'danger',  'sec_torrents_wse', $leech . ' AND leechers >= 5 AND times_completed > 0', 'seeders / leechers ASC, leechers DESC'],
    ];
    foreach ($sections as $sub => [$icon, $tone, $key, $where, $order]) {
        if ($limit == 10 || $subtype === $sub) {
            $r = $db->sql_query_prepared(
                "SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data
                 FROM torrents AS t LEFT JOIN {$join}
                 WHERE {$where}
                 GROUP BY t.id
                 ORDER BY {$order}
                 LIMIT ?",
                [(int)$limit]
            );
            _torrenttable($r, ags_fmt($lang->topten[$key], $limit), tt_more(2, $sub, [25, 50]), $icon, $tone);
        }
    }

    if ($limit == 10 || $subtype === "mcom") {
        $r = $db->sql_query_prepared(
            "SELECT t.*, COUNT(c.id) AS comment_count
             FROM torrents t
             LEFT JOIN comments c ON t.id = c.torrent
             GROUP BY t.id
             ORDER BY comment_count DESC
             LIMIT ?",
            [(int)$limit]
        );
        mostcommentedtable($r, ags_fmt($lang->topten["sec_torrents_mcom"], $limit), tt_more(2, 'mcom', [25]));
    }
}

elseif ($type === 3) {
    if (!$limit || $limit > 50) {
        $limit = 10;
    }

    $country_sections = [
        'us' => ["SELECT c.name, c.flagpic, COUNT(u.country) AS v
                  FROM countries AS c
                  LEFT JOIN users AS u ON u.country = c.id
                  GROUP BY c.id, c.name, c.flagpic
                  ORDER BY v DESC LIMIT ?", 'sec_countries_us', 'Users'],
        'ul' => ["SELECT c.name, c.flagpic, SUM(u.uploaded) AS v
                  FROM users AS u
                  LEFT JOIN countries AS c ON u.country = c.id
                  WHERE u.enabled = 'yes'
                  GROUP BY c.id, c.name, c.flagpic
                  ORDER BY v DESC LIMIT ?", 'sec_countries_ul', 'Uploaded'],
        'avg' => ["SELECT c.name, c.flagpic, SUM(u.uploaded)/COUNT(u.id) AS v
                  FROM users AS u
                  LEFT JOIN countries AS c ON u.country = c.id
                  WHERE u.enabled = 'yes'
                  GROUP BY c.id, c.name, c.flagpic
                  HAVING SUM(u.uploaded) > 1099511627776 AND COUNT(u.id) >= 100
                  ORDER BY v DESC LIMIT ?", 'sec_countries_avg', 'Average'],
        'r' => ["SELECT c.name, c.flagpic, SUM(u.uploaded)/SUM(u.downloaded) AS v
                  FROM users AS u
                  LEFT JOIN countries AS c ON u.country = c.id
                  WHERE u.enabled = 'yes'
                  GROUP BY c.id, c.name, c.flagpic
                  HAVING SUM(u.uploaded) > 1099511627776
                     AND SUM(u.downloaded) > 1099511627776
                     AND COUNT(u.id) >= 100
                  ORDER BY v DESC LIMIT ?", 'sec_countries_r', 'Ratio'],
    ];
    foreach ($country_sections as $sub => [$sql, $key, $what]) {
        if ($limit == 10 || $subtype === $sub) {
            $r = $db->sql_query_prepared($sql, [(int)$limit]);
            if ($r) {
                countriestable($r, ags_fmt($lang->topten[$key], $limit), tt_more(3, $sub, [25]), $what);
            }
        }
    }
}

elseif ($type === 4) {
    if (!$limit || $limit > 250) {
        $limit = 10;
    }
    if ($xbt_active === "yes") {
        echo '<div class="tt-card">
                <div class="tt-empty">
                    <div class="tt-empty__icon tt-soft-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="text-body-secondary">' . $lang->topten["msg_not_available"] . '</div>
                </div>
              </div></div>';
        stdfoot();
        exit;
    }

    $peer_user = "users.id AS userid, users.usergroup, users.username, users.avatar, users.avatardimensions,
                  users.donor, users.enabled, users.warned, users.leechwarn";

    if ($limit == 10 || $subtype === "ul") {
        $r = $db->sql_query_prepared(
            "SELECT {$peer_user},
                IF(peers.uploaded >= peers.uploadoffset, (peers.uploaded - peers.uploadoffset), peers.uploadoffset) /
                    IF(UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started) != 0, UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started), 1) AS uprate,
                IF(seeder = 'yes',
                    (peers.downloaded - peers.downloadoffset) / IF(finishedat - UNIX_TIMESTAMP(started) != 0, finishedat - UNIX_TIMESTAMP(started), 1),
                    (peers.downloaded - peers.downloadoffset) / IF(UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started) != 0, UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started), 1)
                ) AS downrate
             FROM peers
             LEFT JOIN users ON peers.userid = users.id
             WHERE users.usergroup NOT IN (8,7,6,5)
             ORDER BY uprate DESC
             LIMIT ?",
            [(int)$limit]
        );
        peerstable($r, ags_fmt($lang->topten["sec_peers_ul"], $limit), tt_more(4, 'ul', [100, 250]), 'fa-rocket', 'success');
    }

    if ($limit == 10 || $subtype === "dl") {
        $r = $db->sql_query_prepared(
            "SELECT {$peer_user},
                    IF(peers.uploaded >= peers.uploadoffset, (peers.uploaded - peers.uploadoffset), peers.uploadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started)) AS uprate,
                    IF(seeder = 'yes',(peers.downloaded - peers.downloadoffset) / (finishedat - UNIX_TIMESTAMP(started)),(peers.downloaded - peers.downloadoffset) / (UNIX_TIMESTAMP(last_action) - UNIX_TIMESTAMP(started))) AS downrate
             FROM peers
             LEFT JOIN users ON peers.userid = users.id
             ORDER BY downrate DESC
             LIMIT ?",
            [(int)$limit]
        );
        peerstable($r, ags_fmt($lang->topten["sec_peers_dl"], $limit), tt_more(4, 'dl', [100, 250]), 'fa-bolt', 'warning');
    }
}

elseif ($type === 5) {
    if (!$limit || $limit > 50) {
        $limit = 10;
    }
    if ($limit == 10 || $subtype === "categories") {
        $r = $db->sql_query_prepared(
            "SELECT c.id, c.name, c.icon,
                    COUNT(t.id) AS torrents_count,
                    SUM(t.seeders) AS total_seeders,
                    SUM(t.leechers) AS total_leechers,
                    SUM(t.times_completed) AS total_snatches,
                    SUM(t.size) AS total_size
             FROM categories c
             LEFT JOIN torrents t ON c.id = t.category
             WHERE (t.visible = 'yes' OR t.id IS NULL)
             GROUP BY c.id
             ORDER BY torrents_count DESC
             LIMIT ?",
            [(int)$limit]
        );
        categoriestable($r, ags_fmt($lang->topten["sec_categories"], $limit), tt_more(5, 'categories', [25]));
    }
}

elseif ($type === 6) {
    if (!$limit || $limit > 250) {
        $limit = 10;
    }
    $r = $db->sql_query_prepared(
        "SELECT u.id as userid, u.username, u.usergroup, u.seedbonus, u.avatar, u.avatardimensions, {$user_fields}
         FROM users u
         WHERE u.enabled = 'yes' AND u.usergroup NOT IN (5,6,7,8,9)
         ORDER BY u.seedbonus DESC
         LIMIT ?",
        [(int)$limit]
    );
    seedbonustable($r, ags_fmt($lang->topten["sec_seedbonus"], $limit), tt_more(6, '', [25, 50]));
}

elseif ($type === 8) {
    if (!$limit || $limit > 50) {
        $limit = 10;
    }
    $r = $db->sql_query_prepared(
        "SELECT t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data
         FROM torrents AS t
         LEFT JOIN peers AS p ON t.id = p.torrent
         WHERE (t.free = 'yes' OR t.silver = 'yes' OR t.thirtypercent = 'yes')
         GROUP BY t.id
         ORDER BY seeders + leechers DESC, added DESC
         LIMIT ?",
        [(int)$limit]
    );
    hottorrentstable($r, ags_fmt($lang->topten["sec_hot"], $limit), tt_more(8, '', [25, 50]));
}

elseif ($type === 9) {
    if (!$limit || $limit > 250) {
        $limit = 10;
    }
    $r = $db->sql_query_prepared(
        "SELECT u.id as userid, u.username, u.usergroup, u.avatar, u.avatardimensions, {$user_fields},
                COALESCE(c.cnt, 0) AS comment_count,
                COALESCE(rt.cnt, 0) AS rating_count,
                (COALESCE(c.cnt, 0) + COALESCE(rt.cnt, 0)) AS total_activity
         FROM users u
         LEFT JOIN (SELECT user, COUNT(*) AS cnt FROM comments GROUP BY user) c ON c.user = u.id
         LEFT JOIN (SELECT user_id, COUNT(*) AS cnt FROM torrent_ratings GROUP BY user_id) rt ON rt.user_id = u.id
         WHERE u.enabled = 'yes' AND u.usergroup NOT IN (5,6,7,8,9)
         HAVING total_activity > 0
         ORDER BY total_activity DESC
         LIMIT ?",
        [(int)$limit]
    );
    activitytable($r, ags_fmt($lang->topten["sec_contributors"], $limit), tt_more(9, '', [25, 50]));
}

elseif ($type === 7) {
    if (!$limit || $limit > 50) {
        $limit = 10;
    }
    if ($limit == 10 || $subtype === "active_threads") {
        $r = $db->sql_query_prepared(
            "SELECT t.tid, t.subject, t.uid, t.username, t.views, t.replies,
                    t.dateline, t.lastpost, t.lastposter,
                    f.name AS forum_name,
                    u.avatar, u.avatardimensions,
                    (t.replies + t.views) AS activity_score
             FROM threads t
             LEFT JOIN forums f ON t.fid = f.fid
             LEFT JOIN users u ON t.uid = u.id
             WHERE t.visible = 1
             ORDER BY activity_score DESC
             LIMIT ?",
            [(int)$limit]
        );
        activethreadstable($r, $lang->topten["sec_active_threads"]);
    }
}

echo '</div>'; // .tt-page
stdfoot();


// ═════════════════════════════════════════════════════════════════════════════
//  Таблицы
// ═════════════════════════════════════════════════════════════════════════════

function usertable($res, string $title, string $more, string $icon, string $tone): void
{
    global $lang, $regdateformat, $db;

    tt_card_open($icon, $tone, $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_user"], 'fa-user')
        . tt_th($lang->topten["col_uploaded"], 'fa-arrow-up', 'text-end')
        . tt_th($lang->topten["col_ulspeed"], 'fa-gauge-high', 'text-end')
        . tt_th($lang->topten["col_downloaded"], 'fa-arrow-down', 'text-end')
        . tt_th($lang->topten["col_dlspeed"], 'fa-gauge', 'text-end')
        . tt_th($lang->topten["col_ratio"], 'fa-scale-balanced', 'text-center')
        . tt_th($lang->topten["col_joined"], 'fa-calendar-day')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $joined = ($a["added"] == "0000-00-00 00:00:00" || empty($a["added"]))
            ? '<span class="tt-meta">' . $lang->topten["lbl_na"] . '</span>'
            : '<i class="fa-solid fa-calendar-day me-1 text-body-secondary"></i>' . my_datee($regdateformat, $a["added"]);

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_user_cell($a) . "</td>
                <td class='text-end tt-traffic text-success'><i class='fa-solid fa-arrow-up me-1 tt-ico-sm'></i>" . mksize($a["uploaded"]) . "</td>
                <td class='text-end tt-num'><span class='tt-badge tt-soft-info'><i class='fa-solid fa-gauge-high'></i>" . mksize($a["upspeed"]) . $lang->topten["lbl_per_sec"] . "</span></td>
                <td class='text-end tt-traffic text-danger'><i class='fa-solid fa-arrow-down me-1 tt-ico-sm'></i>" . mksize($a["downloaded"]) . "</td>
                <td class='text-end tt-num'><span class='tt-badge tt-soft-warning'><i class='fa-solid fa-gauge'></i>" . mksize($a["downspeed"]) . $lang->topten["lbl_per_sec"] . "</span></td>
                <td class='text-center'>" . tt_ratio($a["uploaded"], $a["downloaded"]) . "</td>
                <td class='tt-num'>" . $joined . "</td>
              </tr>";
    }
    tt_card_close($num, 8);
}


function seedbonustable($res, string $title, string $more): void
{
    global $lang, $db;

    tt_card_open('fa-coins', 'warning', $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_user"], 'fa-user')
        . tt_th($lang->topten["col_seedbonus"], 'fa-coins', 'text-end', '240px')
    );

    $num = 0;
    $max = 0.0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $bp = (float)$a["seedbonus"];
        if ($num === 1) {
            $max = $bp;
        }
        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_user_cell($a) . "</td>
                <td class='text-end'>
                    <span class='tt-badge tt-soft-warning'><i class='fa-solid fa-coins'></i>" . number_format($bp, 2) . "</span>
                    " . tt_bar($bp, $max, 'warning') . "
                </td>
              </tr>";
    }
    tt_card_close($num, 3);
}


function hottorrentstable($res, string $title, string $more): void
{
    global $lang, $db;

    tt_card_open('fa-fire', 'danger', $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_name"], 'fa-magnet')
        . tt_th($lang->topten["col_promo"], 'fa-gift', 'text-center')
        . tt_th($lang->topten["col_seeders"], 'fa-arrow-up', 'text-end')
        . tt_th($lang->topten["col_leechers"], 'fa-arrow-down', 'text-end')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $promo = match (true) {
            ($a['free'] ?? 'no') === 'yes'          => '<span class="tt-badge tt-soft-success"><i class="fa-solid fa-gift"></i>' . $lang->topten["badge_free"] . '</span>',
            ($a['silver'] ?? 'no') === 'yes'        => '<span class="tt-badge tt-soft-muted" title="' . htmlspecialchars_uni($lang->topten["tip_silver"]) . '"><i class="fa-solid fa-star-half-stroke"></i>50%</span>',
            ($a['thirtypercent'] ?? 'no') === 'yes' => '<span class="tt-badge tt-soft-purple"><i class="fa-solid fa-chart-pie"></i>' . $lang->topten["badge_thirty"] . '</span>',
            default                                 => '',
        };

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_torrent_cell($a) . "</td>
                <td class='text-center'>" . $promo . "</td>
                <td class='text-end tt-traffic text-success'><i class='fa-solid fa-arrow-up me-1 tt-ico-sm'></i>" . number_format((int)$a["seeders"]) . "</td>
                <td class='text-end tt-traffic text-danger'><i class='fa-solid fa-arrow-down me-1 tt-ico-sm'></i>" . number_format((int)$a["leechers"]) . "</td>
              </tr>";
    }
    tt_card_close($num, 5);
}


function activitytable($res, string $title, string $more): void
{
    global $lang, $db;

    tt_card_open('fa-hand-holding-heart', 'purple', $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_user"], 'fa-user')
        . tt_th($lang->topten["col_comments"], 'fa-comment-dots', 'text-end')
        . tt_th($lang->topten["col_ratings"], 'fa-star', 'text-end')
        . tt_th($lang->topten["col_total_activity"], 'fa-chart-line', 'text-end', '200px')
    );

    $num = 0;
    $max = 0.0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $total = (int)$a["total_activity"];
        if ($num === 1) {
            $max = (float)$total;
        }
        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_user_cell($a) . "</td>
                <td class='text-end tt-num'><i class='fa-solid fa-comment-dots me-1 text-body-secondary'></i>" . number_format((int)$a["comment_count"]) . "</td>
                <td class='text-end tt-num'><i class='fa-solid fa-star me-1 text-warning'></i>" . number_format((int)$a["rating_count"]) . "</td>
                <td class='text-end'>
                    <span class='tt-badge tt-soft-purple'><i class='fa-solid fa-bolt'></i>" . number_format($total) . "</span>
                    " . tt_bar((float)$total, $max, 'purple') . "
                </td>
              </tr>";
    }
    tt_card_close($num, 5);
}


function _torrenttable($res, string $title, string $more, string $icon, string $tone): void
{
    global $lang, $db;

    tt_card_open($icon, $tone, $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_name"], 'fa-magnet')
        . tt_th($lang->topten["col_snatched"], 'fa-flag-checkered', 'text-end')
        . tt_th($lang->topten["col_data"], 'fa-database', 'text-end')
        . tt_th($lang->topten["col_seeders"], 'fa-arrow-up', 'text-end')
        . tt_th($lang->topten["col_leechers"], 'fa-arrow-down', 'text-end')
        . tt_th($lang->topten["col_total"], 'fa-users', 'text-end')
        . tt_th($lang->topten["col_sl_ratio"], 'fa-scale-balanced', 'text-center')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $seeders  = (int)$a["seeders"];
        $leechers = (int)$a["leechers"];

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_torrent_cell($a) . "</td>
                <td class='text-end tt-num'><span class='tt-badge tt-soft-info'><i class='fa-solid fa-flag-checkered'></i>" . number_format((int)$a["times_completed"]) . "</span></td>
                <td class='text-end tt-traffic'><i class='fa-solid fa-hard-drive me-1 text-body-secondary tt-ico-sm'></i>" . mksize((float)$a["data"]) . "</td>
                <td class='text-end tt-traffic text-success'><i class='fa-solid fa-arrow-up me-1 tt-ico-sm'></i>" . number_format($seeders) . "</td>
                <td class='text-end tt-traffic text-danger'><i class='fa-solid fa-arrow-down me-1 tt-ico-sm'></i>" . number_format($leechers) . "</td>
                <td class='text-end tt-traffic'>" . number_format($seeders + $leechers) . "</td>
                <td class='text-center'>" . tt_ratio($seeders, $leechers) . "</td>
              </tr>";
    }
    tt_card_close($num, 8);
}


function countriestable($res, string $title, string $more, string $what): void
{
    global $pic_base_url, $lang, $db;

    [$icon, $tone, $label, $colIcon] = match ($what) {
        "Users"    => ['fa-users',          'success', $lang->topten["col_cnt_users"],    'fa-users'],
        "Uploaded" => ['fa-cloud-arrow-up', 'info',    $lang->topten["col_cnt_uploaded"], 'fa-arrow-up'],
        "Average"  => ['fa-chart-column',   'purple',  $lang->topten["col_cnt_average"],  'fa-chart-column'],
        default    => ['fa-scale-balanced', 'warning', $lang->topten["col_cnt_ratio"],    'fa-scale-balanced'],
    };

    tt_card_open($icon, $tone, $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_country"], 'fa-flag')
        . tt_th($label, $colIcon, 'text-end', '240px')
    );

    $num = 0;
    $max = 0.0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $raw   = (float)($a["v"] ?? 0);
        $value = match ($what) {
            "Users"              => number_format((int)$raw),
            "Uploaded", "Average" => mksize($raw),
            default              => number_format($raw, 2),
        };
        if ($num === 1) {
            $max = $raw;
        }

        $name = htmlspecialchars_uni((string)($a["name"] ?? ''));
        if ($name === '') {
            $name = $lang->topten["lbl_na"];
        }
        $flag = !empty($a["flagpic"])
            ? '<img src="' . $pic_base_url . 'flag/' . htmlspecialchars_uni((string)$a["flagpic"]) . '" class="tt-flag" alt="" loading="lazy">'
            : '<span class="tt-torrent-ico tt-soft-muted"><i class="fa-solid fa-earth-europe"></i></span>';

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td><div class='d-flex align-items-center gap-2'>" . $flag . "<strong>" . $name . "</strong></div></td>
                <td class='text-end'>
                    <span class='tt-badge tt-soft-" . $tone . "'>" . $value . "</span>
                    " . tt_bar($raw, $max, $tone) . "
                </td>
              </tr>";
    }
    tt_card_close($num, 3);
}


function peerstable($res, string $title, string $more, string $icon, string $tone): void
{
    global $lang, $db;

    tt_card_open($icon, $tone, $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_user"], 'fa-user')
        . tt_th($lang->topten["col_ulspeed"], 'fa-arrow-up', 'text-end', '170px')
        . tt_th($lang->topten["col_dlspeed"], 'fa-arrow-down', 'text-end', '170px')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_user_cell($a) . "</td>
                <td class='text-end tt-traffic text-success'><i class='fa-solid fa-arrow-up me-1 tt-ico-sm'></i>" . mksize($a["uprate"] ?? 0) . $lang->topten["lbl_per_sec"] . "</td>
                <td class='text-end tt-traffic text-danger'><i class='fa-solid fa-arrow-down me-1 tt-ico-sm'></i>" . mksize($a["downrate"] ?? 0) . $lang->topten["lbl_per_sec"] . "</td>
              </tr>";
    }
    tt_card_close($num, 4);
}


function mostcommentedtable($res, string $title, string $more): void
{
    global $lang, $db;

    tt_card_open('fa-comment-dots', 'info', $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_torrent"], 'fa-magnet')
        . tt_th($lang->topten["col_comments"], 'fa-comment-dots', 'text-center')
        . tt_th($lang->topten["col_snatched"], 'fa-flag-checkered', 'text-center')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>" . tt_torrent_cell($a) . "</td>
                <td class='text-center'><span class='tt-badge tt-soft-info'><i class='fa-solid fa-comment-dots'></i>" . number_format((int)$a["comment_count"]) . "</span></td>
                <td class='text-center tt-num'><i class='fa-solid fa-flag-checkered me-1 text-body-secondary'></i>" . number_format((int)$a["times_completed"]) . "</td>
              </tr>";
    }
    tt_card_close($num, 4);
}


function categoriestable($res, string $title, string $more): void
{
    global $lang, $db;

    tt_card_open('fa-tags', 'primary', $title, $more,
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_category"], 'fa-folder')
        . tt_th($lang->topten["col_torrents"], 'fa-magnet', 'text-center', '150px')
        . tt_th($lang->topten["col_seeders"], 'fa-arrow-up', 'text-end')
        . tt_th($lang->topten["col_leechers"], 'fa-arrow-down', 'text-end')
        . tt_th($lang->topten["col_snatches"], 'fa-flag-checkered', 'text-end')
        . tt_th($lang->topten["col_total_size"], 'fa-hard-drive', 'text-end')
    );

    $num = 0;
    $max = 0.0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $count = (int)$a["torrents_count"];
        if ($num === 1) {
            $max = (float)$count;
        }
        $icon_classes = !empty($a["icon"]) ? htmlspecialchars_uni((string)$a["icon"]) : "fa-solid fa-folder";

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>
                    <div class='d-flex align-items-center gap-2'>
                        <span class='tt-torrent-ico tt-soft-primary'><i class='" . $icon_classes . "'></i></span>
                        <a href='browse.php?cat=" . (int)$a["id"] . "' class='tt-link'>" . htmlspecialchars((string)$a["name"]) . "</a>
                    </div>
                </td>
                <td class='text-center'>
                    <span class='tt-badge tt-soft-primary'>" . number_format($count) . "</span>
                    " . tt_bar((float)$count, $max, 'primary') . "
                </td>
                <td class='text-end tt-traffic text-success'><i class='fa-solid fa-arrow-up me-1 tt-ico-sm'></i>" . number_format((int)($a["total_seeders"] ?? 0)) . "</td>
                <td class='text-end tt-traffic text-danger'><i class='fa-solid fa-arrow-down me-1 tt-ico-sm'></i>" . number_format((int)($a["total_leechers"] ?? 0)) . "</td>
                <td class='text-end tt-num'>" . number_format((int)($a["total_snatches"] ?? 0)) . "</td>
                <td class='text-end tt-num'><i class='fa-solid fa-hard-drive me-1 text-body-secondary'></i>" . mksize((float)($a["total_size"] ?? 0)) . "</td>
              </tr>";
    }
    tt_card_close($num, 7);
}


function activethreadstable($res, string $title): void
{
    global $lang, $db;

    tt_card_open('fa-comments', 'info', $title, '',
        tt_th('#', '', 'text-center', '60px')
        . tt_th($lang->topten["col_thread"], 'fa-message')
        . tt_th($lang->topten["col_forum"], 'fa-folder-open')
        . tt_th($lang->topten["col_views"], 'fa-eye', 'text-end')
        . tt_th($lang->topten["col_replies"], 'fa-reply', 'text-end')
        . tt_th($lang->topten["col_activity"], 'fa-chart-line', 'text-center')
    );

    $num = 0;
    while ($a = $db->fetch_array($res)) {
        $num++;
        $av = format_avatar((string)($a['avatar'] ?? ''), (string)($a['avatardimensions'] ?? ''));

        echo tt_row($num) . "
                <td class='text-center'>" . tt_rank($num) . "</td>
                <td>
                    <div class='d-flex align-items-center gap-2'>
                        <img class='tt-avatar' src='" . $av['image'] . "' alt='' loading='lazy'>
                        <div class='lh-sm'>
                            <a href='" . get_thread_link($a['tid']) . "' class='tt-link'>" . htmlspecialchars((string)$a["subject"]) . "</a>
                            <div class='tt-meta'><i class='fa-solid fa-user'></i>" . ags_fmt($lang->topten["lbl_by"], htmlspecialchars((string)$a["username"])) . "</div>
                        </div>
                    </div>
                </td>
                <td><span class='tt-chip'><i class='fa-solid fa-folder-open'></i>" . htmlspecialchars((string)$a["forum_name"]) . "</span></td>
                <td class='text-end tt-num'><i class='fa-solid fa-eye me-1 text-body-secondary'></i>" . number_format((int)$a["views"]) . "</td>
                <td class='text-end tt-num'><i class='fa-solid fa-reply me-1 text-body-secondary'></i>" . number_format((int)$a["replies"]) . "</td>
                <td class='text-center'><span class='tt-badge tt-soft-info'><i class='fa-solid fa-bolt'></i>" . number_format((int)$a["activity_score"]) . "</span></td>
              </tr>";
    }
    tt_card_close($num, 6);
}
