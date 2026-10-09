<?php
if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Direct initialization of this file is not allowed.</div>');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ignore_user_abort(true);
define('FH_VERSION', '0.9');
const FH_ASSET_VER = 2; // поднимать вручную при изменении fixhash.css / fixhash.js

require_once __DIR__ . '/../vendor/autoload.php';
require_once './include/global_config.php';

global $lang;
$lang->load('fixhash');

use Arokettu\Torrent\TorrentFile;

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… (и %1$s, %2$s… — $lang->load() конвертирует {N} в %N$s).
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}'] = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

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
        echo json_encode(['success' => false, 'error' => $lang->fixhash['err_token']], JSON_UNESCAPED_UNICODE);
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

    // Лог остаётся на английском
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
    global $lang;
    if ($hash === null) {
        return '<span class="fh-muted-note"><i class="fa-solid fa-minus"></i></span>';
    }
    $h   = htmlspecialchars($hash);
    $tip = htmlspecialchars($lang->fixhash['tip_copy']);
    return '<span class="fh-hash-wrap">'
         . '<code class="fh-hash fh-hash--' . $tone . '">' . $h . '</code>'
         . '<button type="button" class="fh-copy" data-copy="' . $h . '" title="' . $tip . '" aria-label="' . $tip . '">'
         . '<i class="fa-solid fa-copy"></i></button></span>';
};

$badgeHtml = static fn(string $tone, string $icon, string $key): string =>
    '<span class="fh-badge fh-badge--' . $tone . '"><i class="fa-solid ' . $icon . '"></i>'
    . htmlspecialchars($lang->fixhash[$key]) . '</span>';

// Строки для JS: js_* → без префикса
$jsLang = [];
foreach ($lang->fixhash as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}

$e = static fn(string $key): string => htmlspecialchars($lang->fixhash[$key]);

stdhead($lang->fixhash['title']);
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
            <h1 class="fh-title"><?= $e('title') ?></h1>
            <p class="fh-subtitle"><?= $e('subtitle') ?></p>
        </div>
        <span class="fh-version"><i class="fa-solid fa-code-branch"></i> v<?= FH_VERSION ?></span>
    </div>

    <!-- KPI -->
    <div class="fh-kpis">
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-info"><i class="fa-solid fa-database"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($results) ?></div><div class="fh-kpi-label"><?= $e('kpi_total') ?></div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['mismatch']) ?></div><div class="fh-kpi-label"><?= $e('kpi_mismatch') ?></div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-success"><i class="fa-solid fa-circle-check"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['ok']) ?></div><div class="fh-kpi-label"><?= $e('kpi_ok') ?></div></div>
        </div>
        <div class="fh-card fh-kpi">
            <span class="fh-icon fh-soft-secondary"><i class="fa-solid fa-file-circle-xmark"></i></span>
            <div><div class="fh-kpi-value"><?= ts_nf($stats['missing'] + $stats['error']) ?></div><div class="fh-kpi-label"><?= $e('kpi_missing') ?></div></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="fh-card fh-toolbar">
        <div class="fh-note">
            <i class="fa-solid fa-circle-info"></i>
            <span><?= $lang->fixhash['hint_preview'] ?></span>
        </div>
        <form method="get" action="index.php" id="fhFilterForm" class="fh-auto-form">
            <input type="hidden" name="act" value="fixhash">
            <input type="hidden" name="page" value="<?= $pagenumber ?>" id="page-input">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="autoRefreshSwitch" name="auto" value="1" <?= $autoRefresh ? 'checked' : '' ?>>
                <label class="form-check-label" for="autoRefreshSwitch">
                    <i class="fa-solid fa-robot me-1"></i><?= $e('lbl_auto') ?>
                </label>
            </div>
            <noscript><button type="submit" class="btn btn-outline-primary btn-sm fh-pill"><?= $e('btn_apply_auto') ?></button></noscript>
        </form>
    </div>

    <!-- Table -->
    <div class="fh-card fh-table-card">
        <?php if (!$rows): ?>
            <div class="fh-empty">
                <span class="fh-icon fh-soft-secondary"><i class="fa-solid fa-box-open"></i></span>
                <p><?= $e('empty') ?></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fh-table">
                <thead>
                <tr>
                    <th><i class="fa-solid fa-file-arrow-down me-1"></i><?= $e('th_torrent') ?></th>
                    <th><i class="fa-solid fa-database me-1"></i><?= $e('th_stored') ?></th>
                    <th><i class="fa-solid fa-file-shield me-1"></i><?= $e('th_real') ?></th>
                    <th class="text-end"><i class="fa-solid fa-signal me-1"></i><?= $e('th_status') ?></th>
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
                        'ok'       => $badgeHtml('success',   'fa-check',               'badge_ok'),
                        'mismatch' => $badgeHtml('danger',    'fa-wrench',              'badge_mismatch'),
                        'missing'  => $badgeHtml('secondary', 'fa-file-circle-question', 'badge_missing'),
                        'nov1'     => $badgeHtml('info',      'fa-code-fork',           'badge_nov1'),
                        default    => $badgeHtml('warning',   'fa-bug',                 'badge_error'),
                    };
                    $newCell = $r['status'] === 'missing'
                        ? '<span class="fh-muted-note"><i class="fa-solid fa-ghost me-1"></i>' . $e('note_file_missing') . '</span>'
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
    <nav aria-label="<?= $e('aria_pagination') ?>" class="fh-pager">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?= $pagenumber <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(1) ?>" title="<?= $e('tip_first') ?>" aria-label="<?= $e('tip_first') ?>"><i class="fa-solid fa-angles-left"></i></a>
            </li>
            <li class="page-item <?= $pagenumber <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(max(1, $pagenumber - 1)) ?>"><i class="fa-solid fa-angle-left me-1"></i><?= $e('btn_prev') ?></a>
            </li>
            <li class="page-item active" aria-current="page">
                <span class="page-link"><?= $pagenumber ?> / <?= $totalpages ?></span>
            </li>
            <li class="page-item <?= $pagenumber >= $totalpages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl(min($totalpages, $pagenumber + 1)) ?>"><?= $e('btn_next') ?><i class="fa-solid fa-angle-right ms-1"></i></a>
            </li>
            <li class="page-item <?= $pagenumber >= $totalpages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $pageUrl($totalpages) ?>" title="<?= $e('tip_last') ?>" aria-label="<?= $e('tip_last') ?>"><i class="fa-solid fa-angles-right"></i></a>
            </li>
        </ul>
    </nav>

    <!-- Sticky action bar -->
    <div class="fh-actionbar">
        <div class="fh-progress-wrap">
            <div class="fh-progress-meta">
                <span><i class="fa-solid fa-layer-group me-1"></i><?= ags_fmt($lang->fixhash['progress_page'], $pagenumber, $totalpages) ?></span>
                <span id="fixSummary"><?= htmlspecialchars(ags_fmt($lang->fixhash['progress_to_fix'], ts_nf($countMismatched))) ?></span>
            </div>
            <div class="progress fh-progress" role="progressbar" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>

        <?php if ($autoRefresh): ?>
            <span class="fh-auto-status" id="fhAutoStatus"><span class="fh-dot"></span><span class="fh-auto-text"><?= $e('auto_running') ?></span></span>
            <a href="<?= htmlspecialchars($pageUrl($pagenumber, false)) ?>" class="btn btn-outline-secondary btn-sm fh-pill">
                <i class="fa-solid fa-pause me-1"></i><?= $e('btn_stop') ?>
            </a>
        <?php endif; ?>

        <form id="applyForm" method="post" class="m-0">
            <input type="hidden" name="do" value="apply">
            <input type="hidden" name="page" value="<?= $pagenumber ?>">
            <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
            <button type="submit" class="btn btn-success fh-pill" id="applyBtn"<?= $countMismatched === 0 ? ' disabled' : '' ?>>
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= $e('btn_apply') ?>
            </button>
        </form>
    </div>
</div>

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/fixhash.js?ver=<?= FH_ASSET_VER ?>"></script>
<?php
stdfoot();
