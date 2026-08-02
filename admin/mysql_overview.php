<?php


declare(strict_types=1);


/**
 * Formats byte size into human readable format
 */
function formatByteSize(int $value, int $precision = 2): array
{
    static $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
    
    if ($value === 0) {
        return ['0', $units[0]];
    }

    $exponent = min((int)floor(log($value, 1024)), count($units) - 1);
    $formattedValue = round($value / pow(1024, $exponent), $precision);
    
    return [
        number_format($formattedValue, $precision, '.', ','),
        $units[$exponent]
    ];
}

/**
 * Generates HTML for overhead display
 */
function getOverheadDisplay(array $row, string $scriptUrl): string
{
    global $mybb;

    $dataFree = (int)($row['Data_free'] ?? 0);
    [$formattedSize, $unit] = formatByteSize($dataFree);
    
    if ($dataFree > 0) {
        $tableName = urlencode($row['Name'] ?? '');
        $link = htmlspecialchars($scriptUrl . '&Do=T&table=' . $tableName . '&my_post_key=' . $mybb->post_code);
        return sprintf(
            '<a href="%s" class="text-decoration-none text-danger fw-bold" title="Optimize Table">
                <i class="fas fa-tools me-1"></i>%s %s
            </a>',
            $link,
            $formattedSize,
            $unit
        );
    }
    
    return sprintf('<span class="text-success"><i class="fas fa-check-circle me-1"></i>%s %s</span>', $formattedSize, $unit);
}

/**
 * Formats table metadata
 */
function formatTableMetadata(array $row): array
{
    $rows = match (true) {
        isset($row['Rows']) && is_numeric($row['Rows']) && $row['Rows'] > 0 => 
            number_format((int)$row['Rows'], 0, '.', ','),
        default => 'N/A'
    };
    
    $formatDate = function(?string $timestamp): string {
        if (empty($timestamp)) {
            return 'N/A';
        }
        $time = strtotime($timestamp);
        return $time !== false ? date('Y-m-d H:i:s', $time) : 'N/A';
    };
    
    return [
        $rows,
        $formatDate($row['Create_time'] ?? ''),
        $formatDate($row['Update_time'] ?? ''),
        $formatDate($row['Check_time'] ?? '')
    ];
}

/**
 * Get real-time database server status
 */
function getDatabaseServerStatus(): array {
    global $db;
    
    $status = [];
    
    try {
        // Server version and uptime
        $result = $db->sql_query_prepared("SELECT VERSION() as version, @@version_comment as version_comment");
        if ($row = $db->fetch_array($result)) {
            $status['version'] = $row['version'] ?? 'Unknown';
            $status['version_comment'] = $row['version_comment'] ?? '';
        }
        
        // Uptime
        $result = $db->sql_query_prepared("SHOW STATUS LIKE 'Uptime'");
        if ($row = $db->fetch_array($result)) {
            $uptimeSeconds = (int)($row['Value'] ?? 0);
            $status['uptime_seconds'] = $uptimeSeconds;
            $status['uptime'] = gmdate("H:i:s", $uptimeSeconds);
        }
        
        // Connections
        $result = $db->sql_query_prepared("SHOW STATUS WHERE `variable_name` IN ('Threads_connected', 'Max_used_connections', 'Max_connections')");
        while ($row = $db->fetch_array($result)) {
            $status[$row['Variable_name']] = $row['Value'];
        }
        
        // Query statistics
        $queries = [
            'Questions' => 'total_queries',
            'Slow_queries' => 'slow_queries',
            'Select_scan' => 'table_scans',
            'Innodb_buffer_pool_reads' => 'buffer_reads',
            'Innodb_buffer_pool_read_requests' => 'buffer_requests'
        ];
        
        foreach ($queries as $key => $name) {
            $result = $db->sql_query_prepared("SHOW STATUS WHERE `variable_name` = ?", [$key]);
            if ($row = $db->fetch_array($result)) {
                $status[$name] = $row['Value'];
            }
        }
        
        // Calculate queries per second
        if (isset($status['total_queries']) && isset($status['uptime_seconds']) && $status['uptime_seconds'] > 0) {
            $status['queries_per_second'] = round($status['total_queries'] / $status['uptime_seconds'], 2);
        }
        
        // Active processes count
        $result = $db->sql_query_prepared("SHOW PROCESSLIST");
        $status['active_processes'] = $db->num_rows($result);
        $db->free_result($result);
        
        // Database size
        $database = $GLOBALS['config']['database']['database'] ?? '';
        $result = $db->sql_query_prepared("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = ?
        ", [$database]);
        if ($row = $db->fetch_array($result)) {
            $status['database_size_mb'] = $row['size_mb'] ?? 0;
        }
        
    } catch (Exception $e) {
        error_log("Database status error: " . $e->getMessage());
    }
    
    return $status;
}

/**
 * Get active database connections/processes
 */
function getActiveProcesses(): array {
    global $db;
    
    $processes = [];
    
    try {
        $result = $db->sql_query_prepared("
            SELECT 
                Id, User, Host, db, Command, Time, State, Info,
                CASE 
                    WHEN Command = 'Sleep' THEN 'idle'
                    WHEN Time > 60 THEN 'slow'
                    WHEN State LIKE '%Lock%' THEN 'locked'
                    ELSE 'active'
                END as status
            FROM information_schema.PROCESSLIST 
            WHERE COMMAND != 'Daemon'
            ORDER BY Time DESC
        ");
        
        while ($row = $db->fetch_array($result)) {
            $processes[] = $row;
        }
        
        $db->free_result($result);
    } catch (Exception $e) {
        error_log("Process list error: " . $e->getMessage());
    }
    
    return $processes;
}

if (!defined('STAFF_PANEL')) 
{
    http_response_code(403);
    $errorPage = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="alert alert-danger text-center">
                    <h1 class="alert-heading">🚫 Access Denied</h1>
                    <p class="mb-0">Direct access to this file is not allowed.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    die($errorPage);
}

// ── AJAX handlers - must run BEFORE any HTML output (stdhead() etc),
// otherwise headers()/clean JSON output are impossible once the page
// has started rendering. ──────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    switch ($_GET['ajax']) {
        case 'stats':
            $status = getDatabaseServerStatus();
            echo json_encode([
                'success' => true,
                'uptime' => $status['uptime'] ?? '00:00:00',
                'connections' => $status['Threads_connected'] ?? 0,
                'max_connections' => $status['Max_connections'] ?? 0,
                'queries_per_second' => $status['queries_per_second'] ?? 0,
                'active_processes' => $status['active_processes'] ?? 0,
                'slow_queries' => $status['slow_queries'] ?? 0,
                'database_size_mb' => $status['database_size_mb'] ?? 0
            ]);
            exit;

        case 'processes':
            $processes = getActiveProcesses();
            echo json_encode([
                'success' => true,
                'processes' => $processes
            ]);
            exit;

        case 'kill':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid security token or request method']);
                exit;
            }

            if (isset($_GET['process']) || isset($_POST['process'])) {
                $processId = (int)($_POST['process'] ?? $_GET['process']);

                // Проверяем, что процесс ещё жив - к моменту клика он мог
                // уже сам завершиться или быть убитым другой попыткой.
                $existsCheck = $db->sql_query_prepared("SELECT 1 FROM information_schema.PROCESSLIST WHERE Id = ?", [$processId]);
                if (!$existsCheck || $db->num_rows($existsCheck) === 0) {
                    echo json_encode(['success' => false, 'error' => 'Process #' . $processId . ' no longer exists (it may have already finished or been killed).']);
                    exit;
                }

                try {
                    // KILL - административная команда, MySQL не поддерживает
                    // её через PREPARE-протокол ("This command is not
                    // supported in the prepared statement protocol yet"),
                    // поэтому sql_query_prepared() тут не применить.
                    // $processId уже приведён к (int) выше - конкатенация безопасна.
                    $ok = @$db->sql_query("KILL " . $processId);
                    if ($ok) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to kill process #' . $processId . '.']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            }
            exit;
    }
}

// Get real-time status
$serverStatus = getDatabaseServerStatus();
$activeProcesses = getActiveProcesses();

// Process parameters
$action = $_GET['Do'] ?? '';
$table = $_GET['table'] ?? '';
$message = $_GET['message'] ?? '';
$error = '';

if ($action === 'T' && $table && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $error = 'Invalid or expired security token. Please try again from this page.';
    } else {
    $tableName = '`' . $table . '`';
    $sql = "OPTIMIZE TABLE {$tableName}";
    
    if ($db->sql_query_prepared($sql)) {
        $successMsg = 'Table "' . htmlspecialchars($table) . '" optimized successfully!';
        header('Location: ' . $_this_script_ . '&Do=F&message=' . urlencode($successMsg));
        exit;
    } else {
        $error = 'Optimization error: ' . htmlspecialchars($db->error_string());
    }
    }
}

stdhead('MySQL Server Stats');
?>

<input type="hidden" id="myPostKey" value="<?= htmlspecialchars($mybb->post_code) ?>">
<input type="hidden" id="actQuery" value="?act=<?= htmlspecialchars($_GET['act'] ?? 'mysql_overview') ?>">

<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-11">
            <!-- Header Card -->
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-primary text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-database me-2"></i>
                            <h2 class="d-inline mb-0 fw-bold">MySQL Database Overview</h2>
                            <br><small>Table monitoring and performance optimization</small>
                        </div>
                        <button class="btn btn-light btn-sm" onclick="location.reload()" title="Refresh Data">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <small class="text-white-50">Updated: <?= date('m/d/Y H:i:s') ?> (<?= date_default_timezone_get() ?>)</small>
                    </div>
                </div>
            </div>

            <!-- Real-time Monitoring Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-heart-pulse text-danger me-2"></i>
                                    Real-time Server Monitoring
                                </h5>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                                    <label class="form-check-label small" for="autoRefresh">Auto-refresh</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3" id="serverStats">
                                <!-- Server Uptime -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-primary bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-clock fs-2 text-primary mb-2"></i>
                                            <h4 class="text-primary fw-bold mb-1" id="uptimeStat">
                                                <?= $serverStatus['uptime'] ?? '00:00:00' ?>
                                            </h4>
                                            <small class="text-muted">Uptime</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Active Connections -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-success bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-plug fs-2 text-success mb-2"></i>
                                            <h4 class="text-success fw-bold mb-1" id="connectionsStat">
                                                <?= $serverStatus['Threads_connected'] ?? 0 ?>
                                            </h4>
                                            <small class="text-muted">
                                                Connections
                                                <?php if (isset($serverStatus['Max_connections'])): ?>
                                                    <br><small>/ <?= $serverStatus['Max_connections'] ?> max</small>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Queries per Second -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-info bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-bolt fs-2 text-info mb-2"></i>
                                            <h4 class="text-info fw-bold mb-1" id="queriesStat">
                                                <?= $serverStatus['queries_per_second'] ?? 0 ?>
                                            </h4>
                                            <small class="text-muted">Queries/sec</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Active Processes -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-warning bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-list fs-2 text-warning mb-2"></i>
                                            <h4 class="text-warning fw-bold mb-1" id="processesStat">
                                                <?= $serverStatus['active_processes'] ?? 0 ?>
                                            </h4>
                                            <small class="text-muted">Active Processes</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Slow Queries -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-danger bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-tachometer-alt fs-2 text-danger mb-2"></i>
                                            <h4 class="text-danger fw-bold mb-1" id="slowQueriesStat">
                                                <?= $serverStatus['slow_queries'] ?? 0 ?>
                                            </h4>
                                            <small class="text-muted">Slow Queries</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Database Size -->
                                <div class="col-xl-2 col-md-4 col-6">
                                    <div class="card bg-secondary bg-opacity-10 border-0 h-100">
                                        <div class="card-body text-center p-3">
                                            <i class="fas fa-hdd fs-2 text-secondary mb-2"></i>
                                            <h4 class="text-secondary fw-bold mb-1" id="dbSizeStat">
                                                <?= $serverStatus['database_size_mb'] ?? 0 ?>MB
                                            </h4>
                                            <small class="text-muted">Database Size</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connection Progress -->
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Connection Usage</small>
                                    <small class="text-muted" id="connectionUsage">
                                        <?php 
                                        if (isset($serverStatus['Threads_connected']) && isset($serverStatus['Max_connections'])) {
                                            $usage = round(($serverStatus['Threads_connected'] / $serverStatus['Max_connections']) * 100, 1);
                                            echo $serverStatus['Threads_connected'] . ' / ' . $serverStatus['Max_connections'] . ' (' . $usage . '%)';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar 
                                        <?php
                                        if (isset($serverStatus['Threads_connected']) && isset($serverStatus['Max_connections'])) {
                                            $usagePercent = ($serverStatus['Threads_connected'] / $serverStatus['Max_connections']) * 100;
                                            if ($usagePercent > 80) echo 'bg-danger';
                                            elseif ($usagePercent > 60) echo 'bg-warning';
                                            else echo 'bg-success';
                                        }
                                        ?>" 
                                        id="connectionProgress" 
                                        style="width: <?= $usagePercent ?? 0 ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Processes Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-list-alt text-info me-2"></i>
                                    Active Database Processes
                                </h5>
                                <button class="btn btn-sm btn-outline-info" onclick="refreshProcessList()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" id="processTable">
                                    <thead class="table-info">
                                        <tr>
                                            <th class="ps-3">ID</th>
                                            <th>User</th>
                                            <th>Database</th>
                                            <th>Command</th>
                                            <th>Time</th>
                                            <th>State</th>
                                            <th>Query</th>
                                            <th class="text-center pe-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="processList">
                                        <?php if (empty($activeProcesses)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    No active processes found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($activeProcesses as $process): ?>
                                                <tr class="<?= $process['status'] === 'slow' ? 'table-warning' : ($process['status'] === 'locked' ? 'table-danger' : '') ?>">
                                                    <td class="ps-3 fw-semibold"><?= $process['Id'] ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($process['User']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($process['db'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?= $process['Command'] === 'Sleep' ? 'bg-light text-dark' : 
                                                                 ($process['Command'] === 'Query' ? 'bg-primary' : 'bg-info') ?>">
                                                            <?= $process['Command'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge 
                                                            <?= $process['Time'] > 60 ? 'bg-danger' : 
                                                                 ($process['Time'] > 10 ? 'bg-warning' : 'bg-success') ?>">
                                                            <?= $process['Time'] ?>s
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= $process['State'] ?? 'N/A' ?></small>
                                                    </td>
                                                    <td>
                                                        <small class="font-monospace" style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            <?= htmlspecialchars($process['Info'] ?? 'N/A') ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center pe-3">
                                                        <?php if ($process['Command'] !== 'Sleep' && $process['Command'] !== 'Daemon'): ?>
                                                            <button class="btn btn-sm btn-outline-danger kill-process-btn" 
                                                                    data-pid="<?= (int)$process['Id'] ?>"
                                                                    data-user="<?= htmlspecialchars($process['User']) ?>"
                                                                    title="Kill Process">
                                                                <i class="fas fa-skull"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?= count($activeProcesses) ?> active processes
                                </small>
                                <div>
                                    <span class="badge bg-light text-dark me-2"><i class="fas fa-circle text-success"></i> Fast (<10s)</span>
                                    <span class="badge bg-light text-dark me-2"><i class="fas fa-circle text-warning"></i> Slow (10-60s)</span>
                                    <span class="badge bg-light text-dark"><i class="fas fa-circle text-danger"></i> Very Slow (>60s)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="databaseTables">
                            <thead class="table-dark">
                                <tr>
                                    <th class="ps-4 py-3">
                                        <i class="fas fa-database me-2"></i>Table Name
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-chart-pie me-2"></i>Total Size
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-list-ol me-2"></i>Rows
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-arrows-alt-h me-2"></i>Avg Row
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-archive me-2"></i>Data
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-sitemap me-2"></i>Indexes
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-tachometer-alt me-2"></i>Overhead
                                    </th>
                                    <th class="text-center py-3">
                                        <i class="fas fa-cogs me-2"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $databaseName = $config['database']['database'] ?? '';
                            if (empty($databaseName)) {
                                echo '<tr><td colspan="8" class="text-center p-5">';
                                echo '<div class="alert alert-warning">';
                                echo '<i class="fas fa-exclamation-circle me-2"></i>';
                                echo 'Database configuration not found';
                                echo '</div></td></tr>';
                            } else {
                                try {
                                    $query = "SHOW TABLE STATUS FROM `" . $databaseName . "`";
                                    $res = $db->sql_query_prepared($query);
                                    
                                    if (!$res) {
                                        throw new RuntimeException('Database query error');
                                    }
                                    
                                    $stats = [
                                        'tables' => 0,
                                        'total_size' => 0,
                                        'needs_optimization' => 0
                                    ];
                                    
                                    while ($row = $db->fetch_array($res)) {
                                        $stats['tables']++;
                                        
                                        // Format data
                                        [$avgSize, $avgUnit] = formatByteSize((int)($row['Avg_row_length'] ?? 0));
                                        [$dataLength, $dataUnit] = formatByteSize((int)($row['Data_length'] ?? 0));
                                        [$indexLength, $indexUnit] = formatByteSize((int)($row['Index_length'] ?? 0));
                                        [$dataFree, $freeUnit] = formatByteSize((int)($row['Data_free'] ?? 0));
                                        
                                        $tableSize = (int)($row['Data_length'] ?? 0) + (int)($row['Index_length'] ?? 0);
                                        $stats['total_size'] += $tableSize;
                                        [$totalSize, $totalUnit] = formatByteSize($tableSize, 1);
                                        
                                        $hasOverhead = (int)($row['Data_free'] ?? 0) > 0;
                                        if ($hasOverhead) {
                                            $stats['needs_optimization']++;
                                        }
                                        
                                        // Metadata
                                        [$rowsCount, $createTime, $updateTime, $checkTime] = formatTableMetadata($row);
                                        $tableNameEscaped = htmlspecialchars($row['Name'] ?? 'unknown');
                                        $engine = htmlspecialchars($row['Engine'] ?? 'N/A');
                                        $rowFormat = htmlspecialchars($row['Row_format'] ?? 'N/A');
                                        
                                        $overheadHtml = getOverheadDisplay($row, $_this_script_);
                                        
                                        // Fragmentation percentage
                                        $fragmentationPercent = 0;
                                        if ($dataFree > 0 && $tableSize > 0) {
                                            $fragmentationPercent = (int)round(($dataFree / $tableSize) * 100);
                                            $fragmentationPercent = min(100, $fragmentationPercent);
                                        }
                                        
                                        // Output table row
                                        echo '<tr class="table-row" data-overhead="' . ($hasOverhead ? '1' : '0') . '">';
                                        echo '<td class="ps-4 align-middle fw-semibold">';
                                        echo '<i class="fas fa-table me-2 text-primary"></i>';
                                        echo $tableNameEscaped;
                                        if ($engine !== 'N/A') {
                                            echo '<br><small class="badge bg-secondary text-xs">' . $engine . '</small>';
                                        }
                                        echo '</td>';
                                        
                                        echo '<td class="text-center align-middle">';
                                        echo '<span class="badge bg-info">' . $totalSize . ' ' . $totalUnit . '</span>';
                                        echo '</td>';
                                        
                                        echo '<td class="text-center align-middle">' . $rowsCount . '</td>';
                                        echo '<td class="text-center align-middle text-muted small">' . $avgSize . ' ' . $avgUnit . '</td>';
                                        echo '<td class="text-center align-middle">' . $dataLength . ' ' . $dataUnit . '</td>';
                                        echo '<td class="text-center align-middle">' . $indexLength . ' ' . $indexUnit . '</td>';
                                        
                                        echo '<td class="text-center align-middle">';
                                        echo $overheadHtml;
                                        echo '<div class="progress mt-1" style="height: 4px;">';
                                        echo '<div class="progress-bar bg-danger" style="width: ' . $fragmentationPercent . '%"></div>';
                                        echo '</div>';
                                        echo '<small class="text-muted">' . $fragmentationPercent . '%</small>';
                                        echo '</td>';
                                        
                                        echo '<td class="text-center align-middle">';
                                        
                                        if ($hasOverhead) {
                                            $tableNameEscaped = htmlspecialchars($row['Name']);
                                            $overheadSize = $dataFree . ' ' . $freeUnit;
                                            
                                            echo '<div class="btn-group btn-group-sm" role="group">';
                                            echo '<button type="button" ';
                                            echo 'class="btn btn-outline-danger hover-lift position-relative" ';
                                            echo 'onclick="showOptimizeModal(\'' . addslashes($tableNameEscaped) . '\')" ';
                                            echo 'title="Optimize Table - Reclaim ' . $overheadSize . ' of space" ';
                                            echo 'data-bs-toggle="tooltip" data-bs-placement="top">';
                                            echo '<i class="fas fa-rocket me-1"></i>';
                                            echo 'Optimize';
                                            // Badge with overhead size
                                            echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">';
                                            echo $overheadSize;
                                            echo '<span class="visually-hidden">overhead size</span>';
                                            echo '</span>';
                                            echo '</button>';
                                            echo '</div>';
                                            
                                        } else {
                                            echo '<div class="d-flex align-items-center justify-content-center">';
                                            echo '<span class="badge bg-success bg-opacity-20 text-success fs-7 px-3 py-2">';
                                            echo '<i class="fas fa-check-circle me-1"></i>';
                                            echo '<span class="fw-semibold">Optimized</span>';
                                            echo '</span>';
                                            echo '</div>';
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                        
                                        // Detailed information
                                        echo '<tr class="table-secondary">';
                                        echo '<td colspan="8" class="p-3 bg-light">';
                                        echo '<div class="row g-2 small text-muted">';
                                        echo '<div class="col-md-2"><strong>Format:</strong> ' . $rowFormat . '</div>';
                                        echo '<div class="col-md-2"><strong>Engine:</strong> ' . $engine . '</div>';
                                        echo '<div class="col-md-2"><strong>Created:</strong> ' . $createTime . '</div>';
                                        echo '<div class="col-md-2"><strong>Updated:</strong> ' . $updateTime . '</div>';
                                        echo '<div class="col-md-2"><strong>Checked:</strong> ' . $checkTime . '</div>';                                     
										echo '<div class="col-md-2"><strong>Checksum:</strong> ' . htmlspecialchars((string)($row['Checksum'] ?? 'N/A')) . '</div>';
                                        echo '</div>';
                                        echo '</td></tr>';
                                    }
                                    
                                    $db->free_result($res);
                                    
                                    // Summary statistics
                                    [$totalFormatted, $totalUnit] = formatByteSize($stats['total_size'], 1);
                                    
                                    echo '<tr class="table-success fw-bold">';
                                    echo '<td class="ps-4">';
                                    echo '<i class="fas fa-chart-line me-2 text-success"></i>';
                                    echo '<strong>TOTAL: ' . $stats['tables'] . ' tables</strong>';
                                    if ($stats['needs_optimization'] > 0) {
                                        echo ' <span class="badge bg-warning">';
                                        echo $stats['needs_optimization'] . ' need optimization';
                                        echo '</span>';
                                    }
                                    echo '</td>';
                                    echo '<td class="text-center"><span class="badge bg-success fs-6">' . $totalFormatted . ' ' . $totalUnit . '</span></td>';
                                    echo '<td colspan="6" class="text-end pe-4">';
                                    echo '<div class="alert alert-info d-inline-block m-0 p-2 small">';
                                    echo '<i class="fas fa-info-circle me-1"></i>';
                                    echo 'Red overhead indicates data fragmentation';
                                    echo '</div>';
                                    echo '</td></tr>';
                                    
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="8" class="text-center p-5">';
                                    echo '<div class="alert alert-danger">';
                                    echo '<i class="fas fa-database me-2"></i>';
                                    echo htmlspecialchars($e->getMessage());
                                    echo '</div></td></tr>';
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Data current as of <?= date('m/d/Y H:i:s') ?>
                        </small>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time monitoring functionality
let autoRefreshInterval;

function startAutoRefresh() {
    if (document.getElementById('autoRefresh').checked) {
        autoRefreshInterval = setInterval(updateServerStats, 5000); // Update every 5 seconds
    }
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

async function updateServerStats() {
    try {
        const actQuery = document.getElementById('actQuery')?.value || '';
        const response = await fetch(actQuery + '&ajax=stats&_=' + Date.now());
        const data = await response.json();
        
        if (data.success) {
            // Update statistics cards
            document.getElementById('uptimeStat').textContent = data.uptime;
            document.getElementById('connectionsStat').textContent = data.connections;
            document.getElementById('queriesStat').textContent = data.queries_per_second;
            document.getElementById('processesStat').textContent = data.active_processes;
            document.getElementById('slowQueriesStat').textContent = data.slow_queries;
            document.getElementById('dbSizeStat').textContent = data.database_size_mb + 'MB';
            
            // Update connection progress
            const usagePercent = (data.connections / data.max_connections) * 100;
            document.getElementById('connectionUsage').textContent = 
                `${data.connections} / ${data.max_connections} (${usagePercent.toFixed(1)}%)`;
            
            const progressBar = document.getElementById('connectionProgress');
            progressBar.style.width = `${usagePercent}%`;
            progressBar.className = `progress-bar ${
                usagePercent > 80 ? 'bg-danger' : 
                usagePercent > 60 ? 'bg-warning' : 'bg-success'
            }`;
            
            // Add pulse animation to indicate update
            document.getElementById('serverStats').style.animation = 'pulseUpdate 1s ease';
            setTimeout(() => {
                document.getElementById('serverStats').style.animation = '';
            }, 1000);
        }
    } catch (error) {
        console.error('Failed to update server stats:', error);
    }
}

async function refreshProcessList() {
    try {
        const button = event.target;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        button.disabled = true;
        
        const actQuery = document.getElementById('actQuery')?.value || '';
        const response = await fetch(actQuery + '&ajax=processes&_=' + Date.now());
        const data = await response.json();
        
        if (data.success) {
            updateProcessList(data.processes);
            showToast('Process list updated', 'success');
        }
        
        button.innerHTML = originalHtml;
        button.disabled = false;
    } catch (error) {
        showToast('Failed to refresh process list', 'error');
        console.error('Process list refresh error:', error);
    }
}

function updateProcessList(processes) {
    const processList = document.getElementById('processList');
    
    if (processes.length === 0) {
        processList.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    No active processes found
                </td>
            </tr>
        `;
        return;
    }
    
    processList.innerHTML = processes.map(process => `
        <tr class="${process.status === 'slow' ? 'table-warning' : (process.status === 'locked' ? 'table-danger' : '')}">
            <td class="ps-3 fw-semibold">${process.Id}</td>
            <td><span class="badge bg-secondary">${escapeHtml(process.User)}</span></td>
            <td>${escapeHtml(process.db || 'N/A')}</td>
            <td>
                <span class="badge ${process.Command === 'Sleep' ? 'bg-light text-dark' : 
                                     process.Command === 'Query' ? 'bg-primary' : 'bg-info'}">
                    ${process.Command}
                </span>
            </td>
            <td>
                <span class="badge ${process.Time > 60 ? 'bg-danger' : 
                                     process.Time > 10 ? 'bg-warning' : 'bg-success'}">
                    ${process.Time}s
                </span>
            </td>
            <td><small class="text-muted">${process.State || 'N/A'}</small></td>
            <td>
                <small class="font-monospace" style="max-width: 200px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ${escapeHtml(process.Info || 'N/A')}
                </small>
            </td>
            <td class="text-center pe-3">
                ${process.Command !== 'Sleep' && process.Command !== 'Daemon' ? 
                    `<button class="btn btn-sm btn-outline-danger kill-process-btn" 
                            data-pid="${process.Id}" data-user="${escapeHtml(process.User)}"
                            title="Kill Process">
                        <i class="fas fa-skull"></i>
                    </button>` : 
                    '<span class="text-muted">-</span>'
                }
            </td>
        </tr>
    `).join('');
}

function killProcess(processId, userName) {
    if (confirm(`Kill process #${processId} from user "${userName}"?`)) {
        const myPostKey = document.getElementById('myPostKey')?.value || '';
        const actQuery = document.getElementById('actQuery')?.value || '';
        fetch(`${actQuery}&ajax=kill&process=${processId}&my_post_key=${encodeURIComponent(myPostKey)}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Process #${processId} killed successfully`, 'success');
                refreshProcessList();
            } else {
                showToast(`Failed to kill process: ${data.error}`, 'error');
            }
        })
        .catch(error => {
            showToast('Error killing process', 'error');
            console.error('Kill process error:', error);
        });
    }
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Initialize auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();

    // Delegated handler for Kill Process buttons - works for both the
    // initial server-rendered rows and rows re-rendered via AJAX refresh.
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.kill-process-btn');
        if (!btn) return;
        killProcess(parseInt(btn.dataset.pid, 10), btn.dataset.user);
    });
    
    // Toggle auto-refresh
    document.getElementById('autoRefresh').addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });
    
    // Add CSS for pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulseUpdate {
            0% { background-color: transparent; }
            50% { background-color: rgba(0, 123, 255, 0.05); }
            100% { background-color: transparent; }
        }
    `;
    document.head.appendChild(style);
});

// Your existing table and optimization functions
function confirmOptimization(tableName) {
    return confirm('🚀 Optimize table "' + tableName + '"?\n\n' +
        'This action will:\n' +
        '• Improve performance\n' +
        '• Free fragmented space\n' +
        '• May take several minutes\n\n' +
        'Continue?');
}

// Row appearance animation
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.table-row');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
    
    // Row click for selection
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('a, button')) {
                this.classList.toggle('table-active');
            }
        });
    });
});

// Initialize tooltips
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Enhanced modal with size info
function showOptimizeModal(tableName) {
    // Get overhead size for this table
    const overheadBadge = document.querySelector(`button[onclick="showOptimizeModal('${tableName}')"] .badge`);
    const overheadSize = overheadBadge ? overheadBadge.textContent.trim() : 'unknown size';
    
    const modalHtml = `
        <div class="modal fade" id="optimizeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-xl rounded-4 overflow-hidden">
                    <!-- Animated Background -->
                    <div class="position-relative">
                        <div class="bg-gradient-danger" 
                             style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
                                    position: absolute; top: 0; left: 0; right: 0; height: 200px; 
                                    z-index: 1; opacity: 0.1;"></div>
                        
                        <!-- Header -->
                        <div class="modal-header bg-gradient-danger text-white rounded-top p-4 position-relative z-2">
                            <div class="d-flex align-items-center w-100">
                                <div class="icon-shape bg-white bg-opacity-15 rounded-4 p-3 me-4 shadow-sm">
                                    <i class="fas fa-rocket fs-3 text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h4 class="modal-title mb-1 fw-bolder fs-4">🚀 Optimize Table</h4>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light bg-opacity-20 text-white px-2 py-1 rounded-pill small">
                                            <i class="fas fa-hdd me-1"></i>${overheadSize}
                                        </span>
                                        <small class="opacity-90">Reclaim fragmented space</small>
                                    </div>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" 
                                        aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="modal-body p-0">
                        <!-- Hero Section -->
                        <div class="p-4 pb-3 bg-gradient-light">
                            <div class="text-center">
                                <div class="icon-shape bg-danger bg-opacity-10 text-danger rounded-5 p-4 d-inline-flex mb-3 shadow-sm"
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-database fs-2"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-2">${tableName}</h3>
                                <p class="text-muted mb-0">Reclaiming <strong class="text-danger fw-bold">${overheadSize}</strong> 
                                   of fragmented storage space</p>
                            </div>
                        </div>
                        
                        <!-- Benefits Grid -->
                        <div class="row g-3 p-4">
                            <div class="col-md-4 text-center">
                                <div class="icon-shape bg-danger bg-opacity-10 text-danger rounded-4 p-3 mb-2 shadow-sm">
                                    <i class="fas fa-broom fs-2"></i>
                                </div>
                                <h6 class="fw-semibold text-dark">Defragment</h6>
                                <small class="text-muted">Organize scattered data</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="icon-shape bg-danger bg-opacity-10 text-danger rounded-4 p-3 mb-2 shadow-sm">
                                    <i class="fas fa-bolt fs-2"></i>
                                </div>
                                <h6 class="fw-semibold text-dark">Speed Boost</h6>
                                <small class="text-muted">Faster query response</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="icon-shape bg-danger bg-opacity-10 text-danger rounded-4 p-3 mb-2 shadow-sm">
                                    <i class="fas fa-recycle fs-2"></i>
                                </div>
                                <h6 class="fw-semibold text-dark">Space Recovery</h6>
                                <small class="text-muted">Free up disk space</small>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-danger p-2 rounded-3 me-3 shadow-sm">
                                    <i class="fas fa-chart-line text-white"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold">Optimization Impact</h6>
                            </div>
                            <div class="progress rounded-4 overflow-hidden shadow-sm" style="height: 8px;">
                                <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">Estimated 75% performance improvement</small>
                        </div>
                        
                        <!-- Info Cards -->
                        <div class="px-4 pb-4">
                            <!-- Benefits Card -->
                            <div class="card border-0 bg-light shadow-sm mb-3 overflow-hidden">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-success p-2 rounded-3 me-3 flex-shrink-0">
                                            <i class="fas fa-check-circle text-white"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-success mb-2">🎯 Performance Benefits</h6>
                                            <ul class="list-unstyled mb-0 ps-3">
                                                <li class="mb-2 small"><i class="fas fa-arrow-up text-success me-2"></i>Lightning-fast query execution</li>
                                                <li class="mb-2 small"><i class="fas fa-hdd text-success me-2"></i>Reduced I/O operations</li>
                                                <li class="mb-2 small"><i class="fas fa-battery-full text-success me-2"></i>Improved cache efficiency</li>
                                                <li class="small"><i class="fas fa-search text-success me-2"></i>Better index utilization</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Warning Card -->
                            <div class="card border-0 bg-warning bg-opacity-10 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-warning p-2 rounded-3 me-3 flex-shrink-0">
                                            <i class="fas fa-exclamation-triangle text-white"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-warning mb-2">⚠️ Operation Details</h6>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-clock me-1"></i>Duration: 1-5 min
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-lock me-1"></i>Table locked
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-users me-1"></i>Low traffic recommended
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-shield-alt me-1"></i>Backup advised
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="modal-footer border-0 bg-gradient-light p-4 rounded-bottom">
                        <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-3" 
                                data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger px-5 py-2 rounded-3 btn-optimize shadow-sm position-relative overflow-hidden"
                                onclick="performOptimization('${tableName}')" data-bs-dismiss="modal">
                            <span class="btn-text">
                                <i class="fas fa-rocket me-2"></i>🚀 Optimize Now
                            </span>
                            <span class="btn-loading d-none">
                                <i class="fas fa-spinner fa-spin me-2"></i>Optimizing...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Custom CSS -->
        <style>
            .shadow-xl { box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important; }
            .bg-gradient-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
            .bg-gradient-light { background: linear-gradient(135deg, #f8f9fa, #e9ecef); }
            
            .btn-optimize {
                background: linear-gradient(135deg, #dc3545, #c82333);
                border: none;
                transition: all 0.3s ease;
            }
            .btn-optimize:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(220, 53, 69, 0.4);
                background: linear-gradient(135deg, #c82333, #dc3545);
            }
            .btn-optimize:active {
                transform: translateY(0);
            }
            
            .progress-bar {
                background: linear-gradient(90deg, #dc3545, #c82333) !important;
            }
            
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .modal.show .modal-dialog { 
                animation: fadeInUp 0.4s ease-out; 
            }
            
            .icon-shape {
                transition: transform 0.3s ease;
            }
            .icon-shape:hover {
                transform: scale(1.1);
            }
        </style>
    `;
    
    // Remove existing modal
    const existingModal = document.getElementById('optimizeModal');
    if (existingModal) existingModal.remove();
    
    // Insert new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Initialize modal
    const modalElement = document.getElementById('optimizeModal');
    const modal = new bootstrap.Modal(modalElement, { 
        backdrop: 'static',
        keyboard: false 
    });
    modal.show();
    
    // Enhanced button loading state
    const optimizeBtn = document.querySelector('.btn-optimize');
    optimizeBtn.addEventListener('click', function() {
        const btnText = this.querySelector('.btn-text');
        const btnLoading = this.querySelector('.btn-loading');
        
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        this.disabled = true;
        
        // Add pulsing effect
        this.style.animation = 'pulse 1.5s infinite';
    });
    
    // Clean up modal on hide
    modalElement.addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
    
    // Add hover effects to icons
    setTimeout(() => {
        const icons = modalElement.querySelectorAll('.icon-shape');
        icons.forEach(icon => {
            icon.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            icon.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    }, 100);
}

// Perform optimization
function performOptimization(tableName) {
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('optimizeModal'));
    modal.hide();
    
    // Show loading state
    showToast(`<i class="fas fa-spinner fa-spin me-2"></i>Optimizing table "${tableName}"...`, 'info');
    
    // Perform AJAX request
    const myPostKey = document.getElementById('myPostKey')?.value || '';
    const actQuery = document.getElementById('actQuery')?.value || '';
    fetch(`${actQuery}&Do=T&table=${encodeURIComponent(tableName)}&my_post_key=${encodeURIComponent(myPostKey)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            showToast(`<i class="fas fa-check-circle me-2"></i>Table "${tableName}" optimized successfully!`, 'success');
            
            // Update UI after optimization
            setTimeout(() => {
                updateTableStatus(tableName);
            }, 1000);
        } else {
            throw new Error('Optimization failed');
        }
    })
    .catch(error => {
        showToast(`<i class="fas fa-exclamation-circle me-2"></i>Failed to optimize table "${tableName}"`, 'error');
        console.error('Optimization error:', error);
    });
}

// Update table status in UI
function updateTableStatus(tableName) {
    const tableRow = document.querySelector(`[data-table-name="${tableName.toLowerCase()}"]`);
    if (tableRow) {
        // Update overhead badge
        const overheadCell = tableRow.querySelector('td:nth-child(7)');
        if (overheadCell) {
            overheadCell.innerHTML = `
                <span class="badge bg-success bg-opacity-10 text-success fs-7">
                    <i class="fas fa-check me-1"></i>0 Bytes
                </span>
            `;
        }
        
        // Add success animation
        tableRow.classList.add('optimized');
        setTimeout(() => {
            tableRow.classList.remove('optimized');
        }, 2000);
    }
}

// Enhanced toast function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    toast.style.cssText = `
        background: ${getToastColor(type)};
        color: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        margin-bottom: 10px;
        animation: slideInRight 0.4s ease-out;
        position: relative;
        min-height: 60px;
        display: flex;
        align-items: center;
        border-left: 4px solid ${getToastBorderColor(type)};
    `;
    
    toast.innerHTML = `
        <div style="flex: 1;">
            ${message}
        </div>
        <button type="button" class="toast-close btn-close btn-close-white ms-3" 
                onclick="this.parentElement.parentElement.remove()"></button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
        min-width: 350px;
    `;
    document.body.appendChild(container);
    return container;
}

function getToastColor(type) {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    return colors[type] || '#17a2b8';
}

function getToastBorderColor(type) {
    const colors = {
        success: '#1e7e34',
        error: '#c82333',
        warning: '#e0a800',
        info: '#138496'
    };
    return colors[type] || '#138496';
}

// Add CSS styles
const optimizeStyles = `
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%) !important;
    }
    
    .table-active {
        background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(58, 12, 163, 0.05) 100%) !important;
        border-left: 4px solid #4361ee !important;
    }
    
    .optimized {
        animation: pulseSuccess 2s ease-in-out;
    }
    
    @keyframes pulseSuccess {
        0% { background-color: transparent; }
        50% { background-color: rgba(40, 167, 69, 0.1); }
        100% { background-color: transparent; }
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .icon-shape {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }
`;

// Inject styles
if (!document.getElementById('optimize-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'optimize-styles';
    styleSheet.textContent = optimizeStyles;
    document.head.appendChild(styleSheet);
}
</script>

<?php
stdfoot();
?>