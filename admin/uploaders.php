<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center mt-4">Direct initialization not allowed.</div>');
}

define('U_VERSION', '0.4');

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

$perpage = 25;
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
            if ($combine || ($torrentCounts[$oid] <= 5)) {
                $torrentsByOwner[$oid][] = $t;
            }
        }
    }
}

stdhead($SITENAME . ' — Uploader List');

$BASE     = htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8');
$h_script = htmlspecialchars($_this_script_, ENT_QUOTES, 'UTF-8');
$h_site   = htmlspecialchars($SITENAME, ENT_QUOTES, 'UTF-8');
?>

<style>
/* ── Hero banner ───────────────────────────────────────────────────────────── */
.uploaders-hero {
    background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
    box-shadow: 0 4px 24px rgba(13,110,253,.25);
}
.stat-pill {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 2rem;
    padding: .35rem 1rem;
    font-size: .875rem;
    color: #fff;
}

/* ── Card grid ─────────────────────────────────────────────────────────────── */
.uploaders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}
.uploader-card {
    border-radius: 1rem !important;
    transition: transform .25s ease, box-shadow .25s ease;
}
.uploader-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,.12) !important;
}

/* ── Avatar ────────────────────────────────────────────────────────────────── */
.uploader-avatar {
    width: 52px; height: 52px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--bs-border-color);
    flex-shrink: 0;
    transition: border-color .2s;
}
.uploader-card:hover .uploader-avatar { border-color: #0d6efd; }

.uploader-avatar-placeholder {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: var(--bs-tertiary-bg);
    border: 2px solid var(--bs-border-color);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: var(--bs-secondary-color);
    font-size: 1.25rem;
}

/* ── Torrent list ──────────────────────────────────────────────────────────── */
.torrent-list { max-height: 220px; overflow-y: auto; scrollbar-width: thin; }
.torrent-item {
    background: var(--bs-tertiary-bg);
    transition: background .15s ease;
}
.torrent-item:hover { background: var(--bs-secondary-bg) !important; }

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 576px) {
    .uploaders-grid { grid-template-columns: 1fr; }
    .uploaders-hero { padding: 1.25rem !important; }
}
</style>

<div class="container-lg py-4">

    <div class="uploaders-hero rounded-4 mb-4 p-4 text-white position-relative overflow-hidden">
        <div class="position-relative">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1">
                        <i class="fas fa-users me-2"></i><?= $h_site ?> Uploaders
                    </h1>
                    <p class="mb-0 opacity-75 small">
                        <?= $combine ? 'Uploads by selected user' : 'Overview of all uploaders and their statistics' ?>
                    </p>
                </div>
                <?php if (!$combine): ?>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= $h_script ?>&amp;type=1" class="btn btn-sm <?= $type===1 ? 'btn-light' : 'btn-outline-light' ?>">
                        <i class="fas fa-user-group me-1"></i>UC_Uploaders
                    </a>
                    <a href="<?= $h_script ?>&amp;type=2" class="btn btn-sm <?= $type===2 ? 'btn-light' : 'btn-outline-light' ?>">
                        <i class="fas fa-upload me-1"></i>Can Upload
                    </a>
                </div>
                <?php else: ?>
                <a href="<?= $h_script ?>&amp;type=<?= $type ?>" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i>Back to List
                </a>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-4 mt-3 flex-wrap">
                <div class="stat-pill">
                    <i class="fas fa-users me-1 opacity-75"></i>
                    <span class="fw-bold"><?= ts_nf($total_count) ?></span>
                    <span class="opacity-75 ms-1">uploaders</span>
                </div>
                <div class="stat-pill">
                    <i class="fas fa-file-alt me-1 opacity-75"></i>
                    <span class="fw-bold"><?= ts_nf(array_sum($torrentCounts)) ?></span>
                    <span class="opacity-75 ms-1">torrents shown</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($multipage): ?>
    <div class="mb-3"><?= $multipage ?></div>
    <?php endif; ?>

    <?php if (empty($uploaderRows)): ?>
    
    <div class="card border-0 rounded-4 overflow-hidden" style="background: #fff;">
        <div class="card-body p-5 text-center">
            <div class="empty-state-icon mb-4 position-relative d-inline-block">
                <i class="fas fa-folder-open fa-5x text-primary" style="opacity: 0.2;"></i>
                <i class="fas fa-user-slash fa-2x text-danger position-absolute" style="margin-top: -60px; margin-left: -10px; opacity: 0.6;"></i>
            </div>
            <h3 class="fw-light text-dark mb-2">No Uploaders Found</h3>
            <p class="text-muted mb-4">Looks like there are no uploaders in this section yet</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">
                    <i class="fas fa-clock me-1"></i> Check back later
                </span>
                <?php if ($type === 1): ?>
                <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">
                    <i class="fas fa-info-circle me-1"></i> UC_UPLOADER = <?= defined('UC_UPLOADER') ? UC_UPLOADER : 'not defined' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php else: ?>

    <div class="uploaders-grid">
    <?php foreach ($uploaderRows as $uid => $res): ?>
    <?php
        $useravatar  = format_avatar($res['avatar'] ?? '', $res['avatardimensions'] ?? '');
        $avatarImg   = (!empty($useravatar['image']) && !str_starts_with($useravatar['image'], '<'))
            ? '<img src="' . htmlspecialchars($useravatar['image'], ENT_QUOTES, 'UTF-8') . '" class="uploader-avatar" alt="">'
            : '<div class="uploader-avatar-placeholder"><i class="fas fa-user"></i></div>';
        $lastSeen    = max((int)$res['lastactive'], (int)$res['lastvisit']);
        $lastSeenHtml = $lastSeen
            ? '<span class="badge bg-body-secondary text-body border"><i class="far fa-clock me-1"></i>' . my_datee('relative', $lastSeen) . '</span>'
            : '<span class="badge bg-secondary">Never</span>';
        $ratio_html  = get_user_ratio((int)$res['uploaded'], (int)$res['downloaded']);
        $ratio_raw   = (float)strip_tags($ratio_html);
        $ratio_class = str_contains($ratio_html, '∞') || $ratio_raw >= 1.0 ? 'success' : ($ratio_raw >= 0.5 ? 'warning' : 'danger');
        $torrents    = $torrentsByOwner[$uid] ?? [];
        $tcount      = $torrentCounts[$uid] ?? 0;
    ?>
    <div class="uploader-card card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <?= $avatarImg ?>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-bold text-truncate">
                        <a href="<?= $BASE ?>/<?= get_profile_link((int)$uid) ?>" class="text-decoration-none text-body">
                            <?= format_name($res['username'], $res['usergroup']) ?>
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <span class="badge bg-<?= $ratio_class ?>-subtle text-<?= $ratio_class ?> border border-<?= $ratio_class ?>">
                            <i class="fas fa-chart-line me-1"></i><?= $ratio_html ?>
                        </span>
                        <?php if (!$combine): ?>
                        <a href="<?= $h_script ?>&amp;uploader=<?= $uid ?>" class="badge bg-primary-subtle text-primary border border-primary text-decoration-none">
                            <i class="fas fa-list me-1"></i>All uploads
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3 small text-muted">
                <div><?= $lastSeenHtml ?></div>
                <div><i class="fas fa-upload me-1 text-primary"></i><strong class="text-body"><?= ts_nf($tcount) ?></strong> uploads</div>
            </div>

            <?php if ($torrents): ?>
            <div class="torrent-list">
                <?php foreach ($torrents as $t): ?>
                <a href="<?= $BASE ?>/<?= get_torrent_link((int)$t['id']) ?>"
                   class="torrent-item text-decoration-none d-flex align-items-start gap-2 p-2 rounded mb-1">
                    <i class="fas fa-file-alt text-primary mt-1 flex-shrink-0"></i>
                    <div class="min-w-0">
                        <div class="text-truncate fw-medium text-body small">
                            <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <i class="far fa-calendar me-1"></i><?= my_datee($dateformat ?? '', (int)$t['added']) ?>
                            <span class="ms-2 text-success"><i class="fas fa-seedling me-1"></i><?= ts_nf((int)$t['seeders']) ?></span>
                            <span class="ms-1 text-danger"><i class="fas fa-download me-1"></i><?= ts_nf((int)$t['leechers']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (!$combine && $tcount > 5): ?>
                <div class="text-center mt-1">
                    <a href="<?= $h_script ?>&amp;uploader=<?= $uid ?>" class="small text-muted">
                        +<?= ts_nf($tcount - 5) ?> more…
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-3 small">
                <i class="fas fa-inbox d-block mb-1 opacity-50"></i>No uploads yet
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <?php if ($multipage): ?>
    <div class="mt-4"><?= $multipage ?></div>
    <?php endif; ?>

</div>

<?php stdfoot(); ?>