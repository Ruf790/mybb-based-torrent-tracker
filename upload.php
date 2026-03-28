<?php

declare(strict_types=1);

define("IN_MYBB", 1);
define("SCRIPTNAME", "upload.php");
require_once 'global.php';
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
    $row = $db->fetch_array($db->sql_query("SELECT * FROM `screenshots` WHERE id = '{$screenshot_id}'"));

    if ($row) 
	{
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/' . $row['filename'];
        if (file_exists($filePath)) 
		{
            unlink($filePath);
        }

		$db->delete_query("screenshots", "id='$screenshot_id'");

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

    $order = array_map('intval', $_POST['order']);

    foreach ($order as $position => $screenshotId) {
        if ($screenshotId <= 0) continue;
        $db->update_query(
            "screenshots",
            ['sort_order' => (int)$position],
            "id='" . $screenshotId . "'"
        );
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

    $escaped = $db->escape_string($info_hash);
    $query   = $db->simple_select("torrents", "id, name, added", "info_hash='{$escaped}'", ['limit' => 1]);
    $torrent = $db->fetch_array($query);

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

$query = $db->simple_select("users_perm", "userid", "userid='".$db->escape_string($CURUSER['id'])."' AND canupload = '0'");

if ($db->num_rows($query)) 
{
    print_no_permission(false, true, $lang->upload["uploaderform"]);
	  
}








if (strtoupper($_SERVER["REQUEST_METHOD"]) == "POST")
{



header('Content-Type: application/json');



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
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) 
	{
        return false;
    }

    $filename = basename($file['name']);
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




// Check if external torrent is being uploaded
$externalTorrent = isset($_POST['externalTorrent']) && $_POST['externalTorrent'] === 'yes';



// Save uploaded torrent file
$torrentFilename = null;
$info_hash = '';
$size = 0;
$numfiles = 0;





if (!$isEdit || ($torrentFile && $torrentFile['error'] === UPLOAD_ERR_OK)) 
{
    // Загружен новый файл
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




// Screenshots
$screenshotFilenames = [];
if (!empty($_FILES['screenshotsUpload']['tmp_name'][0])) 
{
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
            include_once(INC_PATH . '/ts_imdb.php');

            // Escape IMDb link
            $t_link = $db->escape_string($t_link);

            // Escape Genre (populated by ts_imdb.php)
            $Genre = isset($Genre) ? $db->escape_string($Genre) : '';
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
    'name' => $db->escape_string($torrentName),
    't_link' => $t_link,
    'tags' => $Genre,
    //'owner' => $db->escape_string($CURUSER['id']),
    'category' => $category,
    'anonymous' => $db->escape_string($anonymous),
    'isrequest' => $db->escape_string($request),
    'free' => $db->escape_string($free),
    'silver' => $db->escape_string($silver),
    'doubleupload' => $db->escape_string($doubleUpload),
    'allowcomments' => $db->escape_string($allowComments),
    'sticky' => $db->escape_string($sticky),
    'isnuked' => $db->escape_string($isNuked),
    'WhyNuked' => $db->escape_string($nukeReason),
    'descr' => $db->escape_string($description)
    
);



// Добавляем дату добавления только при новом торрента
if (!$isEdit) 
{
    $metadata['owner'] = $db->escape_string($CURUSER['id']);
	$metadata['added'] = TIMENOW;
}


if ($torrentFilename) 
{
    $metadata['filename'] = $db->escape_string($torrentFilename);
    $metadata['info_hash'] = $db->escape_string($info_hash);
	$metadata['size'] = (int)$size;
    $metadata['numfiles'] = (int)$numfiles;
}


// Check if it's an external torrent and update accordingly
if ($externalTorrent) 
{
    $metadata['ts_external'] = 'yes';
    $metadata['ts_external_url'] = $db->escape_string($torrentObj->getAnnounce() ?? '');  // assuming `announce()` returns the correct URL
    $metadata['visible'] = 'yes';
} 
else 
{
    $metadata['ts_external'] = 'no';
    $metadata['ts_external_url'] = '';
    $metadata['visible'] = 'no';
    
}






// Assuming you have a database instance `$db`


    if ($isEdit) 
	{
        $EditTorrentID = (int)$_POST['EditTorrentID'];

        // Update database entry
        $db->update_query("torrents", $metadata, "id='{$EditTorrentID}'");
        $NewTID = $EditTorrentID;
		
		
		
        if (!empty($_POST['file_ids'])) 
		{
             $file_ids = array_map('intval', $_POST['file_ids']); // защита
             $id_list  = implode(',', $file_ids);

             if (!empty($id_list)) 
		     {
                 $db->sql_query("
                     UPDATE comment_files 
                     SET torrent_id = " . (int)$NewTID . "
                     WHERE id IN ($id_list)
                     ");
             }
        }
		
		
		
		
		write_log( sprintf($lang->upload['editedtorrent'], '[URL='.$BASEURL."/".get_torrent_link($EditTorrentID).']<font color=red>' . $torrentName . '</font>[/URL]', '[URL='.$BASEURL . '/'.get_profile_link($CURUSER['id']).']' . format_name($CURUSER['username'],$CURUSER['usergroup']) . '[/URL]'));
		$cache->update_torrents();
		

  
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
           $row = $db->fetch_array($db->simple_select("torrents", "t_image", "id='{$NewTID}'"));
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
           $row = $db->fetch_array($db->simple_select("torrents", "t_image2", "id='{$NewTID}'"));
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
            $db->update_query("torrents", $UpdateSet, "id='{$NewTID}'");
        }
		
		
		
		
    } 
	else 
	{
        // Insert new torrent record
        $db->insert_query("torrents", $metadata);
        $NewTID = $db->insert_id();
		
		// Привязываем загруженные файлы к этому комментарию
        if (!empty($_POST['file_ids'])) 
		{
             $file_ids = array_map('intval', $_POST['file_ids']); // защита
             $id_list  = implode(',', $file_ids);

             if (!empty($id_list)) 
		     {
                 $db->sql_query("
                     UPDATE comment_files 
                     SET torrent_id = " . (int)$NewTID . "
                     WHERE id IN ($id_list)
                     ");
             }
        }
		
		
		
		
		
		// Now log the upload event
        write_log(sprintf($lang->upload['newtorrent'],'[URL=' . $BASEURL . "/" . get_torrent_link($NewTID) . ']<font color=red>' . $torrentName . '</font>[/URL]','[URL=' . $BASEURL . '/' . get_profile_link($CURUSER['id']) . ']' . format_name($CURUSER['username'], $CURUSER['usergroup']) . '[/URL]'));
        $cache->update_torrents();
		
		notify_upload_subscribers((int)$category, $NewTID, $torrentName);
		
		
    }
	
	
	
	
	
function get_next_screenshot_number($torrent_id, $db, $step = 3)
{
    $maxNum = 0;
    $existingScreenshots = [];

    // Загружаем существующие имена файлов
    $res = $db->sql_query("SELECT filename FROM `screenshots` WHERE torrent_id = '{$torrent_id}'");
    while ($row = $db->fetch_array($res)) 
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

		
		
		$insert_array = array(
			'torrent_id' => $db->escape_string($NewTID),
			'filename' => $db->escape_string($newFilename),
			'uploaded_at' => TIMENOW
		);
		$db->insert_query("screenshots", $insert_array);
		

        $count++;
    }
}








// Rename the torrent file using NewTID
$originalTorrentPath = $torrentDir . $torrentFilename;
$finalTorrentFilename = $NewTID . '.torrent';
$finalTorrentPath = $torrentDir . $finalTorrentFilename;

if ($torrentFilename && file_exists($originalTorrentPath)) 
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
    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $imageFile = $_FILES['imagesUpload'];

    if ($imageFile['error'] === 0 && in_array($imageFile['type'], $allowedMimeTypes, true)) 
    {
        // Get image extension from MIME type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];

        $ext = isset($mimeToExt[$imageFile['type']]) ? $mimeToExt[$imageFile['type']] : null;

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
				
				$update_image = array(
			        "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		        );
						   
				$db->update_query("torrents", $update_image, "id='{$NewTID}'");
				
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
    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $imageFile = $_FILES['imagesUpload2'];

    if ($imageFile['error'] === 0 && in_array($imageFile['type'], $allowedMimeTypes, true)) 
    {
        // Get image extension from MIME type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];

        $ext = isset($mimeToExt[$imageFile['type']]) ? $mimeToExt[$imageFile['type']] : null;

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
				
				$update_image2 = array(
			        "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL2)
		        );
						   
				$db->update_query("torrents", $update_image2, "id='{$NewTID}'");
				
				
				
				
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
                $db->update_query("torrents", ["t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)], "id='{$NewTID}'");
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
                $db->update_query("torrents", ["t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL2)], "id='{$NewTID}'");
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
        if (file_exists(INC_PATH . '/functions_ts_remote_connect.php')) {
            include_once(INC_PATH . '/functions_ts_remote_connect.php');
            $imgData = TS_Fetch_Data($screenshotUrl, false);
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
            $db->insert_query("screenshots", [
                'torrent_id'  => $db->escape_string($NewTID),
                'filename'    => $db->escape_string($filename),
                'uploaded_at' => TIMENOW
            ]);
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
        $escaped     = $db->escape_string($nfoUtf8);
        $existingNfo = $db->simple_select("torrents_nfo", "id", "torrent_id='{$NewTID}'", ['limit' => 1]);

        if ($db->num_rows($existingNfo)) {
            $db->update_query("torrents_nfo",
                ['nfo' => $escaped, 'uploaded_at' => TIMENOW],
                "torrent_id='{$NewTID}'"
            );
        } else {
            $db->insert_query("torrents_nfo", [
                'torrent_id'  => (int)$NewTID,
                'nfo'         => $escaped,
                'uploaded_at' => TIMENOW
            ]);
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


















stdhead('Upload Torrent');






























$torrent = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) 
{
    $EditTorrent = true;
    $EditTorrentID = (int)$_GET['id'];

    // Sanitize input by casting to int (already done)
    //$query = "SELECT * FROM torrents WHERE id = $EditTorrentID";

    // Execute query
    $result = $db->sql_query("SELECT * FROM torrents WHERE id = $EditTorrentID");

    // Fetch the result as an associative array
    $torrent = $db->fetch_array($result);
	
	
	
	// Получить все скрины торрента
    $screenshots = [];
    $res = $db->sql_query("SELECT id, filename FROM `screenshots` WHERE torrent_id = '{$EditTorrentID}' ORDER BY sort_order ASC, id ASC");
    while ($row = $db->fetch_array($res)) 
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
$headingText = $isEdit ? "Edit Torrent" : "Upload Torrent";

// Заголовок: иконка + стиль
if ($isEdit) 
{
    $icon = '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>'; // иконка
    $style = 'style="font-weight: 700; color: #0d6efd;"'; // синий заголовок
    $buttonIcon = '<i class="fa-solid fa-pen-to-square me-1"></i>'; // оранжевая иконка для кнопки
    $buttonText = "Update Torrent";
}
else 
{
    $icon = '<i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>'; // синяя иконка
    $style = 'style="font-weight: 700; color: #0d6efd;"'; // синий заголовок
    $buttonIcon = '<i class="fa-solid fa-upload me-1"></i>'; // синяя иконка для кнопки
    $buttonText = "Upload Torrent";
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
    <strong>Important!</strong> ' . sprintf($lang->upload["title2"], '<code id="announceUrl">' . $AnnounceURL . '</code>') . '
  </div>
  <button type="button" class="btn btn-sm btn-outline-primary ms-0 ms-md-3 mt-2 mt-md-0" onclick="copyAnnounceUrl()">Copy</button>
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



<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="errorModalLabel">Upload Error</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="errorModalBody">
        <!-- Error message will be inserted here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>




<!-- Upload Complete Modal -->
<div class="modal fade" id="uploadCompleteModal" tabindex="-1" aria-labelledby="uploadCompleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-success text-white border-0">
        <div class="d-flex align-items-center w-100">
          <i class="fas fa-check-circle fa-2x me-3"></i>
          <h5 class="modal-title mb-0" id="uploadCompleteModalLabel">Upload Successful!</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="success-animation mb-3">
          <i class="fas fa-check text-success" style="font-size: 3rem;"></i>
        </div>
        <h4 class="text-success mb-2">Congratulations!</h4>
        <p class="text-muted mb-0">Your torrent has been successfully uploaded and is now live.</p>
        <p class="text-muted">You will be redirected shortly...</p>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
          <i class="fas fa-times me-2"></i>Stay Here
        </button>
        <button type="button" class="btn btn-success" onclick="redirectToTorrent()">
          <i class="fas fa-eye me-2"></i>View Torrent
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
          <h5 class="modal-title mb-0" id="uploadModalLabel">Processing Upload</h5>
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
        <h6 class="text-primary mb-2">Uploading your content...</h6>
        <p class="text-muted mb-3">Please wait while we process your torrent and files</p>
        
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
            <small class="text-muted" id="uploadStatusText">Initializing upload process...</small>
            <small class="text-primary fw-bold float-end" id="progressPercentage">0%</small>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 justify-content-center pb-4">
        <small class="text-muted">
          <i class="fas fa-info-circle me-1"></i>
          This may take a few moments depending on file sizes
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
          <i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion
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
            <h5 class="fw-bold mb-1" id="deleteScreenshotTitle">Delete Screenshot?</h5>
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
              <span class="text-muted">No preview available</span>
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
              <strong>Warning:</strong> This action cannot be undone!
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteScreenshotBtn">
          <i class="fas fa-trash-alt me-1"></i> Yes, Delete
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
        <i class="fas fa-heading me-1 text-primary"></i>Torrent Name
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
            placeholder="Enter torrent name (min 3 characters)" 
            required 
            minlength="3" 
            maxlength="255" 
            value="<?= isset($torrent['name']) ? htmlspecialchars($torrent['name']) : '' ?>"
        />
        
        <div class="invalid-feedback">Please enter a valid torrent name (3-255 characters)</div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i>Choose a descriptive name for your torrent
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
        <i class="fas fa-file-alt me-1 text-primary"></i>Torrent File
        <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
    </label>

    <!-- Drop Zone -->
    <div id="torrentDropZone"
         class="border border-2 border-dashed rounded-3 p-4 text-center position-relative"
         style="border-color: #0d6efd !important; cursor: pointer; transition: all 0.3s ease; background: #f8f9ff;">

        <!-- Иконка и текст -->
        <div id="torrentDropContent">
            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3 d-block"></i>
            <h6 class="fw-bold mb-1">Drag & Drop your .torrent file here</h6>
            <p class="text-muted small mb-3">or click to browse files</p>
           <button type="button" class="btn btn-outline-primary btn-sm px-4">
                <i class="fas fa-folder-open me-2"></i>Browse File
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
        <small class="text-muted">Validating torrent file...</small>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Accepted format: .torrent <?= $isEdit ? '(optional for edit)' : '' ?>
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
                <i class="fas fa-info-circle text-primary me-2"></i>Torrent Info
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
                        <i class="fas fa-list me-1"></i>File List
                    </small>
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2"
                            onclick="toggleTorrentFileList()">
                        <i class="fas fa-eye me-1" id="torrentFileListToggleIcon"></i>
                        <span id="torrentFileListToggleText">Show</span>
                    </button>
                </div>
                <div id="torrentFileList" style="display:none; max-height:200px; overflow-y:auto;">
                    <!-- Список вставляется через JS -->
                </div>
            </div>
        </div>
    </div>
</div>



    
<!-- External Torrent Checkbox -->
<?php if (!isset($privatetrackerpatch) || $privatetrackerpatch !== "yes"): ?>
<!-- External Torrent Checkbox -->
<div class="mb-3">
    <div class="form-check form-switch">
        <input 
            class="form-check-input" 
            type="checkbox" 
            id="externalTorrent" 
            name="externalTorrent" 
            value="yes" 
            role="switch"
            <?= isset($torrent['ts_external']) && $torrent['ts_external'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold text-success" for="externalTorrent">
            <i class="fas fa-link me-1"></i> External Torrent
        </label>
        <div class="form-text">Torrent is linked from another tracker</div>
    </div>
</div>
<?php endif; ?>
  
  
  
  
  
  
  
  
  

<!-- NFO File -->
<div class="mb-3">
    <label for="nfoFile" class="form-label fw-semibold">
        <i class="fas fa-file-alt me-1 text-primary"></i>NFO File
        <span class="text-muted fw-normal small">(optional)</span>
    </label>

    <div class="input-group">
        <input class="form-control" type="file" id="nfoFile" name="nfoFile" accept=".nfo,text/plain" />
        <button type="button" class="btn btn-outline-secondary" id="nfoPreviewBtn"
                style="display:none;" onclick="toggleNfoPreview()">
            <i class="fas fa-eye me-1"></i>Preview
        </button>
    </div>
    <div class="form-text">Optional: Upload your .nfo file (stored in database)</div>

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
    $existingNfoRow = $db->fetch_array($db->simple_select("torrents_nfo", "id", "id='{$EditTorrentID}'"));
    if ($existingNfoRow):
    ?>
    <div class="mt-2 d-flex align-items-center gap-2">
        <span class="badge bg-success">
            <i class="fas fa-check me-1"></i>NFO already uploaded
        </span>
        <small class="text-muted">Upload a new file to replace it</small>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

  
  
  
  
  
  <?= $editor['toolbar']?>
  
  
<!-- Description with BBCode textarea -->
<div class="mb-3">
  <label for="description" class="form-label">Description</label>

  
  <!-- Textarea -->
  <textarea 
    class="form-control" 
    id="description" 
    name="description" 
    rows="10" 
    required 
    placeholder="Enter Torrent Description..."><?= isset($torrent['descr']) ? htmlspecialchars($torrent['descr']) : '' ?></textarea>

  <div class="form-text text-end"><span id="charCount2">0 / 500</span></div>
  <div class="invalid-feedback">Please enter a description.</div>
  



 
 
  
</div>


    <!-- Category Dropdown -->
    <div class="mb-3">
      <label for="category" class="form-label">Select Category</label>
      <?php
        // Call the function to generate the category select list
        $category = isset($torrent['category']) ? intval($torrent['category']) : 0;
        echo ts_category_list('category', $category);
		

		
      ?>
      <div class="invalid-feedback">Please select a category.</div>
    </div>









<div class="section-label">
                        <i class="fas fa-images"></i>
                        Media & Images
                    </div>



<!-- IMDb Link -->
<!-- IMDb Link -->
<div class="col-md-12 mb-4">
    <label class="form-label fw-semibold">
        <i class="fab fa-imdb text-warning me-2"></i>IMDb Link
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
               placeholder="Example: https://www.imdb.com/title/tt1234567/">
        <button type="button" class="btn btn-warning" id="imdbFetchBtn" onclick="fetchImdbData()">
            <i class="fab fa-imdb me-1"></i>Fetch
        </button>
    </div>
    <div class="form-text">
        <i class="fas fa-info-circle me-1"></i>
        Paste IMDb URL and click Fetch to auto-fill poster and info
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
                                    onclick="applyImdbPoster('main')" title="Set as Main Image">
                                <i class="fas fa-image me-1"></i>Main
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2 w-100"
                                    onclick="applyImdbPoster('secondary')" title="Set as Secondary Image">
                                <i class="fas fa-images me-1"></i>2nd
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
                                <i class="fas fa-paste me-1"></i>Add to Description
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
            Fetching IMDb data...
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
                    Main Image
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
                    <label for="imageUrl" class="form-label">Image URL</label>
                    <input type="url" 
                           class="form-control form-control-custom" 
                           id="imageUrl" 
                           name="imageUrl" 
                           value="<?= htmlspecialchars($torrent['t_image'] ?? '') ?>" 
                           placeholder="https://example.com/image.jpg"
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
                        <p class="mb-1 fw-semibold">Click to upload image</p>
                        <p class="small text-muted mb-0">or drag & drop</p>
                        <input type="file" 
                               class="d-none" 
                               id="imagesUpload" 
                               name="imagesUpload" 
                               accept="image/*"
                               onchange="handleImageUpload(this, 'imagePreview')">
                    </div>
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        JPG, PNG, GIF, WebP (max 10MB)
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
                    Secondary Image
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
                    <label for="imageUrl2" class="form-label">Image URL</label>
                    <input type="url" 
                           class="form-control form-control-custom" 
                           id="imageUrl2" 
                           name="imageUrl2" 
                           value="<?= htmlspecialchars($torrent['t_image2'] ?? '') ?>" 
                           placeholder="https://example.com/image2.jpg"
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
                        <p class="mb-1 fw-semibold">Click to upload image</p>
                        <p class="small text-muted mb-0">or drag & drop</p>
                        <input type="file" 
                               class="d-none" 
                               id="imagesUpload2" 
                               name="imagesUpload2" 
                               accept="image/*"
                               onchange="handleImageUpload(this, 'imagePreview2')">
                    </div>
                    <div class="form-text mt-1">
                        <i class="fas fa-info-circle text-muted me-1"></i>
                        JPG, PNG, GIF, WebP (max 10MB)
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
                        Torrent Settings
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
            <?= isset($torrent['anonymous']) && $torrent['anonymous'] === 'yes' ? 'checked' : '' ?> 
        />
        <label class="form-check-label fw-bold" for="anonymous">
            <i class="fas fa-user-secret me-1"></i> Anonymous Upload
        </label>
        <div class="form-text">Check this box if you want to upload this torrent anonymously</div>
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
            <i class="fas fa-hand-paper me-1"></i> Requested Torrent
        </label>
        <div class="form-text">Please check this box if you are uploading a requested torrent</div>
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
            <i class="fas fa-gift me-1"></i> Free Torrent
        </label>
        <div class="form-text">Mark this torrent as FREE! Only Upload stats will be recorded!</div>
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
            <i class="fas fa-star me-1"></i> Silver Torrent
        </label>
        <div class="form-text">Mark this torrent as SILVER! Only 50% Download stats will be recorded!</div>
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
            <i class="fas fa-bolt me-1"></i> x2 Torrent
        </label>
        <div class="form-text">Mark this torrent as x2! Give Double Upload stats for this torrent</div>
    </div>
</div>
	  
	  
	  
	  
   </td>

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
            <i class="fas fa-comment-slash me-1"></i> Disable Comments
        </label>
        <div class="form-text">Check this box to disable comments on this Torrent!</div>
    </div>
</div>
	  
	  
	  
   </td>
</tr>

<tr>
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
            <i class="fas fa-thumbtack me-1"></i> Sticky Torrent
        </label>
        <div class="form-text">Check this box to set this torrent as Sticky</div>
    </div>
</div>
	  
	  
	  
   </td>

   
   
   
   
   
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
            <i class="fas fa-radiation me-1"></i> Nuked Torrent
        </label>
        <div class="form-text">Please check this box if you want to Nuke this torrent</div>
    </div>
</div>
   
   
   
   
   <!-- Nuke Reason (conditional) -->
<div class="mb-3" id="nukereason" style="<?= isset($torrent['isnuked']) && $torrent['isnuked'] === 'yes' ? '' : 'display:none;' ?>">
    <label for="WhyNuked" class="form-label fw-bold text-danger">
        <i class="fas fa-exclamation-circle me-1"></i> Nuke Reason
    </label>
    <input 
        type="text" 
        class="form-control" 
        id="WhyNuked" 
        name="WhyNuked" 
        placeholder="Enter reason for nuking this torrent"
        value="<?= isset($torrent['WhyNuked']) ? htmlspecialchars($torrent['WhyNuked']) : '' ?>" 
    />
    <div class="form-text">Please provide a reason for nuking this torrent</div>
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
                Screenshots
            </h6>

            <!-- Переключатель между File и URL -->
            <div class="mb-3">
                <div class="btn-group btn-group-sm w-100" role="group">
                    <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByFile" value="file" checked>
                    <label class="btn btn-outline-primary" for="screenshotByFile">
                        <i class="fas fa-upload me-1"></i>Upload Files
                    </label>
                    <input type="radio" class="btn-check" name="screenshotUploadType" id="screenshotByUrl" value="url">
                    <label class="btn btn-outline-primary" for="screenshotByUrl">
                        <i class="fas fa-link me-1"></i>Bulk URL
                    </label>
                </div>
            </div>

            <!-- Upload Files -->
            <div id="screenshotFileGroup">
                <div class="upload-zone mb-3" onclick="document.getElementById('screenshotsUpload').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h6>Drop screenshots here</h6>
                    <p class="text-muted mb-0">Multiple files allowed</p>
                    <input type="file"
                           class="d-none"
                           id="screenshotsUpload"
                           name="screenshotsUpload[]"
                           multiple
                           accept="image/*">
                </div>
                <!-- Preview ВНУТРИ screenshotFileGroup -->
                <div id="screenshotsPreview" class="preview-container"></div>
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
                            <i class="fas fa-info-circle me-1"></i>Supported: jpg, png, gif, webp
                        </small>
                        <button type="button" class="btn btn-primary btn-sm" onclick="loadScreenshotUrls()">
                            <i class="fas fa-eye me-1"></i>Load Previews
                        </button>
                    </div>
                </div>
                <div id="screenshotUrlPreview" class="preview-container mt-2"></div>
                <div id="screenshotUrlInputs"></div>
            </div>

            <!-- Existing Screenshots -->
            <?php if(!empty($screenshots)): ?>
                <h6 class="mt-4 mb-3">Existing Screenshots</h6>
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

<!-- JavaScripts -->














<?

stdfoot();
