<?php
declare(strict_types=1);

define('IN_MYBB', 1);



$action = $_GET['action'] ?? 'list';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

// ── POST: Admin actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['admin_revoke'])) {
        $invite_id = (int)$_POST['invite_id'];
        $inv = get_invite_by_id($invite_id);
        if ($inv && $inv['status'] === 'pending') {
            $db->sql_query("UPDATE invites SET status='revoked' WHERE id={$invite_id}");
        }
    }

    if (isset($_POST['admin_delete'])) {
        $invite_id = (int)$_POST['invite_id'];
        $db->sql_query("DELETE FROM invites WHERE id={$invite_id}");
    }

    if (isset($_POST['add_invites'])) {
        $user_id = (int)$_POST['user_id'];
        $amount  = max(1, min(100, (int)$_POST['amount']));
        $db->sql_query("UPDATE users SET invites = invites + {$amount} WHERE id={$user_id}");
    }

    if (!empty($_POST['bulk_action_type'])) {
        $action_type = $_POST['bulk_action_type'];
        $invite_ids  = $_POST['invite_ids'] ?? [];
        if (!empty($invite_ids)) {
            $ids = implode(',', array_map('intval', $invite_ids));
            if ($action_type === 'revoke') {
                $db->sql_query("UPDATE invites SET status='revoked' WHERE id IN ({$ids}) AND status='pending'");
            } elseif ($action_type === 'delete') {
                $db->sql_query("DELETE FROM invites WHERE id IN ({$ids})");
            }
        }
    }

    redirect($_this_script_);
}

// ── Stats ─────────────────────────────────────────────────────────────────────
expire_old_invites();
$stats = get_invite_stats();

// ── Filter ────────────────────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$filter_search = trim($_GET['search'] ?? '');

$where = '1=1';
if ($filter_status) {
    $where .= " AND i.status='" . $db->escape_string($filter_status) . "'";
}
if ($filter_search) {
    $s = $db->escape_string($filter_search);
    $where .= " AND (i.code LIKE '%{$s}%' OR u1.username LIKE '%{$s}%' OR u2.username LIKE '%{$s}%' OR i.email LIKE '%{$s}%')";
}

$total_q = $db->sql_query("
    SELECT COUNT(*) AS cnt FROM invites i
    LEFT JOIN users u1 ON i.inviter_id = u1.id
    LEFT JOIN users u2 ON i.invitee_id = u2.id
    WHERE {$where}
");
$total_row   = $db->fetch_array($total_q);
$total_items = (int)$total_row['cnt'];
$total_pages = max(1, (int)ceil($total_items / $limit));

$q = $db->sql_query("
    SELECT i.*, u1.username AS inviter_name, u1.usergroup AS inviter_usergroup, u2.username AS invitee_name, u2.usergroup AS invitee_usergroup
    FROM invites i
    LEFT JOIN users u1 ON i.inviter_id = u1.id
    LEFT JOIN users u2 ON i.invitee_id = u2.id
    WHERE {$where}
    ORDER BY i.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");

$invites = [];
while ($row = $db->fetch_array($q)) $invites[] = $row;

stdhead('Invite Manager');


define('INVITE_EXPIRE_DAYS', 14);
define('INVITE_CODE_LENGTH', 32);

function generate_invite_code(): string
{
    return bin2hex(random_bytes(INVITE_CODE_LENGTH / 2));
}

function create_invite(int $inviter_id, string $email = '', string $note = ''): array|false
{
    global $db;

    $user = get_user($inviter_id);
    if (!$user || (int)$user['invites'] <= 0) {
        return false;
    }

    $code       = generate_invite_code();
    $created_at = TIMENOW;
    $expires_at = $created_at + (INVITE_EXPIRE_DAYS * 86400);
    $ip         = $_SERVER['REMOTE_ADDR'] ?? '';

    $data = [
        'code'       => $db->escape_string($code),
        'inviter_id' => $inviter_id,
        'email'      => $db->escape_string($email),
        'status'     => 'pending',
        'created_at' => $created_at,
        'expires_at' => $expires_at,
        'ip_created' => $db->escape_string($ip),
        'note'       => $db->escape_string($note),
    ];

    $db->insert_query('invites', $data);
    $id = $db->insert_id();

    if (!$id) return false;

    $db->sql_query("UPDATE users SET invites = invites - 1 WHERE id = {$inviter_id} AND invites > 0");

    return array_merge(['id' => $id], $data);
}

function validate_invite(string $code): array|false
{
    global $db;

    if (empty($code)) return false;

    expire_old_invites();

    $code = $db->escape_string($code);
    $now  = TIMENOW;

    $q = $db->sql_query("
        SELECT i.*, u.username AS inviter_name
        FROM invites i
        LEFT JOIN users u ON i.inviter_id = u.id
        WHERE i.code = '{$code}'
          AND i.status = 'pending'
          AND (i.expires_at IS NULL OR i.expires_at > {$now})
        LIMIT 1
    ");

    return $db->num_rows($q) ? $db->fetch_array($q) : false;
}

function use_invite(string $code, int $new_user_id): bool
{
    global $db;

    $code = $db->escape_string($code);
    $now  = TIMENOW;
    $ip   = $db->escape_string($_SERVER['REMOTE_ADDR'] ?? '');

    $db->sql_query("
        UPDATE invites
        SET status='used', invitee_id={$new_user_id}, used_at={$now}, ip_used='{$ip}'
        WHERE code='{$code}' AND status='pending'
        LIMIT 1
    ");

    return $db->affected_rows() > 0;
}

function revoke_invite(int $invite_id, int $user_id): bool
{
    global $db;

    $db->sql_query("
        UPDATE invites SET status='revoked'
        WHERE id={$invite_id} AND inviter_id={$user_id} AND status='pending'
        LIMIT 1
    ");

    if ($db->affected_rows() > 0) {
        $db->sql_query("UPDATE users SET invites = invites + 1 WHERE id = {$user_id}");
        return true;
    }

    return false;
}

function expire_old_invites(): void
{
    global $db;
    $now = TIMENOW;
    $db->sql_query("
        UPDATE invites SET status='expired'
        WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < {$now}
    ");
}

function get_user_invites(int $user_id): array
{
    global $db;

    expire_old_invites();

    $q = $db->sql_query("
        SELECT i.*, u.username AS invitee_name
        FROM invites i
        LEFT JOIN users u ON i.invitee_id = u.id
        WHERE i.inviter_id = {$user_id}
        ORDER BY i.created_at DESC
    ");

    $invites = [];
    while ($row = $db->fetch_array($q)) $invites[] = $row;
    return $invites;
}

function get_invite_by_id(int $id, int $user_id = 0): array|false
{
    global $db;
    $where = $user_id > 0 ? "AND i.inviter_id = {$user_id}" : '';
    $q = $db->sql_query("
        SELECT i.*, u1.username AS inviter_name, u2.username AS invitee_name
        FROM invites i
        LEFT JOIN users u1 ON i.inviter_id = u1.id
        LEFT JOIN users u2 ON i.invitee_id = u2.id
        WHERE i.id = {$id} {$where} LIMIT 1
    ");
    return $db->num_rows($q) ? $db->fetch_array($q) : false;
}

function send_invite_email(string $to_email, string $code, string $inviter_name): bool
{
    global $BASEURL, $SITENAME;
    $invite_url = rtrim($BASEURL, '/') . '/signup.php?invite=' . $code;
    $subject    = "You've been invited to {$SITENAME}";
    $message    = "Hello!\n\n{$inviter_name} has invited you to join {$SITENAME}.\n\n"
                . "Register here:\n{$invite_url}\n\n"
                . "This invite expires in " . INVITE_EXPIRE_DAYS . " days.\n\n"
                . "— {$SITENAME} Team";
    return my_mail($to_email, $subject, $message);
}

function get_invite_stats(): array
{
    global $db;
    $q = $db->sql_query("
        SELECT COUNT(*) AS total,
               SUM(status='pending') AS pending,
               SUM(status='used')    AS used,
               SUM(status='expired') AS expired,
               SUM(status='revoked') AS revoked
        FROM invites
    ");
    return $db->fetch_array($q) ?: [];
}

function invite_status_badge(string $status): string
{
    return match($status) {
        'pending' => '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>',
        'used'    => '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Used</span>',
        'expired' => '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Expired</span>',
        'revoked' => '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Revoked</span>',
        default   => '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>',
    };
}

function get_invite_tree(int $user_id, int $depth = 0, int $max_depth = 3): array
{
    global $db;
    if ($depth >= $max_depth) return [];

    $q = $db->sql_query("
        SELECT i.*, u.username AS invitee_name, u.usergroup, u.added
        FROM invites i
        LEFT JOIN users u ON i.invitee_id = u.id
        WHERE i.inviter_id = {$user_id} AND i.status = 'used'
        ORDER BY i.used_at DESC
    ");

    $tree = [];
    while ($row = $db->fetch_array($q)) {
        $row['children'] = $row['invitee_id']
            ? get_invite_tree((int)$row['invitee_id'], $depth + 1, $max_depth)
            : [];
        $tree[] = $row;
    }
    return $tree;
}






?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    --success-gradient: linear-gradient(135deg, #198754 0%, #157347 100%);
    --warning-gradient: linear-gradient(135deg, #ffc107 0%, #ff9f00 100%);
    --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}



.bg-light {
    background: #f8f9fa !important;
}


.table {
    background: #ffffff;
    border-color: #e9ecef;
}
.table th {
    border-top: none;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    color: #495057;
    background: #f8f9fa;
}
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.03) !important;
    transform: translateY(-1px);
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}


.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.form-switch .form-check-input {
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.form-switch .form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}




/* Modern Stats Cards */
.stat-card {
    background: white;
    border-radius: 20px;
    border: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.stat-card.primary::before { background: var(--primary-gradient); }
.stat-card.warning::before { background: var(--warning-gradient); }
.stat-card.success::before { background: var(--success-gradient); }
.stat-card.secondary::before { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); }
.stat-card.danger::before { background: var(--danger-gradient); }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
}

.stat-icon.primary { background: #0d6efd20; color: #0d6efd; }
.stat-icon.warning { background: #ffc10720; color: #ffc107; }
.stat-icon.success { background: #19875420; color: #198754; }
.stat-icon.secondary { background: #6c757d20; color: #6c757d; }
.stat-icon.danger { background: #dc354520; color: #dc3545; }

/* Modern Table */
.invite-table {
    border-radius: 20px;
    overflow: hidden;
}

.invite-table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 700;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 18px 12px;
    border: none;
}

.invite-table tbody td {
    font-size: 0.9rem;
    padding: 15px 12px;
}

.invite-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.invite-table tbody tr:hover {
    background: #f8f9fa;
    transform: scale(1.01);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

/* Badges */
.badge-modern {
    padding: 2px 5px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.badge-pending { background: var(--warning-gradient); color: #fff; }
.badge-used { background: var(--success-gradient); color: #fff; }
.badge-expired { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: #fff; }
.badge-revoked { background: var(--danger-gradient); color: #fff; }

/* Code styling */
.invite-code {
    font-family: 'Monaco', 'Courier New', monospace;
    background: #f8f9fa;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 0.85rem;
    color: #0d6efd;
    border: 1px solid #dee2e6;
}

/* Buttons */
.btn-modern {
    border-radius: 12px;
    padding: 10px 20px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    border: none;
}

.btn-modern-sm {
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Filter section */
.filter-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.filter-card .form-label {
    font-size: 0.9rem;
    font-weight: 600;
}

.filter-card .form-control,
.filter-card .form-select {
    font-size: 0.9rem;
    padding: 10px 12px;
}

/* Pagination */
.pagination-modern .page-item .page-link {
    border-radius: 12px;
    margin: 0 4px;
    border: none;
    color: #0d6efd;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 8px 14px;
    transition: all 0.2s ease;
}

.pagination-modern .page-item.active .page-link {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
}

.pagination-modern .page-item .page-link:hover {
    transform: translateY(-2px);
    background: #e9ecef;
    color: #0d6efd;
}

/* Checkbox styling */
.custom-checkbox {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    cursor: pointer;
    accent-color: #0d6efd;
}

/* Card headers */
.card-header {
    font-size: 1rem;
    font-weight: 600;
}

.card-header strong {
    font-size: 1.05rem;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeInUp 0.5s ease-out;
}

/* Typography improvements */
h2 {
    font-size: 1.8rem !important;
    font-weight: 700 !important;
}

.fw-bold.fs-2 {
    font-size: 2.2rem !important;
}

.text-muted.small {
    font-size: 0.85rem !important;
}

.stat-card .text-muted {
    font-size: 0.85rem;
}

/* Table text improvements */
.invite-table .align-middle {
    font-size: 0.9rem;
}

.invite-table .small.text-muted {
    font-size: 0.85rem !important;
}

.invite-table code.text-muted {
    font-size: 0.8rem;
}
</style>

<div class="container mt-4 animate-in">

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1 fw-bold" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <i class="fas fa-ticket-alt me-2"></i>Invite Management
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.95rem;">Manage and monitor all invitation codes</p>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark p-3" style="font-size: 0.9rem;">
            <i class="fas fa-calendar-alt me-2"></i> <?= date('F j, Y') ?>
        </span>
    </div>
</div>

<!-- Stats row -->
<div class="row g-4 mb-4">
<?php
$stat_cards = [
    ['Total',   $stats['total']   ?? 0, 'fas fa-envelope',    'primary', 'Total invitations generated'],
    ['Pending', $stats['pending'] ?? 0, 'fas fa-clock',        'warning', 'Awaiting activation'],
    ['Used',    $stats['used']    ?? 0, 'fas fa-check-circle', 'success', 'Successfully used'],
    ['Expired', $stats['expired'] ?? 0, 'fas fa-times-circle', 'secondary', 'Past expiration date'],
    ['Revoked', $stats['revoked'] ?? 0, 'fas fa-ban',          'danger', 'Manually revoked'],
];
foreach ($stat_cards as [$label, $val, $icon, $color, $desc]):
?>
<div class="col-md-2 col-sm-4 col-6">
    <div class="stat-card <?= $color ?> p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="stat-icon <?= $color ?>">
                <i class="<?= $icon ?>"></i>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-2 mb-0"><?= ts_nf($val) ?></div>
                <div class="text-muted" style="font-size: 0.9rem; font-weight: 500;"><?= $label ?></div>
            </div>
        </div>
        <div class="text-muted mt-2" style="font-size: 0.8rem;">
            <i class="fas fa-info-circle me-1"></i><?= $desc ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Filters & Actions -->
<div class="filter-card mb-4 p-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-12">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted">
                        <i class="fas fa-search me-1"></i>Search
                    </label>
                    <input type="text" name="search" class="form-control form-control-lg"
                           placeholder="Username, email or invite code..."
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted">
                        <i class="fas fa-filter me-1"></i>Status
                    </label>
                    <select name="status" class="form-select form-select-lg">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','used','expired','revoked'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-modern w-100" style="background: var(--primary-gradient); color: white;">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary w-100 btn-modern">
                        <i class="fas fa-redo-alt me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="col-md-12 mt-3">
            <hr class="my-2">
            <form method="post" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold text-muted">
                        <i class="fas fa-user-plus me-1"></i>Add Invites to User
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">User ID</span>
                        <input type="number" name="user_id" class="form-control" placeholder="Enter user ID" min="1" required>
                        <span class="input-group-text">+</span>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" min="1" max="100" value="1" required>
                        <button type="submit" name="add_invites" class="btn btn-success btn-modern">
                            <i class="fas fa-plus-circle me-2"></i>Add Invites
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="alert alert-info mb-3" style="display: none; border-radius: 15px; font-size: 0.95rem;">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-check-circle me-2"></i>
            <strong><span id="selectedCount">0</span> invite(s) selected</strong>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-warning btn-modern-sm" onclick="bulkAction('revoke')">
                <i class="fas fa-ban me-1"></i>Revoke Selected
            </button>
            <button type="button" class="btn btn-danger btn-modern-sm" onclick="bulkAction('delete')">
                <i class="fas fa-trash me-1"></i>Delete Selected
            </button>
            <button type="button" class="btn btn-secondary btn-modern-sm" onclick="clearSelection()">
                <i class="fas fa-times me-1"></i>Cancel
            </button>
        </div>
    </div>
</div>

<!-- Invites Table -->
<div class="card border-0 shadow-sm" style="border-radius: 20px; overflow: hidden;">
    <div class="card-header bg-white py-3 border-0" style="border-bottom: 2px solid #f0f0f0;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-list-ul text-primary me-2"></i>
                <strong>Invitations List</strong>
                <span class="badge bg-secondary ms-2" style="font-size: 0.85rem;"><?= ts_nf($total_items) ?> total</span>
            </div>
            <div class="text-muted" style="font-size: 0.9rem;">
                <i class="fas fa-chart-line me-1"></i>Page <?= $page ?> of <?= $total_pages ?>
            </div>
        </div>
    </div>

    <?php if (empty($invites)): ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-4x mb-3 text-muted"></i>
        <h5 class="text-muted">No invites found</h5>
        <p class="text-muted" style="font-size: 0.95rem;">Try adjusting your filters or create new invites</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <form id="bulkForm" method="post">
            <input type="hidden" name="bulk_action_type" id="bulkActionType">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th width="50">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="selectAll" onclick="toggleSelectAll()" style="cursor:pointer;width:2.5em;height:1.2em;">
    </div>
</th>
                        <th>ID</th>
                        <th>Invite Code</th>
                        <th>Inviter</th>
                        <th>Invitee</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>IP Addresses</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invites as $inv): ?>
                <tr>
                    <td class="align-middle">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input invite-checkbox" type="checkbox" 
               name="invite_ids[]" value="<?= $inv['id'] ?>" 
               onchange="updateBulkBar()"
               style="cursor:pointer;width:2.5em;height:1.2em;">
    </div>
</td>
                    <td class="align-middle text-muted fw-bold">#<?= $inv['id'] ?></td>
                    <td class="align-middle">
                        <code class="invite-code"><?= htmlspecialchars($inv['code']) ?></code>
                        <button class="btn btn-link btn-sm p-0 ms-1" onclick="copyToClipboard('<?= htmlspecialchars($inv['code']) ?>')" title="Copy code">
                            <i class="fas fa-copy text-muted"></i>
                        </button>
                    </td>
                    <td class="align-middle">
                        <?php if ($inv['inviter_name']): ?>
                        
						
						<a href="<?= get_profile_link($inv['inviter_id']) ?>">
						     <i class="fas fa-user-circle me-1"></i><?= format_name($inv['inviter_name'], $inv['inviter_usergroup']) ?>
					    </a>
						
						
						
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <?php if ($inv['invitee_name']): ?>
                        
						<a href="<?= get_profile_link($inv['invitee_id']) ?>">
						     <i class="fas fa-user-circle me-1"></i><?= format_name($inv['invitee_name'], $inv['invitee_usergroup']) ?>
					    </a>
						
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <?php if ($inv['email']): ?>
                        <a href="mailto:<?= htmlspecialchars($inv['email']) ?>" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($inv['email']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <span class="badge-modern badge-<?= $inv['status'] ?>">
                            <i class="fas fa-<?= $inv['status'] === 'pending' ? 'clock' : ($inv['status'] === 'used' ? 'check' : ($inv['status'] === 'expired' ? 'times' : 'ban')) ?> me-1"></i>
                            <?= ucfirst($inv['status']) ?>
                        </span>
                    </td>
                    <td class="align-middle text-muted">
                        <div><i class="far fa-calendar-alt me-1"></i><?= date('M d, Y', (int)$inv['created_at']) ?></div>
                        <small><?= date('H:i:s', (int)$inv['created_at']) ?></small>
                    </td>
                    <td class="align-middle text-muted">
                        <?php if ($inv['expires_at']): ?>
                        <div><i class="far fa-hourglass-half me-1"></i><?= date('M d, Y', (int)$inv['expires_at']) ?></div>
                        <small><?= date('H:i:s', (int)$inv['expires_at']) ?></small>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <code class="text-muted" style="font-size: 0.75rem;"><?= $inv['ip_created'] ? htmlspecialchars($inv['ip_created']) : '—' ?></code>
                        <?php if ($inv['ip_used']): ?>
                        <br><i class="fas fa-arrow-right text-muted"></i>
                        <code class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($inv['ip_used']) ?></code>
                        <?php endif; ?>
                     </td>
                    
					
					<!-- СТАЛО: -->
<td class="align-middle">
    <div class="btn-group btn-group-sm">
        <?php if ($inv['status'] === 'pending'): ?>
        <button type="button"
                class="btn btn-outline-warning btn-modern-sm"
                title="Revoke"
                onclick="singleAction('revoke', <?= $inv['id'] ?>)">
            <i class="fas fa-ban"></i>
        </button>
        <?php endif; ?>
        <button type="button"
                class="btn btn-outline-danger btn-modern-sm"
                title="Delete"
                onclick="singleAction('delete', <?= $inv['id'] ?>)">
            <i class="fas fa-trash-alt"></i>
        </button>
    </div>
</td>
					 
					 
					 
                </tr>
                <?php endforeach; ?>
                </tbody>
             </table>
        </form>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white py-3 border-0">
        <nav class="d-flex justify-content-center">
            <ul class="pagination pagination-modern mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

</div>

<script>
// Select/Deselect all checkboxes
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBar();
}

// Update bulk actions bar visibility
function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    const count = checkboxes.length;
    const bar = document.getElementById('bulkActionsBar');
    
    if (count > 0) {
        bar.style.display = 'block';
        document.getElementById('selectedCount').innerText = count;
    } else {
        bar.style.display = 'none';
    }
}

// Clear all selections
function clearSelection() {
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    if (document.getElementById('selectAll')) {
        document.getElementById('selectAll').checked = false;
    }
    updateBulkBar();
}

// Bulk action
function bulkAction(actionType) {
    const checkboxes = document.querySelectorAll('.invite-checkbox:checked');
    if (checkboxes.length === 0) {
        showNotification('No invites selected', 'warning');
        return;
    }
    
    const actionText = actionType === 'revoke' ? 'revoke' : 'delete';
    if (confirm(`Are you sure you want to ${actionText} ${checkboxes.length} invite(s)? This action cannot be undone.`)) {
        document.getElementById('bulkActionType').value = actionType;
        document.getElementById('bulkForm').submit();
    }
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Invite code copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy code', 'error');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.style.borderRadius = '12px';
    notification.style.fontSize = '0.95rem';
    notification.style.fontWeight = '500';
    notification.style.animation = 'fadeInUp 0.3s ease-out';
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBulkBar();
});





function singleAction(type, id) {
    const msg = type === 'revoke'
        ? 'Revoke this invite?'
        : 'Delete this invite permanently?';

    if (!confirm(msg)) return;

    const form = document.getElementById('bulkForm');

    // Убираем старые hidden inputs если есть
    ['singleFlag', 'singleId'].forEach(eid => {
        const el = document.getElementById(eid);
        if (el) el.remove();
    });

    // Снимаем все switches
    document.querySelectorAll('.invite-checkbox').forEach(cb => cb.checked = false);

    // Добавляем нужные поля
    const flag = document.createElement('input');
    flag.type = 'hidden';
    flag.id   = 'singleFlag';
    flag.name = type === 'revoke' ? 'admin_revoke' : 'admin_delete';
    flag.value = '1';
    form.appendChild(flag);

    const invId = document.createElement('input');
    invId.type  = 'hidden';
    invId.id    = 'singleId';
    invId.name  = 'invite_id';
    invId.value = id;
    form.appendChild(invId);

    form.submit();
}




</script>

<?php stdfoot(); ?>