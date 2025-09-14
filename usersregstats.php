<?php
require_once 'global.php';

// Get min and max user registration dates (UNIX timestamps)
$res = $db->sql_query("SELECT MIN(added) AS min_added, MAX(added) AS max_added FROM users");
$row = $db->fetch_array($res);
$minDate = $row && $row['min_added'] ? date('Y-m-d', $row['min_added']) : date('Y-m-d');
$maxDate = $row && $row['max_added'] ? date('Y-m-d', $row['max_added']) : date('Y-m-d');

// Get & sanitize GET parameters with fallback defaults
$group = $_GET['group'] ?? 'month';
$fromDate = $_GET['from'] ?? $minDate;
$toDate = $_GET['to'] ?? $maxDate;

// Clamp date inputs to valid range
if ($fromDate < $minDate) $fromDate = $minDate;
if ($toDate > $maxDate) $toDate = $maxDate;
if ($toDate < $fromDate) $toDate = $fromDate;

// Convert to timestamps (start and end of days)
$fromTimestamp = strtotime($fromDate . ' 00:00:00');
$toTimestamp = strtotime($toDate . ' 23:59:59');

// Determine grouping format for SQL and labels
switch ($group) {
    case 'year':
        $format = '%Y';
        $title = 'User Registrations (Grouped by Year)';
        $xlabel = 'Year';
        break;
    case 'day':
        $format = '%Y-%m-%d';
        $title = 'User Registrations (Grouped by Day)';
        $xlabel = 'Date';
        break;
    case 'month':
    default:
        $group = 'month';
        $format = '%Y-%m';
        $title = 'User Registrations (Grouped by Month)';
        $xlabel = 'Month';
        break;
}

// Fetch total users count in range
$totalSql = "SELECT COUNT(*) AS total FROM users WHERE added BETWEEN $fromTimestamp AND $toTimestamp";
$res = $db->sql_query($totalSql);
$total_users = 0;
if ($row = $db->fetch_array($res)) {
    $total_users = $row['total'];
}

// Fetch registration stats grouped and filtered by date range
$sql = "
    SELECT 
        FROM_UNIXTIME(added, '$format') AS reg_group,
        COUNT(*) AS count
    FROM users
    WHERE added BETWEEN $fromTimestamp AND $toTimestamp
    GROUP BY reg_group
    ORDER BY reg_group ASC
";

$result = $db->sql_query($sql);
$labels = [];
$counts = [];
while ($row = $db->fetch_array($result)) {
    $labels[] = $row['reg_group'];
    $counts[] = (int)$row['count'];
}

// New users this week/month
$lastWeek = strtotime('-7 days 00:00:00');
$lastMonth = strtotime('-1 month 00:00:00');

$res = $db->sql_query("SELECT COUNT(*) AS week_count FROM users WHERE added >= $lastWeek");
$weekCount = ($row = $db->fetch_array($res)) ? $row['week_count'] : 0;

$res = $db->sql_query("SELECT COUNT(*) AS month_count FROM users WHERE added >= $lastMonth");
$monthCount = ($row = $db->fetch_array($res)) ? $row['month_count'] : 0;

// Last registered user
$res = $db->sql_query("SELECT username, added FROM users ORDER BY added DESC LIMIT 1");
$lastUser = $db->fetch_array($res);

$maxReg = count($counts) ? max($counts) : 0;
$avgReg = count($counts) ? round(array_sum($counts) / count($counts), 2) : 0;

stdhead("Registration Stats");
?>



<title><?= htmlspecialchars($title) ?></title>

<div class="container mt-4">

  <!-- Date Range Presets -->
  <div class="d-flex justify-content-center gap-2 mb-4 flex-wrap">
    <a href="?from=<?=date('Y-m-d', strtotime('-6 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm">Last 7 Days</a>
    <a href="?from=<?=date('Y-m-d', strtotime('-29 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm">Last 30 Days</a>
    <a href="?from=<?=date('Y-01-01')?>&to=<?=date('Y-m-d')?>&group=month" class="btn btn-outline-primary btn-sm">This Year</a>
    <a href="?from=<?=htmlspecialchars($minDate)?>&to=<?=htmlspecialchars($maxDate)?>&group=month" class="btn btn-outline-secondary btn-sm">All Time</a>
  </div>

  <!-- Total Users Badge -->
  <div class="text-center mb-2">
    <span class="badge bg-primary fs-5">
      <i class="fas fa-user me-2"></i> Total Users in Range: <?= number_format($total_users) ?>
    </span>
  </div>

  <!-- New users this week/month -->
  <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
    <span class="badge bg-success fs-6">
      <i class="fas fa-calendar-week me-1"></i> New This Week: <?= number_format($weekCount) ?>
    </span>
    <span class="badge bg-info fs-6">
      <i class="fas fa-calendar-alt me-1"></i> New This Month: <?= number_format($monthCount) ?>
    </span>
  </div>

  <!-- Last registered user -->
  <div class="text-center mb-3">
    <small>
      <i class="fas fa-user-clock me-1"></i> Last Registered User: 
      <strong><?= htmlspecialchars($lastUser['username']) ?></strong> 
      (<?= date('Y-m-d', $lastUser['added']) ?>)
    </small>
  </div>

  <!-- Summary Cards -->
  <div class="d-flex justify-content-center gap-3 mb-4 flex-wrap">
    <div class="card text-center" style="width: 12rem;">
      <div class="card-body">
        <h6 class="card-title">Average per <?=htmlspecialchars($group)?></h6>
        <p class="card-text fs-4 fw-bold"><?= number_format($avgReg) ?></p>
      </div>
    </div>
    <div class="card text-center" style="width: 12rem;">
      <div class="card-body">
        <h6 class="card-title">Max per <?=htmlspecialchars($group)?></h6>
        <p class="card-text fs-4 fw-bold"><?= number_format($maxReg) ?></p>
      </div>
    </div>
  </div>

  <!-- Filters Form -->
  <form method="get" class="row g-3 align-items-center justify-content-center mb-4" id="filterForm">

    <div class="col-auto">
      <label for="from" class="col-form-label fw-bold">From:</label>
      <input
        type="date"
        id="from"
        name="from"
        class="form-control"
        value="<?= htmlspecialchars($fromDate) ?>"
        min="<?= $minDate ?>"
        max="<?= $maxDate ?>"
        required
      >
    </div>

    <div class="col-auto">
      <label for="to" class="col-form-label fw-bold">To:</label>
      <input
        type="date"
        id="to"
        name="to"
        class="form-control"
        value="<?= htmlspecialchars($toDate) ?>"
        min="<?= $minDate ?>"
        max="<?= $maxDate ?>"
        required
      >
    </div>

    <div class="col-auto">
      <label for="group" class="col-form-label fw-bold">Group By:</label>
      <select
        name="group"
        id="group"
        class="form-select"
        onchange="this.form.submit()"
      >
        <option value="day" <?= $group === 'day' ? 'selected' : '' ?>>Day</option>
        <option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Month</option>
        <option value="year" <?= $group === 'year' ? 'selected' : '' ?>>Year</option>
      </select>
    </div>

    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary">Filter</button>
    </div>

  </form>

  <!-- Chart Type Toggle + Cumulative + Dark Mode -->
  <div class="text-center mb-4 d-flex justify-content-center align-items-center gap-3 flex-wrap">

    <div>
      <label class="me-3">
        <input type="radio" name="chartType" value="bar" checked> Bar Chart
      </label>
      <label>
        <input type="radio" name="chartType" value="line"> Line Chart
      </label>
    </div>

    <label class="ms-3">
      <input type="checkbox" id="cumulativeToggle"> Show Cumulative Total
    </label>

    <button id="darkModeToggle" class="btn btn-outline-secondary btn-sm ms-3">Toggle Dark Mode</button>

  </div>

  <!-- Export buttons -->
  <div class="text-center mb-4">
    <button id="exportCsvBtn" class="btn btn-outline-secondary btn-sm me-2">Export Data CSV</button>
    <button id="exportPngBtn" class="btn btn-outline-secondary btn-sm">Export Chart PNG</button>
  </div>

  <!-- Chart canvas -->
  <canvas id="regChart" height="100"></canvas>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  const labels = <?= json_encode($labels) ?>;
  const values = <?= json_encode($counts) ?>;
  const originalValues = [...values];

  // Color thresholds for bars/lines
  const getColors = vals => vals.map(v => {
    if (v < 5) return 'rgba(75, 192, 192, 0.6)';       // low = teal
    if (v < 20) return 'rgba(255, 206, 86, 0.6)';      // medium = yellow
    return 'rgba(255, 99, 132, 0.6)';                  // high = red
  });
  const getBorderColors = vals => vals.map(v => {
    if (v < 5) return 'rgba(75, 192, 192, 1)';
    if (v < 20) return 'rgba(255, 206, 86, 1)';
    return 'rgba(255, 99, 132, 1)';
  });

  let chartType = 'bar';

  const ctx = document.getElementById('regChart').getContext('2d');

  const createChartConfig = (type, dataVals) => ({
    type,
    data: {
      labels,
      datasets: [{
        label: 'Registrations',
        data: dataVals,
        backgroundColor: type === 'bar' ? getColors(dataVals) : 'transparent',
        borderColor: getBorderColors(dataVals),
        borderWidth: 2,
        fill: type === 'line',
        tension: 0.3,
        pointRadius: 5,
        pointHoverRadius: 7,
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          title: { display: true, text: 'User Count', color: window.isDarkMode ? '#eee' : '#000' }
        },
        x: {
          ticks: {
            autoSkip: true,
            maxRotation: 90,
            minRotation: 45,
            color: window.isDarkMode ? '#eee' : '#000',
          },
          title: { display: true, text: '<?= $xlabel ?>', color: window.isDarkMode ? '#eee' : '#000' }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: window.isDarkMode ? 'rgba(255,255,255,0.9)' : 'rgba(0,0,0,0.7)',
          titleColor: window.isDarkMode ? '#000' : '#fff',
          bodyColor: window.isDarkMode ? '#000' : '#fff',
          callbacks: {
            label: ctx => {
              const curr = ctx.parsed.y;
              const prev = ctx.dataIndex > 0 ? ctx.dataset.data[ctx.dataIndex - 1] : null;
              let pct = '';
              if (prev !== null && prev > 0) {
                pct = ` (${((curr - prev) / prev * 100).toFixed(1)}%)`;
              }
              return `${curr} users${pct}`;
            }
          }
        }
      }
    }
  });

  let chart = new Chart(ctx, createChartConfig(chartType, values));

  // Chart type toggle handler
  document.querySelectorAll('input[name="chartType"]').forEach(el => {
    el.addEventListener('change', e => {
      chartType = e.target.value;
      updateChart();
    });
  });

  // Cumulative toggle handler
  document.getElementById('cumulativeToggle').addEventListener('change', (e) => {
    updateChart();
  });

  function updateChart() {
    let dataVals;
    const cumulative = document.getElementById('cumulativeToggle').checked;

    if (cumulative) {
      dataVals = [];
      originalValues.reduce((a, b, i) => dataVals[i] = a + b, 0);
    } else {
      dataVals = [...originalValues];
    }

    chart.destroy();
    chart = new Chart(ctx, createChartConfig(chartType, dataVals));
  }

  // Export CSV functionality
  document.getElementById('exportCsvBtn').addEventListener('click', () => {
    let csv = 'Date,Registrations\n';
    labels.forEach((label, i) => {
      csv += `${label},${originalValues[i]}\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'registration_stats.csv';
    a.click();
    URL.revokeObjectURL(url);
  });

  // Export Chart PNG
  document.getElementById('exportPngBtn').addEventListener('click', () => {
    const link = document.createElement('a');
    link.href = chart.toBase64Image();
    link.download = 'registration_chart.png';
    link.click();
  });

  // Dark Mode toggle
  window.isDarkMode = false;
  const body = document.body;
  const darkBtn = document.getElementById('darkModeToggle');

  darkBtn.addEventListener('click', () => {
    window.isDarkMode = !window.isDarkMode;
    body.classList.toggle('dark-mode');

    // Update chart colors
    updateChart();
  });

  // Fade out on filter submit for smooth transition
  document.getElementById('filterForm').addEventListener('submit', e => {
    const canvas = document.getElementById('regChart');
    canvas.style.opacity = 0;
    setTimeout(() => e.target.submit(), 300);
    e.preventDefault();
  });

</script>

<style>
  /* Dark mode styles */
  .dark-mode {
    background-color: #121212;
    color: #eee;
  }
  .dark-mode .badge, 
  .dark-mode .btn,
  .dark-mode .form-control,
  .dark-mode .form-select,
  .dark-mode .card {
    background-color: #333 !important;
    color: #eee !important;
  }

  #regChart {
    transition: opacity 0.3s ease;
  }
</style>

<?php stdfoot(); ?>
