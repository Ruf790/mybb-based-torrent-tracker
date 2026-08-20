<?php
declare(strict_types=1);

// ====================================================
//   Core Bootstrap
//   PHP 8.4 Compatible Version
// ====================================================

// --- Tracker constants ---
const IN_TRACKER = true;
const APP_INITIALIZED = true;
define('TIMENOW', time());

// --- Paths ---
define('TSDIR', __DIR__);
define('INC_PATH', TSDIR . '/include');
define('CONFIG_DIR', TSDIR . '/config');

// --- Debug / timing ---
if (!defined('DEBUGMODE')) {
    $GLOBALS['ts_start_time'] = microtime(true);
}

// --- Misc constants ---
const VERSION = '';
const TS_MESSAGE = 'Powered by ArtCore Gangsta';

// --- Default Usergroups ---
const UC_GUEST         = 1;
const UC_USER          = 2;
const UC_POWER_USER    = 3;
const UC_VIP           = 4;
const UC_UPLOADER      = 5;
const UC_MODERATOR     = 6;
const UC_ADMINISTRATOR = 7;
const UC_SYSOP         = 8;
const UC_BANNED        = 9;

// --- Script name ---
if (!defined('SCRIPTNAME')) {
    define('SCRIPTNAME', $_SERVER['SCRIPT_NAME'] ?? '');
}

// --- Error handler ---
require_once INC_PATH . '/error_handler.php';

// --- Global init structures ---
$shutdown_queries   = [];
$shutdown_functions = [];

// --- Load core init ---
require_once INC_PATH . '/init.php';

// --- Load usergroup cache ---
$groupscache = $cache->read('usergroups');
if (!is_array($groupscache)) {
    $cache->update_usergroups();
    $groupscache = $cache->read('usergroups');
}

// --- Initialize session system ---
require_once INC_PATH . '/class_session.php';
$session = new session();
$session->init();
$mybb->session = $session;

// --- Generate post key ---
$mybb->post_code = generate_post_check();

$post_code_string = '';
if (!empty($CURUSER['id'])) {
    $post_code_string = '&amp;my_post_key=' . $mybb->post_code;
}


// ====================================================
// Forum-Specific Initialization (ONLY when in forum context)
// ====================================================
if (defined('IN_FORUM') && IN_FORUM === true) {
    
    // Define forum security constants BEFORE including forum files
    define('FORUM_ACTIVE', true);
    define('FORUM_SECURE', true);
    
    // Load forum-specific files ONLY in forum context
    require_once INC_PATH . '/functions_forum.php';                  
    include_once INC_PATH . '/class_parser.php';         
    
    // Initialize parser (forum only)
    $parser = new postParser();
    $parser_options = [
        "allow_html" => 0,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "allow_videocode" => 1,
        "filter_badwords" => 1
    ];
    
    // Run forum functions ONLY
    gzip();
    maxsysop();
    
    // Load forum language ONLY in forum
    //$lang->load('tsf_forums');
    
    // MB string encoding (forum only)
    if (function_exists('mb_internal_encoding') && !empty($charset)) {
        mb_internal_encoding($charset);
    }
    
    // Navigation for forum ONLY
    $navbits = [];
    $navbits[0]['name'] = $SITENAME ?? 'Site Name';
    $navbits[0]['url'] = ($BASEURL ?? '') . '/index2.php';
}
?>