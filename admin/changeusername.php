<?php

declare(strict_types=1);

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';



if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="error-message">❌ Error! Direct initialization of this file is not allowed.</div>');
}

define('CU_VERSION', '0.3 by xam');

// Initialize variables
$formSubmitted = false;
$confirmationNeeded = false;
$success = false;
$message = '';
$userId = '';
$oldUsername = '';
$newUsername = '';
$currentUsername = '';
$validationErrors = [];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'changeusername') {
    $formSubmitted = true;
    
    $userId = trim($_POST['id'] ?? '');
    $newUsername = trim($_POST['username'] ?? '');
    $sure = $_POST['sure'] ?? '';
    $oldUsername = $_POST['oldusername'] ?? '';
    
    // Validate user ID is numeric
    if (!is_numeric($userId)) {
        $message = 'Invalid user ID format. Must be a number.';
    }
    // Check super admin permissions
    elseif (is_super_admin((int)$userId) && $CURUSER['id'] != $userId && !is_super_admin($CURUSER['id'])) {
        $message = 'You do not have permission to change a super administrator\'s username.';
    }
    // Validate inputs
    elseif (empty($userId) || empty($newUsername)) {
        $message = 'Please fill in all required fields.';
    } elseif (!is_valid_id($userId)) {
        $message = 'Invalid user ID format.';
    } else {
        if ($sure === 'yes' && !empty($oldUsername)) {
            // Confirmation given, update username using UserDataHandler
            try {
                // Include MyBB user handler
                require_once INC_PATH . "/datahandlers/user.php";
                $userhandler = new UserDataHandler('update');
                
                // Check if user exists
                $escapedUserId = $db->sqlesc((int)$userId);
                $userQuery = $db->sql_query('SELECT id, username FROM users WHERE id = ' . $escapedUserId . ' LIMIT 1');
                
                if ($db->num_rows($userQuery) === 0) {
                    $message = 'No user found with this ID.';
                } else {
                    $user = mysqli_fetch_assoc($userQuery);
                    $oldUsername = $user['username'];
                    
                    // Set the data for the user update
                    $updated_user = [
                        "uid" => (int)$userId,
                        "username" => $newUsername
                    ];
                    
                    $userhandler->set_data($updated_user);
                    
                    // Validate the user
                    if (!$userhandler->validate_user()) {
                        $errors = $userhandler->get_friendly_errors();
                        $validationErrors = $errors;
                        $message = 'Validation failed: ' . implode(', ', $errors);
                    } else {
                        // Update the user
                        $user_info = $userhandler->update_user();
                        
                        if ($user_info) {
                            $success = true;
                            
                            // Log the action
                            $logMessage = sprintf(
                                "%s's account name has been changed to %s by %s (Change Username Tool)",
                                $oldUsername,
                                $newUsername,
                                $CURUSER['username'] ?? 'System'
                            );
                            write_log($logMessage);
                            
                            $message = 'Username successfully updated.';
                        } else {
                            $message = 'Failed to update username using UserDataHandler.';
                        }
                    }
                }
            } catch (Exception $e) {
                $message = 'System error: ' . $e->getMessage();
            }
        } else {
            // Need confirmation - get current username
            $escapedUserId = $db->sqlesc((int)$userId);
            $userQuery = $db->sql_query('SELECT id, username FROM users WHERE id = ' . $escapedUserId . ' LIMIT 1');
            
            if ($db->num_rows($userQuery) === 0) {
                $message = 'No user found with this ID.';
            } else {
                $user = mysqli_fetch_assoc($userQuery);
                
                // Check super admin permissions before showing confirmation
                if (is_super_admin((int)$userId) && $CURUSER['id'] != $userId && !is_super_admin($CURUSER['id'])) {
                    $message = 'You do not have permission to change a super administrator\'s username.';
                } else {
                    // Validate username format before confirmation
                    if (!preg_match('/^[a-zA-Z0-9]+$/', $newUsername)) {
                        $message = 'Invalid username format. Only letters and numbers are allowed.';
                    } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 25) {
                        $message = 'Username must be between 3 and 25 characters.';
                    } else {
                        // Check if username already exists (basic check before UserDataHandler)
                        $usernameCheck = $db->sql_query('SELECT id FROM users WHERE username = ' . $db->sqlesc($newUsername) . ' LIMIT 1');
                        if ($db->num_rows($usernameCheck) > 0) {
                            $message = 'This username is already taken.';
                        } else {
                            $confirmationNeeded = true;
                            $currentUsername = $user['username'];
                            $oldUsername = $user['username'];
                        }
                    }
                }
            }
        }
    }
}

// Start output
stdhead('Change Username');

echo '<div class="username-change-container">';
echo '<div class="main-card">';
echo '<div class="card-header bg-primary text-white">';
echo '<h1><i class="fas fa-user-edit"></i> Change Username</h1>';
echo '<div class="version">' . CU_VERSION . '</div>';
echo '</div>';
echo '<div class="card-body">';

if ($formSubmitted) {
    if ($success) {
        // Success message
        $profileLink = $BASEURL.'/'.get_profile_link($userId).'';
        ?>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-content">
                <h3>✓ Username Changed Successfully!</h3>
                <div class="success-details">
                    <div class="detail-item">
                        <span class="detail-label">User ID:</span>
                        <span class="detail-value"><?= htmlspecialchars($userId) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Old Username:</span>
                        <span class="detail-value"><?= htmlspecialchars($oldUsername) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">New Username:</span>
                        <span class="detail-value success-value"><?= htmlspecialchars($newUsername) ?></span>
                    </div>
                </div>
                <div class="success-note">
                    <i class="fas fa-info-circle"></i> This change has been logged in the system.
                </div>
                <div class="success-actions">
                    <a href="<?= $_this_script_ ?>" class="btn-secondary">
                        <i class="fas fa-redo"></i> Change Another Username
                    </a>
                    <a href="<?= $profileLink ?>" class="btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View User Profile
                    </a>
                </div>
            </div>
        </div>
        <?php
    } elseif ($confirmationNeeded) {
        // Check if user is super admin
        $isSuperAdminUser = is_super_admin((int)$userId);
        
        // Confirmation required
        ?>
        <div class="confirmation-container">
            <div class="confirmation-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="confirmation-content">
                <h3>⚠️ Confirm Username Change</h3>
                <p class="confirmation-warning">
                    <i class="fas fa-warning"></i> You are about to change the username for user ID 
                    <strong><?= htmlspecialchars($userId) ?></strong> from 
                    "<strong><?= htmlspecialchars($currentUsername) ?></strong>" to 
                    "<strong><?= htmlspecialchars($newUsername) ?></strong>".
                </p>
                
                <?php if ($isSuperAdminUser): ?>
                <div class="super-admin-warning">
                    <div class="super-admin-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="super-admin-text">
                        <strong>⚠️ Super Administrator Account</strong>
                        <p>This user is a Super Administrator. Changing their username may affect system permissions.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="confirmation-details">
                    <p><i class="fas fa-info-circle"></i> Please review this change carefully:</p>
                    <ul class="confirmation-list">
                        <li>User will need to use the new username to log in</li>
                        <li>All references to the old username will be updated</li>
                        <li>This action cannot be undone</li>
                        <li>The change will be logged for security purposes</li>
                        <li><strong>Advanced Validation:</strong> Username will be validated using MyBB's UserDataHandler</li>
                        <?php if ($isSuperAdminUser): ?>
                        <li><strong>Special Note:</strong> Super Admin accounts have elevated permissions</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <form method="post" action="<?= $_this_script_ ?>" class="confirmation-form">
                    <input type="hidden" name="act" value="changeusername">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($userId) ?>">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($newUsername) ?>">
                    <input type="hidden" name="oldusername" value="<?= htmlspecialchars($currentUsername) ?>">
                    <input type="hidden" name="sure" value="yes">
                    
                    <div class="confirmation-checkbox">
                        <label>
                            <input type="checkbox" name="confirm" value="1" required>
                            <span class="checkbox-label">
                                I understand this action is permanent and cannot be undone
                            </span>
                        </label>
                    </div>
                    
                    <div class="confirmation-actions">
                        <button type="submit" class="confirm-button">
                            <i class="fas fa-check"></i> Yes, Change Username
                        </button>
                        <a href="<?= $_this_script_ ?>" class="cancel-button">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    } else {
        // Error message
        ?>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="error-content">
                <h3>Error</h3>
                <p><?= htmlspecialchars($message) ?></p>
                
                <?php if (!empty($validationErrors)): ?>
                <div class="validation-errors">
                    <h4>Validation Errors:</h4>
                    <ul>
                        <?php foreach ($validationErrors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        // Show form again with saved values
        showUsernameChangeForm($userId, $newUsername);
    }
} else {
    // Show initial form
    showUsernameChangeForm();
}

echo '</div>';
echo '<div class="card-footer">';
echo '<div class="footer-note">';
echo '<i class="fas fa-history"></i> All username changes are logged for security purposes.';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Add CSS and JavaScript
addUsernameChangeStyles();
addUsernameChangeJavaScript();

stdfoot();

// Helper functions
function showUsernameChangeForm(string $userId = '', string $username = ''): void
{
    $scriptUrl = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '');
    $userIdValue = htmlspecialchars($userId);
    $usernameValue = htmlspecialchars($username);
    ?>
    <div class="username-change-form">
        <div class="form-header">
            <h2><i class="fas fa-user-pen"></i> Change Username</h2>
            <p class="form-description">
                Update a user's username using MyBB's UserDataHandler for advanced validation.
            </p>
            <div class="features-list">
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Advanced validation using MyBB UserDataHandler</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Full MyBB permission checks</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Super Admin protection</span>
                </div>
            </div>
        </div>
        
        <form method="post" action="<?= $scriptUrl ?>" class="change-username-form" id="username-change-form">
            <input type="hidden" name="act" value="changeusername">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="user-id">
                        <i class="fas fa-id-card"></i> User ID
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="user-id" 
                           name="id" 
                           value="<?= $userIdValue ?>"
                           class="form-control"
                           placeholder="Enter user ID"
                           required
                           pattern="[0-9]+"
                           title="Enter a valid user ID (numbers only)"
                           autocomplete="off"
                           autofocus>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i> Enter the numeric user ID
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> New Username
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?= $usernameValue ?>"
                           class="form-control"
                           placeholder="Enter new username"
                           required
                           minlength="3"
                           maxlength="25"
                           autocomplete="off">
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i> 3-25 characters (MyBB will validate further)
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit-button">
                    <i class="fas fa-user-check"></i> Change Username
                </button>
                <button type="reset" class="reset-button">
                    <i class="fas fa-undo"></i> Clear Form
                </button>
            </div>
            
            <div class="form-note">
                <i class="fas fa-shield-alt"></i> This tool uses MyBB's UserDataHandler for maximum security and validation.
            </div>
        </form>
    </div>
    <?php
}

function addUsernameChangeStyles(): void
{
    ?>
    <style>
        .username-change-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h1 {
            margin: 0;
            font-size: 2rem;
            display: flex;
                align-items: center;
            gap: 12px;
        }
        
        .version {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .card-footer {
            background: #f9fafb;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-note {
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .username-change-form {
            padding: 1rem 0;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h2 {
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .form-description {
            color: #6b7280;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 1.5rem;
        }
        
        .features-list {
            background: #f0f9ff;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e0f2fe;
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.75rem;
            color: #0369a1;
        }
        
        .feature-item:last-child {
            margin-bottom: 0;
        }
        
        .feature-item i {
            color: #0ea5e9;
            flex-shrink: 0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .required {
            color: #ef4444;
            margin-left: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .form-control.invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .form-control.valid {
            border-color: #8b5cf6;
            background-color: #f5f3ff;
        }
        
        .form-control::placeholder {
            color: #9ca3af;
        }
        
        .form-hint {
            color: #9ca3af;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .submit-button {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 200px;
            justify-content: center;
        }
        
        .submit-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }
        
        .submit-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .reset-button {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 150px;
            justify-content: center;
        }
        
        .reset-button:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .form-note {
            background: #f5f3ff;
            color: #5b21b6;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #8b5cf6;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        
        .success-container {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .success-icon {
            color: #10b981;
            font-size: 4rem;
            text-align: center;
            margin-bottom: 1.5rem;
            animation: successPulse 2s infinite;
        }
        
        @keyframes successPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .success-content h3 {
            margin: 0 0 1.5rem 0;
            color: #065f46;
            font-size: 1.8rem;
            text-align: center;
        }
        
        .success-details {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 120px;
        }
        
        .detail-value {
            color: #6b7280;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            text-align: right;
        }
        
        .success-value {
            color: #10b981;
            font-weight: 600;
        }
        
        .success-note {
            background: #ecfdf5;
            color: #047857;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        
        .success-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-primary {
            background: #8b5cf6;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s, transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-primary:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-2px);
        }
        
        .confirmation-container {
            background: #fffbeb;
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .confirmation-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }
        
        .super-admin-warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .super-admin-icon {
            color: #f59e0b;
            font-size: 2.5rem;
            flex-shrink: 0;
        }
        
        .super-admin-text {
            flex: 1;
        }
        
        .super-admin-text strong {
            color: #92400e;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .super-admin-text p {
            color: #92400e;
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .confirmation-icon {
            color: #f59e0b;
            font-size: 4rem;
            text-align: center;
            margin-bottom: 1.5rem;
            animation: warningPulse 2s infinite;
        }
        
        @keyframes warningPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .confirmation-content h3 {
            margin: 0 0 1.5rem 0;
            color: #92400e;
            font-size: 1.8rem;
            text-align: center;
        }
        
        .confirmation-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-left: 4px solid #f59e0b;
        }
        
        .confirmation-warning i {
            margin-top: 3px;
            flex-shrink: 0;
        }
        
        .confirmation-details {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f3f4f6;
        }
        
        .confirmation-details p {
            margin: 0 0 1rem 0;
            color: #374151;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .confirmation-list {
            margin: 0;
            padding-left: 1.5rem;
            color: #6b7280;
        }
        
        .confirmation-list li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        
        .confirmation-list li:last-child {
            margin-bottom: 0;
        }
        
        .confirmation-checkbox {
            background: #fef3c7;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #fde68a;
        }
        
        .confirmation-checkbox label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            color: #92400e;
            font-weight: 500;
        }
        
        .confirmation-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #f59e0b;
            flex-shrink: 0;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .confirm-button {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 200px;
            justify-content: center;
        }
        
        .confirm-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
        }
        
        .confirm-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .cancel-button {
            background: white;
            color: #374151;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
            min-width: 150px;
            justify-content: center;
        }
        
        .cancel-button:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-2px);
        }
        
        .error-container {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        
        .error-icon {
            color: #ef4444;
            font-size: 2.5rem;
            flex-shrink: 0;
        }
        
        .error-content h3 {
            margin: 0 0 0.75rem 0;
            color: #dc2626;
            font-size: 1.5rem;
        }
        
        .error-content p {
            margin: 0;
            color: #7f1d1d;
            font-size: 1.1rem;
            line-height: 1.5;
        }
        
        .validation-errors {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .validation-errors h4 {
            margin: 0 0 0.5rem 0;
            color: #b91c1c;
            font-size: 1rem;
        }
        
        .validation-errors ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #7f1d1d;
        }
        
        .validation-errors li {
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .version {
                align-self: flex-start;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .submit-button,
            .reset-button,
            .confirm-button,
            .cancel-button {
                width: 100%;
            }
            
            .success-container,
            .confirmation-container {
                padding: 1.5rem;
            }
            
            .success-details,
            .confirmation-details {
                padding: 1rem;
            }
            
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .detail-value {
                text-align: left;
                width: 100%;
            }
            
            .success-actions,
            .confirmation-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
            
            .super-admin-warning {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }
            
            .features-list {
                padding: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .card-body {
                padding: 1rem;
            }
            
            .form-grid {
                gap: 1.5rem;
            }
            
            .form-control {
                padding: 12px 16px;
            }
            
            .success-container,
            .error-container,
            .confirmation-container {
                padding: 1.25rem;
            }
            
            .success-icon,
            .confirmation-icon {
                font-size: 3rem;
            }
            
            .success-content h3,
            .confirmation-content h3 {
                font-size: 1.5rem;
            }
        }
    </style>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
}

function addUsernameChangeJavaScript(): void
{
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('username-change-form');
            const userIdInput = document.getElementById('user-id');
            const usernameInput = document.getElementById('username');
            const submitButton = form?.querySelector('.submit-button');
            
            if (!form) return;
            
            // Real-time user ID validation
            userIdInput.addEventListener('input', function() {
                const userId = this.value.trim();
                const isValid = /^[0-9]+$/.test(userId);
                
                if (userId && !isValid) {
                    this.setCustomValidity('User ID can only contain numbers.');
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                } else if (userId) {
                    this.setCustomValidity('');
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('invalid', 'valid');
                }
            });
            
            // Real-time username validation (basic)
            usernameInput.addEventListener('input', function() {
                const username = this.value.trim();
                const isValid = username.length >= 3 && username.length <= 25;
                
                if (username && !isValid) {
                    this.setCustomValidity('Username must be 3-25 characters.');
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                } else if (username) {
                    this.setCustomValidity('');
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('invalid', 'valid');
                }
            });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                // Clear any previous custom validity
                userIdInput.setCustomValidity('');
                usernameInput.setCustomValidity('');
                
                // Validate user ID
                const userId = userIdInput.value.trim();
                if (!userId) {
                    userIdInput.setCustomValidity('User ID is required.');
                    userIdInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (!/^[0-9]+$/.test(userId)) {
                    userIdInput.setCustomValidity('User ID can only contain numbers.');
                    userIdInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                // Validate username
                const username = usernameInput.value.trim();
                if (!username) {
                    usernameInput.setCustomValidity('Username is required.');
                    usernameInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (username.length < 3 || username.length > 25) {
                    usernameInput.setCustomValidity('Username must be between 3 and 25 characters.');
                    usernameInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                if (submitButton) {
                    const originalHtml = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
                    submitButton.disabled = true;
                    
                    // Restore button state after 5 seconds (in case of error)
                    setTimeout(() => {
                        submitButton.innerHTML = originalHtml;
                        submitButton.disabled = false;
                    }, 5000);
                }
                
                return true;
            });
            
            // Reset button
            const resetButton = form.querySelector('.reset-button');
            if (resetButton) {
                resetButton.addEventListener('click', function() {
                    userIdInput.setCustomValidity('');
                    usernameInput.setCustomValidity('');
                    userIdInput.classList.remove('invalid', 'valid');
                    usernameInput.classList.remove('invalid', 'valid');
                });
            }
            
            // Auto-focus user ID field
            if (!userIdInput.value) {
                userIdInput.focus();
            }
        });
    </script>
    <?php
}