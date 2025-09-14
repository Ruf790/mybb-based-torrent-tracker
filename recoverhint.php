<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

  define("SCRIPTNAME", "recoverhint.php");
  function staffnamecheck ($username)
  {
    global $rootpath;
    global $lang;
	global $db;
    $username = strtolower ($username);
    $query = $db->sql_query("SELECT id FROM users WHERE username = ".$db->sqlesc($username));
	
	
    if (0 < $db->num_rows ($query))
    {
      $res = $db->fetch_array($query);
      $userid = intval ($res['id']);
    }
    else
    {
      stderr ($lang->global['error'], 'nousername');
    }

    $filename = CONFIG_DIR . '/STAFFTEAM';
    $results = @file_get_contents ($filename);
    $results = @explode (',', $results);
    if (in_array ($username . ':' . $userid, $results))
    {
      stderr ($lang->global['error'], $lang->recover['denyaccessforstaff'], false);
      exit ();
    }

  }

  function validusername ($username)
  {
    if (!preg_match ('|[^a-z\\|A-Z\\|0-9]|', $username))
    {
      return true;
    }

    return false;
  }

  require_once 'global.php';
  include_once INC_PATH . '/functions_security.php';
  
  require_once INC_PATH."/functions_user.php";
  
  gzip ();

  failedloginscheck ('Recover');
  $lang->load ('recover');
  define ('RH_VERSION', '1.2.1 ');
  $act = (int)$_GET['act'];
  if ($act == '0')
  {
    define ('SKIP_RELOAD_CODE', true);
    stdhead ($lang->recover['head'], false, 'collapse');
    if (!empty ($_GET['error']))
    {
      if ($_GET['error'] == 1)
      {
        $error = '<tr><td colspan="2"><div class="error">' . sprintf ($lang->recover['errortype3'], remaining ()) . '</div></td></tr>';
      }
      else
      {
        if ($_GET['error'] == 2)
        {
          $error = '<tr><td colspan="2"><div class="error">' . sprintf ($lang->global['invalidimagecode'], remaining ()) . '</div></td></tr>';
        }
      }
    }

    echo '
		<form method="post" action="recoverhint.php?act=1" name="recover" onsubmit="document.forms[\'recover\'].elements[\'send\'].disabled=true; document.forms[\'recover\'].elements[\'send\'].value=\'' . $lang->global['pleasewait'] . '\';">
		<table width="100%" border="0" cellspacing="0" cellpadding="5">
			<tr>
				<td align="center" class="thead">' . $lang->recover['head'] . '</td>
			</tr>
			<tr>
				<td>' . sprintf ($lang->recover['info2'], $maxloginattempts) . '</td>
			</tr>
		</table>
		<br />
		
			
			
			
		<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->recover['head'] . '
	</div>
	 </div>
		</div>';
			
			
			
			
			
			
			
			
			
			
    if (isset ($error))
    {
      echo $error;
    }

    echo '
			
			
			
			
			
		
				
				
				
				
		<div class="container mt-3">
  <div class="card">
  <div class="card-body">
  

<tr><td>' . $lang->recover['fieldusername'] . '<label>


<input class="form-control" type="text" size="30" name="username" /></label> ' . ($iv == 'no' ? ' <input type="submit" value="' . $lang->global['buttonrecover'] . '" name="send" class="btn btn-primary" />' : '') . '


</td></tr>


</div>
</div>
</div>';	
				
	
				
    echo '
		</div></div>
		</form>
		<script type="text/javascript">
			//<![CDATA[
			reload();
			function reload ()
			{
				TSGetID(\'regimage\').src = "' . $BASEURL . '/include/class_tscaptcha.php?" + (new Date()).getTime();
				return;
			};
			//]]>
		</script>';
    stdfoot ();
    exit ();
  }

  if ($act == '1')
  {
    if (($iv == 'yes' OR $iv == 'reCAPTCHA'))
    {
      check_code ($_POST['imagestring'], 'recoverhint.php', true);
    }

    $username = htmlspecialchars_uni ($_POST['username']);
    if ((empty ($username) OR !validusername ($username)))
    {
      failedlogins ('silent', false, false);
      stderr ($lang->global['error'], $lang->global['dontleavefieldsblank']);
      exit ();
    }

    staffnamecheck ($username);
    ($res = $db->sql_query ('SELECT id, username FROM users WHERE username=' . $db->sqlesc($username) . ' AND ustatus = \'confirmed\' AND enabled = \'yes\' LIMIT 1'));
    if (1 <= $db->num_rows ($res))
    {
      $arr = $db->fetch_array ($res);
      $securehash = securehash ($arr['id'] . $arr['username']);
      setcookie ('securehash_recoverhint', $securehash, TIMENOW + 3600);
      redirect ('recoverhint.php?act=3&id=' . $arr['id'] . '&username=' . $username, $lang->global['redirect']);
    }
    else
    {
      stdhead ($lang->recover['head']);
      stdmsg ($lang->global['error'], $lang->global['nousername']);
      failedlogins ('silent', false, false);
      stdfoot ();
    }

    exit ();
  }

  if ($act == '3')
  {
    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
      if ($_SESSION['password_generated'] != 0)
      {
        print_no_permission ();
      }

      $id = (int)$_GET['id'];
      int_check ($id, true);
      $answer = htmlspecialchars_uni ($_POST['answer']);
      if (!$answer)
      {
        failedlogins ('silent', false, false);
        stderr ($lang->global['error'], $lang->global['dontleavefieldsblank']);
      }

      
	 
	  $res = $db->simple_select("users", "id,username,ustatus,enabled", "id='$id'");
	  
	  
      ($user = $db->fetch_array ($res) OR stderr ($lang->global['error'], $lang->global['nouserid']));
      if ((empty ($user['username']) OR !validusername ($user['username'])))
      {
        failedlogins ('silent', false, false);
        stderr ($lang->global['error'], $lang->global['dontleavefieldsblank']);
        exit ();
      }

      staffnamecheck ($user['username']);
      $securehash = securehash ($user['id'] . $user['username']);
      if (($_COOKIE['securehash_recoverhint'] != $securehash OR (empty ($_COOKIE['securehash_recoverhint']) OR empty ($securehash))))
      {
        failedlogins ('silent', false, false);
        print_no_permission ();
        exit ();
      }

     
	  
	  $query = $db->simple_select("ts_secret_questions", "passhint,hintanswer", "userid='{$user['id']}'");
	  
	  
      $Array = $db->fetch_array ($query);
      if (($Array AND is_array ($Array)))
      {
        $user = array_merge ($user, $Array);
      }
      else
      {
        $user = false;
      }

      if ((md5 ($answer) != $user['hintanswer'] OR empty ($user['hintanswer'])))
      {
        failedlogins ('silent', false, false);
        stderr ($lang->global['error'], $lang->recover['invalidanswer']);
        return 1;
      }

      if (((((!$user OR $user['ustatus'] == 'pending') OR $user['enabled'] == 'no') OR empty ($user['passhint'])) OR empty ($user['hintanswer'])))
      {
        failedlogins ('silent', false, false);
        stderr ($lang->global['error'], $lang->global['nouserid']);
        exit ();
        return 1;
      }

	  
	  
	  
	  $password = random_str();
	  $md5password = md5($password);
	  
	  $salt = generate_salt();
	  $saltedpw = salt_password($md5password, $salt);
	  $loginkey = generate_loginkey();
	  
	  
	  $newpass = array(
				"password" => $db->escape_string($saltedpw),
				"salt" => $db->escape_string($salt),
				"loginkey" => $db->escape_string($loginkey)
	  );
	  $db->update_query("users", $newpass, "id='".$id."'");
	  

      if (!$db->affected_rows ())
      {
        stderr ($lang->global['error'], $lang->global['dberror']);
      }

      //$db->sql_query ('DELETE FROM ts_user_validation WHERE userid = ' . $db->sqlesc ($id));
      ++$_SESSION['password_generated'];
      //stderr ($lang->recover['generated1']);
	  
	  stderr ($password);
	  
      return 1;
    }

    $id = (int)$_GET['id'];
    $username = htmlspecialchars_uni ($_GET['username']);
    staffnamecheck ($username);
    if ((((empty ($id) OR !is_valid_id ($id)) OR empty ($username)) OR !validusername ($username)))
    {
      failedlogins ('silent', false, false);
      print_no_permission ();
      exit ();
    }

   
	
	$res = $db->simple_select("users", "id,username,ustatus,enabled", "id='$id' AND username ='{$username}'");
	
	
    ($user = $db->fetch_array ($res) OR stderr ($lang->global['error'], $lang->global['nouserid']));
    $securehash = securehash ($user['id'] . $user['username']);
    if (($_COOKIE['securehash_recoverhint'] != $securehash OR (empty ($_COOKIE['securehash_recoverhint']) OR empty ($securehash))))
    {
      failedlogins ('silent', false, false);
      print_no_permission ();
      exit ();
    }

    
	
	$query = $db->simple_select("ts_secret_questions", "passhint,hintanswer", "userid='{$user['id']}'");
	

    $Array = $db->fetch_array ($query);
    if (($Array AND is_array ($Array)))
    {
      $user = array_merge ($user, $Array);
    }
    else
    {
      $user = false;
    }

    if (((((!$user OR $user['ustatus'] == 'pending') OR $user['enabled'] == 'no') OR empty ($user['passhint'])) OR empty ($user['hintanswer'])))
    {
      failedlogins ('silent', false, false);
      stderr ($lang->global['error'], $lang->global['nouserid']);
      exit ();
    }

    stdhead ($lang->recover['head'], false, 'collapse');
    echo '
		
		
		
		
		<form method="POST" action="recoverhint.php?act=3&id=' . $id . '">
		<tr>
		
		
		
		<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->recover['head'] . '
	</div>
	 </div>
		</div>
		
		
		
		<div class="container mt-3">
  <div class="card">
  <div class="card-body">
		
		
		
		</tr>
		<tr><td colspan="2">' . $lang->recover['info3'] . '</td></tr>
		<tr><td class="rowhead">' . $lang->recover['sq'] . '</td>';
    $HF[0] = '/1/';
    $HF[1] = '/2/';
    $HF[2] = '/3/';
    $HR[0] = '<font color=blue>' . $lang->recover['hr0'] . '</font>';
    $HR[1] = '<font color=blue>' . $lang->recover['hr1'] . '</font>';
    $HR[2] = '<font color=blue>' . $lang->recover['hr2'] . '</font>';
    $passhint = preg_replace ($HF, $HR, $user['passhint']);
    
	echo '<td>' . $passhint . '</td>
	</br>
    <tr><td class="rowhead">' . $lang->recover['ha'] . '</td>
    <td>
	
	<label>
	<input type="text" size="40" name="answer" class="form-control" />
	</label>

	<input type="submit" value="' . $lang->global['buttonrecover'] . '" class="btn btn-primary" /></td></tr>
    </form>
	
	</div>
</div>
</div>
	
	';
	
	
    stdfoot ();
  }

?>
