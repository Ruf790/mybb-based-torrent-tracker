<?php
if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Direct initialization of this file is not allowed.</div>');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ignore_user_abort(true);
define('FH_VERSION', '0.9');
const FH_ASSET_VER = 1; // поднимать вручную при изменении fixhash.css / fixhash.js

require_once __DIR__ . '/../vendor/autoload.php';
require_once './include/global_config.php';

use Arokettu\Torrent\TorrentFile;

/**
 * Проверка одного .torrent файла.
 * status: ok | mismatch | missing | error | nov1 (v2-only торрент без v1 хеша)
 */
function fh_check_torrent(string $path, ?string $current): array
{
    if (!is_file($path)) {
        return ['status' => 'missing', 'hash' => null];
    }
    try {
        $hash = TorrentFile::load($path)->v1()->getInfoHash();
    } catch (Throwable) {
        return ['status' => 'error', 'hash' => null];
    }
    if (!$hash) {
        // Раньше в этом случае статус оставался 'ok' и строка показывалась зелёной как "Matches"
        return ['status' => 'nov1', 'hash' => null];
    }
    return ['status' => $hash === $current ? 'ok' : 'mismatch', 'hash' => $hash];
}

// Подсчёт общего числа торрентов
$query = $db->sql_query_prepared('SELECT COUNT(id) as cnt FROM torrents');
$row = $query ? $db->fetch_array($query) : null;
$results = (int)($row['cnt'] ?? 0);

$perpage = max(1, (int)($config['fixhash_perpage'] ?? 10));
$totalpages = max(1, (int)ceil($results / $perpage));

$pagenumber = (isset($_GET['page']) && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1;
$pagenumber = min(max(1, $pagenumber), $totalpages);

$limitlower = ($pagenumber - 1) * $perpage;

// Auto Fix: каждые 10 секунд применяет фиксы текущей страницы (POST+CSRF) и листает дальше
$autoRefresh = isset($_GET['auto']) && $_GET['auto'] === '1';

// ── Применение фиксов (POST + CSRF) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'apply') {
    header('Content-Type: application/json; charset=utf-8');

    // $silent=true — иначе при провале функция может вывести HTML вместо false,
    // а JS ждёт валидный JSON.
    global $mybb, $CURUSER;
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }

    $applyPage = (isset($_POST['page']) && intval($_POST['page']) > 0) ? intval($_POST['page']) : 1;
    $applyLimit = ($applyPage - 1) * $perpage;

    $res = $db->sql_query_prepared("SELECT id, info_hash FROM torrents ORDER BY added DESC LIMIT ?, ?", [$applyLimit, $perpage]);
    $fixed = 0;
    $errors = 0;
    $skipped = 0;
    $fixed_ids = [];

    while ($res && ($row = $db->fetch_array($res))) {
        $torrentPath = TSDIR . '/' . $torrent_dir . '/' . $row['id'] . '.torrent';
        $check = fh_check_torrent($torrentPath, isset($row['info_hash']) ? (string)$row['info_hash'] : null);

        switch ($check['status']) {
            case 'mismatch':
                try {
                    if ($db->sql_query_prepared("UPDATE torrents SET info_hash = ? WHERE id = ?", [$check['hash'], $row['id']])) {
                        $fixed++;
                        $fixed_ids[] = (int)$row['id'];
                    } else {
                        $errors++;
                    }
                } catch (Throwable) {
                    // например, дубликат по уникальному индексу info_hash
                    $errors++;
                }
                break;
            case 'error':
                $errors++;
                break;
            case 'nov1':
            case 'missing':
                $skipped++;
                break;
        }
    }

    if ($fixed > 0) {
        write_log(sprintf(
            'Fix Torrent Hashes: %s (UID %d) fixed info_hash for %d torrent(s) on page %d (IDs: %s)%s',
            $CURUSER['username'],
            (int)$CURUSER['id'],
            $fixed,
            $applyPage,
            implode(',', $fixed_ids),
            $errors > 0 ? ", {$errors} error(s)" : ''
        ), 'torrent');
    }

    echo json_encode(['success' => true, 'fixed' => $fixed, 'errors' => $errors, 'skipped' => $skipped]);
    exit;
}

// ── Сбор данных страницы (до вывода, чтобы KPI были сверху) ─────────────
$rows = [];
$stats = ['ok' => 0, 'mismatch' => 0, 'missing' => 0, 'error' => 0];

$res = $db->sql_query_prepared("SELECT id, name, info_hash FROM torrents ORDER BY added DESC LIMIT ?, ?", [$limitlower, $perpage]);
while ($res && ($row = $db->fetch_array($res))) {
    $current = isset($row['info_hash']) && $row['info_hash'] !== '' ? (string)$row['info_hash'] : null;
    $check = fh_check_torrent(TSDIR . '/' . $torrent_dir . '/' . $row['id'] . '.torrent', $current);

    $stats[$check['status'] === 'nov1' ? 'error' : $check['status']]++;

    $rows[] = [
        'id'     => (int)$row['id'],
        'name'   => (string)$row['name'],
        'old'    => $current,
        'new'    => $check['hash'],
        'status' => $check['status'],
    ];
}
$countMismatched = $stats['mismatch'];
$progressPercent = (int)round(($pagenumber / $totalpages) * 100);

$pageUrl = static fn(int $p, ?bool $auto = null): string =>
    '?act=fixhash&page=' . $p . '&auto=' . (($auto ?? $autoRefresh) ? '1' : '0');

$hashCell = static function (?string $hash, string $tone): string {
    if ($hash === null) {
        return '<span class="fh-muted-note"><i class="fa-solid fa-minus"></i></span>';
    }
    $h = htmlspecialchars($hash);
    return '<span class="fh-hash-wrap">'
         . '<code class="fh-hash fh-hash--' . $tone . '">' . $h . '</code>'
         . '<button type="button" class="fh-copy" data-copy="' . $h . '" title="Copy hash" aria-label="Copy hash">'
         . '<i class="fa-solid fa-copy"></i></button></span>';
};

stdhead('Fix Torrent Hashes');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/admin/templates/fixhash.css?ver=<?= FH_ASSET_VER ?>">

<div class="fh-page container my-4" id="fhPage"
     data-page="<?= $pagenumber ?>"
     data-total-pages="<?= $totalpages ?>"
     data-mismatch="<?= $countMismatched ?>"
     data-auto="<?= $autoRefresh ? '1' : '0' ?>"
     data-stop-url="<?= htmlspecialchars($pageUrl($pagenumber, false)) ?>">

    <!-- Header -->
    <div class="fh-card fh-header">
        <span class="fh-icon fh-soft-primary"><i class="fa-solid fa-fingerprint"></i></span>
        <div class="fh-header-text">
            <h1 class="fh-title">Fix Torrent Hashes</h1>
            <p class="fh-subtitle">Compares the info_hash stored in the database with the real v1 hash of each .torrent file.</p>
        </div>
        <span class="fh-version"><i class="fa-solid fa-code-branch"></i> v<?= FH_VERSION ?></span>
    </div>

    <!-- KPI -->
    <div class="fh-kpis">
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-info"><i class="fa-solid fa-database"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($results) ?></div><div class="fh-kpi-label">Torrents in total</div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['mismatch']) ?></div><div class="fh-kpi-label">Need fixing on this page</div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-success"><i class="fa-solid fa-circle-check"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['ok']) ?></div><div class="fh-kpi-label">Match on this page</div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-secondary"><i class="fa-solid fa-file-circle-xmark"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['missing'] + $stats['error']) ?></div><div class="fh-kpi-label">Missing or unreadable</div></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="fh-card fh-toolbar">
        <div class="fh-note">
            <i class="fa-solid fa-circle-info"></i>
            <span>This page only <strong>previews</strong> differences. Nothing is written to the database until you apply fixes.</span>
        </div>
        <form method="get" action="index.php" id="fhFilterForm" class="fh-auto-form">
            <input type="hidden" name="act" value="fixhash">
            <input type="hidden" name="page" value="<?= $pagenumber ?>" id="page-input">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="autoRefreshSwitch" name="auto" value="1" <?= $autoRefresh ? 'checked' : '' ?>>
                <label class="form-check-label" for="autoRefreshSwitch">
                    <i class="fa-solid fa-robot me-1"></i>Auto Fix every 10s
                </label>
            </div>
            <noscript><button type="submit" class="btn btn-outline-primary btn-sm fh-pill">Apply</button></noscript>
        </form>
    </div>

    <!-- Table -->
    <div class="fh-card fh-table-card">
        <?php if (!$rows): ?>
            <div class="fh-empty">
                <span class="fh-icon fh-soft-secondary"><i class="fa-solid fa-box-open"></i></span>
                <p>No torrents on this page.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fh-table">
                <thead>
                <tr>
                    <th><i class="fa-solid fa-file-arrow-down me-1"></i>Torrent</th>
                    <th><i class="fa-solid fa-database me-1"></i>Stored hash</th>
                    <th><i class="fa-solid fa-file-shield me-1"></i>Real hash</th>
                    <th class="text-end"><i class="fa-solid fa-signal me-1"></i>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    [$oldTone, $newTone] = match ($r['status']) {
                        'mismatch' => ['bad', 'good'],
                        'ok'       => ['ok', 'ok'],
                        default    => ['muted', 'muted'],
                    };
                    $badge = match ($r['status']) {
                        'ok'       => '<span class="fh-badge fh-badge--success"><i class="fa-solid fa-check"></i>Matches</span>',
                        'mismatch' => '<span class="fh-badge fh-badge--danger"><i class="fa-solid fa-wrench"></i>Needs fix</span>',
                        'missing'  => '<span class="fh-badge fh-badge--secondary"><i class="fa-solid fa-file-circle-question"></i>No file</span>',
                        'nov1'     => '<span class="fh-badge fh-badge--info"><i class="fa-solid fa-code-fork"></i>v2 only</span>',
                        default    => '<span class="fh-badge fh-badge--warning"><i class="fa-solid fa-bug"></i>Unreadable</span>',
                    };
                    $newCell = $r['status'] === 'missing'
                        ? '<span class="fh-muted-note"><i class="fa-solid fa-ghost me-1"></i>File missing</span>'
                        : $hashCell($r['new'], $newTone);
                ?>
                    <tr class="fh-row fh-row--<?= $r['status'] ?>">
                        <td class="fh-name">
                            <a href="<?= htmlspecialchars($BASEURL) . '/' . get_torrent_link($r['id']) ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars_uni($r['name']) ?>
                            </a>
                            <span class="fh-id">#<?= $r['id'] ?></span>
                        </td>
                        <td><?= $hashCell($r['old'], $oldTone) ?></td>
                        <td><?= $newCell ?></td>
                        <td class="text-end"><?= $badge ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation" class="fh-pager">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?= $pagenumber <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(1) ?>" title="First page"><i class="fa-solid fa-angles-left"></i></a>
            </li>
            <li class="page-item <?= $pagenumber <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(max(1, $pagenumber - 1)) ?>"><i class="fa-solid fa-angle-left me-1"></i>Previous</a>
            </li>
            <li class="page-item active" aria-current="page">
                <span class="page-link"><?= $pagenumber ?> / <?= $totalpages ?></span>
            </li>
            <li class="page-item <?= $pagenumber >= $totalpages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(min($totalpages, $pagenumber + 1)) ?>">Next<i class="fa-solid fa-angle-right ms-1"></i></a>
            </li>
            <li class="page-item <?= $pagenumber >= $totalpages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl($totalpages) ?>" title="Last page"><i class="fa-solid fa-angles-right"></i></a>
            </li>
        </ul>
    </nav>

    <!-- Sticky action bar -->
    <div class="fh-actionbar">
        <div class="fh-progress-wrap">
            <div class="fh-progress-meta">
                <span><i class="fa-solid fa-layer-group me-1"></i>Page <strong><?= $pagenumber ?></strong> of <strong><?= $totalpages ?></strong></span>
                <span id="fixSummary"><?= ts_nf($countMismatched) ?> to fix here</span>
            </div>
            <div class="progress fh-progress" role="progressbar" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>

        <?php if ($autoRefresh): ?>
            <span class="fh-auto-status" id="fhAutoStatus"><span class="fh-dot"></span><span class="fh-auto-text">Auto Fix running</span></span>
            <a href="<?= htmlspecialchars($pageUrl($pagenumber, false)) ?>" class="btn btn-outline-secondary btn-sm fh-pill">
                <i class="fa-solid fa-pause me-1"></i>Stop
            </a>
        <?php endif; ?>

        <form id="applyForm" method="post" class="m-0">
            <input type="hidden" name="do" value="apply">
            <input type="hidden" name="page" value="<?= $pagenumber ?>">
            <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
            <button type="submit" class="btn btn-success fh-pill" id="applyBtn"<?= $countMismatched === 0 ? ' disabled' : '' ?>>
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Apply fixes for this page
            </button>
        </form>
    </div>
</div>

<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/fixhash.js?ver=<?= FH_ASSET_VER ?>"></script>
<?php
stdfoot();