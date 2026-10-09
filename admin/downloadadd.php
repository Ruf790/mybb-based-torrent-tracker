<?php
declare(strict_types=1);

/**
 * Download Manager — добавление и снятие скачанного (в стиле Upload Manager)
 * Ресурсы: admin/templates/downloadadd.css, admin/scripts/downloadadd.js
 * Ланг:    languages/<lang>/downloadadd.lang.php
 */

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Access denied.</strong> Direct initialization of this file is not allowed.</div>');
}

use function htmlspecialchars as e;

global $lang;
$lang->load('downloadadd');

/** {1}… и %1$s… ($lang->load() превращает {1} в %1$s) */
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $a) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$a;
            $map['%' . $n . '$s'] = (string)$a;
        }
        return strtr($str, $map);
    }
}

const DM_VERSION    = '4.2';
const DM_GB         = 1024 * 1024 * 1024;
const DM_MAX_SINGLE = 1000;   // GB для одного пользователя
const DM_MAX_BULK   = 50;     // GB на человека для группы

/** Кого затрагивают действия: только активные подтверждённые аккаунты */
const DM_ACTIVE = "enabled = 'yes' AND ustatus = 'confirmed'";

function dm_op(mixed $v): string { return $v === 'remove' ? 'remove' : 'add'; }

function dm_ratio(float $up, float $down): string
{
    if ($down <= 0) return $up > 0 ? '∞' : '—';
    return number_format($up / $down, 2);
}

/** Модкомментарий — всегда на английском (как и логи) */
function dm_modcomment(string $op, int $gb, string $note = ''): string
{
    global $CURUSER;
    $c = sprintf('%s - %s %s download by %s (Download Manager)',
        gmdate('Y-m-d'), $op === 'add' ? 'Added' : 'Removed', mksize($gb * DM_GB), $CURUSER['username'] ?? 'System');
    if ($note !== '') $c .= ' [Note: ' . e($note, ENT_QUOTES, 'UTF-8') . ']';
    return $c . "\n";
}

/**
 * (string): get_user_class_name(string) с int даёт TypeError в strict_types.
 * $fallback = '' → английское «Group N» (для лога); для экрана передаётся перевод.
 */
function dm_group_name(int $gid, string $fallback = ''): string
{
    $n = function_exists('get_user_class_name') ? (string)get_user_class_name((string)$gid) : '';
    if ($n !== '') return $n;
    return $fallback !== '' ? $fallback : 'Group ' . $gid;
}

/** SQL-выражение для нового значения (снятие — никогда ниже нуля) */
function dm_set(string $op): string
{
    return $op === 'add' ? 'downloaded = downloaded + ?' : 'downloaded = CASE WHEN downloaded >= ? THEN downloaded - ? ELSE 0 END';
}
function dm_set_params(string $op, int $bytes): array
{
    return $op === 'add' ? [$bytes] : [$bytes, $bytes];
}

// ── AJAX: пользователь ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = $db->sql_query_prepared("SELECT id, username, usergroup, uploaded, downloaded, enabled, ustatus FROM users WHERE username = ? LIMIT 1", [trim((string)$_GET['lookup'])]);
    $u = $q ? $db->fetch_array($q) : null;
    echo json_encode($u ? [
        'found'      => true,
        'id'         => (int)$u['id'],
        'name_html'  => format_name(e((string)$u['username']), (int)$u['usergroup']),
        'uploaded'   => (float)$u['uploaded'],
        'downloaded' => (float)$u['downloaded'],
        'up_h'       => mksize((float)$u['uploaded']),
        'down_h'     => mksize((float)$u['downloaded']),
        'active'     => $u['enabled'] === 'yes' && $u['ustatus'] === 'confirmed',
    ] : ['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── AJAX: сводка по группе ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['groupstat'])) {
    header('Content-Type: application/json; charset=utf-8');
    $g     = ctype_digit((string)$_GET['groupstat']) ? (int)$_GET['groupstat'] : 0;
    $bytes = max(0, min(DM_MAX_BULK, (int)($_GET['gb'] ?? 0))) * DM_GB;
    $w     = DM_ACTIVE . ($g > 0 ? ' AND usergroup = ?' : '');
    $q = $db->sql_query_prepared(
        "SELECT COUNT(*) AS n, COALESCE(SUM(downloaded > 0),0) AS withdown, COALESCE(SUM(LEAST(downloaded, ?)),0) AS removable FROM users WHERE {$w}",
        $g > 0 ? [$bytes, $g] : [$bytes]
    );
    $r = $q ? $db->fetch_array($q) : [];
    $n = (int)($r['n'] ?? 0);
    echo json_encode([
        'n'        => $n,
        'withdown' => (int)($r['withdown'] ?? 0),
        'add_h'    => mksize((float)$bytes * $n),
        'remove_h' => mksize((float)($r['removable'] ?? 0)),
    ]);
    exit;
}

// ── Итог прошлого действия (показываем один раз) ──────────────────────
$error = null;
$done  = $_SESSION['dm_done'] ?? null;
unset($_SESSION['dm_done']);
$op    = dm_op($_POST['op'] ?? $_GET['op'] ?? ($done['op'] ?? 'add'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            throw new InvalidArgumentException($lang->downloadadd['err_csrf']);
        }
        $op   = dm_op($_POST['op'] ?? 'add');
        $verb = $op === 'add' ? 'added' : 'removed';
        $note = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)($_POST['admin_note'] ?? '')) ?? '');
        $note = mb_substr($note, 0, 255);

        if (($_POST['doit'] ?? '') === 'yes') {
            // ── Группа ──
            $class = ctype_digit((string)($_POST['usergroup'] ?? '')) ? (int)$_POST['usergroup'] : 0;
            $gb    = (int)($_POST['classamount'] ?? 0);
            if ($gb < 1 || $gb > DM_MAX_BULK) {
                throw new InvalidArgumentException(ags_fmt($lang->downloadadd['err_bulk_amount'], DM_MAX_BULK));
            }
            $bytes = $gb * DM_GB;
            // Снятие: только тем, у кого есть скачанное — иначе в модкомментарий попадёт «Removed» без изменений
            $w     = DM_ACTIVE . ($class > 0 ? ' AND usergroup = ?' : '') . ($op === 'remove' ? ' AND downloaded > 0' : '');
            $wp    = $class > 0 ? [$class] : [];

            // Цифры — ДО изменения
            $cq = $db->sql_query_prepared("SELECT COUNT(*) AS n, COALESCE(SUM(LEAST(downloaded, ?)),0) AS removable FROM users WHERE {$w}", [$bytes, ...$wp]);
            $c  = $cq ? $db->fetch_array($cq) : [];
            $n  = (int)($c['n'] ?? 0);
            if ($n === 0) throw new RuntimeException($op === 'remove'
                ? $lang->downloadadd['err_group_nothing']
                : $lang->downloadadd['err_group_empty']);
            $total = $op === 'add' ? (float)$bytes * $n : (float)($c['removable'] ?? 0);

            $ok = $db->sql_query_prepared(
                "UPDATE users SET " . dm_set($op) . ", modcomment = CONCAT(?, COALESCE(modcomment, '')) WHERE {$w}",
                [...dm_set_params($op, $bytes), dm_modcomment($op, $gb), ...$wp]
            );
            if ($ok === false) throw new RuntimeException($lang->downloadadd['err_update_users']);

            $who = $class > 0 ? dm_group_name($class) : 'all users';   // для лога — английский
            write_log(sprintf('Download Manager: %s %s %d GB %s %s (%d users, %s in total)',
                $CURUSER['username'] ?? 'staff', $verb, $gb, $op === 'add' ? 'to' : 'from', $who, $n, mksize($total)));
            $done = ['op' => $op, 'mass' => true, 'all' => $class === 0, 'gb' => $gb,
                     'who' => $class > 0 ? dm_group_name($class, ags_fmt($lang->downloadadd['fallback_group'], $class)) : '',
                     'n' => $n, 'total' => mksize($total)];
        } else {
            // ── Один пользователь ──
            $username = trim((string)($_POST['username'] ?? ''));
            $gb       = (int)($_POST['downloaded'] ?? 0);
            if ($username === '') throw new InvalidArgumentException($lang->downloadadd['err_no_username']);
            if ($gb < 1 || $gb > DM_MAX_SINGLE) throw new InvalidArgumentException(ags_fmt($lang->downloadadd['err_single_amount'], DM_MAX_SINGLE));

            $uq = $db->sql_query_prepared("SELECT id, username, uploaded, downloaded FROM users WHERE username = ? AND " . DM_ACTIVE . " LIMIT 1", [$username]);
            $u  = $uq ? $db->fetch_array($uq) : null;
            if (!$u) throw new RuntimeException($lang->downloadadd['err_user_not_found']);
            if ($op === 'remove' && (float)$u['downloaded'] <= 0) {
                throw new RuntimeException($lang->downloadadd['err_user_nothing']);
            }

            $bytes = $gb * DM_GB;
            $ok = $db->sql_query_prepared(
                "UPDATE users SET " . dm_set($op) . ", modcomment = CONCAT(?, COALESCE(modcomment, '')) WHERE id = ?",
                [...dm_set_params($op, $bytes), dm_modcomment($op, $gb, $note), (int)$u['id']]
            );
            if ($ok === false) throw new RuntimeException($lang->downloadadd['err_update_user']);

            $up  = (float)$u['uploaded'];
            $old = (float)$u['downloaded'];
            $new = $op === 'add' ? $old + $bytes : max(0.0, $old - $bytes);
            write_log(sprintf('Download Manager: %s %s %d GB %s %s (%s -> %s, ratio %s -> %s)',
                $CURUSER['username'] ?? 'staff', $verb, $gb, $op === 'add' ? 'to' : 'from', $u['username'],
                mksize($old), mksize($new), dm_ratio($up, $old), dm_ratio($up, $new))
                . ($note !== '' ? ' [Note: ' . $note . ']' : ''));
            $done = ['op' => $op, 'mass' => false, 'gb' => $gb, 'who' => (string)$u['username'], 'id' => (int)$u['id'],
                     'old' => mksize($old), 'new' => mksize($new), 'r_old' => dm_ratio($up, $old), 'r_new' => dm_ratio($up, $new), 'note' => $note];
        }

        // Post/Redirect/Get — F5 не повторит действие
        $_SESSION['dm_done'] = $done;
        $to = $_this_script_ . (str_contains((string)$_this_script_, '?') ? '&' : '?') . 'op=' . $op;
        function_exists('admin_redirect') ? admin_redirect($to) : header('Location: ' . $to);
        exit;
    } catch (InvalidArgumentException | RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = $lang->downloadadd['err_unexpected'];
        error_log('Download Manager error: ' . $ex->getMessage());
    }
}

// Сводка для плиток
$sq = $db->sql_query_prepared("SELECT COUNT(*) AS n, COALESCE(SUM(uploaded),0) AS up, COALESCE(SUM(downloaded),0) AS down FROM users WHERE " . DM_ACTIVE);
$st = $sq ? $db->fetch_array($sq) : [];

// Последние действия (новый формат + старые записи прежнего инструмента)
$recent = [];
$rq = $db->sql_query_prepared(
    "SELECT txt, added FROM sitelog WHERE txt LIKE ? OR txt LIKE ? ORDER BY added DESC LIMIT 8",
    ['Download Manager:%', '% GB download added via %']
);
while ($rq && ($r = $db->fetch_array($rq))) $recent[] = $r;

// Строки для JS: ключи js_* без префикса
$dmJs = [];
foreach ($lang->downloadadd as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) $dmJs[substr((string)$k, 3)] = $v;
}

$GB = e($lang->downloadadd['unit_gb']);

stdhead($lang->downloadadd['page_title']);
$self = (string)$_this_script_;
$key  = e((string)$mybb->post_code);
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/downloadadd.css?ver=2">
<script>const AGS_LANG = <?= json_encode($dmJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js" defer></script>
<script src="<?= $BASEURL ?>/admin/scripts/downloadadd.js?ver=3" defer></script>

<div class="container mt-3 mb-4 dm" data-op="<?= $op ?>" data-self="<?= e($self) ?>" id="dm">

    <div class="dm-card mb-3"><div class="dm-head">
        <span class="dm-head-icon"><i class="fa-solid fa-cloud-arrow-down" id="dmHeadIcon"></i></span>
        <div style="min-width:0">
            <h1 class="dm-title"><?= e($lang->downloadadd['page_title']) ?></h1>
            <div class="dm-sub"><?= e($lang->downloadadd['page_sub']) ?></div>
        </div>
        <span class="ms-auto dm-muted"><i class="fa-solid fa-code-branch me-1"></i>v<?= DM_VERSION ?></span>
    </div></div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-exclamation mt-1"></i><div><?= e($error) ?></div></div>
    <?php endif; ?>

    <?php if ($done):
        $add    = $done['op'] === 'add';
        $gbDone = (int)$done['gb'];
        if (!$done['mass'])           $doneHead = ags_fmt($lang->downloadadd[$add ? 'done_add_user' : 'done_remove_user'], $gbDone, (string)$done['who']);
        elseif (!empty($done['all'])) $doneHead = ags_fmt($lang->downloadadd[$add ? 'done_add_all' : 'done_remove_all'], $gbDone);
        else                          $doneHead = ags_fmt($lang->downloadadd[$add ? 'done_add_group' : 'done_remove_group'], $gbDone, (string)$done['who']);
    ?>
    <div class="dm-done mb-3"><i class="fa-solid fa-circle-check"></i>
        <div class="flex-grow-1">
            <div class="fw-bold"><?= e($doneHead) ?></div>
            <?php if ($done['mass']): ?>
            <div class="dm-muted"><?= e(ags_fmt($lang->downloadadd['done_mass_detail'], number_format((int)$done['n']), (string)$done['total'])) ?></div>
            <?php else: ?>
            <div class="dm-muted"><?= ags_fmt($lang->downloadadd['done_single_detail'],
                    e($done['old']), '<strong>' . e($done['new']) . '</strong>',
                    e($done['r_old']), '<strong>' . e($done['r_new']) . '</strong>') ?><?php if ($done['note'] !== ''): ?> <i class="fa-solid fa-note-sticky ms-1"></i> <?= e($done['note']) ?><?php endif; ?></div>
            <?php endif; ?>
        </div>
        <?php if (!$done['mass']): ?><a href="<?= e($BASEURL . '/' . get_profile_link((int)$done['id'])) ?>" class="btn btn-sm btn-outline-success px-3"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e($lang->downloadadd['btn_profile']) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Режим -->
    <div class="dm-mode mb-3" role="radiogroup" aria-label="<?= e($lang->downloadadd['aria_operation']) ?>">
        <input type="radio" name="dm_op" id="opAdd" value="add" <?= $op === 'add' ? 'checked' : '' ?>>
        <label for="opAdd"><span class="dm-sec-icon ic-green"><i class="fa-solid fa-plus"></i></span><span><b class="d-block"><?= e($lang->downloadadd['mode_add']) ?></b><small class="dm-muted"><?= e($lang->downloadadd['hint_mode_add']) ?></small></span></label>
        <input type="radio" name="dm_op" id="opRemove" value="remove" <?= $op === 'remove' ? 'checked' : '' ?>>
        <label for="opRemove"><span class="dm-sec-icon ic-red"><i class="fa-solid fa-minus"></i></span><span><b class="d-block"><?= e($lang->downloadadd['mode_remove']) ?></b><small class="dm-muted"><?= e($lang->downloadadd['hint_mode_remove']) ?></small></span></label>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ([
            ['fa-users',          'ic-blue',  $lang->downloadadd['kpi_users'],      number_format((int)($st['n'] ?? 0))],
            ['fa-download',       'ic-red',   $lang->downloadadd['kpi_downloaded'], mksize((float)($st['down'] ?? 0))],
            ['fa-upload',         'ic-green', $lang->downloadadd['kpi_uploaded'],   mksize((float)($st['up'] ?? 0))],
            ['fa-scale-balanced', 'ic-amber', $lang->downloadadd['kpi_ratio'],      dm_ratio((float)($st['up'] ?? 0), (float)($st['down'] ?? 0))],
        ] as [$ic, $cls, $label, $val]): ?>
        <div class="col-6 col-lg-3"><div class="dm-card dm-kpi"><span class="dm-kpi-icon <?= $cls ?>"><i class="fa-solid <?= $ic ?>"></i></span>
            <div><div class="dm-kpi-label"><?= e($label) ?></div><div class="dm-kpi-value"><?= $val ?></div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <!-- Один пользователь -->
        <div class="col-lg-6">
            <form method="post" action="<?= e($self) ?>" class="dm-card h-100" id="dmSingle" novalidate>
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="op" value="<?= $op ?>" class="dm-op-field">
                <div class="dm-sec-head"><span class="dm-sec-icon mode"><i class="fa-solid fa-user"></i></span>
                    <div><h2 class="dm-sec-title"><?= e($lang->downloadadd['sec_single']) ?></h2><div class="dm-muted"><?= e(ags_fmt($lang->downloadadd['sec_single_sub'], DM_MAX_SINGLE)) ?></div></div></div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <label for="username" class="form-label"><i class="fa-solid fa-user"></i><?= e($lang->downloadadd['lbl_username']) ?></label>
                        <input type="text" class="form-control" name="username" id="username" value="<?= e((string)($_POST['username'] ?? '')) ?>" placeholder="<?= e($lang->downloadadd['ph_username']) ?>" required maxlength="64" autocomplete="off">
                        <div class="dm-box mt-2" id="dmUser" hidden>
                            <i class="fa-solid fa-download text-body-secondary"></i>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="fw-bold" id="dmUName"></div>
                                <div class="dm-muted"><?= e($lang->downloadadd['lbl_downloaded']) ?> <span class="v" id="dmUNow"></span> → <span class="v res" id="dmUAfter"></span></div>
                                <div class="dm-muted"><?= e($lang->downloadadd['lbl_ratio']) ?> <span class="v" id="dmRNow"></span> → <span class="v" id="dmRAfter"></span> <span class="small">(<?= e($lang->downloadadd['lbl_uploaded']) ?> <span id="dmUUp"></span>)</span></div>
                                <div class="small text-danger" id="dmUInactive" hidden><i class="fa-solid fa-ban me-1"></i><?= e($lang->downloadadd['hint_inactive']) ?></div>
                            </div>
                        </div>
                        <div class="small text-body-secondary mt-2" id="dmUMissing" hidden><i class="fa-solid fa-user-slash me-1"></i><?= e($lang->downloadadd['hint_missing']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="downloaded" class="form-label"><i class="fa-solid fa-hashtag"></i><span class="dm-verb" data-add="<?= e($lang->downloadadd['lbl_amount_add']) ?>" data-remove="<?= e($lang->downloadadd['lbl_amount_remove']) ?>"><?= e($lang->downloadadd[$op === 'add' ? 'lbl_amount_add' : 'lbl_amount_remove']) ?></span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="downloaded" id="downloaded" min="1" max="<?= DM_MAX_SINGLE ?>" value="<?= e((string)($_POST['downloaded'] ?? '10')) ?>" required>
                            <span class="input-group-text"><?= $GB ?></span>
                        </div>
                        <div class="dm-chips" data-for="downloaded"><?php foreach ([1, 5, 10, 25, 50, 100] as $v): ?><button type="button" class="dm-chip" data-v="<?= $v ?>"><?= $v ?> <?= $GB ?></button><?php endforeach; ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="adminNote" class="form-label"><i class="fa-solid fa-note-sticky"></i><?= e($lang->downloadadd['lbl_note']) ?> <span class="fw-normal text-body-secondary"><?= e($lang->downloadadd['lbl_optional']) ?></span></label>
                        <input type="text" class="form-control" name="admin_note" id="adminNote" maxlength="255" value="<?= e((string)($_POST['admin_note'] ?? '')) ?>" placeholder="<?= e($lang->downloadadd['ph_note']) ?>">
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn dm-go px-4"><i class="fa-solid fa-check me-1"></i><span class="dm-verb" data-add="<?= e($lang->downloadadd['btn_single_add']) ?>" data-remove="<?= e($lang->downloadadd['btn_single_remove']) ?>"><?= e($lang->downloadadd[$op === 'add' ? 'btn_single_add' : 'btn_single_remove']) ?></span></button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Группа -->
        <div class="col-lg-6">
            <form method="post" action="<?= e($self) ?>" class="dm-card h-100" id="dmMass" novalidate>
                <input type="hidden" name="doit" value="yes">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <input type="hidden" name="op" value="<?= $op ?>" class="dm-op-field">
                <div class="dm-sec-head"><span class="dm-sec-icon mode"><i class="fa-solid fa-users"></i></span>
                    <div><h2 class="dm-sec-title"><?= e($lang->downloadadd['sec_group']) ?></h2><div class="dm-muted"><?= e($lang->downloadadd['sec_group_sub']) ?></div></div></div>
                <div class="p-3 p-md-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-7">
                            <label class="form-label"><i class="fa-solid fa-filter"></i><?= e($lang->downloadadd['lbl_group']) ?></label>
                            <div class="dm-group"><?= _selectbox_('', 'usergroup', true, $lang->downloadadd['opt_all_users'], $_POST['usergroup'] ?? '') ?></div>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="classamount"><i class="fa-solid fa-hashtag"></i><?= e($lang->downloadadd['lbl_per_user']) ?></label>
                            <select name="classamount" id="classamount" class="form-select" required>
                                <option value="0"><?= e($lang->downloadadd['opt_choose']) ?></option>
                                <?php for ($i = 1; $i <= DM_MAX_BULK; $i++): ?><option value="<?= $i ?>" <?= (int)($_POST['classamount'] ?? 10) === $i ? 'selected' : '' ?>><?= $i ?> <?= $GB ?></option><?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="dm-box mb-3">
                        <i class="fa-solid fa-calculator text-body-secondary"></i>
                        <div class="flex-grow-1"><div class="fw-semibold" id="dmGWho"><?= e($lang->downloadadd['opt_all_users']) ?></div><div class="dm-muted" id="dmGStat"><?= e($lang->downloadadd['hint_counting']) ?></div></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn dm-go px-4" id="dmMassBtn"><i class="fa-solid fa-bolt me-1"></i><span class="dm-verb" data-add="<?= e($lang->downloadadd['btn_group_add']) ?>" data-remove="<?= e($lang->downloadadd['btn_group_remove']) ?>"><?= e($lang->downloadadd[$op === 'add' ? 'btn_group_add' : 'btn_group_remove']) ?></span></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-12">
            <div class="dm-card overflow-hidden">
                <div class="dm-sec-head"><span class="dm-sec-icon ic-slate"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div><h2 class="dm-sec-title"><?= e($lang->downloadadd['sec_recent']) ?></h2><div class="dm-muted"><?= e($lang->downloadadd['sec_recent_sub']) ?></div></div></div>
                <?php if ($recent): ?>
                <ul class="dm-log">
                    <?php foreach ($recent as $r):
                        $t = (string)$r['txt'];
                        $isAdd = str_contains($t, ' added '); ?>
                    <li><i class="fa-solid <?= $isAdd ? 'fa-plus text-success' : 'fa-minus text-danger' ?>"></i>
                        <span class="flex-grow-1"><?= e($t) ?></span>
                        <span class="dm-muted text-nowrap"><?= my_datee('relative', (int)$r['added']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="dm-muted px-4 py-3"><?= e($lang->downloadadd['hint_no_recent']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
stdfoot();
