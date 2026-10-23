<?php
declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'notifications.php');

require_once './global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_notify.php';

// Lang: $L raw (toasts, JS), $E escaped for HTML (lang values are plain text)
$L = notify_lang();
$E = array_map(fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $L);

if (empty($CURUSER['id'])) {
    redirect('member.php?action=login');
}

$uid = (int)$CURUSER['id'];

// ── Open one: mark as read and go to its target ─────────────────────────────
if (isset($_GET['go'])) {
    $link = notify_mark_read($uid, (int)$_GET['go']);
    header('Location: ' . ($link ? $BASEURL . '/' . $link : 'notifications.php'));
    exit;
}

// ── POST: mark all / delete read (PRG) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        header('Location: notifications.php?msg=csrf'); exit;
    }
    if (isset($_POST['mark_all'])) {
        notify_mark_all($uid);
        header('Location: notifications.php?msg=marked'); exit;
    }
    if (isset($_POST['delete_read'])) {
        notify_delete_read($uid);
        header('Location: notifications.php?msg=deleted'); exit;
    }
}

[$success, $error] = match ($_GET['msg'] ?? '') {
    'marked'  => [$L['flash_marked'], ''],
    'deleted' => [$L['flash_deleted'], ''],
    'csrf'    => ['', $L['err_csrf']],
    default   => ['', ''],
};

// ── Data ────────────────────────────────────────────────────────────────────
$filter  = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$total   = notify_count($uid);
$unread  = notify_resync($uid);   // real count + fixes users.notif_unread if it drifted
$shown   = $filter === 'unread' ? $unread : $total;

$perpage = max(1, (int)($ts_perpage ?? 20));
$pages   = max(1, (int)ceil($shown / $perpage));
// get_input() merges GET and POST: the pager's "Jump to page" form POSTs the number
$page    = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $pages);

$items     = array_map('notify_render', notify_fetch($uid, $perpage, ($page - 1) * $perpage, $filter === 'unread'));
$multipage = multipage($shown, $perpage, $page, 'notifications.php?filter=' . $filter . '&page={page}');

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

stdhead($L['page_title']);

$loadSwal = !defined('AGS_SWAL_LOADED');
if ($loadSwal) {
    define('AGS_SWAL_LOADED', true);
}
?>
<?php if (!defined('AGS_NOTIF_ASSETS')): /* the header bell already printed them */ ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/notifications.css?ver=<?= NOTIFY_JS_VER ?>">
<?php endif; ?>
<?php if ($loadSwal): ?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<?php endif; ?>

<?php if ($error !== ''): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,'error'));</script>
<?php endif; ?>
<?php if ($success !== ''): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($success, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,'success'));</script>
<?php endif; ?>

<div class="container mt-3">

    <!-- Header -->
    <div class="ntf-header">
        <div class="ntf-header-icon"><i class="fa-solid fa-bell"></i></div>
        <div class="flex-grow-1">
            <h1 class="ntf-title"><?= $E['page_title'] ?></h1>
            <div class="ntf-subtitle"><?= $E['page_subtitle'] ?></div>
        </div>
        <div class="ntf-actions">
            <form method="post" action="notifications.php" class="d-inline">
                <input type="hidden" name="my_post_key" value="<?= $h((string)$mybb->post_code) ?>">
                <button type="submit" name="mark_all" value="1" class="btn btn-sm btn-primary rounded-pill px-3"<?= $unread ? '' : ' disabled' ?>>
                    <i class="fa-solid fa-check-double me-1"></i><?= $E['btn_mark_all'] ?>
                </button>
            </form>
            <form method="post" action="notifications.php" class="d-inline" data-ntf-confirm>
                <input type="hidden" name="my_post_key" value="<?= $h((string)$mybb->post_code) ?>">
                <button type="submit" name="delete_read" value="1" class="btn btn-sm btn-outline-danger rounded-pill px-3"<?= ($total - $unread) ? '' : ' disabled' ?>>
                    <i class="fa-solid fa-trash-can me-1"></i><?= $E['btn_delete_read'] ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-pills ntf-tabs">
        <li class="nav-item">
            <a class="nav-link<?= $filter === 'all' ? ' active' : '' ?>" href="notifications.php">
                <?= $E['tab_all'] ?> <span class="ntf-count"><?= $total ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $filter === 'unread' ? ' active' : '' ?>" href="notifications.php?filter=unread">
                <?= $E['tab_unread'] ?> <span class="ntf-count"><?= $unread ?></span>
            </a>
        </li>
    </ul>

    <!-- List -->
    <div class="ntf-card">
        <?php if (!$items): ?>
            <div class="ntf-empty">
                <i class="fa-regular fa-bell-slash"></i>
                <div><?= $filter === 'unread' ? $E['lbl_empty_unread'] : $E['lbl_empty'] ?></div>
            </div>
        <?php else: ?>
            <?php foreach ($items as $n): ?>
            <a class="ntf-item<?= $n['read'] ? '' : ' is-unread' ?>" href="<?= $h($n['url']) ?>">
                <span class="ntf-icon text-<?= $h($n['color']) ?>-emphasis bg-<?= $h($n['color']) ?>-subtle">
                    <i class="fa-solid <?= $h($n['icon']) ?>"></i>
                </span>
                <span class="ntf-body">
                    <span class="ntf-text"><?= $h($n['text']) ?></span>
                    <span class="ntf-time" title="<?= $h($n['date']) ?>"><?= $h($n['time']) ?></span>
                </span>
                <?php if (!$n['read']): ?><span class="ntf-dot" title="<?= $E['tip_unread'] ?>"></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="mt-3"><?= $multipage ?></div>
    <?php endif; ?>

</div>

<?php if (!defined('AGS_NOTIF_ASSETS')): ?>
<script>window.AGS_NOTIF_LANG = <?= json_encode(notify_js_lang(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/scripts/notifications.js?ver=<?= NOTIFY_JS_VER ?>" defer></script>
<?php endif; ?>

<?php stdfoot(); ?>
