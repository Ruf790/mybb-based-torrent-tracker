<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}


// В админке init может не определять эти константы
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);


define('W_VERSION', '1.1');

include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_mkprettytime.php';

global $mybb, $db, $lang;

$lang->load('warned');

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and the %1$s form produced by $lang->load()).
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string) $arg;
            $map['%' . $n . '$s'] = (string) $arg;
        }
        return strtr($str, $map);
    }
}

// Plural form for the current language: 'one' | 'few' | 'many'
$wuPlural = static function (int $n): string {
    global $lang;
    if (($lang->warned['js_plural_rule'] ?? 'en') === 'ru') {
        $m10  = $n % 10;
        $m100 = $n % 100;
        return match (true) {
            $m10 === 1 && $m100 !== 11                            => 'one',
            $m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) => 'few',
            default                                               => 'many',
        };
    }
    return $n === 1 ? 'one' : 'many';
};

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
        stderr($lang->warned['err_security']);
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

$untilCell = static function (int $ts, string $kind) use ($dateformat, $timeformat, $h): string {
    global $lang;
    $icon = $kind === 'leech' ? 'fa-skull-crossbones' : 'fa-triangle-exclamation';
    if ($ts <= 0) {
        return '<span class="wu-chip wu-chip-muted"><i class="fa-solid fa-infinity"></i> ' . $h($lang->warned['lbl_no_expiry']) . '</span>';
    }
    $left = $ts - TIMENOW;
    if ($left <= 0) {
        return '<span class="wu-chip wu-chip-muted"><i class="fa-solid fa-hourglass-end"></i> ' . $h($lang->warned['lbl_expired_cron']) . '</span>';
    }
    $soon = $left <= 7 * DAY_IN_SECONDS;
    return '<div class="wu-until' . ($soon ? ' is-soon' : '') . '">'
         . '<span><i class="fa-solid ' . $icon . ' fa-fw"></i> ' . my_datee($dateformat, $ts)
         . ' <span class="wu-muted">' . my_datee($timeformat, $ts) . '</span></span>'
         . '<small><i class="fa-solid fa-hourglass-half fa-fw"></i> ' . ags_fmt($h($lang->warned['lbl_time_left']), mkprettytime($left)) . '</small>'
         . '</div>';
};

/* ------------------------------------------------------------------
 * Output
 * ------------------------------------------------------------------ */
stdhead($lang->warned['page_title']);

$wuAsset = static function (string $rel): string {
    $mtime = @filemtime(TSDIR . $rel);
    return $GLOBALS['BASEURL'] . $rel . '?v=' . ($mtime ?: W_VERSION);
};

// JS strings: js_* keys without the prefix
$wuJsLang = [];
foreach ($lang->warned as $k => $v) {
    if (str_starts_with((string) $k, 'js_')) {
        $wuJsLang[substr((string) $k, 3)] = (string) $v;
    }
}

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="' . $wuAsset('/admin/templates/warned.css') . '">
<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>
<script>const AGS_LANG = ' . json_encode($wuJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>
<script src="' . $wuAsset('/admin/scripts/warned.js') . '" defer></script>';

echo '<div class="container-md wu-page my-3">';

// Header
echo '
<div class="wu-card wu-header">
    <div class="wu-header-icon"><i class="fa-solid fa-user-shield"></i></div>
    <div>
        <h1>' . $h($lang->warned['sec_header']) . '</h1>
        <p>' . $h($lang->warned['hint_header']) . '</p>
    </div>
    <span class="wu-version"><i class="fa-solid fa-code-branch"></i> v' . W_VERSION . '</span>
</div>';

// KPI tiles (first three act as filters)
$kpi = static function (string $tone, string $icon, int $value, string $label, ?string $type) use ($h, $baseUrl, $wuType): string {
    global $lang;
    $inner = '<div class="wu-kpi-icon"><i class="fa-solid ' . $icon . '"></i></div>'
           . '<div><div class="wu-kpi-value">' . ts_nf($value) . '</div><div class="wu-kpi-label">' . $h($label) . '</div></div>';
    if ($type === null) {
        return '<div class="wu-card wu-kpi wu-t-' . $tone . '">' . $inner . '</div>';
    }
    $active = $type === $wuType ? ' is-active' : '';
    return '<a href="' . $h($baseUrl . '&type=' . $type) . '" class="wu-card wu-kpi wu-t-' . $tone . $active . '" title="' . $h(ags_fmt($lang->warned['tip_kpi_show'], $label)) . '">' . $inner . '</a>';
};

echo '<div class="wu-kpis">'
   . $kpi('primary', 'fa-users', $stats['total'], $lang->warned['lbl_kpi_all'], 'all')
   . $kpi('warning', 'fa-triangle-exclamation', $stats['normal'], $lang->warned['lbl_kpi_normal'], 'normal')
   . $kpi('danger', 'fa-skull-crossbones', $stats['leech'], $lang->warned['lbl_kpi_leech'], 'leech')
   . $kpi('info', 'fa-hourglass-half', $stats['expiring'], $lang->warned['lbl_kpi_expiring'], null)
   . '</div>';

if ($done > 0) {
    $flashText = match ($wuPlural($done)) {
        'one'   => $lang->warned['flash_removed_one'],
        'few'   => $lang->warned['flash_removed_few'],
        default => $lang->warned['flash_removed_many'],
    };
    echo '<div class="wu-flash" role="status"><i class="fa-solid fa-circle-check"></i>
        <span>' . ags_fmt($flashText, $done) . '</span>
        <button type="button" class="wu-flash-close" aria-label="' . $h($lang->warned['lbl_close']) . '"><i class="fa-solid fa-xmark"></i></button>
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
                        <th><i class="fa-solid fa-user"></i>' . $h($lang->warned['lbl_col_user']) . '</th>
                        <th><i class="fa-solid fa-calendar-plus"></i>' . $h($lang->warned['lbl_col_registered']) . '</th>
                        <th><i class="fa-solid fa-clock-rotate-left"></i>' . $h($lang->warned['lbl_col_lastseen']) . '</th>
                        <th><i class="fa-solid fa-right-left"></i>' . $h($lang->warned['lbl_col_traffic']) . '</th>
                        <th><i class="fa-solid fa-scale-balanced"></i>' . $h($lang->warned['lbl_col_ratio']) . '</th>
                        <th><i class="fa-solid fa-calendar-xmark"></i>' . $h($lang->warned['lbl_col_expires']) . '</th>
                        <th><i class="fa-solid fa-tag"></i>' . $h($lang->warned['lbl_col_type']) . '</th>
                        <th class="text-center">
                            <label class="wu-switch wu-master" title="' . $h($lang->warned['tip_select_all_page']) . '">
                                <input type="checkbox" id="wuSelectAll" aria-label="' . $h($lang->warned['lbl_select_all']) . '">
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
            : '<span class="wu-muted">' . $h($lang->warned['lbl_never']) . '</span>';

        $dl = (float) $res['downloaded'];
        $ul = (float) $res['uploaded'];
        $traffic = '<div class="wu-stack wu-traffic">'
                 . '<span><i class="fa-solid fa-arrow-up fa-fw"></i> ' . mksize($res['uploaded']) . '</span>'
                 . '<span><i class="fa-solid fa-arrow-down fa-fw"></i> ' . mksize($res['downloaded']) . '</span></div>';

        if ($dl > 0) {
            $r = $ul / $dl;
            $ratio = '<span class="wu-ratio" style="color:' . $h(get_ratio_color($r)) . '">' . number_format($r, 3) . '</span>';
        } else {
            $ratio = '<span class="wu-ratio wu-muted" title="' . $h($lang->warned['tip_no_download']) . '"><i class="fa-solid fa-infinity"></i></span>';
        }

        $isNormal = $res['warned'] === 'yes';
        $isLeech  = $res['leechwarn'] === 'yes';

        $until = '';
        $types = '';
        if ($isNormal) {
            $until .= $untilCell((int) $res['warneduntil'], 'normal');
            $types .= '<span class="wu-chip wu-chip-warning"><i class="fa-solid fa-triangle-exclamation"></i> ' . $h($lang->warned['opt_type_normal']) . '</span>';
        }
        if ($isLeech) {
            $until .= $untilCell((int) $res['leechwarnuntil'], 'leech');
            $types .= ($types !== '' ? '<br>' : '') . '<span class="wu-chip wu-chip-danger"><i class="fa-solid fa-skull-crossbones"></i> ' . $h($lang->warned['opt_type_leech']) . '</span>';
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
                            <label class="wu-switch" title="' . $h($lang->warned['tip_remove_warning']) . '">
                                <input type="checkbox" name="userid[]" value="' . $uid . '" aria-label="' . $h(ags_fmt($lang->warned['lbl_select_user'], (string) $res['username'])) . '">
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
                                <strong>' . $h($lang->warned['sec_empty']) . '</strong>
                                ' . $h($lang->warned['hint_empty']) . '
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
        <span class="wu-selinfo"><i class="fa-solid fa-square-check"></i> ' . ags_fmt($h($lang->warned['lbl_selected']), '<b id="wuSelCount">0</b>', $rowCount) . '</span>
        <div class="wu-actions">
            <button type="button" class="wu-btn wu-btn-light" id="wuClearBtn" disabled>
                <i class="fa-solid fa-eraser"></i> ' . $h($lang->warned['btn_clear']) . '
            </button>
            <button type="button" class="wu-btn wu-btn-danger" id="wuRemoveBtn" disabled>
                <i class="fa-solid fa-user-check"></i> ' . $h($lang->warned['btn_remove']) . '
            </button>
        </div>
    </div>';
}

echo '
</form>
</div>';

stdfoot();