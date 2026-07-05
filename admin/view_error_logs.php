<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

stdhead('View Error Logs');

$logDir = TSDIR . '/error_logs/';
$allowedLogs = glob($logDir . '*.log') ?: [];
$logFiles = array_map('basename', $allowedLogs);
$selectedLog = isset($_GET['log']) ? basename($_GET['log']) : '';
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Error Log Viewer</title>
    <style>
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            max-height: 70vh;
            overflow: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #212529;
        }
        
        .log-line {
            padding: 2px 0;
            border-bottom: 1px solid transparent;
            transition: all 0.2s ease;
            display: flex;
        }
        
        .log-line:hover {
            background: rgba(0,0,0,0.03);
            border-bottom-color: #dee2e6;
        }
        
        .log-line mark {
            background-color: #ffeb3b;
            color: #000;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 600;
        }
        
        .file-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 500;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .max-h-200 {
            max-height: 200px;
        }
        
        .line-number {
            color: #6c757d;
            font-size: 0.75em;
            width: 50px;
            display: inline-block;
            text-align: right;
            margin-right: 10px;
            user-select: none;
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-file-alt me-2"></i>PHP Error Log Viewer
                    </h1>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-white-50">Total Log Files</h6>
                                        <h3 class="mb-0"><?= count($logFiles) ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-folder fa-2x text-white-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control Panel -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="get" action="index.php" class="row g-3 align-items-end">
                            <input type="hidden" name="act" value="view_error_logs">
                            
                            <div class="col-md-5">
                                <label for="log" class="form-label fw-semibold">Select Log File:</label>
                                <select class="form-select" name="log" id="log" onchange="this.form.submit()">
                                    <option value="">-- Choose a log file --</option>
                                    <?php foreach ($logFiles as $file): ?>
                                        <option value="<?= htmlspecialchars($file) ?>" 
                                                <?= $file === $selectedLog ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($file) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="d-flex gap-2 justify-content-end">
                                    <?php if ($selectedLog): ?>
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
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-eye me-2"></i>Viewing: 
                                    <span class="badge file-badge"><?= htmlspecialchars($selectedLog) ?></span>
                                </h5>
                                <small class="text-muted">
                                    <?= $logContents ? number_format(substr_count($logContents, "\n")) : 0 ?> lines
                                </small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="searchInput" class="form-control" 
                                           placeholder="Search in logs... (supports regex)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                        Clear
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($logContents !== ''): ?>
                                <div class="log-container" id="logOutput">
                                    <?php 
                                    $lines = explode("\n", $logContents);
                                    foreach ($lines as $index => $line): 
                                    ?>
                                        <div class="log-line" data-line="<?= $index + 1 ?>">
                                            <span class="line-number"><?= $index + 1 ?></span>
                                            <span class="log-content"><?= htmlspecialchars($line) ?></span>
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