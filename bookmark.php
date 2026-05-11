<?php
declare(strict_types=1);

require_once 'global.php';

// Set proper JSON headers
header("Content-Type: application/json; charset=utf-8");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Разрешаем OPTIONS и принимаем POST
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Get request data from POST or GET
 */
function getRequestData(): array {
    // Если это POST с JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
        // Если не JSON, пробуем обычные POST параметры
        return $_POST;
    }
    // GET запрос
    return $_GET;
}

/**
 * Process bookmark toggle action
 */
function processBookmarkToggle(): never
{
    global $db, $CURUSER;
    
    // Allow both POST and GET
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        sendJsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
    }
    
    // Get data from request
    $requestData = getRequestData();
    
    // Get action and torrent ID
    $action = $requestData['action'] ?? '';
    $torrentid = filter_var($requestData['id'] ?? 0, FILTER_VALIDATE_INT);
    
    if ($action !== 'toggle') {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid action']);
    }
    
    if ($torrentid === false || $torrentid <= 0) {
        sendJsonResponse(400, ['success' => false, 'message' => 'Invalid torrent ID']);
    }
    
    // Validate user
    if (empty($CURUSER['id'])) {
        sendJsonResponse(401, ['success' => false, 'message' => 'Not authorized']);
    }
    
    $user_id = (int)$CURUSER['id'];
    
    // Check if torrent exists
    $torrentExists = $db->simple_select("torrents", "id", "id = " . $torrentid);
    if ($db->num_rows($torrentExists) === 0) {
        sendJsonResponse(404, ['success' => false, 'message' => 'Torrent not found']);
    }
    
    // Check current bookmark state
    $bookmarkExists = $db->simple_select(
        "bookmarks", 
        "id", 
        "torrentid = " . $torrentid . " AND userid = " . $user_id
    );
    
    $isBookmarked = $db->num_rows($bookmarkExists) > 0;
    
    if ($isBookmarked) {
        // Remove bookmark
        $db->delete_query(
            "bookmarks", 
            "torrentid = " . $torrentid . " AND userid = " . $user_id
        );
        
        sendJsonResponse(200, [
            'success' => true, 
            'bookmarked' => false,
            'message' => 'Bookmark removed',
            'html' => '<i class="fa-regular fa-star fa-lg" style="color: #6c757d;"></i>'
        ]);
        
    } else {
        // Add bookmark
        $insert_data = [
            'torrentid' => $torrentid,
            'userid' => $user_id
        ];
        
        $db->insert_query("bookmarks", $insert_data);
        
        sendJsonResponse(200, [
            'success' => true, 
            'bookmarked' => true,
            'message' => 'Bookmark added successfully',
            'html' => '<i class="fa-solid fa-star fa-lg" style="color: #ffc107;"></i>'
        ]);
    }
}

/**
 * Send JSON response and exit
 */
function sendJsonResponse(int $statusCode, array $data): never
{
    http_response_code($statusCode);
    
    try {
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (JsonException $e) {
        // Fallback for JSON encoding errors
        http_response_code(500);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Error generating response";
        error_log("JSON encoding error: " . $e->getMessage());
    }
    
    exit;
}

// Handle exceptions
try {
    processBookmarkToggle();
} catch (Throwable $e) {
    error_log("Bookmark system error: " . $e->getMessage());
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        sendJsonResponse(500, [
            'success' => false, 
            'message' => 'System error: ' . $e->getMessage()
        ]);
    } else {
        sendJsonResponse(500, [
            'success' => false, 
            'message' => 'Internal server error'
        ]);
    }
}
?>