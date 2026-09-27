<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// ═══════════════════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Классифицирует строку лога — та же категоризация, что и в write_log()
 * (error/warning/security/general), плюс install-режим и голые PHP Notice.
 */
function lel_classify_line(string $line): string
{
    $l = strtolower($line);
    if (str_contains($l, 'fatal') || str_contains($l, 'sql error') || str_contains($l, '[error]')) {
        return 'error';
    }
    if (str_contains($l, 'warning') || str_contains($l, 'deprecated')) {
        return 'warning';
    }
    if (str_contains($l, 'attempt') || str_contains($l, 'unwanted') || str_contains($l, 'security')) {
        return 'security';
    }
    if (str_contains($l, '[installer]') || str_contains($l, '[install]')) {
        return 'install';
    }
    if (str_contains($l, 'notice')) {
        return 'notice';
    }
    return 'default';
}

function lel_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function lel_time_ago(int $ts): string
{
    if ($ts <= 0) {
        return '—';
    }
    $diff = max(0, time() - $ts);
    return match (true) {
        $diff < 60     => 'just now',
        $diff < 3600   => intdiv($diff, 60) . ' min ago',
        $diff < 86400  => intdiv($diff, 3600) . ' h ago',
        $diff < 604800 => intdiv($diff, 86400) . ' d ago',
        default        => date('Y-m-d', $ts),
    };
}

/**
 * Возвращает реальный путь к логу только если файл есть в списке и лежит
 * строго внутри каталога логов. Иначе null (защита от traversal).
 */
function lel_resolve_log(string $name, string $logDirReal, array $logFiles): ?string
{
    $name = basename($name);
    if ($name === '' || $logDirReal === '' || !in_array($name, $logFiles, true)) {
        return null;
    }
    $path = realpath($logDirReal . DIRECTORY_SEPARATOR . $name);
    if ($path === false || !str_starts_with($path, $logDirReal . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $path;
}

// ═══════════════════════════════════════════════════════════════════════════
//  Список логов (самый свежий — первым)
// ═══════════════════════════════════════════════════════════════════════════

$logDir     = TSDIR . '/error_logs/';
$logDirReal = realpath($logDir) ?: '';

$logMeta = [];
foreach (glob($logDir . '*.log') ?: [] as $path) {
    $logMeta[] = [
        'name'  => basename($path),
        'size'  => (int) (@filesize($path) ?: 0),
        'mtime' => (int) (@filemtime($path) ?: 0),
    ];
}
usort($logMeta, fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
$logFiles  = array_column($logMeta, 'name');
$totalSize = (int) array_sum(array_column($logMeta, 'size'));

$adminName = (string) ($mybb->user['username'] ?? 'unknown');

// ═══════════════════════════════════════════════════════════════════════════
//  Действия — всё ДО stdhead(): download и POST + Post/Redirect/Get
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['download'])) {
    $dlName = basename((string) $_GET['download']);
    $dlPath = lel_resolve_log($dlName, $logDirReal, $logFiles);
    if ($dlPath !== null) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $dlName . '"');
        header('Content-Length: ' . filesize($dlPath));
        header('X-Content-Type-Options: nosniff');
        readfile($dlPath);
        exit;
    }
    http_response_code(404);
    exit('Log file not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_log']) || isset($_POST['delete_all']))) {
    $back = static function (string $status, string $msg) use ($_this_script_): never {
        admin_redirect($_this_script_ . '&status=' . $status . '&msg=' . urlencode($msg));
        exit;
    };

    if (!verify_post_check((string) ($_POST['my_post_key'] ?? ''), true)) {
        $back('danger', 'Security check failed. Please refresh the page and try again.');
    }

    if (isset($_POST['delete_log'])) {
        $name = basename((string) $_POST['delete_log']);
        $path = lel_resolve_log($name, $logDirReal, $logFiles);
        if ($path !== null && @unlink($path)) {
            write_log("Error log {$name} deleted by {$adminName}");
            $back('success', "{$name} deleted successfully.");
        }
        $back('danger', "Failed to delete {$name}.");
    }

    // delete_all
    $deleted = 0;
    foreach ($logFiles as $file) {
        $path = lel_resolve_log($file, $logDirReal, $logFiles);
        if ($path !== null && @unlink($path)) {
            $deleted++;
        }
    }
    if ($deleted > 0) {
        write_log("All error logs ({$deleted} file(s)) deleted by {$adminName}");
        $back('success', "Successfully deleted {$deleted} log file(s).");
    }
    $back('warning', 'No logs were deleted.');
}

// ═══════════════════════════════════════════════════════════════════════════
//  Данные для вывода
// ═══════════════════════════════════════════════════════════════════════════

// Без параметра — сразу открываем самый свежий; пустой ?log= — осознанный сброс.
$selectedLog = isset($_GET['log']) ? basename((string) $_GET['log']) : ($logFiles[0] ?? '');
$invalidLog  = false;
$logPath     = null;

if ($selectedLog !== '') {
    $logPath = lel_resolve_log($selectedLog, $logDirReal, $logFiles);
    if ($logPath === null) {
        $invalidLog  = true;
        $selectedLog = '';
    }
}

$selectedMeta = null;
foreach ($logMeta as $m) {
    if ($m['name'] === $selectedLog) {
        $selectedMeta = $m;
        break;
    }
}

$levels = [
    'error'    => ['label' => 'Error',    'icon' => 'fa-circle-xmark'],
    'warning'  => ['label' => 'Warning',  'icon' => 'fa-triangle-exclamation'],
    'security' => ['label' => 'Security', 'icon' => 'fa-shield-halved'],
    'install'  => ['label' => 'Install',  'icon' => 'fa-screwdriver-wrench'],
    'notice'   => ['label' => 'Notice',   'icon' => 'fa-circle-info'],
    'default'  => ['label' => 'Other',    'icon' => 'fa-align-left'],
];
$levelCounts = array_fill_keys(array_keys($levels), 0);

$logLines   = [];
$logBytes   = 0;
$readFailed = false;

if ($logPath !== null) {
    $raw = is_readable($logPath) ? file_get_contents($logPath) : false;
    if ($raw === false) {
        $readFailed = true;
    } else {
        $logBytes = strlen($raw);
        $split    = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        if ($split !== [] && end($split) === '') {
            array_pop($split);
        }
        foreach ($split as $line) {
            $lvl = lel_classify_line($line);
            $levelCounts[$lvl]++;
            $logLines[] = [$line, $lvl];
        }
        unset($raw, $split);
    }
}

$status = (string) ($_GET['status'] ?? '');
$flash  = in_array($status, ['success', 'danger', 'warning', 'info'], true) && isset($_GET['msg'])
    ? ['status' => $status, 'msg' => (string) $_GET['msg']]
    : null;

$errorCount = $levelCounts['error'];
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// Внешние ассеты: ?v=filemtime — сброс кэша после правок
$lelAsset = static fn(string $rel): string => $BASEURL . $rel . '?v=' . (int) (@filemtime(TSDIR . $rel) ?: 1);

$lelData = [
    'selected'  => $selectedLog,
    'files'     => $logFiles,
    'totalSize' => lel_format_bytes($totalSize),
];

stdhead('View Error Logs');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/view_error_logs.css">


<div class="container mt-3 lel">

    <!-- Header -->
    <div class="lel-card lel-head">
        <div class="lel-ico"><i class="fa-solid fa-bug"></i></div>
        <div>
            <h1>Error Log Viewer</h1>
            <p>PHP and tracker logs from <code>/error_logs</code>, newest file first</p>
        </div>
    </div>

    <?php if ($invalidLog): ?>
        <div class="lel-alert tone-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            That log file doesn't exist or isn't allowed. Pick one from the list below.
        </div>
    <?php endif; ?>

    <!-- KPI tiles -->
    <div class="lel-kpis">
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-primary"><i class="fa-solid fa-folder-open"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val"><?= number_format(count($logFiles)) ?></div>
                <div class="lel-kpi-lbl">Log files</div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-info"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val"><?= lel_format_bytes($totalSize) ?></div>
                <div class="lel-kpi-lbl">Total size on disk</div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val" title="<?= !empty($logMeta[0]['mtime']) ? date('Y-m-d H:i:s', $logMeta[0]['mtime']) : '' ?>">
                    <?= lel_time_ago($logMeta[0]['mtime'] ?? 0) ?>
                </div>
                <div class="lel-kpi-lbl text-truncate" title="<?= $e($logMeta[0]['name'] ?? '') ?>">
                    Last write<?= isset($logMeta[0]) ? ' · ' . $e($logMeta[0]['name']) : '' ?>
                </div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico <?= $errorCount > 0 ? 'tone-danger' : 'tone-success' ?>">
                <i class="fa-solid <?= $errorCount > 0 ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
            </div>
            <div>
                <div class="lel-kpi-val"><?= number_format($errorCount) ?></div>
                <div class="lel-kpi-lbl">Errors in this file</div>
            </div>
        </div>
    </div>

    <?php if (count($logFiles) > 0): ?>
    <!-- Toolbar: file picker + search -->
    <div class="lel-card lel-toolbar">
        <form method="get" action="index.php" class="lel-field lel-field-file">
            <input type="hidden" name="act" value="view_error_logs">
            <i class="fa-solid fa-file-lines"></i>
            <select name="log" id="log" aria-label="Log file">
                <option value="">— choose a log file —</option>
                <?php foreach ($logMeta as $meta): ?>
                    <option value="<?= $e($meta['name']) ?>" <?= $meta['name'] === $selectedLog ? 'selected' : '' ?>>
                        <?= $e($meta['name']) ?>  ·  <?= lel_format_bytes($meta['size']) ?><?= $meta['mtime'] ? '  ·  ' . date('Y-m-d H:i', $meta['mtime']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($logLines !== []): ?>
        <div class="lel-field lel-field-search" id="searchField">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" autocomplete="off" spellcheck="false"
                   placeholder="Search in log — regex supported" aria-label="Search in log">
            <span class="lel-kbd" title="Press / to search">/</span>
            <button type="button" class="lel-x" title="Clear (Esc)" aria-label="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Log content -->
    <?php if ($selectedLog !== '' && $selectedMeta !== null): ?>
        <div class="lel-card lel-panel">
            <div class="lel-panel-head">
                <div class="lel-file">
                    <div class="lel-ico tone-primary"><i class="fa-solid fa-file-code"></i></div>
                    <div class="min-w-0">
                        <div class="lel-file-name"><?= $e($selectedLog) ?></div>
                        <div class="lel-file-meta">
                            <span><i class="fa-solid fa-list-ol"></i><?= number_format(count($logLines)) ?> lines</span>
                            <span><i class="fa-solid fa-weight-hanging"></i><?= lel_format_bytes($logBytes) ?></span>
                            <?php if ($selectedMeta['mtime']): ?>
                                <span title="<?= date('Y-m-d H:i:s', $selectedMeta['mtime']) ?>">
                                    <i class="fa-regular fa-clock"></i><?= lel_time_ago($selectedMeta['mtime']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($logLines !== []): ?>
                <div class="lel-chips" role="toolbar" aria-label="Filter by level">
                    <button type="button" class="lel-chip active" data-level="all">
                        <i class="fa-solid fa-layer-group"></i>All <b><?= number_format(count($logLines)) ?></b>
                    </button>
                    <?php foreach ($levels as $key => $lv): ?>
                        <button type="button" class="lel-chip lvl-<?= $key ?>" data-level="<?= $key ?>"
                                <?= $levelCounts[$key] === 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid <?= $lv['icon'] ?>"></i><?= $lv['label'] ?> <b><?= number_format($levelCounts[$key]) ?></b>
                        </button>
                    <?php endforeach; ?>
                    <span class="lel-found" id="foundCounter" aria-live="polite"></span>
                </div>

                <div class="log-container" id="logOutput">
                    <?php foreach ($logLines as $i => [$line, $lvl]): ?>
                        <div class="log-line lvl-<?= $lvl ?>" data-level="<?= $lvl ?>" data-line="<?= $i + 1 ?>">
                            <span class="line-number"><?= $i + 1 ?></span>
                            <span class="log-lvl"><?php if ($lvl !== 'default'): ?><i class="fa-solid <?= $levels[$lvl]['icon'] ?>" title="<?= $levels[$lvl]['label'] ?>"></i><?php endif; ?></span>
                            <span class="log-content"><?= $e($line) ?></span>
                            <button type="button" class="copy-line-btn" title="Copy line" aria-label="Copy line"><i class="fa-regular fa-copy"></i></button>
                        </div>
                    <?php endforeach; ?>
                    <div class="lel-noresults" id="noResults">
                        <i class="fa-solid fa-magnifying-glass-minus fa-lg mb-2 d-block"></i>No lines match
                    </div>
                </div>
            <?php else: ?>
                <div class="lel-empty">
                    <div class="lel-ico <?= $readFailed ? 'tone-danger' : 'tone-success' ?>">
                        <i class="fa-solid <?= $readFailed ? 'fa-file-circle-xmark' : 'fa-file-circle-check' ?>"></i>
                    </div>
                    <h2><?= $readFailed ? 'Unable to read this file' : 'This log is empty' ?></h2>
                    <p><?= $readFailed ? 'Check file permissions on the server.' : 'Nothing has been written here yet.' ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (count($logFiles) === 0): ?>
        <div class="lel-card lel-empty">
            <div class="lel-ico tone-success"><i class="fa-solid fa-broom"></i></div>
            <h2>No log files</h2>
            <p>The error log directory is clean.</p>
        </div>
    <?php else: ?>
        <div class="lel-card lel-empty">
            <div class="lel-ico tone-primary"><i class="fa-solid fa-hand-pointer"></i></div>
            <h2>No log file selected</h2>
            <p>Choose a file from the list above to view its contents.</p>
        </div>
    <?php endif; ?>

    <!-- Sticky action bar -->
    <?php if (count($logFiles) > 0): ?>
    <div class="lel-card lel-actionbar">
        <div class="lel-actionbar-info">
            <?php if ($selectedLog !== ''): ?>
                <i class="fa-solid fa-file-lines me-1"></i><strong><?= $e($selectedLog) ?></strong>
            <?php else: ?>
                <i class="fa-solid fa-folder me-1"></i><?= count($logFiles) ?> file(s) · <?= lel_format_bytes($totalSize) ?>
            <?php endif; ?>
        </div>

        <?php if ($logLines !== []): ?>
            <button type="button" class="lel-btn lel-btn-icon" id="jumpTop" title="Jump to first line"><i class="fa-solid fa-arrow-up"></i></button>
            <button type="button" class="lel-btn lel-btn-icon" id="jumpBottom" title="Jump to last line"><i class="fa-solid fa-arrow-down"></i></button>
            <span class="lel-sep"></span>
        <?php endif; ?>

        <?php if ($selectedLog !== ''): ?>
            <a href="index.php?act=view_error_logs&amp;download=<?= urlencode($selectedLog) ?>" class="lel-btn">
                <i class="fa-solid fa-download"></i>Download
            </a>
            <button type="button" class="lel-btn lel-btn-danger" data-confirm="one">
                <i class="fa-solid fa-trash-can"></i>Delete
            </button>
        <?php endif; ?>
        <button type="button" class="lel-btn lel-btn-soft-danger" data-confirm="all">
            <i class="fa-solid fa-broom"></i>Delete all
        </button>
    </div>

    <!-- Hidden POST forms (confirmed via SweetAlert2) -->
    <?php if ($selectedLog !== ''): ?>
    <form method="post" id="lelDeleteOne" class="d-none">
        <input type="hidden" name="my_post_key" value="<?= $e((string) $mybb->post_code) ?>">
        <input type="hidden" name="delete_log" value="<?= $e($selectedLog) ?>">
    </form>
    <?php endif; ?>
    <form method="post" id="lelDeleteAll" class="d-none">
        <input type="hidden" name="my_post_key" value="<?= $e((string) $mybb->post_code) ?>">
        <input type="hidden" name="delete_all" value="1">
    </form>
    <?php endif; ?>

    <!-- Toast -->
    <?php if ($flash !== null): ?>
    <div class="position-fixed bottom-0 end-0 p-3 lel-toast-wrap">
        <div class="toast align-items-center text-bg-<?= $flash['status'] ?> border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fa-solid <?= $flash['status'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i><?= $e($flash['msg']) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="application/json" id="lelData"><?= json_encode($lelData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/view_error_logs.js"></script>

<?php stdfoot(); ?>