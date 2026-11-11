<?php
/**
 * Fill torrents.v2_hash for torrents uploaded before v2 support (run once after sql/v2_hash.sql).
 * Command line only:   php sql/fill_v2_hashes.php
 * Reads every stored .torrent; v1-only torrents are left NULL. Safe to run again.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line: php sql/fill_v2_hashes.php');
}

define('IN_ANNOUNCE', true);
define('TSDIR', dirname(__DIR__));

require TSDIR . '/vendor/autoload.php';
require TSDIR . '/include/config_announce.php';

use Arokettu\Torrent\TorrentFile;

$db = mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
mysqli_set_charset($db, 'utf8mb4');

$dir = 'torrents';
$res = mysqli_query($db, "SELECT value FROM settings WHERE name = 'torrent_dir' LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res)) && trim((string)$row['value']) !== '') {
    $dir = trim((string)$row['value'], "/\\ ");
}
$base = TSDIR . '/' . $dir . '/';
echo "Torrent folder: {$base}\n";

$upd = mysqli_prepare($db, 'UPDATE torrents SET v2_hash = ? WHERE id = ?');
$res = mysqli_query($db, 'SELECT id FROM torrents WHERE v2_hash IS NULL ORDER BY id');

$filled = $v1only = $missing = $errors = 0;
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $id   = (int)$row['id'];
    $path = $base . $id . '.torrent';
    if (!is_file($path)) {
        $missing++;
        continue;
    }
    try {
        $v2 = TorrentFile::load($path)->v2()?->getInfoHash();   // null for v1-only torrents
    } catch (Throwable) {
        $errors++;
        continue;
    }
    if (!$v2) {
        $v1only++;
        continue;
    }
    $short = substr(strtolower($v2), 0, 40);
    mysqli_stmt_bind_param($upd, 'si', $short, $id);
    mysqli_stmt_execute($upd);
    $filled++;
    echo "  #{$id}: {$short}\n";
}

echo "Done: {$filled} hybrid/v2 torrent(s) filled, {$v1only} v1-only, {$missing} file(s) missing, {$errors} error(s).\n";
