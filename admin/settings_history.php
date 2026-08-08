<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('B_VERSION', '6.6.3');
define('THIS_SCRIPT', 'settings_history.php');

$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';

if ((int)($usergroups['cansettingspanel'] ?? 0) !== 1) {
    stdhead();
    error_no_permission(true);
    exit();
}

// ============================================================
//  ОБРАБОТКА POST ЗАПРОСОВ
// ============================================================
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cleanup':
            if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
                $cutoff = time() - (90 * 86400);
                $query = $db->sql_query_prepared("DELETE FROM sitelog WHERE category = 'settings' AND added < ?", [$cutoff]);
                $deleted = $db->affected_rows($query);
                flash_message("Deleted {$deleted} old logs", "success");
            }
            admin_redirect("settings_history.php");
            break;
            
        case 'export':
            $search = $_GET['search'] ?? '';
            $user = $_GET['user'] ?? '';
            $history = get_settings_history(10000, 0, $search, $user);
            $logs = $history['logs'] ?? [];
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="settings_history_' . date('Y-m-d_H-i') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date/Time', 'User ID', 'User', 'Event', 'Level', 'IP Address']);
            
            $level_names = ['Info', 'Warning', 'Error'];
            foreach ($logs as $log) {
                fputcsv($output, [
                    date('Y-m-d H:i:s', (int)$log['added']),
                    $log['uid'],
                    $log['username'] ?? 'System',
                    $log['txt'],
                    $level_names[(int)$log['level']] ?? 'Info',
                    $log['ipaddress'] ?? '0.0.0.0'
                ]);
            }
            fclose($output);
            exit;
            
        case 'export_json':
            $search = $_GET['search'] ?? '';
            $user = $_GET['user'] ?? '';
            $history = get_settings_history(10000, 0, $search, $user);
            $logs = $history['logs'] ?? [];
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="settings_history_' . date('Y-m-d_H-i') . '.json"');
            
            echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            break;
    }
}

// ============================================================
//  ФУНКЦИИ
// ============================================================
if (!function_exists('get_settings_history')) {
    function get_settings_history(int $limit = 50, int $offset = 0, ?string $search = null, ?string $user = null): array {
        global $db;
        
        $params = [];
        $where_conditions = ["category = 'settings'"];
        
        if ($search !== null && $search !== '') {
            $where_conditions[] = "txt LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        if ($user !== null && $user !== '') {
            $where_conditions[] = "txt LIKE ?";
            $params[] = '%' . $user . '%';
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $sql = "
            SELECT sl.*, u.username 
            FROM sitelog sl
            LEFT JOIN users u ON sl.uid = u.id
            {$where_clause}
            ORDER BY sl.added DESC 
            LIMIT ?, ?
        ";
        
        $query = $db->sql_query_prepared($sql, [...$params, $offset, $limit]);
        
        $logs = [];
        if ($query) {
            while ($row = $db->fetch_array($query)) {
                if (!empty($row['ipaddress'])) {
                    $ip = inet_ntop($row['ipaddress']);
                    $row['ipaddress'] = $ip !== false ? $ip : '0.0.0.0';
                } else {
                    $row['ipaddress'] = '0.0.0.0';
                }
                $logs[] = $row;
            }
        }
        
        $count_sql = "SELECT COUNT(*) as total FROM sitelog {$where_clause}";
        $count_query = $db->sql_query_prepared($count_sql, $params);
        
        $total = 0;
        if ($count_query && $db->num_rows($count_query) > 0) {
            $row = $db->fetch_array($count_query);
            $total = (int)$row['total'];
        }
        
        return [
            'logs' => $logs,
            'total' => $total
        ];
    }
}

// Получаем параметры фильтрации
$search = $_GET['search'] ?? '';
$user = $_GET['user'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Получаем историю
$history_data = get_settings_history($per_page, $offset, $search, $user);
$logs = $history_data['logs'] ?? [];
$total = $history_data['total'] ?? 0;

// ============================================================
//  СТАТИСТИКА ПО ЛОГАМ
// ============================================================
$log_stats = [
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'today' => 0,
    'this_week' => 0
];

$today_start = strtotime(date('Y-m-d 00:00:00'));
$week_start = strtotime(date('Y-m-d 00:00:00', strtotime('-7 days')));

foreach ($logs as $log) {
    $level = (int)$log['level'];
    if ($level === 0) $log_stats['info']++;
    elseif ($level === 1) $log_stats['warning']++;
    elseif ($level === 2) $log_stats['error']++;
    
    if ($log['added'] >= $today_start) $log_stats['today']++;
    if ($log['added'] >= $week_start) $log_stats['this_week']++;
}

// Создаем пагинацию
$page_url = 'settings_history.php?' . http_build_query(array_filter([
    'search' => $search,
    'user' => $user
]));
$multipage = multipage($total, $per_page, $page, $page_url);

stdhead('Settings History');
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/settings.css">

<style>
.history-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0 20px;
    border-bottom: 2px solid var(--bs-border-color);
    margin-bottom: 24px;
}

.history-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.history-header h1 small {
    font-size: 0.9rem;
    font-weight: 400;
    color: var(--bs-secondary-color);
}

.history-header .badge {
    font-size: 0.85rem;
    padding: 6px 14px;
}

.history-header .btn {
    font-size: 0.85rem;
    padding: 6px 16px;
}

.history-filters {
    background: var(--bs-card-bg);
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 24px;
    border: 1px solid var(--bs-border-color);
}

.history-filters .filter-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.history-filters .filter-row .filter-group {
    flex: 1;
    min-width: 150px;
}

.history-filters .form-control {
    font-size: 0.95rem;
    padding: 8px 14px;
}

.history-filters .btn {
    font-size: 0.9rem;
    padding: 8px 18px;
}

/* ============================================================
   СТАТИСТИКА - УВЕЛИЧЕННЫЙ ШРИФТ
============================================================ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-box {
    background: var(--bs-card-bg);
    border-radius: 12px;
    padding: 14px 18px;
    border: 1px solid var(--bs-border-color);
    text-align: center;
    transition: all 0.2s ease;
}

.stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.stat-box .stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-box .stat-label {
    font-size: 0.8rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-box.info .stat-number { color: #0d6efd; }
.stat-box.warning .stat-number { color: #ffc107; }
.stat-box.error .stat-number { color: #dc3545; }
.stat-box.today .stat-number { color: #198754; }
.stat-box.week .stat-number { color: #6f42c1; }

/* ============================================================
   ТАБЛИЦА - УВЕЛИЧЕННЫЙ ШРИФТ
============================================================ */
.history-table-wrapper {
    background: var(--bs-card-bg);
    border-radius: 16px;
    border: 1px solid var(--bs-border-color);
    overflow: hidden;
}

.history-table {
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 0;
}

.history-table thead th {
    background: var(--bs-tertiary-bg);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 18px;
    border-bottom: 2px solid var(--bs-border-color);
    white-space: nowrap;
}

.history-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    font-size: 0.95rem;
}

/* ============================================================
   ЛОГ СООБЩЕНИЕ - УВЕЛИЧЕННЫЙ ШРИФТ
============================================================ */
.history-table .log-message {
    font-family: 'Courier New', monospace;
    font-size: 0.95rem;
    display: block;
    max-width: 600px;
    word-wrap: break-word;
    white-space: normal;
    line-height: 1.5;
    padding: 4px 0;
    max-height: 70px;
    overflow: hidden;
    position: relative;
    transition: max-height 0.3s ease;
}

.history-table .log-message.expanded {
    max-height: none;
    overflow: visible;
}

.history-table .log-message .log-text {
    display: block;
}

.history-table .log-message .log-toggle {
    cursor: pointer;
    color: var(--bs-primary);
    font-size: 0.8rem;
    background: none;
    border: none;
    padding: 3px 8px;
    text-decoration: underline;
    display: inline-block;
    margin-top: 4px;
}

.history-table .log-message .log-toggle:hover {
    color: var(--bs-primary-hover);
}

.history-table .log-level {
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    white-space: nowrap;
}

.history-table .log-level.level-0 {
    background: #cfe2ff;
    color: #084298;
}

.history-table .log-level.level-1 {
    background: #fff3cd;
    color: #664d03;
}

.history-table .log-level.level-2 {
    background: #f8d7da;
    color: #842029;
}

.history-table .user-link {
    color: var(--bs-body-color);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
}

.history-table .user-link:hover {
    color: var(--bs-primary);
}

.history-table .ip-address {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: var(--bs-secondary-color);
}

.history-table .btn-group .btn {
    font-size: 0.8rem;
    padding: 4px 8px;
}

.history-empty {
    text-align: center;
    padding: 60px 20px;
}

.history-empty i {
    font-size: 4.5rem;
    opacity: 0.3;
    margin-bottom: 20px;
    color: var(--bs-secondary-color);
}

.history-empty h4 {
    font-size: 1.3rem;
    color: var(--bs-secondary-color);
}

.history-empty p {
    font-size: 1rem;
}

.history-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    align-items: center;
}

.history-actions .btn {
    font-size: 0.85rem;
    padding: 6px 16px;
}

@keyframes highlightNew {
    0% { background: rgba(13, 110, 253, 0.12); }
    100% { background: transparent; }
}

.history-table tbody tr.new-row {
    animation: highlightNew 2s ease;
}

/* ============================================================
   ТЕМНАЯ ТЕМА
============================================================ */
[data-theme="dark"] .history-table thead th {
    background: #1a1a2e;
    border-color: #2d2d3d;
}

[data-theme="dark"] .history-table .log-level.level-0 {
    background: rgba(13, 110, 253, 0.2);
    color: #6ea8fe;
}

[data-theme="dark"] .history-table .log-level.level-1 {
    background: rgba(255, 193, 7, 0.2);
    color: #ffda6a;
}

[data-theme="dark"] .history-table .log-level.level-2 {
    background: rgba(220, 53, 69, 0.2);
    color: #ea868f;
}

[data-theme="dark"] .stat-box {
    border-color: #2d2d3d;
}

/* ============================================================
   ПАГИНАЦИЯ - УВЕЛИЧЕННЫЙ ШРИФТ
============================================================ */
.pagination {
    font-size: 0.95rem;
}

.pagination .page-link {
    padding: 8px 16px;
    font-size: 0.95rem;
}

/* ============================================================
   АДАПТИВ
============================================================ */
@media (max-width: 768px) {
    .history-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .history-header h1 {
        font-size: 1.4rem;
    }
    
    .history-filters .filter-row {
        flex-direction: column;
    }
    
    .history-filters .filter-row .filter-group {
        min-width: unset;
        width: 100%;
    }
    
    .history-table .log-message {
        max-width: 200px;
        font-size: 0.85rem;
    }
    
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
    
    .stat-box {
        padding: 10px 12px;
    }
    
    .stat-box .stat-number {
        font-size: 1.4rem;
    }
    
    .stat-box .stat-label {
        font-size: 0.7rem;
    }
    
    .history-table tbody td {
        font-size: 0.85rem;
        padding: 8px 10px;
    }
    
    .history-table thead th {
        font-size: 0.75rem;
        padding: 10px 12px;
    }
}

@media (max-width: 576px) {
    .history-table .log-message {
        max-width: 120px;
        font-size: 0.75rem;
    }
    
    .history-table tbody td {
        font-size: 0.8rem;
        padding: 6px 8px;
    }
    
    .history-table thead th {
        font-size: 0.65rem;
        padding: 6px 8px;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .history-actions .btn {
        font-size: 0.75rem;
        padding: 4px 10px;
    }
    
    .history-header .btn {
        font-size: 0.75rem;
        padding: 4px 10px;
    }
}
</style>

<div class="history-container">
    <div class="history-header">
        <h1>
            <i class="fas fa-history text-primary me-2"></i>
            Settings History
            <small>v<?= B_VERSION ?></small>
        </h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">Total: <?= number_format($total) ?></span>
            <a href="settings.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Settings
            </a>
        </div>
    </div>

    <!-- ============================================================
         СТАТИСТИКА
    ============================================================ -->
    <div class="stats-row">
        <div class="stat-box info">
            <div class="stat-number"><?= $log_stats['info'] ?></div>
            <div class="stat-label"><i class="fas fa-info-circle me-1"></i> Info</div>
        </div>
        <div class="stat-box warning">
            <div class="stat-number"><?= $log_stats['warning'] ?></div>
            <div class="stat-label"><i class="fas fa-exclamation-triangle me-1"></i> Warnings</div>
        </div>
        <div class="stat-box error">
            <div class="stat-number"><?= $log_stats['error'] ?></div>
            <div class="stat-label"><i class="fas fa-times-circle me-1"></i> Errors</div>
        </div>
        <div class="stat-box today">
            <div class="stat-number"><?= $log_stats['today'] ?></div>
            <div class="stat-label"><i class="fas fa-calendar-day me-1"></i> Today</div>
        </div>
        <div class="stat-box week">
            <div class="stat-number"><?= $log_stats['this_week'] ?></div>
            <div class="stat-label"><i class="fas fa-calendar-week me-1"></i> This Week</div>
        </div>
    </div>

    <!-- ============================================================
         ФИЛЬТРЫ
    ============================================================ -->
    <div class="history-filters">
        <form method="get" action="settings_history.php">
            <div class="filter-row">
                <div class="filter-group">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search in logs..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="user" 
                               placeholder="Search by user..." 
                               value="<?= htmlspecialchars($user) ?>">
                    </div>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="settings_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================================
         ТАБЛИЦА
    ============================================================ -->
    <div class="history-table-wrapper">
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="table history-table">
                <thead>
                    <tr>
                        <th style="width: 12%;"><i class="fas fa-clock me-1"></i> Date/Time</th>
                        <th style="width: 10%;"><i class="fas fa-user me-1"></i> User</th>
                        <th style="width: 48%;"><i class="fas fa-info-circle me-1"></i> Event</th>
                        <th style="width: 8%;"><i class="fas fa-flag me-1"></i> Level</th>
                        <th style="width: 10%;"><i class="fas fa-network-wired me-1"></i> IP</th>
                        <th style="width: 12%;" class="text-center"><i class="fas fa-cog me-1"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php 
                    $is_new = (time() - (int)$log['added']) < 300;
                    $level_names = ['Info', 'Warning', 'Error'];
                    $level = (int)$log['level'];
                    $log_text = htmlspecialchars($log['txt']);
                    $is_long = strlen($log['txt']) > 100;
                    ?>
                    <tr class="<?= $is_new ? 'new-row' : '' ?>">
                        <td style="white-space: nowrap;">
                            <span title="<?= date('Y-m-d H:i:s', (int)$log['added']) ?>">
                                <?= my_datee('relative', $log['added']) ?>
                            </span>
                            <?php if ($is_new): ?>
                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;">NEW</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['uid'] > 0): ?>
                            <a href="<?= get_profile_link($log['uid']) ?>" class="user-link">
                                <?= htmlspecialchars($log['username'] ?? 'Unknown') ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="log-message <?= $is_long ? 'collapsed' : 'expanded' ?>" data-full="<?= $log_text ?>">
                                <span class="log-text"><?= $log_text ?></span>
                                <?php if ($is_long): ?>
                                    <button class="log-toggle" onclick="toggleLog(this)">Show more</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="log-level level-<?= $level ?>">
                                <?= $level_names[$level] ?? 'Info' ?>
                            </span>
                        </td>
                        <td>
                            <code class="ip-address"><?= htmlspecialchars($log['ipaddress'] ?? '0.0.0.0') ?></code>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ($log['uid'] > 0): ?>
                                <a href="<?= get_profile_link($log['uid']) ?>" class="btn btn-outline-secondary" title="View user profile">
                                    <i class="fas fa-user"></i>
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-outline-info" onclick="copyLog(this)" title="Copy log">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="history-empty">
            <i class="fas fa-history text-muted"></i>
            <h4 class="text-muted">No history found</h4>
            <p class="text-muted">Settings changes will appear here as they happen</p>
            <a href="settings.php" class="btn btn-primary">
                <i class="fas fa-cog me-1"></i> Go to Settings
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($multipage): ?>
    <div class="mt-3">
        <?= $multipage ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         ДЕЙСТВИЯ
    ============================================================ -->
    <div class="history-actions">
        <button class="btn btn-outline-secondary btn-sm" id="autoRefreshToggle" onclick="toggleAutoRefresh()">
            <i class="fas fa-sync-alt me-1"></i> Auto-refresh <span id="autoRefreshStatus">ON</span>
        </button>
        
        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="cleanup">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('⚠️ Are you sure you want to delete settings logs older than 90 days?\n\nThis action cannot be undone!')">
                <i class="fas fa-trash me-1"></i> Cleanup Old Logs (90+ days)
            </button>
        </form>
        
        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </button>
        </form>
        
        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="export_json">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-code me-1"></i> Export JSON
            </button>
        </form>
        
        <a href="settings_history.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </a>
    </div>
</div>

<script>
// ============================================================
//  РАЗВЕРНУТЬ/СВЕРНУТЬ ЛОГ
// ============================================================
function toggleLog(btn) {
    const container = btn.closest('.log-message');
    if (container.classList.contains('collapsed')) {
        container.classList.remove('collapsed');
        container.classList.add('expanded');
        btn.textContent = 'Show less';
    } else {
        container.classList.add('collapsed');
        container.classList.remove('expanded');
        btn.textContent = 'Show more';
    }
}

// ============================================================
//  КОПИРОВАТЬ ЛОГ
// ============================================================
function copyLog(btn) {
    const row = btn.closest('tr');
    const logText = row.querySelector('.log-message .log-text')?.textContent || row.querySelector('.log-message')?.textContent;
    if (logText) {
        navigator.clipboard?.writeText(logText).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => btn.innerHTML = original, 2000);
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = logText;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => btn.innerHTML = original, 2000);
        });
    }
}

// ============================================================
//  АВТООБНОВЛЕНИЕ
// ============================================================
let autoRefresh = true;
let refreshInterval = setInterval(() => {
    if (autoRefresh && !document.hidden) {
        location.reload();
    }
}, 60000);

function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    const status = document.getElementById('autoRefreshStatus');
    status.textContent = autoRefresh ? 'ON' : 'OFF';
    status.style.color = autoRefresh ? '#198754' : '#dc3545';
    
    if (autoRefresh) {
        refreshInterval = setInterval(() => {
            if (!document.hidden) location.reload();
        }, 60000);
    } else {
        clearInterval(refreshInterval);
    }
}

// Остановка автообновления при взаимодействии
document.addEventListener('click', () => {
    if (autoRefresh) {
        clearInterval(refreshInterval);
        refreshInterval = setInterval(() => {
            if (!document.hidden) location.reload();
        }, 60000);
    }
});

// ============================================================
//  ПОДСВЕТКА НОВЫХ ЗАПИСЕЙ
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.log-message').forEach(function(el) {
        const text = el.querySelector('.log-text');
        if (text && text.textContent.length > 100) {
            el.classList.add('collapsed');
        }
    });
});
</script>

<?php stdfoot(); ?>