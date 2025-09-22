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
    <select name="classamount" class="form-select form-select-sm border pe-5 w-auto">
        <option value="0" disabled selected>Select amount</option>';
    
    for ($i = 1; $i < 51; $i++) {
        echo '<option value="' . $i . '">' . $i . ' GB</option>';
    }
    
    echo '</select>';
}

// Security check
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger m-3"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> Direct access to this file is not allowed.</div>');
}

define('UA_VERSION', '0.5 by xam');


 if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
  {
    $eol = '
';
  }
  else
  {
    if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
    {
      $eol = '
';
    }
    else
    {
      $eol = '
';
    }
  }





// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
    
	$class = (int)$_POST['usergroup'];
    if (($class == '-' OR !is_valid_id ($class)))
    {
      $class = '';
    }
	
	$query2 = 'enabled=\'yes\' AND ustatus=\'confirmed\'' . ($class ? ' AND usergroup=' . $class : '');
	if (($_POST['doit'] ?? '') == 'yes') 
	{
    $amount = (int)($_POST['classamount'] ?? 0);

    if ($amount < 1 || $amount > 50) {
        stderr('Error,Please select a valid amount between 1-50 GB!');
    }

    $modcomment = gmdate('Y-m-d') . " - Got " . mksize($amount * 1024 * 1024 * 1024) . 
                 " Upload Amount from {$CURUSER['username']} (Upload Add Tool)$eol";

    $ulamount = $amount * 1024 * 1024 * 1024;

    $sql = "UPDATE users 
            SET uploaded = uploaded + " . (int)$ulamount . ", 
                modcomment = CONCAT(" . $db->sqlesc($modcomment) . ", modcomment) 
            WHERE $query2";

    $db->sql_query($sql);

    write_log("$amount GB upload amount sent to " . ($class ? "usergroup: $class" : "all users") . 
             " by {$CURUSER['username']}");

    stderr('Success  <strong>'.$amount.' GB</strong> upload has been added to ' . 
           ($class ? get_user_class_name($class) : 'all users'));
    exit();
    }


    
    // Process individual user action
$username_raw = trim($_POST['username'] ?? '');
$amount_gb    = (int)($_POST['uploaded'] ?? 0);

if (empty($username_raw) || $amount_gb < 1) {
    stderr('Error, Please fill all fields with valid values!');
}


$username = $db->sqlesc($username_raw);
	
	
	
    
  

    $modcomment = gmdate('Y-m-d') . " - Got " . mksize($amount_gb * 1024 * 1024 * 1024) . 
                 " Upload Amount from {$CURUSER['username']} (Upload Add Tool)$eol";
    
    $ulBytes  = $amount_gb * 1024 * 1024 * 1024;
    
    $sql = "UPDATE users 
        SET uploaded = uploaded + " . (int)$ulBytes . ", 
            modcomment = CONCAT(" . $db->sqlesc($modcomment) . ", modcomment) 
        WHERE username = " . $username . " 
        AND enabled='yes' AND ustatus='confirmed'";
    
    $db->sql_query($sql);

    // Verify update and redirect
    $res = $db->sql_query("SELECT id FROM users WHERE username = $username");
    $arr = mysqli_fetch_row($res);
    
    if (!$arr) 
	{
        stderr('Error, User not found or unable to update account.');
    } 
	else 
	{
        write_log("$amount_gb GB upload amount sent to user: $username by {$CURUSER['username']}");
        
       
	    flash_message("$uploaded GB upload amount sent to user: $username by {$CURUSER['username']}", "success");
        admin_redirect($_this_script_);
	   
	   
    }
}

// Display forms
stdhead('Update Users Upload Amounts');
?>

<div class="container-lg">
    <!-- Header -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Amount Management</h4>
            <small class="opacity-75">Version <?= UA_VERSION ?></small>
        </div>
    </div>

    <!-- Individual User Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Add Upload to Specific User</h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Enter username" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Amount (GB)</label>
                        <input type="number" name="uploaded" class="form-control" 
                               min="1" max="1000" placeholder="e.g., 10" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-plus-circle me-1"></i> Add Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Action Form -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Bulk Add Upload to Group</h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= htmlspecialchars($_this_script_) ?>">
                <input type="hidden" name="doit" value="yes">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">User Group</label>
                        <?= _selectbox_(null, 'usergroup') ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Amount</label>
                        <?php class_amount(); ?>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-bolt me-1"></i> Apply to Group
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Panel -->
    <div class="card shadow-sm mt-4">
        <div class="card-body bg-light">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-info-circle me-2 text-info"></i>Information</h6>
                    <p class="text-muted small mb-0">
                        This tool allows you to add upload credit to individual users or entire user groups.
                    </p>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-shield-alt me-2 text-success"></i>Security</h6>
                    <p class="text-muted small mb-0">
                        All actions are logged and require staff privileges.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
stdfoot();
?>