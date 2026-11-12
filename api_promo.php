<?php
/**
 * Promotions of torrents for torrent clients (ArtCore Client shows them as badges).
 *
 * GET api_promo.php?passkey=<32 hex>&h=<v1 info-hash hex>,<…>   (up to 200 hashes)
 * → {"global": "freeleech" | null, "t": {"<hash>": ["free", "silver", "30", "2x"]}}
 *
 * Read-only and as light as announce.php: config_announce.php for the DB, no global.php.
 * Unknown passkey or disabled / banned account → 403. Hashes that aren't on the tracker are
 * simply absent from "t".
 */
declare(strict_types=1);

define('IN_ANNOUNCE', true);
define('TSDIR', __DIR__);
define('TIMENOW', time());

function promo_out(int $code, array $data): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

require TSDIR . '/include/config_announce.php';

$passkey = (string)($_GET['passkey'] ?? '');
if (strlen($passkey) !== 32 || !ctype_xdigit($passkey)) {
    promo_out(403, ['error' => 'passkey']);
}

$hashes = [];
foreach (explode(',', strtolower((string)($_GET['h'] ?? ''))) as $h) {
    $h = trim($h);
    if (strlen($h) === 40 && ctype_xdigit($h)) {
        $hashes[$h] = true;
    }
    if (count($hashes) >= 200) {
        break;
    }
}
$hashes = array_keys($hashes);

$db = @mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if (!$db) {
    promo_out(503, ['error' => 'db']);
}
mysqli_set_charset($db, 'utf8mb4');

$stmt = mysqli_prepare($db,
    "SELECT u.id FROM users u
     INNER JOIN usergroups g ON g.gid = u.usergroup
     WHERE u.passkey = ? AND u.enabled = 'yes' AND g.isbannedgroup = 0
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $passkey);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$user) {
    promo_out(403, ['error' => 'passkey']);
}

// site-wide event (same cache file announce.php reads)
$global = null;
@include TSDIR . '/cache/freeleech.php';
if (isset($__F_START, $__F_END, $__FLSTYPE)) {
    $flStart = is_numeric($__F_START) ? (int)$__F_START : (int)strtotime((string)$__F_START);
    $flEnd   = is_numeric($__F_END)   ? (int)$__F_END   : (int)strtotime((string)$__F_END);
    if (TIMENOW >= $flStart && TIMENOW <= $flEnd) {
        $global = (string)$__FLSTYPE;
    }
}

$out = [];
if ($hashes) {
    $in   = implode(',', array_fill(0, count($hashes), '?'));
    $stmt = mysqli_prepare($db,
        "SELECT info_hash, free, silver, thirtypercent, doubleupload FROM torrents WHERE info_hash IN ($in)"
    );
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($hashes)), ...$hashes);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $tags = [];
        if (($row['free'] ?? '') === 'yes')          $tags[] = 'free';
        if (($row['silver'] ?? '') === 'yes')        $tags[] = 'silver';
        if (($row['thirtypercent'] ?? '') === 'yes') $tags[] = '30';
        if (($row['doubleupload'] ?? '') === 'yes')  $tags[] = '2x';
        if ($tags) {
            $out[strtolower((string)$row['info_hash'])] = $tags;
        }
    }
    mysqli_stmt_close($stmt);
}

@mysqli_close($db);
promo_out(200, ['global' => $global, 't' => (object)$out]);
