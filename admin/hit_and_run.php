<?php

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-light border" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}


define('TSHRD_TOOL', 'v1.3');

require_once INC_PATH . '/datahandler.php';
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

$page = isset($_GET['page']) && $_GET['page'] > 0 ? intval($_GET['page']) : 1;
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
    // Показ формы для ввода сообщения (когда нажата кнопка Warn Selected на основной странице)
    elseif (isset($_POST['warn']) && !empty($_POST['user_torrent_ids']) && is_array($_POST['user_torrent_ids'])) {
        if (!hnr_verify_csrf()) {
            stderr('<div class="alert alert-danger border"><i class="fas fa-shield-alt me-2"></i><strong>Error!</strong> Security check failed. Please refresh the page and try again.</div>');
        }

        stdhead('Hit & Run Detection Tool');
        
        echo '
        <style>
        .warning-form-card {
            animation: slideInUp 0.5s ease-out;
        }
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .warning-icon {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        </style>
        
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border warning-form-card">
                        <div class="card-header bg-warning text-white py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="warning-icon">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 fw-bold">Hit & Run Warning System</h4>
                                    <p class="mb-0 opacity-75 small">Please provide warning message for selected users</p>
                                </div>
                            </div>
                        </div>
                        <form method="post" action="' . $_this_script_ . '">
                            <input type="hidden" name="warn_execute" value="1">
                            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                            <input type="hidden" name="page" value="' . intval($_POST['page']) . '">
                            ' . ($torrentid ? '<input type="hidden" name="torrentid" value="' . $torrentid . '">' : '') . '
                            <input type="hidden" name="user_torrent_ids" value="' . htmlspecialchars(implode(',', $_POST['user_torrent_ids'])) . '">
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark">
                                        <i class="fas fa-envelope me-2 text-warning"></i>Warning Message
                                    </label>
                                    <small class="form-text text-muted d-block mb-2">
                                        <i class="fas fa-info-circle me-1"></i>Do not change <b>{torrentinfo}</b>, <b>{showratio}</b> and <b>{torrentdownloadinfo}</b> values
                                    </small>
                                    <textarea name="warnmessage" class="form-control border-0 bg-light" rows="10" style="border-radius: 12px; font-family: monospace;">' . htmlspecialchars($adminlang['ts_hit_and_run'] ?? "⚠️ WARNING: Hit & Run Violation\n\nTorrent: {torrentinfo}\nRatio: {showratio}\n\nYou have been warned for not meeting the minimum seeding requirements.\n\nPlease download and seed: {torrentdownloadinfo}\n\nFailure to comply may result in further actions.\n\nRegards,\nStaff Team") . '</textarea>
                                </div>
                                <div class="alert alert-info border-0 bg-light">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>' . count($_POST['user_torrent_ids']) . ' user(s)</strong> will receive this warning
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-end p-4">
                                <button type="reset" class="btn btn-outline-secondary px-4 me-2">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </button>
                                <button type="submit" class="btn btn-warning px-4">
                                    <i class="fas fa-exclamation-circle me-1"></i>Send Warnings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
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

if (is_valid_id($torrentid)) {
    $extraquery = ' AND s.torrentid=?';
    $extraquery_params[] = $torrentid;
    $hiddenvalues = '<input type="hidden" name="torrentid" value="' . $torrentid . '">';
    $link = $orjlink = 'torrentid=' . $torrentid . '&amp;';
}

if (isset($_GET['page'])) {
    $hiddenvalues .= '<input type="hidden" name="page" value="' . intval($_GET['page']) . '">';
}

if (isset($_GET['show_by_userid'])) {
    $userid = intval($_GET['show_by_userid']);
    if (is_valid_id($userid)) {
        $extraquery2 = ' AND u.id=?';
        $extraquery2_params = [$userid];
    }
}

require_once INC_PATH . '/functions_icons.php';

// Поиск
if (isset($_POST['do_search']) && !empty($_POST['keywords'])) {
    $keywords = trim((string)$_POST['keywords']);
    $searchtype = intval($_POST['searchtype']);
    switch ($searchtype) {
        case 1:
            $extraquery2 = ' AND u.username=?';
            $extraquery2_params = [$keywords];
            break;
        case 2:
            $extraquery2 = ' AND u.id=?';
            $extraquery2_params = [$keywords];
            break;
        case 3:
            $extraquery2 = ' AND s.torrentid=?';
            $extraquery2_params = [$keywords];
            break;
    }
}

// Определяем тип запроса
$type_title = '';
$type_icon = '';
$type_color = '';
switch ($type) {
    case 'ratio':
        $typequery = 's.uploaded/s.downloaded < ' . ($config['ts_hit_and_run']['min_share_ratio'] ?? 1.0);
        $link = ($link ? $link . '&' : '') . 'type=ratio';
        $type_title = 'Low Ratio Users';
        $type_icon = 'fas fa-chart-line';
        $type_color = 'danger';
        break;
    case 'seedtime':
        $typequery = '(s.seedtime = 0 OR s.seedtime < s.leechtime)';
        $link = ($link ? $link . '&' : '') . 'type=seedtime';
        $type_title = 'Hit & Run Users';
        $type_icon = 'fas fa-hourglass-half';
        $type_color = 'warning';
        break;
    default:
        $typequery = 's.uploaded/s.downloaded < ' . ($config['ts_hit_and_run']['min_share_ratio'] ?? 1.0);
        $link = ($link ? $link . '&' : '') . 'type=ratio';
        $type_title = 'Low Ratio Users';
        $type_icon = 'fas fa-chart-line';
        $type_color = 'danger';
}

// Подсчет общего количества
$count_sql = 'SELECT COUNT(*) as total 
FROM snatched s 
INNER JOIN users u ON (s.userid=u.id) 
LEFT JOIN torrents t ON (s.torrentid=t.id) 
WHERE s.finished=\'yes\' AND s.seeder=\'no\' 
AND u.enabled=\'yes\' AND u.usergroup NOT IN (' . $skip_usergroups . ') 
AND u.ustatus=\'confirmed\' 
AND t.visible=\'yes\' 
AND s.downloaded > 0
AND ' . $typequery . $extraquery . $extraquery2;

$count_query = $db->sql_query_prepared($count_sql, [...$extraquery_params, ...$extraquery2_params]);
$total_count = 0;
if ($count_query && ($result = $db->fetch_array($count_query))) {
    $total_count = (int)$result['total'];
}

$offset = ($page - 1) * $per_page;
$limit = "LIMIT $offset, $per_page";
$total_pages = $total_count > 0 ? ceil($total_count / $per_page) : 1;

// Функция пагинации
function generate_pagination($base_url, $current_page, $total_pages, $per_page, $total_count) {
    if ($total_pages <= 1) return '';
    
    $base_url = str_replace('&&', '&', (string)$base_url);
    $base_url = rtrim($base_url, '&');
    
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center">';
    
    if ($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page - 1) . '"><i class="fas fa-chevron-left"></i></a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>';
    }
    
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $start_page + 4);
    
    if ($end_page - $start_page < 4) {
        $start_page = max(1, $end_page - 4);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page + 1) . '"><i class="fas fa-chevron-right"></i></a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    $start_item = ($current_page - 1) * $per_page + 1;
    $end_item = min($current_page * $per_page, $total_count);
    $page_info = '<div class="text-center text-muted small mb-2"><i class="fas fa-chart-line me-1"></i>Showing ' . $start_item . ' to ' . $end_item . ' of ' . $total_count . ' entries</div>';
    
    return $page_info . $pagination;
}

$base_url = $_this_script_ . '&' . $link;
$base_url = str_replace('&&', '&', $base_url);
$base_url = rtrim($base_url, '&');
$pagertop = generate_pagination($base_url, $page, $total_pages, $per_page, $total_count);
$pagerbottom = $pagertop;

// Основной запрос
$main_query = 'SELECT s.torrentid, s.seedtime, s.leechtime, s.userid, s.downloaded, s.uploaded, 
t.name, t.seeders, t.leechers, u.timeswarned, u.username, u.usergroup, u.enabled, u.donor, u.leechwarn, u.warned, 
g.namestyle, g.title
FROM snatched s 
INNER JOIN users u ON (s.userid=u.id) 
LEFT JOIN torrents t ON (s.torrentid=t.id) 
LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
WHERE s.finished=\'yes\' AND s.seeder=\'no\' 
AND u.enabled=\'yes\' AND u.usergroup NOT IN (' . $skip_usergroups . ') 
AND u.ustatus=\'confirmed\' 
AND t.visible=\'yes\' 
AND s.downloaded > 0
AND ' . $typequery . $extraquery . $extraquery2 . ' 
ORDER BY u.timeswarned DESC, s.uploaded/s.downloaded ASC 
' . $limit;

$query = $db->sql_query_prepared($main_query, [...$extraquery_params, ...$extraquery2_params]);

$criticallimit = ($ban_user_limit ?? 7) - 1;

stdhead('TS Hit & Run Detection Tool');
    
    // JavaScript для выбора всех
    echo '
    <script type="text/javascript">
    function select_deselectAll(formname, elm, group)
    {
        var frm = document.forms[formname];
        if(!frm) return;
        
        var isChecked = elm.checked;
        
        for(var i = 0; i < frm.elements.length; i++)
        {
            var element = frm.elements[i];
            if(element.getAttribute && element.getAttribute("checkme") == group)
            {
                element.checked = isChecked;
            }
        }
    }
    
    function toggleCheckbox(checkbox) {
        var row = checkbox.closest("tr");
        if(checkbox.checked) {
            row.classList.add("table-warning");
        } else {
            row.classList.remove("table-warning");
        }
    }
    </script>
    <style>
    .table-hover tbody tr {
        transition: all 0.2s ease;
    }
    .table-hover tbody tr:hover {
        transform: scale(1.01);
        outline: 1px solid rgba(0,0,0,0.1);
    }
    .table-warning {
        background-color: rgba(255, 193, 7, 0.1) !important;
    }
    .badge-warned {
        background: #28a745;
        color: white;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .badge-success {
        background: #28a745;
        color: white;
    }
    .badge-warning {
        background: #ffc107;
        color: #856404;
    }
    .badge-danger {
        background: #dc3545;
        color: white;
    }
    </style>';
    
    // Выводим сообщение об успехе если есть
    if (!empty($success_msg)) {
        echo '<div class="container mt-3">' . $success_msg . '</div>';
    }

    $already_warned_count = array_sum(array_map('count', $alreadywarnedarrays));
    $ban_threshold = $ban_user_limit ?? 7;

    $stat_cards = [
        ['icon' => 'fas fa-user-slash',        'color' => '#dc3545', 'label' => 'Flagged Users',   'value' => number_format($total_count)],
        ['icon' => 'fas fa-envelope-open-text','color' => '#f59e0b', 'label' => 'Warned (7 days)', 'value' => number_format($already_warned_count)],
        ['icon' => 'fas fa-gavel',             'color' => '#6c757d', 'label' => 'Ban Threshold',   'value' => $ban_threshold . ' warns'],
        ['icon' => $type_icon,                 'color' => '#0d6efd', 'label' => 'Active Filter',   'value' => $type_title],
    ];

    echo '<div class="container mt-3"><div class="row g-3 mb-1">';
    foreach ($stat_cards as $c) {
        echo '
        <div class="col-6 col-md-3">
            <div class="card border h-100">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                         style="width:46px;height:46px;background:' . $c['color'] . '1a;">
                        <i class="' . $c['icon'] . '" style="color:' . $c['color'] . ';font-size:19px;"></i>
                    </div>
                    <div>
                        <div class="fs-5 fw-bold lh-1">' . $c['value'] . '</div>
                        <div class="text-secondary" style="font-size:12px;">' . $c['label'] . '</div>
                    </div>
                </div>
            </div>
        </div>';
    }
    echo '</div></div>';
    
    echo '
    <div class="container mb-4">
        <div class="card border">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-search fa-2x"></i>
                        <div>
                            <h5 class="mb-0 fw-bold">Search Hit and Run</h5>
                            <small class="opacity-75">Find users by username, ID or torrent ID</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body bg-light">
                <form method="post" action="' . $_this_script_ . '" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">SEARCH KEYWORD</label>
                        <input type="text" class="form-control" name="keywords" value="' . htmlspecialchars($keywords) . '" placeholder="Enter keyword...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">SEARCH TYPE</label>
                        <select class="form-select" name="searchtype">
                            <option value="3"' . ($searchtype == 3 ? ' selected' : '') . '>🔍 Torrent ID</option>
                            <option value="2"' . ($searchtype == 2 ? ' selected' : '') . '>👤 User ID</option>
                            <option value="1"' . ($searchtype == 1 ? ' selected' : '') . '>👤 Username</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" name="do_search">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <a href="' . $_this_script_ . '&' . $orjlink . 'page=' . $page . '&type=seedtime" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-hourglass-half me-1"></i>Seed/Leech Time
                            </a>
                            <a href="' . $_this_script_ . '&' . $orjlink . 'page=' . $page . '&type=ratio" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-chart-line me-1"></i>Ratio
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    echo $pagertop;
    
    echo '
    <div class="container">
        <div class="card border">
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="' . $type_icon . ' fa-2x"></i>
                        <div>
                            <h5 class="mb-0 fw-bold">' . $type_title . '</h5>
                            <small class="opacity-75"><i class="fas fa-users me-1"></i>' . number_format($total_count) . ' users found | <i class="fas fa-list me-1"></i>Limit: ' . $per_page . '</small>
                        </div>
                    </div>
                    <span class="badge bg-' . $type_color . ' bg-opacity-75 px-3 py-2">
                        <i class="' . $type_icon . ' me-1"></i>' . strtoupper($type) . '
                    </span>
                </div>
            </div>
            <form method="post" action="' . $_this_script_ . '" name="update">
                ' . $hiddenvalues . '
                <input type="hidden" name="page" value="' . $page . '">
                <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-dark">Username</th>
                                    <th class="text-dark">Torrent Name</th>
                                    <th class="text-dark">Uploaded / SeedTime</th>
                                    <th class="text-dark">Downloaded / LeechTime</th>
                                    <th class="text-dark">Ratio</th>
                                    <th class="text-dark">Warns</th>
                                    <th class="text-center text-dark" width="60">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" id="checkall_group" checkall="group" onclick="select_deselectAll(\'update\', this, \'group\');">
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>';

    require_once INC_PATH . '/functions_mkprettytime.php';

    if ($query && $db->num_rows($query) > 0) {
    while ($user = $db->fetch_array($query)) {
        $userid = (int)$user['userid'];
        $torrentid = (int)$user['torrentid'];
        
        $is_warned = isset($alreadywarnedarrays[$userid][$torrentid]);
        $disabled = $is_warned ? ' disabled' : ' checkme="group"';
        $alreadw = $is_warned ? '<span class="badge-warned"><i class="fas fa-check-circle me-1"></i>Warned</span>' : '';
        
        $timeswarned = (int)$user['timeswarned'];
        if ($timeswarned == 0) {
            $warnClass = 'badge-success';
        } elseif ($timeswarned >= ($ban_user_limit ?? 7)) {
            $warnClass = 'badge-danger';
        } elseif ($timeswarned >= $criticallimit) {
            $warnClass = 'badge-warning';
        } else {
            $warnClass = '';
        }

        $label2 = format_name($user['username'], (int)$user['usergroup']);
		
		$user_icons = get_user_icons($user);
        $downloaded = (float)$user['downloaded'];
        $ratio = $downloaded > 0 ? number_format((float)$user['uploaded'] / $downloaded, 2) : '∞';
        $ratioClass = ($downloaded > 0 && (float)$user['uploaded'] / $downloaded < 1) ? 'text-danger fw-bold' : 'text-success fw-bold';
        $ratioIcon = ($downloaded > 0 && (float)$user['uploaded'] / $downloaded < 1) ? '<i class="fas fa-arrow-down text-danger me-1"></i>' : '<i class="fas fa-arrow-up text-success me-1"></i>';

        echo '
                                <tr>
                                    <td><a href="' . $_this_script_ . '&show_by_userid=' . $userid . '" class="text-decoration-none fw-semibold">' . $label2 . '</a> ' . $user_icons . '</td>
                                    <td><a href="' . $_this_script_ . '&torrentid=' . $torrentid . '" class="text-decoration-none">' . cutename($user['name'], 60) . '</a></td>
                                    <td><span class="fw-semibold">' . mksize($user['uploaded']) . '</span><br><small class="text-muted"><i class="fas fa-clock me-1"></i>' . mkprettytime((int)$user['seedtime']) . '</small></td>
                                    <td><span class="fw-semibold">' . mksize($user['downloaded']) . '</span><br><small class="text-muted"><i class="fas fa-clock me-1"></i>' . mkprettytime((int)$user['leechtime']) . '</small></td>
                                    <td class="' . $ratioClass . '">' . $ratioIcon . $ratio . '</td>
                                    <td><span class="badge ' . $warnClass . ' px-2 py-1">' . number_format($timeswarned) . '</span></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="user_torrent_ids[]" value="' . $userid . '|' . $torrentid . '|' . $ratio . '"' . $disabled . ' onclick="toggleCheckbox(this)">
                                            <label class="form-check-label">' . $alreadw . '</label>
                                        </div>
                                    </td>
                                </tr>';
    }
    } else {
        echo '
                                <tr>
                                    <td colspan="7" class="border-0">
                                        <div class="text-center py-5">
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 mb-3" style="width:72px;height:72px;">
                                                <i class="fas fa-magnifying-glass text-warning" style="font-size:28px;"></i>
                                            </div>
                                            <h5 class="mb-1">Nothing found!</h5>
                                            <p class="text-muted mb-0">No matching results — try adjusting your search or filters.</p>
                                        </div>
                                    </td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Already warned users cannot be selected</small>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-warning me-2" name="warn">
                                <i class="fas fa-exclamation-triangle me-1"></i>Warn Selected
                            </button>
                            <button type="submit" class="btn btn-danger" name="ban">
                                <i class="fas fa-ban me-1"></i>Ban Selected
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>';

    echo $pagerbottom;
    stdfoot();
    exit();
?>