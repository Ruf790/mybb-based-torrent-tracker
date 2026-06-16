<?php
declare(strict_types=1);



if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert">
            <h4 class="alert-heading"><i class="fas fa-ban me-2"></i>Access Denied</h4>
            <p class="mb-0">Direct initialization of this file is not allowed.</p>
          </div>');
}

use function htmlspecialchars as e;

const RA_VERSION = '0.2 by xam';
const GB_IN_BYTES = 1024 * 1024 * 1024;

/**
 * Generate class amount dropdown
 */
function generateClassAmountDropdown(): string {
    $options = '<option value="0" class="text-muted fst-italic" selected>(select amount)</option>';
    
    for ($i = 1; $i <= 50; $i++) {
        $options .= sprintf(
            '<option value="%d">%d GB</option>',
            $i,
            $i
        );
    }
    
    return <<<HTML
    <select name="classamount" class="form-select form-select-lg border" required>
        {$options}
    </select>
    HTML;
}

/**
 * Generate mod comment for logging
 */
function getModComment(int $amountGB): string {
    global $CURUSER;
    
    return sprintf(
        "%s - Removed %s Upload Amount by %s (Upload Remove Tool)\n",
        gmdate('Y-m-d'),
        mksize($amountGB * GB_IN_BYTES),
        $CURUSER['username'] ?? 'System'
    );
}

// Initialize variables
$error = null;
$success = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process mass removal form
        if (isset($_POST['doit']) && $_POST['doit'] === 'yes') {
            $class = isset($_POST['usergroup']) && $_POST['usergroup'] !== '-' ? (int)$_POST['usergroup'] : 0;
            $classAmount = isset($_POST['classamount']) ? (int)$_POST['classamount'] : 0;
            
            // Validation
            if ($classAmount < 1 || $classAmount > 50) {
                throw new InvalidArgumentException('Please select a valid amount between 1-50 GB.');
            }
            
            $removeAmount = $classAmount * GB_IN_BYTES;
            $modComment = getModComment($classAmount);
            
            // Build query based on whether we have a class filter
            if ($class > 0) {
                // Исправленный запрос: CASE WHEN для предотвращения отрицательных значений
                $updateQuery = "UPDATE users 
                               SET uploaded = CASE 
                                   WHEN uploaded >= ? THEN uploaded - ?
                                   ELSE 0 
                               END,
                                   modcomment = CONCAT(?, modcomment)
                               WHERE usergroup = ?";
                
                $result = $db->sql_query_prepared($updateQuery, [$removeAmount, $removeAmount, $modComment, $class]);
                $groupName = get_user_class_name($class);
            } else {
                // Для всех пользователей
                $updateQuery = "UPDATE users 
                               SET uploaded = CASE 
                                   WHEN uploaded >= ? THEN uploaded - ?
                                   ELSE 0 
                               END,
                                   modcomment = CONCAT(?, modcomment)";
                
                $result = $db->sql_query_prepared($updateQuery, [$removeAmount, $removeAmount, $modComment]);
                $groupName = 'ALL USERS';
            }
            
            if ($result === false) {
                throw new RuntimeException('Failed to update user upload amounts.');
            }
            
            //$affectedRows = $db->affected_rows();
			
			
			// Подсчитываем пользователей в целевой группе
if ($class > 0) {
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE usergroup = ?";
    $countResult = $db->sql_query_prepared($countQuery, [$class]);
} else {
    $countQuery = "SELECT COUNT(*) as total FROM users";
    $countResult = $db->sql_query_prepared($countQuery);
}

if ($countResult) {
    $countData = $db->fetch_array($countResult);
    $affectedRows = (int)($countData['total'] ?? 0);
} else {
    $affectedRows = 0;
}
			
			
			
			
            
            



stdhead();

$totalDataRemoved = mksize($classAmount * GB_IN_BYTES * $affectedRows);
$timestamp = date('Y-m-d H:i:s');
$performedBy = e($CURUSER['username'] ?? 'System');

echo <<<HTML
<link href="{$BASEURL}/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="{$BASEURL}/include/templates/default/style/errorss.css" rel="stylesheet">

<div class="container mt-3">
    <div class="card error-card">
        <div class="card-header22 success">
            <i class="bi bi-check-circle-fill me-2" style="font-size:2rem;"></i>
            <div>
                <h2 class="mb-0">Upload Removal Complete</h2>
                <p class="mb-0 opacity-75">User upload amounts successfully adjusted!</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-success" role="alert">
                <strong>Success!</strong> Removed {$classAmount} GB upload from {$groupName}
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Operation Details:</strong></p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Target Group</span>
                            <span class="badge bg-primary">{$groupName}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Amount Removed</span>
                            <span class="badge bg-danger">{$classAmount} GB</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Users Affected</span>
                            <span class="badge bg-success">{$affectedRows}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Performed By</span>
                            <span class="badge bg-info">{$performedBy}</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <p><strong>Operation Summary:</strong></p>
                    <div class="p-3 bg-light rounded">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Total Data Removed:</strong> {$totalDataRemoved}<br>
                        <i class="bi bi-clock me-2"></i>
                        <strong>Timestamp:</strong> {$timestamp}<br>
                        <i class="bi bi-shield-check me-2"></i>
                        <strong>Status:</strong> <span class="text-success">Completed Successfully</span>
                    </div>
                    <div class="mt-3">
                        <a href="{$_this_script_}" class="btn btn-outline-primary me-2">
                            <i class="bi bi-arrow-left me-1"></i> Back to Upload Remover
                        </a>
                        <a href="{$BASEURL}/admin/index.php" class="btn btn-primary">
                            <i class="bi bi-house me-1"></i> Return to Admin Panel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
   


        stdfoot();




			
			
			
			
			
			
            exit();
            
        } else {
            // Process single user form
            $username = trim($_POST['username'] ?? '');
            $uploaded = trim($_POST['uploaded'] ?? '');
            
            // Validation
            if (empty($username) || empty($uploaded)) {
                throw new InvalidArgumentException('Please fill in all required fields.');
            }
            
            if (!is_numeric($uploaded) || $uploaded < 1 || $uploaded > 50) {
                throw new InvalidArgumentException('Amount must be a number between 1-50 GB.');
            }
            
            $uploadedAmount = (int)$uploaded * GB_IN_BYTES;
            $modComment = getModComment((int)$uploaded);
            
            // Исправленный запрос для одного пользователя
            $updateQuery = "UPDATE users 
                           SET uploaded = CASE 
                               WHEN uploaded >= ? THEN uploaded - ?
                               ELSE 0 
                           END,
                               modcomment = CONCAT(?, modcomment)
                           WHERE username = ?";
            
            $result = $db->sql_query_prepared($updateQuery, [
                $uploadedAmount, 
                $uploadedAmount, 
                $modComment, 
                $username
            ]);
            
            if ($result === false) {
                throw new RuntimeException('Failed to update user upload.');
            }
            
            $affectedRows = $db->affected_rows();
            
            if ($affectedRows === 0) {
                throw new RuntimeException('User not found.');
            }
            
            // Get user ID for redirect
            $selectQuery = "SELECT id FROM users WHERE username = ?";
            $userResult = $db->sql_query_prepared($selectQuery, [$username]);
            
            if ($userResult) {
                $user = $db->fetch_array($userResult);
                if ($user) {
                    header('Location: ' . $BASEURL . '/userdetails.php?id=' . $user['id']);
                    exit();
                }
            }
            
            throw new RuntimeException('Unable to redirect to user profile.');
        }
        
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'An unexpected error occurred. Please try again.';
        error_log("Upload removal error: " . $e->getMessage());
    }
}

// Start output
stdhead('Remove Upload Amounts');

?>

<div class="container mt-3">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-danger text-white rounded-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-trash-alt fa-lg me-3"></i>
                        <div>
                            <h1 class="h4 mb-0">Upload Amount Remover</h1>
                            <p class="small mb-0 opacity-85">Deduct upload amounts from users</p>
                        </div>
                        <div class="ms-auto">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-toolbox me-1"></i>v<?= RA_VERSION ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if ($error): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Error</h5>
                            <p class="mb-0"><?= e($error) ?></p>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Single User Form -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-user-minus me-2"></i>Remove Upload from Single User
                    </h2>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="<?= $_this_script_ ?>" class="needs-validation" novalidate>
                        <div class="row g-4 align-items-end">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username" class="form-label fw-bold mb-2">
                                        <i class="fas fa-user me-2"></i>Username
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg" 
                                           name="username" 
                                           id="username"
                                           value="<?= e($_POST['username'] ?? '') ?>"
                                           placeholder="Enter username"
                                           required>
                                    <div class="invalid-feedback">
                                        Please enter a valid username
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="uploaded" class="form-label fw-bold mb-2">
                                        <i class="fas fa-cloud-download-alt me-2"></i>Amount to Remove
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <input type="number" 
                                               class="form-control" 
                                               name="uploaded" 
                                               id="uploaded"
                                               min="1" 
                                               max="50"
                                               value="<?= e($_POST['uploaded'] ?? '') ?>"
                                               placeholder="GB"
                                               required>
                                        <span class="input-group-text bg-light">GB</span>
                                    </div>
                                    <div class="invalid-feedback">
                                        Please enter amount between 1-50 GB
                                    </div>
                                    <small class="form-text text-muted mt-1 d-block">
                                        <i class="fas fa-info-circle me-1"></i>Upload will not go below 0 bytes
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-danger btn-lg w-100 py-3">
                                    <i class="fas fa-trash me-2"></i>Remove
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mass Removal Form -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-users me-2"></i>Mass Upload Removal
                    </h2>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="<?= $_this_script_ ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="doit" value="yes">
                        
                        <div class="alert alert-warning border-warning">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                                <div>
                                    <h5 class="alert-heading mb-1">⚠️ Warning: Bulk Operation</h5>
                                    <p class="mb-0">This will affect multiple users. Changes cannot be undone.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-4 align-items-end">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label fw-bold mb-2">
                                        <i class="fas fa-layer-group me-2"></i>User Group
                                    </label>
                                    <?= _selectbox_('', 'usergroup', true, 'All Users', $_POST['usergroup'] ?? '') ?>
                                    <small class="form-text text-muted mt-1 d-block">
                                        <i class="fas fa-filter me-1"></i>Filter by user group (select "All Users" for everyone)
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label fw-bold mb-2">
                                        <i class="fas fa-weight-hanging me-2"></i>Amount per User
                                    </label>
                                    <?= generateClassAmountDropdown() ?>
                                    <small class="form-text text-muted mt-1 d-block">
                                        <i class="fas fa-balance-scale me-1"></i>1-50 GB per user
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label fw-bold mb-2 d-block">&nbsp;</label>
                                    <button type="submit" 
                                            class="btn btn-warning btn-lg w-100 py-3"
                                            onclick="return confirmMassRemoval()">
                                        <i class="fas fa-bolt me-2"></i>Execute Mass Removal
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-3">
                                            <i class="fas fa-eye me-2"></i>Operation Details
                                        </h6>
                                        <div class="row text-center">
                                            <div class="col-md-6">
                                                <div class="p-3 bg-white rounded shadow-sm">
                                                    <small class="text-muted d-block mb-1">Target Group</small>
                                                    <span id="targetGroupPreview" class="fw-bold text-primary">All Users</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 bg-white rounded shadow-sm">
                                                    <small class="text-muted d-block mb-1">Amount per User</small>
                                                    <span id="amountPreview" class="fw-bold text-danger">-</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 p-3 bg-white rounded shadow-sm">
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-database me-1"></i>Database Safety Features:
                                            </small>
                                            <div class="row small">
                                                <div class="col-6">
                                                    <i class="fas fa-check text-success me-1"></i>Prevents negative values
                                                </div>
                                                <div class="col-6">
                                                    <i class="fas fa-check text-success me-1"></i>Uses prepared statements
                                                </div>
                                                <div class="col-6">
                                                    <i class="fas fa-check text-success me-1"></i>Logs all operations
                                                </div>
                                                <div class="col-6">
                                                    <i class="fas fa-check text-success me-1"></i>Affected rows counted
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Information Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-info text-white">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-info-circle me-2"></i>Operation Details
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-user-shield me-2"></i>Security Notes
                                </h6>
                                <ul class="mb-0 ps-3">
                                    <li>All operations are logged in user modcomments</li>
                                    <li>Upload will never go below 0 bytes</li>
                                    <li>Only staff members can access this tool</li>
                                    <li>Each operation is timestamped and attributed</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <h6 class="alert-heading">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </h6>
                                <ul class="mb-0 ps-3">
                                    <li><strong>Current User:</strong> <?= e($CURUSER['username'] ?? 'Unknown') ?></li>
                                    <li><strong>Session Time:</strong> <?= date('Y-m-d H:i:s') ?></li>
                                    <li><strong>Tool Version:</strong> <?= RA_VERSION ?></li>
                                    <li><strong>Database:</strong> MySQL Prepared</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Enhancements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Real-time preview for mass removal
    const updatePreview = function() {
        const groupSelect = document.querySelector('select[name="usergroup"]');
        const amountSelect = document.querySelector('select[name="classamount"]');
        
        if (groupSelect && amountSelect) {
            const groupText = groupSelect.options[groupSelect.selectedIndex].text;
            const amountText = amountSelect.options[amountSelect.selectedIndex].text;
            
            document.getElementById('targetGroupPreview').textContent = groupText;
            document.getElementById('amountPreview').textContent = amountText !== '(select amount)' ? amountText : '-';
        }
    };
    
    // Add event listeners to dropdowns
    const groupSelect = document.querySelector('select[name="usergroup"]');
    const amountSelect = document.querySelector('select[name="classamount"]');
    
    if (groupSelect) {
        groupSelect.classList.add('form-select-lg');
        groupSelect.addEventListener('change', updatePreview);
    }
    
    if (amountSelect) {
        amountSelect.addEventListener('change', updatePreview);
    }
    
    // Initial preview update
    updatePreview();
});

// Confirmation for mass removal
function confirmMassRemoval() {
    const groupSelect = document.querySelector('select[name="usergroup"]');
    const amountSelect = document.querySelector('select[name="classamount"]');
    
    if (!groupSelect || !amountSelect) return false;
    
    const groupName = groupSelect.options[groupSelect.selectedIndex].text;
    const amount = amountSelect.value;
    
    if (amount === '0') {
        alert('⚠️ Please select an amount to remove (1-50 GB).');
        return false;
    }
    
    return confirm(`⚠️ CONFIRM MASS REMOVAL\n\nRemove ${amount} GB from:\n${groupName}\n\nThis action affects ALL users in this group and cannot be undone!`);
}

// Auto-focus username field
function focusUsername() {
    document.getElementById('username').focus();
}

// Clear single user form
function clearSingleForm() {
    document.getElementById('username').value = '';
    document.getElementById('uploaded').value = '';
    document.getElementById('username').focus();
}
</script>

<style>
:root {
    --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    --warning-gradient: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.bg-gradient-danger {
    background: var(--danger-gradient) !important;
}

.bg-gradient-warning {
    background: var(--warning-gradient) !important;
}

.bg-gradient-info {
    background: var(--info-gradient) !important;
}

.card {
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.08);
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.card-header {
    border-bottom: none;
    font-weight: 600;
    padding: 1.25rem 1.5rem;
}

.form-control-lg, .form-select-lg {
    padding: 1rem 1.25rem;
    font-size: 1.1rem;
    border-radius: 10px;
}

.form-control:focus, .form-select:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

.input-group-text {
    background-color: #f8f9fa;
    border-radius: 0 10px 10px 0;
    font-weight: 500;
}

.btn {
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
}

.btn-danger {
    background: var(--danger-gradient);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
}

.btn-warning {
    background: var(--warning-gradient);
    color: #212529;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
}

.alert {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.badge {
    border-radius: 20px;
    padding: 0.5em 1em;
    font-weight: 500;
}

.was-validated .form-control:invalid,
.was-validated .form-select:invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.was-validated .form-control:valid,
.was-validated .form-select:valid {
    border-color: #198754;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>

<?php
stdfoot();
unset($error, $success);
?>