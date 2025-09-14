<?php
define("SCRIPTNAME", "upload_image.php");
require_once 'global.php'; // Подключаем общие зависимости

header('Content-Type: application/json');

if (isset($_POST['upload_type']) && $_POST['upload_type'] === 'editor_image') 
{
    header('Content-Type: application/json');
    
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    $maxSize = 200 * 1024 * 1024; // 200MB

    try {
        if (!isset($_FILES['image'])) {
            throw new Exception('Файл не был загружен');
        }

        $file = $_FILES['image'];

        // Проверки безопасности
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception(getUploadError($file['error']));
        }
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Недопустимый тип файла');
        }
        if ($file['size'] > $maxSize) {
            throw new Exception('Файл слишком большой (макс. 200MB)');
        }

        // Создаем папку если не существует
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '.' . $ext;
        $destination = $uploadDir . $filename;

        // Сохраняем файл
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Ошибка сохранения файла');
        }

        // URL
        $url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') 
               . $_SERVER['HTTP_HOST'] 
               . '/uploads/' . $filename;

        // Сохраняем данные в БД
        $user_id = isset($CURUSER['id']) ? (int)$CURUSER['id'] : null;
        $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : null;
		$news_id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : null;
		$t_id = isset($_POST['torrent_id']) ? (int)$_POST['torrent_id'] : null;
		

        $sql = "INSERT INTO comment_files 
                (comment_id, news_id, torrent_id, user_id, file_name, file_path, file_url, file_type, file_size, uploaded_at)
                VALUES 
                (" . ($comment_id ?: 'NULL') . ",
				 " . ($news_id ?: 'NULL') . ", 
				 " . ($t_id ?: 'NULL') . ", 
                 " . ($user_id ?: 'NULL') . ", 
                 " . $db->sqlesc($file['name']) . ", 
                 " . $db->sqlesc($destination) . ", 
                 " . $db->sqlesc($url) . ", 
                 " . $db->sqlesc($file['type']) . ", 
                 " . (int)$file['size'] . ", 
                 NOW())";

        $db->sql_query($sql);
        $insert_id = $db->insert_id();

        echo json_encode([
            'success' => true,
            'url' => $url,
            'file_id' => $insert_id,
            'type' => 'editor_image'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'type' => 'editor_image'
        ]);
        exit;
    }
}
