<?php

declare(strict_types=1);

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'online.php');
define('SCRIPTNAME', 'online.php');
define('IN_FORUM', true);

require_once 'global.php';

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_online.php';

define('WOL_VERSION', '1.0');

$lang->load('online');

add_breadcrumb($lang->online['nav_online'], 'online.php');

$wol_css = '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/online.css?v=' . WOL_VERSION . '">';

function wol_kpi(string $icon, string $soft, string $value, string $label, string $sub = ''): string
{
    return '
        <div class="col-6 col-lg-3">
            <div class="wol-card wol-kpi">
                <div class="wol-kpi__icon ' . $soft . '"><i class="fa-solid ' . $icon . '"></i></div>
                <div>
                    <div class="wol-kpi__value">' . $value . '</div>
                    <div class="wol-kpi__label">' . $label . ($sub !== '' ? ' <span class="wol-kpi__sub">' . $sub . '</span>' : '') . '</div>
                </div>
            </div>
        </div>';
}

function wol_paging(int $total, int $perpage): array
{
    global $mybb;

    $pages = max(1, (int)ceil($total / $perpage));
    $page  = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($page < 1 || $page > $pages) {
        $page = 1;
    }

    return [$page, ($page - 1) * $perpage, $pages];
}

// ══════════════════════════════════════════════════════════════════════════
// Who was online today
// ══════════════════════════════════════════════════════════════════════════
if ($mybb->get_input('action') === 'today') {
    add_breadcrumb($lang->online['nav_onlinetoday']);
    $plugins->run_hooks('online_today_start');

    $threshold = TIMENOW - 86400;

    $query = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total, COALESCE(SUM(invisible = 1), 0) AS hidden
         FROM users WHERE lastactive > ?",
        [$threshold]
    );
    $row         = $query ? $db->fetch_array($query) : [];
    $todaycount  = (int)($row['total'] ?? 0);
    $invis_count = (int)($row['hidden'] ?? 0);

    $perpage = (int)($ts_perpage ?? 0) > 0 ? (int)$ts_perpage : 20;
    [$page, $start, $pages] = wol_paging($todaycount, $perpage);

    $query = $db->sql_query_prepared(
        "SELECT id, username, usergroup, invisible, lastactive, avatar, avatardimensions
         FROM users WHERE lastactive > ?
         ORDER BY lastactive DESC LIMIT ?, ?",
        [$threshold, $start, $perpage]
    );

    $can_see_invisible = (int)($usergroups['canviewwolinvis'] ?? 0) === 1
        || (int)($usergroups['canstaffpanel'] ?? 0) === 1;
    $my_uid = (int)($CURUSER['id'] ?? 0);

    $rows = '';
    while ($query && ($u = $db->fetch_array($query))) {
        $uid       = (int)$u['id'];
        $invisible = (int)($u['invisible'] ?? 0) === 1;

        if ($invisible && !$can_see_invisible && $uid !== $my_uid) {
            continue;
        }

        $name   = format_name(htmlspecialchars_uni((string)$u['username']), (int)($u['usergroup'] ?? 1));
        $avatar = format_avatar((string)($u['avatar'] ?? ''), (string)($u['avatardimensions'] ?? ''));
        $last   = (int)($u['lastactive'] ?? 0);

        $rows .= '
            <tr>
                <td data-sort="' . htmlspecialchars(mb_strtolower((string)$u['username']), ENT_QUOTES) . '">
                    <div class="d-flex align-items-center gap-2 wol-user">
                        <img class="wol-avatar" src="' . $avatar['image'] . '" alt="" loading="lazy">
                        <div class="lh-sm">
                            ' . build_profile_link($name, $uid)
                            . ($invisible ? ' <span class="wol-badge wol-soft-muted" title="' . htmlspecialchars($lang->online['tip_invisible'], ENT_QUOTES) . '"><i class="fa-solid fa-eye-slash"></i></span>' : '')
                            . ($uid === $my_uid ? ' <span class="wol-badge wol-soft-primary">' . $lang->online['txt_you'] . '</span>' : '') . '
                            <div class="wol-meta"><i class="fa-solid fa-id-badge me-1"></i>' . $uid . '</div>
                        </div>
                    </div>
                </td>
                <td class="wol-date text-end" data-sort="' . $last . '">
                    <div><i class="fa-regular fa-clock"></i>' . my_datee('relative', $last) . '</div>
                </td>
            </tr>';
    }

    $multipage = multipage($todaycount, $perpage, $page, 'online.php?action=today');

    $plugins->run_hooks('online_today_end');

    stdhead($lang->online['online_today']);
    build_breadcrumb();
    echo $wol_css;
    ?>
<div class="container mt-3 py-4 wol-page">

    <div class="wol-card wol-head mb-3">
        <div class="wol-head__icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div class="flex-grow-1">
            <h1 class="wol-title"><?php echo $lang->online['online_today']; ?></h1>
            <p><?php echo $lang->online['today_subtitle']; ?></p>
        </div>
        <a href="online.php" class="btn btn-outline-secondary wol-pill d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-users me-2"></i><?php echo $lang->online['users_online']; ?>
        </a>
    </div>

    <div class="row g-3 mb-3">
        <?php
        echo wol_kpi('fa-user-clock', 'wol-soft-primary', ts_nf($todaycount), $lang->online['kpi_today']);
        echo wol_kpi('fa-eye-slash', 'wol-soft-muted', ts_nf($invis_count), $lang->online['kpi_invisible']);
        ?>
    </div>

    <div class="wol-card overflow-hidden">
    <?php if ($rows !== ''): ?>
        <div class="wol-toolbar">
            <span><i class="fa-solid fa-list me-1"></i><?php echo ags_fmt($lang->online['txt_showing'], ts_nf($start + 1), ts_nf(min($start + $perpage, $todaycount)), ts_nf($todaycount)); ?></span>
            <?php if ($todaycount > $perpage) { echo '<div>' . $multipage . '</div>'; } ?>
        </div>
        <div class="table-responsive">
            <table class="table wol-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-user"></i><?php echo $lang->online['on_username']; ?></th>
                        <th class="text-end"><i class="fa-solid fa-clock"></i><?php echo $lang->online['col_last_active']; ?></th>
                    </tr>
                </thead>
                <tbody><?php echo $rows; ?></tbody>
            </table>
        </div>
        <?php if ($todaycount > $perpage): ?>
        <div class="wol-toolbar wol-toolbar--bottom">
            <span><?php echo ags_fmt($lang->online['txt_page_of'], $page, $pages); ?></span>
            <div><?php echo $multipage; ?></div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="wol-empty">
            <div class="wol-empty__icon wol-soft-muted"><i class="fa-solid fa-moon"></i></div>
            <h4 class="wol-title"><?php echo $lang->online['empty_today_title']; ?></h4>
            <p class="text-body-secondary mb-0"><?php echo $lang->online['empty_today_text']; ?></p>
        </div>
    <?php endif; ?>
    </div>
</div>
    <?php
    stdfoot();
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
// Who's online now
// ══════════════════════════════════════════════════════════════════════════
$plugins->run_hooks('online_start');

$sortby = $mybb->get_input('sortby');
$order  = match ($sortby) {
    'username' => 'u.username ASC, s.time DESC',
    'location' => 's.location ASC, s.time DESC',
    default    => 'IF(s.uid > 0, 1, 0) DESC, s.time DESC',
};
if (!in_array($sortby, ['username', 'location', 'time'], true)) {
    $sortby = 'time';
}
$refresh_string = $sortby !== 'time' ? '?sortby=' . $sortby : '';

$cutoff_mins = (int)($wolcutoffmins ?? 15) > 0 ? (int)$wolcutoffmins : 15;
$timesearch  = TIMENOW - $cutoff_mins * 60;

// Только залогиненные: гостей и ботов в sessions больше нет.
$query = $db->sql_query_prepared(
    "SELECT s.sid, s.ip, s.uid, s.time, s.location, s.nopermission,
            u.username, u.invisible, u.usergroup, u.avatar, u.avatardimensions
     FROM sessions s
     LEFT JOIN users u ON (s.uid = u.id)
     WHERE s.uid != 0 AND s.time > ?
     ORDER BY {$order}",
    [$timesearch]
);

$list        = [];   // key => row, in SQL order
$n_members   = 0;
$n_hidden    = 0;

while ($query && ($user = $db->fetch_array($query))) {
    $plugins->run_hooks('online_user');

    $uid = (int)($user['uid'] ?? 0);
    if ($uid <= 0) {
        continue;
    }

    $key = 'u' . $uid;
    if (!isset($list[$key])) {
        $n_members++;
        if ((int)($user['invisible'] ?? 0) === 1) {
            $n_hidden++;
        }
    } elseif ((int)$list[$key]['time'] >= (int)$user['time']) {
        continue;
    }

    $user['activity'] = fetch_wol_activity((string)($user['location'] ?? ''), (bool)($user['nopermission'] ?? 0));
    $list[$key] = $user;
}

$total   = count($list);
$perpage = (int)($f_threadsperpage ?? 0) > 0 ? (int)$f_threadsperpage : 20;
[$page, $start, $pages] = wol_paging($total, $perpage);

$online_rows = '';
foreach (array_slice($list, $start, $perpage) as $user) {
    $online_rows .= build_wol_row($user);
}

$multipage = multipage($total, $perpage, $page, 'online.php' . $refresh_string);

$most_online  = $cache->read('mostonline') ?? [];
$record_count = (int)($most_online['numusers'] ?? 0);
$record_date  = !empty($most_online['time']) ? my_datee('relative', (int)$most_online['time']) : '';

$is_staff    = (int)($usergroups['canstaffpanel'] ?? 0) === 1;
$refresh_min = 1;

$plugins->run_hooks('online_end');

stdhead($lang->online['users_online']);
build_breadcrumb();
echo $wol_css;

$sort_tab = static function (string $key, string $icon, string $label) use ($sortby): string {
    $href   = 'online.php' . ($key !== 'time' ? '?sortby=' . $key : '');
    $active = $sortby === $key ? ' active' : '';
    return '<a class="wol-tab' . $active . '" href="' . $href . '"><i class="fa-solid ' . $icon . '"></i>' . $label . '</a>';
};
?>
<div class="container mt-3 py-4 wol-page">

    <div class="wol-card wol-head mb-3">
        <div class="wol-head__icon"><i class="fa-solid fa-users"></i></div>
        <div class="flex-grow-1">
            <h1 class="wol-title"><?php echo $lang->online['users_online']; ?></h1>
            <p><?php echo ags_fmt($lang->online['sec_subtitle'], $cutoff_mins); ?></p>
        </div>
        <span class="wol-badge wol-soft-success d-none d-md-inline-flex">
            <span class="wol-live"></span><?php echo ags_fmt($lang->online['badge_autorefresh'], $refresh_min); ?>
        </span>
    </div>

    <div class="row g-3 mb-3">
        <?php
        echo wol_kpi('fa-user', 'wol-soft-primary', ts_nf($n_members), $lang->online['kpi_members'],
            $n_hidden > 0 ? '(' . ags_fmt($lang->online['kpi_hidden'], ts_nf($n_hidden)) . ')' : '');
        echo wol_kpi('fa-trophy', 'wol-soft-success', ts_nf($record_count), $lang->online['kpi_record'],
            $record_date !== '' ? '(' . $record_date . ')' : '');
        ?>
    </div>

    <div class="wol-card overflow-hidden">
        <div class="wol-toolbar">
            <div class="wol-tabs">
                <span class="wol-meta me-1"><?php echo $lang->online['lbl_sort']; ?></span>
                <?php
                echo $sort_tab('time', 'fa-clock', $lang->online['time']);
                echo $sort_tab('username', 'fa-user', $lang->online['on_username']);
                echo $sort_tab('location', 'fa-location-dot', $lang->online['location']);
                ?>
            </div>
            <div class="d-flex gap-2">
                <a href="online.php?action=today" class="btn btn-sm btn-outline-secondary wol-pill"><i class="fa-solid fa-calendar-day me-1"></i><?php echo $lang->online['online_today']; ?></a>
                <a href="online.php<?php echo $refresh_string; ?>" class="btn btn-sm btn-outline-primary wol-pill"><i class="fa-solid fa-rotate-right me-1"></i><?php echo $lang->online['refresh_page']; ?></a>
            </div>
        </div>

    <?php if ($online_rows !== ''): ?>
        <div class="table-responsive">
            <table class="table wol-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-user"></i><?php echo $lang->online['on_username']; ?></th>
                        <th><i class="fa-solid fa-location-dot"></i><?php echo $lang->online['location']; ?></th>
                        <?php if ($is_staff): ?><th><i class="fa-solid fa-network-wired"></i><?php echo $lang->online['col_ip']; ?></th><?php endif; ?>
                        <th class="text-end"><i class="fa-solid fa-clock"></i><?php echo $lang->online['time']; ?></th>
                    </tr>
                </thead>
                <tbody><?php echo $online_rows; ?></tbody>
            </table>
        </div>

        <?php if ($total > $perpage): ?>
        <div class="wol-toolbar wol-toolbar--bottom">
            <span><?php echo ags_fmt($lang->online['txt_page_of'], $page, $pages); ?></span>
            <div><?php echo $multipage; ?></div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="wol-empty">
            <div class="wol-empty__icon wol-soft-muted"><i class="fa-solid fa-ghost"></i></div>
            <h4 class="wol-title"><?php echo $lang->online['empty_title']; ?></h4>
            <p class="text-body-secondary mb-0"><?php echo $lang->online['empty_text']; ?></p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
setTimeout(function () { location.reload(); }, <?php echo $refresh_min * 60000; ?>);
</script>
<?php
stdfoot();