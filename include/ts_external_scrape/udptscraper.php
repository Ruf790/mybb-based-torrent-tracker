<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

class udptscraper extends tscraper
{
    public function scrape($url, $infohash)
    {
        if (!is_array($infohash)) 
		{
            $infohash = [$infohash];
        }
        foreach ($infohash as $hash) 
		{
            if (!preg_match("#^[a-f0-9]{40}\$#i", $hash)) 
			{
                throw new ScraperException("Invalid infohash: " . $hash . ".");
            }
        }
        if (74 < count($infohash)) 
		{
            throw new ScraperException("Too many infohashes provided.");
        }
        if (!preg_match("%udp://([^:/]*)(?::([0-9]*))?(?:/)?%si", $url, $m)) 
		{
            throw new ScraperException("Invalid tracker url.");
        }
        $tracker = "udp://" . $m[1];
        $port = isset($m[2]) ? $m[2] : 80;
        $transaction_id = mt_rand(0, 65535);
        $fp = @fsockopen($tracker, $port, $errno, $errstr);
        if (!$fp) {
            throw new ScraperException("Could not open UDP connection: " . $errno . " - " . $errstr, 0, true);
        }
        stream_set_timeout($fp, $this->timeout);
        $current_connid = "\0\0\4\27'\20\31�";
        $packet = $current_connid . pack("N", 0) . pack("N", $transaction_id);
        @fwrite($fp, $packet);
        $ret = fread($fp, 16);
        if (strlen($ret) < 1) 
		{
            throw new ScraperException("No connection response.", 0, true);
        }
        if (strlen($ret) < 16) 
		{
            throw new ScraperException("Too short connection response.");
        }
        $retd = unpack("Naction/Ntransid", $ret);
        if ($retd["action"] != 0 || $retd["transid"] != $transaction_id) 
		{
            throw new ScraperException("Invalid connection response.");
        }
        $current_connid = substr($ret, 8, 8);
        $hashes = "";
        foreach ($infohash as $hash) 
		{
            $hashes .= pack("H*", $hash);
        }
        $packet = $current_connid . pack("N", 2) . pack("N", $transaction_id) . $hashes;
        fwrite($fp, $packet);
        $readlength = 8 + 12 * count($infohash);
        $ret = fread($fp, $readlength);
        if (strlen($ret) < 1) 
		{
            throw new ScraperException("No scrape response.", 0, true);
        }
        if (strlen($ret) < 8) 
		{
            throw new ScraperException("Too short scrape response.");
        }
        $retd = unpack("Naction/Ntransid", $ret);
        if ($retd["action"] != 2 || $retd["transid"] != $transaction_id) 
		{
            throw new ScraperException("Invalid scrape response.");
        }
        if (strlen($ret) < $readlength) 
		{
            throw new ScraperException("Too short scrape response.");
        }
        $torrents = [];
        $index = 8;
        foreach ($infohash as $hash) 
		{
            $retd = unpack("Nseeders/Ncompleted/Nleechers", substr($ret, $index, 12));
            $retd["infohash"] = $hash;
            $torrents[$hash] = $retd;
            $index = $index + 12;
        }
        return $torrents;
    }
}

?>