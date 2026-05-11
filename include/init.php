<?php
declare(strict_types=1);

// ====================================================
//   Core Initialization
//   PHP 8.4 Compatible Version
// ====================================================

// --- Security: Prevent GLOBALS overwrite attacks ---
if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
    define('errorid', 1);
    include_once TSDIR . '/error.php';
    exit();
}

// --- Referrer check for POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('SKIP_REFERRER_CHECK')) {
    $http_host = $_SERVER['HTTP_HOST'] 
        ?? $_ENV['HTTP_HOST'] 
        ?? $_SERVER['SERVER_NAME'] 
        ?? $_ENV['SERVER_NAME'] 
        ?? '';
}

// --- JSON fallback for older PHP versions ---
if (!function_exists('json_encode') || !function_exists('json_decode')) {
    require_once INC_PATH . '/3rdparty/json/json.php';
}

// --- Load core classes ---
require_once INC_PATH . '/functions.php';
require_once INC_PATH . '/functions_tsseo.php';

require_once INC_PATH . "/class_timers.php";
$maintimer = new timer();

require_once INC_PATH . '/class_core.php';
$mybb = new MyBB();

require_once INC_PATH . '/class_plugins.php';
$plugins = new pluginSystem();

// --- Database configuration and connection ---
if (!file_exists(INC_PATH . '/config.php')) {
    die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Required</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-5">
        <div class="mb-4">
            <i class="fas fa-cog fa-spin text-primary" style="font-size:5rem"></i>
        </div>
        <h2 class="fw-bold mb-3">Tracker Not Configured</h2>
        <p class="text-muted mb-4">Configuration file <code>include/config.php</code> not found.</p>
        <a href="/install.php" class="btn btn-primary btn-lg">
            <i class="fas fa-rocket me-2"></i>Run Installer
        </a>
    </div>
</body>
</html>');
}
require_once INC_PATH . '/config.php';
$mybb->config = $config;


require_once INC_PATH . '/db_base.php';
$dbDriverFile = INC_PATH . "/db_{$config['database']['type']}.php";

if (file_exists($dbDriverFile)) {
    require_once $dbDriverFile;
} else {
    throw new RuntimeException("Database driver not found: {$config['database']['type']}");
}

// --- Database driver selection ---
$db = match($config['database']['type']) {
    'sqlite' => new DB_SQLite(),
    'pgsql'  => new DB_PgSQL(),
    'mysqli' => new DB_MySQLi(),
    default  => new DB_MySQL()
};

// --- Database connection ---
define("TABLE_PREFIX", $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

// --- Cache system ---
require_once INC_PATH . '/class_datacache.php';
$cache = new datacache();
$cache->cache();

$mybb->parse_cookies();
$mybb->cache = $cache;
$mybb->asset_url = $mybb->get_asset_url();

require_once INC_PATH . '/ctracker.php';

// --- Load settings ---
$settingsFile = INC_PATH . '/settings.php';
if (file_exists($settingsFile)) {
    require_once $settingsFile;
} else {
    if (function_exists('rebuild_settings')) {
        rebuild_settings();
    } else {
        $settings = [];
        $query = $db->simple_select(
            "settings", 
            "value, name", 
            "", 
            [
                "order_by" => "title",
                "order_dir" => "ASC"
            ]
        );
        
        while ($setting = $db->fetch_array($query)) {
            $settings[$setting['name']] = str_replace("\"", "\\\"", $setting['value']);
        }
        $db->free_result($query);
    }
}

// --- Language system ---
require_once INC_PATH . '/class_language.php';
$lang = new trackerlanguage();
$lang->set_path(INC_PATH . '/languages');

$language = $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english';
if (empty($language) || !file_exists(INC_PATH . "/languages/{$language}")) {
    $language = $defaultlanguage ?? 'english';
}

$lang->set_language($language);
$lang->load('global');

// --- URL Definitions (SEO vs Standard) ---
$seoEnabled = ($seourls === "yes") || 
              ($seourls === "auto" && ($_SERVER['SEO_SUPPORT'] ?? 0) == 1);


if ($seoEnabled) {
    $mybb->seo_support = true;

    // SEO URLs
    define('FORUM_URL', 'forum-{fid}.html');
    define('FORUM_URL_PAGED', 'forum-{fid}-page-{page}.html');
    define('THREAD_URL', 'thread-{tid}.html');
    define('THREAD_URL_PAGED', 'thread-{tid}-page-{page}.html');
    define('THREAD_URL_ACTION', 'thread-{tid}-{action}.html');
    define('THREAD_URL_POST', 'thread-{tid}-post-{pid}.html');
    define('POST_URL', 'post-{pid}.html');
    define('PROFILE_URL', 'user-{id}.html');
    define('PROFILE_URL_PAGED', 'user-{id}-page-{page}.html');
    define('TORRENT_URL', 'torrent-{id}.html');
    define('TORRENT_URL_PAGED', 'torrent-{id}-page-{page}.html');
    define('DOWNLOAD_URL', 'download-{id}.html');
    define('TORRENT_URL_COMMENT', 'torrent-{id}-comment-{pid}.html');
    define('COMMENT_URL', 'comment-{pid}.html');
    define('ANNOUNCEMENT_URL', 'announcement-{aid}.html');

} else {
    // Standard URLs
    define('PROFILE_URL', 'member.php?action=profile&id={id}');
    define('PROFILE_URL_PAGED', 'userdetails.php?id={id}&page={page}');
    define('FORUM_URL', 'forumdisplay.php?fid={fid}');
    define('FORUM_URL_PAGED', 'forumdisplay.php?fid={fid}&page={page}');
    define('THREAD_URL', 'showthread.php?tid={tid}');
    define('THREAD_URL_PAGED', 'showthread.php?tid={tid}&page={page}');
    define('THREAD_URL_ACTION', 'showthread.php?tid={tid}&action={action}');
    define('THREAD_URL_POST', 'showthread.php?tid={tid}&pid={pid}');
    define('POST_URL', 'showthread.php?pid={pid}');
    define('TORRENT_URL', 'details.php?id={id}');
    define('TORRENT_URL_PAGED', 'details.php?id={id}&page={page}');
    define('TORRENT_URL_COMMENT', 'details.php?id={id}&pid={pid}');
    define('COMMENT_URL', 'details.php?pid={pid}');
    define('DOWNLOAD_URL', 'download.php?id={id}');
}


// --- Date and time formats ---
$date_formats = [
    1 => "m-d-Y",
    2 => "m-d-y", 
    3 => "m.d.Y",
    4 => "m.d.y",
    5 => "d-m-Y",
    6 => "d-m-y",
    7 => "d.m.Y", 
    8 => "d.m.y",
    9 => "F jS, Y",
    10 => "l, F jS, Y",
    11 => "jS F, Y",
    12 => "l, jS F, Y",
    13 => "Y-m-d" // ISO 8601
];

$time_formats = [
    1 => "h:i a",
    2 => "h:i A", 
    3 => "H:i"
];

// --- User IP and shutdown function ---
define("USERIPADDRESS", get_ip());

if ($mybb->use_shutdown === true) {
    register_shutdown_function('run_shutdown');
}

// --- Legacy variable support (for compatibility) ---
if (!isset($HTTP_POST_VARS) && isset($_POST)) {
    $HTTP_POST_VARS = $_POST;
    $HTTP_GET_VARS = $_GET;
    $HTTP_SERVER_VARS = $_SERVER;
    $HTTP_COOKIE_VARS = $_COOKIE;
    $HTTP_ENV_VARS = $_ENV;
    $HTTP_POST_FILES = $_FILES;
}