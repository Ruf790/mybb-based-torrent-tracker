<?php
//require_once "backend/init.php"; // Include DB, session, etc.


$rootpath = './../';
$thispath = './';
define ('IN_ADMIN_PANEL', true);
define ('STAFF_PANEL_TSSEv56', true);
define ('SKIP_CRON_JOBS', true);
define ('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);

require_once $rootpath . 'global.php';

$action = $_POST['action'] ?? '';
$newsid = (int)($_POST['newsid'] ?? 0);

switch ($action) 
{
    case 'delete':
    // === Удаляем картинки, привязанные к новости ===
    $files = $db->simple_select("comment_files", "*", "news_id = " . (int)$newsid);
    while ($file = $db->fetch_array($files)) 
	{
        // Проверка, если файл существует на сервере
        if (is_file($file['file_path'])) 
		{
            @unlink($file['file_path']); // Удаляем файл с диска
        }
    }

    // Удаляем записи из таблицы comment_files
    $db->delete_query("comment_files", "news_id = " . (int)$newsid);

    // Удаляем саму новость
    $db->delete_query("news", "id='$newsid'");

    // Обновляем кэш новостей
    $cache->update_news();

    echo json_encode(['success' => true, 'id' => $newsid]);
    break;

    case 'edit':
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '' || $body === '') {
            echo json_encode(['error' => 'Title and body cannot be empty.']);
            exit;
        }

        $title = $db->sqlesc($title);
        $body = $db->sqlesc($body);

        $db->sql_query("UPDATE news SET title = $title, body = $body WHERE id = " . $db->sqlesc($newsid));
        $cache->update_news();
        echo json_encode(['success' => true, 'id' => $newsid]);
        break;

    case 'add':
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['newsMessage'] ?? '');

        if ($subject === '' || $message === '') {
            echo json_encode(['error' => 'Title and message are required.']);
            exit;
        }

		// Вставляем новость в базу данных
        $news_insert_data = array(
        "title" => $db->escape_string($subject),
        "body" => $db->escape_string($message),
        "userid" => $CURUSER['id'],
        "added" => TIMENOW
        );
    
        $db->insert_query("news", $news_insert_data);
        $newsid = $db->insert_id();  // Получаем ID вставленной новости
	
	    $cache->update_news();
	
	
	
	
	// Привязываем файлы к новости (если они есть)
    if (!empty($_POST['file_ids'])) {
        $file_ids = array_map('intval', $_POST['file_ids']);
        $id_list = implode(',', $file_ids);

        if (!empty($id_list)) {
            // Привязываем файлы к новости в базе данных
            $db->sql_query("
                UPDATE comment_files 
                SET news_id = " . (int)$newsid . " 
                WHERE id IN ($id_list)
            ");
        }
    }
		
		
		
		

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}

