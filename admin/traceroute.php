<?php
/**
 * Traceroute Utility v3.1 (AJAX Live)
 */

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('TR_VERSION', '3.1');

$lang->load('traceroute');

/**
 * Fill {1}, {2}… placeholders. $lang->load() turns {N} into %N$s,
 * so both forms are replaced (strtr, not sprintf: a literal % is safe).
 */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
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

        $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string)($data['host'] ?? ''));
        if (!$host) {
            echo json_encode(['error' => $lang->traceroute['err_invalid_host']], JSON_UNESCAPED_UNICODE);
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

        $id     = basename((string)($data['id'] ?? ''));
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
$clientIP = (string)($_SERVER['REMOTE_ADDR'] ?? $lang->traceroute['lbl_unknown_ip']);

// JS strings: js_* keys → array without the prefix
$js_lang = [];
foreach ($lang->traceroute as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $js_lang[substr((string)$k, 3)] = $v;
    }
}

stdhead($lang->traceroute['page_title']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/traceroute.css?v=' . TR_VERSION . '">';
?>
<script>
const AGS_LANG = <?= json_encode($js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php
echo '<script src="' . $BASEURL . '/admin/scripts/traceroute.js?v=' . TR_VERSION . '" defer></script>';
?>

<div class="container mt-3 py-4 trc-page">

    <!-- Header -->
    <div class="trc-card trc-head mb-3">
        <div class="trc-head__icon"><i class="fa-solid fa-route"></i></div>
        <div class="flex-grow-1">
            <h1 class="trc-title"><?= htmlspecialchars($lang->traceroute['sec_title']) ?></h1>
            <p><?= htmlspecialchars($lang->traceroute['sec_subtitle']) ?></p>
        </div>
        <span class="trc-badge trc-soft-info d-none d-md-inline-flex"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars(ags_fmt($lang->traceroute['badge_your_ip'], $clientIP)) ?></span>
    </div>

    <!-- Form -->
    <div class="trc-card trc-form mb-3">
        <form id="traceForm">
            <div class="row g-3 align-items-end">
                <div class="col">
                    <label class="form-label" for="host"><i class="fa-solid fa-globe me-1"></i><?= htmlspecialchars($lang->traceroute['lbl_host']) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-network-wired"></i></span>
                        <input type="text" id="host"
                               class="form-control"
                               value="<?= htmlspecialchars($clientIP, ENT_QUOTES) ?>"
                               placeholder="<?= htmlspecialchars($lang->traceroute['ph_host'], ENT_QUOTES) ?>"
                               required>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary trc-pill w-100">
                        <i class="fa-solid fa-play me-1"></i><?= htmlspecialchars($lang->traceroute['btn_start']) ?>
                    </button>
                </div>
            </div>
            <div class="trc-meta mt-2"><i class="fa-solid fa-circle-info me-1"></i><?= htmlspecialchars($lang->traceroute['hint_host']) ?></div>
        </form>
    </div>

    <!-- Output -->
    <div class="trc-card overflow-hidden">
        <div class="trc-toolbar">
            <span><i class="fa-solid fa-terminal me-1"></i><?= htmlspecialchars($lang->traceroute['sec_output']) ?></span>
            <span id="traceStatus" class="trc-badge trc-soft-muted"><i class="fa-solid fa-circle-pause"></i><span class="trc-status-text"><?= htmlspecialchars($lang->traceroute['js_status_ready']) ?></span></span>
        </div>

        <div id="traceEmpty" class="trc-empty">
            <div class="trc-empty__icon trc-soft-muted"><i class="fa-solid fa-satellite-dish"></i></div>
            <p class="text-body-secondary mb-0"><?= htmlspecialchars($lang->traceroute['empty_text']) ?></p>
        </div>

        <pre id="output" class="trace-output" hidden></pre>
    </div>
</div>

<?php stdfoot(); ?>
