<?php

declare(strict_types=1);

function getFileIcon(string $filename): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    return match($extension) {
        'php' => 'fab fa-php text-primary',
        'js' => 'fab fa-js-square text-warning',
        'css' => 'fab fa-css3-alt text-info',
        'html' => 'fab fa-html5 text-danger',
        'sql' => 'fas fa-database text-secondary',
        'json' => 'fas fa-code text-success',
        'xml' => 'fas fa-file-code text-info',
        default => 'fas fa-file text-muted'
    };
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1024 ** $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/** Server load, or '' when it cannot be read (the caller prints "Unknown" in the user's language) */
function server_load(): string
{
    return str_starts_with(strtolower(PHP_OS), 'win')
        ? get_windows_server_load()
        : get_unix_server_load();
}

function get_windows_server_load(): string
{
    if (!class_exists('COM')) {
        return '';
    }

    try {
        $wmi = new COM('WinMgmts:\\\\.');
        $cpus = $wmi->InstancesOf('Win32_Processor');

        $cpuload = 0;
        $i = 0;

        foreach ($cpus as $cpu) {
            $cpuload += $cpu->LoadPercentage;
            $i++;
        }

        return $i > 0 ? round($cpuload / $i, 2) . '%' : '';
    } catch (Exception) {
        return '';
    }
}

function get_unix_server_load(): string
{
    if (file_exists('/proc/loadavg')) {
        $load = file_get_contents('/proc/loadavg') ?: '';
        $serverload = explode(' ', $load);
        $loadValue = round((float)($serverload[0] ?? 0), 4);
    } else {
        $load = exec('uptime') ?: '';
        preg_match('/load averages?: ([\d.]+)/', $load, $matches);
        $loadValue = isset($matches[1]) ? round((float)$matches[1], 4) : 0;
    }

    return $loadValue > 0 ? (string)$loadValue : '';
}

function calctime(float $time): string
{
    global $lang;

    $stat = round($time * 100, 3);
    $val = sprintf('%.4f', $time);

    return match (true) {
        $stat <= 40  => $val . ' <span class="badge bg-success">' . hsafe($lang->query_explain['speed_excellent'] ?? 'Excellent') . '</span>',
        $stat <= 70  => $val . ' <span class="badge bg-success-subtle text-success border">' . hsafe($lang->query_explain['speed_good'] ?? 'Good') . '</span>',
        $stat <= 98  => $val . ' <span class="badge bg-warning text-dark">' . hsafe($lang->query_explain['speed_regular'] ?? 'Regular') . '</span>',
        default      => $val . ' <span class="badge bg-danger">' . hsafe($lang->query_explain['speed_bad'] ?? 'Bad') . '</span>'
    };
}

function hsafe(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function has_unquoted_semicolon(?string $sql): bool
{
    if (empty($sql)) {
        return false;
    }

    $inSingle = false;
    $inDouble = false;

    for ($i = 0; $i < strlen($sql); $i++) {
        $ch = $sql[$i];

        if ($inSingle) {
            if ($ch === "'") {
                if ($i + 1 < strlen($sql) && $sql[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                $inSingle = false;
            } elseif ($ch === "\\") {
                $i++;
            }
            continue;
        }

        if ($inDouble) {
            if ($ch === '"') {
                if ($i + 1 < strlen($sql) && $sql[$i + 1] === '"') {
                    $i++;
                    continue;
                }
                $inDouble = false;
            } elseif ($ch === "\\") {
                $i++;
            }
            continue;
        }

        if ($ch === "'") {
            $inSingle = true;
            continue;
        }

        if ($ch === '"') {
            $inDouble = true;
            continue;
        }

        if ($ch === ';') {
            return true;
        }
    }

    return false;
}

function sql_strip_leading_comments(string $sql): string
{
    $result = ltrim($sql);

    while (preg_match('/^\/\*.*?\*\//s', $result, $matches)) {
        $result = ltrim(substr($result, strlen($matches[0])));
    }

    while (preg_match('/^(?:--|#)[^\r\n]*(?:\r?\n|$)/', $result, $matches)) {
        $result = ltrim(substr($result, strlen($matches[0])));
    }

    return $result;
}

function sql_is_select(string $sql): bool
{
    $lead = sql_strip_leading_comments($sql);
    return (bool) preg_match('/^\s*(SELECT|WITH)\b/i', $lead);
}

function format_sql_with_syntax(string $sql): string
{
    return hsafe($sql);
}

function format_explain_value(string $column, mixed $value): string
{
    $value = hsafe($value);

    if ($column === 'type') {
        return match (true) {
            str_contains($value, 'ref') => '<span class="badge bg-success badge-sm">' . $value . '</span>',
            $value === 'ALL' => '<span class="badge bg-danger badge-sm">' . $value . '</span>',
            default => '<span class="badge bg-warning badge-sm">' . $value . '</span>'
        };
    }

    if ($column === 'Extra') {
        return preg_replace('/Using (\w+)/', '<span class="badge bg-info badge-sm">Using $1</span>', $value);
    }

    return $value;
}

/** MySQL version, or '' when it cannot be read */
function get_mysql_version(): string
{
    global $db;
    $link = get_database_link($db);
    if (!$link) return '';

    $result = mysqli_query($link, "SELECT VERSION() as version");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return (string)$row['version'];
    }
    return '';
}

function get_php_extensions_status(): string
{
    global $lang;

    $importantExtensions = ['mysqli', 'pdo_mysql', 'json', 'mbstring', 'xml', 'curl'];
    $extensionsHtml = '';

    foreach ($importantExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $status = $loaded ? 'bg-success' : 'bg-danger';
        $text   = $loaded
            ? ($lang->query_explain['ext_loaded'] ?? 'Loaded')
            : ($lang->query_explain['ext_missing'] ?? 'Missing');
        $extensionsHtml .= '<span class="badge ' . $status . ' me-1 mb-1">' . $ext . ': ' . hsafe($text) . '</span>';
    }

    return $extensionsHtml;
}

function render_enhanced_stats(int $printed, int $skipped, float $queryTime, float $totalTime, string $memoryUsage, bool $deep): string
{
    global $lang;

    $phpTime = $totalTime - $queryTime;
    $percentPhp = $totalTime > 0 ? number_format(($phpTime / $totalTime) * 100, 2) : '0.00';
    $percentSql = $totalTime > 0 ? number_format(($queryTime / $totalTime) * 100, 2) : '0.00';

    $unknown = $lang->query_explain['unknown'] ?? 'Unknown';

    $memoryPeak = function_exists('memory_get_peak_usage')
        ? mksize(memory_get_peak_usage())
        : $unknown;

    $mysqlVersion  = get_mysql_version() ?: $unknown;
    $serverLoad    = server_load() ?: $unknown;
    $phpExtensions = get_php_extensions_status();

    $gzipHtml = ($GLOBALS['gzipcompress'] ?? '') === 'yes'
        ? '<span class="badge bg-success">' . hsafe($lang->query_explain['enabled'] ?? 'Enabled') . '</span>'
        : '<span class="badge bg-secondary">' . hsafe($lang->query_explain['disabled'] ?? 'Disabled') . '</span>';

    return '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light text-dark">
                <h5 class="mb-0">' . hsafe($lang->query_explain['stats_title'] ?? 'Performance Statistics') . '</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="performance-chart mb-4">
                            <h6>' . hsafe($lang->query_explain['time_distribution'] ?? 'Time Distribution') . '</h6>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" style="width: ' . $percentPhp . '%"
                                     title="PHP: ' . $percentPhp . '%">
                                    PHP: ' . $percentPhp . '%
                                </div>
                                <div class="progress-bar bg-info" style="width: ' . $percentSql . '%"
                                     title="MySQL: ' . $percentSql . '%">
                                    MySQL: ' . $percentSql . '%
                                </div>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-label">' . hsafe($lang->query_explain['total_queries'] ?? 'Total Queries:') . '</span>
                                <span class="stat-value badge bg-primary">' . ($printed + $skipped) . '</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">' . hsafe($lang->query_explain['displayed'] ?? 'Displayed:') . '</span>
                                <span class="stat-value badge bg-success">' . $printed . '</span>
                            </div>
                            ' . ($deep && $skipped > 0 ? '
                            <div class="stat-item">
                                <span class="stat-label">' . hsafe($lang->query_explain['hidden'] ?? 'Hidden:') . '</span>
                                <span class="stat-value badge bg-warning">' . $skipped . '</span>
                            </div>' : '') . '
                            <div class="stat-item">
                                <span class="stat-label">' . hsafe($lang->query_explain['total_time'] ?? 'Total Time:') . '</span>
                                <span class="stat-value badge bg-secondary">' . sprintf('%.4fs', $totalTime) . '</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="system-info">
                            <h6>' . hsafe($lang->query_explain['system_info'] ?? 'System Information') . '</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['php_version'] ?? 'PHP Version:') . '</strong></td>
                                    <td>' . hsafe(PHP_VERSION) . '</td>
                                </tr>
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['mysql_version'] ?? 'MySQL Version:') . '</strong></td>
                                    <td>' . hsafe($mysqlVersion) . '</td>
                                </tr>
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['server_load'] ?? 'Server Load:') . '</strong></td>
                                    <td>' . hsafe($serverLoad) . '</td>
                                </tr>
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['memory_usage'] ?? 'Memory Usage:') . '</strong></td>
                                    <td>' . hsafe($memoryUsage ?: $unknown) . '</td>
                                </tr>
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['memory_peak'] ?? 'Peak Memory:') . '</strong></td>
                                    <td>' . hsafe($memoryPeak) . '</td>
                                </tr>
                                <tr>
                                    <td><strong>' . hsafe($lang->query_explain['gzip'] ?? 'GZip Compression:') . '</strong></td>
                                    <td>' . $gzipHtml . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <h6>' . hsafe($lang->query_explain['php_extensions'] ?? 'PHP Extensions') . '</h6>
                    <div>' . $phpExtensions . '</div>
                </div>
            </div>
        </div>
    </div>';
}

function render_performance_summary(array $stats): string
{
    global $lang;

    $fastPercent = $stats['total_queries'] > 0
        ? round(($stats['fast'] / $stats['total_queries']) * 100, 1)
        : 0;
    $mediumPercent = $stats['total_queries'] > 0
        ? round(($stats['medium'] / $stats['total_queries']) * 100, 1)
        : 0;
    $slowPercent = $stats['total_queries'] > 0
        ? round(($stats['slow'] / $stats['total_queries']) * 100, 1)
        : 0;

    return '
    <div class="container mt-3">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">' . hsafe($lang->query_explain['summary_title'] ?? 'Performance Summary') . '</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="text-success">
                            <h4>' . $stats['fast'] . '</h4>
                            <small>' . hsafe($lang->query_explain['fast_queries'] ?? 'Fast Queries (<10ms)') . '</small>
                            <div class="mt-1">' . $fastPercent . '%</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-warning">
                            <h4>' . $stats['medium'] . '</h4>
                            <small>' . hsafe($lang->query_explain['medium_queries'] ?? 'Medium Queries (10-100ms)') . '</small>
                            <div class="mt-1">' . $mediumPercent . '%</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-danger">
                            <h4>' . $stats['slow'] . '</h4>
                            <small>' . hsafe($lang->query_explain['slow_queries'] ?? 'Slow Queries (>100ms)') . '</small>
                            <div class="mt-1">' . $slowPercent . '%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function render_custom_styles(): string
{
    return '
    <style>
    .sql-keyword { color: #d73a49; font-weight: bold; }
    .sql-string { color: #032f62; }
    .sql-number { color: #005cc5; }
    .sql-comment { color: #6a737d; font-style: italic; }

    .performance-chart .progress { background: #e9ecef; }
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .stat-item { display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0; }
    .stat-label { font-size: 0.875rem; }
    .stat-value { font-size: 0.75rem; }

    .bg-orange { background-color: #fd7e14 !important; }

    .query-card { transition: transform 0.2s; }
    .query-card:hover { transform: translateY(-2px); }

    .badge-sm { font-size: 0.7em; }

    .sql-code {
        background: #f8f9fa !important;
        padding: 1rem !important;
        border-radius: 0.375rem !important;
        border: 1px solid #dee2e6 !important;
    }
    </style>';
}

function explain_query(string $sql, ?float $execTime = null, bool $deep = false, int $id = 1): string
{
    global $db, $lang;

    $link = get_database_link($db);
    if (!$link) {
        return '<div class="alert alert-danger">' . hsafe($lang->query_explain['err_no_db'] ?? 'No database connection') . '</div>';
    }

    $timeHtml = calctime((float) $execTime);
    $sqlClean = normalize_sql($sql);

    if ($sqlClean === '') {
        return '<div class="alert alert-warning">' . hsafe($lang->query_explain['err_empty_query'] ?? 'Empty query.') . '</div>';
    }

    if (has_unquoted_semicolon($sqlClean)) {
        return render_multiple_statements_error($sql);
    }

    $isSelect = sql_is_select($sqlClean);
    $queryType = get_query_type($sqlClean);

    if ($deep && $isSelect) {
        $deepResult = explain_deep_analysis($link, $sqlClean, $sql, $timeHtml, $id);
        if (!empty($deepResult)) {
            return $deepResult;
        }
    }

    return $isSelect
        ? explain_select_query($link, $sqlClean, $sql, $timeHtml, $id)
        : explain_write_query($link, $sqlClean, $sql, $timeHtml, $id, $queryType);
}

function get_database_link(mixed $db): ?mysqli
{
    return $db->current_link
        ?? $db->write_link
        ?? $db->read_link
        ?? null;
}

function normalize_sql(string $sql): string
{
    $clean = rtrim($sql);
    return preg_replace('/;+\s*$/', '', $clean) ?? $clean;
}

function render_multiple_statements_error(string $sql): string
{
    global $lang;

    return '<div class="alert alert-danger">' . hsafe($lang->query_explain['err_multi_statements'] ?? 'Multiple statements are not allowed.') . '</div>'
         . '<div class="card-body"><pre>' . hsafe($sql) . '</pre></div>';
}

function get_query_type(string $sql): string
{
    $lead = sql_strip_leading_comments($sql);
    preg_match('/^\s*([a-zA-Z]+)/', $lead, $matches);
    return strtoupper($matches[1] ?? 'UNKNOWN');
}

function explain_deep_analysis(mysqli $link, string $sqlClean, string $sql, string $timeHtml, int $id): string
{
    @mysqli_query($link, "SET SESSION MAX_EXECUTION_TIME=3000");

    $sqlHint = preg_replace('/^\s*select\b/i', 'SELECT /*+ MAX_EXECUTION_TIME(3000) */', $sqlClean, 1);
    $result = @mysqli_query($link, "EXPLAIN ANALYZE $sqlHint");

    if ($result === false) {
        return '';
    }

    $lines = [];
    while ($row = mysqli_fetch_array($result)) {
        $lines[] = (string) ($row[0] ?? $row['EXPLAIN'] ?? '');
    }

    $analysis = trim(implode("\n", $lines));
    $warnings = fetch_warnings($link);

    return render_deep_analysis_result($id, $sql, $analysis, $warnings, $timeHtml);
}

function fetch_warnings(mysqli $link): string
{
    $result = @mysqli_query($link, "SHOW WARNINGS");
    if (!$result) {
        return '';
    }

    $warnings = '';
    while ($warning = mysqli_fetch_assoc($result)) {
        $warnings .= sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
            hsafe($warning['Level'] ?? ''),
            hsafe($warning['Code'] ?? ''),
            hsafe($warning['Message'] ?? '')
        );
    }

    return $warnings;
}

function render_deep_analysis_result(int $id, string $sql, string $analysis, string $warnings, string $timeHtml): string
{
    global $lang;

    $warningsHtml = '';
    if (!empty($warnings)) {
        $warningsHtml = '
        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered">
                <thead><tr>'
                    . '<th>' . hsafe($lang->query_explain['warn_level'] ?? 'Level') . '</th>'
                    . '<th>' . hsafe($lang->query_explain['warn_code'] ?? 'Code') . '</th>'
                    . '<th>' . hsafe($lang->query_explain['warn_message'] ?? 'Message') . '</th>'
                . '</tr></thead>
                <tbody>' . $warnings . '</tbody>
            </table>
        </div>';
    }

    $formattedSql = format_sql_with_syntax($sql);

    return '
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>' . hsafe(sprintf($lang->query_explain['hdr_deep'] ?? '#%1$s - Deep Plan (EXPLAIN ANALYZE)', $id)) . '</strong>
                <div class="badge bg-light text-dark">' . hsafe($lang->query_explain['badge_deep'] ?? 'Deep Analysis') . '</div>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-2"><pre class="sql-code">' . $formattedSql . '</pre></div>
            <pre class="mb-0" style="white-space:pre-wrap">' . hsafe($analysis) . '</pre>
            ' . $warningsHtml . '
        </div>
        <div class="card-footer">' . hsafe($lang->query_explain['measured_time'] ?? 'Measured time:') . ' ' . $timeHtml
            . ' • ' . hsafe($lang->query_explain['executed_analyze'] ?? 'Executed with ANALYZE') . '</div>';
}

function explain_select_query(mysqli $link, string $sqlClean, string $sql, string $timeHtml, int $id): string
{
    $result = mysqli_query($link, "EXPLAIN FORMAT=TRADITIONAL $sqlClean");

    if ($result === false) {
        return render_read_query($id, $sql, $timeHtml, mysqli_error($link));
    }

    $rows = [];
    $columns = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
        $columns = array_merge($columns, array_keys($row));
    }

    $columns = array_unique($columns);
    $preferred = ['id', 'select_type', 'table', 'partitions', 'type', 'possible_keys', 'key', 'key_len', 'ref', 'rows', 'filtered', 'Extra'];
    $orderedColumns = array_values(array_unique([...$preferred, ...$columns]));

    return render_explain_table($id, $sql, $timeHtml, $rows, $orderedColumns);
}

function render_explain_table(int $id, string $sql, string $timeHtml, array $rows, array $columns): string
{
    global $lang;

    $headers = implode('', array_map(fn($col) => '<th>' . hsafe($col) . '</th>', $columns));

    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr>';
        foreach ($columns as $col) {
            $value = $row[$col] ?? '';
            $formattedValue = format_explain_value((string)$col, $value);
            $body .= '<td>' . $formattedValue . '</td>';
        }
        $body .= '</tr>';
    }

    $formattedSql = format_sql_with_syntax($sql);

    return '
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>' . hsafe(sprintf($lang->query_explain['hdr_select'] ?? '#%1$s - Select Query', $id)) . '</strong>
                <div class="badge bg-light text-dark">' . hsafe(sprintf($lang->query_explain['explain_rows'] ?? '%1$s row(s) in explain', count($rows))) . '</div>
            </div>
        </div>
        <div class="card-body">
            ' . $formattedSql . '
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-light"><tr>' . $headers . '</tr></thead>
                <tbody>' . $body . '</tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span>' . $timeHtml . '</span>
            <small class="text-muted">' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>
        </div>';
}

function render_read_query(int $id, string $sql, string $timeHtml, string $error = ''): string
{
    global $lang;

    $errorHtml = $error
        ? '<div class="alert alert-warning mt-2">' . hsafe($lang->query_explain['err_explain_failed'] ?? 'EXPLAIN failed:') . ' ' . hsafe($error) . '</div>'
        : '';
    $formattedSql = format_sql_with_syntax($sql);

    return '
        <div class="card-header bg-secondary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>' . hsafe(sprintf($lang->query_explain['hdr_read'] ?? '#%1$s - Read Query', $id)) . '</strong>
                <div class="badge bg-light text-dark">' . hsafe($lang->query_explain['badge_read_only'] ?? 'Read Only') . '</div>
            </div>
        </div>
        <div class="card-body">
            <pre class="sql-code">' . $formattedSql . '</pre>' . $errorHtml . '
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span>' . $timeHtml . '</span>
            <small class="text-muted">' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>
        </div>';
}

function explain_write_query(mysqli $link, string $sqlClean, string $sql, string $timeHtml, int $id, string $queryType): string
{
    global $lang;

    $affected = @mysqli_affected_rows($link);
    $insertId = @mysqli_insert_id($link);

    $meta = [];
    if ($affected >= 0) {
        $meta[] = sprintf($lang->query_explain['affected_rows'] ?? 'Affected rows: %1$s', $affected);
    }
    if ($insertId > 0) {
        $meta[] = sprintf($lang->query_explain['insert_id'] ?? 'Insert ID: %1$s', $insertId);
    }

    $metaHtml = $meta ? ' • ' . hsafe(implode(' | ', $meta)) : '';
    $formattedSql = format_sql_with_syntax($sql);

    $headerClass = match ($queryType) {
        'INSERT' => 'bg-success',
        'UPDATE' => 'bg-warning',
        'DELETE' => 'bg-danger',
        default => 'bg-info'
    };

    return '
        <div class="card-header ' . $headerClass . ' text-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>' . hsafe(sprintf($lang->query_explain['hdr_write'] ?? '#%1$s - %2$s Query', $id, $queryType)) . '</strong>
                <div class="badge bg-light text-dark">' . hsafe($lang->query_explain['badge_write'] ?? 'Write Operation') . '</div>
            </div>
        </div>
        <div class="card-body">
            ' . $formattedSql . '
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span>' . $timeHtml . $metaHtml . '</span>
            <small class="text-muted">' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>
        </div>';
}

function splitsql(string $sql): string
{
    $patterns = [
        '/\bstraight_join\b/i' => '<b>STRAIGHT_JOIN</b>',
        '/\bjoin\b/i'          => '<b>JOIN</b>',
        '/\bselect\b/i'        => '<b>SELECT</b>',
        '/\bdelete\b/i'        => '<b>DELETE</b>',
        '/\bupdate\b/i'        => '<b>UPDATE</b>',
        '/\bfrom\b/i'          => '<br><b>FROM</b>',
        '/\bwhere\b/i'         => '<br><b>WHERE</b>',
        '/\bgroup\s+by\b/i'    => '<br><b>GROUP BY</b>',
        '/\bhaving\b/i'        => '<br><b>HAVING</b>',
        '/\border\s+by\b/i'    => '<br><b>ORDER BY</b>',
    ];

    return preg_replace(array_keys($patterns), array_values($patterns), strtolower($sql));
}

// Initialize application
$rootpath = './../';
define('TQE_VERSION', '0.9');
define('DEBUGMODE', false);
define('IN_MYBB', 1);

require_once $rootpath . 'global.php';

if (!defined('APP_INITIALIZED')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('query_explain');

gzip();
maxsysop();

if ((int)($usergroups['cansettingspanel'] ?? 0) !== 1) {
    print_no_permission(true);
}

// CSRF: без этой проверки EXPLAIN ANALYZE (реально выполняющий переданный
// SQL-текст против живой БД) можно было бы спровоцировать подделанным
// POST-запросом от имени залогиненного sysop-а.
//
// $mybb->post_code (и, соответственно, токен в форме) считается ОДИН РАЗ
// внутри global.php — до того, как конкретная страница (если вообще делает
// это) успевает сама объявить IN_ADMINCP. Из-за этого нельзя надёжно узнать
// заранее, в каком состоянии была константа в момент реального вычисления
// токена — это зависит от конкретного файла и порядка define()/require_once
// в нём. Поэтому не гадаем через флаг, а пробуем оба варианта явно:
// сначала БЕЗ IN_ADMINCP (обязательно первым — константу нельзя "отменить"
// после define()), и только если не совпало — определяем и пробуем снова.
global $mybb;
$submitted_token = $mybb->get_input('my_post_key');
$token_valid = verify_post_check($submitted_token, true);

if (!$token_valid && !defined('IN_ADMINCP')) {
    define('IN_ADMINCP', 1);
    $token_valid = verify_post_check($submitted_token, true);
}

if (!$token_valid) {
    http_response_code(403);
    exit('<div class="alert alert-danger">'
        . hsafe($lang->query_explain['err_security'] ?? 'Invalid security token. Please refresh the page and try again.')
        . '</div>');
}

// Just the size: the label is printed by render_enhanced_stats()
// (before it was " - <b>Memory Usage:</b> 2 MB", so the label showed twice)
$memoryUsage = function_exists('memory_get_usage') ? mksize(memory_get_usage()) : '';

$deep = !empty($_POST['deep']);
$queries = is_array($_POST['queries'] ?? null) ? $_POST['queries'] : [];
$totalTime = (float) ($_POST['totaltime'] ?? 0.0);
$cacheCallsRaw = is_array($_POST['cache_calls'] ?? null) ? $_POST['cache_calls'] : [];

process_queries($queries, $deep, $totalTime, $memoryUsage, $cacheCallsRaw);

function process_queries(array $queries, bool $deep, float $totalTime, string $memoryUsage, array $cacheCallsRaw = []): void
{
    if (empty($queries)) {
        render_no_queries();
        return;
    }

    $output = render_custom_styles();
    $queryId = 1;
    $queryTime = 0.0;
    $printed = 0;
    $skipped = 0;

    $performanceStats = [
        'fast' => 0,
        'medium' => 0,
        'slow' => 0,
        'total_queries' => count($queries)
    ];

    foreach ($queries as $queryData) {
        if (!is_string($queryData)) {
            continue;
        }

        [$execTime, $query] = decode_query_data($queryData);

        if ($query === null) {
            continue;
        }

        if ($execTime <= 0.01) $performanceStats['fast']++;
        elseif ($execTime <= 0.1) $performanceStats['medium']++;
        else $performanceStats['slow']++;

        if ($deep && !sql_is_select($query)) {
            $skipped++;
            continue;
        }

        $html = explain_query($query, $execTime, $deep, $queryId);

        if (empty($html)) {
            $skipped++;
            continue;
        }

        $output .= '
        <div class="container mt-3">
            <div class="card shadow-sm query-card">' . $html . '</div>
        </div>';

        $queryId++;
        $queryTime += $execTime;
        $printed++;
    }

    $output .= render_enhanced_stats($printed, $skipped, $queryTime, $totalTime, $memoryUsage, $deep);
    $output .= render_performance_summary($performanceStats);
    $output .= process_cache_calls($cacheCallsRaw);

    render_final_output($output, $printed, $skipped, $queryTime, $totalTime, $memoryUsage, $deep);
}

function decode_query_data(string $queryData): array
{
    $decoded = base64_decode($queryData, true);
    if ($decoded === false) {
        return [0.0, null];
    }

    $parts = explode(',', $decoded, 2);
    $execTime = isset($parts[0]) ? (float) $parts[0] : 0.0;
    $sql = base64_decode($parts[1] ?? '', true);

    return [$execTime, $sql !== false ? $sql : null];
}

function decode_cache_call_data(string $data): ?array
{
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
        return null;
    }

    $parts = explode(',', $decoded, 4);
    if (count($parts) < 4) {
        return null;
    }

    $verb = base64_decode($parts[2], true);
    $key  = base64_decode($parts[3], true);

    return [
        'time' => (float) $parts[0],
        'hit'  => $parts[1] === '1',
        'verb' => $verb !== false ? $verb : '',
        'key'  => $key !== false ? $key : '',
    ];
}

function render_cache_call(int $id, array $call): string
{
    global $lang;

    // HIT / MISS are standard cache terms — left as they are
    $hitLabel  = $call['hit'] ? 'HIT' : 'MISS';
    $hitClass  = $call['hit'] ? 'bg-success' : 'bg-danger';
    $timeHtml  = '<b>' . hsafe($lang->query_explain['call_time'] ?? 'Call Time:') . '</b> ' . format_time_duration($call['time']);

    return '
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <strong>' . hsafe(sprintf($lang->query_explain['hdr_cache_call'] ?? '#%1$s - %2$s Call', $id, ucfirst($call['verb']))) . '</strong>
                <div class="badge ' . $hitClass . '">' . $hitLabel . '</div>
            </div>
        </div>
        <div class="card-body">
            <code>' . hsafe($call['key']) . '</code>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span>' . $timeHtml . '</span>
            <small class="text-muted">' . hsafe(sprintf($lang->query_explain['call_num'] ?? 'Call #%1$s', $id)) . '</small>
        </div>';
}

function process_cache_calls(array $cacheCallsRaw): string
{
    global $lang;

    if (empty($cacheCallsRaw)) {
        return '';
    }

    $cards     = '';
    $id        = 1;
    $totalTime = 0.0;
    $printed   = 0;

    foreach ($cacheCallsRaw as $raw) {
        $call = decode_cache_call_data((string)$raw);
        if ($call === null) {
            continue;
        }

        $totalTime += $call['time'];

        $cards .= '
        <div class="container mt-3">
            <div class="card shadow-sm query-card">' . render_cache_call($id, $call) . '</div>
        </div>';

        $id++;
        $printed++;
    }

    if ($printed === 0) {
        return '';
    }

    $heading = '
    <div class="container mt-4">
        <h4>' . hsafe(sprintf($lang->query_explain['cache_calls_title'] ?? 'Cache Calls (%1$s Total, %2$s)', $printed, strip_tags((string)format_time_duration($totalTime)))) . '</h4>
    </div>';

    return $heading . $cards;
}

function render_no_queries(): void
{
    global $lang;

    stdhead($lang->query_explain['page_title'] ?? 'DEBUG MODE');
    echo '<div class="container mt-4"><div class="alert alert-warning">'
        . hsafe($lang->query_explain['no_queries'] ?? 'There is no query to show.')
        . '</div></div>';
    stdfoot();
    exit;
}

function render_final_output(string $output, int $printed, int $skipped, float $queryTime, float $totalTime, string $memoryUsage, bool $deep): void
{
    global $lang;

    $includedFiles = array_map(
        fn($file) => str_replace('\\', '/', $file),
        get_included_files()
    );

    $output .= '
<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white py-3 position-relative">
            <div class="position-absolute top-0 end-0 mt-2 me-3">
                <span class="badge bg-white text-dark fs-6">' . hsafe(sprintf($lang->query_explain['files_count'] ?? '%1$s files', count($includedFiles))) . '</span>
            </div>
            <h4 class="mb-0 d-flex align-items-center">
                <i class="fas fa-file-import me-3"></i>
                ' . hsafe($lang->query_explain['included_files'] ?? 'Included Files') . '
            </h4>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">';

    $coreBadge = '<span class="badge bg-warning text-dark ms-2">' . hsafe($lang->query_explain['badge_core'] ?? 'Core') . '</span>';

    foreach ($includedFiles as $index => $file) {
        $isCore = str_contains($file, 'core') || str_contains($file, 'config');
        $fileType = pathinfo($file, PATHINFO_EXTENSION);

        $output .= '
                <div class="list-group-item d-flex align-items-center py-3 border-0 ' . ($index % 2 === 0 ? 'bg-light' : '') . '">
                    <div class="flex-shrink-0">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px;">
                            <span class="fw-bold">' . ($index + 1) . '</span>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="d-flex align-items-center mb-1">
                            <code class="fs-6 text-dark fw-semibold">' . hsafe(basename($file)) . '</code>
                            ' . ($isCore ? $coreBadge : '') . '
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-folder me-1"></i>' . hsafe(dirname($file)) . '
                        </small>
                    </div>
                    <div class="flex-shrink-0 text-end">
                        <small class="text-muted d-block">' . hsafe(strtoupper($fileType)) . '</small>
                        ' . (file_exists($file) ? '<small class="text-success">' . formatBytes((int)filesize($file)) . '</small>' : '') . '
                    </div>
                </div>';
    }

    $output .= '
            </div>
        </div>
    </div>
</div>';

    if ($deep && $skipped > 0) {
        $output .= '<div class="container mt-2"><div class="alert alert-info">'
            . hsafe(sprintf($lang->query_explain['hidden_non_select'] ?? 'Hidden non-SELECT queries: %1$s', $skipped))
            . '</div></div>';
    }

    stdhead($lang->query_explain['page_title'] ?? 'DEBUG MODE');
    echo $output;
    stdfoot();
    exit;
}
