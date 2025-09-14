<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

define("THIS_SCRIPT", "ts_update.php");
$rootpath = "./../../";
require $rootpath . "global.php";
define("TSU_VERSION", "1.0 by xam");
@ini_set("upload_max_filesize", 1000 < $max_torrent_size ? $max_torrent_size : 10485760);
@ini_set("memory_limit", "20000M");
$id = intval(TS_Global("id"));
if (isset($_POST["ajax_update"]) && strtoupper($_SERVER["REQUEST_METHOD"]) == "POST" && is_valid_id($id)) 
{
    define("USE_AJAX", true);
    $ajax = true;
} 
else 
{
    $ajax = false;
    int_check($id);
    $returnto = isset($_SERVER["HTTP_REFERER"]) ? fix_url($_SERVER["HTTP_REFERER"]) : "browse.php";
    $returnto .= strpos($returnto, "?") ? "&tsuid=" . $id : "?tsuid=" . $id;
    $returnto = str_replace([$BASEURL, "//"], ["", "/"], $returnto);
}
//$query = $db->sql_query("SELECT ts_external_lastupdate FROM torrents WHERE id = " . $db->sqlesc($id) . " AND ts_external = 'yes'");


$query = $db->simple_select("torrents", "ts_external_lastupdate", "id='{$id}' AND ts_external = 'yes'");


if (!$db->num_rows($query)) 
{
    if (!$ajax) 
	{
        redirect($returnto, $lang->global["recentlyupdated"]);
        exit;
    }
    show_msg($lang->global["recentlyupdated"]);
}
$Result = $db->fetch_array($query);
$ts_external_lastupdate = $Result["ts_external_lastupdate"];
if (!$is_mod && TIMENOW - $ts_external_lastupdate < 3600) 
{
    if (!$ajax) 
	{
        redirect($returnto, $lang->global["recentlyupdated"]);
        exit;
    }
    show_msg($lang->global["recentlyupdated"]);
}
$externaltorrent = TSDIR . "/" . $torrent_dir . "/" . $id . ".torrent";
require_once INC_PATH . "/ts_external_scrape/ts_external.php";
if (!$ajax) 
{
    redirect($returnto, $lang->global["externalupdated"]);
} 
else 
{
    if (!isset($seeders)) 
	{
        $seeders = 0;
    } else 
	{
        $seeders = ts_nf($seeders);
    }
    if (!isset($leechers)) 
	{
        $leechers = 0;
    } else 
	{
        $leechers = ts_nf($leechers);
    }
    show_msg("<span class='sticky'>" . $seeders . "</span>|<span class='sticky'>" . $leechers . "</span>|" . $id, false);
}
function show_msg($message = "", $error = true)
{
    global $shoutboxcharset;
    header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Content-type: text/html; charset=" . $shoutboxcharset);
    if ($error) {
        exit("<error>" . $message . "</error>");
    }
    exit($message);
}

?>