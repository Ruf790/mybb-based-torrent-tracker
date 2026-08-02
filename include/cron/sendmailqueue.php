<?php


declare(strict_types=1);


// Disallow direct access to this file for security reasons
if (!defined('IN_CRON')) {
    exit();
}

$mail_queue_limit = "10";
$num_to_send = max(1, (int)$mail_queue_limit);

send_mail_queue($num_to_send);
++$CQueryCount;

savelog('The send mail queue task sent up to ' . $num_to_send . ' messages.');
++$CQueryCount;
