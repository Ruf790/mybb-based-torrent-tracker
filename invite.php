<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function check_amount ($uid)
  {
    global $db;
	$res = $db->sql_query ('SELECT invites FROM users WHERE id = ' . $db->sqlesc ($uid));
    if ($db->num_rows ($res) == 0)
    {
      return false;
    }

    $amount = mysqli_fetch_array ($res);
    if ($amount['invites'] < 1)
    {
      return false;
    }

    return true;
  }

  function invite_amount ($uid)
  {
    global $db;
	$res = $db->sql_query ('SELECT invites FROM users WHERE id = ' . $db->sqlesc ($uid));
    $amount = mysqli_fetch_array ($res);
    if (($amount['invites'] == 1 OR $amount['invites'] == 2))
    {
      $msg = '<font color=red>' . $amount['invites'] . '</font>';
    }
    else
    {
      $msg = '<font color=green>' . $amount['invites'] . '</font>';
    }

    return $msg;
  }

  function is_email_exists ($email)
  {
    global $db;
	$check1 = $db->sql_query ('SELECT email FROM users WHERE email = ' . $db->sqlesc ($email));
    if (1 <= $db->num_rows ($check1))
    {
      return false;
    }

    $check2 = $db->sql_query ('SELECT invitee FROM invites WHERE invitee = ' . $db->sqlesc ($email));
    if (1 <= $db->num_rows ($check2))
    {
      return false;
    }

    return true;
  }
  


  define("IN_MYBB", 1);
  
  require_once 'global.php';
  
  if (!isset($CURUSER) || isset($CURUSER) && $CURUSER["id"] == 0) 
  {
    print_no_permission();
  }
  
  
  
  gzip ();
  maxsysop ();
  define ('I_VERSION', '1.2');
  //require INC_PATH . '/readconfig_signup.php';
  require INC_PATH . '/readconfig.php';
  
  

  $lang->load ('invite');
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'main'));
  $type = (isset ($_POST['type']) ? htmlspecialchars ($_POST['type']) : (isset ($_GET['type']) ? htmlspecialchars ($_GET['type']) : ''));
  $is_mod = is_mod ($usergroups);
  stdhead ($lang->invite['head'], true, 'collapse');
  if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND ($is_mod OR $usergroups['canuserdetails'] == 'yes')))
  {
    $inviterid = (int)$_GET['id'];
    $ra = $db->sql_query ('SELECT username FROM users where id = ' . $db->sqlesc ($inviterid));
    $raa = mysqli_fetch_array ($ra);
    $invitername = htmlspecialchars (trim ($raa['username']));
  }
  else
  {
    $inviterid = (int)$CURUSER['id'];
    $invitername = htmlspecialchars (trim ($CURUSER['username']));
  }

  if ($action == 'delete')
  {
    $error = false;
    $deleteids = $_POST['id'];
    if ((empty ($deleteids) OR !is_array ($deleteids)))
    {
      $error = true;
    }
    else
    {
      foreach ($deleteids as $id)
      {
        if (!is_valid_id ($id))
        {
          $error = true;
          break;
        }
      }
    }

    if (!$error)
    {
      $ids = implode (',', $deleteids);
      $db->sql_query ('' . 'DELETE FROM invites WHERE id IN (' . $ids . ') AND inviter = ' . $db->sqlesc ($inviterid));
    }

    $action = 'main';
  }

  if ($action == 'main')
  {
    $res = $db->sql_query ('SELECT invites FROM users WHERE id = ' . $db->sqlesc ($inviterid));
    $inv = $db->fetch_array ($res);
    if ($inv['invites'] != 1)
    {
      $_s = 's';
    }
    else
    {
      $_s = '';
    }

    $number = tsrowcount ('id', 'users', 'invited_by=' . $inviterid);
    
	$ret = $db->sql_query ('SELECT u.id, u.username, u.email, u.uploaded, 
	u.lastactive, u.last_login, u.added, u.downloaded, 
	u.ustatus, u.warned, u.leechwarn, u.enabled, u.donor, u.email, p.canupload, p.candownload, p.cancomment,
	g.namestyle 
	FROM users u 
	LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
	LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
	WHERE u.invited_by = ' . $db->sqlesc ($inviterid));
	
    $num = $db->num_rows ($ret);
    echo '
	
	<div class="container mt-3">
	<p align="right">
		<input value="' . $lang->invite['button'] . '" class="btn btn-primary" onclick="jumpto(\'invite.php?action=send\');" type="button">
	<p>
	</div>';
	
    echo '
	
	
	
	
  
  
   
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->invite['status'] . '</b> (' . $number . ')
	</div>
	 </div>
		</div>
	 
		
		
	<div class="container mt-3">	
		
		';
  
  
  
	
	
	
    if (!$num)
    {
      $str = '<tr class=tableb><td colspan=8>' . $lang->invite['noinvitesyet'] . '</td></tr></tbody>';
    }
    else
    {
      print '
	 
	
	  
	   <div class="card">
            
  <table class="table table-hover">
	  
	   <thead>
      <tr>
        <th>' . $lang->invite['username'] . '</th>
        <th>' . $lang->invite['email'] . '</th>
        <th>' . $lang->invite['added'] . '</th>
		<th>' . $lang->invite['lastseen'] . '</th>
        <th>' . $lang->invite['uploaded'] . '</th>
        <th>' . $lang->invite['downloaded'] . '</th>
		<th>' . $lang->invite['ratio'] . '</th>
		<th>' . $lang->invite['status2'] . '</th>
      </tr>
    </thead>
	  
	  
	  
	  
	  
	  
	  
	  ';
	  
	  include_once INC_PATH . '/functions_icons.php';
	  
	  
      $dt = get_date_time (gmtime () - TS_TIMEOUT);
      
	  $str = "";
	  
	  while ($arr = mysqli_fetch_array ($ret))
      {
        $orj_username = $arr['username'];
        $arr['username'] = get_user_color ($arr['username'], $arr['namestyle']);
        $registered = my_datee ($dateformat, $arr['added']) . ' ' . my_datee ($timeformat, $arr['added']);
        $lastseen = $arr['lastactive'];
        if ((preg_match ('#B1#is', $arr['options']) AND !$is_mod))
        {
          $lastseen = $arr['last_login'];
        }

        if (($lastseen == '0000-00-00 00:00:00' OR $lastseen == '-'))
        {
          $lastseen = $lang->invite['never'];
        }
        else
        {
          $lastseen = my_datee ($dateformat, $lastseen) . ' ' . my_datee ($timeformat, $lastseen);
        }

        if ($arr['ustatus'] == 'pending')
        {
          $user = '' . '<a href=checkuser.php?id=' . $arr['id'] . '>' . $arr['username'] . '</a>';
        }
        else
        {
          $user = '<a href="' . get_profile_link($arr['id']) . '">' . $arr['username'] . '</a>' . get_user_icons ($arr).'';
        }

        if (0 < $arr['downloaded'])
        {
          include_once INC_PATH . '/functions_ratio.php';
          $ratio = number_format ($arr['uploaded'] / $arr['downloaded'], 2);
          $ratio = '<font color=' . get_ratio_color ($ratio) . ('' . '>' . $ratio . '</font>');
        }
        else
        {
          if (0 < $arr['uploaded'])
          {
            $ratio = 'Inf.';
          }
          else
          {
            $ratio = '---';
          }
        }

        if ($arr['ustatus'] == 'confirmed')
        {
          $status = '<a href="' . get_profile_link($arr['id']) . '">
		  
		  
		  <span class="badge bg-success">' . $lang->invite['confirmed'] . '</span>
		  
		  </a>';
        }
        else
        {
          $status = '' . '<a href=checkuser.php?id=' . $arr['id'] . '><span class="badge bg-danger">' . $lang->invite['pending'] . '</span></a>';

        }

        $str .= '' . '<tr class=tableb><td>' . $user . '</td><td><a href=mailto:' . $arr['email'] . '>' . $arr['email'] . '</a></td><td>' . $registered . '</td><td>' . $lastseen . '</td><td>' . mksize ($arr['uploaded']) . '</td><td>' . mksize ($arr['downloaded']) . ('' . '</td><td>' . $ratio . '</td><td>' . $status . '</td></tr>');
      }
    }

    echo $str . '</tbody></table></div><br />';
    unset ($str);
    $number1 = tsrowcount ('id', 'invites', 'inviter=' . $inviterid);
    $rer = $db->sql_query ('SELECT id, invitee, hash, time_invited FROM invites WHERE inviter = ' . $db->sqlesc ($inviterid));
    $num1 = $db->num_rows ($rer);
    print '
	
	
	
	
	
	<div class="container-md">
	 
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->invite['status3'] . '(' . $number1 . ')
	</div>
	 </div>
		</div>
		
		
		
		
		
		';
   
   
	
	
	
	if (!$num1)
    {
      $str = '<tr class=rowhead><td colspan=5>' . $lang->invite['nooutyet'] . '</tr>';
    }
    else
    {
      print '
	  
	   <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>' . $lang->invite['email'] . '</th>
        <th>' . $lang->invite['hash'] . '</th>
        <th>' . $lang->invite['senddate'] . '</th>
		<th>' . $lang->invite['invitedeadtime'] . '</th>
        <th>' . $lang->invite['action'] . '</th>

      </tr>
    </thead>';
	  
	  
	  
	  
	  
      print '<form method=\'post\' action=\'' . $_SERVER['SCRIPT_NAME'] . '\'><input type=\'hidden\' name=\'action\' value=\'delete\'>';
      include_once INC_PATH . '/readconfig_cleanup.php';
      $i = 0;
      while ($i < $num1)
      {
        $arr1 = mysqli_fetch_array ($rer);
        $timeout = $arr1['time_invited'] + 172800;
        $timeoutdate = my_datee ($dateformat, $timeout);
        $timeouttime = my_datee ($timeformat, $timeout);
        $senddate = my_datee ($dateformat, $arr1['time_invited']) . ' ' . my_datee ($timeformat, $arr1['time_invited']);
        $_m_link = strip_tags (sprintf ($lang->invite['manuellink'], $BASEURL, $arr1['hash']));
        $str .= '' . '<tr class=rowhead><td>' . $arr1['invitee'] . '<td><span style="float: right;">
        <a href="" onclick="javascript:prompt(\'' . $_m_link . '\',\'' . $BASEURL . '/signup.php?invitehash=' . $arr1['hash'] . '&type=invite\'); return false;"><img src="' . $BASEURL . '/' . $pic_base_url . 'plus.gif" alt="' . $lang->invite['hash'] . '" title="' . $lang->invite['hash'] . '" border=""></a></span>' . $arr1['hash'] . '</td><td>' . $senddate . '</td><td>' . $timeoutdate . ' ' . $timeouttime . '</td><td align=\'center\'>
		<input type=\'checkbox\' class=\'form-check-input\' name=\'id[]\' value=\'' . $arr1['id'] . '\'></td></tr>';
        ++$i;
      }

      $str .= '<tr><td colspan=\'5\' align=\'right\'><input type=\'submit\' class=\'btn btn-primary\' value=\'' . $lang->invite['actionbutton'] . '\'></form></td></tr>';
    }

    echo $str . '</table></div>
	</div>';
  }
  else
  {
    if ($action == 'send')
    {
      $alert = false;
      if (!check_amount ($inviterid))
      {
        error ($lang->invite['noinvitesleft']);
      }
      else
      {
        if (($invitesystem == 'off' AND $is_mod))
        {
          $alert = true;
        }
        else
        {
          if ($invitesystem == 'off')
          {
            error ($lang->invite['invitesystemoff']);
          }
        }
      }

      if ($alert)
      {
        //echo '<div class="error">' . $lang->invite['alert'] . '</div>';
		
		
		echo '
		
		<div class="container mt-3">
         <div class="red_alert mb-3" role="alert">
          ' . $lang->invite['alert'] . '
        </div>
        </div>
		
		
		';
		
		
      }

      if ($type == 'email')
      {
        echo '
		<div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
   
  
  ';
        echo '<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '" name="sendinvite" ' . submit_disable ('sendinvite', 'send') . '>';
        echo '<input type="hidden" name="action" value="sendinvite">';
        echo ''.$lang->invite['field1'].'
		<label>
		<input type="text" name="email" class="form-control"></label> <b><font color=red>' . $lang->invite['field2'] . '</b></font>
		</br>
		
		
        
		<tr>
		<td align=left>'.$lang->invite['field3'].'</td>
		</tr>
		
		</br>
		
		
		<textarea name="note" style="width: 100%; height: 200px;" wrap="virtual" class="form-control form-control-sm border">' . $lang->invite['default_invite_msg'] . '</textarea>
		
		
		
		
		
		
		
        '.sprintf ($lang->invite['field4'], invite_amount ($inviterid)).'
		<input type="submit" class="btn btn-primary" value="' . $lang->invite['button2'] . '" name="send"> <input type="reset" class="btn btn-primary" value="' . $lang->invite['button3'] . '">';
        echo '</div></div></div>';
      }
      else
      {
        if ($type == 'manual')
        {
          $hash = substr (md5 (md5 (rand ())), 0, 32);
          $time_invited = TIMENOW;
          
		 
		  
		  
		  $insert_invite = array(
			"inviter" => $db->escape_string($inviterid),
			"invitee" => 'manual',
			"hash" => $db->escape_string($hash),
			"time_invited" => $db->escape_string($time_invited)

		  );


		  $db->insert_query("invites", $insert_invite);
		  
		  
		  
		  if ($db->affected_rows () != 1)
          {
            error ($lang->invite['error']);
          }
          else
          {
            $db->sql_query ('UPDATE users SET invites = invites - 1 WHERE id = ' . $db->sqlesc ($inviterid));
          }

          if ($db->affected_rows () != 1)
          {
            error ($lang->invite['error']);
          }
          else
          {
            //error($lang->invite['success'], sprintf ($lang->invite['manuellink'], $BASEURL, $hash), false, 'success');
			
			
			echo '
			
			<div class="container mt-3">
			    <div class="alert alert-primary" role="alert">
                '.sprintf ($lang->invite['manuellink'], $BASEURL, $hash).'
				</div>
			</div>';
			
			
			
			
			
			
            stdfoot ();
            exit ();
          }
        }
        else
        {
          echo '
		<div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
		
		<tr><td class="thead">' . $lang->invite['selecttype'] . '</td></tr>
		<tr><td class="trow1">
		<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '" name="sendinvite" ' . submit_disable ('sendinvite', 'submit') . '>
		<input type="hidden" name="action" value="send">
		
		<label>
		<select name="type" class="form-select form-select-sm border pe-5 w-auto">
		<option value="email">' . $lang->invite['type1'] . ' </option>
		<option value="manual">' . $lang->invite['type2'] . ' </option>
		</select>
		</label>
		
		
		 <input type="submit" name="submit" value="' . $lang->invite['typebutton'] . ' " class="btn btn-primary">
		 </form>
		 </td></tr>
		
		</div></div></div>';
        }
      }
    }
    else
    {
      if ($action == 'sendinvite')
      {
        function safe_email ($email)
        {
          return str_replace (array ('<', '>', '\\\'', '\\"', '\\\\'), '', $email);
        }

        if (($invitesystem == 'off' AND !$is_mod))
        {
          error ($lang->invite['invitesystemoff']);
        }

        if (!check_amount ($inviterid))
        {
          error ($lang->invite['noinvitesleft']);
        }

        $email = htmlspecialchars_uni (safe_email ($_POST['email']));
        if (!check_email ($email))
        {
          error ($lang->invite['invalidemail']);
        }

        if (!is_email_exists ($email))
        {
          error ($lang->invite['invalidemail2']);
        }

        $note = htmlspecialchars_uni ($_POST['note']);
        if (empty ($note))
        {
          $note = $lang->invite['nonote'];
        }

        $subject = sprintf ($lang->invite['subject'], $SITENAME);
        $time_invited = TIMENOW;
        $invitehash = substr (md5 (md5 (rand ())), 0, 32);
        include_once INC_PATH . '/readconfig_cleanup.php';
        $message = sprintf ($lang->invite['message'], $invitername, $SITENAME, $BASEURL, $invitehash, 2, $note);
        
		
		
		
		
		$insert_invite2 = array(
			"inviter" => $db->escape_string($inviterid),
			"invitee" => $db->escape_string($email),
			"hash" => $db->escape_string($invitehash),
			"time_invited" => $db->escape_string($time_invited)

		);


		$db->insert_query("invites", $insert_invite2);
        
		
		
		if ($db->affected_rows () != 1)
        {
          error ($lang->invite['error']);
        }
        else
        {
          $db->sql_query ('UPDATE users SET invites = invites - 1 WHERE id = ' . $db->sqlesc ($inviterid));
        }

        if ($db->affected_rows () != 1)
        {
          error ($lang->invite['error']);
        }
        else
        {
        
		  my_mail($email, $subject, $message);;
		  
        }

        error (sprintf ($lang->invite['sent'], $email), false);
      }
    }
  }

  stdfoot ();
?>
