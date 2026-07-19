<?php

declare(strict_types=1);


require_once 'global.php';

define('D_VERSION', '0.7');

$lang->load('delete');

// Удаление торрента - только POST с валидным CSRF-токеном. Раньше ID
// можно было передать через GET, и удаление проходило по одной ссылке,
// без всякой защиты - Referer-проверка применялась только для POST, а
// GET-путь её вообще обходил стороной.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stderr($lang->global['error'], 'Invalid request method');
}

if (!verify_post_check($mybb->get_input('my_post_key'))) {
    stderr($lang->global['error'], 'Invalid security token. Please refresh the page and try again.');
}

$id = (int)($_POST['id'] ?? 0);
int_check($id, true);

$res = $db->sql_query('SELECT name, owner FROM torrents WHERE id = ' . $db->sqlesc($id));
$row = $db->fetch_array($res);

if (!$row) {
    stderr($lang->global['error'], $lang->global['notorrentid']);
}

$is_mod = is_mod($usergroups);

if ($is_mod || (int)$CURUSER['id'] === (int)$row['owner']) {
    $rt = (int)($_POST['reasontype'] ?? 5);
    
    if ($rt < 1 || $rt > 5) {
        stderr($lang->global['error'], sprintf($lang->delete['invalidreason'], $rt));
    }

    $reason = $_POST['reason'] ?? [];
    
   
    $reason[3] ??= 'Deleted via quick delete modal';

    $reasonstr = match ($rt) {
        1 => $lang->delete['reasonstr1'],
        2 => $lang->delete['reasonstr2'] . (isset($reason[0]) ? ': ' . trim($reason[0]) : '!'),
        3 => $lang->delete['reasonstr3'] . (isset($reason[1]) ? ': ' . trim($reason[1]) : '!'),
        4 => isset($reason[2]) 
            ? sprintf($lang->delete['reasonstr4'], $SITENAME) . trim($reason[2])
            : stderr($lang->global['error'], $lang->delete['violaterule']),
        default => isset($reason[3]) 
            ? trim($reason[3])
            : stderr($lang->global['error'], $lang->delete['enterreason'])
    };

    require_once INC_PATH . '/functions_deletetorrent.php';
    deletetorrent($id, true);

   
	
	$logMessage = (isset($CURUSER['invisible']) && $CURUSER['invisible'] == 1) && $is_mod
        ? sprintf($lang->delete['logmsg1'], $id, $row['name'], htmlspecialchars($reasonstr))
        : sprintf($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], htmlspecialchars($reasonstr));
    
    write_log($logMessage);
    
    $cache->update_torrents();

    redirect($BASEURL . '/browse.php', $lang->delete['deleted'], '', false);
    exit();
}

print_no_permission(true);