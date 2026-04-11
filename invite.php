<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'invite.php');

$templatelist = 'invite';
require_once './global.php';






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
    $invite_url = rtrim($BASEURL, '/') . '/member.php?action=register&invite=' . $code;
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

























if (!$CURUSER) {
    redirect('member.php?action=login');
}

$uid    = (int)$CURUSER['id'];
$action = $_GET['action'] ?? '';

$success = match($_GET['msg'] ?? '') {
    'created' => 'Invite created successfully!',
    'revoked' => 'Invite revoked. Your invite has been returned.',
    default   => '',
};

// ── POST: Create invite ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
    verify_post_check($_POST['my_post_key'] ?? '');

    $email = trim($_POST['email'] ?? '');
    $note  = trim($_POST['note']  ?? '');

    if ($email && !validate_email_format($email)) {
        $error = 'Invalid email address.';
    } elseif ((int)$CURUSER['invites'] <= 0) {
        $error = 'You have no invites left.';
    } else {
        $invite = create_invite($uid, $email, $note);
        if ($invite) {
            if ($email) {
                send_invite_email($email, $invite['code'], $CURUSER['username']);
            }
            header('Location: invite.php?msg=created'); exit;
        } else {
            $error = 'Failed to create invite. You may have no invites remaining.';
        }
    }
}

// ── POST: Revoke invite ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_invite'])) {
    verify_post_check($_POST['my_post_key'] ?? '');
    $invite_id = (int)($_POST['invite_id'] ?? 0);
    if (revoke_invite($invite_id, $uid)) {
        header('Location: invite.php?msg=revoked'); exit;
    } else {
        $error = 'Failed to revoke invite.';
    }
}

// ── Load invites ──────────────────────────────────────────────────────────────
$invites = get_user_invites($uid);

$stats = [
    'total'   => count($invites),
    'pending' => count(array_filter($invites, fn($i) => $i['status'] === 'pending')),
    'used'    => count(array_filter($invites, fn($i) => $i['status'] === 'used')),
];

stdhead('My Invites');
?>
<style>
.invite-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    transition: box-shadow .2s;
}
.invite-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }

.invite-code {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    color: #334155;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.invite-code:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}

.stat-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}
.stat-box .num {
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
}
.stat-box .lbl {
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.invite-link {
    font-size: 12px;
    word-break: break-all;
    color: #64748b;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}
.empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
</style>

<div class="container mt-2">

<?php if (!empty($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($error) ?>,'error'));</script>
<?php endif; ?>
<?php if (!empty($success)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($success) ?>,'success'));</script>
<?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-envelope-open-text me-2 text-primary"></i>My Invites</h2>
            <p class="text-muted mb-0">Invite your friends to join <?= htmlspecialchars($SITENAME) ?></p>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-4 text-primary"><?= (int)$CURUSER['invites'] ?></div>
            <div class="text-muted small">invites left</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-dark"><?= $stats['total'] ?></div>
                <div class="lbl">Total Sent</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-warning"><?= $stats['pending'] ?></div>
                <div class="lbl">Pending</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-success"><?= $stats['used'] ?></div>
                <div class="lbl">Accepted</div>
            </div>
        </div>
    </div>

    <!-- Create invite form -->
    <?php if ((int)$CURUSER['invites'] > 0): ?>
    <div class="invite-card p-4 mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle me-2 text-success"></i>Create New Invite</h5>
        <form method="post">
            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email (optional)</label>
                    <input type="email" class="form-control" name="email" placeholder="friend@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="form-text">If provided, invite will be sent via email.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Note (optional)</label>
                    <input type="text" class="form-control" name="note" placeholder="e.g. for John from work"
                           maxlength="255" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" name="create_invite" class="btn btn-primary px-4">
                        <i class="fas fa-paper-plane me-2"></i>Generate Invite
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        You have no invites left. Invites can be earned through activity or purchased with bonus points.
    </div>
    <?php endif; ?>

    <!-- Invite list -->
    <div class="invite-card">
        <div class="p-3 border-bottom fw-semibold">
            <i class="fas fa-list me-2"></i>Invite History
        </div>

        <?php if (empty($invites)): ?>
        <div class="empty-state">
            <i class="fas fa-envelope-open"></i>
            <p class="mb-0">No invites yet. Create your first invite above!</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Email</th>
                    <th>Note</th>
                    <th>Invited User</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invites as $inv): ?>
            <tr>
                <td>
                    <?php if ($inv['status'] === 'pending'): ?>
                    <span class="invite-code" onclick="copyInvite('<?= $BASEURL ?>/signup.php?invite=<?= $inv['code'] ?>', this)"
                          title="Click to copy invite link">
                        <?= substr($inv['code'], 0, 12) ?>...
                        <i class="fas fa-copy fa-xs"></i>
                    </span>
                    <?php else: ?>
                    <span class="text-muted small"><?= substr($inv['code'], 0, 12) ?>...</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?= $inv['email'] ? htmlspecialchars($inv['email']) : '—' ?></td>
                <td class="small text-muted"><?= $inv['note'] ? htmlspecialchars($inv['note']) : '—' ?></td>
                <td>
                    <?php if ($inv['invitee_name']): ?>
                    <a href="userdetails.php?id=<?= $inv['invitee_id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($inv['invitee_name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= invite_status_badge($inv['status']) ?></td>
                <td class="small text-muted">
                    <?= $inv['expires_at'] ? date('M j, Y', (int)$inv['expires_at']) : '—' ?>
                </td>
                <td class="small text-muted"><?= date('M j, Y', (int)$inv['created_at']) ?></td>
                <td>
                    <?php if ($inv['status'] === 'pending'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Revoke this invite?')">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <input type="hidden" name="invite_id" value="<?= $inv['id'] ?>">
                        <button type="submit" name="revoke_invite" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-ban"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

</div>



<div id="toast-container"></div>


<script src="<?= $BASEURL; ?>/scripts/toast.js"></script>

<script>
function copyInvite(url, el) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = el.innerHTML;
        el.innerHTML = '<i class="fas fa-check fa-xs"></i> Copied!';
        el.style.color = '#16a34a';
        showToast('Invite link copied to clipboard!', 'success');
        setTimeout(() => { el.innerHTML = orig; el.style.color = ''; }, 2000);
    });
}
</script>

<?php stdfoot(); ?>
