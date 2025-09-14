<?php


$rootpath = './../';
$thispath = './';
define ('IN_ADMIN_PANEL', true);
define ('STAFF_PANEL_TSSEv56', true);
define ('SKIP_CRON_JOBS', true);
define ('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);


require_once $rootpath . 'global.php';

// Include our base data handler class
require_once $rootpath . '/include/datahandler.php';


require_once $thispath . 'include/adminfunctions.php';


if($_REQUEST['empid']) 
{
	
	
	
	
	$user = get_user($_REQUEST['empid']);

	// Does the user not exist?
	if(!$user)
	{
		stderr('You have selected an invalid user');
		redirect($_this_script_);
	}

	// Set up user handler.
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler('delete');

	// Delete the user
	if(!$userhandler->delete_user($user['id']))
	{
		stderr('This user cannot be deleted');
		redirect($_this_script_);
	}
	

	require INC_PATH . '/function_log_user_deletion.php';
    log_user_deletion ('Following user has been deleted by ' . $CURUSER['username'] . ' (latest_users tool - Staff Panel): Userid: ' . $_REQUEST['empid']);
    //redirect ($_this_script_, 'Account has been deleted!');
	
	
	
    //$sql = "DELETE FROM employee WHERE id='".$_REQUEST['empid']."'";
	//$resultset = mysqli_query($conn, $sql) or die("database error:". mysqli_error($conn));	
	if($userhandler) 
	{
		echo "Record Deleted";
	}
}
?>
