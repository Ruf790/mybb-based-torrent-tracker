<?php
declare(strict_types=1);

// Буферизуем вывод на случай случайного вывода
ob_start();

// ── Константы ─────────────────────────────────────────────

define('IN_ANNOUNCE', true);
define('TIMENOW',     time());
define('TSDIR',       dirname(__FILE__));

const BANNED_PORTS = [
    21, 22, 411, 412, 413,
    1214, 4662, 6346, 6347, 6699,
    6881, 6882, 6883, 6884, 6885,
    6886, 6887, 6889, 65535,
];

// ── Функции ───────────────────────────────────────────────

function stop(string $msg): never
{
    ob_end_clean();
    header('Content-Type: text/plain');
    header('Pragma: no-cache');
    exit('d14:failure reason' . strlen($msg) . ':' . $msg . 'e');
}

function checkconnect(string $host, int $port): string
{
    global $checkconnectable;
    if (!$checkconnectable || $checkconnectable === 'no') return 'yes';
    $fp = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($fp) { fclose($fp); return 'yes'; }
    return 'no';
}

function apply_freeleech_mode(string $type, array &$Result): void
{
    match($type) {
        'freeleech'    => ($Result['free'] = $Result['canfreeleech'] = 'yes'),
        'silverleech'  => ($Result['silver']      = 'yes'),
        'doubleupload' => ($Result['doubleupload'] = 'yes'),
        default        => null,
    };
}

// ── Конфигурация ──────────────────────────────────────────

require TSDIR . '/include/config_announce.php';

$langFile = TSDIR . '/include/languages/english/announce.lang.php';
if (file_exists($langFile)) require $langFile;
if (!isset($l) || !is_array($l)) {
    $l = [
        'error'         => 'Invalid request',
        'registerfirst' => 'Please register at ',
        'cerror'        => 'Database error',
        'sqlerror'      => 'SQL error',
        'tuerror'       => 'Torrent or user not found',
        'qerror1'       => 'Download forbidden',
        'invalidip'     => 'Invalid IP',
        'invalidagent'  => 'Invalid client',
        'bannedclient'  => 'Client not allowed',
        'invalidport'   => 'Invalid port',
        'conerror'      => 'Not connectable',
        'antispam'      => 'Please wait before re-announcing.',
    ];
}

// ── Входные параметры ─────────────────────────────────────

$compact  = isset($_GET['compact']) ? (int)$_GET['compact'] : 0;
$peer_id  = $_GET['peer_id'] ?? '';
$port     = isset($_GET['port'])       ? (int)$_GET['port']       : 0;
$event    = $_GET['event'] ?? '';
$downloaded = isset($_GET['downloaded']) ? (int)$_GET['downloaded'] : 0;
$uploaded   = isset($_GET['uploaded'])   ? (int)$_GET['uploaded']   : 0;
$left       = isset($_GET['left'])       ? (int)$_GET['left']       : 0;
$numwant    = min((int)($_GET['numwant'] ?? $_GET['num_want'] ?? 50), 50);

if (isset($_GET['passkey']) && str_contains($_GET['passkey'], '?')) {
    [$_GET['passkey'], $rest] = explode('?', $_GET['passkey'], 2);
    $parts = explode('=', $rest, 2);
    if (count($parts) === 2) $_GET['info_hash'] = $parts[1];
}

$passkey    = $_GET['passkey']   ?? '';
$info_hash  = $_GET['info_hash'] ?? '';
$info_hash2 = bin2hex($info_hash);
$ip         = trim($_SERVER['REMOTE_ADDR'] ?? '');
$agent      = substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 128);
$seeder     = ($left === 0 ? 'yes' : 'no');

// ── Базовые проверки ──────────────────────────────────────

if (!(strlen($passkey) === 32 && strlen($info_hash) === 20 && strlen($peer_id) === 20
      && $port > 0 && $port < 65535)) {
    stop($l['error']);
}

if ($passkey === 'tssespecialtorrentv1byxamsep2007') {
    stop(($l['registerfirst']) . ($BASEURL ?? '') . '/signup.php');
}

// ── Подключение к БД (прямое mysqli — как в оригинале) ────

$db = @mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if (!$db) stop($l['cerror']);
mysqli_set_charset($db, 'utf8mb4');

// ── Загрузка торрента и пользователя ─────────────────────

$stmt = mysqli_prepare($db,
    'SELECT t.id AS tid, t.visible, t.banned, t.free, t.silver, t.doubleupload,
            t.seeders, t.leechers, t.times_completed,
            u.id AS userid, u.enabled, u.uploaded, u.downloaded,
            u.usergroup, u.birthday, u.regip,
            g.isbannedgroup, g.canfreeleech
     FROM torrents t
     INNER JOIN users      u ON u.passkey   = ?
     INNER JOIN usergroups g ON u.usergroup = g.gid
     WHERE (t.info_hash = ? OR t.info_hash = ?)
     LIMIT 1'
);
if (!$stmt) stop($l['sqlerror'] . ' TU1');

mysqli_stmt_bind_param($stmt, 'sss', $passkey, $info_hash2, $info_hash);
mysqli_stmt_execute($stmt);
$Result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$Result || !($Result['tid'] ?? null) || ($Result['enabled'] ?? '') !== 'yes') {
    stop($l['tuerror']);
}
if ((int)($Result['isbannedgroup'] ?? 0) === 1) stop($l['qerror1']);

$Tid    = (int)$Result['tid'];
$userId = (int)$Result['userid'];

// ── Проверки клиента ──────────────────────────────────────

if (($checkip ?? '') === 'yes' && ($Result['regip'] ?? '') !== $ip) {
    stop($l['invalidip']);
}

if (($bannedclientdetect ?? '') === 'yes') {
    $accept     = $_SERVER['HTTP_ACCEPT']          ?? '';
    $connection = $_SERVER['HTTP_CONNECTION']      ?? '';
    $encoding   = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $isBanned   =
        $accept === 'text/html, */*' ||
        ($connection === 'Close' && $encoding !== 'gzip, deflate') ||
        ($accept === 'text/html, */*' && $encoding === 'identity') ||
        !in_array(substr($peer_id, 0, 8), explode(',', $allowed_clients ?? ''), true);
    if ($isBanned) stop($l['bannedclient']);
}

// ── Загрузка пиров ────────────────────────────────────────

$fields    = 'seeder, peer_id, ip, port, uploaded, downloaded, userid, last_action,
              (' . TIMENOW . ' - last_action) AS announcetime,
              last_action AS ts, prev_action AS prevts, connectable';
$gp_eq     = (($nc ?? '') === 'yes' ? " AND connectable = 'yes'" : '');
$wantseeds = ($seeder === 'yes' ? " AND seeder = 'no'" : '');

$stmt_peers = mysqli_prepare($db,
    "SELECT {$fields} FROM peers WHERE torrent = ?{$gp_eq}{$wantseeds} ORDER BY last_action DESC LIMIT ?"
);

$self = null;

// ── Строим ответ ──────────────────────────────────────────

$resp = 'd8:completei'      . ($Result['seeders']         ?? 0)
      . 'e10:downloadedi'   . ($Result['times_completed'] ?? 0)
      . 'e10:incompletei'   . ($Result['leechers']        ?? 0)
      . 'e8:intervali'      . ($announce_interval         ?? 1800)
      . 'e12:min intervali' . ($announce_interval         ?? 1800)
      . (($privatetrackerpatch ?? '') === 'yes' && $compact !== 1 ? 'e7:privatei1' : '')
      . 'e5:peers'          . ($compact !== 1 ? 'l' : '');

$compactPeers = [];

if ($stmt_peers) {
    mysqli_stmt_bind_param($stmt_peers, 'ii', $Tid, $numwant);
    mysqli_stmt_execute($stmt_peers);
    $query_peers = mysqli_stmt_get_result($stmt_peers);
    mysqli_stmt_close($stmt_peers);

    while ($peer = mysqli_fetch_assoc($query_peers)) {
        if ((int)($peer['userid'] ?? 0) === $userId) {
            $self = $peer;
            continue;
        }
        if ($compact !== 1) {
            $resp .= 'd2:ip' . strlen($peer['ip']) . ':' . $peer['ip'] . '4:porti' . (int)$peer['port'] . 'ee';
        } else {
            $parts = explode('.', $peer['ip']);
            if (count($parts) === 4) {
                $compactPeers[] = pack('C4n', ...[...$parts, (int)$peer['port']]);
            }
        }
    }
}

if ($compact !== 1) {
    $resp .= 'e';
} else {
    $packed = implode('', $compactPeers);
    $resp  .= strlen($packed) . ':' . $packed . 'e';
}

// Себя не нашли — отдельный запрос
if ($self === null) {
    $stmt_self = mysqli_prepare($db,
        "SELECT {$fields} FROM peers WHERE torrent = ? AND userid = ? LIMIT 1"
    );
    if ($stmt_self) {
        mysqli_stmt_bind_param($stmt_self, 'ii', $Tid, $userId);
        mysqli_stmt_execute($stmt_self);
        $res_self = mysqli_stmt_get_result($stmt_self);
        if (mysqli_num_rows($res_self) > 0) $self = mysqli_fetch_assoc($res_self);
        mysqli_stmt_close($stmt_self);
    }
}

// ── Антиспам ─────────────────────────────────────────────

if ($self && (int)($announce_wait ?? 0) > 0 && $event !== 'started' && $event !== 'stopped') {
    $last_announce = (int)($self['ts'] ?? 0);
    $time_since    = (int)$_SERVER['REQUEST_TIME'] - $last_announce;
    if ($last_announce > 0 && $time_since < (int)$announce_wait) {
        $wait_more = (int)$announce_wait - $time_since;
        stop($l['antispam'] . ' ' . $wait_more . 's');
    }
}

// ── Freeleech / Birthday ──────────────────────────────────

@require_once TSDIR . '/cache/freeleech.php';

if (isset($__F_START, $__F_END, $__FLSTYPE)) {
    $flStart = is_numeric($__F_START) ? (int)$__F_START : strtotime((string)$__F_START);
    $flEnd   = is_numeric($__F_END)   ? (int)$__F_END   : strtotime((string)$__F_END);
    if (TIMENOW >= $flStart && TIMENOW <= $flEnd) {
        apply_freeleech_mode($__FLSTYPE, $Result);
    }
}

if (($bdayreward ?? '') === 'yes' && !empty($bdayrewardtype) && !empty($Result['birthday'])) {
    $bday = explode('-', (string)$Result['birthday']);
    if (count($bday) >= 3 && date('j-n') === ((int)$bday[2]) . '-' . ((int)$bday[1])) {
        apply_freeleech_mode($bdayrewardtype, $Result);
    }
}

// ── Дельты трафика ────────────────────────────────────────

$update_user    = [];
$update_torrent = [];
$realupload     = 0;
$downthis       = 0;

if ($self) {
    $realupload   = max(0, $uploaded   - (int)($self['uploaded']   ?? 0));
    $downthis     = max(0, $downloaded - (int)($self['downloaded'] ?? 0));
    $announce_time = max(1, (int)($self['announcetime'] ?? 1));
    $upthis       = ($Result['doubleupload'] ?? '') === 'yes' ? $realupload * 2 : $realupload;

    if ($upthis > 0) $update_user[] = 'uploaded = uploaded + ' . $upthis;

    $dled = ($Result['silver'] ?? '') === 'yes' ? (int)($downthis / 2) : $downthis;
    if ($dled > 0 && ($Result['free'] ?? '') !== 'yes' && ($Result['canfreeleech'] ?? '') !== 'yes') {
        $update_user[] = 'downloaded = downloaded + ' . $dled;
    }
}

// ── Античит проверки ─────────────────────────────────────
// Только математически точные — без ложных срабатываний

if ($self) {

    $announce_time_cheat = max(1, (int)($self['announcetime'] ?? 1));

    // Функция записи в cheat_attempts
    $write_cheat = function(string $reason, string $detail) use ($db, $Result, $Tid, $ip, $agent): void {
        $added      = TIMENOW;
        $uid        = (int)$Result['userid'];
        $torrentid  = (int)$Tid;
        $agent_str  = (string)$agent;
        $ip_str     = (string)$ip;
        $reason_str = substr($reason, 0, 64);
        $detail_str = substr($detail, 0, 512);
        $severity   = 'high';

        // Не дублировать одинаковую причину за последние 60 секунд
        $check = mysqli_prepare($db,
            'SELECT id FROM cheat_attempts WHERE uid=? AND torrentid=? AND reason=? AND added>? LIMIT 1'
        );
        if ($check) {
            $since = TIMENOW - 60;
            mysqli_stmt_bind_param($check, 'iisi', $uid, $torrentid, $reason_str, $since);
            mysqli_stmt_execute($check);
            $exists = mysqli_stmt_get_result($check)->num_rows > 0;
            mysqli_stmt_close($check);
            if ($exists) return;
        }

        $stmt = mysqli_prepare($db,
            'INSERT INTO cheat_attempts
             (added, uid, agent, ip, torrentid, reason, detail, severity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isssssss',
                $added, $uid, $agent_str, $ip_str,
                $torrentid, $reason_str, $detail_str, $severity
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    };

    // Проверка 1: completed + left > 0 — физически невозможно
    if ($event === 'completed' && $left > 0) {
        $write_cheat(
            'fake_completed_event',
            "Sent 'completed' but left={$left} bytes remaining"
        );
    }

    // Проверка 2: completed без реального скачивания (< 90% размера торрента)
    // Только если знаем размер торрента
    if ($event === 'completed' && !empty($Result['size']) && (int)$Result['size'] > 0) {
        $torrentSize = (int)$Result['size'];
        if ($downloaded < ($torrentSize * 0.90)) {
            $write_cheat(
                'completed_without_download',
                "downloaded={$downloaded} but torrent_size={$torrentSize} (< 90%)"
            );
        }
    }

    // Проверка 3: seeder=yes но left > 0 — сидирует файл которого нет
    if ($seeder === 'yes' && $left > 0) {
        $write_cheat(
            'fake_seeding',
            "seeder=yes but left={$left} bytes"
        );
    }

    // Проверка 4: peer_id изменился — возможная подмена клиента
    if (!empty($self['peer_id']) && $self['peer_id'] !== $peer_id) {
        $write_cheat(
            'peer_id_changed',
            "old=" . substr($self['peer_id'], 0, 12) . " new=" . substr($peer_id, 0, 12)
        );
    }

    // Проверка 5: подозрительный peer_id (тестовые/фейковые значения)
    if (preg_match('/^(AAAA{4}|0{8}|test|fake)/i', $peer_id)) {
        $write_cheat(
            'suspicious_peer_id',
            "peer_id=" . substr($peer_id, 0, 20)
        );
    }

    // Проверка 6: отрицательные значения (эксплойт клиента)
    // Проверяем сырые GET параметры — int cast скроет отрицательные
    $raw_up   = isset($_GET['uploaded'])   ? (float)$_GET['uploaded']   : 0;
    $raw_down = isset($_GET['downloaded']) ? (float)$_GET['downloaded'] : 0;
    $raw_left = isset($_GET['left'])       ? (float)$_GET['left']       : 0;
    if ($raw_up < 0 || $raw_down < 0 || $raw_left < 0) {
        $write_cheat(
            'negative_values',
            "uploaded={$raw_up} downloaded={$raw_down} left={$raw_left}"
        );
    }

    // Проверка 7: мгновенный stop после complete
    if ($event === 'stopped' && isset($self['finishedat']) && (int)$self['finishedat'] > 0) {
        $time_as_seed = TIMENOW - (int)$self['finishedat'];
        if ($time_as_seed > 0 && $time_as_seed < 5) {
            $write_cheat(
                'instant_stop_after_complete',
                "Stopped seeding after only {$time_as_seed}s"
            );
        }
    }

    // Проверка 8: невозможный ratio на новом торренте
    if ($downthis > 0 && $realupload > 0 && !empty($Result['added'])) {
        $torrent_age = TIMENOW - (int)strtotime((string)$Result['added']);
        $ratio       = $realupload / $downthis;

        if ($torrent_age < 3600 && $ratio > 3) {
            $write_cheat(
                'impossible_ratio_new_torrent',
                "ratio={$ratio} on torrent {$torrent_age}s old"
            );
        }

        if ($ratio > 100) {
            $write_cheat(
                'extreme_ratio',
                "ratio={$ratio} (up={$realupload} down={$downthis})"
            );
        }
    }

    // Проверка 9: пустой User-Agent
    if (empty($agent) && ($bannedclientdetect ?? '') === 'yes') {
        $write_cheat('empty_user_agent', 'No User-Agent header');
        stop($l['invalidagent']);
    }

    // Проверка 10: seeder с left > 0
    if ($seeder === 'yes' && $left > 0) {
        $write_cheat(
            'seed_with_left',
            "left={$left} bytes (should be 0 for seeder)"
        );
    }

    // Проверка 11: fake_completed без данных
    if ($event === 'completed' && $downthis < 1024 && $left > 0) {
        $write_cheat(
            'fake_completed_no_data',
            "downloaded_delta={$downthis} left={$left}"
        );
    }

    // Проверка 12: один peer_id с разных IP
    $stmt_multi = mysqli_prepare($db,
        'SELECT COUNT(DISTINCT ip) FROM peers WHERE peer_id = ? AND userid != ? AND last_action > ?'
    );
    if ($stmt_multi) {
        $time_ago_multi = TIMENOW - 300;
        mysqli_stmt_bind_param($stmt_multi, 'sii', $peer_id, $userId, $time_ago_multi);
        mysqli_stmt_execute($stmt_multi);
        mysqli_stmt_bind_result($stmt_multi, $multi_ip_count);
        mysqli_stmt_fetch($stmt_multi);
        mysqli_stmt_close($stmt_multi);

        if ($multi_ip_count >= 2) {
            $write_cheat(
                'multi_ip_same_peer_id',
                "Same peer_id from {$multi_ip_count} different IPs"
            );
        }
    }

    // Проверка 13: читерские клиенты по User-Agent
    $cheat_agents = ['mRatio', 'Ratio Faker', 'RatioMaster', 'shuMod'];
    foreach ($cheat_agents as $bad_agent) {
        if (stripos($agent, $bad_agent) !== false) {
            $write_cheat('banned_cheat_client', "agent={$agent}");
            stop($l['bannedclient']);
        }
    }

    // Проверка 14: анонс слишком часто (< 10 сек)
    if ($event !== 'started' && $event !== 'stopped') {
        $time_since_last = TIMENOW - (int)($self['ts'] ?? 0);
        if ($time_since_last > 0 && $time_since_last < 10) {
            $write_cheat(
                'announce_spam',
                "Only {$time_since_last}s between announces"
            );
        }
    }
}

// ── Событие stopped ───────────────────────────────────────

if ($event === 'stopped' && $self) {

    $stmt = mysqli_prepare($db, 'DELETE FROM peers WHERE torrent = ? AND userid = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $Tid, $userId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected > 0) {
            $col   = ($self['seeder'] ?? '') === 'yes' ? 'seeders' : 'leechers';
            $stmt2 = mysqli_prepare($db,
                "UPDATE torrents SET {$col} = GREATEST({$col} - 1, 0) WHERE id = ? AND {$col} > 0"
            );
            if ($stmt2) {
                mysqli_stmt_bind_param($stmt2, 'i', $Tid);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);
            }
        }
    }

    if (($snatchmod ?? '') === 'yes') {
        $stmt = mysqli_prepare($db, "UPDATE snatched SET seeder = 'no' WHERE torrentid = ? AND userid = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $Tid, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

} else {

// ── Существующий или новый пир ────────────────────────────

    $torrent_id = $Tid;
    $user_id    = $userId;

    if ($event === 'completed') {
        $update_torrent[] = 'times_completed = times_completed + 1';
        if (($snatchmod ?? '') === 'yes') {
            $update_snatched_fixed = ['finished' => 'yes', 'completedat' => TIMENOW];
        }
    }

    if ($self) {

        // UPDATE peers
        $connectable = ($self['connectable'] === 'yes') ? 'yes' : checkconnect($ip, $port);
        $prev_action  = (int)($self['ts'] ?? TIMENOW);
        $was_seeder   = $self['seeder'] ?? null;
        $became_seeder = ($seeder === 'yes' && $was_seeder !== $seeder);

        $p_timenow = TIMENOW;
        $q      = 'UPDATE peers SET uploaded=?, downloaded=?, to_go=?, last_action=?, prev_action=?, seeder=?';
        $params = [$uploaded, $downloaded, $left, $p_timenow, $prev_action, $seeder];
        $types  = 'iiiiss';

        if ($became_seeder) { $q .= ', finishedat=?'; $params[] = (int)$_SERVER['REQUEST_TIME']; $types .= 'i'; }
        $q .= ' WHERE torrent=? AND userid=?';
        $params[] = $torrent_id; $params[] = $user_id; $types .= 'ii';

        $stmt = mysqli_prepare($db, $q);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0 && $was_seeder !== $seeder) {
                if ($seeder === 'yes') {
                    $update_torrent[] = 'seeders = seeders + 1';
                    $update_torrent[] = 'leechers = GREATEST(leechers - 1, 0)';
                } else {
                    $update_torrent[] = 'leechers = leechers + 1';
                    $update_torrent[] = 'seeders = GREATEST(seeders - 1, 0)';
                }
            }
        }

        // UPDATE snatched
        if (($snatchmod ?? '') === 'yes') {
            $interval = (int)($announce_interval ?? 1800);
            if (isset($self['announcetime']) && $self['announcetime'] > 0 && $self['announcetime'] < 3600) {
                $interval = (int)$self['announcetime'];
            }

            $update_snatched_fixed = array_merge($update_snatched_fixed ?? [], [
                'seeder'      => $seeder,
                'connectable' => $connectable,
                'last_action' => TIMENOW,
                'port'        => $port,
                'agent'       => $agent,
                'ip'          => $ip,
                'uploaded'    => $realupload,
                'downloaded'  => $downthis,
                'to_go'       => $left,
            ]);

            $fields2  = [];
            $types_s  = '';
            $params_s = [];

            $fields2[]  = ($self['seeder'] ?? 'no') === 'yes' ? 'seedtime = seedtime + ?' : 'leechtime = leechtime + ?';
            $types_s   .= 'i';
            $params_s[] = $interval;

            $sum_fields    = ['uploaded', 'downloaded', 'to_go'];
            $int_fields    = ['completedat', 'last_action', 'port'];

            foreach ($update_snatched_fixed as $key => $value) {
                if (in_array($key, $sum_fields, true)) {
                    $fields2[]  = "{$key} = {$key} + ?";
                    $types_s   .= 'i';
                } elseif (in_array($key, $int_fields, true)) {
                    $fields2[]  = "{$key} = ?";
                    $types_s   .= 'i';
                } else {
                    $fields2[]  = "{$key} = ?";
                    $types_s   .= 's';
                }
                $params_s[] = $value;
            }

            $types_s   .= 'ii';
            $params_s[] = $torrent_id;
            $params_s[] = $user_id;

            $stmt = mysqli_prepare($db,
                'UPDATE snatched SET ' . implode(', ', $fields2) . ' WHERE torrentid = ? AND userid = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types_s, ...$params_s);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

    } else {

        // Новый пир
        if (in_array($port, BANNED_PORTS, true)) stop($l['invalidport']);

        $connectable = checkconnect($ip, $port);
        if ($connectable === 'no' && ($nc ?? '') === 'yes') stop($l['conerror']);

        // INSERT snatched
        if (($snatchmod ?? '') === 'yes') {
            $stmt = mysqli_prepare($db, 'SELECT 1 FROM snatched WHERE torrentid = ? AND userid = ? LIMIT 1');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $torrent_id, $user_id);
                mysqli_stmt_execute($stmt);
                $res_check = mysqli_stmt_get_result($stmt);
                mysqli_stmt_close($stmt);

                if (mysqli_num_rows($res_check) === 0) {
                    $stmt2 = mysqli_prepare($db,
                        'INSERT INTO snatched (torrentid, userid, port, startdat, last_action, agent, ip)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    if ($stmt2) {
                         $p_snatch_now = TIMENOW;
                    mysqli_stmt_bind_param($stmt2, 'iiiiiss',
                        $torrent_id, $user_id, $port, $p_snatch_now, $p_snatch_now, $agent, $ip
                    );
                        mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);
                    }
                }
            }
        }

        // INSERT peers
        $stmt = mysqli_prepare($db,
            'INSERT INTO peers
             (connectable, torrent, peer_id, ip, port, uploaded, downloaded, to_go,
              started, last_action, seeder, userid, agent, uploadoffset, downloadoffset, passkey)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $p_now     = TIMENOW;
            $p_up_off  = $uploaded;
            $p_dl_off  = $downloaded;
            mysqli_stmt_bind_param($stmt, 'sisssiiiiisissss',
                $connectable, $torrent_id, $peer_id, $ip, $port,
                $uploaded, $downloaded, $left,
                $p_now, $p_now, $seeder, $user_id, $agent,
                $p_up_off, $p_dl_off, $passkey
            );
            if (mysqli_stmt_execute($stmt)) {
                $update_torrent[] = ($seeder === 'yes' ? 'seeders = seeders + 1' : 'leechers = leechers + 1');
            }
            mysqli_stmt_close($stmt);
        }
    }

    // UPDATE torrents
    if ($seeder === 'yes') {
        if (($Result['banned'] ?? '') !== 'yes' && ($Result['visible'] ?? '') === 'no') {
            $update_torrent[] = "visible = 'yes'";
        }
        $update_torrent[] = 'last_action = ' . TIMENOW;
        $update_torrent[] = 'mtime = '       . (int)$_SERVER['REQUEST_TIME'];
    }

    if (!empty($update_torrent)) {
        $stmt = mysqli_prepare($db, 'UPDATE torrents SET ' . implode(',', $update_torrent) . ' WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $torrent_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // UPDATE users
    if (!empty($update_user)) {
        $stmt = mysqli_prepare($db, 'UPDATE users SET ' . implode(',', $update_user) . ' WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

// ── Ответ клиенту ─────────────────────────────────────────

ob_end_clean();

header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
// Бинарный ответ — без charset, иначе PHP портит compact peers данные
header('Content-Type: text/plain');

$acceptGzip = ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '') === 'gzip';
if ($compact !== 1 && $acceptGzip && ($gzipcompress ?? '') === 'yes') {
    header('Content-Encoding: gzip');
    echo gzencode($resp, 9, FORCE_GZIP);
} else {
    echo $resp;
}

@mysqli_close($db);