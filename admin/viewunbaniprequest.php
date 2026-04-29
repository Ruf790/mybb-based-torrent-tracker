<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct access to this file is not allowed.</div>');
}

define('UL_VERSION', 'by xam v.0.8');

// ── Helpers ───────────────────────────────────────────────────

function auto_redirect(string $url, int $seconds = 2): string
{
    $ms = $seconds * 1000;
    $json_url = json_encode($url);
    return "<script>setTimeout(() => location.href = {$json_url}, {$ms});</script>"
         . '<p class="text-muted small mt-2"><i class="bi bi-arrow-clockwise me-1"></i>Redirecting in ' . $seconds . ' seconds…</p>';
}

function display_error(string $title, string $message): void
{
    global $_this_script_;
    stdhead($title);
    echo '<div class="container mt-4">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>' . htmlspecialchars($title) . '</h5>
            </div>
            <div class="card-body">
                <p class="card-text">' . htmlspecialchars($message) . '</p>
                ' . auto_redirect($_this_script_) . '
            </div>
        </div>
    </div>';
    stdfoot();
}

// ── Actions ───────────────────────────────────────────────────

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        display_error('Error', 'Invalid request ID');
        exit;
    }

    stdhead('Unban Requests Manager');

    try {
        $result = $db->sql_query('DELETE FROM unbanrequests WHERE id = ' . $db->sqlesc($id));

        if ($result) {
            echo '<div class="container mt-4">
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Done!</strong> Unban request #' . $id . ' has been deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                ' . auto_redirect($_this_script_) . '
            </div>';
        } else {
            display_error('Delete Failed', 'Unable to delete unban request #' . $id);
        }
    } catch (Exception $e) {
        display_error('Database Error', 'Failed to delete request: ' . $e->getMessage());
    }

    stdfoot();
    exit;
}

// ── Main page ─────────────────────────────────────────────────

stdhead('Unban Requests Manager');


echo '<link href="' .$BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';

$perpage      = $ts_perpage ?? 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $perpage;

$count_query = $db->sql_query('SELECT COUNT(*) AS total FROM unbanrequests');
$total_count = (int)($db->fetch_array($count_query)['total'] ?? 0);

$total_pages  = max(1, (int)ceil($total_count / $perpage));
$current_page = min($current_page, $total_pages);

$result = $db->sql_query(
    "SELECT u.*, l.id AS loginaid
     FROM unbanrequests u
     LEFT JOIN loginattempts l ON (u.ip = l.ip OR u.realip = l.ip)
     ORDER BY u.added DESC
     LIMIT " . (int)$offset . ", " . (int)$perpage
);

?>

<!-- ══════════════════ PAGE HEADER ══════════════════ -->
<div class="container-fluid py-4" style="background:linear-gradient(135deg,#f8f9fa,#e9ecef)">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php">Staff Panel</a></li>
                        <li class="breadcrumb-item active">Unban Requests</li>
                    </ol>
                </nav>
                <h1 class="h2 mb-0 mt-2">
                    <i class="bi bi-shield-lock me-2"></i>Unban Requests Manager
                </h1>
                <p class="text-muted mb-0">
                    Manage user requests for IP unbanning
                    <span class="badge bg-primary ms-2"><?= UL_VERSION ?></span>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-secondary fs-6">
                    <i class="bi bi-list-ul me-1"></i><?= number_format($total_count) ?> Requests
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<div class="container mt-4">

<?php if ($db->num_rows($result) === 0): ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted opacity-50 d-block mb-3"></i>
            <h3 class="text-muted mb-2">No Unban Requests</h3>
            <p class="text-muted mb-0">There are currently no pending unban requests.</p>
        </div>
    </div>

<?php else: ?>

    <div class="card border-0 shadow-sm">

        <!-- Card header -->
        <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table me-2"></i>Unban Requests</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button class="btn btn-outline-secondary" onclick="exportToCSV()">
                    <i class="bi bi-download me-1"></i>Export
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:80px"><i class="bi bi-hash"></i> ID</th>
                        <th><i class="bi bi-globe me-1"></i>IP Address</th>
                        <th><i class="bi bi-globe-americas me-1"></i>Real IP</th>
                        <th><i class="bi bi-envelope me-1"></i>Email</th>
                        <th><i class="bi bi-chat-text me-1"></i>Comment</th>
                        <th><i class="bi bi-calendar-plus me-1"></i>Submitted</th>
                        <th class="text-center" style="width:150px"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($request = $db->fetch_array($result)): ?>
                    <tr id="row-<?= (int)$request['id'] ?>">

                        <td class="text-center">
                            <span class="badge bg-dark rounded-pill">#<?= (int)$request['id'] ?></span>
                        </td>

                        <td><code><?= htmlspecialchars((string)($request['ip']     ?? 'N/A')) ?></code></td>
                        <td><code><?= htmlspecialchars((string)($request['realip'] ?? 'N/A')) ?></code></td>

                        <td>
                            <?php if (!empty($request['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="text-decoration-none">
                                    <i class="bi bi-envelope me-1 text-primary"></i><?= htmlspecialchars($request['email']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="text-truncate comment-preview" style="max-width:250px"
                                 data-bs-toggle="tooltip" data-bs-placement="top"
                                 title="<?= htmlspecialchars((string)($request['comment'] ?? '')) ?>">
                                <?= htmlspecialchars((string)($request['comment'] ?? 'No comment')) ?>
                            </div>
                        </td>

                        <td class="small text-muted">
                            <div><i class="bi bi-calendar3 me-1"></i><?= my_datee($dateformat, $request['added']) ?></div>
                            <div><i class="bi bi-clock me-1"></i><?= my_datee($timeformat, $request['added']) ?></div>
                        </td>

                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <?php if ($request['loginaid']): ?>
                                    <a href="<?= $_this_script_no_act ?>?act=maxlogin&action=edit&id=<?= (int)$request['loginaid'] ?>&return=yes"
                                       class="btn btn-outline-primary"
                                       data-bs-toggle="tooltip" title="Edit Failed Login Attempt">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= $_this_script_no_act ?>?act=maxlogin&action=delete&id=<?= (int)$request['loginaid'] ?>&return=yes"
                                       class="btn btn-outline-warning"
                                       data-bs-toggle="tooltip" title="Delete Failed Login Attempt"
                                       onclick="return confirm('Delete this failed login attempt?')">
                                        <i class="bi bi-shield-x"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Delete button — triggers modal -->
                                <button type="button"
                                        class="btn btn-outline-danger btn-delete-request"
                                        data-id="<?= (int)$request['id'] ?>"
                                        data-ip="<?= htmlspecialchars((string)($request['ip'] ?? '')) ?>"
                                        data-email="<?= htmlspecialchars((string)($request['email'] ?? 'N/A')) ?>"
                                        data-bs-toggle="tooltip" title="Delete Unban Request">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>

                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-light border-0">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $_this_script_ ?>&page=<?= max(1, $current_page - 1) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $_this_script_ ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $_this_script_ ?>&page=<?= min($total_pages, $current_page + 1) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Legend -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-light border-0">
            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Action Legend</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><i class="bi bi-pencil"></i></span>
                    <span class="small">Edit Failed Login Attempt</span>
                </div>
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark"><i class="bi bi-shield-x"></i></span>
                    <span class="small">Delete Failed Login Attempt</span>
                </div>
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span class="badge bg-danger"><i class="bi bi-trash"></i></span>
                    <span class="small">Delete Unban Request</span>
                </div>
            </div>
            <div class="alert alert-info mt-3 mb-0">
                <i class="bi bi-lightbulb me-2"></i>
                <strong>Note:</strong> If no edit button is shown, the IP address could not be found in the failed login attempts database.
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- ══════════════════ DELETE MODAL ══════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden">

            <!-- Animated danger stripe -->
            <div style="height:4px;background:linear-gradient(90deg,#dc3545,#ff6b6b,#dc3545);background-size:200%;animation:stripe 2s linear infinite"></div>

            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(220,53,69,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-trash3-fill text-danger fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold" id="deleteModalLabel">Delete Unban Request</h5>
                        <small class="text-muted">This action cannot be undone</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="alert alert-danger border-0 rounded-3 mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    You are about to permanently delete this unban request.
                </div>

                <!-- Request details -->
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="row g-2 small">
                        <div class="col-4 text-muted fw-semibold">Request ID</div>
                        <div class="col-8"><span class="badge bg-dark" id="modal-id">—</span></div>

                        <div class="col-4 text-muted fw-semibold">IP Address</div>
                        <div class="col-8"><code id="modal-ip">—</code></div>

                        <div class="col-4 text-muted fw-semibold">Email</div>
                        <div class="col-8" id="modal-email">—</div>
                    </div>
                </div>

                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    The user will <strong>not</strong> be notified about this deletion.
                </p>
            </div>

            <div class="modal-footer border-0 pt-0 gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <a href="#" id="modal-confirm-btn" class="btn btn-danger px-4">
                    <i class="bi bi-trash3 me-1"></i>Delete Request
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════ JS ══════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    const modal      = new bootstrap.Modal(document.getElementById('deleteModal'));
    const confirmBtn = document.getElementById('modal-confirm-btn');
    const baseUrl    = <?= json_encode($_this_script_ . '&action=delete&id=') ?>;

    // Open modal on delete button click
    document.querySelectorAll('.btn-delete-request').forEach(btn => {
        btn.addEventListener('click', function () {
            const id    = this.dataset.id;
            const ip    = this.dataset.ip;
            const email = this.dataset.email;

            document.getElementById('modal-id').textContent    = '#' + id;
            document.getElementById('modal-ip').textContent    = ip;
            document.getElementById('modal-email').textContent = email;
            confirmBtn.href = baseUrl + id;

            modal.show();
        });
    });

    // Row highlight when modal opens
    document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget || document.querySelector('.btn-delete-request:focus');
        if (btn) {
            const id  = btn.dataset?.id;
            const row = document.getElementById('row-' + id);
            if (row) row.classList.add('table-danger');
        }
    });

    document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('tr.table-danger').forEach(r => r.classList.remove('table-danger'));
    });
});

// CSV export
function exportToCSV() {
    const rows  = document.querySelectorAll('table tr');
    const lines = [];
    rows.forEach(row => {
        const cols = [...row.querySelectorAll('td, th')].map(c => '"' + c.innerText.replace(/\n/g, ' ').trim() + '"');
        lines.push(cols.join(','));
    });
    const uri  = 'data:text/csv;charset=utf-8,' + encodeURI(lines.join('\n'));
    const link = Object.assign(document.createElement('a'), { href: uri, download: 'unban_requests_' + new Date().toISOString().split('T')[0] + '.csv' });
    document.body.appendChild(link);
    link.click();
    link.remove();
}
</script>

<style>
@keyframes stripe {
    0%   { background-position: 0% 0; }
    100% { background-position: 200% 0; }
}
.comment-preview { cursor: help; transition: color .15s; }
.comment-preview:hover { color: #0d6efd; }
.table td, .table th { vertical-align: middle; }
.table-hover tbody tr:hover { background: rgba(13,110,253,.04); }
code { font-size: .82rem; color: #495057; }
</style>

<?php stdfoot(); ?>