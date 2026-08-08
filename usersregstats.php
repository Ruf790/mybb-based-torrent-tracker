<?php
declare(strict_types=1);

require_once 'global.php';

// ── Валидация параметров ──────────────────────────────────────────────────────
// FIX: $group из $_GET попадал напрямую в SQL через $format — SQL injection
$allowed_groups = ['day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'];
$group  = isset($_GET['group']) && array_key_exists($_GET['group'], $allowed_groups)
    ? $_GET['group'] : 'month';
$format = $allowed_groups[$group];

$titles = ['day' => 'by Day', 'month' => 'by Month', 'year' => 'by Year'];
$xlabels = ['day' => 'Date', 'month' => 'Month', 'year' => 'Year'];
$title  = 'User Registrations — ' . $titles[$group];
$xlabel = $xlabels[$group];

// ── Диапазон дат из БД ────────────────────────────────────────────────────────
// FIX: sql_query() → sql_query_prepared()
$res = $db->sql_query_prepared('SELECT MIN(added) AS min_added, MAX(added) AS max_added FROM users');
$row = $db->fetch_array($res);
$minDate = $row && $row['min_added'] ? date('Y-m-d', (int)$row['min_added']) : date('Y-m-d');
$maxDate = $row && $row['max_added'] ? date('Y-m-d', (int)$row['max_added']) : date('Y-m-d');

function sanitize_date(string $s): bool {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

$fromDate = (isset($_GET['from']) && sanitize_date($_GET['from'])) ? $_GET['from'] : $minDate;
$toDate   = (isset($_GET['to'])   && sanitize_date($_GET['to']))   ? $_GET['to']   : $maxDate;

// Зажимаем в допустимый диапазон
$fromDate = max($fromDate, $minDate);
$toDate   = min($toDate,   $maxDate);
if ($toDate < $fromDate) $toDate = $fromDate;

$fromTs = (int)strtotime($fromDate . ' 00:00:00');
$toTs   = (int)strtotime($toDate   . ' 23:59:59');

// ── Статистика ────────────────────────────────────────────────────────────────
// FIX: sql_query() с конкатенацией → sql_query_prepared()
$res         = $db->sql_query_prepared('SELECT COUNT(*) AS total FROM users WHERE added BETWEEN ? AND ?', [$fromTs, $toTs]);
$total_users = (int)($db->fetch_array($res)['total'] ?? 0);

// FIX: и $format (из whitelist, но всё равно), и метки времени — через плейсхолдеры
$result = $db->sql_query_prepared("
    SELECT FROM_UNIXTIME(added, ?) AS reg_group, COUNT(*) AS count
    FROM users
    WHERE added BETWEEN ? AND ?
    GROUP BY reg_group
    ORDER BY reg_group ASC
", [$format, $fromTs, $toTs]);
$labels = $counts = [];
while ($row = $db->fetch_array($result)) {
    $labels[] = $row['reg_group'];
    $counts[] = (int)$row['count'];
}

$lastWeekTs  = (int)strtotime('-7 days 00:00:00');
$lastMonthTs = (int)strtotime('-1 month 00:00:00');

// FIX: sql_query() с конкатенацией → sql_query_prepared()
$res        = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM users WHERE added >= ?', [$lastWeekTs]);
$weekCount  = (int)($db->fetch_array($res)['c'] ?? 0);
$res        = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM users WHERE added >= ?', [$lastMonthTs]);
$monthCount = (int)($db->fetch_array($res)['c'] ?? 0);

// FIX: sql_query() → sql_query_prepared()
$res      = $db->sql_query_prepared('SELECT username, added FROM users ORDER BY added DESC LIMIT 1');
$lastUser = $db->fetch_array($res);

$maxReg = $counts ? max($counts) : 0;
$avgReg = $counts ? round(array_sum($counts) / count($counts), 2) : 0;

// ── JS данные ─────────────────────────────────────────────────────────────────
$js_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
$js_counts = json_encode($counts, JSON_UNESCAPED_UNICODE);
$js_xlabel = json_encode($xlabel);
$h_group   = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
$h_from    = htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8');
$h_to      = htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8');
$h_min     = htmlspecialchars($minDate, ENT_QUOTES, 'UTF-8');
$h_max     = htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8');

// FIX: stdhead() вызывается ДО HTML, <title> отдельно не нужен
stdhead($title);

?>
<div class="container mt-4">

    <!-- Пресеты -->
    <div class="d-flex justify-content-center gap-2 mb-4 flex-wrap">
        <a href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= date('Y-m-d') ?>&group=day"
           class="btn btn-outline-primary btn-sm">Last 7 Days</a>
        <a href="?from=<?= date('Y-m-d', strtotime('-29 days')) ?>&to=<?= date('Y-m-d') ?>&group=day"
           class="btn btn-outline-primary btn-sm">Last 30 Days</a>
        <a href="?from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>&group=month"
           class="btn btn-outline-primary btn-sm">This Year</a>
        <a href="?from=<?= $h_min ?>&to=<?= $h_max ?>&group=month"
           class="btn btn-outline-secondary btn-sm">All Time</a>
    </div>

    <!-- Сводка -->
    <div class="row g-3 mb-4 justify-content-center text-center">
        <div class="col-auto">
            <span class="badge bg-primary fs-6">
                <i class="fas fa-users me-1"></i>In range: <?= number_format($total_users) ?>
            </span>
        </div>
        <div class="col-auto">
            <span class="badge bg-success fs-6">
                <i class="fas fa-calendar-week me-1"></i>This week: <?= number_format($weekCount) ?>
            </span>
        </div>
        <div class="col-auto">
            <span class="badge bg-info fs-6">
                <i class="fas fa-calendar-alt me-1"></i>This month: <?= number_format($monthCount) ?>
            </span>
        </div>
    </div>

    <?php if ($lastUser): ?>
    <p class="text-center text-muted small mb-3">
        <i class="fas fa-user-clock me-1"></i>Last registered:
        <strong><?= htmlspecialchars($lastUser['username'], ENT_QUOTES, 'UTF-8') ?></strong>
        (<?= date('Y-m-d', (int)$lastUser['added']) ?>)
    </p>
    <?php endif; ?>

    <!-- Мини-карточки -->
    <div class="d-flex justify-content-center gap-3 mb-4 flex-wrap">
        <div class="card text-center" style="min-width:11rem;">
            <div class="card-body">
                <div class="text-muted small">Avg per <?= $h_group ?></div>
                <div class="fs-4 fw-bold"><?= number_format($avgReg) ?></div>
            </div>
        </div>
        <div class="card text-center" style="min-width:11rem;">
            <div class="card-body">
                <div class="text-muted small">Max per <?= $h_group ?></div>
                <div class="fs-4 fw-bold"><?= number_format($maxReg) ?></div>
            </div>
        </div>
    </div>

    <!-- Фильтр -->
    <form method="get" class="row g-3 align-items-center justify-content-center mb-4" id="filterForm">
        <div class="col-auto">
            <label for="from" class="col-form-label fw-semibold">From</label>
        </div>
        <div class="col-auto">
            <input type="date" id="from" name="from" class="form-control"
                   value="<?= $h_from ?>" min="<?= $h_min ?>" max="<?= $h_max ?>">
        </div>
        <div class="col-auto">
            <label for="to" class="col-form-label fw-semibold">To</label>
        </div>
        <div class="col-auto">
            <input type="date" id="to" name="to" class="form-control"
                   value="<?= $h_to ?>" min="<?= $h_min ?>" max="<?= $h_max ?>">
        </div>
        <div class="col-auto">
            <select name="group" id="groupSel" class="form-select">
                <option value="day"   <?= $group === 'day'   ? 'selected' : '' ?>>Day</option>
                <option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Month</option>
                <option value="year"  <?= $group === 'year'  ? 'selected' : '' ?>>Year</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
        </div>
    </form>

    <!-- Управление графиком -->
    <div class="d-flex justify-content-center align-items-center gap-3 mb-3 flex-wrap">
        <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="radio" name="chartType" id="ctBar" value="bar" checked>
            <label class="form-check-label" for="ctBar">Bar</label>
        </div>
        <div class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="radio" name="chartType" id="ctLine" value="line">
            <label class="form-check-label" for="ctLine">Line</label>
        </div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="cumulativeToggle" role="switch">
            <label class="form-check-label" for="cumulativeToggle">Cumulative</label>
        </div>
        <button id="exportCsvBtn" class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-csv me-1"></i>CSV
        </button>
        <button id="exportPngBtn" class="btn btn-outline-info btn-sm">
            <i class="fas fa-image me-1"></i>PNG
        </button>
    </div>

    <!-- График -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <canvas id="regChart" height="100"></canvas>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var labels         = <?= $js_labels ?>;
    var originalValues = <?= $js_counts ?>;
    var xlabel         = <?= $js_xlabel ?>;

    var getColors = function (vals) {
        return vals.map(function (v) {
            return v < 5  ? 'rgba(75,192,192,0.6)'  :
                   v < 20 ? 'rgba(255,206,86,0.6)'  :
                            'rgba(255,99,132,0.6)';
        });
    };
    var getBorders = function (vals) {
        return vals.map(function (v) {
            return v < 5  ? 'rgba(75,192,192,1)'  :
                   v < 20 ? 'rgba(255,206,86,1)'  :
                            'rgba(255,99,132,1)';
        });
    };

    var ctx       = document.getElementById('regChart').getContext('2d');
    var chartType = 'bar';
    var chart;

    function buildConfig(type, dataVals) {
        return {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Registrations',
                    data: dataVals,
                    backgroundColor: type === 'bar' ? getColors(dataVals) : 'rgba(54,162,235,0.2)',
                    borderColor:     type === 'bar' ? getBorders(dataVals) : 'rgba(54,162,235,1)',
                    borderWidth: 2,
                    fill: type === 'line',
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }],
            },
            options: {
                responsive: true,
                animation: { duration: 600 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                var curr = c.parsed.y;
                                var prev = c.dataIndex > 0 ? c.dataset.data[c.dataIndex - 1] : null;
                                var pct  = (prev && prev > 0)
                                    ? ' (' + ((curr - prev) / prev * 100).toFixed(1) + '%)'
                                    : '';
                                return curr + ' users' + pct;
                            },
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Users' } },
                    x: { ticks: { autoSkip: true, maxRotation: 60 }, title: { display: true, text: xlabel } },
                },
            },
        };
    }

    function getValues() {
        if (!document.getElementById('cumulativeToggle').checked) {
            return originalValues.slice();
        }
        var cum = [], acc = 0;
        originalValues.forEach(function (v) { acc += v; cum.push(acc); });
        return cum;
    }

    function renderChart() {
        if (chart) chart.destroy();
        chart = new Chart(ctx, buildConfig(chartType, getValues()));
    }

    renderChart();

    // Chart type
    document.querySelectorAll('input[name="chartType"]').forEach(function (el) {
        el.addEventListener('change', function () { chartType = el.value; renderChart(); });
    });

    // Cumulative
    document.getElementById('cumulativeToggle').addEventListener('change', renderChart);

    // Group select — auto-submit on change
    document.getElementById('groupSel').addEventListener('change', function () {
        document.getElementById('filterForm').submit();
    });

    // Export CSV
    document.getElementById('exportCsvBtn').addEventListener('click', function () {
        var csv = 'Date,Registrations\n';
        labels.forEach(function (l, i) { csv += l + ',' + originalValues[i] + '\n'; });
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        var a   = Object.assign(document.createElement('a'), { href: url, download: 'reg_stats.csv' });
        a.click(); URL.revokeObjectURL(url);
    });

    // Export PNG
    document.getElementById('exportPngBtn').addEventListener('click', function () {
        var a = Object.assign(document.createElement('a'), { href: chart.toBase64Image(), download: 'reg_chart.png' });
        a.click();
    });

    // Fade on form submit
    document.getElementById('filterForm').addEventListener('submit', function (e) {
        document.getElementById('regChart').style.opacity = '0';
        setTimeout(function () { e.target.submit(); }, 250);
        e.preventDefault();
    });

}());
</script>

<?php stdfoot(); ?>