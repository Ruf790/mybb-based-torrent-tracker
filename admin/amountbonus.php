<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center" style="font-family: system-ui, -apple-system, sans-serif; font-size: 1rem; color: #dc2626;">
            <strong>🚫 Access Denied!</strong> Direct access to this file is prohibited.
          </div>');
}

const AB_VERSION = 'Enhanced Amountbonus Module v0.8.5';
const EOL = PHP_EOL;

/**
 * Process bonus points distribution
 */
function processBonusDistribution(): bool
{
    global $db, $CURUSER, $BASEURL;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    // Validate and sanitize input with PHP 8.x features
    $seedbonus = filter_input(INPUT_POST, 'seedbonus', FILTER_VALIDATE_INT, 
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );
    
    $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
    $toAll = $_POST['toall'] ?? '';
    $usergroup = filter_input(INPUT_POST, 'usergroup', FILTER_VALIDATE_INT);
    
    if ($seedbonus === false || $seedbonus === null) {
        displayError('❌ Please enter a valid number between 1 and 1,000,000 for bonus points.');
        return false;
    }
    
    $timestamp = gmdate('Y-m-d H:i:s');
    $moderatorName = $CURUSER['username'] ?? 'System';
    
    // Prepare modcomment using sprintf
    $modcomment = sprintf(
        "%s - Received %d bonus points from %s (Amount Bonus Tool)" . EOL,
        $timestamp,
        $seedbonus,
        $moderatorName
    );
    
    try {
        if ($toAll === 'yes') {
            distributeToAll($seedbonus, $usergroup, $modcomment, $moderatorName);
        } elseif (!empty($username)) {
            distributeToUser($seedbonus, $username, $modcomment, $moderatorName);
        } else {
            displayError('Please specify either a username or select "All Users".');
            return false;
        }
        return true;
    } catch (Throwable $e) {
        error_log('Bonus Distribution Error: ' . $e->getMessage());
        displayError('⚠️ An unexpected error occurred. Please try again.');
        return false;
    }
}

/**
 * Distribute bonus points to all users or specific group
 */
function distributeToAll(int $points, ?int $group, string $comment, string $moderator): void
{
    global $db;
    
    $escapedPoints = $db->sqlesc((string)$points);
    $escapedComment = $db->sqlesc($comment);
    
    // Build WHERE clause
    $whereClause = "WHERE ustatus = 'confirmed'";
    $targetDescription = 'All confirmed users';
    
    if ($group > 0) {
        $escapedGroup = $db->sqlesc((string)$group);
        $whereClause .= " AND usergroup = $escapedGroup";
        $targetDescription = get_user_class_name($group) . ' group';
    }
    
    // Using modern SQL syntax
    $query = <<<SQL
        UPDATE users 
        SET seedbonus = seedbonus + $escapedPoints, 
            modcomment = CONCAT($escapedComment, modcomment) 
        $whereClause
    SQL;
    
    if (!$db->sql_query($query)) {
        throw new RuntimeException('Failed to update user records.');
    }
    
    logAction(
        message: "$points bonus points distributed to $targetDescription by $moderator",
        type: 'BULK_DISTRIBUTION'
    );
    
    displaySuccess("✅ $points bonus points have been successfully sent to $targetDescription.");
}

/**
 * Distribute bonus points to specific user
 */
function distributeToUser(int $points, string $username, string $comment, string $moderator): void
{
    global $db, $BASEURL;
    
    if (strlen($username) < 3) {
        throw new InvalidArgumentException('Username must be at least 3 characters long.');
    }
    
    $escapedUsername = $db->sqlesc($username);
    $escapedPoints = $db->sqlesc((string)$points);
    $escapedComment = $db->sqlesc($comment);
    
    // Update user's bonus points - using modern syntax
    $updateQuery = <<<SQL
        UPDATE users 
        SET seedbonus = seedbonus + $escapedPoints, 
            modcomment = CONCAT($escapedComment, modcomment)
           
        WHERE username = $escapedUsername
    SQL;
    
    if (!$db->sql_query($updateQuery)) {
        throw new RuntimeException('Failed to update user account.');
    }
    
    // Check if user was updated
    if ($db->affected_rows() === 0) {
        throw new RuntimeException("User '$username' not found.");
    }
    
    // Get user ID for redirection
    $selectQuery = <<<SQL
        SELECT id, username 
        FROM users 
        WHERE username = $escapedUsername
        LIMIT 1
    SQL;
    
    $result = $db->sql_query($selectQuery);
    $userData = mysqli_fetch_assoc($result);
    
    if (!$userData) {
        throw new RuntimeException('Failed to retrieve user information.');
    }
    
    logAction(
        message: "$points bonus points sent to $username by $moderator",
        type: 'INDIVIDUAL_DISTRIBUTION'
    );
    
    // Redirect to user profile with modern header syntax
    header("Location: {$BASEURL}/" . get_profile_link($userData['id']));
    exit;
}

/**
 * Display error message
 */
function displayError(string $message): void
{
    echo <<<HTML
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                <div class="flex-grow-1">
                    <strong>Error:</strong> $message
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    HTML;
}

/**
 * Display success message
 */
function displaySuccess(string $message): void
{
    echo <<<HTML
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-3 fs-4"></i>
                <div class="flex-grow-1">
                    <strong>Success!</strong> $message
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    HTML;
}

/**
 * Log action to system log
 */
function logAction(string $message, string $type = 'INFO'): void
{
    global $CURUSER;
    $moderator = $CURUSER['username'] ?? 'System';
    $logMessage = sprintf("[%s] [%s] %s - %s", 
        gmdate('Y-m-d H:i:s'), 
        $type, 
        $moderator, 
        $message
    );
    
    // Using write_log function from your system
    if (function_exists('write_log')) {
        write_log($message);
    }
    
    // Also log to PHP error log for debugging
    error_log($logMessage);
}

/**
 * Generate user group select box with modern PHP
 */
function generateGroupSelect(string $name = 'usergroup'): string
{
    $groups = [
        '' => '👥 All User Groups',
        1 => '👤 Member',
        2 => '⭐ VIP',
        3 => '📤 Uploader',
        4 => '🛡️ Moderator',
        5 => '👑 Administrator',
        6 => '💎 Premium',
        7 => '🔧 System'
    ];
    
    $html = '<select name="' . htmlspecialchars($name) . '" class="form-select form-select-lg">';
    
    foreach ($groups as $value => $label) {
        $valueAttr = $value !== '' ? 'value="' . htmlspecialchars((string)$value) . '"' : '';
        $selected = $value === '' ? ' selected' : '';
        
        $html .= sprintf(
            '<option %s%s>%s</option>',
            $valueAttr,
            $selected,
            htmlspecialchars($label)
        );
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Display usage statistics (new feature)
 */
function displayStatistics(): void
{
    global $db;
    
    $query = "SELECT COUNT(*) as total_users, SUM(seedbonus) as total_bonus FROM users WHERE ustatus = 'confirmed'";
    $result = $db->sql_query($query);
    $stats = mysqli_fetch_assoc($result);
    
    if ($stats) {
        $totalUsers = number_format((int)($stats['total_users'] ?? 0));
        $totalBonus = number_format((float)($stats['total_bonus'] ?? 0));
        
        echo <<<HTML
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-light border-primary">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">📊 System Statistics</h6>
                            <div class="d-flex justify-content-between">
                                <span>Total Users:</span>
                                <span class="fw-bold">{$totalUsers}</span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span>Total Bonus Points:</span>
                                <span class="fw-bold text-success">{$totalBonus}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}

// Main execution
try {
    $processed = processBonusDistribution();
} catch (Throwable $e) {
    error_log('Critical error in bonus module: ' . $e->getMessage());
    displayError('A critical error occurred. Please contact the administrator.');
    $processed = false;
}

// Display page
stdhead('🎁 Bonus Points Management System');

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Points Management - Staff Panel</title>
    









</head>
<body>
<div class="container mt-3">
    <div class="row justify-content-center">
        <div class="col-xxl-10">
            <!-- Header -->
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="fas fa-gift text-gradient"></i>
                    Bonus Points Distribution
                </h1>
                <p class="lead text-muted">
                    <?= AB_VERSION ?>
                </p>
            </div>
            
            <!-- Statistics -->
            <?php displayStatistics(); ?>
            
            <!-- Main Card -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-rocket me-2"></i>
                            Distribution Panel
                        </h4>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-code-branch me-1"></i>
                            Staff Access
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-4" id="bonusTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" 
                                    data-bs-target="#single" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Single User
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" 
                                    data-bs-target="#bulk" type="button" role="tab">
                                <i class="fas fa-users me-2"></i>Bulk Distribution
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="bonusTabContent">
                        
                        <!-- Single User Tab -->
                        <div class="tab-pane fade show active" id="single" role="tabpanel">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <input type="hidden" name="act" value="amountbonus">
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-user-tag me-1"></i> Target User
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text">
                                                <i class="fas fa-at"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="username" 
                                                   placeholder="Enter exact username"
                                                   pattern=".{3,50}"
                                                   required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="document.querySelector('input[name=username]').value = '<?= htmlspecialchars($CURUSER['username'] ?? '') ?>'">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Enter the exact username (3-50 characters)
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-coins me-1"></i> Bonus Points
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="seedbonus" 
                                                   min="1" 
                                                   max="1000000"
                                                   placeholder="Amount"
                                                   required>
                                            <span class="input-group-text">points</span>
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="document.querySelector('input[name=seedbonus]').value = 1000">
                                                <i class="fas fa-bolt"></i> 1K
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Enter amount (1 - 1,000,000)
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-outline-secondary me-2">
                                            <i class="fas fa-undo me-1"></i> Clear
                                        </button>
                                        <button type="submit" class="btn btn-primary px-5">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Send Bonus Points
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Bulk Distribution Tab -->
                        <div class="tab-pane fade" id="bulk" role="tabpanel">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <input type="hidden" name="act" value="amountbonus">
                                <input type="hidden" name="toall" value="yes">
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-filter me-1"></i> Target Group
                                        </label>
                                        <?= generateGroupSelect('usergroup') ?>
                                        <div class="form-text">
                                            Select user group or leave for all users
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-coins me-1"></i> Points Amount
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="seedbonus" 
                                                   min="1" 
                                                   max="1000000"
                                                   placeholder="Amount"
                                                   required>
                                            <span class="input-group-text">points</span>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    Quick Set
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="setPoints(100)">100</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setPoints(500)">500</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setPoints(1000)">1,000</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="setPoints(5000)">5,000</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="form-text">
                                            Points will be added to each user in the selected group
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-4">
                                    <div class="d-flex">
                                        <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                                        <div>
                                            <strong>⚠️ Important:</strong> This action will affect ALL users in the selected group.
                                            Please double-check before proceeding.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-outline-warning me-2"
                                                onclick="confirmBulkDistribution()">
                                            <i class="fas fa-shield-alt me-1"></i> Preview
                                        </button>
                                        <button type="submit" class="btn btn-success px-5">
                                            <i class="fas fa-broadcast-tower me-2"></i>
                                            Distribute to All
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="card-footer bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-white">
                                <i class="fas fa-clock me-1"></i>
                                Server Time: <?= date('Y-m-d H:i:s') ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-white">
                                <i class="fas fa-user-shield me-1"></i>
                                Logged in as: <strong><?= htmlspecialchars($CURUSER['username'] ?? 'Guest') ?></strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card stat-card border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-history fa-2x text-primary mb-3"></i>
                            <h5>Recent Distributions</h5>
                            <p class="text-muted">View distribution history</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
                            <h5>Statistics</h5>
                            <p class="text-muted">Detailed bonus analytics</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-cogs fa-2x text-warning mb-3"></i>
                            <h5>Settings</h5>
                            <p class="text-muted">Configure bonus system</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Form validation
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Helper functions
function setPoints(points) {
    const inputs = document.querySelectorAll('input[name="seedbonus"]');
    inputs.forEach(input => input.value = points);
    return false;
}

function confirmBulkDistribution() {
    const points = document.querySelector('input[name="seedbonus"]').value;
    const groupSelect = document.querySelector('select[name="usergroup"]');
    const groupName = groupSelect.options[groupSelect.selectedIndex].text;
    
    if (!points || points < 1) {
        alert('Please enter a valid points amount first.');
        return;
    }
    
    const confirmation = confirm(
        `⚠️ BULK DISTRIBUTION CONFIRMATION\n\n` +
        `You are about to distribute ${points} bonus points\n` +
        `to ALL users in: ${groupName}\n\n` +
        `This action cannot be undone.\n\n` +
        `Click OK to proceed, or Cancel to review.`
    );
    
    if (confirmation) {
        document.querySelector('form').submit();
    }
}

// Auto-select current tab based on previous selection
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('bonusTab');
    if (savedTab) {
        const tab = new bootstrap.Tab(document.querySelector(savedTab));
        tab.show();
    }
    
    // Save tab selection
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            localStorage.setItem('bonusTab', e.target.getAttribute('data-bs-target'));
        });
    });
});
</script>
</body>
</html>

<?php
stdfoot();