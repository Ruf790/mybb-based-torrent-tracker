<?php
declare(strict_types=1);

/******************************************************************/
/*==========[ TS Special Edition v.5.6 - Modernized ]============*/
/*====================[ Special Thanks To ]======================*/
/*        DrNet - wWw.SpecialCoders.CoM                         */
/*        Vinson - wWw.Decode4u.CoM                             */
/*    MrDecoder - wWw.Fearless-Releases.CoM                     */
/*        Fynnon - wWw.BvList.CoM                               */
/*****************************************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger m-3" role="alert">
            <h4 class="alert-heading"><i class="fas fa-ban me-2"></i>Access Denied</h4>
            <p class="mb-0">Direct initialization of this file is not allowed.</p>
          </div>');
}

use function htmlspecialchars as e;

// Initialize variables
$action = $_POST['action'] ?? '';
$value = null;
$error = null;
$success = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update') {
        try {
            $username = trim($_POST['username'] ?? '');
            $uploaded = trim($_POST['uploaded'] ?? '');
            $downloaded = trim($_POST['downloaded'] ?? '');
            
            // Validate input
            if (empty($username) || empty($uploaded) || empty($downloaded)) {
                throw new InvalidArgumentException('Please fill in all required fields.');
            }
            
            if (!is_numeric($uploaded) || !is_numeric($downloaded)) {
                throw new InvalidArgumentException('Uploaded and downloaded values must be numeric.');
            }
            
            $uploaded = (int)$uploaded;
            $downloaded = (int)$downloaded;
            
            if ($uploaded < 0 || $downloaded < 0) {
                throw new InvalidArgumentException('Values cannot be negative.');
            }
            
            // Update database using sql_query_prepared
            $updateQuery = "UPDATE users 
                           SET uploaded = ?, downloaded = ? 
                           WHERE username = ?";
            
            $result = $db->sql_query_prepared($updateQuery, [$uploaded, $downloaded, $username]);
            
            if ($result === false) {
                throw new RuntimeException('Database update failed.');
            }
            
            if ($db->affected_rows() === 0) {
                throw new RuntimeException('User not found.');
            }
            
            // Get user ID for redirect
            $selectQuery = "SELECT id FROM users WHERE username = ?";
            $userResult = $db->sql_query_prepared($selectQuery, [$username]);
            
            if ($userResult) {
                $user = $db->fetch_array($userResult);
                if ($user) {
                    $success = "User statistics updated successfully!";
					
					$member = get_profile_link((int)$user['id']);
					
					
					
					
                    // Redirect after 2 seconds
                    echo '<script>
                            setTimeout(function() {
                                window.location.href = "' . $BASEURL . '/'.$member . '";
                            }, 2000);
                          </script>';
                }
            }
            
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = 'An unexpected error occurred.';
            error_log("Update ratio error: " . $e->getMessage());
        }
        
    } elseif ($action === 'calculate') {
        $inputValue = trim($_POST['value'] ?? '');
        if (!empty($inputValue)) {
            $value = calculateValue($inputValue);
        }
    }
}

/**
 * Calculate value helper function
 */
function calculateValue(string $input): string {
    // Add your calculation logic here
    $input = trim($input);
    
    // Example: Convert to bytes if contains units
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(GB|MB|KB|B)?$/i', $input, $matches)) {
        $num = (float)$matches[1];
        $unit = strtoupper($matches[2] ?? 'B');
        
        $multipliers = [
            'GB' => 1024 * 1024 * 1024,
            'MB' => 1024 * 1024,
            'KB' => 1024,
            'B' => 1
        ];
        
        $bytes = $num * ($multipliers[$unit] ?? 1);
        
        // Format nicely
        if ($bytes >= 1099511627776) { // 1 TB
            return number_format($bytes / 1099511627776, 2) . ' TB';
        } elseif ($bytes >= 1073741824) { // 1 GB
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) { // 1 MB
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) { // 1 KB
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return number_format($bytes) . ' B';
    }
    
    return "Invalid input format";
}

// Start output
stdhead('Update User Statistics');

?>

<div class="container mt-3">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-chart-line fa-lg me-3"></i>
                        <div>
                            <h1 class="h4 mb-0">User Statistics Manager</h1>
                            <p class="small mb-0 opacity-75">Update user upload/download statistics</p>
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
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= e($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update Form -->
    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-user-edit me-2"></i>Update User Statistics
                    </h2>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="<?= $_this_script_ ?>" id="updateForm">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="row g-4">
                            <!-- Username -->
                            <div class="col-md-12">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= isset($_POST['username']) && empty($_POST['username']) ? 'is-invalid' : '' ?>"
                                           name="username" 
                                           id="username"
                                           value="<?= e($_POST['username'] ?? '') ?>"
                                           placeholder=" "
                                           required
                                           autofocus>
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user me-2"></i>Username
                                    </label>
                                    <div class="invalid-feedback">
                                        Please enter a username
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Uploaded -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= isset($_POST['uploaded']) && !is_numeric($_POST['uploaded']) ? 'is-invalid' : '' ?>"
                                           name="uploaded" 
                                           id="uploaded"
                                           value="<?= e($_POST['uploaded'] ?? '') ?>"
                                           placeholder=" "
                                           required
                                           data-bs-toggle="tooltip"
                                           title="Enter value in bytes or with unit (e.g., 1.5GB)">
                                    <label for="uploaded" class="form-label">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Uploaded
                                    </label>
                                    <div class="invalid-feedback">
                                        Please enter a valid number
                                    </div>
                                    <div class="form-text small">
                                        <i class="fas fa-info-circle me-1"></i>Example: 1073741824 or 1GB
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Downloaded -->
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?= isset($_POST['downloaded']) && !is_numeric($_POST['downloaded']) ? 'is-invalid' : '' ?>"
                                           name="downloaded" 
                                           id="downloaded"
                                           value="<?= e($_POST['downloaded'] ?? '') ?>"
                                           placeholder=" "
                                           required
                                           data-bs-toggle="tooltip"
                                           title="Enter value in bytes or with unit (e.g., 500MB)">
                                    <label for="downloaded" class="form-label">
                                        <i class="fas fa-cloud-download-alt me-2"></i>Downloaded
                                    </label>
                                    <div class="invalid-feedback">
                                        Please enter a valid number
                                    </div>
                                    <div class="form-text small">
                                        <i class="fas fa-info-circle me-1"></i>Example: 536870912 or 512MB
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="col-12">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="reset" class="btn btn-outline-secondary me-md-2">
                                        <i class="fas fa-redo me-2"></i>Reset
                                    </button>
                                    <button type="submit" class="btn btn-primary px-5">
                                        <i class="fas fa-save me-2"></i>Update Statistics
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Information Panel -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h3 class="h5 mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Quick Information
                    </h3>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Changes are irreversible
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-center border-0 py-2">
                            <i class="fas fa-database text-primary me-3 fa-fw"></i>
                            <div>
                                <small class="text-muted">Current Database</small>
                                <div class="fw-bold">MySQL</div>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center border-0 py-2">
                            <i class="fas fa-shield-alt text-success me-3 fa-fw"></i>
                            <div>
                                <small class="text-muted">Security Level</small>
                                <div class="fw-bold">Staff Only</div>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center border-0 py-2">
                            <i class="fas fa-history text-warning me-3 fa-fw"></i>
                            <div>
                                <small class="text-muted">Last Updated</small>
                                <div class="fw-bold"><?= date('Y-m-d H:i:s') ?></div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Calculator Form -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white rounded-0">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-calculator me-2"></i>Byte Calculator
                    </h2>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="<?= $_this_script_ ?>" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="calculate">
                        
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       name="value" 
                                       id="calcValue"
                                       value="<?= e($_POST['value'] ?? '') ?>"
                                       placeholder=" "
                                       required>
                                <label for="calcValue" class="form-label">
                                    <i class="fas fa-keyboard me-2"></i>Enter value to calculate
                                </label>
                                <div class="form-text small">
                                    Supports: 1.5GB, 1024MB, 500KB, 1024
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100 h-100 py-3">
                                <i class="fas fa-calculator me-2"></i>Calculate
                            </button>
                        </div>
                        
                        <?php if ($value !== null): ?>
                            <div class="col-12 mt-4">
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                                        <div>
                                            <h5 class="mb-1">Calculation Result</h5>
                                            <p class="mb-0 fs-4">
                                                <code class="bg-light px-3 py-2 rounded"><?= e($value) ?></code>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Enhancements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
    
    // Auto-format input fields
    const formatInput = function(input) {
        const value = input.value.trim();
        if (value.match(/^\d+(\.\d+)?[GMK]?B?$/i)) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else if (value.match(/^\d+$/)) {
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');
        } else if (value) {
            input.classList.add('is-invalid');
        }
    };
    
    document.getElementById('uploaded').addEventListener('blur', function() {
        formatInput(this);
    });
    
    document.getElementById('downloaded').addEventListener('blur', function() {
        formatInput(this);
    });
    
    // Confirm before submission
    document.getElementById('updateForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        if (!confirm(`Are you sure you want to update statistics for "${username}"?`)) {
            e.preventDefault();
        }
    });
});
</script>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.card-header {
    border-bottom: none;
    font-weight: 600;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.alert {
    border: none;
    border-radius: 8px;
}

.list-group-item {
    background: transparent;
}

.btn {
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

.form-floating > label {
    padding-left: 2.5rem;
}

.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label {
    padding-left: 0.5rem;
}

.form-floating > .fas {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    z-index: 3;
    color: #6c757d;
}

.form-floating > .form-control:focus ~ .fas {
    color: #0d6efd;
}
</style>

<?php
stdfoot();
unset($value, $error, $success, $action);
?>