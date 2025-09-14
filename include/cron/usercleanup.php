<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

if (!defined('IN_CRON')) {
    exit();
}

// Expire bans
$query = $db->simple_select("banned", "uid, oldgroup, oldadditionalgroups, olddisplaygroup", "lifted != 0 AND lifted < " . TIMENOW);
$CQueryCount++;

$banned_users = [];
while($ban = $db->fetch_array($query)) {
    $banned_users[] = $ban;
}

if (!empty($banned_users)) {
    $user_ids = [];
    foreach ($banned_users as $ban) {
        $user_ids[] = (int)$ban['uid'];
        
        // Обновление пользователя
        $updated_user = [
            "usergroup" => (int)$ban['oldgroup'],
            "additionalgroups" => $db->escape_string($ban['oldadditionalgroups']),
            "displaygroup" => (int)$ban['olddisplaygroup'],
            "notifs" => ''
        ];
        $db->update_query("users", $updated_user, "uid = '" . (int)$ban['uid'] . "'");
        $CQueryCount++;
    }
    
    // Массовое удаление банов
    $db->delete_query("banned", "uid IN (" . implode(',', $user_ids) . ")");
    $CQueryCount++;
    
    savelog('Expired bans removed for users: ' . implode(', ', $user_ids));
    $CQueryCount++;
} else {
    savelog('No expired bans to remove');
    $CQueryCount++;
}
