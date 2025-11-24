<?php

declare(strict_types=1);

$rootpath = './../';
$thispath = './';
define('IN_ADMIN_PANEL', true);
define('STAFF_PANEL_TSSEv56', true);
define('SKIP_CRON_JOBS', true);
define('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);

require_once $rootpath . 'global.php';

// Set proper content type for JSON responses
header('Content-Type: application/json; charset=utf-8');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$newsid = (int)($_POST['newsid'] ?? 0);

try {
    switch ($action) {
        case 'delete':
            if ($newsid <= 0) {
                throw new Exception('Invalid news ID');
            }

            // Verify news exists using prepared statement
            $result = $db->sql_query_prepared("SELECT id FROM news WHERE id = ?", [$newsid]);
            if (!$result || $db->num_rows($result) === 0) {
                throw new Exception('News item not found');
            }
            $db->free_result($result);

            // Get files associated with the news
            $file_result = $db->sql_query_prepared("SELECT file_path FROM comment_files WHERE news_id = ?", [$newsid]);
            $deleted_files = 0;
            
            if ($file_result) {
                while ($file = $db->fetch_array($file_result)) {
                    $file_path = (string)($file['file_path'] ?? '');
                    if ($file_path !== '' && file_exists($file_path)) {
                        if (@unlink($file_path)) {
                            $deleted_files++;
                        }
                    }
                }
                $db->free_result($file_result);
            }

            // Delete file records using prepared statement
            $db->sql_query_prepared("DELETE FROM comment_files WHERE news_id = ?", [$newsid]);

            // Delete the news item using prepared statement
            $db->sql_query_prepared("DELETE FROM news WHERE id = ?", [$newsid]);

            // Update news cache
            if (method_exists($cache, 'update_news')) {
                $cache->update_news();
            }

            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'message' => 'News deleted successfully'
            ], JSON_THROW_ON_ERROR);
            break;

        case 'edit':
            if ($newsid <= 0) {
                throw new Exception('Invalid news ID');
            }

            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));

            if ($title === '') {
                echo json_encode(['error' => 'Title cannot be empty'], JSON_THROW_ON_ERROR);
                exit;
            }

            if ($body === '') {
                echo json_encode(['error' => 'Body cannot be empty'], JSON_THROW_ON_ERROR);
                exit;
            }

            // Validate length
            if (strlen($title) > 255) {
                echo json_encode(['error' => 'Title is too long (max 255 characters)'], JSON_THROW_ON_ERROR);
                exit;
            }

            // Update news using prepared statement
            $success = $db->sql_query_prepared("UPDATE news SET title = ?, body = ? WHERE id = ?", [$title, $body, $newsid]);
            
            if (!$success) {
                throw new Exception('Failed to update news');
            }

            // Update cache
            if (method_exists($cache, 'update_news')) {
                $cache->update_news();
            }
            
            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'message' => 'News updated successfully'
            ], JSON_THROW_ON_ERROR);
            break;

        case 'add':
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['newsMessage'] ?? ''));

            if ($subject === '') {
                echo json_encode(['error' => 'Title is required'], JSON_THROW_ON_ERROR);
                exit;
            }

            if ($message === '') {
                echo json_encode(['error' => 'Message is required'], JSON_THROW_ON_ERROR);
                exit;
            }

            // Validate length
            if (strlen($subject) > 255) {
                echo json_encode(['error' => 'Title is too long (max 255 characters)'], JSON_THROW_ON_ERROR);
                exit;
            }

            $userid = (int)($CURUSER['id'] ?? 0);
            if ($userid <= 0) {
                throw new Exception('Invalid user ID');
            }

            $added = TIMENOW;

            // Insert news using prepared statement
            $success = $db->sql_query_prepared(
                "INSERT INTO news (title, body, userid, added) VALUES (?, ?, ?, ?)", 
                [$subject, $message, $userid, $added]
            );
            
            if (!$success) {
                throw new Exception('Failed to create news item');
            }
            
            $newsid = $db->insert_id();
            if ($newsid <= 0) {
                throw new Exception('Failed to get news ID');
            }

            // Link files to news if provided
            if (!empty($_POST['file_ids'])) {
                $file_ids = is_array($_POST['file_ids']) ? $_POST['file_ids'] : [$_POST['file_ids']];
                $file_ids = array_filter(array_map('intval', $file_ids));
                
                if (!empty($file_ids)) {
                    // Создаем плейсхолдеры для IN условия
                    $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
                    $query = "UPDATE comment_files SET news_id = ? WHERE id IN ($placeholders)";
                    
                    // Собираем параметры: newsid + все file_ids
                    $params = array_merge([$newsid], $file_ids);
                    
                    $db->sql_query_prepared($query, $params);
                }
            }

            // Update cache
            if (method_exists($cache, 'update_news')) {
                $cache->update_news();
            }

            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'message' => 'News added successfully'
            ], JSON_THROW_ON_ERROR);
            break;

        default:
            echo json_encode(['error' => 'Invalid action'], JSON_THROW_ON_ERROR);
    }
} catch (JsonException $e) {
    http_response_code(500);
    echo '{"error": "JSON encoding error"}';
} catch (Exception $e) {
    // Логируем только реальные исключения, а не ошибки валидации
    if (!in_array($e->getMessage(), ['Invalid news ID', 'News item not found', 'Invalid user ID'])) {
        error_log("News AJAX error: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log("News AJAX fatal error: " . $e->getMessage());
    http_response_code(500);
    echo '{"error": "Internal server error"}';
}

exit;