<?php

declare(strict_types=1);


if (!defined('STAFF_PANEL')) {
    exit('<div class="error-message">❌ Error! Direct initialization of this file is not allowed.</div>');
}

define('CE_VERSION', '0.5');

// Check if form was submitted
$formSubmitted = false;
$success = false;
$message = '';
$username = '';
$email = '';
$oldEmail = '';
$newEmail = '';
$userId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'changemail') {
    $formSubmitted = true;
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validate inputs
    if (empty($username) || empty($email)) {
        $message = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $message = 'Invalid username format. Only letters and numbers are allowed.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address format.';
    } else {
        // Sanitize email
        $cleanEmail = str_replace(['<', '>', '\'', '"', '\\'], '', $email);
        
        // Check if email exists
        $emailCheck = $db->sql_query('SELECT email FROM users WHERE email = ' . $db->sqlesc($cleanEmail) . ' LIMIT 1');
        if ($db->num_rows($emailCheck) > 0) {
            $message = 'This email address is already taken.';
        } else {
            // Check if user exists and get old email
            $userQuery = $db->sql_query('SELECT id, email FROM users WHERE username = ' . $db->sqlesc($username) . ' LIMIT 1');
            if ($db->num_rows($userQuery) === 0) {
                $message = 'User not found.';
            } else {
                $user = mysqli_fetch_assoc($userQuery);
                $userId = (int)$user['id'];
                $oldEmail = $user['email'];
                
                // Update email
                $updateData = ['email' => $cleanEmail];
                $whereCondition = "username = " . $db->sqlesc($username);
                
                $updateResult = $db->update_query("users", $updateData, $whereCondition);
                
                if ($updateResult) {
                    $success = true;
                    $newEmail = $cleanEmail;
                    
                    // Log the action
                    $logMessage = sprintf(
                        "%s's email has been changed from %s to %s by %s (Change Email Tool)",
                        $username,
                        $oldEmail,
                        $cleanEmail,
                        $CURUSER['username'] ?? 'System'
                    );
                    write_log($logMessage);
                    
                    $message = 'Email successfully updated.';
                } else {
                    $message = 'Database error: Unable to update email.';
                }
            }
        }
    }
}

// Start output
stdhead('Change User Email Address');

echo '<div class="email-change-container">';
echo '<div class="main-card">';
echo '<div class="card-header">';
echo '<h1><i class="fas fa-envelope-circle-check"></i> Change User Email</h1>';
echo '<div class="version">' . CE_VERSION . '</div>';
echo '</div>';
echo '<div class="card-body">';

if ($formSubmitted) {
    if ($success) {
        // Success message
        //$profileLink = $BASEURL . '/userdetails.php?id=' . $userId;
		
		 $profileLink = $BASEURL.'/'.get_profile_link($userId).'';
		
		
        ?>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="success-content">
                <h3>✓ Email Changed Successfully!</h3>
                <div class="success-details">
                    <div class="detail-item">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Old Email:</span>
                        <span class="detail-value"><?= htmlspecialchars($oldEmail) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">New Email:</span>
                        <span class="detail-value success-value"><?= htmlspecialchars($newEmail) ?></span>
                    </div>
                </div>
                <div class="success-note">
                    <i class="fas fa-info-circle"></i> This change has been logged in the system.
                </div>
                <div class="success-actions">
                    <a href="<?= $_this_script_ ?>" class="btn-secondary">
                        <i class="fas fa-redo"></i> Change Another Email
                    </a>
                    <a href="<?= $profileLink ?>" class="btn-primary" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View User Profile
                    </a>
                </div>
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
            </div>
        </div>
        <?php
        // Show form again with saved values
        showEmailChangeForm($username, $email);
    }
} else {
    // Show initial form
    showEmailChangeForm();
}

echo '</div>';
echo '<div class="card-footer">';
echo '<div class="footer-note">';
echo '<i class="fas fa-history"></i> All changes are logged for security purposes.';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Add CSS and JavaScript
addEmailChangeStyles();
addEmailChangeJavaScript();

stdfoot();

// Helper functions
function showEmailChangeForm(string $username = '', string $email = ''): void
{
    global $_this_script_;
	
	$scriptUrl = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '');
    $usernameValue = htmlspecialchars($username);
    $emailValue = htmlspecialchars($email);
    ?>
    <div class="email-change-form">
        <div class="form-header">
            <h2><i class="fas fa-envelope"></i> Change User Email Address</h2>
            <p class="form-description">
                Update a user's email address. Enter username and new email below.
            </p>
        </div>
        
        <form method="post" action="<?= $_this_script_ ?>" class="change-email-form" id="email-change-form">
            <input type="hidden" name="act" value="changemail">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?= $usernameValue ?>"
                           class="form-control"
                           placeholder="Enter username"
                           required
                           pattern="[a-zA-Z0-9]+"
                           title="Only letters and numbers allowed"
                           autocomplete="off"
                           autofocus>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i> Only alphanumeric characters allowed
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-at"></i> New Email Address
                        <span class="required">*</span>
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= $emailValue ?>"
                           class="form-control"
                           placeholder="user@example.com"
                           required
                           autocomplete="off">
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i> Enter a valid email address
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="submit-button">
                    <i class="fas fa-paper-plane"></i> Change Email Address
                </button>
                <button type="reset" class="reset-button">
                    <i class="fas fa-undo"></i> Clear Form
                </button>
            </div>
            
            <div class="form-note">
                <i class="fas fa-shield-alt"></i> This action will be logged in the system logs.
            </div>
        </form>
    </div>
    <?php
}

function addEmailChangeStyles(): void
{
    ?>
    <style>
        .email-change-container {
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        
        .email-change-form {
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
            margin: 0 auto;
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
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-control.invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .form-control.valid {
            border-color: #10b981;
            background-color: #f0fdf4;
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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
            background: #f0f9ff;
            color: #0369a1;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #0ea5e9;
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
            background: #10b981;
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
            background: #059669;
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
            .reset-button {
                width: 100%;
            }
            
            .success-container {
                padding: 1.5rem;
            }
            
            .success-details {
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
            
            .success-actions {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
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
            .error-container {
                padding: 1.25rem;
            }
            
            .success-icon {
                font-size: 3rem;
            }
            
            .success-content h3 {
                font-size: 1.5rem;
            }
        }
    </style>
    
   
    <?php
}

function addEmailChangeJavaScript(): void
{
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('email-change-form');
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const submitButton = form?.querySelector('.submit-button');
            
            if (!form) return;
            
            // Real-time username validation
            usernameInput.addEventListener('input', function() {
                const username = this.value.trim();
                const isValid = /^[a-zA-Z0-9]+$/.test(username);
                
                if (username && !isValid) {
                    this.setCustomValidity('Username can only contain letters and numbers.');
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
            
            // Real-time email validation
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                
                if (email && !isValid) {
                    this.setCustomValidity('Please enter a valid email address.');
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                } else if (email) {
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
                usernameInput.setCustomValidity('');
                emailInput.setCustomValidity('');
                
                // Validate username
                const username = usernameInput.value.trim();
                if (!username) {
                    usernameInput.setCustomValidity('Username is required.');
                    usernameInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (!/^[a-zA-Z0-9]+$/.test(username)) {
                    usernameInput.setCustomValidity('Username can only contain letters and numbers.');
                    usernameInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                // Validate email
                const email = emailInput.value.trim();
                if (!email) {
                    emailInput.setCustomValidity('Email is required.');
                    emailInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    emailInput.setCustomValidity('Please enter a valid email address.');
                    emailInput.reportValidity();
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                if (submitButton) {
                    const originalHtml = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
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
                    usernameInput.setCustomValidity('');
                    emailInput.setCustomValidity('');
                    usernameInput.classList.remove('invalid', 'valid');
                    emailInput.classList.remove('invalid', 'valid');
                });
            }
            
            // Auto-focus username field
            if (!usernameInput.value) {
                usernameInput.focus();
            }
        });
    </script>
    <?php
}