<?php


class ServerInfoDisplay {
    private array $config;
    private $db;
    private string $charset;
    private string $siteName;
    private string $baseUrl;

    public function __construct(array $config, $db, string $charset, string $siteName, string $baseUrl) {
        $this->config = $config;
        $this->db = $db;
        $this->charset = $charset;
        $this->siteName = $siteName;
        $this->baseUrl = $baseUrl;
    }

    public function display(): void {
        $this->renderHeader();
        $this->renderNavigation();
        $this->renderGeneralInfo();
        $this->renderPhpInfo();
        $this->renderMysqlInfo();
        $this->renderFooter();
    }

    private function renderHeader(): void {
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="' . htmlspecialchars($this->charset) . '">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($this->siteName) . ' - Server Info</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4cc9f0;
            --info-color: #7209b7;
            --warning-color: #f72585;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 1.25rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .table-info {
            --bs-table-bg: rgba(67, 97, 238, 0.05);
            --bs-table-striped-bg: rgba(67, 97, 238, 0.1);
            --bs-table-hover-bg: rgba(67, 97, 238, 0.15);
        }
        
        .badge-server {
            font-size: 0.75rem;
            padding: 0.4em 0.8em;
            border-radius: 20px;
        }
        
        .nav-pills .nav-link {
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .server-load {
            height: 10px;
            border-radius: 5px;
            background: #e9ecef;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .load-bar {
            height: 100%;
            background: linear-gradient(90deg, #4cc9f0, #4361ee);
            border-radius: 5px;
        }
        
        pre {
            background: var(--dark-bg);
            color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            font-size: 0.9rem;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .info-section {
            display: none;
        }
        
        .info-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .metric-badge {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-server me-2"></i>
                        Server Information Dashboard
                    </h1>
                    <p class="text-muted mb-0">Comprehensive server diagnostics and monitoring</p>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">v2.0</span>
                    <span class="text-muted small">' . date('Y-m-d H:i:s') . '</span>
                </div>
            </div>
        </div>
    </div>';
    }

    private function renderNavigation(): void {
        echo '<div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-pills justify-content-center" id="serverInfoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="pill" 
                                    data-bs-target="#general" type="button" role="tab">
                                <i class="bi bi-speedometer2 me-2"></i>General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="php-tab" data-bs-toggle="pill" 
                                    data-bs-target="#php" type="button" role="tab">
                                <i class="bi bi-file-code me-2"></i>PHP
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mysql-tab" data-bs-toggle="pill" 
                                    data-bs-target="#mysql" type="button" role="tab">
                                <i class="bi bi-database me-2"></i>MySQL
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>';
    }

    private function renderGeneralInfo(): void {
        $sqlVersion = $this->getSqlVersion();
        $dataUsage = $this->getDatabaseUsage('Data_length');
        $indexUsage = $this->getDatabaseUsage('Index_length');
        $packetMax = $this->getMysqlVariable('max_allowed_packet');
        $connectionMax = $this->getMysqlVariable('max_connections');
        
        echo '<div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="row">
            <!-- Quick Stats -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-value">' . PHP_VERSION . '</div>
                    <div class="stat-label">PHP Version</div>
                    <i class="bi bi-file-code float-end" style="font-size: 2rem; opacity: 0.5;"></i>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-value">' . htmlspecialchars($sqlVersion) . '</div>
                    <div class="stat-label">MySQL Version</div>
                    <i class="bi bi-database float-end" style="font-size: 2rem; opacity: 0.5;"></i>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-value">' . $this->formatSize($dataUsage + $indexUsage) . '</div>
                    <div class="stat-label">Database Size</div>
                    <i class="bi bi-hdd float-end" style="font-size: 2rem; opacity: 0.5;"></i>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-value">' . $this->getServerLoad() . '</div>
                    <div class="stat-label">Server Load</div>
                    <i class="bi bi-cpu float-end" style="font-size: 2rem; opacity: 0.5;"></i>
                </div>
            </div>
        </div>

        <!-- Detailed Information -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>Detailed Server Information
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-info">
                                <tbody>';
        
        $this->renderInfoRow('Operating System', PHP_OS, 'Server Software', $_SERVER['SERVER_SOFTWARE']);
        $this->renderInfoRow('Server Hostname', $_SERVER['SERVER_NAME'], 'Server IP:Port', $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT']);
        $this->renderInfoRow('Document Root', $_SERVER['DOCUMENT_ROOT'], 'Server Admin', $_SERVER['SERVER_ADMIN']);
        $this->renderInfoRow('Server Date/Time', date('l, F j, Y H:i:s'), 'Server Load', $this->getServerLoad());
        $this->renderInfoRow('PHP Memory Limit', ini_get('memory_limit'), 'Max Upload Size', ini_get('upload_max_filesize'));
        $this->renderInfoRow('Max Post Size', ini_get('post_max_size'), 'Max Execution Time', ini_get('max_execution_time') . 's');
        $this->renderInfoRow('Short Open Tag', ini_get('short_open_tag') ? 'On' : 'Off', 'Safe Mode', ini_get('safe_mode') ? 'On' : 'Off');
        $this->renderInfoRow('Database Data', $this->formatSize($dataUsage), 'Database Index', $this->formatSize($indexUsage));
        $this->renderInfoRow('Max Packet Size', $this->formatSize($packetMax), 'Max Connections', number_format($connectionMax));
        
        echo '                      </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    }

    private function renderPhpInfo(): void {
        ob_start();
        phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES | INFO_VARIABLES);
        $phpinfo = ob_get_clean();
        
        echo '<div class="tab-pane fade" id="php" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-file-code me-2"></i>PHP Configuration
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            ' . $this->cleanPhpInfo($phpinfo) . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    }

    private function renderMysqlInfo(): void {
        $variables = $this->getMysqlVariables();
        
        echo '<div class="tab-pane fade" id="mysql" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-database me-2"></i>MySQL Server Variables
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Variable Name</th>
                                        <th>Value</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>';
        
        foreach ($variables as $name => $value) {
            echo '<tr>
                <td><code>' . htmlspecialchars($name) . '</code></td>
                <td><span class="badge metric-badge">' . htmlspecialchars($value) . '</span></td>
                <td><small class="text-muted">' . $this->getMysqlVarDescription($name) . '</small></td>
            </tr>';
        }
        
        echo '              </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    }

    private function renderFooter(): void {
        echo '</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Tab persistence
document.addEventListener(\'DOMContentLoaded\', function() {
    const tabs = document.querySelectorAll(\'#serverInfoTabs .nav-link\');
    tabs.forEach(tab => {
        tab.addEventListener(\'click\', function() {
            localStorage.setItem(\'activeServerInfoTab\', this.id);
        });
    });
    
    const activeTab = localStorage.getItem(\'activeServerInfoTab\');
    if (activeTab) {
        const tabElement = document.getElementById(activeTab);
        if (tabElement) {
            new bootstrap.Tab(tabElement).show();
        }
    }
});
</script>
</body>
</html>';
    }

    private function getSqlVersion(): string {
        $result = $this->db->sql_query('SELECT VERSION() as version');
        $row = mysqli_fetch_assoc($result);
        return $row['version'] ?? 'Unknown';
    }

    private function getDatabaseUsage(string $column): int {
        $usage = 0;
        $result = $this->db->sql_query("SHOW TABLE STATUS FROM `{$this->config['database']['database']}`");
        
        while ($row = mysqli_fetch_assoc($result)) {
            $usage += (int)($row[$column] ?? 0);
        }
        
        return $usage;
    }

    private function getMysqlVariable(string $variable): string {
        $result = $this->db->sql_query("SHOW VARIABLES LIKE '{$variable}'");
        $row = mysqli_fetch_assoc($result);
        return $row['Value'] ?? 'N/A';
    }

    private function getMysqlVariables(): array {
        $variables = [];
        $result = $this->db->sql_query('SHOW VARIABLES');
        
        while ($row = mysqli_fetch_assoc($result)) {
            $variables[$row['Variable_name']] = $row['Value'];
        }
        
        return $variables;
    }

    private function getServerLoad(): string {
        if (PHP_OS === 'Linux' || PHP_OS === 'Unix') {
            if (file_exists('/proc/loadavg')) {
                $load = file_get_contents('/proc/loadavg');
                $loads = explode(' ', $load);
                return trim($loads[0] ?? 'N/A');
            }
            
            $output = shell_exec('uptime');
            if (preg_match('/load average:\s+([\d\.]+)/', $output, $matches)) {
                return $matches[1];
            }
        }
        
        return 'N/A';
    }

    private function formatSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function renderInfoRow(string $label1, string $value1, string $label2, string $value2): void {
        echo '<tr>
            <td><strong>' . htmlspecialchars($label1) . '</strong></td>
            <td><span class="badge bg-light text-dark">' . htmlspecialchars($value1) . '</span></td>
            <td><strong>' . htmlspecialchars($label2) . '</strong></td>
            <td><span class="badge bg-light text-dark">' . htmlspecialchars($value2) . '</span></td>
        </tr>';
    }

    private function cleanPhpInfo(string $phpinfo): string {
        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
        $phpinfo = str_replace('<table', '<table class="table table-hover table-bordered"', $phpinfo);
        $phpinfo = str_replace('<td class="e"', '<td class="fw-bold"', $phpinfo);
        $phpinfo = str_replace('<td class="v"', '<td', $phpinfo);
        $phpinfo = str_replace('<tr class="h"', '<tr class="table-primary"', $phpinfo);
        $phpinfo = str_replace('<tr class="v"', '<tr class="table-default"', $phpinfo);
        
        return $phpinfo;
    }

    private function getMysqlVarDescription(string $variable): string {
        $descriptions = [
            'max_connections' => 'Maximum number of simultaneous client connections',
            'max_allowed_packet' => 'Maximum size of one packet or generated/intermediate string',
            'innodb_buffer_pool_size' => 'Size of the memory buffer InnoDB uses to cache data and indexes',
            'query_cache_size' => 'Amount of memory allocated for caching query results',
            'thread_cache_size' => 'How many threads the server should cache for reuse',
            'key_buffer_size' => 'Size of the buffer used for index blocks'
        ];
        
        return $descriptions[$variable] ?? 'MySQL server variable';
    }
}

// Usage:
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger">Error! Direct initialization of this file is not allowed.</div>');
}

try {
    $serverInfo = new ServerInfoDisplay($config, $db, $charset, $SITENAME, $BASEURL);
    $serverInfo->display();
} catch (Exception $e) {
    echo '<div class="alert alert-danger">
        <h4>Error Loading Server Information</h4>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>';
}
?>