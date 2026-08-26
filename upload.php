<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define("SCRIPTNAME", "upload.php");
require_once 'global.php';


if (empty($CURUSER['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    print_no_permission();
}



require(INC_PATH . '/functions_category.php');
require_once INC_PATH.'/datahandler.php';

require_once INC_PATH . '/functions_notify.php';




require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;




require_once INC_PATH . '/editor.php';

$editor = insert_bbcode_editor($smilies, $BASEURL, 'description');















// ✅ Обработчик удаления скрина
if (isset($_POST['action']) && $_POST['action'] === 'delete_screenshot') 
{
    if (empty($_POST['screenshot_id'])) 
    {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
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
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/' . $row['filename'];
        if (file_exists($filePath)) 
        {
            unlink($filePath);
        }

        $db->sql_query_prepared("DELETE FROM screenshots WHERE id = ?", [$screenshot_id]);

        echo json_encode(['success' => true]);
        exit;
    } 
    else 
    {
        echo json_encode(['success' => false, 'error' => 'Screenshot not found']);
        exit;
    }
}


// Переупорядочивание скриншотов
if (isset($_POST['action']) && $_POST['action'] === 'reorder_screenshots')
{
    if (empty($_POST['order']) || !is_array($_POST['order'])) {
        echo json_encode(['success' => false, 'error' => 'No order provided']);
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
        echo json_encode(['exists' => false, 'error' => 'No hash provided']);
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
        echo json_encode(['success' => false, 'error' => 'No URL provided']);
        exit;
    }

    // Валидация URL
    if (!preg_match('@^https?://www\.imdb\.com/title/tt\d+/@i', $imdb_url)) {
        echo json_encode(['success' => false, 'error' => 'Invalid IMDb URL']);
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
    echo json_encode(["success" => false, "error" => "Invalid security token. Please refresh the page and try again."]);
    exit;
}



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
    echo json_encode(["success" => false, "error" => "Torrent file is required for new uploads."]);
    exit;
}





// Save uploaded torrent file
$torrentFilename = null;
$info_hash = '';
$size = 0;
$numfiles = 0;





if (!$isEdit || ($torrentFile && $torrentFile['error'] === UPLOAD_ERR_OK)) 
{
    // Загружен новый файл
    $originalTorrentFilename = basename($torrentFile['name']); // оригинальное имя для БД/отображения
    $torrentFilename = saveFile($torrentFile, $torrentDir, ['application/x-bittorrent']);
    if (!$torrentFilename) 
	{
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid or unsupported torrent file format."]);
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
        echo json_encode(["success" => false, "error" => "Existing torrent file not found at path: " . $torrentPath]);
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
        $errors[] = sprintf('Your group allows maximum %d screenshots. You tried to upload %d.', $max_screenshots, $uploaded_count);
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
            $screenshotFilenames[] = $filename;
        }
    }
	
	}
}















// Description
$description = trim($_POST['description'] ?? '');
if (empty($description)) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Description is required."]);
    exit;
}

// Torrent Name
$torrentName = trim($_POST['formName'] ?? '');
if (empty($torrentName)) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Torrent name is required."]);
    exit;
}

// Category
$category = isset($_POST['category']) ? (int) $_POST['category'] : 0;
if ($category <= 0) 
{
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Category is required."]);
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
            echo json_encode(["success" => false, "error" => "You do not have permission to edit this torrent."]);
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
		
		kps('+', $kpsupload, $CURUSER['id']);
		
		
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
        echo json_encode(["success" => false, "error" => "Failed to rename the torrent file."]);
        exit;
    }
}














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
        $extByType = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

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
                // File successfully saved and renamed
                // You can now use $imageFilename or $targetPath
				
				$NewImageURL = 'torrents/images/' . $NewTID . '.' . $ext;
				
				$db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL, $NewTID]);
				
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
        $extByType = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

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
                // File successfully saved and renamed
                // You can now use $imageFilename2 or $targetPath
				
				
				$NewImageURL2 = 'torrents/images/' . $NewTID . '_2.' . $ext;
				
				$db->sql_query_prepared("UPDATE torrents SET t_image2 = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL2, $NewTID]);
				
				
				
				
            } 
            else 
            {
                // Handle error: unable to move uploaded file
                $imageFilename2 = null;
            }
        }
    }
}








$errors = [];

// === IMAGE FROM URL 1 ===
if (!empty($_POST['imageUrl'])) 
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
                $NewImageURL = 'torrents/images/' . $imageName;
                $db->sql_query_prepared("UPDATE torrents SET t_image = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL, $NewTID]);
            } 
			else 
			{
                $errors[] = "Image from URL (imageUrl) could not be saved.";
            }
        } 
		else 
		{
            $errors[] = "Could not download image from imageUrl.";
        }
    } 
	else 
	{
        $errors[] = "Invalid image URL (imageUrl).";
    }
}

// === IMAGE FROM URL 2 ===
if (!empty($_POST['imageUrl2'])) 
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
                $NewImageURL2 = 'torrents/images/' . $imageName2;
                $db->sql_query_prepared("UPDATE torrents SET t_image2 = ? WHERE id = ?", [$BASEURL . '/' . $NewImageURL2, $NewTID]);
            } 
			else 
			{
                $errors[] = "Image from URL (imageUrl2) could not be saved.";
            }
        } 
		else 
		{
            $errors[] = "Could not download image from imageUrl2.";
        }
    } 
	else 
	{
        $errors[] = "Invalid image URL (imageUrl2).";
    }
}


// Optional: log metadata
//file_put_contents(__DIR__ . '/upload_log.json', json_encode($metadata, JSON_PRETTY_PRINT), FILE_APPEND);

// === JSON Response ===
// Проверяем ошибки


// Screenshot URLs (bulk URL upload)
if (!empty($_POST['screenshot_urls']) && is_array($_POST['screenshot_urls'])) {
    foreach ($_POST['screenshot_urls'] as $screenshotUrl) {
        $screenshotUrl = filter_var(trim($screenshotUrl), FILTER_VALIDATE_URL);
        if (!$screenshotUrl) continue;
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $screenshotUrl)) continue;

        $ext = strtolower(pathinfo(parse_url($screenshotUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

        // Используем include из functions_ts_remote_connect.php если есть
        // иначе file_get_contents с подавлением ошибок
        $imgData = false;
        if (file_exists(INC_PATH . '/functions_remote_connect.php')) {
            include_once(INC_PATH . '/functions_remote_connect.php');
            $imgData = fetch_remote_file($screenshotUrl, false);
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout'         => 10,
                    'follow_location' => true,
                    'user_agent'      => 'Mozilla/5.0',
                ]
            ]);
            $imgData = @file_get_contents($screenshotUrl, false, $context);
        }

        if (!$imgData) continue;

        $count    = get_next_screenshot_number($NewTID, $db, 3);
        $filename = $NewTID . '_' . $count . '.' . $ext;
        $filePath = rtrim($screenshotDir, '/') . '/' . $filename;

        if (@file_put_contents($filePath, $imgData)) {
            $db->sql_query_prepared(
                "INSERT INTO screenshots (`torrent_id`,`filename`,`uploaded_at`) VALUES (?,?,?)",
                [$NewTID, $filename, TIMENOW]
            );
        }
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





// Определяем режим (у тебя уже есть эти переменные)
$isEdit = isset($EditTorrent) && !empty($EditTorrentID);
$headingText = $isEdit ? $lang->upload['head_edit'] : $lang->upload['head'];

// Заголовок: иконка + стиль
if ($isEdit) 
{
    $icon = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>'; // иконка
    $style = 'style="font-weight: 700; color: #0d6efd;"'; // синий заголовок
    $buttonIcon = '<i class="fa-solid fa-pen-to-square me-1"></i>'; // оранжевая иконка для кнопки
    $buttonText = $lang->upload['btn_update'];
}
else 
{
    $icon = '<i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>'; // синяя иконка
    $style = 'style="font-weight: 700; color: #0d6efd;"'; // синий заголовок
    $buttonIcon = '<i class="fa-solid fa-upload me-1"></i>'; // синяя иконка для кнопки
    $buttonText = $lang->upload['btn_upload'];
}



// Формируем финальный текст кнопки
$buttonFullText = $buttonIcon . $buttonText;






$t_link = $torrent['t_link'] ?? '';
if ($t_link && preg_match('@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result)) 
{
    $t_link = $result[0];
    unset($result);
}





$AnnounceURL = trim($announce_urls[0] . "?passkey=" . $CURUSER["passkey"]);

echo '
<div class="container mt-3">
<div class="alert d-flex align-items-start flex-column flex-md-row" style="background-color: #e8f4fd; color: #084298; border: 1px solid #b6e0fe;">
  <i class="fa-solid fa-circle-info fa-lg me-2 mt-1"></i>
  <div class="flex-grow-1">
    <strong>'.$lang->upload['announce_important'].'</strong> ' . sprintf($lang->upload["title2"], '<code id="announceUrl">' . $AnnounceURL . '</code>') . '
  </div>
  <button type="button" class="btn btn-sm btn-outline-primary ms-0 ms-md-3 mt-2 mt-md-0" onclick="copyAnnounceUrl()">'.$lang->upload['announce_copy'].'</button>
</div>
</div>

<script>
function copyAnnounceUrl() {
  const urlElement = document.getElementById("announceUrl");
  const text = urlElement.textContent;

  navigator.clipboard.writeText(text).then(function() {
    alert("Announce URL copied to clipboard!");
  }, function(err) {
    alert("Failed to copy: " + err);
  });
}
</script>
';





?>



  <title>Torrent Upload Form</title>



<!-- Modern Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);border:none;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-white bg-opacity-20 p-1">
                        <i class="fas fa-exclamation-triangle text-white" style="font-size:1.2rem;"></i>
                    </div>
                    <h5 class="modal-title text-white fw-semibold" id="errorModalLabel"><?= $lang->upload['error_title'] ?></h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light bg-opacity-25 p-3 border-bottom border-danger border-opacity-25">
                    <div class="d-flex align-items-center gap-2 text-danger">
                        <i class="fas fa-bug"></i>
                        <span class="small fw-semibold">Error Details</span>
                    </div>
                </div>
                <div id="errorModalBody"
                     style="max-height:500px;overflow-y:auto;padding:1.25rem;
                            font-family:'SF Mono','Monaco','Cascadia Code',monospace;
                            font-size:12px;line-height:1.6;white-space:pre-wrap;word-break:break-word;
                            background:linear-gradient(145deg,#fef2f2 0%,#fee2e2 100%);
                            color:#991b1b;"></div>
            </div>
            <div class="modal-footer border-0 bg-light bg-opacity-50">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="text-muted small"><i class="fas fa-info-circle me-1"></i>Check your input and try again</div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i><?= $lang->upload['error_close'] ?>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm ms-2" id="copyErrorBtn">
                            <i class="fas fa-copy me-1"></i>Copy Error
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.error-line{transition:all .2s;padding:2px 0 2px 8px;border-left:3px solid transparent}
.error-line:hover{background:rgba(220,38,38,.1);border-left-color:#dc2626;transform:translateX(2px)}
#errorModalBody::-webkit-scrollbar{width:8px}
#errorModalBody::-webkit-scrollbar-track{background:#fecaca;border-radius:4px}
#errorModalBody::-webkit-scrollbar-thumb{background:#dc2626;border-radius:4px}
</style>




<!-- Upload Complete Modal -->
<div class="modal fade" id="uploadCompleteModal" tabindex="-1" aria-labelledby="uploadCompleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-success text-white border-0">
        <div class="d-flex align-items-center w-100">
          <i class="fas fa-check-circle fa-2x me-3"></i>
          <h5 class="modal-title mb-0" id="uploadCompleteModalLabel"><?= $lang->upload['upload_success_title'] ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="success-animation mb-3">
          <i class="fas fa-check text-success" style="font-size: 3rem;"></i>
        </div>
        <h4 class="text-success mb-2"><?= $lang->upload['upload_success_congrats'] ?></h4>
        <p class="text-muted mb-0"><?= $lang->upload['upload_success_text'] ?></p>
        <p class="text-muted"><?= $lang->upload['upload_success_redirect'] ?></p>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i><?= $lang->upload['upload_stay'] ?>
        </button>
        <button type="button" class="btn btn-success" onclick="redirectToTorrent()">
          <i class="fas fa-eye me-2"></i><?= $lang->upload['upload_view'] ?>
        </button>
      </div>
    </div>
  </div>
</div>








<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-gradient-primary text-white border-0">
        <div class="d-flex align-items-center w-100">
          <i class="fas fa-cloud-upload-alt fa-2x me-3"></i>
          <h5 class="modal-title mb-0" id="uploadModalLabel"><?= $lang->upload['upload_processing'] ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-5">
        <!-- Animated spinner -->
        <div class="upload-spinner mb-4">
          <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div class="spinner-glow"></div>
        </div>
        
        <!-- Progress text -->
        <h6 class="text-primary mb-2"><?= $lang->upload['upload_wait'] ?></h6>
        <p class="text-muted mb-3"><?= $lang->upload['upload_wait_sub'] ?></p>
        
        <!-- Animated dots -->
        <div class="loading-dots mb-3">
          <span class="dot"></span>
          <span class="dot"></span>
          <span class="dot"></span>
        </div>
        
        <!-- Timer display -->
        <div class="timer-display mb-3">
          <span class="badge bg-light text-dark fs-6">
            <i class="fas fa-clock me-2"></i>
            <span id="uploadTimer">0</span> seconds
          </span>
        </div>
        
        <!-- Progress bar -->
        <div class="progress-container">
          <div class="progress mt-3" style="height: 8px; background-color: #e9ecef; border-radius: 10px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 id="uploadProgressBar"
                 role="progressbar" 
                 style="width: 0%; background: linear-gradient(90deg, #0d6efd, #0dcaf0);"
                 aria-valuenow="0" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
            </div>
          </div>
          <div class="progress-text mt-2">
            <small class="text-muted" id="uploadStatusText"><?= $lang->upload['upload_init'] ?></small>
            <small class="text-primary fw-bold float-end" id="progressPercentage">0%</small>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <small class="text-muted">
          <i class="fas fa-info-circle me-1"></i>
          <?= $lang->upload['upload_size_note'] ?>
        </small>
      </div>
    </div>
  </div>
</div>









<script src="<?= $BASEURL; ?>/scripts/toast.js"></script>





<!-- Modal for deleting screenshot with preview -->
<div class="modal fade" id="deleteScreenshotModal" tabindex="-1" aria-labelledby="deleteScreenshotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteScreenshotModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> <?= $lang->upload['screenshots_delete_title'] ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <!-- Основная иконка и текст -->
        <div class="d-flex align-items-center mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
            <i class="fas fa-trash-alt text-danger fs-1"></i>
          </div>
          <div>
            <h5 class="fw-bold mb-1" id="deleteScreenshotTitle"><?= $lang->upload['screenshots_delete_title'] ?></h5>
            <p class="text-muted mb-0" id="deleteScreenshotFilename"></p>
          </div>
        </div>

        <!-- Контейнер для превью -->
        <div id="deleteScreenshotPreviewContainer" class="preview-container mb-3 text-center">
          <div class="preview-wrapper" style="display: inline-block; max-width: 100%;">
            <img id="deleteScreenshotImage" src="" alt="Preview" 
                 style="max-width: 100%; max-height: 200px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none;" 
                 onerror="handleImageError(this)">
            <div id="noImagePreview" class="d-none flex-column align-items-center justify-content-center p-4" style="min-height: 150px;">
              <i class="fas fa-image fa-3x text-muted mb-2"></i>
              <span class="text-muted"><?= $lang->upload['screenshots_no_preview'] ?></span>
            </div>
          </div>
        </div>

        <!-- Детали файла -->
        <div class="file-details bg-light p-3 rounded-3 mb-3">
          <div class="d-flex align-items-center">
            <i class="fas fa-file-image text-primary me-3 fa-2x"></i>
            <div class="overflow-hidden">
              <div class="fw-bold" id="deleteScreenshotFileName">screenshot.jpg</div>
              <div class="small text-muted" id="deleteScreenshotFileInfo">
                <i class="fas fa-image me-1"></i> Existing screenshot
              </div>
            </div>
          </div>
        </div>

        <!-- Предупреждение -->
        <div class="alert alert-warning mt-2 mb-0">
          <div class="d-flex">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
              <strong><?= $lang->upload['error_title'] ?>:</strong> <?= $lang->upload['screenshots_delete_warning'] ?>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteScreenshotBtn">
          <i class="fas fa-trash-alt me-1"></i><?= $lang->upload['screenshots_delete_confirm'] ?>
        </button>
      </div>
    </div>
  </div>
</div>





<!-- Подключение стилей Upload -->
<link rel="stylesheet" href="<?= $BASEURL; ?>/include/templates/default/style/upload_torrent.css" type="text/css" media="screen" />





  <div class="container my-5">
    
	
	
	
	
	
	<h2 class="d-flex align-items-center mb-4" <?= $style; ?>>
           <?= $icon; ?>
           <?= $headingText; ?>
    </h2>
	
	
	 
    
	
	
<form method="post" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']) . ($EditTorrent ? '?id=' . urlencode((string)$EditTorrentID) : ''); ?>" id="torrent-upload-form" enctype="multipart/form-data">
<input type="hidden" name="my_post_key" value="<?php echo $mybb->post_code; ?>" />
  
  
  <!-- Скрытый контейнер для добавления file_ids -->
    <div id="fileIdsContainer"></div>
  
  

 <!-- Form Name -->
<div class="mb-4">
    <label for="formName" class="form-label fw-semibold">
        <i class="fas fa-heading me-1 text-primary"></i><?= $lang->upload['torrent_name'] ?>
        <span class="text-danger">*</span>
    </label>
    
    <div class="input-group has-validation">
        <span class="input-group-text bg-light border-end-0">
            <i class="fas fa-pencil-alt text-muted"></i>
        </span>
        
        <input 
            class="form-control border-start-0 ps-0" 
            type="text" 
            id="formName" 
            name="formName" 
            placeholder="<?= $lang->upload['torrent_name_hint'] ?>" 
            required 
            minlength="3" 
            maxlength="255" 
            value="<?= isset($torrent['name']) ? htmlspecialchars($torrent['name']) : '' ?>"
        />
        
        <div class="invalid-feedback"><?= $lang->upload['torrent_name_invalid'] ?></div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i><?= $lang->upload['torrent_name_hint'] ?>
        </small>
        <small class="text-muted">
            <span id="formNameCharCount">0</span>/255
        </small>
    </div>
</div>


  
  
  <script src="<?= $BASEURL; ?>/scripts/Sortable.min.js"></script>
<script src="<?= $BASEURL; ?>/scripts/upload_torrent.js"></script>
  
 
<!-- Torrent File -->
<div class="mb-4">
    <label for="torrentFile" class="form-label fw-semibold">
        <i class="fas fa-file-alt me-1 text-primary"></i><?= $lang->upload['torrent_file'] ?>
        <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
    </label>

    <!-- Drop Zone -->
    <div id="torrentDropZone"
         class="border border-2 border-dashed rounded-3 p-4 text-center position-relative"
         style="border-color: #0d6efd !important; cursor: pointer; transition: all 0.3s ease; background: #f8f9ff;">

        <!-- Иконка и текст -->
        <div id="torrentDropContent">
            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 d-block"></i>
            <h6 class="fw-bold mb-1"><?= $lang->upload['torrent_drop_title'] ?></h6>
           <p class="text-muted small mb-3"><?= $lang->upload['torrent_drop_sub'] ?></p>
           <button type="button" class="btn btn-outline-primary btn-sm px-4">
                <i class="fas fa-folder-open me-2"></i><?= $lang->upload['torrent_browse'] ?>
            </button>
        </div>

        <!-- Файл выбран — показываем инфо -->
        <div id="torrentFileSelected" style="display:none;">
            <div class="d-flex align-items-center justify-content-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                    <i class="fas fa-file-alt fa-2x text-primary"></i>
                </div>
                <div class="text-start">
                    <div class="fw-bold" id="torrentSelectedName">filename.torrent</div>
                    <div class="text-muted small" id="torrentSelectedSize">0 KB</div>
                    <span class="badge bg-success mt-1">
                        <i class="fas fa-check me-1"></i>Ready to upload
                    </span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger ms-3" id="torrentRemoveBtn" onclick="removeTorrentFile()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Скрытый input -->
        <input class="d-none"
               type="file"
               id="torrentFile"
               name="torrentFile"
               accept=".torrent"
               <?= $isEdit ? '' : 'required' ?> />
    </div>

    <!-- Прогресс бар при загрузке -->
    <div id="torrentUploadProgress" class="mt-2" style="display:none;">
        <div class="progress" style="height:6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 style="width:100%;"></div>
        </div>
        <small class="text-muted"><?= $lang->upload['torrent_validating'] ?></small>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i>
            <?= $isEdit ? $lang->upload['torrent_file_hint_edit'] : $lang->upload['torrent_file_hint'] ?>
        </small>
        <small class="text-muted" id="torrentFileInfo"></small>
    </div>
    <div class="mt-2" id="torrentFilePreview"></div>
</div>






<!-- Метаданные торрента -->
<div id="torrentMetaInfo" style="display:none;" class="mt-3">
    <div class="card border-0 bg-light">
        <div class="card-body py-3">
            <h6 class="card-title mb-3">
                <i class="fas fa-info-circle text-primary me-2"></i><?= $lang->upload['torrent_info'] ?>
            </h6>
            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <span class="badge bg-primary">
                        <i class="fas fa-hdd me-1"></i>Size: <strong id="torrentMetaSize">—</strong>
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary">
                        <i class="fas fa-file me-1"></i>Files: <strong id="torrentMetaFiles">—</strong>
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-info text-dark">
                        <i class="fas fa-fingerprint me-1"></i>Hash: <strong id="torrentMetaHash">—</strong>
                    </span>
                </div>
            </div>

            <!-- Список файлов -->
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="fw-bold text-muted">
                        <i class="fas fa-list me-1"></i><?= $lang->upload['torrent_file_list'] ?>
                    </small>
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                            onclick="toggleTorrentFileList()">
                        <i class="fas fa-eye me-1" id="torrentFileListToggleIcon"></i>
                        <span id="torrentFileListToggleText"><?= $lang->upload['torrent_show'] ?></span>
                    </button>
                </div>
                <div id="torrentFileList" style="display:none; max-height:200px; overflow-y:auto;">
                    <!-- Список вставляется через JS -->
                </div>
            </div>
        </div>
    </div>
</div>





<!-- NFO File -->
<div class="mb-3">
    <label for="nfoFile" class="form-label fw-semibold">
        <i class="fas fa-file-alt me-1 text-primary"></i><?= $lang->upload['nfofile'] ?>
        <span class="text-muted fw-normal small"><?= $lang->upload['nfofile_optional'] ?></span>
    </label>

    <div class="input-group">
        <input class="form-control" type="file" id="nfoFile" name="nfoFile" accept=".nfo,text/plain" />
        <button type="button" class="btn btn-outline-secondary" id="nfoPreviewBtn"
                style="display:none;" onclick="toggleNfoPreview()">
            <i class="fas fa-eye me-1"></i>Preview
        </button>
    </div>
    <div class="form-text"><?= $lang->upload['nfofile_hint'] ?></div>

    <!-- NFO Preview -->
    <div id="nfoPreviewContainer" class="mt-2" style="display:none;">
        <div class="card border-0" style="border: 1px solid #dee2e6 !important;">
    <div class="card-header d-flex justify-content-between align-items-center py-2"
         style="background:#f1f3f5; border-bottom: 1px solid #dee2e6;">
        <span class="text-dark small">
            <i class="fas fa-file-alt me-1 text-primary"></i>
            <span id="nfoPreviewFilename">file.nfo</span>
        </span>
        <div class="d-flex gap-2">
            <span class="badge bg-secondary" id="nfoPreviewSize"></span>
            <span class="badge bg-secondary" id="nfoPreviewLines"></span>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                    onclick="toggleNfoPreview()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <pre id="nfoPreviewText"
             style="background:#f8f9fa; color:#212529; font-family:'Courier New',monospace;
                    font-size:0.75rem; padding:1rem; margin:0; max-height:300px;
                    overflow-y:auto; white-space:pre; border-radius:0 0 8px 8px;
                    border: none;"></pre>
    </div>
</div>
    </div>

    <?php if ($isEdit): ?>
    <!-- Показываем что NFO уже есть -->
    <?php
    $existingNfoQ = $db->sql_query_prepared("SELECT id FROM torrents_nfo WHERE torrent_id = ?", [$EditTorrentID]);
    $existingNfoRow = $existingNfoQ ? $db->fetch_array($existingNfoQ) : null;
    if ($existingNfoRow):
    ?>
    <div class="mt-2 d-flex align-items-center gap-2">
        <span class="badge bg-success">
            <i class="fas fa-check me-1"></i><?= $lang->upload['nfofile_uploaded'] ?>
        </span>
        <small class="text-muted"><?= $lang->upload['nfofile_replace'] ?></small>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

  
  
  
  
  
  <?= $editor['toolbar']?>
  
  
<!-- Description with BBCode textarea -->
<div class="mb-3">
  <label for="description" class="form-label"><?= $lang->upload['description'] ?></label>

  
  <!-- Textarea -->
  <textarea 
    class="form-control" 
    id="description" 
    name="description" 
    rows="10" 
    required 
    placeholder="<?= $lang->upload['description_placeholder'] ?>"><?= isset($torrent['descr']) ? htmlspecialchars($torrent['descr']) : '' ?></textarea>

  <div class="form-text text-end"><span id="charCount2">0 / 500</span></div>
  <div class="invalid-feedback"><?= $lang->upload['description_invalid'] ?></div>
  



 
 
  
</div>


   
   
   
   
<!-- Category Picker -->
<div class="mb-4">
    <label class="form-label fw-semibold">
        <i class="fas fa-th-large me-1 text-primary"></i><?= $lang->upload['category'] ?>
        <span class="text-danger">*</span>
    </label>

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

    <div class="category-icon-picker">
        <?php foreach ($_categoriesC as $cat):
            $isActive = ($category === (int)$cat['id']) ? 'active' : '';
        ?>
        <button type="button"
                class="cat-pick-btn <?= $isActive ?>"
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
            <span class="text-muted">
                <i class="fas fa-hand-pointer me-1"></i>Click a category to select
            </span>
        <?php endif; ?>
    </div>

    <div class="text-danger small mt-1" id="categoryError" style="display:none;">
        <?= $lang->upload['category_invalid'] ?>
    </div>
</div>

        
    


   
   
   
   
   
   
   
   




<!-- Tags Field -->
<div class="mb-4">
    <label for="tags" class="form-label fw-semibold">
        <i class="fas fa-tags me-2 text-gradient"></i><?= $lang->upload['tags'] ?>
        <span class="text-muted fw-normal small">(optional)</span>
    </label>

    <div class="input-group mb-3">
        <span class="input-group-text bg-gradient-light border-0 shadow-sm">
            <i class="fas fa-tag text-primary"></i>
        </span>
        <input type="text"
               class="form-control border-0 shadow-sm"
               id="tags"
               name="tags"
               placeholder="<?= $lang->upload['tags_placeholder'] ?>"
               value="<?= htmlspecialchars($torrent['tags'] ?? '') ?>"
               style="background: #f8f9fa;">
        <button type="button" class="btn btn-gradient-secondary shadow-sm" onclick="clearAllTags()">
            <i class="fas fa-eraser me-2"></i><?= $lang->upload['tags_clear'] ?>
        </button>
    </div>

    <!-- Genre icon buttons -->
    <div class="d-flex flex-wrap gap-2 mb-3" id="genreButtons">
        <?php
        $genres = [
            ['Action',      'fas fa-bolt',              '#ff4757'],
            ['Adventure',   'fas fa-compass',           '#ffa502'],
            ['Animation',   'fas fa-film',              '#7bed9f'],
            ['Biography',   'fas fa-user-graduate',     '#70a1ff'],
            ['Comedy',      'fas fa-laugh-squint',      '#ff6b81'],
            ['Crime',       'fas fa-gavel',             '#2f3542'],
            ['Documentary', 'fas fa-video',             '#a4b0be'],
            ['Drama',       'fas fa-mask',              '#57606f'],
            ['Family',      'fas fa-users',             '#ff7f50'],
            ['Fantasy',     'fas fa-dragon',            '#dfe6e9'],
            ['History',     'fas fa-landmark',          '#cd84f1'],
            ['Horror',      'fas fa-ghost',             '#ff4d4d'],
            ['Music',       'fas fa-music',             '#1e90ff'],
            ['Mystery',     'fas fa-search',            '#8e44ad'],
            ['Romance',     'fas fa-heart',             '#ff6b6b'],
            ['Sci-Fi',      'fas fa-rocket',            '#00cec9'],
            ['Sport',       'fas fa-trophy',            '#fdcb6e'],
            ['Thriller',    'fas fa-skull',             '#e17055'],
            ['War',         'fas fa-fist-raised',       '#636e72'],
            ['Western',     'fas fa-horse-head',        '#f39c12'],
        ];
        
        $currentTags = array_map('trim', explode(',', $torrent['tags'] ?? ''));
        
        foreach ($genres as [$label, $icon, $color]):
            $active = in_array($label, $currentTags) ? 'genre-active' : '';
            $bgColor = $active ? $color : 'transparent';
            $textColor = $active ? 'white' : ($color ?? '#6c757d');
        ?>
        <button type="button"
                class="btn genre-tag-btn <?= $active ?>"
                data-genre="<?= $label ?>"
                data-color="<?= $color ?>"
                onclick="toggleGenreTag(this)"
                style="border-color: <?= $color ?>80; color: <?= $textColor ?>;">
            <i class="<?= $icon ?> me-2" style="font-size: 1.1rem;"></i>
            <span><?= $label ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm" role="alert" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);">
        <i class="fas fa-info-circle me-2 text-primary"></i>
        <?= $lang->upload['tags_hint'] ?> to add or remove it from tags. 
        Tags will be automatically filled from IMDb when you click the <strong>Fetch Movie Info</strong> button.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>






<div class="section-label">
                        <i class="fas fa-images"></i>
                        <?= $lang->upload['media_section'] ?>
                    </div>






<!-- IMDb Link -->
<div class="col-md-12 mb-4">
    <label class="form-label fw-semibold">
        <i class="fab fa-imdb text-warning me-2"></i><?= $lang->upload['imdb_link'] ?>
    </label>
    <div class="input-group">
        <span class="input-group-text bg-light">
            <i class="fas fa-link"></i>
        </span>
        <input type="url"
               class="form-control form-control-custom"
               id="imdbUrl"
               name="imdbUrl"
               value="<?= htmlspecialchars($t_link) ?>"
               placeholder="<?= $lang->upload['imdb_placeholder'] ?>">
        <button type="button" class="btn btn-warning" id="imdbFetchBtn" onclick="fetchImdbData()">
            <i class="fab fa-imdb me-1"></i><?= $lang->upload['imdb_fetch'] ?>
        </button>
    </div>
    <div class="form-text">
        <i class="fas fa-info-circle me-1"></i>
        <?= $lang->upload['imdb_hint'] ?>
    </div>

    <!-- IMDb Preview -->
    <div id="imdbPreview" class="mt-3" style="display:none;">
        <div class="card border-0 bg-light">
            <div class="card-body p-3">
                <div class="d-flex gap-3 align-items-start">

                    <!-- Постер -->
                    <div id="imdbPosterPreview" style="flex-shrink:0; display:none;">
                        <img id="imdbPosterImg" src="" alt="Poster"
                             style="width:80px;height:120px;object-fit:cover;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                        <div class="mt-1 d-flex gap-1">
                            <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2 w-100"
                                    onclick="applyImdbPoster('main')" title="<?= $lang->upload['imdb_set_main'] ?>">
                                <i class="fas fa-image me-1"></i><?= $lang->upload['imdb_poster_main'] ?>
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2 w-100"
                                    onclick="applyImdbPoster('secondary')" title="<?= $lang->upload['imdb_set_secondary'] ?>">
                                <i class="fas fa-images me-1"></i><?= $lang->upload['imdb_poster_secondary'] ?>
                            </button>
                        </div>
                    </div>

                    <!-- Инфо -->
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold" id="imdbPreviewTitle">—</h6>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge bg-warning text-dark" id="imdbPreviewYear" style="display:none;"></span>
                            <span class="badge bg-secondary" id="imdbPreviewGenre" style="display:none;"></span>
                            <span class="badge bg-success" id="imdbPreviewRating" style="display:none;">
                                <i class="fas fa-star me-1"></i><span></span>
                            </span>
                        </div>
                        <p class="small text-muted mb-2" id="imdbPreviewPlot"></p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyImdbToDescription()">
                                <i class="fas fa-paste me-1"></i><?= $lang->upload['imdb_add_description'] ?>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Индикатор загрузки -->
    <div id="imdbLoading" class="mt-2" style="display:none;">
        <div class="d-flex align-items-center gap-2 text-muted small">
            <div class="spinner-border spinner-border-sm text-warning"></div>
            <?= $lang->upload['imdb_fetching'] ?>
        </div>
    </div>

    <!-- Ошибка -->
    <div id="imdbError" class="alert alert-warning mt-2 small py-2" style="display:none;">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <span id="imdbErrorText"></span>
    </div>
</div>







  




  
 
						<!-- Image Uploads -->
<div class="row g-3">
    <!-- Main Image -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title d-flex align-items-center">
                    <i class="fas fa-image text-primary me-2"></i>
                    <?= $lang->upload['image_main'] ?>
                </h6>
                
                <!-- Choose Upload Method -->
                <div class="mb-3">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="uploadType1" id="uploadByUrl1" value="url" checked>
                        <label class="btn btn-outline-primary" for="uploadByUrl1">
                            <i class="fas fa-link me-1"></i> URL
                        </label>
                        
                        <input type="radio" class="btn-check" name="uploadType1" id="uploadByFile1" value="file">
                        <label class="btn btn-outline-primary" for="uploadByFile1">
                            <i class="fas fa-upload me-1"></i> File
                        </label>
                    </div>
                </div>

                <!-- Upload by URL -->
                <div class="mb-3" id="uploadUrlGroup1">
                    <label for="imageUrl" class="form-label"><?= $lang->upload['image_url_label'] ?></label>
                    <input type="url" 
                           class="form-control form-control-custom" 
                           id="imageUrl" 
                           name="imageUrl" 
                           value="<?= htmlspecialchars($torrent['t_image'] ?? '') ?>" 
                           placeholder="<?= $lang->upload['image_url_placeholder'] ?>"
                           oninput="updateImagePreviewFromUrl(this.value, 'imagePreview')">
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        Paste direct image URL (jpg, png, gif, webp)
                    </div>
                </div>

                <!-- Upload from Device -->
                <div class="mb-3 d-none" id="uploadFileGroup1">
                    <div class="upload-zone-sm" onclick="document.getElementById('imagesUpload').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                        <p class="mb-1 fw-semibold"><?= $lang->upload['image_click_upload'] ?></p>
                        <p class="small text-muted mb-0"><?= $lang->upload['image_drag_drop'] ?></p>
                        <input type="file" 
                               class="d-none" 
                               id="imagesUpload" 
                               name="imagesUpload" 
                               accept="image/*"
                               onchange="handleImageUpload(this, 'imagePreview')">
                    </div>
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        <?= $lang->upload['image_file_hint'] ?>
                    </div>
                </div>

                <!-- Preview -->
                <div id="imagePreview" class="preview-container mt-3">
                    <?php if(!empty($torrent['t_image'])): ?>
                        <div class="preview-item">
                            <img src="<?= htmlspecialchars($torrent['t_image']) ?>" 
                                 class="preview-poster" 
                                 alt="Main image">
                            <button type="button" 
                                    class="delete-btn2" 
                                    onclick="removeImagePreview('imagePreview', 'imageUrl', 'imagesUpload')"
                                    title="Remove image">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Image -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title d-flex align-items-center">
                    <i class="fas fa-images text-primary me-2"></i>
                    <?= $lang->upload['image_secondary'] ?>
                </h6>
                
                <!-- Choose Upload Method -->
                <div class="mb-3">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="uploadType2" id="uploadByUrl2" value="url" checked>
                        <label class="btn btn-outline-primary" for="uploadByUrl2">
                            <i class="fas fa-link me-1"></i> URL
                        </label>
                        
                        <input type="radio" class="btn-check" name="uploadType2" id="uploadByFile2" value="file">
                        <label class="btn btn-outline-primary" for="uploadByFile2">
                            <i class="fas fa-upload me-1"></i> File
                        </label>
                    </div>
                </div>

                <!-- Upload by URL -->
                <div class="mb-3" id="uploadUrlGroup2">
                    <label for="imageUrl2" class="form-label"><?= $lang->upload['image_url_label'] ?></label>
                    <input type="url" 
                           class="form-control form-control-custom" 
                           id="imageUrl2" 
                           name="imageUrl2" 
                           value="<?= htmlspecialchars($torrent['t_image2'] ?? '') ?>" 
                           placeholder="<?= $lang->upload['image_url_placeholder'] ?>"
                           oninput="updateImagePreviewFromUrl(this.value, 'imagePreview2')">
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        Paste direct image URL (jpg, png, gif, webp)
                    </div>
                </div>

                <!-- Upload from Device -->
                <div class="mb-3 d-none" id="uploadFileGroup2">
                    <div class="upload-zone-sm" onclick="document.getElementById('imagesUpload2').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                        <p class="mb-1 fw-semibold"><?= $lang->upload['image_click_upload'] ?></p>
                        <p class="small text-muted mb-0"><?= $lang->upload['image_drag_drop'] ?></p>
                        <input type="file" 
                               class="d-none" 
                               id="imagesUpload2" 
                               name="imagesUpload2" 
                               accept="image/*"
                               onchange="handleImageUpload(this, 'imagePreview2')">
                    </div>
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        <?= $lang->upload['image_file_hint'] ?>
                    </div>
                </div>

                <!-- Preview -->
                <div id="imagePreview2" class="preview-container mt-3">
                    <?php if(!empty($torrent['t_image2'])): ?>
                        <div class="preview-item">
                            <img src="<?= htmlspecialchars($torrent['t_image2']) ?>" 
                                 class="preview-poster" 
                                 alt="Secondary image">
                            <button type="button" 
                                    class="delete-btn2" 
                                    onclick="removeImagePreview('imagePreview2', 'imageUrl2', 'imagesUpload2')"
                                    title="Remove image">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>







<table>
	  
      
	  
	  
	  <!-- Anonymous Upload checkbox -->
	  <br />
	  <div class="section-label">
                        <i class="fas fa-cog"></i>
                        <?= $lang->upload['settings_section'] ?>
                    </div>
<tr>
  <td class="none">
   
	
	
	
	<br />
    
	
	
	<div class="switch-container">
	
	
	
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="anonymous" 
            name="anonymous" 
            value="yes" 
            role="switch"
            <?= (isset($torrent['anonymous']) ? $torrent['anonymous'] === 'yes' : (($CURUSER['invisible'] ?? 0) == 1)) ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold" for="anonymous">
            <i class="fas fa-user-secret me-1"></i><?= $lang->upload['anonymous'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['anonymous_hint'] ?></div>
    </div>

</div>



	
	
	
	
  </td>




<!-- Request Upload checkbox -->

<td class="none">
 
 
 <!-- Request Upload checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="request" 
            name="request" 
            value="yes"
            role="switch"
            <?= isset($torrent['isrequest']) && $torrent['isrequest'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold" for="request">
            <i class="fas fa-hand-paper me-1"></i><?= $lang->upload['request'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['request_hint'] ?></div>
    </div>
</div>
 
 
 
 
</td>
	  
	  
	  
	  
	  
	<tr>
   <td class="none">
      
	  
	  <!-- Free Torrent checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="free" 
            name="free" 
            value="yes"
            role="switch"
            <?= isset($torrent['free']) && $torrent['free'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-success" for="free">
            <i class="fas fa-gift me-1"></i><?= $lang->upload['free'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['free_hint'] ?></div>
    </div>
</div>
	  
	  
	  
   </td>

   <td class="none">
      
	  
	  <!-- Silver Torrent checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="silver" 
            name="silver" 
            value="yes"
            role="switch"
            <?= isset($torrent['silver']) && $torrent['silver'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-info" for="silver">
            <i class="fas fa-star me-1"></i><?= $lang->upload['silver'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['silver_hint'] ?></div>
    </div>
</div>
	  
	  
	  
   </td>
</tr>

<tr>
   <td class="none">
      
	  
	 <!-- Double Upload checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="doubleupload" 
            name="doubleupload" 
            value="yes"
            role="switch"
            <?= isset($torrent['doubleupload']) && $torrent['doubleupload'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-warning" for="doubleupload">
            <i class="fas fa-bolt me-1"></i><?= $lang->upload['doubleupload'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['doubleupload_hint'] ?></div>
    </div>
</div>
	  
	  
	  
	  
   </td>

   <td class="none">
      
	  
	 <!-- 30% Leech checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="thirtypercent" 
            name="thirtypercent" 
            value="yes"
            role="switch"
            <?= isset($torrent['thirtypercent']) && $torrent['thirtypercent'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold" style="color:#411749" for="thirtypercent">
            <i class="fas fa-percent me-1"></i><?= $lang->upload['thirtypercent'] ?? '30% Leech' ?>
        </label>
        <div class="form-text"><?= $lang->upload['thirtypercent_hint'] ?? 'Only 30% of downloaded data is counted toward this user\'s download stats.' ?></div>
    </div>
</div>
	  
	  
	  
   </td>
</tr>

<tr>
   <td class="none">
      
	  
	 <!-- Disable Comments checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="allowcomments" 
            name="allowcomments" 
            value="no"
            role="switch"
            <?= isset($torrent['allowcomments']) && $torrent['allowcomments'] === 'no' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-secondary" for="allowcomments">
            <i class="fas fa-comment-slash me-1"></i><?= $lang->upload['allowcomments'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['allowcomments_hint'] ?></div>
    </div>
</div>
	  
	  
	  
   </td>

   <td class="none">
      
	  
	  <!-- Sticky Torrent checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="sticky" 
            name="sticky" 
            value="yes"
            role="switch"
            <?= isset($torrent['sticky']) && $torrent['sticky'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-primary" for="sticky">
            <i class="fas fa-thumbtack me-1"></i><?= $lang->upload['sticky'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['sticky_hint'] ?></div>
    </div>
</div>
	  
	  
	  
   </td>
</tr>

<tr>
   
   
   
   
   
   <td class="none">
   
   
   <!-- Nuked Torrent checkbox -->
<div class="switch-container">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="isnuked" 
            name="isnuked" 
            value="yes"
            role="switch"
            onclick="ShowHideField('nukereason');"
            <?= isset($torrent['isnuked']) && $torrent['isnuked'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-danger" for="isnuked">
            <i class="fas fa-radiation me-1"></i><?= $lang->upload['isnuked'] ?>
        </label>
        <div class="form-text"><?= $lang->upload['isnuked_hint'] ?></div>
    </div>
</div>
   
   
   
   
   <!-- Nuke Reason (conditional) -->
<div class="mb-3" id="nukereason" style="<?= isset($torrent['isnuked']) && $torrent['isnuked'] === 'yes' ? '' : 'display:none;' ?>">
    <label for="WhyNuked" class="form-label fw-bold text-danger">
        <i class="fas fa-exclamation-circle me-1"></i><?= $lang->upload['nuke_reason'] ?>
    </label>
    <input 
        type="text" 
        class="form-control" 
        id="WhyNuked" 
        name="WhyNuked" 
        placeholder="<?= $lang->upload['nuke_reason_placeholder'] ?>"
        value="<?= isset($torrent['WhyNuked']) ? htmlspecialchars($torrent['WhyNuked']) : '' ?>" 
    />
    <div class="form-text"><?= $lang->upload['nuke_reason_hint'] ?></div>
</div>







 
   
</td>
   
</tr>

   </table>













  

<!-- Screenshots Upload -->
<div class="col-12 mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="card-title d-flex align-items-center">
                <i class="fas fa-camera text-primary me-2"></i>
               <?= $lang->upload['screenshots'] ?>
            </h6>

            <!-- Переключатель между File и URL -->
            <div class="mb-3">
                <div class="btn-group btn-group-sm w-100" role="group">
                    <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByFile" value="file" checked>
                    <label class="btn btn-outline-primary" for="screenshotByFile">
                        <i class="fas fa-upload me-1"></i><?= $lang->upload['screenshots_upload_files'] ?>
                    </label>
                    <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByUrl" value="url">
                    <label class="btn btn-outline-primary" for="screenshotByUrl">
                        <i class="fas fa-link me-1"></i><?= $lang->upload['screenshots_bulk_url'] ?>
                    </label>
                </div>
            </div>

            <!-- Upload Files -->
            <div id="screenshotFileGroup">
                <div class="upload-zone mb-3" onclick="document.getElementById('screenshotsUpload').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h6><?= $lang->upload['screenshots_drop'] ?></h6>
                    <p class="text-muted mb-0"><?= $lang->upload['screenshots_multiple'] ?></p>
                    <input type="file"
                           class="d-none"
                           id="screenshotsUpload"
                           name="screenshotsUpload[]"
                           multiple
                           accept="image/*"
						   data-max="<?= (int)$max_screenshots ?>">
                </div>
                <!-- Preview ВНУТРИ screenshotFileGroup -->
                <div id="screenshotsPreview" class="preview-container"></div>
            </div>
			
			<!-- Лимит скринов -->

<!-- Модалка превью скринов -->
<div class="modal fade" id="screenshotPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-images me-2"></i>Screenshots Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalPreviewGrid" class="row g-2"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="text-muted small" id="modalPreviewCount"></span>
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm me-2" onclick="clearScreenshots()">
                        <i class="fas fa-times me-1"></i>Clear All
                    </button>
                    
					<button type="button" class="btn btn-primary btn-sm" 
        data-bs-dismiss="modal"
        onclick="showSelectedPreviews()">
    <i class="fas fa-check me-1"></i>Confirm
</button>

                </div>
            </div>
        </div>
    </div>
</div>


			
			

            <!-- Bulk URL -->
            <div id="screenshotUrlGroup" class="d-none">
                <div class="mb-2">
                    <label class="form-label fw-semibold small">
                        <i class="fas fa-link me-1 text-primary"></i>Screenshot URLs
                        <span class="text-muted fw-normal">(one URL per line)</span>
                    </label>
                    <textarea
                        id="screenshotUrlsInput"
                        class="form-control font-monospace"
                        rows="6"
                        placeholder="https://example.com/screen1.jpg
https://example.com/screen2.png
https://example.com/screen3.webp"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i><?= $lang->upload['screenshots_url_supported'] ?>
                        </small>
                        <button type="button" class="btn btn-primary btn-sm" onclick="loadScreenshotUrls()">
                            <i class="fas fa-eye me-1"></i><?= $lang->upload['screenshots_load_preview'] ?>
                        </button>
                    </div>
                </div>
                <div id="screenshotUrlPreview" class="preview-container mt-2"></div>
                <div id="screenshotUrlInputs"></div>
            </div>

            <!-- Existing Screenshots -->
            <?php if(!empty($screenshots)): ?>
                <h6 class="mt-4 mb-3"><?= $lang->upload['screenshots_existing'] ?></h6>
                <div id="existingScreenshots" class="preview-container">
                    <?php foreach($screenshots as $shot): ?>
                        <div class="screenshot-item" data-id="<?= $shot['id'] ?>">
                            <img src="/torrents/screens/<?= htmlspecialchars($shot['filename']) ?>"
                                 class="preview-screenshot"
                                 alt="Screenshot">
                            <button type="button"
                                    class="delete-btn"
                                    title="Delete screenshot">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
	
	
	
<style>
/* Torrent Drop Zone */
#torrentDropZone {
    min-height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#torrentDropZone.drag-over {
    background: #e7f1ff !important;
    border-color: #0b5ed7 !important;
    transform: scale(1.01);
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15);
}

#torrentDropZone.drag-invalid {
    background: #fff5f5 !important;
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.15);
}


/* Screenshot Drag & Drop */
.screenshot-drag-ghost {
    opacity: 0.4;
    background: #e7f1ff;
    border: 2px dashed #0d6efd !important;
    border-radius: 6px;
}

.screenshot-drag-chosen {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    transform: scale(1.05);
    z-index: 999;
}

.screenshot-drag-active {
    cursor: grabbing !important;
}

.screenshot-item img,
.screenshot-url-item img {
    cursor: grab;
}

.screenshot-item img:active,
.screenshot-url-item img:active {
    cursor: grabbing;
}

.screenshot-order-badge {
    pointer-events: none;
}




/* Скриншоты при загрузке файлов */
#screenshotsPreview.preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

#screenshotsPreview .screenshot-item {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid var(--light-border);
    background: white;
    transition: all 0.3s;
    animation: fadeIn 0.3s ease;
}

#screenshotsPreview .screenshot-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--primary-light);
}

#screenshotsPreview .screenshot-item .preview-screenshot {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}





/* Подсказка о сортировке */
#existingScreenshots::before,
#screenshotsPreview::before {
    content: '';
}
</style>	
	
	
	




















 
  
  
  
  <?php if ($isEdit): ?>
    <input type="hidden" name="EditTorrent" value="1">
	<input type="hidden" name="EditTorrentID" value="<?= htmlspecialchars((string)$EditTorrentID) ?>">

  <?php endif; ?>

  <button type="submit" class="btn btn-primary"><?= $buttonFullText ?></button>
  
  
  
  
  
  
  
  
  
  
  
  

  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
</form>


 <?= $editor['modal']?>

	
	
	
	
	
	
  </div>







<style>
:root {
    --genre-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.genre-tag-btn {
    border-radius: 40px;
    font-size: 0.85rem;
    font-weight: 500;
    padding: 0.5rem 1.2rem;
    transition: var(--genre-transition);
    position: relative;
    overflow: hidden;
    background: white;
    border-width: 1.5px;
    letter-spacing: 0.3px;
}

.genre-tag-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.4s, height 0.4s;
}

.genre-tag-btn:hover::before {
    width: 120%;
    height: 120%;
}

.genre-tag-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.genre-tag-btn.genre-active {
    background: linear-gradient(135deg, currentColor 0%, currentColor 100%);
    color: white !important;
    border-color: transparent !important;
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.genre-tag-btn.genre-active i {
    animation: bounce 0.3s ease;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.genre-tag-btn i {
    transition: transform 0.2s ease;
}

.genre-tag-btn:hover i {
    transform: rotate(5deg) scale(1.1);
}

.bg-gradient-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.btn-gradient-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: white;
    border: none;
    transition: var(--genre-transition);
}

.btn-gradient-secondary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    color: white;
}

.text-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.shadow-sm {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Pulse animation for active tags */
.genre-tag-btn.genre-active {
    animation: gentlePulse 2s infinite;
}

@keyframes gentlePulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.2);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(0, 0, 0, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
    }
}

/* Ripple effect on click */
.genre-tag-btn:active {
    transform: scale(0.96);
}

/* Responsive design */
@media (max-width: 768px) {
    .genre-tag-btn {
        padding: 0.35rem 0.9rem;
        font-size: 0.75rem;
    }
    
    .genre-tag-btn i {
        font-size: 0.9rem;
    }
}

/* Custom scrollbar for genre container */
#genreButtons {
    max-height: 220px;
    overflow-y: auto;
    padding: 0.5rem;
    scrollbar-width: thin;
}

#genreButtons::-webkit-scrollbar {
    width: 6px;
}

#genreButtons::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#genreButtons::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
}

/* Tooltip effect */
.genre-tag-btn {
    position: relative;
}

.genre-tag-btn:hover::after {
    content: attr(data-genre);
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
}

/* Font Awesome specific adjustments */
.fa, .fas, .far, .fab {
    line-height: 1;
}







.category-icon-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.cat-pick-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 18px;
    min-width: 90px;
    border: 1.5px solid #dee2e6;
    border-radius: 14px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6c757d;
    font-size: 0.78rem;
    font-weight: 500;
    line-height: 1.2;
    text-align: center;
}

.cat-pick-btn i {
    font-size: 1.8rem;
    transition: transform 0.2s ease;
}

.cat-pick-btn:hover {
    border-color: #0d6efd;
    color: #0d6efd;
    background: #f0f5ff;
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(13,110,253,0.15);
}

.cat-pick-btn:hover i {
    transform: scale(1.2);
}

.cat-pick-btn.active {
    border-color: #0d6efd;
    border-width: 2px;
    background: #e7f1ff;
    color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13,110,253,0.12);
    transform: translateY(-2px);
}

.cat-pick-btn.active span {
    font-weight: 700;
}


</style>




<script src="<?= $BASEURL ?>/scripts/tags.js"></script>
<script src="<?= $BASEURL ?>/scripts/category_picker.js"></script>


<?

stdfoot();