<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('B_VERSION', '6.7.0');

// This page's own file name: redirects and form actions use it, so the file can be renamed freely
define('AGS_SELF', basename(__FILE__));

$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';

$lang->load('managesettings');

require_once INC_PATH . '/functions_mkprettytime.php';
require TSDIR . '/cache/freeleech.php';

if ((int)($usergroups['cansettingspanel'] ?? 0) !== 1) {
    stdhead();
    error_no_permission(true);
    exit();
}

// ============================================================
//  ЛОГИРОВАНИЕ ИЗМЕНЕНИЙ В SITELOG
// ============================================================
function log_settings_change(int $user_id, string $username, string $action, string $setting_name, ?string $old_value = null, ?string $new_value = null): void {
    global $db;

    // Пароли никогда не пишем в лог открытым текстом
    if (str_contains(strtolower($setting_name), 'pass')) {
        $old_value = $old_value !== null ? '***' : null;
        $new_value = $new_value !== null ? '***' : null;
    }

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
    ", [$user_id, $ip_binary, time(), $txt, 'settings', 1]);
}

// ── История изменений из sitelog ──────────────────────────────────────────
function get_settings_history(int $limit = 50, int $offset = 0, ?string $search = null, ?string $user = null): array {
    global $db;

    $params = ['settings'];
    $where  = ["category = ?"];

    if ($search !== null && $search !== '') {
        $where[]  = "txt LIKE ?";
        $params[] = "%{$search}%";
    }
    if ($user !== null && $user !== '') {
        $where[]  = "txt LIKE ?";
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
        if (!empty($row['ipaddress'])) {
            $ip = inet_ntop($row['ipaddress']);
            $row['ipaddress'] = $ip !== false ? $ip : '0.0.0.0';
        } else {
            $row['ipaddress'] = '0.0.0.0';
        }
        $logs[] = $row;
    }

    // Все параметры, включая category (раньше срезался первый - COUNT не совпадал с плейсхолдерами)
    $count_query = $db->sql_query_prepared("SELECT COUNT(*) AS total FROM sitelog {$where_clause}", $params);

    $total = 0;
    if ($count_query) {
        $row   = $db->fetch_array($count_query);
        $total = (int)$row['total'];
    }

    return ['logs' => $logs, 'total' => $total];
}

function cleanup_old_logs(int $days = 90): int {
    global $db;
    $cutoff = time() - ($days * 86400);
    $query  = $db->sql_query_prepared("DELETE FROM sitelog WHERE category = 'settings' AND added < ?", [$cutoff]);
    return $db->affected_rows($query);
}

// ============================================================
//  СТАТИСТИКА ДЛЯ ДАШБОРДА
// ============================================================
function get_dashboard_stats(): array {
    global $db;

    $stats    = [];
    $today    = date('Y-m-d');
    $week_ago = time() - 604800;

    $query = $db->sql_query_prepared(
        "SELECT
            SUM(enabled='yes') AS total,
            SUM(lastactive > ?) AS active,
            SUM(DATE(FROM_UNIXTIME(added)) = ?) AS today,
            SUM(added > ?) AS week
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

    $query = $db->sql_query_prepared(
        "SELECT
            SUM(banned='no') AS total,
            SUM(seeders > 0 AND banned='no') AS active,
            SUM(DATE(FROM_UNIXTIME(added)) = ?) AS today,
            SUM(added > ?) AS week,
            SUM(seeders = 0 AND banned='no' AND visible='yes') AS dead,
            SUM(CASE WHEN banned='no' THEN size ELSE 0 END) AS total_size,
            COUNT(DISTINCT CASE WHEN banned='no' THEN category END) AS categories_active
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

    $query = $db->sql_query_prepared("SELECT SUM(seeder='yes') AS seeders, SUM(seeder='no') AS leechers FROM peers");
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['seeders_total']  = (int)$row['seeders'];
        $stats['leechers_total'] = (int)$row['leechers'];
    } else {
        $stats['seeders_total']  = 0;
        $stats['leechers_total'] = 0;
    }
    $stats['peers_total'] = $stats['seeders_total'] + $stats['leechers_total'];

    $query = $db->sql_query_prepared(
        "SELECT
            COUNT(DISTINCT userid) AS unique_users,
            COUNT(DISTINCT CASE WHEN last_action > ? THEN userid END) AS active_peers
         FROM peers",
        [time() - 300]
    );
    if ($query) {
        $row = $db->fetch_array($query);
        $stats['unique_peers'] = (int)$row['unique_users'];
        $stats['active_peers'] = (int)$row['active_peers'];
    }

    // most_snatched / most_active / most_seeded / top_seeders удалены:
    // нигде не выводились, а два из них - полный JOIN torrents×peers с GROUP BY на каждый заход.

    return $stats;
}

// ── Критические уведомления ──────────────────────────────────────────────
function check_critical_settings(): array {
    global $settings, $lang;
    $alerts = [];

    if (($settings['SITEONLINE'] ?? 'yes') === 'no') {
        $alerts[] = [
            'type'    => 'warning',
            'icon'    => 'fa-power-off',
            'title'   => $lang->managesettings['alert_offline_title'],
            'message' => $lang->managesettings['alert_offline_msg'],
            'action'  => '<a href="#main-settings" data-ag-tab="main-settings">' . $lang->managesettings['alert_offline_action'] . '</a>',
        ];
    }
    if (($settings['disableregs'] ?? '0') === '1') {
        $alerts[] = [
            'type'    => 'info',
            'icon'    => 'fa-user-slash',
            'title'   => $lang->managesettings['alert_regs_title'],
            'message' => $lang->managesettings['alert_regs_msg'],
            'action'  => '<a href="#registration-settings" data-ag-tab="registration-settings">' . $lang->managesettings['alert_regs_action'] . '</a>',
        ];
    }
    if (empty($settings['mysql_host'] ?? '')) {
        $alerts[] = [
            'type'    => 'danger',
            'icon'    => 'fa-database',
            'title'   => $lang->managesettings['alert_announce_title'],
            'message' => $lang->managesettings['alert_announce_msg'],
            'action'  => '<a href="#announce-settings" data-ag-tab="announce-settings">' . $lang->managesettings['alert_announce_action'] . '</a>',
        ];
    }
    $free_space = disk_free_space('/');
    if ($free_space !== false && $free_space < 1073741824) {
        $alerts[] = [
            'type'    => 'danger',
            'icon'    => 'fa-hard-drive',
            'title'   => $lang->managesettings['alert_disk_title'],
            'message' => $lang->managesettings['alert_disk_msg'],
            'action'  => '',
        ];
    }
    $backup_dir = TSDIR . '/admin/backup';
    if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
        $alerts[] = [
            'type'    => 'warning',
            'icon'    => 'fa-triangle-exclamation',
            'title'   => $lang->managesettings['alert_backup_title'],
            'message' => $lang->managesettings['alert_backup_msg'],
            'action'  => '',
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
    global $lang;

    echo '<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100" aria-live="polite" aria-atomic="true">';
    foreach ($_SESSION['flash'] as $flash) {
        [$cls, $icon, $title] = match($flash['type']) {
            'success'        => ['text-bg-success', 'fa-circle-check', $lang->managesettings['toast_saved']],
            'error','danger' => ['text-bg-danger',  'fa-circle-xmark', $lang->managesettings['toast_error']],
            'warning'        => ['text-bg-warning', 'fa-triangle-exclamation', $lang->managesettings['toast_warning']],
            default          => ['text-bg-info',    'fa-circle-info', $lang->managesettings['toast_info']],
        };
        $msg   = htmlspecialchars($flash['message']);
        $now   = htmlspecialchars((string)$lang->managesettings['toast_now']);
        $close = htmlspecialchars((string)$lang->managesettings['toast_close']);
        echo "<div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header {$cls}'>
                  <i class='fa-solid {$icon} me-2'></i>
                  <strong class='me-auto'>{$title}</strong><small>{$now}</small>
                  <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='{$close}'></button>
                </div>
                <div class='toast-body'>{$msg}</div>
              </div>";
    }
    echo '</div>
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
        echo "<meta http-equiv='refresh' content='0; url=" . htmlspecialchars($url) . "'>";
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

    // var_export(), not addslashes(): inside '…' addslashes turns " into \" literally,
    // so a password with a double quote was written wrong and announce lost the DB
    $c  = "<?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!\n";
    $c .= "if(!defined('IN_ANNOUNCE')) die('Hacking attempt!');\n\n";
    foreach ($keys as $k) {
        $c .= "\${$k} = " . var_export((string)($settings[$k] ?? ''), true) . ";\n";
    }
    foreach ([
        'BASEURL'             => $settings['BASEURL'] ?? '',
        'SITENAME'            => $settings['SITENAME'] ?? '',
        'privatetrackerpatch' => $settings['privatetrackerpatch'] ?? 'no',
        'gzipcompress'        => $settings['gzipcompress'] ?? 'no',
        'charset'             => $settings['charset'] ?? 'UTF-8',
        'aggressivecheckip'   => $settings['aggressivecheckip'] ?? 'no',
        'snatchmod'           => $settings['snatchmod'] ?? 'yes',
        'bdayreward'          => $settings['bdayreward'] ?? 'yes',
        'bdayrewardtype'      => $settings['bdayrewardtype'] ?? 'freeleech',
    ] as $k => $v) {
        $c .= "\${$k} = " . var_export((string)$v, true) . ";\n";
    }
    $c .= "?>";
    return ags_write_file(INC_PATH . '/config_announce.php', $c);
}

/**
 * Write a PHP config file atomically: temp file + rename. announce.php includes
 * config_announce.php on every request; a plain write could be read half-written.
 */
function ags_write_file(string $path, string $content): bool {
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        // Windows can refuse to replace a file that is open right now: fall back to a direct write
        @unlink($tmp);
        return file_put_contents($path, $content, LOCK_EX) !== false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return true;
}

function save_to_settings(array $data): void {
    global $db;
    // Требует UNIQUE-индекс на settings.name (см. migration_settings_unique.sql)
    foreach ($data as $name => $value) {
        $db->sql_query_prepared(
            "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?",
            [$name, $value, $value]
        );
    }
    rebuild_settings();
}

/** Несколько настроек одним запросом - для логирования старых значений. */
function get_settings_values(array $keys): array {
    global $db;
    if (empty($keys)) {
        return [];
    }
    $ph     = implode(',', array_fill(0, count($keys), '?'));
    $check  = $db->sql_query_prepared("SELECT name, value FROM settings WHERE name IN ({$ph})", $keys);
    $values = [];
    while ($check && ($row = $db->fetch_array($check))) {
        $values[$row['name']] = $row['value'];
    }
    return $values;
}

/** Сохранить набор ключей + записать в лог каждое реальное изменение. */
function save_and_log(array $data, array $old_values): void {
    global $CURUSER;
    if (empty($data)) {
        return;
    }
    save_to_settings($data);
    foreach ($data as $key => $new_value) {
        $old_value = $old_values[$key] ?? null;
        if ($old_value !== $new_value) {
            log_settings_change((int)$CURUSER['id'], (string)$CURUSER['username'], 'update', (string)$key, $old_value, (string)$new_value);
        }
    }
}

// ============================================================
//  UI-ХЕЛПЕРЫ (Font Awesome 6, fa-solid)
// ============================================================
// ── Default hints ────────────────────────────────────────────────────────────
// Used by ftxt / fsel / fswitch when the call passes no tip of its own, so every
// field gets a short explanation without touching each call. A tip passed in the
// call always wins.
// Default hints live in the language file as hint_<setting name> (non [a-z0-9_] stripped:
// 'announce_urls[]' -> hint_announce_urls). A tip passed in the call always wins.

function ags_tip(string $name, string $tip): string {
    global $lang;
    if ($tip !== '') {
        return $tip;
    }
    return (string)($lang->managesettings['hint_' . preg_replace('/[^A-Za-z0-9_]/', '', $name)] ?? '');
}

/** Language string with {1}, {2}… placeholders filled in ($lang->load() turns them into %1$s — both handled). */
function ags_fmt(string $str, string|int|float ...$args): string {
    $map = [];
    foreach ($args as $i => $a) {
        $n = $i + 1;
        $map['{' . $n . '}']   = (string)$a;
        $map['%' . $n . '$s'] = (string)$a;
    }
    return strtr($str, $map);
}

function ags_icon(string $icon): string {
    // 'fa-code' → 'fa-solid fa-code'; полная строка ('fa-regular fa-clock') - как есть
    return str_contains($icon, ' ') ? $icon : 'fa-solid ' . $icon;
}

function ags_id(string $name): string {
    return 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
}

function ags_hint(string $tip): string {
    return $tip !== '' ? '<div class="ag-hint"><i class="fa-solid fa-circle-info"></i><span>' . htmlspecialchars($tip) . '</span></div>' : '';
}

function fsel(string $n, array $o, string $cur, string $lbl, string $tip = '', string $icon = 'fa-list'): void {
    $tip = ags_tip($n, $tip);
    $id = ags_id($n);
    echo '<div class="ag-field"><label class="ag-label" for="' . $id . '"><i class="' . ags_icon($icon) . '"></i>' . htmlspecialchars($lbl) . '</label>';
    echo '<select class="form-select ag-input" id="' . $id . '" name="configoption[' . htmlspecialchars($n) . ']">';
    foreach ($o as $v => $t) {
        echo '<option value="' . htmlspecialchars((string)$v) . '"' . ($cur === (string)$v ? ' selected' : '') . '>' . htmlspecialchars($t) . '</option>';
    }
    echo '</select>' . ags_hint($tip) . '</div>';
}

// Скрытое поле перед чекбоксом: если чекбокс не отмечен, уходит только offValue;
// если отмечен - уходят оба, но чекбокс идёт последним и побеждает (onValue).
function fswitch(string $n, bool $checked, string $lbl, string $tip = '', string $icon = 'fa-toggle-on', string $onValue = 'yes', string $offValue = 'no'): void {
    global $lang;
    $tip = ags_tip($n, $tip);
    $id = 'sw_' . htmlspecialchars($n);
    echo '<div class="ag-field">'
       . '<label class="ag-switch" for="' . $id . '">'
       . '<span class="ag-switch-icon"><i class="' . ags_icon($icon) . '"></i></span>'
       . '<span class="ag-switch-text"><span class="ag-switch-label">' . htmlspecialchars($lbl) . '</span>'
       . ($tip !== '' ? '<span class="ag-switch-tip">' . htmlspecialchars($tip) . '</span>' : '')
       . '</span>'
       . '<span class="form-check form-switch ag-switch-ctrl">'
       . '<input type="hidden" name="configoption[' . htmlspecialchars($n) . ']" value="' . htmlspecialchars($offValue) . '">'
       . '<input type="checkbox" class="form-check-input" role="switch" id="' . $id . '" name="configoption[' . htmlspecialchars($n) . ']" value="' . htmlspecialchars($onValue) . '"' . ($checked ? ' checked' : '') . '>'
       . '<span class="ag-state"><span class="on"><i class="fa-solid fa-check"></i> ' . htmlspecialchars((string)$lang->managesettings['switch_on']) . '</span><span class="off">' . htmlspecialchars((string)$lang->managesettings['switch_off']) . '</span></span>'
       . '</span>'
       . '</label></div>';
}

function ftxt(string $n, string $v, string $lbl, string $tip = '', string $type = 'text', string $icon = 'fa-pen'): void {
    $tip = ags_tip($n, $tip);
    $id = ags_id($n);
    echo '<div class="ag-field"><label class="ag-label" for="' . $id . '"><i class="' . ags_icon($icon) . '"></i>' . htmlspecialchars($lbl) . '</label>';
    echo '<input type="' . $type . '" class="form-control ag-input" id="' . $id . '" name="configoption[' . htmlspecialchars($n) . ']" value="' . htmlspecialchars($v) . '">';
    echo ags_hint($tip) . '</div>';
}

function fsec(string $icon, string $title, string $hint = ''): void {
    echo '<div class="ag-sec"><span class="ag-sec-icon"><i class="' . ags_icon($icon) . '"></i></span>'
       . '<div><h3>' . htmlspecialchars($title) . '</h3>' . ($hint !== '' ? '<p>' . htmlspecialchars($hint) . '</p>' : '') . '</div></div>';
}

function pane_head(string $icon, string $title, string $sub, string $tag = '', string $tagIcon = 'fa-tag'): void {
    echo '<div class="ag-pane-head"><span class="ag-sq"><i class="' . ags_icon($icon) . '"></i></span>'
       . '<div class="ag-pane-title"><h2>' . htmlspecialchars($title) . '</h2><p>' . htmlspecialchars($sub) . '</p></div>'
       . ($tag !== '' ? '<span class="ag-tag"><i class="' . ags_icon($tagIcon) . '"></i>' . htmlspecialchars($tag) . '</span>' : '')
       . '</div>';
}

function savebar(string $label = '', string $name = '', string $note = ''): void {
    global $lang;
    if ($label === '') $label = (string)$lang->managesettings['save_changes'];
    if ($note === '')  $note  = (string)$lang->managesettings['save_note'];
    echo '<div class="ag-savebar"><span class="ag-savebar-note"><i class="fa-solid fa-clock-rotate-left"></i>' . htmlspecialchars($note) . '</span>'
       . '<button type="submit" class="btn btn-primary ag-pill"' . ($name !== '' ? ' name="' . htmlspecialchars($name) . '" value="1"' : '') . '>'
       . '<i class="fa-solid fa-floppy-disk me-2"></i>' . htmlspecialchars($label) . '</button></div>';
}

function nav_item(string $href, string $icon, string $tone, string $label, string $extra = '', bool $tab = true, bool $active = false): void {
    echo '<li><a class="nav-link ' . $tone . ($active ? ' active' : '') . '" href="' . $href . '"' . ($tab ? ' data-ag-pane role="tab" aria-selected="' . ($active ? 'true' : 'false') . '"' : '') . '>'
       . '<span class="ag-ni"><i class="' . ags_icon($icon) . '"></i></span>'
       . '<span class="ag-nl">' . $label . '</span>'
       . ($extra !== '' ? $extra : ($tab ? '' : '<i class="fa-solid fa-arrow-up-right-from-square ag-ext"></i>'))
       . '</a></li>';
}

function csrf_field(): string {
    global $ags_post_key;
    return '<input type="hidden" name="my_post_key" value="' . htmlspecialchars((string)$ags_post_key) . '">';
}

// ── Load settings ──────────────────────────────────────────────────────────────
$settings = [];
$q = $db->sql_query_prepared("SELECT name, value FROM settings");
while ($q && ($r = $db->fetch_array($q))) $settings[$r['name']] = $r['value'];
$announce_url = $settings['announce_urls[]'] ?? '';

ob_start();

// ── CSRF ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
    flash_message($lang->managesettings['flash_csrf'], "danger");
    admin_redirect(AGS_SELF);
}

// ── POST handlers (PRG: каждый заканчивается redirect) ─────────────────────────
match(true) {

    isset($_POST['save_kps']) => (function(): void {
        global $lang;
        $keys = ['bonus','kpsseed','kpsupload','kpscomment','kpsthanks','kpsrate','kpspoll',
                 'kpsmaxpoint','kpsinvite','kpstitle','kpsvip','kpsgift','kpswarning','kpsratiofix',
                 'bdayreward','bdayrewardtype'];
        save_and_log(array_intersect_key($_POST['configoption'] ?? [], array_flip($keys)), get_settings_values($keys));
        flash_message($lang->managesettings['flash_kps_saved'], "success");
        admin_redirect(AGS_SELF . "#kps-settings");
    })(),

    isset($_POST['save_user_management']) => (function(): void {
        global $lang;
        $keys = ['max_dead_torrent_time','promote_gig_limit','promote_min_ratio',
                 'promote_min_reg_days','demote_min_ratio','referrergift','leechwarn_min_ratio',
                 'leechwarn_gig_limit','leechwarn_length','leechwarn_remove_ratio','ban_user_limit',
                 'hr_enabled','hr_min_seed_hours','hr_start_date','hr_skip_groups',
                 'hr_min_ratio',
                 'iu_maxdays','iu_deleteafter','iu_protect_groups'];
        $raw = $_POST['configoption'] ?? [];

        // Hit & Run: normalise before saving (weekly_cleanups.php and admin/hit_and_run.php read these)
        if (array_key_exists('hr_enabled', $raw)) {
            $raw['hr_enabled'] = ($raw['hr_enabled'] === 'yes') ? 'yes' : 'no';
        }
        if (array_key_exists('hr_min_seed_hours', $raw)) {
            $raw['hr_min_seed_hours'] = (string)max(0, (int)$raw['hr_min_seed_hours']);
        }
        if (array_key_exists('hr_start_date', $raw)) {
            $d = trim((string)$raw['hr_start_date']);
            $raw['hr_start_date'] = (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) ? $d : '';
        }
        // Staff tool: ratio threshold (accepts "0,8" too) and rows per page
        if (array_key_exists('hr_min_ratio', $raw)) {
            $raw['hr_min_ratio'] = number_format(max(0.0, (float)str_replace(',', '.', (string)$raw['hr_min_ratio'])), 2, '.', '');
        }
        
        // Мульти-селект групп приходит массивом id - сворачиваем в CSV (скрытый пустой input гарантирует ключ)
        foreach (['hr_skip_groups', 'iu_protect_groups'] as $multi) {
            if (isset($raw[$multi]) && is_array($raw[$multi])) {
                $raw[$multi] = implode(',', array_filter(array_map('intval', $raw[$multi])));
            }
        }

        // Inactive users (admin/inactiveusers.php): whole days, at least 1
        foreach (['iu_maxdays', 'iu_deleteafter'] as $days) {
            if (array_key_exists($days, $raw)) {
                $raw[$days] = (string)max(1, (int)$raw[$days]);
            }
        }

        save_and_log(array_intersect_key($raw, array_flip($keys)), get_settings_values($keys));
        flash_message($lang->managesettings['flash_cleanup_saved'], "success");
        admin_redirect(AGS_SELF . "#user-management-settings");
    })(),

    isset($_POST['save_registration']) => (function(): void {
        global $lang;
        $keys = ['regtype','minnamelength','maxnamelength',
                 'minpasswordlength','maxpasswordlength','requirecomplexpasswords','failedlogincount',
                 'failedlogintext','disableregs','maxusers',
                 '_d_usergroup','invite_count','autogigsignup','autosbsignup',
                 'betweenregstime','maxregsbetweentime'];
        save_and_log(array_intersect_key($_POST['configoption'] ?? [], array_flip($keys)), get_settings_values($keys));
        flash_message($lang->managesettings['flash_reg_saved'], "success");
        admin_redirect(AGS_SELF . "#registration-settings");
    })(),

    isset($_POST['save_forum_legacy']) => (function(): void {
        global $lang;
        $keys = ['defaultlanguage','enablepms','browsingthisthread','delayedthreadviews',
                 'showforumpagesbreadcrumb','showownunapproved','threadreadcut','ts_perpage',
                 'f_postsperpage','f_threadsperpage','userpppoptions','usertppoptions',
                 'loadlimit','shoutboxcharset','uploadspath','usezip',
                 'postmergemins','postmergesep','postmergefignore','postmergeuignore',
                 'minmessagelength','maxmessagelength','mycodemessagelength'];
        $raw = $_POST['configoption'] ?? [];

        // Мульти-селекты приходят массивом id - сворачиваем в CSV.
        // Скрытый пустой input перед селектом гарантирует наличие ключа, даже если сняты все галочки.
        foreach (['postmergefignore', 'postmergeuignore'] as $multi) {
            if (isset($raw[$multi]) && is_array($raw[$multi])) {
                $raw[$multi] = implode(',', array_filter(array_map('intval', $raw[$multi])));
            }
        }

        save_and_log(array_intersect_key($raw, array_flip($keys)), get_settings_values($keys));
        flash_message($lang->managesettings['flash_forum_saved'], "success");
        admin_redirect(AGS_SELF . "#forum-legacy-settings");
    })(),

    isset($_POST['save_announce']) => (function(): void {
        global $lang;
        $keys = ['nc','announce_wait','announce_interval',
                 'max_rate','bannedclientdetect','allowed_clients',
                 'checkconnectable','checkip','mysql_host','mysql_user','mysql_pass','mysql_db'];
        $data = array_intersect_key($_POST['configoption'] ?? [], array_flip($keys));

        // Пустое поле пароля = "оставить текущий" (раньше затирало пароль announce-БД)
        if (($data['mysql_pass'] ?? null) === '') {
            unset($data['mysql_pass']);
        }

        if (!empty($data)) {
            save_and_log($data, get_settings_values($keys));
            rebuild_announce_settings();
        }
        flash_message($lang->managesettings['flash_announce_saved'], "success");
        admin_redirect(AGS_SELF . "#announce-settings");
    })(),

    isset($_POST['save_freeleech']) => (function(): void {
        global $lang;
        global $CURUSER, $__FLSTYPE, $__F_START, $__F_END;
        $start   = (string)($_POST['configoption']['start']  ?? '');
        $end     = (string)($_POST['configoption']['end']    ?? '');
        $flstype = (string)($_POST['configoption']['system'] ?? 'freeleech');
        if (!in_array($flstype, ['freeleech', 'silverleech', 'doubleupload'], true)) {
            $flstype = 'freeleech';
        }

        $old_type  = (string)($__FLSTYPE ?? '');
        $old_start = (string)($__F_START ?? '');
        $old_end   = (string)($__F_END   ?? '');

        $c  = "<?php\n/** Cache: FreeLeech | Generated: " . gmdate('r') . " */\n";
        $c .= "\$__FLSTYPE = " . var_export($flstype, true) . ";\n";
        $c .= "\$__F_START = " . var_export($start, true) . ";\n";
        $c .= "\$__F_END   = " . var_export($end, true) . ";\n?>";

        if (ags_write_file(TSDIR . '/cache/freeleech.php', $c)) {
            log_settings_change(
                (int)$CURUSER['id'],
                (string)$CURUSER['username'],
                'update',
                'freeleech_settings',
                "Type: {$old_type}, Start: {$old_start}, End: {$old_end}",
                "Type: {$flstype}, Start: {$start}, End: {$end}"
            );
            flash_message($lang->managesettings['flash_fl_saved'], "success");
            admin_redirect(AGS_SELF . "?saved=freeleech#freeleech-settings");
        }
        flash_message($lang->managesettings['flash_fl_write_error'], "danger");
        admin_redirect(AGS_SELF . "#freeleech-settings");
    })(),

    isset($_POST['save_staff']) => (function() use ($db): void {
        global $lang;
        global $CURUSER;
        $valid = [];
        $q = $db->sql_query_prepared("SELECT u.id, u.username FROM users u LEFT JOIN usergroups g ON u.usergroup=g.gid WHERE u.enabled='yes' AND (g.cansettingspanel='1' OR g.issupermod='1' OR g.canstaffpanel='1')");
        while ($q && ($r = $db->fetch_array($q))) $valid[(string)$r['id']] = $r['username'];

        $entries = []; $errors = [];
        foreach (($_POST['staffids'] ?? []) as $i => $rawId) {
            $id   = trim((string)$rawId);
            $name = trim((string)($_POST['staffnames'][$i] ?? ''));
            if ($id === '' && $name === '') continue;
            if ($id !== '' && !ctype_digit($id))      { $errors[] = "{$name}:{$id} (" . $lang->managesettings['staff_err_id'] . ")"; continue; }
            if (!isset($valid[$id]))                   { $errors[] = "{$name}:{$id} (" . $lang->managesettings['staff_err_notallowed'] . ")"; continue; }
            if (strcasecmp($valid[$id], $name) !== 0) { $errors[] = "{$name}:{$id} (" . $lang->managesettings['staff_err_mismatch'] . ")"; continue; }
            $entries[] = "{$name}:{$id}";
        }

        $old_staff = file_exists(CONFIG_DIR . '/STAFFTEAM') ? (string)file_get_contents(CONFIG_DIR . '/STAFFTEAM') : 'empty';

        if (!empty($errors)) {
            flash_message($lang->managesettings['flash_errors'] . implode(', ', $errors), "danger");
        } elseif (file_put_contents(CONFIG_DIR . '/STAFFTEAM', implode(',', $entries), LOCK_EX) === false) {
            flash_message($lang->managesettings['flash_staff_write_error'], "danger");
        } else {
            log_settings_change((int)$CURUSER['id'], (string)$CURUSER['username'], 'update', 'staff_team', $old_staff, implode(',', $entries));
            flash_message($lang->managesettings['flash_staff_saved'], "success");
        }
        admin_redirect(AGS_SELF . "#staff-team");
    })(),

    $_SERVER['REQUEST_METHOD'] === 'POST' => (function() use ($db): void {
        global $lang;
        global $CURUSER;
        $opts   = $_POST['configoption'] ?? [];
        $ofType = $_POST['offline_mode_type'] ?? 'limited';
        $ofMins = (int)($_POST['offline_minutes_input'] ?? 30);

        // Пустое поле пароля = "оставить текущий"
        if (array_key_exists('smtp_pass', $opts) && $opts['smtp_pass'] === '') {
            unset($opts['smtp_pass']);
        }

        // name="configoption[announce_urls[]]" arrives as key 'announce_urls[' (PHP stops
        // at the first ']'), but the setting is stored as 'announce_urls[]'. Without this
        // the UPDATE matched no row and a new announce URL was silently not saved.
        if (array_key_exists('announce_urls[', $opts)) {
            $opts['announce_urls[]'] = $opts['announce_urls['];
            unset($opts['announce_urls[']);
        }

        // Only scalar values go to the settings table
        $opts = array_filter($opts, 'is_scalar');

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
            }
        } elseif (isset($opts['SITEONLINE'])) {
            // Only the Main tab sends SITEONLINE. Saving any other tab while the site is
            // offline used to clear offline_minutes too - and the maintenance timer was lost.
            $opts['offline_minutes'] = '';
            write_log("[MAINTENANCE] Site set to online");
        }

        $db->begin_transaction(hide_errors: true);
        try {
            foreach ($opts as $name => $value) {
                $value = (string)$value;
                // Upsert like save_to_settings(): a plain UPDATE silently did nothing for a
                // setting that has no row yet (and the change was still logged as saved)
                if (!$db->sql_query_prepared(
                    "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?",
                    [(string)$name, $value, $value], 1
                ))
                    throw new \Exception(ags_fmt((string)$lang->managesettings['err_update_failed'], (string)$name));

                $old_value = $old_values[$name] ?? null;
                if ($old_value !== $value) {
                    log_settings_change((int)$CURUSER['id'], (string)$CURUSER['username'], 'update', (string)$name, $old_value, (string)$value);
                }
            }
            $db->commit(hide_errors: true);
            rebuild_settings();
            flash_message($lang->managesettings['flash_settings_saved'], "success");
        } catch (\Throwable $e) {
            $db->rollback(hide_errors: true);
            write_log("[ERROR] " . $e->getMessage());
            flash_message($lang->managesettings['flash_error_prefix'] . $e->getMessage(), "danger");
        }
        admin_redirect(AGS_SELF);
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
$regtype                 = $settings['regtype']                 ?? 'instant';
$minnamelength           = $settings['minnamelength']           ?? '3';
$maxnamelength           = $settings['maxnamelength']           ?? '20';
$minpasswordlength       = $settings['minpasswordlength']       ?? '6';
$maxpasswordlength       = $settings['maxpasswordlength']       ?? '40';
$requirecomplexpasswords = $settings['requirecomplexpasswords'] ?? '0';
$failedlogincount        = $settings['failedlogincount']        ?? '0';
$failedlogintext         = $settings['failedlogintext']         ?? '0';
$disableregs             = $settings['disableregs']             ?? '0';
$maxusers                = $settings['maxusers']                ?? '0';
$_d_usergroup            = $settings['_d_usergroup']            ?? '1';
$invite_count            = $settings['invite_count']            ?? '0';
$autogigsignup           = $settings['autogigsignup']           ?? '0';
$autosbsignup            = $settings['autosbsignup']            ?? '0';
// CLEANUP
$max_dead_torrent_time  = $settings['max_dead_torrent_time']  ?? '30';
$promote_gig_limit      = $settings['promote_gig_limit']      ?? '0';
$promote_min_ratio      = $settings['promote_min_ratio']      ?? '0.5';
$promote_min_reg_days   = $settings['promote_min_reg_days']   ?? '30';
$demote_min_ratio       = $settings['demote_min_ratio']       ?? '0.2';
$referrergift           = $settings['referrergift']           ?? '0';
$leechwarn_min_ratio    = $settings['leechwarn_min_ratio']    ?? '0.3';
$leechwarn_gig_limit    = $settings['leechwarn_gig_limit']    ?? '10';
$leechwarn_length       = $settings['leechwarn_length']       ?? '2';
$leechwarn_remove_ratio = $settings['leechwarn_remove_ratio'] ?? '0.5';
$ban_user_limit         = $settings['ban_user_limit']         ?? '5';
// HIT & RUN (defaults = the values that used to be hard-coded in weekly_cleanups.php)
$hr_enabled             = $settings['hr_enabled']             ?? 'yes';
$hr_min_seed_hours      = $settings['hr_min_seed_hours']      ?? '24';
$hr_start_date          = $settings['hr_start_date']          ?? '';
$hr_skip_groups         = $settings['hr_skip_groups']         ?? '4,5,6,7,8';
// HIT & RUN staff tool (were $config['ts_hit_and_run'] in admin/include/global_config.php)
$hr_min_ratio           = $settings['hr_min_ratio']           ?? '1.00';
// INACTIVE USERS (were admin/include/inactiveusers_config.php)
$iu_maxdays             = $settings['iu_maxdays']             ?? '60';
$iu_deleteafter         = $settings['iu_deleteafter']         ?? '15';
$iu_protect_groups      = $settings['iu_protect_groups']      ?? '4,5,6,7,8';


$siteOnline          = ($settings['SITEONLINE'] ?? 'yes') === 'yes';
$offlineMinutesValue = $settings['offline_minutes'] ?? '';
$isUnlimited         = !$siteOnline && $offlineMinutesValue === 'unlimited';
$durationMinutes     = 30;
$timeRemaining       = '';
if (!$siteOnline) {
    if ($isUnlimited) {
        $timeRemaining = '<span class="ag-remain t-purple"><i class="fa-solid fa-infinity"></i>' . $lang->managesettings['offline_unlimited'] . '</span>';
    } elseif (is_numeric($offlineMinutesValue) && (int)$offlineMinutesValue > time()) {
        $rem             = (int)ceil(((int)$offlineMinutesValue - time()) / 60);
        $h               = intdiv($rem, 60); $m = $rem % 60;
        $durationMinutes = max(1, $rem);
        $timeRemaining   = '<span class="ag-remain t-orange"><i class="fa-solid fa-hourglass-half"></i>'
                         . ($h > 0 ? ags_fmt((string)$lang->managesettings['offline_remaining_hm'], $h, $m) : ags_fmt((string)$lang->managesettings['offline_remaining_m'], $m)) . '</span>';
    } else {
        $durationMinutes = 30;
        $timeRemaining   = '<span class="ag-remain t-red"><i class="fa-solid fa-triangle-exclamation"></i>' . $lang->managesettings['offline_expired'] . '</span>';
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

$ds              = get_dashboard_stats();
$critical_alerts = check_critical_settings();
$ags_post_key    = generate_post_check();

$pct = static fn(int $a, int $b): int => (int)round($a / max(1, $b) * 100);
$seedPct = $pct((int)($ds['seeders_total'] ?? 0), (int)($ds['peers_total'] ?? 0));

$s = $settings;

// Strings for managesettings.js: every js_* key of the language file, without the prefix
$agsJsLang = [];
foreach ($lang->managesettings as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $agsJsLang[substr((string)$k, 3)] = (string)$v;
    }
}

stdhead();
?>
<link href="<?= $BASEURL ?>/include/templates/default/style/errorss.css" rel="stylesheet">
<link href="<?= $BASEURL ?>/admin/templates/managesettings.css?ver=7" rel="stylesheet">
<link href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css" rel="stylesheet">
<?php flash_message(); ?>
<title><?= htmlspecialchars((string)$SITENAME) ?> <?= $lang->managesettings['page_title'] ?></title>

<div class="ag-settings settings-container">

    <!-- ====== HEADER ====== -->
    <header class="ag-head t-blue">
        <span class="ag-sq ag-sq-lg"><i class="fa-solid fa-sliders"></i></span>
        <div class="ag-head-title">
            <h1><?= $lang->managesettings['head_title'] ?> <span class="ag-ver">v<?= B_VERSION ?></span></h1>
            <p><i class="fa-solid fa-server"></i> <?= ags_fmt((string)$lang->managesettings['head_sub'], htmlspecialchars((string)($s['SITENAME'] ?? $lang->managesettings['head_sub_default']))) ?></p>
        </div>
        <div class="ag-head-actions">
            <span class="ag-status <?= $siteOnline ? 'is-online t-green' : 'is-offline t-red' ?>">
                <span class="dot"></span>
                <i class="fa-solid <?= $siteOnline ? 'fa-globe' : 'fa-screwdriver-wrench' ?>"></i>
                <?= $siteOnline ? $lang->managesettings['status_online'] : $lang->managesettings['status_maintenance'] ?>
            </span>
            <a href="settings_history.php" class="btn btn-outline-secondary ag-pill">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> <?= $lang->managesettings['btn_history'] ?>
            </a>
            <button class="btn btn-primary ag-pill" id="globalSaveBtn" type="button" title="<?= $lang->managesettings['btn_save_tab_title'] ?>">
                <i class="fa-solid fa-floppy-disk me-1"></i> <?= $lang->managesettings['btn_save_tab'] ?>
            </button>
        </div>
    </header>

    <!-- ====== KPI ====== -->
    <section class="ag-kpis" aria-label="<?= $lang->managesettings['kpi_aria'] ?>">
        <div class="ag-kpi t-blue">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-users"></i></span>
                <span class="ag-kpi-label"><?= $lang->managesettings['kpi_users'] ?></span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['users_total'] ?? 0)) ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-user-plus"></i>+<?= number_format((int)($ds['users_today'] ?? 0)) ?> <?= $lang->managesettings['kpi_today'] ?></span>
                <span><i class="fa-solid fa-signal"></i><?= number_format((int)($ds['users_active'] ?? 0)) ?> <?= $lang->managesettings['kpi_active24'] ?></span>
            </div>
        </div>

        <div class="ag-kpi t-purple">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-magnet"></i></span>
                <span class="ag-kpi-label"><?= $lang->managesettings['kpi_torrents'] ?></span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['torrents_total'] ?? 0)) ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-cloud-arrow-up"></i>+<?= number_format((int)($ds['torrents_today'] ?? 0)) ?> <?= $lang->managesettings['kpi_today'] ?></span>
                <span><i class="fa-solid fa-seedling"></i><?= $pct((int)($ds['torrents_active'] ?? 0), (int)($ds['torrents_total'] ?? 0)) ?>% <?= $lang->managesettings['kpi_seeded'] ?></span>
            </div>
        </div>

        <div class="ag-kpi t-cyan">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-network-wired"></i></span>
                <span class="ag-kpi-label"><?= $lang->managesettings['kpi_peers'] ?></span>
                <span class="ag-kpi-live" title="<?= $lang->managesettings['kpi_live_title'] ?>"><i class="fa-solid fa-circle"></i><?= number_format((int)($ds['active_peers'] ?? 0)) ?> <?= $lang->managesettings['kpi_live'] ?></span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['peers_total'] ?? 0)) ?></div>
            <div class="ag-split" role="img" aria-label="<?= $seedPct ?>% <?= $lang->managesettings['kpi_seeders'] ?>">
                <span style="width:<?= $seedPct ?>%"></span>
            </div>
            <div class="ag-kpi-meta">
                <span class="t-green"><i class="fa-solid fa-arrow-up"></i><?= number_format((int)($ds['seeders_total'] ?? 0)) ?> <?= $lang->managesettings['kpi_seeders'] ?></span>
                <span class="t-red"><i class="fa-solid fa-arrow-down"></i><?= number_format((int)($ds['leechers_total'] ?? 0)) ?> <?= $lang->managesettings['kpi_leechers'] ?></span>
            </div>
        </div>

        <div class="ag-kpi t-orange">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-hard-drive"></i></span>
                <span class="ag-kpi-label"><?= $lang->managesettings['kpi_shared'] ?></span>
            </div>
            <div class="ag-kpi-value"><?= $ds['total_size'] ?? '0 B' ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-skull"></i><?= number_format((int)($ds['dead_torrents'] ?? 0)) ?> <?= $lang->managesettings['kpi_dead'] ?></span>
                <span><i class="fa-solid fa-folder-tree"></i><?= number_format((int)($ds['categories_active'] ?? 0)) ?> <?= $lang->managesettings['kpi_categories'] ?></span>
            </div>
        </div>
    </section>

    <!-- ====== ALERTS ====== -->
    <?php if (!empty($critical_alerts)): ?>
    <section class="ag-alerts" aria-label="<?= $lang->managesettings['alerts_aria'] ?>">
        <?php foreach ($critical_alerts as $alert):
            $tone = match($alert['type']) { 'danger' => 't-red', 'warning' => 't-orange', default => 't-cyan' }; ?>
        <div class="ag-alert <?= $tone ?>" role="alert">
            <span class="ag-sq"><i class="<?= ags_icon($alert['icon']) ?>"></i></span>
            <div class="ag-alert-body">
                <div class="ag-alert-title"><?= $alert['title'] ?></div>
                <div class="ag-alert-msg"><?= $alert['message'] ?></div>
            </div>
            <?php if (!empty($alert['action'])): ?>
                <div class="ag-alert-action"><?= $alert['action'] ?> <i class="fa-solid fa-arrow-right"></i></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <div class="ag-layout settings-layout">

        <!-- ====== SIDEBAR ====== -->
        <nav class="ag-nav settings-sidebar" aria-label="<?= $lang->managesettings['nav_aria'] ?>">
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-globe"></i> <?= $lang->managesettings['nav_group_site'] ?></div>
                <ul class="sidebar-nav">
                    <?php nav_item('#main-settings', 'fa-gear', 't-blue', $lang->managesettings['nav_main'],
                        count($critical_alerts) > 0 ? '<span class="ag-count is-alert"><i class="fa-solid fa-bell"></i> ' . count($critical_alerts) . '</span>' : '', true, true); ?>
                    <?php nav_item('#date-time', 'fa-clock', 't-teal', $lang->managesettings['nav_datetime']); ?>
                    <?php nav_item('#cookie-settings', 'fa-cookie-bite', 't-yellow', $lang->managesettings['nav_cookies']); ?>
                    <?php nav_item('#avatar-settings', 'fa-circle-user', 't-pink', $lang->managesettings['nav_avatars']); ?>
                    <?php nav_item('#security-settings', 'fa-shield-halved', 't-red', $lang->managesettings['nav_security']); ?>
                    <?php nav_item('#email-settings', 'fa-envelope', 't-orange', $lang->managesettings['nav_email']); ?>
                    <?php nav_item('#forum-legacy-settings', 'fa-comments', 't-indigo', $lang->managesettings['nav_forum']); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-magnet"></i> <?= $lang->managesettings['nav_group_tracker'] ?></div>
                <ul class="sidebar-nav">
                    <?php nav_item('#tracker-settings', 'fa-server', 't-purple', $lang->managesettings['nav_tracker']); ?>
                    <?php nav_item('#announce-settings', 'fa-tower-broadcast', 't-cyan', $lang->managesettings['nav_announce'], '<span class="ag-count">' . $lang->managesettings['nav_badge_core'] . '</span>'); ?>
                    <?php nav_item('#freeleech-settings', 'fa-gift', 't-red', $lang->managesettings['nav_freeleech']); ?>
                    <?php nav_item('index.php?act=torrents_promo', 'fa-shuffle', 't-cyan', $lang->managesettings['nav_promo'], '', false); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-users"></i> <?= $lang->managesettings['nav_group_members'] ?></div>
                <ul class="sidebar-nav">
                    <?php nav_item('#registration-settings', 'fa-user-plus', 't-green', $lang->managesettings['nav_registration']); ?>
                    <?php nav_item('#user-management-settings', 'fa-users-gear', 't-teal', $lang->managesettings['nav_cleanup']); ?>
                    <?php nav_item('#kps-settings', 'fa-coins', 't-yellow', $lang->managesettings['nav_kps']); ?>
                    <?php nav_item('#staff-team', 'fa-user-shield', 't-purple', $lang->managesettings['nav_staff'], '<span class="ag-count">' . count($staffarray) . '</span>'); ?>
                    <?php nav_item('index.php?act=seedbonus_settings', 'fa-seedling', 't-green', $lang->managesettings['nav_seedbonus'], '', false); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-toolbox"></i> <?= $lang->managesettings['nav_group_tools'] ?></div>
                <ul class="sidebar-nav">
                    <?php nav_item('settings_history.php', 'fa-clock-rotate-left', 't-blue', $lang->managesettings['nav_history'], '', false); ?>
                    <?php nav_item('index.php?act=cronjobs', 'fa-calendar-check', 't-indigo', $lang->managesettings['nav_cronjobs'], '', false); ?>
                </ul>
            </div>
        </nav>

        <!-- ====== CONTENT ====== -->
        <main class="ag-main settings-main">
            <div class="tab-content">

                <!-- ── MAIN ─────────────────────────────────────────────────────── -->
                <div class="tab-pane fade show active t-blue" id="main-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-gear', $lang->managesettings['pane_main_settings_title'], $lang->managesettings['pane_main_settings_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-id-card', $lang->managesettings['sec_basic_information']); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('SITENAME', (string)($s['SITENAME'] ?? ''), $lang->managesettings['lbl_SITENAME'], '', 'text', 'fa-signature'); ?>
                                <?php ftxt('BASEURL', (string)($s['BASEURL'] ?? ''), $lang->managesettings['lbl_BASEURL'], $lang->managesettings['tip_BASEURL'], 'text', 'fa-link'); ?>
                                <?php ftxt('SITEEMAIL', (string)($s['SITEEMAIL'] ?? ''), $lang->managesettings['lbl_SITEEMAIL'], $lang->managesettings['tip_SITEEMAIL'], 'email', 'fa-at'); ?>
                                <?php ftxt('contactemail', (string)($s['contactemail'] ?? ''), $lang->managesettings['lbl_contactemail'], $lang->managesettings['tip_contactemail'], 'text', 'fa-address-book'); ?>
                                <?php ftxt('slogan', (string)($s['slogan'] ?? ''), $lang->managesettings['lbl_slogan'], '', 'text', 'fa-quote-right'); ?>
                                <?php fsel('defaultlanguage', [
                                    'english' => $lang->managesettings['opt_lang_english'], 'russian' => $lang->managesettings['opt_lang_russian'], 'ukrainian' => $lang->managesettings['opt_lang_ukrainian'],
                                    'german' => $lang->managesettings['opt_lang_german'], 'french' => $lang->managesettings['opt_lang_french'], 'spanish' => $lang->managesettings['opt_lang_spanish'],
                                ], (string)($s['defaultlanguage'] ?? 'english'), $lang->managesettings['lbl_default_language'], '', 'fa-language'); ?>
                            </div>

                            <?php fsec('fa-magnifying-glass-chart', $lang->managesettings['sec_seo'], $lang->managesettings['sec_seo_hint']); ?>
                            <div class="ag-grid ag-grid-2">
                                <div class="ag-field">
                                    <label class="ag-label" for="f_metakeywords"><i class="fa-solid fa-tags"></i><?= $lang->managesettings['lbl_metakeywords'] ?></label>
                                    <textarea class="form-control ag-input" id="f_metakeywords" name="configoption[metakeywords]" rows="3"><?= htmlspecialchars((string)($s['metakeywords'] ?? '')) ?></textarea>
                                    <?= ags_hint($lang->managesettings['help_metakeywords']) ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_metadesc"><i class="fa-solid fa-align-left"></i><?= $lang->managesettings['lbl_metadesc'] ?></label>
                                    <textarea class="form-control ag-input" id="f_metadesc" name="configoption[metadesc]" rows="3"><?= htmlspecialchars((string)($s['metadesc'] ?? '')) ?></textarea>
                                    <?= ags_hint($lang->managesettings['help_metadesc']) ?>
                                </div>
                            </div>

                            <?php fsec('fa-power-off', $lang->managesettings['sec_site_status'], $lang->managesettings['sec_site_status_hint']); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php fswitch('SITEONLINE', $siteOnline, $lang->managesettings['lbl_SITEONLINE'], $lang->managesettings['tip_SITEONLINE'], 'fa-power-off'); ?>
                                <?php ftxt('offline_message', (string)($s['offline_message'] ?? $lang->managesettings['offline_default']), $lang->managesettings['lbl_offline_message'], '', 'text', 'fa-person-digging'); ?>
                            </div>

                            <div id="offlineDurationGroup" class="ag-offline t-red" style="display:<?= !$siteOnline ? 'block' : 'none' ?>">
                                <?php if ($timeRemaining): ?>
                                    <div class="ag-offline-state">
                                        <?= $timeRemaining ?>
                                        <?php if (!$isUnlimited && is_numeric($offlineMinutesValue)): ?>
                                            <span class="ag-muted"><i class="fa-regular fa-calendar"></i> <?= $lang->managesettings['offline_back_at'] ?> <?= date('Y-m-d H:i', (int)$offlineMinutesValue) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ag-radio-row">
                                    <label class="ag-radio t-orange" for="limitedMode">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" id="limitedMode" value="limited" <?= !$isUnlimited ? 'checked' : '' ?>>
                                        <i class="fa-solid fa-hourglass-half"></i> <?= $lang->managesettings['offline_limited'] ?>
                                    </label>
                                    <label class="ag-radio t-purple" for="unlimitedMode">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" id="unlimitedMode" value="unlimited" <?= $isUnlimited ? 'checked' : '' ?>>
                                        <i class="fa-solid fa-infinity"></i> <?= $lang->managesettings['offline_manual'] ?>
                                    </label>
                                </div>
                                <div id="timeLimitGroup" class="ag-field" style="display:<?= !$isUnlimited ? 'flex' : 'none' ?>;max-width:280px">
                                    <label class="ag-label" for="f_offline_minutes"><i class="fa-solid fa-stopwatch"></i><?= $lang->managesettings['lbl_offline_duration'] ?></label>
                                    <div class="ag-affix">
                                        <input type="number" min="1" max="1440" class="form-control ag-input" id="f_offline_minutes"
                                               name="offline_minutes_input" value="<?= !$isUnlimited ? $durationMinutes : 30 ?>">
                                        <span><?= $lang->managesettings['offline_minmax'] ?></span>
                                    </div>
                                    <?= ags_hint($lang->managesettings['help_offline_duration']) ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── TRACKER ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-purple" id="tracker-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-server', $lang->managesettings['pane_tracker_title'], $lang->managesettings['pane_tracker_sub'], $lang->managesettings['pane_tracker_tag'], 'fa-wrench'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-toggle-on', $lang->managesettings['sec_features']); ?>
                            <div class="ag-grid">
                                <?php fswitch('use_xmlhttprequest', ($s['use_xmlhttprequest'] ?? '1') === '1', $lang->managesettings['lbl_use_xmlhttprequest'], $lang->managesettings['tip_use_xmlhttprequest'], 'fa-code', '1', '0'); ?>
                                <?php fswitch('seourls', ($s['seourls'] ?? 'no') === 'yes', $lang->managesettings['lbl_seourls'], '', 'fa-magnifying-glass'); ?>
                                <?php fswitch('gzipcompress', ($s['gzipcompress'] ?? 'yes') === 'yes', $lang->managesettings['lbl_gzipcompress'], $lang->managesettings['tip_gzipcompress'], 'fa-file-zipper'); ?>
                                <?php fswitch('jumptopagemultipage', ($s['jumptopagemultipage'] ?? '1') === '1', $lang->managesettings['lbl_jumptopagemultipage'], '', 'fa-arrow-right-to-bracket', '1', '0'); ?>
                                <?php fswitch('hitrun', ($s['hitrun'] ?? 'yes') === 'yes', $lang->managesettings['lbl_hitrun'], '', 'fa-person-running'); ?>
                            </div>

                            <?php fsec('fa-ruler-combined', $lang->managesettings['sec_limits']); ?>
                            <div class="ag-grid">
                                <?php ftxt('maxloginattempts', (string)($s['maxloginattempts'] ?? '5'), $lang->managesettings['lbl_maxloginattempts'], $lang->managesettings['tip_maxloginattempts'], 'number', 'fa-user-lock'); ?>
                                <?php ftxt('maxmultipagelinks', (string)($s['maxmultipagelinks'] ?? '5'), $lang->managesettings['lbl_maxmultipagelinks'], '', 'number', 'fa-ellipsis'); ?>
                                <?php ftxt('wolcutoffmins', (string)($s['wolcutoffmins'] ?? '15'), $lang->managesettings['lbl_wolcutoffmins'], $lang->managesettings['tip_wolcutoffmins'], 'number', 'fa-user-clock'); ?>
                                <?php ftxt('hitrun_ratio', (string)($s['hitrun_ratio'] ?? '0.5'), $lang->managesettings['lbl_hitrun_ratio'], '', 'text', 'fa-scale-balanced'); ?>
                                <?php ftxt('hitrun_gig', (string)($s['hitrun_gig'] ?? '5'), $lang->managesettings['lbl_hitrun_gig'], '', 'number', 'fa-database'); ?>
                            </div>

                            <?php fsec('fa-folder-tree', $lang->managesettings['sec_urls_paths']); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('announce_urls[]', (string)$announce_url, $lang->managesettings['lbl_announce_urls'], $lang->managesettings['tip_announce_urls'], 'text', 'fa-tower-broadcast'); ?>
                                <?php ftxt('torrent_dir', (string)($s['torrent_dir'] ?? ''), $lang->managesettings['lbl_torrent_dir'], $lang->managesettings['tip_torrent_dir'], 'text', 'fa-folder'); ?>
                                <?php ftxt('pic_base_url', (string)($s['pic_base_url'] ?? ''), $lang->managesettings['lbl_pic_base_url'], $lang->managesettings['tip_pic_base_url'], 'text', 'fa-images'); ?>
                            </div>

                            <?php fsec('fa-paperclip', $lang->managesettings['sec_attachments']); ?>
                            <div class="ag-grid">
                                <?php fswitch('enableattachments', ($s['enableattachments'] ?? '1') === '1', $lang->managesettings['lbl_enableattachments'], $lang->managesettings['tip_enableattachments'], 'fa-paperclip', '1', '0'); ?>
                                <?php ftxt('maxattachments', (string)($s['maxattachments'] ?? '5'), $lang->managesettings['lbl_maxattachments'], $lang->managesettings['tip_maxattachments'], 'number', 'fa-layer-group'); ?>
                                <?php fsel('attachthumbnails', ['yes' => $lang->managesettings['opt_thumb_yes'], 'no' => $lang->managesettings['opt_thumb_no'], 'download' => $lang->managesettings['opt_thumb_download']], (string)($s['attachthumbnails'] ?? 'yes'), $lang->managesettings['lbl_attachthumbnails'], '', 'fa-image'); ?>
                                <?php ftxt('attachthumbh', (string)($s['attachthumbh'] ?? '96'), $lang->managesettings['lbl_attachthumbh'], $lang->managesettings['tip_attachthumbh'], 'number', 'fa-arrows-up-down'); ?>
                                <?php ftxt('attachthumbw', (string)($s['attachthumbw'] ?? '96'), $lang->managesettings['lbl_attachthumbw'], $lang->managesettings['tip_attachthumbw'], 'number', 'fa-arrows-left-right'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── DATE & TIME ──────────────────────────────────────────────── -->
                <div class="tab-pane fade t-teal" id="date-time">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-clock', $lang->managesettings['pane_date_time_title'], $lang->managesettings['pane_date_time_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-calendar-days', $lang->managesettings['sec_formats'], $lang->managesettings['sec_formats_hint']); ?>
                            <div class="ag-grid">
                                <?php ftxt('dateformat', (string)($s['dateformat'] ?? 'd M Y'), $lang->managesettings['lbl_dateformat'], ags_fmt((string)$lang->managesettings['tip_dateformat'], date('d M Y')), 'text', 'fa-calendar-day'); ?>
                                <?php ftxt('timeformat', (string)($s['timeformat'] ?? 'H:i'), $lang->managesettings['lbl_timeformat'], ags_fmt((string)$lang->managesettings['tip_timeformat'], date('H:i')), 'text', 'fa-clock'); ?>
                                <?php ftxt('regdateformat', (string)($s['regdateformat'] ?? 'd M Y'), $lang->managesettings['lbl_regdateformat'], '', 'text', 'fa-calendar-check'); ?>
                                <?php ftxt('datetimesep', (string)($s['datetimesep'] ?? ', '), $lang->managesettings['lbl_datetimesep'], '', 'text', 'fa-grip-lines-vertical'); ?>
                            </div>

                            <?php fsec('fa-earth-europe', $lang->managesettings['sec_timezone']); ?>
                            <div class="ag-grid">
                                <?php
                                $tzOpts = [];
                                foreach (['-12','-11','-10','-9','-8','-7','-6','-5','-4','-3.5','-3','-2','-1','0','+1','+2','+3','+3.5','+4','+4.5','+5','+5.5','+5.75','+6','+7','+8','+9','+9.5','+10','+10.5','+11','+12'] as $tz) {
                                    $tzOpts[$tz] = 'GMT ' . $tz;
                                }
                                fsel('timezoneoffset', $tzOpts, (string)($s['timezoneoffset'] ?? '0'), $lang->managesettings['lbl_timezoneoffset'], '', 'fa-earth-europe');
                                ?>
                                <?php fswitch('dstcorrection', ($s['dstcorrection'] ?? '0') === '1', $lang->managesettings['lbl_dstcorrection'], '', 'fa-sun', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── COOKIES ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-yellow" id="cookie-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-cookie-bite', $lang->managesettings['pane_cookies_title'], $lang->managesettings['pane_cookies_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-globe', $lang->managesettings['sec_scope']); ?>
                            <div class="ag-grid">
                                <?php ftxt('cookiedomain', (string)($s['cookiedomain'] ?? ''), $lang->managesettings['lbl_cookiedomain'], $lang->managesettings['tip_cookiedomain'], 'text', 'fa-globe'); ?>
                                <?php ftxt('cookiepath', (string)($s['cookiepath'] ?? '/'), $lang->managesettings['lbl_cookiepath'], '', 'text', 'fa-folder-open'); ?>
                                <?php ftxt('cookieprefix', (string)($s['cookieprefix'] ?? ''), $lang->managesettings['lbl_cookieprefix'], '', 'text', 'fa-font'); ?>
                            </div>

                            <?php fsec('fa-lock', $lang->managesettings['sec_security_flags']); ?>
                            <div class="ag-grid">
                                <?php fswitch('cookiesecureflag', ($s['cookiesecureflag'] ?? '0') === '1', $lang->managesettings['lbl_cookiesecureflag'], $lang->managesettings['tip_cookiesecureflag'], 'fa-lock', '1', '0'); ?>
                                <?php fswitch('cookiesamesiteflag', ($s['cookiesamesiteflag'] ?? '0') === '1', $lang->managesettings['lbl_cookiesamesiteflag'], $lang->managesettings['tip_cookiesamesiteflag'], 'fa-shield-halved', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── AVATARS ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-pink" id="avatar-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-circle-user', $lang->managesettings['pane_avatars_title'], $lang->managesettings['pane_avatars_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-user', $lang->managesettings['sec_default_avatar']); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('useravatar', (string)($s['useravatar'] ?? ''), $lang->managesettings['lbl_useravatar'], $lang->managesettings['tip_useravatar'], 'text', 'fa-image-portrait'); ?>
                                <?php ftxt('useravatardims', (string)($s['useravatardims'] ?? '40x40'), $lang->managesettings['lbl_useravatardims'], $lang->managesettings['tip_useravatardims'], 'text', 'fa-crop-simple'); ?>
                            </div>

                            <?php fsec('fa-upload', $lang->managesettings['sec_uploads']); ?>
                            <div class="ag-grid">
                                <?php ftxt('maxavatardims', (string)($s['maxavatardims'] ?? '100x100'), $lang->managesettings['lbl_maxavatardims'], '', 'text', 'fa-expand'); ?>
                                <?php ftxt('avatarsize', (string)($s['avatarsize'] ?? '102400'), $lang->managesettings['lbl_avatarsize'], $lang->managesettings['tip_avatarsize'], 'number', 'fa-weight-hanging'); ?>
                                <?php ftxt('avataruploadpath', (string)($s['avataruploadpath'] ?? ''), $lang->managesettings['lbl_avataruploadpath'], '', 'text', 'fa-folder-open'); ?>
                                <?php fswitch('allowremoteavatars', ($s['allowremoteavatars'] ?? '0') === '1', $lang->managesettings['lbl_allowremoteavatars'], $lang->managesettings['tip_allowremoteavatars'], 'fa-cloud-arrow-down', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── SECURITY ─────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-red" id="security-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-shield-halved', $lang->managesettings['pane_security_title'], $lang->managesettings['pane_security_sub']); ?>
                        <div class="ag-pane-body">
                            <div class="ag-grid ag-grid-2">
                                <?php fswitch('aggressivecheckip', ($s['aggressivecheckip'] ?? 'no') === 'yes', $lang->managesettings['lbl_aggressivecheckip'], $lang->managesettings['tip_aggressivecheckip'], 'fa-ban'); ?>
                                <?php fswitch('privatetrackerpatch', ($s['privatetrackerpatch'] ?? 'no') === 'yes', $lang->managesettings['lbl_privatetrackerpatch'], $lang->managesettings['tip_privatetrackerpatch'], 'fa-user-secret'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── EMAIL ────────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-orange" id="email-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-envelope', $lang->managesettings['pane_email_title'], $lang->managesettings['pane_email_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-paper-plane', $lang->managesettings['sec_delivery']); ?>
                            <div class="ag-grid">
                                <?php fsel('mail_handler', ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'sendmail' => 'Sendmail'], (string)($s['mail_handler'] ?? 'mail'), $lang->managesettings['lbl_mail_handler'], $lang->managesettings['tip_mail_handler'], 'fa-envelopes-bulk'); ?>
                                <?php fsel('mail_logging', ['0' => $lang->managesettings['opt_maillog_0'], '1' => $lang->managesettings['opt_maillog_1'], '2' => $lang->managesettings['opt_maillog_2']], (string)($s['mail_logging'] ?? '0'), $lang->managesettings['lbl_mail_logging'], '', 'fa-clipboard-list'); ?>
                                <?php ftxt('mail_queue_limit', (string)($s['mail_queue_limit'] ?? '50'), $lang->managesettings['lbl_mail_queue_limit'], $lang->managesettings['tip_mail_queue_limit'], 'number', 'fa-inbox'); ?>
                                <?php fswitch('mail_message_id', ($s['mail_message_id'] ?? '1') === '1', $lang->managesettings['lbl_mail_message_id'], $lang->managesettings['tip_mail_message_id'], 'fa-fingerprint', '1', '0'); ?>
                            </div>

                            <?php if (($s['mail_handler'] ?? 'mail') === 'smtp'): ?>
                            <?php fsec('fa-server', $lang->managesettings['sec_smtp']); ?>
                            <div class="ag-grid">
                                <?php ftxt('smtp_host', (string)($s['smtp_host'] ?? ''), $lang->managesettings['lbl_smtp_host'], '', 'text', 'fa-server'); ?>
                                <?php ftxt('smtp_port', (string)($s['smtp_port'] ?? '587'), $lang->managesettings['lbl_smtp_port'], $lang->managesettings['tip_smtp_port'], 'number', 'fa-plug'); ?>
                                <?php fsel('secure_smtp', ['0' => $lang->managesettings['opt_smtp_none'], '1' => 'SSL', '2' => 'TLS'], (string)($s['secure_smtp'] ?? '0'), $lang->managesettings['lbl_secure_smtp'], '', 'fa-lock'); ?>
                                <?php ftxt('smtp_user', (string)($s['smtp_user'] ?? ''), $lang->managesettings['lbl_smtp_user'], '', 'text', 'fa-user'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_smtp_pass"><i class="fa-solid fa-key"></i><?= $lang->managesettings['lbl_smtp_pass'] ?></label>
                                    <input type="password" class="form-control ag-input" id="f_smtp_pass" name="configoption[smtp_pass]" autocomplete="new-password"
                                           placeholder="<?= ($s['smtp_pass'] ?? '') !== '' ? '•••••••• ' . $lang->managesettings['pass_set'] : $lang->managesettings['pass_not_set'] ?>">
                                    <?= ags_hint($lang->managesettings['help_keep_password']) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── ANNOUNCE ─────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-cyan" id="announce-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_announce" value="1">
                        <?php pane_head('fa-tower-broadcast', $lang->managesettings['pane_announce_title'], $lang->managesettings['pane_announce_sub'], $lang->managesettings['pane_announce_tag'], 'fa-microchip'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-cyan">
                                <i class="fa-solid fa-rotate"></i>
                                <div><?= $lang->managesettings['note_announce'] ?></div>
                            </div>

                            <?php fsec('fa-filter', $lang->managesettings['sec_client_checks']); ?>
                            <div class="ag-grid">
                                <?php fswitch('nc', (string)$nc === 'yes', $lang->managesettings['lbl_nc'], $lang->managesettings['tip_nc'], 'fa-plug-circle-xmark'); ?>
                                <?php fswitch('bannedclientdetect', (string)$bannedclientdetect === 'yes', $lang->managesettings['lbl_bannedclientdetect'], '', 'fa-ban'); ?>
                                <?php fswitch('checkconnectable', (string)$checkconnectable === 'yes', $lang->managesettings['lbl_checkconnectable'], $lang->managesettings['tip_checkconnectable'], 'fa-wifi'); ?>
                                <?php fswitch('checkip', (string)$checkip === 'yes', $lang->managesettings['lbl_checkip'], $lang->managesettings['tip_checkip'], 'fa-location-crosshairs'); ?>
                            </div>

                            <?php fsec('fa-gauge-high', $lang->managesettings['sec_timing_limits']); ?>
                            <div class="ag-grid">
                                <?php ftxt('announce_wait', (string)$announce_wait, $lang->managesettings['lbl_announce_wait'], $lang->managesettings['tip_announce_wait'], 'number', 'fa-hourglass-start'); ?>
                                <?php ftxt('announce_interval', (string)$announce_interval, $lang->managesettings['lbl_announce_interval'], $lang->managesettings['tip_announce_interval'], 'number', 'fa-repeat'); ?>
                                <?php ftxt('max_rate', (string)$max_rate, $lang->managesettings['lbl_max_rate'], $lang->managesettings['tip_max_rate'], 'number', 'fa-gauge-simple-high'); ?>
                            </div>
                            <div class="ag-field mt-3">
                                <label class="ag-label" for="f_allowed_clients"><i class="fa-solid fa-list-check"></i><?= $lang->managesettings['lbl_allowed_clients'] ?></label>
                                <textarea class="form-control ag-input ag-mono" id="f_allowed_clients" name="configoption[allowed_clients]" rows="4"><?= htmlspecialchars((string)$allowed_clients) ?></textarea>
                                <?= ags_hint($lang->managesettings['help_allowed_clients']) ?>
                            </div>

                            <?php fsec('fa-database', $lang->managesettings['sec_announce_database']); ?>
                            <div class="ag-grid">
                                <?php ftxt('mysql_host', (string)$mysql_host, $lang->managesettings['lbl_mysql_host'], '', 'text', 'fa-server'); ?>
                                <?php ftxt('mysql_db', (string)$mysql_db, $lang->managesettings['lbl_mysql_db'], '', 'text', 'fa-database'); ?>
                                <?php ftxt('mysql_user', (string)$mysql_user, $lang->managesettings['lbl_mysql_user'], '', 'text', 'fa-user'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_mysql_pass"><i class="fa-solid fa-key"></i><?= $lang->managesettings['lbl_mysql_pass'] ?></label>
                                    <input type="password" class="form-control ag-input" id="f_mysql_pass" name="configoption[mysql_pass]" autocomplete="new-password"
                                           placeholder="<?= (string)$mysql_pass !== '' ? '•••••••• ' . $lang->managesettings['pass_set'] : $lang->managesettings['pass_not_set'] ?>">
                                    <?= ags_hint($lang->managesettings['help_keep_password']) ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar($lang->managesettings['save_rebuild']); ?>
                    </form>
                </div>

                <!-- ── KPS ──────────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-yellow" id="kps-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_kps" value="1">
                        <?php pane_head('fa-coins', $lang->managesettings['pane_kps_bonus_points_title'], $lang->managesettings['pane_kps_bonus_points_sub'], $lang->managesettings['pane_kps_bonus_points_tag'], 'fa-star'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-grid ag-grid-2">
                                <?php fsel('bonus', ['enable' => $lang->managesettings['opt_bonus_enable'], 'disablesave' => $lang->managesettings['opt_bonus_disablesave'], 'disable' => $lang->managesettings['opt_bonus_disable']], (string)$bonus, $lang->managesettings['lbl_bonus'], '', 'fa-power-off'); ?>
                            </div>

                            <?php fsec('fa-hand-holding-dollar', $lang->managesettings['sec_earning'], $lang->managesettings['sec_earning_hint']); ?>
                            <div class="ag-grid">
                                <?php ftxt('kpsupload', (string)$kpsupload, $lang->managesettings['lbl_kpsupload'], '', 'number', 'fa-cloud-arrow-up'); ?>
                                <?php ftxt('kpscomment', (string)$kpscomment, $lang->managesettings['lbl_kpscomment'], '', 'number', 'fa-comment'); ?>
                                <?php ftxt('kpsthanks', (string)$kpsthanks, $lang->managesettings['lbl_kpsthanks'], '', 'number', 'fa-heart'); ?>
                                <?php ftxt('kpsrate', (string)$kpsrate, $lang->managesettings['lbl_kpsrate'], '', 'number', 'fa-star'); ?>
                                <?php ftxt('kpspoll', (string)$kpspoll, $lang->managesettings['lbl_kpspoll'], '', 'number', 'fa-square-poll-vertical'); ?>
                                <?php ftxt('kpsmaxpoint', (string)$kpsmaxpoint, $lang->managesettings['lbl_kpsmaxpoint'], $lang->managesettings['tip_kpsmaxpoint'], 'number', 'fa-arrow-up-9-1'); ?>
                            </div>

                            <?php fsec('fa-cart-shopping', $lang->managesettings['sec_spending'], $lang->managesettings['sec_spending_hint']); ?>
                            <div class="ag-grid">
                                <?php fswitch('kpsinvite', $kpsinvite === 'yes', $lang->managesettings['lbl_kpsinvite'], '', 'fa-envelope-open-text'); ?>
                                <?php fswitch('kpstitle', $kpstitle === 'yes', $lang->managesettings['lbl_kpstitle'], '', 'fa-user-tag'); ?>
                                <?php fswitch('kpsvip', $kpsvip === 'yes', $lang->managesettings['lbl_kpsvip'], '', 'fa-crown'); ?>
                                <?php fswitch('kpsgift', $kpsgift === 'yes', $lang->managesettings['lbl_kpsgift'], '', 'fa-gift'); ?>
                                <?php fswitch('kpswarning', $kpswarning === 'yes', $lang->managesettings['lbl_kpswarning'], '', 'fa-triangle-exclamation'); ?>
                                <?php fswitch('kpsratiofix', $kpsratiofix === 'yes', $lang->managesettings['lbl_kpsratiofix'], '', 'fa-scale-balanced'); ?>
                            </div>

                            <?php fsec('fa-cake-candles', $lang->managesettings['sec_birthday_reward']); ?>
                            <div class="ag-grid">
                                <?php fswitch('bdayreward', $bdayreward === 'yes', $lang->managesettings['lbl_bdayreward'], '', 'fa-cake-candles'); ?>
                                <?php fsel('bdayrewardtype', ['freeleech' => $lang->managesettings['opt_promo_free'], 'silverleech' => $lang->managesettings['opt_promo_silver'], 'doubleupload' => $lang->managesettings['opt_promo_double']], (string)$bdayrewardtype, $lang->managesettings['lbl_bdayrewardtype'], '', 'fa-gift'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── CLEANUP ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-teal" id="user-management-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_user_management" value="1">
                        <?php pane_head('fa-users-gear', $lang->managesettings['pane_cleanup_title'], $lang->managesettings['pane_cleanup_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-magnet', $lang->managesettings['sec_torrents']); ?>
                            <div class="ag-grid">
                                <?php ftxt('max_dead_torrent_time', (string)$max_dead_torrent_time, $lang->managesettings['lbl_max_dead_torrent_time'], $lang->managesettings['tip_max_dead_torrent_time'], 'number', 'fa-eye-slash'); ?>
                            </div>

                            <?php fsec('fa-arrow-trend-up', $lang->managesettings['sec_promotion_demotion']); ?>
                            <div class="ag-grid">
                                <?php ftxt('promote_gig_limit', (string)$promote_gig_limit, $lang->managesettings['lbl_promote_gig_limit'], $lang->managesettings['tip_promote_gig_limit'], 'number', 'fa-database'); ?>
                                <?php ftxt('promote_min_ratio', (string)$promote_min_ratio, $lang->managesettings['lbl_promote_min_ratio'], '', 'text', 'fa-scale-balanced'); ?>
                                <?php ftxt('promote_min_reg_days', (string)$promote_min_reg_days, $lang->managesettings['lbl_promote_min_reg_days'], '', 'number', 'fa-calendar'); ?>
                                <?php ftxt('demote_min_ratio', (string)$demote_min_ratio, $lang->managesettings['lbl_demote_min_ratio'], '', 'text', 'fa-arrow-trend-down'); ?>
                                <?php ftxt('referrergift', (string)$referrergift, $lang->managesettings['lbl_referrergift'], '', 'number', 'fa-gift'); ?>
                            </div>

                            <?php fsec('fa-triangle-exclamation', $lang->managesettings['sec_leech_warnings']); ?>
                            <div class="ag-grid">
                                <?php ftxt('leechwarn_min_ratio', (string)$leechwarn_min_ratio, $lang->managesettings['lbl_leechwarn_min_ratio'], '', 'text', 'fa-scale-unbalanced'); ?>
                                <?php ftxt('leechwarn_gig_limit', (string)$leechwarn_gig_limit, $lang->managesettings['lbl_leechwarn_gig_limit'], '', 'number', 'fa-download'); ?>
                                <?php ftxt('leechwarn_length', (string)$leechwarn_length, $lang->managesettings['lbl_leechwarn_length'], '', 'number', 'fa-hourglass-half'); ?>
                                <?php ftxt('leechwarn_remove_ratio', (string)$leechwarn_remove_ratio, $lang->managesettings['lbl_leechwarn_remove_ratio'], '', 'text', 'fa-circle-check'); ?>
                                <?php ftxt('ban_user_limit', (string)$ban_user_limit, $lang->managesettings['lbl_ban_user_limit'], '', 'number', 'fa-gavel'); ?>
                            </div>

                            <?php fsec('fa-person-running', $lang->managesettings['sec_hit_and_run'], $lang->managesettings['hint_sec_hit_and_run']); ?>
                            <div class="ag-grid">
                                <?php fswitch('hr_enabled', $hr_enabled === 'yes', $lang->managesettings['lbl_hr_enabled'], '', 'fa-person-running'); ?>
                                <?php ftxt('hr_min_seed_hours', (string)$hr_min_seed_hours, $lang->managesettings['lbl_hr_min_seed_hours'], '', 'number', 'fa-seedling'); ?>
                                <?php ftxt('hr_start_date', (string)$hr_start_date, $lang->managesettings['lbl_hr_start_date'], '', 'date', 'fa-calendar-day'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_hr_skip_groups"><i class="fa-solid fa-user-shield"></i><?= htmlspecialchars($lang->managesettings['lbl_hr_skip_groups']) ?></label>
                                    <?php $hrSkipSelected = array_filter(explode(',', (string)$hr_skip_groups), fn($v) => $v !== ''); ?>
                                    <input type="hidden" name="configoption[hr_skip_groups][]" value="">
                                    <select class="form-select ag-input" id="f_hr_skip_groups" name="configoption[hr_skip_groups][]" multiple size="6">
                                        <?php
                                        $gqHr = $db->sql_query_prepared("SELECT gid, title FROM usergroups ORDER BY gid ASC");
                                        while ($gqHr && ($gHr = $db->fetch_array($gqHr))) {
                                            $sel = in_array((string)$gHr['gid'], $hrSkipSelected, true) ? ' selected' : '';
                                            echo '<option value="' . (int)$gHr['gid'] . '"' . $sel . '>' . htmlspecialchars(strip_tags((string)$gHr['title'])) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <?= ags_hint(ags_tip('hr_skip_groups', '')) ?>
                                </div>
                                <?php ftxt('hr_min_ratio', (string)$hr_min_ratio, $lang->managesettings['lbl_hr_min_ratio'], '', 'text', 'fa-scale-unbalanced'); ?>
                            </div>

                            <?php fsec('fa-user-clock', $lang->managesettings['sec_inactive_users'], $lang->managesettings['hint_sec_inactive_users']); ?>
                            <div class="ag-grid">
                                <?php ftxt('iu_maxdays', (string)$iu_maxdays, $lang->managesettings['lbl_iu_maxdays'], '', 'number', 'fa-bed'); ?>
                                <?php ftxt('iu_deleteafter', (string)$iu_deleteafter, $lang->managesettings['lbl_iu_deleteafter'], '', 'number', 'fa-hourglass-end'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_iu_protect_groups"><i class="fa-solid fa-user-shield"></i><?= htmlspecialchars($lang->managesettings['lbl_iu_protect_groups']) ?></label>
                                    <?php $iuProtectSelected = array_filter(explode(',', (string)$iu_protect_groups), fn($v) => $v !== ''); ?>
                                    <input type="hidden" name="configoption[iu_protect_groups][]" value="">
                                    <select class="form-select ag-input" id="f_iu_protect_groups" name="configoption[iu_protect_groups][]" multiple size="6">
                                        <?php
                                        $gqIu = $db->sql_query_prepared("SELECT gid, title FROM usergroups ORDER BY gid ASC");
                                        while ($gqIu && ($gIu = $db->fetch_array($gqIu))) {
                                            $sel = in_array((string)$gIu['gid'], $iuProtectSelected, true) ? ' selected' : '';
                                            echo '<option value="' . (int)$gIu['gid'] . '"' . $sel . '>' . htmlspecialchars(strip_tags((string)$gIu['title'])) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <?= ags_hint(ags_tip('iu_protect_groups', '')) ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── REGISTRATION ─────────────────────────────────────────────── -->
                <div class="tab-pane fade t-green" id="registration-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_registration" value="1">
                        <?php pane_head('fa-user-plus', $lang->managesettings['pane_registration_title'], $lang->managesettings['pane_registration_sub']); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-door-open', $lang->managesettings['sec_signup']); ?>
                            <div class="ag-grid">
                                <?php fsel('regtype', ['invite' => $lang->managesettings['opt_reg_invite'], 'instant' => $lang->managesettings['opt_reg_instant'], 'verify' => $lang->managesettings['opt_reg_verify']], (string)$regtype, $lang->managesettings['lbl_regtype'], '', 'fa-user-plus'); ?>
                                <?php fswitch('disableregs', $disableregs === '1', $lang->managesettings['lbl_disableregs'], $lang->managesettings['tip_disableregs'], 'fa-user-slash', '1', '0'); ?>
                                <?php ftxt('maxusers', (string)$maxusers, $lang->managesettings['lbl_maxusers'], $lang->managesettings['tip_maxusers'], 'number', 'fa-users'); ?>
                            </div>

                            <?php fsec('fa-user-pen', $lang->managesettings['sec_usernames_passwords']); ?>
                            <div class="ag-grid">
                                <?php ftxt('minnamelength', (string)$minnamelength, $lang->managesettings['lbl_minnamelength'], '', 'number', 'fa-arrow-down-1-9'); ?>
                                <?php ftxt('maxnamelength', (string)$maxnamelength, $lang->managesettings['lbl_maxnamelength'], '', 'number', 'fa-arrow-up-9-1'); ?>
                                <?php ftxt('minpasswordlength', (string)$minpasswordlength, $lang->managesettings['lbl_minpasswordlength'], '', 'number', 'fa-arrow-down-1-9'); ?>
                                <?php ftxt('maxpasswordlength', (string)$maxpasswordlength, $lang->managesettings['lbl_maxpasswordlength'], '', 'number', 'fa-arrow-up-9-1'); ?>
                                <?php fswitch('requirecomplexpasswords', $requirecomplexpasswords === '1', $lang->managesettings['lbl_requirecomplexpasswords'], $lang->managesettings['tip_requirecomplexpasswords'], 'fa-key', '1', '0'); ?>
                            </div>

                            <?php fsec('fa-user-lock', $lang->managesettings['sec_login_protection']); ?>
                            <div class="ag-grid">
                                <?php ftxt('failedlogincount', (string)$failedlogincount, $lang->managesettings['lbl_failedlogincount'], $lang->managesettings['tip_failedlogincount'], 'number', 'fa-user-lock'); ?>
                                <?php fswitch('failedlogintext', $failedlogintext === '1', $lang->managesettings['lbl_failedlogintext'], '', 'fa-eye', '1', '0'); ?>
                            </div>

                            <?php fsec('fa-gift', $lang->managesettings['sec_new_member_defaults']); ?>
                            <div class="ag-grid">
                                <?php
                                $groupOpts = [];
                                $gq = $db->sql_query_prepared('SELECT gid, title FROM usergroups ORDER BY gid ASC');
                                while ($gq && ($g = $db->fetch_array($gq))) {
                                    $groupOpts[(string)$g['gid']] = strip_tags((string)$g['title']);
                                }
                                fsel('_d_usergroup', $groupOpts, (string)$_d_usergroup, $lang->managesettings['lbl__d_usergroup'], '', 'fa-users-rectangle');
                                ?>
                                <?php ftxt('invite_count', (string)$invite_count, $lang->managesettings['lbl_invite_count'], $lang->managesettings['tip_invite_count'], 'number', 'fa-envelope'); ?>
                                <?php ftxt('autogigsignup', (string)$autogigsignup, $lang->managesettings['lbl_autogigsignup'], $lang->managesettings['tip_autogigsignup'], 'number', 'fa-cloud-arrow-up'); ?>
                                <?php ftxt('autosbsignup', (string)$autosbsignup, $lang->managesettings['lbl_autosbsignup'], $lang->managesettings['tip_autosbsignup'], 'number', 'fa-seedling'); ?>
                            </div>

                            <?php fsec('fa-stopwatch', $lang->managesettings['sec_flood_control'], $lang->managesettings['sec_flood_control_hint']); ?>
                            <div class="ag-grid">
                                <?php ftxt('betweenregstime', (string)($s['betweenregstime'] ?? '24'), $lang->managesettings['lbl_betweenregstime'], '', 'number', 'fa-hourglass-half'); ?>
                                <?php ftxt('maxregsbetweentime', (string)($s['maxregsbetweentime'] ?? '2'), $lang->managesettings['lbl_maxregsbetweentime'], '', 'number', 'fa-user-clock'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── FORUM / LEGACY ───────────────────────────────────────────── -->
                <div class="tab-pane fade t-indigo" id="forum-legacy-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_forum_legacy" value="1">
                        <?php pane_head('fa-comments', $lang->managesettings['pane_forum_legacy_title'], $lang->managesettings['pane_forum_legacy_sub'], $lang->managesettings['pane_forum_legacy_tag'], 'fa-clock-rotate-left'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-indigo">
                                <i class="fa-solid fa-circle-info"></i>
                                <div><?= $lang->managesettings['note_forum'] ?></div>
                            </div>

                            <?php fsec('fa-sliders', $lang->managesettings['sec_general']); ?>
                            <div class="ag-grid">
                                <?php
                                $langOpts = [];
                                foreach (glob(INC_PATH . '/languages/*', GLOB_ONLYDIR) ?: [] as $langDir) {
                                    $langName = basename($langDir);
                                    $langOpts[$langName] = ucfirst($langName);
                                }
                                fsel('defaultlanguage', $langOpts, (string)($s['defaultlanguage'] ?? 'english'), $lang->managesettings['lbl_defaultlanguage'], '', 'fa-language');
                                ?>
                                <?php fsel('shoutboxcharset', ['UTF-8' => 'UTF-8', 'ISO-8859-1' => 'ISO-8859-1'], (string)($s['shoutboxcharset'] ?? 'UTF-8'), $lang->managesettings['lbl_shoutboxcharset'], '', 'fa-comment-dots'); ?>
                                <?php fswitch('enablepms', ($s['enablepms'] ?? '1') === '1', $lang->managesettings['lbl_enablepms'], '', 'fa-envelope-open-text', '1', '0'); ?>
                                <?php fswitch('usezip', ($s['usezip'] ?? 'no') === 'yes', $lang->managesettings['lbl_usezip'], '', 'fa-file-zipper'); ?>
                                <?php ftxt('uploadspath', (string)($s['uploadspath'] ?? './uploads'), $lang->managesettings['lbl_uploadspath'], $lang->managesettings['tip_uploadspath'], 'text', 'fa-folder-open'); ?>
                                <?php ftxt('loadlimit', (string)($s['loadlimit'] ?? ''), $lang->managesettings['lbl_loadlimit'], $lang->managesettings['tip_loadlimit'], 'text', 'fa-gauge-high'); ?>
                            </div>

                            <?php fsec('fa-table-list', $lang->managesettings['sec_threads_listings']); ?>
                            <div class="ag-grid">
                                <?php fswitch('browsingthisthread', ($s['browsingthisthread'] ?? '1') === '1', $lang->managesettings['lbl_browsingthisthread'], '', 'fa-eye', '1', '0'); ?>
                                <?php fswitch('delayedthreadviews', ($s['delayedthreadviews'] ?? '1') === '1', $lang->managesettings['lbl_delayedthreadviews'], '', 'fa-clock-rotate-left', '1', '0'); ?>
                                <?php fswitch('showforumpagesbreadcrumb', ($s['showforumpagesbreadcrumb'] ?? '1') === '1', $lang->managesettings['lbl_showforumpagesbreadcrumb'], '', 'fa-route', '1', '0'); ?>
                                <?php fswitch('showownunapproved', ($s['showownunapproved'] ?? '1') === '1', $lang->managesettings['lbl_showownunapproved'], '', 'fa-user-check', '1', '0'); ?>
                                <?php ftxt('threadreadcut', (string)($s['threadreadcut'] ?? '7'), $lang->managesettings['lbl_threadreadcut'], $lang->managesettings['tip_threadreadcut'], 'number', 'fa-calendar-check'); ?>
                                <?php ftxt('ts_perpage', (string)($s['ts_perpage'] ?? '20'), $lang->managesettings['lbl_ts_perpage'], '', 'number', 'fa-list'); ?>
                                <?php ftxt('f_postsperpage', (string)($s['f_postsperpage'] ?? '10'), $lang->managesettings['lbl_f_postsperpage'], '', 'number', 'fa-align-left'); ?>
                                <?php ftxt('f_threadsperpage', (string)($s['f_threadsperpage'] ?? '20'), $lang->managesettings['lbl_f_threadsperpage'], '', 'number', 'fa-list-ol'); ?>
                                <?php ftxt('userpppoptions', (string)($s['userpppoptions'] ?? '5,10,15,20,25,30,40,50'), $lang->managesettings['lbl_userpppoptions'], $lang->managesettings['tip_userpppoptions'], 'text', 'fa-table-cells'); ?>
                                <?php ftxt('usertppoptions', (string)($s['usertppoptions'] ?? '10,15,20,25,30,40,50'), $lang->managesettings['lbl_usertppoptions'], $lang->managesettings['tip_usertppoptions'], 'text', 'fa-table-cells'); ?>
                            </div>

                            <?php fsec('fa-object-group', $lang->managesettings['sec_post_merge'], $lang->managesettings['sec_post_merge_hint']); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('postmergemins', (string)($s['postmergemins'] ?? '60'), $lang->managesettings['lbl_postmergemins'], $lang->managesettings['tip_postmergemins'], 'number', 'fa-clock'); ?>
                                <?php ftxt('postmergesep', (string)($s['postmergesep'] ?? '[hr]'), $lang->managesettings['lbl_postmergesep'], $lang->managesettings['tip_postmergesep'], 'text', 'fa-grip-lines'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_postmergefignore"><i class="fa-solid fa-comment-slash"></i><?= $lang->managesettings['lbl_postmergefignore'] ?></label>
                                    <?php $mergeFidsSelected = array_filter(explode(',', (string)($s['postmergefignore'] ?? '')), fn($v) => $v !== ''); ?>
                                    <input type="hidden" name="configoption[postmergefignore][]" value="">
                                    <select class="form-select ag-input" id="f_postmergefignore" name="configoption[postmergefignore][]" multiple size="6">
                                        <?php
                                        $fq = $db->sql_query_prepared("SELECT fid, name FROM forums ORDER BY name ASC");
                                        while ($fq && ($f = $db->fetch_array($fq))) {
                                            $sel = in_array((string)$f['fid'], $mergeFidsSelected, true) ? ' selected' : '';
                                            echo '<option value="' . (int)$f['fid'] . '"' . $sel . '>' . htmlspecialchars((string)$f['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <?= ags_hint($lang->managesettings['help_postmergefignore']) ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_postmergeuignore"><i class="fa-solid fa-users-slash"></i><?= $lang->managesettings['lbl_postmergeuignore'] ?></label>
                                    <?php $mergeGidsSelected = array_filter(explode(',', (string)($s['postmergeuignore'] ?? '6,7,8')), fn($v) => $v !== ''); ?>
                                    <input type="hidden" name="configoption[postmergeuignore][]" value="">
                                    <select class="form-select ag-input" id="f_postmergeuignore" name="configoption[postmergeuignore][]" multiple size="6">
                                        <?php
                                        $gq2 = $db->sql_query_prepared("SELECT gid, title FROM usergroups ORDER BY gid ASC");
                                        while ($gq2 && ($g2 = $db->fetch_array($gq2))) {
                                            $sel = in_array((string)$g2['gid'], $mergeGidsSelected, true) ? ' selected' : '';
                                            echo '<option value="' . (int)$g2['gid'] . '"' . $sel . '>' . htmlspecialchars(strip_tags((string)$g2['title'])) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <?= ags_hint($lang->managesettings['help_postmergeuignore']) ?>
                                </div>
                            </div>

                            <?php fsec('fa-ruler-horizontal', $lang->managesettings['sec_message_length']); ?>
                            <div class="ag-grid">
                                <?php ftxt('minmessagelength', (string)($s['minmessagelength'] ?? '5'), $lang->managesettings['lbl_minmessagelength'], $lang->managesettings['tip_minmessagelength'], 'number', 'fa-arrow-down-short-wide'); ?>
                                <?php ftxt('maxmessagelength', (string)($s['maxmessagelength'] ?? '65535'), $lang->managesettings['lbl_maxmessagelength'], $lang->managesettings['tip_maxmessagelength'], 'number', 'fa-arrow-up-wide-short'); ?>
                                <?php fswitch('mycodemessagelength', ($s['mycodemessagelength'] ?? '1') === '1', $lang->managesettings['lbl_mycodemessagelength'], $lang->managesettings['tip_mycodemessagelength'], 'fa-code', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── STAFF TEAM ───────────────────────────────────────────────── -->
                <div class="tab-pane fade t-purple" id="staff-team">
                    <form method="post" action="<?= AGS_SELF ?>" class="ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-user-shield', $lang->managesettings['pane_staff_team_title'], $lang->managesettings['pane_staff_team_sub'], ags_fmt((string)$lang->managesettings['staff_members_count'], count($staffarray)), 'fa-users'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-purple">
                                <i class="fa-solid fa-lightbulb"></i>
                                <div><?= $lang->managesettings['note_staff'] ?></div>
                            </div>

                            <datalist id="staffNames">
                                <?php foreach ($availableStaff as $st): ?>
                                <option value="<?= htmlspecialchars((string)$st['username']) ?>"><?= htmlspecialchars((string)$st['username']) ?> (<?= htmlspecialchars(strip_tags((string)($st['title'] ?? ''))) ?>)</option>
                                <?php endforeach; ?>
                            </datalist>

                            <div class="table-responsive">
                                <table class="ag-table staff-table">
                                    <thead>
                                        <tr>
                                            <th style="width:48px"><i class="fa-solid fa-hashtag"></i></th>
                                            <th><i class="fa-solid fa-user me-1"></i> <?= $lang->managesettings['staff_col_username'] ?></th>
                                            <th style="width:32%"><i class="fa-solid fa-id-badge me-1"></i> <?= $lang->managesettings['staff_col_userid'] ?></th>
                                            <th style="width:52px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staffarray)): ?>
                                            <tr><td colspan="4" class="ag-empty"><i class="fa-solid fa-user-slash"></i> <?= $lang->managesettings['staff_empty'] ?></td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($staffarray as $i => $st): ?>
                                        <tr>
                                            <td><span class="ag-num"><?= $i + 1 ?></span></td>
                                            <td><input type="text" name="staffnames[]" value="<?= htmlspecialchars($st['name']) ?>" class="form-control ag-input" list="staffNames" placeholder="<?= $lang->managesettings['staff_col_username'] ?>" aria-label="<?= $lang->managesettings['staff_col_username'] ?>"></td>
                                            <td><input type="text" name="staffids[]" value="<?= htmlspecialchars($st['id']) ?>" class="form-control ag-input" placeholder="<?= $lang->managesettings['staff_col_userid'] ?>" aria-label="<?= $lang->managesettings['staff_col_userid'] ?>" inputmode="numeric"></td>
                                            <td>
                                                <button type="button" class="ag-icon-btn" title="<?= $lang->managesettings['staff_remove_title'] ?>" aria-label="<?= $lang->managesettings['staff_remove'] ?>"
                                                        onclick="this.closest('tr').querySelectorAll('input').forEach(i=>i.value='')">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php for ($i = 0; $i < 3; $i++): ?>
                                        <tr class="row-new">
                                            <td><span class="ag-num is-new" title="<?= $lang->managesettings['staff_new_row'] ?>"><i class="fa-solid fa-plus"></i></span></td>
                                            <td><input type="text" name="staffnames[]" class="form-control ag-input" list="staffNames" placeholder="<?= $lang->managesettings['staff_new_username'] ?>" aria-label="<?= $lang->managesettings['staff_new_username'] ?>"></td>
                                            <td><input type="text" name="staffids[]" class="form-control ag-input" placeholder="<?= $lang->managesettings['staff_col_userid'] ?>" aria-label="<?= $lang->managesettings['staff_new_userid'] ?>" inputmode="numeric"></td>
                                            <td></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?= ags_hint($lang->managesettings['help_staff']) ?>

                            <div class="ag-toolbar">
                                <button type="button" class="btn btn-outline-secondary btn-sm ag-pill"
                                        onclick="window.open('<?= htmlspecialchars((string)$BASEURL) ?>/users.php#searchuser','finduser','toolbar=no,scrollbars=yes,width=800,height=600')">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> <?= $lang->managesettings['staff_find_user'] ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ag-pill" data-clear-rows
                                        onclick="this.closest('form').querySelectorAll('.row-new input').forEach(i=>i.value='')">
                                    <i class="fa-solid fa-eraser me-1"></i> <?= $lang->managesettings['staff_clear_rows'] ?>
                                </button>
                                <span class="ag-muted ms-auto"><i class="fa-solid fa-user-shield"></i> <?= count($availableStaff) ?> <?= $lang->managesettings['staff_eligible'] ?></span>
                            </div>
                        </div>
                        <?php savebar($lang->managesettings['save_staff'], 'save_staff'); ?>
                    </form>
                </div>

                <!-- ── FREELEECH ────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-red" id="freeleech-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_freeleech" value="1">
                        <?php pane_head('fa-gift', $lang->managesettings['pane_freeleech_title'], $lang->managesettings['pane_freeleech_sub'], $lang->managesettings['pane_freeleech_tag'], 'fa-bullhorn'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-orange">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <div><?= $lang->managesettings['note_freeleech'] ?></div>
                            </div>

                            <div class="ag-grid">
                                <?php fsel('system', ['freeleech' => $lang->managesettings['opt_promo_free'], 'silverleech' => $lang->managesettings['opt_promo_silver'], 'doubleupload' => $lang->managesettings['opt_promo_double']], (string)($__FLSTYPE ?? ''), $lang->managesettings['lbl_system'], '', 'fa-tags'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="startPicker"><i class="fa-solid fa-calendar-plus"></i><?= $lang->managesettings['lbl_fl_start'] ?></label>
                                    <div class="ag-affix">
                                        <input type="text" id="startPicker" class="form-control ag-input" name="configoption[start]"
                                               value="<?= ($__F_START ?? '') !== '0000-00-00 00:00:00' ? htmlspecialchars((string)($__F_START ?? '')) : '' ?>"
                                               placeholder="YYYY-MM-DD HH:MM:SS">
                                        <span><i class="fa-regular fa-calendar"></i></span>
                                    </div>
                                    <?= ags_hint($lang->managesettings['help_fl_start']) ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="endPicker"><i class="fa-solid fa-calendar-xmark"></i><?= $lang->managesettings['lbl_fl_end'] ?></label>
                                    <div class="ag-affix">
                                        <input type="text" id="endPicker" class="form-control ag-input" name="configoption[end]"
                                               value="<?= ($__F_END ?? '') !== '0000-00-00 00:00:00' ? htmlspecialchars((string)($__F_END ?? '')) : '' ?>"
                                               placeholder="YYYY-MM-DD HH:MM:SS">
                                        <span><i class="fa-regular fa-calendar"></i></span>
                                    </div>
                                    <?= ags_hint($lang->managesettings['help_fl_end']) ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar($lang->managesettings['save_promotion']); ?>
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
const AGS_LANG = <?= json_encode($agsJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/managesettings.js?ver=23"></script>

<?php stdfoot(); ?>