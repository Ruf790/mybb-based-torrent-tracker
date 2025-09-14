<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function check ($id)
  {
    if (!is_valid_id ($id))
    {
      return stderr ('Error', 'Invalid ID');
    }

    return true;
  }

  function safe_query ($query, $id, $where = '', $returnto = false)
  {
    global $_this_script_;
    global $_this_script_no_act;
	global $db;
    $query = sprintf ('' . $query . ' WHERE id =\'%s\'',$db->escape_string ($id));
    $result = $db->sql_query ($query);
    if (!$result)
    {
      return sqlerr (__FILE__, 38);
    }

    if ($returnto)
    {
      redirect ($_this_script_no_act . '?act=viewunbaniprequest');
      return null;
    }

    redirect ($_this_script_ . '&update=' . htmlspecialchars ($where));
  }

  function searchform ()
  {
    global $_this_script_;
    echo '
	<br />
	<form method=post name=search action="' . $_this_script_ . '">
	<input type=hidden name=action value=searchip>
	
	
	
	
	
	<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Search IP
	</div>
	 </div>
		</div>
		
	
	
	<div class="container mt-3">
  <div class="card">
	<tr><td colspan=2><div align=center>Please Enter Ip Address: <label><input type=text name=ip size=25 class="form-control"></label> <input type=submit name=submit value=Search class="btn btn-primary"></div></td></tr>
	</div>
	</div>
	
	</form>';
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('L_VERSION', '1.0 by xam');
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'showlist'));
  $id = (isset ($_POST['id']) ? htmlspecialchars ($_POST['id']) : (isset ($_GET['id']) ? htmlspecialchars ($_GET['id']) : ''));
  $update = (isset ($_POST['update']) ? htmlspecialchars ($_POST['update']) : (isset ($_GET['update']) ? htmlspecialchars ($_GET['update']) : ''));
  $countrows = number_format (tsrowcount ('id', 'loginattempts'));
  $page = 0 + $_GET['page'];
  $order = $_GET['order'];
  if ($order == 'id')
  {
    $orderby = 'id';
  }
  else
  {
    if ($order == 'ip')
    {
      $orderby = 'ip';
    }
    else
    {
      if ($order == 'added')
      {
        $orderby = 'added';
      }
      else
      {
        if ($order == 'attempts')
        {
          $orderby = 'attempts';
        }
        else
        {
          if ($order == 'type')
          {
            $orderby = 'type';
          }
          else
          {
            if ($order == 'status')
            {
              $orderby = 'banned';
            }
            else
            {
              $orderby = 'attempts';
            }
          }
        }
      }
    }
  }

  $otype = 'DESC';
  if ($_GET['otype'] == 'DESC')
  {
    $otype = 'ASC';
  }

  //$perpage = $ts_perpage;
  //list ($pagertop, $pagerbottom, $limit) = pager ($perpage, $countrows, $_this_script_ . '&order=' . $order . '&');
  if ($action == 'showlist')
  {
    stdhead ('Max. Login Attemps - Show List');
  
	
	echo '
	<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Failed Login Attempts
	</div>
	 </div>
		</div>';
	
	
	
   
    if ($update)
    {
      $msg = '<tr><td colspan=6><b>' . htmlspecialchars ($update) . ' Successful!</b></td></tr>
';
    }

    ($res = $db->sql_query ('' . 'SELECT *
				FROM  loginattempts
				ORDER BY ' . $orderby . ' ' . $otype . ' ' . $limit));
    if ($db->num_rows ($res) == 0)
    {
      print '<tr><td colspan=2><b>Nothing found</b></td></tr>
';
    }
    else
    {
      print '



<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th><a href=' . $_this_script_ . '&order=id&otype=' . $otype . '>ID</a></th>
        <th><a href=' . $_this_script_ . '&order=ip&otype=' . $otype . '>Ip Address</a></th>
        <th><a href=' . $_this_script_ . '&order=added&otype=' . $otype . '>Action Time</a></th>
		<th><a href=' . $_this_script_ . '&order=attempts&otype=' . $otype . '>Attempts</a></th>
        <th><a href=' . $_this_script_ . '&order=type&otype=' . $otype . '>Attempt Type</a></th>
        <th><a href=' . $_this_script_ . '&order=status&otype=' . $otype . '>Status</a></th>
	
      </tr>
    </thead>';







      while ($arr = mysqli_fetch_array ($res))
      {
        print '' . '<tr><td align=center>' . $arr['id'] . '</td><td align=left>' . htmlspecialchars_uni ($arr['ip']) . ('' . ' [<a href=\'' . $BASEURL . '/admin/index.php?act=ipsearch&do=1&ip=') . htmlspecialchars_uni ($arr['ip']) . '\' target=\'_blank\'><b>search on db</b></a>]</td><td align=left>' . my_datee ($dateformat, $arr['added']) . ' ' . my_datee ($timeformat, $arr['added']) . ('' . '</td><td align=center>' . $arr['attempts'] . '</td><td align=left>') . ($arr['type'] == 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!') . '</td><td align=left>' . ($arr['banned'] == 'yes' ? '<span class="badge bg-danger"<b>banned</b></span> <a href=' . $_this_script_ . ('' . '&action=unban&id=' . $arr['id'] . '><font color=green>[<b>unban</b>]</font></a>') : '<font color=green><b>not banned</b></font> <a href=' . $_this_script_ . ('' . '&action=ban&id=' . $arr['id'] . '><span class="badge bg-danger">[<b>ban</b>]</span></a>')) . '  <a OnClick="return confirm(\'Are you wish to delete this attempt?\');" href=' . $_this_script_ . ('' . '&action=delete&id=' . $arr['id'] . '>[<b>delete</b></a>] <a href=') . $_this_script_ . ('' . '&action=edit&id=' . $arr['id'] . '><font color=blue>[<b>edit</b></a>]</font></td></tr>
');
      }
    }

    print $msg;
    print '</table></div></div>
';
    if ($perpage < $countrows)
    {
      echo '<tr><td colspan=2>' . $pagerbottom . '</td></tr>';
    }

    echo '</table></div></div>';
    searchform ();
    stdfoot ();
    return 1;
  }

  if ($action == 'ban')
  {
    check ($id);
    stdhead ('Max. Login Attemps - BAN');
    safe_query ('UPDATE loginattempts SET banned = \'yes\'', $id, 'Ban');
    redirect ($_this_script_ . '&update=Ban');
    return 1;
  }

  if ($action == 'unban')
  {
    check ($id);
    stdhead ('Max. Login Attemps - UNBAN');
    safe_query ('UPDATE loginattempts SET banned = \'no\'', $id, 'Unban');
    return 1;
  }

  if ($action == 'delete')
  {
    check ($id);
    if (isset ($_GET['return']))
    {
      safe_query ('DELETE FROM loginattempts', $id, 'Delete', true);
      return 1;
    }

    stdhead ('Max. Login Attemps - DELETE');
    safe_query ('DELETE FROM loginattempts', $id, 'Delete');
    return 1;
  }

  if ($action == 'edit')
  {
    check ($id);
    stdhead ('Max. Login Attemps - EDIT (' . htmlspecialchars ($id) . ')');
    $query = sprintf ('SELECT * FROM loginattempts WHERE id =\'%s\'', $db->escape_string ($id));
    ($result = $db->sql_query ($query));
    $a = $db->fetch_array ($result);
    print '
	
	<div class="container mt-3">
    <div class="card">
   
	<table border=0 cellspacing=0 cellpadding=5 width=100%>
';
    print '<tr><td><p>IP Address: <b>' . htmlspecialchars ($a['ip']) . '</b></p>';
    print '<p>Action Time: <b>' . my_datee('relative', $a['added']) . '</b></p></tr></td>';
    print '<form method=\'post\' action=\'' . $_this_script_ . '\'>';
    print '<input type=\'hidden\' name=\'action\' value=\'save\'>';
    print '' . '<input type=\'hidden\' name=\'id\' value=\'' . $a['id'] . '\'>';
    print '' . '<input type=\'hidden\' name=\'ip\' value=\'' . $a['ip'] . '\'>';
    if ($_GET['return'] == 'yes')
    {
      print '<input type=\'hidden\' name=\'returnto\' value=\'' . $_this_script_no_act . '?act=viewunbaniprequest\'>';
    }

    print '' . '<tr><td>Attempts <label><input type=\'text\' class=\'form-control\' name=\'attempts\' value=\'' . $a['attempts'] . '\'></label>';
    print '<tr><td>Attempt Type <label><select name=\'type\' class="form-select form-select-sm border pe-5 w-auto"><option value=\'login\' ' . ($a['type'] == 'login' ? 'selected' : '') . '>Login Attempt</option><option value=\'recover\' ' . ($a['type'] == 'recover' ? 'selected' : '') . '>Recover Password Attempts</option></select></label></tr></td>';
    print '<tr><td>Current Status <label><select name=\'banned\' class="form-select form-select-sm border pe-5 w-auto"><option value=\'yes\' ' . ($a['banned'] == 'yes' ? 'selected' : '') . '>Banned!</option><option value=\'no\' ' . ($a['banned'] == 'no' ? 'selected' : '') . '>Not Banned!</option></select></label> <input type=\'submit\' name=\'submit\' value=\'Save\' class="btn btn-primary"></tr></td>';
    print '</table>
	</div></div>';
    stdfoot ();
    return 1;
  }

  if ($action == 'save')
  {
    $id = $db->sqlesc (0 + $_POST['id']);
    $ip = $db->sqlesc ($_POST['ip']);
    $attempts = $db->sqlesc ($_POST['attempts']);
    $type = $db->sqlesc ($_POST['type']);
    $banned = $db->sqlesc ($_POST['banned']);
    check ($id);
    check ($attempts);
    ($db->sql_query ('' . 'UPDATE loginattempts SET attempts = ' . $attempts . ', type = ' . $type . ', banned = ' . $banned . ' WHERE id = ' . $id . ' LIMIT 1'));
    if ($_POST['returnto'])
    {
      redirect (fix_url ($_POST['returnto']));
      return 1;
    }

    redirect ($_this_script_ . '&update=Edit');
    return 1;
  }

  if ($action == 'searchip')
  {
    $ip = $db->escape_string ($_POST['ip']);
    ($search = $db->sql_query ('' . 'SELECT *
				FROM  loginattempts
				WHERE ip LIKE \'%' . $ip . '%\''));
    stdhead ('Max. Login Attemps - Search');
    echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="colhead" colspan="4" align="center">Failed Login Attempts</td></tr>';
    print '<table border=1 cellspacing=0 cellpadding=5 width=100%>
';
    if ($db->num_rows ($search) == 0)
    {
      print '<tr><td colspan=2><b>Sorry, nothing found!</b></td></tr>
';
    }
    else
    {
      print '' . '<tr><td class=subheader align=center><a href=' . $_this_script_ . '&order=id&otype=' . $otype . '>ID</a></td><td class=subheader align=left><a href=' . $_this_script_ . '&order=ip&otype=' . $otype . '>Ip Address</a></td><td class=subheader align=left><a href=' . $_this_script_ . '&order=added&otype=' . $otype . '>Action Time</a></td>' . ('' . '<td class=subheader align=center><a href=' . $_this_script_ . '&order=attempts&otype=' . $otype . '>Attempts</a></td><td class=subheader align=left><a href=' . $_this_script_ . '&order=type&otype=' . $otype . '>Attempt Type</a></td><td class=subheader align=left><a href=' . $_this_script_ . '&order=status&otype=' . $otype . '>Status</a></td></tr>
');
      while ($arr =$db->fetch_array ($search))
      {
        print '' . '<tr><td align=center>' . $arr['id'] . '</td><td align=left>' . htmlspecialchars_uni ($arr['ip']) . ('' . ' [<a href=\'' . $BASEURL . '/admin/index.php?act=ipsearch&do=1&ip=') . htmlspecialchars_uni ($arr['ip']) . '\' target=\'_blank\'><b>search on db</b></a>]</td><td align=left>' . my_datee ($dateformat, $arr['added']) . ' ' . my_datee ($timeformat, $arr['added']) . ('' . '</td><td align=center>' . $arr['attempts'] . '</td><td align=left>') . ($arr['type'] == 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!') . '</td><td align=left>' . ($arr['banned'] == 'yes' ? '<font color=red><b>banned</b></font> <a href=' . $_this_script_ . ('' . '&action=unban&id=' . $arr['id'] . '><font color=green>[<b>unban</b>]</font></a>') : '<font color=green><b>not banned</b></font> <a href=' . $_this_script_ . ('' . '&action=ban&id=' . $arr['id'] . '><font color=red>[<b>ban</b>]</font></a>')) . '  <a OnClick="return confirm(\'Are you wish to delete this attempt?\');" href=' . $_this_script_ . ('' . '&action=delete&id=' . $arr['id'] . '>[<b>delete</b></a>] <a href=') . $_this_script_ . ('' . '&action=edit&id=' . $arr['id'] . '><font color=blue>[<b>edit</b></a>]</font></td></tr>
');
      }
    }

    print '</table></table></table>
';
    searchform ();
    stdfoot ();
    return 1;
  }

  stderr ('Error', 'Invalid Action');
?>
