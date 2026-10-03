<?php
declare(strict_types=1);

/**
 * Cron: Claims monthly settlement (NexusPHP "claim:settle", hourly but only does work on the 1st).
 * Here it runs every hour and settles whatever has not been settled for the month that ended:
 * on the 1st normally, and later as a catch-up if the cron was down.
 */

if (!defined('IN_CRON')) {
    exit();
}

if (!function_exists('claim_settle_cron')) {
    require_once (defined('INC_PATH') ? INC_PATH : dirname(__DIR__)) . '/functions_claim.php';
}

if (CLAIM_ENABLED) {
    claim_settle_cron();
	++$CQueryCount;
}
