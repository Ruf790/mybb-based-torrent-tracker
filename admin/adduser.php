<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  require_once INC_PATH.'/functions_user.php';
  
  require INC_PATH . '/readconfig.php';

  

  function username_exists2 ($username)
  {
    global $illegalusernames, $db;
    //$tracker_query = $db->sql_query ('SELECT username FROM users WHERE username=' . $db->escape_string($username) . ' LIMIT 1');
	
	$tracker_query = $db->simple_select("users", "username", "username='{$username}'", array('limit' => 1));
	
    if (0 < $db->num_rows ($tracker_query))
    {
      return true;
    }

    $usernames = preg_split ('/\\s+/', $illegalusernames, 0 - 1, PREG_SPLIT_NO_EMPTY);
    foreach ($usernames as $val)
    {
      if (strpos (strtolower ($username), strtolower ($val)) !== false)
      {
        return true;
      }
    }

    return false;
  }

  function email_exists ($email)
  {
    global $db;
	//$tracker_query = $db->sql_query ('SELECT email FROM users WHERE email=' . $db->escape_string($email) . ' LIMIT 1');
	
	$tracker_query = $db->simple_select("users", "email", "email='{$email}'", array('limit' => 1));
	
    return (0 < $db->num_rows ($tracker_query) ? true : false);
  }

  function validusername ($username)
  {
    return (!preg_match ('|[^a-z\\|A-Z\\|0-9]|', $username) ? true : false);
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('AU_VERSION', '1.1 by xam');
  define("IN_MYBB", 1);
  
  $lang->load ('adduser');
  
  $lang->load("signup");
 
  
  $ffcache = $cache->read('SIGNUP');
  $illegalusernames = $ffcache['illegalusernames'];

  $allowed_usergroups = $error = array ();
  $ugs .= '<select name="usergroup" class="form-select form-select-sm border pe-5 w-auto">';
  $query = $db->sql_query ('SELECT gid, title FROM usergroups WHERE isbannedgroup = \'0\' AND issupermod = \'0\' AND cansettingspanel = \'0\' AND canstaffpanel = \'0\' AND canuserdetails = \'0\' ORDER BY gid');
  while ($ug = mysqli_fetch_assoc ($query))
  {
    $allowed_usergroups[] = $ug['gid'];
    $ugs .= '<option value="' . $ug['gid'] . '"' . ($_POST['usergroup'] == $ug['gid'] ? ' selected="selected"' : '') . ' />' . $ug['title'] . '</option>';
  }

  $ugs .= '</select>';
  if (strtoupper ($_SERVER['REQUEST_METHOD'] == 'POST'))
  {
    //require INC_PATH . '/functions_EmailBanned.php';
    $lang->load ('signup');
    $username = trim ($_POST['username']);
    $email = trim ($_POST['email']);
    $password = trim ($_POST['password']);
    $password2 = trim ($_POST['password2']);
    $usergroup = intval ($_POST['usergroup']);
    $modcomment = htmlspecialchars_uni ($_POST['modcomment']);
    $seedbonus = intval ($_POST['seedbonus']);
    $invites = intval ($_POST['invites']);
    $uploaded = intval ($_POST['uploaded']);
    $downloaded = intval ($_POST['downloaded']);
    $confirm = trim ($_POST['confirm']);
	
	
	// Determine the usergroup stuff
	if(!empty($mybb->input['additionalgroups']) && is_array($mybb->input['additionalgroups']))
	{
		foreach($mybb->input['additionalgroups'] as $key => $gid)
		{
				if($gid == $usergroup)
				{
					unset($mybb->input['additionalgroups'][$key]);
				}
		}
		$additionalgroups = implode(",", array_map('intval', $mybb->input['additionalgroups']));
	}
	else
	{
		$additionalgroups = '';
	}

	
	
	
	
	
    if (strlen ($username) < 6)
    {
      $error[] = $lang->signup['une1'];
    }

    if (32 < strlen ($username))
    {
      $error[] = $lang->signup['une2'];
    }

    if (!validusername ($username))
    {
      $error[] = $lang->signup['une3'];
    }

    if (username_exists2 ($username))
    {
      $error[] = $lang->signup['une4'];
    }

    //if (!check_email ($email))
    //{
    //  $error[] = $lang->signup['invalidemail'];
    //}

    //if (emailbanned ($email))
    //{
      //$error[] = $lang->signup['invalidemail2'];
    //}
	
	
	// Check banned emails
	if(is_banned_email($email, true))
	{
		$error[] = $lang->signup['banned_email'];
		
	}
	
	
	

    if (email_exists ($email))
    {
      $error[] = $lang->signup['invalidemail3'];
    }

    if ($password != $password2)
    {
      $error[] = $lang->signup['passe1'];
    }

    if (strlen ($password) < 6)
    {
      $error[] = $lang->signup['passe2'];
    }

    if (40 < strlen ($password))
    {
      $error[] = $lang->signup['passe3'];
    }

    if ($password == $username)
    {
      $error[] = $lang->signup['passe4'];
    }

    if (!in_array ($usergroup, $allowed_usergroups))
    {
      $error[] = $lang->adduser['invalidug'];
    }
	
	
	if (!empty ($_POST['avatar_url']))
    {
          @clearstatcache ();
          $avatar = (isset ($_POST['avatar_url']) ? $_POST['avatar_url'] : '');
          $image_info = @getimagesize ($avatar);
          if ((!$remote_file = @fopen ($avatar, 'rb') OR !$image_info))
          {
            $error = show__message ($lang->usercp['a_error1']);
            unset ($avatar);
          }
          else
          {
            $user_avatar_size = 0;
            do
            {
              if ((strlen (@fread ($remote_file, 1)) == 0 OR $f_avatar_maxsize < $user_avatar_size))
              {
                break;
              }

              ++$user_avatar_size;
            }while (!(true));

            @fclose ($remote_file);
            //$error = check_avatar ($image_info[0], $image_info[1], $image_info['mime'], $user_avatar_size);
            if ($error)
            {
              unset ($avatar);
            }
          }
     }
	 
	 $avatar_dimensions = $image_info[0]."|".$image_info[1];
	 
	 // Generate the user login key
	 $user['loginkey'] = generate_loginkey();
	 
	 // Combine the password and salt
	 $password_fields = create_password($password, false, $user);
	 $user = array_merge($user, $password_fields);

    if (count ($error) == 0)
    {
      
      $added = TIMENOW;
  

	  	$user_insert_data = array(
			"username" => $db->escape_string($username),
			"password" => $user['password'],
			"salt" => $user['salt'],
			"loginkey" => $user['loginkey'],
			"added" => $db->escape_string($added),
			"ustatus" => 'confirmed',
			"email" => $db->escape_string($email),
			"usergroup" => $usergroup,
			"additionalgroups" => $additionalgroups,
	        "modcomment" => $db->escape_string(gmdate ('Y-m-d') . ' - ' . $modcomment),
	        "seedbonus" =>$seedbonus,
	        "invites" => $invites,
	        "uploaded" => $uploaded,
	        "downloaded" => $downloaded,
			"timezone" => '2',
			"avatar" => $avatar,
		    "avatardimensions" => $avatar_dimensions,
		    "avatartype" => "remote",
			"showsigs" => '1',
			"showavatars" => '1',
			"showredirect" => '1',
			"ignorelist" => '',
			"buddylist" => '',
			"pmfolders" => "0**$%%$1**$%%$2**$%%$3**$%%$4**"
			
			
		
		);

       $db->insert_query("users", $user_insert_data);
	   
	   
	  
      if ($db->affected_rows ())
      {
        $id = $db->insert_id ();
		
		
		$user['user_fields']['ufid'] = $id;
		
		
		$pfcache = $cache->read('profilefields');

		if(is_array($pfcache))
		{
			foreach($pfcache as $profile_field)
			{
				if(array_key_exists("fid{$profile_field['fid']}", $user['user_fields']))
				{
					continue;
				}
				$user['user_fields']["fid{$profile_field['fid']}"] = '';
			}
		}

		$db->insert_query("userfields", $user['user_fields'], false);
		
		
		// Update forum stats
		update_stats(array('numusers' => '+1'));
		
		
		
       
		$usern = htmlspecialchars_uni ($username);
	   
	    require_once INC_PATH . '/functions_pm.php';
            
	    $pm = array(
			'subject' => sprintf ($lang->signup['welcomepmsubject'], $SITENAME),
			'message' => sprintf ($lang->signup['welcomepmbody'], $usern, $SITENAME, $BASEURL),
			'touid' => $id
		);
					
					
		/// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
		$pm['sender']['uid'] = -1;
		send_pm($pm, -1, true);
		
		
		
		
		
		
        if ($confirm == 'yes')
        {
          $editsecret = mksecret ();
          if ($db->sql_query ('REPLACE INTO ts_user_validation (editsecret, userid) VALUES (' . $db->sqlesc ($editsecret) . ', ' . $db->sqlesc ($id) . ')'))
          {
            $psecret = md5 ($editsecret);
            $body = sprintf ($lang->signup['verifiyemailbody'], $username, $BASEURL, $id, $psecret, $SITENAME);
            sent_mail ($email, sprintf ($lang->signup['verifiyemailsubject'], $SITENAME), $body, 'signup', false);
          }
        }

        write_log ('New Account Created by ' . $CURUSER['username'] . '.  Account Name: ' . htmlspecialchars_uni ($username));
        redirect ($BASEURL . '/' . ($confirm == 'yes' ? 'checkuser' : 'member') . '.php?action=profile&id=' . $id, '', '', 3, false, false);
        exit ();
      }
      else
      {
        $error[] = $lang->global['error'];
      }
    }
  }

  stdhead ($lang->adduser['title']);
  if (0 < count ($error))
  {
    
	
   echo '
   
   <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
   <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">


   <div class="container mt-3">
   <div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">Detect Errors</h2>
          <p class="mb-0 opacity-75"></p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
          ' . implode ('<br />', $error) . '
        </div>
      </div>
    </div>
	</div>';
   
  
   
   
   
  
    unset ($error);
    echo '<br />';
  }

  echo '<form method="POST" action="' . $_this_script_ . '"><input type="hidden" name="act" value="adduser">';
 
  echo '
  

<div class="container mt-3">
  <div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">'.$lang->adduser['title'].'</div>
    <div class="card-body">
	
	

	
	
	
	
	




<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="username">Username:</label>
		<input type="text" class="form-control form-control-sm border" name="username"  value="' . ($username ? htmlspecialchars_uni ($username) : '') . '" />
		</div>
		<div class="col-lg-6">
		<label for="usergroup">Usergroup</label>
		' . $ugs . '			
		</div>
		
	</div>
</div>


















	
	
	
	
	
	
	
	
	<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="password">Password:</label>
		<input type="password" class="form-control form-control-sm border" name="password" id="password" />
		</div>
		<div class="col-lg-6">
		<label for="password2">Confirm Password:</label>
		<input type="password" class="form-control form-control-sm border" name="password2" id="password2" style="width: 100%" />			
		</div>
		<div class="col" style="display: none" id="password_status">&nbsp;</div>
	</div>
</div>








<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="email">Email:</label>
		<input type="text" class="form-control form-control-sm border" name="email" value="' . ($email ? htmlspecialchars_uni ($email) : '') . '" />
		</div>
		<div class="col-lg-6">
		<label for="seedbonus">' . $lang->adduser['bonus'] . '</label>
		<input type="text" class="form-control form-control-sm border" name="seedbonus" value="' . ($seedbonus ? htmlspecialchars_uni ($seedbonus) : '') . '" />			
		</div>
		<div class="col" style="display: none" id="password_status">&nbsp;</div>
	</div>
</div>







<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="uploaded">Uploaded:</label>
		<input type="text" class="form-control form-control-sm border" name="uploaded" value="' . ($uploaded ? htmlspecialchars_uni ($uploaded) : '') . '" />
		</div>
		<div class="col-lg-6">
		<label for="downloaded">Downloaded:</label>
		<input type="text" class="form-control form-control-sm border" name="downloaded" value="' . ($downloaded ? htmlspecialchars_uni ($downloaded) : '') . '" />			
		</div>
		
	</div>
</div>




<div class="py-3 border-bottom">
	<div class="row g-3">
		<div class="col-lg-6">
<label for="modcomment">' . $lang->adduser['comment'] . '</label>
		<input type="text" class="form-control form-control-sm border" name="modcomment" value="' . ($modcomment ? $modcomment : '') . '" />
		</div>
		<div class="col-lg-6">
		<label for="invites">' . $lang->adduser['invites'] . '</label>
		<input type="text" class="form-control form-control-sm border" name="invites" value="' . ($invites ? htmlspecialchars_uni ($invites) : '') . '" />			
		</div>
		
	</div>
</div>



<div class="border-bottom pb-3">
<label for="username">Avatar:</label>
<input type="text" class="form-control form-control-sm border" name="avatar_url" value="' . ($avatar ? htmlspecialchars_uni ($avatar) : '') . '" />
</div>


     ' . $lang->adduser['options'] . '
	<input type="checkbox" class="form-check-input" name="confirm" class="inlineimg" value="yes"' . ($confirm == 'yes' ? ' checked="checked"' : '') . ' /> ' . $lang->adduser['o1'] . '			
		

</div> 
    

	
	<div class="card-footer text-center">
	<tr><td colspan=3 align=center>
	<input type="submit" class="btn btn-primary" value="' . $lang->adduser['title'] . '" /></td></tr>
	</div>
	
  
  
  </div>
</div>
</form>


';
  //_form_header_close_ ();
  echo '</form>';
  stdfoot ();
?>
