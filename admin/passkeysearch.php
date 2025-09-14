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

  define ('PS_VERSION', 'v0.1 by xam');
  $do = (isset ($_POST['do']) ? htmlspecialchars ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars ($_GET['do']) : 0));
  $error = false;
  $title = 'Search Results: ';
  stdhead ('Passkey Search');
  if ($do == 2)
  {
    $title = 'Passkey Reset: ';
    $passkey = trim (strtolower ($_GET['passkey']));
    if (empty ($passkey))
    {
      $error = 'Please enter passkey!';
    }
    else
    {
      if (strlen ($passkey) != 32)
      {
        $error = 'Invalid Passkey!';
      }
      else
      {
        $db->sql_query ('UPDATE users SET passkey = \'\' WHERE passkey = ' . $db->sqlesc ($passkey));
        $error = 'User passkey has been cleared.';
      }
    }
  }

  if ($do == 1)
  {
    $passkey = trim (strtolower ($_POST['passkey']));
    if (empty ($passkey))
    {
      $error = 'Please enter passkey!';
    }
    else
    {
      if (strlen ($passkey) != 32)
      {
        $error = 'Invalid Passkey!';
      }
      else
      {
        $query = $db->sql_query ('SELECT u.*, g.namestyle FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.passkey = ' . $db->sqlesc ($passkey));
        if ($db->num_rows ($query) == 0)
        {
          $error = 'There is no registered user with this passkey!';
        }
        else
        {
          $user = mysqli_fetch_assoc ($query);
          if ($user['lastactive'] == '0000-00-00 00:00:00')
          {
            $lastseen = 'NEVER';
          }
          else
          {
            $lastseen_date = my_datee ($dateformat, $user['lastactive']);
            $lastseen_time = my_datee ($timeformat, $user['lastactive']);
            $lastseen = '' . $lastseen_date . ' ' . $lastseen_time;
          }

          if ($user['added'] == '0000-00-00 00:00:00')
          {
            $joindate = 'N/A';
          }
          else
          {
            $joindate_date = my_datee ($dateformat, $user['added']);
            $joindate_time = my_datee ($timeformat, $user['added']);
            $joindate = '' . $joindate_date . ' ' . $joindate_time;
          }

          
		  
		  echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$title . '(' . htmlspecialchars_uni ($passkey) . ')
	</div>
	 </div>
		</div>';
		  
		  
		  
          echo '
			
			
			
			
			<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Username</th>
        <th>Email</th>
        <th>IP</th>
		<th>Passkey</th>
        <th>Last Seen</th>
        <th>Registered</th>
		<th>UP</th>
		<th>Down</th>
		<th>Ratio</th>
      </tr>
    </thead>
			
			
			
			
			
			
			
			
			
			';
          include_once INC_PATH . '/functions_ratio.php';
          echo '
			<tr>
				<td><a href="' . $BASEURL . '/'.get_profile_link($user['id']).'">' . format_name($user['username'], $user['usergroup']) . '</a></td>
				<td>' . htmlspecialchars_uni ($user['email']) . '</td>
				<td>' . htmlspecialchars_uni ($user['ip']) . '</td>			
				<td>' . htmlspecialchars_uni ($user['passkey']) . ' <b>[<a href="' . $_this_script_ . '&do=2&passkey=' . htmlspecialchars_uni ($user['passkey']) . '">reset</a>]</b></td>
				<td>' . $lastseen . '</td>
				<td>' . $joindate . '</td>
				<td>' . mksize ($user['uploaded']) . '</td>
				<td>' . mksize ($user['downloaded']) . '</td>
				<td>' . get_user_ratio ($user['uploaded'], $user['downloaded']) . '</td>
			</tr>
			
			</table>
			</div>
            </div>
			
			';
          
          echo '<br />';
        }
      }
    }
  }

  if (!empty ($error))
  {
   
	
	
	echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$title . '(' . htmlspecialchars_uni ($passkey) . ')
	</div>
	 </div>
		</div>';
	
	
	
    echo '
	
	<div class="container mt-3">
	<div class="card">
    <div class="card-body">
	
	<tr><td><font color="red"><b>' . $error . '</b></font></td></tr>
	</div></div></div>';
   
    echo '<br />';
  }

 
  
  echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Search Passkey
	</div>
	 </div>
		</div>';
  
  
  
  echo '
  
<div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">  
  
<tr><td>
<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
		<input type="hidden" name="act" value="passkeysearch">
		<input type="hidden" name="do" value="1">
		Passkey: <label><input type="text" class="form-control" name="passkey" value="' . htmlspecialchars_uni ($passkey) . '"></label>  
		<input type="submit" class="btn btn-primary" name="submit" value="search">
</form>
</td></tr>


</div>
 </div>
 </div>


';
 
 
 
 
  stdfoot ();
?>
