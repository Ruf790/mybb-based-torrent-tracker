<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

require_once 'global.php';
define('DL_VERSION', '2.4.6');

$lang->load('download');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Завершает выполнение с ошибкой.
 * Для RSS — выводит текст, для остальных — страницу 403.
 */
function download_error(string $message = ''): never
{
    global $action_type;

    if ($action_type === 'rss') {
        exit($message);
    }

    print_no_permission(true);
    exit;
}


/**
 * Отправляет файл браузеру через стандартные HTTP-заголовки.
 */
function send_torrent_file(string $contents, string $filename): never
{
    $safe_name = basename($filename);

    // RFC 5987 — корректное имя файла с UTF-8 символами
    $encoded_name = rawurlencode($safe_name);

    header('Content-Type: application/x-bittorrent');
    header('Content-Disposition: attachment; filename="' . $safe_name . '"; filename*=UTF-8\'\'' . $encoded_name);
    header('Content-Length: ' . strlen($contents));
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Accept-Ranges: bytes');
    header('Connection: close');

    echo $contents;
    exit;
}

/**
 * Отправляет magnet-ссылку.
 */
function send_magnet_link(string $info_hash, string $name, iterable $announce_list): never
{
    $magnet = 'magnet:?xt=urn:btih:' . $info_hash . '&dn=' . urlencode($name);

    foreach ($announce_list as $tier) {
        foreach ($tier as $tracker) {
            $magnet .= '&tr=' . urlencode((string)$tracker);
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $magnet;
    exit;
}

// ---------------------------------------------------------------------------
// Тип запроса
// ---------------------------------------------------------------------------

$action_type = (string)($_GET['type'] ?? '');
$valid_types = ['', 'magnet', 'rss'];

if (!in_array($action_type, $valid_types, true)) {
    download_error();
}

if (in_array($action_type, ['magnet', 'rss'], true)) {
    define('SKIP_LOCATION_SAVE', true);
}

// ---------------------------------------------------------------------------
// Аутентификация для RSS (по passkey в URL)
// ---------------------------------------------------------------------------

if ($action_type === 'rss') {
    $secret_key = (string)($_GET['secret_key'] ?? '');

    if (strlen($secret_key) !== 32 || !ctype_alnum($secret_key)) {
        download_error();
    }

    $rss_id = (int)($_GET['id'] ?? 0);
    if (!is_valid_id($rss_id)) {
        download_error();
    }

    $res = $db->sql_query(
        'SELECT * FROM users WHERE passkey = ' . $db->sqlesc($secret_key) . ' LIMIT 1'
    );

    if ($db->num_rows($res) === 0) {
        download_error();
    }

    $rss_user = $db->fetch_array($res);

    require TSDIR . '/cache/usergroups.php';
    $rss_group = $usergroupscache[$rss_user['usergroup']] ?? [];

    // Проверяем что пользователь активен и не забанен
    if (
        ($rss_group['isbannedgroup'] ?? '') === '1'
        || $rss_user['enabled']  !== 'yes'
        || $rss_user['status']   !== 'confirmed'
    ) {
        download_error();
    }

    // Устанавливаем контекст пользователя для RSS-запроса
    $GLOBALS['CURUSER']    = $rss_user;
    $GLOBALS['usergroups'] = $rss_group;

    unset($rss_user, $rss_group, $usergroupscache);

} else {
    maxsysop();
}

// ---------------------------------------------------------------------------
// Системные настройки для отдачи файла
// ---------------------------------------------------------------------------

ini_set('zlib.output_compression', 'Off');
set_time_limit(0);

if (ini_get('output_handler') === 'ob_gzhandler' && ob_get_length() !== false) {
    ob_end_clean();
    header('Content-Encoding:');
}

// ---------------------------------------------------------------------------
// Валидация ID торрента
// ---------------------------------------------------------------------------

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!is_valid_id($id)) {
    download_error();
}

// ---------------------------------------------------------------------------
// Загрузка данных торрента
// ---------------------------------------------------------------------------

$res = $db->sql_query(
    'SELECT t.id, t.name, t.filename, t.ts_external, t.size, t.owner, t.free
     FROM torrents t
     LEFT JOIN categories c ON t.category = c.id
     WHERE t.id = ' . $id  // $id уже (int) — sqlesc не нужен
);
$row = $db->fetch_array($res);

// Проверяем существование торрента ДО обращения к полям $row
if (!$row) {
    download_error($lang->download['error1']);
}

$id       = (int)$row['id'];
$external = ($row['ts_external'] === 'yes');
$fn       = $torrent_dir . '/' . $id . '.torrent';

if (!is_file($fn)) {
    download_error($lang->download['error2']);
}

if (!is_readable($fn)) {
    download_error($lang->download['error3']);
}

// ---------------------------------------------------------------------------
// Проверка прав на скачивание
// ---------------------------------------------------------------------------

$perm_query = $db->simple_select('users', 'candownload', 'id = ' . (int)$CURUSER['id']);
$downperm = $db->fetch_array($perm_query);
if ((int)($downperm['candownload'] ?? 1) === 0) {
    download_error();
}

// ---------------------------------------------------------------------------
// Hit-and-Run проверка
// ---------------------------------------------------------------------------

$is_mod = is_mod($usergroups);
$ratio  = $CURUSER['downloaded'] > 0
    ? $CURUSER['uploaded'] / $CURUSER['downloaded']
    : PHP_INT_MAX; // нет скачиваний — ratio бесконечный, не ограничиваем

$xbt_active = 'no';

if (
    $ratio        <= $hitrun_ratio
    && $CURUSER['downloaded'] > 0
    && !$is_mod
    && $hitrun    === 'yes'
    && ($usergroups['isvipgroup'] ?? '') !== 'yes'
    && $row['owner'] != $CURUSER['id']
    && $row['free']  !== 'yes'
) {
    $already_finished = $db->num_rows($db->sql_query(
        'SELECT torrentid FROM snatched
         WHERE torrentid = ' . $id . '
           AND userid    = ' . (int)$CURUSER['id'] . '
           AND finished  = "yes"'
    ));

    if (!$already_finished) {
        $userlist_url = $BASEURL . '/' . ($xbt_active === 'yes' ? 'mysnatchlist' : 'userdetails') . '.php';

        stderr(
            sprintf(
                $lang->download['downloadwarning'],
                number_format($ratio, 2),
                mksize($ratio * 100),
                $hitrun_ratio,
                '<a href="' . $userlist_url . '">' . $userlist_url . '</a>'
            ),
            true
        );
        stdhead();
        exit;
    }
}

// ---------------------------------------------------------------------------
// Загрузка torrent-файла через библиотеку
// ---------------------------------------------------------------------------

require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;

$torrentFileObj = TorrentFile::load($fn);

// ---------------------------------------------------------------------------
// Magnet-ссылка для внешних торрентов
// ---------------------------------------------------------------------------

if ($external && $action_type === 'magnet') {
    send_magnet_link(
        $torrentFileObj->v1()->getInfoHash(),
        $torrentFileObj->getName(),
        $torrentFileObj->getAnnounceList() ?? []
    );
}

// ---------------------------------------------------------------------------
// Обновление счётчика загрузок
// ---------------------------------------------------------------------------

$db->update_query('torrents', ['hits' => 'hits+1'], "id='{$id}'", '1', true);

// ---------------------------------------------------------------------------
// Подстановка announce URL для приватных торрентов
// ---------------------------------------------------------------------------

if (!$external) {
    $torrentFileObj->setAnnounce(
        ts_seo($CURUSER['passkey'], $row['filename'], 'a')
    );
}

$torrent_contents = $torrentFileObj->storeToString();

// ---------------------------------------------------------------------------
// Отдача файла
// ---------------------------------------------------------------------------

if ($usezip !== 'yes' || $action_type === 'rss') {
    send_torrent_file($torrent_contents, $row['filename']);
}

// ---------------------------------------------------------------------------
// ZIP-упаковка (если включено в настройках)
// ---------------------------------------------------------------------------

require_once INC_PATH . '/class_zip.php';

$zip = new createZip();
$zip->addFile('This torrent was downloaded from ' . $BASEURL, 'readme.txt');
$zip->addFile($torrent_contents, $row['filename']);

$zip_filename = $row['filename'] . '.zip';
$zip_path     = 'cache/' . $zip_filename;

// Убеждаемся что директория существует
if (!is_dir('cache')) {
    mkdir('cache', 0755, true);
}

file_put_contents($zip_path, $zip->getZippedfile());
$zip->forceDownload($zip_path);

// Очищаем временный ZIP после отдачи
if (file_exists($zip_path)) {
    unlink($zip_path);
}