<?php

declare(strict_types=1);



if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger fade-in" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> Direct access to this file is not allowed.</div>');
}

//define('CT_VERSION', '1.0');

/**
 * MySQL Tables Truncation Manager Class
 */
class TableTruncationManager
{
    private $db;
    private $config;
    private $scriptUrl;
    private $baseUrl;

    // Критичные таблицы, которые нельзя truncate через этот инструмент
    // ни при каких обстоятельствах - даже случайный выбор в multi-select
    // списке не должен иметь возможности необратимо снести users/права
    // доступа/структуру раздач. TRUNCATE необратим, отката нет.
    //
    // ВАЖНО: список собран по тому, что встречалось в файлах сайта за
    // сегодня - не гарантированно полный. Проверьте и дополните под
    // реальную схему БД перед использованием этого инструмента.
    private const PROTECTED_TABLES = [
        'users', 'usergroups', 'staffpanel', 'torrents', 'categories',
        'forumpermissions', 'settings', 'moderators', 'banned',
        'forums', 'faq', 'sitelog',
    ];
    
    public function __construct($db, $config, $scriptUrl, $baseUrl)
    {
        $this->db = $db;
        $this->config = $config;
        $this->scriptUrl = $scriptUrl;
        $this->baseUrl = $baseUrl;
    }
    
    /**
     * Validate table name (формат имени, для любых операций)
     */
    private function validateTableName($tableName)
    {
        return !empty($tableName) && preg_match('/^[a-zA-Z0-9_]+$/', $tableName);
    }

    /**
     * Проверка формата ИЛИ безопасности разрушительной операции (TRUNCATE).
     * OPTIMIZE безопасен и данные не удаляет, поэтому использует только
     * validateTableName() без этого дополнительного барьера.
     */
    private function validateTableNameForTruncate($tableName)
    {
        return $this->validateTableName($tableName)
            && !in_array(strtolower($tableName), self::PROTECTED_TABLES, true);
    }
    
    /**
     * Get all database tables
     */
    private function getAllTables()
    {
        $database = $this->config['database']['database'];
        $result = $this->db->sql_query_prepared("SHOW TABLES FROM `{$database}`");
        $tables = [];
        
        while ($result && ($row = $this->db->fetch_array($result))) {
            $tables[] = reset($row);
        }
        
        if ($result && method_exists($this->db, 'free_result')) {
            $this->db->free_result($result);
        }
        
        return $tables;
    }
    
    /**
     * Truncate table
     */
    private function truncateTable($tableName)
    {
        if (!$this->validateTableNameForTruncate($tableName)) {
            return false;
        }
        
        try {
            $this->db->sql_query_prepared("TRUNCATE TABLE `{$tableName}`");
            return true;
        } catch (Exception $e) {
            error_log("[TableTruncation] Error truncating table {$tableName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process table truncation
     */
    public function processTruncation()
    {
        
		
		
		// AJAX optimize handler
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'ajax_optimize') {
        global $mybb;

        $table = trim($_POST['table'] ?? '');
        header('Content-Type: application/json');

        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        if (!$this->validateTableName($table)) {
            echo json_encode(['success' => false, 'message' => 'Invalid table name']);
            exit;
        }

        try {
            $this->db->sql_query_prepared("OPTIMIZE TABLE `{$table}`");
            echo json_encode(['success' => true, 'table' => $table]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
		
		
		
		
		
		
		
		if (!isset($_GET['do']) || $_GET['do'] !== 'clear') {
            return;
        }
        
        $tables = $this->getSelectedTables();
        
        if (empty($tables)) {
            $this->showError('No tables selected for truncation.');
            return;
        }
        
        if (!isset($_GET['sure'])) {
            $this->showConfirmation($tables);
            return;
        }

        // Реальное удаление - только POST с валидным CSRF-токеном.
        global $mybb;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            $this->showError('Invalid or missing security token. Please use the confirmation button below instead of a direct link.');
            return;
        }
        
        $this->executeTruncation($tables);
    }
    
    /**
     * Get selected tables
     */
    private function getSelectedTables()
    {
        if (isset($_POST['tablenames']) && is_array($_POST['tablenames'])) {
            $tables = [];
            foreach ($_POST['tablenames'] as $table) {
                if ($this->validateTableNameForTruncate($table)) {
                    $tables[] = $table;
                }
            }
            return $tables;
        }
        
        if (isset($_POST['tablehash']) || isset($_GET['tablehash'])) {
            $decoded = base64_decode($_POST['tablehash'] ?? $_GET['tablehash']);
            $tables = explode(':', $decoded);
            $validTables = [];
            foreach ($tables as $table) {
                if ($this->validateTableNameForTruncate($table)) {
                    $validTables[] = $table;
                }
            }
            return $validTables;
        }
        
        return [];
    }
    
    /**
     * Display error
     */
    private function showError($message)
    {
        stdhead('TRUNCATE MySQL Tables');
        echo $this->getStyles();
        echo $this->getAlertHtml('danger', $message);
        stdfoot();
        exit();
    }
    
    /**
     * Get alert HTML
     */
    private function getAlertHtml($type, $message)
    {
        return <<<HTML
        <div class="ct-wrap fade-in">
            <div class="ct-panel">
                <div class="ct-titlebar">
                    <h1><i class="fas fa-exclamation-triangle"></i> Truncate Database Tables</h1>
                </div>
                <div class="ct-notice"><strong>{$message}</strong></div>
                <div class="ct-body">
                    <a href="{$this->scriptUrl}" class="ct-btn ct-btn-ghost">Go back</a>
                </div>
            </div>
        </div>
HTML;
    }
    
    /**
     * Show confirmation dialog
     */
    private function showConfirmation($tables)
    {
        global $mybb;

        $tableHash = base64_encode(implode(':', $tables));

        $items = '';
        foreach ($tables as $table) {
            $items .= '<div class="ct-result-row"><span class="ct-icon-fail"><i class="fas fa-trash"></i></span>' . $this->escapeHtml($table) . '</div>';
        }
        $count = count($tables);

        $confirmationHtml = <<<HTML
        <div class="ct-wrap fade-in">
            <div class="ct-panel">
                <div class="ct-titlebar">
                    <h1><i class="fas fa-shield-alt"></i> Confirm Truncation</h1>
                    <span class="ct-optag">STEP 2 OF 2</span>
                </div>

                <div class="ct-notice">
                    <strong>You are about to permanently delete all data in {$count} table(s).</strong>
                    This cannot be undone — make sure you have a backup before continuing.
                </div>

                <div class="ct-body">
                    <div class="ct-table-list">{$items}</div>

                    <div class="ct-divider"></div>

                    <div class="ct-actions">
                        <form method="post" action="{$this->scriptUrl}&do=clear&sure=true">
                            <input type="hidden" name="my_post_key" value="{$this->escapeHtml($mybb->post_code)}">
                            <input type="hidden" name="tablehash" value="{$this->escapeHtml($tableHash)}">
                            <button type="submit" class="ct-btn ct-btn-danger">
                                <i class="fas fa-check-circle"></i> Yes, truncate these tables
                            </button>
                        </form>
                        <a href="{$this->scriptUrl}" class="ct-btn ct-btn-ghost">Go back</a>
                    </div>
                </div>
            </div>
        </div>
HTML;
        
        stdhead('TRUNCATE MySQL Tables');
        echo $this->getStyles();
        echo $confirmationHtml;
        stdfoot();
        exit();
    }
    
    /**
     * Execute table truncation
     */
    private function executeTruncation($tables)
    {
        global $CURUSER;

        $success = [];
        $failed = [];
        
        foreach ($tables as $table) {
            if ($this->truncateTable($table)) {
                $success[] = $table;
            } else {
                $failed[] = $table;
            }
        }

        // Логирование - раньше отсутствовало вообще, хотя TRUNCATE
        // необратим. Пишем и успешные, и провалившиеся попытки отдельно,
        // чтобы при разборе инцидента было видно, кто и что реально снёс,
        // а что просто пытался.
        if (!empty($success)) {
            write_log(sprintf(
                '[CLEARTABLE] Admin: %s (UID %d) TRUNCATED table(s): %s',
                $CURUSER['username'] ?? 'unknown',
                (int)($CURUSER['id'] ?? 0),
                implode(', ', $success)
            ), 'admin');
        }
        if (!empty($failed)) {
            write_log(sprintf(
                '[CLEARTABLE] Admin: %s (UID %d) FAILED to truncate table(s): %s',
                $CURUSER['username'] ?? 'unknown',
                (int)($CURUSER['id'] ?? 0),
                implode(', ', $failed)
            ), 'admin');
        }
        
        $this->showResults($success, $failed);
    }
    
    /**
     * Display results
     */
    private function showResults($success, $failed)
    {
        stdhead('TRUNCATE MySQL Tables - Results');
        echo $this->getStyles();
        echo $this->getJavaScript();

        echo '<div class="ct-wrap fade-in">';
        
        if (!empty($success)) {
            echo $this->getSuccessHtml($success);
        }
        
        if (!empty($failed)) {
            echo $this->getFailureHtml($failed);
        }
        
        echo '</div>';
        
        stdfoot();
        exit();
    }
    
    /**
     * Success HTML
     */
   private function getSuccessHtml(array $tables): string
{
    global $mybb;

    $items = '';
    foreach ($tables as $table) {
        $escaped = $this->escapeHtml($table);
        $items .= '
        <div class="ct-success-item" id="row_' . $escaped . '">
            <span class="ct-check-sq"><i class="fas fa-check"></i></span>
            ' . $escaped . ' - successfully truncated!
            <span id="opt_status_' . $escaped . '" class="ms-2"></span>
        </div>';
    }

    $count   = count($tables);
    $tablesAttr  = htmlspecialchars(json_encode($tables), ENT_QUOTES, 'UTF-8');
    $postKeyAttr = htmlspecialchars($mybb->post_code, ENT_QUOTES, 'UTF-8');

    return '
    <div class="ct-success-panel" id="ctSuccessPanel" data-tables="' . $tablesAttr . '" data-post-key="' . $postKeyAttr . '">
        <div class="ct-success-header">
            <span class="ct-check-circle"><i class="fas fa-check"></i></span> Success
        </div>
        <div class="ct-success-box">
            <div class="ct-success-lead">
                <span class="ct-check-sq"><i class="fas fa-check"></i></span> Operation completed successfully!
            </div>
            <div class="ct-success-sub">The following tables have been truncated:</div>
            <div class="ct-success-list">' . $items . '</div>
            <div class="ct-success-total">
                Total truncated: <span class="ct-pill">' . $count . '</span> table(s)
            </div>
        </div>

        <div class="ct-optimize-plain" id="opt_wrap">
            <div id="opt_progress" style="display:none;margin-bottom:1rem;">
                <div class="progress" style="height:6px;border-radius:4px;">
                    <div id="opt_bar" class="progress-bar bg-warning" style="width:0%"></div>
                </div>
                <div class="mt-2" style="font-size:.82rem;color:var(--text-dim)" id="opt_label">Optimizing…</div>
            </div>

            <div id="opt_done" style="display:none;color:#1e8e4f;font-weight:600;margin-bottom:1rem;">
                <i class="bi bi-lightning-charge-fill"></i> All tables optimized.
            </div>

            <i class="bi bi-database-fill ct-db-icon"></i>
            <h4>Don\'t forget to optimize your tables!</h4>
            <div class="ct-actions" style="justify-content:center">
                <button type="button" id="btnOptimize" class="ct-btn ct-btn-blue">
                    <i class="fas fa-rocket"></i> Optimize Tables
                </button>
                <a href="' . $this->scriptUrl . '" class="ct-btn ct-btn-blue">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>';
}
    
    /**
     * Failure HTML
     */
    private function getFailureHtml($tables)
    {
        $items = '';
        foreach ($tables as $table) {
            $items .= <<<HTML
            <div class="ct-result-row">
                <span class="ct-icon-fail"><i class="fas fa-times"></i></span>
                {$this->escapeHtml($table)}
            </div>
HTML;
        }
        
        return <<<HTML
        <div class="ct-panel">
            <div class="ct-titlebar">
                <h1><i class="fas fa-exclamation-triangle"></i> Failed</h1>
            </div>
            <div class="ct-notice">
                <strong>Failed to truncate the following table(s):</strong>
            </div>
            <div class="ct-body">
                <div class="ct-table-list">{$items}</div>
            </div>
        </div>
HTML;
    }
    

    
    /**
     * Display table selection form
     */
    public function showSelectionForm()
    {
        $tables = $this->getAllTables();
        $options = $this->generateTableOptions($tables);
        
        stdhead('TRUNCATE MySQL Tables');
        
        echo $this->getStyles();
        echo $this->getFormHtml($options);
        echo $this->getJavaScript();
        
        stdfoot();
    }
    
    /**
     * Generate select options
     */
    private function generateTableOptions($tables)
    {
    $rows = '';
    foreach ($tables as $table) {
        $isProtected = in_array(strtolower($table), self::PROTECTED_TABLES, true);
        $escaped = $this->escapeHtml($table);
        if ($isProtected) {
            // Показываем защищённые таблицы прямо в списке, но заблокированными -
            // это честнее, чем молча их прятать: видно, что система реально
            // не даст выбрать критичное, а не просто "таблицы не хватает".
            $rows .= <<<HTML
            <label class="ct-row ct-locked" data-name="{$escaped}">
                <input type="checkbox" disabled>
                <span class="ct-tname">{$escaped}</span>
                <span class="ct-lock-tag"><i class="bi bi-lock-fill"></i> protected</span>
            </label>
HTML;
        } else {
            $rows .= <<<HTML
            <label class="ct-row" data-name="{$escaped}">
                <input type="checkbox" name="tablenames[]" value="{$escaped}" class="ct-check">
                <span class="ct-tname">{$escaped}</span>
            </label>
HTML;
        }
    }
    return $rows;
    }
	
	
	
	
	
	
	
    
    /**
     * CSS styles
     */
    private function getStyles()
    {
        return '<link rel="stylesheet" href="' . $this->baseUrl . '/admin/templates/cleartable.css">' . "\n";
    }

    /**
     * Form HTML
     */
    private function getFormHtml($options)
    {
        $protectedList = implode(', ', self::PROTECTED_TABLES);
        $totalProtected = $this->countProtected();

        return <<<HTML
        <div class="ct-wrap fade-in">
            <div class="ct-panel">
                <div class="ct-titlebar">
                    <h1><i class="fas fa-database"></i> Truncate Database Tables</h1>
                    <span class="ct-optag">IRREVERSIBLE</span>
                </div>

                <div class="ct-notice">
                    <strong>TRUNCATE deletes every row in the tables you select. There is no undo.</strong>
                    Take a database backup before continuing.
                </div>

                <div class="ct-body">
                    <form method="post" action="{$this->scriptUrl}&do=clear" id="truncateForm">

                        <div class="ct-toolbar">
                            <div class="ct-count"><strong id="ctCount">0</strong> table(s) selected</div>
                            <div class="ct-search-wrap">
                                <i class="bi bi-search ct-search-icon"></i>
                                <input type="text" id="ctSearch" class="ct-search" placeholder="Filter tables…">
                            </div>
                            <div style="display:flex;gap:.5rem;">
                                <button type="button" id="ctAll" class="ct-side-btn"><i class="bi bi-check2-all"></i> All</button>
                                <button type="button" id="ctNone" class="ct-side-btn"><i class="bi bi-x-lg"></i> Clear</button>
                                <button type="button" id="ctInvert" class="ct-side-btn"><i class="bi bi-arrow-left-right"></i> Invert</button>
                            </div>
                        </div>

                        <div class="ct-table-list" id="ctList">
                            {$options}
                        </div>

                        <div class="ct-notice" style="border-radius:10px;margin-top:1rem;">
                            <i class="bi bi-lock-fill"></i> {$totalProtected} table(s) are locked and cannot be truncated from here:
                            <span class="ct-mono">{$this->escapeHtml($protectedList)}</span>
                        </div>

                        <div class="ct-divider"></div>

                        <div class="ct-actions">
                            <button type="submit" class="ct-btn ct-btn-danger">
                                <i class="fas fa-trash-alt"></i> Truncate selected tables
                            </button>
                            <a href="{$this->scriptUrl}" class="ct-btn ct-btn-ghost">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
HTML;
    }

    /**
     * JavaScript code
     */
	private function getJavaScript(): string
    {
        return '<script src="' . $this->baseUrl . '/admin/scripts/cleartable.js"></script>' . "\n";
    }
    
    /**
     * HTML escaping
     */
    private function escapeHtml($text)
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function countProtected(): int
    {
        return count(self::PROTECTED_TABLES);
    }
}

// Initialize and execute
$manager = new TableTruncationManager($db, $config, $_this_script_, $BASEURL);
$manager->processTruncation();
$manager->showSelectionForm();
?>