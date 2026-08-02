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
    
    public function __construct($db, $config, $scriptUrl, $baseUrl)
    {
        $this->db = $db;
        $this->config = $config;
        $this->scriptUrl = $scriptUrl;
        $this->baseUrl = $baseUrl;
    }
    
    /**
     * Validate table name
     */
    private function validateTableName($tableName)
    {
        return !empty($tableName) && preg_match('/^[a-zA-Z0-9_]+$/', $tableName);
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
        if (!$this->validateTableName($tableName)) {
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
                if ($this->validateTableName($table)) {
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
                if ($this->validateTableName($table)) {
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
        echo $this->getAlertHtml('danger', $message);
        stdfoot();
        exit();
    }
    
    /**
     * Get alert HTML
     */
    private function getAlertHtml($type, $message)
    {
        $icon = ($type === 'danger') ? 'fa-exclamation-triangle' : 'fa-info-circle';
        $alertClass = ($type === 'danger') ? 'alert-danger' : 'alert-warning';
        
        return <<<HTML
        <div class="container-md">
            <div class="alert {$alertClass} fade-in" role="alert">
                <i class="fas {$icon} me-2"></i>
                <strong>{$message}</strong>
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
        $tableList = implode(', ', $tables);
        
        $confirmationHtml = <<<HTML
        <div class="container-md">
            <div class="alert alert-warning fade-in">
                <div class="d-flex align-items-start">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                    <div class="flex-grow-1">
                        <h4 class="alert-heading mb-3"><i class="fas fa-shield-alt me-2"></i>Security Check</h4>
                        <p class="mb-3"><strong>We STRONGLY recommend backing up your database before truncating tables.</strong></p>
                        <p class="mb-3">Are you sure you want to truncate the following tables?</p>
                        
                        <div class="bg-dark text-light p-3 rounded-3 mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-database me-2"></i>
                                <strong>Selected Tables:</strong>
                            </div>
                            <div class="table-list">
                                <code class="text-info">{$this->escapeHtml($tableList)}</code>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-3">
                            <form method="post" action="{$this->scriptUrl}&do=clear&sure=true" class="d-inline">
                                <input type="hidden" name="my_post_key" value="{$this->escapeHtml($mybb->post_code)}">
                                <input type="hidden" name="tablehash" value="{$this->escapeHtml($tableHash)}">
                                <button type="submit" class="btn btn-danger btn-lg px-4">
                                    <i class="fas fa-check-circle me-2"></i>Yes, I am sure
                                </button>
                            </form>
                            <a href="{$this->scriptUrl}" class="btn btn-secondary btn-lg px-4">
                                <i class="fas fa-arrow-left me-2"></i>No, go back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
HTML;
        
        stdhead('TRUNCATE MySQL Tables');
        echo $confirmationHtml;
        stdfoot();
        exit();
    }
    
    /**
     * Execute table truncation
     */
    private function executeTruncation($tables)
    {
        $success = [];
        $failed = [];
        
        foreach ($tables as $table) {
            if ($this->truncateTable($table)) {
                $success[] = $table;
            } else {
                $failed[] = $table;
            }
        }
        
        $this->showResults($success, $failed);
    }
    
    /**
     * Display results
     */
    private function showResults($success, $failed)
    {
        stdhead('TRUNCATE MySQL Tables - Results');
        
        echo '<div class="container-md fade-in">';
        
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
    $items = '';
    foreach ($tables as $table) {
        $escaped = $this->escapeHtml($table);
        $items .= '
        <li class="text-dark mb-2" id="row_' . $escaped . '">
            <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
            <code class="text-dark">' . $escaped . '</code> - successfully truncated!
            <span id="opt_status_' . $escaped . '" class="ms-2"></span>
        </li>';
    }

    $count   = count($tables);
    $tablesJson = json_encode($tables);

    return '
    <div class="card mb-4">
        <div class="card-header bg-success text-white py-3">
            <h3 class="mb-0"><i class="fas fa-check-circle me-2"></i>Success</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <h4 class="alert-heading mb-3">✅ Operation completed successfully!</h4>
                <p class="mb-3">The following tables have been truncated:</p>
                <div class="bg-light p-3 rounded-3 mb-3">
                    <ul class="list-unstyled mb-0">' . $items . '</ul>
                </div>
                <p class="mb-0 fw-bold">
                    Total truncated: <span class="badge bg-success">' . $count . '</span> table(s)
                </p>
            </div>

            
			

            <!-- Optimize block -->
            <div class="mt-3">
                <div id="opt_progress" class="mb-3" style="display:none">
                    <div class="progress" style="height:8px;border-radius:4px">
                        <div id="opt_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             style="width:0%"></div>
                    </div>
                    <div class="mt-2 text-muted small" id="opt_label">Optimizing...</div>
                </div>

                <div id="opt_done" class="alert alert-info" style="display:none">
                    <i class="bi bi-lightning-charge-fill me-2"></i>
                    All tables optimized successfully!
                </div>

                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-database fa-3x text-primary mb-3 d-block"></i>
                        <h4>Don\'t forget to optimize your tables!</h4>
                        <button type="button" id="btnOptimize" class="btn btn-primary btn-lg mt-3">
                            <i class="fas fa-rocket me-2"></i>Optimize Tables
                        </button>
						<a href="' . $this->scriptUrl . '" class="btn btn-primary btn-lg mt-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                    </div>
                </div>
				
				
            </div>
			
			
			
        </div>
    </div>

    <script>
    (function(){
        const tables  = ' . $tablesJson . ';
        const btn     = document.getElementById("btnOptimize");
        const bar     = document.getElementById("opt_bar");
        const label   = document.getElementById("opt_label");
        const progress= document.getElementById("opt_progress");
        const done    = document.getElementById("opt_done");

        if (!btn) return;

        btn.addEventListener("click", function(){
            btn.disabled = true;
            btn.innerHTML = \'<i class="bi bi-hourglass-split me-2"></i>Optimizing...\';
            progress.style.display = "block";

            let idx = 0;

            function optimizeNext() {
                if (idx >= tables.length) {
                    progress.style.display = "none";
                    done.style.display = "block";
                    btn.style.display = "none";
                    return;
                }

                const table = tables[idx];
                const pct   = Math.round(((idx) / tables.length) * 100);
                bar.style.width   = pct + "%";
                label.textContent = "Optimizing: " + table + " (" + (idx+1) + "/" + tables.length + ")";

                const statusEl = document.getElementById("opt_status_" + table);

                fetch(window.location.href, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "do=ajax_optimize&table=" + encodeURIComponent(table)
                })
                .then(r => r.json())
                .then(data => {
                    if (statusEl) {
                        statusEl.innerHTML = data.success
                            ? \'<span class="badge bg-primary"><i class="bi bi-lightning-charge-fill"></i> optimized</span>\'
                            : \'<span class="badge bg-danger">failed</span>\';
                    }
                    idx++;
                    bar.style.width = Math.round((idx / tables.length) * 100) + "%";
                    optimizeNext();
                })
                .catch(() => {
                    if (statusEl) statusEl.innerHTML = \'<span class="badge bg-danger">error</span>\';
                    idx++;
                    optimizeNext();
                });
            }

            optimizeNext();
        });
    })();
    </script>';
}
    
    /**
     * Failure HTML
     */
    private function getFailureHtml($tables)
    {
        $items = '';
        foreach ($tables as $table) {
            $items .= <<<HTML
            <li class="mb-2">
                <span class="badge bg-danger me-2"><i class="fas fa-times"></i></span>
                <code>{$this->escapeHtml($table)}</code>
            </li>
HTML;
        }
        
        return <<<HTML
        <div class="card">
            <div class="card-header bg-danger text-white py-3">
                <h3 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Errors</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h4 class="alert-heading mb-3">❌ Failed to truncate the following tables:</h4>
                    <ul class="mb-0">{$items}</ul>
                </div>
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
    $options = '';
    foreach ($tables as $table) {
        $options .= sprintf(
            '<option value="%s">🗄️ %s</option>',
            $this->escapeHtml($table),
            $this->escapeHtml($table)
        );
    }
    return $options;
    }
	
	
	
	
	
	
	
    
    /**
     * CSS styles
     */
    private function getStyles()
    {
        return <<<CSS
        <style>
            :root {
                --gradient-primary: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                --gradient-danger: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                --gradient-success: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            }
            
            .fade-in {
                animation: fadeIn 0.5s ease-in;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .table-select-modern {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border: 2px solid #dee2e6;
                border-radius: 12px;
                font-family: 'Courier New', 'Monaco', monospace;
                font-size: 14px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
            }
            
            .table-select-modern:focus {
                border-color: #007bff;
              
                transform: scale(1.01);
            }
            
            .table-select-modern option {
                padding: 12px 15px;
                border-bottom: 1px solid #e9ecef;
                transition: all 0.2s ease;
                background: white;
                color: #000000;
            }
            
            .table-select-modern option:hover {
                background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
                color: white !important;
                transform: translateX(5px);
            }
            
            .table-select-modern option:checked {
                background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
                color: white !important;
                font-weight: bold;
            }
            
            .card-modern {
                border-radius: 20px;
                overflow: hidden;
               
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .card-modern:hover {
                transform: translateY(-5px);
                
            }
            
            .btn-gradient {
                background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                border: none;
                border-radius: 12px;
                transition: all 0.3s ease;
            }
            
            .btn-gradient:hover {
                transform: translateY(-2px);
               
            }
            
            .btn-gradient-danger {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            }
            
            .btn-gradient-danger:hover {
              
            }
            
            .badge-glow {
                animation: glow 2s ease-in-out infinite;
            }
            
            @keyframes glow {
                0%, 100% { box-shadow: 0 0 5px rgba(0, 123, 255, 0.5); }
                50% { box-shadow: 0 0 20px rgba(0, 123, 255, 0.8); }
            }
            
            .table-list code {
                background: #2d3748;
                padding: 8px 12px;
                border-radius: 8px;
                display: inline-block;
                font-size: 13px;
            }
            
            .text-dark {
                color: #000000 !important;
            }
			
			
			/* ── ct-* styles ── */
.ct-label {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: .6rem;
}
.ct-hint {
    margin-left: auto;
    font-size: .82rem;
    font-weight: 400;
    color: #94a3b8;
}
.ct-select-wrap { display: flex; gap: .85rem; align-items: flex-start; }
.ct-select {
    flex: 1;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: .4rem;
    font-size: .95rem;
    font-family: "Fira Code","Courier New",monospace;
    color: #1e293b;
    background: #f8fafc;
    outline: none;
    min-height: 340px;
}
.ct-select:focus { border-color: #1a56db; box-shadow: 0 0 0 3px rgba(26,86,219,.12); }
.ct-select option { padding: 5px 8px; font-size: .95rem; }
.ct-select option:checked { background: #1a56db; color: #fff; }
.ct-sidebar { display: flex; flex-direction: column; gap: .5rem; min-width: 120px; }
.ct-count-badge {
    text-align: center;
    font-size: .9rem;
    font-weight: 700;
    color: #1a56db;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: .4rem .5rem;
    border-radius: 10px;
}
.ct-count-badge span { font-size: .72rem; font-weight: 400; color: #64748b; display: block; }
.ct-search-wrap { position: relative; }
.ct-search {
    width: 100%;
    padding: .45rem .5rem .45rem 2rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: .9rem;
    background: #fff;
    outline: none;
    box-sizing: border-box;
    color: #1e293b;
}
.ct-search:focus { border-color: #1a56db; }
.ct-search-icon {
    position: absolute;
    left: .6rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: .8rem;
    color: #94a3b8;
    pointer-events: none;
}
.ct-side-btn {
    width: 100%;
    padding: .5rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    color: #374151;
    font-size: .875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
}
.ct-side-btn:hover { background: #f1f5f9; }
.ct-side-btn-primary { background: #1a56db; color: #fff; border-color: #1a56db; }
.ct-side-btn-primary:hover { background: #1648c0; }
.ct-divider { height: 1px; background: #f1f5f9; margin: 1.5rem 0; }
.ct-actions { display: flex; gap: .75rem; flex-wrap: wrap; }
.ct-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .6rem 1.4rem;
    border-radius: 10px;
    font-size: .95rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all .15s;
}
.ct-btn-danger { background: #dc2626; color: #fff; }
.ct-btn-danger:hover { background: #b91c1c; transform: translateY(-1px); }
.ct-btn-ghost { background: #f3f4f6; color: #374151; border: 1.5px solid #e5e7eb; }
.ct-btn-ghost:hover { background: #e5e7eb; }
			
			
			
			
			
        </style>
CSS;
    }
    
    /**
     * Form HTML
     */
    private function getFormHtml($options)
{
    return <<<HTML
    <div class="container-md fade-in">
        <div class="card">
            <div class="card-header text-white py-4" style="background: var(--gradient-primary);">
                <div class="d-flex align-items-center justify-content-between">
                    <h2 class="mb-0">
                        <i class="fas fa-database me-2"></i>
                        TRUNCATE MySQL Tables
                    </h2>
                    <span class="badge bg-light text-dark badge-glow px-3 py-2">
                        <i class="fas fa-code me-1"></i>
                    </span>
                </div>
            </div>
            
            <div class="card-body p-4">
                <div class="alert alert-info border-0 rounded-3 mb-4 fade-in">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                        <div>
                            <h4 class="alert-heading mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Important Notice</h4>
                            <p class="mb-2">TRUNCATE permanently removes ALL data from selected tables.</p>
                            <p class="mb-0"><strong class="text-danger">⚠️ Always backup your database before performing this operation!</strong></p>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="{$this->scriptUrl}&do=clear" id="truncateForm">

                    <div class="ct-label">
                        <i class="bi bi-table me-2"></i>Select tables to truncate
                        <span class="ct-hint">Hold Ctrl / Cmd to multi-select</span>
                    </div>

                    <div class="ct-select-wrap">
                        <select name="tablenames[]" id="ctSelect" multiple size="18" class="ct-select">
                            {$options}
                        </select>
                        <div class="ct-sidebar">
                            <div class="ct-count-badge">
                                <strong id="ctCount">0</strong>
                                <span>selected</span>
                            </div>
                            <div class="ct-search-wrap">
                                <i class="bi bi-search ct-search-icon"></i>
                                <input type="text" id="ctSearch" class="ct-search" placeholder="Filter...">
                            </div>
                            <button type="button" id="ctAll" class="ct-side-btn ct-side-btn-primary">
                                <i class="bi bi-check2-all"></i> All
                            </button>
                            <button type="button" id="ctNone" class="ct-side-btn">
                                <i class="bi bi-x-lg"></i> None
                            </button>
                            <button type="button" id="ctInvert" class="ct-side-btn">
                                <i class="bi bi-arrow-left-right"></i> Invert
                            </button>
                        </div>
                    </div>

                    <div class="ct-divider"></div>

                    <div class="ct-actions">
                        <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-2"></i>
                                🚨 TRUNCATE SELECTED TABLES
                            </button>
                        <a href="{$this->scriptUrl}" class="btn btn-secondary btn-lg px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
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
    return '
<script>
(function(){
    const sel   = document.getElementById("ctSelect");
    const srch  = document.getElementById("ctSearch");
    const cnt   = document.getElementById("ctCount");
    const opts  = sel ? Array.from(sel.options) : [];

    const upd = () => { if(cnt) cnt.textContent = Array.from(sel.selectedOptions).length; };

    if (sel) sel.addEventListener("change", upd);

    if (srch) {
        srch.addEventListener("input", function(){
            const q = this.value.toLowerCase();
            opts.forEach(o => {
                o.hidden = !o.text.toLowerCase().includes(q);
                if (o.hidden) o.selected = false;
            });
            upd();
        });
    }

    const ctAll = document.getElementById("ctAll");
    if (ctAll) ctAll.addEventListener("click", () => {
        opts.forEach(o => { if (!o.hidden) o.selected = true; }); upd();
    });

    const ctNone = document.getElementById("ctNone");
    if (ctNone) ctNone.addEventListener("click", () => {
        opts.forEach(o => o.selected = false); upd();
    });

    const ctInvert = document.getElementById("ctInvert");
    if (ctInvert) ctInvert.addEventListener("click", () => {
        opts.forEach(o => { if (!o.hidden) o.selected = !o.selected; }); upd();
    });

    const form = document.getElementById("truncateForm");
    if (form) {
        form.addEventListener("submit", function(e){
            const n = Array.from(sel.selectedOptions).length;
            if (n === 0) {
                e.preventDefault();
                alert("Select at least one table.");
                return;
            }
            if (!confirm("Truncate " + n + " table(s)?\n\nAll data will be permanently deleted.\nThis cannot be undone!")) {
                e.preventDefault();
            }
        });
    }
})();
</script>';
}
    
    /**
     * HTML escaping
     */
    private function escapeHtml($text)
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Initialize and execute
$manager = new TableTruncationManager($db, $config, $_this_script_, $BASEURL);
$manager->processTruncation();
$manager->showSelectionForm();
?>