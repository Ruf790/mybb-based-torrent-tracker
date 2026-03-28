<?php
/**
 * Traceroute Utility v3.0 (AJAX Live)
 */

if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('Access denied');
}

/* ================= AJAX HANDLER ================= */
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $baseDir = __DIR__ . '/cache/traceroute/';
    @mkdir($baseDir, 0777, true);

    /* ---- START TRACE ---- */
    if ($action === 'start') {

        $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $data['host'] ?? '');
        if (!$host) {
            echo json_encode(['error' => 'Invalid host']);
            exit;
        }

        $id   = uniqid('trace_', true);
        $file = $baseDir . $id . '.log';

        $cmd = $isWindows
            ? "tracert -d -h 30 $host"
            : "traceroute -n -m 30 $host";

        if ($isWindows) {
            pclose(popen("start /B $cmd > \"$file\"", "r"));
        } else {
            exec("$cmd > \"$file\" 2>&1 &");
        }

        echo json_encode(['id' => $id]);
        exit;
    }

    /* ---- PROGRESS ---- */
    if ($action === 'progress') {

        $id     = basename($data['id'] ?? '');
        $offset = (int)($data['offset'] ?? 0);
        $file   = $baseDir . $id . '.log';

        if (!file_exists($file)) {
            echo json_encode(['done' => true]);
            exit;
        }

        $size  = filesize($file);
        $chunk = '';

        if ($size > $offset) {
            $fp = fopen($file, 'r');
            fseek($fp, $offset);
            $chunk = fread($fp, $size - $offset);
            fclose($fp);
        }

        $done = preg_match('/(Trace complete|traceroute to)/i', $chunk);

        echo json_encode([
            'chunk' => $chunk,
            'size'  => $size,
            'done'  => $done
        ]);
        exit;
    }
}




/* ================= UI ================= */
stdhead();

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
?>

<div class="container mt-3">
    <div class="card traceroute-card">
        <div class="card-header bg-primary text-white">
            <h1>🌐 Network Traceroute Tool</h1>
            <p class="subtitle">Live AJAX Traceroute</p>
        </div>

        <div class="card-body">

            <form id="traceForm">
                <div class="form-group">
                    <label class="form-label">📍 IP / Host</label>
                    <input type="text" id="host"
                           class="form-control"
                           value="<?= htmlspecialchars($clientIP) ?>"
                           required>
                </div>

                <button class="btn btn-primary btn-lg">
                    🚀 Start Traceroute
                </button>
            </form>

            <pre id="output" class="trace-output"></pre>

        </div>
    </div>
</div>

<script>
let traceId = null;
let offset = 0;
let timer = null;

function formatTrace(text) {
    return text
        .replace(/\*/g, '<span class="timeout">*</span>')
        .replace(/^(\s*\d+)/gm, '<span class="hop">$1</span>')
        .replace(/(\d+\.?\d*\s?ms)/gi, '<span class="time">$1</span>');
}

document.getElementById('traceForm').addEventListener('submit', e => {
    e.preventDefault();

    output.innerHTML = '';
    offset = 0;

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'start',
            host: document.getElementById('host').value
        })
    })
    .then(r => r.json())
    .then(data => {
        traceId = data.id;
        timer = setInterval(loadProgress, 1000);
    });
});

function loadProgress() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'progress',
            id: traceId,
            offset: offset
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.chunk) {
            output.innerHTML += formatTrace(data.chunk);
            offset = data.size;
            output.scrollTop = output.scrollHeight;
        }
        if (data.done) {
            clearInterval(timer);
        }
    });
}
</script>


<style>
.trace-output {
    background: #f8fafc;            /* светлый фон */
    color: #1f2937;                 /* тёмно-серый текст */
    padding: 1.25rem 1.5rem;
    border-radius: 0.75rem;
    height: 350px;
    overflow: auto;
    margin-top: 1.5rem;
    font-family: "SF Mono", Monaco, Consolas, monospace;
    font-size: 0.9rem;
    line-height: 1.55;
    border: 1px solid #e5e7eb;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
    white-space: pre-wrap;
}

/* Немного стилизации строк */
.trace-output span.hop {
    color: #2563eb; /* синий */
}

.trace-output span.time {
    color: #059669; /* зелёный */
}

.trace-output span.timeout {
    color: #dc2626; /* красный */
    font-weight: 600;
}
</style>


<?php stdfoot(); ?>
