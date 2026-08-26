<?php
declare(strict_types=1);

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');

$response = ['success' => false, 'message' => '', 'uploaded' => 0];

try {
    $rootpath = './../';
    if (!defined('IN_ADMINCP')) {
        define('IN_ADMINCP', true);
    }
    require_once $rootpath . 'global.php';
    require_once INC_PATH . '/functions_image_recode.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Admin-only. Раньше эта проверка отсутствовала вообще - файл лежит в
    // admin/, но принять запрос мог кто угодно, включая незалогиненного
    // анонима.
    if (empty($CURUSER['id']) || !is_mod($usergroups)) {
        http_response_code(403);
        throw new Exception('Access denied. Staff only.');
    }

    // CSRF. Раньше отсутствовала полностью.
    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';

    // Реальный MIME по содержимому файла - $_FILES[...]['type'] это просто
    // Content-Type заголовок ОТ КЛИЕНТА, тривиально подделывается и не может
    // использоваться как проверка безопасности сама по себе.
    $allowedMime = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Явный whitelist расширений. Раньше расширение бралось из имени файла
    // клиента без всякой проверки и приклеивалось к серверному имени - то
    // есть можно было залить .php/.phtml с подделанным Content-Type и
    // получить выполнение кода на сервере (файл сохранялся прямо под
    // DOCUMENT_ROOT).
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];

    $maxSize = 10 * 1024 * 1024; // 10 MB

    $contentType = $_POST['content_type'] ?? '';
    $contentId   = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;

    if ($contentType === '' || $contentId === 0) {
        throw new Exception('Content type and ID are required');
    }

    $tableName = match ($contentType) {
        'comment' => 'comments',
        'news'    => 'news',
        'torrent' => 'torrents',
        'post'    => 'posts',
        'message' => 'privatemessages',
        default   => throw new Exception('Invalid content type'),
    };

    $idField = match ($contentType) {
        'post'    => 'pid',
        'message' => 'pmid',
        default   => 'id',
    };

    $check_result = $db->sql_query_prepared("SELECT {$idField} FROM {$tableName} WHERE {$idField} = ?", [$contentId]);
    if (!$check_result || $db->num_rows($check_result) === 0) {
        throw new Exception("{$contentType} with ID {$contentId} does not exist");
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $contentColumn = match ($contentType) {
        'comment' => 'comment_id',
        'news'    => 'news_id',
        'torrent' => 'torrent_id',
        'post'    => 'post_id',
        'message' => 'messages_id',
        default   => null,
    };

    if ($contentColumn === null) {
        throw new Exception('Invalid content type');
    }

    $files         = $_FILES['files'];
    $fileCount     = count($files['name']);
    $uploaded      = 0;
    $uploadedFiles = [];

    for ($i = 0; $i < $fileCount; $i++) {
        $fileName  = $files['name'][$i];
        $fileTmp   = $files['tmp_name'][$i];
        $fileSize  = $files['size'][$i];
        $fileError = $files['error'][$i];

        if ($fileError !== UPLOAD_ERR_OK) {
            continue;
        }

        if ($fileSize > $maxSize) {
            continue;
        }

        // MIME по содержимому, не по заголовку клиента
        $realMime = (new finfo(FILEINFO_MIME_TYPE))->file($fileTmp);
        if (!in_array($realMime, $allowedMime, true)) {
            continue;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        // Доп. проверка для изображений - ловит "полиглот"-файлы, у которых
        // магические байты картинки валидны, но дальше не изображение.
        if (str_starts_with($realMime, 'image/') && @getimagesize($fileTmp) === false) {
            continue;
        }

        $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadPath  = $uploadDir . $newFileName;
        $fileUrl     = $BASEURL . '/uploads/' . $newFileName;

        if (!move_uploaded_file($fileTmp, $uploadPath)) {
            continue;
        }

        // Перекодирование через GD — только для картинок, остальные типы
        // (pdf/doc/docx) GD не умеет и не должна трогать. getimagesize()+
        // finfo проверяют только заголовок, не гарантируют отсутствие
        // приклеенной после настоящих данных изображения нагрузки
        // ("полиглот"-файл). Возвращает актуальный размер файла на диске —
        // перекодирование почти всегда меняет размер в байтах.
        $actualFileSize = (int)$fileSize;
        if (str_starts_with($realMime, 'image/')) {
            $recodedSize = recode_image_file($uploadPath, $realMime);
            if ($recodedSize === false) {
                @unlink($uploadPath);
                continue;
            }
            $actualFileSize = $recodedSize;
        }

        $userId = (int)($CURUSER['id'] ?? 0);

        $result = $db->sql_query_prepared(
            "INSERT INTO comment_files (file_name, file_path, file_url, file_type, file_size, user_id, {$contentColumn}, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$fileName, $uploadPath, $fileUrl, $realMime, $actualFileSize, $userId, $contentId]
        );

        if ($result) {
            $uploaded++;
            $uploadedFiles[] = $fileUrl;
        } else {
            // Запись в БД не удалась - файл уже физически на диске, без
            // этого он остался бы висеть осиротевшим.
            @unlink($uploadPath);
        }
    }

    // Автоматически добавляем [img] в текст контента.
    // Приватные сообщения сюда сознательно не включены - см. пояснение в
    // сопроводительном тексте.
    if ($uploaded > 0 && $uploadedFiles !== []) {
        $contentInfo = match ($contentType) {
            'comment' => ['table' => 'comments', 'field' => 'text', 'id_field' => 'id'],
            'news'    => ['table' => 'news', 'field' => 'body', 'id_field' => 'id'],
            'torrent' => ['table' => 'torrents', 'field' => 'descr', 'id_field' => 'id'],
            'post'    => ['table' => 'posts', 'field' => 'message', 'id_field' => 'pid'],
            default   => null,
        };

        if ($contentInfo !== null) {
            $result = $db->sql_query_prepared(
                "SELECT {$contentInfo['field']} FROM {$contentInfo['table']} WHERE {$contentInfo['id_field']} = ?",
                [$contentId]
            );
            $row = $result ? $db->fetch_array($result) : null;

            if ($row !== null) {
                $currentText = $row[$contentInfo['field']] ?? '';
                $newImages   = '';
                foreach ($uploadedFiles as $url) {
                    $newImages .= '[img]' . $url . '[/img]' . "\n";
                }
                $updatedText = $currentText !== '' ? $currentText . "\n\n" . $newImages : $newImages;

                if ($contentType === 'post') {
                    $db->sql_query_prepared(
                        "UPDATE {$contentInfo['table']} SET {$contentInfo['field']} = ?, edituid = ?, edittime = ?, editreason = ? WHERE {$contentInfo['id_field']} = ?",
                        [$updatedText, (int)$CURUSER['id'], TIMENOW, 'Added ' . count($uploadedFiles) . ' new image(s)', $contentId]
                    );
                } elseif ($contentType === 'comment') {
                    $db->sql_query_prepared(
                        "UPDATE {$contentInfo['table']} SET {$contentInfo['field']} = ?, editedat = ?, editedby = ? WHERE {$contentInfo['id_field']} = ?",
                        [$updatedText, TIMENOW, (int)$CURUSER['id'], $contentId]
                    );
                } else {
                    $db->sql_query_prepared(
                        "UPDATE {$contentInfo['table']} SET {$contentInfo['field']} = ? WHERE {$contentInfo['id_field']} = ?",
                        [$updatedText, $contentId]
                    );
                }

                
            }
        }
    }

    if ($uploaded > 0) {
        $response['success']  = true;
        $response['message']  = "{$uploaded} file(s) uploaded successfully";
        $response['uploaded'] = $uploaded;
    } else {
        $response['message'] = 'No files were uploaded';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();