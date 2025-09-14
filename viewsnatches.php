<?php


require_once 'global.php';
include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_mkprettytime.php';

gzip();

define('VS_VERSION', '1.3.8');

$is_mod = is_mod($usergroups);
if ($snatchmod == 'no' && !$is_mod) {
    stderr($lang->global['notavailable']);
}

$lang->load('viewsnatches');
$id = intval($_GET['id']);
int_check($id, true);


if (isset($_GET['delete']) && $usergroups['cansettingspanel'] == '1') 
{
    $userid = intval($_GET['userid']);
    if (is_valid_id($userid)) 
    {
        $db->sql_query_prepared(
            "DELETE FROM snatched WHERE userid = ? AND torrentid = ?",
            [$userid, $id]
        );
    }
}




// Подсчёт скачиваний
$res3 = $db->sql_query_prepared(
    "SELECT COUNT(snatched.id) 
     FROM snatched
     INNER JOIN users ON snatched.userid = users.id
     INNER JOIN torrents ON snatched.torrentid = torrents.id
     WHERE snatched.finished = 'yes' AND snatched.torrentid = ?",
    [$id]
);
$count = $db->fetch_field($res3, 0);

// Информация о торренте
$res3 = $db->sql_query_prepared(
    "SELECT torrents.name, torrents.ts_external
     FROM torrents
     LEFT JOIN categories ON torrents.category = categories.id
     WHERE torrents.id = ?",
    [$id]
);
$arr3 = $db->fetch_array($res3);





if ($arr3['ts_external'] == 'yes') {
    stderr($lang->viewsnatches['external']);
}

stdhead($lang->viewsnatches['headmessage']);

echo '<script>
function toggleFilters() {
    document.getElementById(\'filterPanel\').classList.toggle(\'d-none\');
}

function quickSearch(query) {
    query = query.toLowerCase();
    const rows = document.querySelectorAll(\'table tbody tr\');
    rows.forEach(row => {
        const usernameCell = row.querySelector(\'td a.fw-bold\');
        if (usernameCell) {
            const username = usernameCell.textContent.toLowerCase();
            row.style.display = username.includes(query) ? \'\' : \'none\';
        }
    });
}
</script>';

// Создаем quicklink для сортировки
$quicklink = $_SERVER['SCRIPT_NAME'] . '?id=' . $id . '&amp;order=';

// Модераторский поиск
if ($is_mod) {
    echo '
    <div class="container mt-4">
        <div class="card border-0">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h6 class="mb-0 text-primary"><i class="fas fa-tools me-2"></i>Moderator Tools</h6>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="username" 
                               placeholder="Search username..." onkeyup="quickSearch(this.value)">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2">
                            <a href="' . $BASEURL . '/takereseed.php?reseedid=' . $id . '" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-seedling"></i>
                            </a>
                            <a href="' . $BASEURL . '/admin/index.php?act=ts_hit_and_run&amp;torrentid=' . $id . '" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-running"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilters()">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row mt-2 d-none" id="filterPanel">
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="unfinishedSwitch">
                            <label class="form-check-label small" for="unfinishedSwitch">
                                Include unfinished
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <br>';
}

// Сортировка и пагинация
$type = 'DESC';
$orderby = 'snatched.completedat';
$typelink = '&amp;type=ASC';

// Переключение типа сортировки
if (isset($_GET['type']) && strtoupper($_GET['type']) === 'DESC') {
    $type = 'ASC';
    $typelink = '&amp;type=DESC';
}

// Разрешенные поля для сортировки
$allowed = ['username', 'uploaded', 'downloaded', 'completedat', 'last_action', 'seeder', 'seedtime', 'leechtime', 'connectable'];

// Проверка order
$orderlink = '';
if (isset($_GET['order']) && in_array($_GET['order'], $allowed)) {
    $order = $_GET['order'];
    $orderby = $order === 'username' ? 'users.username' : 'snatched.' . $order;
    $orderlink = '&amp;order=' . $order;
}

// === Пагинация ===
$perpage = isset($ts_perpage) ? intval($ts_perpage) : 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start = ($page - 1) * $perpage;
$pages = ceil($count / $perpage);

// Ссылка для навигации
$page_url = $_SERVER['SCRIPT_NAME'] . '?id=' . intval($id) . $typelink . $orderlink . '&amp;';
$multipage = multipage($count, $perpage, $page, $page_url);

// Основной контент
echo '
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-gradient-primary mb-1">
                <i class="fas fa-users me-2"></i>Snatch List
            </h2>
            <p class="text-muted mb-0">' . htmlspecialchars($arr3['name']) . '</p>
        </div>
        <div class="text-end">
            <span class="badge bg-primary rounded-pill fs-6">' . $count . ' snatches</span>
        </div>
    </div>

    <!-- Кнопка запроса повторной раздачи -->
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
        <div>
            <i class="fas fa-info-circle me-2"></i>
            Need this torrent reseeded?
        </div>
        <a href="' . $BASEURL . '/takereseed.php?reseedid=' . $id . '" class="btn btn-primary btn-sm">
            <i class="fas fa-seedling me-1"></i>Request Reseed
        </a>
    </div>

    <!-- Таблица с улучшенным дизайном -->
    <div class="card">
        <div class="card-header bg-transparent py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">Snatch Details</h5>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-columns"></i> Columns
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">
                            <a href="' . $quicklink . 'username' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-user me-1"></i>User
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'uploaded' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-upload me-1"></i>Uploaded
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'downloaded' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-download me-1"></i>Downloaded
                            </a>
                        </th>
                        <th><i class="fas fa-percentage me-1"></i>Ratio</th>
                        <th>
                            <a href="' . $quicklink . 'completedat' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-flag-checkered me-1"></i>Finished
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'last_action' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-clock me-1"></i>Last Action
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'seeder' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-seedling me-1"></i>Status
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'seedtime' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-clock me-1"></i>Seed Time
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'leechtime' . $typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-hourglass-half me-1"></i>Leech Time
                            </a>
                        </th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>';

// Данные таблицы
$params = [$id, $start, $perpage];
$query = "
    SELECT 
        users.donor, users.enabled, users.warned, users.leechwarn, users.avatar, users.avatardimensions,
        users.last_login, users.lastactive, users.username, users.usergroup,
        p.canupload, p.candownload, p.cancomment,
        snatched.seedtime, snatched.leechtime, snatched.upspeed, snatched.downspeed, 
        snatched.connectable, snatched.port, snatched.completedat, snatched.last_action, 
        snatched.agent, snatched.seeder, snatched.userid, snatched.uploaded, 
        snatched.downloaded, usergroups.namestyle 
    FROM snatched 
    INNER JOIN users ON snatched.userid = users.id 
    LEFT JOIN ts_u_perm p ON (users.id = p.userid) 
    INNER JOIN torrents ON snatched.torrentid = torrents.id 
    INNER JOIN usergroups ON users.usergroup = usergroups.gid 
    WHERE snatched.finished='yes' AND snatched.torrentid = ? 
    ORDER BY $orderby $type 
    LIMIT ?, ?
";

$res = $db->sql_query_prepared($query, $params);

while ($arr = $db->fetch_array($res)) 
{
    $ratio = $arr['downloaded'] > 0 ? $arr['uploaded'] / $arr['downloaded'] : 0;
    $ratio_class = $ratio >= 1 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger');
    
    // Форматируем время
    $seedtime_formatted = mkprettytime($arr['seedtime']);
    $leechtime_formatted = mkprettytime($arr['leechtime']);
    
    $useravatarzz = format_avatar($arr['avatar'], $arr['avatardimensions']);
    $ava22 = '<img class="user-avatar" src="'.$useravatarzz['image'].'" alt="" '.$useravatarzz['width_height'].' />';
    
    echo '
    <tr class="' . ($CURUSER['id'] == $arr['userid'] ? 'highlight-row' : '') . '">
        <td class="ps-4">
            <div class="d-flex align-items-center">
                 '.$ava22.'
                <div>
                    <a href="' . get_profile_link($arr['userid']) . '" class="fw-bold text-decoration-none">
                        ' . format_name($arr['username'], $arr['usergroup']) . '
                    </a>
                    <div class="small text-muted">' . get_user_icons($arr) . '</div>
                </div>
            </div>
        </td>
        <td>
            <span class="fw-bold">' . mksize($arr['uploaded']) . '</span>
            <div class="small text-muted">' . mksize($arr['upspeed']) . '/s</div>
        </td>
        <td>
            <span class="fw-bold">' . mksize($arr['downloaded']) . '</span>
            <div class="small text-muted">' . mksize($arr['downspeed']) . '/s</div>
        </td>
        <td>
            <span class="fw-bold ' . $ratio_class . '">' . number_format($ratio, 2) . '</span>
        </td>
        <td>
            <div class="small">' . my_datee($dateformat, $arr['completedat']) . '</div>
            <div class="small text-muted">' . my_datee($timeformat, $arr['completedat']) . '</div>
        </td>
        <td>
            <div class="small">' . my_datee($dateformat, $arr['last_action']) . '</div>
            <div class="small text-muted">' . my_datee($timeformat, $arr['last_action']) . '</div>
        </td>
        <td>
            <span class="badge ' . ($arr['seeder'] == 'yes' ? 'bg-success' : 'bg-secondary') . '">
                ' . ($arr['seeder'] == 'yes' ? 'Seeding' : 'Inactive') . '
            </span>
        </td>
        <td>
            <span class="small">' . $seedtime_formatted . '</span>
        </td>
        <td>
            <span class="small">' . $leechtime_formatted . '</span>
        </td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-primary">
                <i class="fas fa-eye"></i>
            </button>
        </td>
    </tr>';
}

echo '
                </tbody>
            </table>
        </div>
        
        <div class="card-footer bg-transparent">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">Showing ' . min($perpage, $count - $start) . ' of ' . $count . ' records</div>
                <div>' . $multipage . '</div>
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 0.75rem;
    border: 2px solid #e9ecef;
}
.highlight-row {
    background-color: rgba(13, 110, 253, 0.1) !important;
}
</style>';

stdfoot();
?>