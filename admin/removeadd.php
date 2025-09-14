<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


 
  
  function class_amount ()
  {
    echo '
	<label>
	<select name="classamount" style="width: 145px;" class="form-select form-select-sm border pe-5 w-auto">
	<option value="0" style="color: gray;">( amount )</option>';
    $i = 1;
    while ($i < 51)
    {
      print '' . '<option value=\'' . $i . '\'>' . $i . ' GB</option>
';
      ++$i;
    }

    echo '</select></label>';
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('RA_VERSION', '0.2 by xam');
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

  if ($HTTP_SERVER_VARS['REQUEST_METHOD'] == 'POST')
  {
    $class = (int)$_POST['usergroup'];
    if (($class == '-' OR !is_valid_id ($class)))
    {
      $class = '';
    }

    $query = ($class ? ' WHERE usergroup=' . $class : '');
    if ($_POST['doit'] == 'yes')
    {
      if (($_POST['classamount'] < 1 OR 51 < $_POST['classamount']))
      {
        stderr ('Error', 'Don\'t leave any fields blank!');
      }

      $modcomment = gmdate ('Y-m-d') . ' - Removed ' . mksize ($_POST['classamount'] * 1024 * 1024 * 1024) . ' Upload Amount by ' . $CURUSER['username'] . ' (Upload Remove Tool)' . $eol;
      $ulamount = $db->sqlesc ($_POST['classamount'] * 1024 * 1024 * 1024);
      ($db->sql_query ('' . 'UPDATE users SET uploaded = uploaded - ' . $ulamount . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ', modcomment)' . $query));
      stderr ('Upload', $_POST['classamount'] . 'GB upload is removed to ' . ($class ? 'following usergroup: ' . get_user_class_name ($class) : 'ALL'));
      exit ();
    }

    if (($_POST['username'] == '' OR $_POST['uploaded'] == ''))
    {
      stderr ('Error', 'Don\'t leave any fields blank!');
    }

    $username = $db->sqlesc ($_POST['username']);
    $uploaded = $db->sqlesc ($_POST['uploaded'] * 1024 * 1024 * 1024);
    $modcomment = gmdate ('Y-m-d') . ' - Removed ' . mksize ($_POST['uploaded'] * 1024 * 1024 * 1024) . ' Upload Amount by ' . $CURUSER['username'] . ' (Upload Remove Tool)' . $eol;
    ($db->sql_query ('' . 'UPDATE users SET uploaded= uploaded - ' . $uploaded . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ('' . ', modcomment) WHERE username=' . $username)));
    ($res = $db->sql_query ('' . 'SELECT id FROM users WHERE username=' . $username));
    $arr = mysqli_fetch_row ($res);
    if (!$arr)
    {
      stderr ('Error', 'Unable to update account.');
    }

    header ('' . 'Location: ' . $BASEURL . '/userdetails.php?id=' . $arr['0']);
    exit ();
  }

  $usergroups = _selectbox_ (NULL, 'usergroup');
  stdhead ('Remove Users Upload Amounts');
  
  
  
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Remove Users Uploaded Amounts
	</div>
	 </div>
		</div>';
  
  
  
  echo '<form method="post" action="' . $_this_script_ . '">';
  
  echo '
  
  <div class="container mt-3">
  <tr>
  <td class="rowhead">User name: </td><td class="row1">
  
  <label>
  <input type="text" class="form-control" name="username"/>
  </label>
  
  </td></tr>
  <td class="rowhead">Amount (GB): </td><td class="row1">
  
  <label>
  <input type="text" class="form-control" name="uploaded" /> 
  </label>
  
   
  <input type="submit" value="do it" class="btn btn-primary"/>
  
  
  </td>
  </tr>
  </div>
  ';
  
  
  
  
  
  echo '</form>';
 
  echo '<br />';
 
  
  
  
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Remove xGB upload amount from everyone!
	</div>
	 </div>
		</div>';
  
  
  
  
  echo '
  
  
  <form action="' . $_this_script_ . '" method="post">
  <div class="container mt-3">';
  
  echo '
  
   
<td class="row1">Usergroup: 
<input type = "hidden" name = "doit" value = "yes" />';
  echo $usergroups . '';
  class_amount ();
  echo '
  <label>
  <input type="submit" value="do it" class="btn btn-primary" />
  </label>';
 
  echo '</td></div></form>';
  
  stdfoot ();
?>
