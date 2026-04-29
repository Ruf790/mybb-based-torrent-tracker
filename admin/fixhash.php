<?php
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger">Direct initialization of this file is not allowed.</div>');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ignore_user_abort(true);
define('FH_VERSION', '0.5 by xam');

require_once __DIR__ . '../../vendor/autoload.php';
require_once './include/global_config.php';

use Arokettu\Torrent\TorrentFile;

// Подсчёт общего числа торрентов
$query = $db->sql_query('SELECT COUNT(id) as cnt FROM torrents');
$row = mysqli_fetch_assoc($query);
$results = $row['cnt'] ?? 0;

$perpage = ($config['fixhash_perpage'] ?? 10);
$totalpages = max(1, ceil($results / $perpage));

$pagenumber = (isset($_GET['page']) && intval($_GET['page']) > 0) ? intval($_GET['page']) : 1;
if ($pagenumber < 1) {
    $pagenumber = 1;
} elseif ($pagenumber > $totalpages) {
    $pagenumber = $totalpages;
}

$limitlower = ($pagenumber - 1) * $perpage;

// Автообновление (каждые 10 секунд)
$autoRefresh = isset($_GET['auto']) && $_GET['auto'] === '1';

stdhead('Fix Torrent Hashes');
?>

<div class="container my-4">
    <h1 class="mb-4 fw-light">Fix Torrent Hashes <small class="text-muted">v<?= FH_VERSION ?></small></h1>

    <form method="get" action="index.php" class="d-flex align-items-center gap-3 flex-wrap mb-3">
        <input type="hidden" name="act" value="fixhash" />
        <input type="hidden" name="page" value="<?= $pagenumber ?>" id="page-input" />
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="autoRefreshSwitch" name="auto" value="1" <?= $autoRefresh ? 'checked' : '' ?>>
            <label class="form-check-label" for="autoRefreshSwitch">Auto Fix (every 10s)</label>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
    </form>

<?php

$countFixed = 0;

echo '<div class="table-responsive">';
echo '<table class="table table-hover align-middle">';
echo '<thead class="table-light"><tr>
        <th>Torrent</th>
        <th class="text-monospace" style="min-width:180px;">Old Hash</th>
        <th class="text-monospace" style="min-width:180px;">New Hash</th>
        <th>Status</th>
      </tr></thead><tbody>';

// Получаем торренты для текущей страницы
$res = $db->sql_query("SELECT id, name, info_hash FROM torrents ORDER BY added DESC LIMIT $limitlower, $perpage");

while ($row = mysqli_fetch_assoc($res)) {
    $torrentPath = TSDIR . '/' . $torrent_dir . '/' . $row['id'] . '.torrent';
    $fixed = false;
    $oldHash = $row['info_hash'] ?? 'N/A';
    $newHash = 'N/A';

    echo '<tr>';
    echo '<td><a href="' . htmlspecialchars($BASEURL) . '/' . get_torrent_link($row['id']) . '" target="_blank" class="text-decoration-none fw-semibold">' . htmlspecialchars_uni($row['name']) . '</a></td>';

    if (file_exists($torrentPath)) 
	{
        
		
		try 
		{
            $torrent = TorrentFile::load($torrentPath);
             $infoHash = $torrent->v1()->getInfoHash();
            $newHash = $infoHash ?: 'Error';

            if ($infoHash) {
                $update_hash = ['info_hash' => $db->escape_string($infoHash)];

                if ($db->update_query('torrents', $update_hash, "id='{$row['id']}'")) {
                    $fixed = true;
                    $countFixed++;
                }
            }
        } 
		catch (Exception $e) 
		{
            $newHash = 'Error';
        }
    } 
	else 
	{
        $newHash = '<span class="text-muted fst-italic">File missing</span>';
    }

    // Подсветка различий хешей
    $oldClass = ($oldHash === $newHash) ? 'text-success' : 'text-danger';
    $newClass = ($oldHash === $newHash) ? 'text-success' : 'text-danger';

    echo '<td class="text-monospace ' . $oldClass . '">' . htmlspecialchars($oldHash) . '</td>';
    echo '<td class="text-monospace ' . $newClass . '">' . htmlspecialchars($newHash) . '</td>';

    $statusBadge = $fixed
        ? '<span class="badge bg-success">Fixed</span>'
        : '<span class="badge bg-danger">Error</span>';

    echo '<td>' . $statusBadge . '</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

// Пагинация и прогресс
echo '<div class="d-flex justify-content-between align-items-center my-3 small text-muted">';
echo '<div>Page <strong>' . $pagenumber . '</strong> of <strong>' . $totalpages . '</strong></div>';
echo '<div>Fixed <strong>' . ts_nf($countFixed) . '</strong> torrents this page, total <strong>' . ts_nf($results) . '</strong></div>';
echo '</div>';

$progressPercent = intval(($pagenumber / $totalpages) * 100);
echo '<div class="progress mb-4" style="height: 12px;">';
echo '<div class="progress-bar bg-info" role="progressbar" style="width: ' . $progressPercent . '%;" aria-valuenow="' . $progressPercent . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '</div>';

// Навигация по страницам
echo '<nav aria-label="Page navigation">';
echo '<ul class="pagination justify-content-center pagination-sm">';
echo '<li class="page-item ' . ($pagenumber <= 1 ? 'disabled' : '') . '">';
echo '<a class="page-link" href="?act=fixhash&page=' . max(1, $pagenumber - 1) . '&auto=' . ($autoRefresh ? '1' : '0') . '" tabindex="-1">Previous</a></li>';

echo '<li class="page-item disabled"><a class="page-link" href="#">Page ' . $pagenumber . ' of ' . $totalpages . '</a></li>';

echo '<li class="page-item ' . ($pagenumber >= $totalpages ? 'disabled' : '') . '">';
echo '<a class="page-link" href="?act=fixhash&page=' . min($totalpages, $pagenumber + 1) . '&auto=' . ($autoRefresh ? '1' : '0') . '">Next</a></li>';
echo '</ul></nav>';

// Скрипт автообновления
if ($autoRefresh && $pagenumber < $totalpages) {
    echo <<<HTML
<script>
    setTimeout(() => {
        const url = new URL(window.location.href);
        let page = parseInt(url.searchParams.get('page') || '1', 10);
        if (page < {$totalpages}) {
            url.searchParams.set('page', page + 1);
            window.location.href = url.toString();
        }
    }, 10000);
</script>
HTML;
}

?>

</div>

<?php
stdfoot();
