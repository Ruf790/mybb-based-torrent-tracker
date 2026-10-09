<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_mkprettytime.php';

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

global $lang;
$lang->load('requests_offers');

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Редирект со своим flash-сообщением (PRG: F5 после действия ничего не повторяет).
 */
function ro_redirect(string $url, string $msg, string $type = 'success'): never
{
    $_SESSION['ro_flash'] = ['msg' => $msg, 'type' => $type];
    header('Location: ' . $url);
    exit();
}

/**
 * Мягкая цветовая пара из Bootstrap 5.3 (bg-subtle / text-emphasis),
 * автоматически работает и в тёмной теме. $c — только из конфига ниже.
 */
function ro_tone(string $c): string
{
    return "--ro-bg:var(--bs-{$c}-bg-subtle);--ro-fg:var(--bs-{$c}-text-emphasis);--ro-solid:var(--bs-{$c})";
}

/** Экранирование чистого текста (ланг, БД) для HTML и атрибутов. */
function ro_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('ags_fmt')) {
    /**
     * Подстановка {1}, {2}… в строку из ланга.
     * $lang->load() превращает {1} в %1$s — поддерживаем оба формата.
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

/**
 * Форма существительного для числа: [one, few, many].
 * ru: 1 запрос / 2 запроса / 5 запросов; остальные языки: one / many.
 */
function ro_plural(int $n, array $forms, string $locale): string
{
    [$one, $few, $many] = $forms;
    if ($locale === 'ru') {
        $m10  = abs($n) % 10;
        $m100 = abs($n) % 100;
        if ($m10 === 1 && $m100 !== 11) return $one;
        if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return $few;
        return $many;
    }
    return $n === 1 ? $one : $many;
}

// ── Настройки по типу вкладки ──────────────────────────────────────────────
$tab = ($_GET['tab'] ?? $_POST['table'] ?? 'requests') === 'offers' ? 'offers' : 'requests';

$TABLES = [
    'requests' => [
        'table'           => 'requests',
        'vote_table'      => 'request_votes',
        'vote_fk'         => 'request_id',
        'comment_table'   => 'request_comments',
        'comment_fk'      => 'request_id',
        'count_col'       => 'votes',
        'count_label'     => $lang->requests_offers['lbl_votes'],
        'count_icon'      => 'fa-thumbs-up',
        'statuses'        => [
            'open'      => $lang->requests_offers['opt_req_open'],
            'filled'    => $lang->requests_offers['opt_req_filled'],
            'cancelled' => $lang->requests_offers['opt_req_cancelled'],
        ],
        'status_class'    => ['open' => 'success', 'filled' => 'primary', 'cancelled' => 'secondary'],
        'status_icon'     => ['open' => 'fa-circle-dot', 'filled' => 'fa-circle-check', 'cancelled' => 'fa-ban'],
        'complete_status' => 'filled',
        'public_view'     => '/requests.php?action=view&rid=',
        'has_bounty'      => true,
        'icon'            => 'fa-clipboard-list',
        'color'           => 'primary',
        'label'           => $lang->requests_offers['lbl_tab_requests'],
        'singular'        => $lang->requests_offers['th_req_item'],
        'noun'            => [
            $lang->requests_offers['js_req_noun_one'],
            $lang->requests_offers['js_req_noun_few'],
            $lang->requests_offers['js_req_noun_many'],
        ],
        'txt'             => [
            'flash_done'      => $lang->requests_offers['flash_req_completed'],
            'flash_deleted'   => $lang->requests_offers['flash_req_deleted'],
            'flash_status'    => $lang->requests_offers['flash_req_status'],
            'kpi_total'       => $lang->requests_offers['kpi_req_total'],
            'kpi_open_sub'    => $lang->requests_offers['kpi_req_open_sub'],
            'kpi_done'        => $lang->requests_offers['kpi_req_done'],
            'sort_count'      => $lang->requests_offers['opt_sort_votes'],
            'empty_filtered'  => $lang->requests_offers['empty_req_filtered'],
            'empty_none'      => $lang->requests_offers['empty_req_none'],
            'empty_none_hint' => $lang->requests_offers['empty_req_none_hint'],
            'modal_title'     => $lang->requests_offers['modal_req_title'],
            'modal_label'     => $lang->requests_offers['modal_req_label'],
        ],
    ],
    'offers' => [
        'table'           => 'offers',
        'vote_table'      => 'offer_votes',
        'vote_fk'         => 'offer_id',
        'comment_table'   => 'offer_comments',
        'comment_fk'      => 'offer_id',
        'count_col'       => 'requests',
        'count_label'     => $lang->requests_offers['lbl_wants'],
        'count_icon'      => 'fa-hand',
        'statuses'        => [
            'open'      => $lang->requests_offers['opt_off_open'],
            'uploaded'  => $lang->requests_offers['opt_off_uploaded'],
            'cancelled' => $lang->requests_offers['opt_off_cancelled'],
        ],
        'status_class'    => ['open' => 'success', 'uploaded' => 'primary', 'cancelled' => 'secondary'],
        'status_icon'     => ['open' => 'fa-circle-dot', 'uploaded' => 'fa-cloud-arrow-up', 'cancelled' => 'fa-ban'],
        'complete_status' => 'uploaded',
        'public_view'     => '/offers.php?action=view&oid=',
        'has_bounty'      => false,
        'icon'            => 'fa-gift',
        'color'           => 'success',
        'label'           => $lang->requests_offers['lbl_tab_offers'],
        'singular'        => $lang->requests_offers['th_off_item'],
        'noun'            => [
            $lang->requests_offers['js_off_noun_one'],
            $lang->requests_offers['js_off_noun_few'],
            $lang->requests_offers['js_off_noun_many'],
        ],
        'txt'             => [
            'flash_done'      => $lang->requests_offers['flash_off_completed'],
            'flash_deleted'   => $lang->requests_offers['flash_off_deleted'],
            'flash_status'    => $lang->requests_offers['flash_off_status'],
            'kpi_total'       => $lang->requests_offers['kpi_off_total'],
            'kpi_open_sub'    => $lang->requests_offers['kpi_off_open_sub'],
            'kpi_done'        => $lang->requests_offers['kpi_off_done'],
            'sort_count'      => $lang->requests_offers['opt_sort_wants'],
            'empty_filtered'  => $lang->requests_offers['empty_off_filtered'],
            'empty_none'      => $lang->requests_offers['empty_off_none'],
            'empty_none_hint' => $lang->requests_offers['empty_off_none_hint'],
            'modal_title'     => $lang->requests_offers['modal_off_title'],
            'modal_label'     => $lang->requests_offers['modal_off_label'],
        ],
    ],
];

$cfg        = $TABLES[$tab];
$txt        = $cfg['txt'];
$locale     = (string)$lang->requests_offers['js_locale'];
$admin_base = $_this_script_no_act . '?act=requests_offers&tab=' . $tab;
$post_key   = $mybb->post_code; // CSRF-токен для всех POST-форм этой страницы

// ── Категории ───────────────────────────────────────────────────────────────
$cats = [];
$q = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[(int)$r['id']] = (string)$r['name'];

// ── Пометить как Filled/Uploaded (модалка с torrent_id) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ro_complete_id'])) {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        ro_redirect($admin_base, $lang->requests_offers['flash_security'], 'danger');
    }

    $cid        = (int)$_POST['ro_complete_id'];
    $torrent_id = (int)($_POST['torrent_id'] ?? 0);

    if (!$cid || !$torrent_id) {
        ro_redirect($admin_base, $lang->requests_offers['flash_no_torrent_id'], 'danger');
    }
    $torrent_check = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$torrent_id]);
    if (!$db->num_rows($torrent_check)) {
        ro_redirect($admin_base, ags_fmt($lang->requests_offers['flash_torrent_missing'], $torrent_id), 'danger');
    }

    if ($tab === 'requests') {
        $db->sql_query_prepared(
            "UPDATE requests SET status = ?, filled_by = ?, torrent_id = ?, filled_at = ?, updated_at = ? WHERE id = ?",
            ['filled', (int)$CURUSER['id'], $torrent_id, TIMENOW, TIMENOW, $cid]
        );
        write_log("Marked request #{$cid} as filled (torrent #{$torrent_id}) by " . $CURUSER['username']);
    } else {
        $db->sql_query_prepared(
            "UPDATE offers SET status = ?, torrent_id = ?, uploaded_at = ?, updated_at = ? WHERE id = ?",
            ['uploaded', $torrent_id, TIMENOW, TIMENOW, $cid]
        );
        write_log("Marked offer #{$cid} as uploaded (torrent #{$torrent_id}) by " . $CURUSER['username']);
    }

    ro_redirect($admin_base, ags_fmt($txt['flash_done'], $cid));
}

// ── Одиночные действия (удаление / смена статуса) — только POST ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ro_do'])) {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        ro_redirect($admin_base, $lang->requests_offers['flash_security'], 'danger');
    }

    $do     = (string)$_POST['ro_do'];
    $row_id = (int)($_POST['id'] ?? 0);

    if (!$row_id) {
        ro_redirect($admin_base, $lang->requests_offers['flash_nothing_selected'], 'danger');
    }

    if ($do === 'delete') {
        $db->sql_query_prepared("DELETE FROM {$cfg['table']} WHERE id = ?", [$row_id]);
        $db->sql_query_prepared("DELETE FROM {$cfg['vote_table']} WHERE {$cfg['vote_fk']} = ?", [$row_id]);
        $db->sql_query_prepared("DELETE FROM {$cfg['comment_table']} WHERE {$cfg['comment_fk']} = ?", [$row_id]);
        write_log("Deleted {$tab} #{$row_id} by " . $CURUSER['username']);
        ro_redirect($admin_base, ags_fmt($txt['flash_deleted'], $row_id));
    }

    $status = (string)($_POST['status'] ?? '');
    if ($do === 'setstatus' && array_key_exists($status, $cfg['statuses'])) {
        $db->sql_query_prepared("UPDATE {$cfg['table']} SET status = ?, updated_at = ? WHERE id = ?", [$status, TIMENOW, $row_id]);
        write_log("Set status '{$status}' on {$tab} #{$row_id} by " . $CURUSER['username']);
        ro_redirect($admin_base, ags_fmt($txt['flash_status'], $row_id, $cfg['statuses'][$status]));
    }

    ro_redirect($admin_base, $lang->requests_offers['flash_unknown_action'], 'danger');
}

// ── Bulk-действия ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        ro_redirect($admin_base, $lang->requests_offers['flash_security'], 'danger');
    }

    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $do  = (string)$_POST['bulk_action'];

    if (empty($ids)) {
        ro_redirect($admin_base, $lang->requests_offers['flash_nothing_selected'], 'danger');
    }

    $ids_sql      = implode(',', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $n_ids        = count($ids);
    $n_noun       = ro_plural($n_ids, $cfg['noun'], $locale);

    if ($do === 'delete') {
        $db->sql_query_prepared("DELETE FROM {$cfg['table']} WHERE id IN ({$placeholders})", $ids);
        $db->sql_query_prepared("DELETE FROM {$cfg['vote_table']} WHERE {$cfg['vote_fk']} IN ({$placeholders})", $ids);
        $db->sql_query_prepared("DELETE FROM {$cfg['comment_table']} WHERE {$cfg['comment_fk']} IN ({$placeholders})", $ids);
        write_log('Bulk deleted ' . count($ids) . " {$tab} (IDs: {$ids_sql}) by " . $CURUSER['username']);
        ro_redirect($admin_base, ags_fmt($lang->requests_offers['flash_bulk_deleted'], $n_ids, $n_noun));
    }

    if (array_key_exists($do, $cfg['statuses'])) {
        $params = array_merge([$do, TIMENOW], $ids);
        $db->sql_query_prepared("UPDATE {$cfg['table']} SET status = ?, updated_at = ? WHERE id IN ({$placeholders})", $params);
        write_log("Bulk set status '{$do}' on " . count($ids) . " {$tab} (IDs: {$ids_sql}) by " . $CURUSER['username']);
        ro_redirect($admin_base, ags_fmt($lang->requests_offers['flash_bulk_status'], $n_ids, $n_noun, $cfg['statuses'][$do]));
    }

    ro_redirect($admin_base, $lang->requests_offers['flash_unknown_action'], 'danger');
}

// ── Фильтры / сортировка / пагинация ───────────────────────────────────────
$filter_status = array_key_exists((string)($_GET['status'] ?? ''), $cfg['statuses']) ? (string)$_GET['status'] : '';
$filter_cat    = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search        = trim((string)($_GET['q'] ?? ''));
$sort_options  = $cfg['has_bounty'] ? ['created_at', $cfg['count_col'], 'bounty'] : ['created_at', $cfg['count_col']];
$sort          = in_array($_GET['sort'] ?? '', $sort_options, true) ? (string)$_GET['sort'] : 'created_at';
$perpage       = 25;
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * $perpage;
$filters_on    = $filter_status !== '' || $filter_cat > 0 || $search !== '';

$where = [];
$where_params = [];
if ($filter_status !== '') { $where[] = 't.status = ?';      $where_params[] = $filter_status; }
if ($filter_cat > 0)       { $where[] = 't.category_id = ?'; $where_params[] = $filter_cat; }
if ($search !== '')        { $where[] = 't.title LIKE ?';    $where_params[] = '%' . $search . '%'; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->fetch_field(
    $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM {$cfg['table']} t {$where_sql}", $where_params),
    'cnt'
);

$list_q = $db->sql_query_prepared("
    SELECT t.*, u.username, u.avatar, u.usergroup, u.displaygroup
    FROM {$cfg['table']} t
    LEFT JOIN users u ON u.id = t.user_id
    {$where_sql}
    ORDER BY t.{$sort} DESC
    LIMIT ?, ?
", array_merge($where_params, [$offset, $perpage]));

/** Ссылка на эту страницу с текущими фильтрами и заменой части параметров. */
$ro_url = static function (array $over = []) use ($_this_script_no_act, $tab, $filter_status, $filter_cat, $search, $sort): string {
    $p = array_merge(
        ['act' => 'requests_offers', 'tab' => $tab, 'status' => $filter_status, 'cat' => $filter_cat, 'q' => $search, 'sort' => $sort],
        $over
    );
    $p = array_filter($p, static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return $_this_script_no_act . '?' . http_build_query($p);
};

// ── Счётчики вкладок + KPI ─────────────────────────────────────────────────
$tab_counts = [];
foreach ($TABLES as $key => $t) {
    $tab_counts[$key] = (int)$db->fetch_field($db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM {$t['table']}"), 'cnt');
}

$kpi_by_status = [];
$kpi_q = $db->sql_query_prepared("SELECT status, COUNT(*) AS cnt FROM {$cfg['table']} GROUP BY status");
while ($r = $db->fetch_array($kpi_q)) $kpi_by_status[(string)$r['status']] = (int)$r['cnt'];
$kpi_total = array_sum($kpi_by_status);
$pct = static fn(int $n): int => $kpi_total > 0 ? (int)round($n * 100 / $kpi_total) : 0;

$done_key   = $cfg['complete_status'];
$kpi_open   = $kpi_by_status['open'] ?? 0;
$kpi_done   = $kpi_by_status[$done_key] ?? 0;
$kpi_cancel = $kpi_by_status['cancelled'] ?? 0;

if ($cfg['has_bounty']) {
    $bq = $db->fetch_array($db->sql_query_prepared(
        "SELECT COALESCE(SUM(bounty),0) AS s, COALESCE(SUM(CASE WHEN status = 'open' THEN bounty ELSE 0 END),0) AS so FROM {$cfg['table']}"
    ));
    $kpi_extra = [
        'label' => $lang->requests_offers['kpi_bounty'],
        'value' => ags_fmt($lang->requests_offers['kpi_bounty_value'], number_format((float)$bq['s'], 1)),
        'sub'   => ags_fmt($lang->requests_offers['kpi_bounty_sub'], number_format((float)$bq['so'], 1)),
        'icon'  => 'fa-coins',
    ];
} else {
    $kpi_extra = [
        'label' => $lang->requests_offers['kpi_wants'],
        'value' => number_format((int)$db->fetch_field($db->sql_query_prepared("SELECT COALESCE(SUM({$cfg['count_col']}),0) AS s FROM {$cfg['table']}"), 's')),
        'sub'   => $lang->requests_offers['kpi_wants_sub'],
        'icon'  => 'fa-hand',
    ];
}

$colspan = $cfg['has_bounty'] ? 8 : 7;
$from    = $total ? $offset + 1 : 0;
$to      = min($offset + $perpage, $total);

// Строки для JS: js_* из ланга без префикса
$ro_js_lang = [];
foreach ($lang->requests_offers as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) $ro_js_lang[substr((string)$k, 3)] = (string)$v;
}

// ── Рендер ──────────────────────────────────────────────────────────────────
stdhead($lang->requests_offers['pane_title']);
enqueue_staff_assets();
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/requests_offers.css?ver=1">

<div class="ro-page"
     data-tab="<?= $tab ?>"
     data-singular="<?= ro_e($cfg['singular']) ?>"
     data-plural="<?= ro_e($cfg['noun'][2]) ?>">
<div class="container py-3">

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
    <script>document.addEventListener("DOMContentLoaded",function(){ showToast(<?= json_encode($f['msg'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($toast_type) ?>); });</script>
    <?php endif; ?>

    <!-- Header -->
    <header class="ro-head" style="<?= ro_tone($cfg['color']) ?>">
        <div class="ro-head-icon"><i class="fa-solid <?= $cfg['icon'] ?>"></i></div>
        <div class="ro-head-text">
            <h1><?= ro_e($lang->requests_offers['pane_title']) ?></h1>
            <p><?= ro_e($lang->requests_offers['pane_subtitle']) ?></p>
        </div>
        <nav class="ro-switch" aria-label="<?= ro_e($lang->requests_offers['aria_section']) ?>">
            <?php foreach ($TABLES as $key => $t): ?>
            <a href="<?= htmlspecialchars($_this_script_no_act . '?act=requests_offers&tab=' . $key) ?>"
               class="ro-switch-item<?= $tab === $key ? ' is-active' : '' ?>"
               style="<?= ro_tone($t['color']) ?>"
               <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid <?= $t['icon'] ?>"></i>
                <span><?= ro_e($t['label']) ?></span>
                <span class="ro-switch-count"><?= number_format($tab_counts[$key]) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- KPI -->
    <section class="ro-kpis">
        <div class="ro-kpi" style="<?= ro_tone($cfg['color']) ?>">
            <div class="ro-kpi-icon"><i class="fa-solid <?= $cfg['icon'] ?>"></i></div>
            <div class="ro-kpi-body">
                <div class="ro-kpi-value"><?= number_format($kpi_total) ?></div>
                <div class="ro-kpi-label"><?= ro_e($txt['kpi_total']) ?></div>
                <div class="ro-kpi-sub"><i class="fa-solid fa-ban"></i> <?= ro_e(ags_fmt($lang->requests_offers['kpi_cancelled_sub'], number_format($kpi_cancel))) ?></div>
            </div>
        </div>
        <div class="ro-kpi" style="<?= ro_tone($cfg['status_class']['open']) ?>">
            <div class="ro-kpi-icon"><i class="fa-solid <?= $cfg['status_icon']['open'] ?>"></i></div>
            <div class="ro-kpi-body">
                <div class="ro-kpi-value"><?= number_format($kpi_open) ?></div>
                <div class="ro-kpi-label"><?= ro_e($lang->requests_offers['kpi_open']) ?></div>
                <div class="ro-kpi-sub"><?= ro_e(ags_fmt($txt['kpi_open_sub'], $pct($kpi_open))) ?></div>
            </div>
        </div>
        <div class="ro-kpi" style="<?= ro_tone($cfg['status_class'][$done_key]) ?>">
            <div class="ro-kpi-icon"><i class="fa-solid <?= $cfg['status_icon'][$done_key] ?>"></i></div>
            <div class="ro-kpi-body">
                <div class="ro-kpi-value"><?= number_format($kpi_done) ?></div>
                <div class="ro-kpi-label"><?= ro_e($txt['kpi_done']) ?></div>
                <div class="ro-kpi-sub"><?= ro_e(ags_fmt($lang->requests_offers['kpi_done_sub'], $pct($kpi_done))) ?></div>
            </div>
        </div>
        <div class="ro-kpi" style="<?= ro_tone('warning') ?>">
            <div class="ro-kpi-icon"><i class="fa-solid <?= $kpi_extra['icon'] ?>"></i></div>
            <div class="ro-kpi-body">
                <div class="ro-kpi-value"><?= ro_e($kpi_extra['value']) ?></div>
                <div class="ro-kpi-label"><?= ro_e($kpi_extra['label']) ?></div>
                <div class="ro-kpi-sub"><?= ro_e($kpi_extra['sub']) ?></div>
            </div>
        </div>
    </section>

    <!-- Фильтр-форма: контролы лежат внутри карточки и привязаны через form="" -->
    <form method="get" id="ro-filter-form" action="<?= htmlspecialchars($_this_script_no_act) ?>">
        <input type="hidden" name="act" value="requests_offers">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <?php if ($filter_status !== ''): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <?php endif; ?>
    </form>

    <!-- Bulk form оборачивает карточку и нижнюю панель -->
    <form method="post" id="ro-bulk-form" action="<?= htmlspecialchars($admin_base) ?>">
        <input type="hidden" name="table" value="<?= $tab ?>">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">

        <div class="ro-card">
            <div class="ro-card-head">
                <!-- Статусы -->
                <div class="ro-chips" role="tablist" aria-label="<?= ro_e($lang->requests_offers['aria_status_filter']) ?>">
                    <a href="<?= htmlspecialchars($ro_url(['status' => ''])) ?>"
                       class="ro-chip<?= $filter_status === '' ? ' is-active' : '' ?>"
                       style="<?= ro_tone($cfg['color']) ?>">
                        <i class="fa-solid fa-layer-group"></i><?= ro_e($lang->requests_offers['lbl_all']) ?>
                        <span class="ro-chip-count"><?= number_format($kpi_total) ?></span>
                    </a>
                    <?php foreach ($cfg['statuses'] as $val => $label): ?>
                    <a href="<?= htmlspecialchars($ro_url(['status' => $val])) ?>"
                       class="ro-chip<?= $filter_status === $val ? ' is-active' : '' ?>"
                       style="<?= ro_tone($cfg['status_class'][$val]) ?>">
                        <i class="fa-solid <?= $cfg['status_icon'][$val] ?>"></i><?= ro_e($label) ?>
                        <span class="ro-chip-count"><?= number_format($kpi_by_status[$val] ?? 0) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Поиск / категория / сортировка -->
                <div class="ro-filters">
                    <label class="ro-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" name="q" form="ro-filter-form" class="form-control form-control-sm"
                               placeholder="<?= ro_e($lang->requests_offers['lbl_search']) ?>" value="<?= htmlspecialchars($search) ?>"
                               aria-label="<?= ro_e($lang->requests_offers['lbl_search']) ?>">
                    </label>

                    <label class="ro-select">
                        <i class="fa-solid fa-folder-open"></i>
                        <select name="cat" form="ro-filter-form" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="<?= ro_e($lang->requests_offers['aria_category']) ?>">
                            <option value="0"><?= ro_e($lang->requests_offers['opt_all_categories']) ?></option>
                            <?php foreach ($cats as $cid => $cname): ?>
                            <option value="<?= $cid ?>" <?= $filter_cat === $cid ? 'selected' : '' ?>><?= htmlspecialchars($cname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="ro-select">
                        <i class="fa-solid fa-arrow-down-wide-short"></i>
                        <select name="sort" form="ro-filter-form" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="<?= ro_e($lang->requests_offers['aria_sort']) ?>">
                            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>><?= ro_e($lang->requests_offers['opt_sort_newest']) ?></option>
                            <option value="<?= $cfg['count_col'] ?>" <?= $sort === $cfg['count_col'] ? 'selected' : '' ?>><?= ro_e($txt['sort_count']) ?></option>
                            <?php if ($cfg['has_bounty']): ?>
                            <option value="bounty" <?= $sort === 'bounty' ? 'selected' : '' ?>><?= ro_e($lang->requests_offers['opt_sort_bounty']) ?></option>
                            <?php endif; ?>
                        </select>
                    </label>

                    <button type="submit" form="ro-filter-form" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fa-solid fa-filter me-1"></i><?= ro_e($lang->requests_offers['btn_filter']) ?>
                    </button>
                    <?php if ($filters_on): ?>
                    <a href="<?= htmlspecialchars($_this_script_no_act . '?act=requests_offers&tab=' . $tab) ?>"
                       class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="fa-solid fa-rotate-left me-1"></i><?= ro_e($lang->requests_offers['btn_reset']) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table ro-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ro-col-check">
                                <input type="checkbox" id="ro-select-all" class="form-check-input ro-check" aria-label="<?= ro_e($lang->requests_offers['aria_select_all']) ?>">
                            </th>
                            <th><?= ro_e($cfg['singular']) ?></th>
                            <th class="ro-col-user"><i class="fa-solid fa-user me-1"></i><?= ro_e($lang->requests_offers['th_by']) ?></th>
                            <th class="ro-col-status"><?= ro_e($lang->requests_offers['th_status']) ?></th>
                            <th class="ro-col-num text-center" title="<?= ro_e($cfg['count_label']) ?>"><i class="fa-solid <?= $cfg['count_icon'] ?> me-1"></i><?= ro_e($cfg['count_label']) ?></th>
                            <?php if ($cfg['has_bounty']): ?>
                            <th class="ro-col-num text-end"><i class="fa-solid fa-coins me-1"></i><?= ro_e($lang->requests_offers['th_bounty']) ?></th>
                            <?php endif; ?>
                            <th class="ro-col-date d-none d-lg-table-cell"><i class="fa-regular fa-clock me-1"></i><?= ro_e($lang->requests_offers['th_created']) ?></th>
                            <th class="ro-col-actions text-end"><span class="visually-hidden"><?= ro_e($lang->requests_offers['th_actions']) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $count = 0;
                    while ($row = $db->fetch_array($list_q)):
                        $count++;
                        $rid     = (int)$row['id'];
                        $status  = (string)$row['status'];
                        $s_class = $cfg['status_class'][$status] ?? 'secondary';
                        $s_icon  = $cfg['status_icon'][$status] ?? 'fa-circle-question';
                        $s_label = $cfg['statuses'][$status] ?? ucfirst($status);
                        $title   = (string)$row['title'];
                        $view    = $BASEURL . $cfg['public_view'] . $rid;
                        $tid     = (int)($row['torrent_id'] ?? 0);

                        $uname = (string)($row['username'] ?? '');
                        $av    = (string)($row['avatar'] ?? '');
                        $av_url = '';
                        if ($av !== '') {
                            $av_url = preg_match('~^https?://~i', $av)
                                ? $av
                                : $BASEURL . '/' . ltrim((string)preg_replace('~^\./~', '', $av), '/');
                        }
                    ?>
                        <tr class="ro-row" style="<?= ro_tone($s_class) ?>">
                            <td class="ro-col-check">
                                <input type="checkbox" name="ids[]" value="<?= $rid ?>" class="form-check-input ro-check" aria-label="<?= ro_e(ags_fmt($lang->requests_offers['aria_select_row'], $rid)) ?>">
                            </td>
                            <td class="ro-col-title">
                                <a href="<?= htmlspecialchars($view) ?>" class="ro-title" target="_blank" rel="noopener"
                                   title="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></a>
                                <?php if (!empty($row['year'])): ?>
                                <span class="ro-year"><?= (int)$row['year'] ?></span>
                                <?php endif; ?>
                                <div class="ro-meta">
                                    <span class="ro-id">#<?= $rid ?></span>
                                    <span><i class="fa-solid fa-folder"></i><?= htmlspecialchars($cats[(int)($row['category_id'] ?? 0)] ?? $lang->requests_offers['lbl_no_category']) ?></span>
                                    <?php if ($tid > 0): ?>
                                    <a href="<?= htmlspecialchars($BASEURL . '/details.php?id=' . $tid) ?>" target="_blank" rel="noopener" class="ro-torrent">
                                        <i class="fa-solid fa-magnet"></i><?= ro_e(ags_fmt($lang->requests_offers['lbl_torrent'], $tid)) ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="ro-col-user">
                                <div class="ro-user">
                                    <?php if ($av_url !== ''): ?>
                                    <img src="<?= htmlspecialchars($av_url) ?>" class="ro-avatar" alt="" loading="lazy">
                                    <?php else: ?>
                                    <span class="ro-avatar ro-avatar-empty"><?= htmlspecialchars(mb_strtoupper(mb_substr($uname !== '' ? $uname : '?', 0, 1))) ?></span>
                                    <?php endif; ?>
                                    <span class="ro-username">
                                        <?php if ($uname !== ''): ?>
                                        <?= format_name(htmlspecialchars_uni($uname), (int)($row['usergroup'] ?? 0), (int)($row['displaygroup'] ?? 0)) ?>
                                        <?php else: ?>
                                        <em class="ro-muted"><?= ro_e($lang->requests_offers['lbl_user_deleted']) ?></em>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="ro-col-status">
                                <span class="ro-status"><i class="fa-solid <?= $s_icon ?>"></i><?= htmlspecialchars($s_label) ?></span>
                            </td>
                            <td class="ro-col-num text-center">
                                <span class="ro-num<?= (int)$row[$cfg['count_col']] === 0 ? ' is-zero' : '' ?>"><?= number_format((int)$row[$cfg['count_col']]) ?></span>
                            </td>
                            <?php if ($cfg['has_bounty']): ?>
                            <td class="ro-col-num text-end">
                                <?php if ((float)$row['bounty'] > 0): ?>
                                <span class="ro-bounty"><i class="fa-solid fa-coins"></i><?= number_format((float)$row['bounty'], 1) ?></span>
                                <?php else: ?>
                                <span class="ro-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="ro-col-date d-none d-lg-table-cell">
                                <span class="ro-date" title="<?= date('Y-m-d H:i', (int)$row['created_at']) ?>">
                                    <?= ags_fmt(ro_e($lang->requests_offers['lbl_ago']), mkprettytime(TIMENOW - (int)$row['created_at'])) ?>
                                </span>
                            </td>
                            <td class="ro-col-actions text-end">
                                <div class="ro-actions">
                                    <a href="<?= htmlspecialchars($view) ?>" target="_blank" rel="noopener" class="ro-icon-btn" title="<?= ro_e($lang->requests_offers['tip_open_public']) ?>">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <button class="ro-icon-btn ro-actions-btn" type="button" title="<?= ro_e($lang->requests_offers['tip_more_actions']) ?>" aria-haspopup="menu">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <ul class="dropdown-menu ro-actions-menu" role="menu">
                                        <li><h6 class="dropdown-header"><?= ro_e(ags_fmt($lang->requests_offers['lbl_change_status'], $rid)) ?></h6></li>
                                        <?php foreach ($cfg['statuses'] as $val => $label): if ($val === $status) continue; ?>
                                        <li>
                                            <?php if ($val === $done_key): ?>
                                            <button type="button" class="dropdown-item ro-mark-complete"
                                                    data-id="<?= $rid ?>" data-title="<?= htmlspecialchars($title) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#roCompleteModal">
                                                <i class="fa-solid <?= $cfg['status_icon'][$val] ?> fa-fw me-2 text-<?= $cfg['status_class'][$val] ?>"></i><?= ro_e(ags_fmt($lang->requests_offers['lbl_mark_as'], $label)) ?>…
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="dropdown-item"
                                                    data-ro-do="setstatus" data-id="<?= $rid ?>"
                                                    data-status="<?= $val ?>" data-status-label="<?= ro_e($label) ?>"
                                                    data-title="<?= htmlspecialchars($title) ?>">
                                                <i class="fa-solid <?= $cfg['status_icon'][$val] ?> fa-fw me-2 text-<?= $cfg['status_class'][$val] ?>"></i><?= ro_e(ags_fmt($lang->requests_offers['lbl_mark_as'], $label)) ?>
                                            </button>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger"
                                                    data-ro-do="delete" data-id="<?= $rid ?>"
                                                    data-title="<?= htmlspecialchars($title) ?>">
                                                <i class="fa-solid fa-trash-can fa-fw me-2"></i><?= ro_e($lang->requests_offers['btn_delete']) ?>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if ($count === 0): ?>
                        <tr>
                            <td colspan="<?= $colspan ?>">
                                <div class="ro-empty">
                                    <div class="ro-empty-icon" style="<?= ro_tone($cfg['color']) ?>">
                                        <i class="fa-solid <?= $filters_on ? 'fa-filter-circle-xmark' : $cfg['icon'] ?>"></i>
                                    </div>
                                    <?php if ($filters_on): ?>
                                    <h2><?= ro_e($txt['empty_filtered']) ?></h2>
                                    <p><?= ro_e($lang->requests_offers['empty_filtered_hint']) ?></p>
                                    <a href="<?= htmlspecialchars($_this_script_no_act . '?act=requests_offers&tab=' . $tab) ?>"
                                       class="btn btn-sm btn-outline-secondary rounded-pill px-4">
                                        <i class="fa-solid fa-rotate-left me-1"></i><?= ro_e($lang->requests_offers['btn_reset_filters']) ?>
                                    </a>
                                    <?php else: ?>
                                    <h2><?= ro_e($txt['empty_none']) ?></h2>
                                    <p><?= ro_e($txt['empty_none_hint']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total > 0): ?>
            <div class="ro-card-foot">
                <span class="ro-muted">
                    <i class="fa-solid fa-list-ol me-1"></i><?= ro_e(ags_fmt($lang->requests_offers['lbl_showing'], number_format($from), number_format($to), number_format($total))) ?>
                </span>
                <?php if ($total > $perpage): ?>
                <div class="ro-pager">
                    <?= multipage($total, $perpage, $page, $ro_url() . '&page={page}') ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sticky bulk bar -->
        <div class="ro-bulkbar" id="ro-bulkbar">
            <span class="ro-bulk-count" id="ro-selected-count">
                <i class="fa-solid fa-square-check"></i><?= ags_fmt(ro_e($lang->requests_offers['lbl_selected']), '<b>0</b>') ?>
            </span>
            <button type="button" class="ro-link-btn" id="ro-clear-selection">
                <i class="fa-solid fa-xmark me-1"></i><?= ro_e($lang->requests_offers['btn_clear']) ?>
            </button>
            <div class="ro-bulk-controls">
                <select name="bulk_action" class="form-select form-select-sm" aria-label="<?= ro_e($lang->requests_offers['aria_bulk_action']) ?>">
                    <option value=""><?= ro_e($lang->requests_offers['opt_choose_action']) ?></option>
                    <?php foreach ($cfg['statuses'] as $val => $label): ?>
                    <option value="<?= $val ?>"><?= ro_e(ags_fmt($lang->requests_offers['lbl_mark_as'], $label)) ?></option>
                    <?php endforeach; ?>
                    <option value="delete"><?= ro_e($lang->requests_offers['opt_delete_selected']) ?></option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4" id="ro-apply-btn" disabled>
                    <i class="fa-solid fa-bolt me-1"></i><?= ro_e($lang->requests_offers['btn_apply']) ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Скрытая форма для одиночных действий (POST + CSRF) -->
    <form method="post" id="ro-action-form" action="<?= htmlspecialchars($admin_base) ?>" hidden>
        <input type="hidden" name="table" value="<?= $tab ?>">
        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">
        <input type="hidden" name="ro_do" value="">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="status" value="">
    </form>

    <!-- Modal: Mark as Filled/Uploaded (нужен torrent_id) -->
    <div class="modal fade ro-modal" id="roCompleteModal" tabindex="-1" aria-labelledby="roCompleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= htmlspecialchars($admin_base) ?>">
                    <input type="hidden" name="table" value="<?= $tab ?>">
                    <input type="hidden" name="ro_complete_id" id="roCompleteId" value="">
                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($post_key) ?>">

                    <div class="modal-header">
                        <div class="ro-modal-icon" style="<?= ro_tone($cfg['status_class'][$done_key]) ?>">
                            <i class="fa-solid <?= $cfg['status_icon'][$done_key] ?>"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" id="roCompleteLabel"><?= ro_e($txt['modal_title']) ?></h5>
                            <div class="ro-modal-sub" id="roCompleteTitle"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= ro_e($lang->requests_offers['btn_close']) ?>"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="roTorrentId"><?= ro_e($txt['modal_label']) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-magnet"></i></span>
                            <input type="number" class="form-control" id="roTorrentId" name="torrent_id" required min="1" placeholder="12345">
                        </div>
                        <div class="form-text"><?= ro_e($lang->requests_offers['hint_torrent_id']) ?></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal"><?= ro_e($lang->requests_offers['btn_cancel']) ?></button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4">
                            <i class="fa-solid fa-check me-1"></i><?= ro_e($txt['modal_title']) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Строки для JS: блок данных (не исполняется) — работает при CSP и не конфликтует с глобальным AGS_LANG других скриптов -->
<script type="application/json" id="ro-lang"><?= json_encode($ro_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/requests_offers.js?ver=31"></script>
<?php
