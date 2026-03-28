<?php
declare(strict_types=1);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

$response = ['success' => false, 'message' => '', 'uploaded' => 0];

try {
    $rootpath = './../';
    require_once $rootpath . 'global.php';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }
    
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 10 * 1024 * 1024; // 10 MB
    
    // Get content type and ID (используем null coalescing operator ??)
    $contentType = $_POST['content_type'] ?? '';
    $contentId = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;
    
    if ($contentType === '' || $contentId === 0) {
        throw new Exception('Content type and ID are required');
    }
    
    // ============ ПРОВЕРКА СУЩЕСТВОВАНИЯ ID ============
    $tableName = match($contentType) { // Используем match expression (PHP 8+)
        'comment' => 'comments',
        'news' => 'news',
        'torrent' => 'torrents',
        'post' => 'tsf_posts',
        'message' => 'privatemessages',
        default => throw new Exception('Invalid content type')
    };
    
    $idField = match($contentType) {
        'post' => 'pid',
        'message' => 'pmid',
        default => 'id'
    };
    
    // Проверяем существует ли запись с таким ID
    $check_result = $db->sql_query("SELECT $idField FROM $tableName WHERE $idField = $contentId");
    if ($db->num_rows($check_result) == 0) {
        throw new Exception("$contentType with ID $contentId does not exist");
    }
    // ============ КОНЕЦ ПРОВЕРКИ ============
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    $files = $_FILES['files'];
    $fileCount = count($files['name']);
    $uploaded = 0;
    $uploadedFiles = [];
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $files['name'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes, true) && !str_starts_with($fileType, 'image/')) { // str_starts_with для PHP 8+
            continue;
        }
        
        // Validate file size
        if ($fileSize > $maxSize) {
            continue;
        }
        
        // Generate unique filename
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $newFileName = uniqid() . '.' . $ext;
        $uploadPath = $uploadDir . $newFileName;
        $fileUrl = $BASEURL . '/uploads/' . $newFileName;
        
        if (move_uploaded_file($fileTmp, $uploadPath)) {
            // Get user ID from session
            $userId = (int)($CURUSER["id"] ?? 0);
            
            // Determine which column to use based on content type
            $contentColumn = match($contentType) {
                'comment' => 'comment_id',
                'news' => 'news_id',
                'torrent' => 'torrent_id',
                'post' => 'post_id',
                'message' => 'messages_id',
                default => null
            };
            
            if ($contentColumn === null) {
                continue;
            }
            
            // Insert into database
            $sql = "INSERT INTO comment_files 
                    (file_name, file_path, file_url, file_type, file_size, user_id, $contentColumn, uploaded_at) 
                    VALUES 
                    ('" . $db->escape_string($fileName) . "', 
                     '" . $db->escape_string($uploadPath) . "', 
                     '" . $db->escape_string($fileUrl) . "', 
                     '" . $db->escape_string($fileType) . "', 
                     " . (int)$fileSize . ", 
                     " . $userId . ", 
                     " . $contentId . ", 
                     NOW())";
            
            if ($db->sql_query($sql)) {
                $uploaded++;
                $uploadedFiles[] = $fileUrl;
            }
        }
    }
    
    // Если загружены изображения и есть контент, добавляем их в текст
    if ($uploaded > 0 && $uploadedFiles !== [] && in_array($contentType, ['comment', 'news', 'post', 'message', 'torrent'], true)) {
        
        // Получаем текущий контент
        $contentInfo = match($contentType) {
            'comment' => ['table' => 'comments', 'field' => 'text', 'id_field' => 'id'],
            'news' => ['table' => 'news', 'field' => 'body', 'id_field' => 'id'],
            'torrent' => ['table' => 'torrents', 'field' => 'descr', 'id_field' => 'id'],
            'post' => ['table' => 'tsf_posts', 'field' => 'message', 'id_field' => 'pid'],
            'message' => ['table' => 'privatemessages', 'field' => 'message', 'id_field' => 'pmid'],
            default => null
        };
        
        if ($contentInfo !== null) {
            $contentTable = $contentInfo['table'];
            $contentField = $contentInfo['field'];
            $contentIdField = $contentInfo['id_field'];
            
            // Получаем текущий текст
            $result = $db->sql_query("SELECT $contentField FROM $contentTable WHERE $contentIdField = $contentId");
            if ($row = $db->fetch_array($result)) {
                $currentText = $row[$contentField];
                
                // Добавляем изображения в текст
                $newImages = '';
                foreach ($uploadedFiles as $fileUrl) {
                    $newImages .= '[img]' . $fileUrl . '[/img]' . "\n";
                }
                
                // Если есть существующий текст, добавляем изображения после него
                $updatedText = $currentText !== '' 
                    ? $currentText . "\n\n" . $newImages 
                    : $newImages;
                
                // Обновляем контент с изображениями
                $update_data = match($contentType) {
                    'torrent', 'news' => [
                        $contentField => $db->escape_string($updatedText)
                    ],
                    'post' => [
                        $contentField => $db->escape_string($updatedText),
                        "edituid" => (int)$CURUSER["id"],
                        "edittime" => TIMENOW,
                        "editreason" => "Added " . count($uploadedFiles) . " new image(s)"
                    ],
                    default => [
                        $contentField => $db->escape_string($updatedText),
                        "editedat" => TIMENOW,
                        "editedby" => $db->escape_string($CURUSER["id"])
                    ]
                };
                
                $db->update_query($contentTable, $update_data, "$contentIdField = $contentId");
                
                // Очищаем кеш для новостей если нужно
                if ($contentType === 'news') {
                    $cache->update_news();
                }
            }
        }
    }
    
    if ($uploaded > 0) {
        $response['success'] = true;
        $response['message'] = "$uploaded file(s) uploaded successfully";
        $response['uploaded'] = $uploaded;
    } else {
        $response['message'] = 'No files were uploaded';
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();
?>