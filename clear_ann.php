<?php
declare(strict_types=1);

require_once 'global.php';

define('CA_VERSION', '0.5');

if (!$CURUSER) {
    exit();
}

// Увеличиваем счётчик просмотров
$announcement_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($announcement_id > 0) {
    $db->sql_query_prepared("UPDATE announcements SET views = views + 1 WHERE id = ?", [$announcement_id]);
}

// Отмечаем анонс как прочитанный
if ($CURUSER['announce_read'] === 'no') {
    $db->sql_query_prepared("UPDATE users SET announce_read = 'yes' WHERE announce_read='no' AND id = ?", [$CURUSER['id']]);
}

header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=' . $shoutboxcharset);

redirect('browse.php');
