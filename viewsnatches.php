<?php
declare(strict_types=1);


define('IN_MYBB', 1);

require_once 'global.php';
include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_pm.php';

gzip();

define('VS_VERSION', '1.3.9');

// ── Авторизация ───────────────────────────────────────────
$is_mod = is_mod($mybb->usergroup);
if ($snatchmod == 'no' && !$is_mod) {
    stderr($lang->global['notavailable']);
}

$lang->load('viewsnatches');

$id = (int)($_GET['id'] ?? 0);
int_check($id, true);

// ── Функции ───────────────────────────────────────────────

function getExtendedStats(int $torrent_id): array
{
    global $db;

    $defaults = [
        'ratio_poor'           => 0,
        'ratio_fair'           => 0,
        'ratio_good'           => 0,
        'ratio_excellent'      => 0,
        'unique_users'         => 0,
        'avg_ratio'            => '0.00',
        'total_speed'          => 0,
        'completion_rate'      => 100,
        'total_seedtime'       => 0,
        'avg_seedtime'         => 0,
        'users_over_24h'       => 0,
        'warned_users'         => 0,
        'clean_users'          => 0,
        'seedtime_distribution' => ['poor' => 0, 'fair' => 0, 'good' => 0, 'excellent' => 0],
    ];

    $res = $db->sql_query_prepared("
        SELECT
            userid,
            SUM(uploaded)   AS total_uploaded,
            SUM(downloaded) AS total_downloaded,
            SUM(seedtime)   AS total_seedtime,
            MAX(warned)     AS warned,
            CASE WHEN SUM(downloaded) = 0 THEN 999
                 ELSE SUM(uploaded) / SUM(downloaded) END AS user_ratio,
            AVG(upspeed)    AS avg_upspeed,
            AVG(downspeed)  AS avg_downspeed
        FROM snatched
        WHERE torrentid = ? AND finished = 'yes'
        GROUP BY userid",
        [$torrent_id]
    );

    $stats         = $defaults;
    $totalRatio    = 0.0;
    $totalSeedtime = 0;

    while ($row = $db->fetch_array($res)) {
        $stats['unique_users']++;
        $ratio    = (float)$row['user_ratio'];
        $seedtime = (int)$row['total_seedtime'];
        $warned   = (int)$row['warned'];

        $totalRatio    += $ratio;
        $totalSeedtime += $seedtime;
        $stats['total_speed'] += ((float)$row['avg_upspeed'] + (float)$row['avg_downspeed']);

        // Ratio distribution
        if      ($ratio < 0.5) $stats['ratio_poor']++;
        elseif  ($ratio < 1.0) $stats['ratio_fair']++;
        elseif  ($ratio < 2.0) $stats['ratio_good']++;
        else                   $stats['ratio_excellent']++;

        // Seedtime distribution
        if      ($seedtime < 3600)  $stats['seedtime_distribution']['poor']++;
        elseif  ($seedtime < 21600) $stats['seedtime_distribution']['fair']++;
        elseif  ($seedtime < 86400) $stats['seedtime_distribution']['good']++;
        else {
            $stats['seedtime_distribution']['excellent']++;
            $stats['users_over_24h']++;
        }

        // H&R distribution
        if      ($warned >= 1) $stats['warned_users']++;
        else                   $stats['clean_users']++;
    }

    $n = $stats['unique_users'];
    $stats['avg_ratio']      = $n > 0 ? ts_nf($totalRatio / $n, 2) : '0.00';
    $stats['total_seedtime'] = $totalSeedtime;
    $stats['avg_seedtime']   = $n > 0 ? (int)($totalSeedtime / $n) : 0;
    $stats['total_speed']   *= $n;

    return $stats;
}

function sendPmToUsers(array $userIds, string $subject, string $messageTemplate,
                       string $torrentName, string $torrentUrl, int $senderId): int
{
    global $db;
    if (empty($userIds)) return 0;

    $userIds   = array_map('intval', $userIds);
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $res       = $db->sql_query_prepared("SELECT id, username FROM users WHERE id IN ({$placeholders})", $userIds);
    $sentCount = 0;

    while ($user = $db->fetch_array($res)) {
        $msg = str_replace(
            ['{username}', '{torrentname}', '{torrenturl}'],
            [$user['username'], $torrentName, $torrentUrl],
            $messageTemplate
        );
        send_pm([
            'subject' => $subject,
            'message' => $msg,
            'touid'   => (int)$user['id'],
        ], $senderId, true);
        $sentCount++;
    }
    return $sentCount;
}

function handleMassAction(array $post, int $torrentId): void
{
    global $db, $BASEURL, $CURUSER;

    $action        = $post['mass_action'] ?? '';
    $selectedUsers = json_decode($post['selected_users'] ?? '[]', true);

    if (empty($selectedUsers) || !is_array($selectedUsers)) {
        header("Location: {$_SERVER['SCRIPT_NAME']}?id={$torrentId}");
        exit;
    }

    $res     = $db->sql_query_prepared("SELECT name FROM torrents WHERE id = ?", [$torrentId]);
    $torrent = $db->fetch_array($res);
    if (!$torrent) {
        header("Location: {$_SERVER['SCRIPT_NAME']}?id={$torrentId}");
        exit;
    }

    $torrentName = $torrent['name'];
    $torrentUrl  = '[url=' . $BASEURL . '/' . get_torrent_link($torrentId) . ']' . $torrentName . '[/url]';

    switch ($action) {
        case 'send_message':
            $subject = trim($post['subject'] ?? 'Regarding torrent: ' . $torrentName);
            $message = trim($post['message'] ?? '');
            if ($message) {
                sendPmToUsers($selectedUsers, $subject, $message, $torrentName, $torrentUrl, (int)$CURUSER['id']);
            }
            break;

        case 'reseed_request':
            $subject = "Reseed request (TID: {$torrentId})";
            $message = "Hello {username},\n\nWe noticed that you have downloaded {torrenturl} but are no longer seeding it.\n"
                     . "Could you please help the community by reseeding it? Thank you!\n\nBest regards, Staff";
            sendPmToUsers($selectedUsers, $subject, $message, $torrentName, $torrentUrl, (int)$CURUSER['id']);
            break;
    }

    header("Location: {$_SERVER['SCRIPT_NAME']}?id={$torrentId}");
    exit;
}

function exportSnatchData(int $torrentId, string $format = 'csv'): void
{
    global $db;

    $res = $db->sql_query_prepared("
        SELECT
            u.username, u.email,
            sn.user_sum_uploaded   AS uploaded,
            sn.user_sum_downloaded AS downloaded,
            CASE WHEN sn.user_sum_downloaded = 0 THEN 0
                 ELSE ROUND(sn.user_sum_uploaded / sn.user_sum_downloaded, 2) END AS ratio,
            sn.last_completedat AS completed,
            sn.last_action,
            sn.seeder,
            sn.connectable,
            sn.last_seedtime  AS seed_time,
            sn.last_leechtime AS leech_time,
            sn.warned
        FROM users u
        INNER JOIN (
            SELECT userid,
                SUM(uploaded)        AS user_sum_uploaded,
                SUM(downloaded)      AS user_sum_downloaded,
                MAX(completedat)     AS last_completedat,
                MAX(last_action)     AS last_action,
                MAX(seeder='yes')    AS seeder,
                MAX(connectable='yes') AS connectable,
                MAX(seedtime)        AS last_seedtime,
                MAX(leechtime)       AS last_leechtime,
                MAX(warned)          AS warned
            FROM snatched
            WHERE torrentid = ? AND finished = 'yes'
            GROUP BY userid
        ) AS sn ON sn.userid = u.id
        ORDER BY sn.last_completedat DESC",
        [$torrentId]
    );

    $data = [];
    while ($row = $db->fetch_array($res)) $data[] = $row;

    if ($format === 'json') {
        header('Content-Type: application/json');
        header("Content-Disposition: attachment; filename=\"snatch_{$torrentId}.json\"");
        echo json_encode($data, JSON_PRETTY_PRINT);
    } else {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"snatch_{$torrentId}.csv\"");
        $out = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $row) fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}

// ── Экспорт (до любого вывода) ────────────────────────────
if (isset($_GET['export']) && $is_mod) {
    exportSnatchData($id, $_GET['export'] ?? 'csv');
}

// ── Массовые действия ─────────────────────────────────────
if (isset($_POST['mass_action']) && $is_mod) {
    handleMassAction($_POST, $id);
}

// ── Удаление записи ───────────────────────────────────────
if (isset($_GET['delete']) && ($usergroups['cansettingspanel'] ?? '') == '1') {
    $delUserId = (int)($_GET['userid'] ?? 0);
    if (is_valid_id($delUserId)) {
        $db->sql_query_prepared(
            "DELETE FROM snatched WHERE userid = ? AND torrentid = ?",
            [$delUserId, $id]
        );
        header("Location: {$_SERVER['SCRIPT_NAME']}?id={$id}");
        exit;
    }
}

// ── Данные торрента ───────────────────────────────────────
$res         = $db->sql_query_prepared(
    "SELECT t.name, t.size, t.category, c.name AS category_name
     FROM torrents t LEFT JOIN categories c ON t.category = c.id
     WHERE t.id = ?",
    [$id]
);
$torrentInfo = $db->fetch_array($res);

if (!$torrentInfo) stderr($lang->global['notavailable'], 'Torrent not found');


// ── Статистика ────────────────────────────────────────────
$extStats = getExtendedStats($id);

// Banned = пользователи с u.enabled = 'no'
$resDisabled = $db->sql_query_prepared(
    "SELECT COUNT(DISTINCT s.userid) AS cnt
     FROM snatched s
     INNER JOIN users u ON u.id = s.userid
     WHERE s.torrentid = ? AND s.finished = 'yes' AND u.enabled = 'no'",
    [$id]
);
$extStats['banned_users'] = (int)$db->fetch_field($resDisabled, 'cnt');

$resCount = $db->sql_query_prepared(
    "SELECT COUNT(*) AS cnt FROM snatched WHERE finished='yes' AND torrentid=?", [$id]
);
$count = (int)$db->fetch_field($resCount, 'cnt');

$resStats = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total_snatches,
            SUM(uploaded)    AS total_uploaded,
            SUM(downloaded)  AS total_downloaded,
            AVG(seedtime)    AS avg_seedtime,
            SUM(seeder='yes') AS current_seeders
     FROM snatched WHERE torrentid=?",
    [$id]
);
$stats = $db->fetch_array($resStats);

// ── Пагинация ─────────────────────────────────────────────
$perpage = isset($ts_perpage) && $ts_perpage > 0 ? (int)$ts_perpage : 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$start   = ($page - 1) * $perpage;

// ── Сортировка ────────────────────────────────────────────
$allowedOrders = ['username','uploaded','downloaded','completedat','last_action',
                  'seeder','seedtime','leechtime','connectable','ratio','warned'];
$order    = in_array($_GET['order'] ?? '', $allowedOrders, true) ? $_GET['order'] : 'last_completedat';
$type     = strtoupper($_GET['type'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
$typeLink = $type === 'ASC' ? '&amp;type=DESC' : '&amp;type=ASC';

$orderBy = match($order) {
    'username' => 'users.username',
    'ratio'    => '(sn.user_sum_uploaded / sn.user_sum_downloaded)',
    'warned'   => 'sn.warned',
    default    => 'sn.' . $order,
};

// ── Фильтры ───────────────────────────────────────────────
$filterSql    = '';
$filterParams = [$id];
$activeFilters = [];

if (in_array($_GET['filter_seeder'] ?? '', ['yes','no'], true)) {
    $filterSql         .= ' AND sn.seeder = ?';
    $filterParams[]     = $_GET['filter_seeder'];
    $activeFilters['filter_seeder'] = $_GET['filter_seeder'];
}
if (in_array($_GET['filter_connectable'] ?? '', ['yes','no'], true)) {
    $filterSql         .= ' AND sn.connectable = ?';
    $filterParams[]     = $_GET['filter_connectable'];
    $activeFilters['filter_connectable'] = $_GET['filter_connectable'];
}
if (isset($_GET['filter_ratio'])) {
    $rf = $_GET['filter_ratio'];
    if ($rf === 'poor') {
        $filterSql .= ' AND (sn.user_sum_uploaded / sn.user_sum_downloaded) < 0.5';
        $activeFilters['filter_ratio'] = 'poor';
    } elseif ($rf === 'good') {
        $filterSql .= ' AND (sn.user_sum_uploaded / sn.user_sum_downloaded) >= 1.0';
        $activeFilters['filter_ratio'] = 'good';
    }
}

// ── Фильтр H&R ───────────────────────────────────────────
if (isset($_GET['filter_warned'])) {
    match($_GET['filter_warned']) {
        'warned' => $filterSql .= ' AND sn.warned BETWEEN 1 AND 2',
        'banned' => $filterSql .= ' AND sn.warned >= 3',
        'clean'  => $filterSql .= ' AND sn.warned = 0',
        default  => null,
    };
    if (in_array($_GET['filter_warned'], ['warned','banned','clean'], true)) {
        $activeFilters['filter_warned'] = $_GET['filter_warned'];
    }
}

$filterQs  = $activeFilters ? '&' . http_build_query($activeFilters) : '';
$pageUrl   = "{$_SERVER['SCRIPT_NAME']}?id={$id}{$typeLink}&order={$order}{$filterQs}";
$multipage = multipage($count, $perpage, $page, $pageUrl);

// ── Данные для JS (до stdhead) ────────────────────────────
$ratioData  = [(float)$extStats['ratio_poor'], (float)$extStats['ratio_fair'],
               (float)$extStats['ratio_good'], (float)$extStats['ratio_excellent']];
$hnrData    = [(int)$extStats['clean_users'], (int)$extStats['warned_users'], (int)$extStats['banned_users']];
$scriptName = $_SERVER['SCRIPT_NAME'];

// ── HTML ──────────────────────────────────────────────────
stdhead($lang->viewsnatches['headmessage']);
?>

<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/chart.js"></script>

<!-- Модальное окно деталей пользователя -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($lang->viewsnatches['modal_user_details'] ?? 'User Snatch Details', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent"><?= htmlspecialchars($lang->viewsnatches['modal_loading'] ?? 'Loading...', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
        </div>
    </div>
</div>

<?php if ($is_mod): ?>
<!-- Панель модератора -->
<div class="container mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <h6 class="mb-0 text-primary"><i class="fas fa-tools me-2"></i><?= htmlspecialchars($lang->viewsnatches['mod_tools'] ?? 'Moderator Tools', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h6>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control" id="searchInput"
                               placeholder="<?= htmlspecialchars($lang->viewsnatches['search_placeholder'] ?? 'Search username...', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                               oninput="quickSearch(this.value)">
                        <span class="input-group-text">
                            <span id="visibleCount" class="badge bg-success"><?= $count ?></span>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                        <button class="btn btn-sm btn-outline-info" onclick="toggleAdvancedFilters()">
                            <i class="fas fa-sliders-h me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_filters'] ?? 'Filters', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            <?php if ($activeFilters): ?>
                            <span class="badge bg-info ms-1"><?= count($activeFilters) ?></span>
                            <?php endif; ?>
                        </button>
                        <a href="<?= htmlspecialchars($BASEURL) ?>/takereseed.php?reseedid=<?= $id ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-seedling me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_reseed'] ?? 'Reseed', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <a href="<?= htmlspecialchars($BASEURL) ?>/admin/index.php?act=ts_hit_and_run&amp;torrentid=<?= $id ?>"
                           class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-running me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_hnr'] ?? 'H&amp;R', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-file-export me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_export'] ?? 'Export', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?id=<?= $id ?>&export=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="?id=<?= $id ?>&export=json">JSON</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Расширенные фильтры -->
            <div class="row mt-3 <?= $activeFilters ? '' : 'd-none' ?>" id="advancedFilters">
                <div class="col-md-12">
                    <form method="GET" class="row g-2">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="col-md-2">
                            <select name="filter_seeder" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value=""><?= htmlspecialchars($lang->viewsnatches['filter_all_status'] ?? 'All Status', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="yes" <?= ($activeFilters['filter_seeder'] ?? '') === 'yes' ? 'selected' : '' ?>><?= htmlspecialchars($lang->viewsnatches['filter_seeders_only'] ?? 'Seeders Only', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="no"  <?= ($activeFilters['filter_seeder'] ?? '') === 'no'  ? 'selected' : '' ?>><?= htmlspecialchars($lang->viewsnatches['filter_non_seeders'] ?? 'Non-Seeders', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_connectable" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value=""><?= htmlspecialchars($lang->viewsnatches['filter_all_connectivity'] ?? 'All Connectivity', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="yes" <?= ($activeFilters['filter_connectable'] ?? '') === 'yes' ? 'selected' : '' ?>><?= htmlspecialchars($lang->viewsnatches['filter_connectable'] ?? 'Connectable', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="no"  <?= ($activeFilters['filter_connectable'] ?? '') === 'no'  ? 'selected' : '' ?>><?= htmlspecialchars($lang->viewsnatches['filter_not_connectable'] ?? 'Not Connectable', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_ratio" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value=""><?= htmlspecialchars($lang->viewsnatches['filter_all_ratios'] ?? 'All Ratios', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="poor" <?= ($activeFilters['filter_ratio'] ?? '') === 'poor' ? 'selected' : '' ?>>Ratio &lt; 0.5</option>
                                <option value="good" <?= ($activeFilters['filter_ratio'] ?? '') === 'good' ? 'selected' : '' ?>>Ratio ≥ 1.0</option>
                            </select>
                        </div>
                        <!-- H&R фильтр -->
                        <div class="col-md-2">
                            <select name="filter_warned" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value=""><?= htmlspecialchars($lang->viewsnatches['filter_all_hnr'] ?? 'All H&amp;R', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <option value="clean"  <?= ($activeFilters['filter_warned'] ?? '') === 'clean'  ? 'selected' : '' ?>>Clean</option>
                                <option value="warned" <?= ($activeFilters['filter_warned'] ?? '') === 'warned' ? 'selected' : '' ?>>Warned</option>
                                <option value="banned" <?= ($activeFilters['filter_warned'] ?? '') === 'banned' ? 'selected' : '' ?>>Banned</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <a href="?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_clear'] ?? 'Clear', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_apply'] ?? 'Apply', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Массовые действия -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="d-flex align-items-center gap-3 p-2 bg-light rounded">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="selectAll"
                                   onchange="selectAllUsers(this.checked)">
                            <label class="form-check-label small" for="selectAll"><?= htmlspecialchars($lang->viewsnatches['select_all'] ?? 'Select All', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></label>
                        </div>
                        <span class="small text-muted"><?= htmlspecialchars($lang->viewsnatches['selected_count'] ?? 'Selected:', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> <strong id="selectedCount">0</strong></span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="sendMassMessage()">
                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_message'] ?? 'Message', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </button>
                            <button class="btn btn-outline-warning" onclick="quickAction('reseed_request')">
                                <i class="fas fa-seedling me-1"></i><?= htmlspecialchars($lang->viewsnatches['btn_request_reseed'] ?? 'Request Reseed', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><br>
<?php endif; ?>

<div class="container mt-4">

    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-gradient-primary mb-1">
                <i class="fas fa-users me-2"></i><?= htmlspecialchars($lang->viewsnatches['page_title'] ?? 'Snatch List', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($torrentInfo['name']) ?></p>
            <div class="small text-muted">
                <i class="fas fa-hdd me-1"></i><?= htmlspecialchars($lang->viewsnatches['torrent_size'] ?? 'Size', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>: <?= mksize($torrentInfo['size']) ?> |
                <i class="fas fa-tag me-1"></i><?= htmlspecialchars($lang->viewsnatches['torrent_category'] ?? 'Category', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>: <?= htmlspecialchars($torrentInfo['category_name'] ?? 'Unknown') ?>
            </div>
        </div>
        <div class="text-end">
            <span class="badge bg-primary rounded-pill fs-6">
                <i class="fas fa-download me-1"></i><?= $count ?> snatches
            </span>
        </div>
    </div>

    <!-- Графики -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i><?= htmlspecialchars($lang->viewsnatches['ratio_distribution'] ?? 'Ratio Distribution', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h6>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <canvas id="ratioChart" width="260" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row g-3 h-100">
                <!-- Quick Stats -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i><?= htmlspecialchars($lang->viewsnatches['quick_stats'] ?? 'Quick Stats', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <?php
                                $quickStats = [
                                    [$extStats['unique_users'],              $lang->viewsnatches['stat_unique_users'] ?? 'Unique Users', 'success'],
                                    [$extStats['avg_ratio'],                 $lang->viewsnatches['stat_avg_ratio']    ?? 'Avg Ratio',    'primary'],
                                    [mksize($extStats['total_speed']) . '/s',$lang->viewsnatches['stat_total_speed']  ?? 'Total Speed',  'info'],
                                    [$extStats['completion_rate'] . '%',     $lang->viewsnatches['stat_completion']   ?? 'Completion',   'warning'],
                                ];
                                foreach ($quickStats as [$val, $label, $color]):
                                ?>
                                <div class="col-6 mb-2">
                                    <div class="text-<?= $color ?>">
                                        <h5 class="mb-0"><?= $val ?></h5>
                                        <small class="text-muted"><?= $label ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- H&R Stats -->
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2 text-warning"></i><?= htmlspecialchars($lang->viewsnatches['hnr_status'] ?? 'H&R Status', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row text-center">
                                <div class="col-4">
                                    <a href="?id=<?= $id ?>&filter_warned=clean" class="text-decoration-none">
                                        <h5 class="text-success mb-0"><?= $extStats['clean_users'] ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($lang->viewsnatches['hnr_clean'] ?? 'Clean', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></small>
                                    </a>
                                </div>
                                <div class="col-4">
                                    <a href="?id=<?= $id ?>&filter_warned=warned" class="text-decoration-none">
                                        <h5 class="text-warning mb-0"><?= $extStats['warned_users'] ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($lang->viewsnatches['hnr_warned'] ?? 'Warned', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></small>
                                    </a>
                                </div>
                                <div class="col-4">
                                    <a href="?id=<?= $id ?>&filter_warned=banned" class="text-decoration-none">
                                        <h5 class="text-danger mb-0"><?= $extStats['banned_users'] ?></h5>
                                        <small class="text-muted"><?= htmlspecialchars($lang->viewsnatches['hnr_banned'] ?? 'Banned', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></small>
                                    </a>
                                </div>
                            </div>
                            <!-- Прогресс-бар H&R -->
                            <?php
                            $total_hnr = max($extStats['unique_users'], 1);
                            $pct_clean  = round($extStats['clean_users']  / $total_hnr * 100);
                            $pct_warned = round($extStats['warned_users'] / $total_hnr * 100);
                            $pct_banned = round($extStats['banned_users'] / $total_hnr * 100);
                            ?>
                            <div class="progress mt-2" style="height:8px">
                                <div class="progress-bar bg-success" style="width:<?= $pct_clean ?>%"
                                     title="Clean: <?= $pct_clean ?>%"></div>
                                <div class="progress-bar bg-warning" style="width:<?= $pct_warned ?>%"
                                     title="Warned: <?= $pct_warned ?>%"></div>
                                <div class="progress-bar bg-danger" style="width:<?= $pct_banned ?>%"
                                     title="Banned: <?= $pct_banned ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted"><?= $pct_clean ?>% clean</small>
                                <small class="text-muted"><?= $pct_banned ?>% banned</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Карточки статистики -->
    <div class="row mb-4">
        <?php
        $statCards = [
            [$stats['current_seeders'],
             $lang->viewsnatches['stat_current_seeders'] ?? 'Current Seeders', 'success',
             ts_nf(($stats['current_seeders'] / max($count, 1)) * 100, 1) . ($lang->viewsnatches['stat_pct_snatchers'] ?? '% of snatchers')],
            [mksize($stats['total_uploaded']),
             $lang->viewsnatches['stat_total_uploaded'] ?? 'Total Uploaded', 'primary',
             mksize($stats['total_uploaded'])],
            [mksize($stats['total_downloaded']),
             $lang->viewsnatches['stat_total_downloaded'] ?? 'Total Downloaded', 'info',
             mksize($stats['total_downloaded'])],
            [mkprettytime($extStats['total_seedtime']),
             $lang->viewsnatches['stat_total_seedtime'] ?? 'Total Seed Time', 'warning',
             sprintf($lang->viewsnatches['stat_users_over_24h'], ts_nf($extStats['users_over_24h']))],
        ];
        $statIcons = ['fas fa-seedling','fas fa-upload','fas fa-download','fas fa-clock'];
        foreach ($statCards as $i => [$val, $label, $color, $sub]):
        ?>
        <div class="col-md-3">
            <div class="card text-center border-<?= $color ?>">
                <div class="card-body py-3">
                    <h5 class="card-title text-<?= $color ?> mb-1">
                        <i class="<?= $statIcons[$i] ?> me-1"></i><?= $val ?>
                    </h5>
                    <p class="card-text small text-muted mb-0"><?= $label ?></p>
                    <small class="text-muted"><?= $sub ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Распределение времени сидирования -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i><?= htmlspecialchars($lang->viewsnatches['seedtime_dist'] ?? 'Seed Time Distribution', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                        $seedDist = [
                            [$extStats['seedtime_distribution']['poor'],      $lang->viewsnatches['seedtime_poor']      ?? '< 1 hour',   'danger'],
                            [$extStats['seedtime_distribution']['fair'],      $lang->viewsnatches['seedtime_fair']      ?? '1-6 hours',  'warning'],
                            [$extStats['seedtime_distribution']['good'],      $lang->viewsnatches['seedtime_good']      ?? '6-24 hours', 'info'],
                            [$extStats['seedtime_distribution']['excellent'], $lang->viewsnatches['seedtime_excellent'] ?? '> 24 hours', 'success'],
                        ];
                        $uniq = max($extStats['unique_users'], 1);
                        foreach ($seedDist as [$val, $label, $color]):
                        ?>
                        <div class="col-md-3">
                            <div class="text-<?= $color ?>">
                                <h4><?= $val ?></h4>
                                <small class="text-muted"><?= $label ?></small>
                                <div class="small text-muted"><?= ts_nf($val / $uniq * 100, 1) ?>%</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Пагинация сверху -->
    <?php if ($count > $perpage): ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Page <?= $page ?> of <?= ceil($count / $perpage) ?> |
                    <?= $start + 1 ?>–<?= min($start + $perpage, $count) ?> of <?= $count ?>
                </div>
                <div><?= $multipage ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Таблица скачиваний -->
    <?php
    $quickLink  = "{$_SERVER['SCRIPT_NAME']}?id={$id}&order=";
    $colLink    = $type === 'ASC' ? '&amp;type=DESC' : '&amp;type=ASC';
    $colHeaders = [
        ['username',    'fa-user',                $lang->viewsnatches['col_user']        ?? 'User'],
        ['uploaded',    'fa-upload',              $lang->viewsnatches['col_uploaded']    ?? 'Uploaded'],
        ['downloaded',  'fa-download',            $lang->viewsnatches['col_downloaded']  ?? 'Downloaded'],
        ['ratio',       'fa-percentage',          $lang->viewsnatches['col_ratio']       ?? 'Ratio'],
        ['completedat', 'fa-flag-checkered',      $lang->viewsnatches['col_finished']    ?? 'Finished'],
        ['last_action', 'fa-clock',               $lang->viewsnatches['col_last_action'] ?? 'Last Action'],
        ['seeder',      'fa-seedling',            $lang->viewsnatches['col_status']      ?? 'Status'],
        ['seedtime',    'fa-clock',               $lang->viewsnatches['col_seedtime']    ?? 'Seed Time'],
        ['leechtime',   'fa-hourglass-half',      $lang->viewsnatches['col_leechtime']   ?? 'Leech Time'],
        ['warned',      'fa-exclamation-triangle', $lang->viewsnatches['col_hnr']        ?? 'H&amp;R'],
    ];
    ?>
    <div class="card shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i><?= htmlspecialchars($lang->viewsnatches['snatch_details'] ?? 'Snatch Details', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="snatchTable">
                <thead class="table-light">
                    <tr>
                        <?php if ($is_mod): ?>
                        <th width="30">
                            <input type="checkbox" class="form-check-input"
                                   onchange="selectAllUsers(this.checked)">
                        </th>
                        <?php endif; ?>
                        <?php foreach ($colHeaders as [$col, $icon, $label]): ?>
                        <th>
                            <a href="<?= $quickLink . $col . $colLink ?>"
                               class="text-decoration-none text-dark <?= $order === $col ? 'fw-bold text-primary' : '' ?>">
                                <i class="fas <?= $icon ?> me-1"></i><?= $label ?>
                                <?php if ($order === $col): ?>
                                <i class="fas fa-sort-<?= $type === 'ASC' ? 'up' : 'down' ?> ms-1 text-primary"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center"><i class="fas fa-cog me-1"></i><?= htmlspecialchars($lang->viewsnatches['col_actions'] ?? 'Actions', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $mainParams  = array_merge([$id], array_slice($filterParams, 1));
                $snatchQuery = "
                    SELECT u.*, g.namestyle,
                           sn.user_sum_uploaded, sn.user_sum_downloaded,
                           sn.last_seedtime, sn.last_leechtime, sn.last_completedat,
                           sn.last_action, sn.seeder, sn.connectable,
                           sn.avg_upspeed, sn.avg_downspeed,
                           sn.warned
                    FROM users u
                    INNER JOIN (
                        SELECT userid,
                               SUM(uploaded)          AS user_sum_uploaded,
                               SUM(downloaded)        AS user_sum_downloaded,
                               MAX(completedat)       AS last_completedat,
                               MAX(seedtime)          AS last_seedtime,
                               MAX(leechtime)         AS last_leechtime,
                               MAX(last_action)       AS last_action,
                               MAX(seeder='yes')      AS seeder,
                               MAX(connectable='yes') AS connectable,
                               AVG(upspeed)           AS avg_upspeed,
                               AVG(downspeed)         AS avg_downspeed,
                               MAX(warned)            AS warned
                        FROM snatched
                        WHERE torrentid = ? AND finished = 'yes'
                        GROUP BY userid
                    ) AS sn ON sn.userid = u.id
                    INNER JOIN usergroups g ON u.usergroup = g.gid
                    WHERE 1=1 {$filterSql}
                    ORDER BY {$orderBy} {$type}
                    LIMIT {$start}, {$perpage}";

                $res = $db->sql_query_prepared($snatchQuery, $filterParams);

                if ($db->num_rows($res) > 0):
                    while ($row = $db->fetch_array($res)):
                        $ratio      = $row['user_sum_downloaded'] > 0
                            ? $row['user_sum_uploaded'] / $row['user_sum_downloaded'] : 0;
                        $ratioClass = $ratio >= 1 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger');
                        $avatarData = format_avatar($row['avatar'], $row['avatardimensions']);
                        $avaHtml    = "<img class='user-avatar' src='{$avatarData['image']}' alt='' {$avatarData['width_height']}>";
                        $status     = $row['seeder']
                            ? "<span class='badge bg-success'><i class='fas fa-seedling me-1'></i>" . ($lang->viewsnatches['badge_seeding'] ?? 'Seeding') . "</span>"
                            : "<span class='badge bg-secondary'><i class='fas fa-times me-1'></i>" . ($lang->viewsnatches['badge_inactive'] ?? 'Inactive') . "</span>";
                        $connIcon   = $row['connectable']
                            ? '<i class="fas fa-plug text-success" title="Connectable"></i>'
                            : '<i class="fas fa-plug text-danger" title="Not Connectable"></i>';
                        $rowClass   = $CURUSER['id'] == $row['id'] ? 'highlight-row' : '';

                        // H&R badge
                        $warnedVal = (int)$row['warned'];
                        if ($warnedVal >= 3) {
                            $hnrBadge   = "<span class='badge bg-danger'><i class='fas fa-ban me-1'></i>" . ($lang->viewsnatches['badge_banned'] ?? 'Banned') . "</span>";
                            $rowClass  .= ' hnr-banned';
                        } elseif ($warnedVal >= 1) {
                            $warned_label = sprintf($lang->viewsnatches['badge_warned_x'], (string)$warnedVal);
                            $hnrBadge   = "<span class='badge bg-warning text-dark'>"
                                        . "<i class='fas fa-exclamation-triangle me-1'></i>{$warned_label}</span>";
                            $rowClass  .= ' hnr-warned';
                        } else {
                            $hnrBadge   = "<span class='badge bg-success'><i class='fas fa-check me-1'></i>" . ($lang->viewsnatches['badge_clean'] ?? 'Clean') . "</span>";
                        }
                ?>
                <tr class="<?= trim($rowClass) ?>">
                    <?php if ($is_mod): ?>
                    <td>
                        <input type="checkbox" class="form-check-input user-checkbox"
                               value="<?= (int)$row['id'] ?>"
                               onchange="updateSelectionCounter()">
                    </td>
                    <?php endif; ?>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <?= $avaHtml ?>
                            <div>
                                <a href="<?= get_profile_link($row['id']) ?>"
                                   class="fw-bold text-decoration-none">
                                    <?= format_name($row['username'], $row['usergroup']) ?>
                                </a>
                                <div class="small text-muted"><?= $connIcon ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold"><?= mksize($row['user_sum_uploaded']) ?></span>
                        <div class="small text-muted"><?= mksize($row['avg_upspeed']) ?>/s</div>
                    </td>
                    <td>
                        <span class="fw-bold"><?= mksize($row['user_sum_downloaded']) ?></span>
                        <div class="small text-muted"><?= mksize($row['avg_downspeed']) ?>/s</div>
                    </td>
                    <td><span class="fw-bold <?= $ratioClass ?>"><?= ts_nf($ratio, 2) ?></span></td>
                    <td>
                        <div class="small"><?= my_datee($dateformat, $row['last_completedat']) ?></div>
                        <div class="small text-muted"><?= my_datee($timeformat, $row['last_completedat']) ?></div>
                    </td>
                    <td>
                        <div class="small"><?= my_datee($dateformat, $row['last_action']) ?></div>
                        <div class="small text-muted"><?= my_datee($timeformat, $row['last_action']) ?></div>
                    </td>
                    <td><?= $status ?></td>
                    <td><span class="small"><?= mkprettytime((int)$row['last_seedtime']) ?></span></td>
                    <td><span class="small"><?= mkprettytime((int)$row['last_leechtime']) ?></span></td>
                    <td><?= $hnrBadge ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="showUserDetails(<?= (int)$row['id'] ?>)" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($is_mod): ?>
                        <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?id=<?= $id ?>&amp;delete=1&amp;userid=<?= (int)$row['id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('<?= addslashes($lang->viewsnatches['confirm_delete'] ?? 'Delete this snatch record?') ?>')"
                           title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <tr>
                    <td colspan="<?= $is_mod ? 13 : 12 ?>" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            <h5><?= htmlspecialchars($lang->viewsnatches['no_snatches'] ?? 'No snatches found', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h5>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($count > $perpage): ?>
        <div class="card-footer bg-transparent">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Showing <?= $start + 1 ?>–<?= min($start + $perpage, $count) ?> of <?= $count ?>
                </div>
                <div><?= $multipage ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.user-avatar    { width:44px;height:44px;border-radius:50%;object-fit:cover;margin-right:.75rem;border:2px solid #e9ecef;transition:border-color .3s; }
.user-avatar:hover { border-color:#007bff; }
.highlight-row  { background:rgba(13,110,253,.05)!important;border-left:3px solid #007bff; }
.hnr-warned     { background:rgba(255,193,7,.06)!important;border-left:3px solid #ffc107; }
.hnr-banned     { background:rgba(220,53,69,.06)!important;border-left:3px solid #dc3545; }
.text-gradient-primary { background:linear-gradient(135deg,#007bff,#6610f2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text; }
.card           { transition:box-shadow .3s ease; }
.card:hover     { box-shadow:0 4px 15px rgba(0,0,0,.1)!important; }
</style>

<script>
window.VS_CONFIG = {
    ratioData:  <?= json_encode($ratioData) ?>,
    baseUrl:    <?= json_encode($BASEURL) ?>,
    torrentId:  <?= (int)$id ?>,
    scriptName: <?= json_encode($scriptName) ?>
};
</script>
<script src="<?= $BASEURL ?>/scripts/viewsnatches.js"></script>

<?php stdfoot(); ?>