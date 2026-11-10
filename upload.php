<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define("SCRIPTNAME", "upload.php");
require_once 'global.php';

// Max .torrent size in MB — the same limit is passed to upload_torrent.js (maxTorrentMb)
const UPLOAD_MAX_TORRENT_MB = 5;

// Lang is needed already by the AJAX handlers below (they run before the page code)
$lang->load('upload');

/** Error text from upload.lang.php; {1}, {2}… (or %1$s after $lang->load()) are filled with $args. */
$ue = static function (string $key, string $fallback, string|int ...$args) use ($lang): string {
    $s = $lang->upload[$key] ?? $fallback;
    foreach ($args as $i => $v) {
        $n = $i + 1;
        $s = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$v, $s);
    }
    return $s;
};


if (empty($CURUSER['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(['success' => false, 'error' => $ue('err_not_logged_in', 'Not logged in')]);
        exit;
    }
    print_no_permission();
}



require(INC_PATH . '/functions_category.php');
require_once INC_PATH.'/datahandler.php';
require_once INC_PATH . '/functions_image_recode.php';

require_once INC_PATH . '/functions_notify.php';




require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;




require_once INC_PATH . '/editor.php';

$editor = insert_bbcode_editor($smilies, $BASEURL, 'description');















// ✅ Обработчик удаления одного скрина
if (isset($_POST['action']) && $_POST['action'] === 'delete_screenshot') 
{
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $ue('err_security', 'Invalid security token. Please refresh the page and try again.')]);
        exit;
    }

    if (empty($_POST['screenshot_id'])) 
    {
        echo json_encode(['success' => false, 'error' => $ue('err_invalid_id', 'Invalid ID')]);
        exit;
    }

    $screenshot_id = (int)$_POST['screenshot_id'];
    $row_q = $db->sql_query_prepared("SELECT * FROM `screenshots` WHERE id = ?", [$screenshot_id]);
    $row = $row_q ? $db->fetch_array($row_q) : null;

    if ($row) 
    {
        // Проверяем, что скриншот принадлежит торренту текущего юзера (или юзер — мод)
        $owner_q = $db->sql_query_prepared("SELECT owner FROM torrents WHERE id = ?", [(int)$row['torrent_id']]);
        $torrent_owner = $owner_q ? $db->fetch_array($owner_q) : null;

        $is_mod = is_mod($usergroups);

        if (!$torrent_owner || (!$is_mod && (int)$CURUSER['id'] !== (int)$torrent_owner['owner'])) {
            echo json_encode(['success' => false, 'error' => $ue('err_access_denied', 'Access denied')]);
            exit;
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/' . $row['filename'];
        $realPath = realpath($filePath);
        $basePath = realpath($_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/');
        if ($realPath && $basePath && strpos($realPath, $basePath) === 0 && file_exists($realPath)) {
            @unlink($realPath);
        }

        $db->sql_query_prepared("DELETE FROM screenshots WHERE id = ?", [$screenshot_id]);

        echo json_encode(['success' => true]);
        exit;
    } 
    else 
    {
        echo json_encode(['success' => false, 'error' => $ue('err_screenshot_not_found', 'Screenshot not found')]);
        exit;
    }
}


// ✅ Массовое удаление скринов — один запрос вместо N параллельных
if (isset($_POST['action']) && $_POST['action'] === 'delete_screenshots')
{
    header("Content-type: application/json; charset=utf-8");

    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $ue('err_security', 'Invalid security token. Please refresh the page and try again.')]);
        exit;
    }

    if (empty($_POST['screenshot_ids']) || !is_array($_POST['screenshot_ids'])) {
        echo json_encode(['success' => false, 'error' => $ue('err_no_ids', 'No screenshots selected')]);
        exit;
    }

    // Приводим к int, убираем мусор и дубликаты
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $_POST['screenshot_ids']),
        fn($id) => $id > 0
    )));

    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => $ue('err_no_ids', 'No screenshots selected')]);
        exit;
    }

    $is_mod = is_mod($usergroups);

    // Одним запросом берём всё, что относится к этим ID, вместе с владельцем торрента
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $res = $db->sql_query_prepared(
        "SELECT s.id, s.filename, s.torrent_id, t.owner
         FROM screenshots s
         LEFT JOIN torrents t ON t.id = s.torrent_id
         WHERE s.id IN ({$ph})",
        $ids
    );

    $rows = [];
    while ($res && ($row = $db->fetch_array($res))) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        echo json_encode(['success' => false, 'error' => $ue('err_no_screenshots', 'No screenshots found')]);
        exit;
    }

    // Фильтруем: оставляем только те, что принадлежат юзеру (или модератору)
    $allowedIds    = [];
    $allowedFiles  = [];
    $deniedCount   = 0;

    foreach ($rows as $row) {
        if (!$row['owner'] || (!$is_mod && (int)$CURUSER['id'] !== (int)$row['owner'])) {
            $deniedCount++;
            continue;
        }
        $allowedIds[]   = (int)$row['id'];
        $allowedFiles[] = (string)$row['filename'];
    }

    if (empty($allowedIds)) {
        echo json_encode(['success' => false, 'error' => $ue('err_access_denied', 'Access denied')]);
        exit;
    }

    // Удаляем файлы с диска (только те, что прошли проверку)
    $baseDir   = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/torrents/screens/';
    $baseReal  = realpath($baseDir);
    $deletedFiles = 0;
    foreach ($allowedFiles as $filename) {
        $filePath = $baseDir . $filename;
        $realPath = realpath($filePath);
        if ($realPath && $baseReal && strpos($realPath, $baseReal) === 0 && is_file($realPath)) {
            @unlink($realPath);
            $deletedFiles++;
        }
    }

    // Удаляем записи из БД — один запрос
    $ph2 = implode(',', array_fill(0, count($allowedIds), '?'));
    $db->sql_query_prepared("DELETE FROM screenshots WHERE id IN ({$ph2})", $allowedIds);

    echo json_encode([
        'success' => true,
        'deleted' => count($allowedIds),
        'denied'  => $deniedCount,
        'files'   => $deletedFiles,
    ]);
    exit;
}


// Переупорядочивание скриншотов
if (isset($_POST['action']) && $_POST['action'] === 'reorder_screenshots')
{
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $ue('err_security', 'Invalid security token. Please refresh the page and try again.')]);
        exit;
    }

    if (empty($_POST['order']) || !is_array($_POST['order'])) {
        echo json_encode(['success' => false, 'error' => $ue('err_no_order', 'No order provided')]);
        exit;
    }

    $order   = array_map('intval', $_POST['order']);
    $is_mod  = is_mod($usergroups);

    foreach ($order as $position => $screenshotId) {
        if ($screenshotId <= 0) continue;

        // Проверка владения для каждого screenshot_id
        $row_q = $db->sql_query_prepared("SELECT torrent_id FROM `screenshots` WHERE id = ?", [(int)$screenshotId]);
        $row = $row_q ? $db->fetch_array($row_q) : null;
        if (!$row) continue;

        $owner_q = $db->sql_query_prepared("SELECT owner FROM torrents WHERE id = ?", [(int)$row['torrent_id']]);
        $torrent_owner = $owner_q ? $db->fetch_array($owner_q) : null;

        if (!$torrent_owner || (!$is_mod && (int)$CURUSER['id'] !== (int)$torrent_owner['owner'])) {
            continue; // тихо пропускаем чужие скриншоты, не отдавая их ID в ответе
        }

        $db->sql_query_prepared("UPDATE screenshots SET sort_order = ? WHERE id = ?", [(int)$position, $screenshotId]);
    }

    echo json_encode(['success' => true]);
    exit;
}



// Проверка дубликата торрента по info_hash
if (isset($_GET['action']) && $_GET['action'] === 'check_torrent_hash')
{
    header("Content-type: application/json; charset=utf-8");

    $info_hash = trim($_GET['info_hash'] ?? '');

    if (empty($info_hash)) {
        echo json_encode(['exists' => false, 'error' => $ue('err_no_hash', 'No hash provided')]);
        exit;
    }

    $query   = $db->sql_query_prepared("SELECT id, name, added FROM torrents WHERE info_hash = ? LIMIT 1", [$info_hash]);
    $torrent = $query ? $db->fetch_array($query) : null;

    if ($torrent) {
        echo json_encode([
            'exists' => true,
            'id'     => (int)$torrent['id'],
            'name'   => htmlspecialchars_uni($torrent['name']),
            'link'   => get_torrent_link($torrent['id']),
            'added'  => my_datee('relative', $torrent['added']),
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}



// Ранняя проверка passkey сразу после выбора файла (до отправки всей формы)
if (isset($_GET['action']) && $_GET['action'] === 'check_passkey' && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    header("Content-type: application/json; charset=utf-8");

    if (empty(trim($CURUSER['passkey'] ?? ''))) {
        echo json_encode(['valid' => false, 'error' => $lang->upload['error_no_passkey'] ?? 'Your account does not have a passkey. Please contact staff.']);
        exit;
    }

    if (empty($_FILES['torrentFile']) || $_FILES['torrentFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['valid' => true]); // нет файла — нечего проверять, не блокируем
        exit;
    }

    try {
        $tmpObj       = \Arokettu\Torrent\TorrentFile::load($_FILES['torrentFile']['tmp_name']);
        $fileAnnounce = (string)($tmpObj->getAnnounce() ?? '');

        if ($fileAnnounce !== '') {
            $parsed = parse_url($fileAnnounce);
            parse_str($parsed['query'] ?? '', $qs);
            $announcePasskey = $qs['passkey'] ?? '';

            if ($announcePasskey !== '' && $announcePasskey !== $CURUSER['passkey']) {
                write_log(sprintf(
                    'Security: user %s attempted to upload a torrent file (%s) containing a passkey belonging to another account. Foreign passkey: %s',
                    '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]',
                    htmlspecialchars_uni($_FILES['torrentFile']['name'] ?? 'unknown'),
                    $announcePasskey
                ));

                echo json_encode(['valid' => false, 'error' => $lang->upload['error_wrong_passkey'] ?? 'This torrent contains a passkey that does not belong to your account.']);
                exit;
            }
        }

        echo json_encode(['valid' => true]);
    } catch (\Throwable $e) {
        // Невалидный/повреждённый файл — финальная отправка формы разберётся подробнее
        echo json_encode(['valid' => true]);
    }
    exit;
}



// Получение данных IMDb по URL
if (isset($_GET['action']) && $_GET['action'] === 'get_imdb_data' && $_SERVER['REQUEST_METHOD'] === 'GET')
{
    header("Content-type: application/json; charset=utf-8");

    $imdb_url = trim($_GET['imdb_url'] ?? '');

    if (empty($imdb_url)) {
        echo json_encode(['success' => false, 'error' => $ue('imdb_error_empty', 'Please enter an IMDb URL')]);
        exit;
    }

    // Валидация URL
    if (!preg_match('@^https?://www\.imdb\.com/title/tt\d+/@i', $imdb_url)) {
        echo json_encode(['success' => false, 'error' => $ue('imdb_error_invalid', 'Invalid IMDb URL — it must contain /title/ttXXXXXXX/')]);
        exit;
    }

    // Добавляем слеш если нет
    if (substr($imdb_url, -1) !== '/') {
        $imdb_url .= '/';
    }

    $t_link = $imdb_url;

    try {
        include_once(INC_PATH . '/IMDB.php');

        $imdbObj = new IMDB($t_link);
        $data    = $imdbObj->parse();

        $poster = $data['poster'] ?? '';
        if ($poster) {
            $poster = preg_replace('#\._V1_.*?\.(jpg|png|jpeg)$#i', '.$1', $poster);
        }

        echo json_encode([
            'success' => true,
            'poster'  => $poster,
            'title'   => $data['title']  ?? '',
            'year'    => $data['year']   ?? '',
            'plot'    => $data['plot']   ?? '',
            'rating'  => $data['rating'] ?? '',
            'genre'   => !empty($data['genres'])    ? implode(', ', $data['genres'])    : '',
            'country' => !empty($data['countries']) ? implode(', ', $data['countries']) : '',
        ]);
    } catch (Exception $e) {
        // English details go to the log; the user gets a translated message by the exception code
        error_log('upload.php IMDb: ' . $e->getMessage());
        $msg = match ($e->getCode()) {
            IMDB::ERR_INVALID_ID => $ue('imdb_error_invalid',   'Invalid IMDb URL — it must contain /title/ttXXXXXXX/'),
            IMDB::ERR_NOT_FOUND  => $ue('imdb_error_not_found', 'This title was not found in the movie database.'),
            default              => $ue('imdb_error_failed',    'Could not get data from IMDb'),
        };
        echo json_encode(['success' => false, 'error' => $msg]);
    }
    exit;
}





$lang->load('upload');


$is_mod = is_mod($usergroups);

$query = $db->sql_query_prepared("SELECT canupload FROM users WHERE id = ? LIMIT 1", [(int)$CURUSER['id']]);
$user  = $query ? $db->fetch_array($query) : null;
if ((int)($user['canupload'] ?? 1) === 0) {
    stderr($lang->upload['no_upload_permission'] ?? 'You do not have permission to upload.', '', 403, '403upload');
}








if (strtoupper($_SERVER["REQUEST_METHOD"]) == "POST")
{



header('Content-Type: application/json');

if (!verify_post_check($mybb->get_input('my_post_key'))) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => $ue('err_security', 'Invalid security token. Please refresh the page and try again.')]);
    exit;
}

// ✅ Инициализируем массив ошибок СРАЗУ, до всех блоков с $errors[] =
$errors = [];



 // ← ЧИТАЕМ NFO СРАЗУ пока tmp файл ещё существует
    $nfoContentRaw = null;
    if (!empty($_FILES['nfoFile']['tmp_name']) && $_FILES['nfoFile']['error'] === UPLOAD_ERR_OK) {
        $nfoContentRaw = file_get_contents($_FILES['nfoFile']['tmp_name']);
        if ($nfoContentRaw === false) {
            $nfoContentRaw = null;
        }
    }








// Configuration
$uploadDir = __DIR__ . '/uploads/';
//$imageDir = $uploadDir;
$screenshotDir = $torrent_dir . '/screens/';
$nfoDir = $uploadDir;
//$torrentDir = $uploadDir;

$torrentDir = $torrent_dir.'/';
$imageDir = $torrent_dir . '/images/';


foreach ([$uploadDir, $imageDir, $screenshotDir, $nfoDir, $torrentDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}



/**
 * Проверяет, указывает ли ссылка на постер, лежащий на нашем сервере
 * (при редактировании поле imageUrl заполнено текущим t_image).
 * Такой постер не скачиваем заново: сервер не всегда достучится сам
 * до себя по внешнему домену, а при неудачной проверке файл постера
 * перезаписывался и удалялся.
 *
 * Возвращает:
 *   'external' — внешняя ссылка, её нужно скачать;
 *   'ok'       — свой постер, файл на месте, ничего делать не нужно;
 *   'missing'  — свой постер, но файла нет (ссылку нужно очистить).
 */
function ags_own_poster_state(string $url, string $baseurl): string
{
    $url  = trim($url);
    $path = (string)parse_url($url, PHP_URL_PATH);

    $isOwn = str_starts_with($path, '/torrents/images/')
        && strcasecmp((string)parse_url($url, PHP_URL_HOST),
                      (string)parse_url($baseurl, PHP_URL_HOST)) === 0;

    if (!$isOwn) {
        return 'external';
    }

    // basename() — защита от ../ в пути
    $file = __DIR__ . '/torrents/images/' . basename($path);

    return is_file($file) ? 'ok' : 'missing';
}


function saveFile($file, $targetDir, $allowedTypes)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    //finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) 
	{
        return false;
    }

    // Случайное имя на диске - чтобы одновременная загрузка двух файлов
    // с одинаковым исходным именем не перезаписала друг друга. Оригинальное
    // имя (для отображения/БД) сохраняется вызывающим кодом отдельно.
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = $ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';
    $filename = bin2hex(random_bytes(16)) . $ext;
    $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) 
	{
        return $filename;
    }

    return false;
}





// Upload .torrent file and extract info_hash
$isEdit = isset($_POST['EditTorrent']) && !empty($_POST['EditTorrentID']);

$torrentFile = $_FILES['torrentFile'] ?? null;


$newFileUploaded = ($torrentFile && $torrentFile['error'] === UPLOAD_ERR_OK);


if (!$isEdit && (!$torrentFile || $torrentFile['error'] !== UPLOAD_ERR_OK)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $ue('torrent_file_required', 'A torrent file is required for a new upload.')]);
    exit;
}





// Save uploaded torrent file
$torrentFilename = null;
$info_hash = '';
$size = 0;
$numfiles = 0;





if (!$isEdit || ($torrentFile && $torrentFile['error'] === UPLOAD_ERR_OK)) 
{
    // Проверка размера .torrent — 5MB это с большим запасом,
    // реальные торренты редко превышают 500KB.
    if ($torrentFile['size'] > UPLOAD_MAX_TORRENT_MB * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $ue('err_torrent_too_large', 'Torrent file is too large (max {1} MB).', UPLOAD_MAX_TORRENT_MB)]);
        exit;
    }

    // Загружен новый файл
    $originalTorrentFilename = basename($torrentFile['name']); // оригинальное имя для БД/отображения
    $torrentFilename = saveFile($torrentFile, $torrentDir, ['application/x-bittorrent']);
    if (!$torrentFilename) 
	{
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $ue('torrent_file_invalid', 'Invalid or unsupported torrent file format.')]);
        exit;
    }
    $torrentPath = $torrentDir . $torrentFilename;
} 
elseif ($isEdit) 
{
    // Используем старый файл
    $EditTorrentID = (int)$_POST['EditTorrentID'];
    $torrentFilename = $EditTorrentID . '.torrent';
    $torrentPath = $torrentDir . $torrentFilename;

    if (!file_exists($torrentPath)) 
	{
        http_response_code(400);
        error_log("upload.php: existing torrent file not found: {$torrentPath}");
        echo json_encode(["success" => false, "error" => $ue('torrent_not_found', 'Existing torrent file not found.')]);
        exit;
    }
}



// ✅ Загружаем объект
$torrentObj = TorrentFile::load($torrentPath);

// ── Passkey & announce validation (server-side safety net; JS does this earlier too) ──
// Модераторы освобождены от этой проверки ТОЛЬКО при редактировании уже
// существующего чужого торрента ($isEdit=true): в файле по праву зашит
// passkey оригинального аплоадера, а не мода - это не мошенничество.
// При создании НОВОГО торрента (даже модератором) проверка применяется
// как обычно.

if (!($is_mod && $isEdit) && empty(trim($CURUSER['passkey'] ?? ''))) {
    if ($newFileUploaded) { @unlink($torrentPath); }
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => [$lang->upload['error_no_passkey'] ?? 'Your account does not have a passkey. Please contact staff.']]);
    exit;
}

if (!($is_mod && $isEdit)) {
    $fileAnnounce = (string)($torrentObj->getAnnounce() ?? '');
    if ($fileAnnounce !== '') {
        $parsed = parse_url($fileAnnounce);
        parse_str($parsed['query'] ?? '', $qs);
        $announcePasskey = $qs['passkey'] ?? '';

        if ($announcePasskey !== '' && $announcePasskey !== $CURUSER['passkey']) {
            write_log(sprintf(
                'Security: user %s submitted the upload form for a torrent (%s) containing a passkey belonging to another account, bypassing the client-side check. Foreign passkey: %s',
                '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]',
                htmlspecialchars_uni($torrentFilename ?? basename($torrentPath)),
                $announcePasskey
            ));

            // Удаляем файл ТОЛЬКО если это временный, только что загруженный файл.
            // Если это Edit без нового файла - $torrentPath указывает на уже
            // существующий, боевой .torrent файл раздачи - его удалять нельзя.
            if ($newFileUploaded) { @unlink($torrentPath); }
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => [$lang->upload['error_wrong_passkey'] ?? 'This torrent contains a passkey that does not belong to your account.']]);
            exit;
        }
    }
}

// ── End passkey & announce validation ───────────────────────────────────────


// ✅ Генерируем новый announce URL
$AnnounceURL = trim($announce_urls[0] . "?passkey=" . $CURUSER["passkey"]);

if (isset($privatetrackerpatch) && $privatetrackerpatch === 'yes') 
{
    // Читаем файл
    $rawContent = file_get_contents($torrentPath);

    // Декодируем
    $bencode = \Arokettu\Bencode\Bencode::decode($rawContent);

    // Проверяем, уже ли стоит приватный флаг
    if (!isset($bencode['info']['private']) || $bencode['info']['private'] != 1) 
	{
        // Если флаг не установлен — ставим
        $bencode['info']['private'] = 1;

        // Задаём announce
        $bencode['announce'] = $AnnounceURL;

        // Добавляем мета-информацию
        $bencode['comment'] = $lang->upload["DefaultTorrentComment"];
        $bencode['created by'] = sprintf($lang->upload["CreatedBy"], $anonymous == "yes" ? "" : $CURUSER["username"]) . " [" . $SITENAME . "]";
        $bencode['source'] = $BASEURL;
        $bencode['creation date'] = TIMENOW;

        // Перекодируем
        $newContent = \Arokettu\Bencode\Bencode::encode($bencode);

        // Сохраняем файл
        file_put_contents($torrentPath, $newContent);

        // Перезагружаем объект
        $torrentObj = \Arokettu\Torrent\TorrentFile::load($torrentPath);

        // Проверяем новый хеш
        $check_info_hash = $torrentObj->v1()->getInfoHash();
        if ($info_hash != $check_info_hash) 
		{
            $info_hash = $check_info_hash;
            $Info_hash_changed = true;
        }
    }
}

// ✅ Пересчитываем info_hash
$info_hash = $torrentObj->v1()->getInfoHash();

// ✅ Подсчёт файлов и размера
$files = $torrentObj->v1()->getFiles();
$numfiles = count($files);

$size = 0;
foreach ($files as $file) 
{
    $size += $file->length;
}

// ✅ Проверка, изменился ли хеш
$check_info_hash = $torrentObj->v1()->getInfoHash();
if ($info_hash != $check_info_hash) {
    $info_hash = $check_info_hash;
    unset($check_info_hash);
    $UpdateHash = true;
    $Info_hash_changed = true;
}








// Optional NFO
$nfoFilename = null;
if (!empty($_FILES['nfoFile']['tmp_name'])) 
{
    $nfoFilename = saveFile($_FILES['nfoFile'], $nfoDir, ['text/plain']);
}




$max_screenshots ??= (int)($usergroups['max_screenshots'] ?? 3);

// Screenshots
$screenshotFilenames = [];
if (!empty($_FILES['screenshotsUpload']['tmp_name'][0])) 
{
    
	$uploaded_count = count(array_filter($_FILES['screenshotsUpload']['tmp_name']));

    if ($uploaded_count > $max_screenshots) {
        $errors[] = $ue('err_screens_limit', 'Your group allows a maximum of {1} screenshots. You tried to upload {2}.', (int)$max_screenshots, (int)$uploaded_count);
    } 
	
	
	else {
		
	foreach ($_FILES['screenshotsUpload']['tmp_name'] as $index => $tmpName) 
	{
        $file = [
            'name' => $_FILES['screenshotsUpload']['name'][$index],
            'type' => $_FILES['screenshotsUpload']['type'][$index],
            'tmp_name' => $tmpName,
            'error' => $_FILES['screenshotsUpload']['error'][$index],
            'size' => $_FILES['screenshotsUpload']['size'][$index],
        ];
        $filename = saveFile($file, $screenshotDir, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp']);
        if ($filename) 
		{
            // Перекодирование — защита от "полиглот"-файлов. saveFile() не
            // отдаёт mime наружу, поэтому определяем заново по уже
            // сохранённому файлу.
            $savedPath = rtrim($screenshotDir, '/') . '/' . $filename;
            $shotInfo  = @getimagesize($savedPath);
            if ($shotInfo === false || recode_image_file($savedPath, $shotInfo['mime']) === false) {
                // Раньше тут ничего не сообщалось - файл тихо удалялся,
                // счётчик загруженных просто оказывался меньше ожидаемого,
                // без единой подсказки почему.
                error_log(sprintf(
                    'Screenshot recode failed: file=%s mime=%s getimagesize=%s',
                    $file['name'],
                    $shotInfo['mime'] ?? 'unknown',
                    $shotInfo === false ? 'false' : 'ok'
                ));
                $errors[] = $ue('err_screen_bad_file', 'Screenshot "{1}": could not be processed (corrupted or unsupported image).', (string)$file['name']);
                @unlink($savedPath);
            } else {
                $screenshotFilenames[] = $filename;
            }
        }
    }
	
	}
}















// Description
$description = trim($_POST['description'] ?? '');
if (empty($description)) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $ue('description_required', 'Description is required.')]);
    exit;
}

// MediaInfo (необязательно): отдельная таблица torrents_mediainfo, карточка на details.php
$mediainfoPosted = isset($_POST['mediainfo']);
$mediainfo       = trim(str_replace("\r\n", "\n", (string)($_POST['mediainfo'] ?? '')));
// Формат у всех разный: сохраняем любой текст. Разобрали — карточка, нет — показываем как есть.
if (mb_strlen($mediainfo) > 200000) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $ue('mediainfo_too_long', 'MediaInfo text is too long.')]);
    exit;
}

// Torrent Name
$torrentName = trim($_POST['formName'] ?? '');
if (empty($torrentName)) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $ue('torrent_name_required', 'Torrent name is required.')]);
    exit;
}

// Category
$category = isset($_POST['category']) ? (int) $_POST['category'] : 0;
if ($category <= 0) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $ue('category_required', 'Category is required.')]);
    exit;
}




// IMDb URL handling
$t_link = trim($_POST['imdbUrl'] ?? '');
$Genre = '';

if (!empty($t_link)) 
{
    // Ensure the URL ends with a slash
    if (substr($t_link, -1) !== '/') 
	{
        $t_link .= '/';
    }

    // Validate IMDb URL structure
    if (preg_match('@^https:\/\/www\.imdb\.com\/title\/(.*)\/$@isU', $t_link, $result)) 
	{
        if (!empty($result[0])) 
		{
            $t_link = $result[0];

            // Include script that fetches metadata (like $Genre)
            include_once(INC_PATH . '/imdb_parser.php');

            $Genre = $Genre ?? '';
        }
    } 
	else 
	{
        // Optionally handle invalid IMDb format (log, ignore, or reject)
        $t_link = ''; // or keep empty
    }
}








// Handle torrent flags from checkboxes (Free, Silver, Double Upload, Allow Comments, Sticky, Nuked)
$free = (isset($_POST['free']) && $_POST['free'] === 'yes') ? 'yes' : 'no';
$silver = (isset($_POST['silver']) && $_POST['silver'] === 'yes') ? 'yes' : 'no';
$doubleUpload = (isset($_POST['doubleupload']) && $_POST['doubleupload'] === 'yes') ? 'yes' : 'no';
$thirtypercent = (isset($_POST['thirtypercent']) && $_POST['thirtypercent'] === 'yes') ? 'yes' : 'no';





$userPickedPromoManually = ($free === 'yes' || $silver === 'yes' || $doubleUpload === 'yes' || $thirtypercent === 'yes');

if (!$isEdit && !$userPickedPromoManually) {
    // largepro keys map 1-7 to the same flag combinations as PROMO_TARGETS
    // in torrentspromo.php (1=normal, 2=free, 3=2x, 4=2x free, 5=50%, 6=2x 50%, 7=30%)
    $applyPromoType = static function (int $type) use (&$free, &$silver, &$doubleUpload, &$thirtypercent): void {
        $free = $silver = $doubleUpload = $thirtypercent = 'no';
        match ($type) {
            2 => $free = 'yes',
            3 => $doubleUpload = 'yes',
            4 => ($free = $doubleUpload = 'yes'),
            5 => $silver = 'yes',
            6 => ($silver = $doubleUpload = 'yes'),
            7 => $thirtypercent = 'yes',
            default => null,
        };
    };

    $largeSizeGb = (float)($largesize ?? 0);
    $largeSizeBytes = $largeSizeGb * 1024 * 1024 * 1024;

    if ($largeSizeGb > 0 && $size >= $largeSizeBytes) {
        // Large-size rule takes priority over random chance
        $applyPromoType((int)($largepro ?? 2));
    } else {
        // Random chance, matching original NexusPHP takeupload.php exactly:
        // ONE roll (1-100), checked against cumulative thresholds in this
        // specific order (2X Free, 2X, Free, Half Leech, 2X Half Leech, 30%),
        // falling through to Normal if nothing hit. This is NOT independent
        // per-rule rolls — each threshold builds on the previous one, so the
        // order of the elseif chain materially affects the odds.
        $spId = random_int(1, 100);
        $probability = (int)($randomtwoupfree ?? 0);
        if ($spId <= $probability) {
            $applyPromoType(4); // 2X Free
        } elseif ($spId <= ($probability += (int)($randomtwoup ?? 0))) {
            $applyPromoType(3); // 2X
        } elseif ($spId <= ($probability += (int)($randomfree ?? 0))) {
            $applyPromoType(2); // Free
        } elseif ($spId <= ($probability += (int)($randomhalfleech ?? 0))) {
            $applyPromoType(5); // Half Leech (50%)
        } elseif ($spId <= ($probability += (int)($randomtwouphalfdown ?? 0))) {
            $applyPromoType(6); // 2X Half Leech
        } elseif ($spId <= ($probability += (int)($randomthirtypercentdown ?? 0))) {
            $applyPromoType(7); // 30% Leech
        } else {
            $applyPromoType(1); // Normal — explicit no-op but kept for clarity
        }
    }
}













// For allowcomments checkbox, if it's missing or not 'no', allow comments = 'yes', else 'no'
$allowComments = (!isset($_POST['allowcomments']) || $_POST['allowcomments'] !== 'no') ? 'yes' : 'no';

$sticky = (isset($_POST['sticky']) && $_POST['sticky'] === 'yes') ? 'yes' : 'no';
$isNuked = (isset($_POST['isnuked']) && $_POST['isnuked'] === 'yes') ? 'yes' : 'no';

// Only set reason if nuked = 'yes', otherwise empty string
$nukeReason = ($isNuked === 'yes' && !empty($_POST['WhyNuked'])) ? trim($_POST['WhyNuked']) : '';


$anonymous = (isset($_POST['anonymous']) && $_POST['anonymous'] === 'yes') ? 'yes' : 'no';


$request = (isset($_POST['request']) && $_POST['request'] === 'yes') ? 'yes' : 'no';



// Определяем, редактируем ли мы торрент
$isEdit = isset($_POST['EditTorrent']) && !empty($_POST['EditTorrentID']);


// Store metadata (Example: Save to DB or log)
$metadata = array(
    'name' => $torrentName,
    't_link' => $t_link,
    'tags' => trim($_POST['tags'] ?? $Genre),
    //'owner' => $CURUSER['id'],
    'category' => $category,
    'anonymous' => $anonymous,
    'isrequest' => $request,
    'free' => $free,
    'silver' => $silver,
    'doubleupload' => $doubleUpload,
	'thirtypercent' => $thirtypercent,
    'allowcomments' => $allowComments,
    'sticky' => $sticky,
    'isnuked' => $isNuked,
    'WhyNuked' => $nukeReason,
    'descr' => $description
    
);



// Добавляем дату добавления только при новом торрента
if (!$isEdit) 
{
    $metadata['owner'] = $CURUSER['id'];
	$metadata['added'] = TIMENOW;
}



if ($torrentFilename) 
{
    // filename обновляем только если загружен новый файл
    if ($newFileUploaded) {
        $metadata['filename'] = $originalTorrentFilename;
    } elseif (!$isEdit) {
        $metadata['filename'] = $originalTorrentFilename;
    }
    $metadata['info_hash'] = $info_hash;
    $metadata['size'] = (int)$size;
    $metadata['numfiles'] = (int)$numfiles;
}






// Assuming you have a database instance `$db`


    if ($isEdit) 
	{
        $EditTorrentID = (int)$_POST['EditTorrentID'];

        // Проверка владельца: сохранять может только владелец торрента или модератор.
        // Никогда не доверяем данным из POST для этой проверки - берём владельца
        // напрямую из базы.
        $ownerCheck_q = $db->sql_query_prepared("SELECT owner FROM torrents WHERE id = ?", [$EditTorrentID]);
        $ownerCheck = $ownerCheck_q ? $db->fetch_array($ownerCheck_q) : null;
        if (!$ownerCheck || (!$is_mod && (int)$CURUSER['id'] !== (int)$ownerCheck['owner'])) {
            write_log(sprintf(
                'Security: user %s attempted to edit torrent #%d without permission (not the owner, not a moderator). Actual owner id: %s',
                '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]',
                $EditTorrentID,
                $ownerCheck ? (string)$ownerCheck['owner'] : 'unknown (torrent not found)'
            ));

            http_response_code(403);
            echo json_encode(["success" => false, "error" => $ue('no_permission_edit', 'You do not have permission to edit this torrent.')]);
            exit;
        }

        // Update database entry
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($metadata)));
        $params = array_values($metadata);
        $params[] = $EditTorrentID;
        $db->sql_query_prepared("UPDATE torrents SET {$set} WHERE id = ?", $params);
        $NewTID = $EditTorrentID;
		
		
		
        if (!empty($_POST['file_ids'])) 
		{
             $file_ids = array_map('intval', $_POST['file_ids']); // защита

             if (!empty($file_ids)) 
		     {
                 $ph = implode(',', array_fill(0, count($file_ids), '?'));
                 $db->sql_query_prepared("UPDATE comment_files SET torrent_id = ? WHERE id IN ({$ph})", [$NewTID, ...$file_ids]);
             }
        }
		
		
		
		
		write_log( sprintf($lang->upload['editedtorrent'], '[URL='.$BASEURL."/".get_torrent_link($EditTorrentID).']<font color=red>' . $torrentName . '</font>[/URL]', '[URL='.$BASEURL . '/'.get_profile_link($CURUSER['id']).']' . format_name($CURUSER['username'],$CURUSER['usergroup']) . '[/URL]'));
		
		

  
		// Delete old torrent file to replace with new one
        if ($newFileUploaded) 
		{
           $oldTorrentFile = $torrentDir . $NewTID . '.torrent';
           if (is_file($oldTorrentFile)) 
		   {
              @unlink($oldTorrentFile);
           }
        }
		
		
		
		// 2. Очистить изображения, если нужно
        $UpdateSet = [];

       if (empty($_POST['imageUrl']) && empty($_FILES['imagesUpload']['tmp_name'])) 
	   {
           $row_q = $db->sql_query_prepared("SELECT t_image FROM torrents WHERE id = ?", [$NewTID]);
           $row = $row_q ? $db->fetch_array($row_q) : null;
           if (!empty($row['t_image'])) 
		   {
              $imgPath = parse_url($row['t_image'], PHP_URL_PATH);
              $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imgPath;
              if (is_file($fullPath)) 
			  {
                  @unlink($fullPath);
              }
           }
           $UpdateSet['t_image'] = '';
       }

       if (empty($_POST['imageUrl2']) && empty($_FILES['imagesUpload2']['tmp_name'])) 
	   {
           $row_q = $db->sql_query_prepared("SELECT t_image2 FROM torrents WHERE id = ?", [$NewTID]);
           $row = $row_q ? $db->fetch_array($row_q) : null;
           if (!empty($row['t_image2'])) 
		   {
              $imgPath2 = parse_url($row['t_image2'], PHP_URL_PATH);
              $fullPath2 = $_SERVER['DOCUMENT_ROOT'] . $imgPath2;
              if (is_file($fullPath2)) 
			  {
                  @unlink($fullPath2);
              }
           }
           $UpdateSet['t_image2'] = '';
        }

        if (!empty($UpdateSet)) 
		{
            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($UpdateSet)));
            $params = array_values($UpdateSet);
            $params[] = $NewTID;
            $db->sql_query_prepared("UPDATE torrents SET {$set} WHERE id = ?", $params);
        }
		
		
		
		
    } 
	else 
	{
        // Insert new torrent record
        $columns      = array_keys($metadata);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $db->sql_query_prepared(
            "INSERT INTO torrents (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
            array_values($metadata)
        );
        $NewTID = $db->insert_id();
		
		// Привязываем загруженные файлы к этому комментарию
        if (!empty($_POST['file_ids'])) 
		{
             $file_ids = array_map('intval', $_POST['file_ids']); // защита

             if (!empty($file_ids)) 
		     {
                 $ph = implode(',', array_fill(0, count($file_ids), '?'));
                 $db->sql_query_prepared("UPDATE comment_files SET torrent_id = ? WHERE id IN ({$ph})", [$NewTID, ...$file_ids]);
             }
        }
		
		
		
		
		
		// Now log the upload event
        write_log(sprintf($lang->upload['newtorrent'],'[URL=' . $BASEURL . "/" . get_torrent_link($NewTID) . ']<font color=red>' . $torrentName . '</font>[/URL]','[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]'));
       
		
		notify_upload_subscribers((int)$category, $NewTID, $torrentName);

		// Chat bot: "New torrent" in the public chat (switch: Settings → Cleanup → Chat bot)
		require_once INC_PATH . '/functions_chat.php';
		chat_bot_post('upload', ['id' => (int)$NewTID]);
		
		//kps('+', $kpsupload, $CURUSER['id']);
		kps('+', $kpsupload, $CURUSER['id'],
             'Torrent uploaded: ' . mb_strimwidth($torrentName, 0, 120, '…') . ' (#' . (int)$NewTID . ')',
             (int)$NewTID);
		
		
    }
	
	
	
	
	
function get_next_screenshot_number($torrent_id, $db, $step = 3)
{
    $maxNum = 0;
    $existingScreenshots = [];

    // Загружаем существующие имена файлов
    $res = $db->sql_query_prepared("SELECT filename FROM `screenshots` WHERE torrent_id = ?", [$torrent_id]);
    while ($res && ($row = $db->fetch_array($res))) 
	{
        $existingScreenshots[] = $row['filename'];
    }

    // Ищем максимальный номер
    foreach ($existingScreenshots as $file) 
	{
        if (preg_match('/^' . $torrent_id . '_(\d+)\./', $file, $matches)) 
		{
            $num = (int)$matches[1];
            if ($num > $maxNum) 
			{
                $maxNum = $num;
            }
        }
    }

    
    return $maxNum + $step;
}



if (!empty($screenshotFilenames)) 
{
    // Лимит на общее число скриншотов у торрента
    
    
    $existingScreenshotsCount = (int)$db->fetch_field(
        $db->sql_query_prepared("SELECT COUNT(*) as cnt FROM screenshots WHERE torrent_id = ?", [$NewTID]),
        'cnt'
    );
    $screenshotSlotsLeft = max(0, $max_screenshots - $existingScreenshotsCount);
    $screenshotFilenames = array_slice($screenshotFilenames, 0, $screenshotSlotsLeft);
}

if (!empty($screenshotFilenames)) 
{
    
    $count = get_next_screenshot_number($NewTID, $db, 3);

    foreach ($screenshotFilenames as $originalFilename) 
    {
        $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $newFilename = "{$NewTID}_{$count}." . $ext;

        $oldFilePath = $screenshotDir . '/' . $originalFilename;
        $newFilePath = $screenshotDir . '/' . $newFilename;

        if (file_exists($oldFilePath)) 
		{
            rename($oldFilePath, $newFilePath);
        }

		
		
		$db->sql_query_prepared(
			"INSERT INTO screenshots (`torrent_id`,`filename`,`uploaded_at`) VALUES (?,?,?)",
			[$NewTID, $newFilename, TIMENOW]
		);
		

        $count++;
    }
}








// Rename the torrent file using NewTID
$originalTorrentPath = $torrentDir . $torrentFilename;
$finalTorrentFilename = $NewTID . '.torrent';
$finalTorrentPath = $torrentDir . $finalTorrentFilename;

if (($newFileUploaded || !$isEdit) && $torrentFilename && file_exists($originalTorrentPath)) 
{
    if (!rename($originalTorrentPath, $finalTorrentPath)) 
	{
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $ue('torrent_rename_error', 'Failed to rename the torrent file.')]);
        exit;
    }
}














// Optional image
// Общая карта расширений — используется и для основной, и для второй картинки
$extByType = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

// Optional image
$imageFilename = null;
if (!empty($_FILES['imagesUpload']['tmp_name'])) 
{
    $imageFile = $_FILES['imagesUpload'];

    // Не доверяем $imageFile['type'] (заголовок от браузера, легко подделать) -
    // проверяем реальное содержимое файла через getimagesize().
    $imageInfo = ($imageFile['error'] === 0) ? @getimagesize($imageFile['tmp_name']) : false;

    if ($imageInfo !== false) 
    {
        $ext = $extByType[$imageInfo[2]] ?? null;

        if ($ext !== null) 
        {
            // Define the new image filename using $id
            $imageFilename = $NewTID . '.' . $ext;
            $targetPath = rtrim($imageDir, '/') . '/' . $imageFilename;

            // Optional: remove existing file if present
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            // Move uploaded file to new location with new name
            if (move_uploaded_file($imageFile['tmp_name'], $targetPath)) 
            {
                // Перекодирование — защита от "полиглот"-файлов. Сама
                // recode_image_file() отличает анимированные GIF (Imagick,
                // с сохранением анимации) от статичных (обычный путь GD).
                if (recode_image_file($targetPath, $imageInfo['mime']) === false) {
                    @unlink($targetPath);
                    $imageFilename = null;
                } else {
                $NewImageURL = 'torrents/images/' . $NewTID . '.' . $ext;
				
				$db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL, $NewTID]);
				
                }
            } 
            else 
            {
                // Handle error: unable to move uploaded file
                $imageFilename = null;
            }
        }
    }
}










// Optional image 2
$imageFilename2 = null;
if (!empty($_FILES['imagesUpload2']['tmp_name'])) 
{
    $imageFile = $_FILES['imagesUpload2'];

    $imageInfo = ($imageFile['error'] === 0) ? @getimagesize($imageFile['tmp_name']) : false;

    if ($imageInfo !== false) 
    {
        $ext = $extByType[$imageInfo[2]] ?? null;

        if ($ext !== null) 
        {
            // Define the new image filename using $id
            $imageFilename2 = $NewTID . '_2.' . $ext;
			
			
			
            $targetPath = rtrim($imageDir, '/') . '/' . $imageFilename2;

            // Optional: remove existing file if present
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            // Move uploaded file to new location with new name
            if (move_uploaded_file($imageFile['tmp_name'], $targetPath)) 
            {
                // Перекодирование — защита от "полиглот"-файлов.
                if (recode_image_file($targetPath, $imageInfo['mime']) === false) {
                    @unlink($targetPath);
                    $imageFilename2 = null;
                } else {
				$NewImageURL2 = 'torrents/images/' . $NewTID . '_2.' . $ext;
				
				$db->sql_query_prepared("UPDATE torrents SET t_image2 = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL2, $NewTID]);
				
                }
				
			
            } 
            else 
            {
                // Handle error: unable to move uploaded file
                $imageFilename2 = null;
            }
        }
    }
}










// Свой постер (при редактировании поле заполнено текущим t_image):
// не скачиваем заново; если файла нет — чистим битую ссылку.
$posterState1 = !empty($_POST['imageUrl'])
    ? ags_own_poster_state((string)$_POST['imageUrl'], $BASEURL)
    : 'external';

if ($posterState1 === 'missing') {
    // Только если в базе всё ещё эта ссылка (новый загруженный файл не трогаем)
    $db->sql_query_prepared("UPDATE torrents SET t_image = '' WHERE id = ? AND t_image = ?", [$NewTID, trim((string)$_POST['imageUrl'])]);
}

// === IMAGE FROM URL 1 ===
if (!empty($_POST['imageUrl']) && $posterState1 === 'external') 
{
    $imageUrl = filter_var($_POST['imageUrl'], FILTER_VALIDATE_URL);

    if ($imageUrl && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imageUrl)) 
	{
        $imageData = @file_get_contents($imageUrl);
        if ($imageData !== false) 
		{
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = strtolower(in_array($extension, ['jpg','jpeg','png','gif','webp']) ? $extension : 'jpg');
            $imageName = $NewTID . '.' . $extension;
            $uploadPath = __DIR__ . "/torrents/images/" . $imageName;

            if (@file_put_contents($uploadPath, $imageData)) 
			{
                // Проверка содержимого + перекодировка — защита от
                // "полиглот"-файлов. Раньше файл, скачанный по URL,
                // сохранялся напрямую без всякой проверки, что это
                // реально изображение.
                $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($uploadPath);
                $imgCheck = @getimagesize($uploadPath);
                if ($imgCheck === false || !str_starts_with((string)$realMime, 'image/')
                    || recode_image_file($uploadPath, $realMime) === false) {
                    @unlink($uploadPath);
                    $errors[] = $ue('err_image_not_valid', 'Image {1}: the link does not point to a valid image.', 1);
                } else {
                    $NewImageURL = 'torrents/images/' . $imageName;
                    $db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL, $NewTID]);
                }
            } 
			else 
			{
                $errors[] = $ue('err_image_save', 'Image {1}: could not be saved.', 1);
            }
        } 
		else 
		{
            $errors[] = $ue('err_image_download', 'Image {1}: could not be downloaded.', 1);
        }
    } 
	else 
	{
        $errors[] = $ue('err_image_url', 'Image {1}: invalid link (it must end in .jpg, .png, .gif or .webp).', 1);
    }
}

// Свой постер (при редактировании поле заполнено текущим t_image2):
// не скачиваем заново; если файла нет — чистим битую ссылку.
$posterState2 = !empty($_POST['imageUrl2'])
    ? ags_own_poster_state((string)$_POST['imageUrl2'], $BASEURL)
    : 'external';

if ($posterState2 === 'missing') {
    // Только если в базе всё ещё эта ссылка (новый загруженный файл не трогаем)
    $db->sql_query_prepared("UPDATE torrents SET t_image2 = '' WHERE id = ? AND t_image2 = ?", [$NewTID, trim((string)$_POST['imageUrl2'])]);
}

// === IMAGE FROM URL 2 ===
if (!empty($_POST['imageUrl2']) && $posterState2 === 'external') 
{
    $imageUrl2 = filter_var($_POST['imageUrl2'], FILTER_VALIDATE_URL);

    if ($imageUrl2 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imageUrl2)) 
	{
        $imageData2 = @file_get_contents($imageUrl2);
        if ($imageData2 !== false) 
		{
            $extension2 = pathinfo(parse_url($imageUrl2, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension2 = strtolower(in_array($extension2, ['jpg','jpeg','png','gif','webp']) ? $extension2 : 'jpg');
            $imageName2 = $NewTID . '_2.' . $extension2;
            $uploadPath2 = __DIR__ . "/torrents/images/" . $imageName2;

            if (@file_put_contents($uploadPath2, $imageData2)) 
			{
                // Проверка содержимого + перекодировка — защита от
                // "полиглот"-файлов.
                $realMime2 = (new finfo(FILEINFO_MIME_TYPE))->file($uploadPath2);
                $imgCheck2 = @getimagesize($uploadPath2);
                if ($imgCheck2 === false || !str_starts_with((string)$realMime2, 'image/')
                    || recode_image_file($uploadPath2, $realMime2) === false) {
                    @unlink($uploadPath2);
                    $errors[] = $ue('err_image_not_valid', 'Image {1}: the link does not point to a valid image.', 2);
                } else {
                    $NewImageURL2 = 'torrents/images/' . $imageName2;
                    $db->sql_query_prepared("UPDATE torrents SET t_image2 = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL2, $NewTID]);
                }
            } 
			else 
			{
                $errors[] = $ue('err_image_save', 'Image {1}: could not be saved.', 2);
            }
        } 
		else 
		{
            $errors[] = $ue('err_image_download', 'Image {1}: could not be downloaded.', 2);
        }
    } 
	else 
	{
        $errors[] = $ue('err_image_url', 'Image {1}: invalid link (it must end in .jpg, .png, .gif or .webp).', 2);
    }
}


// Optional: log metadata
//file_put_contents(__DIR__ . '/upload_log.json', json_encode($metadata, JSON_PRETTY_PRINT), FILE_APPEND);



// Screenshot URLs (bulk URL upload)
if (!empty($_POST['screenshot_urls']) && is_array($_POST['screenshot_urls'])) {

    // Сколько уже есть у торрента и сколько ещё влезет
    $dbExistingScreenshots = (int)$db->fetch_field(
        $db->sql_query_prepared("SELECT COUNT(*) as cnt FROM screenshots WHERE torrent_id = ?", [$NewTID]),
        'cnt'
    );
    $slotsLeft = max(0, $max_screenshots - $dbExistingScreenshots);

    // ✅ Считаем стартовый номер ОДИН РАЗ до цикла,
    // дальше просто инкрементим локально — вместо SELECT filename
    // на каждой итерации.
    $count = ($slotsLeft > 0) ? get_next_screenshot_number($NewTID, $db, 3) : 0;

    // Include remote-connect один раз, а не на каждой итерации
    $hasRemoteConnect = file_exists(INC_PATH . '/functions_remote_connect.php');
    if ($hasRemoteConnect) {
        include_once(INC_PATH . '/functions_remote_connect.php');
    }

    foreach ($_POST['screenshot_urls'] as $screenshotUrl) {
        if ($slotsLeft <= 0) break;

        $rawUrl = trim($screenshotUrl);
        $screenshotUrl = filter_var($rawUrl, FILTER_VALIDATE_URL);
        if (!$screenshotUrl) {
            $errors[] = $ue('err_shot_url_invalid', 'Screenshot link "{1}": not a valid URL.', $rawUrl);
            continue;
        }
	
		
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $screenshotUrl)) {
            $errors[] = $ue('err_shot_url_ext', 'Screenshot link "{1}": must end in .jpg/.jpeg/.png/.gif/.webp (gallery page links are not supported — use the direct image link).', $rawUrl);
            continue;
        }

        $ext = strtolower(pathinfo(parse_url($screenshotUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $errors[] = $ue('err_shot_url_ext2', 'Screenshot link "{1}": unsupported file type.', $rawUrl);
            continue;
        }

        // Скачивание
        if ($hasRemoteConnect && function_exists('fetch_remote_file')) {
            $imgData = fetch_remote_file($screenshotUrl, false);
        } else {
           
            $refererHost = parse_url($screenshotUrl, PHP_URL_HOST);
            $context = stream_context_create([
                'http' => [
                    'timeout'         => 10,
                    'follow_location' => true,
                    'user_agent'      => 'Mozilla/5.0',
                    'header'          => $refererHost ? "Referer: https://{$refererHost}/\r\n" : '',
                ]
            ]);
            $imgData = @file_get_contents($screenshotUrl, false, $context);

        }

        if (!$imgData) {
            $errors[] = $ue('err_shot_url_download', 'Screenshot link "{1}": could not be downloaded.', $rawUrl);
            continue;
        }

        $filename = $NewTID . '_' . $count . '.' . $ext;
        $filePath = rtrim($screenshotDir, '/') . '/' . $filename;

        if (@file_put_contents($filePath, $imgData)) {
           
            $realMimeShot = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
            $imgCheckShot = @getimagesize($filePath);
            if ($imgCheckShot === false || !str_starts_with((string)$realMimeShot, 'image/')
                || recode_image_file($filePath, $realMimeShot) === false) {
                @unlink($filePath);
                $errors[] = $ue('err_shot_url_not_image', 'Screenshot link "{1}": the downloaded file is not an image ({2}). If this is a gallery page link, use the direct image link.', $rawUrl, (string)($realMimeShot ?: 'unknown'));
                continue;
            }

            $insertOk = $db->sql_query_prepared(
                "INSERT INTO screenshots (`torrent_id`,`filename`,`uploaded_at`) VALUES (?,?,?)",
                [$NewTID, $filename, TIMENOW]
            );

            if (!$insertOk) {
                @unlink($filePath);
                error_log("upload.php: screenshot INSERT failed (URL), removed orphan file {$filePath}");
                $errors[] = $ue('err_shot_url_db', 'Screenshot link "{1}": database error.', $rawUrl);
                continue;
            }

            $count++;
            $slotsLeft--;
        }
    }
}








// MediaInfo — сохраняем в БД (пустое поле при редактировании = удалить)
if ($mediainfoPosted) {
    if ($mediainfo !== '') {
        $db->sql_query_prepared(
            "INSERT INTO torrents_mediainfo (`torrent_id`,`mediainfo`,`updated_at`) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE `mediainfo` = VALUES(`mediainfo`), `updated_at` = VALUES(`updated_at`)",
            [(int)$NewTID, $mediainfo, TIMENOW]
        );
    } elseif ($isEdit) {
        $db->sql_query_prepared("DELETE FROM torrents_mediainfo WHERE torrent_id = ?", [(int)$NewTID]);
    }
}

// NFO файл — сохраняем в БД
if ($nfoContentRaw !== null && strlen($nfoContentRaw) > 0) {
    // Пробуем определить кодировку только из поддерживаемых
    $nfoUtf8 = null;

    // Проверяем валидный ли UTF-8
    if (mb_check_encoding($nfoContentRaw, 'UTF-8')) {
        $nfoUtf8 = $nfoContentRaw;
    } else {
        // NFO файлы обычно в Windows-1252 или ISO-8859-1
        $nfoUtf8 = mb_convert_encoding($nfoContentRaw, 'UTF-8', 'Windows-1252');
    }

    if (!empty($nfoUtf8)) {
        $existingNfo_q = $db->sql_query_prepared("SELECT id FROM torrents_nfo WHERE torrent_id = ? LIMIT 1", [$NewTID]);

        if ($existingNfo_q && $db->num_rows($existingNfo_q)) {
            $db->sql_query_prepared(
                "UPDATE torrents_nfo SET nfo = ?, uploaded_at = ? WHERE torrent_id = ?",
                [$nfoUtf8, TIMENOW, $NewTID]
            );
        } else {
            $db->sql_query_prepared(
                "INSERT INTO torrents_nfo (`torrent_id`,`nfo`,`uploaded_at`) VALUES (?,?,?)",
                [(int)$NewTID, $nfoUtf8, TIMENOW]
            );
        }
    }
}











// ✅ Очистить буферы
while (ob_get_level()) 
{
    ob_end_clean();
}







// ✅ Повторно ставим заголовок (обязательно!)
header('Content-Type: application/json; charset=utf-8');





if (!empty($errors)) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "errors" => $errors]);
} 
else 
{
    $response = [
        "success" => true,
        "id" => $NewTID,
        "link" => get_torrent_link($NewTID),
        "download" => get_download_link($NewTID),
    ];

    if (isset($Info_hash_changed)) 
	{
        $response["hash_changed"] = true;
    }

    echo json_encode($response);
}


exit;


}





stdhead($lang->upload['head']);




$max_screenshots = (int)($usergroups['max_screenshots'] ?? 3);








$torrent = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) 
{
    $EditTorrent = true;
    $EditTorrentID = (int)$_GET['id'];

    // Sanitize input by casting to int (already done)
    //$query = "SELECT * FROM torrents WHERE id = $EditTorrentID";

    // Execute query
    $result = $db->sql_query_prepared("SELECT * FROM torrents WHERE id = ?", [$EditTorrentID]);

    // Fetch the result as an associative array
    $torrent = $result ? $db->fetch_array($result) : null;

    // Проверка владельца: редактировать может только владелец торрента или модератор
    if (!$torrent || (!$is_mod && (int)$CURUSER['id'] !== (int)$torrent['owner'])) {
        write_log(sprintf(
            'Security: user %s attempted to open the edit form for torrent #%d without permission (not the owner, not a moderator). Actual owner id: %s',
            '[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]',
            $EditTorrentID,
            $torrent ? (string)$torrent['owner'] : 'unknown (torrent not found)'
        ));

        stderr($lang->upload['no_permission_edit'], '', 403, '403editupload');
    }
	
	
	
	// MediaInfo для формы редактирования
    $torrent_mediainfo = '';
    $miRes = $db->sql_query_prepared("SELECT mediainfo FROM torrents_mediainfo WHERE torrent_id = ?", [$EditTorrentID]);
    if ($miRes && ($miRow = $db->fetch_array($miRes))) {
        $torrent_mediainfo = (string)$miRow['mediainfo'];
    }

	// Получить все скрины торрента
    $screenshots = [];
    $res = $db->sql_query_prepared("SELECT id, filename FROM `screenshots` WHERE torrent_id = ? ORDER BY sort_order ASC, id ASC", [$EditTorrentID]);
    while ($res && ($row = $db->fetch_array($res))) 
    {
       $screenshots[] = $row;
    }
	
	
	
	
	

} 
else 
{
    $EditTorrent = false;
}


// Режим страницы
$isEdit      = isset($EditTorrent) && !empty($EditTorrentID);
$headingText = $isEdit ? $lang->upload['head_edit'] : $lang->upload['head'];
$buttonIcon  = $isEdit ? 'fa-pen-to-square' : 'fa-upload';
$buttonText  = $isEdit ? $lang->upload['btn_update'] : $lang->upload['btn_upload'];


$t_link = $torrent['t_link'] ?? '';
if ($t_link && preg_match('@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result))
{
    $t_link = $result[0];
    unset($result);
}

$AnnounceURL = trim($announce_urls[0] . "?passkey=" . $CURUSER["passkey"]);


// ── KPI-плитки ──────────────────────────────────────────────────────────────
// Формат: [иконка, цвет значка, значение, подпись, id значения (или '')]
$upKpis = [];

if ($isEdit) {
    $upKpis = [
        ['fa-hard-drive',  'primary', mksize((float)($torrent['size'] ?? 0)), $lang->upload['torrent_size'], ''],
        ['fa-file-lines',  'info',    number_format((int)($torrent['numfiles'] ?? 0)), $lang->upload['torrent_files'], ''],
        ['fa-seedling',    'success', number_format((int)($torrent['seeders'] ?? 0)) . ' / ' . number_format((int)($torrent['leechers'] ?? 0)), $lang->global['seeders'] . ' / ' . $lang->global['leechers'], ''],
        ['fa-camera',      'warning', count($screenshots ?? []) . ' / ' . (int)$max_screenshots, $lang->upload['screenshots'], 'upKpiShots'],
    ];
} else {
    $myStatsQ = $db->sql_query_prepared("SELECT COUNT(*) AS cnt, COALESCE(SUM(size), 0) AS total FROM torrents WHERE owner = ?", [(int)$CURUSER['id']]);
    $myStats  = $myStatsQ ? $db->fetch_array($myStatsQ) : null;

    $upKpis = [
        ['fa-cloud-arrow-up', 'primary', number_format((int)($myStats['cnt'] ?? 0)), $lang->upload['kpi_my_torrents'] ?? 'My torrents', ''],
        ['fa-hard-drive',     'info',    mksize((float)($myStats['total'] ?? 0)), $lang->upload['kpi_my_size'] ?? 'Total size', ''],
    ];

    // Бонус показываем только если он реально начисляется (те же условия, что в kps())
    if (($bonus === 'enable' || $bonus === 'disablesave') && (float)($kpsupload ?? 0) > 0) {
        $upKpis[] = ['fa-coins', 'warning', '+' . rtrim(rtrim(number_format((float)$kpsupload, 2, '.', ''), '0'), '.'), $lang->upload['kpi_upload_bonus'] ?? 'Bonus per upload', ''];
    } else {
        $upKpis[] = ['fa-file-arrow-up', 'warning', (int)UPLOAD_MAX_TORRENT_MB . ' MB', $lang->upload['kpi_torrent_limit'] ?? 'Max .torrent size', ''];
    }

    $upKpis[] = ['fa-camera', 'success', (string)(int)$max_screenshots, $lang->upload['kpi_shots_limit'] ?? 'Screenshots limit', ''];
}

?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/st-ui.css?ver=7">
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/upload.css?ver=9">

<div class="container-md st-page up-page">

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid <?= $isEdit ? 'fa-pen-to-square' : 'fa-cloud-arrow-up' ?>"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $headingText ?></h1>
            <?php if ($isEdit): ?>
                <div class="st-head__meta">
                    <span><i class="fa-solid fa-magnet"></i><a href="<?= htmlspecialchars(get_torrent_link($EditTorrentID)) ?>"><?= htmlspecialchars((string)($torrent['name'] ?? '')) ?></a></span>
                </div>
            <?php else: ?>
                <div class="st-meta mt-1"><?= $lang->upload['subtitle'] ?></div>
            <?php endif; ?>
        </div>
        <?php if ($isEdit): ?>
            <a class="btn btn-sm btn-outline-secondary st-pill" href="<?= htmlspecialchars(get_torrent_link($EditTorrentID)) ?>">
                <i class="fa-solid fa-eye me-1"></i><?= $lang->upload['upload_view'] ?>
            </a>
        <?php endif; ?>
    </header>

    <!-- KPI tiles -->
    <div class="up-kpis mb-3">
        <?php foreach ($upKpis as [$kpiIcon, $kpiTone, $kpiVal, $kpiLabel, $kpiId]): ?>
            <div class="st-kpi st-kpi--muted">
                <span class="st-ico st-soft-<?= $kpiTone ?>"><i class="fa-solid <?= $kpiIcon ?>"></i></span>
                <div class="min-w-0">
                    <div class="st-kpi__val"<?= $kpiId !== '' ? ' id="' . $kpiId . '"' : '' ?>><?= $kpiVal ?></div>
                    <div class="st-kpi__lbl"><?= $kpiLabel ?></div>
                </div>
                <i class="fa-solid <?= $kpiIcon ?> st-kpi__bg" aria-hidden="true"></i>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Announce URL -->
    <div class="st-card up-announce mb-3">
        <span class="st-ico st-soft-info"><i class="fa-solid fa-circle-info"></i></span>
        <div class="flex-grow-1 min-w-0">
            <strong><?= $lang->upload['announce_important'] ?></strong>
            <?= sprintf($lang->upload["title2"], '<code id="announceUrl">' . htmlspecialchars($AnnounceURL) . '</code>') ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary st-pill flex-shrink-0" id="announceCopyBtn"
                data-ok="<?= htmlspecialchars($lang->upload['announce_copied']) ?>"
                data-fail="<?= htmlspecialchars($lang->upload['announce_copy_failed']) ?>">
            <i class="fa-solid fa-copy me-1"></i><?= $lang->upload['announce_copy'] ?>
        </button>
    </div>


    <form method="post" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']) . ($EditTorrent ? '?id=' . urlencode((string)$EditTorrentID) : ''); ?>" id="torrent-upload-form" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="my_post_key" value="<?php echo $mybb->post_code; ?>" />
    <?php
    // JS strings for upload_torrent.js (js_ut_*) and tags_category.js (js_tc_*): js_* keys of the upload lang -> AGS_LANG without the prefix
    // (const AGS_LANG must exist only once on the page)
    $ags_lang = [];
    foreach ((array)$lang->upload as $ags_key => $ags_val) {
        if (str_starts_with((string)$ags_key, 'js_')) {
            $ags_lang[substr((string)$ags_key, 3)] = $ags_val;
        }
    }
    ?>
    <script>const AGS_LANG = <?= json_encode($ags_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script>var myPostKey = <?= json_encode($mybb->post_code) ?>; var maxTorrentMb = <?= (int)UPLOAD_MAX_TORRENT_MB ?>; var existingScreenshotsCount = <?= count($screenshots ?? []) ?>; var maxScreenshotsLimit = <?= (int)$max_screenshots ?>;</script>

    <!-- Скрытый контейнер для добавления file_ids -->
    <div id="fileIdsContainer"></div>

    <div class="d-flex flex-column gap-3">

    <!-- Torrent -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-magnet"></i></span>
            <?= $lang->upload['torrent_info'] ?>
        </div>
        <div class="st-card__body">

            <!-- Name -->
            <div class="mb-3">
                <label for="formName" class="form-label fw-semibold">
                    <?= $lang->upload['torrent_name'] ?> <span class="text-danger">*</span>
                </label>
                <div class="input-group has-validation st-subject">
                    <span class="input-group-text"><i class="fa-solid fa-heading"></i></span>
                    <input class="form-control" type="text" id="formName" name="formName"
                           placeholder="<?= $lang->upload['torrent_name_hint'] ?>"
                           required minlength="3" maxlength="255"
                           value="<?= isset($torrent['name']) ? htmlspecialchars($torrent['name']) : '' ?>" />
                    <div class="invalid-feedback"><?= $lang->upload['torrent_name_invalid'] ?></div>
                </div>
                <div class="d-flex justify-content-end mt-1">
                    <small class="st-meta"><span id="formNameCharCount">0</span>/255</small>
                </div>
            </div>

            <!-- Torrent file -->
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <?= $lang->upload['torrent_file'] ?>
                    <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
                </label>

                <div id="torrentDropZone" class="up-drop">
                    <div id="torrentDropContent">
                        <span class="st-ico up-drop__ico"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <div class="fw-semibold mb-1"><?= $lang->upload['torrent_drop_title'] ?></div>
                        <div class="st-meta mb-2"><?= $lang->upload['torrent_drop_sub'] ?></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary st-pill">
                            <i class="fa-solid fa-folder-open me-1"></i><?= $lang->upload['torrent_browse'] ?>
                        </button>
                    </div>

                    <div id="torrentFileSelected" style="display:none;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <span class="st-ico st-soft-primary"><i class="fa-solid fa-file-lines"></i></span>
                            <div class="text-start min-w-0">
                                <div class="fw-bold up-break" id="torrentSelectedName">filename.torrent</div>
                                <div class="st-meta" id="torrentSelectedSize">0 KB</div>
                                <span class="st-badge st-soft-success mt-1" id="torrentFileBadge">
                                    <i class="fa-solid fa-check"></i><?= $lang->upload['torrent_ready'] ?>
                                </span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger st-pill" id="torrentRemoveBtn" onclick="removeTorrentFile()">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <input class="d-none" type="file" id="torrentFile" name="torrentFile" accept=".torrent" />
                </div>

                <div class="text-danger small mt-1" id="torrentFileError" style="display:none;"><?= $lang->upload['torrent_file_required'] ?></div>

                <div id="torrentUploadProgress" class="mt-2" style="display:none;">
                    <div class="progress up-progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%;"></div>
                    </div>
                    <small class="st-meta"><?= $lang->upload['torrent_validating'] ?></small>
                </div>

                <div class="st-meta mt-1">
                    <?= $isEdit ? $lang->upload['torrent_file_hint_edit'] : $lang->upload['torrent_file_hint'] ?>
                    <span id="torrentFileInfo"></span>
                </div>
                <div class="mt-2" id="torrentFilePreview"></div>
            </div>

            <!-- Метаданные торрента -->
            <div id="torrentMetaInfo" class="up-sub mb-3" style="display:none;">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="st-badge st-soft-primary"><i class="fa-solid fa-hard-drive"></i><?= $lang->upload['torrent_size'] ?>: <strong id="torrentMetaSize">—</strong></span>
                    <span class="st-badge st-soft-info"><i class="fa-solid fa-file"></i><?= $lang->upload['torrent_files'] ?>: <strong id="torrentMetaFiles">—</strong></span>
                    <span class="st-badge st-soft-muted"><i class="fa-solid fa-fingerprint"></i><?= $lang->upload['torrent_hash'] ?>: <strong id="torrentMetaHash">—</strong></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="fw-semibold st-meta"><i class="fa-solid fa-list me-1"></i><?= $lang->upload['torrent_file_list'] ?></small>
                    <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="toggleTorrentFileList()">
                        <i class="fa-solid fa-eye me-1" id="torrentFileListToggleIcon"></i>
                        <span id="torrentFileListToggleText" data-show="<?= htmlspecialchars($lang->upload['torrent_show']) ?>" data-hide="<?= htmlspecialchars($lang->upload['torrent_hide']) ?>"><?= $lang->upload['torrent_show'] ?></span>
                    </button>
                </div>
                <div id="torrentFileList" class="up-filelist mt-2" style="display:none;"></div>
            </div>

            <!-- NFO -->
            <div>
                <label for="nfoFile" class="form-label fw-semibold">
                    <?= $lang->upload['nfofile'] ?>
                    <span class="st-meta fw-normal"><?= $lang->upload['nfofile_optional'] ?></span>
                </label>
                <div class="input-group">
                    <input class="form-control" type="file" id="nfoFile" name="nfoFile" accept=".nfo,text/plain" />
                    <button type="button" class="btn btn-outline-secondary" id="nfoPreviewBtn" style="display:none;" onclick="toggleNfoPreview()">
                        <i class="fas fa-eye me-1"></i><?= $lang->upload['nfofile_preview'] ?>
                    </button>
                </div>
                <div class="st-meta mt-1"><?= $lang->upload['nfofile_hint'] ?></div>

                <div id="nfoPreviewContainer" class="up-sub p-0 mt-2" style="display:none;">
                    <div class="up-nfo-head">
                        <span class="st-meta up-break"><i class="fa-solid fa-file-lines me-1"></i><span id="nfoPreviewFilename">file.nfo</span></span>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="st-badge st-soft-muted" id="nfoPreviewSize"></span>
                            <span class="st-badge st-soft-muted" id="nfoPreviewLines"></span>
                            <button type="button" class="st-icon-btn border-0" onclick="toggleNfoPreview()"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                    <pre id="nfoPreviewText" class="up-nfo"></pre>
                </div>

                <?php if ($isEdit):
                    $existingNfoQ = $db->sql_query_prepared("SELECT id FROM torrents_nfo WHERE torrent_id = ?", [$EditTorrentID]);
                    $existingNfoRow = $existingNfoQ ? $db->fetch_array($existingNfoQ) : null;
                    if ($existingNfoRow): ?>
                        <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                            <span class="st-badge st-soft-success"><i class="fa-solid fa-check"></i><?= $lang->upload['nfofile_uploaded'] ?></span>
                            <small class="st-meta"><?= $lang->upload['nfofile_replace'] ?></small>
                        </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </section>

    <!-- Description -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-align-left"></i></span>
            <?= $lang->upload['description'] ?> <span class="text-danger">*</span>
        </div>
        <div class="st-card__body">
            <?= $editor['toolbar'] ?>
            <textarea class="form-control st-editor-text" id="description" name="description" rows="10" required
                      placeholder="<?= $lang->upload['description_placeholder'] ?>"><?= isset($torrent['descr']) ? htmlspecialchars($torrent['descr']) : '' ?></textarea>
            <div class="st-meta text-end mt-1"><span id="charCount2">0 / 500</span></div>
            <div class="invalid-feedback"><?= $lang->upload['description_invalid'] ?></div>
        </div>
    </section>

    <!-- MediaInfo (необязательно) -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-circle-info"></i></span>
            MediaInfo
            <span class="st-meta fw-normal"><?= htmlspecialchars($lang->upload['tags_optional'] ?? '(optional)') ?></span>
        </div>
        <div class="st-card__body">
            <textarea class="form-control font-monospace up-mi-text" id="mediainfo" name="mediainfo" rows="6" spellcheck="false"
                      placeholder="General&#10;Format                                   : Matroska&#10;..."><?= htmlspecialchars($torrent_mediainfo ?? '') ?></textarea>
            <div class="st-meta mt-1"><?= htmlspecialchars($lang->upload['mediainfo_hint'] ?? 'Optional: paste the MediaInfo text report (View → Text). It is shown as a neat card on the torrent page.') ?></div>
            <?php
                $mu = static fn(string $k, string $d): string => htmlspecialchars((string)($lang->upload[$k] ?? $d));
                $mi_opts = static function (array $list): string {
                    $o = '<option value=""></option>';
                    foreach ($list as $v) {
                        $o .= '<option>' . htmlspecialchars($v) . '</option>';
                    }
                    return $o;
                };
            ?>
              <button type="button" class="btn btn-sm btn-outline-primary st-pill mt-2" id="miBuilderToggle" aria-expanded="false" aria-controls="miBuilder">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= $mu('btn_mi_builder', 'Fill in by template') ?>
              </button>

              <div class="up-sub up-mib mt-2 d-none" id="miBuilder">
                <div class="st-meta mb-3"><?= $mu('mi_builder_hint', 'Fill in the fields — the text above is generated automatically and is always shown as a card.') ?></div>

                <div class="row g-2 mb-3">
                  <div class="col-6 col-md-3">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_quality', 'Quality') ?></label>
                    <input type="text" class="form-control form-control-sm" data-mi="quality" list="miQualityList" placeholder="WEB-DL 1080p">
                    <datalist id="miQualityList">
                      <option value="WEB-DL 2160p"><option value="WEB-DL 1080p"><option value="WEBRip 1080p"><option value="BDRip 1080p">
                      <option value="BDRemux 1080p"><option value="UHD BDRemux 2160p"><option value="HDTV 1080p"><option value="DVDRip">
                    </datalist>
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_container', 'Container') ?></label>
                    <select class="form-select form-select-sm" data-mi="container"><?= $mi_opts(['MKV', 'MP4', 'AVI', 'TS', 'M2TS', 'ISO']) ?></select>
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_duration', 'Duration') ?></label>
                    <input type="text" class="form-control form-control-sm" data-mi="duration" placeholder="02:11:45">
                  </div>
                  <div class="col-6 col-md-3">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_size', 'Size') ?></label>
                    <div class="input-group input-group-sm">
                      <input type="text" class="form-control" data-mi="size" inputmode="decimal" placeholder="18.4">
                      <select class="form-select flex-grow-0 w-auto" data-mi="sizeunit" aria-label="<?= $mu('lbl_mib_size', 'Size') ?>">
                        <option>GB</option><option>MB</option><option>TB</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="fw-semibold small mb-2"><i class="fa-solid fa-film me-1 text-primary"></i><?= $mu('lbl_mi_video', 'Video') ?></div>
                <div class="row g-2 mb-3">
                  <div class="col-6 col-md-2">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_codec', 'Codec') ?></label>
                    <select class="form-select form-select-sm" data-mi="vcodec"><?= $mi_opts(['HEVC', 'AVC', 'x265', 'x264', 'AV1', 'VP9', 'MPEG-2', 'XviD']) ?></select>
                  </div>
                  <div class="col-6 col-md-2">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_resolution', 'Resolution') ?></label>
                    <input type="text" class="form-control form-control-sm" data-mi="vres" list="miResList" placeholder="1920x1080">
                    <datalist id="miResList">
                      <option value="3840x2160"><option value="3840x1608"><option value="1920x1080"><option value="1920x800">
                      <option value="1280x720"><option value="720x576"><option value="720x480">
                    </datalist>
                  </div>
                  <div class="col-4 col-md-2">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_aspect', 'Aspect') ?></label>
                    <select class="form-select form-select-sm" data-mi="vaspect" data-auto-label="<?= $mu('opt_mib_auto', 'Auto') ?>"><?= str_replace('<option value=""></option>', '<option value="">' . $mu('opt_mib_auto', 'Auto') . '</option>', $mi_opts(['4:3', '16:9', '1.85:1', '2.00:1', '2.20:1', '2.39:1', '2.40:1', '21:9'])) ?></select>
                  </div>
                  <div class="col-4 col-md-2">
                    <label class="form-label small mb-1">FPS</label>
                    <select class="form-select form-select-sm" data-mi="vfps"><?= $mi_opts(['23.976', '24', '25', '29.970', '30', '50', '59.940', '60']) ?></select>
                  </div>
                  <div class="col-4 col-md-2">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_bitrate', 'Bitrate') ?></label>
                    <div class="input-group input-group-sm">
                      <input type="text" class="form-control" data-mi="vbitrate" inputmode="decimal" placeholder="24.3">
                      <span class="input-group-text">Mbps</span>
                    </div>
                  </div>
                  <div class="col-6 col-md-2">
                    <label class="form-label small mb-1"><?= $mu('lbl_mib_depth', 'Bit depth') ?></label>
                    <select class="form-select form-select-sm" data-mi="vdepth"><?= $mi_opts(['8 bit', '10 bit', '12 bit']) ?></select>
                  </div>
                  <div class="col-12">
                    <span class="small text-muted me-2">HDR:</span>
                    <?php foreach (['DV', 'HDR10+', 'HDR10', 'HLG'] as $hdr): ?>
                      <div class="form-check form-check-inline small mb-0">
                        <input class="form-check-input" type="checkbox" data-mi-hdr value="<?= $hdr ?>" id="miHdr<?= md5($hdr) ?>">
                        <label class="form-check-label" for="miHdr<?= md5($hdr) ?>"><?= $hdr ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="fw-semibold small mb-2"><i class="fa-solid fa-volume-high me-1 text-primary"></i><?= $mu('lbl_mi_audio', 'Audio') ?></div>
                <div id="miAudioRows"></div>
                <template id="miAudioTpl">
                  <div class="row g-2 mb-2 align-items-end" data-mi-audio>
                    <div class="col-6 col-md-2">
                      <label class="form-label small mb-1"><?= $mu('lbl_mib_lang', 'Language') ?></label>
                      <select class="form-select form-select-sm" data-mi-a="lang"><?= $mi_opts(['Rus', 'Eng', 'Ukr', 'Jpn', 'Kor', 'Chi', 'Fre', 'Ger', 'Spa', 'Ita']) ?></select>
                    </div>
                    <div class="col-6 col-md-2">
                      <label class="form-label small mb-1"><?= $mu('lbl_mib_codec', 'Codec') ?></label>
                      <select class="form-select form-select-sm" data-mi-a="codec"><?= $mi_opts(['AC3', 'E-AC3', 'E-AC3 Atmos', 'TrueHD', 'TrueHD Atmos', 'DTS', 'DTS-HD MA', 'DTS:X', 'AAC', 'FLAC', 'Opus', 'MP3', 'PCM']) ?></select>
                    </div>
                    <div class="col-4 col-md-2">
                      <label class="form-label small mb-1"><?= $mu('lbl_mib_channels', 'Channels') ?></label>
                      <select class="form-select form-select-sm" data-mi-a="ch"><?= $mi_opts(['1.0', '2.0', '5.1', '7.1']) ?></select>
                    </div>
                    <div class="col-8 col-md-2">
                      <label class="form-label small mb-1"><?= $mu('lbl_mib_bitrate', 'Bitrate') ?></label>
                      <div class="input-group input-group-sm">
                        <input type="text" class="form-control" data-mi-a="bitrate" inputmode="numeric" placeholder="384">
                        <span class="input-group-text">kbps</span>
                      </div>
                    </div>
                    <div class="col-10 col-md-3">
                      <label class="form-label small mb-1"><?= $mu('lbl_mib_voice', 'Voice-over / title') ?></label>
                      <input type="text" class="form-control form-control-sm" data-mi-a="title" placeholder="<?= $mu('ph_mib_voice', 'e.g. Dub, Original') ?>">
                    </div>
                    <div class="col-2 col-md-1 text-end">
                      <button type="button" class="btn btn-sm btn-outline-danger" data-mi-del title="<?= $mu('btn_mib_remove', 'Remove') ?>"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                  </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary st-pill mb-3" id="miAudioAdd">
                  <i class="fa-solid fa-plus me-1"></i><?= $mu('btn_mib_add_audio', 'Add audio track') ?>
                </button>

                <div class="fw-semibold small mb-2"><i class="fa-solid fa-closed-captioning me-1 text-primary"></i><?= $mu('lbl_mi_subs', 'Subtitles') ?></div>
                <div id="miSubRows"></div>
                <template id="miSubTpl">
                  <div class="row g-2 mb-2 align-items-center" data-mi-sub>
                    <div class="col-6 col-md-3">
                      <select class="form-select form-select-sm" data-mi-s="lang" aria-label="<?= $mu('lbl_mib_lang', 'Language') ?>">
                        <?= $mi_opts(['Russian', 'English', 'Ukrainian', 'Japanese', 'Korean', 'Chinese', 'French', 'German', 'Spanish', 'Italian', 'Polish', 'Portuguese', 'Turkish', 'Bulgarian']) ?>
                      </select>
                    </div>
                    <div class="col-6 col-md-2">
                      <select class="form-select form-select-sm" data-mi-s="format" aria-label="<?= $mu('lbl_mib_format', 'Format') ?>">
                        <?= $mi_opts(['SRT', 'ASS', 'PGS', 'VobSub']) ?>
                      </select>
                    </div>
                    <div class="col-10 col-md-6">
                      <?php foreach (['Forced', 'Full', 'SDH'] as $flag): ?>
                        <label class="form-check form-check-inline small mb-0">
                          <input class="form-check-input" type="checkbox" data-mi-flag value="<?= $flag ?>">
                          <span class="form-check-label"><?= $flag ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <div class="col-2 col-md-1 text-end">
                      <button type="button" class="btn btn-sm btn-outline-danger" data-mi-del title="<?= $mu('btn_mib_remove', 'Remove') ?>"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                  </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary st-pill" id="miSubAdd">
                  <i class="fa-solid fa-plus me-1"></i><?= $mu('btn_mib_add_sub', 'Add subtitles') ?>
                </button>
              </div>

        </div>
    </section>

    <!-- Category -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-table-cells-large"></i></span>
            <?= $lang->upload['category'] ?> <span class="text-danger">*</span>
        </div>
        <div class="st-card__body">
            <?php
            $category = isset($torrent['category']) ? intval($torrent['category']) : 0;

            if (!isset($_categoriesC) || !is_array($_categoriesC)) {
                require_once TSDIR . '/cache/categories.php';
            }

            $activeCatName = '';
            foreach ($_categoriesC as $c) {
                if ((int)$c['id'] === $category) {
                    $activeCatName = $c['name'];
                    break;
                }
            }
            ?>
            <input type="hidden" name="category" id="categorySelected" value="<?= $category ?>">

            <div class="category-icon-picker" id="categoryPicker">
                <?php foreach ($_categoriesC as $cat):
                    $isActive = ($category === (int)$cat['id']) ? 'active' : '';
                ?>
                <button type="button" class="cat-pick-btn <?= $isActive ?>"
                        data-id="<?= (int)$cat['id'] ?>"
                        data-name="<?= htmlspecialchars($cat['name']) ?>"
                        title="<?= htmlspecialchars($cat['name']) ?>"
                        onclick="selectCategory(this)">
                    <i class="<?= htmlspecialchars($cat['icon']) ?>"></i>
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <div id="categoryLabel" class="mt-2 small">
                <?php if ($category && $activeCatName): ?>
                    <i class="fas fa-check-circle text-success me-1"></i>
                    <strong><?= htmlspecialchars($activeCatName) ?></strong>
                <?php else: ?>
                    <span class="st-meta"><i class="fa-solid fa-hand-pointer me-1"></i><?= htmlspecialchars($lang->upload['category_click_select'] ?? 'Click a category to select') ?></span>
                <?php endif; ?>
            </div>

            <div class="text-danger small mt-1" id="categoryError" style="display:none;"><?= $lang->upload['category_invalid'] ?></div>
        </div>
    </section>

    <!-- Tags -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-tags"></i></span>
            <?= $lang->upload['tags'] ?>
            <span class="st-meta fw-normal"><?= htmlspecialchars($lang->upload['tags_optional'] ?? '(optional)') ?></span>
        </div>
        <div class="st-card__body">
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control" id="tags" name="tags"
                       placeholder="<?= $lang->upload['tags_placeholder'] ?>"
                       value="<?= htmlspecialchars($torrent['tags'] ?? '') ?>">
                <button type="button" class="btn btn-outline-secondary" onclick="clearAllTags()" title="<?= htmlspecialchars($lang->upload['tags_clear']) ?>">
                    <i class="fa-solid fa-eraser"></i>
                </button>
            </div>

            <div class="d-flex flex-wrap gap-1 mb-2" id="genreButtons">
                <?php
                // [тег (англ.), иконка, цвет]
                $genres = [
                    ['Action',      'fa-bolt',              '#ff4757'],
                    ['Adventure',   'fa-compass',           '#ffa502'],
                    ['Animation',   'fa-film',              '#7bed9f'],
                    ['Biography',   'fa-user-graduate',     '#70a1ff'],
                    ['Comedy',      'fa-face-laugh-squint', '#ff6b81'],
                    ['Crime',       'fa-gavel',             '#2f3542'],
                    ['Documentary', 'fa-video',             '#a4b0be'],
                    ['Drama',       'fa-masks-theater',     '#57606f'],
                    ['Family',      'fa-users',             '#ff7f50'],
                    ['Fantasy',     'fa-dragon',            '#dfe6e9'],
                    ['History',     'fa-landmark',          '#cd84f1'],
                    ['Horror',      'fa-ghost',             '#ff4d4d'],
                    ['Music',       'fa-music',             '#1e90ff'],
                    ['Mystery',     'fa-magnifying-glass',  '#8e44ad'],
                    ['Romance',     'fa-heart',             '#ff6b6b'],
                    ['Sci-Fi',      'fa-rocket',            '#00cec9'],
                    ['Sport',       'fa-trophy',            '#fdcb6e'],
                    ['Thriller',    'fa-skull',             '#e17055'],
                    ['War',         'fa-hand-fist',         '#636e72'],
                    ['Western',     'fa-horse-head',        '#f39c12'],
                ];

                $currentTags = array_map('trim', explode(',', $torrent['tags'] ?? ''));

                foreach ($genres as [$label, $genreIcon, $genreColor]):
                    // $label stays English (it is what goes into the tags field); $shown is the translated name
                    $shown  = $lang->upload['genre_' . strtolower(str_replace('-', '_', $label))] ?? $label;
                    $active = in_array($label, $currentTags, true) ? 'genre-active' : '';
                ?>
                <button type="button" class="genre-tag-btn <?= $active ?>" style="--g: <?= $genreColor ?>"
                        data-genre="<?= htmlspecialchars($label) ?>"
                        data-label="<?= htmlspecialchars($shown) ?>"
                        onclick="toggleGenreTag(this)">
                    <i class="fa-solid <?= $genreIcon ?>"></i><span><?= htmlspecialchars($shown) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="st-meta">
                <?= $lang->upload['tags_hint'] ?>
                <?php $fetchBtn = '<strong>' . $lang->upload['imdb_fetch'] . '</strong>'; // {1} (or %1$s after $lang->load()) = the button name ?>
                <?= strtr($lang->upload['tags_hint_imdb'] ?? 'Tags will be automatically filled from IMDb when you click the {1} button.',
                          ['{1}' => $fetchBtn, '%1$s' => $fetchBtn]) ?>
            </div>
        </div>
    </section>

    <!-- Media & Images -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-images"></i></span>
            <?= $lang->upload['media_section'] ?>
        </div>
        <div class="st-card__body">

            <!-- IMDb -->
            <div class="mb-3">
                <label for="imdbUrl" class="form-label fw-semibold">
                    <i class="fa-brands fa-imdb me-1"></i><?= $lang->upload['imdb_link'] ?>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                    <input type="url" class="form-control" id="imdbUrl" name="imdbUrl"
                           value="<?= htmlspecialchars($t_link) ?>"
                           placeholder="<?= $lang->upload['imdb_placeholder'] ?>">
                    <button type="button" class="btn btn-warning" id="imdbFetchBtn" onclick="fetchImdbData()">
                        <i class="fa-brands fa-imdb me-1"></i><?= $lang->upload['imdb_fetch'] ?>
                    </button>
                </div>
                <div class="st-meta mt-1"><?= $lang->upload['imdb_hint'] ?></div>

                <div id="imdbPreview" class="up-sub mt-2" style="display:none;">
                    <div class="d-flex gap-3 align-items-start">
                        <div id="imdbPosterPreview" class="up-imdb-poster" style="display:none;">
                            <img id="imdbPosterImg" src="" alt="">
                            <div class="d-grid gap-1 mt-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="applyImdbPoster('main')" title="<?= $lang->upload['imdb_set_main'] ?>">
                                    <i class="fa-solid fa-image me-1"></i><?= $lang->upload['imdb_poster_main'] ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="applyImdbPoster('secondary')" title="<?= $lang->upload['imdb_set_secondary'] ?>">
                                    <i class="fa-solid fa-images me-1"></i><?= $lang->upload['imdb_poster_secondary'] ?>
                                </button>
                            </div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold mb-1 up-break" id="imdbPreviewTitle">—</div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="st-badge st-soft-warning" id="imdbPreviewYear" style="display:none;"></span>
                                <span class="st-badge st-soft-muted" id="imdbPreviewGenre" style="display:none;"></span>
                                <span class="st-badge st-soft-success" id="imdbPreviewRating" style="display:none;"><i class="fa-solid fa-star"></i><span></span></span>
                            </div>
                            <p class="st-meta mb-2" id="imdbPreviewPlot"></p>
                            <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="applyImdbToDescription()">
                                <i class="fa-solid fa-paste me-1"></i><?= $lang->upload['imdb_add_description'] ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="imdbLoading" class="mt-2" style="display:none;">
                    <div class="d-flex align-items-center gap-2 st-meta">
                        <div class="spinner-border spinner-border-sm"></div>
                        <?= $lang->upload['imdb_fetching'] ?>
                    </div>
                </div>

                <div id="imdbError" class="alert alert-warning mt-2 small py-2 mb-0" style="display:none;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i><span id="imdbErrorText"></span>
                </div>
            </div>

            <!-- Posters -->
            <div class="row g-3">
                <?php foreach ([
                    1 => ['imageUrl',  'imagesUpload',  'imagePreview',  't_image',  'fa-image',  $lang->upload['image_main']],
                    2 => ['imageUrl2', 'imagesUpload2', 'imagePreview2', 't_image2', 'fa-images', $lang->upload['image_secondary']],
                ] as $slot => [$urlId, $fileId, $previewId, $column, $slotIcon, $slotTitle]): ?>
                <div class="col-md-6">
                    <div class="up-sub h-100">
                        <div class="fw-semibold mb-2"><i class="fa-solid <?= $slotIcon ?> me-2 opacity-75"></i><?= $slotTitle ?></div>

                        <div class="btn-group btn-group-sm w-100 up-seg mb-2" role="group">
                            <input type="radio" class="btn-check" name="uploadType<?= $slot ?>" id="uploadByUrl<?= $slot ?>" value="url" checked>
                            <label class="btn btn-outline-primary" for="uploadByUrl<?= $slot ?>"><i class="fa-solid fa-link me-1"></i><?= $lang->upload['image_url_mode'] ?></label>
                            <input type="radio" class="btn-check" name="uploadType<?= $slot ?>" id="uploadByFile<?= $slot ?>" value="file">
                            <label class="btn btn-outline-primary" for="uploadByFile<?= $slot ?>"><i class="fa-solid fa-upload me-1"></i><?= $lang->upload['image_file_mode'] ?></label>
                        </div>

                        <div id="uploadUrlGroup<?= $slot ?>">
                            <label for="<?= $urlId ?>" class="form-label small mb-1"><?= $lang->upload['image_url_label'] ?></label>
                            <input type="url" class="form-control form-control-sm" id="<?= $urlId ?>" name="<?= $urlId ?>"
                                   value="<?= htmlspecialchars($torrent[$column] ?? '') ?>"
                                   placeholder="<?= $lang->upload['image_url_placeholder'] ?>"
                                   oninput="updateImagePreviewFromUrl(this.value, '<?= $previewId ?>')">
                            <div class="st-meta mt-1"><?= $lang->upload['image_url_hint'] ?></div>
                        </div>

                        <div class="d-none" id="uploadFileGroup<?= $slot ?>">
                            <div class="upload-zone-sm" data-input="<?= $fileId ?>" data-preview="<?= $previewId ?>" data-radio="uploadByFile<?= $slot ?>" onclick="document.getElementById('<?= $fileId ?>').click()">
                                <i class="fa-solid fa-cloud-arrow-up mb-1"></i>
                                <div class="fw-semibold small"><?= $lang->upload['image_click_upload'] ?></div>
                                <div class="st-meta"><?= $lang->upload['image_drag_drop'] ?></div>
                                <input type="file" class="d-none" id="<?= $fileId ?>" name="<?= $fileId ?>" accept="image/*"
                                       onclick="event.stopPropagation()"
                                       onchange="handleImageUpload(this, '<?= $previewId ?>')">
                            </div>
                            <div class="st-meta mt-1"><?= $lang->upload['image_file_hint'] ?></div>
                        </div>

                        <div id="<?= $previewId ?>" class="preview-container">
                            <?php if (!empty($torrent[$column])): ?>
                                <div class="preview-item">
                                    <img src="<?= htmlspecialchars($torrent[$column]) ?>" class="preview-poster" alt="<?= htmlspecialchars($slotTitle) ?>"
                                         onerror="window.utPosterFallback && utPosterFallback(this)">
                                    <button type="button" class="delete-btn2" onclick="removeImagePreview('<?= $previewId ?>', '<?= $urlId ?>', '<?= $fileId ?>')"
                                            title="<?= htmlspecialchars($lang->upload['js_ut_image_remove']) ?>">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Settings -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-sliders"></i></span>
            <?= $lang->upload['settings_section'] ?>
        </div>
        <div class="st-card__body">
            <div class="up-opts">
                <?php
                // [name/id, value, отмечено?, иконка, подпись, подсказка, цвет]
                $upFlag = static fn(string $col): bool => ($torrent[$col] ?? '') === 'yes';
                $upOptions = [
                    ['anonymous',     'yes', isset($torrent['anonymous']) ? $torrent['anonymous'] === 'yes' : (($CURUSER['invisible'] ?? 0) == 1), 'fa-user-secret', $lang->upload['anonymous'], $lang->upload['anonymous_hint'], '#64748b'],
                    ['request',       'yes', $upFlag('isrequest'),     'fa-hand',          $lang->upload['request'],      $lang->upload['request_hint'], '#f97316'],
                    ['free',          'yes', $upFlag('free'),          'fa-gift',          $lang->upload['free'],         $lang->upload['free_hint'], '#16a34a'],
                    ['silver',        'yes', $upFlag('silver'),        'fa-star',          $lang->upload['silver'],       $lang->upload['silver_hint'], '#0ea5e9'],
                    ['doubleupload',  'yes', $upFlag('doubleupload'),  'fa-bolt',          $lang->upload['doubleupload'], $lang->upload['doubleupload_hint'], '#f59e0b'],
                    ['thirtypercent', 'yes', $upFlag('thirtypercent'), 'fa-percent',       $lang->upload['thirtypercent'] ?? '30% Leech', $lang->upload['thirtypercent_hint'] ?? 'Only 30% of downloaded data is counted toward this user\'s download stats.', '#8b5cf6'],
                    ['allowcomments', 'no',  ($torrent['allowcomments'] ?? '') === 'no', 'fa-comment-slash', $lang->upload['allowcomments'], $lang->upload['allowcomments_hint'], '#64748b'],
                    ['sticky',        'yes', $upFlag('sticky'),        'fa-thumbtack',     $lang->upload['sticky'],       $lang->upload['sticky_hint'], '#2563eb'],
                    ['isnuked',       'yes', $upFlag('isnuked'),       'fa-radiation',     $lang->upload['isnuked'],      $lang->upload['isnuked_hint'], '#dc2626'],
                ];
                foreach ($upOptions as [$optName, $optValue, $optOn, $optIcon, $optLabel, $optHint, $optColor]): ?>
                    <label class="st-check form-switch up-opt" for="<?= $optName ?>" style="--o: <?= $optColor ?>">
                        <input class="form-check-input" type="checkbox" role="switch" id="<?= $optName ?>" name="<?= $optName ?>" value="<?= $optValue ?>"<?= $optOn ? ' checked' : '' ?><?= $optName === 'isnuked' ? ' onclick="ShowHideField(\'nukereason\');"' : '' ?>>
                        <span class="min-w-0">
                            <span class="fw-semibold d-block up-opt__title"><i class="fa-solid <?= $optIcon ?> me-1"></i><?= $optLabel ?></span>
                            <span class="st-meta d-block"><?= $optHint ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="mt-2" id="nukereason" style="<?= $upFlag('isnuked') ? '' : 'display:none;' ?>">
                <label for="WhyNuked" class="form-label small fw-semibold mb-1"><?= $lang->upload['nuke_reason'] ?></label>
                <input type="text" class="form-control form-control-sm" id="WhyNuked" name="WhyNuked"
                       placeholder="<?= $lang->upload['nuke_reason_placeholder'] ?>"
                       value="<?= isset($torrent['WhyNuked']) ? htmlspecialchars($torrent['WhyNuked']) : '' ?>" />
                <div class="st-meta mt-1"><?= $lang->upload['nuke_reason_hint'] ?></div>
            </div>
        </div>
    </section>

    <!-- Screenshots -->
    <section class="st-card up-sec" style="--c: #3b82f6">
        <div class="st-card__head">
            <span class="st-ico up-sec__ico"><i class="fa-solid fa-camera"></i></span>
            <?= $lang->upload['screenshots'] ?>
        </div>
        <div class="st-card__body">
            <div class="btn-group btn-group-sm w-100 up-seg mb-2" role="group">
                <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByFile" value="file" checked>
                <label class="btn btn-outline-primary" for="screenshotByFile"><i class="fa-solid fa-upload me-1"></i><?= $lang->upload['screenshots_upload_files'] ?></label>
                <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByUrl" value="url">
                <label class="btn btn-outline-primary" for="screenshotByUrl"><i class="fa-solid fa-link me-1"></i><?= $lang->upload['screenshots_bulk_url'] ?></label>
            </div>

            <div id="screenshotFileGroup">
                <div class="upload-zone" onclick="document.getElementById('screenshotsUpload').click()">
                    <i class="fa-solid fa-cloud-arrow-up mb-1"></i>
                    <div class="fw-semibold"><?= $lang->upload['screenshots_drop'] ?></div>
                    <div class="st-meta"><?= $lang->upload['screenshots_multiple'] ?></div>
                    <input type="file" class="d-none" id="screenshotsUpload" name="screenshotsUpload[]" multiple accept="image/*"
                           onclick="event.stopPropagation()" data-max="<?= (int)$max_screenshots ?>">
                </div>
                <div id="screenshotsPreview" class="preview-container"></div>
            </div>

            <div id="screenshotUrlGroup" class="d-none">
                <label for="screenshotUrlsInput" class="form-label small mb-1">
                    <?= $lang->upload['screenshots_url_label'] ?>
                    <span class="st-meta"><?= $lang->upload['screenshots_url_hint'] ?></span>
                </label>
                <textarea id="screenshotUrlsInput" class="form-control font-monospace small" rows="6"
                          placeholder="https://example.com/screen1.jpg
https://example.com/screen2.png
https://example.com/screen3.webp"></textarea>
                <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                    <small class="st-meta"><?= $lang->upload['screenshots_url_supported'] ?></small>
                    <button type="button" class="btn btn-sm btn-outline-secondary st-pill" onclick="loadScreenshotUrls()">
                        <i class="fa-solid fa-eye me-1"></i><?= $lang->upload['screenshots_load_preview'] ?>
                    </button>
                </div>
                <div id="screenshotUrlPreview" class="preview-container"></div>
                <div id="screenshotUrlInputs"></div>
            </div>

            <?php if (!empty($screenshots)): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div class="fw-semibold">
                        <?= $lang->upload['screenshots_existing'] ?>
                        <span class="st-badge st-soft-muted ms-1"><span id="upShotsBadge"><?= count($screenshots) ?> / <?= (int)$max_screenshots ?></span></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="selectAllScreenshotsCheckbox">
                            <label class="form-check-label small" for="selectAllScreenshotsCheckbox"><?= $lang->upload['screenshots_select_all'] ?? 'Select all' ?></label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger st-pill d-none" id="deleteSelectedScreenshotsBtn">
                            <i class="fa-solid fa-trash-can me-1"></i><?= $lang->upload['screenshots_delete_selected'] ?? 'Delete selected' ?> (<span id="selectedScreenshotsCount">0</span>)
                        </button>
                    </div>
                </div>
                <div id="existingScreenshots" class="preview-container">
                    <?php foreach ($screenshots as $shot): ?>
                        <div class="screenshot-item" data-id="<?= (int)$shot['id'] ?>">
                            <input type="checkbox" class="form-check-input screenshot-select-checkbox position-absolute" data-id="<?= (int)$shot['id'] ?>">
                            <img src="/torrents/screens/<?= htmlspecialchars($shot['filename']) ?>" class="preview-screenshot" alt=""
                                 onerror="window.utShotFallback && utShotFallback(this)">
                            <button type="button" class="delete-btn" title="<?= htmlspecialchars($lang->upload['screenshots_delete_title']) ?>">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    </div>

    <?php if ($isEdit): ?>
        <input type="hidden" name="EditTorrent" value="1">
        <input type="hidden" name="EditTorrentID" value="<?= htmlspecialchars((string)$EditTorrentID) ?>">
    <?php endif; ?>

    <!-- Submit -->
    <div class="st-submitbar">
        <span class="st-meta"><i class="fa-solid fa-circle-info me-1"></i><?= $lang->upload['upload_size_note'] ?></span>
        <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid <?= $buttonIcon ?> me-1"></i><?= $buttonText ?></button>
    </div>

    </form>

    <?= $editor['modal'] ?>


    <!-- ═══ Модалки ═══════════════════════════════════════════════════════ -->

    <!-- Error -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header up-modal-head">
                    <span class="st-ico st-soft-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <h5 class="modal-title" id="errorModalLabel"><?= $lang->upload['error_title'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="st-meta fw-semibold mb-2"><i class="fa-solid fa-bug me-1"></i><?= $lang->upload['error_details'] ?? 'Error details' ?></div>
                    <div id="errorModalBody" class="up-err"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="st-meta"><i class="fa-solid fa-circle-info me-1"></i><?= $lang->upload['error_check_input'] ?? 'Check your input and try again' ?></span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary st-pill" data-bs-dismiss="modal"><?= $lang->upload['error_close'] ?></button>
                        <button type="button" class="btn btn-sm btn-danger st-pill ms-1" id="copyErrorBtn"><i class="fa-solid fa-copy me-1"></i><?= $lang->upload['error_copy'] ?? 'Copy error' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload complete (зелёная, с анимацией галочки) -->
    <div class="modal fade" id="uploadCompleteModal" tabindex="-1" aria-labelledby="uploadCompleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 up-done">
                <div class="modal-header bg-success text-white border-0">
                    <div class="d-flex align-items-center w-100">
                        <i class="fa-solid fa-circle-check fa-2x me-3"></i>
                        <h5 class="modal-title mb-0" id="uploadCompleteModalLabel"><?= $lang->upload['upload_success_title'] ?></h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="success-animation mb-3">
                        <i class="fa-solid fa-check text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="text-success mb-2"><?= $lang->upload['upload_success_congrats'] ?></h4>
                    <p class="text-muted mb-0"><?= $lang->upload['upload_success_text'] ?></p>
                    <p class="text-muted"><?= $lang->upload['upload_success_redirect'] ?></p>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-2"></i><?= $lang->upload['upload_stay'] ?>
                    </button>
                    <button type="button" class="btn btn-success" onclick="redirectToTorrent()">
                        <i class="fa-solid fa-eye me-2"></i><?= $lang->upload['upload_view'] ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload progress (старая: градиентная шапка, спиннер со свечением, точки) -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-gradient-primary text-white border-0">
                    <div class="d-flex align-items-center w-100">
                        <i class="fa-solid fa-cloud-arrow-up fa-2x me-3"></i>
                        <h5 class="modal-title mb-0" id="uploadModalLabel"><?= $lang->upload['upload_processing'] ?></h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <div class="upload-spinner mb-4">
                        <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
                            <span class="visually-hidden"><?= $lang->upload['js_ut_loading'] ?></span>
                        </div>
                        <div class="spinner-glow"></div>
                    </div>

                    <h6 class="text-primary mb-2"><?= $lang->upload['upload_wait'] ?></h6>
                    <p class="text-muted mb-3"><?= $lang->upload['upload_wait_sub'] ?></p>

                    <div class="loading-dots mb-3">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </div>

                    <div class="timer-display mb-3">
                        <span class="badge bg-body-tertiary text-body fs-6">
                            <i class="fa-solid fa-clock me-2"></i><span id="uploadTimer">0</span> <?= $lang->upload['upload_timer'] ?>
                        </span>
                    </div>

                    <div class="progress-container">
                        <div class="progress mt-3 up-upl-progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" role="progressbar"
                                 style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="progress-text mt-2">
                            <small class="text-muted" id="uploadStatusText"><?= $lang->upload['upload_init'] ?></small>
                            <small class="text-primary fw-bold" id="progressPercentage">0%</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><?= $lang->upload['upload_size_note'] ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete one screenshot -->
    <div class="modal fade" id="deleteScreenshotModal" tabindex="-1" aria-labelledby="deleteScreenshotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header up-modal-head">
                    <span class="st-ico st-soft-danger"><i class="fa-solid fa-trash-can"></i></span>
                    <h5 class="modal-title" id="deleteScreenshotModalLabel"><?= $lang->upload['screenshots_delete_title'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="fw-semibold" id="deleteScreenshotTitle"><?= $lang->upload['screenshots_delete_title'] ?></div>
                    <div class="st-meta up-break mb-2" id="deleteScreenshotFilename"></div>

                    <div id="deleteScreenshotPreviewContainer" class="text-center mb-2">
                        <img id="deleteScreenshotImage" src="" alt="" class="up-del-img" style="display:none;">
                        <div id="noImagePreview" class="up-noprev" style="display:none;">
                            <i class="fa-regular fa-image"></i>
                            <span class="st-meta"><?= $lang->upload['screenshots_no_preview'] ?></span>
                        </div>
                    </div>

                    <div class="st-meta up-break mb-2"><i class="fa-solid fa-file-image me-1"></i><span id="deleteScreenshotFileName">screenshot.jpg</span></div>

                    <div class="alert alert-warning small py-2 mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= $lang->upload['screenshots_delete_warning'] ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal"><?= $lang->global['cancel'] ?></button>
                    <button type="button" class="btn btn-danger st-pill" id="confirmDeleteScreenshotBtn"><i class="fa-solid fa-trash-can me-1"></i><?= $lang->upload['screenshots_delete_confirm'] ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete selected screenshots -->
    <div class="modal fade" id="bulkDeleteScreenshotsModal" tabindex="-1" aria-labelledby="bulkDeleteScreenshotsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header up-modal-head">
                    <span class="st-ico st-soft-danger"><i class="fa-solid fa-trash-can"></i></span>
                    <h5 class="modal-title" id="bulkDeleteScreenshotsModalLabel"><?= $lang->upload['screenshots_bulk_delete_title'] ?? 'Delete screenshots?' ?> <span class="st-badge st-soft-danger" id="bulkDeleteScreenshotsCount">0</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <div id="bulkDeleteScreenshotsPreviewLabel" class="st-meta fw-semibold mb-1 d-none"></div>
                    <div id="bulkDeleteScreenshotsPreview" class="d-flex flex-wrap gap-2 mb-2"></div>
                    <div class="alert alert-warning small py-2 mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= $lang->upload['screenshots_delete_warning'] ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal"><?= $lang->global['cancel'] ?></button>
                    <button type="button" class="btn btn-danger st-pill" id="confirmBulkDeleteScreenshotsBtn"><i class="fa-solid fa-trash-can me-1"></i><?= $lang->upload['screenshots_delete_confirm'] ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Screenshots preview -->
    <div class="modal fade" id="screenshotPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header up-modal-head">
                    <span class="st-ico st-soft-primary"><i class="fa-solid fa-images"></i></span>
                    <h5 class="modal-title"><?= $lang->upload['screenshots'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->upload['error_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <div id="modalPreviewGrid" class="row g-2"></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="st-meta" id="modalPreviewCount"></span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger st-pill me-1" onclick="clearScreenshots()"><?= $lang->upload['tags_clear'] ?></button>
                        <button type="button" class="btn btn-sm btn-primary st-pill" data-bs-dismiss="modal" onclick="showSelectedPreviews()"><i class="fa-solid fa-check me-1"></i><?= $lang->upload['screenshots_confirm'] ?? 'Confirm' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.up-page -->

<script src="<?= $BASEURL ?>/scripts/swal-notify.js?ver=2"></script>
<script src="<?= $BASEURL ?>/scripts/Sortable.min.js"></script>
<script src="<?= $BASEURL ?>/scripts/upload_torrent.js?ver=75"></script>
<script src="<?= $BASEURL ?>/scripts/tags_category.js?ver=22"></script>
<script src="<?= $BASEURL ?>/scripts/mediainfo_builder.js?ver=4"></script>

<?php

stdfoot();
