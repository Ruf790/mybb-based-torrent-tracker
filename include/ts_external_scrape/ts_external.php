<?php
use Arokettu\Torrent\TorrentFile;

define("TSE_VERSION", "1.1 by xam");
define("DEBUGMODE", false);
@ini_set("memory_limit", "20000M");

function isUdp(string $url): bool
{
    return str_starts_with($url, "udp://");
}

if ($Data = file_get_contents($externaltorrent)) {
    require_once __DIR__ . '/../../vendor/autoload.php';

    // Загрузка торрента из данных
    $Torrent = TorrentFile::loadFromString($Data);

    if ($Torrent) 
	{
        
		
		// Получаем info_hash v1 (если нужен v2 - нужно добавить)
$infoHashRaw = $Torrent->v1()->getInfoHash(); 
$friendlyInfoHash = strtoupper(bin2hex($infoHashRaw));

$rawData = $Torrent->getRawData()->toArray();

$announceListRaw = $rawData['announce-list'] ?? null;
$TrackerList = [];

if ($announceListRaw) 
{
    foreach ($announceListRaw as $tier) 
	{
        foreach ($tier as $tracker) 
		{
            $TrackerList[] = $tracker;
        }
    }
}

if (empty($TrackerList)) 
{
    if (isset($rawData['announce'])) 
	{
        $TrackerList[] = $rawData['announce'];
    }
}

$seeders = 0;
$leechers = 0;
$times_completed = 0;
$timeout = 2;
		
		
		
		

        foreach ($TrackerList as $TrackerURL) {
            if (isUdp($TrackerURL)) {
                require_once INC_PATH . "/ts_external_scrape/tscraper.php";
                require_once INC_PATH . "/ts_external_scrape/udptscraper.php";

                try {
                    $scraper = new udptscraper($timeout);
                    $ret = $scraper->scrape($TrackerURL, [$friendlyInfoHash]);

                    $seeders += $ret[$friendlyInfoHash]["seeders"] ?? 0;
                    $leechers += $ret[$friendlyInfoHash]["leechers"] ?? 0;
                    $times_completed += $ret[$friendlyInfoHash]["completed"] ?? 0;
                } catch (ScraperException $e) {
                    // Лог или пропуск ошибок
                }
            } else {
                require_once INC_PATH . "/ts_external_scrape/ts_decode.php";
                require_once INC_PATH . "/functions_ts_remote_connect.php";

                $TrackerURL = str_replace("announce", "scrape", $TrackerURL);
                $URLTAG = strpos($TrackerURL, "?") === false ? "?" : "&";
                $FINALURL = $TrackerURL . $URLTAG . "info_hash=" . urlencode($infoHashRaw);
                $STREAM = TS_Fetch_Data($FINALURL, false);

                if ($STREAM && ($decoded = BDecode($STREAM)) && isset($decoded["files"])) {
                    $files = $decoded["files"];

                    // В новой версии infoHashRaw - бинарный, нужно экранировать для ключа массива
                    $escapedInfoHash = addslashes($infoHashRaw);

                    if (isset($files[$escapedInfoHash])) {
                        $sha1tor = $files[$escapedInfoHash];
                        $seeders += $sha1tor["complete"] ?? 0;
                        $leechers += $sha1tor["incomplete"] ?? 0;
                        $times_completed += $sha1tor["downloaded"] ?? 0;
                    }
                }
            }
        }

        $update_torrents = [
            "seeders" => $db->escape_string($seeders),
            "leechers" => $db->escape_string($leechers),
            "times_completed" => $db->escape_string($times_completed),
            "ts_external_lastupdate" => TIMENOW
        ];
        $db->update_query("torrents", $update_torrents, "id='{$id}'");
    }
}
