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

  define ('AB_VERSION', 'New Amountbonus Mod v.0.8 by xam');
  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    $seedbonus = intval ($_POST['seedbonus']);
    if (empty ($seedbonus))
    {
      stderr ('Error, Dont leave any fields blank!');
    }
    else
    {
      if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
      {
        $eol = '
';
      }
      else
      {
        if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
        {
          $eol = '
';
        }
        else
        {
          $eol = '
';
        }
      }

      $modcomment = gmdate ('Y-m-d') . ' - Got ' . $seedbonus . ' Bonus Points from ' . $CURUSER['username'] . ' (Amount Bonus Tool)' . $eol;
      $seedbonus = $db->sqlesc ($seedbonus);
      if ($_POST['toall'] == 'yes')
      {
        $usergroup = intval ($_POST['usergroup']);
        if ((empty ($usergroup) OR !is_valid_id ($usergroup)))
        {
          $db->sql_query ('' . 'UPDATE users SET seedbonus = seedbonus+' . $seedbonus . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ', modcomment) WHERE status=\'confirmed\'');
          $to = 'Everyone';
        }
        else
        {
          $db->sql_query ('' . 'UPDATE users SET seedbonus = seedbonus+' . $seedbonus . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ', modcomment) WHERE status=\'confirmed\' AND usergroup=' . $db->sqlesc ($usergroup));
          $to = get_user_class_name ($usergroup);
        }

        write_log ('' . $seedbonus . ' bonus point is sent to following usergroup(s): ' . $to . ' by ' . $CURUSER['username'] . ' (Amountbonus Tool)');
        stderr ('Bonus, ' . $seedbonus . ' bonus point is sent to ' . $to . '...');
      }
      else
      {
        if (($_POST['username'] == '' OR $_POST['seedbonus'] == ''))
        {
          stderr ('Error', 'Missing form data.');
        }

        $username = $db->sqlesc ($_POST['username']);
        ($db->sql_query ('' . 'UPDATE users SET seedbonus=seedbonus+' . $seedbonus . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ('' . ', modcomment) WHERE username=' . $username)));
        $res = $db->sql_query ('' . 'SELECT id FROM users WHERE username=' . $username);
        $arr = mysqli_fetch_row ($res);
        if (!$arr)
        {
          stderr ('Error, Unable to update account.');
        }
        else
        {
          write_log ('' . $_POST['seedbonus'] . ' bonus point is sent to following user: ' . $_POST['username'] . ' by ' . $CURUSER['username'] . ' (Amountbonus Tool)');
        }

        header ('' . 'Location: ' . $BASEURL . '/'.get_profile_link($arr[0]));
        exit ();
      }
    }
  }

  stdhead ('Update Users Upload Amounts');
  echo '
  
  
  <div class="container mt-3">
  
  <div class="card">
  <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
  echo '
  
  
  
  
  <div class="card-header rounded-bottom text-19 fw-bold">Send Bonus Points (to a specific user or all)</div>';
  $values = '
  </br>
  <tr class="subheader"><td width="50%" align="center">Username: <label>
  <input type="text" class="form-control" name="username"></label></td>
  <td width="50%" align="left">Point(s): <label><input type="text" class="form-control" name="seedbonus"/></label>';
  $hidden_values = '<input type=hidden name=act value=amountbonus>';
  _form_open_ ($values, $hidden_values);
  _form_close_ ('send');
  echo '</td></tr></table></table><br />';
  $selectbox = _selectbox_ ('Usergroup', 'usergroup');
  echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
  echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="colhead" colspan="4" align="center">To ALL Users</td></tr>';
  $values = '<tr class="subheader"><td width="50%" align="center">' . $selectbox . '</td><td width="50%" align="left">Point(s): <label><input type="text" class="form-control" name="seedbonus"/></label> ';
  $hidden_values = array ('<input type=hidden name=act value=amountbonus>', '<input type=hidden name=toall value=yes>');
  _form_open_ ($values, $hidden_values);
  _form_close_ ('send');
  echo '</td></tr></table></table>
  
  
  </div>
</div>
  ';
  stdfoot ();
?>
