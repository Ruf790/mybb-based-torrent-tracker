<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$lang->load('view_error_logs');

// ═══════════════════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в строку ланга. $lang->load() превращает {1} в %1$s,
     * поэтому заменяем оба формата.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string) $arg;
            $map['%' . $n . '$s'] = (string) $arg;
        }
        return strtr($str, $map);
    }
}

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
    global $lang;

    $units = [
        $lang->view_error_logs['unit_b'],
        $lang->view_error_logs['unit_kb'],
        $lang->view_error_logs['unit_mb'],
        $lang->view_error_logs['unit_gb'],
    ];
    if ($bytes <= 0) {
        return '0 ' . $units[0];
    }
    $i = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function lel_time_ago(int $ts): string
{
    global $lang;

    if ($ts <= 0) {
        return '—';
    }
    $diff = max(0, time() - $ts);
    return match (true) {
        $diff < 60     => $lang->view_error_logs['time_just_now'],
        $diff < 3600   => ags_fmt($lang->view_error_logs['time_min_ago'], intdiv($diff, 60)),
        $diff < 86400  => ags_fmt($lang->view_error_logs['time_h_ago'], intdiv($diff, 3600)),
        $diff < 604800 => ags_fmt($lang->view_error_logs['time_d_ago'], intdiv($diff, 86400)),
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
    exit(htmlspecialchars($lang->view_error_logs['err_not_found'], ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_log']) || isset($_POST['delete_all']))) {
    $back = static function (string $status, string $msg) use ($_this_script_): never {
        admin_redirect($_this_script_ . '&status=' . $status . '&msg=' . urlencode($msg));
        exit;
    };

    if (!verify_post_check((string) ($_POST['my_post_key'] ?? ''), true)) {
        $back('danger', $lang->view_error_logs['flash_csrf']);
    }

    if (isset($_POST['delete_log'])) {
        $name = basename((string) $_POST['delete_log']);
        $path = lel_resolve_log($name, $logDirReal, $logFiles);
        if ($path !== null && @unlink($path)) {
            write_log("Error log {$name} deleted by {$adminName}");
            $back('success', ags_fmt($lang->view_error_logs['flash_deleted'], $name));
        }
        $back('danger', ags_fmt($lang->view_error_logs['flash_delete_failed'], $name));
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
        $back('success', ags_fmt($lang->view_error_logs['flash_deleted_all'], $deleted));
    }
    $back('warning', $lang->view_error_logs['flash_none_deleted']);
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
    'error'    => ['label' => $lang->view_error_logs['lvl_error'],    'icon' => 'fa-circle-xmark'],
    'warning'  => ['label' => $lang->view_error_logs['lvl_warning'],  'icon' => 'fa-triangle-exclamation'],
    'security' => ['label' => $lang->view_error_logs['lvl_security'], 'icon' => 'fa-shield-halved'],
    'install'  => ['label' => $lang->view_error_logs['lvl_install'],  'icon' => 'fa-screwdriver-wrench'],
    'notice'   => ['label' => $lang->view_error_logs['lvl_notice'],   'icon' => 'fa-circle-info'],
    'default'  => ['label' => $lang->view_error_logs['lvl_default'],  'icon' => 'fa-align-left'],
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

// Строки для JS: js_* из ланга без префикса
$agsLang = [];
foreach ($lang->view_error_logs as $k => $v) {
    if (str_starts_with((string) $k, 'js_')) {
        $agsLang[substr((string) $k, 3)] = (string) $v;
    }
}

stdhead($lang->view_error_logs['page_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/view_error_logs.css">


<div class="container mt-3 lel">

    <!-- Header -->
    <div class="lel-card lel-head">
        <div class="lel-ico"><i class="fa-solid fa-bug"></i></div>
        <div>
            <h1><?= $e($lang->view_error_logs['head_title']) ?></h1>
            <p><?= $lang->view_error_logs['head_subtitle'] ?></p>
        </div>
    </div>

    <?php if ($invalidLog): ?>
        <div class="lel-alert tone-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= $e($lang->view_error_logs['err_invalid_log']) ?>
        </div>
    <?php endif; ?>

    <!-- KPI tiles -->
    <div class="lel-kpis">
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-primary"><i class="fa-solid fa-folder-open"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val"><?= number_format(count($logFiles)) ?></div>
                <div class="lel-kpi-lbl"><?= $e($lang->view_error_logs['kpi_files']) ?></div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-info"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val"><?= lel_format_bytes($totalSize) ?></div>
                <div class="lel-kpi-lbl"><?= $e($lang->view_error_logs['kpi_total_size']) ?></div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico tone-warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="min-w-0">
                <div class="lel-kpi-val" title="<?= !empty($logMeta[0]['mtime']) ? date('Y-m-d H:i:s', $logMeta[0]['mtime']) : '' ?>">
                    <?= $e(lel_time_ago($logMeta[0]['mtime'] ?? 0)) ?>
                </div>
                <div class="lel-kpi-lbl text-truncate" title="<?= $e($logMeta[0]['name'] ?? '') ?>">
                    <?= $e($lang->view_error_logs['kpi_last_write']) ?><?= isset($logMeta[0]) ? ' · ' . $e($logMeta[0]['name']) : '' ?>
                </div>
            </div>
        </div>
        <div class="lel-card lel-kpi">
            <div class="lel-ico <?= $errorCount > 0 ? 'tone-danger' : 'tone-success' ?>">
                <i class="fa-solid <?= $errorCount > 0 ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
            </div>
            <div>
                <div class="lel-kpi-val"><?= number_format($errorCount) ?></div>
                <div class="lel-kpi-lbl"><?= $e($lang->view_error_logs['kpi_errors']) ?></div>
            </div>
        </div>
    </div>

    <?php if (count($logFiles) > 0): ?>
    <!-- Toolbar: file picker + search -->
    <div class="lel-card lel-toolbar">
        <form method="get" action="index.php" class="lel-field lel-field-file">
            <input type="hidden" name="act" value="view_error_logs">
            <i class="fa-solid fa-file-lines"></i>
            <select name="log" id="log" aria-label="<?= $e($lang->view_error_logs['lbl_log_file']) ?>">
                <option value=""><?= $e($lang->view_error_logs['opt_choose_log']) ?></option>
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
                   placeholder="<?= $e($lang->view_error_logs['hint_search']) ?>" aria-label="<?= $e($lang->view_error_logs['lbl_search']) ?>">
            <span class="lel-kbd" title="<?= $e($lang->view_error_logs['tip_search_key']) ?>">/</span>
            <button type="button" class="lel-x" title="<?= $e($lang->view_error_logs['tip_clear']) ?>" aria-label="<?= $e($lang->view_error_logs['lbl_clear']) ?>">
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
                            <span><i class="fa-solid fa-list-ol"></i><?= $e(ags_fmt($lang->view_error_logs['meta_lines'], number_format(count($logLines)))) ?></span>
                            <span><i class="fa-solid fa-weight-hanging"></i><?= lel_format_bytes($logBytes) ?></span>
                            <?php if ($selectedMeta['mtime']): ?>
                                <span title="<?= date('Y-m-d H:i:s', $selectedMeta['mtime']) ?>">
                                    <i class="fa-regular fa-clock"></i><?= $e(lel_time_ago($selectedMeta['mtime'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($logLines !== []): ?>
                <div class="lel-chips" role="toolbar" aria-label="<?= $e($lang->view_error_logs['lbl_filter_level']) ?>">
                    <button type="button" class="lel-chip active" data-level="all">
                        <i class="fa-solid fa-layer-group"></i><?= $e($lang->view_error_logs['chip_all']) ?> <b><?= number_format(count($logLines)) ?></b>
                    </button>
                    <?php foreach ($levels as $key => $lv): ?>
                        <button type="button" class="lel-chip lvl-<?= $key ?>" data-level="<?= $key ?>"
                                <?= $levelCounts[$key] === 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid <?= $lv['icon'] ?>"></i><?= $e($lv['label']) ?> <b><?= number_format($levelCounts[$key]) ?></b>
                        </button>
                    <?php endforeach; ?>
                    <span class="lel-found" id="foundCounter" aria-live="polite"></span>
                </div>

                <?php $copyTip = $e($lang->view_error_logs['tip_copy_line']); ?>
                <div class="log-container" id="logOutput">
                    <?php foreach ($logLines as $i => [$line, $lvl]): ?>
                        <div class="log-line lvl-<?= $lvl ?>" data-level="<?= $lvl ?>" data-line="<?= $i + 1 ?>">
                            <span class="line-number"><?= $i + 1 ?></span>
                            <span class="log-lvl"><?php if ($lvl !== 'default'): ?><i class="fa-solid <?= $levels[$lvl]['icon'] ?>" title="<?= $e($levels[$lvl]['label']) ?>"></i><?php endif; ?></span>
                            <span class="log-content"><?= $e($line) ?></span>
                            <button type="button" class="copy-line-btn" title="<?= $copyTip ?>" aria-label="<?= $copyTip ?>"><i class="fa-regular fa-copy"></i></button>
                        </div>
                    <?php endforeach; ?>
                    <div class="lel-noresults" id="noResults">
                        <i class="fa-solid fa-magnifying-glass-minus fa-lg mb-2 d-block"></i><?= $e($lang->view_error_logs['no_match']) ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="lel-empty">
                    <div class="lel-ico <?= $readFailed ? 'tone-danger' : 'tone-success' ?>">
                        <i class="fa-solid <?= $readFailed ? 'fa-file-circle-xmark' : 'fa-file-circle-check' ?>"></i>
                    </div>
                    <h2><?= $e($readFailed ? $lang->view_error_logs['empty_read_failed_title'] : $lang->view_error_logs['empty_log_title']) ?></h2>
                    <p><?= $e($readFailed ? $lang->view_error_logs['empty_read_failed_text'] : $lang->view_error_logs['empty_log_text']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (count($logFiles) === 0): ?>
        <div class="lel-card lel-empty">
            <div class="lel-ico tone-success"><i class="fa-solid fa-broom"></i></div>
            <h2><?= $e($lang->view_error_logs['empty_none_title']) ?></h2>
            <p><?= $e($lang->view_error_logs['empty_none_text']) ?></p>
        </div>
    <?php else: ?>
        <div class="lel-card lel-empty">
            <div class="lel-ico tone-primary"><i class="fa-solid fa-hand-pointer"></i></div>
            <h2><?= $e($lang->view_error_logs['empty_select_title']) ?></h2>
            <p><?= $e($lang->view_error_logs['empty_select_text']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Sticky action bar -->
    <?php if (count($logFiles) > 0): ?>
    <div class="lel-card lel-actionbar">
        <div class="lel-actionbar-info">
            <?php if ($selectedLog !== ''): ?>
                <i class="fa-solid fa-file-lines me-1"></i><strong><?= $e($selectedLog) ?></strong>
            <?php else: ?>
                <i class="fa-solid fa-folder me-1"></i><?= $e(ags_fmt($lang->view_error_logs['bar_files_summary'], count($logFiles), lel_format_bytes($totalSize))) ?>
            <?php endif; ?>
        </div>

        <?php if ($logLines !== []): ?>
            <button type="button" class="lel-btn lel-btn-icon" id="jumpTop" title="<?= $e($lang->view_error_logs['tip_jump_top']) ?>"><i class="fa-solid fa-arrow-up"></i></button>
            <button type="button" class="lel-btn lel-btn-icon" id="jumpBottom" title="<?= $e($lang->view_error_logs['tip_jump_bottom']) ?>"><i class="fa-solid fa-arrow-down"></i></button>
            <span class="lel-sep"></span>
        <?php endif; ?>

        <?php if ($selectedLog !== ''): ?>
            <a href="index.php?act=view_error_logs&amp;download=<?= urlencode($selectedLog) ?>" class="lel-btn">
                <i class="fa-solid fa-download"></i><?= $e($lang->view_error_logs['btn_download']) ?>
            </a>
            <button type="button" class="lel-btn lel-btn-danger" data-confirm="one">
                <i class="fa-solid fa-trash-can"></i><?= $e($lang->view_error_logs['btn_delete']) ?>
            </button>
        <?php endif; ?>
        <button type="button" class="lel-btn lel-btn-soft-danger" data-confirm="all">
            <i class="fa-solid fa-broom"></i><?= $e($lang->view_error_logs['btn_delete_all']) ?>
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
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= $e($lang->view_error_logs['lbl_close']) ?>"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="application/json" id="lelData"><?= json_encode($lelData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script>const AGS_LANG = <?= json_encode($agsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/view_error_logs.js?ver=2"></script>

<?php stdfoot(); ?>
