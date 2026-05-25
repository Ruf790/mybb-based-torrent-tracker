<?php
declare(strict_types=1);



// Access check
if (!defined('STAFF_PANEL_TSSEv56')) {
    http_response_code(403);
    exit('<div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong> Direct initialization of this file is not allowed.
    </div>');
}

// Define version
define('PS_VERSION', 'v0.1 by xam');

// Initialize
$do = $_POST['do'] ?? $_GET['do'] ?? 0;
$do = htmlspecialchars((string)$do);
$passkey = '';
$error = null;
$title = 'Search Results: ';

// Page header
stdhead('Passkey Search');


echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';

// Handle actions
if ($do == '2') {
    handlePasskeyReset();
} elseif ($do == '1') {
    handlePasskeySearch();
}

// Display results or errors
if (!empty($error)) {
    displayError($error);
}

// Display search form
displaySearchForm();

stdfoot();

/**
 * Handle passkey reset
 */
function handlePasskeyReset(): void
{
    global $db, $error, $title, $passkey;
    
    $title = 'Passkey Reset: ';
    $passkey = trim(strtolower($_GET['passkey'] ?? ''));
    
    if (empty($passkey)) {
        $error = 'Please enter passkey!';
        return;
    }
    
    if (strlen($passkey) != 32) {
        $error = 'Invalid Passkey!';
        return;
    }
    
    $db->sql_query('UPDATE users SET passkey = \'\' WHERE passkey = ' . $db->sqlesc($passkey));
    $affected = $db->affected_rows();
    
    if ($affected > 0) {
        $error = '<i class="fas fa-check-circle text-success"></i> Passkey has been successfully reset for the user!';
    } else {
        $error = '<i class="fas fa-exclamation-circle text-warning"></i> No user found with this passkey.';
    }
}

/**
 * Handle passkey search
 */
function handlePasskeySearch(): void
{
    global $db, $error, $title, $passkey, $BASEURL, $_this_script_;
    
    $title = 'Passkey Search: ';
    $passkey = trim(strtolower($_POST['passkey'] ?? ''));
    
    if (empty($passkey)) {
        $error = 'Please enter passkey!';
        return;
    }
    
    if (strlen($passkey) != 32) {
        $error = 'Invalid Passkey! Passkey must be exactly 32 characters.';
        return;
    }
    
    $query = $db->sql_query('SELECT u.*, g.namestyle FROM users u 
                            LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
                            WHERE u.passkey = ' . $db->sqlesc($passkey));
    
    if ($db->num_rows($query) == 0) {
        $error = '<i class="fas fa-user-slash text-warning"></i> No registered user found with this passkey!';
        return;
    }
    
    $user = $db->fetch_array($query);
    displayUserDetails($user);
}

/**
 * Display user details
 */
function displayUserDetails(array $user): void
{
    global $passkey, $BASEURL, $_this_script_;
    
    include_once INC_PATH . '/functions_ratio.php';
    
    // Format dates
    $lastseen = formatDateTime($user['lastactive'] ?? '0000-00-00 00:00:00');
    $joindate = formatDateTime($user['added'] ?? '0000-00-00 00:00:00');
    $ratio = get_user_ratio((float)($user['uploaded'] ?? 0), (float)($user['downloaded'] ?? 0));
    
    // Format avatar
    $avatar_html = '<i class="fas fa-user fa-4x text-primary bg-light p-4 rounded-circle"></i>';
    if (isset($user['avatar']) && function_exists('format_avatar')) {
        $useravatar = format_avatar($user['avatar'], $user['avatardimensions'] ?? '');
        
        if (!empty($useravatar['image'])) {
            if (str_starts_with($useravatar['image'], '<')) {
                $avatar_html = $useravatar['image'];
            } else {
                $avatar_html = '<img class="rounded-circle img-fluid border border-3 border-primary" src="' . 
                               htmlspecialchars($useravatar['image']) . '" alt="User Avatar" ' . 
                               ($useravatar['width_height'] ?? 'width="128" height="128"') . ' />';
            }
        }
    }
    
    // Get member's permissions
    $memperms = [];
    if (function_exists('user_permissions')) {
        $perms = user_permissions((int)($user['id'] ?? 0));
        if (is_array($perms)) {
            $memperms = $perms;
        }
    }
    
    // Set display group
    $displaygroupfields = ["title", "description", "namestyle", "usertitle", "stars", "starimage", "image"];
    
    if (empty($user['displaygroup'])) {
        $user['displaygroup'] = $user['usergroup'] ?? 0;
    }
    
    if (function_exists('usergroup_displaygroup')) {
        $display_group = usergroup_displaygroup((int)($user['displaygroup']));
        
        if (is_array($display_group)) {
            $memperms = array_merge($memperms, $display_group);
        }
    }
    
    // Safely get usertitle
    $usertitle = $memperms['image'] ?? '';
    $username_formatted = htmlspecialchars($user['username'] ?? '');
    
    if (function_exists('format_name')) {
        $username_formatted = format_name($user['username'] ?? '', $user['usergroup'] ?? 0);
    }
    
    // Get profile link
    $profile_link = '#';
    if (function_exists('get_profile_link')) {
        $profile_link = get_profile_link($user['id'] ?? 0);
    }
    
    echo '
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 bg-primary text-white shadow-lg">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-1">
                                    <i class="fas fa-key me-2"></i>Passkey Search Results
                                </h4>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-fingerprint me-1"></i>
                                    <code class="text-light bg-primary bg-opacity-25 px-2 py-1 rounded">' . 
                                    htmlspecialchars($passkey) . '</code>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-primary fs-6 shadow-sm">
                                    <i class="fas fa-check-circle me-1"></i> User Found
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Info Card -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg h-100">
                    <div class="card-header bg-white border-0 py-4">
                        <h5 class="mb-0 text-center">
                            <i class="fas fa-user-circle me-2 text-primary"></i>User Profile
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="user-avatar mb-3 position-relative d-inline-block">
                                <div class="avatar-wrapper">
                                    ' . $avatar_html . '
                                </div>
                                <div class="online-status position-absolute bottom-0 end-0">
                                    ' . (($lastseen['full'] !== 'Never' && time() - strtotime($user['lastactive'] ?? '') < 300) ? 
                                    '<span class="badge bg-success p-1"><i class="fas fa-circle"></i> Online</span>' : 
                                    '<span class="badge bg-secondary p-1"><i class="fas fa-circle"></i> Offline</span>') . '
                                </div>
                            </div>
                            <h4 class="mb-2 fw-bold">' . $username_formatted . '</h4>
                            <div class="mb-3">
                                ' . (!empty($usertitle) ? '<div class="badge bg-primary bg-opacity-10 text-primary">' . $usertitle . '</div>' : '') . '
                            </div>
                            <a href="' . htmlspecialchars($BASEURL) . '/' . $profile_link . '" 
                               class="btn btn-primary btn-sm shadow-sm">
                               <i class="fas fa-external-link-alt me-1"></i> View Full Profile
                            </a>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted"><i class="fas fa-envelope me-2"></i>Email</span>
                                <span class="fw-medium text-truncate" style="max-width: 180px;" title="' . 
                                htmlspecialchars($user['email'] ?? '') . '">
                                    ' . htmlspecialchars($user['email'] ?? '') . '
                                </span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted"><i class="fas fa-network-wired me-2"></i>IP Address</span>
                                <span class="fw-medium">
                                    <code class="bg-light px-2 py-1 rounded">' . htmlspecialchars($user['ipaddress'] ?? '') . '</code>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <!-- Statistics -->
          
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg h-100">
                    <div class="card-header bg-white border-0 py-4">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2 text-success"></i>User Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card border-0 bg-opacity-10 h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-success mb-3">
                                            <i class="fas fa-upload fa-3x"></i>
                                        </div>
                                        <h2 class="mb-2 fw-bold">' . mksize($user['uploaded'] ?? 0) . '</h2>
                                        <p class="text-muted mb-0">Total Uploaded</p>
                                        <div class="mt-3">
                                            <small class="text-success">
                                                <i class="fas fa-arrow-up me-1"></i>
                                                ' . mksize($user['uploaded'] ?? 0) . '
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 bg-opacity-10 h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-danger mb-3">
                                            <i class="fas fa-download fa-3x"></i>
                                        </div>
                                        <h2 class="mb-2 fw-bold">' . mksize($user['downloaded'] ?? 0) . '</h2>
                                        <p class="text-muted mb-0">Total Downloaded</p>
                                        <div class="mt-3">
                                            <small class="text-danger">
                                                <i class="fas fa-arrow-down me-1"></i>
                                                ' . mksize($user['downloaded'] ?? 0) . '
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ratio Card -->
                        
						 <div class="row mt-4">
                            <div class="col-12">
                            <div class="col-12">
                                <div class="card border-light">
                                    <div class="card-body text-center">
                                        <div class="mb-2">
                                            <i class="fas fa-balance-scale fa-2x ' . getRatioColorClass($ratio) . '"></i>
                                        </div>
                                        <h2 class="mb-1 ' . getRatioColorClass($ratio) . '">' . $ratio . '</h2>
                                        <p class="text-muted mb-0">Current Ratio</p>
                                    </div>
                                </div>
                            </div>
                        </div>
						</div>
						
						
						
                    </div>
                </div>
            </div>
        </div>
			
			
        
        
        <!-- Details Table -->
        <div class="card border-0 shadow-lg">
            <div class="card-header bg-white border-0 py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2 text-info"></i>Detailed Information
                    </h5>
                    <div class="btn-group">
                        <a href="' . htmlspecialchars($_this_script_) . '&do=2&passkey=' . htmlspecialchars($passkey) . '" 
                           class="btn btn-outline-danger btn-sm"
                           onclick="return confirm(\'⚠️ Are you sure you want to reset this passkey?\\\\n\\\\nThis will:\\\\n• Invalidate current passkey\\\\n• Require user to generate new one\\\\n• Disrupt active downloads\')">
                           <i class="fas fa-key me-1"></i> Reset Passkey
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyToClipboard(\'' . 
                        htmlspecialchars($passkey) . '\')">
                            <i class="fas fa-copy me-1"></i> Copy Passkey
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-calendar-plus me-1"></i> Registration</th>
                                <th><i class="fas fa-clock me-1"></i> Last Activity</th>
                                <th><i class="fas fa-key me-1"></i> Passkey Status</th>
                                <th><i class="fas fa-users me-1"></i> User Group</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 text-primary bg-primary bg-opacity-10 p-2 rounded">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium">' . $joindate['date'] . '</div>
                                            <small class="text-muted d-block">' . $joindate['time'] . '</small>
                                            <small class="text-muted">' . getTimeAgo($user['added'] ?? '') . '</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 text-warning bg-warning bg-opacity-10 p-2 rounded">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium">' . $lastseen['date'] . '</div>
                                            <small class="text-muted d-block">' . $lastseen['time'] . '</small>
                                            <small class="text-muted">' . getTimeAgo($user['lastactive'] ?? '') . '</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 text-success bg-success bg-opacity-10 p-2 rounded">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium">Active & Valid</div>
                                            <small class="text-muted d-block">
                                                <code class="bg-light p-1 rounded font-monospace">' . 
                                                substr(htmlspecialchars($passkey), 0, 8) . '...' . substr(htmlspecialchars($passkey), -8) . 
                                                '</code>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                       
                                        <div>
                                            <div class="fw-medium">' . htmlspecialchars($memperms['title'] ?? 'Member') . '</div>
                                            ' . (!empty($usertitle) ? 
                                            '<small class="text-muted d-block">' . $usertitle . '</small>' : '') . '
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Passkey Security Notice -->
    <div class="container mt-4">
        <div class="alert alert-warning border-warning">
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h5 class="alert-heading">⚠️ Security Notice</h5>
                    <p class="mb-2">This passkey is actively linked to a user account. Resetting it will:</p>
                    <ul class="mb-2">
                        <li>Invalidate all current torrent downloads using this passkey</li>
                        <li>Require the user to generate a new passkey from their profile</li>
                        <li>Disrupt any active seeding/leeching sessions</li>
                    </ul>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Passkey Format:</strong></p>
                            <code class="d-block bg-light p-2 rounded font-monospace">32-character hexadecimal (a-f, 0-9)</code>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Passkey Preview:</strong></p>
                            <code class="d-block bg-light p-2 rounded font-monospace">
                                ' . chunk_split(htmlspecialchars($passkey), 8, ' ') . '
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            const alert = document.createElement("div");
            alert.className = "alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3";
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                <strong>Passkey copied to clipboard!</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                alert.remove();
            }, 3000);
        }).catch(function(err) {
            alert("Failed to copy: " + err);
        });
    }
    
    // Add animation to ratio bar
    document.addEventListener("DOMContentLoaded", function() {
        const ratioBar = document.querySelector(".progress-bar");
        if (ratioBar) {
            setTimeout(() => {
                ratioBar.style.transition = "width 1.5s ease-in-out";
            }, 500);
        }
    });
    </script>
    
    <style>
    .user-avatar .avatar-wrapper {
        position: relative;
        display: inline-block;
    }
    .user-avatar img {
        border: 3px solid #4361ee;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        transition: transform 0.3s ease;
    }
    .user-avatar img:hover {
        transform: scale(1.05);
    }
    .online-status .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border: 2px solid white;
    }
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
    }
    .bg-gradient-success {
        background: linear-gradient(135deg, #4ade80 0%, #16a34a 100%);
    }
    .bg-gradient-danger {
        background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
    }
    .card {
        border-radius: 15px;
        overflow: hidden;
    }
    .list-group-item {
        border-left: none;
        border-right: none;
        transition: background-color 0.2s;
    }
    .list-group-item:hover {
        background-color: rgba(67, 97, 238, 0.05);
    }
    .ratio-display {
        animation: float 3s ease-in-out infinite;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    </style>';
}

/**
 * Get ratio background class
 */
function getRatioBgClass(string $ratio): string
{
    $numeric = (float)str_replace(['∞', '---'], ['999', '0'], $ratio);
    
    if ($numeric >= 2.0) return 'bg-success bg-opacity-10';
    if ($numeric >= 1.0) return 'bg-primary bg-opacity-10';
    if ($numeric >= 0.5) return 'bg-warning bg-opacity-10';
    return 'bg-danger bg-opacity-10';
}

/**
 * Get ratio progress class
 */
function getRatioProgressClass(string $ratio): string
{
    $numeric = (float)str_replace(['∞', '---'], ['999', '0'], $ratio);
    
    if ($numeric >= 2.0) return 'bg-success';
    if ($numeric >= 1.0) return 'bg-primary';
    if ($numeric >= 0.5) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Get ratio percentage for progress bar
 */
function getRatioPercentage(string $ratio): float
{
    $numeric = (float)str_replace(['∞', '---'], ['999', '0'], $ratio);
    
    if ($numeric >= 2.0) return 100;
    if ($numeric >= 1.0) return 75;
    if ($numeric >= 0.5) return 50;
    if ($numeric > 0) return 25;
    return 0;
}

/**
 * Get time ago string
 */
function getTimeAgo(string $datetime): string
{
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return 'Never';
    }
    
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}




































/**
 * Format date and time
 */
function formatDateTime(string $datetime): array
{
    global $dateformat, $timeformat;
    
    if ($datetime == '0000-00-00 00:00:00' || empty($datetime)) {
        return [
            'date' => '<span class="text-muted">N/A</span>',
            'time' => '',
            'full' => '<span class="text-muted">Never</span>'
        ];
    }
    
    return [
        'date' => my_datee($dateformat, $datetime),
        'time' => my_datee($timeformat, $datetime),
        'full' => my_datee($dateformat, $datetime) . ' ' . my_datee($timeformat, $datetime)
    ];
}

/**
 * Get ratio color class
 */
function getRatioColorClass(string $ratio): string
{
    $numeric = (float)str_replace(['∞', '---'], ['999', '0'], $ratio);
    
    if ($numeric >= 2.0) return 'text-success';
    if ($numeric >= 1.0) return 'text-primary';
    if ($numeric >= 0.5) return 'text-warning';
    return 'text-danger';
}

/**
 * Display error message
 */
function displayError(string $error): void
{
    global $title, $passkey;
    
    $isError = str_contains($error, 'Error') || str_contains($error, 'Invalid') || str_contains($error, 'Please');
    $alertClass = $isError ? 'danger' : 'warning';
    $icon = $isError ? 'exclamation-circle' : 'exclamation-triangle';
    
    echo '
    <div class="container mt-4">
        <div class="card border-' . $alertClass . ' shadow-sm">
            <div class="card-header bg-' . $alertClass . ' text-white border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-' . $icon . ' me-2"></i>' . $title . '
                    </h5>
                    ' . (!empty($passkey) ? '
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-fingerprint me-1"></i>' . substr($passkey, 0, 8) . '...' . '
                    </span>' : '') . '
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-' . $alertClass . ' mb-0">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-' . $icon . ' fa-2x"></i>
                        </div>
                        <div>
                            ' . $error . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Display search form
 */
function displaySearchForm(): void
{
    global $passkey;
    
    echo '
    <div class="container mt-4">
        <!-- Search Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 bg-primary text-white shadow-lg">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-search fa-4x mb-4 opacity-50"></i>
                        <h1 class="display-6 mb-3">Passkey Search Tool</h1>
                        <p class="lead mb-0 opacity-75">
                            Search user accounts by their unique 32-character passkey
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg">
                    <div class="card-header bg-white border-0 py-4">
                        <h3 class="text-center mb-0">
                            <i class="fas fa-key me-2 text-primary"></i>Enter Passkey
                        </h3>
                    </div>
                    <div class="card-body p-5">
                        <form method="post" action="' . htmlspecialchars($_SERVER['SCRIPT_NAME']) . '" id="passkeyForm">
                            <input type="hidden" name="act" value="passkeysearch">
                            <input type="hidden" name="do" value="1">
                            
                            <div class="mb-4">
                                <label for="passkeyInput" class="form-label fw-bold">
                                    <i class="fas fa-fingerprint me-1"></i>Passkey (32 characters)
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-key text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           id="passkeyInput"
                                           name="passkey" 
                                           class="form-control border-start-0 form-control-lg" 
                                           placeholder="Enter 32-character passkey..."
                                           value="' . htmlspecialchars($passkey) . '"
                                           pattern="[a-f0-9]{32}"
                                           maxlength="32"
                                           required>
                                    <button class="btn btn-primary" type="button" id="generateSample">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Enter the exact 32-character hexadecimal passkey (a-f, 0-9)
                                </div>
                            </div>
                            
                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-search me-2"></i> Search Passkey
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearForm">
                                    <i class="fas fa-eraser me-2"></i> Clear Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Info Cards -->
        <div class="row mt-5">
            <div class="col-md-4 mb-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-primary mb-3">
                            <i class="fas fa-user-secret fa-3x"></i>
                        </div>
                        <h5 class="card-title">User Identification</h5>
                        <p class="card-text text-muted">
                            Each user has a unique 32-character passkey for secure torrent access
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-success mb-3">
                            <i class="fas fa-shield-alt fa-3x"></i>
                        </div>
                        <h5 class="card-title">Security</h5>
                        <p class="card-text text-muted">
                            Passkeys enhance security by providing unique identifiers for each user
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card border-0 h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-warning mb-3">
                            <i class="fas fa-exclamation-triangle fa-3x"></i>
                        </div>
                        <h5 class="card-title">Reset Caution</h5>
                        <p class="card-text text-muted">
                            Resetting a passkey will require the user to generate a new one
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const passkeyInput = document.getElementById("passkeyInput");
        const generateBtn = document.getElementById("generateSample");
        const clearBtn = document.getElementById("clearForm");
        const form = document.getElementById("passkeyForm");
        
        // Generate sample passkey
        if (generateBtn) {
            generateBtn.addEventListener("click", function() {
                const chars = "0123456789abcdef";
                let result = "";
                for (let i = 0; i < 32; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                passkeyInput.value = result;
                passkeyInput.focus();
                passkeyInput.select();
            });
        }
        
        // Clear form
        if (clearBtn) {
            clearBtn.addEventListener("click", function() {
                passkeyInput.value = "";
                passkeyInput.focus();
            });
        }
        
        // Validate passkey format
        if (form) {
            form.addEventListener("submit", function(e) {
                const value = passkeyInput.value.trim();
                if (value.length !== 32) {
                    e.preventDefault();
                    alert("Passkey must be exactly 32 characters!");
                    passkeyInput.focus();
                    return false;
                }
                if (!/^[a-f0-9]{32}$/i.test(value)) {
                    e.preventDefault();
                    alert("Passkey must contain only hexadecimal characters (0-9, a-f)!");
                    passkeyInput.focus();
                    return false;
                }
                return true;
            });
        }
        
        // Auto-format passkey
        if (passkeyInput) {
            passkeyInput.addEventListener("input", function() {
                this.value = this.value.toLowerCase().replace(/[^a-f0-9]/g, "").slice(0, 32);
            });
        }
    });
    </script>
    
    <style>
    .bg-gradient-dark {
        background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    }
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
    }
    .user-avatar {
        display: inline-block;
        position: relative;
    }
    .user-avatar::after {
        content: "";
        position: absolute;
        top: -5px;
        left: -5px;
        right: -5px;
        bottom: -5px;
        background: linear-gradient(45deg, #4361ee, #4cc9f0);
        border-radius: 50%;
        z-index: -1;
        opacity: 0.3;
    }
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
    }
    .table th {
        border-top: none;
        border-bottom: 2px solid #e9ecef;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem 0;
    }
    .list-group-item:first-child {
        border-top: none;
    }
    .list-group-item:last-child {
        border-bottom: none;
    }
    </style>';
}
?>