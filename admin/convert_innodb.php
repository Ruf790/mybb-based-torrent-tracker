<?php
declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><b class="text-dark">Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('CI_VERSION', '1.1');

global $lang;
$lang->load('convert_innodb');


/**
 * Convert Tables to InnoDB + utf8mb4
*/

const TARGET_COLLATION = 'utf8mb4_unicode_ci';

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
    global $db, $CURUSER, $lang;

    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->convert_innodb['err_invalid_token']]);
        exit;
    }

    $tableName = (string)$_POST['ajax_convert_table'];


    $pending = get_tables_to_convert();
    $row = null;
    foreach ($pending as $t) {
        if ($t['TABLE_NAME'] === $tableName) {
            $row = $t;
            break;
        }
    }
    if ($row === null) {
        echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->convert_innodb['err_not_needed'], $tableName)]);
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
            echo json_encode(['status' => 'success', 'message' => ags_fmt($lang->convert_innodb['msg_converted_in'], $elapsed)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $lang->convert_innodb['err_alter_failed']]);
        }
    } catch (\Throwable $e) {
        write_log("Table conversion FAILED: {$tableName} - " . $e->getMessage() . " | {$CURUSER['username']}");

        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════
// UI
// ═══════════════════════════════════════════════════════════
global $BASEURL;

$pendingTables = get_tables_to_convert();
$L = $lang->convert_innodb;

// KPI totals (from the same list, no extra query)
$kpi_pending = count($pendingTables);
$kpi_engine  = 0;
$kpi_charset = 0;
$kpi_bytes   = 0;
$kpi_rows    = 0;
foreach ($pendingTables as $t) {
    $kpi_engine  += $t['needs_engine'] ? 1 : 0;
    $kpi_charset += $t['needs_charset'] ? 1 : 0;
    $kpi_bytes   += (int)round((float)$t['size_mb'] * 1024 * 1024);
    $kpi_rows    += (int)$t['TABLE_ROWS'];
}

$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = $v;
    }
}

stdhead($L['page_title']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/snatched_torrents.css?v=' . CI_VERSION . '">';
?>

<div class="container mt-3 py-4 stn-page">

    <!-- Header -->
    <div class="stn-card stn-head mb-3">
        <div class="stn-head__icon"><i class="fa-solid fa-database"></i></div>
        <div class="flex-grow-1">
            <h1 class="stn-title"><?= htmlspecialchars($L['page_title']) ?></h1>
            <p><?= htmlspecialchars($L['sec_subtitle']) ?></p>
        </div>
        <?php if ($kpi_pending > 0): ?>
            <span class="stn-badge stn-soft-warning d-none d-md-inline-flex"><i class="fa-solid fa-hourglass-half"></i><?= htmlspecialchars(ags_fmt($L['badge_to_convert'], $kpi_pending)) ?></span>
        <?php else: ?>
            <span class="stn-badge stn-soft-success d-none d-md-inline-flex"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($L['badge_all_done']) ?></span>
        <?php endif; ?>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-primary"><i class="fa-solid fa-table-list"></i></div>
                <div>
                    <div class="stn-kpi__value"><?= number_format($kpi_pending) ?></div>
                    <div class="stn-kpi__label"><?= htmlspecialchars($L['kpi_pending']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-danger"><i class="fa-solid fa-gears"></i></div>
                <div>
                    <div class="stn-kpi__value"><?= number_format($kpi_engine) ?></div>
                    <div class="stn-kpi__label"><?= htmlspecialchars($L['kpi_engine']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-info"><i class="fa-solid fa-language"></i></div>
                <div>
                    <div class="stn-kpi__value"><?= number_format($kpi_charset) ?></div>
                    <div class="stn-kpi__label"><?= htmlspecialchars($L['kpi_charset']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stn-card stn-kpi">
                <div class="stn-kpi__icon stn-soft-warning"><i class="fa-solid fa-hard-drive"></i></div>
                <div>
                    <div class="stn-kpi__value stn-kpi__value--sm"><?= mksize($kpi_bytes) ?></div>
                    <div class="stn-kpi__sub"><?= htmlspecialchars(ags_fmt($L['kpi_rows'], number_format($kpi_rows))) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Warning -->
    <div class="stn-card stn-kpi mb-3">
        <div class="stn-kpi__icon stn-soft-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="stn-kpi__label fw-semibold"><?= htmlspecialchars($L['warn_title']) ?></div>
            <div class="stn-meta"><?= $L['warn_text'] ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="stn-card overflow-hidden">
    <?php if ($kpi_pending > 0): ?>

        <div class="stn-toolbar">
            <span><i class="fa-solid fa-list-check me-1"></i><?= htmlspecialchars(ags_fmt($L['toolbar_pending'], number_format($kpi_pending))) ?></span>
            <div class="d-flex align-items-center gap-3">
                <span class="stn-meta fw-medium" id="overallProgress"></span>
                <button type="button" class="btn btn-primary btn-sm stn-pill" id="startConvertBtn">
                    <i class="fa-solid fa-play me-2"></i><?= htmlspecialchars($L['btn_start']) ?>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table stn-table" id="pendingTable">
                <thead>
                    <tr>
                        <th style="width:1%"><input type="checkbox" class="form-check-input" id="selectAll" checked aria-label="<?= htmlspecialchars($L['aria_select_all'], ENT_QUOTES) ?>"></th>
                        <th><i class="fa-solid fa-table"></i><?= htmlspecialchars($L['col_table']) ?></th>
                        <th><i class="fa-solid fa-gears"></i><?= htmlspecialchars($L['col_engine']) ?></th>
                        <th><i class="fa-solid fa-language"></i><?= htmlspecialchars($L['col_collation']) ?></th>
                        <th class="text-end"><i class="fa-solid fa-list-ol"></i><?= htmlspecialchars($L['col_rows']) ?></th>
                        <th class="text-end"><i class="fa-solid fa-hard-drive"></i><?= htmlspecialchars($L['col_size']) ?></th>
                        <th><i class="fa-solid fa-bars-progress"></i><?= htmlspecialchars($L['col_status']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTables as $t): ?>
                    <tr data-table="<?= htmlspecialchars($t['TABLE_NAME'], ENT_QUOTES) ?>">
                        <td><input type="checkbox" class="form-check-input table-check" checked aria-label="<?= htmlspecialchars(ags_fmt($L['aria_select_table'], (string)$t['TABLE_NAME']), ENT_QUOTES) ?>"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="stn-torrent-ico stn-soft-primary"><i class="fa-solid fa-table"></i></span>
                                <code><?= htmlspecialchars($t['TABLE_NAME']) ?></code>
                            </div>
                        </td>
                        <td>
                            <?php if ($t['needs_engine']): ?>
                                <span class="stn-badge stn-soft-danger"><?= htmlspecialchars((string)$t['ENGINE']) ?></span>
                            <?php else: ?>
                                <span class="stn-badge stn-soft-success">InnoDB</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['needs_charset']): ?>
                                <span class="stn-badge stn-soft-danger"><?= htmlspecialchars((string)$t['TABLE_COLLATION']) ?></span>
                            <?php else: ?>
                                <span class="stn-badge stn-soft-success"><?= htmlspecialchars((string)$t['TABLE_COLLATION']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format((int)$t['TABLE_ROWS']) ?></td>
                        <td class="text-end stn-traffic"><?= mksize((int)round((float)$t['size_mb'] * 1024 * 1024)) ?></td>
                        <td class="status-cell">
                            <span class="stn-badge stn-soft-muted"><i class="fa-solid fa-hourglass-half"></i><?= htmlspecialchars($L['lbl_status_pending']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="stn-empty">
            <div class="stn-empty__icon stn-soft-success"><i class="fa-solid fa-circle-check"></i></div>
            <h4 class="stn-title"><?= htmlspecialchars($L['empty_title']) ?></h4>
            <p class="text-body-secondary mb-0"><?= htmlspecialchars($L['empty_text']) ?></p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script>
(function() {
    const myPostKey = <?= json_encode($mybb->post_code ?? '') ?>;
    const scriptUrl  = <?= json_encode($_SERVER['REQUEST_URI'] ?? '') ?>;

    function t(key, fallback, ...args) {
        let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string')
            ? AGS_LANG[key] : fallback;
        args.forEach((a, i) => { s = s.split('{' + (i + 1) + '}').join(String(a)); });
        return s;
    }

    function icon(name) {
        const i = document.createElement('i');
        i.className = 'fa-solid ' + name;
        return i;
    }
    function spinner() {
        const s = document.createElement('span');
        s.className = 'spinner-border spinner-border-sm';
        return s;
    }
    // Replace element content with an optional leading node + plain text
    function setContent(el, leadNode, text) {
        el.replaceChildren();
        if (leadNode) el.appendChild(leadNode);
        el.appendChild(document.createTextNode(text));
    }
    // Status badge: <span class="stn-badge stn-soft-*">[lead]text</span>
    function setStatus(cell, soft, leadNode, text) {
        const b = document.createElement('span');
        b.className = 'stn-badge ' + soft;
        setContent(b, leadNode, text);
        cell.replaceChildren(b);
    }

    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.table-check').forEach(cb => cb.checked = this.checked);
    });

    document.getElementById('startConvertBtn')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        const btnSpin = spinner();
        btnSpin.classList.add('me-2');
        setContent(btn, btnSpin, t('btn_converting', 'Converting...'));

        const rows = [...document.querySelectorAll('#pendingTable tbody tr')]
            .filter(row => row.querySelector('.table-check').checked);

        let done = 0;
        const total = rows.length;
        let failed = 0;

        for (const row of rows) {
            const tableName  = row.dataset.table;
            const statusCell = row.querySelector('.status-cell');
            setStatus(statusCell, 'stn-soft-info', spinner(), t('status_converting', 'Converting...'));

            try {
                const formData = new FormData();
                formData.append('ajax_convert_table', tableName);
                formData.append('my_post_key', myPostKey);

                const resp = await fetch(scriptUrl, { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.status === 'success') {
                    setStatus(statusCell, 'stn-soft-success', icon('fa-circle-check'), String(data.message));
                    row.querySelectorAll('td:nth-child(3) .stn-badge, td:nth-child(4) .stn-badge')
                        .forEach(b => { b.classList.replace('stn-soft-danger', 'stn-soft-success'); });
                } else {
                    setStatus(statusCell, 'stn-soft-danger', icon('fa-circle-xmark'), String(data.message));
                    failed++;
                }
            } catch (err) {
                setStatus(statusCell, 'stn-soft-danger', icon('fa-circle-xmark'), t('network_error', 'Network error'));
                failed++;
            }

            done++;
            document.getElementById('overallProgress').textContent =
                t('progress', '{1} / {2} processed', done, total)
                + (failed ? t('progress_failed', ' ({1} failed)', failed) : '');
        }

        btn.disabled = false;
        const btnIco = icon(failed ? 'fa-triangle-exclamation' : 'fa-check');
        btnIco.classList.add('me-2');
        setContent(btn, btnIco, failed ? t('done_errors', 'Done with errors') : t('done', 'Done'));
    });
})();
</script>
<?php
stdfoot();
