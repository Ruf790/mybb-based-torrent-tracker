<?php
if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger">Direct initialization of this file is not allowed.</div>');
}



@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ignore_user_abort(true);
define('FH_VERSION', '0.8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once './include/global_config.php';

use Arokettu\Torrent\TorrentFile;

// Подсчёт общего числа торрентов
$query = $db->sql_query_prepared('SELECT COUNT(id) as cnt FROM torrents');
$row = $query ? $db->fetch_array($query) : null;
$results = $row['cnt'] ?? 0;

$perpage = (int)($config['fixhash_perpage'] ?? 10);
$totalpages = max(1, ceil($results / $perpage));

$pagenumber = (isset($_GET['page']) && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1;
if ($pagenumber < 1) {
    $pagenumber = 1;
} elseif ($pagenumber > $totalpages) {
    $pagenumber = $totalpages;
}

$limitlower = ($pagenumber - 1) * $perpage;

// Автообновление (каждые 10 секунд) - теперь автоматически ПРИМЕНЯЕТ фиксы
// текущей страницы (через POST+CSRF), а не просто перезагружает страницу.
$autoRefresh = isset($_GET['auto']) && $_GET['auto'] === '1';

// ── Применение фиксов (POST + CSRF) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'apply') {
    header('Content-Type: application/json; charset=utf-8');

    // $silent=true — иначе при провале функция может сама вывести HTML
    // (в зависимости от состояния IN_ADMINCP) вместо простого false, а этот
    // эндпоинт уже отправил Content-Type: application/json и ждёт от JS
    // валидный res.json() — HTML внутри JSON-ответа сломает fetch().then(r => r.json()).
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
    $fixed_ids = [];

    while ($res && ($row = $db->fetch_array($res))) {
        $torrentPath = TSDIR . '/' . $torrent_dir . '/' . $row['id'] . '.torrent';
        if (!file_exists($torrentPath)) {
            continue;
        }
        try {
            $torrent = TorrentFile::load($torrentPath);
            $infoHash = $torrent->v1()->getInfoHash();
            if ($infoHash && $infoHash !== $row['info_hash']) {
                if ($db->sql_query_prepared("UPDATE torrents SET info_hash = ? WHERE id = ?", [$infoHash, $row['id']])) {
                    $fixed++;
                    $fixed_ids[] = (int)$row['id'];
                }
            }
        } catch (Exception $e) {
            $errors++;
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

    echo json_encode(['success' => true, 'fixed' => $fixed, 'errors' => $errors]);
    exit;
}

stdhead('Fix Torrent Hashes');
?>

<div class="container my-4">
    <h1 class="mb-4 fw-light">Fix Torrent Hashes <small class="text-muted">v<?= FH_VERSION ?></small></h1>

    <div class="alert alert-info small">
        <i class="fas fa-info-circle me-1"></i>
        This page only <strong>previews</strong> hash differences. Nothing is written to the
        database until you click <strong>Apply Fixes</strong> below.
    </div>

    <form method="get" action="index.php" class="d-flex align-items-center gap-3 flex-wrap mb-3">
        <input type="hidden" name="act" value="fixhash" />
        <input type="hidden" name="page" value="<?= $pagenumber ?>" id="page-input" />
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="autoRefreshSwitch" name="auto" value="1" <?= $autoRefresh ? 'checked' : '' ?>>
            <label class="form-check-label" for="autoRefreshSwitch">Auto Fix (every 10s)</label>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm">Apply Filter</button>
    </form>

<?php

echo '<div class="table-responsive">';
echo '<table class="table table-hover align-middle">';
echo '<thead class="table-light"><tr>
        <th>Torrent</th>
        <th class="text-monospace" style="min-width:180px;">Current Hash</th>
        <th class="text-monospace" style="min-width:180px;">Real Hash</th>
        <th>Status</th>
      </tr></thead><tbody>';

$countMismatched = 0;

// Получаем торренты для текущей страницы
$res = $db->sql_query_prepared("SELECT id, name, info_hash FROM torrents ORDER BY added DESC LIMIT ?, ?", [$limitlower, $perpage]);

while ($res && ($row = $db->fetch_array($res))) {
    $torrentPath = TSDIR . '/' . $torrent_dir . '/' . $row['id'] . '.torrent';
    $oldHash = $row['info_hash'] ?? 'N/A';
    $newHash = 'N/A';
    $newHashHtml = null; // если задано - выводим как готовый HTML, без повторного экранирования
    $status = 'ok';

    echo '<tr>';
    echo '<td><a href="' . htmlspecialchars($BASEURL) . '/' . get_torrent_link($row['id']) . '" target="_blank" class="text-decoration-none fw-semibold">' . htmlspecialchars_uni($row['name']) . '</a></td>';

    if (file_exists($torrentPath))
    {
        try
        {
            $torrent = TorrentFile::load($torrentPath);
            $infoHash = $torrent->v1()->getInfoHash();
            $newHash = $infoHash ?: 'Error';

            if ($infoHash && $infoHash !== $oldHash) {
                $status = 'mismatch';
                $countMismatched++;
            }
        }
        catch (Exception $e)
        {
            $newHash = 'Error';
            $status = 'error';
        }
    }
    else
    {
        $newHashHtml = '<span class="text-muted fst-italic">File missing</span>';
        $status = 'missing';
    }

    $rowClass = match ($status) {
        'mismatch' => 'text-danger',
        'error', 'missing' => 'text-muted',
        default => 'text-success',
    };

    echo '<td class="text-monospace ' . $rowClass . '">' . htmlspecialchars($oldHash) . '</td>';
    echo '<td class="text-monospace ' . $rowClass . '">' . ($newHashHtml ?? htmlspecialchars($newHash)) . '</td>';

    $statusBadge = match ($status) {
        'ok'       => '<span class="badge bg-success">Matches</span>',
        'mismatch' => '<span class="badge bg-danger">Needs Fix</span>',
        'missing'  => '<span class="badge bg-secondary">No File</span>',
        default    => '<span class="badge bg-warning text-dark">Error</span>',
    };

    echo '<td>' . $statusBadge . '</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

// Пагинация и прогресс
echo '<div class="d-flex justify-content-between align-items-center my-3 small text-muted">';
echo '<div>Page <strong>' . $pagenumber . '</strong> of <strong>' . $totalpages . '</strong></div>';
echo '<div id="fixSummary">' . ts_nf($countMismatched) . ' torrent(s) on this page need fixing, total <strong>' . ts_nf($results) . '</strong></div>';
echo '</div>';

$progressPercent = intval(($pagenumber / $totalpages) * 100);
echo '<div class="progress mb-3" style="height: 12px;">';
echo '<div class="progress-bar bg-info" role="progressbar" style="width: ' . $progressPercent . '%;" aria-valuenow="' . $progressPercent . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '</div>';

// ── Кнопка Apply (POST + CSRF) ──────────────────────────────────────────
echo '<form id="applyForm" method="post" class="mb-4">
        <input type="hidden" name="do" value="apply">
        <input type="hidden" name="page" value="' . $pagenumber . '">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
        <button type="submit" class="btn btn-success" id="applyBtn"' . ($countMismatched === 0 ? ' disabled' : '') . '>
            <i class="fas fa-wrench me-1"></i>Apply Fixes for This Page
        </button>
      </form>';

// Навигация по страницам
echo '<nav aria-label="Page navigation">';
echo '<ul class="pagination justify-content-center pagination-sm">';
echo '<li class="page-item ' . ($pagenumber <= 1 ? 'disabled' : '') . '">';
echo '<a class="page-link" href="?act=fixhash&page=' . max(1, $pagenumber - 1) . '&auto=' . ($autoRefresh ? '1' : '0') . '" tabindex="-1">Previous</a></li>';

echo '<li class="page-item disabled"><a class="page-link" href="#">Page ' . $pagenumber . ' of ' . $totalpages . '</a></li>';

echo '<li class="page-item ' . ($pagenumber >= $totalpages ? 'disabled' : '') . '">';
echo '<a class="page-link" href="?act=fixhash&page=' . min($totalpages, $pagenumber + 1) . '&auto=' . ($autoRefresh ? '1' : '0') . '">Next</a></li>';
echo '</ul></nav>';

// Ручной клик по "Apply Fixes for This Page" — тоже через AJAX, чтобы не
// уходить на голый JSON-ответ (это происходило раньше при обычном сабмите
// формы, до AJAX-обработчика). Работает всегда, не только при auto-refresh.
echo <<<HTML
<script>
    (function () {
        const applyForm = document.getElementById('applyForm');
        if (!applyForm) return;
        applyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('applyBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Applying…';

            const formData = new FormData(applyForm);
            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Fixed ' + data.fixed + ' torrent(s), ' + data.errors + ' error(s).');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                })
                .catch(() => {
                    alert('Request failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        });
    })();
</script>
HTML;

// Скрипт автообновления - теперь реально применяет фиксы (через AJAX POST),
// а не просто листает страницы вхолостую.
if ($autoRefresh && $pagenumber <= $totalpages) {
    echo <<<HTML
<script>
    (function () {
        const applyForm = document.getElementById('applyForm');
        const btn = document.getElementById('applyBtn');

        function goToNextPage() {
            const url = new URL(window.location.href);
            let page = parseInt(url.searchParams.get('page') || '1', 10);
            if (page < {$totalpages}) {
                url.searchParams.set('page', page + 1);
                window.location.href = url.toString();
            }
        }

        function applyThenAdvance() {
            const formData = new FormData(applyForm);
            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .catch(() => null)
                .finally(() => setTimeout(goToNextPage, 500));
        }

        // Если на странице есть что чинить - применяем автоматически, иначе просто листаем дальше.
        setTimeout(function () {
            if (btn && !btn.disabled) {
                applyThenAdvance();
            } else {
                goToNextPage();
            }
        }, 10000);
    })();
</script>
HTML;
}

?>

</div>

<?php
stdfoot();
