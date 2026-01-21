<?php
$rootpath = './../';
$thispath = './';
require_once $rootpath . 'global.php';

if (($usergroups['cansettingspanel'] == '0' OR $usergroups['cansettingspanel'] != '1')) 
{
    stdhead();
    error_no_permission(true);
    exit();
}

stdhead();

// Initialize session for query history if not exists
if (!isset($_SESSION['query_history'])) 
{
    $_SESSION['query_history'] = [];
}

// Add current query to history if it was executed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'ts_execute_sql_query') {
    $query = trim($_POST['query'] ?? '');
    if (!empty($query)) {
        // Add to history (limit to 20 most recent)
        array_unshift($_SESSION['query_history'], [
            'query' => $query,
            'timestamp' => time()
        ]);
        $_SESSION['query_history'] = array_slice($_SESSION['query_history'], 0, 20);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Query Editor</title>
    
   
    <style>
       
        .sql-container {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-top: 1.5rem;
            overflow: hidden;
        }
        .sql-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1.2rem;
            margin-bottom: 1.8rem;
        }
        .sql-editor {
            border: 1px solid #ced4da;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            font-family: 'Fira Code', monospace;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        .sql-editor:focus-within {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        .sql-toolbar {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 0.9rem;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-run {
            background: linear-gradient(to right, #4e54c8, #8f94fb);
            border: none;
            border-radius: 30px;
            padding: 0.6rem 1.8rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-run:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(78, 84, 200, 0.3);
        }
        .result-container {
            margin-top: 2.2rem;
            transition: all 0.3s ease;
        }
        .alert-box {
            border-radius: 8px;
            border-left: 5px solid #0d6efd;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
        }
        .form-label i {
            margin-right: 8px;
            color: #4e54c8;
        }
        .examples {
            margin-top: 1.5rem;
            padding: 1.2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4e54c8;
        }
        .example-btn {
            margin: 0.3rem;
            font-size: 0.85rem;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        #query {
            font-family: 'Fira Code', monospace;
            font-size: 15px;
            line-height: 1.5;
            padding: 1.2rem;
            min-height: 200px;
            border: none;
            border-radius: 8px 8px 0 0;
        }
        #query:focus {
            outline: none;
            box-shadow: none;
        }
        .tooltip-icon {
            font-size: 0.9rem;
            color: #6c757d;
            margin-left: 8px;
            cursor: pointer;
        }
        .connection-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .connection-status.connected {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .connection-status.disconnected {
            background-color: #f8d7da;
            color: #842029;
        }
        .history-modal .history-item {
            border-left: 3px solid transparent;
            transition: all 0.2s;
            cursor: pointer;
        }
        .history-modal .history-item:hover {
            border-left-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .history-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        @media (max-width: 768px) {
            .sql-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-group {
                margin-bottom: 10px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="sql-container">
            <div class="sql-header">
                <h2 class="mb-1"><i class="fas fa-database me-2 text-primary"></i>SQL Query Editor</h2>
                <p class="text-muted">Execute SQL queries and analyze results</p>
                
                <?php
                // Ensure DB connection is initialized
                if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli'] instanceof mysqli) {
                    $GLOBALS['mysqli'] = new mysqli($config['database']['hostname'], $config['database']['username'], $config['database']['password'], $config['database']['database']);
                    
                    if ($GLOBALS['mysqli']->connect_errno) {
                        echo '<div class="connection-status disconnected">
                                <i class="fas fa-times-circle me-2"></i>Connection error: ' . $GLOBALS['mysqli']->connect_error . '
                              </div>';
                    } else {
                        echo '<div class="connection-status connected">
                                <i class="fas fa-check-circle me-2"></i>Connected to database: tracker
                              </div>';
                    }
                } else {
                    echo '<div class="connection-status connected">
                            <i class="fas fa-check-circle me-2"></i>Database connection already established
                          </div>';
                }

                $query   = '';
                $alert   = '';
                $table   = '';

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'ts_execute_sql_query') {
                    $query = trim($_POST['query'] ?? '');
                    if (!empty($query)) {
                        $doquery = mysqli_query($GLOBALS['mysqli'], $query);

                        if ($doquery) {
                            // SELECT: show results
                            if (stripos($query, 'SELECT') === 0) {
                                $num_rows = mysqli_num_rows($doquery);

                                if ($num_rows > 0) {
                                    $alert = '<div class="alert alert-success mt-3">
                                                  <strong>Success!</strong> Query executed. 
                                                  <br>Rows found: <strong>' . $num_rows . '</strong>
                                              </div>';
                                    $table = '<div class="table-responsive mt-3"><table class="table table-bordered table-sm table-striped table-hover align-middle">';
                                    $first = true;

                                    while ($row = mysqli_fetch_assoc($doquery)) {
                                        if ($first) {
                                            $table .= '<thead class="table-light"><tr>';
                                            foreach (array_keys($row) as $col) {
                                                $table .= '<th>' . htmlspecialchars($col) . '</th>';
                                            }
                                            $table .= '</tr></thead><tbody>';
                                            $first = false;
                                        }
                                        $table .= '<tr>';
                                        foreach ($row as $val) {
                                            $table .= '<td style="white-space: nowrap; max-width: 250px; overflow: hidden; text-overflow: ellipsis;">' 
                                                    . htmlspecialchars((string)$val) . '</td>';
                                        }
                                        $table .= '</tr>';
                                    }

                                    $table .= '</tbody></table></div>';
                                } else {
                                    $alert = '<div class="alert alert-info mt-3"><strong>Info:</strong> Query returned no results.</div>';
                                }
                            } else {
                                // Non-SELECT queries
                                $count = mysqli_affected_rows($GLOBALS['mysqli']);
                                $alert = '<div class="alert alert-success mt-3">
                                              <strong>Success!</strong> Query executed. 
                                              <br>Rows affected: <strong>' . max(0, $count) . '</strong>
                                          </div>';
                            }
                        } else {
                            $alert = '<div class="alert alert-danger mt-3">
                                          <strong>Error!</strong> ' . htmlspecialchars(mysqli_error($GLOBALS['mysqli'])) . '
                                      </div>';
                        }
                    } else {
                        $alert = '<div class="alert alert-warning mt-3">
                                      <strong>Warning!</strong> Empty SQL query.
                                  </div>';
                    }
                }
                ?>
            </div>
            
            <form method="post" action="#sql-executor">
                <input type="hidden" name="do" value="ts_execute_sql_query">
                
                <div class="mb-4">
                    <label for="query" class="form-label">
                        <i class="fas fa-code"></i>SQL Query
                        <span class="tooltip-icon" data-bs-toggle="tooltip" title="Enter your SQL query in this field">
                            <i class="fas fa-question-circle"></i>
                        </span>
                    </label>
                    <div class="sql-editor">
                        <textarea name="query" id="query" rows="6" 
                                class="form-control"
                                placeholder="Enter your SQL query here"><?= htmlspecialchars($query) ?></textarea>
                    </div>
                </div>
                
                <div class="sql-toolbar">
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="format-btn">
                            <i class="fas fa-indent me-1"></i> Format
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="history-btn">
                            <i class="fas fa-history me-1"></i> History
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="clear-btn">
                            <i class="fas fa-broom me-1"></i> Clear
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-play me-1"></i> Execute Query
                    </button>
                </div>
            </form>
            
            <div class="examples">
                <h6><i class="fas fa-lightbulb me-2 text-warning"></i>Query Examples:</h6>
                <button class="btn btn-sm example-btn btn-outline-secondary" data-query="SELECT * FROM users;">SELECT all users</button>
                <button class="btn btn-sm example-btn btn-outline-secondary" data-query="SELECT username, email FROM users WHERE enabled = 'yes';">Active users</button>
                <button class="btn btn-sm example-btn btn-outline-secondary" data-query="INSERT INTO users (name, email) VALUES ('John', 'john@example.com');">Add user</button>
                <button class="btn btn-sm example-btn btn-outline-secondary" data-query="UPDATE users SET enabled = 'no' WHERE id = 5;">Update record</button>
            </div>
            
            <div class="result-container mt-4">
                <h5><i class="fas fa-list-alt me-2 text-success"></i>Results:</h5>
                <?= $alert ?>
                <?= $table ?>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade history-modal" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel"><i class="fas fa-history me-2"></i>Query History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($_SESSION['query_history'])): ?>
                        <div class="list-group">
                            <?php foreach ($_SESSION['query_history'] as $index => $historyItem): ?>
                                <div class="list-group-item history-item p-3" data-query="<?= htmlspecialchars($historyItem['query']) ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Query #<?= count($_SESSION['query_history']) - $index ?></h6>
                                        <small class="history-time"><?= date('Y-m-d H:i:s', $historyItem['timestamp']) ?></small>
                                    </div>
                                    <p class="mb-1 font-monospace small"><?= htmlspecialchars(substr($historyItem['query'], 0, 100)) ?><?= strlen($historyItem['query']) > 100 ? '...' : '' ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No query history yet. Your executed queries will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="clear-history-btn">Clear History</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sql-formatter/11.0.2/sql-formatter.min.js"></script>
    <script>
        // Initialize Bootstrap tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Format button
        document.getElementById('format-btn').addEventListener('click', function() {
            const textarea = document.getElementById('query');
            try {
                const formatted = sqlFormatter.format(textarea.value);
                textarea.value = formatted;
            } catch (e) {
                alert('Formatting error: ' + e.message);
            }
        });
        
        // Clear button
        document.getElementById('clear-btn').addEventListener('click', function() {
            document.getElementById('query').value = '';
        });
        
        // History button - show modal
        document.getElementById('history-btn').addEventListener('click', function() {
            const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
            historyModal.show();
        });
        
        // Query examples
        document.querySelectorAll('.example-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('query').value = this.getAttribute('data-query');
            });
        });
        
        // History item selection
        document.querySelectorAll('.history-item').forEach(item => {
            item.addEventListener('click', function() {
                document.getElementById('query').value = this.getAttribute('data-query');
                const historyModal = bootstrap.Modal.getInstance(document.getElementById('historyModal'));
                historyModal.hide();
            });
        });
        
        // Clear history button
        document.getElementById('clear-history-btn').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all query history?')) {
                // This would typically be done via AJAX to a PHP endpoint
                // For this example, we'll just reload the page with a clear parameter
                window.location.href = window.location.pathname + '?clear_history=1';
            }
        });
        
        // Scroll to results after query execution
        <?php if (!empty($alert)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.result-container').scrollIntoView({
                behavior: 'smooth'
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Clear history if requested
if (isset($_GET['clear_history']) && $_GET['clear_history'] == 1) {
    $_SESSION['query_history'] = [];
    header('Location: ' . str_replace('?clear_history=1', '', $_SERVER['REQUEST_URI']));
    exit();
}

stdfoot();
?>