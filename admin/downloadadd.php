<?php
/*****************************************************************/
/*=============[ TS Special Edition v.5.6 ]=====================*/
/*=============[ Special Thanks To ]============================*/
/*        DrNet - wWw.SpecialCoders.CoM                         */
/*          Vinson - wWw.Decode4u.CoM                           */
/*    MrDecoder - wWw.Fearless-Releases.CoM                     */
/*           Fynnon - wWw.BvList.CoM                            */
/*****************************************************************/

/**
 * Generate GB amount dropdown selector
 */
function class_amount() {
    echo '
    <label class="form-label">Amount (GB)</label>
    <select name="classamount" class="form-select" style="width: 200px;">
        <option value="0" disabled selected>(select amount)</option>';
    
    for ($i = 1; $i < 51; $i++) {
        echo '<option value="' . $i . '">' . $i . ' GB</option>';
    }
    
    echo '</select>';
}

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

define('DA_VERSION', '0.2 by xam');

// End of line based on OS
if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
    $eol = "\r\n";
} elseif (strtoupper(substr(PHP_OS, 0, 3)) == 'MAC') {
    $eol = "\r";
} else {
    $eol = "\n";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class = (int)($_POST['usergroup'] ?? 0);
    $class = ($class <= 0 || !is_valid_id($class)) ? '' : $class;
    
    $query = "enabled='yes' AND ustatus='confirmed'" . ($class ? " AND usergroup=$class" : '');

    // Process bulk action (send to group)
    if (($_POST['doit'] ?? '') == 'yes') {
        $amount = (int)($_POST['classamount'] ?? 0);
        
        if ($amount < 1 || $amount > 50) {
            stderr('Error', 'Please select a valid amount between 1-50 GB!');
        }

        $modcomment = gmdate('Y-m-d') . " - Got " . mksize($amount * 1024 * 1024 * 1024) . 
                     " Download Amount from {$CURUSER['username']} (Download Add Tool)$eol";
        
        $dlamount = $amount * 1024 * 1024 * 1024;
        
       
        $sql = "UPDATE users 
                SET downloaded = downloaded + " . (int)$dlamount . ", 
                    modcomment = CONCAT(" . $db->sqlesc($modcomment) . ", modcomment) 
                WHERE $query";
        
        $db->sql_query($sql);

        write_log("$amount GB download amount sent to " . ($class ? "usergroup: $class" : "all users") . 
                 " by {$CURUSER['username']} (Download Add Tool)");
        
        stderr('Success', "$amount GB Download has been added to " . 
               ($class ? get_user_class_name($class) : 'all users'));
        exit();
    }

    // Process individual user action
    $username = trim($_POST['username'] ?? '');
    $downloaded = (int)($_POST['downloaded'] ?? 0);
    
    if (empty($username) || $downloaded < 1) {
        stderr('Error', 'Please fill all fields with valid values!');
    }

    $modcomment = gmdate('Y-m-d') . " - Got " . mksize($downloaded * 1024 * 1024 * 1024) . 
                 " Download Amount from {$CURUSER['username']} (Download Add Tool)$eol";
    
    $dlBytes = $downloaded * 1024 * 1024 * 1024;
    
    
    $sql = "UPDATE users 
            SET downloaded = downloaded + " . (int)$dlBytes . ", 
                modcomment = CONCAT(" . $db->sqlesc($modcomment) . ", modcomment) 
            WHERE username = " . $db->sqlesc($username) . " 
            AND enabled='yes' AND ustatus='confirmed'";
    
    $db->sql_query($sql);

    // Verify update and redirect
    $res = $db->sql_query("SELECT id FROM users WHERE username = " . $db->sqlesc($username));
    $arr = mysqli_fetch_row($res);
    
    if (!$arr) 
	{
        stderr('Error', 'Unable to update account. User not found.');
    } 
	else 
	{
        write_log("$downloaded GB download amount sent to user: $username by {$CURUSER['username']}");
        
		
		flash_message("$downloaded GB download amount sent to user: $username by {$CURUSER['username']}", "success");
        admin_redirect($_this_script_);
		
		
		
        exit();
    }
}

// Display forms
stdhead('Update Users Download Amounts');
?>

<div class="container-lg">
    <!-- Header -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white rounded-top">
            <h4 class="mb-0"><i class="fas fa-download me-2"></i>Update Download Amounts</h4>
        </div>
    </div>

    <!-- Individual User Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Add Download to Specific User</h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount (GB)</label>
                        <input type="number" name="downloaded" class="form-control" min="1" max="1000" placeholder="e.g., 10" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus-circle me-1"></i> Add Download
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Action Form -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Bulk Add Download to User Group</h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
                <input type="hidden" name="doit" value="yes">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label">User Group</label>
                        <?= _selectbox_(null, 'usergroup') ?>
                    </div>
                    <div class="col-md-3">
                        <?php class_amount(); ?>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-users me-1"></i> Apply to Group
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
stdfoot();
?>
