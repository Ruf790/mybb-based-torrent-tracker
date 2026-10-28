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
    $val  = sprintf('%.4f', $time);

    [$soft, $key, $fb] = match (true) {
        $stat <= 40 => ['st-soft-success', 'speed_excellent', 'Excellent'],
        $stat <= 70 => ['st-soft-info',    'speed_good',      'Good'],
        $stat <= 98 => ['st-soft-warning', 'speed_regular',   'Regular'],
        default     => ['st-soft-danger',  'speed_bad',       'Bad'],
    };

    return '<span class="qe-time"><i class="fa-regular fa-clock"></i>' . $val . 's</span>'
         . ' <span class="st-badge ' . $soft . '">' . hsafe($lang->query_explain[$key] ?? $fb) . '</span>';
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
    if ($value === '') {
        return '<span class="qe-null">—</span>';
    }

    if ($column === 'type') {
        $soft = match (true) {
            str_contains($value, 'ref') || in_array($value, ['const', 'system', 'eq_ref'], true) => 'st-soft-success',
            $value === 'ALL' => 'st-soft-danger',
            default          => 'st-soft-warning',
        };
        return '<span class="st-badge ' . $soft . '">' . $value . '</span>';
    }

    if ($column === 'key') {
        return '<code class="qe-key">' . $value . '</code>';
    }

    if ($column === 'Extra') {
        return preg_replace('/Using (\w+)/', '<span class="st-badge st-soft-info">Using $1</span>', $value) ?? $value;
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

    $html = '';
    foreach (['mysqli', 'pdo_mysql', 'json', 'mbstring', 'xml', 'curl'] as $ext) {
        $loaded = extension_loaded($ext);
        $text   = $loaded ? ($lang->query_explain['ext_loaded'] ?? 'Loaded') : ($lang->query_explain['ext_missing'] ?? 'Missing');
        $html  .= '<span class="st-badge ' . ($loaded ? 'st-soft-success' : 'st-soft-danger') . '">'
                . '<i class="fa-solid ' . ($loaded ? 'fa-check' : 'fa-xmark') . '"></i>' . $ext . ': ' . hsafe($text) . '</span>';
    }
    return $html;
}

/** Строка из query_explain.lang.php без «:» на конце */
function qe_l(string $key, string $fallback): string
{
    global $lang;
    return hsafe(rtrim(trim((string)($lang->query_explain[$key] ?? $fallback)), ':'));
}

/** Карточка запроса / обращения к кэшу */
function qe_card(int $id, string $soft, string $icon, string $title, string $badge, string $body, string $foot, string $extra_cls = ''): string
{
    return '
    <article class="qe-q' . ($extra_cls !== '' ? ' ' . $extra_cls : '') . '" id="qe-' . $id . '">
        <div class="qe-q__head">
            <span class="qe-q__num">' . $id . '</span>
            <span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>
            <strong class="qe-q__title">' . $title . '</strong>
            <span class="ms-auto">' . $badge . '</span>
        </div>
        <div class="qe-q__body">' . $body . '</div>
        <div class="qe-q__foot">' . $foot . '</div>
    </article>';
}

function qe_sql(string $sql): string
{
    return '<pre class="qe-sql">' . format_sql_with_syntax($sql) . '</pre>';
}

/** Тип запроса → цвет и иконка */
function qe_type_style(string $type): array
{
    return match ($type) {
        'SELECT', 'WITH' => ['st-soft-primary', 'fa-magnifying-glass'],
        'INSERT', 'REPLACE' => ['st-soft-success', 'fa-plus'],
        'UPDATE' => ['st-soft-warning', 'fa-pen'],
        'DELETE' => ['st-soft-danger', 'fa-trash'],
        default  => ['st-soft-info', 'fa-terminal'],
    };
}

/** Сводка вверху страницы: KPI, распределение времени, сведения о системе */
function render_dashboard(int $printed, int $skipped, float $queryTime, float $totalTime, string $memoryUsage, bool $deep, array $speed, int $cacheCount, int $filesCount): string
{
    global $lang;

    $phpTime    = max(0.0, $totalTime - $queryTime);
    $percentPhp = $totalTime > 0 ? round($phpTime / $totalTime * 100, 2) : 0.0;
    $percentSql = $totalTime > 0 ? round($queryTime / $totalTime * 100, 2) : 0.0;
    $unknown    = $lang->query_explain['unknown'] ?? 'Unknown';
    $memoryPeak = function_exists('memory_get_peak_usage') ? mksize(memory_get_peak_usage()) : $unknown;

    $gzip = ($GLOBALS['gzipcompress'] ?? '') === 'yes'
        ? '<span class="st-badge st-soft-success">' . hsafe($lang->query_explain['enabled'] ?? 'Enabled') . '</span>'
        : '<span class="st-badge st-soft-muted">' . hsafe($lang->query_explain['disabled'] ?? 'Disabled') . '</span>';

    $n   = max(1, (int)$speed['total_queries']);
    $pct = static fn(int $v): float => round($v / $n * 100, 1);

    $kpi = static fn(string $icon, string $soft, string $val, string $lbl): string =>
        '<div class="col-6 col-lg-3"><div class="st-kpi"><span class="st-ico ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></span>'
        . '<div class="min-w-0"><div class="st-kpi__val">' . $val . '</div><div class="st-kpi__lbl">' . $lbl . '</div></div></div></div>';

    $row = static fn(string $icon, string $lbl, string $val): string =>
        '<div class="st-row"><span class="st-row__lbl"><i class="fa-solid ' . $icon . '"></i>' . $lbl . '</span><span class="st-row__val">' . $val . '</span></div>';

    return '
    <div class="row g-3 mb-3">
        ' . $kpi('fa-stopwatch', 'st-soft-primary', sprintf('%.4fs', $totalTime), qe_l('total_time', 'Total Time')) . '
        ' . $kpi('fa-database', 'st-soft-info', sprintf('%.4fs', $queryTime), 'MySQL · ' . $percentSql . '%') . '
        ' . $kpi('fa-list-ol', 'st-soft-success', (string)($printed + $skipped), qe_l('total_queries', 'Total Queries') . ($deep && $skipped > 0 ? ' · ' . qe_l('hidden', 'Hidden') . ' ' . $skipped : '')) . '
        ' . $kpi('fa-memory', 'st-soft-warning', hsafe($memoryUsage ?: $unknown), qe_l('memory_usage', 'Memory Usage')) . '
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-7">
            <section class="st-card h-100">
                <div class="st-card__head"><span class="st-ico st-soft-primary"><i class="fa-solid fa-chart-simple"></i></span>' . qe_l('stats_title', 'Performance Statistics') . '</div>
                <div class="st-card__body">
                    <div class="qe-lbl">' . qe_l('time_distribution', 'Time Distribution') . '</div>
                    <div class="qe-bar">
                        <span class="qe-bar__php" style="width:' . $percentPhp . '%"></span><span class="qe-bar__sql" style="width:' . $percentSql . '%"></span>
                    </div>
                    <div class="qe-legend">
                        <span><i class="qe-dot qe-dot--php"></i>PHP <b>' . $percentPhp . '%</b> <small>' . sprintf('%.4fs', $phpTime) . '</small></span>
                        <span><i class="qe-dot qe-dot--sql"></i>MySQL <b>' . $percentSql . '%</b> <small>' . sprintf('%.4fs', $queryTime) . '</small></span>
                    </div>

                    <div class="qe-lbl mt-4">' . qe_l('summary_title', 'Performance Summary') . '</div>
                    <div class="qe-bar">
                        <span class="bg-success" style="width:' . $pct($speed['fast']) . '%"></span><span class="bg-warning" style="width:' . $pct($speed['medium']) . '%"></span><span class="bg-danger" style="width:' . $pct($speed['slow']) . '%"></span>
                    </div>
                    <div class="qe-speed">
                        <div class="qe-speed__item qe-speed__item--ok"><b>' . $speed['fast'] . '</b><span>' . qe_l('fast_queries', 'Fast Queries (<10ms)') . '</span><small>' . $pct($speed['fast']) . '%</small></div>
                        <div class="qe-speed__item qe-speed__item--mid"><b>' . $speed['medium'] . '</b><span>' . qe_l('medium_queries', 'Medium Queries (10-100ms)') . '</span><small>' . $pct($speed['medium']) . '%</small></div>
                        <div class="qe-speed__item qe-speed__item--bad"><b>' . $speed['slow'] . '</b><span>' . qe_l('slow_queries', 'Slow Queries (>100ms)') . '</span><small>' . $pct($speed['slow']) . '%</small></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-5">
            <section class="st-card h-100">
                <div class="st-card__head"><span class="st-ico st-soft-success"><i class="fa-solid fa-server"></i></span>' . qe_l('system_info', 'System Information') . '</div>
                <div class="st-card__body p-0"><div class="st-rows border-0 rounded-0">
                    ' . $row('fa-brands fa-php', qe_l('php_version', 'PHP Version'), hsafe(PHP_VERSION)) . '
                    ' . $row('fa-database', qe_l('mysql_version', 'MySQL Version'), hsafe(get_mysql_version() ?: $unknown)) . '
                    ' . $row('fa-gauge-high', qe_l('server_load', 'Server Load'), hsafe(server_load() ?: $unknown)) . '
                    ' . $row('fa-memory', qe_l('memory_peak', 'Peak Memory'), hsafe($memoryPeak)) . '
                    ' . $row('fa-file-zipper', qe_l('gzip', 'GZip Compression'), $gzip) . '
                </div></div>
                <div class="st-card__body border-top">
                    <div class="qe-lbl">' . qe_l('php_extensions', 'PHP Extensions') . '</div>
                    <div class="qe-exts">' . get_php_extensions_status() . '</div>
                </div>
            </section>
        </div>
    </div>';
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

    return '<div class="st-danger-box qe-err"><i class="fa-solid fa-ban"></i><div>' . hsafe($lang->query_explain['err_multi_statements'] ?? 'Multiple statements are not allowed.') . '</div></div>'
         . qe_sql($sql);
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
        <div class="table-responsive qe-tablewrap mt-3">
            <table class="table table-sm qe-table mb-0">
                <thead><tr>'
                    . '<th>' . hsafe($lang->query_explain['warn_level'] ?? 'Level') . '</th>'
                    . '<th>' . hsafe($lang->query_explain['warn_code'] ?? 'Code') . '</th>'
                    . '<th>' . hsafe($lang->query_explain['warn_message'] ?? 'Message') . '</th>'
                . '</tr></thead>
                <tbody>' . $warnings . '</tbody>
            </table>
        </div>';
    }

    return qe_card(
        $id, 'st-soft-primary', 'fa-microscope',
        hsafe(sprintf($lang->query_explain['hdr_deep'] ?? '#%1$s - Deep Plan (EXPLAIN ANALYZE)', $id)),
        '<span class="st-badge st-soft-primary">' . hsafe($lang->query_explain['badge_deep'] ?? 'Deep Analysis') . '</span>',
        qe_sql($sql) . '<pre class="qe-plan">' . hsafe($analysis) . '</pre>' . $warningsHtml,
        '<span>' . hsafe($lang->query_explain['measured_time'] ?? 'Measured time:') . ' ' . $timeHtml . '</span>'
        . '<small>' . hsafe($lang->query_explain['executed_analyze'] ?? 'Executed with ANALYZE') . '</small>'
    );
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

    $body    = '';
    $has_all = false;
    foreach ($rows as $row) {
        $has_all = $has_all || (($row['type'] ?? '') === 'ALL');
        $body   .= '<tr>';
        foreach ($columns as $col) {
            $body .= '<td>' . format_explain_value((string)$col, $row[$col] ?? '') . '</td>';
        }
        $body .= '</tr>';
    }

    return qe_card(
        $id, 'st-soft-primary', 'fa-magnifying-glass',
        hsafe(sprintf($lang->query_explain['hdr_select'] ?? '#%1$s - Select Query', $id)),
        '<span class="st-badge st-soft-muted">' . hsafe(sprintf($lang->query_explain['explain_rows'] ?? '%1$s row(s) in explain', count($rows))) . '</span>',
        qe_sql($sql)
        . '<div class="table-responsive qe-tablewrap"><table class="table table-sm qe-table mb-0"><thead><tr>' . $headers . '</tr></thead><tbody>' . $body . '</tbody></table></div>',
        '<span>' . $timeHtml . '</span><small>' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>',
        $has_all ? 'qe-q--warn' : ''
    );
}

function render_read_query(int $id, string $sql, string $timeHtml, string $error = ''): string
{
    global $lang;

    $errorHtml = $error !== ''
        ? '<div class="st-danger-box qe-err mt-2"><i class="fa-solid fa-triangle-exclamation"></i><div>' . hsafe($lang->query_explain['err_explain_failed'] ?? 'EXPLAIN failed:') . ' ' . hsafe($error) . '</div></div>'
        : '';

    return qe_card(
        $id, 'st-soft-muted', 'fa-book-open',
        hsafe(sprintf($lang->query_explain['hdr_read'] ?? '#%1$s - Read Query', $id)),
        '<span class="st-badge st-soft-muted">' . hsafe($lang->query_explain['badge_read_only'] ?? 'Read Only') . '</span>',
        qe_sql($sql) . $errorHtml,
        '<span>' . $timeHtml . '</span><small>' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>'
    );
}

function explain_write_query(mysqli $link, string $sqlClean, string $sql, string $timeHtml, int $id, string $queryType): string
{
    global $lang;

    // Запрос здесь не выполняется: mysqli_affected_rows()/insert_id() вернули бы данные
    // последнего запроса на соединении (этой страницы), а не исходного — поэтому не показываем.
    [$soft, $icon] = qe_type_style($queryType);

    return qe_card(
        $id, $soft, $icon,
        hsafe(sprintf($lang->query_explain['hdr_write'] ?? '#%1$s - %2$s Query', $id, $queryType)),
        '<span class="st-badge ' . $soft . '">' . hsafe($lang->query_explain['badge_write'] ?? 'Write Operation') . '</span>',
        qe_sql($sql),
        '<span>' . $timeHtml . '</span><small>' . hsafe(sprintf($lang->query_explain['query_num'] ?? 'Query #%1$s', $id)) . '</small>'
    );
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

// Just the size: the label is printed by render_dashboard()
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

    $queriesHtml = '';
    $queryId     = 1;
    $queryTime   = 0.0;
    $printed     = 0;
    $skipped     = 0;

    $performanceStats = [
        'fast'          => 0,
        'medium'        => 0,
        'slow'          => 0,
        'total_queries' => count($queries),
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

        $queriesHtml .= $html;

        $queryId++;
        $queryTime += $execTime;
        $printed++;
    }

    render_final_output($queriesHtml, $printed, $skipped, $queryTime, $totalTime, $memoryUsage, $deep, $performanceStats, $cacheCallsRaw);
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

    // HIT / MISS — стандартные термины кэша, без перевода
    return '
    <div class="qe-c">
        <span class="qe-q__num">' . $id . '</span>
        <span class="st-badge ' . ($call['hit'] ? 'st-soft-success' : 'st-soft-danger') . ' qe-c__hit">' . ($call['hit'] ? 'HIT' : 'MISS') . '</span>
        <span class="qe-c__verb">' . hsafe(ucfirst($call['verb'])) . '</span>
        <code class="qe-c__key">' . hsafe($call['key']) . '</code>
        <span class="qe-c__time" title="' . hsafe(rtrim($lang->query_explain['call_time'] ?? 'Call Time:', ':')) . '">' . format_time_duration($call['time']) . '</span>
    </div>';
}

/** @return array{0:string,1:int} HTML списка и число обращений */
function process_cache_calls(array $cacheCallsRaw): array
{
    global $lang;

    $rows      = '';
    $id        = 1;
    $totalTime = 0.0;
    $hits      = 0;

    foreach ($cacheCallsRaw as $raw) {
        $call = decode_cache_call_data((string)$raw);
        if ($call === null) {
            continue;
        }
        $totalTime += $call['time'];
        $hits      += $call['hit'] ? 1 : 0;
        $rows      .= render_cache_call($id, $call);
        $id++;
    }

    $count = $id - 1;
    if ($count === 0) {
        return ['', 0];
    }

    $head = '<div class="qe-pane-head">'
          . '<span>' . hsafe(sprintf($lang->query_explain['cache_calls_title'] ?? 'Cache Calls (%1$s Total, %2$s)', $count, strip_tags((string)format_time_duration($totalTime)))) . '</span>'
          . '<span class="st-badge st-soft-success">HIT ' . $hits . '</span>'
          . '<span class="st-badge st-soft-danger">MISS ' . ($count - $hits) . '</span>'
          . '</div>';

    return [$head . '<div class="qe-clist">' . $rows . '</div>', $count];
}

function render_no_queries(): void
{
    global $lang, $BASEURL;

    stdhead($lang->query_explain['page_title'] ?? 'DEBUG MODE');
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=6">'
       . '<div class="container-md st-page qe-page"><div class="st-card"><div class="st-empty">'
       . '<span class="st-ico st-soft-muted"><i class="fa-solid fa-database"></i></span>'
       . '<div class="fw-semibold">' . hsafe($lang->query_explain['no_queries'] ?? 'There is no query to show.') . '</div>'
       . '</div></div></div>';
    stdfoot();
    exit;
}

function render_final_output(string $queriesHtml, int $printed, int $skipped, float $queryTime, float $totalTime, string $memoryUsage, bool $deep, array $speed = [], array $cacheCallsRaw = []): void
{
    global $lang, $BASEURL;

    [$cacheHtml, $cacheCount] = process_cache_calls($cacheCallsRaw);

    $includedFiles = array_map(fn($file) => str_replace('\\', '/', $file), get_included_files());
    $coreBadge     = '<span class="st-badge st-soft-warning">' . hsafe($lang->query_explain['badge_core'] ?? 'Core') . '</span>';
    $filesHtml     = '';
    $filesSize     = 0;
    foreach ($includedFiles as $index => $file) {
        $isCore = str_contains($file, 'core') || str_contains($file, 'config');
        $size   = file_exists($file) ? (int)filesize($file) : 0;
        $filesSize += $size;
        $filesHtml .= '
        <div class="qe-f">
            <span class="qe-q__num">' . ($index + 1) . '</span>
            <i class="' . getFileIcon($file) . ' qe-f__ico"></i>
            <div class="qe-f__body">
                <div><code class="qe-f__name">' . hsafe(basename($file)) . '</code> ' . ($isCore ? $coreBadge : '') . '</div>
                <div class="qe-f__dir"><i class="fa-regular fa-folder"></i>' . hsafe(dirname($file)) . '</div>
            </div>
            <span class="qe-f__size">' . ($size > 0 ? formatBytes($size) : '') . '</span>
        </div>';
    }

    $speed += ['fast' => 0, 'medium' => 0, 'slow' => 0, 'total_queries' => $printed + $skipped];

    $hiddenNote = ($deep && $skipped > 0)
        ? '<div class="qe-note"><i class="fa-solid fa-circle-info"></i>' . hsafe(sprintf($lang->query_explain['hidden_non_select'] ?? 'Hidden non-SELECT queries: %1$s', $skipped)) . '</div>'
        : '';

    $tab = static fn(string $id, string $icon, string $label, int $n, bool $active = false): string =>
        '<li class="nav-item" role="presentation"><button class="nav-link' . ($active ? ' active' : '') . '" data-bs-toggle="tab" data-bs-target="#qe-tab-' . $id . '" type="button" role="tab">'
        . '<i class="fa-solid ' . $icon . '"></i>' . $label . '<span class="st-badge st-soft-muted">' . $n . '</span></button></li>';

    $output = '
<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">
<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/query-explain.css?ver=2">
<div class="container-md st-page qe-page">

    <div class="st-card mb-3">
        <div class="st-head">
            <span class="st-head__icon st-soft-warning"><i class="fa-solid fa-bug"></i></span>
            <div class="min-w-0">
                <h1 class="st-title mb-0">' . hsafe($lang->query_explain['page_title'] ?? 'DEBUG MODE') . '</h1>
                <div class="st-meta mt-1">
                    ' . qe_l('total_queries', 'Total Queries') . ' <b>' . ($printed + $skipped) . '</b> ·
                    ' . qe_l('displayed', 'Displayed') . ' <b>' . $printed . '</b> ·
                    ' . qe_l('total_time', 'Total Time') . ' <b>' . sprintf('%.4fs', $totalTime) . '</b>
                </div>
            </div>
            ' . ($deep ? '<span class="st-badge st-soft-primary ms-auto"><i class="fa-solid fa-microscope"></i>EXPLAIN ANALYZE</span>' : '') . '
        </div>
    </div>

    ' . render_dashboard($printed, $skipped, $queryTime, $totalTime, $memoryUsage, $deep, $speed, $cacheCount, count($includedFiles)) . '

    <section class="st-card">
        <div class="st-tabs-wrap">
            <ul class="nav st-tabs" role="tablist">
                ' . $tab('sql', 'fa-database', 'SQL', $printed, true) . '
                ' . ($cacheCount > 0 ? $tab('cache', 'fa-bolt', qe_l('tab_cache', 'Cache'), $cacheCount) : '') . '
                ' . $tab('files', 'fa-file-import', qe_l('included_files', 'Included Files'), count($includedFiles)) . '
            </ul>
        </div>
        <div class="st-card__body tab-content">
            <div class="tab-pane fade show active" id="qe-tab-sql" role="tabpanel">
                ' . $hiddenNote . '
                <div class="qe-list">' . $queriesHtml . '</div>
            </div>
            ' . ($cacheCount > 0 ? '<div class="tab-pane fade" id="qe-tab-cache" role="tabpanel">' . $cacheHtml . '</div>' : '') . '
            <div class="tab-pane fade" id="qe-tab-files" role="tabpanel">
                <div class="qe-pane-head">
                    <span>' . hsafe(sprintf($lang->query_explain['files_count'] ?? '%1$s files', count($includedFiles))) . '</span>
                    <span class="st-badge st-soft-muted">' . formatBytes($filesSize) . '</span>
                </div>
                <div class="qe-flist">' . $filesHtml . '</div>
            </div>
        </div>
    </section>
</div>';

    stdhead($lang->query_explain['page_title'] ?? 'DEBUG MODE');
    echo $output;
    stdfoot();
    exit;
}
