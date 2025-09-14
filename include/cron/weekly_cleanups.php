<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Optimized Hit & Run Cron]=====*/
/***********************************************/

if (!defined('IN_CRON')) {
    exit();
}

require INC_PATH . '/functions_pm.php';

// ======= Настройки =======
$MinRatio      = 3.5;
$MinSeedTime   = 24;          // часы
$MinFinishDate = 1230772229;
$Enabled       = true;
$HRSkipGroups  = [7];         // массив usergroup для пропуска

define('HOUR_IN_SECONDS', 3600);

// ======= Функции =======

// Безопасное формирование IN-условия
function build_safe_in_clause(array $ids, string $field = 'id'): string {
    if (empty($ids)) return '0=1';
    $safe_ids = array_map('intval', $ids);
    return $field . ' IN (' . implode(',', $safe_ids) . ')';
}

// Массовая отправка PM
function send_bulk_pm(array $users, string $subject, string $message_template) 
{
    global $CQueryCount, $db;
    $pm_data = [
        'subject' => $subject,
        'sender'  => ['uid' => -1]
    ];

    foreach ($users as $HR) {
        $message = sprintf(
            $message_template,
            $HR['username'],
            '[URL=details.php?id=' . $HR['torrentid'] . ']' . htmlspecialchars($HR['name']) . '[/URL]',
            ($HR['seedtime'] > 0 ? floor($HR['seedtime'] / HOUR_IN_SECONDS) : 0),
            $GLOBALS['MinSeedTime'],
            '[URL=download.php?id=' . $HR['torrentid'] . ']' . htmlspecialchars($HR['name']) . '[/URL]',
            $GLOBALS['MinSeedTime']
        );

        $pm_data['message'] = $message;
        $pm_data['touid'] = (int)$HR['userid'];
        send_pm($pm_data, -1, true);
        $CQueryCount++;
    }
}

// ======= Основной код =======
if ($Enabled && ($MinSeedTime > 0 || $MinRatio > 0)) {
    $conditions = [
        "s.finished = 'yes'",
        "s.seeder = 'no'",
        "t.banned = 'no'",
        "u.enabled = 'yes'"
    ];

    if (!empty($HRSkipGroups)) {
        $conditions[] = 'u.usergroup NOT IN (0,' . implode(',', array_map('intval', $HRSkipGroups)) . ')';
    }

    if ($MinSeedTime > 0) {
        $conditions[] = "s.seedtime < " . ($MinSeedTime * HOUR_IN_SECONDS);
    }

    if ($MinRatio > 0) {
        $conditions[] = "s.uploaded / s.downloaded < " . $MinRatio;
    }

    if ($MinFinishDate > 0) {
        $conditions[] = "s.completedat > " . intval($MinFinishDate);
    }

    // Получаем всех пользователей, которые нужно предупредить
    $query = $db->sql_query("
        SELECT s.torrentid, s.userid, s.seedtime, t.name, u.username
        FROM snatched s
        INNER JOIN torrents t ON s.torrentid = t.id
        INNER JOIN users u ON s.userid = u.id
        WHERE " . implode(' AND ', $conditions)
    );
    $CQueryCount++;

    $WarnUsers = [];
    while ($HR = $db->fetch_array($query)) {
        $WarnUsers[$HR['userid']] = $HR;
    }

    if (!empty($WarnUsers)) {
        // 1. Массовая отправка PM
        send_bulk_pm(array_values($WarnUsers), $lang->cronjobs['lwarning_subject'], $lang->cronjobs['hr_warn_message']);

        // 2. Обновление timeswarned одним запросом
        $db->sql_query("UPDATE users SET timeswarned = timeswarned + 1 WHERE " . build_safe_in_clause(array_keys($WarnUsers)));
        $CQueryCount++;
    }

    unset($WarnUsers);
}
?>
