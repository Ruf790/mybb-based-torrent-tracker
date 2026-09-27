<?php
declare(strict_types=1);

/**
 * Upload Manager — добавление и снятие отдачи (раньше: uploadadd.php + removeadd.php)
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

use function htmlspecialchars as e;

const UM_VERSION    = '1.0';
const GB_IN_BYTES   = 1024 * 1024 * 1024;
const UM_MAX_SINGLE = 1000;   // GB для одного пользователя
const UM_MAX_BULK   = 50;     // GB на человека для группы

/** Кого затрагивают действия: только активные подтверждённые аккаунты */
const UM_ACTIVE = "enabled = 'yes' AND ustatus = 'confirmed'";

function um_op(mixed $v): string { return $v === 'remove' ? 'remove' : 'add'; }

function um_modcomment(string $op, int $gb): string
{
    global $CURUSER;
    return sprintf("%s - %s %s upload by %s (Upload Manager)\n",
        gmdate('Y-m-d'), $op === 'add' ? 'Received' : 'Removed', mksize($gb * GB_IN_BYTES), $CURUSER['username'] ?? 'System');
}

/** (string): get_user_class_name(string) с int давала TypeError в strict_types */
function um_group_name(int $gid): string
{
    $n = function_exists('get_user_class_name') ? (string)get_user_class_name((string)$gid) : '';
    return $n !== '' ? $n : 'Group ' . $gid;
}

/** SQL-выражение для новой отдачи (снятие — никогда ниже нуля) */
function um_set(string $op): string
{
    return $op === 'add' ? 'uploaded = uploaded + ?' : 'uploaded = CASE WHEN uploaded >= ? THEN uploaded - ? ELSE 0 END';
}
function um_set_params(string $op, int $bytes): array
{
    return $op === 'add' ? [$bytes] : [$bytes, $bytes];
}

// ── AJAX: пользователь ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = $db->sql_query_prepared("SELECT id, username, usergroup, uploaded, downloaded, enabled, ustatus FROM users WHERE username = ? LIMIT 1", [trim((string)$_GET['lookup'])]);
    $u = $q ? $db->fetch_array($q) : null;
    echo json_encode($u ? [
        'found'     => true,
        'id'        => (int)$u['id'],
        'name_html' => format_name(e((string)$u['username']), (int)$u['usergroup']),
        'uploaded'  => (float)$u['uploaded'],
        'downloaded'=> (float)$u['downloaded'],
        'up_h'      => mksize((float)$u['uploaded']),
        'active'    => $u['enabled'] === 'yes' && $u['ustatus'] === 'confirmed',
    ] : ['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: сводка по группе ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['groupstat'])) {
    header('Content-Type: application/json; charset=utf-8');
    $g     = ctype_digit((string)$_GET['groupstat']) ? (int)$_GET['groupstat'] : 0;
    $bytes = max(0, min(UM_MAX_BULK, (int)($_GET['gb'] ?? 0))) * GB_IN_BYTES;
    $w     = UM_ACTIVE . ($g > 0 ? ' AND usergroup = ?' : '');
    $q = $db->sql_query_prepared(
        "SELECT COUNT(*) AS n, COALESCE(SUM(uploaded > 0),0) AS withup, COALESCE(SUM(LEAST(uploaded, ?)),0) AS removable FROM users WHERE {$w}",
        $g > 0 ? [$bytes, $g] : [$bytes]
    );
    $r = $q ? $db->fetch_array($q) : [];
    $n = (int)($r['n'] ?? 0);
    echo json_encode([
        'n'         => $n,
        'withup'    => (int)($r['withup'] ?? 0),
        'add_h'     => mksize((float)$bytes * $n),
        'remove_h'  => mksize((float)($r['removable'] ?? 0)),
    ]);
    exit;
}

// ── Итог прошлого действия (показываем один раз) ──────────────────────
$error = null;
$done  = $_SESSION['um_done'] ?? null;
unset($_SESSION['um_done']);
$op    = um_op($_POST['op'] ?? $_GET['op'] ?? ($done['op'] ?? 'add'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            throw new InvalidArgumentException('Security check failed. Please refresh the page and try again.');
        }
        $op   = um_op($_POST['op'] ?? 'add');
        $verb = $op === 'add' ? 'added' : 'removed';

        if (($_POST['doit'] ?? '') === 'yes') {
            // ── Группа ──
            $class = ctype_digit((string)($_POST['usergroup'] ?? '')) ? (int)$_POST['usergroup'] : 0;
            $gb    = (int)($_POST['classamount'] ?? 0);
            if ($gb < 1 || $gb > UM_MAX_BULK) {
                throw new InvalidArgumentException('Please choose an amount between 1 and ' . UM_MAX_BULK . ' GB.');
            }
            $bytes = $gb * GB_IN_BYTES;
            $w     = UM_ACTIVE . ($class > 0 ? ' AND usergroup = ?' : '');
            $wp    = $class > 0 ? [$class] : [];

            // Цифры — ДО изменения
            $cq = $db->sql_query_prepared("SELECT COUNT(*) AS n, COALESCE(SUM(LEAST(uploaded, ?)),0) AS removable FROM users WHERE {$w}", [$bytes, ...$wp]);
            $c  = $cq ? $db->fetch_array($cq) : [];
            $n  = (int)($c['n'] ?? 0);
            $total = $op === 'add' ? (float)$bytes * $n : (float)($c['removable'] ?? 0);

            $ok = $db->sql_query_prepared(
                "UPDATE users SET " . um_set($op) . ", modcomment = CONCAT(?, modcomment) WHERE {$w}",
                [...um_set_params($op, $bytes), um_modcomment($op, $gb), ...$wp]
            );
            if ($ok === false) throw new RuntimeException('Failed to update users.');

            $who = $class > 0 ? um_group_name($class) : 'all users';
            write_log(sprintf('Upload Manager: %s %s %d GB %s %s (%d users, %s in total)',
                $CURUSER['username'] ?? 'staff', $verb, $gb, $op === 'add' ? 'to' : 'from', $who, $n, mksize($total)));
            $done = ['op' => $op, 'mass' => true, 'gb' => $gb, 'who' => $who, 'n' => $n, 'total' => mksize($total)];
        } else {
            // ── Один пользователь ──
            $username = trim((string)($_POST['username'] ?? ''));
            $gb       = (int)($_POST['uploaded'] ?? 0);
            if ($username === '') throw new InvalidArgumentException('Please enter a username.');
            if ($gb < 1 || $gb > UM_MAX_SINGLE) throw new InvalidArgumentException('Amount must be between 1 and ' . UM_MAX_SINGLE . ' GB.');

            $uq = $db->sql_query_prepared("SELECT id, username, uploaded FROM users WHERE username = ? AND " . UM_ACTIVE . " LIMIT 1", [$username]);
            $u  = $uq ? $db->fetch_array($uq) : null;
            if (!$u) throw new RuntimeException('User not found, disabled or not confirmed.');

            $bytes = $gb * GB_IN_BYTES;
            $ok = $db->sql_query_prepared(
                "UPDATE users SET " . um_set($op) . ", modcomment = CONCAT(?, modcomment) WHERE id = ?",
                [...um_set_params($op, $bytes), um_modcomment($op, $gb), (int)$u['id']]
            );
            if ($ok === false) throw new RuntimeException('Failed to update the user.');

            $old = (float)$u['uploaded'];
            $new = $op === 'add' ? $old + $bytes : max(0.0, $old - $bytes);
            write_log(sprintf('Upload Manager: %s %s %d GB %s %s (%s -> %s)',
                $CURUSER['username'] ?? 'staff', $verb, $gb, $op === 'add' ? 'to' : 'from', $u['username'], mksize($old), mksize($new)));
            $done = ['op' => $op, 'mass' => false, 'gb' => $gb, 'who' => (string)$u['username'], 'id' => (int)$u['id'], 'old' => mksize($old), 'new' => mksize($new)];
        }

        // Post/Redirect/Get — F5 не повторит действие
        $_SESSION['um_done'] = $done;
        $to = $_this_script_ . (str_contains((string)$_this_script_, '?') ? '&' : '?') . 'op=' . $op;
        function_exists('admin_redirect') ? admin_redirect($to) : header('Location: ' . $to);
        exit;
    } catch (InvalidArgumentException | RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = 'An unexpected error occurred. Please try again.';
        error_log('Upload Manager error: ' . $ex->getMessage());
    }
}

// Сводка для плиток
$sq = $db->sql_query_prepared("SELECT COUNT(*) AS n, COALESCE(SUM(uploaded),0) AS up, COALESCE(SUM(downloaded),0) AS down FROM users WHERE " . UM_ACTIVE);
$st = $sq ? $db->fetch_array($sq) : [];

// Последние действия (новые записи + старые форматы обоих инструментов)
$recent = [];
$rq = $db->sql_query_prepared(
    "SELECT txt, added FROM sitelog WHERE txt LIKE ? OR txt LIKE ? OR txt LIKE ? ORDER BY added DESC LIMIT 8",
    ['Upload Manager:%', '% GB upload added to %', 'Upload Remover:%']
);
while ($rq && ($r = $db->fetch_array($rq))) $recent[] = $r;

stdhead('Upload Manager');
$self = (string)$_this_script_;
$key  = e((string)$mybb->post_code);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/uploadadd.css?v=<?= UM_VERSION ?>">


<div class="container mt-3 mb-4 um" data-op="<?= $op ?>" data-self="<?= e($self) ?>" id="um">

    <div class="um-card mb-3"><div class="um-head">
        <span class="um-head-icon"><i class="fa-solid fa-cloud-arrow-up" id="umHeadIcon"></i></span>
        <div class="um-min0">
            <h1 class="um-title">Upload Manager</h1>
            <div class="um-sub">Add or remove upload credit — for one member or a whole group</div>
        </div>
        <span class="ms-auto um-muted"><i class="fa-solid fa-code-branch me-1"></i>v<?= UM_VERSION ?></span>
    </div></div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation mt-1"></i><div><?= e($error) ?></div></div>
    <?php endif; ?>

    <?php if ($done): $add = $done['op'] === 'add'; ?>
    <div class="um-done mb-3"><i class="fa-solid fa-circle-check"></i>
        <div class="flex-grow-1">
            <div class="fw-bold"><?= $add ? 'Added' : 'Removed' ?> <?= (int)$done['gb'] ?> GB <?= $add ? 'to' : 'from' ?> <?= e($done['who']) ?></div>
            <?php if ($done['mass']): ?>
            <div class="um-muted"><?= number_format((int)$done['n']) ?> user(s) · <?= e($done['total']) ?> in total · noted in mod comments and the site log</div>
            <?php else: ?>
            <div class="um-muted">Uploaded <?= e($done['old']) ?> → <strong><?= e($done['new']) ?></strong> · noted in mod comment</div>
            <?php endif; ?>
        </div>
        <?php if (!$done['mass']): ?><a href="<?= e($BASEURL . '/' . get_profile_link((int)$done['id'])) ?>" class="btn btn-sm btn-outline-success px-3"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Profile</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Режим -->
    <div class="um-mode mb-3" role="radiogroup" aria-label="Operation">
        <input type="radio" name="um_op" id="opAdd" value="add" <?= $op === 'add' ? 'checked' : '' ?>>
        <label for="opAdd"><span class="um-sec-icon ic-green"><i class="fa-solid fa-plus"></i></span><span><b class="d-block">Add upload</b><small class="um-muted">Credit extra upload</small></span></label>
        <input type="radio" name="um_op" id="opRemove" value="remove" <?= $op === 'remove' ? 'checked' : '' ?>>
        <label for="opRemove"><span class="um-sec-icon ic-red"><i class="fa-solid fa-minus"></i></span><span><b class="d-block">Remove upload</b><small class="um-muted">Never below zero</small></span></label>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-users',    'ic-blue',  'Active users',   number_format((int)($st['n'] ?? 0))],
            ['fa-upload',   'ic-green', 'Total uploaded', mksize((float)($st['up'] ?? 0))],
            ['fa-download', 'ic-red',   'Total downloaded', mksize((float)($st['down'] ?? 0))],
            ['fa-scale-balanced', 'ic-amber', 'Site ratio', (float)($st['down'] ?? 0) > 0 ? number_format((float)$st['up'] / (float)$st['down'], 2) : '∞'],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="um-card um-kpi"><span class="um-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="um-kpi-label"><?= $label ?></div><div class="um-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <!-- Один пользователь -->
        <div class="col-lg-6">
            <form method="post" action="<?= e($self) ?>" class="um-card h-100" id="umSingle" novalidate>
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="op" value="<?= $op ?>" class="um-op-field">
                <div class="um-sec-head"><span class="um-sec-icon mode"><i class="fa-solid fa-user"></i></span>
                    <div><h2 class="um-sec-title">Single user</h2><div class="um-muted">Up to <?= UM_MAX_SINGLE ?> GB</div></div></div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <label for="username" class="form-label"><i class="fa-solid fa-user"></i>Username</label>
                        <input type="text" class="form-control" name="username" id="username" value="<?= e((string)($_POST['username'] ?? '')) ?>" placeholder="Exact username" required maxlength="64" autocomplete="off">
                        <div class="um-box mt-2" id="umUser" hidden>
                            <i class="fa-solid fa-upload text-body-secondary"></i>
                            <div class="flex-grow-1 um-min0">
                                <div class="fw-bold" id="umUName"></div>
                                <div class="um-muted">Uploaded <span class="v" id="umUNow"></span> → <span class="v res" id="umUAfter"></span></div>
                                <div class="small text-danger" id="umUInactive" hidden><i class="fa-solid fa-ban me-1"></i>Disabled or not confirmed — can't be changed</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="uploaded" class="form-label"><i class="fa-solid fa-hashtag"></i><span class="um-verb">Add</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="uploaded" id="uploaded" min="1" max="<?= UM_MAX_SINGLE ?>" value="<?= e((string)($_POST['uploaded'] ?? '10')) ?>" required>
                            <span class="input-group-text">GB</span>
                        </div>
                        <div class="um-chips" data-for="uploaded"><?php foreach ([1, 5, 10, 25, 50, 100] as $v): ?><button type="button" class="um-chip" data-v="<?= $v ?>"><?= $v ?> GB</button><?php endforeach; ?></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn um-go px-4"><i class="fa-solid fa-check me-1"></i><span class="um-verb">Add</span> upload</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Группа -->
        <div class="col-lg-6">
            <form method="post" action="<?= e($self) ?>" class="um-card h-100" id="umMass" novalidate>
                <input type="hidden" name="doit" value="yes">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="op" value="<?= $op ?>" class="um-op-field">
                <div class="um-sec-head"><span class="um-sec-icon mode"><i class="fa-solid fa-users"></i></span>
                    <div><h2 class="um-sec-title">Whole group</h2><div class="um-muted">Same amount for every active member</div></div></div>
                <div class="p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label"><i class="fa-solid fa-filter"></i>Group</label>
                            <div class="um-group"><?= _selectbox_('', 'usergroup', true, 'All users', $_POST['usergroup'] ?? '') ?></div>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="classamount"><i class="fa-solid fa-hashtag"></i>Per user</label>
                            <select name="classamount" id="classamount" class="form-select" required>
                                <option value="0">Choose…</option>
                                <?php for ($i = 1; $i <= UM_MAX_BULK; $i++): ?><option value="<?= $i ?>" <?= (int)($_POST['classamount'] ?? 10) === $i ? 'selected' : '' ?>><?= $i ?> GB</option><?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="um-box mb-3">
                        <i class="fa-solid fa-calculator text-body-secondary"></i>
                        <div class="flex-grow-1"><div class="fw-semibold" id="umGWho">All users</div><div class="um-muted" id="umGStat">—</div></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn um-go px-4" id="umMassBtn"><i class="fa-solid fa-bolt me-1"></i><span class="um-verb">Add</span> for group</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <div class="um-card overflow-hidden">
                <div class="um-sec-head"><span class="um-sec-icon ic-slate"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div><h2 class="um-sec-title">Recent changes</h2><div class="um-muted">From the site log</div></div></div>
                <?php if ($recent): ?>
                <ul class="um-log">
                    <?php foreach ($recent as $r):
                        $t = (string)$r['txt'];
                        $isAdd = str_contains($t, ' added ') || str_contains($t, 'upload added'); ?>
                    <li><i class="fa-solid <?= $isAdd ? 'fa-plus text-success' : 'fa-minus text-danger' ?>"></i>
                        <span class="flex-grow-1"><?= e($t) ?></span>
                        <span class="um-muted text-nowrap"><?= my_datee('relative', (int)$r['added']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="um-muted px-4 py-3">No changes logged yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/uploadadd.js?v=<?= UM_VERSION ?>"></script>
<?php
stdfoot();