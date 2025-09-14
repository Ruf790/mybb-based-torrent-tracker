<?php





$logDir = TSDIR . '/error_logs/';






$allowedLogs = glob($logDir . '*.log');
$logFiles = array_map('basename', $allowedLogs);

$selectedLog = isset($_GET['log']) ? basename($_GET['log']) : '';
$logPath = realpath($logDir . $selectedLog);

// Prevent directory traversal
if ($selectedLog && (!in_array($selectedLog, $logFiles) || strpos($logPath, realpath($logDir)) !== 0)) {
    die("Invalid log file.");
}

// Handle delete single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    $deleteLog = basename($_POST['delete_log']);
    $deletePath = realpath($logDir . $deleteLog);
    if (in_array($deleteLog, $logFiles) && strpos($deletePath, realpath($logDir)) === 0 && @unlink($deletePath)) {
        header("Location: ".$_this_script_."&status=success&msg=" . urlencode("$deleteLog deleted."));
    } else {
        header("Location: ".$_this_script_."&status=danger&msg=" . urlencode("Failed to delete $deleteLog."));
    }
    exit;
}

// Handle delete all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $deleted = 0;
    foreach ($logFiles as $logFile) {
        $path = realpath($logDir . $logFile);
        if ($path && strpos($path, realpath($logDir)) === 0 && @unlink($path)) {
            $deleted++;
        }
    }
    $msg = $deleted > 0 ? "Deleted $deleted log(s)." : "No logs were deleted.";
    $status = $deleted > 0 ? "success" : "danger";
    header("Location: ".$_this_script_."&status=$status&msg=" . urlencode($msg));
    exit;
}






stdhead('View Error Logs');


?>

    <title>PHP Error Log Viewer</title>
    
    <style>
        pre {
    background-color: #ffffff; /* White background */
    color: #212529;            /* Dark text color */
    padding: 20px;
    border-radius: 8px;
    max-height: 60vh;
    overflow: auto;
    white-space: pre-wrap;
}

#logOutput mark {
    background-color: yellow;
    color: black;
}
    </style>

<div class="container py-5">
    <h1 class="mb-4 text-center">PHP Error Log Viewer</h1>

    <form method="get" action="index.php" class="row g-3 align-items-end">
	    <input type="hidden" name="act" value="view_error_logs">
        <div class="col-md-5">
            <label for="log" class="form-label">Select Log File:</label>
            <select class="form-select" name="log" id="log">
                <option value="">-- Select a file --</option>
                <?php foreach ($logFiles as $file): ?>
                    <option value="<?= htmlspecialchars($file) ?>" <?= $file === $selectedLog ? 'selected' : '' ?>>
                        <?= htmlspecialchars($file) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit">View</button>
        </div>

        <?php if ($selectedLog): ?>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteSingleModal">Delete</button>
            </div>
        <?php endif; ?>

        <div class="col-md-2">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="autoRefresh">
                <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
            </div>
        </div>

        <div class="col-md-1">
            <?php if (count($logFiles) > 0): ?>
                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAllModal">🗑</button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($selectedLog): ?>
        <div class="mt-4">
            <h5>Viewing: <span class="text-muted"><?= htmlspecialchars($selectedLog) ?></span></h5>
            <input type="text" id="searchInput" class="form-control my-3" placeholder="Search logs...">
            <?php if (is_file($logPath) && is_readable($logPath)): ?>
                <?php $logContents = file_get_contents($logPath); ?>
                <pre id="logOutput"><?= htmlspecialchars($logContents) ?></pre>
            <?php else: ?>
                <div class="alert alert-warning">Unable to read the log file.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Toast -->
<?php if (isset($_GET['status'], $_GET['msg'])): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
    <div class="toast align-items-center text-white bg-<?= htmlspecialchars($_GET['status']) ?> border-0 show" role="alert">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars(urldecode($_GET['msg'])) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Single Modal -->
<div class="modal fade" id="deleteSingleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Log</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong><?= htmlspecialchars($selectedLog) ?></strong>?
            </div>
            <div class="modal-footer">
                <input type="hidden" name="delete_log" value="<?= htmlspecialchars($selectedLog) ?>">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete All Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete All Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                This will delete <strong>all log files</strong>. Are you sure?
            </div>
            <div class="modal-footer">
                <input type="hidden" name="delete_all" value="1">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete All</button>
            </div>
        </form>
    </div>
</div>



<script>
    const searchInput = document.getElementById('searchInput');
    const logOutput = document.getElementById('logOutput');
    const autoRefresh = document.getElementById('autoRefresh');

    searchInput?.addEventListener('input', () => {
        const keyword = searchInput.value.toLowerCase();
        const original = `<?= isset($logContents) ? json_encode($logContents) : '' ?>`;
        const plain = JSON.parse(original);
        if (!keyword) return logOutput.innerHTML = plain;
        const regex = new RegExp(`(${keyword})`, 'gi');
        logOutput.innerHTML = plain.replace(regex, '<mark>$1</mark>');
    });

    setInterval(() => {
        if (autoRefresh?.checked) {
            location.reload();
        }
    }, 5000);

    document.addEventListener('DOMContentLoaded', function () {
        const toastEl = document.querySelector('.toast');
        if (toastEl) {
            new bootstrap.Toast(toastEl, { delay: 4000 }).show();
        }
    });
</script>



<?php stdfoot(); ?>