<?php
/**
 * Installer v1.2
 * 
 */
const IN_TRACKER = true;
const APP_INITIALIZED = true;
define('IN_MYBB', 1);
define('TSDIR', __DIR__);
define('MYBB_ROOT', __DIR__ . '/');
define('INC_PATH', __DIR__ . '/include');

require_once INC_PATH . '/functions.php';
require_once INC_PATH . '/functions_user.php';

define('INSTALLER_VERSION', '1.2.0');
define('LOCK_FILE', __DIR__ . '/install.lock');

if (file_exists(LOCK_FILE) && !isset($_GET['force'])) {
    die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Already Installed | Mybb Based Torrent Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .lock-card { backdrop-filter:blur(10px); background:rgba(255,255,255,.95); border-radius:30px; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); transition:transform .3s ease; }
        .lock-card:hover { transform:translateY(-5px); }
        .lock-icon { font-size:5rem; animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.1);opacity:.8} }
        .danger-btn { transition:all .3s ease; }
        .danger-btn:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(220,53,69,.3); }
        code { background:#f8f9fa; padding:4px 8px; border-radius:8px; font-weight:600; color:#dc3545; }
        .warning-alert { border-left:4px solid #ffc107; background:#fff9e6; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;margin:0;padding:20px;">
    <div class="container" style="max-width:550px;">
        <div class="lock-card p-5">
            <div class="text-center mb-4">
                <div class="lock-icon mb-3"><i class="fas fa-lock-circle fa-4x text-danger"></i></div>
                <div class="mb-3"><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-2"></i>Installation Locked</span></div>
                <h2 class="fw-bold mb-2" style="color:#1a1a2e;">Already Installed</h2>
                <p class="text-muted mb-1">Mybb Based Torrent Tracker is already installed and configured.</p>
                <p class="text-muted">Delete <code>install.lock</code> to reinstall.</p>
            </div>
            <hr class="my-4">
            <div class="warning-alert p-3 rounded mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0"><i class="fas fa-exclamation-triangle text-warning fa-lg"></i></div>
                    <div class="ms-3"><strong class="d-block mb-1">Warning!</strong><small class="text-muted">Force reinstall will overwrite your current settings and data.</small></div>
                </div>
            </div>
            <div class="d-grid gap-3">
                <a href="install.php?force=1" class="btn btn-outline-danger btn-lg danger-btn"><i class="fas fa-rotate-right me-2"></i>Force Reinstall</a>
                <a href="/" class="btn btn-secondary btn-lg"><i class="fas fa-home me-2"></i>Go to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>');
}

session_start();

$steps = [
    1 => 'Requirements',
    2 => 'Database',
    3 => 'Site Settings',
    4 => 'Admin Account',
    5 => 'Install',
    6 => 'Complete',
];

$step   = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step   = max(1, min(6, $step));
$errors = [];

// ── Initialize DB class from session (starting at step 3) ─────────────────
function getDb(): mixed
{
    $dbSess = $_SESSION['db'] ?? [];
    if (empty($dbSess)) return null;

    $config = [
        'database' => [
            'type'         => $dbSess['type']     ?? 'mysqli',
            'hostname'     => $dbSess['hostname']  ?? 'localhost',
            'database'     => $dbSess['database']  ?? '',
            'username'     => $dbSess['username']  ?? '',
            'password'     => $dbSess['password']  ?? '',
            'encoding'     => 'utf8mb4',
        ],
    ];

    require_once INC_PATH . '/db_base.php';
    $driverFile = INC_PATH . "/db_{$config['database']['type']}.php";
    if (!file_exists($driverFile)) {
        throw new RuntimeException("Database driver not found: {$config['database']['type']}");
    }
    require_once $driverFile;

    $db = match($config['database']['type']) {
        'sqlite' => new DB_SQLite(),
        'pgsql'  => new DB_PgSQL(),
        'mysqli' => new DB_MySQLi(),
        default  => new DB_MySQL(),
    };

    $db->connect($config['database']);
    $db->set_table_prefix('');
    $db->type = $config['database']['type'];

    return $db;
}

// ── POST handling ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 2: connection check ────────────────────────────────────────────
    if ($step === 2) {
        $dbType = trim($_POST['db_type'] ?? 'mysqli');

        $_SESSION['db'] = [
            'type'     => $dbType,
            'hostname' => trim($_POST['db_host'] ?? 'localhost'),
            'database' => trim($_POST['db_name'] ?? ''),
            'username' => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
        ];

        try {
            $db = getDb();
            // Simple test: version query
            $db->sql_query("SELECT 1");
            header('Location: install.php?step=3'); exit;
        } catch (Throwable $e) {
            $errors[] = 'Cannot connect to database: ' . $e->getMessage();
        }
    }

    // ── Step 3: site settings ────────────────────────────────────────────────
    if ($step === 3) {
        $url = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $_SESSION['site'] = [
            'name'          => trim($_POST['site_name']      ?? ''),
            'url'           => $url,
            'email'         => trim($_POST['site_email']     ?? ''),
            'timezone'      => trim($_POST['timezone']       ?? '2'),
            'cookie_domain' => trim($_POST['cookie_domain']  ?? ''),
            'smtp_host'     => trim($_POST['smtp_host']      ?? 'smtp.gmail.com'),
            'smtp_user'     => trim($_POST['smtp_user']      ?? ''),
            'smtp_pass'     => $_POST['smtp_pass']           ?? '',
            'smtp_port'     => trim($_POST['smtp_port']      ?? '587'),
            'membersonly'   => isset($_POST['membersonly'])  ? 'yes' : 'no',
            'privatepatch'  => isset($_POST['privatepatch']) ? 'yes' : 'no',
        ];
        if (!$_SESSION['site']['name'])  $errors[] = 'Site name is required.';
        if (!$_SESSION['site']['url'])   $errors[] = 'Site URL is required.';
        if (!$_SESSION['site']['email']) $errors[] = 'Site email is required.';
        if (!$errors) { header('Location: install.php?step=4'); exit; }
    }

    // ── Step 4: admin account ────────────────────────────────────────────────
    if ($step === 4) {
        $_SESSION['admin'] = [
            'username' => trim($_POST['admin_user']    ?? ''),
            'email'    => trim($_POST['admin_email']   ?? ''),
            'password' => $_POST['admin_pass']         ?? '',
            'confirm'  => $_POST['admin_confirm']      ?? '',
        ];
        if (!$_SESSION['admin']['username']) $errors[] = 'Admin username is required.';
        if (!$_SESSION['admin']['email'])    $errors[] = 'Admin email is required.';
        if (strlen($_SESSION['admin']['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($_SESSION['admin']['password'] !== $_SESSION['admin']['confirm']) $errors[] = 'Passwords do not match.';
        if (!$errors) { header('Location: install.php?step=5'); exit; }
    }

    // ── Step 5: run installation ─────────────────────────────────────────────
    if ($step === 5) {
        $result = runInstallation();
        if ($result === true) {
            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
            header('Location: install.php?step=6'); exit;
        } else {
            $errors = $result;
        }
    }
	
}

// ── Main installation function ────────────────────────────────────────────────
function runInstallation(): true|array
{
    $dbSess = $_SESSION['db']    ?? [];
    $site   = $_SESSION['site']  ?? [];
    $admin  = $_SESSION['admin'] ?? [];

    // --- Connection via DB class ---
    try {
        $db = getDb();
    } catch (Throwable $e) {
        return ['Database connection failed: ' . $e->getMessage()];
    }

    // ── SQL — tracker schema ────────────────────────────────────────────────────
    $tables = <<<SQL
CREATE TABLE IF NOT EXISTS `2fa` (
  `uid`        INT UNSIGNED     NOT NULL,
  `secret`     VARCHAR(64)      NOT NULL DEFAULT '',
  `enabled`    ENUM('yes','no') NOT NULL DEFAULT 'no',
  `created_at` INT UNSIGNED     NOT NULL DEFAULT 0,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `2fa_pending` (
  `token`      VARCHAR(64)  NOT NULL,
  `uid`        INT UNSIGNED NOT NULL,
  `remember`   VARCHAR(8)   NOT NULL DEFAULT '',
  `url`        VARCHAR(512) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`token`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
CREATE TABLE IF NOT EXISTS `adminlog` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `module` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(50) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  KEY `module` (`module`,`action`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(120) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `uid` int UNSIGNED NOT NULL DEFAULT '0',
  `added` int UNSIGNED NOT NULL DEFAULT '0',
  `updated` int UNSIGNED NOT NULL DEFAULT '0',
  `views` int UNSIGNED NOT NULL DEFAULT '0',
  `startdate` int UNSIGNED NOT NULL DEFAULT '0',
  `enddate` int UNSIGNED NOT NULL DEFAULT '0',
  `minclassread` tinyint NOT NULL DEFAULT '1',
  `fid` int NOT NULL DEFAULT '0',
  `type` enum('tracker','forum','global') NOT NULL DEFAULT 'tracker',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE IF NOT EXISTS `attachments` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
  `comment_id` int unsigned NOT NULL DEFAULT '0',
  `posthash` varchar(50) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `filename` varchar(255) NOT NULL DEFAULT '',
  `filetype` varchar(120) NOT NULL DEFAULT '',
  `filesize` int unsigned NOT NULL DEFAULT '0',
  `attachname` varchar(255) NOT NULL DEFAULT '',
  `downloads` int unsigned NOT NULL DEFAULT '0',
  `dateuploaded` int unsigned NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail` varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`),
  KEY `pid` (`pid`,`visible`),
  KEY `uid` (`uid`),
  KEY `comment_id` (`comment_id`),
  KEY `posthash` (`posthash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `attachtypes` (
  `atid` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `mimetype` varchar(120) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  `maxsize` int unsigned NOT NULL DEFAULT '0',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `forcedownload` tinyint(1) NOT NULL DEFAULT '0',
  `groups` text NOT NULL,
  `forums` text NOT NULL,
  `avatarfile` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`atid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `awaitingactivation` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `code` varchar(100) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  `misc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `banfilters` (
  `fid` int unsigned NOT NULL AUTO_INCREMENT,
  `filter` varchar(200) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `lastuse` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fid`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `banned` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `gid` int unsigned NOT NULL DEFAULT '0',
  `oldgroup` int unsigned NOT NULL DEFAULT '0',
  `oldadditionalgroups` text NOT NULL,
  `olddisplaygroup` int unsigned NOT NULL DEFAULT '0',
  `admin` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `bantime` varchar(50) NOT NULL DEFAULT '',
  `lifted` int unsigned NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT '',
  KEY `uid` (`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`,`torrentid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` char(100) NOT NULL DEFAULT '',
  `cat_desc` char(30) NOT NULL DEFAULT '',
  `minclassread` tinyint unsigned NOT NULL DEFAULT '0',
  `type` char(1) NOT NULL DEFAULT 'c',
  `pid` smallint unsigned NOT NULL DEFAULT '0',
  `icon` varchar(100) NOT NULL DEFAULT 'fa-solid fa-question',
  PRIMARY KEY (`id`),
  KEY `type` (`type`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cheat_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `added` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `agent` char(32) NOT NULL DEFAULT '',
  `transfer_rate` bigint unsigned NOT NULL DEFAULT '0',
  `beforeup` bigint unsigned NOT NULL DEFAULT '0',
  `upthis` bigint unsigned NOT NULL DEFAULT '0',
  `timediff` int unsigned NOT NULL DEFAULT '0',
  `ip` char(15) NOT NULL DEFAULT '',
  `reason` varchar(64) NOT NULL DEFAULT '',
  `detail` varchar(512) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comment_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` int unsigned DEFAULT NULL,
  `news_id` int unsigned DEFAULT NULL,
  `torrent_id` int unsigned DEFAULT NULL,
  `post_id` int unsigned DEFAULT NULL,
  `messages_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_id` (`comment_id`),
  KEY `idx_news_id` (`news_id`),
  KEY `idx_torrent_id` (`torrent_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL DEFAULT '0',
  `torrent` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `text` text NOT NULL,
  `editreason` varchar(150) NOT NULL DEFAULT '',
  `editedby` int unsigned NOT NULL DEFAULT '0',
  `editedat` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `torrent` (`torrent`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `flagpic` char(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron` (
  `cronid` int unsigned NOT NULL AUTO_INCREMENT,
  `nextrun` int unsigned NOT NULL DEFAULT '0',
  `minutes` int unsigned NOT NULL DEFAULT '0',
  `filename` char(50) NOT NULL DEFAULT '',
  `description` char(200) NOT NULL DEFAULT '',
  `loglevel` tinyint unsigned NOT NULL DEFAULT '1',
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`cronid`),
  KEY `nextrun` (`nextrun`),
  KEY `active` (`active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron_log` (
  `filename` char(100) NOT NULL DEFAULT '',
  `querycount` smallint unsigned NOT NULL DEFAULT '0',
  `executetime` char(10) NOT NULL DEFAULT '0',
  `runtime` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `filename` (`filename`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `datacache` (
  `title` varchar(50) NOT NULL DEFAULT '',
  `cache` mediumtext NOT NULL,
  PRIMARY KEY (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faq` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('category','item') NOT NULL DEFAULT 'category',
  `name` varchar(120) NOT NULL DEFAULT '',
  `pid` int unsigned NOT NULL DEFAULT '0',
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `views_count` int unsigned DEFAULT '0',
  `icon_class` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumpermissions` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` int unsigned NOT NULL DEFAULT '0',
  `gid` int unsigned NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyviewownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `canonlyreplyownthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canratethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeletionnotice` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`),
  KEY `fid` (`fid`,`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `hit_and_run` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inactivity` (
  `userid` int unsigned NOT NULL DEFAULT '0',
  `inactivitytag` int unsigned NOT NULL DEFAULT '0',
  KEY `userid` (`userid`),
  KEY `inactivitytag` (`inactivitytag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `inviter_id` int unsigned NOT NULL DEFAULT '0',
  `invitee_id` int unsigned DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `status` enum('pending','used','expired','revoked') NOT NULL DEFAULT 'pending',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  `expires_at` int unsigned DEFAULT NULL,
  `used_at` int unsigned DEFAULT NULL,
  `ip_created` varchar(45) DEFAULT NULL,
  `ip_used` varchar(45) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `inviter_id` (`inviter_id`),
  KEY `status` (`status`),
  KEY `expires_at` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `loginattempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `added` int unsigned NOT NULL DEFAULT '0',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `type` enum('login','recover') NOT NULL DEFAULT 'login',
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `login_log` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `uid`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `ip`         VARCHAR(45)     NOT NULL DEFAULT '',
  `country`    VARCHAR(64)     NOT NULL DEFAULT '',
  `city`       VARCHAR(64)     NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255)    NOT NULL DEFAULT '',
  `fingerprint` VARCHAR(32)    NOT NULL DEFAULT '',
  `datetime`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `type`       ENUM('login','recover') NOT NULL DEFAULT 'login',
  `status`     ENUM('success','fail')  NOT NULL DEFAULT 'fail',
  `suspicious` ENUM('yes','no')        NOT NULL DEFAULT 'no',
  `banned`     ENUM('yes','no')        NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`), KEY `ip` (`ip`),
  KEY `datetime` (`datetime`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS `mailerrors` (
  `eid` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `toaddress` varchar(150) NOT NULL DEFAULT '',
  `fromaddress` varchar(150) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `error` text NOT NULL,
  `smtperror` varchar(200) NOT NULL DEFAULT '',
  `smtpcode` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`eid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `maillogs` (
  `mid` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(200) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `fromuid` int unsigned NOT NULL DEFAULT '0',
  `fromemail` varchar(200) NOT NULL DEFAULT '',
  `touid` int unsigned NOT NULL DEFAULT '0',
  `toemail` varchar(200) NOT NULL DEFAULT '',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `mailqueue` (
  `mid` int unsigned NOT NULL AUTO_INCREMENT,
  `mailto` varchar(200) NOT NULL,
  `mailfrom` varchar(200) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `headers` text NOT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `moderatorlog` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `pid` int unsigned NOT NULL DEFAULT '0',
  `action` text NOT NULL,
  `data` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  KEY `uid` (`uid`),
  KEY `fid` (`fid`),
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `moderators` (
  `mid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `id` int unsigned NOT NULL DEFAULT '0',
  `isgroup` tinyint unsigned NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `canrestoreposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `cansoftdeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canrestorethreads` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canviewips` tinyint(1) NOT NULL DEFAULT '0',
  `canviewunapprove` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeleted` tinyint(1) NOT NULL DEFAULT '0',
  `canopenclosethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canstickunstickthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapprovethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveposts` tinyint(1) NOT NULL DEFAULT '0',
  `canapproveunapproveattachs` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canpostclosedthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canmovetononmodforum` tinyint(1) NOT NULL DEFAULT '0',
  `canusecustomtools` tinyint(1) NOT NULL DEFAULT '0',
  `canmanageannouncements` tinyint(1) NOT NULL DEFAULT '0',
  `canmanagereportedposts` tinyint(1) NOT NULL DEFAULT '0',
  `canviewmodlog` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`mid`),
  KEY `uid` (`id`,`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `body` text NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `added` (`added`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` int unsigned NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_userid` (`userid`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `peers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent` int unsigned NOT NULL DEFAULT '0',
  `peer_id` binary(20) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') NOT NULL DEFAULT 'no',
  `started` int unsigned NOT NULL DEFAULT '0',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `prev_action` int unsigned NOT NULL DEFAULT '0',
  `connectable` enum('yes','no') NOT NULL DEFAULT 'yes',
  `userid` int unsigned NOT NULL DEFAULT '0',
  `agent` varchar(80) NOT NULL DEFAULT '',
  `finishedat` int unsigned NOT NULL DEFAULT '0',
  `downloadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `uploadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `passkey` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `last_action` (`last_action`),
  KEY `userid` (`userid`),
  KEY `passkey` (`passkey`),
  KEY `torrent` (`torrent`),
  KEY `peer_id` (`peer_id`,`seeder`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `privatemessages` (
  `pmid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `toid` int unsigned NOT NULL DEFAULT '0',
  `fromid` int unsigned NOT NULL DEFAULT '0',
  `recipients` text NOT NULL,
  `folder` smallint unsigned NOT NULL DEFAULT '1',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `icon` smallint unsigned NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `deletetime` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `statustime` int unsigned NOT NULL DEFAULT '0',
  `receipt` tinyint(1) NOT NULL DEFAULT '0',
  `readtime` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`pmid`),
  KEY `uid` (`uid`,`folder`),
  KEY `toid` (`toid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `addedby` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `reported_id` int unsigned NOT NULL DEFAULT '0',
  `reported_user_id` int unsigned NOT NULL DEFAULT '0',
  `forum_id` int DEFAULT '0',
  `thread_id` int DEFAULT '0',
  `type` enum('torrent','user','comment','forumpost') NOT NULL DEFAULT 'torrent',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `rule_violation` varchar(50) DEFAULT '',
  `description` text,
  `dealtby` int unsigned NOT NULL DEFAULT '0',
  `dealtwith` tinyint unsigned NOT NULL DEFAULT '0',
  `updated_at` int unsigned NOT NULL DEFAULT '0',
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reported_id` (`reported_id`),
  KEY `idx_dealtwith` (`dealtwith`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `usergroups` varchar(100) NOT NULL DEFAULT '[0]',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `priority` tinyint unsigned DEFAULT '50',
  `is_active` tinyint(1) DEFAULT '1',
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `screenshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` int unsigned NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `searchlog` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `threads` longtext NOT NULL,
  `posts` longtext NOT NULL,
  `resulttype` varchar(10) NOT NULL DEFAULT '',
  `querycache` text NOT NULL,
  `keywords` text NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `seedbonus_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('boolean','integer','float','string','array') DEFAULT 'string',
  `setting_group` varchar(50) DEFAULT 'general',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sessions` (
  `sid` varchar(32) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ip` varbinary(16) NOT NULL DEFAULT '',
  `time` int unsigned NOT NULL DEFAULT '0',
  `location` varchar(150) NOT NULL DEFAULT '',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `nopermission` tinyint(1) NOT NULL DEFAULT '0',
  `location1` int unsigned NOT NULL DEFAULT '0',
  `location2` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`),
  KEY `time` (`time`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `settings` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sitelog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `added` int unsigned NOT NULL DEFAULT '0',
  `txt` text,
  `category` varchar(50) NOT NULL DEFAULT '',
  `level` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `category` (`category`),
  KEY `added` (`added`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `smilies` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `stitle` char(100) NOT NULL DEFAULT '',
  `stext` char(20) NOT NULL DEFAULT '',
  `spath` char(100) NOT NULL DEFAULT '',
  `sorder` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `snatched` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` int unsigned DEFAULT '0',
  `userid` int unsigned DEFAULT '0',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') NOT NULL DEFAULT 'no',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `startdat` int unsigned NOT NULL DEFAULT '0',
  `completedat` int unsigned NOT NULL DEFAULT '0',
  `connectable` enum('yes','no') NOT NULL DEFAULT 'yes',
  `agent` char(32) NOT NULL DEFAULT '',
  `finished` enum('yes','no') NOT NULL DEFAULT 'no',
  `downspeed` bigint unsigned NOT NULL DEFAULT '0',
  `upspeed` bigint unsigned NOT NULL DEFAULT '0',
  `seedtime` int unsigned NOT NULL DEFAULT '0',
  `leechtime` int unsigned NOT NULL DEFAULT '0',
  `ip` char(15) NOT NULL DEFAULT '',
  `warned` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `seeder` (`seeder`,`last_action`),
  KEY `torrentid` (`torrentid`),
  KEY `userid` (`userid`),
  KEY `finished` (`finished`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `spiders` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `theme` smallint unsigned NOT NULL DEFAULT '0',
  `language` varchar(20) NOT NULL DEFAULT '',
  `usergroup` smallint unsigned NOT NULL DEFAULT '0',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `lastvisit` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `staffmessages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sender` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `msg` text,
  `subject` varchar(100) NOT NULL DEFAULT '',
  `answeredby` int unsigned NOT NULL DEFAULT '0',
  `answered` tinyint unsigned NOT NULL DEFAULT '0',
  `answer` text,
  PRIMARY KEY (`id`),
  KEY `answered` (`answered`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `staffpanel` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` char(32) NOT NULL DEFAULT '',
  `description` varchar(64) NOT NULL DEFAULT '',
  `filename` char(32) NOT NULL DEFAULT '',
  `usergroups` char(32) NOT NULL DEFAULT '[8]',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stats` (
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `numusers` int unsigned NOT NULL DEFAULT '0',
  `numthreads` int unsigned NOT NULL DEFAULT '0',
  `numposts` int unsigned NOT NULL DEFAULT '0',
  `torrents` int unsigned NOT NULL DEFAULT '0',
  `seeders` int unsigned NOT NULL DEFAULT '0',
  `leechers` int unsigned NOT NULL DEFAULT '0',
  `peers` int unsigned NOT NULL DEFAULT '0',
  `totaldownloaded` bigint unsigned NOT NULL DEFAULT '0',
  `totaluploaded` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `torrents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `info_hash` varchar(40) NOT NULL,
  `name` tinytext,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `descr` longtext,
  `tags` text,
  `category` int unsigned NOT NULL DEFAULT '0',
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `numfiles` int unsigned NOT NULL DEFAULT '0',
  `comments` int unsigned NOT NULL DEFAULT '0',
  `hits` int unsigned NOT NULL DEFAULT '0',
  `times_completed` int unsigned NOT NULL DEFAULT '0',
  `leechers` int unsigned NOT NULL DEFAULT '0',
  `seeders` int unsigned NOT NULL DEFAULT '0',
  `last_action` int unsigned NOT NULL DEFAULT '0',
  `visible` enum('yes','no') NOT NULL DEFAULT 'yes',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no',
  `owner` int unsigned NOT NULL DEFAULT '0',
  `anonymous` enum('yes','no') NOT NULL DEFAULT 'no',
  `free` enum('yes','no') DEFAULT 'no',
  `silver` enum('yes','no') NOT NULL DEFAULT 'no',
  `doubleupload` enum('yes','no') NOT NULL DEFAULT 'no',
  `thirtypercent` enum('yes','no') NOT NULL DEFAULT 'no',
  `sticky` enum('yes','no') NOT NULL DEFAULT 'no',
  `allowcomments` enum('yes','no') NOT NULL DEFAULT 'yes',
  `t_image` varchar(255) NOT NULL DEFAULT '',
  `t_image2` varchar(255) NOT NULL DEFAULT '',
  `t_link` text NOT NULL,
  `isnuked` enum('yes','no') NOT NULL DEFAULT 'no',
  `WhyNuked` varchar(255) NOT NULL DEFAULT 'Bad quality!',
  `isrequest` enum('yes','no') NOT NULL DEFAULT 'no',
  `mtime` int unsigned NOT NULL DEFAULT '0',
  `ctime` int unsigned NOT NULL DEFAULT '0',
  `promotion_time_type` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `info_hash` (`info_hash`),
  KEY `added` (`added`),
  KEY `category` (`category`),
  KEY `owner` (`owner`),
  KEY `visible` (`visible`,`banned`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `torrents_nfo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int unsigned NOT NULL,
  `nfo` longtext NOT NULL,
  `uploaded_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `torrent_id` (`torrent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `torrent_ratings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `torrent_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `added` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`torrent_id`, `user_id`)
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS `threadratings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tid` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `added` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`tid`, `user_id`),
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;






CREATE TABLE IF NOT EXISTS `forums` (
  `fid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `linkto` varchar(180) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `pid` smallint unsigned NOT NULL DEFAULT '0',
  `parentlist` text NOT NULL,
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `open` tinyint(1) NOT NULL DEFAULT '1',
  `threads` int unsigned NOT NULL DEFAULT '0',
  `posts` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastposter` varchar(120) NOT NULL DEFAULT '',
  `lastposteruid` int unsigned NOT NULL DEFAULT '0',
  `lastposttid` int NOT NULL DEFAULT '0',
  `lastpostsubject` varchar(120) NOT NULL DEFAULT '',
  `password` varchar(50) NOT NULL DEFAULT '',
  `usepostcounts` tinyint(1) NOT NULL DEFAULT '1',
  `usethreadcounts` tinyint(1) NOT NULL DEFAULT '1',
  `unapprovedthreads` int unsigned NOT NULL DEFAULT '0',
  `unapprovedposts` int unsigned NOT NULL DEFAULT '0',
  `defaultdatecut` smallint unsigned NOT NULL DEFAULT '0',
  `defaultsortby` varchar(10) NOT NULL DEFAULT '',
  `defaultsortorder` varchar(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`fid`),
  KEY `pid` (`pid`),
  KEY `type` (`type`,`pid`,`disporder`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumsread` (
  `fid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `fid` (`fid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `forumsubscriptions` (
  `fsid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fsid`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;


CREATE TABLE IF NOT EXISTS `polls` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `tid` int unsigned NOT NULL DEFAULT '0',
  `question` varchar(200) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `options` text NOT NULL,
  `votes` text NOT NULL,
  `numoptions` smallint unsigned NOT NULL DEFAULT '0',
  `numvotes` int unsigned NOT NULL DEFAULT '0',
  `timeout` int unsigned NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `multiple` tinyint(1) NOT NULL DEFAULT '0',
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `maxoptions` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`),
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `pollvotes` (
  `vid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `voteoption` smallint unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`vid`),
  KEY `pid` (`pid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `posts` (
  `pid` int unsigned NOT NULL AUTO_INCREMENT,
  `tid` int unsigned NOT NULL DEFAULT '0',
  `replyto` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `username` char(32) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `message` text NOT NULL,
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `edituid` int unsigned NOT NULL DEFAULT '0',
  `edittime` int NOT NULL DEFAULT '0',
  `editreason` varchar(150) NOT NULL DEFAULT '',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`pid`),
  KEY `tid` (`tid`,`uid`),
  KEY `uid` (`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threads` (
  `tid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `username` char(32) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `firstpost` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastposter` char(32) NOT NULL DEFAULT '',
  `lastposteruid` int unsigned NOT NULL DEFAULT '0',
  `views` int NOT NULL DEFAULT '0',
  `replies` int NOT NULL DEFAULT '0',
  `closed` varchar(30) NOT NULL DEFAULT '',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `votenum` smallint unsigned NOT NULL DEFAULT '0',
  `votetotal` smallint unsigned NOT NULL DEFAULT '0',
  `poll` int unsigned NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `attachmentcount` int unsigned NOT NULL DEFAULT '0',
  `prefix` smallint unsigned NOT NULL DEFAULT '0',
  `notes` text NOT NULL,
  `numratings` smallint unsigned NOT NULL DEFAULT '0',
  `totalratings` smallint unsigned NOT NULL DEFAULT '0',
  `unapprovedposts` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `fid` (`fid`,`visible`,`sticky`),
  KEY `lastpost` (`lastpost`,`fid`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threadsread` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `tid` (`tid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `threadsubscriptions` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`),
  KEY `uid` (`uid`),
  KEY `tid` (`tid`,`notification`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `threadviews` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `unbanrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `realip` char(15) NOT NULL DEFAULT '',
  `email` char(64) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `added` int unsigned NOT NULL DEFAULT '0',
  `reply` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `usergroups` (
  `gid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  `type` tinyint unsigned NOT NULL DEFAULT '1',
  `title` char(20) NOT NULL DEFAULT '',
  `usertitle` varchar(120) NOT NULL DEFAULT '',
  `description` tinytext NOT NULL,
  `isbannedgroup` tinyint(1) NOT NULL DEFAULT '0',
  `cansettingspanel` tinyint(1) NOT NULL DEFAULT '0',
  `canstaffpanel` tinyint(1) NOT NULL DEFAULT '0',
  `canuserdetails` tinyint(1) NOT NULL DEFAULT '0',
  `floodlimit` tinyint unsigned NOT NULL DEFAULT '60',
  `autoinvite` tinyint unsigned NOT NULL DEFAULT '3',
  `namestyle` char(255) NOT NULL DEFAULT '{username}',
  `issupermod` tinyint(1) NOT NULL DEFAULT '0',
  `isvipgroup` enum('yes','no') NOT NULL DEFAULT 'no',
  `canfreeleech` enum('yes','no') NOT NULL DEFAULT 'no',
  `candeletetorrent` tinyint(1) NOT NULL DEFAULT '0',
  `attachquota` int unsigned NOT NULL DEFAULT '0',
  `maxpmrecipients` int unsigned NOT NULL DEFAULT '5',
  `pmquota` int unsigned NOT NULL DEFAULT '0',
  `maxposts` int unsigned NOT NULL DEFAULT '0',
  `showforumteam` tinyint(1) NOT NULL DEFAULT '0',
  `cansendemail` tinyint(1) NOT NULL DEFAULT '0',
  `cansendemailoverride` tinyint(1) NOT NULL DEFAULT '0',
  `canusepms` tinyint(1) NOT NULL DEFAULT '0',
  `cansendpms` tinyint(1) NOT NULL DEFAULT '0',
  `canoverridepm` tinyint(1) NOT NULL DEFAULT '0',
  `cantrackpms` tinyint(1) NOT NULL DEFAULT '0',
  `candenypmreceipts` tinyint(1) NOT NULL DEFAULT '0',
  `canview` tinyint(1) NOT NULL DEFAULT '0',
  `canviewthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostthreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostreplys` tinyint(1) NOT NULL DEFAULT '0',
  `caneditposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeleteposts` tinyint(1) NOT NULL DEFAULT '0',
  `candeletethreads` tinyint(1) NOT NULL DEFAULT '0',
  `canpostpolls` tinyint(1) NOT NULL DEFAULT '0',
  `cansearch` tinyint(1) NOT NULL DEFAULT '0',
  `candlattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewboardclosed` tinyint(1) NOT NULL DEFAULT '0',
  `canpostattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canratethreads` tinyint(1) NOT NULL DEFAULT '0',
  `max_screenshots` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `caneditattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canviewdeletionnotice` tinyint(1) NOT NULL DEFAULT '0',
  `modposts` tinyint(1) NOT NULL DEFAULT '0',
  `modthreads` tinyint(1) NOT NULL DEFAULT '0',
  `mod_edit_posts` tinyint(1) NOT NULL DEFAULT '0',
  `modattachments` tinyint(1) NOT NULL DEFAULT '0',
  `canvotepolls` tinyint(1) NOT NULL DEFAULT '0',
  `canundovotes` tinyint(1) NOT NULL DEFAULT '0',
  `image` varchar(300) NOT NULL DEFAULT '',
  `canviewwolinvis` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`gid`),
  KEY `autoinvite` (`autoinvite`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(120) NOT NULL DEFAULT '',
  `password` varchar(120) NOT NULL DEFAULT '',
  `salt` varchar(10) NOT NULL DEFAULT '',
  `loginkey` varchar(50) NOT NULL DEFAULT '',
  `email` char(64) NOT NULL DEFAULT '',
  `ustatus` enum('pending','confirmed') NOT NULL DEFAULT 'pending',
  `enabled` enum('yes','no') NOT NULL DEFAULT 'yes',
  `added` int unsigned NOT NULL DEFAULT '0',
  `last_login` int unsigned NOT NULL DEFAULT '0',
  `timeonline` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `lastactive` int unsigned NOT NULL DEFAULT '0',
  `lastvisit` int unsigned NOT NULL DEFAULT '0',
  `stylesheet` char(32) DEFAULT NULL,
  `regip` varbinary(16) NOT NULL DEFAULT '',
  `lastip` varbinary(16) NOT NULL DEFAULT '',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `usertitle` varchar(250) NOT NULL DEFAULT '',
  `country` int unsigned NOT NULL DEFAULT '1',
  `notifs` varchar(300) NOT NULL DEFAULT '',
  `modcomment` text,
  `donor` enum('yes','no') NOT NULL DEFAULT 'no',
  `warned` enum('yes','no') NOT NULL DEFAULT 'no',
  `lastwarned` int unsigned NOT NULL DEFAULT '0',
  `warnedby` int unsigned NOT NULL DEFAULT '0',
  `timeswarned` int unsigned NOT NULL DEFAULT '0',
  `warneduntil` int unsigned NOT NULL DEFAULT '0',
  `leechwarn` enum('yes','no') NOT NULL DEFAULT 'no',
  `leechwarnuntil` int unsigned NOT NULL DEFAULT '0',
  `torrentsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `postsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `threadsperpages` smallint unsigned NOT NULL DEFAULT '0',
  `passkey` char(32) NOT NULL DEFAULT '',
  `timezone` varchar(5) NOT NULL DEFAULT '0',
  `timeformat` varchar(4) NOT NULL DEFAULT '',
  `dateformat` varchar(4) NOT NULL DEFAULT '',
  `dst` tinyint(1) NOT NULL DEFAULT '0',
  `dstcorrection` tinyint(1) NOT NULL DEFAULT '0',
  `invites` int unsigned NOT NULL DEFAULT '0',
  `invited_by` int unsigned NOT NULL DEFAULT '0',
  `seedbonus` decimal(9,1) NOT NULL DEFAULT '0.0',
  `bonuscomment` text,
  `lastinvite` int unsigned NOT NULL DEFAULT '0',
  `announce_read` enum('yes','no') NOT NULL DEFAULT 'yes',
  `usergroup` smallint unsigned NOT NULL DEFAULT '0',
  `additionalgroups` varchar(200) NOT NULL DEFAULT '',
  `displaygroup` smallint unsigned NOT NULL DEFAULT '0',
  `oldusergroup` int unsigned NOT NULL DEFAULT '2',
  `avatar` varchar(200) NOT NULL DEFAULT '',
  `avatardimensions` varchar(10) NOT NULL DEFAULT '',
  `avatartype` varchar(10) NOT NULL DEFAULT '0',
  `signature` varchar(255) DEFAULT NULL,
  `birthday` varchar(15) NOT NULL DEFAULT '',
  `birthdayprivacy` varchar(4) NOT NULL DEFAULT 'all',
  `totalpms` int unsigned NOT NULL DEFAULT '0',
  `unreadpms` int unsigned NOT NULL DEFAULT '0',
  `postnum` int unsigned NOT NULL DEFAULT '0',
  `threadnum` int unsigned NOT NULL DEFAULT '0',
  `comms` int unsigned NOT NULL DEFAULT '0',
  `invisible` tinyint(1) NOT NULL DEFAULT '0',
  `commentpm` tinyint(1) NOT NULL DEFAULT '1',
  `showredirect` tinyint(1) NOT NULL DEFAULT '0',
  `subscriptionmethod` tinyint(1) NOT NULL DEFAULT '0',
  `ignorelist` text NOT NULL,
  `loginattempts` smallint unsigned NOT NULL DEFAULT '0',
  `loginlockoutexpiry` int unsigned NOT NULL DEFAULT '0',
  `buddylist` text NOT NULL,
  `buddyrequestsauto` tinyint(1) NOT NULL DEFAULT '0',
  `buddyrequestspm` tinyint(1) NOT NULL DEFAULT '1',
  `daysprune` smallint unsigned NOT NULL DEFAULT '0',
  `pmnotice` tinyint(1) NOT NULL DEFAULT '1',
  `pmnotify` tinyint(1) NOT NULL DEFAULT '0',
  `receivepms` tinyint(1) NOT NULL DEFAULT '1',
  `pmfolders` text NOT NULL,
  `moderateposts` tinyint(1) NOT NULL DEFAULT '0',
  `moderationtime` int unsigned NOT NULL DEFAULT '0',
  `hideemail` tinyint(1) NOT NULL DEFAULT '0',
  `allownotices` tinyint(1) NOT NULL DEFAULT '0',
  `receivefrombuddy` tinyint(1) NOT NULL DEFAULT '0',
  `threadmode` varchar(8) NOT NULL DEFAULT '',
  `canupload` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `candownload` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `cancomment` tinyint UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `passkey` (`passkey`),
  KEY `uploaded` (`uploaded`),
  KEY `added` (`added`),
  KEY `usergroup` (`usergroup`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `user_devices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`         INT UNSIGNED NOT NULL,
  `fingerprint` VARCHAR(32)  NOT NULL,
  `user_agent`  VARCHAR(255) NOT NULL DEFAULT '',
  `first_seen`  INT UNSIGNED NOT NULL DEFAULT '0',
  `last_seen`   INT UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_fp` (`uid`, `fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS `bonus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bonusname` char(50) NOT NULL DEFAULT '',
  `points` decimal(5,1) NOT NULL DEFAULT '0.0',
  `description` tinytext NOT NULL,
  `art` char(10) NOT NULL DEFAULT 'traffic',
  `menge` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `buddyrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `touid` int unsigned NOT NULL DEFAULT '0',
  `date` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `touid` (`touid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `delayedmoderation` (
  `did` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(30) NOT NULL DEFAULT '',
  `delaydateline` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `tids` text NOT NULL,
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `inputs` text NOT NULL,
  PRIMARY KEY (`did`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `notconnectablepmlog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
SQL;

    // ── Execute CREATE TABLE via DB class ─────────────────────────────────────
    $statements = array_filter(array_map('trim', explode(";\n", $tables)));
    $sql_errors = [];
    foreach ($statements as $sql) {
        if (empty($sql)) continue;
        try {
            $db->sql_query($sql);
        } catch (Throwable $e) {
            $err = $e->getMessage();
            if (!str_contains($err, 'already exists')) {
                $sql_errors[] = $err;
            }
        }
    }
    if ($sql_errors) {
        return array_merge(['SQL errors:'], array_slice($sql_errors, 0, 5));
    }

    $now = time();

    // ── Default categories ──────────────────────────────────────────────────────
    $cats = [
        ['1080p',   'fa-solid fa-film fa-shake'],
        ['2160p',   'fa-solid fa-clapperboard'],
        ['720p',    'fa-solid fa-tv'],
        ['BluRay',  'fa-solid fa-compact-disc fa-spin'],
        ['UltraHD', 'fa-solid fa-satellite-dish'],
    ];
    foreach ($cats as $cat) {
        $db->insert_query(
            'categories',
            ['name' => $cat[0], 'icon' => $cat[1], 'type' => 'c', 'minclassread' => 0],
            true   // IGNORE
        );
    }

    // ── Default country ─────────────────────────────────────────────────────────
    $db->insert_query(
        'countries',
        ['id' => 1, 'name' => 'Unknown', 'flagpic' => 'unknown.gif'],
        true
    );
	
	

    // ── Create admin user ────────────────────────────────────────────────────
    $salt      = random_str();
    $loginkey  = generate_loginkey();
    $pass_hash = md5(md5($salt) . md5($admin['password']));

    $admin_id = $db->insert_query('users', [
        'username'     => $admin['username'],
        'password'     => $pass_hash,
        'salt'         => $salt,
        'loginkey'     => $loginkey,
        'email'        => $admin['email'],
        'ustatus'      => 'confirmed',
        'enabled'      => 'yes',
        'added'        => $now,
		'lastactive'   => $now,
		'lastvisit'    => $now,
        'usergroup'    => 8,
        'timezone'     => '0',
        'ignorelist'   => '',
        'buddylist'    => '',
        'pmfolders'    => '0**$%%$1**$%%$2**$%%$3**$%%$4**',
        'notifs'       => '',
		'announce_read' => 'no',
    ]);

    // ── STAFFTEAM ─────────────────────────────────────────────────────────────
    $staffteam_dir  = __DIR__ . '/config';
    $staffteam_file = $staffteam_dir . '/STAFFTEAM';
    if (!is_dir($staffteam_dir)) {
        @mkdir($staffteam_dir, 0755, true);
    }
    $errors_out = [];
    if (file_put_contents($staffteam_file, $admin['username'] . ':' . $admin_id) === false) {
        $errors_out[] = 'Cannot write config/STAFFTEAM — check permissions';
    }

    // ── News post and announcement ──────────────────────────────────────────────
    $db->insert_query('news', [
        'userid' => $admin_id,
        'added'  => $now,
        'body'   => '<p>Welcome to <strong>' . $site['name'] . '</strong>! The tracker is now online.</p>',
        'title'  => 'Welcome!',
    ]);


    $db->insert_query('announcements', [
    'subject'      => 'Welcome to ' . $site['name'],
    'message'      => '<p>The tracker is now online and ready to use!</p>',
    'uid'          => $admin_id,   // ← correct field
    'added'        => $now,
    'updated'      => $now,
    'startdate'    => $now,
    'enddate'      => 0,
    'minclassread' => 0,
    'type'         => 'tracker',
    ]);
	
	

    // ── Initialize cache and refresh news ──────────────────────────────────────
    $GLOBALS['db'] = $db;
    require_once INC_PATH . '/class_datacache.php';
    $cache = new datacache();
    $cache->cache();
    $cache->update_news();

    // ── Generate configuration files ───────────────────────────────────────────
    $securehash  = 'RT__' . preg_replace('#^https?://(www\.)?#', '', $site['url'])
                 . '_' . rand(1,9) . '_' . rand(10,99) . '-' . rand(100,999) . '-' . rand(10,99);
    $cookieDomain = $site['cookie_domain'] ?: ('.' . preg_replace('#^https?://(www\.)?#', '', $site['url']));
    $announce_url = $site['url'] . '/announce.php';
    $db_cfg       = $dbSess;

    // config.php
    $config_php  = "<?php\n";
    $config_php .= "  \$config['database']['type'] = '" . addslashes($db_cfg['type'] ?? 'mysqli') . "';\n";
    $config_php .= "  \$config['database']['database'] = '" . addslashes($db_cfg['database']) . "';\n";
    $config_php .= "  \$config['database']['table_prefix'] = '';\n";
    $config_php .= "  \$config['database']['hostname'] = '" . addslashes($db_cfg['hostname']) . "';\n";
    $config_php .= "  \$config['database']['username'] = '" . addslashes($db_cfg['username']) . "';\n";
    $config_php .= "  \$config['database']['password'] = '" . addslashes($db_cfg['password']) . "';\n";
    $config_php .= "  \$config['database']['encoding'] = 'utf8';\n";
    $config_php .= "  \$config['cache_store'] = 'files';\n";
    $config_php .= "  \$config['super_admins'] = '{$admin_id}';\n";
    $config_php .= "  \$config['log_pruning'] = array(\n";
    $config_php .= "    'admin_logs' => 365,\n    'mod_logs' => 365,\n    'task_logs' => 30,\n";
    $config_php .= "    'mail_logs' => 180,\n    'user_mail_logs' => 180,\n    'promotion_logs' => 180\n  );\n";

    // ── Insert settings from sql/default_settings.sql ───────────────────────────
    // Placeholders like {{KEY}} are replaced with real values before execution.
    $settings_sql_file = __DIR__ . '/sql/default_settings.sql';
    if (!file_exists($settings_sql_file)) {
        return ['Missing file: sql/default_settings.sql'];
    }
    $settings_sql = file_get_contents($settings_sql_file);

    // Substitute all placeholders
    $placeholders = [
        '{{SITENAME}}'     => $site['name'],
        '{{BASEURL}}'      => $site['url'],
        '{{SITEEMAIL}}'    => $site['email'],
        '{{MEMBERSONLY}}'  => $site['membersonly'],
        '{{PRIVATEPATCH}}' => $site['privatepatch'],
        '{{TIMEZONE}}'     => $site['timezone'],
        '{{COOKIEDOMAIN}}' => $cookieDomain,
        '{{SECUREHASH}}'   => $securehash,
        '{{ANNOUNCE_URL}}' => $announce_url,
        '{{SMTP_HOST}}'    => $site['smtp_host'],
        '{{SMTP_USER}}'    => $site['smtp_user'],
        '{{SMTP_PASS}}'    => $site['smtp_pass'],
        '{{SMTP_PORT}}'    => $site['smtp_port'],
        '{{DB_HOST}}'      => $db_cfg['hostname'],
        '{{DB_USER}}'      => $db_cfg['username'],
        '{{DB_PASS}}'      => $db_cfg['password'],
        '{{DB_NAME}}'      => $db_cfg['database'],
    ];
    // Escape values for SQL strings (single quotes)
    foreach ($placeholders as $key => $val) {
        $settings_sql = str_replace($key, $db->escape_string($val), $settings_sql);
    }

    // Execute as a single query
    try {
        $db->sql_query($settings_sql);
    } catch (Throwable $e) {
        return ['Failed to insert default settings: ' . $e->getMessage()];
    }

    // ── Generate settings.php from the DB table ──────────────────────────────
    // Format: $VARNAME = "value"; — this is exactly how the tracker reads it (global.php, my_setcookie, etc.)
    // Single source of truth: the DB is primary, the file is its cache.
    $settings_out = '';
    $q = $db->simple_select('settings', 'name, value');
    while ($row = $db->fetch_array($q)) {
        $n = $row['name'];                        // variable name — as-is
        $v = addcslashes($row['value'], '\\"$'); // escape \, " and $ in the value
        $settings_out .= "\${$n} = \"{$v}\";\n";
    }
    $s = "<?php\n"
       . "/*********************************\\\n"
       . "  DO NOT EDIT THIS FILE, PLEASE USE\n"
       . "  THE SETTINGS EDITOR\n"
       . "\\*********************************/\n\n"
       . $settings_out
       . "\n";

    // config_announce.php
    $announce_php  = "<?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!\n";
    $announce_php .= "if(!defined('IN_ANNOUNCE')) die('Hacking attempt!');\n";
    $announce_php .= "\$announce_actions = 'yes';\n\$aggressivecheat = 'yes';\n\$nc = 'no';\n";
    $announce_php .= "\$announce_wait = '0';\n\$announce_interval = '900';\n\$max_rate = '2097152';\n";
    $announce_php .= "\$bannedclientdetect = 'no';\n\$allowed_clients = '-UT1610-,-AZ3034-,-UT1750-';\n";
    $announce_php .= "\$detectbrowsercheats = 'no';\n\$checkconnectable = 'no';\n\$checkip = 'no';\n";
    $announce_php .= "\$mysql_host = '" . addslashes($db_cfg['hostname']) . "';\n";
    $announce_php .= "\$mysql_user = '" . addslashes($db_cfg['username']) . "';\n";
    $announce_php .= "\$mysql_pass = '" . addslashes($db_cfg['password']) . "';\n";
    $announce_php .= "\$mysql_db = '" . addslashes($db_cfg['database']) . "';\n";
    $announce_php .= "\$BASEURL = '" . addslashes($site['url']) . "';\n";
    $announce_php .= "\$SITENAME = '" . addslashes($site['name']) . "';\n";
    $announce_php .= "\$privatetrackerpatch = '" . $site['privatepatch'] . "';\n";
    $announce_php .= "\$gzipcompress = 'no';\n\$charset = 'UTF-8';\n\$aggressivecheckip = 'no';\n";
    $announce_php .= "\$snatchmod = 'yes';\n\$bonus = 'enable';\n\$kpsseed = '5.0';\n";
    $announce_php .= "\$bdayreward = 'yes';\n\$bdayrewardtype = 'freeleech';\n?>\n";

    // Write files
    if (@file_put_contents(__DIR__ . '/include/config.php', $config_php) === false) {
        $errors_out[] = 'Cannot write config.php — check permissions';
    }
    if (@file_put_contents(__DIR__ . '/include/settings.php', $s) === false) {
        $errors_out[] = 'Cannot write settings.php — check permissions';
    }
    if (@file_put_contents(__DIR__ . '/include/config_announce.php', $announce_php) === false) {
        $errors_out[] = 'Cannot write config_announce.php — check permissions';
    }
    if (!is_dir(__DIR__ . '/cache')) {
        @mkdir(__DIR__ . '/cache', 0755, true);
    }

    // ── SQL files with seed data ──────────────────────────────────────────────
    $data_sql = @file_get_contents(__DIR__ . '/sql/install_data.sql');
    if ($data_sql) {
        $data_sql = str_replace("\r\n", "\n", $data_sql);
        foreach (preg_split('/\n\n+/', $data_sql) as $block) {
            $block = trim($block);
            if (empty($block)) continue;
            $lines = array_filter(explode("\n", $block), fn($l) => strpos(trim($l), '--') !== 0);
            $sql = trim(implode("\n", $lines));
            if (!preg_match('/^INSERT/i', $sql)) continue;
            try { $db->sql_query(rtrim($sql, ';')); } catch (Throwable) {}
        }
    }

    $faq_sql = @file_get_contents(__DIR__ . '/sql/faq.sql');
    if ($faq_sql) {
        $faq_sql = str_replace("\r\n", "\n", $faq_sql);
        $faq_sql = str_ireplace('INSERT INTO faq', 'INSERT IGNORE INTO `faq`', $faq_sql);
        foreach (array_filter(array_map('trim', explode("\n", $faq_sql))) as $sql) {
            if (stripos($sql, 'INSERT') !== 0) continue;
            try { $db->sql_query(rtrim($sql, ';')); } catch (Throwable) {}
        }
    }

    $usergroups_sql = @file_get_contents(__DIR__ . '/sql/group.sql');
    if ($usergroups_sql) {
        $usergroups_sql = str_replace(["\r\n", '\\"'], ["\n", '"'], $usergroups_sql);
        $usergroups_sql = str_ireplace('INSERT IGNORE INTO usergroups', 'INSERT IGNORE INTO `usergroups`', $usergroups_sql);
        foreach (array_filter(array_map('trim', explode("\n", $usergroups_sql))) as $sql) {
            if (stripos($sql, 'INSERT') !== 0) continue;
            try { $db->sql_query(rtrim($sql, ';')); } catch (Throwable) {}
        }
    }

    return $errors_out ?: true;
}

// ── Requirements check ───────────────────────────────────────────────────────
function checkRequirements(): array
{
    return [
        ['PHP Version ≥ 8.0',    version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
        ['MySQLi',               extension_loaded('mysqli'),   extension_loaded('mysqli') ? 'OK' : 'Missing'],
        ['GD',                   extension_loaded('gd'),       extension_loaded('gd') ? 'OK' : 'Missing'],
        ['JSON',                 extension_loaded('json'),     'OK'],
        ['MB String',            extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'Missing'],
        ['OpenSSL',              extension_loaded('openssl'),  extension_loaded('openssl') ? 'OK' : 'Missing'],
        ['cURL',                 extension_loaded('curl'),     extension_loaded('curl') ? 'OK' : 'Missing'],
        ['DB base driver',       file_exists(INC_PATH . '/db_base.php'), file_exists(INC_PATH . '/db_base.php') ? 'Found' : 'Missing: include/db_base.php'],
        ['config.php writable',  is_writable(__DIR__) || (file_exists(__DIR__.'/include/config.php') && is_writable(__DIR__.'/include/config.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable'],
        ['settings.php writable',is_writable(__DIR__) || (file_exists(__DIR__.'/include/settings.php') && is_writable(__DIR__.'/include/settings.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable'],
    ];
}

$reqs  = ($step === 1) ? checkRequirements() : [];
$allOk = true;
foreach ($reqs as $r) { if (!$r[1]) { $allOk = false; break; } }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mybb Based Torrent Tracker — Installer v<?= INSTALLER_VERSION ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    :root { --primary-gradient: linear-gradient(135deg,#667eea 0%,#764ba2 100%); --success-gradient: linear-gradient(135deg,#84fab0 0%,#8fd3f4 100%); }
    .installer-container { max-width:800px; margin:0 auto; }
    .main-card { background:#fff; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
    .card-header { background:var(--primary-gradient); color:#fff; padding:25px 30px; border-bottom:none; }
    .step-progress { padding:25px 30px; background:#f8f9fa; border-bottom:1px solid #e9ecef; }
    .step-item { position:relative; flex:1; text-align:center; }
    .step-circle { width:40px; height:40px; background:#dee2e6; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; font-weight:bold; color:#6c757d; transition:all .3s ease; position:relative; z-index:2; }
    .step-item.active .step-circle { background:#667eea; color:#fff; box-shadow:0 0 0 4px rgba(102,126,234,.2); transform:scale(1.1); }
    .step-item.completed .step-circle { background:#28a745; color:#fff; }
    .step-label { font-size:12px; color:#6c757d; font-weight:500; }
    .step-item.active .step-label { color:#667eea; font-weight:600; }
    .step-connector { position:absolute; top:20px; left:50%; width:100%; height:2px; background:#dee2e6; z-index:1; }
    .step-item:last-child .step-connector { display:none; }
    .form-control,.form-select { border-radius:12px; border:2px solid #e9ecef; transition:all .3s ease; padding:10px 15px; }
    .form-control:focus,.form-select:focus { border-color:#667eea; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); }
    .btn { border-radius:12px; padding:12px 24px; font-weight:600; transition:all .3s ease; }
    .btn-primary { background:var(--primary-gradient); border:none; }
    .btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(102,126,234,.3); }
    .btn-success { background:var(--success-gradient); border:none; color:#333; }
    .btn-success:hover { transform:translateY(-2px); box-shadow:0 10px 20px rgba(132,250,176,.3); }
    .requirement-item { display:flex; align-items:center; padding:12px 0; border-bottom:1px solid #f0f0f0; }
    .requirement-item:last-child { border-bottom:none; }
    .requirement-status { width:30px; text-align:center; margin-right:15px; }
    .info-card { background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%); border-radius:15px; padding:20px; margin-bottom:20px; }
    .alert-custom { border-radius:15px; border-left:4px solid; }
    .fade-in { animation:fadeIn .5s ease-in; }
    @keyframes fadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
</style>
</head>
<body>
<div class="installer-container fade-in p-3">
    <div class="main-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-satellite-dish me-2"></i>Mybb Based Torrent Tracker Installer</h2>
                    <p class="mb-0 opacity-75">Professional Torrent Tracker Setup</p>
                </div>
                <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                    <i class="fas fa-code-branch me-1"></i> v<?= INSTALLER_VERSION ?>
                </span>
            </div>
        </div>

        <div class="step-progress">
            <div class="d-flex justify-content-between position-relative">
                <?php foreach ($steps as $num => $label): ?>
                <div class="step-item <?= $num < $step ? 'completed' : ($num == $step ? 'active' : '') ?>">
                    <div class="step-circle">
                        <?php if ($num < $step): ?><i class="fas fa-check"></i><?php else: ?><?= $num ?><?php endif; ?>
                    </div>
                    <div class="step-label"><?= $label ?></div>
                    <?php if ($num < count($steps)): ?><div class="step-connector"></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4">
            <?php if ($errors): ?>
            <div class="alert alert-danger alert-custom mb-4" style="border-left-color:#dc3545">
                <div class="d-flex">
                    <div class="me-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                    <div>
                        <strong>Errors:</strong>
                        <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-clipboard-list fa-2x me-3"></i><h5 class="mb-0">System Requirements Check</h5></div><p class="text-muted small mb-0">All checks must pass before proceeding.</p></div>
            <div class="mb-4">
                <?php foreach ($reqs as $req): ?>
                <div class="requirement-item">
                    <div class="requirement-status"><i class="fas fa-<?= $req[1] ? 'check-circle text-success' : 'times-circle text-danger' ?> fa-lg"></i></div>
                    <div class="flex-grow-1"><strong><?= $req[0] ?></strong><div class="small text-muted"><?= htmlspecialchars($req[2]) ?></div></div>
                    <span class="badge bg-<?= $req[1] ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $req[1] ? 'success' : 'danger' ?> px-3 py-1 rounded-pill">
                        <i class="fas fa-<?= $req[1] ? 'check' : 'times' ?> me-1"></i><?= $req[1] ? 'Passed' : 'Failed' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($allOk): ?>
                <a href="install.php?step=2" class="btn btn-primary btn-lg w-100"><i class="fas fa-arrow-right me-2"></i>Continue to Database Setup</a>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-3"><i class="fas fa-tools me-2"></i>Please fix the issues above before continuing</div>
                <a href="install.php?step=1" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt me-2"></i>Recheck</a>
            <?php endif; ?>

            <?php elseif ($step === 2): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-database fa-2x me-3"></i><h5 class="mb-0">Database Configuration</h5></div><p class="text-muted small mb-0">Connects via your DB class (db_base.php + driver).</p></div>
            <form method="post" action="install.php?step=2">
                <input type="hidden" name="db_type" value="mysqli">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-server me-1"></i>Database Host</label>
                        <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_SESSION['db']['hostname'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-database me-1"></i>Database Name</label>
                        <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_SESSION['db']['database'] ?? '') ?>" placeholder="tracker_db" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-user me-1"></i>Username</label>
                        <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_SESSION['db']['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-lock me-1"></i>Password</label>
                        <input type="password" class="form-control" name="db_pass" value="<?= htmlspecialchars($_SESSION['db']['password'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-database me-2"></i>Test Connection & Continue</button>
            </form>

            <?php elseif ($step === 3): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-globe fa-2x me-3"></i><h5 class="mb-0">Site Configuration</h5></div><p class="text-muted small mb-0">Basic tracker settings.</p></div>
            <form method="post" action="install.php?step=3">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-tag me-1"></i>Site Name</label>
                        <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($_SESSION['site']['name'] ?? '') ?>" placeholder="My Awesome Tracker" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold"><i class="fas fa-link me-1"></i>Site URL</label>
                        <input type="url" class="form-control" name="site_url" value="<?= htmlspecialchars($_SESSION['site']['url'] ?? '') ?>" placeholder="https://tracker.example.com" required>
                        <small class="text-muted">No trailing slash.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-envelope me-1"></i>Site Email</label>
                        <input type="email" class="form-control" name="site_email" value="<?= htmlspecialchars($_SESSION['site']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-clock me-1"></i>Timezone</label>
                        <select class="form-select" name="timezone">
                            <?php for ($i=-12;$i<=14;$i++): ?>
                            <option value="<?=$i?>" <?=(($_SESSION['site']['timezone']??2)==$i)?'selected':''?>>UTC <?=$i>=0?'+'.$i:$i?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="membersonly" id="membersonly" <?= ($_SESSION['site']['membersonly'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="membersonly"><i class="fas fa-users me-1"></i>Members Only Mode</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="privatepatch" id="privatepatch" <?= ($_SESSION['site']['privatepatch'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="privatepatch"><i class="fas fa-lock me-1"></i>Private Tracker Mode</label>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <h6 class="mb-3"><i class="fas fa-envelope me-2"></i>SMTP <small class="text-muted">(Optional)</small></h6>
                <div class="row g-3">
                    <div class="col-md-8"><input type="text" class="form-control" name="smtp_host" value="<?= htmlspecialchars($_SESSION['site']['smtp_host'] ?? 'smtp.gmail.com') ?>" placeholder="SMTP Host"></div>
                    <div class="col-md-4"><input type="text" class="form-control" name="smtp_port" value="<?= htmlspecialchars($_SESSION['site']['smtp_port'] ?? '587') ?>" placeholder="Port"></div>
                    <div class="col-md-6"><input type="text" class="form-control" name="smtp_user" value="<?= htmlspecialchars($_SESSION['site']['smtp_user'] ?? '') ?>" placeholder="SMTP Username"></div>
                    <div class="col-md-6"><input type="password" class="form-control" name="smtp_pass" value="<?= htmlspecialchars($_SESSION['site']['smtp_pass'] ?? '') ?>" placeholder="SMTP Password"></div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-arrow-right me-2"></i>Continue to Admin Setup</button>
            </form>

            <?php elseif ($step === 4): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-user-shield fa-2x me-3"></i><h5 class="mb-0">Administrator Account</h5></div><p class="text-muted small mb-0">Master admin for your tracker.</p></div>
            <form method="post" action="install.php?step=4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-user me-1"></i>Username</label>
                        <input type="text" class="form-control" name="admin_user" value="<?= htmlspecialchars($_SESSION['admin']['username'] ?? '') ?>" required minlength="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-envelope me-1"></i>Email</label>
                        <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-lock me-1"></i>Password</label>
                        <input type="password" class="form-control" name="admin_pass" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><i class="fas fa-check-double me-1"></i>Confirm Password</label>
                        <input type="password" class="form-control" name="admin_confirm" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="fas fa-arrow-right me-2"></i>Continue to Installation</button>
            </form>

            <?php elseif ($step === 5): ?>
            <div class="info-card"><div class="d-flex align-items-center mb-3"><i class="fas fa-rocket fa-2x me-3"></i><h5 class="mb-0">Ready to Install</h5></div></div>
            <div class="bg-light rounded p-3 mb-4">
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Summary</h6>
                <div class="row g-2 small">
                    <div class="col-4 fw-semibold">Driver:</div><div class="col-8"><?= htmlspecialchars(strtoupper($_SESSION['db']['type'] ?? 'mysqli')) ?></div>
                    <div class="col-4 fw-semibold">Database:</div><div class="col-8"><?= htmlspecialchars(($_SESSION['db']['database']??'').'@'.($_SESSION['db']['hostname']??'')) ?></div>
                    <div class="col-4 fw-semibold">Site:</div><div class="col-8"><?= htmlspecialchars($_SESSION['site']['name']??'') ?> — <?= htmlspecialchars($_SESSION['site']['url']??'') ?></div>
                    <div class="col-4 fw-semibold">Admin:</div><div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?> &lt;<?= htmlspecialchars($_SESSION['admin']['email']??'') ?>&gt;</div>
                </div>
            </div>
            <div class="alert alert-warning alert-custom mb-4" style="border-left-color:#ffc107">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Warning:</strong> This will create all database tables and config files. Cannot be undone.
            </div>
            <form method="post" action="install.php?step=5">
                <button type="submit" class="btn btn-success w-100 btn-lg"><i class="fas fa-download me-2"></i>Install Mybb Based Torrent Tracker Now!</button>
            </form>

            <?php elseif ($step === 6): ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle text-success mb-4" style="font-size:5rem;display:block"></i>
                <h3 class="text-success fw-bold mb-3">Installation Complete!</h3>
                <p class="text-muted mb-4">Mybb Based Torrent Tracker has been successfully installed and configured.</p>
                <div class="alert alert-danger text-start mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Security:</strong> Delete <code>install.php</code> from your server immediately!
                </div>
                <div class="info-card text-start mb-4">
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Access</h6>
                    <div class="row g-2 small">
                        <div class="col-4 fw-semibold">Site:</div>
                        <div class="col-8"><a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" target="_blank"><?= htmlspecialchars($_SESSION['site']['url']??'/') ?></a></div>
                        <div class="col-4 fw-semibold">Admin Panel:</div>
                        <div class="col-8"><a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" target="_blank">admin/index.php</a></div>
                        <div class="col-4 fw-semibold">Username:</div>
                        <div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?></div>
                    </div>
                </div>
                <div class="d-grid gap-3">
                    <a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" class="btn btn-primary btn-lg"><i class="fas fa-home me-2"></i>Go to Tracker</a>
                    <a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" class="btn btn-outline-primary btn-lg"><i class="fas fa-crown me-2"></i>Admin Panel</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-center mt-3">
        <small class="text-white-50">
            <i class="fas fa-code me-1"></i> Mybb Based Torrent Tracker Installer v<?= INSTALLER_VERSION ?> |
            <a href="install.php?step=1" class="text-white-50 text-decoration-none">Start Over</a>
        </small>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>