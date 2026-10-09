<?php
declare(strict_types=1);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border m-3"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('FNC_VERSION', '1.1');

$lang->load('findnotconnectable');

// ---------------------------------------------------------------------
// Lang helpers
// ---------------------------------------------------------------------
/**
 * Fill {1}, {2}… placeholders. $lang->load() turns {N} into %N$s,
 * so both forms are replaced (strtr, not sprintf: a literal % is safe).
 */
if (!function_exists('ags_fmt')) {
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

/** Raw lang text (plain-text contexts: PM, errors, stdhead). */
function fnc_t(string $key): string
{
    global $lang;
    return (string)($lang->findnotconnectable[$key] ?? $key);
}

/** Escaped lang text for HTML output. */
function fnc_e(string $key): string
{
    return htmlspecialchars(fnc_t($key), ENT_QUOTES, 'UTF-8');
}

$do     = (string)($_GET['do'] ?? $_POST['do'] ?? '');
$errors = [];
$PMSEND = false;

// ---------------------------------------------------------------------
// Delete log entry
// ---------------------------------------------------------------------
// FIX: было без CSRF — любой мог удалить лог через GET-ссылку
if ($do === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post_check($_POST['my_post_key'] ?? '');
    $DelID = (int)($_POST['id'] ?? 0);
    if ($DelID > 0) {
        $db->sql_query_prepared('DELETE FROM notconnectablepmlog WHERE id = ?', [$DelID]);
    }
    $do = '';
}

// ---------------------------------------------------------------------
// PM to selected users
// ---------------------------------------------------------------------
if ($do === 'pm2') {
    if (!is_array($_POST['userids'] ?? null)) {
        $errors[] = fnc_t('err_no_users');
        $do = 'showlist';
    } else {
        $msg = trim(fnc_t('pm_body'));
        if ($msg === '') {
            $errors[] = fnc_t('err_empty_msg');
            $do = 'showlist';
        } else {
            require_once INC_PATH . '/functions_pm.php';
            foreach ($_POST['userids'] as $userid) {
                $uid = (int)$userid;
                if ($uid > 0) {
                    send_pm(['touid' => $uid, 'message' => $msg, 'subject' => fnc_t('pm_subject')], 0, true);
                }
            }
            $db->sql_query_prepared(
                'INSERT INTO notconnectablepmlog (user, date) VALUES (?, NOW())',
                [(int)$CURUSER['id']]
            );
            $PMSEND = true;
            $do = '';
        }
    }
}

// ---------------------------------------------------------------------
// PM to all unconnectable (POST)
// ---------------------------------------------------------------------
if ($do === 'pm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post_check($_POST['my_post_key'] ?? '');
    $msg = trim((string)($_POST['msg'] ?? ''));

    if ($msg === '') {
        $errors[] = fnc_t('err_empty_msg');
    } else {
        $query = $db->sql_query_prepared('SELECT DISTINCT userid FROM peers WHERE connectable = "no"');
        if ($db->num_rows($query) > 0) {
            require_once INC_PATH . '/functions_pm.php';
            while ($row = $db->fetch_array($query)) {
                send_pm(['touid' => (int)$row['userid'], 'message' => $msg, 'subject' => fnc_t('pm_subject')], 0, true);
            }
            $db->sql_query_prepared(
                'INSERT INTO notconnectablepmlog (user, date) VALUES (?, NOW())',
                [(int)$CURUSER['id']]
            );
            $PMSEND = true;
            $do = '';
        } else {
            $errors[] = fnc_t('err_no_peers');
        }
    }
}

// ---------------------------------------------------------------------
// Totals for KPI tiles
// ---------------------------------------------------------------------
$statQ = $db->sql_query_prepared(
    'SELECT COUNT(*) AS peers,
            COUNT(DISTINCT userid) AS users,
            COALESCE(SUM(seeder = "yes"), 0) AS seeders
       FROM peers
      WHERE connectable = "no"'
);
$stat         = $db->fetch_array($statQ);
$statPeers    = (int)($stat['peers'] ?? 0);
$statUsers    = (int)($stat['users'] ?? 0);
$statSeeders  = (int)($stat['seeders'] ?? 0);
$seedPct      = $statPeers > 0 ? (int)round($statSeeders / $statPeers * 100) : 0;

$logStatQ  = $db->sql_query_prepared('SELECT COUNT(*) AS cnt, MAX(date) AS last FROM notconnectablepmlog');
$logStat   = $db->fetch_array($logStatQ);
$statPms   = (int)($logStat['cnt'] ?? 0);
$lastPm    = $logStat['last'] ?? null;
$lastPmTxt = $lastPm !== null && $lastPm !== ''
    ? ags_fmt(fnc_t('kpi_last_pm'), my_datee($dateformat ?? '', $lastPm))
    : fnc_t('kpi_never');

// ---------------------------------------------------------------------
// List of unconnectable peers
// ---------------------------------------------------------------------
$peerRows  = [];
$multipage = '';
$page      = 1;
$pages     = 1;
$perpage   = 25;

if ($do === 'showlist') {
    $count = $statUsers;
    $pages = max(1, (int)ceil($count / $perpage));
    $page  = max(1, (int)($mybb->input['page'] ?? 1));
    $start = ($page - 1) * $perpage;
    $multipage = (string)multipage($count, $perpage, $page, $_this_script_ . '&do=showlist&');

    $query = $db->sql_query_prepared('
        SELECT DISTINCT p.torrent, p.userid, p.ip, p.port, p.seeder, p.agent,
               t.name, u.username, g.namestyle
        FROM peers p
        LEFT JOIN torrents t     ON (p.torrent   = t.id)
        LEFT JOIN users u        ON (p.userid    = u.id)
        LEFT JOIN usergroups g   ON (u.usergroup = g.gid)
        WHERE p.connectable = "no"
        ORDER BY u.username
        LIMIT ?, ?
    ', [$start, $perpage]);

    while ($row = $db->fetch_array($query)) {
        $peerRows[] = $row;
    }

    if (!$peerRows) {
        $errors[] = fnc_t('err_no_peers');
        $do = '';
    }
}

// ---------------------------------------------------------------------
// PM log (home)
// ---------------------------------------------------------------------
$logRows = [];
if ($do === '') {
    $Query = $db->sql_query_prepared('
        SELECT n.id, n.user, n.date, u.username, g.namestyle
        FROM notconnectablepmlog n
        LEFT JOIN users u      ON (n.user      = u.id)
        LEFT JOIN usergroups g ON (u.usergroup = g.gid)
        ORDER BY n.date DESC
    ');
    while ($log = $db->fetch_array($Query)) {
        $logRows[] = $log;
    }
}

// ---------------------------------------------------------------------
// JS strings (js_* → without prefix)
// ---------------------------------------------------------------------
$jsLang = [];
foreach ((array)($lang->findnotconnectable ?? []) as $k => $v) {
    if (is_string($k) && str_starts_with($k, 'js_')) {
        $jsLang[substr($k, 3)] = (string)$v;
    }
}

$postCode = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES, 'UTF-8');
$navTabs  = [
    ''         => ['fa-clock-rotate-left', 'btn_home'],
    'pm'       => ['fa-envelope',          'btn_pm'],
    'showlist' => ['fa-list',              'btn_showlist'],
];

// ---------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------
stdhead(fnc_t('pane_head'));

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/findnotconnectable.css?v=' . FNC_VERSION . '">';
echo '<script>const AGS_LANG = ' . json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js" defer></script>';
echo '<script src="' . $BASEURL . '/admin/scripts/findnotconnectable.js?v=' . FNC_VERSION . '" defer></script>';
?>

<div class="container mt-3 py-4 fnc-page">

    <!-- Header -->
    <div class="fnc-card fnc-head mb-3">
        <div class="fnc-head__icon"><i class="fa-solid fa-plug-circle-xmark"></i></div>
        <div class="flex-grow-1">
            <h1 class="fnc-title"><?php echo fnc_e('pane_head'); ?></h1>
            <p><?php echo fnc_e('sec_subtitle'); ?></p>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="fnc-card fnc-kpi">
                <div class="fnc-kpi__icon fnc-soft-danger"><i class="fa-solid fa-user-shield"></i></div>
                <div>
                    <div class="fnc-kpi__value"><?php echo number_format($statUsers); ?></div>
                    <div class="fnc-kpi__label"><?php echo fnc_e('kpi_users'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fnc-card fnc-kpi">
                <div class="fnc-kpi__icon fnc-soft-warning"><i class="fa-solid fa-network-wired"></i></div>
                <div>
                    <div class="fnc-kpi__value"><?php echo number_format($statPeers); ?></div>
                    <div class="fnc-kpi__label"><?php echo fnc_e('kpi_peers'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fnc-card fnc-kpi">
                <div class="fnc-kpi__icon fnc-soft-success"><i class="fa-solid fa-seedling"></i></div>
                <div>
                    <div class="fnc-kpi__value"><?php echo number_format($statSeeders); ?></div>
                    <div class="fnc-kpi__label"><?php echo fnc_e('kpi_seeders'); ?> <span class="fnc-kpi__sub">(<?php echo $seedPct; ?>%)</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fnc-card fnc-kpi">
                <div class="fnc-kpi__icon fnc-soft-primary"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <div class="fnc-kpi__value"><?php echo number_format($statPms); ?></div>
                    <div class="fnc-kpi__label"><?php echo fnc_e('kpi_pms'); ?></div>
                    <div class="fnc-kpi__sub"><i class="fa-regular fa-clock me-1"></i><?php echo htmlspecialchars($lastPmTxt, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="fnc-nav mb-3">
        <?php foreach ($navTabs as $tabDo => [$tabIco, $tabKey]):
            $tabUrl = $_this_script_ . ($tabDo !== '' ? '&amp;do=' . $tabDo : ''); ?>
            <a href="<?php echo $tabUrl; ?>" class="btn fnc-pill fnc-nav__link<?php echo $do === $tabDo ? ' is-active' : ''; ?>"<?php echo $do === $tabDo ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid <?php echo $tabIco; ?> me-1"></i><?php echo fnc_e($tabKey); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Alerts -->
    <?php if ($errors): ?>
        <div class="alert fnc-alert fnc-soft-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?php echo implode('<br>', array_map(static fn(string $e): string => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors)); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo fnc_e('lbl_close'); ?>"></button>
        </div>
    <?php endif; ?>
    <?php if ($PMSEND): ?>
        <div class="alert fnc-alert fnc-soft-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check"></i>
            <div><?php echo fnc_e('flash_pm_sent'); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo fnc_e('lbl_close'); ?>"></button>
        </div>
    <?php endif; ?>

<?php if ($do === 'showlist'): ?>

    <!-- Unconnectable users list -->
    <div class="fnc-card">
        <form method="post" action="<?php echo $_this_script_; ?>&amp;do=pm2" id="userlistForm" class="fnc-confirm" data-confirm="selected">
            <input type="hidden" name="my_post_key" value="<?php echo $postCode; ?>">

            <div class="fnc-toolbar">
                <span><i class="fa-solid fa-list me-1"></i><b><?php echo fnc_e('pane_list'); ?></b>
                    <span class="fnc-meta ms-2"><?php echo htmlspecialchars(ags_fmt(fnc_t('hint_total'), number_format($statUsers)), ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <?php if ($pages > 1) { echo '<div>' . $multipage . '</div>'; } ?>
            </div>

            <div class="table-responsive">
                <table class="table fnc-table">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-user"></i><?php echo fnc_e('col_username'); ?></th>
                            <th><i class="fa-solid fa-magnet"></i><?php echo fnc_e('col_torrent'); ?></th>
                            <th><i class="fa-solid fa-ethernet"></i><?php echo fnc_e('col_ip'); ?></th>
                            <th><i class="fa-solid fa-desktop"></i><?php echo fnc_e('col_client'); ?></th>
                            <th class="text-center"><i class="fa-solid fa-seedling"></i><?php echo fnc_e('col_seeder'); ?></th>
                            <th class="text-center fnc-col-check">
                                <input type="checkbox" id="selectAll" class="form-check-input"
                                       title="<?php echo fnc_e('lbl_select_all'); ?>" aria-label="<?php echo fnc_e('lbl_select_all'); ?>">
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($peerRows as $row):
                        $isSeeder = $row['seeder'] === 'yes'; ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_profile_link($row['userid']); ?>" class="fnc-user"><?php echo get_user_color($row['username'], $row['namestyle']); ?></a>
                                <div class="fnc-meta"><i class="fa-solid fa-id-badge me-1"></i><?php echo (int)$row['userid']; ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fnc-torrent-ico fnc-soft-danger"><i class="fa-solid fa-magnet"></i></span>
                                    <div class="lh-sm">
                                        <a href="<?php echo $BASEURL; ?>/details.php?id=<?php echo (int)$row['torrent']; ?>" class="fnc-torrent"><?php echo cutename($row['name'], 60); ?></a>
                                        <div class="fnc-meta"><i class="fa-solid fa-hashtag"></i><?php echo (int)$row['torrent']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="fnc-ip"><code><?php echo htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8'); ?>:<?php echo (int)$row['port']; ?></code></td>
                            <td class="fnc-client"><?php echo htmlspecialchars($row['agent'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center">
                                <?php if ($isSeeder): ?>
                                    <span class="fnc-badge fnc-soft-success"><i class="fa-solid fa-seedling"></i><?php echo fnc_e('lbl_yes'); ?></span>
                                <?php else: ?>
                                    <span class="fnc-badge fnc-soft-muted"><i class="fa-solid fa-circle-minus"></i><?php echo fnc_e('lbl_no'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fnc-col-check">
                                <input type="checkbox" name="userids[]" value="<?php echo (int)$row['userid']; ?>"
                                       class="userCheckbox form-check-input" aria-label="<?php echo fnc_e('lbl_select_user'); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
            <div class="fnc-toolbar fnc-toolbar--bottom">
                <span><?php echo htmlspecialchars(ags_fmt(fnc_t('txt_page_of'), $page, $pages), ENT_QUOTES, 'UTF-8'); ?></span>
                <div><?php echo $multipage; ?></div>
            </div>
            <?php endif; ?>

            <!-- Sticky action bar -->
            <div class="fnc-actionbar">
                <span class="fnc-meta"><i class="fa-solid fa-circle-info me-1"></i><?php echo fnc_e('hint_selected_pm'); ?></span>
                <div class="d-flex align-items-center gap-2">
                    <span class="fnc-badge fnc-soft-primary" id="fncSelCount">0</span>
                    <button type="submit" class="btn btn-primary fnc-pill">
                        <i class="fa-solid fa-paper-plane me-1"></i><?php echo fnc_e('btn_pm_selected'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

<?php elseif ($do === 'pm'): ?>

    <!-- Mass PM form -->
    <div class="fnc-card">
        <form method="post" action="<?php echo $_this_script_; ?>&amp;do=pm" class="fnc-confirm" data-confirm="mass" data-count="<?php echo $statUsers; ?>">
            <input type="hidden" name="my_post_key" value="<?php echo $postCode; ?>">

            <div class="fnc-toolbar">
                <span><i class="fa-solid fa-envelope me-1"></i><b><?php echo fnc_e('lbl_pm_form'); ?></b></span>
                <span class="fnc-badge fnc-soft-info"><i class="fa-solid fa-users"></i><?php echo htmlspecialchars(ags_fmt(fnc_t('hint_recipients'), number_format($statUsers)), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="fnc-body">
                <label class="form-label" for="fncMsg"><i class="fa-solid fa-message me-1"></i><?php echo fnc_e('lbl_message'); ?></label>
                <textarea name="msg" id="fncMsg" class="form-control fnc-textarea" rows="14"
                          placeholder="<?php echo fnc_e('ph_msg'); ?>"><?php echo fnc_e('pm_body'); ?></textarea>
                <div class="fnc-meta mt-2"><i class="fa-solid fa-circle-info me-1"></i><?php echo fnc_e('hint_pm_form'); ?></div>
            </div>

            <!-- Sticky action bar -->
            <div class="fnc-actionbar justify-content-end">
                <button type="reset" class="btn btn-outline-secondary fnc-pill">
                    <i class="fa-solid fa-rotate-left me-1"></i><?php echo fnc_e('btn_reset'); ?>
                </button>
                <button type="submit" class="btn btn-primary fnc-pill">
                    <i class="fa-solid fa-paper-plane me-1"></i><?php echo fnc_e('btn_send'); ?>
                </button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- PM log -->
    <div class="fnc-card">
        <div class="fnc-toolbar">
            <span><i class="fa-solid fa-clock-rotate-left me-1"></i><b><?php echo fnc_e('pane_log'); ?></b></span>
        </div>

        <?php if ($logRows): ?>
        <div class="table-responsive">
            <table class="table fnc-table">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-user-tie"></i><?php echo fnc_e('col_sender'); ?></th>
                        <th><i class="fa-solid fa-calendar-day"></i><?php echo fnc_e('col_date'); ?></th>
                        <th class="text-end"><i class="fa-solid fa-gear"></i><?php echo fnc_e('col_action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logRows as $log): ?>
                    <tr>
                        <td><a href="<?php echo get_profile_link($log['user']); ?>" class="fnc-user"><?php echo get_profile_link($log['user']); ?></a></td>
                        <td class="fnc-date">
                            <div><i class="fa-solid fa-calendar-day"></i><?php echo my_datee($dateformat ?? '', $log['date']); ?></div>
                            <div class="fnc-meta"><i class="fa-regular fa-clock"></i><?php echo my_datee($timeformat ?? '', $log['date']); ?></div>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?php echo $_this_script_; ?>&amp;do=delete" class="d-inline fnc-confirm" data-confirm="delete">
                                <input type="hidden" name="my_post_key" value="<?php echo $postCode; ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$log['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger fnc-pill">
                                    <i class="fa-solid fa-trash-can me-1"></i><?php echo fnc_e('btn_delete'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="fnc-empty">
            <div class="fnc-empty__icon fnc-soft-muted"><i class="fa-solid fa-inbox"></i></div>
            <h4 class="fnc-title"><?php echo fnc_e('hint_nolog'); ?></h4>
            <p class="text-body-secondary mb-0"><?php echo fnc_e('hint_nolog_text'); ?></p>
        </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<?php
stdfoot();
