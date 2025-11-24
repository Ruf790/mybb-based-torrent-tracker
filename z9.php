<?php
require_once 'global.php';

// Helper to sanitize date input
function sanitize_date($date_str) {
    $d = DateTime::createFromFormat('Y-m-d', $date_str);
    return $d && $d->format('Y-m-d') === $date_str ? $date_str : false;
}

// Get date range from GET, default last 7 days
$start_date = isset($_GET['start']) && sanitize_date($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-6 days'));
$end_date = isset($_GET['end']) && sanitize_date($_GET['end']) ? $_GET['end'] : date('Y-m-d');

$start_ts = strtotime($start_date);
$end_ts = strtotime($end_date) + 86399; // End of day

$days = floor(($end_ts - $start_ts) / 86400) + 1;

$data = [
    'labels' => [],
    'counts' => [],
    'activity' => [],
    'avg_time_per_user' => [],
];

// Fetch data day by day
for ($i = 0; $i < $days; $i++) {
    $day_start = $start_ts + $i * 86400;
    $day_end = $day_start + 86400;

    $q = $db->sql_query("
        SELECT COUNT(*) AS user_count, SUM(timeonline) AS total_time
        FROM users
        WHERE lastactive BETWEEN $day_start AND $day_end
    ");
    $r = $db->fetch_array($q);

    $user_count = (int) $r['user_count'];
    $total_time_hours = isset($r['total_time']) ? round($r['total_time'] / 3600, 2) : 0;
    $avg_time = $user_count > 0 ? round($total_time_hours / $user_count, 2) : 0;

    $data['labels'][] = date('Y-m-d', $day_start);
    $data['counts'][] = $user_count;
    $data['activity'][] = $total_time_hours;
    $data['avg_time_per_user'][] = $avg_time;
}

// If user clicked on a date bar, show usernames active on that date
$active_usernames = [];
$clicked_date = $_GET['date'] ?? null;
if ($clicked_date && sanitize_date($clicked_date)) {
    $clicked_start = strtotime($clicked_date);
    $clicked_end = $clicked_start + 86400;
    $q = $db->sql_query("SELECT username FROM users WHERE lastactive BETWEEN $clicked_start AND $clicked_end ORDER BY username ASC");
    while ($row = $db->fetch_array($q)) {
        $active_usernames[] = htmlspecialchars($row['username']);
    }
}

stdhead("Active Users Last 7 Days");
?>



<div class="container my-4 position-relative">

  <!-- Summary card -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body d-flex justify-content-around">
      <div class="text-center">
        <h5>Total Users</h5>
        <p class="fs-4 fw-bold"><?= array_sum($data['counts']) ?></p>
      </div>
      <div class="text-center">
        <h5>Avg Time Online (hrs)</h5>
        <p class="fs-4 fw-bold"><?= $days > 0 ? round(array_sum($data['activity']) / $days, 2) : 0 ?></p>
      </div>
    </div>
  </div>

  <!-- Date range form with presets -->
  <form method="GET" autocomplete="off" class="row g-3 align-items-center mb-4 justify-content-center">
    <div class="col-auto">
      <label for="start" class="col-form-label fw-bold">From:</label>
    </div>
    <div class="col-auto">
      <input type="date" id="start" name="start" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
    </div>

    <div class="col-auto">
      <label for="end" class="col-form-label fw-bold">To:</label>
    </div>
    <div class="col-auto">
      <input type="date" id="end" name="end" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
    </div>

    <div class="col-auto">
      <select id="datePreset" class="form-select" aria-label="Date Range Presets" title="Select date range preset">
        <option value="" <?= !isset($_GET['start']) && !isset($_GET['end']) ? 'selected' : '' ?>>Custom Range</option>
        <option value="7">Last 7 days</option>
        <option value="30">Last 30 days</option>
        <option value="this_month">This Month</option>
        <option value="last_month">Last Month</option>
      </select>
    </div>

    <div class="col-auto">
      <button type="submit" class="btn btn-primary" title="Show chart for selected date range">Show</button>
    </div>
  </form>

  <!-- Chart container -->
  <div class="card shadow-sm mb-4 position-relative">
    <div class="card-body">
      <h3 class="card-title mb-3 text-center">Active Users from <?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?></h3>

      <!-- Loading spinner -->
      <div id="loadingSpinner" class="position-absolute top-50 start-50 translate-middle d-none" style="z-index:10;">
        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
        <span class="visually-hidden">Loading...</span>
      </div>

      <canvas id="userChart" height="120"></canvas>

      <div class="mt-3 d-flex flex-wrap justify-content-center gap-2" id="exportButtons">
        <button id="exportCsv" class="btn btn-outline-success btn-sm" title="Export data as CSV file">Export CSV</button>
        <button id="exportPng" class="btn btn-outline-info btn-sm" title="Export chart image as PNG">Export Chart PNG</button>
      </div>

      <div class="mt-3 d-flex flex-wrap justify-content-center gap-2" id="extraButtons">
        <button id="resetDateRange" class="btn btn-outline-secondary btn-sm" title="Reset date range to default">Reset Date Range</button>
        <button id="toggleUsersCount" class="btn btn-outline-warning btn-sm" title="Toggle Users Count dataset visibility">Toggle Users Count</button>
        <button id="toggleTotalTime" class="btn btn-outline-primary btn-sm" title="Toggle Total Time Online dataset visibility">Toggle Total Time</button>
        <button id="toggleAvgTime" class="btn btn-outline-info btn-sm" title="Toggle Average Time per User dataset visibility">Toggle Avg Time</button>
        <button id="downloadJson" class="btn btn-outline-dark btn-sm" title="Download chart data as JSON file">Download JSON</button>
        <button id="toggleChartType" class="btn btn-outline-secondary btn-sm" title="Toggle chart type between bar+line and line only">Toggle Chart Type</button>
        <button id="refreshChart" class="btn btn-outline-primary btn-sm" title="Refresh chart animation">Refresh Chart</button>
      </div>
    </div>
  </div>

  <!-- Active users list on clicked date -->
  <?php if ($clicked_date): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="card-title">Users active on <?= htmlspecialchars($clicked_date) ?> (<?= count($active_usernames) ?>)</h4>
        <?php if (count($active_usernames) > 0): ?>
          <ul class="list-group list-group-flush mt-3" style="max-height: 250px; overflow-y: auto;">
            <?php foreach ($active_usernames as $user): ?>
              <li class="list-group-item"><?= $user ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted fst-italic mt-3">No users active on this date.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- Chart.js and Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
const labels = <?= json_encode($data['labels']) ?>;
const counts = <?= json_encode($data['counts']) ?>;
const activity = <?= json_encode($data['activity']) ?>;
const avgTime = <?= json_encode($data['avg_time_per_user']) ?>;

const ctx = document.getElementById('userChart').getContext('2d');

const userChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [
      {
        label: 'Users Count',
        data: counts,
        backgroundColor: 'rgba(255, 159, 64, 0.7)',
        borderColor: 'rgba(255, 159, 64, 1)',
        borderWidth: 1,
        yAxisID: 'yRight'
      },
      {
        label: 'Total Time Online (hrs)',
        data: activity,
        type: 'line',
        borderColor: 'rgba(54, 162, 235, 1)',
        backgroundColor: 'rgba(54, 162, 235, 0.2)',
        borderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.4,
        fill: false,
        yAxisID: 'yLeft'
      },
      {
        label: 'Avg Time per User (hrs)',
        data: avgTime,
        type: 'line',
        borderColor: 'rgba(75, 192, 192, 1)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.3,
        fill: true,
        yAxisID: 'yLeft'
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    animation: { duration: 1000, easing: 'easeOutQuart' },
    plugins: {
      tooltip: {
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';
            if (context.dataset.type === 'line') {
              label += ': ' + context.parsed.y + ' hrs';
            } else {
              label += ': ' + context.parsed.y + ' users';
            }
            return label;
          }
        }
      },
      legend: { position: 'top' },
      title: { display: false }
    },
    scales: {
      yLeft: {
        type: 'linear',
        position: 'left',
        title: { display: true, text: 'Time Online (hrs)' },
        beginAtZero: true,
        ticks: { precision: 0 }
      },
      yRight: {
        type: 'linear',
        position: 'right',
        title: { display: true, text: 'Users Count' },
        beginAtZero: true,
        grid: { drawOnChartArea: false },
        ticks: { precision: 0 }
      }
    }
  }
});

// Handle click on bars to show usernames on that date (redirect with date param)
ctx.canvas.onclick = function(evt) {
  const points = userChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
  if (points.length) {
    const idx = points[0].index;
    const dateClicked = labels[idx];
    // Redirect with date param + preserve current start/end filters
    const params = new URLSearchParams(window.location.search);
    params.set('date', dateClicked);
    window.location.search = params.toString();
  }
};

// Export CSV button
document.getElementById('exportCsv').addEventListener('click', () => {
  let csv = 'Date,Users Count,Total Time Online (hrs),Avg Time per User (hrs)\n';
  labels.forEach((label, i) => {
    csv += `${label},${counts[i]},${activity[i]},${avgTime[i]}\n`;
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'active_users.csv';
  a.click();
  URL.revokeObjectURL(url);
});

// Export PNG button
document.getElementById('exportPng').addEventListener('click', () => {
  const url = userChart.toBase64Image();
  const a = document.createElement('a');
  a.href = url;
  a.download = 'active_users_chart.png';
  a.click();
});

// Reset date range to last 7 days (reload page with no filters)
document.getElementById('resetDateRange').addEventListener('click', () => {
  const params = new URLSearchParams(window.location.search);
  params.delete('start');
  params.delete('end');
  params.delete('date');
  window.location.search = params.toString();
});

// Toggle datasets visibility helpers
function toggleDatasetVisibility(index) {
  const meta = userChart.getDatasetMeta(index);
  meta.hidden = meta.hidden === null ? !userChart.data.datasets[index].hidden : null;
  userChart.update();
}

document.getElementById('toggleUsersCount').addEventListener('click', () => {
  toggleDatasetVisibility(0); // Users Count (bar)
});

document.getElementById('toggleTotalTime').addEventListener('click', () => {
  toggleDatasetVisibility(1); // Total Time (line)
});

document.getElementById('toggleAvgTime').addEventListener('click', () => {
  toggleDatasetVisibility(2); // Avg Time (line)
});

// Download JSON of current chart data
document.getElementById('downloadJson').addEventListener('click', () => {
  const chartData = {
    labels: userChart.data.labels,
    datasets: userChart.data.datasets.map(ds => ({
      label: ds.label,
      data: ds.data,
      type: ds.type || 'bar',
    }))
  };
  const blob = new Blob([JSON.stringify(chartData, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'active_users_data.json';
  a.click();
  URL.revokeObjectURL(url);
});

// Toggle chart type between bar+line (default) and line-only
let lineOnly = false;
document.getElementById('toggleChartType').addEventListener('click', () => {
  lineOnly = !lineOnly;
  userChart.data.datasets.forEach((ds, i) => {
    if (i === 0) { // Users Count dataset
      ds.type = lineOnly ? 'line' : 'bar';
      ds.backgroundColor = lineOnly ? 'rgba(255, 159, 64, 0.2)' : 'rgba(255, 159, 64, 0.7)';
      ds.borderColor = 'rgba(255, 159, 64, 1)';
      ds.fill = false;
      ds.borderWidth = 2;
      ds.pointRadius = 4;
    }
    else { // Keep line datasets as line
      ds.type = 'line';
      ds.fill = i === 2; // fill avg time only
    }
  });
  userChart.update();
});

// Show/hide loading spinner helper
function showSpinner(show = true) {
  document.getElementById('loadingSpinner').classList.toggle('d-none', !show);
}

// Refresh chart with spinner
document.getElementById('refreshChart').addEventListener('click', () => {
  showSpinner(true);
  setTimeout(() => {
    userChart.update();
    showSpinner(false);
  }, 600); // simulate slight delay for effect
});

// Date Presets dropdown fills date inputs
document.getElementById('datePreset').addEventListener('change', function() {
  const now = new Date();
  let start, end = new Date();

  switch (this.value) {
    case '7':
      start = new Date(now);
      start.setDate(now.getDate() - 6);
      break;
    case '30':
      start = new Date(now);
      start.setDate(now.getDate() - 29);
      break;
    case 'this_month':
      start = new Date(now.getFullYear(), now.getMonth(), 1);
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      break;
    case 'last_month':
      start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      end = new Date(now.getFullYear(), now.getMonth(), 0);
      break;
    default:
      return; // custom range, do nothing
  }

  function formatDate(d) {
    return d.toISOString().slice(0, 10);
  }

  document.getElementById('start').value = formatDate(start);
  document.getElementById('end').value = formatDate(end);
});

// Bootstrap tooltip init for all elements with title attribute
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
</script>

<?php stdfoot(); ?>
