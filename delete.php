<?php

/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

require_once 'global.php';

define('D_VERSION', '0.7');

$lang->load('delete');

// ★ Упрощенная проверка вместо class_page_check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valid_referer = strpos($_SERVER['HTTP_REFERER'], $BASEURL) === 0;
    if (!$valid_referer) {
        stderr("Error", "Invalid request source");
    }
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
int_check($id, true);

$res = $db->sql_query('SELECT name, owner FROM torrents WHERE id = ' . $db->sqlesc($id));
$row = $db->fetch_array($res); // ★ ИСПРАВИТЬ на fetch_array

if (!$row) {
    stderr($lang->global['error'], $lang->global['notorrentid']);
}

$is_mod = is_mod($usergroups);


if (is_mod($usergroups) || $CURUSER['id'] == $row['owner']) 
{
    $rt = (int)($_POST['reasontype'] ?? 5); // Установить значение по умолчанию если не передано

    if ($rt < 1 || $rt > 5) {
        stderr($lang->global['error'], sprintf($lang->delete['invalidreason'], $rt));
    }

    $r = $_POST['r'] ?? null;
    $reason = $_POST['reason'] ?? [];

    // ★ Установить значение по умолчанию для reason
    if (empty($reason[3])) {
        $reason[3] = 'Deleted via quick delete modal';
    }

    switch ($rt) 
	{
        case 1:
            $reasonstr = $lang->delete['reasonstr1'];
            break;
        case 2:
            $reasonstr = $lang->delete['reasonstr2'] . (isset($reason[0]) ? ': ' . trim($reason[0]) : '!');
            break;
        case 3:
            $reasonstr = $lang->delete['reasonstr3'] . (isset($reason[1]) ? ': ' . trim($reason[1]) : '!');
            break;
        case 4:
            if (!isset($reason[2])) {
                stderr($lang->global['error'], $lang->delete['violaterule']);
            }
            $reasonstr = sprintf($lang->delete['reasonstr4'], $SITENAME) . trim($reason[2]);
            break;
        default:
            if (!isset($reason[3])) {
                stderr($lang->global['error'], $lang->delete['enterreason']);
            }
            $reasonstr = trim($reason[3]);
            break;
    }

    require_once INC_PATH . '/functions_deletetorrent.php';
    deletetorrent($id, true);

    // Логирование действий
    if ($CURUSER['anonymous'] === 'yes' && is_mod($usergroups)) 
	{
        write_log(sprintf($lang->delete['logmsg1'], $id, $row['name'], htmlspecialchars($reasonstr)));
    } 
	else 
	{
        write_log(sprintf($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], htmlspecialchars($reasonstr)));
    }
	
	$cache->update_torrents();

    redirect($BASEURL . '/browse.php', $lang->delete['deleted'], '', 3, false, false);
    exit();
}

print_no_permission(true);
?>

