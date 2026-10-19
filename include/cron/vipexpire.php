<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

require_once INC_PATH . '/functions_pm.php';

$wrapped = $db->sql_query_prepared(
    "SELECT v.userid AS id, v.old_gid, u.modcomment, u.language
     FROM auto_vip v
     LEFT JOIN users u ON (v.userid = u.id)
     LEFT JOIN usergroups g ON (u.usergroup = g.gid)
     WHERE v.vip_until < ?
       AND g.cansettingspanel = '0'
       AND g.canstaffpanel = '0'
       AND g.issupermod = '0'
       AND u.enabled = 'yes'
       AND u.usergroup != ?",
    [TIMENOW, UC_BANNED]
);
$CQueryCount++;

$vip_expired_ids = [];

while ($wrapped && ($row = $db->fetch_array($wrapped))) {
    $uid = (int)$row['id'];
    $vip_expired_ids[] = $uid;

    $newGid = $row['old_gid'] ? (int)$row['old_gid'] : UC_POWER_USER;

    $db->sql_query_prepared(
        "UPDATE users SET usergroup = ?, modcomment = CONCAT(?, ?) WHERE id = ?",
        [
            $newGid,
            date('Y-m-d H:i') . " - VIP status removed by AutoSystem.\n",
            (string)($row['modcomment'] ?? ''),
            $uid,
        ]
    );
    $CQueryCount++;

    $db->sql_query_prepared("DELETE FROM auto_vip WHERE userid = ?", [$uid]);
    $CQueryCount++;

    // ЛС на языке получателя (тексты из cron.lang.php)
    $result = send_pm([
        'subject'       => ['vip_subject'],
        'message'       => ['vip_message'],
        'touid'         => $uid,
        'language'      => $row['language'] ?? null,
        'language_file' => 'cron',
        'sender'        => ['uid' => -1],
    ], -1, true);
    if ($result) {
        $CQueryCount++;
    }
}

if ($vip_expired_ids) {
    savelog('Following user(s) has been demoted: ' . implode(', ', $vip_expired_ids) . '. Reason: KPS VIP status has been expired!');
    $CQueryCount++;
}