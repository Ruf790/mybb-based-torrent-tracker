<?php

declare(strict_types=1);


// Constants definition
const IN_MYBB = 1;
const TSCR_VERSION = '1.2';
const IN_CRON = true;
const IN_TRACKER = true;
const TS_TIMEOUT = 3600;
define('TIMENOW', time());
define('THIS_PATH', __DIR__);
define('CONFIG_DIR', THIS_PATH . '/config/');
define('CRON_PATH', THIS_PATH . '/include/cron/');
define('INC_PATH', THIS_PATH . '/include');
define('TSDIR', THIS_PATH);
const APP_INITIALIZED = true;

// User group constants
const UC_GUEST = 0;
const UC_USER = 1;
const UC_POWER_USER = 2;
const UC_VIP = 3;
const UC_UPLOADER = 4;
const UC_MODERATOR = 5;
const UC_ADMINISTRATOR = 6;
const UC_SYSOP = 7;
const UC_BANNED = 9;

// Size and time constants
const GB_IN_BYTES = 1024 * 1024 * 1024;
const DAY_IN_SECONDS = 86400;
const WEEK_IN_SECONDS = 604800;
const HOUR_IN_SECONDS = 3600;

// Required files
require_once INC_PATH . '/init.php';
require CRON_PATH . '/cron_functions.php';
require_once INC_PATH . '/readconfig.php';
require_once INC_PATH . '/datahandler.php';



// Send 1x1 transparent GIF
$transparentGif = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
$gifSize = strlen($transparentGif);

header('Content-Type: image/gif');

// Only send Content-Length if not IIS with CGI
if (!(str_contains($_SERVER['SERVER_SOFTWARE'] ?? '', 'Microsoft-IIS') && str_contains(PHP_SAPI, 'cgi'))) {
    header('Content-Length: ' . $gifSize);
    header('Connection: Close');
}

echo $transparentGif;
flush();

// Load language and execute cron jobs
$lang->load('cronjobs');

// Get pending cron jobs
$cronQuery = $db->simple_select(
    "cron", 
    "cronid, minutes, filename, loglevel", 
    "nextrun <= '" . TIMENOW . "' AND active = '1'"
);

if ($db->num_rows($cronQuery) > 0) {
    while ($cronJob = $db->fetch_array($cronQuery)) {
        $cronFile = CRON_PATH . $cronJob['filename'];
        
        if (file_exists($cronFile)) {
            $CQueryCount = 0;
            $startTime = microtime(true);
            
            // Execute cron job
            include $cronFile;
            
            // Log execution if loglevel is 1
            if ($cronJob['loglevel'] == '1') {
                $executionTime = round(microtime(true) - $startTime, 4);
                logcronaction($cronJob['filename'], $CQueryCount, $executionTime);
            }
            
            // Calculate next run time
            $nextRun = TIMENOW + (int)$cronJob['minutes'];
            
            // Update next run time
            $db->update_query(
                "cron", 
                ["nextrun" => $nextRun], 
                "cronid = '" . (int)$cronJob['cronid'] . "'"
            );
        } 
		
    }
}