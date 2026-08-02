<?php
declare(strict_types=1);

require_once(INC_PATH . '/functions_mkprettytime.php');

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
    if ($type === 'status') {
        $class = $active ? 'success' : 'danger';
        $text  = $active ? 'ACTIVE'  : 'DISABLED';
        $icon  = $active ? 'fa-check-circle' : 'fa-times-circle';
    } else {
        $class = $active ? 'success' : 'secondary';
        $text  = $active ? 'YES'     : 'NO';
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
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
    exit();
}

// === POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($act2 === 'save' || $act2 === 'save_new') && (is_valid_id($cronid) || $act2 === 'save_new')) {
        if (!verify_post_check($_POST['my_post_key'] ?? '')) {
            http_response_code(403);
            echo 'Invalid security token';
            exit;
        }

        $rawFilename = trim($_POST['filename'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $rawFilename)) {
            flash_message("Invalid filename. Only letters, numbers, underscore, hyphen and a .php extension are allowed (no paths).", "error");
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

        $act2ive  = !empty($_POST['active'])   ? 1 : 0;
        $loglevel = !empty($_POST['loglevel']) ? 1 : 0;

        if ($act2 === 'save_new') {
            $nextrun = TIMENOW + $minutes;
            $db->sql_query_prepared(
                "INSERT INTO cron (filename,description,minutes,nextrun,active,loglevel) VALUES (?,?,?,?,?,?)",
                [$filename, $description, $minutes, $nextrun, $act2ive, $loglevel]
            );
            flash_message("New cron job created successfully!", "success");
        } else {
            $db->sql_query_prepared(
                "UPDATE cron SET filename=?, description=?, minutes=?, active=?, loglevel=? WHERE cronid=?",
                [$filename, $description, $minutes, $act2ive, $loglevel, $cronid]
            );
            flash_message("Cron job updated successfully!", "success");
        }

        admin_redirect($_this_script_);
        exit();
    }
}

// === POST actions (run / activate / disable / delete) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($act2, ['run', 'active', 'disable', 'delete'], true)) {
    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        http_response_code(403);
        echo 'Invalid security token';
        exit;
    }

    if ($act2 === 'run' && is_valid_id($cronid)) {
        $db->sql_query_prepared("UPDATE cron SET nextrun='0' WHERE cronid = ?", [$cronid]);

        // Fetch cron name for display
        $cron_info_q = $db->sql_query_prepared("SELECT filename, description FROM cron WHERE cronid = ?", [$cronid]);
        $cron_info   = ($cron_info_q && $db->num_rows($cron_info_q)) ? $db->fetch_array($cron_info_q) : [];
        $cron_fname  = htmlspecialchars($cron_info['filename']    ?? 'unknown');
        $cron_desc   = htmlspecialchars($cron_info['description'] ?? '');

        stdhead('Running Cron...');
        echo '
<div class="d-flex align-items-center justify-content-center" style="min-height:60vh">
    <div class="text-center" style="max-width:400px">
        <div class="mb-4 position-relative d-inline-block">
            <div class="spinner-border text-primary" style="width:56px;height:56px;border-width:3px" role="status"></div>
            <i class="fas fa-clock position-absolute top-50 start-50 translate-middle text-primary" style="font-size:1.3rem"></i>
        </div>
        <h5 class="fw-semibold mb-1">Running Cron Job</h5>
        <div class="mb-3">
            <code class="bg-light px-2 py-1 rounded text-primary small">' . $cron_fname . '</code>
            ' . ($cron_desc ? '<div class="text-muted small mt-1">' . $cron_desc . '</div>' : '') . '
        </div>
        <p class="text-muted small mb-4">Please wait while the job executes...</p>
        <div class="progress mb-3" style="height:4px;border-radius:2px">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div>
        </div>
        <small class="text-muted">Redirecting in <span id="cron_countdown">2</span>s...</small>
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
        flash_message("Cron job " . ($status ? "enabled" : "disabled") . " successfully!", "success");
        admin_redirect($_this_script_);
        exit();
    }

    if ($act2 === 'delete' && is_valid_id($cronid)) {
        $db->sql_query_prepared("DELETE FROM cron WHERE cronid = ?", [$cronid]);
        flash_message("Cron job deleted successfully!", "success");
        admin_redirect($_this_script_);
        exit();
    }
}

stdhead('⚡ Cron Jobs Management');

// Prepare time selectors HTML helper
$timeFields = ['months' => 12, 'weeks' => 4, 'days' => 31, 'hours' => 24, 'minutes' => 60];
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/admin/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="breadcrumb-item active">⚡ Cron Jobs</li>
        </ol>
    </nav>

    <!-- ─── Cron Jobs List ─────────────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-tasks me-2"></i>
                <h5 class="mb-0 d-inline-block">Cron Jobs</h5>
                <span class="badge bg-light text-dark ms-2">
                    <?php
                    $count_result = $db->sql_query_prepared("SELECT COUNT(*) as total FROM cron");
                    $count_row = $count_result ? $db->fetch_array($count_result) : null;
                    echo (int)($count_row['total'] ?? 0);
                    ?> jobs
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-light" onclick="openCreateModal()">
                <i class="fas fa-plus-circle me-1"></i> Create New
            </button>
        </div>
        <div class="card-body table-responsive">
            <?php
            $result = $db->sql_query_prepared("
    SELECT c.*,
           cl.runtime    AS last_runtime,
           cl.executetime AS last_executetime
    FROM cron c
    LEFT JOIN cron_log cl ON cl.filename = c.filename
        AND cl.runtime = (
            SELECT MAX(cl2.runtime) FROM cron_log cl2 WHERE cl2.filename = c.filename
        )
    ORDER BY c.cronid
");
            if ($result && $db->num_rows($result) > 0):
            ?>
            <table class="table table-hover table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th><i class="fas fa-file me-1"></i> Filename</th>
                        <th><i class="fas fa-align-left me-1"></i> Description</th>
                        <th><i class="fas fa-clock me-1"></i> Run Period</th>
                        <th><i class="fas fa-history me-1"></i> Last Run</th>
                        <th><i class="fas fa-calendar-check me-1"></i> Next Run</th>
                        <th><i class="fas fa-clipboard-list me-1"></i> Logging</th>
                        <th><i class="fas fa-power-off me-1"></i> Status</th>
                        <th class="text-center"><i class="fas fa-cog me-1"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($cron = $db->fetch_array($result)): ?>
                    <tr>
                        <td><code class="text-primary"><?= htmlspecialchars($cron['filename']) ?></code></td>
                        <td><?= htmlspecialchars($cron['description']) ?></td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                <i class="fas fa-hourglass-half me-1"></i><?= mkprettytime((int)$cron['minutes']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($cron['last_runtime'])): ?>
                                <span class="fw-semibold"><?= my_datee($dateformat, (int)$cron['last_runtime']) ?></span>
                                <br><small class="text-muted"><?= my_datee($timeformat, (int)$cron['last_runtime']) ?></small>
                            <?php else: ?>
                                <small class="text-muted fst-italic">Never</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-semibold"><?= my_datee($dateformat, $cron['nextrun']) ?></span>
                            <br><small class="text-muted"><?= my_datee($timeformat, $cron['nextrun']) ?></small>
                        </td>
                        <td><?= render_status_badge((bool)$cron['loglevel'], 'log') ?></td>
                        <td><?= render_status_badge((bool)$cron['active'], 'status') ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <form method="post" action="<?= $_this_script_ ?>" class="d-inline">
                                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                                    <input type="hidden" name="act2" value="run">
                                    <input type="hidden" name="cronid" value="<?= (int)$cron['cronid'] ?>">
                                    <button type="submit" class="btn btn-outline-primary" title="Run Now" data-bs-toggle="tooltip">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-secondary"
                                        title="Edit" data-bs-toggle="tooltip"
                                        onclick="openEditModal(<?= (int)$cron['cronid'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" action="<?= $_this_script_ ?>" class="d-inline">
                                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                                    <input type="hidden" name="act2" value="<?= $cron['active'] ? 'disable' : 'active' ?>">
                                    <input type="hidden" name="cronid" value="<?= (int)$cron['cronid'] ?>">
                                    <button type="submit" class="btn btn-outline-<?= $cron['active'] ? 'warning' : 'success' ?>"
                                            title="<?= $cron['active'] ? 'Disable' : 'Enable' ?>" data-bs-toggle="tooltip">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <form method="post" action="<?= $_this_script_ ?>" class="d-inline"
                                      onsubmit="return confirm('Delete cron job: <?= addslashes(htmlspecialchars($cron['filename'])) ?>?')">
                                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                                    <input type="hidden" name="act2" value="delete">
                                    <input type="hidden" name="cronid" value="<?= (int)$cron['cronid'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete" data-bs-toggle="tooltip">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No cron jobs found</h5>
                <p class="text-muted">
                    <button class="btn btn-primary btn-sm" onclick="openCreateModal()">
                        <i class="fas fa-plus me-1"></i>Create your first cron job
                    </button>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── Execution Logs ──────────────────────────────────────────────── -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-history me-2"></i>
                <h6 class="mb-0 d-inline-block">Execution Logs</h6>
                <span class="badge bg-light text-dark ms-2">Last 50 entries</span>
            </div>
        </div>
        <div class="card-body table-responsive">
            <?php
            $query = $db->sql_query_prepared('SELECT * FROM cron_log ORDER BY runtime DESC LIMIT 50');
            if ($query && $db->num_rows($query) > 0):
            ?>
            <table class="table table-sm table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th><i class="fas fa-file me-1"></i> Filename</th>
                        <th class="text-center"><i class="fas fa-database me-1"></i> Queries</th>
                        <th class="text-center"><i class="fas fa-stopwatch me-1"></i> Execute Time</th>
                        <th class="text-center"><i class="fas fa-calendar me-1"></i> Last Run</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($log = $db->fetch_array($query)):
                    $exec_time  = (float)$log['executetime'];
                    $time_class = $exec_time > 5 ? 'text-danger' : ($exec_time > 2 ? 'text-warning' : 'text-success');
                ?>
                    <tr>
                        <td><code class="text-primary"><?= htmlspecialchars($log['filename']) ?></code></td>
                        <td class="text-center">
                            <span class="badge bg-info bg-opacity-10 text-info"><?= ts_nf($log['querycount']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="<?= $time_class ?> fw-semibold"><?= $exec_time ?> sec</span>
                        </td>
                        <td class="text-center">
                            <span><?= date($dateformat, (int)$log['runtime']) ?></span>
                            <br><small class="text-muted"><?= date($timeformat, (int)$log['runtime']) ?></small>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">No execution logs yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════
     MODAL: Create / Edit Cron Job
     ══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="cronModal" tabindex="-1" aria-labelledby="cronModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">

            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         id="modalIconWrap" style="width:36px;height:36px;background:rgba(13,110,253,.1)">
                        <i class="fas fa-plus text-primary" id="modalIcon"></i>
                    </div>
                    <h5 class="modal-title mb-0" id="cronModalLabel">Create New Cron Job</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-3">
                <div id="modalLoader" class="text-center py-4 d-none">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                    Loading...
                </div>

                <form id="cronForm" method="POST" action="">
                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                    <input type="hidden" id="formCronId" name="cronid" value="999">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">
                                <i class="fas fa-file me-1 text-muted"></i>Filename
                            </label>
                            <input type="text" class="form-control form-control-sm font-monospace"
                                   name="filename" id="modalFilename"
                                   placeholder="seedbonus.php" required>
                            <div class="form-text">PHP file in /cron/ directory</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">
                                <i class="fas fa-align-left me-1 text-muted"></i>Description
                            </label>
                            <input type="text" class="form-control form-control-sm"
                                   name="description" id="modalDescription"
                                   placeholder="What does this job do?" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="fas fa-clock me-1 text-muted"></i>Run Interval
                        </label>
                        <div class="row g-2">
                            <?php foreach ($timeFields as $name => $max): ?>
                            <div class="col">
                                <label class="form-label small text-muted mb-1 text-capitalize"><?= $name ?></label>
                                <select class="form-select form-select-sm" name="<?= $name ?>" id="modal_<?= $name ?>">
                                    <?php for ($i = 0; $i <= $max; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-1">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modalActive" name="active" value="1">
                                <label class="form-check-label fw-semibold small" for="modalActive">
                                    <i class="fas fa-power-off me-1 text-muted"></i>Active
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modalLoglevel" name="loglevel" value="1">
                                <label class="form-check-label fw-semibold small" for="modalLoglevel">
                                    <i class="fas fa-clipboard-list me-1 text-muted"></i>Log Execution
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="modalSaveBtn" onclick="submitCronForm()">
                    <i class="fas fa-save me-1"></i><span id="modalSaveBtnText">Create Job</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const cronModal    = new bootstrap.Modal(document.getElementById('cronModal'));
const thisScript   = <?= json_encode($_this_script_) ?>;

function openCreateModal() {
    // Reset form
    document.getElementById('cronForm').reset();
    document.getElementById('formCronId').value = '999';
    document.getElementById('cronForm').action  = thisScript + '&act2=save_new&cronid=999';

    // Update UI
    document.getElementById('cronModalLabel').textContent = 'Create New Cron Job';
    document.getElementById('modalIcon').className        = 'fas fa-plus text-primary';
    document.getElementById('modalIconWrap').style.background = 'rgba(13,110,253,.1)';
    document.getElementById('modalSaveBtnText').textContent   = 'Create Job';

    cronModal.show();
}

function openEditModal(cronid) {
    // Update UI to "edit" state
    document.getElementById('cronModalLabel').textContent = 'Edit Cron Job';
    document.getElementById('modalIcon').className        = 'fas fa-pen text-success';
    document.getElementById('modalIconWrap').style.background = 'rgba(32,201,151,.1)';
    document.getElementById('modalSaveBtnText').textContent   = 'Save Changes';
    document.getElementById('modalLoader').classList.remove('d-none');
    document.getElementById('cronForm').classList.add('d-none');

    cronModal.show();

    // Load cron data via AJAX
    fetch(thisScript + '&act2=get_cron_data&cronid=' + cronid)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Failed to load cron job data');
                cronModal.hide();
                return;
            }

            // Populate form
            document.getElementById('formCronId').value         = data.cronid;
            document.getElementById('modalFilename').value      = data.filename;
            document.getElementById('modalDescription').value   = data.description;
            document.getElementById('modalActive').checked      = data.active === 1;
            document.getElementById('modalLoglevel').checked    = data.loglevel === 1;

            // Set time selectors
            ['months','weeks','days','hours','minutes'].forEach(f => {
                const sel = document.getElementById('modal_' + f);
                if (sel) sel.value = (data.tarray[f] ?? 0).toString();
            });

            document.getElementById('cronForm').action = thisScript + '&act2=save&cronid=' + data.cronid;

            // Show form
            document.getElementById('modalLoader').classList.add('d-none');
            document.getElementById('cronForm').classList.remove('d-none');
        })
        .catch(() => {
            alert('Network error loading cron job');
            cronModal.hide();
        });
}

function submitCronForm() {
    const form = document.getElementById('cronForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    form.submit();
}

// Init tooltips
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
        new bootstrap.Tooltip(el)
    );
});
</script>

<?php stdfoot(); ?>