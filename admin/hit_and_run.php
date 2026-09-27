<?php

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}


define('TSHRD_TOOL', 'v1.3');

require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_multipage.php';
include_once $rootpath . '/admin/include/global_config.php';
include_once $rootpath . '/admin/include/staff_languages.php';

global $mybb;

/**
 * Проверяет CSRF-токен для мутирующих POST-действий.
 */
function hnr_verify_csrf(): bool
{
    return verify_post_check($_POST['my_post_key'] ?? '');
}

/**
 * Возвращает только те пары userid|torrentid из присланных клиентом,
 * которые реально являются hit-and-run нарушением по текущим правилам —
 * иначе клиент мог бы забанить/предупредить произвольного юзера, просто
 * подставив его ID в форму.
 *
 * @param array<array{0:int,1:int}> $pairs
 * @return array<string,bool> ключи вида "userid|torrentid"
 */
function hnr_get_valid_pairs(object $db, array $pairs, array $skip_usergroups): array
{
    $pairs = array_values(array_filter($pairs, fn($p) => $p[0] > 0 && $p[1] > 0));
    if (empty($pairs)) {
        return [];
    }

    $conditions = [];
    foreach ($pairs as [$uid, $tid]) {
        $conditions[] = '(s.userid=' . (int)$uid . ' AND s.torrentid=' . (int)$tid . ')';
    }
    $skip = implode(',', array_map('intval', $skip_usergroups));

    $sql = "SELECT s.userid, s.torrentid, s.uploaded, s.downloaded
            FROM snatched s
            INNER JOIN users u ON (s.userid=u.id)
            LEFT JOIN torrents t ON (s.torrentid=t.id)
            WHERE s.finished='yes' AND s.seeder='no'
              AND u.enabled='yes' AND u.usergroup NOT IN ({$skip})
              AND u.ustatus='confirmed'
              AND t.visible='yes'
              AND s.downloaded > 0
              AND (" . implode(' OR ', $conditions) . ')';

    $result = $db->sql_query_prepared($sql);
    $valid = [];
    while ($result && ($row = $db->fetch_array($result))) {
        $downloaded = (float)$row['downloaded'];
        $ratio = $downloaded > 0 ? number_format((float)$row['uploaded'] / $downloaded, 2) : '∞';
        $valid[(int)$row['userid'] . '|' . (int)$row['torrentid']] = $ratio;
    }

    return $valid;
}

// PHP 8.5 совместимость - объявляем переменные
$success_msg = '';
$keywords = '';
$searchtype = 0;

$torrentid = ((isset($_GET['torrentid']) && is_valid_id($_GET['torrentid'])) ? intval($_GET['torrentid']) : ((isset($_POST['torrentid']) && is_valid_id($_POST['torrentid'])) ? intval($_POST['torrentid']) : 0));
$type = ((isset($_GET['type']) && $_GET['type'] === 'seedtime') ? 'seedtime' : 'ratio');
$eol = PHP_EOL;

// Читаем и POST, и GET - форма "Jump to Page" в multipage() отправляет
// через POST, а обычные ссылки-страницы (1,2,3...) идут через GET.
$page = isset($_POST['page']) && $_POST['page'] > 0 ? intval($_POST['page'])
      : (isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1);
$per_page = $config['ts_hit_and_run']['query_limit'] ?? 20;

$skip_usergroups_arr = $config['ts_hit_and_run']['skip_usergroups'] ?? [UC_BANNED, UC_VIP, UC_ADMINISTRATOR, UC_SYSOP, UC_MODERATOR];
$skip_usergroups = implode(',', $skip_usergroups_arr);

// Обработка POST запросов
if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
    // Обработка BAN
    if (isset($_POST['ban']) && !empty($_POST['user_torrent_ids']) && is_array($_POST['user_torrent_ids'])) {
        if (!hnr_verify_csrf()) {
            stderr('<div class="alert alert-danger border"><i class="fas fa-shield-alt me-2"></i><strong>Error!</strong> Security check failed. Please refresh the page and try again.</div>');
        }

        $pairs = [];
        foreach ($_POST['user_torrent_ids'] as $work) {
            $worknow = explode('|', (string)$work);
            $pairs[] = [(int)($worknow[0] ?? 0), (int)($worknow[1] ?? 0)];
        }
        $valid_pairs = hnr_get_valid_pairs($db, $pairs, $skip_usergroups_arr);

        $userids = [];
        foreach ($pairs as [$uid, $tid]) {
            if (isset($valid_pairs[$uid . '|' . $tid])) {
                $userids[] = $uid;
            }
        }
        if (!empty($userids)) {
            $userids   = array_values(array_unique($userids));
            $ids_ph    = implode(',', array_fill(0, count($userids), '?'));
            $modcomment = gmdate('Y-m-d') . ' - Banned by ' . ($CURUSER['username'] ?? 'System') . '. (TS Hit & Run Staff Tool)' . $eol;
            $db->sql_query_prepared(
                "UPDATE users SET enabled='no', usergroup=?, modcomment=CONCAT(?, modcomment) WHERE id IN (0,{$ids_ph})",
                [UC_BANNED, $modcomment, ...$userids]
            );
            $success_msg = '<div class="alert alert-success border"><i class="fas fa-check-circle me-2"></i><strong>Success!</strong> Users have been banned successfully!</div>';
        } else {
            $success_msg = '<div class="alert alert-warning border"><i class="fas fa-exclamation-triangle me-2"></i><strong>Warning!</strong> No valid hit-and-run users were found in the selection.</div>';
        }
    } 
    // Выполнение предупреждения (после ввода сообщения)
    elseif (isset($_POST['warn_execute']) && !empty($_POST['user_torrent_ids'])) {
        if (!hnr_verify_csrf()) {
            stderr('<div class="alert alert-danger border"><i class="fas fa-shield-alt me-2"></i><strong>Error!</strong> Security check failed. Please refresh the page and try again.</div>');
        }

        $user_torrent_ids = explode(',', (string)$_POST['user_torrent_ids']);
        require_once INC_PATH . '/functions_pm.php';

        $pairs = [];
        foreach ($user_torrent_ids as $work) {
            $arrays = explode('|', (string)$work);
            $pairs[] = [(int)($arrays[0] ?? 0), (int)($arrays[1] ?? 0)];
        }
        $valid_pairs = hnr_get_valid_pairs($db, $pairs, $skip_usergroups_arr);

        $warned_count = 0;
        foreach ($pairs as [$userid, $warn_torrentid]) {
            $key = $userid . '|' . $warn_torrentid;
            if (!isset($valid_pairs[$key])) {
                continue; // не реальный hit-and-run — присланному клиентом значению не доверяем
            }
            $ratio = $valid_pairs[$key]; // ratio считаем на сервере, а не берём из POST

            $db->sql_query_prepared(
                "REPLACE INTO hit_and_run (userid,torrentid,added) VALUES (?, ?, ?)",
                [$userid, $warn_torrentid, TIMENOW]
            );
            $msg = str_replace(
                ['{torrentinfo}', '{torrentdownloadinfo}', '{showratio}'],
                ['[URL]' . $BASEURL . '/details.php?id=' . $warn_torrentid . '[/URL]', '[URL]' . $BASEURL . '/download.php?id=' . $warn_torrentid . '[/URL]', $ratio],
                (string)$_POST['warnmessage']
            );
            $pm = [
                'subject' => '⚠️ Warning!',
                'message' => $msg,
                'touid' => $userid
            ];
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
            $modcomment = gmdate('Y-m-d') . ' - Warned by ' . ($CURUSER['username'] ?? 'System') . '. Torrent ID: ' . $warn_torrentid . ' (TS Hit & Run Staff Tool)' . $eol;
            $db->sql_query_prepared(
                "UPDATE users SET timeswarned = timeswarned + 1, modcomment=CONCAT(?, modcomment) WHERE id = ?",
                [$modcomment, $userid]
            );
            $warned_count++;
        }
        $success_msg = '<div class="alert alert-warning border"><i class="fas fa-exclamation-triangle me-2"></i><strong>Warning!</strong> ' . $warned_count . ' user(s) have been warned successfully!</div>';
    } 
    // Форма текста предупреждения (нажата «Warn Selected» на основной странице)
    elseif (isset($_POST['warn']) && !empty($_POST['user_torrent_ids']) && is_array($_POST['user_torrent_ids'])) {
        if (!hnr_verify_csrf()) {
            stderr('<div class="alert alert-danger border"><i class="fas fa-shield-alt me-2"></i><strong>Error!</strong> Security check failed. Please refresh the page and try again.</div>');
        }

        $selected_ids   = array_map('strval', $_POST['user_torrent_ids']);
        $selected_count = count($selected_ids);
        // Раньше intval($_POST['page']) без проверки — warning, если page не пришёл
        $back_page      = max(1, (int)($_POST['page'] ?? 1));
        $default_msg    = $adminlang['ts_hit_and_run'] ?? "⚠️ WARNING: Hit & Run Violation\n\nTorrent: {torrentinfo}\nRatio: {showratio}\n\nYou have been warned for not meeting the minimum seeding requirements.\n\nPlease download and seed: {torrentdownloadinfo}\n\nFailure to comply may result in further actions.\n\nRegards,\nStaff Team";

        stdhead('Hit & Run — Send Warnings');
        ?>
        <link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/hit_and_run.css?v=<?= @filemtime($rootpath . '/admin/templates/hit_and_run.css') ?: 1 ?>">

        <div class="container py-4 hr hr-compose">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <form method="post" action="<?= $_this_script_ ?>" class="hr-card">
                        <input type="hidden" name="warn_execute" value="1">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <input type="hidden" name="page" value="<?= $back_page ?>">
                        <?= $torrentid ? '<input type="hidden" name="torrentid" value="' . $torrentid . '">' : '' ?>
                        <input type="hidden" name="user_torrent_ids" value="<?= htmlspecialchars(implode(',', $selected_ids)) ?>">

                        <div class="hr-head">
                            <span class="hr-head-icon"><i class="fas fa-envelope-open-text"></i></span>
                            <div>
                                <h1 class="hr-title">Send Hit &amp; Run Warnings</h1>
                                <div class="hr-sub"><i class="fas fa-users me-1"></i><b><?= $selected_count ?></b> selected snatch(es) will receive a PM</div>
                            </div>
                        </div>

                        <div class="p-3 p-md-4">
                            <label class="form-label fw-semibold" for="warnmessage"><i class="fas fa-pen-to-square me-2 text-body-secondary"></i>Message</label>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-body-secondary">
                                <span><i class="fas fa-puzzle-piece me-1"></i>Placeholders (click to insert):</span>
                                <span class="hr-token" data-token="{torrentinfo}"><i class="fas fa-magnet"></i>{torrentinfo}</span>
                                <span class="hr-token" data-token="{torrentdownloadinfo}"><i class="fas fa-download"></i>{torrentdownloadinfo}</span>
                                <span class="hr-token" data-token="{showratio}"><i class="fas fa-scale-balanced"></i>{showratio}</span>
                            </div>
                            <textarea name="warnmessage" id="warnmessage" class="form-control hr-msg" rows="11"><?= htmlspecialchars($default_msg) ?></textarea>
                            <div class="form-text"><i class="fas fa-circle-info me-1"></i>The ratio is calculated on the server; users already warned in the last 7 days are skipped.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-2 px-3 px-md-4 pb-4">
                            <a href="<?= $_this_script_ . '&page=' . $back_page . ($torrentid ? '&torrentid=' . $torrentid : '') ?>" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                            <div class="d-flex gap-2">
                                <button type="reset" class="btn btn-outline-secondary rounded-pill px-3"><i class="fas fa-rotate-left me-1"></i>Reset</button>
                                <button type="submit" class="btn btn-warning rounded-pill px-4"><i class="fas fa-paper-plane me-1"></i>Send <?= $selected_count ?> Warning(s)</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="<?= $BASEURL ?>/admin/scripts/hit_and_run.js?v=<?= @filemtime($rootpath . '/admin/scripts/hit_and_run.js') ?: 1 ?>" defer></script>
        <?php
        stdfoot();
        exit();
    }
}

$alreadywarnedarrays = [];
$query = $db->sql_query_prepared('SELECT userid,torrentid,added FROM hit_and_run WHERE added > ?', [TIMENOW - 60 * 60 * (7 * 24)]);
if ($query && $db->num_rows($query) > 0) {
    while ($alreadywarned = $db->fetch_array($query)) {
        $alreadywarnedarrays[(int)$alreadywarned['userid']][(int)$alreadywarned['torrentid']] = (int)$alreadywarned['added'];
    }
}

$extraquery = '';
$extraquery2 = '';
$extraquery_params = [];
$extraquery2_params = [];
$hiddenvalues = '';
$link = '';
$orjlink = '';
$active_filters = []; // чипы «фильтр: … ×»

if (is_valid_id($torrentid)) {
    $extraquery = ' AND s.torrentid=?';
    $extraquery_params[] = $torrentid;
    $hiddenvalues = '<input type="hidden" name="torrentid" value="' . $torrentid . '">';
    $link = $orjlink = 'torrentid=' . $torrentid . '&amp;';
    $active_filters[] = ['fa-magnet', 'Torrent #' . $torrentid, $_this_script_ . '&type=' . $type];
}

if ($page > 1) {
    $hiddenvalues .= '<input type="hidden" name="page" value="' . $page . '">';
}

if (isset($_GET['show_by_userid'])) {
    $userid = intval($_GET['show_by_userid']);
    if (is_valid_id($userid)) {
        $extraquery2 = ' AND u.id=?';
        $extraquery2_params = [$userid];
        $active_filters[] = ['fa-user', 'User #' . $userid, $_this_script_ . '&type=' . $type . ($torrentid ? '&torrentid=' . $torrentid : '')];
    }
}

require_once INC_PATH . '/functions_icons.php';

// Поиск
if (isset($_POST['do_search']) && !empty($_POST['keywords'])) {
    $keywords = trim((string)$_POST['keywords']);
    $searchtype = intval($_POST['searchtype'] ?? 0);
    switch ($searchtype) {
        case 1:
            $extraquery2 = ' AND u.username=?';
            $extraquery2_params = [$keywords];
            break;
        case 2:
            $extraquery2 = ' AND u.id=?';
            $extraquery2_params = [(int)$keywords];
            break;
        case 3:
            $extraquery2 = ' AND s.torrentid=?';
            $extraquery2_params = [(int)$keywords];
            break;
    }
    if ($extraquery2 !== '') {
        $active_filters[] = ['fa-magnifying-glass', 'Search: ' . htmlspecialchars($keywords), $_this_script_ . '&type=' . $type];
    }
}

// Тип отбора. min_share_ratio приводим к float: значение подставляется прямо в SQL
$min_ratio = (float)($config['ts_hit_and_run']['min_share_ratio'] ?? 1.0);
if ($type === 'seedtime') {
    $typequery  = '(s.seedtime = 0 OR s.seedtime < s.leechtime)';
    $link       = ($link ? $link . '&' : '') . 'type=seedtime';
    $type_title = 'Seed time below leech time';
    $type_icon  = 'fa-hourglass-half';
} else {
    $typequery  = 's.uploaded/s.downloaded < ' . $min_ratio;
    $link       = ($link ? $link . '&' : '') . 'type=ratio';
    $type_title = 'Ratio below ' . number_format($min_ratio, 2);
    $type_icon  = 'fa-chart-line';
}

$base_where = 'WHERE s.finished=\'yes\' AND s.seeder=\'no\'
AND u.enabled=\'yes\' AND u.usergroup NOT IN (' . $skip_usergroups . ')
AND u.ustatus=\'confirmed\'
AND t.visible=\'yes\'
AND s.downloaded > 0
AND ' . $typequery . $extraquery . $extraquery2;
$where_params = [...$extraquery_params, ...$extraquery2_params];

// Подсчёт: записи + уникальные пользователи одним запросом
$count_query = $db->sql_query_prepared(
    'SELECT COUNT(*) AS total, COUNT(DISTINCT s.userid) AS users
     FROM snatched s
     INNER JOIN users u ON (s.userid=u.id)
     LEFT JOIN torrents t ON (s.torrentid=t.id) ' . $base_where,
    $where_params
);
$total_count = 0;
$total_users = 0;
if ($count_query && ($result = $db->fetch_array($count_query))) {
    $total_count = (int)$result['total'];
    $total_users = (int)$result['users'];
}

$per_page = max(1, (int)$per_page);
$offset   = ($page - 1) * $per_page;

$base_url  = rtrim(str_replace('&&', '&', $_this_script_ . '&' . $link), '&');
$multipage = multipage($total_count, $per_page, (int)$page, $base_url);

$query = $db->sql_query_prepared(
    'SELECT s.torrentid, s.seedtime, s.leechtime, s.userid, s.downloaded, s.uploaded,
            t.name, t.seeders, t.leechers,
            u.timeswarned, u.username, u.usergroup, u.enabled, u.donor, u.leechwarn, u.warned,
            u.avatar, u.avatardimensions,
            g.namestyle, g.title
     FROM snatched s
     INNER JOIN users u ON (s.userid=u.id)
     LEFT JOIN torrents t ON (s.torrentid=t.id)
     LEFT JOIN usergroups g ON (u.usergroup=g.gid) ' . $base_where . '
     ORDER BY u.timeswarned DESC, s.uploaded/s.downloaded ASC
     LIMIT ' . (int)$offset . ', ' . (int)$per_page,
    $where_params
);

$ban_threshold = (int)($ban_user_limit ?? 7);
$criticallimit = $ban_threshold - 1;
$already_warned_count = array_sum(array_map('count', $alreadywarnedarrays));

require_once INC_PATH . '/functions_mkprettytime.php';

stdhead('Hit & Run Detection Tool');
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/hit_and_run.css?v=<?= @filemtime($rootpath . '/admin/templates/hit_and_run.css') ?: 1 ?>">

<div class="container mt-3 mb-4 hr">

    <?php if (!empty($success_msg)) echo $success_msg; ?>

    <!-- Заголовок -->
    <div class="hr-card mb-3">
        <div class="hr-head">
            <span class="hr-head-icon"><i class="fas fa-person-running"></i></span>
            <div>
                <h1 class="hr-title">Hit &amp; Run Detection</h1>
                <div class="hr-sub">Finished downloads that are no longer seeded and break the share rules</div>
            </div>
            <span class="hr-ver"><i class="fas fa-toolbox me-1"></i><?= TSHRD_TOOL ?></span>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="hr-card hr-stat"><span class="hr-stat-icon ic-red"><i class="fas fa-list-check"></i></span>
                <div><div class="hr-stat-label">Violations</div><div class="hr-stat-value"><?= number_format($total_count) ?></div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="hr-card hr-stat"><span class="hr-stat-icon ic-blue"><i class="fas fa-users"></i></span>
                <div><div class="hr-stat-label">Users</div><div class="hr-stat-value"><?= number_format($total_users) ?></div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="hr-card hr-stat"><span class="hr-stat-icon ic-amber"><i class="fas fa-envelope-circle-check"></i></span>
                <div><div class="hr-stat-label">Warned · 7 days</div><div class="hr-stat-value"><?= number_format($already_warned_count) ?></div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="hr-card hr-stat"><span class="hr-stat-icon ic-slate"><i class="fas fa-gavel"></i></span>
                <div><div class="hr-stat-label">Ban threshold</div><div class="hr-stat-value"><?= $ban_threshold ?> warns</div></div></div>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="hr-card p-3 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="hr-seg" role="tablist">
                <a href="<?= $_this_script_ . '&' . $orjlink . 'type=ratio' ?>" class="<?= $type === 'ratio' ? 'active' : '' ?>"><i class="fas fa-chart-line me-1"></i>Low ratio</a>
                <a href="<?= $_this_script_ . '&' . $orjlink . 'type=seedtime' ?>" class="<?= $type === 'seedtime' ? 'active' : '' ?>"><i class="fas fa-hourglass-half me-1"></i>Seed time</a>
            </div>

            <form method="post" action="<?= $_this_script_ . '&type=' . $type ?>" class="hr-search d-flex flex-wrap gap-2">
                <div class="input-group input-group-sm" style="width:auto">
                    <span class="input-group-text rounded-start-pill"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control rounded-0" name="keywords" value="<?= htmlspecialchars($keywords) ?>" placeholder="Search..." style="min-width:170px">
                    <select class="form-select rounded-0 rounded-end-pill" name="searchtype" style="max-width:150px">
                        <option value="1"<?= $searchtype == 1 ? ' selected' : '' ?>>Username</option>
                        <option value="2"<?= $searchtype == 2 ? ' selected' : '' ?>>User ID</option>
                        <option value="3"<?= $searchtype == 3 || $searchtype == 0 ? ' selected' : '' ?>>Torrent ID</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3" name="do_search"><i class="fas fa-search me-1"></i>Search</button>
            </form>
        </div>

        <?php if ($active_filters): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="hr-muted"><i class="fas fa-filter me-1"></i>Filters:</span>
                <?php foreach ($active_filters as [$ico, $label, $clear_url]): ?>
                    <span class="hr-chip"><i class="fas <?= $ico ?>"></i><?= $label ?><a href="<?= $clear_url ?>" title="Remove filter" aria-label="Remove filter"><i class="fas fa-xmark"></i></a></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= $_this_script_ ?>" name="update" id="hrForm">
        <?= $hiddenvalues ?>
        <input type="hidden" name="page" value="<?= $page ?>">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
        <input type="hidden" name="ban" id="hrBanField" value="" disabled>

        <!-- Панель действий -->
        <div class="hr-card hr-toolbar mb-3">
            <div class="hr-selected">
                <i class="fas <?= $type_icon ?> me-1"></i><?= $type_title ?> ·
                <i class="fas fa-square-check ms-1 me-1"></i>Selected: <b id="hrSelCount">0</b>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" name="warn" value="1" class="btn btn-sm btn-warning rounded-pill px-3 hr-act" disabled>
                    <i class="fas fa-triangle-exclamation me-1"></i>Warn
                </button>
                <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 hr-act" id="hrBanBtn" disabled>
                    <i class="fas fa-ban me-1"></i>Ban
                </button>
            </div>
        </div>

        <div class="hr-card overflow-hidden">
            <div class="table-responsive">
                <table class="table hr-table">
                    <thead>
                        <tr>
                            <th style="width:48px">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="hrCheckAll" aria-label="Select all"></div>
                            </th>
                            <th><i class="fas fa-user"></i>User</th>
                            <th><i class="fas fa-magnet"></i>Torrent</th>
                            <th><i class="fas fa-arrow-up"></i>Uploaded / Seed</th>
                            <th><i class="fas fa-arrow-down"></i>Downloaded / Leech</th>
                            <th><i class="fas fa-scale-balanced"></i>Ratio</th>
                            <th><i class="fas fa-triangle-exclamation"></i>Warns</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($query && $db->num_rows($query) > 0): ?>
                        <?php while ($row = $db->fetch_array($query)): ?>
                            <?php
                            $uid       = (int)$row['userid'];
                            $tid       = (int)$row['torrentid'];
                            $is_warned = isset($alreadywarnedarrays[$uid][$tid]);
                            $rawName   = (string)$row['username'];
                            $safeName  = htmlspecialchars_uni($rawName);
                            $initial   = htmlspecialchars_uni(mb_strtoupper(mb_substr($rawName, 0, 1)));

                            $up    = (float)$row['uploaded'];
                            $down  = (float)$row['downloaded'];
                            $ratio_val = $down > 0 ? $up / $down : INF;
                            $ratio = is_finite($ratio_val) ? number_format($ratio_val, 2) : '∞';
                            $ratio_bad = $ratio_val < $min_ratio;

                            $seed  = (int)$row['seedtime'];
                            $leech = (int)$row['leechtime'];
                            $seed_pct = ($seed + $leech) > 0 ? (int)round($seed / ($seed + $leech) * 100) : 0;

                            $tw = (int)$row['timeswarned'];
                            // Раньше для 1…(порог-2) класс был пустым: badge без bg-* — белый текст на белом
                            [$wcls, $wico] = match (true) {
                                $tw === 0              => ['w-none', 'fa-circle-check'],
                                $tw >= $ban_threshold  => ['w-ban',  'fa-gavel'],
                                $tw >= $criticallimit  => ['w-crit', 'fa-fire'],
                                default                => ['w-some', 'fa-triangle-exclamation'],
                            };

                            $av = format_avatar($row['avatar'] ?? '', $row['avatardimensions'] ?? '');
                            $avatarHtml = (!empty($av['image']) && empty($av['is_placeholder']))
                                ? '<img class="hr-avatar" src="' . $av['image'] . '" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;"><span class="hr-initial" hidden>' . $initial . '</span>'
                                : '<span class="hr-initial">' . $initial . '</span>';

                            $tname = htmlspecialchars_uni((string)($row['name'] ?? ''));
                            ?>
                            <tr class="<?= $is_warned ? 'is-warned' : '' ?>">
                                <td>
                                    <div class="form-check form-switch">
                                        <?php if ($is_warned): ?>
                                            <input class="form-check-input" type="checkbox" disabled title="Already warned in the last 7 days">
                                        <?php else: ?>
                                            <input class="form-check-input hr-cb" type="checkbox" role="switch" name="user_torrent_ids[]" value="<?= $uid . '|' . $tid . '|' . $ratio ?>" aria-label="Select">
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="hr-user">
                                        <?= $avatarHtml ?>
                                        <div style="min-width:0">
                                            <a href="<?= $_this_script_ . '&type=' . $type . '&show_by_userid=' . $uid ?>" class="fw-semibold text-decoration-none" title="Show only this user"><?= format_name($safeName, (int)$row['usergroup']) ?></a>
                                            <?= get_user_icons($row) ?>
                                            <div class="hr-muted"><?= htmlspecialchars_uni((string)($row['title'] ?? '')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?= $_this_script_ . '&type=' . $type . '&torrentid=' . $tid ?>" class="hr-torrent" title="<?= $tname ?> — show only this torrent"><i class="fas fa-magnet text-danger me-1"></i><?= $tname ?></a>
                                    <div class="hr-muted">
                                        <span class="me-2" title="Seeders"><i class="fas fa-arrow-up text-success me-1"></i><?= ts_nf((int)$row['seeders']) ?></span>
                                        <span title="Leechers"><i class="fas fa-arrow-down text-danger me-1"></i><?= ts_nf((int)$row['leechers']) ?></span>
                                        <a href="<?= $BASEURL . '/' . get_torrent_link($tid) ?>" target="_blank" rel="noopener" class="ms-2 text-body-secondary" title="Open torrent"><i class="fas fa-up-right-from-square"></i></a>
                                    </div>
                                </td>
                                <td class="hr-io">
                                    <div><i class="fas fa-arrow-up text-success me-1"></i><b><?= mksize((int)$up) ?></b></div>
                                    <div class="hr-muted"><i class="fas fa-seedling me-1"></i><?= $seed > 0 ? mkprettytime($seed) : 'never seeded' ?></div>
                                    <div class="hr-time-bar" title="Seed <?= $seed_pct ?>% vs leech <?= 100 - $seed_pct ?>%"><span style="width:<?= $seed_pct ?>%"></span></div>
                                </td>
                                <td class="hr-io">
                                    <div><i class="fas fa-arrow-down text-danger me-1"></i><b><?= mksize((int)$down) ?></b></div>
                                    <div class="hr-muted"><i class="fas fa-stopwatch me-1"></i><?= mkprettytime($leech) ?></div>
                                </td>
                                <td>
                                    <span class="hr-ratio <?= $ratio_bad ? 'bad' : 'ok' ?>"><i class="fas <?= $ratio_bad ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up' ?> me-1"></i><?= $ratio ?></span>
                                </td>
                                <td>
                                    <span class="hr-warns <?= $wcls ?>"><i class="fas <?= $wico ?>"></i><?= number_format($tw) ?></span>
                                    <?php if ($is_warned): ?>
                                        <div class="hr-warned-tag mt-1"><i class="fas fa-envelope-circle-check"></i>Warned <?= my_datee('relative', $alreadywarnedarrays[$uid][$tid]) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="hr-empty">
                                <i class="fas fa-circle-check text-success"></i>
                                <div class="fw-semibold">Nothing found</div>
                                <div class="small">No violations match the current filter.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 border-top hr-muted"><i class="fas fa-circle-info me-1"></i>Users warned in the last 7 days can't be selected again. Click a user or torrent to filter by it.</div>
        </div>
    </form>

    <?php if ($multipage): ?>
        <div class="d-flex justify-content-center mt-3"><?= $multipage ?></div>
    <?php endif; ?>
</div>

<!-- Подтверждение бана (раньше бан уходил сразу, без вопросов) -->
<div class="modal fade" id="hrBanModal" tabindex="-1" aria-labelledby="hrBanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="hrBanModalLabel"><i class="fas fa-ban me-2"></i>Ban users</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-start">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0" style="width:44px;height:44px"><i class="fas fa-gavel"></i></span>
                    <div>
                        <div class="fw-semibold">Ban <span id="hrBanCount">0</span> user(s)?</div>
                        <div class="small text-body-secondary">Accounts will be disabled and moved to the banned group. The whole account is banned, not just this torrent.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal"><i class="fas fa-xmark me-1"></i>Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-3" id="hrBanConfirm"><i class="fas fa-ban me-1"></i>Ban</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $BASEURL ?>/admin/scripts/hit_and_run.js?v=<?= @filemtime($rootpath . '/admin/scripts/hit_and_run.js') ?: 1 ?>" defer></script>

<?php
stdfoot();
exit();