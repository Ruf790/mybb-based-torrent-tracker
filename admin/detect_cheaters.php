<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert">
            <h4 class="alert-heading"><i class="fas fa-ban me-2"></i>Access Denied</h4>
            <p class="mb-0">Direct initialization of this file is not allowed.</p>
          </div>');
}

use function htmlspecialchars as e;

const AU_VERSION        = '0.8';
const TORRENTS_PER_PAGE = 20;
const BANNED_GROUP_ID   = 9;               // UC_BANNED
const MIN_UPLOAD_BYTES  = 104857600;       // 100 MB — порог попадания в список
const FAST_UPLOAD_BPS   = 10 * 1048576;    // средняя отдача > 10 MB/s — подозрительно

/**
 * Флаги торрента — иконки с подсказками
 */
function getTorrentFlags(array $t): string
{
    global $lang;
    $lang->load('browse');

    $flag = static fn(string $cls, string $icon, string $tip): string =>
        '<span class="dc-flag ' . $cls . '" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="' . e($tip) . '"><i class="fas ' . $icon . '"></i></span>';

    $flags = [];
    if (($t['free'] ?? '') === 'yes')         $flags[] = $flag('fl-green',  'fa-gift',          $lang->browse['freedownload'] ?? 'Free Download');
    if (($t['silver'] ?? '') === 'yes')       $flags[] = $flag('fl-slate',  'fa-medal',         $lang->browse['silverdownload'] ?? 'Silver Download');
    if (($t['isrequest'] ?? '') === 'yes')    $flags[] = $flag('fl-blue',   'fa-hand-holding',  $lang->browse['requested'] ?? 'Requested Torrent');
    if (($t['anonymous'] ?? '') === 'yes')    $flags[] = $flag('fl-purple', 'fa-user-secret',   'Anonymous torrent');
    if (($t['doubleupload'] ?? '') === 'yes') $flags[] = $flag('fl-dark',   'fa-angles-up',     $lang->browse['dupload'] ?? 'Double Upload');
    if (($t['sticky'] ?? '') === 'yes')       $flags[] = $flag('fl-amber',  'fa-thumbtack',     $lang->browse['sticky'] ?? 'Sticky Torrent');
    if (($t['isnuked'] ?? '') === 'yes')      $flags[] = $flag('fl-red',    'fa-radiation',     sprintf($lang->browse['nuked'] ?? 'Nuked: %s', $t['WhyNuked'] ?? ''));

    if (!isset($t['torrent_name'])) {
        $flags[] = $flag('fl-red', 'fa-trash-can', 'Torrent deleted');
    } elseif (($t['visible'] ?? '') === 'yes') {
        $flags[] = $flag('fl-green', 'fa-heart-pulse', 'Active torrent');
    } else {
        $flags[] = $flag('fl-red', 'fa-skull-crossbones', 'Dead torrent');
    }

    return implode('', $flags);
}

function calculateUserRatio(int $downloaded, int $uploaded): string
{
    if ($downloaded > 0) {
        return number_format($uploaded / $downloaded, 2);
    }
    return $uploaded > 0 ? '∞' : '0.00';
}

function fmtDate(int $ts): string
{
    global $dateformat, $timeformat;
    return $ts > 0 ? my_datee($dateformat, $ts) . ' ' . my_datee($timeformat, $ts) : '—';
}

/**
 * Попап с данными пользователя. HTML-содержимое целиком экранируется ещё раз
 * при выводе в атрибут: раньше значения экранировались только один раз, браузер
 * декодировал атрибут, и при data-bs-html="true" ник снова становился HTML.
 */
function getUserPopoverHtml(array $u): string
{
    global $lang;

    $down = (int)($u['user_current_download'] ?? 0);
    $up   = (int)($u['user_current_upload'] ?? 0);

    return sprintf(
        '<div class="small"><b>%s:</b> %s<br><b>%s:</b> %s<br><b>Downloaded:</b> %s<br><b>Uploaded:</b> %s<br><b>Ratio:</b> %s</div>',
        e($lang->tsf_forums['jdate'] ?? 'Joined'),   e(fmtDate((int)($u['added'] ?? 0))),
        e($lang->tsf_forums['lastseen'] ?? 'Last Seen'), e(fmtDate((int)($u['lastactive'] ?? 0))),
        e(mksize($down)), e(mksize($up)), e(calculateUserRatio($down, $up))
    );
}

// ── Условие выборки (общее для статистики и списка) ──────────────────────
$where = "s.downloaded = 0
          AND s.uploaded > ?
          AND s.leechtime = 0
          AND (t.free IS NULL OR t.free != 'yes')
          AND (t.silver IS NULL OR t.silver != 'yes')
          AND u.enabled = 'yes'
          AND u.usergroup != ?";
$whereParams = [MIN_UPLOAD_BYTES, BANNED_GROUP_ID];

// ── Статистика одним запросом (вместо «Per page / Total pages / Active») ─
$statsRes = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT s.userid) AS users,
            COALESCE(SUM(s.uploaded), 0) AS uploaded,
            COALESCE(SUM(u.downloaded = 0), 0) AS never_downloaded
     FROM snatched s
     INNER JOIN users u ON (u.id = s.userid)
     LEFT JOIN torrents t ON (s.torrentid = t.id)
     WHERE $where",
    $whereParams
);
$stats         = $statsRes ? $db->fetch_array($statsRes) : [];
$totalRecords  = (int)($stats['total'] ?? 0);
$uniqueUsers   = (int)($stats['users'] ?? 0);
$totalUploaded = (int)($stats['uploaded'] ?? 0);
$highRiskRows  = (int)($stats['never_downloaded'] ?? 0);

// ── Пагинация ─────────────────────────────────────────────────────────────
$perPage    = TORRENTS_PER_PAGE;
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1)), $totalPages);
$start      = ($page - 1) * $perPage;

$query = $db->sql_query_prepared(
    "SELECT s.port, s.ip, s.last_action, s.startdat, s.agent,
            s.userid, s.uploaded AS snatched_uploaded, s.torrentid,
            t.name AS torrent_name, t.free, t.silver, t.isrequest, t.isnuked,
            t.sticky, t.anonymous, t.banned, t.visible, t.doubleupload,
            t.seeders, t.leechers,
            u.downloaded AS user_current_download, u.uploaded AS user_current_upload,
            u.username, u.usergroup, u.added, u.avatar, u.avatardimensions, u.lastactive,
            u.donor, u.leechwarn, u.warned,
            g.title AS group_title
     FROM snatched s
     LEFT JOIN torrents t ON (s.torrentid = t.id)
     INNER JOIN users u ON (u.id = s.userid)
     LEFT JOIN usergroups g ON (u.usergroup = g.gid)
     WHERE $where
     ORDER BY u.username
     LIMIT ?, ?",
    array_merge($whereParams, [(int)$start, (int)$perPage])
);

if ($query === false) {
    error_log('Database query failed in detect_cheaters.php');
    stdhead('Cheater Detection System - Error');
    echo '<div class="container py-4"><div class="alert alert-danger"><i class="fas fa-database me-2"></i><strong>Database Error:</strong> Unable to retrieve cheater data. Please try again later.</div></div>';
    stdfoot();
    exit();
}

$multipage = multipage($totalRecords, $perPage, (int)$page, $_this_script_ . '&page=');

$lang->load('tsf_forums');
include_once INC_PATH . '/functions_icons.php';
include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_mkprettytime.php';

stdhead('Cheater Detection System');
?>

<?php
$dcAsset = static fn(string $rel): string =>
    e($BASEURL) . $rel . '?v=' . (is_file(TSDIR . $rel) ? (string)filemtime(TSDIR . $rel) : AU_VERSION);
?>
<link rel="stylesheet" href="<?= $dcAsset('/admin/templates/detect_cheaters.css') ?>">

<div class="container mt-3 mb-4 dc">

    <!-- Заголовок -->
    <div class="dc-card mb-3">
        <div class="dc-head">
            <span class="dc-head-icon"><i class="fas fa-user-secret"></i></span>
            <div>
                <h1 class="dc-title">Cheater Detection</h1>
                <div class="dc-sub">Snatches with upload but zero download on non-free torrents</div>
            </div>
            <span class="dc-ver"><i class="fas fa-shield-halved me-1"></i>v<?= e(AU_VERSION) ?></span>
        </div>
        <div class="dc-rules">
            <span class="dc-rule"><i class="fas fa-arrow-up text-success"></i>Uploaded &gt; <?= mksize(MIN_UPLOAD_BYTES) ?></span>
            <span class="dc-rule"><i class="fas fa-arrow-down text-danger"></i>Downloaded = 0</span>
            <span class="dc-rule"><i class="fas fa-stopwatch"></i>Leech time = 0</span>
            <span class="dc-rule"><i class="fas fa-gift"></i>Not free / silver</span>
            <span class="dc-rule"><i class="fas fa-user-check"></i>Enabled, not banned</span>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="dc-card dc-stat">
                <span class="dc-stat-icon ic-purple"><i class="fas fa-list-check"></i></span>
                <div><div class="dc-stat-label">Suspicious snatches</div><div class="dc-stat-value"><?= number_format($totalRecords) ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dc-card dc-stat">
                <span class="dc-stat-icon ic-blue"><i class="fas fa-users"></i></span>
                <div><div class="dc-stat-label">Users involved</div><div class="dc-stat-value"><?= number_format($uniqueUsers) ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dc-card dc-stat">
                <span class="dc-stat-icon ic-green"><i class="fas fa-cloud-arrow-up"></i></span>
                <div><div class="dc-stat-label">Uploaded total</div><div class="dc-stat-value"><?= mksize($totalUploaded) ?></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dc-card dc-stat">
                <span class="dc-stat-icon ic-red"><i class="fas fa-triangle-exclamation"></i></span>
                <div><div class="dc-stat-label">High risk</div><div class="dc-stat-value"><?= number_format($highRiskRows) ?></div></div>
            </div>
        </div>
    </div>

    <!-- Таблица -->
    <div class="dc-card overflow-hidden">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
            <span class="fw-bold"><i class="fas fa-table-list me-2 text-body-secondary"></i>Suspicious Activity</span>
            <?php if ($totalRecords > 0): ?>
                <span class="dc-muted">
                    <i class="fas fa-eye me-1"></i><?= number_format($start + 1) ?>–<?= number_format(min($start + $perPage, $totalRecords)) ?> of <?= number_format($totalRecords) ?>
                    · page <?= $page ?>/<?= $totalPages ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table dc-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i>User</th>
                        <th><i class="fas fa-magnet"></i>Torrent</th>
                        <th><i class="fas fa-upload"></i>Uploaded</th>
                        <th><i class="fas fa-network-wired"></i>Client</th>
                        <th><i class="fas fa-clock"></i>Time</th>
                        <th class="text-end"><i class="fas fa-bolt"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($db->num_rows($query) > 0): ?>
                    <?php while ($r = $db->fetch_array($query)): ?>
                        <?php
                        $uid        = (int)$r['userid'];
                        $tid        = (int)$r['torrentid'];
                        $rawName    = (string)($r['username'] ?? '');
                        $safeName   = e($rawName);
                        $initial    = e(mb_strtoupper(mb_substr($rawName, 0, 1)));
                        $uploadedB  = (int)($r['snatched_uploaded'] ?? 0);
                        $startTs    = (int)($r['startdat'] ?? 0);
                        $lastTs     = (int)($r['last_action'] ?? 0);
                        $duration   = ($startTs > 0 && $lastTs > $startTs) ? $lastTs - $startTs : 0;
                        $speedBps   = $duration > 0 ? (int)($uploadedB / $duration) : 0;
                        $isFast     = $speedBps > FAST_UPLOAD_BPS;

                        // Риск: высокий — пользователь вообще ничего не скачивал (прежняя
                        // «жёлтая» подсветка) или отдача быстрее порога; иначе — средний
                        $neverDl  = (int)($r['user_current_download'] ?? 0) === 0;
                        $riskHigh = $neverDl || $isFast;
                        $riskWhy  = $neverDl
                            ? 'User has never downloaded anything, but uploaded on a non-free torrent'
                            : ($isFast ? 'Average upload speed is above ' . mksize(FAST_UPLOAD_BPS) . '/s' : 'Upload without any download on this torrent');

                        $av = format_avatar($r['avatar'] ?? '', $r['avatardimensions'] ?? '');
                        $avatarHtml = (!empty($av['image']) && empty($av['is_placeholder']))
                            ? '<img class="dc-avatar" src="' . $av['image'] . '" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;"><span class="dc-initial" hidden>' . $initial . '</span>'
                            : '<span class="dc-initial">' . $initial . '</span>';

                        $profileLink = $BASEURL . '/' . get_profile_link($uid);
                        $torrentLink = $BASEURL . '/' . get_torrent_link($tid);
                        $torrentName = (string)($r['torrent_name'] ?? '');
                        ?>
                        <tr class="<?= $riskHigh ? 'risk-high' : 'risk-medium' ?>">
                            <!-- User -->
                            <td>
                                <div class="dc-user">
                                    <?= $avatarHtml ?>
                                    <div class="dc-minw-0">
                                        <a href="<?= e($profileLink) ?>" class="fw-semibold text-decoration-none"
                                           data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top"
                                           data-bs-html="true" data-bs-container="body"
                                           data-bs-title="<?= e($safeName) ?>"
                                           data-bs-content="<?= e(getUserPopoverHtml($r)) ?>"><?= format_name($safeName, (int)($r['usergroup'] ?? 0)) ?></a>
                                        <div class="dc-muted"><?= e((string)($r['group_title'] ?? 'No Group')) ?></div>
                                        <span class="dc-risk <?= $riskHigh ? 'dc-risk-high' : 'dc-risk-medium' ?> mt-1"
                                              data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="<?= e($riskWhy) ?>">
                                            <i class="fas <?= $riskHigh ? 'fa-circle-exclamation' : 'fa-circle-question' ?>"></i><?= $riskHigh ? 'High risk' : 'Check' ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Torrent -->
                            <td>
                                <?php if ($torrentName !== ''): ?>
                                    <a href="<?= e($torrentLink) ?>" target="_blank" rel="noopener" class="dc-torrent" title="<?= e($torrentName) ?>">
                                        <i class="fas fa-magnet text-danger me-1"></i><?= e($torrentName) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="dc-muted"><i class="fas fa-trash-can me-1"></i>Deleted torrent #<?= $tid ?></span>
                                <?php endif; ?>
                                <div class="mt-1 d-flex align-items-center flex-wrap">
                                    <span class="dc-flags"><?= getTorrentFlags($r) ?></span>
                                    <span class="dc-peers">
                                        <span title="Seeders"><i class="fas fa-arrow-up text-success me-1"></i><?= ts_nf((int)($r['seeders'] ?? 0)) ?></span>
                                        <span title="Leechers"><i class="fas fa-arrow-down text-danger me-1"></i><?= ts_nf((int)($r['leechers'] ?? 0)) ?></span>
                                    </span>
                                </div>
                            </td>

                            <!-- Upload -->
                            <td>
                                <div class="dc-up"><i class="fas fa-arrow-up me-1"></i><?= mksize($uploadedB) ?></div>
                                <div class="dc-speed <?= $isFast ? 'is-fast' : 'dc-muted' ?>">
                                    <i class="fas fa-gauge-high me-1"></i><?= $speedBps > 0 ? mksize($speedBps) . '/s avg' : 'speed n/a' ?>
                                </div>
                            </td>

                            <!-- Client -->
                            <td>
                                <div><span class="dc-agent" title="<?= e((string)($r['agent'] ?? 'Unknown')) ?>"><i class="fas fa-desktop me-1 text-body-secondary"></i><?= e((string)($r['agent'] ?? 'Unknown')) ?></span></div>
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    <span class="dc-copy" data-copy="<?= e((string)($r['ip'] ?? '')) ?>" title="Click to copy"><i class="fas fa-location-dot me-1"></i><?= e((string)($r['ip'] ?? '0.0.0.0')) ?></span>
                                    <span class="dc-copy" data-copy="<?= (int)($r['port'] ?? 0) ?>" title="Port — click to copy"><i class="fas fa-plug me-1"></i><?= (int)($r['port'] ?? 0) ?></span>
                                </div>
                            </td>

                            <!-- Time -->
                            <td>
                                <div class="dc-time"><i class="fas fa-play"></i><?= fmtDate($startTs) ?></div>
                                <div class="dc-time dc-muted"><i class="fas fa-rotate"></i><?= fmtDate($lastTs) ?></div>
                                <?php if ($duration > 0): ?>
                                    <div class="dc-time dc-muted"><i class="fas fa-hourglass-half"></i><?= mkprettytime($duration) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-end">
                                <div class="dc-actions">
                                    <a class="dc-act" href="<?= e($profileLink) ?>" title="Profile"><i class="fas fa-user"></i></a>
                                    <a class="dc-act" href="<?= e($BASEURL . '/admin/edituser.php?action=edituser&userid=' . $uid) ?>" title="Edit user"><i class="fas fa-user-pen"></i></a>
                                    <?php if ($torrentName !== ''): ?>
                                        <a class="dc-act" href="<?= e($torrentLink) ?>" target="_blank" rel="noopener" title="Torrent"><i class="fas fa-up-right-from-square"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="dc-empty">
                                <i class="fas fa-circle-check"></i>
                                <div class="fw-semibold">No suspicious activity found</div>
                                <div class="small">All users appear to be following the rules.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-3"><?= $multipage ?></div>
    <?php endif; ?>

    <!-- Легенда -->
    <?php if ($totalRecords > 0): ?>
    <div class="dc-card mt-3">
        <div class="px-3 py-2 border-bottom fw-bold"><i class="fas fa-key me-2 text-body-secondary"></i>Legend</div>
        <div class="row g-3 p-3">
            <div class="col-md-6">
                <div class="dc-muted fw-bold text-uppercase mb-2 dc-legend-title">Torrent flags</div>
                <div class="row row-cols-2 g-1">
                    <div class="dc-legend-item"><span class="dc-flag fl-green"><i class="fas fa-gift"></i></span>Free</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-slate"><i class="fas fa-medal"></i></span>Silver</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-blue"><i class="fas fa-hand-holding"></i></span>Requested</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-purple"><i class="fas fa-user-secret"></i></span>Anonymous</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-dark"><i class="fas fa-angles-up"></i></span>Double upload</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-amber"><i class="fas fa-thumbtack"></i></span>Sticky</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-green"><i class="fas fa-heart-pulse"></i></span>Active</div>
                    <div class="dc-legend-item"><span class="dc-flag fl-red"><i class="fas fa-skull-crossbones"></i></span>Dead / nuked</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dc-muted fw-bold text-uppercase mb-2 dc-legend-title">Risk level</div>
                <div class="dc-legend-item"><span class="dc-risk dc-risk-high"><i class="fas fa-circle-exclamation"></i>High risk</span>User never downloaded anything, or average upload &gt; <?= mksize(FAST_UPLOAD_BPS) ?>/s</div>
                <div class="dc-legend-item"><span class="dc-risk dc-risk-medium"><i class="fas fa-circle-question"></i>Check</span>Upload without download on this torrent</div>
                <div class="dc-muted mt-2"><i class="fas fa-circle-info me-1"></i>This is a heuristic, not proof. Check peers and client history before taking action.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="text/javascript" src="<?= e($BASEURL) ?>/scripts/popover.js"></script>
<script src="<?= $dcAsset('/admin/scripts/detect_cheaters.js') ?>" defer></script>

<?php
stdfoot();