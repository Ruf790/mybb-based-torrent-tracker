<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_mkprettytime.php';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
const M_VIP_VERSION    = 'v0.6';
const VIP_USERGROUP_ID = 4;
const DEFAULT_PER_PAGE = 20;
const VIP_DAY_SECONDS  = 86400;

/** sortby value => SQL expression (whitelist) + default direction */
const VIP_SORT_FIELDS = [
    'username'  => ['sql' => 'u.username',  'dir' => 'ASC'],
    'vip_until' => ['sql' => 'COALESCE(NULLIF(av.vip_until, 0), 4294967295)', 'dir' => 'ASC'],
    'seedbonus' => ['sql' => 'u.seedbonus', 'dir' => 'DESC'],
    'invites'   => ['sql' => 'u.invites',   'dir' => 'DESC'],
];

/** Bulk actions: label, unit for the amount, icon, upper limit, colour tone */
const VIP_ACTIONS = [
    'donoruntil' => ['label' => 'Extend VIP',        'unit' => 'weeks',   'icon' => 'fa-calendar-plus', 'max' => 520,      'tone' => 'primary'],
    'seedbonus'  => ['label' => 'Give bonus points', 'unit' => 'points',  'icon' => 'fa-coins',         'max' => 10000000, 'tone' => 'warning'],
    'invites'    => ['label' => 'Give invites',      'unit' => 'invites', 'icon' => 'fa-envelope',      'max' => 1000,     'tone' => 'info'],
    'remove_vip' => ['label' => 'Remove VIP',        'unit' => '',        'icon' => 'fa-user-slash',    'max' => 0,        'tone' => 'danger'],
];

/** Base WHERE shared by every query: VIP group, no staff groups */
const VIP_BASE_WHERE = "u.usergroup = ?
          AND g.cansettingspanel = '0'
          AND g.canstaffpanel = '0'
          AND g.issupermod = '0'";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function vipH(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Raw (not HTML-escaped) URL: base + query, empty values dropped */
function vipUrl(string $base, array $query = []): string
{
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '' && $v !== 0);
    if (!$query) {
        return $base;
    }
    return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query);
}

/** Post/Redirect/Get */
function vipRedirect(string $base, array $query): never
{
    header('Location: ' . vipUrl($base, $query), true, 303);
    exit;
}

/** Wraps any avatar markup in a round clipping frame (no inline styles: popover sanitizer strips them) */
function vipAvatar(array $user, int $size): string
{
    $dim        = $size . '|' . $size;
    $avatarData = format_avatar($user['avatar'] ?? null, $dim, $dim);
    $avatarHtml = $avatarData['html'] ?? '';
    $sizeClass  = $size >= 64 ? 'vm-avatar-lg' : 'vm-avatar-sm';

    if ($avatarHtml === '') {
        $avatarHtml = '<i class="fa-solid fa-user"></i>';
        $sizeClass .= ' vm-avatar-empty';
    }

    return '<span class="vm-avatar ' . $sizeClass . '">' . $avatarHtml . '</span>';
}

function getVipUserPopoverContent(array $user): string
{
    global $lang, $dateformat, $timeformat;

    $lang->load('tsf_forums');

    $lastseen   = my_datee($dateformat, $user['lastactive']) . ' ' . my_datee($timeformat, $user['lastactive']);
    $downloaded = mksize($user['downloaded']);
    $uploaded   = mksize($user['uploaded']);
    $ratio      = get_user_ratio($user['uploaded'], $user['downloaded']);
    $isOnline   = (int) ($user['lastactive'] ?? 0) > (TIMENOW - 300);

    $ratioNum  = is_numeric($ratio) ? (float) $ratio : null;
    $ratioTone = match (true) {
        $ratioNum === null => 'secondary',
        $ratioNum >= 1.0   => 'success',
        $ratioNum >= 0.5   => 'warning',
        default            => 'danger',
    };

    $stat = static fn(string $icon, string $label, string $value, string $extra = ''): string =>
        '<div class="vp-stat">'
        . '<span class="vp-stat-label"><i class="fa-solid ' . $icon . '"></i>' . $label . '</span>'
        . '<span class="vp-stat-value ' . $extra . '">' . $value . '</span>'
        . '</div>';

    $html  = '<div class="vp">';
    $html .= '<div class="vp-head">';
    $html .= vipAvatar($user, 64);
    $html .= '<div class="vp-id">';
    $html .= '<div class="vp-name">' . vipH($user['username'] ?? '') . '</div>';
    $html .= '<div class="vp-title">' . vipH($user['title'] ?? 'VIP Member') . '</div>';
    $html .= $isOnline
        ? '<span class="vm-pill vm-tone-success"><i class="fa-solid fa-circle vp-dot"></i>Online</span>'
        : '<span class="vm-pill vm-tone-secondary"><i class="fa-regular fa-circle vp-dot"></i>Offline</span>';
    $html .= '</div></div>';

    $html .= '<div class="vp-grid">';
    $html .= $stat('fa-calendar-plus', 'Joined', my_datee($dateformat, $user['added']));
    $html .= $stat('fa-clock', 'Last seen', $lastseen);
    $html .= $stat('fa-scale-balanced', 'Ratio', (string) $ratio, 'vm-text-' . $ratioTone);
    $html .= $stat('fa-coins', 'Bonus points', ts_nf($user['seedbonus'] ?? 0));
    $html .= $stat('fa-arrow-up', 'Uploaded', $uploaded, 'vm-text-success');
    $html .= $stat('fa-arrow-down', 'Downloaded', $downloaded, 'vm-text-danger');
    $html .= $stat('fa-envelope', 'Invites', ts_nf($user['invites'] ?? 0) . ' available');
    $html .= '</div></div>';

    return $html;
}

function getVipUntilDisplay(?int $vipUntil): string
{
    global $dateformat;

    if (empty($vipUntil)) {
        return '<span class="vm-pill vm-tone-success"><i class="fa-solid fa-infinity"></i>Unlimited</span>';
    }

    $timeLeft = $vipUntil - TIMENOW;
    $daysLeft = (int) floor($timeLeft / VIP_DAY_SECONDS);

    [$tone, $icon] = match (true) {
        $timeLeft <= 0  => ['danger',  'fa-circle-exclamation'],
        $daysLeft <= 7  => ['warning', 'fa-hourglass-end'],
        $daysLeft <= 30 => ['info',    'fa-hourglass-half'],
        default         => ['primary', 'fa-hourglass-start'],
    };

    $sub = $timeLeft > 0
        ? mkprettytime($timeLeft) . ' left'
        : 'Expired, waiting for cron';

    return '<div class="vm-until">'
        . '<span class="vm-pill vm-tone-' . $tone . '"><i class="fa-solid ' . $icon . '"></i>' . my_datee($dateformat, $vipUntil) . '</span>'
        . '<small class="vm-until-sub vm-text-' . $tone . '">' . $sub . '</small>'
        . '</div>';
}

// ---------------------------------------------------------------------------
// Request state
// ---------------------------------------------------------------------------
$baseUrl = html_entity_decode((string) $_this_script_, ENT_QUOTES, 'UTF-8');
$src     = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$username    = trim((string) ($src['username'] ?? ''));
$sortField   = (string) ($src['sortby'] ?? 'username');
$sortField   = isset(VIP_SORT_FIELDS[$sortField]) ? $sortField : 'username';
$sortOrder   = strtoupper((string) ($src['type'] ?? VIP_SORT_FIELDS[$sortField]['dir'])) === 'DESC' ? 'DESC' : 'ASC';
$currentPage = max(1, (int) ($src['page'] ?? 1));

/** State carried through links and redirects */
$state = [
    'username' => $username,
    'sortby'   => $sortField === 'username' && $sortOrder === 'ASC' ? '' : $sortField,
    'type'     => $sortField === 'username' && $sortOrder === 'ASC' ? '' : $sortOrder,
];

// ---------------------------------------------------------------------------
// POST handling (always ends in a redirect)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['do'] ?? ''));

    // Search: turn the POST into a bookmarkable GET
    if ($action === 'search_user') {
        vipRedirect($baseUrl, $state);
    }

    if ($action === 'update') {
        $back = $state + ['page' => $currentPage > 1 ? $currentPage : ''];

        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            vipRedirect($baseUrl, $back + ['vipmsg' => 'csrf']);
        }

        $addType = (string) ($_POST['add'] ?? '');
        if (!isset(VIP_ACTIONS[$addType])) {
            vipRedirect($baseUrl, $back + ['vipmsg' => 'bad_action']);
        }

        $limit = (int) ($_POST['limit'] ?? 0);
        if ($addType !== 'remove_vip' && ($limit < 1 || $limit > VIP_ACTIONS[$addType]['max'])) {
            vipRedirect($baseUrl, $back + ['vipmsg' => 'bad_amount', 'act' => $addType]);
        }

        $userIds = [];
        foreach ((array) ($_POST['userids'] ?? []) as $raw) {
            if (is_scalar($raw) && ctype_digit((string) $raw) && (int) $raw > 0) {
                $userIds[(int) $raw] = (int) $raw;
            }
        }

        // Only act on accounts that really are listed here (VIP, not staff)
        if ($userIds) {
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $q  = $db->sql_query_prepared(
                "SELECT u.id FROM users u
                 LEFT JOIN usergroups g ON u.usergroup = g.gid
                 WHERE u.id IN ({$ph}) AND " . VIP_BASE_WHERE,
                [...array_values($userIds), VIP_USERGROUP_ID]
            );
            $valid = [];
            while ($q && ($row = $db->fetch_array($q))) {
                $valid[] = (int) $row['id'];
            }
            $userIds = $valid;
        }

        if (!$userIds) {
            vipRedirect($baseUrl, $back + ['vipmsg' => 'none_selected']);
        }

        switch ($addType) {
            case 'donoruntil':
                $extendSeconds = $limit * 7 * VIP_DAY_SECONDS;

                foreach ($userIds as $uid) {
                    $existingQ = $db->sql_query_prepared('SELECT vip_until, old_gid FROM auto_vip WHERE userid = ?', [$uid]);
                    $existing  = $existingQ ? $db->fetch_array($existingQ) : null;

                    if ($existing) {
                        $newUntil = max((int) $existing['vip_until'], TIMENOW) + $extendSeconds;
                        $db->sql_query_prepared('UPDATE auto_vip SET vip_until = ? WHERE userid = ?', [$newUntil, $uid]);
                    } else {
                        $currentGroupQ = $db->sql_query_prepared('SELECT usergroup FROM users WHERE id = ?', [$uid]);
                        $currentGroup  = $currentGroupQ ? $db->fetch_array($currentGroupQ) : null;
                        $oldGid = ((int) ($currentGroup['usergroup'] ?? 0) === VIP_USERGROUP_ID)
                            ? UC_USER
                            : (int) ($currentGroup['usergroup'] ?? UC_USER);

                        $db->sql_query_prepared(
                            'INSERT INTO auto_vip (userid, vip_until, old_gid) VALUES (?, ?, ?)',
                            [$uid, TIMENOW + $extendSeconds, $oldGid]
                        );
                    }

                    $db->sql_query_prepared('UPDATE users SET usergroup = ? WHERE id = ?', [VIP_USERGROUP_ID, $uid]);
                }

                write_log('VIP time extended by ' . $limit . ' week(s) for users: ' . implode(', ', $userIds), 'general', 1);
                break;

            case 'seedbonus':
                $ph = implode(',', array_fill(0, count($userIds), '?'));
                $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus + ? WHERE id IN ({$ph})", [$limit, ...$userIds]);
                write_log('Gave ' . $limit . ' bonus points to users: ' . implode(', ', $userIds), 'general', 1);
                break;

            case 'invites':
                $ph = implode(',', array_fill(0, count($userIds), '?'));
                $db->sql_query_prepared("UPDATE users SET invites = invites + ? WHERE id IN ({$ph})", [$limit, ...$userIds]);
                write_log('Gave ' . $limit . ' invite(s) to users: ' . implode(', ', $userIds), 'general', 1);
                break;

            case 'remove_vip':
                foreach ($userIds as $uid) {
                    $existingQ = $db->sql_query_prepared('SELECT old_gid FROM auto_vip WHERE userid = ?', [$uid]);
                    $existing  = $existingQ ? $db->fetch_array($existingQ) : null;
                    $newGid    = ($existing && (int) $existing['old_gid'] > 0) ? (int) $existing['old_gid'] : UC_USER;

                    $db->sql_query_prepared('UPDATE users SET usergroup = ? WHERE id = ?', [$newGid, $uid]);
                    $db->sql_query_prepared('DELETE FROM auto_vip WHERE userid = ?', [$uid]);
                }

                write_log('VIP status manually removed by staff for users: ' . implode(', ', $userIds), 'general', 1);
                break;
        }

        // After a removal the current page may be past the end — the GET clamps it
        vipRedirect($baseUrl, $back + ['vipmsg' => $addType, 'n' => count($userIds), 'amt' => $limit]);
    }
}

// ---------------------------------------------------------------------------
// Flash message (from PRG redirect)
// ---------------------------------------------------------------------------
$flash    = null;
$flashKey = (string) ($_GET['vipmsg'] ?? '');
$flashN   = max(0, (int) ($_GET['n'] ?? 0));
$flashAmt = max(0, (int) ($_GET['amt'] ?? 0));
$flashAct = (string) ($_GET['act'] ?? '');

$flash = match ($flashKey) {
    'donoruntil'    => ['success', 'fa-calendar-check', 'VIP extended by ' . ts_nf($flashAmt) . ' week(s) for ' . ts_nf($flashN) . ' account(s).'],
    'seedbonus'     => ['success', 'fa-coins',          ts_nf($flashAmt) . ' bonus points given to ' . ts_nf($flashN) . ' account(s).'],
    'invites'       => ['success', 'fa-envelope-circle-check', ts_nf($flashAmt) . ' invite(s) given to ' . ts_nf($flashN) . ' account(s).'],
    'remove_vip'    => ['success', 'fa-user-check',     'VIP removed from ' . ts_nf($flashN) . ' account(s). Their previous group was restored.'],
    'none_selected' => ['warning', 'fa-hand-pointer',   'Nothing was changed: select at least one VIP account in the table.'],
    'bad_amount'    => ['warning', 'fa-hashtag',        'Nothing was changed: enter an amount from 1 to '
                            . ts_nf(VIP_ACTIONS[$flashAct]['max'] ?? 1) . (isset(VIP_ACTIONS[$flashAct]) ? ' ' . VIP_ACTIONS[$flashAct]['unit'] : '') . '.'],
    'bad_action'    => ['danger',  'fa-circle-xmark',   'Nothing was changed: unknown action.'],
    'csrf'          => ['danger',  'fa-shield-halved',  'Nothing was changed: the form has expired. Reload the page and try again.'],
    default         => null,
};

// ---------------------------------------------------------------------------
// KPI stats (whole VIP group, ignores search)
// ---------------------------------------------------------------------------
$kpiQ = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN av.vip_until IS NULL OR av.vip_until = 0 THEN 1 ELSE 0 END) AS unlimited,
            SUM(CASE WHEN av.vip_until > ? AND av.vip_until <= ? THEN 1 ELSE 0 END)   AS expiring,
            SUM(CASE WHEN av.vip_until > 0 AND av.vip_until <= ? THEN 1 ELSE 0 END)   AS expired
       FROM users u
       LEFT JOIN usergroups g ON u.usergroup = g.gid
       LEFT JOIN auto_vip av  ON av.userid = u.id
      WHERE " . VIP_BASE_WHERE,
    [TIMENOW, TIMENOW + 7 * VIP_DAY_SECONDS, TIMENOW, VIP_USERGROUP_ID]
);
$kpiRow = $kpiQ ? $db->fetch_array($kpiQ) : [];
$kpi = [
    'total'     => (int) ($kpiRow['total'] ?? 0),
    'unlimited' => (int) ($kpiRow['unlimited'] ?? 0),
    'expiring'  => (int) ($kpiRow['expiring'] ?? 0),
    'expired'   => (int) ($kpiRow['expired'] ?? 0),
];

// ---------------------------------------------------------------------------
// Search + count
// ---------------------------------------------------------------------------
$searchSql    = '';
$searchParams = [];
if ($username !== '') {
    $searchSql    = ' AND (u.username = ? OR u.username LIKE ?) ';
    $searchParams = [$username, '%' . addcslashes($username, '%_\\') . '%'];

    $countQ     = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total FROM users u
         LEFT JOIN usergroups g ON u.usergroup = g.gid
         WHERE " . VIP_BASE_WHERE . $searchSql,
        [VIP_USERGROUP_ID, ...$searchParams]
    );
    $countRow   = $countQ ? $db->fetch_array($countQ) : [];
    $totalUsers = (int) ($countRow['total'] ?? 0);
} else {
    $totalUsers = $kpi['total'];
}

// ---------------------------------------------------------------------------
// Pagination + list
// ---------------------------------------------------------------------------
$perPage = (int) ($torrentsperpage ?? 0);
$perPage = $perPage > 0 ? $perPage : DEFAULT_PER_PAGE;
$totalPages  = max(1, (int) ceil($totalUsers / $perPage));
$currentPage = min($currentPage, $totalPages);
$start       = ($currentPage - 1) * $perPage;

$orderSql = VIP_SORT_FIELDS[$sortField]['sql'] . ' ' . $sortOrder . ', u.id ASC';

$listQ = $db->sql_query_prepared(
    "SELECT u.*, g.namestyle, g.title, av.vip_until, av.old_gid
       FROM users u
       LEFT JOIN usergroups g ON u.usergroup = g.gid
       LEFT JOIN auto_vip av  ON av.userid = u.id
      WHERE " . VIP_BASE_WHERE . $searchSql . "
      ORDER BY {$orderSql}
      LIMIT ?, ?",
    [VIP_USERGROUP_ID, ...$searchParams, $start, $perPage]
);

$vipRows = [];
while ($listQ && ($row = $db->fetch_array($listQ))) {
    $vipRows[] = $row;
}

$pageUrl   = vipH(vipUrl($baseUrl, $state)) . '&amp;';
$multipage = $totalPages > 1 ? multipage($totalUsers, $perPage, $currentPage, $pageUrl) : '';

$shownFrom = $vipRows ? $start + 1 : 0;
$shownTo   = $start + count($vipRows);

/** Sortable header link */
$sortLink = static function (string $field, string $label, string $icon, string $align = '') use ($baseUrl, $username, $sortField, $sortOrder): string {
    $active  = $sortField === $field;
    $nextDir = $active ? ($sortOrder === 'ASC' ? 'DESC' : 'ASC') : VIP_SORT_FIELDS[$field]['dir'];
    $href    = vipUrl($baseUrl, ['username' => $username, 'sortby' => $field, 'type' => $nextDir]);
    $caret   = $active ? ($sortOrder === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';

    return '<a href="' . vipH($href) . '" class="vm-sort' . ($active ? ' is-active' : '') . ($align ? ' ' . $align : '') . '"'
        . ($active ? ' aria-sort="' . ($sortOrder === 'ASC' ? 'ascending' : 'descending') . '"' : '') . '>'
        . '<i class="fa-solid ' . $icon . '"></i><span>' . $label . '</span>'
        . '<i class="fa-solid ' . $caret . ' vm-caret"></i></a>';
};

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------
stdhead('Manage VIP Accounts (Total ' . ts_nf($totalUsers) . ' VIP Accounts found)');
?>
<link rel="stylesheet" href="<?= vipH($BASEURL) ?>/include/templates/default/style/sweetalert2.min.css">

<div class="container mt-3 vm-page">

    <!-- Header -->
    <div class="card vm-card vm-header mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <span class="vm-icon-square vm-tone-warning"><i class="fa-solid fa-crown"></i></span>
            <div class="flex-grow-1">
                <h1 class="vm-title">Manage VIP accounts</h1>
                <p class="vm-subtitle mb-0">Extend VIP time, give bonus points or invites, or remove VIP from selected members.</p>
            </div>
            <span class="vm-pill vm-tone-secondary" title="Module version"><i class="fa-solid fa-code-branch"></i><?= vipH(M_VIP_VERSION) ?></span>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <?php
        $tiles = [
            ['primary', 'fa-users',               $kpi['total'],     'VIP accounts'],
            ['success', 'fa-infinity',            $kpi['unlimited'], 'Unlimited'],
            ['warning', 'fa-hourglass-end',       $kpi['expiring'],  'Expire within 7 days'],
            ['danger',  'fa-circle-exclamation',  $kpi['expired'],   'Expired, waiting for cron'],
        ];
        foreach ($tiles as [$tone, $icon, $value, $label]): ?>
        <div class="col-6 col-lg-3">
            <div class="card vm-card vm-kpi h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="vm-icon-square vm-icon-square-sm vm-tone-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <div class="min-w-0">
                        <div class="vm-kpi-value"><?= ts_nf($value) ?></div>
                        <div class="vm-kpi-label"><?= $label ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($flash): [$fTone, $fIcon, $fText] = $flash; ?>
    <div class="vm-flash vm-tone-<?= $fTone ?> mb-3" role="<?= $fTone === 'success' ? 'status' : 'alert' ?>">
        <i class="fa-solid <?= $fIcon ?>"></i>
        <span class="flex-grow-1"><?= vipH($fText) ?></span>
        <button type="button" class="vm-flash-close" aria-label="Dismiss" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <?php endif; ?>

    <!-- Toolbar: search + range -->
    <div class="card vm-card mb-3">
        <div class="card-body">
            <form method="post" action="<?= vipH($baseUrl) ?>" class="vm-toolbar">
                <input type="hidden" name="do" value="search_user">
                <input type="hidden" name="sortby" value="<?= vipH($sortField) ?>">
                <input type="hidden" name="type" value="<?= vipH($sortOrder) ?>">

                <div class="vm-search">
                    <i class="fa-solid fa-magnifying-glass vm-search-icon"></i>
                    <input type="search" class="form-control vm-input" id="username" name="username"
                           value="<?= vipH($username) ?>" placeholder="Find a VIP by username"
                           autocomplete="off" aria-label="Username">
                </div>
                <button type="submit" class="btn btn-primary vm-btn"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
                <?php if ($username !== ''): ?>
                <a href="<?= vipH(vipUrl($baseUrl, ['sortby' => $state['sortby'], 'type' => $state['type']])) ?>" class="btn btn-outline-secondary vm-btn">
                    <i class="fa-solid fa-xmark me-1"></i>Clear
                </a>
                <?php endif; ?>

                <span class="vm-range ms-auto">
                    <i class="fa-solid fa-list-ol"></i>
                    <?php if ($totalUsers > 0): ?>
                        <?= ts_nf($shownFrom) ?>–<?= ts_nf($shownTo) ?> of <?= ts_nf($totalUsers) ?>
                    <?php else: ?>
                        0 found
                    <?php endif; ?>
                </span>
            </form>
        </div>
    </div>

    <?php if ($multipage !== ''): ?>
    <nav class="vm-pager mb-3" aria-label="VIP users pagination"><?= $multipage ?></nav>
    <?php endif; ?>

    <!-- VIP table + sticky action bar -->
    <form method="post" action="<?= vipH($baseUrl) ?>" name="update" id="vmUpdateForm">
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="my_post_key" value="<?= vipH($mybb->post_code ?? '') ?>">
        <input type="hidden" name="page" value="<?= $currentPage ?>">
        <input type="hidden" name="username" value="<?= vipH($username) ?>">
        <input type="hidden" name="sortby" value="<?= vipH($sortField) ?>">
        <input type="hidden" name="type" value="<?= vipH($sortOrder) ?>">

        <div class="card vm-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 vm-table">
                    <thead>
                        <tr>
                            <th class="vm-col-check">
                                <input class="form-check-input" type="checkbox" checkall="group" id="vmCheckAll"
                                       aria-label="Select all on this page"
                                       onclick="if (typeof select_deselectAll === 'function') select_deselectAll('update', this, 'group')">
                            </th>
                            <th><?= $sortLink('username', 'Member', 'fa-user') ?></th>
                            <th><?= $sortLink('vip_until', 'VIP until', 'fa-crown') ?></th>
                            <th class="text-end"><?= $sortLink('seedbonus', 'Bonus points', 'fa-coins', 'justify-content-end') ?></th>
                            <th class="text-end"><?= $sortLink('invites', 'Invites', 'fa-envelope', 'justify-content-end') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($vipRows): ?>
                        <?php foreach ($vipRows as $vip):
                            $uid = (int) ($vip['id'] ?? 0); ?>
                        <tr class="vm-row">
                            <td class="vm-col-check">
                                <input class="form-check-input" type="checkbox" name="userids[]" value="<?= $uid ?>"
                                       checkme="group" id="vmUser<?= $uid ?>"
                                       aria-label="Select <?= vipH($vip['username'] ?? '') ?>">
                            </td>
                            <td>
                                <div class="vm-member">
                                    <?= vipAvatar($vip, 32) ?>
                                    <div class="min-w-0">
                                        <a href="<?= vipH($BASEURL . '/' . get_profile_link($uid)) ?>" target="_blank" rel="noopener"
                                           class="vm-member-name user-popover-link"
                                           data-bs-toggle="popover"
                                           data-bs-custom-class="user-popover"
                                           data-bs-html="true"
                                           data-bs-content="<?= vipH(getVipUserPopoverContent($vip)) ?>"
                                           data-bs-placement="auto"
                                           data-bs-trigger="hover focus"><?= format_name(htmlspecialchars_uni((string) ($vip['username'] ?? '')), (int) ($vip['usergroup'] ?? 0)) ?></a>
                                        <div class="vm-member-title"><?= vipH($vip['title'] ?? 'VIP Member') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= getVipUntilDisplay(isset($vip['vip_until']) ? (int) $vip['vip_until'] : null) ?></td>
                            <td class="text-end">
                                <span class="vm-num"><i class="fa-solid fa-coins vm-text-warning"></i><?= ts_nf($vip['seedbonus'] ?? 0) ?></span>
                            </td>
                            <td class="text-end">
                                <span class="vm-num"><i class="fa-solid fa-envelope vm-text-info"></i><?= ts_nf($vip['invites'] ?? 0) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="vm-empty">
                                <span class="vm-icon-square vm-tone-secondary mb-3"><i class="fa-solid <?= $username !== '' ? 'fa-magnifying-glass' : 'fa-crown' ?>"></i></span>
                                <?php if ($username !== ''): ?>
                                    <h2 class="vm-empty-title">No VIP account matches “<?= vipH($username) ?>”</h2>
                                    <p class="mb-3">Check the spelling or search for part of the name.</p>
                                    <a href="<?= vipH($baseUrl) ?>" class="btn btn-outline-secondary vm-btn"><i class="fa-solid fa-xmark me-1"></i>Clear search</a>
                                <?php else: ?>
                                    <h2 class="vm-empty-title">There are no VIP accounts yet</h2>
                                    <p class="mb-0">Members appear here once they buy VIP or staff move them into the VIP group.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($vipRows): ?>
        <div class="vm-actionbar" id="vmActionBar">
            <span class="vm-selected" title="Selected accounts">
                <i class="fa-solid fa-check-double"></i><span id="selectedCount">0</span> selected
            </span>

            <div class="vm-seg" role="radiogroup" aria-label="Action">
                <?php $first = true; foreach (VIP_ACTIONS as $key => $a): ?>
                <input type="radio" class="btn-check" name="add" id="vmAct_<?= $key ?>" value="<?= $key ?>"
                       data-unit="<?= vipH($a['unit']) ?>" data-max="<?= (int) $a['max'] ?>" data-label="<?= vipH($a['label']) ?>"
                       autocomplete="off"<?= $first ? ' checked' : '' ?>>
                <label class="vm-seg-btn vm-tone-<?= $a['tone'] ?>" for="vmAct_<?= $key ?>">
                    <i class="fa-solid <?= $a['icon'] ?>"></i><span><?= vipH($a['label']) ?></span>
                </label>
                <?php $first = false; endforeach; ?>
            </div>

            <div class="vm-amount" id="limitFormGroup">
                <input type="number" class="form-control vm-input" id="limit" name="limit" min="1"
                       max="<?= (int) VIP_ACTIONS['donoruntil']['max'] ?>" placeholder="Amount" required aria-label="Amount">
                <span class="vm-unit" id="vmUnit"><?= vipH(VIP_ACTIONS['donoruntil']['unit']) ?></span>
            </div>

            <button type="submit" class="btn btn-primary vm-btn ms-auto" id="vmSubmit" disabled>
                <i class="fa-solid fa-bolt me-1"></i>Apply to selected
            </button>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($multipage !== ''): ?>
    <nav class="vm-pager mt-3" aria-label="VIP users pagination bottom"><?= $multipage ?></nav>
    <?php endif; ?>
</div>

<script src="<?= vipH($BASEURL) ?>/scripts/popover.js"></script>
<script src="<?= vipH($BASEURL) ?>/scripts/sweetalert2.min.js"></script>

<script src="<?= vipH($BASEURL) ?>/admin/scripts/manage_vip.js?ver=336"></script>

<link rel="stylesheet" href="<?= vipH($BASEURL) ?>/admin/templates/manage_vip.css?ver=336">

<?php
stdfoot();