<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('B_VERSION', '6.6.3');
define('THIS_SCRIPT', 'settings_history.php');

$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';
$lang->load('settings_history');
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';

if ((int)($usergroups['cansettingspanel'] ?? 0) !== 1) {
    stdhead();
    error_no_permission(true);
    exit();
}

// ── ags_fmt ───────────────────────────────────────────────────────────────────
// Подстановка {1}, {2}… (и %1$s, %2$s… — в них $lang->load() превращает {N})
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']   = (string)$arg;
            $map['%' . $n . '$s']  = (string)$arg;
        }
        return strtr($str, $map);
    }
}

// ── flash_message ─────────────────────────────────────────────────────────────

if (!function_exists('flash_message')) {
    function flash_message(?string $message = null, string $type = 'info', bool $raw_html = false): void
    {
        if ($message !== null) {
            $_SESSION['flash'][] = ['message' => $message, 'type' => $type, 'raw' => $raw_html];
            return;
        }

        if (empty($_SESSION['flash'])) return;


        echo '<script>';
        foreach ($_SESSION['flash'] as $flash) {

            $jsType = $flash['type'] === 'danger' ? 'error' : $flash['type'];
            if (!in_array($jsType, ['success', 'error', 'warning', 'info'], true)) {
                $jsType = 'info';
            }
            $msg = $flash['raw'] ? $flash['message'] : htmlspecialchars($flash['message']);
            echo 'document.addEventListener("DOMContentLoaded", function() { showToast(' . json_encode($msg) . ', ' . json_encode($jsType) . '); });' . "\n";
        }
        echo '</script>';

        unset($_SESSION['flash']);
    }
}

// ── admin_redirect ────────────────────────────────────────────────────────────
if (!function_exists('admin_redirect')) {
    function admin_redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}

// Названия уровней (интерфейс и CSV)
$level_names = [
    $lang->settings_history['lvl_info'],
    $lang->settings_history['lvl_warning'],
    $lang->settings_history['lvl_error'],
];

// ============================================================
//  ОБРАБОТКА POST ЗАПРОСОВ
// ============================================================
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cleanup':

            if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
                flash_message($lang->settings_history['flash_csrf'], 'error');
                admin_redirect('settings_history.php');
            }
            if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
                $cutoff = time() - (90 * 86400);
                $query = $db->sql_query_prepared("DELETE FROM sitelog WHERE category = 'settings' AND added < ?", [$cutoff]);
                $deleted = $db->affected_rows($query);
                flash_message(ags_fmt($lang->settings_history['flash_deleted'], (int)$deleted), "success");
            }
            admin_redirect("settings_history.php");
            break;

        case 'export':
            $search = $_GET['search'] ?? '';
            $user = $_GET['user'] ?? '';
            $history = get_settings_history(10000, 0, $search, $user);
            $logs = $history['logs'] ?? [];

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="settings_history_' . date('Y-m-d_H-i') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, [
                $lang->settings_history['csv_datetime'],
                $lang->settings_history['csv_uid'],
                $lang->settings_history['csv_user'],
                $lang->settings_history['csv_event'],
                $lang->settings_history['csv_level'],
                $lang->settings_history['csv_ip'],
            ]);

            foreach ($logs as $log) {
                fputcsv($output, [
                    date('Y-m-d H:i:s', (int)$log['added']),
                    $log['uid'],
                    $log['username'] ?? $lang->settings_history['lbl_system'],
                    $log['txt'],
                    $level_names[(int)$log['level']] ?? $lang->settings_history['lvl_info'],
                    $log['ipaddress'] ?? '0.0.0.0'
                ]);
            }
            fclose($output);
            exit;

        case 'export_json':
            $search = $_GET['search'] ?? '';
            $user = $_GET['user'] ?? '';
            $history = get_settings_history(10000, 0, $search, $user);
            $logs = $history['logs'] ?? [];

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="settings_history_' . date('Y-m-d_H-i') . '.json"');

            echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
            break;
    }
}

// ============================================================
//  ФУНКЦИИ
// ============================================================
if (!function_exists('get_settings_history')) {
    function get_settings_history(int $limit = 50, int $offset = 0, ?string $search = null, ?string $user = null): array {
        global $db;

        $params = [];
        $where_conditions = ["category = 'settings'"];

        if ($search !== null && $search !== '') {
            $where_conditions[] = "txt LIKE ?";
            $params[] = '%' . $search . '%';
        }

        if ($user !== null && $user !== '') {
            $where_conditions[] = "txt LIKE ?";
            $params[] = '%' . $user . '%';
        }

        $where_clause = "WHERE " . implode(" AND ", $where_conditions);

        $sql = "
            SELECT sl.*, u.username
            FROM sitelog sl
            LEFT JOIN users u ON sl.uid = u.id
            {$where_clause}
            ORDER BY sl.added DESC
            LIMIT ?, ?
        ";

        $query = $db->sql_query_prepared($sql, [...$params, $offset, $limit]);

        $logs = [];
        if ($query) {
            while ($row = $db->fetch_array($query)) {
                if (!empty($row['ipaddress'])) {
                    $ip = inet_ntop($row['ipaddress']);
                    $row['ipaddress'] = $ip !== false ? $ip : '0.0.0.0';
                } else {
                    $row['ipaddress'] = '0.0.0.0';
                }
                $logs[] = $row;
            }
        }

        $count_sql = "SELECT COUNT(*) as total FROM sitelog {$where_clause}";
        $count_query = $db->sql_query_prepared($count_sql, $params);

        $total = 0;
        if ($count_query && $db->num_rows($count_query) > 0) {
            $row = $db->fetch_array($count_query);
            $total = (int)$row['total'];
        }

        return [
            'logs' => $logs,
            'total' => $total
        ];
    }
}

// Получаем параметры фильтрации
$search = $_GET['search'] ?? '';
$user = $_GET['user'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Получаем историю
$history_data = get_settings_history($per_page, $offset, $search, $user);
$logs = $history_data['logs'] ?? [];
$total = $history_data['total'] ?? 0;

// ============================================================
//  СТАТИСТИКА ПО ЛОГАМ
// ============================================================
$log_stats = [
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'today' => 0,
    'this_week' => 0
];

$today_start = strtotime(date('Y-m-d 00:00:00'));
$week_start = strtotime(date('Y-m-d 00:00:00', strtotime('-7 days')));

foreach ($logs as $log) {
    $level = (int)$log['level'];
    if ($level === 0) $log_stats['info']++;
    elseif ($level === 1) $log_stats['warning']++;
    elseif ($level === 2) $log_stats['error']++;

    if ($log['added'] >= $today_start) $log_stats['today']++;
    if ($log['added'] >= $week_start) $log_stats['this_week']++;
}

// Создаем пагинацию
$page_url = 'settings_history.php?' . http_build_query(array_filter([
    'search' => $search,
    'user' => $user
]));
$multipage = multipage($total, $per_page, $page, $page_url);

// Короткий алиас для ланга страницы
$L = $lang->settings_history;

// Строки для JS: js_* → без префикса
$ags_js_lang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $ags_js_lang[substr((string)$k, 3)] = $v;
    }
}

stdhead($L['page_title']);
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/settings.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/settings_history.css">
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>

<?php flash_message(); ?>

<div class="container mt-3">
    <div class="history-header">
        <h1>
            <i class="fas fa-history text-primary me-2"></i>
            <?= htmlspecialchars($L['page_title']) ?>
            <small>v<?= B_VERSION ?></small>
        </h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary"><?= htmlspecialchars(ags_fmt($L['badge_total'], number_format($total))) ?></span>
            <a href="managesettings.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars($L['btn_back']) ?>
            </a>
        </div>
    </div>

    <!-- ============================================================
         СТАТИСТИКА
    ============================================================ -->
    <div class="stats-row">
        <div class="stat-box info">
            <div class="stat-number"><?= $log_stats['info'] ?></div>
            <div class="stat-label"><i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($L['stat_info']) ?></div>
        </div>
        <div class="stat-box warning">
            <div class="stat-number"><?= $log_stats['warning'] ?></div>
            <div class="stat-label"><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($L['stat_warnings']) ?></div>
        </div>
        <div class="stat-box error">
            <div class="stat-number"><?= $log_stats['error'] ?></div>
            <div class="stat-label"><i class="fas fa-times-circle me-1"></i> <?= htmlspecialchars($L['stat_errors']) ?></div>
        </div>
        <div class="stat-box today">
            <div class="stat-number"><?= $log_stats['today'] ?></div>
            <div class="stat-label"><i class="fas fa-calendar-day me-1"></i> <?= htmlspecialchars($L['stat_today']) ?></div>
        </div>
        <div class="stat-box week">
            <div class="stat-number"><?= $log_stats['this_week'] ?></div>
            <div class="stat-label"><i class="fas fa-calendar-week me-1"></i> <?= htmlspecialchars($L['stat_week']) ?></div>
        </div>
    </div>

    <!-- ============================================================
         ФИЛЬТРЫ
    ============================================================ -->
    <div class="history-filters">
        <form method="get" action="settings_history.php">
            <div class="filter-row">
                <div class="filter-group">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search"
                               placeholder="<?= htmlspecialchars($L['ph_search']) ?>"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="user"
                               placeholder="<?= htmlspecialchars($L['ph_user']) ?>"
                               value="<?= htmlspecialchars($user) ?>">
                    </div>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> <?= htmlspecialchars($L['btn_filter']) ?>
                    </button>
                    <a href="settings_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> <?= htmlspecialchars($L['btn_reset']) ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================================
         ТАБЛИЦА
    ============================================================ -->
    <div class="history-table-wrapper">
        <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="table history-table">
                <thead>
                    <tr>
                        <th style="width: 12%;"><i class="fas fa-clock me-1"></i> <?= htmlspecialchars($L['th_datetime']) ?></th>
                        <th style="width: 10%;"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($L['th_user']) ?></th>
                        <th style="width: 48%;"><i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($L['th_event']) ?></th>
                        <th style="width: 8%;"><i class="fas fa-flag me-1"></i> <?= htmlspecialchars($L['th_level']) ?></th>
                        <th style="width: 10%;"><i class="fas fa-network-wired me-1"></i> <?= htmlspecialchars($L['th_ip']) ?></th>
                        <th style="width: 12%;" class="text-center"><i class="fas fa-cog me-1"></i> <?= htmlspecialchars($L['th_actions']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php
                    $is_new = (time() - (int)$log['added']) < 300;
                    $level = (int)$log['level'];
                    $log_text = htmlspecialchars($log['txt']);
                    $is_long = strlen($log['txt']) > 100;
                    ?>
                    <tr class="<?= $is_new ? 'new-row' : '' ?>">
                        <td style="white-space: nowrap;">
                            <span title="<?= date('Y-m-d H:i:s', (int)$log['added']) ?>">
                                <?= my_datee('relative', $log['added']) ?>
                            </span>
                            <?php if ($is_new): ?>
                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;"><?= htmlspecialchars($L['badge_new']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['uid'] > 0): ?>
                            <a href="<?= get_profile_link($log['uid']) ?>" class="user-link">
                                <?= htmlspecialchars($log['username'] ?? $L['lbl_unknown']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted"><?= htmlspecialchars($L['lbl_system']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="log-message <?= $is_long ? 'collapsed' : 'expanded' ?>" data-full="<?= $log_text ?>">
                                <span class="log-text"><?= $log_text ?></span>
                                <?php if ($is_long): ?>
                                    <button class="log-toggle" onclick="toggleLog(this)"><?= htmlspecialchars($L['btn_show_more']) ?></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="log-level level-<?= $level ?>">
                                <?= htmlspecialchars($level_names[$level] ?? $L['lvl_info']) ?>
                            </span>
                        </td>
                        <td>
                            <code class="ip-address"><?= htmlspecialchars($log['ipaddress'] ?? '0.0.0.0') ?></code>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ($log['uid'] > 0): ?>
                                <a href="<?= get_profile_link($log['uid']) ?>" class="btn btn-outline-secondary" title="<?= htmlspecialchars($L['tip_profile']) ?>">
                                    <i class="fas fa-user"></i>
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-outline-info" onclick="copyLog(this)" title="<?= htmlspecialchars($L['tip_copy']) ?>">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="history-empty">
            <i class="fas fa-history text-muted"></i>
            <h4 class="text-muted"><?= htmlspecialchars($L['empty_title']) ?></h4>
            <p class="text-muted"><?= htmlspecialchars($L['empty_text']) ?></p>
            <a href="managesettings.php" class="btn btn-primary">
                <i class="fas fa-cog me-1"></i> <?= htmlspecialchars($L['btn_go_settings']) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($multipage): ?>
    <div class="mt-3">
        <?= $multipage ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         ДЕЙСТВИЯ
    ============================================================ -->
    <div class="history-actions">
        <button class="btn btn-outline-secondary btn-sm" id="autoRefreshToggle" onclick="toggleAutoRefresh()">
            <i class="fas fa-sync-alt me-1"></i> <?= htmlspecialchars($L['btn_autorefresh']) ?> <span id="autoRefreshStatus"><?= htmlspecialchars($L['lbl_on']) ?></span>
        </button>

        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="cleanup">
            <input type="hidden" name="confirm" value="yes">
            <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirmCleanup()">
                <i class="fas fa-trash me-1"></i> <?= htmlspecialchars(ags_fmt($L['btn_cleanup'], 90)) ?>
            </button>
        </form>

        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-csv me-1"></i> <?= htmlspecialchars($L['btn_export_csv']) ?>
            </button>
        </form>

        <form method="post" action="settings_history.php" style="display:inline;">
            <input type="hidden" name="action" value="export_json">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-code me-1"></i> <?= htmlspecialchars($L['btn_export_json']) ?>
            </button>
        </form>

        <a href="settings_history.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-sync-alt me-1"></i> <?= htmlspecialchars($L['btn_refresh']) ?>
        </a>
    </div>
</div>

<script>
const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// ============================================================
//  ПЕРЕВОД: t(key, fallback, ...args) — {1} и %1$s
// ============================================================
function t(key, fallback, ...args) {
    let s = (AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
    args.forEach((arg, i) => {
        const n = i + 1;
        s = s.split('{' + n + '}').join(String(arg)).split('%' + n + '$s').join(String(arg));
    });
    return s;
}

// ============================================================
//  ПОДТВЕРЖДЕНИЕ ОЧИСТКИ
// ============================================================
function confirmCleanup() {
    return confirm(t('confirm_cleanup',
        '⚠️ Are you sure you want to delete settings logs older than {1} days?\n\nThis action cannot be undone!', 90));
}

// ============================================================
//  РАЗВЕРНУТЬ/СВЕРНУТЬ ЛОГ
// ============================================================
function toggleLog(btn) {
    const container = btn.closest('.log-message');
    if (container.classList.contains('collapsed')) {
        container.classList.remove('collapsed');
        container.classList.add('expanded');
        btn.textContent = t('show_less', 'Show less');
    } else {
        container.classList.add('collapsed');
        container.classList.remove('expanded');
        btn.textContent = t('show_more', 'Show more');
    }
}

// ============================================================
//  КОПИРОВАТЬ ЛОГ
// ============================================================
function copyLog(btn) {
    const row = btn.closest('tr');
    const logText = row.querySelector('.log-message .log-text')?.textContent || row.querySelector('.log-message')?.textContent;
    if (logText) {
        navigator.clipboard?.writeText(logText).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => btn.innerHTML = original, 2000);
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = logText;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => btn.innerHTML = original, 2000);
        });
    }
}

// ============================================================
//  АВТООБНОВЛЕНИЕ
// ============================================================
let autoRefresh = true;
let refreshInterval = setInterval(() => {
    if (autoRefresh && !document.hidden) {
        location.reload();
    }
}, 60000);

function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    const status = document.getElementById('autoRefreshStatus');
    status.textContent = autoRefresh ? t('on', 'ON') : t('off', 'OFF');
    status.style.color = autoRefresh ? '#198754' : '#dc3545';

    if (autoRefresh) {
        refreshInterval = setInterval(() => {
            if (!document.hidden) location.reload();
        }, 60000);
    } else {
        clearInterval(refreshInterval);
    }
}

// Остановка автообновления при взаимодействии
document.addEventListener('click', () => {
    if (autoRefresh) {
        clearInterval(refreshInterval);
        refreshInterval = setInterval(() => {
            if (!document.hidden) location.reload();
        }, 60000);
    }
});

// ============================================================
//  ПОДСВЕТКА НОВЫХ ЗАПИСЕЙ
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.log-message').forEach(function(el) {
        const text = el.querySelector('.log-text');
        if (text && text.textContent.length > 100) {
            el.classList.add('collapsed');
        }
    });
});
</script>

<?php stdfoot(); ?>
