<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'ts_update.php');

$rootpath = './../../';
require $rootpath . 'global.php';

define('TSU_VERSION', '1.0 by xam');

// Убраны @ — в PHP 8.5 подавление ошибок не рекомендуется
ini_set('upload_max_filesize', (string) (1000 < $max_torrent_size ? $max_torrent_size : 10485760));
ini_set('memory_limit', '20000M');

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);

if (isset($_POST['ajax_update']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && is_valid_id($id)) {
    define('USE_AJAX', true);
    $ajax = true;
} else {
    $ajax = false;
    int_check($id);
    $returnto  = isset($_SERVER['HTTP_REFERER']) ? fix_url($_SERVER['HTTP_REFERER']) : 'browse.php';
    $returnto .= str_contains($returnto, '?') ? '&tsuid=' . $id : '?tsuid=' . $id;
    $returnto  = str_replace([$BASEURL, '//'], ['', '/'], $returnto);
}

$query = $db->simple_select('torrents', 'ts_external_lastupdate', "id='{$id}' AND ts_external = 'yes'");

if (!$db->num_rows($query)) {
    if (!$ajax) {
        redirect($returnto, $lang->global['recentlyupdated']);
        exit;
    }
    show_msg($lang->global['recentlyupdated']);
}

$Result                 = $db->fetch_array($query);
$ts_external_lastupdate = $Result['ts_external_lastupdate'];

$is_mod = is_mod($usergroups);

if (!$is_mod && TIMENOW - $ts_external_lastupdate < 3600) {
    if (!$ajax) {
        redirect($returnto, $lang->global['recentlyupdated']);
        exit;
    }
    show_msg($lang->global['recentlyupdated']);
}

$externaltorrent = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';

require_once INC_PATH . '/ts_external_scrape/ts_external.php';

if (!$ajax) {
    redirect($returnto, $lang->global['externalupdated']);
} else {
    $seeders  = isset($seeders)  ? ts_nf($seeders)  : 0;
    $leechers = isset($leechers) ? ts_nf($leechers) : 0;
    show_msg("<span class='sticky'>{$seeders}</span>|<span class='sticky'>{$leechers}</span>|{$id}", false);
}

function show_msg(string $message = '', bool $error = true): never
{
    global $shoutboxcharset;
    header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=' . $shoutboxcharset);
    exit($error ? '<error>' . $message . '</error>' : $message);
}