<?php


// Fetch categories
$categories = [];
$res = $db->sql_query("SELECT id, name FROM categories");
while ($row = $db->fetch_array($res)) {
    $categories[(int)$row['id']] = $row['name'];
}

// Min/max date range
$res = $db->sql_query("SELECT MIN(added) AS min_added, MAX(added) AS max_added FROM torrents WHERE visible='yes' AND banned='no'");
$row = $db->fetch_array($res);
$minDate = $row && $row['min_added'] ? date('Y-m-d', $row['min_added']) : date('Y-m-d');
$maxDate = $row && $row['max_added'] ? date('Y-m-d', $row['max_added']) : date('Y-m-d');

// Filter input
$group = $_GET['group'] ?? 'month';
$fromDate = $_GET['from'] ?? $minDate;
$toDate = $_GET['to'] ?? $maxDate;
if ($fromDate < $minDate) $fromDate = $minDate;
if ($toDate > $maxDate) $toDate = $maxDate;
if ($toDate < $fromDate) $toDate = $fromDate;

$fromTimestamp = strtotime($fromDate . ' 00:00:00');
$toTimestamp = strtotime($toDate . ' 23:59:59');

// Group format
switch ($group) {
    case 'year':  $format = '%Y';        $title = 'Torrents Added (Yearly)';  $xlabel = 'Year'; break;
    case 'day':   $format = '%Y-%m-%d';  $title = 'Torrents Added (Daily)';   $xlabel = 'Date'; break;
    default:      $group = 'month';      $format = '%Y-%m';     $title = 'Torrents Added (Monthly)'; $xlabel = 'Month';
}

// Chart 1: Torrents added over time
$sql = "
    SELECT FROM_UNIXTIME(added, '$format') AS time_group, COUNT(*) AS count
    FROM torrents
    WHERE visible='yes' AND banned='no' AND added BETWEEN $fromTimestamp AND $toTimestamp
    GROUP BY time_group ORDER BY time_group ASC
";
$res = $db->sql_query($sql);
$timeLabels = $timeCounts = [];
while ($row = $db->fetch_array($res)) {
    $timeLabels[] = $row['time_group'];
    $timeCounts[] = (int)$row['count'];
}

// Summary stats in range
$sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(seeders) AS total_seeders,
        SUM(leechers) AS total_leechers,
        SUM(times_completed) AS total_completed,
        SUM(size) AS total_size
    FROM torrents
    WHERE visible='yes' AND banned='no' AND added BETWEEN $fromTimestamp AND $toTimestamp
";
$res = $db->sql_query($sql);
$row = $db->fetch_array($res);
$totalTorrentsInRange = (int)$row['total'];
$totalSeeders = (int)$row['total_seeders'];
$totalLeechers = (int)$row['total_leechers'];
$totalCompleted = (int)$row['total_completed'];
$totalSizeBytes = (float)$row['total_size'];

function formatBytes($bytes) {
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024), 2) . ' MB';
    if ($bytes < 1024 * 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    return number_format($bytes / (1024 * 1024 * 1024 * 1024), 2) . ' TB';
}

stdhead("Torrent Stats");
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<div class="container mt-4">

  <h2 class="mb-4 text-center">Torrent Statistics Dashboard</h2>

  <!-- Preset Buttons -->
  <div class="d-flex justify-content-center gap-2 mb-4 flex-wrap">
    <a href="?from=<?=date('Y-m-d', strtotime('-6 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm">Last 7 Days</a>
    <a href="?from=<?=date('Y-m-d', strtotime('-29 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm">Last 30 Days</a>
    <a href="?from=<?=date('Y-01-01')?>&to=<?=date('Y-m-d')?>&group=month" class="btn btn-outline-primary btn-sm">This Year</a>
    <a href="?from=<?=htmlspecialchars($minDate)?>&to=<?=htmlspecialchars($maxDate)?>&group=month" class="btn btn-outline-secondary btn-sm">All Time</a>
  </div>

  <!-- Filter Form -->
  <form method="get" class="row g-3 align-items-center justify-content-center mb-4" id="filterForm">
    <div class="col-auto">
      <label for="from" class="col-form-label fw-bold">From:</label>
      <input type="date" id="from" name="from" class="form-control" value="<?=htmlspecialchars($fromDate)?>" min="<?= $minDate ?>" max="<?= $maxDate ?>" required>
    </div>
    <div class="col-auto">
      <label for="to" class="col-form-label fw-bold">To:</label>
      <input type="date" id="to" name="to" class="form-control" value="<?=htmlspecialchars($toDate)?>" min="<?= $minDate ?>" max="<?= $maxDate ?>" required>
    </div>
    <div class="col-auto">
      <label for="group" class="col-form-label fw-bold">Group By:</label>
      <select name="group" id="group" class="form-select" onchange="this.form.submit()">
        <option value="day" <?= $group === 'day' ? 'selected' : '' ?>>Day</option>
        <option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Month</option>
        <option value="year" <?= $group === 'year' ? 'selected' : '' ?>>Year</option>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary">Filter</button>
    </div>
  </form>

  <!-- Chart Title + Count -->
  <h4 class="mb-1 text-center"><?=htmlspecialchars($title)?></h4>
  <p class="text-center text-muted mb-4">Total Torrents in Range: <strong><?= number_format($totalTorrentsInRange) ?></strong></p>

  <!-- Summary Cards -->
  <div class="row text-center mb-4">
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-upload text-primary fs-3 mb-2"></i>
          <div class="fw-bold text-primary">Seeders</div>
          <div class="fs-5"><?= number_format($totalSeeders) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-download text-danger fs-3 mb-2"></i>
          <div class="fw-bold text-danger">Leechers</div>
          <div class="fs-5"><?= number_format($totalLeechers) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-check2-circle text-success fs-3 mb-2"></i>
          <div class="fw-bold text-success">Times Completed</div>
          <div class="fs-5"><?= number_format($totalCompleted) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-hdd-network text-dark fs-3 mb-2"></i>
          <div class="fw-bold text-dark">Total Size</div>
          <div class="fs-6"><?= formatBytes($totalSizeBytes) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart Canvas -->
  <canvas id="addedChart" height="100"></canvas>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const addedChart = new Chart(document.getElementById('addedChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($timeLabels) ?>,
    datasets: [{
      label: 'Torrents Added',
      data: <?= json_encode($timeCounts) ?>,
      backgroundColor: 'rgba(54, 162, 235, 0.7)',
      borderColor: 'rgba(54, 162, 235, 1)',
      borderWidth: 1,
      borderRadius: 3,
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Count' } },
      x: { title: { display: true, text: '<?= $xlabel ?>' } }
    },
    plugins: { legend: { display: false } }
  }
});
</script>

<?php stdfoot(); ?>
