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
	<select name="classamount" style="width: 145px;" class="form-select">
	<option value="0" style="color: gray;">( amount )</option>';
    $i = 1;
    while ($i < 51)
    {
      print '' . '<option value=' . $i . '>' . $i . ' GB</option>
';
      ++$i;
    }

    echo '</select></label>';
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('DA_VERSION', '0.2 by xam');
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

    $query = 'enabled=\'yes\' AND status=\'confirmed\'' . ($class ? ' AND usergroup=' . $class : '');
    if ($_POST['doit'] == 'yes')
    {
      if (($_POST['classamount'] < 1 OR 51 < $_POST['classamount']))
      {
        stderr ('Error, Don\'t leave any fields blank!');
      }

      $modcomment = gmdate ('Y-m-d') . ' - Got ' . mksize ($_POST['classamount'] * 1024 * 1024 * 1024) . ' Download Amount from ' . $CURUSER['username'] . ' (Download Add Tool)' . $eol;
      $dlamount = $db->sqlesc ($_POST['classamount'] * 1024 * 1024 * 1024);
      ($db->sql_query ('' . 'UPDATE users SET downloaded = downloaded + ' . $dlamount . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ('' . ', modcomment) WHERE ' . $query)));
      write_log ('' . $_POST['classamount'] . ' GB download amount is sent to following usergroup(s): ' . $class . ' by ' . $CURUSER['username'] . ' (Download Add Tool)');
      stderr ('Download', $_POST['classamount'] . 'GB Download is sent to ' . ($class ? 'following class: ' . get_user_class_name ($class) : 'everyone...'));
      exit ();
    }

    if (($_POST['username'] == '' OR $_POST['downloaded'] == ''))
    {
      stderr ('Error, Don\'t leave any fields blank!');
    }

    $username = $db->sqlesc ($_POST['username']);
    $downloaded = $db->sqlesc ($_POST['downloaded'] * 1024 * 1024 * 1024);
    $modcomment = gmdate ('Y-m-d') . ' - Got ' . mksize ($_POST['downloaded'] * 1024 * 1024 * 1024) . ' Download Amount from ' . $CURUSER['username'] . ' (Download Add Tool)' . $eol;
    ($db->sql_query ('' . 'UPDATE users SET downloaded= downloaded + ' . $downloaded . ', modcomment=CONCAT(' . $db->sqlesc ($modcomment . '') . ('' . ', modcomment) WHERE username=' . $username . ' AND ' . $query)));
    ($res = $db->sql_query ('' . 'SELECT id FROM users WHERE username=' . $username));
    $arr = mysqli_fetch_row ($res);
    if (!$arr)
    {
      stderr ('Error, Unable to update account.');
    }
    else
    {
      write_log ('' . $_POST['downloaded'] . ' GB download amount is sent to following user: ' . $_POST['username'] . ' by ' . $CURUSER['username'] . ' (Download Add Tool)');
    }

    header ('' . 'Location: ' . $BASEURL . '/userdetails.php?id=' . $arr['0']);
    exit ();
  }

  $usergroups = _selectbox_ (NULL, 'usergroup');
  stdhead ('Update Users Download Amounts');
  
  
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Update Users downloaded Amounts
	</div>
	 </div>
		</div>';
  
  
  
  
  
  echo '
  
  <div class="container mt-3">
  <div class="card">
  <form method="post" action="' . $_this_script_ . '">';
  
  echo '<tr><td class="rowhead">User name: </td><td class="row1">
  
  <label>
  <input type="text" name="username" class="form-control"/>
  </label>
  
  </td></tr>';
  echo '<tr><td class="rowhead">Amount (GB): </td><td class="row1">
  
  <label>
  <input type="text" name="downloaded" class="form-control"/> 
  </label>
  
  <input type="submit" value="do it" class="btn btn-primary"/></td></tr>';
  echo '</form>';
  
 
  echo '<br /></div></div>';
 
  
  
   echo '
  </br>
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Send xGB download amount to everyone!
	</div>
	 </div>
		</div>';
  
  
  echo '
  
  <div class="container mt-3">
  <div class="card">
  <form action="' . $_this_script_ . '" method="post">';

  echo '<tr><td class="row1" align="center">Usergroup: 
<input type = "hidden" name = "doit" value = "yes" />';
  echo $usergroups . ' ';
  class_amount ();
  echo '<input type="submit" value="do it" class="btn btn-primary" />
</td></tr>';

  echo '</form></div></div>';
  
  stdfoot ();
?>
