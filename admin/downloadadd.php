<?php


declare(strict_types=1);


require_once INC_PATH.'/functions_mkprettytime.php';
 

class DownloadAmountManager
{
    private const VERSION = 'v3.0';
    private const MAX_GB_INDIVIDUAL = 1000;
    private const MAX_GB_BULK = 50;
    private const MIN_GB = 1;
    private const CACHE_TTL = 300; // 5 минут
    
    private string $eol;
    private array $currentUser;
    private array $userStats = [];
    private bool $ajaxRequest = false;
    private string $scriptUrl;
    
    public function __construct()
    {
        $this->validateAccess();
        $this->currentUser = $GLOBALS['CURUSER'] ?? [];
        $this->eol = PHP_EOL;
        $this->ajaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $this->scriptUrl = $_SERVER['PHP_SELF'] ?? 'index.php';
    }

    /**
     * Валидация доступа к скрипту
     */
    private function validateAccess(): void
    {
        if (!defined('STAFF_PANEL_TSSEv56')) {
            throw new RuntimeException('Direct initialization of this file is not allowed.');
        }
    }

    /**
     * Main execution method
     */
    public function execute(): void
    {
        try {
            $this->preloadUserStats();
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handlePostRequest();
            } elseif (isset($_GET['action'])) {
                $this->handleAjaxRequest();
            } else {
                $this->displayInterface();
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle POST requests
     */
    private function handlePostRequest(): void
    {
        if (isset($_POST['doit']) && $_POST['doit'] === 'yes') {
            $this->processBulkAction();
        } else {
            $this->processIndividualAction();
        }
    }

    /**
     * Process bulk action for user groups
     */
    private function processBulkAction(): void
    {
        $groupId = $this->validateGroupId($_POST['usergroup'] ?? 0);
        $amountGB = $this->validateAmount($_POST['classamount'] ?? 0, self::MAX_GB_BULK);
        
        $query = "enabled = 'yes' AND ustatus = 'confirmed'" . 
                 ($groupId ? " AND usergroup = {$groupId}" : '');
        
        $this->updateGroupDownloadAmount($query, $amountGB, $groupId);
        
        $this->logAction('bulk', $amountGB, $groupId);
        $this->displaySuccess($amountGB, $groupId);
		
    }

    /**
     * Process individual user action
     */
    private function processIndividualAction(): void
    {
        $username = $this->sanitizeUsername($_POST['username'] ?? '');
        $amountGB = $this->validateAmount($_POST['downloaded'] ?? 0, self::MAX_GB_INDIVIDUAL);
        $adminNote = trim($_POST['admin_note'] ?? '');
        
        $this->updateUserDownloadAmount($username, $amountGB, $adminNote);
        
        $this->logAction('individual', $amountGB, null, $username);
        $this->redirectToSuccess($username, $amountGB);
    }

    /**
     * Handle AJAX requests
     */
    private function handleAjaxRequest(): void
    {
        header('Content-Type: application/json');
        
        $action = $_GET['action'] ?? '';
        $response = [];
        
        switch ($action) {
            case 'check_user':
                $response = $this->checkUserExists($_GET['username'] ?? '');
                break;
            case 'group_stats':
                $response = $this->getGroupStats((int)($_GET['group_id'] ?? 0));
                break;
            case 'recent_activity':
                $response = $this->getRecentActivity();
                break;
        
            case 'search_users':
                $response = $this->searchUsers($_GET['query'] ?? '');
                break; 
			
			case 'search_by_group': // ДОБАВЬТЕ ЭТОТ КЕЙС
            $response = $this->searchByGroup((int)($_GET['group_id'] ?? 0));
            break;
				
			default:
                $response = ['error' => 'Invalid action'];
        }
        
        echo json_encode($response);
        exit;
    }
	
	
	// Добавьте этот метод в класс
private function searchByGroup(int $groupId): array
{
    global $db;
    
    // Получаем название группы
    $groupName = 'Unknown Group';
    $groupResult = $db->sql_query("SELECT title FROM usergroups WHERE gid = " . $db->sqlesc($groupId));
    if ($row = mysqli_fetch_assoc($groupResult)) {
        $groupName = $row['title'];
    }
    
    // Ищем пользователей в этой группе
    $sql = "SELECT id, username, downloaded, uploaded, usergroup 
            FROM users 
            WHERE usergroup = " . $db->sqlesc($groupId) . "
            AND enabled = 'yes' 
            AND ustatus = 'confirmed'
            ORDER BY username 
            LIMIT 200";
    
    $result = $db->sql_query($sql);
    $users = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $downloaded = (float)$row['downloaded'];
        $uploaded = (float)$row['uploaded'];
        $ratio = $downloaded > 0 ? round($uploaded / $downloaded, 2) : 0;
        
        $users[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'group_id' => (int)$row['usergroup'],
            'downloaded' => mksize($downloaded),
            'uploaded' => mksize($uploaded),
            'ratio' => $ratio
        ];
    }
    
    return [
        'users' => $users,
        'group_name' => $groupName,
        'group_id' => $groupId,
        'count' => count($users)
    ];
}

    /**
     * Preload user statistics
     */
    private function preloadUserStats(): void
    {
        global $db;
        
        $cacheKey = 'user_stats_' . md5(serialize($this->currentUser));
        
        // Проверяем кэш (если функция cache_get существует)
        if (function_exists('cache_get')) {
            $cacheData = cache_get($cacheKey);
            if ($cacheData !== false) {
                $this->userStats = $cacheData;
                return;
            }
        }
        
        // Общая статистика
        $sql = "SELECT 
                COUNT(*) as total_users,
                SUM(downloaded) as total_downloaded,
                AVG(downloaded) as avg_downloaded
                FROM users 
                WHERE enabled = 'yes' 
                AND ustatus = 'confirmed'";
        
        $result = $db->sql_query($sql);
        $this->userStats['global'] = mysqli_fetch_assoc($result) ?? [];
        
        // Статистика по группам
        $sql = "SELECT 
                usergroup,
                COUNT(*) as user_count,
                SUM(downloaded) as total_downloaded
                FROM users 
                WHERE enabled = 'yes' 
                AND ustatus = 'confirmed'
                GROUP BY usergroup";
        
        $result = $db->sql_query($sql);
        $this->userStats['groups'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $this->userStats['groups'][$row['usergroup']] = $row;
        }
        
        // Сохраняем в кэш если функция существует
        if (function_exists('cache_set')) {
            cache_set($cacheKey, $this->userStats, self::CACHE_TTL);
        }
    }

    /**
     * Check if user exists
     */
    private function checkUserExists(string $username): array
    {
        global $db;
        
        $username = $this->sanitizeUsername($username, false);
        if (empty($username)) {
            return ['exists' => false];
        }
        
        $sql = "SELECT id, username, downloaded, uploaded, usergroup 
                FROM users 
                WHERE username = " . $db->sqlesc($username) . "
                AND enabled = 'yes' 
                AND ustatus = 'confirmed' 
                LIMIT 1";
        
        $result = $db->sql_query($sql);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $downloaded = (float)$row['downloaded'];
            $uploaded = (float)$row['uploaded'];
            $ratio = $downloaded > 0 ? round($uploaded / $downloaded, 2) : 0;
            
            return [
                'exists' => true,
                'user' => [
                    'id' => (int)$row['id'],
                    'username' => $row['username'],
                    'downloaded' => $downloaded,
                    'uploaded' => $uploaded,
                    'usergroup' => (int)$row['usergroup'],
                    'downloaded_human' => mksize($downloaded),
                    'uploaded_human' => mksize($uploaded),
                    'ratio' => $ratio
                ]
            ];
        }
        
        return ['exists' => false];
    }

    /**
     * Search users by username
     */
    private function searchUsers(string $query): array
    {
        global $db;
        
        if (strlen($query) < 2) {
            return ['users' => []];
        }
        
        $sql = "SELECT username, usergroup 
                FROM users 
                WHERE username LIKE " . $db->sqlesc($query . '%') . "
                AND enabled = 'yes' 
                AND ustatus = 'confirmed'
                ORDER BY username 
                LIMIT 10";
        
        $result = $db->sql_query($sql);
        $users = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = [
                'username' => $row['username'],
                'group_id' => (int)$row['usergroup']
            ];
        }
        
        return ['users' => $users];
    }

    /**
     * Get statistics for a specific group
     */
    private function getGroupStats(int $groupId): array
    {
        $stats = $this->userStats['groups'][$groupId] ?? null;
        
        if (!$stats) {
            return ['error' => 'Group not found'];
        }
        
        $userCount = (int)$stats['user_count'];
        $totalDownloaded = (float)$stats['total_downloaded'];
        $avgPerUser = $userCount > 0 ? $totalDownloaded / $userCount : 0;
        
        return [
            'user_count' => $userCount,
            'total_downloaded' => $totalDownloaded,
            'total_downloaded_human' => mksize($totalDownloaded),
            'avg_per_user' => mksize($avgPerUser),
			'group_id' => $groupId
        ];
    }




 
    /**
     * Get recent activity log
     */
    private function getRecentActivity(): array
    {
        global $db;
        
        $sql = "SELECT * FROM sitelog 
                WHERE (LOWER(txt) LIKE '%download%added%' 
                OR LOWER(txt) LIKE '%gb%')
                ORDER BY added DESC 
                LIMIT 10";
        
        $result = $db->sql_query($sql);
        $activities = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $activities[] = [
                'id' => (int)$row['id'],
                'message' => $row['txt'],
                'added' => mkprettytime(TIMENOW - $row['added']),
                'added_relative' => my_datee('relative', $row['added']),
                'username' => $row['username'] ?? 'System'
            ];
        }
        
        return ['activities' => $activities];
    }

    /**
     * Get elapsed time in human readable format
     */
    private function getElapsedTime(string $timestamp): string
    {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
        if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
        return floor($diff / 31536000) . ' years ago';
    }

    /**
     * Update download amount for a user group
     */
    private function updateGroupDownloadAmount(string $query, int $amountGB, ?int $groupId): void
    {
        global $db;
        
        $bytes = $this->convertGBtoBytes($amountGB);
        $modComment = $this->generateModComment($amountGB, 'group');
        
        $sql = sprintf(
            "UPDATE users 
             SET downloaded = downloaded + %d, 
                 modcomment = CONCAT(%s, modcomment) 
             WHERE %s",
            $bytes,
            $db->sqlesc($modComment),
            $query
        );
        
        $db->sql_query($sql);
    }

    /**
     * Update download amount for individual user with admin note
     */
    private function updateUserDownloadAmount(string $username, int $amountGB, string $adminNote = ''): void
    {
        global $db;
        
        $bytes = $this->convertGBtoBytes($amountGB);
        $modComment = $this->generateModComment($amountGB, 'individual', $adminNote);
        
        $sql = sprintf(
            "UPDATE users 
             SET downloaded = downloaded + %d, 
                 modcomment = CONCAT(%s, modcomment) 
             WHERE username = %s 
             AND enabled = 'yes' 
             AND ustatus = 'confirmed'",
            $bytes,
            $db->sqlesc($modComment),
            $db->sqlesc($username)
        );
        
        $db->sql_query($sql);
        
        // Verify the update
        $this->verifyUserUpdate($username);
    }

    /**
     * Verify user was updated successfully
     */
    private function verifyUserUpdate(string $username): void
    {
        global $db;
        
        $res = $db->sql_query(
            "SELECT id FROM users WHERE username = " . $db->sqlesc($username)
        );
        
        if (!$res || mysqli_num_rows($res) === 0) {
            throw new RuntimeException('User not found or update failed.');
        }
    }

    /**
     * Validate group ID
     */
    private function validateGroupId(mixed $groupId): ?int
    {
        $id = (int)$groupId;
        
        // Проверяем существование функции is_valid_id
        if (function_exists('is_valid_id')) {
            return ($id > 0 && is_valid_id($id)) ? $id : null;
        }
        
        // Альтернативная проверка если функция не существует
        return ($id > 0) ? $id : null;
    }

    /**
     * Validate amount
     */
    private function validateAmount(mixed $amount, int $max): int
    {
        $amount = (int)$amount;
        if ($amount < self::MIN_GB || $amount > $max) {
            throw new RuntimeException(
                sprintf('Amount must be between %d-%d GB.', self::MIN_GB, $max)
            );
        }
        return $amount;
    }

    /**
     * Sanitize username
     */
    private function sanitizeUsername(string $username, bool $throwException = true): string
    {
        $username = trim($username);
        if (empty($username) || !preg_match('/^[a-zA-Z0-9_\-\.]{3,32}$/', $username)) {
            if ($throwException) {
                throw new RuntimeException('Invalid username format.');
            }
            return '';
        }
        return $username;
    }

    /**
     * Convert GB to bytes
     */
    private function convertGBtoBytes(int $gb): int
    {
        return $gb * 1024 * 1024 * 1024;
    }

    /**
     * Generate mod comment
     */
    private function generateModComment(int $amountGB, string $type, string $adminNote = ''): string
    {
        $bytes = $this->convertGBtoBytes($amountGB);
        $size = mksize($bytes);
        $admin = $this->currentUser['username'] ?? 'System';
        $date = gmdate('Y-m-d H:i:s');
        
        $comment = "{$date} - Added {$size} download via ";
        $comment .= ($type === 'group') ? "Bulk Tool" : "Individual Tool";
        $comment .= " by {$admin}";
        
        if (!empty($adminNote)) {
            $comment .= " [Note: {$adminNote}]";
        }
        
        $comment .= $this->eol;
        return $comment;
    }

    /**
     * Log action
     */
    private function logAction(string $type, int $amountGB, ?int $groupId = null, ?string $username = null): void
    {
        $admin = $this->currentUser['username'] ?? 'System';
        $message = "{$amountGB} GB download added via {$type}";
        
        if ($groupId) {
            $message .= " to group ID: {$groupId}";
        } elseif ($username) {
            $message .= " to user: {$username}";
        }
        
        $message .= " by {$admin}";
        
        // Используем существующую функцию write_log
        if (function_exists('write_log')) {
            write_log($message);
        }
    }

    /**
     * Display success message
     */
private function displaySuccess(int $amountGB, ?int $groupId = null): void
{
    $groupName = 'all users';
    if ($groupId && function_exists('get_user_class_name')) {
        $groupName = get_user_class_name($groupId);
    } elseif ($groupId) {
        $groupName = "Group {$groupId}";
    }
    
    $bytes = $this->convertGBtoBytes($amountGB);
    $size = mksize($bytes);
    
    // Генерируем уникальный ID для toast
    $toastId = 'success_toast_' . uniqid();
    
    // Сохраняем toast в сессии чтобы показать после редиректа
    $_SESSION['success_toast'] = [
        'id' => $toastId,
        'amount' => $amountGB,
        'size' => $size,
        'group' => $groupName,
        'timestamp' => date('H:i:s')
    ];
    
    // Редирект на ту же страницу (чтобы избежать повторной отправки формы при F5)
    header('Location: ' . $this->scriptUrl . '?act=downloadadd');
    exit;
}





     

    /**
     * Redirect to success page
     */
    private function redirectToSuccess(string $username, int $amountGB): void
    {
        $message = sprintf('%d GB download amount successfully added to user: %s', $amountGB, $username);
        
        // Используем существующую функцию flash_message
        if (function_exists('flash_message')) {
            flash_message($message, 'success');
        }
        
        // Используем существующую функцию admin_redirect
        if (function_exists('admin_redirect')) {
            
			admin_redirect("index.php?act=downloadadd");
        } else {
            header('Location: ' . $this->scriptUrl);
        }
        exit;
    }

    /**
     * Display error message
     */
    private function displayError(string $message): void
    {
        $html = sprintf(
            '<div class="alert alert-danger alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h4 class="alert-heading">Error!</h4>
                        <p class="mb-0">%s</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>',
            htmlspecialchars($message)
        );
        
        echo $html;
    }

    /**
     * Handle errors appropriately
     */
    private function handleError(Throwable $e): void
    {
        if ($this->ajaxRequest) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        } else {
            $this->displayError($e->getMessage());
        }
        exit;
    }

    /**
     * Display the main interface
     */
    private function displayInterface(): void
    {
        // Используем существующую функцию stdhead
        if (function_exists('stdhead')) {
            stdhead('Download Amount Manager ' . self::VERSION);
        } else {
            echo '<!DOCTYPE html><html><head><title>Download Amount Manager</title>';
           
            echo '</head><body>';
        }
		
		
		
		 // Показываем toast из сессии если есть
    if (!empty($_SESSION['success_toast'])) {
        $toast = $_SESSION['success_toast'];
        
        echo '
        <!-- Toast контейнер -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999">
            <div id="' . $toast['id'] . '" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="10000">
                <div class="toast-header bg-success text-white">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <small>' . $toast['timestamp'] . '</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-download fa-2x text-success me-3"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Download Added</h6>
                            <p class="mb-0">
                                <strong>' . $toast['amount'] . ' GB</strong> to ' . htmlspecialchars($toast['group']) . '
                            </p>
                            <small class="text-muted">' . $toast['size'] . ' • Action logged</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Показываем toast при загрузке страницы
        document.addEventListener("DOMContentLoaded", function() {
            const toastEl = document.getElementById("' . $toast['id'] . '");
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
                
                // Обновляем данные на странице через 1 секунду
                setTimeout(() => {
                    if (typeof loadRecentActivity === "function") {
                        loadRecentActivity();
                    }
                    
                    ' . (!empty($toast['group_id']) ? "if (typeof loadGroupStats === 'function') { loadGroupStats({$toast['group_id']}); }" : "") . '
                }, 1000);
            }
        });
        </script>';
        
        // Очищаем toast из сессии
        unset($_SESSION['success_toast']);
    }
		
		
		
		
        ?>
      

        <!-- Confirmation Modal -->
        <div class="modal fade" id="confirmationModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Confirm Bulk Action
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalContent">
                            <!-- Content will be loaded dynamically -->
                        </div>
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-radiation me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-warning" id="confirmBulkAction">
                            <i class="fas fa-check me-1"></i> Confirm & Proceed
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Lookup Modal -->
<!-- Enhanced User Lookup Modal -->
<div class="modal fade" id="userLookupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>
                    Advanced User Lookup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Поисковое поле -->
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" class="form-control" id="userSearch" 
                           placeholder="Type username (min. 2 characters)...">
                    <button class="btn btn-primary" type="button" id="searchUser">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Quick filters and buttons -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="searchByGroup(1)">
                                <i class="fas fa-users me-1"></i> All Users
                            </button>
							
							<button class="btn btn-sm btn-outline-purple" onclick="searchByGroup(2)">
        <i class="fas fa-bolt me-1"></i> Power Users
    </button>
							
                            <button class="btn btn-sm btn-outline-info" onclick="searchByGroup(3)">
                                <i class="fas fa-star me-1"></i> VIP
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="searchByGroup(6)">
                                <i class="fas fa-crown me-1"></i> Admins
                            </button>
							
							<button class="btn btn-sm btn-outline-dark" onclick="searchByGroup(7)">
        <i class="fas fa-terminal me-1"></i> SysOp
    </button>
							
							
                            <button class="btn btn-sm btn-outline-warning" onclick="searchByGroup(5)">
                                <i class="fas fa-user-shield me-1"></i> Moderators
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="searchByGroup(4)">
                                <i class="fas fa-cloud-upload-alt me-1"></i> Uploaders
                            </button>
                           
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-dark" onclick="toggleSearchMode()">
                                <i class="fas fa-exchange-alt me-1"></i> 
                                <span id="searchMode">Starts With</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark" onclick="toggleSortOrder()">
                                <i class="fas fa-sort-alpha-down me-1"></i>
                                <span id="sortOrder">A-Z</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stats info -->
                <div class="alert alert-light border mb-3 py-2" id="searchStats" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-chart-bar me-2"></i>
                            <span id="resultCount">0 results</span>
                            <span id="groupName" class="text-muted ms-2"></span>
                        </div>
                        <div>
                            <small class="text-muted" id="searchTime">Time: 0ms</small>
                        </div>
                    </div>
                </div>
                
                <!-- Результаты поиска -->
                <div id="userResults" class="mt-3">
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                        <p>Enter a username or use quick filters to search</p>
                        <small class="text-muted">Search results will appear here</small>
                    </div>
                </div>
                
                <!-- Search tips -->
                <div class="alert alert-light border mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-lightbulb me-2"></i>Search Tips:</h6>
                            <ul class="small mb-0">
                                <li>Use <code>*</code> for wildcard searches</li>
                                <li>Search is case-insensitive</li>
                                <li>Minimum 2 characters required</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-bolt me-2"></i>Quick Actions:</h6>
                            <ul class="small mb-0">
                                <li>Click on username to select</li>
                                <li>Press <kbd>Enter</kbd> to search</li>
                                <li>Use arrows to navigate results</li>
                                <li>Group buttons search by user group</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer with actions -->
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="exportSearchResults()">
                    <i class="fas fa-download me-1"></i> Export
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>


	   

        <div class="container mt-3">
            <!-- Enhanced Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-primary text-white py-4 position-relative">
                            <div class="position-absolute top-0 end-0 mt-3 me-3">
                                <span class="badge bg-light text-primary fs-6"><?= self::VERSION ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bg-white rounded-3 p-3 me-4 shadow">
                                    <i class="fas fa-rocket text-primary fs-1"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h1 class="h2 mb-2 fw-bold">
                                        <i class="fas fa-cloud-download-alt me-2"></i>
                                        Download Amount Manager
                                    </h1>
                                    <p class="mb-0 opacity-75 fs-5">
                                        Advanced download allocation system with real-time analytics
                                    </p>
                                </div>
                                <div class="ms-4">
                                    <button class="btn btn-light btn-sm" onclick="window.location.reload()">
                                        <i class="fas fa-sync-alt me-1"></i> Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center">
                                        <div class="me-4">
                                            <div class="avatar avatar-lg bg-primary rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user-cog text-white fs-4"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Logged in as</h6>
                                            <h4 class="mb-0 text-primary fw-bold">
                                                <?= htmlspecialchars($this->currentUser['username'] ?? 'System Administrator') ?>
                                            </h4>
                                            <small class="text-muted">
                                                <i class="fas fa-shield-alt me-1"></i>
                                                Administrator Privileges Active
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#userLookupModal">
                                            <i class="fas fa-search me-1"></i> Find User
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="showStats">
                                            <i class="fas fa-chart-bar me-1"></i> Stats
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="exportToCSV()">
                                            <i class="fas fa-download me-1"></i> Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

           

            <!-- Enhanced Stats Cards -->
            <div class="row mb-5">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Total Users
                                    </div>
                                    <div class="h2 mb-0 fw-bold text-gray-800">
                                        <?= $this->userStats['global']['total_users'] ?? 0 ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted">
                                        <span class="text-success me-2">
                                            <i class="fas fa-users"></i> Active
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-3x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Total Downloaded
                                    </div>
                                    <div class="h2 mb-0 fw-bold text-gray-800">
                                        <?= mksize($this->userStats['global']['total_downloaded'] ?? 0) ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted">
                                        <span class="text-info me-2">
                                            <i class="fas fa-database"></i> <?= mksize($this->userStats['global']['avg_downloaded'] ?? 0) ?> avg
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-3x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Max Individual
                                    </div>
                                    <div class="h2 mb-0 fw-bold text-gray-800">
                                        <?= self::MAX_GB_INDIVIDUAL ?> GB
                                    </div>
                                    <div class="mt-2 mb-0 text-muted">
                                        <small>
                                            <i class="fas fa-user me-1"></i>
                                            Per user limit
                                        </small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-shield fa-3x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">
                                        Max Bulk
                                    </div>
                                    <div class="h2 mb-0 fw-bold text-gray-800">
                                        <?= self::MAX_GB_BULK ?> GB
                                    </div>
                                    <div class="mt-2 mb-0 text-muted">
                                        <small>
                                            <i class="fas fa-users me-1"></i>
                                            Group operations
                                        </small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users-cog fa-3x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forms with Advanced Features -->
            <div class="row">
                <!-- Individual User Form -->
                <div class="col-xl-6 mb-4">
                    <div class="card shadow h-100 border-0">
                        <div class="card-header bg-success text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Individual User Management
                                </h5>
                                <span class="badge bg-light text-success">Precise Control</span>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="<?= $_this_script_ ?>" 
                                  id="individualForm" class="needs-validation" novalidate>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold d-flex justify-content-between">
                                        <span>
                                            <i class="fas fa-user me-1"></i> Username
                                        </span>
                                        <a href="#" class="text-decoration-none" 
                                           data-bs-toggle="modal" data-bs-target="#userLookupModal">
                                            <small><i class="fas fa-search me-1"></i> Find User</small>
                                        </a>
                                    </label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text">
                                            <i class="fas fa-at"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="username" 
                                               name="username" 
                                               placeholder="Enter username"
                                               required
                                               pattern="[a-zA-Z0-9_\-\.]{3,32}"
                                               title="3-32 characters: letters, numbers, dots, dashes, underscores">
                                        <button class="btn btn-outline-secondary" type="button" 
                                                onclick="verifyUser()" id="verifyUserBtn">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                        <div class="invalid-feedback">
                                            Please enter a valid username (3-32 characters).
                                        </div>
                                    </div>
                                    <div id="userInfo" class="mt-2" style="display: none;">
                                        <!-- User info will be loaded here -->
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-sliders-h me-1"></i> Download Amount Configuration
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-hdd"></i>
                                                </span>
                                                <input type="number" 
                                                       class="form-control form-control-lg" 
                                                       id="downloaded" 
                                                       name="downloaded"
                                                       min="<?= self::MIN_GB ?>"
                                                       max="<?= self::MAX_GB_INDIVIDUAL ?>"
                                                       value="10"
                                                       required>
                                                <span class="input-group-text">GB</span>
                                            </div>
                                            <div class="form-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-calculator me-1"></i>
                                                    Equivalent to <span id="bytesDisplay">10.74 GB</span> in bytes
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="btn-group w-100" role="group">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="setIndividualAmount(5)">5 GB</button>
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="setIndividualAmount(10)">10 GB</button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="setIndividualAmount(50)">50 GB</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <input type="range" class="form-range" id="gbRange" 
                                               min="<?= self::MIN_GB ?>" 
                                               max="<?= self::MAX_GB_INDIVIDUAL ?>" 
                                               value="10"
                                               oninput="document.getElementById('downloaded').value = this.value; updateBytesDisplay()">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-sticky-note me-1"></i> Admin Note (Optional)
                                    </label>
                                    <textarea class="form-control" id="adminNote" name="admin_note" 
                                              rows="2" placeholder="Add a note about this action..."></textarea>
                                    <div class="form-text">
                                        <small>This note will be added to the user's modcomment.</small>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg py-3" 
                                            id="submitIndividualBtn">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        <span>Add Download to User</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bulk Group Form -->
                <div class="col-xl-6 mb-4">
                    <div class="card shadow h-100 border-0">
                        <div class="card-header bg-warning text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-users-cog me-2"></i>
                                    Bulk Group Operations
                                </h5>
                                <span class="badge bg-light text-warning">Power Tool</span>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="<?= $_this_script_ ?>" 
                                  id="bulkForm" class="needs-validation" novalidate>
                                <input type="hidden" name="doit" value="yes">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-layer-group me-1"></i> Select User Group
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-filter"></i>
                                        </span>
                                        <?= $this->renderGroupSelector() ?>
                                        <div class="invalid-feedback">
                                            Please select a user group.
                                        </div>
                                    </div>
                                    <div id="groupInfo" class="mt-3 p-3 bg-light rounded" style="display: none;">
                                        <!-- Group information will be loaded here -->
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-tachometer-alt me-1"></i> Amount Configuration
                                    </label>
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-chart-line"></i>
                                                </span>
                                                <?= $this->renderAmountSelector() ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <div class="fs-5 fw-bold" id="totalImpact">
                                                    Total Impact: Select group first
                                                </div>
                                                <small class="text-muted" id="perUserImpact">
                                                    Per user: N/A
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-warning progress-bar-striped" 
                                                 id="amountProgress" style="width: 0%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <small><?= self::MIN_GB ?> GB</small>
                                            <small id="currentAmount">10 GB</small>
                                            <small><?= self::MAX_GB_BULK ?> GB</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning border-warning" role="alert">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="alert-heading">Critical Operation</h6>
                                            <p class="mb-2">This action will affect multiple users simultaneously.</p>
                                            <ul class="mb-0 ps-3">
                                                <li>Cannot be undone automatically</li>
                                                <li>Will be logged in system audit trail</li>
                                                <li>May impact server performance</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
    <button type="button" class="btn btn-warning btn-lg py-3" id="applyBulkBtn">
        <i class="fas fa-bolt me-2"></i>
        <span>Apply to Selected Group</span>
    </button>
</div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

          
            
			
			
			<!-- Recent Activity & Audit Log -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow border-0">
            <div class="card-header bg-secondary text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Activity & Audit Log
                    </h5>
                    <button class="btn btn-sm btn-light" onclick="loadRecentActivity()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="activityTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Admin</th>
                                <th>Target</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="activityBody">
                            <!-- Activity loaded via AJAX -->
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    Loading activity...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <div class="spinner-border text-primary" id="activitySpinner" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



            <script src="<?= $BASEURL; ?>/admin/scripts/download_extract.js"></script>
			


            <!-- Information Panel -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card shadow border-0">
                        <div class="card-header bg-dark text-white py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Information & Guidelines
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            All changes are logged in user's modcomment
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            System logs track all administrator actions
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Real-time byte conversion display
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            Bulk actions affect multiple users simultaneously
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            Changes are permanent and cannot be reversed
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                            Always verify username before individual updates
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced JavaScript -->
       
	   <script src="<?= $BASEURL; ?>/admin/scripts/download_add.js"></script>
	   
	   
	   
	   
       

        <style>
        .dark-mode {
            background-color: #1a1a1a;
            color: #f8f9fa;
        }

        .dark-mode .card {
            background-color: #2d2d2d;
            border-color: #404040;
        }

        .dark-mode .card-header {
            background-color: #333 !important;
        }

        .dark-mode .list-group-item {
            background-color: #2d2d2d;
            color: #f8f9fa;
            border-color: #404040;
        }

        .avatar {
            width: 60px;
            height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .border-left-primary {
            border-left: 4px solid #667eea;
        }

        .border-left-success {
            border-left: 4px solid #11998e;
        }

        .border-left-warning {
            border-left: 4px solid #f7971e;
        }

        .border-left-danger {
            border-left: 4px solid #ff416c;
        }

        .progress-bar-striped {
            background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
        }
		
		
		
		
/* User search results styling */
.user-result-item {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}

.user-result-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
    transform: translateY(-2px);
}

.select-user-btn {
    transition: all 0.2s ease;
}

.select-user-btn:hover {
    transform: scale(1.05);
}

#userResults {
    max-height: 400px;
    overflow-y: auto;
}
	


		
		
		
		
		
        </style>
        <?php
        // Используем существующую функцию stdfoot
        if (function_exists('stdfoot')) {
            stdfoot();
        } else {
            echo '</body></html>';
        }
    }

    /**
     * Render group selector dropdown
     */
    private function renderGroupSelector(): string
    {
        global $db;
        
        $html = '<select class="form-select form-select-lg" id="usergroup" name="usergroup" required>';
        $html .= '<option value="" disabled selected>Select a user group...</option>';
        $html .= '<option value="">All Users</option>';
        
        $result = $db->sql_query("SELECT gid, title FROM usergroups ORDER BY gid DESC");
        while ($group = mysqli_fetch_assoc($result)) {
            $html .= sprintf(
                '<option value="%d">%s (GID: %d)</option>',
                $group['gid'],
                htmlspecialchars($group['title']),
                $group['gid']
            );
        }
        
        $html .= '</select>';
        return $html;
    }

    /**
     * Render amount selector dropdown
     */
    private function renderAmountSelector(): string
    {
        $html = '<select class="form-select form-select-lg" id="classamount" name="classamount" required>';
        $html .= '<option value="" disabled selected>Select amount...</option>';
        
        for ($i = self::MIN_GB; $i <= self::MAX_GB_BULK; $i++) {
            $selected = ($i === 10) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%d"%s>%d GB</option>',
                $i,
                $selected,
                $i
            );
        }
        
        $html .= '</select>';
        return $html;
    }
}

// Initialize and execute
// Initialize and execute
try {
    $manager = new DownloadAmountManager();
    $manager->execute();
} catch (Throwable $e) {
    // Полностью прекращаем вывод и показываем только ошибку
    ob_clean(); // Очищаем буфер вывода
    
    // Определяем переменную безопасно
    $errorMessage = $e->getMessage();
    if ($errorMessage === null) {
        $errorMessage = 'Unknown error occurred';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Error</title>
    
       
    </head>
    <body>
        <div class="container-fluid min-vh-100 d-flex align-items-center">
            <div class="row justify-content-center w-100">
                <div class="col-md-6">
                    <div class="card shadow-lg border-0">
                        <div class="card-body text-center p-5">
                            <i class="fas fa-exclamation-triangle fa-4x text-danger mb-4"></i>
                            <h2 class="mb-3">System Error</h2>
                            <div class="alert alert-danger">
                                <code><?= htmlspecialchars($errorMessage) ?></code>
                            </div>
                            <p class="text-muted mb-4">
                                An unexpected error occurred. Please contact system administrator.
                            </p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="window.location.reload()">
                                    <i class="fas fa-sync-alt me-2"></i> Try Again
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i> Return to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </body>
    </html>
    <?php
    exit; // Важно: завершаем выполнение скрипта
}

