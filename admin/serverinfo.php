<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

define('SI_VERSION', '3.0');
const SI_ASSET_VER = 1;

final class ServerInfo
{
    private array $vars   = [];   // SHOW VARIABLES
    private array $status = [];   // SHOW GLOBAL STATUS

    public function __construct(private $db) {}

    // ═════════════════════════════════════════════════════
    //  Сбор данных
    // ═════════════════════════════════════════════════════

    private function loadMysql(): void
    {
        $q = $this->db->sql_query_prepared('SHOW VARIABLES');
        while ($q && ($r = $this->db->fetch_array($q))) $this->vars[(string)$r['Variable_name']] = (string)$r['Value'];

        $q = $this->db->sql_query_prepared('SHOW GLOBAL STATUS');
        while ($q && ($r = $this->db->fetch_array($q))) $this->status[(string)$r['Variable_name']] = (string)$r['Value'];
    }

    private function v(string $name): string { return $this->vars[$name] ?? ''; }
    private function st(string $name): int   { return (int)($this->status[$name] ?? 0); }

    /** Один запрос вместо двух SHOW TABLE STATUS. */
    private function dbTotals(): array
    {
        // MySQL 8+ кэширует статистику information_schema на сутки
        $this->db->sql_query_prepared('SET SESSION information_schema_stats_expiry = 0');

        $q = $this->db->sql_query_prepared(
            'SELECT COUNT(*) AS n, COALESCE(SUM(DATA_LENGTH),0) AS d, COALESCE(SUM(INDEX_LENGTH),0) AS i,
                    COALESCE(SUM(DATA_FREE),0) AS f, COALESCE(SUM(TABLE_ROWS),0) AS r
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        $r = $q ? $this->db->fetch_array($q) : [];
        return [
            'tables' => (int)($r['n'] ?? 0),
            'data'   => (int)($r['d'] ?? 0),
            'index'  => (int)($r['i'] ?? 0),
            'free'   => (int)($r['f'] ?? 0),
            'rows'   => (int)($r['r'] ?? 0),
        ];
    }

    private function topTables(int $limit = 10): array
    {
        $out = [];
        $q = $this->db->sql_query_prepared(
            'SELECT TABLE_NAME AS t, ENGINE AS e, TABLE_ROWS AS r, DATA_LENGTH AS d, INDEX_LENGTH AS i
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC LIMIT ' . max(1, $limit)
        );
        while ($q && ($r = $this->db->fetch_array($q))) {
            $out[] = [
                'name'   => (string)$r['t'],
                'engine' => (string)($r['e'] ?? ''),
                'rows'   => (int)($r['r'] ?? 0),
                'size'   => (int)($r['d'] ?? 0) + (int)($r['i'] ?? 0),
            ];
        }
        return $out;
    }

    private function opcache(): ?array
    {
        if (!function_exists('opcache_get_status')) return null;
        $s = @opcache_get_status(false);
        if (!is_array($s) || empty($s['opcache_enabled'])) return null;

        $used = (int)($s['memory_usage']['used_memory'] ?? 0);
        $free = (int)($s['memory_usage']['free_memory'] ?? 0);
        return [
            'hit'     => (float)($s['opcache_statistics']['opcache_hit_rate'] ?? 0),
            'scripts' => (int)($s['opcache_statistics']['num_cached_scripts'] ?? 0),
            'mem_pct' => ($used + $free) > 0 ? $used / ($used + $free) * 100 : 0.0,
            'used'    => $used,
            'total'   => $used + $free,
        ];
    }

    private function cpu(): array
    {
        $cores = 0;
        if (PHP_OS_FAMILY === 'Windows') {
            $cores = (int)getenv('NUMBER_OF_PROCESSORS');
        } elseif (@is_readable('/proc/cpuinfo')) {
            $cores = substr_count((string)@file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1;
        }

        $load = null;
        if (function_exists('sys_getloadavg')) {
            $l = @sys_getloadavg();
            if (is_array($l) && $l) $load = array_map(fn($x) => round((float)$x, 2), $l);
        }
        return ['cores' => $cores, 'load' => $load];
    }

    private function disk(): ?array
    {
        $total = @disk_total_space(TSDIR);
        $free  = @disk_free_space(TSDIR);
        if (!$total || $free === false) return null;
        return ['total' => (int)$total, 'free' => (int)$free, 'used_pct' => ($total - $free) / $total * 100];
    }

    // ═════════════════════════════════════════════════════
    //  Форматирование
    // ═════════════════════════════════════════════════════

    private function e(string|int|float|null $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private function size(int|float|string $bytes): string
    {
        $b = (float)$bytes;
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
        return round($b, $i ? 2 : 0) . ' ' . $u[$i];
    }

    private function num(int|float $n): string
    {
        return number_format((float)$n, 0, '.', ' ');
    }

    private function uptime(int $sec): string
    {
        $d = intdiv($sec, 86400);
        $h = intdiv($sec % 86400, 3600);
        $m = intdiv($sec % 3600, 60);
        return $d ? "{$d}d {$h}h" : ($h ? "{$h}h {$m}m" : "{$m}m");
    }

    private function ini(string $key): string
    {
        $v = ini_get($key);
        return $v === false ? '—' : ($v === '' ? '(empty)' : $v);
    }

    private function onOff(string $key): bool
    {
        return filter_var(ini_get($key), FILTER_VALIDATE_BOOLEAN);
    }

    /** Цветовая полоса: зелёная / жёлтая / красная по порогам. */
    private function bar(float $pct, float $warn = 75, float $bad = 90): string
    {
        $pct = max(0, min(100, $pct));
        $cls = $pct >= $bad ? 'bad' : ($pct >= $warn ? 'warn' : 'ok');
        return '<div class="si-bar si-bar-' . $cls . '"><span style="width:' . round($pct, 1) . '%"></span></div>';
    }

    // ═════════════════════════════════════════════════════
    //  Проверки
    // ═════════════════════════════════════════════════════

    /** @return list<array{0:string,1:string,2:string}> [ok|warn|bad|info, заголовок, пояснение] */
    private function checks(?array $opc, ?array $disk): array
    {
        $c = [];

        // Сроки поддержки: 8.1 - до 2025-12-31, 8.2 - до 2026-12-31, 8.3 - до 2027-12-31
        if (PHP_VERSION_ID < 80200) {
            $c[] = ['bad', 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ' no longer gets security fixes', 'Upgrade to PHP 8.3 or newer.'];
        } elseif (PHP_VERSION_ID < 80300) {
            $c[] = ['warn', 'PHP 8.2 security support ends on 2026-12-31', 'Plan an upgrade to PHP 8.3 or newer.'];
        } else {
            $c[] = ['ok', 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ' is supported', 'Receives security fixes.'];
        }

        $c[] = $this->onOff('display_errors')
            ? ['bad', 'display_errors is On', 'Errors with file paths are shown to visitors. Turn it off and use log_errors.']
            : ['ok', 'display_errors is Off', 'Errors are not shown to visitors.'];

        $c[] = $this->onOff('log_errors')
            ? ['ok', 'log_errors is On', 'Errors go to ' . ($this->ini('error_log') !== '(empty)' ? $this->ini('error_log') : 'the server log') . '.']
            : ['warn', 'log_errors is Off', 'PHP errors are not recorded anywhere.'];

        $c[] = $this->onOff('expose_php')
            ? ['warn', 'expose_php is On', 'Every response advertises the PHP version in X-Powered-By.']
            : ['ok', 'expose_php is Off', 'The PHP version is not advertised.'];

        $c[] = $this->onOff('allow_url_include')
            ? ['bad', 'allow_url_include is On', 'include() can load remote code. Turn it off.']
            : ['ok', 'allow_url_include is Off', 'include() cannot load remote code.'];

        $c[] = $this->onOff('session.cookie_httponly')
            ? ['ok', 'Session cookie is HttpOnly', 'JavaScript cannot read the session cookie.']
            : ['warn', 'Session cookie is not HttpOnly', 'Set session.cookie_httponly = 1.'];

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($https) {
            $c[] = $this->onOff('session.cookie_secure')
                ? ['ok', 'Session cookie is Secure', 'Sent over HTTPS only.']
                : ['warn', 'Session cookie is not Secure', 'The site runs on HTTPS; set session.cookie_secure = 1.'];
        }

        $c[] = $opc
            ? ($opc['mem_pct'] >= 90
                ? ['warn', 'OPcache memory is ' . round($opc['mem_pct']) . '% full', 'Raise opcache.memory_consumption so scripts are not evicted.']
                : ['ok', 'OPcache is enabled', round($opc['hit'], 1) . '% hit rate, ' . $this->num($opc['scripts']) . ' scripts cached.'])
            : ['warn', 'OPcache is off', 'Enabling it makes every page faster.'];

        if ($disk) {
            $freePct = 100 - $disk['used_pct'];
            $c[] = $freePct < 10
                ? ['bad', 'Disk is almost full', $this->size($disk['free']) . ' free (' . round($freePct, 1) . '%).']
                : ($freePct < 20
                    ? ['warn', 'Disk space is getting low', $this->size($disk['free']) . ' free (' . round($freePct, 1) . '%).']
                    : ['ok', 'Enough disk space', $this->size($disk['free']) . ' free (' . round($freePct, 1) . '%).']);
        }

        $maxConn  = (int)$this->v('max_connections');
        $usedConn = $this->st('Max_used_connections');
        if ($maxConn > 0) {
            $pct = $usedConn / $maxConn * 100;
            $c[] = $pct >= 80
                ? ['warn', 'Connection peak reached ' . round($pct) . '% of max_connections', "{$usedConn} of {$maxConn} since the last MySQL restart."]
                : ['ok', 'Connection headroom is fine', "Peak {$usedConn} of {$maxConn} since the last MySQL restart."];
        }

        $reads = $this->st('Innodb_buffer_pool_reads');
        $reqs  = $this->st('Innodb_buffer_pool_read_requests');
        if ($reqs > 0) {
            $hit = (1 - $reads / $reqs) * 100;
            $c[] = $hit < 95
                ? ['warn', 'InnoDB buffer pool hit rate is ' . round($hit, 2) . '%', 'Many reads go to disk; consider a larger innodb_buffer_pool_size.']
                : ['ok', 'InnoDB buffer pool hit rate is ' . round($hit, 2) . '%', 'Almost all reads are served from memory.'];
        }

        return $c;
    }

    // ═════════════════════════════════════════════════════
    //  Вывод
    // ═════════════════════════════════════════════════════

    public function render(): void
    {
        global $BASEURL;

        $this->loadMysql();
        $db    = $this->dbTotals();
        $top   = $this->topTables();
        $opc   = $this->opcache();
        $cpu   = $this->cpu();
        $disk  = $this->disk();
        $checks = $this->checks($opc, $disk);

        $isWin   = PHP_OS_FAMILY === 'Windows';
        $mysqlV  = $this->v('version') ?: 'Unknown';
        $flavor  = stripos($this->v('version_comment'), 'mariadb') !== false || stripos($mysqlV, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
        $maxConn = (int)$this->v('max_connections');
        $conn    = $this->st('Threads_connected');
        $uptime  = $this->st('Uptime');
        $qps     = $uptime > 0 ? $this->st('Questions') / $uptime : 0;

        $problems = count(array_filter($checks, fn($c) => $c[0] === 'bad'));
        $warnings = count(array_filter($checks, fn($c) => $c[0] === 'warn'));

        $v = SI_ASSET_VER;
        echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/serverinfo.css?ver=' . $v . '">';
        ?>
<div class="si-page container mt-3 mb-5">

    <div class="si-head">
        <div class="si-head-icon"><i class="fa-solid fa-server"></i></div>
        <div class="si-head-text">
            <h1>Server information</h1>
            <p>PHP, <?= $flavor ?> and host details · <?= $this->e(date('Y-m-d H:i:s')) ?> (<?= $this->e(date_default_timezone_get()) ?>)</p>
        </div>
        <div class="si-head-badges">
            <span class="si-chip"><i class="fa-brands <?= $isWin ? 'fa-windows' : 'fa-linux' ?>"></i><?= $this->e(PHP_OS_FAMILY) ?></span>
            <span class="si-chip"><i class="fa-solid fa-plug"></i><?= $this->e(PHP_SAPI) ?></span>
            <?php if ($problems): ?>
                <span class="si-chip si-chip-bad"><i class="fa-solid fa-circle-xmark"></i><?= $problems ?> problem<?= $problems > 1 ? 's' : '' ?></span>
            <?php elseif ($warnings): ?>
                <span class="si-chip si-chip-warn"><i class="fa-solid fa-triangle-exclamation"></i><?= $warnings ?> warning<?= $warnings > 1 ? 's' : '' ?></span>
            <?php else: ?>
                <span class="si-chip si-chip-ok"><i class="fa-solid fa-circle-check"></i>All checks passed</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI -->
    <div class="si-kpis">
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-php"><i class="fa-brands fa-php"></i></div>
            <div><div class="si-kpi-val"><?= $this->e(PHP_VERSION) ?></div><div class="si-kpi-lbl">PHP · <?= count(get_loaded_extensions()) ?> extensions</div></div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-db"><i class="fa-solid fa-database"></i></div>
            <div><div class="si-kpi-val"><?= $this->e(preg_replace('~-.*$~', '', $mysqlV)) ?></div><div class="si-kpi-lbl"><?= $flavor ?> · up <?= $this->uptime($uptime) ?></div></div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-info"><i class="fa-solid fa-table"></i></div>
            <div><div class="si-kpi-val"><?= $this->size($db['data'] + $db['index']) ?></div><div class="si-kpi-lbl">Database · <?= $db['tables'] ?> tables</div></div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-disk"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="si-kpi-body">
                <?php if ($disk): ?>
                    <div class="si-kpi-val"><?= $this->size($disk['free']) ?></div>
                    <div class="si-kpi-lbl">free of <?= $this->size($disk['total']) ?></div>
                    <?= $this->bar($disk['used_pct'], 80, 90) ?>
                <?php else: ?>
                    <div class="si-kpi-val">—</div><div class="si-kpi-lbl">Disk space unavailable</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="si-kpi">
            <div class="si-kpi-icon si-c-conn"><i class="fa-solid fa-network-wired"></i></div>
            <div class="si-kpi-body">
                <div class="si-kpi-val"><?= $conn ?> <small>/ <?= $maxConn ?: '—' ?></small></div>
                <div class="si-kpi-lbl">connections · peak <?= $this->st('Max_used_connections') ?></div>
                <?= $maxConn ? $this->bar($conn / $maxConn * 100, 60, 80) : '' ?>
            </div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-q"><i class="fa-solid fa-gauge-high"></i></div>
            <div><div class="si-kpi-val"><?= round($qps, 1) ?></div><div class="si-kpi-lbl">queries / sec · <?= $this->num($this->st('Slow_queries')) ?> slow</div></div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-opc"><i class="fa-solid fa-bolt"></i></div>
            <div class="si-kpi-body">
                <?php if ($opc): ?>
                    <div class="si-kpi-val"><?= round($opc['hit'], 1) ?>%</div>
                    <div class="si-kpi-lbl">OPcache hits · <?= $this->size($opc['used']) ?> / <?= $this->size($opc['total']) ?></div>
                    <?= $this->bar($opc['mem_pct'], 80, 95) ?>
                <?php else: ?>
                    <div class="si-kpi-val">Off</div><div class="si-kpi-lbl">OPcache</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="si-kpi">
            <div class="si-kpi-icon si-c-cpu"><i class="fa-solid fa-microchip"></i></div>
            <div>
                <div class="si-kpi-val"><?= $cpu['load'] ? $this->e(implode(' · ', $cpu['load'])) : ($cpu['cores'] ?: '—') ?></div>
                <div class="si-kpi-lbl"><?= $cpu['load'] ? 'load 1 · 5 · 15 min' : 'CPU cores' ?><?= $cpu['load'] && $cpu['cores'] ? ' · ' . $cpu['cores'] . ' cores' : '' ?></div>
            </div>
        </div>
    </div>

    <!-- Вкладки -->
    <div class="si-tabs nav" role="tablist">
        <button class="si-tab active" id="si-tab-overview" data-bs-toggle="pill" data-bs-target="#si-overview" type="button" role="tab" aria-controls="si-overview" aria-selected="true">
            <i class="fa-solid fa-gauge"></i>Overview
        </button>
        <button class="si-tab" id="si-tab-php" data-bs-toggle="pill" data-bs-target="#si-php" type="button" role="tab" aria-controls="si-php" aria-selected="false">
            <i class="fa-brands fa-php"></i>PHP
        </button>
        <button class="si-tab" id="si-tab-mysql" data-bs-toggle="pill" data-bs-target="#si-mysql" type="button" role="tab" aria-controls="si-mysql" aria-selected="false">
            <i class="fa-solid fa-database"></i><?= $flavor ?>
        </button>
        <button class="si-tab" id="si-tab-phpinfo" data-bs-toggle="pill" data-bs-target="#si-phpinfo" type="button" role="tab" aria-controls="si-phpinfo" aria-selected="false">
            <i class="fa-solid fa-scroll"></i>phpinfo()
        </button>
    </div>

    <div class="tab-content">
        <?php $this->tabOverview($checks, $top, $db); ?>
        <?php $this->tabPhp(); ?>
        <?php $this->tabMysql(); ?>
        <?php $this->tabPhpinfo(); ?>
    </div>
</div>
<script src="<?= $BASEURL ?>/admin/scripts/serverinfo.js?ver=<?= $v ?>"></script>
        <?php
    }

    // ── Обзор ────────────────────────────────────────────

    private function tabOverview(array $checks, array $top, array $db): void
    {
        $host = [
            ['fa-display',        'Operating system', php_uname('s') . ' ' . php_uname('r')],
            ['fa-globe',          'Web server',       (string)($_SERVER['SERVER_SOFTWARE'] ?? '—')],
            ['fa-signature',      'Hostname',         (string)($_SERVER['SERVER_NAME'] ?? gethostname())],
            ['fa-location-dot',   'Address',          ($_SERVER['SERVER_ADDR'] ?? '—') . ':' . ($_SERVER['SERVER_PORT'] ?? '—')],
            ['fa-folder-tree',    'Document root',    (string)($_SERVER['DOCUMENT_ROOT'] ?? '—')],
            ['fa-clock',          'PHP time zone',    date_default_timezone_get()],
            ['fa-business-time',  'MySQL time zone',  $this->v('time_zone') . ($this->v('system_time_zone') ? ' (system ' . $this->v('system_time_zone') . ')' : '')],
            ['fa-font',           'MySQL charset',    $this->v('character_set_server') . ' / ' . $this->v('collation_server')],
        ];
        $maxTop = $top ? max(1, $top[0]['size']) : 1;
        $icon = ['ok' => 'fa-circle-check', 'warn' => 'fa-triangle-exclamation', 'bad' => 'fa-circle-xmark', 'info' => 'fa-circle-info'];

        // Сначала проблемы, потом предупреждения, потом OK
        $rank = ['bad' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3];
        usort($checks, fn($a, $b) => $rank[$a[0]] <=> $rank[$b[0]]);
        ?>
        <div class="tab-pane fade show active" id="si-overview" role="tabpanel" aria-labelledby="si-tab-overview">
            <div class="si-cols">
                <div class="si-card">
                    <h2 class="si-card-title"><i class="fa-solid fa-shield-halved"></i>Health checks</h2>
                    <ul class="si-checks">
                        <?php foreach ($checks as [$lvl, $title, $detail]): ?>
                            <li class="si-check si-check-<?= $lvl ?>">
                                <i class="fa-solid <?= $icon[$lvl] ?>"></i>
                                <div><strong><?= $this->e($title) ?></strong><span><?= $this->e($detail) ?></span></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="si-card">
                    <h2 class="si-card-title"><i class="fa-solid fa-circle-info"></i>Host</h2>
                    <dl class="si-dl">
                        <?php foreach ($host as [$ic, $k, $val]): ?>
                            <div><dt><i class="fa-solid <?= $ic ?>"></i><?= $this->e($k) ?></dt><dd><?= $this->e($val) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

            <div class="si-card">
                <h2 class="si-card-title"><i class="fa-solid fa-ranking-star"></i>Largest tables
                    <span class="si-card-sub"><?= $this->size($db['data']) ?> data · <?= $this->size($db['index']) ?> indexes · <?= $this->size($db['free']) ?> reclaimable · ~<?= $this->num($db['rows']) ?> rows</span>
                </h2>
                <div class="si-table-wrap">
                    <table class="si-table">
                        <thead><tr><th>Table</th><th>Engine</th><th class="text-end">Rows</th><th class="text-end">Size</th><th class="si-col-bar"></th></tr></thead>
                        <tbody>
                        <?php foreach ($top as $t): ?>
                            <tr>
                                <td><code><?= $this->e($t['name']) ?></code></td>
                                <td class="si-muted"><?= $this->e($t['engine']) ?></td>
                                <td class="text-end">~<?= $this->num($t['rows']) ?></td>
                                <td class="text-end"><?= $this->size($t['size']) ?></td>
                                <td class="si-col-bar"><div class="si-bar si-bar-info"><span style="width:<?= round($t['size'] / $maxTop * 100, 1) ?>%"></span></div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // ── PHP ──────────────────────────────────────────────

    private function tabPhp(): void
    {
        $groups = [
            ['fa-memory', 'Limits', [
                'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars',
                'upload_max_filesize', 'post_max_size', 'max_file_uploads',
            ]],
            ['fa-bug', 'Errors', ['display_errors', 'log_errors', 'error_log', 'error_reporting']],
            ['fa-cookie-bite', 'Sessions', [
                'session.save_handler', 'session.gc_maxlifetime', 'session.cookie_httponly',
                'session.cookie_secure', 'session.cookie_samesite', 'session.use_strict_mode',
            ]],
            ['fa-bolt', 'OPcache', [
                'opcache.enable', 'opcache.memory_consumption', 'opcache.max_accelerated_files',
                'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.jit',
            ]],
            ['fa-sliders', 'Other', ['default_charset', 'date.timezone', 'file_uploads', 'allow_url_fopen', 'expose_php', 'open_basedir']],
        ];
        $ext = get_loaded_extensions();
        natcasesort($ext);
        ?>
        <div class="tab-pane fade" id="si-php" role="tabpanel" aria-labelledby="si-tab-php">
            <div class="si-grid-cards">
                <?php foreach ($groups as [$ic, $title, $keys]): ?>
                    <div class="si-card">
                        <h2 class="si-card-title"><i class="fa-solid <?= $ic ?>"></i><?= $this->e($title) ?></h2>
                        <dl class="si-kv">
                            <?php foreach ($keys as $k): ?>
                                <div><dt><code><?= $this->e($k) ?></code></dt><dd><?= $this->e($this->ini($k)) ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="si-card">
                <h2 class="si-card-title"><i class="fa-solid fa-puzzle-piece"></i>Loaded extensions <span class="si-card-sub"><?= count($ext) ?></span></h2>
                <div class="si-ext">
                    <?php foreach ($ext as $x): ?>
                        <span class="si-ext-chip"><?= $this->e($x) ?><small><?= $this->e((string)(phpversion($x) ?: '')) ?></small></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ── MySQL ────────────────────────────────────────────

    private function tabMysql(): void
    {
        // name => [описание, размер в байтах?]
        $key = [
            'max_connections'                => ['Maximum simultaneous client connections', false],
            'max_allowed_packet'             => ['Largest single packet or query', true],
            'innodb_buffer_pool_size'        => ['Memory InnoDB uses to cache data and indexes', true],
            'innodb_redo_log_capacity'       => ['Total size of the InnoDB redo log', true],
            'innodb_flush_log_at_trx_commit' => ['1 = safest, 2 = faster, may lose ~1 s on OS crash', false],
            'tmp_table_size'                 => ['Largest in-memory temporary table', true],
            'max_heap_table_size'            => ['Largest MEMORY table', true],
            'table_open_cache'               => ['Open tables kept in cache', false],
            'thread_cache_size'              => ['Threads kept for reuse', false],
            'wait_timeout'                   => ['Seconds before an idle connection is closed', false],
            'slow_query_log'                 => ['Whether slow queries are logged', false],
            'long_query_time'                => ['Seconds after which a query counts as slow', false],
            'sql_mode'                       => ['SQL modes in effect', false],
        ];
        $status = [
            ['fa-clock',              'Uptime',             $this->uptime($this->st('Uptime'))],
            ['fa-plug',               'Connected now',      $this->num($this->st('Threads_connected'))],
            ['fa-person-running',     'Running now',        $this->num($this->st('Threads_running'))],
            ['fa-arrow-trend-up',     'Peak connections',   $this->num($this->st('Max_used_connections'))],
            ['fa-circle-question',    'Queries',            $this->num($this->st('Questions'))],
            ['fa-hourglass-half',     'Slow queries',       $this->num($this->st('Slow_queries'))],
            ['fa-ban',                'Aborted connects',   $this->num($this->st('Aborted_connects'))],
            ['fa-download',           'Received',           $this->size($this->st('Bytes_received'))],
            ['fa-upload',             'Sent',               $this->size($this->st('Bytes_sent'))],
        ];
        ?>
        <div class="tab-pane fade" id="si-mysql" role="tabpanel" aria-labelledby="si-tab-mysql">
            <div class="si-card">
                <h2 class="si-card-title"><i class="fa-solid fa-chart-simple"></i>Status since last restart</h2>
                <div class="si-stats">
                    <?php foreach ($status as [$ic, $k, $val]): ?>
                        <div class="si-stat"><i class="fa-solid <?= $ic ?>"></i><span><?= $this->e($k) ?></span><strong><?= $this->e($val) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="si-card">
                <h2 class="si-card-title"><i class="fa-solid fa-star"></i>Key settings</h2>
                <div class="si-table-wrap">
                    <table class="si-table">
                        <thead><tr><th>Variable</th><th>Value</th><th>What it does</th></tr></thead>
                        <tbody>
                        <?php foreach ($key as $name => [$desc, $isSize]):
                            if (!isset($this->vars[$name])) continue;
                            $val = $this->vars[$name];
                        ?>
                            <tr>
                                <td><code><?= $this->e($name) ?></code></td>
                                <td><span class="si-val"><?= $this->e($isSize && is_numeric($val) ? $this->size($val) : $val) ?></span></td>
                                <td class="si-muted"><?= $this->e($desc) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="si-card">
                <h2 class="si-card-title"><i class="fa-solid fa-list"></i>All variables <span class="si-card-sub" id="siVarCount"><?= count($this->vars) ?></span></h2>
                <div class="si-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="siVarFilter" placeholder="Filter by name or value" aria-label="Filter variables">
                </div>
                <div class="si-table-wrap si-scroll">
                    <table class="si-table" id="siVarTable">
                        <tbody>
                        <?php foreach ($this->vars as $name => $val): ?>
                            <tr data-search="<?= $this->e(strtolower($name . ' ' . $val)) ?>">
                                <td><code><?= $this->e($name) ?></code></td>
                                <td class="si-break"><?= $this->e($val) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // ── phpinfo() ────────────────────────────────────────

    private function tabPhpinfo(): void
    {
        // INFO_VARIABLES не выводим: там $_COOKIE и $_SERVER с cookie текущей
        // сессии - на скриншоте страницы их легко утащить.
        ob_start();
        phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);
        $html = (string)ob_get_clean();

        $html = preg_replace('~^.*<body[^>]*>(.*)</body>.*$~is', '$1', $html) ?? '';
        // Модуль веб-сервера (apache2handler и т.п.) показывает заголовки
        // запроса - вырезаем строки с cookie и авторизацией.
        $html = preg_replace(
            '~<tr>(?:(?!</tr>).)*?(?:HTTP_COOKIE|HTTP_AUTHORIZATION|>\s*Cookie\s*<|>\s*Authorization\s*<)(?:(?!</tr>).)*</tr>~is',
            '',
            $html
        ) ?? '';
        ?>
        <div class="tab-pane fade" id="si-phpinfo" role="tabpanel" aria-labelledby="si-tab-phpinfo">
            <div class="si-card">
                <div class="si-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="siInfoFilter" placeholder="Filter directives, e.g. upload or curl" aria-label="Filter phpinfo">
                </div>
                <div class="si-modules" id="siModules"></div>
                <div class="si-phpinfo" id="siPhpinfo"><?= $html ?></div>
            </div>
        </div>
        <?php
    }
}

stdhead('Server information');

try {
    (new ServerInfo($db))->render();
} catch (Throwable $e) {
    echo '<div class="container mt-3"><div class="alert alert-danger"><i class="fa-solid fa-circle-xmark me-2"></i>'
       . 'Could not load server information: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div></div>';
}

stdfoot();