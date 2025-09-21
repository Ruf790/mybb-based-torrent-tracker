<?php

define ('IN_ADMIN_PANEL', true);
define ('STAFF_PANEL_TSSEv56', true);
define ('SKIP_CRON_JOBS', true);
define ('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);
define("IN_ADMINCP", 1);





require_once(INC_PATH.'/functions_mkprettytime.php');





$act2 = isset($_GET['act2']) ? $_GET['act2'] : '';
$cronid = isset($_GET['cronid']) ? (int)$_GET['cronid'] : 0;

// === POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($act2 === 'save' || $act2 === 'save_new') && (is_valid_id($cronid) || $act2==='save_new')) {
        $filename = isset($_POST['filename']) ? $db->sqlesc(trim($_POST['filename'])) : "''";
        $description = isset($_POST['description']) ? $db->sqlesc(trim($_POST['description'])) : "''";

        $mosecs=31*24*60*60; $wsecs=7*24*60*60; $dsecs=24*60*60; $hsecs=60*60; $msecs=60;
        $minutes = 0;
        if(!empty($_POST['months']))  $minutes += $mosecs*(int)$_POST['months'];
        if(!empty($_POST['weeks']))   $minutes += $wsecs*(int)$_POST['weeks'];
        if(!empty($_POST['days']))    $minutes += $dsecs*(int)$_POST['days'];
        if(!empty($_POST['hours']))   $minutes += $hsecs*(int)$_POST['hours'];
        if(!empty($_POST['minutes'])) $minutes += $msecs*(int)$_POST['minutes'];

        $act2ive = !empty($_POST['active']) ? 1 : 0;
        $loglevel = !empty($_POST['loglevel']) ? 1 : 0;

        if($act2==='save_new') 
		{
            $nextrun = TIMENOW+$minutes;
            $db->sql_query("INSERT INTO ts_cron (filename,description,minutes,nextrun,active,loglevel) VALUES ($filename,$description,'$minutes','$nextrun','$act2ive','$loglevel')");
            flash_message("New cron job created successfully!", "success");
        } 
		else 
		{
            $db->sql_query("UPDATE ts_cron SET filename=$filename, description=$description, minutes='$minutes', active='$act2ive', loglevel='$loglevel' WHERE cronid='$cronid'");
            flash_message("Cron job updated successfully!", "success");
        }

        admin_redirect($_this_script_);
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') 
{
    if ($act2 === 'run' && is_valid_id($cronid)) 
	{
        $db->sql_query("UPDATE ts_cron SET nextrun='".TIMENOW."' WHERE cronid='$cronid'");
        flash_message("Cron job scheduled to run immediately!", "info");
        admin_redirect($_this_script_);
        exit();
    }

    if (in_array($act2,['active','disable']) && is_valid_id($cronid)) 
	{
        $status = ($act2==='active')?1:0;
        $db->sql_query("UPDATE ts_cron SET active='$status' WHERE cronid='$cronid'");
        $status_text = $status ? "enabled" : "disabled";
        flash_message("Cron job {$status_text} successfully!", "success");
        admin_redirect($_this_script_);
        exit();
    }

    if ($act2==='delete' && is_valid_id($cronid)) 
	{
        $db->sql_query("DELETE FROM ts_cron WHERE cronid='$cronid'");
        flash_message("Cron job deleted successfully!", "success");
        admin_redirect($_this_script_);
        exit();
    }
}

// === HTML ===
stdhead('Cron Jobs'); 
?>

<div class="container mt-4">



<?php

if(in_array($act2,['edit','save_new']) && ($act2==='save_new' || is_valid_id($cronid))) 
{
    $IsNew = ($act2==='save_new');

    if(!$IsNew) 
	{
        $query = $db->sql_query("SELECT * FROM ts_cron WHERE cronid='$cronid'");
        if($db->num_rows($query)) 
		{
            $Cron = $db->fetch_array($query);
            $TArray = calc_cron_time($Cron['minutes']);
        } 
		else 
		{
            flash_message("Cron job not found!", "danger");
            $IsNew=true;
        }
    }

    if($IsNew) 
	{
        $Cron = ['cronid'=>999,'filename'=>'','description'=>'','active'=>0,'loglevel'=>0];
        $TArray = [];
    }
?>
<div class="card mb-4">
<div class="card-header"><h5><?php echo $IsNew?'Create New Cron Job':'Edit Cron Job'; ?></h5></div>
<div class="card-body">
<form method="POST" action="<?= $_this_script_ ?>&act2=<?php echo $IsNew?'save_new':'save'; ?>&cronid=<?php echo $Cron['cronid']; ?>">
<div class="mb-3">
<label for="filename" class="form-label">Filename</label>
<input type="text" class="form-control" name="filename" id="filename" value="<?php echo htmlspecialchars($Cron['filename']);?>" required>
</div>
<div class="mb-3">
<label for="description" class="form-label">Description</label>
<input type="text" class="form-control" name="description" id="description" value="<?php echo htmlspecialchars($Cron['description']);?>" required>
</div>
<div class="row g-2">
<?php
$timeFields = ['months'=>12,'weeks'=>4,'days'=>31,'hours'=>24,'minutes'=>60];
foreach($timeFields as $name=>$max) {
    echo "<div class='col-md'><label class='form-label text-capitalize'>$name</label><select class='form-select' name='$name'>";
    for($i=0;$i<=$max;$i++){
        $selected=(isset($TArray[$name]) && $TArray[$name]==$i)?'selected':'';
        echo "<option value='$i' $selected>$i $name</option>";
    }
    echo "</select></div>";
}
?>
</div>
<div class="form-check form-switch mt-3">
<input class="form-check-input" type="checkbox" id="active" name="active" value="1" <?php echo $Cron['active']?'checked':'';?>>
<label class="form-check-label" for="active">Activate</label>
</div>
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" id="loglevel" name="loglevel" value="1" <?php echo $Cron['loglevel']?'checked':'';?>>
<label class="form-check-label" for="loglevel">Log cron execution</label>
</div>
<div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
<button type="submit" class="btn btn-primary">Save</button>
<a href="<?= $_this_script_ ?>" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
<?php } ?>

<!-- Cron Jobs -->
<div class="card mb-4">
<div class="card-header d-flex justify-content-between align-items-center">
<h5 class="mb-0">Cron Jobs Management</h5>
<a href="<?= $_this_script_ ?>&act2=save_new" class="btn btn-sm btn-primary">Create New Cronjob</a>
</div>
<div class="card-body table-responsive">
<table class="table table-striped">
<thead>
<tr>
<th>Filename</th>
<th>Description</th>
<th>Run Period</th>
<th>Next Run</th>
<th>Logging</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$result = $db->sql_query("SELECT * FROM ts_cron ORDER BY cronid");
if($db->num_rows($result) > 0) 
{
    while($cron = $db->fetch_array($result)) 
	{
        echo "<tr>
        <td>".htmlspecialchars($cron['filename'])."</td>
        <td>".htmlspecialchars($cron['description'])."</td>
        <td>".mkprettytime($cron['minutes'])."</td>
        <td>".my_datee($dateformat,$cron['nextrun']).' '.my_datee($timeformat,$cron['nextrun'])."</td>
        <td><span class='badge bg-".($cron['loglevel']?'success':'danger')."'>".($cron['loglevel']?'YES':'NO')."</span></td>
        <td><span class='badge bg-".($cron['active']?'success':'danger')."'>".($cron['active']?'ACTIVE':'DISABLED')."</span></td>
        <td>
        <div class='btn-group btn-group-sm'>
        <a href='$_this_script_&act2=run&cronid={$cron['cronid']}' class='btn btn-outline-primary'><i class='fas fa-play'></i></a>
        <a href='$_this_script_&act2=edit&cronid={$cron['cronid']}' class='btn btn-outline-secondary'><i class='fas fa-edit'></i></a>
        <a href='$_this_script_&act2=".($cron['active']?'disable':'active')."&cronid={$cron['cronid']}' class='btn btn-outline-".($cron['active']?'warning':'success')."'><i class='fas fa-power-off'></i></a>
        <a href='$_this_script_&act2=delete&cronid={$cron['cronid']}' class='btn btn-outline-danger' onclick=\"return confirm('Are you sure to delete?');\"><i class='fas fa-trash-alt'></i></a>
        </div>
        </td>
        </tr>";
    }
} 
else 
{
    echo "<tr><td colspan='7' class='text-center'>No cron jobs found</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>

<!-- Cron Logs -->
<div class="card mb-4">
<div class="card-header"><h6 class="mb-0">Cron Jobs Execution Logs</h6></div>
<div class="card-body table-responsive">
<table class="table table-sm table-striped">
<thead>
<tr>
<th>Filename</th>
<th class="text-center">Query Count</th>
<th class="text-center">Execute Time</th>
<th class="text-center">Last Run</th>
</tr>
</thead>
<tbody>
<?php
$query = $db->sql_query('SELECT * FROM ts_cron_log ORDER BY runtime DESC LIMIT 50');
if($db->num_rows($query) > 0) 
{
    while($log = $db->fetch_array($query)) 
	{
        echo "<tr>
        <td>".htmlspecialchars($log['filename'])."</td>
        <td class='text-center'>".number_format($log['querycount'])."</td>
        <td class='text-center'>".$log['executetime']." sec</td>
        <td class='text-center'>".date($dateformat,$log['runtime']).' '.date($timeformat,$log['runtime'])."</td>
        </tr>";
    }
} 
else 
{
    echo "<tr><td colspan='4' class='text-center'>No cron logs found</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>

</div>
<?php stdfoot(); ?>