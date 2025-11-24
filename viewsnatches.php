<?php

declare(strict_types=1);

$templatelist = "browses,browse_table,browse_edit,browse_categories,browse_categories2,multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

define('IN_MYBB', 1);

require_once 'global.php';
include_once INC_PATH . '/functions_ratio.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_icons.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_pm.php';

gzip();

define('VS_VERSION', '1.3.8');

// Проверка модератора
$is_mod = is_mod($usergroups);
if ($snatchmod == 'no' && !$is_mod) {
    stderr($lang->global['notavailable']);
}

$lang->load('viewsnatches');
$id = intval($_GET['id']);
int_check($id, true);

// === НОВЫЙ ФУНКЦИОНАЛ: Экспорт данных ===
if (isset($_GET['export']) && $is_mod) {
    $export_type = $_GET['export'] ?? 'csv';
    exportSnatchData($id, $export_type);
}

// === НОВЫЙ ФУНКЦИОНАЛ: Массовые действия ===
if (isset($_POST['mass_action']) && $is_mod) {
    handleMassAction($_POST, $id);
}

// Удаление записи (если разрешено)
if (isset($_GET['delete']) && $usergroups['cansettingspanel'] == '1') {
    $userid = intval($_GET['userid']);
    if (is_valid_id($userid)) {
        $db->sql_query_prepared(
            "DELETE FROM snatched WHERE userid = ? AND torrentid = ?",
            [$userid, $id]
        );
        // Редирект после удаления
        header("Location: {$_SERVER['SCRIPT_NAME']}?id={$id}");
        exit;
    }
}

// Информация о торренте с названием категории
$res_torrent = $db->sql_query_prepared(
    "SELECT t.name, t.ts_external, t.size, t.category, c.name as category_name
     FROM torrents t
     LEFT JOIN categories c ON t.category = c.id
     WHERE t.id=?",
    [$id]
);
$torrent_info = $db->fetch_array($res_torrent);

if (!$torrent_info) {
    stderr($lang->global['notavailable'], "Torrent not found");
}

if ($torrent_info['ts_external'] == 'yes') {
    stderr($lang->viewsnatches['external']);
}

// === НОВЫЙ ФУНКЦИОНАЛ: Получение расширенной статистики ===
$extended_stats = getExtendedStats($id);

// Подсчёт общего количества скачиваний
$res_count = $db->sql_query_prepared(
    "SELECT COUNT(*) AS cnt 
     FROM snatched 
     WHERE finished='yes' AND torrentid=?",
    [$id]
);
$count = $db->fetch_field($res_count, 'cnt');

// Дополнительная статистика
$res_stats = $db->sql_query_prepared(
    "SELECT 
        COUNT(*) as total_snatches,
        SUM(uploaded) as total_uploaded,
        SUM(downloaded) as total_downloaded,
        AVG(seedtime) as avg_seedtime,
        SUM(CASE WHEN seeder = 'yes' THEN 1 ELSE 0 END) as current_seeders
     FROM snatched 
     WHERE torrentid=?",
    [$id]
);
$stats = $db->fetch_array($res_stats);

// === ПАГИНАЦИЯ ===
$perpage = isset($ts_perpage) && $ts_perpage > 0 ? intval($ts_perpage) : 20;
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$start = ($page - 1) * $perpage;

// === СОРТИРОВКА ===
$allowed_orders = ['username', 'uploaded', 'downloaded', 'completedat', 'last_action', 'seeder', 'seedtime', 'leechtime', 'connectable', 'ratio'];
$order = isset($_GET['order']) && in_array($_GET['order'], $allowed_orders) ? $_GET['order'] : 'last_completedat';
$type = isset($_GET['type']) && strtoupper($_GET['type']) == 'ASC' ? 'ASC' : 'DESC';
$typelink = $type === 'ASC' ? '&amp;type=DESC' : '&amp;type=ASC';

$orderby = $order === 'username' ? 'users.username' : ($order === 'ratio' ? '(sn.user_sum_uploaded/sn.user_sum_downloaded)' : 'sn.' . $order);
$orderlink = "&amp;order={$order}";

// === ФИЛЬТРАЦИЯ ===
$filter_sql = "";
$filter_params = [$id];
$active_filters = [];

// Фильтр по статусу сидирования
if (isset($_GET['filter_seeder']) && in_array($_GET['filter_seeder'], ['yes', 'no'])) {
    $filter_sql .= " AND sn.seeder = ?";
    $filter_params[] = $_GET['filter_seeder'];
    $active_filters['seeder'] = $_GET['filter_seeder'];
}

// Фильтр по connectable
if (isset($_GET['filter_connectable']) && in_array($_GET['filter_connectable'], ['yes', 'no'])) {
    $filter_sql .= " AND sn.connectable = ?";
    $filter_params[] = $_GET['filter_connectable'];
    $active_filters['connectable'] = $_GET['filter_connectable'];
}

// Фильтр по ratio
if (isset($_GET['filter_ratio'])) {
    $ratio_filter = $_GET['filter_ratio'];
    if ($ratio_filter === 'poor') {
        $filter_sql .= " AND (sn.user_sum_uploaded/sn.user_sum_downloaded) < 0.5";
    } elseif ($ratio_filter === 'good') {
        $filter_sql .= " AND (sn.user_sum_uploaded/sn.user_sum_downloaded) >= 1.0";
    }
    $active_filters['ratio'] = $ratio_filter;
}


$filter_url = http_build_query($active_filters);
$page_url = $_SERVER['SCRIPT_NAME'] . "?id={$id}{$typelink}{$orderlink}" . ($filter_url ? "&{$filter_url}" : "");
$multipage = multipage($count, $perpage, $page, $page_url);

stdhead($lang->viewsnatches['headmessage']);





$ratio_data = [
    (float)$extended_stats['ratio_poor'],
    (float)$extended_stats['ratio_fair'],
    (float)$extended_stats['ratio_good'],
    (float)$extended_stats['ratio_excellent']
];

$baseurl = $BASEURL;
$torrent_id = (int)$id;
$script_name = $_SERVER['SCRIPT_NAME'];

?>


<script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/chart.js"></script>


<script>




document.addEventListener('DOMContentLoaded', () => {
    // --- PHP → JS данные ---
    const ratioData = <?= json_encode($ratio_data) ?>;
    const BASEURL = <?= json_encode($baseurl) ?>;
    const TORRENT_ID = <?= json_encode($torrent_id) ?>;
    const SCRIPT_NAME = <?= json_encode($script_name) ?>;

    // --- Chart.js графики ---
    let charts = {};

function initCharts() {
    const ratioCtx = document.getElementById('ratioChart');
    if (ratioCtx) {
        // Устанавливаем фиксированные размеры для канваса
        ratioCtx.width = 400; // Ширина
        ratioCtx.height = 400; // Высота

        charts.ratio = new Chart(ratioCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ratio < 0.5', 'Ratio 0.5–1.0', 'Ratio 1.0–2.0', 'Ratio > 2.0'],
                datasets: [{
                    data: ratioData,
                    backgroundColor: ['#dc3545', '#ffc107', '#198754', '#0d6efd']
                }]
            },
            options: {
                responsive: false, // отключаем авто-растяжение
                cutout: '40%', // контролируем пустую область в центре
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
}


    // --- Выделение пользователей ---
    function getSelectedUsers() {
        const selected = [];
        document.querySelectorAll('.user-checkbox:checked').forEach(checkbox => {
            selected.push(checkbox.value);
        });
        return selected;
    }

    function selectAllUsers(selectAll) {
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.checked = selectAll;
        });
        updateSelectionCounter();
    }

    function updateSelectionCounter() {
        const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
        const counter = document.getElementById('selectedCount');
        if (counter) counter.textContent = selectedCount;
    }

    // --- AJAX: Детали пользователя ---
    function showUserDetails(userId) {
        fetch(`${BASEURL}/ajax/user_snatch_details.php?userid=${userId}&torrentid=${TORRENT_ID}`)
            .then(r => r.json())
            .then(data => {
                const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
                document.getElementById('userDetailsContent').innerHTML = data.html;
                modal.show();
            })
            .catch(err => console.error('Error:', err));
    }

    // --- Массовое сообщение ---
    function sendMassMessage() {
        const selectedUsers = getSelectedUsers();
        if (selectedUsers.length === 0) {
            showAlert('Please select at least one user', 'warning');
            return;
        }

        const modalHtml = `
            <div class="modal fade" id="massMessageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-envelope me-2"></i>
                                Send Message to ${selectedUsers.length} Users
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" id="messageSubject"
                                       value="Regarding torrent download" placeholder="Enter message subject">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" id="messageText" rows="8"
                                          placeholder="Enter your message here...">Hello {username},

Regarding the torrent {torrenturl}...

Thank you for your cooperation.

Best regards,
Torrent Staff</textarea>
                                <div class="form-text">
                                    Available placeholders:
                                    <span class="badge bg-secondary">{username}</span> - user name,
                                    <span class="badge bg-secondary">{torrentname}</span> - torrent name,
                                    <span class="badge bg-secondary">{torrenturl}</span> - torrent URL
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This message will be sent to <strong>${selectedUsers.length}</strong> selected users.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="submitMassMessage()">
                                <i class="fas fa-paper-plane me-1"></i> Send Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const oldModal = document.getElementById('massMessageModal');
        if (oldModal) oldModal.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('massMessageModal'));
        modal.show();
    }

    function submitMassMessage() {
        const subject = document.getElementById('messageSubject').value.trim();
        const message = document.getElementById('messageText').value.trim();
        const selectedUsers = getSelectedUsers();

        if (!subject) {
            showAlert('Please enter a subject', 'warning');
            return;
        }
        if (!message) {
            showAlert('Please enter a message', 'warning');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${SCRIPT_NAME}?id=${TORRENT_ID}`;

        form.innerHTML = `
            <input type="hidden" name="mass_action" value="send_message">
            <input type="hidden" name="selected_users" value='${JSON.stringify(selectedUsers)}'>
            <input type="hidden" name="message" value="${message.replace(/"/g, '&quot;')}">
            <input type="hidden" name="subject" value="${subject.replace(/"/g, '&quot;')}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    // --- Быстрые действия ---
    function quickActions(action) {
        const selectedUsers = getSelectedUsers();
        if (selectedUsers.length === 0) {
            showAlert('Please select users first', 'warning');
            return;
        }

        let modalTitle = '', modalMessage = '', buttonClass = '', icon = '', actionText = '';
        switch (action) {
            case 'reseed_request':
                modalTitle = 'Send Reseed Requests';
                modalMessage = `Are you sure you want to send reseed requests to <strong>${selectedUsers.length}</strong> users?`;
                buttonClass = 'btn-warning'; icon = 'fas fa-seedling'; actionText = 'reseed_request';
                break;
            case 'delete_snatches':
                modalTitle = 'Delete Snatch Records';
                modalMessage = `<div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone!<br><br>
                    Are you sure you want to delete snatch records for <strong>${selectedUsers.length}</strong> users?
                </div>`;
                buttonClass = 'btn-danger'; icon = 'fas fa-trash'; actionText = 'delete_snatches';
                break;
            case 'mark_as_seeding':
                modalTitle = 'Mark as Seeding';
                modalMessage = `Are you sure you want to mark <strong>${selectedUsers.length}</strong> users as currently seeding?`;
                buttonClass = 'btn-success'; icon = 'fas fa-check'; actionText = 'mark_as_seeding';
                break;
        }

        const modalHtml = `
            <div class="modal fade" id="confirmActionModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="${icon} me-2"></i>${modalTitle}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${modalMessage}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn ${buttonClass}" onclick="submitQuickAction('${actionText}')">
                                <i class="${icon} me-1"></i> Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const oldModal = document.getElementById('confirmActionModal');
        if (oldModal) oldModal.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
        modal.show();
    }

    function submitQuickAction(action) {
        const selectedUsers = getSelectedUsers();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${SCRIPT_NAME}?id=${TORRENT_ID}`;
        form.innerHTML = `
            <input type="hidden" name="mass_action" value="${action}">
            <input type="hidden" name="selected_users" value='${JSON.stringify(selectedUsers)}'>
        `;
        document.body.appendChild(form);
        form.submit();
    }

    // --- Вспомогательная функция уведомлений ---
    function showAlert(message, type = 'info') {
        const icon = type === 'warning' ? 'exclamation-triangle' :
                     (type === 'success' ? 'check-circle' : 'info-circle');
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed"
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <i class="fas fa-${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        setTimeout(() => {
            const alert = document.querySelector('.alert.position-fixed');
            if (alert) new bootstrap.Alert(alert).close();
        }, 5000);
    }

    // --- Инициализация ---
    initCharts();
    updateSelectionCounter();

    // --- Автообновление каждые 30 секунд ---
    setInterval(() => {
        if (!document.hidden) window.location.reload();
    }, 30000);

    // Экспорт функций для HTML (onchange и т.д.)
    window.selectAllUsers = selectAllUsers;
    window.showUserDetails = showUserDetails;
    window.sendMassMessage = sendMassMessage;
    window.submitMassMessage = submitMassMessage;
    window.quickActions = quickActions;
    window.submitQuickAction = submitQuickAction;
});
</script>


<?



echo '
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Snatch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                Loading...
            </div>
        </div>
    </div>
</div>';


if ($is_mod) 
{
    echo '
    <div class="container mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <h6 class="mb-0 text-primary"><i class="fas fa-tools me-2"></i>Moderator Tools</h6>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control" 
                                   placeholder="Search username..." 
                                   onkeyup="quickSearch(this.value)"
                                   id="searchInput">
                            <span class="input-group-text">
                                <span id="visibleCount" class="badge bg-success">' . $count . '</span>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                            <button class="btn btn-sm btn-outline-info" onclick="toggleAdvancedFilters()">
                                <i class="fas fa-sliders-h me-1"></i> Filters
                            </button>
                            <a href="' . $BASEURL . '/takereseed.php?reseedid=' . $id . '" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-seedling me-1"></i> Reseed
                            </a>
                            <a href="' . $BASEURL . '/admin/index.php?act=ts_hit_and_run&amp;torrentid=' . $id . '" 
                               class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-running me-1"></i> H&amp;R
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-file-export me-1"></i> Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?id=' . $id . '&export=csv">CSV Format</a></li>
                                    <li><a class="dropdown-item" href="?id=' . $id . '&export=json">JSON Format</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-sm btn-outline-warning" onclick="toggleFilters()" id="filterPanelToggle">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Расширенные фильтры -->
                <div class="row mt-3 d-none" id="advancedFilters">
                    <div class="col-md-12">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="id" value="' . $id . '">
                            <div class="col-md-2">
                                <select name="filter_seeder" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="yes" ' . (($active_filters['seeder'] ?? '') == 'yes' ? 'selected' : '') . '>Seeders Only</option>
                                    <option value="no" ' . (($active_filters['seeder'] ?? '') == 'no' ? 'selected' : '') . '>Non-Seeders</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_connectable" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Connectivity</option>
                                    <option value="yes" ' . (($active_filters['connectable'] ?? '') == 'yes' ? 'selected' : '') . '>Connectable</option>
                                    <option value="no" ' . (($active_filters['connectable'] ?? '') == 'no' ? 'selected' : '') . '>Not Connectable</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_ratio" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Ratios</option>
                                    <option value="poor" ' . (($active_filters['ratio'] ?? '') == 'poor' ? 'selected' : '') . '>Ratio < 0.5</option>
                                    <option value="good" ' . (($active_filters['ratio'] ?? '') == 'good' ? 'selected' : '') . '>Ratio ≥ 1.0</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <a href="?id=' . $id . '" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Массовые действия -->
                <div class="row mt-3" id="massActionsPanel">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center gap-3 p-2 bg-light rounded">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="selectAll" onchange="selectAllUsers(this.checked)">
                                <label class="form-check-label small" for="selectAll">Select All</label>
                            </div>
                            <span class="small text-muted" id="selectedCount">0</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="sendMassMessage()">
                                    <i class="fas fa-envelope me-1"></i> Message
                                </button>
                                <button class="btn btn-outline-warning" onclick="quickActions(\'reseed_request\')">
                                    <i class="fas fa-seedling me-1"></i> Request Reseed
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <br>';
}


echo '<div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-gradient-primary mb-1">
                    <i class="fas fa-users me-2"></i>Snatch List
                </h2>
                <p class="text-muted mb-0">' . htmlspecialchars($torrent_info['name']) . '</p>
                <div class="small text-muted">
                    <i class="fas fa-hdd me-1"></i>Size: ' . mksize($torrent_info['size']) . ' | 
                    <i class="fas fa-tag me-1"></i>Category: ' . htmlspecialchars($torrent_info['category_name'] ?? 'Unknown') . '
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-primary rounded-pill fs-6">
                    <i class="fas fa-download me-1"></i>' . $count . ' snatches
                </span>
                <div class="small text-muted mt-1">
                    <i class="fas fa-list me-1"></i>' . $perpage . ' per page
                </div>
            </div>
        </div>';


echo '
<div class="row mb-4">
    <div class="col-md-8">
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Ratio Distribution</h6>
        </div>
        <div class="card-body d-flex justify-content-center">
            <!-- Уменьшаем ширину canvas -->
            <canvas id="ratioChart" width="150" height="150"></canvas>
        </div>
    </div>
</div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Quick Stats</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="text-success">
                            <h4>' . $extended_stats['unique_users'] . '</h4>
                            <small>Unique Users</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-primary">
                            <h4>' . $extended_stats['avg_ratio'] . '</h4>
                            <small>Avg Ratio</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-info">
                            <h4>' . mksize($extended_stats['total_speed']) . '/s</h4>
                            <small>Total Speed</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-warning">
                            <h4>' . $extended_stats['completion_rate'] . '%</h4>
                            <small>Completion</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';


echo '
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body py-3">
                <h5 class="card-title text-success mb-1">
                    <i class="fas fa-seedling me-1"></i>' . $stats['current_seeders'] . '
                </h5>
                <p class="card-text small text-muted mb-0">Current Seeders</p>
                <small class="text-muted">' . number_format(($stats['current_seeders'] / max($count, 1)) * 100, 1) . '% of snatchers</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body py-3">
                <h5 class="card-title text-primary mb-1">
                    <i class="fas fa-upload me-1"></i>' . mksize($stats['total_uploaded']) . '
                </h5>
                <p class="card-text small text-muted mb-0">Total Uploaded</p>
                <small class="text-muted">' . mksize($stats['total_uploaded']) . ' total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body py-3">
                <h5 class="card-title text-info mb-1">
                    <i class="fas fa-download me-1"></i>' . mksize($stats['total_downloaded']) . '
                </h5>
                <p class="card-text small text-muted mb-0">Total Downloaded</p>
                <small class="text-muted">' . mksize($stats['total_downloaded']) . ' total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body py-3">
                <h5 class="card-title text-warning mb-1">
                    <i class="fas fa-clock me-1"></i>' . mkprettytime($extended_stats['total_seedtime']) . '
                </h5>
                <p class="card-text small text-muted mb-0">Total Seed Time</p>
                <small class="text-muted">' . number_format($extended_stats['users_over_24h'] ?? 0) . ' users > 24h</small>
            </div>
        </div>
    </div>
</div>';


echo '
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Seed Time Distribution</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="text-danger">
                            <h4>' . ($extended_stats['seedtime_distribution']['poor'] ?? 0) . '</h4>
                            <small class="text-muted">< 1 hour</small>
                            <div class="small text-muted">' . number_format(($extended_stats['seedtime_distribution']['poor'] / max($extended_stats['unique_users'], 1)) * 100, 1) . '%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-warning">
                            <h4>' . ($extended_stats['seedtime_distribution']['fair'] ?? 0) . '</h4>
                            <small class="text-muted">1-6 hours</small>
                            <div class="small text-muted">' . number_format(($extended_stats['seedtime_distribution']['fair'] / max($extended_stats['unique_users'], 1)) * 100, 1) . '%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-info">
                            <h4>' . ($extended_stats['seedtime_distribution']['good'] ?? 0) . '</h4>
                            <small class="text-muted">6-24 hours</small>
                            <div class="small text-muted">' . number_format(($extended_stats['seedtime_distribution']['good'] / max($extended_stats['unique_users'], 1)) * 100, 1) . '%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-success">
                            <h4>' . ($extended_stats['seedtime_distribution']['excellent'] ?? 0) . '</h4>
                            <small class="text-muted">> 24 hours</small>
                            <div class="small text-muted">' . number_format(($extended_stats['seedtime_distribution']['excellent'] / max($extended_stats['unique_users'], 1)) * 100, 1) . '%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';


if ($count > $perpage) 
{
    echo '
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="fas fa-file-alt me-1"></i>
                    Page ' . $page . ' of ' . ceil($count / $perpage) . ' | 
                    Showing ' . ($start + 1) . '-' . min($start + $perpage, $count) . ' of ' . $count . ' records
                </div>
                <div class="pagination-container">' . $multipage . '</div>
            </div>
        </div>
    </div>';
}


$quicklink = $_SERVER['SCRIPT_NAME'] . '?id=' . $id . '&amp;order=';
$current_typelink = $type === 'ASC' ? '&amp;type=DESC' : '&amp;type=ASC';

echo '<div class="card shadow-sm">
        <div class="card-header bg-transparent py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Snatch Details</h5>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <div class="btn-group" role="group">
                            
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-columns me-1"></i> Columns
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="toggleColumn(1)">Toggle Uploaded</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="toggleColumn(2)">Toggle Downloaded</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="toggleColumn(6)">Toggle Status</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        ' . ($is_mod ? '<th width="30"><input type="checkbox" class="form-check-input" onchange="selectAllUsers(this.checked)"></th>' : '') . '
                        <th class="ps-4">
                            <a href="' . $quicklink . 'username' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-user me-1"></i>User
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'uploaded' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-upload me-1"></i>Uploaded
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'downloaded' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-download me-1"></i>Downloaded
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'ratio' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-percentage me-1"></i>Ratio
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'completedat' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-flag-checkered me-1"></i>Finished
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'last_action' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-clock me-1"></i>Last Action
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'seeder' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-seedling me-1"></i>Status
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'seedtime' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-clock me-1"></i>Seed Time
                            </a>
                        </th>
                        <th>
                            <a href="' . $quicklink . 'leechtime' . $current_typelink . '" class="text-decoration-none text-dark">
                                <i class="fas fa-hourglass-half me-1"></i>Leech Time
                            </a>
                        </th>
                        <th class="text-center"><i class="fas fa-cog me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>';


$limit_sql = "LIMIT {$start}, {$perpage}";


$query = "
SELECT 
    u.*, 
    p.canupload, p.candownload, p.cancomment, 
    g.namestyle,
    sn.user_sum_uploaded,
    sn.user_sum_downloaded,
    sn.last_seedtime,
    sn.last_leechtime,
    sn.last_completedat,
    sn.last_action,
    sn.seeder,
    sn.connectable,
	sn.avg_upspeed,
    sn.avg_downspeed
FROM users u
INNER JOIN (
    SELECT 
        userid,
        SUM(uploaded) AS user_sum_uploaded,
        SUM(downloaded) AS user_sum_downloaded,
        MAX(completedat) AS last_completedat,
        MAX(seedtime) AS last_seedtime,
        MAX(leechtime) AS last_leechtime,
        MAX(last_action) AS last_action,
        MAX(seeder='yes') AS seeder,
        MAX(connectable='yes') AS connectable,
		AVG(upspeed) AS avg_upspeed,
        AVG(downspeed) AS avg_downspeed
    FROM snatched
    WHERE torrentid = ? AND finished = 'yes'
    GROUP BY userid
) AS sn ON sn.userid = u.id
LEFT JOIN ts_u_perm p ON u.id = p.userid
INNER JOIN usergroups g ON u.usergroup = g.gid
ORDER BY sn.last_completedat DESC
{$limit_sql}
";

$res = $db->sql_query_prepared($query, [$id]);




if ($db->num_rows($res) > 0) {
    while ($arr = $db->fetch_array($res)) {
        // Расчёт ratio
        $ratio = $arr['user_sum_downloaded'] > 0 
            ? $arr['user_sum_uploaded'] / $arr['user_sum_downloaded'] 
            : 0;
        $ratio_class = $ratio >= 1 ? 'text-success' : ($ratio >= 0.5 ? 'text-warning' : 'text-danger');

        $seedtime_formatted = mkprettytime((int)$arr['last_seedtime']);
        $leechtime_formatted = mkprettytime((int)$arr['last_leechtime']);
        $useravatar = format_avatar($arr['avatar'], $arr['avatardimensions']);
        $ava_img = "<img class='user-avatar' src='{$useravatar['image']}' alt='' {$useravatar['width_height']} />";

        $status = $arr['seeder'] 
            ? "<span class='badge bg-success'><i class='fas fa-seedling me-1'></i>Seeding</span>" 
            : "<span class='badge bg-secondary'><i class='fas fa-times me-1'></i>Inactive</span>";

        $connectable_icon = $arr['connectable'] 
            ? '<i class="fas fa-plug text-success" title="Connectable"></i>' 
            : '<i class="fas fa-plug text-danger" title="Not Connectable"></i>';

        echo "<tr class='" . ($CURUSER['id'] == $arr['id'] ? 'highlight-row' : '') . "'>
                " . ($is_mod ? "<td><input type='checkbox' class='form-check-input user-checkbox' value='{$arr['id']}' onchange='updateSelectionCounter()'></td>" : "") . "
                <td class='ps-4'>
                    <div class='d-flex align-items-center'>
                        {$ava_img}
                        <div>
                            <a href='" . get_profile_link($arr['id']) . "' class='fw-bold text-decoration-none'>" . format_name($arr['username'], $arr['usergroup']) . "</a>
                            <div class='small text-muted'>{$connectable_icon}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class='fw-bold'>" . mksize($arr['user_sum_uploaded']) . "</span>
                    <div class='small text-muted'>" . mksize($arr['avg_upspeed']) . "/s</div>
                </td>
                <td>
                    <span class='fw-bold'>" . mksize($arr['user_sum_downloaded']) . "</span>
                    <div class='small text-muted'>" . mksize($arr['avg_downspeed']) . "/s</div>
                </td>
                <td><span class='fw-bold {$ratio_class}'>" . number_format($ratio, 2) . "</span></td>
                <td>
                    <div class='small'>" . my_datee($dateformat, $arr['last_completedat']) . "</div>
                    <div class='small text-muted'>" . my_datee($timeformat, $arr['last_completedat']) . "</div>
                </td>
                <td>
                    <div class='small'>" . my_datee($dateformat, $arr['last_action']) . "</div>
                    <div class='small text-muted'>" . my_datee($timeformat, $arr['last_action']) . "</div>
                </td>
                <td>{$status}</td>
                <td><span class='small'>{$seedtime_formatted}</span></td>
                <td><span class='small'>{$leechtime_formatted}</span></td>
                <td class='text-center'>
                    <button class='btn btn-sm btn-outline-primary me-1' title='View Details' onclick='showUserDetails({$arr['id']})'>
                        <i class='fas fa-eye'></i>
                    </button>";

        if ($is_mod) {
            echo "<a href='{$_SERVER['SCRIPT_NAME']}?id={$id}&amp;delete=1&amp;userid={$arr['id']}' 
                      class='btn btn-sm btn-outline-danger' 
                      title='Delete Snatch' 
                      onclick='return confirm(\"Are you sure you want to delete this snatch record?\")'>
                    <i class='fas fa-trash'></i>
                  </a>";
        }

        echo "</td></tr>";
    }
} else {
    echo "<tr>
            <td colspan='" . ($is_mod ? 12 : 11) . "' class='text-center py-5'>
                <div class='text-muted'>
                    <i class='fas fa-inbox fa-3x mb-3'></i>
                    <h5>No snatches found</h5>
                    <p class='mb-0'>This torrent hasn't been snatched by anyone yet.</p>
                </div>
            </td>
          </tr>";
}

echo "</tbody>
    </table>
</div>";


if ($count > $perpage) 
{
    echo "<div class='card-footer bg-transparent'>
            <div class='d-flex justify-content-between align-items-center'>
                <div class='small text-muted'>
                    <i class='fas fa-list me-1'></i>
                    Showing " . ($start + 1) . " - " . min($start + $perpage, $count) . " of {$count} records
  
                </div>
                <div>{$multipage}</div>
            </div>
          </div>";
}

echo "</div>
</div>";

// CSS стили
echo "<style>
.user-avatar { 
    width: 44px; 
    height: 44px; 
    border-radius: 50%; 
    object-fit: cover; 
    margin-right: 0.75rem; 
    border: 2px solid #e9ecef; 
    transition: border-color 0.3s ease;
}
.user-avatar:hover {
    border-color: #007bff;
}
.highlight-row { 
    background-color: rgba(13, 110, 253, 0.05) !important; 
    border-left: 3px solid #007bff;
}
.pagination-container .pagination { 
    margin-bottom: 0; 
}
.table-responsive { 
    overflow-x: auto; 
}
.card { 
    transition: box-shadow 0.3s ease; 
    border: 1px solid rgba(0,0,0,0.125);
}
.card:hover { 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important; 
}
.text-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.dropdown-item.active {
    background-color: #007bff;
    color: white;
}
.badge {
    font-size: 0.75em;
}
</style>";

stdfoot();


function exportSnatchData($torrent_id, $format = 'csv') 
{
    global $db;
    
    $query = "
    SELECT 
        u.username,
        u.email,
        sn.user_sum_uploaded as uploaded,
        sn.user_sum_downloaded as downloaded,
        (sn.user_sum_uploaded/sn.user_sum_downloaded) as ratio,
        sn.last_completedat as completed,
        sn.last_action as last_action,
        sn.seeder,
        sn.connectable,
        sn.last_seedtime as seed_time,
        sn.last_leechtime as leech_time
    FROM users u
    INNER JOIN (
        SELECT 
            userid,
            SUM(uploaded) AS user_sum_uploaded,
            SUM(downloaded) AS user_sum_downloaded,
            MAX(completedat) AS last_completedat,
            MAX(last_action) AS last_action,
            MAX(seeder='yes') AS seeder,
            MAX(connectable='yes') AS connectable,
            MAX(seedtime) AS last_seedtime,
            MAX(leechtime) AS last_leechtime
        FROM snatched
        WHERE torrentid = ? AND finished = 'yes'
        GROUP BY userid
    ) AS sn ON sn.userid = u.id
    ORDER BY sn.last_completedat DESC
    ";
    
    $res = $db->sql_query_prepared($query, [$torrent_id]);
    $data = [];
    
    while ($row = $db->fetch_array($res)) 
	{
        $data[] = $row;
    }
    
    switch ($format) 
	{
        case 'json':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="snatch_data_' . $torrent_id . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            break;
            
        case 'csv':
        default:
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="snatch_data_' . $torrent_id . '.csv"');
            
            $output = fopen('php://output', 'w');
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            fclose($output);
            break;
    }
    exit;
}


function handleMassAction($post_data, $torrent_id) 
{
    global $db, $BASEURL, $CURUSER;
	
	
    
    $action = $post_data['mass_action'] ?? '';
    $selected_users = json_decode($post_data['selected_users'] ?? '[]', true);
    
    if (empty($selected_users)) 
	{
        return;
    }
    
    
    $res_torrent = $db->sql_query_prepared(
        "SELECT name FROM torrents WHERE id = ?",
        [$torrent_id]
    );
    $torrent_info = $db->fetch_array($res_torrent);
    
    if (!$torrent_info) {
        return;
    }
    
    $torrent_name = $torrent_info['name'];
    $torrent_url = '[url=' . $BASEURL . '/' . get_torrent_link($torrent_id) . ']' . $torrent_name . '[/url]';
	
	$tid = $torrent_id;
    
    switch ($action) 
	{
        case 'send_message':
            $message = $post_data['message'] ?? '';
            $subject = $post_data['subject'] ?? 'Regarding torrent: ' . $torrent_name;
            
            if (!empty($message)) {
                $sent_count = 0;
                $user_ids = implode(',', array_map('intval', $selected_users));
                
                
                $res_users = $db->sql_query_prepared(
                    "SELECT id, username FROM users WHERE id IN ($user_ids)",
                    []
                );
                
                $users = [];
                while ($user = $db->fetch_array($res_users)) {
                    $users[$user['id']] = $user['username'];
                }
                
                foreach ($selected_users as $user_id) {
                    if (isset($users[$user_id])) {
                        $username = $users[$user_id];
                        $personalized_msg = str_replace(
                            ['{username}', '{torrentname}', '{torrenturl}'], 
                            [$username, $torrent_name, $torrent_url], 
                            $message
                        );
                        
                        
                        send_pm([
                            'subject' => $db->escape_string($subject),
                            'message' => $db->escape_string($personalized_msg),
                            'touid'   => $user_id
                        ], $CURUSER['id'], true);
                        
                        $sent_count++;
                    }
                }
            }
            break;
            
    case 'reseed_request':
    $subject = "Reseed request (TID: {$tid})";
    $message = "Hello {username},We noticed that you have downloaded the torrent {torrenturl} but are no longer seeding it. 
	Could you please help the community by reseeding this torrent? Your contribution would be greatly appreciated!
	Thank you for your cooperation.Best regards, Torrent Staff";
    
    $sent_count = 0;
    $user_ids = implode(',', array_map('intval', $selected_users));
    
    
    $res_users = $db->sql_query_prepared(
        "SELECT id, username FROM users WHERE id IN ($user_ids)",
        []
    );
    
    $users = [];
    while ($user = $db->fetch_array($res_users)) {
        $users[$user['id']] = $user['username'];
    }
    
    foreach ($selected_users as $user_id) 
	{
        if (isset($users[$user_id])) 
		{
            $username = $users[$user_id];
            $personalized_msg = str_replace(
                ['{username}', '{torrentname}', '{torrenturl}'], 
                [$username, $torrent_name, $torrent_url], 
                $message
            );
            
            
            send_pm([
                'subject' => $db->escape_string($subject),
                'message' => $db->escape_string($personalized_msg),
                'touid'   => $user_id
            ], $CURUSER['id'], true); 
            
            $sent_count++;
        }
    }
    
    
    break;
	
    }
    
    header("Location: {$_SERVER['SCRIPT_NAME']}?id={$torrent_id}");
    exit;
}


function getExtendedStats($torrent_id) {
    global $db;
    
   
    $stats = [
        'ratio_poor' => 0,
        'ratio_fair' => 0, 
        'ratio_good' => 0,
        'ratio_excellent' => 0,
        'unique_users' => 0,
        'avg_ratio' => 0,
        'total_speed' => 0,
        'completion_rate' => 100,
        'seedtime_distribution' => [
            'poor' => 0,     // < 1 hour
            'fair' => 0,     // 1-6 hours
            'good' => 0,     // 6-24 hours
            'excellent' => 0 // > 24 hours
        ],
        'total_seedtime' => 0,
        'avg_seedtime' => 0,
        'users_over_24h' => 0
    ];
    
   
    $stats_query = "
    SELECT 
        userid,
        SUM(uploaded) as total_uploaded,
        SUM(downloaded) as total_downloaded,
        SUM(seedtime) as total_seedtime,
        CASE 
            WHEN SUM(downloaded) = 0 THEN 999
            ELSE SUM(uploaded) / SUM(downloaded)
        END as user_ratio
    FROM snatched 
    WHERE torrentid = ? AND finished = 'yes' 
    GROUP BY userid
    ";
    
    $res = $db->sql_query_prepared($stats_query, [$torrent_id]);
    
    $total_ratio = 0;
    $total_seedtime_all_users = 0;
    $user_count = 0;
    
    while ($row = $db->fetch_array($res)) {
        $user_count++;
        $ratio = $row['user_ratio'];
        $user_seedtime = (int)$row['total_seedtime'];
        
        $total_ratio += $ratio;
        $total_seedtime_all_users += $user_seedtime;
        
        
        if ($ratio < 0.5) {
            $stats['ratio_poor']++;
        } elseif ($ratio < 1.0) {
            $stats['ratio_fair']++;
        } elseif ($ratio < 2.0) {
            $stats['ratio_good']++;
        } else {
            $stats['ratio_excellent']++;
        }
        
        
        if ($user_seedtime < 3600) 
		{ 
            $stats['seedtime_distribution']['poor']++;
        } 
		elseif ($user_seedtime < 21600) 
		{ 
            $stats['seedtime_distribution']['fair']++;
        } 
		elseif ($user_seedtime < 86400) 
		{ 
            $stats['seedtime_distribution']['good']++;
        } 
		else 
		{ 
            $stats['seedtime_distribution']['excellent']++;
            $stats['users_over_24h']++;
        }
    }
    
    $stats['unique_users'] = $user_count;
    $stats['avg_ratio'] = $user_count > 0 ? number_format($total_ratio / $user_count, 2) : '0.00';
    $stats['total_seedtime'] = $total_seedtime_all_users;
    $stats['avg_seedtime'] = $user_count > 0 ? (int)($total_seedtime_all_users / $user_count) : 0;
    
    
    $speed_query = "
    SELECT 
        AVG(upspeed) as avg_upspeed,
        AVG(downspeed) as avg_downspeed
    FROM snatched 
    WHERE torrentid = ? AND finished = 'yes'
    ";
    
    $res_speed = $db->sql_query_prepared($speed_query, [$torrent_id]);
    if ($speed_row = $db->fetch_array($res_speed)) {
        $stats['total_speed'] = ($speed_row['avg_upspeed'] + $speed_row['avg_downspeed']) * $user_count;
    }
    
    return $stats;
}
?>