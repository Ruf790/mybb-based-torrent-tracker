<?php

declare(strict_types=1);

$rootpath = './../';
$thispath = './';
define('IN_ADMINCP', 1);
define('IN_ADMIN_PANEL', true);
define('SKIP_CRON_JOBS', true);
define('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);

require_once $rootpath . 'global.php';

// Set proper content type for JSON responses
header('Content-Type: application/json; charset=utf-8');

// ── Реальная проверка прав доступа (staff only) ─────────────────────────
$__usergroups = $mybb->usergroup ?? [];
if (empty($CURUSER['id']) || !is_mod($__usergroups)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── CSRF-проверка ────────────────────────────────────────────────────────
if (!isset($_POST['my_post_key']) || !verify_post_check($_POST['my_post_key'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed. Please refresh the page and try again.']);
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

            // Verify news exists
            $result = $db->sql_query_prepared("SELECT id, title FROM news WHERE id = ?", [$newsid]);
            if (!$result || $db->num_rows($result) === 0) {
                throw new Exception('News item not found');
            }
            $newsData = $db->fetch_array($result);
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

            // Delete file records
            $db->sql_query_prepared("DELETE FROM comment_files WHERE news_id = ?", [$newsid]);

            // Delete the news item
            $db->sql_query_prepared("DELETE FROM news WHERE id = ?", [$newsid]);

            write_log('News item deleted: "' . $newsData['title'] . '" (id=' . $newsid . ')', 'news', 1);

            

            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'title' => $newsData['title'],
                'message' => 'News deleted successfully'
            ]);
            break;

        case 'edit':
            if ($newsid <= 0) {
                throw new Exception('Invalid news ID');
            }

            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));

            if ($title === '') {
                echo json_encode(['error' => 'Title cannot be empty']);
                exit;
            }

            if ($body === '') {
                echo json_encode(['error' => 'Content cannot be empty']);
                exit;
            }

            if (strlen($title) > 255) {
                echo json_encode(['error' => 'Title is too long (max 255 characters)']);
                exit;
            }

            if (strlen($body) > 5000) {
                echo json_encode(['error' => 'Content is too long (max 5000 characters)']);
                exit;
            }

            // Update news
            $success = $db->sql_query_prepared("UPDATE news SET title = ?, body = ? WHERE id = ?", [$title, $body, $newsid]);
            
            if (!$success) {
                throw new Exception('Failed to update news');
            }

            write_log('News item edited: "' . $title . '" (id=' . $newsid . ')', 'news', 1);

           
            
            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'message' => 'News updated successfully'
            ]);
            break;

        case 'add':
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['newsMessage'] ?? ''));

            if ($subject === '') {
                echo json_encode(['error' => 'Title is required']);
                exit;
            }

            if ($message === '') {
                echo json_encode(['error' => 'Content is required']);
                exit;
            }

            if (strlen($subject) > 255) {
                echo json_encode(['error' => 'Title is too long (max 255 characters)']);
                exit;
            }

            if (strlen($message) > 5000) {
                echo json_encode(['error' => 'Content is too long (max 5000 characters)']);
                exit;
            }

            $userid = (int)($CURUSER['id'] ?? 0);
            if ($userid <= 0) {
                throw new Exception('Invalid user ID');
            }

            $added = TIMENOW;

            // Insert news
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

            write_log('News item created: "' . $subject . '" (id=' . $newsid . ')', 'news', 1);

            // Link files to news if provided
            if (!empty($_POST['file_ids'])) {
                $file_ids = is_array($_POST['file_ids']) ? $_POST['file_ids'] : [$_POST['file_ids']];
                $file_ids = array_filter(array_map('intval', $file_ids));
                
                if (!empty($file_ids)) {
                    $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
                    $query = "UPDATE comment_files SET news_id = ? WHERE id IN ($placeholders)";
                    $params = array_merge([$newsid], $file_ids);
                    $db->sql_query_prepared($query, $params);
                }
            }

           

            echo json_encode([
                'success' => true, 
                'id' => $newsid,
                'message' => 'News added successfully'
            ]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    if (!in_array($e->getMessage(), ['Invalid news ID', 'News item not found', 'Invalid user ID'])) {
        error_log("News AJAX error: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log("News AJAX fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

exit;
?>