<?php

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger text-center"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

$lang->load('torrentstats');

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… produced by $lang->load()) with arguments.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

// Fetch categories
$categories = [];
$res = $db->sql_query_prepared("SELECT id, name FROM categories");
while ($res && ($row = $db->fetch_array($res))) {
    $categories[(int)$row['id']] = $row['name'];
}

// Min/max date range
$res = $db->sql_query_prepared("SELECT MIN(added) AS min_added, MAX(added) AS max_added FROM torrents WHERE visible='yes' AND banned='no'");
$row = $db->fetch_array($res);
$minDate = $row && $row['min_added'] ? date('Y-m-d', $row['min_added']) : date('Y-m-d');
$maxDate = $row && $row['max_added'] ? date('Y-m-d', $row['max_added']) : date('Y-m-d');

// Filter input
$group = $_GET['group'] ?? 'month';
$fromDate = $_GET['from'] ?? $minDate;
$toDate = $_GET['to'] ?? $maxDate;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || strtotime($fromDate) === false) {
    $fromDate = $minDate;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) || strtotime($toDate) === false) {
    $toDate = $maxDate;
}

if ($fromDate < $minDate) $fromDate = $minDate;
if ($toDate > $maxDate) $toDate = $maxDate;
if ($toDate < $fromDate) $toDate = $fromDate;

$fromTimestamp = strtotime($fromDate . ' 00:00:00');
$toTimestamp = strtotime($toDate . ' 23:59:59');

// Group format
switch ($group) {
    case 'year':  $format = '%Y';        $title = $lang->torrentstats['sec_added_year'];  break;
    case 'day':   $format = '%Y-%m-%d';  $title = $lang->torrentstats['sec_added_day'];   break;
    default:      $group = 'month';      $format = '%Y-%m';     $title = $lang->torrentstats['sec_added_month'];
}

// Chart 1: Torrents added over time
$res = $db->sql_query_prepared("
    SELECT FROM_UNIXTIME(added, '{$format}') AS time_group, COUNT(*) AS count
    FROM torrents
    WHERE visible='yes' AND banned='no' AND added BETWEEN ? AND ?
    GROUP BY time_group ORDER BY time_group ASC
", [$fromTimestamp, $toTimestamp]);
$timeLabels = $timeCounts = [];
while ($res && ($row = $db->fetch_array($res))) {
    $timeLabels[] = $row['time_group'];
    $timeCounts[] = (int)$row['count'];
}

// Summary stats in range
$res = $db->sql_query_prepared("
    SELECT
        COUNT(*) AS total,
        SUM(seeders) AS total_seeders,
        SUM(leechers) AS total_leechers,
        SUM(times_completed) AS total_completed,
        SUM(size) AS total_size
    FROM torrents
    WHERE visible='yes' AND banned='no' AND added BETWEEN ? AND ?
", [$fromTimestamp, $toTimestamp]);
$row = $db->fetch_array($res);
$totalTorrentsInRange = (int)$row['total'];
$totalSeeders = (int)$row['total_seeders'];
$totalLeechers = (int)$row['total_leechers'];
$totalCompleted = (int)$row['total_completed'];
$totalSizeBytes = (float)$row['total_size'];

function formatBytes($bytes) {
    global $lang;
    $L = $lang->torrentstats;
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 2) . ' ' . $L['unit_kb'];
    if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024), 2) . ' ' . $L['unit_mb'];
    if ($bytes < 1024 * 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024 * 1024), 2) . ' ' . $L['unit_gb'];
    return number_format($bytes / (1024 * 1024 * 1024 * 1024), 2) . ' ' . $L['unit_tb'];
}

// JS strings: js_* keys without the prefix
$agsJsLang = [];
foreach ($lang->torrentstats as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $agsJsLang[substr((string)$k, 3)] = $v;
    }
}

$L = $lang->torrentstats;

stdhead($L['page_title']);
?>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<div class="container mt-4">

  <h2 class="mb-4 text-center"><?= htmlspecialchars($L['sec_dashboard']) ?></h2>

  <!-- Preset Buttons -->
  <div class="d-flex justify-content-center gap-2 mb-4 flex-wrap">
    <a href="<?= $_this_script_ ?>&from=<?=date('Y-m-d', strtotime('-6 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars($L['btn_last_7']) ?></a>
    <a href="<?= $_this_script_ ?>&from=<?=date('Y-m-d', strtotime('-29 days'))?>&to=<?=date('Y-m-d')?>&group=day" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars($L['btn_last_30']) ?></a>
    <a href="<?= $_this_script_ ?>&from=<?=date('Y-01-01')?>&to=<?=date('Y-m-d')?>&group=month" class="btn btn-outline-primary btn-sm"><?= htmlspecialchars($L['btn_this_year']) ?></a>
    <a href="<?= $_this_script_ ?>&from=<?=htmlspecialchars($minDate)?>&to=<?=htmlspecialchars($maxDate)?>&group=month" class="btn btn-outline-secondary btn-sm"><?= htmlspecialchars($L['btn_all_time']) ?></a>
  </div>

  <!-- Filter Form -->
  <form method="get" class="row g-3 align-items-center justify-content-center mb-4" id="filterForm">
    <input type="hidden" name="act" value="<?= htmlspecialchars($_GET['act'] ?? '') ?>">
    <div class="col-auto">
      <label for="from" class="col-form-label fw-bold"><?= htmlspecialchars($L['lbl_from']) ?></label>
      <input type="date" id="from" name="from" class="form-control" value="<?=htmlspecialchars($fromDate)?>" min="<?= $minDate ?>" max="<?= $maxDate ?>" required>
    </div>
    <div class="col-auto">
      <label for="to" class="col-form-label fw-bold"><?= htmlspecialchars($L['lbl_to']) ?></label>
      <input type="date" id="to" name="to" class="form-control" value="<?=htmlspecialchars($toDate)?>" min="<?= $minDate ?>" max="<?= $maxDate ?>" required>
    </div>
    <div class="col-auto">
      <label for="group" class="col-form-label fw-bold"><?= htmlspecialchars($L['lbl_group_by']) ?></label>
      <select name="group" id="group" class="form-select" onchange="this.form.submit()">
        <option value="day" <?= $group === 'day' ? 'selected' : '' ?>><?= htmlspecialchars($L['opt_day']) ?></option>
        <option value="month" <?= $group === 'month' ? 'selected' : '' ?>><?= htmlspecialchars($L['opt_month']) ?></option>
        <option value="year" <?= $group === 'year' ? 'selected' : '' ?>><?= htmlspecialchars($L['opt_year']) ?></option>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary"><?= htmlspecialchars($L['btn_filter']) ?></button>
    </div>
  </form>

  <!-- Chart Title + Count -->
  <h4 class="mb-1 text-center"><?=htmlspecialchars($title)?></h4>
  <p class="text-center text-muted mb-4"><?= ags_fmt(htmlspecialchars($L['lbl_total_in_range']), '<strong>' . number_format($totalTorrentsInRange) . '</strong>') ?></p>

  <!-- Summary Cards -->
  <div class="row text-center mb-4">
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-upload text-primary fs-3 mb-2"></i>
          <div class="fw-bold text-primary"><?= htmlspecialchars($L['lbl_seeders']) ?></div>
          <div class="fs-5"><?= number_format($totalSeeders) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-download text-danger fs-3 mb-2"></i>
          <div class="fw-bold text-danger"><?= htmlspecialchars($L['lbl_leechers']) ?></div>
          <div class="fs-5"><?= number_format($totalLeechers) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-check2-circle text-success fs-3 mb-2"></i>
          <div class="fw-bold text-success"><?= htmlspecialchars($L['lbl_completed']) ?></div>
          <div class="fs-5"><?= number_format($totalCompleted) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3 animate__animated animate__fadeIn">
      <div class="card shadow-sm border-0 bg-light h-100">
        <div class="card-body">
          <i class="bi bi-hdd-network text-dark fs-3 mb-2"></i>
          <div class="fw-bold text-dark"><?= htmlspecialchars($L['lbl_total_size']) ?></div>
          <div class="fs-6"><?= htmlspecialchars(formatBytes($totalSizeBytes)) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart Container -->
  <div id="addedChart" style="width:100%; height:400px;"></div>
</div>

<!-- Highcharts.js -->
<script>
const AGS_LANG = <?= json_encode($agsJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const AGS_GROUP = <?= json_encode($group, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/highcharts.js"></script>
<script>
(function () {
  'use strict';

  // {1} / %1$s substitution
  function fmt(s, args) {
    args.forEach(function (a, i) {
      var n = i + 1, v = String(a);
      s = s.split('{' + n + '}').join(v).split('%' + n + '$s').join(v);
    });
    return s;
  }
  function tpl(key, fallback) {
    return (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
  }
  // Plain-text translation
  function t(key, fallback) {
    return fmt(tpl(key, fallback), Array.prototype.slice.call(arguments, 2));
  }
  // Highcharts parses its text options as HTML, so translations are escaped
  // to stay literal text; only the extra args (our own markup) are inserted raw.
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function tHtml(key, fallback) {
    return fmt(esc(tpl(key, fallback)), Array.prototype.slice.call(arguments, 2));
  }

  var axisTitles = {
    day:   t('axis_day', 'Date'),
    month: t('axis_month', 'Month'),
    year:  t('axis_year', 'Year')
  };

  Highcharts.chart('addedChart', {
    chart: {
      type: 'column',
      backgroundColor: 'transparent',
      animation: { duration: 700 }
    },
    title: { text: null },
    credits: { enabled: false },
    xAxis: {
      categories: <?= json_encode($timeLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: { text: esc(axisTitles[AGS_GROUP] || axisTitles.month) },
      labels: { style: { fontSize: '11px' } }
    },
    yAxis: {
      title: { text: esc(t('axis_count', 'Count')) },
      allowDecimals: false
    },
    tooltip: {
      headerFormat: '<b>{point.key}</b><br>',
      pointFormat: tHtml('tooltip_added', 'Torrents Added: {1}', '<b>{point.y}</b>')
    },
    legend: { enabled: false },
    plotOptions: {
      column: {
        borderRadius: 4,
        color: {
          linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
          stops: [
            [0, 'rgba(54, 162, 235, 0.9)'],
            [1, 'rgba(54, 162, 235, 0.5)']
          ]
        },
        borderWidth: 0
      }
    },
    series: [{
      name: esc(t('series_added', 'Torrents Added')),
      data: <?= json_encode($timeCounts) ?>
    }]
  });
})();
</script>

<?php stdfoot(); ?>
