<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization is not allowed.</div>');
}

$lang->load('manage_invites');

require_once INC_PATH . '/functions_multipage.php';

// {1}, {2}… placeholder substitution (also handles %1$s — $lang->load() converts {N} into it)
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

$action = $_GET['action'] ?? 'list';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

// ── POST: Admin actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        http_response_code(403);
        die(htmlspecialchars($lang->manage_invites['err_invalid_token']));
    }

    if (isset($_POST['admin_revoke'])) {
        $invite_id = (int)$_POST['invite_id'];
        $inv = get_invite_by_id($invite_id);
        if ($inv && $inv['status'] === 'pending') {
            $db->sql_query_prepared("UPDATE invites SET status='revoked' WHERE id=?", [$invite_id]);
        }
    }

    if (isset($_POST['admin_delete'])) {
        $invite_id = (int)$_POST['invite_id'];
        $db->sql_query_prepared("DELETE FROM invites WHERE id=?", [$invite_id]);
    }

    if (isset($_POST['add_invites'])) {
        $user_id = (int)$_POST['user_id'];
        $amount  = max(1, min(100, (int)$_POST['amount']));
        $db->sql_query_prepared("UPDATE users SET invites = invites + ? WHERE id=?", [$amount, $user_id]);
    }

    if (!empty($_POST['bulk_action_type'])) {
        $action_type = $_POST['bulk_action_type'];
        $invite_ids  = $_POST['invite_ids'] ?? [];
        if (!empty($invite_ids)) {
            $ids = array_map('intval', $invite_ids);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            if ($action_type === 'revoke') {
                $db->sql_query_prepared("UPDATE invites SET status='revoked' WHERE id IN ({$ph}) AND status='pending'", $ids);
            } elseif ($action_type === 'delete') {
                $db->sql_query_prepared("DELETE FROM invites WHERE id IN ({$ph})", $ids);
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
$where_params = [];
if ($filter_status) {
    $where .= " AND i.status=?";
    $where_params[] = $filter_status;
}
if ($filter_search) {
    $like = "%{$filter_search}%";
    $where .= " AND (i.code LIKE ? OR u1.username LIKE ? OR u2.username LIKE ? OR i.email LIKE ?)";
    array_push($where_params, $like, $like, $like, $like);
}

$total_q = $db->sql_query_prepared("
    SELECT COUNT(*) AS cnt FROM invites i
    LEFT JOIN users u1 ON i.inviter_id = u1.id
    LEFT JOIN users u2 ON i.invitee_id = u2.id
    WHERE {$where}
", $where_params);
$total_row   = $total_q ? $db->fetch_array($total_q) : null;
$total_items = (int)($total_row['cnt'] ?? 0);
$total_pages = max(1, (int)ceil($total_items / $limit));

$q = $db->sql_query_prepared("
    SELECT i.*, u1.username AS inviter_name, u1.usergroup AS inviter_usergroup, u2.username AS invitee_name, u2.usergroup AS invitee_usergroup
    FROM invites i
    LEFT JOIN users u1 ON i.inviter_id = u1.id
    LEFT JOIN users u2 ON i.invitee_id = u2.id
    WHERE {$where}
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
", [...$where_params, $limit, $offset]);

$invites = [];
while ($q && ($row = $db->fetch_array($q))) $invites[] = $row;

stdhead($lang->manage_invites['page_title']);
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">' . "\n"
   . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>' . "\n";


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
        'code'       => $code,
        'inviter_id' => $inviter_id,
        'email'      => $email,
        'status'     => 'pending',
        'created_at' => $created_at,
        'expires_at' => $expires_at,
        'ip_created' => $ip,
        'note'       => $note,
    ];

    $columns      = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO invites (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
        array_values($data)
    );
    $id = $db->insert_id();

    if (!$id) return false;

    $db->sql_query_prepared("UPDATE users SET invites = invites - 1 WHERE id = ? AND invites > 0", [$inviter_id]);

    return array_merge(['id' => $id], $data);
}

function validate_invite(string $code): array|false
{
    global $db;

    if (empty($code)) return false;

    expire_old_invites();

    $now  = TIMENOW;

    $q = $db->sql_query_prepared("
        SELECT i.*, u.username AS inviter_name
        FROM invites i
        LEFT JOIN users u ON i.inviter_id = u.id
        WHERE i.code = ?
          AND i.status = 'pending'
          AND (i.expires_at IS NULL OR i.expires_at > ?)
        LIMIT 1
    ", [$code, $now]);

    return ($q && $db->num_rows($q)) ? $db->fetch_array($q) : false;
}

function use_invite(string $code, int $new_user_id): bool
{
    global $db;

    $now  = TIMENOW;
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';

    $db->sql_query_prepared("
        UPDATE invites
        SET status='used', invitee_id=?, used_at=?, ip_used=?
        WHERE code=? AND status='pending'
        LIMIT 1
    ", [$new_user_id, $now, $ip, $code]);

    return $db->affected_rows() > 0;
}

function revoke_invite(int $invite_id, int $user_id): bool
{
    global $db;

    $db->sql_query_prepared("
        UPDATE invites SET status='revoked'
        WHERE id=? AND inviter_id=? AND status='pending'
        LIMIT 1
    ", [$invite_id, $user_id]);

    if ($db->affected_rows() > 0) {
        $db->sql_query_prepared("UPDATE users SET invites = invites + 1 WHERE id = ?", [$user_id]);
        return true;
    }

    return false;
}

function expire_old_invites(): void
{
    global $db;
    $now = TIMENOW;
    $db->sql_query_prepared("
        UPDATE invites SET status='expired'
        WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < ?
    ", [$now]);
}

function get_user_invites(int $user_id): array
{
    global $db;

    expire_old_invites();

    $q = $db->sql_query_prepared("
        SELECT i.*, u.username AS invitee_name
        FROM invites i
        LEFT JOIN users u ON i.invitee_id = u.id
        WHERE i.inviter_id = ?
        ORDER BY i.created_at DESC
    ", [$user_id]);

    $invites = [];
    while ($q && ($row = $db->fetch_array($q))) $invites[] = $row;
    return $invites;
}

function get_invite_by_id(int $id, int $user_id = 0): array|false
{
    global $db;
    $params = [$id];
    $where = '';
    if ($user_id > 0) {
        $where = "AND i.inviter_id = ?";
        $params[] = $user_id;
    }
    $q = $db->sql_query_prepared("
        SELECT i.*, u1.username AS inviter_name, u2.username AS invitee_name
        FROM invites i
        LEFT JOIN users u1 ON i.inviter_id = u1.id
        LEFT JOIN users u2 ON i.invitee_id = u2.id
        WHERE i.id = ? {$where} LIMIT 1
    ", $params);
    return ($q && $db->num_rows($q)) ? $db->fetch_array($q) : false;
}

function send_invite_email(string $to_email, string $code, string $inviter_name): bool
{
    global $BASEURL, $SITENAME, $lang;
    $invite_url = rtrim($BASEURL, '/') . '/signup.php?invite=' . $code;
    $subject    = ags_fmt($lang->manage_invites['mail_subject'], (string)$SITENAME);
    $message    = ags_fmt(
        $lang->manage_invites['mail_body'],
        $inviter_name,
        (string)$SITENAME,
        $invite_url,
        INVITE_EXPIRE_DAYS
    );
    return my_mail($to_email, $subject, $message);
}

function get_invite_stats(): array
{
    global $db;
    $q = $db->sql_query_prepared("
        SELECT COUNT(*) AS total,
               SUM(status='pending') AS pending,
               SUM(status='used')    AS used,
               SUM(status='expired') AS expired,
               SUM(status='revoked') AS revoked
        FROM invites
    ");
    return ($q ? $db->fetch_array($q) : null) ?: [];
}

// Translated status name (plain text — escape on output)
function invite_status_label(string $status): string
{
    global $lang;
    return match($status) {
        'pending' => $lang->manage_invites['status_pending'],
        'used'    => $lang->manage_invites['status_used'],
        'expired' => $lang->manage_invites['status_expired'],
        'revoked' => $lang->manage_invites['status_revoked'],
        default   => ucfirst($status),
    };
}

function invite_status_badge(string $status): string
{
    $label = htmlspecialchars(invite_status_label($status));
    return match($status) {
        'pending' => '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>' . $label . '</span>',
        'used'    => '<span class="badge bg-success"><i class="fas fa-check me-1"></i>' . $label . '</span>',
        'expired' => '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>' . $label . '</span>',
        'revoked' => '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>' . $label . '</span>',
        default   => '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>',
    };
}

function get_invite_tree(int $user_id, int $depth = 0, int $max_depth = 3): array
{
    global $db;
    if ($depth >= $max_depth) return [];

    $q = $db->sql_query_prepared("
        SELECT i.*, u.username AS invitee_name, u.usergroup, u.added
        FROM invites i
        LEFT JOIN users u ON i.invitee_id = u.id
        WHERE i.inviter_id = ? AND i.status = 'used'
        ORDER BY i.used_at DESC
    ", [$user_id]);

    $tree = [];
    while ($q && ($row = $db->fetch_array($q))) {
        $row['children'] = $row['invitee_id']
            ? get_invite_tree((int)$row['invitee_id'], $depth + 1, $max_depth)
            : [];
        $tree[] = $row;
    }
    return $tree;
}

// Strings for manage_invites.js: js_* keys without the prefix
$ags_js_lang = [];
foreach ($lang->manage_invites as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $ags_js_lang[substr((string)$k, 3)] = $v;
    }
}

?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/manage_invites.css?ver=1">

<div class="container mt-4 animate-in inv-page">

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1 fw-bold" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            <i class="fas fa-ticket-alt me-2"></i><?= htmlspecialchars($lang->manage_invites['pane_title']) ?>
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.95rem;"><?= htmlspecialchars($lang->manage_invites['pane_subtitle']) ?></p>
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
    [$lang->manage_invites['stat_total'],   $stats['total']   ?? 0, 'fas fa-envelope',     'primary',   $lang->manage_invites['hint_stat_total']],
    [$lang->manage_invites['stat_pending'], $stats['pending'] ?? 0, 'fas fa-clock',        'warning',   $lang->manage_invites['hint_stat_pending']],
    [$lang->manage_invites['stat_used'],    $stats['used']    ?? 0, 'fas fa-check-circle', 'success',   $lang->manage_invites['hint_stat_used']],
    [$lang->manage_invites['stat_expired'], $stats['expired'] ?? 0, 'fas fa-times-circle', 'secondary', $lang->manage_invites['hint_stat_expired']],
    [$lang->manage_invites['stat_revoked'], $stats['revoked'] ?? 0, 'fas fa-ban',          'danger',    $lang->manage_invites['hint_stat_revoked']],
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
                <div class="text-muted" style="font-size: 0.9rem; font-weight: 500;"><?= htmlspecialchars($label) ?></div>
            </div>
        </div>
        <div class="text-muted mt-2" style="font-size: 0.8rem;">
            <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($desc) ?>
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
                        <i class="fas fa-search me-1"></i><?= htmlspecialchars($lang->manage_invites['lbl_search']) ?>
                    </label>
                    <input type="text" name="search" class="form-control form-control-lg"
                           placeholder="<?= htmlspecialchars($lang->manage_invites['ph_search']) ?>"
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-muted">
                        <i class="fas fa-filter me-1"></i><?= htmlspecialchars($lang->manage_invites['lbl_status']) ?>
                    </label>
                    <select name="status" class="form-select form-select-lg">
                        <option value=""><?= htmlspecialchars($lang->manage_invites['opt_all_statuses']) ?></option>
                        <?php foreach (['pending','used','expired','revoked'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars(invite_status_label($s)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-modern w-100" style="background: var(--primary-gradient); color: white;">
                        <i class="fas fa-search me-2"></i><?= htmlspecialchars($lang->manage_invites['btn_apply_filters']) ?>
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary w-100 btn-modern">
                        <i class="fas fa-redo-alt me-2"></i><?= htmlspecialchars($lang->manage_invites['btn_reset']) ?>
                    </a>
                </div>
            </form>
        </div>

        <div class="col-md-12 mt-3">
            <hr class="my-2">
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                <div class="col-md-5">
                    <label class="form-label fw-semibold text-muted">
                        <i class="fas fa-user-plus me-1"></i><?= htmlspecialchars($lang->manage_invites['lbl_add_invites']) ?>
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><?= htmlspecialchars($lang->manage_invites['lbl_user_id']) ?></span>
                        <input type="number" name="user_id" class="form-control" placeholder="<?= htmlspecialchars($lang->manage_invites['ph_user_id']) ?>" min="1" required>
                        <span class="input-group-text">+</span>
                        <input type="number" name="amount" class="form-control" placeholder="<?= htmlspecialchars($lang->manage_invites['ph_amount']) ?>" min="1" max="100" value="1" required>
                        <button type="submit" name="add_invites" class="btn btn-success btn-modern">
                            <i class="fas fa-plus-circle me-2"></i><?= htmlspecialchars($lang->manage_invites['btn_add_invites']) ?>
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
            <strong><?= ags_fmt(htmlspecialchars($lang->manage_invites['lbl_selected_count']), '<span id="selectedCount">0</span>') ?></strong>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-warning btn-modern-sm" onclick="bulkAction('revoke')">
                <i class="fas fa-ban me-1"></i><?= htmlspecialchars($lang->manage_invites['btn_revoke_selected']) ?>
            </button>
            <button type="button" class="btn btn-danger btn-modern-sm" onclick="bulkAction('delete')">
                <i class="fas fa-trash me-1"></i><?= htmlspecialchars($lang->manage_invites['btn_delete_selected']) ?>
            </button>
            <button type="button" class="btn btn-secondary btn-modern-sm" onclick="clearSelection()">
                <i class="fas fa-times me-1"></i><?= htmlspecialchars($lang->manage_invites['btn_cancel']) ?>
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
                <strong><?= htmlspecialchars($lang->manage_invites['sec_list']) ?></strong>
                <span class="badge bg-secondary ms-2" style="font-size: 0.85rem;"><?= htmlspecialchars(ags_fmt($lang->manage_invites['lbl_total_badge'], ts_nf($total_items))) ?></span>
            </div>
            <div class="text-muted" style="font-size: 0.9rem;">
                <i class="fas fa-chart-line me-1"></i><?= htmlspecialchars(ags_fmt($lang->manage_invites['lbl_page_of'], $page, $total_pages)) ?>
            </div>
        </div>
    </div>

    <?php if (empty($invites)): ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-4x mb-3 text-muted"></i>
        <h5 class="text-muted"><?= htmlspecialchars($lang->manage_invites['empty_title']) ?></h5>
        <p class="text-muted" style="font-size: 0.95rem;"><?= htmlspecialchars($lang->manage_invites['empty_hint']) ?></p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <form id="bulkForm" method="post">
            <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
            <input type="hidden" name="bulk_action_type" id="bulkActionType">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th width="50">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="selectAll" onclick="toggleSelectAll()" style="cursor:pointer;width:2.5em;height:1.2em;">
    </div>
</th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_id']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_code']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_inviter']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_invitee']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_email']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_status']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_created']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_expires']) ?></th>
                        <th><?= htmlspecialchars($lang->manage_invites['col_ip']) ?></th>
                        <th width="100"><?= htmlspecialchars($lang->manage_invites['col_actions']) ?></th>
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
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="copyToClipboard('<?= htmlspecialchars($inv['code']) ?>')" title="<?= htmlspecialchars($lang->manage_invites['tip_copy_code']) ?>">
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
                            <?= htmlspecialchars(invite_status_label((string)$inv['status'])) ?>
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

<td class="align-middle">
    <div class="btn-group btn-group-sm">
        <?php if ($inv['status'] === 'pending'): ?>
        <button type="button"
                class="btn btn-outline-warning btn-modern-sm"
                title="<?= htmlspecialchars($lang->manage_invites['tip_revoke']) ?>"
                onclick="singleAction('revoke', <?= $inv['id'] ?>)">
            <i class="fas fa-ban"></i>
        </button>
        <?php endif; ?>
        <button type="button"
                class="btn btn-outline-danger btn-modern-sm"
                title="<?= htmlspecialchars($lang->manage_invites['tip_delete']) ?>"
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
        <?php
        $mp_url = $_this_script_ . '&status=' . urlencode($filter_status) . '&search=' . urlencode($filter_search);
        echo multipage($total_items, $limit, $page, $mp_url);
        ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

</div>

<script>const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_invites.js?ver=2"></script>

<?php stdfoot(); ?>
