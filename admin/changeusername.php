<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

  function validusername ($username)
  {
    if (!preg_match ('|[^a-z\\|A-Z\\|0-9]|', $username))
    {
      return true;
    }

    return false;
  }

  function username_exists ($username)
  {
    global $db;
	$tracker_query = $db->sql_query ('SELECT username FROM users WHERE username=' . $db->sqlesc ($username) . ' LIMIT 1');
    if (1 <= $db->num_rows ($tracker_query))
    {
      return false;
    }

    return true;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('CU_VERSION', '0.3 by xam');
  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    if ((($_POST['username'] == '' OR $_POST['id'] == '') OR !is_valid_id ($_POST['id'])))
    {
      stderr ('Error, Missing form data.');
    }

    $sure = htmlspecialchars ($_POST['sure']);
    $id = $db->sqlesc ((int)$_POST['id']);
    $username = $_POST['username'];
    if ((!validusername ($username) OR !username_exists ($username)))
    {
      stderr ('Error, Invalid Username or Username already taken.');
    }

    $username = $db->sqlesc ($username);
    if (($sure == 'yes' AND !empty ($_POST['oldusername'])))
    {
      $db->sql_query ('' . 'UPDATE users SET username=' . $username . ' WHERE id=' . $id);
      write_log ('' . $_POST['oldusername'] . '\'s account name has been changed to ' . $username . ' by ' . $CURUSER['username'] . ' (Change Username Tool)');
      header ('' . 'Location: ' . $BASEURL . '/'.get_profile_link($id));
      exit ();
    }
    else
    {
      ($get_user = $db->sql_query ('' . 'SELECT id,username FROM users WHERE id=' . $id));
      $user = $db->fetch_array ($get_user);
      if (empty ($user))
      {
        stderr ('Error, No user with this id!');
      }

      stdhead ('Change Username');
      echo '
	  
	  
	    <div class="container mt-3">
        <div class="card">
  
        <div class="card-header text-19 fw-bold">Sanity Check</div>
	  
		
		<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
		<input type=hidden name=act value=changeusername>
		<input type=hidden name=oldusername value="' . $user['username'] . '">
		
		
		<div class="card-body">
		<table border=0 cellspacing=0 cellpadding=5 width=100%>
		<tr><td class=rowhead>User ID</td><td>
		
		
		<label>
		<input type=text name=id value="' . $id . '" class="form-control"> (' . $user['username'] . ')
		</label>
		
		
		</td></tr>
		<tr><td class=rowhead>New Username</td><td>
		
		<label>
		<input type=text name=username value="' . htmlspecialchars (str_replace ('\'', '', $username)) . '" class="form-control">
		</label>
		
		
		<input type=checkbox class="form-check-input" name=sure value=yes style="vertical-align: middle;" checked> <input type=submit value="I\'m Sure Update Account" class="btn btn-primary">
		</td></tr>
		</table>
		</div>
		
		
		</form>
		
		</div>
		</div>';
		
      stdfoot ();
      exit ();
    }
  }

  stdhead ('Change Username');
 
  echo '
<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
<input type=hidden name=act value=changeusername>


<div class="container mt-3">

  <div class="card">
  
  <div class="card-header text-19 fw-bold">Change Username</div>

<div class="card-body">
<table border=0 cellspacing=0 cellpadding=5 width=100%>
<tr><td class=rowhead>User ID</td><td>

<label>
<input type=text name=id class="form-control">
</label>


</td></tr>
<tr><td class=rowhead>New Username</td><td>

<label>
<input type=text name=username class="form-control">
</label>


<input type=submit value="Update" class="btn btn-primary btn-sm">
</td></tr>
</table>
</div>
</form>';
 
  stdfoot ();
?>
