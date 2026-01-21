<?php

if (!defined('IN_CRON')) {
    exit();
}



function torrent_promotion_expire($days, $type = 2, $targettype = 1)
{
    global $db, $CQueryCount;
    
    $secs = (int)($days * 86400); // XX дней
    $dt = TIMENOW - $secs;
    
    // Определяем, какое поле проверять в зависимости от типа промо
    $field_condition = "";
    
    // Используем if вместо switch чтобы избежать ошибок
    if ($type == 1) {
        $field_condition = "free = 'no' AND silver = 'no' AND doubleupload = 'no'";
    } elseif ($type == 2) {
        $field_condition = "free = 'yes'";
    } elseif ($type == 3) {
        $field_condition = "doubleupload = 'yes' AND free = 'no'";
    } elseif ($type == 4) {
        $field_condition = "free = 'yes' AND doubleupload = 'yes'";
    } elseif ($type == 5) {
        $field_condition = "silver = 'yes' AND free = 'no' AND doubleupload = 'no'";
    } elseif ($type == 6) {
        $field_condition = "silver = 'yes' AND doubleupload = 'yes' AND free = 'no'";
    } elseif ($type == 7) {
        $field_condition = "silver = 'yes' AND free = 'no' AND doubleupload = 'no'";
    } else {
        $field_condition = "free = 'yes'";
    }
    
    // Запрос для поиска торрентов с истекшим промо
    $sql = "SELECT id, name FROM torrents 
            WHERE added < $dt 
            AND $field_condition AND ts_external = 'no'
            AND promotion_time_type = 0";
				
    
    $res = $db->sql_query($sql);
	
	++$CQueryCount;	
    
    if (!$res) {
        savelog("Database error in torrent_promotion_expire", 'error');
        return 0;
    }
    
    // Определяем новые значения полей в зависимости от targettype
    $update_fields = "";
    $become = "";
    
    if ($targettype == 1) {
        $update_fields = "free = 'no', silver = 'no', doubleupload = 'no'";
        $become = "normal";
    } elseif ($targettype == 2) {
        $update_fields = "free = 'yes', silver = 'no', doubleupload = 'no'";
        $become = "Free";
    } elseif ($targettype == 3) {
        $update_fields = "free = 'no', silver = 'no', doubleupload = 'yes'";
        $become = "2X";
    } elseif ($targettype == 4) {
        $update_fields = "free = 'yes', silver = 'no', doubleupload = 'yes'";
        $become = "2X Free";
    } elseif ($targettype == 5) {
        $update_fields = "free = 'no', silver = 'yes', doubleupload = 'no'";
        $become = "50%";
    } elseif ($targettype == 6) {
        $update_fields = "free = 'no', silver = 'yes', doubleupload = 'yes'";
        $become = "2X 50%";
    } elseif ($targettype == 7) {
        $update_fields = "free = 'no', silver = 'yes', doubleupload = 'no'";
        $become = "50%";
    } else {
        $update_fields = "free = 'no', silver = 'no', doubleupload = 'no'";
        $become = "normal";
    }
    
    $processed = 0;
    
    // Используем $db методы вместо mysqli
    while ($arr = $db->fetch_array($res)) {
        $id = (int)$arr['id'];
        $name = $arr['name'];
        
        $update_sql = "UPDATE torrents SET $update_fields WHERE id = $id";
        $update_res = $db->sql_query($update_sql);
		
		++$CQueryCount;
        
        if (!$update_res) {
            savelog("Failed to update torrent $id", 'error');
			
			++$CQueryCount;
			
            continue;
        }
        
        if ($targettype == 1) {
            savelog("Torrent $id ($name) is no longer on promotion (time expired)", 'normal');
			
			++$CQueryCount;
			
        } else {
            savelog("Promotion type for torrent $id ($name) is changed to $become (time expired)", 'normal');
			
			++$CQueryCount;
        }
        
        $processed++;
    }
    
    $db->free_result($res);
    
    // Возвращаем количество обработанных торрентов для логирования
    return $processed;
}


// Логируем запуск
savelog("Starting torrent promotion expiration cleanup", 'cron');
++$CQueryCount;

// Обрабатываем истечение промо-акций
$processed = 0;

if ($expirehalfleech_torrent > 0) {
    $count = torrent_promotion_expire($expirehalfleech_torrent, 5, $halfleechbecome_torrent);
    $processed += $count;
    savelog("Expired 50% Leech promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expirefree_torrent > 0) {
    $count = torrent_promotion_expire($expirefree_torrent, 2, $freebecome_torrent);
    $processed += $count;
    savelog("Expired Free Leech promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expiretwoup_torrent > 0) {
    $count = torrent_promotion_expire($expiretwoup_torrent, 3, $twoupbecome_torrent);
    $processed += $count;
    savelog("Expired 2X Upload promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expiretwoupfree_torrent > 0) {
    $count = torrent_promotion_expire($expiretwoupfree_torrent, 4, $twoupfreebecome_torrent);
    $processed += $count;
    savelog("Expired Free + 2X promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expiretwouphalfleech_torrent > 0) {
    $count = torrent_promotion_expire($expiretwouphalfleech_torrent, 6, $twouphalfleechbecome_torrent);
    $processed += $count;
    savelog("Expired 50% + 2X promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expirethirtypercentleech_torrent > 0) {
    $count = torrent_promotion_expire($expirethirtypercentleech_torrent, 7, $thirtypercentleechbecome_torrent);
    $processed += $count;
    savelog("Expired 30% Leech promotions: $count torrents", 'cron');
	++$CQueryCount;
}

if ($expirenormal_torrent > 0) {
    $count = torrent_promotion_expire($expirenormal_torrent, 1, $normalbecome_torrent);
    $processed += $count;
    savelog("Expired Normal torrents (set to new promotion): $count torrents", 'cron');
	++$CQueryCount;
}

savelog("Finished torrent promotion expiration cleanup. Total processed: $processed torrents", 'cron');
++$CQueryCount;