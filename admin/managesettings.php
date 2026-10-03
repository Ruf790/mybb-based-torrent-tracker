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
    global $settings;
    $alerts = [];

    if (($settings['SITEONLINE'] ?? 'yes') === 'no') {
        $alerts[] = [
            'type'    => 'warning',
            'icon'    => 'fa-power-off',
            'title'   => 'Site is offline',
            'message' => 'Maintenance mode is on — regular users cannot reach the site.',
            'action'  => '<a href="#main-settings" data-ag-tab="main-settings">Open site status</a>',
        ];
    }
    if (($settings['disableregs'] ?? '0') === '1') {
        $alerts[] = [
            'type'    => 'info',
            'icon'    => 'fa-user-slash',
            'title'   => 'Registrations disabled',
            'message' => 'New accounts cannot be created right now.',
            'action'  => '<a href="#registration-settings" data-ag-tab="registration-settings">Registration settings</a>',
        ];
    }
    if (empty($settings['mysql_host'] ?? '')) {
        $alerts[] = [
            'type'    => 'danger',
            'icon'    => 'fa-database',
            'title'   => 'Announce DB not configured',
            'message' => 'MySQL host for announce is empty — the tracker may not answer clients.',
            'action'  => '<a href="#announce-settings" data-ag-tab="announce-settings">Configure announce</a>',
        ];
    }
    $free_space = disk_free_space('/');
    if ($free_space !== false && $free_space < 1073741824) {
        $alerts[] = [
            'type'    => 'danger',
            'icon'    => 'fa-hard-drive',
            'title'   => 'Low disk space',
            'message' => 'Less than 1 GB of free disk space is left.',
            'action'  => '',
        ];
    }
    $backup_dir = TSDIR . '/admin/backup';
    if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
        $alerts[] = [
            'type'    => 'warning',
            'icon'    => 'fa-triangle-exclamation',
            'title'   => 'Backup directory issue',
            'message' => 'admin/backup is missing or not writable.',
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

    echo '<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100" aria-live="polite" aria-atomic="true">';
    foreach ($_SESSION['flash'] as $flash) {
        [$cls, $icon, $title] = match($flash['type']) {
            'success'        => ['text-bg-success', 'fa-circle-check', 'Saved'],
            'error','danger' => ['text-bg-danger',  'fa-circle-xmark', 'Error'],
            'warning'        => ['text-bg-warning', 'fa-triangle-exclamation', 'Warning'],
            default          => ['text-bg-info',    'fa-circle-info', 'Info'],
        };
        $msg = htmlspecialchars($flash['message']);
        echo "<div class='toast border-0 mb-2' role='alert' aria-live='assertive' aria-atomic='true'>
                <div class='toast-header {$cls}'>
                  <i class='fa-solid {$icon} me-2'></i>
                  <strong class='me-auto'>{$title}</strong><small>now</small>
                  <button type='button' class='btn-close' data-bs-dismiss='toast' aria-label='Close'></button>
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
const AGS_HINTS = [
    // Main
    'SITENAME'            => 'Your tracker name: shown in page titles, emails and the header',
    'BASEURL'             => 'Full site address, for example https://artcore-gangsta.eu. No trailing slash (/) at the end',
    'SITEEMAIL'           => 'Sender address for outgoing mail, for example contact@sitename.com',
    'contactemail'        => 'Addresses the Contact Us page delivers to. Separate several with commas: contact@site.com,john@mail.com',
    'slogan'              => 'Short line shown under the tracker name',
    'default_language'    => 'Language for guests and new accounts',
    'offline_message'     => 'What regular users see while the site is offline. Staff can still log in',
    'use_xmlhttprequest'  => 'AJAX features across the site: quick reply, inline editing, live search',
    'seourls'             => 'Friendly links like torrent-87.html. Needs the rewrite rules on the server',
    'gzipcompress'        => 'Compresses pages before sending them: faster pages. Turn off if the web server already compresses',
    'jumptopagemultipage' => 'Adds a "go to page" box to long paginations',
    'hitrun'              => 'Checks the user\'s ratio before a torrent can be downloaded (see the two fields below)',
    'maxloginattempts'    => 'IPs that exceed this limit are banned',
    'maxmultipagelinks'   => 'Page numbers shown around the current page in paginations',
    'wolcutoffmins'       => 'Minutes of inactivity before a user drops off "Who is online"',
    'hitrun_ratio'        => 'Users below this ratio can not download new torrents',
    'hitrun_gig'          => 'The ratio check starts only after this many GB downloaded',
    'announce_urls[]'     => 'Full URL to announce.php, for example https://site.com/announce.php. Written into every .torrent file',
    'torrent_dir'         => 'Folder with .torrent files. No trailing slash (/) at the end',
    'pic_base_url'        => 'Folder with site images. Add a trailing slash (/) at the end',
    'enableattachments'   => 'Off = no uploads to posts or comments',
    'maxattachments'      => 'Files per post or comment. 0 = disabled',
    'attachthumbnails'    => 'How attached images are shown inside posts',
    'attachthumbh'        => 'Thumbnail height in pixels',
    'attachthumbw'        => 'Thumbnail width in pixels',

    // Date & time
    'dateformat'          => 'PHP date() format for dates on the tracker and forum. d = day, M = month name, Y = year. Change only if you know the format',
    'timeformat'          => 'PHP date() format for times: H:i = 24 h, g:i a = 12 h',
    'regdateformat'       => 'Format of the registration date in profiles, threads and user details',
    'datetimesep'         => 'Text between date and time, for example ", " or " at "',
    'timezoneoffset'      => 'Default for guests and users who have not set their own timezone',
    'dstcorrection'       => 'If times are an hour off although the timezone is right, turn this on (daylight saving time)',

    // Cookies
    'cookiedomain'        => 'Leave blank for the current domain. Start with a dot to cover subdomains',
    'cookiepath'          => '/ = the whole site. Change only if the tracker runs in a subfolder',
    'cookieprefix'        => 'Added to cookie names. Useful when several sites share a domain',
    'cookiesecureflag'    => 'Cookies are sent over HTTPS only. Enable only on HTTPS',
    'cookiesamesiteflag'  => 'Helps against CSRF: cookies are not sent on requests from other sites',

    // Avatars
    'useravatar'          => 'Image path shown when a user has no avatar',
    'useravatardims'      => 'Width x height of the default avatar: 40x40 or 40|40',
    'maxavatardims'       => 'Largest width x height an avatar can have, for example 100x100',
    'avatarsize'          => 'In bytes: 102400 = 100 KB, 204800 = 200 KB',
    'avataruploadpath'    => 'Folder for uploaded avatars. Must be writable by the web server',
    'allowremoteavatars'  => 'Avatars by URL from other sites. Exposes your server IP',

    // Security
    'aggressivecheckip'   => 'Checks banned IPs on every announce too, not only on page views',
    'privatetrackerpatch' => 'Sets the private flag (no DHT, PEX or other trackers). This changes the info hash, so the uploader must re-download the .torrent to seed',

    // Email
    'mail_handler'        => 'PHP mail() works on most hosts. SMTP is more reliable and less likely to land in spam',
    'mail_logging'        => 'Keep a record of sent mail. "Log everything" also stores the message text',
    'mail_queue_limit'    => 'Messages sent per cron run',
    'mail_message_id'     => 'Disable on shared hosting with spam issues',
    'smtp_host'           => 'Mail server address, for example smtp.gmail.com',
    'smtp_port'           => '25 · 465 SSL · 587 TLS',
    'secure_smtp'         => 'Must match the port: 465 = SSL, 587 = TLS',
    'smtp_user'           => 'Usually the full email address',

    // Announce
    'nc'                  => 'Disables download and upload for peers that can not be reached. Helps to catch cheaters',
    'bannedclientdetect'  => 'Only clients from the Allowed clients list below can announce. Others are rejected',
    'checkconnectable'    => 'Detects whether each peer accepts incoming connections. Costs performance. Off = everybody is shown as connectable',
    'checkip'             => 'Before sending the peer list, the client IP must match the user\'s last IP stored in the users table',
    'announce_wait'       => 'Minimum seconds between two announces of one client (flood limit). 0 = disabled',
    'announce_interval'   => 'Seconds between announces sent to clients. 900 = 15 min. Higher = better performance',
    'max_rate'            => 'Bytes per second; above this the upload speed is checked as possible cheating. 2097152 = 2 MB/s',
    'mysql_host'          => 'Database server for announce.php, usually localhost',
    'mysql_db'            => 'Database name for announce.php',
    'mysql_user'          => 'Database user for announce.php',

    // KPS (bonus)
    'bonus'               => 'Users earn points by seeding, uploading, commenting… and spend them on the KPS page. Disabled, keep points = paused, balances are not reset',
    'kpsupload'           => 'Points for uploading a torrent. Taken back when the torrent is deleted. Usual: 10-50',
    'kpscomment'          => 'Points for a comment, forum post or new thread. Taken back when it is deleted. Usual: 1-5',
    'kpsthanks'           => 'Points for saying thanks on a torrent',
    'kpsrate'             => 'Points for the first rating of a torrent or a forum thread',
    'kpspoll'             => 'Points for voting in a poll',
    'kpsmaxpoint'         => 'Balance cap: above it the user can only give points away as a gift',
    'kpsinvite'           => 'KPS shop: buy invites with points',
    'kpstitle'            => 'KPS shop: buy a custom title under the username',
    'kpsvip'              => 'KPS shop: buy VIP status for a limited time',
    'kpsgift'             => 'KPS shop: give points to another user',
    'kpswarning'          => 'KPS shop: remove an active warning',
    'kpsratiofix'         => 'KPS shop: fix the ratio of a single torrent',
    'bdayreward'          => 'Free, silver or double upload for the user on their birthday',
    'bdayrewardtype'      => 'Free leech = no download counted, upload only. Silver = 50% of the download counted. Double upload = upload counted twice',

    // Cleanup
    'max_dead_torrent_time'   => 'Torrents with no activity for this many days are hidden from Browse',
    'promote_gig_limit'       => 'User → Power User after uploading this many GB. 0 = disabled',
    'promote_min_ratio'       => 'Ratio a User needs to be promoted to Power User, for example 1.05',
    'promote_min_reg_days'    => 'Account must be at least this many days old to be promoted',
    'demote_min_ratio'        => 'Power Users below this ratio are demoted back to User',
    'referrergift'            => 'GB of upload the inviter gets when the invited user reaches Power User',
    'leechwarn_min_ratio'     => 'Users below this ratio get a leech warning',
    'leechwarn_gig_limit'     => 'Leech warning only after this many GB downloaded',
    'leechwarn_length'        => 'Weeks to raise the ratio before the account is banned',
    'leechwarn_remove_ratio'  => 'The leech warning is removed once the ratio reaches this',
    'ban_user_limit'          => 'An account is banned automatically after this many warnings (H&R counts too)',

    // Registration
    'regtype'                 => 'Invite only = sign up with an invite code. Email verification = account works after the confirmation link',
    'disableregs'             => 'On = nobody can sign up',
    'maxusers'                => 'Registration closes when this many accounts exist. 0 = unlimited',
    'minnamelength'           => 'Shortest allowed username',
    'maxnamelength'           => 'Longest allowed username',
    'minpasswordlength'       => 'Shortest allowed password. 8 or more is recommended',
    'maxpasswordlength'       => 'Longest allowed password',
    'requirecomplexpasswords' => 'Require mixed characters',
    'failedlogincount'        => 'Failed logins before the login form is locked for a while. 0 = disabled',
    'failedlogintext'         => 'Tells the user how many attempts are left',
    '_d_usergroup'            => 'Group new accounts start in',
    'invite_count'            => 'Invites every new account starts with. 0 = none',
    'autogigsignup'           => 'Free upload every new account starts with, in GB. 0 = none',
    'autosbsignup'            => 'Bonus points every new account starts with. 0 = none',
    'betweenregstime'         => 'Length of the anti-flood window for sign-ups from one IP',
    'maxregsbetweentime'      => 'Sign-ups allowed from one IP within the window',

    // Forum / legacy
    'defaultlanguage'          => 'Language for guests and members who have not chosen one',
    'shoutboxcharset'          => 'Keep UTF-8. Change only if AJAX parts (shoutbox, polls) show broken characters',
    'enablepms'                => 'Private messages between users. System PMs (exams, H&R) need this on',
    'usezip'                   => 'Download a .zip with the .torrent and a short info file inside, instead of the bare .torrent',
    'uploadspath'              => 'Folder for attachments. Must be writable by the web server',
    'loadlimit'                => 'Server load above which users get a \'too busy\' page. Works only on Linux/Unix: on Windows the load can not be read. Blank = disabled',
    'browsingthisthread'       => 'Shows who is reading a thread under the posts. One extra query per page',
    'delayedthreadviews'       => 'Views are counted by cron instead of on every page view. Less load',
    'showforumpagesbreadcrumb' => 'Page numbers next to the forum name in the breadcrumb',
    'showownunapproved'        => 'Users see their own posts that wait for moderation',
    'threadreadcut'            => 'Older threads always show as read',
    'ts_perpage'               => 'Items per page on every page that uses the pager (Browse and others)',
    'f_postsperpage'           => 'Default posts per page in a thread',
    'f_threadsperpage'         => 'Default threads per page in a forum',
    'userpppoptions'           => 'Choices users get in their settings, comma-separated',
    'usertppoptions'           => 'Choices users get in their settings, comma-separated',
    'postmergemins'            => 'Two posts in a row from the same user within this time are merged. 0 = disabled',
    'postmergesep'             => 'Inserted between merged messages',
    'minmessagelength'         => 'Shortest allowed post or comment, in characters',
    'maxmessagelength'         => '0 = column max. TEXT holds 65535 — use MEDIUMTEXT for more',
    'mycodemessagelength'      => 'Off = tags stripped before the check',

    // Freeleech
    'system'                   => 'Applies to ALL torrents between the dates below: Free = no download counted, Silver = 50%, Double = upload ×2',
];

function ags_tip(string $name, string $tip): string {
    return $tip !== '' ? $tip : (AGS_HINTS[$name] ?? '');
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
       . '<span class="ag-state"><span class="on"><i class="fa-solid fa-check"></i> On</span><span class="off">Off</span></span>'
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

function savebar(string $label = 'Save changes', string $name = '', string $note = 'Every change is written to the settings history'): void {
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
    flash_message("Security token expired — reload the page and try again.", "danger");
    admin_redirect(AGS_SELF);
}

// ── POST handlers (PRG: каждый заканчивается redirect) ─────────────────────────
match(true) {

    isset($_POST['save_kps']) => (function(): void {
        $keys = ['bonus','kpsseed','kpsupload','kpscomment','kpsthanks','kpsrate','kpspoll',
                 'kpsmaxpoint','kpsinvite','kpstitle','kpsvip','kpsgift','kpswarning','kpsratiofix',
                 'bdayreward','bdayrewardtype'];
        save_and_log(array_intersect_key($_POST['configoption'] ?? [], array_flip($keys)), get_settings_values($keys));
        flash_message("KPS settings saved successfully!", "success");
        admin_redirect(AGS_SELF . "#kps-settings");
    })(),

    isset($_POST['save_user_management']) => (function(): void {
        $keys = ['max_dead_torrent_time','promote_gig_limit','promote_min_ratio',
                 'promote_min_reg_days','demote_min_ratio','referrergift','leechwarn_min_ratio',
                 'leechwarn_gig_limit','leechwarn_length','leechwarn_remove_ratio','ban_user_limit'];
        save_and_log(array_intersect_key($_POST['configoption'] ?? [], array_flip($keys)), get_settings_values($keys));
        flash_message("Cleanup settings saved successfully!", "success");
        admin_redirect(AGS_SELF . "#user-management-settings");
    })(),

    isset($_POST['save_registration']) => (function(): void {
        $keys = ['regtype','minnamelength','maxnamelength',
                 'minpasswordlength','maxpasswordlength','requirecomplexpasswords','failedlogincount',
                 'failedlogintext','disableregs','maxusers',
                 '_d_usergroup','invite_count','autogigsignup','autosbsignup',
                 'betweenregstime','maxregsbetweentime'];
        save_and_log(array_intersect_key($_POST['configoption'] ?? [], array_flip($keys)), get_settings_values($keys));
        flash_message("Registration settings saved successfully!", "success");
        admin_redirect(AGS_SELF . "#registration-settings");
    })(),

    isset($_POST['save_forum_legacy']) => (function(): void {
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
        flash_message("Forum / Legacy settings saved successfully!", "success");
        admin_redirect(AGS_SELF . "#forum-legacy-settings");
    })(),

    isset($_POST['save_announce']) => (function(): void {
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
        flash_message("Announce settings saved successfully!", "success");
        admin_redirect(AGS_SELF . "#announce-settings");
    })(),

    isset($_POST['save_freeleech']) => (function(): void {
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
            flash_message("Freeleech settings saved successfully!", "success");
            admin_redirect(AGS_SELF . "?saved=freeleech#freeleech-settings");
        }
        flash_message("Error: unable to write FreeLeech cache!", "danger");
        admin_redirect(AGS_SELF . "#freeleech-settings");
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

        $old_staff = file_exists(CONFIG_DIR . '/STAFFTEAM') ? (string)file_get_contents(CONFIG_DIR . '/STAFFTEAM') : 'empty';

        if (!empty($errors)) {
            flash_message("Errors: " . implode(', ', $errors), "danger");
        } elseif (file_put_contents(CONFIG_DIR . '/STAFFTEAM', implode(',', $entries), LOCK_EX) === false) {
            flash_message("Failed to write STAFFTEAM config file!", "danger");
        } else {
            log_settings_change((int)$CURUSER['id'], (string)$CURUSER['username'], 'update', 'staff_team', $old_staff, implode(',', $entries));
            flash_message("Staff team saved successfully!", "success");
        }
        admin_redirect(AGS_SELF . "#staff-team");
    })(),

    $_SERVER['REQUEST_METHOD'] === 'POST' => (function() use ($db): void {
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
                    throw new \Exception("Failed to update '{$name}'");

                $old_value = $old_values[$name] ?? null;
                if ($old_value !== $value) {
                    log_settings_change((int)$CURUSER['id'], (string)$CURUSER['username'], 'update', (string)$name, $old_value, (string)$value);
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
$ban_user_limit         = $settings['ban_user_limit']         ?? '3';

$siteOnline          = ($settings['SITEONLINE'] ?? 'yes') === 'yes';
$offlineMinutesValue = $settings['offline_minutes'] ?? '';
$isUnlimited         = !$siteOnline && $offlineMinutesValue === 'unlimited';
$durationMinutes     = 30;
$timeRemaining       = '';
if (!$siteOnline) {
    if ($isUnlimited) {
        $timeRemaining = '<span class="ag-remain t-purple"><i class="fa-solid fa-infinity"></i>Unlimited — must be switched back on manually</span>';
    } elseif (is_numeric($offlineMinutesValue) && (int)$offlineMinutesValue > time()) {
        $rem             = (int)ceil(((int)$offlineMinutesValue - time()) / 60);
        $h               = intdiv($rem, 60); $m = $rem % 60;
        $durationMinutes = max(1, $rem);
        $timeRemaining   = '<span class="ag-remain t-orange"><i class="fa-solid fa-hourglass-half"></i>' . ($h > 0 ? "{$h}h " : '') . "{$m}m remaining</span>";
    } else {
        $durationMinutes = 30;
        $timeRemaining   = '<span class="ag-remain t-red"><i class="fa-solid fa-triangle-exclamation"></i>Time expired — should auto-enable</span>';
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

stdhead();
?>
<link href="<?= $BASEURL ?>/include/templates/default/style/errorss.css" rel="stylesheet">
<link href="<?= $BASEURL ?>/admin/templates/managesettings.css?ver=7" rel="stylesheet">
<link href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css" rel="stylesheet">
<?php flash_message(); ?>
<title><?= htmlspecialchars((string)$SITENAME) ?> Admin Panel</title>

<div class="ag-settings settings-container">

    <!-- ====== HEADER ====== -->
    <header class="ag-head t-blue">
        <span class="ag-sq ag-sq-lg"><i class="fa-solid fa-sliders"></i></span>
        <div class="ag-head-title">
            <h1>Settings Panel <span class="ag-ver">v<?= B_VERSION ?></span></h1>
            <p><i class="fa-solid fa-server"></i> Global configuration for <?= htmlspecialchars((string)($s['SITENAME'] ?? 'the tracker')) ?></p>
        </div>
        <div class="ag-head-actions">
            <span class="ag-status <?= $siteOnline ? 'is-online t-green' : 'is-offline t-red' ?>">
                <span class="dot"></span>
                <i class="fa-solid <?= $siteOnline ? 'fa-globe' : 'fa-screwdriver-wrench' ?>"></i>
                <?= $siteOnline ? 'Site online' : 'Maintenance' ?>
            </span>
            <a href="settings_history.php" class="btn btn-outline-secondary ag-pill">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> History
            </a>
            <button class="btn btn-primary ag-pill" id="globalSaveBtn" type="button" title="Save the open tab (Ctrl+S)">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save tab
            </button>
        </div>
    </header>

    <!-- ====== KPI ====== -->
    <section class="ag-kpis" aria-label="Tracker statistics">
        <div class="ag-kpi t-blue">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-users"></i></span>
                <span class="ag-kpi-label">Users</span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['users_total'] ?? 0)) ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-user-plus"></i>+<?= number_format((int)($ds['users_today'] ?? 0)) ?> today</span>
                <span><i class="fa-solid fa-signal"></i><?= number_format((int)($ds['users_active'] ?? 0)) ?> active 24h</span>
            </div>
        </div>

        <div class="ag-kpi t-purple">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-magnet"></i></span>
                <span class="ag-kpi-label">Torrents</span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['torrents_total'] ?? 0)) ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-cloud-arrow-up"></i>+<?= number_format((int)($ds['torrents_today'] ?? 0)) ?> today</span>
                <span><i class="fa-solid fa-seedling"></i><?= $pct((int)($ds['torrents_active'] ?? 0), (int)($ds['torrents_total'] ?? 0)) ?>% seeded</span>
            </div>
        </div>

        <div class="ag-kpi t-cyan">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-network-wired"></i></span>
                <span class="ag-kpi-label">Peers</span>
                <span class="ag-kpi-live" title="Users announced in the last 5 minutes"><i class="fa-solid fa-circle"></i><?= number_format((int)($ds['active_peers'] ?? 0)) ?> live</span>
            </div>
            <div class="ag-kpi-value"><?= number_format((int)($ds['peers_total'] ?? 0)) ?></div>
            <div class="ag-split" role="img" aria-label="<?= $seedPct ?>% seeders">
                <span style="width:<?= $seedPct ?>%"></span>
            </div>
            <div class="ag-kpi-meta">
                <span class="t-green"><i class="fa-solid fa-arrow-up"></i><?= number_format((int)($ds['seeders_total'] ?? 0)) ?> seeders</span>
                <span class="t-red"><i class="fa-solid fa-arrow-down"></i><?= number_format((int)($ds['leechers_total'] ?? 0)) ?> leechers</span>
            </div>
        </div>

        <div class="ag-kpi t-orange">
            <div class="ag-kpi-top">
                <span class="ag-sq"><i class="fa-solid fa-hard-drive"></i></span>
                <span class="ag-kpi-label">Shared data</span>
            </div>
            <div class="ag-kpi-value"><?= $ds['total_size'] ?? '0 B' ?></div>
            <div class="ag-kpi-meta">
                <span><i class="fa-solid fa-skull"></i><?= number_format((int)($ds['dead_torrents'] ?? 0)) ?> dead</span>
                <span><i class="fa-solid fa-folder-tree"></i><?= number_format((int)($ds['categories_active'] ?? 0)) ?> categories</span>
            </div>
        </div>
    </section>

    <!-- ====== ALERTS ====== -->
    <?php if (!empty($critical_alerts)): ?>
    <section class="ag-alerts" aria-label="Warnings">
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
        <nav class="ag-nav settings-sidebar" aria-label="Settings sections">
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-globe"></i> Site</div>
                <ul class="sidebar-nav">
                    <?php nav_item('#main-settings', 'fa-gear', 't-blue', 'Main settings',
                        count($critical_alerts) > 0 ? '<span class="ag-count is-alert"><i class="fa-solid fa-bell"></i> ' . count($critical_alerts) . '</span>' : '', true, true); ?>
                    <?php nav_item('#date-time', 'fa-clock', 't-teal', 'Date &amp; time'); ?>
                    <?php nav_item('#cookie-settings', 'fa-cookie-bite', 't-yellow', 'Cookies'); ?>
                    <?php nav_item('#avatar-settings', 'fa-circle-user', 't-pink', 'Avatars'); ?>
                    <?php nav_item('#security-settings', 'fa-shield-halved', 't-red', 'Security'); ?>
                    <?php nav_item('#email-settings', 'fa-envelope', 't-orange', 'Email'); ?>
                    <?php nav_item('#forum-legacy-settings', 'fa-comments', 't-indigo', 'Forum / Legacy'); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-magnet"></i> Tracker</div>
                <ul class="sidebar-nav">
                    <?php nav_item('#tracker-settings', 'fa-server', 't-purple', 'Tracker'); ?>
                    <?php nav_item('#announce-settings', 'fa-tower-broadcast', 't-cyan', 'Announce', '<span class="ag-count">core</span>'); ?>
                    <?php nav_item('#freeleech-settings', 'fa-gift', 't-red', 'Freeleech'); ?>
                    <?php nav_item('index.php?act=torrents_promo', 'fa-shuffle', 't-cyan', 'Promo rules', '', false); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-users"></i> Members</div>
                <ul class="sidebar-nav">
                    <?php nav_item('#registration-settings', 'fa-user-plus', 't-green', 'Registration'); ?>
                    <?php nav_item('#user-management-settings', 'fa-users-gear', 't-teal', 'Cleanup'); ?>
                    <?php nav_item('#kps-settings', 'fa-coins', 't-yellow', 'KPS bonus'); ?>
                    <?php nav_item('#staff-team', 'fa-user-shield', 't-purple', 'Staff team', '<span class="ag-count">' . count($staffarray) . '</span>'); ?>
                    <?php nav_item('index.php?act=seedbonus_settings', 'fa-seedling', 't-green', 'Seedbonus', '', false); ?>
                </ul>
            </div>
            <div class="ag-nav-group">
                <div class="ag-nav-title"><i class="fa-solid fa-toolbox"></i> Tools</div>
                <ul class="sidebar-nav">
                    <?php nav_item('settings_history.php', 'fa-clock-rotate-left', 't-blue', 'Change history', '', false); ?>
                    <?php nav_item('index.php?act=cronjobs', 'fa-calendar-check', 't-indigo', 'Cronjobs', '', false); ?>
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
                        <?php pane_head('fa-gear', 'Main settings', 'Name, addresses, SEO and maintenance mode'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-id-card', 'Basic information'); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('SITENAME', (string)($s['SITENAME'] ?? ''), 'Tracker name', '', 'text', 'fa-signature'); ?>
                                <?php ftxt('BASEURL', (string)($s['BASEURL'] ?? ''), 'Base URL', 'No trailing slash', 'text', 'fa-link'); ?>
                                <?php ftxt('SITEEMAIL', (string)($s['SITEEMAIL'] ?? ''), 'Site email', 'Sender address for outgoing mail', 'email', 'fa-at'); ?>
                                <?php ftxt('contactemail', (string)($s['contactemail'] ?? ''), 'Contact email(s)', 'Separate with commas', 'text', 'fa-address-book'); ?>
                                <?php ftxt('slogan', (string)($s['slogan'] ?? ''), 'Tracker slogan', '', 'text', 'fa-quote-right'); ?>
                                <?php fsel('default_language', [
                                    'english' => '🇬🇧 English', 'russian' => '🇷🇺 Russian', 'ukrainian' => '🇺🇦 Ukrainian',
                                    'german' => '🇩🇪 German', 'french' => '🇫🇷 French', 'spanish' => '🇪🇸 Spanish',
                                ], (string)($s['default_language'] ?? 'english'), 'Default language', '', 'fa-language'); ?>
                            </div>

                            <?php fsec('fa-magnifying-glass-chart', 'SEO', 'What search engines see in the page head'); ?>
                            <div class="ag-grid ag-grid-2">
                                <div class="ag-field">
                                    <label class="ag-label" for="f_metakeywords"><i class="fa-solid fa-tags"></i>Meta keywords</label>
                                    <textarea class="form-control ag-input" id="f_metakeywords" name="configoption[metakeywords]" rows="3"><?= htmlspecialchars((string)($s['metakeywords'] ?? '')) ?></textarea>
                                    <?= ags_hint('Words that describe the site, separated with commas. Search engines use them very little today') ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_metadesc"><i class="fa-solid fa-align-left"></i>Meta description</label>
                                    <textarea class="form-control ag-input" id="f_metadesc" name="configoption[metadesc]" rows="3"><?= htmlspecialchars((string)($s['metadesc'] ?? '')) ?></textarea>
                                    <?= ags_hint('Short description of the site for search engines: one or two sentences, up to ~160 characters') ?>
                                </div>
                            </div>

                            <?php fsec('fa-power-off', 'Site status', 'Maintenance mode closes the site for regular users'); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php fswitch('SITEONLINE', $siteOnline, 'Site online', 'Turn off to show the maintenance message', 'fa-power-off'); ?>
                                <?php ftxt('offline_message', (string)($s['offline_message'] ?? 'Site is currently under maintenance. Please check back later.'), 'Maintenance message', '', 'text', 'fa-person-digging'); ?>
                            </div>

                            <div id="offlineDurationGroup" class="ag-offline t-red" style="display:<?= !$siteOnline ? 'block' : 'none' ?>">
                                <?php if ($timeRemaining): ?>
                                    <div class="ag-offline-state">
                                        <?= $timeRemaining ?>
                                        <?php if (!$isUnlimited && is_numeric($offlineMinutesValue)): ?>
                                            <span class="ag-muted"><i class="fa-regular fa-calendar"></i> back at <?= date('Y-m-d H:i', (int)$offlineMinutesValue) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ag-radio-row">
                                    <label class="ag-radio t-orange" for="limitedMode">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" id="limitedMode" value="limited" <?= !$isUnlimited ? 'checked' : '' ?>>
                                        <i class="fa-solid fa-hourglass-half"></i> Limited time
                                    </label>
                                    <label class="ag-radio t-purple" for="unlimitedMode">
                                        <input class="form-check-input" type="radio" name="offline_mode_type" id="unlimitedMode" value="unlimited" <?= $isUnlimited ? 'checked' : '' ?>>
                                        <i class="fa-solid fa-infinity"></i> Until I turn it back on
                                    </label>
                                </div>
                                <div id="timeLimitGroup" class="ag-field" style="display:<?= !$isUnlimited ? 'flex' : 'none' ?>;max-width:280px">
                                    <label class="ag-label" for="f_offline_minutes"><i class="fa-solid fa-stopwatch"></i>Duration</label>
                                    <div class="ag-affix">
                                        <input type="number" min="1" max="1440" class="form-control ag-input" id="f_offline_minutes"
                                               name="offline_minutes_input" value="<?= !$isUnlimited ? $durationMinutes : 30 ?>">
                                        <span>min · max 24h</span>
                                    </div>
                                    <?= ags_hint('The site comes back online by itself after this time') ?>
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
                        <?php pane_head('fa-server', 'Tracker', 'Core behaviour, limits, paths and attachments', 'Config', 'fa-wrench'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-toggle-on', 'Features'); ?>
                            <div class="ag-grid">
                                <?php fswitch('use_xmlhttprequest', ($s['use_xmlhttprequest'] ?? '1') === '1', 'Use XMLHttpRequest', 'AJAX features across the site', 'fa-code', '1', '0'); ?>
                                <?php fswitch('seourls', ($s['seourls'] ?? 'no') === 'yes', 'SEO URLs', '', 'fa-magnifying-glass'); ?>
                                <?php fswitch('gzipcompress', ($s['gzipcompress'] ?? 'yes') === 'yes', 'GZIP compression', 'Compresses pages for faster delivery', 'fa-file-zipper'); ?>
                                <?php fswitch('jumptopagemultipage', ($s['jumptopagemultipage'] ?? '1') === '1', 'Jump-to-page in pagination', '', 'fa-arrow-right-to-bracket', '1', '0'); ?>
                                <?php fswitch('hitrun', ($s['hitrun'] ?? 'yes') === 'yes', 'Hit & Run system', '', 'fa-person-running'); ?>
                            </div>

                            <?php fsec('fa-ruler-combined', 'Limits'); ?>
                            <div class="ag-grid">
                                <?php ftxt('maxloginattempts', (string)($s['maxloginattempts'] ?? '5'), 'Max. login attempts', 'IPs over this limit get banned', 'number', 'fa-user-lock'); ?>
                                <?php ftxt('maxmultipagelinks', (string)($s['maxmultipagelinks'] ?? '5'), 'Max. pagination links', '', 'number', 'fa-ellipsis'); ?>
                                <?php ftxt('wolcutoffmins', (string)($s['wolcutoffmins'] ?? '15'), 'Online cut-off (min)', 'Minutes before a user is shown offline', 'number', 'fa-user-clock'); ?>
                                <?php ftxt('hitrun_ratio', (string)($s['hitrun_ratio'] ?? '0.5'), 'Min. ratio for H&R', '', 'text', 'fa-scale-balanced'); ?>
                                <?php ftxt('hitrun_gig', (string)($s['hitrun_gig'] ?? '5'), 'Min. GB for H&R', '', 'number', 'fa-database'); ?>
                            </div>

                            <?php fsec('fa-folder-tree', 'URLs & paths'); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('announce_urls[]', (string)$announce_url, 'Announce URL', 'Full URL to announce.php', 'text', 'fa-tower-broadcast'); ?>
                                <?php ftxt('torrent_dir', (string)($s['torrent_dir'] ?? ''), 'Torrent directory', 'No trailing slash', 'text', 'fa-folder'); ?>
                                <?php ftxt('pic_base_url', (string)($s['pic_base_url'] ?? ''), 'Image directory', 'With trailing slash', 'text', 'fa-images'); ?>
                            </div>

                            <?php fsec('fa-paperclip', 'Attachments'); ?>
                            <div class="ag-grid">
                                <?php fswitch('enableattachments', ($s['enableattachments'] ?? '1') === '1', 'Enable attachments', 'Off = no uploads to posts or comments', 'fa-paperclip', '1', '0'); ?>
                                <?php ftxt('maxattachments', (string)($s['maxattachments'] ?? '5'), 'Max. per post', '0 = disabled', 'number', 'fa-layer-group'); ?>
                                <?php fsel('attachthumbnails', ['yes' => 'Thumbnail', 'no' => 'Full size image', 'download' => 'Download link'], (string)($s['attachthumbnails'] ?? 'yes'), 'Show images as', '', 'fa-image'); ?>
                                <?php ftxt('attachthumbh', (string)($s['attachthumbh'] ?? '96'), 'Thumbnail max height', 'In pixels', 'number', 'fa-arrows-up-down'); ?>
                                <?php ftxt('attachthumbw', (string)($s['attachthumbw'] ?? '96'), 'Thumbnail max width', 'In pixels', 'number', 'fa-arrows-left-right'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── DATE & TIME ──────────────────────────────────────────────── -->
                <div class="tab-pane fade t-teal" id="date-time">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-clock', 'Date & time', 'Formats and default timezone for guests and new members'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-calendar-days', 'Formats', 'PHP date() syntax'); ?>
                            <div class="ag-grid">
                                <?php ftxt('dateformat', (string)($s['dateformat'] ?? 'd M Y'), 'Date format', 'Example: d M Y → ' . date('d M Y'), 'text', 'fa-calendar-day'); ?>
                                <?php ftxt('timeformat', (string)($s['timeformat'] ?? 'H:i'), 'Time format', 'Example: H:i → ' . date('H:i'), 'text', 'fa-clock'); ?>
                                <?php ftxt('regdateformat', (string)($s['regdateformat'] ?? 'd M Y'), 'Registered date format', '', 'text', 'fa-calendar-check'); ?>
                                <?php ftxt('datetimesep', (string)($s['datetimesep'] ?? ', '), 'Date/time separator', '', 'text', 'fa-grip-lines-vertical'); ?>
                            </div>

                            <?php fsec('fa-earth-europe', 'Timezone'); ?>
                            <div class="ag-grid">
                                <?php
                                $tzOpts = [];
                                foreach (['-12','-11','-10','-9','-8','-7','-6','-5','-4','-3.5','-3','-2','-1','0','+1','+2','+3','+3.5','+4','+4.5','+5','+5.5','+5.75','+6','+7','+8','+9','+9.5','+10','+10.5','+11','+12'] as $tz) {
                                    $tzOpts[$tz] = 'GMT ' . $tz;
                                }
                                fsel('timezoneoffset', $tzOpts, (string)($s['timezoneoffset'] ?? '0'), 'Default timezone offset', '', 'fa-earth-europe');
                                ?>
                                <?php fswitch('dstcorrection', ($s['dstcorrection'] ?? '0') === '1', 'Daylight saving time', '', 'fa-sun', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── COOKIES ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-yellow" id="cookie-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-cookie-bite', 'Cookies', 'Scope and security flags of the login cookie'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-globe', 'Scope'); ?>
                            <div class="ag-grid">
                                <?php ftxt('cookiedomain', (string)($s['cookiedomain'] ?? ''), 'Cookie domain', 'Start with a dot to cover subdomains', 'text', 'fa-globe'); ?>
                                <?php ftxt('cookiepath', (string)($s['cookiepath'] ?? '/'), 'Cookie path', '', 'text', 'fa-folder-open'); ?>
                                <?php ftxt('cookieprefix', (string)($s['cookieprefix'] ?? ''), 'Cookie prefix', '', 'text', 'fa-font'); ?>
                            </div>

                            <?php fsec('fa-lock', 'Security flags'); ?>
                            <div class="ag-grid">
                                <?php fswitch('cookiesecureflag', ($s['cookiesecureflag'] ?? '0') === '1', 'Secure flag', 'Enable only on HTTPS', 'fa-lock', '1', '0'); ?>
                                <?php fswitch('cookiesamesiteflag', ($s['cookiesamesiteflag'] ?? '0') === '1', 'SameSite flag', 'Helps against CSRF', 'fa-shield-halved', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── AVATARS ──────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-pink" id="avatar-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-circle-user', 'Avatars', 'Defaults, size limits and remote avatars'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-user', 'Default avatar'); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('useravatar', (string)($s['useravatar'] ?? ''), 'Default avatar', 'Shown when a user has none', 'text', 'fa-image-portrait'); ?>
                                <?php ftxt('useravatardims', (string)($s['useravatardims'] ?? '40x40'), 'Default dimensions', '40x40 or 40|40', 'text', 'fa-crop-simple'); ?>
                            </div>

                            <?php fsec('fa-upload', 'Uploads'); ?>
                            <div class="ag-grid">
                                <?php ftxt('maxavatardims', (string)($s['maxavatardims'] ?? '100x100'), 'Max. dimensions', '', 'text', 'fa-expand'); ?>
                                <?php ftxt('avatarsize', (string)($s['avatarsize'] ?? '102400'), 'Max. size (bytes)', '102400 = 100 KB', 'number', 'fa-weight-hanging'); ?>
                                <?php ftxt('avataruploadpath', (string)($s['avataruploadpath'] ?? ''), 'Upload path', '', 'text', 'fa-folder-open'); ?>
                                <?php fswitch('allowremoteavatars', ($s['allowremoteavatars'] ?? '0') === '1', 'Remote avatars', 'Exposes your server IP', 'fa-cloud-arrow-down', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── SECURITY ─────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-red" id="security-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-shield-halved', 'Security', 'IP checks and private tracker protection'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-grid ag-grid-2">
                                <?php fswitch('aggressivecheckip', ($s['aggressivecheckip'] ?? 'no') === 'yes', 'Aggressive IP ban', 'Also blocks announces from banned IPs', 'fa-ban'); ?>
                                <?php fswitch('privatetrackerpatch', ($s['privatetrackerpatch'] ?? 'no') === 'yes', 'Private tracker patch', 'Sets the private flag on uploaded torrents', 'fa-user-secret'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── EMAIL ────────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-orange" id="email-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-envelope', 'Email', 'Delivery method, queue and logging'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-paper-plane', 'Delivery'); ?>
                            <div class="ag-grid">
                                <?php fsel('mail_handler', ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'sendmail' => 'Sendmail'], (string)($s['mail_handler'] ?? 'mail'), 'Mail handler', 'SMTP fields appear after saving', 'fa-envelopes-bulk'); ?>
                                <?php fsel('mail_logging', ['0' => 'None', '1' => 'Log without content', '2' => 'Log everything'], (string)($s['mail_logging'] ?? '0'), 'Mail logging', '', 'fa-clipboard-list'); ?>
                                <?php ftxt('mail_queue_limit', (string)($s['mail_queue_limit'] ?? '50'), 'Queue batch size', 'Messages per cron run', 'number', 'fa-inbox'); ?>
                                <?php fswitch('mail_message_id', ($s['mail_message_id'] ?? '1') === '1', 'Message-ID header', 'Disable on shared hosting with spam issues', 'fa-fingerprint', '1', '0'); ?>
                            </div>

                            <?php if (($s['mail_handler'] ?? 'mail') === 'smtp'): ?>
                            <?php fsec('fa-server', 'SMTP'); ?>
                            <div class="ag-grid">
                                <?php ftxt('smtp_host', (string)($s['smtp_host'] ?? ''), 'SMTP host', '', 'text', 'fa-server'); ?>
                                <?php ftxt('smtp_port', (string)($s['smtp_port'] ?? '587'), 'SMTP port', '25 · 465 SSL · 587 TLS', 'number', 'fa-plug'); ?>
                                <?php fsel('secure_smtp', ['0' => 'No encryption', '1' => 'SSL', '2' => 'TLS'], (string)($s['secure_smtp'] ?? '0'), 'Encryption', '', 'fa-lock'); ?>
                                <?php ftxt('smtp_user', (string)($s['smtp_user'] ?? ''), 'SMTP username', '', 'text', 'fa-user'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_smtp_pass"><i class="fa-solid fa-key"></i>SMTP password</label>
                                    <input type="password" class="form-control ag-input" id="f_smtp_pass" name="configoption[smtp_pass]" autocomplete="new-password"
                                           placeholder="<?= ($s['smtp_pass'] ?? '') !== '' ? '•••••••• (set)' : 'Not set' ?>">
                                    <?= ags_hint('Leave blank to keep the current password') ?>
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
                        <?php pane_head('fa-tower-broadcast', 'Announce', 'How announce.php talks to BitTorrent clients', 'Tracker core', 'fa-microchip'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-cyan">
                                <i class="fa-solid fa-rotate"></i>
                                <div>Saving rewrites <code>include/config_announce.php</code> — clients pick up the new values on their next announce.</div>
                            </div>

                            <?php fsec('fa-filter', 'Client checks'); ?>
                            <div class="ag-grid">
                                <?php fswitch('nc', (string)$nc === 'yes', 'Block non-connectable', 'Disables DL/UL for peers that cannot be reached', 'fa-plug-circle-xmark'); ?>
                                <?php fswitch('bannedclientdetect', (string)$bannedclientdetect === 'yes', 'Banned client detection', '', 'fa-ban'); ?>
                                <?php fswitch('checkconnectable', (string)$checkconnectable === 'yes', 'Detect connectable', 'Costs some performance', 'fa-wifi'); ?>
                                <?php fswitch('checkip', (string)$checkip === 'yes', 'Check IP', 'Match stored IP against client IP', 'fa-location-crosshairs'); ?>
                            </div>

                            <?php fsec('fa-gauge-high', 'Timing & limits'); ?>
                            <div class="ag-grid">
                                <?php ftxt('announce_wait', (string)$announce_wait, 'Min. refresh time (s)', 'Flood limit between announces', 'number', 'fa-hourglass-start'); ?>
                                <?php ftxt('announce_interval', (string)$announce_interval, 'Announce interval (s)', 'Higher = less load', 'number', 'fa-repeat'); ?>
                                <?php ftxt('max_rate', (string)$max_rate, 'Max. transfer rate', 'Speeds above this are flagged', 'number', 'fa-gauge-simple-high'); ?>
                            </div>
                            <div class="ag-field mt-3">
                                <label class="ag-label" for="f_allowed_clients"><i class="fa-solid fa-list-check"></i>Allowed clients</label>
                                <textarea class="form-control ag-input ag-mono" id="f_allowed_clients" name="configoption[allowed_clients]" rows="4"><?= htmlspecialchars((string)$allowed_clients) ?></textarea>
                                <?= ags_hint('Peer ID prefixes of allowed clients, for example -UT1610-,-AZ3034-. Used only when Banned client detection is on') ?>
                            </div>

                            <?php fsec('fa-database', 'Announce database'); ?>
                            <div class="ag-grid">
                                <?php ftxt('mysql_host', (string)$mysql_host, 'MySQL host', '', 'text', 'fa-server'); ?>
                                <?php ftxt('mysql_db', (string)$mysql_db, 'Database name', '', 'text', 'fa-database'); ?>
                                <?php ftxt('mysql_user', (string)$mysql_user, 'MySQL user', '', 'text', 'fa-user'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_mysql_pass"><i class="fa-solid fa-key"></i>MySQL password</label>
                                    <input type="password" class="form-control ag-input" id="f_mysql_pass" name="configoption[mysql_pass]" autocomplete="new-password"
                                           placeholder="<?= (string)$mysql_pass !== '' ? '•••••••• (set)' : 'Not set' ?>">
                                    <?= ags_hint('Leave blank to keep the current password') ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar('Save & rebuild'); ?>
                    </form>
                </div>

                <!-- ── KPS ──────────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-yellow" id="kps-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_kps" value="1">
                        <?php pane_head('fa-coins', 'KPS bonus points', 'What members earn and what they can spend points on', 'Points', 'fa-star'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-grid ag-grid-2">
                                <?php fsel('bonus', ['enable' => '✅ Enabled', 'disablesave' => '⏸️ Disabled, keep points', 'disable' => '❌ Disabled'], (string)$bonus, 'KPS system', '', 'fa-power-off'); ?>
                            </div>

                            <?php fsec('fa-hand-holding-dollar', 'Earning', 'Points awarded per action'); ?>
                            <div class="ag-grid">
                                <?php ftxt('kpsupload', (string)$kpsupload, 'Upload', '', 'number', 'fa-cloud-arrow-up'); ?>
                                <?php ftxt('kpscomment', (string)$kpscomment, 'Post / comment / thread', '', 'number', 'fa-comment'); ?>
                                <?php ftxt('kpsthanks', (string)$kpsthanks, 'Thanks', '', 'number', 'fa-heart'); ?>
                                <?php ftxt('kpsrate', (string)$kpsrate, 'Rating', '', 'number', 'fa-star'); ?>
                                <?php ftxt('kpspoll', (string)$kpspoll, 'Poll vote', '', 'number', 'fa-square-poll-vertical'); ?>
                                <?php ftxt('kpsmaxpoint', (string)$kpsmaxpoint, 'Max. bonus points', 'Balance cap', 'number', 'fa-arrow-up-9-1'); ?>
                            </div>

                            <?php fsec('fa-cart-shopping', 'Spending', 'What points can be exchanged for'); ?>
                            <div class="ag-grid">
                                <?php fswitch('kpsinvite', $kpsinvite === 'yes', 'Invites', '', 'fa-envelope-open-text'); ?>
                                <?php fswitch('kpstitle', $kpstitle === 'yes', 'Custom title', '', 'fa-user-tag'); ?>
                                <?php fswitch('kpsvip', $kpsvip === 'yes', 'VIP status', '', 'fa-crown'); ?>
                                <?php fswitch('kpsgift', $kpsgift === 'yes', 'Karma gift', '', 'fa-gift'); ?>
                                <?php fswitch('kpswarning', $kpswarning === 'yes', 'Remove warning', '', 'fa-triangle-exclamation'); ?>
                                <?php fswitch('kpsratiofix', $kpsratiofix === 'yes', 'Fix torrent ratio', '', 'fa-scale-balanced'); ?>
                            </div>

                            <?php fsec('fa-cake-candles', 'Birthday reward'); ?>
                            <div class="ag-grid">
                                <?php fswitch('bdayreward', $bdayreward === 'yes', 'Birthday reward', '', 'fa-cake-candles'); ?>
                                <?php fsel('bdayrewardtype', ['freeleech' => '🎁 Free leech', 'silverleech' => '🥈 Silver leech', 'doubleupload' => '2️⃣ Double upload'], (string)$bdayrewardtype, 'Reward type', '', 'fa-gift'); ?>
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
                        <?php pane_head('fa-users-gear', 'Cleanup', 'Rules the cleanup cron applies to torrents and members'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-magnet', 'Torrents'); ?>
                            <div class="ag-grid">
                                <?php ftxt('max_dead_torrent_time', (string)$max_dead_torrent_time, 'Hide dead torrents after (days)', 'Days since last action', 'number', 'fa-eye-slash'); ?>
                            </div>

                            <?php fsec('fa-arrow-trend-up', 'Promotion & demotion'); ?>
                            <div class="ag-grid">
                                <?php ftxt('promote_gig_limit', (string)$promote_gig_limit, 'Promote: min. GB uploaded', '0 = disabled', 'number', 'fa-database'); ?>
                                <?php ftxt('promote_min_ratio', (string)$promote_min_ratio, 'Promote: min. ratio', '', 'text', 'fa-scale-balanced'); ?>
                                <?php ftxt('promote_min_reg_days', (string)$promote_min_reg_days, 'Promote: min. days registered', '', 'number', 'fa-calendar'); ?>
                                <?php ftxt('demote_min_ratio', (string)$demote_min_ratio, 'Demote below ratio', '', 'text', 'fa-arrow-trend-down'); ?>
                                <?php ftxt('referrergift', (string)$referrergift, 'Referrer gift (GB)', '', 'number', 'fa-gift'); ?>
                            </div>

                            <?php fsec('fa-triangle-exclamation', 'Leech warnings'); ?>
                            <div class="ag-grid">
                                <?php ftxt('leechwarn_min_ratio', (string)$leechwarn_min_ratio, 'Warn below ratio', '', 'text', 'fa-scale-unbalanced'); ?>
                                <?php ftxt('leechwarn_gig_limit', (string)$leechwarn_gig_limit, 'Warn after GB downloaded', '', 'number', 'fa-download'); ?>
                                <?php ftxt('leechwarn_length', (string)$leechwarn_length, 'Warning length (weeks)', '', 'number', 'fa-hourglass-half'); ?>
                                <?php ftxt('leechwarn_remove_ratio', (string)$leechwarn_remove_ratio, 'Remove warning at ratio', '', 'text', 'fa-circle-check'); ?>
                                <?php ftxt('ban_user_limit', (string)$ban_user_limit, 'Ban after X warnings', '', 'number', 'fa-gavel'); ?>
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
                        <?php pane_head('fa-user-plus', 'Registration', 'Signup method, credential rules and starter bonuses'); ?>
                        <div class="ag-pane-body">
                            <?php fsec('fa-door-open', 'Signup'); ?>
                            <div class="ag-grid">
                                <?php fsel('regtype', ['invite' => '✉️ Invite only', 'instant' => '⚡ Instant activation', 'verify' => '📧 Email verification'], (string)$regtype, 'Registration method', '', 'fa-user-plus'); ?>
                                <?php fswitch('disableregs', $disableregs === '1', 'Registrations closed', 'On = nobody can sign up', 'fa-user-slash', '1', '0'); ?>
                                <?php ftxt('maxusers', (string)$maxusers, 'Max. users', '0 = unlimited', 'number', 'fa-users'); ?>
                            </div>

                            <?php fsec('fa-user-pen', 'Usernames & passwords'); ?>
                            <div class="ag-grid">
                                <?php ftxt('minnamelength', (string)$minnamelength, 'Username min. length', '', 'number', 'fa-arrow-down-1-9'); ?>
                                <?php ftxt('maxnamelength', (string)$maxnamelength, 'Username max. length', '', 'number', 'fa-arrow-up-9-1'); ?>
                                <?php ftxt('minpasswordlength', (string)$minpasswordlength, 'Password min. length', '', 'number', 'fa-arrow-down-1-9'); ?>
                                <?php ftxt('maxpasswordlength', (string)$maxpasswordlength, 'Password max. length', '', 'number', 'fa-arrow-up-9-1'); ?>
                                <?php fswitch('requirecomplexpasswords', $requirecomplexpasswords === '1', 'Complex passwords', 'Require mixed characters', 'fa-key', '1', '0'); ?>
                            </div>

                            <?php fsec('fa-user-lock', 'Login protection'); ?>
                            <div class="ag-grid">
                                <?php ftxt('failedlogincount', (string)$failedlogincount, 'Max. failed logins', '0 = disabled', 'number', 'fa-user-lock'); ?>
                                <?php fswitch('failedlogintext', $failedlogintext === '1', 'Show failed login count', '', 'fa-eye', '1', '0'); ?>
                            </div>

                            <?php fsec('fa-gift', 'New member defaults'); ?>
                            <div class="ag-grid">
                                <?php
                                $groupOpts = [];
                                $gq = $db->sql_query_prepared('SELECT gid, title FROM usergroups ORDER BY gid ASC');
                                while ($gq && ($g = $db->fetch_array($gq))) {
                                    $groupOpts[(string)$g['gid']] = strip_tags((string)$g['title']);
                                }
                                fsel('_d_usergroup', $groupOpts, (string)$_d_usergroup, 'Default usergroup', '', 'fa-users-rectangle');
                                ?>
                                <?php ftxt('invite_count', (string)$invite_count, 'Starting invites', '0 = none', 'number', 'fa-envelope'); ?>
                                <?php ftxt('autogigsignup', (string)$autogigsignup, 'Starting upload (GB)', '0 = none', 'number', 'fa-cloud-arrow-up'); ?>
                                <?php ftxt('autosbsignup', (string)$autosbsignup, 'Starting seedbonus', '0 = none', 'number', 'fa-seedling'); ?>
                            </div>

                            <?php fsec('fa-stopwatch', 'Flood control', 'Limits signups from a single IP'); ?>
                            <div class="ag-grid">
                                <?php ftxt('betweenregstime', (string)($s['betweenregstime'] ?? '24'), 'Time window (hours)', '', 'number', 'fa-hourglass-half'); ?>
                                <?php ftxt('maxregsbetweentime', (string)($s['maxregsbetweentime'] ?? '2'), 'Max. signups in window', '', 'number', 'fa-user-clock'); ?>
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
                        <?php pane_head('fa-comments', 'Forum / Legacy', 'MyBB-era options, kept here so nothing is edited by hand', 'MyBB', 'fa-clock-rotate-left'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-indigo">
                                <i class="fa-solid fa-circle-info"></i>
                                <div>Inherited from the original MyBB base. Most trackers never touch these, but they are exposed here instead of <code>include/settings.php</code>.</div>
                            </div>

                            <?php fsec('fa-sliders', 'General'); ?>
                            <div class="ag-grid">
                                <?php
                                $langOpts = [];
                                foreach (glob(INC_PATH . '/languages/*', GLOB_ONLYDIR) ?: [] as $langDir) {
                                    $langName = basename($langDir);
                                    $langOpts[$langName] = ucfirst($langName);
                                }
                                fsel('defaultlanguage', $langOpts, (string)($s['defaultlanguage'] ?? 'english'), 'Forum language', '', 'fa-language');
                                ?>
                                <?php fsel('shoutboxcharset', ['UTF-8' => 'UTF-8', 'ISO-8859-1' => 'ISO-8859-1'], (string)($s['shoutboxcharset'] ?? 'UTF-8'), 'Shoutbox charset', '', 'fa-comment-dots'); ?>
                                <?php fswitch('enablepms', ($s['enablepms'] ?? '1') === '1', 'Private messages', '', 'fa-envelope-open-text', '1', '0'); ?>
                                <?php fswitch('usezip', ($s['usezip'] ?? 'no') === 'yes', 'ZIP for uploads', '', 'fa-file-zipper'); ?>
                                <?php ftxt('uploadspath', (string)($s['uploadspath'] ?? './uploads'), 'Uploads path', 'Relative or absolute path', 'text', 'fa-folder-open'); ?>
                                <?php ftxt('loadlimit', (string)($s['loadlimit'] ?? ''), 'Server load limit', 'Blank = disabled', 'text', 'fa-gauge-high'); ?>
                            </div>

                            <?php fsec('fa-table-list', 'Threads & listings'); ?>
                            <div class="ag-grid">
                                <?php fswitch('browsingthisthread', ($s['browsingthisthread'] ?? '1') === '1', '"Users browsing this thread"', '', 'fa-eye', '1', '0'); ?>
                                <?php fswitch('delayedthreadviews', ($s['delayedthreadviews'] ?? '1') === '1', 'Delayed view counting', '', 'fa-clock-rotate-left', '1', '0'); ?>
                                <?php fswitch('showforumpagesbreadcrumb', ($s['showforumpagesbreadcrumb'] ?? '1') === '1', 'Pages in breadcrumb', '', 'fa-route', '1', '0'); ?>
                                <?php fswitch('showownunapproved', ($s['showownunapproved'] ?? '1') === '1', 'Show own unapproved posts', '', 'fa-user-check', '1', '0'); ?>
                                <?php ftxt('threadreadcut', (string)($s['threadreadcut'] ?? '7'), 'Thread read cut-off (days)', 'Older threads always show as read', 'number', 'fa-calendar-check'); ?>
                                <?php ftxt('ts_perpage', (string)($s['ts_perpage'] ?? '20'), 'Torrents per page', '', 'number', 'fa-list'); ?>
                                <?php ftxt('f_postsperpage', (string)($s['f_postsperpage'] ?? '10'), 'Posts per page', '', 'number', 'fa-align-left'); ?>
                                <?php ftxt('f_threadsperpage', (string)($s['f_threadsperpage'] ?? '20'), 'Threads per page', '', 'number', 'fa-list-ol'); ?>
                                <?php ftxt('userpppoptions', (string)($s['userpppoptions'] ?? '5,10,15,20,25,30,40,50'), 'User choices: posts/page', 'Comma-separated', 'text', 'fa-table-cells'); ?>
                                <?php ftxt('usertppoptions', (string)($s['usertppoptions'] ?? '10,15,20,25,30,40,50'), 'User choices: threads/page', 'Comma-separated', 'text', 'fa-table-cells'); ?>
                            </div>

                            <?php fsec('fa-object-group', 'Post merge', 'Joins back-to-back posts by the same author'); ?>
                            <div class="ag-grid ag-grid-2">
                                <?php ftxt('postmergemins', (string)($s['postmergemins'] ?? '60'), 'Merge window (minutes)', '0 = disabled', 'number', 'fa-clock'); ?>
                                <?php ftxt('postmergesep', (string)($s['postmergesep'] ?? '[hr]'), 'Separator', 'Inserted between merged messages', 'text', 'fa-grip-lines'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_postmergefignore"><i class="fa-solid fa-comment-slash"></i>Excluded forums</label>
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
                                    <?= ags_hint('Ctrl/Cmd to select several. Empty = merge everywhere') ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="f_postmergeuignore"><i class="fa-solid fa-users-slash"></i>Excluded usergroups</label>
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
                                    <?= ags_hint('Posts by these groups are never merged') ?>
                                </div>
                            </div>

                            <?php fsec('fa-ruler-horizontal', 'Message length'); ?>
                            <div class="ag-grid">
                                <?php ftxt('minmessagelength', (string)($s['minmessagelength'] ?? '5'), 'Min. length', 'Characters', 'number', 'fa-arrow-down-short-wide'); ?>
                                <?php ftxt('maxmessagelength', (string)($s['maxmessagelength'] ?? '65535'), 'Max. length', '0 = column max. TEXT holds 65535 — use MEDIUMTEXT for more', 'number', 'fa-arrow-up-wide-short'); ?>
                                <?php fswitch('mycodemessagelength', ($s['mycodemessagelength'] ?? '1') === '1', 'BBCode counts toward min.', 'Off = tags stripped before the check', 'fa-code', '1', '0'); ?>
                            </div>
                        </div>
                        <?php savebar(); ?>
                    </form>
                </div>

                <!-- ── STAFF TEAM ───────────────────────────────────────────────── -->
                <div class="tab-pane fade t-purple" id="staff-team">
                    <form method="post" action="<?= AGS_SELF ?>" class="ag-pane">
                        <?= csrf_field() ?>
                        <?php pane_head('fa-user-shield', 'Staff team', 'Who is listed on the public staff page', count($staffarray) . ' members', 'fa-users'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-purple">
                                <i class="fa-solid fa-lightbulb"></i>
                                <div>Type a username — the ID fills in automatically when found. Only enabled accounts in staff groups are accepted.</div>
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
                                            <th><i class="fa-solid fa-user me-1"></i> Username</th>
                                            <th style="width:32%"><i class="fa-solid fa-id-badge me-1"></i> User ID</th>
                                            <th style="width:52px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staffarray)): ?>
                                            <tr><td colspan="4" class="ag-empty"><i class="fa-solid fa-user-slash"></i> No staff members yet — add them below.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($staffarray as $i => $st): ?>
                                        <tr>
                                            <td><span class="ag-num"><?= $i + 1 ?></span></td>
                                            <td><input type="text" name="staffnames[]" value="<?= htmlspecialchars($st['name']) ?>" class="form-control ag-input" list="staffNames" placeholder="Username" aria-label="Username"></td>
                                            <td><input type="text" name="staffids[]" value="<?= htmlspecialchars($st['id']) ?>" class="form-control ag-input" placeholder="User ID" aria-label="User ID" inputmode="numeric"></td>
                                            <td>
                                                <button type="button" class="ag-icon-btn" title="Remove from list" aria-label="Remove"
                                                        onclick="this.closest('tr').querySelectorAll('input').forEach(i=>i.value='')">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php for ($i = 0; $i < 3; $i++): ?>
                                        <tr class="row-new">
                                            <td><span class="ag-num is-new" title="New row"><i class="fa-solid fa-plus"></i></span></td>
                                            <td><input type="text" name="staffnames[]" class="form-control ag-input" list="staffNames" placeholder="New username" aria-label="New username"></td>
                                            <td><input type="text" name="staffids[]" class="form-control ag-input" placeholder="User ID" aria-label="New user ID" inputmode="numeric"></td>
                                            <td></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?= ags_hint('Only members of staff groups can be added. Type a username and the User ID fills in by itself. Clear both fields of a row to remove that member') ?>

                            <div class="ag-toolbar">
                                <button type="button" class="btn btn-outline-secondary btn-sm ag-pill"
                                        onclick="window.open('<?= htmlspecialchars((string)$BASEURL) ?>/users.php#searchuser','finduser','toolbar=no,scrollbars=yes,width=800,height=600')">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> Find user
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ag-pill" data-clear-rows
                                        onclick="this.closest('form').querySelectorAll('.row-new input').forEach(i=>i.value='')">
                                    <i class="fa-solid fa-eraser me-1"></i> Clear new rows
                                </button>
                                <span class="ag-muted ms-auto"><i class="fa-solid fa-user-shield"></i> <?= count($availableStaff) ?> eligible accounts</span>
                            </div>
                        </div>
                        <?php savebar('Save staff team', 'save_staff'); ?>
                    </form>
                </div>

                <!-- ── FREELEECH ────────────────────────────────────────────────── -->
                <div class="tab-pane fade t-red" id="freeleech-settings">
                    <form method="post" class="settings-form ag-pane">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_freeleech" value="1">
                        <?php pane_head('fa-gift', 'Freeleech', 'Site-wide promotion for a fixed period', 'Promo', 'fa-bullhorn'); ?>
                        <div class="ag-pane-body">
                            <div class="ag-note t-orange">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <div><strong>Applies to every torrent</strong> between the start and end date.</div>
                            </div>

                            <div class="ag-grid">
                                <?php fsel('system', ['freeleech' => '🎁 Free leech', 'silverleech' => '🥈 Silver leech', 'doubleupload' => '2️⃣ Double upload'], (string)($__FLSTYPE ?? ''), 'Promotion type', '', 'fa-tags'); ?>
                                <div class="ag-field">
                                    <label class="ag-label" for="startPicker"><i class="fa-solid fa-calendar-plus"></i>Starts</label>
                                    <div class="ag-affix">
                                        <input type="text" id="startPicker" class="form-control ag-input" name="configoption[start]"
                                               value="<?= ($__F_START ?? '') !== '0000-00-00 00:00:00' ? htmlspecialchars((string)($__F_START ?? '')) : '' ?>"
                                               placeholder="YYYY-MM-DD HH:MM:SS">
                                        <span><i class="fa-regular fa-calendar"></i></span>
                                    </div>
                                    <?= ags_hint('From this moment ALL torrents get the promotion selected above') ?>
                                </div>
                                <div class="ag-field">
                                    <label class="ag-label" for="endPicker"><i class="fa-solid fa-calendar-xmark"></i>Ends</label>
                                    <div class="ag-affix">
                                        <input type="text" id="endPicker" class="form-control ag-input" name="configoption[end]"
                                               value="<?= ($__F_END ?? '') !== '0000-00-00 00:00:00' ? htmlspecialchars((string)($__F_END ?? '')) : '' ?>"
                                               placeholder="YYYY-MM-DD HH:MM:SS">
                                        <span><i class="fa-regular fa-calendar"></i></span>
                                    </div>
                                    <?= ags_hint('At this moment the global promotion stops by itself') ?>
                                </div>
                            </div>
                        </div>
                        <?php savebar('Save promotion'); ?>
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
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/managesettings.js?ver=9"></script>

<?php stdfoot(); ?>