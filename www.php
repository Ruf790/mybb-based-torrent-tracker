<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('SCRIPTNAME', 'www.php');

require_once 'global.php';
require_once INC_PATH . '/functions_mkprettytime.php';

if (!isset($CURUSER)) {
    stderr('You must be logged in to view statistics.');
}

if (session_status() === PHP_SESSION_NONE) session_start();

$is_mod = is_mod($usergroups);
$action = $mybb->get_input('action');

// ── Flash helper ──────────────────────────────────────────────────────────────
function show_flash(): void {
    global $BASEURL;
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $type = match($f['type']) {
            'success' => 'success',
            'danger'  => 'error',
            'warning' => 'warning',
            default   => 'info',
        };
        echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){ showToast(' . json_encode($f['msg']) . ',' . json_encode($type) . '); });</script>';
        unset($_SESSION['flash']);
    }
}

// ── Load categories ───────────────────────────────────────────────────────────
$cats = [];
$q = $db->sql_query("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[$r['id']] = $r['name'];

// ── Статистика ─────────────────────────────────────────────────────────────────

// 1. Общая статистика
$total_requests = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM requests"), 'COUNT(*)');
$total_offers = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM offers"), 'COUNT(*)');
$total_votes = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM request_votes"), 'COUNT(*)');
$total_wants = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM offer_votes"), 'COUNT(*)');
$total_comments = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM request_comments"), 'COUNT(*)') + 
                  (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM offer_comments"), 'COUNT(*)');

// 2. Статусы запросов
$req_statuses = [];
$q = $db->sql_query("SELECT status, COUNT(*) as cnt FROM requests GROUP BY status");
while ($r = $db->fetch_array($q)) {
    $req_statuses[$r['status']] = (int)$r['cnt'];
}

// 3. Статусы предложений
$offer_statuses = [];
$q = $db->sql_query("SELECT status, COUNT(*) as cnt FROM offers GROUP BY status");
while ($r = $db->fetch_array($q)) {
    $offer_statuses[$r['status']] = (int)$r['cnt'];
}

// 4. Запросы по категориям (топ 10)
$requests_by_category = [];
$q = $db->sql_query("
    SELECT category_id, COUNT(*) as cnt 
    FROM requests 
    WHERE category_id > 0
    GROUP BY category_id 
    ORDER BY cnt DESC 
    LIMIT 10
");
while ($r = $db->fetch_array($q)) {
    $cat_name = $cats[$r['category_id']] ?? 'Unknown';
    $requests_by_category[] = [
        'category' => $cat_name,
        'count' => (int)$r['cnt']
    ];
}

// 5. Предложения по категориям (топ 10)
$offers_by_category = [];
$q = $db->sql_query("
    SELECT category_id, COUNT(*) as cnt 
    FROM offers 
    WHERE category_id > 0
    GROUP BY category_id 
    ORDER BY cnt DESC 
    LIMIT 10
");
while ($r = $db->fetch_array($q)) {
    $cat_name = $cats[$r['category_id']] ?? 'Unknown';
    $offers_by_category[] = [
        'category' => $cat_name,
        'count' => (int)$r['cnt']
    ];
}

// 6. Топ пользователей по созданным запросам
$top_requesters = [];
$q = $db->sql_query("
    SELECT u.id, u.username, COUNT(r.id) as cnt
    FROM users u
    INNER JOIN requests r ON r.user_id = u.id
    GROUP BY u.id, u.username
    ORDER BY cnt DESC
    LIMIT 10
");
while ($r = $db->fetch_array($q)) {
    $top_requesters[] = [
        'user_id' => (int)$r['id'],
        'username' => $r['username'],
        'count' => (int)$r['cnt']
    ];
}

// 7. Топ пользователей по полученным голосам за запросы
$top_voted = [];
$q = $db->sql_query("
    SELECT u.id, u.username, SUM(r.votes) as total_votes
    FROM users u
    INNER JOIN requests r ON r.user_id = u.id
    GROUP BY u.id, u.username
    ORDER BY total_votes DESC
    LIMIT 10
");
while ($r = $db->fetch_array($q)) {
    $top_voted[] = [
        'user_id' => (int)$r['id'],
        'username' => $r['username'],
        'total_votes' => (int)$r['total_votes']
    ];
}

// 8. Топ пользователей по созданным предложениям
$top_offers = [];
$q = $db->sql_query("
    SELECT u.id, u.username, COUNT(o.id) as cnt
    FROM users u
    INNER JOIN offers o ON o.user_id = u.id
    GROUP BY u.id, u.username
    ORDER BY cnt DESC
    LIMIT 10
");
while ($r = $db->fetch_array($q)) {
    $top_offers[] = [
        'user_id' => (int)$r['id'],
        'username' => $r['username'],
        'count' => (int)$r['cnt']
    ];
}

// 9. Динамика создания запросов (последние 30 дней)
$requests_timeline = [];
$days = 30;
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = (int)$db->fetch_field(
        $db->sql_query("SELECT COUNT(*) FROM requests WHERE DATE(FROM_UNIXTIME(created_at)) = '$date'"),
        'COUNT(*)'
    );
    $requests_timeline[] = [
        'date' => $date,
        'count' => $count
    ];
}

// 10. Динамика создания предложений (последние 30 дней)
$offers_timeline = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = (int)$db->fetch_field(
        $db->sql_query("SELECT COUNT(*) FROM offers WHERE DATE(FROM_UNIXTIME(created_at)) = '$date'"),
        'COUNT(*)'
    );
    $offers_timeline[] = [
        'date' => $date,
        'count' => $count
    ];
}

// ── Формируем данные для Chart.js ────────────────────────────────────────────
$chart_data = [
    'requests_status' => [
        'labels' => array_keys($req_statuses),
        'values' => array_values($req_statuses),
        'colors' => ['#198754', '#0d6efd', '#6c757d']
    ],
    'offers_status' => [
        'labels' => array_keys($offer_statuses),
        'values' => array_values($offer_statuses),
        'colors' => ['#198754', '#0d6efd', '#6c757d']
    ],
    'requests_by_category' => [
        'labels' => array_column($requests_by_category, 'category'),
        'values' => array_column($requests_by_category, 'count')
    ],
    'offers_by_category' => [
        'labels' => array_column($offers_by_category, 'category'),
        'values' => array_column($offers_by_category, 'count')
    ],
    'top_requesters' => [
        'labels' => array_column($top_requesters, 'username'),
        'values' => array_column($top_requesters, 'count')
    ],
    'top_voted' => [
        'labels' => array_column($top_voted, 'username'),
        'values' => array_column($top_voted, 'total_votes')
    ],
    'top_offers' => [
        'labels' => array_column($top_offers, 'username'),
        'values' => array_column($top_offers, 'count')
    ],
    'requests_timeline' => [
        'labels' => array_column($requests_timeline, 'date'),
        'values' => array_column($requests_timeline, 'count')
    ],
    'offers_timeline' => [
        'labels' => array_column($offers_timeline, 'date'),
        'values' => array_column($offers_timeline, 'count')
    ]
];

stdhead('Statistics Dashboard');
show_flash();
?>
<style>
    /* ============================================================
       Стили для страницы статистики
       ============================================================ */
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: var(--shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }
    .stat-card .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    .stat-card .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.875rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }
    .stat-card .stat-change {
        font-size: 0.75rem;
        margin-top: 0.5rem;
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 50rem;
    }
    .stat-change.positive {
        background: rgba(25, 135, 84, 0.15);
        color: #198754;
    }
    .stat-change.negative {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }

    .chart-container {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow);
        height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .chart-container:hover {
        box-shadow: var(--shadow-lg);
    }
    .chart-container .chart-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text-primary);
    }
    .chart-container canvas {
        max-height: 300px;
        width: 100% !important;
    }

    /* Темная тема для графиков */
    [data-bs-theme="dark"] .stat-card {
        background: var(--bg-card);
    }
    [data-bs-theme="dark"] .chart-container {
        background: var(--bg-card);
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.10s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.20s; }
    .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
    .animate-fade-in-up:nth-child(6) { animation-delay: 0.30s; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .stat-card .stat-number {
        animation: pulse 2s ease-in-out infinite;
    }

    /* Scrollbar для темной темы */
    [data-bs-theme="dark"] ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-track {
        background: var(--bg-body);
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
        background: #30363d;
        border-radius: 5px;
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb:hover {
        background: #484f58;
    }

    .badge {
        font-weight: 500;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .stat-card .stat-number {
            font-size: 1.8rem;
        }
        .chart-container canvas {
            max-height: 200px;
        }
    }

    @media (max-width: 480px) {
        .stat-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4 animate-fade-in-up">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php"><i class="fas fa-home"></i></a></li>
            <li class="breadcrumb-item active"><i class="fas fa-chart-bar me-1"></i>Statistics Dashboard</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 animate-fade-in-up">
        <div>
            <h4 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Statistics Dashboard</h4>
            <small class="text-muted">Overview of requests and offers activity</small>
        </div>
        <div>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stat-grid animate-fade-in-up">
        <div class="stat-card">
            <span class="stat-icon text-primary"><i class="fas fa-list-alt"></i></span>
            <div class="stat-number text-primary"><?= number_format($total_requests) ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon text-success"><i class="fas fa-gift"></i></span>
            <div class="stat-number text-success"><?= number_format($total_offers) ?></div>
            <div class="stat-label">Total Offers</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon text-warning"><i class="fas fa-thumbs-up"></i></span>
            <div class="stat-number text-warning"><?= number_format($total_votes) ?></div>
            <div class="stat-label">Total Votes</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon text-info"><i class="fas fa-hand-paper"></i></span>
            <div class="stat-number text-info"><?= number_format($total_wants) ?></div>
            <div class="stat-label">Total Wants</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon text-secondary"><i class="fas fa-comments"></i></span>
            <div class="stat-number text-secondary"><?= number_format($total_comments) ?></div>
            <div class="stat-label">Total Comments</div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-circle text-success me-1"></i> Requests by Status</div>
                <canvas id="requestsStatusChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-circle text-primary me-1"></i> Offers by Status</div>
                <canvas id="offersStatusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-tag me-1"></i> Requests by Category (Top 10)</div>
                <canvas id="requestsCategoryChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-tag me-1"></i> Offers by Category (Top 10)</div>
                <canvas id="offersCategoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-user me-1"></i> Top Requesters</div>
                <canvas id="topRequestersChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-star me-1"></i> Top Voted Users</div>
                <canvas id="topVotedChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 4 -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-user me-1"></i> Top Offerers</div>
                <canvas id="topOffersChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 animate-fade-in-up">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-calendar-alt me-1"></i> Requests Timeline (Last 30 Days)</div>
                <canvas id="requestsTimelineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 5 -->
    <div class="row g-4 animate-fade-in-up">
        <div class="col-md-12">
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-calendar-alt me-1"></i> Offers Timeline (Last 30 Days)</div>
                <canvas id="offersTimelineChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Определяем цвета для темной/светлой темы
    function getThemeColors() {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            text: isDark ? '#e6edf3' : '#212529',
            grid: isDark ? '#30363d' : '#dee2e6',
            border: isDark ? '#30363d' : '#dee2e6'
        };
    }

    // Обновление цвета текста при смене темы
    function updateChartColors(chart) {
        const colors = getThemeColors();
        if (chart.options.scales) {
            if (chart.options.scales.x) {
                chart.options.scales.x.ticks.color = colors.text;
                chart.options.scales.x.grid.color = colors.grid;
            }
            if (chart.options.scales.y) {
                chart.options.scales.y.ticks.color = colors.text;
                chart.options.scales.y.grid.color = colors.grid;
            }
        }
        chart.update();
    }

    // Опции по умолчанию для всех графиков
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: {
                    color: getThemeColors().text,
                    font: { size: 11 }
                }
            }
        }
    };

    // ============================================================
    // 1. Requests by Status (Pie Chart)
    // ============================================================
    const ctx1 = document.getElementById('requestsStatusChart').getContext('2d');
    const requestsStatusChart = new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: <?= json_encode($chart_data['requests_status']['labels']) ?>,
            datasets: [{
                data: <?= json_encode($chart_data['requests_status']['values']) ?>,
                backgroundColor: <?= json_encode($chart_data['requests_status']['colors']) ?>,
                borderWidth: 2,
                borderColor: getThemeColors().border
            }]
        },
        options: {
            ...defaultOptions,
            plugins: {
                ...defaultOptions.plugins,
                legend: {
                    ...defaultOptions.plugins.legend,
                    position: 'bottom'
                }
            }
        }
    });

    // ============================================================
    // 2. Offers by Status (Pie Chart)
    // ============================================================
    const ctx2 = document.getElementById('offersStatusChart').getContext('2d');
    const offersStatusChart = new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: <?= json_encode($chart_data['offers_status']['labels']) ?>,
            datasets: [{
                data: <?= json_encode($chart_data['offers_status']['values']) ?>,
                backgroundColor: <?= json_encode($chart_data['offers_status']['colors']) ?>,
                borderWidth: 2,
                borderColor: getThemeColors().border
            }]
        },
        options: {
            ...defaultOptions,
            plugins: {
                ...defaultOptions.plugins,
                legend: {
                    ...defaultOptions.plugins.legend,
                    position: 'bottom'
                }
            }
        }
    });

    // ============================================================
    // 3. Requests by Category (Bar Chart)
    // ============================================================
    const ctx3 = document.getElementById('requestsCategoryChart').getContext('2d');
    const requestsCategoryChart = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['requests_by_category']['labels']) ?>,
            datasets: [{
                label: 'Requests',
                data: <?= json_encode($chart_data['requests_by_category']['values']) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.6)',
                borderColor: '#0d6efd',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...defaultOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: { display: false }
            }
        }
    });

    // ============================================================
    // 4. Offers by Category (Bar Chart)
    // ============================================================
    const ctx4 = document.getElementById('offersCategoryChart').getContext('2d');
    const offersCategoryChart = new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['offers_by_category']['labels']) ?>,
            datasets: [{
                label: 'Offers',
                data: <?= json_encode($chart_data['offers_by_category']['values']) ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.6)',
                borderColor: '#198754',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...defaultOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: { display: false }
            }
        }
    });

    // ============================================================
    // 5. Top Requesters (Horizontal Bar Chart)
    // ============================================================
    const ctx5 = document.getElementById('topRequestersChart').getContext('2d');
    const topRequestersChart = new Chart(ctx5, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['top_requesters']['labels']) ?>,
            datasets: [{
                label: 'Requests Created',
                data: <?= json_encode($chart_data['top_requesters']['values']) ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.6)',
                borderColor: '#ffc107',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...defaultOptions,
            indexAxis: 'y',
            scales: {
                y: {
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: { display: false }
            }
        }
    });

    // ============================================================
    // 6. Top Voted Users (Horizontal Bar Chart)
    // ============================================================
    const ctx6 = document.getElementById('topVotedChart').getContext('2d');
    const topVotedChart = new Chart(ctx6, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['top_voted']['labels']) ?>,
            datasets: [{
                label: 'Votes Received',
                data: <?= json_encode($chart_data['top_voted']['values']) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.6)',
                borderColor: '#dc3545',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...defaultOptions,
            indexAxis: 'y',
            scales: {
                y: {
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: { display: false }
            }
        }
    });

    // ============================================================
    // 7. Top Offerers (Horizontal Bar Chart)
    // ============================================================
    const ctx7 = document.getElementById('topOffersChart').getContext('2d');
    const topOffersChart = new Chart(ctx7, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_data['top_offers']['labels']) ?>,
            datasets: [{
                label: 'Offers Created',
                data: <?= json_encode($chart_data['top_offers']['values']) ?>,
                backgroundColor: 'rgba(13, 202, 240, 0.6)',
                borderColor: '#0dcaf0',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            ...defaultOptions,
            indexAxis: 'y',
            scales: {
                y: {
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: { display: false }
            }
        }
    });

    // ============================================================
    // 8. Requests Timeline (Line Chart)
    // ============================================================
    const ctx8 = document.getElementById('requestsTimelineChart').getContext('2d');
    const requestsTimelineChart = new Chart(ctx8, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_data['requests_timeline']['labels']) ?>,
            datasets: [{
                label: 'Requests',
                data: <?= json_encode($chart_data['requests_timeline']['values']) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                borderWidth: 2
            }]
        },
        options: {
            ...defaultOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    ticks: { 
                        color: getThemeColors().text,
                        maxTicksLimit: 15,
                        maxRotation: 45
                    },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: {
                    ...defaultOptions.plugins.legend,
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // ============================================================
    // 9. Offers Timeline (Line Chart)
    // ============================================================
    const ctx9 = document.getElementById('offersTimelineChart').getContext('2d');
    const offersTimelineChart = new Chart(ctx9, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_data['offers_timeline']['labels']) ?>,
            datasets: [{
                label: 'Offers',
                data: <?= json_encode($chart_data['offers_timeline']['values']) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                borderWidth: 2
            }]
        },
        options: {
            ...defaultOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: getThemeColors().text },
                    grid: { color: getThemeColors().grid }
                },
                x: {
                    ticks: { 
                        color: getThemeColors().text,
                        maxTicksLimit: 15,
                        maxRotation: 45
                    },
                    grid: { color: getThemeColors().grid }
                }
            },
            plugins: {
                ...defaultOptions.plugins,
                legend: {
                    ...defaultOptions.plugins.legend,
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // ============================================================
    // Обновление цветов при смене темы
    // ============================================================
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-bs-theme') {
                const colors = getThemeColors();
                
                // Обновляем все графики
                const charts = [
                    requestsStatusChart,
                    offersStatusChart,
                    requestsCategoryChart,
                    offersCategoryChart,
                    topRequestersChart,
                    topVotedChart,
                    topOffersChart,
                    requestsTimelineChart,
                    offersTimelineChart
                ];
                
                charts.forEach(chart => {
                    // Обновляем цвета текста
                    if (chart.options.scales) {
                        if (chart.options.scales.x) {
                            chart.options.scales.x.ticks.color = colors.text;
                            chart.options.scales.x.grid.color = colors.grid;
                        }
                        if (chart.options.scales.y) {
                            chart.options.scales.y.ticks.color = colors.text;
                            chart.options.scales.y.grid.color = colors.grid;
                        }
                    }
                    if (chart.options.plugins && chart.options.plugins.legend) {
                        chart.options.plugins.legend.labels.color = colors.text;
                    }
                    chart.update();
                });
            }
        });
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });

    // ============================================================
    // Адаптация для мобильных устройств
    // ============================================================
    function handleResize() {
        const isMobile = window.innerWidth < 768;
        const charts = [
            requestsStatusChart,
            offersStatusChart,
            requestsCategoryChart,
            offersCategoryChart,
            topRequestersChart,
            topVotedChart,
            topOffersChart,
            requestsTimelineChart,
            offersTimelineChart
        ];
        
        charts.forEach(chart => {
            chart.options.plugins.legend.labels.font.size = isMobile ? 9 : 11;
            chart.update();
        });
    }

    window.addEventListener('resize', handleResize);
    handleResize();
});
</script>

<?php
stdfoot();