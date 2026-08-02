<?php
declare(strict_types=1);



enum ByteUnit: string {
    case BYTES = 'Bytes';
    case KB = 'KB';
    case MB = 'MB';
    case GB = 'GB';
    case TB = 'TB';
    case PB = 'PB';
    case EB = 'EB';
}

class SystemStats {
    private const BYTE_UNITS = [ByteUnit::BYTES, ByteUnit::KB, ByteUnit::MB, ByteUnit::GB, ByteUnit::TB, ByteUnit::PB, ByteUnit::EB];
    
    public static function formatByteDown(float $value, int $precision = 2): array {
        $base = 1024;
        $value = max($value, 0);
        
        foreach (array_reverse(self::BYTE_UNITS, true) as $exponent => $unit) {
            $threshold = $base ** $exponent;
            if ($value >= $threshold) {
                $formatted = $exponent > 0 
                    ? number_format($value / $threshold, $precision, '.', ',')
                    : number_format($value, 0, '.', ',');
                return [$formatted, $unit->value];
            }
        }
        
        return [number_format($value, 0, '.', ','), ByteUnit::BYTES->value];
    }
    
    public static function formatTimeSpan(int $seconds): string {
        $components = [];
        
        $intervals = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];
        
        foreach ($intervals as $unit => $divisor) {
            if ($seconds >= $divisor) {
                $count = floor($seconds / $divisor);
                $seconds %= $divisor;
                $components[] = $count . ' ' . $unit . ($count !== 1 ? 's' : '');
            }
        }
        
        return $components ? implode(', ', $components) : '0 seconds';
    }
    
    public static function getLocalizedDate(?int $timestamp = null, string $format = 'F j, Y \a\t g:i A'): string {
    $timestamp ??= time();
    return date($format, $timestamp);
    }

}

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center"><i class="bi bi-shield-exclamation"></i> Direct initialization of this file is not allowed.</div>');
}

stdhead('MySQL Server Statistics');

// Fetch MySQL status
$statusResult = $db->sql_query_prepared('SHOW STATUS');
if (!$statusResult) {
    stderr('Database Error', 'Unable to fetch MySQL status information.');
}

$serverStatus = [];
while ($row = $db->fetch_array($statusResult)) {
    $serverStatus[$row['Variable_name']] = $row['Value'];
}
$db->free_result($statusResult);

// Get server startup time
$uptimeResult = $db->sql_query_prepared('SELECT UNIX_TIMESTAMP() - ? AS startup_time', [(int)($serverStatus['Uptime'] ?? 0)]);
$startupRow = $db->fetch_array($uptimeResult);
$startupTime = $startupRow['startup_time'] ?? time();
$db->free_result($uptimeResult);

// Extract query statistics
$queryStats = [];
foreach ($serverStatus as $name => $value) {
    if (str_starts_with($name, 'Com_')) {
        $queryName = str_replace('_', ' ', substr($name, 4));
        $queryStats[$queryName] = (int)$value;
    }
}
?>

<!-- Main Header -->
<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center mb-4">
                <div class="me-3">
                    <i class="bi bi-database-fill-gear display-4 text-primary"></i>
                </div>
                <div>
                    <h1 class="h3 mb-1 fw-bold text-dark">MySQL Server Statistics</h1>
                    <p class="text-muted mb-0">
                        <i class="bi bi-clock-history me-1"></i>
                        Server running for <?= SystemStats::formatTimeSpan((int)$serverStatus['Uptime']) ?>
                    </p>
                    <small class="text-muted">
                        Started on <?= SystemStats::getLocalizedDate((int)$startupTime) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Traffic Card -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-cloud-arrow-down-fill me-2"></i>
                    <span class="fw-semibold">Network Traffic</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Per Hour</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ([
                                    ['Received', 'Bytes_received'],
                                    ['Sent', 'Bytes_sent'],
                                    ['Total', null]
                                ] as [$label, $key]): ?>
                                <tr>
                                    <td class="fw-medium"><?= $label ?></td>
                                    <td class="text-end">
                                        <?php if ($key): ?>
                                            <?php [$value, $unit] = SystemStats::formatByteDown((float)$serverStatus[$key]) ?>
                                            <span class="badge bg-light text-dark"><?= $value ?> <?= $unit ?></span>
                                        <?php else: ?>
                                            <?php [$value, $unit] = SystemStats::formatByteDown((float)$serverStatus['Bytes_received'] + (float)$serverStatus['Bytes_sent']) ?>
                                            <span class="badge bg-primary text-white"><?= $value ?> <?= $unit ?></span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($key): ?>
                                            <?php [$value, $unit] = SystemStats::formatByteDown((float)$serverStatus[$key] * 3600 / (float)$serverStatus['Uptime']) ?>
                                            <span class="text-muted small"><?= $value ?> <?= $unit ?></span>
                                        <?php else: ?>
                                            <?php [$value, $unit] = SystemStats::formatByteDown(((float)$serverStatus['Bytes_received'] + (float)$serverStatus['Bytes_sent']) * 3600 / (float)$serverStatus['Uptime']) ?>
                                            <span class="text-muted small"><?= $value ?> <?= $unit ?></span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-plug-fill me-2"></i>
                    <span class="fw-semibold">Connection Statistics</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Per Hour</th>
                                    <th class="text-end">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $connections = (int)$serverStatus['Connections'];
                                $failedAttempts = (int)$serverStatus['Aborted_connects'];
                                $abortedClients = (int)$serverStatus['Aborted_clients'];
                                ?>
                                <tr>
                                    <td class="fw-medium text-warning">Failed Attempts</td>
                                    <td class="text-end"><?= number_format($failedAttempts) ?></td>
                                    <td class="text-end"><?= number_format($failedAttempts * 3600 / (float)$serverStatus['Uptime'], 2) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-warning text-dark">
                                            <?= $connections > 0 ? number_format($failedAttempts * 100 / $connections, 2) . '%' : '---' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-medium text-danger">Aborted Clients</td>
                                    <td class="text-end"><?= number_format($abortedClients) ?></td>
                                    <td class="text-end"><?= number_format($abortedClients * 3600 / (float)$serverStatus['Uptime'], 2) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-danger text-white">
                                            <?= $connections > 0 ? number_format($abortedClients * 100 / $connections, 2) . '%' : '---' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr class="table-success">
                                    <td class="fw-medium">Total Connections</td>
                                    <td class="text-end"><?= number_format($connections) ?></td>
                                    <td class="text-end"><?= number_format($connections * 3600 / (float)$serverStatus['Uptime'], 2) ?></td>
                                    <td class="text-end"><span class="badge bg-success">100%</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Query Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-speedometer2 me-2"></i>
                        <span class="fw-semibold">Query Statistics</span>
                    </div>
                    <span class="badge bg-light text-dark fs-6">
                        <?= number_format((int)$serverStatus['Questions']) ?> total queries
                    </span>
                </div>
                <div class="card-body">
                    <!-- Query Rates -->
                    <div class="row mb-4">
                        <div class="col-md-8 mx-auto">
                            <div class="card bg-light border-0">
                                <div class="card-body py-3">
                                    <div class="row text-center">
                                        <?php 
                                        $questions = (int)$serverStatus['Questions'];
                                        $uptime = (float)$serverStatus['Uptime'];
                                        ?>
                                        <div class="col-3">
                                            <div class="fw-bold text-primary fs-4"><?= number_format($questions) ?></div>
                                            <small class="text-muted">Total Queries</small>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold text-success fs-4"><?= number_format($questions * 3600 / $uptime, 1) ?></div>
                                            <small class="text-muted">Per Hour</small>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold text-warning fs-4"><?= number_format($questions * 60 / $uptime, 1) ?></div>
                                            <small class="text-muted">Per Minute</small>
                                        </div>
                                        <div class="col-3">
                                            <div class="fw-bold text-danger fs-4"><?= number_format($questions / $uptime, 2) ?></div>
                                            <small class="text-muted">Per Second</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Query Types -->
                    <div class="row">
                        <?php 
                        // ИСПРАВЛЕННАЯ СТРОКА - приведение к integer
                        $queryChunks = array_chunk($queryStats, (int)ceil(count($queryStats) / 2), true);
                        foreach ($queryChunks as $chunk): 
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Query Type</th>
                                            <th class="text-end">Count</th>
                                            <th class="text-end">Per Hour</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($chunk as $name => $value): ?>
                                        <tr>
                                            <td class="small"><?= htmlspecialchars($name) ?></td>
                                            <td class="text-end fw-medium"><?= number_format($value) ?></td>
                                            <td class="text-end text-muted small"><?= number_format($value * 3600 / $uptime, 2) ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-secondary">
                                                    <?= number_format($value * 100 / ($questions - $connections), 2) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Status Variables -->
    <?php
    // Remove already displayed variables
    $displayedVars = ['Aborted_clients', 'Aborted_connects', 'Bytes_received', 'Bytes_sent', 'Connections', 'Questions', 'Uptime'];
    foreach ($displayedVars as $var) {
        unset($serverStatus[$var]);
    }
    
    if (!empty($serverStatus)):
    ?>
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white d-flex align-items-center">
                    <i class="bi bi-gear-fill me-2"></i>
                    <span class="fw-semibold">Additional Status Variables</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        // ИСПРАВЛЕННАЯ СТРОКА - приведение к integer
                        $statusChunks = array_chunk($serverStatus, (int)ceil(count($serverStatus) / 3), true);
                        foreach ($statusChunks as $chunk): 
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        <?php foreach ($chunk as $name => $value): ?>
                                        <tr class="border-bottom">
                                            <td class="small text-muted"><?= htmlspecialchars(str_replace('_', ' ', $name)) ?></td>
                                            <td class="text-end fw-medium">
                                                <span class="badge bg-light text-dark"><?= htmlspecialchars((string)$value) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif ?>
</div>

<style>
.card {
    border-radius: 12px;
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.85rem;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    font-size: 0.95rem;
}
</style>

<?php
stdfoot();
?>