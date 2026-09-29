<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert">
            <h4 class="alert-heading"><i class="fas fa-ban me-2"></i>Access Denied</h4>
            <p class="mb-0">Direct initialization of this file is not allowed.</p>
          </div>');
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

// Пропускаем пробелы И SQL-комментарии (/* */, --, #) перед разрушительным
// ключевым словом - раньше "/* x */ DROP TABLE ..." обходил подтверждение,
// потому что \s* пропускал только пробелы, не комментарии.
const DESTRUCTIVE_QUERY_PATTERN = '/^(?:\s+|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\/\*[\s\S]*?\*\/)*(DROP|DELETE|TRUNCATE|UPDATE|ALTER)\b/i';

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
            // Сырой текст — экранируется один раз при выводе (раньше было двойное экранирование)
            $rows_info = $mysqli->error;
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

// ── KPI stats ─────────────────────────────────────────────────────────────────
// Считаем после выполнения запроса, чтобы плитки отражали изменения (CREATE/DROP и т.п.)
$kpi = ['version' => '—', 'tables' => 0, 'size' => 0, 'rows' => 0];
if ($db_ok) {
    $kpi['version'] = (string)$mysqli->server_info;
    $kres = $mysqli->query(
        'SELECT COUNT(*) AS t, COALESCE(SUM(data_length + index_length), 0) AS sz, COALESCE(SUM(table_rows), 0) AS rw
           FROM information_schema.tables
          WHERE table_schema = DATABASE()'
    );
    if ($kres instanceof \mysqli_result) {
        $k = $kres->fetch_assoc() ?: [];
        $kpi['tables'] = (int)($k['t']  ?? 0);
        $kpi['size']   = (int)($k['sz'] ?? 0);
        $kpi['rows']   = (int)($k['rw'] ?? 0);
        $kres->free();
    }
}

$qe_bytes = static function (int $b): string {
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $v = (float)$b;
    while ($v >= 1024 && $i < count($u) - 1) {
        $v /= 1024;
        $i++;
    }
    return ($i === 0 ? (string)$b : number_format($v, 2)) . ' ' . $u[$i];
};

$historyCount = count($_SESSION['query_history']);

$alertIcon = match ($alert) {
    'success' => 'fa-circle-check',
    'danger'  => 'fa-circle-xmark',
    'warning' => 'fa-triangle-exclamation',
    default   => 'fa-circle-info',
};

$examples = [
    ['fa-users',         'All users',     'SELECT * FROM users LIMIT 20;'],
    ['fa-user-check',    'Active users',  "SELECT id, username, email FROM users WHERE enabled = 'yes' LIMIT 50;"],
    ['fa-table-list',    'Show tables',   'SHOW TABLES;'],
    ['fa-microchip',     'Thread status', "SHOW STATUS LIKE 'Threads%';"],
    ['fa-weight-hanging','Table sizes',   'SELECT table_name, ROUND((data_length+index_length)/1024/1024,2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY size_mb DESC;'],
    ['fa-list-check',    'Process list',  'SHOW FULL PROCESSLIST;'],
];

stdhead('SQL Query Editor');
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/execute_sql_query.css?ver=2">

<div class="qe-wrap">

    <!-- Header -->
    <div class="qe-panel qe-header">
        <div class="qe-header-icon"><i class="fa-solid fa-terminal"></i></div>
        <div class="qe-header-text">
            <h2>SQL Query Editor</h2>
            <p>Run SQL against the live database. Every executed query is written to the staff log.</p>
        </div>
        <span class="qe-conn <?= $db_ok ? 'qe-conn-ok' : 'qe-conn-err' ?>">
            <i class="fa-solid <?= $db_ok ? 'fa-plug-circle-check' : 'fa-plug-circle-xmark' ?>"></i>
            <?= $db_ok ? 'Connected' : 'Offline' ?>
        </span>
    </div>

    <!-- KPI tiles -->
    <div class="qe-kpis">
        <div class="qe-kpi">
            <div class="qe-kpi-icon qe-soft-<?= $db_ok ? 'success' : 'danger' ?>"><i class="fa-solid fa-database"></i></div>
            <div class="qe-kpi-body">
                <div class="qe-kpi-label">Database</div>
                <div class="qe-kpi-value" title="<?= htmlspecialchars($db_name) ?>"><?= htmlspecialchars($db_name) ?></div>
            </div>
        </div>
        <div class="qe-kpi">
            <div class="qe-kpi-icon qe-soft-primary"><i class="fa-solid fa-server"></i></div>
            <div class="qe-kpi-body">
                <div class="qe-kpi-label">MySQL version</div>
                <div class="qe-kpi-value"><?= htmlspecialchars($kpi['version']) ?></div>
            </div>
        </div>
        <div class="qe-kpi">
            <div class="qe-kpi-icon qe-soft-info"><i class="fa-solid fa-table"></i></div>
            <div class="qe-kpi-body">
                <div class="qe-kpi-label">Tables</div>
                <div class="qe-kpi-value"><?= number_format($kpi['tables']) ?></div>
                <div class="qe-kpi-sub">~<?= number_format($kpi['rows']) ?> rows</div>
            </div>
        </div>
        <div class="qe-kpi">
            <div class="qe-kpi-icon qe-soft-warning"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="qe-kpi-body">
                <div class="qe-kpi-label">Size on disk</div>
                <div class="qe-kpi-value"><?= $qe_bytes($kpi['size']) ?></div>
                <div class="qe-kpi-sub">data + indexes</div>
            </div>
        </div>
    </div>

    <?php if (!$db_ok): ?>
    <div class="qe-msg qe-msg-danger qe-mb">
        <i class="fa-solid fa-circle-xmark"></i>
        <span>Database connection failed. Check the server log for details.</span>
    </div>
    <?php endif; ?>

    <!-- Editor -->
    <div class="qe-panel qe-editor-panel">
        <form method="post" id="qeForm">
            <input type="hidden" name="do" value="ts_execute_sql_query">
            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
            <input type="hidden" name="confirm_destructive" id="confirmDestructive" value="0">

            <div class="qe-editor-head">
                <span class="qe-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="qe-editor-title"><i class="fa-solid fa-code"></i> query.sql</span>
                <span class="qe-editor-db"><i class="fa-solid fa-database"></i> <?= htmlspecialchars($db_name) ?></span>
            </div>

            <textarea name="query" id="query" spellcheck="false"
                      placeholder="-- Enter your SQL query here&#10;SELECT * FROM users LIMIT 10;"
            ><?= htmlspecialchars($query) ?></textarea>

            <div class="qe-toolbar">
                <div class="qe-tool-group">
                    <button type="button" class="qe-btn qe-btn-ghost" id="qeFormat">
                        <i class="fa-solid fa-align-left"></i> Format
                    </button>
                    <button type="button" class="qe-btn qe-btn-ghost" id="qeHistory"
                            data-bs-toggle="modal" data-bs-target="#histModal">
                        <i class="fa-solid fa-clock-rotate-left"></i> History
                        <?php if ($historyCount > 0): ?>
                        <span class="qe-count"><?= $historyCount ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="qe-btn qe-btn-ghost" id="qeClear">
                        <i class="fa-solid fa-eraser"></i> Clear
                    </button>
                </div>
                <button type="submit" class="qe-btn qe-btn-run">
                    <i class="fa-solid fa-play"></i> Execute
                </button>
            </div>
        </form>
    </div>

    <!-- Examples -->
    <div class="qe-panel qe-examples">
        <div class="qe-section-title"><i class="fa-solid fa-lightbulb"></i> Quick examples</div>
        <div class="qe-ex-list">
            <?php foreach ($examples as [$icon, $label, $sql]): ?>
            <button type="button" class="qe-ex-btn" data-q="<?= htmlspecialchars($sql) ?>" title="<?= htmlspecialchars($sql) ?>">
                <i class="fa-solid <?= $icon ?>"></i> <?= htmlspecialchars($label) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Results -->
    <?php if (!empty($alert)): ?>
    <div class="qe-panel qe-result">
        <div class="qe-result-head">
            <div class="qe-section-title"><i class="fa-solid fa-table-cells"></i> Query result</div>
            <div class="qe-result-meta">
                <?php if ($exec_time !== null): ?>
                <span class="qe-badge qe-soft-secondary">
                    <i class="fa-solid fa-stopwatch"></i> <?= $exec_time ?> ms
                </span>
                <?php endif; ?>
                <span class="qe-badge qe-soft-<?= $alert ?>">
                    <i class="fa-solid <?= $alertIcon ?>"></i>
                    <?= $alert === 'danger' ? 'Error' : htmlspecialchars($rows_info) ?>
                </span>
                <?php if (!empty($table)): ?>
                <button type="button" class="qe-btn qe-btn-ghost qe-btn-sm" id="qeCopyCsv">
                    <i class="fa-solid fa-file-csv"></i> Copy as CSV
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="qe-result-body">
            <?php if (empty($table)): ?>
            <div class="qe-msg qe-msg-<?= $alert ?>">
                <i class="fa-solid <?= $alertIcon ?>"></i>
                <span><?= htmlspecialchars($rows_info) ?></span>
            </div>
            <?php endif; ?>
            <?= $table ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- History Modal -->
    <div class="modal fade qe-modal" id="histModal" tabindex="-1" aria-labelledby="histModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="qe-header-icon qe-header-icon-sm"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <h5 class="modal-title" id="histModalLabel">Query history</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <?php if ($historyCount > 0): ?>
                        <?php foreach ($_SESSION['query_history'] as $i => $h): ?>
                        <div class="qe-history-item" data-q="<?= htmlspecialchars($h['query']) ?>" title="Load into editor">
                            <div class="qe-history-num">#<?= $historyCount - $i ?></div>
                            <div class="qe-history-main">
                                <div class="qe-history-meta">
                                    <i class="fa-regular fa-clock"></i> <?= date('Y-m-d H:i:s', $h['timestamp']) ?>
                                </div>
                                <div class="qe-history-query">
                                    <?= htmlspecialchars(mb_substr($h['query'], 0, 120))
                                        . (mb_strlen($h['query']) > 120 ? '…' : '') ?>
                                </div>
                            </div>
                            <i class="fa-solid fa-arrow-turn-up qe-history-load"></i>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="qe-empty">
                            <i class="fa-solid fa-inbox"></i>
                            <p>No queries yet. Executed queries from this session will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <?php if ($historyCount > 0): ?>
                    <form method="post" id="qeClearHistoryForm" class="me-auto">
                        <input type="hidden" name="do" value="ts_clear_history">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <button type="submit" class="qe-btn qe-btn-danger qe-btn-sm">
                            <i class="fa-solid fa-trash-can"></i> Clear history
                        </button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="qe-btn qe-btn-ghost qe-btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.qe-wrap -->

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/execute_sql_query.js?ver=2"></script>

<?php stdfoot(); ?>