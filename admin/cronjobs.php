<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3" role="alert">
        <i class="fas fa-ban me-2"></i><b>Error!</b> Direct initialization of this file is not allowed.
    </div>');
}


require_once(INC_PATH . '/functions_mkprettytime.php');

$lang->load('cronjobs');

// ─── Helper: placeholder formatting ({1} / %1$s → args) ───────────────
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

// ─── Helper: Calculate Cron Time ──────────────────────────────────────
function calc_cron_time(int $stamp): array
{
    $intervals = [
        'years'   => 365 * 24 * 3600,
        'months'  => 31  * 24 * 3600,
        'weeks'   => 7   * 24 * 3600,
        'days'    =>       24 * 3600,
        'hours'   =>            3600,
        'minutes' =>              60,
    ];

    $result = [];
    foreach ($intervals as $key => $secs) {
        $result[$key] = (int)floor($stamp / $secs);
        $stamp %= $secs;
    }

    return $result;
}

// ─── Helper: Render Status Badge ──────────────────────────────────────
function render_status_badge(bool $active, string $type = 'status'): string
{
    global $lang;
    if ($type === 'status') {
        $class = $active ? 'success' : 'danger';
        $text  = $active ? $lang->cronjobs['badge_active'] : $lang->cronjobs['badge_disabled'];
        $icon  = $active ? 'fa-check-circle' : 'fa-times-circle';
    } else {
        $class = $active ? 'success' : 'secondary';
        $text  = $active ? $lang->cronjobs['badge_yes']    : $lang->cronjobs['badge_no'];
        $icon  = $active ? 'fa-clipboard-check' : 'fa-clipboard';
    }
    return "<span class='badge bg-{$class}'><i class='fas {$icon} me-1'></i>{$text}</span>";
}

$act2   = $_GET['act2']   ?? $_POST['act2']   ?? '';
$cronid = (int)($_GET['cronid'] ?? $_POST['cronid'] ?? 0);

// ── AJAX: load cron data for modal ────────────────────────────────────
if ($act2 === 'get_cron_data' && is_valid_id($cronid)) {
    header('Content-Type: application/json');
    $q = $db->sql_query_prepared("SELECT * FROM cron WHERE cronid = ?", [$cronid]);
    if ($q && $db->num_rows($q)) {
        $cron   = $db->fetch_array($q);
        $tarray = calc_cron_time((int)$cron['minutes']);
        echo json_encode([
            'success'     => true,
            'cronid'      => (int)$cron['cronid'],
            'filename'    => $cron['filename'],
            'description' => $cron['description'],
            'active'      => (int)$cron['active'],
            'loglevel'    => (int)$cron['loglevel'],
            'tarray'      => $tarray,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $lang->cronjobs['err_not_found']]);
    }
    exit();
}

// === POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($act2 === 'save' || $act2 === 'save_new') && (is_valid_id($cronid) || $act2 === 'save_new')) {
        if (!verify_post_check($_POST['my_post_key'] ?? '')) {
            http_response_code(403);
            stderr($lang->cronjobs['err_security_title'], $lang->cronjobs['err_security_token']);
        }

        $rawFilename = trim($_POST['filename'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $rawFilename)) {
            flash_message($lang->cronjobs['flash_bad_filename'], "error");
            admin_redirect($_this_script_);
            exit();
        }

        $filename    = $rawFilename;
        $description = trim($_POST['description'] ?? '');

        $mosecs = 31*24*60*60; $wsecs = 7*24*60*60; $dsecs = 24*60*60; $hsecs = 60*60; $msecs = 60;
        $minutes = 0;
        if (!empty($_POST['months']))  $minutes += $mosecs * (int)$_POST['months'];
        if (!empty($_POST['weeks']))   $minutes += $wsecs  * (int)$_POST['weeks'];
        if (!empty($_POST['days']))    $minutes += $dsecs  * (int)$_POST['days'];
        if (!empty($_POST['hours']))   $minutes += $hsecs  * (int)$_POST['hours'];
        if (!empty($_POST['minutes'])) $minutes += $msecs  * (int)$_POST['minutes'];

        // Интервал 0 означал бы запуск задачи на каждом обращении к cron.php
        if ($minutes < 60) {
            flash_message($lang->cronjobs['flash_min_interval'], "error");
            admin_redirect($_this_script_);
            exit();
        }

        $act2ive  = !empty($_POST['active'])   ? 1 : 0;
        $loglevel = !empty($_POST['loglevel']) ? 1 : 0;

        if ($act2 === 'save_new') {
            $nextrun = TIMENOW + $minutes;
            $db->sql_query_prepared(
                "INSERT INTO cron (filename,description,minutes,nextrun,active,loglevel) VALUES (?,?,?,?,?,?)",
                [$filename, $description, $minutes, $nextrun, $act2ive, $loglevel]
            );
            flash_message($lang->cronjobs['flash_created'], "success");
        } else {
            $db->sql_query_prepared(
                "UPDATE cron SET filename=?, description=?, minutes=?, active=?, loglevel=? WHERE cronid=?",
                [$filename, $description, $minutes, $act2ive, $loglevel, $cronid]
            );
            flash_message($lang->cronjobs['flash_updated'], "success");
        }

        admin_redirect($_this_script_);
        exit();
    }
}

// === POST actions (run / activate / disable / delete) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($act2, ['run', 'active', 'disable', 'delete'], true)) {
    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        http_response_code(403);
        stderr($lang->cronjobs['err_security_title'], $lang->cronjobs['err_security_token']);
    }

    if ($act2 === 'run' && is_valid_id($cronid)) {
        $db->sql_query_prepared("UPDATE cron SET nextrun='0' WHERE cronid = ?", [$cronid]);

        // Fetch cron name for display
        $cron_info_q = $db->sql_query_prepared("SELECT filename, description FROM cron WHERE cronid = ?", [$cronid]);
        $cron_info   = ($cron_info_q && $db->num_rows($cron_info_q)) ? $db->fetch_array($cron_info_q) : [];
        $cron_fname  = htmlspecialchars($cron_info['filename']    ?? $lang->cronjobs['lbl_unknown']);
        $cron_desc   = htmlspecialchars($cron_info['description'] ?? '');

        stdhead($lang->cronjobs['page_title_running']);
        echo '
<div class="d-flex align-items-center justify-content-center" style="min-height:60vh">
    <div class="text-center" style="max-width:400px">
        <div class="mb-4 position-relative d-inline-block">
            <div class="spinner-border text-primary" style="width:56px;height:56px;border-width:3px" role="status"></div>
            <i class="fas fa-clock position-absolute top-50 start-50 translate-middle text-primary" style="font-size:1.3rem"></i>
        </div>
        <h5 class="fw-semibold mb-1">' . $lang->cronjobs['run_heading'] . '</h5>
        <div class="mb-3">
            <code class="bg-light px-2 py-1 rounded text-primary small">' . $cron_fname . '</code>
            ' . ($cron_desc ? '<div class="text-muted small mt-1">' . $cron_desc . '</div>' : '') . '
        </div>
        <p class="text-muted small mb-4">' . $lang->cronjobs['run_wait'] . '</p>
        <div class="progress mb-3" style="height:4px;border-radius:2px">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div>
        </div>
        <small class="text-muted">' . ags_fmt($lang->cronjobs['run_redirect'], '<span id="cron_countdown">2</span>') . '</small>
        <img src="' . $BASEURL . '/cron.php?rand=' . TIMENOW . '" width="1" height="1" alt="">
        <script>
            var s = 2;
            var t = setInterval(function(){
                s--;
                var el = document.getElementById("cron_countdown");
                if (el) el.textContent = s;
                if (s <= 0) { clearInterval(t); location.href = "' . addslashes($_this_script_) . '"; }
            }, 1000);
        </script>
    </div>
</div>';
        stdfoot();
        exit();
    }

    if (in_array($act2, ['active', 'disable']) && is_valid_id($cronid)) {
        $status = ($act2 === 'active') ? 1 : 0;
        $db->sql_query_prepared("UPDATE cron SET active = ? WHERE cronid = ?", [$status, $cronid]);
        flash_message($status ? $lang->cronjobs['flash_enabled'] : $lang->cronjobs['flash_disabled'], "success");
        admin_redirect($_this_script_);
        exit();
    }

    if ($act2 === 'delete' && is_valid_id($cronid)) {
        $db->sql_query_prepared("DELETE FROM cron WHERE cronid = ?", [$cronid]);
        flash_message($lang->cronjobs['flash_deleted'], "success");
        admin_redirect($_this_script_);
        exit();
    }
}

stdhead($lang->cronjobs['page_title']);

// ── Данные ───────────────────────────────────────────────────────────
$jobs = [];
$result = $db->sql_query_prepared("
    SELECT c.*,
           cl.runtime     AS last_runtime,
           cl.executetime AS last_executetime,
           cl.querycount  AS last_querycount
    FROM cron c
    LEFT JOIN cron_log cl ON cl.filename = c.filename
        AND cl.runtime = (SELECT MAX(cl2.runtime) FROM cron_log cl2 WHERE cl2.filename = c.filename)
    ORDER BY c.cronid
");
while ($result && ($row = $db->fetch_array($result))) $jobs[] = $row;

$logs = [];
$q = $db->sql_query_prepared('SELECT * FROM cron_log ORDER BY runtime DESC LIMIT 50');
while ($q && ($row = $db->fetch_array($q))) $logs[] = $row;

$cronDir   = TSDIR . '/cron/';
$checkDir  = is_dir($cronDir);                       // проверяем файлы, только если папка есть
$n_active  = count(array_filter($jobs, fn($j) => (int)$j['active'] === 1));
$n_overdue = count(array_filter($jobs, fn($j) => (int)$j['active'] === 1 && (int)$j['nextrun'] > 0 && (int)$j['nextrun'] < TIMENOW - 300));
$avg_exec  = $logs ? array_sum(array_map(fn($l) => (float)$l['executetime'], $logs)) / count($logs) : 0.0;

$timeFields = ['months' => 12, 'weeks' => 4, 'days' => 31, 'hours' => 24, 'minutes' => 60];
$unitLabels = [
    'months'  => $lang->cronjobs['lbl_unit_months'],
    'weeks'   => $lang->cronjobs['lbl_unit_weeks'],
    'days'    => $lang->cronjobs['lbl_unit_days'],
    'hours'   => $lang->cronjobs['lbl_unit_hours'],
    'minutes' => $lang->cronjobs['lbl_unit_minutes'],
];
$L = $lang->cronjobs;
$key = htmlspecialchars((string)$mybb->post_code);

/** Цвет длительности выполнения */
$execCls = static fn(float $t): string => $t > 5 ? 'is-bad' : ($t > 2 ? 'is-warn' : 'is-good');
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/cronjobs.css?ver=336">

<div class="container mt-3 mb-4 cj">

    <div class="cj-card mb-3"><div class="cj-head">
        <span class="cj-head-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
        <div style="min-width:0">
            <h1 class="cj-title"><?= $L['pane_title'] ?></h1>
            <div class="cj-sub"><?= $L['pane_sub'] ?></div>
        </div>
        <button type="button" class="btn btn-primary px-3 ms-auto" onclick="openCreateModal()"><i class="fa-solid fa-plus me-1"></i><?= $L['btn_new_job'] ?></button>
    </div></div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-list-check',          'ic-blue',   $L['kpi_jobs'],    count($jobs)],
            ['fa-circle-play',         'ic-green',  $L['kpi_active'],  $n_active],
            ['fa-triangle-exclamation', $n_overdue ? 'ic-red' : 'ic-slate', $L['kpi_overdue'], $n_overdue],
            ['fa-stopwatch',           'ic-amber',  $L['kpi_avg'],     ags_fmt($L['val_seconds'], number_format($avg_exec, 2))],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="cj-card cj-kpi"><span class="cj-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="cj-kpi-label"><?= $label ?></div><div class="cj-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <!-- ── Задачи ─────────────────────────────────────────────── -->
    <div class="cj-card overflow-hidden mb-3">
        <div class="cj-sec-head"><span class="cj-sec-icon ic-purple"><i class="fa-solid fa-gears"></i></span>
            <div><h2 class="cj-sec-title"><?= $L['sec_jobs'] ?></h2><div class="cj-muted"><?= ags_fmt($L['sec_jobs_sub'], count($jobs), $n_active) ?></div></div></div>
        <?php if ($jobs): ?>
        <div class="table-responsive"><table class="table cj-table">
            <thead><tr>
                <th><i class="fa-solid fa-file-code"></i><?= $L['th_job'] ?></th>
                <th><i class="fa-solid fa-hourglass-half"></i><?= $L['th_every'] ?></th>
                <th><i class="fa-solid fa-clock-rotate-left"></i><?= $L['th_last_run'] ?></th>
                <th><i class="fa-solid fa-calendar-check"></i><?= $L['th_next_run'] ?></th>
                <th><i class="fa-solid fa-power-off"></i><?= $L['th_status'] ?></th>
                <th class="text-end"><i class="fa-solid fa-bolt"></i></th>
            </tr></thead>
            <tbody>
            <?php foreach ($jobs as $cron):
                $id      = (int)$cron['cronid'];
                $active  = (int)$cron['active'] === 1;
                $next    = (int)$cron['nextrun'];
                $late    = $active && $next > 0 && $next < TIMENOW - 300;
                $missing = $checkDir && !is_file($cronDir . basename((string)$cron['filename']));
                $fileE   = htmlspecialchars((string)$cron['filename']);
            ?>
                <tr class="<?= $active ? '' : 'is-off' ?>">
                    <td><div class="d-flex align-items-center gap-3">
                        <span class="cj-ico <?= $active ? 'ic-purple' : 'ic-slate' ?>"><i class="fa-solid fa-file-code"></i></span>
                        <div style="min-width:0">
                            <div class="cj-file"><?= $fileE ?>
                                <?php if ($missing): ?><span class="cj-tag t-miss ms-1" title="<?= htmlspecialchars($L['tip_missing']) ?>"><i class="fa-solid fa-file-circle-exclamation"></i><?= $L['tag_missing'] ?></span><?php endif; ?>
                                <?php if ((int)$cron['loglevel'] === 1): ?><i class="fa-solid fa-clipboard-list text-body-secondary ms-1" title="<?= htmlspecialchars($L['tip_logged']) ?>"></i><?php endif; ?>
                            </div>
                            <div class="cj-muted"><?= htmlspecialchars((string)$cron['description']) ?></div>
                        </div>
                    </div></td>
                    <td><span class="cj-tag t-int"><i class="fa-solid fa-rotate"></i><?= mkprettytime((int)$cron['minutes']) ?></span></td>
                    <td class="text-nowrap">
                        <?php if (!empty($cron['last_runtime'])): ?>
                            <div><?= my_datee('relative', (int)$cron['last_runtime']) ?></div>
                            <?php if ($cron['last_executetime'] !== null): $t = (float)$cron['last_executetime']; ?>
                            <div class="cj-muted"><span class="cj-time <?= $execCls($t) ?>"><?= ags_fmt($L['val_seconds'], number_format($t, 3)) ?></span> · <?= ags_fmt($L['lbl_queries'], number_format((int)$cron['last_querycount'])) ?></div>
                            <?php endif; ?>
                        <?php else: ?><span class="cj-muted fst-italic"><?= $L['lbl_never'] ?></span><?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if (!$active): ?><span class="cj-muted">—</span>
                        <?php elseif ($late): ?><span class="cj-tag t-late" title="<?= htmlspecialchars(my_datee($dateformat, $next) . ' ' . my_datee($timeformat, $next)) ?>"><i class="fa-solid fa-triangle-exclamation"></i><?= $L['tag_overdue'] ?></span>
                        <?php else: ?>
                            <div><?= my_datee('relative', $next) ?></div>
                            <div class="cj-muted"><?= my_datee($dateformat, $next) ?> <?= my_datee($timeformat, $next) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $active ? '<span class="cj-tag t-on"><i class="fa-solid fa-circle-check"></i>' . $L['tag_active'] . '</span>' : '<span class="cj-tag t-off"><i class="fa-solid fa-circle-pause"></i>' . $L['tag_disabled'] . '</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="<?= $_this_script_ ?>" class="d-inline">
                            <input type="hidden" name="my_post_key" value="<?= $key ?>"><input type="hidden" name="act2" value="run"><input type="hidden" name="cronid" value="<?= $id ?>">
                            <button type="submit" class="cj-act" title="<?= htmlspecialchars($L['tip_run']) ?>"><i class="fa-solid fa-play"></i></button>
                        </form>
                        <button type="button" class="cj-act" title="<?= htmlspecialchars($L['tip_edit']) ?>" onclick="openEditModal(<?= $id ?>)"><i class="fa-solid fa-pen"></i></button>
                        <form method="post" action="<?= $_this_script_ ?>" class="d-inline">
                            <input type="hidden" name="my_post_key" value="<?= $key ?>"><input type="hidden" name="act2" value="<?= $active ? 'disable' : 'active' ?>"><input type="hidden" name="cronid" value="<?= $id ?>">
                            <button type="submit" class="cj-act <?= $active ? 'amber' : 'green' ?>" title="<?= htmlspecialchars($active ? $L['tip_disable'] : $L['tip_enable']) ?>"><i class="fa-solid <?= $active ? 'fa-pause' : 'fa-power-off' ?>"></i></button>
                        </form>
                        <form method="post" action="<?= $_this_script_ ?>" class="d-inline cj-del" data-file="<?= $fileE ?>">
                            <input type="hidden" name="my_post_key" value="<?= $key ?>"><input type="hidden" name="act2" value="delete"><input type="hidden" name="cronid" value="<?= $id ?>">
                            <button type="submit" class="cj-act danger" title="<?= htmlspecialchars($L['tip_delete']) ?>"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <div class="cj-empty"><i class="fa-solid fa-inbox"></i><div class="fw-semibold"><?= $L['empty_jobs'] ?></div>
            <button class="btn btn-primary btn-sm mt-3 px-3" onclick="openCreateModal()"><i class="fa-solid fa-plus me-1"></i><?= $L['btn_create_first'] ?></button></div>
        <?php endif; ?>
    </div>

    <!-- ── Журнал выполнения ──────────────────────────────────── -->
    <div class="cj-card overflow-hidden">
        <div class="cj-sec-head"><span class="cj-sec-icon ic-slate"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div><h2 class="cj-sec-title"><?= $L['sec_log'] ?></h2><div class="cj-muted"><?= $L['sec_log_sub'] ?></div></div>
            <?php if ($logs): ?><div class="ms-auto cj-filter"><input type="search" class="form-control form-control-sm" id="cjLogFilter" placeholder="<?= htmlspecialchars($L['ph_log_filter']) ?>"></div><?php endif; ?>
        </div>
        <?php if ($logs): ?>
        <div class="table-responsive"><table class="table cj-table" id="cjLog">
            <thead><tr>
                <th><i class="fa-solid fa-file-code"></i><?= $L['th_file'] ?></th>
                <th class="text-center"><i class="fa-solid fa-database"></i><?= $L['th_queries'] ?></th>
                <th class="text-center"><i class="fa-solid fa-stopwatch"></i><?= $L['th_duration'] ?></th>
                <th class="text-end"><i class="fa-solid fa-calendar"></i><?= $L['th_when'] ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $log): $t = (float)$log['executetime']; ?>
                <tr data-f="<?= htmlspecialchars(strtolower((string)$log['filename'])) ?>">
                    <td class="cj-file"><?= htmlspecialchars((string)$log['filename']) ?></td>
                    <td class="text-center"><?= ts_nf($log['querycount']) ?></td>
                    <!-- раньше время выводилось «как есть», с 10+ знаками после запятой -->
                    <td class="text-center"><span class="cj-time <?= $execCls($t) ?>"><?= ags_fmt($L['val_seconds'], number_format($t, 3)) ?></span></td>
                    <!-- раньше date() — без учёта часового пояса пользователя, в отличие от остальных дат -->
                    <td class="text-end text-nowrap"><span title="<?= htmlspecialchars(my_datee($dateformat, (int)$log['runtime']) . ' ' . my_datee($timeformat, (int)$log['runtime'])) ?>"><?= my_datee('relative', (int)$log['runtime']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <div class="cj-empty"><i class="fa-solid fa-clipboard-list"></i><div class="fw-semibold"><?= $L['empty_log'] ?></div></div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ MODAL: Create / Edit ══════════════════════════════════════════ -->
<div class="modal fade cj-modal" id="cronModal" tabindex="-1" aria-labelledby="cronModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <span class="cj-mh-icon" id="modalIconWrap"><i class="fa-solid fa-plus" id="modalIcon"></i></span>
                <h5 class="modal-title fw-bold" id="cronModalLabel"><?= $L['pane_modal_new'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($L['lbl_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <div id="modalLoader" class="text-center py-4 d-none"><span class="spinner-border spinner-border-sm text-primary me-2"></span><?= $L['lbl_loading'] ?></div>

                <form id="cronForm" method="POST" action="">
                    <input type="hidden" name="my_post_key" value="<?= $key ?>">
                    <input type="hidden" id="formCronId" name="cronid" value="999">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="modalFilename"><i class="fa-solid fa-file-code"></i><?= $L['lbl_file'] ?></label>
                            <input type="text" class="form-control font-monospace" name="filename" id="modalFilename" placeholder="seedbonus.php" required pattern="[A-Za-z0-9_\-]+\.php">
                            <div class="form-text"><?= $L['hint_file'] ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modalDescription"><i class="fa-solid fa-align-left"></i><?= $L['lbl_description'] ?></label>
                            <input type="text" class="form-control" name="description" id="modalDescription" placeholder="<?= htmlspecialchars($L['ph_description']) ?>" required>
                        </div>
                    </div>

                    <label class="form-label"><i class="fa-solid fa-hourglass-half"></i><?= $L['lbl_run_every'] ?></label>
                    <div class="cj-units">
                        <?php foreach ($timeFields as $name => $max): ?>
                        <div class="cj-unit">
                            <label for="modal_<?= $name ?>" class="d-block"><?= $unitLabels[$name] ?></label>
                            <select class="form-select" name="<?= $name ?>" id="modal_<?= $name ?>">
                                <?php for ($i = 0; $i <= $max; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="cj-presets">
                        <?php foreach ([
                            [$L['opt_preset_5m'],  [0,0,0,0,5]],
                            [$L['opt_preset_15m'], [0,0,0,0,15]],
                            [$L['opt_preset_30m'], [0,0,0,0,30]],
                            [$L['opt_preset_1h'],  [0,0,0,1,0]],
                            [$L['opt_preset_6h'],  [0,0,0,6,0]],
                            [$L['opt_preset_1d'],  [0,0,1,0,0]],
                            [$L['opt_preset_1w'],  [0,1,0,0,0]],
                        ] as [$lbl, $v]): ?>
                        <button type="button" class="cj-preset" data-v="<?= implode(',', $v) ?>"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="cj-sum" id="cjSum"></div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="cj-switch" for="modalActive">
                                <i class="fa-solid fa-power-off text-success"></i>
                                <span><b class="d-block"><?= $L['lbl_active'] ?></b><small class="text-body-secondary"><?= $L['hint_active'] ?></small></span>
                                <input class="form-check-input" type="checkbox" role="switch" id="modalActive" name="active" value="1">
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="cj-switch" for="modalLoglevel">
                                <i class="fa-solid fa-clipboard-list text-primary"></i>
                                <span><b class="d-block"><?= $L['lbl_loglevel'] ?></b><small class="text-body-secondary"><?= $L['hint_loglevel'] ?></small></span>
                                <input class="form-check-input" type="checkbox" role="switch" id="modalLoglevel" name="loglevel" value="1">
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i><?= $L['btn_cancel'] ?></button>
                <button type="button" class="btn btn-primary px-4" id="modalSaveBtn" onclick="submitCronForm()"><i class="fa-solid fa-floppy-disk me-1"></i><span id="modalSaveBtnText"><?= $L['btn_create'] ?></span></button>
            </div>
        </div>
    </div>
</div>

<?php
$cjJsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $cjJsLang[substr((string)$k, 3)] = $v;
    }
}
?>
<script>
const thisScript = <?= json_encode(html_entity_decode((string)$_this_script_)) ?>;
const AGS_LANG = <?= json_encode($cjJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/admin/scripts/cronjobs.js?ver=1"></script>

<?php stdfoot(); ?>