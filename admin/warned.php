<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}


// В админке init может не определять эти константы
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);


define('W_VERSION', '1.0');

include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_mkprettytime.php';

global $mybb, $db;

$action = match (true) {
    isset($_POST['action']) => (string) $_POST['action'],
    isset($_GET['action'])  => (string) $_GET['action'],
    default                 => 'showlist',
};

// Filter whitelist — the only SQL fragments that ever reach the query
$wuFilters = [
    'all'    => "(u.warned = 'yes' OR u.leechwarn = 'yes')",
    'normal' => "u.warned = 'yes'",
    'leech'  => "u.leechwarn = 'yes'",
];
$wuType = is_string($_REQUEST['type'] ?? null) ? $_REQUEST['type'] : 'all';
if (!isset($wuFilters[$wuType])) {
    $wuType = 'all';
}

/* ------------------------------------------------------------------
 * POST: remove warnings (CSRF + log + Post/Redirect/Get)
 * ------------------------------------------------------------------ */
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        stderr('Security check failed. Please refresh the page and try again.');
    }

    $userIds = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['userid'] ?? [])),
        static fn(int $id): bool => $id > 0
    )));

    $removed = 0;
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        // Collect names first for the log (only users that actually have a warning)
        $names = [];
        $nameRes = $db->sql_query_prepared(
            "SELECT id, username FROM users
             WHERE id IN ($placeholders) AND (warned = 'yes' OR leechwarn = 'yes')",
            $userIds
        );
        if ($nameRes !== false) {
            while ($n = $db->fetch_array($nameRes)) {
                $names[] = $n['username'] . ' (#' . (int) $n['id'] . ')';
            }
        }

        if (!empty($names)) {
            $db->sql_query_prepared(
                "UPDATE users
                 SET warned = 'no', leechwarn = 'no', warneduntil = '0', leechwarnuntil = '0'
                 WHERE id IN ($placeholders) AND (warned = 'yes' OR leechwarn = 'yes')",
                $userIds
            );
            $removed = count($names);

            write_log(sprintf(
                'Warnings removed by %s for %d user(s): %s',
                (string) ($mybb->user['username'] ?? 'unknown'),
                $removed,
                implode(', ', $names)
            ));
        }
    }

    $page = max(1, (int) ($_POST['page'] ?? 1));
    header('Location: ' . $_this_script_ . '&action=showlist&type=' . $wuType . '&page=' . $page . '&done=' . $removed);
    exit;
}

/* ------------------------------------------------------------------
 * GET: list
 * ------------------------------------------------------------------ */
$weekAhead = TIMENOW + 7 * DAY_IN_SECONDS;

$stats = ['total' => 0, 'normal' => 0, 'leech' => 0, 'expiring' => 0];
$statRes = $db->sql_query_prepared(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(warned = 'yes'), 0) AS normal,
            COALESCE(SUM(leechwarn = 'yes'), 0) AS leech,
            COALESCE(SUM(
                (warned = 'yes' AND warneduntil > 0 AND warneduntil <= ?)
                OR (leechwarn = 'yes' AND leechwarnuntil > 0 AND leechwarnuntil <= ?)
            ), 0) AS expiring
     FROM users
     WHERE enabled = 'yes' AND usergroup != ? AND (warned = 'yes' OR leechwarn = 'yes')",
    [$weekAhead, $weekAhead, UC_BANNED]
);
if ($statRes !== false && ($row = $db->fetch_array($statRes))) {
    foreach ($stats as $k => $_) {
        $stats[$k] = (int) ($row[$k] ?? 0);
    }
}

$countrows = match ($wuType) {
    'normal' => $stats['normal'],
    'leech'  => $stats['leech'],
    default  => $stats['total'],
};

// Pagination
$perpage = max(1, (int) ($ts_perpage ?? 20));
$pages   = max(1, (int) ceil($countrows / $perpage));
$page    = max(1, (int) ($mybb->input['page'] ?? 1));
if ($page > $pages) {
    $page = 1;
}
$start = ($page - 1) * $perpage;

$baseUrl   = $_this_script_ . '&action=showlist';
$multipage = multipage($countrows, $perpage, $page, $baseUrl . '&type=' . $wuType);

$query = $db->sql_query_prepared(
    "SELECT u.*
     FROM users u
     WHERE u.usergroup != ? AND u.enabled = 'yes' AND {$wuFilters[$wuType]}
     ORDER BY u.added DESC
     LIMIT ?, ?",
    [UC_BANNED, $start, $perpage]
);

$done = max(0, (int) ($mybb->input['done'] ?? 0));

// Helpers (closures — safe if the file is included more than once)
$h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$untilCell = static function (int $ts, string $kind) use ($dateformat, $timeformat): string {
    $icon = $kind === 'leech' ? 'fa-skull-crossbones' : 'fa-triangle-exclamation';
    if ($ts <= 0) {
        return '<span class="wu-chip wu-chip-muted"><i class="fa-solid fa-infinity"></i> No expiry</span>';
    }
    $left = $ts - TIMENOW;
    if ($left <= 0) {
        return '<span class="wu-chip wu-chip-muted"><i class="fa-solid fa-hourglass-end"></i> Expired, awaiting cron</span>';
    }
    $soon = $left <= 7 * DAY_IN_SECONDS;
    return '<div class="wu-until' . ($soon ? ' is-soon' : '') . '">'
         . '<span><i class="fa-solid ' . $icon . ' fa-fw"></i> ' . my_datee($dateformat, $ts)
         . ' <span class="wu-muted">' . my_datee($timeformat, $ts) . '</span></span>'
         . '<small><i class="fa-solid fa-hourglass-half fa-fw"></i> ' . mkprettytime($left) . ' left</small>'
         . '</div>';
};

/* ------------------------------------------------------------------
 * Output
 * ------------------------------------------------------------------ */
stdhead('Warned Users');

$wuAsset = static function (string $rel): string {
    $mtime = @filemtime(TSDIR . $rel);
    return $GLOBALS['BASEURL'] . $rel . '?v=' . ($mtime ?: W_VERSION);
};

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="' . $wuAsset('/admin/templates/warned.css') . '">
<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>
<script src="' . $wuAsset('/admin/scripts/warned.js') . '" defer></script>';

echo '<div class="container-md wu-page my-3">';

// Header
echo '
<div class="wu-card wu-header">
    <div class="wu-header-icon"><i class="fa-solid fa-user-shield"></i></div>
    <div>
        <h1>Warned Users</h1>
        <p>Active members with a normal or leech warning. Select rows to lift warnings in bulk.</p>
    </div>
    <span class="wu-version"><i class="fa-solid fa-code-branch"></i> v' . W_VERSION . '</span>
</div>';

// KPI tiles (first three act as filters)
$kpi = static function (string $tone, string $icon, int $value, string $label, ?string $type) use ($h, $baseUrl, $wuType): string {
    $inner = '<div class="wu-kpi-icon"><i class="fa-solid ' . $icon . '"></i></div>'
           . '<div><div class="wu-kpi-value">' . ts_nf($value) . '</div><div class="wu-kpi-label">' . $label . '</div></div>';
    if ($type === null) {
        return '<div class="wu-card wu-kpi wu-t-' . $tone . '">' . $inner . '</div>';
    }
    $active = $type === $wuType ? ' is-active' : '';
    return '<a href="' . $h($baseUrl . '&type=' . $type) . '" class="wu-card wu-kpi wu-t-' . $tone . $active . '" title="Show: ' . $label . '">' . $inner . '</a>';
};

echo '<div class="wu-kpis">'
   . $kpi('primary', 'fa-users', $stats['total'], 'All warned', 'all')
   . $kpi('warning', 'fa-triangle-exclamation', $stats['normal'], 'Normal warnings', 'normal')
   . $kpi('danger', 'fa-skull-crossbones', $stats['leech'], 'Leech warnings', 'leech')
   . $kpi('info', 'fa-hourglass-half', $stats['expiring'], 'Expire within 7 days', null)
   . '</div>';

if ($done > 0) {
    echo '<div class="wu-flash" role="status"><i class="fa-solid fa-circle-check"></i>
        <span>Warnings removed from <b>' . $done . '</b> user' . ($done === 1 ? '' : 's') . '.</span>
        <button type="button" class="wu-flash-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>';
}

if ($pages > 1) {
    echo '<div class="wu-pager">' . $multipage . '</div>';
}

echo '
<form method="post" action="' . $h($_this_script_) . '" name="update" id="warnedForm">
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="type" value="' . $h($wuType) . '">
    <input type="hidden" name="page" value="' . $page . '">
    <input type="hidden" name="my_post_key" value="' . $h($mybb->post_code ?? '') . '">

    <div class="wu-card">
        <div class="wu-table-wrap">
            <table class="wu-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-user"></i>User</th>
                        <th><i class="fa-solid fa-calendar-plus"></i>Registered</th>
                        <th><i class="fa-solid fa-clock-rotate-left"></i>Last seen</th>
                        <th><i class="fa-solid fa-right-left"></i>Traffic</th>
                        <th><i class="fa-solid fa-scale-balanced"></i>Ratio</th>
                        <th><i class="fa-solid fa-calendar-xmark"></i>Expires</th>
                        <th><i class="fa-solid fa-tag"></i>Type</th>
                        <th class="text-center">
                            <label class="wu-switch wu-master" title="Select all on this page">
                                <input type="checkbox" id="wuSelectAll" aria-label="Select all">
                                <span class="wu-slider"></span>
                            </label>
                        </th>
                    </tr>
                </thead>
                <tbody>';

$rowCount = 0;
if ($query !== false) {
    while ($res = $db->fetch_array($query)) {
        $rowCount++;
        $uid = (int) $res['id'];

        $user = '<div class="wu-user"><a href="' . $BASEURL . '/' . get_profile_link($uid) . '">'
              . format_name(htmlspecialchars_uni((string) $res['username']), $res['usergroup']) . '</a>'
              . get_user_icons($res)
              . '<small>' . $h(get_user_class_name((string) $res['usergroup'])) . '</small></div>';

        $registered = '<div class="wu-stack"><span>' . my_datee($dateformat, $res['added']) . '</span></div>';

        $lastactive = (int) $res['lastactive'];
        $lastaccess = $lastactive > 0
            ? '<div class="wu-stack"><span>' . my_datee($dateformat, $lastactive) . '</span><span class="wu-muted">' . my_datee($timeformat, $lastactive) . '</span></div>'
            : '<span class="wu-muted">Never</span>';

        $dl = (float) $res['downloaded'];
        $ul = (float) $res['uploaded'];
        $traffic = '<div class="wu-stack wu-traffic">'
                 . '<span><i class="fa-solid fa-arrow-up fa-fw"></i> ' . mksize($res['uploaded']) . '</span>'
                 . '<span><i class="fa-solid fa-arrow-down fa-fw"></i> ' . mksize($res['downloaded']) . '</span></div>';

        if ($dl > 0) {
            $r = $ul / $dl;
            $ratio = '<span class="wu-ratio" style="color:' . $h(get_ratio_color($r)) . '">' . number_format($r, 3) . '</span>';
        } else {
            $ratio = '<span class="wu-ratio wu-muted" title="Nothing downloaded"><i class="fa-solid fa-infinity"></i></span>';
        }

        $isNormal = $res['warned'] === 'yes';
        $isLeech  = $res['leechwarn'] === 'yes';

        $until = '';
        $types = '';
        if ($isNormal) {
            $until .= $untilCell((int) $res['warneduntil'], 'normal');
            $types .= '<span class="wu-chip wu-chip-warning"><i class="fa-solid fa-triangle-exclamation"></i> Normal</span>';
        }
        if ($isLeech) {
            $until .= $untilCell((int) $res['leechwarnuntil'], 'leech');
            $types .= ($types !== '' ? '<br>' : '') . '<span class="wu-chip wu-chip-danger"><i class="fa-solid fa-skull-crossbones"></i> Leech</span>';
        }

        echo '
                    <tr>
                        <td>' . $user . '</td>
                        <td>' . $registered . '</td>
                        <td>' . $lastaccess . '</td>
                        <td>' . $traffic . '</td>
                        <td>' . $ratio . '</td>
                        <td>' . $until . '</td>
                        <td>' . $types . '</td>
                        <td class="text-center">
                            <label class="wu-switch" title="Remove warning">
                                <input type="checkbox" name="userid[]" value="' . $uid . '" aria-label="Select ' . $h($res['username']) . '">
                                <span class="wu-slider"></span>
                            </label>
                        </td>
                    </tr>';
    }
}

if ($rowCount === 0) {
    echo '
                    <tr>
                        <td colspan="8">
                            <div class="wu-empty">
                                <i class="fa-solid fa-shield-heart"></i>
                                <strong>No warned users</strong>
                                Nobody in this view has an active warning.
                            </div>
                        </td>
                    </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

if ($pages > 1) {
    echo '<div class="wu-pager">' . $multipage . '</div>';
}

if ($rowCount > 0) {
    echo '
    <div class="wu-actionbar">
        <span class="wu-selinfo"><i class="fa-solid fa-square-check"></i> Selected: <b id="wuSelCount">0</b> of ' . $rowCount . '</span>
        <div class="wu-actions">
            <button type="button" class="wu-btn wu-btn-light" id="wuClearBtn" disabled>
                <i class="fa-solid fa-eraser"></i> Clear
            </button>
            <button type="button" class="wu-btn wu-btn-danger" id="wuRemoveBtn" disabled>
                <i class="fa-solid fa-user-check"></i> Remove warnings
            </button>
        </div>
    </div>';
}

echo '
</form>
</div>';

stdfoot();