<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center">Error! Direct initialization of this file is not allowed.</div>');
}

define('VP_VERSION', '0.3 by xam');
const VP_ASSET_VER = 1;

/* =========================================================================
 * Helpers
 * ========================================================================= */

/**
 * Normalize a DB time value (int timestamp or DATETIME string) to a unix timestamp.
 */
function vp_ts(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    if (is_string($value) && $value !== '' && !str_starts_with($value, '0000')) {
        $t = strtotime($value);
        return $t === false ? 0 : $t;
    }
    return 0;
}

/**
 * "5 min ago" style relative time.
 */
function vp_ago(int $ts, int $now): string
{
    if ($ts <= 0) {
        return '—';
    }
    $d = max(0, $now - $ts);

    return match (true) {
        $d < 60    => $d . ' sec ago',
        $d < 3600  => intdiv($d, 60) . ' min ago',
        $d < 86400 => intdiv($d, 3600) . ' h ago',
        default    => intdiv($d, 86400) . ' d ago',
    };
}

/**
 * Tone for "last action": fresh (≤ 30 min), late (≤ 60 min), stale.
 */
function vp_activity_tone(int $ts, int $now): string
{
    if ($ts <= 0) {
        return 'secondary';
    }
    $d = $now - $ts;
    return match (true) {
        $d <= 1800 => 'success',
        $d <= 3600 => 'warning',
        default    => 'danger',
    };
}

/**
 * Client name: agent string, otherwise printable prefix of peer_id (e.g. -qB4650-).
 */
function vp_client(?string $agent, ?string $peer_id): string
{
    global $lang;

    $agent = trim((string)$agent);
    if ($agent !== '') {
        return $agent;
    }

    $peer_id = (string)$peer_id;
    if ($peer_id !== '') {
        $printable = (string)preg_replace('/[^\x20-\x7E]/', '', substr($peer_id, 0, 8));
        if ($printable !== '') {
            return $printable;
        }
    }

    return (string)($lang->global['unknown'] ?? 'Unknown');
}

/**
 * Soft pill badge with icon.
 */
function vp_badge(string $tone, string $icon, string $text): string
{
    return '<span class="vp-badge vp-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i>' . $text . '</span>';
}

/**
 * KPI tile.
 */
function vp_kpi(string $tone, string $icon, string $label, string $value, string $sub = ''): string
{
    return '
    <div class="col-6 col-xl-3">
        <div class="vp-card vp-kpi">
            <div class="vp-icon-sq vp-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></div>
            <div class="min-w-0">
                <div class="vp-kpi-label">' . $label . '</div>
                <div class="vp-kpi-val">' . $value . '</div>
                ' . ($sub !== '' ? '<div class="vp-kpi-sub">' . $sub . '</div>' : '') . '
            </div>
        </div>
    </div>';
}

/**
 * Ratio text.
 */
function vp_ratio(int $up, int $down): string
{
    if ($down <= 0) {
        return $up > 0 ? '∞' : '—';
    }
    return number_format($up / $down, 2);
}

/**
 * Two-line date/time cell.
 */
function vp_datetime(mixed $value): string
{
    global $dateformat, $timeformat;

    if (vp_ts($value) <= 0) {
        return '<span class="vp-muted">—</span>';
    }
    return my_datee($dateformat, $value) . '<br><span class="vp-muted">' . my_datee($timeformat, $value) . '</span>';
}

/**
 * Render a single peer row.
 */
function vp_render_row(array $row, int $now): string
{
    global $BASEURL, $dateformat;

    // --- User ---
    $username  = (string)($row['username'] ?? '');
    $user_html = $username !== ''
        ? format_name(htmlspecialchars_uni($username), $row['usergroup'] ?? 0, $row['displaygroup'] ?? 0)
        : '<span class="vp-muted fst-italic">deleted user</span>';
    $initial = $username !== '' ? htmlspecialchars_uni(mb_strtoupper(mb_substr($username, 0, 1))) : '?';
    $profile_link = $BASEURL . '/' . get_profile_link($row['userid'] ?? 0);

    // --- Torrent ---
    $torrent_name = (string)($row['name'] ?? 'Unknown torrent');
    $short_name   = htmlspecialchars_uni(cutename($torrent_name, 5));
    $torrent_link = $BASEURL . '/' . get_torrent_link($row['torrent'] ?? 0);

    $is_seed = ($row['seeder'] ?? 'no') === 'yes';
    $size    = (int)($row['size'] ?? 0);
    $to_go   = (int)($row['to_go'] ?? 0);
    $pct     = $size > 0
        ? max(0.0, min(100.0, round(($size - $to_go) / $size * 100, 1)))
        : ($is_seed ? 100.0 : 0.0);
    $pct_txt = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');

    $popover_title = htmlspecialchars(
        '<i class="fa-solid fa-folder-open me-2"></i>' . htmlspecialchars_uni(cutename($torrent_name, 20)),
        ENT_QUOTES
    );
    $popover_content = htmlspecialchars('
        <div class="vp-pop">
            <div class="vp-pop-name">' . htmlspecialchars_uni($torrent_name) . '</div>
            <div class="vp-pop-meta">
                <span><i class="fa-solid fa-user"></i>' . $user_html . '</span>
                <span><i class="fa-solid fa-calendar-plus"></i>' . my_datee($dateformat, $row['added'] ?? 0) . '</span>
                ' . ($size > 0 ? '<span><i class="fa-solid fa-hard-drive"></i>' . mksize($size) . '</span>' : '') . '
            </div>
        </div>', ENT_QUOTES);

    // --- Address ---
    $ip      = (string)($row['ip'] ?? '');
    $port    = (string)($row['port'] ?? '');
    $ip_safe = htmlspecialchars_uni($ip !== '' ? $ip : 'N/A');
    $is_v6   = $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

    // --- Traffic ---
    $up   = (int)($row['uploaded'] ?? 0);
    $down = (int)($row['downloaded'] ?? 0);
    $up_class = match (true) {
        $up > 0 && $up < $down => 'text-danger',
        $up > 0                => 'text-success',
        default                => 'vp-muted',
    };

    // --- Client ---
    $client = htmlspecialchars_uni(vp_client(
        isset($row['agent']) ? (string)$row['agent'] : '',
        isset($row['peer_id']) ? (string)$row['peer_id'] : ''
    ));

    // --- Badges ---
    $connectable = ($row['connectable'] ?? 'no') === 'yes'
        ? vp_badge('success', 'fa-plug-circle-check', 'yes')
        : vp_badge('danger', 'fa-plug-circle-xmark', 'no');
    $status = $is_seed
        ? vp_badge('success', 'fa-arrow-up-from-bracket', 'seed')
        : vp_badge('warning', 'fa-arrow-down', 'leech');

    // --- Activity ---
    $last_ts   = vp_ts($row['last_action'] ?? 0);
    $prev_ts   = vp_ts($row['prev_action'] ?? 0);
    $act_tone  = vp_activity_tone($last_ts, $now);

    // --- Search index for client-side filter ---
    $search = htmlspecialchars(mb_strtolower($username . ' ' . $torrent_name . ' ' . $ip . ' ' . vp_client(
        isset($row['agent']) ? (string)$row['agent'] : '',
        isset($row['peer_id']) ? (string)$row['peer_id'] : ''
    )), ENT_QUOTES);

    return '
    <tr class="vp-row vp-row-' . ($is_seed ? 'seed' : 'leech') . '" data-status="' . ($is_seed ? 'seed' : 'leech') . '" data-search="' . $search . '">
        <td>
            <div class="vp-user">
                <span class="vp-avatar">' . $initial . '</span>
                <a href="' . $profile_link . '" class="text-decoration-none fw-semibold">' . $user_html . '</a>
            </div>
        </td>
        <td class="vp-torrent-cell">
            <a href="' . $torrent_link . '"
               class="text-decoration-none torrent-link fw-medium"
               data-bs-toggle="popover"
               data-bs-title="' . $popover_title . '"
               data-bs-content="' . $popover_content . '"
               data-bs-html="true">' . $short_name . '</a>
            <div class="vp-progress-wrap" title="' . $pct_txt . '% complete">
                <div class="vp-progress vp-tone-' . ($is_seed ? 'success' : 'primary') . '"><span style="width:' . $pct . '%"></span></div>
                <span class="vp-progress-txt">' . $pct_txt . '%</span>
            </div>
        </td>
        <td class="text-nowrap">
            <div class="vp-ip">
                <i class="fa-solid fa-location-dot vp-muted"></i>
                <span class="font-monospace">' . $ip_safe . '</span>
                ' . ($is_v6 ? '<span class="vp-tag">v6</span>' : '') . '
                ' . ($ip !== '' ? '<button type="button" class="vp-copy" data-vp-copy="' . $ip_safe . '" title="Copy IP" aria-label="Copy IP"><i class="fa-solid fa-copy"></i></button>' : '') . '
            </div>
            <div class="vp-muted small"><i class="fa-solid fa-door-open me-1"></i>port ' . htmlspecialchars_uni($port !== '' ? $port : 'N/A') . '</div>
        </td>
        <td class="text-nowrap">
            <div class="fw-semibold ' . $up_class . '"><i class="fa-solid fa-arrow-up fa-fw"></i> ' . mksize($up) . '</div>
            <div><i class="fa-solid fa-arrow-down fa-fw vp-muted"></i> ' . mksize($down) . '</div>
            <div class="vp-muted small"><i class="fa-solid fa-scale-balanced fa-fw"></i> ' . vp_ratio($up, $down) . '</div>
        </td>
        <td><span class="vp-client" title="' . $client . '"><i class="fa-solid fa-desktop"></i>' . $client . '</span></td>
        <td class="text-center">' . $connectable . '</td>
        <td class="text-center">' . $status . '</td>
        <td class="text-center small text-nowrap">' . vp_datetime($row['started'] ?? 0) . '</td>
        <td class="small text-nowrap">
            <div><span class="vp-dot vp-tone-' . $act_tone . '"></span>' . vp_ago($last_ts, $now) . '</div>
            <div class="vp-muted"><i class="fa-solid fa-clock-rotate-left fa-fw"></i> ' . vp_ago($prev_ts, $now) . '</div>
        </td>
        <td class="small text-nowrap vp-muted">
            <div><i class="fa-solid fa-arrow-up fa-fw"></i> ' . mksize((int)($row['uploadoffset'] ?? 0)) . '</div>
            <div><i class="fa-solid fa-arrow-down fa-fw"></i> ' . mksize((int)($row['downloadoffset'] ?? 0)) . '</div>
        </td>
        <td class="text-center small text-nowrap">' . ($to_go > 0 ? mksize($to_go) : '<i class="fa-solid fa-check text-success" title="Complete"></i>') . '</td>
    </tr>';
}

/* =========================================================================
 * Page
 * ========================================================================= */

global $BASEURL;

$now = defined('TIMENOW') ? (int)TIMENOW : time();

stdhead('Peer List');
require_once INC_PATH . '/functions_multipage.php';

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/viewpeers.css?ver=' . VP_ASSET_VER . '">';

// ---- Stats (one query) ----
$stats = ['cnt' => 0, 'seeders' => 0, 'connectable' => 0, 'users' => 0];
$stats_res = $db->sql_query_prepared("
    SELECT COUNT(*)                                AS cnt,
           COALESCE(SUM(seeder = 'yes'), 0)        AS seeders,
           COALESCE(SUM(connectable = 'yes'), 0)   AS connectable,
           COUNT(DISTINCT userid)                  AS users
    FROM peers
");
if ($stats_res !== false && ($srow = $db->fetch_array($stats_res))) {
    foreach (array_keys($stats) as $k) {
        $stats[$k] = (int)($srow[$k] ?? 0);
    }
}
$total_peers = $stats['cnt'];
$leechers    = max(0, $total_peers - $stats['seeders']);
$pct_of      = static fn(int $n): string => $total_peers > 0 ? round($n / $total_peers * 100) . '% of peers' : '—';

// ---- Pagination ----
$per_page    = max(20, (int)($ts_perpage ?? 20));
$page        = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
$total_pages = max(1, (int)ceil($total_peers / $per_page));
if ($page > $total_pages) {
    $page = 1;
}
$start     = ($page - 1) * $per_page;
$multipage = multipage($total_peers, $per_page, $page, $_this_script_ . '&');

// ---- Header ----
echo '
<div class="vp-page container mt-3">

    <div class="vp-card vp-head">
        <div class="vp-icon-sq vp-icon-lg vp-tone-primary"><i class="fa-solid fa-network-wired"></i></div>
        <div class="min-w-0">
            <h4 class="vp-title">Peer List</h4>
            <div class="vp-sub">Live peer connections on the tracker, newest first</div>
        </div>
        <span class="vp-live ms-auto"><span class="vp-live-dot"></span>Live</span>
    </div>

    <div class="row g-3 mb-3">'
        . vp_kpi('primary', 'fa-diagram-project', 'Active peers', number_format($total_peers), $total_pages . ' page' . ($total_pages === 1 ? '' : 's'))
        . vp_kpi('success', 'fa-seedling', 'Seeders', number_format($stats['seeders']), $pct_of($stats['seeders']))
        . vp_kpi('warning', 'fa-cloud-arrow-down', 'Leechers', number_format($leechers), $pct_of($leechers))
        . vp_kpi('info', 'fa-users', 'Unique users', number_format($stats['users']), number_format($stats['connectable']) . ' connectable peers')
    . '</div>';

// ---- Toolbar ----
echo '
    <div class="vp-card vp-toolbar">
        <div class="vp-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="vp-search" class="form-control" placeholder="Filter this page by user, torrent, IP or client" autocomplete="off">
        </div>
        <div class="vp-seg" role="group" aria-label="Filter by status">
            <button type="button" class="vp-pill active" data-vp-filter="all"><i class="fa-solid fa-layer-group"></i>All</button>
            <button type="button" class="vp-pill" data-vp-filter="seed"><i class="fa-solid fa-seedling"></i>Seeding</button>
            <button type="button" class="vp-pill" data-vp-filter="leech"><i class="fa-solid fa-cloud-arrow-down"></i>Leeching</button>
        </div>
        <div class="vp-count ms-lg-auto"><i class="fa-solid fa-eye"></i><span id="vp-visible">0</span> shown on this page</div>
    </div>';

if (!empty($multipage)) {
    echo '<div class="vp-pager">' . $multipage . '</div>';
}

// ---- Table ----
$result = $db->sql_query_prepared("
    SELECT p.*, t.name, t.added, t.size, u.username, u.usergroup, u.displaygroup
    FROM peers p
    LEFT JOIN torrents t ON (p.torrent = t.id)
    LEFT JOIN users u ON (p.userid = u.id)
    ORDER BY p.started DESC
    LIMIT ? OFFSET ?
", [$per_page, $start]);

if ($result !== false && $db->num_rows($result) > 0) {
    echo '
    <div class="vp-card vp-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 vp-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-user"></i>User</th>
                        <th><i class="fa-solid fa-file-arrow-down"></i>Torrent</th>
                        <th><i class="fa-solid fa-globe"></i>Address</th>
                        <th><i class="fa-solid fa-right-left"></i>Traffic</th>
                        <th><i class="fa-solid fa-desktop"></i>Client</th>
                        <th class="text-center"><i class="fa-solid fa-plug"></i>Connectable</th>
                        <th class="text-center"><i class="fa-solid fa-signal"></i>Status</th>
                        <th class="text-center"><i class="fa-solid fa-play"></i>Started</th>
                        <th><i class="fa-solid fa-heart-pulse"></i>Activity</th>
                        <th><i class="fa-solid fa-sliders"></i>Offsets</th>
                        <th class="text-center"><i class="fa-solid fa-hourglass-half"></i>To go</th>
                    </tr>
                </thead>
                <tbody>';

    while ($row = $db->fetch_array($result)) {
        echo vp_render_row($row, $now);
    }

    echo '
                    <tr id="vp-no-match" hidden>
                        <td colspan="11" class="text-center py-4 vp-muted">
                            <i class="fa-solid fa-filter-circle-xmark me-2"></i>No peers on this page match the filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>';
} else {
    echo '
    <div class="vp-card vp-empty">
        <div class="vp-icon-sq vp-icon-lg vp-tone-secondary mx-auto mb-3"><i class="fa-solid fa-users-slash"></i></div>
        <h5 class="mb-1">No active peers</h5>
        <p class="vp-muted mb-0">Peers appear here as soon as a client announces to the tracker.</p>
    </div>';
}

if (!empty($multipage)) {
    echo '<div class="vp-pager">' . $multipage . '</div>';
}

echo '
    <div class="vp-foot vp-muted">
        <span><span class="vp-dot vp-tone-success"></span>announced ≤ 30 min</span>
        <span><span class="vp-dot vp-tone-warning"></span>≤ 60 min</span>
        <span><span class="vp-dot vp-tone-danger"></span>stale</span>
        <span class="ms-auto">viewpeers ' . VP_VERSION . '</span>
    </div>
</div>';

echo '<script src="' . $BASEURL . '/admin/scripts/viewpeers.js?ver=' . VP_ASSET_VER . '" defer></script>';

stdfoot();