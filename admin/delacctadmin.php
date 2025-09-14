<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  

  define ('DA_VERSION', '0.2 by xam');
  define("IN_MYBB", 1);
  
  
  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    $username = trim ($_POST['username']);
    if (!$username)
    {
      stderr ('Error, Please fill out the form correctly.');
    }

    ($res = $db->sql_query ('SELECT id FROM users WHERE username=' . $db->sqlesc ($username)));
    if ($db->num_rows ($res) != 1)
    {
      stderr ('Error, No user with this name.');
    }

    $arr = mysqli_fetch_array ($res);
    $id = (int)$arr['id'];
   
	
	$user = get_user($id);
	  
	// Set up user handler.
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler('delete');
	  
	// Delete the user
	if(!$userhandler->delete_user($user['id']))
	{
		stderr('Error, Unable to delete the account.');
		redirect($_this_script_);
	}
	

    require INC_PATH . '/function_log_user_deletion.php';
    log_user_deletion ('Following user has been deleted by ' . $CURUSER['username'] . ' (delactadmin Tool - Staff Panel): Userid: ' . $id);
    stderr ('Success, The account <b>' . htmlspecialchars_uni ($username) . '</b> was deleted.', false);
  }

  stdhead ('Delete account');
  //_form_header_open_ ('Delete User Account');
  
  
  echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Delete User Account
	</div>
	 </div>
		</div>';
  
  
  echo '
  
  <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
  
  
<form method=post action="' . $_this_script_ . '">
<tr><td class=rowhead>User name</td><td>

<label>
<input name=username class="form-control">
</label>


<button type="submit" class="btn btn-primary" name="delete" value="Delete"><i class="fa-solid fa-xmark"></i> &nbsp;Delete</button>

</td></tr>
</form>


</div>
 </div>
 </div>';
  
  stdfoot ();
?>
