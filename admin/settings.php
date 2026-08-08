<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('B_VERSION', '6.6.3');

$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require TSDIR . '/cache/freeleech.php';

if ((int)($usergroups['cansettingspanel'] ?? 0) !== 1) {
    stdhead();
    error_no_permission(true);
    exit();
}

// ============================================================
//  ФУНКЦИЯ ЛОГИРОВАНИЯ ИЗМЕНЕНИЙ В SITELOG
// ============================================================
function log_settings_change(int $user_id, string $username, string $action, string $setting_name, ?string $old_value = null, ?string $new_value = null): void {
    global $db;
    
    // Формируем текст лога
    $txt = "[SETTINGS] {$username} ({$user_id}) {$action} '{$setting_name}'";
    
    if ($action === 'update') {
        $old_display = $old_value !== null ? substr($old_value, 0, 200) : 'NULL';
        $new_display = $new_value !== null ? substr($new_value, 0, 200) : 'NULL';
        $txt .= " from '{$old_display}' to '{$new_display}'";
    } elseif ($action === 'create') {
        $txt .= " with value '{$new_value}'";
    } elseif ($action === 'delete') {
        $txt .= " (was '{$old_value}')";
    }
    
    // Ограничиваем длину текста
    if (strlen($txt) > 65535) {
        $txt = substr($txt, 0, 65520) . '... [TRUNCATED]';
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip_binary = inet_pton($ip);
    if ($ip_binary === false) {
        $ip_binary = inet_pton('0.0.0.0');
    }
    
    $db->sql_query_prepared("
        INSERT INTO sitelog (uid, ipaddress, added, txt, category, level)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [
        $user_id,
        $ip_binary,
        time(),
        $txt,
        'settings',
        1 // Уровень важности: 0=info, 1=warning, 2=error
    ]);
}

// ── Получение истории изменений из sitelog ────────────────────────────────
function get_settings_history(int $limit = 50, int $offset = 0, ?string $search = null, ?string $user = null): array {
    global $db;
    
    $params = ['settings'];
    $where = ["category = ?"];
    
    if ($search !== null && $search !== '') {
        $where[] = "txt LIKE ?";
        $params[] = "%{$search}%";
    }
    
    if ($user !== null && $user !== '') {
        $where[] = "txt LIKE ?";
        $params[] = "%{$user}%";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where);
    
    $query = $db->sql_query_prepared("
        SELECT sl.*, u.username 
        FROM sitelog sl
        LEFT JOIN users u ON sl.uid = u.id
        {$where_clause}
        ORDER BY sl.added DESC 
        LIMIT ?, ?
    ", array_merge($params, [$offset, $limit]));
    
    $logs = [];
    while ($query && ($row = $db->fetch_array($query))) {
        // Конвертируем IP обратно в строку
        if (!empty($row['ipaddress'])) {
            $ip = inet_ntop($row['ipaddress']);
            $row['ipaddress'] = $ip !== false ? $ip : '0.0.0.0';
        } else {
            $row['ipaddress'] = '0.0.0.0';
        }
        $logs[] = $row;
    }
    
    // Получаем общее количество
    $count_query = $db->sql_query_prepared("
        SELECT COUNT(*) as total FROM sitelog {$where_clause}
    ", array_slice($params, 1));
    
    $total = 0;
    if ($count_query) {
        $row = $db->fetch_array($count_query);
        $total = (int)$row['total'];
    }
    
    return [
        'logs' => $logs,
        'total' => $total
    ];
}

// ── Очистка старых логов ──────────────────────────────────────────────────────
function cleanup_old_logs(int $days = 90): int {
    global $db;
    $cutoff = time() - ($days * 86400);
    $query = $db->sql_query_prepared("DELETE FROM sitelog WHERE category = 'settings' AND added < ?", [$cutoff]);
    return $db->affected_rows($query);
}

// ============================================================
//  СТАТИСТИКА ДЛЯ ДАШБОРДА
// ============================================================
function get_dashboard_stats(): array {
    global $db;
    
    $stats = [];
    
    // СТАТИСТИКА ПОЛЬЗОВАТЕЛЕЙ - 4 показателя одним запросом вместо четырёх
    $today    = date('Y-m-d');
    $week_ago = time() - 604800;

    $query = $db->sql_query_prepared(
        "SELECT
            SUM(enabled='yes') as total,
            SUM(lastactive > ?) as active,
            SUM(DATE(FROM_UNIXTIME(added)) = ?) as today,
            SUM(added > ?) as week
         FROM users",
        [time() - 86400, $today, $week_ago]
    );
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['users_total']  = (int)$row['total'];
        $stats['users_active'] = (int)$row['active'];
        $stats['users_today']  = (int)$row['today'];
        $stats['users_week']   = (int)$row['week'];
    }

    // СТАТИСТИКА ТОРРЕНТОВ - 7 показателей одним запросом вместо семи
    $query = $db->sql_query_prepared(
        "SELECT
            SUM(banned='no') as total,
            SUM(seeders > 0 AND banned='no') as active,
            SUM(DATE(FROM_UNIXTIME(added)) = ?) as today,
            SUM(added > ?) as week,
            SUM(seeders = 0 AND banned='no' AND visible='yes') as dead,
            SUM(CASE WHEN banned='no' THEN size ELSE 0 END) as total_size,
            COUNT(DISTINCT CASE WHEN banned='no' THEN category END) as categories_active
         FROM torrents",
        [$today, $week_ago]
    );
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['torrents_total']    = (int)$row['total'];
        $stats['torrents_active']   = (int)$row['active'];
        $stats['torrents_today']    = (int)$row['today'];
        $stats['torrents_week']     = (int)$row['week'];
        $stats['dead_torrents']     = (int)$row['dead'];
        $stats['total_size']        = mksize((float)$row['total_size']);
        $stats['categories_active'] = (int)$row['categories_active'];
    }

    // СТАТИСТИКА ПИРОВ - сиды/личеры одним запросом вместо двух
    $query = $db->sql_query_prepared(
        "SELECT SUM(seeder='yes') as seeders, SUM(seeder='no') as leechers FROM peers"
    );
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['seeders_total']  = (int)$row['seeders'];
        $stats['leechers_total'] = (int)$row['leechers'];
    } else {
        $stats['seeders_total']  = 0;
        $stats['leechers_total'] = 0;
    }

    $stats['peers_total'] = $stats['seeders_total'] + $stats['leechers_total'];

    // Уникальные/активные пользователи в пирах одним запросом вместо двух
    $time_ago = time() - 300;
    $query = $db->sql_query_prepared(
        "SELECT
            COUNT(DISTINCT userid) as unique_users,
            COUNT(DISTINCT CASE WHEN last_action > ? THEN userid END) as active_peers
         FROM peers",
        [$time_ago]
    );
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['unique_peers'] = (int)$row['unique_users'];
        $stats['active_peers'] = (int)$row['active_peers'];
    }
    
    // ДОПОЛНИТЕЛЬНАЯ СТАТИСТИКА
    $query = $db->sql_query_prepared("SELECT id, name, times_completed FROM torrents WHERE banned='no' ORDER BY times_completed DESC LIMIT 1");
    if ($query && $db->num_rows($query) > 0) {
        $row = $db->fetch_array($query);
        $stats['most_snatched'] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'times' => (int)$row['times_completed']
        ];
    }
    
    $query = $db->sql_query_prepared("
        SELECT t.id, t.name, COUNT(p.id) as peers_count 
        FROM torrents t 
        LEFT JOIN peers p ON t.id = p.torrent
        WHERE t.banned='no' 
        GROUP BY t.id 
        ORDER BY peers_count DESC 
        LIMIT 1
    ");
    if ($query && $db->num_rows($query) > 0) {
        $row = $db->fetch_array($query);
        $stats['most_active'] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'peers' => (int)$row['peers_count']
        ];
    }
    
    $query = $db->sql_query_prepared("
        SELECT t.id, t.name, COUNT(p.id) as seeders 
        FROM torrents t 
        LEFT JOIN peers p ON t.id = p.torrent AND p.seeder='yes' 
        WHERE t.banned='no' 
        GROUP BY t.id 
        ORDER BY seeders DESC 
        LIMIT 1
    ");
    if ($query && $db->num_rows($query) > 0) {
        $row = $db->fetch_array($query);
        $stats['most_seeded'] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'seeders' => (int)$row['seeders']
        ];
    }
    
    $query = $db->sql_query_prepared("
        SELECT u.id, u.username, COUNT(p.id) as seed_count 
        FROM peers p 
        LEFT JOIN users u ON p.userid = u.id 
        WHERE p.seeder='yes' 
        GROUP BY p.userid 
        ORDER BY seed_count DESC 
        LIMIT 3
    ");
    $stats['top_seeders'] = [];
    if ($query) {
        while ($row = $db->fetch_array($query)) {
            $stats['top_seeders'][] = [
                'id' => $row['id'],
                'username' => htmlspecialchars($row['username']),
                'count' => (int)$row['seed_count']
            ];
        }
    }
    
    return $stats;
}

// ── Проверка критических настроек для уведомлений ──────────────────────────
function check_critical_settings(): array {
    global $settings;
    $alerts = [];
    
    if (($settings['SITEONLINE'] ?? 'yes') === 'no') {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fas fa-power-off',
            'title' => 'Site is Offline',
            'message' => 'The site is currently in maintenance mode. Users cannot access the site.',
            'action' => '<a href="#main-settings" class="alert-link">Go to settings</a>'
        ];
    }
    
    if (($settings['disableregs'] ?? '0') === '1') {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'fas fa-user-slash',
            'title' => 'Registrations Disabled',
            'message' => 'New user registrations are currently disabled.',
            'action' => '<a href="#registration-settings" class="alert-link">Enable registrations</a>'
        ];
    }
    
    if (empty($settings['mysql_host'] ?? '')) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'fas fa-database',
            'title' => 'Announce DB Not Configured',
            'message' => 'MySQL host for announce is not configured. Tracker may not work.',
            'action' => '<a href="#announce-settings" class="alert-link">Configure now</a>'
        ];
    }
    
    $free_space = disk_free_space('/');
    if ($free_space !== false && $free_space < 1073741824) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'fas fa-hdd',
            'title' => 'Low Disk Space',
            'message' => 'Less than 1GB of free disk space available.',
            'action' => ''
        ];
    }
    
    $backup_file = TSDIR . '/admin/backup';
    if (!is_dir($backup_file) || !is_writable($backup_file)) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fas fa-exclamation-triangle',
            'title' => 'Backup Directory Issue',
            'message' => 'Backup directory is missing or not writable.',
            'action' => '<a href="#main-settings" class="alert-link">Check settings</a>'
        ];
    }
    
    return $alerts;
}

function flash_message(?string $message = null, string $type = 'info'): void
{
    if ($message !== null) {
        $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
        return;
    }
    if (empty($_SESSION['flash'])) return;

    echo '<div aria-live="polite" aria-atomic="true" class="position-relative">
          <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100">';
    foreach ($_SESSION['flash'] as $flash) {
        $cls = match($flash['type']) {
            'success'        => 'bg-success text-white',
            'error','danger' => 'bg-danger text-white',
            'warning'        => 'bg-warning text-dark',
            default          => 'bg-info text-white',
        };
        $msg = htmlspecialchars($flash['message']);
        echo "<div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header {$cls}'>
                  <strong class='me-auto'>Message</strong><small>Now</small>
                  <button type='button' class='btn-close' data-bs-dismiss='toast'></button>
                </div>
                <div class='toast-body'>{$msg}</div>
              </div>";
    }
    echo '</div></div>
    <script>document.addEventListener("DOMContentLoaded",()=>{
      document.querySelectorAll(".toast").forEach(t=>new bootstrap.Toast(t,{delay:5000}).show());
    });</script>';
    unset($_SESSION['flash']);
}

function admin_redirect(string $url): never
{
    if (!headers_sent()) {
        header("Location: " . str_replace("&amp;", "&", $url));
    } else {
        echo "<meta http-equiv='refresh' content='0; url={$url}'>";
    }
    exit;
}

function rebuild_announce_settings(): bool {
    global $db;
    $settings = [];
    $q = $db->sql_query_prepared("SELECT name, value FROM settings");
    while ($q && ($r = $db->fetch_array($q))) $settings[$r['name']] = $r['value'];

    $keys = ['nc','announce_wait','announce_interval',
             'max_rate','bannedclientdetect','allowed_clients',
             'checkconnectable','checkip','mysql_host','mysql_user','mysql_pass','mysql_db'];

    $c  = "<?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!\n";
    $c .= "if(!defined('IN_ANNOUNCE')) die('Hacking attempt!');\n\n";
    foreach ($keys as $k) {
        $c .= "\${$k} = '" . addslashes((string)($settings[$k] ?? '')) . "';\n";
    }
    foreach ([
        'BASEURL'            => $settings['BASEURL'] ?? '',
        'SITENAME'           => $settings['SITENAME'] ?? '',
        'privatetrackerpatch'=> $settings['privatetrackerpatch'] ?? 'no',
        'gzipcompress'       => $settings['gzipcompress'] ?? 'no',
        'charset'            => $settings['charset'] ?? 'UTF-8',
        'aggressivecheckip'  => $settings['aggressivecheckip'] ?? 'no',
        'snatchmod'          => $settings['snatchmod'] ?? 'yes',
        'bdayreward'         => $settings['bdayreward'] ?? 'yes',
        'bdayrewardtype'     => $settings['bdayrewardtype'] ?? 'freeleech',
    ] as $k => $v) {
        $c .= "\${$k} = '" . addslashes((string)$v) . "';\n";
    }
    $c .= "?>";
    return file_put_contents(INC_PATH . '/config_announce.php', $c) !== false;
}

// ── Load settings ──────────────────────────────────────────────────────────────
$settings = [];
$q = $db->sql_query_prepared("SELECT name, value FROM settings");
while ($q && ($r = $db->fetch_array($q))) $settings[$r['name']] = $r['value'];
$announce_url = $settings['announce_urls[]'] ?? '';

ob_start();

function save_to_settings(array $data): void {
    global $db;
    // ВАЖНО: требует UNIQUE-индекс на settings.name (см. migration_settings_unique.sql).
    // Без него ON DUPLICATE KEY UPDATE не сработает и будет плодить дубли строк
    // с одинаковым name при каждом сохранении.
    foreach ($data as $name => $value) {
        $db->sql_query_prepared(
            "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?",
            [$name, $value, $value]
        );
    }
    rebuild_settings();
}

/**
 * Читает несколько настроек одним запросом (WHERE name IN (...)) вместо
 * цикла с отдельным SELECT на каждый ключ. Используется POST-обработчиками
 * ниже перед сохранением - чтобы записать в лог старое значение для каждого
 * изменённого поля.
 */
function get_settings_values(array $keys): array {
    global $db;
    if (empty($keys)) {
        return [];
    }
    $ph    = implode(',', array_fill(0, count($keys), '?'));
    $check = $db->sql_query_prepared("SELECT name, value FROM settings WHERE name IN ({$ph})", $keys);
    $values = [];
    while ($check && ($row = $db->fetch_array($check))) {
        $values[$row['name']] = $row['value'];
    }
    return $values;
}

// ── POST handlers ──────────────────────────────────────────────────────────────
match(true) {

    isset($_POST['save_kps']) => (function(): void {
        global $db, $CURUSER;
        
        $keys = ['bonus','kpsseed','kpsupload','kpscomment','kpsthanks','kpsrate','kpspoll',
                 'kpsmaxpoint','kpsinvite','kpstitle','kpsvip','kpsgift','kpswarning','kpsratiofix',
                 'bdayreward','bdayrewardtype'];
        $old_values = get_settings_values($keys);
        
        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));
        if (!empty($data)) {
            save_to_settings($data);
            foreach ($data as $key => $new_value) {
                $old_value = $old_values[$key] ?? null;
                if ($old_value !== $new_value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $key,
                        $old_value,
                        $new_value
                    );
                }
            }
        }
        flash_message("KPS settings saved successfully!", "success");
        admin_redirect("settings.php#kps-settings");
    })(),

    isset($_POST['save_user_management']) => (function(): void {
        global $db, $CURUSER;
        
        $keys = ['max_dead_torrent_time','promote_gig_limit','promote_min_ratio',
                 'promote_min_reg_days','demote_min_ratio','referrergift','leechwarn_min_ratio',
                 'leechwarn_gig_limit','leechwarn_length','leechwarn_remove_ratio','ban_user_limit'];
        $old_values = get_settings_values($keys);
        
        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));
        if (!empty($data)) {
            save_to_settings($data);
            foreach ($data as $key => $new_value) {
                $old_value = $old_values[$key] ?? null;
                if ($old_value !== $new_value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $key,
                        $old_value,
                        $new_value
                    );
                }
            }
        }
        flash_message("Cleanup settings saved successfully!", "success");
        admin_redirect("settings.php#user-management-settings");
    })(),
    
    isset($_POST['save_registration']) => (function(): void {
        global $db, $CURUSER;
        
        $keys = ['regtype','minnamelength','maxnamelength','illegalusernames',
                 'minpasswordlength','maxpasswordlength','requirecomplexpasswords','failedlogincount',
                 'failedlogintext','username_method','disableregs','maxusers',
                 '_d_usergroup','invite_count','autogigsignup','autosbsignup','allowmultipleemails',
                 'betweenregstime','maxregsbetweentime'];
        $old_values = get_settings_values($keys);
        
        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));
        if (!empty($data)) {
            save_to_settings($data);
            foreach ($data as $key => $new_value) {
                $old_value = $old_values[$key] ?? null;
                if ($old_value !== $new_value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $key,
                        $old_value,
                        $new_value
                    );
                }
            }
        }
        flash_message("Registration settings saved successfully!", "success");
        admin_redirect("settings.php#registration-settings");
    })(),

    isset($_POST['save_forum_legacy']) => (function(): void {
        global $db, $CURUSER;

        $keys = ['defaultlanguage','enablepms','browsingthisthread','delayedthreadviews',
                 'showforumpagesbreadcrumb','showownunapproved','threadreadcut','ts_perpage',
                 'f_postsperpage','f_threadsperpage','userpppoptions','usertppoptions',
                 'loadlimit','shoutboxcharset','uploadspath','usezip'];
        $old_values = get_settings_values($keys);

        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));
        if (!empty($data)) {
            save_to_settings($data);
            foreach ($data as $key => $new_value) {
                $old_value = $old_values[$key] ?? null;
                if ($old_value !== $new_value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $key,
                        $old_value,
                        $new_value
                    );
                }
            }
        }
        flash_message("Forum / Legacy settings saved successfully!", "success");
        admin_redirect("settings.php#forum-legacy-settings");
    })(),

    isset($_POST['save_announce']) => (function(): void {
        global $db, $CURUSER;
        
        $keys = ['nc','announce_wait','announce_interval',
                 'max_rate','bannedclientdetect','allowed_clients',
                 'checkconnectable','checkip','mysql_host','mysql_user','mysql_pass','mysql_db'];
        $old_values = get_settings_values($keys);
        
        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));
        if (!empty($data)) { 
            save_to_settings($data); 
            rebuild_announce_settings();
            foreach ($data as $key => $new_value) {
                $old_value = $old_values[$key] ?? null;
                if ($old_value !== $new_value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $key,
                        $old_value,
                        $new_value
                    );
                }
            }
        }
        flash_message("Announce settings saved successfully!", "success");
        admin_redirect("settings.php#announce-settings");
    })(),

    isset($_POST['save_freeleech']) => (function(): void {
        global $CURUSER;
        $start   = $_POST['configoption']['start']  ?? '';
        $end     = $_POST['configoption']['end']    ?? '';
        $flstype = $_POST['configoption']['system'] ?? 'freeleech';
        $file    = TSDIR . '/cache/freeleech.php';
        $fp      = @fopen($file, 'w');
        if ($fp) {
            $c  = "<?php\n/** Cache: FreeLeech | Generated: " . gmdate('r') . " */\n";
            $c .= "\$__FLSTYPE = '" . addslashes($flstype) . "';\n";
            $c .= "\$__F_START = '" . addslashes($start) . "';\n";
            $c .= "\$__F_END   = '" . addslashes($end) . "';\n?>";
            fwrite($fp, $c); fclose($fp);
            
            log_settings_change(
                (int)$CURUSER['id'],
                $CURUSER['username'],
                'update',
                'freeleech_settings',
               "Type: {$old_type}, Start: {$old_start}, End: {$old_end}",
            "Type: {$flstype}, Start: {$start}, End: {$end}"
            );
            
            flash_message("Freeleech settings saved successfully!", "success");
            admin_redirect("settings.php?saved=freeleech#freeleech-settings");
        }
        flash_message("Error: unable to write FreeLeech cache!", "danger");
        admin_redirect("settings.php#freeleech-settings");
    })(),

    isset($_POST['save_staff']) => (function() use ($db): void {
        global $CURUSER;
        $valid = [];
        $q = $db->sql_query_prepared("SELECT u.id, u.username FROM users u LEFT JOIN usergroups g ON u.usergroup=g.gid WHERE u.enabled='yes' AND (g.cansettingspanel='1' OR g.issupermod='1' OR g.canstaffpanel='1')");
        while ($q && ($r = $db->fetch_array($q))) $valid[(string)$r['id']] = $r['username'];

        $entries = []; $errors = [];
        foreach (($_POST['staffids'] ?? []) as $i => $rawId) {
            $id   = trim((string)$rawId);
            $name = trim((string)($_POST['staffnames'][$i] ?? ''));
            if ($id === '' && $name === '') continue;
            if ($id !== '' && !ctype_digit($id))      { $errors[] = "{$name}:{$id} (invalid ID format)"; continue; }
            if (!isset($valid[$id]))                   { $errors[] = "{$name}:{$id} (not allowed)"; continue; }
            if (strcasecmp($valid[$id], $name) !== 0) { $errors[] = "{$name}:{$id} (name does not match)"; continue; }
            $entries[] = "{$name}:{$id}";
        }
        
        $old_staff = file_exists(CONFIG_DIR . '/STAFFTEAM') ? file_get_contents(CONFIG_DIR . '/STAFFTEAM') : 'empty';
        
        if (!empty($errors)) {
            flash_message("Errors: " . implode(', ', $errors), "danger");
        } elseif (file_put_contents(CONFIG_DIR . '/STAFFTEAM', implode(',', $entries), LOCK_EX) === false) {
            flash_message("Failed to write STAFFTEAM config file!", "danger");
        } else {
            $new_staff = implode(',', $entries);
            log_settings_change(
                (int)$CURUSER['id'],
                $CURUSER['username'],
                'update',
                'staff_team',
                $old_staff,
                $new_staff
            );
            flash_message("Staff team saved successfully!", "success");
        }
        admin_redirect("settings.php#staff-team");
    })(),

    $_SERVER['REQUEST_METHOD'] === 'POST' => (function() use ($db, $settings): void {
        global $CURUSER;
        $opts   = $_POST['configoption'] ?? [];
        $ofType = $_POST['offline_mode_type'] ?? 'limited';
        $ofMins = (int)($_POST['offline_minutes_input'] ?? 30);

        $old_values = get_settings_values(array_keys($opts));

        if (($opts['SITEONLINE'] ?? '') === 'no') {
            if ($ofType === 'unlimited') {
                $opts['offline_minutes'] = 'unlimited';
                write_log("[MAINTENANCE] Site set to offline (unlimited)");
            } else {
                $ofMins = max(1, min(1440, $ofMins));
                $end    = time() + $ofMins * 60;
                $opts['offline_minutes'] = (string)$end;
                write_log("[MAINTENANCE] Site offline for {$ofMins}min, back at: " . date('Y-m-d H:i:s', $end));
                if ($end <= time()) { $opts['SITEONLINE'] = 'yes'; $opts['offline_minutes'] = ''; }
            }
        } else {
            $opts['offline_minutes'] = '';
            if (isset($opts['SITEONLINE'])) write_log("[MAINTENANCE] Site set to online");
        }

        $db->begin_transaction(hide_errors: true);
        try {
            foreach ($opts as $name => $value) {
                if (!$db->sql_query_prepared("UPDATE settings SET value = ? WHERE name = ?", [$value, $name], 1))
                    throw new \Exception("Failed to update '{$name}'");
                    
                $old_value = $old_values[$name] ?? null;
                if ($old_value !== $value) {
                    log_settings_change(
                        (int)$CURUSER['id'],
                        $CURUSER['username'],
                        'update',
                        $name,
                        $old_value,
                        $value
                    );
                }
            }
            $db->commit(hide_errors: true);
            rebuild_settings();
            flash_message("Settings updated successfully!", "success");
        } catch (\Throwable $e) {
            $db->rollback(hide_errors: true);
            write_log("[ERROR] " . $e->getMessage());
            flash_message("Error: " . $e->getMessage(), "danger");
        }
        admin_redirect($_SERVER['PHP_SELF']);
    })(),

    default => null,
};

ob_end_flush();

// ── Load caches ────────────────────────────────────────────────────────────────
foreach (['nc','announce_wait','announce_interval',
          'max_rate','bannedclientdetect','allowed_clients',
          'checkconnectable','checkip','mysql_host','mysql_user','mysql_pass','mysql_db'] as $k) {
    $$k = $settings[$k] ?? '';
}

// KPS
$bonus          = $settings['bonus']          ?? 'enable';
$kpsupload      = $settings['kpsupload']      ?? '0';
$kpscomment     = $settings['kpscomment']     ?? '0';
$kpsthanks      = $settings['kpsthanks']      ?? '0';
$kpsrate        = $settings['kpsrate']        ?? '0';
$kpspoll        = $settings['kpspoll']        ?? '0';
$kpsmaxpoint    = $settings['kpsmaxpoint']    ?? '0';
$kpsinvite      = $settings['kpsinvite']      ?? 'yes';
$kpstitle       = $settings['kpstitle']       ?? 'yes';
$kpsvip         = $settings['kpsvip']         ?? 'yes';
$kpsgift        = $settings['kpsgift']        ?? 'yes';
$kpswarning     = $settings['kpswarning']     ?? 'yes';
$kpsratiofix    = $settings['kpsratiofix']    ?? 'yes';
$bdayreward     = $settings['bdayreward']     ?? 'yes';
$bdayrewardtype = $settings['bdayrewardtype'] ?? 'silverleech';
// SIGNUP
$regtype              = $settings['regtype']                ?? 'instant';
$minnamelength        = $settings['minnamelength']          ?? '3';
$maxnamelength        = $settings['maxnamelength']          ?? '20';
$illegalusernames     = $settings['illegalusernames']       ?? '';
$minpasswordlength    = $settings['minpasswordlength']      ?? '6';
$maxpasswordlength    = $settings['maxpasswordlength']      ?? '40';
$requirecomplexpasswords = $settings['requirecomplexpasswords'] ?? '0';
$allowmultipleemails  = $settings['allowmultipleemails']    ?? '0';
$failedlogincount     = $settings['failedlogincount']       ?? '0';
$failedlogintext      = $settings['failedlogintext']        ?? '0';
$username_method      = $settings['username_method']        ?? '0';
$disableregs          = $settings['disableregs']            ?? '0';
$maxusers             = $settings['maxusers']               ?? '0';
$_d_usergroup         = $settings['_d_usergroup']           ?? '1';
$invite_count         = $settings['invite_count']           ?? '0';
$autogigsignup        = $settings['autogigsignup']          ?? '0';
$autosbsignup         = $settings['autosbsignup']           ?? '0';
// CLEANUP
$max_dead_torrent_time = $settings['max_dead_torrent_time'] ?? '30';
$promote_gig_limit     = $settings['promote_gig_limit']     ?? '0';
$promote_min_ratio     = $settings['promote_min_ratio']     ?? '0.5';
$promote_min_reg_days  = $settings['promote_min_reg_days']  ?? '30';
$demote_min_ratio      = $settings['demote_min_ratio']      ?? '0.2';
$referrergift          = $settings['referrergift']          ?? '0';
$leechwarn_min_ratio   = $settings['leechwarn_min_ratio']   ?? '0.3';
$leechwarn_gig_limit   = $settings['leechwarn_gig_limit']   ?? '10';
$leechwarn_length      = $settings['leechwarn_length']      ?? '2';
$leechwarn_remove_ratio= $settings['leechwarn_remove_ratio'] ?? '0.5';
$ban_user_limit        = $settings['ban_user_limit']        ?? '3';

$offlineMinutesValue = $settings['offline_minutes'] ?? '';
$isUnlimited         = ($settings['SITEONLINE'] ?? 'yes') === 'no' && $offlineMinutesValue === 'unlimited';
$durationMinutes     = 30;
$timeRemaining       = '';
if (($settings['SITEONLINE'] ?? 'yes') === 'no') {
    if ($isUnlimited) {
        $timeRemaining = '<span class="text-success"><i class="fas fa-infinity me-1"></i>Unlimited (manual enable required)</span>';
    } elseif (is_numeric($offlineMinutesValue) && (int)$offlineMinutesValue > time()) {
        $rem             = (int)ceil(((int)$offlineMinutesValue - time()) / 60);
        $h               = (int)floor($rem / 60); $m = $rem % 60;
        $durationMinutes = max(1, $rem);
        $timeRemaining   = '<span class="text-warning"><i class="fas fa-clock me-1"></i>' . ($h > 0 ? "{$h}h " : '') . "{$m}m remaining</span>";
    } else {
        $durationMinutes = is_numeric($offlineMinutesValue) ? max(1, (int)$offlineMinutesValue) : 30;
        $timeRemaining   = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Time expired (should auto-enable)</span>';
    }
}

$staffarray = [];
$staffFile  = CONFIG_DIR . '/STAFFTEAM';
if (is_readable($staffFile)) {
    foreach (explode(',', (string)file_get_contents($staffFile)) as $entry) {
        $parts = explode(':', trim($entry), 2);
        if (count($parts) === 2 && $parts[0] !== '') {
            $staffarray[] = ['name' => trim($parts[0]), 'id' => trim($parts[1])];
        }
    }
}
$availableStaff = [];
$q = $db->sql_query_prepared("SELECT u.id, u.username, g.title FROM users u LEFT JOIN usergroups g ON u.usergroup=g.gid WHERE u.enabled='yes' AND (g.cansettingspanel='1' OR g.issupermod='1' OR g.canstaffpanel='1') ORDER BY u.username ASC");
while ($q && ($r = $db->fetch_array($q))) $availableStaff[] = $r;

$dashboard_stats = get_dashboard_stats();
$critical_alerts = check_critical_settings();

echo '<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">';
echo '<link href="' . $BASEURL . '/admin/templates/settings.css" rel="stylesheet">';
echo '<link href="' . $BASEURL . '/admin/templates/settings_extracted_styles.css" rel="stylesheet">';

stdhead();
flash_message();
?>
<title><?= $SITENAME ?> Admin Panel</title>

<div class="settings-container">
    <div class="settings-topbar">
        <h1>
            <i class="fas fa-sliders-h text-primary"></i>
            Settings Panel
            <small>v<?= B_VERSION ?></small>
        </h1>
        <div class="topbar-actions">
            <span class="status-indicator <?= ($settings['SITEONLINE'] ?? 'yes') === 'yes' ? 'online' : 'offline' ?>">
                <span class="dot"></span>
                <?= ($settings['SITEONLINE'] ?? 'yes') === 'yes' ? 'Online' : 'Offline' ?>
            </span>
            <button class="btn btn-primary btn-sm px-4" id="globalSaveBtn">
                <i class="fas fa-save me-1"></i> Save All
            </button>
            <a href="settings_history.php" class="btn btn-info btn-sm">
                <i class="fas fa-history me-1"></i> History
            </a>
        </div>
    </div>

    <div class="settings-layout">
        <!-- Sidebar -->
        <nav class="settings-sidebar">
            <ul class="sidebar-nav">
                <li><a class="nav-link active" href="#main-settings" data-bs-toggle="tab">
                    <i class="fas fa-cog icon-blue <?= (count($critical_alerts) > 0) ? 'fa-pulse' : '' ?>"></i> Main Settings
                    <?php if (count($critical_alerts) > 0): ?>
                        <span class="badge bg-danger"><?= count($critical_alerts) ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a class="nav-link" href="#tracker-settings" data-bs-toggle="tab">
                    <i class="fas fa-server icon-purple"></i> Tracker
                    <span class="badge bg-primary">Config</span>
                </a></li>
                <li><a class="nav-link" href="#date-time" data-bs-toggle="tab">
                    <i class="far fa-clock icon-teal fa-spin" style="--fa-animation-duration: 8s;"></i> Date & Time
                </a></li>
                <li><a class="nav-link" href="#cookie-settings" data-bs-toggle="tab">
                    <i class="fas fa-cookie icon-yellow"></i> Cookies
                </a></li>
                <li><a class="nav-link" href="#avatar-settings" data-bs-toggle="tab">
                    <i class="fas fa-user-circle icon-pink"></i> Avatars
                </a></li>
                <li><a class="nav-link" href="#security-settings" data-bs-toggle="tab">
                    <i class="fas fa-shield-alt icon-red <?= (($settings['SITEONLINE'] ?? 'yes') === 'no') ? 'fa-pulse' : '' ?>"></i> Security
                </a></li>
                <li><a class="nav-link" href="#email-settings" data-bs-toggle="tab">
                    <i class="fas fa-envelope icon-orange"></i> Email
                </a></li>
                <li><a class="nav-link" href="#announce-settings" data-bs-toggle="tab">
                    <i class="fas fa-broadcast-tower icon-cyan bounce"></i> Announce
                    <span class="badge bg-warning text-dark">Tracker</span>
                </a></li>
                <li><a class="nav-link" href="#kps-settings" data-bs-toggle="tab">
                    <i class="fas fa-coins icon-yellow pulse"></i> KPS
                    <span class="badge bg-success">Bonus</span>
                </a></li>
                <li><a class="nav-link" href="#user-management-settings" data-bs-toggle="tab">
                    <i class="fas fa-users-cog icon-teal"></i> Cleanup
                </a></li>
                <li><a class="nav-link" href="#registration-settings" data-bs-toggle="tab">
                    <i class="fas fa-user-plus icon-green pulse"></i> Registration
                </a></li>
                <li><a class="nav-link" href="#forum-legacy-settings" data-bs-toggle="tab">
                    <i class="fas fa-comments icon-teal"></i> Forum / Legacy
                </a></li>
                <li><a class="nav-link" href="#staff-team" data-bs-toggle="tab">
                    <i class="fas fa-user-shield icon-purple"></i> Staff Team
                    <span class="badge bg-info"><?= count($staffarray) ?></span>
                </a></li>
                <li><a class="nav-link" href="#freeleech-settings" data-bs-toggle="tab">
                    <i class="fas fa-gift icon-red bounce"></i> Freeleech
                    <span class="badge bg-danger">Promo</span>
                </a></li>
				<li><a class="nav-link" href="index.php?act=torrents_promo">
                    <i class="fas fa-random icon-cyan"></i> Torrent Promo Rules
                    <span class="badge bg-info">Promo</span>
                </a></li>
				<li><a class="nav-link" href="index.php?act=seedbonus_settings">
                    <i class="fas fa-coins icon-yellow"></i> Seedbonus System
                    <span class="badge bg-success">Bonus</span>
                </a></li>
                <li><a class="nav-link" href="settings_history.php">
                    <i class="fas fa-history icon-blue"></i> History
                    <span class="badge bg-info">Logs</span>
                </a></li>
                <li><a class="nav-link" href="index.php?act=cronjobs">
                    <i class="fas fa-clock icon-blue"></i> Cronjobs
                    <span class="badge bg-secondary">Jobs</span>
                </a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="settings-main">
            
            <!-- ====== DASHBOARD STATS ====== -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon pulse"><i class="fas fa-users icon-blue"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['users_total'] ?? 0) ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= $dashboard_stats['users_today'] ?? 0 ?> today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bounce"><i class="fas fa-user-check icon-green"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['users_active'] ?? 0) ?></div>
                    <div class="stat-label">Active Users (24h)</div>
                    <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= round((($dashboard_stats['users_active'] ?? 0) / max(1, ($dashboard_stats['users_total'] ?? 1))) * 100) ?>%</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon spin"><i class="fas fa-magnet icon-purple"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['torrents_total'] ?? 0) ?></div>
                    <div class="stat-label">Total Torrents</div>
                    <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= $dashboard_stats['torrents_today'] ?? 0 ?> today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pulse"><i class="fas fa-upload icon-green"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['torrents_active'] ?? 0) ?></div>
                    <div class="stat-label">Active Torrents</div>
                    <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= round((($dashboard_stats['torrents_active'] ?? 0) / max(1, ($dashboard_stats['torrents_total'] ?? 1))) * 100) ?>%</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bounce"><i class="fas fa-hdd icon-orange"></i></div>
                    <div class="stat-number"><?= $dashboard_stats['total_size'] ?? '0 B' ?></div>
                    <div class="stat-label">Total Size</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon spin"><i class="fas fa-network-wired icon-cyan"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['peers_total'] ?? 0) ?></div>
                    <div class="stat-label">Total Peers</div>
                    <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= round((($dashboard_stats['seeders_total'] ?? 0) / max(1, ($dashboard_stats['peers_total'] ?? 1))) * 100) ?>% seeders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pulse"><i class="fas fa-arrow-up icon-green"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['seeders_total'] ?? 0) ?></div>
                    <div class="stat-label">Seeders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bounce"><i class="fas fa-arrow-down icon-red"></i></div>
                    <div class="stat-number"><?= number_format($dashboard_stats['leechers_total'] ?? 0) ?></div>
                    <div class="stat-label">Leechers</div>
                </div>
            </div>

            <!-- ====== CRITICAL ALERTS ====== -->
            <?php if (!empty($critical_alerts)): ?>
            <div class="alerts-container">
                <?php foreach ($critical_alerts as $alert): ?>
                <div class="alert-card <?= $alert['type'] ?>">
                    <div class="alert-icon <?= ($alert['type'] === 'danger') ? 'bounce' : '' ?>">
                        <i class="<?= $alert['icon'] ?>"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title"><?= $alert['title'] ?></div>
                        <div class="alert-message"><?= $alert['message'] ?></div>
                        <?php if (!empty($alert['action'])): ?>
                            <div class="alert-action"><?= $alert['action'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="tab-content">

                <!-- ── MAIN SETTINGS ─────────────────────────────────────────────── -->
                <div class="tab-pane fade show active" id="main-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-info-circle header-icon icon-blue"></i>
                                Basic Information
                            </div>
                            <div class="card-body">
                                <div class="settings-grid-2">
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-tag label-icon icon-blue"></i>Tracker Name</label>
                                        <input type="text" class="form-control-modern" 
                                               name="configoption[SITENAME]" 
                                               value="<?= htmlspecialchars($settings['SITENAME'] ?? '') ?>"
                                               placeholder="My Tracker">
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-link label-icon icon-blue"></i>Base URL</label>
                                        <input type="text" class="form-control-modern" 
                                               name="configoption[BASEURL]" 
                                               value="<?= htmlspecialchars($settings['BASEURL'] ?? '') ?>"
                                               placeholder="https://yourtracker.com">
                                        <div class="form-text">No trailing slash!</div>
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-envelope label-icon icon-orange"></i>Site Email</label>
                                        <input type="email" class="form-control-modern" 
                                               name="configoption[SITEEMAIL]" 
                                               value="<?= htmlspecialchars($settings['SITEEMAIL'] ?? '') ?>">
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-address-book label-icon icon-teal"></i>Contact Email(s)</label>
                                        <input type="text" class="form-control-modern" 
                                               name="configoption[contactemail]" 
                                               value="<?= htmlspecialchars($settings['contactemail'] ?? '') ?>"
                                               placeholder="admin@example.com, support@example.com">
                                        <div class="form-text">Separate with commas</div>
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-quote-right label-icon icon-purple"></i>Tracker Slogan</label>
                                        <input type="text" class="form-control-modern" 
                                               name="configoption[slogan]" 
                                               value="<?= htmlspecialchars($settings['slogan'] ?? '') ?>"
                                               placeholder="The best tracker ever">
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-language label-icon icon-cyan"></i>Default Language</label>
                                        <select class="form-select-modern" name="configoption[default_language]">
                                            <option value="english" <?= ($settings['default_language'] ?? 'english') === 'english' ? 'selected' : '' ?>>🇬🇧 English</option>
                                            <option value="russian" <?= ($settings['default_language'] ?? '') === 'russian' ? 'selected' : '' ?>>🇷🇺 Russian</option>
                                            <option value="ukrainian" <?= ($settings['default_language'] ?? '') === 'ukrainian' ? 'selected' : '' ?>>🇺🇦 Ukrainian</option>
                                            <option value="german" <?= ($settings['default_language'] ?? '') === 'german' ? 'selected' : '' ?>>🇩🇪 German</option>
                                            <option value="french" <?= ($settings['default_language'] ?? '') === 'french' ? 'selected' : '' ?>>🇫🇷 French</option>
                                            <option value="spanish" <?= ($settings['default_language'] ?? '') === 'spanish' ? 'selected' : '' ?>>🇪🇸 Spanish</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-chart-line icon-blue"></i> SEO</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid-2">
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-tags label-icon icon-blue"></i>Meta Keywords</label>
                                        <textarea class="form-control-modern" name="configoption[metakeywords]" rows="2"><?= htmlspecialchars($settings['metakeywords'] ?? '') ?></textarea>
                                        <div class="form-text">Separate with commas</div>
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-align-left label-icon icon-blue"></i>Meta Description</label>
                                        <textarea class="form-control-modern" name="configoption[metadesc]" rows="2"><?= htmlspecialchars($settings['metadesc'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-power-off icon-red <?= (($settings['SITEONLINE'] ?? 'yes') === 'no') ? 'pulse' : '' ?>"></i> Site Status</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid-2">
                                    <?php fswitch('SITEONLINE', ($settings['SITEONLINE']??'yes')==='yes', 'Site Online', 'Turn off to show the maintenance message to visitors', 'fas fa-power-off'); ?>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-globe label-icon icon-blue"></i>Maintenance Message</label>
                                        <input type="text" class="form-control-modern" 
                                               name="configoption[offline_message]" 
                                               value="<?= htmlspecialchars($settings['offline_message'] ?? 'Site is currently under maintenance. Please check back later.') ?>"
                                               placeholder="Maintenance message">
                                    </div>
                                </div>

                                <div id="offlineDurationGroup" class="offline-wrap mt-3" 
                                     style="display:<?= ($settings['SITEONLINE']??'yes')==='no'?'block':'none' ?>">
                                    <?php if ($timeRemaining): ?>
                                        <div class="mb-2 small"><?= $timeRemaining ?>
                                            <?php if (!$isUnlimited && is_numeric($offlineMinutesValue)): ?>
                                                <div class="text-muted mt-1">Back at: <?= date('Y-m-d H:i:s', (int)$offlineMinutesValue) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" 
                                               id="limitedMode" value="limited" <?= !$isUnlimited?'checked':'' ?>>
                                        <label class="form-check-label" for="limitedMode"><i class="fas fa-hourglass-half icon-yellow"></i> Limited Time</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" 
                                               id="unlimitedMode" value="unlimited" <?= $isUnlimited?'checked':'' ?>>
                                        <label class="form-check-label" for="unlimitedMode"><i class="fas fa-infinity icon-purple"></i> Unlimited</label>
                                    </div>
                                    <div id="timeLimitGroup" style="display:<?= !$isUnlimited?'block':'none' ?>">
                                        <label class="form-label"><i class="fas fa-clock icon-blue"></i> Duration (minutes)</label>
                                        <div class="input-group" style="max-width:220px">
                                            <input type="number" min="1" max="1440" class="form-control-modern" 
                                                   name="offline_minutes_input" value="<?= !$isUnlimited?$durationMinutes:30 ?>">
                                            <span class="input-group-text">min (max 24h)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── TRACKER SETTINGS ──────────────────────────────────────────── -->
                <div class="tab-pane fade" id="tracker-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-server header-icon icon-purple"></i>
                                Tracker Configuration
                            </div>
                            <div class="card-body">
                                <?php
                                $yn  = ['yes'=>'Yes','no'=>'No'];
                                $yn1 = ['1'=>'Yes','0'=>'No'];
                                function fsel(string $n,array $o,string $cur,string $lbl,string $tip='',string $icon='fas fa-toggle-on'): void {
                                    echo '<div class="form-group-modern"><label><i class="'.$icon.' label-icon icon-blue"></i>'.htmlspecialchars($lbl).'</label>';
                                    echo '<select class="form-select-modern" name="configoption['.htmlspecialchars($n).']">';
                                    foreach($o as $v=>$t) echo '<option value="'.htmlspecialchars((string)$v).'"'.($cur==$v?' selected':'').'>'.htmlspecialchars($t).'</option>';
                                    echo '</select>';
                                    if($tip) echo '<div class="form-text">'.htmlspecialchars($tip).'</div>';
                                    echo '</div>';
                                }
                                // Переключатель для бинарных настроек (yes/no, 1/0) - вместо select
                                // с двумя опциями. Скрытое поле перед чекбоксом гарантирует, что
                                // значение придёт в любом случае: PHP берёт последнее значение среди
                                // повторяющихся имён полей, а порядок в HTML/теле запроса сохраняется -
                                // если чекбокс не отмечен, отправится только скрытое поле (offValue);
                                // если отмечен, отправятся оба, но чекбокс идёт после и побеждает (onValue).
                                function fswitch(string $n, bool $checked, string $lbl, string $tip='', string $icon='fas fa-toggle-on', string $onValue='yes', string $offValue='no'): void {
                                    echo '<div class="form-group-modern"><label><i class="'.$icon.' label-icon icon-blue"></i>'.htmlspecialchars($lbl).'</label>';
                                    echo '<div class="form-check form-switch">';
                                    echo '<input type="hidden" name="configoption['.htmlspecialchars($n).']" value="'.htmlspecialchars($offValue).'">';
                                    echo '<input type="checkbox" class="form-check-input" role="switch" id="sw_'.htmlspecialchars($n).'" name="configoption['.htmlspecialchars($n).']" value="'.htmlspecialchars($onValue).'"'.($checked?' checked':'').'>';
                                    echo '</div>';
                                    if($tip) echo '<div class="form-text">'.htmlspecialchars($tip).'</div>';
                                    echo '</div>';
                                }
                                function ftxt(string $n,string $v,string $lbl,string $tip='',string $type='text',string $icon='fas fa-edit'): void {
                                    echo '<div class="form-group-modern"><label><i class="'.$icon.' label-icon icon-blue"></i>'.htmlspecialchars($lbl).'</label>';
                                    echo '<input type="'.$type.'" class="form-control-modern" name="configoption['.htmlspecialchars($n).']" value="'.htmlspecialchars($v).'">';
                                    if($tip) echo '<div class="form-text">'.htmlspecialchars($tip).'</div>';
                                    echo '</div>';
                                }
                                $s = $settings;
                                ?>
                                <div class="settings-grid">
                                    <?php fswitch('use_xmlhttprequest', ($s['use_xmlhttprequest']??'1') === '1', 'Use XMLHttpRequest', '', 'fas fa-code', '1', '0'); ?>
                                    <?php fswitch('seourls', ($s['seourls']??'no') === 'yes', 'Active SEO', '', 'fas fa-search'); ?>
                                    <?php fswitch('gzipcompress', ($s['gzipcompress']??'yes') === 'yes', 'GZIP Compression Enabled', 'Compresses pages for faster delivery', 'fas fa-compress-alt'); ?>
                                    <?php fswitch('jumptopagemultipage', ($s['jumptopagemultipage']??'1') === '1', 'Show Jump To Page in Pagination', '', 'fas fa-arrows-alt-h', '1', '0'); ?>
                                    <?php fswitch('hitrun', ($s['hitrun']??'yes') === 'yes', 'HIT & RUN System Enabled?', '', 'fas fa-running'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-arrows-alt-h icon-blue"></i> Limits & Paths</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('maxloginattempts',$s['maxloginattempts']??'5','Max. Login Attempts','Ban IPs over this limit','number','fas fa-user-lock'); ?>
                                    <?php ftxt('maxmultipagelinks',$s['maxmultipagelinks']??'5','Max Pagination Links','','number','fas fa-link'); ?>
                                    <?php ftxt('wolcutoffmins',$s['wolcutoffmins']??'15','Cut-off Time (mins)','Minutes before user marked offline','number','fas fa-clock'); ?>
                                    <?php ftxt('hitrun_ratio',$s['hitrun_ratio']??'0.5','Min. Ratio for H&R','','text','fas fa-percent'); ?>
                                    <?php ftxt('hitrun_gig',$s['hitrun_gig']??'5','Min. GB for H&R','','number','fas fa-database'); ?>
                                    <?php ftxt('announce_urls[]',$announce_url,'Announce URL','Full URL to announce.php','text','fas fa-broadcast-tower'); ?>
                                    <?php ftxt('torrent_dir',$s['torrent_dir']??'','Torrent Directory Path','No trailing slash','text','fas fa-folder'); ?>
                                    <?php ftxt('pic_base_url',$s['pic_base_url']??'','Image Directory Path','With trailing slash','text','fas fa-images'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-paperclip icon-blue"></i> Attachments</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php fswitch('enableattachments', ($s['enableattachments']??'1') === '1', 'Enable Attachment Functionality', 'If disabled, users cannot upload attachments to posts/comments', 'fas fa-paperclip', '1', '0'); ?>
                                    <?php ftxt('maxattachments',$s['maxattachments']??'5','Max Attachments Per Post','Maximum number of attachments a user can upload per comment/post. 0 = disabled.','number','fas fa-layer-group'); ?>
                                    <?php fsel('attachthumbnails',['yes'=>'Thumbnail','no'=>'Full Size Image','download'=>'As Download Link'],$s['attachthumbnails']??'yes','Show Attached Thumbnails','How image attachments should be displayed in comments/posts','fas fa-image'); ?>
                                    <?php ftxt('attachthumbh',$s['attachthumbh']??'96','Thumbnail Max Height','In pixels','number','fas fa-arrow-down'); ?>
                                    <?php ftxt('attachthumbw',$s['attachthumbw']??'96','Thumbnail Max Width','In pixels','number','fas fa-arrow-right'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── DATE & TIME ───────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="date-time">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="far fa-clock header-icon icon-teal"></i>
                                Date & Time Settings
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php ftxt('dateformat',$settings['dateformat']??'d M Y','Date Format','PHP date() format','text','fas fa-calendar-alt'); ?>
                                    <?php ftxt('timeformat',$settings['timeformat']??'H:i','Time Format','','text','fas fa-clock'); ?>
                                    <?php ftxt('regdateformat',$settings['regdateformat']??'d M Y','Registered Date Format','','text','fas fa-calendar-check'); ?>
                                    <?php ftxt('datetimesep',$settings['datetimesep']??', ','Date/Time Separator','','text','fas fa-minus'); ?>
                                    <?php fsel('dstcorrection',['1'=>'Yes','0'=>'No'],$settings['dstcorrection']??'0','Day Light Savings Time?','','fas fa-sun'); ?>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-globe-asia label-icon icon-blue"></i> Default Timezone Offset</label>
                                        <select class="form-select-modern" name="configoption[timezoneoffset]">
                                            <?php foreach(['-12','-11','-10','-9','-8','-7','-6','-5','-4','-3.5','-3','-2','-1','0','+1','+2','+3','+3.5','+4','+4.5','+5','+5.5','+5.75','+6','+7','+8','+9','+9.5','+10','+10.5','+11','+12'] as $tz):
                                                $sel = ($settings['timezoneoffset']??'0')==$tz?' selected':'';
                                                echo "<option value=\"{$tz}\"{$sel}>GMT {$tz}</option>";
                                            endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── COOKIES ───────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="cookie-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-cookie header-icon icon-yellow"></i>
                                Cookie Settings
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php ftxt('cookiedomain',$settings['cookiedomain']??'','Cookie Domain','Start with a dot for all subdomains','text','fas fa-globe'); ?>
                                    <?php ftxt('cookiepath',$settings['cookiepath']??'/','Cookie Path','','text','fas fa-folder-open'); ?>
                                    <?php ftxt('cookieprefix',$settings['cookieprefix']??'','Cookie Prefix','','text','fas fa-tag'); ?>
                                    <?php fsel('cookiesecureflag',['1'=>'Yes','0'=>'No'],$settings['cookiesecureflag']??'0','Secure Cookie Flag','Only enable on HTTPS','fas fa-lock'); ?>
                                    <?php fsel('cookiesamesiteflag',['1'=>'Yes','0'=>'No'],$settings['cookiesamesiteflag']??'0','SameSite Cookie Flag','Prevents CSRF attacks','fas fa-shield-alt'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── AVATARS ───────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="avatar-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-user-circle header-icon icon-pink"></i>
                                Avatar Settings
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php ftxt('useravatar',$settings['useravatar']??'','Default User Avatar','Used when user has no avatar','text','fas fa-user'); ?>
                                    <?php ftxt('useravatardims',$settings['useravatardims']??'40x40','Default Avatar Dimensions','40x40 or 40|40','text','fas fa-arrows-alt'); ?>
                                    <?php ftxt('maxavatardims',$settings['maxavatardims']??'100x100','Maximum Avatar Dimensions','','text','fas fa-expand'); ?>
                                    <?php ftxt('avataruploadpath',$settings['avataruploadpath']??'','Avatar Upload Path','','text','fas fa-upload'); ?>
                                    <?php ftxt('avatarsize',$settings['avatarsize']??'102400','Maximum Avatar Size (bytes)','','number','fas fa-weight'); ?>
                                    <?php fsel('allowremoteavatars',['1'=>'Yes','0'=>'No'],$settings['allowremoteavatars']??'0','Allow Remote Avatars','Exposes your server IP','fas fa-cloud-upload-alt'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── SECURITY ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="security-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-shield-alt header-icon icon-red <?= (($settings['SITEONLINE'] ?? 'yes') === 'no') ? 'pulse' : '' ?>"></i>
                                Security Settings
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php fswitch('aggressivecheckip', ($settings['aggressivecheckip']??'no') === 'yes', 'Aggressive IP Ban?', '', 'fas fa-ban'); ?>
                                    <?php fswitch('privatetrackerpatch', ($settings['privatetrackerpatch']??'no') === 'yes', 'Private Tracker Patch Enabled?', '', 'fas fa-shield-virus'); ?>                                                         
								</div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── EMAIL ─────────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="email-settings">
                    <form method="post" class="settings-form">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-envelope header-icon icon-orange"></i>
                                Email Settings
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php fsel('mail_handler',['mail'=>'PHP Mail','smtp'=>'SMTP','sendmail'=>'Sendmail'],$settings['mail_handler']??'mail','Mail Handler','','fas fa-mail-bulk'); ?>
                                    <?php fsel('mail_logging',['0'=>'None','1'=>'Log without content','2'=>'Log everything'],$settings['mail_logging']??'0','Mail Logging','','fas fa-clipboard-list'); ?>
                                    <?php fsel('mail_message_id',['1'=>'Yes','0'=>'No'],$settings['mail_message_id']??'1','Add Message-ID in headers?','Disable on shared hosting if spam issues','fas fa-id-card'); ?>
                                    <?php ftxt('mail_queue_limit',$settings['mail_queue_limit']??'50','Messages to send from mail queue','Max per cron batch','number','fas fa-queue'); ?>
                                </div>
                                <?php if (($settings['mail_handler']??'mail')==='smtp'): ?>
                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-server icon-blue"></i> SMTP Configuration</span>
                                    <span class="line"></span>
                                </div>
                                <div class="settings-grid">
                                    <?php ftxt('smtp_host',$settings['smtp_host']??'','SMTP Host','','text','fas fa-server'); ?>
                                    <?php ftxt('smtp_user',$settings['smtp_user']??'','SMTP Username','','text','fas fa-user'); ?>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-key label-icon icon-red"></i> SMTP Password</label>
                                        <input type="password" class="form-control-modern" name="configoption[smtp_pass]" value="<?= htmlspecialchars($settings['smtp_pass']??'') ?>" placeholder="Leave blank to keep current">
                                    </div>
                                    <?php ftxt('smtp_port',$settings['smtp_port']??'587','SMTP Port','25 / 465 (SSL) / 587 (TLS)','number','fas fa-plug'); ?>
                                    <?php fsel('secure_smtp',['0'=>'No encryption','1'=>'SSL encryption','2'=>'TLS encryption'],$settings['secure_smtp']??'0','Secure SMTP','','fas fa-lock'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── ANNOUNCE ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="announce-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_announce" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-broadcast-tower header-icon icon-cyan bounce"></i>
                                Announce Settings
                                <span class="badge bg-warning text-dark ms-auto">Tracker Core</span>
                            </div>
                            <div class="card-body">
                                <div class="settings-grid">
                                    <?php fswitch('nc', (string)$nc === 'yes', 'Disable DL/UL?', 'Block non-connectable peers', 'fas fa-toggle-off'); ?>
                                    <?php fswitch('bannedclientdetect', (string)$bannedclientdetect === 'yes', 'Banned Client Detection?', '', 'fas fa-ban'); ?>
                                    <?php fswitch('checkconnectable', (string)$checkconnectable === 'yes', 'Detect Connectable?', 'Decreases performance', 'fas fa-wifi'); ?>
                                    <?php fswitch('checkip', (string)$checkip === 'yes', 'Check IP?', 'Match DB IP vs client IP', 'fas fa-ip'); ?>
                                    <?php ftxt('announce_wait',(string)$announce_wait,'Min. Announce Refresh Time?','Flood limit in seconds','number','fas fa-hourglass'); ?>
                                    <?php ftxt('announce_interval',(string)$announce_interval,'Announce Interval?','Higher = better performance','number','fas fa-clock'); ?>
                                    <?php ftxt('max_rate',(string)$max_rate,'Max. Transfer Rate?','Flag suspicious speeds above this','number','fas fa-tachometer-alt'); ?>
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-check-circle label-icon icon-green"></i> Allowed Clients</label>
                                    <textarea class="form-control-modern" name="configoption[allowed_clients]" rows="4"><?= htmlspecialchars((string)$allowed_clients) ?></textarea>
                                    <div class="form-text">Whitelist of allowed clients (one per line)</div>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-database icon-blue"></i> Database Connection (Announce)</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('mysql_host',(string)$mysql_host,'MySQL Host','','text','fas fa-hdd'); ?>
                                    <?php ftxt('mysql_user',(string)$mysql_user,'MySQL User','','text','fas fa-user'); ?>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-key label-icon icon-red"></i> MySQL Password</label>
                                        <input type="password" class="form-control-modern" name="configoption[mysql_pass]" placeholder="Leave blank to keep current">
                                    </div>
                                    <?php ftxt('mysql_db',(string)$mysql_db,'MySQL Database Name','','text','fas fa-database'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── KPS ───────────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="kps-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_kps" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-coins header-icon icon-yellow pulse"></i>
                                KPS (Bonus Points) System
                                <span class="badge bg-success ms-auto">Points</span>
                            </div>
                            <div class="card-body">
                                <div class="form-group-modern">
                                    <label><i class="fas fa-toggle-on label-icon icon-blue"></i> KPS System Enabled?</label>
                                    <select class="form-select-modern" name="configoption[bonus]">
                                        <option value="enable"      <?= $bonus==='enable'     ?'selected':''?>>✅ Yes, Enabled</option>
                                        <option value="disablesave" <?= $bonus==='disablesave'?'selected':''?>>⏸️ No, But Save Points</option>
                                        <option value="disable"     <?= $bonus==='disable'    ?'selected':''?>>❌ No, Disabled</option>
                                    </select>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-star icon-yellow"></i> Point Values</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('kpsupload',$kpsupload,'Upload Point','','number','fas fa-cloud-upload-alt'); ?>
                                    <?php ftxt('kpscomment',$kpscomment,'Post/Comment/Thread Point','','number','fas fa-comment'); ?>
                                    <?php ftxt('kpsthanks',$kpsthanks,'Thanks Point','','number','fas fa-heart'); ?>
                                    <?php ftxt('kpsrate',$kpsrate,'Rating Point','','number','fas fa-star'); ?>
                                    <?php ftxt('kpspoll',$kpspoll,'Poll Point','','number','fas fa-poll'); ?>
                                    <?php ftxt('kpsmaxpoint',$kpsmaxpoint,'Max. Bonus Point','','number','fas fa-arrow-up'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-toggle-on icon-blue"></i> Feature Toggles</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php fswitch('kpsinvite', $kpsinvite === 'yes', 'Enable Invite Usage?', '', 'fas fa-envelope'); ?>
                                    <?php fswitch('kpstitle', $kpstitle === 'yes', 'Enable Custom Title Usage?', '', 'fas fa-user-tag'); ?>
                                    <?php fswitch('kpsvip', $kpsvip === 'yes', 'Enable VIP Status Usage?', '', 'fas fa-crown'); ?>
                                    <?php fswitch('kpsgift', $kpsgift === 'yes', 'Enable Give A Karma Gift?', '', 'fas fa-gift'); ?>
                                    <?php fswitch('kpswarning', $kpswarning === 'yes', 'Enable Remove Warning Usage?', '', 'fas fa-exclamation-triangle'); ?>
                                    <?php fswitch('kpsratiofix', $kpsratiofix === 'yes', 'Enable Fix Torrent Ratio Usage?', '', 'fas fa-balance-scale'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-birthday-cake icon-pink"></i> Birthday Reward</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php fswitch('bdayreward', $bdayreward === 'yes', 'Birthday Reward System Enabled?', '', 'fas fa-birthday-cake'); ?>
                                    <?php fsel('bdayrewardtype',['freeleech'=>'Free Leech','silverleech'=>'Silver Leech','doubleupload'=>'x2 Upload'],$bdayrewardtype,'Birthday Reward Type','','fas fa-gift'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── CLEANUP ───────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="user-management-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_user_management" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-users-cog header-icon icon-teal"></i>
                                Cleanup Settings
                            </div>
                            <div class="card-body">
                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-torrent icon-blue"></i> Torrents</span>
                                    <span class="line"></span>
                                </div>
                                <?php ftxt('max_dead_torrent_time',$max_dead_torrent_time,'Mark Torrents Invisible After (days)','Torrent Last Action < X days','number','fas fa-calendar-times'); ?>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-arrow-up icon-green"></i> User Promotion</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('promote_gig_limit',$promote_gig_limit,'Promote Users GB Limit','0 = disabled','number','fas fa-database'); ?>
                                    <?php ftxt('promote_min_ratio',$promote_min_ratio,'Promote Users RATIO Limit','','text','fas fa-percent'); ?>
                                    <?php ftxt('promote_min_reg_days',$promote_min_reg_days,'Promote Users DAYS Limit','','number','fas fa-calendar'); ?>
                                    <?php ftxt('demote_min_ratio',$demote_min_ratio,'Demote Users RATIO Limit','','text','fas fa-percent'); ?>
                                    <?php ftxt('referrergift',$referrergift,'Referrer Gift (GB)','','number','fas fa-gift'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-exclamation-triangle icon-red"></i> User Warnings</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('leechwarn_min_ratio',$leechwarn_min_ratio,'Warn User MIN. Ratio','','text','fas fa-percent'); ?>
                                    <?php ftxt('leechwarn_gig_limit',$leechwarn_gig_limit,'Warn User GB Limit','','number','fas fa-database'); ?>
                                    <?php ftxt('leechwarn_length',$leechwarn_length,'Warning Length (weeks)','','number','fas fa-clock'); ?>
                                    <?php ftxt('leechwarn_remove_ratio',$leechwarn_remove_ratio,'Remove Warning Min. Ratio','','text','fas fa-percent'); ?>
                                    <?php ftxt('ban_user_limit',$ban_user_limit,'Ban Warned Users After X Warnings','','number','fas fa-ban'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── REGISTRATION ──────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="registration-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_registration" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-user-plus header-icon icon-green pulse"></i>
                                Registration Settings
                            </div>
                            <div class="card-body">
                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-user-plus icon-green"></i> Registration System</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php fsel('regtype',['invite'=>'Invite System','instant'=>'Instant Activation','verify'=>'Email Verification'],$regtype,'Registration Method','','fas fa-user-plus'); ?>
                                    <?php fsel('disableregs',['1'=>'Yes','0'=>'No'],$disableregs,'Disable Registrations','','fas fa-ban'); ?>
                                    <?php ftxt('maxusers',$maxusers,'Max Users (0 = unlimited)','','number','fas fa-users'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-user icon-blue"></i> Username Requirements</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('minnamelength',$minnamelength,'Min Username Length','','number','fas fa-arrow-down'); ?>
                                    <?php ftxt('maxnamelength',$maxnamelength,'Max Username Length','','number','fas fa-arrow-up'); ?>
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-ban label-icon icon-red"></i> Banned Usernames</label>
                                    <textarea class="form-control-modern" name="configoption[illegalusernames]" rows="3"><?= htmlspecialchars($illegalusernames) ?></textarea>
                                    <div class="form-text">One per line</div>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-key icon-blue"></i> Password Requirements</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('minpasswordlength',$minpasswordlength,'Min Password Length','','number','fas fa-arrow-down'); ?>
                                    <?php ftxt('maxpasswordlength',$maxpasswordlength,'Max Password Length','','number','fas fa-arrow-up'); ?>
                                    <?php fsel('requirecomplexpasswords',['1'=>'Required','0'=>'Not Required'],$requirecomplexpasswords,'Complex Passwords','','fas fa-shield-alt'); ?>
                                    <?php fsel('allowmultipleemails',['1'=>'Yes','0'=>'No'],$allowmultipleemails,'Allow Multiple Emails','Allow users to sign up with the same email more than once','fas fa-envelope'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-shield-alt icon-red"></i> Security & Bonuses</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('failedlogincount',$failedlogincount,'Max Failed Logins','0 = disabled','number','fas fa-user-lock'); ?>
                                    <?php fsel('failedlogintext',['1'=>'Yes','0'=>'No'],$failedlogintext,'Display Failed Login Count','','fas fa-text'); ?>
                                    <?php fsel('username_method',['0'=>'Username Only','1'=>'Email Only','2'=>'Both'],$username_method,'Allowed Login Methods','','fas fa-user'); ?>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-users-cog label-icon icon-purple"></i> Default Usergroup</label>
                                        <select class="form-select-modern" name="configoption[_d_usergroup]">
                                            <?php $gq = $db->sql_query_prepared('SELECT gid, title, namestyle FROM usergroups');
                                            while ($gq && ($g = $db->fetch_array($gq))) {
                                                $sel = $_d_usergroup == $g['gid'] ? ' selected' : '';
                                                echo "<option value=\"{$g['gid']}\"{$sel}>" . htmlspecialchars(strip_tags($g['title'])) . "</option>";
                                            } ?>
                                        </select>
                                    </div>
                                    <?php ftxt('invite_count',$invite_count,'Initial Invites','0 = disabled','number','fas fa-envelope'); ?>
                                    <?php ftxt('autogigsignup',$autogigsignup,'Auto GB on Signup','0 = disabled','number','fas fa-database'); ?>
                                    <?php ftxt('autosbsignup',$autosbsignup,'Auto SeedBonus','0 = disabled','number','fas fa-seedling'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-stopwatch icon-orange"></i> Signup Flood Control</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('betweenregstime',(string)($settings['betweenregstime']??'24'),'Time Window (hours)','Period over which registrations from the same IP are counted','number','fas fa-hourglass-half'); ?>
                                    <?php ftxt('maxregsbetweentime',(string)($settings['maxregsbetweentime']??'2'),'Max Registrations in Window','Registrations from the same IP allowed within the time window','number','fas fa-user-clock'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── FORUM / LEGACY ────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="forum-legacy-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_forum_legacy" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-comments header-icon icon-teal"></i>
                                Forum / Legacy Settings
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-circle-info me-2"></i>
                                    Inherited from the original MyBB base. Most trackers never touch these,
                                    but they're exposed here so nothing has to be edited by hand in
                                    <code>include/settings.php</code>.
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-sliders icon-blue"></i> General</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-language label-icon icon-blue"></i> Default Language</label>
                                        <select class="form-select-modern" name="configoption[defaultlanguage]">
                                            <?php
                                            $curLang = $settings['defaultlanguage'] ?? 'english';
                                            foreach (glob(INC_PATH . '/languages/*', GLOB_ONLYDIR) as $langDir) {
                                                $langName = basename($langDir);
                                                $sel = $langName === $curLang ? ' selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($langName) . "\"{$sel}>" . htmlspecialchars(ucfirst($langName)) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <?php fsel('shoutboxcharset',['UTF-8'=>'UTF-8','ISO-8859-1'=>'ISO-8859-1'],$settings['shoutboxcharset']??'UTF-8','Shoutbox Charset','','fas fa-comment-dots'); ?>
                                    <?php fswitch('enablepms', ($settings['enablepms']??'1') === '1', 'Enable Private Messages', '', 'fas fa-envelope-open-text', '1', '0'); ?>
                                    <?php fswitch('usezip', ($settings['usezip']??'no') === 'yes', 'Use ZIP Compression for Uploads', '', 'fas fa-file-zipper'); ?>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('uploadspath',(string)($settings['uploadspath']??'./uploads'),'Uploads Path','Relative or absolute path on disk','text','fas fa-folder-open'); ?>
                                    <?php ftxt('loadlimit',(string)($settings['loadlimit']??''),'Server Load Limit','Blank = disabled. Rejects requests above this load average','text','fas fa-gauge-high'); ?>
                                </div>

                                <div class="section-divider">
                                    <span class="line"></span>
                                    <span class="label"><i class="fas fa-comments icon-teal"></i> Thread & Forum Display</span>
                                    <span class="line"></span>
                                </div>

                                <div class="settings-grid">
                                    <?php fswitch('browsingthisthread', ($settings['browsingthisthread']??'1') === '1', 'Show "Users Browsing This Thread"', '', 'fas fa-eye', '1', '0'); ?>
                                    <?php fswitch('delayedthreadviews', ($settings['delayedthreadviews']??'1') === '1', 'Delay Thread View Counting', '', 'fas fa-clock-rotate-left', '1', '0'); ?>
                                    <?php fswitch('showforumpagesbreadcrumb', ($settings['showforumpagesbreadcrumb']??'1') === '1', 'Show Forum Pages in Breadcrumb', '', 'fas fa-route', '1', '0'); ?>
                                    <?php fswitch('showownunapproved', ($settings['showownunapproved']??'1') === '1', 'Show Own Unapproved Posts/Threads', '', 'fas fa-user-check', '1', '0'); ?>
                                    <?php ftxt('threadreadcut',(string)($settings['threadreadcut']??'7'),'Thread Read Cutoff (days)','Threads older than this are always shown as read','number','fas fa-calendar-check'); ?>
                                    <?php ftxt('ts_perpage',(string)($settings['ts_perpage']??'20'),'Torrents Per Page','','number','fas fa-list'); ?>
                                    <?php ftxt('f_postsperpage',(string)($settings['f_postsperpage']??'10'),'Forum Posts Per Page','','number','fas fa-align-left'); ?>
                                    <?php ftxt('f_threadsperpage',(string)($settings['f_threadsperpage']??'20'),'Forum Threads Per Page','','number','fas fa-list-ol'); ?>
                                </div>

                                <div class="settings-grid">
                                    <?php ftxt('userpppoptions',(string)($settings['userpppoptions']??'5,10,15,20,25,30,40,50'),'User "Posts Per Page" Choices','Comma-separated','text','fas fa-table-list'); ?>
                                    <?php ftxt('usertppoptions',(string)($settings['usertppoptions']??'10,15,20,25,30,40,50'),'User "Threads Per Page" Choices','Comma-separated','text','fas fa-table-list'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── STAFF TEAM ────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="staff-team">
                    <div class="settings-card">
                        <div class="card-header">
                            <i class="fas fa-user-shield header-icon icon-purple"></i>
                            Staff Team Management
                            <span class="badge bg-primary ms-auto"><?= count($staffarray) ?> members</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> Type a username — ID auto-fills if found. Use <strong>Find User</strong> for manual search.
                            </div>
                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <datalist id="staffNames">
                                    <?php foreach ($availableStaff as $st): ?>
                                    <option value="<?= htmlspecialchars($st['username']) ?>"><?= htmlspecialchars($st['username']) ?> (<?= htmlspecialchars($st['group'] ?? '') ?>)</option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="table-responsive">
                                    <table class="table staff-table">
                                        <thead>
                                            <tr>
                                                <th width="5%"><i class="fas fa-hashtag"></i></th>
                                                <th width="45%"><i class="fas fa-user"></i> Username</th>
                                                <th width="45%"><i class="fas fa-id-card"></i> User ID</th>
                                                <th width="5%"><i class="fas fa-cog"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($staffarray)): ?>
                                                <?php foreach ($staffarray as $i => $st): ?>
                                                <tr>
                                                    <td class="text-center fw-bold"><?= $i + 1 ?></td>
                                                    <td><input type="text" name="staffnames[]" value="<?= htmlspecialchars($st['name']) ?>" class="form-control-modern" list="staffNames" placeholder="Username"></td>
                                                    <td><input type="text" name="staffids[]"   value="<?= htmlspecialchars($st['id'])   ?>" class="form-control-modern" placeholder="User ID"></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').querySelectorAll('input').forEach(i=>i.value='')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted py-4">No staff members found. Add below.</td></tr>
                                            <?php endif; ?>
                                            <?php for ($i = 0; $i < 3; $i++): ?>
                                            <tr class="row-new">
                                                <td class="text-center"><span class="badge bg-success rounded-pill"><i class="fas fa-plus"></i> New</span></td>
                                                <td><input type="text" name="staffnames[]" class="form-control-modern" list="staffNames" placeholder="Enter username"></td>
                                                <td><input type="text" name="staffids[]"   class="form-control-modern" placeholder="Enter user ID"></td>
                                                <td></td>
                                            </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-light rounded">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                onclick="window.open('<?= htmlspecialchars($BASEURL) ?>/users.php#searchuser','finduser','toolbar=no,scrollbars=yes,width=800,height=600')">
                                            <i class="fas fa-search me-1"></i> Find User
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="this.closest('tbody').querySelectorAll('.row-new input').forEach(i=>i.value='')">
                                            <i class="fas fa-undo me-1"></i> Clear New
                                        </button>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="text-muted small"><i class="fas fa-database me-1"></i><?= count($availableStaff) ?> available staff</span>
                                        <button type="submit" name="save_staff" class="btn btn-primary px-4">
                                            <i class="fas fa-save me-1"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ── FREELEECH ─────────────────────────────────────────────────── -->
                <div class="tab-pane fade" id="freeleech-settings">
                    <form method="post" class="settings-form">
                        <input type="hidden" name="save_freeleech" value="1">
                        <div class="settings-card">
                            <div class="card-header">
                                <i class="fas fa-gift header-icon icon-red bounce"></i>
                                FreeLeech Settings
                                <span class="badge bg-danger ms-auto">Promo</span>
                            </div>
                            <div class="card-body">
                                <div class="form-group-modern">
                                    <label><i class="fas fa-arrow-circle-right label-icon icon-blue"></i> Select System Type</label>
                                    <select class="form-select-modern" name="configoption[system]">
                                        <option value="freeleech"    <?= ($__FLSTYPE??'')==='freeleech'   ?'selected':''?>>🎁 Free Leech</option>
                                        <option value="silverleech"  <?= ($__FLSTYPE??'')==='silverleech' ?'selected':''?>>🥈 Silver Leech</option>
                                        <option value="doubleupload" <?= ($__FLSTYPE??'')==='doubleupload'?'selected':''?>>2️⃣ Double Upload</option>
                                    </select>
                                </div>
                                <div class="settings-grid">
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-calendar-plus label-icon icon-green"></i> Begin Date</label>
                                        <div class="input-group">
                                            <input type="text" id="startPicker" class="form-control-modern" name="configoption[start]"
                                                   value="<?= ($__F_START??'')!=='0000-00-00 00:00:00' ? htmlspecialchars($__F_START??'') : '' ?>"
                                                   placeholder="YYYY-MM-DD HH:MM:SS">
                                            <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        </div>
                                    </div>
                                    <div class="form-group-modern">
                                        <label><i class="fas fa-calendar-minus label-icon icon-red"></i> End Date</label>
                                        <div class="input-group">
                                            <input type="text" id="endPicker" class="form-control-modern" name="configoption[end]"
                                                   value="<?= ($__F_END??'')!=='0000-00-00 00:00:00' ? htmlspecialchars($__F_END??'') : '' ?>"
                                                   placeholder="YYYY-MM-DD HH:MM:SS">
                                            <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> Freeleech affects ALL torrents during the specified period.
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

            </div><!-- /.tab-content -->
        </main>
    </div>
</div>

<!-- Flatpickr -->
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/airbnb.css">
<script src="<?= $BASEURL ?>/admin/scripts/flatpickr.js"></script>

<script>
const staffData = <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'username' => $s['username']], $availableStaff)) ?>;
</script>
<script src="<?= $BASEURL ?>/admin/scripts/settings-page.js"></script>

<script src="<?= $BASEURL ?>/admin/scripts/admin-settings.js"></script>

<?php stdfoot(); ?>