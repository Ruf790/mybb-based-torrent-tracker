<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function email_exists ($email)
  {
    global $db;
	$tracker_query = $db->sql_query ('SELECT email FROM users WHERE email=' . $db->sqlesc ($email) . ' LIMIT 1');
    if (1 <= $db->num_rows ($tracker_query))
    {
      return false;
    }

    return true;
  }

  function validusername ($username)
  {
    if (!preg_match ('|[^a-z\\|A-Z\\|0-9]|', $username))
    {
      return true;
    }

    return false;
  }

  function safe_email ($email)
  {
    return str_replace (array ('<', '>', '\\\'', '\\"', '\\\\'), '', $email);
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('CE_VERSION', '0.4 by xam');
  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    if (($_POST['username'] == '' OR $_POST['email'] == ''))
    {
      stderr ('Error, Missing form data.');
    }

    $username = $_POST['username'];
    if (!validusername ($username))
    {
      stderr ('Error, Invalid Username.');
    }

    $username = $username;
    $email = htmlspecialchars (trim ($_POST['email']));
    $email = safe_email ($email);
    if ((!check_email ($email) OR !email_exists ($email)))
    {
      stderr ('Error, Invalid email address or Email already taken.');
    }

    require_once INC_PATH . '/functions_EmailBanned.php';
    if (emailbanned ($email))
    {
      stderr ('Error, This email address has been banned!');
    }

    $email = $db->escape_string($email);
   
	
	$update_query = array(
		"email" => $email
	);
				
	$db->update_query("users", $update_query, "username='".$username."'");
	
	
	

	
	$res = $db->simple_select("users", "id", "username='".$db->escape_string($username)."'");
	
	
    $arr = $db->fetch_array ($res);
    if (empty ($arr))
    {
      stderr ('Error, Unable to update account.');
    }
    else
    {
      write_log ($username . ('' . 's email has been changed to ' . $email . ' by ' . $CURUSER['username'] . ' (Change Email Tool)'));
    }

    header ('' . 'Location: '.$BASEURL.'/'.get_profile_link($arr['id']).'');
	
    exit ();
  }

  stdhead ('Change Users E-mail Address');
 
  
   echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Change Users E-mail Address
	</div>
	 </div>
		</div>';
  
  
  
  echo '
  
  
 <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
  
<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
<input type=hidden name=act value=changemail>

User name

<label>
<input type=text name=username class="form-control">
</label>


New E-mail

<label>
<input type=email name=email class="form-control"> 
</label>


<input type=submit value="Change Email" class="btn btn-primary">



</form>

</div>
 </div>
 </div>



';
  
  stdfoot ();
?>
