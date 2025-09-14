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

  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    $username = trim ($_POST['username']);
    if (!$username)
    {
      stderr ('Error, Dont leave any fields blank!');
    }

    ($res = $db->sql_query ('SELECT id,username,email FROM users WHERE username=' . $db->sqlesc ($username)));
    if ($db->num_rows ($res) == '0')
    {
      stderr ('Error, No user found!');
    }
    else
    {
      $arr = $db->fetch_array ($res);
    }

    $id = (int)$arr['id'];
    $wantpassword = 'ts_auto_password_reset';
    $secret = mksecret ();
    $wantpasshash = md5 ($secret . $wantpassword . $secret);
    $db->sql_query ('UPDATE users SET passhash=' . $db->sqlesc ($wantpasshash) . ', secret= ' . $db->sqlesc ($secret) . ' where id=' . $db->sqlesc ($id));
    if ($db->affected_rows () != 1)
    {
      stderr ('Error, Unable to RESET PASSWORD on this account.');
      return 1;
    }

    write_log ('' . 'Password Reset for ' . $username . ' by ' . $CURUSER['username']);
    if ($_POST['inform'] == 'yes')
    {
      $subject = 'Password Reset on ' . $SITENAME;
      $body = 'Hello ' . $arr['username'] . ',

			Your password has been reset by ' . $CURUSER['username'] . '.
			
			Please login and change your password by using following link:
			' . $BASEURL . '/usercp.php?act=edit_password

			Your new password is: ' . $wantpassword . '

			Thank you.
			' . $SITENAME . ' Team.';
      sent_mail ($arr['email'], $subject, $body, 'reset', false);
    }

    stdhead ('Reset User\'s Lost Password');
    stdmsg ('Success', 'The account \'<b>' . htmlspecialchars ($username) . '</b>\' password reset to \'<b>' . $wantpassword . '</b>\'.  ' . ($_POST['inform'] == 'yes' ? 'Information mail has been sent.' : 'Please inform user of this change.'), false, 'success');
    stdfoot ();
    return 1;
  }

  stdhead ('Reset User\'s Lost Password');
 
  
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Reset User\'s Lost Password
	</div>
	 </div>
		</div>';
  
  
  
  
  echo '
	
	<div class="container mt-3">
    <div class="card">
	
	<form method=post action="' . $_this_script_ . '">
	<tr><td class=rowhead>User name</td><td>
	
	<label>
	<input name=username class="form-control"> 
	</label>
	
	<input type=checkbox class="form-check-input" name=inform value=yes> Inform user via email <input type=submit class="btn btn-primary" value="Reset Password">
	</td></tr>
	</form>
	
	</div>
</div>
	';
 
 
 
  stdfoot ();
?>
