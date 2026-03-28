<?php

require_once 'global.php';
define ('CA_VERSION', '0.4 by xam');

if (!$CURUSER)
{
    exit ();
}

// Получаем ID анонса из GET параметра
$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Увеличиваем счетчик просмотров, если передан ID анонса
if ($announcement_id > 0) {
    $db->sql_query("UPDATE announcements SET views = views + 1 WHERE id = " . $announcement_id);
}

header ('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . 'GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');
header ('' . 'Content-type: text/html; charset=' . $shoutboxcharset);

if (($CURUSER AND $CURUSER['announce_read'] == 'no'))
{
    $update_read = array(
        "announce_read" => 'yes'                        
    );
    
    $db->update_query("users", $update_read, "announce_read='no' AND id='{$CURUSER['id']}'");
}


redirect('browse.php');

?>
