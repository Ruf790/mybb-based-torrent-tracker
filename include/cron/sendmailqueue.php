<?php


declare(strict_types=1);


// Disallow direct access to this file for security reasons
if (!defined('IN_CRON')) {
    exit();
}

$mail_queue_limit = "10";
$num_to_send = max(1, (int)$mail_queue_limit);

$sent = send_mail_queue($num_to_send);
++$CQueryCount;

// Only when something was really sent (was: "sent up to 10" on every run)
if ($sent > 0) {
    savelog("Mail queue: sent {$sent} message(s).");
    ++$CQueryCount;
}
