<?php
declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('useractivity');

if (!function_exists('ags_fmt')) {
    /**
     * Substitutes {1}, {2}… (and %1$s, %2$s… — $lang->load() converts {N} to that form).
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

function sanitize_date(string $date_str): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date_str);
    return $d && $d->format('Y-m-d') === $date_str;
}

$start_date = (isset($_GET['start']) && sanitize_date($_GET['start']))
    ? $_GET['start'] : date('Y-m-d', strtotime('-6 days'));

$end_date = (isset($_GET['end']) && sanitize_date($_GET['end']))
    ? $_GET['end'] : date('Y-m-d');

$start_ts = (int)strtotime($start_date);
$end_ts   = (int)strtotime($end_date) + 86399;

if (($end_ts - $start_ts) > 90 * 86400) {
    $start_ts   = $end_ts - 90 * 86400;
    $start_date = date('Y-m-d', $start_ts);
}
if ($start_ts > $end_ts) {
    [$start_ts, $end_ts]     = [$end_ts - 86399, $start_ts + 86399];
    [$start_date, $end_date] = [$end_date, $start_date];
}

$days = (int)floor(($end_ts - $start_ts) / 86400) + 1;

$q = $db->sql_query_prepared("
    SELECT DATE(FROM_UNIXTIME(lastactive)) AS day,
           COUNT(*) AS user_count, SUM(timeonline) AS total_time
    FROM users
    WHERE lastactive BETWEEN ? AND ?
    GROUP BY day ORDER BY day ASC
", [$start_ts, $end_ts]);
$db_rows = [];
while ($row = $db->fetch_array($q)) { $db_rows[$row['day']] = $row; }

$data = ['labels' => [], 'counts' => [], 'activity' => [], 'avg_time_per_user' => []];
for ($i = 0; $i < $days; $i++) {
    $day_ts  = $start_ts + $i * 86400;
    $day_key = date('Y-m-d', $day_ts);
    $row     = $db_rows[$day_key] ?? null;
    $uc      = (int)($row['user_count'] ?? 0);
    $th      = $row ? round((float)$row['total_time'] / 3600, 2) : 0.0;
    $avg     = $uc > 0 ? round($th / $uc, 2) : 0.0;
    $data['labels'][]            = $day_key;
    $data['counts'][]            = $uc;
    $data['activity'][]          = $th;
    $data['avg_time_per_user'][] = $avg;
}

$active_usernames = [];
$clicked_date     = $_GET['date'] ?? null;
if ($clicked_date && sanitize_date($clicked_date)) {
    $cs = (int)strtotime($clicked_date);
    $ce = $cs + 86400;
    $q  = $db->sql_query_prepared("SELECT username FROM users WHERE lastactive BETWEEN ? AND ? ORDER BY username ASC", [$cs, $ce]);
    while ($row = $db->fetch_array($q)) {
        $active_usernames[] = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
    }
}

$total_users    = array_sum($data['counts']);
$avg_time_total = $days > 0 ? round(array_sum($data['activity']) / $days, 2) : 0;
$js_labels      = json_encode($data['labels'],            JSON_UNESCAPED_UNICODE);
$js_counts      = json_encode($data['counts'],            JSON_UNESCAPED_UNICODE);
$js_activity    = json_encode($data['activity'],          JSON_UNESCAPED_UNICODE);
$js_avg         = json_encode($data['avg_time_per_user'], JSON_UNESCAPED_UNICODE);
$h_start        = htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8');
$h_end          = htmlspecialchars($end_date,   ENT_QUOTES, 'UTF-8');

// Lang strings are plain text — always escape on output.
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// js_* keys → AGS_LANG (without the prefix)
$js_lang = [];
foreach ($lang->useractivity as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $js_lang[substr((string)$k, 3)] = (string)$v;
    }
}

stdhead(ags_fmt($lang->useractivity['page_title'], $h_start, $h_end));

?>
<div class="container my-4">

    <div class="card mb-4 shadow-sm">
        <div class="card-body d-flex justify-content-around flex-wrap gap-3">
            <div class="text-center">
                <div class="text-muted small"><?= $h($lang->useractivity['kpi_total_users']) ?></div>
                <div class="fs-4 fw-bold"><?= number_format($total_users) ?></div>
            </div>
            <div class="text-center">
                <div class="text-muted small"><?= $h($lang->useractivity['kpi_avg_time_day']) ?></div>
                <div class="fs-4 fw-bold"><?= $avg_time_total ?></div>
            </div>
            <div class="text-center">
                <div class="text-muted small"><?= $h($lang->useractivity['kpi_period']) ?></div>
                <div class="fs-4 fw-bold"><?= $h(ags_fmt($lang->useractivity['kpi_days'], $days)) ?></div>
            </div>
        </div>
    </div>

    <form method="GET" autocomplete="off" class="row g-3 align-items-center mb-4 flex-wrap">
        <input type="hidden" name="act" value="<?= htmlspecialchars($_GET['act'] ?? '') ?>">
        <div class="col-auto">
            <label for="start" class="col-form-label fw-semibold"><?= $h($lang->useractivity['lbl_from']) ?></label>
        </div>
        <div class="col-auto">
            <input type="date" id="start" name="start" class="form-control" value="<?= $h_start ?>">
        </div>
        <div class="col-auto">
            <label for="end" class="col-form-label fw-semibold"><?= $h($lang->useractivity['lbl_to']) ?></label>
        </div>
        <div class="col-auto">
            <input type="date" id="end" name="end" class="form-control" value="<?= $h_end ?>">
        </div>
        <div class="col-auto">
            <select id="datePreset" class="form-select" aria-label="<?= $h($lang->useractivity['aria_date_preset']) ?>">
                <option value=""><?= $h($lang->useractivity['opt_preset']) ?></option>
                <option value="7"><?= $h($lang->useractivity['opt_last_7']) ?></option>
                <option value="30"><?= $h($lang->useractivity['opt_last_30']) ?></option>
                <option value="this_month"><?= $h($lang->useractivity['opt_this_month']) ?></option>
                <option value="last_month"><?= $h($lang->useractivity['opt_last_month']) ?></option>
            </select>
        </div>
        <div class="col-auto d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-chart-line me-1"></i><?= $h($lang->useractivity['btn_show']) ?>
            </button>
            <a href="<?= $_this_script_ ?>" class="btn btn-outline-secondary">
                <i class="fas fa-undo me-1"></i><?= $h($lang->useractivity['btn_reset']) ?>
            </a>
        </div>
    </form>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <?= $h(ags_fmt($lang->useractivity['sec_chart'], $start_date, $end_date)) ?>
        </div>
        <div class="card-body position-relative">
            <div id="loadingSpinner" class="position-absolute top-50 start-50 translate-middle d-none" style="z-index:10;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden"><?= $h($lang->useractivity['msg_loading']) ?></span>
                </div>
            </div>
            <div id="userChart" style="width:100%; height:400px;"></div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button id="toggleUsersCount" class="btn btn-sm btn-outline-warning"><?= $h($lang->useractivity['btn_toggle_users']) ?></button>
            <button id="toggleTotalTime"  class="btn btn-sm btn-outline-primary"><?= $h($lang->useractivity['btn_toggle_time']) ?></button>
            <button id="toggleAvgTime"    class="btn btn-sm btn-outline-info"><?= $h($lang->useractivity['btn_toggle_avg']) ?></button>
            <button id="toggleChartType"  class="btn btn-sm btn-outline-secondary"><?= $h($lang->useractivity['btn_chart_type']) ?></button>
            <button id="refreshChart"     class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync-alt me-1"></i><?= $h($lang->useractivity['btn_refresh']) ?>
            </button>
            <button id="exportCsv"    class="btn btn-sm btn-outline-success ms-auto">
                <i class="fas fa-file-csv me-1"></i><?= $h($lang->useractivity['btn_csv']) ?>
            </button>
            <button id="exportPng"    class="btn btn-sm btn-outline-info">
                <i class="fas fa-image me-1"></i><?= $h($lang->useractivity['btn_png']) ?>
            </button>
            <button id="downloadJson" class="btn btn-sm btn-outline-dark">
                <i class="fas fa-code me-1"></i><?= $h($lang->useractivity['btn_json']) ?>
            </button>
        </div>
    </div>

    <?php if ($clicked_date): ?>
    <div class="card shadow-sm">
        <div class="card-header fw-semibold">
            <?= $h(ags_fmt($lang->useractivity['sec_users_on_date'], (string)$clicked_date)) ?>
            <span class="badge bg-primary ms-2"><?= count($active_usernames) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if ($active_usernames): ?>
            <ul class="list-group list-group-flush" style="max-height:260px;overflow-y:auto;">
                <?php foreach ($active_usernames as $user): ?>
                <li class="list-group-item py-1"><?= $user ?></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted fst-italic p-3 mb-0"><?= $h($lang->useractivity['msg_no_users']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/highcharts.js"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/scripts/exporting.js"></script>
<script>
const AGS_LANG = <?= json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script>
(function () {
    'use strict';

    // t(key, fallback, ...args) — AGS_LANG string or English fallback, {1}/%1$s substitution
    var L = (typeof AGS_LANG === 'object' && AGS_LANG !== null) ? AGS_LANG : {};
    function t(key, fallback) {
        var s    = (typeof L[key] === 'string' && L[key] !== '') ? L[key] : fallback;
        var args = Array.prototype.slice.call(arguments, 2);
        return String(s).replace(/\{(\d+)\}|%(\d+)\$s/g, function (m, a, b) {
            var i = parseInt(a || b, 10) - 1;
            return i < args.length ? String(args[i]) : m;
        });
    }

    var labels   = <?= $js_labels ?>;
    var counts   = <?= $js_counts ?>;
    var activity = <?= $js_activity ?>;
    var avgTime  = <?= $js_avg ?>;

    var sfxUsers = ' ' + t('suffix_users', 'users');
    var sfxHrs   = ' ' + t('suffix_hrs', 'hrs');

    var userChart = Highcharts.chart('userChart', {
        chart: {
            backgroundColor: 'transparent',
            animation: { duration: 800 }
        },
        title: { text: null },
        credits: { enabled: false },
        exporting: { enabled: false }, // экспорт через свои кнопки ниже, встроенное меню не нужно
        xAxis: {
            categories: labels
        },
        yAxis: [
            {
                // yLeft - время (часы)
                title: { text: t('axis_time', 'Time (hrs)') },
                min: 0,
                allowDecimals: false
            },
            {
                // yRight - количество пользователей
                title: { text: t('axis_users', 'Users Count') },
                min: 0,
                allowDecimals: false,
                opposite: true,
                gridLineWidth: 0
            }
        ],
        tooltip: {
            shared: true,
            valueSuffix: ''
        },
        legend: { enabled: true, verticalAlign: 'top' },
        plotOptions: {
            series: {
                point: {
                    events: {
                        click: function () {
                            var p = new URLSearchParams(window.location.search);
                            p.set('date', labels[this.index]);
                            window.location.search = p.toString();
                        }
                    }
                }
            }
        },
        series: [
            {
                name: t('series_users', 'Users Count'),
                type: 'column',
                data: counts,
                yAxis: 1,
                color: 'rgba(255,159,64,0.7)',
                tooltip: { valueSuffix: sfxUsers }
            },
            {
                name: t('series_total_time', 'Total Time Online (hrs)'),
                type: 'line',
                data: activity,
                yAxis: 0,
                color: 'rgba(54,162,235,1)',
                marker: { radius: 4 },
                tooltip: { valueSuffix: sfxHrs }
            },
            {
                name: t('series_avg_time', 'Avg Time per User (hrs)'),
                type: 'line',
                data: avgTime,
                yAxis: 0,
                color: 'rgba(75,192,192,1)',
                marker: { radius: 3 },
                fillOpacity: 0.15,
                tooltip: { valueSuffix: sfxHrs }
            }
        ]
    });

    function toggleDataset(i) {
        var s = userChart.series[i];
        s.setVisible(!s.visible);
    }

    document.getElementById('toggleUsersCount').addEventListener('click', function () { toggleDataset(0); });
    document.getElementById('toggleTotalTime').addEventListener('click',  function () { toggleDataset(1); });
    document.getElementById('toggleAvgTime').addEventListener('click',    function () { toggleDataset(2); });

    var lineOnly = false;
    document.getElementById('toggleChartType').addEventListener('click', function () {
        lineOnly = !lineOnly;
        userChart.series[0].update({
            type: lineOnly ? 'line' : 'column',
            color: lineOnly ? 'rgba(255,159,64,0.4)' : 'rgba(255,159,64,0.7)'
        });
    });

    document.getElementById('refreshChart').addEventListener('click', function () {
        var s = document.getElementById('loadingSpinner');
        s.classList.remove('d-none');
        setTimeout(function () { userChart.redraw(); s.classList.add('d-none'); }, 400);
    });

    function downloadBlob(content, filename, type) {
        var url = URL.createObjectURL(new Blob([content], { type: type }));
        var a   = Object.assign(document.createElement('a'), { href: url, download: filename });
        a.click();
        URL.revokeObjectURL(url);
    }

    // CSV-поле: в кавычки, если есть запятая/кавычка/перевод строки
    function csvCell(v) {
        var s = String(v);
        return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }

    document.getElementById('exportCsv').addEventListener('click', function () {
        // BOM — чтобы Excel открыл кириллицу в заголовках как UTF-8
        var csv = '﻿' + [
            t('csv_date', 'Date'),
            t('csv_users', 'Users'),
            t('csv_total_time', 'Total Time (hrs)'),
            t('csv_avg_time', 'Avg Time (hrs)')
        ].map(csvCell).join(',') + '\n';
        labels.forEach(function (l, i) { csv += l + ',' + counts[i] + ',' + activity[i] + ',' + avgTime[i] + '\n'; });
        downloadBlob(csv, 'active_users.csv', 'text/csv;charset=utf-8');
    });

    document.getElementById('exportPng').addEventListener('click', function () {
        // exportChartLocal() - конвертация SVG->PNG прямо в браузере,
        // без похода на сервер экспорта Highcharts (нужен только модуль
        // exporting.js, уже подключён выше).
        userChart.exportChartLocal({ type: 'image/png', filename: 'active_users' });
    });

    document.getElementById('downloadJson').addEventListener('click', function () {
        downloadBlob(JSON.stringify({ labels: labels, counts: counts, activity: activity, avgTime: avgTime }, null, 2), 'active_users.json', 'application/json');
    });

    document.getElementById('datePreset').addEventListener('change', function () {
        var now = new Date(), start, end = new Date(now);
        switch (this.value) {
            case '7':          start = new Date(now); start.setDate(now.getDate() - 6); break;
            case '30':         start = new Date(now); start.setDate(now.getDate() - 29); break;
            case 'this_month': start = new Date(now.getFullYear(), now.getMonth(), 1); end = new Date(now.getFullYear(), now.getMonth() + 1, 0); break;
            case 'last_month': start = new Date(now.getFullYear(), now.getMonth() - 1, 1); end = new Date(now.getFullYear(), now.getMonth(), 0); break;
            default: return;
        }
        var fmt = function (d) { return d.toISOString().slice(0, 10); };
        document.getElementById('start').value = fmt(start);
        document.getElementById('end').value   = fmt(end);
    });

}());
</script>

<?php stdfoot(); ?>
