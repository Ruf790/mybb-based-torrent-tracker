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

const INVITE_EXPIRE_DAYS  = 14;
const INVITE_CODE_LENGTH  = 32;
const INVITE_EMAIL_MAXLEN = 64;   // invites.email is VARCHAR(64)
const INVITE_NOTE_MAXLEN  = 255;  // invites.note  is VARCHAR(255)

function generate_invite_code(): string
{
    return bin2hex(random_bytes(INVITE_CODE_LENGTH / 2));
}

/** Registration link for an invite code */
function invite_register_url(string $code): string
{
    global $BASEURL;
    return rtrim((string)$BASEURL, '/') . '/member.php?action=register&invite=' . rawurlencode($code);
}

/**
 * Take one invite from the user's balance (atomically) and create the invite row.
 * The balance is decremented first, so two parallel requests can't overspend it.
 */
function create_invite(int $inviter_id, string $email = '', string $note = ''): array|false
{
    global $db;

    $db->sql_query_prepared(
        "UPDATE users SET invites = invites - 1 WHERE id = ? AND invites > 0",
        [$inviter_id]
    );
    if ($db->affected_rows() < 1) {
        return false;
    }

    $data = [
        'code'       => generate_invite_code(),
        'inviter_id' => $inviter_id,
        'email'      => $email !== '' ? $email : null,
        'status'     => 'pending',
        'created_at' => TIMENOW,
        'expires_at' => TIMENOW + INVITE_EXPIRE_DAYS * 86400,
        'ip_created' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'note'       => $note !== '' ? $note : null,
    ];

    $columns      = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO invites (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($data)
    );
    $id = (int)$db->insert_id();

    if ($id < 1) {
        // Insert failed — give the invite back
        $db->sql_query_prepared("UPDATE users SET invites = invites + 1 WHERE id = ?", [$inviter_id]);
        return false;
    }

    return ['id' => $id] + $data;
}

function revoke_invite(int $invite_id, int $user_id): bool
{
    global $db;

    $db->sql_query_prepared(
        "UPDATE invites SET status = 'revoked'
         WHERE id = ? AND inviter_id = ? AND status = 'pending'
         LIMIT 1",
        [$invite_id, $user_id]
    );

    if ($db->affected_rows() > 0) {
        $db->sql_query_prepared("UPDATE users SET invites = invites + 1 WHERE id = ?", [$user_id]);
        return true;
    }

    return false;
}

function expire_old_invites(): void
{
    global $db;
    $db->sql_query_prepared(
        "UPDATE invites SET status = 'expired'
         WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < ?",
        [TIMENOW]
    );
}

/** One page of the user's invites */
function get_user_invites(int $user_id, int $limit, int $offset): array
{
    global $db;

    $q = $db->sql_query_prepared(
        "SELECT i.id, i.code, i.email, i.note, i.status, i.created_at, i.expires_at, i.used_at,
                i.invitee_id, u.username AS invitee_name, u.usergroup AS invitee_group
         FROM invites i
         LEFT JOIN users u ON i.invitee_id = u.id
         WHERE i.inviter_id = ?
         ORDER BY i.created_at DESC, i.id DESC
         LIMIT ? OFFSET ?",
        [$user_id, max(1, $limit), max(0, $offset)]
    );

    $invites = [];
    while ($row = $db->fetch_array($q)) {
        $invites[] = $row;
    }
    return $invites;
}

/** Totals for the KPI tiles, over ALL of the user's invites (one query) */
function get_user_invite_counts(int $user_id): array
{
    global $db;

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

function send_invite_email(string $to_email, string $code, string $inviter_name): bool
{
    global $SITENAME, $lang;
    $subject = ags_fmt((string)$lang->invites['email_subject'], (string)$SITENAME);
    $message = ags_fmt((string)$lang->invites['email_body'], $inviter_name, (string)$SITENAME, invite_register_url($code), INVITE_EXPIRE_DAYS);
    return my_mail($to_email, $subject, $message);
}

function invite_status_badge(string $status): string
{
    global $E;

    [$tone, $icon, $key] = match ($status) {
        'pending' => ['warning', 'fa-hourglass-half', 'opt_status_pending'],
        'used'    => ['success', 'fa-check',          'opt_status_used'],
        'expired' => ['muted',   'fa-clock',          'opt_status_expired'],
        'revoked' => ['danger',  'fa-ban',            'opt_status_revoked'],
        default   => ['muted',   'fa-circle-question', ''],
    };
    $text = $key !== '' ? ($E[$key] ?? $key) : htmlspecialchars($status, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return '<span class="st-badge st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i>' . $text . '</span>';
}


// ═════════════════════════════════════════════════════════════════════════════

if (!$CURUSER) {
    redirect('member.php?action=login');
}

$uid   = (int)$CURUSER['id'];
$error = '';

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_invite']) || isset($_POST['revoke_invite']))) {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        $error = $L['err_token'] ?? 'Security token expired. Reload the page and try again.';
    } elseif (isset($_POST['create_invite'])) {
        $email = trim((string)($_POST['email'] ?? ''));
        $note  = mb_substr(trim((string)($_POST['note'] ?? '')), 0, INVITE_NOTE_MAXLEN);

        if ($email !== '' && (mb_strlen($email) > INVITE_EMAIL_MAXLEN || !validate_email_format($email))) {
            $error = $L['err_email'];
        } elseif ((int)$CURUSER['invites'] <= 0) {
            $error = $L['err_no_invites'];
        } else {
            $invite = create_invite($uid, $email, $note);
            if ($invite) {
                $msg = 'created';
                $logMail = '';
                if ($email !== '') {
                    $sent    = send_invite_email($email, (string)$invite['code'], (string)$CURUSER['username']);
                    $msg     = $sent ? 'created' : 'created_nomail';
                    $logMail = $sent ? ' (sent by e-mail)' : ' (e-mail delivery failed)';
                }
                write_log("[Invites] {$CURUSER['username']} created invite #{$invite['id']}{$logMail}");
                header('Location: invite.php?msg=' . $msg);
                exit;
            }
            $error = $L['err_create_failed'];
        }
    } else {
        $invite_id = (int)($_POST['invite_id'] ?? 0);
        if (revoke_invite($invite_id, $uid)) {
            write_log("[Invites] {$CURUSER['username']} revoked invite #{$invite_id}");
            header('Location: invite.php?msg=revoked');
            exit;
        }
        $error = $L['err_revoke_failed'];
    }

    // Balance may have changed in this request
    $row = $db->fetch_array($db->sql_query_prepared("SELECT invites FROM users WHERE id = ? LIMIT 1", [$uid]));
    $CURUSER['invites'] = (int)($row['invites'] ?? 0);
}

[$flashType, $flashMsg] = match ($_GET['msg'] ?? '') {
    'created'        => ['success', (string)$L['flash_created']],
    'created_nomail' => ['warning', (string)($L['msg_mail_failed'] ?? 'Invite created, but the e-mail could not be sent. Copy the link and send it yourself.')],
    'revoked'        => ['success', (string)$L['flash_revoked']],
    default          => ['', ''],
};
if ($error !== '') {
    [$flashType, $flashMsg] = ['error', (string)$error];
}

// ── Data ─────────────────────────────────────────────────────────────────────
expire_old_invites();
$stats    = get_user_invite_counts($uid);
$balance  = (int)$CURUSER['invites'];

// Rows per page: the site-wide pager setting (Settings → Forum/Legacy → ts_perpage)
$perpage = max(1, (int)($ts_perpage ?? 20));
$pages   = max(1, (int)ceil($stats['total'] / $perpage));
// get_input() merges GET and POST: the pager links send ?page=N, while its
// "Jump to page" form POSTs the number to invite.php?page=1 (POST wins)
$page    = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $pages);

$invites   = get_user_invites($uid, $perpage, ($page - 1) * $perpage);
$multipage = (string)multipage($stats['total'], $perpage, $page, 'invite.php?page={page}');

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$fmtDate = (string)($L['fmt_date'] ?? 'M j, Y');

$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$v;
    }
}

stdhead($L['page_title']);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=7">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/invite.css?ver=2">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">

<div class="container-md st-page iv-page"<?php if ($flashMsg !== ''): ?> data-flash-type="<?= $h($flashType) ?>" data-flash-msg="<?= $h($flashMsg) ?>"<?php endif; ?>>

    <!-- Header -->
    <div class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i></div>
        <div class="min-w-0 flex-grow-1">
            <h1 class="st-title"><?= $E['page_title'] ?></h1>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-user-plus" aria-hidden="true"></i><?= ags_fmt($E['pane_subtitle'], $h($SITENAME)) ?></span>
            </div>
        </div>
        <?php if ($balance > 0): ?>
        <a href="#iv-create" class="btn btn-primary btn-sm st-pill">
            <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= $E['btn_generate'] ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- KPI -->
    <div class="iv-kpis mb-3">
        <div class="st-kpi st-kpi--muted">
            <i class="fa-solid fa-ticket st-kpi__bg" aria-hidden="true"></i>
            <div><div class="st-kpi__val"><?= number_format($balance) ?></div><div class="st-kpi__lbl"><?= $E['lbl_invites_left'] ?></div></div>
        </div>
        <div class="st-kpi st-kpi--muted">
            <i class="fa-solid fa-paper-plane st-kpi__bg" aria-hidden="true"></i>
            <div><div class="st-kpi__val"><?= number_format($stats['total']) ?></div><div class="st-kpi__lbl"><?= $E['lbl_kpi_total'] ?></div></div>
        </div>
        <div class="st-kpi st-kpi--muted">
            <i class="fa-solid fa-hourglass-half st-kpi__bg" aria-hidden="true"></i>
            <div><div class="st-kpi__val"><?= number_format($stats['pending']) ?></div><div class="st-kpi__lbl"><?= $E['lbl_kpi_pending'] ?></div></div>
        </div>
        <div class="st-kpi st-kpi--muted">
            <i class="fa-solid fa-user-check st-kpi__bg" aria-hidden="true"></i>
            <div><div class="st-kpi__val"><?= number_format($stats['used']) ?></div><div class="st-kpi__lbl"><?= $E['lbl_kpi_used'] ?></div></div>
        </div>
    </div>

    <!-- Create invite -->
    <div class="st-card mb-3" id="iv-create">
        <div class="st-card__head">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i></span>
            <span><?= $E['sec_create'] ?></span>
        </div>
        <?php if ($balance > 0): ?>
        <form method="post" action="invite.php" class="st-card__body">
            <input type="hidden" name="my_post_key" value="<?= $h($mybb->post_code) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="iv-email"><?= $E['lbl_email'] ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-at" aria-hidden="true"></i></span>
                        <input type="email" class="form-control" id="iv-email" name="email" maxlength="<?= INVITE_EMAIL_MAXLEN ?>"
                               placeholder="<?= $E['ph_email'] ?>" value="<?= $error !== '' ? $h($_POST['email'] ?? '') : '' ?>">
                    </div>
                    <div class="form-text"><?= $E['hint_email'] ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="iv-note"><?= $E['lbl_note'] ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" id="iv-note" name="note" maxlength="<?= INVITE_NOTE_MAXLEN ?>"
                               placeholder="<?= $E['ph_note'] ?>" value="<?= $error !== '' ? $h($_POST['note'] ?? '') : '' ?>">
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="create_invite" value="1" class="btn btn-primary st-pill">
                    <i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i><?= $E['btn_generate'] ?>
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="st-empty">
            <div class="st-ico st-soft-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
            <div><?= $E['hint_no_invites'] ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- History -->
    <div class="st-card iv-section">
        <div class="st-card__head">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-list" aria-hidden="true"></i></span>
            <span><?= $E['sec_history'] ?></span>
            <?php if ($stats['total'] > 0): ?>
            <span class="ms-auto st-badge st-soft-muted"><?= number_format($stats['total']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$invites): ?>
        <div class="st-empty">
            <div class="st-ico st-soft-muted"><i class="fa-solid fa-envelope-open" aria-hidden="true"></i></div>
            <div><?= $E['hint_empty'] ?></div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table iv-table align-middle">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-key" aria-hidden="true"></i><?= $E['lbl_col_code'] ?></th>
                        <th class="d-none d-lg-table-cell"><?= $E['lbl_col_email'] ?></th>
                        <th class="d-none d-md-table-cell"><?= $E['lbl_col_note'] ?></th>
                        <th><?= $E['lbl_col_invitee'] ?></th>
                        <th><?= $E['lbl_col_status'] ?></th>
                        <th class="d-none d-sm-table-cell"><?= $E['lbl_col_expires'] ?></th>
                        <th class="d-none d-md-table-cell"><?= $E['lbl_col_created'] ?></th>
                        <th class="text-end"><span class="visually-hidden"><?= $E['tip_revoke'] ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invites as $inv):
                    $status  = (string)$inv['status'];
                    $code    = (string)$inv['code'];
                    $short   = $h(substr($code, 0, 12)) . '…';
                    $pending = $status === 'pending';
                ?>
                    <tr class="<?= $pending ? 'iv-row--pending' : (in_array($status, ['expired', 'revoked'], true) ? 'iv-row--dim' : '') ?>">
                        <td>
                            <?php if ($pending): ?>
                            <button type="button" class="iv-code" data-iv-link="<?= $h(invite_register_url($code)) ?>"
                                    title="<?= $E['tip_copy'] ?>" aria-label="<?= $E['tip_copy'] ?>">
                                <span class="iv-code__txt"><?= $short ?></span>
                                <i class="fa-solid fa-copy" aria-hidden="true"></i>
                            </button>
                            <?php else: ?>
                            <span class="iv-code iv-code--off"><?= $short ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell iv-muted iv-wrap"><?= !empty($inv['email']) ? $h($inv['email']) : '—' ?></td>
                        <td class="d-none d-md-table-cell iv-muted iv-wrap"><?= !empty($inv['note']) ? $h($inv['note']) : '—' ?></td>
                        <td>
                            <?php if (!empty($inv['invitee_name'])): ?>
                            <a href="<?= $h(get_profile_link((int)$inv['invitee_id'])) ?>" class="iv-user">
                                <?= format_name(htmlspecialchars_uni((string)$inv['invitee_name']), (int)$inv['invitee_group']) ?>
                            </a>
                            <?php else: ?>
                            <span class="iv-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= invite_status_badge($status) ?></td>
                        <td class="d-none d-sm-table-cell iv-muted iv-nowrap">
                            <?php if ($status === 'used' && !empty($inv['used_at'])): ?>
                                <i class="fa-solid fa-check iv-ico-sm" aria-hidden="true"></i><?= $h(date($fmtDate, (int)$inv['used_at'])) ?>
                            <?php elseif (!empty($inv['expires_at'])): ?>
                                <?= $h(date($fmtDate, (int)$inv['expires_at'])) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell iv-muted iv-nowrap"><?= $h(date($fmtDate, (int)$inv['created_at'])) ?></td>
                        <td class="text-end">
                            <?php if ($pending): ?>
                            <form method="post" action="invite.php" class="d-inline iv-revoke-form">
                                <input type="hidden" name="my_post_key" value="<?= $h($mybb->post_code) ?>">
                                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                                <input type="hidden" name="revoke_invite" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-danger st-pill" title="<?= $E['tip_revoke'] ?>" aria-label="<?= $E['tip_revoke'] ?>">
                                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
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
        <div class="st-toolbar st-toolbar--bottom st-pager"><?= $multipage ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<div id="toast-container"></div>

<script>const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/scripts/invite.js?ver=1"></script>
<?php
stdfoot();
