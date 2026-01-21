<?php
declare(strict_types=1);

function checkconnect(string $host, int $port): string
{
    global $checkconnectable;
    
    if (!$checkconnectable || $checkconnectable === 'no') {
        return 'yes';
    }

    $fp = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($fp) {
        fclose($fp);
        return 'yes';
    }

    return 'no';
}

function stop(string $msg): never
{
    global $db;
    
    if ($db) 
	{
        mysqli_close($db);
    }

    header('Content-Type: text/plain');
    header('Pragma: no-cache');
    exit('d14:failure reason' . strlen($msg) . ':' . $msg . 'e');
}

function send_action(string $actionmessage, bool $resetpasskey = false): void
{
    global $announce_actions, $Tid, $Result, $ip, $passkey, $db;
    
    if ($announce_actions !== 'yes') 
	{
        return;
    }

    
    $stmt = mysqli_prepare($db, 'INSERT DELAYED INTO announce_actions (torrentid, userid, ip, passkey, actionmessage, actiontime) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iisssi", $Tid, $Result['userid'], $ip, $passkey, $actionmessage, $_SERVER['REQUEST_TIME']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if ($resetpasskey) 
	{
        $stmt = mysqli_prepare($db, 'UPDATE users SET passkey = \'\' WHERE id = ? AND passkey = ?');
        if ($stmt) 
		{
            mysqli_stmt_bind_param($stmt, "is", $Result['userid'], $passkey);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

define('IN_ANNOUNCE', true);
define('TIMENOW', time());
define('TSDIR', dirname(__FILE__));

require TSDIR . '/include/config_announce.php';
require TSDIR . '/include/languages/english/announce.lang.php';

$compact = isset($_GET['compact']) ? (int)$_GET['compact'] : 0;
$peer_id = $_GET['peer_id'] ?? '';
$port = isset($_GET['port']) ? (int)$_GET['port'] : 0;
$event = $_GET['event'] ?? '';
$downloaded = isset($_GET['downloaded']) ? (int)$_GET['downloaded'] : 0;
$uploaded = isset($_GET['uploaded']) ? (int)$_GET['uploaded'] : 0;
$left = isset($_GET['left']) ? (int)$_GET['left'] : 0;

$numwant = min(
    (int)($_GET['numwant'] ?? $_GET['num_want'] ?? $_GET['num want'] ?? 50),
    50
);

$update_user = $update_torrent = $update_snatched = [];

if (isset($_GET['passkey']) && str_contains($_GET['passkey'], '?')) {
    $parts = explode('?', $_GET['passkey'], 2);
    $_GET['passkey'] = $parts[0];
    
    if (isset($parts[1])) {
        $hashParts = explode('=', $parts[1], 2);
        if (count($hashParts) === 2) {
            $_GET['info_hash'] = $hashParts[1];
        }
    }
}

$passkey = $_GET['passkey'] ?? '';
$info_hash = $_GET['info_hash'] ?? '';
$info_hash2 = bin2hex($info_hash);

$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
$agent = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '');
$seeder = ($left === 0 ? 'yes' : 'no');

if (!((strlen($passkey) === 32 && strlen($info_hash) === 20 && strlen($peer_id) === 20 && $port > 0 && $port < 65535))) 
{
    stop($l['error'] ?? 'Invalid parameters');
}

if ($passkey === 'tssespecialtorrentv1byxamsep2007') {
    stop(($l['registerfirst'] ?? 'Please register first') . ($BASEURL ?? '') . '/signup.php');
}

$db = @mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if (!$db) {
    stop($l['cerror'] ?? 'Database connection error');
}


mysqli_set_charset($db, 'utf8mb4');


$stmt = mysqli_prepare($db, '
    SELECT t.id as tid, t.name, t.size, t.added, t.visible, t.banned, t.free, t.silver, t.doubleupload, 
           t.seeders, t.leechers, t.times_completed,
           u.id as userid, u.enabled, u.uploaded, u.downloaded, u.usergroup, u.birthday, u.regip,
           g.isbannedgroup, g.isvipgroup, g.canfreeleech
    FROM torrents t
    INNER JOIN users u ON (u.passkey = ?)
    INNER JOIN usergroups g ON (u.usergroup = g.gid)
    WHERE (t.info_hash = ? OR t.info_hash = ?)
    LIMIT 1');

if (!$stmt) {
    stop(($l['sqlerror'] ?? 'SQL error') . ' TU1');
}




mysqli_stmt_bind_param($stmt, "sss", $passkey, $info_hash2, $info_hash);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$Result = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);




if ((int)($Result['isbannedgroup'] ?? 0) === 1) 
{
    stop($l['qerror1'] ?? 'Download forbidden');
}


$Tid = $Result['tid'] ?? null;
$user_id22 = $Result['userid'] ?? null;

if (!$Result || !$Tid || ($Result['enabled'] ?? '') !== 'yes' || !$user_id22) 
{
    stop($l['tuerror'] ?? 'Torrent or user error');
}



if (($checkip ?? '') === 'yes' && ($Result['regip'] ?? '') !== $ip) {
    stop($l['invalidip'] ?? 'Invalid IP');
}

if (($detectbrowsercheats ?? '') === 'yes' && 
    isset($_SERVER['HTTP_COOKIE'], $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['HTTP_ACCEPT_CHARSET'])) {
    send_action('This user tried to cheat with a browser!', true);
    stop($l['invalidagent'] ?? 'Invalid agent');
}

if (($bannedclientdetect ?? '') === 'yes') {
    $Stop = false;
    
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'text/html, */*' || 
        (($_SERVER['HTTP_CONNECTION'] ?? '') === 'Close' && ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') !== 'gzip, deflate')) {
        $Stop = true;
    } elseif (($_SERVER['HTTP_ACCEPT'] ?? '') === 'text/html, */*' && ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') === 'identity') {
        $Stop = true;
    } else {
        $userclient = substr($peer_id, 0, 8);
        $allowed_clients = explode(',', $allowed_clients ?? '');
        if (!in_array($userclient, $allowed_clients)) {
            $Stop = true;
        }
    }

    if ($Stop) {
        stop($l['bannedclient'] ?? 'Banned client');
    }
}

$fields = 'seeder, peer_id, ip, port, uploaded, downloaded, userid, last_action, ('.TIMENOW.' - last_action) AS announcetime, last_action AS ts, '.TIMENOW.' AS nowts, prev_action AS prevts, connectable';


$gp_eq = (($nc ?? '') === 'yes' ? ' AND connectable = \'yes\'' : '');
$wantseeds = ($seeder === 'yes' ? ' AND seeder = \'no\'' : '');

$resp = "d8:completei" . ($Result["seeders"] ?? 0) . "e10:downloadedi" . ($Result["times_completed"] ?? 0) . "e10:incompletei" . ($Result["leechers"] ?? 0) . "e8:intervali" . ($announce_interval ?? 1800) . "e12:min intervali" . ($announce_interval ?? 1800) . (($privatetrackerpatch ?? '') === "yes" && $compact !== 1 ? "e7:privatei1" : "") . "e5:peers" . ($compact !== 1 ? "l" : "");

$peer = [];
$peer_num = 0;


$peer_query = 'SELECT ' . $fields . ' FROM peers WHERE torrent = ?' . $gp_eq . $wantseeds . ' ORDER BY last_action DESC LIMIT ?';
$stmt_peers = mysqli_prepare($db, $peer_query);
if ($stmt_peers) {
    mysqli_stmt_bind_param($stmt_peers, "ii", $Tid, $numwant);
    mysqli_stmt_execute($stmt_peers);
    $query_peers = mysqli_stmt_get_result($stmt_peers);
    mysqli_stmt_close($stmt_peers);
} else {
    // Fallback на обычный запрос
    $query_peers = @mysqli_query($db, 'SELECT ' . $fields . ' FROM peers WHERE torrent = ' . $Tid . $gp_eq . $wantseeds . ' ORDER BY last_action DESC LIMIT ' . $numwant);
}

if ($compact !== 1) {
    while ($result_peers = mysqli_fetch_assoc($query_peers)) {
        if (($result_peers['userid'] ?? null) === $Result['userid']) {
            $self = $result_peers;
            continue;
        }
        $resp .= 'd2:ip' . strlen($result_peers['ip']) . ':' . $result_peers['ip'] . '4:porti' . $result_peers['port'] . 'ee';
    }
    $resp .= 'ee';
} else {
    while ($result_peers = mysqli_fetch_assoc($query_peers)) {
        $peer_ip = explode('.', $result_peers['ip']);
        $peer_ip = pack('C*', (int)$peer_ip[0], (int)$peer_ip[1], (int)$peer_ip[2], (int)$peer_ip[3]);
        $peer_port = pack('n*', (int)$result_peers['port']);
        $time = intval(time() % 7680 / 60);
        if ($left === 0) {
            $time += 128;
        }
        $peer[] = pack('C', $time) . $peer_ip . $peer_port;
        ++$peer_num;
    }

    $o = '';
    foreach ($peer as $p) {
        $o .= substr($p, 1, 6);
    }
    $resp .= strlen($o) . ':' . $o . 'e';
    unset($peer);
}


if (!isset($self)) {
    $stmt_self = mysqli_prepare($db, 'SELECT ' . $fields . ' FROM peers WHERE torrent = ? AND userid = ? LIMIT 1');
    if ($stmt_self) {
        mysqli_stmt_bind_param($stmt_self, "ii", $Tid, $Result['userid']);
        mysqli_stmt_execute($stmt_self);
        $result_self = mysqli_stmt_get_result($stmt_self);
        if (mysqli_num_rows($result_self)) {
            $self = mysqli_fetch_assoc($result_self);
        }
        mysqli_stmt_close($stmt_self);
    }
}

if (isset($self) && ($announce_wait ?? 0) > 0 && $_SERVER['REQUEST_TIME'] - $announce_wait < ($self['prevts'] ?? 0)) {
    stop(($l['antispam'] ?? 'Please wait') . ' ' . $announce_wait);
}

@require_once TSDIR . '/cache/freeleech.php';
$TIMENOW = date('Y-m-d H:i:s');

if (isset($__F_START, $__F_END, $__FLSTYPE) && $__F_START < $TIMENOW && $TIMENOW < $__F_END) {
    switch ($__FLSTYPE) {
        case 'freeleech':
            $Result['free'] = 'yes';
            $Result['canfreeleech'] = 'yes';
            break;
        case 'silverleech':
            $Result['silver'] = 'yes';
            break;
        case 'doubleupload':
            $Result['doubleupload'] = 'yes';
            break;
    }
}

if (($bdayreward ?? '') === 'yes' && !empty($bdayrewardtype) && !empty($Result['birthday'])) {
    $curuserbday = explode('-', $Result['birthday']);
    if (date('j-n') === $curuserbday[1] . '-' . $curuserbday[2]) {
        switch ($bdayrewardtype) {
            case 'freeleech':
                $Result['free'] = 'yes';
                $Result['canfreeleech'] = 'yes';
                break;
            case 'silverleech':
                $Result['silver'] = 'yes';
                break;
            case 'doubleupload':
                $Result['doubleupload'] = 'yes';
                break;
        }
    }
}

if (isset($self)) {
    $realupload = max(0, $uploaded - ($self['uploaded'] ?? 0));
    $upthis = (($Result['doubleupload'] ?? '') === 'yes' ? $realupload * 2 : $realupload);
    $downthis = max(0, $downloaded - ($self['downloaded'] ?? 0));
    
    $announce_time = max(1, ($self['announcetime'] ?? 1));
    $upspeed = $realupload > 0 ? $realupload / $announce_time : 0;
    $downspeed = $downthis > 0 ? $downthis / $announce_time : 0;
    
    $safe_announcetime = min($self['announcetime'] ?? 0, 31536000);
    
    //$announcetime_sql = (($self['seeder'] ?? 'no') === 'yes'
        //? "seedtime = seedtime + " . $safe_announcetime
        //: "leechtime = leechtime + " . $safe_announcetime);
		
	//$announcetime_sql = ($self['seeder'] ?? 'no') === 'yes'
    //? "seedtime = seedtime + " . ($self['announcetime'] ?? 0)
    //: "leechtime = leechtime + " . ($self['announcetime'] ?? 0);
		
		
		

    if ($upthis > 0 || $downthis > 0) {
        if ($realupload > 536870912 && ($aggressivecheat ?? '') === 'yes') {
            send_action('There was no Leecher on this torrent however this user uploaded ' . $realupload . ' bytes, which might be a cheat attempt with a cheat software such as Ratio Maker, Ratio Faker etc..');
        }

        $dled = (($Result['silver'] ?? '') === 'yes' ? $downthis / 2 : $downthis);
        
        if ($upthis > 0) {
            $update_user[] = 'uploaded = uploaded + ' . $upthis;
        }

        if ($dled > 0 && ($Result['free'] ?? '') !== 'yes' && ($Result['canfreeleech'] ?? '') !== 'yes') {
            $update_user[] = 'downloaded = downloaded + ' . $dled;
        }
    }

   
   
   
   
   




if (($max_rate ?? 0) < $upspeed) 
{
    $added = TIMENOW;
    $uid = (int)$Result['userid'];
    $transfer_rate = (int)$upspeed;
    $beforeup = (int)$Result['uploaded'];
    $upthis = (int)$realupload;
    $timediff = (int)($self['announcetime'] ?? 0);
    $ip_addr = (string)$ip;
    $torrentid = (int)$Tid;
    $agent = (string)$agent;

    $stmt_cheat = mysqli_prepare($db, '
        INSERT INTO cheat_attempts 
        (added, uid, agent, transfer_rate, beforeup, upthis, timediff, ip, torrentid) 
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    if ($stmt_cheat) 
    {
        mysqli_stmt_bind_param($stmt_cheat, "iisiiiisi", 
            $added,
            $uid,
            $agent,
            $transfer_rate,
            $beforeup,
            $upthis,
            $timediff,
            $ip_addr,
            $torrentid
        );
        mysqli_stmt_execute($stmt_cheat);
        mysqli_stmt_close($stmt_cheat);
    }
}



   
   
   
   
   
   
}










if ($event === 'stopped' && isset($self)) 
{
    
    
    $stmt_delete = mysqli_prepare($db, 'DELETE FROM peers WHERE torrent = ? AND userid = ?');
    if ($stmt_delete) 
	{
        mysqli_stmt_bind_param($stmt_delete, "ii", $Tid, $Result['userid']);
        mysqli_stmt_execute($stmt_delete);
        $affected_rows = mysqli_stmt_affected_rows($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        
        if ($affected_rows > 0) 
		{
           
            if (($self['seeder'] ?? '') === 'yes') 
			{
               
                $stmt_update = mysqli_prepare($db, 
                    'UPDATE torrents SET seeders = GREATEST(seeders - 1, 0) WHERE id = ? AND seeders > 0'
                );
            } 
			else 
			{
                $stmt_update = mysqli_prepare($db, 
                    'UPDATE torrents SET leechers = GREATEST(leechers - 1, 0) WHERE id = ? AND leechers > 0'
                );
            }
            
            if ($stmt_update) 
			{
                mysqli_stmt_bind_param($stmt_update, "i", $Tid);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            }
        }
    }

    
    if (($snatchmod ?? '') === 'yes') 
	{
        $stmt_snatch = mysqli_prepare($db, 'UPDATE snatched SET seeder = \'no\' WHERE torrentid = ? AND userid = ?');
        if ($stmt_snatch) 
		{
            mysqli_stmt_bind_param($stmt_snatch, "ii", $Tid, $Result['userid']);
            mysqli_stmt_execute($stmt_snatch);
            mysqli_stmt_close($stmt_snatch);
        }
    }
}







 



else 
{
  
        $torrent_id = (int)$Tid;
        $user_id = (int)$Result['userid'];
        
        if ($event === 'completed') 
		{
           
            if (($snatchmod ?? '') === 'yes') 
			{
                $update_snatched_fixed = [
                    'finished'    => 'yes',
                    'completedat' => TIMENOW
                ];
            }

            $update_torrent[] = 'times_completed = times_completed + 1';
        }

        
        if (isset($self)) 
		{
            $connectable = (isset($self['connectable']) && $self['connectable'] === 'yes') ? 'yes' : checkconnect($ip, $port);

            if (($snatchmod ?? '') === 'yes') 
			{
                
                $update_snatched_fixed = array_merge($update_snatched_fixed ?? [], [
                    'seeder'      => $seeder,
                    'connectable' => $connectable,
                    'last_action' => TIMENOW,
                    'port'        => $port,
                    'agent'       => $agent,
                    'ip'          => $ip,
                    'uploaded'    => $realupload,
                    'downloaded'  => $downthis,
                    'to_go'       => $left
                ]);
				
				
				
                if ($upspeed > 0) 
				{
                   $update_snatched_fixed['upspeed'] = $upspeed;
                }
                if ($downspeed > 0) 
				{
                   $update_snatched_fixed['downspeed'] = $downspeed;
                }
				

                $fields2 = [];
                $types_snatched = '';
                $params_snatched = [];

                
                $announce_interval = (int)$announce_interval;
                if (isset($self['announcetime']) && $self['announcetime'] > 0 && $self['announcetime'] < 3600) {
                    $announce_interval = $self['announcetime'];
                } 

                if (($self['seeder'] ?? 'no') === 'yes') {
                    $fields2[] = "seedtime = seedtime + ?";
                } else {
                    $fields2[] = "leechtime = leechtime + ?";
                }
                $types_snatched .= 'i';
                $params_snatched[] = $announce_interval;

                
                foreach ($update_snatched_fixed as $key => $value) 
				{
                  $sum_fields = ['uploaded', 'downloaded', 'to_go'];
                  $int_fields = ['completedat', 'last_action']; 
                  $bigint_fields = ['upspeed', 'downspeed'];

                  if (in_array($key, $sum_fields, true)) 
				  {
                     $fields2[] = "$key = $key + ?";
                     $types_snatched .= 'i';
                  }  
	              elseif (in_array($key, $int_fields, true)) 
				  {
                     $fields2[] = "$key = ?";
                     $types_snatched .= 'i';
                  } 
				  elseif (in_array($key, $bigint_fields, true)) 
				  {
                     $fields2[] = "$key = ?";
                     $types_snatched .= 's';
                  } 
				  else 
				  {
                     $fields2[] = "$key = ?";
                     $types_snatched .= 's';
                  }

                  $params_snatched[] = $value;
				  
                }
				
				
				
				
				
				
				
				
				

                // WHERE
                $types_snatched .= 'ii';
                $params_snatched[] = $torrent_id;
                $params_snatched[] = $user_id;
            }

            
			
            $prev_action = $self['ts'] ?? TIMENOW;
            $was_seeder = $self['seeder'] ?? null;
            $became_seeder = ($seeder === 'yes' && $was_seeder !== $seeder);

            $query = 'UPDATE peers SET uploaded = ?, downloaded = ?, to_go = ?, last_action = ?, prev_action = ?, seeder = ?';
            $params_peers = [$uploaded, $downloaded, $left, TIMENOW, $prev_action, $seeder];
            $types_peers = 'iiiiss';

            if ($became_seeder) {
                $query .= ', finishedat = ?';
                $params_peers[] = $_SERVER['REQUEST_TIME'];
                $types_peers .= 'i';
            }

            $query .= ' WHERE torrent = ? AND userid = ?';
            $params_peers[] = $torrent_id;
            $params_peers[] = $user_id;
            $types_peers .= 'ii';

            $stmt_update = mysqli_prepare($db, $query);
            if (!$stmt_update) {
                throw new Exception('Ошибка подготовки запроса peers: ' . mysqli_error($db));
            }

            mysqli_stmt_bind_param($stmt_update, $types_peers, ...$params_peers);

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception('Ошибка выполнения запроса peers: ' . mysqli_stmt_error($stmt_update));
            }

            $affected = mysqli_stmt_affected_rows($stmt_update);
            mysqli_stmt_close($stmt_update);

           
            if ($affected > 0 && $was_seeder !== $seeder) 
			{
                if ($seeder === 'yes') 
				{
                    $update_torrent[] = 'seeders = seeders + 1';
                    $update_torrent[] = 'leechers = GREATEST(leechers - 1, 0)';
                } 
				else 
				{
                    $update_torrent[] = 'leechers = leechers + 1';
                    $update_torrent[] = 'seeders = GREATEST(seeders - 1, 0)';
                }
            }

        
        } else {
            
		   $port = (int)$port;

           $banned_ports = [21, 22, 411, 412, 413, 6881, 6882, 6883, 6884, 6885, 6886, 6887, 6889, 1214, 6346, 6347, 4662, 6699, 65535];

           if (in_array($port, $banned_ports, true)) 
		   { 
              stop($l['invalidport'] ?? 'Invalid port');
           }

            
            $connectable = checkconnect($ip, $port);
            if ($connectable === 'no' && ($nc ?? '') === 'yes') {
                throw new Exception($l['conerror'] ?? 'Connection error');
            }

            // Добавление в snatched если включен snatchmod (InnoDB)
            if (($snatchmod ?? '') === 'yes') 
			{
                
				
				$startdat = TIMENOW;
                $last_action = TIMENOW;
				
				$stmt_check = mysqli_prepare($db, 'SELECT 1 FROM snatched WHERE torrentid = ? AND userid = ?');
                if ($stmt_check) {
                    mysqli_stmt_bind_param($stmt_check, "ii", $torrent_id, $user_id);
                    mysqli_stmt_execute($stmt_check);
                    $result_check = mysqli_stmt_get_result($stmt_check);
                    if (mysqli_num_rows($result_check) === 0) {
                        $stmt_insert = mysqli_prepare($db, 'INSERT INTO snatched (torrentid, userid, port, startdat, last_action, agent, ip) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        if ($stmt_insert) {
                            mysqli_stmt_bind_param(
                                $stmt_insert, 
                                "iiiiiss", 
                                $torrent_id, 
                                $user_id,
                                $port, 
                                $startdat,
                                $last_action,
                                $agent, 
                                $ip
                            );
                            if (!mysqli_stmt_execute($stmt_insert)) {
                                throw new Exception('Ошибка вставки snatched: ' . mysqli_stmt_error($stmt_insert));
                            }
                            mysqli_stmt_close($stmt_insert);
                        }
                    }
                    mysqli_stmt_close($stmt_check);
                }
            }

            // Добавление нового пира (InnoDB)
            $started = TIMENOW;
            $last_action = TIMENOW;
            $port_int = (int)$port;
            $uploaded_int = (int)$uploaded;
            $downloaded_int = (int)$downloaded;
            $to_go = (int)$left;

            $stmt_insert_peer = mysqli_prepare($db, '
                INSERT INTO peers (connectable, torrent, peer_id, ip, port, uploaded, downloaded, to_go, started, last_action, seeder, userid, agent, uploadoffset, downloadoffset, passkey) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            if ($stmt_insert_peer) {
                mysqli_stmt_bind_param(
                    $stmt_insert_peer,
                    "sisssiiisisissis",
                    $connectable,
                    $torrent_id,
                    $peer_id,
                    $ip,
                    $port_int,
                    $uploaded_int,
                    $downloaded_int,
                    $to_go,
                    $started,
                    $last_action,
                    $seeder,
                    $user_id,
                    $agent,
                    $uploaded_int,
                    $downloaded_int,
                    $passkey
                );

                if (!mysqli_stmt_execute($stmt_insert_peer)) {
                    throw new Exception('Ошибка вставки peer: ' . mysqli_stmt_error($stmt_insert_peer));
                }
                mysqli_stmt_close($stmt_insert_peer);

                // Обновление счетчиков торрента (MyISAM - вне транзакции)
                $update_torrent[] = ($seeder === 'yes' ? 'seeders = seeders + 1' : 'leechers = leechers + 1');
            }
        }

      
        if (!empty($fields2)) 
		{
            $stmt_snatched = mysqli_prepare($db, 
                'UPDATE snatched SET ' . implode(', ', $fields2) . ' WHERE torrentid = ? AND userid = ?'
            );

            if (!$stmt_snatched) {
                throw new Exception('Ошибка подготовки запроса snatched: ' . mysqli_error($db));
            }

            mysqli_stmt_bind_param($stmt_snatched, $types_snatched, ...$params_snatched);

            if (!mysqli_stmt_execute($stmt_snatched)) {
                throw new Exception('Ошибка выполнения запроса snatched: ' . mysqli_stmt_error($stmt_snatched));
            }

            mysqli_stmt_close($stmt_snatched);
        }

        


    // Обновления для сидеров (torrents - MyISAM)
    if ($seeder === 'yes') {
        if (($Result['banned'] ?? '') !== 'yes' && ($Result['visible'] ?? '') === 'no') {
            $update_torrent[] = 'visible = \'yes\'';
        }
        $update_torrent[] = 'last_action = ' . TIMENOW;
        $update_torrent[] = 'mtime = ' . $_SERVER['REQUEST_TIME'];
    }

    // Обновление торрента (MyISAM)
    if (!empty($update_torrent)) {
        $stmt_torrent = mysqli_prepare($db, 'UPDATE torrents SET ' . implode(',', $update_torrent) . ' WHERE id = ?');
        if ($stmt_torrent) {
            mysqli_stmt_bind_param($stmt_torrent, "i", $torrent_id);
            mysqli_stmt_execute($stmt_torrent);
            mysqli_stmt_close($stmt_torrent);
        }
    }

    // Обновление пользователя (MyISAM)
    if (!empty($update_user)) {
        $stmt_user = mysqli_prepare($db, 'UPDATE users SET ' . implode(',', $update_user) . ' WHERE id = ?');
        if ($stmt_user) {
            mysqli_stmt_bind_param($stmt_user, "i", $user_id);
            mysqli_stmt_execute($stmt_user);
            mysqli_stmt_close($stmt_user);
        }
    }
}

// ----------------------------
// ВЫВОД РЕЗУЛЬТАТА
// ----------------------------
header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-type: text/html; charset=' . ($charset ?? 'utf-8'));

if ($compact !== 1 && ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') === 'gzip' && ($gzipcompress ?? '') === 'yes') {
    header('Content-Encoding: gzip');
    echo gzencode($resp, 9, FORCE_GZIP);
} else {
    if ($compact) {
        header('Content-Type: text/plain');
    }
    echo $resp;
}

@mysqli_close($db);
exit();

?>