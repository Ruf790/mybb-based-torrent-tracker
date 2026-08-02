<?php


declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center" style="font-family: system-ui, -apple-system, sans-serif; font-size: 1rem; color: #dc2626;">
            <strong>🚫 Access Denied!</strong> Direct access to this file is prohibited.
          </div>');
}

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger text-center" style="font-family: system-ui, -apple-system, sans-serif; font-size: 1rem; color: #dc2626;">
            <strong>🚫 Access Denied!</strong> You do not have permission to access this page.
          </div>');
}

const AB_VERSION = 'Enhanced Amountbonus Module v0.8.5';
const EOL = PHP_EOL;

// Group IDs that must never be targeted by bulk distribution (Moderator, Administrator, Sysop — canstaffpanel=1)
const PROTECTED_BULK_GROUPS = [6, 7, 8];

global $mybb;

/**
 * Process bonus points distribution
 */
function processBonusDistribution(): bool
{
    global $db, $CURUSER, $BASEURL, $mybb;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        displayError('⚠️ Invalid security token. Please refresh the page and try again.');
        return false;
    }
    
    // Validate and sanitize input with PHP 8.x features
    $seedbonus = filter_input(INPUT_POST, 'seedbonus', FILTER_VALIDATE_INT, 
        ['options' => ['min_range' => 1, 'max_range' => 1000000]]
    );
    
    $username = trim($_POST['username'] ?? '');
    $toAll = $_POST['toall'] ?? '';
    $usergroup = filter_input(INPUT_POST, 'usergroup', FILTER_VALIDATE_INT) ?: null;
    
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
            if ($usergroup !== null && $usergroup > 0 && in_array($usergroup, PROTECTED_BULK_GROUPS, true)) {
                displayError('🚫 Bulk distribution to staff or administrative groups is not allowed.');
                return false;
            }
            distributeToAll($seedbonus, $usergroup, $modcomment, $moderatorName);
        } elseif ($username !== '') {
            distributeToUser($seedbonus, $username, $modcomment, $moderatorName);
        } else {
            displayError('Please specify either a username or select "All Users".');
            return false;
        }
        return true;
    } catch (InvalidArgumentException | RuntimeException $e) {
        // Known, safe-to-display validation/business-logic errors
        displayError('❌ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES));
        return false;
    } catch (Throwable $e) {
        // Unexpected/internal errors — don't leak details to the browser
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
    
    if ($group !== null && $group > 0 && in_array($group, PROTECTED_BULK_GROUPS, true)) {
        throw new InvalidArgumentException('Bulk distribution to staff or administrative groups is not allowed.');
    }
    
    // Build WHERE clause
    $whereClause = "WHERE ustatus = 'confirmed'";
    $targetDescription = 'All confirmed users';
    $params = [$points, $comment];

    if ($group > 0) {
        $whereClause .= " AND usergroup = ?";
        $params[] = $group;
        $targetDescription = get_user_class_name($group) . ' group';
    }

    // Using prepared statement
    $query = "UPDATE users SET seedbonus = seedbonus + ?, modcomment = CONCAT(?, modcomment) $whereClause";

    if (!$db->sql_query_prepared($query, $params)) {
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
    
    // Update user's bonus points - using prepared statement
    $updateQuery = "UPDATE users SET seedbonus = seedbonus + ?, modcomment = CONCAT(?, modcomment) WHERE username = ?";

    if (!$db->sql_query_prepared($updateQuery, [$points, $comment, $username])) {
        throw new RuntimeException('Failed to update user account.');
    }
    
    // Check if user was updated
    if ($db->affected_rows() === 0) {
        throw new RuntimeException("User '$username' not found.");
    }
    
    // Get user ID for redirection
    $selectQuery = "SELECT id, username FROM users WHERE username = ? LIMIT 1";

    $result = $db->sql_query_prepared($selectQuery, [$username]);
    $userData = $result ? $db->fetch_array($result) : null;
    
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
 * Store a flash message to be rendered later, in the correct place in the page layout
 * (echoing directly here would run before stdhead() and break the page structure)
 */
function setFlashMessage(string $type, string $message): void
{
    $GLOBALS['_bonus_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Render the stored flash message (call this from within the page layout, after stdhead())
 */
function renderFlashMessage(): void
{
    $flash = $GLOBALS['_bonus_flash'] ?? null;
    if (!$flash) {
        return;
    }

    if ($flash['type'] === 'error') {
        echo <<<HTML
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                    <div class="flex-grow-1">
                        <strong>Error:</strong> {$flash['message']}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        HTML;
    } else {
        echo <<<HTML
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-3 fs-4"></i>
                    <div class="flex-grow-1">
                        <strong>Success!</strong> {$flash['message']}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        HTML;
    }
}

/**
 * Display error message
 */
function displayError(string $message): void
{
    setFlashMessage('error', $message);
}

/**
 * Display success message
 */
function displaySuccess(string $message): void
{
    setFlashMessage('success', $message);
}

/**
 * Log action to system log
 */
function logAction(string $message, string $type = 'INFO'): void
{
    // Using write_log function from your system
    if (function_exists('write_log')) {
        write_log($message);
    }
}

/**
 * Generate user group select box with modern PHP
 */
function generateGroupSelect(string $name = 'usergroup'): string
{
    $groups = [
        '' => '👥 All User Groups',
        2 => '👤 User',
        3 => '⚡ Power User',
        4 => '⭐ VIP',
        5 => '📤 Uploader',
        6 => '🛡️ Moderator',
        7 => '👑 Administrator',
        8 => '🔧 Sysop',
    ];
    
    $html = '<select name="' . htmlspecialchars($name) . '" class="form-select form-select-lg">';
    
    foreach ($groups as $value => $label) {
        $isProtected = $value !== '' && in_array($value, PROTECTED_BULK_GROUPS, true);
        
        $valueAttr = $value !== '' ? 'value="' . htmlspecialchars((string)$value) . '"' : '';
        $selected = $value === '' ? ' selected' : '';
        $disabledAttr = $isProtected ? ' disabled' : '';
        $displayLabel = $isProtected ? $label . ' (staff — protected)' : $label;
        
        $html .= sprintf(
            '<option %s%s%s>%s</option>',
            $valueAttr,
            $selected,
            $disabledAttr,
            htmlspecialchars($displayLabel)
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
    
    $query = "SELECT COUNT(*) as total_users, SUM(seedbonus) as total_bonus, AVG(seedbonus) as avg_bonus FROM users WHERE ustatus = 'confirmed'";
    $result = $db->sql_query_prepared($query);
    $stats = $result ? $db->fetch_array($result) : null;
    
    if (!$stats) {
        return;
    }
    
    $totalUsers = number_format((int)($stats['total_users'] ?? 0));
    $totalBonus = number_format((float)($stats['total_bonus'] ?? 0));
    $avgBonus   = number_format((float)($stats['avg_bonus'] ?? 0));
    
    $cards = [
        ['icon' => 'fa-users',       'color' => '#3b82f6', 'label' => 'Confirmed Users',   'value' => $totalUsers],
        ['icon' => 'fa-coins',       'color' => '#f59e0b', 'label' => 'Total Bonus Points','value' => $totalBonus],
        ['icon' => 'fa-chart-line',  'color' => '#22c55e', 'label' => 'Avg. per User',     'value' => $avgBonus],
    ];
    
    echo '<div class="row g-3 mb-4">';
    foreach ($cards as $c) {
        echo <<<HTML
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                             style="width:48px;height:48px;background:{$c['color']}1a;">
                            <i class="fa-solid {$c['icon']}" style="color:{$c['color']};font-size:20px;"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold lh-1">{$c['value']}</div>
                            <div class="text-secondary" style="font-size:13px;">{$c['label']}</div>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
    echo '</div>';
}

// Main execution
try {
    $processed = processBonusDistribution();
} catch (Throwable $e) {
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
            
            <!-- Flash message -->
            <?php renderFlashMessage(); ?>
            
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
                                <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                                
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
                                <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                                
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


<script src="<?= $BASEURL ?>/admin/scripts/amountbonus.js"></script>
</body>
</html>

<?php
stdfoot();