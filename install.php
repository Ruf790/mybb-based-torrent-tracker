<?php
/**
 * Ruff Tracker — Installer v1.1
 * Точная схема под реальную БД трекера
 */
const IN_TRACKER = true;
const APP_INITIALIZED = true;
define('IN_MYBB', 1);
define('MYBB_ROOT', __DIR__ . '/');
define('INC_PATH', __DIR__ . '/include');

require_once INC_PATH . '/functions.php';
require_once INC_PATH . '/functions_user.php';


define('INSTALLER_VERSION', '1.1.0');
define('LOCK_FILE', __DIR__ . '/install.lock');

if (file_exists(LOCK_FILE) && !isset($_GET['force'])) {
    die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Already Installed | Ruff Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
       
        .lock-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transition: transform 0.3s ease;
        }
        .lock-card:hover {
            transform: translateY(-5px);
        }
        .lock-icon {
            font-size: 5rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .danger-btn {
            transition: all 0.3s ease;
        }
        .danger-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }
        code {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 8px;
            font-weight: 600;
            color: #dc3545;
        }
        .warning-alert {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; margin: 0; padding: 20px;">
    <div class="container" style="max-width: 550px;">
        <div class="lock-card p-5">
            <div class="text-center mb-4">
                <div class="lock-icon mb-3">
                    <i class="fas fa-lock-circle fa-4x text-danger"></i>
                </div>
                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                        <i class="fas fa-shield-alt me-2"></i>Installation Locked
                    </span>
                </div>
                <h2 class="fw-bold mb-2" style="color: #1a1a2e;">Already Installed</h2>
                <p class="text-muted mb-1">Ruff Tracker is already installed and configured.</p>
                <p class="text-muted">Delete <code>install.lock</code> to reinstall.</p>
            </div>

            <hr class="my-4">

            <div class="warning-alert p-3 rounded mb-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                    </div>
                    <div class="ms-3">
                        <strong class="d-block mb-1">Warning!</strong>
                        <small class="text-muted">Force reinstall will overwrite your current settings and data.</small>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-3">
                <a href="install.php?force=1" class="btn btn-outline-danger btn-lg danger-btn">
                    <i class="fas fa-rotate-right me-2"></i>Force Reinstall
                </a>
                <a href="/" class="btn btn-secondary btn-lg">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Need help? Check the documentation
                </small>
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

// ── POST обработка ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 2) {
        $_SESSION['db'] = [
            'hostname' => trim($_POST['db_host'] ?? 'localhost'),
            'database' => trim($_POST['db_name'] ?? ''),
            'username' => trim($_POST['db_user'] ?? ''),
            'password' => $_POST['db_pass'] ?? '',
            'prefix'   => trim($_POST['db_prefix'] ?? ''),
        ];
        $conn = @mysqli_connect($_SESSION['db']['hostname'], $_SESSION['db']['username'], $_SESSION['db']['password'], $_SESSION['db']['database']);
        if (!$conn) {
            $errors[] = 'Cannot connect to database: ' . mysqli_connect_error();
        } else {
            //mysqli_close($conn);
            header('Location: install.php?step=3'); exit;
        }
    }

    if ($step === 3) {
        $url = rtrim(trim($_POST['site_url'] ?? ''), '/');
        $_SESSION['site'] = [
            'name'          => trim($_POST['site_name'] ?? ''),
            'url'           => $url,
            'email'         => trim($_POST['site_email'] ?? ''),
            'timezone'      => trim($_POST['timezone'] ?? '2'),
            'cookie_domain' => trim($_POST['cookie_domain'] ?? ''),
            'smtp_host'     => trim($_POST['smtp_host'] ?? 'smtp.gmail.com'),
            'smtp_user'     => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass'     => $_POST['smtp_pass'] ?? '',
            'smtp_port'     => trim($_POST['smtp_port'] ?? '587'),
            'membersonly'   => isset($_POST['membersonly']) ? 'yes' : 'no',
            'privatepatch'  => isset($_POST['privatepatch']) ? 'yes' : 'no',
        ];
        if (!$_SESSION['site']['name']) $errors[] = 'Site name is required.';
        if (!$_SESSION['site']['url'])  $errors[] = 'Site URL is required.';
        if (!$_SESSION['site']['email']) $errors[] = 'Site email is required.';
        if (!$errors) { header('Location: install.php?step=4'); exit; }
    }

    if ($step === 4) {
        $_SESSION['admin'] = [
            'username' => trim($_POST['admin_user'] ?? ''),
            'email'    => trim($_POST['admin_email'] ?? ''),
            'password' => $_POST['admin_pass'] ?? '',
            'confirm'  => $_POST['admin_confirm'] ?? '',
        ];
        if (!$_SESSION['admin']['username']) $errors[] = 'Admin username is required.';
        if (!$_SESSION['admin']['email'])    $errors[] = 'Admin email is required.';
        if (strlen($_SESSION['admin']['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($_SESSION['admin']['password'] !== $_SESSION['admin']['confirm']) $errors[] = 'Passwords do not match.';
        if (!$errors) { header('Location: install.php?step=5'); exit; }
    }

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

// ── Основная функция установки ────────────────────────────────────────────────
function runInstallation() {
    $db    = $_SESSION['db']    ?? [];
    $site  = $_SESSION['site']  ?? [];
    $admin = $_SESSION['admin'] ?? [];

    $conn = @mysqli_connect($db['hostname'], $db['username'], $db['password'], $db['database']);
    if (!$conn) return ['Database connection failed: ' . mysqli_connect_error()];
    mysqli_set_charset($conn, 'utf8mb4');

    $p = $db['prefix'];

    // ── SQL — точная схема трекера ────────────────────────────────────────────
    $tables = <<<SQL

CREATE TABLE IF NOT EXISTS `{$p}adminlog` (
  `uid` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `module` varchar(50) NOT NULL DEFAULT '',
  `action` varchar(50) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  KEY `module` (`module`,`action`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}announce_actions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `userid` int unsigned NOT NULL DEFAULT '0',
  `ip` char(15) NOT NULL DEFAULT '',
  `passkey` char(32) NOT NULL DEFAULT '',
  `actionmessage` tinytext NOT NULL,
  `actiontime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}announcements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(85) NOT NULL,
  `message` text NOT NULL,
  `by` varchar(64) NOT NULL DEFAULT 'Admin',
  `added` int unsigned NOT NULL DEFAULT '0',
  `updated` int unsigned NOT NULL DEFAULT '0',
  `minclassread` tinyint NOT NULL DEFAULT '1',
  `views` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_updated` (`updated`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}attachments` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
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
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}attachtypes` (
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

CREATE TABLE IF NOT EXISTS `{$p}awaitingactivation` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `code` varchar(100) NOT NULL DEFAULT '',
  `type` char(1) NOT NULL DEFAULT '',
  `validated` tinyint(1) NOT NULL DEFAULT '0',
  `misc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}banfilters` (
  `fid` int unsigned NOT NULL AUTO_INCREMENT,
  `filter` varchar(200) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `lastuse` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fid`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}banned` (
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

CREATE TABLE IF NOT EXISTS `{$p}bannedemails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `value` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}bookmarks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`,`torrentid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}categories` (
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

CREATE TABLE IF NOT EXISTS `{$p}cheat_attempts` (
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}comment_files` (
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

CREATE TABLE IF NOT EXISTS `{$p}comments` (
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

CREATE TABLE IF NOT EXISTS `{$p}countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `flagpic` char(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}cron` (
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

CREATE TABLE IF NOT EXISTS `{$p}cron_log` (
  `filename` char(100) NOT NULL DEFAULT '',
  `querycount` smallint unsigned NOT NULL DEFAULT '0',
  `executetime` char(10) NOT NULL DEFAULT '0',
  `runtime` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `filename` (`filename`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}datacache` (
  `title` varchar(50) NOT NULL DEFAULT '',
  `cache` mediumtext NOT NULL,
  PRIMARY KEY (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}faq` (
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

CREATE TABLE IF NOT EXISTS `{$p}forumpermissions` (
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

CREATE TABLE IF NOT EXISTS `{$p}hit_and_run` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `torrentid` int unsigned NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}inactivity` (
  `userid` int unsigned NOT NULL DEFAULT '0',
  `inactivitytag` int unsigned NOT NULL DEFAULT '0',
  KEY `userid` (`userid`),
  KEY `inactivitytag` (`inactivitytag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS `{$p}invites` (
    `id`          int unsigned NOT NULL AUTO_INCREMENT,
    `code`        varchar(64)  NOT NULL,
    `inviter_id`  int unsigned NOT NULL DEFAULT '0',
    `invitee_id`  int unsigned          DEFAULT NULL,
    `email`       varchar(64)           DEFAULT NULL,
    `status`      enum('pending','used','expired','revoked') NOT NULL DEFAULT 'pending',
    `created_at`  int unsigned NOT NULL DEFAULT '0',
    `expires_at`  int unsigned          DEFAULT NULL,
    `used_at`     int unsigned          DEFAULT NULL,
    `ip_created`  varchar(45)           DEFAULT NULL,
    `ip_used`     varchar(45)           DEFAULT NULL,
    `note`        varchar(255)          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `inviter_id` (`inviter_id`),
    KEY `status` (`status`),
    KEY `expires_at` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;







CREATE TABLE IF NOT EXISTS `{$p}iplog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `userid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}loginattempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `added` int unsigned NOT NULL DEFAULT '0',
  `banned` enum('yes','no') NOT NULL DEFAULT 'no',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `type` enum('login','recover') NOT NULL DEFAULT 'login',
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}mailerrors` (
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

CREATE TABLE IF NOT EXISTS `{$p}maillogs` (
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

CREATE TABLE IF NOT EXISTS `{$p}mailqueue` (
  `mid` int unsigned NOT NULL AUTO_INCREMENT,
  `mailto` varchar(200) NOT NULL,
  `mailfrom` varchar(200) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `headers` text NOT NULL,
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}moderatorlog` (
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

CREATE TABLE IF NOT EXISTS `{$p}moderators` (
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

CREATE TABLE IF NOT EXISTS `{$p}news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL DEFAULT '0',
  `added` int unsigned NOT NULL DEFAULT '0',
  `body` text NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `added` (`added`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}password_reset_tokens` (
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

CREATE TABLE IF NOT EXISTS `{$p}peers` (
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

CREATE TABLE IF NOT EXISTS `{$p}privatemessages` (
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

CREATE TABLE IF NOT EXISTS `{$p}reports` (
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

CREATE TABLE IF NOT EXISTS `{$p}rules` (
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

CREATE TABLE IF NOT EXISTS `{$p}screenshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` int unsigned NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}searchlog` (
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

CREATE TABLE IF NOT EXISTS `{$p}seedbonus_settings` (
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

CREATE TABLE IF NOT EXISTS `{$p}sessions` (
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

CREATE TABLE IF NOT EXISTS `{$p}settings` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}sitelog` (
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

CREATE TABLE IF NOT EXISTS `{$p}smilies` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `stitle` char(100) NOT NULL DEFAULT '',
  `stext` char(20) NOT NULL DEFAULT '',
  `spath` char(100) NOT NULL DEFAULT '',
  `sorder` smallint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}snatched` (
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
  PRIMARY KEY (`id`),
  KEY `seeder` (`seeder`,`last_action`),
  KEY `torrentid` (`torrentid`),
  KEY `userid` (`userid`),
  KEY `finished` (`finished`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}spiders` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `theme` smallint unsigned NOT NULL DEFAULT '0',
  `language` varchar(20) NOT NULL DEFAULT '',
  `usergroup` smallint unsigned NOT NULL DEFAULT '0',
  `useragent` varchar(200) NOT NULL DEFAULT '',
  `lastvisit` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}staffmessages` (
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

CREATE TABLE IF NOT EXISTS `{$p}staffpanel` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` char(32) NOT NULL DEFAULT '',
  `description` varchar(64) NOT NULL DEFAULT '',
  `filename` char(32) NOT NULL DEFAULT '',
  `usergroups` char(32) NOT NULL DEFAULT '[8]',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}stats` (
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

CREATE TABLE IF NOT EXISTS `{$p}templates` (
  `tid` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(120) NOT NULL DEFAULT '',
  `template` text NOT NULL,
  `sid` smallint NOT NULL DEFAULT '0',
  `version` varchar(20) NOT NULL DEFAULT '0',
  `status` varchar(10) NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `sid` (`sid`,`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}torrents` (
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
  `sticky` enum('yes','no') NOT NULL DEFAULT 'no',
  `allowcomments` enum('yes','no') NOT NULL DEFAULT 'yes',
  `t_image` varchar(255) NOT NULL DEFAULT '',
  `t_image2` varchar(255) NOT NULL DEFAULT '',
  `t_link` text NOT NULL,
  `isnuked` enum('yes','no') NOT NULL DEFAULT 'no',
  `WhyNuked` varchar(255) NOT NULL DEFAULT 'Bad quality!',
  `isrequest` enum('yes','no') NOT NULL DEFAULT 'no',
  `ts_external` enum('yes','no') NOT NULL DEFAULT 'no',
  `ts_external_url` varchar(128) NOT NULL DEFAULT '',
  `ts_external_lastupdate` int unsigned NOT NULL DEFAULT '0',
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

CREATE TABLE IF NOT EXISTS `{$p}torrents_nfo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int unsigned NOT NULL,
  `nfo` longtext NOT NULL,
  `uploaded_at` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `torrent_id` (`torrent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}tsf_announcements` (
  `aid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` int NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `subject` varchar(120) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `startdate` int unsigned NOT NULL DEFAULT '0',
  `enddate` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`aid`),
  KEY `fid` (`fid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}tsf_forums` (
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

CREATE TABLE IF NOT EXISTS `{$p}tsf_forumsread` (
  `fid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `fid` (`fid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}tsf_forumsubscriptions` (
  `fsid` int unsigned NOT NULL AUTO_INCREMENT,
  `fid` smallint unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fsid`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}tsf_moderators` (
  `moderatorid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `userid` int unsigned NOT NULL DEFAULT '0',
  `forumid` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`moderatorid`),
  KEY `userid` (`userid`,`forumid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}tsf_polls` (
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

CREATE TABLE IF NOT EXISTS `{$p}tsf_pollvotes` (
  `vid` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `voteoption` smallint unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  `ipaddress` varbinary(16) NOT NULL DEFAULT '',
  PRIMARY KEY (`vid`),
  KEY `pid` (`pid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}tsf_posts` (
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

CREATE TABLE IF NOT EXISTS `{$p}tsf_threads` (
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

CREATE TABLE IF NOT EXISTS `{$p}tsf_threadsread` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  `uid` int unsigned NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `tid` (`tid`,`uid`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}tsf_threadsubscriptions` (
  `sid` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `tid` int unsigned NOT NULL DEFAULT '0',
  `notification` tinyint(1) NOT NULL DEFAULT '0',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`),
  KEY `uid` (`uid`),
  KEY `tid` (`tid`,`notification`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}tsf_threadviews` (
  `tid` int unsigned NOT NULL DEFAULT '0',
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}unbanrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '',
  `realip` char(15) NOT NULL DEFAULT '',
  `email` char(64) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `added` int unsigned NOT NULL DEFAULT '0',
  `reply` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}userfields` (
  `ufid` int unsigned NOT NULL DEFAULT '0',
  `fid1` text NOT NULL,
  `fid2` text NOT NULL,
  `fid3` text NOT NULL,
  PRIMARY KEY (`ufid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}usergroups` (
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

CREATE TABLE IF NOT EXISTS `{$p}users` (
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
  `showavatars` tinyint(1) NOT NULL DEFAULT '0',
  `showsigs` tinyint(1) NOT NULL DEFAULT '0',
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `passkey` (`passkey`),
  KEY `uploaded` (`uploaded`),
  KEY `added` (`added`),
  KEY `usergroup` (`usergroup`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}users_perm` (
  `userid` int unsigned NOT NULL DEFAULT '0',
  `canupload` tinyint unsigned NOT NULL DEFAULT '1',
  `candownload` tinyint unsigned NOT NULL DEFAULT '1',
  `cancomment` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}bonus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bonusname` char(50) NOT NULL DEFAULT '',
  `points` decimal(5,1) NOT NULL DEFAULT '0.0',
  `description` tinytext NOT NULL,
  `art` char(10) NOT NULL DEFAULT 'traffic',
  `menge` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$p}buddyrequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `touid` int unsigned NOT NULL DEFAULT '0',
  `date` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `touid` (`touid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}delayedmoderation` (
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

CREATE TABLE IF NOT EXISTS `{$p}helpdocs` (
  `hid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `sid` smallint unsigned NOT NULL DEFAULT '0',
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `document` text NOT NULL,
  `usetranslation` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`hid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}helpsections` (
  `sid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `usetranslation` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `disporder` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}modtools` (
  `tid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `forums` text NOT NULL,
  `groups` text NOT NULL,
  `type` char(1) NOT NULL DEFAULT '',
  `postoptions` text NOT NULL,
  `threadoptions` text NOT NULL,
  PRIMARY KEY (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

CREATE TABLE IF NOT EXISTS `{$p}notconnectablepmlog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

SQL;

    // Выполняем все таблицы по одной (mysqli_multi_query может глючить)
    $statements = array_filter(array_map('trim', explode(";\n", $tables)));
    $sql_errors = [];
    foreach ($statements as $sql) {
        if (empty($sql)) continue;
        if (!mysqli_query($conn, $sql)) {
            $err = mysqli_error($conn);
            if (!str_contains($err, 'already exists')) {
                $sql_errors[] = $err;
            }
        }
    }
    if ($sql_errors) {
        //mysqli_close($conn);
        return array_merge(['SQL errors:'], array_slice($sql_errors, 0, 5));
    }

    // ── Базовые группы пользователей (точно как в usergroups) ────────────────
    $now = time();
    

    // ── Базовые категории ─────────────────────────────────────────────────────
    $cats = [
        ['1080p',   'fa-solid fa-film fa-shake'],
        ['2160p',   'fa-solid fa-clapperboard'],
        ['720p',    'fa-solid fa-tv'],
        ['BluRay',  'fa-solid fa-compact-disc fa-spin'],
        ['UltraHD', 'fa-solid fa-satellite-dish'],
    ];
    foreach ($cats as $cat) {
        $name = mysqli_real_escape_string($conn, $cat[0]);
        $icon = mysqli_real_escape_string($conn, $cat[1]);
        mysqli_query($conn, "INSERT IGNORE INTO `{$p}categories` (`name`,`icon`,`type`,`minclassread`) VALUES ('{$name}','{$icon}','c',0)");
    }


    // ── Страна по умолчанию ───────────────────────────────────────────────────
    mysqli_query($conn, "INSERT IGNORE INTO `{$p}countries` (`id`,`name`,`flagpic`) VALUES (1,'Unknown','unknown.gif')");

    // ── Создаём admin пользователя ────────────────────────────────────────────
    $salt      = random_str();
    $loginkey  = generate_loginkey();
    $pass_hash = md5(md5($salt) . md5($admin['password']));

   

    $uname  = mysqli_real_escape_string($conn, $admin['username']);
    $uemail = mysqli_real_escape_string($conn, $admin['email']);

    
    mysqli_query($conn, "INSERT INTO `{$p}users`
    (`username`,`password`,`salt`,`loginkey`,`email`,`ustatus`,`enabled`,
     `added`,`usergroup`,`timezone`,`ignorelist`,`buddylist`,`pmfolders`,`notifs`)
    VALUES
    ('{$uname}','{$pass_hash}','{$salt}','{$loginkey}','{$uemail}','confirmed','yes',
     {$now},8,'0','','','0**\$%%\$1**\$%%\$2**\$%%\$3**\$%%\$4**','')");
    $admin_id = (int)mysqli_insert_id($conn);
	
	
// ── STAFFTEAM ─────────────────────────────────────────────────────────────────
$staffteam_dir  = __DIR__ . '/config';
$staffteam_file = $staffteam_dir . '/STAFFTEAM';

if (!is_dir($staffteam_dir)) {
    @mkdir($staffteam_dir, 0755, true);
}

$staffteam_content = $admin['username'] . ':' . $admin_id;

if (file_put_contents($staffteam_file, $staffteam_content) === false) {
    $errors_out[] = 'Cannot write config/STAFFTEAM — check permissions';
}
	
    

    // userfields для admin
    mysqli_query($conn, "INSERT IGNORE INTO `{$p}userfields` (`ufid`,`fid1`,`fid2`,`fid3`) VALUES ({$admin_id},'','','')");

    // Новость
    $siteName = mysqli_real_escape_string($conn, $site['name']);
    mysqli_query($conn, "INSERT IGNORE INTO `{$p}news` (`userid`,`added`,`body`,`title`) VALUES ({$admin_id},{$now},'<p>Welcome to <strong>{$siteName}</strong>! The tracker is now online.</p>','Welcome!')");

    // Объявление
    $by = mysqli_real_escape_string($conn, $admin['username']);
    mysqli_query($conn, "INSERT IGNORE INTO `{$p}announcements` (`subject`,`message`,`by`,`added`,`updated`,`minclassread`) VALUES ('Welcome to {$siteName}','<p>The tracker is now online and ready to use!</p>','{$by}',{$now},{$now},0)");

    //mysqli_close($conn);

    // ── Генерация config.php ──────────────────────────────────────────────────
    $securehash = 'RT__' . preg_replace('#^https?://(www\.)?#', '', $site['url']) . '_' . rand(1,9) . '_' . rand(10,99) . '-' . rand(100,999) . '-' . rand(10,99);
    $cookieDomain = $site['cookie_domain'] ?: ('.' . preg_replace('#^https?://(www\.)?#', '', $site['url']));

    $config_php  = "<?php\n";
    $config_php .= "  \$config['database']['type'] = 'mysqli';\n";
    $config_php .= "  \$config['database']['database'] = '" . addslashes($db['database']) . "';\n";
    $config_php .= "  \$config['database']['table_prefix'] = '" . addslashes($db['prefix']) . "';\n";
    $config_php .= "  \$config['database']['hostname'] = '" . addslashes($db['hostname']) . "';\n";
    $config_php .= "  \$config['database']['username'] = '" . addslashes($db['username']) . "';\n";
    $config_php .= "  \$config['database']['password'] = '" . addslashes($db['password']) . "';\n";
    $config_php .= "  \$config['database']['encoding'] = 'utf8';\n";
    $config_php .= "  \$config['cache_store'] = 'files';\n";
    $config_php .= "  \$config['super_admins'] = '{$admin_id}';\n";
    $config_php .= "  \$config['log_pruning'] = array(\n";
    $config_php .= "    'admin_logs' => 365,\n    'mod_logs' => 365,\n    'task_logs' => 30,\n";
    $config_php .= "    'mail_logs' => 180,\n    'user_mail_logs' => 180,\n    'promotion_logs' => 180\n  );\n";

    // ── Генерация settings.php ────────────────────────────────────────────────
    $announce_url = $site['url'] . '/announce.php';
    $s = "<?php\n/*********************************\\\n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\\*********************************/\n";
    $s .= "\$SITENAME = \"" . addslashes($site['name']) . "\";\n";
    $s .= "\$BASEURL = \"" . addslashes($site['url']) . "\";\n";
    $s .= "\$SITEEMAIL = \"" . addslashes($site['email']) . "\";\n";
    $s .= "\$REPORTMAIL = \"" . addslashes($site['email']) . "\";\n";
    $s .= "\$contactemail = \"" . addslashes($site['email']) . "\";\n";
    $s .= "\$SITEONLINE = \"yes\";\n";
    $s .= "\$useajax = \"yes\";\n";
    $s .= "\$externalscrape = \"yes\";\n";
    $s .= "\$includeexpeers = \"no\";\n";
    $s .= "\$MEMBERSONLY = \"" . $site['membersonly'] . "\";\n";
    $s .= "\$aggressivecheckip = \"no\";\n";
    $s .= "\$aggressivecheckemail = \"no\";\n";
    $s .= "\$maxloginattempts = \"7\";\n";
    $s .= "\$securehash = \"{$securehash}\";\n";
    $s .= "\$charset = \"UTF-8\";\n";
    $s .= "\$shoutboxcharset = \"UTF-8\";\n";
    $s .= "\$metakeywords = \"torrent, tracker\";\n";
    $s .= "\$metadesc = \"" . addslashes($site['name']) . " - Torrent Tracker\";\n";
    $s .= "\$slogan = \"The Best Tracker\";\n";
    $s .= "\$usezip = \"no\";\n";
    $s .= "\$iplog1 = \"yes\";\n";
    $s .= "\$ctracker = \"yes\";\n";
    $s .= "\$gzipcompress = \"no\";\n";
    $s .= "\$snatchmod = \"yes\";\n";
    $s .= "\$ts_perpage = \"20\";\n";
    $s .= "\$loadlimit = \"\";\n";
    $s .= "\$useredirectsystem = \"yes\";\n";
    $s .= "\$vkeyword = \"no\";\n";
    $s .= "\$privatetrackerpatch = \"" . $site['privatepatch'] . "\";\n";
    $s .= "\$iv = \"no\";\n";
    $s .= "\$dateformat = \"l, jS F, Y\";\n";
    $s .= "\$timeformat = \"h:i A\";\n";
    $s .= "\$regdateformat = \"M Y\";\n";
    $s .= "\$timezoneoffset = \"" . addslashes($site['timezone']) . "\";\n";
    $s .= "\$dstcorrection = \"1\";\n";
    $s .= "\$cookiedomain = \"" . addslashes($cookieDomain) . "\";\n";
    $s .= "\$cookiepath = \"/\";\n";
    $s .= "\$cookieprefix = \"\";\n";
    $s .= "\$cookiesecureflag = \"0\";\n";
    $s .= "\$cookiesamesiteflag = \"1\";\n";
    $s .= "\$maxmultipagelinks = \"5\";\n";
    $s .= "\$jumptopagemultipage = \"1\";\n";
    $s .= "\$useravatar = \"pic/default_avatar.gif\";\n";
    $s .= "\$useravatardims = \"200|200\";\n";
    $s .= "\$maxavatardims = \"200x200\";\n";
    $s .= "\$allowremoteavatars = \"1\";\n";
    $s .= "\$delayedthreadviews = \"1\";\n";
    $s .= "\$datetimesep = \", \";\n";
    $s .= "\$announce_urls[] = \"{$announce_url}\";\n";
    $s .= "\$torrent_dir = \"torrents\";\n";
    $s .= "\$pic_base_url = \"pic/\";\n";
    $s .= "\$table_cat = \"categories\";\n";
    $s .= "\$maxchar = \"250\";\n";
    $s .= "\$max_torrent_size = \"10 * 1024 * 1024\";\n";
    $s .= "\$wolcutoffmins = \"15\";\n";
    $s .= "\$defaultlanguage = \"english\";\n";
    $s .= "\$defaulttemplate = \"default\";\n";
    $s .= "\$avataruploadpath = \"./uploads/avatars\";\n";
    $s .= "\$avatarsize = \"250000\";\n";
    $s .= "\$f_postsperpage = \"10\";\n";
    $s .= "\$f_threadsperpage = \"20\";\n";
    $s .= "\$threadreadcut = \"7\";\n";
    $s .= "\$use_xmlhttprequest = \"1\";\n";
    $s .= "\$redirects = \"0\";\n";
    $s .= "\$uploadspath = \"./uploads\";\n";
    $s .= "\$attachthumbh = \"96\";\n";
    $s .= "\$attachthumbw = \"96\";\n";
    $s .= "\$enableattachments = \"1\";\n";
    $s .= "\$showownunapproved = \"1\";\n";
    $s .= "\$seourls = \"yes\";\n";
    $s .= "\$enablepms = \"1\";\n";
    $s .= "\$mail_handler = \"smtp\";\n";
    $s .= "\$smtp_host = \"" . addslashes($site['smtp_host']) . "\";\n";
    $s .= "\$smtp_user = \"" . addslashes($site['smtp_user']) . "\";\n";
    $s .= "\$smtp_port = \"" . addslashes($site['smtp_port']) . "\";\n";
    $s .= "\$smtp_pass = \"" . addslashes($site['smtp_pass']) . "\";\n";
    $s .= "\$secure_smtp = \"2\";\n";
    $s .= "\$mail_logging = \"2\";\n";
    $s .= "\$mail_message_id = \"1\";\n";
    $s .= "\$mail_queue_limit = \"10\";\n";
    $s .= "\$hitrun = \"no\";\n";
    $s .= "\$hitrun_ratio = \"0.4\";\n";
    $s .= "\$hitrun_gig = \"7\";\n";
    $s .= "\$offline_minutes = \"\";\n";
    $s .= "\$betweenregstime = \"24\";\n";
    $s .= "\$maxregsbetweentime = \"2\";\n";
    $s .= "\$showforumpagesbreadcrumb = \"1\";\n";
    $s .= "\$browsingthisthread = \"1\";\n";
    $s .= "\$userpppoptions = \"5,10,15,20,25,30,40,50\";\n";
    $s .= "\$usertppoptions = \"10,15,20,25,30,40,50\";\n";
    $s .= "\$attachthumbnails = \"yes\";\n";
	$s .= "\$invitesystem = \"off\";\n";
    $s .= "\$regtype = \"instant\";\n";
    $s .= "\$minnamelength = \"6\";\n";
    $s .= "\$maxnamelength = \"30\";\n";
    $s .= "\$maxip = \"444466\";\n";
    $s .= "\$illegalusernames = \"\";\n";
    $s .= "\$minpasswordlength = \"6\";\n";
$s .= "\$maxpasswordlength = \"30\";\n";
$s .= "\$requirecomplexpasswords = \"0\";\n";
$s .= "\$failedlogincount = \"3\";\n";
$s .= "\$failedlogintext = \"1\";\n";
$s .= "\$username_method = \"0\";\n";
$s .= "\$disableregs = \"0\";\n";
$s .= "\$r_verification = \"yes\";\n";
$s .= "\$maxusers = \"5000\";\n";
$s .= "\$coppa = \"disabled\";\n";
$s .= "\$_d_usergroup = \"1\";\n";
$s .= "\$invite_count = \"1\";\n";
$s .= "\$autogigsignup = \"11\";\n";
$s .= "\$autosbsignup = \"500\";\n";
$s .= "\$bonus = \"enable\";\n";
$s .= "\$kpsseed = \"5.0\";\n";
$s .= "\$kpsupload = \"15.0\";\n";
$s .= "\$kpscomment = \"5.0\";\n";
$s .= "\$kpsthanks = \"3.0\";\n";
$s .= "\$kpsrate = \"3.0\";\n";
$s .= "\$kpspoll = \"2.0\";\n";
$s .= "\$kpsmaxpoint = \"999.0\";\n";
$s .= "\$kpsinvite = \"yes\";\n";
$s .= "\$kpstitle = \"yes\";\n";
$s .= "\$kpsvip = \"yes\";\n";
$s .= "\$kpsgift = \"yes\";\n";
$s .= "\$kpswarning = \"yes\";\n";
$s .= "\$kpsratiofix = \"yes\";\n";
$s .= "\$bdayreward = \"yes\";\n";
$s .= "\$bdayrewardtype = \"freeleech\";\n";
$s .= "\$announce_actions = \"yes\";\n";
$s .= "\$aggressivecheat = \"yes\";\n";
$s .= "\$nc = \"no\";\n";
$s .= "\$bannedclientdetect = \"no\";\n";
$s .= "\$detectbrowsercheats = \"no\";\n";
$s .= "\$checkconnectable = \"no\";\n";
$s .= "\$checkip = \"no\";\n";
$s .= "\$announce_wait = \"0\";\n";
$s .= "\$announce_interval = \"900\";\n";
$s .= "\$max_rate = \"2097152\";\n";
$s .= "\$allowed_clients = \"-UT1610-,-AZ3034-,-UT1750-\";\n";
$s .= "\$mysql_host = \"" . addslashes($db['hostname']) . "\";\n";
$s .= "\$mysql_user = \"" . addslashes($db['username']) . "\";\n";
$s .= "\$mysql_pass = \"" . addslashes($db['password']) . "\";\n";
$s .= "\$mysql_db = \"" . addslashes($db['database']) . "\";\n";
$s .= "\$ai = \"no\";\n";
$s .= "\$autoinvitetime = \"28\";\n";
$s .= "\$max_dead_torrent_time = \"2\";\n";
$s .= "\$promote_gig_limit = \"25\";\n";
$s .= "\$promote_min_ratio = \"1.05\";\n";
$s .= "\$promote_min_reg_days = \"28\";\n";
$s .= "\$demote_min_ratio = \"0.95\";\n";
$s .= "\$referrergift = \"2.5\";\n";
$s .= "\$leechwarn_min_ratio = \"0.4\";\n";
$s .= "\$leechwarn_gig_limit = \"5\";\n";
$s .= "\$leechwarn_length = \"2\";\n";
$s .= "\$leechwarn_remove_ratio = \"0.8\";\n";
$s .= "\$ban_user_limit = \"5\";\n";
$s .= "\$prorules = \"yes\";\n";
$s .= "\$randomhalfleech = \"7\";\n";
$s .= "\$randomfree = \"2\";\n";
$s .= "\$randomtwoup = \"2\";\n";
$s .= "\$randomtwoupfree = \"1\";\n";
$s .= "\$randomtwouphalfdown = \"0\";\n";
$s .= "\$randomthirtypercentdown = \"0\";\n";
$s .= "\$largesize = \"12\";\n";
$s .= "\$largepro = \"5\";\n";
$s .= "\$expirehalfleech = \"70\";\n";
$s .= "\$expirefree = \"60\";\n";
$s .= "\$expiretwoup = \"60\";\n";
$s .= "\$expiretwoupfree = \"30\";\n";
$s .= "\$expiretwouphalfleech = \"30\";\n";
$s .= "\$expirethirtypercentleech = \"30\";\n";
$s .= "\$expirenormal = \"0\";\n";
$s .= "\$halfleechbecome = \"1\";\n";
$s .= "\$freebecome = \"1\";\n";
$s .= "\$twoupbecome = \"1\";\n";
$s .= "\$twoupfreebecome = \"1\";\n";
$s .= "\$twouphalfleechbecome = \"1\";\n";
$s .= "\$thirtypercentleechbecome = \"1\";\n";
$s .= "\$normalbecome = \"1\";\n";
$s .= "\$hotdays = \"7\";\n";
$s .= "\$hotseeder = \"5\";\n";
$s .= "\$uploaderdouble = \"no\";\n";
$s .= "\$deldeadtorrent = \"no\";\n";
	
    
    
    
    
    
    // ── Запись всех настроек в таблицу settings ───────────────────────────────
$db_settings = [
    'SITENAME'              => $site['name'],
    'BASEURL'               => $site['url'],
    'SITEEMAIL'             => $site['email'],
    'REPORTMAIL'            => $site['email'],
    'contactemail'          => $site['email'],
    'SITEONLINE'            => 'yes',
    'useajax'               => 'yes',
    'externalscrape'        => 'yes',
    'includeexpeers'        => 'no',
    'MEMBERSONLY'           => $site['membersonly'],
    'aggressivecheckip'     => 'no',
    'aggressivecheckemail'  => 'no',
    'maxloginattempts'      => '7',
    'securehash'            => $securehash,
    'charset'               => 'UTF-8',
    'shoutboxcharset'       => 'UTF-8',
    'metakeywords'          => 'torrent, tracker',
    'metadesc'              => $site['name'] . ' - Torrent Tracker',
    'slogan'                => 'The Best Tracker',
    'usezip'                => 'no',
    'iplog1'                => 'yes',
    'ctracker'              => 'yes',
    'gzipcompress'          => 'no',
    'snatchmod'             => 'yes',
    'ts_perpage'            => '20',
    'loadlimit'             => '',
    'useredirectsystem'     => 'yes',
    'vkeyword'              => 'no',
    'privatetrackerpatch'   => $site['privatepatch'],
    'iv'                    => 'no',
    'dateformat'            => 'l, jS F, Y',
    'timeformat'            => 'h:i A',
    'regdateformat'         => 'M Y',
    'timezoneoffset'        => $site['timezone'],
    'dstcorrection'         => '1',
    'cookiedomain'          => $cookieDomain,
    'cookiepath'            => '/',
    'cookieprefix'          => '',
    'cookiesecureflag'      => '0',
    'cookiesamesiteflag'    => '1',
    'maxmultipagelinks'     => '5',
    'jumptopagemultipage'   => '1',
    'useravatar'            => 'pic/default_avatar.gif',
    'useravatardims'        => '200|200',
    'maxavatardims'         => '200x200',
    'allowremoteavatars'    => '1',
    'delayedthreadviews'    => '1',
    'datetimesep'           => ', ',
    'announce_urls[]'       => $announce_url,
    'torrent_dir'           => 'torrents',
    'pic_base_url'          => 'pic/',
    'table_cat'             => 'categories',
    'maxchar'               => '250',
    'max_torrent_size'      => '10 * 1024 * 1024',
    'wolcutoffmins'         => '15',
    'defaultlanguage'       => 'english',
    'defaulttemplate'       => 'default',
    'avataruploadpath'      => './uploads/avatars',
    'avatarsize'            => '250000',
    'f_postsperpage'        => '10',
    'f_threadsperpage'      => '20',
    'threadreadcut'         => '7',
    'use_xmlhttprequest'    => '1',
    'redirects'             => '0',
    'uploadspath'           => './uploads',
    'attachthumbh'          => '96',
    'attachthumbw'          => '96',
    'enableattachments'     => '1',
    'showownunapproved'     => '1',
    'seourls'               => 'yes',
    'enablepms'             => '1',
    'mail_handler'          => 'smtp',
    'smtp_host'             => $site['smtp_host'],
    'smtp_user'             => $site['smtp_user'],
    'smtp_port'             => $site['smtp_port'],
    'smtp_pass'             => $site['smtp_pass'],
    'secure_smtp'           => '2',
    'mail_logging'          => '2',
    'mail_message_id'       => '1',
    'mail_queue_limit'      => '10',
    'hitrun'                => 'no',
    'hitrun_ratio'          => '0.4',
    'hitrun_gig'            => '7',
    'offline_minutes'       => '',
    'betweenregstime'       => '24',
    'maxregsbetweentime'    => '2',
    'showforumpagesbreadcrumb' => '1',
    'browsingthisthread'    => '1',
    'userpppoptions'        => '5,10,15,20,25,30,40,50',
    'usertppoptions'        => '10,15,20,25,30,40,50',
    'attachthumbnails'      => 'yes',
	
	// ANNOUNCE settings
    'announce_actions'    => 'yes',
    'aggressivecheat'     => 'yes',
    'nc'                  => 'no',
    'bannedclientdetect'  => 'no',
    'detectbrowsercheats' => 'no',
    'checkconnectable'    => 'no',
    'checkip'             => 'no',
    'announce_wait'       => '0',
    'announce_interval'   => '900',
    'max_rate'            => '2097152',
    'allowed_clients'     => '-UT1610-,-AZ3034-,-UT1750-',
    'mysql_host'          => $db['hostname'],
    'mysql_user'          => $db['username'],
    'mysql_pass'          => $db['password'],
    'mysql_db'            => $db['database'],
    // CLEANUP settings
    'ai'                     => 'no',
    'autoinvitetime'         => '28',
    'max_dead_torrent_time'  => '2',
    'promote_gig_limit'      => '25',
    'promote_min_ratio'      => '1.05',
    'promote_min_reg_days'   => '28',
    'demote_min_ratio'       => '0.95',
    'referrergift'           => '2.5',
    'leechwarn_min_ratio'    => '0.4',
    'leechwarn_gig_limit'    => '5',
    'leechwarn_length'       => '2',
    'leechwarn_remove_ratio' => '0.8',
    'ban_user_limit'         => '5',
	
	// SIGNUP settings
    'invitesystem'           => 'off',
    'regtype'                => 'instant',
    'minnamelength'          => '6',
    'maxnamelength'          => '30',
    'maxip'                  => '444466',
    'illegalusernames'       => '',
    'minpasswordlength'      => '6',
    'maxpasswordlength'      => '30',
    'requirecomplexpasswords'=> '0',
    'failedlogincount'       => '3',
    'failedlogintext'        => '1',
    'username_method'        => '0',
    'disableregs'            => '0',
    'r_verification'         => 'yes',
    'maxusers'               => '5000',
    'coppa'                  => 'disabled',
    '_d_usergroup'           => '1',
    'invite_count'           => '1',
    'autogigsignup'          => '11',
    'autosbsignup'           => '500',
    // KPS settings
    'bonus'                  => 'enable',
    'kpsseed'                => '5.0',
    'kpsupload'              => '15.0',
    'kpscomment'             => '5.0',
    'kpsthanks'              => '3.0',
    'kpsrate'                => '3.0',
    'kpspoll'                => '2.0',
    'kpsmaxpoint'            => '999.0',
    'kpsinvite'              => 'yes',
    'kpstitle'               => 'yes',
    'kpsvip'                 => 'yes',
    'kpsgift'                => 'yes',
    'kpswarning'             => 'yes',
    'kpsratiofix'            => 'yes',
    'bdayreward'             => 'yes',
    'bdayrewardtype'         => 'freeleech',
	
	// PROMO settings
    'prorules'                 => 'yes',
    'randomhalfleech'          => '7',
    'randomfree'               => '2',
    'randomtwoup'              => '2',
    'randomtwoupfree'          => '1',
    'randomtwouphalfdown'      => '0',
    'randomthirtypercentdown'  => '0',
    'largesize'                => '12',
    'largepro'                 => '5',
    'expirehalfleech'          => '70',
    'expirefree'               => '60',
    'expiretwoup'              => '60',
    'expiretwoupfree'          => '30',
    'expiretwouphalfleech'     => '30',
    'expirethirtypercentleech' => '30',
    'expirenormal'             => '0',
    'halfleechbecome'          => '1',
    'freebecome'               => '1',
    'twoupbecome'              => '1',
    'twoupfreebecome'          => '1',
    'twouphalfleechbecome'     => '1',
    'thirtypercentleechbecome' => '1',
    'normalbecome'             => '1',
    'hotdays'                  => '7',
    'hotseeder'                => '5',
    'uploaderdouble'           => 'no',
    'deldeadtorrent'           => 'no',
	
	
	
	
];

foreach ($db_settings as $name => $value) {
    $n = mysqli_real_escape_string($conn, $name);
    $v = mysqli_real_escape_string($conn, $value);
    $check = mysqli_query($conn, "SELECT `sid` FROM `{$p}settings` WHERE `name`='{$n}' LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE `{$p}settings` SET `value`='{$v}' WHERE `name`='{$n}'");
    } else {
        mysqli_query($conn, "INSERT INTO `{$p}settings` (`name`,`value`) VALUES ('{$n}','{$v}')");
    }
}
    
    
 
 // ── Генерация config_announce.php ────────────────────────────────────────────
$announce_php  = "<?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!\n";
$announce_php .= "if(!defined('IN_ANNOUNCE')) die('Hacking attempt!');\n";
$announce_php .= "\$announce_actions = 'yes';\n";
$announce_php .= "\$aggressivecheat = 'yes';\n";
$announce_php .= "\$nc = 'no';\n";
$announce_php .= "\$announce_wait = '0';\n";
$announce_php .= "\$announce_interval = '900';\n";
$announce_php .= "\$max_rate = '2097152';\n";
$announce_php .= "\$bannedclientdetect = 'no';\n";
$announce_php .= "\$allowed_clients = '-UT1610-,-AZ3034-,-UT1750-';\n";
$announce_php .= "\$detectbrowsercheats = 'no';\n";
$announce_php .= "\$checkconnectable = 'no';\n";
$announce_php .= "\$checkip = 'no';\n";
$announce_php .= "\$mysql_host = '" . addslashes($db['hostname']) . "';\n";
$announce_php .= "\$mysql_user = '" . addslashes($db['username']) . "';\n";
$announce_php .= "\$mysql_pass = '" . addslashes($db['password']) . "';\n";
$announce_php .= "\$mysql_db = '" . addslashes($db['database']) . "';\n";
$announce_php .= "\$BASEURL = '" . addslashes($site['url']) . "';\n";
$announce_php .= "\$SITENAME = '" . addslashes($site['name']) . "';\n";
$announce_php .= "\$privatetrackerpatch = '" . $site['privatepatch'] . "';\n";
$announce_php .= "\$gzipcompress = 'no';\n";
$announce_php .= "\$charset = 'UTF-8';\n";
$announce_php .= "\$aggressivecheckip = 'no';\n";
$announce_php .= "\$snatchmod = 'yes';\n";
$announce_php .= "\$bonus = 'enable';\n";
$announce_php .= "\$kpsseed = '5.0';\n";
$announce_php .= "\$bdayreward = 'yes';\n";
$announce_php .= "\$bdayrewardtype = 'freeleech';\n";
$announce_php .= "?>\n";


    


$errors_out = [];

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

    


    // Директории
    //foreach (['uploads','uploads/avatars','torrents','torrents/images','torrents/screens','cache','cache/datacache'] as $dir) {
     //   $path = __DIR__ . '/' . $dir;
    //    if (!is_dir($path)) @mkdir($path, 0755, true);
    //}
    
    
    


// Загружаем базовые данные
$data_sql = @file_get_contents(__DIR__ . '/sql/install_data.sql');
if ($data_sql) {
    $data_sql = str_replace("\r\n", "\n", $data_sql);
    $blocks = preg_split('/\n\n+/', $data_sql);
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        if (preg_match('/^--/m', $block) && !preg_match('/^INSERT/im', $block)) continue;
        $lines = explode("\n", $block);
        $sql_lines = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '--') === 0) continue;
            $sql_lines[] = $line;
        }
        $sql = trim(implode("\n", $sql_lines));
        if (empty($sql)) continue;
        if (!preg_match('/^INSERT/i', $sql)) continue;
        $sql = rtrim($sql, ';');
        mysqli_query($conn, $sql);
    }
}


// Загружаем FAQ
$faq_sql = @file_get_contents(__DIR__ . '/sql/faq.sql');
if ($faq_sql) {
    $faq_sql = str_replace("\r\n", "\n", $faq_sql);
    $faq_sql = str_ireplace('INSERT INTO faq', 'INSERT IGNORE INTO `' . $p . 'faq`', $faq_sql);
    foreach (array_filter(array_map('trim', explode("\n", $faq_sql))) as $sql) {
        if (stripos($sql, 'INSERT') !== 0) continue;
        mysqli_query($conn, rtrim($sql, ';'));
    }
}

// Загружаем группы пользователей
$usergroups_sql = @file_get_contents(__DIR__ . '/sql/group.sql');
if ($usergroups_sql) {
    $usergroups_sql = str_replace("\r\n", "\n", $usergroups_sql);
   $usergroups_sql = str_replace('\\"', '"', $usergroups_sql);
    $usergroups_sql = str_ireplace('INSERT IGNORE INTO usergroups', 'INSERT IGNORE INTO `' . $p . 'usergroups`', $usergroups_sql);
    foreach (array_filter(array_map('trim', explode("\n", $usergroups_sql))) as $sql) {
        if (stripos($sql, 'INSERT') !== 0) continue;
        mysqli_query($conn, rtrim($sql, ';'));
    }
}





// Загружаем шаблоны
$templates_sql = @file_get_contents(__DIR__ . '/sql/templates.sql');
if ($templates_sql) {
    $templates_sql = str_replace("\r\n", "\n", $templates_sql);
    $statements = explode("');\n", $templates_sql);
    foreach ($statements as $sql) {
        $sql = trim($sql);
        if (empty($sql)) continue;
        $sql .= "');";
        if (stripos($sql, 'INSERT') !== 0) continue;
        mysqli_query($conn, $sql);
    }
}

    

    return $errors_out ?: true;
}

// ── Проверка требований ───────────────────────────────────────────────────────
function checkRequirements() {
    return [
        ['PHP Version ≥ 8.0',    version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
        ['MySQLi',               extension_loaded('mysqli'),   extension_loaded('mysqli') ? 'OK' : 'Missing'],
        ['GD',                   extension_loaded('gd'),       extension_loaded('gd') ? 'OK' : 'Missing'],
        ['JSON',                 extension_loaded('json'),     'OK'],
        ['MB String',            extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'Missing'],
        ['OpenSSL',              extension_loaded('openssl'),  extension_loaded('openssl') ? 'OK' : 'Missing'],
        ['cURL',                 extension_loaded('curl'),     extension_loaded('curl') ? 'OK' : 'Missing'],
        ['config.php writable',  is_writable(__DIR__) || (file_exists(__DIR__.'/config.php') && is_writable(__DIR__.'/config.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable — run: chmod 666 config.php'],
        ['settings.php writable',is_writable(__DIR__) || (file_exists(__DIR__.'/settings.php') && is_writable(__DIR__.'/settings.php')), is_writable(__DIR__) ? 'Writable' : 'Not writable — run: chmod 666 settings.php'],
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
<title>Ruff Tracker — Installer v<?= INSTALLER_VERSION ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    
    .installer-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .main-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        backdrop-filter: blur(10px);
    }
    
    .card-header {
        background: var(--primary-gradient);
        color: white;
        padding: 25px 30px;
        border-bottom: none;
    }
    
    .step-progress {
        padding: 25px 30px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    
    .step-item {
        position: relative;
        flex: 1;
        text-align: center;
    }
    
    .step-circle {
        width: 40px;
        height: 40px;
        background: #dee2e6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
        color: #6c757d;
        transition: all 0.3s ease;
        position: relative;
        z-index: 2;
    }
    
    .step-item.active .step-circle {
        background: #667eea;
        color: white;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        transform: scale(1.1);
    }
    
    .step-item.completed .step-circle {
        background: #28a745;
        color: white;
    }
    
    .step-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 500;
    }
    
    .step-item.active .step-label {
        color: #667eea;
        font-weight: 600;
    }
    
    .step-connector {
        position: absolute;
        top: 20px;
        left: 50%;
        width: 100%;
        height: 2px;
        background: #dee2e6;
        z-index: 1;
    }
    
    .step-item:last-child .step-connector {
        display: none;
    }
    
    .form-control, .form-select {
        border-radius: 12px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        padding: 10px 15px;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn {
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: var(--primary-gradient);
        border: none;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    .btn-success {
        background: var(--success-gradient);
        border: none;
        color: #333;
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(132, 250, 176, 0.3);
    }
    
    .requirement-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .requirement-item:last-child {
        border-bottom: none;
    }
    
    .requirement-status {
        width: 30px;
        text-align: center;
        margin-right: 15px;
    }
    
    .info-card {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .badge-custom {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .alert-custom {
        border-radius: 15px;
        border-left: 4px solid;
    }
</style>
</head>
<body>
<div class="installer-container fade-in">
    <div class="main-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-satellite-dish me-2"></i>
                        Ruff Tracker Installer
                    </h2>
                    <p class="mb-0 opacity-75">Professional Torrent Tracker Setup</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="fas fa-code-branch me-1"></i> v<?= INSTALLER_VERSION ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="step-progress">
            <div class="d-flex justify-content-between position-relative">
                <?php foreach ($steps as $num => $label): ?>
                <div class="step-item text-center <?= $num < $step ? 'completed' : ($num == $step ? 'active' : '') ?>">
                    <div class="step-circle">
                        <?php if ($num < $step): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <?= $num ?>
                        <?php endif; ?>
                    </div>
                    <div class="step-label"><?= $label ?></div>
                    <?php if ($num < count($steps)): ?>
                        <div class="step-connector"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="p-4">
            <?php if ($errors): ?>
            <div class="alert alert-danger alert-custom mb-4" style="border-left-color: #dc3545;">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <strong>Installation Errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <div class="info-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-clipboard-list fa-2x me-3"></i>
                    <h5 class="mb-0">System Requirements Check</h5>
                </div>
                <p class="text-muted small">Please ensure all requirements are met before proceeding with installation.</p>
            </div>
            
            <div class="mb-4">
                <?php foreach ($reqs as $req): ?>
                <div class="requirement-item">
                    <div class="requirement-status">
                        <i class="fas fa-<?= $req[1] ? 'check-circle text-success' : 'times-circle text-danger' ?> fa-lg"></i>
                    </div>
                    <div class="flex-grow-1">
                        <strong><?= $req[0] ?></strong>
                        <div class="small text-muted"><?= htmlspecialchars($req[2]) ?></div>
                    </div>
                    <?php if ($req[1]): ?>
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1 rounded-pill">
                            <i class="fas fa-check me-1"></i>Passed
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-1 rounded-pill">
                            <i class="fas fa-times me-1"></i>Failed
                        </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($allOk): ?>
                <a href="install.php?step=2" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-arrow-right me-2"></i>Continue to Database Setup
                </a>
            <?php else: ?>
                <div class="alert alert-warning text-center mb-3">
                    <i class="fas fa-tools me-2"></i>
                    Please fix the issues above before continuing
                </div>
                <a href="install.php?step=1" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-sync-alt me-2"></i>Recheck Requirements
                </a>
            <?php endif; ?>
            
            <?php elseif ($step === 2): ?>
            <div class="info-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-database fa-2x me-3"></i>
                    <h5 class="mb-0">Database Configuration</h5>
                </div>
                <p class="text-muted small">Connect to your MySQL database where the tracker tables will be installed.</p>
            </div>
            
            <form method="post" action="install.php?step=2">
                <div class="row g-3">
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-server me-1"></i>Database Host
                        </label>
                        <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_SESSION['db']['hostname'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-plug me-1"></i>Port
                        </label>
                        <input type="text" class="form-control" name="db_port" value="3306">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-database me-1"></i>Database Name
                        </label>
                        <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_SESSION['db']['database'] ?? '') ?>" placeholder="tracker_db" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-user me-1"></i>Database Username
                        </label>
                        <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_SESSION['db']['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-lock me-1"></i>Database Password
                        </label>
                        <input type="password" class="form-control" name="db_pass" value="<?= htmlspecialchars($_SESSION['db']['password'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tag me-1"></i>Table Prefix
                        </label>
                        <input type="text" class="form-control" name="db_prefix" value="<?= htmlspecialchars($_SESSION['db']['prefix'] ?? '') ?>" placeholder="Optional">
                        <small class="text-muted">Leave empty for no prefix</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4">
                    <i class="fas fa-database me-2"></i>Test Connection & Continue
                </button>
            </form>
            
            <?php elseif ($step === 3): ?>
            <div class="info-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-globe fa-2x me-3"></i>
                    <h5 class="mb-0">Site Configuration</h5>
                </div>
                <p class="text-muted small">Configure your tracker's basic settings and appearance.</p>
            </div>
            
            <form method="post" action="install.php?step=3">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tag me-1"></i>Site Name
                        </label>
                        <input type="text" class="form-control" name="site_name" value="<?= htmlspecialchars($_SESSION['site']['name'] ?? '') ?>" placeholder="My Awesome Tracker" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-link me-1"></i>Site URL
                        </label>
                        <input type="url" class="form-control" name="site_url" value="<?= htmlspecialchars($_SESSION['site']['url'] ?? '') ?>" placeholder="https://tracker.example.com" required>
                        <small class="text-muted">No trailing slash. HTTPS recommended for security.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-envelope me-1"></i>Site Email
                        </label>
                        <input type="email" class="form-control" name="site_email" value="<?= htmlspecialchars($_SESSION['site']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-clock me-1"></i>Timezone
                        </label>
                        <select class="form-select" name="timezone">
                            <?php for ($i=-12;$i<=14;$i++): ?>
                                <option value="<?=$i?>" <?=(($_SESSION['site']['timezone']??2)==$i)?'selected':''?>>
                                    UTC <?=$i>=0?'+'.$i:$i?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="membersonly" id="membersonly" <?= ($_SESSION['site']['membersonly'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="membersonly">
                                <i class="fas fa-users me-1"></i>Members Only Mode
                            </label>
                            <div class="form-text">Only registered users can browse the site</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="privatepatch" id="privatepatch" <?= ($_SESSION['site']['privatepatch'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="privatepatch">
                                <i class="fas fa-lock me-1"></i>Private Tracker Mode
                            </label>
                            <div class="form-text">Force private flag on all torrents</div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="mb-3">
                    <i class="fas fa-envelope me-2"></i>
                    SMTP Configuration <small class="text-muted">(Optional)</small>
                </h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="smtp_host" value="<?= htmlspecialchars($_SESSION['site']['smtp_host'] ?? 'smtp.gmail.com') ?>" placeholder="SMTP Host">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="smtp_port" value="<?= htmlspecialchars($_SESSION['site']['smtp_port'] ?? '587') ?>" placeholder="Port">
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="smtp_user" value="<?= htmlspecialchars($_SESSION['site']['smtp_user'] ?? '') ?>" placeholder="SMTP Username">
                    </div>
                    <div class="col-md-6">
                        <input type="password" class="form-control" name="smtp_pass" value="<?= htmlspecialchars($_SESSION['site']['smtp_pass'] ?? '') ?>" placeholder="SMTP Password">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mt-4">
                    <i class="fas fa-arrow-right me-2"></i>Continue to Admin Setup
                </button>
            </form>
            
            <?php elseif ($step === 4): ?>
            <div class="info-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-user-shield fa-2x me-3"></i>
                    <h5 class="mb-0">Administrator Account</h5>
                </div>
                <p class="text-muted small">Create the master administrator account for your tracker.</p>
            </div>
            
            <div class="alert alert-info alert-custom mb-4" style="border-left-color: #17a2b8;">
                <i class="fas fa-info-circle me-2"></i>
                Password is hashed using MD5+salt (tracker native format). Please use a strong password.
            </div>
            
            <form method="post" action="install.php?step=4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-user me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" name="admin_user" value="<?= htmlspecialchars($_SESSION['admin']['username'] ?? '') ?>" required minlength="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-envelope me-1"></i>Email Address
                        </label>
                        <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-lock me-1"></i>Password
                        </label>
                        <input type="password" class="form-control" name="admin_pass" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-check-double me-1"></i>Confirm Password
                        </label>
                        <input type="password" class="form-control" name="admin_confirm" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-4">
                    <i class="fas fa-arrow-right me-2"></i>Continue to Installation
                </button>
            </form>
            
            <?php elseif ($step === 5): ?>
            <div class="info-card">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-rocket fa-2x me-3"></i>
                    <h5 class="mb-0">Ready to Install</h5>
                </div>
                <p class="text-muted small">Review your settings and begin the installation process.</p>
            </div>
            
            <div class="bg-light rounded p-3 mb-4">
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Installation Summary</h6>
                <div class="row g-2 small">
                    <div class="col-4 fw-semibold">Database:</div>
                    <div class="col-8"><?= htmlspecialchars(($_SESSION['db']['database']??'').'@'.($_SESSION['db']['hostname']??'')) ?></div>
                    <div class="col-4 fw-semibold">Site Name:</div>
                    <div class="col-8"><?= htmlspecialchars($_SESSION['site']['name']??'') ?></div>
                    <div class="col-4 fw-semibold">Site URL:</div>
                    <div class="col-8"><?= htmlspecialchars($_SESSION['site']['url']??'') ?></div>
                    <div class="col-4 fw-semibold">Admin Username:</div>
                    <div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?></div>
                    <div class="col-4 fw-semibold">Admin Email:</div>
                    <div class="col-8"><?= htmlspecialchars($_SESSION['admin']['email']??'') ?></div>
                </div>
            </div>
            
            <div class="alert alert-warning alert-custom mb-4" style="border-left-color: #ffc107;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Important:</strong> This will create all database tables and configuration files. This process cannot be undone.
            </div>
            
            <form method="post" action="install.php?step=5">
                <button type="submit" class="btn btn-success w-100 btn-lg">
                    <i class="fas fa-download me-2"></i>Install Ruff Tracker Now!
                </button>
            </form>
            
            <?php elseif ($step === 6): ?>
            <div class="text-center py-4">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                </div>
                <h3 class="text-success fw-bold mb-3">Installation Complete!</h3>
                <p class="text-muted mb-4">Your Ruff Tracker has been successfully installed and configured.</p>
                
                <div class="alert alert-danger text-start mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div>
                            <strong>Security Notice!</strong>
                            <p class="mb-0 mt-1">Please delete <code>install.php</code> from your server immediately to prevent unauthorized access.</p>
                        </div>
                    </div>
                </div>
                
                <div class="info-card text-start mb-4">
                    <h6 class="mb-3"><i class="fas fa-key me-2"></i>Access Information</h6>
                    <div class="row g-2">
                        <div class="col-4 fw-semibold">Site URL:</div>
                        <div class="col-8">
                            <a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" target="_blank">
                                <?= htmlspecialchars($_SESSION['site']['url']??'/') ?>
                            </a>
                        </div>
                        <div class="col-4 fw-semibold">Admin Panel:</div>
                        <div class="col-8">
                            <a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" target="_blank">
                                <?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>
                            </a>
                        </div>
                        <div class="col-4 fw-semibold">Username:</div>
                        <div class="col-8"><?= htmlspecialchars($_SESSION['admin']['username']??'') ?></div>
                    </div>
                </div>
                
                <div class="d-grid gap-3">
                    <a href="<?= htmlspecialchars($_SESSION['site']['url']??'/') ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Go to Your Tracker
                    </a>
                    <a href="<?= htmlspecialchars(($_SESSION['site']['url']??'').'/admin/index.php') ?>" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-crown me-2"></i>Access Admin Panel
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="text-center mt-3">
        <small class="text-white-50">
            <i class="fas fa-code me-1"></i> Ruff Tracker Installer v<?= INSTALLER_VERSION ?> | 
            <a href="install.php?step=1" class="text-white-50 text-decoration-none">Start Over</a>
        </small>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>