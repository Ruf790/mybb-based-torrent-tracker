<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_mkprettytime.php';

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Редирект со своим flash-сообщением, независимо от того, показывает ли
 * системная redirect() второй аргумент в динамических staff-инструментах.
 */
function ro_redirect(string $url, string $msg, string $type = 'success'): void
{
    $_SESSION['ro_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: ' . $url);
    exit();
}

// ── Настройки по типу вкладки ──────────────────────────────────────────────
$tab = ($_GET['tab'] ?? $_POST['table'] ?? 'requests') === 'offers' ? 'offers' : 'requests';

$TABLES = [
    'requests' => [
        'table'          => 'requests',
        'vote_table'     => 'request_votes',
        'vote_fk'        => 'request_id',
        'comment_table'  => 'request_comments',
        'comment_fk'     => 'request_id',
        'count_col'      => 'votes',
        'count_label'    => 'Votes',
        'statuses'       => ['open' => 'Open', 'filled' => 'Filled', 'cancelled' => 'Cancelled'],
        'status_class'   => ['open' => 'success', 'filled' => 'primary', 'cancelled' => 'secondary'],
        'status_icon'    => ['open' => 'fa-circle', 'filled' => 'fa-check', 'cancelled' => 'fa-times'],
        'public_view'    => '/requests.php?action=view&rid=',
        'has_bounty'     => true,
        'icon'           => 'fa-list-alt',
        'color'          => 'primary',
        'label'          => 'Requests',
    ],
    'offers' => [
        'table'          => 'offers',
        'vote_table'     => 'offer_votes',
        'vote_fk'        => 'offer_id',
        'comment_table'  => 'offer_comments',
        'comment_fk'     => 'offer_id',
        'count_col'      => 'requests',
        'count_label'    => 'Wants',
        'statuses'       => ['open' => 'Open', 'uploaded' => 'Uploaded', 'cancelled' => 'Cancelled'],
        'status_class'   => ['open' => 'success', 'uploaded' => 'primary', 'cancelled' => 'secondary'],
        'status_icon'    => ['open' => 'fa-circle', 'uploaded' => 'fa-upload', 'cancelled' => 'fa-times'],
        'public_view'    => '/offers.php?action=view&oid=',
        'has_bounty'     => false,
        'icon'           => 'fa-gift',
        'color'          => 'success',
        'label'          => 'Offers',
    ],
];

$cfg = $TABLES[$tab];
$admin_base = $_this_script_no_act . '?act=requests_offers&tab=' . $tab;

// ── Категории ───────────────────────────────────────────────────────────────
$cats = [];
$q = $db->sql_query("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[$r['id']] = $r['name'];

// ── Пометить как Filled/Uploaded (модалка с torrent_id) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ro_complete_id'])) {
    $cid        = (int)$_POST['ro_complete_id'];
    $torrent_id = (int)($_POST['torrent_id'] ?? 0);

    if (!$cid || !$torrent_id) {
        ro_redirect($admin_base, 'Missing torrent ID.', 'danger');
        exit();
    }
    if (!$db->num_rows($db->simple_select('torrents', 'id', "id='{$torrent_id}'"))) {
        ro_redirect($admin_base, "Torrent ID {$torrent_id} does not exist.", 'danger');
        exit();
    }

    if ($tab === 'requests') {
        $db->update_query('requests', [
            'status'     => 'filled',
            'filled_by'  => (int)$CURUSER['id'],
            'torrent_id' => $torrent_id,
            'filled_at'  => TIMENOW,
            'updated_at' => TIMENOW,
        ], "id='{$cid}'");
        write_log("Marked request #{$cid} as filled (torrent #{$torrent_id}) by " . $CURUSER['username']);
    } else {
        $db->update_query('offers', [
            'status'      => 'uploaded',
            'torrent_id'  => $torrent_id,
            'uploaded_at' => TIMENOW,
            'updated_at'  => TIMENOW,
        ], "id='{$cid}'");
        write_log("Marked offer #{$cid} as uploaded (torrent #{$torrent_id}) by " . $CURUSER['username']);
    }

    ro_redirect($admin_base, ucfirst(rtrim($cfg['label'], 's')) . " #{$cid} marked as complete.");
    exit();
}

// ── Bulk-действия ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bt  = $TABLES[$tab];
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
    $do  = (string)$_POST['bulk_action'];

    if (empty($ids)) {
        ro_redirect($admin_base, 'Nothing selected.', 'danger');
        exit();
    }

    $ids_sql = implode(',', $ids);

    if ($do === 'delete') {
        $db->sql_query("DELETE FROM {$bt['table']} WHERE id IN ({$ids_sql})");
        $db->sql_query("DELETE FROM {$bt['vote_table']} WHERE {$bt['vote_fk']} IN ({$ids_sql})");
        $db->sql_query("DELETE FROM {$bt['comment_table']} WHERE {$bt['comment_fk']} IN ({$ids_sql})");
        write_log('Bulk deleted ' . count($ids) . " {$tab} (IDs: {$ids_sql}) by " . $CURUSER['username']);
        ro_redirect($admin_base, count($ids) . ' ' . $cfg['label'] . ' deleted.');
        exit();
    }

    if (array_key_exists($do, $bt['statuses'])) {
        $status = $db->escape_string($do);
        $db->sql_query("UPDATE {$bt['table']} SET status = '{$status}', updated_at = '" . TIMENOW . "' WHERE id IN ({$ids_sql})");
        write_log("Bulk set status '{$do}' on " . count($ids) . " {$tab} (IDs: {$ids_sql}) by " . $CURUSER['username']);
        ro_redirect($admin_base, 'Status updated for ' . count($ids) . ' ' . $cfg['label'] . '.');
        exit();
    }

    ro_redirect($admin_base, 'Unknown action.', 'danger');
    exit();
}

// ── Одиночное удаление ─────────────────────────────────────────────────────
$do = $_GET['do'] ?? '';
$row_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($do === 'delete' && $row_id) {
    if (($_GET['sure'] ?? '') !== 'yes') {
        stdhead('Confirm Delete');
        enqueue_staff_assets();
        ?>
        <div class="container d-flex justify-content-center py-5">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4" style="max-width:480px;width:100%">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Are you sure you want to delete this <?= htmlspecialchars(rtrim($cfg['label'], 's')) ?>?</h5>
                    <p class="text-muted">ID #<?= $row_id ?> — this action cannot be undone.</p>
                    <div class="mt-3 d-flex justify-content-center gap-2">
                        <a href="<?= $admin_base ?>&do=delete&id=<?= $row_id ?>&sure=yes" class="btn btn-danger btn-sm rounded-pill px-4">
                            <i class="fas fa-trash me-1"></i>Yes, delete it
                        </a>
                        <a href="<?= $admin_base ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i>No, go back
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        stdfoot();
        exit();
    }
    $db->sql_query("DELETE FROM {$cfg['table']} WHERE id = {$row_id}");
    $db->sql_query("DELETE FROM {$cfg['vote_table']} WHERE {$cfg['vote_fk']} = {$row_id}");
    $db->sql_query("DELETE FROM {$cfg['comment_table']} WHERE {$cfg['comment_fk']} = {$row_id}");
    write_log("Deleted {$tab} #{$row_id} by " . $CURUSER['username']);
    ro_redirect($admin_base, ucfirst(rtrim($cfg['label'], 's')) . ' #' . $row_id . ' deleted.');
    exit();
}

// ── Одиночная смена статуса ────────────────────────────────────────────────
if ($do === 'setstatus' && $row_id && isset($_GET['status']) && array_key_exists($_GET['status'], $cfg['statuses'])) {
    $status = $db->escape_string($_GET['status']);
    $db->sql_query("UPDATE {$cfg['table']} SET status = '{$status}', updated_at = '" . TIMENOW . "' WHERE id = {$row_id}");
    write_log("Set status '{$status}' on {$tab} #{$row_id} by " . $CURUSER['username']);
    ro_redirect($admin_base, ucfirst(rtrim($cfg['label'], 's')) . ' #' . $row_id . ' status changed to ' . $status . '.');
    exit();
}

// ── Фильтры / сортировка / пагинация ───────────────────────────────────────
$filter_status = array_key_exists($_GET['status'] ?? '', $cfg['statuses']) ? $_GET['status'] : '';
$filter_cat    = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search        = trim((string)($_GET['q'] ?? ''));
$sort_options  = $cfg['has_bounty'] ? ['created_at', $cfg['count_col'], 'bounty'] : ['created_at', $cfg['count_col']];
$sort          = in_array($_GET['sort'] ?? '', $sort_options, true) ? $_GET['sort'] : 'created_at';
$perpage       = 25;
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perpage;

$where = [];
if ($filter_status !== '') $where[] = "t.status = '" . $db->escape_string($filter_status) . "'";
if ($filter_cat > 0)       $where[] = "t.category_id = {$filter_cat}";
if ($search !== '')        $where[] = "t.title LIKE '%" . $db->escape_string($search) . "%'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->fetch_field(
    $db->sql_query("SELECT COUNT(*) AS cnt FROM {$cfg['table']} t {$where_sql}"),
    'cnt'
);

$list_q = $db->sql_query("
    SELECT t.*, u.username, u.usergroup, u.avatar
    FROM {$cfg['table']} t
    LEFT JOIN users u ON u.id = t.user_id
    {$where_sql}
    ORDER BY t.{$sort} DESC
    LIMIT {$offset}, {$perpage}
");

$querystring = 'tab=' . $tab
    . '&status=' . urlencode($filter_status)
    . '&cat=' . $filter_cat
    . '&q=' . urlencode($search)
    . '&sort=' . $sort;

// ── KPI по текущей вкладке (для карточек над списком) ─────────────────────
$kpi_by_status = [];
$kpi_q = $db->sql_query("SELECT status, COUNT(*) AS cnt FROM {$cfg['table']} GROUP BY status");
while ($r = $db->fetch_array($kpi_q)) $kpi_by_status[$r['status']] = (int)$r['cnt'];
$kpi_total = array_sum($kpi_by_status);

if ($cfg['has_bounty']) {
    $kpi_extra_label = 'Total Bounty';
    $kpi_extra_value = number_format((float)$db->fetch_field($db->sql_query("SELECT COALESCE(SUM(bounty),0) AS s FROM {$cfg['table']}"), 's'), 1) . ' BP';
    $kpi_extra_icon  = 'fa-coins';
} else {
    $kpi_extra_label = 'Total ' . $cfg['count_label'];
    $kpi_extra_value = number_format((int)$db->fetch_field($db->sql_query("SELECT COALESCE(SUM({$cfg['count_col']}),0) AS s FROM {$cfg['table']}"), 's'));
    $kpi_extra_icon  = 'fa-hand-paper';
}

// ── Рендер ──────────────────────────────────────────────────────────────────
stdhead('Requests & Offers');
enqueue_staff_assets();

?>
<style>
    /* ============================================================
       АНИМАЦИИ
       ============================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    .animate-slide-down {
        animation: slideDown 0.3s ease forwards;
        opacity: 0;
    }

    .pulse-on-hover:hover {
        animation: pulse 0.4s ease;
    }

    /* ============================================================
       СТИЛИ
       ============================================================ */
    .ro-tabs .nav-link {
        border-radius: 12px 12px 0 0;
        font-weight: 600;
        padding: 0.6rem 1.5rem;
        transition: all 0.3s ease;
        color: var(--text-secondary);
        position: relative;
    }
    .ro-tabs .nav-link:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }
    .ro-tabs .nav-link.active {
        color: var(--text-primary);
        background: var(--bg-card);
        border-bottom-color: transparent;
    }
    .ro-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 50%;
        transform: translateX(-50%);
        width: 30px;
        height: 3px;
        background: var(--bs-primary, #0d6efd);
        border-radius: 3px;
    }
    .ro-tabs .nav-link .badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.5rem;
        margin-left: 0.4rem;
    }

    .ro-row {
        transition: all 0.2s ease;
        cursor: default;
    }
    .ro-row:hover {
        background: var(--bg-hover);
    }
    .ro-row td {
        vertical-align: middle;
        padding: 0.6rem 0.5rem;
        border-bottom-color: var(--border-color);
    }

    .ro-toolbar {
        background: var(--bg-light);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .ro-badge-count {
        min-width: 2.2rem;
        display: inline-block;
        font-weight: 600;
    }

    .ro-check {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: var(--bs-primary, #0d6efd);
    }

    .ro-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50rem;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .ro-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--bg-light);
    }
    .ro-avatar-placeholder {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-light);
        color: var(--text-muted);
        font-size: 0.6rem;
        font-weight: 600;
    }

    .ro-title-link {
        color: var(--text-primary);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }
    .ro-title-link:hover {
        color: var(--bs-primary, #0d6efd);
        text-decoration: underline;
    }

    .ro-empty-state {
        padding: 3rem 1rem;
        text-align: center;
    }
    .ro-empty-state i {
        font-size: 3rem;
        color: var(--text-muted);
        opacity: 0.3;
        margin-bottom: 1rem;
    }

    /* KPI-карточки */
    .ro-kpi {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 1rem 1.1rem;
        box-shadow: var(--shadow, 0 2px 10px rgba(0,0,0,.06));
        display: flex;
        align-items: center;
        gap: 0.9rem;
        height: 100%;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .ro-kpi:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg, 0 8px 24px rgba(0,0,0,.1));
    }
    .ro-kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }
    .ro-kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.1;
    }
    .ro-kpi-label {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    /* Карточка-обёртка списка (toolbar + таблица единым блоком) */
    .ro-card {
        background: var(--bg-card);
        border-radius: 16px;
        box-shadow: var(--shadow, 0 2px 10px rgba(0,0,0,.06));
    }
    .ro-card .ro-toolbar {
        border-radius: 16px 16px 0 0;
        border-bottom: 1px solid var(--border-color);
        margin: 0;
    }
    .ro-card .table-responsive {
        margin: 0;
        border-radius: 0 0 16px 16px;
    }
    .ro-card table {
        margin-bottom: 0;
    }
    .ro-card thead th {
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: .04em;
        color: var(--text-muted);
        border-bottom-width: 1px;
        background: var(--bg-light);
    }

    /* Dark theme adjustments */
    [data-bs-theme="dark"] .ro-tabs .nav-link {
        color: var(--text-secondary);
    }
    [data-bs-theme="dark"] .ro-tabs .nav-link.active {
        background: var(--bg-card);
        color: var(--text-primary);
    }
    [data-bs-theme="dark"] .ro-tabs .nav-link.active::after {
        background: #58a6ff;
    }
    [data-bs-theme="dark"] .ro-toolbar {
        background: var(--bg-light);
    }
    [data-bs-theme="dark"] .ro-row:hover {
        background: var(--bg-hover);
    }
    [data-bs-theme="dark"] .ro-kpi,
    [data-bs-theme="dark"] .ro-card {
        background: var(--bg-card);
    }
    [data-bs-theme="dark"] .ro-card thead th {
        background: var(--bg-light);
    }

    /* Кастомное меню действий: JS сам считает координаты и держит меню
       в границах экрана — обычный Bootstrap dropdown здесь ломается
       (открывается не в ту сторону и вылезает за край экрана). */
    .ro-actions { position: relative; display: inline-block; }
    .ro-actions-menu {
        display: none;
        position: fixed;
        margin: 0;
        z-index: 3000;
        min-width: 200px;
    }
    .ro-actions-menu.show { display: block; }

    /* Scrollbar */
    [data-bs-theme="dark"] ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-track {
        background: var(--bg-body);
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
        background: #30363d;
        border-radius: 4px;
    }
    [data-bs-theme="dark"] ::-webkit-scrollbar-thumb:hover {
        background: #484f58;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .ro-tabs .nav-link {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
        .ro-tabs .nav-link .badge {
            display: none;
        }
        .table-responsive td {
            font-size: 0.85rem;
        }
        .ro-title-link {
            font-size: 0.85rem;
        }
    }
    @media (max-width: 576px) {
        .ro-tabs .nav-link {
            padding: 0.3rem 0.5rem;
            font-size: 0.75rem;
        }
        .ro-tabs .nav-link i {
            margin-right: 0.2rem;
        }
        .ro-toolbar {
            flex-wrap: wrap;
        }
        .ro-toolbar select {
            min-width: 150px;
        }
        .ro-kpi-value {
            font-size: 1.1rem;
        }
        .ro-kpi-icon {
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
        }
    }
</style>

<div class="container mt-3">

    <?php if (!empty($_SESSION['ro_flash'])):
        $f = $_SESSION['ro_flash'];
        unset($_SESSION['ro_flash']);
        $toast_type = match ($f['type']) {
            'success' => 'success',
            'danger'  => 'error',
            'warning' => 'warning',
            default   => 'info',
        };
    ?>
    <script src="<?= $BASEURL ?>/scripts/toast.js"></script>
    <script>document.addEventListener("DOMContentLoaded",function(){ showToast(<?= json_encode($f['msg']) ?>,<?= json_encode($toast_type) ?>); });</script>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 animate-fade-in-up">
        <div>
            <h4 class="mb-0">
                <i class="fas fa-tasks me-2 text-primary"></i>
                Requests &amp; Offers
            </h4>
            <small class="text-muted">Manage all user requests and offers</small>
        </div>
        <div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4 animate-fade-in-up">
        <div class="col-6 col-md">
            <div class="ro-kpi">
                <div class="ro-kpi-icon bg-<?= $cfg['color'] ?> bg-opacity-10 text-<?= $cfg['color'] ?>">
                    <i class="fas <?= $cfg['icon'] ?>"></i>
                </div>
                <div>
                    <div class="ro-kpi-value"><?= number_format($kpi_total) ?></div>
                    <div class="ro-kpi-label">Total <?= $cfg['label'] ?></div>
                </div>
            </div>
        </div>
        <?php foreach ($cfg['statuses'] as $val => $label): ?>
        <div class="col-6 col-md">
            <div class="ro-kpi">
                <div class="ro-kpi-icon bg-<?= $cfg['status_class'][$val] ?> bg-opacity-10 text-<?= $cfg['status_class'][$val] ?>">
                    <i class="fas <?= $cfg['status_icon'][$val] ?>"></i>
                </div>
                <div>
                    <div class="ro-kpi-value"><?= number_format($kpi_by_status[$val] ?? 0) ?></div>
                    <div class="ro-kpi-label"><?= $label ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-md">
            <div class="ro-kpi">
                <div class="ro-kpi-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas <?= $kpi_extra_icon ?>"></i>
                </div>
                <div>
                    <div class="ro-kpi-value"><?= $kpi_extra_value ?></div>
                    <div class="ro-kpi-label"><?= $kpi_extra_label ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs ro-tabs mb-3 animate-fade-in-up">
        <?php foreach ($TABLES as $key => $t): 
            $count_all = (int)$db->fetch_field($db->sql_query("SELECT COUNT(*) FROM {$t['table']}"), 'COUNT(*)');
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?> pulse-on-hover"
               href="<?= $_this_script_no_act ?>?act=requests_offers&tab=<?= $key ?>">
                <i class="fas <?= $t['icon'] ?> me-1"></i>
                <?= $t['label'] ?>
                <span class="badge bg-<?= $t['color'] ?> bg-opacity-25 text-<?= $t['color'] ?>">
                    <?= number_format($count_all) ?>
                </span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Filters -->
    <form method="get" class="row g-2 align-items-center mb-3 animate-slide-down">
        <input type="hidden" name="act" value="requests_offers">
        <input type="hidden" name="tab" value="<?= $tab ?>">

        <div class="col-auto">
            <select name="status" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($cfg['statuses'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $filter_status === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-auto">
            <select name="cat" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                <option value="0">All categories</option>
                <?php foreach ($cats as $cid => $cname): ?>
                <option value="<?= $cid ?>" <?= $filter_cat === (int)$cid ? 'selected' : '' ?>><?= htmlspecialchars($cname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-auto">
            <select name="sort" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>
                    <i class="fas fa-clock me-1"></i>Newest first
                </option>
                <option value="<?= $cfg['count_col'] ?>" <?= $sort === $cfg['count_col'] ? 'selected' : '' ?>>
                    <i class="fas fa-chart-simple me-1"></i>Most <?= strtolower($cfg['count_label']) ?>
                </option>
                <?php if ($cfg['has_bounty']): ?>
                <option value="bounty" <?= $sort === 'bounty' ? 'selected' : '' ?>>
                    <i class="fas fa-coins me-1"></i>Highest bounty
                </option>
                <?php endif; ?>
            </select>
        </div>

        <div class="col-auto flex-grow-1" style="min-width:180px">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0 rounded-start-pill">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" name="q" class="form-control border-start-0 rounded-end-pill"
                       placeholder="Search title…" value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 pulse-on-hover">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
            <a href="<?= $_this_script_no_act ?>?act=requests_offers&tab=<?= $tab ?>"
               class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover">
                <i class="fas fa-undo me-1"></i>Reset
            </a>
        </div>

        <div class="col-auto ms-auto">
            <small class="text-muted">
                <i class="fas fa-database me-1"></i><?= number_format($total) ?> total
            </small>
        </div>
    </form>

    <!-- Bulk actions + Table -->
    <form method="post" id="ro-bulk-form">
        <input type="hidden" name="table" value="<?= $tab ?>">

        <div class="ro-card animate-fade-in-up">
        <div class="ro-toolbar d-flex align-items-center gap-2 flex-wrap mb-2 animate-slide-down">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <i class="fas fa-tasks text-muted"></i>
                <select name="bulk_action" class="form-select form-select-sm" style="max-width:220px; border-radius:50rem;">
                    <option value="">Bulk action…</option>
                    <?php foreach ($cfg['statuses'] as $val => $label): ?>
                    <option value="<?= $val ?>">Set status: <?= $label ?></option>
                    <?php endforeach; ?>
                    <option value="delete">🗑️ Delete selected</option>
                </select>
                <button type="submit" class="btn btn-sm btn-dark rounded-pill px-4" id="ro-apply-btn" disabled
                        onclick="return roConfirmBulk(this.form)">
                    <i class="fas fa-play me-1"></i>Apply
                </button>
            </div>
            <div>
                <span class="badge bg-light text-dark border rounded-pill px-3 py-2" id="ro-selected-count">
                    <i class="fas fa-check-circle me-1"></i>0 selected
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:36px">
                            <input type="checkbox" id="ro-select-all" class="ro-check">
                        </th>
                        <th style="width:50px">#</th>
                        <th>Title</th>
                        <th style="width:140px">User</th>
                        <th style="width:120px">Category</th>
                        <th style="width:110px">Status</th>
                        <th style="width:70px" class="text-center"><?= $cfg['count_label'] ?></th>
                        <?php if ($cfg['has_bounty']): ?>
                        <th style="width:90px" class="text-end">Bounty</th>
                        <?php endif; ?>
                        <th style="width:120px">Created</th>
                        <th style="width:80px" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $count = 0; while ($row = $db->fetch_array($list_q)): $count++; ?>
                    <tr class="ro-row animate-fade-in-up" style="animation-delay: <?= min($count * 0.03, 0.5) ?>s">
                        <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="ro-check"></td>
                        <td><span class="fw-semibold text-muted">#<?= $row['id'] ?></span></td>
                        <td>
                            <a href="<?= $BASEURL . $cfg['public_view'] . $row['id'] ?>"
                               class="ro-title-link" target="_blank"
                               title="<?= htmlspecialchars($row['title']) ?>">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                            <?php if (!empty($row['year'])): ?>
                            <small class="text-muted">(<?= (int)$row['year'] ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($row['avatar'])): ?>
                                <img src="<?= $BASEURL ?>/<?= htmlspecialchars($row['avatar']) ?>"
                                     class="ro-avatar" alt="<?= htmlspecialchars($row['username'] ?? '') ?>">
                                <?php else: ?>
                                <div class="ro-avatar-placeholder">
                                    <?= strtoupper(substr($row['username'] ?? 'U', 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                                <span class="text-truncate" style="max-width:90px">
                                    <?= format_name($row['username'], $row['usergroup']) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border rounded-pill px-2">
                                <?= htmlspecialchars($cats[$row['category_id']] ?? '—') ?>
                            </span>
                        </td>
                        <td>
                            <span class="ro-status-badge bg-<?= $cfg['status_class'][$row['status']] ?? 'secondary' ?> bg-opacity-25 text-<?= $cfg['status_class'][$row['status']] ?? 'secondary' ?>">
                                <i class="fas <?= $cfg['status_icon'][$row['status']] ?? 'fa-circle' ?>" style="font-size:0.5rem"></i>
                                <?= $cfg['statuses'][$row['status']] ?? htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="ro-badge-count fw-bold text-<?= $cfg['color'] ?>">
                                <?= (int)$row[$cfg['count_col']] ?>
                            </span>
                        </td>
                        <?php if ($cfg['has_bounty']): ?>
                        <td class="text-end">
                            <?php if ((float)$row['bounty'] > 0): ?>
                            <span class="text-warning fw-semibold">
                                <i class="fas fa-coins me-1"></i><?= number_format((float)$row['bounty'], 1) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <small class="text-muted" title="<?= date('Y-m-d H:i:s', (int)$row['created_at']) ?>">
                                <?= mkprettytime(TIMENOW - (int)$row['created_at']) ?> ago
                            </small>
                        </td>
                        <td class="text-end">
                            <div class="ro-actions">
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 ro-actions-btn" type="button">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end ro-actions-menu">
                                    <li><a class="dropdown-item" href="<?= $BASEURL . $cfg['public_view'] . $row['id'] ?>" target="_blank"><i class="fas fa-eye me-2"></i>View</a></li>
                                    <?php foreach ($cfg['statuses'] as $val => $label): if ($val === $row['status']) continue; ?>
                                    <?php if ($val === 'filled' || $val === 'uploaded'): ?>
                                    <li>
                                        <button type="button" class="dropdown-item ro-mark-complete" data-id="<?= $row['id'] ?>" data-bs-toggle="modal" data-bs-target="#roCompleteModal">
                                            <i class="fas fa-upload me-2"></i>Mark as <?= $label ?>
                                        </button>
                                    </li>
                                    <?php else: ?>
                                    <li><a class="dropdown-item" href="<?= $admin_base ?>&do=setstatus&id=<?= $row['id'] ?>&status=<?= $val ?>"><i class="fas fa-flag me-2"></i>Mark as <?= $label ?></a></li>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?= $admin_base ?>&do=delete&id=<?= $row['id'] ?>"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if ($count === 0): ?>
                    <tr>
                        <td colspan="<?= $cfg['has_bounty'] ? 10 : 9 ?>">
                            <div class="ro-empty-state">
                                <i class="fas <?= $cfg['icon'] ?>"></i>
                                <h6 class="text-muted">No <?= strtolower($cfg['label']) ?> found</h6>
                                <p class="text-muted small">Try adjusting your filters or search query</p>
                                <a href="<?= $_this_script_no_act ?>?act=requests_offers&tab=<?= $tab ?>"
                                   class="btn btn-sm btn-outline-secondary rounded-pill px-4 mt-2">
                                    <i class="fas fa-undo me-1"></i>Reset filters
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </form>

    <!-- Modal: Mark as Filled/Uploaded (нужен torrent_id) -->
    <div class="modal fade" id="roCompleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Mark as <?= $cfg['has_bounty'] ? 'Filled' : 'Uploaded' ?>
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Enter the torrent ID that was uploaded for this <?= rtrim($cfg['label'], 's') ?>.</p>
                    <form method="post" action="<?= $admin_base ?>">
                        <input type="hidden" name="ro_complete_id" id="roCompleteId" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Torrent ID</label>
                            <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="e.g. 12345">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total > $perpage): ?>
    <div class="mt-3 d-flex justify-content-center animate-fade-in-up">
        <?= multipage($total, $perpage, $page, $_this_script_no_act . '?act=requests_offers&' . $querystring . '&page={page}') ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';

    const selectAll = document.getElementById('ro-select-all');
    const checks    = () => document.querySelectorAll('.ro-check');
    const countEl   = document.getElementById('ro-selected-count');
    const applyBtn  = document.getElementById('ro-apply-btn');

    function updateCount() {
        const n = Array.from(checks()).filter(c => c.checked).length;
        countEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>' + n + ' selected';
        applyBtn.disabled = n === 0;
        // Анимация счетчика
        countEl.style.transition = 'all 0.2s ease';
        countEl.style.transform = 'scale(1.1)';
        setTimeout(() => { countEl.style.transform = 'scale(1)'; }, 200);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checks().forEach(c => c.checked = selectAll.checked);
            updateCount();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('ro-check')) {
            updateCount();
            // Обновляем select-all
            if (selectAll) {
                const all = checks();
                const checked = Array.from(all).filter(c => c.checked).length;
                selectAll.checked = all.length > 0 && checked === all.length;
                selectAll.indeterminate = checked > 0 && checked < all.length;
            }
        }
    });

    // Инициализация
    updateCount();

    // Подтверждение bulk действия
    window.roConfirmBulk = function(form) {
        const action = form.bulk_action.value;
        const n = Array.from(checks()).filter(c => c.checked).length;
        if (!action) {
            alert('Please choose a bulk action first.');
            return false;
        }
        if (action === 'delete') {
            return confirm('⚠️ Delete ' + n + ' selected item(s)?\nThis will also remove their votes/comments and cannot be undone!');
        }
        return confirm('Apply "' + action + '" to ' + n + ' selected item(s)?');
    };

    // Highlight checked rows
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('ro-check')) {
            const row = e.target.closest('tr');
            if (row) {
                row.style.background = e.target.checked ? 'var(--bg-hover)' : '';
            }
        }
    });

    // ── Кастомное меню действий ──────────────────────────────────────
    // Переносим открытое меню в <body> и считаем координаты сами —
    // Bootstrap-дропдаун в этой сборке открывается не в ту сторону
    // и вылезает за физический край экрана внутри table-responsive.
    let openMenu = null;

    function closeOpenMenu() {
        if (!openMenu) return;
        const { menu, placeholder } = openMenu;
        menu.classList.remove('show');
        placeholder.replaceWith(menu);
        openMenu = null;
    }

    function positionMenu(btn, menu) {
        const rect = btn.getBoundingClientRect();
        menu.style.visibility = 'hidden';
        menu.classList.add('show');
        const menuWidth  = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;

        let left = rect.right - menuWidth;   // выравниваем по правому краю кнопки
        let top  = rect.bottom + 4;

        if (left < 8) left = 8;
        if (left + menuWidth > window.innerWidth - 8) left = window.innerWidth - menuWidth - 8;

        if (top + menuHeight > window.innerHeight - 8) {
            top = rect.top - menuHeight - 4;   // не хватает места снизу — открыть вверх
        }

        menu.style.left = left + 'px';
        menu.style.top  = top + 'px';
        menu.style.visibility = '';
    }

    document.addEventListener('click', function(e) {
        const markBtn = e.target.closest('.ro-mark-complete');
        if (markBtn) {
            document.getElementById('roCompleteId').value = markBtn.dataset.id;
            closeOpenMenu();
        }

        const btn = e.target.closest('.ro-actions-btn');

        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const wrap = btn.closest('.ro-actions');
            const menu = wrap.querySelector('.ro-actions-menu');
            const alreadyOpen = openMenu && openMenu.menu === menu;

            closeOpenMenu();
            if (alreadyOpen) return;

            const placeholder = document.createComment('ro-menu-placeholder');
            menu.replaceWith(placeholder);
            document.body.appendChild(menu);
            positionMenu(btn, menu);
            openMenu = { menu, placeholder };
            return;
        }

        if (openMenu && !e.target.closest('.ro-actions-menu')) {
            closeOpenMenu();
        }
    });

    window.addEventListener('scroll', closeOpenMenu, true);
    window.addEventListener('resize', closeOpenMenu);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeOpenMenu();
    });

})();
</script>

<?php
stdfoot();