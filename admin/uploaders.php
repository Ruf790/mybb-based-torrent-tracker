<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center mt-4">Direct initialization not allowed.</div>');
}

define('U_VERSION', '0.5');

include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
include_once $rootpath . '/admin/include/global_config.php';

$type     = (int)($_GET['type'] ?? 1);
$uploader = isset($_GET['uploader']) && is_valid_id($_GET['uploader']) ? (int)$_GET['uploader'] : null;
$combine  = $uploader !== null;

// Формируем условия для WHERE с использованием prepared statements
$whereClause = '';
$params = [];

if ($combine) {
    $whereClause = 'u.id = ?';
    $params[] = $uploader;
} elseif ($type === 2) {
    // Для "canupload" используем прямой запрос без параметров
    $whereClause = "g.canupload = 'yes'";
} else {
    // Для UC_UPLOADER используем параметр
    $whereClause = 'u.usergroup = ?';
    $params[] = UC_UPLOADER;
}

// Для отладки - можно залогировать
// error_log("WHERE clause: $whereClause, params: " . print_r($params, true));

// COUNT запрос с использованием prepared statement
$countSql = "SELECT COUNT(u.id) AS cnt FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.enabled='yes' AND {$whereClause}";
$countQ = $db->sql_query_prepared($countSql, $params);

$total_count = 0;
if ($countQ !== false) {
    $row = $db->fetch_array($countQ);
    $total_count = (int)($row['cnt'] ?? 0);
}

// Если нет результатов и это type=1, попробуем альтернативный запрос без JOIN
if ($total_count === 0 && $type === 1) {
    // Альтернативный запрос - возможно, usergroup хранится как число
    $altCountQ = $db->sql_query_prepared(
        "SELECT COUNT(id) AS cnt FROM users WHERE enabled='yes' AND usergroup = ?",
        [UC_UPLOADER]
    );
    if ($altCountQ !== false) {
        $row = $db->fetch_array($altCountQ);
        $alt_count = (int)($row['cnt'] ?? 0);
        if ($alt_count > 0) {
            // Если альтернативный запрос вернул результаты, используем его
            $total_count = $alt_count;
            // Обновляем основной запрос
            $whereClause = 'u.usergroup = ?';
            $params = [UC_UPLOADER];
        }
    }
}

$perpage = max(1, (int)($ts_perpage ?? 25));
$page    = max(1, (int)($mybb->input['page'] ?? 1));
$pages   = $total_count > 0 ? (int)ceil($total_count / $perpage) : 1;
if ($page > $pages) $page = 1;
$start   = ($page - 1) * $perpage;

$multipage = multipage($total_count, $perpage, $page,
    $_this_script_ . '&type=' . $type . ($uploader ? '&uploader=' . $uploader : '') . '&');

// Основной SELECT запрос с использованием prepared statement
$queryParams = array_merge($params, [$start, $perpage]);
$querySql = "SELECT u.id, u.username, u.avatar, u.avatardimensions, u.usergroup,
                    u.lastactive, u.lastvisit, u.uploaded, u.downloaded
             FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid)
             WHERE u.enabled='yes' AND {$whereClause}
             ORDER BY u.username ASC
             LIMIT ? OFFSET ?";

$query = $db->sql_query_prepared($querySql, $queryParams);

$uploaderIds  = [];
$uploaderRows = [];

if ($query !== false) {
    while ($row = $db->fetch_array($query)) {
        $uploaderIds[]             = (int)$row['id'];
        $uploaderRows[$row['id']] = $row;
    }
}

// Если все еще нет результатов и это type=1, попробуем прямой запрос без JOIN
if (empty($uploaderRows) && $type === 1) {
    // Прямой запрос без JOIN
    $altQuery = $db->sql_query_prepared(
        "SELECT id, username, avatar, avatardimensions, usergroup,
                lastactive, lastvisit, uploaded, downloaded
         FROM users 
         WHERE enabled='yes' AND usergroup = ?
         ORDER BY username ASC
         LIMIT ? OFFSET ?",
        [UC_UPLOADER, $perpage, $start]
    );
    
    if ($altQuery !== false) {
        while ($row = $db->fetch_array($altQuery)) {
            $uploaderIds[]             = (int)$row['id'];
            $uploaderRows[$row['id']] = $row;
        }
        // Обновляем total_count если он был 0
        if ($total_count === 0) {
            $total_count = count($uploaderIds);
        }
    }
}

// Получение торрентов с использованием prepared statement с IN()
$torrentsByOwner = [];
$torrentCounts   = [];
$sumSeeders      = 0;
$sumLeechers     = 0;
$sumSize         = 0;

if (!empty($uploaderIds)) {
    // Создаем плейсхолдеры для каждого ID
    $placeholders = implode(',', array_fill(0, count($uploaderIds), '?'));
    
    $tq = $db->sql_query_prepared(
        "SELECT id, name, added, owner, seeders, leechers, size 
         FROM torrents 
         WHERE owner IN ({$placeholders}) 
         ORDER BY added DESC",
        $uploaderIds
    );
    
    if ($tq !== false) {
        while ($t = $db->fetch_array($tq)) {
            $oid = (int)$t['owner'];
            $torrentCounts[$oid] = ($torrentCounts[$oid] ?? 0) + 1;
            $sumSeeders  += (int)$t['seeders'];
            $sumLeechers += (int)$t['leechers'];
            $sumSize     += (int)$t['size'];
            if ($combine || ($torrentCounts[$oid] <= 5)) {
                $torrentsByOwner[$oid][] = $t;
            }
        }
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────────
$fmtSize = static function (int $bytes): string {
    if (function_exists('mksize')) {
        return (string)mksize($bytes);
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = 0;
    $v = (float)$bytes;
    while ($v >= 1024 && $i < count($units) - 1) {
        $v /= 1024;
        $i++;
    }
    return number_format($v, $i === 0 ? 0 : 2) . ' ' . $units[$i];
};

$ONLINE_WINDOW = 900; // 15 минут

stdhead($SITENAME . ' — Uploader List');

$BASE     = htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8');
$h_script = htmlspecialchars($_this_script_, ENT_QUOTES, 'UTF-8');
$h_site   = htmlspecialchars($SITENAME, ENT_QUOTES, 'UTF-8');
$shownTorrents = array_sum($torrentCounts);
?>

<style>
/* Все стили изолированы под .upl-page — глобальные .card/.btn/.badge не трогаем */
.upl-page { font-size: 1.055rem; }

/* ── Header ───────────────────────────────────────────────────────────────── */
.upl-page .upl-header {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
}
.upl-page .upl-icon-square {
    width: 3.25rem; height: 3.25rem;
    border-radius: .85rem;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.upl-page .upl-soft-primary { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
.upl-page .upl-soft-success { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.upl-page .upl-soft-danger  { background: var(--bs-danger-bg-subtle);  color: var(--bs-danger-text-emphasis); }
.upl-page .upl-soft-info    { background: var(--bs-info-bg-subtle);    color: var(--bs-info-text-emphasis); }
.upl-page .upl-soft-warning { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
.upl-page .upl-title { font-size: 1.45rem; font-weight: 700; margin: 0; }
.upl-page .upl-subtitle { color: var(--bs-secondary-color); margin: .15rem 0 0; font-size: .92rem; }

/* ── Pill buttons / tabs ──────────────────────────────────────────────────── */
.upl-page .upl-pill {
    display: inline-flex; align-items: center; gap: .45rem;
    padding: .42rem 1rem;
    border-radius: 2rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: .9rem; font-weight: 500;
    text-decoration: none;
    transition: background-color .15s, border-color .15s, color .15s;
}
.upl-page .upl-pill:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.upl-page .upl-pill.is-active {
    background: var(--bs-primary); border-color: var(--bs-primary); color: #fff;
}
.upl-page .upl-pill:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: 2px; }

/* ── KPI tiles ────────────────────────────────────────────────────────────── */
.upl-page .upl-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.upl-page .upl-kpi {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 1rem 1.1rem;
    display: flex; align-items: center; gap: .9rem;
}
.upl-page .upl-kpi .upl-icon-square { width: 2.75rem; height: 2.75rem; font-size: 1.15rem; border-radius: .75rem; }
.upl-page .upl-kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
.upl-page .upl-kpi-label { font-size: .82rem; color: var(--bs-secondary-color); }

/* ── Toolbar (поиск) ─────────────────────────────────────────────────────── */
.upl-page .upl-search {
    position: relative; flex: 1 1 260px; max-width: 360px;
}
.upl-page .upl-search i {
    position: absolute; left: .95rem; top: 50%; transform: translateY(-50%);
    color: var(--bs-secondary-color); font-size: .9rem; pointer-events: none;
}
.upl-page .upl-search input {
    width: 100%;
    padding: .45rem 1rem .45rem 2.4rem;
    border-radius: 2rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: .92rem;
}
.upl-page .upl-search input:focus { outline: none; border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15); }

/* ── Uploader cards ───────────────────────────────────────────────────────── */
.upl-page .upl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.1rem;
}
.upl-page .upl-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 1.1rem 1.15rem 1rem;
    display: flex; flex-direction: column;
    transition: border-color .2s;
}
.upl-page .upl-card:hover { border-color: rgba(var(--bs-primary-rgb), .45); }

.upl-page .upl-avatar-wrap { position: relative; flex-shrink: 0; }
.upl-page .upl-avatar,
.upl-page .upl-avatar-ph {
    width: 54px; height: 54px; border-radius: 50%;
    border: 2px solid var(--bs-border-color);
}
.upl-page .upl-avatar { object-fit: cover; }
.upl-page .upl-avatar-ph {
    display: flex; align-items: center; justify-content: center;
    background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 1.3rem;
}
.upl-page .upl-online-dot {
    position: absolute; right: 1px; bottom: 1px;
    width: 13px; height: 13px; border-radius: 50%;
    background: var(--bs-success);
    border: 2px solid var(--bs-body-bg);
}
.upl-page .upl-name { font-weight: 700; font-size: 1.05rem; }
.upl-page .upl-name a { color: var(--bs-body-color); text-decoration: none; }
.upl-page .upl-name a:hover { text-decoration: underline; }

.upl-page .upl-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .18rem .6rem;
    border-radius: 2rem;
    font-size: .78rem; font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
}
.upl-page a.upl-chip:hover { filter: brightness(.95); }

/* Отдача/скачка — визуальная полоса соотношения */
.upl-page .upl-traffic { margin: .9rem 0 .75rem; }
.upl-page .upl-traffic-row {
    display: flex; justify-content: space-between; font-size: .85rem; margin-bottom: .35rem;
}
.upl-page .upl-traffic-row .up   { color: var(--bs-success-text-emphasis); }
.upl-page .upl-traffic-row .down { color: var(--bs-danger-text-emphasis); }
.upl-page .upl-bar {
    display: flex; height: 7px; border-radius: 4px; overflow: hidden;
    background: var(--bs-tertiary-bg);
}
.upl-page .upl-bar .up   { background: var(--bs-success); }
.upl-page .upl-bar .down { background: var(--bs-danger); opacity: .75; }

.upl-page .upl-meta {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .84rem; color: var(--bs-secondary-color);
    padding-bottom: .7rem; margin-bottom: .6rem;
    border-bottom: 1px dashed var(--bs-border-color);
}
.upl-page .upl-meta strong { color: var(--bs-body-color); }

/* Торренты */
.upl-page .upl-torrents { max-height: 250px; overflow-y: auto; scrollbar-width: thin; }
.upl-page .upl-torrent {
    display: flex; align-items: flex-start; gap: .6rem;
    padding: .5rem .6rem; border-radius: .6rem;
    text-decoration: none; color: var(--bs-body-color);
    transition: background-color .15s;
}
.upl-page .upl-torrent:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.upl-page .upl-torrent-ico {
    width: 1.9rem; height: 1.9rem; border-radius: .5rem; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center; font-size: .85rem;
}
.upl-page .upl-torrent-name {
    font-size: .88rem; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.upl-page .upl-torrent-info {
    display: flex; flex-wrap: wrap; gap: .75rem;
    font-size: .74rem; color: var(--bs-secondary-color); margin-top: .1rem;
}
.upl-page .upl-torrent-info .s { color: var(--bs-success-text-emphasis); }
.upl-page .upl-torrent-info .l { color: var(--bs-danger-text-emphasis); }
.upl-page .upl-more {
    display: block; text-align: center; margin-top: .35rem;
    font-size: .82rem; color: var(--bs-secondary-color); text-decoration: none;
}
.upl-page .upl-more:hover { color: var(--bs-primary); }
.upl-page .upl-empty-small {
    text-align: center; color: var(--bs-secondary-color); padding: 1.1rem 0; font-size: .88rem;
}

/* Пустое состояние */
.upl-page .upl-empty {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 3rem 1.5rem; text-align: center;
}
.upl-page .upl-empty .upl-icon-square { width: 4.5rem; height: 4.5rem; font-size: 2rem; border-radius: 1.2rem; }

.upl-page .upl-no-match { display: none; }

/* ── Sticky bottom bar ───────────────────────────────────────────────────── */
.upl-page .upl-bottom-bar {
    position: sticky; bottom: 0; z-index: 5;
    margin-top: 1.25rem;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: .65rem 1rem;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .75rem;
    box-shadow: 0 -4px 16px rgba(0,0,0,.06);
    font-size: .88rem; color: var(--bs-secondary-color);
}

@media (max-width: 992px) { .upl-page .upl-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 576px) {
    .upl-page .upl-grid { grid-template-columns: 1fr; }
    .upl-page .upl-header { padding: 1rem; }
    .upl-page .upl-search { max-width: none; }
}
@media (prefers-reduced-motion: reduce) {
    .upl-page * { transition: none !important; }
}
</style>

<div class="upl-page container-lg py-4">

    <!-- Header -->
    <div class="upl-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="upl-icon-square upl-soft-primary"><i class="fa-solid fa-cloud-arrow-up"></i></span>
            <div>
                <h1 class="upl-title"><?= $h_site ?> Uploaders</h1>
                <p class="upl-subtitle">
                    <?= $combine ? 'All uploads by the selected user' : 'Uploaders, their traffic and latest releases' ?>
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$combine): ?>
            <a href="<?= $h_script ?>&amp;type=1" class="upl-pill<?= $type === 1 ? ' is-active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>Uploader group
            </a>
            <a href="<?= $h_script ?>&amp;type=2" class="upl-pill<?= $type === 2 ? ' is-active' : '' ?>">
                <i class="fa-solid fa-unlock-keyhole"></i>Can upload
            </a>
            <?php else: ?>
            <a href="<?= $h_script ?>&amp;type=<?= $type ?>" class="upl-pill">
                <i class="fa-solid fa-arrow-left"></i>Back to list
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="upl-kpis mb-3">
        <div class="upl-kpi">
            <span class="upl-icon-square upl-soft-primary"><i class="fa-solid fa-users"></i></span>
            <div>
                <div class="upl-kpi-value"><?= ts_nf($total_count) ?></div>
                <div class="upl-kpi-label"><?= $combine ? 'Uploader' : 'Uploaders total' ?></div>
            </div>
        </div>
        <div class="upl-kpi">
            <span class="upl-icon-square upl-soft-info"><i class="fa-solid fa-magnet"></i></span>
            <div>
                <div class="upl-kpi-value"><?= ts_nf($shownTorrents) ?></div>
                <div class="upl-kpi-label">Torrents on this page</div>
            </div>
        </div>
        <div class="upl-kpi">
            <span class="upl-icon-square upl-soft-success"><i class="fa-solid fa-seedling"></i></span>
            <div>
                <div class="upl-kpi-value"><?= ts_nf($sumSeeders) ?> <small class="fw-normal text-body-secondary" style="font-size:.8rem">/ <?= ts_nf($sumLeechers) ?></small></div>
                <div class="upl-kpi-label">Seeders / leechers</div>
            </div>
        </div>
        <div class="upl-kpi">
            <span class="upl-icon-square upl-soft-warning"><i class="fa-solid fa-hard-drive"></i></span>
            <div>
                <div class="upl-kpi-value"><?= $fmtSize($sumSize) ?></div>
                <div class="upl-kpi-label">Total release size</div>
            </div>
        </div>
    </div>

    <?php if (empty($uploaderRows)): ?>

    <div class="upl-empty">
        <span class="upl-icon-square upl-soft-primary mb-3"><i class="fa-solid fa-user-slash"></i></span>
        <h3 class="h5 fw-semibold mb-1">No uploaders found</h3>
        <p class="text-body-secondary mb-3">There are no uploaders in this section yet.</p>
        <?php if ($type === 1): ?>
        <span class="upl-chip upl-soft-info">
            <i class="fa-solid fa-circle-info"></i>UC_UPLOADER = <?= defined('UC_UPLOADER') ? (int)UC_UPLOADER : 'not defined' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <?php if (!$combine): ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <label class="upl-search mb-0">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="uplFilter" placeholder="Filter by username on this page…" autocomplete="off" aria-label="Filter uploaders">
        </label>
        <?php if ($multipage): ?><div><?= $multipage ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="upl-grid" id="uplGrid">
    <?php foreach ($uploaderRows as $uid => $res): ?>
    <?php
        $uid         = (int)$uid;
        $useravatar  = format_avatar($res['avatar'] ?? '', $res['avatardimensions'] ?? '');
        $avatarImg   = (!empty($useravatar['image']) && !str_starts_with($useravatar['image'], '<'))
            ? '<img src="' . htmlspecialchars($useravatar['image'], ENT_QUOTES, 'UTF-8') . '" class="upl-avatar" alt="" loading="lazy">'
            : '<div class="upl-avatar-ph"><i class="fa-solid fa-user"></i></div>';

        $lastSeen    = max((int)$res['lastactive'], (int)$res['lastvisit']);
        $isOnline    = $lastSeen > 0 && (TIMENOW - $lastSeen) < $ONLINE_WINDOW;

        $up          = (int)$res['uploaded'];
        $down        = (int)$res['downloaded'];
        $traffic     = $up + $down;
        $upPct       = $traffic > 0 ? round($up / $traffic * 100, 1) : 0;

        $ratio_html  = get_user_ratio($up, $down);
        $ratio_raw   = (float)strip_tags((string)$ratio_html);
        $ratio_class = str_contains((string)$ratio_html, '∞') || $ratio_raw >= 1.0
            ? 'success' : ($ratio_raw >= 0.5 ? 'warning' : 'danger');

        $torrents    = $torrentsByOwner[$uid] ?? [];
        $tcount      = $torrentCounts[$uid] ?? 0;
        $safeName    = htmlspecialchars_uni((string)$res['username']);
        $filterName  = htmlspecialchars(mb_strtolower((string)$res['username']), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="upl-card" data-name="<?= $filterName ?>">
        <div class="d-flex align-items-center gap-3">
            <div class="upl-avatar-wrap">
                <?= $avatarImg ?>
                <?php if ($isOnline): ?><span class="upl-online-dot" title="Online"></span><?php endif; ?>
            </div>
            <div class="min-w-0 flex-grow-1">
                <div class="upl-name text-truncate">
                    <a href="<?= $BASE ?>/<?= get_profile_link($uid) ?>"><?= format_name($safeName, $res['usergroup']) ?></a>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <span class="upl-chip upl-soft-<?= $ratio_class ?>" title="Ratio">
                        <i class="fa-solid fa-scale-balanced"></i><?= $ratio_html ?>
                    </span>
                    <?php if ($lastSeen): ?>
                    <span class="upl-chip <?= $isOnline ? 'upl-soft-success' : 'upl-soft-info' ?>" title="Last seen">
                        <i class="fa-solid <?= $isOnline ? 'fa-signal' : 'fa-clock' ?>"></i><?= $isOnline ? 'Online' : my_datee('relative', $lastSeen) ?>
                    </span>
                    <?php else: ?>
                    <span class="upl-chip upl-soft-danger"><i class="fa-solid fa-ban"></i>Never</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="upl-traffic">
            <div class="upl-traffic-row">
                <span class="up"><i class="fa-solid fa-arrow-up me-1"></i><?= $fmtSize($up) ?></span>
                <span class="down"><?= $fmtSize($down) ?><i class="fa-solid fa-arrow-down ms-1"></i></span>
            </div>
            <div class="upl-bar" role="img" aria-label="Uploaded <?= $upPct ?>% of total traffic">
                <span class="up" style="width: <?= $upPct ?>%"></span>
                <span class="down" style="width: <?= $traffic > 0 ? 100 - $upPct : 0 ?>%"></span>
            </div>
        </div>

        <div class="upl-meta">
            <span><i class="fa-solid fa-box-archive me-1"></i><strong><?= ts_nf($tcount) ?></strong> <?= $tcount === 1 ? 'upload' : 'uploads' ?></span>
            <?php if (!$combine && $tcount > 0): ?>
            <a href="<?= $h_script ?>&amp;uploader=<?= $uid ?>" class="upl-chip upl-soft-primary">
                <i class="fa-solid fa-list-ul"></i>All uploads
            </a>
            <?php endif; ?>
        </div>

        <?php if ($torrents): ?>
        <div class="upl-torrents">
            <?php foreach ($torrents as $t): ?>
            <?php
                $seed  = (int)$t['seeders'];
                $icoCl = $seed > 0 ? 'upl-soft-success' : 'upl-soft-danger';
            ?>
            <a href="<?= $BASE ?>/<?= get_torrent_link((int)$t['id']) ?>" class="upl-torrent">
                <span class="upl-torrent-ico <?= $icoCl ?>" title="<?= $seed > 0 ? 'Alive' : 'No seeders' ?>">
                    <i class="fa-solid <?= $seed > 0 ? 'fa-file-circle-check' : 'fa-file-circle-exclamation' ?>"></i>
                </span>
                <div class="min-w-0 flex-grow-1">
                    <div class="upl-torrent-name"><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="upl-torrent-info">
                        <span><i class="fa-regular fa-calendar me-1"></i><?= my_datee($dateformat ?? '', (int)$t['added']) ?></span>
                        <span><i class="fa-solid fa-weight-hanging me-1"></i><?= $fmtSize((int)$t['size']) ?></span>
                        <span class="s"><i class="fa-solid fa-arrow-up me-1"></i><?= ts_nf($seed) ?></span>
                        <span class="l"><i class="fa-solid fa-arrow-down me-1"></i><?= ts_nf((int)$t['leechers']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$combine && $tcount > 5): ?>
        <a href="<?= $h_script ?>&amp;uploader=<?= $uid ?>" class="upl-more">
            <i class="fa-solid fa-ellipsis me-1"></i>+<?= ts_nf($tcount - 5) ?> more
        </a>
        <?php endif; ?>
        <?php else: ?>
        <div class="upl-empty-small">
            <i class="fa-solid fa-inbox d-block mb-1 fs-5 opacity-50"></i>No uploads yet
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="upl-empty upl-no-match mt-2" id="uplNoMatch">
        <span class="upl-icon-square upl-soft-warning mb-3"><i class="fa-solid fa-magnifying-glass-minus"></i></span>
        <h3 class="h6 fw-semibold mb-0">Nobody on this page matches the filter</h3>
    </div>

    <?php endif; ?>

    <div class="upl-bottom-bar">
        <span>
            <i class="fa-solid fa-layer-group me-1"></i>
            Page <strong class="text-body"><?= $page ?></strong> of <strong class="text-body"><?= $pages ?></strong>
            — <?= ts_nf(count($uploaderRows)) ?> shown
        </span>
        <?php if ($multipage): ?><div><?= $multipage ?></div><?php endif; ?>
    </div>

</div>

<script>
(function () {
    var input = document.getElementById('uplFilter');
    var grid  = document.getElementById('uplGrid');
    var none  = document.getElementById('uplNoMatch');
    if (!input || !grid) return;
    var cards = grid.querySelectorAll('.upl-card');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        cards.forEach(function (c) {
            var hit = q === '' || (c.getAttribute('data-name') || '').indexOf(q) !== -1;
            c.style.display = hit ? '' : 'none';
            if (hit) visible++;
        });
        if (none) none.style.display = visible === 0 ? 'block' : 'none';
    });
})();
</script>

<?php stdfoot(); ?>