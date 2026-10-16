<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'invite.php');

$templatelist = 'invite';
require_once './global.php';
require_once INC_PATH . '/functions_multipage.php';

$lang->load('invites');

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… — $lang->load() converts {n} into those)
     */
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

// Lang: $L raw (toasts, e-mail, JS), $E escaped for HTML (lang values are plain text)
$L = $lang->invites;
$E = array_map(static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $L);






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
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO invites (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($data)
    );
    $id = $db->insert_id();

    if (!$id) return false;

    $db->sql_query_prepared(
        "UPDATE users SET invites = invites - 1 WHERE id = ? AND invites > 0",
        [$inviter_id]
    );

    return array_merge(['id' => $id], $data);
}

function validate_invite(string $code): array|false
{
    global $db;

    if (empty($code)) return false;

    expire_old_invites();

    $now  = TIMENOW;

    $q = $db->sql_query_prepared(
        "SELECT i.*, u.username AS inviter_name
         FROM invites i
         LEFT JOIN users u ON i.inviter_id = u.id
         WHERE i.code = ?
           AND i.status = 'pending'
           AND (i.expires_at IS NULL OR i.expires_at > ?)
         LIMIT 1",
        [$code, $now]
    );

    return $db->num_rows($q) ? $db->fetch_array($q) : false;
}

function use_invite(string $code, int $new_user_id): bool
{
    global $db;

    $now = TIMENOW;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';

    $db->sql_query_prepared(
        "UPDATE invites
         SET status='used', invitee_id = ?, used_at = ?, ip_used = ?
         WHERE code = ? AND status='pending'
         LIMIT 1",
        [$new_user_id, $now, $ip, $code]
    );

    return $db->affected_rows() > 0;
}

function revoke_invite(int $invite_id, int $user_id): bool
{
    global $db;

    $db->sql_query_prepared(
        "UPDATE invites SET status='revoked'
         WHERE id = ? AND inviter_id = ? AND status='pending'
         LIMIT 1",
        [$invite_id, $user_id]
    );

    if ($db->affected_rows() > 0) {
        $db->sql_query_prepared(
            "UPDATE users SET invites = invites + 1 WHERE id = ?",
            [$user_id]
        );
        return true;
    }

    return false;
}

function expire_old_invites(): void
{
    global $db;
    $now = TIMENOW;
    $db->sql_query_prepared(
        "UPDATE invites SET status='expired'
         WHERE status='pending' AND expires_at IS NOT NULL AND expires_at < ?",
        [$now]
    );
}

/**
 * One page of the user's invites ($limit = 0 → all of them)
 */
function get_user_invites(int $user_id, int $limit = 0, int $offset = 0): array
{
    global $db;

    $sql = "SELECT i.*, u.username AS invitee_name
            FROM invites i
            LEFT JOIN users u ON i.invitee_id = u.id
            WHERE i.inviter_id = ?
            ORDER BY i.created_at DESC";
    $params = [$user_id];

    if ($limit > 0) {
        $sql     .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = max(0, $offset);
    }

    $q = $db->sql_query_prepared($sql, $params);

    $invites = [];
    while ($row = $db->fetch_array($q)) $invites[] = $row;
    return $invites;
}

/**
 * Totals for the KPI tiles, over ALL of the user's invites (not just one page)
 */
function get_user_invite_counts(int $user_id): array
{
    global $db;

    expire_old_invites();

    $q = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(status = 'used'), 0)    AS used
         FROM invites
         WHERE inviter_id = ?",
        [$user_id]
    );
    $row = $q ? $db->fetch_array($q) : null;

    return [
        'total'   => (int)($row['total']   ?? 0),
        'pending' => (int)($row['pending'] ?? 0),
        'used'    => (int)($row['used']    ?? 0),
    ];
}

function get_invite_by_id(int $id, int $user_id = 0): array|false
{
    global $db;

    $sql = "SELECT i.*, u1.username AS inviter_name, u2.username AS invitee_name
            FROM invites i
            LEFT JOIN users u1 ON i.inviter_id = u1.id
            LEFT JOIN users u2 ON i.invitee_id = u2.id
            WHERE i.id = ?";
    $params = [$id];

    if ($user_id > 0) {
        $sql .= " AND i.inviter_id = ?";
        $params[] = $user_id;
    }
    $sql .= " LIMIT 1";

    $q = $db->sql_query_prepared($sql, $params);
    return $db->num_rows($q) ? $db->fetch_array($q) : false;
}

function send_invite_email(string $to_email, string $code, string $inviter_name): bool
{
    global $BASEURL, $SITENAME, $lang;
    $invite_url = rtrim($BASEURL, '/') . '/member.php?action=register&invite=' . $code;
    $subject    = ags_fmt($lang->invites['email_subject'], (string)$SITENAME);
    $message    = ags_fmt($lang->invites['email_body'], $inviter_name, (string)$SITENAME, $invite_url, INVITE_EXPIRE_DAYS);
    return my_mail($to_email, $subject, $message);
}












function get_invite_stats(): array
{
    global $db;
    $q = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total,
                SUM(status='pending') AS pending,
                SUM(status='used')    AS used,
                SUM(status='expired') AS expired,
                SUM(status='revoked') AS revoked
         FROM invites"
    );
    return $db->fetch_array($q) ?: [];
}

function invite_status_badge(string $status): string
{
    global $lang;
    $b = static fn(string $k): string => htmlspecialchars((string)$lang->invites[$k], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return match($status) {
        'pending' => '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>' . $b('opt_status_pending') . '</span>',
        'used'    => '<span class="badge bg-success"><i class="fas fa-check me-1"></i>' . $b('opt_status_used') . '</span>',
        'expired' => '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>' . $b('opt_status_expired') . '</span>',
        'revoked' => '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>' . $b('opt_status_revoked') . '</span>',
        default   => '<span class="badge bg-light text-dark">' . htmlspecialchars($status) . '</span>',
    };
}

function get_invite_tree(int $user_id, int $depth = 0, int $max_depth = 3): array
{
    global $db;
    if ($depth >= $max_depth) return [];

    $q = $db->sql_query_prepared(
        "SELECT i.*, u.username AS invitee_name, u.usergroup, u.added
         FROM invites i
         LEFT JOIN users u ON i.invitee_id = u.id
         WHERE i.inviter_id = ? AND i.status = 'used'
         ORDER BY i.used_at DESC",
        [$user_id]
    );

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
    'created' => $L['flash_created'],
    'revoked' => $L['flash_revoked'],
    default   => '',
};

// ── POST: Create invite ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
    verify_post_check($_POST['my_post_key'] ?? '');

    $email = trim($_POST['email'] ?? '');
    $note  = trim($_POST['note']  ?? '');

    if ($email && !validate_email_format($email)) {
        $error = $L['err_email'];
    } elseif ((int)$CURUSER['invites'] <= 0) {
        $error = $L['err_no_invites'];
    } else {
        $invite = create_invite($uid, $email, $note);
        if ($invite) {
            if ($email) {
                send_invite_email($email, $invite['code'], $CURUSER['username']);
            }
            header('Location: invite.php?msg=created'); exit;
        } else {
            $error = $L['err_create_failed'];
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
        $error = $L['err_revoke_failed'];
    }
}

// ── Load invites (one page) ───────────────────────────────────────────────────
$stats = get_user_invite_counts($uid);

// Rows per page: the site-wide pager setting (Settings → Forum/Legacy → ts_perpage)
$perpage = max(1, (int)($ts_perpage ?? 20));
$pages   = max(1, (int)ceil($stats['total'] / $perpage));
// get_input() merges GET and POST: the pager links send ?page=N, while its
// "Jump to page" form POSTs the number to invite.php?page=1 (POST wins)
$page    = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $pages);

$invites   = get_user_invites($uid, $perpage, ($page - 1) * $perpage);
$multipage = multipage($stats['total'], $perpage, $page, 'invite.php?page={page}');

stdhead($L['page_title']);
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
            <h2 class="fw-bold mb-1"><i class="fas fa-envelope-open-text me-2 text-primary"></i><?= $E['page_title'] ?></h2>
            <p class="text-muted mb-0"><?= ags_fmt($E['pane_subtitle'], htmlspecialchars((string)$SITENAME)) ?></p>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-4 text-primary"><?= (int)$CURUSER['invites'] ?></div>
            <div class="text-muted small"><?= $E['lbl_invites_left'] ?></div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-dark"><?= $stats['total'] ?></div>
                <div class="lbl"><?= $E['lbl_kpi_total'] ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-warning"><?= $stats['pending'] ?></div>
                <div class="lbl"><?= $E['lbl_kpi_pending'] ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box">
                <div class="num text-success"><?= $stats['used'] ?></div>
                <div class="lbl"><?= $E['lbl_kpi_used'] ?></div>
            </div>
        </div>
    </div>

    <!-- Create invite form -->
    <?php if ((int)$CURUSER['invites'] > 0): ?>
    <div class="invite-card p-4 mb-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle me-2 text-success"></i><?= $E['sec_create'] ?></h5>
        <form method="post">
            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><?= $E['lbl_email'] ?></label>
                    <input type="email" class="form-control" name="email" placeholder="<?= $E['ph_email'] ?>"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div class="form-text"><?= $E['hint_email'] ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><?= $E['lbl_note'] ?></label>
                    <input type="text" class="form-control" name="note" placeholder="<?= $E['ph_note'] ?>"
                           maxlength="255" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" name="create_invite" class="btn btn-primary px-4">
                        <i class="fas fa-paper-plane me-2"></i><?= $E['btn_generate'] ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= $E['hint_no_invites'] ?>
    </div>
    <?php endif; ?>

    <!-- Invite list -->
    <div class="invite-card">
        <div class="p-3 border-bottom fw-semibold">
            <i class="fas fa-list me-2"></i><?= $E['sec_history'] ?>
        </div>

        <?php if (empty($invites)): ?>
        <div class="empty-state">
            <i class="fas fa-envelope-open"></i>
            <p class="mb-0"><?= $E['hint_empty'] ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= $E['lbl_col_code'] ?></th>
                    <th><?= $E['lbl_col_email'] ?></th>
                    <th><?= $E['lbl_col_note'] ?></th>
                    <th><?= $E['lbl_col_invitee'] ?></th>
                    <th><?= $E['lbl_col_status'] ?></th>
                    <th><?= $E['lbl_col_expires'] ?></th>
                    <th><?= $E['lbl_col_created'] ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invites as $inv): ?>
            <tr>
                <td>
                    <?php if ($inv['status'] === 'pending'): ?>
                    <span class="invite-code" onclick="copyInvite('<?= $BASEURL ?>/signup.php?invite=<?= $inv['code'] ?>', this)"
                          title="<?= $E['tip_copy'] ?>">
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
                    <a href="<?= get_profile_link($inv['invitee_id']) ?>" class="text-decoration-none">
                        <?= htmlspecialchars($inv['invitee_name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= invite_status_badge($inv['status']) ?></td>
                <td class="small text-muted">
                    <?= $inv['expires_at'] ? date($L['fmt_date'], (int)$inv['expires_at']) : '—' ?>
                </td>
                <td class="small text-muted"><?= date($L['fmt_date'], (int)$inv['created_at']) ?></td>
                <td>
                    <?php if ($inv['status'] === 'pending'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($L['js_revoke_confirm'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>)">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <input type="hidden" name="invite_id" value="<?= $inv['id'] ?>">
                        <button type="submit" name="revoke_invite" class="btn btn-sm btn-outline-danger" title="<?= $E['tip_revoke'] ?>" aria-label="<?= $E['tip_revoke'] ?>">
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
        <?php if ($multipage !== ''): ?>
        <div class="p-3 border-top">
            <?= $multipage ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>



<div id="toast-container"></div>


<script src="<?= $BASEURL; ?>/scripts/toast.js"></script>

<?php
$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$v;
    }
}
?>
<script>
const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

/* i18n: t(key, fallback, ...args) — substitutes {1} and %1$s; result is plain text */
function t(key, fallback, ...args) {
    let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((a, i) => { const n = i + 1; s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a)); });
    return s;
}

function copyInvite(url, el) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = el.innerHTML;
        el.innerHTML = '<i class="fas fa-check fa-xs"></i> ';
        el.appendChild(document.createTextNode(t('copied', 'Copied!')));
        el.style.color = '#16a34a';
        showToast(t('copied_toast', 'Invite link copied to clipboard!'), 'success');
        setTimeout(() => { el.innerHTML = orig; el.style.color = ''; }, 2000);
    });
}
</script>

<?php stdfoot(); ?>