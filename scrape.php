<?php
declare(strict_types=1);

ob_start();

define('IN_ANNOUNCE', true);
define('TIMENOW', time());
define('TSDIR', dirname(__FILE__));

// ── Функция остановки ────────────────────────────────────

function stop(string $msg): never
{
    ob_end_clean();
    header('Content-Type: text/plain');
    exit('d14:failure reason' . strlen($msg) . ':' . $msg . 'e');
}

// ── Конфигурация ─────────────────────────────────────────

require TSDIR . '/include/config_announce.php';

// ── Подключение к БД ─────────────────────────────────────

$db = @mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
if (!$db) stop('Database error');
mysqli_set_charset($db, 'utf8mb4');

// ── Проверка passkey ─────────────────────────────────────

$passkey = $_GET['passkey'] ?? '';

if (strlen($passkey) !== 32) {
    stop('Invalid passkey');
}

$stmt = mysqli_prepare($db, 'SELECT id, enabled FROM users WHERE passkey = ? LIMIT 1');
if (!$stmt) stop('Database error');

mysqli_stmt_bind_param($stmt, 's', $passkey);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user || $user['enabled'] !== 'yes') {
    stop('Invalid passkey or account disabled');
}

// ── Получение info_hash ──────────────────────────────────

// Поддержка одного или нескольких info_hash
$hashes = [];

if (isset($_GET['info_hash'])) {
    $hashes[] = $_GET['info_hash'];
}

// Некоторые клиенты передают несколько хешей
if (isset($_GET['info_hash']) && is_array($_GET['info_hash'])) {
    $hashes = $_GET['info_hash'];
}

// Проверяем raw query string для множественных хешей
$raw = $_SERVER['QUERY_STRING'] ?? '';
preg_match_all('/info_hash=([^&]+)/', $raw, $matches);
if (!empty($matches[1])) {
    $hashes = array_map('urldecode', $matches[1]);
}

if (empty($hashes)) {
    stop('No info_hash provided');
}

// ── Запрос к БД ──────────────────────────────────────────

$resp = 'd5:filesd';

foreach ($hashes as $hash) {
    if (strlen($hash) !== 20) continue;

    $hash_hex = bin2hex($hash);

    $stmt = mysqli_prepare($db,
        'SELECT info_hash, seeders, leechers, times_completed
         FROM torrents
         WHERE (info_hash = ? OR info_hash = ?) AND banned != \'yes\'
         LIMIT 1'
    );
    if (!$stmt) continue;

    mysqli_stmt_bind_param($stmt, 'ss', $hash_hex, $hash);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) continue;

    // Бинарный info_hash для ответа
    $bin_hash = strlen($row['info_hash']) === 40
        ? pack('H*', $row['info_hash'])
        : $row['info_hash'];

    $resp .= '20:' . $bin_hash
           . 'd8:completei'    . (int)$row['seeders']          . 'e'
           . '10:downloadedi'  . (int)$row['times_completed']  . 'e'
           . '10:incompletei'  . (int)$row['leechers']         . 'ee';
}

$resp .= 'ee';

// ── Ответ клиенту ────────────────────────────────────────

ob_end_clean();

header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: text/plain');

$acceptGzip = str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip');
if ($acceptGzip && ($gzipcompress ?? '') === 'yes') {
    header('Content-Encoding: gzip');
    echo gzencode($resp, 9, FORCE_GZIP);
} else {
    echo $resp;
}

@mysqli_close($db);
