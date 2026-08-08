<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$logDir = TSDIR . '/error_logs/';
$allowedLogPaths = glob($logDir . '*.log') ?: [];

// ── Собираем файлы с метаданными (размер, mtime) и сортируем по свежести ──
// Раньше порядок в списке был "как отдаёт glob()" (обычно алфавитный) —
// самый свежий лог (тот, что реально нужен сразу после бага) мог оказаться
// где угодно в списке. Теперь — самый недавно изменённый всегда первый.
$logMeta = [];
foreach ($allowedLogPaths as $path) {
    $logMeta[] = [
        'name'  => basename($path),
        'size'  => @filesize($path) ?: 0,
        'mtime' => @filemtime($path) ?: 0,
    ];
}
usort($logMeta, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
$logFiles  = array_column($logMeta, 'name');
$totalSize = array_sum(array_column($logMeta, 'size'));

// ── Скачивание лога целиком ─────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $dlName = basename($_GET['download']);
    $dlPath = realpath($logDir . $dlName);
    if ($dlName && in_array($dlName, $logFiles, true) && $dlPath && str_starts_with($dlPath, realpath($logDir))) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $dlName . '"');
        header('Content-Length: ' . filesize($dlPath));
        readfile($dlPath);
        exit;
    }
    http_response_code(404);
    exit('Log file not found.');
}

stdhead('View Error Logs');

// По умолчанию — сразу открываем самый свежий лог, а не пустой выбор.
// isset($_GET['log']) с пустым значением ('') — это осознанный сброс выбора
// (например, после удаления файла), отличаем от полного отсутствия параметра.
$selectedLog = isset($_GET['log']) ? basename($_GET['log']) : ($logFiles[0] ?? '');
$logPath = realpath($logDir . $selectedLog);

// Security: Prevent directory traversal
if ($selectedLog && (!in_array($selectedLog, $logFiles) || !str_starts_with($logPath, realpath($logDir)))) {
    die("Invalid log file.");
}

// Handle delete single log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        die('Security check failed. Please refresh the page and try again.');
    }

    $deleteLog = basename($_POST['delete_log']);
    $deletePath = realpath($logDir . $deleteLog);
    
    if (in_array($deleteLog, $logFiles) && 
        str_starts_with($deletePath, realpath($logDir)) && 
        @unlink($deletePath)) {
        // Редирект без параметра log
        $redirectUrl = $_this_script_ . "&status=success&msg=" . urlencode("$deleteLog deleted successfully.");
        admin_redirect($redirectUrl);
    } else {
        $redirectUrl = $_this_script_ . "&status=danger&msg=" . urlencode("Failed to delete $deleteLog.");
        admin_redirect($redirectUrl);
    }
}

// Handle delete all logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        die('Security check failed. Please refresh the page and try again.');
    }

    $deleted = 0;
    foreach ($logFiles as $logFile) {
        $path = realpath($logDir . $logFile);
        if ($path && str_starts_with($path, realpath($logDir)) && @unlink($path)) {
            $deleted++;
        }
    }
    
    $msg = $deleted > 0 ? "Successfully deleted $deleted log file(s)." : "No logs were deleted.";
    $status = $deleted > 0 ? "success" : "warning";
    
    // Редирект без параметра log
    $redirectUrl = $_this_script_ . "&status=$status&msg=" . urlencode($msg);
    admin_redirect($redirectUrl);
}

// Если выбранный файл был удален, сбрасываем выбор
if ($selectedLog && !in_array($selectedLog, $logFiles)) {
    $selectedLog = '';
    $logPath = '';
}

// Read log contents if file is selected
$logContents = '';
if ($selectedLog && is_file($logPath) && is_readable($logPath)) {
    $logContents = file_get_contents($logPath) ?: '';
}

/**
 * Классифицирует строку лога по ключевым словам — та же категоризация,
 * что и в write_log() (error/warning/security/general), плюс install-режим
 * и голые PHP Fatal/Notice, которые сюда тоже сыплются через error_log().
 */
function classify_log_line(string $line): string
{
    $l = strtolower($line);
    if (str_contains($l, 'fatal') || str_contains($l, 'sql error') || str_contains($l, '[error]')) {
        return 'lvl-error';
    }
    if (str_contains($l, 'warning') || str_contains($l, 'deprecated')) {
        return 'lvl-warning';
    }
    if (str_contains($l, 'attempt') || str_contains($l, 'unwanted') || str_contains($l, 'security')) {
        return 'lvl-security';
    }
    if (str_contains($l, '[installer]') || str_contains($l, '[install]')) {
        return 'lvl-install';
    }
    if (str_contains($l, 'notice')) {
        return 'lvl-notice';
    }
    return 'lvl-default';
}

function format_bytes(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Error Log Viewer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: var(--bs-primary, #0d6efd);
            --accent-dark: var(--bs-primary-text-emphasis, #0a58ca);
            --accent-soft: var(--bs-primary-bg-subtle, rgba(13, 110, 253, .1));
        }

        .lel-masthead {
            padding: 1.75rem 1.9rem;
            margin-bottom: 1.5rem;
            background: #fff;
            border: 1px solid #e9e7fa;
            border-radius: .9rem;
        }
        .lel-masthead__eyebrow {
            display: inline-block;
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent-dark);
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: 999px;
            padding: .3rem .85rem;
            margin-bottom: .75rem;
        }
        .lel-masthead__title {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            font-size: clamp(1.4rem, 3vw, 1.9rem);
            margin: 0;
            color: #201d3d;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            border: none;
            color: #fff;
            border-radius: .9rem;
        }
        .stat-card .stat-label {
            font-family: 'Oswald', sans-serif;
            font-size: .72rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            opacity: .8;
        }
        .stat-card .stat-value {
            font-family: 'Oswald', sans-serif;
            font-weight: 700;
            font-size: 1.9rem;
        }

        .lel-panel {
            border: 1px solid #e9e7fa !important;
            border-radius: .9rem;
            overflow: hidden;
        }
        .lel-panel .card-header {
            background: #faf9ff !important;
            border-bottom: 1px solid #e9e7fa;
            border-left: 4px solid var(--accent);
        }
        .lel-panel .card-header h5 {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            font-size: 1rem;
        }

        .file-badge {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            font-weight: 500;
        }

        .log-container {
            background: #f7f7fb;
            border-radius: 0 0 .9rem .9rem;
            padding: 1rem 0;
            max-height: 68vh;
            overflow: auto;
            font-family: 'JetBrains Mono', 'Monaco', monospace;
            font-size: .82rem;
            line-height: 1.6;
        }

        .log-line {
            padding: .1rem 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            border-left: 3px solid transparent;
            color: #33304d;
            position: relative;
        }
        .log-line:hover {
            background: rgba(99, 102, 241, .05);
        }
        .log-line:hover .copy-line-btn { opacity: 1; }

        .log-line.lvl-error    { border-left-color: #dc3545; }
        .log-line.lvl-warning  { border-left-color: #d39e00; }
        .log-line.lvl-security { border-left-color: #d6336c; }
        .log-line.lvl-install  { border-left-color: #0d6efd; }
        .log-line.lvl-notice   { border-left-color: #0f9d68; }

        .log-line.lvl-error    .log-content { color: #b02a37; }
        .log-line.lvl-warning  .log-content { color: #96780a; }
        .log-line.lvl-security .log-content { color: #a8285a; }
        .log-line.lvl-install  .log-content { color: #0a58ca; }
        .log-line.lvl-notice   .log-content { color: #0c7d54; }

        .log-line mark {
            background-color: #fff3a3;
            color: #1a1a1a;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 600;
        }

        .line-number {
            color: #9691b8;
            font-size: .78em;
            width: 46px;
            flex-shrink: 0;
            text-align: right;
            user-select: none;
        }

        .log-content {
            flex: 1;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .copy-line-btn {
            opacity: 0;
            flex-shrink: 0;
            background: rgba(99, 102, 241, .08);
            border: none;
            color: #6b6690;
            border-radius: .4rem;
            width: 26px;
            height: 26px;
            font-size: .75rem;
            transition: opacity .15s ease, background .15s ease, color .15s ease;
        }
        .copy-line-btn:hover {
            background: var(--accent);
            color: #fff;
        }
        .copy-line-btn.copied {
            opacity: 1;
            background: #10b981;
            color: #fff;
        }

        .max-h-200 { max-height: 200px; }

        .btn-accent {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            border: none;
            color: #fff;
        }
        .btn-accent:hover { color: #fff; opacity: .92; }

        .log-select-item {
            font-family: 'JetBrains Mono', monospace;
            font-size: .82rem;
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">

                <div class="lel-masthead">
                    <span class="lel-masthead__eyebrow">Admin / Diagnostics</span>
                    <h1 class="lel-masthead__title"><i class="fas fa-file-alt me-2"></i>PHP Error Log Viewer</h1>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-label">Total Log Files</div>
                                        <div class="stat-value"><?= count($logFiles) ?></div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-folder fa-2x" style="opacity:.5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-label">Total Size</div>
                                        <div class="stat-value"><?= format_bytes($totalSize) ?></div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-database fa-2x" style="opacity:.5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-label">Most Recent</div>
                                        <div class="stat-value" style="font-size:1.15rem;word-break:break-all;">
                                            <?= htmlspecialchars($logMeta[0]['name'] ?? '—') ?>
                                        </div>
                                        <?php if (!empty($logMeta[0]['mtime'])): ?>
                                        <small style="opacity:.75"><?= date('Y-m-d H:i:s', $logMeta[0]['mtime']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x" style="opacity:.5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control Panel -->
                <div class="card lel-panel shadow-sm mb-4">
                    <div class="card-body">
                        <form method="get" action="index.php" class="row g-3 align-items-end">
                            <input type="hidden" name="act" value="view_error_logs">
                            
                            <div class="col-md-5">
                                <label for="log" class="form-label fw-semibold">Select Log File <small class="text-muted fw-normal">(newest first)</small>:</label>
                                <select class="form-select log-select-item" name="log" id="log" onchange="this.form.submit()">
                                    <option value="">-- Choose a log file --</option>
                                    <?php foreach ($logMeta as $meta): ?>
                                        <option value="<?= htmlspecialchars($meta['name']) ?>"
                                                <?= $meta['name'] === $selectedLog ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($meta['name']) ?> — <?= format_bytes($meta['size']) ?><?= $meta['mtime'] ? ' — ' . date('Y-m-d H:i', $meta['mtime']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                    <?php if ($selectedLog): ?>
                                        <a href="?act=view_error_logs&download=<?= urlencode($selectedLog) ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteSingleModal">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                    <?php if (count($logFiles) > 0): ?>
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                                            <i class="fas fa-broom me-1"></i>Delete All
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Log Content -->
                <?php if ($selectedLog): ?>
                    <div class="card lel-panel shadow-sm">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">
                                    <i class="fas fa-eye me-2"></i>Viewing: 
                                    <span class="badge file-badge"><?= htmlspecialchars($selectedLog) ?></span>
                                </h5>
                                <small class="text-muted">
                                    <?= $logContents ? number_format(substr_count($logContents, "\n")) : 0 ?> lines
                                    &middot; <?= format_bytes(strlen($logContents)) ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-white">
                                <div class="d-flex gap-3 flex-wrap align-items-center">
                                    <div class="input-group flex-grow-1" style="min-width:260px;">
                                        <span class="input-group-text bg-white">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" id="searchInput" class="form-control" 
                                               placeholder="Search in logs... (supports regex)">
                                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                            Clear
                                        </button>
                                    </div>
                                    <div class="d-flex gap-2 small">
                                        <span class="badge" style="background:#dc3545">Error</span>
                                        <span class="badge" style="background:#d39e00">Warning</span>
                                        <span class="badge" style="background:#d6336c">Security</span>
                                        <span class="badge" style="background:#0d6efd">Install</span>
                                        <span class="badge" style="background:#0f9d68">Notice</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($logContents !== ''): ?>
                                <div class="log-container" id="logOutput">
                                    <?php 
                                    $lines = explode("\n", $logContents);
                                    foreach ($lines as $index => $line): 
                                        if ($line === '' && $index === count($lines) - 1) continue;
                                        $levelClass = classify_log_line($line);
                                    ?>
                                        <div class="log-line <?= $levelClass ?>" data-line="<?= $index + 1 ?>">
                                            <span class="line-number"><?= $index + 1 ?></span>
                                            <span class="log-content"><?= htmlspecialchars($line) ?></span>
                                            <button type="button" class="copy-line-btn" title="Copy line" onclick="copyLogLine(this)">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-excel fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Unable to read the log file or file is empty.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No log file selected</h4>
                        <p class="text-muted">Please select a log file from the dropdown above to view its contents.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <?php if (isset($_GET['status'], $_GET['msg'])): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
        <div class="toast align-items-center text-white bg-<?= htmlspecialchars($_GET['status']) ?> border-0 show" 
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-<?= $_GET['status'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars(urldecode($_GET['msg'])) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Single Modal -->
    <?php if ($selectedLog): ?>
    <div class="modal fade" id="deleteSingleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Log File
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the following log file?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-file me-2"></i>
                        <strong><?= htmlspecialchars($selectedLog) ?></strong>
                    </div>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <input type="hidden" name="delete_log" value="<?= htmlspecialchars($selectedLog) ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete File
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete All Modal -->
    <?php if (count($logFiles) > 0): ?>
    <div class="modal fade" id="deleteAllModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-radiation me-2"></i>Delete All Log Files
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This will permanently delete <strong>all <?= count($logFiles) ?> log files</strong>:</p>
                    <div class="alert alert-danger small max-h-200 overflow-auto">
                        <ul class="mb-0">
                            <?php foreach ($logFiles as $file): ?>
                                <li><?= htmlspecialchars($file) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <p class="text-muted small mt-3">This action cannot be undone and will remove all log history.</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-broom me-1"></i>Delete All Files
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const logOutput = document.getElementById('logOutput');

        function performSearch() {
            const keyword = searchInput.value.trim();
            const logLines = logOutput?.querySelectorAll('.log-line');
            
            if (!logLines || !keyword) {
                logLines?.forEach(line => {
                    line.style.display = 'flex';
                    const contentSpan = line.querySelector('.log-content');
                    if (contentSpan) {
                        contentSpan.innerHTML = contentSpan.textContent;
                    }
                });
                return;
            }

            try {
                const regex = new RegExp(`(${keyword})`, 'gi');
                let found = false;
                
                logLines.forEach(line => {
                    const contentSpan = line.querySelector('.log-content');
                    if (!contentSpan) return;
                    
                    const originalContent = contentSpan.getAttribute('data-original') || contentSpan.textContent;
                    contentSpan.setAttribute('data-original', originalContent);
                    
                    const text = contentSpan.textContent;
                    if (regex.test(text)) {
                        contentSpan.innerHTML = originalContent.replace(regex, '<mark>$1</mark>');
                        line.style.display = 'flex';
                        found = true;
                    } else {
                        line.style.display = 'none';
                    }
                });

                // Remove existing no results message
                const existingMessage = logOutput.querySelector('.no-results-message');
                if (existingMessage) {
                    existingMessage.remove();
                }

                if (!found) {
                    const message = document.createElement('div');
                    message.className = 'no-results-message text-center text-muted py-3';
                    message.textContent = 'No matches found';
                    logOutput.appendChild(message);
                }
            } catch (e) {
                // If regex fails, fall back to simple text search
                logLines.forEach(line => {
                    const contentSpan = line.querySelector('.log-content');
                    if (!contentSpan) return;
                    
                    const originalContent = contentSpan.getAttribute('data-original') || contentSpan.textContent;
                    contentSpan.setAttribute('data-original', originalContent);
                    
                    const text = contentSpan.textContent.toLowerCase();
                    if (text.includes(keyword.toLowerCase())) {
                        const escapedKeyword = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        const simpleRegex = new RegExp(`(${escapedKeyword})`, 'gi');
                        contentSpan.innerHTML = originalContent.replace(simpleRegex, '<mark>$1</mark>');
                        line.style.display = 'flex';
                    } else {
                        line.style.display = 'none';
                    }
                });
            }
        }

        function clearSearch() {
            if (searchInput) {
                searchInput.value = '';
                performSearch();
            }
        }

        searchInput?.addEventListener('input', performSearch);
        searchInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

        // Copy single line to clipboard
        function copyLogLine(btn) {
            const line = btn.closest('.log-line');
            const text = line.querySelector('.log-content')?.textContent ?? '';
            navigator.clipboard.writeText(text).then(() => {
                const icon = btn.querySelector('i');
                icon.classList.remove('fa-copy');
                icon.classList.add('fa-check');
                btn.classList.add('copied');
                setTimeout(() => {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-copy');
                    btn.classList.remove('copied');
                }, 1200);
            }).catch(() => {
                // Fallback for non-secure contexts / older browsers
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
            });
        }

        // Initialize Bootstrap toasts
        document.addEventListener('DOMContentLoaded', function () {
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                new bootstrap.Toast(toastEl, { delay: 5000 }).show();
            }
        });

        // Auto-focus search input when log is selected
        <?php if ($selectedLog): ?>
            setTimeout(() => {
                searchInput?.focus();
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>

<?php stdfoot(); ?>