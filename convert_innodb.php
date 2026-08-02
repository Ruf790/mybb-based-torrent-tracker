<?php
declare(strict_types=1);


require_once('global.php');


/**
 * Convert Tables to InnoDB + utf8mb4
 *
 * Ожидает стандартный admin-bootstrap (тот же паттерн, что recount_rebuild.php) -
 * $db/$mybb/$CURUSER уже проинициализированы через обычный роутер admin/index.php,
 * IN_ADMINCP уже определён до подключения этого файла.
 */

const TARGET_COLLATION = 'utf8mb4_unicode_ci';

function get_tables_to_convert(): array
{
    global $db, $config;

    $database = $config['database']['database'] ?? '';

    $query = $db->sql_query_prepared(
        "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS,
                ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_TYPE = 'BASE TABLE'
           AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION NOT LIKE 'utf8mb4%')
         ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC",
        [$database]
    );

    $tables = [];
    while ($query && ($row = $db->fetch_array($query))) {
        $row['needs_engine']  = strtolower((string)$row['ENGINE']) !== 'innodb';
        $row['needs_charset'] = !str_starts_with((string)$row['TABLE_COLLATION'], 'utf8mb4');
        $tables[] = $row;
    }
    return $tables;
}

/**
 * Строит ALTER TABLE только с теми клозами, которые реально нужны этой
 * конкретной таблице (одни уже InnoDB и им нужна только смена кодировки,
 * другие наоборот) - не переписывает то, что и так уже в порядке.
 */
function build_alter_sql(string $escapedName, array $row): string
{
    $clauses = [];
    if ($row['needs_engine']) {
        $clauses[] = 'ENGINE=InnoDB';
    }
    if ($row['needs_charset']) {
        $clauses[] = 'CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . TARGET_COLLATION;
    }
    return "ALTER TABLE `{$escapedName}` " . implode(', ', $clauses);
}

// ═══════════════════════════════════════════════════════════
// ACTION: AJAX-конвертация одной таблицы
// ═══════════════════════════════════════════════════════════
if (isset($_POST['ajax_convert_table']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db, $CURUSER;

    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }

    $tableName = (string)$_POST['ajax_convert_table'];

    // Не доверяем присланному имени таблицы вслепую - сверяем со свежим
    // списком реальных таблиц, требующих конвертации, из information_schema
    // перед тем как строить ALTER TABLE. Идентификатор таблицы нельзя
    // параметризовать через placeholder ('?' работает только для значений,
    // не для имён), поэтому валидация по whitelist из самой БД - обязательна.
    $pending = get_tables_to_convert();
    $row = null;
    foreach ($pending as $t) {
        if ($t['TABLE_NAME'] === $tableName) {
            $row = $t;
            break;
        }
    }
    if ($row === null) {
        echo json_encode(['status' => 'error', 'message' => "Table '{$tableName}' does not need conversion"]);
        exit;
    }

    $escapedName = str_replace('`', '``', $tableName);
    $alterSql    = build_alter_sql($escapedName, $row);
    $t0 = microtime(true);

    try {
        $result  = $db->sql_query_prepared($alterSql);
        $elapsed = round(microtime(true) - $t0, 2);

        if ($result) {
            $what = implode('+', array_filter([
                $row['needs_engine']  ? 'InnoDB' : null,
                $row['needs_charset'] ? 'utf8mb4' : null,
            ]));
            write_log("Table converted to {$what}: {$tableName} ({$elapsed}s) | {$CURUSER['username']}");
            echo json_encode(['status' => 'success', 'message' => "Converted in {$elapsed}s"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ALTER TABLE failed']);
        }
    } catch (\Throwable $e) {
        write_log("Table conversion FAILED: {$tableName} - " . $e->getMessage() . " | {$CURUSER['username']}");
        // Частая причина сбоя: индексируемая VARCHAR-колонка упирается в лимит
        // длины ключа после перехода на utf8mb4 (4 байта/символ вместо 3) -
        // отдаём сообщение как есть, это админ-панель для доверенных админов,
        // а не публичный вывод ошибок конечным пользователям.
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════
// UI
// ═══════════════════════════════════════════════════════════
$pendingTables = get_tables_to_convert();

stdhead('Convert Tables to InnoDB + utf8mb4');
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-database me-2"></i>Convert Tables to InnoDB + utf8mb4</h1>
    </div>

    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Each table is locked for the duration of its own conversion. Large tables can take a while &mdash;
        make a backup first and consider running this during low-traffic hours.
    </div>

    <?php if (empty($pendingTables)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>No tables to convert &mdash; everything is already InnoDB + utf8mb4.
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <table class="table table-sm align-middle" id="pendingTable">
                    <thead>
                        <tr>
                            <th style="width:1%"><input type="checkbox" id="selectAll" checked></th>
                            <th>Table</th>
                            <th>Engine</th>
                            <th>Collation</th>
                            <th>Rows</th>
                            <th>Size (MB)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTables as $t): ?>
                        <tr data-table="<?= htmlspecialchars($t['TABLE_NAME'], ENT_QUOTES) ?>">
                            <td><input type="checkbox" class="table-check" checked></td>
                            <td><code><?= htmlspecialchars($t['TABLE_NAME']) ?></code></td>
                            <td>
                                <?php if ($t['needs_engine']): ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis"><?= htmlspecialchars((string)$t['ENGINE']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success-emphasis">InnoDB</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['needs_charset']): ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis"><?= htmlspecialchars((string)$t['TABLE_COLLATION']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success-emphasis"><?= htmlspecialchars((string)$t['TABLE_COLLATION']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((int)$t['TABLE_ROWS']) ?></td>
                            <td><?= htmlspecialchars((string)$t['size_mb']) ?></td>
                            <td class="status-cell text-muted">Pending</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button class="btn btn-primary" id="startConvertBtn">
                    <i class="fas fa-play me-2"></i>Start Conversion
                </button>
                <span class="ms-3 fw-medium" id="overallProgress"></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const myPostKey = <?= json_encode($mybb->post_code ?? '') ?>;
    const scriptUrl  = <?= json_encode($_SERVER['REQUEST_URI'] ?? '') ?>;

    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.table-check').forEach(cb => cb.checked = this.checked);
    });

    document.getElementById('startConvertBtn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Converting...';

        const rows = [...document.querySelectorAll('#pendingTable tbody tr')]
            .filter(row => row.querySelector('.table-check').checked);

        let done = 0;
        const total = rows.length;
        let failed = 0;

        for (const row of rows) {
            const tableName  = row.dataset.table;
            const statusCell = row.querySelector('.status-cell');
            statusCell.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Converting...';
            statusCell.className = 'status-cell text-primary';

            try {
                const formData = new FormData();
                formData.append('ajax_convert_table', tableName);
                formData.append('my_post_key', myPostKey);

                const resp = await fetch(scriptUrl, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.status === 'success') {
                    statusCell.textContent = '\u2713 ' + data.message;
                    statusCell.className = 'status-cell text-success';
                    row.querySelectorAll('td:nth-child(3) .badge, td:nth-child(4) .badge')
                        .forEach(b => { b.className = 'badge bg-success-subtle text-success-emphasis'; });
                } else {
                    statusCell.textContent = '\u2717 ' + data.message;
                    statusCell.className = 'status-cell text-danger';
                    failed++;
                }
            } catch (err) {
                statusCell.textContent = '\u2717 Network error';
                statusCell.className = 'status-cell text-danger';
                failed++;
            }

            done++;
            document.getElementById('overallProgress').textContent =
                `${done} / ${total} processed` + (failed ? ` (${failed} failed)` : '');
        }

        btn.disabled = false;
        btn.innerHTML = failed
            ? '<i class="fas fa-exclamation-triangle me-2"></i>Done with errors'
            : '<i class="fas fa-check me-2"></i>Done';
    });
})();
</script>
<?php
stdfoot();
