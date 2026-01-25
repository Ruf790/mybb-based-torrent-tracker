<?php

declare(strict_types=1);

/**
 * Upload Amount Management Tool
 * 
 * @package StaffTools
 * @version 0.5
 * @author xam
 */

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger m-3"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> Direct access is not permitted.</div>');
}

define('UA_VERSION', '0.5 by xam');
define('GB_IN_BYTES', 1024 ** 3); // 1 GB in bytes




/**
 * Return usergroup name with fallback
 */
function get_user_class_name_fallback(int $id): string {
    $name = get_user_class_name($id);
    return $name !== '' ? $name : 'Unknown Group';
}



/**
 * Generate GB amount dropdown selector
 */
function class_amount(): void {
    echo <<<HTML
    <select name="classamount" class="form-select form-select-sm border pe-5 w-auto" required>
        <option value="" disabled selected>Select amount</option>
    HTML;

    for ($i = 1; $i <= 50; $i++) {
        $selected = ($i === 10) ? ' selected' : '';
        echo <<<HTML
        <option value="{$i}"{$selected}>{$i} GB</option>
        HTML;
    }

    echo '</select>';
}




function bulk_preview(int $class): int {
    global $db;

    $sql = "SELECT COUNT(*) AS cnt FROM users WHERE enabled='yes' AND ustatus='confirmed'";
    $params = [];

    if ($class > 0) {
        $sql .= " AND usergroup = ?";
        $params[] = $class;
    }

    $resObj = $db->sql_query_prepared($sql, $params);

    if (!$resObj) {
        return 0; // ошибка запроса
    }

    $row = mysqli_fetch_assoc($resObj->result);
    return (int)($row['cnt'] ?? 0);
}







/**
 * Process bulk upload addition for user groups
 */
function process_bulk_addition(int $class, int $amount): void {
    global $db, $CURUSER, $BASEURL, $eol;

    $query_conditions = "enabled = 'yes' AND ustatus = 'confirmed'";
    $params = [];
    
    if ($class > 0) {
        $query_conditions .= " AND usergroup = ?";
        $params[] = $class;
    }

    // Считаем пользователей заранее
    $count_sql = "SELECT COUNT(*) AS cnt FROM users WHERE $query_conditions";
    $count_result = $db->sql_query_prepared($count_sql, $params);
    $row = mysqli_fetch_assoc($count_result->result);
    $affected_rows = (int)($row['cnt'] ?? 0);

    $modcomment = sprintf(
        "%s - Received %s Upload Amount from %s (Upload Add Tool)%s",
        gmdate('Y-m-d'),
        mksize($amount * GB_IN_BYTES),
        $CURUSER['username'],
        $eol
    );

    $ulamount = $amount * GB_IN_BYTES;

    $sql = <<<SQL
    UPDATE users 
    SET uploaded = uploaded + ?,
        modcomment = CONCAT(?, modcomment)
    WHERE {$query_conditions}
    SQL;

    $query_params = array_merge([$ulamount, $modcomment], $params);
    $result = $db->sql_query_prepared($sql, $query_params);

    if ($result === false) {
        stderr('Database Error', 'Failed to update users');
    }

    $group_info = $class > 0 ? "User Group ID: $class" : 'all users';
    write_log("{$amount} GB upload added to {$group_info} (affected: {$affected_rows}) by {$CURUSER['username']}");

    $total_size = mksize($amount * GB_IN_BYTES * $affected_rows);

    stdhead('Upload Successfully Added');
    

echo '
<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">
<div class="container mt-3">
    <div class="card error-card">
        <div class="card-header22 success text-white d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2" style="font-size:1.5rem;"></i>
            <span>Upload Successfully Added</span>
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>' . $amount . ' GB</strong> upload has been added to <strong>' . htmlspecialchars($group_info) . '</strong></p>
            <p class="mb-1"><strong>Users affected:</strong> ' . $affected_rows . '</p>
            <p class="mb-0"><strong>Total upload:</strong> ' . $total_size . '</p>

            <div class="mt-3">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</div>';




	
    stdfoot();
    exit;
}




/**
 * Process individual user upload addition
 */
function process_individual_addition(string $username, int $amount_gb): void {
    global $db, $CURUSER, $eol, $_this_script_;

    $modcomment = sprintf(
        "%s - Received %s Upload Amount from %s (Upload Add Tool)%s",
        gmdate('Y-m-d'),
        mksize($amount_gb * GB_IN_BYTES),
        $CURUSER['username'],
        $eol
    );

    $ulBytes = $amount_gb * GB_IN_BYTES;

    // Используем подготовленные выражения
    $sql = <<<SQL
    UPDATE users 
    SET uploaded = uploaded + ?,
        modcomment = CONCAT(?, modcomment)
    WHERE username = ?
      AND enabled = 'yes'
      AND ustatus = 'confirmed'
    SQL;

    $result = $db->sql_query_prepared($sql, [$ulBytes, $modcomment, $username]);
    
    if ($result === false) {
        stderr('Database Error', 'Failed to update user');
    }

    if ($db->affected_rows() === 0) {
        stderr('Error', 'User not found or unable to update account.');
    }

    // Log action
    write_log("{$amount_gb} GB upload added to user: {$username} by {$CURUSER['username']}");
    
    // Success message
    flash_message(
        sprintf('%d GB upload added to user: %s', $amount_gb, htmlspecialchars($username)),
        'success'
    );
    
    admin_redirect($_this_script_);
}

/**
 * Parse usergroup value from _selectbox_
 */
function parse_usergroup_value($value): int {
    if ($value === '-' || $value === '' || $value === '0' || $value === 0) {
        return 0; // All Users
    }
    
    return (int)$value;
}

/**
 * Validate and sanitize input
 */
function validate_input(array $input): array {
    $errors = [];
    
    // Validate individual user form
    if (!empty($input['username'])) {
        $username = trim($input['username']);
        $amount_gb = (int)($input['uploaded'] ?? 0);
        
        if (empty($username)) {
            $errors[] = 'Username cannot be empty';
        }
        
        // Проверка длины имени пользователя
        if (strlen($username) < 3 || strlen($username) > 25) {
            $errors[] = 'Username must be between 3-25 characters';
        }
        
        // Проверка на допустимые символы
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, underscores and hyphens';
        }
        
        if ($amount_gb < 1 || $amount_gb > 1000) {
            $errors[] = 'Amount must be between 1-1000 GB';
        }
        
        if (empty($errors)) {
            return ['type' => 'individual', 'username' => $username, 'amount' => $amount_gb];
        }
    }
    
    // Validate bulk form
    if (($input['doit'] ?? '') === 'yes') {
        $class_raw = $input['usergroup'] ?? '-';
        $class = parse_usergroup_value($class_raw);
        $amount = (int)($input['classamount'] ?? 0);
        
        if ($amount < 1 || $amount > 50) {
            $errors[] = 'Please select a valid amount between 1-50 GB';
        }
        
        // Проверяем, что класс не отрицательный
        if ($class < 0) {
            $errors[] = 'Invalid user group selected';
        }
        
        if (empty($errors)) {
            return ['type' => 'bulk', 'class' => $class, 'amount' => $amount];
        }
    }
    
    if (!empty($errors)) {
        stderr('Validation Error', implode('<br>', $errors));
    }
    
    return [];
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

// Determine EOL character
$eol = PHP_EOL;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        /* ===============================
           BULK PREVIEW (NO APPLY)
           =============================== */
        if (
            ($_POST['doit'] ?? '') === 'yes'
            && isset($_POST['preview'])
        ) {
            $class  = (int)($_POST['usergroup'] ?? 0);
            $amount = (int)($_POST['classamount'] ?? 0);

            if ($amount < 1 || $amount > 50) {
                echo '<div class="alert alert-danger">Invalid amount selected.</div>';
                exit;
            }

            $count = bulk_preview($class);
            $group = $class > 0
                ? get_user_class_name_fallback($class)
                : 'ALL USERS';
            $total_bytes = $count * $amount * GB_IN_BYTES;
            $total_size = mksize($total_bytes);

            stdhead('Preview');
			// Custom Bootstrap preview block
            echo '
<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">

<div class="container mt-3">
    <div class="card error-card">
        <div class="card-header22 success">
            <i class="bi bi-check-circle-fill me-2" style="font-size:2rem;"></i>
            <div>
                <h2 class="mb-0">Preview Bulk Upload</h2>
                <p class="mb-0 opacity-75">Review the upload before applying.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-success" role="alert">
                <strong>Group:</strong> '.htmlspecialchars($group).'<br>
                <strong>Users affected:</strong> '.$count.'<br>
                <strong>Per user:</strong> '.$amount.' GB<br>
                <strong>Total upload:</strong> '.$total_size.'
            </div>

            <hr>

            <form method="post">
                <input type="hidden" name="doit" value="yes">
                <input type="hidden" name="usergroup" value="'.$class.'">
                <input type="hidden" name="classamount" value="'.$amount.'">
                <button class="btn btn-danger btn-lg w-100">
                    Confirm & Apply
                </button>
            </form>
        </div>
    </div>
</div>';
stdfoot();
            exit; // Прерываем дальнейшее выполнение, чтобы форма превью не смешивалась с остальным
        }

        /* ===============================
           FINAL APPLY
           =============================== */
        $validated = validate_input($_POST);

        if (!empty($validated)) {
            if ($validated['type'] === 'bulk') {
                process_bulk_addition(
                    (int)$validated['class'],
                    (int)$validated['amount']
                );
            } elseif ($validated['type'] === 'individual') {
                process_individual_addition(
                    $validated['username'],
                    (int)$validated['amount']
                );
            }
        }

    } catch (Throwable $e) {
        error_log(
            "Upload Add Tool Error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}"
        );
        echo '<div class="alert alert-danger">An unexpected error occurred. Please try again.</div>';
    }
}



// ============================================================================
// DISPLAY INTERFACE
// ============================================================================

stdhead('Update Users Upload Amounts');

?>
<div class="container-lg">
    <!-- Header -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary bg-gradient text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Amount Management</h4>
                    <small class="opacity-75">Version <?= htmlspecialchars(UA_VERSION) ?></small>
                </div>
                <span class="badge bg-light text-primary fs-6">Staff Tool</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Individual User Form -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-primary border-top-0 border-top-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-user-circle me-2"></i>Individual User
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= htmlspecialchars($_this_script_) ?>" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" 
                                   id="username"
                                   name="username" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter exact username"
                                   required
                                   pattern="[a-zA-Z0-9_-]{3,25}"
                                   title="3-25 characters, letters, numbers, underscores or hyphens"
                                   autocomplete="off">
                            <div class="invalid-feedback">
                                Please enter a valid username (3-25 characters, letters, numbers, underscores or hyphens).
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="uploaded" class="form-label fw-semibold">
                                <i class="fas fa-hdd me-1"></i>Upload Amount
                            </label>
                            <div class="input-group input-group-lg">
                                <input type="number" 
                                       id="uploaded"
                                       name="uploaded" 
                                       class="form-control" 
                                       min="1" 
                                       max="1000" 
                                       value="10"
                                       step="1"
                                       required>
                                <span class="input-group-text">GB</span>
                                <div class="invalid-feedback">
                                    Amount must be between 1-1000 GB.
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3">
                            <i class="fas fa-plus-circle me-2"></i>Add Upload to User
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-light">
                    <small class="text-muted">
                        <i class="fas fa-lightbulb me-1"></i>
                        Updates will be logged in user's modcomment.
                    </small>
                </div>
            </div>
        </div>

        <!-- Bulk Group Form -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-warning border-top-0 border-top-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-warning">
                        <i class="fas fa-users me-2"></i>Bulk Group Operation
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= htmlspecialchars($_this_script_) ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="doit" value="yes">
                        
                        <div class="mb-3">
                            <label for="usergroup" class="form-label fw-semibold">
                                <i class="fas fa-layer-group me-1"></i>User Group
                            </label>
                            <?php 
                            // Используем вашу существующую функцию _selectbox_
                            // Значение "-" будет означать "All Users"
                            echo _selectbox_('', 'usergroup', true, 'All Users (Any Group)', '-');
                            ?>
                            <div class="form-text">
                                Select "All Users" to apply to all confirmed users.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="classamount" class="form-label fw-semibold">
                                <i class="fas fa-database me-1"></i>Amount to Add
                            </label>
                            <?php class_amount(); ?>
                            <div class="invalid-feedback">
                                Please select a valid amount.
                            </div>
                        </div>
						
						
						 <button type="submit"
            name="preview"
            value="1"
            class="btn btn-outline-warning btn-lg w-100 mb-2">
        <i class="fas fa-eye me-2"></i>Preview
    </button>

						
						
						
						
						
                        
                        <button type="submit" 
                                class="btn btn-warning btn-lg w-100 py-3"
                                onclick="return confirm('This will affect ALL users in selected group. Continue?')">
                            <i class="fas fa-bolt me-2"></i>Apply to Entire Group
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-light">
                    <small class="text-muted">
                        <i class="fas fa-exclamation-triangle me-1 text-warning"></i>
                        Use with caution! This action cannot be undone.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics & Info -->
    <div class="row mt-4 g-3">
        <div class="col-md-4">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle me-2"></i>About This Tool</h6>
                    <p class="small mb-0">
                        Add upload credit to reward users or compensate for issues.
                        All actions are permanently logged.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body">
                    <h6><i class="fas fa-history me-2"></i>Audit Trail</h6>
                    <p class="small mb-0">
                        Every change is recorded in both user's modcomment 
                        and system log with staff username.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body">
                    <h6><i class="fas fa-shield-alt me-2"></i>Safety & Security</h6>
                    <ul class="small mb-0 ps-3">
                        <li>Prepared statements prevent SQL injection</li>
                        <li>Input validation on all fields</li>
                        <li>All actions are logged</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="card mt-4 bg-light border">
        <div class="card-body py-2">
            <div class="row text-center">
                <div class="col-md-3">
                    <small class="text-muted d-block">Version</small>
                    <strong><?= htmlspecialchars(UA_VERSION) ?></strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Max Individual</small>
                    <strong>1000 GB</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Max Bulk</small>
                    <strong>50 GB</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Target Users</small>
                    <strong>Confirmed & Enabled</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(() => {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php
stdfoot();