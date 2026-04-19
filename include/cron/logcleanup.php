<?php



if (!defined('IN_CRON')) {
    exit();
}


$log_pruning = [
    'admin_logs'     => 365,
    'mod_logs'       => 365,
    'mail_logs'      => 180,
    'user_mail_logs' => 180,
];

// Clear out old admin logs
if ($log_pruning['admin_logs'] > 0) {
    $cut = TIMENOW - 60 * 60 * 24 * $log_pruning['admin_logs'];
    $db->delete_query("adminlog", "dateline<'{$cut}'");
    ++$CQueryCount;
}

// Clear out old moderator logs
if ($log_pruning['mod_logs'] > 0) {
    $cut = TIMENOW - 60 * 60 * 24 * $log_pruning['mod_logs'];
    $db->delete_query("moderatorlog", "dateline<'{$cut}'");
    ++$CQueryCount;
}

// Clear out old mail error logs
if ($log_pruning['mail_logs'] > 0) {
    $cut = TIMENOW - 60 * 60 * 24 * $log_pruning['mail_logs'];
    $db->delete_query("mailerrors", "dateline<'{$cut}'");
    ++$CQueryCount;
}

// Clear out old user mail logs
if ($log_pruning['user_mail_logs'] > 0) {
    $cut = TIMENOW - 60 * 60 * 24 * $log_pruning['user_mail_logs'];
    $db->delete_query("maillogs", "dateline<'{$cut}'");
    ++$CQueryCount;
}