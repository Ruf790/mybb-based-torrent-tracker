<?php


require_once 'global.php';
$lang->load("takewhatever");

// Проверка пользователя
if (!isset($CURUSER) || (int)$CURUSER['id'] === 0) {
    stderr($lang->takewhatever["takereseednouser"]);
}

// Включаем gzip и права sysop
gzip();
maxsysop();

define('TR_VERSION', '0.5 ');
define("IN_MYBB", 1);

// Подключаем обработчик данных
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_pm.php';

/**
 * Проверка на спам
 */
function spamcheck(int $reseedid = 0, int $receiver = 0, int $sender = 0): bool
{
    global $db;

    $spamcheck = $db->simple_select(
        'messages',
        'sender',
        "sender = '{$sender}' AND subject = '{$db->escape_string($_GET['subject'] ?? '')}' AND receiver = '{$receiver}'"
    );

    return ($db->num_rows($spamcheck) === 0);
}


// Получаем данные
$reseedid = intval($_GET['reseedid'] ?? 0);
$userid   = (int)$CURUSER['id'];
int_check([$reseedid, $userid], true);

// Получаем всех завершивших закачку
$sql = "
    SELECT s.uploaded, s.downloaded, s.userid, t.name, u.username
    FROM snatched s
    INNER JOIN torrents t ON s.torrentid = t.id
    INNER JOIN users u ON s.userid = u.id
    WHERE s.finished = 'yes' 
      AND s.torrentid = ?
";

$params = [$reseedid];

$res = $db->sql_query_prepared($sql, $params);





if ($db->num_rows($res->result) === 0) {
    stderr($lang->takewhatever['takereseednouser']);
}

// Тема сообщения
$subject = sprintf($lang->takewhatever['reseedsubject'], $reseedid);

// Отправка PM
while ($row = $db->fetch_array($res->result)) {
    $name_torrent = $db->escape_string($row['name']);
    $reseedmsg = sprintf(
        $lang->takewhatever['reseedmsg'],
        $row['username'],
        '[URL=' . $BASEURL . '/' . get_torrent_link($reseedid) . ']' . $name_torrent . '[/URL]',
        mksize($row['uploaded']),
        mksize($row['downloaded'])
    );

    $pm = [
        'subject' => $subject,
        'message' => $reseedmsg,
        'touid'   => (int)$row['userid']
    ];

    send_pm($pm, $CURUSER['id'], true);
}

// Редирект на страницу торрента
redirect(get_torrent_link($reseedid));
