<?php

declare(strict_types=1);

if (!defined('IN_CRON')) {
    exit();
}

require_once INC_PATH . '/functions_pm.php';

$wrapped = $db->sql_query_prepared(
    "SELECT v.userid as id, v.old_gid, u.modcomment
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

if ($wrapped && $wrapped->result) {
    while ($row = mysqli_fetch_array($wrapped->result, MYSQLI_BOTH)) {
        $vip_expired_ids[] = $row['id'];

        $newGid = $row['old_gid'] ? (int)$row['old_gid'] : UC_POWER_USER;

        $db->sql_query_prepared(
            "UPDATE users SET usergroup = ?, modcomment = CONCAT(?, ?) WHERE id = ?",
            [
                $newGid,
                gmdate('Y-m-d') . " - VIP status removed by -AutoSystem.
",
                $row['modcomment'] ?? '',
                (int)$row['id'],
            ]
        );
        $CQueryCount++;

        $db->sql_query_prepared("DELETE FROM auto_vip WHERE userid = ?", [(int)$row['id']]);
        $CQueryCount++;

        $result = send_pm([
            'subject' => $lang->cronjobs['vip_subject'] ?? 'VIP status expired',
            'message' => $lang->cronjobs['vip_message'] ?? 'Your VIP status has expired.',
            'touid'   => (int)$row['id'],
            'sender'  => ['uid' => -1],
        ], -1, true);
        if ($result) $CQueryCount++;
    }
    mysqli_free_result($wrapped->result);
}
if ($wrapped && $wrapped->stmt) {
    mysqli_stmt_close($wrapped->stmt);
}

if ($vip_expired_ids) {
    savelog('Following user(s) has been demoted: ' . implode(', ', $vip_expired_ids) . '. Reason: KPS VIP status has been expired!');
    $CQueryCount++;
}
