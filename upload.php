<?php

define("SCRIPTNAME", "upload.php");
require_once 'global.php';
require(INC_PATH . '/functions_category.php');

require_once 'cache/smilies.php';


require_once __DIR__ . '/vendor/autoload.php';

use Arokettu\Torrent\TorrentFile;




// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';


// Вызов функции
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

        //$db->sql_query("DELETE FROM `screenshots` WHERE id = '{$screenshot_id}'");
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









$lang->load('upload');


$is_mod = is_mod($usergroups);

$query = $db->simple_select("ts_u_perm", "userid", "userid='".$db->escape_string($CURUSER['id'])."' AND canupload = '0'");

if ($db->num_rows($query)) 
{
    print_no_permission(false, true, $lang->upload["uploaderform"]);
	  
}





if (strtoupper($_SERVER["REQUEST_METHOD"]) == "POST")
{



header('Content-Type: application/json');










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


if ($torrentFilename) {
    $metadata['filename'] = $db->escape_string($torrentFilename);
    $metadata['info_hash'] = $db->escape_string($info_hash);
    //$metadata['size'] = $db->escape_string($size);
    //$metadata['numfiles'] = $db->escape_string($numfiles);
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

    // Возвращаем новый стартовый номер с шагом
    return $maxNum + $step;
}



if (!empty($screenshotFilenames)) 
{
    // Получаем стартовый номер
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

        //$escapedFilename = $db->escape_string($newFilename);
        //$db->sql_query("INSERT INTO `screenshots` (`torrent_id`, `filename`) VALUES ('{$NewTID}', '{$escapedFilename}')");
		
		
		
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
    $res = $db->sql_query("SELECT id, filename FROM `screenshots` WHERE torrent_id = '{$EditTorrentID}'");
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









<div class="modal fade" id="deleteScreenshotModal" tabindex="-1" aria-labelledby="deleteScreenshotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteScreenshotModalLabel">Delete Screenshot</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this screenshot?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteScreenshotBtn">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>



<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
  <div id="screenshotToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMessage">
        Screenshot deleted successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
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
	
	
	 
    
	
	
<form method="post" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']) . ($EditTorrent ? '?id=' . urlencode($EditTorrentID) : ''); ?>" id="torrent-upload-form" enctype="multipart/form-data">
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



  
  
  
  
 
<!-- Torrent File -->
<div class="mb-4">
    <label for="torrentFile" class="form-label fw-semibold">
        <i class="fas fa-file-alt me-1 text-primary"></i>Torrent File
        <?php if (!$isEdit): ?>
            <span class="text-danger">*</span>
        <?php endif; ?>
    </label>
    
    <div class="input-group has-validation">
        <span class="input-group-text bg-light">
            <i class="fas fa-cloud-upload-alt text-muted"></i>
        </span>
        
        <input 
            class="form-control" 
            type="file" 
            id="torrentFile" 
            name="torrentFile" 
            accept=".torrent" 
            <?= $isEdit ? '' : 'required' ?>
        />
        
        <div class="invalid-feedback">Please select a valid .torrent file</div>
    </div>
    
    <!-- File Info -->
    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Select your .torrent file <?= $isEdit ? '(optional for edit)' : '' ?>
        </small>
        <small class="text-muted file-info" id="torrentFileInfo"></small>
    </div>
    
    <!-- File Preview -->
    <div class="mt-2" id="torrentFilePreview"></div>
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
    <label for="nfoFile" class="form-label">NFO File</label>
    <input class="form-control" type="file" id="nfoFile" name="nfoFile" accept=".nfo,text/plain" />
    <div class="form-text">Optional: Upload your .nfo file</div>
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









 <!-- Upload IMDB -->
<div class="mb-3">
  <label for="imdbUrl" class="form-label">IMDB Link</label>
  
  
  
  
  <input type="url" class="form-control" id="imdbUrl" name="imdbUrl" placeholder="Example: https://www.imdb.com/title/tt0913354/" value="<?= isset($t_link) ? htmlspecialchars($t_link) : '' ?>">
  
  
</div>
  

  
  
 
 <!-- Choose Upload Method -->
<div class="mb-3">
  <label class="form-label">Select Image Upload Method</label>
  <div>
    <input type="radio" class="form-check-input me-1" name="uploadType" id="uploadByUrl" value="url" checked>
    <label for="uploadByUrl" class="form-check-label me-3">Upload by URL</label>

    <input type="radio" class="form-check-input me-1" name="uploadType" id="uploadByFile" value="file">
    <label for="uploadByFile" class="form-check-label">Upload from Device</label>
  </div>
</div>








<!-- Upload Image by URL -->
<div class="mb-3" id="uploadUrlGroup">
  <label for="imageUrl" class="form-label">Upload Image by URL</label>
  
  
  
  <input type="url" class="form-control" id="imageUrl" name="imageUrl" value="<?= isset($torrent['t_image']) ? htmlspecialchars($torrent['t_image']) : '' ?>"placeholder="https://example.com/image.jpg">
  
  
  
  <div class="form-text">Paste the direct image URL (jpg, png, etc.)</div>
  <div id="image22Preview" class="d-flex flex-wrap mt-2"></div>
</div>




<!-- Upload from Device -->
<div class="mb-3 d-none" id="uploadFileGroup">
  <label for="imagesUpload" class="form-label">Upload Images (Screenshots, Posters, etc.)</label>
  <input class="form-control" type="file" id="imagesUpload" name="imagesUpload" accept="image/*" />
  <div id="imagesPreview" class="d-flex flex-wrap mt-2"></div>
</div>
  
  
  
  
  

  
  

<!-- Choose Upload Method for Image 2 -->
<div class="mb-3">
  <label class="form-label">Select Image 2 Upload Method</label>
  <div>
    <input type="radio" class="form-check-input me-1" name="uploadType2" id="uploadByUrl2" value="url" checked>
    <label for="uploadByUrl2" class="form-check-label me-3">Upload by URL</label>

    <input type="radio" class="form-check-input me-1" name="uploadType2" id="uploadByFile2" value="file">
    <label for="uploadByFile2" class="form-check-label">Upload from Device</label>
  </div>
</div>

<!-- Upload Image 2 by URL -->
<div class="mb-3" id="uploadUrlGroup2">
  <label for="imageUrl2" class="form-label">Upload Image 2 by URL</label>
  
 
  
  <input type="url" class="form-control" id="imageUrl2" name="imageUrl2" value="<?= isset($torrent['t_image2']) ? htmlspecialchars($torrent['t_image2']) : '' ?>"placeholder="https://example.com/image2.jpg">
  
  
  
  <div class="form-text">Paste the direct image URL (jpg, png, etc.)</div>
  <div id="image22Preview2" class="d-flex flex-wrap mt-2"></div>
</div>

<!-- Upload Image 2 from Device -->
<div class="mb-3 d-none" id="uploadFileGroup2">
  <label for="imagesUpload2" class="form-label">Upload Images 2 (Screenshots, Posters, etc.)</label>
  <input class="form-control" type="file" id="imagesUpload2" name="imagesUpload2" accept="image/*" />
  <div id="imagesPreview2" class="d-flex flex-wrap mt-2"></div>
</div>












<table>
	  
      
	  
	  
	  <!-- Anonymous Upload checkbox -->
<tr>
  <td class="none">
    <b>Anonymous Upload</b><br />
    
	
	
	<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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
<div class="mb-3">
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

<div class="mb-3">
  <label for="screenshotsUpload" class="form-label">Upload Screenshots</label>
  <input class="form-control" type="file" id="screenshotsUpload" name="screenshotsUpload[]" accept="image/*" multiple />
  <div id="screenshotsPreview" class="d-flex flex-wrap mt-2"></div>
</div>

<?php if (!empty($screenshots)): ?>
  <label class="form-label mt-3">Current Screenshots</label>
  <div class="d-flex flex-wrap" id="existingScreenshots">
    <?php foreach ($screenshots as $shot): ?>
      <div class="position-relative m-1 screenshot-item" data-id="<?php echo (int)$shot['id']; ?>">
        <img src="/torrents/screens/<?php echo htmlspecialchars($shot['filename']); ?>" class="img-thumbnail" style="max-width:100px;">
        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-screenshot-btn">&times;</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

 
  
  
  
  <?php if ($isEdit): ?>
    <input type="hidden" name="EditTorrent" value="1">
    <input type="hidden" name="EditTorrentID" value="<?= htmlspecialchars($EditTorrentID) ?>">
  <?php endif; ?>

  <button type="submit" class="btn btn-primary"><?= $buttonFullText ?></button>
  
  
  
  
</form>


 <?= $editor['modal']?>

	
	
	
	
	
	
  </div>

<!-- JavaScripts -->



<script src="<?= $BASEURL; ?>/scripts/upload_torrent.js"></script>










<?

stdfoot();
