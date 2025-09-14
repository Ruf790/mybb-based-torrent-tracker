<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define('CT_VERSION', '0.4 by xam');

// Функция для проверки и очистки таблиц
function processTableTruncation() {
    global $db, $config, $_this_script_, $BASEURL;
    
    // Проверяем, была ли отправлена форма
    if (isset($_GET['do']) && $_GET['do'] == 'clear') {
        // Проверяем, были ли выбраны таблицы через POST или переданы через GET
        $tables = [];
        
        if (isset($_POST['tablenames']) && is_array($_POST['tablenames']) && count($_POST['tablenames']) > 0) {
            $tables = $_POST['tablenames'];
        } elseif (isset($_GET['tablehash'])) {
            $tablehash = explode(':', base64_decode($_GET['tablehash']));
            if (is_array($tablehash)) {
                $tables = $tablehash;
            }
        }
        
        // Если таблицы не выбраны, выводим ошибку
        if (count($tables) == 0) {
            stderr('<div class="alert alert-danger"><b>Error!</b> No tables selected for truncation.</div>', false);
            return;
        }
        
        // Проверяем подтверждение действия
        if (!isset($_GET['sure'])) {
            $tablehash = base64_encode(implode(':', $tables));
            $tableList = implode(', ', $tables);
            
            $message = '<div class="alert alert-warning">
                <h4 class="text-dark">⚠️ Sanity check</h4>
                <p class="text-dark">We STRONGLY recommend backup before truncating any table.</p>
                <p class="text-dark">Are you sure you want to truncate the following tables?</p>
                <div class="bg-dark text-white p-3 rounded mb-3">
                    <strong>Selected Table(s):</strong><br>
                    <span style="font-family: monospace; font-size: 14px;">' . htmlspecialchars($tableList) . '</span>
                </div>
                <div class="mt-3">
                    <a href="' . $_this_script_ . '&do=clear&sure=true&tablehash=' . $tablehash . '" class="btn btn-danger me-2">Yes, I am sure</a>
                    <a href="' . $_this_script_ . '" class="btn btn-secondary">No, go back!</a>
                </div>
            </div>';
            
            stderr($message, false);
            return;
        }
        
        // Если подтверждение получено, очищаем таблицы
        $successCount = 0;
        $processedTables = [];
        
        foreach ($tables as $table) {
            // Проверяем имя таблицы на безопасность
            if (!empty($table) && preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                try {
                    $db->sql_query('TRUNCATE TABLE `' . $table . '`');
                    $processedTables[] = $table;
                    $successCount++;
                } catch (Exception $e) {
                    // Логируем ошибку, но продолжаем обработку других таблиц
                    error_log("Error truncating table $table: " . $e->getMessage());
                }
            }
        }
        
        // Выводим результат
        stdhead('TRUNCATE MySQL Tables');
        
        echo '<div class="container-md">
            <div class="card border-0 mb-4">
                <div class="card-header rounded-bottom bg-primary text-white">
                    <h2 class="mb-0">TRUNCATE MySQL Tables - Results</h2>
                </div>
                <div class="card-body">';
        
        if ($successCount > 0) {
            echo '<div class="alert alert-success">
                <h4 class="text-dark">✅ Success!</h4>
                <p class="text-dark">The following tables have been truncated successfully:</p>
                <div class="border p-3 rounded mb-3" style="background-color: #f8f9fa;">
                    <ul class="list-unstyled mb-0">';
            
            foreach ($processedTables as $table) {
                echo '<li class="text-dark mb-2" style="font-family: monospace; font-size: 15px;">
                    <span class="badge bg-success me-2">✓</span>
                    <strong>' . htmlspecialchars($table) . '</strong> - has been emptied!
                    </li>';
            }
            
            echo '</ul>
                </div>
                <p class="text-dark fw-bold">Total ' . $successCount . ' table(s) have been emptied!</p>
                <p class="text-dark">Please do not forget to optimize tables now! 
                <a href="' . $BASEURL . '/admin/managesettings.php?do=dboptimize" class="btn btn-sm btn-outline-primary ms-2">Optimize Tables</a></p>
            </div>';
        } else {
            echo '<div class="alert alert-danger">
                <h4 class="text-dark">❌ Error!</h4>
                <p class="text-dark">No tables were truncated. Please check your selection and try again.</p>
            </div>';
        }
        
        echo '</div></div></div>';
        stdfoot();
        exit();
    }
}

// Функция для отображения формы выбора таблиц
function showTableSelectionForm() {
    global $db, $config, $_this_script_;
    
    // Получаем список таблиц из базы данных
    $result = $db->sql_query('SHOW TABLES FROM ' . $config['database']['database']);
    $options = '';
    
    while ($row = $db->fetch_array($result)) {
        $tableName = $row['Tables_in_tracker'];
        $options .= '<option value="' . htmlspecialchars($tableName) . '">' . htmlspecialchars($tableName) . '</option>';
    }
    
    // Освобождаем результат запроса
    if (method_exists($db, 'free_result')) {
        $db->free_result($result);
    }
    
    // Выводим форму
    stdhead('TRUNCATE MySQL Tables');
    
    echo '<style>
        .table-select {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border: 2px solid #dee2e6;
            font-family: "Fira Code", "Monaco", "Consolas", monospace;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .table-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
            transform: translateY(-2px);
        }
        
        .table-select option {
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            transition: all 0.2s ease;
            background-color: #ffffff;
            color: #000000;
        }
        
        .table-select option:hover {
            background: linear-gradient(45deg, #007bff, #0056b3) !important;
            color: white !important;
            transform: scale(1.02);
        }
        
        .table-select option:checked {
            background: linear-gradient(45deg, #28a745, #20c997) !important;
            color: white !important;
            font-weight: bold;
        }
        
        .card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3) !important;
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220, 53, 69, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(45deg, #007bff, #0056b3);
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
        }
    </style>
    
    <div class="container-md">
        <div class="card border-0 mb-5">
            <div class="card-header rounded-bottom text-white py-4">
                <h2 class="mb-0"><i class="fas fa-database me-2"></i>TRUNCATE MySQL Tables</h2>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 mb-4">
                    <div class="d-flex align-items-center">
                        <span class="display-6 me-3">ℹ️</span>
                        <div>
                            <h4 class="alert-heading mb-2">Important Notice</h4>
                            <p class="mb-2">TRUNCATE permanently removes all data from the selected tables. This action cannot be undone.</p>
                            <p class="mb-0"><strong>⚠️ Always backup your database before performing this operation!</strong></p>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="' . $_this_script_ . '&do=clear" id="truncateForm">
                    <div class="mb-4">
                        <label for="tablesSelect" class="form-label fw-bold fs-5 text-dark">
                            <i class="fas fa-table me-2"></i>Select tables to truncate:
                        </label>
                        <select name="tablenames[]" id="tablesSelect" multiple size="15" class="form-select table-select">
                            ' . $options . '
                        </select>
                        <div class="form-text text-muted">
                            <i class="fas fa-mouse-pointer me-1"></i>Hold CTRL (or CMD on Mac) to select multiple tables
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mb-4">
                        <button type="button" id="selectAllBtn" class="btn btn-outline-primary">
                            <i class="fas fa-check-square me-2"></i>Select All
                        </button>
                        <button type="button" id="deselectAllBtn" class="btn btn-outline-secondary">
                            <i class="fas fa-times-circle me-2"></i>Deselect All
                        </button>
                    </div>
                    
                    <div class="d-flex gap-3 align-items-center mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-danger btn-lg px-4 py-3">
                            <i class="fas fa-trash-alt me-2"></i>🚨 TRUNCATE Selected Tables
                        </button>
                        <a href="' . $_this_script_ . '" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const selectAllBtn = document.getElementById("selectAllBtn");
            const deselectAllBtn = document.getElementById("deselectAllBtn");
            const tablesSelect = document.getElementById("tablesSelect");
            
            selectAllBtn.addEventListener("click", function() {
                Array.from(tablesSelect.options).forEach(option => {
                    option.selected = true;
                });
                this.classList.add("btn-success");
                deselectAllBtn.classList.remove("btn-success");
            });
            
            deselectAllBtn.addEventListener("click", function() {
                Array.from(tablesSelect.options).forEach(option => {
                    option.selected = false;
                });
                this.classList.add("btn-success");
                selectAllBtn.classList.remove("btn-success");
            });
            
            document.getElementById("truncateForm").addEventListener("submit", function(e) {
                const selectedOptions = Array.from(tablesSelect.selectedOptions);
                if (selectedOptions.length === 0) {
                    e.preventDefault();
                    alert("Please select at least one table to truncate.");
                } else {
                    return confirm("WARNING: This will permanently delete all data from " + selectedOptions.length + " tables!\\n\\nThis action cannot be undone!\\n\\nAre you absolutely sure you want to continue?");
                }
            });
        });
    </script>';
    
    stdfoot();
}

// Основная логика
processTableTruncation();
showTableSelectionForm();
?>