<?php
declare(strict_types=1);

$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';

if ($usergroups['cansettingspanel'] != '1') {
    stdhead();
    error_no_permission(true);
    exit;
}

// ── Session history ───────────────────────────────────────────────────────────
if (!isset($_SESSION['query_history'])) {
    $_SESSION['query_history'] = [];
}

// Clear history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'ts_clear_history') {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        stdhead('SQL Query Editor');
        echo '<div class="container mt-4"><div class="alert alert-danger">Security check failed. Please refresh the page and try again.</div></div>';
        stdfoot();
        exit;
    }
    $_SESSION['query_history'] = [];
    header('Location: ' . $_this_script_);
    exit;
}

// ── DB connection ─────────────────────────────────────────────────────────────
if (!isset($GLOBALS['mysqli']) || !($GLOBALS['mysqli'] instanceof mysqli)) {
    $GLOBALS['mysqli'] = new mysqli(
        $config['database']['hostname'],
        $config['database']['username'],
        $config['database']['password'],
        $config['database']['database']
    );
}

$mysqli   = $GLOBALS['mysqli'];
$db_ok    = !$mysqli->connect_errno;
$db_name  = $config['database']['database'];

if (!$db_ok) {
    write_log('SQL Query Editor: DB connection failed — ' . $mysqli->connect_error);
}

// ── Query execution ───────────────────────────────────────────────────────────
$query       = '';
$alert       = '';
$table       = '';
$exec_time   = null;
$rows_info   = '';
$needsConfirm = false;

const DESTRUCTIVE_QUERY_PATTERN = '/^\s*(DROP|DELETE|TRUNCATE|UPDATE|ALTER)\b/i';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'ts_execute_sql_query') {
    if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'])) {
        stdhead('SQL Query Editor');
        echo '<div class="container mt-4"><div class="alert alert-danger">Security check failed. Please refresh the page and try again.</div></div>';
        stdfoot();
        exit;
    }

    $query = trim($_POST['query'] ?? '');

    if (!empty($query) && preg_match(DESTRUCTIVE_QUERY_PATTERN, $query) && ($_POST['confirm_destructive'] ?? '') !== '1') {
        $alert = 'warning';
        $rows_info = 'This query looks destructive (DROP/DELETE/TRUNCATE/UPDATE/ALTER). Confirm the dialog to run it.';
        $needsConfirm = true;
    } elseif (!empty($query)) {
        // Save to history
        array_unshift($_SESSION['query_history'], [
            'query'     => $query,
            'timestamp' => time(),
        ]);
        $_SESSION['query_history'] = array_slice($_SESSION['query_history'], 0, 20);

        // Аудит: кто и какой именно SQL выполнил — отдельно от истории в сессии,
        // которая пропадает вместе с сессией и не видна другим админам.
        write_log('SQL Query Editor: ' . $CURUSER['username'] . ' executed: ' . str_replace(["\r", "\n"], ' ', $query));

        $t0      = microtime(true);
        $result  = $mysqli->query($query);
        $exec_time = round((microtime(true) - $t0) * 1000, 2);

        if ($result === false) {
            $alert = 'danger';
            $rows_info = htmlspecialchars($mysqli->error);
        } elseif ($result instanceof \mysqli_result) {
            $num = $result->num_rows;
            $alert = 'success';
            $rows_info = "{$num} row(s) returned";

            if ($num > 0) {
                $table = '<div class="qe-result-scroll"><table class="qe-table" id="qeResultTable">';
                $first = true;
                $rowNum = 0;
                while ($row = $result->fetch_assoc()) {
                    $rowNum++;
                    if ($first) {
                        $table .= '<thead><tr><th class="qe-col-num">#</th>';
                        foreach (array_keys($row) as $col) {
                            $table .= '<th>' . htmlspecialchars($col) . '</th>';
                        }
                        $table .= '</tr></thead><tbody>';
                        $first = false;
                    }
                    $table .= '<tr><td class="qe-col-num">' . $rowNum . '</td>';
                    foreach ($row as $val) {
                        $display = $val === null ? '<em class="qe-null">NULL</em>' : htmlspecialchars((string)$val);
                        $table .= '<td>' . $display . '</td>';
                    }
                    $table .= '</tr>';
                }
                $table .= '</tbody></table></div>';
            } else {
                $alert = 'info';
                $rows_info = 'Query returned no rows.';
            }
        } else {
            $affected = $mysqli->affected_rows;
            $alert    = 'success';
            $rows_info = max(0, $affected) . ' row(s) affected';
        }
    } else {
        $alert     = 'warning';
        $rows_info = 'Empty query — nothing to execute.';
    }
}

stdhead('SQL Query Editor');
?>
<style>
/* ── Layout ── */
.qe-wrap {
    max-width: 1100px;
    margin: 2rem auto;
    padding: 0 1rem;
    font-size: 1rem;
}

/* ── Card ── */
.qe-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
    margin-bottom: 1.5rem;
}

.qe-card-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    background: var(--bs-primary);
    color: #fff;
}
.qe-card-header h2 {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    flex: 1;
}
.qe-card-header p {
    font-size: .82rem;
    opacity: .75;
    margin: 0;
}
.qe-card-header-text { flex: 1; }

.qe-card-body { padding: 1.75rem; }

/* ── Status pill ── */
.qe-status {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .3rem .85rem;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 600;
    margin-bottom: 1.25rem;
}
.qe-status-ok  { background: #dcfce7; color: #14532d; }
.qe-status-err { background: #fef2f2; color: #7f1d1d; }
.qe-status-ok i { animation: qe-pulse 2s ease-in-out infinite; }
@keyframes qe-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .35; }
}

/* ── Editor ── */
.qe-editor-wrap {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
    margin-bottom: 1rem;
}
.qe-editor-wrap:focus-within {
    border-color: #1a56db;
    box-shadow: 0 0 0 3px rgba(26,86,219,.12);
}

#query {
    width: 100%;
    min-height: 200px;
    padding: 1.1rem;
    font-family: "Fira Code", "Cascadia Code", "Courier New", monospace;
    font-size: 1rem;
    line-height: 1.6;
    color: #1e293b;
    background: #fafafa;
    border: none;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
}
#query:focus { background: #fff; }

/* ── Toolbar ── */
.qe-toolbar {
    background: #f8fafc;
    border-top: 1px solid #e9ecef;
    padding: .85rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .6rem;
}

.qe-tool-group { display: flex; gap: .4rem; flex-wrap: wrap; }

.qe-tool-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .4rem .9rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: .875rem;
    font-weight: 500;
    color: #374151;
    background: #fff;
    cursor: pointer;
    transition: all .15s;
}
.qe-tool-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }

.qe-run-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1.4rem;
    background: linear-gradient(135deg, #1a56db, #1648c0);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s;
    box-shadow: 0 2px 8px rgba(26,86,219,.25);
}
.qe-run-btn:hover {
    background: linear-gradient(135deg, #1648c0, #1e40af);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(26,86,219,.35);
}

/* ── Examples ── */
.qe-examples {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #1a56db;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-top: 1.25rem;
}
.qe-examples h6 {
    font-size: .9rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: .6rem;
}
.qe-ex-btn {
    display: inline-flex;
    align-items: center;
    margin: .2rem;
    padding: .3rem .8rem;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: .8rem;
    font-family: "Fira Code", monospace;
    color: #374151;
    background: #fff;
    cursor: pointer;
    transition: all .15s;
}
.qe-ex-btn:hover { background: #1a56db; color: #fff; border-color: #1a56db; }

/* ── Result card ── */
.qe-result-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    margin-top: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
}

.qe-result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
    flex-wrap: wrap;
    gap: .5rem;
}
.qe-result-title {
    font-size: .9rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: .4rem;
}

.qe-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .75rem;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 600;
}
.qe-badge-success { background: #dcfce7; color: #14532d; }
.qe-badge-danger  { background: #fef2f2; color: #7f1d1d; }
.qe-badge-info    { background: #eff6ff; color: #1e3a8a; }
.qe-badge-warning { background: #fffbeb; color: #78350f; }
.qe-badge-time    { background: #f1f5f9; color: #475569; }

.qe-result-body { padding: 1.25rem; }

/* ── Table ── */
.qe-result-scroll {
    overflow: auto;
    max-height: 520px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}
.qe-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
}
.qe-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f1f5f9;
    color: #374151;
    font-weight: 600;
    padding: .6rem 1rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.qe-table .qe-col-num {
    color: #94a3b8;
    font-weight: 500;
    text-align: right;
    width: 1%;
    background: #fafbfc;
}
.qe-table thead th.qe-col-num { background: #f1f5f9; }
.qe-table tbody tr { transition: background .1s; }
.qe-table tbody tr:hover { background: #f8fafc; }
.qe-table tbody td {
    padding: .55rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: "Fira Code", monospace;
    font-size: .875rem;
}
.qe-table tbody tr:last-child td { border-bottom: none; }
.qe-null { color: #94a3b8; font-style: italic; }

/* ── Alert messages ── */
.qe-msg {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    padding: .85rem 1rem;
    border-radius: 10px;
    font-size: .95rem;
}
.qe-msg i { font-size: 1.1rem; flex-shrink: 0; margin-top: .05rem; }
.qe-msg-success { background: #f0fdf4; color: #14532d; border: 1px solid #bbf7d0; }
.qe-msg-danger  { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
.qe-msg-info    { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
.qe-msg-warning { background: #fffbeb; color: #78350f; border: 1px solid #fde68a; }

/* ── History modal ── */
.qe-history-item {
    padding: .85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background .15s;
    border-left: 3px solid transparent;
}
.qe-history-item:last-child { border-bottom: none; }
.qe-history-item:hover {
    background: #f8fafc;
    border-left-color: #1a56db;
}
.qe-history-query {
    font-family: "Fira Code", monospace;
    font-size: .85rem;
    color: #374151;
    margin: .2rem 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.qe-history-meta {
    font-size: .75rem;
    color: #94a3b8;
    margin-bottom: .15rem;
}

@media (max-width: 640px) {
    .qe-toolbar { flex-direction: column; align-items: stretch; }
    .qe-run-btn { justify-content: center; }
    .qe-card-body { padding: 1.1rem; }
}
</style>

<div class="qe-wrap">

    <!-- Main card -->
    <div class="qe-card">
        <div class="qe-card-header">
            <i class="bi bi-terminal-fill" style="font-size:1.3rem"></i>
            <div class="qe-card-header-text">
                <h2>SQL Query Editor</h2>
                <p>Execute queries and inspect results — admin only</p>
            </div>
        </div>

        <div class="qe-card-body">

            <!-- Connection status -->
            <div class="qe-status <?= $db_ok ? 'qe-status-ok' : 'qe-status-err' ?>">
                <i class="bi <?= $db_ok ? 'bi-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                <?= $db_ok
                    ? 'Connected &rarr; <strong>' . htmlspecialchars($db_name) . '</strong>'
                    : 'Database connection failed. See server log for details.' ?>
            </div>

            <!-- Form -->
            <form method="post" id="qeForm">
                <input type="hidden" name="do" value="ts_execute_sql_query">
                <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                <input type="hidden" name="confirm_destructive" id="confirmDestructive" value="0">

                <div class="qe-editor-wrap">
                    <textarea name="query" id="query"
                              placeholder="-- Enter your SQL query here&#10;SELECT * FROM users LIMIT 10;"
                    ><?= htmlspecialchars($query) ?></textarea>

                    <div class="qe-toolbar">
                        <div class="qe-tool-group">
                            <button type="button" class="qe-tool-btn" id="qeFormat">
                                <i class="bi bi-text-indent-left"></i> Format
                            </button>
                            <button type="button" class="qe-tool-btn" id="qeHistory"
                                    data-bs-toggle="modal" data-bs-target="#histModal">
                                <i class="bi bi-clock-history"></i>
                                History
                                <?php if (!empty($_SESSION['query_history'])): ?>
                                <span style="background:#1a56db;color:#fff;border-radius:10px;
                                             padding:1px 7px;font-size:.7rem">
                                    <?= count($_SESSION['query_history']) ?>
                                </span>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="qe-tool-btn" id="qeClear">
                                <i class="bi bi-eraser"></i> Clear
                            </button>
                        </div>
                        <button type="submit" class="qe-run-btn">
                            <i class="bi bi-play-fill"></i> Execute
                        </button>
                    </div>
                </div>
            </form>

            <!-- Examples -->
            <div class="qe-examples">
                <h6><i class="bi bi-lightbulb-fill me-1" style="color:#f59e0b"></i>Quick examples</h6>
                <button class="qe-ex-btn" data-q="SELECT * FROM users LIMIT 20;">SELECT users</button>
                <button class="qe-ex-btn" data-q="SELECT id, username, email FROM users WHERE enabled = 'yes' LIMIT 50;">Active users</button>
                <button class="qe-ex-btn" data-q="SHOW TABLES;">SHOW TABLES</button>
                <button class="qe-ex-btn" data-q="SHOW STATUS LIKE 'Threads%';">Thread status</button>
                <button class="qe-ex-btn" data-q="SELECT table_name, ROUND((data_length+index_length)/1024/1024,2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY size_mb DESC;">Table sizes</button>
            </div>
        </div>
    </div>

    <!-- Results -->
    <?php if (!empty($alert)): ?>
    <div class="qe-result-card">
        <div class="qe-result-header">
            <div class="qe-result-title">
                <i class="bi bi-grid-3x3-gap-fill"></i> Query Result
            </div>
            <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                <?php if ($exec_time !== null): ?>
                <span class="qe-badge qe-badge-time">
                    <i class="bi bi-stopwatch"></i> <?= $exec_time ?>ms
                </span>
                <?php endif; ?>
                <span class="qe-badge qe-badge-<?= $alert ?>">
                    <?php match ($alert) {
                        'success' => print('<i class="bi bi-check-circle-fill"></i>'),
                        'danger'  => print('<i class="bi bi-x-circle-fill"></i>'),
                        'info'    => print('<i class="bi bi-info-circle-fill"></i>'),
                        'warning' => print('<i class="bi bi-exclamation-triangle-fill"></i>'),
                        default   => null,
                    }; ?>
                    <?= htmlspecialchars($rows_info) ?>
                </span>
                <?php if (!empty($table)): ?>
                <button type="button" class="qe-tool-btn" id="qeCopyCsv" style="padding:.25rem .75rem;font-size:.78rem;">
                    <i class="bi bi-clipboard-data"></i> Copy as CSV
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="qe-result-body">
            <?php if ($alert === 'danger'): ?>
            <div class="qe-msg qe-msg-danger">
                <i class="bi bi-x-circle-fill"></i>
                <span><?= htmlspecialchars($rows_info) ?></span>
            </div>
            <?php elseif (empty($table) && $alert !== 'danger'): ?>
            <div class="qe-msg qe-msg-<?= $alert ?>">
                <i class="bi bi-info-circle-fill"></i>
                <span><?= htmlspecialchars($rows_info) ?></span>
            </div>
            <?php endif; ?>
            <?= $table ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.qe-wrap -->

<!-- History Modal -->
<div class="modal fade" id="histModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none">
            <div class="modal-header"
                 style="background:var(--bs-primary);color:#fff;border:none">
                <h5 class="modal-title" style="font-weight:700">
                    <i class="bi bi-clock-history me-2"></i>Query History
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="max-height:460px;overflow-y:auto">
                <?php if (!empty($_SESSION['query_history'])): ?>
                    <?php foreach ($_SESSION['query_history'] as $i => $h): ?>
                    <div class="qe-history-item" data-q="<?= htmlspecialchars($h['query']) ?>">
                        <div class="qe-history-meta">
                            #<?= count($_SESSION['query_history']) - $i ?>
                            &nbsp;·&nbsp;
                            <?= date('Y-m-d H:i:s', $h['timestamp']) ?>
                        </div>
                        <div class="qe-history-query">
                            <?= htmlspecialchars(substr($h['query'], 0, 120))
                                . (strlen($h['query']) > 120 ? '…' : '') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:2.5rem;opacity:.4"></i>
                        <p class="mt-2 mb-0">No history yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f1f5f9">
                <form method="post" style="margin:0">
                    <input type="hidden" name="do" value="ts_clear_history">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Clear all history?')">
                        <i class="bi bi-trash3 me-1"></i>Clear history
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/admin/scripts/execute_sql_query.js"></script>

<?php stdfoot(); ?>