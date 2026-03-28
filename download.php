<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

declare(strict_types=1);

function print_download_error(string $message = ''): void
{
    global $action_type;
    
    if (!$action_type || $action_type != 'rss') {
        print_no_permission(true);
        return;
    }

    exit($message);
}

require_once 'global.php';
define('DL_VERSION', '2.4.6');

$lang->load('download');

$action_type = $_GET['type'] ?? '';

if ($action_type == 'magnet') {
    define('SKIP_LOCATION_SAVE', true);
    // без passkey — просто продолжаем
}

if ($action_type == 'rss') {
    define('SKIP_LOCATION_SAVE', true);

    $secret_key = htmlspecialchars($_GET['secret_key'] ?? '');
    if (empty($secret_key) || strlen($secret_key) != 32) {
        print_download_error();
    }

    $id = (int)($_GET['id'] ?? 0);
    if (!is_valid_id($id)) {
        print_download_error();
    }

    //$ip = getip();
    //require_once INC_PATH . '/functions_isipbanned.php';
    
    //if (isipbanned($ip)) {
        //print_download_error();
    //}

    $res = $db->sql_query('SELECT * FROM users WHERE passkey=' . $db->sqlesc($secret_key) . ' LIMIT 1');
    
    if ($db->num_rows($res) == 0) {
        print_download_error();
    }

    $row = $db->fetch_array($res);
    require TSDIR . '/cache/usergroups.php';
    
    $group_data_results = $usergroupscache[$row['usergroup']];
    $GLOBALS['usergroups'] = $group_data_results;
    $GLOBALS['CURUSER'] = $row;
    
    if ($group_data_results['isbannedgroup'] == '1' || $row['enabled'] != 'yes' || $row['status'] != 'confirmed') {
        unset($GLOBALS['CURUSER']);
        unset($GLOBALS['usergroups']);
        unset($group_data_results);
        unset($usergroupscache);
        print_download_error();
    }

    unset($row, $group_data_results, $usergroupscache);
} else {
    maxsysop();
}

@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);

if (@ini_get('output_handler') == 'ob_gzhandler' && @ob_get_length() !== false) {
    @ob_end_clean();
    @header('Content-Encoding:');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!is_valid_id($id)) {
    print_download_error();
}

$gigs = $CURUSER['downloaded'] / (1024 * 1024 * 1024);
$ratio = $CURUSER['downloaded'] > 0 ? $CURUSER['uploaded'] / $CURUSER['downloaded'] : 0;
$is_mod = is_mod($usergroups);

$res = $db->sql_query('SELECT t.id, t.name, t.filename, t.ts_external, t.size, t.owner, t.free FROM torrents t LEFT JOIN categories c ON t.category = c.id WHERE t.id = ' . $db->sqlesc($id));
$row = $db->fetch_array($res);

$query = $db->sql_query('SELECT candownload FROM users_perm WHERE userid = ' . $db->sqlesc($CURUSER['id']));
if ($db->num_rows($query) > 0) {
    $downperm = $db->fetch_array($query);
    if ($downperm['candownload'] == '0') {
        print_download_error();
    }
}

$xbt_active = "no";

// Hit and run check
if ($ratio <= $hitrun_ratio && 
    $CURUSER["downloaded"] != 0 && 
    !$is_mod && 
    $hitrun == "yes" && 
    $usergroups["isvipgroup"] != "yes" && 
    $row["owner"] != $CURUSER["id"] && 
    $row["free"] != "yes") {
    
    $has = $db->num_rows($db->sql_query(
        "SELECT torrentid FROM snatched WHERE torrentid = " . 
        $db->sqlesc($id) . " AND userid = " . 
        $db->sqlesc($CURUSER["id"]) . " AND finished = \"yes\""
    ));
    
    if (!$has) {
        $percentage = $ratio * 100;
        $userlist_url = $BASEURL . "/" . ($xbt_active == "yes" ? "mysnatchlist" : "userdetails") . ".php";
        
        stderr(
            sprintf(
                $lang->download["downloadwarning"], 
                number_format($ratio, 2), 
                mksize($percentage), 
                $hitrun_ratio,
                "<a href=\"{$userlist_url}\">{$userlist_url}</a>"
            ), 
            true
        );
        stdhead();
        exit;
    }
}

$external = $row['ts_external'] == 'yes';
$id = (int)$row['id'];
$fn = $torrent_dir . '/' . $id . '.torrent';

if (!$row) {
    print_download_error($lang->download['error1']);
}

if (!is_file($fn)) {
    print_download_error($lang->download['error2']);
}

if (!is_readable($fn)) {
    print_download_error($lang->download['error3']);
}

// Update hit counter
$db->update_query("torrents", ['hits' => 'hits+1'], "id='{$id}'", '1', true);

// Load and modify torrent file
require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;
use Arokettu\Bencode\Bencode;

$torrentFileObj = TorrentFile::load($fn);


// Magnet для внешних торрентов без passkey
if ($external && $action_type == 'magnet') {
    $infoHash = $torrentFileObj->v1()->getInfoHash();
    $name = $torrentFileObj->getName();

    $magnet = 'magnet:?xt=urn:btih:' . $infoHash;
    $magnet .= '&dn=' . urlencode($name);

    $announceList = $torrentFileObj->getAnnounceList();
    if ($announceList) {
        foreach ($announceList as $tier) {
            foreach ($tier as $tracker) {
                $magnet .= '&tr=' . urlencode((string)$tracker);
            }
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $magnet;
    exit;
}





if (!$external) {
    $AnnounceURL = ts_seo($CURUSER['passkey'], $row['filename'], "a");
    $torrentFileObj->setAnnounce($AnnounceURL);
}

$TorrentContents = $torrentFileObj->storeToString();

if ($usezip != 'yes' || $action_type == 'rss') {
    require_once INC_PATH . '/functions_browser.php';
    
    if (is_browser('ie')) {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Disposition: attachment; filename=' . basename($row['filename']) . ';');
        header('Content-Transfer-Encoding: binary');
    } else {
        header('Expires: Tue, 1 Jan 1980 00:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('X-Powered-By: ' . VERSION . ' (c) ' . date('Y') . ' ' . $SITENAME . '');
        header('Accept-Ranges: bytes');
        header('Connection: close');
        header('Content-Transfer-Encoding: binary');
        header('Content-Type: application/x-bittorrent');
        header('Content-Disposition: attachment; filename=' . basename($row['filename']) . ';');
    }

    ob_implicit_flush(true);
    echo $TorrentContents;
    exit;
}

// ZIP download handling
require_once INC_PATH . '/class_zip.php';
$createZip = new createZip();
$fileContents2 = 'This torrent was downloaded from ' . $BASEURL;
$createZip->addFile($fileContents2, 'readme.txt');
$createZip->addFile($TorrentContents, $row['filename']);
$fileName = $row['filename'] . '.zip';

$fd = fopen('cache/' . $fileName, 'wb');
fwrite($fd, $createZip->getZippedfile());
fclose($fd);

$createZip->forceDownload('cache/' . $fileName);
?>